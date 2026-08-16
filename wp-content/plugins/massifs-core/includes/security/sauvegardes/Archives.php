<?php
/**
 * Cycle de vie d'une archive : nommage, création, listage, rotation, et surtout
 * ÉCRITURE ATOMIQUE.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  UNE ARCHIVE À MOITIÉ ÉCRITE NE PORTE JAMAIS UN NOM VALIDE.                   │
 * │                                                                               │
 * │  TOUT S'ÉCRIT SOUS `.tmp-<uuid>`, ET LE `rename()` FINAL EST LE SEUL INSTANT  │
 * │  OÙ L'ARCHIVE DEVIENT VISIBLE. SANS CELA, UN PROCESSUS TUÉ AU MILIEU D'UN     │
 * │  ZIP LAISSERAIT UN FICHIER AU BON NOM, DE LA BONNE FORME, ET IRRESTAURABLE —  │
 * │  ET LA ROTATION AURAIT DÉJÀ SUPPRIMÉ LA PLUS ANCIENNE ARCHIVE VALIDE POUR     │
 * │  LUI FAIRE DE LA PLACE. LA ROTATION NE S'EXÉCUTE QU'APRÈS UN `rename` RÉUSSI. │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI UN SUFFIXE ALÉATOIRE DE 8 HEXADÉCIMAUX DANS LE NOM
 *
 * Le répertoire par défaut vit sous la racine web (compromis A-5, voir README).
 * Un nom entièrement dérivé de la date serait DEVINABLE : il suffirait d'essayer
 * les 365 noms de l'année pour tenter d'aspirer une archive contenant des
 * hachages de mots de passe. Le suffixe ne remplace aucune des quatre
 * protections — il retire seulement l'énumération triviale.
 *
 * POURQUOI LE NOM EST HORODATÉ EN UTC
 *
 * Deux archives prises de part et d'autre d'un changement d'heure doivent rester
 * ordonnables par leur nom. Un horodatage local se répète une heure par an.
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
 * Nommage, création, listage et rotation des archives.
 */
final class Archives {

	/**
	 * Expression de nommage d'une archive.
	 *
	 * OPPOSÉE À TOUTE ENTRÉE AVANT LE MOINDRE ACCÈS DISQUE. Un nom qui ne la
	 * satisfait pas n'est jamais concaténé à un chemin : c'est ce qui rend
	 * impossible `wp massifs sauvegarde inspecter ../../wp-config.php`.
	 */
	public const MOTIF_NOM = '/^massifs-(sauvegarde|filet)-\d{8}-\d{6}-[0-9a-f]{8}\.zip$/';

	/**
	 * Genre « sauvegarde ordinaire ».
	 */
	public const GENRE_SAUVEGARDE = 'sauvegarde';

	/**
	 * Genre « filet avant restauration ».
	 */
	public const GENRE_FILET = 'filet';

	/**
	 * Répertoire d'archives, créé si nécessaire.
	 *
	 * @return string|WP_Error Chemin réel, sans barre oblique finale.
	 */
	public static function repertoire(): string|WP_Error {
		$repertoire = Reglages::repertoire();

		if ( ! is_dir( $repertoire ) && ! wp_mkdir_p( $repertoire ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire d\'archives introuvable et non créable : ' . $repertoire . '.' );
		}

		$reel = realpath( $repertoire );

		if ( false === $reel ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire d\'archives non résolvable : ' . $repertoire . '.' );
		}

		if ( ! is_writable( $reel ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire d\'archives non inscriptible : ' . $reel . '.' );
		}

		return rtrim( wp_normalize_path( $reel ), '/' );
	}

	/**
	 * Compose un nom d'archive.
	 *
	 * @param string $genre Genre d'archive.
	 */
	public static function nommer( string $genre ): string {
		$genre = self::GENRE_FILET === $genre ? self::GENRE_FILET : self::GENRE_SAUVEGARDE;

		return sprintf(
			'massifs-%s-%s-%s.zip',
			$genre,
			gmdate( 'Ymd-His' ),
			bin2hex( random_bytes( 4 ) )
		);
	}

	/**
	 * Résout et confine un nom d'archive fourni de l'extérieur.
	 *
	 * @param string $nom Nom d'archive.
	 *
	 * @return string|WP_Error Chemin absolu confiné.
	 */
	public static function chemin( string $nom ): string|WP_Error {
		$nom = trim( $nom );

		// Un nom d'archive peut arriver avec un chemin complet : on n'en garde que
		// le nom de base, et il doit satisfaire l'expression de nommage AVANT le
		// moindre accès disque.
		$nom = basename( str_replace( '\\', '/', $nom ) );

		if ( 1 !== preg_match( self::MOTIF_NOM, $nom ) ) {
			return new WP_Error( 'massifs_sauvegarde_nom', 'Nom d\'archive refusé : ' . $nom . '.' );
		}

		$repertoire = self::repertoire();

		if ( is_wp_error( $repertoire ) ) {
			return $repertoire;
		}

		$chemin = $repertoire . '/' . $nom;

		if ( ! is_file( $chemin ) ) {
			return new WP_Error( 'massifs_sauvegarde_absente', 'Archive introuvable : ' . $nom . '.' );
		}

		$reel = realpath( $chemin );

		// Confinement final : après `realpath`, l'archive doit toujours être SOUS le
		// répertoire résolu. Un lien symbolique déposé dans le répertoire pourrait
		// sinon faire lire — puis extraire — un fichier d'ailleurs.
		if ( false === $reel || ! str_starts_with( rtrim( wp_normalize_path( $reel ), '/' ), $repertoire . '/' ) ) {
			return new WP_Error( 'massifs_sauvegarde_nom', 'Archive hors du répertoire d\'archives : ' . $nom . '.' );
		}

		return rtrim( wp_normalize_path( $reel ), '/' );
	}

	/**
	 * Crée une archive.
	 *
	 * @param array{sans_base?:bool,sans_fichiers?:bool,genre?:string} $options Options de création.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function creer( array $options = array() ): array|WP_Error {
		$rapport = self::produire( $options );

		if ( is_wp_error( $rapport ) ) {
			Journal::consigner(
				'creation_echouee',
				array(
					'code'    => $rapport->get_error_code(),
					'message' => $rapport->get_error_message(),
				)
			);

			/**
			 * Signale l'échec d'une sauvegarde.
			 *
			 * @param WP_Error $erreur Erreur remontée par le moteur.
			 */
			do_action( 'massifs_sauvegarde_echouee', $rapport );

			return $rapport;
		}

		Journal::consigner(
			'creation_terminee',
			array(
				'nom'     => $rapport['nom'],
				'octets'  => $rapport['octets'],
				'complet' => $rapport['complet'],
				'lignes'  => $rapport['lignes'],
			)
		);

		/**
		 * Signale la fin d'une sauvegarde.
		 *
		 * @param array<string, mixed> $rapport Rapport de création.
		 */
		do_action( 'massifs_sauvegarde_terminee', $rapport );

		return $rapport;
	}

	/**
	 * Liste les archives présentes.
	 *
	 * @param bool $inclure_filets Inclure les archives « avant restauration » ?
	 *
	 * @return list<array{nom:string,chemin:string,genre:string,genere_le:string,octets:int,complet:bool}>
	 */
	public static function lister( bool $inclure_filets = false ): array {
		$repertoire = self::repertoire();

		if ( is_wp_error( $repertoire ) ) {
			return array();
		}

		$entrees = scandir( $repertoire );

		if ( false === $entrees ) {
			return array();
		}

		$liste = array();

		foreach ( $entrees as $entree ) {
			if ( 1 !== preg_match( self::MOTIF_NOM, $entree, $trouve ) ) {
				continue;
			}

			if ( self::GENRE_FILET === $trouve[1] && ! $inclure_filets ) {
				continue;
			}

			$chemin = $repertoire . '/' . $entree;

			if ( ! is_file( $chemin ) ) {
				continue;
			}

			$taille    = filesize( $chemin );
			$manifeste = Manifeste::depuis_archive( $chemin );

			$liste[] = array(
				'nom'       => $entree,
				'chemin'    => $chemin,
				'genre'     => $trouve[1],
				'genere_le' => is_wp_error( $manifeste ) ? self::date_du_nom( $entree ) : (string) ( $manifeste['genere_le'] ?? self::date_du_nom( $entree ) ),
				'octets'    => false === $taille ? 0 : (int) $taille,

				// UNE ARCHIVE DONT LE MANIFESTE EST ILLISIBLE N'EST PAS COMPLÈTE.
				// Le doute se résout toujours dans le sens qui n'endort pas.
				'complet'   => ! is_wp_error( $manifeste ) && true === ( $manifeste['complet'] ?? false ),
			);
		}

		// Tri décroissant sur le nom : l'horodatage UTC en tête le rend équivalent
		// à un tri chronologique, sans dépendre d'un `mtime` que `rsync` réécrit.
		usort(
			$liste,
			static function ( array $gauche, array $droite ): int {
				return strcmp( $droite['nom'], $gauche['nom'] );
			}
		);

		return $liste;
	}

	/**
	 * Applique la rotation.
	 *
	 * @param int|null $garder  Nombre de sauvegardes conservées, `null` pour le réglage.
	 * @param bool     $simuler Ne rien supprimer, seulement énumérer ?
	 *
	 * @return array{supprimees:list<string>,conservees:int}
	 */
	public static function purger( ?int $garder = null, bool $simuler = false ): array {
		$supprimees = array();
		$conservees = 0;

		// Un seul listage pour les deux genres : `lister()` ouvre le manifeste de
		// CHAQUE archive, et la rotation s'exécute après chaque sauvegarde.
		$toutes = self::lister( true );
		$jours  = Reglages::retention_jours();
		$limite = $jours > 0 ? time() - ( $jours * DAY_IN_SECONDS ) : 0;

		foreach ( array( self::GENRE_SAUVEGARDE, self::GENRE_FILET ) as $genre ) {
			$plafond = self::GENRE_FILET === $genre
				? Reglages::retention_filets()
				: ( null === $garder ? Reglages::retention_nombre() : max( 1, $garder ) );

			$archives = array_values(
				array_filter(
					$toutes,
					static function ( array $archive ) use ( $genre ): bool {
						return $archive['genre'] === $genre;
					}
				)
			);

			foreach ( $archives as $rang => $archive ) {
				$trop_nombreuse = $rang >= $plafond;
				$trop_vieille   = $limite > 0 && self::instant_du_nom( $archive['nom'] ) < $limite;

				if ( ! $trop_nombreuse && ! $trop_vieille ) {
					++$conservees;

					continue;
				}

				$supprimees[] = $archive['nom'];

				if ( ! $simuler ) {
					wp_delete_file( $archive['chemin'] );
				}
			}
		}

		if ( ! $simuler && array() !== $supprimees ) {
			Journal::consigner( 'rotation', array( 'supprimees' => count( $supprimees ) ) );
		}

		return array(
			'supprimees' => $supprimees,
			'conservees' => $conservees,
		);
	}

	/**
	 * Produit l'archive, sans journalisation ni action.
	 *
	 * @param array<string, mixed> $options Options de création.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function produire( array $options ): array|WP_Error {
		$sans_base     = true === ( $options['sans_base'] ?? false );
		$sans_fichiers = true === ( $options['sans_fichiers'] ?? false );
		$genre         = self::GENRE_FILET === ( $options['genre'] ?? '' ) ? self::GENRE_FILET : self::GENRE_SAUVEGARDE;

		if ( $sans_base && $sans_fichiers ) {
			return new WP_Error( 'massifs_sauvegarde_perimetre', 'Périmètre vide : ni base ni fichiers. Rien à sauvegarder.' );
		}

		$repertoire = self::repertoire();

		if ( is_wp_error( $repertoire ) ) {
			return $repertoire;
		}

		$uuid    = wp_generate_uuid4();
		$travail = $repertoire . '/.tmp-' . $uuid;

		if ( ! wp_mkdir_p( $travail ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire de travail non créable.' );
		}

		$base     = array();
		$fichiers = array();
		$entrees  = array();

		if ( ! $sans_base ) {
			$chemin_sql = $travail . '/' . DumpSql::NOM_FICHIER;
			$base       = DumpSql::ecrire( $chemin_sql );

			if ( is_wp_error( $base ) ) {
				self::nettoyer( $travail );

				return $base;
			}

			$entrees[ DumpSql::NOM_FICHIER ] = $chemin_sql;
		}

		if ( ! $sans_fichiers ) {
			$fichiers = Fichiers::collecter();
			$entrees  = array_merge( $entrees, $fichiers['entrees'] );
		}

		$manifeste        = Manifeste::composer( $base, $fichiers, $genre );
		$chemin_manifeste = $travail . '/' . Manifeste::NOM_FICHIER;

		if ( ! Manifeste::ecrire( $chemin_manifeste, $manifeste ) ) {
			self::nettoyer( $travail );

			return new WP_Error( 'massifs_sauvegarde_manifeste', 'Écriture du manifeste impossible.' );
		}

		// Le manifeste en tête : une archive tronquée à la lecture doit au moins
		// livrer ce qu'elle prétendait contenir.
		$entrees = array( Manifeste::NOM_FICHIER => $chemin_manifeste ) + $entrees;

		$temporaire = $repertoire . '/.tmp-' . $uuid . '.zip';
		$archivage  = Archiveur::creer( $temporaire, $entrees );

		if ( is_wp_error( $archivage ) ) {
			self::nettoyer( $travail );
			self::nettoyer_fichier( $temporaire );

			return $archivage;
		}

		$nom    = self::nommer( $genre );
		$chemin = $repertoire . '/' . $nom;

		// L'INSTANT ATOMIQUE. Avant lui, rien ne porte un nom valide ; après lui,
		// tout est là.
		if ( ! rename( $temporaire, $chemin ) ) {
			self::nettoyer( $travail );
			self::nettoyer_fichier( $temporaire );

			return new WP_Error( 'massifs_sauvegarde_publication', 'Publication de l\'archive impossible : le renommage a échoué.' );
		}

		self::nettoyer( $travail );

		// LA ROTATION SEULEMENT MAINTENANT. Purger avant le `rename` reviendrait à
		// détruire une archive valide pour faire place à une archive qui n'existe
		// pas encore.
		self::purger();

		$taille = filesize( $chemin );

		return array(
			'nom'       => $nom,
			'chemin'    => $chemin,
			'genre'     => $genre,
			'octets'    => false === $taille ? 0 : (int) $taille,
			'complet'   => true === $manifeste['complet'],
			'tables'    => count( $manifeste['tables'] ),
			'lignes'    => (int) $manifeste['lignes'],
			'fichiers'  => (int) $manifeste['fichiers']['nombre'],
			'genere_le' => (string) $manifeste['genere_le'],
			'manifeste' => $manifeste,
		);
	}

	/**
	 * Instant UTC dérivé du nom d'archive.
	 *
	 * @param string $nom Nom d'archive.
	 */
	private static function instant_du_nom( string $nom ): int {
		if ( 1 !== preg_match( '/-(\d{8})-(\d{6})-/', $nom, $trouve ) ) {
			return 0;
		}

		$instant = strtotime( $trouve[1] . 'T' . substr( $trouve[2], 0, 2 ) . ':' . substr( $trouve[2], 2, 2 ) . ':' . substr( $trouve[2], 4, 2 ) . '+00:00' );

		return false === $instant ? 0 : $instant;
	}

	/**
	 * Date ISO dérivée du nom d'archive, repli quand le manifeste est illisible.
	 *
	 * @param string $nom Nom d'archive.
	 */
	private static function date_du_nom( string $nom ): string {
		$instant = self::instant_du_nom( $nom );

		return 0 === $instant ? '' : gmdate( 'c', $instant );
	}

	/**
	 * Supprime un répertoire de travail et son contenu.
	 *
	 * Aucune récursion générale : le répertoire de travail n'a qu'un niveau, et
	 * une suppression récursive écrite « au cas où » finit toujours par être
	 * appelée sur autre chose que ce qu'elle attendait.
	 *
	 * @param string $repertoire Répertoire de travail.
	 */
	private static function nettoyer( string $repertoire ): void {
		if ( ! is_dir( $repertoire ) || ! str_contains( basename( $repertoire ), '.tmp-' ) ) {
			return;
		}

		foreach ( array( DumpSql::NOM_FICHIER, Manifeste::NOM_FICHIER ) as $nom ) {
			self::nettoyer_fichier( $repertoire . '/' . $nom );
		}

		$restants = array_diff( (array) scandir( $repertoire ), array( '.', '..' ) );

		// Retrait conditionné plutôt que forcé : un répertoire de travail qui n'est
		// pas vide signale un fichier inattendu, et l'effacer à l'aveugle
		// effacerait la seule trace de l'anomalie.
		if ( array() === $restants ) {
			rmdir( $repertoire );
		}
	}

	/**
	 * Supprime un fichier temporaire.
	 *
	 * @param string $chemin Chemin du fichier.
	 */
	private static function nettoyer_fichier( string $chemin ): void {
		if ( is_file( $chemin ) ) {
			wp_delete_file( $chemin );
		}
	}
}
