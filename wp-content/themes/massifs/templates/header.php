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
	//
	// La garde REFLÈTE celle de templates/parts/liste-statuts.php (chaîne #6) :
	// #5 appelant la partie sans aucun $args, sa garde d'extension se réduit
	// aux deux function_exists ci-dessous, et la partie rend alors zéro octet —
	// donc pas d'ancre `liste`. Toute évolution de la garde de la partie doit
	// être répercutée ici. Le test de locate_template() couvre en plus le cas
	// où l'extension est active mais le fichier de partie absent.
	$massifs_ancre_liste = is_front_page()
		&& function_exists( 'massifs_referentiel' )
		&& function_exists( 'massifs_statuts_du_jour' )
		&& '' !== locate_template( 'templates/parts/liste-statuts.php' );
	?>
	<div class="liens-evitement">
		<a class="lien-evitement" href="#contenu-principal">Aller au contenu</a>
		<?php if ( $massifs_ancre_liste ) : ?>
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
