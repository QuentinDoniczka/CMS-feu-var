<?php
/**
 * Le garde-fou « référentiel » du connecteur (couche 3) protège-t-il
 * encore, alors que la passerelle `massifs_referentiel_codes_source()` promise
 * par la décision §8.6 n'existe pas et que le connecteur retombe sur sa liste
 * d'observation ?
 *
 * On n'inspecte aucune fonction privée : on soumet des charges utiles au point
 * d'entrée public `Connector::validate_payload()` et on observe le verdict.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Ingest\Prefecture\Connector;
use Massifs\Ingest\Prefecture\Settings;

$jour = massifs_jour_courant();

// 1. Constat : la passerelle promise par la décision §8.6 n'est pas fournie.
$existe = function_exists( 'massifs_referentiel_codes_source' );
t_note( 'massifs_referentiel_codes_source() existe : ' . var_export( $existe, true ) );
t_assert( ! $existe, 'CONSTAT (non un souhait) : la passerelle §8.6 est absente, le connecteur utilise son repli' );

// 2. Ce que le repli contient réellement.
$attendus = Settings::massifs_attendus();
sort( $attendus, SORT_STRING );
t_note( 'ensemble de référence effectif (' . count( $attendus ) . ') : ' . implode( ', ', $attendus ) );
t_egal( 27, count( $attendus ), 'le repli porte les 27 identifiants source observés' );

// 3. Charge nominale : acceptée.
$nominale = t_charge_source( 2, 0 )['massifs'];
$v = Connector::validate_payload( $nominale, $jour );
t_assert( true === $v, 'charge nominale (27 identifiants source) acceptée', true, is_wp_error( $v ) ? $v->get_error_code() : $v );

// 4. Un massif manquant : lot entier rejeté.
$amputee = $nominale;
unset( $amputee['1327'] );
$v = Connector::validate_payload( $amputee, $jour );
t_assert( is_wp_error( $v ) && 'referentiel_divergent' === $v->get_error_code(), 'massif manquant => rejet du lot (referentiel_divergent)', 'referentiel_divergent', is_wp_error( $v ) ? $v->get_error_code() : $v );

// 5. Un identifiant inconnu : lot entier rejeté.
$inconnue = $nominale;
unset( $inconnue['1327'] );
$inconnue['1399'] = array( 2, 0 );
$v = Connector::validate_payload( $inconnue, $jour );
t_assert( is_wp_error( $v ) && 'referentiel_divergent' === $v->get_error_code(), 'identifiant inconnu => rejet du lot', 'referentiel_divergent', is_wp_error( $v ) ? $v->get_error_code() : $v );

// 6. Cardinal différent (28 entrées) : rejet.
$excedent = $nominale;
$excedent['1328'] = array( 2, 0 );
$v = Connector::validate_payload( $excedent, $jour );
t_assert( is_wp_error( $v ) && 'referentiel_divergent' === $v->get_error_code(), 'cardinal différent => rejet du lot', 'referentiel_divergent', is_wp_error( $v ) ? $v->get_error_code() : $v );

// 7. LE PIÈGE SÉMANTIQUE. Si la passerelle §8.6 était câblée sur nos 25 codes
//    internes (`massifs_codes()`) au lieu des identifiants émis par la source,
//    la couche 3 rejetterait toutes les charges réelles. On le démontre en
//    branchant ce mauvais ensemble par le filtre public prévu à cet effet.
add_filter( 'massifs_prefecture_massifs_attendus', static fn() => massifs_codes() );
$v = Connector::validate_payload( $nominale, $jour );
t_assert(
	is_wp_error( $v ),
	'PREUVE DE SÉMANTIQUE : brancher les 25 codes internes ferait rejeter toute charge réelle',
	'rejet',
	is_wp_error( $v ) ? $v->get_error_code() : $v
);
t_note( 'code de rejet obtenu : ' . ( is_wp_error( $v ) ? $v->get_error_code() : '(accepté !)' )
	. ' — les codes kebab ne passent pas l\'assainisseur /^\d{3,4}$/ de Settings::codes(), l\'ensemble devient vide' );
remove_all_filters( 'massifs_prefecture_massifs_attendus' );

// 8. Référentiel de secours vide => échec fermé, jamais d'acceptation.
add_filter( 'massifs_prefecture_massifs_attendus', static fn() => array() );
$v = Connector::validate_payload( $nominale, $jour );
t_assert( is_wp_error( $v ) && 'referentiel_indisponible' === $v->get_error_code(), 'ensemble de référence vide => échec fermé', 'referentiel_indisponible', is_wp_error( $v ) ? $v->get_error_code() : $v );
remove_all_filters( 'massifs_prefecture_massifs_attendus' );

t_reset();
t_bilan();
