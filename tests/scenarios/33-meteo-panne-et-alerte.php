<?php
/**
 * Panne de la source : reprise, compteur d'échecs, UNE alerte, et surtout
 * l'instantané de la veille jamais servi pour aujourd'hui.
 *
 * Le corps de l'alerte ne porte AUCUNE valeur de niveau : un chiffre de danger
 * dans un courriel serait exactement l'information que le site refuse
 * d'afficher faute de libellé officiel, transmise par une porte de service.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Meteo\Connector;
use Massifs\Ingest\Meteo\StateRepository;

$purge = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge();

if ( ! defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' ) ) {
	define( 'MASSIFS_METEO_JSON_URL_TEMPLATE', 'http://wordpress/massifs-bouchon-meteo/{date}.json' );
}

add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$boite = array();
t_intercepter_mail( $boite );

$aujourdhui = massifs_jour_courant();
$hier       = t_jour_avant( $aujourdhui );

// Valeur DISTINCTIVE : si elle apparaissait dans un courriel, ce serait sans
// ambiguïté possible une fuite de niveau, et non un fragment de date.
$niveau_temoin = 424242;

// ---------------------------------------------------------------------------
// 1. La veille est en cache. Elle ne doit JAMAIS remplir la journée courante.
// ---------------------------------------------------------------------------
update_option(
	'massifs_meteo_snapshots',
	array(
		str_replace( '-', '', $hier ) => array(
			'schema'        => 1,
			'date_validite' => $hier,
			'zone_cle'      => '13',
			'niveau_source' => $niveau_temoin,
			'publie_le'     => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'recupere_le'   => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'hash'          => hash( 'sha256', 'veille' ),
			'octets'        => 120,
		),
	),
	false
);

t_egal( true, Connector::has_snapshot_for( $hier ), 'la veille est bien en cache' );
t_egal( false, Connector::has_snapshot_for( $aujourdhui ), 'la journée courante ne l\'est pas' );

$m = massifs_meteo_du_jour( $aujourdhui );
t_egal( 'indisponible', $m['etat'], '§4.2 : la valeur de la veille n\'est JAMAIS servie pour aujourd\'hui' );
t_egal( null, $m['niveau'], 'aucun niveau composé depuis la veille' );
t_egal( null, $m['publie_le'], '`publie_le` ne peut venir que de l\'instantané du jour demandé' );
t_egal( $aujourdhui, $m['jour'], '`jour` est toujours le jour DEMANDÉ' );

// ---------------------------------------------------------------------------
// 2. Panne réseau : échecs consécutifs, reprise, une seule alerte.
// ---------------------------------------------------------------------------
t_bouchon_http( new WP_Error( 'http_request_failed', 'Connexion impossible vers la source.' ) );

$r = Connector::run_now( $aujourdhui );
t_assert( is_wp_error( $r ), 'panne réseau : le connecteur rend une erreur', 'WP_Error', is_wp_error( $r ) ? 'WP_Error' : $r );
t_egal( 1, (int) StateRepository::get()['echecs_consecutifs'], 'premier échec compté' );
t_egal( 0, count( $boite ), 'un échec isolé n\'alerte pas : la reprise horaire suffit' );

Connector::run_now( $aujourdhui );
t_egal( 2, (int) StateRepository::get()['echecs_consecutifs'], 'deuxième échec compté' );
t_egal( 0, count( $boite ), 'toujours aucune alerte sous le seuil' );

Connector::run_now( $aujourdhui );
$etat = StateRepository::get();
t_egal( 3, (int) $etat['echecs_consecutifs'], 'troisième échec : le seuil est atteint' );
t_egal( 1, count( $boite ), 'UNE alerte est émise, jamais zéro : aucun échec silencieux' );

// Trois tentatives de plus : le verrou tient.
Connector::run_now( $aujourdhui );
Connector::run_now( $aujourdhui );
Connector::run_now( $aujourdhui );
t_egal( 6, (int) StateRepository::get()['echecs_consecutifs'], 'les échecs continuent d\'être comptés' );
t_egal( 1, count( $boite ), 'verrou par date et par type : UNE seule alerte, jamais une par tentative' );

// ---------------------------------------------------------------------------
// 3. Le corps de l'alerte ne porte aucune valeur de niveau.
// ---------------------------------------------------------------------------
$courriel = $boite[0];
$corps    = (string) ( $courriel['message'] ?? '' );
$sujet    = (string) ( $courriel['subject'] ?? '' );

t_assert( '' !== $corps, 'l\'alerte porte un corps', 'non vide', $corps );
t_assert( false === strpos( $corps, (string) $niveau_temoin ), 'le corps ne contient AUCUNE valeur de niveau', 'absente', $corps );
t_assert( false === strpos( $sujet, (string) $niveau_temoin ), 'le sujet ne contient aucune valeur de niveau' );
t_assert( false === strpos( $corps, 'niveau_source' ), 'le corps ne cite pas le champ de niveau de la source' );
t_assert( false !== strpos( $corps, 'indisponible' ), 'le corps dit ce que le site affiche : l\'indicateur reste indisponible' );
t_assert( false !== strpos( $corps, 'Échecs consécutifs' ), 'le corps porte le compteur d\'échecs' );

// ---------------------------------------------------------------------------
// 4. Rien n'a été écrit, et la lecture reste honnête.
// ---------------------------------------------------------------------------
t_egal( false, Connector::has_snapshot_for( $aujourdhui ), 'aucun instantané inventé pendant la panne' );
t_egal( true, Connector::has_snapshot_for( $hier ), 'la valeur précédente reste intacte en cache' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et le visiteur voit toujours une absence' );

$derniere_erreur = StateRepository::get()['derniere_erreur'];
t_assert( is_array( $derniere_erreur ) && isset( $derniere_erreur['couche'] ), 'la dernière erreur porte sa couche d\'origine', 'transport', $derniere_erreur['couche'] ?? null );

// ---------------------------------------------------------------------------
// 5. La source revient : le compteur retombe à zéro.
// ---------------------------------------------------------------------------
t_bouchon_http(
	array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => (string) wp_json_encode(
			array(
				'schema'        => 1,
				'zone'          => '13',
				'jour'          => $aujourdhui,
				'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
				'niveau_source' => 1,
			)
		),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	)
);

t_egal( true, Connector::run_now( $aujourdhui ), 'la source revient : la charge est acceptée' );
t_egal( 0, (int) StateRepository::get()['echecs_consecutifs'], 'le compteur d\'échecs consécutifs retombe à zéro' );
t_egal( 1, count( $boite ), 'aucun courriel supplémentaire au retour à la normale' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et l\'affichage reste « indisponible » : la garde de vocabulaire prime' );

$purge();
t_reset();
t_bilan();
