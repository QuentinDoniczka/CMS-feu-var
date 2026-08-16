<?php
/**
 * Périmètre fichiers : racines, exclusions, refus des liens symboliques, plafond
 * de taille.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  LE RÉPERTOIRE D'ARCHIVES EST EXCLU EN DUR ET N'EST PAS FILTRABLE.            │
 * │                                                                               │
 * │  UNE SAUVEGARDE QUI CONTIENT LES PRÉCÉDENTES DOUBLE DE TAILLE À CHAQUE        │
 * │  EXÉCUTION. AU BOUT DE DIX PASSAGES, LE DISQUE EST PLEIN ET PLUS AUCUNE       │
 * │  SAUVEGARDE N'ABOUTIT — Y COMPRIS CELLE DONT ON AURA BESOIN. C'EST UNE        │
 * │  GARDE, PAS UN RÉGLAGE : AUCUN FILTRE NE PEUT LA LEVER.                       │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI LES LIENS SYMBOLIQUES SONT REFUSÉS, ET NON SUIVIS
 *
 * Un lien suivi peut sortir de la racine déclarée — `uploads/tout` pointant sur
 * `/` embarquerait le système de fichiers entier, et un lien circulaire ferait
 * tourner le parcours jusqu'à l'épuisement du disque. Refuser est le seul
 * comportement dont on puisse énoncer la borne. Chaque refus est NOMMÉ dans le
 * manifeste : une omission silencieuse ferait croire à une archive exhaustive.
 *
 * POURQUOI UN FICHIER TROP GROS EST IGNORÉ ET NON FATAL
 *
 * Mieux vaut une archive dont on sait précisément ce qu'elle omet qu'une
 * sauvegarde qui n'aboutit pas du tout. L'omission descend jusqu'à
 * `complet:false`, donc jusqu'au code de retour.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collecte des fichiers non versionnés à embarquer.
 */
final class Fichiers {

	/**
	 * Préfixe des entrées de fichiers dans l'archive.
	 */
	public const PREFIXE_ARCHIVE = 'fichiers';

	/**
	 * Nombre maximal de fichiers collectés.
	 *
	 * Borne de sûreté, pas de réglage : au-delà, la liste d'entrées ne tient plus
	 * en mémoire et le moteur ZIP s'écroule. Le dépassement est NOMMÉ dans le
	 * manifeste et rend l'archive incomplète.
	 */
	private const PLAFOND_FICHIERS = 200000;

	/**
	 * Collecte le périmètre fichiers.
	 *
	 * @return array{entrees:array<string,string>,nombre:int,octets:int,ignores:list<string>,racines:array<string,string>,complet:bool}
	 */
	public static function collecter(): array {
		$racines    = Reglages::racines_fichiers();
		$exclusions = Reglages::exclusions_fichiers();
		$plafond    = Reglages::taille_max_fichier();
		$archives   = self::repertoire_archives();

		$entrees  = array();
		$ignores  = array();
		$octets   = 0;
		$retenues = array();
		$complet  = true;

		foreach ( $racines as $etiquette => $racine ) {
			$reel = realpath( $racine );

			if ( false === $reel || ! is_dir( $reel ) ) {
				$ignores[] = 'racine absente : ' . $etiquette;

				continue;
			}

			$reel = rtrim( wp_normalize_path( $reel ), '/' );

			// La racine elle-même peut être un lien, et le parcours n'en saurait rien.
			if ( is_link( $racine ) ) {
				$ignores[] = 'racine ignorée (lien symbolique) : ' . $etiquette;
				$complet   = false;

				continue;
			}

			$retenues[ $etiquette ] = $reel;

			$iterateur = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $reel, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterateur as $element ) {
				$chemin = rtrim( wp_normalize_path( (string) $element->getPathname() ), '/' );

				// REFUS, JAMAIS SUIVI.
				if ( $element->isLink() ) {
					$ignores[] = 'lien symbolique ignoré : ' . self::relatif( $etiquette, $reel, $chemin );
					$complet   = false;

					continue;
				}

				if ( $element->isDir() ) {
					continue;
				}

				if ( ! $element->isFile() || ! $element->isReadable() ) {
					$ignores[] = 'fichier illisible : ' . self::relatif( $etiquette, $reel, $chemin );
					$complet   = false;

					continue;
				}

				// GARDE EN DUR : rien de ce qui vit sous le répertoire d'archives
				// n'entre dans une archive, quelle que soit la racine déclarée.
				if ( '' !== $archives && ( $chemin === $archives || str_starts_with( $chemin, $archives . '/' ) ) ) {
					continue;
				}

				$relatif = self::relatif( $etiquette, $reel, $chemin );

				if ( self::exclu( $relatif, $exclusions ) ) {
					continue;
				}

				$taille = (int) $element->getSize();

				if ( $taille > $plafond ) {
					$ignores[] = 'fichier trop volumineux (' . $taille . ' octets) : ' . $relatif;
					$complet   = false;

					continue;
				}

				if ( count( $entrees ) >= self::PLAFOND_FICHIERS ) {
					$ignores[] = 'plafond de ' . self::PLAFOND_FICHIERS . ' fichiers atteint : collecte tronquée';
					$complet   = false;

					break 2;
				}

				$entrees[ self::PREFIXE_ARCHIVE . '/' . $relatif ] = $chemin;
				$octets                                           += $taille;
			}
		}

		return array(
			'entrees' => $entrees,
			'nombre'  => count( $entrees ),
			'octets'  => $octets,
			'ignores' => $ignores,
			'racines' => $retenues,
			'complet' => $complet,
		);
	}

	/**
	 * Racines de restauration, `étiquette => chemin absolu`.
	 *
	 * @return array<string, string>
	 */
	public static function racines(): array {
		return Reglages::racines_fichiers();
	}

	/**
	 * Un chemin relatif est-il exclu ?
	 *
	 * @param string       $relatif    Chemin `<étiquette>/<relatif>`.
	 * @param list<string> $exclusions Motifs à jokers.
	 */
	public static function exclu( string $relatif, array $exclusions ): bool {
		foreach ( $exclusions as $motif ) {
			if ( self::correspond( $motif, $relatif ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compare un chemin à un motif à jokers.
	 *
	 * `fnmatch()` n'est pas disponible sur toutes les plateformes — notamment sur
	 * certaines constructions Windows — et son comportement vis-à-vis de `/` dépend
	 * de drapeaux. Une conversion explicite en expression régulière donne la même
	 * sémantique partout, ce qui compte quand la même archive doit être écrite ici
	 * et relue là-bas.
	 *
	 * @param string $motif  Motif à jokers `*` et `?`.
	 * @param string $chemin Chemin relatif.
	 */
	public static function correspond( string $motif, string $chemin ): bool {
		$regex = '';

		foreach ( str_split( $motif ) as $caractere ) {
			if ( '*' === $caractere ) {
				$regex .= '.*';
			} elseif ( '?' === $caractere ) {
				$regex .= '.';
			} else {
				$regex .= preg_quote( $caractere, '#' );
			}
		}

		return 1 === preg_match( '#^' . $regex . '$#u', $chemin );
	}

	/**
	 * Chemin d'archive d'un fichier local.
	 *
	 * @param string $etiquette Étiquette de racine.
	 * @param string $racine    Racine réelle.
	 * @param string $chemin    Chemin absolu du fichier.
	 */
	private static function relatif( string $etiquette, string $racine, string $chemin ): string {
		$reste = ltrim( substr( $chemin, strlen( $racine ) ), '/' );

		return $etiquette . ( '' === $reste ? '' : '/' . $reste );
	}

	/**
	 * Répertoire d'archives résolu, ou chaîne vide s'il n'existe pas encore.
	 */
	private static function repertoire_archives(): string {
		$reel = realpath( Reglages::repertoire() );

		return false === $reel ? '' : rtrim( wp_normalize_path( $reel ), '/' );
	}
}
