<?php
/**
 * Partie de gabarit — bandeau de non-officialité (§5.6 du brief).
 *
 * Rend la mention obligatoire sur toute page affichant un statut. C'est la
 * seule des quatre parties de la chaîne #6 qui ne rend jamais zéro octet.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/bandeau-non-officialite', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   attribution  array  Défaut : massifs_attribution_statuts(). Seule source de
 *                       `carte_officielle_url`. Absente ET extension absente
 *                       ⇒ la phrase est rendue SANS lien (contrat #6, F) : sans
 *                       extension aucun statut n'est affiché, l'obligation du
 *                       §5.6 n'est donc pas déclenchée, et un lien mort serait
 *                       pire qu'une phrase seule.
 *
 * Gabarit pur, sans aucune déclaration : `load_template()` fait un `require` et
 * non un `require_once`, une partie incluse deux fois est donc ré-exécutée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arguments = isset( $args ) && is_array( $args ) ? $args : array();

$attribution = isset( $arguments['attribution'] ) && is_array( $arguments['attribution'] )
	? $arguments['attribution']
	: array();

if ( array() === $attribution && function_exists( 'massifs_attribution_statuts' ) ) {
	$attribution = massifs_attribution_statuts();
}

// L'adresse n'est jamais rédigée ici : elle vient d'une source unique
// (contrat #6, arbitrage F′), jamais de massifs_legende()['source_officielle_url'].
$carte_officielle = isset( $attribution['carte_officielle_url'] ) && is_string( $attribution['carte_officielle_url'] )
	? trim( $attribution['carte_officielle_url'] )
	: '';

// esc_url() vide toute adresse de protocole non autorisé : on ne rend le lien
// que s'il survit à l'échappement, plutôt que d'écrire un lien mort.
$carte_officielle_liee = '' !== esc_url( $carte_officielle );
?>
<div class="bandeau-non-officialite">
<p class="bandeau-non-officialite__texte">Site d'information indépendant. Seules les publications de la préfecture des Bouches-du-Rhône font foi<?php if ( $carte_officielle_liee ) : ?> : <a class="bandeau-non-officialite__lien" href="<?php echo esc_url( $carte_officielle ); ?>">carte officielle de la préfecture</a><?php endif; ?>.</p>
</div>
