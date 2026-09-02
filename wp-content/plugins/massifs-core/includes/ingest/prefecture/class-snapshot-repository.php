<?php
/**
 * Stockage des instantanés de la source, indexés par date de VALIDITÉ.
 *
 * Une carte de dates, pas un instantané unique : à 18 h le jour J on obtient
 * J+1 alors que J est toujours le statut courant. Les deux doivent coexister.
 *
 * L'unité de persistance est UNE option, écrite par un seul `update_option`
 * après validation complète : aucune écriture partielle n'est représentable.
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
 * Lecture et écriture des instantanés.
 */
final class SnapshotRepository {

	/**
	 * Option des instantanés. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_prefecture_snapshots';

	/**
	 * Version de structure d'un enregistrement.
	 *
	 * Volontairement inchangée par l'ajout de la clé `projection` : `all()`
	 * conserve les clés inconnues, un instantané écrit avant cet ajout se relit
	 * donc sans migration, et son absence de clé se lit comme l'état `inconnue`.
	 */
	public const SCHEMA = 1;

	/**
	 * Résultats reconnus pour l'état de projection d'un instantané.
	 *
	 * `inconnue`        — aucune projection n'est connue pour cet instantané.
	 * `complet`         — le lot entier a été écrit par le domaine.
	 * `partiel`         — une partie seulement a été écrite.
	 * `rejete`          — le domaine a refusé le lot, rien n'a été écrit.
	 * `sans_projecteur` — personne n'a conclu de projection : le domaine est
	 *                     absent ou désarmé. Rejouable UNIQUEMENT si un abonné
	 *                     est de nouveau présent — voir `Runner::etat_rejouable()`.
	 */
	public const PROJECTION_RESULTATS = array( 'inconnue', 'complet', 'partiel', 'rejete', 'sans_projecteur' );

	/**
	 * Résultats qu'un bilan du domaine peut légitimement conclure.
	 *
	 * Sous-ensemble de `PROJECTION_RESULTATS` : `inconnue` est l'absence de bilan
	 * et `sans_projecteur` est une conclusion du connecteur, jamais du domaine.
	 */
	public const PROJECTION_RESULTATS_DU_DOMAINE = array( 'complet', 'partiel', 'rejete' );

	/**
	 * Résultats qui autorisent un rejeu de projection SANS AUCUNE CONDITION.
	 *
	 * Sous-ensemble de `PROJECTION_RESULTATS` : `inconnue` et `complet` n'ont rien
	 * à réparer. `sans_projecteur` n'est pas dans cette liste parce qu'il n'est
	 * pas inconditionnel — il exige la présence d'un abonné, que seul le `Runner`
	 * sait sonder. La décision entière vit dans `Runner::rejeu_du()`, jamais ici :
	 * cette constante n'est qu'un vocabulaire. La table complète est dans
	 * `docs/decisions/rejeu-ingestion-prefecture.md` §3.
	 */
	public const PROJECTION_RESULTATS_REJOUABLES = array( 'partiel', 'rejete' );

	/**
	 * Longueur maximale d'un motif de bilan, en caractères.
	 */
	private const MOTIF_MAX = 300;

	/**
	 * Tous les instantanés, indexés par `Ymd`.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$stocke = get_option( self::OPTION, null );

		if ( ! is_array( $stocke ) ) {
			return array();
		}

		$propres = array();

		foreach ( $stocke as $date => $enregistrement ) {
			$date = (string) $date;

			if ( 1 === preg_match( '/^\d{8}$/', $date ) && is_array( $enregistrement ) ) {
				$propres[ $date ] = $enregistrement;
			}
		}

		return $propres;
	}

	/**
	 * Instantané pour une date de validité.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $date_ymd ): ?array {
		$tous = self::all();

		return isset( $tous[ $date_ymd ] ) ? $tous[ $date_ymd ] : null;
	}

	/**
	 * Un instantané couvre-t-il cette date de validité ?
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	public static function has( string $date_ymd ): bool {
		return null !== self::get( $date_ymd );
	}

	/**
	 * Date de validité d'un instantané portant ce hachage, s'il existe.
	 *
	 * @param string $hash Hachage sha256 du corps brut.
	 * @return string|null Date au format `Ymd`.
	 */
	public static function find_by_hash( string $hash ): ?string {
		if ( '' === $hash ) {
			return null;
		}

		foreach ( self::all() as $date => $enregistrement ) {
			if ( isset( $enregistrement['hash'] ) && hash_equals( (string) $enregistrement['hash'], $hash ) ) {
				return (string) $date;
			}
		}

		return null;
	}

	/**
	 * État de projection neutre.
	 *
	 * @return array{resultat:string,le:string|null,motif:string,rejeux:int,rejeux_le:string}
	 */
	public static function projection_vide(): array {
		return array(
			'resultat'  => 'inconnue',
			'le'        => null,
			'motif'     => '',
			'rejeux'    => 0,
			'rejeux_le' => '',
		);
	}

	/**
	 * État de projection d'un instantané, normalisé.
	 *
	 * L'ABSENCE TOTALE DE LA CLÉ SE LIT COMME `inconnue`, jamais comme un échec
	 * ni comme un succès : un instantané écrit avant l'introduction de la clé ne
	 * doit ni déclencher de rejeu, ni faire croire que le domaine a reçu son lot.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return array{resultat:string,le:string|null,motif:string,rejeux:int,rejeux_le:string}
	 */
	public static function projection( string $date_ymd ): array {
		$enregistrement = self::get( $date_ymd );

		if ( null === $enregistrement || ! isset( $enregistrement['projection'] ) || ! is_array( $enregistrement['projection'] ) ) {
			return self::projection_vide();
		}

		return self::normaliser_projection( $enregistrement['projection'] );
	}

	/**
	 * Nombre de rejeux déjà consommés AUJOURD'HUI pour cette date de validité.
	 *
	 * Le compteur se réarme au changement de jour : une cause permanente
	 * d'échec de projection ne doit pas boucler, mais elle doit pouvoir être
	 * réessayée le lendemain, une fois le référentiel réparé.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	public static function rejeux_du_jour( string $date_ymd ): int {
		$projection = self::projection( $date_ymd );

		return $projection['rejeux_le'] === SourceCalendar::today()->format( 'Ymd' ) ? $projection['rejeux'] : 0;
	}

	/**
	 * Consomme un rejeu pour cette date de validité.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	public static function consommer_rejeu( string $date_ymd ): void {
		self::update_projection(
			$date_ymd,
			array(
				'rejeux'    => self::rejeux_du_jour( $date_ymd ) + 1,
				'rejeux_le' => SourceCalendar::today()->format( 'Ymd' ),
			)
		);
	}

	/**
	 * Écrit l'état de projection d'une date SANS toucher au reste de l'instantané.
	 *
	 * Écriture ciblée : `all()` est relu, seule la clé `projection` de cette date
	 * est remplacée, l'option est réécrite. Aucun élagage n'est déclenché — ce
	 * n'est pas une nouvelle publication, c'est l'annotation d'une publication
	 * existante.
	 *
	 * Les clés fournies sont FUSIONNÉES sur l'état courant : le récepteur de
	 * bilan écrit `resultat`/`le`/`motif`, le connecteur écrit les compteurs de
	 * rejeu, et aucun des deux n'efface l'autre.
	 *
	 * @param string              $date_ymd   Date au format `Ymd`.
	 * @param array<string,mixed> $projection Clés à fusionner.
	 * @return bool Faux si la date n'existe pas — rien n'est créé — ou si l'option est déjà à cette valeur.
	 */
	public static function update_projection( string $date_ymd, array $projection ): bool {
		$tous = self::all();

		if ( ! isset( $tous[ $date_ymd ] ) ) {
			return false;
		}

		$courante = isset( $tous[ $date_ymd ]['projection'] ) && is_array( $tous[ $date_ymd ]['projection'] )
			? $tous[ $date_ymd ]['projection']
			: array();

		$tous[ $date_ymd ]['projection'] = self::normaliser_projection(
			array_merge( self::normaliser_projection( $courante ), $projection )
		);

		return update_option( self::OPTION, $tous, false );
	}

	/**
	 * Normalise un état de projection d'origine quelconque.
	 *
	 * @param array<string,mixed> $brut État brut.
	 * @return array{resultat:string,le:string|null,motif:string,rejeux:int,rejeux_le:string}
	 */
	private static function normaliser_projection( array $brut ): array {
		$vide   = self::projection_vide();
		$propre = array_merge( $vide, $brut );

		$resultat = is_string( $propre['resultat'] ) ? $propre['resultat'] : '';

		$propre['resultat']  = in_array( $resultat, self::PROJECTION_RESULTATS, true ) ? $resultat : 'inconnue';
		$propre['le']        = is_string( $propre['le'] ) && '' !== $propre['le'] ? $propre['le'] : null;
		$propre['motif']     = self::tronquer_motif( is_string( $propre['motif'] ) ? $propre['motif'] : '' );
		$propre['rejeux']    = absint( $propre['rejeux'] );
		$propre['rejeux_le'] = is_string( $propre['rejeux_le'] ) && 1 === preg_match( '/^\d{8}$/', $propre['rejeux_le'] )
			? $propre['rejeux_le']
			: '';

		return array_intersect_key( $propre, $vide );
	}

	/**
	 * Tronque un motif de bilan : il vient du domaine, il n'a pas à faire enfler
	 * l'option d'instantanés.
	 *
	 * @param string $motif Motif brut.
	 */
	private static function tronquer_motif( string $motif ): string {
		$motif = trim( wp_strip_all_tags( $motif ) );

		if ( mb_strlen( $motif ) <= self::MOTIF_MAX ) {
			return $motif;
		}

		return mb_substr( $motif, 0, self::MOTIF_MAX - 1 ) . '…';
	}

	/**
	 * Écrit un instantané et élague les plus anciens.
	 *
	 * L'enregistrement remplace le précédent, clé `projection` comprise : un
	 * corps nouveau ouvre un nouveau cycle de projection, avec son propre budget
	 * de rejeux. Ce n'est pas une fuite de budget — le nombre de corps nouveaux
	 * par jour est lui-même borné par le plafond de re-contrôles du connecteur.
	 *
	 * @param array<string,mixed> $enregistrement Instantané validé.
	 * @return bool Vrai si l'écriture a eu lieu.
	 */
	public static function save( array $enregistrement ): bool {
		$date_iso = (string) ( $enregistrement['date_validite'] ?? '' );
		$date     = SourceCalendar::from_iso( $date_iso );

		if ( null === $date ) {
			return false;
		}

		$tous = self::all();

		$tous[ $date->format( 'Ymd' ) ] = $enregistrement;

		return update_option( self::OPTION, self::elaguer( $tous ), false );
	}

	/**
	 * Élague les instantanés hors fenêtre de conservation.
	 *
	 * Aujourd'hui et demain ne sont jamais élagués, quelle que soit la
	 * profondeur configurée : ce sont les seules dates que le site peut
	 * présenter.
	 *
	 * @param array<string,array<string,mixed>> $tous Instantanés courants.
	 * @return array<string,array<string,mixed>>
	 */
	private static function elaguer( array $tous ): array {
		$jours    = Settings::conserver_jours();
		$proteges = array(
			SourceCalendar::today()->format( 'Ymd' ),
			SourceCalendar::tomorrow()->format( 'Ymd' ),
		);

		$limite = SourceCalendar::today()->modify( '-' . $jours . ' days' )->format( 'Ymd' );

		foreach ( array_keys( $tous ) as $date ) {
			$date = (string) $date;

			if ( ! in_array( $date, $proteges, true ) && $date < $limite ) {
				unset( $tous[ $date ] );
			}
		}

		// Plafond dur : une source qui servirait des dates lointaines ne doit
		// pas faire enfler l'option indéfiniment.
		$plafond = $jours + count( $proteges );

		if ( count( $tous ) > $plafond ) {
			$dates = array_keys( $tous );
			rsort( $dates, SORT_STRING );

			$conserves = array_unique( array_merge( $proteges, array_slice( $dates, 0, $plafond ) ) );

			foreach ( array_keys( $tous ) as $date ) {
				if ( ! in_array( (string) $date, $conserves, true ) ) {
					unset( $tous[ $date ] );
				}
			}
		}

		ksort( $tous, SORT_STRING );

		return $tous;
	}
}
