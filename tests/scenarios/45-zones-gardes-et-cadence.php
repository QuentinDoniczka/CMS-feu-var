<?php
/**
 * Coupe-circuit et gardes de cadence : ZÉRO OCTET SORTANT tant qu'une porte
 * n'est pas franchie.
 *
 * Toutes les gardes sont évaluées AVANT le moindre appel réseau. Une stack non
 * configurée n'émet rien du tout ; une stack configurée n'émet au plus que
 * quatre appels par jour, pour une source publiée de l'ordre de deux fois par
 * jour.
 *
 * L'ORDRE DE CE FICHIER EST SIGNIFIANT : l'état désarmé s'éprouve d'abord,
 * puisque l'armement passe par une constante, et qu'une constante ne se
 * dépose pas.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Bootstrap;
use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\Schedule;
use Massifs\Ingest\Effis\Settings;
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

// LES APPELS SORTANTS SE COMPTENT DANS LE BOUCHON LUI-MÊME, jamais par
// `http_api_debug` : `WP_Http::request()` rend la main dès que
// `pre_http_request` court-circuite (`wp-includes/class-wp-http.php` l. 277-281)
// et l'action de débogage n'est alors JAMAIS déclenchée. Un compteur branché là
// resterait vide quoi qu'il arrive — et ce fichier ne prouverait plus rien de
// ses gardes de cadence, tout en restant vert sur les assertions à zéro. Le
// harnais prévoit ce cas : `t_bouchon_http()` accepte une fonction qui reçoit
// l'URL (`tests/bootstrap.php` l. 223-232).
$urls = array();

t_bouchon_http(
	static function ( string $url ) use ( &$urls ) {
		$urls[] = $url;

		return t_reponse_200(
			array(
				'type'     => 'FeatureCollection',
				'features' => array(),
			)
		);
	}
);

// ---------------------------------------------------------------------------
// 1. COUPE-CIRCUIT ARMÉ — aucune constante d'URL, environnement local.
// ---------------------------------------------------------------------------
t_egal( 'local', wp_get_environment_type(), 'environnement de la stack = local' );
t_egal( true, Settings::is_disabled(), 'coupe-circuit actif : le module est désarmé' );
t_assert( Bootstrap::is_registered(), 'le module s\'enregistre quand même (crochet de désactivation, route publique)', true, Bootstrap::is_registered() );

t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'aucun évènement d\'ingestion planifié' );
t_egal( false, has_action( Schedule::HOOK, array( Runner::class, 'run_scheduled' ) ), 'aucun rappel d\'exécution branché' );

Schedule::ensure();
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'ensure() ne replanifie rien quand le module est désarmé' );

Runner::run_scheduled();
t_egal( array(), $urls, 'désarmé : ZÉRO appel sortant, même appelé de force' );
t_assert( in_array( 'desactive', array_column( StateRepository::get()['journal'], 'issue' ), true ), 'marqueur « desactive » journalisé', 'desactive', array_column( StateRepository::get()['journal'], 'issue' ) );

$couche = massifs_zones_parcourues_par_le_feu();
t_egal( 'couche_effis_indisponible', $couche['etat'], 'désarmé : la couche se déclare indisponible, jamais « aucune zone »' );

// Le marqueur est dédupliqué : un état stable ne noie pas le journal FIFO à
// raison d'une entrée par heure.
$avant = count( StateRepository::get()['journal'] );
Runner::run_scheduled();
Runner::run_scheduled();
t_egal( $avant, count( StateRepository::get()['journal'] ), 'marqueur d\'état stable dédupliqué' );
t_egal( array(), $urls, 'toujours zéro appel sortant' );

// ---------------------------------------------------------------------------
// 2. ARMEMENT EN COURS DE REQUÊTE — ce que rend possible un coupe-circuit non
//    mémoïsé, et ce qui permet de se passer d'un suffixe `.arme.php`.
// ---------------------------------------------------------------------------
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}
t_egal( false, Settings::is_disabled(), 'coupe-circuit ré-évalué en cours de requête : le module est armé' );

Schedule::ensure();
$prochain = wp_next_scheduled( Schedule::HOOK );
t_assert( is_int( $prochain ) && $prochain > time(), 'ensure() planifie l\'évènement, décalé dans le futur', '> maintenant', $prochain );
t_egal( 'hourly', (string) wp_get_schedule( Schedule::HOOK ), 'récurrence native `hourly` : la reprise du §4.5 sans une seule boucle bloquante' );
t_egal( 'massifs_effis_recuperation', Schedule::HOOK, 'nom de crochet contractuel' );

// ---------------------------------------------------------------------------
// 3. URL VIDE — le défaut est la chaîne vide, jamais une URL tierce en dur.
// ---------------------------------------------------------------------------
add_filter( 'massifs_effis_url', static fn() => '' );
t_egal( '', Settings::url(), 'URL résolue vide' );

Runner::run_scheduled();
t_egal( array(), $urls, 'URL vide : zéro appel sortant' );
t_assert( in_array( 'url_absente', array_column( StateRepository::get()['journal'], 'issue' ), true ), 'marqueur « url_absente » journalisé', 'url_absente', array_column( StateRepository::get()['journal'], 'issue' ) );

remove_all_filters( 'massifs_effis_url' );
t_assert( '' !== Settings::url(), 'URL de nouveau résolue', 'une URL', Settings::url() );

// ---------------------------------------------------------------------------
// 4. CADENCE — anti-rafale sur la TENTATIVE, suffisance sur le SUCCÈS.
// ---------------------------------------------------------------------------
Runner::run_scheduled();
t_egal( 1, count( $urls ), 'premier passage armé : un appel sortant' );
t_egal( 'wordpress', (string) wp_parse_url( (string) $urls[0], PHP_URL_HOST ), 'l\'appel reste dans la stack : aucun domaine tiers atteint' );
t_egal( 'aucune_zone', massifs_zones_parcourues_par_le_feu()['etat'], 'la couche est alimentée' );

Runner::run_scheduled();
Runner::run_scheduled();
t_egal( 1, count( $urls ), 'anti-rafale : une tentative de moins de 30 min interdit tout nouvel appel' );

// Tentative vieillie, succès encore frais : la suffisance prend le relais.
$etat                       = get_option( 'massifs_effis_etat' );
$etat['derniere_tentative'] = gmdate( Settings::FORMAT_ISO_UTC, time() - HOUR_IN_SECONDS );
update_option( 'massifs_effis_etat', $etat, false );

Runner::run_scheduled();
t_egal( 1, count( $urls ), 'suffisance : un succès de moins de 6 h rend l\'appel inutile' );

// Les deux vieillis : l'appel repart.
$etat                       = get_option( 'massifs_effis_etat' );
$etat['derniere_tentative'] = gmdate( Settings::FORMAT_ISO_UTC, time() - ( 7 * HOUR_IN_SECONDS ) );
$etat['derniere_reussite']  = gmdate( Settings::FORMAT_ISO_UTC, time() - ( 7 * HOUR_IN_SECONDS ) );
update_option( 'massifs_effis_etat', $etat, false );

Runner::run_scheduled();
t_egal( 2, count( $urls ), 'les deux gardes franchies : un nouvel appel est émis' );
t_note( 'plafond nominal de la cadence : 4 appels sortants par jour (garde de suffisance à 6 h)' );

Schedule::unschedule();
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'unschedule() retire l\'évènement : aucune orpheline à la désactivation' );

t_effis_purge();
t_reset();
t_bilan();
