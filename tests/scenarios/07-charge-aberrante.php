<?php
/**
 * Données aberrantes : rejet du lot entier, valeur précédente intacte,
 * alerte au gestionnaire. Et les trois « non-règles » du §7.2 de la décision :
 * ce qui ressemble à une aberration sans en être une doit être ACCEPTÉ.
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
$ymd        = str_replace( '-', '', $aujourdhui );
$table      = $wpdb->prefix . 'massifs_statuts';

// Valeur précédente en place, qui devra rester intacte.
massifs_enregistrer_statut( array(
	'massif_code'   => 'sainte-victoire',
	'jour_validite' => $aujourdhui,
	'niveau_cle'    => 'autorise',
	'zapef_cle'     => 'autorise',
	'source'        => 'saisie_manuelle',
	'auteur_id'     => 1,
) );
$reference = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
t_egal( 'disponible', $reference['etat'], 'valeur précédente en place' );

$nominale = t_charge_source( 2, 0 );

/** Fabrique une réponse 200 dont le corps est arbitraire. */
$reponse_corps = static function ( string $corps ): array {
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => $corps,
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};

$aberrations = array(
	'HTTP 200 servant une page HTML'          => $reponse_corps( '<!DOCTYPE html><html><body>Erreur</body></html>' ),
	'corps vide'                              => $reponse_corps( '' ),
	'JSON tronqué'                            => $reponse_corps( '{"massifs":{"131":[2,0]' ),
	'massifs absent'                          => $reponse_corps( '{"zm":{"131":2}}' ),
	'massifs vide'                            => $reponse_corps( '{"massifs":{}}' ),
	'valeur non tabulaire'                    => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => 'interdit', $nominale['massifs'] ) ) ) ),
	'niveau en chaîne de caractères'          => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => array( '2', 0 ), $nominale['massifs'] ) ) ) ),
	'niveau flottant'                         => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => array( 2.5, 0 ), $nominale['massifs'] ) ) ) ),
	'trois éléments par massif'               => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => array( 2, 0, 1 ), $nominale['massifs'] ) ) ) ),
	'niveau hors liste blanche (9)'           => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => array( 9, 0 ), $nominale['massifs'] ) ) ) ),
	'procédure hors liste blanche (7)'        => $reponse_corps( wp_json_encode( array( 'massifs' => array_map( static fn() => array( 2, 7 ), $nominale['massifs'] ) ) ) ),
);

$alertes = 0;
foreach ( $aberrations as $libelle => $reponse ) {
	delete_option( 'massifs_prefecture_etat' );
	$boite = array();
	remove_all_filters( 'pre_http_request' );
	remove_all_filters( 'pre_wp_mail' );
	t_intercepter_mail( $boite );
	t_bouchon_http( $reponse );

	$r = Connector::run_now( $aujourdhui );

	t_assert( is_wp_error( $r ), "aberration rejetée : {$libelle}", 'WP_Error', $r );
	t_assert( ! SnapshotRepository::has( $ymd ), "aucun instantané enregistré : {$libelle}" );
	t_assert( count( $boite ) >= 1, "alerte de rejet envoyée : {$libelle}", '>=1 mail', count( $boite ) );
	$alertes += count( $boite );

	$apres = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
	t_egal( $reference['statut_id'], $apres['statut_id'], "valeur précédente intacte après « {$libelle} »" );
	if ( is_wp_error( $r ) ) {
		t_note( '   → ' . $r->get_error_code() . ' : ' . $r->get_error_message() );
	}
}
t_note( 'total d\'alertes de rejet émises : ' . $alertes );

// --- CE QUI RESSEMBLE À UNE ABERRATION SANS EN ÊTRE UNE : doit PASSER.
//
// CORRECTION D'UNE ATTENTE FAUSSE DE CETTE SUITE. Ce scénario a longtemps
// affirmé qu'un corps identique à celui d'une autre date valait « pas encore
// publié ». C'était faux, et c'est ce qui a rendu invisible un défaut bloquant :
// le corps de la source ne porte aucune date, deux journées stables sont donc
// identiques au bit près, et les classer « doublon » éteint le site pendant tout
// un épisode stable. Le 404 est le SEUL signal de non-publication.
// Le cas complet est joué par `13-jours-consecutifs-identiques.php`.
remove_all_filters( 'pre_http_request' );
remove_all_filters( 'pre_wp_mail' );
delete_option( 'massifs_prefecture_etat' );
delete_option( 'massifs_prefecture_snapshots' );

t_bouchon_http( $reponse_corps( (string) wp_json_encode( t_charge_source( 4, 1 ) ) ) );
$r = Connector::run_now( $aujourdhui );
t_assert( true === $r, 'NON-aberration : tous les massifs au niveau le plus sévère est ACCEPTÉ', true, is_wp_error( $r ) ? $r->get_error_code() : $r );

// Charge strictement identique servie pour une AUTRE date : c'est une
// publication de plein droit, pas un doublon.
delete_option( 'massifs_prefecture_etat' );
$demain = massifs_jour_suivant();
$r      = Connector::run_now( $demain );
t_assert( true === $r, 'NON-aberration : corps identique à la veille = publication acceptée', true, is_wp_error( $r ) ? $r->get_error_code() . ' — ' . $r->get_error_message() : $r );
t_egal( 'disponible', massifs_statut_du_jour( 'sainte-victoire', $demain )['etat'], 'corps identique à la veille : le visiteur voit le statut du lendemain' );
t_egal( 0, (int) StateRepository::get()['echecs_consecutifs'], 'corps identique : aucun échec compté' );
t_assert( ! in_array( 'non_publie_doublon', StateRepository::ISSUES, true ), 'la notion même de « doublon » a disparu de la machine à états' );

// --- Le hachage ne protégeant plus contre un fichier réellement rassis, la
// couche temporelle devient la seule garde : un `Last-Modified` antérieur de
// plus de 48 h au début de validité demandé est refusé.
//
// La valeur de référence est reprise ICI : les deux publications légitimes
// ci-dessus ont, à juste titre, écrit de nouvelles lignes.
$reference = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
remove_all_filters( 'pre_http_request' );
delete_option( 'massifs_prefecture_etat' );
delete_option( 'massifs_prefecture_snapshots' );
t_bouchon_http(
	t_reponse_200(
		t_charge_source( 2, 0 ),
		gmdate( 'D, d M Y H:i:s \G\M\T', time() - 5 * DAY_IN_SECONDS )
	)
);
$r = Connector::run_now( $aujourdhui );
t_assert( is_wp_error( $r ) && 'fichier_perime' === $r->get_error_code(), 'fichier réellement rassis (modifié il y a 5 jours) : rejeté par la couche temporelle', 'fichier_perime', is_wp_error( $r ) ? $r->get_error_code() : $r );
t_egal( $reference['statut_id'], massifs_statut_du_jour( 'sainte-victoire', $aujourdhui )['statut_id'], 'fichier rassis : valeur précédente intacte' );

t_reset();
t_bilan();
