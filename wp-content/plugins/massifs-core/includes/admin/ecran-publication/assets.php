<?php
/**
 * Enfilage des feuilles, préchargement des polices et titre de document de
 * l'écran de publication.
 *
 * POURQUOI L'EXTENSION ENFILE DES FEUILLES DU THÈME (déviation D-8, contrat #14)
 *
 * `wp-admin` ne charge pas la feuille du thème. Recopier les jetons côté extension
 * les ferait dériver de `MASTER.md` au premier ajustement, et surtout : le liseré
 * 2 px et le motif des pastilles sont le mécanisme qui porte la conformité AA des
 * statuts (MASTER §10.2 — le vert sur rouge ne contraste qu'à 1,48:1 sans lui).
 * Dupliquer ce mécanisme créerait une divergence silencieuse sur un encodage de
 * sécurité. La liste des quatre fichiers est fermée et ratifiée par le contrat.
 *
 * `layout.css` n'est PAS enfilée : elle style `body`, `a`, `h1,h2,h3` et se
 * battrait avec `wp-admin`. Les deux choses qu'elle porte et dont nous avons
 * besoin — le `box-sizing` global dont `.pastille` dépend sémantiquement, et
 * `.repere` — sont reprises scopées dans `ecran-publication.css`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_est_notre_ecran' ) ) {
	/**
	 * Indique si l'écran d'administration courant est celui de la publication.
	 *
	 * Le suffixe de hook est exposé par `page.php`, qui possède le menu ; il n'est
	 * connu qu'après `admin_menu`, ce qui interdit d'accrocher `admin_print_styles-…`
	 * au chargement du module. La comparaison à l'exécution est donc à la fois la
	 * plus simple et la seule qui ne parie pas sur un ordre de chargement.
	 *
	 * @param string $suffixe Suffixe de hook observé.
	 */
	function massifs_publication_est_notre_ecran( string $suffixe ): bool {
		if ( '' === $suffixe || ! function_exists( 'massifs_publication_hook_suffixe' ) ) {
			return false;
		}

		$attendu = massifs_publication_hook_suffixe();

		return '' !== $attendu && $suffixe === $attendu;
	}
}

if ( ! function_exists( 'massifs_publication_version_fichier' ) ) {
	/**
	 * Version de cache d'un fichier, tirée de sa date de modification.
	 *
	 * Sans dépendance à `massifs_version_asset()` du thème : cette fonction n'existe
	 * pas si le thème actif n'est pas `massifs`, et l'écran doit rester utilisable
	 * dans ce cas.
	 *
	 * @param string $chemin Chemin absolu du fichier.
	 */
	function massifs_publication_version_fichier( string $chemin ): string {
		if ( is_readable( $chemin ) ) {
			$horodatage = filemtime( $chemin );

			if ( false !== $horodatage ) {
				return (string) $horodatage;
			}
		}

		return MASSIFS_CORE_VERSION;
	}
}

if ( ! function_exists( 'massifs_publication_enfiler_styles' ) ) {
	/**
	 * Enfile les quatre feuilles du thème puis la nôtre, sur notre écran seulement.
	 *
	 * Chaque feuille du thème est sous garde `is_readable( get_theme_file_path() )`.
	 * Les dépendances de notre feuille sont construites à partir des poignées
	 * RÉELLEMENT enfilées : déclarer une dépendance absente ferait sauter notre
	 * feuille en entier, alors que le mode dégradé voulu par le contrat est celui-ci
	 * — notre feuille est bien chargée, ses `var(--…)` restent non résolues, les
	 * déclarations concernées deviennent invalides au calcul, et l'écran retombe sur
	 * le rendu natif de `wp-admin` en restant intégralement utilisable.
	 *
	 * @param string $suffixe Suffixe de hook de l'écran courant.
	 */
	function massifs_publication_enfiler_styles( string $suffixe ): void {
		if ( ! massifs_publication_est_notre_ecran( $suffixe ) ) {
			return;
		}

		$feuilles = array(
			'massifs-admin-fonts'      => array(
				'chemin' => 'assets/fonts/fonts.css',
				'deps'   => array(),
				'media'  => 'all',
			),
			'massifs-admin-tokens'     => array(
				'chemin' => 'assets/css/tokens.css',
				'deps'   => array(),
				'media'  => 'all',
			),
			'massifs-admin-composants' => array(
				'chemin' => 'assets/css/composants.css',
				'deps'   => array( 'massifs-admin-tokens' ),
				'media'  => 'all',
			),
			'massifs-admin-print'      => array(
				'chemin' => 'assets/css/print.css',
				'deps'   => array( 'massifs-admin-tokens', 'massifs-admin-composants' ),
				'media'  => 'print',
			),
		);

		$enfilees = array();

		foreach ( $feuilles as $poignee => $feuille ) {
			$chemin = get_theme_file_path( $feuille['chemin'] );

			if ( ! is_readable( $chemin ) ) {
				continue;
			}

			wp_enqueue_style(
				$poignee,
				get_theme_file_uri( $feuille['chemin'] ),
				array_values( array_intersect( $feuille['deps'], $enfilees ) ),
				massifs_publication_version_fichier( $chemin ),
				$feuille['media']
			);

			$enfilees[] = $poignee;
		}

		$notre_chemin = __DIR__ . '/assets/css/ecran-publication.css';

		if ( is_readable( $notre_chemin ) ) {
			wp_enqueue_style(
				'massifs-ecran-publication',
				MASSIFS_CORE_URL . 'includes/admin/ecran-publication/assets/css/ecran-publication.css',
				$enfilees,
				massifs_publication_version_fichier( $notre_chemin ),
				'all'
			);
		}

		/*
		 * `admin_print_styles-{hook}` et pas `admin_head` (R-4) : vérifié dans
		 * `admin-header.php`, l'ordre est `admin_enqueue_scripts` → ce hook →
		 * `admin_print_styles`, où `print_admin_styles` (priorité 20) émet enfin les
		 * `<link>`. Le préchargement arrive donc AVANT la feuille qui demande la
		 * police, ce qui est tout son intérêt ; sur `admin_head` il arrivait après.
		 *
		 * Enregistré ici et pas au chargement du module : la garde de notre écran est
		 * déjà passée, et le suffixe de hook est en main.
		 */
		add_action( 'admin_print_styles-' . $suffixe, 'massifs_publication_precharger_polices' );
	}
}
add_action( 'admin_enqueue_scripts', 'massifs_publication_enfiler_styles' );

if ( ! function_exists( 'massifs_publication_precharger_polices' ) ) {
	/**
	 * Précharge les deux polices, servies depuis notre domaine.
	 *
	 * `crossorigin` est obligatoire MÊME EN MÊME ORIGINE : sans lui, la requête de
	 * préchargement et celle de la police ne sont pas dans le même mode CORS et la
	 * police est téléchargée deux fois. L'URL ne porte AUCUN paramètre de version,
	 * pour être identique à celle que demandent les `url()` de `fonts.css` — une URL
	 * différente serait, elle aussi, un second téléchargement.
	 */
	function massifs_publication_precharger_polices(): void {
		$polices = array(
			'assets/fonts/big-shoulders-display-var.woff2',
			'assets/fonts/atkinson-hyperlegible-next-var.woff2',
		);

		foreach ( $polices as $police ) {
			if ( ! is_readable( get_theme_file_path( $police ) ) ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( $police ) )
			);
		}
	}
}

if ( ! function_exists( 'massifs_publication_titre_document' ) ) {
	/**
	 * Pose le titre de document servi par le serveur.
	 *
	 * C'est le porteur PRINCIPAL de la confirmation après une redirection PRG
	 * (contrat #14 §5) : tous les lecteurs d'écran annoncent le titre au chargement,
	 * et c'est le seul canal universel après une navigation. Il satisfait du même
	 * coup l'exigence de titres de page uniques du §8 du brief.
	 *
	 * Le filtre est posé globalement parce que `admin_title` est appliqué AVANT
	 * `admin_enqueue_scripts` dans `admin-header.php` ; la garde est donc interne et
	 * s'appuie sur l'écran courant, déjà résolu à ce moment.
	 *
	 * WordPress échappe `$admin_title` à l'impression : la chaîne est rendue brute.
	 *
	 * @param string $titre_admin Titre calculé par le cœur.
	 */
	function massifs_publication_titre_document( string $titre_admin ): string {
		if ( ! function_exists( 'get_current_screen' ) || ! function_exists( 'massifs_publication_modele' ) ) {
			return $titre_admin;
		}

		$ecran = get_current_screen();

		if ( ! $ecran instanceof WP_Screen || ! massifs_publication_est_notre_ecran( $ecran->id ) ) {
			return $titre_admin;
		}

		$modele = massifs_publication_modele();
		$titre  = $modele['ecran']['titre_document'];

		return '' === $titre ? $titre_admin : $titre;
	}
}
add_filter( 'admin_title', 'massifs_publication_titre_document' );
