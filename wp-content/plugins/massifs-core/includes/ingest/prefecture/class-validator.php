<?php
/**
 * Validation d'un corps de réponse de la préfecture.
 *
 * Cinq couches successives, dans un ordre non négociable : transport, forme,
 * référentiel, sémantique, temporel. Chaque couche rejette pour son propre
 * motif, et le motif voyage dans les données de l'erreur (`couche`, `detail`).
 *
 * Ce validateur n'appelle JAMAIS `Settings::mode()` : il est indépendant du
 * mode par construction, pour que le portail de saisie manuelle puisse réutiliser
 * exactement la même validation que le cron.
 *
 * Il ne traduit jamais un entier source en libellé, couleur ou sévérité : il
 * conserve le jeton brut et laisse toute la sémantique au domaine.
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
 * Validation en cinq couches d'un instantané source.
 */
final class Validator {

	/**
	 * Version de structure de l'instantané produit.
	 */
	public const SCHEMA = 1;

	/**
	 * Bornes de taille du corps, en octets.
	 */
	private const OCTETS_MIN = 200;
	private const OCTETS_MAX = 65536;

	/**
	 * Ancienneté maximale tolérée d'un `Last-Modified`, en secondes.
	 */
	private const PEREMPTION_SECONDES = 48 * HOUR_IN_SECONDS;

	/*
	 * CE QUI N'EST PAS UNE ABERRATION — à ne jamais réintroduire comme rejet.
	 *
	 * 1. Un lot où TOUS les massifs sont au niveau le plus sévère n'est pas une
	 *    aberration : c'est exactement le jour de canicule où l'information
	 *    compte le plus. Le rejeter afficherait « information non disponible »
	 *    ce jour-là, c'est-à-dire précisément quand le site doit servir.
	 * 2. Un lot identique à celui de la veille n'est pas une aberration : c'est
	 *    le cas nominal en juin, où le risque reste stable des jours durant.
	 * 3. Un saut d'amplitude de niveau, quelle qu'en soit la hauteur, n'est pas
	 *    une aberration : la préfecture peut passer d'un extrême à l'autre en
	 *    une publication.
	 *
	 * Le seul détecteur de répétition du connecteur est le rapprochement par
	 * hachage côté `Runner`, et il sert à distinguer « pas encore publié » d'une
	 * vraie publication — jamais à rejeter une donnée.
	 */

	/**
	 * Valide un corps brut et produit l'instantané normalisé.
	 *
	 * @param string             $body     Corps de la réponse, verbatim.
	 * @param array<string,mixed> $headers  En-têtes de réponse, clés libres.
	 * @param \DateTimeImmutable $target   Date de VALIDITÉ demandée.
	 * @param array<string,mixed> $contexte Contexte d'appel : `source_url`, `mode`.
	 * @return array<string,mixed>|\WP_Error Instantané normalisé, ou rejet motivé.
	 */
	public static function validate( string $body, array $headers, \DateTimeImmutable $target, array $contexte = array() ) {
		$a_verifier = false;

		$transport = self::couche_transport( $body, $headers );
		if ( is_wp_error( $transport ) ) {
			return $transport;
		}
		$a_verifier = $a_verifier || $transport['a_verifier'];

		$forme = self::couche_forme( $body );
		if ( is_wp_error( $forme ) ) {
			return $forme;
		}
		$a_verifier = $a_verifier || $forme['a_verifier'];

		$referentiel = self::couche_referentiel( $forme['massifs'] );
		if ( is_wp_error( $referentiel ) ) {
			return $referentiel;
		}

		$semantique = self::couche_semantique( $forme['massifs'] );
		if ( is_wp_error( $semantique ) ) {
			return $semantique;
		}

		$temporel = self::couche_temporel( $headers, $target );
		if ( is_wp_error( $temporel ) ) {
			return $temporel;
		}

		$a_verifier = $a_verifier || $semantique['lot_sans_donnee'];

		$instantane = array(
			'schema'            => self::SCHEMA,
			'date_validite'     => $target->format( 'Y-m-d' ),
			'source_url'        => isset( $contexte['source_url'] ) ? esc_url_raw( (string) $contexte['source_url'] ) : '',
			'recupere_le'       => gmdate( 'c' ),
			'source_modifie_le' => $temporel['source_modifie_le'],
			'hash'              => hash( 'sha256', $body ),
			'octets'            => strlen( $body ),
			'brut'              => $body,
			'massifs'           => $semantique['massifs'],
			'zm'                => $forme['zm'],
			'cles_inconnues'    => $forme['cles_inconnues'],
			'lot_sans_donnee'   => $semantique['lot_sans_donnee'],
			'mode'              => self::mode_contexte( $contexte ),
			'confiance'         => $a_verifier ? 'a_verifier' : 'nominale',
		);

		/**
		 * Dernier mot sur l'acceptation d'un instantané validé.
		 *
		 * @param true|\WP_Error      $verdict    Verdict courant.
		 * @param array<string,mixed> $instantane Instantané normalisé.
		 * @param string              $date_ymd   Date de validité au format `Ymd`.
		 */
		$verdict = apply_filters( 'massifs_prefecture_valider_payload', true, $instantane, $target->format( 'Ymd' ) );

		if ( is_wp_error( $verdict ) ) {
			return $verdict;
		}

		if ( true !== $verdict ) {
			return self::erreur( 'refuse_par_filtre', 'semantique', 'Instantané refusé par le filtre massifs_prefecture_valider_payload.' );
		}

		return $instantane;
	}

	/**
	 * Mode déclaré par l'appelant.
	 *
	 * Volontairement lu dans le contexte et jamais dans les réglages : le
	 * validateur reste utilisable par le portail manuel sans dépendre du mode
	 * global du connecteur.
	 *
	 * @param array<string,mixed> $contexte Contexte d'appel.
	 */
	private static function mode_contexte( array $contexte ): string {
		$mode = isset( $contexte['mode'] ) ? sanitize_key( (string) $contexte['mode'] ) : '';

		return in_array( $mode, array( 'automatique', 'manuel' ), true ) ? $mode : 'automatique';
	}

	/**
	 * Couche 1 — transport : taille et nature du corps reçu.
	 *
	 * Le `Content-Type` n'est jamais un motif de rejet : son absence est
	 * courante sur cette source, et c'est la forme du corps qui tranche. Un
	 * type déclaré non-JSON dégrade seulement la confiance.
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
			return self::erreur( 'html_sous_200', 'transport', 'Réponse HTML servie en HTTP 200 : page d\'erreur ou portail captif, pas un flux de statuts.', $premier );
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
	 * Une clé racine inattendue n'est PAS un rejet : sur un flux non
	 * contractuel, une clé nouvelle est une information, pas une faute. Elle est
	 * collectée et remontée.
	 *
	 * @param string $body Corps brut.
	 * @return array{massifs:array<string,array<int,int>>,zm:array<string,int>|null,cles_inconnues:string[],a_verifier:bool}|\WP_Error
	 */
	private static function couche_forme( string $body ) {
		$document = json_decode( $body, true, 8 );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $document ) ) {
			return self::erreur( 'json_invalide', 'forme', 'JSON illisible : ' . json_last_error_msg() );
		}

		if ( ! isset( $document['massifs'] ) || ! is_array( $document['massifs'] ) || array() === $document['massifs'] ) {
			return self::erreur( 'massifs_absents', 'forme', 'Clé « massifs » absente, non tabulaire ou vide.' );
		}

		$massifs = array();

		foreach ( $document['massifs'] as $identifiant => $couple ) {
			$code = (string) $identifiant;

			if ( 1 !== preg_match( '/^\d{3,4}$/', $code ) ) {
				return self::erreur( 'identifiant_invalide', 'forme', sprintf( 'Identifiant de massif « %s » hors format attendu.', $code ), $code );
			}

			if ( ! is_array( $couple ) || 2 !== count( $couple ) || ! isset( $couple[0], $couple[1] ) ) {
				return self::erreur( 'couple_invalide', 'forme', sprintf( 'Massif %s : couple [niveau, procedure] attendu.', $code ), $code );
			}

			// `is_int` strict : une chaîne numérique ou un flottant est un
			// changement de type du flux, donc un rejet, jamais une conversion
			// silencieuse.
			if ( ! is_int( $couple[0] ) || ! is_int( $couple[1] ) ) {
				return self::erreur( 'type_invalide', 'forme', sprintf( 'Massif %s : niveau et procédure doivent être des entiers.', $code ), $code );
			}

			$massifs[ $code ] = array( $couple[0], $couple[1] );
		}

		$zm         = null;
		$a_verifier = false;

		if ( isset( $document['zm'] ) ) {
			$zm = self::zones_meteo( $document['zm'] );
			// `zm` ne bloque jamais : une forme inattendue est écartée, pas
			// stockée, et le lot reste valide.
			$a_verifier = ( null === $zm );
		}

		$cles_inconnues = array_values(
			array_diff(
				array_map( 'strval', array_keys( $document ) ),
				array( 'massifs', 'zm' )
			)
		);

		return array(
			'massifs'        => $massifs,
			'zm'             => $zm,
			'cles_inconnues' => $cles_inconnues,
			'a_verifier'     => $a_verifier || array() !== $cles_inconnues,
		);
	}

	/**
	 * Normalise le bloc `zm` s'il est exploitable.
	 *
	 * @param mixed $brut Valeur brute.
	 * @return array<string,int>|null
	 */
	private static function zones_meteo( $brut ): ?array {
		if ( ! is_array( $brut ) || array() === $brut ) {
			return null;
		}

		$zones = array();

		foreach ( $brut as $identifiant => $valeur ) {
			if ( ! is_int( $valeur ) ) {
				return null;
			}

			$zones[ (string) $identifiant ] = $valeur;
		}

		return $zones;
	}

	/**
	 * Couche 3 — référentiel : comparaison d'ensembles dans les deux sens.
	 *
	 * Un écart, dans un sens ou dans l'autre, rejette le LOT ENTIER. Jamais de
	 * validation partielle : une carte à laquelle il manque un massif est un
	 * mensonge par omission, et un massif inconnu signale un référentiel
	 * désynchronisé qu'il faut traiter, pas ignorer.
	 *
	 * Référentiel vide : rejet également (fail closed).
	 *
	 * @param array<string,array<int,int>> $massifs Massifs du lot.
	 * @return true|\WP_Error
	 */
	private static function couche_referentiel( array $massifs ) {
		// L'ensemble de référence est celui des identifiants ÉMIS PAR LE FLUX
		// (`131`…`1327`), et non les codes du référentiel métier. Les deux
		// vocabulaires sont distincts et ne doivent jamais être confondus :
		// comparer les identifiants source à des codes métier rejetterait
		// 100 % des charges réelles. Voir le README, section « Référentiel ».
		$reference = Settings::massifs_attendus();

		$reference = is_array( $reference ) ? array_values( array_unique( array_map( 'strval', $reference ) ) ) : array();

		if ( array() === $reference ) {
			return self::erreur( 'referentiel_indisponible', 'referentiel', 'Aucun référentiel de massifs disponible : validation impossible, lot refusé.' );
		}

		$recus = array_keys( $massifs );

		$inconnus  = array_values( array_diff( $recus, $reference ) );
		$manquants = array_values( array_diff( $reference, $recus ) );

		if ( array() !== $inconnus || array() !== $manquants || count( $recus ) !== count( $reference ) ) {
			return self::erreur(
				'referentiel_divergent',
				'referentiel',
				sprintf(
					'Divergence de référentiel : %d reçus pour %d attendus ; inconnus [%s] ; manquants [%s].',
					count( $recus ),
					count( $reference ),
					implode( ', ', $inconnus ),
					implode( ', ', $manquants )
				),
				array(
					'inconnus'  => $inconnus,
					'manquants' => $manquants,
				)
			);
		}

		return true;
	}

	/**
	 * Couche 4 — sémantique : listes blanches de valeurs source.
	 *
	 * Les listes blanches viennent de `Settings`, qui interroge déjà les
	 * passerelles `massifs_niveaux_source_autorises()` /
	 * `massifs_procedures_source_autorisees()` avant ses propres réglages :
	 * aucune énumération de niveaux n'est figée ici.
	 *
	 * Relevé du 2026-08-11, non officiel : le niveau source `0` signifie
	 * « pas de donnée » côté source. L'entrée reste valide et stockée, mais elle
	 * ne porte aucun statut. Un lot entièrement à `0` est un instantané valide
	 * qui déclare explicitement l'absence de statut : ce n'est pas un rejet
	 * d'uniformité.
	 *
	 * @param array<string,array<int,int>> $massifs Massifs du lot.
	 * @return array{massifs:array<string,array<string,mixed>>,lot_sans_donnee:bool}|\WP_Error
	 */
	private static function couche_semantique( array $massifs ) {
		$niveaux    = Settings::niveaux_autorises();
		$procedures = Settings::procedures_autorisees();

		if ( array() === $niveaux || array() === $procedures ) {
			return self::erreur( 'listes_blanches_indisponibles', 'semantique', 'Listes blanches de niveaux ou de procédures vides : lot refusé.' );
		}

		$normalises = array();
		$avec_donnee = false;

		foreach ( $massifs as $code => $couple ) {
			list( $niveau, $procedure ) = $couple;

			if ( ! in_array( $niveau, $niveaux, true ) ) {
				return self::erreur( 'niveau_hors_liste', 'semantique', sprintf( 'Massif %s : niveau source %d hors liste blanche.', $code, $niveau ), $code );
			}

			if ( ! in_array( $procedure, $procedures, true ) ) {
				return self::erreur( 'procedure_hors_liste', 'semantique', sprintf( 'Massif %s : procédure source %d hors liste blanche.', $code, $procedure ), $code );
			}

			$absente = ( 0 === $niveau );

			if ( ! $absente ) {
				$avec_donnee = true;
			}

			$normalises[ $code ] = array(
				'niveau_source'    => $niveau,
				'procedure_source' => $procedure,
				'donnee_absente'   => $absente,
			);
		}

		return array(
			'massifs'         => $normalises,
			'lot_sans_donnee' => ! $avec_donnee,
		);
	}

	/**
	 * Couche 5 — temporel.
	 *
	 * La date de validité EST la date demandée : jamais l'instant de
	 * récupération, jamais le `Last-Modified`, jamais un recalcul, jamais une
	 * prolongation. Un fichier ne peut donc pas « glisser » d'un jour à l'autre.
	 *
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @param \DateTimeImmutable  $target  Date de validité demandée.
	 * @return array{source_modifie_le:string|null}|\WP_Error
	 */
	private static function couche_temporel( array $headers, \DateTimeImmutable $target ) {
		if ( ! SourceCalendar::is_within_range( $target ) ) {
			return self::erreur( 'date_hors_plage', 'temporel', sprintf( 'Date de validité %s hors plage : seuls aujourd\'hui et demain sont récupérables.', $target->format( 'Y-m-d' ) ), $target->format( 'Y-m-d' ) );
		}

		$brut = self::entete( $headers, 'last-modified' );

		if ( '' === $brut ) {
			// En-tête absent : contrôle sauté en silence. Rien à inventer.
			return array( 'source_modifie_le' => null );
		}

		$horodatage = strtotime( $brut );

		if ( false === $horodatage ) {
			return array( 'source_modifie_le' => null );
		}

		$debut_validite = $target->setTime( 0, 0, 0 )->getTimestamp();

		if ( $horodatage < ( $debut_validite - self::PEREMPTION_SECONDES ) ) {
			return self::erreur(
				'fichier_perime',
				'temporel',
				sprintf( 'Fichier modifié le %s, soit plus de 48 h avant le début de validité du %s.', gmdate( 'c', $horodatage ), $target->format( 'Y-m-d' ) ),
				gmdate( 'c', $horodatage )
			);
		}

		return array( 'source_modifie_le' => gmdate( DATE_ATOM, $horodatage ) );
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
	 * @param string $message Message lisible, en français.
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
