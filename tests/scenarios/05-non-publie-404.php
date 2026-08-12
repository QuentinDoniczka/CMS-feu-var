<?php
/**
 * 404 = « pas encore publié », état légitime.
 * La source ne dépose le fichier du lendemain qu'en fin d'après-midi : ce 404
 * ne doit ni compter comme un échec, ni déclencher d'alerte, ni écrire un statut.
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

$boite = array();
t_intercepter_mail( $boite );
t_bouchon_http( t_reponse_code( 404, '<html>Not Found</html>' ) );

$demain = massifs_jour_suivant();
$avant  = StateRepository::get();
t_egal( 0, (int) $avant['echecs_consecutifs'], 'compteur d\'échecs à zéro au départ' );

$r = Connector::run_now( $demain );
t_assert( is_wp_error( $r ) && 'non_publie' === $r->get_error_code(), '404 => code « non_publie »', 'non_publie', is_wp_error( $r ) ? $r->get_error_code() : $r );

$apres = StateRepository::get();
t_egal( 0, (int) $apres['echecs_consecutifs'], '404 : le compteur d\'échecs consécutifs N\'EST PAS incrémenté' );
t_egal( null, $apres['derniere_erreur'], '404 : aucune erreur enregistrée' );
t_egal( array(), $boite, '404 : aucune alerte e-mail envoyée' );

$dernier = end( $apres['journal'] );
t_egal( 'non_publie', $dernier['issue'] ?? '', 'journal : issue « non_publie » tracée' );
t_egal( str_replace( '-', '', $demain ), $dernier['date_cible'] ?? '', 'journal : date cible correcte' );

// Aucun statut inventé, et l'état rendu reste honnête.
t_egal( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}massifs_statuts" ), 'aucun statut écrit sur un 404' );
$s = massifs_statut_du_jour( 'sainte-victoire', $demain );
t_egal( 'non_encore_publie', $s['etat'], 'le visiteur voit « non encore publié », jamais un niveau' );

// Trois 404 d'affilée restent trois non-évènements.
Connector::run_now( $demain );
Connector::run_now( $demain );
$apres = StateRepository::get();
t_egal( 0, (int) $apres['echecs_consecutifs'], 'trois 404 d\'affilée : toujours zéro échec' );
t_egal( array(), $boite, 'trois 404 d\'affilée : toujours aucune alerte' );

t_reset();
t_bilan();
