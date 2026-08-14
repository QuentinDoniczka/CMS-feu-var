<?php
/**
 * Vocabulaire gelé des rôles et capacités du portail.
 *
 * SOURCE UNIQUE des quatre chaînes gelées par le contrat d'interface #13. Aucune
 * autre classe, aucun autre fichier de l'extension ne réécrit `massifs_publier_statuts`
 * en littéral : une faute de frappe dans un contrôle d'accès n'émet AUCUNE erreur
 * PHP, elle refuse simplement tout le monde — ou pire, n'attrape personne.
 *
 * POURQUOI AUCUN RÔLE « ADMINISTRATEUR » SUR MESURE (arbitrage A-1)
 *
 * Le rôle `administrator` du cœur reçoit les trois capacités par `add_cap`. Cloner
 * `administrator` a été explicitement rejeté : son jeu de capacités grandit à chaque
 * version majeure de WordPress, et un clone figé pourrit en silence.
 *
 * POURQUOI L'ADMINISTRATEUR PORTE AUSSI `publier` ET `historique` (A-2)
 *
 * Un administrateur incapable de publier serait absurde. C'est aussi ce qui rend
 * l'interdit 1 du contrat nécessaire : un contrôle écrit `in_array( 'massifs_gestionnaire',
 * $user->roles )` exclurait l'administrateur de son propre portail. On teste la
 * CAPACITÉ, jamais le rôle.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes de rôle et de capacité, et jeux canoniques.
 *
 * Classe sans état et sans dépendance : elle est lisible par n'importe quel module
 * à n'importe quel moment du cycle de vie, y compris avant `plugins_loaded`.
 */
final class Capacites {

	/**
	 * Identifiant du rôle gestionnaire.
	 */
	public const ROLE_GESTIONNAIRE = 'massifs_gestionnaire';

	/**
	 * Nom affiché du rôle gestionnaire, tel que gelé par le contrat.
	 */
	public const NOM_ROLE_GESTIONNAIRE = 'Gestionnaire des massifs';

	/**
	 * Publier et corriger les statuts.
	 */
	public const PUBLIER = 'massifs_publier_statuts';

	/**
	 * Consulter l'historique et l'exporter.
	 */
	public const HISTORIQUE = 'massifs_consulter_historique';

	/**
	 * Gérer les comptes gestionnaires.
	 */
	public const GERER = 'massifs_gerer_gestionnaires';

	/**
	 * Préfixe reconnu par les gardes.
	 */
	public const PREFIXE = 'massifs_';

	/**
	 * Les trois capacités du portail, dans l'ordre du contrat.
	 *
	 * @return list<string>
	 */
	public static function toutes(): array {
		return array( self::PUBLIER, self::HISTORIQUE, self::GERER );
	}

	/**
	 * Jeu canonique du rôle gestionnaire.
	 *
	 * `read` est OBLIGATOIRE et n'est jamais retiré, même à un compte suspendu :
	 * un utilisateur sans `read` est éjecté de `wp-admin` par le cœur, ce qui
	 * tuerait le portail au lieu de le restreindre.
	 *
	 * Volontairement PAS de `massifs_gerer_gestionnaires` : le §6 du brief réserve
	 * la gestion des comptes à l'administrateur.
	 *
	 * @return array<string, bool>
	 */
	public static function capacites_gestionnaire(): array {
		return array(
			'read'           => true,
			self::PUBLIER    => true,
			self::HISTORIQUE => true,
		);
	}

	/**
	 * Capacités ajoutées au rôle `administrator` du cœur.
	 *
	 * Les trois, jamais `read` : le cœur le porte déjà et nous n'avons rien à
	 * dire sur les capacités natives de l'administrateur.
	 *
	 * @return array<string, bool>
	 */
	public static function capacites_administrateur(): array {
		return array(
			self::PUBLIER    => true,
			self::HISTORIQUE => true,
			self::GERER      => true,
		);
	}

	/**
	 * Cette capacité appartient-elle au portail ?
	 *
	 * DÉFINITION UNIQUE du préfixe. Le résolveur de suspension, la réconciliation
	 * d'installation et le garde REST s'appuient tous les trois dessus : trois
	 * `str_starts_with` recopiés auraient divergé au premier renommage.
	 *
	 * @param string $capacite Nom de capacité.
	 */
	public static function est_capacite_massifs( string $capacite ): bool {
		return str_starts_with( $capacite, self::PREFIXE );
	}
}
