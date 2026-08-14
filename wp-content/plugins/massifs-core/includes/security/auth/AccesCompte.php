<?php
/**
 * Refus de connexion d'un compte suspendu.
 *
 * POURQUOI PRIORITÉ 30, DONC APRÈS LE CŒUR (priorité 20)
 *
 * Le refus n'agit QUE si `$utilisateur` est déjà un `WP_User`, c'est-à-dire seulement
 * une fois les identifiants validés. Refuser plus tôt transformerait la suspension en
 * ORACLE D'EXISTENCE DE COMPTE : « ce compte est suspendu » renseignerait un attaquant
 * qui n'a pas le mot de passe, exactement ce que l'uniformisation du message
 * d'identifiants invalides s'emploie à empêcher.
 *
 * ASYMÉTRIE ASSUMÉE AVEC `GardeRest`, ET C'EST DÉLIBÉRÉ
 *
 *   Ici                → FAIL OPEN. Si le module des rôles n'est pas chargé, la
 *                        connexion passe.
 *   Dans `GardeRest`   → FAIL CLOSED. Si le module des rôles n'est pas chargé,
 *                        l'écriture REST est refusée.
 *
 * Les deux choix opposés répondent à la même question — « que se passe-t-il si un
 * déploiement est partiel ? » — et à des conséquences opposées. Refuser une ÉCRITURE
 * est réversible : on réessaie. Refuser une CONNEXION ne l'est pas : personne ne peut
 * plus entrer dans l'administration pour réparer, et le site est mort jusqu'à un accès
 * SSH. Un déploiement partiel ne doit jamais murer la production dehors.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use Massifs\Security\Roles\Suspension;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Garde d'accès au compte, au moment de l'authentification.
 */
final class AccesCompte {

	/**
	 * Code d'erreur du refus.
	 *
	 * Jamais uniformisé avec les identifiants invalides : le message est émis
	 * SEULEMENT après validation des identifiants, il ne révèle donc rien que le
	 * titulaire légitime ne sache déjà.
	 */
	public const CODE_REFUS = 'massifs_compte_suspendu';

	/**
	 * Message contractuel du refus.
	 */
	public const MESSAGE = 'Ce compte est suspendu. Contactez un administrateur.';

	/**
	 * Refuse la connexion d'un compte suspendu.
	 *
	 * @param mixed  $utilisateur  Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant  Identifiant soumis, non utilisé.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function refuser_si_suspendu( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $identifiant, $mot_de_passe );

		if ( ! $utilisateur instanceof WP_User ) {
			return $utilisateur;
		}

		if ( ! class_exists( Suspension::class ) ) {
			return $utilisateur;
		}

		if ( ! Suspension::est_suspendu( (int) $utilisateur->ID ) ) {
			return $utilisateur;
		}

		return new WP_Error( self::CODE_REFUS, self::MESSAGE );
	}
}
