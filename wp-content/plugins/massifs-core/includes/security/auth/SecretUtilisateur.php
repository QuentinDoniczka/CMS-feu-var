<?php
/**
 * Secret TOTP, anti-rejeu et codes de secours d'un compte.
 *
 * SECRET ≠ ACTIF. Un secret généré mais jamais confirmé ne verrouille personne : seule
 * la méta `massifs_totp_actif` déclenche la demande de second facteur. C'est cette
 * séparation qui rend l'enrôlement interruptible sans risque — fermer l'onglet en
 * cours de route ne laisse pas un compte à moitié armé, donc inaccessible.
 *
 * ANTI-REJEU (arbitrage A-17, RFC 6238 §5.2)
 *
 * Un code n'est accepté que si son pas est STRICTEMENT SUPÉRIEUR au dernier pas
 * mémorisé. C'est exactement la différence entre une vraie 2FA et un jeton rejouable
 * pendant quatre-vingt-dix secondes : sans ce champ, un code observé par-dessus
 * l'épaule ou capté dans un journal vaut trois tentatives de connexion.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  **CHIFFREMENT AU REPOS ET ROTATION DE SEL — À LIRE AVANT TOUTE EXPLOITATION** │
 * │                                                                               │
 * │  **LA CLÉ DE CHIFFREMENT DÉRIVE DE `wp_salt('secure_auth')`, DONC DE LA        │
 * │  CONSTANTE `SECURE_AUTH_SALT` DE `wp-config.php`. FAIRE TOURNER CE SEL REND    │
 * │  ILLISIBLE LE SECRET TOTP DE TOUS LES COMPTES ENRÔLÉS, DÉFINITIVEMENT.**       │
 * │                                                                               │
 * │  **CE N'EST PAS UNE PERTE D'ACCÈS : LE CHEMIN DE RÉCUPÉRATION EST LE CODE DE  │
 * │  SECOURS, QUI EST HACHÉ SÉPARÉMENT PAR `wp_hash_password` ET NE DÉPEND PAS DU  │
 * │  SEL. TOUT COMPTE DOIT AVOIR CONSERVÉ SES DIX CODES. À DÉFAUT, LA SORTIE EST   │
 * │  WP-CLI (voir `Enrolement`).**                                                 │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stockage du second facteur d'un compte.
 *
 * ÉCRITURE STRICTEMENT RÉSERVÉE À CE MODULE (interdit 5 du contrat).
 */
final class SecretUtilisateur {

	/**
	 * Secret TOTP, chiffré (`v1:…`).
	 */
	public const META_SECRET = 'massifs_totp_secret';

	/**
	 * Second facteur exigé à la connexion : `'1'` ou méta absente.
	 */
	public const META_ACTIF = 'massifs_totp_actif';

	/**
	 * Instant d'activation, ISO 8601 UTC.
	 */
	public const META_ACTIVE_LE = 'massifs_totp_active_le';

	/**
	 * Dernier pas de temps consommé, pour l'anti-rejeu.
	 */
	public const META_DERNIER_PAS = 'massifs_totp_dernier_pas';

	/**
	 * Codes de secours, hachés.
	 */
	public const META_CODES = 'massifs_totp_codes_secours';

	/**
	 * Instant de génération des codes de secours, ISO 8601 UTC.
	 */
	public const META_CODES_LE = 'massifs_totp_codes_secours_genere_le';

	/**
	 * Nombre de codes de secours générés.
	 */
	public const NOMBRE_CODES = 10;

	/**
	 * Préfixe de version du format chiffré.
	 */
	private const PREFIXE_V1 = 'v1:';

	/**
	 * Algorithme de chiffrement au repos : authentifié, donc détectant l'altération.
	 */
	private const CHIFFREMENT = 'aes-256-gcm';

	/**
	 * Longueur du vecteur d'initialisation, en octets (recommandation GCM).
	 */
	private const LONGUEUR_IV = 12;

	/**
	 * Longueur de l'étiquette d'authentification GCM, en octets.
	 */
	private const LONGUEUR_TAG = 16;

	/**
	 * Alphabet des codes de secours.
	 *
	 * Sans `0`, `O`, `1`, `l` ni `I` : ces codes sont IMPRIMÉS puis RECOPIÉS À LA
	 * MAIN, souvent des mois plus tard et souvent sous stress. Une confusion de
	 * glyphe y coûterait le seul chemin de récupération du compte.
	 */
	private const ALPHABET_CODES = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * Format d'échange des instants : ISO 8601 en UTC.
	 */
	private const FORMAT_ISO_UTC = 'Y-m-d\TH:i:s\Z';

	/**
	 * Le second facteur est-il actif sur ce compte ?
	 *
	 * @param int $user_id Compte concerné.
	 */
	public static function est_actif( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return '1' === (string) get_user_meta( $user_id, self::META_ACTIF, true )
			&& '' !== self::secret( $user_id );
	}

	/**
	 * Secret en clair du compte, ou chaîne vide.
	 *
	 * @param int $user_id Compte concerné.
	 */
	public static function secret( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$stocke = (string) get_user_meta( $user_id, self::META_SECRET, true );

		return '' === $stocke ? '' : self::dechiffrer( $stocke );
	}

	/**
	 * Instant d'activation, ou chaîne vide.
	 *
	 * @param int $user_id Compte concerné.
	 */
	public static function active_le( int $user_id ): string {
		return trim( (string) get_user_meta( $user_id, self::META_ACTIVE_LE, true ) );
	}

	/**
	 * Enregistre le secret et arme le second facteur.
	 *
	 * `$pas_consomme` porte le pas de temps qui a servi à CONFIRMER l'enrôlement.
	 * Il est mémorisé comme dernier pas consommé, sans quoi le code tapé pendant
	 * l'enrôlement resterait rejouable à la connexion pendant sa fenêtre de
	 * tolérance : l'anti-rejeu doit couvrir l'enrôlement, pas seulement les
	 * connexions suivantes.
	 *
	 * @param int      $user_id      Compte concerné.
	 * @param string   $secret_b32   Secret confirmé, en base32.
	 * @param int|null $pas_consomme Pas ayant validé la confirmation.
	 */
	public static function activer( int $user_id, string $secret_b32, ?int $pas_consomme = null ): void {
		if ( $user_id <= 0 || '' === $secret_b32 ) {
			return;
		}

		update_user_meta( $user_id, self::META_SECRET, self::chiffrer( $secret_b32 ) );
		update_user_meta( $user_id, self::META_ACTIF, '1' );
		update_user_meta( $user_id, self::META_ACTIVE_LE, gmdate( self::FORMAT_ISO_UTC ) );

		// Le pas consommé sur l'ANCIEN secret n'a aucun sens pour le nouveau, et le
		// conserver refuserait des codes valides jusqu'à ce que le temps le rattrape.
		if ( null === $pas_consomme ) {
			delete_user_meta( $user_id, self::META_DERNIER_PAS );
		} else {
			update_user_meta( $user_id, self::META_DERNIER_PAS, $pas_consomme );
		}

		self::couper_les_sessions( $user_id );
	}

	/**
	 * Retire le second facteur et tout ce qui s'y rattache.
	 *
	 * Appelée par la réinitialisation administrateur et par le bouton de
	 * désactivation du profil. Les codes de secours partent avec le secret : les
	 * conserver laisserait dix chaînes valides pour un facteur qui n'existe plus.
	 *
	 * @param int $user_id Compte concerné.
	 */
	public static function desactiver( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		delete_user_meta( $user_id, self::META_SECRET );
		delete_user_meta( $user_id, self::META_ACTIF );
		delete_user_meta( $user_id, self::META_ACTIVE_LE );
		delete_user_meta( $user_id, self::META_DERNIER_PAS );
		delete_user_meta( $user_id, self::META_CODES );
		delete_user_meta( $user_id, self::META_CODES_LE );

		self::couper_les_sessions( $user_id );
	}

	/**
	 * Vérifie un code TOTP, anti-rejeu compris.
	 *
	 * @param int    $user_id Compte concerné.
	 * @param string $code    Code soumis.
	 */
	public static function verifier_code( int $user_id, string $code ): bool {
		$secret = self::secret( $user_id );

		if ( '' === $secret ) {
			return false;
		}

		$pas = Totp::verifier( $secret, $code );

		if ( null === $pas ) {
			return false;
		}

		$dernier = (int) get_user_meta( $user_id, self::META_DERNIER_PAS, true );

		// STRICTEMENT supérieur : un code déjà consommé, même encore dans sa fenêtre
		// de tolérance, est refusé.
		if ( $pas <= $dernier ) {
			return false;
		}

		update_user_meta( $user_id, self::META_DERNIER_PAS, $pas );

		return true;
	}

	/**
	 * Génère dix codes de secours et renvoie leur forme EN CLAIR.
	 *
	 * La valeur de retour est la SEULE occasion de les lire : seuls des condensats
	 * sont stockés, et aucune méthode ne sait les redonner.
	 *
	 * @param int $user_id Compte concerné.
	 *
	 * @return list<string>
	 *
	 * @throws Exception Si la source d'aléa du système est indisponible.
	 */
	public static function generer_codes_secours( int $user_id ): array {
		$clairs = array();
		$haches = array();

		for ( $index = 0; $index < self::NOMBRE_CODES; $index++ ) {
			$code = self::forger_code();

			$clairs[] = $code;
			$haches[] = wp_hash_password( $code );
		}

		update_user_meta( $user_id, self::META_CODES, $haches );
		update_user_meta( $user_id, self::META_CODES_LE, gmdate( self::FORMAT_ISO_UTC ) );

		return $clairs;
	}

	/**
	 * Nombre de codes de secours encore utilisables.
	 *
	 * @param int $user_id Compte concerné.
	 */
	public static function nombre_codes_secours( int $user_id ): int {
		return count( self::codes_secours( $user_id ) );
	}

	/**
	 * Consomme un code de secours.
	 *
	 * USAGE UNIQUE : l'entrée est retirée du stockage dès qu'elle est reconnue.
	 * `wp_check_password` est appelée SANS identifiant d'utilisateur : le lui passer
	 * déclencherait le réencodage du mot de passe DU COMPTE avec la valeur du code
	 * de secours, ce qui remplacerait purement et simplement son mot de passe.
	 *
	 * @param int    $user_id Compte concerné.
	 * @param string $code    Code soumis.
	 */
	public static function consommer_code_secours( int $user_id, string $code ): bool {
		$normalise = self::normaliser_code( $code );

		if ( '' === $normalise ) {
			return false;
		}

		$haches = self::codes_secours( $user_id );

		foreach ( $haches as $position => $hache ) {
			if ( ! wp_check_password( $normalise, $hache ) ) {
				continue;
			}

			unset( $haches[ $position ] );

			update_user_meta( $user_id, self::META_CODES, array_values( $haches ) );

			return true;
		}

		return false;
	}

	/**
	 * Condensats des codes de secours restants.
	 *
	 * @param int $user_id Compte concerné.
	 *
	 * @return list<string>
	 */
	private static function codes_secours( int $user_id ): array {
		$brut = get_user_meta( $user_id, self::META_CODES, true );

		if ( ! is_array( $brut ) ) {
			return array();
		}

		$propres = array();

		foreach ( $brut as $entree ) {
			if ( is_string( $entree ) && '' !== $entree ) {
				$propres[] = $entree;
			}
		}

		return $propres;
	}

	/**
	 * Forge un code de secours `XXXX-XXXX`.
	 *
	 * `random_int` et non `rand` : ce code vaut un mot de passe.
	 *
	 * @throws Exception Si la source d'aléa du système est indisponible.
	 */
	private static function forger_code(): string {
		$dernier = strlen( self::ALPHABET_CODES ) - 1;
		$code    = '';

		for ( $index = 0; $index < 8; $index++ ) {
			if ( 4 === $index ) {
				$code .= '-';
			}

			$code .= self::ALPHABET_CODES[ random_int( 0, $dernier ) ];
		}

		return $code;
	}

	/**
	 * Normalise un code de secours saisi, ou renvoie une chaîne vide.
	 *
	 * Le tiret est optionnel à la saisie et les minuscules sont acceptées : refuser
	 * un code correct sur une question de ponctuation, au moment précis où
	 * l'utilisateur a perdu son téléphone, serait cruel et sans bénéfice.
	 *
	 * @param string $code Code soumis.
	 */
	private static function normaliser_code( string $code ): string {
		$propre = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $code ) );

		if ( 8 !== strlen( $propre ) ) {
			return '';
		}

		for ( $index = 0; $index < 8; $index++ ) {
			if ( ! str_contains( self::ALPHABET_CODES, $propre[ $index ] ) ) {
				return '';
			}
		}

		return substr( $propre, 0, 4 ) . '-' . substr( $propre, 4, 4 );
	}

	/**
	 * Chiffre le secret pour le stockage.
	 *
	 * Sans OpenSSL, le secret est stocké EN CLAIR et l'anomalie est journalisée :
	 * un enrôlement cassé serait pire qu'un stockage en clair sur une base à laquelle
	 * un attaquant a déjà accès — et il n'aurait aucun moyen de le comprendre.
	 *
	 * @param string $clair Secret en base32.
	 */
	private static function chiffrer( string $clair ): string {
		if ( ! extension_loaded( 'openssl' ) ) {
			error_log( 'massifs-core : extension openssl absente, le secret TOTP est stocké en clair.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return $clair;
		}

		try {
			$iv = random_bytes( self::LONGUEUR_IV );
		} catch ( Exception $exception ) {
			error_log( 'massifs-core : aléa indisponible, le secret TOTP est stocké en clair.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return $clair;
		}

		$tag     = '';
		$chiffre = openssl_encrypt(
			$clair,
			self::CHIFFREMENT,
			self::cle(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::LONGUEUR_TAG
		);

		if ( false === $chiffre ) {
			error_log( 'massifs-core : chiffrement du secret TOTP impossible, stockage en clair.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return $clair;
		}

		return self::PREFIXE_V1 . base64_encode( $iv . $tag . $chiffre );
	}

	/**
	 * Déchiffre un secret stocké.
	 *
	 * Une valeur sans préfixe de version est rendue telle quelle : c'est un secret
	 * écrit avant l'activation d'OpenSSL, et le refuser enfermerait le compte dehors.
	 *
	 * @param string $stocke Valeur stockée.
	 */
	private static function dechiffrer( string $stocke ): string {
		if ( ! str_starts_with( $stocke, self::PREFIXE_V1 ) ) {
			return $stocke;
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			return '';
		}

		$brut = base64_decode( substr( $stocke, strlen( self::PREFIXE_V1 ) ), true );

		if ( false === $brut || strlen( $brut ) <= self::LONGUEUR_IV + self::LONGUEUR_TAG ) {
			return '';
		}

		$iv      = substr( $brut, 0, self::LONGUEUR_IV );
		$tag     = substr( $brut, self::LONGUEUR_IV, self::LONGUEUR_TAG );
		$chiffre = substr( $brut, self::LONGUEUR_IV + self::LONGUEUR_TAG );

		$clair = openssl_decrypt( $chiffre, self::CHIFFREMENT, self::cle(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $clair ? '' : $clair;
	}

	/**
	 * Clé de chiffrement, dérivée du sel d'authentification du site.
	 *
	 * Voir l'avertissement en tête de fichier : faire tourner `SECURE_AUTH_SALT`
	 * invalide tous les secrets.
	 */
	private static function cle(): string {
		return hash( 'sha256', wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Coupe les sessions après un changement de second facteur.
	 *
	 * `detruire_autres()` quand le compte agit sur LUI-MÊME : le déconnecter du
	 * navigateur où il vient d'enrôler son facteur, avant même qu'il ait pu noter
	 * ses codes de secours, serait hostile et lui coûterait sa récupération.
	 * `detruire()` quand la décision vient d'un tiers.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function couper_les_sessions( int $user_id ): void {
		if ( ! class_exists( Sessions::class ) ) {
			return;
		}

		if ( get_current_user_id() === $user_id ) {
			Sessions::detruire_autres( $user_id );

			return;
		}

		Sessions::detruire( $user_id );
	}
}
