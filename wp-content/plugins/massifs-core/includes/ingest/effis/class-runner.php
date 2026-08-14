<?php
/**
 * Orchestration d'une exécution de récupération.
 *
 * TOUTES LES GARDES SONT FRANCHIES AVANT LE MOINDRE OCTET RÉSEAU : coupe-circuit
 * armé, URL absente, tentative trop récente, dernier succès encore suffisant —
 * aucun appel sortant n'est émis. Le plafond nominal qui en résulte est de
 * quatre appels sortants par jour, cohérent avec une source publiée de l'ordre
 * de deux fois par jour.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exécution planifiée.
 */
final class Runner {

	/**
	 * Délai minimal entre deux TENTATIVES, en secondes.
	 */
	private const ANTI_RAFALE_SECONDES = 30 * MINUTE_IN_SECONDS;

	/**
	 * Âge en deçà duquel le dernier SUCCÈS suffit, en secondes.
	 *
	 * La récurrence est horaire, mais la source ne bouge que de l'ordre de deux
	 * fois par jour : interroger toutes les heures serait du bruit sans gain.
	 */
	private const SUFFISANCE_SECONDES = 6 * HOUR_IN_SECONDS;

	/**
	 * Exécution déclenchée par le planificateur.
	 */
	public static function run_scheduled(): void {
		if ( Settings::is_disabled() ) {
			StateRepository::record_marker( 'desactive', 'Module désarmé : aucun appel sortant.' );

			return;
		}

		if ( '' === Settings::url() ) {
			StateRepository::record_marker( 'url_absente', 'Aucune URL de source résolue : aucun appel sortant.' );

			// La surveillance de péremption reste due : une URL retirée après
			// coup ne doit pas faire disparaître la couche en silence.
			self::surveiller_peremption();

			return;
		}

		if ( self::doit_temporiser() ) {
			self::surveiller_peremption();

			return;
		}

		self::executer();
		self::surveiller_peremption();
	}

	/**
	 * Une garde de cadence interdit-elle l'appel sortant ?
	 */
	private static function doit_temporiser(): bool {
		$maintenant = time();

		$tentative = StateRepository::derniere_tentative();

		if ( null !== $tentative && ( $maintenant - $tentative ) < self::ANTI_RAFALE_SECONDES ) {
			return true;
		}

		$reussite = StateRepository::derniere_reussite();

		if ( null !== $reussite && ( $maintenant - $reussite ) < self::SUFFISANCE_SECONDES ) {
			return true;
		}

		return false;
	}

	/**
	 * Récupère, valide et enregistre la couche.
	 *
	 * @return true|\WP_Error
	 */
	public static function executer() {
		/**
		 * Une tentative de récupération démarre.
		 *
		 * @param string $module Clé de source.
		 */
		do_action( 'massifs_effis_tentative', 'effis' );

		StateRepository::record_attempt();

		$reponse = Fetcher::fetch();

		if ( is_wp_error( $reponse ) ) {
			return self::echouer( 'reseau', $reponse );
		}

		$classe = Fetcher::classify( $reponse['code'] );

		if ( 'succes' !== $classe ) {
			// Un 404 tombe ici comme n'importe quel autre code inattendu : il
			// n'existe aucun état « pas encore publié » pour une fenêtre
			// glissante.
			$codes = array(
				'source_indisponible' => 'source_indisponible',
				'transport'           => 'transport_inattendu',
			);

			return self::echouer(
				$classe,
				new \WP_Error(
					$codes[ $classe ],
					sprintf( 'Réponse HTTP inattendue : %d.', $reponse['code'] ),
					array(
						'couche' => 'transport',
						'detail' => $reponse['code'],
					)
				)
			);
		}

		$releve = Validator::validate(
			$reponse['body'],
			$reponse['headers'],
			array( 'source_url' => $reponse['url'] )
		);

		if ( is_wp_error( $releve ) ) {
			// UN REJET N'ÉCRIT RIEN : le relevé précédent reste en place et
			// continue de vivre sa propre péremption. Écraser une donnée valide
			// par un rejet ferait mentir la fraîcheur.
			StateRepository::record_issue( 'rejet', (string) $releve->get_error_message(), $releve );
			Notifier::alert_rejected( $releve, StateRepository::get() );
			self::signaler_echec( $releve );

			return $releve;
		}

		ReleveRepository::save( $releve );

		StateRepository::record_issue(
			'succes',
			sprintf( '%d octets, %d zone(s) retenue(s), %d écartée(s) hors emprise.', (int) $releve['octets'], count( $releve['zones'] ), (int) $releve['ecartees'] )
		);

		// Registre transverse de fraîcheur (§4.5). Écrit, JAMAIS RELU : l'horloge
		// qui fait autorité pour la péremption vit avec la couche.
		if ( function_exists( 'massifs_enregistrer_releve_reussi' ) ) {
			massifs_enregistrer_releve_reussi( 'effis', (string) $releve['releve_le'] );
		}

		self::invalider_cache_public();

		/**
		 * Un relevé validé vient d'être enregistré.
		 *
		 * @param array<string,mixed> $releve Relevé validé et enregistré.
		 */
		do_action( 'massifs_effis_releve_enregistre', $releve );

		return true;
	}

	/**
	 * Alerte à l'instant où la couche disparaît du site.
	 */
	private static function surveiller_peremption(): void {
		if ( ! Couche::peremption_traversee() ) {
			return;
		}

		Notifier::alert_peremption( StateRepository::get() );
	}

	/**
	 * Journalise un échec, le signale, et le retourne.
	 *
	 * @param string    $issue  Issue à journaliser.
	 * @param \WP_Error $erreur Erreur associée.
	 */
	private static function echouer( string $issue, \WP_Error $erreur ): \WP_Error {
		StateRepository::record_issue( $issue, (string) $erreur->get_error_message(), $erreur );
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
		 * @param array<string,mixed> $etat   État du module après journalisation.
		 */
		do_action( 'massifs_effis_echec', $erreur, StateRepository::get() );
	}

	/**
	 * Invalide le cache de page publique après un relevé enregistré.
	 *
	 * Aucune dépendance à une extension de cache : le cache d'objets est vidé,
	 * et un hôte qui pose un cache de page s'y branche par l'action ci-dessous.
	 * L'opération reste bornée par la cadence — quatre relevés par jour au plus.
	 */
	private static function invalider_cache_public(): void {
		wp_cache_flush();

		/**
		 * La couche publiée a changé : le cache de page la portant est caduc.
		 */
		do_action( 'massifs_effis_cache_a_invalider' );
	}
}
