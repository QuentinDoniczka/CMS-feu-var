<?php
/**
 * Surface publique du fond de carte : les fonctions `massifs_*()`.
 *
 * Trois fonctions, et trois seulement (contrat #9 §1). Seules celles-ci sont
 * publiques ; les fonctions du namespace `Massifs\Ingest\Tuiles\` sont
 * l'implémentation. Chacune est gardée par `function_exists()` pour qu'une double
 * inclusion reste sans effet.
 *
 * `massifs_fond_de_carte_etat()` et `massifs_fond_de_carte_disponible()` ne sont
 * PAS créées : elles n'ont aucun consommateur — `disponible` et `mode` sont déjà
 * des clés de `massifs_fond_de_carte()` —, et une seconde manière de poser la
 * même question est une divergence en attente (§1.4).
 *
 * Toutes sont TOTALES : aucune exception, aucun `WP_Error`, une valeur définie
 * même si les métadonnées sont absentes ou corrompues, et toutes les clés du
 * contrat toujours présentes. Toutes retournent des données BRUTES, non échappées.
 *
 * Les noms de clés suivent l'AVENANT du 14 août 2026 (§13 du contrat #9), qui
 * aligne ce module sur la chaîne #7 déjà livrée. Les noms des trois fonctions,
 * eux, sont ceux du §1 d'origine et n'ont pas bougé.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/metadonnees.php';
require_once __DIR__ . '/fond.php';
require_once __DIR__ . '/attribution.php';

if ( ! function_exists( 'massifs_fond_de_carte' ) ) {
	/**
	 * Métadonnées de la pyramide de tuiles du fond de carte.
	 *
	 * `url_modele` est de même origine que la page, porte ses accolades non
	 * substituées et aucune query string. Le thème ne la passe JAMAIS à
	 * `esc_url()`, qui supprimerait `{` et `}` : `esc_attr()` ou `wp_json_encode()`.
	 *
	 * `format` est la CLASSE DE MÉDIA de la couche (`raster`), `format_tuile`
	 * l'extension des fichiers (`png`) : deux faits distincts (avenant A-11).
	 *
	 * `zoom_max` est la borne de la PYRAMIDE, jamais une autorisation de zoom :
	 * la carte reste plafonnée au `zoom_max` du référentiel (clause F-11).
	 *
	 * `bbox` est l'emprise réellement couverte, alignée sur la grille de tuiles,
	 * donc un sur-ensemble strict de `massifs_emprise()['bbox']`. Elle sert à
	 * borner la couche, jamais à cadrer la vue initiale.
	 *
	 * `attribution` et `attribution_url` sont liées au fond : elles sont vides
	 * quand `disponible` est faux — on n'attribue pas une ressource absente.
	 *
	 * @return array{disponible:bool,type:string,format:string,format_tuile:string,url_modele:string,zoom_min:int,zoom_max:int,taille_tuile:int,nombre:int,bbox:array,mode:string,version:string,sha256:string,octets:int,attribution:string,attribution_url:string}
	 */
	function massifs_fond_de_carte(): array {
		return \Massifs\Ingest\Tuiles\fond();
	}
}

if ( ! function_exists( 'massifs_fond_de_carte_statique' ) ) {
	/**
	 * Métadonnées de l'image statique du repli sans JavaScript.
	 *
	 * Aucune clé `url` : l'artefact vit dans le thème, qui résout son propre
	 * chemin d'asset. L'extension publie des faits, jamais un chemin de thème.
	 *
	 * `porte_les_statuts` vaut `false` à vie.
	 *
	 * @return array{disponible:bool,largeur:int,hauteur:int,porte_les_statuts:bool,contours_massifs:int,version:string,sha256:string,octets:int}
	 */
	function massifs_fond_de_carte_statique(): array {
		return \Massifs\Ingest\Tuiles\statique();
	}
}

if ( ! function_exists( 'massifs_attribution_fond_de_carte' ) ) {
	/**
	 * Mention de source §9 du fond de carte.
	 *
	 * `phrase` est la chaîne du §9 du brief, verbatim. Elle se rend entière, comme
	 * texte du lien vers `lien_licence` ; elle ne se coupe ni ne se reformule.
	 *
	 * @return array{phrase:string,lien_licence:string,faits:array<string,string>}
	 */
	function massifs_attribution_fond_de_carte(): array {
		return \Massifs\Ingest\Tuiles\attribution();
	}
}
