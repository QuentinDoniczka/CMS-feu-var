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
import { CHEMINS_COMMUNES, LOOKUP, SEUILS_COMMUNES, SOURCE_COMMUNES } from './communes.mjs';

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
const cheminExtraitCommunes = CHEMINS_COMMUNES.extrait;
const cheminLookup = CHEMINS_COMMUNES.lookup;

/*
 * Intitulés des contrôles qu'un segment tombé n'aura pas joués.
 *
 * Ces listes ne pilotent rien : elles servent à les NOMMER. Sans elles, l'échec
 * agrégé annoncerait « 1 contrôle » là où huit mesures manquent, et la règle du
 * fichier — aucun contrôle n'est passé en silence — ne tiendrait qu'en apparence.
 *
 * Elles redisent des libellés écrits ailleurs, dans les appels à `controler()` :
 * dette assumée et bornée. La renommer d'un côté sans l'autre ne fausse aucun
 * contrôle, seulement la liste des non-joués.
 */
const CONTROLES_DE_LA_MESURE = [
	'fidélité : écart maximal',
	'fidélité : écart de surface global',
	'fidélité : pire écart de surface par massif',
	'fidélité : surface des anneaux supprimés',
	'recette : écart maximal conforme à massifs-13.fidelite.json',
	'recette : écart de surface conforme à massifs-13.fidelite.json',
	'référence : sommets de la géométrie',
	'référence : écart maximal recalculé',
];

/** Les 29 contrôles que porte le fichier de métadonnées PHP. */
const CONTROLES_DES_METADONNEES = [
	'métadonnées : schéma connu',
	'métadonnées : sha256 de la géométrie',
	'métadonnées : octets de la géométrie',
	'métadonnées : jeton de version',
	'métadonnées : sha256 de la source archivée',
	'métadonnées : octets de la source archivée',
	'métadonnées : mention d\'attribution non vide',
	'métadonnées : un massif par entité géométrique',
	'identités : codes conformes à la regex',
	'identités : clé du tableau égale au code',
	'identités : libellés non vides',
	'identités : note de provenance présente si le libellé diffère du nom source',
	'identités : gid source uniques',
	'identités : liste pré-triée par `tri`',
	'correspondance : une entrée par massif',
	'correspondance : identifiants conformes à la regex',
	'correspondance : identifiants uniques',
	'correspondance : bijective avec les codes des lignes',
	'correspondance : identifiants en surnombre non rattachés',
	'correspondance : total du flux consigné',
	'correspondance : identifiants en surnombre consignés',
	'emprise : contient toutes les bbox de massifs',
	'emprise : zoom maximal',
	'communes : millésime consigné',
	'communes : sha256 de l\'artefact de lookup',
	'communes : octets de l\'artefact de lookup',
	'communes : sha256 de l\'extrait archivé',
	'communes : mention d\'attribution non vide',
	'communes : liste peuplée pour tout massif actif',
];

/**
 * Sans artefact analysable, AUCUN contrôle ultérieur ne peut être joué : la
 * liste vaut donc 68 intitulés. Ils sont annoncés en catégories COMPTÉES, et
 * c'est un choix, pas un raccourci — le compteur ne ment pas (le total est
 * exact), tandis que 68 libellés déroulés noieraient un diagnostic qui tient
 * en un mot : l'artefact nommé juste avant est illisible.
 *
 * Le segment des métadonnées fait l'inverse, et pour la raison inverse : cette
 * panne-là ne se localise pas d'un coup d'œil, ses 29 intitulés sont utiles.
 */
const CONTROLES_DE_LA_LECTURE = [
	'octets (2)',
	'géométrie (7)',
	'métadonnées et identités (29)',
	'fidélité et recette (6)',
	'recette : verdict et empreinte consignés (2)',
	'référence : tailles et empreintes (4)',
	'référence : sommets et écart recalculé (2)',
	'référence : outillage (2)',
	'communes : artefact de lookup (8)',
	'communes : dérive de référence (6)',
];

/** Les 8 contrôles que porte l'artefact de lookup communal. */
const CONTROLES_DU_LOOKUP = [
	'communes : aucune fin de ligne CRLF dans communes-13.lookup.json',
	'communes : type et version de l\'artefact',
	'communes : cardinal annoncé',
	'communes : codes INSEE uniques',
	'communes : Marseille (13055) présente exactement une fois',
	'communes : noms officiels non vides',
	'communes : aucun alias de millésime dans l\'artefact',
	'communes : plafond de distance consigné',
];

/**
 * Analyse un artefact JSON en nommant le fichier fautif.
 *
 * `JSON.parse` ne dit que « Unterminated string in JSON at position 388 » : sur
 * cinq artefacts lus d'affilée, cela n'apprend pas LEQUEL est en cause, et c'est
 * précisément le diagnostic qu'on vient chercher. Le nom du fichier est donc
 * remis devant.
 *
 * @param {string} chemin  Chemin de l'artefact, pour le nommer en cas d'échec.
 * @param {string} contenu Texte à analyser.
 * @return {*} La valeur analysée.
 */
function analyser( chemin, contenu ) {
	try {
		return JSON.parse( contenu );
	} catch ( erreur ) {
		throw new Error( `${ path.basename( chemin ) } illisible : ${ erreur.message }` );
	}
}

/**
 * Joue un segment susceptible de lever, sans laisser sa chute emporter le rapport.
 *
 * Le précédent est le `try` posé plus bas autour de `versionMapshaper()` : non
 * rattrapée, une levée tuait le processus AVANT l'affichage, et les 75 contrôles
 * partaient avec elle. `tenter()` généralise ce traitement aux segments qui
 * peuvent lever pour de bon — un JSON malformé, une re-mesure impossible.
 *
 * Un seul échec est poussé, jamais un par contrôle perdu : la cause est unique,
 * la rapporter huit fois la travestirait en huit dérives. Mais les contrôles non
 * joués sont NOMMÉS, sans quoi le compteur final mentirait par omission.
 *
 * Le `catch` n'est restreint à aucune classe : `Arret` n'est qu'un des modes de
 * levée, un artefact structurellement faux lève un `TypeError` et un artefact
 * malformé une `SyntaxError` — ce sont précisément les cas à rattraper.
 *
 * @param {string}   phase Intitulé de la phase, en tête du message d'échec.
 * @param {Function} fn    Segment à jouer.
 * @param {string[]} avals Contrôles que sa chute rend impossibles.
 * @return {boolean} `true` si le segment est allé au bout.
 */
function tenter( phase, fn, avals ) {
	try {
		fn();

		return true;
	} catch ( erreur ) {
		echecs.push( `${ phase } — ${ erreur.message } ; contrôles non joués : ${ avals.join( ', ' ) }` );

		return false;
	}
}

/**
 * Déroule la recette. Le rapport n'est PAS affiché ici : c'est l'appelant qui
 * s'en charge, pour qu'il le soit quel que soit le sort de cette fonction.
 */
function main() {
	for ( const chemin of [ cheminGeometrie, cheminPhp, cheminFidelite, cheminSource, cheminExtraitCommunes ] ) {
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

	/*
	 * L'artefact de lookup est contrôlé APRÈS la sortie ci-dessus, et son absence
	 * n'interrompt donc pas la recette. C'est la propriété §4.6 rendue vérifiable
	 * plutôt qu'affirmée : les communes par massif sont bakées dans
	 * `data/massifs-13.php` et ne dépendent d'aucun fichier de géométrie. Le
	 * traiter comme fatal ici affirmerait mécaniquement le contraire.
	 */
	const lookupPresent = fs.existsSync( cheminLookup );

	controler(
		`présence de ${ path.basename( cheminLookup ) }`,
		lookupPresent,
		undefined,
		'artefact de lookup manquant : `massifs_commune_de_la_zone()` répondra `artefact_absent` et les ' +
			'communes par massif resteront servies ; `npm run importer` le réémet'
	);

	/*
	 * Les cinq artefacts sont présents — les contrôles ci-dessus l'ont établi —
	 * mais rien ne dit qu'ils sont analysables. Un seul JSON malformé tuait ici le
	 * processus : ni contrôle joué, ni contrôle affiché. Aucun aval ne survit à
	 * cette chute, d'où le retour anticipé ; le rapport, lui, sort quand même.
	 */
	let geometrieBrute;
	let geometrieFC;
	let phpBrut;
	let sourceBrute;
	let sourceFC;
	let fidelite;
	let reference;
	let extraitCommunesBrut;

	const artefactsLus = tenter(
		'lecture des artefacts',
		() => {
			geometrieBrute = fs.readFileSync( cheminGeometrie );
			geometrieFC = analyser( cheminGeometrie, geometrieBrute.toString( 'utf8' ) );
			phpBrut = fs.readFileSync( cheminPhp );
			sourceBrute = fs.readFileSync( cheminSource );
			sourceFC = analyser( cheminSource, sourceBrute.toString( 'utf8' ) );
			fidelite = analyser( cheminFidelite, fs.readFileSync( cheminFidelite, 'utf8' ) );
			reference = analyser( cheminReference, fs.readFileSync( cheminReference, 'utf8' ) );
			extraitCommunesBrut = fs.readFileSync( cheminExtraitCommunes );
		},
		CONTROLES_DE_LA_LECTURE
	);

	if ( ! artefactsLus ) {
		return;
	}

	const empreinteGeometrie = sha256( geometrieBrute );
	const octets = geometrieBrute.length;
	const regex = new RegExp( SEUILS.code_regex );

	/*
	 * `features` ramené à un tableau une fois pour toutes. Sans cette garde, une
	 * géométrie structurellement fausse levait DÈS la construction des codes,
	 * c'est-à-dire AVANT les contrôles « FeatureCollection » et « properties
	 * limitées à `code` » qui sont précisément là pour la démasquer. Sur un
	 * tableau vide ces contrôles échouent bruyamment — ce qui vaut infiniment
	 * mieux qu'un script mort.
	 */
	const entites = Array.isArray( geometrieFC.features ) ? geometrieFC.features : [];
	const codes = entites.map( ( f ) => f?.properties?.code );

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
		SEUILS.features_attendues === entites.length,
		`${ entites.length }`
	);
	/*
	 * `entites.length > 0 &&` n'est pas une précaution de style : `[].every()`
	 * vaut `true`. Sans lui, une géométrie vidée de ses entités passerait ces
	 * deux contrôles AU VERT. Un faux vert est pire que l'arrêt qu'on répare.
	 */
	controler(
		'géométrie : properties limitées à `code`',
		entites.length > 0 &&
			entites.every(
				( f ) => f.properties && 1 === Object.keys( f.properties ).length && 'string' === typeof f.properties.code
			)
	);
	controler(
		'géométrie : aucune géométrie nulle',
		entites.length > 0 && entites.every( ( f ) => f.geometry && f.geometry.coordinates )
	);
	controler( 'géométrie : codes uniques', new Set( codes ).size === codes.length );
	controler( 'géométrie : codes conformes à la regex', codes.every( ( code ) => regex.test( code ) ) );
	controler(
		'géométrie : budget en octets bruts',
		octets <= SEUILS.octets_bruts_max,
		`${ octets } / ${ SEUILS.octets_bruts_max }`
	);

	/* -------------------------------------------------------------------------- */
	/* Référentiel communal                                                        */
	/* -------------------------------------------------------------------------- */

	let lookupBrut = null;
	let lookup = null;
	let empreinteLookup = '';

	if ( lookupPresent ) {
		tenter(
			'communes : lecture de l\'artefact de lookup',
			() => {
				lookupBrut = fs.readFileSync( cheminLookup );
				lookup = analyser( cheminLookup, lookupBrut.toString( 'utf8' ) );
				empreinteLookup = sha256( lookupBrut );
			},
			CONTROLES_DU_LOOKUP
		);
	}

	if ( lookup ) {
		const insee = ( lookup.communes || [] ).map( ( commune ) => commune.insee );

		controler(
			'communes : aucune fin de ligne CRLF dans ' + path.basename( cheminLookup ),
			! lookupBrut.includes( 0x0d ),
			undefined,
			'votre clone a converti les fins de ligne, les empreintes ne peuvent plus correspondre ; ' +
				'vérifier `.gitattributes` et `git check-attr -a` sur ce fichier (attendu : `text: unset`)'
		);
		controler( 'communes : type et version de l\'artefact', LOOKUP.type === lookup.type && LOOKUP.version === lookup.version );
		controler( 'communes : cardinal annoncé', insee.length === lookup.nombre, `${ insee.length }` );
		controler( 'communes : codes INSEE uniques', new Set( insee ).size === insee.length );
		controler(
			'communes : Marseille (13055) présente exactement une fois',
			1 === insee.filter( ( code ) => '13055' === code ).length,
			undefined,
			'la couche COG CARTO peut porter des arrondissements municipaux : leur apparition doit rougir'
		);
		controler(
			'communes : noms officiels non vides',
			( lookup.communes || [] ).every( ( commune ) => 'string' === typeof commune.nom && '' !== commune.nom.trim() )
		);
		/*
		 * L'alias mouvant ne se lit dans AUCUN artefact (§2.1). Le contrôle porte sur
		 * les OCTETS du fichier et non sur une clé : c'est la seule forme qui attrape
		 * aussi un alias glissé dans une phrase de provenance.
		 */
		controler(
			'communes : aucun alias de millésime dans l\'artefact',
			! lookupBrut.toString( 'utf8' ).includes( 'LATEST' ),
			`millésime ${ lookup.millesime }`
		);
		controler(
			'communes : plafond de distance consigné',
			SEUILS_COMMUNES.plafond_m === lookup.plafond_m,
			`${ lookup.plafond_m } m`
		);
	}

	const lecture = lireMetadonneesPhp( cheminPhp );

	if ( lecture.erreur ) {
		echecs.push(
			`métadonnées PHP illisibles (PHP_BIN=${ PHP_INVOCATION.join( ' ' ) }` +
				`${ '' === PHP_RACINE ? '' : `, MASSIFS_PHP_RACINE=${ PHP_RACINE }` }) : ${ lecture.erreur }`
		);
	} else {
		/*
		 * Les métadonnées sont analysables, mais leur STRUCTURE n'est pas garantie :
		 * un bloc absent lève un `TypeError` sur la chaîne de propriétés qui le lit.
		 * Le segment est donc joué sous `tenter()`, qui nomme les 29 contrôles perdus
		 * plutôt que de laisser le compteur en annoncer un seul.
		 */
		tenter(
			'métadonnées PHP',
			() => {
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
				controler(
					'métadonnées : sha256 de la source archivée',
					meta.source.archive.sha256 === sha256( sourceBrute )
				);
				controler(
					'métadonnées : octets de la source archivée',
					meta.source.archive.octets === sourceBrute.length
				);
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
				controler(
					'identités : clé du tableau égale au code',
					Object.entries( massifs ).every( ( [ cle, l ] ) => cle === l.code )
				);
				controler(
					'identités : libellés non vides',
					lignes.every( ( l ) => 'string' === typeof l.libelle && '' !== l.libelle.trim() )
				);
				controler(
					'identités : note de provenance présente si le libellé diffère du nom source',
					lignes.every( ( l ) => l.libelle === l.source.nom_massif || Boolean( l.note_provenance ) )
				);
				controler(
					'identités : gid source uniques',
					new Set( lignes.map( ( l ) => l.source.gid ) ).size === lignes.length
				);
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
					identifiants.length > 0 &&
						identifiants.every( ( identifiant ) => formeIdentifiant.test( identifiant ) )
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
					FLUX_PREFECTURE.sans_correspondance.every(
						( identifiant ) => ! identifiants.includes( identifiant )
					),
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
				/*
				 * Le seul contrôle du fichier qui compare deux valeurs LUES. Deux blocs
				 * absents donneraient `undefined === undefined`, c'est-à-dire un contrôle
				 * VERT sur des métadonnées cassées — un faux vert, pire que l'arrêt qu'on
				 * répare. On exige donc que la valeur existe avant de la comparer. La
				 * garde va ici et nulle part ailleurs : partout ailleurs, l'absence fait
				 * déjà rougir.
				 */
				controler(
					'emprise : zoom maximal',
					undefined !== meta.emprise?.zoom_max &&
						meta.emprise.zoom_max === meta.geometrie?.zoom_max,
					`z${ meta.emprise?.zoom_max }`
				);
				controler(
					'communes : millésime consigné',
					SOURCE_COMMUNES.millesime === meta.communes?.millesime,
					`${ meta.communes?.millesime }`
				);
				/*
				 * Les deux contrôles d'empreinte du lookup ne sont joués QUE si
				 * l'artefact est là. Les faire rougir en son absence ferait passer une
				 * panne indépendante et voulue (§4.6) pour une dérive du référentiel.
				 */
				controler(
					'communes : sha256 de l\'artefact de lookup',
					! lookupPresent || meta.communes?.lookup?.sha256 === empreinteLookup,
					lookupPresent ? undefined : 'non joué : artefact absent'
				);
				controler(
					'communes : octets de l\'artefact de lookup',
					! lookupPresent || meta.communes?.lookup?.octets === lookupBrut.length,
					lookupPresent ? `${ lookupBrut.length }` : 'non joué : artefact absent'
				);
				controler(
					'communes : sha256 de l\'extrait archivé',
					meta.communes?.archive?.sha256 === sha256( extraitCommunesBrut )
				);
				controler(
					'communes : mention d\'attribution non vide',
					'string' === typeof meta.communes?.attribution?.phrase && meta.communes.attribution.phrase.length > 0
				);
				/*
				 * LE contrôle qui prouve §4.6 : les communes par massif sont servies
				 * depuis le fichier de métadonnées, sans que l'artefact de lookup soit
				 * seulement ouvert. Il reste vert artefact supprimé.
				 */
				controler(
					'communes : liste peuplée pour tout massif actif',
					lignes
						.filter( ( l ) => l.actif )
						.every( ( l ) => Array.isArray( l.communes ) && l.communes.length > 0 ),
					`${ lignes.filter( ( l ) => l.actif && l.communes.length > 0 ).length } massifs actifs peuplés`
				);
			},
			CONTROLES_DES_METADONNEES
		);
	}

	/*
	 * La re-mesure est le cœur de la recette, et elle lève pour de bon : massif
	 * absent de la géométrie simplifiée, géométrie non surfacique. Non rattrapée,
	 * elle emportait le rapport ENTIER — les 75 contrôles, puisque rien n'était
	 * encore affiché. C'est le défaut que corrige cette passe : seuls les 8
	 * contrôles qui consomment la mesure tombent désormais, les 67 autres sont
	 * joués et imprimés.
	 */
	let metriques;

	const fideliteMesuree = tenter(
		'fidélité : re-mesure depuis la source archivée',
		() => {
			metriques = mesurerFidelite( sourceFC, geometrieFC ).global_metrics;
		},
		CONTROLES_DE_LA_MESURE
	);

	/*
	 * Seuls les contrôles qui CONSOMMENT `metriques` sont suspendus à la re-mesure.
	 * Les autres — verdict consigné, empreinte consignée, dérive des tailles et des
	 * empreintes, outillage — n'en ont aucun besoin et continuent de tourner : une
	 * re-mesure impossible ne doit pas effacer le diagnostic que, justement, eux
	 * portent.
	 */
	if ( fideliteMesuree ) {
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
			Math.abs( metriques.max_deviation_m - fidelite.global_metrics.max_deviation_m ) <=
				TOLERANCES.ecart_m,
			`mesuré ${ metriques.max_deviation_m } m, consigné ${ fidelite.global_metrics.max_deviation_m } m`
		);
		controler(
			'recette : écart de surface conforme à massifs-13.fidelite.json',
			Math.abs( metriques.area_delta_pct - fidelite.global_metrics.area_delta_pct ) <=
				TOLERANCES.surface_pct
		);
	}

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
	if ( fideliteMesuree ) {
		controlerDerive( 'référence : sommets de la géométrie', reference.geometrie.sommets, metriques.out_vertices );
	}

	controlerDerive( 'référence : sha256 de la source archivée', reference.source.sha256, sha256( sourceBrute ) );
	controlerDerive( 'référence : octets de la source archivée', reference.source.octets, sourceBrute.length );

	/*
	 * Dérive du référentiel communal. Le millésime est en tête : un changement de
	 * millésime change des NOMS DE COMMUNES sans changer une ligne de code, et
	 * c'est exactement la dérive silencieuse que le §2.1 du contrat #45 refuse.
	 */
	controlerDerive( 'référence : millésime communal', reference.communes.millesime, SOURCE_COMMUNES.millesime );
	controlerDerive( 'référence : sha256 de l\'extrait communal', reference.communes.extrait.sha256, sha256( extraitCommunesBrut ) );
	controlerDerive( 'référence : octets de l\'extrait communal', reference.communes.extrait.octets, extraitCommunesBrut.length );

	if ( lookupPresent && lookup ) {
		controlerDerive( 'référence : sha256 du lookup communal', reference.communes.lookup.sha256, empreinteLookup );
		controlerDerive( 'référence : octets du lookup communal', reference.communes.lookup.octets, lookupBrut.length );
		controlerDerive( 'référence : communes du lookup', reference.communes.lookup.communes, lookup.nombre );
	} else {
		avertissements.push(
			'artefact de lookup absent : les trois contrôles de dérive qui le concernent ne sont pas joués. ' +
				'Les communes par massif, elles, restent servies — c\'est la panne indépendante du §4.6.'
		);
	}

	/*
	 * Écart recalculé alors que les octets sont identiques : ce n'est pas une dérive
	 * de données mais un comportement flottant de l'environnement (arithmétique,
	 * ordre d'itération). Il mérite un message distinct, parce que le remède est
	 * différent — chercher ce qui a changé dans la machine, pas dans la géométrie.
	 */
	if ( fideliteMesuree ) {
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
}

/**
 * Affiche le rapport accumulé : constats, avertissements, échecs.
 *
 * Extraite pour être appelable depuis un `finally` : les constats déjà obtenus
 * valent d'être lus même quand la recette n'est pas allée au bout.
 */
function afficherRapport() {
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
}

/*
 * Filet de dernier recours. `tenter()` couvre les segments dont on SAIT qu'ils
 * lèvent ; ce `finally` couvre tout le reste — y compris ce qu'aucune relecture
 * n'a prévu. Le rapport est affiché quoi qu'il arrive, et l'échec inattendu est
 * consigné comme un contrôle en échec plutôt que craché en trace de pile.
 *
 * `process.exitCode`, jamais `process.exit()` : ce dernier n'attend pas le vidage
 * des flux et tronquerait le rapport, c'est-à-dire exactement le défaut réparé ici.
 */
try {
	main();
} catch ( erreur ) {
	echecs.push( `interruption inattendue de la recette — ${ erreur.message }` );
} finally {
	afficherRapport();
}
