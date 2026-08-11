<?php
/**
 * États possibles d'un statut résolu.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vocabulaire fermé des états exposés.
 *
 * Le consommateur filtre avec un `match()` SANS `default` : l'ajout d'un
 * cinquième état doit casser bruyamment, jamais passer en silence.
 */
enum EtatStatut: string {

	/**
	 * Une ligne existe pour ce massif et ce jour de validité.
	 */
	case Disponible = 'disponible';

	/**
	 * Jour demandé futur, rien de publié pour l'instant.
	 */
	case NonEncorePublie = 'non_encore_publie';

	/**
	 * Aucune donnée pour ce jour, en saison, jour passé ou courant.
	 */
	case Indisponible = 'indisponible';

	/**
	 * Dispositif inactif ce jour-là selon le calendrier, et aucune donnée.
	 */
	case HorsSaison = 'hors_saison';
}
