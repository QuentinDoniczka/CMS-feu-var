<?php
/**
 * Route publique de lecture : `GET /massifs/v1/zones-parcourues-par-le-feu`.
 *
 * Elle est le SEUL artefact qui serve la deuxième case de la checklist de
 * l'issue — « mettre les polygones en cache et LES SERVIR DEPUIS NOTRE PROPRE
 * DOMAINE ». Une fonction PHP ne démontre pas cela.
 *
 * `200` dans tous les états de la donnée, jamais `503` : « la couche est
 * indisponible » est un état légitime et attendu, pas une panne serveur. Un
 * `503` enverrait le client dans une branche d'erreur, où la tentation est la
 * reprise, le repli, ou un cache tiers.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Route;
use Massifs\Ingest\Effis\Runner;

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

if ( ! function_exists( 't_effis_appel' ) ) {
	/**
	 * Appelle la route, éventuellement avec un `If-None-Match`.
	 *
	 * @param string|null $if_none_match En-tête de revalidation.
	 *
	 * @return WP_REST_Response
	 */
	function t_effis_appel( ?string $if_none_match = null ): WP_REST_Response {
		$requete = new WP_REST_Request( 'GET', '/' . Route::NAMESPACE_REST . Route::CHEMIN );

		if ( null !== $if_none_match ) {
			$requete->set_header( 'If-None-Match', $if_none_match );
		}

		return rest_do_request( $requete );
	}
}

t_reset();
t_effis_purge();

// Armement en cours de requête : voir l'en-tête du scénario 40.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );

// ---------------------------------------------------------------------------
// Surface REST du module — lecture seule, et rien d'autre.
// ---------------------------------------------------------------------------
$routes = $serveur->get_routes();
$chemin = '/' . Route::NAMESPACE_REST . Route::CHEMIN;
t_egal( '/massifs/v1/zones-parcourues-par-le-feu', $chemin, 'chemin contractuel' );
t_assert( isset( $routes[ $chemin ] ), 'la route est déclarée', $chemin, array_values( array_filter( array_keys( $routes ), static fn( $r ) => str_contains( $r, 'massifs' ) ) ) );

$declaration = $routes[ $chemin ][0];

$methodes = $declaration['methods'];
$methodes = is_array( $methodes ) ? array_keys( array_filter( $methodes ) ) : array_map( 'trim', explode( ',', (string) $methodes ) );
sort( $methodes );
t_egal( array( 'GET' ), $methodes, 'lecture seule : AUCUNE route d\'écriture n\'est déclarée par ce module' );

t_egal( '__return_true', $declaration['permission_callback'], 'permission_callback explicite — jamais absent, jamais dépendant de la session' );
t_egal( array(), (array) $declaration['args'], 'aucun argument : pas de `jour`, pas de `bbox`, pas de `format`' );

// ---------------------------------------------------------------------------
// État 1 — indisponible : 200, pas 503.
// ---------------------------------------------------------------------------
$reponse = t_effis_appel();
t_egal( 200, $reponse->get_status(), 'indisponible : 200' );
$corps = $reponse->get_data();
t_note( 'corps (indisponible) = ' . wp_json_encode( $corps ) );

$cles_attendues = array( 'attribution', 'etat', 'expire_le', 'fenetre_jours', 'nombre', 'releve_le', 'surface_minimale_ha', 'zones' );
$cles           = array_keys( $corps );
sort( $cles );
t_egal( $cles_attendues, $cles, 'les huit clés de la réponse, ni plus ni moins' );

t_egal( 'couche_effis_indisponible', $corps['etat'], 'état indisponible' );
t_egal( '', $corps['releve_le'], 'releve_le vide' );
t_egal( '', $corps['expire_le'], 'expire_le vide' );
t_egal( '', $corps['attribution'], 'attribution vide : pas de donnée, pas de crédit' );
t_egal( 0, $corps['nombre'], 'nombre = 0' );
t_egal( 'FeatureCollection', $corps['zones']['type'], 'zones est un FeatureCollection' );
t_egal( array(), $corps['zones']['features'], 'aucune entité' );
t_assert( str_contains( (string) wp_json_encode( $corps['zones'] ), '"features":[]' ), 'features s\'encode en TABLEAU JSON, directement consommable par L.geoJSON', '"features":[]', wp_json_encode( $corps['zones'] ) );

$entetes = $reponse->get_headers();
t_egal( 'no-cache', (string) ( $entetes['Cache-Control'] ?? '' ), 'Cache-Control: no-cache — jamais de max-age, qui servirait des octets périmés le temps de sa durée' );
t_assert( str_starts_with( (string) ( $entetes['ETag'] ?? '' ), 'W/"' ), 'ETag FAIBLE', 'W/"…"', $entetes['ETag'] ?? null );

// ---------------------------------------------------------------------------
// État 2 — une zone servie.
// ---------------------------------------------------------------------------
$emprise    = massifs_emprise();
$centre_lon = ( (float) $emprise['bbox']['ouest'] + (float) $emprise['bbox']['est'] ) / 2;
$centre_lat = ( (float) $emprise['bbox']['sud'] + (float) $emprise['bbox']['nord'] ) / 2;
$d          = 0.01;

t_bouchon_http(
	t_reponse_200(
		array(
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
					'geometry'   => array(
						'type'        => 'Polygon',
						'coordinates' => array(
							array(
								array( $centre_lon - $d, $centre_lat - $d ),
								array( $centre_lon + $d, $centre_lat - $d ),
								array( $centre_lon + $d, $centre_lat + $d ),
								array( $centre_lon - $d, $centre_lat - $d ),
							),
						),
					),
				),
			),
		)
	)
);

t_assert( true === Runner::executer(), 'relevé accepté', true, 'rejet' );

$reponse = t_effis_appel();
$corps   = $reponse->get_data();
t_note( 'corps (zones_disponibles) = ' . wp_json_encode( $corps ) );

t_egal( 200, $reponse->get_status(), 'zones disponibles : 200' );
t_egal( 'zones_disponibles', $corps['etat'], 'état zones_disponibles' );
t_egal( 1, $corps['nombre'], 'une zone' );
t_egal( '© Union européenne, Copernicus Emergency Management Service / EFFIS', $corps['attribution'], 'attribution servie AVEC la donnée, verbatim et entière' );

$feature = $corps['zones']['features'][0];
t_egal( 'Feature', $feature['type'], 'chaque entité est un Feature' );
t_egal( 'Polygon', $feature['geometry']['type'], 'géométrie surfacique' );

$proprietes = array_keys( $feature['properties'] );
sort( $proprietes );
t_egal( array( 'commune_la_plus_proche', 'derniere_observation', 'id', 'premiere_observation', 'surface_ha', 'surface_texte' ), $proprietes, 'les six propriétés contractuelles d\'une entité' );
t_egal( 'zpf-2026-0142', $feature['properties']['id'], 'identifiant servi' );
t_egal( 42.0, $feature['properties']['surface_ha'], 'surface brute servie à la carte' );
t_egal( "42\u{00A0}ha", $feature['properties']['surface_texte'], 'surface déjà formatée servie au texte' );

// ---------------------------------------------------------------------------
// ETag et 304.
// ---------------------------------------------------------------------------
$etag = (string) $reponse->get_headers()['ETag'];

$revalide = t_effis_appel( $etag );
t_egal( 304, $revalide->get_status(), 'If-None-Match concordant ⇒ 304' );
t_egal( null, $revalide->get_data(), '304 : aucun corps' );

$forte = t_effis_appel( substr( $etag, 2 ) );
t_egal( 304, $forte->get_status(), 'comparaison FAIBLE : le préfixe W/ est retiré des DEUX côtés (RFC 9110)' );

$joker = t_effis_appel( '*' );
t_egal( 304, $joker->get_status(), 'If-None-Match: * est accepté' );

$perime = t_effis_appel( 'W/"une-empreinte-qui-n-est-plus-la-bonne"' );
t_egal( 200, $perime->get_status(), 'empreinte périmée ⇒ 200 et corps complet' );

// L'ETag suit la donnée : il change quand la couche change.
t_effis_purge();
$apres = t_effis_appel();
t_assert( (string) $apres->get_headers()['ETag'] !== $etag, 'l\'ETag change quand la couche change', 'un autre ETag', $apres->get_headers()['ETag'] );

// ---------------------------------------------------------------------------
// Invariance par session : la réponse ne dépend d'aucun utilisateur, d'aucun
// cookie. Une route publique dont la sortie varie par session est un cache
// empoisonnable.
// ---------------------------------------------------------------------------
$anonyme = t_effis_appel()->get_data();

$administrateurs = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);

if ( array() !== $administrateurs ) {
	wp_set_current_user( (int) $administrateurs[0] );
	$connecte = t_effis_appel()->get_data();
	wp_set_current_user( 0 );

	t_egal( $anonyme, $connecte, 'réponse invariante par session' );
} else {
	t_note( 'aucun administrateur en base : invariance par session non éprouvée ici' );
}

t_effis_purge();
t_reset();
t_bilan();
