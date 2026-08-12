<?php
/**
 * T11 (volet PHP) — Un module frère absent ne doit pas empêcher le site de
 * fonctionner : le chargeur par convention tolère l'absence, et les fonctions
 * du module manquant disparaissent sans erreur fatale.
 */
require_once __DIR__ . '/../bootstrap.php';

$args   = isset( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array();
$module = '' === (string) end( $args ) ? '(inconnu)' : (string) end( $args );

t_note( 'module masqué : ' . $module );
t_note( 'massifs_referentiel existe : ' . var_export( function_exists( 'massifs_referentiel' ), true ) );
t_note( 'massifs_statuts_du_jour existe : ' . var_export( function_exists( 'massifs_statuts_du_jour' ), true ) );
t_note( 'Connector existe : ' . var_export( class_exists( '\\Massifs\\Ingest\\Prefecture\\Connector' ), true ) );

// Le reste du domaine continue de répondre honnêtement.
if ( function_exists( 'massifs_statuts_du_jour' ) ) {
	$s = massifs_statuts_du_jour( array( 'sainte-victoire' ), massifs_jour_courant() );
	t_egal( 'indisponible', $s['sainte-victoire']['etat'], 'le domaine statuts répond encore, et honnêtement' );
}

if ( function_exists( 'massifs_referentiel' ) ) {
	t_note( 'référentiel : ' . count( massifs_referentiel() ) . ' massifs' );
}

// Référentiel absent = plus aucune identité de rangement : une projection ne
// doit RIEN écrire plutôt que de ranger un statut sous l'identifiant source.
if ( ! function_exists( 'massifs_code_depuis_source' ) && function_exists( 'massifs_enregistrer_statuts' ) ) {
	global $wpdb;
	$table = $wpdb->prefix . 'massifs_statuts';
	$wpdb->query( "DELETE FROM {$table}" );

	$bilan   = null;
	add_action( 'massifs_projection_prefecture', static function ( $b ) use ( &$bilan ) {
		$bilan = $b;
	} );

	$massifs = array();
	for ( $n = 1; $n <= 27; $n++ ) {
		$massifs[ '13' . $n ] = array( 'niveau_source' => 2, 'procedure_source' => 0 );
	}

	do_action(
		'massifs_prefecture_snapshot_enregistre',
		array( 'date_validite' => massifs_jour_courant(), 'massifs' => $massifs )
	);

	t_egal( 'rejete', $bilan['resultat'] ?? '(aucun bilan)', 'référentiel absent : projection rejetée' );
	t_egal( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'référentiel absent : AUCUN statut rangé sous un identifiant source' );
	t_note( '   motif : ' . ( $bilan['motif'] ?? '' ) );
}

t_assert( null === error_get_last(), 'aucune erreur PHP malgré le module absent', null, error_get_last() );
t_bilan();
