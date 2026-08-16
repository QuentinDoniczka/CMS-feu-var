<?php
/**
 * Écluse anti-force-brute des tentatives de connexion.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  JAMAIS, EN AUCUN CAS, UN COMPTEUR PAR IDENTIFIANT SEUL.                      │
 * │                                                                               │
 * │  LES IDENTIFIANTS DU COMPTE DE DÉMONSTRATION SONT PUBLIÉS SUR LE SITE (§6 DU  │
 * │  BRIEF). UN COMPTEUR PAR IDENTIFIANT PERMETTRAIT À N'IMPORTE QUEL VISITEUR DE │
 * │  DÉSACTIVER LA DÉMONSTRATION EN DIX REQUÊTES RATÉES — UN SABOTAGE À UN CLIC   │
 * │  DE L'ARGUMENTAIRE COMMERCIAL DU SITE. LA CLÉ PRIMAIRE EST L'IP HACHÉE ; LA   │
 * │  CLÉ SECONDAIRE EST LE COUPLE (IDENTIFIANT × IP HACHÉE). SANS IP LISIBLE,     │
 * │  AUCUN COMPTAGE N'A LIEU — L'ABSENCE DE PROTECTION EST PRÉFÉRABLE À UNE       │
 * │  PROTECTION QUI SE RETOURNE EN DÉNI DE SERVICE SUR LA DÉMO.                   │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI LE STOCKAGE EST SÉPARÉ EN DEUX (arbitrage A-11)
 *
 * `Alertes\Verrou` a choisi l'option parce qu'un transient est évincible sous pression
 * du cache objet. Ici les prémisses s'inversent PARTIELLEMENT, et la ligne de partage
 * est posée là où la conséquence d'une éviction change de nature :
 *
 *   COMPTEURS D'ÉCHECS → transients, un par clé, TTL = fenêtre. L'écluse écrit à
 *   CHAQUE échec, depuis potentiellement des centaines d'IP. Un registre partagé
 *   unique en ferait un read-modify-write non atomique où deux échecs concurrents
 *   s'écrasent, plus une amplification d'écriture sur `wp_options`. Et perdre un
 *   INCRÉMENT est immatériel : l'attaquant franchit le seuil une fraction de seconde
 *   plus tard.
 *
 *   VERROUS ACTIFS → registre borné en option, `autoload = false`. Perdre un VERROU
 *   n'est pas immatériel : ce serait la protection elle-même qui disparaît, en
 *   silence, au pire moment.
 *
 * POURQUOI L'IP EST HACHÉE (A-12)
 *
 * Le §9 du brief n'admet que le traitement des comptes internes. `hash_hmac` avec le
 * sel d'authentification garde le compteur et abandonne la capacité forensique, dont
 * nous n'avons pas besoin : ce n'est pas un SIEM. AUCUNE IP EN CLAIR N'EST STOCKÉE,
 * NI JOURNALISÉE, NI EXPORTÉE (interdit 8).
 *
 * COMMENT LE VERROU EST RÉELLEMENT OPPOSÉ — CEINTURE ET BRETELLES
 *
 * Un `WP_Error` renvoyé en priorité 1 est SILENCIEUSEMENT IGNORÉ par le cœur : ses
 * rappels de priorité 20 ne se court-circuitent que sur un `WP_User`. Le refus repose
 * donc sur deux mécanismes indépendants, et il faut les deux :
 *
 *   `barrer()`     priorité 1   → retire les trois rappels de mot de passe du cœur,
 *                                 dans la branche verrouillée SEULEMENT. C'est ce qui
 *                                 garantit qu'AUCUN HACHAGE N'EST CALCULÉ.
 *   `constater()`  priorité 40  → coupe court sur verrou actif, avant que `Deuxfacteurs`
 *                                 (50) ne puisse rediriger et `exit`, et avant que quoi
 *                                 que ce soit ne prenne la tentative pour un succès.
 *   `reaffirmer()` priorité 100 → après le dernier rappel du cœur (99). Refuse même un
 *                                 `WP_User` parfaitement valide. C'est le filet fermé :
 *                                 il ne dépend du bon comportement d'aucun autre rappel.
 *
 * Et la purge (`sur_connexion()`) REFUSE DE S'EXÉCUTER pendant un verrou vivant : sans
 * cela, qui connaît le mot de passe effacerait son propre verrou.
 *
 * POURQUOI `X-Forwarded-For` N'EST PAS HONORÉ
 *
 * C'est un en-tête FALSIFIABLE : le lire par défaut rendrait l'écluse triviale à
 * contourner en variant une chaîne à chaque requête. Derrière un proxy de confiance,
 * le filtre `massifs_auth_ip_client` permet de l'activer en connaissance de cause.
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
 * Comptage des échecs et verrouillage temporaire.
 */
final class Ecluse {

	/**
	 * Registre borné des verrous actifs.
	 */
	public const OPTION_VERROUS = 'massifs_ecluse_verrous';

	/**
	 * Code d'erreur du refus par verrouillage.
	 *
	 * Volontairement DISTINCT du message uniformisé d'identifiants invalides : un
	 * utilisateur légitime doit savoir combien de temps attendre, sinon il
	 * réessaie en boucle et prolonge son propre verrou.
	 */
	public const CODE_REFUS = 'massifs_trop_de_tentatives';

	/**
	 * Nombre maximal de verrous conservés, borné sur l'ordre d'insertion.
	 */
	private const MAX_VERROUS = 100;

	/**
	 * Préfixe des transients de comptage.
	 */
	private const PREFIXE_COMPTEUR = 'massifs_ecluse_c_';

	/**
	 * Longueur de clé conservée après hachage.
	 */
	private const LONGUEUR_CLE = 32;

	/**
	 * Codes d'échec comptabilisés.
	 *
	 * `empty_username` et `empty_password` sont EXCLUS : un formulaire soumis à
	 * vide est une maladresse, pas une tentative, et les compter permettrait de
	 * remplir le compteur sans jamais éprouver un mot de passe. Nos propres refus
	 * (`massifs_trop_de_tentatives`, `massifs_compte_suspendu`) sont exclus aussi,
	 * sans quoi un verrou s'auto-entretiendrait indéfiniment.
	 *
	 * @var list<string>
	 */
	private const CODES_COMPTES = array(
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'incorrect_password_reset',
	);

	/**
	 * Rappels du cœur qui vérifient un mot de passe sur `authenticate`.
	 *
	 * Tous en priorité 20, enregistrés par `wp-includes/default-filters.php`.
	 *
	 * @var array<string, int>
	 */
	private const RAPPELS_MOT_DE_PASSE_COEUR = array(
		'wp_authenticate_username_password'    => 20,
		'wp_authenticate_email_password'       => 20,
		'wp_authenticate_application_password' => 20,
	);

	/**
	 * Rappels du cœur RÉELLEMENT retirés par la tentative en cours.
	 *
	 * Mémorisés pour être remis en place à l'issue de la même chaîne, et EUX SEULS :
	 * un site qui aurait délibérément désenregistré l'un d'eux ne doit pas se le voir
	 * ressusciter par nous.
	 *
	 * @var array<string, int>
	 */
	private static array $rappels_desarmes = array();

	/**
	 * Refuse une tentative provenant d'une origine verrouillée.
	 *
	 * PRIORITÉ 1 SUR `authenticate`, AVANT LE CŒUR (priorité 20).
	 *
	 * ┌────────────────────────────────────────────────────────────────────────┐
	 * │  RENVOYER UN `WP_Error` NE SUFFIT PAS, ET NE L'A JAMAIS FAIT.          │
	 * │                                                                        │
	 * │  `wp_authenticate_username_password()` ne se court-circuite que sur un │
	 * │  `WP_User` (« if ( $user instanceof WP_User ) { return $user; } »).    │
	 * │  UN `WP_Error` ENTRANT EST PUREMENT IGNORÉ : le cœur enchaîne sur      │
	 * │  `get_user_by()` puis `wp_check_password()` et RENVOIE LE COMPTE si le │
	 * │  mot de passe est bon — le verrou était annoncé, jamais opposé.        │
	 * └────────────────────────────────────────────────────────────────────────┘
	 *
	 * D'où le désarmement explicite des trois rappels du cœur, UNIQUEMENT dans la
	 * branche verrouillée. C'est lui, et lui seul, qui tient la promesse d'origine :
	 * une requête verrouillée est rejetée SANS QU'AUCUN HACHAGE DE MOT DE PASSE NE
	 * SOIT CALCULÉ — aucun oracle de temps de réponse, aucune charge offerte à
	 * l'attaquant.
	 *
	 * Ce désarmement est une mesure de coût, pas la mesure d'accès : le refus
	 * d'accès, lui, est réaffirmé en priorité 100 par `reaffirmer()`, qui ne dépend
	 * du comportement d'aucun autre rappel — et qui remet les rappels du cœur en
	 * place, le désarmement ne devant durer que le temps de la tentative.
	 *
	 * @param mixed  $utilisateur Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant Identifiant soumis.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function barrer( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $mot_de_passe );

		$attente = self::attente( $identifiant );

		if ( $attente <= 0 ) {
			return $utilisateur;
		}

		self::desarmer_verification_du_coeur();

		return new WP_Error( self::CODE_REFUS, self::message( $attente ) );
	}

	/**
	 * Réaffirme le refus en fin de chaîne, quoi qu'aient fait les autres rappels.
	 *
	 * PRIORITÉ 100, donc APRÈS le dernier rappel du cœur (`wp_authenticate_spam_check`,
	 * priorité 99) et après tout ce que le projet enregistre.
	 *
	 * FILET FERMÉ, DÉLIBÉRÉMENT REDONDANT AVEC `barrer()`. Si un `WP_User` valide
	 * arrive ici alors qu'un verrou est actif pour cette origine — parce qu'une
	 * extension a réenregistré un rappel de mot de passe, parce que l'ordre des
	 * priorités a bougé, parce que le désarmement de `barrer()` a été contourné —
	 * IL EST REFUSÉ QUAND MÊME. Aucune session ne s'ouvre pendant un verrou, y
	 * compris avec le bon mot de passe.
	 *
	 * @param mixed  $utilisateur  Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant  Identifiant soumis.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function reaffirmer( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $mot_de_passe );

		self::rearmer_verification_du_coeur();

		$attente = self::attente( $identifiant );

		if ( $attente <= 0 ) {
			return $utilisateur;
		}

		return new WP_Error( self::CODE_REFUS, self::message( $attente ) );
	}

	/**
	 * Comptabilise un échec et pose le verrou si un seuil est franchi.
	 *
	 * Priorité 40 sur `authenticate` : après le cœur, qui a produit le code
	 * d'erreur, et avant l'uniformisation du message (priorité 45), qui l'efface.
	 *
	 * VERROU ACTIF = SORTIE IMMÉDIATE PAR LE REFUS, avant toute autre lecture du
	 * résultat courant. Deux pièges se ferment d'un coup :
	 *
	 *   1. Un `WP_User` produit par le cœur pendant un verrou n'atteint jamais la
	 *      suite de la chaîne. Sans cela, `Deuxfacteurs` (priorité 50) redirigerait
	 *      vers l'étape 2 en appelant `exit`, et la réaffirmation de la priorité 100
	 *      ne s'exécuterait jamais.
	 *   2. Rien de ce qui suit ne peut interpréter cette tentative comme un succès,
	 *      donc rien ne peut purger le verrou que l'on est en train d'opposer. Un
	 *      attaquant qui CONNAÎT le mot de passe ne doit pas pouvoir effacer son
	 *      propre verrou en s'en servant.
	 *
	 * Le comptage n'en est pas affecté : pendant un verrou, `barrer()` a déjà
	 * substitué notre propre code d'erreur, qui n'est pas comptabilisable — un
	 * verrou ne s'auto-entretient jamais.
	 *
	 * @param mixed  $utilisateur  Résultat courant de la chaîne d'authentification.
	 * @param string $identifiant  Identifiant soumis.
	 * @param string $mot_de_passe Mot de passe soumis, jamais lu.
	 *
	 * @return mixed
	 */
	public static function constater( mixed $utilisateur, string $identifiant = '', string $mot_de_passe = '' ): mixed {
		unset( $mot_de_passe );

		$verrou = self::attente( $identifiant );

		if ( $verrou > 0 ) {
			return new WP_Error( self::CODE_REFUS, self::message( $verrou ) );
		}

		if ( ! is_wp_error( $utilisateur ) || ! self::est_comptabilisable( $utilisateur ) ) {
			return $utilisateur;
		}

		$attente = self::signaler_echec( $identifiant );

		if ( $attente <= 0 ) {
			return $utilisateur;
		}

		// Le seuil vient d'être franchi : on annonce le délai plutôt que de laisser
		// passer un « identifiants incorrects » qui inviterait à réessayer aussitôt.
		return new WP_Error( self::CODE_REFUS, self::message( $attente ) );
	}

	/**
	 * Comptabilise un échec pour l'origine courante.
	 *
	 * Point d'entrée public : la vérification du second facteur s'en sert aussi.
	 * Un code à six chiffres est brute-forçable, le verrouillage doit donc couvrir
	 * l'étape 2 comme il couvre l'étape 1.
	 *
	 * @param string $identifiant Identifiant concerné.
	 *
	 * @return int Secondes d'attente si un verrou vient d'être posé, `0` sinon.
	 */
	public static function signaler_echec( string $identifiant ): int {
		$portees = self::portees( $identifiant );

		if ( array() === $portees ) {
			return 0;
		}

		$seuils  = self::seuils();
		$attente = 0;
		$echecs  = 0;

		foreach ( $portees as $portee => $cle ) {
			$reglage = $seuils[ $portee ];
			$compte  = self::incrementer( $cle, (int) $reglage['fenetre'] );
			$echecs  = max( $echecs, $compte );

			if ( $compte < (int) $reglage['essais'] ) {
				continue;
			}

			self::poser_verrou( $cle, (int) $reglage['verrou'] );
			self::oublier_compteur( $cle );

			$attente = max( $attente, (int) $reglage['verrou'] );
		}

		self::temporiser( $echecs );

		return $attente;
	}

	/**
	 * Purge compteurs et verrous après une connexion réussie.
	 *
	 * Sans cette purge, neuf échecs suivis d'une connexion réussie laisseraient un
	 * compteur à neuf : le prochain lapsus du même utilisateur légitime le
	 * verrouillerait.
	 *
	 * ON NE PURGE JAMAIS PENDANT UN VERROU ACTIF. La chaîne `authenticate` est
	 * censée rendre ce cas inatteignable, mais `wp_login` est une action publique :
	 * l'étape 2 du second facteur l'émet elle-même, et n'importe quel chemin de
	 * connexion l'émettra demain. Si un `wp_login` survenait malgré un verrou vivant,
	 * purger reviendrait à offrir à qui connaît le mot de passe le moyen d'effacer
	 * son propre verrou. Le verrou expire de lui-même ; la purge attendra.
	 *
	 * @param string $identifiant Identifiant connecté.
	 * @param mixed  $utilisateur Compte connecté, non utilisé.
	 */
	public static function sur_connexion( string $identifiant, mixed $utilisateur = null ): void {
		unset( $utilisateur );

		if ( self::attente( $identifiant ) > 0 ) {
			return;
		}

		$portees = self::portees( $identifiant );

		if ( array() === $portees ) {
			return;
		}

		foreach ( $portees as $cle ) {
			self::oublier_compteur( $cle );
		}

		self::lever_verrous( array_values( $portees ) );
	}

	/**
	 * Applique l'écluse au formulaire de mot de passe oublié.
	 *
	 * Ce formulaire ne traverse pas `authenticate` : sans cette greffe, il
	 * resterait un canal d'énumération et de bombardement de courriels non limité.
	 * Une demande y est comptée comme une tentative — le débit légitime est d'une
	 * demande par personne et par jour, très loin des seuils.
	 *
	 * @param mixed $erreurs Collecteur d'erreurs du cœur.
	 */
	public static function sur_mot_de_passe_perdu( mixed $erreurs ): void {
		if ( ! $erreurs instanceof WP_Error ) {
			return;
		}

		// Lecture d'un champ de formulaire du cœur à seule fin de comptage : aucune
		// écriture, aucune décision d'accès, et la valeur est assainie avant usage.
		$identifiant = isset( $_POST['user_login'] )
			? sanitize_user( wp_unslash( (string) $_POST['user_login'] ), true )
			: '';

		$attente = self::attente( $identifiant );

		if ( $attente > 0 ) {
			$erreurs->add( self::CODE_REFUS, self::message( $attente ) );

			return;
		}

		self::signaler_echec( $identifiant );
	}

	/**
	 * Secondes restantes avant expiration du verrou le plus long, `0` si libre.
	 *
	 * @param string $identifiant Identifiant soumis.
	 */
	public static function attente( string $identifiant ): int {
		$portees = self::portees( $identifiant );

		if ( array() === $portees ) {
			return 0;
		}

		$verrous    = self::verrous();
		$maintenant = time();
		$attente    = 0;

		foreach ( $portees as $cle ) {
			if ( ! isset( $verrous[ $cle ] ) ) {
				continue;
			}

			$attente = max( $attente, (int) $verrous[ $cle ] - $maintenant );
		}

		return max( 0, $attente );
	}

	/**
	 * Adresse IP du client, validée.
	 *
	 * `REMOTE_ADDR` UNIQUEMENT. Renvoie une chaîne vide si elle est illisible ou
	 * invalide — en pratique uniquement hors contexte HTTP (WP-CLI, cron), qui ne
	 * doivent jamais être verrouillés.
	 */
	public static function ip_client(): string {
		$brut = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$ip = filter_var( $brut, FILTER_VALIDATE_IP );

		/**
		 * Adresse IP retenue par l'écluse.
		 *
		 * Point d'entrée EXPLICITE pour honorer `X-Forwarded-For` derrière un proxy
		 * de confiance. Ne l'activer que si le serveur réécrit réellement l'en-tête :
		 * sinon l'écluse devient contournable à volonté.
		 *
		 * @param string $ip Adresse validée, ou chaîne vide.
		 */
		$filtree = (string) apply_filters( 'massifs_auth_ip_client', is_string( $ip ) ? $ip : '' );

		$valide = filter_var( $filtree, FILTER_VALIDATE_IP );

		return is_string( $valide ) ? $valide : '';
	}

	/**
	 * Seuils de l'écluse.
	 *
	 * Ces valeurs sont un JUGEMENT D'INGÉNIERIE, pas un fait du brief : le §6 dit
	 * « tentatives limitées » sans donner un seul chiffre. Elles sont filtrables.
	 *
	 * @return array<string, array{essais:int,fenetre:int,verrou:int}>
	 */
	public static function seuils(): array {
		$defauts = array(
			// 10 essais / 15 min → verrou 15 min.
			'ip'        => array(
				'essais'  => 10,
				'fenetre' => 900,
				'verrou'  => 900,
			),
			// Palier long : 20 essais / 1 h → verrou 1 h. Il attrape l'attaquant
			// patient qui reste sous le seuil court.
			'ip_palier' => array(
				'essais'  => 20,
				'fenetre' => 3600,
				'verrou'  => 3600,
			),
			// 5 essais / 15 min → verrou 15 min, sur le couple identifiant × IP.
			'couple'    => array(
				'essais'  => 5,
				'fenetre' => 900,
				'verrou'  => 900,
			),
		);

		/**
		 * Seuils de l'écluse.
		 *
		 * @param array<string, array{essais:int,fenetre:int,verrou:int}> $defauts Seuils par défaut.
		 */
		$filtres = apply_filters( 'massifs_auth_ecluse_seuils', $defauts );

		if ( ! is_array( $filtres ) ) {
			return $defauts;
		}

		foreach ( $defauts as $portee => $reglage ) {
			if ( ! isset( $filtres[ $portee ] ) || ! is_array( $filtres[ $portee ] ) ) {
				$filtres[ $portee ] = $reglage;

				continue;
			}

			foreach ( $reglage as $champ => $valeur ) {
				$propose = isset( $filtres[ $portee ][ $champ ] ) ? (int) $filtres[ $portee ][ $champ ] : 0;

				// Plancher à 1 : un seuil à zéro verrouillerait tout le monde dès la
				// première requête, une fenêtre à zéro n'expirerait jamais.
				$filtres[ $portee ][ $champ ] = max( 1, $propose );
			}
		}

		return $filtres;
	}

	/**
	 * Retire les rappels du cœur qui vérifieraient le mot de passe.
	 *
	 * APPELÉ UNIQUEMENT DEPUIS LA BRANCHE VERROUILLÉE de `barrer()`, jamais
	 * inconditionnellement : désarmer le cœur hors verrou rendrait toute connexion
	 * impossible pour le reste de la requête.
	 *
	 * Retirer un rappel pendant l'itération de son propre crochet est un usage
	 * explicitement pris en charge par `WP_Hook`, qui réajuste ses itérations en
	 * cours (`_resort_active_iterations`). Le retrait vaut pour la requête entière,
	 * ce qui est exactement l'effet recherché : une requête verrouillée ne doit
	 * calculer aucun hachage, par aucun chemin.
	 */
	private static function desarmer_verification_du_coeur(): void {
		foreach ( self::RAPPELS_MOT_DE_PASSE_COEUR as $rappel => $priorite ) {
			if ( remove_filter( 'authenticate', $rappel, $priorite ) ) {
				self::$rappels_desarmes[ $rappel ] = $priorite;
			}
		}
	}

	/**
	 * Remet en place les rappels du cœur retirés par la tentative en cours.
	 *
	 * Appelé en fin de chaîne (priorité 100), donc BIEN APRÈS la priorité 20 : le
	 * hachage n'a pas été calculé, et il ne le sera pas. `WP_Hook` n'exécute pas une
	 * priorité inférieure réinsérée pendant l'itération en cours — il avance son
	 * pointeur au-delà.
	 *
	 * POURQUOI REMETTRE : le retrait vaut sinon pour toute la durée du processus.
	 * En requête web c'est sans conséquence (une seule chaîne `authenticate`), mais
	 * en WP-CLI ou dans un processus qui authentifie deux fois, la seconde tentative
	 * échouerait sans qu'aucun verrou ne s'y oppose — un faux refus silencieux, avec
	 * le code générique `authentication_failed`. Le désarmement doit durer ce que dure
	 * la tentative verrouillée, pas davantage.
	 */
	private static function rearmer_verification_du_coeur(): void {
		if ( array() === self::$rappels_desarmes ) {
			return;
		}

		foreach ( self::$rappels_desarmes as $rappel => $priorite ) {
			add_filter( 'authenticate', $rappel, $priorite, 3 );
		}

		self::$rappels_desarmes = array();
	}

	/**
	 * Clés de comptage applicables à cette tentative.
	 *
	 * @param string $identifiant Identifiant soumis.
	 *
	 * @return array<string, string> Portée => clé hachée.
	 */
	private static function portees( string $identifiant ): array {
		$ip = self::ip_client();

		if ( '' === $ip ) {
			return array();
		}

		$portees = array(
			'ip'        => self::cle( 'ip|' . $ip ),
			'ip_palier' => self::cle( 'ip6|' . $ip ),
		);

		$normalise = strtolower( trim( $identifiant ) );

		if ( '' !== $normalise ) {
			$portees['couple'] = self::cle( 'cp|' . $normalise . '|' . $ip );
		}

		return $portees;
	}

	/**
	 * Clé de comptage : HMAC tronqué, jamais la valeur en clair.
	 *
	 * @param string $graine Valeur à hacher.
	 */
	private static function cle( string $graine ): string {
		return substr( hash_hmac( 'sha256', $graine, wp_salt( 'auth' ) ), 0, self::LONGUEUR_CLE );
	}

	/**
	 * Incrémente un compteur à fenêtre FIXE.
	 *
	 * La fin de fenêtre est stockée avec le compteur, et le TTL du transient est
	 * recalculé sur le temps restant. Sans cela, chaque incrément relancerait le
	 * TTL : la fenêtre deviendrait glissante et un utilisateur légitime accumulant
	 * un échec toutes les quatorze minutes finirait verrouillé.
	 *
	 * @param string $cle     Clé de comptage.
	 * @param int    $fenetre Durée de la fenêtre, en secondes.
	 */
	private static function incrementer( string $cle, int $fenetre ): int {
		$nom  = self::PREFIXE_COMPTEUR . $cle;
		$brut = get_transient( $nom );

		$maintenant = time();
		$compte     = 0;
		$fin        = $maintenant + $fenetre;

		if ( is_array( $brut ) && isset( $brut['n'], $brut['fin'] ) && (int) $brut['fin'] > $maintenant ) {
			$compte = (int) $brut['n'];
			$fin    = (int) $brut['fin'];
		}

		++$compte;

		set_transient(
			$nom,
			array(
				'n'   => $compte,
				'fin' => $fin,
			),
			max( 1, $fin - $maintenant )
		);

		return $compte;
	}

	/**
	 * Supprime un compteur.
	 *
	 * @param string $cle Clé de comptage.
	 */
	private static function oublier_compteur( string $cle ): void {
		delete_transient( self::PREFIXE_COMPTEUR . $cle );
	}

	/**
	 * Le code d'erreur produit par le cœur est-il comptabilisable ?
	 *
	 * @param WP_Error $erreur Erreur d'authentification.
	 */
	private static function est_comptabilisable( WP_Error $erreur ): bool {
		foreach ( $erreur->get_error_codes() as $code ) {
			if ( in_array( (string) $code, self::CODES_COMPTES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registre des verrous actifs, expirés balayés.
	 *
	 * @return array<string, int> Clé => instant d'expiration.
	 */
	private static function verrous(): array {
		$brut = get_option( self::OPTION_VERROUS, array() );

		if ( ! is_array( $brut ) ) {
			return array();
		}

		$maintenant = time();
		$actifs     = array();

		foreach ( $brut as $cle => $fin ) {
			if ( ! is_string( $cle ) || '' === $cle || ! is_scalar( $fin ) ) {
				continue;
			}

			if ( (int) $fin <= $maintenant ) {
				continue;
			}

			$actifs[ $cle ] = (int) $fin;
		}

		return $actifs;
	}

	/**
	 * Pose un verrou et borne le registre.
	 *
	 * Le balayage des verrous expirés est PARESSEUX, à l'écriture : aucune tâche
	 * planifiée n'est nécessaire, et un registre qui n'est plus écrit est un
	 * registre que plus personne n'attaque.
	 *
	 * @param string $cle   Clé verrouillée.
	 * @param int    $duree Durée du verrou, en secondes.
	 */
	private static function poser_verrou( string $cle, int $duree ): void {
		$verrous = self::verrous();

		// Réinsertion en fin de tableau : l'ordre d'insertion porte à lui seul
		// l'ancienneté, ce qui rend le bornage exact sans lire une seule date.
		unset( $verrous[ $cle ] );
		$verrous[ $cle ] = time() + max( 1, $duree );

		$verrous = array_slice( $verrous, -self::MAX_VERROUS, null, true );

		update_option( self::OPTION_VERROUS, $verrous, false );
	}

	/**
	 * Lève les verrous portant sur ces clés.
	 *
	 * @param list<string> $cles Clés à libérer.
	 */
	private static function lever_verrous( array $cles ): void {
		$verrous = self::verrous();
		$avant   = count( $verrous );

		foreach ( $cles as $cle ) {
			unset( $verrous[ $cle ] );
		}

		// N'écrit que sur changement réel : une connexion réussie est fréquente, le
		// registre l'est beaucoup moins.
		if ( count( $verrous ) === $avant ) {
			return;
		}

		update_option( self::OPTION_VERROUS, $verrous, false );
	}

	/**
	 * Temporisation progressive.
	 *
	 * À PARTIR DU DEUXIÈME ÉCHEC SEULEMENT, jamais sur un succès, et toujours
	 * APRÈS que la décision de réponse a été prise : temporiser une réponse déjà
	 * décidée coûte à l'attaquant sans rien changer pour l'utilisateur légitime,
	 * qui ne repasse pas par là. Une temporisation au premier échec pénaliserait
	 * une simple faute de frappe.
	 *
	 * @param int $echecs Nombre d'échecs constatés dans la fenêtre.
	 */
	private static function temporiser( int $echecs ): void {
		if ( $echecs < 2 ) {
			return;
		}

		/**
		 * Temporisation appliquée après un échec, en secondes.
		 *
		 * @param int $secondes Valeur par défaut.
		 * @param int $echecs   Nombre d'échecs dans la fenêtre.
		 */
		$secondes = (int) apply_filters( 'massifs_auth_ecluse_temporisation', min( $echecs, 5 ), $echecs );

		if ( $secondes <= 0 ) {
			return;
		}

		// Plafond dur : un filtre maladroit ne doit pas immobiliser un processus PHP
		// pendant des minutes, ce qui serait un déni de service offert à l'attaquant.
		sleep( min( $secondes, 10 ) );
	}

	/**
	 * Message de refus, portant le délai d'attente.
	 *
	 * @param int $attente Secondes restantes.
	 */
	private static function message( int $attente ): string {
		$minutes = (int) ceil( $attente / 60 );

		if ( $minutes <= 1 ) {
			return 'Trop de tentatives de connexion. Réessayez dans une minute.';
		}

		return sprintf(
			'Trop de tentatives de connexion. Réessayez dans %d minutes.',
			$minutes
		);
	}
}
