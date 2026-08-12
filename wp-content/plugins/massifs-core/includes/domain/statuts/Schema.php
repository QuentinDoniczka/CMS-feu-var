<?php
/**
 * Schéma de la table des statuts.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Création et migration de la table des statuts.
 *
 * La table est SIMULTANÉMENT l'historique intégral et le journal d'écriture :
 * deux tables signifieraient deux vérités possibles. « Ancienne → nouvelle
 * valeur » se dérive de la ligne précédente du même couple (massif, jour).
 *
 * AUCUNE CLÉ UNIQUE sur (massif_code, jour_validite) : une unique
 * transformerait mécaniquement toute correction en écrasement et détruirait
 * l'historique exigé par le §4.2. Une correction est une ligne de plus.
 *
 * Aucun `uninstall.php`, aucune suppression de table à la désactivation :
 * détruire l'historique intégral sur une désactivation accidentelle
 * contredirait le §4.2.
 */
final class Schema {

	/**
	 * Nom du module dans la signature de schéma.
	 */
	public const MODULE = 'statuts';

	/**
	 * Version du schéma du module.
	 *
	 * 2.0.0 : `niveau_cle` devient nullable, ajout de `zapef_cle`,
	 * `niveau_source_brut` et `procedure_source`.
	 * 2.1.0 : `source` passe de `varchar(20)` à `varchar(32)` — la valeur
	 * `recuperation_officielle` fait 23 caractères et était SILENCIEUSEMENT
	 * refusée par MySQL ; correction explicite de la nullabilité de `niveau_cle`
	 * sur les bases déjà installées, que `dbDelta` ne sait pas produire.
	 */
	public const VERSION = '2.1.0';

	/**
	 * Colonnes dont la nullabilité réelle est vérifiée à chaque installation.
	 *
	 * @var list<string>
	 */
	private const COLONNES_A_RENDRE_NULLABLES = array( 'niveau_cle' );

	/**
	 * Déclare la version du schéma du module.
	 *
	 * @param array<string, string> $signatures Signatures des modules.
	 *
	 * @return array<string, string>
	 */
	public static function signature( array $signatures ): array {
		$signatures[ self::MODULE ] = self::VERSION;

		return $signatures;
	}

	/**
	 * Crée ou met à niveau la table des statuts.
	 *
	 * Handler idempotent : `dbDelta` compare la structure existante et n'agit
	 * que sur les écarts. Aucune migration de données n'est nécessaire en
	 * 2.0.0 : les trois colonnes ajoutées sont nullables et les lignes existantes
	 * les portent à `NULL`, ce qui est exactement leur sens pour une écriture
	 * antérieure à la connaissance du `level` brut. L'élargissement d'un
	 * `varchar` en 2.1.0 est en revanche un changement de TYPE, que `dbDelta`
	 * sait produire, et il ne tronque aucune valeur déjà stockée.
	 *
	 * @param string $signature_precedente Signature de schéma enregistrée avant ce passage.
	 */
	public static function installer( string $signature_precedente = '' ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Mise en forme imposée par `dbDelta` : une définition par ligne, deux
		// espaces après `PRIMARY KEY`, `KEY` et jamais `INDEX`, chaque index
		// nommé, aucune espace après les virgules des listes de colonnes, types
		// en minuscules, aucun accent grave. Un seul de ces points manqué fait
		// rejouer un `ALTER` à chaque chargement de page.
		//
		// `niveau_cle` est NULLABLE : `NULL` signifie « la source a publié une
		// ligne qui ne porte aucun statut d'accès » (`level` 0). C'est un fait
		// distinct de « la source n'a rien publié », qui est l'absence de ligne —
		// même si le visiteur voit un seul et même état dans les deux cas.
		//
		// `niveau_source_brut` et `procedure_source` conservent la valeur EXACTE
		// émise par la source. La perdre serait irréversible : c'est elle, et elle
		// seule, qui rendra possible une re-projection si le propriétaire arbitre
		// plus tard une granularité d'affichage plus fine. Elles sont `NULL` pour
		// une saisie manuelle, qui n'a pas de `level`.
		//
		// LARGEURS MESURÉES, JAMAIS SUPPOSÉES — une valeur trop longue est refusée
		// par MySQL sans exception PHP, donc en silence, ce qui a réellement tué la
		// chaîne d'ingestion officielle en `varchar(20)` :
		//
		//   `source`      valeurs de SourceStatut : `recuperation_officielle` 23 car.,
		//                 `saisie_manuelle` 15 car. → 32, soit 9 de marge.
		//   `niveau_cle`  clés de legende.config.php : `autorise` 8, `interdit` 8 → 32.
		//   `zapef_cle`   mêmes clés, 8 car. → 32.
		//   `massif_code` codes du référentiel : `collines-de-gardanne` 20 car. au plus
		//                 long ; la forme admise par le domaine plafonne de toute façon
		//                 à 64 caractères, la colonne ne peut donc pas être trop courte.
		//
		// Toute valeur ajoutée à `SourceStatut` doit être remesurée ici.
		$sql = 'CREATE TABLE ' . Depot::nom_table() . " (\n"
			. "id bigint(20) unsigned NOT NULL auto_increment,\n"
			. "massif_code varchar(64) NOT NULL,\n"
			. "jour_validite date NOT NULL,\n"
			. "niveau_cle varchar(32) NULL,\n"
			. "zapef_cle varchar(32) DEFAULT NULL,\n"
			. "niveau_source_brut tinyint(3) unsigned DEFAULT NULL,\n"
			. "procedure_source tinyint(3) unsigned DEFAULT NULL,\n"
			. "source varchar(32) NOT NULL,\n"
			. "auteur_id bigint(20) unsigned DEFAULT NULL,\n"
			. "publie_prefecture_le datetime DEFAULT NULL,\n"
			. "enregistre_le datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "KEY jour_massif (jour_validite,massif_code,id),\n"
			. "KEY massif_jour (massif_code,jour_validite,id),\n"
			. "KEY enregistre_le (enregistre_le),\n"
			. "KEY auteur_id (auteur_id)\n"
			. ') ' . Depot::collation() . ';';

		dbDelta( $sql );

		self::corriger_nullabilite();
	}

	/**
	 * Corrige la nullabilité que `dbDelta` ne sait pas produire.
	 *
	 * `dbDelta` ne compare QUE le type d'une colonne, jamais sa nullabilité : une
	 * base installée quand `niveau_cle` était `NOT NULL` reste `NOT NULL` pour
	 * toujours, et la première ligne à `level` 0 — « la source a publié qu'elle
	 * n'a pas d'information » — est rejetée par MySQL. Le défaut ne se voit pas
	 * sur une installation vierge, seulement sur une base déjà installée, donc en
	 * production.
	 *
	 * IDEMPOTENT PAR VÉRIFICATION D'ÉTAT, pas par répétition : l'`ALTER` n'est
	 * émis que si la colonne est réellement en `NOT NULL`. Un second passage
	 * n'altère rien. Et cette méthode n'est de toute façon appelée que depuis
	 * `installer()`, donc uniquement quand la signature de schéma a changé.
	 */
	private static function corriger_nullabilite(): void {
		$depot = new Depot();

		foreach ( self::COLONNES_A_RENDRE_NULLABLES as $colonne ) {
			// `false ===` strictement : une colonne absente ou illisible rend `null`
			// et ne doit surtout pas déclencher un `ALTER` à l'aveugle.
			if ( false === $depot->colonne_accepte_null( $colonne ) ) {
				$depot->rendre_colonne_nullable( $colonne );
			}
		}
	}
}
