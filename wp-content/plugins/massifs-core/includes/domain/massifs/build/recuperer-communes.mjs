/**
 * Récupération de l'extrait communal IGN ADMIN EXPRESS (issue #45).
 *
 *   node recuperer-communes.mjs      (ou : npm run recuperer-communes)
 *
 * C'EST LE SEUL FICHIER DU MODULE « MASSIFS » QUI TOUCHE LE RÉSEAU. Il se joue
 * à la main, jamais en intégration continue, et n'est JAMAIS un prérequis de
 * `npm run importer` : l'extrait qu'il produit est commité, et c'est lui que
 * l'import consomme, hors ligne (§10 du contrat #45). Le §3 du brief interdit
 * toute requête navigateur vers un domaine tiers ; l'acquisition est ici, au
 * build, et nulle part ailleurs.
 *
 * LE MILLÉSIME NE S'ÉCRIT JAMAIS `LATEST` (§2.1). La couche est publiée derrière
 * un alias mouvant ; épingler l'alias produirait un millésime qui dérive en
 * silence, et une commune fusionnée ou renommée s'afficherait comme courante
 * sans qu'aucun artefact ne le signale. Ce script RÉSOUT l'alias avant de
 * l'utiliser, et refuse d'écrire si le millésime résolu n'est pas celui épinglé
 * dans `communes.mjs`.
 *
 * Comment l'alias est résolu — et pourquoi ainsi. Les `GetCapabilities` du
 * service publient les millésimes datés de la famille `ADMINEXPRESS-COG`, mais
 * la variante CARTO n'existe QUE derrière l'alias : aucun
 * `ADMINEXPRESS-COG-CARTO.<AAAA>` n'est servi (vérifié : HTTP 400). Le nom ne
 * peut donc pas résoudre l'alias, et une correspondance devinée serait une
 * invention. On résout donc par MESURE : les attributs des 119 communes du 13
 * sont demandés à l'alias et à chaque millésime daté, et le millésime dont la
 * charge d'attributs est identique à celle de l'alias EST le millésime de
 * l'alias. Zéro correspondance, ou plus d'une : arrêt, rien n'est écrit.
 *
 * L'archive en place N'EST JAMAIS écrasée par un échec : tout est contrôlé avant
 * la première écriture, et l'écriture est atomique.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Arret, DECIMALES_COORDONNEES, arrondir, bboxGeometrie } from './geometrie.mjs';
import {
	CHEMINS_COMMUNES,
	CHEMINS_COMMUNES_RELATIFS,
	LICENCE_COMMUNES,
	SEUILS_COMMUNES,
	SOURCE_COMMUNES,
	elargir,
	recoupe,
} from './communes.mjs';

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );

/** Source archivée des massifs : c'est SON emprise qui borne l'extrait communal. */
const CHEMIN_MASSIFS = path.join( RACINE, 'source/massifs-13.full.geojson' );

/** Point d'accès WFS du Géoportail. */
const POINT_ACCES = 'https://data.geopf.fr/wfs/ows';

/**
 * Alias mouvant de la couche, utilisé pour la GÉOMÉTRIE et résolu avant usage.
 *
 * Il n'apparaît dans aucun artefact : seul le millésime résolu y est consigné.
 */
const COUCHE_ALIAS = 'ADMINEXPRESS-COG-CARTO.LATEST';

/** Famille datée servant de témoin de résolution : ses millésimes, eux, sont publiés. */
const FAMILLE_DATEE = /^ADMINEXPRESS-COG\.(\d{4}):commune$/;

/** Attributs comparés pour résoudre l'alias. Tous ceux que la couche publie hors géométrie. */
const ATTRIBUTS_TEMOINS = [
	'code_insee',
	'nom_officiel',
	'nom_officiel_en_majuscules',
	'statut',
	'population',
	'date_du_recensement',
	'code_insee_du_canton',
	'code_insee_de_l_arrondissement',
	'code_insee_du_departement',
	'code_siren',
	'code_postal',
	'superficie_cadastrale',
];

/** Département témoin de la résolution : celui du projet. */
const DEPARTEMENT_TEMOIN = '13';

/** Agent déclaré : un service public a le droit de savoir qui l'interroge. */
const AGENT = 'massifs-core/1.0 (referentiel communal, build hors ligne)';

/** Délai d'attente d'une réponse, en millisecondes. */
const DELAI_MS = 180000;

/** Nombre d'essais par requête, et attente entre deux essais. */
const ESSAIS = 3;
const ATTENTE_MS = 5000;

/**
 * Bornes de dénombrement, par département.
 *
 * Une charge tronquée par timeout est un JSON syntaxiquement valide mais amputé.
 * Les bornes sont larges — elles n'épinglent pas un nombre de communes, qui
 * change légitimement d'un millésime à l'autre — mais elles attrapent la
 * troncature et la réponse vide.
 */
const BORNES_DEPARTEMENT = { plancher: 80, plafond: 500 };

/** Une commune dont le nom est vide n'est pas une commune : la charge est abîmée. */
const NOMS_VIDES_MAX = 0;

function journal( ligne ) {
	process.stdout.write( `${ ligne }\n` );
}

function sha256( donnees ) {
	return createHash( 'sha256' ).update( donnees ).digest( 'hex' );
}

function md5( donnees ) {
	return createHash( 'md5' ).update( donnees ).digest( 'hex' );
}

/**
 * Une requête HTTP, avec réessais et délai d'attente explicite.
 *
 * @param {string} url     URL complète.
 * @param {string} intitule Intitulé, pour le message d'échec.
 * @return {{texte:string,ms:number}}
 */
async function interroger( url, intitule ) {
	const echecs = [];

	for ( let essai = 1; essai <= ESSAIS; essai += 1 ) {
		const depart = Date.now();

		try {
			const reponse = await fetch( url, {
				headers: { 'User-Agent': AGENT },
				signal: AbortSignal.timeout( DELAI_MS ),
			} );
			const texte = await reponse.text();

			if ( 200 === reponse.status ) {
				return { texte, ms: Date.now() - depart };
			}

			echecs.push( `essai ${ essai } : HTTP ${ reponse.status } — ${ texte.slice( 0, 200 ) }` );
		} catch ( erreur ) {
			echecs.push( `essai ${ essai } : ${ erreur.message }` );
		}

		if ( essai < ESSAIS ) {
			await new Promise( ( suite ) => setTimeout( suite, ATTENTE_MS * essai ) );
		}
	}

	throw new Arret( `${ intitule } : injoignable.\n  - ${ echecs.join( '\n  - ' ) }` );
}

/** URL d'une requête `GetFeature` sur la couche `commune`. */
function urlGetFeature( couche, departement, proprietes ) {
	const parametres = new URLSearchParams( {
		SERVICE: 'WFS',
		VERSION: '2.0.0',
		REQUEST: 'GetFeature',
		TYPENAMES: `${ couche }:commune`,
		SRSNAME: 'EPSG:4326',
		COUNT: '1000',
		OUTPUTFORMAT: 'application/json',
		CQL_FILTER: `code_insee_du_departement='${ departement }'`,
	} );

	if ( proprietes ) {
		parametres.set( 'PROPERTYNAME', proprietes.join( ',' ) );
	}

	return `${ POINT_ACCES }?${ parametres.toString() }`;
}

/**
 * Charge JSON décodée et dénombrée.
 *
 * @param {string} intitule Intitulé, pour les messages d'échec.
 * @param {string} texte    Corps de la réponse.
 */
function decoder( intitule, texte ) {
	let charge;

	try {
		charge = JSON.parse( texte );
	} catch ( erreur ) {
		throw new Arret( `${ intitule } : réponse illisible — ${ erreur.message } (${ texte.slice( 0, 200 ) })` );
	}

	if ( ! charge || ! Array.isArray( charge.features ) ) {
		throw new Arret( `${ intitule } : aucune liste d'entités dans la réponse.` );
	}

	// `numberMatched` dit ce que le serveur a trouvé, `features.length` ce qu'il a
	// rendu. Un écart signale une pagination non demandée : la charge est amputée.
	if ( undefined !== charge.numberMatched && charge.numberMatched !== charge.features.length ) {
		throw new Arret(
			`${ intitule } : ${ charge.features.length } entités rendues pour ${ charge.numberMatched } trouvées — ` +
				'charge paginée, donc incomplète. Rien n\'est écrit.'
		);
	}

	return charge;
}

/** Empreinte des seuls ATTRIBUTS d'une charge, insensible à l'ordre des entités. */
function empreinteAttributs( charge ) {
	const lignes = charge.features
		.map( ( f ) => ATTRIBUTS_TEMOINS.map( ( cle ) => String( f.properties?.[ cle ] ) ).join( '|' ) )
		.sort();

	return md5( lignes.join( '\n' ) );
}

/**
 * Millésimes datés publiés par les `GetCapabilities`, du plus récent au plus ancien.
 *
 * @return {Array<{millesime:string,couche:string,titre:string}>}
 */
function millesimesDates( capabilities ) {
	const trouves = [];

	for ( const bloc of capabilities.match( /<FeatureType[\s>][\s\S]*?<\/FeatureType>/g ) || [] ) {
		const nom = ( bloc.match( /<Name>([^<]*)<\/Name>/ ) || [] )[ 1 ] || '';
		const correspondance = nom.match( FAMILLE_DATEE );

		if ( correspondance ) {
			trouves.push( {
				millesime: correspondance[ 1 ],
				couche: nom.replace( /:commune$/, '' ),
				titre: ( bloc.match( /<Title>([^<]*)<\/Title>/ ) || [] )[ 1 ] || '',
			} );
		}
	}

	return trouves.sort( ( a, b ) => b.millesime.localeCompare( a.millesime ) );
}

/**
 * Résout l'alias vers son millésime daté, par identité de charge d'attributs.
 *
 * @return {{millesime:string,titre:string,empreinte:string,candidats:object[]}}
 */
async function resoudreMillesime() {
	journal( 'Résolution du millésime — lecture des GetCapabilities…' );

	const parametres = new URLSearchParams( { SERVICE: 'WFS', VERSION: '2.0.0', REQUEST: 'GetCapabilities' } );
	const capabilities = await interroger( `${ POINT_ACCES }?${ parametres.toString() }`, 'GetCapabilities' );
	const candidats = millesimesDates( capabilities.texte );

	if ( 0 === candidats.length ) {
		throw new Arret(
			'Aucun millésime daté publié par les GetCapabilities : l\'alias ne peut pas être résolu, ' +
				'et un alias non résolu ne s\'écrit dans aucun artefact (§2.1). Rien n\'est écrit.'
		);
	}

	journal( `  ${ candidats.length } millésimes datés publiés : ${ candidats.map( ( c ) => c.millesime ).join( ', ' ) }` );

	const reference = decoder(
		'alias (attributs)',
		( await interroger( urlGetFeature( COUCHE_ALIAS, DEPARTEMENT_TEMOIN, ATTRIBUTS_TEMOINS ), 'alias (attributs)' ) ).texte
	);
	const empreinte = empreinteAttributs( reference );
	const concordants = [];
	const examines = [];

	for ( const candidat of candidats ) {
		let charge;

		try {
			const reponse = await interroger(
				urlGetFeature( candidat.couche, DEPARTEMENT_TEMOIN, ATTRIBUTS_TEMOINS ),
				`millésime ${ candidat.millesime }`
			);
			charge = decoder( `millésime ${ candidat.millesime }`, reponse.texte );
		} catch ( erreur ) {
			// Un millésime ancien peut ne plus être interrogeable ; il ne peut alors
			// simplement pas être le millésime de l'alias. Ce n'est pas une panne.
			examines.push( { millesime: candidat.millesime, interrogeable: false, concordant: false } );
			continue;
		}

		const concordant = empreinteAttributs( charge ) === empreinte;

		examines.push( { millesime: candidat.millesime, interrogeable: true, concordant } );

		if ( concordant ) {
			concordants.push( candidat );
		}
	}

	if ( 1 !== concordants.length ) {
		throw new Arret(
			`Résolution ambiguë : ${ concordants.length } millésime(s) concordent avec l'alias ` +
				`(${ concordants.map( ( c ) => c.millesime ).join( ', ' ) || 'aucun' }). Un millésime deviné serait ` +
				'une invention. Rien n\'est écrit.'
		);
	}

	const resolu = concordants[ 0 ];

	journal( `  alias résolu vers le millésime ${ resolu.millesime } — « ${ resolu.titre } »` );

	return { millesime: resolu.millesime, titre: resolu.titre, empreinte, candidats: examines };
}

/** Emprise des périmètres de massifs, lue sur la source archivée pleine précision. */
function empriseMassifs() {
	if ( ! fs.existsSync( CHEMIN_MASSIFS ) ) {
		throw new Arret( `Source des massifs absente : ${ CHEMIN_MASSIFS }` );
	}

	const fc = JSON.parse( fs.readFileSync( CHEMIN_MASSIFS, 'utf8' ) );
	const boite = { ouest: Infinity, sud: Infinity, est: -Infinity, nord: -Infinity };

	for ( const feature of fc.features ) {
		const partielle = bboxGeometrie( feature.geometry );

		boite.ouest = Math.min( boite.ouest, partielle.ouest );
		boite.sud = Math.min( boite.sud, partielle.sud );
		boite.est = Math.max( boite.est, partielle.est );
		boite.nord = Math.max( boite.nord, partielle.nord );
	}

	return boite;
}

/** Ramène toutes les coordonnées à la précision de l'archive. */
function reduirePrecision( noeud ) {
	if ( 'number' === typeof noeud[ 0 ] ) {
		return [ arrondir( noeud[ 0 ], DECIMALES_COORDONNEES ), arrondir( noeud[ 1 ], DECIMALES_COORDONNEES ) ];
	}

	return noeud.map( reduirePrecision );
}

async function principal() {
	const resolution = await resoudreMillesime();

	if ( resolution.millesime !== SOURCE_COMMUNES.millesime ) {
		throw new Arret(
			`Millésime résolu ${ resolution.millesime }, millésime épinglé ${ SOURCE_COMMUNES.millesime }. ` +
				'Une montée de millésime change des noms de communes et des limites : c\'est une DÉCISION HUMAINE. ' +
				'La prendre, c\'est mettre à jour `SOURCE_COMMUNES` dans `communes.mjs` (millésime, édition, ' +
				'libellé d\'édition) puis relancer. Rien n\'est écrit.'
		);
	}

	const emprise = empriseMassifs();
	const decoupe = elargir( emprise, SEUILS_COMMUNES.marge_clip_deg );

	journal(
		`Emprise des massifs : ${ arrondir( emprise.ouest, DECIMALES_COORDONNEES ) },` +
			`${ arrondir( emprise.sud, DECIMALES_COORDONNEES ) } → ` +
			`${ arrondir( emprise.est, DECIMALES_COORDONNEES ) },` +
			`${ arrondir( emprise.nord, DECIMALES_COORDONNEES ) } ; ` +
			`découpe élargie de ${ SEUILS_COMMUNES.marge_clip_deg }°`
	);

	const toutes = [];
	const reponses = {};
	const requetes = {};

	for ( const departement of SOURCE_COMMUNES.departements ) {
		const url = urlGetFeature( COUCHE_ALIAS, departement, [ ...SOURCE_COMMUNES.attributs, 'geometrie' ] );
		const intitule = `département ${ departement }`;

		// L'alias est SUBSTITUÉ dans la requête consignée : §2.1 interdit qu'il
		// s'écrive dans un artefact. Y écrire le millésime résolu à sa place
		// donnerait une URL qui n'existe pas — la variante CARTO ne publie aucune
		// couche datée. Le lecteur a les deux faits : le gabarit et le millésime.
		requetes[ departement ] = url.replace( COUCHE_ALIAS, '<alias de la couche, résolu au millésime ci-dessus>' );

		const reponse = await interroger( url, intitule );
		const charge = decoder( intitule, reponse.texte );

		if (
			charge.features.length < BORNES_DEPARTEMENT.plancher ||
			charge.features.length > BORNES_DEPARTEMENT.plafond
		) {
			throw new Arret(
				`${ intitule } : ${ charge.features.length } communes, hors de [${ BORNES_DEPARTEMENT.plancher }, ` +
					`${ BORNES_DEPARTEMENT.plafond }]. Charge aberrante ou tronquée. Rien n'est écrit.`
			);
		}

		reponses[ departement ] = {
			// Empreinte de la réponse REÇUE, telle quelle. C'est une trace de
			// provenance, pas un contrôle de reproductibilité : le service ne garantit
			// pas un ordre d'entités stable. Ce qui est reproductible, c'est
			// l'empreinte de l'extrait dérivé, plus bas.
			md5_reponse: md5( reponse.texte ),
			octets_reponse: reponse.texte.length,
			ms: reponse.ms,
			communes: charge.features.length,
		};

		journal(
			`  ${ intitule } — ${ charge.features.length } communes, ${ reponse.texte.length } octets, ` +
				`${ ( reponse.ms / 1000 ).toFixed( 2 ) } s`
		);

		toutes.push( ...charge.features );
	}

	const retenues = toutes.filter( ( f ) => f.geometry && recoupe( bboxGeometrie( f.geometry ), decoupe ) );

	journal( `  ${ retenues.length } communes retenues sur ${ toutes.length } après découpe à l'emprise` );

	/* ---------------------------------------------------------------------- */
	/* Contrôles — aucun n'est facultatif, aucun n'est joué après l'écriture   */
	/* ---------------------------------------------------------------------- */

	const insee = retenues.map( ( f ) => String( f.properties.code_insee ) );

	if ( new Set( insee ).size !== insee.length ) {
		throw new Arret( 'Codes INSEE non uniques dans l\'extrait. Rien n\'est écrit.' );
	}

	const marseille = insee.filter( ( code ) => '13055' === code ).length;

	if ( 1 !== marseille ) {
		throw new Arret(
			`Marseille (13055) présente ${ marseille } fois. La couche COG CARTO peut porter des arrondissements ` +
				'municipaux : un seul objet « Marseille » est attendu. Rien n\'est écrit.'
		);
	}

	const sansNom = retenues.filter(
		( f ) => 'string' !== typeof f.properties.nom_officiel || '' === f.properties.nom_officiel.trim()
	).length;

	if ( sansNom > NOMS_VIDES_MAX ) {
		throw new Arret( `${ sansNom } commune(s) sans nom officiel. Rien n'est écrit.` );
	}

	const typesInattendus = [ ...new Set( retenues.map( ( f ) => f.geometry.type ) ) ].filter(
		( type ) => 'Polygon' !== type && 'MultiPolygon' !== type
	);

	if ( typesInattendus.length > 0 ) {
		throw new Arret( `Géométries non surfaciques : ${ typesInattendus.join( ', ' ) }. Rien n'est écrit.` );
	}

	if ( ! retenues.some( ( f ) => '13' === String( f.properties.code_insee_du_departement ) ) ) {
		throw new Arret( 'Aucune commune des Bouches-du-Rhône dans l\'extrait. Rien n\'est écrit.' );
	}

	/* ---------------------------------------------------------------------- */
	/* Extrait dérivé                                                          */
	/* ---------------------------------------------------------------------- */

	// Tri par code INSEE : l'extrait est reproductible d'une exécution à l'autre,
	// quel que soit l'ordre dans lequel le service a rendu les entités.
	const triees = [ ...retenues ].sort( ( a, b ) =>
		String( a.properties.code_insee ).localeCompare( String( b.properties.code_insee ) )
	);

	const extrait = {
		type: 'FeatureCollection',
		features: triees.map( ( f ) => ( {
			type: 'Feature',
			// Quatre attributs, pas un de plus. `population` et
			// `date_du_recensement` sont explicitement écartés : rien dans le brief
			// ne les demande, et les embarquer serait publier une donnée dont nous
			// n'avons pas l'usage.
			properties: Object.fromEntries( SOURCE_COMMUNES.attributs.map( ( cle ) => [ cle, f.properties[ cle ] ] ) ),
			geometry: { type: f.geometry.type, coordinates: reduirePrecision( f.geometry.coordinates ) },
		} ) ),
	};

	const octets = Buffer.from( `${ JSON.stringify( extrait ) }\n`, 'utf8' );
	let sommets = 0;
	const compter = ( noeud ) => {
		if ( 'number' === typeof noeud[ 0 ] ) {
			sommets += 1;
			return;
		}

		noeud.forEach( compter );
	};

	extrait.features.forEach( ( f ) => compter( f.geometry.coordinates ) );

	const manifeste = {
		a_propos:
			'Provenance de l\'extrait communal IGN ADMIN EXPRESS. ÉMIS PAR `npm run recuperer-communes`, jamais édité à la main. Ce qui est commité est l\'EXTRAIT DÉRIVÉ, découpé à l\'emprise des massifs élargie : committer ADMIN EXPRESS national n\'est pas viable, et la propriété qui compte — un import rejouable depuis le seul dépôt — est préservée. Écart au précédent des massifs assumé et consigné (§10 du contrat #45).',
		recupere_le: new Date().toISOString().replace( /\.\d{3}Z$/, 'Z' ),
		producteur: SOURCE_COMMUNES.producteur,
		jeu_de_donnees: SOURCE_COMMUNES.jeu_de_donnees,
		couche: SOURCE_COMMUNES.couche,
		// Le millésime RÉSOLU, jamais l'alias : `<couche>` remplace l'alias dans les
		// requêtes consignées ci-dessous, pour la même raison.
		millesime: resolution.millesime,
		edition: SOURCE_COMMUNES.edition,
		resolution_millesime: {
			methode:
				'identité de la charge d\'attributs des communes du département témoin entre l\'alias mouvant et chaque millésime daté publié par les GetCapabilities. La variante CARTO ne publie aucun millésime daté : le nom ne peut pas résoudre l\'alias, la mesure le peut.',
			departement_temoin: DEPARTEMENT_TEMOIN,
			attributs_temoins: ATTRIBUTS_TEMOINS,
			empreinte_attributs_md5: resolution.empreinte,
			// `<Title>` de la couche TÉMOIN datée `ADMINEXPRESS-COG.<AAAA>` ayant
			// servi à résoudre l'alias par mesure — JAMAIS celui de la couche
			// `ADMINEXPRESS-COG-CARTO.LATEST` d'où vient la géométrie livrée. Le
			// suffixe `_temoin` est là pour que ce champ ne soit pas relu comme
			// décrivant la couche livrée : il voisine `jeu_de_donnees`, qui vaut
			// « ADMIN EXPRESS COG Carto », et les deux valeurs sont exactes.
			titre_publie_temoin: resolution.titre,
			candidats: resolution.candidats,
		},
		point_acces: POINT_ACCES,
		agent: AGENT,
		crs: SOURCE_COMMUNES.crs,
		licence: LICENCE_COMMUNES,
		departements: SOURCE_COMMUNES.departements,
		requetes,
		reponses,
		emprise_massifs: {
			ouest: arrondir( emprise.ouest, DECIMALES_COORDONNEES ),
			sud: arrondir( emprise.sud, DECIMALES_COORDONNEES ),
			est: arrondir( emprise.est, DECIMALES_COORDONNEES ),
			nord: arrondir( emprise.nord, DECIMALES_COORDONNEES ),
		},
		decoupe: {
			marge_deg: SEUILS_COMMUNES.marge_clip_deg,
			ouest: arrondir( decoupe.ouest, DECIMALES_COORDONNEES ),
			sud: arrondir( decoupe.sud, DECIMALES_COORDONNEES ),
			est: arrondir( decoupe.est, DECIMALES_COORDONNEES ),
			nord: arrondir( decoupe.nord, DECIMALES_COORDONNEES ),
			note:
				'Sélection par recoupement d\'emprises : les communes retenues gardent leur géométrie ENTIÈRE, aucune n\'est coupée. Un polygone tronqué à la bbox porterait un bord artificiel, et la distance au bord la plus proche mesurerait ce bord-là.',
		},
		precision_decimales: DECIMALES_COORDONNEES,
		communes_recuperees: toutes.length,
		communes_retenues: extrait.features.length,
		sommets,
		archive: {
			fichier: CHEMINS_COMMUNES_RELATIFS.extrait,
			sha256: sha256( octets ),
			md5: md5( octets ),
			octets: octets.length,
		},
	};

	fs.mkdirSync( path.dirname( CHEMINS_COMMUNES.extrait ), { recursive: true } );

	const sorties = [
		{ chemin: CHEMINS_COMMUNES.extrait, contenu: octets },
		{
			chemin: CHEMINS_COMMUNES.manifeste,
			contenu: Buffer.from( `${ JSON.stringify( manifeste, null, '\t' ) }\n`, 'utf8' ),
		},
	];

	for ( const { chemin, contenu } of sorties ) {
		fs.writeFileSync( `${ chemin }.tmp`, contenu );
	}

	for ( const { chemin } of sorties ) {
		fs.renameSync( `${ chemin }.tmp`, chemin );
	}

	journal( '' );
	journal( `Extrait : ${ CHEMINS_COMMUNES_RELATIFS.extrait } — ${ octets.length } octets, ${ sommets } sommets` );
	journal( `Millésime résolu : ${ resolution.millesime } (édition ${ SOURCE_COMMUNES.edition })` );
	journal( '' );
	journal( 'Extrait et manifeste sont à COMMITER : c\'est ce qui rend `npm run importer` rejouable hors ligne.' );
	journal( 'Puis : npm run importer && npm run verifier' );
}

try {
	await principal();
} catch ( erreur ) {
	process.stderr.write( `${ erreur instanceof Arret ? erreur.message : erreur.stack }\n` );
	process.exitCode = 1;
}
