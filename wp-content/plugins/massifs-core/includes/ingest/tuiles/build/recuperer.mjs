/**
 * Récupération de l'extrait OpenStreetMap du fond de carte.
 *
 *   node recuperer.mjs      (ou : npm run recuperer)
 *
 * C'EST LE SEUL FICHIER DU DÉPÔT QUI TOUCHE LE RÉSEAU. Il se joue à la main,
 * jamais en intégration continue, et n'est JAMAIS un prérequis de
 * `npm run construire` : l'archive qu'il produit est commitée, et c'est elle que
 * le build consomme, hors ligne. Invariant I-9.8 : aucun autre fichier de
 * `includes/ingest/tuiles/**` ne connaît le réseau, ce qu'un `grep` de revue
 * vérifie mécaniquement.
 *
 * Les serveurs de TUILES RENDUES — celui de l'OSMF comme tout autre — sont
 * interdits, au build comme à l'exécution (interdit 17 du contrat #9). Leur nom
 * n'est écrit nulle part dans ce dépôt, pas même pour dire qu'on ne s'en sert
 * pas : la politique d'usage de l'OSMF proscrit le téléchargement systématique,
 * et un rendu aplati ne donnerait de toute façon pas le monochrome du §4.2 de
 * `MASTER.md`. Seule l'API d'INTERROGATION, Overpass, est appelée ici.
 *
 * L'archive en place N'EST JAMAIS écrasée par un échec : tout est validé avant
 * la première écriture, et l'écriture est atomique.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import fs from 'node:fs';
import path from 'node:path';
import {
	AGENT,
	ARCHIVE_OCTETS_MAX,
	ARCHIVE_OCTETS_MIN,
	Arret,
	BORNES_OSM,
	CHEMINS,
	COUCHES,
	DEBORDEMENT_MAX_DEG,
	NORMALISATION,
	POINTS_ACCES,
	SELECTEURS,
	ecrireFc,
	lireEmprise,
	lireFc,
	mapshaper,
	nodeMajeur,
	relatifAuDepot,
	sha256,
	versionMapshaper,
} from './commun.mjs';

/** Délai d'attente d'une réponse Overpass, en millisecondes. */
const DELAI_MS = 900000;

/** Nombre d'essais par point d'accès, et attente entre deux essais. */
const ESSAIS = 3;
const ATTENTE_MS = 20000;

const FACTEUR = Math.pow( 10, NORMALISATION.decimales );

function journal( ligne ) {
	process.stdout.write( `${ ligne }\n` );
}

/** Arrondit une coordonnée à la précision de l'archive. */
function arrondir( valeur ) {
	return Math.round( valeur * FACTEUR ) / FACTEUR;
}

/**
 * Interroge Overpass, avec repli d'instance et réessais.
 *
 * @param {string} requete Programme Overpass QL complet.
 * @return {{point_acces:string,texte:string,ms:number}}
 */
async function interroger( requete ) {
	const echecs = [];

	for ( const point of POINTS_ACCES ) {
		for ( let essai = 1; essai <= ESSAIS; essai += 1 ) {
			const depart = Date.now();

			try {
				const reponse = await fetch( point, {
					method: 'POST',
					headers: { 'User-Agent': AGENT, 'Content-Type': 'text/plain;charset=UTF-8' },
					body: requete,
					signal: AbortSignal.timeout( DELAI_MS ),
				} );

				const texte = await reponse.text();

				if ( 200 === reponse.status ) {
					return { point_acces: point, texte, ms: Date.now() - depart };
				}

				echecs.push( `${ point } essai ${ essai } : HTTP ${ reponse.status }` );
			} catch ( erreur ) {
				echecs.push( `${ point } essai ${ essai } : ${ erreur.message }` );
			}

			if ( essai < ESSAIS ) {
				await new Promise( ( suite ) => setTimeout( suite, ATTENTE_MS * essai ) );
			}
		}
	}

	throw new Arret( `Overpass injoignable :\n  - ${ echecs.join( '\n  - ' ) }` );
}

/**
 * Charge Overpass décodée et contrôlée.
 *
 * Une charge tronquée par timeout est un JSON syntaxiquement VALIDE mais amputé :
 * Overpass le signale par une clé `remark`, que l'on refuse, et le dénombrement
 * rattrape les troncatures qu'elle ne signale pas.
 */
function decoder( nom, texte ) {
	let charge;

	try {
		charge = JSON.parse( texte );
	} catch ( erreur ) {
		throw new Arret( `Couche « ${ nom } » : réponse illisible — ${ erreur.message }` );
	}

	if ( ! charge || ! Array.isArray( charge.elements ) ) {
		throw new Arret( `Couche « ${ nom } » : aucune liste d'éléments dans la réponse.` );
	}

	if ( charge.remark ) {
		throw new Arret( `Couche « ${ nom } » : Overpass signale « ${ charge.remark } » — charge incomplète, rien n'est écrit.` );
	}

	const bornes = BORNES_OSM[ nom ];
	const nombre = charge.elements.length;

	if ( nombre < bornes.plancher || nombre > bornes.plafond ) {
		throw new Arret(
			`Couche « ${ nom } » : ${ nombre } éléments, hors de [${ bornes.plancher }, ${ bornes.plafond }]. ` +
				'Charge aberrante ou tronquée : rien n\'est écrit, l\'archive en place est intacte.'
		);
	}

	return { charge, nombre };
}

/* -------------------------------------------------------------------------- */
/* Conversion Overpass -> géométries GeoJSON                                   */
/* -------------------------------------------------------------------------- */

/** Un anneau est-il fermé ? */
function ferme( anneau ) {
	return anneau.length > 3 && anneau[ 0 ][ 0 ] === anneau[ anneau.length - 1 ][ 0 ] && anneau[ 0 ][ 1 ] === anneau[ anneau.length - 1 ][ 1 ];
}

/**
 * Chaîne des segments en anneaux fermés.
 *
 * Un multipolygone OSM ne porte pas ses anneaux tout faits : il porte des ways
 * ouverts qu'il faut recoudre bout à bout, dans un sens ou dans l'autre. Sans
 * cette étape, l'Étang de Berre — un multipolygone de plusieurs dizaines de
 * membres — ne serait jamais peint.
 */
function chainer( segments ) {
	const anneaux = [];
	const restants = segments.map( ( segment ) => segment.slice() );

	while ( restants.length > 0 ) {
		let courant = restants.pop();
		let progresse = true;

		while ( progresse && ! ferme( courant ) ) {
			progresse = false;

			for ( let i = 0; i < restants.length; i += 1 ) {
				const segment = restants[ i ];
				const fin = courant[ courant.length - 1 ];

				if ( segment[ 0 ][ 0 ] === fin[ 0 ] && segment[ 0 ][ 1 ] === fin[ 1 ] ) {
					courant = courant.concat( segment.slice( 1 ) );
					restants.splice( i, 1 );
					progresse = true;
					break;
				}

				const queue = segment[ segment.length - 1 ];

				if ( queue[ 0 ] === fin[ 0 ] && queue[ 1 ] === fin[ 1 ] ) {
					courant = courant.concat( segment.slice( 0, -1 ).reverse() );
					restants.splice( i, 1 );
					progresse = true;
					break;
				}
			}
		}

		// Un anneau qui ne se referme pas est ABANDONNÉ, jamais refermé d'office :
		// le fermer par une corde inventerait un trait de côte qui n'existe pas.
		if ( ferme( courant ) ) {
			anneaux.push( courant );
		}
	}

	return anneaux;
}

/** Le point est-il dans l'anneau ? Sert à rattacher un trou à son anneau extérieur. */
function dansAnneau( point, anneau ) {
	let dedans = false;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i += 1 ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];

		if ( yi > point[ 1 ] !== yj > point[ 1 ] && point[ 0 ] < ( ( xj - xi ) * ( point[ 1 ] - yi ) ) / ( yj - yi ) + xi ) {
			dedans = ! dedans;
		}
	}

	return dedans;
}

/**
 * Convertit une charge Overpass en géométries GeoJSON, sans aucune étiquette.
 *
 * Les tags ne sont PAS conservés : la couche est déjà l'information, et un
 * toponyme conservé dans l'archive finirait par se retrouver cuit dans une tuile
 * (arbitrage A-9, `OUVERT`).
 */
function convertir( charge, surfacique ) {
	const geometries = [];

	for ( const element of charge.elements ) {
		if ( 'way' === element.type && Array.isArray( element.geometry ) ) {
			const points = element.geometry.filter( Boolean ).map( ( p ) => [ arrondir( p.lon ), arrondir( p.lat ) ] );

			if ( points.length < 2 ) {
				continue;
			}

			if ( surfacique ) {
				if ( ferme( points ) ) {
					geometries.push( { type: 'Polygon', coordinates: [ points ] } );
				}

				continue;
			}

			geometries.push( { type: 'LineString', coordinates: points } );
			continue;
		}

		if ( 'relation' === element.type && Array.isArray( element.members ) ) {
			const exterieurs = [];
			const interieurs = [];

			for ( const membre of element.members ) {
				if ( ! Array.isArray( membre.geometry ) ) {
					continue;
				}

				const points = membre.geometry.filter( Boolean ).map( ( p ) => [ arrondir( p.lon ), arrondir( p.lat ) ] );

				if ( points.length < 2 ) {
					continue;
				}

				( 'inner' === membre.role ? interieurs : exterieurs ).push( points );
			}

			const anneaux = chainer( exterieurs );
			const trous = chainer( interieurs );

			for ( const anneau of anneaux ) {
				geometries.push( {
					type: 'Polygon',
					coordinates: [ anneau, ...trous.filter( ( trou ) => dansAnneau( trou[ 0 ], anneau ) ) ],
				} );
			}
		}
	}

	return geometries;
}

/** Emprise d'un jeu de géométries. */
function empriseDe( geometries ) {
	const bbox = { ouest: Infinity, sud: Infinity, est: -Infinity, nord: -Infinity };

	const visiter = ( noeud ) => {
		if ( 'number' === typeof noeud[ 0 ] ) {
			bbox.ouest = Math.min( bbox.ouest, noeud[ 0 ] );
			bbox.est = Math.max( bbox.est, noeud[ 0 ] );
			bbox.sud = Math.min( bbox.sud, noeud[ 1 ] );
			bbox.nord = Math.max( bbox.nord, noeud[ 1 ] );
			return;
		}

		for ( const enfant of noeud ) {
			visiter( enfant );
		}
	};

	for ( const geometrie of geometries ) {
		visiter( geometrie.coordinates );
	}

	return bbox;
}

/* -------------------------------------------------------------------------- */
/* Déroulé                                                                     */
/* -------------------------------------------------------------------------- */

async function principal() {
	const emprise = lireEmprise( CHEMINS.referentiel );
	const bbox = `${ emprise.sud },${ emprise.ouest },${ emprise.nord },${ emprise.est }`;
	const extraitLe = new Date().toISOString().replace( /\.\d{3}Z$/, 'Z' );
	const requetes = {};
	const brutes = {};
	const comptes = {};
	const points = {};

	journal( `Emprise du référentiel : ${ bbox } (lue dans ${ relatifAuDepot( CHEMINS.referentiel ) })` );

	for ( const couche of COUCHES ) {
		const requete = `[out:json][timeout:900][bbox:${ bbox }];${ SELECTEURS[ couche.nom ] };out geom;`;
		requetes[ couche.nom ] = requete;

		journal( `  ${ couche.nom } — interrogation…` );

		const reponse = await interroger( requete );
		const { charge, nombre } = decoder( couche.nom, reponse.texte );

		brutes[ couche.nom ] = convertir( charge, couche.surfacique );
		comptes[ couche.nom ] = nombre;
		points[ couche.nom ] = reponse.point_acces;

		journal( `  ${ couche.nom } — ${ nombre } éléments, ${ brutes[ couche.nom ].length } géométries, ${ Math.round( reponse.ms / 1000 ) } s` );
	}

	if ( 0 === brutes.terre.length ) {
		throw new Arret( 'Aucun anneau départemental n\'a pu être recousu : la couche « terre » serait vide.' );
	}

	const empriseTerre = empriseDe( brutes.terre );

	// Recouvrement, jamais égalité : le département déborde l'emprise à l'ouest et
	// la mord de quelques cent-millièmes de degré au sud. Ce qui se contrôle, c'est
	// qu'il la couvre — un polygone amputé de la moitié de ses membres rendrait une
	// côte fausse sans rien casser d'autre.
	const debordements = [
		[ 'ouest', empriseTerre.ouest - emprise.ouest ],
		[ 'sud', empriseTerre.sud - emprise.sud ],
		[ 'est', emprise.est - empriseTerre.est ],
		[ 'nord', emprise.nord - empriseTerre.nord ],
	].filter( ( [ , ecart ] ) => ecart > DEBORDEMENT_MAX_DEG );

	if ( debordements.length > 0 ) {
		throw new Arret(
			'Le polygone départemental ne recouvre pas l\'emprise du référentiel : ' +
				debordements.map( ( [ borne, ecart ] ) => `${ borne } manque de ${ ecart.toFixed( 5 ) }°` ).join( ', ' ) +
				`. Toléré : ${ DEBORDEMENT_MAX_DEG }°. Rien n'est écrit.`
		);
	}

	journal( 'Normalisation (clip au département, simplification, filtrage des îlots)…' );

	const travail = path.join( path.dirname( CHEMINS.archive ), '_travail' );
	const cheminTerre = `${ travail }-terre.json`;
	const couches = {};
	const argv = {};

	fs.mkdirSync( path.dirname( CHEMINS.archive ), { recursive: true } );
	ecrireFc( cheminTerre, brutes.terre );

	try {
		for ( const couche of COUCHES ) {
			const entree = 'terre' === couche.nom ? cheminTerre : `${ travail }-${ couche.nom }.json`;
			const sortie = `${ travail }-${ couche.nom }.out.json`;

			if ( 'terre' !== couche.nom ) {
				ecrireFc( entree, brutes[ couche.nom ] );
			}

			const options = [ entree ];

			if ( NORMALISATION.clip !== couche.nom ) {
				options.push( '-clip', cheminTerre );
			}

			options.push( '-simplify', 'dp', `interval=${ NORMALISATION.intervalle_m }`, 'keep-shapes' );

			if ( couche.surfacique ) {
				options.push( '-filter-islands', `min-area=${ NORMALISATION.aire_min_m2 }` );
			}

			options.push( '-o', `precision=0.${ '0'.repeat( NORMALISATION.decimales - 1 ) }1`, 'format=geojson', sortie );

			argv[ couche.nom ] = mapshaper( options );
			couches[ couche.nom ] = lireFc( sortie );

			journal( `  ${ couche.nom } — ${ couches[ couche.nom ].length } géométries après normalisation` );
		}
	} finally {
		for ( const fichier of fs.readdirSync( path.dirname( CHEMINS.archive ) ) ) {
			if ( fichier.startsWith( '_travail' ) ) {
				fs.unlinkSync( path.join( path.dirname( CHEMINS.archive ), fichier ) );
			}
		}
	}

	const archive = {
		type: 'massifs-fond-de-carte-source',
		version: 1,
		extrait_le: extraitLe,
		bbox: emprise,
		couches: Object.fromEntries(
			COUCHES.map( ( couche ) => [
				couche.nom,
				{ type: 'FeatureCollection', features: couches[ couche.nom ].map( ( geometry ) => ( { type: 'Feature', properties: {}, geometry } ) ) },
			] )
		),
	};

	const octets = Buffer.from( `${ JSON.stringify( archive ) }\n`, 'utf8' );

	if ( octets.length < ARCHIVE_OCTETS_MIN || octets.length > ARCHIVE_OCTETS_MAX ) {
		throw new Arret(
			`Archive de ${ octets.length } octets, hors de [${ ARCHIVE_OCTETS_MIN }, ${ ARCHIVE_OCTETS_MAX }]. ` +
				'Au-delà du plafond elle cesse d\'être commitable, et le §11 du brief se dégraderait en ' +
				'« télécharger avant de construire ». Rien n\'est écrit.'
		);
	}

	const manifeste = {
		a_propos:
			'Provenance de l\'archive OpenStreetMap du fond de carte. ÉMIS PAR `npm run recuperer`, jamais édité à la main. Données © les contributeurs d\'OpenStreetMap, sous Open Database License 1.0.',
		extrait_le: extraitLe,
		points_acces: points,
		agent: AGENT,
		bbox: emprise,
		requetes,
		normalisation: NORMALISATION,
		outillage: { mapshaper: versionMapshaper(), node_major: nodeMajeur() },
		mapshaper_argv: argv,
		comptes_overpass: comptes,
		comptes_normalises: Object.fromEntries( COUCHES.map( ( c ) => [ c.nom, couches[ c.nom ].length ] ) ),
		archive: {
			fichier: relatifAuDepot( CHEMINS.archive ),
			sha256: sha256( octets ),
			octets: octets.length,
		},
	};

	// Tout est contrôlé : on écrit enfin, et en bloc.
	const sorties = [
		{ chemin: CHEMINS.archive, contenu: octets },
		{ chemin: CHEMINS.manifeste_source, contenu: Buffer.from( `${ JSON.stringify( manifeste, null, '\t' ) }\n`, 'utf8' ) },
	];

	for ( const { chemin, contenu } of sorties ) {
		fs.writeFileSync( `${ chemin }.tmp`, contenu );
	}

	for ( const { chemin } of sorties ) {
		fs.renameSync( `${ chemin }.tmp`, chemin );
	}

	journal( '' );
	journal( `Archive : ${ relatifAuDepot( CHEMINS.archive ) } — ${ octets.length } octets, sha256 ${ manifeste.archive.sha256 }` );
	journal( `Manifeste : ${ relatifAuDepot( CHEMINS.manifeste_source ) }` );
	journal( '' );
	journal( 'Archive et manifeste sont à COMMITER : c\'est ce qui rend `npm run construire` reproductible hors ligne.' );
	journal( 'Puis : npm run construire' );
}

try {
	await principal();
} catch ( erreur ) {
	process.stderr.write( `${ erreur instanceof Arret ? erreur.message : erreur.stack }\n` );
	process.exitCode = 1;
}
