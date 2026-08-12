<?php
/**
 * Le contrat d'écriture de la projection préfecture (nouveau).
 *
 * On entre par la couture d'intégration réelle — l'action
 * `massifs_prefecture_snapshot_enregistre` — et on observe la base, le bilan
 * publié et la fraîcheur. Aucune méthode privée n'est appelée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;
t_reset();

$table = $wpdb->prefix . 'massifs_statuts';
$jour  = massifs_jour_courant();

/** Instantané au format publié par le connecteur. */
$instantane = static function ( array $massifs, ?string $jour_validite = null ) use ( $jour ): array {
	return array(
		'date_validite'     => $jour_validite ?? $jour,
		'massifs'           => $massifs,
		'source_modifie_le' => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
	);
};

/** Les 27 entrées telles que le validateur les publie. */
$vingt_sept = static function ( int $niveau = 2, int $procedure = 0 ): array {
	$m = array();
	for ( $n = 1; $n <= 27; $n++ ) {
		$m[ '13' . $n ] = array( 'niveau_source' => $niveau, 'procedure_source' => $procedure );
	}
	return $m;
};

$bilans = array();
add_action( 'massifs_projection_prefecture', static function ( $b ) use ( &$bilans ) {
	$bilans[] = $b;
} );

$lignes = static fn(): int => (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}massifs_statuts" );

// ---------------------------------------------------------------- CAS 1
// Lot nominal de 27 : les 25 nommés sont écrits, les 2 surnuméraires écartés,
// le lot est déclaré COMPLET et la fraîcheur est mise à jour.
$bilans = array();
do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $vingt_sept( 3, 1 ) ) );
$b = end( $bilans );
t_assert( is_array( $b ), 'une projection publie toujours son bilan (action émise même en succès)', 'bilan', $b );
t_note( 'bilan nominal : ' . wp_json_encode( $b ) );
t_egal( 'complet', $b['resultat'], 'lot nominal : résultat complet' );
t_egal( 27, $b['recus'], 'bilan : 27 entrées reçues' );
t_egal( 25, $b['resolus'], 'bilan : 25 identifiants résolus par le référentiel' );
t_egal( 25, $b['ecrits'], 'bilan : 25 lignes écrites' );
t_egal( 0, $b['refuses'], 'bilan : aucune ligne refusée' );
t_egal( 2, $b['ignores'], 'bilan : 2 identifiants écartés' );
t_egal( array( '1326', '1327' ), $b['identifiants_ignores'], 'bilan : ce sont bien 1326 et 1327' );
t_egal( 25, $lignes(), 'base : 25 lignes, aucune rangée sous un identifiant source' );
t_egal( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE massif_code REGEXP '^13[0-9]+$'" ), 'base : aucune clé numérique de la source' );
$frais_apres_complet = massifs_fraicheur( $jour );
t_assert( null !== $frais_apres_complet['dernier_releve_le'], 'lot complet : le relevé est déclaré réussi', 'instant ISO', $frais_apres_complet['dernier_releve_le'] );
t_egal( false, $frais_apres_complet['perimee'], 'lot complet : la donnée est fraîche' );

// ---------------------------------------------------------------- CAS 2
// Une seule ligne irrécupérable dans le lot => RIEN n'est écrit.
foreach ( array(
	'entrée non tabulaire'          => array( '135' => 'interdit' ),
	'niveau_source absent'          => array( '135' => array( 'procedure_source' => 0 ) ),
	'niveau_source non entier'      => array( '135' => array( 'niveau_source' => '3' ) ),
	'niveau_source hors liste (9)'  => array( '135' => array( 'niveau_source' => 9, 'procedure_source' => 0 ) ),
	'procedure hors liste (7)'      => array( '135' => array( 'niveau_source' => 2, 'procedure_source' => 7 ) ),
) as $libelle => $ligne_cassee ) {
	t_reset();
	$bilans = array();

	$massifs = $vingt_sept( 2, 0 );
	foreach ( $ligne_cassee as $id => $valeur ) {
		$massifs[ $id ] = $valeur;
	}

	do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $massifs ) );
	$b = end( $bilans );

	t_egal( 'rejete', $b['resultat'], "une ligne irrécupérable ({$libelle}) : lot rejeté" );
	t_egal( 0, $lignes(), "une ligne irrécupérable ({$libelle}) : AUCUNE écriture" );
	t_egal( 0, $b['ecrits'], "une ligne irrécupérable ({$libelle}) : bilan à zéro écrit" );
	t_egal( null, massifs_fraicheur( $jour )['dernier_releve_le'], "une ligne irrécupérable ({$libelle}) : relevé NON déclaré réussi" );
	t_note( '   motif : ' . $b['motif'] );
}

// ---------------------------------------------------------------- CAS 3
// Refus par les règles d'écriture du domaine (jour hors horizon) : le lot est
// éprouvé AVANT la première insertion, donc rien n'est écrit.
t_reset();
$bilans   = array();
$trop_loin = ( new DateTimeImmutable( $jour, new DateTimeZone( 'Europe/Paris' ) ) )->modify( '+9 days' )->format( 'Y-m-d' );
do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $vingt_sept( 2, 0 ), $trop_loin ) );
$b = end( $bilans );
t_egal( 'rejete', $b['resultat'], 'jour hors horizon d\'écriture : lot rejeté par la pré-validation' );
t_egal( 0, $lignes(), 'jour hors horizon : aucune écriture' );
t_assert( str_contains( (string) $b['motif'], 'avant toute écriture' ), 'le motif dit que le refus précède l\'écriture', 'avant toute écriture', $b['motif'] );
t_note( '   motif : ' . $b['motif'] );

// ---------------------------------------------------------------- CAS 4
// Échec d'écriture MALGRÉ la pré-validation : panne de base simulée sur UNE
// ligne (l'insertion est déroutée vers une table inexistante). Le lot doit
// être déclaré PARTIEL — sans prétendre à une atomicité qui n'existe pas — et
// la fraîcheur NE DOIT PAS être mise à jour.
t_reset();
$bilans = array();
$saboteur = static function ( $requete ) {
	if ( str_starts_with( strtolower( trim( (string) $requete ) ), 'insert into' )
		&& str_contains( (string) $requete, "'sainte-victoire'" ) ) {
		return "INSERT INTO wp_massifs_table_inexistante (id) VALUES (1)";
	}
	return $requete;
};
add_filter( 'query', $saboteur );
$wpdb->suppress_errors( true );
do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $vingt_sept( 2, 0 ) ) );
$wpdb->suppress_errors( false );
remove_filter( 'query', $saboteur );

$b = end( $bilans );
t_note( 'bilan sur panne d\'écriture : ' . wp_json_encode( $b ) );
t_egal( 'partiel', $b['resultat'], 'échec d\'écriture imprévu : lot déclaré PARTIEL' );
t_egal( 25, $b['resolus'], 'lot partiel : 25 lignes attendues' );
t_egal( 24, $b['ecrits'], 'lot partiel : 24 écrites, le compte réel est dit' );
t_egal( 1, $b['refuses'], 'lot partiel : 1 refusée, comptée et déclarée' );
t_egal( null, massifs_fraicheur( $jour )['dernier_releve_le'], 'lot partiel : relevé NON déclaré réussi (la fraîcheur ne ment pas)' );
t_egal( 'indisponible', massifs_statut_du_jour( 'sainte-victoire', $jour )['etat'], 'lot partiel : le massif non écrit reste « information non disponible »' );
t_egal( 'disponible', massifs_statut_du_jour( 'alpilles', $jour )['etat'], 'lot partiel : les massifs écrits, eux, sont affichables' );

// ---------------------------------------------------------------- CAS 5
// Instantané difforme : refus explicite, aucune écriture, bilan publié.
foreach ( array(
	'instantané non tabulaire' => 'nawak',
	'date de validité absente' => array( 'massifs' => array( '131' => array( 'niveau_source' => 2 ) ) ),
	'aucun massif'             => array( 'date_validite' => $jour, 'massifs' => array() ),
) as $libelle => $charge ) {
	t_reset();
	$bilans = array();
	do_action( 'massifs_prefecture_snapshot_enregistre', $charge );
	$b = end( $bilans );
	t_egal( 'rejete', $b['resultat'], "instantané difforme ({$libelle}) : rejeté" );
	t_egal( 0, $lignes(), "instantané difforme ({$libelle}) : aucune écriture" );
}

// ---------------------------------------------------------------- CAS 6
// Une correction du même jour est une ligne de plus, jamais un écrasement.
t_reset();
$bilans = array();
do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $vingt_sept( 1, 0 ) ) );
t_egal( 'autorise', massifs_statut_du_jour( 'sainte-victoire', $jour )['niveau']['cle'], 'première publication : autorisé' );
do_action( 'massifs_prefecture_snapshot_enregistre', $instantane( $vingt_sept( 3, 1 ) ) );
t_egal( 'interdit', massifs_statut_du_jour( 'sainte-victoire', $jour )['niveau']['cle'], 'republication : la correction est visible' );
t_egal( 50, $lignes(), 'historique intégral conservé : 25 + 25 lignes, aucun écrasement' );

t_reset();
t_bilan();
