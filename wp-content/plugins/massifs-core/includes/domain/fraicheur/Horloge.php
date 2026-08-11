<?php
/**
 * Horloge du domaine : jour civil, instants, conversions.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Seule source légitime du temps pour l'extension.
 *
 * Le fuseau est figé en constante de classe, indépendamment du réglage
 * WordPress : `provision.sh` ne pose pas `timezone_string`, l'option vaut donc
 * UTC et `current_time( 'Y-m-d' )` ferait basculer le jour de validité à 2 h du
 * matin heure de Paris — soit le bug du §4.2 livré clé en main. Un
 * administrateur ne doit pas pouvoir décaler le jour de validité depuis
 * Réglages → Général.
 *
 * Aucune fonction de date de WordPress (`current_time`, `date_i18n`, `wp_date`)
 * n'est employée nulle part dans l'extension.
 */
final class Horloge {

	/**
	 * Fuseau du dispositif.
	 */
	public const FUSEAU = 'Europe/Paris';

	/**
	 * Format du jour civil.
	 */
	public const FORMAT_JOUR = 'Y-m-d';

	/**
	 * Format d'échange des instants : ISO 8601 en UTC.
	 */
	public const FORMAT_ISO_UTC = 'Y-m-d\TH:i:s\Z';

	/**
	 * Format de stockage des instants en base (UTC, sans fuseau explicite).
	 */
	public const FORMAT_MYSQL = 'Y-m-d H:i:s';

	/**
	 * Format ISO 8601 avec décalage explicite.
	 */
	public const FORMAT_ISO_DECALAGE = 'Y-m-d\TH:i:sP';

	/**
	 * Fuseau du dispositif.
	 */
	public static function fuseau(): DateTimeZone {
		return new DateTimeZone( self::FUSEAU );
	}

	/**
	 * Fuseau de référence des instants échangés et stockés.
	 */
	public static function utc(): DateTimeZone {
		return new DateTimeZone( 'UTC' );
	}

	/**
	 * Instant courant, en UTC.
	 */
	public static function maintenant(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', self::utc() );
	}

	/**
	 * Instant courant au format ISO 8601 UTC.
	 */
	public static function maintenant_iso_utc(): string {
		return self::vers_iso_utc( self::maintenant() );
	}

	/**
	 * Jour civil courant à Paris.
	 */
	public static function jour_courant(): string {
		return self::maintenant()->setTimezone( self::fuseau() )->format( self::FORMAT_JOUR );
	}

	/**
	 * Jour civil suivant à Paris.
	 */
	public static function jour_suivant(): string {
		return self::decaler_jour( self::jour_courant(), 1 );
	}

	/**
	 * Décale un jour civil d'un nombre de jours signé.
	 *
	 * @param string $jour  Jour civil `YYYY-MM-DD`.
	 * @param int    $jours Décalage en jours.
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé ou inexistant.
	 */
	public static function decaler_jour( string $jour, int $jours ): string {
		$origine = self::jour_vers_debut( $jour );
		$signe   = $jours < 0 ? '-' : '+';

		return $origine->modify( $signe . abs( $jours ) . ' days' )->format( self::FORMAT_JOUR );
	}

	/**
	 * Un jour civil est-il bien formé ET existant ?
	 *
	 * L'égalité aller-retour du formatage rejette les dates impossibles
	 * (`2026-02-31`), que `createFromFormat` reporterait silencieusement.
	 *
	 * @param string $jour Jour civil supposé.
	 */
	public static function jour_est_valide( string $jour ): bool {
		$date = DateTimeImmutable::createFromFormat( '!' . self::FORMAT_JOUR, $jour, self::fuseau() );

		return false !== $date && $date->format( self::FORMAT_JOUR ) === $jour;
	}

	/**
	 * Début du jour civil, à Paris.
	 *
	 * @param string $jour Jour civil `YYYY-MM-DD`.
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé ou inexistant.
	 */
	public static function jour_vers_debut( string $jour ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!' . self::FORMAT_JOUR, $jour, self::fuseau() );

		if ( false === $date || $date->format( self::FORMAT_JOUR ) !== $jour ) {
			throw new InvalidArgumentException( 'Jour de validité attendu au format YYYY-MM-DD.' );
		}

		return $date;
	}

	/**
	 * Normalise le jour demandé par un appelant.
	 *
	 * `null` vaut « aujourd'hui ». Un format invalide lève : une coercition
	 * silencieuse vers aujourd'hui masquerait un bug du §4.2 chez l'appelant.
	 *
	 * @param string|null $jour Jour demandé.
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé ou inexistant.
	 */
	public static function jour_demande( ?string $jour ): string {
		if ( null === $jour ) {
			return self::jour_courant();
		}

		$jour = trim( $jour );

		if ( ! self::jour_est_valide( $jour ) ) {
			throw new InvalidArgumentException( 'Jour de validité attendu au format YYYY-MM-DD.' );
		}

		return $jour;
	}

	/**
	 * Compare deux jours civils : négatif, zéro ou positif.
	 *
	 * @param string $gauche Jour civil `YYYY-MM-DD`.
	 * @param string $droite Jour civil `YYYY-MM-DD`.
	 */
	public static function comparer_jours( string $gauche, string $droite ): int {
		return strcmp( $gauche, $droite );
	}

	/**
	 * Nombre de jours signé entre deux jours civils.
	 *
	 * @param string $depuis Jour civil de départ.
	 * @param string $vers   Jour civil d'arrivée.
	 *
	 * @throws InvalidArgumentException Si un jour est mal formé ou inexistant.
	 */
	public static function ecart_jours( string $depuis, string $vers ): int {
		$ecart = self::jour_vers_debut( $depuis )->diff( self::jour_vers_debut( $vers ) );

		return (int) $ecart->days * ( 1 === $ecart->invert ? -1 : 1 );
	}

	/**
	 * Sérialise un instant en ISO 8601 UTC.
	 *
	 * @param DateTimeImmutable $instant Instant à sérialiser.
	 */
	public static function vers_iso_utc( DateTimeImmutable $instant ): string {
		return $instant->setTimezone( self::utc() )->format( self::FORMAT_ISO_UTC );
	}

	/**
	 * Sérialise un instant au format de stockage (UTC).
	 *
	 * @param DateTimeImmutable $instant Instant à sérialiser.
	 */
	public static function vers_mysql( DateTimeImmutable $instant ): string {
		return $instant->setTimezone( self::utc() )->format( self::FORMAT_MYSQL );
	}

	/**
	 * Convertit une valeur lue en base vers l'ISO 8601 UTC exposé.
	 *
	 * @param string|null $valeur Instant stocké, ou `null`.
	 */
	public static function stockage_vers_iso_utc( ?string $valeur ): ?string {
		if ( null === $valeur || '' === trim( $valeur ) || str_starts_with( $valeur, '0000-00-00' ) ) {
			return null;
		}

		try {
			return self::vers_iso_utc( self::instant_depuis_chaine( $valeur ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Parse strictement un instant fourni par une source externe ou la base.
	 *
	 * Accepte `YYYY-MM-DD` suivi de `T` ou d'une espace, `HH:MM` avec secondes et
	 * fraction facultatives, et un décalage facultatif (`Z`, `+02:00`, `+0200`).
	 * Sans décalage, la valeur est interprétée en UTC — c'est le format de
	 * stockage. Toute autre forme est refusée : ne jamais faire confiance à une
	 * charge utile ingérée.
	 *
	 * @param string $valeur Instant à parser.
	 *
	 * @throws InvalidArgumentException Si la chaîne n'est pas un instant valide.
	 */
	public static function instant_depuis_chaine( string $valeur ): DateTimeImmutable {
		$valeur = trim( $valeur );
		$motif  = '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?(?:\.\d{1,9})?(Z|[+-]\d{2}:?\d{2})?$/';

		if ( 1 !== preg_match( $motif, $valeur, $captures ) ) {
			throw new InvalidArgumentException( 'Instant attendu au format ISO 8601.' );
		}

		$secondes = isset( $captures[3] ) && '' !== $captures[3] ? $captures[3] : '00';
		$decalage = $captures[4] ?? '';

		if ( '' === $decalage || 'Z' === $decalage ) {
			$decalage = '+00:00';
		} elseif ( 5 === strlen( $decalage ) ) {
			$decalage = substr( $decalage, 0, 3 ) . ':' . substr( $decalage, 3, 2 );
		}

		$canonique = $captures[1] . 'T' . $captures[2] . ':' . $secondes . $decalage;
		$instant   = DateTimeImmutable::createFromFormat( self::FORMAT_ISO_DECALAGE, $canonique );

		// L'égalité aller-retour rejette les dates impossibles (`2026-02-31`).
		if ( false === $instant || $instant->format( self::FORMAT_ISO_DECALAGE ) !== $canonique ) {
			throw new InvalidArgumentException( 'Instant attendu au format ISO 8601.' );
		}

		return $instant->setTimezone( self::utc() );
	}
}
