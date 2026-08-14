<?php
/**
 * Stockage des instantanés météo, indexés par date de VALIDITÉ.
 *
 * Une carte de dates, pas un instantané unique : le lendemain peut être obtenu
 * alors que le jour courant est toujours celui qui s'affiche. Les deux doivent
 * coexister.
 *
 * IL N'EXISTE AUCUN ACCESSEUR « DERNIER INSTANTANÉ », ici pas plus qu'ailleurs.
 * Toute lecture exige une date, et l'absence d'instantané pour cette date EST la
 * réponse. C'est ce qui rend structurellement impossible de servir la valeur de
 * la veille comme si elle était courante.
 *
 * L'unité de persistance est UNE option, écrite par un seul `update_option`
 * après validation complète : aucune écriture partielle n'est représentable.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Meteo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lecture et écriture des instantanés météo.
 */
final class SnapshotRepository {

	/**
	 * Option des instantanés. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_meteo_snapshots';

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

		if ( ! isset( $tous[ $date_ymd ] ) ) {
			return null;
		}

		$enregistrement = $tous[ $date_ymd ];

		// Garde de cohérence à la LECTURE, et non seulement à l'écriture : un
		// enregistrement dont la date de validité ne serait pas celle sous
		// laquelle il est rangé est écarté, jamais recalé. Une option est
		// modifiable depuis l'administration ; c'est le dernier verrou entre une
		// donnée déplacée et un affichage faux.
		$validite = isset( $enregistrement['date_validite'] ) && is_string( $enregistrement['date_validite'] )
			? SourceCalendar::from_iso( $enregistrement['date_validite'] )
			: null;

		if ( null === $validite || $validite->format( 'Ymd' ) !== $date_ymd ) {
			return null;
		}

		return $enregistrement;
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

		// Plafond dur : une source qui servirait des dates lointaines ne doit pas
		// faire enfler l'option indéfiniment.
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
