<?php
/**
 * État opérationnel du module : tentatives, succès, échecs, verrous d'alerte,
 * journal.
 *
 * Cet état ne contient AUCUNE zone : il décrit uniquement la santé de la
 * récupération. Les octets de la couche vivent dans `ReleveRepository`, et
 * l'horodatage qui fait autorité pour la péremption vit avec eux.
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
 * Lecture et écriture de l'option d'état.
 */
final class StateRepository {

	/**
	 * Option d'état. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_effis_etat';

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
		'reseau',
		'source_indisponible',
		'transport',
		'rejet',
		'desactive',
		'url_absente',
	);

	/**
	 * Issues qui incrémentent le compteur d'échecs consécutifs.
	 */
	private const ISSUES_EN_ECHEC = array(
		'reseau',
		'source_indisponible',
		'transport',
		'rejet',
	);

	/**
	 * Types d'alerte gérés. Liste FERMÉE.
	 *
	 * `peremption` : la couche vient de disparaître du site.
	 * `rejet`      : la source a été récupérée puis refusée par la validation.
	 */
	public const ALERTES = array( 'peremption', 'rejet' );

	/**
	 * Structure vide.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema'             => self::SCHEMA,
			'derniere_tentative' => null,
			'derniere_reussite'  => null,
			'derniere_erreur'    => null,
			'echecs_consecutifs' => 0,
			'alertes'            => array_fill_keys( self::ALERTES, null ),
			'journal'            => array(),
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
		$etat['journal']            = is_array( $etat['journal'] ) ? array_values( $etat['journal'] ) : array();

		$alertes = is_array( $etat['alertes'] ) ? $etat['alertes'] : array();
		$propres = array();

		foreach ( self::ALERTES as $type ) {
			$valeur           = $alertes[ $type ] ?? null;
			$propres[ $type ] = is_string( $valeur ) && '' !== $valeur ? $valeur : null;
		}

		$etat['alertes'] = $propres;

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
	 * Horodatage ISO 8601 UTC de l'instant courant.
	 */
	private static function maintenant(): string {
		return gmdate( Settings::FORMAT_ISO_UTC );
	}

	/**
	 * Enregistre le fait qu'une tentative démarre.
	 *
	 * Écrit AVANT tout octet réseau : si le processus meurt pendant l'appel, la
	 * trace de la tentative existe quand même, et le garde-fou anti-rafale
	 * reste opposable.
	 */
	public static function record_attempt(): void {
		$etat                       = self::get();
		$etat['derniere_tentative'] = self::maintenant();

		self::save( $etat );
	}

	/**
	 * Enregistre l'issue d'une tentative.
	 *
	 * @param string         $issue  Issue, parmi self::ISSUES.
	 * @param string         $detail Détail court, non échappé.
	 * @param \WP_Error|null $erreur Erreur associée, le cas échéant.
	 */
	public static function record_issue( string $issue, string $detail = '', ?\WP_Error $erreur = null ): void {
		if ( ! in_array( $issue, self::ISSUES, true ) ) {
			return;
		}

		$etat = self::get();

		if ( 'succes' === $issue ) {
			$etat['echecs_consecutifs'] = 0;
			$etat['derniere_reussite']  = self::maintenant();
			$etat['derniere_erreur']    = null;
			// Les verrous se ré-arment au premier succès : un nouvel épisode
			// mérite un nouveau courriel.
			$etat['alertes']            = array_fill_keys( self::ALERTES, null );
		} elseif ( in_array( $issue, self::ISSUES_EN_ECHEC, true ) ) {
			$etat['echecs_consecutifs'] = absint( $etat['echecs_consecutifs'] ) + 1;
			$etat['derniere_erreur']    = self::formater_erreur( $issue, $detail, $erreur );
		}

		$etat['journal'] = self::empiler( $etat['journal'], $issue, $detail );

		self::save( $etat );
	}

	/**
	 * Enregistre un marqueur d'état stable (désarmé, URL absente).
	 *
	 * Dédupliqué contre la dernière entrée : ces états durent des semaines et
	 * noieraient le journal FIFO à raison d'une entrée par heure.
	 *
	 * @param string $issue  Issue, parmi self::ISSUES.
	 * @param string $detail Détail court.
	 */
	public static function record_marker( string $issue, string $detail = '' ): void {
		if ( ! in_array( $issue, self::ISSUES, true ) ) {
			return;
		}

		$etat    = self::get();
		$journal = $etat['journal'];
		$dernier = array() === $journal ? null : $journal[ count( $journal ) - 1 ];

		if ( is_array( $dernier ) && ( $dernier['issue'] ?? '' ) === $issue ) {
			return;
		}

		$etat['journal'] = self::empiler( $journal, $issue, $detail );

		self::save( $etat );
	}

	/**
	 * Ajoute une entrée au journal FIFO.
	 *
	 * @param array<int,array<string,string>> $journal Journal courant.
	 * @param string                          $issue   Issue.
	 * @param string                          $detail  Détail court.
	 * @return array<int,array<string,string>>
	 */
	private static function empiler( array $journal, string $issue, string $detail ): array {
		$journal[] = array(
			'le'     => self::maintenant(),
			'issue'  => $issue,
			'detail' => self::tronquer( $detail ),
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
	 * Horodatage Unix de la dernière tentative, ou `null`.
	 */
	public static function derniere_tentative(): ?int {
		return self::instant( self::get()['derniere_tentative'] ?? null );
	}

	/**
	 * Horodatage Unix de la dernière réussite, ou `null`.
	 */
	public static function derniere_reussite(): ?int {
		return self::instant( self::get()['derniere_reussite'] ?? null );
	}

	/**
	 * Convertit une valeur d'état en horodatage Unix.
	 *
	 * @param mixed $valeur Valeur brute.
	 */
	private static function instant( $valeur ): ?int {
		if ( ! is_string( $valeur ) || '' === $valeur ) {
			return null;
		}

		$horodatage = strtotime( $valeur );

		return false === $horodatage ? null : $horodatage;
	}

	/**
	 * Une alerte de ce type a-t-elle déjà été envoyée pour l'épisode courant ?
	 *
	 * @param string $type Type d'alerte, parmi self::ALERTES.
	 */
	public static function was_alerted( string $type ): bool {
		if ( ! in_array( $type, self::ALERTES, true ) ) {
			return true;
		}

		return null !== self::get()['alertes'][ $type ];
	}

	/**
	 * Pose le verrou d'alerte : une alerte par épisode, pas une par exécution.
	 *
	 * @param string $type Type d'alerte, parmi self::ALERTES.
	 */
	public static function mark_alerted( string $type ): void {
		if ( ! in_array( $type, self::ALERTES, true ) ) {
			return;
		}

		$etat                     = self::get();
		$etat['alertes'][ $type ] = self::maintenant();

		self::save( $etat );
	}
}
