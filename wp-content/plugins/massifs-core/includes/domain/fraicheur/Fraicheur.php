<?php
/**
 * Règle de fraîcheur du §4.5 : seuil de 24 h sur le dernier relevé réussi.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;

/**
 * Âge de la donnée affichée.
 *
 * DEUX RÈGLES DISJOINTES, JAMAIS FUSIONNÉES :
 *
 * - §4.2, absolue et sans seuil : un statut n'est courant que pour son propre
 *   jour de validité. Elle vit dans le domaine « statuts » et n'est pas ici.
 * - §4.5, ci-dessous : seuil de 24 h sur l'INSTANT du dernier relevé réussi.
 *   Elle déclenche UNIQUEMENT une bannière.
 *
 * Les fusionner produirait soit des faux positifs, soit un trou de sécurité.
 * `perimee` ne peut donc jamais masquer une donnée valide.
 */
final class Fraicheur {

	/**
	 * Seuil de péremption, en secondes.
	 */
	public const SEUIL_SECONDES = 86400;

	/**
	 * Construit le service.
	 *
	 * @param RegistreReleves $registre   Registre des relevés réussis.
	 * @param Saison          $saison     Calendrier du dispositif.
	 * @param string          $source_cle Source dont on mesure la fraîcheur.
	 */
	public function __construct(
		private readonly RegistreReleves $registre,
		private readonly Saison $saison,
		private readonly string $source_cle = RegistreReleves::SOURCE_PREFECTURE
	) {}

	/**
	 * Évalue la fraîcheur pour un jour donné.
	 *
	 * @param string $jour Jour de validité `YYYY-MM-DD`.
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé.
	 */
	public function evaluer( string $jour ): ResultatFraicheur {
		$maintenant = Horloge::maintenant();
		$releve     = $this->registre->dernier_releve( $this->source_cle );
		$actif      = $this->saison->est_active( $jour );
		$age        = null;

		if ( null !== $releve ) {
			try {
				$age = max( 0, $maintenant->getTimestamp() - Horloge::instant_depuis_chaine( $releve )->getTimestamp() );
			} catch ( InvalidArgumentException ) {
				$releve = null;
			}
		}

		// Hors période d'activité du dispositif, l'absence de relevé est normale :
		// aucune bannière de péremption ne doit être levée.
		$perimee = $actif && ( null === $releve || $age > self::SEUIL_SECONDES );

		return new ResultatFraicheur(
			$jour,
			$releve,
			$this->source_cle,
			$age,
			self::SEUIL_SECONDES,
			$perimee,
			$this->registre->publication( $jour ),
			$actif,
			Horloge::vers_iso_utc( $maintenant )
		);
	}
}
