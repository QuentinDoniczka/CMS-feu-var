/**
 * Recette du référentiel des massifs : relit les artefacts émis et rejoue tous
 * les contrôles, sans rien réécrire.
 *
 *   node verifier.mjs      (ou : npm run verifier)
 *
 * C'est ce fichier qui rend l'exigence §4.1 « périmètres fidèles » VÉRIFIABLE
 * plutôt qu'affirmée : la fidélité est remesurée depuis la source archivée, et
 * toute dérive par rapport aux seuils, aux tailles ou aux empreintes fait
 * sortir en code ≠ 0.
 *
 * Le fichier de métadonnées PHP est lu par `php -r`, ce que permet sa garde
 * volontairement dépourvue d'`exit`. Sans binaire PHP, les contrôles qui en
 * dépendent ne sont pas silencieusement passés : la sortie est en échec.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { mesurerFidelite, sha256, SEUILS, SCHEMA, FLUX_PREFECTURE } from './importer.mjs';

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );
const EXTENSION = path.resolve( RACINE, '../../../..' );
const PHP = process.env.PHP_BIN || 'php';

/** Tolérances de re-mesure : la source archivée est arrondie à 5 décimales (~1,1 m). */
const TOLERANCES = {
	ecart_m: 2,
	surface_pct: 0.05,
};

const echecs = [];
const constats = [];

function controler( nom, condition, detail ) {
	const ligne = `${ nom }${ detail ? ` — ${ detail }` : '' }`;

	if ( condition ) {
		constats.push( `  ok   ${ ligne }` );
		return;
	}

	echecs.push( ligne );
}

function lireMetadonneesPhp( chemin ) {
	const script = `define('MASSIFS_VERIFICATION', true); echo json_encode(require '${ chemin.replace( /\\/g, '/' ) }');`;
	const execution = spawnSync( PHP, [ '-r', script ], { encoding: 'utf8' } );

	if ( execution.error || 0 !== execution.status ) {
		return { erreur: execution.error ? execution.error.message : execution.stderr.trim() };
	}

	try {
		return { donnees: JSON.parse( execution.stdout ) };
	} catch ( erreur ) {
		return { erreur: `sortie PHP illisible : ${ erreur.message }` };
	}
}

const cheminGeometrie = path.join( EXTENSION, 'data/massifs-13.geometrie.json' );
const cheminPhp = path.join( EXTENSION, 'data/massifs-13.php' );
const cheminFidelite = path.join( EXTENSION, 'data/massifs-13.fidelite.json' );
const cheminSource = path.join( RACINE, 'source/massifs-13.full.geojson' );

for ( const chemin of [ cheminGeometrie, cheminPhp, cheminFidelite, cheminSource ] ) {
	controler( `présence de ${ path.basename( chemin ) }`, fs.existsSync( chemin ) );
}

if ( echecs.length > 0 ) {
	process.stderr.write( `Artefacts manquants :\n  - ${ echecs.join( '\n  - ' ) }\n` );
	process.exit( 1 );
}

const geometrieBrute = fs.readFileSync( cheminGeometrie );
const geometrieFC = JSON.parse( geometrieBrute.toString( 'utf8' ) );
const sourceBrute = fs.readFileSync( cheminSource );
const sourceFC = JSON.parse( sourceBrute.toString( 'utf8' ) );
const fidelite = JSON.parse( fs.readFileSync( cheminFidelite, 'utf8' ) );
const empreinteGeometrie = sha256( geometrieBrute );
const octets = geometrieBrute.length;
const regex = new RegExp( SEUILS.code_regex );
const codes = geometrieFC.features.map( ( f ) => f.properties.code );

controler( 'géométrie : FeatureCollection', 'FeatureCollection' === geometrieFC.type );
controler(
	'géométrie : nombre d\'entités',
	SEUILS.features_attendues === geometrieFC.features.length,
	`${ geometrieFC.features.length }`
);
controler(
	'géométrie : properties limitées à `code`',
	geometrieFC.features.every(
		( f ) => f.properties && 1 === Object.keys( f.properties ).length && 'string' === typeof f.properties.code
	)
);
controler( 'géométrie : aucune géométrie nulle', geometrieFC.features.every( ( f ) => f.geometry && f.geometry.coordinates ) );
controler( 'géométrie : codes uniques', new Set( codes ).size === codes.length );
controler( 'géométrie : codes conformes à la regex', codes.every( ( code ) => regex.test( code ) ) );
controler(
	'géométrie : budget en octets bruts',
	octets <= SEUILS.octets_bruts_max,
	`${ octets } / ${ SEUILS.octets_bruts_max }`
);

const lecture = lireMetadonneesPhp( cheminPhp );

if ( lecture.erreur ) {
	echecs.push( `métadonnées PHP illisibles (PHP_BIN=${ PHP }) : ${ lecture.erreur }` );
} else {
	const meta = lecture.donnees;
	const massifs = meta && meta.massifs ? meta.massifs : {};
	const lignes = Object.values( massifs );

	controler( 'métadonnées : schéma connu', SCHEMA === meta.schema, `schema=${ meta.schema }` );
	controler( 'métadonnées : sha256 de la géométrie', meta.geometrie.sha256 === empreinteGeometrie );
	controler( 'métadonnées : octets de la géométrie', meta.geometrie.octets === octets );
	controler(
		'métadonnées : jeton de version',
		meta.geometrie.version === empreinteGeometrie.slice( 0, 8 ),
		meta.geometrie.version
	);
	controler( 'métadonnées : sha256 de la source archivée', meta.source.archive.sha256 === sha256( sourceBrute ) );
	controler( 'métadonnées : octets de la source archivée', meta.source.archive.octets === sourceBrute.length );
	controler(
		'métadonnées : mention d\'attribution non vide',
		'string' === typeof meta.attribution.phrase && meta.attribution.phrase.length > 0
	);
	controler(
		'métadonnées : un massif par entité géométrique',
		codes.every( ( code ) => Object.prototype.hasOwnProperty.call( massifs, code ) ),
		`${ lignes.length } lignes`
	);
	controler( 'identités : codes conformes à la regex', lignes.every( ( l ) => regex.test( l.code ) ) );
	controler( 'identités : clé du tableau égale au code', Object.entries( massifs ).every( ( [ cle, l ] ) => cle === l.code ) );
	controler( 'identités : libellés non vides', lignes.every( ( l ) => 'string' === typeof l.libelle && '' !== l.libelle.trim() ) );
	controler(
		'identités : note de provenance présente si le libellé diffère du nom source',
		lignes.every( ( l ) => l.libelle === l.source.nom_massif || Boolean( l.note_provenance ) )
	);
	controler( 'identités : gid source uniques', new Set( lignes.map( ( l ) => l.source.gid ) ).size === lignes.length );
	controler(
		'identités : liste pré-triée par `tri`',
		lignes.every( ( l, i ) => 0 === i || lignes[ i - 1 ].tri <= l.tri )
	);
	const correspondance = meta.correspondance_source || {};
	const codesCorrespondance = Object.keys( correspondance );
	const identifiants = Object.values( correspondance );
	const formeIdentifiant = new RegExp( SEUILS.identifiant_prefecture_regex );

	controler(
		'correspondance : une entrée par massif',
		SEUILS.features_attendues === codesCorrespondance.length,
		`${ codesCorrespondance.length } / ${ SEUILS.features_attendues }`
	);
	controler(
		'correspondance : identifiants conformes à la regex',
		identifiants.length > 0 && identifiants.every( ( identifiant ) => formeIdentifiant.test( identifiant ) )
	);
	controler(
		'correspondance : identifiants uniques',
		new Set( identifiants ).size === identifiants.length
	);
	controler(
		'correspondance : bijective avec les codes des lignes',
		codesCorrespondance.length === lignes.length &&
			lignes.every( ( l ) => correspondance[ l.code ] === l.source.identifiant_prefecture )
	);
	controler(
		'correspondance : identifiants en surnombre non rattachés',
		FLUX_PREFECTURE.sans_correspondance.every( ( identifiant ) => ! identifiants.includes( identifiant ) ),
		FLUX_PREFECTURE.sans_correspondance.join( ', ' )
	);
	controler(
		'correspondance : total du flux consigné',
		meta.source.flux_identifiants_total === FLUX_PREFECTURE.identifiants_total,
		`${ meta.source.flux_identifiants_total }`
	);
	controler(
		'correspondance : identifiants en surnombre consignés',
		Array.isArray( meta.source.flux_identifiants_sans_correspondance ) &&
			meta.source.flux_identifiants_sans_correspondance.join( ',' ) ===
				FLUX_PREFECTURE.sans_correspondance.join( ',' )
	);
	controler(
		'emprise : contient toutes les bbox de massifs',
		lignes
			.filter( ( l ) => l.bbox )
			.every(
				( l ) =>
					l.bbox.ouest >= meta.emprise.bbox.ouest &&
					l.bbox.est <= meta.emprise.bbox.est &&
					l.bbox.sud >= meta.emprise.bbox.sud &&
					l.bbox.nord <= meta.emprise.bbox.nord
			)
	);
	controler( 'emprise : zoom maximal', meta.emprise.zoom_max === meta.geometrie.zoom_max, `z${ meta.emprise.zoom_max }` );
}

const metriques = mesurerFidelite( sourceFC, geometrieFC ).global_metrics;

controler(
	'fidélité : écart maximal',
	metriques.max_deviation_m <= SEUILS.ecart_max_m,
	`${ metriques.max_deviation_m } m / ${ SEUILS.ecart_max_m } m`
);
controler(
	'fidélité : écart de surface global',
	Math.abs( metriques.area_delta_pct ) <= SEUILS.ecart_surface_global_abs_pct_max,
	`${ metriques.area_delta_pct } %`
);
controler(
	'fidélité : pire écart de surface par massif',
	metriques.area_delta_abs_worst_pct <= SEUILS.ecart_surface_massif_abs_pct_max,
	`${ metriques.area_delta_abs_worst_pct } % (${ metriques.area_delta_abs_worst_massif })`
);
controler(
	'fidélité : surface des anneaux supprimés',
	metriques.dropped_ring_area_pct_of_total <= SEUILS.surface_anneaux_supprimes_pct_max,
	`${ metriques.dropped_ring_area_pct_of_total } %`
);
controler(
	'recette : écart maximal conforme à massifs-13.fidelite.json',
	Math.abs( metriques.max_deviation_m - fidelite.global_metrics.max_deviation_m ) <= TOLERANCES.ecart_m,
	`mesuré ${ metriques.max_deviation_m } m, consigné ${ fidelite.global_metrics.max_deviation_m } m`
);
controler(
	'recette : écart de surface conforme à massifs-13.fidelite.json',
	Math.abs( metriques.area_delta_pct - fidelite.global_metrics.area_delta_pct ) <= TOLERANCES.surface_pct
);
controler( 'recette : verdict consigné', 'conforme' === fidelite.verdict.statut, fidelite.verdict.statut );
controler(
	'recette : empreinte consignée',
	Boolean( fidelite.empreintes ) &&
		fidelite.empreintes[ path.basename( cheminGeometrie ) ] === empreinteGeometrie
);

process.stdout.write( `${ constats.join( '\n' ) }\n` );

if ( echecs.length > 0 ) {
	process.stderr.write( `\nÉCHEC — ${ echecs.length } contrôle(s) :\n  - ${ echecs.join( '\n  - ' ) }\n` );
	process.exitCode = 1;
} else {
	process.stdout.write( `\nCONFORME — ${ constats.length } contrôles.\n` );
}
