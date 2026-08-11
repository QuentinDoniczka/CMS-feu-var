<?php
/**
 * Provenance d'un statut enregistré.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vocabulaire fermé des sources.
 *
 * Chaque statut porte sa provenance : c'est une exigence du §4.2 du brief, au
 * même titre que son jour de validité et son auteur.
 */
enum SourceStatut: string {

	/**
	 * Relevé automatisé de la publication préfectorale.
	 */
	case RecuperationOfficielle = 'recuperation_officielle';

	/**
	 * Saisie par un gestionnaire depuis le portail.
	 */
	case SaisieManuelle = 'saisie_manuelle';
}
