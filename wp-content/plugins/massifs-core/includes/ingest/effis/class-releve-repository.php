<?php
/**
 * Stockage du relevé validé de la couche.
 *
 * UN SEUL RELEVÉ EST CONSERVÉ. Une fenêtre glissante n'a pas de valeur passée,
 * et le §4.2 du brief n'impose l'historique que pour les statuts.
 *
 * Les octets vivent dans une OPTION, jamais dans un fichier (invariant I-11.8) :
 * ce relevé est réécrit par le cron, en production, plusieurs fois par jour.
 * L'écrire sous `includes/` ou sous `data/` rendrait un répertoire de code
 * d'extension inscriptible par le serveur web, ce qui prend le durcissement du
 * §9 du brief à revers. `update_option` offre en outre une écriture ATOMIQUE
 * qu'un couple fichier + métadonnées n'offre pas.
 *
 * Il n'existe AUCUN accesseur public « dernier relevé ». Le seul chemin vers
 * ces octets traverse la garde de péremption de `Couche` : la règle tient par
 * l'ABSENCE DE CHEMIN, pas par une garde qu'on pourrait oublier.
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
 * Lecture et écriture de l'option de relevé.
 */
final class ReleveRepository {

	/**
	 * Option de relevé. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_effis_releve';

	/**
	 * Version de structure de l'option.
	 */
	public const SCHEMA = 1;

	/**
	 * Écrit le relevé validé.
	 *
	 * UN SEUL `update_option`, APRÈS validation complète : aucun état partiel
	 * n'est représentable, et un échec n'écrit rien — sinon la fraîcheur
	 * mentirait.
	 *
	 * @param array<string,mixed> $releve Relevé normalisé par `Validator`.
	 */
	public static function save( array $releve ): void {
		update_option( self::OPTION, self::normaliser( $releve ), false );
	}

	/**
	 * Relevé stocké, normalisé, ou `null`.
	 *
	 * Visibilité de module : `Couche` est le seul appelant légitime.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get(): ?array {
		$stocke = get_option( self::OPTION, null );

		if ( ! is_array( $stocke ) || ! isset( $stocke['releve_le'], $stocke['zones'] ) ) {
			return null;
		}

		$releve = self::normaliser( $stocke );

		return '' === $releve['releve_le'] ? null : $releve;
	}

	/**
	 * Normalise un relevé, quelle que soit sa provenance.
	 *
	 * Une option est modifiable depuis l'administration : elle est ré-assainie
	 * à la relecture, jamais crue sur parole.
	 *
	 * @param array<string,mixed> $brut Relevé brut.
	 * @return array<string,mixed>
	 */
	private static function normaliser( array $brut ): array {
		$zones = array();

		if ( isset( $brut['zones'] ) && is_array( $brut['zones'] ) ) {
			foreach ( $brut['zones'] as $zone ) {
				if ( is_array( $zone ) ) {
					$zones[] = self::normaliser_zone( $zone );
				}
			}
		}

		return array(
			'schema'     => self::SCHEMA,
			'releve_le'  => Validator::instant_iso( $brut['releve_le'] ?? '' ),
			'source_url' => isset( $brut['source_url'] ) ? esc_url_raw( (string) $brut['source_url'] ) : '',
			'octets'     => absint( $brut['octets'] ?? 0 ),
			'hash'       => is_string( $brut['hash'] ?? null ) ? (string) $brut['hash'] : '',
			'ecartees'   => absint( $brut['ecartees'] ?? 0 ),
			'connecteur' => in_array( (string) ( $brut['connecteur'] ?? '' ), Settings::CONNECTEURS, true ) ? (string) $brut['connecteur'] : 'simule',
			'zones'      => $zones,
		);
	}

	/**
	 * Normalise une entrée de zone.
	 *
	 * Toutes les clés du contrat sont TOUJOURS présentes : le thème n'écrit
	 * jamais `isset()`.
	 *
	 * @param array<string,mixed> $brut Zone brute.
	 * @return array<string,mixed>
	 */
	private static function normaliser_zone( array $brut ): array {
		$geometrie = isset( $brut['geometrie'] ) && is_array( $brut['geometrie'] ) ? $brut['geometrie'] : array();

		return array(
			'id'                     => is_string( $brut['id'] ?? null ) ? (string) $brut['id'] : '',
			'surface_texte'          => is_string( $brut['surface_texte'] ?? null ) ? (string) $brut['surface_texte'] : '',
			'surface_ha'             => is_numeric( $brut['surface_ha'] ?? null ) ? (float) $brut['surface_ha'] : 0.0,
			'premiere_observation'   => Validator::instant_iso( $brut['premiere_observation'] ?? '' ),
			'derniere_observation'   => Validator::instant_iso( $brut['derniere_observation'] ?? '' ),
			'commune_la_plus_proche' => is_string( $brut['commune_la_plus_proche'] ?? null ) ? (string) $brut['commune_la_plus_proche'] : '',
			'geometrie'              => array(
				'type'        => in_array( (string) ( $geometrie['type'] ?? '' ), Validator::TYPES_GEOMETRIE, true ) ? (string) $geometrie['type'] : 'Polygon',
				'coordinates' => isset( $geometrie['coordinates'] ) && is_array( $geometrie['coordinates'] ) ? $geometrie['coordinates'] : array(),
			),
		);
	}
}
