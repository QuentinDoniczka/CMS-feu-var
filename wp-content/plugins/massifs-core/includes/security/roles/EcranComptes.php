<?php
/**
 * Greffes de gestion des comptes sur l'écran « Utilisateurs » du cœur.
 *
 * POURQUOI AUCUN ÉCRAN DÉDIÉ (arbitrage A-20)
 *
 * `CLAUDE.md` placerait un écran de gestion des comptes dans `includes/admin/comptes/`,
 * chemin HORS de l'empreinte d'écriture de cette chaîne et non attribué dans ce lot.
 * Les greffes ci-dessous sont des FILTRES, enregistrables depuis nos propres fichiers :
 * elles donnent l'essentiel de la valeur de démonstration pour une fraction du coût, et
 * zéro octet écrit hors empreinte.
 *
 * L'action « créer » n'a rien à écrire : `user-new.php` du cœur propose déjà le rôle
 * « Gestionnaire des massifs » dès qu'il est installé. Le §6 du brief exige que les
 * trois actions EXISTENT, pas qu'elles aient leur propre écran.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Roles;

use Massifs\Security\Auth\SecretUtilisateur;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colonne d'état, actions de ligne et actions groupées.
 */
final class EcranComptes {

	/**
	 * Clé de la colonne ajoutée à la liste des utilisateurs.
	 */
	private const COLONNE = 'massifs_portail';

	/**
	 * Paramètre d'action porté par les liens de ligne.
	 */
	private const PARAM_ACTION = 'massifs_action';

	/**
	 * Paramètre d'avis renvoyé après traitement.
	 */
	private const PARAM_AVIS = 'massifs_avis';

	/**
	 * Actions unitaires acceptées.
	 *
	 * @var array<string, string>
	 */
	private const ACTIONS = array(
		'suspendre'     => 'Suspendre',
		'retablir'      => 'Rétablir',
		'reinitialiser' => 'Réinitialiser le mot de passe',
	);

	/**
	 * Ajoute la colonne « Portail ».
	 *
	 * @param array<string, string> $colonnes Colonnes de l'écran.
	 *
	 * @return array<string, string>
	 */
	public static function colonnes( array $colonnes ): array {
		if ( ! Comptes::peut_gerer() ) {
			return $colonnes;
		}

		$colonnes[ self::COLONNE ] = 'Portail';

		return $colonnes;
	}

	/**
	 * Rend la cellule « Portail » d'une ligne.
	 *
	 * @param string $sortie  Contenu déjà produit par un autre abonné.
	 * @param string $colonne Colonne rendue.
	 * @param int    $user_id Compte de la ligne.
	 */
	public static function colonne( string $sortie, string $colonne, int $user_id ): string {
		if ( self::COLONNE !== $colonne ) {
			return $sortie;
		}

		if ( ! Comptes::peut_gerer() ) {
			return $sortie;
		}

		$compte = get_userdata( $user_id );

		if ( false === $compte || ! self::est_du_portail( $compte ) ) {
			return '—';
		}

		$etats = array( Suspension::est_suspendu( $user_id ) ? 'Suspendu' : 'Actif' );

		if ( class_exists( SecretUtilisateur::class ) && SecretUtilisateur::est_actif( $user_id ) ) {
			$etats[] = 'Second facteur actif';
		}

		return esc_html( implode( ' · ', $etats ) );
	}

	/**
	 * Ajoute les actions de ligne sur les comptes du portail.
	 *
	 * @param array<string, string> $actions Actions déjà proposées.
	 * @param WP_User|mixed         $compte  Compte de la ligne.
	 *
	 * @return array<string, string>
	 */
	public static function actions_ligne( array $actions, mixed $compte ): array {
		if ( ! $compte instanceof WP_User || ! Comptes::peut_gerer() ) {
			return $actions;
		}

		if ( ! self::est_du_portail( $compte ) ) {
			return $actions;
		}

		$user_id  = (int) $compte->ID;
		$suspendu = Suspension::est_suspendu( $user_id );

		$proposees = $suspendu
			? array( 'retablir', 'reinitialiser' )
			: array( 'suspendre', 'reinitialiser' );

		if ( get_current_user_id() === $user_id ) {
			// On ne se suspend pas soi-même : le service le refuse déjà, autant ne
			// pas proposer un bouton qui ne peut qu'échouer.
			$proposees = array_values( array_diff( $proposees, array( 'suspendre' ) ) );
		}

		foreach ( $proposees as $action ) {
			$actions[ 'massifs_' . $action ] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::lien( $action, $user_id ) ),
				esc_html( self::ACTIONS[ $action ] )
			);
		}

		return $actions;
	}

	/**
	 * Ajoute les actions groupées de suspension et de rétablissement.
	 *
	 * `reinitialiser` n'est volontairement PAS proposée en masse : une
	 * réinitialisation envoie un courriel par compte, et un clic malheureux sur une
	 * liste de cent comptes serait irrattrapable.
	 *
	 * @param array<string, string> $actions Actions groupées.
	 *
	 * @return array<string, string>
	 */
	public static function actions_groupees( array $actions ): array {
		if ( ! Comptes::peut_gerer() ) {
			return $actions;
		}

		$actions['massifs_suspendre'] = 'Suspendre (portail)';
		$actions['massifs_retablir']  = 'Rétablir (portail)';

		return $actions;
	}

	/**
	 * Exécute une action groupée.
	 *
	 * Le nonce de l'écran de liste est vérifié par le cœur AVANT ce filtre
	 * (`bulk-users`) ; la capacité est revérifiée par chaque service.
	 *
	 * @param string            $redirection URL de retour.
	 * @param string            $action      Action demandée.
	 * @param array<int, mixed> $comptes     Comptes cochés.
	 */
	public static function traiter_actions_groupees( string $redirection, string $action, array $comptes ): string {
		if ( 'massifs_suspendre' !== $action && 'massifs_retablir' !== $action ) {
			return $redirection;
		}

		$traites = 0;
		$echecs  = 0;

		foreach ( $comptes as $brut ) {
			$user_id = absint( $brut );

			if ( $user_id <= 0 ) {
				continue;
			}

			$resultat = 'massifs_suspendre' === $action
				? Comptes::suspendre( $user_id )
				: Comptes::retablir( $user_id );

			if ( $resultat instanceof WP_Error ) {
				++$echecs;

				continue;
			}

			++$traites;
		}

		$redirection = remove_query_arg( array( self::PARAM_AVIS, 'massifs_traites', 'massifs_echecs' ), $redirection );

		return add_query_arg(
			array(
				self::PARAM_AVIS  => 'massifs_suspendre' === $action ? 'suspendus' : 'retablis',
				'massifs_traites' => $traites,
				'massifs_echecs'  => $echecs,
			),
			$redirection
		);
	}

	/**
	 * Traite une action de ligne.
	 *
	 * Ordre non négociable : présence du paramètre, assainissement, nonce,
	 * délégation au service — qui recontrôle la capacité —, redirection, `exit`.
	 * La redirection après écriture évite qu'un rafraîchissement rejoue l'action.
	 */
	public static function traiter_actions(): void {
		if ( ! is_admin() || ! isset( $_GET[ self::PARAM_ACTION ] ) ) {
			return;
		}

		$action  = sanitize_key( wp_unslash( (string) $_GET[ self::PARAM_ACTION ] ) );
		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( (string) $_GET['user'] ) ) : 0;

		if ( ! array_key_exists( $action, self::ACTIONS ) || $user_id <= 0 ) {
			return;
		}

		check_admin_referer( self::nonce( $action, $user_id ) );

		if ( ! Comptes::peut_gerer() ) {
			wp_die(
				esc_html( "Vous n'avez pas le droit de gérer les comptes du portail." ),
				esc_html( 'Droits insuffisants' ),
				array( 'response' => 403 )
			);
		}

		$resultat = match ( $action ) {
			'suspendre'     => Comptes::suspendre( $user_id ),
			'retablir'      => Comptes::retablir( $user_id ),
			'reinitialiser' => Comptes::reinitialiser( $user_id ),
		};

		$avis = $resultat instanceof WP_Error ? 'erreur' : $action;

		wp_safe_redirect(
			add_query_arg(
				array( self::PARAM_AVIS => $avis ),
				admin_url( 'users.php' )
			)
		);

		exit;
	}

	/**
	 * Affiche l'avis consécutif à une action.
	 */
	public static function notices(): void {
		if ( ! isset( $_GET[ self::PARAM_AVIS ] ) ) {
			return;
		}

		// Lecture seule d'un paramètre d'affichage, sans effet de bord : le nonce a
		// été vérifié au moment de l'écriture, pas au moment d'afficher son résultat.
		$avis = sanitize_key( wp_unslash( (string) $_GET[ self::PARAM_AVIS ] ) );

		$messages = array(
			'suspendre'     => 'Compte suspendu. Ses sessions ont été fermées.',
			'retablir'      => 'Compte rétabli.',
			'reinitialiser' => 'Courriel de réinitialisation envoyé. Les sessions du compte ont été fermées.',
			'suspendus'     => 'Comptes suspendus.',
			'retablis'      => 'Comptes rétablis.',
			'erreur'        => "L'action n'a pas pu être effectuée.",
		);

		if ( ! isset( $messages[ $avis ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'erreur' === $avis ? 'error' : 'success',
			esc_html( $messages[ $avis ] )
		);
	}

	/**
	 * Ce compte relève-t-il du portail ?
	 *
	 * Le rôle, et non la capacité : un gestionnaire suspendu n'a plus de capacité
	 * `massifs_*` et disparaîtrait de l'écran qui sert précisément à le rétablir.
	 *
	 * @param WP_User $compte Compte évalué.
	 */
	private static function est_du_portail( WP_User $compte ): bool {
		return in_array( Capacites::ROLE_GESTIONNAIRE, (array) $compte->roles, true );
	}

	/**
	 * Lien d'action, porteur de son nonce.
	 *
	 * @param string $action  Action demandée.
	 * @param int    $user_id Compte visé.
	 */
	private static function lien( string $action, int $user_id ): string {
		$url = add_query_arg(
			array(
				self::PARAM_ACTION => $action,
				'user'             => $user_id,
			),
			admin_url( 'users.php' )
		);

		return wp_nonce_url( $url, self::nonce( $action, $user_id ) );
	}

	/**
	 * Nom du nonce d'une action.
	 *
	 * Porte l'action ET le compte : un nonce valide pour « suspendre le compte 12 »
	 * ne vaut ni pour un autre compte, ni pour une autre action.
	 *
	 * @param string $action  Action demandée.
	 * @param int    $user_id Compte visé.
	 */
	private static function nonce( string $action, int $user_id ): string {
		return 'massifs_compte_' . $action . '_' . $user_id;
	}
}
