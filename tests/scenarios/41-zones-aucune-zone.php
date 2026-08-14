<?php
/**
 * « Aucune zone » — le nominal simulé, valide et VIDE.
 *
 * Un `FeatureCollection` sans entité N'EST PAS une aberration : c'est le cas
 * nominal la plupart des jours. Le rejeter ferait disparaître la couche
 * précisément quand elle dit vrai. Ce scénario verrouille le fait qu'un lot
 * vide est accepté, daté, et servi comme une MESURE.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\Settings;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options de ce module, que `t_reset()` ne connaît pas.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

t_reset();
t_effis_purge();

// Armement en cours de requête : voir l'en-tête du scénario 40.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

t_egal( false, Settings::is_disabled(), 'module armé' );

// LE NOMINAL SIMULÉ EST VALIDE ET VIDE. Aucun polygone fictif n'est dessiné sur
// un vrai massif : ce serait une affirmation géographique fausse, attribuée à
// « © Union européenne, Copernicus EMS / EFFIS ».
$vide = array(
	'type'     => 'FeatureCollection',
	'features' => array(),
);

t_note( 'charge simulée : ' . wp_json_encode( $vide ) . ' (' . strlen( (string) wp_json_encode( $vide ) ) . ' octets)' );

t_bouchon_http( t_reponse_200( $vide ) );

$verdict = Runner::executer();
t_assert( true === $verdict, 'un FeatureCollection vide est ACCEPTÉ : aucun plancher de taille, zéro entité n\'est pas une aberration', true, is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

$couche = massifs_zones_parcourues_par_le_feu();
t_note( 'massifs_zones_parcourues_par_le_feu() = ' . wp_json_encode( $couche ) );

t_egal( 'aucune_zone', $couche['etat'], 'état = aucune_zone' );
t_egal( 0, $couche['nombre'], 'nombre = 0' );
t_egal( array(), $couche['zones'], 'liste de zones vide' );

// CE QUI SÉPARE « aucune_zone » DE L'INDISPONIBILITÉ : la date de mesure.
t_assert( '' !== $couche['releve_le'], 'releve_le est RENSEIGNÉ : vide parce que MESURÉ porte une date de mesure', 'un instant ISO', $couche['releve_le'] );
t_assert( '' !== $couche['expire_le'], 'expire_le est renseigné', 'un instant ISO', $couche['expire_le'] );
t_egal( 1, preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $couche['releve_le'] ), 'releve_le est un instant ISO 8601 UTC complet' );

$horodatage = massifs_horodatage( $couche['releve_le'] );
t_assert( '' !== $horodatage['heure'], 'la fraîcheur est composable par le thème via massifs_horodatage()', 'une heure', $horodatage );

// Un lot identique au précédent n'est pas une aberration non plus : la fenêtre
// glissante change lentement, deux relevés successifs identiques sont la normale.
$premier = $couche['releve_le'];
$verdict = Runner::executer();
t_assert( true === $verdict, 'un lot identique au précédent est accepté sans rejet', true, is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );
$rejoue = massifs_zones_parcourues_par_le_feu();
t_egal( 'aucune_zone', $rejoue['etat'], 'état inchangé après un second relevé identique' );
t_assert( $rejoue['releve_le'] >= $premier, 'la fraîcheur avance : l\'horodatage faisant autorité est celui du relevé réussi ET validé', $premier, $rejoue['releve_le'] );

t_effis_purge();
t_reset();
t_bilan();
