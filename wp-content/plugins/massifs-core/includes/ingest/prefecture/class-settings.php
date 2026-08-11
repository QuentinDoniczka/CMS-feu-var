<?php
/**
 * Réglages du connecteur préfecture.
 *
 * Chaîne de résolution invariable, pour chaque valeur, dans cet ordre :
 * constante > passerelle `function_exists` (chaînes #2 / #3) > option >
 * défaut d'investigation > filtre (dernier mot).
 *
 * Toute valeur lue depuis une option est ré-assainie avant usage : une option
 * est modifiable depuis l'administration, ce n'est donc pas une source de
 * confiance.
 *
 * @package Massifs\Ingest\Prefecture
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Prefecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Résolution de tous les paramètres du connecteur.
 */
final class Settings {

	/**
	 * Option de réglages. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_prefecture_reglages';

	/**
	 * Version de structure de l'option.
	 */
	public const SCHEMA = 1;

	/**
	 * Modes de fonctionnement reconnus.
	 *
	 * Publique parce que `Validator` doit reconnaître le mode déclaré par son
	 * appelant sans jamais lire le mode courant du connecteur.
	 */
	public const MODES = array( 'automatique', 'manuel' );

	private const URL_JSON_DEFAUT = 'https://www.risque-prevention-incendie.fr/static/13/import_data/{date}.json';
	private const URL_PDF_DEFAUT  = 'https://www.risque-prevention-incendie.fr/static/13/import_data/{date}.pdf';
	private const URL_CARTE       = 'https://www.risque-prevention-incendie.fr/13';

	private const ATTRIBUTION_TEXTE = "D'après les publications de la préfecture des Bouches-du-Rhône";

	/**
	 * Cache de requête. Évite de relire et ré-assainir l'option à chaque appel.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Valeurs d'investigation.
	 *
	 * Relevé du 2026-08-11 sur la source, NON OFFICIEL. Ces valeurs décrivent
	 * ce que la source émet, jamais ce que le dispositif signifie : aucun
	 * libellé, aucune couleur, aucune sévérité n'est décidée ici (MASTER.md
	 * §4.1 est `À CONFIRMER`, et §4.2 du brief interdit d'inventer la légende).
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema'                => self::SCHEMA,
			'mode'                  => 'automatique',
			// Relevé du 2026-08-11, non officiel : valeurs de niveau observées
			// dans le flux (0 à 3 en 2026 ; 4 documenté par le JS du site
			// officiel mais non observé). Liste volontairement stockée en
			// option et non figée en constante de classe.
			'niveaux_autorises'     => array( 0, 1, 2, 3, 4 ),
			// Relevé du 2026-08-11, non officiel : seules les valeurs 0 et 1
			// ont été observées en seconde position.
			'procedures_autorisees' => array( 0, 1 ),
			// Relevé du 2026-08-11, non officiel : 27 identifiants contigus
			// « 13 » + 1..27. Remplacé par le référentiel de la chaîne #2 dès
			// que `massifs_referentiel_codes_source()` existe.
			'massifs_attendus'      => self::codes_observes(),
			// Fenêtre de publication en heures locales (Europe/Paris).
			'fenetre_debut_heure'   => 16,
			'fenetre_fin_heure'     => 23,
		);
	}

	/**
	 * Identifiants relevés le 2026-08-11 : « 13 » concaténé à 1..27.
	 *
	 * @return string[]
	 */
	private static function codes_observes(): array {
		$codes = array();
		for ( $n = 1; $n <= 27; $n++ ) {
			$codes[] = '13' . $n;
		}

		return $codes;
	}

	/**
	 * Réglages complets, amorcés paresseusement au premier usage.
	 *
	 * Jamais à l'inclusion, jamais à l'activation : cette méthode n'est
	 * appelée que depuis un contexte d'exécution (init, cron, portail).
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stocke = get_option( self::OPTION, null );

		if ( ! is_array( $stocke ) ) {
			$stocke = self::defaults();
			update_option( self::OPTION, $stocke, false );
		}

		$reglages = self::sanitize( array_merge( self::defaults(), $stocke ) );

		/**
		 * Filtre l'ensemble des réglages du connecteur préfecture.
		 *
		 * @param array<string,mixed> $reglages Réglages assainis.
		 */
		$reglages = apply_filters( 'massifs_prefecture_reglages', $reglages );

		// Ré-assainissement après filtre : le filtre a le dernier mot sur les
		// valeurs, jamais sur les types.
		self::$cache = self::sanitize( is_array( $reglages ) ? $reglages : self::defaults() );

		return self::$cache;
	}

	/**
	 * Assainit une structure de réglages, quelle que soit sa provenance.
	 *
	 * @param array<string,mixed> $brut Réglages bruts.
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $brut ): array {
		$defauts = self::defaults();

		$mode = isset( $brut['mode'] ) ? sanitize_key( (string) $brut['mode'] ) : '';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = $defauts['mode'];
		}

		$niveaux = self::entiers( $brut['niveaux_autorises'] ?? array() );
		if ( array() === $niveaux ) {
			$niveaux = $defauts['niveaux_autorises'];
		}

		$procedures = self::entiers( $brut['procedures_autorisees'] ?? array() );
		if ( array() === $procedures ) {
			$procedures = $defauts['procedures_autorisees'];
		}

		return array(
			'schema'                => self::SCHEMA,
			'mode'                  => $mode,
			'niveaux_autorises'     => $niveaux,
			'procedures_autorisees' => $procedures,
			'massifs_attendus'      => self::codes( $brut['massifs_attendus'] ?? array() ),
			'fenetre_debut_heure'   => self::heure( $brut['fenetre_debut_heure'] ?? null, $defauts['fenetre_debut_heure'] ),
			'fenetre_fin_heure'     => self::heure( $brut['fenetre_fin_heure'] ?? null, $defauts['fenetre_fin_heure'] ),
		);
	}

	/**
	 * Normalise une liste d'entiers.
	 *
	 * @param mixed $valeurs Valeurs brutes.
	 * @return int[]
	 */
	private static function entiers( $valeurs ): array {
		if ( ! is_array( $valeurs ) ) {
			return array();
		}

		$propres = array();
		foreach ( $valeurs as $valeur ) {
			if ( is_int( $valeur ) || ( is_string( $valeur ) && 1 === preg_match( '/^\d+$/', $valeur ) ) ) {
				$propres[] = absint( $valeur );
			}
		}

		$propres = array_values( array_unique( $propres ) );
		sort( $propres );

		return $propres;
	}

	/**
	 * Normalise une liste de codes source (3 ou 4 chiffres).
	 *
	 * @param mixed $valeurs Valeurs brutes.
	 * @return string[]
	 */
	private static function codes( $valeurs ): array {
		if ( ! is_array( $valeurs ) ) {
			return array();
		}

		$propres = array();
		foreach ( $valeurs as $valeur ) {
			if ( ! is_scalar( $valeur ) ) {
				continue;
			}
			$code = trim( (string) $valeur );
			if ( 1 === preg_match( '/^\d{3,4}$/', $code ) ) {
				$propres[] = $code;
			}
		}

		$propres = array_values( array_unique( $propres ) );
		sort( $propres, SORT_STRING );

		return $propres;
	}

	/**
	 * Normalise une heure locale.
	 *
	 * @param mixed $valeur Valeur brute.
	 * @param int   $defaut Repli.
	 */
	private static function heure( $valeur, int $defaut ): int {
		if ( ! is_int( $valeur ) && ! ( is_string( $valeur ) && 1 === preg_match( '/^\d{1,2}$/', $valeur ) ) ) {
			return $defaut;
		}

		$heure = absint( $valeur );

		return $heure > 23 ? $defaut : $heure;
	}

	/**
	 * Mode de fonctionnement : `automatique` ou `manuel`.
	 */
	public static function mode(): string {
		return (string) self::all()['mode'];
	}

	/**
	 * Coupe-circuit.
	 *
	 * Vrai si la constante de désactivation est posée, OU si l'environnement
	 * est local/développement sans que le modèle d'URL ait été redéfini : une
	 * stack Docker non configurée ne doit jamais émettre d'appel sortant réel
	 * (exigence de docker/README.md).
	 *
	 * Ne lit aucune option : appelable dès le chargement de l'extension.
	 */
	public static function is_disabled(): bool {
		if ( defined( 'MASSIFS_PREFECTURE_DISABLE' ) && MASSIFS_PREFECTURE_DISABLE ) {
			return true;
		}

		$environnement = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		if ( in_array( $environnement, array( 'local', 'development' ), true )
			&& ! defined( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * URL du JSON pour une date de validité.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return string Chaîne vide si la date est invalide.
	 */
	public static function url_for( string $date_ymd ): string {
		$modele = defined( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE' )
			? (string) MASSIFS_PREFECTURE_JSON_URL_TEMPLATE
			: self::URL_JSON_DEFAUT;

		return self::construire_url( $modele, $date_ymd, 'massifs_prefecture_json_url' );
	}

	/**
	 * URL du bulletin PDF pour une date de validité.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return string Chaîne vide si la date est invalide.
	 */
	public static function pdf_url_for( string $date_ymd ): string {
		$modele = defined( 'MASSIFS_PREFECTURE_PDF_URL_TEMPLATE' )
			? (string) MASSIFS_PREFECTURE_PDF_URL_TEMPLATE
			: self::URL_PDF_DEFAUT;

		return self::construire_url( $modele, $date_ymd, '' );
	}

	/**
	 * Substitue le jeton `{date}` puis assainit l'URL.
	 *
	 * `{date}` est le seul jeton reconnu, et il n'est substitué qu'après
	 * validation stricte du format : aucune valeur d'origine externe ne peut
	 * se retrouver dans l'URL appelée.
	 *
	 * @param string $modele   Modèle d'URL.
	 * @param string $date_ymd Date au format `Ymd`.
	 * @param string $filtre   Nom du filtre à appliquer, ou chaîne vide.
	 */
	private static function construire_url( string $modele, string $date_ymd, string $filtre ): string {
		if ( 1 !== preg_match( '/^\d{8}$/', $date_ymd ) ) {
			return '';
		}

		$url = str_replace( '{date}', $date_ymd, $modele );

		if ( '' !== $filtre ) {
			/**
			 * Filtre l'URL JSON appelée pour une date donnée.
			 *
			 * @param string $url      URL construite.
			 * @param string $date_ymd Date de validité au format `Ymd`.
			 */
			$url = (string) apply_filters( $filtre, $url, $date_ymd );
		}

		return esc_url_raw( $url );
	}

	/**
	 * Arguments de la requête HTTP sortante.
	 *
	 * @param string $url URL appelée.
	 * @return array<string,mixed>
	 */
	public static function http_args( string $url ): array {
		$args = array(
			'timeout'     => self::timeout(),
			'redirection' => 2,
			'sslverify'   => true,
			'user-agent'  => self::user_agent(),
			'headers'     => array( 'Accept' => 'application/json' ),
		);

		/**
		 * Filtre les arguments de la requête sortante.
		 *
		 * @param array<string,mixed> $args Arguments `wp_remote_get`.
		 * @param string              $url  URL appelée.
		 */
		$filtres = apply_filters( 'massifs_prefecture_http_args', $args, $url );

		if ( is_array( $filtres ) ) {
			$args = $filtres;
		}

		// Ré-imposé après filtre : la vérification TLS n'est pas une option de
		// confort, et une borne de temporisation absente bloquerait la requête
		// visiteur qui déclenche le cron.
		$args['sslverify'] = true;
		$args['timeout']   = self::borner( $args['timeout'] ?? null, 1, 30, 10 );

		return $args;
	}

	/**
	 * Temporisation HTTP en secondes.
	 */
	private static function timeout(): int {
		$valeur = defined( 'MASSIFS_PREFECTURE_HTTP_TIMEOUT' ) ? MASSIFS_PREFECTURE_HTTP_TIMEOUT : 10;

		return self::borner( $valeur, 1, 30, 10 );
	}

	/**
	 * Identification honnête du robot.
	 *
	 * Le domaine source ne publie ni `robots.txt` ni conditions d'utilisation :
	 * on s'identifie explicitement et on laisse un contact.
	 */
	private static function user_agent(): string {
		if ( defined( 'MASSIFS_PREFECTURE_USER_AGENT' ) ) {
			return (string) MASSIFS_PREFECTURE_USER_AGENT;
		}

		$contact = sanitize_email( (string) get_option( 'admin_email', '' ) );

		return sprintf( 'MASSIFS/1.0 (+%s; %s)', home_url( '/' ), $contact );
	}

	/**
	 * Borne une valeur numérique.
	 *
	 * @param mixed $valeur Valeur brute.
	 * @param int   $min    Borne basse.
	 * @param int   $max    Borne haute.
	 * @param int   $defaut Repli si la valeur n'est pas numérique.
	 */
	private static function borner( $valeur, int $min, int $max, int $defaut ): int {
		if ( ! is_int( $valeur ) && ! ( is_string( $valeur ) && 1 === preg_match( '/^\d+$/', $valeur ) ) ) {
			return $defaut;
		}

		return max( $min, min( $max, absint( $valeur ) ) );
	}

	/**
	 * Niveaux source acceptés.
	 *
	 * Liste de valeurs d'entrée, pas une échelle de sévérité : ce connecteur ne
	 * traduit jamais un entier en libellé, couleur ou gravité.
	 *
	 * @return int[]
	 */
	public static function niveaux_autorises(): array {
		$valeurs = null;

		if ( function_exists( 'massifs_niveaux_source_autorises' ) ) {
			$valeurs = massifs_niveaux_source_autorises();
		}

		if ( ! is_array( $valeurs ) || array() === $valeurs ) {
			$valeurs = self::all()['niveaux_autorises'];
		}

		/**
		 * Filtre la liste blanche des niveaux source acceptés.
		 *
		 * @param int[] $valeurs Niveaux acceptés.
		 */
		$valeurs = apply_filters( 'massifs_prefecture_niveaux_autorises', $valeurs );

		return self::entiers( $valeurs );
	}

	/**
	 * Valeurs de procédure acceptées.
	 *
	 * @return int[]
	 */
	public static function procedures_autorisees(): array {
		$valeurs = null;

		if ( function_exists( 'massifs_procedures_source_autorisees' ) ) {
			$valeurs = massifs_procedures_source_autorisees();
		}

		if ( ! is_array( $valeurs ) || array() === $valeurs ) {
			$valeurs = self::all()['procedures_autorisees'];
		}

		/**
		 * Filtre la liste blanche des valeurs de procédure acceptées.
		 *
		 * @param int[] $valeurs Procédures acceptées.
		 */
		$valeurs = apply_filters( 'massifs_prefecture_procedures_autorisees', $valeurs );

		return self::entiers( $valeurs );
	}

	/**
	 * Ensemble de référence des identifiants de massif attendus.
	 *
	 * @return string[]
	 */
	public static function massifs_attendus(): array {
		$codes = null;

		if ( function_exists( 'massifs_referentiel_codes_source' ) ) {
			$codes = massifs_referentiel_codes_source();
		}

		if ( ! is_array( $codes ) || array() === $codes ) {
			$codes = self::all()['massifs_attendus'];
		}

		/**
		 * Filtre l'ensemble de référence des identifiants attendus.
		 *
		 * @param string[] $codes Identifiants source attendus.
		 */
		$codes = apply_filters( 'massifs_prefecture_massifs_attendus', $codes );

		return self::codes( $codes );
	}

	/**
	 * Fenêtre de publication, en heures locales.
	 *
	 * @return array{debut:int,fin:int}
	 */
	public static function fenetre(): array {
		$reglages = self::all();

		$fenetre = array(
			'debut' => (int) $reglages['fenetre_debut_heure'],
			'fin'   => (int) $reglages['fenetre_fin_heure'],
		);

		/**
		 * Filtre la fenêtre de publication.
		 *
		 * @param array{debut:int,fin:int} $fenetre Heures locales.
		 */
		$filtree = apply_filters( 'massifs_prefecture_fenetre_publication', $fenetre );

		if ( is_array( $filtree ) ) {
			$fenetre = array(
				'debut' => self::heure( $filtree['debut'] ?? null, $fenetre['debut'] ),
				'fin'   => self::heure( $filtree['fin'] ?? null, $fenetre['fin'] ),
			);
		}

		if ( $fenetre['fin'] < $fenetre['debut'] ) {
			$fenetre['fin'] = $fenetre['debut'];
		}

		return $fenetre;
	}

	/**
	 * Fuseau de référence du dispositif.
	 */
	public static function timezone(): \DateTimeZone {
		$identifiant = defined( 'MASSIFS_PREFECTURE_TIMEZONE' )
			? (string) MASSIFS_PREFECTURE_TIMEZONE
			: 'Europe/Paris';

		try {
			return new \DateTimeZone( $identifiant );
		} catch ( \Exception $e ) {
			return new \DateTimeZone( 'Europe/Paris' );
		}
	}

	/**
	 * Attribution de la source.
	 *
	 * `url_bulletin` est un MODÈLE contenant le jeton `{date}`, pas un lien
	 * prêt à poser : le bulletin n'existe que pour une date effectivement
	 * publiée. Le consommateur substitue lui-même le jeton par un `Ymd`.
	 *
	 * @return array{texte:string,url_carte:string,url_bulletin:string}
	 */
	public static function attribution(): array {
		$modele_pdf = defined( 'MASSIFS_PREFECTURE_PDF_URL_TEMPLATE' )
			? (string) MASSIFS_PREFECTURE_PDF_URL_TEMPLATE
			: self::URL_PDF_DEFAUT;

		return array(
			'texte'        => self::ATTRIBUTION_TEXTE,
			'url_carte'    => self::URL_CARTE,
			'url_bulletin' => $modele_pdf,
		);
	}

	/**
	 * Nombre de jours d'instantanés conservés.
	 */
	public static function conserver_jours(): int {
		/**
		 * Filtre la profondeur de conservation des instantanés, en jours.
		 *
		 * @param int $jours Nombre de jours.
		 */
		$jours = apply_filters( 'massifs_prefecture_conserver_jours', 7 );

		// Plancher à 2 : aujourd'hui et demain coexistent toujours.
		return self::borner( $jours, 2, 90, 7 );
	}

	/**
	 * Destinataires des alertes.
	 *
	 * @return string[]
	 */
	public static function destinataires_alerte(): array {
		$destinataires = array( (string) get_option( 'admin_email', '' ) );

		/**
		 * Filtre les destinataires des alertes du connecteur.
		 *
		 * @param string[] $destinataires Adresses email.
		 */
		$destinataires = apply_filters( 'massifs_prefecture_alerte_destinataires', $destinataires );

		if ( ! is_array( $destinataires ) ) {
			return array();
		}

		$propres = array();
		foreach ( $destinataires as $adresse ) {
			if ( ! is_scalar( $adresse ) ) {
				continue;
			}
			$adresse = sanitize_email( (string) $adresse );
			if ( '' !== $adresse && is_email( $adresse ) ) {
				$propres[] = $adresse;
			}
		}

		return array_values( array_unique( $propres ) );
	}
}
