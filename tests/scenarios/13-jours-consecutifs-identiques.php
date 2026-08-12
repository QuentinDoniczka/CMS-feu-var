<?php
/**
 * SCÉNARIO H1 — RÉGRESSION PERMANENTE, À NE JAMAIS SUPPRIMER.
 *
 * Deux journées consécutives où la préfecture publie exactement les mêmes
 * valeurs. Le corps servi par la source ne contient AUCUNE date : ces deux
 * journées produisent donc un corps octet pour octet identique. C'est le cas
 * NOMINAL en juin et pendant tout épisode stable — constaté les 8 et 11 août
 * 2026 et le 15 août 2025, les 27 massifs au même niveau.
 *
 * Le défaut à empêcher de revenir : classer le second jour « doublon » sur la
 * seule foi du hachage, ne rien enregistrer, et afficher « information non
 * disponible » pendant toute la durée de l'épisode — c'est-à-dire précisément
 * quand la donnée est bonne et que le visiteur en a le plus besoin.
 *
 * Règle en vigueur, non rediscutable : le 404 est le SEUL signal de
 * non-publication ; un 200 sur `{date}.json` EST la publication de cette date ;
 * le hachage ne peut plus que journaliser ou éviter une réécriture pour la
 * MÊME date.
 *
 * Ce scénario a déjà échappé une fois à la recette parce que la suite de tests
 * affirmait le mauvais comportement. Il est ici pour que cela ne se reproduise
 * pas.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Prefecture\Connector;
use Massifs\Ingest\Prefecture\SnapshotRepository;
use Massifs\Ingest\Prefecture\StateRepository;

global $wpdb;

t_reset();
t_armer_connecteur();

$aujourdhui = massifs_jour_courant();
$demain     = massifs_jour_suivant();
$ymd_j      = str_replace( '-', '', $aujourdhui );
$ymd_j1     = str_replace( '-', '', $demain );

// Le vocabulaire du défaut ne doit plus exister dans la machine à états.
t_assert(
	! in_array( 'non_publie_doublon', StateRepository::ISSUES, true ),
	'« non_publie_doublon » a disparu de l\'énumération des issues',
	'absent',
	StateRepository::ISSUES
);

// UNE SEULE ET MÊME RÉPONSE pour les deux journées : c'est tout l'enjeu.
$charge = t_charge_source( 3, 1 );
$corps  = (string) wp_json_encode( $charge );
t_bouchon_http( t_reponse_200( $charge ) );

// ------------------------------------------------------------------ JOUR J
$resultat_j = Connector::run_now( $aujourdhui );
t_assert( true === $resultat_j, 'jour J : la publication est acceptée', true, is_wp_error( $resultat_j ) ? $resultat_j->get_error_code() : $resultat_j );
t_assert( SnapshotRepository::has( $ymd_j ), 'jour J : instantané enregistré' );
t_egal( 25, t_lignes_statuts(), 'jour J : les 25 massifs nommés sont projetés' );
t_egal( 'disponible', massifs_statut_du_jour( 'sainte-victoire', $aujourdhui )['etat'], 'jour J : le visiteur voit un statut' );

// ---------------------------------------------------------------- JOUR J+1
// Corps stricto sensu identique. C'est bien une nouvelle publication.
$resultat_j1 = Connector::run_now( $demain );

t_assert(
	true === $resultat_j1,
	'JOUR J+1 À CORPS IDENTIQUE : la publication est ACCEPTÉE, jamais classée « doublon »',
	true,
	is_wp_error( $resultat_j1 ) ? $resultat_j1->get_error_code() . ' — ' . $resultat_j1->get_error_message() : $resultat_j1
);

t_assert( SnapshotRepository::has( $ymd_j1 ), 'jour J+1 : instantané enregistré sous SA propre date' );

$instantane_j  = SnapshotRepository::get( $ymd_j );
$instantane_j1 = SnapshotRepository::get( $ymd_j1 );
t_egal( (string) $instantane_j['hash'], (string) $instantane_j1['hash'], 'les deux instantanés portent bien le même hachage (le corps est identique)' );
t_assert( $ymd_j !== $ymd_j1, 'et pourtant deux dates de validité distinctes', 'dates distinctes', $ymd_j . ' / ' . $ymd_j1 );
t_egal( strlen( $corps ), (int) $instantane_j1['octets'], 'jour J+1 : le corps enregistré est bien celui servi' );

// La conséquence qui compte : le visiteur voit le statut de demain.
t_egal( 50, t_lignes_statuts(), 'jour J+1 : 25 lignes de plus, une par massif nommé' );
$statut_demain = massifs_statut_du_jour( 'sainte-victoire', $demain );
t_egal( 'disponible', $statut_demain['etat'], 'JOUR J+1 : le visiteur voit un statut, PAS « information non disponible »' );
t_egal( 'interdit', $statut_demain['niveau']['cle'], 'jour J+1 : le niveau publié est bien celui de la source' );
t_egal( $demain, $statut_demain['jour_validite'], 'jour J+1 : la date de validité est celle demandée' );

$synthese = massifs_synthese_du_jour( massifs_codes(), $demain );
t_egal( 'disponible', $synthese['etat_global'], 'jour J+1 : synthèse disponible' );
t_egal( 25, $synthese['disponibles'], 'jour J+1 : les 25 massifs portent un statut' );
t_egal( 0, $synthese['sans_donnee'], 'jour J+1 : aucun massif sans donnée' );

// Ni échec, ni alerte, ni trace de rejet : deux succès francs.
$etat   = StateRepository::get();
$issues = array_column( $etat['journal'], 'issue' );
t_note( 'journal des issues : ' . wp_json_encode( $issues ) );
t_egal( 0, (int) $etat['echecs_consecutifs'], 'aucun échec compté sur deux journées identiques' );
t_egal( null, $etat['derniere_erreur'], 'aucune erreur enregistrée' );
t_egal( array(), array_values( array_diff( $issues, array( 'succes' ) ) ), 'le journal ne contient que des succès' );
t_egal( $ymd_j1, (string) $etat['derniere_date_obtenue'], 'la dernière date obtenue est bien J+1' );

// La fraîcheur reflète une récupération réussie : elle n'est pas périmée.
$fraicheur = massifs_fraicheur( $demain );
t_assert( null !== $fraicheur['dernier_releve_le'], 'relevé déclaré réussi', 'instant ISO', $fraicheur['dernier_releve_le'] );
t_egal( false, $fraicheur['perimee'], 'donnée fraîche : aucune bannière de péremption due' );

// --------------------------------------------- MÊME DATE, MÊME CORPS : pas de réécriture
$lignes_avant = t_lignes_statuts();
$rejoue       = Connector::run_now( $demain );
t_assert( true === $rejoue, 'même date rejouée à l\'identique : acceptée sans erreur', true, is_wp_error( $rejoue ) ? $rejoue->get_error_code() : $rejoue );
t_egal( $lignes_avant, t_lignes_statuts(), 'même date, même corps : aucune réécriture inutile' );

// ------------------------------------------- MÊME DATE, CORPS DIFFÉRENT : correction publiée
remove_all_filters( 'pre_http_request' );
t_bouchon_http( t_reponse_200( t_charge_source( 1, 0 ) ) );
$correction = Connector::run_now( $demain );
t_assert( true === $correction, 'correction du même jour : acceptée', true, is_wp_error( $correction ) ? $correction->get_error_code() : $correction );
t_egal( 'autorise', massifs_statut_du_jour( 'sainte-victoire', $demain )['niveau']['cle'], 'correction du même jour : la nouvelle valeur est visible' );
t_egal( $lignes_avant + 25, t_lignes_statuts(), 'correction : une ligne de plus par massif, historique intact' );

t_reset();
t_bilan();
