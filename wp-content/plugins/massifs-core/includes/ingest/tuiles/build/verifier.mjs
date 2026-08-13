/**
 * Recette du fond de carte : relit les artefacts émis et rejoue tous les
 * contrôles, sans rien réécrire.
 *
 *   node verifier.mjs      (ou : npm run verifier)
 *
 * C'est ce fichier qui rend les invariants du contrat #9 VÉRIFIABLES plutôt
 * qu'affirmés. Deux régimes de contrôle, qui mesurent deux choses différentes et
 * coexistent :
 *
 *   - les CONTRÔLES DE FOND (mode, jetons, dénombrements recalculés, palette,
 *     dimensions, absence des aplats de statut) disent que l'artefact est
 *     acceptable, quel qu'il soit ;
 *   - `reference.json` dit qu'il est le MÊME qu'au dernier build assumé. Un
 *     artefact peut tenir tous les contrôles et avoir néanmoins changé sans que
 *     personne l'ait décidé.
 *
 * Un artefact en mode `degrade` fait ÉCHOUER la recette. C'est le sens de
 * l'invariant I-9.9 : le mode dégradé est constructible en local sans réseau,
 * jamais commitable en silence.
 *
 * Le fichier de métadonnées PHP est lu par `php -r`, ce que permet sa garde
 * volontairement dépourvue d'`exit`. Sans binaire PHP, les contrôles qui en
 * dépendent NE SONT PAS silencieusement passés : la sortie est en échec
 * (interdit 4 du contrat #20).
 *
 * Aucun PHP sur la machine hôte est pourtant un cas courant (Windows, poste sans
 * stack locale) : `PHP_BIN` accepte donc des ARGUMENTS, et `MASSIFS_PHP_RACINE`
 * réécrit le préfixe du chemin passé à PHP. Les deux ensemble permettent de jouer
 * la recette contre un PHP conteneurisé, qui ne voit pas l'arborescence de l'hôte :
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
import sharp from 'sharp';
import {
	ATTRIBUTION,
	CHEMINS,
	EXTENSION,
	FORMAT,
	JETONS_STATUT,
	JETON_CONTOUR,
	JETONS_CARTE,
	LARGEUR_STATIQUE,
	MODE_COMPLET,
	PLAFOND_STATIQUE_OCTETS,
	TAILLE_TUILE,
	ZOOM_MAX,
	ZOOM_MIN,
	bboxDeGrille,
	divergencesJetons,
	grilles,
	jetonVersion,
	jsonCanonique,
	lireEmprise,
	lireJetons,
	nodeMajeur,
	sha256,
	versHexadecimal,
	versRgb,
	versionMapshaper,
	paletteAutorisee,
} from './commun.mjs';
import { CHUNKS_ATTENDUS, chunksPng } from './png8.mjs';

/*
 * `PHP_BIN` peut porter des arguments : le premier jeton est l'exécutable, les
 * suivants précèdent `-r`. C'est ce qui rend « docker compose run … php »
 * utilisable tel quel, sans wrapper à écrire ni PHP à installer sur l'hôte.
 */
const PHP_INVOCATION = ( process.env.PHP_BIN || 'php' ).trim().split( /\s+/ );
const PHP = PHP_INVOCATION[ 0 ];
const PHP_ARGUMENTS = PHP_INVOCATION.slice( 1 );

/** Racine de l'extension telle que la voit le PHP invoqué. */
const PHP_RACINE = ( process.env.MASSIFS_PHP_RACINE || '' ).replace( /\/+$/, '' );

/**
 * Phrase close à chaque échec de dérive.
 *
 * « ET `reference.json` » n'est pas un détail : régénérer les artefacts sans
 * régénérer l'empreinte de référence laisserait la recette rouge en permanence,
 * et une recette durablement rouge finit par être ignorée.
 */
const REGENERER =
	'si ce changement est voulu, régénérer les artefacts ET `reference.json` par `npm run construire`, dans le même commit';

/**
 * Clés du contrat, telles que l'avenant du 14 août 2026 (§13) les gèle.
 *
 * Elles sont recopiées ici EXPRÈS, et non dérivées de ce que rend le module : une
 * liste dérivée de l'implémentation validerait n'importe quel renommage. C'est le
 * seul endroit du build où une redite est le contrôle lui-même.
 *
 * La comparaison passe par `memesCles()`, qui trie les deux côtés : l'ordre de
 * déclaration d'un tableau PHP n'est pas une clause de contrat, et faire échouer
 * la recette dessus serait un faux positif.
 */
const CLES_FOND = [
	'attribution',
	'attribution_url',
	'bbox',
	'disponible',
	'format',
	'format_tuile',
	'mode',
	'nombre',
	'octets',
	'sha256',
	'taille_tuile',
	'type',
	'url_modele',
	'version',
	'zoom_max',
	'zoom_min',
];

const CLES_STATIQUE = [ 'contours_massifs', 'disponible', 'hauteur', 'largeur', 'octets', 'porte_les_statuts', 'sha256', 'version' ];

const CLES_FAITS = [ 'canal', 'canal_url', 'extrait_le', 'licence_nom', 'licence_version', 'licence_url', 'rendu' ];

/** Deux jeux de clés portent-ils exactement les mêmes noms ? */
function memesCles( mesurees, attendues ) {
	return jsonCanonique( [ ...mesurees ].sort() ) === jsonCanonique( [ ...attendues ].sort() );
}

const echecs = [];
const constats = [];
const avertissements = [];

/**
 * @param {string}  nom       Intitulé du contrôle.
 * @param {boolean} condition Résultat.
 * @param {string}  [detail]  Mesure à afficher dans les deux cas.
 * @param {string}  [remede]  Consigne, affichée EN ÉCHEC SEULEMENT : lue sur une
 *                            ligne verte, une consigne de réparation se lit comme
 *                            un problème et brouille la sortie.
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
function controlerDerive( nom, attendu, mesure ) {
	if ( attendu === mesure ) {
		constats.push( `  ok   ${ nom } — ${ attendu }` );
		return;
	}

	echecs.push( `${ nom } — référence ${ attendu }, mesuré ${ mesure } ; ${ REGENERER }` );
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

function executerPhp( script ) {
	const execution = spawnSync( PHP, [ ...PHP_ARGUMENTS, '-r', script ], { encoding: 'utf8', maxBuffer: 1024 * 1024 * 64 } );

	if ( execution.error || 0 !== execution.status ) {
		return { erreur: execution.error ? execution.error.message : execution.stderr.trim() || `code de sortie ${ execution.status }` };
	}

	try {
		return { donnees: JSON.parse( execution.stdout ) };
	} catch ( erreur ) {
		return { erreur: `sortie PHP illisible : ${ erreur.message }` };
	}
}

/** Lit un fichier de métadonnées généré, par PHP, sans amorcer WordPress. */
function lirePhp( chemin ) {
	return executerPhp( `define('MASSIFS_VERIFICATION', true); echo json_encode(require '${ cheminPourPhp( chemin ) }');` );
}

/**
 * Charge le module de lecture et rend ce que les trois fonctions publiques
 * renvoient réellement.
 *
 * Contrôler le fichier de métadonnées ne suffit pas : ce que la chaîne #7
 * consomme, ce sont les CLÉS DU CONTRAT, et un renommage de clé dans le module
 * casserait la carte sans toucher un seul octet d'artefact. On mesure donc la
 * surface publique elle-même.
 *
 * `plugins_url()` est bouchonnée par un CHEMIN, jamais par une URL : aucune URL
 * étrangère n'a à exister dans ce dépôt, pas même dans un script de recette
 * (invariant I-9.2, contrôlé par `grep` en revue).
 */
function lireSurfacePhp() {
	const module = cheminPourPhp( CHEMINS.module );
	const script =
		"define('ABSPATH', '/');" +
		"if ( ! function_exists('plugins_url') ) { function plugins_url( $chemin = '', $extension = '' ) { return '/recette/' . $chemin; } }" +
		`require '${ module }';` +
		"echo json_encode( array( 'fond' => massifs_fond_de_carte(), 'statique' => massifs_fond_de_carte_statique(), 'attribution' => massifs_attribution_fond_de_carte() ) );";

	return executerPhp( script );
}

/* -------------------------------------------------------------------------- */
/* Présence des artefacts                                                      */
/* -------------------------------------------------------------------------- */

const cheminHtaccess = path.join( CHEMINS.tuiles, '.htaccess' );

/*
 * Deux groupes, et l'ordre compte. Ici, uniquement ce que la recette doit LIRE
 * pour exister : sans ces cinq fichiers, il n'y a rien à contrôler et on s'arrête.
 * L'archive de source, elle, n'est jamais lue par la recette — elle est contrôlée
 * plus bas, comme n'importe quel constat, pour que le VRAI motif d'un artefact
 * dégradé (`mode`) soit énoncé avant sa cause matérielle.
 */
for ( const chemin of [ CHEMINS.manifeste, CHEMINS.metadonnees, CHEMINS.statique, cheminHtaccess ] ) {
	controler( `présence de ${ path.basename( chemin ) }`, fs.existsSync( chemin ) );
}

controler(
	'présence de reference.json',
	fs.existsSync( CHEMINS.reference ),
	undefined,
	'reference.json manquant : lancer `npm run construire`, qui l\'émet en même temps que les artefacts'
);

if ( echecs.length > 0 ) {
	process.stderr.write( `Artefacts manquants :\n  - ${ echecs.join( '\n  - ' ) }\n` );
	process.exit( 1 );
}

const manifeste = JSON.parse( fs.readFileSync( CHEMINS.manifeste, 'utf8' ) );
const reference = JSON.parse( fs.readFileSync( CHEMINS.reference, 'utf8' ) );
const statiqueBrut = fs.readFileSync( CHEMINS.statique );
const htaccess = fs.readFileSync( cheminHtaccess, 'utf8' );

/* -------------------------------------------------------------------------- */
/* Mode                                                                        */
/* -------------------------------------------------------------------------- */

controler(
	'mode de génération',
	MODE_COMPLET === manifeste.mode,
	`${ manifeste.mode }`,
	'un artefact dégradé est constructible en local sans réseau, jamais commitable en silence (I-9.9) : ' +
		'jouer `npm run recuperer` puis `npm run construire`'
);

// L'archive n'est JAMAIS lue par la recette : elle est contrôlée ici, après le
// mode, pour que le motif d'un artefact dégradé soit énoncé avant sa cause.
for ( const chemin of [ CHEMINS.archive, CHEMINS.manifeste_source ] ) {
	controler( `présence de ${ path.basename( chemin ) }`, fs.existsSync( chemin ), undefined, 'l\'archive OSM est commitée : c\'est elle qui rend `npm run construire` reproductible hors ligne' );
}

/* -------------------------------------------------------------------------- */
/* Jetons — invariant I-9.7                                                    */
/* -------------------------------------------------------------------------- */

const jetons = lireJetons( CHEMINS.tokens );
const divergences = divergencesJetons( jetons );

controler(
	`jetons : les ${ Object.keys( JETONS_CARTE ).length } --c-carte-* et ${ JETON_CONTOUR } relus dans tokens.css`,
	0 === divergences.length,
	0 === divergences.length ? undefined : divergences.join( ' ; ' ),
	'le monochrome est cuit à la génération (D-01) : réaligner tokens.css et le build, par une décision écrite'
);

controler(
	'jetons : valeurs cuites dans les artefacts identiques à celles du manifeste',
	[ ...Object.keys( JETONS_CARTE ), JETON_CONTOUR ].every( ( nom ) => manifeste.jetons[ nom ] === jetons.get( nom ) )
);

const palette = paletteAutorisee( jetons );
const paletteHex = new Set( palette.map( versHexadecimal ) );

controler( 'palette : recalculée depuis tokens.css', palette.length === manifeste.palette.length, `${ palette.length } couleurs` );
controler( 'palette : identique à celle du manifeste', manifeste.palette.every( ( hex ) => paletteHex.has( hex ) ) );

/*
 * Les aplats de statut officiels sont relus dans `tokens.css`, jamais écrits ici :
 * les inscrire en dur ferait rendre un résultat au `grep` de revue du §12, qui
 * exige zéro occurrence hors `tokens.css`.
 */
const statuts = JETONS_STATUT.map( ( nom ) => ( { nom, hex: jetons.get( nom ) } ) ).filter( ( s ) => undefined !== s.hex );

controler(
	'statuts : les deux aplats officiels sont déclarés dans tokens.css',
	statuts.length === JETONS_STATUT.length,
	statuts.map( ( s ) => s.nom ).join( ', ' )
);

controler(
	'statuts : aucun aplat de statut dans la palette du fond (I-9.3)',
	statuts.every( ( s ) => ! paletteHex.has( s.hex.toUpperCase() ) )
);

/* -------------------------------------------------------------------------- */
/* Emprise et grille — recalculées, jamais relues                              */
/* -------------------------------------------------------------------------- */

const emprise = lireEmprise( CHEMINS.referentiel );
const grillesAttendues = grilles( emprise );
const nombreAttendu = grillesAttendues.reduce( ( total, g ) => total + g.nombre, 0 );

controler(
	'emprise : identique à celle du manifeste',
	jsonCanonique( emprise ) === jsonCanonique( manifeste.emprise ),
	JSON.stringify( emprise )
);

controler( 'pyramide : bornes de zoom', ZOOM_MIN === manifeste.pyramide.zoom_min && ZOOM_MAX === manifeste.pyramide.zoom_max, `z${ ZOOM_MIN }-z${ ZOOM_MAX }` );
controler( 'pyramide : côté de tuile', TAILLE_TUILE === manifeste.pyramide.taille_tuile, `${ TAILLE_TUILE } px` );
controler( 'pyramide : format', FORMAT === manifeste.pyramide.format, FORMAT );

for ( const g of grillesAttendues ) {
	const declaree = manifeste.pyramide.grilles.find( ( entree ) => entree.zoom === g.zoom );

	controler(
		`grille z${ g.zoom } : dénombrement recalculé depuis la bbox`,
		Boolean( declaree ) && declaree.nombre === g.nombre && declaree.x0 === g.x0 && declaree.x1 === g.x1 && declaree.y0 === g.y0 && declaree.y1 === g.y1,
		`${ g.colonnes } x ${ g.lignes } = ${ g.nombre }`
	);
}

controler( 'pyramide : dénombrement total', nombreAttendu === manifeste.pyramide.nombre, `${ manifeste.pyramide.nombre } tuiles` );

const bboxAttendue = bboxDeGrille( grillesAttendues[ grillesAttendues.length - 1 ] );

controler(
	'pyramide : emprise couverte alignée sur la grille du zoom le plus fin',
	jsonCanonique( bboxAttendue ) === jsonCanonique( manifeste.pyramide.bbox )
);

controler(
	'pyramide : emprise couverte, sur-ensemble strict de celle du référentiel',
	bboxAttendue.ouest < emprise.ouest && bboxAttendue.sud < emprise.sud && bboxAttendue.est > emprise.est && bboxAttendue.nord > emprise.nord
);

/* -------------------------------------------------------------------------- */
/* Version dérivée du contenu                                                  */
/* -------------------------------------------------------------------------- */

const empreinteManifeste = sha256( Buffer.from( jsonCanonique( manifeste ), 'utf8' ) );
const version = jetonVersion( empreinteManifeste );

controler(
	'version : 8 premiers hexadécimaux du sha256 du manifeste',
	version === reference.manifeste.version && empreinteManifeste === reference.manifeste.sha256,
	version,
	`${ REGENERER } — le manifeste a changé sans que la version soit régénérée`
);

/* -------------------------------------------------------------------------- */
/* Une seule version sous data/tuiles/                                         */
/* -------------------------------------------------------------------------- */

const versions = fs
	.readdirSync( CHEMINS.tuiles, { withFileTypes: true } )
	.filter( ( entree ) => entree.isDirectory() )
	.map( ( entree ) => entree.name );

controler(
	'data/tuiles : une seule version présente',
	1 === versions.length,
	versions.join( ', ' ) || '(aucune)',
	'plusieurs versions en place = des octets que personne ne sert et que le cache long fige'
);

controler( 'data/tuiles : la version présente est celle du manifeste', versions.includes( version ), version );

/* -------------------------------------------------------------------------- */
/* Tuiles : forme, dimensions, palette, octets                                 */
/* -------------------------------------------------------------------------- */

const racineVersion = path.join( CHEMINS.tuiles, version );
const attendues = Object.keys( manifeste.pyramide.tuiles );

/** Couleurs distinctes présentes dans une image décodée. */
async function couleursDe( octets ) {
	const { data, info } = await sharp( octets ).raw().toBuffer( { resolveWithObject: true } );
	const vues = new Set();

	for ( let p = 0; p < info.width * info.height; p += 1 ) {
		vues.add( ( data[ p * info.channels ] << 16 ) | ( data[ p * info.channels + 1 ] << 8 ) | data[ p * info.channels + 2 ] );
	}

	return { couleurs: vues, largeur: info.width, hauteur: info.height };
}

const horsPalette = new Set();
const chunksInattendus = new Set();
let octetsMesures = 0;
let manquantes = 0;
let mauvaisesDimensions = 0;
let empreintesFausses = 0;

if ( fs.existsSync( racineVersion ) ) {
	for ( const cle of attendues ) {
		const chemin = path.join( racineVersion, `${ cle }.${ FORMAT }` );

		if ( ! fs.existsSync( chemin ) ) {
			manquantes += 1;
			continue;
		}

		const octets = fs.readFileSync( chemin );

		octetsMesures += octets.length;

		if ( sha256( octets ) !== manifeste.pyramide.tuiles[ cle ] ) {
			empreintesFausses += 1;
		}

		for ( const type of chunksPng( octets ) ) {
			if ( ! CHUNKS_ATTENDUS.includes( type ) ) {
				chunksInattendus.add( type );
			}
		}

		// eslint-disable-next-line no-await-in-loop
		const { couleurs, largeur, hauteur } = await couleursDe( octets );

		if ( TAILLE_TUILE !== largeur || TAILLE_TUILE !== hauteur ) {
			mauvaisesDimensions += 1;
		}

		for ( const couleur of couleurs ) {
			if ( ! paletteHex.has( versHexadecimal( [ couleur >> 16, ( couleur >> 8 ) & 0xff, couleur & 0xff ] ) ) ) {
				horsPalette.add( couleur );
			}
		}
	}
}

// Différence d'ensembles, jamais une différence de comptes : une tuile manquante
// ET une tuile surnuméraire s'annuleraient sur un simple décompte, et la recette
// resterait verte sur une pyramide fausse des deux côtés.
const surnumeraires = fs.existsSync( racineVersion )
	? fs
			.readdirSync( racineVersion, { recursive: true } )
			.filter( ( entree ) => entree.endsWith( `.${ FORMAT }` ) )
			.map( ( entree ) => entree.split( path.sep ).join( '/' ).slice( 0, -1 - FORMAT.length ) )
			.filter( ( cle ) => ! Object.prototype.hasOwnProperty.call( manifeste.pyramide.tuiles, cle ) )
	: [];

controler( 'tuiles : toutes présentes', 0 === manquantes, `${ attendues.length - manquantes } / ${ attendues.length }` );
controler(
	'tuiles : aucune tuile surnuméraire sur le disque',
	0 === surnumeraires.length,
	`${ surnumeraires.length } en trop${ 0 === surnumeraires.length ? '' : ` : ${ surnumeraires.slice( 0, 5 ).join( ', ' ) }` }`
);
controler( 'tuiles : dimensions 256 x 256', 0 === mauvaisesDimensions, `${ mauvaisesDimensions } hors format` );
controler(
	'tuiles : empreintes conformes au manifeste',
	0 === empreintesFausses,
	`${ empreintesFausses } divergentes`,
	'une tuile a changé sans que le manifeste le sache : la version publiée ment sur son contenu'
);
controler(
	'tuiles : aucune couleur hors palette',
	0 === horsPalette.size,
	`${ horsPalette.size } couleur(s) étrangère(s)${ 0 === horsPalette.size ? '' : ` : ${ [ ...horsPalette ].slice( 0, 5 ).map( ( c ) => versHexadecimal( [ c >> 16, ( c >> 8 ) & 0xff, c & 0xff ] ) ).join( ', ' ) }` }`,
	'c\'est le contrôle qui attrape un fond récupéré ailleurs : un rendu OSM standard porte des teintes saturées absentes de notre palette'
);
controler(
	'tuiles : aucun chunk de métadonnée d\'image',
	0 === chunksInattendus.size,
	[ ...chunksInattendus ].join( ', ' ) || 'IHDR, PLTE, IDAT, IEND',
	'I-9.2 : une métadonnée tEXt/iTXt/XMP peut porter une URL tierce'
);
controler(
	'tuiles : octets annoncés = octets sur disque',
	octetsMesures === manifeste.pyramide.octets,
	`${ octetsMesures } / ${ manifeste.pyramide.octets }`
);

/* -------------------------------------------------------------------------- */
/* Image statique                                                              */
/* -------------------------------------------------------------------------- */

const statique = await couleursDe( statiqueBrut );

controler(
	'image statique : dimensions annoncées = dimensions réelles',
	statique.largeur === manifeste.statique.largeur && statique.hauteur === manifeste.statique.hauteur,
	`${ statique.largeur } x ${ statique.hauteur }`
);
controler( 'image statique : largeur cible', LARGEUR_STATIQUE === statique.largeur, `${ statique.largeur } px` );
controler(
	'image statique : octets annoncés = octets sur disque',
	statiqueBrut.length === manifeste.statique.octets,
	`${ statiqueBrut.length } / ${ manifeste.statique.octets }`
);
controler(
	'image statique : sous le plafond de transfert',
	statiqueBrut.length <= PLAFOND_STATIQUE_OCTETS,
	`${ statiqueBrut.length } / ${ PLAFOND_STATIQUE_OCTETS }`
);
controler( 'image statique : empreinte conforme au manifeste', sha256( statiqueBrut ) === manifeste.statique.sha256 );
controler(
	'image statique : aucune couleur hors palette',
	[ ...statique.couleurs ].every( ( c ) => paletteHex.has( versHexadecimal( [ c >> 16, ( c >> 8 ) & 0xff, c & 0xff ] ) ) )
);

const chunksStatique = chunksPng( statiqueBrut );

controler(
	'image statique : aucun chunk de métadonnée d\'image',
	chunksStatique.every( ( type ) => CHUNKS_ATTENDUS.includes( type ) ),
	chunksStatique.join( ', ' )
);

// I-9.3 — vérifié sur le BINAIRE, pas sur l'intention. Deux fronts : les octets
// bruts du fichier (palette PLTE comprise, et toute forme textuelle), et les
// pixels décodés.
for ( const statut of statuts ) {
	const [ r, v, b ] = versRgb( statut.hex );
	const dansLesOctets = statiqueBrut.includes( Buffer.from( [ r, v, b ] ) ) || statiqueBrut.includes( Buffer.from( statut.hex.replace( '#', '' ), 'ascii' ) );
	const dansLesPixels = statique.couleurs.has( ( r << 16 ) | ( v << 8 ) | b );

	controler(
		`image statique : aplat ${ statut.nom } absent du binaire (I-9.3)`,
		! dansLesOctets && ! dansLesPixels,
		dansLesOctets ? 'présent dans les octets' : dansLesPixels ? 'présent dans les pixels' : undefined,
		'une image portant les couleurs du jour se périmerait par un chemin que le PHP ne contrôle plus'
	);
}

controler(
	'image statique : contours de massifs dénombrés',
	manifeste.statique.contours_massifs > 0,
	`${ manifeste.statique.contours_massifs }`
);

/* -------------------------------------------------------------------------- */
/* En-têtes de cache                                                           */
/* -------------------------------------------------------------------------- */

controler( 'htaccess : listing fermé en forme relative', /^Options -Indexes$/m.test( htaccess ) );
controler( 'htaccess : cache long scopé aux .png', /<FilesMatch "\\\.png\$">/.test( htaccess ) && /max-age=31536000, immutable/.test( htaccess ) );
controler( 'htaccess : sous IfModule mod_headers.c', /<IfModule mod_headers\.c>/.test( htaccess ) );

/* -------------------------------------------------------------------------- */
/* Métadonnées relues PAR PHP                                                  */
/* -------------------------------------------------------------------------- */

const lectureFond = lirePhp( CHEMINS.metadonnees );
const lectureReferentiel = lirePhp( CHEMINS.referentiel );

if ( lectureFond.erreur || lectureReferentiel.erreur ) {
	echecs.push(
		`métadonnées PHP illisibles (PHP_BIN=${ PHP_INVOCATION.join( ' ' ) }` +
			`${ '' === PHP_RACINE ? '' : `, MASSIFS_PHP_RACINE=${ PHP_RACINE }` }) : ${ lectureFond.erreur || lectureReferentiel.erreur }\n` +
			'      Sans PHP atteignable la recette ÉCHOUE, elle ne saute pas ses contrôles (interdit 4 du contrat #20).'
	);
} else {
	const meta = lectureFond.donnees;

	controler( 'métadonnées : schéma connu', 1 === meta.schema, `schema=${ meta.schema }` );
	controler( 'métadonnées : mode', MODE_COMPLET === meta.mode, meta.mode );
	controler( 'métadonnées : version de la pyramide', meta.pyramide.version === version, meta.pyramide.version );
	controler( 'métadonnées : empreinte du manifeste', meta.pyramide.sha256 === empreinteManifeste );
	controler( 'métadonnées : dénombrement de tuiles', meta.pyramide.nombre === nombreAttendu, `${ meta.pyramide.nombre }` );
	controler( 'métadonnées : octets de la pyramide', meta.pyramide.octets === octetsMesures );
	controler( 'métadonnées : bornes de zoom', meta.pyramide.zoom_min === ZOOM_MIN && meta.pyramide.zoom_max === ZOOM_MAX );
	controler( 'métadonnées : côté de tuile et format', meta.pyramide.taille_tuile === TAILLE_TUILE && meta.pyramide.format === FORMAT );
	controler(
		'métadonnées : emprise couverte',
		jsonCanonique( meta.pyramide.bbox ) === jsonCanonique( bboxAttendue )
	);
	controler(
		'métadonnées : dimensions de l\'image statique',
		meta.statique.largeur === statique.largeur && meta.statique.hauteur === statique.hauteur,
		`${ meta.statique.largeur } x ${ meta.statique.hauteur }`
	);
	controler( 'métadonnées : octets de l\'image statique', meta.statique.octets === statiqueBrut.length );
	controler( 'métadonnées : empreinte de l\'image statique', meta.statique.sha256 === sha256( statiqueBrut ) );
	controler(
		'métadonnées : attribution §9 verbatim',
		meta.attribution.phrase === ATTRIBUTION.phrase && '' !== meta.attribution.phrase.trim(),
		meta.attribution.phrase
	);
	controler( 'métadonnées : lien de licence', meta.attribution.lien_licence === ATTRIBUTION.lien_licence );
	/*
	 * Liste BLANCHE, jamais liste noire. Une liste noire ne protège que ce qu'on a
	 * déjà vu, et elle obligerait à écrire dans ce dépôt le nom même des serveurs
	 * qu'on s'interdit — ce que le §12 du contrat #9 fait chercher par `grep`.
	 * Ici, toute URL absolue qui n'est pas une des trois mentions d'attribution
	 * fait rougir la recette, y compris celles qu'on n'a pas imaginées.
	 */
	const urlsAutorisees = [ ATTRIBUTION.lien_licence, ATTRIBUTION.licence_url, meta.attribution.faits.canal_url ];
	const urls = [ ...JSON.stringify( meta ).matchAll( /https?:\/\/[^"\\ ]+/g ) ].map( ( trouve ) => trouve[ 0 ] );

	controler(
		'métadonnées : aucune URL absolue hors des mentions d\'attribution',
		urls.every( ( url ) => urlsAutorisees.includes( url ) ),
		urls.filter( ( url ) => ! urlsAutorisees.includes( url ) ).join( ', ' ) || `${ urls.length } URL, toutes attendues`,
		'interdit 17 et invariant I-9.2 : aucun serveur de tuiles rendues, aucune URL tierce'
	);

	// La lecture PAR PHP du référentiel contrôle le parseur d'emprise du build :
	// c'est la lecture qui fait autorité qui valide la lecture fragile.
	const empriseAutorite = lectureReferentiel.donnees.emprise.bbox;

	controler(
		'emprise : lecture du build conforme à la lecture PHP du référentiel',
		[ 'ouest', 'sud', 'est', 'nord' ].every( ( borne ) => Number( empriseAutorite[ borne ] ) === emprise[ borne ] ),
		JSON.stringify( empriseAutorite )
	);

	/* --- Surface publique, telle que la chaîne #7 la consomme ------------- */

	const surface = lireSurfacePhp();

	if ( surface.erreur || ! surface.donnees ) {
		echecs.push( `surface publique illisible : ${ surface.erreur || 'le module de lecture n\'a rien rendu' }` );
	} else {
		const fond = surface.donnees.fond;
		const image = surface.donnees.statique;
		const credit = surface.donnees.attribution;

		controler(
			'surface : clés de massifs_fond_de_carte()',
			memesCles( Object.keys( fond ), CLES_FOND ),
			Object.keys( fond ).join( ', ' ),
			'un renommage de clé casse `templates/parts/carte.php`, hors de l\'empreinte de cette chaîne'
		);
		controler( 'surface : fond disponible', true === fond.disponible );
		controler( 'surface : classe de média', 'raster' === fond.format, fond.format );
		controler( 'surface : extension de tuile', FORMAT === fond.format_tuile, fond.format_tuile );
		controler(
			'surface : url_modele porte ses accolades et aucune query string',
			'string' === typeof fond.url_modele && fond.url_modele.endsWith( `/${ version }/{z}/{x}/{y}.${ FORMAT }` ) && ! fond.url_modele.includes( '?' ),
			fond.url_modele
		);
		controler(
			'surface : attribution du fond, projetée à plat',
			fond.attribution === ATTRIBUTION.phrase && fond.attribution_url === ATTRIBUTION.lien_licence
		);
		controler(
			'surface : clés de massifs_fond_de_carte_statique()',
			memesCles( Object.keys( image ), CLES_STATIQUE ),
			Object.keys( image ).join( ', ' )
		);
		controler(
			'surface : porte_les_statuts vaut false (I-9.3)',
			false === image.porte_les_statuts,
			undefined,
			'gelé à vie : l\'image statique ne portera jamais les statuts du jour'
		);
		controler(
			'surface : dimensions entières, égales aux dimensions réelles',
			Number.isInteger( image.largeur ) && Number.isInteger( image.hauteur ) && image.largeur === statique.largeur && image.hauteur === statique.hauteur,
			`${ image.largeur } x ${ image.hauteur }`
		);
		controler(
			'surface : clés de massifs_attribution_fond_de_carte()',
			memesCles( Object.keys( credit ), [ 'phrase', 'lien_licence', 'faits' ] ) && memesCles( Object.keys( credit.faits ), CLES_FAITS ),
			Object.keys( credit ).join( ', ' )
		);
		controler( 'surface : phrase §9 verbatim, non vide après trim', credit.phrase === ATTRIBUTION.phrase && '' !== credit.phrase.trim() );
	}
}

/* -------------------------------------------------------------------------- */
/* Dérive par rapport à reference.json                                         */
/* -------------------------------------------------------------------------- */

controlerDerive( 'référence : mode', reference.mode, manifeste.mode );
controlerDerive( 'référence : empreinte du manifeste', reference.manifeste.sha256, empreinteManifeste );
controlerDerive( 'référence : dénombrement de tuiles', reference.pyramide ? reference.pyramide.nombre : null, manifeste.pyramide ? manifeste.pyramide.nombre : null );
controlerDerive( 'référence : octets de la pyramide', reference.pyramide ? reference.pyramide.octets : null, octetsMesures );
controlerDerive( 'référence : empreinte de l\'image statique', reference.statique.sha256, sha256( statiqueBrut ) );
controlerDerive( 'référence : octets de l\'image statique', reference.statique.octets, statiqueBrut.length );
controlerDerive( 'référence : dimensions de l\'image statique', `${ reference.statique.largeur }x${ reference.statique.hauteur }`, `${ statique.largeur }x${ statique.hauteur }` );

try {
	controlerDerive( 'référence : version de mapshaper', reference.outillage.mapshaper, versionMapshaper() );
} catch ( erreur ) {
	echecs.push( `référence : version de mapshaper — ${ erreur.message }` );
}

/*
 * Node majeur : AVERTISSEMENT, jamais un échec. Refuser une recette verte sur
 * Node 26 au seul motif que la référence dit 24 est un faux positif ; et un faux
 * positif répété apprend à régénérer `reference.json` par réflexe, ce qui détruit
 * exactement le mécanisme qu'on installe ici.
 */
if ( reference.outillage.node_major !== nodeMajeur() ) {
	avertissements.push(
		`node majeur ${ nodeMajeur() } alors que reference.json a été produit sous ${ reference.outillage.node_major } ` +
			'(non bloquant ; voir `.nvmrc` si un écart apparaît). La reproductibilité BINAIRE inter-plateformes ' +
			'n\'est de toute façon pas revendiquée : resvg est un binaire natif.'
	);
} else {
	constats.push( `  ok   référence : node majeur — ${ nodeMajeur() }` );
}

/* -------------------------------------------------------------------------- */
/* Verdict                                                                     */
/* -------------------------------------------------------------------------- */

process.stdout.write( `${ constats.join( '\n' ) }\n` );

if ( avertissements.length > 0 ) {
	process.stdout.write( `\nAVERTISSEMENT(S) :\n  - ${ avertissements.join( '\n  - ' ) }\n` );
}

if ( echecs.length > 0 ) {
	const contexte = avertissements.length > 0 ? `\nContexte :\n  - ${ avertissements.join( '\n  - ' ) }\n` : '';

	process.stderr.write( `\nÉCHEC — ${ echecs.length } contrôle(s) :\n  - ${ echecs.join( '\n  - ' ) }\n${ contexte }` );
	process.exitCode = 1;
} else {
	process.stdout.write( `\nCONFORME — ${ constats.length } contrôles.\n` );
}
