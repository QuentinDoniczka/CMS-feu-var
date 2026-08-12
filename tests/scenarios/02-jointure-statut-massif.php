<?php
/**
 * SCÉNARIO CŒUR : la préfecture publie, le site doit pouvoir afficher.
 *
 * Une charge réaliste traverse tout le chemin d'ingestion (bouchonné à la
 * frontière réseau), est projetée dans le modèle de statuts, puis on effectue
 * exactement la jointure que le thème fera : parcourir massifs_referentiel() et
 * demander le statut du jour de chaque massif.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

global $wpdb;
t_reset();
t_armer_connecteur();

$table = $wpdb->prefix . 'massifs_statuts';
$jour  = massifs_jour_courant();
$ymd   = str_replace( '-', '', $jour );

// La stack ne doit émettre aucun octet réel : bouchon à la frontière d'ingestion.
t_bouchon_http( t_reponse_200( t_charge_source( 3, 1 ) ) );

$resultat = \Massifs\Ingest\Prefecture\Connector::run_now( $jour );
t_assert( true === $resultat, 'ingestion : la charge du jour est acceptée et enregistrée', true, is_wp_error( $resultat ) ? $resultat->get_error_message() : $resultat );

// --- CÔTÉ STATUTS : quelles clés ont réellement été écrites ?
$codes_statuts = $wpdb->get_col( "SELECT DISTINCT massif_code FROM {$table} ORDER BY massif_code" );
sort( $codes_statuts );
t_note( 'massif_code réellement stockés (' . count( $codes_statuts ) . ') : ' . implode( ', ', $codes_statuts ) );

// --- CÔTÉ RÉFÉRENTIEL : quelles clés le thème itère-t-il ?
$codes_referentiel = array_keys( massifs_referentiel() );
t_note( 'clés de massifs_referentiel() (' . count( $codes_referentiel ) . ') : ' . implode( ', ', $codes_referentiel ) );

// --- LA JOINTURE QUE LE THÈME FERA
$commun = array_values( array_intersect( $codes_statuts, $codes_referentiel ) );
t_note( 'intersection des deux ensembles : ' . ( array() === $commun ? '(vide)' : implode( ', ', $commun ) ) );

t_assert(
	array() !== $commun,
	'un statut ingéré est joignable à un massif du référentiel',
	'au moins un code commun entre statuts et référentiel',
	'intersection vide : ' . count( $codes_statuts ) . ' codes statuts, ' . count( $codes_referentiel ) . ' codes référentiel'
);

// Le rendu réel : le thème demande les statuts des massifs du référentiel.
$statuts = massifs_statuts_du_jour( $codes_referentiel, $jour );
$disponibles = array_values( array_filter( $statuts, static fn( $s ) => 'disponible' === $s['etat'] ) );
t_assert(
	count( $disponibles ) > 0,
	'après une ingestion nominale, au moins un massif du référentiel a un statut disponible',
	'>0 massifs disponibles',
	count( $disponibles ) . ' disponibles ; états observés : ' . implode( ',', array_unique( array_column( $statuts, 'etat' ) ) )
);

$synthese = massifs_synthese_du_jour( $codes_referentiel, $jour );
t_note( 'synthèse : etat_global=' . $synthese['etat_global'] . ' disponibles=' . $synthese['disponibles'] . ' sans_donnee=' . $synthese['sans_donnee'] );
t_egal( 'disponible', $synthese['etat_global'], 'synthèse du jour : état global disponible après ingestion nominale' );

// Le scénario complet du visiteur : les 25 massifs nommés portent un statut.
t_egal( 25, count( $disponibles ), 'les 25 massifs du référentiel portent le statut du jour' );
t_egal( 'interdit', $statuts['sainte-victoire']['niveau']['cle'], 'niveau projeté correct sur un massif nommé' );
t_egal( 'Accès au massif interdit', $statuts['sainte-victoire']['niveau']['libelle'], 'libellé officiel rendu au thème' );

// Preuve complémentaire : la donnée EST bien en base, correctement projetée.
$exemple = $wpdb->get_row( "SELECT * FROM {$table} LIMIT 1", ARRAY_A );
t_note( 'exemple de ligne : ' . wp_json_encode( $exemple ) );
t_egal( 25, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), '25 lignes projetées (les 2 identifiants sans nom publié sont écartés)' );
t_egal( array(), array_values( array_diff( $codes_statuts, $codes_referentiel ) ), 'aucune ligne rangée sous un identifiant de la source' );

t_reset();
t_bilan();
