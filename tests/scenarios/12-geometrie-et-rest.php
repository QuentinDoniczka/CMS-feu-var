<?php
/**
 * Géométrie servie depuis notre propre origine, métadonnées cohérentes
 * avec le fichier réellement servi, et surface REST conforme au contrat
 * (exactement deux entrées depuis l'issue #8 : l'index d'espace de noms et la
 * route publique de lecture — le comportement de cette route est éprouvé en
 * HTTP réel par `22-api-publique-statuts.php`).
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

// Surface REST — ÉGALITÉ SUR LISTE EXACTE, jamais une borne inférieure.
//
// Cette assertion affirmait « aucune route massifs », ce qui était vrai des
// contrats #2 et #3 et est devenu FAUX PAR CONSTRUCTION avec l'issue #8, qui
// publie le point d'accès public en lecture du §5.4 du brief. Le contrat #8 (Q2)
// avait prévu la correction et en avait fixé la forme : une égalité sur liste
// exacte, jamais une suppression ni un affaiblissement en `count() >= 1`.
//
// DEUX entrées, pas une : le cœur enregistre automatiquement la route d'index
// d'espace de noms `/massifs/v1` à côté de la route déclarée. Et la liste est
// EXACTE parce que c'est ce qui rend l'invariant I-11 du contrat #8 opposable —
// une route d'écriture ajoutée par mégarde dans `massifs/v1` rougit ici.
$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );
$routes = array_keys( $serveur->get_routes() );
$nôtres = array_values( array_filter( $routes, static fn( $r ) => str_contains( $r, 'massifs' ) ) );
sort( $nôtres );
t_egal( array( '/massifs/v1', '/massifs/v1/statuts' ), $nôtres, 'la surface REST « massifs » est exactement l\'index d\'espace de noms et la route de lecture (contrat #8, I-11)' );
t_note( 'espaces de noms REST exposés : ' . implode( ', ', $serveur->get_namespaces() ) );

// L'EXTENSION n'enfile aucun asset navigateur.
//
// Le critère est l'ORIGINE du fichier, jamais le nom de la poignée : depuis le
// lot « design system », le THÈME enfile légitimement `massifs-fonts`,
// `massifs-tokens` et `massifs-layout`. Filtrer sur la chaîne « massifs » les
// prendrait pour des assets de l'extension et affirmerait un défaut qui n'existe
// pas. Ce qui est interdit, c'est qu'un fichier servi depuis
// `plugins/massifs-core/` atteigne le navigateur.
do_action( 'wp_enqueue_scripts' );
global $wp_scripts, $wp_styles;
$scripts = is_object( $wp_scripts ) ? $wp_scripts->queue : array();
$styles  = is_object( $wp_styles ) ? $wp_styles->queue : array();
t_note( 'scripts en file : ' . wp_json_encode( $scripts ) . ' / styles : ' . wp_json_encode( $styles ) );

$url_extension = MASSIFS_CORE_URL;
$depuis_extension = static function ( $registre, array $file ) use ( $url_extension ): array {
	$trouves = array();

	foreach ( $file as $poignee ) {
		$objet = is_object( $registre ) && isset( $registre->registered[ $poignee ] ) ? $registre->registered[ $poignee ] : null;
		$src   = is_object( $objet ) && is_string( $objet->src ) ? $objet->src : '';

		if ( '' !== $src && str_contains( $src, $url_extension ) ) {
			$trouves[] = $poignee . ' → ' . $src;
		}
	}

	return $trouves;
};

$tiers = array_merge(
	$depuis_extension( $wp_scripts, (array) $scripts ),
	$depuis_extension( $wp_styles, (array) $styles )
);
t_egal( array(), $tiers, 'aucun fichier servi depuis l\'extension n\'est enfilé pour le navigateur' );

// Et le thème, lui, enfile bien ses trois feuilles — sans quoi les artefacts du
// design system ne seraient chargés par rien.
$feuilles_theme = array_values( array_intersect( array( 'massifs-fonts', 'massifs-tokens', 'massifs-layout' ), (array) $styles ) );
t_egal( array( 'massifs-fonts', 'massifs-tokens', 'massifs-layout' ), $feuilles_theme, 'le thème enfile ses trois feuilles (jetons, polices, mise en page)' );

t_bilan();
