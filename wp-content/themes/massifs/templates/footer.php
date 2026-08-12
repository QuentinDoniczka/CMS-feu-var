<?php
/**
 * Fermeture du document : </main>, pied de page, attributions.
 *
 * Inclus par get_template_part( 'templates/footer' ).
 * get_footer() est INTERDIT dans ce thème : il chargerait footer.php à la
 * RACINE du thème, fichier qui n'existe pas et ne doit pas exister.
 *
 * Le pied porte l'EMPLACEMENT des mentions légales — l'emplacement de menu
 * « pied » — et jamais leur copie : aucun lien codé en dur vers une page qui
 * n'existe pas encore, ce qui produirait une 404 dans le chrome de chaque page.
 *
 * Ni EFFIS, ni Météo-France, ni OpenStreetMap ne sont crédités : créditer une
 * source dont aucune donnée n'est affichée est une affirmation fausse. Chaque
 * attribution arrive avec sa couche.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	</main>

	<footer class="pied sur-sombre">
		<div class="pied__contenu">
			<?php
			// Emplacement des mentions légales. Comme la barre haute : sans menu
			// affecté, aucun <nav> n'est émis. L'emplacement existe, il se tait
			// quand il est vide.
			massifs_menu( 'pied', 'pied' );
	
			/*
			 * Attributions : rendues telles quelles, JAMAIS rédigées à la main ni
			 * découpées pour y insérer un lien. La phrase des périmètres est imposée
			 * par la Licence Ouverte 2.0 et porte la date des données ; elle est vide
			 * quand le référentiel est indisponible, et rien ne s'affiche alors.
			 */
			if ( function_exists( 'massifs_attribution' ) ) {
				$attribution_perimetres = massifs_attribution();
	
				if ( '' !== $attribution_perimetres['phrase'] ) {
					printf( '<p class="pied__attribution">%s</p>', esc_html( $attribution_perimetres['phrase'] ) );
				}
			}
	
			if ( function_exists( 'massifs_attribution_statuts' ) ) {
				$attribution_statuts = massifs_attribution_statuts();
	
				if ( '' !== $attribution_statuts['texte'] ) {
					printf( '<p class="pied__attribution">%s</p>', esc_html( $attribution_statuts['texte'] ) );
				}
			}
			?>
		</div>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
