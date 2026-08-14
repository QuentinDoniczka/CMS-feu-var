<?php
/**
 * Les trois actions de gestion de compte promises par le §6 du brief.
 *
 * Créer, suspendre, réinitialiser. JAMAIS supprimer — voir `proteger_meta_caps()`.
 *
 * POURQUOI CHAQUE SERVICE REVÉRIFIE LA CAPACITÉ
 *
 * Un écran d'administration est une AFFORDANCE : il montre ou cache un bouton. Ce
 * n'est pas un contrôle d'accès. Ces méthodes sont appelables depuis WP-CLI, depuis
 * une tâche planifiée, depuis une route REST d'une chaîne sœur, ou depuis un écran
 * qu'un futur développeur écrira sans lire ce fichier. Chacune commence donc par son
 * propre `current_user_can( Capacites::GERER )` et renvoie un `WP_Error` en 403.
 * Un contrôle en trop n'a jamais cassé un site ; un contrôle manquant, si.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Roles;

use Massifs\Security\Auth\SecretUtilisateur;
use Massifs\Security\Auth\Sessions;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Services de gestion des comptes gestionnaires.
 */
final class Comptes {

	/**
	 * Format d'échange des instants : ISO 8601 en UTC.
	 *
	 * Recopié plutôt qu'emprunté à `Domain\Fraicheur\Horloge` : la couche sécurité
	 * ne dépend d'aucun module de domaine, et un module de domaine absent ne doit
	 * pas empêcher de suspendre un compte.
	 */
	private const FORMAT_ISO_UTC = 'Y-m-d\TH:i:s\Z';

	/**
	 * Crée un compte gestionnaire.
	 *
	 * LE MOT DE PASSE N'EST JAMAIS RETOURNÉ ni affiché. Un mot de passe aléatoire
	 * est posé, puis le courriel du cœur invite le titulaire à définir le sien :
	 * c'est la seule façon d'obtenir un secret que l'administrateur n'a jamais vu,
	 * et donc de rendre l'imputation de l'historique honnête.
	 *
	 * @param array<string, mixed> $donnees `identifiant`, `email`, `nom_affiche` (facultatif).
	 *
	 * @return int|WP_Error Identifiant du compte créé.
	 */
	public static function creer( array $donnees ): int|WP_Error {
		$refus = self::exiger_gestion();

		if ( $refus instanceof WP_Error ) {
			return $refus;
		}

		$identifiant = sanitize_user( (string) ( $donnees['identifiant'] ?? '' ), true );
		$email       = sanitize_email( (string) ( $donnees['email'] ?? '' ) );
		$nom_affiche = sanitize_text_field( (string) ( $donnees['nom_affiche'] ?? '' ) );

		if ( '' === $identifiant || ! validate_username( $identifiant ) ) {
			return new WP_Error(
				'massifs_identifiant_invalide',
				"L'identifiant fourni n'est pas utilisable.",
				array( 'status' => 400 )
			);
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'massifs_email_invalide',
				"L'adresse de courriel fournie n'est pas valide.",
				array( 'status' => 400 )
			);
		}

		if ( username_exists( $identifiant ) ) {
			return new WP_Error(
				'massifs_identifiant_existant',
				'Cet identifiant est déjà utilisé.',
				array( 'status' => 409 )
			);
		}

		if ( email_exists( $email ) ) {
			return new WP_Error(
				'massifs_email_existant',
				'Cette adresse de courriel est déjà utilisée.',
				array( 'status' => 409 )
			);
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $identifiant,
				'user_email'   => $email,
				'display_name' => '' === $nom_affiche ? $identifiant : $nom_affiche,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'role'         => Capacites::ROLE_GESTIONNAIRE,
			)
		);

		if ( $user_id instanceof WP_Error ) {
			return $user_id;
		}

		$user_id = (int) $user_id;

		// Courriel du cœur « définissez votre mot de passe » : jamais un mot de
		// passe en clair transmis par courriel, et aucune réimplémentation de la
		// génération de clé.
		wp_new_user_notification( $user_id, null, 'user' );

		self::emettre( 'compte_cree', $user_id, $identifiant, array( 'role' => Capacites::ROLE_GESTIONNAIRE ) );

		return $user_id;
	}

	/**
	 * Suspend un compte.
	 *
	 * @param int $user_id Compte visé.
	 *
	 * @return true|WP_Error
	 */
	public static function suspendre( int $user_id ): true|WP_Error {
		$cible = self::cible( $user_id );

		if ( $cible instanceof WP_Error ) {
			return $cible;
		}

		// Se suspendre soi-même produirait un portail sans administrateur joignable,
		// et l'opération n'est pas réversible sans accès à la base.
		if ( get_current_user_id() === $cible->ID ) {
			return new WP_Error(
				'massifs_auto_suspension',
				'Vous ne pouvez pas suspendre votre propre compte.',
				array( 'status' => 403 )
			);
		}

		/**
		 * Autoriser la suspension d'un compte administrateur ?
		 *
		 * Défaut `false` : suspendre le dernier administrateur enfermerait le site
		 * dehors, et rien dans le §6 ne demande de suspendre un administrateur.
		 *
		 * @param bool    $autorise Autorisation.
		 * @param WP_User $cible    Compte visé.
		 */
		$autorise_admin = (bool) apply_filters( 'massifs_autoriser_suspension_administrateur', false, $cible );

		if ( ! $autorise_admin && in_array( 'administrator', (array) $cible->roles, true ) ) {
			return new WP_Error(
				'massifs_suspension_administrateur',
				'Un compte administrateur ne peut pas être suspendu depuis le portail.',
				array( 'status' => 403 )
			);
		}

		if ( Suspension::est_suspendu( $cible->ID ) ) {
			return true;
		}

		Suspension::suspendre( $cible->ID, get_current_user_id(), self::maintenant() );

		self::couper_les_sessions( $cible->ID );
		self::emettre( 'compte_suspendu', $cible->ID, (string) $cible->user_login, array() );

		return true;
	}

	/**
	 * Rétablit un compte suspendu.
	 *
	 * @param int $user_id Compte visé.
	 *
	 * @return true|WP_Error
	 */
	public static function retablir( int $user_id ): true|WP_Error {
		$cible = self::cible( $user_id );

		if ( $cible instanceof WP_Error ) {
			return $cible;
		}

		if ( ! Suspension::est_suspendu( $cible->ID ) ) {
			return true;
		}

		Suspension::retablir( $cible->ID );

		self::emettre( 'compte_retabli', $cible->ID, (string) $cible->user_login, array() );

		return true;
	}

	/**
	 * Réinitialise le mot de passe d'un compte.
	 *
	 * `retrieve_password()` du cœur est utilisée telle quelle : elle génère la clé,
	 * la hache, l'horodate et envoie le courriel. Réimplémenter une génération de
	 * clé de réinitialisation serait la façon la plus sûre d'introduire une faille
	 * là où le cœur n'en a pas.
	 *
	 * @param int  $user_id           Compte visé.
	 * @param bool $reinitialiser_2fa Retirer aussi le second facteur ?
	 *
	 * @return true|WP_Error
	 */
	public static function reinitialiser( int $user_id, bool $reinitialiser_2fa = false ): true|WP_Error {
		$cible = self::cible( $user_id );

		if ( $cible instanceof WP_Error ) {
			return $cible;
		}

		$envoi = retrieve_password( (string) $cible->user_login );

		if ( $envoi instanceof WP_Error ) {
			return $envoi;
		}

		if ( $reinitialiser_2fa && class_exists( SecretUtilisateur::class ) ) {
			SecretUtilisateur::desactiver( $cible->ID );
		}

		// Décision SUBIE par le compte : toutes ses sessions tombent, y compris
		// celle d'un éventuel intrus qui justifie la réinitialisation.
		self::couper_les_sessions( $cible->ID );

		self::emettre(
			'compte_reinitialise',
			$cible->ID,
			(string) $cible->user_login,
			array( 'second_facteur_reinitialise' => $reinitialiser_2fa )
		);

		return true;
	}

	/**
	 * Ce compte peut-il gérer les comptes gestionnaires ?
	 *
	 * @param int|null $user_id Compte, `null` pour l'utilisateur courant.
	 */
	public static function peut_gerer( ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			return current_user_can( Capacites::GERER );
		}

		return user_can( $user_id, Capacites::GERER );
	}

	/**
	 * Interdit la suppression d'un compte gestionnaire.
	 *
	 * BLOQUÉE, pas seulement « non proposée » (interdit 6, arbitrage A-19).
	 * `wp_massifs_statuts.auteur_id` est un `bigint` SANS clé étrangère : la
	 * réattribution native de WordPress ne couvre que les types de contenu, jamais
	 * une table sur mesure. Supprimer un compte orphelinerait l'historique que le
	 * §4.2 exige de conserver intégralement.
	 *
	 * ICI, ET ICI SEULEMENT, LE TEST PORTE SUR LE RÔLE ET NON SUR LA CAPACITÉ.
	 * Ce n'est pas une entorse à l'interdit 1, qui vise les contrôles d'accès : on
	 * protège les comptes DONT CE MODULE EST PROPRIÉTAIRE, identifiés par leur rôle.
	 * Un test par capacité protégerait en prime tous les administrateurs — hors
	 * sujet — et surtout LAISSERAIT SUPPRIMER un gestionnaire suspendu, dont le
	 * résolveur de suspension a précisément retiré les capacités `massifs_*`.
	 *
	 * @param array<int, string> $caps    Capacités primitives requises.
	 * @param string             $cap     Méta-capacité demandée.
	 * @param int                $user_id Compte demandeur, non utilisé.
	 * @param array<int, mixed>  $args    Arguments : `$args[0]` est le compte visé.
	 *
	 * @return array<int, string>
	 */
	public static function proteger_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
		unset( $user_id );

		if ( 'delete_user' !== $cap && 'remove_user' !== $cap ) {
			return $caps;
		}

		$cible_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

		if ( $cible_id <= 0 ) {
			return $caps;
		}

		$cible = get_userdata( $cible_id );

		if ( false === $cible || ! in_array( Capacites::ROLE_GESTIONNAIRE, (array) $cible->roles, true ) ) {
			return $caps;
		}

		/**
		 * Autoriser la suppression d'un compte gestionnaire ?
		 *
		 * Échappatoire documentée, défaut `false`. Un site qui l'active accepte
		 * d'orpheliner des lignes d'historique.
		 *
		 * @param bool    $autorise Autorisation.
		 * @param WP_User $cible    Compte visé.
		 */
		if ( (bool) apply_filters( 'massifs_autoriser_suppression_gestionnaire', false, $cible ) ) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Coupe les sessions quand le rôle d'un compte change.
	 *
	 * Un changement de rôle est un changement de privilèges : la session en cours
	 * porte encore les droits d'avant. Elle tombe.
	 *
	 * @param int                $user_id       Compte modifié.
	 * @param string             $role          Nouveau rôle.
	 * @param array<int, string> $anciens_roles Rôles précédents.
	 */
	public static function sur_changement_de_role( int $user_id, string $role, array $anciens_roles ): void {
		// `set_user_role` se déclenche aussi quand le rôle est réaffecté à
		// l'identique, par exemple à l'enregistrement d'un profil inchangé.
		if ( array( $role ) === array_values( $anciens_roles ) ) {
			return;
		}

		self::couper_les_sessions( $user_id );
	}

	/**
	 * Coupe les sessions après une réinitialisation de mot de passe aboutie.
	 *
	 * Point de passage explicite : le cœur détruit déjà les sessions sur ce chemin,
	 * mais son comportement varie selon la version et selon l'appelant, et la DoD ne
	 * peut pas reposer sur un effet de bord.
	 *
	 * @param WP_User|mixed $utilisateur   Compte concerné.
	 * @param string        $nouveau_mot_de_passe Nouveau mot de passe, non utilisé.
	 */
	public static function sur_reinitialisation( mixed $utilisateur, string $nouveau_mot_de_passe = '' ): void {
		unset( $nouveau_mot_de_passe );

		if ( ! $utilisateur instanceof WP_User ) {
			return;
		}

		self::couper_les_sessions( (int) $utilisateur->ID );
	}

	/**
	 * Émet l'évènement de compte.
	 *
	 * @param string               $type        Type d'évènement.
	 * @param int                  $cible_id    Compte visé.
	 * @param string               $cible_login Identifiant du compte visé.
	 * @param array<string, mixed> $details     Détails complémentaires.
	 */
	private static function emettre( string $type, int $cible_id, string $cible_login, array $details ): void {
		/**
		 * Évènement de compte du portail.
		 *
		 * Point d'accroche public : une chaîne sœur peut alimenter son propre audit
		 * sans que ce module la connaisse.
		 *
		 * @param array<string, mixed> $evenement Charge de l'évènement.
		 */
		do_action(
			'massifs_compte_evenement',
			array(
				'type'            => $type,
				'cible_id'        => $cible_id,
				'cible_login'     => $cible_login,
				'acteur_id'       => get_current_user_id(),
				'instant_iso_utc' => self::maintenant(),
				'details'         => $details,
			)
		);
	}

	/**
	 * Refuse l'appelant s'il ne porte pas la capacité de gestion.
	 *
	 * @return true|WP_Error
	 */
	private static function exiger_gestion(): true|WP_Error {
		if ( current_user_can( Capacites::GERER ) ) {
			return true;
		}

		return new WP_Error(
			'massifs_droits_insuffisants',
			"Vous n'avez pas le droit de gérer les comptes du portail.",
			array( 'status' => 403 )
		);
	}

	/**
	 * Résout le compte visé après contrôle de la capacité de gestion.
	 *
	 * @param int $user_id Compte visé.
	 *
	 * @return WP_User|WP_Error
	 */
	private static function cible( int $user_id ): WP_User|WP_Error {
		$refus = self::exiger_gestion();

		if ( $refus instanceof WP_Error ) {
			return $refus;
		}

		$cible = get_userdata( absint( $user_id ) );

		if ( false === $cible ) {
			return new WP_Error(
				'massifs_compte_introuvable',
				'Ce compte est introuvable.',
				array( 'status' => 404 )
			);
		}

		return $cible;
	}

	/**
	 * Détruit les sessions du compte, si la couche d'authentification est chargée.
	 *
	 * @param int $user_id Compte visé.
	 */
	private static function couper_les_sessions( int $user_id ): void {
		if ( class_exists( Sessions::class ) ) {
			Sessions::detruire( $user_id );
		}
	}

	/**
	 * Instant courant, ISO 8601 UTC.
	 */
	private static function maintenant(): string {
		return gmdate( self::FORMAT_ISO_UTC );
	}
}
