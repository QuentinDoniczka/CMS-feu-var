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
 * Deux régimes de contrôle, qui mesurent deux choses différentes et coexistent :
 *
 *   - les SEUILS (écart max, budget d'octets, écarts de surface) disent que la
 *     géométrie est acceptable, quelle qu'elle soit ;
 *   - `reference.json` dit qu'elle est la MÊME qu'au dernier import assumé. Un
 *     artefact peut tenir tous les seuils et avoir néanmoins changé sans que
 *     personne l'ait décidé.
 *
 * Le fichier de métadonnées PHP est lu par `php -r`, ce que permet sa garde
 * volontairement dépourvue d'`exit`. Sans binaire PHP, les contrôles qui en
 * dépendent ne sont pas silencieusement passés : la sortie est en échec.
 *
 * Aucun PHP sur la machine hôte n'est pourtant un cas courant (Windows, poste
 * sans stack locale) : `PHP_BIN` accepte donc des ARGUMENTS, et
 * `MASSIFS_PHP_RACINE` réécrit le préfixe du chemin passé à PHP. Les deux
 * ensemble permettent de jouer la recette contre un PHP conteneurisé, qui ne
 * voit pas l'arborescence de l'hôte :
 *
 *   PHP_BIN="docker compose run --rm -T wpcli php" \
 *   MASSIFS_PHP_RACINE=/var/www/html/wp-content/plugins/massifs-core \
 *   npm run verifier
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
	mesurerFidelite,
	nodeMajeur,
	sha256,
	versionMapshaper,
	CHEMINS,
	EXTENSION,
	SEUILS,
	SCHEMA,
	FLUX_PREFECTURE,
} from './importer.mjs';

/*
 * `PHP_BIN` peut porter des arguments : le premier jeton est l'exécutable, les
 * suivants précèdent `-r`. C'est ce qui rend « docker compose run … php »
 * utilisable tel quel, sans wrapper à écrire ni PHP à installer sur l'hôte.
 */
const PHP_INVOCATION = ( process.env.PHP_BIN || 'php' ).trim().split( /\s+/ );
const PHP = PHP_INVOCATION[ 0 ];
const PHP_ARGUMENTS = PHP_INVOCATION.slice( 1 );

/*
 * Racine de l'extension telle que la voit le PHP invoqué. Un PHP conteneurisé
 * ne connaît pas l'arborescence de l'hôte : sans cette réécriture il échouerait
 * sur « fichier introuvable », ce qui se lirait comme une dérive du référentiel
 * alors que ce n'est qu'un chemin.
 */
const PHP_RACINE = ( process.env.MASSIFS_PHP_RACINE || '' ).replace( /\/+$/, '' );

/** Tolérances de re-mesure : la source archivée est arrondie à 5 décimales (~1,1 m). */
const TOLERANCES = {
	ecart_m: 2,
	surface_pct: 0.05,
};

/**
 * Phrase close à chaque échec de dérive.
 *
 * « ET `reference.json` » n'est pas un détail : régénérer les artefacts sans
 * régénérer l'empreinte de référence laisserait la recette rouge en permanence,
 * et une recette durablement rouge finit par être ignorée.
 */
const REGENERER =
	'si ce changement est voulu, régénérer les artefacts ET `reference.json` par `npm run importer`, dans le même commit';

const NODE_MAJEUR = nodeMajeur();

const echecs = [];
const constats = [];
const avertissements = [];

/**
 * @param {string}  nom         Intitulé du contrôle.
 * @param {boolean} condition   Résultat.
 * @param {string}  [detail]    Mesure à afficher dans les deux cas.
 * @param {string}  [remede]    Consigne, affichée en échec SEULEMENT : lue sur une
 *                              ligne verte, une consigne de réparation se lit comme
 *                              un problème et brouille la sortie.
 */
function controler( nom, condition, detail, remede ) {
	if ( condition ) {
		constats.push( `  ok   ${ nom }${ detail ? ` — ${ detail }` : '' }` );
		return;
	}

	const complement = [ detail, remede ].filter( Boolean ).join( ' — ' );

	echecs.push( `${ nom }${ complement ? ` — ${ complement }` : '' }` );
}

/** Contrôle de dérive : en échec, la consigne de reprise est toujours rappelée. */
function controlerDerive( nom, attendu, mesure, detail ) {
	const complement = detail ? ` (${ detail })` : '';

	if ( attendu === mesure ) {
		constats.push( `  ok   ${ nom } — ${ attendu }${ complement }` );
		return;
	}

	echecs.push( `${ nom }${ complement } — référence ${ attendu }, mesuré ${ mesure } ; ${ REGENERER }` );
}

/** Traduit un chemin de l'hôte vers l'arborescence que voit le PHP invoqué. */
function cheminPourPhp( chemin ) {
	const glisse = chemin.replace( /\\/g, '/' );

	if ( '' === PHP_RACINE ) {
		return glisse;
	}

	const racineHote = EXTENSION.replace( /\\/g, '/' );

	if ( ! glisse.startsWith( racineHote ) ) {
		return glisse;
	}

	return `${ PHP_RACINE }${ glisse.slice( racineHote.length ) }`;
}

function lireMetadonneesPhp( chemin ) {
	const script = `define('MASSIFS_VERIFICATION', true); echo json_encode(require '${ cheminPourPhp( chemin ) }');`;
	const execution = spawnSync( PHP, [ ...PHP_ARGUMENTS, '-r', script ], { encoding: 'utf8' } );

	if ( execution.error || 0 !== execution.status ) {
		return { erreur: execution.error ? execution.error.message : execution.stderr.trim() };
	}

	try {
		return { donnees: JSON.parse( execution.stdout ) };
	} catch ( erreur ) {
		return { erreur: `sortie PHP illisible : ${ erreur.message }` };
	}
}

/*
 * Chemins importés de `importer.mjs`, jamais recomposés ici : une seconde liste
 * finirait par désigner un autre fichier que celui qu'écrit l'import, et une
 * recette verte sur le mauvais fichier est pire que pas de recette.
 */
const cheminGeometrie = CHEMINS.geometrie;
const cheminPhp = CHEMINS.metadonnees;
const cheminFidelite = CHEMINS.fidelite;
const cheminReference = CHEMINS.reference;
const cheminSource = CHEMINS.source;

for ( const chemin of [ cheminGeometrie, cheminPhp, cheminFidelite, cheminSource ] ) {
	controler( `présence de ${ path.basename( chemin ) }`, fs.existsSync( chemin ) );
}

// Empreinte de référence absente = contrôle de dérive impossible. Jamais passé
// en silence : ce serait perdre la moitié de la recette sans que rien ne rougisse.
controler(
	'présence de reference.json',
	fs.existsSync( cheminReference ),
	undefined,
	'reference.json manquant : lancer `npm run importer`, qui l\'émet en même temps que les artefacts'
);

if ( echecs.length > 0 ) {
	process.stderr.write( `Artefacts manquants :\n  - ${ echecs.join( '\n  - ' ) }\n` );
	process.exit( 1 );
}

const geometrieBrute = fs.readFileSync( cheminGeometrie );
const geometrieFC = JSON.parse( geometrieBrute.toString( 'utf8' ) );
const phpBrut = fs.readFileSync( cheminPhp );
const sourceBrute = fs.readFileSync( cheminSource );
const sourceFC = JSON.parse( sourceBrute.toString( 'utf8' ) );
const fidelite = JSON.parse( fs.readFileSync( cheminFidelite, 'utf8' ) );
const reference = JSON.parse( fs.readFileSync( cheminReference, 'utf8' ) );
const empreinteGeometrie = sha256( geometrieBrute );
const octets = geometrieBrute.length;
const regex = new RegExp( SEUILS.code_regex );
const codes = geometrieFC.features.map( ( f ) => f.properties.code );

/*
 * Détection directe d'une conversion de fins de ligne, indépendante de git et de
 * sa configuration : on regarde les OCTETS, pas ce que git prétend en faire.
 * Sans ce contrôle, un clone sous `core.autocrlf=true` fait échouer trois
 * contrôles d'empreinte sans jamais nommer la cause — une heure de recherche
 * pour un `.gitattributes`.
 */
for ( const [ chemin, contenu ] of [
	[ cheminGeometrie, geometrieBrute ],
	[ cheminPhp, phpBrut ],
] ) {
	controler(
		`octets : aucune fin de ligne CRLF dans ${ path.basename( chemin ) }`,
		! contenu.includes( 0x0d ),
		undefined,
		'votre clone a converti les fins de ligne, les empreintes ne peuvent plus correspondre ; ' +
			'vérifier `.gitattributes` et `git check-attr -a` sur ce fichier (attendu : `text: unset`)'
	);
}

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
	echecs.push(
		`métadonnées PHP illisibles (PHP_BIN=${ PHP_INVOCATION.join( ' ' ) }` +
			`${ '' === PHP_RACINE ? '' : `, MASSIFS_PHP_RACINE=${ PHP_RACINE }` }) : ${ lecture.erreur }`
	);
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

/* -------------------------------------------------------------------------- */
/* Dérive par rapport à reference.json                                         */
/* -------------------------------------------------------------------------- */

controlerDerive( 'référence : sha256 de la géométrie', reference.geometrie.sha256, empreinteGeometrie );
controlerDerive( 'référence : octets de la géométrie', reference.geometrie.octets, octets );
controlerDerive( 'référence : sommets de la géométrie', reference.geometrie.sommets, metriques.out_vertices );
controlerDerive( 'référence : sha256 de la source archivée', reference.source.sha256, sha256( sourceBrute ) );
controlerDerive( 'référence : octets de la source archivée', reference.source.octets, sourceBrute.length );

/*
 * Écart recalculé alors que les octets sont identiques : ce n'est pas une dérive
 * de données mais un comportement flottant de l'environnement (arithmétique,
 * ordre d'itération). Il mérite un message distinct, parce que le remède est
 * différent — chercher ce qui a changé dans la machine, pas dans la géométrie.
 */
if ( reference.geometrie.ecart_max_m === metriques.max_deviation_m ) {
	constats.push( `  ok   référence : écart maximal recalculé — ${ metriques.max_deviation_m } m` );
} else if ( reference.geometrie.sha256 === empreinteGeometrie ) {
	echecs.push(
		`référence : écart maximal recalculé — empreintes identiques mais écart recalculé différent → ` +
			`comportement flottant de l'environnement ; référence ${ reference.geometrie.ecart_max_m } m, ` +
			`mesuré ${ metriques.max_deviation_m } m ; ${ REGENERER }`
	);
} else {
	echecs.push(
		`référence : écart maximal recalculé — référence ${ reference.geometrie.ecart_max_m } m, ` +
			`mesuré ${ metriques.max_deviation_m } m ; ${ REGENERER }`
	);
}

/*
 * `versionMapshaper()` lève si `node_modules/` est absent. Non rattrapée, elle
 * tuait le processus AVANT l'affichage du rapport : ni les contrôles verts, ni
 * les échecs, juste une trace de pile. Or un développeur qui vient de cloner et
 * a oublié `npm ci` est exactement le public de la procédure d'installation
 * (§11) — il mérite le même bloc `ÉCHEC` que n'importe quel autre prérequis
 * manquant. Le code de sortie reste ≠ 0 : aucun contrôle n'est passé en silence.
 */
try {
	controlerDerive( 'référence : version de mapshaper', reference.outillage.mapshaper, versionMapshaper() );
} catch ( erreur ) {
	echecs.push(
		`référence : version de mapshaper — ${ erreur.message } ` +
			'Sans lui, ni la version d\'outillage ni la reproductibilité de la géométrie ne sont contrôlables.'
	);
}

/*
 * Node majeur : AVERTISSEMENT, jamais un échec. Refuser une recette verte sur
 * Node 26 au seul motif que la référence dit 24 est un faux positif ; et un faux
 * positif répété apprend à régénérer `reference.json` par réflexe, ce qui détruit
 * exactement le mécanisme qu'on installe ici.
 */
if ( reference.outillage.node_major !== NODE_MAJEUR ) {
	avertissements.push(
		`node majeur ${ NODE_MAJEUR } alors que reference.json a été produit sous ${ reference.outillage.node_major } ` +
			'(non bloquant ; voir `.nvmrc` si un écart de mesure apparaît)'
	);
} else {
	constats.push( `  ok   référence : node majeur — ${ NODE_MAJEUR }` );
}

process.stdout.write( `${ constats.join( '\n' ) }\n` );

if ( avertissements.length > 0 ) {
	process.stdout.write( `\nAVERTISSEMENT(S) :\n  - ${ avertissements.join( '\n  - ' ) }\n` );
}

if ( echecs.length > 0 ) {
	// Les avertissements sont répétés ici : un écart de version d'outillage est
	// souvent le contexte qui explique l'échec, et il serait perdu s'il ne
	// figurait que dans la sortie standard.
	const contexte =
		avertissements.length > 0 ? `\nContexte :\n  - ${ avertissements.join( '\n  - ' ) }\n` : '';

	process.stderr.write(
		`\nÉCHEC — ${ echecs.length } contrôle(s) :\n  - ${ echecs.join( '\n  - ' ) }\n${ contexte }`
	);
	process.exitCode = 1;
} else {
	process.stdout.write( `\nCONFORME — ${ constats.length } contrôles.\n` );
}
