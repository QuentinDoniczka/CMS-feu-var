<?php
/**
 * Coupe-circuit de la stack de développement (nouveau depuis
 * `docker-cms`) : à `WP_ENVIRONMENT_TYPE=local` sans modèle d'URL redéfini, le
 * connecteur doit être RÉELLEMENT inerte, pas seulement silencieux — aucun
 * évènement planifié, aucun crochet d'exécution, aucun octet réseau.
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
use Massifs\Ingest\Prefecture\StateRepository;

t_egal( 'local', wp_get_environment_type(), 'environnement de la stack = local' );
t_egal( true, Settings::is_disabled(), 'coupe-circuit actif : le connecteur est désarmé' );
t_assert( Bootstrap::is_registered(), 'le connecteur s\'enregistre quand même (crochet de désactivation)' );

// Rien de planifié, et rien qui puisse s'exécuter.
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'aucun évènement d\'ingestion planifié' );
t_egal( false, has_action( Schedule::HOOK, array( Runner::class, 'run_scheduled' ) ), 'aucun rappel d\'exécution branché' );

// `ensure()` ne doit pas replanifier tant que le coupe-circuit est actif.
Schedule::ensure();
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'ensure() ne replanifie rien quand le connecteur est désarmé' );

// Même appelé de force, le chemin d'exécution n'émet aucun octet.
$urls = array();
add_action( 'http_api_debug', static function ( $r, $c, $cl, $a, $url ) use ( &$urls ) {
	$urls[] = $url;
}, 10, 5 );

Runner::run_scheduled();
t_egal( array(), $urls, 'run_scheduled() désarmé : zéro appel sortant' );
$journal = StateRepository::get()['journal'];
t_assert( in_array( 'desactive', array_column( $journal, 'issue' ), true ), 'marqueur « desactive » journalisé', 'desactive', array_column( $journal, 'issue' ) );

$r = Connector::run_now( massifs_jour_courant() );
t_assert( is_wp_error( $r ) && 'massifs_prefecture_desactive' === $r->get_error_code(), 'run_now() désarmé : refus explicite, aucun appel', 'massifs_prefecture_desactive', is_wp_error( $r ) ? $r->get_error_code() : $r );
t_egal( array(), $urls, 'toujours zéro appel sortant après run_now()' );

// WP-Cron lui-même est coupé côté serveur web (constante posée par la stack).
t_note( 'DISABLE_WP_CRON vu depuis l\'outillage : ' . var_export( defined( 'DISABLE_WP_CRON' ) ? DISABLE_WP_CRON : null, true ) . ' (la constante est posée sur le service web, pas sur l\'outillage)' );

t_reset();
t_bilan();
