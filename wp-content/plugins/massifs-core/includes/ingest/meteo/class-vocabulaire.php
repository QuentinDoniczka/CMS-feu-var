<?php
/**
 * Garde de vocabulaire de l'échelle de danger météo.
 *
 * Elle répond à une seule question : avons-nous le droit d'afficher un cran de
 * danger ? La réponse est NON tant que les libellés officiels ne sont pas
 * sourcés, et elle ne se force ni par un booléen, ni par une constante, ni par
 * un filtre.
 *
 * La cardinalité de l'échelle est lue ICI et nulle part ailleurs. Aucune autre
 * partie du module ne connaît, ne suppose ni ne borne le nombre de crans.
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
 * Lecture et validation de `vocabulaire.config.php`.
 */
final class Vocabulaire {

	/**
	 * Plafond défensif du nombre de crans.
	 *
	 * GARDE-FOU DE STRUCTURE, JAMAIS UN FAIT DE DOMAINE : il n'affirme rien sur
	 * la cardinalité réelle de l'échelle de Météo-France, il empêche seulement
	 * qu'une table absurde — produite par un filtre fautif — devienne une
	 * échelle affichable. Il n'est jamais exposé au consommateur.
	 *
	 * Il vaut la même borne que celle de la garde de cardinalité du gabarit
	 * (contrat §4.2), pour que le serveur ne puisse jamais produire une échelle
	 * que le thème refuserait de dessiner.
	 */
	private const CRANS_MAX = 12;

	/**
	 * Forme admise d'une clé de cran.
	 */
	private const MOTIF_CLE = '/^[a-z0-9_-]{1,32}$/';

	/**
	 * Configuration brute du fichier, mémorisée pour la requête.
	 *
	 * Seule la LECTURE DU FICHIER est mémorisée. La validation, elle, est
	 * rejouée à chaque appel : un filtre peut être accroché à tout moment de la
	 * requête, et une garde qui daterait de son premier appel ne serait plus
	 * une garde.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $fichier = null;

	/**
	 * Configuration effective : fichier, filtre, puis RE-VALIDATION.
	 *
	 * @return array<string,mixed> Structure toujours complète, éventuellement neutre.
	 */
	public static function configuration(): array {
		$brute = self::depuis_fichier();

		/**
		 * Filtre le vocabulaire de l'échelle de danger météo.
		 *
		 * Ce filtre n'ouvre JAMAIS la garde à lui seul : sa sortie repasse
		 * intégralement par la validation ci-dessous. Fournir `confirme => true`
		 * sans table de crans complète ne produit rien.
		 *
		 * @param array<string,mixed> $brute Vocabulaire lu depuis la configuration.
		 */
		$filtree = apply_filters( 'massifs_meteo_vocabulaire', $brute );

		return self::valider( is_array( $filtree ) ? $filtree : $brute );
	}

	/**
	 * Le vocabulaire est-il confirmé, donc affichable ?
	 */
	public static function est_confirme(): bool {
		return true === self::configuration()['confirme'];
	}

	/**
	 * Crans de l'échelle, ordonnés par rang croissant.
	 *
	 * Tableau VIDE tant que la garde est fermée : il n'existe alors aucun cran,
	 * pas même un cran neutre.
	 *
	 * @return array<int,array{cle:string,libelle:string,rang:int}>
	 */
	public static function crans(): array {
		return self::configuration()['crans'];
	}

	/**
	 * Cardinalité de l'échelle. SEULE source du nombre de crans du module.
	 *
	 * Vaut zéro aujourd'hui, et zéro signifie « aucune échelle n'est
	 * affichable », jamais « échelle vide à dessiner en attendant ».
	 */
	public static function cardinalite(): int {
		return count( self::crans() );
	}

	/**
	 * Cran correspondant à une valeur brute émise par la source.
	 *
	 * @param mixed $valeur_source Jeton de niveau tel qu'émis par la source.
	 * @return array{cle:string,libelle:string,rang:int}|null Null si la garde est fermée ou la valeur sans correspondance.
	 */
	public static function cran_pour_source( $valeur_source ): ?array {
		if ( ! is_scalar( $valeur_source ) ) {
			return null;
		}

		$configuration = self::configuration();

		if ( true !== $configuration['confirme'] ) {
			return null;
		}

		$jeton = (string) $valeur_source;

		if ( ! isset( $configuration['correspondance_source'][ $jeton ] ) ) {
			return null;
		}

		$cle = $configuration['correspondance_source'][ $jeton ];

		foreach ( $configuration['crans'] as $cran ) {
			if ( $cran['cle'] === $cle ) {
				return $cran;
			}
		}

		return null;
	}

	/**
	 * Lecture mémorisée du fichier de configuration.
	 *
	 * @return array<string,mixed>
	 */
	private static function depuis_fichier(): array {
		if ( null !== self::$fichier ) {
			return self::$fichier;
		}

		$chemin = __DIR__ . '/vocabulaire.config.php';
		$lue    = is_file( $chemin ) ? require $chemin : null;

		self::$fichier = is_array( $lue ) ? $lue : self::neutre();

		return self::$fichier;
	}

	/**
	 * Structure neutre : garde fermée, aucune donnée.
	 *
	 * @return array<string,mixed>
	 */
	private static function neutre(): array {
		return array(
			'confirme'              => false,
			'revision'              => '',
			'source'                => '',
			'crans'                 => array(),
			'correspondance_source' => array(),
		);
	}

	/**
	 * Valide une structure de vocabulaire, quelle que soit sa provenance.
	 *
	 * Échoue FERMÉ : au premier manquement, la structure neutre est rendue. Une
	 * table à moitié valide n'existe pas — la moitié valide serait affichée.
	 *
	 * @param array<string,mixed> $brute Structure candidate.
	 * @return array<string,mixed>
	 */
	private static function valider( array $brute ): array {
		$neutre = self::neutre();

		if ( true !== ( $brute['confirme'] ?? null ) ) {
			return $neutre;
		}

		$crans = self::valider_crans( $brute['crans'] ?? null );

		if ( array() === $crans ) {
			return $neutre;
		}

		$correspondance = self::valider_correspondance( $brute['correspondance_source'] ?? null, $crans );

		if ( array() === $correspondance ) {
			return $neutre;
		}

		return array(
			'confirme'              => true,
			'revision'              => is_string( $brute['revision'] ?? null ) ? sanitize_text_field( $brute['revision'] ) : '',
			'source'                => is_string( $brute['source'] ?? null ) ? sanitize_text_field( $brute['source'] ) : '',
			'crans'                 => $crans,
			'correspondance_source' => $correspondance,
		);
	}

	/**
	 * Valide la table des crans : clés, libellés, rangs distincts et contigus.
	 *
	 * @param mixed $brute Table candidate.
	 * @return array<int,array{cle:string,libelle:string,rang:int}> Vide si invalide.
	 */
	private static function valider_crans( $brute ): array {
		if ( ! is_array( $brute ) || array() === $brute ) {
			return array();
		}

		if ( count( $brute ) > self::CRANS_MAX ) {
			return array();
		}

		$crans = array();
		$cles  = array();
		$rangs = array();

		foreach ( $brute as $entree ) {
			if ( ! is_array( $entree ) ) {
				return array();
			}

			$cle     = is_string( $entree['cle'] ?? null ) ? trim( $entree['cle'] ) : '';
			$libelle = is_string( $entree['libelle'] ?? null ) ? trim( $entree['libelle'] ) : '';
			$rang    = $entree['rang'] ?? null;

			if ( 1 !== preg_match( self::MOTIF_CLE, $cle ) || '' === $libelle || ! is_int( $rang ) || $rang < 1 ) {
				return array();
			}

			if ( in_array( $cle, $cles, true ) || in_array( $rang, $rangs, true ) ) {
				return array();
			}

			$cles[]  = $cle;
			$rangs[] = $rang;

			$crans[] = array(
				'cle'     => $cle,
				'libelle' => $libelle,
				'rang'    => $rang,
			);
		}

		sort( $rangs );

		// Contiguïté depuis 1 : une échelle trouée ferait dessiner des cases
		// qui ne correspondent à aucun cran publié.
		foreach ( $rangs as $position => $rang ) {
			if ( $rang !== $position + 1 ) {
				return array();
			}
		}

		usort(
			$crans,
			static function ( array $gauche, array $droite ): int {
				return $gauche['rang'] <=> $droite['rang'];
			}
		);

		return $crans;
	}

	/**
	 * Valide la correspondance source → cran.
	 *
	 * @param mixed                                                 $brute Table candidate.
	 * @param array<int,array{cle:string,libelle:string,rang:int}>   $crans Crans déjà validés.
	 * @return array<string,string> Vide si invalide.
	 */
	private static function valider_correspondance( $brute, array $crans ): array {
		if ( ! is_array( $brute ) || array() === $brute ) {
			return array();
		}

		$connues = array();

		foreach ( $crans as $cran ) {
			$connues[] = $cran['cle'];
		}

		$correspondance = array();

		foreach ( $brute as $jeton => $cle ) {
			if ( ! is_scalar( $jeton ) || ! is_string( $cle ) || ! in_array( $cle, $connues, true ) ) {
				return array();
			}

			$correspondance[ (string) $jeton ] = $cle;
		}

		return $correspondance;
	}
}
