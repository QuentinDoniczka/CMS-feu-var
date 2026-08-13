/**
 * Socle commun des trois commandes du fond de carte.
 *
 *   npm run recuperer   — SEUL fichier réseau du dépôt (recuperer.mjs)
 *   npm run construire  — toujours hors ligne (construire.mjs)
 *   npm run verifier    — recette, ne réécrit rien (verifier.mjs)
 *
 * Ce module n'a AUCUN effet de bord au chargement : ni écriture, ni réseau, ni
 * lecture de fichier. Il n'expose que des constantes et des fonctions pures ou
 * explicitement appelées. C'est ce qui permet aux trois scripts de l'importer
 * sans qu'aucun n'en déclenche un autre — le patron `verifier.mjs` important
 * `importer.mjs` du domaine « massifs » ne tient qu'à deux scripts ; à trois, un
 * socle séparé est la seule forme qui garde UNE SEULE liste de chemins.
 *
 * Une seconde liste de chemins recopiée ailleurs finirait par désigner un autre
 * fichier que celui qu'écrit le build : une recette verte sur le mauvais fichier
 * est pire que pas de recette.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE_MODULE = path.dirname( fileURLToPath( import.meta.url ) );

/** Racine du répertoire de build. */
export const RACINE = RACINE_MODULE;

/** Racine de l'extension. */
export const EXTENSION = path.resolve( RACINE, '../../../..' );

/** Racine du dépôt : sert à composer des chemins lisibles dans les messages. */
export const DEPOT = path.resolve( EXTENSION, '../../..' );

/** Racine du thème. Couplage de BUILD uniquement — jamais à l'exécution (interdit 12 du contrat #9). */
export const THEME = path.join( DEPOT, 'wp-content/themes/massifs' );

/**
 * Chemins des entrées et des sorties, définis une seule fois.
 *
 * Le build LIT deux fichiers du thème (`tokens.css`) et en ÉCRIT un
 * (`carte-statique.png`). Ce couplage est symétrique, borné au build, et
 * délibéré : voir l'arbitrage A-3 du contrat #9.
 */
export const CHEMINS = {
	// Entrées.
	referentiel: path.join( EXTENSION, 'data/massifs-13.php' ),
	geometrie: path.join( EXTENSION, 'data/massifs-13.geometrie.json' ),
	tokens: path.join( THEME, 'assets/css/tokens.css' ),
	// Amorce du module de lecture : la recette charge la SURFACE PUBLIQUE, pas
	// seulement les métadonnées — un renommage de clé casserait le thème sans
	// toucher un octet d'artefact.
	module: path.join( EXTENSION, 'includes/ingest/tuiles/module.php' ),
	archive: path.join( RACINE, 'source/osm-13.json' ),
	manifeste_source: path.join( RACINE, 'source/manifeste.json' ),
	// Sorties.
	tuiles: path.join( EXTENSION, 'data/tuiles' ),
	metadonnees: path.join( EXTENSION, 'data/tuiles/fond-13.php' ),
	statique: path.join( THEME, 'assets/img/carte-statique.png' ),
	// `data/` est servi au navigateur, `build/` ne l'est jamais (interdit 5 du
	// contrat #20) : l'empreinte de référence et le manifeste de build restent ici.
	manifeste: path.join( RACINE, 'manifeste.json' ),
	reference: path.join( RACINE, 'reference.json' ),
	mapshaper: path.join( RACINE, 'node_modules/mapshaper/bin/mapshaper' ),
	mapshaper_manifeste: path.join( RACINE, 'node_modules/mapshaper/package.json' ),
};

/** Version du schéma du fichier de métadonnées lu par le module PHP. */
export const SCHEMA = 1;

/** Bornes de la pyramide. z12 sert la netteté sur écran dense, jamais un cran de zoom (F-11). */
export const ZOOM_MIN = 5;
export const ZOOM_MAX = 12;

/** Côté d'une tuile, en pixels. */
export const TAILLE_TUILE = 256;

/** Format des tuiles et de l'image statique. Gelé (contrat #9 §1.1 et §2). */
export const FORMAT = 'png';

/** Largeur cible de l'image statique. La HAUTEUR est dérivée de la bbox projetée, jamais choisie (A-10). */
export const LARGEUR_STATIQUE = 1600;

/** Plafond dur de l'image statique, en octets transférés (contrat #9 §2). */
export const PLAFOND_STATIQUE_OCTETS = 150 * 1024;

/** Modes de build. Énumération fermée : tout ce qui n'est pas `complet` est `degrade`. */
export const MODE_COMPLET = 'complet';
export const MODE_DEGRADE = 'degrade';

/**
 * Les six jetons de fond de carte, avec la valeur que ce build CUIT dans les
 * octets, plus l'encre des contours de l'image statique.
 *
 * Invariant I-9.7 : le build relit ces jetons dans `tokens.css` et sort en code
 * ≠ 0 sur toute divergence. C'est ce qui rend D-01 opposable et empêche un
 * `filter: grayscale()` de revenir par la fenêtre — le monochrome est cuit ici,
 * jamais dans le navigateur.
 *
 * `--c-charbon` s'y ajoute parce que le build le CUIT aussi (contours des 25
 * massifs sur l'image statique) : un jeton cuit sans être contrôlé serait
 * exactement le trou que I-9.7 ferme pour les six autres.
 */
export const JETONS_CARTE = Object.freeze( {
	'--c-carte-fond': '#E6E7E1',
	'--c-carte-terre': '#DEDFD9',
	'--c-carte-vegetation': '#D6DBD3',
	'--c-carte-eau': '#CBD5D8',
	'--c-carte-trait': '#B4B7AC',
	'--c-carte-encre': '#4A4E48',
} );

/** Encre des contours de massifs sur l'image statique. */
export const JETON_CONTOUR = '--c-charbon';

/** Valeur attendue de `--c-charbon`, contrôlée au même titre que les six. */
export const VALEUR_CONTOUR = '#1A1C19';

/**
 * Jetons des deux aplats de statut officiels.
 *
 * Leurs VALEURS ne sont jamais écrites ici : elles sont relues dans `tokens.css`
 * au moment du contrôle. Les inscrire en dur ferait rendre un résultat au `grep`
 * de revue du §12 du contrat #9, qui exige zéro occurrence hors `tokens.css`.
 */
export const JETONS_STATUT = Object.freeze( [ '--statut-autorise', '--statut-interdit' ] );

/**
 * Couches du fond, dans l'ordre de PEINTURE.
 *
 * `terre` est le polygone départemental : il porte à la fois l'aplat de terre et
 * le trait de limite administrative, en une seule passe. Une seconde couche
 * « limite » recopierait la même géométrie pour la retracer par-dessus — deux
 * représentations d'un même contour finissent par diverger.
 *
 * `--c-carte-encre` n'a AUCUN consommateur en v1 : les toponymes sont écartés
 * (arbitrage A-9, `OUVERT`). Absence délibérée, pas défaut.
 */
export const COUCHES = Object.freeze( [
	{ nom: 'terre', surfacique: true, remplissage: '--c-carte-terre', trait: '--c-carte-trait' },
	{ nom: 'vegetation', surfacique: true, remplissage: '--c-carte-vegetation', trait: null },
	{ nom: 'eau', surfacique: true, remplissage: '--c-carte-eau', trait: null },
	{ nom: 'routes', surfacique: false, remplissage: null, trait: '--c-carte-trait' },
] );

/**
 * Couches retenues pour l'IMAGE STATIQUE — mitigation (2) du §2 du contrat #9.
 *
 * La mitigation (1) ne suffit pas : à 7 couleurs, l'image complète pèse encore
 * 177 824 o pour un plafond de 153 600 o. Le §2 impose alors « supprimer les
 * couches de fond les moins informatives », AVANT de toucher à la largeur. Poids
 * mesurés, à 1600 px et 7 couleurs :
 *
 *   terre + vegetation + eau + routes -> 177 824 o
 *   sans routes                       -> 164 709 o
 *   sans routes ni vegetation         -> 142 464 o   RETENU
 *
 * L'ordre de retrait n'est pas arbitraire. `routes` part la première : le §4.2 de
 * `MASTER.md` dit d'elle qu'elle n'est « jamais porteur d'une limite qui compte »
 * — c'est une trame d'orientation, rien de plus. `vegetation` part ensuite parce
 * que l'arbitrage A-9 du contrat nomme lui-même ce sur quoi l'orientation repose :
 * « la forme du littoral, l'Étang de Berre et les 25 contours ». `eau` et `terre`
 * sont donc les deux couches expressément portantes, et elles restent.
 *
 * Ce retrait ne touche PAS la pyramide, dont les tuiles pèsent ~2 Ko pièce : le
 * plafond de 150 Ko ne porte que sur l'image statique.
 *
 * Point de licence, décisif : `terre` et `eau` viennent toutes deux d'OSM.
 * L'image continue donc de porter de la donnée OpenStreetMap, et l'attribution
 * posée dessous reste vraie. Une image réduite aux seuls contours DDTM créditée
 * d'OSM serait « une affirmation fausse » (arbitrage A-2, `footer.php` l. 13-15).
 */
export const COUCHES_STATIQUE = Object.freeze( [ 'terre', 'eau' ] );

/**
 * Sélecteurs Overpass, par couche. Recopiés tels quels dans le manifeste de source.
 *
 * `routes` s'arrête à `primary` et n'inclut pas `secondary` : `--c-carte-trait`
 * n'est « jamais porteur d'une limite qui compte » (§4.2 de `MASTER.md`), la
 * voirie n'est ici qu'une trame d'orientation. Les 12 000 voies secondaires du
 * département triplent l'encre sans rien ajouter à l'orientation à z ≤ 12, et
 * contredisent le registre du §1 de `MASTER.md`.
 */
export const SELECTEURS = Object.freeze( {
	terre: 'relation["boundary"="administrative"]["admin_level"="6"]["ref"="13"]',
	eau: '(way["natural"="water"];relation["natural"="water"];way["waterway"="riverbank"];)',
	vegetation:
		'(way["landuse"="forest"];relation["landuse"="forest"];way["natural"="wood"];relation["natural"="wood"];way["natural"="scrub"];relation["natural"="scrub"];)',
	routes: '(way["highway"~"^(motorway|trunk|primary)$"];)',
} );

/**
 * Points d'accès Overpass essayés, dans l'ordre.
 *
 * L'instance principale répond `429` ou `504` sous charge ; sans repli, la
 * récupération deviendrait un jeu de patience. Le point d'accès RÉELLEMENT
 * utilisé est consigné dans le manifeste de source — on ne présente jamais un
 * miroir pour l'instance principale.
 */
export const POINTS_ACCES = Object.freeze( [
	'https://overpass-api.de/api/interpreter',
	'https://overpass.kumi.systems/api/interpreter',
] );

/** En-tête d'identification, nommant le projet et son dépôt (politique d'usage de l'OSMF). */
export const AGENT = 'massifs-fond-de-carte/1.0 (+https://github.com/QuentinDoniczka/CMS-feu-var)';

/**
 * Normalisation appliquée à l'archive, et pourquoi chaque étape est sans effet
 * sur un seul pixel rendu.
 *
 *   - `decimales` 5 (~1,1 m) : la tuile la plus fine, z12, vaut ~27,7 m/px ;
 *   - `intervalle_m` 14 (~0,5 px à z12) : sous le pixel du zoom le plus fin ;
 *   - `aire_min_m2` 2000 (~45 x 45 m, soit ~1,6 x 1,6 px à z12) : au-dessous, un
 *     polygone n'occupe pas deux pixels et ne se distingue pas du bruit ;
 *   - `clip` au département : hors du département, la carte est UNIFORMÉMENT
 *     `--c-carte-fond` (§4.2 de `MASTER.md`). Les octets retirés ne pouvaient donc
 *     influencer aucun pixel — c'est ce qui rend le retrait démontrable et non
 *     seulement raisonnable, et c'est ce qui ramène l'archive sous le plafond de
 *     commitabilité (arbitrage A-8, §11 du brief).
 */
export const NORMALISATION = Object.freeze( {
	decimales: 5,
	intervalle_m: 14,
	aire_min_m2: 2000,
	clip: 'terre',
} );

/**
 * Bornes de vraisemblance de la charge Overpass, par couche, en nombre
 * d'éléments RETOURNÉS avant toute normalisation.
 *
 * POURQUOI DES DÉNOMBREMENTS ET PAS UN CONTRÔLE DE SYNTAXE : une charge Overpass
 * tronquée par timeout rend un JSON syntaxiquement VALIDE mais amputé. Aucun
 * `JSON.parse` ne l'attrape. Seuls des dénombrements le font.
 *
 * Planchers à environ la moitié de l'extraction de référence, plafonds à environ
 * le triple : assez larges pour absorber l'évolution normale d'OSM, assez serrés
 * pour qu'une charge amputée sorte de l'intervalle. Ce ne sont pas des seuils à
 * desserrer pour faire passer une récupération.
 */
export const BORNES_OSM = Object.freeze( {
	terre: { plancher: 1, plafond: 1 },
	eau: { plancher: 2000, plafond: 15000 },
	vegetation: { plancher: 5000, plafond: 40000 },
	routes: { plancher: 4000, plafond: 30000 },
} );

/**
 * Débordement toléré, en degrés, entre l'emprise du référentiel et la bbox du
 * département extrait.
 *
 * Le contrôle n'est PAS une égalité : le département déborde largement l'emprise
 * à l'ouest (Camargue) et la mord de 0,0001° au sud. Ce qui est contrôlé, c'est
 * que le département RECOUVRE l'emprise sur ses quatre bords à cette tolérance
 * près — c'est ce qui attrape un polygone départemental amputé de la moitié de
 * ses membres, cas qui rendrait une côte fausse sans rien casser d'autre.
 */
export const DEBORDEMENT_MAX_DEG = 0.02;

/** Bornes de taille de l'archive source, en octets. Au-delà, elle cesse d'être commitable (A-8). */
export const ARCHIVE_OCTETS_MIN = 500 * 1024;
export const ARCHIVE_OCTETS_MAX = 6 * 1024 * 1024;

/**
 * Règles de dessin dérivées de la RÉSOLUTION, jamais d'une table de zooms.
 *
 *   - `seuil_entite_px` — une entité surfacique de moins de 2 x 2 pixels ne se
 *     distingue pas du bruit : elle est écartée du zoom considéré. La surface
 *     minimale se recalcule à chaque zoom depuis les mètres par pixel, elle n'est
 *     donc écrite nulle part en dur ;
 *   - `routes_mpp_max` — au-delà de 250 m/px, le réseau routier départemental se
 *     referme en gribouillis continu : le trait cesse d'orienter et devient du
 *     bruit, à rebours du §1 de `MASTER.md`. Les routes ne sont pas peintes aux
 *     zooms plus larges ;
 *   - `simplification` — la tolérance vaut un demi-pixel du zoom rendu, jamais
 *     moins que la résolution de l'archive : simplifier plus fin que la donnée
 *     n'ajoute rien et coûte le double d'octets.
 */
export const DESSIN = Object.freeze( {
	seuil_entite_px: 2,
	routes_mpp_max: 250,
	trait_px: 1,
	contour_px: 2,
} );

/**
 * Attribution §9 du brief.
 *
 * `phrase` est la chaîne du §9 SEULE ET VERBATIM. Rien n'y est appendu : la
 * condition « + mention de la source de l'extrait le cas échéant » n'est pas
 * levée, et le §9 du contrat #9 la laisse `OUVERT`. Overpass est un service
 * d'interrogation, pas un redistributeur revendiquant un crédit propre ; l'ODbL
 * exige d'attribuer OpenStreetMap, ce que cette phrase fait. Le fait brut vit
 * dans `faits.canal`, citable sur « La démarche » le jour venu.
 */
export const ATTRIBUTION = Object.freeze( {
	phrase: '© les contributeurs d\'OpenStreetMap',
	lien_licence: 'https://www.openstreetmap.org/copyright',
	licence_nom: 'Open Database License',
	licence_version: '1.0',
	licence_url: 'https://opendatacommons.org/licenses/odbl/1-0/',
	rendu: 'monochrome, cuit à la génération',
} );

/** Erreur d'arrêt propre : message lisible, aucune trace de pile. */
export class Arret extends Error {}

/** @param {Buffer|string} donnees Données à empreindre. */
export function sha256( donnees ) {
	return createHash( 'sha256' ).update( donnees ).digest( 'hex' );
}

/** Jeton de version : les 8 premiers hexadécimaux d'une empreinte. Segment de CHEMIN, jamais une query. */
export function jetonVersion( empreinte ) {
	return empreinte.slice( 0, 8 );
}

/** Majeur de Node, consigné comme contexte de diagnostic, jamais comme critère. */
export function nodeMajeur() {
	return Number.parseInt( process.versions.node.split( '.' )[ 0 ], 10 );
}

/** Version de mapshaper réellement installée, lue dans son manifeste. */
export function versionMapshaper() {
	if ( ! fs.existsSync( CHEMINS.mapshaper_manifeste ) ) {
		throw new Arret( 'mapshaper est absent : lancer `npm ci` dans includes/ingest/tuiles/build/.' );
	}

	return JSON.parse( fs.readFileSync( CHEMINS.mapshaper_manifeste, 'utf8' ) ).version;
}

/** Chemin ramené à la racine du dépôt, séparateurs POSIX : lisible et copiable dans un message. */
export function relatifAuDepot( chemin ) {
	return path.relative( DEPOT, chemin ).split( path.sep ).join( '/' );
}

/**
 * JSON canonique : clés triées à toute profondeur, aucun espace.
 *
 * C'est de CETTE sérialisation qu'est dérivée la version. Sans tri des clés,
 * l'ordre d'insertion d'un objet ferait changer la version sans qu'un seul octet
 * de tuile ait bougé — une URL neuve pour un contenu identique, donc un cache
 * invalidé pour rien et une empreinte qui ne veut plus rien dire.
 */
export function jsonCanonique( valeur ) {
	if ( null === valeur || 'object' !== typeof valeur ) {
		return JSON.stringify( valeur );
	}

	if ( Array.isArray( valeur ) ) {
		return `[${ valeur.map( jsonCanonique ).join( ',' ) }]`;
	}

	return `{${ Object.keys( valeur )
		.sort()
		.map( ( cle ) => `${ JSON.stringify( cle ) }:${ jsonCanonique( valeur[ cle ] ) }` )
		.join( ',' ) }}`;
}

/* -------------------------------------------------------------------------- */
/* Lecture des entrées                                                         */
/* -------------------------------------------------------------------------- */

/**
 * Jetons de couleur littéraux déclarés dans `tokens.css`.
 *
 * Seules les valeurs hexadécimales LITTÉRALES sont retenues : un jeton défini par
 * `var(--autre)` n'est pas une couleur, c'est un renvoi, et le build ne résout
 * pas la cascade CSS. Un tel jeton est donc traité comme absent — bruyamment.
 *
 * @param {string} chemin Chemin de `tokens.css`.
 * @return {Map<string,string>} Nom du jeton -> `#RRGGBB` en majuscules.
 */
export function lireJetons( chemin ) {
	if ( ! fs.existsSync( chemin ) ) {
		throw new Arret( `tokens.css introuvable : ${ relatifAuDepot( chemin ) }` );
	}

	const source = fs.readFileSync( chemin, 'utf8' );
	const jetons = new Map();
	const motif = /(--[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/g;
	let trouve = motif.exec( source );

	while ( null !== trouve ) {
		// Première déclaration retenue : `:root` précède les redéfinitions de
		// contexte (`.sur-sombre`), et c'est la valeur de `:root` qui fait foi.
		if ( ! jetons.has( trouve[ 1 ] ) ) {
			jetons.set( trouve[ 1 ], trouve[ 2 ].toUpperCase() );
		}

		trouve = motif.exec( source );
	}

	return jetons;
}

/**
 * Contrôle des jetons cuits dans les octets — invariant I-9.7.
 *
 * @param {Map<string,string>} jetons Jetons lus dans `tokens.css`.
 * @return {string[]} Divergences, chacune nommant le jeton, la valeur lue et la valeur attendue.
 */
export function divergencesJetons( jetons ) {
	const attendus = { ...JETONS_CARTE, [ JETON_CONTOUR ]: VALEUR_CONTOUR };
	const divergences = [];

	for ( const [ nom, attendu ] of Object.entries( attendus ) ) {
		const lu = jetons.get( nom );

		if ( undefined === lu ) {
			divergences.push( `${ nom } — absent ou renommé dans tokens.css ; attendu ${ attendu }` );
			continue;
		}

		if ( lu !== attendu ) {
			divergences.push( `${ nom } — lu ${ lu }, attendu ${ attendu }` );
		}
	}

	return divergences;
}

/**
 * Emprise du référentiel, lue dans `data/massifs-13.php`.
 *
 * POURQUOI UN PARSEUR ET PAS `php -r` : le build doit tourner sans binaire PHP
 * sur l'hôte (le cas courant sous Windows), et rendre la génération du fond
 * dépendante d'une stack PHP serait un prérequis de plus à la promesse §11 du
 * brief. Le fichier lu est MACHINE-GÉNÉRÉ par un rendu stable, ce qui rend le
 * repérage sûr — et la recette relit la même emprise PAR PHP et compare : la
 * lecture fragile est ainsi contrôlée par la lecture qui fait autorité.
 *
 * Aucune coordonnée n'est écrite ici (interdit 11 du contrat #2 appliqué au build).
 *
 * @param {string} chemin Chemin de `data/massifs-13.php`.
 * @return {{ouest:number,sud:number,est:number,nord:number}}
 */
export function lireEmprise( chemin ) {
	if ( ! fs.existsSync( chemin ) ) {
		throw new Arret( `Référentiel introuvable : ${ relatifAuDepot( chemin ) }` );
	}

	const source = fs.readFileSync( chemin, 'utf8' );
	const debutEmprise = source.indexOf( "'emprise'" );

	if ( -1 === debutEmprise ) {
		throw new Arret( `Bloc 'emprise' introuvable dans ${ relatifAuDepot( chemin ) }` );
	}

	const debutBbox = source.indexOf( "'bbox'", debutEmprise );
	const fin = source.indexOf( ')', source.indexOf( 'array(', debutBbox ) );

	if ( -1 === debutBbox || -1 === fin ) {
		throw new Arret( `Bloc 'emprise' => 'bbox' illisible dans ${ relatifAuDepot( chemin ) }` );
	}

	const bloc = source.slice( debutBbox, fin );
	const bbox = {};

	for ( const borne of [ 'ouest', 'sud', 'est', 'nord' ] ) {
		const trouve = bloc.match( new RegExp( `'${ borne }'\\s*=>\\s*(-?\\d+(?:\\.\\d+)?)` ) );

		if ( null === trouve ) {
			throw new Arret( `Borne '${ borne }' absente de l'emprise du référentiel.` );
		}

		bbox[ borne ] = Number.parseFloat( trouve[ 1 ] );
	}

	return controlerBbox( bbox, "l'emprise du référentiel" );
}

/**
 * Une emprise est-elle exploitable ?
 *
 * @param {object} bbox  Emprise candidate.
 * @param {string} objet Désignation, pour le message d'erreur.
 * @return {{ouest:number,sud:number,est:number,nord:number}}
 */
export function controlerBbox( bbox, objet ) {
	for ( const borne of [ 'ouest', 'sud', 'est', 'nord' ] ) {
		if ( ! Number.isFinite( bbox[ borne ] ) ) {
			throw new Arret( `Borne '${ borne }' non numérique dans ${ objet }.` );
		}
	}

	if ( bbox.ouest >= bbox.est || bbox.sud >= bbox.nord ) {
		throw new Arret( `Emprise dégénérée dans ${ objet } : ouest >= est ou sud >= nord.` );
	}

	if ( Math.abs( bbox.ouest ) > 180 || Math.abs( bbox.est ) > 180 || Math.abs( bbox.sud ) > 85 || Math.abs( bbox.nord ) > 85 ) {
		throw new Arret( `Emprise hors des bornes Web Mercator dans ${ objet }.` );
	}

	return { ouest: bbox.ouest, sud: bbox.sud, est: bbox.est, nord: bbox.nord };
}

/* -------------------------------------------------------------------------- */
/* mapshaper                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * Lance mapshaper, à la version DÉJÀ présente dans le dépôt.
 *
 * Le simplificateur du projet est mapshaper, épinglé par le domaine « massifs ».
 * En faire entrer un second produirait deux géométries légèrement différentes
 * pour le même contour selon le chemin de code qui l'a produit.
 *
 * @param {string[]} arguments_ Arguments de ligne de commande.
 * @return {string[]} Argv réellement exécuté, chemins ramenés à `build/`.
 */
export function mapshaper( arguments_ ) {
	if ( ! fs.existsSync( CHEMINS.mapshaper ) ) {
		throw new Arret( 'mapshaper est absent : lancer `npm ci` dans includes/ingest/tuiles/build/.' );
	}

	const complets = [ CHEMINS.mapshaper, ...arguments_ ];
	const execution = spawnSync( process.execPath, complets, { encoding: 'utf8', maxBuffer: 1024 * 1024 * 256 } );

	if ( 0 !== execution.status ) {
		throw new Arret( `mapshaper a échoué : ${ execution.stderr || execution.stdout }` );
	}

	// Le chemin absolu du binaire node de la machine n'a rien à faire dans un
	// artefact versionné : il produirait une dérive fantôme à chaque changement
	// de poste.
	return [
		'node',
		...complets.map( ( argument ) =>
			path.isAbsolute( argument ) ? path.relative( RACINE, argument ).split( path.sep ).join( '/' ) : argument
		),
	];
}

/** Écrit une FeatureCollection de travail. */
export function ecrireFc( chemin, geometries ) {
	fs.writeFileSync(
		chemin,
		JSON.stringify( {
			type: 'FeatureCollection',
			features: geometries.map( ( geometry ) => ( { type: 'Feature', properties: {}, geometry } ) ),
		} )
	);
}

/**
 * Relit une sortie mapshaper et n'en garde que les géométries.
 *
 * Les deux formes sont acceptées parce que mapshaper émet l'une OU l'autre selon
 * qu'il reste des attributs : nos couches n'en portent aucun — la couche EST
 * l'information —, il rend donc une `GeometryCollection`.
 */
export function lireFc( chemin ) {
	const brut = JSON.parse( fs.readFileSync( chemin, 'utf8' ) );

	if ( brut && 'FeatureCollection' === brut.type && Array.isArray( brut.features ) ) {
		return brut.features.map( ( feature ) => feature.geometry ).filter( Boolean );
	}

	if ( brut && 'GeometryCollection' === brut.type && Array.isArray( brut.geometries ) ) {
		return brut.geometries.filter( Boolean );
	}

	throw new Arret( `Ni FeatureCollection ni GeometryCollection dans ${ relatifAuDepot( chemin ) }` );
}

/* -------------------------------------------------------------------------- */
/* Projection Web Mercator sphérique et grille de tuiles                       */
/* -------------------------------------------------------------------------- */

const RADIAN = Math.PI / 180;

/** Abscisse normalisée [0,1] en Web Mercator sphérique. */
export function normX( lon ) {
	return ( lon + 180 ) / 360;
}

/** Ordonnée normalisée [0,1] en Web Mercator sphérique, origine en haut. */
export function normY( lat ) {
	const sinus = Math.sin( lat * RADIAN );

	return 0.5 - Math.log( ( 1 + sinus ) / ( 1 - sinus ) ) / ( 4 * Math.PI );
}

/** Longitude du bord gauche de la colonne de tuiles `x` au zoom `z`. */
export function lonDeTuile( x, z ) {
	return ( x / Math.pow( 2, z ) ) * 360 - 180;
}

/** Latitude du bord haut de la ligne de tuiles `y` au zoom `z`. */
export function latDeTuile( y, z ) {
	return Math.atan( Math.sinh( Math.PI * ( 1 - ( 2 * y ) / Math.pow( 2, z ) ) ) ) / RADIAN;
}

/**
 * Grille de tuiles couvrant une emprise à un zoom donné.
 *
 * Aucun compte de tuiles n'est codé en dur nulle part : tout se recalcule ici,
 * depuis la seule emprise du référentiel.
 */
export function grille( bbox, z ) {
	const cote = Math.pow( 2, z );
	const x0 = Math.floor( normX( bbox.ouest ) * cote );
	const x1 = Math.floor( normX( bbox.est ) * cote );
	const y0 = Math.floor( normY( bbox.nord ) * cote );
	const y1 = Math.floor( normY( bbox.sud ) * cote );

	return {
		zoom: z,
		x0,
		x1,
		y0,
		y1,
		colonnes: x1 - x0 + 1,
		lignes: y1 - y0 + 1,
		nombre: ( x1 - x0 + 1 ) * ( y1 - y0 + 1 ),
		largeur_px: ( x1 - x0 + 1 ) * TAILLE_TUILE,
		hauteur_px: ( y1 - y0 + 1 ) * TAILLE_TUILE,
	};
}

/** Les grilles de tous les zooms de la pyramide, du plus large au plus fin. */
export function grilles( bbox ) {
	const sortie = [];

	for ( let z = ZOOM_MIN; z <= ZOOM_MAX; z += 1 ) {
		sortie.push( grille( bbox, z ) );
	}

	return sortie;
}

/**
 * Emprise géographique réellement couverte par une grille.
 *
 * C'est la valeur publiée en `bbox` par `massifs_fond_de_carte()`, prise sur la
 * grille du zoom LE PLUS FIN : c'est le plus petit sur-ensemble aligné sur la
 * grille parmi les huit zooms, et toutes les grilles plus larges le contiennent.
 * Elle sert à borner la couche (`bounds`), jamais à cadrer la vue initiale (F-13).
 */
export function bboxDeGrille( g ) {
	return {
		ouest: lonDeTuile( g.x0, g.zoom ),
		sud: latDeTuile( g.y1 + 1, g.zoom ),
		est: lonDeTuile( g.x1 + 1, g.zoom ),
		nord: latDeTuile( g.y0, g.zoom ),
	};
}

/** Résolution au sol, en mètres par pixel, à la latitude médiane de l'emprise. */
export function metresParPixel( bbox, z ) {
	const latitude = ( bbox.sud + bbox.nord ) / 2;

	return ( 2 * Math.PI * 6378137 * Math.cos( latitude * RADIAN ) ) / ( TAILLE_TUILE * Math.pow( 2, z ) );
}

/* -------------------------------------------------------------------------- */
/* Palette                                                                     */
/* -------------------------------------------------------------------------- */

/** `#RRGGBB` -> `[r,g,b]`. */
export function versRgb( hexadecimal ) {
	return [
		Number.parseInt( hexadecimal.slice( 1, 3 ), 16 ),
		Number.parseInt( hexadecimal.slice( 3, 5 ), 16 ),
		Number.parseInt( hexadecimal.slice( 5, 7 ), 16 ),
	];
}

/** `[r,g,b]` -> `#RRGGBB`. */
export function versHexadecimal( [ r, v, b ] ) {
	return `#${ [ r, v, b ].map( ( c ) => c.toString( 16 ).padStart( 2, '0' ) ).join( '' ) }`.toUpperCase();
}

/**
 * Nombre de paliers d'anticrénelage générés entre deux couleurs de base.
 *
 * VALEUR MESURÉE, PAS CHOISIE — c'est la mitigation (1) du §2 du contrat #9,
 * « réduire la palette indexée à 6-8 couleurs », appliquée AVANT les deux
 * suivantes. Poids de l'image statique à 1600 px, mesuré :
 *
 *   3 paliers, 70 couleurs -> 287 450 o
 *   1 palier,  28 couleurs -> 239 476 o  (et 183 566 o même réduite à terre + eau)
 *   0 palier,   7 couleurs -> 177 824 o
 *
 * À 1 palier, aucune combinaison de couches ne tient sous le plafond de 153 600 o
 * — pas même la terre seule, à 164 460 o. Le plafond impose donc 0 palier, soit
 * exactement les 6-8 couleurs que le contrat prescrit.
 *
 * Conséquence assumée : aucun anticrénelage. Les cinq aplats de fond sont si
 * proches en luminance que leurs frontières ne crénellent pas visiblement ; seul
 * le contour charbon durcit, et c'est pour lui que `DESSIN.contour_px` vaut 2
 * plutôt que 1,5 — une largeur entière rend un trait d'épaisseur CONSTANTE une
 * fois seuillé, là où 1,5 alternerait entre 1 et 2 pixels le long du tracé, ce
 * qui se lirait comme une différence entre massifs.
 */
const PALIERS = 0;

/**
 * Palette EXACTE et fermée des deux artefacts, dérivée des seuls jetons.
 *
 * Les couleurs de base sont les six `--c-carte-*` et `--c-charbon`. S'y ajoutent
 * les paliers d'anticrénelage entre chaque paire : sans eux, un rendu sans
 * anticrénelage donnerait des côtes et des contours en escalier à 1600 px.
 *
 * La palette étant DÉRIVÉE, la recette la recalcule à l'identique depuis
 * `tokens.css` et exige que chaque pixel des deux artefacts en fasse partie.
 * C'est ce contrôle qui attrape un fond récupéré ailleurs : un rendu OSM standard
 * porte des verts, des jaunes et des bleus saturés qui n'y sont pas.
 *
 * @param {Map<string,string>} jetons Jetons lus dans `tokens.css`.
 * @return {number[][]} Triplets RGB, triés, dédoublonnés.
 */
export function paletteAutorisee( jetons ) {
	const bases = [ ...Object.keys( JETONS_CARTE ), JETON_CONTOUR ].map( ( nom ) => versRgb( jetons.get( nom ) ) );
	const vues = new Map();

	const ajouter = ( couleur ) => {
		const cle = ( couleur[ 0 ] << 16 ) | ( couleur[ 1 ] << 8 ) | couleur[ 2 ];

		if ( ! vues.has( cle ) ) {
			vues.set( cle, couleur );
		}
	};

	for ( const base of bases ) {
		ajouter( base );
	}

	for ( let i = 0; i < bases.length; i += 1 ) {
		for ( let j = i + 1; j < bases.length; j += 1 ) {
			for ( let palier = 1; palier <= PALIERS; palier += 1 ) {
				const part = palier / ( PALIERS + 1 );

				ajouter( [ 0, 1, 2 ].map( ( canal ) => Math.round( bases[ i ][ canal ] + part * ( bases[ j ][ canal ] - bases[ i ][ canal ] ) ) ) );
			}
		}
	}

	return [ ...vues.values() ].sort( ( a, b ) => a[ 0 ] - b[ 0 ] || a[ 1 ] - b[ 1 ] || a[ 2 ] - b[ 2 ] );
}

/**
 * Quantificateur au plus proche voisin sur une palette fermée, sans tramage.
 *
 * Sans tramage, et sur une palette FIXE : deux propriétés qui comptent. Le
 * tramage introduirait des pixels isolés qui ruinent la compression d'aplats et
 * scintillent au zoom ; une palette choisie par un quantificateur adaptatif ne
 * serait pas recalculable par la recette, donc pas contrôlable.
 *
 * Le cache couvre les 2^24 couleurs sRGB : 16 Mo, et une seule recherche par
 * couleur distincte au lieu d'une par pixel.
 *
 * @param {number[][]} palette Palette fermée.
 * @return {(rgba:Uint8Array|Buffer, pixels:number)=>Uint8Array} Convertisseur RGBA -> index de palette.
 */
export function quantificateur( palette ) {
	const cache = new Int16Array( 1 << 24 ).fill( -1 );

	return ( rgba, pixels ) => {
		const indices = new Uint8Array( pixels );

		for ( let p = 0; p < pixels; p += 1 ) {
			const r = rgba[ p * 4 ];
			const v = rgba[ p * 4 + 1 ];
			const b = rgba[ p * 4 + 2 ];
			const cle = ( r << 16 ) | ( v << 8 ) | b;
			let index = cache[ cle ];

			if ( -1 === index ) {
				let meilleure = Infinity;

				for ( let i = 0; i < palette.length; i += 1 ) {
					const dr = r - palette[ i ][ 0 ];
					const dv = v - palette[ i ][ 1 ];
					const db = b - palette[ i ][ 2 ];
					const distance = dr * dr + dv * dv + db * db;

					if ( distance < meilleure ) {
						meilleure = distance;
						index = i;
					}
				}

				cache[ cle ] = index;
			}

			indices[ p ] = index;
		}

		return indices;
	};
}
