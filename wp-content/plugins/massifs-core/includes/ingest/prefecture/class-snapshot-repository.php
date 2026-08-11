<?php
/**
 * Stockage des instantanés de la source, indexés par date de VALIDITÉ.
 *
 * Une carte de dates, pas un instantané unique : à 18 h le jour J on obtient
 * J+1 alors que J est toujours le statut courant. Les deux doivent coexister.
 *
 * L'unité de persistance est UNE option, écrite par un seul `update_option`
 * après validation complète : aucune écriture partielle n'est représentable.
 *
 * @package Massifs\Ingest\Prefecture
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Prefecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lecture et écriture des instantanés.
 */
final class SnapshotRepository {

	/**
	 * Option des instantanés. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_prefecture_snapshots';

	/**
	 * Version de structure d'un enregistrement.
	 */
	public const SCHEMA = 1;

	/**
	 * Tous les instantanés, indexés par `Ymd`.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$stocke = get_option( self::OPTION, null );

		if ( ! is_array( $stocke ) ) {
			return array();
		}

		$propres = array();

		foreach ( $stocke as $date => $enregistrement ) {
			$date = (string) $date;

			if ( 1 === preg_match( '/^\d{8}$/', $date ) && is_array( $enregistrement ) ) {
				$propres[ $date ] = $enregistrement;
			}
		}

		return $propres;
	}

	/**
	 * Instantané pour une date de validité.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $date_ymd ): ?array {
		$tous = self::all();

		return isset( $tous[ $date_ymd ] ) ? $tous[ $date_ymd ] : null;
	}

	/**
	 * Un instantané couvre-t-il cette date de validité ?
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	public static function has( string $date_ymd ): bool {
		return null !== self::get( $date_ymd );
	}

	/**
	 * Date de validité d'un instantané portant ce hachage, s'il existe.
	 *
	 * @param string $hash Hachage sha256 du corps brut.
	 * @return string|null Date au format `Ymd`.
	 */
	public static function find_by_hash( string $hash ): ?string {
		if ( '' === $hash ) {
			return null;
		}

		foreach ( self::all() as $date => $enregistrement ) {
			if ( isset( $enregistrement['hash'] ) && hash_equals( (string) $enregistrement['hash'], $hash ) ) {
				return (string) $date;
			}
		}

		return null;
	}

	/**
	 * Écrit un instantané et élague les plus anciens.
	 *
	 * @param array<string,mixed> $enregistrement Instantané validé.
	 * @return bool Vrai si l'écriture a eu lieu.
	 */
	public static function save( array $enregistrement ): bool {
		$date_iso = (string) ( $enregistrement['date_validite'] ?? '' );
		$date     = SourceCalendar::from_iso( $date_iso );

		if ( null === $date ) {
			return false;
		}

		$tous = self::all();

		$tous[ $date->format( 'Ymd' ) ] = $enregistrement;

		return update_option( self::OPTION, self::elaguer( $tous ), false );
	}

	/**
	 * Élague les instantanés hors fenêtre de conservation.
	 *
	 * Aujourd'hui et demain ne sont jamais élagués, quelle que soit la
	 * profondeur configurée : ce sont les seules dates que le site peut
	 * présenter.
	 *
	 * @param array<string,array<string,mixed>> $tous Instantanés courants.
	 * @return array<string,array<string,mixed>>
	 */
	private static function elaguer( array $tous ): array {
		$jours    = Settings::conserver_jours();
		$proteges = array(
			SourceCalendar::today()->format( 'Ymd' ),
			SourceCalendar::tomorrow()->format( 'Ymd' ),
		);

		$limite = SourceCalendar::today()->modify( '-' . $jours . ' days' )->format( 'Ymd' );

		foreach ( array_keys( $tous ) as $date ) {
			$date = (string) $date;

			if ( ! in_array( $date, $proteges, true ) && $date < $limite ) {
				unset( $tous[ $date ] );
			}
		}

		// Plafond dur : une source qui servirait des dates lointaines ne doit
		// pas faire enfler l'option indéfiniment.
		$plafond = $jours + count( $proteges );

		if ( count( $tous ) > $plafond ) {
			$dates = array_keys( $tous );
			rsort( $dates, SORT_STRING );

			$conserves = array_unique( array_merge( $proteges, array_slice( $dates, 0, $plafond ) ) );

			foreach ( array_keys( $tous ) as $date ) {
				if ( ! in_array( (string) $date, $conserves, true ) ) {
					unset( $tous[ $date ] );
				}
			}
		}

		ksort( $tous, SORT_STRING );

		return $tous;
	}
}
