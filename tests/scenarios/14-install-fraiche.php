<?php
/**
 * Installation fraîche (volumes recréés) : l'extension s'active et
 * s'amorce sans la moindre erreur PHP, crée sa table au bon schéma, et
 * l'ensemble du domaine répond honnêtement en l'absence totale de données.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;

$table = $wpdb->prefix . 'massifs_statuts';
t_egal( $table, (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ), 'installation fraîche : la table des statuts a été créée sans intervention' );
$c = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" );
t_egal( 'YES', $c->Null, 'installation fraîche : niveau_cle est nullable' );
t_egal( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'installation fraîche : aucune ligne de statut' );

t_note( 'massifs_schema_version = ' . (string) get_option( 'massifs_schema_version' ) );
t_assert( '' !== (string) get_option( 'massifs_schema_version' ), 'signature de schéma enregistrée' );

// Le site répond honnêtement sans aucune donnée.
$codes = massifs_codes();
t_egal( 25, count( $codes ), 'référentiel complet dès l\'installation' );
$statuts = massifs_statuts_du_jour( $codes );
$etats   = array_unique( array_column( $statuts, 'etat' ) );
t_egal( array( 'indisponible' ), array_values( $etats ), 'sans données : tous les massifs sont « indisponible »' );
$synthese = massifs_synthese_du_jour( $codes );
t_egal( 'indisponible', $synthese['etat_global'], 'sans données : synthèse indisponible' );
t_egal( 25, $synthese['sans_donnee'], 'sans données : 25 massifs sans donnée' );
t_note( 'par_niveau : ' . wp_json_encode( $synthese['par_niveau'] ) );

$f = massifs_fraicheur();
t_egal( null, $f['dernier_releve_le'], 'sans relevé : aucun instant de relevé inventé' );
t_egal( true, $f['perimee'], 'en saison sans aucun relevé : la bannière de péremption est due (§4.5)' );

t_assert( null === error_get_last(), 'aucune erreur PHP sur une installation fraîche', null, error_get_last() );
t_bilan();
