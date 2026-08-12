<?php
/**
 * Base déjà installée en schéma 1 (`niveau_cle NOT NULL`) : la remontée
 * en schéma 2.0.0 doit rendre la colonne nullable, sinon la publication d'un
 * « level 0 » échouera silencieusement en production.
 *
 * On simule l'installation antérieure EN BASE, puis on rejoue exactement le
 * chemin d'amorçage de l'extension (`massifs_core_verifier_installation`).
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;
t_reset();

$table = $wpdb->prefix . 'massifs_statuts';
$jour  = massifs_jour_courant();

// --- Retour à l'état « schéma 1 » : niveau_cle NOT NULL.
$wpdb->query( "ALTER TABLE {$table} MODIFY niveau_cle varchar(32) NOT NULL" );
$colonne = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" );
t_egal( 'NO', $colonne->Null, 'préparation : la base est revenue à niveau_cle NOT NULL' );

// Une ligne héritée de l'ancien schéma, qui doit survivre à la migration.
$wpdb->insert(
	$table,
	array(
		'massif_code'   => 'sainte-victoire',
		'jour_validite' => $jour,
		'niveau_cle'    => 'autorise',
		'source'        => 'saisie_manuelle',
		'auteur_id'     => 1,
		'enregistre_le' => gmdate( 'Y-m-d H:i:s' ),
	),
	array( '%s', '%s', '%s', '%s', '%d', '%s' )
);
$id_heritee = (int) $wpdb->insert_id;
t_assert( $id_heritee > 0, 'préparation : une ligne héritée existe' );

// --- Rejeu du chemin d'installation réel de l'extension.
delete_option( 'massifs_schema_version' );
massifs_core_verifier_installation();

$colonne = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" );
t_note( 'niveau_cle après migration : type=' . $colonne->Type . ' null=' . $colonne->Null );
t_egal( 'YES', $colonne->Null, 'MIGRATION : niveau_cle est devenue NULLABLE (A-15)' );

// La ligne héritée n'a pas été perdue.
t_egal( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $id_heritee ) ), 'la ligne héritée survit à la migration' );

// Conséquence observable : « level 0 » doit pouvoir être publié.
$r = massifs_enregistrer_statut( array(
	'massif_code'        => 'calanques',
	'jour_validite'      => $jour,
	'niveau_source_brut' => 0,
	'procedure_source'   => 0,
	'source'             => 'saisie_manuelle',
	'auteur_id'          => 1,
) );
t_assert( $r['enregistre'], 'après migration : un « level 0 » (aucune donnée publiée) s\'enregistre', true, $r );
$s = massifs_statut_du_jour( 'calanques', $jour );
t_egal( 'indisponible', $s['etat'], 'après migration : level 0 rend « information non disponible »' );

// --- Idempotence : un second passage ne change rien et ne casse rien.
delete_option( 'massifs_schema_version' );
massifs_core_verifier_installation();
massifs_core_verifier_installation();
$colonne = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'niveau_cle'" );
t_egal( 'YES', $colonne->Null, 'installation idempotente : la colonne reste nullable' );
t_assert( null === error_get_last(), 'aucune erreur PHP pendant la migration', null, error_get_last() );

t_reset();
t_bilan();
