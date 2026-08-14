<?php
/**
 * Installation et réconciliation du rôle gestionnaire.
 *
 * POURQUOI DES CAPACITÉS PERSISTÉES (arbitrage A-5)
 *
 * Le rôle est écrit dans `wp_user_roles`, pas résolu dynamiquement à chaque requête.
 * L'alternative — tout résoudre par `user_has_cap` — laisse `get_role()->capabilities`
 * vide, ce qui envoie sur une fausse piste quiconque débogue « le gestionnaire ne peut
 * pas publier ». Le seul vrai défaut de la persistance, la dérive, est annulé par le
 * mécanisme `massifs_core_signature_schema` / `massifs_core_installation` déjà en
 * service : il rejoue l'installation dès que la signature change.
 *
 * POURQUOI `installer()` RÉCONCILIE AU LIEU D'AJOUTER
 *
 * Il ajoute les capacités déclarées absentes ET retire les `massifs_*` présentes mais
 * non déclarées. Sans ce second temps, une capacité renommée traînerait pour toujours
 * sur les rôles déjà installés : les comptes existants garderaient un droit que le
 * code ne connaît plus, sans la moindre erreur.
 *
 * POURQUOI AUCUNE DÉSINSTALLATION, AUCUN NETTOYAGE À LA DÉSACTIVATION
 *
 * Retirer le rôle détruirait l'affectation de rôle de chaque gestionnaire — une
 * désactivation accidentelle de l'extension coûterait la reconstruction manuelle de
 * tous les comptes. C'est la doctrine déjà posée par `Domain\Statuts\Schema`.
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
 * Propriétaire exclusif du rôle `massifs_gestionnaire` et des `add_cap` sur
 * `administrator` (interdit 7 du contrat).
 */
final class Installation {

	/**
	 * Nom du module dans la signature de schéma.
	 */
	public const MODULE = 'securite-roles';

	/**
	 * Version du jeu de rôles et capacités.
	 *
	 * À incrémenter à CHAQUE modification du vocabulaire de `Capacites` : c'est
	 * l'unique déclencheur du rejeu de la réconciliation sur une base déjà
	 * installée.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Déclare la version du module au mécanisme de signature.
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
	 * Crée le rôle gestionnaire s'il manque, puis réconcilie les capacités.
	 *
	 * Idempotent : `add_role` est sans effet si le rôle existe, et chaque `add_cap`
	 * / `remove_cap` n'est émis que sur un écart réel.
	 *
	 * @param string $signature_precedente Signature enregistrée avant ce passage, non utilisée.
	 */
	public static function installer( string $signature_precedente = '' ): void {
		unset( $signature_precedente );

		add_role(
			Capacites::ROLE_GESTIONNAIRE,
			Capacites::NOM_ROLE_GESTIONNAIRE,
			Capacites::capacites_gestionnaire()
		);

		self::reconcilier( Capacites::ROLE_GESTIONNAIRE, Capacites::capacites_gestionnaire(), true );

		// Sur `administrator`, on AJOUTE sans jamais retirer. Le rôle appartient au
		// cœur et à l'exploitant du site : y émettre des `remove_cap` reviendrait à
		// décider, depuis une extension, de ce qu'un administrateur a le droit de
		// faire. Le résidu possible — une capacité `massifs_*` renommée qui traîne —
		// est sans portée sur un compte qui les porte toutes.
		self::reconcilier( 'administrator', Capacites::capacites_administrateur(), false );
	}

	/**
	 * Aligne les capacités d'un rôle sur le jeu déclaré.
	 *
	 * Le retrait ne porte QUE sur les capacités préfixées `massifs_` : ce module
	 * n'a rien à dire sur `edit_posts` ni sur aucune capacité du cœur, et un
	 * retrait large transformerait la réconciliation en machine à casser
	 * l'administrateur.
	 *
	 * `read` n'est jamais retiré : il figure dans le jeu déclaré du gestionnaire et
	 * n'est de toute façon pas préfixé.
	 *
	 * @param string              $identifiant Identifiant du rôle.
	 * @param array<string, bool> $declarees   Capacités déclarées pour ce rôle.
	 * @param bool                $retirer     Retirer les `massifs_*` non déclarées ?
	 */
	private static function reconcilier( string $identifiant, array $declarees, bool $retirer ): void {
		$role = get_role( $identifiant );

		// Une table de rôles corrompue ou un `administrator` supprimé à la main ne
		// doit pas faire tomber l'amorçage : la réconciliation est reportée au
		// prochain changement de signature, le site continue de booter.
		if ( null === $role ) {
			return;
		}

		foreach ( $declarees as $capacite => $accordee ) {
			if ( ! $role->has_cap( $capacite ) ) {
				$role->add_cap( $capacite, $accordee );
			}
		}

		if ( ! $retirer ) {
			return;
		}

		// Copie explicite : `remove_cap` mute `$role->capabilities` pendant
		// l'itération.
		$presentes = array_keys( (array) $role->capabilities );

		foreach ( $presentes as $capacite ) {
			$nom = (string) $capacite;

			if ( ! Capacites::est_capacite_massifs( $nom ) ) {
				continue;
			}

			if ( array_key_exists( $nom, $declarees ) ) {
				continue;
			}

			$role->remove_cap( $nom );
		}
	}
}
