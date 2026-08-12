<?php
/**
 * Repli générique du thème — dernier gabarit de la hiérarchie WordPress.
 *
 * Depuis la création de page.php et de 404.php, il sert : les articles
 * singuliers, les pièces jointes, et les contextes non singuliers (recherche,
 * archives, auteur, date).
 *
 * Ce gabarit n'affiche aucun statut : il ne lit aucune donnée de domaine et
 * n'appelle aucune fonction de massifs-core. Il rend donc le même HTML que
 * l'extension soit active ou non.
 *
 * Aucun commentaire n'est rendu, et c'est un interdit, pas un oubli : l'article
 * de démonstration porte le commentaire par défaut de WordPress, dont l'avatar
 * est une requête vers secure.gravatar.com — violation de la contrainte non
 * négociable n° 2, sur l'URL même que la recette parcourt. Le brief §2 exclut
 * par ailleurs tout commentaire du périmètre.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'templates/header' );

// Deux gardes, pas une. `is_singular()` interdit d'émettre le bloc sur une
// archive ou une recherche (arbitrage A-4), et `if` — jamais `while` — fait du
// nombre de h1 une constante du code source : 0 ou 1, jamais plus, quel que
// soit le post_count que la requête porte.
if ( is_singular() && have_posts() ) :
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
	// Contexte non singulier : le chrome complet, et rien d'autre. Aucun h1 —
	// trou assumé et déclaré (arbitrage A-4). Fabriquer un titre imposerait
	// get_the_archive_title() (« Catégorie : Non classé »), du registre
	// « template institutionnel » que borne la contrainte non négociable n° 4,
	// et de la copie visible sans propriétaire au §11.3 de MASTER.md. Le site
	// n'a aucun blog au périmètre (brief §5.1, §13) ; la vraie fermeture est de
	// faire disparaître ces contextes depuis functions.php (demande F-4a).
	//
	// Aucune Boucle n'est ouverte : on ne parcourt pas les articles d'une
	// archive pour n'en rien faire.
	echo '<!-- massifs: contexte non singulier — aucun titre de page (contrat #24) -->';

	massifs_journaliser( 'massifs: contexte non singulier servi par index.php — aucun h1 émis, trou déclaré (contrat #24, arbitrage A-4).' );
endif;

get_template_part( 'templates/footer' );
