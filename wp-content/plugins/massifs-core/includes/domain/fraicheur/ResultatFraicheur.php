<?php
/**
 * Objet valeur : fraîcheur des données à une date.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Réponse de `Fraicheur::evaluer()`.
 *
 * `perimee` est une BANNIÈRE, jamais un filtre : elle s'ajoute aux statuts
 * affichés et n'en masque aucun.
 */
final class ResultatFraicheur {

	/**
	 * Construit le résultat.
	 *
	 * @param string      $jour_validite         Jour demandé.
	 * @param string|null $dernier_releve_le     Instant du dernier relevé réussi, ISO 8601 UTC.
	 * @param string      $dernier_releve_source Clé de la source relevée.
	 * @param int|null    $age_secondes          Âge du dernier relevé, `null` si aucun relevé.
	 * @param int         $seuil_secondes        Seuil de péremption.
	 * @param bool        $perimee               La bannière de péremption doit-elle être affichée ?
	 * @param string|null $publie_prefecture_le  Publication préfectorale connue pour ce jour.
	 * @param bool        $dispositif_actif      Le dispositif est-il actif ce jour-là ?
	 * @param string      $evalue_le             Instant d'évaluation, ISO 8601 UTC.
	 */
	public function __construct(
		public readonly string $jour_validite,
		public readonly ?string $dernier_releve_le,
		public readonly string $dernier_releve_source,
		public readonly ?int $age_secondes,
		public readonly int $seuil_secondes,
		public readonly bool $perimee,
		public readonly ?string $publie_prefecture_le,
		public readonly bool $dispositif_actif,
		public readonly string $evalue_le
	) {}

	/**
	 * Forme exposée aux consommateurs.
	 *
	 * @return array<string, mixed>
	 */
	public function en_tableau(): array {
		return array(
			'jour_validite'         => $this->jour_validite,
			'dernier_releve_le'     => $this->dernier_releve_le,
			'dernier_releve_source' => $this->dernier_releve_source,
			'age_secondes'          => $this->age_secondes,
			'seuil_secondes'        => $this->seuil_secondes,
			'perimee'               => $this->perimee,
			'publie_prefecture_le'  => $this->publie_prefecture_le,
			'dispositif_actif'      => $this->dispositif_actif,
			'evalue_le'             => $this->evalue_le,
		);
	}
}
