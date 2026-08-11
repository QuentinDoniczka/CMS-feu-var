<?php
/**
 * Amorçage du module « fraîcheur ».
 *
 * Ce fichier ne déclare que des hooks et le chargement de ses propres fichiers.
 * Ce module n'a pas de table : il n'enregistre aucun handler d'installation.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Fraicheur\RegistreReleves;

// L'instant de publication préfectorale voyage avec le statut : on le retient
// au passage, pour que la phrase de fraîcheur puisse le citer sans requête.
add_action( 'massifs_statut_enregistre', array( RegistreReleves::class, 'noter_publication' ), 10, 2 );

require_once __DIR__ . '/api.php';
