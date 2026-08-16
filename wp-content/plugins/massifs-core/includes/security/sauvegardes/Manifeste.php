<?php
/**
 * Composition, écriture et relecture du `manifeste.json` embarqué dans l'archive.
 *
 * POURQUOI LE MANIFESTE VIT DANS L'ARCHIVE, ET PAS À CÔTÉ
 *
 * Une archive déplacée, renommée ou copiée sur un autre hôte doit continuer à
 * dire ce qu'elle contient. Un fichier de métadonnées posé À CÔTÉ se perd au
 * premier `scp`, et l'archive devient une boîte noire dont on ne sait plus si
 * elle était complète.
 *
 * CE QUE LE MANIFESTE PORTE, ET POURQUOI CHAQUE CHAMP EST LÀ
 *
 * - `complet` : l'archive est-elle fidèle ? Faux dès qu'une seule table a divergé
 *   de son `COUNT(*)`. Lu par la commande de restauration AVANT de rejouer quoi
 *   que ce soit.
 * - `tables[*].pagination` : `"offset"` marque une table dumpée SANS clé de
 *   pagination utilisable, donc exposée au saut de lignes sous écriture
 *   concurrente. La faiblesse doit être lisible DANS L'ARCHIVE, pas seulement
 *   dans le code de celui qui l'a écrite.
 * - `tables[*].exclusions` et `lignes_exclues_ignorees` : la liste NOMMÉE de ce
 *   qui n'est pas dans l'archive. C'est dans « exclu » que se cache un faux vert.
 *
 * CE QUE LE MANIFESTE NE PORTE JAMAIS : aucun secret, aucun identifiant de
 * connexion, aucun extrait de données. `DB_HOST` en particulier est absent — il
 * renseigne un attaquant sans servir la restauration, qui lit la configuration de
 * l'hôte cible et non celle de l'hôte d'origine.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Le manifeste d'une archive.
 */
final class Manifeste {

	/**
	 * Nom du fichier dans l'archive.
	 */
	public const NOM_FICHIER = 'manifeste.json';

	/**
	 * Version du format. Un lecteur futur doit pouvoir refuser une archive
	 * qu'il ne sait pas relire, plutôt que d'en deviner la moitié.
	 */
	public const FORMAT = 1;

	/**
	 * Compose le manifeste d'une archive.
	 *
	 * @param array<string, mixed> $base     Rapport du dump, ou tableau vide.
	 * @param array<string, mixed> $fichiers Rapport de collecte, ou tableau vide.
	 * @param string               $genre    `sauvegarde` ou `filet`.
	 *
	 * @return array<string, mixed>
	 */
	public static function composer( array $base, array $fichiers, string $genre ): array {
		$base_incluse     = array() !== $base;
		$fichiers_inclus  = array() !== $fichiers;
		$tables           = isset( $base['tables'] ) && is_array( $base['tables'] ) ? $base['tables'] : array();
		$ignorees_lignes  = isset( $base['lignes_exclues_ignorees'] ) && is_array( $base['lignes_exclues_ignorees'] ) ? array_values( $base['lignes_exclues_ignorees'] ) : array();
		$ignores_fichiers = isset( $fichiers['ignores'] ) && is_array( $fichiers['ignores'] ) ? array_values( $fichiers['ignores'] ) : array();

		// `complet` est le ET de tout ce qui a été demandé. Un périmètre volontairement
		// réduit (`--sans-fichiers`) reste complet : « complet » veut dire « fidèle à
		// ce que l'archive prétend contenir », pas « exhaustif ».
		$complet = true;

		if ( $base_incluse && true !== ( $base['complet'] ?? false ) ) {
			$complet = false;
		}

		if ( $fichiers_inclus && true !== ( $fichiers['complet'] ?? false ) ) {
			$complet = false;
		}

		if ( ! $base_incluse && ! $fichiers_inclus ) {
			$complet = false;
		}

		return array(
			'format'                  => self::FORMAT,
			'genre'                   => 'filet' === $genre ? 'filet' : 'sauvegarde',
			'genere_le'               => gmdate( 'c' ),
			'site_url'                => (string) get_site_url(),
			'wordpress'               => (string) get_bloginfo( 'version' ),
			'php'                     => PHP_VERSION,
			'extension'               => defined( 'MASSIFS_CORE_VERSION' ) ? (string) MASSIFS_CORE_VERSION : '',
			'base_incluse'            => $base_incluse,
			'fichiers_inclus'         => $fichiers_inclus,
			'complet'                 => $complet,
			'base'                    => array(
				'nom'     => defined( 'DB_NAME' ) ? (string) DB_NAME : '',
				'prefixe' => isset( $base['prefixe'] ) && is_string( $base['prefixe'] ) ? $base['prefixe'] : '',
				'charset' => isset( $base['charset'] ) && is_string( $base['charset'] ) ? $base['charset'] : '',
				'octets'  => isset( $base['octets'] ) ? (int) $base['octets'] : 0,
			),
			'tables'                  => $tables,
			'lignes'                  => isset( $base['lignes'] ) ? (int) $base['lignes'] : 0,
			'lignes_exclues_ignorees' => $ignorees_lignes,
			'fichiers'                => array(
				'nombre'  => isset( $fichiers['nombre'] ) ? (int) $fichiers['nombre'] : 0,
				'octets'  => isset( $fichiers['octets'] ) ? (int) $fichiers['octets'] : 0,
				'racines' => isset( $fichiers['racines'] ) && is_array( $fichiers['racines'] ) ? array_keys( $fichiers['racines'] ) : array(),
				'ignores' => $ignores_fichiers,
			),
		);
	}

	/**
	 * Écrit le manifeste sur disque.
	 *
	 * @param string               $chemin    Chemin du fichier.
	 * @param array<string, mixed> $manifeste Manifeste composé.
	 */
	public static function ecrire( string $chemin, array $manifeste ): bool {
		$json = wp_json_encode( $manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $json ) ) {
			return false;
		}

		$flux = fopen( $chemin, 'wb' );

		if ( false === $flux ) {
			return false;
		}

		$ecrits = fwrite( $flux, $json . "\n" );

		fclose( $flux );

		return false !== $ecrits && $ecrits === strlen( $json ) + 1;
	}

	/**
	 * Relit un manifeste depuis son contenu JSON.
	 *
	 * @param string $json Contenu du fichier.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function depuis_json( string $json ): array|WP_Error {
		$decode = json_decode( $json, true );

		if ( ! is_array( $decode ) || ! isset( $decode['format'] ) ) {
			return new WP_Error( 'massifs_sauvegarde_manifeste', 'Manifeste illisible : ce n\'est pas un manifeste MASSIFS.' );
		}

		if ( (int) $decode['format'] > self::FORMAT ) {
			return new WP_Error(
				'massifs_sauvegarde_manifeste',
				'Manifeste au format ' . (int) $decode['format'] . ', que cette version ne sait pas relire.'
			);
		}

		return $decode;
	}

	/**
	 * Relit le manifeste embarqué dans une archive.
	 *
	 * @param string $chemin_archive Chemin de l'archive.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function depuis_archive( string $chemin_archive ): array|WP_Error {
		$contenu = Archiveur::lire_entree( $chemin_archive, self::NOM_FICHIER );

		if ( is_wp_error( $contenu ) ) {
			return $contenu;
		}

		return self::depuis_json( $contenu );
	}
}
