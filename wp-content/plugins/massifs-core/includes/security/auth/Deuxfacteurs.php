<?php
/**
 * Second facteur TOTP : exigence à la connexion et étape 2 du formulaire.
 *
 * PÉRIMÈTRE (arbitrage A-9) : IMPOSÉ à `administrator`, DISPONIBLE en option au
 * gestionnaire. Le §6 du brief dit « double authentification disponible et active pour
 * les administrateurs ». L'imposer au gestionnaire serait contraire au brief et
 * tuerait la démonstration publique, dont les identifiants sont affichés et le
 * scénario promis en moins de deux minutes.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  `doit_verifier()` REPOSE SUR `est_actif()`, JAMAIS SUR `est_requis()`.       │
 * │                                                                               │
 * │  C'EST EXACTEMENT CE QUI EMPÊCHE L'AUTO-ENFERMEMENT. Un administrateur pour   │
 * │  qui le second facteur est REQUIS mais NON ENRÔLÉ se connecte normalement,    │
 * │  puis la rampe (`Enrolement`) le conduit à son profil. Exiger un code qu'il   │
 * │  ne peut pas produire fermerait la porte de l'extérieur, sans poignée à       │
 * │  l'intérieur.                                                                  │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * DÉROULÉ EN DEUX TEMPS
 *
 *   Étape 1 — le mot de passe est validé, AUCUN COOKIE N'EST POSÉ. Un jeton à usage
 *   unique est déposé en transient (300 s) et l'utilisateur est redirigé vers l'étape 2.
 *   Le transient est le BON stockage ici, et pour la raison inverse de l'écluse : une
 *   éviction y fait échouer FERMÉ — l'utilisateur ressaisit son mot de passe.
 *
 *   Étape 2 — nonce, jeton, VERROU DE L'ÉCLUSE, compteur de tentatives, code TOTP
 *   (anti-rejeu) ou code de secours, puis seulement `wp_set_auth_cookie`. L'étape 2
 *   ne traverse pas `authenticate` : elle est le SEUL chemin du module qui pose un
 *   cookie sans que `Ecluse::barrer` ni `Ecluse::reaffirmer` ne s'exécutent, d'où la
 *   reconsultation explicite du verrou — voir l'encadré de `traiter()`.
 *
 * CINQ TENTATIVES PAR JETON, puis le jeton est brûlé : un code à six chiffres se
 * force en un million d'essais, le verrouillage doit donc couvrir l'étape 2 comme il
 * couvre l'étape 1. Chaque échec alimente en outre l'écluse.
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
 * Exigence et vérification du second facteur.
 */
final class Deuxfacteurs {

	/**
	 * Action de `wp-login.php` portant l'étape 2.
	 *
	 * Le cœur expose `login_form_{action}` précisément pour cela : aucune reprise de
	 * `wp-login.php`, aucun détournement de son flux, aucun gabarit dupliqué.
	 */
	public const ACTION = 'massifs_2fa';

	/**
	 * Préfixe du transient portant le jeton d'étape 2.
	 */
	private const PREFIXE_JETON = 'massifs_2fa_';

	/**
	 * Durée de vie du jeton d'étape 2, en secondes.
	 */
	private const TTL_JETON = 300;

	/**
	 * Tentatives autorisées par jeton.
	 */
	private const MAX_ESSAIS = 5;

	/**
	 * Le second facteur est-il globalement désactivé par constante ?
	 *
	 * Échappatoire d'exploitation, LUE dans `wp-config.php` et jamais écrite par ce
	 * module. Voir l'en-tête de `Enrolement`.
	 */
	public static function desactivee_globalement(): bool {
		return defined( 'MASSIFS_DESACTIVER_2FA' ) && (bool) constant( 'MASSIFS_DESACTIVER_2FA' );
	}

	/**
	 * Le second facteur est-il exigé de ce compte ?
	 *
	 * @param WP_User $compte Compte évalué.
	 */
	public static function est_requis( WP_User $compte ): bool {
		if ( self::desactivee_globalement() ) {
			return false;
		}

		$requis = in_array( 'administrator', (array) $compte->roles, true );

		/**
		 * Le second facteur est-il exigé de ce compte ?
		 *
		 * @param bool    $requis Exigence par défaut.
		 * @param WP_User $compte Compte évalué.
		 */
		return (bool) apply_filters( 'massifs_auth_2fa_requis', $requis, $compte );
	}

	/**
	 * Le second facteur est-il armé sur ce compte ?
	 *
	 * @param int $user_id Compte évalué.
	 */
	public static function est_actif( int $user_id ): bool {
		if ( self::desactivee_globalement() || ! class_exists( SecretUtilisateur::class ) ) {
			return false;
		}

		return SecretUtilisateur::est_actif( $user_id );
	}

	/**
	 * Faut-il demander un second facteur à ce compte ?
	 *
	 * @param WP_User $compte Compte évalué.
	 */
	public static function doit_verifier( WP_User $compte ): bool {
		return self::est_actif( (int) $compte->ID );
	}

	/**
	 * Interrompt la connexion pour demander le second facteur.
	 *
	 * Priorité 50 sur `authenticate` : après toutes les autres gardes, donc sur un
	 * compte dont les identifiants sont validés, qui n'est pas suspendu et dont
	 * l'origine n'est pas verrouillée.
	 *
	 * @param mixed  $utilisateur  Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant  Identifiant soumis, non utilisé.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function exiger_second_facteur( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $identifiant, $mot_de_passe );

		if ( ! $utilisateur instanceof WP_User || ! self::doit_verifier( $utilisateur ) ) {
			return $utilisateur;
		}

		// Hors contexte de navigateur — WP-CLI, cron, REST, AJAX — il n'y a aucune
		// étape 2 possible : on refuse fermé plutôt que de tenter une redirection
		// qui n'aboutirait nulle part.
		if ( ! self::peut_rediriger() ) {
			return new WP_Error(
				'massifs_2fa_requise',
				'Ce compte exige un second facteur : connectez-vous depuis le formulaire du site.'
			);
		}

		$jeton = wp_generate_password( 32, false );

		set_transient(
			self::PREFIXE_JETON . sha1( $jeton ),
			array(
				'user_id'     => (int) $utilisateur->ID,
				'remember'    => self::case_souvenir(),
				'redirect_to' => self::redirection_demandee(),
				'essais'      => 0,
			),
			self::TTL_JETON
		);

		wp_safe_redirect( self::url_etape_2( $jeton ) );

		exit;
	}

	/**
	 * Rend et traite l'étape 2, sur `wp-login.php?action=massifs_2fa`.
	 */
	public static function etape_2(): void {
		$jeton = isset( $_REQUEST['jeton'] )
			? sanitize_text_field( wp_unslash( (string) $_REQUEST['jeton'] ) )
			: '';

		$methode = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		$erreur = 'POST' === $methode ? self::traiter( $jeton ) : '';

		self::rendre( $jeton, $erreur );
	}

	/**
	 * Traite la soumission de l'étape 2.
	 *
	 * ORDRE NON NÉGOCIABLE : nonce, validité du jeton, verrou de l'écluse, compteur de
	 * tentatives, code TOTP puis code de secours, et seulement alors le cookie
	 * d'authentification. Intervertir le compteur et la vérification du code rendrait
	 * le plafond inopérant.
	 *
	 * ┌────────────────────────────────────────────────────────────────────────┐
	 * │  POURQUOI LE VERROU EST RECONSULTÉ ICI, ET NULLE PART AILLEURS.        │
	 * │                                                                        │
	 * │  L'ÉTAPE 2 NE TRAVERSE PAS `authenticate`. Elle est atteinte par       │
	 * │  `login_form_massifs_2fa`, hors de `wp_signon` : ni `Ecluse::barrer`   │
	 * │  (priorité 1) ni `Ecluse::reaffirmer` (priorité 100) ne s'exécutent    │
	 * │  sur ce chemin. Sans la vérification ci-dessous, UN JETON D'ÉTAPE 2    │
	 * │  OBTENU AVANT LA POSE D'UN VERROU OUVRIRAIT ENCORE UNE SESSION         │
	 * │  PENDANT CE VERROU, dans toute la fenêtre de 300 s du jeton — la       │
	 * │  ceinture aurait un trou, sur le seul chemin qui pose un cookie.       │
	 * └────────────────────────────────────────────────────────────────────────┘
	 *
	 * Le refus par verrou NE CONSOMME PAS DE TENTATIVE et n'alimente pas l'écluse :
	 * une requête verrouillée n'est pas un code faux, et la compter permettrait à un
	 * attaquant de maintenir indéfiniment un utilisateur légitime dehors. C'est
	 * exactement le traitement que la chaîne `authenticate` réserve déjà à
	 * `massifs_trop_de_tentatives`, jamais comptabilisé. Le jeton n'est pas brûlé non
	 * plus : l'utilisateur légitime doit pouvoir reprendre une fois le verrou expiré,
	 * et le TTL du jeton borne de toute façon cette fenêtre.
	 *
	 * PORTÉE HONNÊTE DU CORRECTIF : les clés de l'écluse sont l'IP hachée et le couple
	 * (identifiant × IP hachée) — jamais l'identifiant seul (arbitrage A-13). Un
	 * porteur de jeton qui change d'origine passe donc la vérification. C'est une
	 * conséquence ASSUMÉE d'A-13 : verrouiller sur le seul identifiant permettrait à
	 * n'importe quel visiteur de désactiver le compte de démonstration, dont les
	 * identifiants sont publiés (§6).
	 *
	 * @param string $jeton Jeton d'étape 2.
	 *
	 * @return string Message d'erreur, ou chaîne vide en cas de sortie par redirection.
	 */
	private static function traiter( string $jeton ): string {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';

		if ( '' === $jeton || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $jeton ) ) {
			return 'La page a expiré. Recommencez la connexion.';
		}

		$cle     = self::PREFIXE_JETON . sha1( $jeton );
		$dossier = get_transient( $cle );

		if ( ! is_array( $dossier ) || ! isset( $dossier['user_id'] ) ) {
			return 'Cette demande a expiré. Recommencez la connexion.';
		}

		$user_id = absint( $dossier['user_id'] );
		$compte  = get_userdata( $user_id );

		if ( false === $compte ) {
			delete_transient( $cle );

			return 'Cette demande a expiré. Recommencez la connexion.';
		}

		$refus_ecluse = self::refus_ecluse( (string) $compte->user_login );

		if ( '' !== $refus_ecluse ) {
			return $refus_ecluse;
		}

		$essais = isset( $dossier['essais'] ) ? absint( $dossier['essais'] ) + 1 : 1;

		if ( $essais > self::MAX_ESSAIS ) {
			delete_transient( $cle );
			self::signaler_echec( $compte );

			return 'Trop de codes incorrects. Recommencez la connexion.';
		}

		$dossier['essais'] = $essais;
		set_transient( $cle, $dossier, self::TTL_JETON );

		$code = isset( $_POST['massifs_2fa_code'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['massifs_2fa_code'] ) )
			: '';

		if ( ! self::code_accepte( $user_id, $code ) ) {
			self::signaler_echec( $compte );

			return 'Code incorrect. Saisissez le code affiché par votre application, ou l’un de vos codes de secours.';
		}

		delete_transient( $cle );

		wp_set_auth_cookie( $user_id, ! empty( $dossier['remember'] ) );

		/**
		 * Connexion aboutie : l'action du cœur est émise explicitement, l'étape 2
		 * ne passant pas par `wp_signon`. L'omettre priverait tout abonné — dont
		 * l'écluse, qui purge ses compteurs — de l'information.
		 */
		do_action( 'wp_login', $compte->user_login, $compte );

		$destination = isset( $dossier['redirect_to'] ) ? (string) $dossier['redirect_to'] : '';

		wp_safe_redirect( '' === $destination ? admin_url() : $destination );

		exit;
	}

	/**
	 * Le code soumis est-il un TOTP valide ou un code de secours valide ?
	 *
	 * Le TOTP d'abord : c'est le cas courant, et un code de secours mal recopié ne
	 * doit pas consommer une entrée pour rien.
	 *
	 * @param int    $user_id Compte concerné.
	 * @param string $code    Code soumis.
	 */
	private static function code_accepte( int $user_id, string $code ): bool {
		if ( ! class_exists( SecretUtilisateur::class ) ) {
			return false;
		}

		if ( SecretUtilisateur::verifier_code( $user_id, $code ) ) {
			return true;
		}

		return SecretUtilisateur::consommer_code_secours( $user_id, $code );
	}

	/**
	 * Message de refus si l'origine courante est verrouillée, chaîne vide sinon.
	 *
	 * La clé de verrouillage est celle de l'écluse : origine, et couple (identifiant ×
	 * origine). D'où l'usage de l'identifiant RÉSOLU DEPUIS LE JETON — l'étape 2 ne
	 * reçoit aucun identifiant de l'utilisateur, et en accepter un du formulaire
	 * offrirait le choix de sa propre clé de comptage.
	 *
	 * @param string $identifiant Identifiant du compte porté par le jeton.
	 */
	private static function refus_ecluse( string $identifiant ): string {
		if ( ! class_exists( Ecluse::class ) ) {
			return '';
		}

		$attente = Ecluse::attente( $identifiant );

		return $attente > 0 ? Ecluse::message( $attente ) : '';
	}

	/**
	 * Alimente l'écluse sur un échec d'étape 2.
	 *
	 * @param WP_User $compte Compte concerné.
	 */
	private static function signaler_echec( WP_User $compte ): void {
		if ( class_exists( Ecluse::class ) ) {
			Ecluse::signaler_echec( (string) $compte->user_login );
		}
	}

	/**
	 * Affiche le formulaire d'étape 2.
	 *
	 * @param string $jeton  Jeton d'étape 2.
	 * @param string $erreur Message d'erreur éventuel.
	 */
	private static function rendre( string $jeton, string $erreur ): void {
		if ( ! function_exists( 'login_header' ) || ! function_exists( 'login_footer' ) ) {
			wp_safe_redirect( wp_login_url() );

			exit;
		}

		login_header( 'Vérification en deux étapes' );

		echo '<form name="massifs_2fa" id="massifs_2fa" action="' . esc_url( self::url_etape_2( $jeton ) ) . '" method="post">';

		if ( '' !== $erreur ) {
			// L'erreur est liée au champ par `aria-describedby` : un lecteur d'écran
			// l'annonce en atteignant la zone de saisie, pas seulement en haut de page.
			echo '<div id="massifs-2fa-erreur" class="notice notice-error"><p>' . esc_html( $erreur ) . '</p></div>';
		}

		echo '<p><label for="massifs-2fa-code">Code de vérification</label>';
		printf(
			'<input type="text" name="massifs_2fa_code" id="massifs-2fa-code" class="input" value="" size="20"'
			. ' inputmode="numeric" autocomplete="one-time-code" autofocus="autofocus" required="required"'
			. ' aria-describedby="%1$s"%2$s /></p>',
			esc_attr( '' === $erreur ? 'massifs-2fa-aide' : 'massifs-2fa-erreur massifs-2fa-aide' ),
			'' === $erreur ? '' : ' aria-invalid="true"'
		);

		echo '<p id="massifs-2fa-aide" class="description">Saisissez les six chiffres affichés par votre application d’authentification, ou l’un de vos codes de secours au format XXXX-XXXX.</p>';

		wp_nonce_field( self::ACTION . '_' . $jeton );

		printf( '<input type="hidden" name="jeton" value="%s" />', esc_attr( $jeton ) );

		echo '<p class="submit"><button type="submit" class="button button-primary button-large">Vérifier</button></p>';
		echo '</form>';

		echo '<p id="nav"><a href="' . esc_url( wp_login_url() ) . '">Recommencer la connexion</a></p>';

		login_footer( 'massifs-2fa-code' );

		exit;
	}

	/**
	 * URL de l'étape 2.
	 *
	 * LE JETON VOYAGE DANS L'URL, faute d'autre canal : l'étape 1 ne pose aucun
	 * cookie, par construction. Il est à usage unique, expire en cinq minutes, et
	 * n'ouvre RIEN à lui seul — il ne donne accès qu'à la demande d'un second
	 * facteur que son porteur doit encore produire.
	 *
	 * @param string $jeton Jeton d'étape 2.
	 */
	private static function url_etape_2( string $jeton ): string {
		return add_query_arg(
			array(
				'action' => self::ACTION,
				'jeton'  => $jeton,
			),
			wp_login_url()
		);
	}

	/**
	 * Le contexte permet-il une redirection de navigateur ?
	 */
	private static function peut_rediriger(): bool {
		if ( headers_sent() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' ) ) {
			return false;
		}

		return ! ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) );
	}

	/**
	 * La case « se souvenir de moi » était-elle cochée à l'étape 1 ?
	 */
	private static function case_souvenir(): bool {
		// Lecture du formulaire de connexion du cœur, transportée telle quelle vers
		// l'étape 2 : sans elle, cocher la case n'aurait plus aucun effet sur un
		// compte à second facteur.
		return isset( $_POST['rememberme'] ) && '' !== (string) wp_unslash( $_POST['rememberme'] );
	}

	/**
	 * Destination demandée à l'étape 1.
	 *
	 * Conservée brute et confiée à `wp_safe_redirect`, qui refuse un hôte externe :
	 * une redirection ouverte au sortir d'une authentification est un vecteur
	 * d'hameçonnage classique.
	 */
	private static function redirection_demandee(): string {
		if ( ! isset( $_REQUEST['redirect_to'] ) ) {
			return '';
		}

		return esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) );
	}
}
