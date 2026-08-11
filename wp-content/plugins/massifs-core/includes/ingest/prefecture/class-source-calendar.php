<?php
/**
 * Calendrier de la source : saison, dates cibles, conversions de dates.
 *
 * Toutes les dates du connecteur sont des dates de VALIDITÉ, exprimées dans le
 * fuseau du dispositif (Europe/Paris). Elles ne sont jamais dérivées de
 * l'instant de récupération.
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
 * Saison du dispositif et sélection des dates à récupérer.
 */
final class SourceCalendar {

	/**
	 * Premier jour de la saison, format `md`.
	 */
	private const SAISON_DEBUT = '0601';

	/**
	 * Dernier jour de la saison, inclus, format `md`.
	 */
	private const SAISON_FIN = '0930';

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
	 * La date cible est-elle dans la saison du dispositif ?
	 *
	 * Règle évaluée sur la DATE CIBLE, jamais sur « maintenant » : le 31 mai à
	 * 18 h, la cible est le 1er juin, donc en saison — ce qui correspond à
	 * l'observation (`20260601` publié la veille). Le 30 septembre à 18 h, la
	 * cible est le 1er octobre, donc hors saison : aucun appel, aucune alerte.
	 *
	 * @param \DateTimeImmutable $date Date de validité visée.
	 */
	public static function is_in_season( \DateTimeImmutable $date ): bool {
		$jour = $date->format( 'md' );

		$en_saison = ( $jour >= self::SAISON_DEBUT && $jour <= self::SAISON_FIN );

		/**
		 * Filtre l'appartenance d'une date de validité à la saison.
		 *
		 * @param bool   $en_saison Résultat calculé.
		 * @param string $date_ymd  Date de validité au format `Ymd`.
		 */
		return (bool) apply_filters( 'massifs_prefecture_est_en_saison', $en_saison, $date->format( 'Ymd' ) );
	}

	/**
	 * Dates candidates à la récupération pour un instant donné.
	 *
	 * - aujourd'hui, tant qu'aucun instantané ne le couvre : rattrapage
	 *   délibéré du matin. Un site éteint toute la nuit doit encore pouvoir
	 *   récupérer le fichier du jour, qui existe et reste servi ;
	 * - demain, dès l'heure de début de fenêtre : c'est la publication du soir.
	 *
	 * Les deux peuvent coexister : à 18 h le jour J on obtient J+1 alors que J
	 * est toujours le statut courant.
	 *
	 * @param \DateTimeImmutable $now Instant de référence.
	 * @return \DateTimeImmutable[]
	 */
	public static function pending_dates( \DateTimeImmutable $now ): array {
		$candidates = array();

		$aujourdhui = self::today( $now );

		if ( ! SnapshotRepository::has( $aujourdhui->format( 'Ymd' ) ) ) {
			$candidates[] = $aujourdhui;
		}

		$fenetre = Settings::fenetre();

		if ( (int) $now->format( 'G' ) >= $fenetre['debut'] ) {
			$candidates[] = self::tomorrow( $now );
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
		$cible = $date->format( 'Ymd' );

		return in_array(
			$cible,
			array( self::today( $now )->format( 'Ymd' ), self::tomorrow( $now )->format( 'Ymd' ) ),
			true
		);
	}
}
