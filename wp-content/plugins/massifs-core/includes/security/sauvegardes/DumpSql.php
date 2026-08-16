<?php
/**
 * Le moteur de dump. Le cœur du module, et le seul endroit du lot où un bug
 * silencieux produit une archive RESTAURABLE EN APPARENCE ET FAUSSE EN CONTENU.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  UN BACKUP QUI MENT EST PIRE QUE PAS DE BACKUP.                               │
 * │                                                                               │
 * │  PAS DE BACKUP, ÇA SE SAIT : ON NE RESTAURE PAS. UN BACKUP QUI MENT, ÇA NE    │
 * │  SE SAIT QU'AU MOMENT DE LA RESTAURATION, C'EST-À-DIRE LE JOUR OÙ IL N'Y A    │
 * │  PLUS DE SECONDE CHANCE. CHAQUE GARANTIE CI-DESSOUS EXISTE CONTRE UN MODE DE  │
 * │  DÉFAILLANCE PRÉCIS ET SILENCIEUX, ET LA RAISON EST ÉCRITE À CÔTÉ.            │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * GRAMMAIRE DU FICHIER PRODUIT — CONTRAT AVEC `Restauration`
 *
 * Le lecteur ne contient AUCUN analyseur syntaxique SQL. Le découpage sûr est
 * acheté par une contrainte sur l'ÉCRIVAIN, pas par de l'intelligence chez le
 * lecteur. Un `explode( ';' )` naïf casse sur toute chaîne PHP sérialisée,
 * c'est-à-dire sur presque chaque ligne de `wp_options` :
 *
 *   1. Une ligne vide est ignorée.
 *   2. Une ligne commençant par `--` est un commentaire, sauf les trois marqueurs
 *      ci-dessous.
 *   3. `-- massifs:table <nom>` ouvre le bloc d'une table. Sert au lecteur ET à la
 *      projection de comparaison, qui hache table par table.
 *   4. Entre `-- massifs:structure <nom>` et `-- massifs:fin-structure`, le contenu
 *      est UNE instruction, multiligne, transmise au serveur SANS AUCUNE RETOUCHE.
 *      C'est le seul bloc multiligne du fichier, et il existe parce que la sortie
 *      de `SHOW CREATE TABLE` est multiligne et qu'on ne la retouche pas.
 *   5. TOUTE AUTRE LIGNE EST EXACTEMENT UNE INSTRUCTION ET DOIT SE TERMINER PAR
 *      `;`. Le lecteur REFUSE le fichier sinon. C'est cette contrainte, et elle
 *      seule, qui rend le découpage sur le saut de ligne exact : les littéraux
 *      passent par `wpdb::_real_escape()`, qui transforme un saut de ligne réel en
 *      `\n` sur deux caractères, donc aucune donnée ne peut couper une ligne.
 *
 * AUCUN HORODATAGE N'EST ÉCRIT DANS LE FICHIER SQL. Un `-- Généré le …` rendrait
 * deux dumps de la même base structurellement différents et ferait échouer
 * l'aller-retour de `Verification` pour une raison qui n'a rien à voir avec les
 * données. La date vit dans le manifeste.
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
 * Écriture d'un dump SQL complet, en flux, avec ses garanties opposables.
 */
final class DumpSql {

	/**
	 * Nom du fichier de dump dans l'archive.
	 */
	public const NOM_FICHIER = 'base.sql';

	/**
	 * Marqueur d'ouverture du bloc d'une table.
	 */
	public const MARQUEUR_TABLE = '-- massifs:table ';

	/**
	 * Marqueur d'ouverture du bloc de structure.
	 */
	public const MARQUEUR_STRUCTURE = '-- massifs:structure ';

	/**
	 * Marqueur de fermeture du bloc de structure.
	 */
	public const MARQUEUR_FIN_STRUCTURE = '-- massifs:fin-structure';

	/**
	 * Marqueur de fin de la dernière table, juste avant le pied.
	 *
	 * Sans lui, la projection de comparaison rattacherait les lignes de pied à la
	 * DERNIÈRE table rencontrée : le pied changerait de propriétaire dès qu'une
	 * table est ajoutée ou retirée, et deux dumps par ailleurs identiques
	 * divergeraient pour une raison qui n'a rien à voir avec les données.
	 */
	public const MARQUEUR_FIN_TABLES = '-- massifs:fin-tables';

	/**
	 * Seuil de vidage du tampon vers le disque, en octets.
	 *
	 * LE DUMP N'EST JAMAIS CONSTRUIT EN MÉMOIRE. Une base de quelques centaines de
	 * mégaoctets tuerait le processus, et un `memory_limit` atteint au milieu d'un
	 * dump laisse un fichier tronqué — donc une archive fausse.
	 */
	private const SEUIL_VIDAGE = 4194304;

	/**
	 * Types de colonnes dont la valeur est binaire par nature.
	 *
	 * @var list<string>
	 */
	private const TYPES_BINAIRES = array( 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit' );

	/**
	 * Types de colonnes entières, éligibles à la pagination par clé.
	 *
	 * @var list<string>
	 */
	private const TYPES_ENTIERS = array( 'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' );

	/**
	 * Colonnes candidates pour une exclusion de lignes en forme abrégée.
	 *
	 * @var list<string>
	 */
	private const COLONNES_NOM = array( 'option_name', 'meta_key', 'name' );

	/**
	 * Écrit le dump complet de la base dans un fichier.
	 *
	 * @param string $chemin_sql Chemin du fichier à écrire.
	 *
	 * @return array{tables:array<string,array<string,mixed>>,lignes:int,complet:bool,charset:string,octets:int,prefixe:string,lignes_exclues_ignorees:list<string>}|WP_Error
	 */
	public static function ecrire( string $chemin_sql ): array|WP_Error {
		global $wpdb;

		// `suppress_errors( false )` N'EST PAS COSMÉTIQUE : `wpdb::print_error()`
		// sort AVANT de renseigner `last_error` quand les erreurs sont supprimées.
		// Avec la suppression active, `last_error` reste vide sur une requête en
		// échec — et le dump avalerait l'erreur en silence, ce qui est l'archétype
		// du backup qui ment. `hide_errors()` empêche seulement l'affichage : c'est
		// l'inverse, et les deux sont nécessaires ensemble.
		$suppression_precedente = $wpdb->suppress_errors( false );
		$affichage_precedent    = $wpdb->hide_errors();

		try {
			return self::produire( $chemin_sql );
		} finally {
			$wpdb->suppress_errors( $suppression_precedente );

			if ( $affichage_precedent ) {
				$wpdb->show_errors();
			}
		}
	}

	/**
	 * Produit le dump. Toute erreur interrompt et supprime le fichier temporaire.
	 *
	 * @param string $chemin_sql Chemin du fichier à écrire.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function produire( string $chemin_sql ): array|WP_Error {
		global $wpdb;

		$flux = fopen( $chemin_sql, 'wb' );

		if ( false === $flux ) {
			return new WP_Error( 'massifs_sauvegarde_ecriture', 'Impossible d\'ouvrir le fichier de dump en écriture.' );
		}

		$tables = self::tables();

		if ( is_wp_error( $tables ) ) {
			fclose( $flux );
			self::supprimer( $chemin_sql );

			return $tables;
		}

		$charset = self::charset();
		$tampon  = self::entete( $charset );
		$rapport = array(
			'tables'                  => array(),
			'lignes'                  => 0,
			'complet'                 => true,
			'charset'                 => $charset,
			'octets'                  => 0,
			'prefixe'                 => (string) $wpdb->prefix,
			'lignes_exclues_ignorees' => array(),
		);

		$exclues = Reglages::tables_exclues();

		foreach ( $tables as $table ) {
			$courte = substr( $table, strlen( (string) $wpdb->prefix ) );

			if ( in_array( strtolower( $courte ), array_map( 'strtolower', $exclues ), true ) ) {
				continue;
			}

			$resultat = self::ecrire_table( $flux, $tampon, $table, $courte, $tables );

			if ( is_wp_error( $resultat ) ) {
				fclose( $flux );
				self::supprimer( $chemin_sql );

				return $resultat;
			}

			$rapport['tables'][ $table ] = $resultat['table'];
			$rapport['lignes']          += $resultat['table']['lignes_emises'];

			if ( ! $resultat['table']['complet'] ) {
				$rapport['complet'] = false;
			}

			foreach ( $resultat['ignorees'] as $ignoree ) {
				$rapport['lignes_exclues_ignorees'][] = $ignoree;
			}
		}

		$tampon .= self::pied();

		if ( ! self::vider( $flux, $tampon, true ) ) {
			fclose( $flux );
			self::supprimer( $chemin_sql );

			return new WP_Error( 'massifs_sauvegarde_ecriture', 'Écriture du dump interrompue : le disque a refusé les derniers octets.' );
		}

		fclose( $flux );

		$octets = filesize( $chemin_sql );

		$rapport['octets'] = false === $octets ? 0 : (int) $octets;

		return $rapport;
	}

	/**
	 * Liste blanche des tables à dumper, telle que le SERVEUR la donne.
	 *
	 * `SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'`, JAMAIS `SHOW TABLES` :
	 * une vue dumpée en `CREATE TABLE` casse la restauration au rejeu, et rien
	 * dans le dump ne signalerait que la table restaurée n'est pas la vue.
	 *
	 * LE PRÉFIXE N'ATTEINT JAMAIS LE SQL. `SHOW FULL TABLES` n'admet pas
	 * simultanément un `WHERE` et un `LIKE`, et sa première colonne porte un nom
	 * DYNAMIQUE (`Tables_in_<base>`) qu'il faudrait interpoler pour la filtrer côté
	 * serveur — soit exactement l'interpolation qu'on cherche à éviter. Le filtre
	 * de préfixe se fait donc en PHP, sur une liste déjà émise par le serveur :
	 * plus fort qu'un échappement, puisqu'aucune chaîne construite ici n'entre dans
	 * une requête.
	 *
	 * @return list<string>|WP_Error
	 */
	private static function tables(): array|WP_Error {
		global $wpdb;

		$lignes = $wpdb->get_results( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", ARRAY_N );

		$erreur = self::erreur_sql( 'énumération des tables' );

		if ( null !== $erreur ) {
			return $erreur;
		}

		if ( ! is_array( $lignes ) ) {
			return new WP_Error( 'massifs_sauvegarde_tables', 'Le serveur n\'a rendu aucune liste de tables.' );
		}

		$prefixe = (string) $wpdb->prefix;
		$noms    = array();

		foreach ( $lignes as $ligne ) {
			if ( ! is_array( $ligne ) || ! isset( $ligne[0] ) || ! is_string( $ligne[0] ) ) {
				continue;
			}

			if ( '' !== $prefixe && ! str_starts_with( $ligne[0], $prefixe ) ) {
				continue;
			}

			$noms[] = $ligne[0];
		}

		// Tri lexical à l'ÉCRITURE aussi, et pas seulement dans la projection : deux
		// dumps de la même base doivent être identiques octet pour octet, quel que
		// soit l'ordre dans lequel le serveur a énuméré ses tables.
		sort( $noms, SORT_STRING );

		return $noms;
	}

	/**
	 * Écrit le bloc complet d'une table.
	 *
	 * @param resource     $flux          Flux de sortie.
	 * @param string       $tampon        Tampon d'écriture, passé par référence.
	 * @param string       $table         Nom complet de la table.
	 * @param string       $courte        Nom de la table sans le préfixe.
	 * @param list<string> $liste_blanche Liste blanche des tables du serveur.
	 *
	 * @return array{table:array<string,mixed>,ignorees:list<string>}|WP_Error
	 */
	private static function ecrire_table( $flux, string &$tampon, string $table, string $courte, array $liste_blanche ): array|WP_Error {
		global $wpdb;

		// UN NOM ABSENT DE LA LISTE BLANCHE DU SERVEUR N'EST JAMAIS INTERPOLÉ.
		$encadre = self::encadrer_table( $table, $liste_blanche );

		if ( is_wp_error( $encadre ) ) {
			return $encadre;
		}

		$colonnes = self::colonnes( $encadre );

		if ( is_wp_error( $colonnes ) ) {
			return $colonnes;
		}

		$structure = self::structure( $encadre );

		if ( is_wp_error( $structure ) ) {
			return $structure;
		}

		$exclusions = self::exclusions( $courte, $colonnes );

		$tampon .= self::MARQUEUR_TABLE . $table . "\n";
		$tampon .= 'DROP TABLE IF EXISTS ' . $encadre . ";\n";
		$tampon .= self::MARQUEUR_STRUCTURE . $table . "\n";

		// `SHOW CREATE TABLE` TEL QUEL, SANS AUCUNE RETOUCHE, `AUTO_INCREMENT=<n>`
		// COMPRIS. Le retirer « pour simplifier » produirait une restauration
		// subtilement fausse : la table repartirait au prochain identifiant libre au
		// lieu de celui qu'elle avait, et une clé étrangère logique posée dans une
		// autre table pointerait un jour sur une ligne qui n'est pas la bonne.
		// La normalisation de `AUTO_INCREMENT` existe, mais UNIQUEMENT dans la
		// projection de comparaison (`Verification`), jamais dans l'archive.
		$tampon .= rtrim( $structure, "\n" ) . ";\n";
		$tampon .= self::MARQUEUR_FIN_STRUCTURE . "\n";

		if ( ! self::vider( $flux, $tampon, false ) ) {
			return new WP_Error( 'massifs_sauvegarde_ecriture', 'Écriture du dump interrompue sur la table ' . $table . '.' );
		}

		$attendues = self::compter( $encadre, $exclusions );

		if ( is_wp_error( $attendues ) ) {
			return $attendues;
		}

		$emises = self::ecrire_lignes( $flux, $tampon, $encadre, $colonnes, $exclusions );

		if ( is_wp_error( $emises ) ) {
			return $emises;
		}

		// `COUNT(*)` COMPARÉ AUX LIGNES ÉMISES. Sans cette comparaison, une page
		// perdue au milieu d'un `SELECT` — écriture concurrente, verrou, timeout —
		// produirait une archive incomplète étiquetée complète. UN DUMP PARTIEL
		// N'EST JAMAIS ÉTIQUETÉ COMPLET, et la divergence remonte jusqu'au code de
		// retour de la commande.
		$complet = $emises['lignes'] === $attendues;

		return array(
			'table'    => array(
				'lignes_attendues' => $attendues,
				'lignes_emises'    => $emises['lignes'],
				'pagination'       => $emises['pagination'],
				'exclusions'       => $exclusions['libelles'],
				'complet'          => $complet,
			),
			'ignorees' => $exclusions['ignorees'],
		);
	}

	/**
	 * Émet les `INSERT` d'une table, page par page.
	 *
	 * @param resource                                                                   $flux       Flux de sortie.
	 * @param string                                                                     $tampon     Tampon, par référence.
	 * @param string                                                                     $encadre    Nom de table encadré.
	 * @param array{noms:list<string>,binaires:array<string,bool>,cle:string} $colonnes   Description des colonnes.
	 * @param array{clause:string,arguments:list<string>,libelles:list<string>,ignorees:list<string>} $exclusions Exclusions de lignes.
	 *
	 * @return array{lignes:int,pagination:string}|WP_Error
	 */
	private static function ecrire_lignes( $flux, string &$tampon, string $encadre, array $colonnes, array $exclusions ): array|WP_Error {
		$par_page = Reglages::lignes_par_page();
		$plafond  = Reglages::octets_par_insert();

		// PAGINATION PAR CLÉ, PAS PAR `OFFSET`. Sous écriture concurrente, une ligne
		// supprimée entre deux pages décale toutes les suivantes et `OFFSET` SAUTE
		// des lignes : le dump se termine sans erreur, l'archive est incomplète, et
		// rien ne le dit. `WHERE id > %d ORDER BY id ASC` est immunisé.
		$par_cle     = '' !== $colonnes['cle'];
		$pagination  = $par_cle ? 'cle' : 'offset';
		$prefixe_sql = 'INSERT INTO ' . $encadre . ' (' . implode( ',', array_map( array( self::class, 'encadrer' ), $colonnes['noms'] ) ) . ') VALUES ';

		$lignes      = 0;
		$curseur     = null;
		$decalage    = 0;
		$instruction = '';

		do {
			$page = self::page( $encadre, $colonnes, $exclusions, $par_cle, $curseur, $decalage, $par_page );

			if ( is_wp_error( $page ) ) {
				return $page;
			}

			if ( array() === $page ) {
				break;
			}

			foreach ( $page as $ligne ) {
				$valeurs = array();

				foreach ( $colonnes['noms'] as $nom ) {
					$brute = array_key_exists( $nom, $ligne ) ? $ligne[ $nom ] : null;

					$valeurs[] = self::litteral( $brute, $colonnes['binaires'][ $nom ] ?? false );
				}

				$tuple = '(' . implode( ',', $valeurs ) . ')';

				if ( '' === $instruction ) {
					$instruction = $prefixe_sql . $tuple;
				} elseif ( strlen( $instruction ) + strlen( $tuple ) + 2 > $plafond ) {
					$tampon     .= $instruction . ";\n";
					$instruction = $prefixe_sql . $tuple;
				} else {
					$instruction .= ',' . $tuple;
				}

				++$lignes;

				if ( $par_cle ) {
					$curseur = array_key_exists( $colonnes['cle'], $ligne ) ? (int) $ligne[ $colonnes['cle'] ] : $curseur;
				}
			}

			if ( ! $par_cle ) {
				$decalage += count( $page );
			}

			if ( ! self::vider( $flux, $tampon, false ) ) {
				return new WP_Error( 'massifs_sauvegarde_ecriture', 'Écriture du dump interrompue pendant les données.' );
			}
		} while ( count( $page ) === $par_page );

		if ( '' !== $instruction ) {
			$tampon .= $instruction . ";\n";
		}

		if ( ! self::vider( $flux, $tampon, false ) ) {
			return new WP_Error( 'massifs_sauvegarde_ecriture', 'Écriture du dump interrompue pendant les données.' );
		}

		return array(
			'lignes'     => $lignes,
			'pagination' => $pagination,
		);
	}

	/**
	 * Lit une page de lignes.
	 *
	 * @param string                                                                     $encadre    Nom de table encadré.
	 * @param array<string, mixed>                                                       $colonnes   Description des colonnes.
	 * @param array{clause:string,arguments:list<string>,libelles:list<string>,ignorees:list<string>} $exclusions Exclusions.
	 * @param bool                                                                       $par_cle    Pagination par clé ?
	 * @param int|null                                                                   $curseur    Dernière clé émise.
	 * @param int                                                                        $decalage   Décalage, repli `OFFSET`.
	 * @param int                                                                        $par_page   Taille de page.
	 *
	 * @return list<array<string, mixed>>|WP_Error
	 */
	private static function page( string $encadre, array $colonnes, array $exclusions, bool $par_cle, ?int $curseur, int $decalage, int $par_page ): array|WP_Error {
		global $wpdb;

		$arguments = $exclusions['arguments'];
		$sql       = 'SELECT * FROM ' . $encadre . ' WHERE 1=1' . $exclusions['clause'];

		if ( $par_cle ) {
			$cle = self::encadrer( (string) $colonnes['cle'] );

			if ( null !== $curseur ) {
				$sql        .= ' AND ' . $cle . ' > %d';
				$arguments[] = $curseur;
			}

			$sql        .= ' ORDER BY ' . $cle . ' ASC LIMIT %d';
			$arguments[] = $par_page;
		} else {
			$sql        .= ' LIMIT %d OFFSET %d';
			$arguments[] = $par_page;
			$arguments[] = $decalage;
		}

		$page = $wpdb->get_results( $wpdb->prepare( $sql, ...$arguments ), ARRAY_A );

		$erreur = self::erreur_sql( 'lecture d\'une page de ' . $encadre );

		if ( null !== $erreur ) {
			return $erreur;
		}

		return is_array( $page ) ? $page : array();
	}

	/**
	 * Compte les lignes attendues, exclusions appliquées.
	 *
	 * @param string                                                                     $encadre    Nom de table encadré.
	 * @param array{clause:string,arguments:list<string>,libelles:list<string>,ignorees:list<string>} $exclusions Exclusions.
	 *
	 * @return int|WP_Error
	 */
	private static function compter( string $encadre, array $exclusions ): int|WP_Error {
		global $wpdb;

		$sql = 'SELECT COUNT(*) FROM ' . $encadre . ' WHERE 1=1' . $exclusions['clause'];

		// `prepare()` UNIQUEMENT s'il y a un marqueur à substituer : appelé sans
		// marqueur, il déclenche un `_doing_it_wrong` du cœur. La requête sans
		// exclusion ne porte aucune valeur variable — le nom de table vient de la
		// liste blanche du serveur.
		$total = $wpdb->get_var( array() === $exclusions['arguments'] ? $sql : $wpdb->prepare( $sql, ...$exclusions['arguments'] ) );

		$erreur = self::erreur_sql( 'comptage de ' . $encadre );

		if ( null !== $erreur ) {
			return $erreur;
		}

		return null === $total ? 0 : (int) $total;
	}

	/**
	 * Compose un littéral SQL à partir d'une valeur brute.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  LE TEST DE NULLITÉ EST LA PREMIÈRE LIGNE, ET IL PORTE SUR LA VALEUR      │
	 * │  BRUTE, AVANT TOUT CAST. UN `(string)` OU UN `trim()` POSÉ AVANT LUI      │
	 * │  « POUR ASSAINIR » ÉCRASERAIT LA DISTINCTION ENTRE `NULL` ET `''`.        │
	 * │                                                                           │
	 * │  PORTEUR DIRECT : `wp_massifs_statuts.niveau_cle` EST NULLABLE, ET SON    │
	 * │  `NULL` SIGNIFIE « LA SOURCE A PUBLIÉ UNE LIGNE SANS STATUT D'ACCÈS ».    │
	 * │  C'EST UN FAIT DISTINCT DE « LA SOURCE N'A RIEN PUBLIÉ ». LES CONFONDRE   │
	 * │  FAUSSERAIT L'HISTORIQUE DU §4.2 — SANS QU'AUCUNE ERREUR NE SE PRODUISE.  │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * @param mixed $valeur  Valeur brute telle que `wpdb` l'a rendue.
	 * @param bool  $binaire La colonne est-elle de type binaire ?
	 */
	private static function litteral( mixed $valeur, bool $binaire ): string {
		if ( null === $valeur ) {
			return 'NULL';
		}

		global $wpdb;

		$texte = (string) $valeur;

		// CAS LIMITE NOMMÉ : une chaîne binaire VIDE s'écrit `''`, jamais `0x`.
		// `0x` seul est une erreur de syntaxe MySQL, et la restauration s'arrêterait
		// là — visiblement, cette fois, mais après avoir déjà rejoué la moitié des
		// tables.
		if ( '' === $texte ) {
			return "''";
		}

		// Littéral hexadécimal sur type binaire OU sur UTF-8 invalide. Le second cas
		// n'est pas théorique : une colonne texte peut porter des octets qui ne
		// forment pas de l'UTF-8 valide, et les quoter tels quels ferait rejeter
		// l'instruction par le serveur — ou pire, la ferait tronquer en silence à
		// l'octet fautif.
		if ( $binaire || ! mb_check_encoding( $texte, 'UTF-8' ) ) {
			return '0x' . bin2hex( $texte );
		}

		// `_real_escape()` et non une concaténation maison : c'est lui qui
		// transforme un saut de ligne RÉEL en `\n` sur deux caractères, ce qui rend
		// vraie la promesse « une instruction par ligne » faite au lecteur.
		return "'" . $wpdb->_real_escape( $texte ) . "'";
	}

	/**
	 * Décrit les colonnes d'une table et repère une clé de pagination.
	 *
	 * @param string $encadre Nom de table encadré.
	 *
	 * @return array{noms:list<string>,binaires:array<string,bool>,cle:string}|WP_Error
	 */
	private static function colonnes( string $encadre ): array|WP_Error {
		global $wpdb;

		$lignes = $wpdb->get_results( 'SHOW FULL COLUMNS FROM ' . $encadre, ARRAY_A );

		$erreur = self::erreur_sql( 'description de ' . $encadre );

		if ( null !== $erreur ) {
			return $erreur;
		}

		if ( ! is_array( $lignes ) || array() === $lignes ) {
			return new WP_Error( 'massifs_sauvegarde_colonnes', 'Aucune colonne lisible pour ' . $encadre . '.' );
		}

		$noms     = array();
		$binaires = array();
		$types    = array();

		foreach ( $lignes as $ligne ) {
			if ( ! is_array( $ligne ) || ! isset( $ligne['Field'] ) || ! is_string( $ligne['Field'] ) ) {
				continue;
			}

			$nom  = $ligne['Field'];
			$type = self::type_de_base( isset( $ligne['Type'] ) && is_string( $ligne['Type'] ) ? $ligne['Type'] : '' );

			$noms[]           = $nom;
			$binaires[ $nom ] = in_array( $type, self::TYPES_BINAIRES, true );
			$types[ $nom ]    = $type;
		}

		if ( array() === $noms ) {
			return new WP_Error( 'massifs_sauvegarde_colonnes', 'Aucune colonne exploitable pour ' . $encadre . '.' );
		}

		$cle = self::cle_de_pagination( $encadre, $types );

		if ( is_wp_error( $cle ) ) {
			return $cle;
		}

		return array(
			'noms'     => $noms,
			'binaires' => $binaires,
			'cle'      => $cle,
		);
	}

	/**
	 * Repère la clé primaire mono-colonne entière, s'il en existe une.
	 *
	 * @param string                $encadre Nom de table encadré.
	 * @param array<string, string> $types   Types de base par colonne.
	 *
	 * @return string|WP_Error Nom de colonne, ou chaîne vide si aucune.
	 */
	private static function cle_de_pagination( string $encadre, array $types ): string|WP_Error {
		global $wpdb;

		$lignes = $wpdb->get_results( $wpdb->prepare( 'SHOW KEYS FROM ' . $encadre . ' WHERE Key_name = %s', 'PRIMARY' ), ARRAY_A );

		$erreur = self::erreur_sql( 'lecture de la clé primaire de ' . $encadre );

		if ( null !== $erreur ) {
			return $erreur;
		}

		if ( ! is_array( $lignes ) || 1 !== count( $lignes ) ) {
			return '';
		}

		$ligne = $lignes[0];

		if ( ! is_array( $ligne ) || ! isset( $ligne['Column_name'] ) || ! is_string( $ligne['Column_name'] ) ) {
			return '';
		}

		$colonne = $ligne['Column_name'];

		if ( ! isset( $types[ $colonne ] ) || ! in_array( $types[ $colonne ], self::TYPES_ENTIERS, true ) ) {
			return '';
		}

		return $colonne;
	}

	/**
	 * Lit la structure d'une table, telle que le serveur l'écrit.
	 *
	 * @param string $encadre Nom de table encadré.
	 *
	 * @return string|WP_Error
	 */
	private static function structure( string $encadre ): string|WP_Error {
		global $wpdb;

		$ligne = $wpdb->get_row( 'SHOW CREATE TABLE ' . $encadre, ARRAY_N );

		$erreur = self::erreur_sql( 'lecture de la structure de ' . $encadre );

		if ( null !== $erreur ) {
			return $erreur;
		}

		if ( ! is_array( $ligne ) || ! isset( $ligne[1] ) || ! is_string( $ligne[1] ) || '' === trim( $ligne[1] ) ) {
			return new WP_Error( 'massifs_sauvegarde_structure', 'Structure illisible pour ' . $encadre . '.' );
		}

		return $ligne[1];
	}

	/**
	 * Compose la clause d'exclusion de lignes d'une table.
	 *
	 * @param string               $courte   Nom de table sans préfixe.
	 * @param array<string, mixed> $colonnes Description des colonnes.
	 *
	 * @return array{clause:string,arguments:list<string>,libelles:list<string>,ignorees:list<string>}
	 */
	private static function exclusions( string $courte, array $colonnes ): array {
		$vide = array(
			'clause'    => '',
			'arguments' => array(),
			'libelles'  => array(),
			'ignorees'  => array(),
		);

		$toutes = Reglages::lignes_exclues();
		$cle    = strtolower( $courte );

		if ( ! isset( $toutes[ $cle ] ) || ! is_array( $toutes[ $cle ] ) ) {
			return $vide;
		}

		$regles = $toutes[ $cle ];

		// Forme abrégée : la colonne est résolue contre les colonnes RÉELLES de la
		// table, jamais devinée. Une abréviation qui ne résout pas est reportée dans
		// le manifeste : une exclusion silencieusement inopérante ferait croire à une
		// protection qui n'existe pas.
		if ( array_is_list( $regles ) ) {
			$colonne = '';

			foreach ( self::COLONNES_NOM as $candidate ) {
				if ( in_array( $candidate, $colonnes['noms'], true ) ) {
					$colonne = $candidate;
					break;
				}
			}

			if ( '' === $colonne ) {
				$vide['ignorees'] = array( $courte . ' : aucune colonne de nom parmi ' . implode( ', ', self::COLONNES_NOM ) );

				return $vide;
			}

			$regles = array( $colonne => $regles );
		}

		$clause    = '';
		$arguments = array();
		$libelles  = array();
		$ignorees  = array();

		foreach ( $regles as $colonne => $motifs ) {
			if ( ! is_string( $colonne ) || ! in_array( $colonne, $colonnes['noms'], true ) ) {
				$ignorees[] = $courte . ' : colonne « ' . (string) $colonne . ' » absente de la table';

				continue;
			}

			foreach ( (array) $motifs as $motif ) {
				if ( ! is_string( $motif ) || '' === $motif ) {
					continue;
				}

				// `prepare()` sur le motif : c'est la SEULE valeur de cette requête
				// qui vienne d'un filtre, donc de l'extérieur.
				$clause     .= ' AND ' . self::encadrer( $colonne ) . ' NOT LIKE %s';
				$arguments[] = $motif;
				$libelles[]  = $colonne . ' NOT LIKE ' . $motif;
			}
		}

		return array(
			'clause'    => $clause,
			'arguments' => $arguments,
			'libelles'  => $libelles,
			'ignorees'  => $ignorees,
		);
	}

	/**
	 * En-tête du fichier.
	 *
	 * @param string $charset Jeu de caractères de la connexion.
	 */
	private static function entete( string $charset ): string {
		$lignes = array(
			'-- Sauvegarde MASSIFS. Ce fichier est relu par Massifs\\Security\\Sauvegardes\\Restauration.',
			'-- Aucune date n\'y figure : deux dumps de la meme base doivent etre identiques octet pour octet.',
			'/*!40101 SET NAMES ' . $charset . ' */;',

			// SANS `NO_AUTO_VALUE_ON_ZERO`, UNE COLONNE `auto_increment` PORTANT `0`
			// SERAIT RENUMÉROTÉE AU REJEU. Rare, silencieux, irréversible : la ligne
			// revient avec un autre identifiant, tout ce qui la référençait pointe
			// ailleurs, et l'archive avait pourtant l'air correcte.
			"SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';",

			// Les tables sont rejouées dans l'ordre lexical, pas dans l'ordre des
			// dépendances : sans cette ligne, une contrainte referencerait une table
			// pas encore recréée.
			'SET FOREIGN_KEY_CHECKS=0;',
			'SET UNIQUE_CHECKS=0;',
			'SET AUTOCOMMIT=0;',
		);

		return implode( "\n", $lignes ) . "\n";
	}

	/**
	 * Pied du fichier : les trois bascules sont rendues, puis la transaction validée.
	 */
	private static function pied(): string {
		$lignes = array(
			self::MARQUEUR_FIN_TABLES,
			'COMMIT;',
			'SET AUTOCOMMIT=1;',
			'SET UNIQUE_CHECKS=1;',
			'SET FOREIGN_KEY_CHECKS=1;',
		);

		return implode( "\n", $lignes ) . "\n";
	}

	/**
	 * Jeu de caractères de la connexion, réduit à un identifiant sûr.
	 */
	private static function charset(): string {
		global $wpdb;

		$charset = is_string( $wpdb->charset ) ? trim( $wpdb->charset ) : '';

		if ( '' === $charset || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $charset ) ) {
			return 'utf8mb4';
		}

		return $charset;
	}

	/**
	 * Vide le tampon vers le disque au-delà du seuil, ou inconditionnellement.
	 *
	 * @param resource $flux   Flux de sortie.
	 * @param string   $tampon Tampon, par référence.
	 * @param bool     $forcer Vider quelle que soit la taille ?
	 */
	private static function vider( $flux, string &$tampon, bool $forcer ): bool {
		if ( '' === $tampon ) {
			return true;
		}

		if ( ! $forcer && strlen( $tampon ) < self::SEUIL_VIDAGE ) {
			return true;
		}

		$attendu = strlen( $tampon );
		$ecrits  = fwrite( $flux, $tampon );
		$tampon  = '';

		// Une écriture partielle est un disque plein : la traiter comme un succès
		// produirait un dump tronqué, donc une archive fausse.
		return false !== $ecrits && $ecrits === $attendu;
	}

	/**
	 * Encadre un nom de table après contrôle d'appartenance à la liste blanche.
	 *
	 * @param string       $table         Nom de table.
	 * @param list<string> $liste_blanche Liste blanche issue du serveur.
	 *
	 * @return string|WP_Error
	 */
	private static function encadrer_table( string $table, array $liste_blanche ): string|WP_Error {
		if ( ! in_array( $table, $liste_blanche, true ) ) {
			return new WP_Error( 'massifs_sauvegarde_table_inconnue', 'Table hors liste blanche du serveur : ' . $table . '.' );
		}

		return self::encadrer( $table );
	}

	/**
	 * Encadre un identifiant en accents graves.
	 *
	 * Le doublement de l'accent grave est la règle d'échappement MySQL des
	 * identifiants. Les identifiants encadrés ici viennent tous du serveur —
	 * `SHOW FULL TABLES`, `SHOW FULL COLUMNS`, `SHOW KEYS` — jamais d'une entrée.
	 *
	 * @param string $nom Identifiant.
	 */
	private static function encadrer( string $nom ): string {
		return '`' . str_replace( '`', '``', $nom ) . '`';
	}

	/**
	 * Extrait le type de base d'une déclaration de colonne.
	 *
	 * @param string $type Déclaration complète, par exemple `varbinary(255)`.
	 */
	private static function type_de_base( string $type ): string {
		if ( 1 !== preg_match( '/^[a-z]+/', strtolower( trim( $type ) ), $trouve ) ) {
			return '';
		}

		return $trouve[0];
	}

	/**
	 * Rend une `WP_Error` si la dernière requête a échoué.
	 *
	 * APPELÉ APRÈS CHAQUE REQUÊTE, SANS EXCEPTION. `wpdb` ne lève rien : une
	 * requête en échec rend `null` ou `false`, ce qu'un `foreach` traite comme
	 * « rien à faire ». Sans ce contrôle, une table illisible produirait un bloc
	 * vide et un dump vert.
	 *
	 * @param string $contexte Ce qui était en cours.
	 */
	private static function erreur_sql( string $contexte ): ?WP_Error {
		global $wpdb;

		$erreur = is_string( $wpdb->last_error ) ? trim( $wpdb->last_error ) : '';

		if ( '' === $erreur ) {
			return null;
		}

		return new WP_Error(
			'massifs_sauvegarde_sql',
			'Erreur SQL pendant ' . $contexte . ' : ' . $erreur
		);
	}

	/**
	 * Supprime un fichier temporaire sans bruit.
	 *
	 * @param string $chemin Chemin du fichier.
	 */
	private static function supprimer( string $chemin ): void {
		if ( is_file( $chemin ) ) {
			wp_delete_file( $chemin );
		}
	}
}
