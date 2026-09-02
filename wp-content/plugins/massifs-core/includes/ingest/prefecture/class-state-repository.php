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
	 *
	 * CE PLAFOND N'EST DÉLIBÉRÉMENT PAS RELEVÉ. Il l'aurait fallu tant que le
	 * garde anti-rafale dérivait sa mémoire du journal : à la cadence réelle
	 * (une passe tous les quarts d'heure, 96 par jour, jusqu'à deux dates par
	 * passe), 20 entrées couvrent une dizaine de minutes, et la dernière
	 * tentative pour une date sortait du journal avant que le garde ait fini de
	 * la protéger. La mémoire du garde vit désormais dans la carte `tentatives`,
	 * indépendante du journal : ce plafond ne gouverne plus que la lisibilité de
	 * l'écran d'exploitation, ce pour quoi 20 entrées suffisent.
	 */
	public const JOURNAL_MAX = 20;

	/**
	 * Issues reconnues pour une entrée de journal.
	 */
	public const ISSUES = array(
		'succes',
		'non_publie',
		'reseau',
		'source_indisponible',
		'transport',
		'rejet',
		'hors_saison',
		'desactive',
		'rejeu',
	);

	/**
	 * Issues qui incrémentent le compteur d'échecs consécutifs.
	 *
	 * Un 404 avant publication n'est pas un échec : c'est le signal normal
	 * « pas encore publié ».
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
	 * Profondeur de rétention des cartes datées (tentatives, re-contrôles).
	 *
	 * Ces cartes ne servent qu'à freiner le travail d'aujourd'hui et de demain :
	 * au-delà, elles ne répondent plus à aucune question. Trois jours bornent
	 * l'option à une poignée d'entrées, quoi qu'il arrive.
	 */
	private const DATES_RETENTION_JOURS = 3;

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
			'tentatives'            => array(),
			'recontroles'           => array(),
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
		$etat['tentatives']         = is_array( $etat['tentatives'] ) ? $etat['tentatives'] : array();
		$etat['recontroles']        = is_array( $etat['recontroles'] ) ? $etat['recontroles'] : array();

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
	 * Enregistre la tentative POUR UNE DATE CIBLE, dans une carte dédiée.
	 *
	 * Écrite avant tout octet réseau, comme `record_attempt()`, et pour la même
	 * raison : la trace doit survivre à un processus qui meurt pendant l'appel.
	 *
	 * @param string $date_ymd Date de validité visée.
	 */
	public static function record_attempt_for( string $date_ymd ): void {
		if ( 1 !== preg_match( '/^\d{8}$/', $date_ymd ) ) {
			return;
		}

		$etat = self::get();

		$etat['tentatives'][ $date_ymd ] = time();
		$etat['tentatives']              = self::elaguer_dates( $etat['tentatives'] );

		self::save( $etat );
	}

	/**
	 * Nombre de re-contrôles réseau déjà consommés AUJOURD'HUI pour cette date.
	 *
	 * @param string $date_ymd Date de validité visée.
	 */
	public static function recontroles_for( string $date_ymd ): int {
		$entree = self::get()['recontroles'][ $date_ymd ] ?? null;

		if ( ! is_array( $entree ) || ( $entree['le'] ?? '' ) !== SourceCalendar::today()->format( 'Ymd' ) ) {
			return 0;
		}

		return absint( $entree['n'] ?? 0 );
	}

	/**
	 * Consomme un re-contrôle réseau pour cette date.
	 *
	 * Le compteur porte le jour auquel il appartient et se réarme donc de
	 * lui-même au changement de jour, sans tâche de purge.
	 *
	 * @param string $date_ymd Date de validité visée.
	 */
	public static function record_recontrole( string $date_ymd ): void {
		if ( 1 !== preg_match( '/^\d{8}$/', $date_ymd ) ) {
			return;
		}

		$etat = self::get();

		$etat['recontroles'][ $date_ymd ] = array(
			'le' => SourceCalendar::today()->format( 'Ymd' ),
			'n'  => self::recontroles_for( $date_ymd ) + 1,
		);

		$etat['recontroles'] = self::elaguer_dates( $etat['recontroles'] );

		self::save( $etat );
	}

	/**
	 * Élague une carte indexée par date de validité.
	 *
	 * Même principe que `elaguer_alertes()` : une carte alimentée à chaque passe
	 * doit avoir une borne, sans quoi l'option grossit indéfiniment.
	 *
	 * @param array<string,mixed> $carte Carte courante.
	 * @return array<string,mixed>
	 */
	private static function elaguer_dates( array $carte ): array {
		$limite = SourceCalendar::today()->modify( '-' . self::DATES_RETENTION_JOURS . ' days' )->format( 'Ymd' );

		foreach ( array_keys( $carte ) as $date ) {
			if ( 1 !== preg_match( '/^\d{8}$/', (string) $date ) || (string) $date < $limite ) {
				unset( $carte[ $date ] );
			}
		}

		return $carte;
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
	 * DÉDOUBLONNÉ PAR DATE CIBLE, sur tout le journal — et non contre la seule
	 * dernière entrée. Une passe hors saison marque aujourd'hui PUIS demain :
	 * comparés à la seule dernière entrée, `hors_saison(J)` et `hors_saison(J+1)`
	 * alternent et ne se dédoublonnent donc jamais. À la cadence réelle (96
	 * passes par jour), cela écrivait 192 entrées par jour dans un journal de 20,
	 * qui ne montrait plus que quatre mois de hors-saison — le seul état sur
	 * lequel personne n'a rien à lire.
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

		foreach ( $journal as $entree ) {
			if ( is_array( $entree )
				&& ( $entree['issue'] ?? '' ) === $issue
				&& ( $entree['date_cible'] ?? '' ) === $date_ymd ) {
				return;
			}
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
	 * LA CARTE `tentatives` FAIT FOI. Elle est écrite à chaque tentative, ne
	 * dépend d'aucun plafond FIFO, et c'est elle qui donne au garde anti-rafale
	 * une mémoire de la durée qu'il prétend couvrir.
	 *
	 * Le balayage du journal reste comme REPLI, pour un état écrit avant
	 * l'introduction de la carte : rétro-compatibilité, sans migration.
	 *
	 * @param string $date_ymd Date de validité visée.
	 */
	public static function last_attempt_for( string $date_ymd ): ?int {
		$etat = self::get();

		if ( isset( $etat['tentatives'][ $date_ymd ] ) ) {
			$horodatage = (int) $etat['tentatives'][ $date_ymd ];

			if ( $horodatage > 0 ) {
				return $horodatage;
			}
		}

		$dernier = null;

		foreach ( $etat['journal'] as $entree ) {
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
