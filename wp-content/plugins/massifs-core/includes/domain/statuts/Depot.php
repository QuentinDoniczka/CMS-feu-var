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
 * seules méthodes de `$wpdb` employées sont `insert`, `prepare`, `get_results`
 * et `insert_id`, plus `get_charset_collate` pour la collation : aucune méthode
 * ni aucun ordre SQL de modification ou de suppression, sous quelque forme que
 * ce soit. L'historique est strictement en insertion pure : une correction est
 * une ligne de plus, jamais un écrasement.
 *
 * Il n'existe AUCUNE méthode « dernier statut connu quel que soit le jour » :
 * ce que le dépôt ne sait pas faire, personne ne peut le lui demander. C'est la
 * garantie structurelle du §4.2.
 *
 * `niveau_source_brut` et `procedure_source` sont ÉCRITS mais jamais SÉLECTIONNÉS :
 * ils existent pour rendre possible une re-projection si la granularité
 * d'affichage change un jour, pas pour être présentés. Ne pas les lire est la
 * façon la plus simple de garantir qu'ils n'atteindront aucune sortie publique.
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
}
