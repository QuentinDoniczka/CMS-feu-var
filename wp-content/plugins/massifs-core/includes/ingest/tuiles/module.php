<?php
/**
 * Amorçage du module « tuiles » — fond de carte auto-hébergé.
 *
 * Nom de fichier imposé : le chargeur de l'extension découvre un chemin unique
 * et prédit, `<couche>/<module>/module.php`. Un fichier nommé autrement ne serait
 * jamais chargé.
 *
 * Ce module est INERTE : aucun hook, aucun filtre, aucune table, aucune option,
 * aucun transient, aucun cron, aucune route REST, aucun écran, aucun rôle, aucune
 * capability, aucune sortie. Le charger ne fait rien d'observable ; ne pas le
 * charger n'est pas fatal. L'ordre de chargement vis-à-vis des autres modules est
 * donc indifférent.
 *
 * Il n'y a rien à planifier : le fond de carte ne change jamais, sa génération
 * appartient au build hors ligne, et l'hôte mutualisé ne fait que servir des
 * octets statiques. Corollaire opposable : la surface d'écriture du fond de carte
 * en production est NULLE.
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
 * l'arbre de dépendances : si le module a déjà été amorcé par n'importe lequel de
 * ses fichiers, il n'y a rien à refaire.
 */
if ( defined( 'MASSIFS_INGEST_TUILES_VERSION' ) ) {
	return;
}

// `compat.php` requiert lui-même le reste du module ; chaque fichier requiert ses
// propres dépendances, donc n'importe lequel est un point d'entrée valide.
require_once __DIR__ . '/compat.php';
