<?php
/**
 * Gabarit minimal du thème Massifs — squelette d'amorçage.
 *
 * Le rendu serveur réel des statuts, les gabarits dédiés et la structure de
 * page complète sont la responsabilité des chaînes fonctionnelles (voir
 * docs/BRIEF.md et CLAUDE.md). Ce fichier existe uniquement pour que le thème
 * soit valide et activable par la stack Docker locale.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<main id="contenu-principal">
		<p><?php esc_html_e( 'Thème Massifs — squelette d’amorçage.', 'massifs' ); ?></p>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
