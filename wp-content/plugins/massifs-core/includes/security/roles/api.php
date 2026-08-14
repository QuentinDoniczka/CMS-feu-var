<?php
/**
 * Fonctions de lecture du module « rôles ».
 *
 * Seule surface publique du module pour les chaînes sœurs : aucune d'elles
 * n'instancie ni n'appelle une classe `Massifs\Security\Roles\` pour lire un droit.
 *
 * TOUTES RETOURNENT DES CHAÎNES NON ÉCHAPPÉES : l'appelant échappe au rendu, parce
 * que lui seul connaît son contexte (`esc_html`, `esc_attr`, `esc_url`).
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Massifs\Security\Roles\Capacites;
use Massifs\Security\Roles\Suspension;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_role_gestionnaire' ) ) {
	/**
	 * Identifiant du rôle gestionnaire.
	 */
	function massifs_role_gestionnaire(): string {
		return Capacites::ROLE_GESTIONNAIRE;
	}
}

if ( ! function_exists( 'massifs_capacite_publier' ) ) {
	/**
	 * Capacité « publier et corriger les statuts ».
	 */
	function massifs_capacite_publier(): string {
		return Capacites::PUBLIER;
	}
}

if ( ! function_exists( 'massifs_capacite_historique' ) ) {
	/**
	 * Capacité « consulter l'historique et l'exporter ».
	 */
	function massifs_capacite_historique(): string {
		return Capacites::HISTORIQUE;
	}
}

if ( ! function_exists( 'massifs_capacite_gerer' ) ) {
	/**
	 * Capacité « gérer les comptes gestionnaires ».
	 */
	function massifs_capacite_gerer(): string {
		return Capacites::GERER;
	}
}

if ( ! function_exists( 'massifs_capacites_massifs' ) ) {
	/**
	 * Les trois capacités du portail.
	 *
	 * @return list<string>
	 */
	function massifs_capacites_massifs(): array {
		return Capacites::toutes();
	}
}

if ( ! function_exists( 'massifs_peut_publier' ) ) {
	/**
	 * Ce compte peut-il publier ou corriger les statuts ?
	 *
	 * @param int|null $user_id Compte, `null` pour l'utilisateur courant.
	 */
	function massifs_peut_publier( ?int $user_id = null ): bool {
		return massifs_roles_verifier_capacite( Capacites::PUBLIER, $user_id );
	}
}

if ( ! function_exists( 'massifs_peut_consulter_historique' ) ) {
	/**
	 * Ce compte peut-il consulter et exporter l'historique ?
	 *
	 * @param int|null $user_id Compte, `null` pour l'utilisateur courant.
	 */
	function massifs_peut_consulter_historique( ?int $user_id = null ): bool {
		return massifs_roles_verifier_capacite( Capacites::HISTORIQUE, $user_id );
	}
}

if ( ! function_exists( 'massifs_peut_gerer_gestionnaires' ) ) {
	/**
	 * Ce compte peut-il gérer les comptes gestionnaires ?
	 *
	 * @param int|null $user_id Compte, `null` pour l'utilisateur courant.
	 */
	function massifs_peut_gerer_gestionnaires( ?int $user_id = null ): bool {
		return massifs_roles_verifier_capacite( Capacites::GERER, $user_id );
	}
}

if ( ! function_exists( 'massifs_roles_verifier_capacite' ) ) {
	/**
	 * Contrôle une capacité, pour l'utilisateur courant ou pour un compte donné.
	 *
	 * `current_user_can()` et `user_can()` traversent tous deux le filtre
	 * `user_has_cap`, donc le résolveur de suspension : un compte suspendu échoue
	 * ici sans qu'aucun appelant n'ait à y penser.
	 *
	 * @param string   $capacite Capacité contrôlée.
	 * @param int|null $user_id  Compte, `null` pour l'utilisateur courant.
	 */
	function massifs_roles_verifier_capacite( string $capacite, ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			return current_user_can( $capacite );
		}

		return $user_id > 0 && user_can( $user_id, $capacite );
	}
}

if ( ! function_exists( 'massifs_compte_est_suspendu' ) ) {
	/**
	 * Ce compte est-il suspendu ?
	 *
	 * @param int $user_id Compte concerné.
	 */
	function massifs_compte_est_suspendu( int $user_id ): bool {
		return Suspension::est_suspendu( $user_id );
	}
}

if ( ! function_exists( 'massifs_gestionnaires' ) ) {
	/**
	 * Comptes habilités à publier, pour un filtre « auteur » ou une liste de comptes.
	 *
	 * INTERROGÉS PAR CAPACITÉ, PAS PAR RÔLE (interdit 1) : l'administrateur publie
	 * lui aussi, et une liste construite sur `role => massifs_gestionnaire`
	 * l'omettrait silencieusement du filtre d'historique de ses propres écritures.
	 *
	 * Trié par `nom_affiche`, comparaison insensible à la casse et naturelle, pour
	 * qu'une liste déroulante reste lisible sans que l'appelant retrie.
	 *
	 * @param bool $inclure_suspendus Conserver les comptes suspendus ?
	 *
	 * @return list<array{id:int,identifiant:string,nom_affiche:string,email:string,suspendu:bool,suspendu_le:string|null}>
	 */
	function massifs_gestionnaires( bool $inclure_suspendus = true ): array {
		$comptes = get_users(
			array(
				'capability' => Capacites::PUBLIER,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'fields'     => 'all',
			)
		);

		$liste = array();

		foreach ( $comptes as $compte ) {
			if ( ! $compte instanceof WP_User ) {
				continue;
			}

			$identifiant = (int) $compte->ID;
			$suspendu    = Suspension::est_suspendu( $identifiant );

			if ( $suspendu && ! $inclure_suspendus ) {
				continue;
			}

			$liste[] = array(
				'id'          => $identifiant,
				'identifiant' => (string) $compte->user_login,
				'nom_affiche' => massifs_nom_auteur( $identifiant ),
				'email'       => (string) $compte->user_email,
				'suspendu'    => $suspendu,
				'suspendu_le' => Suspension::suspendu_le( $identifiant ),
			);
		}

		usort(
			$liste,
			static function ( array $gauche, array $droite ): int {
				return strnatcasecmp( $gauche['nom_affiche'], $droite['nom_affiche'] );
			}
		);

		return $liste;
	}
}

if ( ! function_exists( 'massifs_nom_auteur' ) ) {
	/**
	 * Nom d'affichage d'un auteur d'écriture.
	 *
	 * NE RENVOIE JAMAIS UNE CHAÎNE VIDE. `wp_massifs_statuts.auteur_id` est un
	 * `bigint` SANS clé étrangère : un identifiant peut ne plus résoudre, et
	 * l'historique doit rester lisible sans qu'un appelant invente son propre
	 * libellé de repli — trois appelants inventeraient trois libellés.
	 *
	 * @param int $user_id Identifiant d'auteur, éventuellement obsolète.
	 */
	function massifs_nom_auteur( int $user_id ): string {
		$repli = 'Auteur inconnu';

		if ( $user_id <= 0 ) {
			return $repli;
		}

		$compte = get_userdata( $user_id );

		if ( false === $compte ) {
			return $repli;
		}

		$nom = trim( (string) $compte->display_name );

		if ( '' !== $nom ) {
			return $nom;
		}

		$nom = trim( (string) $compte->user_login );

		return '' === $nom ? $repli : $nom;
	}
}
