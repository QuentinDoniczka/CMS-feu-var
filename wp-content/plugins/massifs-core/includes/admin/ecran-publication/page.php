<?php
/**
 * Entrée de menu et callback de page de l'écran de mise à jour des statuts.
 *
 * L'entrée de menu exige la capacité `massifs_publier_statuts` — une capacité
 * `massifs_*`, jamais une capacité du cœur — et la page la redemande à
 * l'affichage : `add_menu_page()` cache l'entrée sans protéger l'URL.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_registre_hook_suffixe' ) ) {
	/**
	 * Mémorise et relit le suffixe de hook réel de notre écran.
	 *
	 * Le suffixe est celui que `add_menu_page()` a RETOURNÉ, jamais un suffixe
	 * deviné : `assets.php` s'en sert pour n'enfiler nos feuilles que sur cet
	 * écran, et une valeur devinée y échouerait en silence.
	 *
	 * @param string|null $nouveau Suffixe à mémoriser, `null` pour relire.
	 */
	function massifs_publication_registre_hook_suffixe( ?string $nouveau = null ): string {
		static $suffixe = '';

		if ( null !== $nouveau ) {
			$suffixe = $nouveau;
		}

		return $suffixe;
	}
}

if ( ! function_exists( 'massifs_publication_hook_suffixe' ) ) {
	/**
	 * Suffixe de hook de l'écran, ou chaîne vide tant que le menu n'est pas posé.
	 */
	function massifs_publication_hook_suffixe(): string {
		return massifs_publication_registre_hook_suffixe();
	}
}

if ( ! function_exists( 'massifs_publication_enregistrer_menu' ) ) {
	/**
	 * Pose l'entrée de menu du portail.
	 *
	 * Aucune liste d'autorisation de menu n'existe dans ce projet, par choix : cet
	 * écran ne cherche pas à s'y inscrire.
	 */
	function massifs_publication_enregistrer_menu(): void {
		$suffixe = add_menu_page(
			massifs_publication_chaines()['titre_ecran'],
			'Massifs',
			massifs_publication_capacite(),
			massifs_publication_slug(),
			'massifs_publication_afficher_page',
			'dashicons-location-alt'
		);

		massifs_publication_registre_hook_suffixe( is_string( $suffixe ) ? $suffixe : '' );
	}
}

if ( ! function_exists( 'massifs_publication_afficher_page' ) ) {
	/**
	 * Rend l'écran, ou refuse explicitement.
	 *
	 * AUCUN RENDU DE REPLI : un gabarit manquant est un défaut, pas un mode
	 * dégradé. Rendre un formulaire de secours donnerait un écran d'écriture dont
	 * personne n'a vérifié l'accessibilité ni les libellés officiels.
	 */
	function massifs_publication_afficher_page(): void {
		if ( ! current_user_can( massifs_publication_capacite() ) ) {
			wp_die(
				esc_html( massifs_publication_message_erreur( 'droits_insuffisants' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( ! function_exists( 'massifs_publication_rendre' ) ) {
			wp_die(
				esc_html( massifs_publication_message_gabarit_absent() ),
				'',
				array( 'response' => 500 )
			);
		}

		massifs_publication_rendre( massifs_publication_modele() );
	}
}
