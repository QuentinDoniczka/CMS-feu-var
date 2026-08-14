<?php
/**
 * Ingestion nominale des zones parcourues par le feu.
 *
 * La charge SIMULÉE est une ORIGINE, jamais une branche : elle entre par
 * `wp_remote_get` — bouchonné à la frontière réseau — et traverse les cinq
 * couches du validateur, exactement comme une charge réelle. Ce scénario
 * éprouve ce chemin de bout en bout : filtre départemental, formatage de la
 * surface par le serveur, refus du jour civil nu, et persistance atomique.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\ReleveRepository;
use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\Settings;
use Massifs\Ingest\Effis\StateRepository;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options de ce module.
	 *
	 * `t_reset()` ne les connaît pas : ce module est postérieur au harnais, et
	 * le harnais est hors de l'empreinte de cette chaîne.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

if ( ! function_exists( 't_effis_carre' ) ) {
	/**
	 * Polygone carré centré sur un point.
	 *
	 * FIXTURE DE SCÉNARIO, et le seul endroit du projet où un polygone de zone
	 * parcourue par le feu non vide existe. Le nominal simulé du module, lui,
	 * est un `FeatureCollection` valide et VIDE : dessiner un polygone fictif
	 * sur un vrai massif serait une affirmation géographique fausse, attribuée
	 * à Copernicus.
	 *
	 * @param float $lon Longitude du centre.
	 * @param float $lat Latitude du centre.
	 *
	 * @return array<string, mixed>
	 */
	function t_effis_carre( float $lon, float $lat ): array {
		$d = 0.01;

		return array(
			'type'        => 'Polygon',
			'coordinates' => array(
				array(
					array( $lon - $d, $lat - $d ),
					array( $lon + $d, $lat - $d ),
					array( $lon + $d, $lat + $d ),
					array( $lon - $d, $lat + $d ),
					array( $lon - $d, $lat - $d ),
				),
			),
		);
	}
}

t_reset();
t_effis_purge();

// ARMEMENT EN COURS DE REQUÊTE. Le coupe-circuit n'est pas mémoïsé et ne lit
// aucune option : poser la constante ici suffit à réarmer le module, et l'URL
// pointe sur la stack, ce qui rend toute source réelle inatteignable par
// construction. Aucun scénario n'est suffixé `.arme.php` : `tests/run.sh` l. 25
// code en dur un armement PRÉFECTURE, qui serait la mauvaise constante ici.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

t_egal( 'local', wp_get_environment_type(), 'environnement de la stack = local' );
t_egal( false, Settings::is_disabled(), 'module réarmé en cours de requête par MASSIFS_EFFIS_URL' );

$emprise = massifs_emprise();
t_assert( is_array( $emprise['bbox'] ), 'emprise départementale disponible (sans elle, le lot serait refusé : fail closed)', 'array', $emprise['bbox'] );

$centre_lon = ( (float) $emprise['bbox']['ouest'] + (float) $emprise['bbox']['est'] ) / 2;
$centre_lat = ( (float) $emprise['bbox']['sud'] + (float) $emprise['bbox']['nord'] ) / 2;

$charge = array(
	'type'     => 'FeatureCollection',
	'features' => array(
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-2026-0142',
				'surface_ha'           => 42.0,
				'premiere_observation' => '2026-08-12T09:30:00Z',
				'derniere_observation' => '2026-08-13T21:05:00Z',
			),
			'geometry'   => t_effis_carre( $centre_lon, $centre_lat ),
		),
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-2026-0143',
				'surface_ha'           => 4.5,
				// JOUR CIVIL NU : la source n'a pas publié d'instant. Le module
				// doit rendre la chaîne vide, jamais un midi UTC fabriqué.
				'premiere_observation' => '2026-08-11',
				'derniere_observation' => '2026-08-13T18:00:00Z',
			),
			'geometry'   => t_effis_carre( $centre_lon + 0.05, $centre_lat + 0.03 ),
		),
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-2026-0999',
				'surface_ha'           => 300.0,
				'premiere_observation' => '2026-08-10T04:00:00Z',
				'derniere_observation' => '2026-08-13T04:00:00Z',
			),
			// Hors emprise départementale de plusieurs centaines de kilomètres :
			// la source est continentale, l'entité doit être écartée EN SILENCE,
			// jamais provoquer un rejet du lot.
			'geometry'   => t_effis_carre( 2.35, 48.85 ),
		),
	),
);

// LES APPELS SORTANTS SE COMPTENT DANS LE BOUCHON LUI-MÊME, jamais par
// `http_api_debug` : `WP_Http::request()` rend la main dès que
// `pre_http_request` court-circuite (`wp-includes/class-wp-http.php` l. 277-281)
// et l'action de débogage n'est alors JAMAIS déclenchée. Un compteur branché là
// resterait vide quoi qu'il arrive — il n'observerait rien tout en ayant l'air
// de garantir quelque chose. Le harnais prévoit ce cas : `t_bouchon_http()`
// accepte une fonction qui reçoit l'URL (`tests/bootstrap.php` l. 223-232).
$urls = array();

t_bouchon_http(
	static function ( string $url ) use ( &$urls, $charge ) {
		$urls[] = $url;

		return t_reponse_200( $charge );
	}
);

$verdict = Runner::executer();
t_assert( true === $verdict, 'le relevé est accepté par les cinq couches de validation', true, is_wp_error( $verdict ) ? $verdict->get_error_code() . ' / ' . $verdict->get_error_message() : $verdict );

t_egal( 1, count( $urls ), 'un seul appel sortant émis' );
t_egal( 'wordpress', (string) wp_parse_url( (string) ( $urls[0] ?? '' ), PHP_URL_HOST ), 'l\'appel sortant reste dans la stack : aucun domaine tiers atteint' );

$couche = massifs_zones_parcourues_par_le_feu();
t_note( 'massifs_zones_parcourues_par_le_feu() = ' . wp_json_encode( $couche ) );

t_egal( 'zones_disponibles', $couche['etat'], 'état = zones_disponibles' );
t_egal( 2, $couche['nombre'], 'deux zones retenues : la troisième est hors emprise, écartée en silence' );
t_egal( 2, count( $couche['zones'] ), 'le cardinal annoncé est celui de la liste servie' );
t_egal( 7, $couche['fenetre_jours'], 'fenêtre glissante de la couche source, en jours' );
t_egal( 30, $couche['surface_minimale_ha'], 'seuil de détection annoncé au §4.4 du brief' );
t_egal( 86400, $couche['peremption_secondes'], 'péremption T publiée, donc vérifiable en recette' );

// Toutes les clés du contrat, toujours présentes : le thème n'écrit jamais `isset()`.
$cles_attendues = array( 'etat', 'expire_le', 'fenetre_jours', 'nombre', 'peremption_secondes', 'releve_le', 'surface_minimale_ha', 'zones' );
$cles_obtenues  = array_keys( $couche );
sort( $cles_obtenues );
t_egal( $cles_attendues, $cles_obtenues, 'les huit clés du contrat, ni plus ni moins' );

$cles_zone_attendues = array( 'commune_la_plus_proche', 'derniere_observation', 'geometrie', 'id', 'premiere_observation', 'surface_ha', 'surface_texte' );
$cles_zone           = array_keys( $couche['zones'][0] );
sort( $cles_zone );
t_egal( $cles_zone_attendues, $cles_zone, 'les sept clés d\'une entrée de zone, ni plus ni moins' );

// Instants et fraîcheur.
$insecable = "\u{00A0}";
t_egal( 'zpf-2026-0142', $couche['zones'][0]['id'], 'identifiant opaque repris de la source' );
t_egal( 42.0, $couche['zones'][0]['surface_ha'], 'surface brute conservée comme fait (jamais lue par le thème)' );
t_egal( '42' . $insecable . 'ha', $couche['zones'][0]['surface_texte'], 'surface DÉJÀ FORMATÉE par le serveur, espace insécable avant l\'unité' );
t_egal( '4,5' . $insecable . 'ha', $couche['zones'][1]['surface_texte'], 'sous 10 ha, une décimale : l\'arrondi entier coûterait un dixième de la valeur' );
t_egal( '2026-08-12T09:30:00Z', $couche['zones'][0]['premiere_observation'], 'instant ISO 8601 UTC complet' );
t_egal( '', $couche['zones'][1]['premiere_observation'], 'jour civil nu ⇒ chaîne vide : AUCUN midi UTC fabriqué' );
t_egal( '2026-08-13T18:00:00Z', $couche['zones'][1]['derniere_observation'], 'l\'autre instant de la même zone reste servi' );
t_egal( '', $couche['zones'][0]['commune_la_plus_proche'], 'commune la plus proche : l\'emplacement existe et se tait (aucun référentiel communal)' );

$horodatage = massifs_horodatage( $couche['releve_le'] );
t_assert( '' !== $horodatage['date_longue'], 'releve_le est consommable tel quel par massifs_horodatage()', 'date lisible', $horodatage );
t_egal( strtotime( $couche['releve_le'] ) + 86400, strtotime( $couche['expire_le'] ), 'expire_le = releve_le + T' );

// Le registre transverse de fraîcheur est écrit, jamais relu.
$registre = get_option( 'massifs_dernier_releve', array() );
t_assert( is_array( $registre ) && isset( $registre['effis'] ), 'relevé réussi consigné au registre transverse sous la clé « effis »', 'effis', $registre );

// Attribution.
$attribution = massifs_attribution_zones_parcourues_par_le_feu();
t_egal( '© Union européenne, Copernicus Emergency Management Service / EFFIS', $attribution['phrase'], 'phrase d\'attribution du §9 du brief, verbatim' );
t_egal( false, array_key_exists( 'lien_licence', $attribution ), 'aucune clé lien_licence : le §9 impose cette phrase SANS URL' );
t_egal( '', $attribution['faits']['couche'], 'nom de couche source jamais relevé, donc jamais publié' );
t_egal( 'simule', $attribution['faits']['connecteur'], 'portée simulée auditable dans les faits d\'attribution' );
t_egal( $attribution['phrase'], wp_strip_all_tags( $attribution['phrase'] ), 'la phrase est du TEXTE : aucune balise, aucune entité pré-échappée' );

// Persistance : un seul relevé, dans une option, jamais dans un fichier.
$stocke = get_option( 'massifs_effis_releve', null );
t_assert( is_array( $stocke ) && 2 === count( $stocke['zones'] ), 'le relevé est persisté dans l\'option massifs_effis_releve', 2, is_array( $stocke ) ? count( $stocke['zones'] ) : $stocke );
t_egal( false, is_dir( dirname( __DIR__, 2 ) . '/data/effis' ), 'aucun répertoire de cache fichier n\'a été créé' );
t_egal( 'massifs_effis_releve', ReleveRepository::OPTION, 'nom d\'option stable, contractuel' );

global $wpdb;
$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'massifs_effis_releve' ) ); // phpcs:ignore WordPress.DB
t_assert( in_array( (string) $autoload, array( 'no', 'off', 'auto-off' ), true ), 'option écrite en autoload = false', 'no|off', $autoload );

$etat = StateRepository::get();
t_egal( 0, $etat['echecs_consecutifs'], 'aucun échec consécutif après un relevé accepté' );
t_assert( in_array( 'succes', array_column( $etat['journal'], 'issue' ), true ), 'issue « succes » journalisée', 'succes', array_column( $etat['journal'], 'issue' ) );

t_effis_purge();
t_reset();
t_bilan();
