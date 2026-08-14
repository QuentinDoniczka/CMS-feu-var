<?php
/**
 * Péremption DURE, appliquée à la LECTURE.
 *
 * À `T − 1 s` la couche est servie ; à `T + 1 s` elle ne l'est plus, ET
 * L'OPTION CONTIENT TOUJOURS LES POLYGONES. La péremption s'applique dans la
 * projection, jamais par effacement du stockage : effacer perdrait la trace
 * d'exploitation et ferait dépendre une règle de sécurité d'une tâche de
 * nettoyage qui peut ne jamais tourner.
 *
 * Au-delà de T, la bascule est ENTIÈRE : il n'existe aucun état intermédiaire,
 * aucune clé `perimee`. Servir une fenêtre glissante périmée sous un
 * avertissement laisserait lire « voici les zones parcourues par le feu » sous
 * une phrase que l'œil saute, alors qu'une zone survenue depuis en serait
 * absente.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\Settings;

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

if ( ! function_exists( 't_effis_dater_releve' ) ) {
	/**
	 * Recule l'horodatage du relevé stocké, sans toucher à son contenu.
	 *
	 * Le relevé reste celui produit par l'ingestion réelle : seul l'instant
	 * bouge, ce qui est exactement le paramètre que la garde de péremption lit.
	 *
	 * @param int $age_secondes Âge à simuler.
	 */
	function t_effis_dater_releve( int $age_secondes ): void {
		$releve = get_option( 'massifs_effis_releve', array() );

		if ( ! is_array( $releve ) ) {
			return;
		}

		$releve['releve_le'] = gmdate( Settings::FORMAT_ISO_UTC, time() - $age_secondes );

		update_option( 'massifs_effis_releve', $releve, false );
	}
}

t_reset();
t_effis_purge();

// Armement en cours de requête : voir l'en-tête du scénario 40.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

$emprise    = massifs_emprise();
$centre_lon = ( (float) $emprise['bbox']['ouest'] + (float) $emprise['bbox']['est'] ) / 2;
$centre_lat = ( (float) $emprise['bbox']['sud'] + (float) $emprise['bbox']['nord'] ) / 2;
$d          = 0.01;

$charge = array(
	'type'     => 'FeatureCollection',
	'features' => array(
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-2026-0001',
				'surface_ha'           => 61.0,
				'premiere_observation' => '2026-08-12T06:00:00Z',
				'derniere_observation' => '2026-08-13T06:00:00Z',
			),
			'geometry'   => array(
				'type'        => 'Polygon',
				'coordinates' => array(
					array(
						array( $centre_lon - $d, $centre_lat - $d ),
						array( $centre_lon + $d, $centre_lat - $d ),
						array( $centre_lon + $d, $centre_lat + $d ),
						array( $centre_lon - $d, $centre_lat + $d ),
						array( $centre_lon - $d, $centre_lat - $d ),
					),
				),
			),
		),
	),
);

t_bouchon_http( t_reponse_200( $charge ) );

$verdict = Runner::executer();
t_assert( true === $verdict, 'relevé accepté', true, is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

$peremption = Settings::peremption_secondes();
t_egal( 86400, $peremption, 'T = 86 400 s (24 h) par défaut' );

// --- T − 1 s : la couche est servie -----------------------------------------
t_effis_dater_releve( $peremption - 1 );
$frais = massifs_zones_parcourues_par_le_feu();
t_egal( 'zones_disponibles', $frais['etat'], 'à T − 1 s : la couche est SERVIE' );
t_egal( 1, $frais['nombre'], 'à T − 1 s : la zone est là' );
t_assert( '' !== $frais['releve_le'], 'à T − 1 s : la date de mesure est servie', 'un instant', $frais['releve_le'] );

// --- T + 1 s : la couche disparaît ENTIÈREMENT ------------------------------
t_effis_dater_releve( $peremption + 1 );
$perime = massifs_zones_parcourues_par_le_feu();
t_note( 'projection à T + 1 s = ' . wp_json_encode( $perime ) );

t_egal( 'couche_effis_indisponible', $perime['etat'], 'à T + 1 s : la couche n\'est PLUS servie' );
t_egal( 0, $perime['nombre'], 'à T + 1 s : nombre = 0' );
t_egal( array(), $perime['zones'], 'à T + 1 s : aucun polygone servi' );
t_egal( '', $perime['releve_le'], 'à T + 1 s : releve_le vide — une absence périmée n\'est pas une mesure' );
t_egal( false, array_key_exists( 'perimee', $perime ), 'AUCUNE clé `perimee` : la péremption RETIRE la couche, elle ne l\'annote pas' );

// --- ET L'OPTION CONTIENT TOUJOURS LES POLYGONES ----------------------------
$stocke = get_option( 'massifs_effis_releve', null );
t_assert( is_array( $stocke ) && 1 === count( $stocke['zones'] ), 'le stockage est INTACT : la péremption s\'applique à la lecture, jamais par effacement', 1, is_array( $stocke ) ? count( $stocke['zones'] ) : $stocke );
t_assert( isset( $stocke['zones'][0]['geometrie']['coordinates'] ) && array() !== $stocke['zones'][0]['geometrie']['coordinates'], 'les coordonnées sont toujours en base', 'un anneau', $stocke['zones'][0]['geometrie'] ?? null );

// La route publique tient la même règle, et répond 200 — pas 503.
$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );
$reponse = rest_do_request( new WP_REST_Request( 'GET', '/massifs/v1/zones-parcourues-par-le-feu' ) );
t_egal( 200, $reponse->get_status(), 'route : 200 même périmée — « indisponible » est un état de la donnée, pas une panne serveur' );
$corps = $reponse->get_data();
t_egal( 'couche_effis_indisponible', $corps['etat'], 'route : état périmé = indisponible' );
t_egal( array(), $corps['zones']['features'], 'route : aucune entité servie' );
t_egal( '', $corps['attribution'], 'route : aucune attribution sans donnée' );

// Un T redéfini par filtre reste borné : une règle de sécurité ne se desserre
// pas par un filtre.
add_filter( 'massifs_effis_peremption_secondes', static fn() => 1 );
t_egal( 3600, Settings::peremption_secondes(), 'T re-borné APRÈS filtre : plancher à 3 600 s' );
remove_all_filters( 'massifs_effis_peremption_secondes' );

t_effis_purge();
t_reset();
t_bilan();
