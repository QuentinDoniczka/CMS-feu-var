<?php
/**
 * PANNE INDÉPENDANTE DES DEUX ARTEFACTS — contrat #45, §4.6 et §13.4.
 *
 * Le §4.6 énonce une propriété que le contrat exige de PROUVER et non
 * d'affirmer : si l'artefact de lookup est absent, `massifs[].communes` reste
 * servi, parce qu'il est baké dans `data/massifs-13.php` et ne dépend d'aucun
 * fichier de géométrie. La chaîne de développement n'a pas pu le montrer, faute
 * d'avoir pu retirer le fichier. Dans la stack Docker, on peut : ce scénario
 * l'ENLÈVE, observe le site entier — domaine, JSON public servi en HTTP,
 * gabarit réel — puis le REMET.
 *
 * LE FICHIER EST TOUJOURS REMIS. Sa copie est prise avant toute manipulation,
 * son empreinte est mémorisée, et une fonction d'arrêt le rétablit même si le
 * scénario meurt en cours de route — une erreur fatale ne doit pas laisser le
 * dépôt amputé d'un artefact commité.
 *
 * `lookup_communes()` mémoïse son ouverture pour la durée du processus : ce
 * scénario ne peut donc observer qu'UN SEUL monde, celui de l'artefact absent.
 * C'est pour cela qu'il est un fichier à part, et non un chapitre du scénario
 * nominal.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Runner;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options du module EFFIS.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

/**
 * Carré GeoJSON centré sur un point.
 *
 * @param float $lon Longitude.
 * @param float $lat Latitude.
 * @param float $d   Demi-côté en degrés.
 *
 * @return array<string, mixed>
 */
function t_carre( float $lon, float $lat, float $d = 0.002 ): array {
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

/**
 * GET intra-stack sans redirection canonique. Voir le scénario 48.
 *
 * @param string $chemin Chemin absolu.
 *
 * @return array{code:int,corps:string}
 */
function t_page( string $chemin ): array {
	$home = (string) get_option( 'home' );
	$hote = (string) wp_parse_url( $home, PHP_URL_HOST );
	$port = wp_parse_url( $home, PHP_URL_PORT );

	$reponse = wp_remote_get(
		'http://wordpress' . $chemin,
		array(
			'timeout' => 30,
			'headers' => array( 'Host' => $hote . ( $port ? ':' . $port : '' ) ),
		)
	);

	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $reponse ),
		'corps' => is_wp_error( $reponse ) ? $reponse->get_error_message() : (string) wp_remote_retrieve_body( $reponse ),
	);
}

t_reset();
t_effis_purge();

if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

// ---------------------------------------------------------------------------
// FILET DE SÉCURITÉ — pris AVANT toute manipulation.
// ---------------------------------------------------------------------------

$chemin_lookup = WP_PLUGIN_DIR . '/massifs-core/includes/domain/massifs/communes-13.lookup.json';

t_assert( is_file( $chemin_lookup ), 'PRÉALABLE : l\'artefact est bien là avant qu\'on y touche — sinon le scénario serait vert pour la mauvaise raison', 'présent', 'absent' );

$sauvegarde = (string) file_get_contents( $chemin_lookup );
$empreinte  = hash( 'sha256', $sauvegarde );

t_assert( '' !== $sauvegarde, 'PRÉALABLE : la copie de sauvegarde est prise', 'non vide', 'vide' );

$restaurer = static function () use ( $chemin_lookup, $sauvegarde, $empreinte ): bool {
	if ( is_file( $chemin_lookup ) && hash_file( 'sha256', $chemin_lookup ) === $empreinte ) {
		return true;
	}

	file_put_contents( $chemin_lookup, $sauvegarde );

	return is_file( $chemin_lookup ) && hash_file( 'sha256', $chemin_lookup ) === $empreinte;
};

// Même en cas d'erreur fatale : l'artefact revient.
register_shutdown_function( $restaurer );

// ---------------------------------------------------------------------------
// LE MONDE SANS ARTEFACT.
// ---------------------------------------------------------------------------

t_assert( unlink( $chemin_lookup ), 'l\'artefact de lookup est retiré', 'retiré', 'échec de suppression' );
t_assert( ! file_exists( $chemin_lookup ), 'et il n\'est réellement plus là', 'absent', 'toujours présent' );

// --- §4.6 : les communes PAR MASSIF restent servies. ------------------------

$alpilles = massifs_massif( 'alpilles' );

t_assert( is_array( $alpilles ) && isset( $alpilles['communes'] ), 'le référentiel reste lisible sans l\'artefact de lookup', 'array', gettype( $alpilles ) );
t_assert( is_array( $alpilles['communes'] ) && count( $alpilles['communes'] ) > 0, '§4.6 : `massifs[].communes` est TOUJOURS servi — il est baké, il ne dépend d\'aucun fichier de géométrie', 'liste non vide', $alpilles['communes'] );
t_egal( 'Saint-Rémy-de-Provence', $alpilles['communes'][0], '§4.1 : et il reste trié par surface décroissante, à l\'identique' );

$sans_communes = array();

foreach ( massifs_codes() as $code ) {
	$ligne = massifs_massif( $code );

	if ( ! is_array( $ligne ) || ! is_array( $ligne['communes'] ) || array() === $ligne['communes'] ) {
		$sans_communes[] = $code;
	}
}

t_egal( array(), $sans_communes, '§4.6 : les 25 massifs actifs portent tous leurs communes, artefact absent' );
t_egal( 'calculee', massifs_lacunes()['communes']['statut'], '§7 : le drapeau reste « calculee » — la liste ne dépend pas du lookup' );

// --- §4.6 en HTTP : le JSON public ne bouge pas d'un octet. -----------------

$reponse = wp_remote_get( 'http://wordpress/wp-json/massifs/v1/statuts', array( 'timeout' => 20 ) );
t_egal( 200, (int) wp_remote_retrieve_response_code( $reponse ), 'la route publique répond, artefact absent' );

$json = json_decode( (string) wp_remote_retrieve_body( $reponse ), true );
t_assert( is_array( $json ) && isset( $json['massifs'] ), 'la route publique rend un objet exploitable', 'array', gettype( $json ) );
t_egal( 'calculee', $json['referentiel']['communes_statut'], '§4.6 : `communes_statut` reste « calculee » dans le JSON servi' );

$vides = array_values(
	array_filter(
		$json['massifs'],
		static function ( array $ligne ): bool {
			return ! isset( $ligne['communes'] ) || array() === $ligne['communes'];
		}
	)
);
t_egal( array(), $vides, '§4.6 : les 25 lignes du JSON public portent leurs communes, artefact absent' );

$alpilles_json = array_values( array_filter( $json['massifs'], static function ( array $l ): bool {
	return 'alpilles' === $l['code'];
} ) );
t_egal( $alpilles['communes'], $alpilles_json[0]['communes'], '§4.6 : le JSON servi en HTTP dit EXACTEMENT ce que le domaine dit' );

// --- §13.4 : le seam, lui, dit explicitement pourquoi il se tait. -----------

$resolue = massifs_commune_de_la_zone( t_carre( 5.1018, 43.5952 ) );

t_egal( false, $resolue['trouvee'], '§13.4 : la commune d\'une zone n\'est plus résolue' );
t_egal( 'communes_artefact_absent', $resolue['etat'], '§13.4 : et l\'état NOMME la panne — fichier de lookup absent' );
t_egal( '', $resolue['nom'], '§13.4 : aucun nom deviné' );
t_egal( '', $resolue['insee'], '§13.4 : aucun code' );
t_egal( '', $resolue['departement'], '§13.4 : aucun département' );
t_egal( null, $resolue['distance_m'], '§13.4 : aucune distance' );
t_egal( '', massifs_commune_de_la_zone_nom( t_carre( 5.1018, 43.5952 ) ), '§13.4 : la commodité rend la chaîne vide, que le gabarit omet' );

// --- L'attribution IGN survit : elle vit dans `data/`, pas dans le lookup. ---

$attribution = massifs_attribution_communes();

foreach ( array( 'phrase', 'phrase_courte', 'lien_source', 'lien_licence' ) as $cle ) {
	t_assert( '' !== $attribution[ $cle ], '§5 : l\'attribution reste TOTALE sans l\'artefact — ' . $cle, 'non vide', $attribution[ $cle ] );
}

t_assert( str_contains( t_page( '/mentions-legales/' )['corps'], esc_html( $attribution['phrase'] ) ), '§9 : la mention IGN reste rendue — la Licence Ouverte 2.0 ne dépend pas d\'un fichier de géométrie', $attribution['phrase'], 'absente' );

// --- Le front dégrade proprement : la paire est omise, rien d'autre ne bouge.

$appels = array();

add_filter(
	'pre_http_request',
	static function ( $court_circuit, $args, $url ) use ( &$appels ) {
		if ( ! str_contains( (string) $url, 'massifs-bouchon-effis' ) ) {
			return $court_circuit;
		}

		$appels[] = (string) $url;

		return t_reponse_200(
			array(
				'type'     => 'FeatureCollection',
				'features' => array(
					array(
						'type'       => 'Feature',
						'properties' => array(
							'id'                   => 'zpf-panne-1',
							'surface_ha'           => 88.0,
							'premiere_observation' => '2026-08-12T09:30:00Z',
							'derniere_observation' => '2026-08-13T21:05:00Z',
						),
						'geometry'   => t_carre( 5.1018, 43.5952, 0.002 ),
					),
				),
			)
		);
	},
	10,
	3
);

t_assert( true === Runner::executer(), 'l\'ingestion EFFIS aboutit malgré l\'artefact absent — une panne du lookup n\'arrête pas la couche', true, 'refusée' );

$couche = massifs_zones_parcourues_par_le_feu();
t_egal( 'zones_disponibles', $couche['etat'], 'la couche est servie : la zone est là, seule la commune manque' );
t_egal( '', $couche['zones'][0]['commune_la_plus_proche'], '§13.4 : la clé existe et se tait — jamais un nom deviné, jamais une erreur' );

ob_start();
get_template_part( 'templates/parts/panneau-feu', null, array( 'zones_parcourues' => $couche ) );
$html = (string) ob_get_clean();

t_assert( ! str_contains( $html, 'Commune la plus proche' ), '§13.4 : le gabarit OMET la paire — ni tiret, ni « non renseigné », ni message d\'erreur', 'aucune paire', 'paire rendue' );
t_assert( str_contains( $html, 'Surface estimée' ), '§13.4 : et le reste du panneau est rendu à l\'identique — la panne est locale', 'panneau complet', $html );
t_assert( ! str_contains( $html, 'non renseigné' ) && ! str_contains( $html, 'indisponible' ), '§13.4 : aucun avertissement n\'est adressé au visiteur pour une panne qui ne le concerne pas', 'silence', $html );

// ---------------------------------------------------------------------------
// REMISE EN PLACE — et on le VÉRIFIE, on ne l'espère pas.
// ---------------------------------------------------------------------------

t_assert( $restaurer(), 'l\'artefact est remis, octet pour octet', $empreinte, is_file( $chemin_lookup ) ? hash_file( 'sha256', $chemin_lookup ) : 'absent' );
t_egal( $empreinte, hash_file( 'sha256', $chemin_lookup ), 'MÉNAGE : le dépôt retrouve exactement l\'artefact commité' );

t_effis_purge();
t_reset();

t_bilan();
