<?php
/**
 * Rampe d'enrôlement du second facteur, sur la page de profil.
 *
 * POURQUOI LA PAGE DE PROFIL ET PAS UNE ENTRÉE DE MENU (arbitrage A-10)
 *
 * Trois problèmes évacués d'un coup : aucune entrée de menu, donc ZÉRO COLLISION avec
 * les menus que les chaînes sœurs écrivent à l'aveugle dans le même arbre de travail ;
 * `profile.php` est toujours atteignable avec la seule capacité `read` ; et
 * l'enrôlement facultatif du gestionnaire y trouve sa place naturelle. Aucun
 * `add_menu_page`, aucun `add_submenu_page`, aucun slug en dur.
 *
 * POURQUOI LA RAMPE EXISTE AVANT L'APPLICATION (A-8)
 *
 * Sans elle, l'issue livrerait un MÉCANISME D'AUTO-ENFERMEMENT : second facteur imposé
 * aux administrateurs + téléphone perdu = production définitivement inaccessible. Un
 * administrateur requis mais non enrôlé est REDIRIGÉ vers l'enrôlement, jamais refusé.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  ÉCHAPPATOIRES EN CAS DE PERTE DU SECOND FACTEUR, DANS L'ORDRE                │
 * │                                                                               │
 * │  1. LES DIX CODES DE SECOURS, remis une seule fois à l'enrôlement. C'est le    │
 * │     chemin prévu, et le seul qui n'exige aucun accès serveur.                  │
 * │                                                                               │
 * │  2. WP-CLI, qui ne traverse JAMAIS `admin_init` et n'est donc pas concerné par │
 * │     la rampe :                                                                 │
 * │         wp user meta delete <id> massifs_totp_actif                            │
 * │         wp user meta delete <id> massifs_totp_secret                           │
 * │                                                                               │
 * │  3. LA CONSTANTE `MASSIFS_DESACTIVER_2FA`, à poser dans `wp-config.php` :      │
 * │         define( 'MASSIFS_DESACTIVER_2FA', true );                              │
 * │     ELLE EST LUE, JAMAIS ÉCRITE — ce module ne touche pas `wp-config.php`.     │
 * │                                                                               │
 * │  Le point 3 existe parce que la rampe redirige AUSSI `plugins.php` : un        │
 * │  administrateur enfermé ne peut même pas désactiver l'extension pour en sortir.│
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Auth;

use Exception;
use Massifs\Security\Roles\Capacites;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section « Double authentification » du profil et redirection obligatoire.
 */
final class Enrolement {

	/**
	 * Ancre de la section dans la page de profil.
	 */
	public const ANCRE = 'massifs-2fa';

	/**
	 * Préfixe du transient portant le secret provisoire.
	 */
	private const PREFIXE_PROVISOIRE = 'massifs_2fa_prov_';

	/**
	 * Durée de vie du secret provisoire, en secondes.
	 *
	 * Quinze minutes : le temps d'installer une application et de recopier un
	 * secret, pas le temps de laisser traîner un secret non confirmé en base.
	 */
	private const TTL_PROVISOIRE = 900;

	/**
	 * Préfixe du transient portant les codes de secours à afficher une seule fois.
	 */
	private const PREFIXE_CODES = 'massifs_2fa_codes_';

	/**
	 * Préfixe du transient portant l'avis à afficher après enregistrement.
	 */
	private const PREFIXE_AVIS = 'massifs_2fa_avis_';

	/**
	 * Pages d'administration atteignables sans second facteur enrôlé.
	 *
	 * LISTE MINIMALE ET FERMÉE. `profile.php` porte l'enrôlement et reçoit sa propre
	 * soumission ; rien d'autre n'est nécessaire pour en sortir. La déconnexion
	 * passe par `wp-login.php`, qui ne déclenche pas `admin_init` : elle reste
	 * accessible sans figurer ici.
	 *
	 * @var list<string>
	 */
	private const PAGES_AUTORISEES = array( 'profile.php' );

	/**
	 * Redirige vers l'enrôlement tant que le second facteur exigé n'est pas armé.
	 *
	 * `admin_init` PRIORITÉ 1 : avant que le moindre écran ne se construise, donc
	 * avant qu'une chaîne sœur n'ait pu émettre quoi que ce soit.
	 */
	public static function aiguiller(): void {
		if ( wp_doing_cron() || ! is_user_logged_in() ) {
			return;
		}

		if ( ! class_exists( Deuxfacteurs::class ) || Deuxfacteurs::desactivee_globalement() ) {
			return;
		}

		$compte = wp_get_current_user();

		if ( ! $compte instanceof WP_User || ! Deuxfacteurs::est_requis( $compte ) ) {
			return;
		}

		if ( Deuxfacteurs::est_actif( (int) $compte->ID ) ) {
			return;
		}

		// `admin-ajax.php` déclenche `admin_init` : sans ce refus, TOUTE la surface
		// AJAX de l'administration resterait ouverte à un compte non enrôlé, et la
		// rampe serait contournable par requête directe.
		if ( wp_doing_ajax() ) {
			wp_die(
				esc_html( 'Enrôlement du second facteur requis avant toute action.' ),
				esc_html( 'Second facteur requis' ),
				array( 'response' => 403 )
			);
		}

		$page = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

		if ( in_array( $page, self::PAGES_AUTORISEES, true ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'profile.php' ) . '#' . self::ANCRE );

		exit;
	}

	/**
	 * Affiche la section « Double authentification » du profil.
	 *
	 * @param WP_User|mixed $compte Compte affiché.
	 */
	public static function section_profil( mixed $compte ): void {
		if ( ! $compte instanceof WP_User || ! self::concerne( $compte ) ) {
			return;
		}

		$user_id  = (int) $compte->ID;
		$soi_meme = get_current_user_id() === $user_id;
		$actif    = class_exists( SecretUtilisateur::class ) && SecretUtilisateur::est_actif( $user_id );

		echo '<h2 id="' . esc_attr( self::ANCRE ) . '">Double authentification</h2>';

		self::afficher_avis( $user_id );

		if ( $actif ) {
			self::afficher_etat_actif( $user_id, $soi_meme );

			return;
		}

		if ( ! $soi_meme ) {
			// UN ADMINISTRATEUR NE VOIT JAMAIS LE SECRET D'UN AUTRE COMPTE : le
			// second facteur ne vaut que s'il est le seul à le détenir.
			echo '<p>Ce compte n’a pas encore enrôlé de second facteur. Lui seul peut le faire, depuis son propre profil.</p>';

			return;
		}

		self::afficher_formulaire_enrolement( $compte );
	}

	/**
	 * Traite la soumission de la section, depuis le formulaire de profil.
	 *
	 * @param int $user_id Compte enregistré.
	 */
	public static function enregistrer_profil( int $user_id ): void {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 || ! isset( $_POST['massifs_2fa_action'] ) ) {
			return;
		}

		// Nonce du formulaire de profil du cœur, revérifié : le cœur l'a déjà
		// contrôlé, mais une écriture qui dépend du contrôle d'un autre n'est pas
		// une écriture contrôlée.
		check_admin_referer( 'update-user_' . $user_id );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$action   = sanitize_key( wp_unslash( (string) $_POST['massifs_2fa_action'] ) );
		$soi_meme = get_current_user_id() === $user_id;

		if ( 'desactiver' === $action ) {
			SecretUtilisateur::desactiver( $user_id );
			self::poser_avis( $user_id, 'succes', 'Le second facteur a été désactivé.' );

			return;
		}

		// Les deux actions suivantes touchent au secret : elles n'appartiennent
		// qu'au titulaire du compte.
		if ( ! $soi_meme ) {
			return;
		}

		if ( 'regenerer' === $action ) {
			self::regenerer_codes( $user_id );

			return;
		}

		if ( 'activer' === $action ) {
			self::confirmer( $user_id );
		}
	}

	/**
	 * Confirme l'enrôlement à partir du secret provisoire.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function confirmer( int $user_id ): void {
		$secret = (string) get_transient( self::PREFIXE_PROVISOIRE . $user_id );

		if ( '' === $secret ) {
			self::poser_avis( $user_id, 'erreur', 'Le délai d’enrôlement a expiré. Un nouveau secret vient d’être généré.' );

			return;
		}

		$code = isset( $_POST['massifs_2fa_code'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['massifs_2fa_code'] ) )
			: '';

		$pas = Totp::verifier( $secret, $code );

		if ( null === $pas ) {
			self::poser_avis( $user_id, 'erreur', 'Ce code est incorrect ou périmé. Vérifiez l’heure de votre téléphone, puis réessayez.' );

			return;
		}

		SecretUtilisateur::activer( $user_id, $secret, $pas );
		delete_transient( self::PREFIXE_PROVISOIRE . $user_id );

		self::regenerer_codes( $user_id );
	}

	/**
	 * Génère et met de côté les codes de secours pour un affichage unique.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function regenerer_codes( int $user_id ): void {
		try {
			$codes = SecretUtilisateur::generer_codes_secours( $user_id );
		} catch ( Exception $exception ) {
			self::poser_avis( $user_id, 'erreur', 'Les codes de secours n’ont pas pu être générés. Réessayez.' );

			return;
		}

		// Transient, et non méta : ces codes en clair ne doivent survivre ni à
		// l'affichage, ni à la journée. Le rendu les efface.
		set_transient( self::PREFIXE_CODES . $user_id, $codes, 300 );

		self::poser_avis( $user_id, 'succes', 'Second facteur en place. Conservez vos codes de secours dès maintenant : ils ne seront plus jamais affichés.' );
	}

	/**
	 * Affiche l'état d'un compte déjà enrôlé.
	 *
	 * @param int  $user_id  Compte concerné.
	 * @param bool $soi_meme Le compte se regarde-t-il lui-même ?
	 */
	private static function afficher_etat_actif( int $user_id, bool $soi_meme ): void {
		$restants = SecretUtilisateur::nombre_codes_secours( $user_id );
		$depuis   = SecretUtilisateur::active_le( $user_id );

		echo '<p>Le second facteur est <strong>actif</strong>';

		if ( '' !== $depuis ) {
			echo ' depuis le ' . esc_html( self::formater( $depuis ) );
		}

		echo '.</p>';

		printf(
			'<p>Codes de secours restants : <strong>%d</strong> sur %d.</p>',
			(int) $restants,
			(int) SecretUtilisateur::NOMBRE_CODES
		);

		if ( $restants <= 2 ) {
			echo '<p><strong>Il vous reste très peu de codes de secours.</strong> Régénérez-les : les anciens seront invalidés.</p>';
		}

		self::afficher_codes( $user_id );

		if ( $soi_meme ) {
			echo '<p><button type="submit" name="massifs_2fa_action" value="regenerer" class="button">Régénérer les codes de secours</button></p>';
		}

		echo '<p><button type="submit" name="massifs_2fa_action" value="desactiver" class="button">Désactiver le second facteur</button></p>';
	}

	/**
	 * Affiche le formulaire d'enrôlement du titulaire.
	 *
	 * @param WP_User $compte Compte concerné.
	 */
	private static function afficher_formulaire_enrolement( WP_User $compte ): void {
		$user_id = (int) $compte->ID;
		$secret  = self::secret_provisoire( $user_id );

		if ( '' === $secret ) {
			echo '<p>Le secret n’a pas pu être généré : la source d’aléa du serveur est indisponible. Contactez l’hébergeur.</p>';

			return;
		}

		$uri = Totp::uri_otpauth(
			(string) $compte->user_login,
			(string) get_bloginfo( 'name' ),
			$secret
		);

		echo '<p>Ajoutez ce site à votre application d’authentification, puis saisissez le code affiché pour confirmer.</p>';

		echo '<p>Secret à recopier, en blocs de quatre caractères :<br />';
		echo '<code>' . esc_html( Totp::grouper( $secret ) ) . '</code></p>';

		printf(
			'<p><label for="massifs-2fa-uri">Adresse à coller dans l’application, si elle l’accepte</label><br />'
			. '<input type="text" id="massifs-2fa-uri" class="large-text code" readonly value="%s" /></p>',
			esc_attr( $uri )
		);

		printf(
			'<p><label for="massifs-2fa-code">Code à six chiffres affiché par l’application</label><br />'
			. '<input type="text" id="massifs-2fa-code" name="massifs_2fa_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" size="8" aria-describedby="massifs-2fa-aide" />'
			. '<span id="massifs-2fa-aide" class="description"> Six chiffres, sans espace.</span></p>'
		);

		echo '<p><button type="submit" name="massifs_2fa_action" value="activer" class="button button-primary">Confirmer et activer</button></p>';
	}

	/**
	 * Secret provisoire, généré au besoin.
	 *
	 * IL N'EST PAS ÉCRIT DANS `massifs_totp_secret` : un secret posé là armerait le
	 * compte à moitié — la demande de second facteur se déclencherait sur un secret
	 * que l'utilisateur n'a pas encore enregistré dans son application.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function secret_provisoire( int $user_id ): string {
		$existant = (string) get_transient( self::PREFIXE_PROVISOIRE . $user_id );

		if ( '' !== $existant ) {
			return $existant;
		}

		try {
			$secret = Totp::secret();
		} catch ( Exception $exception ) {
			return '';
		}

		set_transient( self::PREFIXE_PROVISOIRE . $user_id, $secret, self::TTL_PROVISOIRE );

		return $secret;
	}

	/**
	 * Affiche les codes de secours, une seule fois.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function afficher_codes( int $user_id ): void {
		$codes = get_transient( self::PREFIXE_CODES . $user_id );

		if ( ! is_array( $codes ) || array() === $codes ) {
			return;
		}

		delete_transient( self::PREFIXE_CODES . $user_id );

		echo '<div class="notice notice-warning inline"><p><strong>Vos dix codes de secours. Imprimez-les ou notez-les maintenant : ils ne seront plus jamais affichés.</strong> Chacun ne sert qu’une fois.</p>';
		echo '<ol>';

		foreach ( $codes as $code ) {
			echo '<li><code>' . esc_html( (string) $code ) . '</code></li>';
		}

		echo '</ol></div>';
	}

	/**
	 * Affiche puis efface l'avis de la dernière opération.
	 *
	 * @param int $user_id Compte concerné.
	 */
	private static function afficher_avis( int $user_id ): void {
		$avis = get_transient( self::PREFIXE_AVIS . $user_id );

		if ( ! is_array( $avis ) || ! isset( $avis['type'], $avis['message'] ) ) {
			return;
		}

		delete_transient( self::PREFIXE_AVIS . $user_id );

		printf(
			'<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
			'erreur' === $avis['type'] ? 'error' : 'success',
			esc_html( (string) $avis['message'] )
		);
	}

	/**
	 * Met de côté un avis pour le prochain rendu du profil.
	 *
	 * @param int    $user_id Compte concerné.
	 * @param string $type    `succes` ou `erreur`.
	 * @param string $message Message affiché.
	 */
	private static function poser_avis( int $user_id, string $type, string $message ): void {
		set_transient(
			self::PREFIXE_AVIS . $user_id,
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Ce compte est-il concerné par le second facteur ?
	 *
	 * Imposé à l'administrateur, disponible en option au gestionnaire, invisible
	 * pour les autres comptes — qui n'ont rien à protéger sur ce site.
	 *
	 * @param WP_User $compte Compte évalué.
	 */
	private static function concerne( WP_User $compte ): bool {
		if ( ! class_exists( Deuxfacteurs::class ) || ! class_exists( SecretUtilisateur::class ) ) {
			return false;
		}

		if ( Deuxfacteurs::est_requis( $compte ) ) {
			return true;
		}

		if ( ! class_exists( Capacites::class ) ) {
			return false;
		}

		return user_can( (int) $compte->ID, Capacites::PUBLIER )
			|| SecretUtilisateur::est_actif( (int) $compte->ID );
	}

	/**
	 * Formate un instant ISO 8601 UTC pour l'affichage.
	 *
	 * @param string $iso_utc Instant stocké.
	 */
	private static function formater( string $iso_utc ): string {
		$horodatage = strtotime( $iso_utc );

		if ( false === $horodatage ) {
			return $iso_utc;
		}

		return (string) wp_date( 'j F Y à H\hi', $horodatage );
	}
}
