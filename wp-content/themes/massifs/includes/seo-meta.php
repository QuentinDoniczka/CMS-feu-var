<?php
/**
 * Métadonnées de <head> des trois gabarits éditoriaux, et fait d'identité partagé.
 *
 * Ce fichier est chargé par les gabarits EUX-MÊMES, et non par `functions.php` :
 * `functions.php` ne fait aucun `require` et est hors de l'empreinte de l'issue
 * #18 (arbitrage A-3 du contrat). L'inclusion a lieu AVANT
 * `get_template_part( 'templates/header' )`, seul moment où `wp_head()` n'a pas
 * encore été appelé — après l'en-tête, le <head> est clos et il est trop tard.
 *
 * Périmètre volontairement réduit à une seule balise :
 * - AUCUN `add_theme_support( 'title-tag' )` — `templates/header.php` imprime
 *   déjà <title> depuis `wp_get_document_title()` ; en déclarer le support
 *   ferait imprimer un second <title> par `wp_head()`.
 * - AUCUN fournisseur de plan de site, en particulier `users` ou `authors` : la
 *   chaîne #16 le retire inconditionnellement au titre du §9 du brief
 *   (fermeture de l'énumération), et ce comportement l'emporte.
 * - AUCUNE balise `og:` ni `twitter:` : les aperçus de partage sont en sommeil.
 *
 * Le fichier est inclus par trois gabarits ; toutes ses déclarations sont donc
 * gardées, et l'accrochage à `wp_head` ne peut pas être enregistré deux fois.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MASSIFS_CONTACT' ) ) {
	/*
	 * Adresse de contact du §4 du contrat #18, déclarée UNE SEULE FOIS. Les
	 * mentions légales et le signalement d'accessibilité la lisent ici au lieu
	 * de la recopier chacune : le jour où le projet ouvre une adresse dédiée,
	 * il n'y a qu'un point à modifier.
	 *
	 * Elle vit dans ce fichier faute d'autre fichier partagé par les trois
	 * gabarits — l'empreinte de l'issue #18 n'en autorise pas un second.
	 */
	/*
	 * FOURNIE ET VALIDÉE par le propriétaire du projet, relayée par
	 * l'orchestrateur — voir l'avis de provenance du contrat #18 §4.
	 *
	 * DÉCLARÉE ICI ET NULLE PART AILLEURS. Employée par DEUX pages : les
	 * mentions légales (contact de l'éditeur) et « Accessibilité » (canal de
	 * signalement du §8 du brief). Recopiée dans les deux gabarits, elle
	 * finirait par diverger — et c'est le canal de signalement d'accessibilité
	 * qui pointerait un jour dans le vide.
	 *
	 * REMPLAÇABLE : c'est l'adresse personnelle du propriétaire du projet, pas
	 * une boîte dédiée. Le jour où une adresse propre au site existe, elle se
	 * change ICI, une fois, et les deux pages suivent.
	 *
	 * La garde de vacuité des deux gabarits est CONSERVÉE bien que la valeur
	 * soit renseignée : elle coûte une comparaison et elle empêche qu'un
	 * `mailto:` vide reparaisse silencieusement si quelqu'un vide cette
	 * constante — sur le canal de signalement, un lien mort est pire qu'une
	 * absence de lien.
	 */
	define( 'MASSIFS_CONTACT', 'doniczka.quentin67@gmail.com' );
}

if ( ! function_exists( 'massifs_meta_description' ) ) {
	/**
	 * Registre de la description de la page courante.
	 *
	 * Écriture quand `$description` est fournie, lecture sinon. Une seule
	 * variable statique porte l'état : deux fonctions ne peuvent pas partager
	 * un statique, et une globale serait modifiable par n'importe qui.
	 *
	 * @param string|null $description Description à enregistrer, `null` pour lire.
	 *
	 * @return string Description courante, chaîne vide tant qu'aucune n'est déclarée.
	 */
	function massifs_meta_description( ?string $description = null ): string {
		static $courante = '';

		if ( null !== $description ) {
			$courante = trim( $description );
		}

		return $courante;
	}
}

if ( ! function_exists( 'massifs_imprimer_description' ) ) {
	/**
	 * Imprime la description dans le <head>.
	 *
	 * Rien n'est imprimé pour une description vide : une balise `content=""`
	 * décrit la page comme vide au lieu de ne pas la décrire.
	 */
	function massifs_imprimer_description(): void {
		$description = massifs_meta_description();

		if ( '' === $description ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}

if ( ! function_exists( 'massifs_declarer_description' ) ) {
	/**
	 * Déclare la description de la page courante et arme son impression.
	 *
	 * La description est FOURNIE PAR L'APPELANT et jamais déduite du contenu :
	 * une description tronquée depuis `the_content()` couperait au milieu d'une
	 * phrase et changerait à chaque révision éditoriale.
	 *
	 * @param string $description Description de la page.
	 */
	function massifs_declarer_description( string $description ): void {
		massifs_meta_description( $description );

		// Garde d'enregistrement : le fichier est inclus par trois gabarits, et
		// un seul d'entre eux s'exécute par requête — mais la comparaison
		// stricte à `false` reste la seule forme correcte, `has_action()`
		// retournant une PRIORITÉ, qui vaut 0 dans le cas général.
		if ( false === has_action( 'wp_head', 'massifs_imprimer_description' ) ) {
			add_action( 'wp_head', 'massifs_imprimer_description', 1 );
		}
	}
}
