<?php
/**
 * Panne réseau de la source : la dernière valeur connue est conservée
 * telle quelle, l'échec est compté, et la fraîcheur ne ment pas.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;
t_reset();
t_armer_connecteur();

use Massifs\Ingest\Prefecture\Connector;
use Massifs\Ingest\Prefecture\StateRepository;
use Massifs\Ingest\Prefecture\SnapshotRepository;

$aujourdhui = massifs_jour_courant();
$table      = $wpdb->prefix . 'massifs_statuts';

// État de départ : un statut du jour publié et un relevé réussi il y a 2 h.
$r = massifs_enregistrer_statut( array(
	'massif_code'   => 'sainte-victoire',
	'jour_validite' => $aujourdhui,
	'niveau_cle'    => 'interdit',
	'zapef_cle'     => 'autorise',
	'source'        => 'saisie_manuelle',
	'auteur_id'     => 1,
) );
t_assert( $r['enregistre'], 'état de départ : un statut du jour existe' );
massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z', time() - 2 * HOUR_IN_SECONDS ) );

$avant_lignes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
$avant_statut = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
$avant_frais  = massifs_fraicheur( $aujourdhui );
t_egal( 'disponible', $avant_statut['etat'], 'avant la panne : statut disponible' );
t_egal( false, $avant_frais['perimee'], 'avant la panne : donnée fraîche' );

// La source devient injoignable (erreur de transport, pas un code HTTP).
$boite = array();
t_intercepter_mail( $boite );
t_bouchon_http( new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) );

$res = Connector::run_now( $aujourdhui );
t_assert( is_wp_error( $res ), 'panne réseau : la récupération échoue explicitement', 'WP_Error', $res );
t_note( 'code d\'erreur : ' . ( is_wp_error( $res ) ? $res->get_error_code() : '-' ) );

// 1. La donnée en cache n'a pas bougé.
t_egal( $avant_lignes, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'panne réseau : aucune ligne ajoutée ni supprimée' );
$apres_statut = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
t_egal( 'disponible', $apres_statut['etat'], 'panne réseau : le statut du jour reste affiché' );
t_egal( 'interdit', $apres_statut['niveau']['cle'], 'panne réseau : la dernière valeur connue est intacte' );
t_egal( $avant_statut['statut_id'], $apres_statut['statut_id'], 'panne réseau : c\'est exactement la même ligne' );

// 2. L'échec est compté et daté.
$etat = StateRepository::get();
t_egal( 1, (int) $etat['echecs_consecutifs'], 'panne réseau : un échec consécutif compté' );
t_assert( is_array( $etat['derniere_erreur'] ), 'panne réseau : la dernière erreur est enregistrée', 'tableau', $etat['derniere_erreur'] );
t_note( 'derniere_erreur : ' . wp_json_encode( $etat['derniere_erreur'] ) );

// 3. La fraîcheur reflète l'état réel : elle ne repart pas de zéro.
$frais = massifs_fraicheur( $aujourdhui );
t_egal( $avant_frais['dernier_releve_le'], $frais['dernier_releve_le'], 'panne réseau : le dernier relevé réussi n\'est pas réécrit' );
t_egal( false, $frais['perimee'], 'panne réseau récente : pas encore de péremption (relevé de 2 h)' );

// 4. Deux échecs de suite s'additionnent.
Connector::run_now( $aujourdhui );
t_egal( 2, (int) StateRepository::get()['echecs_consecutifs'], 'deux pannes : deux échecs consécutifs' );

// 5. Le retour de la source repart d'un compteur propre.
remove_all_filters( 'pre_http_request' );
t_bouchon_http( t_reponse_200( t_charge_source( 2, 0 ) ) );
Connector::run_now( $aujourdhui );
t_egal( 0, (int) StateRepository::get()['echecs_consecutifs'], 'retour de la source : compteur d\'échecs remis à zéro' );
t_assert( SnapshotRepository::has( str_replace( '-', '', $aujourdhui ) ), 'retour de la source : instantané enregistré' );

t_reset();
t_bilan();
