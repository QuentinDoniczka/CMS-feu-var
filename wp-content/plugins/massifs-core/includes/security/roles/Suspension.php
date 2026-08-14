<?php
/**
 * Suspension d'un compte : marquage, lecture et résolveur de capacités.
 *
 * POURQUOI UNE MÉTA PLUS UN RÉSOLVEUR `user_has_cap` (arbitrage A-3)
 *
 * Quatre mécanismes ont été pesés. La suppression est exclue par le §4.2 du brief
 * (elle orphelinerait `wp_massifs_statuts.auteur_id`, qui n'a pas de clé étrangère).
 * `user_status` est morte dans le cœur depuis la fusion MU. Décaper les capacités du
 * compte perd l'état d'origine, donc interdit un rétablissement fidèle.
 *
 * Reste la méta, doublée d'un résolveur `user_has_cap`. C'est le résolveur qui
 * transforme une suspension DÉCLARATIVE en suspension RÉELLE : sans lui, un cookie
 * d'authentification encore valide, ou un chemin d'écriture qu'une chaîne sœur aurait
 * oublié de garder, laisserait le compte publier. Avec lui, il n'existe aucun chemin
 * — REST, écran d'administration, WP-CLI — par lequel un suspendu conserve une
 * capacité `massifs_*`.
 *
 * RÉSIDU ASSUMÉ (A-4) : la méta ne survit pas fonctionnellement à une désactivation
 * de l'extension. Extension désactivée = plus d'écran de publication, plus de routes
 * portail, plus de capacités `massifs_*` du tout. Un gestionnaire suspendu atterrirait
 * sur un tableau de bord vide avec `read` et rien d'autre. Écrit, pas tu.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Roles;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * État de suspension d'un compte.
 *
 * ÉCRITURE STRICTEMENT RÉSERVÉE À CE MODULE (interdit 5 du contrat) : passer par
 * `Comptes::suspendre()` / `retablir()`, qui détruisent les sessions et journalisent.
 * Un `update_user_meta` nu ne fait ni l'un ni l'autre.
 */
final class Suspension {

	/**
	 * Marqueur de suspension : `'1'` ou méta absente.
	 */
	public const META_SUSPENDU = 'massifs_compte_suspendu';

	/**
	 * Instant de suspension, ISO 8601 UTC.
	 */
	public const META_SUSPENDU_LE = 'massifs_compte_suspendu_le';

	/**
	 * Identifiant de l'acteur ayant suspendu.
	 */
	public const META_SUSPENDU_PAR = 'massifs_compte_suspendu_par';

	/**
	 * Mémoïsation par utilisateur, valable pour la requête en cours.
	 *
	 * @var array<int, bool>
	 */
	private static array $memo = array();

	/**
	 * Ce compte est-il suspendu ?
	 *
	 * @param int $user_id Identifiant du compte.
	 */
	public static function est_suspendu( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( array_key_exists( $user_id, self::$memo ) ) {
			return self::$memo[ $user_id ];
		}

		$valeur = get_user_meta( $user_id, self::META_SUSPENDU, true );

		self::$memo[ $user_id ] = '1' === (string) $valeur;

		return self::$memo[ $user_id ];
	}

	/**
	 * Instant de suspension, ou `null` si le compte n'a jamais été suspendu.
	 *
	 * @param int $user_id Identifiant du compte.
	 */
	public static function suspendu_le( int $user_id ): ?string {
		if ( $user_id <= 0 ) {
			return null;
		}

		$valeur = trim( (string) get_user_meta( $user_id, self::META_SUSPENDU_LE, true ) );

		return '' === $valeur ? null : $valeur;
	}

	/**
	 * Marque le compte comme suspendu.
	 *
	 * Ne détruit AUCUNE session et ne journalise rien : c'est le rôle de
	 * `Comptes::suspendre()`, seul point d'entrée légitime.
	 *
	 * @param int    $user_id         Compte visé.
	 * @param int    $acteur_id       Auteur de la décision.
	 * @param string $instant_iso_utc Instant de la décision, ISO 8601 UTC.
	 */
	public static function suspendre( int $user_id, int $acteur_id, string $instant_iso_utc ): void {
		update_user_meta( $user_id, self::META_SUSPENDU, '1' );
		update_user_meta( $user_id, self::META_SUSPENDU_LE, $instant_iso_utc );
		update_user_meta( $user_id, self::META_SUSPENDU_PAR, $acteur_id );

		self::oublier( $user_id );
	}

	/**
	 * Lève la suspension.
	 *
	 * `massifs_compte_suspendu_le` et `_par` sont CONSERVÉS : ils portent la trace
	 * de la dernière suspension, que `massifs_gestionnaires()` expose. Seul le
	 * marqueur d'état disparaît.
	 *
	 * @param int $user_id Compte visé.
	 */
	public static function retablir( int $user_id ): void {
		delete_user_meta( $user_id, self::META_SUSPENDU );

		self::oublier( $user_id );
	}

	/**
	 * Retire les capacités `massifs_*` d'un compte suspendu.
	 *
	 * PERFORMANCE — CE FILTRE S'EXÉCUTE À CHAQUE `current_user_can()`, donc des
	 * dizaines de fois par écran d'administration. Trois gardes, dans cet ordre :
	 *
	 *   1. court-circuit immédiat si aucune clé de `$allcaps` n'est préfixée
	 *      `massifs_` — le cas de très loin le plus fréquent, et il ne coûte qu'un
	 *      parcours de tableau en mémoire ;
	 *   2. mémoïsation par utilisateur pour la requête ;
	 *   3. et seulement alors, la lecture de la méta.
	 *
	 * `read` n'est jamais retiré : sans lui le cœur éjecte le compte de `wp-admin`,
	 * et un suspendu doit pouvoir se voir refuser l'accès au portail, pas se voir
	 * refuser l'existence.
	 *
	 * @param array<string, bool> $allcaps Capacités résolues du compte.
	 * @param array<int, string>  $caps    Capacités primitives demandées, non utilisées.
	 * @param array<int, mixed>   $args    Arguments de l'appel, non utilisés.
	 * @param WP_User|mixed       $user    Compte évalué.
	 *
	 * @return array<string, bool>
	 */
	public static function filtrer_capacites( array $allcaps, array $caps, array $args, mixed $user ): array {
		unset( $caps, $args );

		$concernees = array();

		foreach ( $allcaps as $capacite => $accordee ) {
			if ( Capacites::est_capacite_massifs( (string) $capacite ) ) {
				$concernees[] = (string) $capacite;
			}
		}

		if ( array() === $concernees ) {
			return $allcaps;
		}

		$user_id = $user instanceof WP_User ? (int) $user->ID : 0;

		if ( $user_id <= 0 || ! self::est_suspendu( $user_id ) ) {
			return $allcaps;
		}

		foreach ( $concernees as $capacite ) {
			unset( $allcaps[ $capacite ] );
		}

		return $allcaps;
	}

	/**
	 * Invalide la mémoïsation d'un compte.
	 *
	 * Indispensable : suspendre puis relire l'état dans la même requête — ce que
	 * fait `Comptes::suspendre()` en journalisant — servirait sinon la valeur
	 * d'avant l'écriture.
	 *
	 * @param int $user_id Compte visé.
	 */
	public static function oublier( int $user_id ): void {
		unset( self::$memo[ $user_id ] );
	}
}
