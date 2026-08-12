<?php
/**
 * Géométrie servie depuis notre propre origine, métadonnées cohérentes
 * avec le fichier réellement servi, et surface REST conforme au contrat
 * (aucune route dans ce lot).
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

$geo = massifs_geometrie();
t_note( 'massifs_geometrie() = ' . wp_json_encode( $geo ) );

t_egal( true, $geo['disponible'], 'métadonnées de géométrie disponibles' );
t_egal( 'geojson', $geo['format'], 'format geojson' );
t_egal( 11, $geo['zoom_max'], 'zoom max contractuel' );

$origine_site = wp_parse_url( home_url(), PHP_URL_HOST );
$origine_geo  = wp_parse_url( $geo['url'], PHP_URL_HOST );
t_egal( $origine_site, $origine_geo, 'la géométrie est servie depuis NOTRE origine (aucun tiers)' );
t_assert( str_contains( $geo['url'], '?v=' . $geo['version'] ), 'URL porte le jeton de cache-busting', $geo['version'], $geo['url'] );

// Le fichier réellement servi correspond aux métadonnées annoncées.
// Depuis le conteneur d'outillage, le serveur du site s'appelle `wordpress` ;
// c'est le MÊME serveur que `localhost:<port>` vu de l'hôte. On substitue le
// préfixe d'origine sans jamais coder le port en dur : il est réglable dans
// `.env` et il a déjà changé une fois.
$url_interne = 'http://wordpress' . (string) substr( $geo['url'], strlen( (string) home_url() ) );
$reponse     = wp_remote_get( $url_interne, array( 'timeout' => 20 ) );
t_note( 'URL interrogée depuis l\'outillage : ' . $url_interne );
t_assert( ! is_wp_error( $reponse ), 'la géométrie est récupérable en HTTP', 200, is_wp_error( $reponse ) ? $reponse->get_error_message() : wp_remote_retrieve_response_code( $reponse ) );
$corps = (string) wp_remote_retrieve_body( $reponse );
t_egal( 200, (int) wp_remote_retrieve_response_code( $reponse ), 'géométrie : HTTP 200' );
t_egal( (int) $geo['octets'], strlen( $corps ), 'octets annoncés = octets réellement servis' );
t_egal( $geo['sha256'], hash( 'sha256', $corps ), 'sha256 annoncé = sha256 réellement servi' );
t_assert( strlen( $corps ) < 300 * 1024, 'BUDGET §10 : géométrie < 300 Ko', '< 307200 o', strlen( $corps ) );
t_note( 'poids brut mesuré : ' . strlen( $corps ) . ' octets (' . round( strlen( $corps ) / 1024, 1 ) . ' Kio)' );
t_note( 'poids gzip mesuré : ' . strlen( (string) gzencode( $corps, 9 ) ) . ' octets' );
t_note( 'en-tête content-encoding vu par le client PHP (après décompression transparente) : «' . (string) wp_remote_retrieve_header( $reponse, 'content-encoding' ) . '»' );
t_note( 'poids réellement transféré : mesuré côté hôte par `tests/verifier-http.sh`, pas ici — wp_remote_get décompresse avant de rendre le corps.' );

// Chaque Feature porte `properties.code` et rien d'autre, égal à un massif_code.
$fc = json_decode( $corps, true );
$fc = is_array( $fc ) ? $fc : array( 'features' => array() );
t_egal( 'FeatureCollection', $fc['type'] ?? '', 'GeoJSON FeatureCollection' );
t_egal( 25, count( (array) ( $fc['features'] ?? array() ) ), '25 features, une par massif du référentiel' );
$codes_geo = array();
$proprietes_en_trop = array();
foreach ( $fc['features'] as $f ) {
	$codes_geo[] = $f['properties']['code'] ?? '';
	foreach ( array_keys( (array) $f['properties'] ) as $p ) {
		if ( 'code' !== $p ) {
			$proprietes_en_trop[] = $p;
		}
	}
}
sort( $codes_geo );
$codes_ref = massifs_codes();
sort( $codes_ref );
t_egal( $codes_ref, $codes_geo, 'les codes de la géométrie === les codes du référentiel' );
t_egal( array(), array_unique( $proprietes_en_trop ), 'aucune propriété autre que `code` dans la géométrie' );

// Surface REST : le contrat annonce zéro route dans ce lot.
$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );
$routes = array_keys( $serveur->get_routes() );
$nôtres = array_values( array_filter( $routes, static fn( $r ) => str_contains( $r, 'massifs' ) ) );
t_egal( array(), $nôtres, 'aucune route REST « massifs » (conforme aux contrats #2 et #3)' );
t_note( 'espaces de noms REST exposés : ' . implode( ', ', $serveur->get_namespaces() ) );

// L'extension n'enfile aucun asset navigateur.
do_action( 'wp_enqueue_scripts' );
global $wp_scripts, $wp_styles;
$scripts = is_object( $wp_scripts ) ? $wp_scripts->queue : array();
$styles  = is_object( $wp_styles ) ? $wp_styles->queue : array();
t_note( 'scripts en file : ' . wp_json_encode( $scripts ) . ' / styles : ' . wp_json_encode( $styles ) );
$tiers = array_values( array_filter( array_merge( (array) $scripts, (array) $styles ), static fn( $h ) => str_contains( (string) $h, 'massifs' ) ) );
t_egal( array(), $tiers, 'l\'extension n\'enfile aucun script ni style (aucune surface navigateur)' );

t_bilan();
