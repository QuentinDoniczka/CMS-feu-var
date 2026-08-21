/**
 * Import du référentiel des massifs forestiers des Bouches-du-Rhône.
 *
 * Chaîne reproductible : source archivée -> réconciliation d'identités ->
 * simplification mapshaper -> émission atomique des cinq artefacts
 * (`data/massifs-13.geometrie.json`, `data/massifs-13.php`,
 * `build/massifs-13.fidelite.json`, `build/reference.json`,
 * `communes-13.lookup.json`).
 *
 *   node importer.mjs      (ou : npm run importer)
 *
 * Rien n'est écrit tant que tous les contrôles ne passent pas : les sorties
 * partent d'abord dans des fichiers temporaires, puis sont renommées en bloc.
 * Un import à moitié appliqué laisserait le site avec une géométrie neuve et
 * des métadonnées anciennes — donc un cache-busting faux.
 *
 * La géométrie est reproductible à l'octet. Les métadonnées portent un
 * horodatage d'import assumé : `MASSIFS_GENERE_LE=AAAA-MM-JJThh:mm:ssZ` le fige
 * pour DÉMONTRER cette reproductibilité, et ne sert jamais à produire des
 * artefacts commités.
 *
 * L'import peut mettre à jour une géométrie automatiquement ; il ne peut jamais
 * créer, supprimer, renommer ni re-lier une identité sans décision humaine.
 * Voir ../README.md pour la table de réconciliation cas par cas.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { gzipSync } from 'node:zlib';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import {
	Arret,
	DECIMALES_COORDONNEES,
	LAT_REFERENCE,
	METRES_PAR_DEGRE_LAT,
	METRES_PAR_DEGRE_LON,
	METRES_PAR_DEGRE_LON_EQUATEUR,
	aireAnneau,
	aireGeometrie,
	anneaux,
	arrondir,
	centroideAnneau,
	distanceAnneau,
	mesurerMassif,
} from './geometrie.mjs';
import {
	CHEMINS_COMMUNES,
	CHEMINS_COMMUNES_RELATIFS,
	LICENCE_COMMUNES,
	LOOKUP,
	SEUILS_COMMUNES,
	SOURCE_COMMUNES,
	attributionCommunes,
	communesParMassif,
	construireLookup,
	lireExtraitCommunes,
	sourceCommunesParMassif,
} from './communes.mjs';
import { CHEMIN_MAPSHAPER, CHEMIN_MAPSHAPER_MANIFESTE, MAPSHAPER_ABSENT } from './mapshaper.mjs';

/*
 * Ré-exportés pour que la surface publique de ce fichier ne bouge pas quand ses
 * primitives déménagent : `verifier.mjs` et les scripts voisins importent d'ici.
 */
export { Arret, mesurerMassif };

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );

/** Racine de l'extension, telle que la voit aussi la recette. */
export const EXTENSION = path.resolve( RACINE, '../../../..' );

/** Racine du dépôt : sert à composer des commandes `git` copiables telles quelles. */
const DEPOT = path.resolve( EXTENSION, '../../..' );

/**
 * Chemins des artefacts, définis ici et importés par `verifier.mjs`.
 *
 * Une seconde liste de chemins recopiée dans la recette finirait par contrôler
 * un autre fichier que celui qu'écrit l'import — une recette verte sur le mauvais
 * fichier est pire que pas de recette.
 */
export const CHEMINS = {
	source: path.join( RACINE, 'source/massifs-13.full.geojson' ),
	identites: path.join( RACINE, 'identites.json' ),
	geometrie: path.join( EXTENSION, 'data/massifs-13.geometrie.json' ),
	metadonnees: path.join( EXTENSION, 'data/massifs-13.php' ),
	// `data/` est servi au navigateur, `build/` ne l'est jamais : un artefact de
	// recette n'a rien à faire à une URL publique.
	fidelite: path.join( RACINE, 'massifs-13.fidelite.json' ),
	reference: path.join( RACINE, 'reference.json' ),
	// Repris de `mapshaper.mjs`, jamais recomposés : c'est exactement la seconde
	// liste que l'en-tête ci-dessus interdit, et `communes.mjs` lit déjà la même.
	mapshaper: CHEMIN_MAPSHAPER,
	mapshaper_manifeste: CHEMIN_MAPSHAPER_MANIFESTE,
};

/** Chemin de la source archivée, relatif à la racine de l'extension, tel que consigné dans les artefacts. */
const CHEMIN_SOURCE_RELATIF = 'includes/domain/massifs/build/source/massifs-13.full.geojson';

/**
 * Version du schéma du fichier de métadonnées lu par le module PHP.
 *
 * 2 : ajout de la correspondance gelée avec les identifiants du flux
 * préfectoral (`source.identifiant_prefecture` par ligne, bloc racine
 * `correspondance_source`). Le module PHP refuse un schéma qu'il ne connaît pas.
 */
export const SCHEMA = 2;

/**
 * Paramètres de simplification. Le paramètre `interval` de Douglas-Peucker EST
 * la borne de déviation : la mesurer après coup rend la garantie §4.1 opposable
 * au lieu d'être postulée.
 *
 * `ilots_min_m2` ne touche pas au tracé — il retire des anneaux entiers avant la
 * simplification. Le jeu source porte 1 129 anneaux dont 884 sous 25 hectares :
 * à l'écran, chacun apposait son liseré et la carte se lisait comme du bruit.
 * DP ne pouvait rien y faire, il supprime des sommets LE LONG d'une ligne mais
 * ne fusionne ni ne retire jamais un anneau.
 *
 * Visvalingam pondéré a été mesuré comme alternative et ÉCARTÉ : il effondre bien
 * les petits anneaux, mais déplace les frontières hors de toute borne utile —
 * 1 454 m d'écart maximal dès 90 m d'intervalle, 1 997 m à 250 m, et un massif
 * (Rougadou) faux de 52,9 % en surface. Un écart de 2 km n'est pas « visuellement
 * fidèle à la carte officielle ». L'algorithme ne change pas ; seuls les îlots
 * détachés cessent d'être dessinés, ce qui laisse l'écart maximal à 93,6 m,
 * soit 1,7 pixel au zoom 11.
 */
export const SIMPLIFICATION = {
	algorithme: 'douglas-peucker',
	intervalle_m: 90,
	ilots_min_m2: 250000,
	precision_decimales: 4,
	zoom_max: 11,
};

/**
 * Zooms auxquels l'écart de simplification est converti en pixels.
 *
 * z10 est le zoom départemental, z11 le plafond de la couche (`zoom_max`) ; z12
 * et z13 sont conservés pour montrer où l'écart cesse d'être sous-pixel.
 */
export const ZOOMS_EVALUES = [ 10, 11, 12, 13 ];

/**
 * Seuils de recette. Le budget est exprimé en octets BRUTS : la compression
 * HTTP n'est vérifiée sur aucune cible, elle reste une marge et non une béquille.
 */
export const SEUILS = {
	octets_bruts_max: 307200,
	ecart_max_m: 120,
	ecart_surface_global_abs_pct_max: 0.5,
	// Relevés de 3 % et 0,5 % en même temps que l'arrivée de
	// `SIMPLIFICATION.ilots_min_m2`. Ces deux seuils ne bornent pas une déformation
	// du tracé — l'écart maximal, lui, n'a pas bougé d'un mètre — mais la surface
	// que le filtre d'îlots retire volontairement.
	//
	// Mesuré au seuil de 25 ha : 4,19 % de la surface totale retirée, et un seul
	// massif réellement touché, Rougadou, constellation de petites parcelles qui
	// tombe à un anneau et perd 19,15 % ; tous les autres restent sous 4,5 %. Les
	// 25 massifs restent dessinés — aucun ne disparaît.
	//
	// La marge au-dessus du mesuré est délibérément courte : ces seuils gardent un
	// choix assumé, pas une tolérance. Un écart qui les dépasse signale que le jeu
	// source a changé de structure, et doit être regardé.
	ecart_surface_massif_abs_pct_max: 22,
	surface_anneaux_supprimes_pct_max: 5,
	features_attendues: 25,
	code_regex: '^[a-z0-9_-]{1,64}$',
	identifiant_prefecture_regex: '^\\d{3,4}$',
};

/**
 * Ce que porte le flux journalier de la préfecture, constaté et non déduit.
 *
 * 27 identifiants pour 25 massifs publiés : `1326` et `1327` sont en surnombre.
 * Ni la table HTML de risque-prevention-incendie.fr/13 (25 lignes) ni le PDF
 * journalier ne les nomment. Aucun nom n'est inventé pour combler l'écart : ils
 * restent délibérément sans correspondance, et une ingestion qui les rencontre
 * n'écrit rien.
 */
export const FLUX_PREFECTURE = {
	identifiants_total: 27,
	sans_correspondance: [ '1326', '1327' ],
	note: 'En surnombre dans le flux : aucune publication officielle ne les nomme, ils n\'ont donc volontairement aucun massif. Aucun nom n\'est inventé.',
};

/** Provenance du jeu de données. Faits vérifiables, jamais de rédaction. */
export const PROVENANCE = {
	producteur: 'DDTM des Bouches-du-Rhône',
	jeu_de_donnees: 'Massifs forestiers dans les Bouches-du-Rhône',
	couche: 'L_MASSIFS_FORESTIERS_S_013',
	dataset_id: '67373dd6495f49af65c40b88',
	geoide_id: 'd2ab6ef7-9839-4e03-a4db-bdbc272a5a69',
	dataset_url: 'https://www.data.gouv.fr/datasets/massifs-forestiers-dans-les-bouches-du-rhone',
	donnees_du: '2023-02-14',
	donnees_du_libelle: '14 février 2023',
	recupere_le: '2026-08-11',
	crs_source: 'EPSG:2154',
	crs_publie: 'EPSG:4326',
	base_reglementaire: 'Arrêté préfectoral n° 13-2018-05-28-005 du 28 mai 2018',
	dispositif: { debut: '06-01', fin: '09-30' },
};

export const LICENCE = {
	nom: 'Licence Ouverte',
	version: '2.0',
	identifiant: 'etalab-2.0',
	url: 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
};

/**
 * Mention §9. La Licence Ouverte 2.0 impose la citation exacte de la source et
 * de la date : la phrase est une donnée, pas de la rédaction de thème.
 */
export const ATTRIBUTION = {
	phrase: 'Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du 14 février 2023',
	phrase_courte: 'DDTM 13 / data.gouv.fr — Licence Ouverte 2.0',
};

/**
 * Lacune LEVÉE : l'attribut n'existe toujours pas dans la couche source, mais la
 * liste est désormais CALCULÉE par intersection avec le référentiel communal.
 *
 * `calculee` et non `disponible` : la valeur dit à un réutilisateur du JSON
 * public que la liste résulte de NOTRE PROPRE CALCUL et n'est pas une
 * publication officielle de la DDTM. `STATUT_COMMUNES_DEFAUT` reste `inconnue`
 * côté PHP — c'est la seule valeur qui ne puisse jamais être relue comme
 * « aucune commune concernée » quand le référentiel est absent.
 *
 * `source_pressentie` garde son nom : la clé est lue par `massifs_lacunes()`,
 * dont la forme est gelée par le contrat #8. Elle ne nomme plus une source
 * pressentie mais la source retenue.
 */
export const LACUNES = {
	communes: {
		statut: 'calculee',
		raison: sourceCommunesParMassif(),
		source_pressentie: `${ SOURCE_COMMUNES.producteur } ${ SOURCE_COMMUNES.jeu_de_donnees } ${ SOURCE_COMMUNES.millesime }`,
	},
};

/** Côté d'une tuile web-mercator, en pixels. */
const TAILLE_TUILE_PX = 256;

/** Longitudes couvertes par le niveau de zoom 0, en degrés. */
const DEGRES_DE_LONGITUDE = 360;

/** Borne de recherche du zoom sous-pixel : au-delà, aucun fond de carte ne propose de niveau. */
const ZOOM_RECHERCHE_MAX = 22;

/**
 * Définitions portées par l'artefact de recette.
 *
 * Ce ne sont pas des mesures mais les conventions SANS LESQUELLES les mesures ne
 * veulent rien dire : « écart max 94 m » est illisible tant qu'on ne sait pas
 * dans quel sens l'écart est mesuré ni dans quelle projection. Les détruire
 * viderait la garantie §4.1 de son contenu opposable.
 */
export const RECETTE = {
	a_propos:
		'Recette de fidélité des périmètres (§4.1, §12). Artefact de recette : hors contrat, aucun consommateur applicatif ne le lit. Régénéré par `npm run importer`, revérifié par `npm run verifier`.',
	deviation_definition: {
		primary:
			'source_to_simplified: for every vertex of every source ring, distance to the nearest segment of the matched simplified ring (one-sided Hausdorff, source -> simplified). This is the direction that carries the simplification error.',
		secondary:
			'quantization_max_m = simplified_to_source: distance from every simplified vertex to the nearest source segment. Douglas-Peucker never moves a vertex, so this measures only the coordinate rounding to 4 decimals.',
	},
	projection_metriques: {
		type: 'local equirectangular',
		x_m: `lon * cos(${ LAT_REFERENCE } deg) * ${ METRES_PAR_DEGRE_LON_EQUATEUR }`,
		y_m: `lat * ${ METRES_PAR_DEGRE_LAT }`,
		accuracy_note: '~0.1% over the 130 km extent of the departement',
	},
	topology_note:
		'mapshaper builds a shared-arc topology on import; the 10 shared borders are simplified once as shared arcs, so no gaps or overlaps can appear.',
	zooms_evalues: ZOOMS_EVALUES,
};

/** Forme imposée à `MASSIFS_GENERE_LE` : ISO 8601 en UTC, à la seconde. */
const FORME_HORODATAGE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;

/* -------------------------------------------------------------------------- */
/* Utilitaires                                                                 */
/* -------------------------------------------------------------------------- */

/**
 * Repli ASCII minuscule d'un nom. Sert de clé de RAPPROCHEMENT et de clé de
 * TRI — jamais à (re)calculer un `code` déjà gelé.
 */
export function slugifier( nom ) {
	return nom
		.normalize( 'NFD' )
		// La classe couvre les marques combinantes U+0300 à U+036F, écrites
		// littéralement — donc invisibles. Ne jamais les retaper à la main : un
		// caractère perdu changerait en silence le `tri` et le rapprochement.
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

export function sha256( donnees ) {
	return createHash( 'sha256' ).update( donnees ).digest( 'hex' );
}

function lireJson( chemin ) {
	return JSON.parse( fs.readFileSync( chemin, 'utf8' ) );
}

/** Chemin rendu relatif à `build/`, en séparateurs POSIX : rejouable sur n'importe quelle machine. */
function relatifAuBuild( chemin ) {
	return path.relative( RACINE, chemin ).split( path.sep ).join( '/' );
}

/** Chemin rendu relatif à la racine du dépôt : utilisable tel quel dans une commande git. */
function relatifAuDepot( chemin ) {
	return path.relative( DEPOT, chemin ).split( path.sep ).join( '/' );
}

/**
 * Version de mapshaper réellement installée, lue dans son manifeste.
 *
 * Codée en dur, elle mentait dès la première montée de version du lockfile — et
 * l'artefact de recette affirmait alors un outillage qui n'était plus celui qui
 * avait produit la géométrie.
 */
export function versionMapshaper() {
	if ( ! fs.existsSync( CHEMINS.mapshaper_manifeste ) ) {
		throw new Arret( MAPSHAPER_ABSENT );
	}

	return lireJson( CHEMINS.mapshaper_manifeste ).version;
}

/**
 * Majeur de Node en cours d'exécution.
 *
 * Consigné par l'import dans `reference.json`, recomparé par la recette : les
 * deux doivent lire la même valeur de la même façon, donc au même endroit.
 */
export function nodeMajeur() {
	return Number.parseInt( process.versions.node.split( '.' )[ 0 ], 10 );
}

/**
 * Horodatage porté par tous les artefacts émis.
 *
 * `MASSIFS_GENERE_LE` est un OUTIL DE PREUVE : il fige l'horodatage pour
 * démontrer que deux imports successifs produisent les mêmes octets. Il ne sert
 * jamais à produire les artefacts commités, qui portent l'heure réelle de leur
 * génération. Une forme approximative est refusée plutôt que réinterprétée : une
 * date silencieusement décalée est exactement ce que ce projet s'interdit.
 */
export function horodatageImport() {
	const impose = process.env.MASSIFS_GENERE_LE;

	if ( undefined === impose || '' === impose ) {
		return new Date().toISOString().replace( /\.\d{3}Z$/, 'Z' );
	}

	if ( ! FORME_HORODATAGE.test( impose ) ) {
		throw new Arret(
			`MASSIFS_GENERE_LE = « ${ impose } » ne respecte pas AAAA-MM-JJThh:mm:ssZ. ` +
				'Rien n\'a été écrit : corriger la variable, ou la retirer pour horodater à l\'heure réelle.'
		);
	}

	return impose;
}

/* -------------------------------------------------------------------------- */
/* Échelle : mètres par pixel                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Résolution d'un pixel de tuile web-mercator à la latitude de référence.
 *
 * Dérivée des constantes déjà utilisées pour projeter les mesures : aucune valeur
 * d'échelle n'est saisie à la main, donc aucune ne peut se désaligner de la
 * projection dans laquelle les écarts sont mesurés.
 */
export function metresParPixel( zoom ) {
	return ( METRES_PAR_DEGRE_LON * DEGRES_DE_LONGITUDE ) / ( TAILLE_TUILE_PX * 2 ** zoom );
}

/** Convertit un écart métrique en pixels, zoom par zoom. */
export function deviationsEnPixels( ecartMetres, zooms = ZOOMS_EVALUES ) {
	const pixels = {};

	for ( const zoom of zooms ) {
		pixels[ `z${ zoom }` ] = arrondir( ecartMetres / metresParPixel( zoom ), 3 );
	}

	return pixels;
}

/**
 * Zoom le plus élevé auquel l'écart reste sous le pixel.
 *
 * Un entier, pas une étiquette : `subpixel_below_zoom: "z10"` se lisait aussi
 * bien « sous-pixel à z10 » que « sous-pixel en dessous de z10 », deux
 * affirmations différentes.
 */
export function maxZoomSousPixel( ecartMetres, zoomMaximal = ZOOM_RECHERCHE_MAX ) {
	let zoom = 0;

	while ( zoom < zoomMaximal && ecartMetres < metresParPixel( zoom + 1 ) ) {
		zoom += 1;
	}

	return zoom;
}

/** Échelle consignée dans l'artefact de recette, aux zooms évalués. */
export function echelleParZoom( zooms = ZOOMS_EVALUES ) {
	const echelle = {};

	for ( const zoom of zooms ) {
		echelle[ `z${ zoom }` ] = arrondir( metresParPixel( zoom ), 2 );
	}

	return echelle;
}

/* -------------------------------------------------------------------------- */
/* Fidélité                                                                    */
/* -------------------------------------------------------------------------- */

/**
 * Apparie les anneaux source aux anneaux simplifiés, au plus proche centroïde,
 * un pour un. Douglas-Peucker ne déplace aucun sommet : le centroïde d'un
 * anneau conservé bouge de façon négligeable, l'appariement est donc sûr.
 * Les anneaux source restés sans partenaire ont été supprimés par `keep-shapes`.
 */
function apparierAnneaux( anneauxSource, anneauxSimplifies ) {
	const centroidesSource = anneauxSource.map( centroideAnneau );
	const centroidesSimplifies = anneauxSimplifies.map( centroideAnneau );
	const candidats = [];

	for ( let s = 0; s < anneauxSimplifies.length; s++ ) {
		for ( let o = 0; o < anneauxSource.length; o++ ) {
			const dx = centroidesSimplifies[ s ][ 0 ] - centroidesSource[ o ][ 0 ];
			const dy = centroidesSimplifies[ s ][ 1 ] - centroidesSource[ o ][ 1 ];
			candidats.push( [ dx * dx + dy * dy, s, o ] );
		}
	}

	candidats.sort( ( a, b ) => a[ 0 ] - b[ 0 ] );

	const paires = new Map();
	const sourcesPrises = new Set();

	for ( const [ , s, o ] of candidats ) {
		if ( paires.has( s ) || sourcesPrises.has( o ) ) {
			continue;
		}

		paires.set( s, o );
		sourcesPrises.add( o );
	}

	const supprimes = anneauxSource.map( ( _, o ) => o ).filter( ( o ) => ! sourcesPrises.has( o ) );

	return { paires, supprimes };
}

/**
 * Écart mesuré dans le sens qui porte l'erreur de simplification : chaque sommet
 * source vers le segment le plus proche de son anneau simplifié.
 */
export function mesurerFidelite( sourceFC, simplifieFC ) {
	const simplifiesParCode = new Map(
		simplifieFC.features.map( ( f ) => [ f.properties.code, f ] )
	);
	const parMassif = [];
	let ecarts = [];
	let quantisationMax = 0;

	for ( const feature of sourceFC.features ) {
		const code = feature.properties.code;
		const simplifie = simplifiesParCode.get( code );

		if ( ! simplifie ) {
			throw new Arret( `Massif absent de la géométrie simplifiée : ${ code }` );
		}

		const anneauxSource = anneaux( feature.geometry );
		const anneauxSimplifies = anneaux( simplifie.geometry );
		const { paires, supprimes } = apparierAnneaux( anneauxSource, anneauxSimplifies );
		const ecartsMassif = [];
		let quantisationMassif = 0;

		for ( const [ s, o ] of paires ) {
			for ( const sommet of anneauxSource[ o ] ) {
				ecartsMassif.push( distanceAnneau( sommet, anneauxSimplifies[ s ] ) );
			}

			for ( const sommet of anneauxSimplifies[ s ] ) {
				quantisationMassif = Math.max( quantisationMassif, distanceAnneau( sommet, anneauxSource[ o ] ) );
			}
		}

		ecartsMassif.sort( ( a, b ) => a - b );
		quantisationMax = Math.max( quantisationMax, quantisationMassif );
		ecarts = ecarts.concat( ecartsMassif );

		const aireSource = aireGeometrie( feature.geometry );
		const aireSimplifiee = aireGeometrie( simplifie.geometry );
		const aireSupprimee = supprimes.reduce( ( total, o ) => total + aireAnneau( anneauxSource[ o ] ), 0 );

		parMassif.push( {
			code,
			src_rings: anneauxSource.length,
			out_rings: anneauxSimplifies.length,
			dropped_rings: supprimes.length,
			dropped_ring_area_m2: arrondir( aireSupprimee, 1 ),
			dropped_ring_area_pct_of_massif: arrondir( ( 100 * aireSupprimee ) / aireSource, 4 ),
			src_vertices: anneauxSource.reduce( ( total, a ) => total + a.length, 0 ),
			out_vertices: anneauxSimplifies.reduce( ( total, a ) => total + a.length, 0 ),
			src_area_km2: arrondir( aireSource / 1e6, 4 ),
			out_area_km2: arrondir( aireSimplifiee / 1e6, 4 ),
			area_delta_pct: arrondir( ( 100 * ( aireSimplifiee - aireSource ) ) / aireSource, 4 ),
			max_deviation_m: arrondir( Math.max( ...ecartsMassif ), 2 ),
			mean_deviation_m: arrondir( ecartsMassif.reduce( ( a, b ) => a + b, 0 ) / ecartsMassif.length, 3 ),
			quantization_max_m: arrondir( quantisationMassif, 3 ),
		} );
	}

	ecarts.sort( ( a, b ) => a - b );

	const quantile = ( q ) => arrondir( ecarts[ Math.min( ecarts.length - 1, Math.floor( q * ecarts.length ) ) ], 2 );
	const total = ( champ ) => parMassif.reduce( ( somme, m ) => somme + m[ champ ], 0 );
	const aireSource = total( 'src_area_km2' );
	const aireSimplifiee = total( 'out_area_km2' );
	const aireSupprimee = total( 'dropped_ring_area_m2' );
	const pires = [ ...parMassif ].sort(
		( a, b ) => Math.abs( b.area_delta_pct ) - Math.abs( a.area_delta_pct )
	);
	const ecartMaximal = arrondir( ecarts[ ecarts.length - 1 ], 2 );
	const ecartP99 = quantile( 0.99 );

	return {
		global_metrics: {
			features: sourceFC.features.length,
			src_vertices: total( 'src_vertices' ),
			out_vertices: total( 'out_vertices' ),
			vertex_retention_pct: arrondir( ( 100 * total( 'out_vertices' ) ) / total( 'src_vertices' ), 3 ),
			src_rings: total( 'src_rings' ),
			out_rings: total( 'out_rings' ),
			dropped_rings: total( 'dropped_rings' ),
			dropped_ring_area_m2: arrondir( aireSupprimee, 1 ),
			dropped_ring_area_pct_of_total: arrondir( ( 100 * aireSupprimee ) / ( aireSource * 1e6 ), 4 ),
			src_area_km2: arrondir( aireSource, 4 ),
			out_area_km2: arrondir( aireSimplifiee, 4 ),
			area_delta_pct: arrondir( ( 100 * ( aireSimplifiee - aireSource ) ) / aireSource, 4 ),
			// Moyenne des écarts ABSOLUS par massif : l'écart global se compense
			// entre massifs qui grossissent et massifs qui maigrissent, et sous-estime
			// donc l'ampleur réelle de la déformation individuelle.
			area_delta_abs_mean_pct: arrondir(
				parMassif.reduce( ( somme, m ) => somme + Math.abs( m.area_delta_pct ), 0 ) / parMassif.length,
				4
			),
			area_delta_abs_worst_pct: Math.abs( pires[ 0 ].area_delta_pct ),
			area_delta_abs_worst_massif: pires[ 0 ].code,
			max_deviation_m: ecartMaximal,
			p999_deviation_m: quantile( 0.999 ),
			p99_deviation_m: ecartP99,
			mean_deviation_m: arrondir( ecarts.reduce( ( a, b ) => a + b, 0 ) / ecarts.length, 3 ),
			quantization_max_m: arrondir( quantisationMax, 3 ),
			metres_per_pixel_at_lat_43_5: echelleParZoom(),
			max_deviation_px: deviationsEnPixels( ecartMaximal ),
			p99_deviation_px: deviationsEnPixels( ecartP99 ),
			max_zoom_subpixel: maxZoomSousPixel( ecartMaximal ),
		},
		per_massif: parMassif,
	};
}

/* -------------------------------------------------------------------------- */
/* Réconciliation des identités                                                */
/* -------------------------------------------------------------------------- */

/**
 * Contrôle la correspondance gelée avec les identifiants du flux préfectoral.
 *
 * L'identifiant n'est JAMAIS calculé — surtout pas en `13` + `gid`, qui est le
 * rang alphabétique et se renumérote à la moindre insertion. Il est recopié
 * depuis `identites.json`, où il a été vérifié à la main. Toute anomalie arrête
 * l'import : une correspondance fausse rattacherait le statut d'un massif à un
 * autre, ce qui se lirait comme une information officielle.
 */
export function controlerIdentifiantsPrefecture( registre ) {
	const forme = new RegExp( SEUILS.identifiant_prefecture_regex );
	const vus = new Map();

	for ( const identite of registre.identites ) {
		const identifiant = identite.identifiant_prefecture;

		if ( 'string' !== typeof identifiant || '' === identifiant ) {
			throw new Arret(
				`Arrêt : ${ identite.code } n'a pas d'\`identifiant_prefecture\` dans identites.json. ` +
					'Le résoudre à la main contre la table officielle, jamais depuis le gid (voir README).'
			);
		}

		if ( ! forme.test( identifiant ) ) {
			throw new Arret(
				`Arrêt : l'identifiant préfectoral « ${ identifiant } » de ${ identite.code } ne respecte pas ` +
					`${ SEUILS.identifiant_prefecture_regex }.`
			);
		}

		if ( vus.has( identifiant ) ) {
			throw new Arret(
				`Arrêt : l'identifiant préfectoral « ${ identifiant } » est partagé par ${ vus.get( identifiant ) } ` +
					`et ${ identite.code }. La correspondance doit rester bijective.`
			);
		}

		if ( FLUX_PREFECTURE.sans_correspondance.includes( identifiant ) ) {
			throw new Arret(
				`Arrêt : ${ identite.code } revendique l'identifiant « ${ identifiant } », déclaré en surnombre ` +
					'et sans massif publié. Trancher à la main avant de le rattacher (voir README).'
			);
		}

		vus.set( identifiant, identite.code );
	}
}

/**
 * Rapproche les entités source du registre d'identités gelées.
 *
 * Toute situation qui exigerait de créer, renommer ou re-lier une identité lève
 * un `Arret` : c'est une décision humaine, jamais une conséquence d'un `npm run`.
 */
export function reconcilier( sourceFC, registre ) {
	controlerIdentifiantsPrefecture( registre );

	const parSlug = new Map(
		registre.identites.map( ( identite ) => [ slugifier( identite.source.nom_massif ), identite ] )
	);
	const parGid = new Map( registre.identites.map( ( identite ) => [ identite.source.gid, identite ] ) );
	const formeDuCode = new RegExp( SEUILS.code_regex );
	const journal = [];
	const lignes = [];
	const vues = new Set();

	for ( const feature of sourceFC.features ) {
		const nom = feature.properties.nom_massif;
		const gid = feature.properties.gid;
		const slug = slugifier( nom );
		const identite = parSlug.get( slug );

		if ( ! identite ) {
			const parNumero = parGid.get( gid );

			if ( parNumero ) {
				throw new Arret(
					`Arrêt : gid ${ gid } correspond à « ${ parNumero.source.nom_massif } » mais la source dit ` +
						`« ${ nom } ». Renommage ou redécoupage : décision humaine (voir README, cas 3).`
				);
			}

			throw new Arret(
				`Arrêt : « ${ nom } » (gid ${ gid }) n'a aucune entrée dans identites.json. ` +
					'Geler un code après confirmation, puis relancer (voir README, cas 4).'
			);
		}

		if ( identite.source.gid !== gid ) {
			journal.push( `Dérive de gid pour ${ identite.code } : ${ identite.source.gid } -> ${ gid } (code inchangé).` );
		}

		if ( identite.libelle !== nom && ! identite.note_provenance ) {
			throw new Arret(
				`Arrêt : le libellé « ${ identite.libelle } » diffère du nom source « ${ nom } » sans ` +
					'`note_provenance`. Citer la source officielle qui atteste la forme retenue.'
			);
		}

		if ( ! formeDuCode.test( identite.code ) ) {
			throw new Arret( `Arrêt : le code « ${ identite.code } » ne respecte pas ${ SEUILS.code_regex }.` );
		}

		if ( vues.has( identite.code ) ) {
			throw new Arret( `Arrêt : deux entités source se rapportent au code « ${ identite.code } ».` );
		}

		vues.add( identite.code );
		lignes.push( { identite, feature, gid } );
	}

	for ( const identite of registre.identites ) {
		if ( vues.has( identite.code ) ) {
			continue;
		}

		if ( ! identite.retire_le ) {
			throw new Arret(
				`Arrêt : ${ identite.code } n'a plus d'entité source. Une ligne n'est jamais supprimée : ` +
					'poser `retire_le` dans identites.json, puis relancer (voir README, cas 5).'
			);
		}

		journal.push( `${ identite.code } retiré le ${ identite.retire_le } : conservé, sans géométrie.` );
		lignes.push( { identite, feature: null, gid: identite.source.gid } );
	}

	return { lignes, journal };
}

/**
 * Construit les 25 lignes de massif, pré-triées par `tri`.
 *
 * `communes` ne vient PAS du registre d'identités : elle est calculée au build,
 * massif par massif, sur la source pleine précision. Le registre gèle des
 * IDENTITÉS — un code, un libellé, un identifiant préfectoral — et une liste de
 * communes n'en est pas une : elle se recalcule à chaque millésime communal.
 *
 * @param {Array}  appariement Massifs appariés à leur entité source.
 * @param {Object} communes    Table `code -> [noms de communes]`, triée par surface décroissante.
 */
export function construireLignes( appariement, communes ) {
	const lignes = appariement.map( ( { identite, feature, gid } ) => {
		const mesures = feature
			? mesurerMassif( feature.geometry )
			: { bbox: null, centre: null };

		return {
			code: identite.code,
			libelle: identite.libelle,
			tri: slugifier( identite.libelle ),
			communes: communes[ identite.code ] || [],
			// Un massif retiré n'a plus de surface à intersecter : sa liste est vide
			// parce qu'on ne sait pas, pas parce qu'aucune commune ne serait
			// concernée. Les deux se distinguent ici, jamais par la vacuité seule.
			communes_source: feature ? sourceCommunesParMassif() : 'inconnue',
			actif: ! identite.retire_le,
			retire_le: identite.retire_le,
			bbox: mesures.bbox,
			centre: mesures.centre,
			source: {
				gid,
				nom_massif: identite.source.nom_massif,
				revision: PROVENANCE.donnees_du,
				// Recopié depuis le registre gelé, jamais reconstruit : voir
				// `controlerIdentifiantsPrefecture`.
				identifiant_prefecture: identite.identifiant_prefecture,
			},
			note_provenance: identite.note_provenance,
		};
	} );

	// Tri fait ici, une fois : le thème reçoit la liste prête et n'a jamais à
	// dépendre de `setlocale`.
	lignes.sort( ( a, b ) => ( a.tri < b.tri ? -1 : a.tri > b.tri ? 1 : 0 ) );

	return lignes;
}

/* -------------------------------------------------------------------------- */
/* Rendu du fichier de métadonnées PHP                                         */
/* -------------------------------------------------------------------------- */

/** Marque une valeur comme flottante, pour qu'elle ne soit pas rendue en entier. */
export function flottant( valeur ) {
	return { __flottant: valeur };
}

function rendrePhpValeur( valeur, retrait ) {
	if ( null === valeur || undefined === valeur ) {
		return 'null';
	}

	if ( 'boolean' === typeof valeur ) {
		return valeur ? 'true' : 'false';
	}

	if ( 'number' === typeof valeur ) {
		return String( valeur );
	}

	if ( 'string' === typeof valeur ) {
		return `'${ valeur.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`;
	}

	if ( 'object' === typeof valeur && '__flottant' in valeur ) {
		const rendu = String( valeur.__flottant );
		return rendu.includes( '.' ) || rendu.includes( 'e' ) ? rendu : `${ rendu }.0`;
	}

	if ( Array.isArray( valeur ) ) {
		if ( 0 === valeur.length ) {
			return 'array()';
		}

		const elements = valeur.map( ( element ) => rendrePhpValeur( element, retrait ) );
		return `array( ${ elements.join( ', ' ) } )`;
	}

	const entrees = Object.entries( valeur );

	if ( 0 === entrees.length ) {
		return 'array()';
	}

	const marge = '\t'.repeat( retrait + 1 );
	const largeur = Math.max( ...entrees.map( ( [ cle ] ) => cle.length ) ) + 2;
	const lignes = entrees.map( ( [ cle, sousValeur ] ) => {
		const clef = `'${ cle }'`.padEnd( largeur, ' ' );
		return `${ marge }${ clef } => ${ rendrePhpValeur( sousValeur, retrait + 1 ) },`;
	} );

	return `array(\n${ lignes.join( '\n' ) }\n${ '\t'.repeat( retrait ) })`;
}

/** Assemble l'arbre de données rendu dans `data/massifs-13.php`. */
export function construireDonnees( { lignes, geometrie, genereLe, sourceSha256, archive, communes } ) {
	// Les massifs retirés n'ont plus de géométrie, donc plus de bbox : ils ne
	// participent pas à l'emprise.
	const avecBbox = lignes.filter( ( ligne ) => ligne.bbox );
	const bbox = {
		ouest: Math.min( ...avecBbox.map( ( ligne ) => ligne.bbox.ouest ) ),
		sud: Math.min( ...avecBbox.map( ( ligne ) => ligne.bbox.sud ) ),
		est: Math.max( ...avecBbox.map( ( ligne ) => ligne.bbox.est ) ),
		nord: Math.max( ...avecBbox.map( ( ligne ) => ligne.bbox.nord ) ),
	};

	const massifs = {};
	// Index direct code -> identifiant, écrit tel quel dans l'artefact : la
	// lecture inverse côté PHP n'a alors aucune boucle à faire.
	const correspondanceSource = {};

	for ( const ligne of lignes ) {
		correspondanceSource[ ligne.code ] = ligne.source.identifiant_prefecture;
		massifs[ ligne.code ] = {
			code: ligne.code,
			libelle: ligne.libelle,
			tri: ligne.tri,
			communes: ligne.communes,
			communes_source: ligne.communes_source,
			actif: ligne.actif,
			retire_le: ligne.retire_le,
			bbox: ligne.bbox
				? {
						ouest: flottant( ligne.bbox.ouest ),
						sud: flottant( ligne.bbox.sud ),
						est: flottant( ligne.bbox.est ),
						nord: flottant( ligne.bbox.nord ),
				  }
				: null,
			centre: ligne.centre
				? { lon: flottant( ligne.centre.lon ), lat: flottant( ligne.centre.lat ) }
				: null,
			source: ligne.source,
			note_provenance: ligne.note_provenance,
		};
	}

	return {
		schema: SCHEMA,
		genere_le: genereLe,
		source: {
			producteur: PROVENANCE.producteur,
			jeu_de_donnees: PROVENANCE.jeu_de_donnees,
			couche: PROVENANCE.couche,
			dataset_id: PROVENANCE.dataset_id,
			geoide_id: PROVENANCE.geoide_id,
			dataset_url: PROVENANCE.dataset_url,
			donnees_du: PROVENANCE.donnees_du,
			donnees_du_libelle: PROVENANCE.donnees_du_libelle,
			recupere_le: PROVENANCE.recupere_le,
			sha256: sourceSha256,
			crs_source: PROVENANCE.crs_source,
			crs_publie: PROVENANCE.crs_publie,
			base_reglementaire: PROVENANCE.base_reglementaire,
			dispositif: PROVENANCE.dispositif,
			flux_identifiants_total: FLUX_PREFECTURE.identifiants_total,
			flux_identifiants_sans_correspondance: FLUX_PREFECTURE.sans_correspondance,
			flux_identifiants_sans_correspondance_note: FLUX_PREFECTURE.note,
			archive: archive,
		},
		licence: LICENCE,
		attribution: {
			phrase: ATTRIBUTION.phrase,
			phrase_courte: ATTRIBUTION.phrase_courte,
			lien_source: PROVENANCE.dataset_url,
			lien_licence: LICENCE.url,
		},
		geometrie,
		/*
		 * Bloc du référentiel communal, SÉPARÉ de `source` et de `attribution` :
		 * deux producteurs, deux licences, deux millésimes. Les fusionner
		 * produirait une phrase qui n'attribue correctement ni la DDTM ni l'IGN,
		 * et la Licence Ouverte 2.0 impose une citation exacte.
		 */
		communes,
		emprise: {
			bbox: {
				ouest: flottant( arrondir( bbox.ouest, DECIMALES_COORDONNEES ) ),
				sud: flottant( arrondir( bbox.sud, DECIMALES_COORDONNEES ) ),
				est: flottant( arrondir( bbox.est, DECIMALES_COORDONNEES ) ),
				nord: flottant( arrondir( bbox.nord, DECIMALES_COORDONNEES ) ),
			},
			centre: {
				lon: flottant( arrondir( ( bbox.ouest + bbox.est ) / 2, DECIMALES_COORDONNEES ) ),
				lat: flottant( arrondir( ( bbox.sud + bbox.nord ) / 2, DECIMALES_COORDONNEES ) ),
			},
			zoom_max: SIMPLIFICATION.zoom_max,
		},
		lacunes: LACUNES,
		correspondance_source: correspondanceSource,
		massifs,
	};
}

/** Rend le fichier PHP complet, en-tête comprise. */
export function rendrePhp( donnees ) {
	const entete = `<?php
/**
 * Référentiel des massifs forestiers des Bouches-du-Rhône — données.
 *
 * FICHIER GÉNÉRÉ — NE PAS ÉDITER À LA MAIN.
 * Produit par \`includes/domain/massifs/build/importer.mjs\` (npm run importer)
 * à partir de la source open data archivée dans \`build/source/\`.
 *
 * Politique de ré-import, en une phrase : l'import peut mettre à jour une
 * géométrie automatiquement ; il ne peut jamais créer, supprimer, renommer ni
 * re-lier une identité sans décision humaine.
 *
 * Procédure cas par cas : \`includes/domain/massifs/README.md\`.
 * Identités gelées, éditées à la main : \`build/identites.json\`.
 *
 * Ce fichier ne s'ouvre pas directement : il se lit par les fonctions
 * \`massifs_*()\` du module. Il ne contient aucune coordonnée de géométrie —
 * celles-ci vivent dans \`massifs-13.geometrie.json\`, servi en statique.
 *
 * \`source.identifiant_prefecture\` et le bloc \`correspondance_source\` portent la
 * correspondance GELÉE entre nos codes et les identifiants du flux journalier de
 * la préfecture. Elle est recopiée depuis \`build/identites.json\`, où elle a été
 * vérifiée à la main : elle ne se DÉDUIT JAMAIS de \`source.gid\`, qui n'est qu'un
 * rang alphabétique et se renumérote à la moindre insertion. Les identifiants
 * listés dans \`source.flux_identifiants_sans_correspondance\` sont en surnombre :
 * le flux les porte, aucune publication officielle ne les nomme, ils n'ont donc
 * volontairement aucun massif.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/*
 * Garde volontairement SANS \`exit\` : hors WordPress, le fichier retourne un
 * tableau vide au lieu d'interrompre le processus. C'est ce qui permet au
 * vérificateur de build de le lire (\`php -d… -r\` avec MASSIFS_VERIFICATION)
 * sans amorcer WordPress. Ne pas « corriger » en \`exit\`.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'MASSIFS_VERIFICATION' ) ) {
	return array();
}

return `;

	return `${ entete }${ rendrePhpValeur( donnees, 0 ) };\n`;
}

/* -------------------------------------------------------------------------- */
/* Contrôles et fichier de fidélité                                            */
/* -------------------------------------------------------------------------- */

/**
 * Forme d'un code INSEE. Sert à REFUSER, jamais à valider : `massifs[].communes`
 * porte des NOMS. Un code INSEE y serait expédié tel quel dans le JSON public et
 * affiché au visiteur (interdit 6 du contrat #45).
 */
const FORME_CODE_INSEE = /^[0-9][0-9AB][0-9]{3}$/;

/** Contrôles booléens de recette : ce qui rend §4.1 vérifiable plutôt qu'affirmé. */
export function controler( { lignes, simplifieFC, octets, emprise, lookup } ) {
	const codes = lignes.map( ( ligne ) => ligne.code );
	const regex = new RegExp( SEUILS.code_regex );
	const formeIdentifiant = new RegExp( SEUILS.identifiant_prefecture_regex );
	const identifiants = lignes.map( ( ligne ) => ligne.source.identifiant_prefecture );
	const proprietes = simplifieFC.features.every(
		( f ) => 1 === Object.keys( f.properties ).length && 'code' in f.properties
	);

	return {
		codes_uniques: new Set( codes ).size === codes.length,
		identifiants_prefecture_presents: identifiants.every(
			( identifiant ) => 'string' === typeof identifiant && '' !== identifiant
		),
		identifiants_prefecture_conformes_regex: identifiants.every(
			( identifiant ) => formeIdentifiant.test( String( identifiant ) )
		),
		identifiants_prefecture_uniques: new Set( identifiants ).size === identifiants.length,
		identifiants_prefecture_en_surnombre_non_rattaches: identifiants.every(
			( identifiant ) => ! FLUX_PREFECTURE.sans_correspondance.includes( identifiant )
		),
		codes_conformes_regex: codes.every( ( code ) => regex.test( code ) ),
		libelles_non_vides: lignes.every( ( ligne ) => '' !== ligne.libelle.trim() ),
		notes_provenance_completes: lignes.every(
			( ligne ) => ligne.libelle === ligne.source.nom_massif || Boolean( ligne.note_provenance )
		),
		gid_source_unique: new Set( lignes.map( ( l ) => l.source.gid ) ).size === lignes.length,
		features_25: SEUILS.features_attendues === simplifieFC.features.length,
		geometrie_proprietes_code_seul: proprietes,
		budget_geometrie_tenu: octets <= SEUILS.octets_bruts_max,
		// Un massif actif sans aucune commune signale une intersection qui n'a rien
		// trouvé — extrait communal amputé, ou emprise fausse. Une liste vide se
		// lirait « aucune commune concernée », ce qui serait faux.
		communes_par_massif_peuplees: lignes
			.filter( ( ligne ) => ligne.actif )
			.every( ( ligne ) => Array.isArray( ligne.communes ) && ligne.communes.length > 0 ),
		communes_sont_des_noms: lignes.every( ( ligne ) =>
			ligne.communes.every( ( nom ) => 'string' === typeof nom && '' !== nom.trim() && ! FORME_CODE_INSEE.test( nom ) )
		),
		communes_par_massif_sans_doublon: lignes.every(
			( ligne ) => new Set( ligne.communes ).size === ligne.communes.length
		),
		communes_source_renseignee: lignes.every(
			( ligne ) => 'string' === typeof ligne.communes_source && '' !== ligne.communes_source
		),
		lookup_communes_uniques: new Set( lookup.insee ).size === lookup.insee.length,
		lookup_marseille_unique: 1 === lookup.insee.filter( ( code ) => '13055' === code ).length,
		lookup_noms_non_vides: lookup.noms.every( ( nom ) => 'string' === typeof nom && '' !== nom.trim() ),
		// L'alias mouvant ne se lit dans AUCUN artefact (§2.1) : un millésime qui
		// dérive en silence afficherait un nom de commune périmé comme courant.
		lookup_sans_alias_de_millesime: ! lookup.contenu.includes( 'LATEST' ),
		// La couverture annoncée doit contenir l'emprise des massifs : sinon une
		// zone de feu du département tomberait « hors couverture » à tort.
		lookup_couverture_contient_emprise:
			lookup.couverture.ouest <= emprise.ouest &&
			lookup.couverture.est >= emprise.est &&
			lookup.couverture.sud <= emprise.sud &&
			lookup.couverture.nord >= emprise.nord,
		bbox_massifs_incluses_dans_emprise: lignes
			.filter( ( ligne ) => ligne.bbox )
			.every(
				( ligne ) =>
					ligne.bbox.ouest >= emprise.ouest &&
					ligne.bbox.est <= emprise.est &&
					ligne.bbox.sud >= emprise.sud &&
					ligne.bbox.nord <= emprise.nord
			),
	};
}

export function verdict( controles, metriques, octets ) {
	const echecs = Object.entries( controles )
		.filter( ( [ , valeur ] ) => ! valeur )
		.map( ( [ nom ] ) => nom );

	if ( octets > SEUILS.octets_bruts_max ) {
		echecs.push( 'octets_bruts_max' );
	}

	if ( metriques.global_metrics.max_deviation_m > SEUILS.ecart_max_m ) {
		echecs.push( 'ecart_max_m' );
	}

	if ( Math.abs( metriques.global_metrics.area_delta_pct ) > SEUILS.ecart_surface_global_abs_pct_max ) {
		echecs.push( 'ecart_surface_global_abs_pct_max' );
	}

	if ( metriques.global_metrics.area_delta_abs_worst_pct > SEUILS.ecart_surface_massif_abs_pct_max ) {
		echecs.push( 'ecart_surface_massif_abs_pct_max' );
	}

	if (
		metriques.global_metrics.dropped_ring_area_pct_of_total > SEUILS.surface_anneaux_supprimes_pct_max
	) {
		echecs.push( 'surface_anneaux_supprimes_pct_max' );
	}

	return { statut: 0 === echecs.length ? 'conforme' : 'non_conforme', controles_en_echec: echecs };
}

/* -------------------------------------------------------------------------- */
/* Émission atomique                                                           */
/* -------------------------------------------------------------------------- */

/**
 * Émission atomique des artefacts : tout en temporaires, puis renommage en bloc.
 *
 * Si un renommage échoue à mi-parcours, le dépôt porte une géométrie neuve avec
 * des métadonnées anciennes — donc un jeton de cache-busting FAUX. On ne tente
 * aucun retour en arrière automatique : ce serait une seconde écriture dans un
 * état déjà incertain. On purge ce qui n'est pas encore posé, on nomme
 * exactement ce qui l'est, et la reprise reste une décision humaine.
 */
function ecrireAtomique( sorties ) {
	const temporaires = sorties.map( ( { chemin, contenu } ) => {
		const temporaire = `${ chemin }.tmp`;
		fs.writeFileSync( temporaire, contenu );
		return { temporaire, chemin };
	} );

	const renommes = [];

	for ( const { temporaire, chemin } of temporaires ) {
		try {
			fs.renameSync( temporaire, chemin );
			renommes.push( chemin );
		} catch ( erreur ) {
			for ( const reste of temporaires ) {
				if ( fs.existsSync( reste.temporaire ) ) {
					fs.unlinkSync( reste.temporaire );
				}
			}

			const restauration =
				renommes.length > 0
					? renommes.map( ( fichier ) => `    git checkout -- ${ relatifAuDepot( fichier ) }` ).join( '\n' )
					: '    (aucun — le premier renommage a échoué, les artefacts en place sont intacts)';

			throw new Arret(
				`Renommage impossible vers ${ relatifAuDepot( chemin ) } : ${ erreur.message }\n` +
					'Les temporaires restants ont été purgés. Fichiers DÉJÀ remplacés, à restaurer à la main :\n' +
					`${ restauration }\n` +
					'Aucun retour en arrière automatique : réécrire dans un état incertain l\'aggraverait.'
			);
		}
	}
}

function simplifier( sourceFC ) {
	if ( ! fs.existsSync( CHEMINS.mapshaper ) ) {
		throw new Arret( MAPSHAPER_ABSENT );
	}

	const entree = path.join( RACINE, '_src_code.geojson' );
	const sortie = path.join( RACINE, '_simplifie.geojson' );

	// mapshaper ne reçoit que `code` : la géométrie publiée ne porte aucun nom,
	// aucun niveau, aucune couleur.
	fs.writeFileSync(
		entree,
		JSON.stringify( {
			type: 'FeatureCollection',
			features: sourceFC.features.map( ( f ) => ( {
				type: 'Feature',
				properties: { code: f.properties.code },
				geometry: f.geometry,
			} ) ),
		} )
	);

	// L'ordre compte : les îlots sont retirés AVANT la simplification, sinon le
	// lissage les déforme d'abord et le filtre trie ensuite sur des aires qui ne
	// sont plus celles de la source.
	const arguments_ = [
		CHEMINS.mapshaper,
		entree,
		'-filter-islands',
		`min-area=${ SIMPLIFICATION.ilots_min_m2 }`,
		'-simplify',
		'dp',
		`interval=${ SIMPLIFICATION.intervalle_m }`,
		'keep-shapes',
		'-o',
		`precision=0.${ '0'.repeat( SIMPLIFICATION.precision_decimales - 1 ) }1`,
		'format=geojson',
		sortie,
	];

	const execution = spawnSync( process.execPath, arguments_, { encoding: 'utf8' } );

	if ( 0 !== execution.status ) {
		throw new Arret( `mapshaper a échoué : ${ execution.stderr || execution.stdout }` );
	}

	const contenu = fs.readFileSync( sortie );
	fs.unlinkSync( entree );
	fs.unlinkSync( sortie );

	// Argv réellement exécuté, chemins ramenés à `build/` : le chemin absolu du
	// binaire node de la machine n'a rien à faire dans un artefact versionné, il
	// produirait une dérive fantôme à chaque changement de poste.
	return {
		contenu,
		argv: [
			'node',
			...arguments_.map( ( argument ) =>
				path.isAbsolute( argument ) ? relatifAuBuild( argument ) : argument
			),
		],
	};
}

/* -------------------------------------------------------------------------- */
/* Empreinte de référence et dérive                                            */
/* -------------------------------------------------------------------------- */

/** Aplatit un objet en `chemin.pointé -> valeur`, pour comparer clé par clé. */
function aplatir( valeur, prefixe = '', sortie = {} ) {
	if ( null !== valeur && 'object' === typeof valeur && ! Array.isArray( valeur ) ) {
		for ( const [ cle, sous ] of Object.entries( valeur ) ) {
			aplatir( sous, '' === prefixe ? cle : `${ prefixe }.${ cle }`, sortie );
		}

		return sortie;
	}

	sortie[ prefixe ] = Array.isArray( valeur ) ? JSON.stringify( valeur ) : valeur;

	return sortie;
}

/**
 * Affiche la dérive par rapport au `reference.json` en place, avant de l'écraser.
 *
 * Sans cet affichage, un ré-import remplace l'empreinte de référence en silence
 * et personne ne voit ce qui a bougé : le mécanisme de détection de dérive
 * s'auto-annulerait à chaque exécution.
 */
function afficherDerive( ancienne, nouvelle ) {
	if ( ! ancienne ) {
		process.stdout.write( 'Aucun reference.json en place : première émission, rien à comparer.\n' );
		return;
	}

	const avant = aplatir( ancienne );
	const apres = aplatir( nouvelle );
	const cles = [ ...new Set( [ ...Object.keys( avant ), ...Object.keys( apres ) ] ) ].filter(
		( cle ) => 'a_propos' !== cle
	);
	const lignes = cles
		.filter( ( cle ) => avant[ cle ] !== apres[ cle ] )
		.map( ( cle ) => `  ${ cle } : ${ avant[ cle ] } → ${ apres[ cle ] }` );

	if ( 0 === lignes.length ) {
		process.stdout.write( 'DÉRIVE PAR RAPPORT À reference.json : aucune.\n' );
		return;
	}

	process.stdout.write( `DÉRIVE PAR RAPPORT À reference.json :\n${ lignes.join( '\n' ) }\n` );
}

/**
 * Empreinte de référence des artefacts, émise par l'import et jamais éditée.
 *
 * Elle rend la reproductibilité vérifiable : la recette compare les artefacts en
 * place à ces valeurs. Aucune taille gzip ici — la sortie de zlib varie avec sa
 * version et créerait une dérive fantôme sans aucun changement de géométrie.
 */
function construireReference( { genereLe, mapshaper, empreinteSource, octetsSource, empreinte, octets, metriques, communes } ) {
	return {
		a_propos:
			'Empreinte de référence des artefacts. ÉMIS PAR `npm run importer`, jamais édité à la main. `npm run verifier` compare les artefacts en place à ces valeurs : une différence est une dérive à expliquer. Si le changement est voulu, régénérer les artefacts ET ce fichier par `npm run importer`, dans le même commit.',
		genere_le: genereLe,
		outillage: {
			mapshaper,
			node_major: nodeMajeur(),
		},
		source: {
			fichier: CHEMIN_SOURCE_RELATIF,
			sha256: empreinteSource,
			octets: octetsSource,
		},
		geometrie: {
			fichier: path.basename( CHEMINS.geometrie ),
			sha256: empreinte,
			octets,
			sommets: metriques.global_metrics.out_vertices,
			ecart_max_m: metriques.global_metrics.max_deviation_m,
		},
		/*
		 * Le référentiel communal entre dans la même empreinte de référence que la
		 * géométrie, et pour la même raison : `npm run verifier` doit pouvoir dire
		 * que l'artefact en place est LE MÊME qu'au dernier import assumé, et pas
		 * seulement qu'il tient les seuils. Le millésime y figure parce qu'un
		 * changement de millésime change des noms de communes sans changer une
		 * seule ligne de code.
		 */
		communes: {
			millesime: communes.millesime,
			extrait: {
				fichier: CHEMINS_COMMUNES_RELATIFS.extrait,
				sha256: communes.extrait_sha256,
				octets: communes.extrait_octets,
			},
			lookup: {
				fichier: CHEMINS_COMMUNES_RELATIFS.lookup,
				sha256: communes.lookup_sha256,
				octets: communes.lookup_octets,
				communes: communes.nombre,
				sommets: communes.sommets,
			},
		},
	};
}

export async function importer() {
	const sourceBrute = fs.readFileSync( CHEMINS.source );
	const sourceFC = JSON.parse( sourceBrute.toString( 'utf8' ) );
	const registre = lireJson( CHEMINS.identites );
	const empreinteSource = sha256( sourceBrute );
	const nomGeometrie = path.basename( CHEMINS.geometrie );
	const genereLe = horodatageImport();
	const mapshaper = versionMapshaper();

	const { lignes: appariement, journal } = reconcilier( sourceFC, registre );

	/*
	 * Référentiel communal. L'intersection se fait sur `sourceFC` — la source
	 * PLEINE PRÉCISION — et jamais sur la géométrie simplifiée : cette dernière a
	 * perdu ses îlots de moins de 25 ha et subi 90 m de Douglas-Peucker, et une
	 * commune y apparaîtrait ou en disparaîtrait pour des raisons de rendu (§4.2).
	 */
	const extraitCommunes = lireExtraitCommunes();
	const { parMassif, mesures: mesuresCommunes } = communesParMassif(
		appariement.map( ( { identite, feature } ) => ( { code: identite.code, feature } ) ),
		extraitCommunes.fc
	);
	const lookup = construireLookup( extraitCommunes.fc, extraitCommunes.manifeste.decoupe );
	const artefactLookup = JSON.parse( lookup.contenu.toString( 'utf8' ) );
	// Empreinte calculée UNE FOIS : `data/massifs-13.php` et `reference.json` la
	// portent tous les deux, et deux calculs de la même empreinte sont deux
	// occasions d'en consigner deux différentes.
	const empreinteLookup = sha256( lookup.contenu );

	const lignes = construireLignes( appariement, parMassif );

	journal.forEach( ( entree ) => process.stdout.write( `  · ${ entree }\n` ) );

	process.stdout.write(
		`  · communes : millésime ${ SOURCE_COMMUNES.millesime }, ${ lookup.communes } dans le lookup ` +
			`(${ lookup.contenu.length } octets, ${ lookup.sommets } sommets)\n`
	);

	const { contenu: geometrieBrute, argv } = simplifier( sourceFC );
	const simplifieFC = JSON.parse( geometrieBrute.toString( 'utf8' ) );
	const empreinte = sha256( geometrieBrute );
	const octets = geometrieBrute.length;

	const metriques = mesurerFidelite( sourceFC, simplifieFC );

	const donnees = construireDonnees( {
		lignes,
		genereLe,
		sourceSha256: empreinteSource,
		archive: {
			fichier: CHEMIN_SOURCE_RELATIF,
			sha256: empreinteSource,
			octets: sourceBrute.length,
		},
		geometrie: {
			fichier: nomGeometrie,
			version: empreinte.slice( 0, 8 ),
			sha256: empreinte,
			octets,
			format: 'geojson',
			zoom_max: SIMPLIFICATION.zoom_max,
			algorithme: SIMPLIFICATION.algorithme,
			tolerance_m: SIMPLIFICATION.intervalle_m,
			precision_decimales: SIMPLIFICATION.precision_decimales,
		},
		communes: {
			producteur: SOURCE_COMMUNES.producteur,
			jeu_de_donnees: SOURCE_COMMUNES.jeu_de_donnees,
			couche: SOURCE_COMMUNES.couche,
			// Le millésime RÉSOLU, jamais l'alias (§2.1).
			millesime: SOURCE_COMMUNES.millesime,
			edition: SOURCE_COMMUNES.edition,
			edition_libelle: SOURCE_COMMUNES.edition_libelle,
			crs: SOURCE_COMMUNES.crs,
			departements: SOURCE_COMMUNES.departements,
			licence: LICENCE_COMMUNES,
			attribution: attributionCommunes(),
			seuil_massif_pct: SEUILS_COMMUNES.seuil_massif_pct,
			plafond_m: SEUILS_COMMUNES.plafond_m,
			source_liste: sourceCommunesParMassif(),
			archive: {
				fichier: CHEMINS_COMMUNES_RELATIFS.extrait,
				sha256: sha256( extraitCommunes.brut ),
				octets: extraitCommunes.octets,
				recupere_le: extraitCommunes.manifeste.recupere_le,
			},
			// Métadonnées de l'artefact de lookup. Le module PHP les lit pour SITUER le
			// fichier, jamais pour se dispenser de le valider : il est ouvert et contrôlé
			// à chaque fois qu'il sert.
			lookup: {
				fichier: CHEMINS_COMMUNES_RELATIFS.lookup,
				sha256: empreinteLookup,
				octets: lookup.contenu.length,
				nombre: lookup.communes,
				sommets: lookup.sommets,
				algorithme: LOOKUP.algorithme,
				tolerance_m: LOOKUP.intervalle_m,
				precision_decimales: LOOKUP.precision_decimales,
				couverture: {
					ouest: flottant( lookup.couverture.ouest ),
					sud: flottant( lookup.couverture.sud ),
					est: flottant( lookup.couverture.est ),
					nord: flottant( lookup.couverture.nord ),
				},
			},
		},
	} );

	const emprise = {
		ouest: donnees.emprise.bbox.ouest.__flottant,
		sud: donnees.emprise.bbox.sud.__flottant,
		est: donnees.emprise.bbox.est.__flottant,
		nord: donnees.emprise.bbox.nord.__flottant,
	};
	const controles = controler( {
		lignes,
		simplifieFC,
		octets,
		emprise,
		lookup: {
			insee: artefactLookup.communes.map( ( commune ) => commune.insee ),
			noms: artefactLookup.communes.map( ( commune ) => commune.nom ),
			contenu: lookup.contenu.toString( 'utf8' ),
			couverture: lookup.couverture,
		},
	} );
	const conclusion = verdict( controles, metriques, octets );

	if ( 'conforme' !== conclusion.statut ) {
		throw new Arret( `Contrôles en échec : ${ conclusion.controles_en_echec.join( ', ' ) }. Rien n'a été écrit.` );
	}

	const nomSource = path.basename( CHEMINS.source );
	const fidelite = {
		artefact: nomGeometrie,
		a_propos: RECETTE.a_propos,
		genere_le: genereLe,
		// Tout ce bloc est MESURÉ sur la source archivée — celle que relit un
		// ré-import et que n'importe qui peut recalculer depuis le dépôt. Aucune
		// valeur héritée d'un fichier absent du dépôt : une preuve invérifiable
		// n'est pas une preuve.
		source: {
			file: CHEMIN_SOURCE_RELATIF,
			features: sourceFC.features.length,
			rings: metriques.global_metrics.src_rings,
			coordinate_pairs: metriques.global_metrics.src_vertices,
			raw_bytes: sourceBrute.length,
			gzip_bytes: gzipSync( sourceBrute ).length,
			sha256: empreinteSource,
			crs: PROVENANCE.crs_publie,
			note:
				`Copie de la source ramenée à 5 décimales (~1,1 m), très en dessous de l'intervalle de ` +
				`simplification de ${ SIMPLIFICATION.intervalle_m } m. C'est ce fichier que relit un ré-import, ` +
				'et le seul sur lequel les métriques ci-dessous sont mesurées.',
		},
		// `verifier.mjs` compare cette empreinte à celle du fichier relu : sans ce
		// bloc, la recette échoue sur une clé absente au lieu d'une dérive réelle.
		empreintes: {
			[ nomGeometrie ]: empreinte,
		},
		simplification: {
			tool: `mapshaper ${ mapshaper }`,
			algorithm: 'Douglas-Peucker',
			interval_m: SIMPLIFICATION.intervalle_m,
			// Appliqué AVANT la simplification : filtrer après ferait trier le seuil
			// sur des aires déjà déformées par le lissage. Ne déplace aucun sommet —
			// l'écart maximal est identique avec et sans.
			filter_islands_min_area_m2: SIMPLIFICATION.ilots_min_m2,
			keep_shapes: true,
			output_coordinate_precision_deg: Number( `0.${ '0'.repeat( SIMPLIFICATION.precision_decimales - 1 ) }1` ),
			topology_preserved: true,
			topology_note: RECETTE.topology_note,
			argv,
			commande: argv.join( ' ' ),
			temporaires_note:
				'Commande rejouable depuis `includes/domain/massifs/build/`. `_src_code.geojson` est la source ' +
				'réduite à `properties.code` (la géométrie publiée ne porte rien d\'autre), `_simplifie.geojson` ' +
				'la sortie de mapshaper avant émission : les deux sont créés puis supprimés par le pipeline.',
		},
		projection_used_for_metrics: RECETTE.projection_metriques,
		deviation_definition: RECETTE.deviation_definition,
		zooms_evalues: RECETTE.zooms_evalues,
		sizes: {
			[ nomGeometrie ]: { raw_bytes: octets, gzip_bytes: gzipSync( geometrieBrute ).length },
			[ nomSource ]: { raw_bytes: sourceBrute.length, gzip_bytes: gzipSync( sourceBrute ).length },
		},
		seuils: SEUILS,
		global_metrics: metriques.global_metrics,
		per_massif: metriques.per_massif,
		/*
		 * Communes par massif : la PREUVE du seuil, pas son affirmation. Chaque part
		 * mesurée est consignée, y compris celles rejetées — sans elles, personne ne
		 * peut voir qu'une commune est passée à 0,9 % et savoir que c'est le seuil,
		 * et non une intersection ratée, qui l'a écartée.
		 */
		communes: {
			a_propos:
				'Intersection des périmètres DDTM pleine précision avec l\'extrait communal IGN. Surfaces mesurées dans la projection ci-dessus. Une commune est retenue au-delà de `seuil_pct` de la surface du massif ; l\'ordre est celui des surfaces décroissantes.',
			millesime: SOURCE_COMMUNES.millesime,
			seuil_pct: SEUILS_COMMUNES.seuil_massif_pct,
			plafond_m: SEUILS_COMMUNES.plafond_m,
			extrait: {
				fichier: CHEMINS_COMMUNES_RELATIFS.extrait,
				communes: extraitCommunes.fc.features.length,
				octets: extraitCommunes.octets,
			},
			lookup: {
				fichier: CHEMINS_COMMUNES_RELATIFS.lookup,
				communes: lookup.communes,
				sommets: lookup.sommets,
				octets: lookup.contenu.length,
				intervalle_m: LOOKUP.intervalle_m,
				couverture: lookup.couverture,
			},
			par_massif: mesuresCommunes,
		},
		controles,
		verdict: conclusion,
	};

	const reference = construireReference( {
		genereLe,
		mapshaper,
		empreinteSource,
		octetsSource: sourceBrute.length,
		empreinte,
		octets,
		metriques,
		communes: {
			millesime: SOURCE_COMMUNES.millesime,
			extrait_sha256: donnees.communes.archive.sha256,
			extrait_octets: extraitCommunes.octets,
			lookup_sha256: empreinteLookup,
			lookup_octets: lookup.contenu.length,
			nombre: lookup.communes,
			sommets: lookup.sommets,
		},
	} );

	afficherDerive( fs.existsSync( CHEMINS.reference ) ? lireJson( CHEMINS.reference ) : null, reference );

	ecrireAtomique( [
		{ chemin: CHEMINS.geometrie, contenu: geometrieBrute },
		{ chemin: CHEMINS.metadonnees, contenu: rendrePhp( donnees ) },
		{ chemin: CHEMINS.fidelite, contenu: `${ JSON.stringify( fidelite, null, 2 ) }\n` },
		{ chemin: CHEMINS.reference, contenu: `${ JSON.stringify( reference, null, 2 ) }\n` },
		// L'artefact de lookup part dans le MÊME renommage en bloc que les autres :
		// des communes par massif d'un millésime et des polygones d'un autre se
		// contrediraient sans que rien ne le signale.
		{ chemin: CHEMINS_COMMUNES.lookup, contenu: lookup.contenu },
	] );

	process.stdout.write(
		`Import conforme : ${ lignes.length } massifs, ${ octets } octets bruts, ` +
			`écart max ${ metriques.global_metrics.max_deviation_m } m, ` +
			`sous-pixel jusqu'à z${ metriques.global_metrics.max_zoom_subpixel }.\n`
	);
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	try {
		await importer();
	} catch ( erreur ) {
		process.stderr.write( `${ erreur.message }\n` );
		process.exitCode = 1;
	}
}
