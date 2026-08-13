<?php
/**
 * Fond de carte : pyramide de tuiles et image statique du repli.
 *
 * Les deux fonctions sont TOTALES : aucune exception, aucun `WP_Error`, aucun
 * `null`, et toutes les clés du contrat toujours présentes — le thème n'écrit
 * jamais `isset()`.
 *
 * Les noms de clés suivent l'AVENANT du 14 août 2026 au contrat #9 (§13), qui
 * réconcilie ce module avec la chaîne #7 déjà livrée : `url_modele` et non
 * `url_gabarit`, `format` = classe de média (`raster`) et `format_tuile` =
 * extension de fichier (`png`), plus `attribution` et `attribution_url` à plat
 * dans le retour. Le consommateur — `templates/parts/carte.php` — est hors de
 * l'empreinte de cette chaîne : c'est donc le module de lecture qui s'aligne, et
 * en un seul exemplaire. Publier les deux orthographes serait « une seconde
 * manière de poser la même question », ce que le §1.4 proscrit.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Tuiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/metadonnees.php';

/**
 * Métadonnées de la pyramide de tuiles.
 *
 * `url_modele` porte ses accolades NON SUBSTITUÉES et n'a AUCUNE query string :
 * la version est un segment de chemin, ce qui est la seule forme qui mérite un
 * `Cache-Control: immutable`. Le thème la passe à `esc_attr()` ou
 * `wp_json_encode()` — jamais à `esc_url()`, qui supprime `{` et `}` et produit
 * une URL `…/zxy.png` : une panne silencieuse, visible à l'exécution seulement.
 *
 * `attribution` et `attribution_url` sont la projection à plat du même bloc de
 * métadonnées que lit `massifs_attribution_fond_de_carte()` — un seul producteur,
 * une seule donnée. Elles sont LIÉES AU FOND, DANS LES DEUX SENS (invariant
 * I-9.4) : fond absent ⇒ attribution vide, on n'attribue pas une ressource qu'on
 * n'affiche pas ; et réciproquement mention de source vide après `trim()` ⇒
 * `disponible` faux, on n'affiche pas une ressource qu'on n'attribue pas.
 *
 * @return array{disponible:bool,type:string,format:string,format_tuile:string,url_modele:string,zoom_min:int,zoom_max:int,taille_tuile:int,nombre:int,bbox:array{ouest:float,sud:float,est:float,nord:float},mode:string,version:string,sha256:string,octets:int,attribution:string,attribution_url:string}
 */
function fond(): array {
	$donnees      = donnees();
	$pyramide     = $donnees['pyramide'];
	$attribution  = $donnees['attribution'];
	$format_tuile = format_tuile( $pyramide['format'] );
	$url          = url_modele( $pyramide['version'], $format_tuile );

	// L'attribution est jaugée COMME LE FAIT `carte-secours.php` : `trim()`, puis
	// vide vaut absente. Une phrase réduite à des espaces est l'état
	// `attribution_fond_indisponible` du §3, pas une phrase.
	$phrase = trim( $attribution['phrase'] );
	$lien   = trim( $attribution['lien_licence'] );

	// Le mode dégradé n'a pas produit de pyramide : le fond est indisponible, et
	// aucune couche n'est montée. Le thème ne se replie JAMAIS sur une URL tierce.
	// `version` et `sha256` sont déjà conformes à leur forme quand elles sont non
	// vides — `metadonnees.php` rejette le fichier ENTIER sinon —, il n'y a donc
	// rien de plus à contrôler ici qu'une absence.
	//
	// LA MENTION DE SOURCE EST UNE CONDITION DE DISPONIBILITÉ, et c'est
	// l'invariant I-9.4 tenu côté carte : « l'image et son attribution n'existent
	// que l'une avec l'autre ». `carte-secours.php` l'obtient en évaluant sa garde
	// d'attribution AVANT sa garde d'image ; `templates/parts/carte.php`, livré par
	// la chaîne #7 et hors empreinte, ne peut pas faire la même chose — il monte la
	// couche de tuiles sur la seule présence de la clé `fond` de l'îlot, et rend
	// séparément `.carte__attribution[data-attribution="fond"]` sur le seul test
	// `'' !== $fond_attribution`, qu'une chaîne d'espaces franchit. Sans la
	// condition ci-dessous, une phrase vide après `trim()` produirait donc des
	// tuiles OSM affichées sous un lien d'attribution SANS TEXTE : à la fois un
	// manquement à l'ODbL et un lien sans nom accessible. La seule moitié encore
	// mobile est celle-ci ; c'est donc elle qui ferme l'invariant, à la source.
	$disponible = $donnees['valide']
		&& MODE_COMPLET === $donnees['mode']
		&& '' !== $pyramide['version']
		&& '' !== $pyramide['sha256']
		&& $pyramide['nombre'] > 0
		&& $pyramide['octets'] > 0
		&& '' !== $url
		&& '' !== $phrase;

	return array(
		'disponible'      => $disponible,
		// Constantes de format : elles décrivent la forme d'une tuile, qui ne
		// dépend pas de la présence du fichier de métadonnées. Elles survivent
		// donc au repli, contrairement aux versions, empreintes et dénombrements.
		'type'            => TYPE_FOND,
		'format'          => FORMAT_MEDIA,
		'format_tuile'    => $format_tuile,
		'url_modele'      => $disponible ? $url : '',
		'zoom_min'        => $pyramide['zoom_min'],
		'zoom_max'        => $pyramide['zoom_max'],
		'taille_tuile'    => $pyramide['taille_tuile'],
		'nombre'          => $disponible ? $pyramide['nombre'] : 0,
		'bbox'            => $disponible ? $pyramide['bbox'] : BBOX_NULLE,
		'mode'            => $donnees['mode'],
		'version'         => $disponible ? $pyramide['version'] : '',
		'sha256'          => $disponible ? $pyramide['sha256'] : '',
		'octets'          => $disponible ? $pyramide['octets'] : 0,
		// Publiées ROGNÉES : ce sont les deux chaînes que `carte.php` teste par
		// `'' !== …`, et une chaîne d'espaces franchirait ce test. Nominalement les
		// valeurs n'ont aucun blanc de bordure : la sortie est octet pour octet la
		// même. `massifs_attribution_fond_de_carte()` reste inchangée et rend la
		// donnée brute — `carte-secours.php` la rogne lui-même.
		'attribution'     => $disponible ? $phrase : '',
		'attribution_url' => $disponible ? $lien : '',
	);
}

/**
 * Métadonnées de l'image statique du repli sans JavaScript.
 *
 * IL N'Y A DÉLIBÉRÉMENT PAS DE CLÉ `url`. L'artefact vit dans le THÈME, et le
 * thème résout son propre chemin d'asset. Publier ici l'URL d'un fichier de thème
 * obligerait l'extension à résoudre une URI d'asset de thème, c'est-à-dire à en
 * dépendre au runtime, à rebours de la frontière stricte du `CLAUDE.md` — et à
 * casser sur un thème enfant ou un renommage. L'extension publie des FAITS sur
 * l'artefact que son build a produit : dimensions, version, empreinte, poids
 * (arbitrage A-3). Aucune fonction de résolution d'asset de thème n'est appelée
 * ici, et un grep du module doit pouvoir le confirmer sans lire les commentaires.
 *
 * `largeur` et `hauteur` sont là pour que le thème les pose sur `<img>` et
 * n'introduise aucun saut de mise en page (§10 du brief). Elles sont CALCULÉES au
 * build depuis la bbox projetée en Web Mercator, jamais choisies. Le transtypage
 * explicite n'est pas décoratif : `carte-secours.php` les teste par `is_int()` et
 * rend zéro octet si le type diverge.
 *
 * Cette disponibilité est INDÉPENDANTE de celle de la pyramide : en mode dégradé,
 * l'image est quand même produite, depuis la seule géométrie des massifs que nous
 * possédons hors ligne. La ligne de DoD §5.5 ne dépend d'aucun accès réseau au
 * build (invariant I-9.9).
 *
 * @return array{disponible:bool,largeur:int,hauteur:int,porte_les_statuts:bool,contours_massifs:int,version:string,sha256:string,octets:int}
 */
function statique(): array {
	$donnees  = donnees();
	$statique = $donnees['statique'];

	$disponible = $donnees['valide']
		&& '' !== $statique['version']
		&& '' !== $statique['sha256']
		&& $statique['largeur'] > 0
		&& $statique['hauteur'] > 0;

	return array(
		'disponible'        => $disponible,
		'largeur'           => $disponible ? (int) $statique['largeur'] : 0,
		'hauteur'           => $disponible ? (int) $statique['hauteur'] : 0,
		// GELÉ À VIE, jamais lu dans les métadonnées. Une image portant les
		// couleurs du jour se périmerait par un chemin que le PHP ne contrôle plus
		// — cache HTTP, CDN de l'hébergeur —, et la règle « ne jamais présenter un
		// statut périmé comme courant » tomberait sans qu'une ligne soit fautive.
		'porte_les_statuts' => false,
		'contours_massifs'  => $disponible ? $statique['contours_massifs'] : 0,
		'version'           => $disponible ? $statique['version'] : '',
		'sha256'            => $disponible ? $statique['sha256'] : '',
		'octets'            => $disponible ? $statique['octets'] : 0,
	);
}

/**
 * Modèle d'URL des tuiles.
 *
 * Construit à partir du chemin du fichier de métadonnées lui-même : ne dépend ni
 * du nom du fichier principal de l'extension, ni d'une constante appartenant à une
 * autre chaîne. Hors WordPress, on renvoie une chaîne vide plutôt que de fataler.
 *
 * L'URL est de MÊME ORIGINE que la page, parce que `plugins_url()` la dérive de
 * `site_url()` : c'est la seule forme correcte. `carte.js` compare l'origine de
 * `url_modele` à celle du document et ne pose aucune couche si elles diffèrent —
 * une URL absolue vers un autre hôte tuerait la carte en silence, et violerait la
 * contrainte « zéro requête navigateur vers un domaine tiers ».
 *
 * AUCUNE query string, contrairement à `url_geometrie()` du domaine massifs : ici
 * la version est un SEGMENT DE CHEMIN, et un `?v=` détruirait la sémantique
 * `immutable` du `.htaccess` sur certains proxies.
 *
 * La version est re-contrôlée contre `VERSION_REGEX` avant d'entrer dans le
 * chemin. `metadonnees.php` l'a déjà fait, et c'est délibérément redondant : la
 * garde vit à l'endroit où la valeur devient une URL, pas trois fichiers plus loin.
 *
 * @param string $version      Jeton de version, 8 hexadécimaux.
 * @param string $format_tuile Extension des tuiles, déjà ramenée à une forme sûre.
 * @return string Vide hors WordPress, ou si l'un des deux segments est douteux.
 */
function url_modele( string $version, string $format_tuile ): string {
	if ( ! function_exists( 'plugins_url' ) ) {
		return '';
	}

	if ( 1 !== preg_match( VERSION_REGEX, $version ) || 1 !== preg_match( FORMAT_TUILE_REGEX, $format_tuile ) ) {
		return '';
	}

	// Les accolades traversent `plugins_url()` intactes : c'est une concaténation
	// de chemin, sans encodage ni liste blanche de caractères. Elles doivent
	// arriver littérales jusqu'à Leaflet, qui seul les substitue.
	return plugins_url( $version . '/{z}/{x}/{y}.' . $format_tuile, chemin_metadonnees() );
}

/**
 * Extension de tuile exploitable, ou la valeur de repli.
 *
 * Une extension non conforme n'est pas une donnée à propager : la clé
 * `format_tuile` est une constante de format, et le thème doit pouvoir la lire
 * sans se demander d'où elle vient. Une valeur douteuse est donc RAMENÉE à
 * `FORMAT_TUILE_DEFAUT`, jamais recopiée — c'est ce qui empêche un `../..` lu
 * dans des métadonnées corrompues de composer un chemin hors du répertoire servi.
 *
 * @param string $format Extension lue dans les métadonnées.
 * @return string
 */
function format_tuile( string $format ): string {
	return 1 === preg_match( FORMAT_TUILE_REGEX, $format ) ? $format : FORMAT_TUILE_DEFAUT;
}
