<?php
/**
 * Façade interne du connecteur météo.
 *
 * SEULE classe que le reste de l'extension a le droit de nommer. Tout le reste
 * (Fetcher, Validator, Runner, dépôts, Vocabulaire) est de l'implémentation et
 * peut changer. Le THÈME, lui, n'en nomme aucune : sa seule porte est
 * `massifs_meteo_du_jour()`.
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
 * Point d'entrée unique pour les consommateurs internes du connecteur.
 */
final class Connector {

	/*
	 * IL N'EXISTE VOLONTAIREMENT AUCUN ACCESSEUR « DERNIER INSTANTANÉ ».
	 *
	 * Un `latest()` serait immédiatement employé pour afficher un indicateur, et
	 * le jour où la récupération échoue il servirait celui de la veille comme
	 * s'il était courant. Le §4.2 du brief l'interdit sans exception.
	 *
	 * D'où la règle : toute lecture EXIGE une date, et l'absence de réponse pour
	 * cette date est une réponse — `null`.
	 *
	 * Cette absence est VÉRIFIÉE par la recette, elle n'est pas seulement écrite
	 * ici.
	 */

	/**
	 * Instantané couvrant une date de validité.
	 *
	 * @param string $date_iso Date de validité `Y-m-d`, obligatoire.
	 * @return array<string,mixed>|null
	 */
	public static function snapshot_for( string $date_iso ): ?array {
		$date = SourceCalendar::from_iso( $date_iso );

		return null === $date ? null : SnapshotRepository::get( $date->format( 'Ymd' ) );
	}

	/**
	 * Un instantané couvre-t-il cette date de validité ?
	 *
	 * @param string $date_iso Date de validité `Y-m-d`, obligatoire.
	 */
	public static function has_snapshot_for( string $date_iso ): bool {
		return null !== self::snapshot_for( $date_iso );
	}

	/**
	 * État opérationnel du connecteur, pour l'exploitation.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		return StateRepository::get();
	}

	/**
	 * Attribution de la source.
	 *
	 * @return array{texte:string,lien_licence:string,lien_source:string}
	 */
	public static function attribution(): array {
		return Settings::attribution();
	}

	/**
	 * Mode de fonctionnement courant : `automatique` ou `manuel`.
	 */
	public static function mode(): string {
		return Settings::mode();
	}

	/**
	 * Déclenche une récupération immédiate pour une date.
	 *
	 * Ceinture d'autorisation : dans un contexte d'utilisateur connecté,
	 * l'appelant doit porter `manage_options`. Les contextes cron et WP-CLI n'ont
	 * pas d'utilisateur et restent autorisés.
	 *
	 * Ce garde-fou est une ceinture, pas la bretelle : ce module n'expose aucune
	 * route, aucun formulaire et aucun écran, donc aucun nonce n'a de sens ici.
	 * Le jour où un appelant HTTP existerait, c'est LUI qui devrait vérifier la
	 * capacité ET le nonce avant d'arriver jusqu'ici.
	 *
	 * @param string $date_iso Date de validité `Y-m-d`, obligatoire.
	 * @return true|\WP_Error
	 */
	public static function run_now( string $date_iso ) {
		if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'massifs_meteo_droits_insuffisants',
				'Droits insuffisants pour déclencher une récupération.',
				array( 'status' => 403 )
			);
		}

		if ( Settings::is_disabled() ) {
			return new \WP_Error(
				'massifs_meteo_desactive',
				'Connecteur désactivé : aucun appel sortant n\'est émis.',
				array( 'status' => 409 )
			);
		}

		$date = SourceCalendar::from_iso( $date_iso );

		if ( null === $date ) {
			return new \WP_Error(
				'massifs_meteo_date_invalide',
				sprintf( 'Date « %s » invalide : format Y-m-d attendu.', $date_iso ),
				array( 'status' => 400 )
			);
		}

		// Borne dure AVANT tout octet réseau : le connecteur ne récupère jamais
		// une date qu'il ne pourrait pas présenter honnêtement.
		if ( ! SourceCalendar::is_within_range( $date ) ) {
			return new \WP_Error(
				'massifs_meteo_date_hors_plage',
				'Seuls aujourd\'hui et demain peuvent être récupérés.',
				array( 'status' => 400 )
			);
		}

		return Runner::run_for( $date, 'manuel' );
	}
}
