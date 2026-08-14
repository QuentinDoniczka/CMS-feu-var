<?php
/**
 * Politique de mot de passe du portail.
 *
 * DOCTRINE : LONGUEUR PLUTÔT QUE COMPLEXITÉ (NIST SP 800-63B). AUCUNE RÈGLE DE
 * COMPOSITION — pas de majuscule obligatoire, pas de chiffre obligatoire, pas de
 * caractère spécial obligatoire. Les règles de composition produisent `Motdepasse1!`
 * et rien d'autre : elles donnent une impression de rigueur en réduisant l'espace de
 * recherche réel, et elles poussent l'utilisateur à noter son mot de passe. Douze
 * caractères libres valent mieux que huit contraints.
 *
 * Trois refus seulement, tous justifiés par une attaque réelle :
 *
 *   1. moins de douze caractères — attaque par force brute hors ligne ;
 *   2. présent dans la liste des mots de passe triviaux — attaque par dictionnaire ;
 *   3. contenant l'identifiant ou le nom du site — première devinette de tout attaquant.
 *
 * Le plafond de 4096 caractères n'est pas une contrainte de sécurité mais une garde
 * contre un déni de service : `wp_hash_password` sur une chaîne d'un mégaoctet coûte
 * cher, et rien ne l'empêche autrement.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation des mots de passe, à la création comme à la réinitialisation.
 */
final class Motdepasse {

	/**
	 * Longueur minimale retenue.
	 *
	 * Jugement d'ingénierie, pas un fait du brief : le §6 dit « mots de passe forts
	 * imposés » sans donner de chiffre.
	 */
	public const LONGUEUR_MIN = 12;

	/**
	 * Plancher DUR de la longueur minimale.
	 *
	 * Un filtre ne peut pas descendre sous cette valeur : le crochet existe pour
	 * durcir la politique ou l'assouplir à la marge, jamais pour la désactiver.
	 */
	private const PLANCHER_DUR = 8;

	/**
	 * Longueur maximale acceptée.
	 */
	private const LONGUEUR_MAX = 4096;

	/**
	 * Longueur minimale effective.
	 */
	public static function longueur_min(): int {
		/**
		 * Longueur minimale d'un mot de passe du portail.
		 *
		 * @param int $longueur Longueur par défaut.
		 */
		$proposee = (int) apply_filters( 'massifs_auth_longueur_mot_de_passe', self::LONGUEUR_MIN );

		return max( self::PLANCHER_DUR, $proposee );
	}

	/**
	 * Valide un mot de passe.
	 *
	 * @param string       $mot_de_passe Mot de passe soumis, jamais journalisé.
	 * @param WP_User|null $utilisateur  Compte concerné, s'il est connu.
	 *
	 * @return true|WP_Error
	 */
	public static function valider( string $mot_de_passe, ?WP_User $utilisateur = null ): true|WP_Error {
		$identifiant = null === $utilisateur ? '' : (string) $utilisateur->user_login;

		return self::verifier( $mot_de_passe, $identifiant );
	}

	/**
	 * Contrôle le mot de passe soumis depuis un écran de profil.
	 *
	 * Couvre `user-new.php`, `profile.php` et `user-edit.php` : le cœur y renseigne
	 * `user_pass` sur l'objet transmis UNIQUEMENT quand un mot de passe a été saisi.
	 * Un profil enregistré sans toucher au mot de passe ne passe donc aucun contrôle,
	 * ce qui est le comportement voulu.
	 *
	 * @param WP_Error $erreurs     Collecteur d'erreurs du cœur.
	 * @param bool     $mise_a_jour S'agit-il d'une modification ? Non utilisé.
	 * @param object   $utilisateur Données soumises.
	 */
	public static function controler_profil( WP_Error $erreurs, bool $mise_a_jour, object $utilisateur ): void {
		unset( $mise_a_jour );

		$mot_de_passe = isset( $utilisateur->user_pass ) ? (string) $utilisateur->user_pass : '';

		if ( '' === $mot_de_passe ) {
			return;
		}

		$identifiant = isset( $utilisateur->user_login ) ? (string) $utilisateur->user_login : '';
		$resultat    = self::verifier( $mot_de_passe, $identifiant );

		if ( $resultat instanceof WP_Error ) {
			$erreurs->add( $resultat->get_error_code(), $resultat->get_error_message() );
		}
	}

	/**
	 * Contrôle le mot de passe soumis depuis le formulaire de réinitialisation.
	 *
	 * `pass1` est lu SANS `sanitize_text_field` : assainir un mot de passe le
	 * modifierait silencieusement, et le compte serait créé avec une valeur que
	 * l'utilisateur n'a jamais tapée. `wp_unslash` seul, exactement comme le cœur.
	 * La légitimité de la requête est établie par la clé de réinitialisation que le
	 * cœur a déjà vérifiée avant d'atteindre ce filtre.
	 *
	 * @param WP_Error $erreurs     Collecteur d'erreurs du cœur.
	 * @param mixed    $utilisateur Compte concerné, ou `WP_Error`.
	 */
	public static function controler_reinitialisation( WP_Error $erreurs, mixed $utilisateur = null ): void {
		if ( ! isset( $_POST['pass1'] ) ) {
			return;
		}

		$mot_de_passe = (string) wp_unslash( $_POST['pass1'] );

		if ( '' === $mot_de_passe ) {
			return;
		}

		$identifiant = $utilisateur instanceof WP_User ? (string) $utilisateur->user_login : '';
		$resultat    = self::verifier( $mot_de_passe, $identifiant );

		if ( $resultat instanceof WP_Error ) {
			$erreurs->add( $resultat->get_error_code(), $resultat->get_error_message() );
		}
	}

	/**
	 * Applique les trois refus.
	 *
	 * @param string $mot_de_passe Mot de passe soumis.
	 * @param string $identifiant  Identifiant du compte, s'il est connu.
	 *
	 * @return true|WP_Error
	 */
	private static function verifier( string $mot_de_passe, string $identifiant ): true|WP_Error {
		$longueur = mb_strlen( $mot_de_passe );
		$minimum  = self::longueur_min();

		if ( $longueur > self::LONGUEUR_MAX ) {
			return new WP_Error(
				'massifs_mot_de_passe_trop_long',
				sprintf( 'Le mot de passe ne peut pas dépasser %d caractères.', self::LONGUEUR_MAX )
			);
		}

		if ( $longueur < $minimum ) {
			return new WP_Error(
				'massifs_mot_de_passe_trop_court',
				sprintf(
					'Le mot de passe doit compter au moins %d caractères. Une phrase de passe fait très bien l’affaire : aucune majuscule, aucun chiffre et aucun caractère spécial n’est exigé.',
					$minimum
				)
			);
		}

		$normalise = mb_strtolower( trim( $mot_de_passe ) );

		if ( in_array( $normalise, self::interdits(), true ) ) {
			return new WP_Error(
				'massifs_mot_de_passe_trivial',
				'Ce mot de passe est trop courant. Choisissez une phrase de passe qui vous est propre.'
			);
		}

		if ( self::contient( $normalise, $identifiant ) ) {
			return new WP_Error(
				'massifs_mot_de_passe_previsible',
				'Le mot de passe ne doit pas contenir votre identifiant.'
			);
		}

		if ( self::contient( $normalise, (string) get_bloginfo( 'name' ) ) ) {
			return new WP_Error(
				'massifs_mot_de_passe_previsible',
				'Le mot de passe ne doit pas contenir le nom du site.'
			);
		}

		return true;
	}

	/**
	 * Le mot de passe contient-il ce fragment ?
	 *
	 * Les fragments de moins de quatre caractères sont ignorés : refuser tout mot de
	 * passe contenant « ma » n'aurait aucun sens.
	 *
	 * @param string $normalise Mot de passe déjà normalisé en minuscules.
	 * @param string $fragment  Fragment recherché.
	 */
	private static function contient( string $normalise, string $fragment ): bool {
		$cible = mb_strtolower( trim( $fragment ) );

		if ( mb_strlen( $cible ) < 4 ) {
			return false;
		}

		return str_contains( $normalise, $cible );
	}

	/**
	 * Liste des mots de passe triviaux, chargée une seule fois.
	 *
	 * @return list<string>
	 */
	private static function interdits(): array {
		static $liste = null;

		if ( null !== $liste ) {
			return $liste;
		}

		$chemin = __DIR__ . '/motsdepasse-interdits.config.php';

		// Garde `is_file` : l'arbre de travail est partagé, et un fichier de
		// configuration absent doit dégrader la politique d'un cran, jamais faire
		// tomber le changement de mot de passe.
		$brut = is_file( $chemin ) ? require $chemin : array();

		$liste = array();

		if ( is_array( $brut ) ) {
			foreach ( $brut as $entree ) {
				if ( is_string( $entree ) && '' !== $entree ) {
					$liste[] = mb_strtolower( $entree );
				}
			}
		}

		return $liste;
	}
}
