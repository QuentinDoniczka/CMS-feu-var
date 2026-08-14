<?php
/**
 * Point d'entrée du module « zones parcourues par le feu ».
 *
 * Nom de fichier imposé par le chargeur de l'extension, qui ne découvre qu'un
 * chemin prédit par module : `<couche>/<module>/module.php`, ou à défaut
 * `<couche>/<module>/bootstrap.php` (`massifs-core.php` l. 122-167). Un fichier
 * nommé autrement ne serait jamais chargé.
 *
 * Ce sous-arbre se charge lui-même : les fichiers suivent la convention WPCS
 * `class-*.php` et ne sont donc PAS résolvables par un autoloader PSR-4.
 *
 * L'inclusion ne produit aucune sortie, n'écrit aucune option et n'émet aucun
 * appel réseau.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$massifs_effis_repertoire = __DIR__ . '/';

// Ordre de dépendance : réglages, dépôts, chaîne de traitement, puis la
// projection, la route, la surface publique et l'amorçage.
foreach (
	array(
		'class-settings.php',
		'class-state-repository.php',
		'class-validator.php',
		'class-releve-repository.php',
		'class-fetcher.php',
		'class-couche.php',
		'class-attribution.php',
		'class-notifier.php',
		'class-runner.php',
		'class-schedule.php',
		'class-route.php',
		'compat.php',
		'class-bootstrap.php',
	) as $massifs_effis_fichier
) {
	require_once $massifs_effis_repertoire . $massifs_effis_fichier;
}

unset( $massifs_effis_repertoire, $massifs_effis_fichier );

\Massifs\Ingest\Effis\Bootstrap::register();
