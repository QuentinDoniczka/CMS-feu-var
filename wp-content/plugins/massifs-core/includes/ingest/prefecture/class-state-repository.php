<?php
/**
 * État opérationnel du connecteur : tentatives, échecs, alertes, journal.
 *
 * Cet état ne contient jamais de statut : il décrit uniquement la santé de la
 * récupération. Les données de statut vivent dans `SnapshotRepository`.
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
 * Lecture et écriture de l'option d'état.
 */
final class StateRepository {

	/**
	 * Option d'état. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_prefecture_etat';

	/**
	 * Version de structure de l'option.
	 */
	public const SCHEMA = 1;

	/**
	 * Taille maximale du journal, en entrées (FIFO).
	 */
	public const JOURNAL_MAX = 20;

	/**
	 * Issues reconnues pour une entrée de journal.
	 */
	public const ISSUES = array(
		'succes',
		'non_publie',
		'non_publie_doublon',
		'reseau',
		'source_indisponible',
		'transport',
		'rejet',
		'hors_saison',
		'desactive',
	);

	/**
	 * Issues qui incrémentent le compteur d'échecs consécutifs.
	 *
	 * Un 404 avant publication n'est pas un échec : c'est le signal normal
	 * « pas encore publié ». Un doublon de corps non plus : la source sert
	 * encore le fichier de la veille.
	 */
	private const ISSUES_EN_ECHEC = array(
		'reseau',
		'source_indisponible',
		'transport',
		'rejet',
	);

	/**
	 * Types d'alerte gérés.
	 */
	public const ALERTES = array( 'fenetre', 'rejet' );

	/**
	 * Profondeur de rétention des verrous d'alerte, en jours.
	 */
	private const ALERTES_RETENTION_JOURS = 30;

	/**
	 * Structure vide.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema'                => self::SCHEMA,
			'derniere_tentative'    => null,
			'derniere_reussite'     => null,
			'derniere_date_obtenue' => null,
			'derniere_erreur'       => null,
			'echecs_consecutifs'    => 0,
			'alertes'               => array(),
			'journal'               => array(),
		);
	}

	/**
	 * État courant, normalisé.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$stocke = get_option( self::OPTION, null );

		if ( ! is_array( $stocke ) ) {
			return self::defaults();
		}

		return self::normaliser( $stocke );
	}

	/**
	 * Normalise une structure d'état d'origine quelconque.
	 *
	 * @param array<string,mixed> $brut État brut.
	 * @return array<string,mixed>
	 */
	private static function normaliser( array $brut ): array {
		$etat = array_merge( self::defaults(), $brut );

		$etat['schema']             = self::SCHEMA;
		$etat['echecs_consecutifs'] = absint( $etat['echecs_consecutifs'] );
		$etat['alertes']            = is_array( $etat['alertes'] ) ? $etat['alertes'] : array();
		$etat['journal']            = is_array( $etat['journal'] ) ? array_values( $etat['journal'] ) : array();

		return $etat;
	}

	/**
	 * Persiste l'état.
	 *
	 * @param array<string,mixed> $etat État à écrire.
	 */
	private static function save( array $etat ): void {
		update_option( self::OPTION, self::normaliser( $etat ), false );
	}

	/**
	 * Horodatage ATOM UTC de l'instant courant.
	 */
	private static function maintenant(): string {
		return gmdate( DATE_ATOM );
	}

	/**
	 * Enregistre le fait qu'une tentative démarre.
	 *
	 * Écrit avant tout octet réseau : si le processus meurt pendant l'appel, la
	 * trace de la tentative existe quand même.
	 */
	public static function record_attempt(): void {
		$etat                       = self::get();
		$etat['derniere_tentative'] = self::maintenant();

		self::save( $etat );
	}

	/**
	 * Enregistre l'issue d'une tentative.
	 *
	 * @param string         $date_ymd Date de validité visée.
	 * @param string         $issue    Issue, parmi self::ISSUES.
	 * @param string         $detail   Détail court, non échappé.
	 * @param \WP_Error|null $erreur   Erreur associée, le cas échéant.
	 */
	public static function record_issue( string $date_ymd, string $issue, string $detail = '', ?\WP_Error $erreur = null ): void {
		if ( ! in_array( $issue, self::ISSUES, true ) ) {
			return;
		}

		$etat = self::get();

		if ( 'succes' === $issue ) {
			$etat['echecs_consecutifs']    = 0;
			$etat['derniere_reussite']     = self::maintenant();
			$etat['derniere_date_obtenue'] = $date_ymd;
			$etat['derniere_erreur']       = null;
		} elseif ( in_array( $issue, self::ISSUES_EN_ECHEC, true ) ) {
			$etat['echecs_consecutifs'] = absint( $etat['echecs_consecutifs'] ) + 1;
			$etat['derniere_erreur']    = self::formater_erreur( $issue, $detail, $erreur );
		}

		$etat['journal'] = self::empiler( $etat['journal'], $date_ymd, $issue, $detail );

		self::save( $etat );
	}

	/**
	 * Enregistre un marqueur d'état stable (désactivé, hors saison).
	 *
	 * Dédupliqué contre la dernière entrée : ces états durent des mois et
	 * noieraient le journal FIFO à raison d'une entrée par heure.
	 *
	 * @param string $date_ymd Date de validité visée, ou chaîne vide.
	 * @param string $issue    Issue, parmi self::ISSUES.
	 * @param string $detail   Détail court.
	 */
	public static function record_marker( string $date_ymd, string $issue, string $detail = '' ): void {
		if ( ! in_array( $issue, self::ISSUES, true ) ) {
			return;
		}

		$etat    = self::get();
		$journal = $etat['journal'];
		$dernier = array() === $journal ? null : $journal[ count( $journal ) - 1 ];

		if ( is_array( $dernier )
			&& ( $dernier['issue'] ?? '' ) === $issue
			&& ( $dernier['date_cible'] ?? '' ) === $date_ymd ) {
			return;
		}

		$etat['journal'] = self::empiler( $journal, $date_ymd, $issue, $detail );

		self::save( $etat );
	}

	/**
	 * Ajoute une entrée au journal FIFO.
	 *
	 * @param array<int,array<string,string>> $journal  Journal courant.
	 * @param string                          $date_ymd Date de validité visée.
	 * @param string                          $issue    Issue.
	 * @param string                          $detail   Détail court.
	 * @return array<int,array<string,string>>
	 */
	private static function empiler( array $journal, string $date_ymd, string $issue, string $detail ): array {
		$journal[] = array(
			'le'         => self::maintenant(),
			'date_cible' => $date_ymd,
			'issue'      => $issue,
			'detail'     => self::tronquer( $detail ),
		);

		if ( count( $journal ) > self::JOURNAL_MAX ) {
			$journal = array_slice( $journal, count( $journal ) - self::JOURNAL_MAX );
		}

		return array_values( $journal );
	}

	/**
	 * Construit la dernière erreur enregistrée.
	 *
	 * @param string         $issue  Issue.
	 * @param string         $detail Détail court.
	 * @param \WP_Error|null $erreur Erreur associée.
	 * @return array<string,string>
	 */
	private static function formater_erreur( string $issue, string $detail, ?\WP_Error $erreur ): array {
		$code    = $issue;
		$message = $detail;
		$couche  = 'transport';

		if ( $erreur instanceof \WP_Error ) {
			$code    = (string) $erreur->get_error_code();
			$message = (string) $erreur->get_error_message();
			$donnees = $erreur->get_error_data();

			if ( is_array( $donnees ) && isset( $donnees['couche'] ) ) {
				$couche = (string) $donnees['couche'];
			}
		}

		return array(
			'code'        => self::tronquer( $code, 80 ),
			'message'     => self::tronquer( $message ),
			'couche'      => self::tronquer( $couche, 40 ),
			'survenue_le' => self::maintenant(),
		);
	}

	/**
	 * Tronque un texte de journal.
	 *
	 * @param string $texte   Texte brut.
	 * @param int    $maximum Longueur maximale.
	 */
	private static function tronquer( string $texte, int $maximum = 300 ): string {
		$texte = trim( wp_strip_all_tags( $texte ) );

		if ( mb_strlen( $texte ) <= $maximum ) {
			return $texte;
		}

		return mb_substr( $texte, 0, $maximum - 1 ) . '…';
	}

	/**
	 * Une alerte de ce type a-t-elle déjà été envoyée pour cette date ?
	 *
	 * @param string $date_ymd Date de validité visée.
	 * @param string $type     Type d'alerte, parmi self::ALERTES.
	 */
	public static function was_alerted( string $date_ymd, string $type ): bool {
		if ( ! in_array( $type, self::ALERTES, true ) ) {
			return true;
		}

		$alertes = self::get()['alertes'];

		return ! empty( $alertes[ $date_ymd ][ $type ] );
	}

	/**
	 * Pose le verrou d'alerte : une alerte, pas une par tentative.
	 *
	 * @param string $date_ymd Date de validité visée.
	 * @param string $type     Type d'alerte, parmi self::ALERTES.
	 */
	public static function mark_alerted( string $date_ymd, string $type ): void {
		if ( ! in_array( $type, self::ALERTES, true ) ) {
			return;
		}

		$etat    = self::get();
		$alertes = $etat['alertes'];

		if ( ! isset( $alertes[ $date_ymd ] ) || ! is_array( $alertes[ $date_ymd ] ) ) {
			$alertes[ $date_ymd ] = array_fill_keys( self::ALERTES, null );
		}

		$alertes[ $date_ymd ][ $type ] = self::maintenant();

		$etat['alertes'] = self::elaguer_alertes( $alertes );

		self::save( $etat );
	}

	/**
	 * Élague les verrous d'alerte trop anciens pour éviter une croissance sans
	 * borne de l'option.
	 *
	 * @param array<string,mixed> $alertes Verrous courants.
	 * @return array<string,mixed>
	 */
	private static function elaguer_alertes( array $alertes ): array {
		$limite = SourceCalendar::today()->modify( '-' . self::ALERTES_RETENTION_JOURS . ' days' )->format( 'Ymd' );

		foreach ( array_keys( $alertes ) as $date ) {
			if ( 1 !== preg_match( '/^\d{8}$/', (string) $date ) || (string) $date < $limite ) {
				unset( $alertes[ $date ] );
			}
		}

		return $alertes;
	}

	/**
	 * Horodatage Unix de la dernière tentative pour une date cible.
	 *
	 * Dérivé du journal : il n'existe pas de compteur par date, et le journal
	 * couvre très largement la fenêtre du garde-fou anti-rafale.
	 *
	 * @param string $date_ymd Date de validité visée.
	 */
	public static function last_attempt_for( string $date_ymd ): ?int {
		$dernier = null;

		foreach ( self::get()['journal'] as $entree ) {
			if ( ! is_array( $entree ) || ( $entree['date_cible'] ?? '' ) !== $date_ymd ) {
				continue;
			}

			$horodatage = strtotime( (string) ( $entree['le'] ?? '' ) );

			if ( false !== $horodatage && ( null === $dernier || $horodatage > $dernier ) ) {
				$dernier = $horodatage;
			}
		}

		return $dernier;
	}

}
