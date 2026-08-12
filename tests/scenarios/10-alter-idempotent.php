<?php
/**
 * L'`ALTER` de nullabilité est idempotent PAR VÉRIFICATION D'ÉTAT :
 * émis une fois quand la colonne est réellement `NOT NULL`, jamais ensuite.
 * On compte les ordres SQL réellement soumis pendant l'installation.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;
t_reset();

$table = $wpdb->prefix . 'massifs_statuts';

$alters = array();
$mouchard = static function ( $requete ) use ( &$alters ) {
	if ( str_contains( strtoupper( (string) $requete ), 'ALTER TABLE' ) ) {
		$alters[] = trim( preg_replace( '/\s+/', ' ', (string) $requete ) );
	}
	return $requete;
};
add_filter( 'query', $mouchard );

// --- Base ramenée à l'état « schéma antérieur » : niveau_cle NOT NULL.
$wpdb->query( "ALTER TABLE {$table} MODIFY niveau_cle varchar(32) NOT NULL" );
$alters = array(); // l'ALTER de préparation ne compte pas.
t_egal( 'NO', $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" )->Null, 'préparation : niveau_cle est NOT NULL' );

// --- Premier passage d'installation : un ALTER, et un seul.
delete_option( 'massifs_schema_version' );
massifs_core_verifier_installation();
$premier = $alters;
t_note( 'ordres ALTER du 1er passage : ' . wp_json_encode( $premier ) );
t_egal( 1, count( $premier ), 'premier passage : exactement un ALTER émis' );
t_assert( str_contains( strtolower( $premier[0] ), 'niveau_cle' ), 'l\'ALTER porte bien sur niveau_cle', 'niveau_cle', $premier[0] ?? '' );
t_egal( 'YES', $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" )->Null, 'après le 1er passage : colonne nullable' );

// --- Second passage, signature forcée : aucun ALTER.
$alters = array();
delete_option( 'massifs_schema_version' );
massifs_core_verifier_installation();
t_note( 'ordres ALTER du 2e passage : ' . wp_json_encode( $alters ) );
t_egal( 0, count( $alters ), 'second passage : AUCUN ALTER (idempotence par vérification d\'état)' );
t_egal( 'YES', $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" )->Null, 'la colonne reste nullable' );

// --- Troisième passage, signature inchangée : l'installation ne se rejoue même pas.
$alters = array();
massifs_core_verifier_installation();
t_egal( 0, count( $alters ), 'signature inchangée : aucune installation rejouée' );

// --- Un chargement de page ordinaire ne doit rejouer aucun ALTER.
$alters = array();
do_action( 'plugins_loaded' );
t_egal( 0, count( $alters ), 'chargement ordinaire : aucun ALTER rejoué' );

remove_filter( 'query', $mouchard );

// Conséquence observable : le « level 0 » s'enregistre.
$r = massifs_enregistrer_statut( array(
	'massif_code'        => 'calanques',
	'jour_validite'      => massifs_jour_courant(),
	'niveau_source_brut' => 0,
	'source'             => 'saisie_manuelle',
	'auteur_id'          => 1,
) );
t_assert( $r['enregistre'], 'après migration : un « level 0 » s\'enregistre', true, $r );
t_egal( 'indisponible', massifs_statut_du_jour( 'calanques', massifs_jour_courant() )['etat'], 'level 0 rend « information non disponible »' );

t_reset();
t_bilan();
