<?php
/**
 * Validation d'un corps de réponse de la source météo.
 *
 * Cinq couches successives, dans un ordre non négociable : transport, forme,
 * référentiel, sémantique, temporel. Chaque couche rejette pour son propre
 * motif, et le motif voyage dans les données de l'erreur (`couche`, `detail`).
 *
 * LA COUCHE SÉMANTIQUE NE CONSULTE JAMAIS `Vocabulaire`, ET CE N'EST PAS UNE
 * ABERRATION. Une charge dont le niveau n'a aucun libellé connu reste VALIDE et
 * mise en cache ; c'est la couche de LECTURE qui refuse de la servir. C'est ce
 * qui permet d'exercer réellement, dès aujourd'hui, le cache, la fraîcheur, les
 * alertes et la reprise — au lieu d'un module inerte qui ne prouverait rien.
 *
 * LE FORMAT VALIDÉ ICI EST LE NÔTRE, DÉCLARÉ COMME TEL ET VERSIONNÉ. Il n'imite
 * pas le format réel de l'API, qui est inconnu. Le jour où ce format sera connu,
 * SEULE la couche `forme` change : transport, référentiel, sémantique, temporel,
 * cache, fraîcheur, planification et alertes ne bougent pas. C'est la définition
 * opérationnelle de « un changement de connecteur, pas une réécriture ».
 *
 * Aucun message d'erreur ne porte de valeur de niveau : les messages voyagent
 * jusque dans les alertes courriel, et une alerte qui citerait un niveau
 * apprendrait au gestionnaire un chiffre que le site refuse d'afficher. La
 * valeur brute reste dans `detail`, structuré et jamais mis en forme.
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
 * Validation en cinq couches d'une charge météo.
 */
final class Validator {

	/**
	 * Version de structure de l'instantané produit, et du format attendu.
	 */
	public const SCHEMA = 1;

	/**
	 * Bornes de taille du corps, en octets.
	 */
	private const OCTETS_MIN = 32;
	private const OCTETS_MAX = 65536;

	/**
	 * Ancienneté maximale tolérée d'une publication déclarée, en secondes.
	 */
	private const PEREMPTION_SECONDES = 48 * HOUR_IN_SECONDS;

	/*
	 * CE QUI N'EST PAS UNE ABERRATION — à ne jamais réintroduire comme rejet.
	 *
	 * 1. Un niveau au MAXIMUM de l'échelle. C'est le jour où l'information
	 *    compte le plus ; le rejeter afficherait une absence précisément alors.
	 * 2. Une valeur IDENTIQUE à celle de la veille. C'est le cas nominal d'un
	 *    épisode stable, pas un doublon.
	 * 3. Un SAUT D'AMPLITUDE quelconque entre deux journées.
	 *
	 * Le hachage ne provoque JAMAIS de rejet : il évite une réécriture pour la
	 * MÊME date, et il journalise. Le seul signal de non-publication est le 404.
	 */

	/**
	 * Valide un corps brut et produit l'instantané normalisé.
	 *
	 * @param string              $body     Corps de la réponse, verbatim.
	 * @param array<string,mixed> $headers  En-têtes de réponse, clés libres.
	 * @param \DateTimeImmutable  $target   Date de VALIDITÉ demandée.
	 * @param array<string,mixed> $contexte Contexte d'appel : `source_url`, `mode`.
	 * @return array<string,mixed>|\WP_Error Instantané normalisé, ou rejet motivé.
	 */
	public static function validate( string $body, array $headers, \DateTimeImmutable $target, array $contexte = array() ) {
		$transport = self::couche_transport( $body, $headers );
		if ( is_wp_error( $transport ) ) {
			return $transport;
		}

		$forme = self::couche_forme( $body );
		if ( is_wp_error( $forme ) ) {
			return $forme;
		}

		$referentiel = self::couche_referentiel( $forme );
		if ( is_wp_error( $referentiel ) ) {
			return $referentiel;
		}

		$semantique = self::couche_semantique( $forme );
		if ( is_wp_error( $semantique ) ) {
			return $semantique;
		}

		$temporel = self::couche_temporel( $forme, $headers, $target );
		if ( is_wp_error( $temporel ) ) {
			return $temporel;
		}

		$a_verifier = $transport['a_verifier'] || $forme['a_verifier'];

		$instantane = array(
			'schema'         => self::SCHEMA,
			'date_validite'  => $target->format( 'Y-m-d' ),
			'zone_cle'       => $forme['zone'],
			'niveau_source'  => $forme['niveau_source'],
			'publie_le'      => $temporel['publie_le'],
			'recupere_le'    => gmdate( DATE_ATOM ),
			'source_url'     => isset( $contexte['source_url'] ) ? esc_url_raw( (string) $contexte['source_url'] ) : '',
			'hash'           => hash( 'sha256', $body ),
			'octets'         => strlen( $body ),
			'brut'           => $body,
			'cles_inconnues' => $forme['cles_inconnues'],
			'mode'           => self::mode_contexte( $contexte ),
			'confiance'      => $a_verifier ? 'a_verifier' : 'nominale',
		);

		/**
		 * Dernier mot sur l'acceptation d'un instantané validé.
		 *
		 * @param true|\WP_Error      $verdict    Verdict courant.
		 * @param array<string,mixed> $instantane Instantané normalisé.
		 * @param string              $date_ymd   Date de validité au format `Ymd`.
		 */
		$verdict = apply_filters( 'massifs_meteo_valider_payload', true, $instantane, $target->format( 'Ymd' ) );

		if ( is_wp_error( $verdict ) ) {
			return $verdict;
		}

		if ( true !== $verdict ) {
			return self::erreur( 'refuse_par_filtre', 'semantique', 'Instantané refusé par le filtre massifs_meteo_valider_payload.' );
		}

		return $instantane;
	}

	/**
	 * Mode déclaré par l'appelant.
	 *
	 * Volontairement lu dans le contexte et jamais dans les réglages : le
	 * validateur reste indépendant du mode global du connecteur.
	 *
	 * @param array<string,mixed> $contexte Contexte d'appel.
	 */
	private static function mode_contexte( array $contexte ): string {
		$mode = isset( $contexte['mode'] ) ? sanitize_key( (string) $contexte['mode'] ) : '';

		return in_array( $mode, Settings::MODES, true ) ? $mode : 'automatique';
	}

	/**
	 * Couche 1 — transport : taille et nature du corps reçu.
	 *
	 * Le `Content-Type` n'est jamais un motif de rejet : c'est la forme du corps
	 * qui tranche. Un type déclaré non-JSON dégrade seulement la confiance.
	 *
	 * @param string              $body    Corps brut.
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @return array{a_verifier:bool}|\WP_Error
	 */
	private static function couche_transport( string $body, array $headers ) {
		$octets = strlen( $body );

		if ( $octets < self::OCTETS_MIN ) {
			return self::erreur( 'corps_trop_court', 'transport', sprintf( 'Corps de %d octets, minimum attendu %d.', $octets, self::OCTETS_MIN ), $octets );
		}

		if ( $octets > self::OCTETS_MAX ) {
			return self::erreur( 'corps_trop_long', 'transport', sprintf( 'Corps de %d octets, maximum toléré %d.', $octets, self::OCTETS_MAX ), $octets );
		}

		$premier = substr( ltrim( $body ), 0, 1 );

		if ( '<' === $premier ) {
			return self::erreur( 'html_sous_200', 'transport', 'Réponse HTML servie en HTTP 200 : page d\'erreur ou portail captif, pas un flux de données.', $premier );
		}

		if ( '{' !== $premier ) {
			return self::erreur( 'corps_non_json', 'transport', 'Le corps ne commence pas par un objet JSON.', $premier );
		}

		$type = self::entete( $headers, 'content-type' );

		return array( 'a_verifier' => ( '' !== $type && false === strpos( strtolower( $type ), 'json' ) ) );
	}

	/**
	 * Couche 2 — forme : structure stricte du document décodé.
	 *
	 * SEULE COUCHE À RÉÉCRIRE le jour où le format réel de la source sera connu.
	 *
	 * Une clé racine inattendue n'est PAS un rejet : sur un flux non contractuel,
	 * une clé nouvelle est une information, pas une faute. Elle est collectée et
	 * remontée.
	 *
	 * @param string $body Corps brut.
	 * @return array{zone:string,jour:string,niveau_source:int,publie_le:string,cles_inconnues:string[],a_verifier:bool}|\WP_Error
	 */
	private static function couche_forme( string $body ) {
		$document = json_decode( $body, true, 8 );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $document ) ) {
			return self::erreur( 'json_invalide', 'forme', 'JSON illisible : ' . json_last_error_msg() );
		}

		if ( ! isset( $document['schema'] ) || ! is_int( $document['schema'] ) ) {
			return self::erreur( 'schema_absent', 'forme', 'Clé « schema » absente ou non entière : le format du document n\'est pas déclaré.' );
		}

		if ( self::SCHEMA !== $document['schema'] ) {
			return self::erreur( 'schema_inconnu', 'forme', 'Version de format non prise en charge par ce connecteur.', $document['schema'] );
		}

		$zone = isset( $document['zone'] ) && is_string( $document['zone'] ) ? trim( $document['zone'] ) : '';

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $zone ) ) {
			return self::erreur( 'zone_invalide', 'forme', 'Clé « zone » absente ou hors format attendu.', $zone );
		}

		$jour = isset( $document['jour'] ) && is_string( $document['jour'] ) ? trim( $document['jour'] ) : '';

		if ( null === SourceCalendar::from_iso( $jour ) ) {
			return self::erreur( 'jour_invalide', 'forme', 'Clé « jour » absente ou hors format `Y-m-d`.', $jour );
		}

		if ( ! array_key_exists( 'niveau_source', $document ) ) {
			return self::erreur( 'niveau_absent', 'forme', 'Clé « niveau_source » absente : le document ne porte aucune donnée.' );
		}

		// `is_int` strict : une chaîne numérique ou un flottant est un changement
		// de type du flux, donc un rejet, jamais une conversion silencieuse.
		if ( ! is_int( $document['niveau_source'] ) ) {
			return self::erreur( 'type_invalide', 'forme', 'Clé « niveau_source » : entier attendu.', gettype( $document['niveau_source'] ) );
		}

		$publie_le = isset( $document['publie_le'] ) && is_string( $document['publie_le'] ) ? trim( $document['publie_le'] ) : '';

		$cles_inconnues = array_values(
			array_diff(
				array_map( 'strval', array_keys( $document ) ),
				array( 'schema', 'zone', 'jour', 'niveau_source', 'publie_le' )
			)
		);

		return array(
			'zone'           => $zone,
			'jour'           => $jour,
			'niveau_source'  => $document['niveau_source'],
			'publie_le'      => $publie_le,
			'cles_inconnues' => $cles_inconnues,
			'a_verifier'     => array() !== $cles_inconnues,
		);
	}

	/**
	 * Couche 3 — référentiel : la zone reçue est-elle celle que nous couvrons ?
	 *
	 * Un écart rejette la charge entière. Servir la zone d'un autre département
	 * sous notre libellé serait un mensonge silencieux, et c'est exactement la
	 * classe d'erreur qu'un connecteur générique produit sans le dire.
	 *
	 * @param array<string,mixed> $forme Sortie de la couche forme.
	 * @return true|\WP_Error
	 */
	private static function couche_referentiel( array $forme ) {
		$attendue = Settings::zone()['cle'];

		if ( '' === $attendue ) {
			return self::erreur( 'referentiel_indisponible', 'referentiel', 'Aucune zone de référence configurée : validation impossible, charge refusée.' );
		}

		if ( (string) $forme['zone'] !== $attendue ) {
			return self::erreur(
				'zone_divergente',
				'referentiel',
				sprintf( 'Zone « %s » reçue pour « %s » attendue.', (string) $forme['zone'], $attendue ),
				(string) $forme['zone']
			);
		}

		return true;
	}

	/**
	 * Couche 4 — sémantique : NATURE de la valeur de niveau.
	 *
	 * Cette couche NE CONSULTE PAS `Vocabulaire`, et ne doit jamais le faire :
	 * l'absence de libellé officiel est un fait de PRÉSENTATION, pas un défaut
	 * de la charge.
	 *
	 * Le contrôle porte donc sur la nature, jamais sur une énumération inventée :
	 * un indice de danger négatif est aberrant quelle que soit l'échelle. La
	 * liste blanche de `Settings` est vide par défaut, et vide signifie « aucune
	 * énumération connue » — le contrôle de nature suffit alors.
	 *
	 * @param array<string,mixed> $forme Sortie de la couche forme.
	 * @return true|\WP_Error
	 */
	private static function couche_semantique( array $forme ) {
		$niveau = (int) $forme['niveau_source'];

		if ( $niveau < 0 ) {
			return self::erreur( 'niveau_negatif', 'semantique', 'Valeur de niveau négative : aberrante quelle que soit l\'échelle.', $niveau );
		}

		$autorises = Settings::niveaux_source_autorises();

		if ( array() !== $autorises && ! in_array( $niveau, $autorises, true ) ) {
			return self::erreur( 'niveau_hors_liste', 'semantique', 'Valeur de niveau hors de la liste blanche configurée.', $niveau );
		}

		return true;
	}

	/**
	 * Dernière couche — temporel.
	 *
	 * La date de validité EST la date demandée : jamais l'instant de
	 * récupération, jamais un recalcul, jamais une prolongation. Une charge qui
	 * porte un autre jour que celui demandé est REJETÉE, jamais recalée sur la
	 * date demandée — c'est ce recalage-là qui ferait glisser une donnée d'un
	 * jour sur l'autre.
	 *
	 * @param array<string,mixed> $forme   Sortie de la couche forme.
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @param \DateTimeImmutable  $target  Date de validité demandée.
	 * @return array{publie_le:string|null}|\WP_Error
	 */
	private static function couche_temporel( array $forme, array $headers, \DateTimeImmutable $target ) {
		if ( ! SourceCalendar::is_within_range( $target ) ) {
			return self::erreur(
				'date_hors_plage',
				'temporel',
				sprintf( 'Date de validité %s hors plage : seuls aujourd\'hui et demain sont récupérables.', $target->format( 'Y-m-d' ) ),
				$target->format( 'Y-m-d' )
			);
		}

		if ( (string) $forme['jour'] !== $target->format( 'Y-m-d' ) ) {
			return self::erreur(
				'jour_divergent',
				'temporel',
				sprintf( 'Charge datée du %s pour une demande du %s.', (string) $forme['jour'], $target->format( 'Y-m-d' ) ),
				(string) $forme['jour']
			);
		}

		$brut = '' !== (string) $forme['publie_le'] ? (string) $forme['publie_le'] : self::entete( $headers, 'last-modified' );

		if ( '' === $brut ) {
			// Aucune publication déclarée : contrôle sauté en silence. Rien à
			// inventer, et `publie_le` voyagera à `null`.
			return array( 'publie_le' => null );
		}

		$horodatage = strtotime( $brut );

		if ( false === $horodatage ) {
			return array( 'publie_le' => null );
		}

		$debut_validite = $target->setTime( 0, 0, 0 )->getTimestamp();

		if ( $horodatage < ( $debut_validite - self::PEREMPTION_SECONDES ) ) {
			return self::erreur(
				'publication_perimee',
				'temporel',
				sprintf( 'Publication déclarée le %s, très antérieure au début de validité du %s.', gmdate( DATE_ATOM, $horodatage ), $target->format( 'Y-m-d' ) ),
				gmdate( DATE_ATOM, $horodatage )
			);
		}

		return array( 'publie_le' => gmdate( DATE_ATOM, $horodatage ) );
	}

	/**
	 * Lecture insensible à la casse d'un en-tête.
	 *
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @param string              $nom     Nom recherché, en minuscules.
	 */
	private static function entete( array $headers, string $nom ): string {
		foreach ( $headers as $cle => $valeur ) {
			if ( strtolower( (string) $cle ) !== $nom ) {
				continue;
			}

			if ( is_array( $valeur ) ) {
				$valeur = reset( $valeur );
			}

			return is_scalar( $valeur ) ? trim( (string) $valeur ) : '';
		}

		return '';
	}

	/**
	 * Fabrique une erreur de validation portant sa couche d'origine.
	 *
	 * @param string $code    Code d'erreur.
	 * @param string $couche  Couche d'origine.
	 * @param string $message Message lisible, en français, SANS valeur de niveau.
	 * @param mixed  $detail  Détail structuré facultatif.
	 */
	private static function erreur( string $code, string $couche, string $message, $detail = null ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array(
				'couche' => $couche,
				'detail' => $detail,
			)
		);
	}
}
