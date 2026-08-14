<?php
/**
 * Réglages du module « zones parcourues par le feu ».
 *
 * Chaîne de résolution invariable, pour chaque valeur, dans cet ordre :
 * constante > passerelle `function_exists` > option > défaut > filtre (dernier
 * mot). Patron littéral de `includes/ingest/prefecture/class-settings.php`.
 *
 * Toute valeur lue depuis une option est ré-assainie avant usage : une option
 * est modifiable depuis l'administration, ce n'est donc pas une source de
 * confiance.
 *
 * AUCUNE URL TIERCE N'EST ÉCRITE ICI. Le défaut de `url()` est la chaîne vide,
 * qui produit honnêtement l'état `couche_effis_indisponible`. Basculer vers une
 * source réelle est un changement d'URL, et rien d'autre.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Résolution de tous les paramètres du module.
 */
final class Settings {

	/**
	 * Option de réglages. Toujours `autoload = false`.
	 */
	public const OPTION = 'massifs_effis_reglages';

	/**
	 * Version de structure de l'option.
	 */
	public const SCHEMA = 1;

	/**
	 * Portées de connecteur reconnues. Liste FERMÉE.
	 */
	public const CONNECTEURS = array( 'simule', 'reel' );

	/**
	 * Format d'échange des instants publiés et stockés.
	 *
	 * Identique au format d'échange du domaine « fraîcheur »
	 * (`Horloge::FORMAT_ISO_UTC`), recopié plutôt qu'importé : ce module ne
	 * nomme aucune classe d'un autre module, et une constante de vingt
	 * caractères coûte moins qu'un couplage.
	 */
	public const FORMAT_ISO_UTC = 'Y-m-d\TH:i:s\Z';

	/**
	 * Péremption par défaut, en secondes.
	 *
	 * MOTIF, ET IL NE SE SUPPRIME PAS : la source est une fenêtre glissante de
	 * sept jours publiée de l'ordre de deux fois par jour. Servir une fenêtre
	 * plus vieille que 24 h, c'est afficher une couche où une zone survenue
	 * depuis est ABSENTE, et un visiteur y lit « aucune zone parcourue par le
	 * feu détectée ». C'est le §4.2 du brief atteint par la route inverse — non
	 * pas une mesure périmée présentée comme courante, mais une ABSENCE périmée
	 * présentée comme une mesure.
	 */
	private const PEREMPTION_DEFAUT = 86400;

	/**
	 * Bornes dures de la péremption, en secondes.
	 */
	private const PEREMPTION_MIN = 3600;
	private const PEREMPTION_MAX = 604800;

	/**
	 * Fenêtre glissante de la couche source, en jours (§4.4 du brief).
	 */
	private const FENETRE_JOURS = 7;

	/**
	 * Seuil de détection annoncé par le §4.4 du brief, en hectares.
	 */
	private const SURFACE_MINIMALE_HA = 30;

	/**
	 * Fréquence de publication annoncée par le §4.4 du brief, par jour.
	 */
	private const FREQUENCE_PAR_JOUR = 2;

	/**
	 * Noms d'attributs lus dans une entité source.
	 *
	 * CE NE SONT PAS LES NOMS D'EFFIS. Le schéma réel de la couche source n'a
	 * jamais été relevé (§11 du contrat #11) ; ces valeurs sont celles de NOTRE
	 * connecteur simulé, et elles sont configurables précisément pour que le
	 * jour où le schéma réel sera connu, la bascule soit un réglage et non une
	 * réécriture. Un attribut absent vaut `''` ou `0.0`, jamais une valeur
	 * fabriquée.
	 *
	 * @return array<string,string>
	 */
	public static function attributs_defaut(): array {
		return array(
			'id'                   => 'id',
			'surface_ha'           => 'surface_ha',
			'premiere_observation' => 'premiere_observation',
			'derniere_observation' => 'derniere_observation',
		);
	}

	/**
	 * Valeurs par défaut de l'option de réglages.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema'     => self::SCHEMA,
			// Défaut vide, et c'est une décision : aucune URL tierce n'est
			// écrite en dur, même comme valeur de repli.
			'url'        => '',
			'connecteur' => 'simule',
			'attributs'  => self::attributs_defaut(),
		);
	}

	/**
	 * Réglages complets, assainis.
	 *
	 * Volontairement NI MÉMOÏSÉS NI PERSISTÉS : la lecture ne doit jamais
	 * écrire, et un réglage modifié en cours de requête doit être vu par la
	 * lecture suivante.
	 *
	 * @return array<string,mixed>
	 */
	private static function all(): array {
		$stocke = get_option( self::OPTION, null );

		return self::sanitize( array_merge( self::defaults(), is_array( $stocke ) ? $stocke : array() ) );
	}

	/**
	 * Assainit une structure de réglages, quelle que soit sa provenance.
	 *
	 * @param array<string,mixed> $brut Réglages bruts.
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $brut ): array {
		$connecteur = isset( $brut['connecteur'] ) ? sanitize_key( (string) $brut['connecteur'] ) : '';

		return array(
			'schema'     => self::SCHEMA,
			'url'        => isset( $brut['url'] ) && is_string( $brut['url'] ) ? esc_url_raw( trim( $brut['url'] ) ) : '',
			'connecteur' => in_array( $connecteur, self::CONNECTEURS, true ) ? $connecteur : 'simule',
			'attributs'  => self::attributs( $brut['attributs'] ?? array() ),
		);
	}

	/**
	 * Normalise une table de correspondance d'attributs.
	 *
	 * @param mixed $valeurs Table brute.
	 * @return array<string,string>
	 */
	private static function attributs( $valeurs ): array {
		$defauts = self::attributs_defaut();

		if ( ! is_array( $valeurs ) ) {
			return $defauts;
		}

		$propres = array();

		foreach ( $defauts as $champ => $defaut ) {
			$nom = isset( $valeurs[ $champ ] ) && is_string( $valeurs[ $champ ] ) ? trim( $valeurs[ $champ ] ) : '';

			// Un nom d'attribut est une clé de propriété GeoJSON : jeu de
			// caractères borné, jamais une expression arbitraire.
			$propres[ $champ ] = 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $nom ) ? $nom : $defaut;
		}

		return $propres;
	}

	/**
	 * URL de la source.
	 *
	 * Constante > passerelle > option > défaut > filtre. Le défaut est la
	 * chaîne vide.
	 */
	public static function url(): string {
		$url = '';

		if ( defined( 'MASSIFS_EFFIS_URL' ) ) {
			$url = (string) MASSIFS_EFFIS_URL;
		}

		if ( '' === $url && function_exists( 'massifs_effis_url_source' ) ) {
			$url = (string) massifs_effis_url_source();
		}

		if ( '' === $url ) {
			$url = (string) self::all()['url'];
		}

		/**
		 * Filtre l'URL de la source des zones parcourues par le feu.
		 *
		 * @param string $url URL résolue, éventuellement vide.
		 */
		$url = (string) apply_filters( 'massifs_effis_url', $url );

		return esc_url_raw( trim( $url ) );
	}

	/**
	 * Coupe-circuit.
	 *
	 * Vrai si la constante de désactivation est posée, OU si l'environnement
	 * est local/développement sans que l'URL ait été redéfinie par constante :
	 * une stack non configurée n'émet ZÉRO octet sortant.
	 *
	 * NE LIT AUCUNE OPTION ET N'EST JAMAIS MÉMOÏSÉ : il doit rester
	 * ré-évaluable en cours de requête, sans quoi un scénario ne pourrait plus
	 * l'armer.
	 */
	public static function is_disabled(): bool {
		if ( defined( 'MASSIFS_EFFIS_DISABLE' ) && MASSIFS_EFFIS_DISABLE ) {
			return true;
		}

		$environnement = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		if ( in_array( $environnement, array( 'local', 'development' ), true ) && ! defined( 'MASSIFS_EFFIS_URL' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Portée du connecteur : `simule` ou `reel`.
	 *
	 * Publiée dans les faits d'attribution pour rendre la portée simulée
	 * AUDITABLE EN PRODUCTION (`docs/decisions/portee-non-publiee.md` §4).
	 */
	public static function connecteur(): string {
		$portee = '';

		if ( defined( 'MASSIFS_EFFIS_CONNECTEUR' ) ) {
			$portee = sanitize_key( (string) MASSIFS_EFFIS_CONNECTEUR );
		}

		if ( ! in_array( $portee, self::CONNECTEURS, true ) ) {
			$portee = (string) self::all()['connecteur'];
		}

		/**
		 * Filtre la portée déclarée du connecteur.
		 *
		 * @param string $portee Portée résolue, parmi self::CONNECTEURS.
		 */
		$portee = sanitize_key( (string) apply_filters( 'massifs_effis_connecteur', $portee ) );

		return in_array( $portee, self::CONNECTEURS, true ) ? $portee : 'simule';
	}

	/**
	 * Table de correspondance des attributs source.
	 *
	 * @return array<string,string>
	 */
	public static function correspondance_attributs(): array {
		/**
		 * Filtre la table de correspondance des attributs source.
		 *
		 * @param array<string,string> $attributs Table résolue.
		 */
		$attributs = apply_filters( 'massifs_effis_attributs', self::all()['attributs'] );

		return self::attributs( $attributs );
	}

	/**
	 * Péremption T, en secondes.
	 *
	 * Appliquée À LA LECTURE, jamais par effacement du stockage.
	 */
	public static function peremption_secondes(): int {
		$valeur = defined( 'MASSIFS_EFFIS_PEREMPTION_SECONDES' )
			? MASSIFS_EFFIS_PEREMPTION_SECONDES
			: self::PEREMPTION_DEFAUT;

		/**
		 * Filtre la péremption de la couche, en secondes.
		 *
		 * @param int $secondes Péremption résolue.
		 */
		$valeur = apply_filters( 'massifs_effis_peremption_secondes', self::borner( $valeur, self::PEREMPTION_MIN, self::PEREMPTION_MAX, self::PEREMPTION_DEFAUT ) );

		// Ré-bornée après filtre : un filtre a le dernier mot sur la valeur,
		// jamais sur les bornes d'une règle de sécurité.
		return self::borner( $valeur, self::PEREMPTION_MIN, self::PEREMPTION_MAX, self::PEREMPTION_DEFAUT );
	}

	/**
	 * Fenêtre glissante de la couche source, en jours.
	 */
	public static function fenetre_jours(): int {
		return self::FENETRE_JOURS;
	}

	/**
	 * Seuil de détection annoncé par la source, en hectares.
	 */
	public static function surface_minimale_ha(): int {
		return self::SURFACE_MINIMALE_HA;
	}

	/**
	 * Fréquence de publication annoncée par la source, par jour.
	 */
	public static function frequence_par_jour(): int {
		return self::FREQUENCE_PAR_JOUR;
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
			'headers'     => array( 'Accept' => 'application/geo+json, application/json' ),
		);

		/**
		 * Filtre les arguments de la requête sortante.
		 *
		 * @param array<string,mixed> $args Arguments de la requête sortante.
		 * @param string              $url  URL appelée.
		 */
		$filtres = apply_filters( 'massifs_effis_http_args', $args, $url );

		if ( is_array( $filtres ) ) {
			$args = $filtres;
		}

		// Ré-imposé APRÈS filtre : un filtre ne peut ni désactiver la
		// vérification TLS, ni supprimer la borne de temporisation qui protège
		// la requête du visiteur déclenchant le cron.
		$args['sslverify'] = true;
		$args['timeout']   = self::borner( $args['timeout'] ?? null, 1, 30, 10 );

		return $args;
	}

	/**
	 * Temporisation HTTP, en secondes.
	 */
	private static function timeout(): int {
		$valeur = defined( 'MASSIFS_EFFIS_HTTP_TIMEOUT' ) ? MASSIFS_EFFIS_HTTP_TIMEOUT : 10;

		return self::borner( $valeur, 1, 30, 10 );
	}

	/**
	 * Identification honnête du robot.
	 */
	private static function user_agent(): string {
		if ( defined( 'MASSIFS_EFFIS_USER_AGENT' ) ) {
			return (string) MASSIFS_EFFIS_USER_AGENT;
		}

		$contact = sanitize_email( (string) get_option( 'admin_email', '' ) );

		return sprintf( 'MASSIFS/1.0 (+%s; %s)', home_url( '/' ), $contact );
	}

	/**
	 * Destinataires des alertes.
	 *
	 * @return string[]
	 */
	public static function destinataires_alerte(): array {
		$destinataires = array( (string) get_option( 'admin_email', '' ) );

		/**
		 * Filtre les destinataires des alertes du module.
		 *
		 * @param string[] $destinataires Adresses électroniques.
		 */
		$destinataires = apply_filters( 'massifs_effis_alerte_destinataires', $destinataires );

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
}
