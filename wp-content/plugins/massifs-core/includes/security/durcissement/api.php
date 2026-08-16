<?php
/**
 * Fonctions de lecture du module « durcissement ».
 *
 * AUCUNE N'EST DESTINÉE AU THÈME. Le durcissement est invisible par construction :
 * ces fonctions existent pour le rendre VÉRIFIABLE sans navigation, c'est-à-dire
 * pour qu'un test puisse prouver la CSP et la politique de mise à jour sans
 * dépendre d'une inspection d'en-têtes réels.
 *
 * TOUTES TOTALES : jamais `null`, jamais `WP_Error`, toutes les clés toujours
 * présentes. Un appelant n'a donc aucune branche d'absence à écrire.
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Massifs\Security\Durcissement\Politique;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_durcissement_entetes' ) ) {
	/**
	 * En-têtes de sécurité qui SERAIENT émis pour la requête courante.
	 *
	 * Dans l'ordre d'émission, sans effet de bord : appeler cette fonction n'envoie
	 * rien. Le résultat dépend de la requête (session ouverte, HTTPS) — c'est
	 * voulu : une carte constante ne prouverait pas la conditionnalité de la CSP.
	 *
	 * @return array<string, string>
	 */
	function massifs_durcissement_entetes(): array {
		return Politique::entetes();
	}
}

if ( ! function_exists( 'massifs_durcissement_politique_mises_a_jour' ) ) {
	/**
	 * Politique de mise à jour et d'édition de code en vigueur.
	 *
	 * `constante_posee` EST SÉPARÉE DE `edition_code_interdite` À DESSEIN : la
	 * première dit que `DISALLOW_FILE_EDIT` interdit, la seconde que le filtre
	 * `map_meta_cap` interdit. Les deux mécanismes sont indépendants et redondants ;
	 * les fondre en un seul drapeau rendrait impossible de savoir LEQUEL tient le
	 * jour où l'autre tombe — donc impossible de diagnostiquer une régression.
	 *
	 * Une constante définie à `false` compte pour NON POSÉE : elle n'interdit alors
	 * rien, et rapporter « posée » serait un faux vert.
	 *
	 * @return array{mineures_auto: bool, majeures_auto: bool, extensions_auto: bool, themes_auto: bool, edition_code_interdite: bool, constante_posee: bool}
	 */
	function massifs_durcissement_politique_mises_a_jour(): array {
		return array(
			'mineures_auto'          => Politique::mises_a_jour_mineures(),
			'majeures_auto'          => Politique::mises_a_jour_majeures(),
			'extensions_auto'        => Politique::mises_a_jour_extensions(),
			'themes_auto'            => Politique::mises_a_jour_themes(),
			'edition_code_interdite' => Politique::interdire_edition_code(),
			'constante_posee'        => defined( 'DISALLOW_FILE_EDIT' ) && (bool) constant( 'DISALLOW_FILE_EDIT' ),
		);
	}
}

if ( ! function_exists( 'massifs_durcissement_enumeration_fermee' ) ) {
	/**
	 * Les surfaces d'énumération de comptes sont-elles fermées ?
	 *
	 * Rapporte le RÉGLAGE, pas l'état de la requête courante : la fermeture est
	 * conditionnée à l'anonymat au moment où chaque surface est traversée, et une
	 * lecture qui rendrait `false` en session laisserait croire que le module est
	 * désarmé alors qu'il ne l'est pas.
	 */
	function massifs_durcissement_enumeration_fermee(): bool {
		return Politique::fermer_enumeration();
	}
}
