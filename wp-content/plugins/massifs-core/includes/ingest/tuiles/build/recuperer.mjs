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
	CLASSES_TOPONYMES,
	COUCHES,
	COUCHES_SOURCE,
	COUCHE_TOPONYMES,
	DEBORDEMENT_MAX_DEG,
	NORMALISATION,
	POINTS_ACCES,
	SELECTEURS,
	dansAnneau,
	ecrireFc,
	ecrireFcPoints,
	featurePoint,
	lireEmprise,
	lireFc,
	lireFcPoints,
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

/**
 * Gardes d'attribut de la couche `toponymes`, gelées au §3 du contrat #71.
 *
 * Elles sont nommées plutôt qu'écrites au fil du code : chacune est citée DEUX fois,
 * dans son test et dans son message, et un seuil recopié finit par ne plus dire ce
 * que le code refuse.
 */
const REJETS_MAX = 0.2;
const VILLES_MIN = 1;
const VILLES_MAX = 10;

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

/**
 * Convertit une charge Overpass en géométries GeoJSON, sans aucune étiquette.
 *
 * Les tags ne sont PAS conservés POUR LES QUATRE COUCHES GÉOMÉTRIQUES : là, la
 * couche EST l'information, et un `name` traîné dans l'archive n'aurait aucun
 * consommateur.
 *
 * La couche `toponymes` est l'exception, et elle est explicite plutôt
 * qu'accidentelle : elle conserve EXACTEMENT TROIS CHAMPS — `nom`, `classe`,
 * `population` — et rien d'autre, parce que #71 renverse l'arbitrage A-9 du
 * contrat #9 et qu'un toponyme cuit dans une tuile est désormais le but. Voir
 * `convertirPoints()`.
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

/**
 * Convertit une charge Overpass de NŒUDS `place` en entités ponctuelles.
 *
 * Trois champs retenus, pas un de plus. `population` absente ou illisible vaut 0,
 * sentinelle non ambiguë : une population réelle de 0 est impossible, et 0 range
 * donc le toponyme en dernier sans jamais se confondre avec une valeur mesurée.
 *
 * Les rejets sont COMPTÉS, pas levés : un nœud isolé sans `name` est une réalité
 * d'OSM, pas une charge amputée. C'est leur PROPORTION qui est contrôlée en aval.
 *
 * @param {object} charge Charge Overpass décodée.
 * @return {{retenus:object[],sans_nom:number,classe_hors_liste:number,coordonnee_non_finie:number}}
 */
function convertirPoints( charge ) {
	const retenus = [];
	let sansNom = 0;
	let classeHorsListe = 0;
	let coordonneeNonFinie = 0;

	for ( const element of charge.elements ) {
		const tags = element.tags;

		if ( ! tags || 'string' !== typeof tags.name || '' === tags.name.trim() ) {
			sansNom += 1;
			continue;
		}

		if ( ! CLASSES_TOPONYMES.includes( tags.place ) ) {
			classeHorsListe += 1;
			continue;
		}

		if ( ! Number.isFinite( element.lon ) || ! Number.isFinite( element.lat ) ) {
			coordonneeNonFinie += 1;
			continue;
		}

		const population = Number.parseInt( tags.population, 10 );

		retenus.push( {
			// LE NOM CUIT EST `tags.name` VERBATIM (I-71.3). Ni `name:fr`, ni
			// `int_name`, ni chaîne de repli, ni abréviation, ni troncature, ni
			// changement de casse, ni traduction, ni translittération, ni suffixe de
			// désambiguïsation. Chacune de ces « améliorations » serait une invention
			// au sens du §4.2 du brief. C'est la ligne de ce fichier la plus
			// susceptible d'être retouchée plus tard : elle porte son propre
			// commentaire disant qu'il ne faut pas.
			nom: String( tags.name ),
			classe: tags.place,
			population: Number.isFinite( population ) && population >= 0 ? population : 0,
			lon: arrondir( element.lon ),
			lat: arrondir( element.lat ),
		} );
	}

	return { retenus, sans_nom: sansNom, classe_hors_liste: classeHorsListe, coordonnee_non_finie: coordonneeNonFinie };
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

	let toponymes = null;

	for ( const couche of COUCHES_SOURCE ) {
		const requete = `[out:json][timeout:900][bbox:${ bbox }];${ SELECTEURS[ couche.nom ] };out geom;`;
		requetes[ couche.nom ] = requete;

		journal( `  ${ couche.nom } — interrogation…` );

		const reponse = await interroger( requete );
		const { charge, nombre } = decoder( couche.nom, reponse.texte );

		comptes[ couche.nom ] = nombre;
		points[ couche.nom ] = reponse.point_acces;

		if ( couche.ponctuel ) {
			toponymes = convertirPoints( charge );

			journal(
				`  ${ couche.nom } — ${ nombre } éléments, ${ toponymes.retenus.length } retenus ` +
					`(${ toponymes.sans_nom } sans nom, ${ toponymes.classe_hors_liste } hors classe, ${ toponymes.coordonnee_non_finie } coordonnée non finie), ` +
					`${ Math.round( reponse.ms / 1000 ) } s`
			);
			continue;
		}

		brutes[ couche.nom ] = convertir( charge, couche.surfacique );

		journal( `  ${ couche.nom } — ${ nombre } éléments, ${ brutes[ couche.nom ].length } géométries, ${ Math.round( reponse.ms / 1000 ) } s` );
	}

	/*
	 * TROIS GARDES D'ATTRIBUT, et elles sont nécessaires. `toponymes` est la
	 * première couche dont les ATTRIBUTS portent l'information : un dénombrement
	 * seul ne peut pas attraper une charge où chaque `name` serait arrivé vide, et
	 * cette charge-là produirait MOINS D'ÉTIQUETTES AU LIEU D'UN ÉCHEC. La
	 * quatrième garde — `place=city` dans [1, 10] — est adoptée par l'arbitrage
	 * A-9 du contrat #71 : Marseille et Aix sont structurellement présentes, et une
	 * charge des Bouches-du-Rhône sans aucune `city` est tronquée. Le risque nommé
	 * (un retaggage OSM la ferait rougir à tort) est ACCEPTÉ : cet échec-là est
	 * bruyant, daté et réparable par une décision humaine écrite, quand le mode
	 * inverse est silencieux.
	 */
	const rejetes = toponymes.sans_nom + toponymes.classe_hors_liste + toponymes.coordonnee_non_finie;
	const proportion = 0 === comptes.toponymes ? 1 : rejetes / comptes.toponymes;

	if ( proportion > REJETS_MAX ) {
		throw new Arret(
			`Couche « toponymes » : ${ rejetes } éléments rejetés sur ${ comptes.toponymes } retournés, soit ` +
				`${ ( proportion * 100 ).toFixed( 1 ) } % — plafond ${ REJETS_MAX * 100 } %. Un nœud place=city|town|village a ` +
				'essentiellement toujours un `name` : un cinquième d\'absences signale une charge abîmée. Rien n\'est écrit.'
		);
	}

	if ( toponymes.coordonnee_non_finie > 0 ) {
		throw new Arret(
			`Couche « toponymes » : ${ toponymes.coordonnee_non_finie } élément(s) à lat/lon non finie. Rien n'est écrit.`
		);
	}

	const villes = toponymes.retenus.filter( ( entite ) => 'city' === entite.classe ).length;

	if ( villes < VILLES_MIN || villes > VILLES_MAX ) {
		throw new Arret(
			`Couche « toponymes » : ${ villes } nœud(s) place=city, hors de [${ VILLES_MIN }, ${ VILLES_MAX }]. Marseille et Aix sont ` +
				'structurellement présentes dans les Bouches-du-Rhône : zéro signale une charge tronquée, et plus de dix ' +
				'un retaggage OSM à constater par une décision écrite. Rien n\'est écrit.'
		);
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
	let retenusApresClip = [];

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

		/*
		 * Les toponymes sont DÉCOUPÉS AU DÉPARTEMENT, comme les quatre autres
		 * couches. Hors du département la carte est uniformément `--c-carte-fond`
		 * (§4.2 de `MASTER.md`) : un nom de ville flottant sur un terrain que nous
		 * avons délibérément effacé affirmerait une géographie retirée. L'emprise du
		 * référentiel déborde sur le Vaucluse, le Gard et le Var — ce n'est pas
		 * théorique.
		 *
		 * Par mapshaper, jamais à la main : le département est un multipolygone à
		 * trous et à îles détachées, et `dansAnneau()` ne traite qu'un anneau. Pas de
		 * `-simplify` (sans objet sur des points), pas de `-filter-islands`
		 * (nuisible). `-filter-fields` gèle le jeu de champs pour que mapshaper ne
		 * puisse pas ajouter un `id` et faire bouger les octets de l'archive.
		 */
		const entreeToponymes = `${ travail }-toponymes.json`;
		const sortieToponymes = `${ travail }-toponymes.out.json`;

		ecrireFcPoints( entreeToponymes, toponymes.retenus );

		argv.toponymes = mapshaper( [
			entreeToponymes,
			'-clip',
			cheminTerre,
			'-filter-fields',
			'nom,classe,population',
			'-o',
			`precision=0.${ '0'.repeat( NORMALISATION.decimales - 1 ) }1`,
			'format=geojson',
			sortieToponymes,
		] );

		retenusApresClip = lireFcPoints( sortieToponymes );

		journal( `  toponymes — ${ retenusApresClip.length } points après découpe au département` );
	} finally {
		for ( const fichier of fs.readdirSync( path.dirname( CHEMINS.archive ) ) ) {
			if ( fichier.startsWith( '_travail' ) ) {
				fs.unlinkSync( path.join( path.dirname( CHEMINS.archive ), fichier ) );
			}
		}
	}

	/*
	 * PLANCHER APRÈS DÉCOUPE. Une découpe qui vide la couche signifie que le
	 * polygone `terre` est faux — et cela ne doit surtout pas se manifester par
	 * « une pyramide sans noms », qui est un artefact d'apparence normale.
	 */
	if ( retenusApresClip.length < BORNES_OSM.toponymes.plancher / 2 ) {
		throw new Arret(
			`Couche « toponymes » : ${ retenusApresClip.length } points après découpe au département, sous le plancher ` +
				`de ${ BORNES_OSM.toponymes.plancher / 2 } (moitié du plancher de charge). Une découpe qui vide la couche ` +
				'signifie que le polygone « terre » est faux. Rien n\'est écrit.'
		);
	}

	const parClasse = Object.fromEntries(
		CLASSES_TOPONYMES.map( ( classe ) => [ classe, retenusApresClip.filter( ( entite ) => classe === entite.classe ).length ] )
	);

	const archive = {
		type: 'massifs-fond-de-carte-source',
		version: 1,
		extrait_le: extraitLe,
		bbox: emprise,
		couches: {
			...Object.fromEntries(
				COUCHES.map( ( couche ) => [
					couche.nom,
					{ type: 'FeatureCollection', features: couches[ couche.nom ].map( ( geometry ) => ( { type: 'Feature', properties: {}, geometry } ) ) },
				] )
			),
			// `featurePoint()` et non une seconde construction de la même forme :
			// `lireFcPoints()` et `controlerPoint()` relisent l'archive commitée comme le
			// fichier de travail de mapshaper, et deux constructions distinctes de la
			// même entité finiraient par diverger d'un champ.
			[ COUCHE_TOPONYMES.nom ]: {
				type: 'FeatureCollection',
				features: retenusApresClip.map( ( entite ) => featurePoint( entite ) ),
			},
		},
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
		comptes_normalises: {
			...Object.fromEntries( COUCHES.map( ( c ) => [ c.nom, couches[ c.nom ].length ] ) ),
			[ COUCHE_TOPONYMES.nom ]: retenusApresClip.length,
		},
		// Ce bloc est ce qui GÈLE `BORNES_OSM.toponymes` par la procédure en deux
		// passes du §8 du contrat #71, et ce qu'un lecteur consulte avant d'y
		// toucher. Sans lui, resserrer les bornes serait une invention.
		toponymes: {
			retournes: comptes.toponymes,
			sans_nom: toponymes.sans_nom,
			classe_hors_liste: toponymes.classe_hors_liste,
			coordonnee_non_finie: toponymes.coordonnee_non_finie,
			retenus_avant_clip: toponymes.retenus.length,
			retenus_apres_clip: retenusApresClip.length,
			par_classe: parClasse,
		},
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
