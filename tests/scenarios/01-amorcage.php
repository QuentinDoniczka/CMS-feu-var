<?php
/**
 * L'extension s'amorce proprement dans un WordPress réel :
 * modules chargés, surface de lecture contractuelle présente, table créée,
 * aucune erreur PHP émise pendant l'amorçage.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

global $wpdb;

// 1. Le plugin est actif et ses modules ont été chargés par la convention.
t_assert( is_plugin_active( 'massifs-core/massifs-core.php' ) || in_array( 'massifs-core/massifs-core.php', (array) get_option( 'active_plugins' ), true ), 'extension massifs-core active' );

// 2. Surface publique contractuelle — issue #2 (référentiel).
$fonctions_2 = array(
	'massifs_referentiel', 'massifs_massif', 'massifs_massif_existe', 'massifs_codes',
	'massifs_libelle', 'massifs_libelles', 'massifs_compte', 'massifs_emprise',
	'massifs_geometrie', 'massifs_attribution', 'massifs_lacunes',
	'massifs_referentiel_etat', 'massifs_referentiel_disponible',
);
$manquantes = array_values( array_filter( $fonctions_2, static fn( $f ) => ! function_exists( $f ) ) );
t_egal( array(), $manquantes, 'contrat #2 : toutes les fonctions de lecture existent' );

// 3. Surface publique contractuelle — issue #3 (statuts / légende / fraîcheur).
$fonctions_3 = array(
	'massifs_statuts_du_jour', 'massifs_statut_du_jour', 'massifs_synthese_du_jour',
	'massifs_legende', 'massifs_legende_est_confirmee', 'massifs_niveaux_source_autorises',
	'massifs_procedures_source_autorisees', 'massifs_fraicheur', 'massifs_saison',
	'massifs_jour_courant', 'massifs_jour_suivant', 'massifs_horodatage',
	'massifs_attribution_statuts', 'massifs_enregistrer_statut', 'massifs_enregistrer_statuts',
	'massifs_enregistrer_releve_reussi',
);
$manquantes = array_values( array_filter( $fonctions_3, static fn( $f ) => ! function_exists( $f ) ) );
t_egal( array(), $manquantes, 'contrat #3 : toutes les fonctions de lecture/écriture existent' );

// 4. Surface de la chaîne #1 (connecteur) — classe nommée par le contrat §8.2.
t_assert( class_exists( '\\Massifs\\Ingest\\Prefecture\\Connector' ), 'contrat #1 : Connector chargé' );
t_assert( \Massifs\Ingest\Prefecture\Bootstrap::is_registered(), 'contrat #1 : Bootstrap::register() a bien été appelé au chargement' );

// 5. La table des statuts existe avec le schéma 2.0.0.
$table = $wpdb->prefix . 'massifs_statuts';
$existe = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
t_egal( $table, $existe, 'table des statuts créée' );

$colonnes = array();
foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}" ) as $c ) {
	$colonnes[ $c->Field ] = array( 'type' => $c->Type, 'null' => $c->Null );
}
foreach ( array( 'id', 'massif_code', 'jour_validite', 'niveau_cle', 'zapef_cle', 'niveau_source_brut', 'procedure_source', 'source', 'auteur_id', 'publie_prefecture_le', 'enregistre_le' ) as $col ) {
	t_assert( isset( $colonnes[ $col ] ), "colonne {$col} présente", $col, array_keys( $colonnes ) );
}
t_egal( 'YES', $colonnes['niveau_cle']['null'] ?? '?', 'niveau_cle est NULLABLE (A-15 : level 0 = ligne sans niveau)' );

// 6. Référentiel réellement chargé.
t_assert( massifs_referentiel_disponible(), 'référentiel disponible' );
t_egal( 25, massifs_compte(), 'référentiel : 25 massifs' );

// 7. Légende officielle binaire (R2).
$legende = massifs_legende();
t_egal( 2, count( $legende['niveaux'] ), 'légende : 2 niveaux d\'accès' );
t_egal( array( 'autorise', 'interdit' ), array_column( $legende['niveaux'], 'cle' ), 'légende : clés autorise/interdit' );
t_egal( 'Accès au massif autorisé', $legende['niveaux'][0]['libelle'], 'libellé officiel verbatim (autorisé)' );
t_egal( 'Accès au massif interdit', $legende['niveaux'][1]['libelle'], 'libellé officiel verbatim (interdit)' );

// 8. Attribution imposée par le §9 du brief, relayée depuis le connecteur.
$attr = massifs_attribution_statuts();
t_egal( "D'après les publications de la préfecture des Bouches-du-Rhône", $attr['texte'], 'attribution §9 verbatim' );
t_egal( 'https://www.risque-prevention-incendie.fr/13', $attr['carte_officielle_url'], 'lien carte officielle' );

// 9. Aucune erreur PHP n'a été émise pendant cet amorçage.
$derniere = error_get_last();
t_assert( null === $derniere, 'aucune erreur PHP pendant l\'amorçage', null, $derniere );

t_bilan();
