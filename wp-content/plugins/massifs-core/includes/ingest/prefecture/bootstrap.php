<?php
/**
 * Point d'entrée du connecteur préfecture.
 *
 * Une seule ligne à écrire côté extension :
 *
 *     require_once MASSIFS_CORE_DIR . 'includes/ingest/prefecture/bootstrap.php';
 *
 * Ce sous-arbre se charge lui-même : les fichiers suivent la convention WPCS
 * `class-*.php` et ne sont donc PAS résolvables par un autoloader PSR-4.
 *
 * L'inclusion ne produit aucune sortie, n'écrit aucune option et n'émet aucun
 * appel réseau.
 *
 * @package Massifs\Ingest\Prefecture
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$massifs_prefecture_repertoire = __DIR__ . '/';

// Ordre de dépendance : réglages, calendrier, dépôts, puis la chaîne de
// traitement, puis la façade et l'amorçage.
foreach (
	array(
		'class-settings.php',
		'class-source-calendar.php',
		'class-state-repository.php',
		'class-snapshot-repository.php',
		'class-projection-listener.php',
		'class-validator.php',
		'class-fetcher.php',
		'class-notifier.php',
		'class-runner.php',
		'class-schedule.php',
		'class-connector.php',
		'class-bootstrap.php',
	) as $massifs_prefecture_fichier
) {
	require_once $massifs_prefecture_repertoire . $massifs_prefecture_fichier;
}

unset( $massifs_prefecture_repertoire, $massifs_prefecture_fichier );

\Massifs\Ingest\Prefecture\Bootstrap::register();
