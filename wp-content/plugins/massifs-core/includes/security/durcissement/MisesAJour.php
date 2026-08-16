<?php
/**
 * Verrouillage de la politique de mise à jour automatique.
 *
 * LA POLITIQUE, EN UNE PHRASE : le cœur se corrige seul en mineur, rien d'autre ne
 * bouge sans décision humaine, et un échec ne reste jamais silencieux.
 *
 * POURQUOI LES EXTENSIONS ET LES THÈMES SONT À `false`
 *
 * Le site n'héberge qu'une extension et qu'un thème, tous deux SUR MESURE et NON
 * DISTRIBUÉS : ils n'ont pas d'entrée sur wordpress.org, donc aucune source d'où
 * une mise à jour automatique pourrait venir. Laisser le mécanisme actif
 * n'apporterait rien et exposerait au scénario du « slug volé » — une extension
 * publique portant le même identifiant serait installée à la place de la nôtre.
 *
 * POURQUOI LES MAJEURES SONT À `false`
 *
 * Une majeure change le cœur sous un thème sur mesure et une extension qui greffe
 * `map_meta_cap`, `authenticate`, `rest_endpoints` et le rendu de l'écran des
 * comptes. Elle se conduit à la main, avec sauvegarde et recette : la procédure est
 * dans le README du module.
 *
 * CHAQUE RAPPEL REND LA POLITIQUE, EN IGNORANT LA DÉCISION ENTRANTE. C'est le
 * propre d'une politique : elle tranche, elle ne négocie pas. Le coupe-circuit d'un
 * exploitant reste entier, parce que `automatic_updater_disabled` n'est PAS posé
 * (A-8) et qu'il court-circuite tout ce fichier en amont.
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Durcissement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Politique de mise à jour automatique du cœur, des extensions et des thèmes.
 */
final class MisesAJour {

	/**
	 * Les mises à jour mineures du cœur sont-elles automatiques ?
	 *
	 * @param mixed $decision Décision courante, ignorée.
	 */
	public static function mineures( mixed $decision = null ): bool {
		unset( $decision );

		return Politique::mises_a_jour_mineures();
	}

	/**
	 * Les mises à jour majeures du cœur sont-elles automatiques ?
	 *
	 * @param mixed $decision Décision courante, ignorée.
	 */
	public static function majeures( mixed $decision = null ): bool {
		unset( $decision );

		return Politique::mises_a_jour_majeures();
	}

	/**
	 * Une extension doit-elle se mettre à jour automatiquement ?
	 *
	 * @param mixed $decision Décision courante, ignorée.
	 * @param mixed $element  Extension évaluée, non utilisée.
	 */
	public static function extensions( mixed $decision = null, mixed $element = null ): bool {
		unset( $decision, $element );

		return Politique::mises_a_jour_extensions();
	}

	/**
	 * Un thème doit-il se mettre à jour automatiquement ?
	 *
	 * @param mixed $decision Décision courante, ignorée.
	 * @param mixed $element  Thème évalué, non utilisé.
	 */
	public static function themes( mixed $decision = null, mixed $element = null ): bool {
		unset( $decision, $element );

		return Politique::mises_a_jour_themes();
	}

	/**
	 * Le rapport de mise à jour du cœur doit-il être envoyé par courriel ?
	 *
	 * TOUJOURS `true`, y compris sur un succès. Sans rapport, l'échec d'une mise à
	 * jour mineure est SILENCIEUX : le site resterait sur une version vulnérable
	 * sans que personne ne l'apprenne, et la promesse du §9 deviendrait
	 * invérifiable. Le courriel de succès est le battement de cœur qui rend son
	 * absence significative.
	 *
	 * @param mixed $envoyer     Décision courante, ignorée.
	 * @param mixed $type        Type de résultat, non utilisé.
	 * @param mixed $mise_a_jour Descripteur de mise à jour, non utilisé.
	 * @param mixed $resultat    Résultat de l'opération, non utilisé.
	 */
	public static function courriel_de_rapport( mixed $envoyer = null, mixed $type = null, mixed $mise_a_jour = null, mixed $resultat = null ): bool {
		unset( $envoyer, $type, $mise_a_jour, $resultat );

		return true;
	}
}
