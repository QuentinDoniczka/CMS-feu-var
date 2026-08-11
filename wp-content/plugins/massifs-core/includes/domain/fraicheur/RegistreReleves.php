<?php
/**
 * Registre des relevés réussis, par source.
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
 * Mémoire de la dernière récupération RÉUSSIE de chaque source.
 *
 * Une option autoloadée, pas une table : la donnée est minuscule, lue à chaque
 * page et écrite une fois par jour.
 *
 * Un échec de récupération n'écrit RIEN ici : sinon la fraîcheur mentirait, ce
 * que le §4.5 du brief interdit.
 */
final class RegistreReleves {

	/**
	 * Option de stockage.
	 */
	public const OPTION = 'massifs_dernier_releve';

	/**
	 * Source de référence des statuts.
	 */
	public const SOURCE_PREFECTURE = 'prefecture';

	/**
	 * Forme admise d'une clé de source.
	 */
	private const MOTIF_CLE = '/^[a-z0-9_-]{1,32}$/';

	/**
	 * Normalise une clé de source.
	 *
	 * @param string $cle Clé brute.
	 */
	public static function normaliser_cle( string $cle ): string {
		return strtolower( trim( $cle ) );
	}

	/**
	 * La clé de source a-t-elle une forme admissible ?
	 *
	 * @param string $cle Clé normalisée.
	 */
	public static function cle_est_valide( string $cle ): bool {
		return 1 === preg_match( self::MOTIF_CLE, $cle );
	}

	/**
	 * Instant du dernier relevé réussi d'une source, en ISO 8601 UTC.
	 *
	 * @param string $source_cle Clé de source normalisée.
	 */
	public function dernier_releve( string $source_cle ): ?string {
		$entree = $this->entree( $source_cle );

		return isset( $entree['instant'] ) ? (string) $entree['instant'] : null;
	}

	/**
	 * Enregistre un relevé réussi.
	 *
	 * @param string $source_cle      Clé de source normalisée.
	 * @param string $instant_iso_utc Instant du relevé, ISO 8601 UTC.
	 */
	public function enregistrer( string $source_cle, string $instant_iso_utc ): void {
		$registre = $this->tout();
		$entree   = $this->entree( $source_cle );

		$entree['instant']       = $instant_iso_utc;
		$registre[ $source_cle ] = $entree;

		update_option( self::OPTION, $registre, true );
	}

	/**
	 * Instant de publication préfectorale connu pour un jour de validité.
	 *
	 * @param string $jour Jour de validité `YYYY-MM-DD`.
	 */
	public function publication( string $jour ): ?string {
		$entree = $this->entree( self::SOURCE_PREFECTURE );

		if ( ! isset( $entree['publie_jour'], $entree['publie_le'] ) || (string) $entree['publie_jour'] !== $jour ) {
			return null;
		}

		return (string) $entree['publie_le'];
	}

	/**
	 * Note l'instant de publication préfectorale porté par un statut enregistré.
	 *
	 * Branché sur `massifs_statut_enregistre`. Seule la publication la plus
	 * récente est conservée, et seulement pour un jour de validité à la fois :
	 * l'option est autoloadée, elle ne doit pas croître avec le temps.
	 *
	 * @param int                  $id     Identifiant de la ligne insérée, non exploité ici.
	 * @param array<string, mixed> $statut Statut normalisé tel qu'enregistré.
	 */
	public static function noter_publication( int $id, array $statut ): void {
		$publie = isset( $statut['publie_prefecture_le'] ) && is_string( $statut['publie_prefecture_le'] )
			? $statut['publie_prefecture_le']
			: '';
		$jour   = isset( $statut['jour_validite'] ) && is_string( $statut['jour_validite'] )
			? $statut['jour_validite']
			: '';

		if ( '' === $publie || '' === $jour ) {
			return;
		}

		$registre = new self();
		$connu    = $registre->publication( $jour );

		if ( null !== $connu && strcmp( $connu, $publie ) >= 0 ) {
			return;
		}

		$tout   = $registre->tout();
		$entree = $registre->entree( self::SOURCE_PREFECTURE );

		$entree['publie_jour'] = $jour;
		$entree['publie_le']   = $publie;

		$tout[ self::SOURCE_PREFECTURE ] = $entree;

		update_option( self::OPTION, $tout, true );
	}

	/**
	 * Registre complet.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function tout(): array {
		$registre = get_option( self::OPTION, array() );

		return is_array( $registre ) ? $registre : array();
	}

	/**
	 * Entrée d'une source, assainie.
	 *
	 * @param string $source_cle Clé de source normalisée.
	 *
	 * @return array<string, string>
	 */
	private function entree( string $source_cle ): array {
		$registre = $this->tout();
		$entree   = $registre[ $source_cle ] ?? array();

		if ( ! is_array( $entree ) ) {
			return array();
		}

		$assainie = array();

		foreach ( array( 'instant', 'publie_le' ) as $champ ) {
			if ( ! isset( $entree[ $champ ] ) || ! is_string( $entree[ $champ ] ) ) {
				continue;
			}

			try {
				$assainie[ $champ ] = Horloge::vers_iso_utc( Horloge::instant_depuis_chaine( $entree[ $champ ] ) );
			} catch ( InvalidArgumentException ) {
				continue;
			}
		}

		if ( isset( $entree['publie_jour'] ) && is_string( $entree['publie_jour'] ) && Horloge::jour_est_valide( $entree['publie_jour'] ) ) {
			$assainie['publie_jour'] = $entree['publie_jour'];
		}

		return $assainie;
	}
}
