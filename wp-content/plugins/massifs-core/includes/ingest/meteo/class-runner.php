<?php
/**
 * Orchestration d'une exécution de récupération météo.
 *
 * Toutes les portes sont franchies AVANT le moindre octet réseau : connecteur
 * désactivé, mode manuel, hors période d'exploitation, date déjà couverte ou
 * tentative trop récente — aucun appel sortant n'est émis.
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
 * Exécution planifiée et exécution ciblée.
 */
final class Runner {

	/**
	 * Délai minimal entre deux tentatives pour une même date, en secondes.
	 */
	private const ANTI_RAFALE_SECONDES = 10 * MINUTE_IN_SECONDS;

	/**
	 * Nombre maximal de dates traitées par exécution.
	 *
	 * Deux au plus — aujourd'hui et demain — donc deux appels sortants au plus,
	 * quelle que soit la fréquence de déclenchement du cron.
	 */
	private const DATES_MAX = 2;

	/**
	 * Exécution déclenchée par le planificateur.
	 */
	public static function run_scheduled(): void {
		if ( Settings::is_disabled() ) {
			StateRepository::record_marker( '', 'desactive', 'Connecteur désactivé : aucun appel sortant.' );

			return;
		}

		if ( 'manuel' === Settings::mode() ) {
			return;
		}

		foreach ( self::dates_a_traiter( SourceCalendar::now() ) as $date ) {
			self::run_for( $date, 'cron' );
		}
	}

	/**
	 * Sélectionne les dates réellement à récupérer.
	 *
	 * @param \DateTimeImmutable $maintenant Instant de référence.
	 * @return \DateTimeImmutable[]
	 */
	private static function dates_a_traiter( \DateTimeImmutable $maintenant ): array {
		$retenues = array();

		foreach ( SourceCalendar::pending_dates( $maintenant ) as $date ) {
			$date_ymd = $date->format( 'Ymd' );

			$derniere = StateRepository::last_attempt_for( $date_ymd );

			if ( null !== $derniere && ( time() - $derniere ) < self::ANTI_RAFALE_SECONDES ) {
				continue;
			}

			$retenues[] = $date;
		}

		return array_slice( $retenues, 0, self::DATES_MAX );
	}

	/**
	 * Récupère, valide et enregistre la charge d'une date de validité.
	 *
	 * SEUL ENTONNOIR du module vers le réseau. Les deux portes qui produisent
	 * « zéro octet » — coupe-circuit et période d'exploitation — sont ici, et
	 * pas seulement dans les appelants : un chemin d'appel oublié ne doit pas
	 * pouvoir contourner l'une ou l'autre.
	 *
	 * @param \DateTimeImmutable $date        Date de validité visée.
	 * @param string             $declencheur Origine de l'exécution.
	 * @return true|\WP_Error Vrai si la date est couverte à l'issue de l'appel.
	 */
	public static function run_for( \DateTimeImmutable $date, string $declencheur ) {
		$date_ymd = $date->format( 'Ymd' );

		if ( Settings::is_disabled() ) {
			StateRepository::record_marker( $date_ymd, 'desactive', 'Connecteur désactivé : aucun appel sortant.' );

			return new \WP_Error(
				'massifs_meteo_desactive',
				'Connecteur désactivé : aucun appel sortant n\'est émis.',
				array( 'status' => 409 )
			);
		}

		// Porte opérationnelle, pas une affirmation publique : hors période, on
		// n'appelle pas et on n'alerte pas. Une absence attendue n'est pas un
		// incident, et s'abstenir d'appeler n'affirme rien au visiteur.
		if ( ! SourceCalendar::est_exploitable( $date ) ) {
			StateRepository::record_marker( $date_ymd, 'hors_saison', 'Date hors période d\'exploitation : aucun appel, aucune alerte.' );

			return new \WP_Error(
				'massifs_meteo_hors_saison',
				'Date hors période d\'exploitation du connecteur.',
				array( 'status' => 409 )
			);
		}

		/**
		 * Une tentative de récupération démarre.
		 *
		 * @param string $date_ymd    Date de validité visée.
		 * @param string $declencheur Origine de l'exécution.
		 */
		do_action( 'massifs_meteo_tentative', $date_ymd, $declencheur );

		StateRepository::record_attempt();

		$reponse = Fetcher::fetch( $date );

		if ( is_wp_error( $reponse ) ) {
			return self::echouer( $date_ymd, 'reseau', $reponse );
		}

		switch ( Fetcher::classify( $reponse['code'] ) ) {
			case 'non_publie':
				// État légitime, PAS un échec : aucun compteur, aucune alerte,
				// aucune action `echec`. C'est le seul signal de non-publication
				// dont nous disposions.
				StateRepository::record_issue( $date_ymd, 'non_publie', 'HTTP 404 : donnée non encore publiée.' );

				return new \WP_Error(
					'non_publie',
					sprintf( 'Danger météo du %s non encore publié par la source.', $date->format( 'Y-m-d' ) ),
					array(
						'couche' => 'transport',
						'detail' => 404,
					)
				);

			case 'source_indisponible':
				return self::echouer(
					$date_ymd,
					'source_indisponible',
					self::erreur_http( 'source_indisponible', $reponse['code'], 'Source indisponible' )
				);

			case 'transport':
				return self::echouer(
					$date_ymd,
					'transport',
					self::erreur_http( 'transport_inattendu', $reponse['code'], 'Réponse HTTP inattendue' )
				);
		}

		return self::traiter_succes( $date, $reponse );
	}

	/**
	 * Traite une réponse HTTP 200.
	 *
	 * @param \DateTimeImmutable                                                            $date    Date de validité visée.
	 * @param array{code:int,body:string,headers:array<string,mixed>,url:string,octets:int} $reponse Réponse brute.
	 * @return true|\WP_Error
	 */
	private static function traiter_succes( \DateTimeImmutable $date, array $reponse ) {
		$date_ymd = $date->format( 'Ymd' );

		$instantane = Validator::validate(
			$reponse['body'],
			$reponse['headers'],
			$date,
			array(
				'source_url' => $reponse['url'],
				'mode'       => Settings::mode(),
			)
		);

		if ( is_wp_error( $instantane ) ) {
			StateRepository::record_issue( $date_ymd, 'rejet', (string) $instantane->get_error_message(), $instantane );
			Notifier::alert_rejected( $date_ymd, $instantane, StateRepository::get() );
			self::signaler_echec( $instantane );

			return $instantane;
		}

		$existant = SnapshotRepository::get( $date_ymd );
		$inchange = null !== $existant
			&& isset( $existant['hash'] )
			&& hash_equals( (string) $existant['hash'], (string) $instantane['hash'] );

		if ( ! $inchange ) {
			SnapshotRepository::save( $instantane );
		}

		StateRepository::record_issue(
			$date_ymd,
			'succes',
			$inchange
				? 'Charge identique à celle déjà enregistrée pour cette date : aucune réécriture.'
				: sprintf( '%d octets, confiance %s.', (int) $instantane['octets'], (string) $instantane['confiance'] )
		);

		// Le relevé de fraîcheur est écrit dans les deux branches, et seulement
		// ici : la source a répondu, la charge a traversé les cinq couches, et un
		// instantané valide couvre la date. Une charge inchangée est une lecture
		// réussie, pas une absence de lecture.
		Releve::enregistrer();

		if ( ! $inchange ) {
			/**
			 * Unique couture d'intégration du connecteur météo.
			 *
			 * Le module ne projette rien dans un autre modèle, n'invalide aucun
			 * cache de page et ne touche à aucune option d'une autre chaîne. Il ne
			 * pose lui-même AUCUN transient : la lecture va droit à l'option, il
			 * n'y a donc rien à purger et rien qui puisse rester périmé. C'est à
			 * un consommateur de s'abonner ici s'il met en cache, lui.
			 *
			 * @param array<string,mixed> $instantane Instantané validé et enregistré.
			 */
			do_action( 'massifs_meteo_snapshot_enregistre', $instantane );
		}

		return true;
	}

	/**
	 * Journalise un échec, alerte si le seuil est franchi, et le retourne.
	 *
	 * @param string    $date_ymd Date de validité visée.
	 * @param string    $issue    Issue à journaliser.
	 * @param \WP_Error $erreur   Erreur associée.
	 */
	private static function echouer( string $date_ymd, string $issue, \WP_Error $erreur ): \WP_Error {
		StateRepository::record_issue( $date_ymd, $issue, (string) $erreur->get_error_message(), $erreur );

		$etat = StateRepository::get();

		// La reprise est la récurrence horaire elle-même ; l'alerte n'arrive
		// qu'après plusieurs échecs consécutifs, et une seule fois par date et
		// par type. Un échec isolé n'est pas un incident, une série en est un —
		// et un silence n'est jamais acceptable.
		if ( absint( $etat['echecs_consecutifs'] ) >= Settings::seuil_alerte_echecs() ) {
			Notifier::alert_failures( $date_ymd, $etat );
		}

		self::signaler_echec( $erreur );

		return $erreur;
	}

	/**
	 * Diffuse l'échec avec l'état consolidé.
	 *
	 * @param \WP_Error $erreur Erreur survenue.
	 */
	private static function signaler_echec( \WP_Error $erreur ): void {
		/**
		 * Un échec de récupération est survenu.
		 *
		 * @param \WP_Error           $erreur Erreur survenue.
		 * @param array<string,mixed> $etat   État du connecteur après journalisation.
		 */
		do_action( 'massifs_meteo_echec', $erreur, StateRepository::get() );
	}

	/**
	 * Fabrique une erreur de transport à partir d'un code HTTP.
	 *
	 * @param string $code    Code d'erreur.
	 * @param int    $statut  Code HTTP reçu.
	 * @param string $libelle Libellé court.
	 */
	private static function erreur_http( string $code, int $statut, string $libelle ): \WP_Error {
		return new \WP_Error(
			$code,
			sprintf( '%s : HTTP %d.', $libelle, $statut ),
			array(
				'couche' => 'transport',
				'detail' => $statut,
			)
		);
	}
}
