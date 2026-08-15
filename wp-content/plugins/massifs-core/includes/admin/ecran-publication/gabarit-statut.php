<?php
/**
 * Fragment `.statut` de l'écran de publication — marque + libellé.
 *
 * Reprend mot pour mot le vocabulaire de classes du thème
 * (`templates/parts/liste-statuts.php`) : `.statut`, `.statut__marque`,
 * `.statut__libelle`, `.pastille`, `.pastille--*`. Le mécanisme de conformité AA
 * des statuts (liseré 2 px + motif, MASTER §10.2 et §10.3) est porté par
 * `composants.css`, enfilée depuis le thème : réécrire ces classes ici créerait
 * une divergence silencieuse sur un encodage de sécurité.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_rendre_statut' ) ) {
	/**
	 * Rend une marque de statut suivie de son libellé.
	 *
	 * Le suffixe de classe vient du modèle et n'est jamais composé ici : seule la
	 * classe de base `pastille`, qui n'est pas dérivée d'une donnée, est littérale.
	 * La marque est `aria-hidden` parce que le libellé qui la suit porte déjà
	 * l'information — MASTER §10.6 : jamais la couleur seule, jamais deux fois le
	 * même mot pour un lecteur d'écran.
	 *
	 * @param string $classe_marque Suffixe de classe fourni par le modèle, ou chaîne vide.
	 * @param string $texte         Libellé déjà rédigé par le serveur.
	 */
	function massifs_publication_rendre_statut( string $classe_marque, string $texte ): void {
		if ( '' === $texte ) {
			return;
		}
		?>
		<span class="statut">
			<?php if ( '' !== $classe_marque ) : ?>
			<span class="statut__marque pastille <?php echo esc_attr( $classe_marque ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="statut__libelle"><?php echo esc_html( $texte ); ?></span>
		</span>
		<?php
	}
}

if ( ! function_exists( 'massifs_publication_rendre_etat' ) ) {
	/**
	 * Rend un bloc d'état du modèle (`reference` ou `enregistre`).
	 *
	 * Le contrat §6 fixe la correspondance : l'état `disponible` s'affiche avec le
	 * libellé officiel verbatim, les trois autres avec la phrase du serveur. Le
	 * modèle encode exactement cette règle en laissant `libelle` vide hors
	 * `disponible` et en garantissant `phrase` toujours non vide. Choisir entre deux
	 * chaînes fournies n'est pas en rédiger une, et prendre `libelle` en premier est
	 * ce qui garantit que le libellé officiel arrive intact à l'écran.
	 *
	 * @param array $etat Bloc `reference` ou `enregistre` du modèle de vue.
	 */
	function massifs_publication_rendre_etat( array $etat ): void {
		$texte = '' !== $etat['libelle'] ? $etat['libelle'] : $etat['phrase'];

		massifs_publication_rendre_statut( $etat['classe_marque'], $texte );
	}
}
