<?php
/**
 * Ouverture du document : <head>, liens d'évitement, barre haute, ouverture de <main>.
 *
 * Inclus par get_template_part( 'templates/header' ).
 * get_header() est INTERDIT dans ce thème : il chargerait header.php à la
 * RACINE du thème, fichier qui n'existe pas et ne doit pas exister.
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
	<?php // Jamais maximum-scale, jamais user-scalable=no : le zoom 200 % est bloquant. ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php // add_theme_support( 'title-tag' ) n'est pas déclaré : wp_head() imprimerait un second <title> dans index.php. ?>
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
	<?php
	// Les liens d'évitement sont les tout premiers éléments focusables du
	// document. Le second n'est rendu que là où sa cible existe : un lien
	// d'évitement vers une ancre absente est pire que son absence.
	?>
	<div class="liens-evitement">
		<a class="lien-evitement" href="#contenu-principal">Aller au contenu</a>
		<?php if ( is_front_page() ) : ?>
			<a class="lien-evitement" href="#liste">Aller à la liste des statuts</a>
		<?php endif; ?>
	</div>

	<header class="barre sur-sombre">
		<div class="barre__contenu">
			<?php // Le nom du site est un <p>, jamais un h1 : l'unique h1 de l'accueil est la phrase de synthèse de l'ardoise. ?>
			<p class="barre__nom">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			</p>
			<?php
			// massifs_menu() garde l'appel par has_nav_menu() : un emplacement non
			// affecté ne produit AUCUN <nav>, plutôt qu'un landmark de navigation vide.
			massifs_menu( 'principal', 'barre' );
			?>
		</div>
	</header>

	<?php // tabindex="-1" : sans lui, plusieurs navigateurs déplacent le défilement sans déplacer le focus. ?>
	<main id="contenu-principal" tabindex="-1">
