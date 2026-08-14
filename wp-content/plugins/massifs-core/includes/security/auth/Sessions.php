<?php
/**
 * Durée de vie et destruction des sessions du portail.
 *
 * POINT DE PASSAGE UNIQUE (arbitrage A-16). C'est LA ligne qui porte la DoD :
 * une suspension qui laisse vivre la session en cours n'est pas une suspension, le
 * compte continuerait de publier des statuts pendant des heures. `detruire()` est
 * appelée EXPLICITEMENT depuis la suspension, la réinitialisation administrateur, le
 * changement de rôle et le changement de secret TOTP — jamais en se reposant sur le
 * comportement implicite du cœur, qui varie selon le chemin d'appel.
 *
 * DISTINCTION QUI COMPTE
 *
 *   `detruire()`        — décision SUBIE par le compte : suspension, réinitialisation
 *                         par un administrateur, changement de rôle. Toutes les
 *                         sessions tombent, y compris celle en cours.
 *   `detruire_autres()` — décision PRISE PAR le compte lui-même : il change son mot de
 *                         passe, il enrôle son second facteur. Le déconnecter de son
 *                         propre navigateur en pleine action serait hostile, et le
 *                         pousserait à choisir un mot de passe qu'il retape vite.
 *
 * POURQUOI AUCUNE EXPIRATION D'INACTIVITÉ (A-15)
 *
 * Les cookies WordPress expirent de façon ABSOLUE, pas glissante. Une vraie
 * inactivité exigerait un `last_seen` contrôlé et réécrit à chaque requête
 * d'administration, soit une écriture en base par vue de page — coûteux sur
 * mutualisé, pour un apport faible face à un plafond absolu de 4 h sur une tâche
 * quotidienne d'une minute. « Sessions expirantes » (§6 du brief) est honnêtement
 * tenu par l'expiration absolue courte. Le crochet est déclaré (`inactivite_max()`),
 * son application n'est délibérément pas écrite.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use Massifs\Security\Roles\Capacites;
use WP_Session_Tokens;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sortie de session unique du portail.
 */
final class Sessions {

	/**
	 * Session d'un compte portant `massifs_gerer_gestionnaires` : 4 h.
	 *
	 * Valeurs en secondes et non en `HOUR_IN_SECONDS` : une constante de classe
	 * évaluée à la compilation ne doit dépendre d'aucune constante d'exécution.
	 */
	public const DUREE_ADMINISTRATEUR = 14400;

	/**
	 * Session d'un gestionnaire : 8 h, soit une journée de service.
	 */
	public const DUREE_GESTIONNAIRE = 28800;

	/**
	 * Plafond de « se souvenir de moi » : 12 h.
	 *
	 * La case est CONSERVÉE — la retirer priverait l'utilisateur d'un choix sans
	 * rien gagner. Elle est plafonnée, pas supprimée. Le défaut du cœur (48 h, et
	 * 14 jours avec la case) est sans rapport avec une tâche quotidienne d'une
	 * minute.
	 */
	public const DUREE_SOUVENIR = 43200;

	/**
	 * Durée de vie du cookie d'authentification.
	 *
	 * N'agit que sur les comptes du portail : un compte sans capacité `massifs_*`
	 * garde la durée du cœur, ce module n'ayant pas à arbitrer pour lui.
	 *
	 * @param int  $expiration  Durée proposée, en secondes.
	 * @param int  $user_id     Compte concerné.
	 * @param bool $se_souvenir La case « se souvenir de moi » est-elle cochée ?
	 */
	public static function duree( int $expiration, int $user_id, bool $se_souvenir ): int {
		if ( ! class_exists( Capacites::class ) ) {
			return $expiration;
		}

		$peut_gerer = user_can( $user_id, Capacites::GERER );
		$du_portail = $peut_gerer || user_can( $user_id, Capacites::PUBLIER ) || user_can( $user_id, Capacites::HISTORIQUE );

		if ( ! $du_portail ) {
			return $expiration;
		}

		$duree = $peut_gerer ? self::DUREE_ADMINISTRATEUR : self::DUREE_GESTIONNAIRE;

		if ( $se_souvenir ) {
			$duree = max( $duree, self::DUREE_SOUVENIR );
		}

		/**
		 * Durée de session d'un compte du portail, en secondes.
		 *
		 * @param int  $duree       Durée retenue.
		 * @param int  $user_id     Compte concerné.
		 * @param bool $se_souvenir Case « se souvenir de moi ».
		 */
		$filtree = (int) apply_filters( 'massifs_auth_duree_session', $duree, $user_id, $se_souvenir );

		// Plancher d'une minute : un filtre à zéro ou négatif produirait un cookie
		// déjà expiré, donc une boucle de connexion infinie.
		return max( 60, $filtree );
	}

	/**
	 * Détruit TOUTES les sessions du compte, y compris celle en cours.
	 *
	 * @param int $user_id Compte visé.
	 */
	public static function detruire( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$jetons = WP_Session_Tokens::get_instance( $user_id );
		$jetons->destroy_all();
	}

	/**
	 * Détruit toutes les sessions SAUF celle en cours.
	 *
	 * Sans session courante identifiable — appel depuis WP-CLI ou depuis une tâche
	 * planifiée — la seule lecture sûre est « toutes les sessions sont des autres ».
	 *
	 * @param int $user_id Compte visé.
	 */
	public static function detruire_autres( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$jeton = wp_get_session_token();

		if ( '' === $jeton || get_current_user_id() !== $user_id ) {
			self::detruire( $user_id );

			return;
		}

		$jetons = WP_Session_Tokens::get_instance( $user_id );
		$jetons->destroy_others( $jeton );
	}

	/**
	 * Réagit à un changement de mot de passe passé par `wp_update_user`.
	 *
	 * L'empreinte est comparée : `profile_update` se déclenche à chaque
	 * enregistrement du profil, la très grande majorité sans toucher au mot de
	 * passe.
	 *
	 * @param int           $user_id Compte modifié.
	 * @param WP_User|mixed $ancien  État du compte avant modification.
	 */
	public static function sur_mise_a_jour_profil( int $user_id, mixed $ancien ): void {
		if ( ! $ancien instanceof WP_User ) {
			return;
		}

		$nouveau = get_userdata( $user_id );

		if ( false === $nouveau || $nouveau->user_pass === $ancien->user_pass ) {
			return;
		}

		if ( get_current_user_id() === $user_id ) {
			self::detruire_autres( $user_id );

			return;
		}

		// Mot de passe changé par un tiers : décision subie, tout tombe.
		self::detruire( $user_id );
	}

	/**
	 * Point d'extension déclaré pour une expiration d'inactivité.
	 *
	 * DÉCLARÉ, VOLONTAIREMENT NON APPLIQUÉ (A-15). Il existe pour qu'une évolution
	 * future ait un nom stable où se brancher, et pour que l'absence
	 * d'implémentation soit une décision lisible plutôt qu'un oubli. Renvoie `0`
	 * par défaut : aucune inactivité maximale.
	 */
	public static function inactivite_max(): int {
		/**
		 * Inactivité maximale tolérée, en secondes. `0` = aucune.
		 *
		 * @param int $secondes Valeur par défaut.
		 */
		return max( 0, (int) apply_filters( 'massifs_auth_inactivite_max', 0 ) );
	}
}
