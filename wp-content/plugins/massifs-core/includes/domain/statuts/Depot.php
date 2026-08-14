<?php
/**
 * Accès à la table des statuts.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEULE classe de l'extension qui voit `$wpdb`.
 *
 * Son vocabulaire de données est volontairement réduit à `inserer()`,
 * `selectionner_jour()` et `selectionner_historique()` ; `nom_table()` et
 * `collation()` ne servent qu'à nommer la table et à décrire son DDL. Les
 * seules méthodes de `$wpdb` employées sont `insert`, `prepare`, `get_results`,
 * `get_row` et `insert_id`, plus `get_charset_collate` pour la collation :
 * aucune méthode ni aucun ordre SQL de modification ou de suppression de
 * DONNÉE, sous quelque forme que ce soit. L'historique est strictement en
 * insertion pure : une correction est une ligne de plus, jamais un écrasement.
 *
 * UNE SEULE EXCEPTION, ET C'EST DU DDL, PAS UNE ÉCRITURE DE DONNÉE.
 * `rendre_colonne_nullable()` emploie `$wpdb->query()` pour un `ALTER TABLE …
 * MODIFY`, parce que `dbDelta` ne compare que le TYPE d'une colonne et jamais sa
 * NULLABILITÉ : sur une base déjà installée en `NOT NULL`, il ne corrigera
 * jamais `niveau_cle`, et une ligne à `level` 0 sera rejetée par MySQL. La
 * méthode ne touche aucune ligne, n'existe que pour une table blanche de
 * colonnes déclarée ici, et ne réintroduit ni `UPDATE`, ni `DELETE`, ni
 * `REPLACE`, ni `TRUNCATE`. Ce n'est pas une régression du verrou d'insertion
 * pure : c'est la structure, pas le contenu.
 *
 * Il n'existe AUCUNE méthode « dernier statut connu quel que soit le jour » :
 * ce que le dépôt ne sait pas faire, personne ne peut le lui demander. C'est la
 * garantie structurelle du §4.2.
 *
 * `niveau_source_brut` et `procedure_source` sont ÉCRITS mais jamais SÉLECTIONNÉS :
 * ils existent pour rendre possible une re-projection si la granularité
 * d'affichage change un jour, pas pour être présentés. Ne pas les lire est la
 * façon la plus simple de garantir qu'ils n'atteindront aucune sortie publique.
 *
 * AJOUT DU JOURNAL (issue #15) — quatre lectures supplémentaires,
 * `selectionner_journal()`, `compter_journal()`, `id_max_journal()` et
 * `auteurs_journal()`, s'ajoutent au vocabulaire de données décrit ci-dessus.
 * Elles n'emploient que `prepare`, `get_row` et `get_results` : aucune écriture,
 * aucun ordre de modification, le verrou d'insertion pure est intact. Elles
 * partagent toutes la MÊME clause `WHERE`, construite par `clause_journal()` :
 * une liste et un compte qui divergeraient afficheraient une pagination fausse.
 */
final class Depot {

	/**
	 * Suffixe de la table, après le préfixe de l'installation.
	 */
	private const SUFFIXE_TABLE = 'massifs_statuts';

	/**
	 * Garde-fou de taille des clauses `IN()`.
	 */
	private const TAILLE_TRANCHE = 200;

	/**
	 * Colonnes dont la nullabilité peut être corrigée après coup, et leur définition cible.
	 *
	 * TABLE BLANCHE FERMÉE. Le nom de colonne et sa définition ne peuvent donc
	 * jamais venir d'une entrée : ils sont écrits ici, dans du code versionné, et
	 * `rendre_colonne_nullable()` refuse tout ce qui n'y figure pas. C'est ce qui
	 * rend l'interpolation du nom de colonne dans le `ALTER` sûre — `prepare()` ne
	 * sait paramétrer ni un identifiant de colonne ni un type.
	 *
	 * @var array<string, string>
	 */
	private const COLONNES_NULLABLES = array(
		'niveau_cle' => 'varchar(32) NULL DEFAULT NULL',
	);

	/**
	 * Nom complet de la table.
	 */
	public static function nom_table(): string {
		global $wpdb;

		return $wpdb->prefix . self::SUFFIXE_TABLE;
	}

	/**
	 * Jeu de caractères et collation de l'installation, pour le DDL.
	 */
	public static function collation(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * La colonne accepte-t-elle `NULL` dans la base RÉELLE ?
	 *
	 * On interroge l'état réel plutôt que de supposer celui du DDL : `dbDelta` a
	 * pu créer la table en `NOT NULL` lors d'une version antérieure du schéma et
	 * ne la corrigera jamais, puisqu'il ne compare que le type.
	 *
	 * `SHOW COLUMNS` plutôt que `information_schema` : même réponse, sans exiger
	 * de privilège supplémentaire sur une base d'hébergement mutualisé.
	 *
	 * @param string $colonne Colonne de la table blanche.
	 *
	 * @return bool|null `null` si la colonne est inconnue, absente ou illisible — auquel cas on n'altère rien.
	 */
	public function colonne_accepte_null( string $colonne ): ?bool {
		global $wpdb;

		if ( ! isset( self::COLONNES_NULLABLES[ $colonne ] ) ) {
			return null;
		}

		$ligne = $wpdb->get_row(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', self::nom_table(), $colonne ),
			ARRAY_A
		);

		if ( ! is_array( $ligne ) || ! isset( $ligne['Null'] ) ) {
			return null;
		}

		return 'YES' === strtoupper( (string) $ligne['Null'] );
	}

	/**
	 * Rend une colonne de la table blanche nullable.
	 *
	 * DDL, PAS UNE ÉCRITURE DE DONNÉE : aucune ligne n'est lue, modifiée ni
	 * supprimée. Voir l'exception documentée en tête de classe.
	 *
	 * L'appelant vérifie d'abord `colonne_accepte_null()` : l'`ALTER` n'est donc
	 * émis que sur une base réellement en `NOT NULL`, jamais à chaque amorçage.
	 *
	 * @param string $colonne Colonne de la table blanche.
	 *
	 * @return bool `true` si l'ordre a été accepté.
	 */
	public function rendre_colonne_nullable( string $colonne ): bool {
		global $wpdb;

		if ( ! isset( self::COLONNES_NULLABLES[ $colonne ] ) ) {
			return false;
		}

		// Le nom de table passe par `%i` ; le nom de colonne et sa définition
		// viennent de la table blanche fermée ci-dessus, et `prepare()` ne sait de
		// toute façon paramétrer ni un identifiant de colonne ni un type.
		$requete = $wpdb->prepare(
			'ALTER TABLE %i MODIFY ' . $colonne . ' ' . self::COLONNES_NULLABLES[ $colonne ],
			self::nom_table()
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Requête construite par `prepare()` juste au-dessus.
		return false !== $wpdb->query( $requete );
	}

	/**
	 * Insère une ligne d'historique.
	 *
	 * Les valeurs reçues ici sont déjà normalisées et validées par le domaine :
	 * ce dépôt ne valide rien, il écrit.
	 *
	 * @param string      $massif_code          Code du massif.
	 * @param string      $jour_validite        Jour de validité `YYYY-MM-DD`.
	 * @param string|null $niveau_cle           Clé texte du niveau, `null` si la source n'a publié aucun statut.
	 * @param string|null $zapef_cle            Clé texte de l'entrée ZAPEF, `null` si inconnue.
	 * @param int|null    $niveau_source_brut   `level` brut émis par la source, `null` pour une saisie manuelle.
	 * @param int|null    $procedure_source     `procedure` brute émise par la source, `null` pour une saisie manuelle.
	 * @param string      $source               Provenance.
	 * @param int|null    $auteur_id            Auteur de la saisie manuelle.
	 * @param string|null $publie_prefecture_le Publication préfectorale, format de stockage UTC.
	 * @param string      $enregistre_le        Instant d'enregistrement, format de stockage UTC.
	 *
	 * @return int|null Identifiant inséré, ou `null` si l'insertion a échoué.
	 */
	public function inserer(
		string $massif_code,
		string $jour_validite,
		?string $niveau_cle,
		?string $zapef_cle,
		?int $niveau_source_brut,
		?int $procedure_source,
		string $source,
		?int $auteur_id,
		?string $publie_prefecture_le,
		string $enregistre_le
	): ?int {
		global $wpdb;

		$insere = $wpdb->insert(
			self::nom_table(),
			array(
				'massif_code'          => $massif_code,
				'jour_validite'        => $jour_validite,
				'niveau_cle'           => $niveau_cle,
				'zapef_cle'            => $zapef_cle,
				'niveau_source_brut'   => $niveau_source_brut,
				'procedure_source'     => $procedure_source,
				'source'               => $source,
				'auteur_id'            => $auteur_id,
				'publie_prefecture_le' => $publie_prefecture_le,
				'enregistre_le'        => $enregistre_le,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $insere ) {
			return null;
		}

		$id = (int) $wpdb->insert_id;

		return $id > 0 ? $id : null;
	}

	/**
	 * Statut courant de chaque massif POUR CE JOUR EXACTEMENT.
	 *
	 * `jour_validite = %s` est lié : la requête ne peut pas ramener la ligne
	 * d'un autre jour. Le statut courant d'un couple (massif, jour) est la ligne
	 * de plus grand identifiant — la dernière correction publiée.
	 *
	 * @param list<string> $codes Codes de massif, déjà normalisés et validés.
	 * @param string       $jour  Jour de validité `YYYY-MM-DD`.
	 *
	 * @return array<string, array<string, mixed>> Lignes indexées par `massif_code`.
	 */
	public function selectionner_jour( array $codes, string $jour ): array {
		global $wpdb;

		if ( array() === $codes ) {
			return array();
		}

		$table  = self::nom_table();
		$lignes = array();

		foreach ( array_chunk( $codes, self::TAILLE_TRANCHE ) as $tranche ) {
			$emplacements = implode( ',', array_fill( 0, count( $tranche ), '%s' ) );

			$requete = $wpdb->prepare(
				'SELECT s.id, s.massif_code, s.jour_validite, s.niveau_cle, s.zapef_cle, s.source, s.auteur_id,'
					. ' s.publie_prefecture_le, s.enregistre_le'
					. ' FROM %i AS s'
					. ' INNER JOIN ('
					. ' SELECT MAX(id) AS id_courant FROM %i'
					. ' WHERE jour_validite = %s AND massif_code IN (' . $emplacements . ')'
					. ' GROUP BY massif_code'
					. ' ) AS courant ON courant.id_courant = s.id',
				array_merge( array( $table, $table, $jour ), $tranche )
			);

			$resultats = $wpdb->get_results( $requete, ARRAY_A );

			if ( ! is_array( $resultats ) ) {
				continue;
			}

			foreach ( $resultats as $ligne ) {
				$lignes[ (string) $ligne['massif_code'] ] = $ligne;
			}
		}

		return $lignes;
	}

	/**
	 * Lignes d'historique, ordonnées pour la dérivation du niveau précédent.
	 *
	 * L'ordre `(massif_code, jour_validite, id)` croissant est contractuel :
	 * `EntreeHistorique::depuis_lignes()` en dépend pour dériver la valeur
	 * précédente de chaque couple.
	 *
	 * @param array{massif_code?: string, jour_debut?: string, jour_fin?: string, auteur_id?: int, limite?: int, decalage?: int} $criteres Filtres.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function selectionner_historique( array $criteres ): array {
		global $wpdb;

		$conditions = array();
		$arguments  = array( self::nom_table() );

		if ( isset( $criteres['massif_code'] ) && '' !== $criteres['massif_code'] ) {
			$conditions[] = 'massif_code = %s';
			$arguments[]  = (string) $criteres['massif_code'];
		}

		if ( isset( $criteres['jour_debut'] ) && '' !== $criteres['jour_debut'] ) {
			$conditions[] = 'jour_validite >= %s';
			$arguments[]  = (string) $criteres['jour_debut'];
		}

		if ( isset( $criteres['jour_fin'] ) && '' !== $criteres['jour_fin'] ) {
			$conditions[] = 'jour_validite <= %s';
			$arguments[]  = (string) $criteres['jour_fin'];
		}

		if ( isset( $criteres['auteur_id'] ) && (int) $criteres['auteur_id'] > 0 ) {
			$conditions[] = 'auteur_id = %d';
			$arguments[]  = (int) $criteres['auteur_id'];
		}

		$limite   = isset( $criteres['limite'] ) ? max( 1, min( 5000, (int) $criteres['limite'] ) ) : 500;
		$decalage = isset( $criteres['decalage'] ) ? max( 0, (int) $criteres['decalage'] ) : 0;

		$arguments[] = $limite;
		$arguments[] = $decalage;

		$requete = $wpdb->prepare(
			'SELECT id, massif_code, jour_validite, niveau_cle, zapef_cle, source, auteur_id,'
				. ' publie_prefecture_le, enregistre_le'
				. ' FROM %i'
				. ( array() === $conditions ? '' : ' WHERE ' . implode( ' AND ', $conditions ) )
				. ' ORDER BY massif_code ASC, jour_validite ASC, id ASC'
				. ' LIMIT %d OFFSET %d',
			$arguments
		);

		$resultats = $wpdb->get_results( $requete, ARRAY_A );

		return is_array( $resultats ) ? array_values( $resultats ) : array();
	}

	/**
	 * Clause `WHERE` PARTAGÉE du journal, et ses arguments liés.
	 *
	 * UNE SEULE construction pour la liste, le compte et la borne. Deux clauses
	 * séparées finiraient par diverger, et une liste qui ne dit pas la même chose
	 * que son compte produit une pagination fausse — des pages vides, ou des
	 * lignes inatteignables.
	 *
	 * Toutes les colonnes sont préfixées `s.` : la clause doit rester
	 * interchangeable entre une requête à une seule table et l'auto-jointure de
	 * `selectionner_journal()`, où `s` et `p` portent les mêmes noms de colonnes.
	 *
	 * La liste des critères reconnus est FERMÉE : une clé inconnue est ignorée en
	 * silence, jamais interpolée. Les valeurs partent toutes en emplacements liés.
	 *
	 * `enregistre_le_max` est une borne HAUTE EXCLUSIVE : elle vaut le début du
	 * jour suivant, jamais `23:59:59`, qui perdrait toute écriture de la dernière
	 * seconde d'un `datetime`.
	 *
	 * @param array<string, mixed> $criteres Critères déjà normalisés par le domaine.
	 *
	 * @return array{clause: string, arguments: list<int|string>}
	 */
	private function clause_journal( array $criteres ): array {
		$conditions = array();
		$arguments  = array();

		if ( isset( $criteres['massif_code'] ) && '' !== $criteres['massif_code'] ) {
			$conditions[] = 's.massif_code = %s';
			$arguments[]  = (string) $criteres['massif_code'];
		}

		if ( isset( $criteres['jour_debut'] ) && '' !== $criteres['jour_debut'] ) {
			$conditions[] = 's.jour_validite >= %s';
			$arguments[]  = (string) $criteres['jour_debut'];
		}

		if ( isset( $criteres['jour_fin'] ) && '' !== $criteres['jour_fin'] ) {
			$conditions[] = 's.jour_validite <= %s';
			$arguments[]  = (string) $criteres['jour_fin'];
		}

		if ( isset( $criteres['auteur_id'] ) && (int) $criteres['auteur_id'] > 0 ) {
			$conditions[] = 's.auteur_id = %d';
			$arguments[]  = (int) $criteres['auteur_id'];
		}

		if ( isset( $criteres['source'] ) && '' !== $criteres['source'] ) {
			$conditions[] = 's.source = %s';
			$arguments[]  = (string) $criteres['source'];
		}

		if ( isset( $criteres['enregistre_le_min'] ) && '' !== $criteres['enregistre_le_min'] ) {
			$conditions[] = 's.enregistre_le >= %s';
			$arguments[]  = (string) $criteres['enregistre_le_min'];
		}

		if ( isset( $criteres['enregistre_le_max'] ) && '' !== $criteres['enregistre_le_max'] ) {
			$conditions[] = 's.enregistre_le < %s';
			$arguments[]  = (string) $criteres['enregistre_le_max'];
		}

		if ( isset( $criteres['id_max'] ) && (int) $criteres['id_max'] > 0 ) {
			$conditions[] = 's.id <= %d';
			$arguments[]  = (int) $criteres['id_max'];
		}

		return array(
			'clause'    => array() === $conditions ? '' : ' WHERE ' . implode( ' AND ', $conditions ),
			'arguments' => $arguments,
		);
	}

	/**
	 * Lignes du journal, la valeur précédente ÉTABLIE EN SQL.
	 *
	 * LE POINT CENTRAL DE CETTE MÉTHODE, ET LA RAISON DE SON EXISTENCE : la ligne
	 * précédente est cherchée sur la partition NON FILTRÉE du couple
	 * (`massif_code`, `jour_validite`). La sous-requête corrélée ne porte AUCUN
	 * des filtres de l'appelant. Filtrer par auteur, par source, ou paginer, ne
	 * peut donc plus transformer une correction en « première écriture », ni —
	 * en ordre décroissant — présenter une valeur FUTURE comme l'ancienne.
	 *
	 * Auto-jointure corrélée et JAMAIS `LAG()` : MySQL 5.7 est encore supporté par
	 * WordPress et ne connaît pas les fonctions fenêtre — l'historique serait
	 * vide, en silence. L'index `massif_jour (massif_code,jour_validite,id)` sert
	 * la sous-requête directement.
	 *
	 * `p.id` est sélectionné exprès : c'est le SEUL discriminant entre « aucune
	 * ligne antérieure n'existe » (`precedent_id` nul) et « la ligne antérieure ne
	 * portait aucun niveau » (`precedent_id` non nul, `precedent_niveau_cle` nul).
	 *
	 * `ORDER BY s.enregistre_le DESC, s.id DESC` : `enregistre_le` est à la
	 * seconde, et sans `s.id` en départage la pagination sauterait ou dupliquerait
	 * des lignes. Le tri est en SQL, jamais rejoué en PHP après pagination.
	 *
	 * `niveau_source_brut` et `procedure_source` ne sont sélectionnés NI sur `s`
	 * NI sur `p`.
	 *
	 * @param array<string, mixed> $criteres Critères déjà normalisés par le domaine.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function selectionner_journal( array $criteres ): array {
		global $wpdb;

		$table  = self::nom_table();
		$filtre = $this->clause_journal( $criteres );

		$limite   = isset( $criteres['limite'] ) ? max( 1, min( 5000, (int) $criteres['limite'] ) ) : 500;
		$decalage = isset( $criteres['decalage'] ) ? max( 0, (int) $criteres['decalage'] ) : 0;

		$arguments = array_merge(
			array( $table, $table, $table ),
			$filtre['arguments'],
			array( $limite, $decalage )
		);

		$requete = $wpdb->prepare(
			'SELECT s.id, s.massif_code, s.jour_validite, s.niveau_cle, s.zapef_cle, s.source, s.auteur_id,'
				. ' s.publie_prefecture_le, s.enregistre_le,'
				. ' p.id AS precedent_id, p.niveau_cle AS precedent_niveau_cle, p.zapef_cle AS precedent_zapef_cle'
				. ' FROM %i AS s'
				. ' LEFT JOIN %i AS p ON p.id = ('
				. ' SELECT MAX(t.id) FROM %i AS t'
				. ' WHERE t.massif_code = s.massif_code AND t.jour_validite = s.jour_validite AND t.id < s.id'
				. ' )'
				. $filtre['clause']
				. ' ORDER BY s.enregistre_le DESC, s.id DESC'
				. ' LIMIT %d OFFSET %d',
			$arguments
		);

		$resultats = $wpdb->get_results( $requete, ARRAY_A );

		return is_array( $resultats ) ? array_values( $resultats ) : array();
	}

	/**
	 * Nombre de lignes du journal pour ces critères.
	 *
	 * Même clause que `selectionner_journal()`, sans jointure : le précédent
	 * n'entre dans aucune condition, il ne peut donc pas changer le compte.
	 *
	 * @param array<string, mixed> $criteres Critères déjà normalisés par le domaine.
	 */
	public function compter_journal( array $criteres ): int {
		global $wpdb;

		$filtre = $this->clause_journal( $criteres );

		$requete = $wpdb->prepare(
			'SELECT COUNT(*) AS total FROM %i AS s' . $filtre['clause'],
			array_merge( array( self::nom_table() ), $filtre['arguments'] )
		);

		$ligne = $wpdb->get_row( $requete, ARRAY_A );

		return is_array( $ligne ) && isset( $ligne['total'] ) ? (int) $ligne['total'] : 0;
	}

	/**
	 * Plus grand identifiant de l'ensemble filtré, `0` s'il est vide.
	 *
	 * Fige la fenêtre d'un export : la table étant en insertion pure, `id <= borne`
	 * rend l'ensemble résultat IMMUABLE pendant toute la diffusion, et `OFFSET`
	 * redevient stable — aucune ligne dupliquée, aucune sautée, même si le cron
	 * écrit pendant l'export.
	 *
	 * @param array<string, mixed> $criteres Critères déjà normalisés par le domaine.
	 */
	public function id_max_journal( array $criteres ): int {
		global $wpdb;

		$filtre = $this->clause_journal( $criteres );

		$requete = $wpdb->prepare(
			'SELECT MAX(s.id) AS borne FROM %i AS s' . $filtre['clause'],
			array_merge( array( self::nom_table() ), $filtre['arguments'] )
		);

		$ligne = $wpdb->get_row( $requete, ARRAY_A );

		return is_array( $ligne ) && null !== ( $ligne['borne'] ?? null ) ? (int) $ligne['borne'] : 0;
	}

	/**
	 * Identifiants des auteurs PRÉSENTS dans le journal.
	 *
	 * Jamais la liste des comptes de l'installation : un écran ouvert au
	 * gestionnaire qui listerait tous les comptes WordPress serait une énumération
	 * d'utilisateurs, que le §9 du brief exige de bloquer.
	 *
	 * @return list<int>
	 */
	public function auteurs_journal(): array {
		global $wpdb;

		$requete = $wpdb->prepare(
			'SELECT DISTINCT s.auteur_id FROM %i AS s WHERE s.auteur_id IS NOT NULL ORDER BY s.auteur_id ASC',
			self::nom_table()
		);

		$resultats = $wpdb->get_results( $requete, ARRAY_A );

		if ( ! is_array( $resultats ) ) {
			return array();
		}

		$auteurs = array();

		foreach ( $resultats as $ligne ) {
			$auteur = isset( $ligne['auteur_id'] ) ? (int) $ligne['auteur_id'] : 0;

			if ( $auteur > 0 ) {
				$auteurs[] = $auteur;
			}
		}

		return $auteurs;
	}
}
