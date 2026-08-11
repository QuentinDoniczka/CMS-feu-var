<?php
/**
 * Objet valeur : activité calendaire du dispositif à une date.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Réponse de `Saison::evaluer()`.
 */
final class ResultatSaison {

	/**
	 * Construit le résultat.
	 *
	 * @param string $jour                Jour demandé.
	 * @param bool   $active              Le dispositif est-il actif ce jour-là, selon le calendrier seul ?
	 * @param string $debut               Début du dispositif pour l'année du jour demandé.
	 * @param string $fin                 Fin du dispositif pour l'année du jour demandé.
	 * @param string $prochaine_ouverture Premier jour actif à venir — toujours une date.
	 * @param bool   $confirmee           Les bornes sont-elles confirmées par la préfecture ?
	 */
	public function __construct(
		public readonly string $jour,
		public readonly bool $active,
		public readonly string $debut,
		public readonly string $fin,
		public readonly string $prochaine_ouverture,
		public readonly bool $confirmee
	) {}

	/**
	 * Forme exposée aux consommateurs.
	 *
	 * @return array<string, bool|string>
	 */
	public function en_tableau(): array {
		return array(
			'jour'                => $this->jour,
			'active'              => $this->active,
			'debut'               => $this->debut,
			'fin'                 => $this->fin,
			'prochaine_ouverture' => $this->prochaine_ouverture,
			'confirmee'           => $this->confirmee,
		);
	}
}
