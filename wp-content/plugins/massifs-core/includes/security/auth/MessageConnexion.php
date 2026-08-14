<?php
/**
 * Uniformisation du message d'échec de connexion.
 *
 * POURQUOI CETTE CLASSE EXISTE (arbitrage A-18)
 *
 * Le cœur distingue `invalid_username` de `incorrect_password` et le DIT à
 * l'utilisateur : « cet identifiant n'existe pas » contre « le mot de passe fourni
 * est incorrect ». Le formulaire de connexion devient alors un ÉNUMÉRATEUR DE
 * COMPTES, et la valeur du verrouillage est divisée par deux — un attaquant peut
 * établir la liste des identifiants valides sans jamais franchir un seuil, puis
 * concentrer ses tentatives.
 *
 * Les deux cas produisent désormais un message RIGOUREUSEMENT IDENTIQUE, et c'est
 * une clause contractuelle vérifiée en recette.
 *
 * POURQUOI PRIORITÉ 45, DONC APRÈS `Ecluse::constater` (40)
 *
 * L'écluse a besoin du code d'erreur D'ORIGINE pour décider si l'échec se compte.
 * Uniformiser avant elle lui retirerait l'information : elle ne compterait plus rien,
 * et le verrouillage deviendrait décoratif.
 *
 * NE SONT JAMAIS FONDUS : `empty_username`, `empty_password` (maladresses de saisie,
 * qui doivent rester lisibles), `massifs_trop_de_tentatives` (doit annoncer le délai),
 * `massifs_compte_suspendu` (émis après validation, ne révèle rien) et les codes du
 * second facteur.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Message d'échec unique pour identifiant inexistant et mot de passe faux.
 */
final class MessageConnexion {

	/**
	 * Code d'erreur uniforme.
	 */
	public const CODE = 'massifs_identifiants_invalides';

	/**
	 * Message contractuel, identique dans les deux cas.
	 */
	public const MESSAGE = 'Identifiant ou mot de passe incorrect.';

	/**
	 * Codes fondus dans le message uniforme.
	 *
	 * @var list<string>
	 */
	private const CODES_FONDUS = array( 'invalid_username', 'invalid_email', 'incorrect_password' );

	/**
	 * Remplace l'erreur du cœur par le message uniforme.
	 *
	 * @param mixed  $utilisateur  Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant  Identifiant soumis, non utilisé.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function uniformiser( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $identifiant, $mot_de_passe );

		if ( ! $utilisateur instanceof WP_Error ) {
			return $utilisateur;
		}

		foreach ( $utilisateur->get_error_codes() as $code ) {
			if ( in_array( (string) $code, self::CODES_FONDUS, true ) ) {
				return new WP_Error( self::CODE, self::MESSAGE );
			}
		}

		return $utilisateur;
	}

	/**
	 * Garantit que le rendu du message uniforme ne porte rien d'autre.
	 *
	 * Le cœur et les extensions ajoutent volontiers au bloc d'erreur du formulaire
	 * de connexion — au minimum le lien « Mot de passe oublié ? », qui n'apparaît
	 * QUE sur `incorrect_password` et redevient donc, à lui seul, l'oracle que
	 * `uniformiser()` vient de fermer. Le bloc est reconstruit à l'identique.
	 *
	 * @param string $erreurs Bloc HTML des erreurs produit par le cœur.
	 */
	public static function filtrer_rendu( string $erreurs ): string {
		if ( ! str_contains( $erreurs, self::MESSAGE ) ) {
			return $erreurs;
		}

		return '<strong>Erreur :</strong> ' . esc_html( self::MESSAGE );
	}
}
