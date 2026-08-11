<?php
/**
 * Amorçage du module « statuts ».
 *
 * Ce fichier ne déclare que des hooks et le chargement de ses propres fichiers.
 * Il est chargé avant `plugins_loaded` : il ne touche jamais la base de données
 * et ne suppose aucun ordre entre modules.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Statuts\ProjecteurPrefecture;
use Massifs\Domain\Statuts\Schema;

add_filter( 'massifs_core_signature_schema', array( Schema::class, 'signature' ) );
add_action( 'massifs_core_installation', array( Schema::class, 'installer' ) );

// C'est le domaine qui s'abonne à l'ingestion, jamais l'inverse. L'abonnement
// est déclaré même si aucun module d'ingestion n'existe : une action jamais
// déclenchée ne coûte rien, et le domaine ne nomme ainsi aucune chaîne sœur.
add_action( 'massifs_prefecture_snapshot_enregistre', array( ProjecteurPrefecture::class, 'projeter' ) );

require_once __DIR__ . '/api.php';
