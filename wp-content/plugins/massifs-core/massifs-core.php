<?php
/**
 * Plugin Name: Massifs Core
 * Plugin URI: https://github.com/QuentinDoniczka/CMS-feu-var
 * Description: Extension dédiée — domaine métier, ingestion des données (préfecture, Météo-France, EFFIS), API et portail sécurisé de mise à jour pour l'accès quotidien aux massifs forestiers des Bouches-du-Rhône. Squelette d'amorçage — le code réel est développé par les chaînes fonctionnelles dédiées (voir docs/BRIEF.md et CLAUDE.md).
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: MASSIFS
 * Author URI: https://github.com/QuentinDoniczka/CMS-feu-var
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: massifs-core
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Squelette d'amorçage volontairement vide : le domaine métier
// (includes/domain/), l'ingestion (includes/ingest/), l'API (includes/rest/),
// l'administration (includes/admin/) et la sécurité (includes/security/) sont
// la responsabilité des chaînes fonctionnelles. Ce fichier existe uniquement
// pour que l'extension soit valide et activable par la stack Docker locale.
