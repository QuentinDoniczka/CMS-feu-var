<?php
/**
 * Orchestration d'une exécution de récupération.
 *
 * Toutes les portes d'entrée sont franchies AVANT le moindre octet réseau :
 * hors saison, mode manuel, connecteur désactivé, instantané déjà obtenu ou
 * tentative trop récente, aucun appel sortant n'est émis.
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
 * Exécution planifiée et exécution ciblée.
 */
final class Runner {

	/**
	 * Délai minimal entre deux tentatives pour une même date, en secondes.
	 */
	private const ANTI_RAFALE_SECONDES = 15 * MINUTE_IN_SECONDS;

	/**
	 * Nombre maximal de dates traitées par exécution.
	 *
	 * Deux au plus : aujourd'hui et demain. Donc deux appels sortants au plus,
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

		$maintenant = SourceCalendar::now();
		$dates      = self::dates_a_traiter( $maintenant );

		foreach ( $dates as $date ) {
			self::run_for( $date, 'cron' );
		}

		self::surveiller_fenetre( $maintenant );
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

			// La saison s'évalue sur la DATE CIBLE, jamais sur « maintenant ».
			// Hors saison : la source ne publie rien, donc pas d'appel et
			// surtout aucune alerte — une absence attendue n'est pas un
			// incident.
			if ( ! SourceCalendar::is_in_season( $date ) ) {
				StateRepository::record_marker( $date_ymd, 'hors_saison', 'Date hors saison du dispositif.' );
				continue;
			}

			if ( SnapshotRepository::has( $date_ymd ) ) {
				continue;
			}

			$derniere = StateRepository::last_attempt_for( $date_ymd );

			if ( null !== $derniere && ( time() - $derniere ) < self::ANTI_RAFALE_SECONDES ) {
				continue;
			}

			$retenues[] = $date;
		}

		return array_slice( $retenues, 0, self::DATES_MAX );
	}

	/**
	 * Alerte si la fenêtre de publication s'est close sans statut pour demain.
	 *
	 * @param \DateTimeImmutable $maintenant Instant de référence.
	 */
	private static function surveiller_fenetre( \DateTimeImmutable $maintenant ): void {
		$demain     = SourceCalendar::tomorrow( $maintenant );
		$demain_ymd = $demain->format( 'Ymd' );

		if ( (int) $maintenant->format( 'G' ) < Settings::fenetre()['fin'] ) {
			return;
		}

		if ( ! SourceCalendar::is_in_season( $demain ) || SnapshotRepository::has( $demain_ymd ) ) {
			return;
		}

		Notifier::alert_window_closed( $demain_ymd, StateRepository::get() );
	}

	/**
	 * Récupère, valide et enregistre le fichier d'une date de validité.
	 *
	 * @param \DateTimeImmutable $date        Date de validité visée.
	 * @param string             $declencheur Origine de l'exécution.
	 * @return true|\WP_Error Vrai si la date est couverte à l'issue de l'appel.
	 */
	public static function run_for( \DateTimeImmutable $date, string $declencheur ) {
		$date_ymd = $date->format( 'Ymd' );

		/**
		 * Une tentative de récupération démarre.
		 *
		 * @param string $date_ymd    Date de validité visée.
		 * @param string $declencheur Origine de l'exécution.
		 */
		do_action( 'massifs_prefecture_tentative', $date_ymd, $declencheur );

		StateRepository::record_attempt();

		$reponse = Fetcher::fetch( $date );

		if ( is_wp_error( $reponse ) ) {
			return self::echouer( $date_ymd, 'reseau', $reponse );
		}

		switch ( Fetcher::classify( $reponse['code'] ) ) {
			case 'non_publie':
				// État légitime, pas un échec : le fichier du lendemain n'est
				// déposé qu'en fin d'après-midi. Aucun compteur d'échec, aucune
				// alerte, aucune action `echec`.
				StateRepository::record_issue( $date_ymd, 'non_publie', 'HTTP 404 : fichier non encore publié.' );

				return new \WP_Error(
					'non_publie',
					sprintf( 'Statuts du %s non encore publiés par la source.', $date->format( 'Y-m-d' ) ),
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
	 * @param \DateTimeImmutable                                                          $date    Date de validité visée.
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

		// Déjà enregistré à l'identique POUR CETTE DATE : rien à réécrire.
		// La comparaison porte sur l'instantané de cette date précise, jamais
		// sur un balayage par hachage : voir le bloc ci-dessous.
		$existant = SnapshotRepository::get( $date_ymd );

		if ( null !== $existant
			&& isset( $existant['hash'] )
			&& hash_equals( (string) $existant['hash'], (string) $instantane['hash'] )
		) {
			return true;
		}

		/*
		 * Le hachage NE PROVOQUE JAMAIS DE REJET. Il ne sert qu'à journaliser.
		 *
		 * Le corps servi par la source ne contient aucune date
		 * (`{"massifs":{…},"zm":{…}}`). Deux journées où les 27 massifs portent
		 * les mêmes couples `[niveau, procedure]` produisent donc un corps
		 * octet pour octet identique — et c'est le CAS NOMINAL en juin comme
		 * lors de tout épisode stable (constaté les 8 et 11 août 2026, et le
		 * 15 août 2025 : les 27 massifs au même niveau).
		 *
		 * Le signal de « pas encore publié » n'est pas le hachage : c'est le
		 * 404. La source répond 404 sur `{date}.json` tant que la journée
		 * n'est pas publiée, et un 200 sur cette URL EST la publication de
		 * cette date. Rejeter un corps identique à la veille reviendrait à
		 * afficher « information non disponible » pendant toute la durée d'un
		 * épisode stable, c'est-à-dire précisément quand la donnée est bonne.
		 */
		$identique_a = SnapshotRepository::find_by_hash( (string) $instantane['hash'] );

		$note = sprintf( '%d octets, confiance %s.', (int) $instantane['octets'], (string) $instantane['confiance'] );

		if ( null !== $identique_a && $identique_a !== $date_ymd ) {
			$note .= sprintf( ' Contenu identique à celui du %s (information d\'exploitation, sans effet sur l\'enregistrement).', $identique_a );
		}

		SnapshotRepository::save( $instantane );
		StateRepository::record_issue( $date_ymd, 'succes', $note );

		/**
		 * Unique couture d'intégration du connecteur.
		 *
		 * Le connecteur ne projette jamais l'instantané dans un modèle de
		 * statut et n'invalide jamais un cache de page : c'est au domaine, en
		 * aval, de décider quoi en faire.
		 *
		 * @param array<string,mixed> $instantane Instantané validé et enregistré.
		 */
		do_action( 'massifs_prefecture_snapshot_enregistre', $instantane );

		return true;
	}

	/**
	 * Journalise un échec, le signale, et le retourne.
	 *
	 * @param string    $date_ymd Date de validité visée.
	 * @param string    $issue    Issue à journaliser.
	 * @param \WP_Error $erreur   Erreur associée.
	 */
	private static function echouer( string $date_ymd, string $issue, \WP_Error $erreur ): \WP_Error {
		StateRepository::record_issue( $date_ymd, $issue, (string) $erreur->get_error_message(), $erreur );
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
		do_action( 'massifs_prefecture_echec', $erreur, StateRepository::get() );
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
