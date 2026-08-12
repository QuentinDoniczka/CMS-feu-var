<?php
/**
 * Chemin cron complet, connecteur armé DÈS L'AMORÇAGE.
 *
 * Le modèle d'URL est redéfini avant le chargement de WordPress (`wp --exec`)
 * et pointe vers notre propre serveur : le connecteur se réarme comme en
 * production, et il est structurellement impossible d'atteindre la source
 * réelle. Enregistrement, planification, filtre d'URL de bout en bout en HTTP
 * réel intra-stack, hors-saison sans octet réseau, retrait à la désactivation.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Ingest\Prefecture\Bootstrap;
use Massifs\Ingest\Prefecture\Schedule;
use Massifs\Ingest\Prefecture\Settings;
use Massifs\Ingest\Prefecture\Runner;
use Massifs\Ingest\Prefecture\Connector;
use Massifs\Ingest\Prefecture\SnapshotRepository;
use Massifs\Ingest\Prefecture\StateRepository;

t_assert( defined( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE' ), 'préalable : modèle d\'URL de bouchon posé avant l\'amorçage' );
t_egal( false, Settings::is_disabled(), 'connecteur réarmé par la redéfinition du modèle d\'URL' );

// 1. Enregistrement et planification à l'amorçage.
t_assert( Bootstrap::is_registered(), 'connecteur enregistré au chargement de l\'extension' );
t_assert( false !== has_action( Schedule::HOOK, array( Runner::class, 'run_scheduled' ) ), 'rappel d\'exécution branché sur ' . Schedule::HOOK );
t_assert( is_int( wp_next_scheduled( Schedule::HOOK ) ), 'évènement planifié tout seul à l\'amorçage (auto-réparation sur init)', 'horodatage', wp_next_scheduled( Schedule::HOOK ) );
t_egal( 'hourly', wp_get_schedule( Schedule::HOOK ), 'récurrence horaire (décision §7.4)' );

Schedule::ensure();
Schedule::ensure();
$evenements = 0;
foreach ( (array) _get_cron_array() as $creneau ) {
	if ( isset( $creneau[ Schedule::HOOK ] ) ) {
		$evenements += count( $creneau[ Schedule::HOOK ] );
	}
}
t_egal( 1, $evenements, 'ensure() idempotent : jamais deux évènements pour le même crochet' );

// 2. Le filtre `massifs_prefecture_json_url` est honoré de bout en bout.
$jour = massifs_jour_courant();
$ymd  = str_replace( '-', '', $jour );
t_note( 'URL construite depuis le modèle : ' . Settings::url_for( $ymd ) );

add_filter( 'massifs_prefecture_json_url', static fn( $url, $d ) => 'http://wordpress/massifs-test-ingest/' . $d . '.json', 10, 2 );
t_egal( 'http://wordpress/massifs-test-ingest/' . $ymd . '.json', Settings::url_for( $ymd ), 'le filtre remplace l\'URL appelée' );

$racine = ABSPATH . 'massifs-test-ingest';
if ( ! is_dir( $racine ) ) {
	mkdir( $racine, 0755, true );
}
file_put_contents( $racine . '/' . $ymd . '.json', wp_json_encode( t_charge_source( 2, 0 ) ) );

$urls = array();
add_action( 'http_api_debug', static function ( $r, $c, $cl, $a, $url ) use ( &$urls ) {
	$urls[] = $url;
}, 10, 5 );

$r = Connector::run_now( $jour );
t_assert( true === $r, 'récupération HTTP RÉELLE (intra-stack) réussie', true, is_wp_error( $r ) ? $r->get_error_code() . ':' . $r->get_error_message() : $r );
t_egal( array( 'http://wordpress/massifs-test-ingest/' . $ymd . '.json' ), $urls, 'aucune autre URL n\'a été contactée' );
t_assert( SnapshotRepository::has( $ymd ), 'instantané enregistré' );
t_egal( 27, count( SnapshotRepository::get( $ymd )['massifs'] ), 'les 27 entrées de la source sont dans l\'instantané' );

// Et la projection a bien atteint le modèle de statuts.
global $wpdb;
t_egal( 25, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}massifs_statuts WHERE jour_validite = '" . esc_sql( $jour ) . "'" ), 'chemin complet : 25 statuts du jour en base' );
t_egal( 'disponible', massifs_statut_du_jour( 'sainte-victoire', $jour )['etat'], 'chemin complet : le visiteur voit un statut' );

unlink( $racine . '/' . $ymd . '.json' );
rmdir( $racine );
remove_all_filters( 'massifs_prefecture_json_url' );

// 3. Hors saison : le rappel planifié n'émet AUCUN octet réseau.
t_reset();
$urls = array();
add_filter( 'massifs_prefecture_est_en_saison', '__return_false' );
Runner::run_scheduled();
$issues = array_column( StateRepository::get()['journal'], 'issue' );
t_egal( array(), $urls, 'hors saison : zéro appel sortant' );
t_assert( in_array( 'hors_saison', $issues, true ), 'hors saison : marqueur journalisé', 'hors_saison', $issues );
t_egal( 0, (int) StateRepository::get()['echecs_consecutifs'], 'hors saison : aucun échec compté' );
remove_all_filters( 'massifs_prefecture_est_en_saison' );

// 4. En saison, le rappel planifié passe par le même chemin validé.
t_reset();
t_bouchon_http( t_reponse_200( t_charge_source( 2, 0 ) ) );
Runner::run_scheduled();
t_assert( in_array( 'succes', array_column( StateRepository::get()['journal'], 'issue' ), true ), 'cron en saison : récupération réussie' );

// 5. Désactivation : aucun évènement orphelin ne survit.
Schedule::ensure();
t_assert( false !== wp_next_scheduled( Schedule::HOOK ), 'évènement planifié avant désactivation' );
deactivate_plugins( 'massifs-core/massifs-core.php' );
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'désactivation : évènement retiré (aucun orphelin)' );
$restant = 0;
foreach ( (array) _get_cron_array() as $creneau ) {
	foreach ( array_keys( $creneau ) as $crochet ) {
		if ( str_starts_with( (string) $crochet, 'massifs_' ) ) {
			++$restant;
		}
	}
}
t_egal( 0, $restant, 'désactivation : plus aucun crochet « massifs_* » dans le cron' );

activate_plugin( 'massifs-core/massifs-core.php' );
t_assert( is_plugin_active( 'massifs-core/massifs-core.php' ), 'extension réactivée en fin de test' );
Schedule::unschedule();

t_reset();
t_bilan();
