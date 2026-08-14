<?php
/**
 * Calendrier du connecteur météo : dates cibles, conversions, porte saisonnière.
 *
 * Toutes les dates du connecteur sont des dates de VALIDITÉ, exprimées dans le
 * fuseau du dispositif (Europe/Paris). Elles ne sont jamais dérivées de
 * l'instant de récupération.
 *
 * AUCUNE FENÊTRE DE PUBLICATION N'EST DÉFINIE ICI, ET C'EST DÉLIBÉRÉ. L'heure à
 * laquelle « Météo des forêts » est publié n'est établie nulle part ; en
 * inventer une ferait déclencher des alertes sur une heure fausse, c'est-à-dire
 * apprendrait au gestionnaire à les ignorer. La récurrence horaire tient lieu de
 * politique de reprise.
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
 * Sélection des dates à récupérer et porte saisonnière opérationnelle.
 */
final class SourceCalendar {

	/**
	 * Instant courant dans le fuseau du dispositif.
	 */
	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', Settings::timezone() );
	}

	/**
	 * Date du jour, à minuit, dans le fuseau du dispositif.
	 *
	 * @param \DateTimeImmutable|null $now Instant de référence.
	 */
	public static function today( ?\DateTimeImmutable $now = null ): \DateTimeImmutable {
		$reference = $now instanceof \DateTimeImmutable ? $now : self::now();

		return $reference->setTime( 0, 0, 0 );
	}

	/**
	 * Date du lendemain, à minuit, dans le fuseau du dispositif.
	 *
	 * @param \DateTimeImmutable|null $now Instant de référence.
	 */
	public static function tomorrow( ?\DateTimeImmutable $now = null ): \DateTimeImmutable {
		return self::today( $now )->modify( '+1 day' );
	}

	/**
	 * Construit une date depuis un `Ymd`.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	public static function from_ymd( string $date_ymd ): ?\DateTimeImmutable {
		if ( 1 !== preg_match( '/^\d{8}$/', $date_ymd ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Ymd', $date_ymd, Settings::timezone() );

		if ( false === $date || $date->format( 'Ymd' ) !== $date_ymd ) {
			return null;
		}

		return $date;
	}

	/**
	 * Construit une date depuis un `Y-m-d`.
	 *
	 * @param string $date_iso Date au format `Y-m-d`.
	 */
	public static function from_iso( string $date_iso ): ?\DateTimeImmutable {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_iso ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date_iso, Settings::timezone() );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $date_iso ) {
			return null;
		}

		return $date;
	}

	/**
	 * La date est-elle dans la période où le module interroge la source ?
	 *
	 * PORTE PUREMENT OPÉRATIONNELLE. Elle n'affirme RIEN au visiteur : nous ne
	 * savons pas si Météo-France publie hors du dispositif préfectoral, et
	 * l'affirmer serait inventer un fait de domaine sur une source tierce. Hors
	 * période, le module s'abstient d'appeler et d'alerter, et la lecture rend
	 * un simple « indisponible ». S'abstenir n'affirme rien ; c'est asymétrique
	 * et sans risque, la seule conséquence possible étant le repli honnête.
	 *
	 * Si `massifs_saison()` est absente, la porte NE S'APPLIQUE PAS et le module
	 * procède : un module frère manquant ne doit pas éteindre celui-ci.
	 *
	 * @param \DateTimeImmutable $date Date de validité visée.
	 */
	public static function est_exploitable( \DateTimeImmutable $date ): bool {
		$exploitable = true;

		if ( function_exists( 'massifs_saison' ) ) {
			try {
				$saison      = massifs_saison( $date->format( 'Y-m-d' ) );
				$exploitable = is_array( $saison ) && true === ( $saison['active'] ?? null );
			} catch ( \Throwable $e ) {
				// Le domaine n'a pas su répondre : on procède plutôt que de
				// s'éteindre sur une erreur d'un module frère.
				$exploitable = true;
			}
		}

		/**
		 * Filtre l'exploitabilité opérationnelle d'une date de validité.
		 *
		 * @param bool   $exploitable Résultat calculé.
		 * @param string $date_ymd    Date de validité au format `Ymd`.
		 */
		return (bool) apply_filters( 'massifs_meteo_saison_operationnelle', $exploitable, $date->format( 'Ymd' ) );
	}

	/**
	 * Dates candidates à la récupération pour un instant donné.
	 *
	 * Aujourd'hui tant qu'aucun instantané ne le couvre — rattrapage du matin
	 * pour un site éteint la nuit — et demain, sans condition d'heure, faute de
	 * fenêtre de publication connue. Les deux peuvent coexister.
	 *
	 * @param \DateTimeImmutable $now Instant de référence.
	 * @return \DateTimeImmutable[]
	 */
	public static function pending_dates( \DateTimeImmutable $now ): array {
		$candidates = array();

		foreach ( array( self::today( $now ), self::tomorrow( $now ) ) as $date ) {
			if ( ! SnapshotRepository::has( $date->format( 'Ymd' ) ) ) {
				$candidates[] = $date;
			}
		}

		return $candidates;
	}

	/**
	 * La date cible est-elle aujourd'hui ou demain ?
	 *
	 * Borne dure : le connecteur ne récupère jamais une date qu'il ne pourrait
	 * pas présenter honnêtement.
	 *
	 * @param \DateTimeImmutable      $date Date de validité visée.
	 * @param \DateTimeImmutable|null $now  Instant de référence.
	 */
	public static function is_within_range( \DateTimeImmutable $date, ?\DateTimeImmutable $now = null ): bool {
		return in_array(
			$date->format( 'Ymd' ),
			array( self::today( $now )->format( 'Ymd' ), self::tomorrow( $now )->format( 'Ymd' ) ),
			true
		);
	}
}
