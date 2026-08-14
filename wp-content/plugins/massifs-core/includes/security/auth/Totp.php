<?php
/**
 * Algèbre TOTP — RFC 6238 (mot de passe à usage unique fondé sur le temps),
 * RFC 4226 (HOTP) et RFC 4648 §6 (base32).
 *
 * CLASSE PUREMENT ALGÉBRIQUE. Aucun appel à WordPress, aucune écriture, aucune
 * lecture d'état, aucune horloge propre au-delà de `time()`. C'est ce qui la rend
 * relisable ligne à ligne face aux RFC, et vérifiable avec les vecteurs de test de
 * l'annexe B de la RFC 6238.
 *
 * POURQUOI `sha1` EXPLICITEMENT, ET PAS `sha256`
 *
 * `sha1` est l'algorithme PAR DÉFAUT de la RFC 6238, et surtout le SEUL que toutes les
 * applications d'authentification acceptent réellement. Google Authenticator ignore le
 * paramètre `algorithm` de l'URI `otpauth://` et suppose `sha1` : un secret provisionné
 * en `sha256` y produirait des codes systématiquement refusés, sans le moindre message
 * d'erreur exploitable. La faiblesse de `sha1` en résistance aux collisions est ici
 * sans portée : HOTP repose sur HMAC, dont la sécurité ne dépend pas de cette
 * propriété.
 *
 * POURQUOI PAS DE QR CODE (arbitrage A-7)
 *
 * Écrire un encodeur QR en PHP pur ferait plusieurs centaines de lignes pour une
 * valeur nulle, et une bibliothèque tierce contredirait l'argumentaire du site. LA
 * SAISIE MANUELLE EST EN PRIME MEILLEURE EN ACCESSIBILITÉ : un lecteur d'écran lit un
 * secret en texte, il ne lit pas une image.
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
 * Génération et vérification de codes TOTP.
 */
final class Totp {

	/**
	 * Durée d'un pas de temps, en secondes (RFC 6238 : 30 s par défaut).
	 */
	public const PAS = 30;

	/**
	 * Nombre de chiffres du code.
	 */
	public const CHIFFRES = 6;

	/**
	 * Tolérance de dérive, en pas, de part et d'autre du pas courant.
	 *
	 * ±1 pas, soit une fenêtre effective de 90 secondes. Suffisant pour absorber une
	 * horloge de téléphone désynchronisée et le temps de saisie ; assez étroit pour
	 * qu'un code intercepté ne vaille pas une minute et demie — l'anti-rejeu s'occupe
	 * du reste.
	 */
	public const TOLERANCE = 1;

	/**
	 * Algorithme de hachage, imposé par l'interopérabilité.
	 */
	private const ALGO = 'sha1';

	/**
	 * Alphabet base32, RFC 4648 §6.
	 */
	private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Longueur du secret généré, en octets (160 bits, recommandation RFC 4226 §4).
	 */
	private const OCTETS_SECRET = 20;

	/**
	 * Pas de temps correspondant à un instant.
	 *
	 * @param int|null $instant Horodatage Unix, `null` pour maintenant.
	 */
	public static function pas_courant( ?int $instant = null ): int {
		$reference = null === $instant ? time() : $instant;

		return intdiv( $reference, self::PAS );
	}

	/**
	 * Génère un secret aléatoire, en base32.
	 *
	 * @throws Exception Si la source d'aléa du système est indisponible — cas où il
	 *                   vaut infiniment mieux échouer que produire un secret devinable.
	 */
	public static function secret(): string {
		return self::base32_encoder( random_bytes( self::OCTETS_SECRET ) );
	}

	/**
	 * Code attendu pour un pas donné.
	 *
	 * @param string $secret_b32 Secret en base32.
	 * @param int    $pas        Pas de temps.
	 *
	 * @return string Code à six chiffres, ou chaîne vide si le secret est illisible.
	 */
	public static function code( string $secret_b32, int $pas ): string {
		$cle = self::base32_decoder( $secret_b32 );

		if ( null === $cle || '' === $cle ) {
			return '';
		}

		// Compteur sur 8 octets, gros-boutiste (RFC 4226 §5.1).
		$compteur  = pack( 'J', max( 0, $pas ) );
		$empreinte = hash_hmac( self::ALGO, $compteur, $cle, true );

		// Troncature dynamique (RFC 4226 §5.3) : les 4 bits de poids faible du
		// dernier octet désignent l'offset de lecture.
		$offset = ord( $empreinte[ strlen( $empreinte ) - 1 ] ) & 0x0F;

		$binaire = ( ( ord( $empreinte[ $offset ] ) & 0x7F ) << 24 )
			| ( ( ord( $empreinte[ $offset + 1 ] ) & 0xFF ) << 16 )
			| ( ( ord( $empreinte[ $offset + 2 ] ) & 0xFF ) << 8 )
			| ( ord( $empreinte[ $offset + 3 ] ) & 0xFF );

		$modulo = 10 ** self::CHIFFRES;

		return str_pad( (string) ( $binaire % $modulo ), self::CHIFFRES, '0', STR_PAD_LEFT );
	}

	/**
	 * Vérifie un code et renvoie le pas qui l'a validé.
	 *
	 * Renvoie le PAS et non un booléen : l'appelant en a besoin pour l'anti-rejeu,
	 * et le lui faire recalculer serait l'occasion d'une divergence.
	 *
	 * Chaque comparaison passe par `hash_equals` : une comparaison `===` sur des
	 * chaînes s'arrête au premier octet différent, ce qui fuite l'information par le
	 * temps de réponse.
	 *
	 * @param string   $secret_b32 Secret en base32.
	 * @param string   $code       Code soumis.
	 * @param int|null $instant    Horodatage Unix, `null` pour maintenant.
	 *
	 * @return int|null Pas validé, ou `null`.
	 */
	public static function verifier( string $secret_b32, string $code, ?int $instant = null ): ?int {
		$soumis = (string) preg_replace( '/\s+/', '', $code );

		if ( 1 !== preg_match( '/^\d{' . self::CHIFFRES . '}$/', $soumis ) ) {
			return null;
		}

		$courant = self::pas_courant( $instant );
		$valide  = null;

		for ( $decalage = -self::TOLERANCE; $decalage <= self::TOLERANCE; $decalage++ ) {
			$pas     = $courant + $decalage;
			$attendu = self::code( $secret_b32, $pas );

			if ( '' === $attendu ) {
				return null;
			}

			// Pas de sortie anticipée : toutes les fenêtres sont éprouvées, pour que
			// la durée de la vérification ne dépende pas du pas qui a répondu.
			if ( hash_equals( $attendu, $soumis ) ) {
				$valide = $pas;
			}
		}

		return $valide;
	}

	/**
	 * Encode une chaîne binaire en base32.
	 *
	 * Sans remplissage `=` : les applications d'authentification l'acceptent, et un
	 * secret sans signe de ponctuation se recopie mieux à la main.
	 *
	 * @param string $binaire Données brutes.
	 */
	public static function base32_encoder( string $binaire ): string {
		if ( '' === $binaire ) {
			return '';
		}

		$bits = '';

		for ( $index = 0, $taille = strlen( $binaire ); $index < $taille; $index++ ) {
			$bits .= str_pad( decbin( ord( $binaire[ $index ] ) ), 8, '0', STR_PAD_LEFT );
		}

		$reste = strlen( $bits ) % 5;

		if ( 0 !== $reste ) {
			$bits .= str_repeat( '0', 5 - $reste );
		}

		$sortie = '';

		foreach ( str_split( $bits, 5 ) as $quintet ) {
			$sortie .= self::ALPHABET[ bindec( $quintet ) ];
		}

		return $sortie;
	}

	/**
	 * Décode une chaîne base32.
	 *
	 * Renvoie `null` — jamais une chaîne vide, jamais une valeur partielle — dès
	 * qu'un caractère n'appartient pas à l'alphabet : décoder « au mieux » un secret
	 * mal recopié produirait des codes systématiquement faux et un utilisateur
	 * persuadé que son application est en cause.
	 *
	 * @param string $base32 Chaîne base32, espaces et remplissage tolérés.
	 */
	public static function base32_decoder( string $base32 ): ?string {
		$propre = strtoupper( (string) preg_replace( '/[\s-]+/', '', $base32 ) );
		$propre = rtrim( $propre, '=' );

		if ( '' === $propre ) {
			return null;
		}

		$bits = '';

		for ( $index = 0, $taille = strlen( $propre ); $index < $taille; $index++ ) {
			$position = strpos( self::ALPHABET, $propre[ $index ] );

			if ( false === $position ) {
				return null;
			}

			$bits .= str_pad( decbin( $position ), 5, '0', STR_PAD_LEFT );
		}

		$octets = '';

		foreach ( str_split( $bits, 8 ) as $octet ) {
			if ( 8 !== strlen( $octet ) ) {
				// Bits de complément du dernier quintet : ignorés, pas une erreur.
				continue;
			}

			$octets .= chr( bindec( $octet ) );
		}

		return '' === $octets ? null : $octets;
	}

	/**
	 * URI `otpauth://` à recopier ou à coller dans une application.
	 *
	 * Chaque composant est encodé séparément par `rawurlencode` : un nom de site
	 * contenant une espace ou une esperluette produirait sinon une URI tronquée en
	 * silence, et un enrôlement impossible à diagnostiquer.
	 *
	 * @param string $libelle    Compte identifié dans l'application.
	 * @param string $emetteur   Nom du site.
	 * @param string $secret_b32 Secret en base32.
	 */
	public static function uri_otpauth( string $libelle, string $emetteur, string $secret_b32 ): string {
		return sprintf(
			'otpauth://totp/%1$s:%2$s?secret=%3$s&issuer=%1$s&algorithm=SHA1&digits=%4$d&period=%5$d',
			rawurlencode( $emetteur ),
			rawurlencode( $libelle ),
			rawurlencode( $secret_b32 ),
			self::CHIFFRES,
			self::PAS
		);
	}

	/**
	 * Découpe un secret en blocs de quatre, pour la lecture et la recopie.
	 *
	 * @param string $secret_b32 Secret en base32.
	 */
	public static function grouper( string $secret_b32 ): string {
		return trim( (string) chunk_split( $secret_b32, 4, ' ' ) );
	}
}
