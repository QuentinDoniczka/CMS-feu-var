<?php
/**
 * Entrée de menu et feuilles de style de l'écran d'historique.
 *
 * MENU DÉFENSIF — trois chaînes de développement veulent une entrée « MASSIFS »
 * sans se voir. L'enregistrement se fait donc en priorité 99, après les autres,
 * et ne crée le parent que si personne ne l'a déjà créé : le doublon est
 * impossible, et le parent n'est jamais un lien mort puisque, quand nous le
 * créons, il pointe sur notre propre écran. L'état dégradé n'existe que tant
 * qu'aucune chaîne sœur n'a livré son écran.
 *
 * LA CAPACITÉ PASSÉE À `add_submenu_page()` N'EST PAS UNE GARANTIE : elle ne
 * gouverne que l'affichage de l'entrée. Le rendu de l'écran la revérifie, comme
 * la route REST et l'export.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_historique_enregistrer_menu' ) ) {
	/**
	 * Déclare l'entrée de menu de l'historique.
	 *
	 * Appelée sur `admin_menu` en priorité 99.
	 */
	function massifs_historique_enregistrer_menu(): void {
		$titre  = massifs_historique_mot( 'titre' );
		$parent = massifs_historique_parent_existant();

		if ( '' === $parent ) {
			$parent = 'massifs';

			add_menu_page(
				'MASSIFS',
				'MASSIFS',
				MASSIFS_HISTORIQUE_CAPACITE,
				$parent,
				'massifs_historique_rendre_ecran',
				'dashicons-location-alt',
				58
			);
		}

		$accroche = add_submenu_page(
			$parent,
			$titre,
			$titre,
			MASSIFS_HISTORIQUE_CAPACITE,
			MASSIFS_HISTORIQUE_PAGE,
			'massifs_historique_rendre_ecran'
		);

		if ( ! is_string( $accroche ) || '' === $accroche ) {
			return;
		}

		add_action(
			'admin_enqueue_scripts',
			static function ( string $accroche_courante ) use ( $accroche ): void {
				if ( $accroche_courante !== $accroche ) {
					return;
				}

				massifs_historique_enfiler_styles();
			}
		);
	}
}

if ( ! function_exists( 'massifs_historique_parent_existant' ) ) {
	/**
	 * Menu de premier niveau du portail déjà déclaré, chaîne vide si aucun.
	 *
	 * `$GLOBALS['admin_page_hooks']` est l'index des menus de premier niveau, du
	 * slug vers son accroche. On y cherche `massifs`, puis, à défaut, le premier
	 * slug préfixé `massifs` — le préfixe de l'extension, pas le nom d'une chaîne
	 * sœur.
	 *
	 * POURQUOI PAS LE SEUL SLUG `massifs` : l'écran de publication a livré son
	 * parent sous `massifs-publication`. S'en tenir à l'égalité stricte
	 * produirait DEUX entrées « Massifs » côte à côte dans la barre latérale,
	 * c'est-à-dire exactement le doublon que ce menu défensif existe pour
	 * empêcher. La règle générale sert l'intention ; l'égalité stricte ne
	 * servirait plus que sa lettre.
	 *
	 * À réconcilier en revue de lot : un seul slug de parent partagé vaudrait
	 * mieux que cette détection.
	 */
	function massifs_historique_parent_existant(): string {
		$menus = isset( $GLOBALS['admin_page_hooks'] ) && is_array( $GLOBALS['admin_page_hooks'] )
			? $GLOBALS['admin_page_hooks']
			: array();

		if ( isset( $menus['massifs'] ) ) {
			return 'massifs';
		}

		foreach ( array_keys( $menus ) as $slug ) {
			$slug = (string) $slug;

			// Le slug de notre propre page n'est pas un parent : sans cette garde,
			// un second passage s'accrocherait à lui-même.
			if ( MASSIFS_HISTORIQUE_PAGE !== $slug && str_starts_with( $slug, 'massifs' ) ) {
				return $slug;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'massifs_historique_enfiler_styles' ) ) {
	/**
	 * Enfile les styles de l'écran, et UNIQUEMENT sur son accroche.
	 *
	 * `layout.css` et `composants.css` du thème ne sont JAMAIS enfilés en
	 * administration : le `box-sizing: border-box` global de `layout.css`
	 * casserait la mise en page de wp-admin. Seuls les jetons sont repris, sous
	 * une poignée propre — celle du thème est enregistrée sur
	 * `wp_enqueue_scripts`, qui ne se déclenche pas ici. Même domaine : la
	 * contrainte « zéro requête vers un domaine tiers » reste intacte.
	 *
	 * LA FEUILLE DE L'ÉCRAN EST NOMMÉE EXPLICITEMENT, et vit hors de `includes/`
	 * — voir le commentaire du corps, qui porte la mesure. Le balisage doit de
	 * toute façon rester lisible et navigable sans aucune feuille de style.
	 *
	 * AUCUNE DÉPENDANCE VERS UN STYLE DU CŒUR (`common`, `forms`, `buttons`) :
	 * les règles de focus et de bouton de la feuille ont la même spécificité que
	 * celles de wp-admin et ne l'emportent que par l'ordre de chargement. La
	 * déclarer en dépendance la ferait passer AVANT, et la ferait perdre.
	 */
	function massifs_historique_enfiler_styles(): void {
		$dependances = array();

		$jetons_chemin = get_theme_file_path( 'assets/css/tokens.css' );

		if ( is_readable( $jetons_chemin ) ) {
			wp_enqueue_style(
				'massifs-historique-jetons',
				get_theme_file_uri( 'assets/css/tokens.css' ),
				array(),
				massifs_historique_version_asset( $jetons_chemin )
			);

			$dependances[] = 'massifs-historique-jetons';
		}

		// CHEMIN EXPLICITE, JAMAIS UN `glob`. La feuille était naguère découverte
		// par `glob( __DIR__ . '/assets/css/*.css' )`, et ce sont les DEUX moitiés
		// de cette ligne qui ont produit un défaut bloquant :
		//
		//   - `__DIR__` la logeait sous `includes/`, que `plugins-guard.conf`
		//     refuse à n'importe quelle profondeur sous `wp-content/plugins/`
		//     (deuxième `DirectoryMatch`). La feuille partait donc en 403 : la
		//     poignée était correcte, le `<link>` bien dans le `<head>`, aucune
		//     erreur PHP — et l'écran rendu NU ;
		//   - un `glob` qui ne trouve rien enfile SILENCIEUSEMENT zéro feuille.
		//     Le mode d'échec est muet, donc invisible sans ouvrir un navigateur.
		//
		// La feuille vit désormais sous `massifs-core/assets/css/`, hors de tout
		// sous-arbre refusé (sondé : servi). Ne pas la remettre sous `includes/`,
		// et ne pas élargir le garde-fou Apache à `plugins/` — ce serait rouvrir
		// l'invariant opposable de l'issue #20.
		$feuille = MASSIFS_CORE_CHEMIN . 'assets/css/historique.css';

		if ( ! is_readable( $feuille ) ) {
			return;
		}

		wp_enqueue_style(
			'massifs-historique',
			plugins_url( 'assets/css/historique.css', MASSIFS_CORE_FICHIER ),
			$dependances,
			massifs_historique_version_asset( $feuille )
		);
	}
}

if ( ! function_exists( 'massifs_historique_version_asset' ) ) {
	/**
	 * Version de cache d'un fichier, depuis sa date de modification.
	 *
	 * `massifs_version_asset()` du thème n'est PAS employée : elle attend un
	 * chemin RELATIF au thème, alors que nos deux feuilles vivent l'une dans le
	 * thème et l'autre dans l'extension. On calcule donc `filemtime()`
	 * nous-mêmes — la seconde option explicitement offerte par le contrat.
	 *
	 * @param string $chemin Chemin absolu du fichier.
	 */
	function massifs_historique_version_asset( string $chemin ): string {
		$modifie = is_readable( $chemin ) ? filemtime( $chemin ) : false;

		return false === $modifie ? MASSIFS_CORE_VERSION : (string) $modifie;
	}
}
