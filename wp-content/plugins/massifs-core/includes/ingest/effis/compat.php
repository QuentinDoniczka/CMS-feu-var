<?php
/**
 * Surface publique de la couche : les fonctions `massifs_*()`.
 *
 * DEUX FONCTIONS, ET DEUX SEULEMENT. Seules celles-ci sont publiques ; les
 * classes du namespace `Massifs\Ingest\Effis\` sont l'implémentation. Chacune
 * est gardée par `function_exists()` pour qu'une double inclusion reste sans
 * effet — patron de `includes/ingest/tuiles/compat.php`.
 *
 * Toutes deux sont TOTALES : aucune exception, aucun `WP_Error`, aucun `null`,
 * et toutes les clés du contrat toujours présentes, y compris quand rien n'a
 * jamais été relevé. Le thème n'écrit jamais `isset()`.
 *
 * Toutes deux rendent des données BRUTES ET NON ÉCHAPPÉES : l'échappement
 * appartient au point de sortie, donc au thème. Corollaire opposable : `phrase`,
 * `surface_texte` et `commune_la_plus_proche` sont du TEXTE, jamais du HTML,
 * jamais une entité pré-échappée.
 *
 * `massifs_couche_effis_etat()`, `massifs_zones_parcourues_disponibles()` et
 * `massifs_effis_fraicheur()` ne sont PAS créées : `etat`, `nombre` et
 * `releve_le` sont déjà des clés du retour unique, et une seconde manière de
 * poser la même question est une divergence en attente.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-validator.php';
require_once __DIR__ . '/class-releve-repository.php';
require_once __DIR__ . '/class-couche.php';
require_once __DIR__ . '/class-attribution.php';

if ( ! function_exists( 'massifs_zones_parcourues_par_le_feu' ) ) {
	/**
	 * Couche des zones parcourues par le feu.
	 *
	 * AUCUN ARGUMENT, et c'est délibéré : il n'existe pas de paramètre `$jour`.
	 * Cette couche est une FENÊTRE GLISSANTE, pas un statut daté. Un accesseur
	 * indexé par date laisserait croire qu'on peut servir une fenêtre passée —
	 * on ne le peut pas, et on ne le doit pas.
	 *
	 * LE TEST DISCRIMINANT EST `etat`, JAMAIS `nombre`, JAMAIS
	 * `count( $zones )`. `aucune_zone` et `couche_effis_indisponible` portent
	 * tous deux `nombre === 0` ; les confondre, c'est écrire « aucune zone
	 * parcourue par le feu » alors que la vérité est « nous ne savons pas ».
	 *
	 * `zones[]['surface_ha']` et `zones[]['geometrie']` sont présentes et
	 * JAMAIS LUES PAR LE THÈME : ce sont des clés de transport, consommées par
	 * la route REST et par la future chaîne cartographique. La surface
	 * affichable est `surface_texte`, déjà formatée, unité et espace insécable
	 * compris.
	 *
	 * @return array{etat:string,zones:array<int,array<string,mixed>>,nombre:int,releve_le:string,expire_le:string,peremption_secondes:int,fenetre_jours:int,surface_minimale_ha:int}
	 */
	function massifs_zones_parcourues_par_le_feu(): array {
		return \Massifs\Ingest\Effis\Couche::etat();
	}
}

if ( ! function_exists( 'massifs_attribution_zones_parcourues_par_le_feu' ) ) {
	/**
	 * Mention de source de la couche, §9 du brief.
	 *
	 * `phrase` se rend ENTIÈRE et EN TEXTE NU : il n'existe aucune clé
	 * `lien_licence`, donc aucune destination à décrire, donc jamais de `<a>`.
	 * Jamais abrégée, jamais reformulée, jamais découpée.
	 *
	 * Elle n'est PAS rendue quand `etat === 'couche_effis_indisponible'` :
	 * créditer une source dont aucune donnée n'est affichée est une affirmation
	 * fausse.
	 *
	 * @return array{phrase:string,faits:array<string,string>}
	 */
	function massifs_attribution_zones_parcourues_par_le_feu(): array {
		return \Massifs\Ingest\Effis\Attribution::attribution();
	}
}
