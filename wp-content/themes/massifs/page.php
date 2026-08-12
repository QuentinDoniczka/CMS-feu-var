<?php
/**
 * Gabarit des pages statiques — les quatre pages éditoriales du brief §5.1.
 *
 * Ce gabarit n'affiche aucun statut : il ne lit aucune donnée de domaine et
 * n'appelle aucune fonction de massifs-core. Il rend donc le même HTML que
 * l'extension soit active ou non, et la règle §4.2 (« jamais un statut périmé
 * présenté comme courant ») y est tenue par construction, faute de statut.
 *
 * L'accueil ne passe jamais par ici : template-loader.php évalue is_front_page
 * AVANT is_page, dans les deux configurations de show_on_front. front-page.php
 * garde la main.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'templates/header' );

// `if` et NON `while` : avec une boucle, le nombre de h1 émis serait une
// fonction de $wp_query->post_count, valeur qu'un pre_get_posts d'extension
// peut porter à 2 ou à 25. Avec `if` + un seul the_post(), il est une constante
// du code source — 0 ou 1, jamais plus. Sur une requête singulière les deux
// formes sont équivalentes : la garantie ne coûte rien.
if ( have_posts() ) :
	the_post();
	?>

		<?php
		// <section> sans id, sans nom accessible et sans tabindex : exposée
		// « generic », elle ne crée aucun landmark vide (arbitrage A-17 du
		// contrat #5).
		?>
		<section class="bande bande--editorial">
			<div class="bande__contenu">
				<?php
				// the_title() et the_content() sont rendus SANS esc_html(), et ce
				// n'est pas un oubli — ne pas le « corriger » :
				// le filtre `the_title` applique wptexturize(), qui PRODUIT des
				// entités (&#8217;) ; les ré-encoder afficherait littéralement
				// « Mentions l&#8217;égales ». the_content() est un flux HTML
				// filtré par construction ; l'échapper viderait la page.
				the_title( '<h1>', '</h1>' );

				// the_content() est appelé SANS AUCUNE enveloppe : layout.css pose
				// le rythme vertical (l. 134), l'espacement des titres (l. 138) et
				// la mesure de 68ch (l. 143) en ENFANT DIRECT de .bande__contenu.
				// Une <div>, un <article> ou un entry-content ici ferait perdre
				// les trois règles d'un coup, silencieusement.
				the_content();
				?>
			</div>
		</section>

	<?php
else :
	// Cas pathologique — une page servie sans post, ce qu'un pre_get_posts tiers
	// est seul à pouvoir produire. La garde existe pour que le gabarit ne puisse
	// émettre ni h1 vide ni avertissement PHP. Rien de visible n'est rendu :
	// écrire une phrase de repli serait de la copie d'interface inventée.
	massifs_journaliser( 'massifs: page.php atteint sans post (contrat #24) — aucun titre de page rendu.' );
endif;

get_template_part( 'templates/footer' );
