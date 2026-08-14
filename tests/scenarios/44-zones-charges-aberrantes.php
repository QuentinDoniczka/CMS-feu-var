<?php
/**
 * Charges aberrantes : les cinq couches de validation, et ce qu'un rejet ne
 * fait PAS.
 *
 * Un rejet n'écrit rien : le relevé précédent reste en place et continue de
 * vivre sa propre péremption. Écraser une donnée valide par un rejet ferait
 * mentir la fraîcheur.
 *
 * Une alerte de rejet est envoyée UNE SEULE FOIS PAR ÉPISODE : la récurrence
 * est horaire, un envoi par tentative noierait la boîte du gestionnaire et
 * finirait ignoré — donc inutile le jour où il compte.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\StateRepository;

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

$boite = array();
t_intercepter_mail( $boite );

$prochaine = null;
t_bouchon_http(
	static function ( $url ) use ( &$prochaine ) {
		return $prochaine;
	}
);

// --- Un relevé valide d'abord : c'est lui qui doit SURVIVRE aux rejets. ------
$prochaine = t_reponse_200(
	array(
		'type'     => 'FeatureCollection',
		'features' => array(),
	)
);
t_assert( true === Runner::executer(), 'relevé initial accepté', true, 'rejet' );

$reference = get_option( 'massifs_effis_releve', array() );
t_assert( is_array( $reference ) && '' !== (string) $reference['releve_le'], 'relevé de référence en place', 'un instant', $reference );

// --- Les cinq couches, une aberration chacune -------------------------------
$cas = array(
	array(
		'nom'      => 'transport : page HTML servie en 200 (portail captif, page d\'erreur)',
		'code'     => 'html_sous_200',
		'reponse'  => array(
			'headers'  => array( 'content-type' => 'text/html' ),
			'body'     => '<!DOCTYPE html><html><body>Service temporairement indisponible</body></html>',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		),
	),
	array(
		'nom'     => 'transport : le corps ne commence pas par un objet JSON',
		'code'    => 'corps_non_json',
		'reponse' => array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => '[1, 2, 3]',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		),
	),
	array(
		'nom'     => 'forme : la racine n\'est pas un FeatureCollection',
		'code'    => 'type_racine_invalide',
		'reponse' => t_reponse_200(
			array(
				'type'        => 'Feature',
				'geometry'    => array(
					'type'        => 'Polygon',
					'coordinates' => array(),
				),
				'properties'  => array(),
			)
		),
	),
	array(
		// UN POINT N'EST PAS UNE GÉOMÉTRIE DÉGRADÉE : il signale qu'on a
		// interrogé la mauvaise couche, celle des détections ponctuelles.
		'nom'     => 'forme : géométrie ponctuelle ⇒ mauvaise couche interrogée, lot entier refusé',
		'code'    => 'geometrie_hors_type',
		'reponse' => t_reponse_200(
			array(
				'type'     => 'FeatureCollection',
				'features' => array(
					array(
						'type'       => 'Feature',
						'properties' => array( 'id' => 'zpf-2026-0007' ),
						'geometry'   => array(
							'type'        => 'Point',
							'coordinates' => array( 5.1, 43.4 ),
						),
					),
				),
			)
		),
	),
	array(
		'nom'     => 'géométrie : coordonnée hors bornes terrestres',
		'code'    => 'position_hors_bornes',
		'reponse' => t_reponse_200(
			array(
				'type'     => 'FeatureCollection',
				'features' => array(
					array(
						'type'       => 'Feature',
						'properties' => array( 'id' => 'zpf-2026-0008' ),
						'geometry'   => array(
							'type'        => 'Polygon',
							'coordinates' => array(
								array(
									array( 5.1, 43.4 ),
									array( 512.0, 43.4 ),
									array( 5.2, 43.5 ),
								),
							),
						),
					),
				),
			)
		),
	),
	array(
		'nom'     => 'temporel : lot antérieur à deux fois la péremption',
		'code'    => 'lot_perime',
		'reponse' => t_reponse_200(
			array(
				'type'     => 'FeatureCollection',
				'features' => array(),
			),
			gmdate( 'D, d M Y H:i:s \G\M\T', time() - ( 5 * DAY_IN_SECONDS ) )
		),
	),
);

foreach ( $cas as $essai ) {
	$prochaine = $essai['reponse'];
	$verdict   = Runner::executer();

	t_assert(
		is_wp_error( $verdict ) && $essai['code'] === $verdict->get_error_code(),
		'REJET — ' . $essai['nom'],
		$essai['code'],
		is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict
	);
}

// --- Codes HTTP : un 404 est ICI un échec ------------------------------------
$prochaine = t_reponse_code( 404, 'Not Found' );
$verdict   = Runner::executer();
t_assert( is_wp_error( $verdict ) && 'transport_inattendu' === $verdict->get_error_code(), 'un 404 est un ÉCHEC : il n\'existe aucun état « pas encore publié » pour une fenêtre glissante', 'transport_inattendu', is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

$prochaine = t_reponse_code( 503, '' );
$verdict   = Runner::executer();
t_assert( is_wp_error( $verdict ) && 'source_indisponible' === $verdict->get_error_code(), 'un 5xx est journalisé comme source indisponible', 'source_indisponible', is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

$prochaine = new WP_Error( 'http_request_failed', 'Connexion impossible.' );
$verdict   = Runner::executer();
t_assert( is_wp_error( $verdict ) && 'http_request_failed' === $verdict->get_error_code(), 'panne réseau : échec propre, aucune exception', 'http_request_failed', is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );

// --- CE QU'UN REJET NE FAIT PAS ----------------------------------------------
$apres = get_option( 'massifs_effis_releve', array() );
t_egal( $reference['releve_le'], $apres['releve_le'], 'AUCUN rejet n\'a touché au relevé valide : un échec n\'écrit rien' );
t_egal( $reference, $apres, 'le relevé stocké est identique, octet pour octet' );

$couche = massifs_zones_parcourues_par_le_feu();
t_egal( 'aucune_zone', $couche['etat'], 'la couche continue de servir le dernier relevé VALIDE, dans sa propre péremption' );

$etat = StateRepository::get();
t_assert( $etat['echecs_consecutifs'] >= 6, 'les échecs consécutifs sont comptés', '>= 6', $etat['echecs_consecutifs'] );
t_assert( is_array( $etat['derniere_erreur'] ) && '' !== (string) $etat['derniere_erreur']['couche'], 'la dernière erreur porte sa couche d\'origine', 'une couche', $etat['derniere_erreur'] );
t_assert( count( $etat['journal'] ) <= StateRepository::JOURNAL_MAX, 'le journal reste borné (FIFO)', '<= ' . StateRepository::JOURNAL_MAX, count( $etat['journal'] ) );

// --- Alertes : une seule par épisode -----------------------------------------
$sujets = array_map( static fn( $m ) => (string) ( $m['subject'] ?? '' ), $boite );
$rejets = array_values( array_filter( $sujets, static fn( $s ) => str_contains( $s, 'rejetée' ) ) );
t_note( 'courriels interceptés : ' . wp_json_encode( $sujets ) );
t_egal( 1, count( $rejets ), 'UNE SEULE alerte de rejet, quel que soit le nombre de rejets de l\'épisode' );

$corps = (string) ( $boite[0]['message'] ?? '' );
t_assert( str_contains( $corps, 'CE QUE LE SITE AFFICHE' ), 'l\'alerte dit explicitement ce que le site affiche', 'CE QUE LE SITE AFFICHE', $corps );
t_assert( str_contains( $corps, 'RIEN N\'A ÉTÉ ENREGISTRÉ' ), 'l\'alerte dit que rien n\'a été enregistré', 'RIEN N\'A ÉTÉ ENREGISTRÉ', $corps );

// Le verrou se ré-arme au premier succès.
$prochaine = t_reponse_200(
	array(
		'type'     => 'FeatureCollection',
		'features' => array(),
	)
);
t_assert( true === Runner::executer(), 'un relevé valide est de nouveau accepté', true, 'rejet' );
t_egal( false, StateRepository::was_alerted( 'rejet' ), 'verrou d\'alerte ré-armé au premier succès : un nouvel épisode mérite un nouveau courriel' );
t_egal( 0, StateRepository::get()['echecs_consecutifs'], 'compteur d\'échecs remis à zéro' );

t_effis_purge();
t_reset();
t_bilan();
