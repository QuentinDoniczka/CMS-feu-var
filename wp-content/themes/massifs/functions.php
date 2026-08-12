<?php
/**
 * Point d'entrée de la logique de présentation du thème Massifs.
 *
 * Ce fichier ne contient AUCUNE règle métier : pas de saison, pas de
 * péremption, pas de sévérité, pas de formatage de date. Tout cela vient des
 * fonctions de lecture de l'extension massifs-core.
 *
 * get_header() et get_footer() sont INTERDITS dans ce thème : ils chargeraient
 * header.php / footer.php à la RACINE, fichiers qui n'existent pas et ne
 * doivent pas exister. L'inclusion passe par
 * get_template_part( 'templates/header' ) / ( 'templates/footer' ).
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version d'un fichier d'assets, pour le cache-busting.
 *
 * `is_readable()` précède TOUT appel à `filemtime()` : `filemtime()` sur un
 * fichier absent émet un E_WARNING et retourne `false`. Cette fonction ne
 * retourne jamais `false` ni `null` — un `$ver` faux ferait imprimer l'URL
 * sans paramètre de version, donc sans cache-busting, ce qui est un défaut
 * silencieux.
 *
 * @param string $chemin_relatif Chemin relatif à la racine du thème.
 */
function massifs_version_asset( string $chemin_relatif ): string {
	$chemin = get_theme_file_path( $chemin_relatif );

	if ( is_readable( $chemin ) ) {
		$horodatage = filemtime( $chemin );

		if ( false !== $horodatage ) {
			return (string) $horodatage;
		}
	}

	static $version_du_theme = null;

	if ( null === $version_du_theme ) {
		$declaree         = wp_get_theme()->get( 'Version' );
		$version_du_theme = is_string( $declaree ) && '' !== $declaree ? $declaree : '0.2.0';
	}

	return $version_du_theme;
}

/**
 * Journalise un diagnostic de gabarit, sous WP_DEBUG uniquement.
 *
 * Point unique de la garde `WP_DEBUG`, partagé par `functions.php` et
 * `front-page.php`.
 *
 * Un message vide n'est jamais journalisé : les branches de l'ardoise portent
 * une chaîne vide quand elles n'ont rien à signaler.
 *
 * Aucun de ces messages n'atteint le visiteur — ils s'adressent à la recette.
 *
 * @param string $message Message de diagnostic.
 */
function massifs_journaliser( string $message ): void {
	if ( '' === $message || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Inclut une partie de gabarit de `templates/parts/`, sans jamais mentir au visiteur.
 *
 * Convention d'appel figée avec la chaîne #6 : aucun `$args` n'est passé, les
 * parties appellent elles-mêmes l'API publique de l'extension.
 *
 * Partie absente : commentaire HTML (observable en recette) et journal sous
 * WP_DEBUG. JAMAIS de texte de repli visible — écrire « liste indisponible »
 * serait de la copie d'interface inventée, et ce serait faux : la donnée n'est
 * pas indisponible, c'est un fichier de gabarit qui manque.
 *
 * @param string $slug Nom du gabarit, sans le chemin ni l'extension.
 *
 * @return bool Vrai si la partie a été chargée.
 */
function massifs_partie( string $slug ): bool {
	// `get_template_part()` retourne `false` quand rien n'a été chargé
	// (WP 5.5+ ; le thème exige 6.4).
	$charge = get_template_part( 'templates/parts/' . $slug );

	if ( false !== $charge ) {
		return true;
	}

	printf( '<!-- massifs: partie « %s » absente -->', esc_html( $slug ) );

	massifs_journaliser( sprintf( 'massifs: gabarit templates/parts/%s.php introuvable.', $slug ) );

	return false;
}

/**
 * Emplacements de menu du thème : identifiant => étiquette.
 *
 * Source unique de l'étiquette : elle nomme l'emplacement dans l'administration
 * ET sert de nom accessible au <nav> correspondant. Écrite deux fois, elle
 * finirait par diverger entre l'écran des menus et l'arbre d'accessibilité.
 *
 * @return array<string, string>
 */
function massifs_emplacements_de_menu(): array {
	return array(
		'principal' => 'Navigation principale',
		'pied'      => 'Liens de pied de page',
	);
}

/**
 * Affiche le menu d'un emplacement — ou rien du tout.
 *
 * Emplacement non affecté : AUCUN <nav> n'est émis, plutôt qu'un landmark de
 * navigation vide. `'fallback_cb' => false` évite wp_page_menu(), qui listerait
 * les pages d'exemple de WordPress.
 *
 * Point unique du jeu d'arguments partagé par la barre haute et le pied de
 * page : seuls l'emplacement et le bloc hôte les distinguent.
 *
 * @param string $emplacement Identifiant d'emplacement, déclaré par
 *                            massifs_emplacements_de_menu().
 * @param string $bloc        Préfixe du bloc hôte : « barre » ou « pied ».
 */
function massifs_menu( string $emplacement, string $bloc ): void {
	if ( ! has_nav_menu( $emplacement ) ) {
		return;
	}

	$etiquettes = massifs_emplacements_de_menu();

	wp_nav_menu(
		array(
			'theme_location'       => $emplacement,
			'container'            => 'nav',
			'container_class'      => $bloc . '__nav',
			'container_aria_label' => $etiquettes[ $emplacement ],
			'menu_class'           => $bloc . '__liens',
			'depth'                => 1,
			'fallback_cb'          => false,
		)
	);
}

/**
 * Supports de thème et emplacements de menu.
 *
 * `title-tag` n'est délibérément PAS déclaré : `index.php` (hors empreinte de
 * cette issue) imprime son propre <title> en dur, et le support en ferait
 * imprimer un second par wp_head().
 */
function massifs_configurer_theme(): void {
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	// Les deux emplacements sont gardés par has_nav_menu() à l'affichage, dans
	// massifs_menu() : un emplacement non affecté ne doit produire aucun
	// landmark vide.
	register_nav_menus( massifs_emplacements_de_menu() );
}
add_action( 'after_setup_theme', 'massifs_configurer_theme' );

/**
 * Enregistre et enfile les cinq feuilles du thème.
 *
 * Les cinq poignées sont TOUJOURS enregistrées, y compris quand le fichier est
 * absent du disque — avec `$src = false`, ce qui produit un handle-alias :
 * aucune balise imprimée, aucune 404, et la dépendance se résout quand même.
 * Sans cela, `WP_Dependencies::all_deps()` retirerait SILENCIEUSEMENT
 * `massifs-layout` si `massifs-tokens` n'était pas enregistré, et la page
 * perdrait TOUT son CSS.
 *
 * L'ordre du tableau est l'ordre des balises. `massifs-print` dépend de
 * `massifs-composants` en plus de `massifs-tokens` : la dépendance ne sert pas
 * seulement à charger les jetons employés par ses `var(--…)`, elle GARANTIT que
 * sa balise vienne après, donc que la feuille d'impression l'emporte dans les
 * égalités de spécificité.
 *
 * Clé `media` absente = `all` : la porter sur les quatre feuilles d'écran
 * n'apprendrait rien, seule l'exception mérite d'être écrite.
 *
 * `style.css` n'est pas enfilé : il ne porte aucune règle CSS.
 */
function massifs_enfiler_styles(): void {
	$feuilles = array(
		'massifs-fonts'      => array(
			'chemin' => 'assets/fonts/fonts.css',
			'deps'   => array(),
		),
		'massifs-tokens'     => array(
			'chemin' => 'assets/css/tokens.css',
			'deps'   => array(),
		),
		'massifs-layout'     => array(
			'chemin' => 'assets/css/layout.css',
			'deps'   => array( 'massifs-tokens' ),
		),
		// Dépendance SÉMANTIQUE, pas cosmétique : composants.css suppose le
		// box-sizing: border-box global de layout.css — sans lui .pastille mesure
		// 30 × 20 au lieu de 26 × 16 — et .bandeau-alerte tient son
		// padding-inline-start de .repere. Ne pas la « simplifier ».
		'massifs-composants' => array(
			'chemin' => 'assets/css/composants.css',
			'deps'   => array( 'massifs-tokens', 'massifs-layout' ),
		),
		'massifs-print'      => array(
			'chemin' => 'assets/css/print.css',
			'deps'   => array( 'massifs-tokens', 'massifs-composants' ),
			'media'  => 'print',
		),
	);

	foreach ( $feuilles as $poignee => $feuille ) {
		$source = is_readable( get_theme_file_path( $feuille['chemin'] ) )
			? get_theme_file_uri( $feuille['chemin'] )
			: false;

		wp_register_style(
			$poignee,
			$source,
			$feuille['deps'],
			massifs_version_asset( $feuille['chemin'] ),
			$feuille['media'] ?? 'all'
		);
		wp_enqueue_style( $poignee );
	}
}
add_action( 'wp_enqueue_scripts', 'massifs_enfiler_styles' );

/**
 * Précharge les deux fichiers de police, servis depuis notre domaine.
 *
 * `crossorigin` est obligatoire MÊME EN MÊME ORIGINE : l'omettre provoque un
 * double téléchargement, la requête de préchargement et celle de la police
 * n'étant alors pas dans le même mode CORS. L'URL ne porte pas de paramètre de
 * version, exactement comme celle demandée par les url() de fonts.css : une
 * URL différente serait, elle aussi, un double téléchargement.
 *
 * fonts.css emploie `font-display: optional` — sans ce préchargement, ce choix
 * perd tout son intérêt.
 */
function massifs_precharger_polices(): void {
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
// Priorité 2 : après wp_enqueue_scripts (1) et AVANT wp_print_styles (8), sans
// quoi le préchargement arriverait après la feuille qui demande la police.
add_action( 'wp_head', 'massifs_precharger_polices', 2 );

/**
 * Retire du front les feuilles de style du cœur que ce thème n'emploie pas.
 *
 * `global-styles` injecte un SECOND système de custom properties
 * (--wp--preset--*), interdit par MASTER.md §12 ; le CSS de blocs est un
 * framework CSS générique, interdit par la contrainte n° 1 du projet.
 *
 * `wp_dequeue_style` et jamais `wp_deregister_style` : désenregistrer casserait
 * une dépendance déclarée par un tiers. On n'enfile pas, on ne détruit pas.
 * Priorité 100, donc après tous les enfilements du cœur (qui ont lieu à 10).
 *
 * DEUX POINTS D'ACCROCHE, et c'est nécessaire : depuis WordPress 6.9,
 * `wp_enqueue_global_styles()` n'enfile plus « global-styles » sur
 * `wp_enqueue_scripts` pour un thème classique — il pose une poignée-placeholder
 * dans l'entête, enfile la vraie feuille sur `wp_footer` (priorité 1), puis
 * `wp_hoist_late_printed_styles()` la remonte dans le <head> en remplaçant le
 * placeholder. Un retrait posé sur le seul `wp_enqueue_scripts` laisse donc
 * passer l'intégralité des --wp--preset--*. Vérifié sur WordPress 7.0.2 :
 * le tag Docker n'étant pas épinglé, les deux mécanismes coexistent.
 */
function massifs_retirer_feuilles_du_coeur(): void {
	$poignees = array(
		'wp-block-library',
		'wp-block-library-theme',
		'classic-theme-styles',
		'global-styles',
		'wp-global-styles-placeholder',
	);

	foreach ( $poignees as $poignee ) {
		wp_dequeue_style( $poignee );
	}
}
add_action( 'wp_enqueue_scripts', 'massifs_retirer_feuilles_du_coeur', 100 );
add_action( 'wp_footer', 'massifs_retirer_feuilles_du_coeur', 2 );

/**
 * Neutralise entièrement les émoji du cœur.
 *
 * C'est la contrainte non négociable n° 2 : le script de détection porte
 * `settings.baseUrl = "https://s.w.org/images/core/emoji/…"`, et le repli
 * remplace le glyphe par un <img> vers s.w.org — une requête navigateur vers
 * un domaine tiers, sur une page publique.
 *
 * La version de WordPress n'est épinglée nulle part (`FROM wordpress:php8.3-apache`),
 * donc les noms pré-6.4 ET post-6.4 sont retirés. Un `remove_action()` sur un
 * couple inexistant est un no-op silencieux : le coût est nul.
 */
function massifs_neutraliser_emoji(): void {
	// LE PIÈGE : default-filters.php déclare cette action à la priorité 7.
	// Sans le troisième argument, le retrait viserait la priorité 10, ne
	// retirerait rien, et retournerait false sans rien signaler.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

	// Styles — noms d'avant WordPress 6.4.
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );

	// Styles — noms de WordPress 6.4 et au-delà.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'wp_print_styles', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_print_styles', 'wp_enqueue_emoji_styles' );

	// Statisation en <img> vers s.w.org dans les flux et les courriels.
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter( 'tiny_mce_plugins', 'massifs_retirer_greffon_emoji' );
	add_filter( 'wp_resource_hints', 'massifs_retirer_indices_emoji', 10, 2 );

	// Ceinture et bretelles : si un script émoji refuyait un jour, plus aucune
	// URL distante ne serait composée. Les remove_action ci-dessus ayant déjà
	// retiré les scripts, ce filtre ne peut produire aucun src="".
	add_filter( 'emoji_svg_url', '__return_false' );
}
// `init` : après default-filters.php et après l'enregistrement des filtres des extensions.
add_action( 'init', 'massifs_neutraliser_emoji' );

/**
 * Retire le greffon émoji de l'éditeur classique.
 *
 * @param mixed $greffons Liste des greffons TinyMCE.
 *
 * @return mixed Liste sans `wpemoji`.
 */
function massifs_retirer_greffon_emoji( $greffons ) {
	return is_array( $greffons ) ? array_diff( $greffons, array( 'wpemoji' ) ) : $greffons;
}

/**
 * Retire tout indice de ressource pointant vers s.w.org.
 *
 * La comparaison porte sur l'HÔTE, jamais sur l'URL exacte : la recette
 * classique (`array_diff` sur l'URL du jeu d'émoji) casse à chaque montée de
 * version du jeu, est aveugle à la forme protocole-relative `//s.w.org/…` et
 * ignore la forme tableau. La comparaison d'hôte est vraie quelle que soit la
 * version de WordPress — ce qu'exige un tag Docker non épinglé.
 *
 * @param mixed  $urls           Indices de ressource.
 * @param string $type_relation  Type de relation demandé.
 *
 * @return mixed Indices sans origine tierce.
 */
function massifs_retirer_indices_emoji( $urls, $type_relation ) {
	if ( 'dns-prefetch' !== $type_relation || ! is_array( $urls ) ) {
		return $urls;
	}

	$conserves = array();

	foreach ( $urls as $entree ) {
		if ( ! massifs_indice_vise_hote( $entree, 's.w.org' ) ) {
			$conserves[] = $entree;
		}
	}

	return $conserves;
}

/**
 * Un indice de ressource vise-t-il cet hôte ?
 *
 * Le cœur admet deux formes : une chaîne, ou un tableau portant `href`. Une
 * entrée d'une troisième forme est déclarée « ne vise pas cet hôte » et sera
 * donc conservée : on ne supprime jamais à l'aveugle ce qu'on ne sait pas lire.
 *
 * @param mixed  $entree Entrée d'indice de ressource.
 * @param string $hote   Hôte recherché.
 */
function massifs_indice_vise_hote( $entree, string $hote ): bool {
	if ( is_string( $entree ) ) {
		$href = $entree;
	} elseif ( is_array( $entree ) && array_key_exists( 'href', $entree ) && is_string( $entree['href'] ) ) {
		$href = $entree['href'];
	} else {
		return false;
	}

	return $hote === wp_parse_url( $href, PHP_URL_HOST );
}
