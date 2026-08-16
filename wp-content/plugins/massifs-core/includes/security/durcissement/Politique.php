<?php
/**
 * Résolution unique de la politique de durcissement.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  TOUS LES `apply_filters()` DU MODULE VIVENT ICI, ET NULLE PART AILLEURS.     │
 * │                                                                               │
 * │  `Entetes` émet, `api.php` rapporte, `EditionCode` et `MisesAJour` décident :  │
 * │  les quatre lisent la MÊME source. Dupliquer un `apply_filters()` ailleurs     │
 * │  ferait diverger ce qui est ANNONCÉ par `massifs_durcissement_entetes()` de    │
 * │  ce qui est RÉELLEMENT émis — c'est-à-dire produirait un module dont la        │
 * │  surface de preuve ment. C'est le défaut que cette classe existe pour rendre   │
 * │  impossible.                                                                   │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * VALIDATION, PAS CONFIANCE. Un filtre est du code tiers : il peut rendre `null`,
 * un objet, ou une chaîne porteuse d'un `\r\n`. Chaque lecture retombe donc sur son
 * DÉFAUT quand le type est inattendu — jamais sur une valeur à demi valide, jamais
 * sur une coercition silencieuse. Et toute valeur d'en-tête traverse
 * `nettoyer()` avant d'atteindre `header()` : un filtre mal écrit ne doit pas
 * pouvoir injecter un second en-tête.
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Durcissement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Source unique de vérité des réglages du module.
 */
final class Politique {

	/**
	 * Politique de sécurité de contenu servie au visiteur anonyme.
	 *
	 * Contractuelle (contrat #16) : elle LIE le balisage du thème, qui n'a donc ni
	 * `<script>` en ligne exécutable, ni bloc `<style>`, ni `style="…"` posé par
	 * JavaScript, ni URL `data:`.
	 *
	 * `style-src-attr 'unsafe-inline'` est le seul assouplissement, et il est borné :
	 * il autorise l'attribut `style` du balisage servi (Leaflet en pose au
	 * positionnement des tuiles) SANS ouvrir les blocs `<style>`, que `style-src`
	 * continue d'interdire.
	 *
	 * `script-src 'self'` SANS `'unsafe-inline'` : l'îlot
	 * `<script type="application/json">` du thème reste permis, c'est un *data
	 * block* que l'algorithme de préparation d'un élément `script` écarte avant le
	 * contrôle CSP. Le repli légitime d'un besoin ponctuel est le hachage ou un
	 * attribut `data-`, JAMAIS l'ajout de `'unsafe-inline'`.
	 */
	private const CSP_DEFAUT = "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'";

	/**
	 * Fonctionnalités du navigateur refusées à la page.
	 *
	 * Le site ne demande jamais la position réelle du visiteur : la carte est
	 * centrée sur le département, et le §9 du brief interdit toute donnée
	 * personnelle côté public.
	 */
	private const PERMISSIONS_POLICY = 'geolocation=(), camera=(), microphone=(), payment=(), usb=()';

	/**
	 * Durée de vie HSTS par défaut, en secondes (180 jours).
	 */
	private const HSTS_MAX_AGE_DEFAUT = 15552000;

	/**
	 * Modes d'application de la CSP.
	 *
	 * `report-only` est le levier de recette : il bascule le NOM de l'en-tête, donc
	 * observe sans casser. Aucun autre mot n'est accepté.
	 *
	 * @var list<string>
	 */
	private const MODES_CSP = array( 'enforce', 'report-only' );

	/**
	 * Caractères de contrôle interdits dans une valeur d'en-tête.
	 *
	 * `\r` et `\n` d'abord — ils permettraient d'injecter un en-tête entier — mais
	 * la classe entière est retirée : `header()` refuse déjà les sauts de ligne, pas
	 * les autres octets de contrôle.
	 */
	private const CONTROLES = '/[\x00-\x1F\x7F]/';

	/**
	 * En-têtes qui seraient émis pour la requête courante, dans l'ordre d'émission.
	 *
	 * PURE : aucune écriture, aucun `header()`, aucun effet de bord. C'est la
	 * surface qu'un test interroge pour prouver la CSP sans naviguer.
	 *
	 * @return array<string, string>
	 */
	public static function entetes(): array {
		$carte = array();

		$csp = self::csp();

		// CSP RÉSERVÉE À L'ANONYME, ET CE N'EST PAS UN OUBLI (arbitrage A-2) :
		// le CŒUR émet deux blocs `<style>` EN LIGNE sur le front dès qu'une session
		// est ouverte (`wp_admin_bar_header`, `_admin_bar_bump_cb`), et le contrat
		// #25 GÈLE la présence de la barre d'administration. « Corriger » cette
		// condition casserait la barre ; ajouter `'unsafe-inline'` à `style-src`
		// pour la sauver annulerait l'intérêt de l'en-tête. L'anonyme, c'est 100 %
		// du public réel. Affaiblissement borné et assumé : un administrateur qui
		// navigue sur le front ne reçoit pas de CSP.
		$csp_due = '' !== $csp && ! ( self::csp_anonyme_seulement() && is_user_logged_in() );

		if ( $csp_due ) {
			$carte[ self::nom_entete_csp() ] = $csp;
		}

		$carte['X-Content-Type-Options'] = 'nosniff';
		$carte['Referrer-Policy']        = 'no-referrer';
		$carte['X-Frame-Options']        = 'DENY';
		$carte['Permissions-Policy']     = self::PERMISSIONS_POLICY;

		// HSTS CONDITIONNÉ À `is_ssl()`, JAMAIS AVEC `preload` : l'en-tête est écrit
		// une fois pour toutes, il ne part qu'en HTTPS, il s'arme donc seul à la
		// publication et ne ment jamais en local. `preload` serait, lui,
		// irréversible à l'échelle du navigateur — pas de nous à décider.
		if ( self::hsts_actif() ) {
			$carte['Strict-Transport-Security'] = sprintf( 'max-age=%d; includeSubDomains', self::hsts_max_age() );
		}

		$carte = self::normaliser_carte( $carte );

		/**
		 * Carte complète des en-têtes, dernier mot avant émission.
		 *
		 * Le résultat est revalidé : un nom d'en-tête non conforme ou une valeur
		 * porteuse d'un caractère de contrôle est écartée, pas corrigée.
		 *
		 * @param array<string, string> $carte En-têtes dans leur ordre d'émission.
		 */
		$filtree = apply_filters( 'massifs_durcissement_entetes', $carte );

		if ( ! is_array( $filtree ) ) {
			return $carte;
		}

		return self::normaliser_carte( $filtree );
	}

	/**
	 * Politique de sécurité de contenu retenue.
	 */
	public static function csp(): string {
		/**
		 * Politique de sécurité de contenu servie au public.
		 *
		 * @param string $csp Valeur par défaut, contractuelle.
		 */
		$csp = apply_filters( 'massifs_durcissement_csp', self::CSP_DEFAUT );

		if ( ! is_string( $csp ) ) {
			return self::CSP_DEFAUT;
		}

		$csp = self::nettoyer( $csp );

		return '' === $csp ? self::CSP_DEFAUT : $csp;
	}

	/**
	 * Mode d'application de la CSP : `enforce` ou `report-only`.
	 */
	public static function csp_mode(): string {
		/**
		 * Mode d'application de la CSP.
		 *
		 * @param string $mode `enforce` (défaut) ou `report-only`.
		 */
		$mode = apply_filters( 'massifs_durcissement_csp_mode', 'enforce' );

		if ( ! is_string( $mode ) || ! in_array( $mode, self::MODES_CSP, true ) ) {
			return 'enforce';
		}

		return $mode;
	}

	/**
	 * La CSP est-elle réservée aux visiteurs anonymes ?
	 */
	public static function csp_anonyme_seulement(): bool {
		return self::booleen( 'massifs_durcissement_csp_anonyme_seulement', true );
	}

	/**
	 * HSTS doit-il être émis pour cette requête ?
	 */
	public static function hsts_actif(): bool {
		return self::booleen( 'massifs_durcissement_hsts_actif', is_ssl() );
	}

	/**
	 * Durée de vie HSTS, en secondes.
	 */
	public static function hsts_max_age(): int {
		/**
		 * Durée de vie de l'en-tête HSTS, en secondes.
		 *
		 * @param int $max_age Défaut 15552000 (180 jours).
		 */
		$max_age = apply_filters( 'massifs_durcissement_hsts_max_age', self::HSTS_MAX_AGE_DEFAUT );

		if ( ! is_int( $max_age ) && ! ( is_string( $max_age ) && ctype_digit( $max_age ) ) ) {
			return self::HSTS_MAX_AGE_DEFAUT;
		}

		$max_age = (int) $max_age;

		return $max_age > 0 ? $max_age : self::HSTS_MAX_AGE_DEFAUT;
	}

	/**
	 * Les surfaces d'énumération de comptes doivent-elles être fermées ?
	 */
	public static function fermer_enumeration(): bool {
		return self::booleen( 'massifs_durcissement_fermer_enumeration', true );
	}

	/**
	 * L'édition de code depuis l'administration est-elle interdite ?
	 */
	public static function interdire_edition_code(): bool {
		return self::booleen( 'massifs_durcissement_interdire_edition_code', true );
	}

	/**
	 * Les mises à jour mineures du cœur sont-elles automatiques ?
	 */
	public static function mises_a_jour_mineures(): bool {
		return self::booleen( 'massifs_durcissement_mises_a_jour_mineures', true );
	}

	/**
	 * Les mises à jour majeures du cœur sont-elles automatiques ?
	 */
	public static function mises_a_jour_majeures(): bool {
		return self::booleen( 'massifs_durcissement_mises_a_jour_majeures', false );
	}

	/**
	 * Les extensions se mettent-elles à jour automatiquement ?
	 */
	public static function mises_a_jour_extensions(): bool {
		return self::booleen( 'massifs_durcissement_mises_a_jour_extensions', false );
	}

	/**
	 * Les thèmes se mettent-ils à jour automatiquement ?
	 */
	public static function mises_a_jour_themes(): bool {
		return self::booleen( 'massifs_durcissement_mises_a_jour_themes', false );
	}

	/**
	 * Nom de l'en-tête de CSP, selon le mode.
	 */
	private static function nom_entete_csp(): string {
		return 'report-only' === self::csp_mode()
			? 'Content-Security-Policy-Report-Only'
			: 'Content-Security-Policy';
	}

	/**
	 * Lecture d'un réglage booléen, défaut opposable en cas de type inattendu.
	 *
	 * `is_bool()` PLUTÔT QU'UN CAST : `(bool) 'false'` vaut `true`, et un filtre
	 * qui rend une chaîne signale une erreur d'écriture, pas une intention. On
	 * retombe alors sur le défaut, qui est toujours le réglage sûr.
	 *
	 * @param string $filtre Nom du filtre.
	 * @param bool   $defaut Valeur par défaut.
	 */
	private static function booleen( string $filtre, bool $defaut ): bool {
		$valeur = apply_filters( $filtre, $defaut );

		return is_bool( $valeur ) ? $valeur : $defaut;
	}

	/**
	 * Retire les caractères de contrôle d'une valeur d'en-tête.
	 *
	 * @param string $valeur Valeur brute.
	 */
	private static function nettoyer( string $valeur ): string {
		return trim( (string) preg_replace( self::CONTROLES, '', $valeur ) );
	}

	/**
	 * Valide une carte d'en-têtes : noms conformes, valeurs sans octet de contrôle.
	 *
	 * Une entrée invalide est ÉCARTÉE, jamais réparée : réparer reviendrait à
	 * deviner l'intention de l'appelant, et une CSP à demi devinée est pire que pas
	 * de CSP du tout.
	 *
	 * @param array<array-key, mixed> $carte Carte à valider.
	 *
	 * @return array<string, string>
	 */
	private static function normaliser_carte( array $carte ): array {
		$propre = array();

		foreach ( $carte as $nom => $valeur ) {
			if ( ! is_string( $nom ) || 1 !== preg_match( '/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/', $nom ) ) {
				continue;
			}

			if ( ! is_string( $valeur ) && ! is_numeric( $valeur ) ) {
				continue;
			}

			$valeur = self::nettoyer( (string) $valeur );

			if ( '' === $valeur ) {
				continue;
			}

			$propre[ $nom ] = $valeur;
		}

		return $propre;
	}
}
