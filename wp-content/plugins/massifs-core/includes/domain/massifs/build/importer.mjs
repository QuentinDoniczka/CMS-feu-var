/**
 * Import du référentiel des massifs forestiers des Bouches-du-Rhône.
 *
 * Chaîne reproductible : source archivée -> réconciliation d'identités ->
 * simplification mapshaper -> émission atomique des trois artefacts.
 *
 *   node importer.mjs      (ou : npm run importer)
 *
 * Rien n'est écrit tant que tous les contrôles ne passent pas : les sorties
 * partent d'abord dans des fichiers temporaires, puis sont renommées en bloc.
 * Un import à moitié appliqué laisserait le site avec une géométrie neuve et
 * des métadonnées anciennes — donc un cache-busting faux.
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

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );
const EXTENSION = path.resolve( RACINE, '../../../..' );

const CHEMINS = {
	source: path.join( RACINE, 'source/massifs-13.full.geojson' ),
	identites: path.join( RACINE, 'identites.json' ),
	geometrie: path.join( EXTENSION, 'data/massifs-13.geometrie.json' ),
	metadonnees: path.join( EXTENSION, 'data/massifs-13.php' ),
	fidelite: path.join( EXTENSION, 'data/massifs-13.fidelite.json' ),
	mapshaper: path.join( RACINE, 'node_modules/mapshaper/bin/mapshaper' ),
};

/** Chemin de la source archivée, relatif à la racine de l'extension, tel que consigné dans les artefacts. */
const CHEMIN_SOURCE_RELATIF = 'includes/domain/massifs/build/source/massifs-13.full.geojson';

/** Version du schéma du fichier de métadonnées lu par le module PHP. */
export const SCHEMA = 1;

/**
 * Paramètres de simplification. Le paramètre `interval` de Douglas-Peucker EST
 * la borne de déviation : la mesurer après coup rend la garantie §4.1 opposable
 * au lieu d'être postulée.
 */
export const SIMPLIFICATION = {
	algorithme: 'douglas-peucker',
	intervalle_m: 90,
	precision_decimales: 4,
	zoom_max: 11,
};

/**
 * Seuils de recette. Le budget est exprimé en octets BRUTS : la compression
 * HTTP n'est vérifiée sur aucune cible, elle reste une marge et non une béquille.
 */
export const SEUILS = {
	octets_bruts_max: 307200,
	ecart_max_m: 120,
	ecart_surface_global_abs_pct_max: 0.5,
	ecart_surface_massif_abs_pct_max: 3,
	surface_anneaux_supprimes_pct_max: 0.5,
	features_attendues: 25,
	code_regex: '^[a-z0-9_-]{1,64}$',
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

/** Lacune assumée : l'attribut n'existe nulle part dans la couche source. */
export const LACUNES = {
	communes: {
		statut: 'inconnue',
		raison: 'aucun attribut de commune dans la couche L_MASSIFS_FORESTIERS_S_013',
		source_pressentie: 'IGN ADMIN EXPRESS',
	},
};

const LAT_REFERENCE = 43.5;
const METRES_PAR_DEGRE_LAT = 110540;
const METRES_PAR_DEGRE_LON = 111320 * Math.cos( ( LAT_REFERENCE * Math.PI ) / 180 );

/** Arrêt volontaire de l'import : rien n'a été écrit. */
export class Arret extends Error {}

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

function arrondir( valeur, decimales ) {
	const facteur = 10 ** decimales;
	return Math.round( valeur * facteur ) / facteur;
}

/* -------------------------------------------------------------------------- */
/* Géométrie                                                                   */
/* -------------------------------------------------------------------------- */

/** Découpe une géométrie GeoJSON en parties, chaque partie = [extérieur, ...trous]. */
function parties( geometrie ) {
	if ( 'Polygon' === geometrie.type ) {
		return [ geometrie.coordinates ];
	}

	if ( 'MultiPolygon' === geometrie.type ) {
		return geometrie.coordinates;
	}

	throw new Arret( `Géométrie non surfacique : ${ geometrie.type }` );
}

function projeter( [ lon, lat ] ) {
	return [ lon * METRES_PAR_DEGRE_LON, lat * METRES_PAR_DEGRE_LAT ];
}

function aireAnneau( anneau ) {
	let somme = 0;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = projeter( anneau[ i ] );
		const [ xj, yj ] = projeter( anneau[ j ] );
		somme += xj * yi - xi * yj;
	}

	return Math.abs( somme ) / 2;
}

function airePartie( partie ) {
	const trous = partie.slice( 1 ).reduce( ( total, trou ) => total + aireAnneau( trou ), 0 );
	return aireAnneau( partie[ 0 ] ) - trous;
}

function aireGeometrie( geometrie ) {
	return parties( geometrie ).reduce( ( total, partie ) => total + airePartie( partie ), 0 );
}

function anneaux( geometrie ) {
	return parties( geometrie ).flat();
}

function bboxGeometrie( geometrie ) {
	const boite = { ouest: Infinity, sud: Infinity, est: -Infinity, nord: -Infinity };

	for ( const anneau of anneaux( geometrie ) ) {
		for ( const [ lon, lat ] of anneau ) {
			boite.ouest = Math.min( boite.ouest, lon );
			boite.est = Math.max( boite.est, lon );
			boite.sud = Math.min( boite.sud, lat );
			boite.nord = Math.max( boite.nord, lat );
		}
	}

	return boite;
}

function centroideAnneau( anneau ) {
	let aire2 = 0;
	let cx = 0;
	let cy = 0;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];
		const croix = xj * yi - xi * yj;
		aire2 += croix;
		cx += ( xj + xi ) * croix;
		cy += ( yj + yi ) * croix;
	}

	if ( 0 === aire2 ) {
		return anneau[ 0 ];
	}

	return [ cx / ( 3 * aire2 ), cy / ( 3 * aire2 ) ];
}

function dansAnneau( [ lon, lat ], anneau ) {
	let dedans = false;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];

		if ( yi > lat !== yj > lat && lon < ( ( xj - xi ) * ( lat - yi ) ) / ( yj - yi ) + xi ) {
			dedans = ! dedans;
		}
	}

	return dedans;
}

function dansPartie( point, partie ) {
	if ( ! dansAnneau( point, partie[ 0 ] ) ) {
		return false;
	}

	return ! partie.slice( 1 ).some( ( trou ) => dansAnneau( point, trou ) );
}

/**
 * Point intérieur représentatif : milieu du plus long segment intérieur de la
 * ligne horizontale passant par le centroïde. Le centre ancre les étiquettes de
 * la carte ; posé hors du polygone sur un massif concave ou troué, il pointerait
 * un massif voisin.
 */
function pointInterieur( partie ) {
	const centroide = centroideAnneau( partie[ 0 ] );

	if ( dansPartie( centroide, partie ) ) {
		return centroide;
	}

	const lat = centroide[ 1 ];
	const abscisses = [];

	for ( const anneau of partie ) {
		for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
			const [ xi, yi ] = anneau[ i ];
			const [ xj, yj ] = anneau[ j ];

			if ( yi > lat !== yj > lat ) {
				abscisses.push( ( ( xj - xi ) * ( lat - yi ) ) / ( yj - yi ) + xi );
			}
		}
	}

	abscisses.sort( ( a, b ) => a - b );

	let meilleur = centroide;
	let largeur = -1;

	for ( let i = 0; i + 1 < abscisses.length; i += 2 ) {
		const milieu = ( abscisses[ i ] + abscisses[ i + 1 ] ) / 2;

		if ( abscisses[ i + 1 ] - abscisses[ i ] > largeur && dansPartie( [ milieu, lat ], partie ) ) {
			largeur = abscisses[ i + 1 ] - abscisses[ i ];
			meilleur = [ milieu, lat ];
		}
	}

	return meilleur;
}

/** bbox + centre d'un massif, calculés sur la géométrie PRÉCISE, jamais sur la simplifiée. */
export function mesurerMassif( geometrie ) {
	const listeParties = parties( geometrie );
	const principale = listeParties.reduce( ( a, b ) => ( airePartie( b ) > airePartie( a ) ? b : a ) );
	const [ lon, lat ] = pointInterieur( principale );
	const boite = bboxGeometrie( geometrie );

	return {
		bbox: {
			ouest: arrondir( boite.ouest, 5 ),
			sud: arrondir( boite.sud, 5 ),
			est: arrondir( boite.est, 5 ),
			nord: arrondir( boite.nord, 5 ),
		},
		centre: { lon: arrondir( lon, 5 ), lat: arrondir( lat, 5 ) },
	};
}

/* -------------------------------------------------------------------------- */
/* Fidélité                                                                    */
/* -------------------------------------------------------------------------- */

function distancePointSegment( p, a, b ) {
	const [ px, py ] = projeter( p );
	const [ ax, ay ] = projeter( a );
	const [ bx, by ] = projeter( b );
	const dx = bx - ax;
	const dy = by - ay;
	const carre = dx * dx + dy * dy;
	let t = 0;

	if ( carre > 0 ) {
		t = Math.max( 0, Math.min( 1, ( ( px - ax ) * dx + ( py - ay ) * dy ) / carre ) );
	}

	const ex = ax + t * dx - px;
	const ey = ay + t * dy - py;

	return Math.sqrt( ex * ex + ey * ey );
}

function distanceAnneau( point, anneau ) {
	let minimum = Infinity;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const distance = distancePointSegment( point, anneau[ j ], anneau[ i ] );

		if ( distance < minimum ) {
			minimum = distance;
		}
	}

	return minimum;
}

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
			area_delta_abs_worst_pct: Math.abs( pires[ 0 ].area_delta_pct ),
			area_delta_abs_worst_massif: pires[ 0 ].code,
			max_deviation_m: arrondir( ecarts[ ecarts.length - 1 ], 2 ),
			p99_deviation_m: quantile( 0.99 ),
			mean_deviation_m: arrondir( ecarts.reduce( ( a, b ) => a + b, 0 ) / ecarts.length, 3 ),
			quantization_max_m: arrondir( quantisationMax, 3 ),
		},
		per_massif: parMassif,
	};
}

/* -------------------------------------------------------------------------- */
/* Réconciliation des identités                                                */
/* -------------------------------------------------------------------------- */

/**
 * Rapproche les entités source du registre d'identités gelées.
 *
 * Toute situation qui exigerait de créer, renommer ou re-lier une identité lève
 * un `Arret` : c'est une décision humaine, jamais une conséquence d'un `npm run`.
 */
export function reconcilier( sourceFC, registre ) {
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

/** Construit les 25 lignes de massif, pré-triées par `tri`. */
export function construireLignes( appariement ) {
	const lignes = appariement.map( ( { identite, feature, gid } ) => {
		const mesures = feature
			? mesurerMassif( feature.geometry )
			: { bbox: null, centre: null };

		return {
			code: identite.code,
			libelle: identite.libelle,
			tri: slugifier( identite.libelle ),
			communes: identite.communes,
			communes_source: identite.communes_source,
			actif: ! identite.retire_le,
			retire_le: identite.retire_le,
			bbox: mesures.bbox,
			centre: mesures.centre,
			source: {
				gid,
				nom_massif: identite.source.nom_massif,
				revision: PROVENANCE.donnees_du,
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
export function construireDonnees( { lignes, geometrie, genereLe, sourceSha256, archive } ) {
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

	for ( const ligne of lignes ) {
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
		emprise: {
			bbox: {
				ouest: flottant( arrondir( bbox.ouest, 5 ) ),
				sud: flottant( arrondir( bbox.sud, 5 ) ),
				est: flottant( arrondir( bbox.est, 5 ) ),
				nord: flottant( arrondir( bbox.nord, 5 ) ),
			},
			centre: {
				lon: flottant( arrondir( ( bbox.ouest + bbox.est ) / 2, 5 ) ),
				lat: flottant( arrondir( ( bbox.sud + bbox.nord ) / 2, 5 ) ),
			},
			zoom_max: SIMPLIFICATION.zoom_max,
		},
		lacunes: LACUNES,
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

/** Contrôles booléens de recette : ce qui rend §4.1 vérifiable plutôt qu'affirmé. */
export function controler( { lignes, simplifieFC, octets, emprise } ) {
	const codes = lignes.map( ( ligne ) => ligne.code );
	const regex = new RegExp( SEUILS.code_regex );
	const proprietes = simplifieFC.features.every(
		( f ) => 1 === Object.keys( f.properties ).length && 'code' in f.properties
	);

	return {
		codes_uniques: new Set( codes ).size === codes.length,
		codes_conformes_regex: codes.every( ( code ) => regex.test( code ) ),
		libelles_non_vides: lignes.every( ( ligne ) => '' !== ligne.libelle.trim() ),
		notes_provenance_completes: lignes.every(
			( ligne ) => ligne.libelle === ligne.source.nom_massif || Boolean( ligne.note_provenance )
		),
		gid_source_unique: new Set( lignes.map( ( l ) => l.source.gid ) ).size === lignes.length,
		features_25: SEUILS.features_attendues === simplifieFC.features.length,
		geometrie_proprietes_code_seul: proprietes,
		budget_geometrie_tenu: octets <= SEUILS.octets_bruts_max,
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

function ecrireAtomique( sorties ) {
	const temporaires = sorties.map( ( { chemin, contenu } ) => {
		const temporaire = `${ chemin }.tmp`;
		fs.writeFileSync( temporaire, contenu );
		return { temporaire, chemin };
	} );

	for ( const { temporaire, chemin } of temporaires ) {
		fs.renameSync( temporaire, chemin );
	}
}

function simplifier( sourceFC ) {
	if ( ! fs.existsSync( CHEMINS.mapshaper ) ) {
		throw new Arret( 'mapshaper est absent : lancer `npm ci` dans includes/domain/massifs/build/.' );
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

	const arguments_ = [
		CHEMINS.mapshaper,
		entree,
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

	return contenu;
}

export async function importer() {
	const sourceBrute = fs.readFileSync( CHEMINS.source );
	const sourceFC = JSON.parse( sourceBrute.toString( 'utf8' ) );
	const registre = lireJson( CHEMINS.identites );
	const empreinteSource = sha256( sourceBrute );
	const nomGeometrie = path.basename( CHEMINS.geometrie );

	const { lignes: appariement, journal } = reconcilier( sourceFC, registre );
	const lignes = construireLignes( appariement );

	journal.forEach( ( entree ) => process.stdout.write( `  · ${ entree }\n` ) );

	const geometrieBrute = simplifier( sourceFC );
	const simplifieFC = JSON.parse( geometrieBrute.toString( 'utf8' ) );
	const empreinte = sha256( geometrieBrute );
	const octets = geometrieBrute.length;

	const metriques = mesurerFidelite( sourceFC, simplifieFC );

	const donnees = construireDonnees( {
		lignes,
		genereLe: new Date().toISOString().replace( /\.\d{3}Z$/, 'Z' ),
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
	} );

	const emprise = {
		ouest: donnees.emprise.bbox.ouest.__flottant,
		sud: donnees.emprise.bbox.sud.__flottant,
		est: donnees.emprise.bbox.est.__flottant,
		nord: donnees.emprise.bbox.nord.__flottant,
	};
	const controles = controler( { lignes, simplifieFC, octets, emprise } );
	const conclusion = verdict( controles, metriques, octets );

	if ( 'conforme' !== conclusion.statut ) {
		throw new Arret( `Contrôles en échec : ${ conclusion.controles_en_echec.join( ', ' ) }. Rien n'a été écrit.` );
	}

	const fidelite = {
		artefact: nomGeometrie,
		genere_le: donnees.genere_le,
		source: {
			file: CHEMIN_SOURCE_RELATIF,
			features: sourceFC.features.length,
			rings: metriques.global_metrics.src_rings,
			coordinate_pairs: metriques.global_metrics.src_vertices,
			raw_bytes: sourceBrute.length,
			crs: PROVENANCE.crs_publie,
		},
		// `verifier.mjs` compare cette empreinte à celle du fichier relu : sans ce
		// bloc, la recette échoue sur une clé absente au lieu d'une dérive réelle.
		empreintes: {
			[ nomGeometrie ]: empreinte,
		},
		simplification: {
			tool: 'mapshaper 0.6.102',
			algorithm: 'Douglas-Peucker',
			interval_m: SIMPLIFICATION.intervalle_m,
			keep_shapes: true,
			output_coordinate_precision_deg: 0.0001,
			topology_preserved: true,
		},
		sizes: {
			[ nomGeometrie ]: { raw_bytes: octets, gzip_bytes: gzipSync( geometrieBrute ).length },
		},
		seuils: SEUILS,
		global_metrics: metriques.global_metrics,
		per_massif: metriques.per_massif,
		controles,
		verdict: conclusion,
	};

	ecrireAtomique( [
		{ chemin: CHEMINS.geometrie, contenu: geometrieBrute },
		{ chemin: CHEMINS.metadonnees, contenu: rendrePhp( donnees ) },
		{ chemin: CHEMINS.fidelite, contenu: `${ JSON.stringify( fidelite, null, 2 ) }\n` },
	] );

	process.stdout.write(
		`Import conforme : ${ lignes.length } massifs, ${ octets } octets bruts, ` +
			`écart max ${ metriques.global_metrics.max_deviation_m } m.\n`
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
