<?php
/**
 * Bloc post-publication de l'écran de publication.
 *
 * Trois mécanismes d'annonce se superposent (contrat #14 §5), et deux fonctionnent
 * sans une ligne de JavaScript : le `<title>` du document (posé par `assets.php`),
 * la cible de fragment `#massifs-recapitulatif` sur un conteneur `tabindex="-1"`,
 * et `role="status"`. Le troisième n'apporte rien au chargement — une région
 * `aria-live` présente dès le chargement n'est pas une mutation pour l'API
 * d'accessibilité — il est écrit parce qu'il est normatif (MASTER §7.2) et qu'un
 * enrichissement JavaScript ultérieur le rendrait correct sans retoucher ce
 * gabarit.
 *
 * Bloc persistant et imprimable, jamais une notification fugitive : c'est la trace
 * de ce qui vient d'être publié. Ni `role="alert"`, ni `autofocus`, ni modale.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_rendre_massifs_nommes' ) ) {
	/**
	 * Rend une liste de massifs nommés, chacun en lien vers son ancre.
	 *
	 * Le lien met le gestionnaire à une touche « Entrée » de la ligne à corriger :
	 * c'est la raison d'être de ces listes, jamais un ornement.
	 *
	 * @param array  $massifs      Entrées `['code','libelle','ancre']`, plus `message` pour les refus.
	 * @param string $intitule     Intitulé rédigé par le serveur, ou chaîne vide.
	 * @param string $modifieur    Suffixe de classe de la liste.
	 * @param string $ancre_id     Identifiant de l'intitulé, pour `aria-labelledby`.
	 * @param bool   $avec_message Vrai pour rendre le message porté par chaque entrée.
	 */
	function massifs_publication_rendre_massifs_nommes( array $massifs, string $intitule, string $modifieur, string $ancre_id, bool $avec_message ): void {
		if ( array() === $massifs ) {
			return;
		}
		?>
		<?php if ( '' !== $intitule ) : ?>
		<p class="massifs-recapitulatif__intitule" id="<?php echo esc_attr( $ancre_id ); ?>"><?php echo esc_html( $intitule ); ?></p>
		<?php endif; ?>
		<ul class="massifs-recapitulatif__liste massifs-recapitulatif__liste--<?php echo esc_attr( $modifieur ); ?>"<?php if ( '' !== $intitule ) : ?> aria-labelledby="<?php echo esc_attr( $ancre_id ); ?>"<?php endif; ?>>
			<?php foreach ( $massifs as $massif ) : ?>
			<li class="massifs-recapitulatif__element">
				<a class="massifs-recapitulatif__lien" href="<?php echo esc_url( '#' . $massif['ancre'] ); ?>"><?php echo esc_html( $massif['libelle'] ); ?></a>
				<?php if ( $avec_message ) : ?>
				<span class="massifs-recapitulatif__message"><?php echo esc_html( $massif['message'] ); ?></span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}

if ( ! function_exists( 'massifs_publication_rendre_recapitulatif' ) ) {
	/**
	 * Rend le compte rendu de la dernière publication.
	 *
	 * @param array $recapitulatif Bloc `recapitulatif` du modèle de vue.
	 */
	function massifs_publication_rendre_recapitulatif( array $recapitulatif ): void {
		if ( ! $recapitulatif['present'] ) {
			return;
		}
		?>
		<div
			class="massifs-recapitulatif massifs-recapitulatif--<?php echo esc_attr( $recapitulatif['ton'] ); ?>"
			id="massifs-recapitulatif"
			role="status"
			tabindex="-1"
			aria-labelledby="massifs-recapitulatif-titre"
		>
			<h2 class="massifs-recapitulatif__titre" id="massifs-recapitulatif-titre"><?php echo esc_html( $recapitulatif['titre'] ); ?></h2>

			<?php if ( '' !== $recapitulatif['resume'] ) : ?>
			<p class="massifs-recapitulatif__resume"><?php echo esc_html( $recapitulatif['resume'] ); ?></p>
			<?php endif; ?>

			<?php
			massifs_publication_rendre_massifs_nommes(
				$recapitulatif['manquants'],
				$recapitulatif['manquants_intitule'],
				'manquants',
				'massifs-recapitulatif-manquants',
				false
			);

			massifs_publication_rendre_massifs_nommes(
				$recapitulatif['zapef_perdue'],
				$recapitulatif['omission_zapef'],
				'zapef',
				'massifs-recapitulatif-zapef',
				false
			);

			// Le modèle ne porte aucun intitulé pour les refus : le résumé du serveur
			// en tient lieu, et en inventer un serait rédiger une chaîne de portail.
			massifs_publication_rendre_massifs_nommes(
				$recapitulatif['refus'],
				'',
				'refus',
				'massifs-recapitulatif-refus',
				true
			);
			?>
		</div>
		<?php
	}
}
