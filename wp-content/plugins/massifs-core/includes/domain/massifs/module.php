<?php
/**
 * Amorçage du module « massifs » — référentiel des périmètres du 13.
 *
 * Nom de fichier imposé : le chargeur de l'extension découvre un chemin unique
 * et prédit, `<couche>/<module>/module.php`. Un fichier nommé autrement ne
 * serait jamais chargé.
 *
 * Ce module est INERTE : aucun hook, aucune table, aucune option, aucun
 * transient, aucun cron, aucune route REST, aucun écran, aucune sortie. Il ne
 * s'abonne à aucun signal d'installation : il n'a rien à installer. Le charger
 * ne fait rien d'observable ; ne pas le charger n'est pas fatal. L'ordre de
 * chargement vis-à-vis des autres modules est donc indifférent.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Garde d'idempotence. La constante est posée par `etats.php`, la feuille de
 * l'arbre de dépendances : si le module a déjà été amorcé par n'importe lequel
 * de ses fichiers, il n'y a rien à refaire.
 */
if ( defined( 'MASSIFS_DOMAINE_MASSIFS_VERSION' ) ) {
	return;
}

// `compat.php` requiert lui-même le reste du module ; chaque fichier requiert
// ses propres dépendances, donc n'importe lequel est un point d'entrée valide.
require_once __DIR__ . '/compat.php';
