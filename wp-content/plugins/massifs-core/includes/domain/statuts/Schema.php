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
	 */
	public const VERSION = '2.0.0';

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
	 * antérieure à la connaissance du `level` brut.
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
		$sql = 'CREATE TABLE ' . Depot::nom_table() . " (\n"
			. "id bigint(20) unsigned NOT NULL auto_increment,\n"
			. "massif_code varchar(64) NOT NULL,\n"
			. "jour_validite date NOT NULL,\n"
			. "niveau_cle varchar(32) NULL,\n"
			. "zapef_cle varchar(32) DEFAULT NULL,\n"
			. "niveau_source_brut tinyint(3) unsigned DEFAULT NULL,\n"
			. "procedure_source tinyint(3) unsigned DEFAULT NULL,\n"
			. "source varchar(20) NOT NULL,\n"
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
	}
}
