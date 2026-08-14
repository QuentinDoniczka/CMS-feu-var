<?php
/**
 * Amorçage du module « météo » — indicateur de danger météo des forêts.
 *
 * Nom de fichier imposé : le chargeur de l'extension découvre les modules par
 * convention, en chargeant `<couche>/<module>/module.php` ou, à défaut,
 * `<couche>/<module>/bootstrap.php`. Il n'y a donc AUCUNE ligne à écrire dans
 * `massifs-core.php`, et il ne faut pas en écrire : ce fichier ne serait pas
 * modifiable par deux chaînes à la fois sans écrasement mutuel.
 *
 * Corollaire, qui est la vraie raison d'être de cette prudence : ce sous-arbre
 * n'est pas inerte par absence de câblage, il est chargé DÈS QUE CE FICHIER
 * EXISTE. Un `ParseError` dans l'un des fichiers requis ci-dessous n'est pas
 * rattrapable par `try/catch` : ce serait un écran blanc sur tout le site.
 *
 * Ce sous-arbre se charge lui-même : les fichiers suivent la convention WPCS
 * `class-*.php` et ne sont donc PAS résolvables par un autoloader PSR-4.
 *
 * L'inclusion ne produit aucune sortie, n'écrit aucune option et n'émet aucun
 * appel réseau. Elle pose des crochets, et rien d'autre.
 *
 * Aucun crochet d'activation n'est requis : la planification est
 * auto-réparatrice sur `init`. Seule la désactivation est câblée, pour qu'une
 * extension désactivée ne laisse pas d'évènement orphelin dans le cron.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'idempotence : un double `require` ne double pas les crochets.
if ( defined( 'MASSIFS_INGEST_METEO_VERSION' ) ) {
	return;
}

define( 'MASSIFS_INGEST_METEO_VERSION', '1.0.0' );

$massifs_meteo_repertoire = __DIR__ . '/';

// Ordre de dépendance : garde de vocabulaire, réglages, calendrier, dépôts,
// puis la chaîne de traitement, puis la lecture et la façade.
foreach (
	array(
		'class-vocabulaire.php',
		'class-settings.php',
		'class-source-calendar.php',
		'class-state-repository.php',
		'class-snapshot-repository.php',
		'class-validator.php',
		'class-fetcher.php',
		'class-notifier.php',
		'class-runner.php',
		'class-schedule.php',
		'class-releve.php',
		'class-lecture.php',
		'api.php',
		'class-connector.php',
	) as $massifs_meteo_fichier
) {
	require_once $massifs_meteo_repertoire . $massifs_meteo_fichier;
}

unset( $massifs_meteo_repertoire, $massifs_meteo_fichier );

register_deactivation_hook(
	defined( 'MASSIFS_CORE_FICHIER' ) ? (string) MASSIFS_CORE_FICHIER : dirname( __DIR__, 3 ) . '/massifs-core.php',
	array( \Massifs\Ingest\Meteo\Schedule::class, 'unschedule' )
);

if ( \Massifs\Ingest\Meteo\Settings::is_disabled() ) {
	// Un profil qui aurait hérité d'un évènement planifié doit devenir réellement
	// inerte, et pas seulement silencieux. `wp_next_scheduled` lit le cache
	// d'options : dans le cas nominal, aucune écriture n'a lieu à l'inclusion.
	if ( wp_next_scheduled( \Massifs\Ingest\Meteo\Schedule::HOOK ) ) {
		\Massifs\Ingest\Meteo\Schedule::unschedule();
	}

	return;
}

add_action( 'init', array( \Massifs\Ingest\Meteo\Schedule::class, 'ensure' ), 20 );
add_action( \Massifs\Ingest\Meteo\Schedule::HOOK, array( \Massifs\Ingest\Meteo\Runner::class, 'run_scheduled' ) );

// Chargeur tardif : si `init` est déjà passé, le crochet ci-dessus ne se
// déclenchera jamais pour cette requête.
if ( did_action( 'init' ) > 0 ) {
	\Massifs\Ingest\Meteo\Schedule::ensure();
}
