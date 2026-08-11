<?php
/**
 * Façade publique du connecteur préfecture.
 *
 * SEULE classe que le reste de l'extension a le droit de nommer. Tout le reste
 * (Fetcher, Validator, Runner, dépôts) est de l'implémentation et peut changer.
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
 * Point d'entrée unique pour les consommateurs du connecteur.
 */
final class Connector {

	/*
	 * IL N'EXISTE VOLONTAIREMENT AUCUN ACCESSEUR « DERNIER INSTANTANÉ ».
	 *
	 * Un `latest()` serait immédiatement utilisé pour afficher un statut, et le
	 * jour où la récupération échoue il servirait celui de la veille comme s'il
	 * était courant. Le §4.2 du brief l'interdit : sans donnée valide pour le
	 * jour demandé, le site doit dire « information non disponible ».
	 *
	 * D'où la règle : toute lecture EXIGE une date, et l'absence de réponse pour
	 * cette date est une réponse — `null`.
	 */

	/**
	 * Instantané couvrant une date de validité.
	 *
	 * @param string $date_iso Date de validité au format `Y-m-d`, obligatoire.
	 * @return array<string,mixed>|null
	 */
	public static function snapshot_for( string $date_iso ): ?array {
		$date = SourceCalendar::from_iso( $date_iso );

		return null === $date ? null : SnapshotRepository::get( $date->format( 'Ymd' ) );
	}

	/**
	 * Un instantané couvre-t-il cette date de validité ?
	 *
	 * @param string $date_iso Date de validité au format `Y-m-d`, obligatoire.
	 */
	public static function has_snapshot_for( string $date_iso ): bool {
		return null !== self::snapshot_for( $date_iso );
	}

	/**
	 * État opérationnel du connecteur, pour l'écran d'administration.
	 *
	 * @return array<string,mixed>
	 */
	public static function state(): array {
		return StateRepository::get();
	}

	/**
	 * Attribution de la source, à afficher partout où la donnée est présentée.
	 *
	 * @return array{texte:string,url_carte:string,url_bulletin:string}
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
	 * Valide un jeu de massifs saisi hors flux automatique.
	 *
	 * Le portail de saisie manuelle passe EXACTEMENT par la même validation que
	 * le cron : référentiel complet dans les deux sens, listes blanches, plage
	 * de dates. Une saisie humaine n'est pas plus digne de confiance qu'un flux.
	 *
	 * @param array<string|int,array<int,int>> $massifs  Couples [niveau, procédure] indexés par identifiant source.
	 * @param string                           $date_iso Date de validité au format `Y-m-d`.
	 * @return true|\WP_Error
	 */
	public static function validate_payload( array $massifs, string $date_iso ) {
		$date = SourceCalendar::from_iso( $date_iso );

		if ( null === $date ) {
			return self::erreur_date( $date_iso );
		}

		$corps = wp_json_encode( array( 'massifs' => $massifs ) );

		if ( ! is_string( $corps ) ) {
			return new \WP_Error(
				'massifs_prefecture_payload_illisible',
				'Le jeu de massifs fourni n\'est pas encodable en JSON.',
				array( 'status' => 400 )
			);
		}

		$verdict = Validator::validate( $corps, array(), $date, array( 'mode' => 'manuel' ) );

		return is_wp_error( $verdict ) ? $verdict : true;
	}

	/**
	 * Déclenche une récupération immédiate pour une date.
	 *
	 * Auto-refus : dans un contexte d'utilisateur connecté, l'appelant doit
	 * porter `manage_options`. Les contextes cron et WP-CLI n'ont pas
	 * d'utilisateur et restent autorisés.
	 *
	 * Ce garde-fou est une ceinture, pas la bretelle : un appelant HTTP doit de
	 * toute façon vérifier la capacité ET le nonce de son côté avant d'arriver
	 * ici.
	 *
	 * @param string $date_iso Date de validité au format `Y-m-d`, obligatoire.
	 * @return true|\WP_Error
	 */
	public static function run_now( string $date_iso ) {
		if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'massifs_prefecture_droits_insuffisants',
				'Droits insuffisants pour déclencher une récupération.',
				array( 'status' => 403 )
			);
		}

		if ( Settings::is_disabled() ) {
			return new \WP_Error(
				'massifs_prefecture_desactive',
				'Connecteur désactivé : aucun appel sortant n\'est émis.',
				array( 'status' => 409 )
			);
		}

		$date = SourceCalendar::from_iso( $date_iso );

		if ( null === $date ) {
			return self::erreur_date( $date_iso );
		}

		// Borne dure avant tout octet réseau : le connecteur ne récupère jamais
		// une date qu'il ne pourrait pas présenter honnêtement.
		if ( ! SourceCalendar::is_within_range( $date ) ) {
			return new \WP_Error(
				'massifs_prefecture_date_hors_plage',
				'Seuls aujourd\'hui et demain peuvent être récupérés.',
				array( 'status' => 400 )
			);
		}

		return Runner::run_for( $date, 'manuel' );
	}

	/**
	 * Erreur de date mal formée.
	 *
	 * @param string $date_iso Valeur reçue.
	 */
	private static function erreur_date( string $date_iso ): \WP_Error {
		return new \WP_Error(
			'massifs_prefecture_date_invalide',
			sprintf( 'Date « %s » invalide : format Y-m-d attendu.', $date_iso ),
			array( 'status' => 400 )
		);
	}
}
