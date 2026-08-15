<?php
/**
 * Une ligne de massif de l'écran de publication.
 *
 * `<li>` + `<fieldset>` + `<legend>`, jamais `<table>` : déviation D-1 du contrat
 * #14. Dans un tableau, le groupe de radios logé dans une cellule n'a aucun nom
 * accessible propre, et #28 a mesuré que l'association en-tête ↔ cellule est
 * « rendue possible », jamais « rétablie ». Sur un écran d'écriture, entendre
 * « Accès au massif interdit » sans savoir de quel massif, puis le publier, est la
 * faute qu'on ne prend pas. Le `<legend>` produit ce nom sans intermédiaire.
 *
 * Chaque donnée en lecture seule porte son étiquette dans un `<span>` réel :
 * motif S-2 déposé par le contrat #28, appliqué du premier coup, jamais un
 * `::before` en contenu généré.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_rendre_ligne' ) ) {
	/**
	 * Rend le groupe de saisie d'un massif.
	 *
	 * @param array $ligne              Une entrée de `lignes` du modèle de vue.
	 * @param array $niveaux            Options de la paire segmentée, dans l'ordre de la légende.
	 * @param array $chaines            Étiquettes rédigées par le serveur.
	 * @param bool  $reference_repliee  Vrai quand la colonne de référence redouble le jour édité.
	 */
	function massifs_publication_rendre_ligne( array $ligne, array $niveaux, array $chaines, bool $reference_repliee ): void {
		$refus_present = (bool) $ligne['refus']['present'];
		$modification  = $ligne['modification'];
		?>
		<li class="massifs-liste__element" id="<?php echo esc_attr( $ligne['ancre'] ); ?>" tabindex="-1">
			<fieldset class="massifs-ligne">
				<legend class="massifs-ligne__nom"><?php echo esc_html( $ligne['libelle'] ); ?></legend>

				<?php if ( ! $reference_repliee ) : ?>
				<p class="massifs-ligne__donnee massifs-ligne__donnee--reference">
					<span class="massifs-ligne__etiquette"><?php echo esc_html( $chaines['etiquette_reference'] ); ?></span>
					<span class="massifs-ligne__valeur"><?php massifs_publication_rendre_etat( $ligne['reference'] ); ?></span>
				</p>
				<?php endif; ?>

				<p class="massifs-ligne__donnee massifs-ligne__donnee--enregistre">
					<span class="massifs-ligne__etiquette"><?php echo esc_html( $chaines['etiquette_enregistre'] ); ?></span>
					<span class="massifs-ligne__valeur"><?php massifs_publication_rendre_etat( $ligne['enregistre'] ); ?></span>
				</p>

				<div class="massifs-ligne__choix">
					<span class="massifs-ligne__etiquette"><?php echo esc_html( $chaines['etiquette_niveau'] ); ?></span>

					<?php if ( $refus_present ) : ?>
					<p class="massifs-ligne__erreur" id="<?php echo esc_attr( $ligne['refus']['id'] ); ?>"><?php echo esc_html( $ligne['refus']['message'] ); ?></p>
					<?php endif; ?>

					<div class="massifs-segmentee">
						<?php foreach ( $niveaux as $niveau ) : ?>
							<?php $identifiant_option = $ligne['id_base'] . '-' . $niveau['cle']; ?>
						<label class="massifs-segmentee__option" for="<?php echo esc_attr( $identifiant_option ); ?>">
							<input
								class="massifs-segmentee__radio"
								type="radio"
								id="<?php echo esc_attr( $identifiant_option ); ?>"
								name="<?php echo esc_attr( $ligne['champ'] ); ?>"
								value="<?php echo esc_attr( $niveau['cle'] ); ?>"
								<?php if ( $refus_present ) : ?>
								aria-describedby="<?php echo esc_attr( $ligne['refus']['id'] ); ?>"
								<?php endif; ?>
								<?php checked( $ligne['valeur_cochee'], $niveau['cle'] ); ?>
							>
							<?php massifs_publication_rendre_statut( $niveau['classe_marque'], $niveau['libelle'] ); ?>
						</label>
						<?php endforeach; ?>
					</div>
				</div>

				<p class="massifs-ligne__donnee massifs-ligne__donnee--modification">
					<span class="massifs-ligne__etiquette"><?php echo esc_html( $chaines['etiquette_modification'] ); ?></span>
					<span class="massifs-ligne__valeur">
						<?php if ( ! $modification['renseignee'] ) : ?>
							<?php echo esc_html( $modification['phrase'] ); ?>
						<?php else : ?>
							<?php if ( '' !== $modification['auteur'] ) : ?>
						<span class="massifs-ligne__auteur"><?php echo esc_html( $modification['auteur'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $modification['texte'] && '' !== $modification['attr_datetime'] ) : ?>
						<time class="massifs-ligne__instant" datetime="<?php echo esc_attr( $modification['attr_datetime'] ); ?>"><?php echo esc_html( $modification['texte'] ); ?></time>
							<?php elseif ( '' !== $modification['texte'] ) : ?>
						<span class="massifs-ligne__instant"><?php echo esc_html( $modification['texte'] ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</p>
			</fieldset>
		</li>
		<?php
	}
}
