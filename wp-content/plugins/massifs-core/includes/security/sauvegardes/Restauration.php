<?php
/**
 * Rejeu d'une archive : gardes de cible, filet, relecture stricte du dump.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  LA GARDE DE CIBLE EST DANS LE SERVICE, PAS SEULEMENT DANS LA COMMANDE.       │
 * │                                                                               │
 * │  MÊME DOCTRINE QUE `Roles\Comptes` : UN ÉCRAN — OU UNE COMMANDE — EST UNE     │
 * │  AFFORDANCE, PAS UN CONTRÔLE D'ACCÈS. UN CONTRÔLE QUI NE VIT QUE DANS         │
 * │  L'APPELANT DISPARAÎT AU PREMIER APPELANT SUIVANT, ET CELUI-LÀ SERA ÉCRIT     │
 * │  PAR QUELQU'UN QUI N'AURA PAS LU CE FICHIER.                                  │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * LA GRAMMAIRE DU DUMP EST OPPOSABLE, PAS INDICATIVE
 *
 * Ce lecteur ne contient AUCUN analyseur syntaxique SQL, et c'est délibéré. Il
 * découpe sur le saut de ligne et REFUSE le fichier entier si une ligne ne se
 * termine pas par `;`. La sûreté du découpage est achetée par une contrainte sur
 * l'écrivain (`DumpSql`), pas par de l'intelligence ici : un `explode( ';' )`
 * naïf casse sur toute chaîne PHP sérialisée, c'est-à-dire sur presque chaque
 * ligne de `wp_options`. Le seul bloc multiligne admis est la structure, encadrée
 * par deux marqueurs explicites.
 *
 * POURQUOI `wpdb::$check_current_query` EST DÉSARMÉ PENDANT LE REJEU
 *
 * `wpdb::query()` inspecte les `INSERT` non ASCII et les REJETTE lorsqu'il estime
 * ne pas pouvoir garantir le jeu de caractères — comportement salutaire sur une
 * écriture applicative, désastreux ici : le dump est déjà byte-exact et émis par
 * le serveur lui-même. Laisser `wpdb` le réécrire ou le refuser produirait une
 * restauration partielle qui ne dirait pas son nom. Le drapeau est rendu à sa
 * valeur d'origine dans tous les cas.
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
 * Restauration d'une archive.
 */
final class Restauration {

	/**
	 * Code de refus opposé par les gardes de cible.
	 *
	 * Contractuel : la commande le traduit en code de retour 3.
	 */
	public const CODE_REFUS = 'massifs_sauvegarde_refusee';

	/**
	 * Code d'archive incomplète.
	 */
	public const CODE_INCOMPLETE = 'massifs_sauvegarde_incomplete';

	/**
	 * Restaure une archive.
	 *
	 * @param string                                                                                       $nom     Nom de l'archive.
	 * @param array{aveu?:bool,nom_base?:string,sans_filet?:bool,sans_base?:bool,sans_fichiers?:bool,forcer?:bool} $options Options.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function depuis_archive( string $nom, array $options = array() ): array|WP_Error {
		// GARDE DANS LA MÉTHODE, ET PAS SEULEMENT DANS LE MODULE. Une restauration
		// déclenchée depuis une requête web serait, au mieux, un site détruit par un
		// timeout au milieu du rejeu ; au pire, l'arme rêvée d'un compte compromis.
		if ( ! ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) ) {
			return new WP_Error(
				self::CODE_REFUS,
				'La restauration ne s\'exécute que depuis WP-CLI.'
			);
		}

		$garde = self::garde_cible(
			true === ( $options['aveu'] ?? false ),
			isset( $options['nom_base'] ) && is_string( $options['nom_base'] ) ? $options['nom_base'] : ''
		);

		if ( is_wp_error( $garde ) ) {
			return $garde;
		}

		$chemin = Archives::chemin( $nom );

		if ( is_wp_error( $chemin ) ) {
			return $chemin;
		}

		$manifeste = Manifeste::depuis_archive( $chemin );

		if ( is_wp_error( $manifeste ) ) {
			return $manifeste;
		}

		$sans_base     = true === ( $options['sans_base'] ?? false );
		$sans_fichiers = true === ( $options['sans_fichiers'] ?? false );

		if ( true !== ( $manifeste['complet'] ?? false ) && true !== ( $options['forcer'] ?? false ) ) {
			return new WP_Error(
				self::CODE_INCOMPLETE,
				'Archive marquée incomplète. Restauration refusée. Ajouter --forcer pour la rejouer malgré tout, en sachant qu\'il manquera des données.'
			);
		}

		// LE FILET D'ABORD, AVANT LE MOINDRE `DROP TABLE`. Une restauration est
		// irréversible : la seule chose qui la rende réversible est une archive
		// prise juste avant.
		$filet = '';

		if ( true !== ( $options['sans_filet'] ?? false ) ) {
			$avant = Archives::creer(
				array(
					'genre'         => Archives::GENRE_FILET,
					'sans_fichiers' => $sans_fichiers,
					'sans_base'     => $sans_base,
				)
			);

			if ( is_wp_error( $avant ) ) {
				return new WP_Error(
					'massifs_sauvegarde_filet',
					'Filet « avant restauration » impossible : ' . $avant->get_error_message() . ' Restauration abandonnée.'
				);
			}

			$filet = (string) $avant['nom'];
		}

		$travail = dirname( $chemin ) . '/.tmp-' . wp_generate_uuid4();

		if ( ! wp_mkdir_p( $travail ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire de travail non créable.' );
		}

		$rapport = self::rejouer_archive( $chemin, $travail, $manifeste, $sans_base, $sans_fichiers );

		self::nettoyer( $travail );

		if ( is_wp_error( $rapport ) ) {
			Journal::consigner(
				'restauration_echouee',
				array(
					'archive' => basename( $chemin ),
					'code'    => $rapport->get_error_code(),
					'message' => $rapport->get_error_message(),
				)
			);

			return $rapport;
		}

		$rapport['filet']   = $filet;
		$rapport['archive'] = basename( $chemin );

		Journal::consigner(
			'restauration_terminee',
			array(
				'archive'      => $rapport['archive'],
				'filet'        => $filet,
				'instructions' => $rapport['instructions'],
				'fichiers'     => $rapport['fichiers'],
			)
		);

		return $rapport;
	}

	/**
	 * Garde de cible, opposable à tout appelant.
	 *
	 * `local` et `development` passent seuls. Ailleurs, il faut l'aveu explicite
	 * ET le nom de la base saisi à la main, comparé STRICTEMENT : c'est le geste
	 * qui force à regarder sur quelle base on est avant de la détruire.
	 *
	 * @param bool   $aveu     `--je-sais-ce-que-je-fais` a-t-il été passé ?
	 * @param string $nom_base Nom de base saisi à la main.
	 *
	 * @return true|WP_Error
	 */
	public static function garde_cible( bool $aveu, string $nom_base ): true|WP_Error {
		$environnement = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		if ( in_array( $environnement, array( 'local', 'development' ), true ) ) {
			return true;
		}

		if ( ! $aveu ) {
			return new WP_Error(
				self::CODE_REFUS,
				'Environnement « ' . $environnement . ' » : geste destructeur refusé. Il faut --je-sais-ce-que-je-fais ET --nom-base=<nom exact de la base>.'
			);
		}

		if ( '' === trim( $nom_base ) ) {
			return new WP_Error(
				self::CODE_REFUS,
				'--je-sais-ce-que-je-fais exige --nom-base=<nom exact de la base>.'
			);
		}

		if ( ! defined( 'DB_NAME' ) || (string) DB_NAME !== $nom_base ) {
			return new WP_Error(
				self::CODE_REFUS,
				'--nom-base ne correspond pas à la base de cette installation. Geste refusé.'
			);
		}

		return true;
	}

	/**
	 * Rejoue un fichier de dump, instruction par instruction.
	 *
	 * @param string $chemin_sql Chemin du dump.
	 *
	 * @return int|WP_Error Nombre d'instructions exécutées.
	 */
	public static function rejouer( string $chemin_sql ): int|WP_Error {
		global $wpdb;

		$flux = fopen( $chemin_sql, 'rb' );

		if ( false === $flux ) {
			return new WP_Error( 'massifs_sauvegarde_lecture', 'Dump illisible.' );
		}

		$suppression = $wpdb->suppress_errors( false );
		$affichage   = $wpdb->hide_errors();
		$controle    = $wpdb->check_current_query;

		$wpdb->check_current_query = false;

		$instructions   = 0;
		$numero         = 0;
		$dans_structure = false;
		$bloc           = '';
		$erreur         = null;

		while ( false !== ( $ligne = fgets( $flux ) ) ) {
			++$numero;

			$ligne = rtrim( $ligne, "\r\n" );

			if ( $dans_structure ) {
				if ( DumpSql::MARQUEUR_FIN_STRUCTURE === $ligne ) {
					$dans_structure = false;

					if ( ! str_ends_with( rtrim( $bloc ), ';' ) ) {
						$erreur = new WP_Error(
							'massifs_sauvegarde_grammaire',
							'Dump refusé : bloc de structure non terminé par « ; » (ligne ' . $numero . ').'
						);

						break;
					}

					$erreur = self::executer( rtrim( $bloc ), $numero );

					if ( null !== $erreur ) {
						break;
					}

					++$instructions;
					$bloc = '';

					continue;
				}

				$bloc .= $ligne . "\n";

				continue;
			}

			if ( '' === trim( $ligne ) ) {
				continue;
			}

			if ( str_starts_with( $ligne, DumpSql::MARQUEUR_STRUCTURE ) ) {
				$dans_structure = true;
				$bloc           = '';

				continue;
			}

			if ( str_starts_with( $ligne, '--' ) ) {
				continue;
			}

			// LE REFUS EST LA GARANTIE, PAS UNE COURTOISIE. Une ligne non terminée par
			// « ; » signifie que le fichier a été tronqué, réencodé ou réécrit par un
			// outil tiers : le rejouer à moitié laisserait une base ni ancienne ni
			// nouvelle, ce qui est le pire des trois états.
			if ( ! str_ends_with( $ligne, ';' ) ) {
				$erreur = new WP_Error(
					'massifs_sauvegarde_grammaire',
					'Dump refusé : instruction non terminée par « ; » à la ligne ' . $numero . '. Le fichier a été altéré ou tronqué.'
				);

				break;
			}

			$erreur = self::executer( $ligne, $numero );

			if ( null !== $erreur ) {
				break;
			}

			++$instructions;
		}

		fclose( $flux );

		$wpdb->check_current_query = $controle;
		$wpdb->suppress_errors( $suppression );

		if ( $affichage ) {
			$wpdb->show_errors();
		}

		if ( null !== $erreur ) {
			return $erreur;
		}

		if ( $dans_structure ) {
			return new WP_Error( 'massifs_sauvegarde_grammaire', 'Dump refusé : bloc de structure jamais refermé.' );
		}

		wp_cache_flush();

		return $instructions;
	}

	/**
	 * Extrait puis rejoue le contenu d'une archive.
	 *
	 * @param string               $chemin        Chemin de l'archive.
	 * @param string               $travail       Répertoire de travail.
	 * @param array<string, mixed> $manifeste     Manifeste relu.
	 * @param bool                 $sans_base     Ne pas restaurer la base ?
	 * @param bool                 $sans_fichiers Ne pas restaurer les fichiers ?
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function rejouer_archive( string $chemin, string $travail, array $manifeste, bool $sans_base, bool $sans_fichiers ): array|WP_Error {
		$instructions = 0;
		$fichiers     = 0;
		$ignores      = array();

		if ( ! $sans_base && true === ( $manifeste['base_incluse'] ?? false ) ) {
			$extraction = Archiveur::extraire( $chemin, $travail, array( DumpSql::NOM_FICHIER ) );

			if ( is_wp_error( $extraction ) ) {
				return $extraction;
			}

			$chemin_sql = $travail . '/' . DumpSql::NOM_FICHIER;

			if ( ! is_file( $chemin_sql ) ) {
				return new WP_Error( 'massifs_sauvegarde_lecture', 'Le dump est absent de l\'archive.' );
			}

			$instructions = self::rejouer( $chemin_sql );

			if ( is_wp_error( $instructions ) ) {
				return $instructions;
			}
		}

		if ( ! $sans_fichiers && true === ( $manifeste['fichiers_inclus'] ?? false ) ) {
			$resultat = self::restaurer_fichiers( $chemin, $travail );

			if ( is_wp_error( $resultat ) ) {
				return $resultat;
			}

			$fichiers = $resultat['nombre'];
			$ignores  = $resultat['ignores'];
		}

		return array(
			'instructions' => $instructions,
			'fichiers'     => $fichiers,
			'ignores'      => $ignores,
			'complet'      => true === ( $manifeste['complet'] ?? false ),
		);
	}

	/**
	 * Réécrit les fichiers de l'archive dans leurs racines.
	 *
	 * L'étiquette de racine est relue dans la CONFIGURATION COURANTE, jamais dans
	 * l'archive : une archive ne porte aucun chemin absolu, ce qui est ce qui la
	 * rend restaurable sur un autre hôte que celui qui l'a produite.
	 *
	 * @param string $chemin  Chemin de l'archive.
	 * @param string $travail Répertoire de travail.
	 *
	 * @return array{nombre:int,ignores:list<string>}|WP_Error
	 */
	private static function restaurer_fichiers( string $chemin, string $travail ): array|WP_Error {
		$noms = Archiveur::lister( $chemin );

		if ( is_wp_error( $noms ) ) {
			return $noms;
		}

		$entrees = array();

		foreach ( $noms as $nom ) {
			if ( str_starts_with( $nom, Fichiers::PREFIXE_ARCHIVE . '/' ) && ! str_ends_with( $nom, '/' ) ) {
				$entrees[] = $nom;
			}
		}

		if ( array() === $entrees ) {
			return array(
				'nombre'  => 0,
				'ignores' => array(),
			);
		}

		$extraction = Archiveur::extraire( $chemin, $travail, $entrees );

		if ( is_wp_error( $extraction ) ) {
			return $extraction;
		}

		$racines = Fichiers::racines();
		$nombre  = 0;
		$ignores = array();

		// Aucune traversée possible ici : `Archiveur::extraire()` ci-dessus refuse
		// l'archive ENTIÈRE dès qu'une seule de ses entrées porte `..`, une racine
		// absolue, une lettre de lecteur ou une contre-oblique. On ne parvient à
		// cette boucle que si tous les noms sont sûrs.
		foreach ( $entrees as $nom ) {
			$relatif   = substr( $nom, strlen( Fichiers::PREFIXE_ARCHIVE ) + 1 );
			$segments  = explode( '/', $relatif );
			$etiquette = array_shift( $segments );

			if ( null === $etiquette || ! isset( $racines[ $etiquette ] ) || array() === $segments ) {
				$ignores[] = $nom;

				continue;
			}

			$source      = $travail . '/' . $nom;
			$destination = $racines[ $etiquette ] . '/' . implode( '/', $segments );

			if ( ! is_file( $source ) ) {
				$ignores[] = $nom;

				continue;
			}

			if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
				$ignores[] = $nom;

				continue;
			}

			if ( ! copy( $source, $destination ) ) {
				$ignores[] = $nom;

				continue;
			}

			++$nombre;
		}

		return array(
			'nombre'  => $nombre,
			'ignores' => $ignores,
		);
	}

	/**
	 * Exécute une instruction et rend l'erreur éventuelle.
	 *
	 * @param string $instruction Instruction SQL complète.
	 * @param int    $numero      Numéro de ligne, pour le diagnostic.
	 */
	private static function executer( string $instruction, int $numero ): ?WP_Error {
		global $wpdb;

		$wpdb->query( $instruction );

		$erreur = is_string( $wpdb->last_error ) ? trim( $wpdb->last_error ) : '';

		if ( '' === $erreur ) {
			return null;
		}

		// L'INSTRUCTION N'EST PAS CITÉE DANS LE MESSAGE : elle contient des données
		// de la base, mots de passe hachés compris, et ce message finit dans un
		// journal puis dans un courriel. Le numéro de ligne suffit au diagnostic.
		return new WP_Error(
			'massifs_sauvegarde_rejeu',
			'Erreur SQL au rejeu, ligne ' . $numero . ' : ' . $erreur
		);
	}

	/**
	 * Supprime récursivement un répertoire de travail de restauration.
	 *
	 * @param string $repertoire Répertoire de travail.
	 */
	private static function nettoyer( string $repertoire ): void {
		// Garde de nom : cette fonction supprime récursivement, elle ne doit jamais
		// pouvoir s'appliquer à autre chose qu'un répertoire de travail du module.
		if ( ! is_dir( $repertoire ) || ! str_contains( basename( $repertoire ), '.tmp-' ) ) {
			return;
		}

		$entrees = scandir( $repertoire );

		if ( false === $entrees ) {
			return;
		}

		foreach ( $entrees as $entree ) {
			if ( '.' === $entree || '..' === $entree ) {
				continue;
			}

			$chemin = $repertoire . '/' . $entree;

			if ( is_link( $chemin ) || is_file( $chemin ) ) {
				wp_delete_file( $chemin );

				continue;
			}

			if ( is_dir( $chemin ) ) {
				self::nettoyer_arborescence( $chemin );
			}
		}

		rmdir( $repertoire );
	}

	/**
	 * Supprime récursivement une arborescence extraite.
	 *
	 * @param string $repertoire Répertoire extrait.
	 */
	private static function nettoyer_arborescence( string $repertoire ): void {
		$entrees = scandir( $repertoire );

		if ( false === $entrees ) {
			return;
		}

		foreach ( $entrees as $entree ) {
			if ( '.' === $entree || '..' === $entree ) {
				continue;
			}

			$chemin = $repertoire . '/' . $entree;

			if ( is_dir( $chemin ) && ! is_link( $chemin ) ) {
				self::nettoyer_arborescence( $chemin );

				continue;
			}

			wp_delete_file( $chemin );
		}

		rmdir( $repertoire );
	}
}
