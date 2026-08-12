<?php
/**
 * Gabarit de la page introuvable — servi par le cœur sur is_404().
 *
 * AUCUNE Boucle ici : have_posts(), the_post() et the_title() sont interdits
 * par l'arbitrage A-6 du contrat #24. WP_Query::set_404() réinitialise bien
 * is_single / is_page / is_attachment, mais le $post global peut porter un
 * reliquat — c'est le seul chemin par lequel le titre d'un AUTRE document
 * pourrait s'afficher comme titre de cette page. L'interdit est structurel,
 * pour n'avoir pas à s'en remettre à la vigilance.
 *
 * Aucun status_header() : le cœur a déjà émis le 404 avant d'arriver ici.
 * Aucun formulaire de recherche, aucune liste de pages, aucune excuse — le §11.1
 * de MASTER.md borne la voix, et rien d'autre n'est rédigé.
 *
 * Les deux chaînes ci-dessous sont livrées NON RATIFIÉES (arbitrage A-1,
 * demande F-1 à lead-design-cms) : le §11.3 de MASTER.md est une liste fermée
 * qui ne couvre aucune page 404.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'templates/header' );
?>

		<?php
		// <section> sans id, sans nom accessible et sans tabindex : elle est
		// exposée « generic » et ne crée donc aucun landmark vide (même raison
		// que l'arbitrage A-17 du contrat #5 pour la bande carte).
		?>
		<section class="bande bande--editorial">
			<div class="bande__contenu">
				<h1>Cette adresse ne correspond à aucune page de ce site.</h1>
				<?php
				// Le <p> est voulu : un <a> enfant direct de .bande__contenu
				// recevrait le rythme vertical mais pas la mesure de ligne posée
				// par `:where(.bande__contenu) > p` (layout.css l. 143).
				// L'apostrophe de « Aller à l’accueil » est U+2019 — arbitrage
				// A-15 du contrat #5 : U+2019 pour toute prose du thème.
				?>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Aller à l’accueil</a></p>
			</div>
		</section>

<?php
get_template_part( 'templates/footer' );
