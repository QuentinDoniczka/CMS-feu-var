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
 *   MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*' \
 *   PHP_BIN="docker compose run --rm -T wpcli php" \
 *   MASSIFS_PHP_RACINE='/var/www/html/wp-content/plugins/massifs-core' \
 *   npm run verifier
 *
 * Les deux variables MSYS_* sont la garde Windows / Git Bash, et ce projet tourne
 * sous Windows : sans elles, MSYS réécrit le chemin absolu de MASSIFS_PHP_RACINE
 * en `C:/Program Files/Git/var/www/…` et la recette échoue pour une raison
 * étrangère à l'artefact. Elles sont sans effet sous un shell POSIX.
 * `docker exec -i massifs_wordpress php` est un PHP_BIN équivalent.
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
	EMPRISE_DECLAREE,
	EXTENSION,
	FORMAT,
	JETONS_CARTE,
	JETONS_STATUT,
	JETON_CONTOUR,
	LARGEUR_STATIQUE,
	MODE_COMPLET,
	PLAFOND_STATIQUE_OCTETS,
	RACINE,
	TAILLE_TUILE,
	TOPONYMES,
	ZOOM_MAX,
	ZOOM_MIN,
	airePlacementMpx,
	bboxDeclaree,
	dansAnneau,
	divergencesJetons,
	ecartBoites,
	grille,
	grilleDeclaree,
	grillesDeclarees,
	jetonVersion,
	jsonCanonique,
	lireEmprise,
	lireJetons,
	luma,
	nodeMajeur,
	normX,
	normY,
	paletteAutorisee,
	sha256,
	versHexadecimal,
	versRgb,
	versionMapshaper,
} from './commun.mjs';
import { ouvrirPolice, svgEtiquettes } from './etiquettes.mjs';
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
 * C-g — part minimale d'encre dans `encre + trait` sous laquelle une étiquette n'a
 * pas de CŒUR à 6,82:1 et ne tient plus que par sa frange à 4,17:1.
 *
 * Seuil du §5 du contrat #71, écrit UNE fois : le contrôle et son intitulé le
 * citaient tous deux, et un seuil recopié se désaccorde de sa propre mesure.
 *
 * Il vit ici et NON dans `TOPONYMES` : c'est un critère de RECETTE, pas un réglage
 * de build. Inscrit dans `TOPONYMES`, il entrerait au manifeste, donc dans
 * l'empreinte de version — et resserrer un seuil de contrôle changerait alors la
 * version publiée sans qu'un seul pixel ait bougé.
 */
const COEUR_ENCRE_MIN = 0.35;

/**
 * Nombre de boîtes décodées par zoom pour C-g.
 *
 * Échantillon BORNÉ, pas exhaustif : le corps est constant sur les zooms résolus et
 * doublé à `ZOOM_MAX`, donc chaque régime typographique est représenté dès les
 * premières boîtes, et le coût de décodage des tuiles ne croît pas avec le carré du
 * zoom — z11 en porte 63.
 */
const ECHANTILLON_BOITES = 8;

/**
 * Zoom dont le jeu est REPOSÉ à `ZOOM_MAX` : z12 ne résout rien, il reprend le jeu
 * du zoom immédiatement inférieur, au double du corps (I-71.9).
 */
const ZOOM_JEU_SOURCE = ZOOM_MAX - 1;

/**
 * Zooms qui résolvent leur propre jeu d'étiquettes — DÉRIVÉS de
 * `TOPONYMES.zoom_min_etiquettes`, jamais écrits.
 *
 * La liste `[ 9, 10, 11 ]` était recopiée à quatre endroits : elle cesserait d'être
 * vraie au premier déplacement du zoom minimal d'étiquetage, et la recette
 * contrôlerait alors des zooms qui ne sont plus étiquetés — en restant verte.
 */
const ZOOMS_RESOLUS = [];

for ( let zoom = TOPONYMES.zoom_min_etiquettes; zoom <= ZOOM_JEU_SOURCE; zoom += 1 ) {
	ZOOMS_RESOLUS.push( zoom );
}

/**
 * Zooms que le §3 du contrat gèle à ZÉRO étiquette — écrits en clair, NORMATIFS.
 *
 * Une liste dérivée de `zoom_min_etiquettes`, comme celle du dessus, suit le réglage :
 * abaisser la constante à 8 renommerait le contrôle au lieu de le faire rougir. Les
 * comptes « z5–z8 0 » du §3 sont une clause, pas une conséquence du réglage — les
 * déplacer demande une révision du contrat, jamais une retouche du build.
 */
const ZOOMS_SANS_ETIQUETTE_NORMATIFS = [ 5, 6, 7, 8 ];

/** Seuil qu'impose la clause ci-dessus : le premier zoom qu'elle laisse étiqueter. */
const ZOOM_MIN_ETIQUETTES_NORMATIF = ZOOMS_SANS_ETIQUETTE_NORMATIFS[ ZOOMS_SANS_ETIQUETTE_NORMATIFS.length - 1 ] + 1;

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
const notes = [];

/**
 * Mesure IMPRIMÉE SANS ÊTRE ASSERTÉE, et délibérément hors du décompte de
 * contrôles.
 *
 * Deux des rapports de contraste du §13 du contrat #71 sont sous 4,5:1, pour une
 * raison de design écrite. Les asserter serait asserter une chose fausse ; les
 * taire ferait croire qu'ils n'existent pas ; les compter comme des contrôles
 * gonflerait le décompte d'une ligne incapable de rougir.
 *
 * @param {string} ligne Mesure et sa lecture.
 */
function noter( ligne ) {
	notes.push( ligne );
}

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

/**
 * Les jetons que le build CUIT dans les octets : les six `--c-carte-*` et l'encre
 * des contours de l'image statique.
 *
 * Écrite une fois, la liste sert au contrôle des valeurs cuites ET au classement en
 * aplats, traits et encre du contrôle de contraste — lequel exige justement de n'en
 * laisser aucun de côté, et ne peut donc pas se fonder sur une seconde copie.
 */
const JETONS_CUITS = [ ...Object.keys( JETONS_CARTE ), JETON_CONTOUR ];

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
	JETONS_CUITS.every( ( nom ) => manifeste.jetons[ nom ] === jetons.get( nom ) )
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
/* Contraste AA des toponymes — §13 du contrat #71, invariant I-71.13          */
/* -------------------------------------------------------------------------- */

/**
 * Luminance relative WCAG 2.x d'une couleur sRGB, canaux LINÉARISÉS.
 *
 * À ne pas confondre avec `luma()` de `commun.mjs`, qui pondère les canaux BRUTS :
 * celui-là mesure une texture APPARENTE, pour C-f, et c'est délibéré ; celui-ci
 * mesure une photométrie, et c'est elle que WCAG 2.x prescrit pour un rapport de
 * contraste. Deux grandeurs voisines, deux usages disjoints.
 *
 * @param {number[]} rgb Canaux 0-255.
 * @return {number} Luminance relative, dans [0, 1].
 */
function luminanceRelative( [ r, v, b ] ) {
	const canal = ( c ) => {
		const s = c / 255;

		return s <= 0.04045 ? s / 12.92 : ( ( s + 0.055 ) / 1.055 ) ** 2.4;
	};

	return 0.2126 * canal( r ) + 0.7152 * canal( v ) + 0.0722 * canal( b );
}

/**
 * Rapport de contraste WCAG 2.x : `( L_clair + 0,05 ) / ( L_sombre + 0,05 )`.
 *
 * Écrit UNE fois, et c'est tout le point du contrôle : les rapports du §13 du
 * contrat #71 ne vivaient que dans de la prose, où aucune dérive de jeton ne
 * pouvait les faire rougir. Ils sont désormais RECALCULÉS depuis `tokens.css`, par
 * les mêmes jetons que I-9.7 y relit.
 *
 * @param {string} hexA Couleur `#RRGGBB`.
 * @param {string} hexB Couleur `#RRGGBB`.
 * @return {number} Rapport, dans [1, 21].
 */
function contrasteWcag( hexA, hexB ) {
	const a = luminanceRelative( versRgb( hexA ) );
	const b = luminanceRelative( versRgb( hexB ) );

	return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
}

/** Rapport minimal WCAG AA pour du texte — §8 du brief, ligne de DoD bloquante. */
const CONTRASTE_AA_MIN = 4.5;

/** Un rapport tel que le §13 l'écrit : deux décimales, virgule décimale. */
function enRapport( valeur ) {
	return `${ valeur.toFixed( 2 ).replace( '.', ',' ) }:1`;
}

/**
 * L'ENCRE, les deux couleurs de TRAIT, les quatre APLATS du fond — et le halo, qui
 * est l'un de ces aplats plutôt qu'un jeton de plus.
 *
 * La distinction porte toute la conformité : un aplat PEUT être l'arrière-plan d'un
 * toponyme, un trait ne l'est JAMAIS. `--c-carte-trait` (frange d'anticrénelage) et
 * `--c-charbon` (les 25 contours) échouent tous deux à 4,5:1 — légitimement — et le
 * design y répond par le halo (§13.1) et par le dégagement de 6 px de C-e (§13.2),
 * jamais par un jeton nouveau. Les asserter serait asserter une chose fausse : ils
 * sont donc IMPRIMÉS par `noter()`, jamais contrôlés.
 */
const JETON_ENCRE = '--c-carte-encre';
const JETON_TRAIT = '--c-carte-trait';
const JETON_HALO = '--c-carte-fond';
const APLATS_FOND = [ JETON_HALO, '--c-carte-terre', '--c-carte-vegetation', '--c-carte-eau' ];
const TRAITS_FOND = [ JETON_TRAIT, JETON_CONTOUR ];

controler(
	`contraste : les ${ JETONS_CUITS.length } jetons cuits se classent en aplats, traits et encre, sans reste`,
	memesCles( [ ...APLATS_FOND, ...TRAITS_FOND, JETON_ENCRE ], JETONS_CUITS ),
	`${ APLATS_FOND.length } aplats, ${ TRAITS_FOND.length } traits, 1 encre`,
	'un jeton non classé échapperait au contrôle de contraste ; la palette est fermée à 7 (interdit 9 du §7 du contrat #71)'
);

const encreHex = jetons.get( JETON_ENCRE );

for ( const aplat of APLATS_FOND ) {
	const rapport = contrasteWcag( encreHex, jetons.get( aplat ) );

	controler(
		`contraste : ${ JETON_ENCRE } sur ${ aplat } tient ${ enRapport( CONTRASTE_AA_MIN ) } (I-71.13)`,
		rapport >= CONTRASTE_AA_MIN,
		enRapport( rapport ),
		'recalculé depuis tokens.css : une dérive de jeton qui casse AA est une décision de design, jamais une correction de build'
	);
}

/*
 * Le halo est l'arrière-plan EFFECTIF de l'encre : c'est lui, et non les aplats,
 * qui porte la conformité AA, puisque chaque glyphe en est intégralement ceint sur
 * `halo_px` vers l'extérieur. On ne relit donc pas une constante — on appelle la
 * fonction que le build emploie, et on lit ce qu'elle émet réellement.
 *
 * DEUX étiquettes de synthèse, et non une : le §13.1 du contrat #71 énonce la
 * propriété AU PLURIEL — TOUS les halos d'abord, TOUS les remplissages ensuite. Une
 * seule étiquette ne distingue pas ce groupement d'un entrelacement par étiquette
 * (halo 1, remplissage 1, halo 2, remplissage 2), lequel poserait le halo de la
 * seconde PAR-DESSUS l'encre de la première : 6,82:1 ne tiendrait alors plus sur
 * toute la ligne. Deux fragments DISTINCTS rendent la différence mesurable.
 *
 * Les deux contrôles qui suivent se composent, et aucun ne suffit seul : le premier
 * établit le GROUPEMENT, le second l'ORDRE et les COULEURS des deux tracés.
 */
const GLYPHES_TEMOINS = [ 'M0 0', 'M1 1' ];
const tracesTemoins = svgEtiquettes( GLYPHES_TEMOINS.map( ( d ) => ( { d } ) ), jetons, TOPONYMES.halo_px );
const [ pathHalo = '', pathRemplissage = '' ] = tracesTemoins;
const haloHex = ( /stroke="(#[0-9A-Fa-f]{6})"/.exec( pathHalo ) || [] )[ 1 ];
const remplissageHex = ( /fill="(#[0-9A-Fa-f]{6})"/.exec( pathRemplissage ) || [] )[ 1 ];
const glyphesGroupes = GLYPHES_TEMOINS.filter( ( d ) => pathHalo.includes( d ) && pathRemplissage.includes( d ) );

controler(
	`contraste : ${ GLYPHES_TEMOINS.length } étiquettes rendent 2 tracés GROUPÉS, jamais un couple halo/remplissage par étiquette (§13.1)`,
	2 === tracesTemoins.length && glyphesGroupes.length === GLYPHES_TEMOINS.length,
	`${ tracesTemoins.length } tracé(s), ${ glyphesGroupes.length } / ${ GLYPHES_TEMOINS.length } glyphes présents dans les deux`,
	"entrelacés, le halo d'une étiquette se peindrait SUR l'encre de la précédente, et 6,82:1 ne tiendrait plus sur toute la ligne"
);

controler(
	`contraste : le halo émis par le build est ${ JETON_HALO }, peint AVANT le remplissage ${ JETON_ENCRE }`,
	haloHex === jetons.get( JETON_HALO ) && remplissageHex === encreHex,
	`halo ${ haloHex }, remplissage ${ remplissageHex }`,
	"sans halo de fond peint en premier, l'encre reposerait sur la frange --c-carte-trait à 4,17:1 et la ligne 4,5:1 tomberait"
);

const contrasteHalo = undefined === haloHex ? 0 : contrasteWcag( encreHex, haloHex );

controler(
	`contraste : ${ JETON_ENCRE } sur le halo réellement peint tient ${ enRapport( CONTRASTE_AA_MIN ) } (I-71.13)`,
	contrasteHalo >= CONTRASTE_AA_MIN,
	enRapport( contrasteHalo ),
	"c'est CE rapport qui porte la conformité AA des toponymes ; les quatre aplats n'en sont que le second rideau"
);

/*
 * Les deux couleurs de trait sont IMPRIMÉES, jamais assertées — voir `noter()` et
 * le commentaire de `TRAITS_FOND`.
 */
for ( const trait of TRAITS_FOND ) {
	noter(
		`${ JETON_ENCRE } sur ${ trait } — ${ enRapport( contrasteWcag( encreHex, jetons.get( trait ) ) ) } pour un ` +
			`seuil AA de ${ enRapport( CONTRASTE_AA_MIN ) } : mesure NON ASSERTÉE, et passer dessous n'y serait pas ` +
			"un défaut — couleur de TRAIT, jamais d'aplat, donc jamais l'arrière-plan d'un toponyme ; halo (§13.1) " +
			'et dégagement de 6 px de C-e (§13.2)'
	);
}

/* -------------------------------------------------------------------------- */
/* Emprise et grille — recalculées, jamais relues                              */
/* -------------------------------------------------------------------------- */

const emprise = lireEmprise( CHEMINS.referentiel );
const grillesAttendues = grillesDeclarees();
const nombreAttendu = grillesAttendues.reduce( ( total, g ) => total + g.nombre, 0 );

controler(
	'emprise : référentiel identique à celui du manifeste',
	jsonCanonique( emprise ) === jsonCanonique( manifeste.emprise ),
	JSON.stringify( emprise )
);

// (a) — l'emprise déclarée du manifeste est bien celle du code. Sans ce contrôle,
// un manifeste produit sous une autre emprise passerait tous les suivants, qui
// se comparent entre eux.
controler(
	'emprise déclarée : identique à EMPRISE_DECLAREE',
	jsonCanonique( { ...EMPRISE_DECLAREE } ) === jsonCanonique( manifeste.emprise_declaree ),
	JSON.stringify( manifeste.emprise_declaree ),
	'l\'emprise est une grandeur DÉCLARÉE : la changer est une décision écrite, qui re-cuit les tuiles et rejoue ce contrôle'
);

controler( 'pyramide : bornes de zoom', ZOOM_MIN === manifeste.pyramide.zoom_min && ZOOM_MAX === manifeste.pyramide.zoom_max, `z${ ZOOM_MIN }-z${ ZOOM_MAX }` );
controler( 'pyramide : côté de tuile', TAILLE_TUILE === manifeste.pyramide.taille_tuile, `${ TAILLE_TUILE } px` );
controler( 'pyramide : format', FORMAT === manifeste.pyramide.format, FORMAT );

// (b) — chaque grille dérive de l'emprise déclarée par décalage entier.
for ( const g of grillesAttendues ) {
	const rendue = manifeste.pyramide.grilles.find( ( entree ) => entree.zoom === g.zoom );

	controler(
		`grille z${ g.zoom } : dérivée de l'emprise déclarée par décalage entier`,
		Boolean( rendue ) && rendue.nombre === g.nombre && rendue.x0 === g.x0 && rendue.x1 === g.x1 && rendue.y0 === g.y0 && rendue.y1 === g.y1,
		`${ g.colonnes } x ${ g.lignes } = ${ g.nombre }`
	);
}

controler( 'pyramide : dénombrement total', nombreAttendu === manifeste.pyramide.nombre, `${ manifeste.pyramide.nombre } tuiles` );

// (c) — DÉCLARÉ === PUBLIÉ : la bbox publiée est exactement l'emprise déclarée.
const bboxAttendue = bboxDeclaree();

controler(
	'pyramide : emprise publiée = emprise déclarée, à l\'octet',
	jsonCanonique( bboxAttendue ) === jsonCanonique( manifeste.pyramide.bbox )
);

/*
 * (d) — non-débordement AU NIVEAU DE LA TUILE. La phrase « sur-ensemble strict de
 * `massifs_emprise()['bbox']` » du §1.1 du contrat #9 reste vraie mot pour mot,
 * mais son référent ne bouge plus avec la géométrie : c'est `EMPRISE_DECLAREE`.
 * L'ancien contrôle comparait une bbox à elle-même — une tautologie, et c'est
 * elle qui a laissé `ded0f2f` invalider 280 tuiles en silence.
 */
const grilleReferentiel = grille( emprise, EMPRISE_DECLAREE.zoom );

controler(
	'emprise déclarée : la grille du référentiel y est contenue sur ses quatre bords',
	grilleReferentiel.x0 >= EMPRISE_DECLAREE.x0 &&
		grilleReferentiel.x1 <= EMPRISE_DECLAREE.x1 &&
		grilleReferentiel.y0 >= EMPRISE_DECLAREE.y0 &&
		grilleReferentiel.y1 <= EMPRISE_DECLAREE.y1,
	`x ${ grilleReferentiel.x0 }..${ grilleReferentiel.x1 } dans ${ EMPRISE_DECLAREE.x0 }..${ EMPRISE_DECLAREE.x1 }, ` +
		`y ${ grilleReferentiel.y0 }..${ grilleReferentiel.y1 } dans ${ EMPRISE_DECLAREE.y0 }..${ EMPRISE_DECLAREE.y1 }`,
	'DÉCIDER une nouvelle EMPRISE_DECLAREE, jamais la recalculer depuis la géométrie'
);

controler(
	'emprise déclarée : sur-ensemble strict de celle du référentiel',
	bboxAttendue.ouest < emprise.ouest && bboxAttendue.sud < emprise.sud && bboxAttendue.est > emprise.est && bboxAttendue.nord > emprise.nord
);

/*
 * (e) — le balayage par SOMMET est rejoué ici, sur la géométrie publiée.
 * `construire` ne peut faire échouer qu'un build qu'on lance ; `verifier` fait
 * échouer un build qu'on COMMITE. Deux garanties différentes, les deux utiles.
 */
const debordements = [];

if ( fs.existsSync( CHEMINS.geometrie ) ) {
	const geometrie = JSON.parse( fs.readFileSync( CHEMINS.geometrie, 'utf8' ) );

	for ( const feature of geometrie.features || [] ) {
		if ( ! feature || ! feature.geometry ) {
			continue;
		}

		const code = feature.properties && feature.properties.code ? String( feature.properties.code ) : '(sans code)';

		const visiter = ( noeud ) => {
			if ( 'number' === typeof noeud[ 0 ] ) {
				if ( noeud[ 0 ] < bboxAttendue.ouest || noeud[ 0 ] > bboxAttendue.est || noeud[ 1 ] < bboxAttendue.sud || noeud[ 1 ] > bboxAttendue.nord ) {
					debordements.push( `${ code } (${ noeud[ 0 ] }, ${ noeud[ 1 ] })` );
				}

				return;
			}

			for ( const enfant of noeud ) {
				visiter( enfant );
			}
		};

		visiter( feature.geometry.coordinates );
	}
} else {
	debordements.push( `géométrie introuvable : ${ CHEMINS.geometrie }` );
}

controler(
	'emprise déclarée : aucun sommet du référentiel n\'en sort (I-71.4)',
	0 === debordements.length,
	0 === debordements.length ? 'balayage O(n) sur chaque sommet des 25 contours' : debordements.slice( 0, 5 ).join( ' ; ' ),
	'DÉCIDER une nouvelle EMPRISE_DECLAREE, en coordonnées entières de tuile à z12, et rejouer le build complet'
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

/**
 * Recompose une zone de la toile d'un zoom depuis les tuiles LIVRÉES.
 *
 * Une boîte d'étiquette est en coordonnées de toile ; elle peut être à cheval sur
 * plusieurs tuiles, puisque le solveur travaille sur la toile entière avant
 * découpe. On recolle donc exactement ce que Leaflet recollera, ce qui est la
 * seule manière de mesurer sur les octets SERVIS et non sur une intention.
 *
 * @param {number}   zoom  Zoom de la toile.
 * @param {number[]} boite `[x0, y0, x1, y1]` en pixels de toile.
 * @return {{data:Buffer,largeur:number,hauteur:number}|null}
 */
async function lireZone( zoom, [ x0, y0, x1, y1 ] ) {
	const g = grilleDeclaree( zoom );
	const largeur = x1 - x0;
	const hauteur = y1 - y0;

	if ( largeur <= 0 || hauteur <= 0 ) {
		return null;
	}

	const data = Buffer.alloc( largeur * hauteur * 3 );

	for ( let tuileY = Math.floor( y0 / TAILLE_TUILE ); tuileY <= Math.floor( ( y1 - 1 ) / TAILLE_TUILE ); tuileY += 1 ) {
		for ( let tuileX = Math.floor( x0 / TAILLE_TUILE ); tuileX <= Math.floor( ( x1 - 1 ) / TAILLE_TUILE ); tuileX += 1 ) {
			const chemin = path.join( racineVersion, `${ zoom }/${ g.x0 + tuileX }/${ g.y0 + tuileY }.${ FORMAT }` );

			if ( ! fs.existsSync( chemin ) ) {
				return null;
			}

			// eslint-disable-next-line no-await-in-loop
			const tuile = await pixelsDe( fs.readFileSync( chemin ) );

			for ( let y = 0; y < TAILLE_TUILE; y += 1 ) {
				const cibleY = tuileY * TAILLE_TUILE + y - y0;

				if ( cibleY < 0 || cibleY >= hauteur ) {
					continue;
				}

				for ( let x = 0; x < TAILLE_TUILE; x += 1 ) {
					const cibleX = tuileX * TAILLE_TUILE + x - x0;

					if ( cibleX < 0 || cibleX >= largeur ) {
						continue;
					}

					const source = ( y * TAILLE_TUILE + x ) * tuile.canaux;
					const cible = ( cibleY * largeur + cibleX ) * 3;

					data[ cible ] = tuile.data[ source ];
					data[ cible + 1 ] = tuile.data[ source + 1 ];
					data[ cible + 2 ] = tuile.data[ source + 2 ];
				}
			}
		}
	}

	return { data, largeur, hauteur };
}

/**
 * Projection de l'image statique, recalculée et non relue.
 *
 * Elle dérive de l'emprise DÉCLARÉE et des dimensions publiées : la recette
 * reconstruit ce que le build a fait, plutôt que de faire confiance à une boîte
 * consignée. C'est ce qui permet de contrôler l'exclusion des intérieurs de
 * massifs sans rasteriser à nouveau.
 */
function projectionStatique( largeur, hauteur ) {
	const declaree = bboxDeclaree();
	const etendueX = normX( declaree.est ) - normX( declaree.ouest );
	const etendueY = normY( declaree.sud ) - normY( declaree.nord );

	return ( [ lon, lat ] ) => [
		( ( normX( lon ) - normX( declaree.ouest ) ) / etendueX ) * largeur,
		( ( normY( lat ) - normY( declaree.nord ) ) / etendueY ) * hauteur,
	];
}

/** Deux segments se croisent-ils ? Orientation, sans division. */
function segmentsSeCroisent( p, q, r, s ) {
	const orientation = ( a, b, c ) => Math.sign( ( b[ 0 ] - a[ 0 ] ) * ( c[ 1 ] - a[ 1 ] ) - ( b[ 1 ] - a[ 1 ] ) * ( c[ 0 ] - a[ 0 ] ) );

	return orientation( p, q, r ) !== orientation( p, q, s ) && orientation( r, s, p ) !== orientation( r, s, q );
}

/**
 * Une boîte rencontre-t-elle l'intérieur d'un polygone ?
 *
 * Test COMPLET, en trois cas qui se complètent : un sommet du polygone dans la
 * boîte, le centre de la boîte dans le polygone (cas d'une boîte entièrement
 * intérieure), ou une arête du polygone qui coupe une arête de la boîte. Aucun des
 * trois seul ne suffit.
 */
function boiteRencontrePolygone( [ x0, y0, x1, y1 ], geometrie, projeter ) {
	const anneaux = [];

	if ( 'Polygon' === geometrie.type ) {
		anneaux.push( ...geometrie.coordinates );
	} else if ( 'MultiPolygon' === geometrie.type ) {
		for ( const partie of geometrie.coordinates ) {
			anneaux.push( ...partie );
		}
	}

	const coins = [
		[ x0, y0 ],
		[ x1, y0 ],
		[ x1, y1 ],
		[ x0, y1 ],
	];
	const centre = [ ( x0 + x1 ) / 2, ( y0 + y1 ) / 2 ];
	let dedans = false;

	for ( const anneau of anneaux ) {
		const projete = anneau.map( projeter );

		for ( const point of projete ) {
			if ( point[ 0 ] >= x0 && point[ 0 ] <= x1 && point[ 1 ] >= y0 && point[ 1 ] <= y1 ) {
				return true;
			}
		}

		for ( let i = 0; i < projete.length - 1; i += 1 ) {
			for ( let c = 0; c < 4; c += 1 ) {
				if ( segmentsSeCroisent( projete[ i ], projete[ i + 1 ], coins[ c ], coins[ ( c + 1 ) % 4 ] ) ) {
					return true;
				}
			}
		}

		// Anneaux extérieurs et trous se composent par ou-exclusif : un centre dans un
		// trou repasse à « dehors », ce qui est exactement la sémantique voulue.
		if ( dansAnneau( centre, projete ) ) {
			dedans = ! dedans;
		}
	}

	return dedans;
}

/** Pixels décodés d'une image, en RGB brut. */
async function pixelsDe( octets ) {
	const { data, info } = await sharp( octets ).raw().toBuffer( { resolveWithObject: true } );

	return { data, largeur: info.width, hauteur: info.height, canaux: info.channels };
}

/** Couleurs distinctes présentes dans une image décodée. */
async function couleursDe( octets ) {
	const { data, largeur, hauteur, canaux } = await pixelsDe( octets );
	const vues = new Set();

	for ( let p = 0; p < largeur * hauteur; p += 1 ) {
		vues.add( ( data[ p * canaux ] << 16 ) | ( data[ p * canaux + 1 ] << 8 ) | data[ p * canaux + 2 ] );
	}

	return { couleurs: vues, largeur, hauteur };
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
/* Toponymes cuits — T1 à T15, C-a à C-g                                       */
/* -------------------------------------------------------------------------- */

const toponymes = manifeste.toponymes;

controler(
	'toponymes : bloc présent au manifeste',
	Boolean( toponymes && toponymes.jeux && toponymes.police && toponymes.reglages ),
	undefined,
	'une archive antérieure à #71 ne porte pas de couche `toponymes` : rejouer `npm run recuperer`'
);

if ( toponymes && toponymes.jeux ) {
	const jeux = toponymes.jeux;
	const nomsDe = ( zoom ) => new Set( ( jeux[ zoom ] || [] ).map( ( etiquette ) => etiquette.nom ) );

	/* --- T1 — la police est contrôlée comme les jetons le sont (I-71.7) ---- */

	const policeOctets = fs.existsSync( CHEMINS.police_texte ) ? fs.readFileSync( CHEMINS.police_texte ) : null;

	controler(
		'toponymes : empreinte de la police cuite',
		null !== policeOctets && sha256( policeOctets ) === toponymes.police.sha256,
		null === policeOctets ? 'police introuvable' : sha256( policeOctets ).slice( 0, 16 ),
		'un fichier de thème cuit dans les octets sans être contrôlé serait le trou que I-9.7 ferme pour les couleurs'
	);

	if ( null !== policeOctets ) {
		const relue = ouvrirPolice( CHEMINS.police_texte );

		controler(
			'toponymes : nom PostScript et upem relus par fontkit',
			relue.nomPostScript === toponymes.police.nomPostScript && relue.upem === toponymes.police.upem,
			`${ relue.nomPostScript }, upem ${ relue.upem }`
		);
	}

	controler( 'toponymes : instance par défaut, aucune variation', 'defaut' === toponymes.police.variation );

	/* --- T2 — aucun `<text>`, aucune substitution, aucun localeCompare ----- */

	/*
	 * Les motifs sont ASSEMBLÉS et non écrits d'un seul tenant : ce fichier est
	 * lui-même dans le champ du balayage, et une aiguille écrite littéralement se
	 * trouverait elle-même. C'est laid, c'est délibéré, et c'est la seule forme
	 * honnête pour un scanner qui s'inspecte.
	 *
	 * Le balayage porte sur le CODE, commentaires retirés. Un commentaire qui dit
	 * « n'émettez jamais <text> » est exactement la documentation que le contrat
	 * exige : le compter comme une occurrence rendrait le contrôle inapplicable et
	 * ferait supprimer la documentation pour faire passer la recette.
	 */
	const interdits = [ '<' + 'text', 'font' + '-family', 'get' + 'Variation', 'locale' + 'Compare' ];
	const trouves = [];

	// Les seuls `//` de nos sources qui ne sont pas des commentaires sont les
	// schémas d'URL ; ils sont précédés de `:`, ce qui suffit à les distinguer.
	const sansCommentaires = ( source ) => source.replace( /\/\*[\s\S]*?\*\//g, ' ' ).replace( /(^|[^:])\/\/[^\n]*/g, '$1' );

	for ( const fichier of fs.readdirSync( RACINE ).filter( ( entree ) => entree.endsWith( '.mjs' ) ) ) {
		const source = sansCommentaires( fs.readFileSync( path.join( RACINE, fichier ), 'utf8' ) );

		for ( const motif of interdits ) {
			if ( source.includes( motif ) ) {
				trouves.push( `${ fichier } : ${ motif }` );
			}
		}
	}

	// L'INTITULÉ NON PLUS ne cite pas les motifs : il est lui-même dans le champ du
	// balayage. Les motifs cherchés sont énoncés dans le détail, assemblés.
	controler(
		'toponymes : aucun élément de texte, aucune police nommée, aucune variation ni comparaison localisée dans build/**',
		0 === trouves.length,
		trouves.join( ' ; ' ) || `${ interdits.length } motifs cherchés (${ interdits.join( ', ' ) }), zéro occurrence hors commentaire`,
		'I-71.8 et interdit 7 du §7 : la substitution système et la dépendance à l\'ICU deviennent structurellement impossibles'
	);

	controler(
		'toponymes : loadSystemFonts: false passé à Resvg',
		sansCommentaires( fs.readFileSync( path.join( RACINE, 'construire.mjs' ), 'utf8' ) ).includes( 'loadSystemFonts: false' ),
		undefined,
		'sans cette ligne la non-substitution est incidente au lieu d\'être structurelle'
	);

	/* --- T3 / T4 — confinement et non-collision, à chaque zoom (I-71.2) ---- */

	for ( let zoom = ZOOM_MIN; zoom <= ZOOM_MAX; zoom += 1 ) {
		const jeu = jeux[ zoom ] || [];

		if ( 0 === jeu.length ) {
			continue;
		}

		const g = grilleDeclaree( zoom );
		const hors = jeu.filter(
			( etiquette ) =>
				etiquette.boite_dilatee[ 0 ] < 0 ||
				etiquette.boite_dilatee[ 1 ] < 0 ||
				etiquette.boite_dilatee[ 2 ] > g.largeur_px ||
				etiquette.boite_dilatee[ 3 ] > g.hauteur_px
		);

		controler(
			`toponymes z${ zoom } : aucune boîte rognée par le bord de la toile (I-71.2)`,
			0 === hors.length,
			`${ jeu.length } étiquette(s) dans ${ g.largeur_px } x ${ g.hauteur_px }${ 0 === hors.length ? '' : ` — ${ hors.map( ( e ) => e.nom ).join( ', ' ) }` }`
		);

		let collisions = 0;

		for ( let i = 0; i < jeu.length; i += 1 ) {
			for ( let j = i + 1; j < jeu.length; j += 1 ) {
				if ( ecartBoites( jeu[ i ].boite_dilatee, jeu[ j ].boite_dilatee ) < 0 ) {
					collisions += 1;
				}
			}
		}

		controler( `toponymes z${ zoom } : aucune paire de boîtes dilatées ne se recouvre`, 0 === collisions, `${ collisions } recouvrement(s)` );
	}

	/* --- T5 — monotonie et jeu z12 (I-71.9, I-71.10) ----------------------- */

	for ( const zoom of ZOOMS_RESOLUS.slice( 1 ) ) {
		const precedents = nomsDe( zoom - 1 );
		const courants = nomsDe( zoom );
		const perdus = [ ...precedents ].filter( ( nom ) => ! courants.has( nom ) );

		controler(
			`toponymes : monotonie noms(z${ zoom }) ⊇ noms(z${ zoom - 1 }) (I-71.10)`,
			0 === perdus.length,
			0 === perdus.length ? `${ precedents.size } → ${ courants.size }` : perdus.join( ', ' ),
			'un nom qui disparaît en zoomant est une carte qui semble perdre de l\'information'
		);
	}

	const jeuSource = jeux[ ZOOM_JEU_SOURCE ] || [];
	const nomsSource = jeuSource.map( ( etiquette ) => etiquette.nom );
	const nomsDouze = ( jeux[ ZOOM_MAX ] || [] ).map( ( etiquette ) => etiquette.nom );

	controler(
		`toponymes : le jeu z${ ZOOM_MAX } est exactement celui de z${ ZOOM_JEU_SOURCE } (I-71.9)`,
		jsonCanonique( nomsSource ) === jsonCanonique( nomsDouze ),
		`${ nomsSource.length } / ${ nomsDouze.length } noms`,
		'un jeu différent afficherait des NOMS DIFFÉRENTS selon la densité de l\'écran : une divergence de données'
	);

	const ancresDifferentes = ( jeux[ ZOOM_MAX ] || [] ).filter(
		( etiquette, rang ) => ! jeuSource[ rang ] || jeuSource[ rang ].ancrage !== etiquette.ancrage
	);

	controler( `toponymes : mêmes ancres à z${ ZOOM_JEU_SOURCE } et z${ ZOOM_MAX }`, 0 === ancresDifferentes.length, `${ ancresDifferentes.length } divergente(s)` );

	const corpsDouzeAttendu = TOPONYMES.corps_px * TOPONYMES.facteur_z12;

	controler(
		`toponymes : corps z${ ZOOM_MAX } = ${ TOPONYMES.facteur_z12 } x corps z${ ZOOM_JEU_SOURCE }`,
		( jeux[ ZOOM_MAX ] || [] ).every( ( etiquette ) => etiquette.corps_px === corpsDouzeAttendu ),
		`${ corpsDouzeAttendu } px`
	);

	/* --- T6 / C-a — la statique est un sous-ensemble du jeu du zoom perçu -- */

	const jeuPercu = nomsDe( TOPONYMES.zoom_percu_statique );
	const horsJeu = ( jeux.statique || [] ).filter( ( etiquette ) => ! jeuPercu.has( etiquette.nom ) );

	controler(
		`toponymes : statique ⊆ jeu z${ TOPONYMES.zoom_percu_statique } (C-a)`,
		0 === horsJeu.length,
		`${ ( jeux.statique || [] ).length } sur ${ jeuPercu.size }${ 0 === horsJeu.length ? '' : ` — ${ horsJeu.map( ( e ) => e.nom ).join( ', ' ) }` }`
	);

	controler(
		'toponymes : nombre d\'étiquettes de la statique sous le plafond (C-a)',
		( jeux.statique || [] ).length <= TOPONYMES.etiquettes_statique_max,
		`${ ( jeux.statique || [] ).length } / ${ TOPONYMES.etiquettes_statique_max }`
	);

	// Contrôle de vraisemblance REDONDANT, jamais la borne mordante : c'est
	// `etiquettes_statique_max` qui mord, quatre fois plus bas. L'estimation d'octets
	// qui fondait cette borne dans l'arbitrage A-4 a été infirmée par la mesure — ne
	// pas la réécrire ici, elle se périmerait de nouveau.
	controler(
		'toponymes : statique sous le nombre de contours de massifs (vraisemblance)',
		( jeux.statique || [] ).length <= manifeste.statique.contours_massifs,
		`${ ( jeux.statique || [] ).length } / ${ manifeste.statique.contours_massifs }`
	);

	/* --- T7 / T8 — dénombrements recalculés, et zéro étiquette à z5–z8 ----- */

	for ( const zoom of ZOOMS_RESOLUS ) {
		const plafond = Math.round( TOPONYMES.densite_par_mpx * airePlacementMpx( emprise, zoom ) );

		controler(
			`toponymes z${ zoom } : dénombrement sous le plafond de densité recalculé`,
			( jeux[ zoom ] || [] ).length <= plafond,
			`${ ( jeux[ zoom ] || [] ).length } / ${ plafond } (aire de placement ${ airePlacementMpx( emprise, zoom ).toFixed( 3 ) } Mpx)`,
			'la densité porte sur la bbox du RÉFÉRENTIEL, jamais sur la toile : l\'erreur livre 2,5 fois trop d\'étiquettes'
		);
	}

	// Ces deux contrôles remplacent un contrôle unique dont la plage était dérivée de
	// `zoom_min_etiquettes` : il ne pouvait pas s'opposer à la borne z5–z8, puisqu'il la
	// tenait de la constante même qu'un abaissement aurait déplacée.
	controler(
		`toponymes : aucune étiquette à z${ ZOOMS_SANS_ETIQUETTE_NORMATIFS[ 0 ] }–z${ ZOOMS_SANS_ETIQUETTE_NORMATIFS[ ZOOMS_SANS_ETIQUETTE_NORMATIFS.length - 1 ] } (§3, règle et non réglage)`,
		ZOOMS_SANS_ETIQUETTE_NORMATIFS.every( ( zoom ) => undefined === jeux[ zoom ] || 0 === jeux[ zoom ].length ),
		ZOOMS_SANS_ETIQUETTE_NORMATIFS.map( ( zoom ) => `z${ zoom } : ${ ( jeux[ zoom ] || [] ).length }` ).join( ', ' ),
		'les comptes z5–z8 = 0 sont gelés par le contrat : c\'est le réglage qui leur répond, jamais l\'inverse'
	);

	controler(
		`toponymes : le seuil d'étiquetage réglé vaut le seuil normatif z${ ZOOM_MIN_ETIQUETTES_NORMATIF }`,
		TOPONYMES.zoom_min_etiquettes === ZOOM_MIN_ETIQUETTES_NORMATIF,
		`zoom_min_etiquettes = ${ TOPONYMES.zoom_min_etiquettes }`,
		'abaisser le seuil sans réviser le contrat doit rougir, et non renommer les contrôles qui en dérivent'
	);

	/* --- T10 / C-d — plancher d'impression --------------------------------- */

	controler(
		'toponymes : corps cuit de la statique au-dessus du plancher d\'impression (C-d)',
		( jeux.statique || [] ).every( ( etiquette ) => etiquette.corps_px >= TOPONYMES.corps_min_statique_px ),
		`${ TOPONYMES.corps_statique_px } px ≥ ${ TOPONYMES.corps_min_statique_px } px (218,5 ppp, 8 pt = 24,3 px, arrondi vers le haut)`
	);

	/* --- T13 / C-b — écart minimal sur la statique -------------------------- */

	const statiqueJeu = jeux.statique || [];
	let ecartMin = Infinity;

	for ( let i = 0; i < statiqueJeu.length; i += 1 ) {
		for ( let j = i + 1; j < statiqueJeu.length; j += 1 ) {
			ecartMin = Math.min( ecartMin, ecartBoites( statiqueJeu[ i ].boite_dilatee, statiqueJeu[ j ].boite_dilatee ) );
		}
	}

	controler(
		'toponymes : écart minimal entre boîtes dilatées de la statique (C-b)',
		! Number.isFinite( ecartMin ) || ecartMin >= TOPONYMES.ecart_min_statique_px,
		Number.isFinite( ecartMin ) ? `${ ecartMin } px ≥ ${ TOPONYMES.ecart_min_statique_px } px` : 'moins de deux étiquettes'
	);

	/* --- T11 / C-e — dégagement, mesuré sur le PNG DÉCODÉ (I-71.11) -------- */

	const pixelsStatique = await pixelsDe( statiqueBrut );
	const charbon = versRgb( jetons.get( JETON_CONTOUR ) );
	const encre = versRgb( jetons.get( JETON_ENCRE ) );
	const marge = TOPONYMES.marge_contour_px;
	const estCouleur = ( p, [ r, v, b ] ) =>
		pixelsStatique.data[ p * pixelsStatique.canaux ] === r &&
		pixelsStatique.data[ p * pixelsStatique.canaux + 1 ] === v &&
		pixelsStatique.data[ p * pixelsStatique.canaux + 2 ] === b;

	let contactsCharbon = 0;

	for ( const etiquette of statiqueJeu ) {
		const [ x0, y0, x1, y1 ] = etiquette.boite_dilatee;

		for ( let y = Math.max( 0, y0 - marge ); y < Math.min( pixelsStatique.hauteur, y1 + marge ); y += 1 ) {
			for ( let x = Math.max( 0, x0 - marge ); x < Math.min( pixelsStatique.largeur, x1 + marge ); x += 1 ) {
				if ( estCouleur( y * pixelsStatique.largeur + x, charbon ) ) {
					contactsCharbon += 1;
				}
			}
		}
	}

	controler(
		`toponymes : aucun pixel ${ JETON_CONTOUR } à moins de ${ marge } px d'une boîte, sur le PNG décodé (C-e)`,
		0 === contactsCharbon,
		`${ contactsCharbon } pixel(s) en contact`,
		'le halo protège le texte ; les 25 contours, eux, SONT l\'information'
	);

	/*
	 * Seconde moitié de C-e (arbitrage A-7), et elle n'est pas redondante : dans la
	 * pyramide un nom intérieur à un massif est TOUJOURS occulté par un aplat de
	 * statut ; dans la statique aucun aplat n'est jamais peint (I-9.3). Le même nom
	 * serait donc visible dans un artefact et caché dans l'autre.
	 */
	const projeterStatique = projectionStatique( manifeste.statique.largeur, manifeste.statique.hauteur );
	const polygones = fs.existsSync( CHEMINS.geometrie )
		? JSON.parse( fs.readFileSync( CHEMINS.geometrie, 'utf8' ) ).features.filter( ( feature ) => feature && feature.geometry )
		: [];
	const interieurs = statiqueJeu.filter( ( etiquette ) =>
		polygones.some( ( feature ) => boiteRencontrePolygone( etiquette.boite_dilatee, feature.geometry, projeterStatique ) )
	);

	controler(
		'toponymes : aucune boîte n\'intersecte l\'intérieur d\'un massif (C-e, A-7)',
		0 === interieurs.length,
		`${ statiqueJeu.length } étiquette(s) contrôlée(s) contre ${ polygones.length } polygone(s)${ 0 === interieurs.length ? '' : ` — ${ interieurs.map( ( e ) => e.nom ).join( ', ' ) }` }`,
		'l\'équivalence entre les deux artefacts se mesure sur ce qui est VISIBLE, jamais sur ce qui est cuit'
	);

	/* --- T12 / C-c — couverture d'encre des TOPONYMES, sur le PNG décodé --- */

	/*
	 * L'encre COMPTÉE est celle qui tombe DANS LES BOÎTES D'ÉTIQUETTE, et non celle
	 * de la toile entière.
	 *
	 * L'arbitrage A-5 fondait une mesure absolue sur la prémisse que
	 * `--c-carte-encre` n'a aucun autre consommateur dans la statique. MESURE À
	 * L'APPUI, ELLE EST FAUSSE : la toile SANS étiquette en porte déjà 37 237 px
	 * (1,559 %), parce que `--c-carte-encre` est le plus proche voisin d'une bande de
	 * la rampe d'anticrénelage charbon -> terre et que `PALIERS = 0` y fait tomber la
	 * frange des contours. Ce coût est ANTÉRIEUR à #71.
	 *
	 * Compter dans les boîtes rétablit l'exactitude que A-5 visait, avec le même
	 * plafond et le même dénominateur : C-e interdit tout pixel charbon à moins de
	 * `marge_contour_px` d'une boîte, donc aucune frange de contour n'y entre, et
	 * l'encre des toponymes n'en sort pas.
	 */
	let pixelsEncre = 0;
	let pixelsEncreToile = 0;

	for ( let p = 0; p < pixelsStatique.largeur * pixelsStatique.hauteur; p += 1 ) {
		if ( estCouleur( p, encre ) ) {
			pixelsEncreToile += 1;
		}
	}

	for ( const etiquette of statiqueJeu ) {
		const [ x0, y0, x1, y1 ] = etiquette.boite_dilatee;

		for ( let y = Math.max( 0, y0 ); y < Math.min( pixelsStatique.hauteur, y1 ); y += 1 ) {
			for ( let x = Math.max( 0, x0 ); x < Math.min( pixelsStatique.largeur, x1 ); x += 1 ) {
				if ( estCouleur( y * pixelsStatique.largeur + x, encre ) ) {
					pixelsEncre += 1;
				}
			}
		}
	}

	const couverture = pixelsEncre / ( pixelsStatique.largeur * pixelsStatique.hauteur );

	controler(
		'toponymes : couverture d\'encre des toponymes sur la statique (C-c)',
		couverture <= TOPONYMES.couverture_encre_max,
		`${ pixelsEncre } px dans les boîtes sur ${ pixelsEncreToile } px d'encre au total — ` +
			`${ ( couverture * 100 ).toFixed( 3 ) } % ≤ ${ TOPONYMES.couverture_encre_max * 100 } %`,
		'abaisser `zoom_percu_statique` ou `densite_par_mpx` — c\'est le seul levier qui améliore aussi la lisibilité'
	);

	controler(
		'toponymes : couverture d\'encre identique à celle du manifeste',
		pixelsEncre === toponymes.encre.dans_les_boites && pixelsEncreToile === toponymes.encre.toile_entiere,
		`${ pixelsEncre } / ${ pixelsEncreToile } px`
	);

	/* --- T14 / C-f — texture à 360 px, les deux moitiés -------------------- */

	if ( statiqueJeu.length > 0 ) {
		const cible = Math.round( pixelsStatique.largeur * TOPONYMES.facteur_360 );
		const reduit = await pixelsDe( await sharp( statiqueBrut ).resize( { width: cible, kernel: 'lanczos3' } ).png().toBuffer() );
		const echelle = reduit.largeur / pixelsStatique.largeur;
		const seuilSombre = luma( ...versRgb( jetons.get( JETON_TRAIT ) ) );

		const boites = statiqueJeu.map( ( etiquette ) => [
			Math.floor( etiquette.boite_dilatee[ 0 ] * echelle ),
			Math.floor( etiquette.boite_dilatee[ 1 ] * echelle ),
			Math.ceil( etiquette.boite_dilatee[ 2 ] * echelle ),
			Math.ceil( etiquette.boite_dilatee[ 3 ] * echelle ),
		] );

		const mesures = boites.map( ( [ x0, y0, x1, y1 ], rang ) => {
			let minimum = Infinity;
			let maximum = -Infinity;
			let total = 0;
			let compte = 0;

			for ( let y = Math.max( 0, y0 ); y < Math.min( reduit.hauteur, y1 ); y += 1 ) {
				for ( let x = Math.max( 0, x0 ); x < Math.min( reduit.largeur, x1 ); x += 1 ) {
					const p = ( y * reduit.largeur + x ) * reduit.canaux;
					const valeur = luma( reduit.data[ p ], reduit.data[ p + 1 ], reduit.data[ p + 2 ] );

					minimum = Math.min( minimum, valeur );
					maximum = Math.max( maximum, valeur );
					total += valeur;
					compte += 1;
				}
			}

			return { nom: statiqueJeu[ rang ].nom, plage: maximum - minimum, moyenne: total / compte };
		} );

		const plageMin = Math.min( ...mesures.map( ( mesure ) => mesure.plage ) );
		const moyenneMin = Math.min( ...mesures.map( ( mesure ) => mesure.moyenne ) );

		controler(
			`toponymes : plage de luma à ${ reduit.largeur } px (C-f, survie de l'étiquette)`,
			plageMin >= TOPONYMES.plage_luma_min_360,
			`minimum ${ plageMin.toFixed( 1 ) } ≥ ${ TOPONYMES.plage_luma_min_360 }`,
			'une étiquette qui s\'effondre en gris uniforme perd sa plage la première ; si C-f ne peut pas être tenu, les toponymes sortent de l\'image statique'
		);

		controler(
			`toponymes : moyenne de luma à ${ reduit.largeur } px (C-f, survie de l'étiquette)`,
			moyenneMin >= TOPONYMES.luma_moyenne_min_360,
			`minimum ${ moyenneMin.toFixed( 1 ) } ≥ ${ TOPONYMES.luma_moyenne_min_360 }`
		);

		let sombres = 0;

		for ( let y = 0; y < reduit.hauteur; y += 1 ) {
			for ( let x = 0; x < reduit.largeur; x += 1 ) {
				const p = ( y * reduit.largeur + x ) * reduit.canaux;

				if (
					luma( reduit.data[ p ], reduit.data[ p + 1 ], reduit.data[ p + 2 ] ) < seuilSombre &&
					! boites.some( ( [ x0, y0, x1, y1 ] ) => x >= x0 && x < x1 && y >= y0 && y < y1 )
				) {
					sombres += 1;
				}
			}
		}

		const reference360 = toponymes.texture ? toponymes.texture.pixels_sombres_sans_etiquettes : 0;
		const recul = 0 === reference360 ? 1 : 1 - sombres / reference360;

		controler(
			`toponymes : les pixels sombres ne reculent pas à ${ reduit.largeur } px (C-f, non-noyage)`,
			recul <= TOPONYMES.recul_sombres_max_360,
			`${ sombres } contre ${ reference360 } sans étiquettes, recul ${ ( recul * 100 ).toFixed( 1 ) } % ≤ ${ TOPONYMES.recul_sombres_max_360 * 100 } %`,
			'le vrai risque n\'est pas que les noms soient illisibles — c\'est qu\'ils NOIENT les 25 contours'
		);

		controler(
			'toponymes : mesures de texture identiques à celles du manifeste',
			Boolean( toponymes.texture ) && toponymes.texture.etiquettes.length === mesures.length,
			`${ mesures.length } étiquette(s)`
		);
	}

	/* --- C-g — le cœur d'encre, sur des tuiles DÉCODÉES -------------------- */

	/*
	 * LE DÉNOMINATEUR EST `encre + trait`, ET NON « tous les pixels non-fond ».
	 *
	 * La question posée par C-g est étroite : le glyphe a-t-il un CŒUR d'encre à
	 * 6,82:1, ou s'est-il entièrement quantifié en `--c-carte-trait` à 4,17:1 ? Les
	 * deux seules couleurs qu'un glyphe puisse produire sont donc les deux seules qui
	 * appartiennent au dénominateur.
	 *
	 * « Non-fond », pris à la lettre, compte AUSSI le FOND DE CARTE sous la boîte —
	 * terre, végétation, eau — qui n'a rien à voir avec le glyphe. Mesuré : à z9,
	 * « Aix-en-Provence » porte 373 px d'encre et 367 de trait (soit 50 % d'un vrai
	 * cœur), mais 403 px de terre et 279 de végétation sous la boîte suffisent à
	 * faire tomber le rapport « non-fond » à 26 %. Le contrôle rougissait sur la
	 * géographie, pas sur la typographie.
	 *
	 * `COEUR_ENCRE_MIN` est INCHANGÉ. Les pixels de `trait` du fond de carte qui
	 * passent entre deux lettres restent comptés au dénominateur : c'est
	 * conservateur, donc sans danger.
	 */
	if ( fs.existsSync( racineVersion ) ) {
		const trait = versRgb( jetons.get( JETON_TRAIT ) );
		const boitesControlees = [];
		const sansCoeur = [];

		for ( const zoom of [ ...ZOOMS_RESOLUS, ZOOM_MAX ] ) {
			for ( const etiquette of ( jeux[ zoom ] || [] ).slice( 0, ECHANTILLON_BOITES ) ) {
				// eslint-disable-next-line no-await-in-loop
				const zone = await lireZone( zoom, etiquette.boite );

				if ( null === zone ) {
					continue;
				}

				let coeur = 0;
				let frange = 0;

				for ( let p = 0; p < zone.largeur * zone.hauteur; p += 1 ) {
					const r = zone.data[ p * 3 ];
					const v = zone.data[ p * 3 + 1 ];
					const b = zone.data[ p * 3 + 2 ];

					if ( r === encre[ 0 ] && v === encre[ 1 ] && b === encre[ 2 ] ) {
						coeur += 1;
					} else if ( r === trait[ 0 ] && v === trait[ 1 ] && b === trait[ 2 ] ) {
						frange += 1;
					}
				}

				const rapport = 0 === coeur + frange ? 0 : coeur / ( coeur + frange );

				boitesControlees.push( rapport );

				if ( rapport < COEUR_ENCRE_MIN ) {
					sansCoeur.push( `z${ zoom } ${ etiquette.nom } (${ ( rapport * 100 ).toFixed( 0 ) } %)` );
				}
			}
		}

		controler(
			`toponymes : chaque étiquette a un cœur d'encre à ${ enRapport( contrasteHalo ) } (C-g, I-71.13)`,
			0 === sansCoeur.length && boitesControlees.length > 0,
			`${ boitesControlees.length } boîte(s) décodée(s), rapport encre/(encre+trait) de ` +
				`${ ( Math.min( ...boitesControlees ) * 100 ).toFixed( 0 ) } % à ${ ( Math.max( ...boitesControlees ) * 100 ).toFixed( 0 ) } %` +
				`${ 0 === sansCoeur.length ? `, toutes ≥ ${ COEUR_ENCRE_MIN * 100 } %` : ` — ${ sansCoeur.join( ', ' ) }` }`,
			'sans ce contrôle la ligne « 4,5:1 » du contrat serait une intention : la frange d\'anticrénelage est en --c-carte-trait, à 4,17:1, et ce n\'est pas le texte'
		);
	}
}

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

	/*
	 * T9 / I-71.5 — AUCUN NOM DE MASSIF N'EST CUIT, dans aucun artefact. Les 25 noms
	 * sont relus PAR PHP, dans le référentiel qui fait autorité, et non recopiés
	 * ici : une liste recopiée validerait n'importe quel renommage.
	 *
	 * C'est la différence entre « nous avons décidé de ne pas cuire les noms de
	 * massifs » et « le build ne peut pas en cuire un ».
	 */
	const normaliser = ( chaine ) =>
		String( chaine )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.toLowerCase()
			.trim();
	const nomsMassifs = new Set(
		Object.values( lectureReferentiel.donnees.massifs || {} )
			.flatMap( ( massif ) => [ massif && massif.libelle, massif && massif.source && massif.source.nom_massif ] )
			.filter( Boolean )
			.map( normaliser )
	);
	const cuits = manifeste.toponymes
		? Object.values( manifeste.toponymes.jeux ).flat().map( ( etiquette ) => etiquette.nom )
		: [];
	const collisions = [ ...new Set( cuits.filter( ( nom ) => nomsMassifs.has( normaliser( nom ) ) ) ) ];

	controler(
		'toponymes : aucun nom de massif cuit, nulle part (I-71.5)',
		0 === collisions.length && nomsMassifs.size > 0,
		0 === nomsMassifs.size
			? 'aucun nom de massif relu par PHP — le contrôle serait vide'
			: `${ nomsMassifs.size } noms de massifs relus par PHP, ${ new Set( cuits ).size } toponymes cuits distincts${ 0 === collisions.length ? '' : ` — ${ collisions.join( ', ' ) }` }`,
		'dans la pyramide il serait occulté par construction ; le cuire dans la seule statique ferait diverger les deux artefacts'
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

controlerDerive( 'référence : version de fontkit', reference.outillage.fontkit, manifeste.outillage.fontkit );

if ( reference.toponymes && manifeste.toponymes ) {
	for ( const cle of Object.keys( reference.toponymes ) ) {
		const mesure =
			'rejets' === cle
				? manifeste.toponymes.rejets.length
				: 'source' === cle
					? manifeste.toponymes.source.retenus
					: ( manifeste.toponymes.jeux[ cle ] || [] ).length;

		controlerDerive( `référence : toponymes ${ cle }`, reference.toponymes[ cle ], mesure );
	}
} else {
	echecs.push( `référence : bloc toponymes absent de reference.json ou du manifeste ; ${ REGENERER }` );
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

if ( notes.length > 0 ) {
	process.stdout.write( `\nMESURES, NON ASSERTÉES :\n  - ${ notes.join( '\n  - ' ) }\n` );
}

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
