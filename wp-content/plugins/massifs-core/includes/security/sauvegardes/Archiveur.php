<?php
/**
 * Abstraction du format ZIP : `ZipArchive` quand l'extension est là, `PclZip`
 * sinon.
 *
 * POURQUOI `PclZip` EST UN REPLI ACCEPTABLE ET PAS UNE DÉPENDANCE TIERCE
 *
 * `wp-admin/includes/class-pclzip.php` est EMBARQUÉ PAR LE CŒUR de WordPress : il
 * est présent sur toute installation, à toute version, sans installation ni
 * `composer require`. La contrainte n° 1 du projet — surface d'extensions tierces
 * à zéro — est donc tenue dans les deux branches. C'est aussi la raison pour
 * laquelle aucun `mysqldump`, aucun `exec()` et aucune bibliothèque d'archivage
 * n'apparaît nulle part dans ce module.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  LE NOM D'UNE ENTRÉE DANS L'ARCHIVE CONSERVE TOUJOURS LE NOM DE BASE DU       │
 * │  FICHIER LOCAL.                                                               │
 * │                                                                               │
 * │  `PclZip` NE SAIT PAS RENOMMER UNE ENTRÉE : IL SAIT SEULEMENT RETIRER UN      │
 * │  PRÉFIXE DE CHEMIN ET EN AJOUTER UN AUTRE. LES DEUX MOTEURS NE PRODUIRAIENT   │
 * │  DONC PAS LA MÊME ARCHIVE SI UN APPELANT RENOMMAIT — ET LA DIVERGENCE NE SE   │
 * │  VERRAIT QU'À LA RESTAURATION, SUR L'HÔTE OÙ L'AUTRE MOTEUR EST INSTALLÉ.     │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * PROTECTION « ZIP SLIP » À L'EXTRACTION
 *
 * Un nom d'entrée est une donnée ARBITRAIRE, y compris dans une archive qu'on
 * croit avoir écrite soi-même : une archive déposée dans le répertoire par un
 * tiers serait extraite avec les droits du serveur web. Toute entrée absolue,
 * porteuse de `..`, d'une lettre de lecteur ou d'une contre-oblique est refusée
 * AVANT extraction, et le refus arrête l'extraction entière.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use PclZip;
use WP_Error;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Création, lecture et extraction d'archives ZIP.
 */
final class Archiveur {

	/**
	 * Moteur retenu : `ziparchive` ou `pclzip`.
	 */
	public static function moteur(): string {
		return class_exists( ZipArchive::class ) ? 'ziparchive' : 'pclzip';
	}

	/**
	 * Crée une archive.
	 *
	 * @param string                $chemin_zip Chemin de l'archive à créer.
	 * @param array<string, string> $entrees    `nom dans l'archive => chemin local`.
	 *
	 * @return true|WP_Error
	 */
	public static function creer( string $chemin_zip, array $entrees ): true|WP_Error {
		if ( array() === $entrees ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Aucune entrée à archiver.' );
		}

		foreach ( $entrees as $nom => $local ) {
			if ( basename( $nom ) !== basename( $local ) ) {
				return new WP_Error(
					'massifs_sauvegarde_archive',
					'Nom d\'entrée incompatible avec le repli PclZip : ' . $nom . '.'
				);
			}
		}

		if ( 'ziparchive' === self::moteur() ) {
			return self::creer_ziparchive( $chemin_zip, $entrees );
		}

		return self::creer_pclzip( $chemin_zip, $entrees );
	}

	/**
	 * Lit une entrée de l'archive et la rend en mémoire.
	 *
	 * Réservé aux petites entrées — le manifeste. Jamais utilisé sur `base.sql`,
	 * qui est extrait vers un fichier puis relu en flux.
	 *
	 * @param string $chemin_zip Chemin de l'archive.
	 * @param string $nom        Nom de l'entrée.
	 *
	 * @return string|WP_Error
	 */
	public static function lire_entree( string $chemin_zip, string $nom ): string|WP_Error {
		if ( ! is_file( $chemin_zip ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Archive introuvable : ' . $chemin_zip . '.' );
		}

		if ( 'ziparchive' === self::moteur() ) {
			$zip = new ZipArchive();

			if ( true !== $zip->open( $chemin_zip ) ) {
				return new WP_Error( 'massifs_sauvegarde_archive', 'Archive illisible : ' . basename( $chemin_zip ) . '.' );
			}

			$contenu = $zip->getFromName( $nom );

			$zip->close();

			if ( ! is_string( $contenu ) ) {
				return new WP_Error( 'massifs_sauvegarde_archive', 'Entrée absente de l\'archive : ' . $nom . '.' );
			}

			return $contenu;
		}

		self::charger_pclzip();

		$zip     = new PclZip( $chemin_zip );
		$extrait = $zip->extract( PCLZIP_OPT_BY_NAME, $nom, PCLZIP_OPT_EXTRACT_AS_STRING );

		if ( ! is_array( $extrait ) || ! isset( $extrait[0]['content'] ) || ! is_string( $extrait[0]['content'] ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Entrée absente de l\'archive : ' . $nom . '.' );
		}

		return $extrait[0]['content'];
	}

	/**
	 * Liste les noms d'entrées d'une archive.
	 *
	 * @param string $chemin_zip Chemin de l'archive.
	 *
	 * @return list<string>|WP_Error
	 */
	public static function lister( string $chemin_zip ): array|WP_Error {
		if ( ! is_file( $chemin_zip ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Archive introuvable : ' . $chemin_zip . '.' );
		}

		$noms = array();

		if ( 'ziparchive' === self::moteur() ) {
			$zip = new ZipArchive();

			if ( true !== $zip->open( $chemin_zip ) ) {
				return new WP_Error( 'massifs_sauvegarde_archive', 'Archive illisible : ' . basename( $chemin_zip ) . '.' );
			}

			for ( $index = 0; $index < $zip->numFiles; $index++ ) {
				$nom = $zip->getNameIndex( $index );

				if ( is_string( $nom ) ) {
					$noms[] = $nom;
				}
			}

			$zip->close();

			return $noms;
		}

		self::charger_pclzip();

		$zip     = new PclZip( $chemin_zip );
		$entrees = $zip->listContent();

		if ( ! is_array( $entrees ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Archive illisible : ' . basename( $chemin_zip ) . '.' );
		}

		foreach ( $entrees as $entree ) {
			if ( is_array( $entree ) && isset( $entree['stored_filename'] ) && is_string( $entree['stored_filename'] ) ) {
				$noms[] = $entree['stored_filename'];
			}
		}

		return $noms;
	}

	/**
	 * Extrait tout ou partie d'une archive vers un répertoire.
	 *
	 * @param string            $chemin_zip  Chemin de l'archive.
	 * @param string            $destination Répertoire de destination, existant.
	 * @param list<string>|null $entrees     Entrées à extraire, `null` pour tout.
	 *
	 * @return true|WP_Error
	 */
	public static function extraire( string $chemin_zip, string $destination, ?array $entrees = null ): true|WP_Error {
		if ( ! is_dir( $destination ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Répertoire de destination absent : ' . $destination . '.' );
		}

		$noms = self::lister( $chemin_zip );

		if ( is_wp_error( $noms ) ) {
			return $noms;
		}

		foreach ( $noms as $nom ) {
			if ( ! self::nom_sur( $nom ) ) {
				return new WP_Error(
					'massifs_sauvegarde_archive',
					'Archive refusée : elle contient une entrée de chemin dangereux (« ' . $nom . ' »).'
				);
			}
		}

		if ( 'ziparchive' === self::moteur() ) {
			$zip = new ZipArchive();

			if ( true !== $zip->open( $chemin_zip ) ) {
				return new WP_Error( 'massifs_sauvegarde_archive', 'Archive illisible : ' . basename( $chemin_zip ) . '.' );
			}

			$succes = null === $entrees ? $zip->extractTo( $destination ) : $zip->extractTo( $destination, $entrees );

			$zip->close();

			if ( true !== $succes ) {
				return new WP_Error( 'massifs_sauvegarde_archive', 'Extraction refusée par le moteur ZIP.' );
			}

			return true;
		}

		self::charger_pclzip();

		$zip = new PclZip( $chemin_zip );

		if ( null === $entrees ) {
			$resultat = $zip->extract( PCLZIP_OPT_PATH, $destination );
		} else {
			$resultat = $zip->extract( PCLZIP_OPT_PATH, $destination, PCLZIP_OPT_BY_NAME, $entrees );
		}

		if ( ! is_array( $resultat ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Extraction refusée par PclZip : ' . (string) $zip->errorInfo( true ) . '.' );
		}

		return true;
	}

	/**
	 * Crée l'archive avec `ZipArchive`.
	 *
	 * @param string                $chemin_zip Chemin de l'archive.
	 * @param array<string, string> $entrees    `nom dans l'archive => chemin local`.
	 *
	 * @return true|WP_Error
	 */
	private static function creer_ziparchive( string $chemin_zip, array $entrees ): true|WP_Error {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $chemin_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Impossible de créer l\'archive : ' . basename( $chemin_zip ) . '.' );
		}

		foreach ( $entrees as $nom => $local ) {
			if ( ! $zip->addFile( $local, $nom ) ) {
				$zip->close();

				return new WP_Error( 'massifs_sauvegarde_archive', 'Ajout refusé pour l\'entrée ' . $nom . '.' );
			}
		}

		if ( ! $zip->close() ) {
			return new WP_Error( 'massifs_sauvegarde_archive', 'Fermeture de l\'archive refusée : le contenu n\'est pas garanti.' );
		}

		return true;
	}

	/**
	 * Crée l'archive avec `PclZip`.
	 *
	 * Un ajout par entrée : c'est le seul moyen de contrôler le chemin interne
	 * de chacune sans supposer une racine commune, que `uploads/` et `data/`
	 * n'ont pas.
	 *
	 * @param string                $chemin_zip Chemin de l'archive.
	 * @param array<string, string> $entrees    `nom dans l'archive => chemin local`.
	 *
	 * @return true|WP_Error
	 */
	private static function creer_pclzip( string $chemin_zip, array $entrees ): true|WP_Error {
		self::charger_pclzip();

		$zip     = new PclZip( $chemin_zip );
		$premier = true;

		foreach ( $entrees as $nom => $local ) {
			$dossier_interne = ltrim( str_replace( '\\', '/', dirname( $nom ) ), '.' );
			$dossier_interne = '/' === $dossier_interne ? '' : trim( $dossier_interne, '/' );

			$arguments = array(
				$local,
				PCLZIP_OPT_REMOVE_ALL_PATH,
			);

			if ( '' !== $dossier_interne ) {
				$arguments[] = PCLZIP_OPT_ADD_PATH;
				$arguments[] = $dossier_interne;
			}

			$resultat = $premier
				? $zip->create( ...$arguments )
				: $zip->add( ...$arguments );

			if ( ! is_array( $resultat ) || array() === $resultat ) {
				return new WP_Error(
					'massifs_sauvegarde_archive',
					'PclZip a refusé l\'entrée ' . $nom . ' : ' . (string) $zip->errorInfo( true ) . '.'
				);
			}

			$premier = false;
		}

		return true;
	}

	/**
	 * Un nom d'entrée est-il sûr à extraire ?
	 *
	 * @param string $nom Nom d'entrée tel qu'il est stocké dans l'archive.
	 */
	private static function nom_sur( string $nom ): bool {
		if ( '' === $nom ) {
			return false;
		}

		if ( str_contains( $nom, '\\' ) || str_contains( $nom, "\0" ) ) {
			return false;
		}

		if ( str_starts_with( $nom, '/' ) || 1 === preg_match( '/^[A-Za-z]:/', $nom ) ) {
			return false;
		}

		foreach ( explode( '/', $nom ) as $segment ) {
			if ( '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Charge `PclZip`, livré par le cœur de WordPress.
	 */
	private static function charger_pclzip(): void {
		if ( ! class_exists( PclZip::class ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}
	}
}
