<?php
/**
 * Fermeture des voies d'authentification qui contournent le formulaire de connexion.
 *
 * RAISON D'ÊTRE, EN UNE PHRASE : les mots de passe d'application et XML-RPC
 * s'authentifient en Basic auth et NE TRAVERSENT JAMAIS `wp-login.php` — les premiers
 * passent par `determine_current_user`, pas par `authenticate` — de sorte que cocher
 * « double authentification active pour les administrateurs » en les laissant ouverts
 * serait une ligne de DoD tout simplement fausse.
 *
 * Ce n'est pas une fermeture de confort : c'est ce qui rend vraie l'affirmation du §6
 * du brief. Un attaquant en possession d'un mot de passe d'application se connecterait
 * en REST avec les pleins droits, sans jamais rencontrer le second facteur.
 *
 * Les deux fermetures sont réversibles par filtre, défaut `false` dans les deux cas :
 * un site qui a réellement besoin d'un client mobile doit pouvoir l'ouvrir en
 * connaissance de cause, pas le découvrir ouvert.
 *
 * HORS PÉRIMÈTRE, ET ASSUMÉ : `xmlrpc.php` continue de répondre au niveau HTTP. Le
 * bloquer côté serveur relève de la configuration (`.htaccess`, vhost), donc d'une
 * issue d'infrastructure. Ici, il ne fait plus rien.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Neutralisation des canaux d'authentification parallèles.
 */
final class Fermetures {

	/**
	 * Les mots de passe d'application sont-ils disponibles sur ce site ?
	 *
	 * @param bool $disponible Décision courante.
	 */
	public static function mots_de_passe_application( bool $disponible ): bool {
		return self::mots_de_passe_application_autorises() && $disponible;
	}

	/**
	 * Les mots de passe d'application sont-ils disponibles pour ce compte ?
	 *
	 * Le cœur pose DEUX filtres, un global et un par compte. Ne couvrir que le
	 * premier laisserait la porte entrouverte selon le chemin d'appel.
	 *
	 * @param bool  $disponible Décision courante.
	 * @param mixed $compte     Compte évalué, non utilisé.
	 */
	public static function mots_de_passe_application_pour( bool $disponible, mixed $compte = null ): bool {
		unset( $compte );

		return self::mots_de_passe_application_autorises() && $disponible;
	}

	/**
	 * XML-RPC est-il actif ?
	 *
	 * @param bool $actif Décision courante.
	 */
	public static function xmlrpc( bool $actif ): bool {
		return self::xmlrpc_autorise() && $actif;
	}

	/**
	 * Méthodes XML-RPC exposées.
	 *
	 * `xmlrpc_enabled` ne coupe que les méthodes AUTHENTIFIÉES : `pingback.ping` et
	 * consorts resteraient servies. Vider la table est ce qui ferme réellement la
	 * surface.
	 *
	 * @param array<string, mixed> $methodes Méthodes déclarées.
	 *
	 * @return array<string, mixed>
	 */
	public static function methodes_xmlrpc( array $methodes ): array {
		return self::xmlrpc_autorise() ? $methodes : array();
	}

	/**
	 * Retire l'en-tête `X-Pingback`.
	 *
	 * Il annonce une capacité que nous venons de retirer : le laisser invite des
	 * requêtes automatisées inutiles sur un point d'entrée mort.
	 *
	 * @param array<string, string> $entetes En-têtes de la réponse.
	 * @param mixed                 $wp      Environnement, non utilisé.
	 *
	 * @return array<string, string>
	 */
	public static function retirer_pingback( array $entetes, mixed $wp = null ): array {
		unset( $wp );

		if ( self::xmlrpc_autorise() ) {
			return $entetes;
		}

		unset( $entetes['X-Pingback'] );

		return $entetes;
	}

	/**
	 * Les mots de passe d'application sont-ils autorisés par configuration ?
	 */
	private static function mots_de_passe_application_autorises(): bool {
		/**
		 * Autoriser les mots de passe d'application ?
		 *
		 * @param bool $autorises Défaut `false`.
		 */
		return (bool) apply_filters( 'massifs_auth_autoriser_mots_de_passe_application', false );
	}

	/**
	 * XML-RPC est-il autorisé par configuration ?
	 */
	private static function xmlrpc_autorise(): bool {
		/**
		 * Autoriser XML-RPC ?
		 *
		 * @param bool $autorise Défaut `false`.
		 */
		return (bool) apply_filters( 'massifs_auth_autoriser_xmlrpc', false );
	}
}
