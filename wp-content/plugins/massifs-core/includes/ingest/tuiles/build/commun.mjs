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
	// Deuxième fichier de thème lu au BUILD, sur le même patron que `tokens.css`
	// et pour la même raison : couplage symétrique, borné au build, délibéré
	// (arbitrage A-3 du contrat #9). Le build lisait déjà les jetons et écrivait
	// le PNG ; il lit maintenant la police dont il cuit les contours de glyphes.
	// Sa `sha256`, son `nomPostScript` et son `upem` sont consignés au manifeste et
	// relus par la recette (I-71.7) : un fichier de thème cuit dans les octets sans
	// être contrôlé serait exactement le trou que I-9.7 ferme pour les couleurs.
	police_texte: path.join( THEME, 'assets/fonts/atkinson-hyperlegible-next-var.woff2' ),
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

/**
 * EMPRISE DÉCLARÉE de la pyramide, en coordonnées ENTIÈRES DE TUILE à z12.
 *
 * POURQUOI DES ENTIERS DE TUILE ET NON QUATRE DEGRÉS. `grille()` calcule
 * `Math.floor( norm · 2^z )`. L'identité
 * `floor( floor( a · 2¹² ) / 2^(12−z) ) === floor( a · 2^z )` fait dériver z5 à
 * z11 des entiers z12 par simple décalage `x0 >> (12 − z)` : emboîtement PROUVÉ,
 * aucun flottant, et aucun décalage d'une colonne quand un bord tombe pile sur
 * une frontière de tuile. `bboxDeGrille( grilleDeclaree( 12 ) )` rend alors
 * exactement l'emprise déclarée — DÉCLARÉ === PUBLIÉ, propriété que quatre
 * degrés n'auraient pas donnée.
 *
 * D'OÙ VIENNENT CES QUATRE ENTIERS. `MARGE_DECLAREE_DEG` appliquée aux quatre
 * bords de `massifs_emprise()['bbox']`, arrondie vers l'extérieur à la grille
 * z12, UNE FOIS, le 17 août 2026, puis GELÉE. Elle n'est jamais réévaluée au
 * build : la réévaluer rétablirait très exactement le couplage que l'issue #71
 * supprime. Marges résiduelles obtenues : ouest 0,08611° · est 0,07542° ·
 * nord 0,05881° · sud 0,08842°. Seul le bord sud franchit une frontière de tuile
 * (y1 1502 → 1503), d'où z12 15 × 14 = 210 et 295 tuiles au total.
 *
 * LES CHANGER EST UNE DÉCISION, prise par un humain et écrite : elle re-cuit les
 * 295 tuiles, déplace la bbox publiée et bouge les dimensions de l'image
 * statique. Un sommet du référentiel qui sort de cette emprise ARRÊTE le build
 * (I-71.4) ; le remède est de décider une nouvelle emprise, jamais de la
 * recalculer depuis la géométrie.
 */
export const EMPRISE_DECLAREE = Object.freeze( { zoom: 12, x0: 2100, x1: 2114, y0: 1490, y1: 1503 } );

/** Marge appliquée UNE FOIS pour dériver `EMPRISE_DECLAREE`. Documentaire : jamais relue par le build. */
export const MARGE_DECLAREE_DEG = 0.05;

/**
 * Marge résiduelle au-dessous de laquelle le build AVERTIT, sans jamais échouer.
 *
 * Même ordre de grandeur et même sens que `DEBORDEMENT_MAX_DEG` — « un cheveu
 * dans cette projection » — et délibérément PAS fusionnée avec lui : l'un borne
 * un écart département/référentiel à la récupération, l'autre signale que
 * l'emprise déclarée se resserre. Deux faits, deux noms.
 */
export const MARGE_MIN_DEG = 0.02;

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
 * `--c-carte-encre` a désormais un consommateur DÉCLARÉ, et un seul : l'encre des
 * toponymes cuits (#71, qui renverse l'arbitrage A-9 du contrat #9). Aucune couche
 * de fond ne l'emploie, aucun contour non plus — les contours sont en
 * `--c-charbon`.
 *
 * NE PAS EN DÉDUIRE que compter les pixels d'index encre compte l'encre des
 * toponymes : l'arbitrage A-5 du contrat #71 le supposait, et LA MESURE L'A
 * INFIRMÉ. `--c-carte-encre` est le plus proche voisin d'une bande de la rampe
 * d'anticrénelage charbon -> terre, et `PALIERS = 0` y fait tomber la frange des
 * 25 contours : la toile SANS étiquette en porte déjà des dizaines de milliers.
 * Ce coût est ANTÉRIEUR à #71. C'est pourquoi `TOPONYMES.couverture_encre_max` se
 * mesure DANS LES BOÎTES d'étiquette, où C-e interdit tout pixel charbon.
 */
export const COUCHES = Object.freeze( [
	{ nom: 'terre', surfacique: true, remplissage: '--c-carte-terre', trait: '--c-carte-trait' },
	{ nom: 'vegetation', surfacique: true, remplissage: '--c-carte-vegetation', trait: null },
	{ nom: 'eau', surfacique: true, remplissage: '--c-carte-eau', trait: null },
	{ nom: 'routes', surfacique: false, remplissage: null, trait: '--c-carte-trait' },
] );

/**
 * Couche PONCTUELLE des toponymes.
 *
 * Elle n'entre délibérément PAS dans `COUCHES`, qui est documenté comme « les
 * couches du fond, dans l'ordre de PEINTURE » : chacun de ses membres porte un
 * `remplissage`/`trait` et passe par `cheminSvg()`. Les toponymes ne sont ni
 * peints par `cheminSvg`, ni simplifiés, ni filtrés par aire, et ils PORTENT DES
 * ATTRIBUTS. Les glisser dans `COUCHES` imposerait un `if ( 'toponymes' === … )
 * continue;` dans cinq boucles — la forme même du code qui pourrit. Une constante
 * séparée, traitée explicitement, plus une liste dérivée pour les seules boucles
 * de récupération et d'archive.
 */
export const COUCHE_TOPONYMES = Object.freeze( { nom: 'toponymes', ponctuel: true } );

/** Toutes les couches de la SOURCE : les quatre géométriques, plus les toponymes. */
export const COUCHES_SOURCE = Object.freeze( [ ...COUCHES, COUCHE_TOPONYMES ] );

/** Classes `place` retenues, DANS L'ORDRE DE RANG. `hamlet`, `suburb`, `locality` sont exclus. */
export const CLASSES_TOPONYMES = Object.freeze( [ 'city', 'town', 'village' ] );

/**
 * Couches retenues pour l'IMAGE STATIQUE — mitigation (2) du §2 du contrat #9.
 *
 * La mitigation (1) ne suffit pas : à 7 couleurs, l'image complète dépasse encore
 * le plafond de 153 600 o. Le §2 impose alors « supprimer les couches de fond les
 * moins informatives », AVANT de toucher à la largeur.
 *
 * ÉCARTS ENTRE COUCHES, et non poids absolus — les écarts sont stables, les poids
 * se périment au premier changement d'emprise, comme #71 vient de le montrer :
 *
 *   retrait de `routes`     -> −13 115 o
 *   retrait de `vegetation` -> −22 245 o
 *
 * Mesures du build initial du 13 août 2026, ORDRE DE GRANDEUR, NON NORMATIVES. Le
 * poids courant de l'artefact vit dans `build/reference.json`, clé
 * `statique.octets`, et dans aucune prose.
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
	// Règle de sélection GELÉE au §3 du contrat #71. `hamlet`, `suburb` et
	// `locality` sont exclus : à z ≤ 12 ils saturent la toile sans rien orienter.
	toponymes: 'node["place"~"^(city|town|village)$"]',
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
	// Mesurées le 18 août 2026 par la procédure en deux passes du §8 du contrat
	// #71 : première passe sous une enveloppe de vraisemblance large, lecture de
	// `comptes_overpass.toponymes`, resserrement à n/2 et 3n, seconde passe. Les
	// bornes ci-dessous sont celles de la SECONDE passe, éprouvées sur une charge
	// réelle — c'est cette archive-là qui est commitée. Première passe :
	// n = 199 éléments retournés, dont 2 `place=city` (Marseille et Aix), d'où
	// 199/2 = 100 et 3 x 199 = 597.
	toponymes: { plancher: 100, plafond: 597 },
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
 * Réglages d'étiquetage des toponymes. Chacun est DÉRIVÉ ou MESURÉ, aucun choisi.
 *
 *   - `zoom_min_etiquettes: 9` — RÈGLE, PAS RÉGLAGE. À z8 la région utile du
 *     département fait 210 x 188 px ; un seul mot en occuperait plus de la moitié.
 *     Aucune étiquette n'est cuite à z5–z8 ;
 *   - `densite_par_mpx: 25` — une étiquette par bloc de ~200 x 200 px. Appliquée à
 *     `airePlacementMpx( emprise, z )` avec `Math.round` — et non `Math.floor`,
 *     parce que 3,95 n'est pas 3 ;
 *   - `zoom_percu_statique: 10` — arbitrage A-8 du contrat #71, RÉVISÉ le 18 août
 *     2026 sur mesure. Il valait 9, sur deux prévisions que la mesure a infirmées :
 *       (i) A-7 annonçait un coût NUL pour l'exclusion des intérieurs de massif.
 *           Mesuré, elle coûte 3 noms sur 4 : Aix-en-Provence, Martigues et Aubagne
 *           sont rejetés AUX CINQ ANCRES, par les DEUX moitiés de A-7
 *           indépendamment — ils sont contre Sainte-Victoire, la Côte Bleue et le
 *           Garlaban. À z9 la feuille ne portait plus que « Marseille » ;
 *       (ii) A-8 redoutait ≈ 143 000 o pour 16 étiquettes, soit 93 % du plafond.
 *           Mesuré, une étiquette coûte 464 o : six en coûtent ≈ 2 800, et la
 *           statique tient à ≈ 71 % du plafond. La prévision était fausse d'un
 *           facteur ≈ 30.
 *     Une image de repli portant un seul nom n'honore la règle « la statique est
 *     l'équivalent de la carte » que formellement. Le degré de liberté reste CE
 *     SEUL ENTIER : la correction honnête est de le bouger et de RE-MESURER,
 *     jamais de choisir des noms à la main ;
 *   - `corps_px: 19` — PYRAMIDE. DEUX dérivations indépendantes, et c'est le
 *     plancher le plus haut qui l'emporte (arbitrage A-1) :
 *       (i) OPTIQUE — `carte.js` pose `zoomSnap: 0.25`, donc `L.GridLayer` charge
 *           `Math.round( zoom )` et met le reste à l'échelle en CSS. Le facteur
 *           garanti est 0,7071 (et vaut exactement cela sur la vue initiale
 *           desktop, z 9,5). Une étiquette cuite à 13 px ne rend donc pas 13 px
 *           CSS mais 9,2. Pour tenir le plus petit corps du système, `--fs-100` =
 *           13 px, il faut 13 / 0,7071 = 18,4, d'où 19 ;
 *       (ii) TYPOGRAPHIQUE — à 13 px les hampes d'Atkinson mesurent ~1,3–1,6 px :
 *           une hampe à cheval sur une frontière de pixel peut se quantifier
 *           ENTIÈREMENT en `--c-carte-trait` (4,17:1), et l'étiquette perd alors
 *           son cœur à 6,82:1. C'est la seconde raison, et c'est ce que le
 *           contrôle C-g rend assertable ;
 *     La pyramide n'a AUCUN plafond d'octets (§10 du brief : « hors fond de
 *     carte »), donc rien n'argumente en faveur du plus petit nombre ;
 *   - `facteur_z12: 2` — une tuile z12 est TOUJOURS rendue à l'échelle de z11
 *     (F-11 + A-7 du contrat #9). Corps, halo et padding sont multipliés ; le jeu
 *     de noms et les ancres géographiques sont IDENTIQUES (I-71.9). Sans quoi un
 *     écran ordinaire et un écran dense afficheraient des NOMS DIFFÉRENTS ;
 *   - `halo_px: 1.5` / `halo_statique_px: 2` — D-28 avait rejeté un halo calcaire
 *     de 4 px parce qu'il REMPLISSAIT LA BOÎTE ENGLOBANTE d'une petite forme. Les
 *     rapports ici sont 1,5/19 = 0,079 et 2/28 = 0,071 : un ordre de grandeur sous
 *     le mode de défaillance de D-28. C'est le RAPPORT qui compte, pas le pixel ;
 *   - `corps_statique_px: 28` et `corps_min_statique_px: 25` — le plancher est
 *     DÉRIVÉ : 1600 px sur les 186 mm utiles de l'A4 (§13 de `MASTER.md`) font
 *     218,5 ppp ; 8 pt = 8/72 in x 218,5 = 24,3 px. C'est un PLANCHER, donc il
 *     s'arrondit VERS LE HAUT : 25. `corps_statique_px = 28` le franchit de 12 % ;
 *   - `marge_contour_px: 6` — `DESSIN.contour_px` (2) + `halo_statique_px` (2) +
 *     2 px de fond pur, pour que halo et contour ne se touchent pas même après
 *     l'étalement d'un pixel par la quantification ;
 *   - `ecart_min_statique_px: 14` — cible 3 px CSS. 2 px CSS est le seuil où deux
 *     marques FUSIONNENT, et la cible est « n'approche jamais la fusion » ; et
 *     0,225 est le bout OPTIMISTE du facteur d'affichage — l'image vit dans
 *     `.bande--carte`, dont les gouttières le ramènent vers ≈ 0,205. 3 / 0,225 =
 *     13,3, d'où 14, qui rend 2,9 px CSS à 0,225 et 2,7 px à 0,205 ;
 *   - `etiquettes_statique_max: 6` — écrit comme une GARDE derrière le moteur
 *     « statique = jeu du zoom perçu », qui rendait 4 noms à z9. DEPUIS LA RÉVISION
 *     DE `zoom_percu_statique` À 10, ce plafond MORD : le zoom perçu propose 16
 *     candidats et six sont retenus. Ce n'est plus un filet, c'est le nombre
 *     d'étiquettes de la feuille — et c'est la valeur à bouger, avec
 *     `zoom_percu_statique`, si le rendu regardé dans Chrome demande autre chose ;
 *   - `couverture_encre_max: 0.005` — 0,5 % de la toile, comptés sur l'index de
 *     palette `--c-carte-encre` DANS LES BOÎTES D'ÉTIQUETTE. L'arbitrage A-5 les
 *     comptait sur la toile entière, sur la prémisse que ce jeton n'a aucun autre
 *     consommateur dans la statique ; MESURE À L'APPUI, LA PRÉMISSE EST FAUSSE — la
 *     toile SANS étiquette en porte déjà des dizaines de milliers, l'encre étant le
 *     plus proche voisin d'une bande de la rampe d'anticrénelage charbon → terre.
 *     Ce coût est ANTÉRIEUR à #71. Compter dans les boîtes rétablit l'exactitude
 *     visée, à plafond et dénominateur inchangés : C-e interdit tout pixel charbon à
 *     moins de `marge_contour_px` d'une boîte, donc aucune frange de contour n'y
 *     entre. Le halo n'est PAS borné : il est couleur de fond, invisible sur le
 *     fond, et le borner serait borner la mauvaise chose ;
 *   - `facteur_360: 0.225` — 360 / 1600, le facteur d'affichage sur la plus petite
 *     fenêtre servie ;
 *   - `plage_luma_min_360` / `luma_moyenne_min_360` — MESURÉS PUIS GELÉS, jamais
 *     prédits : c'est le style de la maison, celui de `PALIERS` et de
 *     `COUCHES_STATIQUE`. Le build IMPRIME les deux nombres par étiquette ; la
 *     valeur gelée est le MINIMUM MESURÉ MOINS 20 %, dans le même commit, datée au
 *     §7 du README ;
 *   - `recul_sombres_max_360: 0.05` — seconde moitié de C-f. Le compte de pixels
 *     sombres HORS DES BOÎTES d'étiquette, après réduction à 360 px, ne recule pas
 *     de plus de 5 % contre la même image SANS étiquettes — hors des boîtes, sans
 *     quoi l'encre des toponymes se compterait elle-même et le contrôle serait
 *     circulaire. Le vrai risque n'est pas que les noms soient
 *     illisibles — ils le sont, et c'est assumé — c'est qu'ils NOIENT ce qui
 *     compte, les 25 contours ;
 *   - `ancrages` — `C` d'abord : un nom seul se centre sur son point, et AUCUN
 *     point n'est dessiné (une pastille serait une marque nouvelle sur une carte
 *     dont les marques sont gouvernées par `MASTER.md`). Les quatre autres ne
 *     servent qu'après collision.
 */
export const TOPONYMES = Object.freeze( {
	zoom_min_etiquettes: 9,
	densite_par_mpx: 25,
	zoom_percu_statique: 10,
	corps_px: 19,
	facteur_z12: 2,
	halo_px: 1.5,
	padding_px: 3,
	corps_statique_px: 28,
	corps_min_statique_px: 25,
	halo_statique_px: 2,
	padding_statique_px: 4,
	marge_contour_px: 6,
	ecart_min_statique_px: 14,
	etiquettes_statique_max: 6,
	couverture_encre_max: 0.005,
	facteur_360: 0.225,
	// MESURÉS le 18 août 2026, puis gelés au MINIMUM MESURÉ MOINS 20 %, sur les SIX
	// étiquettes de la statique : plage minimale 112,4 (Istres) -> 89, moyenne
	// minimale 199,7 (Marignane) -> 159. Étendue mesurée : plage de 112,4 à 144,6,
	// moyenne de 199,7 à 207,0.
	//
	// Les valeurs précédentes (115 et 162) venaient d'un échantillon d'UNE étiquette,
	// quand `zoom_percu_statique` valait 9 ; passer à 10 a fait entrer des noms plus
	// courts et plus serrés, dont la plage est plus basse. LE SEUIL SUIT LA MESURE,
	// jamais l'inverse — et c'est le build qui l'a imposé, en refusant d'émettre sous
	// l'ancien seuil plutôt qu'en le rabotant.
	//
	// La prédiction de vraisemblance du §5 du contrat #71 donnait ≈ 69 et ≈ 207 : la
	// plage mesurée la dépasse largement, ce qui est le bon sens de l'écart — une
	// plage de 20 aurait signalé un défaut de pipeline.
	plage_luma_min_360: 89,
	luma_moyenne_min_360: 159,
	recul_sombres_max_360: 0.05,
	ancrages: [ 'C', 'N', 'S', 'E', 'O' ],
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

/**
 * Écrit une FeatureCollection de travail, PROPRIÉTÉS EFFACÉES.
 *
 * L'effacement est une GARANTIE, pas une commodité : `preparer()` s'y appuie, et
 * `lireFc()` ne relit que des géométries parce que mapshaper, sans attribut à
 * rendre, émet une `GeometryCollection`. Un appelant qui pourrait la désactiver
 * ferait de cette garantie une option — d'où `ecrireFcPoints()`, séparée.
 */
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
 * Entité ponctuelle de toponyme, rendue en Feature GeoJSON : TROIS attributs, pas un
 * de plus.
 *
 * Écrite une seule fois, pour le fichier de travail de mapshaper comme pour
 * l'archive commitée : `lireFcPoints()` et `controlerPoint()` relisent les deux, et
 * deux constructions distinctes de la même forme finiraient par diverger d'un champ.
 *
 * @param {object} entite `{ nom, classe, population, lon, lat }`.
 */
export function featurePoint( entite ) {
	return {
		type: 'Feature',
		properties: { nom: entite.nom, classe: entite.classe, population: entite.population },
		geometry: { type: 'Point', coordinates: [ entite.lon, entite.lat ] },
	};
}

/**
 * Écrit une FeatureCollection de POINTS porteuse de ses trois attributs.
 *
 * Deux fonctions nommées, une garantie chacune, plutôt qu'un paramètre ajouté à
 * `ecrireFc()` : l'effacement des propriétés y est une GARANTIE, énoncée en
 * commentaire et sur laquelle `preparer()` s'appuie. Une garantie qu'un appelant
 * peut désactiver n'est plus une garantie.
 *
 * @param {string}   chemin  Fichier de sortie.
 * @param {object[]} entites `{ nom, classe, population, lon, lat }`.
 */
export function ecrireFcPoints( chemin, entites ) {
	fs.writeFileSync( chemin, JSON.stringify( { type: 'FeatureCollection', features: entites.map( ( entite ) => featurePoint( entite ) ) } ) );
}

/**
 * Relit une FeatureCollection de points et en contrôle la forme, entité par entité.
 *
 * C'est la PREMIÈRE couche dont les attributs portent l'information : un
 * dénombrement seul ne peut pas attraper une charge où chaque `nom` serait arrivé
 * vide — laquelle produirait MOINS D'ÉTIQUETTES AU LIEU D'UN ÉCHEC, exactement la
 * dégradation silencieuse que ce module refuse partout ailleurs.
 *
 * @param {string} chemin Fichier à relire.
 * @return {{nom:string,classe:string,population:number,lon:number,lat:number}[]}
 */
export function lireFcPoints( chemin ) {
	const brut = JSON.parse( fs.readFileSync( chemin, 'utf8' ) );

	if ( ! brut || 'FeatureCollection' !== brut.type || ! Array.isArray( brut.features ) ) {
		throw new Arret( `Ni FeatureCollection de points dans ${ relatifAuDepot( chemin ) }` );
	}

	return brut.features.map( ( feature, rang ) => controlerPoint( feature, `${ relatifAuDepot( chemin ) }, entité ${ rang }` ) );
}

/**
 * Contrôle d'une entité ponctuelle de toponyme, quelle que soit sa provenance.
 *
 * @param {object} feature Entité GeoJSON.
 * @param {string} objet   Désignation, pour le message d'erreur.
 */
export function controlerPoint( feature, objet ) {
	if ( ! feature || 'Feature' !== feature.type || ! feature.geometry || 'Point' !== feature.geometry.type ) {
		throw new Arret( `Toponyme non ponctuel dans ${ objet }.` );
	}

	const proprietes = feature.properties || {};
	const nom = 'string' === typeof proprietes.nom ? proprietes.nom : '';

	if ( '' === nom.trim() ) {
		throw new Arret( `Toponyme sans nom dans ${ objet }.` );
	}

	if ( ! CLASSES_TOPONYMES.includes( proprietes.classe ) ) {
		throw new Arret( `Toponyme « ${ nom } » de classe « ${ proprietes.classe } », hors de [${ CLASSES_TOPONYMES.join( ', ' ) }], dans ${ objet }.` );
	}

	if ( ! Number.isInteger( proprietes.population ) || proprietes.population < 0 ) {
		throw new Arret( `Toponyme « ${ nom } » : population « ${ proprietes.population } » non entière dans ${ objet }.` );
	}

	const [ lon, lat ] = feature.geometry.coordinates || [];

	if ( ! Number.isFinite( lon ) || ! Number.isFinite( lat ) ) {
		throw new Arret( `Toponyme « ${ nom } » : coordonnée non finie dans ${ objet }.` );
	}

	return { nom, classe: proprietes.classe, population: proprietes.population, lon, lat };
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

/** Forme commune d'une grille, à partir de ses quatre entiers de tuile. */
function formerGrille( z, x0, x1, y0, y1 ) {
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

/**
 * Grille de tuiles couvrant une emprise DONNÉE à un zoom donné.
 *
 * CE N'EST PLUS LA GRILLE DE LA PYRAMIDE — celle-là est `grilleDeclaree()`, et
 * elle ne dérive d'aucune géométrie. Cette fonction a exactement UN appelant,
 * `verifier.mjs`, qui s'en sert pour formuler le non-débordement AU NIVEAU DE LA
 * TUILE : `grille( emprise, 12 )` doit être contenue dans `EMPRISE_DECLAREE` sur
 * ses quatre bords. C'est la seconde formulation, indépendante, de la propriété
 * que `construire.mjs` énonce sommet par sommet — deux énoncés d'une même
 * propriété, dans deux scripts, sur le patron déjà en service pour le parseur
 * d'emprise validé par la lecture PHP qui fait autorité.
 *
 * NE PAS LA SUPPRIMER comme « morte » : sans elle, le contrôle de la recette
 * n'aurait plus de référent calculable.
 */
export function grille( bbox, z ) {
	const cote = Math.pow( 2, z );

	return formerGrille(
		z,
		Math.floor( normX( bbox.ouest ) * cote ),
		Math.floor( normX( bbox.est ) * cote ),
		Math.floor( normY( bbox.nord ) * cote ),
		Math.floor( normY( bbox.sud ) * cote )
	);
}

/**
 * Grille DÉCLARÉE d'un zoom, dérivée de `EMPRISE_DECLAREE` par décalage entier.
 *
 * Aucun flottant, aucune projection : l'emboîtement des huit zooms est une
 * propriété arithmétique, pas un résultat de calcul en virgule flottante. La
 * forme rendue est exactement celle de `grille()`, pour que rien en aval n'ait à
 * distinguer les deux.
 */
export function grilleDeclaree( z ) {
	if ( ! Number.isInteger( z ) || z < ZOOM_MIN || z > EMPRISE_DECLAREE.zoom ) {
		throw new Arret( `Zoom ${ z } hors de l'emprise déclarée : elle est posée à z${ EMPRISE_DECLAREE.zoom }, et la pyramide part de z${ ZOOM_MIN }.` );
	}

	const decalage = EMPRISE_DECLAREE.zoom - z;

	return formerGrille(
		z,
		EMPRISE_DECLAREE.x0 >> decalage,
		EMPRISE_DECLAREE.x1 >> decalage,
		EMPRISE_DECLAREE.y0 >> decalage,
		EMPRISE_DECLAREE.y1 >> decalage
	);
}

/** Les grilles déclarées de tous les zooms de la pyramide, du plus large au plus fin. */
export function grillesDeclarees() {
	const sortie = [];

	for ( let z = ZOOM_MIN; z <= ZOOM_MAX; z += 1 ) {
		sortie.push( grilleDeclaree( z ) );
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

/**
 * Emprise géographique de la pyramide, telle que `massifs_fond_de_carte()` la
 * publie en `bbox`.
 *
 * Elle est prise sur la grille déclarée du zoom le plus fin, qui EST l'emprise
 * déclarée : déclaré === publié, à l'octet. La phrase « sur-ensemble strict de
 * `massifs_emprise()['bbox']` » du §1.1 du contrat #9 reste vraie mot pour mot,
 * mais elle est devenue un CONTRÔLE en sortie ≠ 0 et non plus une définition.
 */
export function bboxDeclaree() {
	return bboxDeGrille( grilleDeclaree( EMPRISE_DECLAREE.zoom ) );
}

/**
 * Aire de PLACEMENT d'un zoom, en mégapixels de toile.
 *
 * C'est l'aire, en pixels de toile au zoom `z`, de `massifs_emprise()['bbox']` —
 * JAMAIS celle de la toile entière, JAMAIS celle de l'emprise déclarée. La
 * précision est normative : la toile z9 fait 0,393 Mpx contre 0,158 Mpx pour
 * l'emprise du référentiel, et écrire `g.largeur_px * g.hauteur_px` livrerait
 * 2,5 FOIS TROP D'ÉTIQUETTES. La bonne référence est l'emprise du référentiel
 * parce que la requête Overpass est émise sur elle : aucun candidat n'existe
 * au-dehors, et une densité rapportée à une surface sans candidat est une
 * densité fausse.
 *
 * @param {object} bbox Emprise du référentiel.
 * @param {number} z    Zoom.
 * @return {number} Aire en mégapixels.
 */
export function airePlacementMpx( bbox, z ) {
	const cote = Math.pow( 2, z ) * TAILLE_TUILE;

	return ( ( normX( bbox.est ) - normX( bbox.ouest ) ) * cote * ( ( normY( bbox.sud ) - normY( bbox.nord ) ) * cote ) ) / 1e6;
}

/**
 * Luma sRGB PONDÉRÉE, non linéarisée.
 *
 * Non linéarisée délibérément : on mesure une TEXTURE APPARENTE, pas une
 * photométrie. Linéariser rendrait des nombres physiquement plus justes et
 * perceptuellement moins pertinents pour la question posée — « l'étiquette
 * survit-elle à la réduction ? ».
 */
export function luma( r, v, b ) {
	return 0.2126 * r + 0.7152 * v + 0.0722 * b;
}

/**
 * Écart minimal entre deux boîtes `[x0, y0, x1, y1]`, négatif si elles se recouvrent.
 *
 * Une seule formule, partagée par le solveur de placement et par la recette : deux
 * copies ne mesureraient pas forcément la même chose le jour où l'une bougerait, et
 * C-b se contrôlerait alors contre un écart qui n'est plus celui qui a été posé.
 */
export function ecartBoites( a, b ) {
	return Math.max( a[ 0 ] - b[ 2 ], b[ 0 ] - a[ 2 ], a[ 1 ] - b[ 3 ], b[ 1 ] - a[ 3 ] );
}

/**
 * Le point est-il dans l'anneau ? Lancer de rayon.
 *
 * Deux appelants, deux référentiels, UNE question : `recuperer.mjs` rattache un
 * trou à son anneau extérieur, en degrés ; `verifier.mjs` teste le centre d'une
 * boîte d'étiquette, en pixels de toile. Deux copies de ce test cesseraient un jour
 * de répondre pareil sur un cas limite, et l'une des deux serait alors fausse sans
 * que rien ne le dise.
 *
 * @param {number[]}   point  `[x, y]`.
 * @param {number[][]} anneau Anneau, `[[x, y], …]`.
 * @return {boolean}
 */
export function dansAnneau( [ x, y ], anneau ) {
	let dedans = false;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i += 1 ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];

		if ( yi > y !== yj > y && x < ( ( xj - xi ) * ( y - yi ) ) / ( yj - yi ) + xi ) {
			dedans = ! dedans;
		}
	}

	return dedans;
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
 * suivantes. ÉCARTS mesurés sur l'image statique à 1600 px, le 13 août 2026,
 * ordre de grandeur, non normatifs :
 *
 *   3 paliers (70 couleurs) -> 1 palier (28 couleurs)  −47 974 o
 *   1 palier  (28 couleurs) -> 0 palier  (7 couleurs)  −61 652 o
 *
 * SUR L'ÉTAT ANTÉRIEUR À #71, 1 palier était hors d'atteinte, et de peu : les deux
 * mesures à deux couches (terre + eau) donnaient un rapport de 1,2886, appliqué à
 * une statique de 120 768 o, soit 155 641 o contre 153 600 — un dépassement de
 * 1,3 %. `PALIERS = 0` était donc un RÉGLAGE TENU, pas une dette.
 *
 * DEPUIS #71 CE CALCUL A CHANGÉ, et il faut l'écrire plutôt que le laisser
 * reposer : l'emprise déclarée a fait maigrir la statique, et le rapport de 1,2886
 * appliqué à son poids courant retombe SOUS le plafond. 1 palier redeviendrait donc
 * finançable en octets. AUCUN POIDS ABSOLU N'EST RECOPIÉ ICI — il s'est déjà
 * périmé trois fois en cinq jours ; le poids courant vit dans `build/reference.json`,
 * clé `statique.octets`, et dans aucune prose. 1 palier n'est pas activé pour
 * autant : la palette est FERMÉE À 7 par l'interdit 9 du §7 du contrat #71, et
 * 28 couleurs seraient une décision de design, pas un réglage de build. La
 * justification a donc CHANGÉ DE NATURE — ce n'est plus le plafond d'octets qui
 * tient `PALIERS = 0`, c'est la fermeture de la palette. À rouvrir par une révision
 * de contrat, jamais ici.
 *
 * Conséquence assumée : aucun anticrénelage PALETTISÉ. Ce n'est pas l'absence
 * d'anticrénelage : `resvg` lisse TOUJOURS le bord d'un tracé, et le
 * quantificateur au plus proche voisin rabat ce dégradé sur la palette fermée. Il
 * en sort une RAMPE ACCIDENTELLE À CINQ NIVEAUX entre l'encre et le fond —
 * `--c-carte-encre` jusqu'à ~30 % vers le fond, puis `--c-carte-trait` jusqu'à
 * ~80 %, `--c-carte-vegetation` jusqu'à ~91 %, `--c-carte-terre` jusqu'à ~96 %,
 * `--c-carte-fond` au-delà. Elle est gratuite, elle est réelle, et c'est elle qui
 * rend `PALIERS = 0` indolore POUR LE TEXTE.
 *
 * Une réserve doit l'accompagner, sans quoi un relecteur y lira un défaut AA : la
 * frange d'un glyphe est peinte en `--c-carte-trait`, qui vaut 4,17:1 et N'EST PAS
 * LE TEXTE. Le 6,82:1 repose sur le CŒUR du glyphe, en `--c-carte-encre` — ce que
 * le contrôle C-g de la recette rend asserté au lieu qu'espéré, et qui est la
 * seconde raison de `TOPONYMES.corps_px = 19`.
 *
 * Les cinq aplats de fond sont par ailleurs si proches en luminance que leurs
 * frontières ne crénellent pas visiblement ; seul le contour charbon durcit, et
 * c'est pour lui que `DESSIN.contour_px` vaut 2 plutôt que 1,5 — une largeur
 * entière rend un trait d'épaisseur CONSTANTE une fois seuillé, là où 1,5
 * alternerait entre 1 et 2 pixels le long du tracé, ce qui se lirait comme une
 * différence entre massifs.
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
