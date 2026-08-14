<?php
/**
 * Filet fail-closed sur les écritures REST du portail, et `permission_callback`
 * partagés.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  NE JAMAIS UTILISER `rest_authentication_errors` — NI AUCUN FILTRE GLOBAL     │
 * │  D'AUTHENTIFICATION REST — POUR REJETER LES ANONYMES.                         │
 * │                                                                               │
 * │  CE FILTRE COURT-CIRCUITE `WP_REST_Server::dispatch` POUR TOUTE L'API. IL     │
 * │  RENVERRAIT 401 SUR `GET /massifs/v1/statuts`, CASSANT LE §5.4 DU BRIEF       │
 * │  (DONNÉES OUVERTES) ET LA CARTE PUBLIQUE, QUI LA CONSOMME. C'EST LE RÉFLEXE   │
 * │  NATUREL, ET IL EST FAUX.                                                     │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI LE FILTRE PAR MÉTHODE EST PORTEUR, PAS COSMÉTIQUE
 *
 * `massifs/v1` est un espace de noms PARTAGÉ : `includes/rest/public/route-statuts.php`
 * y déclare `GET /massifs/v1/statuts` avec `permission_callback => '__return_true'`,
 * imposé par le §5.4. Filtrer par espace de noms seul casserait l'open data. `GET`,
 * `HEAD` et `OPTIONS` passent donc intacts, toujours.
 *
 * Le préfixe testé est `massifs`, pas `massifs/v1` : si une chaîne sœur choisit
 * `massifs-portail/v1`, le garde mord quand même.
 *
 * CE FILET NE COUVRE PAS LES ROUTES EN LECTURE. L'historique est une lecture
 * sensible : la chaîne qui le sert DOIT poser son propre `permission_callback`.
 *
 * Le nonce des requêtes REST authentifiées par cookie reste géré par le cœur
 * (`rest_cookie_check_errors`, en-tête `X-WP-Nonce`). Il n'est pas réimplémenté ici.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use Massifs\Security\Roles\Capacites;
use WP_Error;
use WP_REST_Request;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Garde global des écritures REST et contrôles de permission partagés.
 */
final class GardeRest {

	/**
	 * Méthodes considérées comme des lectures et laissées intactes.
	 *
	 * @var list<string>
	 */
	private const METHODES_LECTURE = array( 'GET', 'HEAD', 'OPTIONS' );

	/**
	 * Code d'erreur du filet global sur les écritures.
	 *
	 * Contractuel : il est publié dans le contrat d'interface #13 et les chaînes
	 * sœurs le lisent dans leurs réponses. Constante plutôt que littéral répété,
	 * comme `Ecluse::CODE_REFUS` et `MessageConnexion::CODE`.
	 */
	private const CODE_ECRITURE = 'massifs_ecriture_non_autorisee';

	/**
	 * Code d'erreur des `permission_callback` partagés.
	 */
	private const CODE_DROITS = 'massifs_droits_insuffisants';

	/**
	 * Code d'erreur du second facteur exigé mais non enrôlé.
	 */
	private const CODE_2FA = 'massifs_2fa_requise';

	/**
	 * Message du second facteur exigé mais non enrôlé.
	 *
	 * Émis par les deux chemins — filet global et `permission_callback` — et donc
	 * énoncé une seule fois : deux copies auraient divergé au premier reformulage.
	 */
	private const MESSAGE_2FA = 'Un second facteur est requis sur ce compte. Terminez son enrôlement depuis votre profil.';

	/**
	 * Refuse toute écriture non autorisée sur un espace de noms `massifs*`.
	 *
	 * L'ORDRE DES TESTS EST CONTRACTUEL et ne se réarrange pas.
	 *
	 * @param mixed                $reponse Réponse déjà produite, ou `null`.
	 * @param array<string, mixed> $handler Descripteur de route, non utilisé.
	 * @param WP_REST_Request      $requete Requête entrante.
	 *
	 * @return mixed
	 */
	public static function garder( mixed $reponse, array $handler, WP_REST_Request $requete ): mixed {
		unset( $handler );

		// Une erreur déjà posée par le cœur ou par un autre abonné passe intacte :
		// la remplacer masquerait la cause réelle du refus.
		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		if ( ! str_starts_with( ltrim( $requete->get_route(), '/' ), 'massifs' ) ) {
			return $reponse;
		}

		if ( in_array( strtoupper( $requete->get_method() ), self::METHODES_LECTURE, true ) ) {
			return $reponse;
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				self::CODE_ECRITURE,
				'Une authentification est requise pour écrire sur le portail.',
				array( 'status' => 401 )
			);
		}

		// FAIL-CLOSED, DÉLIBÉRÉMENT L'INVERSE DE `AccesCompte` : sans le vocabulaire
		// des capacités, aucune écriture ne peut être autorisée en connaissance de
		// cause. Refuser une écriture est réversible ; en laisser passer une ne
		// l'est pas.
		if ( ! class_exists( Capacites::class ) ) {
			return new WP_Error(
				self::CODE_ECRITURE,
				"Le portail n'est pas disponible : ses droits ne sont pas chargés.",
				array( 'status' => 403 )
			);
		}

		if ( ! self::porte_une_capacite_portail() ) {
			return new WP_Error(
				self::CODE_ECRITURE,
				"Votre compte n'a pas le droit d'écrire sur le portail.",
				array( 'status' => 403 )
			);
		}

		if ( self::second_facteur_manquant() ) {
			return new WP_Error(
				self::CODE_2FA,
				self::MESSAGE_2FA,
				array( 'status' => 403 )
			);
		}

		return $reponse;
	}

	/**
	 * `permission_callback` : publier ou corriger les statuts.
	 *
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 *
	 * @return true|WP_Error
	 */
	public static function peut_publier( WP_REST_Request $requete ): true|WP_Error {
		unset( $requete );

		return self::exiger( Capacites::PUBLIER );
	}

	/**
	 * `permission_callback` : consulter et exporter l'historique.
	 *
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 *
	 * @return true|WP_Error
	 */
	public static function peut_consulter_historique( WP_REST_Request $requete ): true|WP_Error {
		unset( $requete );

		return self::exiger( Capacites::HISTORIQUE );
	}

	/**
	 * `permission_callback` : gérer les comptes gestionnaires.
	 *
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 *
	 * @return true|WP_Error
	 */
	public static function peut_gerer_gestionnaires( WP_REST_Request $requete ): true|WP_Error {
		unset( $requete );

		return self::exiger( Capacites::GERER );
	}

	/**
	 * Contrôle une capacité pour la requête courante.
	 *
	 * Un compte suspendu échoue ici SANS code dédié : le résolveur `user_has_cap`
	 * lui a déjà retiré ses capacités `massifs_*`.
	 *
	 * Un seul code d'erreur, `massifs_droits_insuffisants`, avec 401 ou 403 selon
	 * que l'appelant est anonyme ou insuffisamment habilité — un code par cause
	 * renseignerait l'attaquant sans rien apporter au client légitime.
	 *
	 * @param string $capacite Capacité exigée.
	 *
	 * @return true|WP_Error
	 */
	private static function exiger( string $capacite ): true|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				self::CODE_DROITS,
				'Une authentification est requise.',
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( $capacite ) ) {
			return new WP_Error(
				self::CODE_DROITS,
				"Votre compte n'a pas le droit d'effectuer cette opération.",
				array( 'status' => 403 )
			);
		}

		if ( self::second_facteur_manquant() ) {
			return new WP_Error(
				self::CODE_DROITS,
				self::MESSAGE_2FA,
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Le compte courant porte-t-il au moins une capacité du portail ?
	 */
	private static function porte_une_capacite_portail(): bool {
		foreach ( Capacites::toutes() as $capacite ) {
			if ( current_user_can( $capacite ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Le second facteur est-il exigé de ce compte sans être enrôlé ?
	 *
	 * Ferme le seul vrai trou de la rampe d'enrôlement : celle-ci ne s'applique
	 * qu'à `wp-admin`, l'API REST doit donc poser la même exigence de son côté.
	 */
	private static function second_facteur_manquant(): bool {
		if ( ! class_exists( Deuxfacteurs::class ) ) {
			return false;
		}

		$compte = wp_get_current_user();

		if ( ! $compte instanceof WP_User || 0 === (int) $compte->ID ) {
			return false;
		}

		return Deuxfacteurs::est_requis( $compte ) && ! Deuxfacteurs::est_actif( (int) $compte->ID );
	}
}
