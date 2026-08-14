<?php
/**
 * L'ASSERTION N° 1 DE L'ISSUE #11.
 *
 * `aucune_zone` et `couche_effis_indisponible` portent TOUS DEUX `nombre === 0`.
 * Ils ne doivent JAMAIS produire le même rendu. Ce qui les sépare est
 * `releve_le` : renseigné dans le premier, chaîne vide dans le second. « Vide
 * parce que mesuré » porte une date de mesure ; « vide parce que muet » n'en
 * porte aucune.
 *
 * Un consommateur qui teste `count( $zones ) === 0` pour décider quoi afficher
 * écrit « aucune zone parcourue par le feu » alors que la vérité est « nous ne
 * savons pas ». C'est un FAUX NÉGATIF SUR UNE DONNÉE DE SÉCURITÉ — le mode de
 * défaillance du §4.2 du brief, atteint par la route inverse.
 *
 * Ce scénario rejoue les deux états dans la même requête et les compare
 * explicitement, clé par clé.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Couche;
use Massifs\Ingest\Effis\Runner;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options de ce module, que `t_reset()` ne connaît pas.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

t_reset();
t_effis_purge();

// Armement en cours de requête : voir l'en-tête du scénario 40.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

// ---------------------------------------------------------------------------
// ÉTAT 1 — rien n'a JAMAIS été relevé : « nous ne savons pas ».
// ---------------------------------------------------------------------------
$indisponible = massifs_zones_parcourues_par_le_feu();
t_note( 'couche_effis_indisponible = ' . wp_json_encode( $indisponible ) );

t_egal( 'couche_effis_indisponible', $indisponible['etat'], 'aucun relevé jamais ⇒ couche_effis_indisponible' );
t_egal( 0, $indisponible['nombre'], 'nombre = 0' );
t_egal( array(), $indisponible['zones'], 'liste vide' );
t_egal( '', $indisponible['releve_le'], 'releve_le VIDE : rien n\'a été mesuré' );
t_egal( '', $indisponible['expire_le'], 'expire_le vide' );
t_egal( 7, $indisponible['fenetre_jours'], 'les faits de la couche restent publiés même indisponible' );

// ---------------------------------------------------------------------------
// ÉTAT 2 — un relevé validé, et il est vide : « aucune zone, et nous l'avons mesuré ».
// ---------------------------------------------------------------------------
t_bouchon_http(
	t_reponse_200(
		array(
			'type'     => 'FeatureCollection',
			'features' => array(),
		)
	)
);

$verdict = Runner::executer();
t_assert( true === $verdict, 'le lot vide est accepté', true, is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

$aucune = massifs_zones_parcourues_par_le_feu();
t_note( 'aucune_zone = ' . wp_json_encode( $aucune ) );

t_egal( 'aucune_zone', $aucune['etat'], 'relevé validé et vide ⇒ aucune_zone' );
t_egal( 0, $aucune['nombre'], 'nombre = 0' );

// ---------------------------------------------------------------------------
// LA COMPARAISON CROISÉE — le cœur de l'issue.
// ---------------------------------------------------------------------------
t_egal( $indisponible['nombre'], $aucune['nombre'], 'les DEUX états portent nombre === 0 : `nombre` ne discrimine RIEN' );
t_egal( $indisponible['zones'], $aucune['zones'], 'les DEUX états portent une liste vide : `count( $zones )` ne discrimine RIEN' );

t_assert( $indisponible['etat'] !== $aucune['etat'], 'LE DISCRIMINANT EST `etat`, et les deux valeurs diffèrent', 'deux états distincts', $indisponible['etat'] . ' vs ' . $aucune['etat'] );
t_assert( $indisponible !== $aucune, 'les deux projections complètes DIFFÈRENT : elles ne peuvent pas produire le même rendu', 'projections distinctes', 'identiques' );
t_assert( '' === $indisponible['releve_le'] && '' !== $aucune['releve_le'], 'ce qui les sépare est `releve_le` : vide parce que MUET vs vide parce que MESURÉ', "'' vs un instant", $indisponible['releve_le'] . ' vs ' . $aucune['releve_le'] );

// L'énumération reste FERMÉE à trois valeurs : toute quatrième est un acte de
// contrat, jamais une surprise d'exécution.
t_egal( array( 'zones_disponibles', 'aucune_zone', 'couche_effis_indisponible' ), Couche::ETATS, 'énumération fermée à trois valeurs' );
t_assert( in_array( $indisponible['etat'], Couche::ETATS, true ) && in_array( $aucune['etat'], Couche::ETATS, true ), 'les deux états servis appartiennent à l\'énumération fermée', Couche::ETATS, array( $indisponible['etat'], $aucune['etat'] ) );

// Et la route publique les distingue elle aussi, corps pour corps.
$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );

$corps_aucune = rest_do_request( new WP_REST_Request( 'GET', '/massifs/v1/zones-parcourues-par-le-feu' ) )->get_data();

t_effis_purge();
$corps_indisponible = rest_do_request( new WP_REST_Request( 'GET', '/massifs/v1/zones-parcourues-par-le-feu' ) )->get_data();

t_egal( 0, $corps_aucune['nombre'], 'route : nombre = 0 dans l\'état aucune_zone' );
t_egal( 0, $corps_indisponible['nombre'], 'route : nombre = 0 dans l\'état indisponible' );
t_assert( $corps_aucune !== $corps_indisponible, 'route : les deux corps DIFFÈRENT', 'corps distincts', 'identiques' );
t_egal( '© Union européenne, Copernicus Emergency Management Service / EFFIS', $corps_aucune['attribution'], 'route : la donnée servie porte son attribution' );
t_egal( '', $corps_indisponible['attribution'], 'route : AUCUNE attribution quand aucune donnée n\'est servie — créditer une source dont rien n\'est affiché est une affirmation fausse' );

t_effis_purge();
t_reset();
t_bilan();
