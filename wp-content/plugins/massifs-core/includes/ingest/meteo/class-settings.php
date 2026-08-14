<?php
/**
 * Réglages du connecteur météo.
 *
 * Chaîne de résolution invariable, pour chaque valeur, dans cet ordre :
 * constante > option > défaut > filtre (dernier mot).
 *
 * Toute valeur lue depuis une option est ré-assainie avant usage : une option
 * est modifiable depuis l'administration, ce n'est donc pas une source de
 * confiance.
 *
 * COUPE-CIRCUIT PLUS STRICT QUE CELUI DU CONNECTEUR PRÉFECTURE, À DESSEIN
 *
 * `MASSIFS_METEO_JSON_URL_TEMPLATE` n'a AUCUNE valeur par défaut, et son
 * absence désarme le module dans TOUS les environnements, production comprise.
 * Le point d'entrée réel de l'API « Météo des forêts » n'est pas connu et ne se
 * déduit pas ; une URL par défaut inventée serait le pire des deux mondes — un
 * appel sortant vers une adresse fausse, en production. Sans la constante, le
 * module ne peut STRUCTURELLEMENT pas émettre un octet.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Meteo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Résolution de tous les paramètres du connecteur météo.
 */
final class Settings {

	/**
	 * Option de réglages. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_meteo_reglages';

	/**
	 * Version de structure de l'option.
	 */
	public const SCHEMA = 1;

	/**
	 * Modes de fonctionnement reconnus.
	 */
	public const MODES = array( 'automatique', 'manuel' );

	/**
	 * Granularités géographiques reconnues. Liste FERMÉE du contrat.
	 */
	public const GRANULARITES = array( 'departement', 'zone_meteo', 'massif' );

	/**
	 * Mention de source du §9 du brief, VERBATIM.
	 *
	 * Tiret cadratin U+2014. Elle se rend entière ; elle ne se coupe, ne se
	 * traduit et ne se reformule pas.
	 */
	private const ATTRIBUTION_TEXTE = 'Données Météo-France — Licence Etalab 2.0';

	/**
	 * Distinction danger météo / accès au massif, VERBATIM `MASTER.md` §8.6.
	 *
	 * Émise dans TOUS les états, y compris quand aucune donnée n'est affichée :
	 * le propos est vrai indépendamment de la donnée, et c'est précisément quand
	 * l'indicateur manque qu'un lecteur risque de le rabattre sur le statut
	 * d'accès.
	 */
	private const DISTINCTION = 'Le danger météo décrit les conditions du jour ; il ne détermine pas l\'accès au massif, qui relève de l\'arrêté préfectoral.';

	/**
	 * Cache de requête. Évite de relire et ré-assainir l'option à chaque appel.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Valeurs par défaut.
	 *
	 * Aucune énumération de niveaux ici : `niveaux_source_autorises` est VIDE,
	 * et vide signifie « aucune liste blanche connue, la couche sémantique s'en
	 * tient à un contrôle de nature ». Y écrire une énumération serait inventer
	 * la forme d'une source inconnue.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema'                   => self::SCHEMA,
			'mode'                     => 'automatique',
			'niveaux_source_autorises' => array(),
			// Hypothèse intérimaire assumée, tracée en Q3 du contrat : le
			// découpage géographique réel n'a pas été arbitré. `granularite`
			// appartient à une liste fermée, il n'y a qu'une valeur à changer.
			'zone_cle'                 => '13',
			'zone_libelle'             => 'Bouches-du-Rhône',
			'zone_granularite'         => 'departement',
		);
	}

	/**
	 * Réglages complets, amorcés paresseusement au premier usage.
	 *
	 * Jamais à l'inclusion, jamais à l'activation.
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
		 * Filtre l'ensemble des réglages du connecteur météo.
		 *
		 * @param array<string,mixed> $reglages Réglages assainis.
		 */
		$reglages = apply_filters( 'massifs_meteo_reglages', $reglages );

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

		$granularite = isset( $brut['zone_granularite'] ) ? sanitize_key( (string) $brut['zone_granularite'] ) : '';
		if ( ! in_array( $granularite, self::GRANULARITES, true ) ) {
			$granularite = $defauts['zone_granularite'];
		}

		$cle = isset( $brut['zone_cle'] ) && is_scalar( $brut['zone_cle'] ) ? trim( (string) $brut['zone_cle'] ) : '';
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $cle ) ) {
			$cle = $defauts['zone_cle'];
		}

		$libelle = isset( $brut['zone_libelle'] ) && is_scalar( $brut['zone_libelle'] )
			? sanitize_text_field( (string) $brut['zone_libelle'] )
			: '';
		if ( '' === $libelle ) {
			$libelle = $defauts['zone_libelle'];
		}

		return array(
			'schema'                   => self::SCHEMA,
			'mode'                     => $mode,
			'niveaux_source_autorises' => self::entiers( $brut['niveaux_source_autorises'] ?? array() ),
			'zone_cle'                 => $cle,
			'zone_libelle'             => $libelle,
			'zone_granularite'         => $granularite,
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
				$propres[] = (int) $valeur;
			}
		}

		$propres = array_values( array_unique( $propres ) );
		sort( $propres );

		return $propres;
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
	 * Vrai si la constante de désactivation est posée, OU si le modèle d'URL
	 * n'est pas défini — dans TOUS les environnements. Voir l'en-tête du
	 * fichier pour le motif.
	 *
	 * Ne lit aucune option : appelable dès le chargement de l'extension.
	 */
	public static function is_disabled(): bool {
		if ( defined( 'MASSIFS_METEO_DISABLE' ) && MASSIFS_METEO_DISABLE ) {
			return true;
		}

		return ! defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' );
	}

	/**
	 * URL du JSON pour une date de validité.
	 *
	 * `{date}` est le SEUL jeton reconnu, substitué uniquement après validation
	 * stricte du format : aucune valeur d'origine externe ne peut se retrouver
	 * dans l'URL appelée.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 * @return string Chaîne vide si la date est invalide ou le modèle absent.
	 */
	public static function url_for( string $date_ymd ): string {
		if ( ! defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' ) ) {
			return '';
		}

		if ( 1 !== preg_match( '/^\d{8}$/', $date_ymd ) ) {
			return '';
		}

		$url = str_replace( '{date}', $date_ymd, (string) MASSIFS_METEO_JSON_URL_TEMPLATE );

		/**
		 * Filtre l'URL appelée pour une date donnée.
		 *
		 * @param string $url      URL construite.
		 * @param string $date_ymd Date de validité au format `Ymd`.
		 */
		$url = (string) apply_filters( 'massifs_meteo_json_url', $url, $date_ymd );

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
		 * C'est aussi le point d'accroche prévu pour un futur en-tête
		 * d'authentification : une clé d'API se pose ici, côté serveur, et ne
		 * traverse jamais une réponse ni le DOM.
		 *
		 * @param array<string,mixed> $args Arguments `wp_remote_get`.
		 * @param string              $url  URL appelée.
		 */
		$filtres = apply_filters( 'massifs_meteo_http_args', $args, $url );

		if ( is_array( $filtres ) ) {
			$args = $filtres;
		}

		// Ré-imposé APRÈS le filtre : la vérification TLS n'est pas une option
		// de confort, et une temporisation non bornée bloquerait la requête
		// visiteur qui déclenche le cron.
		$args['sslverify'] = true;
		$args['timeout']   = self::borner( $args['timeout'] ?? null, 1, 30, 10 );

		return $args;
	}

	/**
	 * Temporisation HTTP en secondes.
	 */
	private static function timeout(): int {
		$valeur = defined( 'MASSIFS_METEO_HTTP_TIMEOUT' ) ? MASSIFS_METEO_HTTP_TIMEOUT : 10;

		return self::borner( $valeur, 1, 30, 10 );
	}

	/**
	 * Identification honnête du robot.
	 */
	private static function user_agent(): string {
		if ( defined( 'MASSIFS_METEO_USER_AGENT' ) ) {
			return (string) MASSIFS_METEO_USER_AGENT;
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
	 * Liste blanche des valeurs de niveau admises en entrée.
	 *
	 * VIDE par défaut, et le rester est le comportement correct : la forme
	 * réelle de la source est inconnue (Q1). Vide signifie « aucune énumération
	 * connue », et la couche sémantique se replie alors sur un contrôle de
	 * nature. Cette liste ne dit RIEN de l'échelle affichée : elle borne une
	 * entrée, elle ne nomme aucun cran.
	 *
	 * @return int[]
	 */
	public static function niveaux_source_autorises(): array {
		$valeurs = self::all()['niveaux_source_autorises'];

		/**
		 * Filtre la liste blanche des valeurs de niveau acceptées en entrée.
		 *
		 * @param int[] $valeurs Valeurs acceptées.
		 */
		$valeurs = apply_filters( 'massifs_meteo_niveaux_source_autorises', $valeurs );

		return self::entiers( $valeurs );
	}

	/**
	 * Zone géographique couverte.
	 *
	 * @return array{cle:string,libelle:string,granularite:string}
	 */
	public static function zone(): array {
		$reglages = self::all();

		return array(
			'cle'         => (string) $reglages['zone_cle'],
			'libelle'     => (string) $reglages['zone_libelle'],
			'granularite' => (string) $reglages['zone_granularite'],
		);
	}

	/**
	 * Fuseau de référence du dispositif.
	 */
	public static function timezone(): \DateTimeZone {
		$identifiant = defined( 'MASSIFS_METEO_TIMEZONE' ) ? (string) MASSIFS_METEO_TIMEZONE : 'Europe/Paris';

		try {
			return new \DateTimeZone( $identifiant );
		} catch ( \Exception $e ) {
			return new \DateTimeZone( 'Europe/Paris' );
		}
	}

	/**
	 * Attribution de la source.
	 *
	 * Les deux liens sont VIDES et le restent : l'URL canonique de la Licence
	 * Ouverte et le point d'entrée de la source ne sont pas vérifiés (Q4). Une
	 * URL plausible mais fausse serait une invention, pas un lien.
	 *
	 * @return array{texte:string,lien_licence:string,lien_source:string}
	 */
	public static function attribution(): array {
		return array(
			'texte'        => self::ATTRIBUTION_TEXTE,
			'lien_licence' => '',
			'lien_source'  => '',
		);
	}

	/**
	 * Phrase de distinction, verbatim.
	 */
	public static function distinction(): string {
		return self::DISTINCTION;
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
		$jours = apply_filters( 'massifs_meteo_conserver_jours', 7 );

		// Plancher à 2 : aujourd'hui et demain coexistent toujours.
		return self::borner( $jours, 2, 90, 7 );
	}

	/**
	 * Nombre d'échecs consécutifs à partir duquel une alerte est émise.
	 */
	public static function seuil_alerte_echecs(): int {
		/**
		 * Filtre le seuil d'alerte sur échecs consécutifs.
		 *
		 * @param int $seuil Nombre d'échecs consécutifs.
		 */
		$seuil = apply_filters( 'massifs_meteo_seuil_alerte_echecs', 3 );

		return self::borner( $seuil, 1, 24, 3 );
	}

	/**
	 * Destinataires des alertes.
	 *
	 * @return string[]
	 */
	public static function destinataires_alerte(): array {
		$destinataires = array( (string) get_option( 'admin_email', '' ) );

		/**
		 * Filtre les destinataires des alertes du connecteur météo.
		 *
		 * @param string[] $destinataires Adresses email.
		 */
		$destinataires = apply_filters( 'massifs_meteo_alerte_destinataires', $destinataires );

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
