<?php
/**
 * La veille de fraîcheur est planifiée MÊME QUAND LE CONNECTEUR EST DÉSARMÉ.
 *
 * C'est le trou que l'issue #12 referme : sur cette stack
 * (`WP_ENVIRONMENT_TYPE=local`), le connecteur d'ingestion est désactivé, donc
 * ni son évènement ni son alerte n'existent. La ligne « alerte si tout échoue »
 * y était structurellement inatteignable.
 *
 * Ce fichier ne porte donc AUCUN suffixe `.arme.php` et n'appelle jamais
 * `t_armer_connecteur()` : c'est en soi l'assertion centrale du scénario.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Ingest\Cron\Planificateur;
use Massifs\Ingest\Cron\Veille;
use Massifs\Ingest\Prefecture\Schedule;
use Massifs\Ingest\Prefecture\Settings;
use Massifs\Security\Alertes\Peremption;
use Massifs\Security\Alertes\Verrou;

delete_option( Verrou::OPTION );

// --- Préalable : le connecteur est bien inerte sur cette stack.
t_egal( true, Settings::is_disabled(), 'préalable : le connecteur d’ingestion est désarmé sur cette stack' );
t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'préalable : aucun évènement d’ingestion planifié' );

// --- La veille, elle, est armée et planifiée quand même.
t_egal( 'massifs_veille_fraicheur', Planificateur::HOOK, 'crochet contractuel de la veille' );
t_egal( true, Planificateur::est_armee(), 'veille armée par défaut : aucune option, aucun réglage, aucune capability' );

// Cette assertion est ce qui verrouille l'égalité entre le nom écrit en toutes
// lettres dans `module.php` et la constante `Planificateur::HOOK` : une
// divergence n'émettrait aucune erreur PHP et rendrait la veille muette.
t_assert(
	false !== has_action( Planificateur::HOOK, array( Veille::class, 'executer' ) ),
	'rappel d’exécution branché sur ' . Planificateur::HOOK,
	'un rappel branché',
	has_action( Planificateur::HOOK )
);

// Même verrouillage pour l'orthographe du nom d'action, écrit une fois dans
// l'émetteur et une fois dans l'abonné.
t_assert(
	false !== has_action( 'massifs_donnee_perimee_constatee', array( Peremption::class, 'alerter' ) ),
	'l’abonné d’alerte est branché sur « massifs_donnee_perimee_constatee »',
	'un abonné branché',
	has_action( 'massifs_donnee_perimee_constatee' )
);

t_assert( is_int( wp_next_scheduled( Planificateur::HOOK ) ), 'évènement planifié tout seul à l’amorçage (auto-réparation sur init)', 'horodatage', wp_next_scheduled( Planificateur::HOOK ) );
t_egal( 'hourly', wp_get_schedule( Planificateur::HOOK ), 'récurrence horaire, native, sans aucun créneau' );

// --- Idempotence : jamais deux évènements pour le même crochet.
Planificateur::assurer();
Planificateur::assurer();

$evenements = 0;
foreach ( (array) _get_cron_array() as $creneau ) {
	if ( isset( $creneau[ Planificateur::HOOK ] ) ) {
		$evenements += count( $creneau[ Planificateur::HOOK ] );
	}
}
t_egal( 1, $evenements, 'assurer() idempotent : une seule occurrence planifiée' );

// --- Aucune récurrence maison n'est ajoutée au vocabulaire cron du site.
$maison = array_values(
	array_filter(
		array_keys( wp_get_schedules() ),
		static fn( $cle ) => str_starts_with( (string) $cle, 'massifs' )
	)
);
t_egal( array(), $maison, 'aucune récurrence maison enregistrée (aucun filtre cron_schedules)' );

// --- La veille tourne sans que le connecteur existe.
//
// PORTÉE EXACTE DE CE TÉMOIN, ET SES LIMITES. Le jour demandé ci-dessous est
// HORS période d'activité : `executer()` sort à sa garde 3, donc ce témoin
// n'observe que les gardes 0 à 3 — armement, `function_exists`, et la lecture du
// domaine `massifs_fraicheur()`. Il ne dit RIEN du chemin d'incident, qui va
// jusqu'à l'émission de l'action et au courriel : ce chemin-là est surveillé,
// avec le même témoin, par le scénario 51.
$urls = array();
add_action( 'http_api_debug', static function ( $r, $c, $cl, $a, $url ) use ( &$urls ) {
	$urls[] = $url;
}, 10, 5 );

$boite = array();
t_intercepter_mail( $boite );

Veille::executer( '2026-01-15' );
t_egal( array(), $urls, 'lecture de fraîcheur hors période : aucun appel sortant (gardes 0 à 3)' );
t_egal( 0, count( $boite ), 'lecture de fraîcheur hors période : aucun courriel' );

remove_all_filters( 'pre_wp_mail' );
Planificateur::retirer();
delete_option( Verrou::OPTION );

t_reset();
t_bilan();
