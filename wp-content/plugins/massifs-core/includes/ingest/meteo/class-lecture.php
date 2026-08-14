<?php
/**
 * Couche de LECTURE : la seule à décider ce qui est présentable.
 *
 * TROIS RÈGLES QUI NE SE DISCUTENT PAS
 *
 * 1. La fonction est TOTALE. Elle ne lève jamais, ne rend jamais `null`, `false`
 *    ni `WP_Error`, et TOUTES ses clés sont toujours présentes. Le consommateur
 *    n'écrit jamais `isset()` ni `??` sur une clé du contrat. Motif : une
 *    exception ferait tomber toute la page pour un module secondaire de bas de
 *    page, alors que les statuts d'accès sont la raison d'être de cette page.
 *
 * 2. Un instantané n'est servi QUE POUR SON PROPRE JOUR DE VALIDITÉ. Il n'existe
 *    aucun accesseur « dernier instantané », donc aucun chemin par lequel la
 *    valeur de la veille pourrait être présentée comme courante. L'absence de
 *    réponse pour une date EST une réponse.
 *
 * 3. `disponible` exige un CRAN CONFIRMÉ. Quelle que soit la charge reçue et
 *    mise en cache — fût-elle pleinement valide et bavarde —, tant que
 *    `Vocabulaire::est_confirme()` est faux, l'état rendu est `indisponible`. La
 *    garde est dans notre code, pas dans la source : un bouchon qui injecterait
 *    un libellé et une cardinalité ne peut pas la contourner.
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
 * Composition de la réponse publique du module.
 */
final class Lecture {

	/**
	 * Vocabulaire FERMÉ des états. Trois valeurs, jamais une quatrième.
	 *
	 * `hors_saison` n'existe PAS ici et ne doit pas être créé : affirmer au
	 * visiteur que la source ne publie pas hors du dispositif préfectoral serait
	 * inventer un fait de domaine sur une source tierce. La période
	 * d'exploitation est une porte opérationnelle, pas un état public.
	 *
	 * `donnee_perimee` n'existe pas non plus : il n'y a aucun état intermédiaire
	 * entre « courant » et « absent ».
	 */
	public const ETATS = array( 'disponible', 'indisponible', 'non_encore_publie' );

	/**
	 * Réponse publique pour un jour donné.
	 *
	 * @param string|null $jour Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 * @return array<string,mixed> Toujours complet, jamais vide, jamais nul.
	 */
	public static function du_jour( ?string $jour = null ): array {
		$demande = '';

		try {
			$demande = self::normaliser( $jour );

			return '' === $demande ? self::repli( '' ) : self::composer( $demande );
		} catch ( \Throwable $e ) {
			// Contractuellement inatteignable. Elle demeure parce qu'une
			// exception qui remonterait d'ici casserait la page d'accueil, et
			// que son repli doit être une absence, jamais une donnée.
			return self::repli( $demande );
		}
	}

	/**
	 * Normalise le jour demandé.
	 *
	 * @param string|null $jour Jour brut.
	 * @return string `Y-m-d` valide, ou chaîne vide si la demande est malformée.
	 */
	private static function normaliser( ?string $jour ): string {
		if ( null === $jour ) {
			return SourceCalendar::today()->format( 'Y-m-d' );
		}

		$date = SourceCalendar::from_iso( trim( $jour ) );

		return null === $date ? '' : $date->format( 'Y-m-d' );
	}

	/**
	 * Compose la réponse pour un jour normalisé.
	 *
	 * @param string $jour Jour `Y-m-d` valide.
	 * @return array<string,mixed>
	 */
	private static function composer( string $jour ): array {
		$date = SourceCalendar::from_iso( $jour );

		if ( null === $date ) {
			return self::repli( '' );
		}

		$instantane  = SnapshotRepository::get( $date->format( 'Ymd' ) );
		$exploitable = SourceCalendar::est_exploitable( $date );

		$cran = null;

		if ( $exploitable && null !== $instantane ) {
			$cran = Vocabulaire::cran_pour_source( $instantane['niveau_source'] ?? null );
		}

		$etat = self::resoudre_etat( $date, $instantane, $exploitable, $cran );

		$crans   = Vocabulaire::cardinalite();
		$atteint = 'disponible' === $etat && null !== $cran ? (int) $cran['rang'] : 0;

		$echelle = array(
			'crans'     => $crans,
			'atteint'   => $atteint,
			'confirmee' => Vocabulaire::est_confirme(),
			'phrase'    => 'disponible' === $etat ? self::phrase_echelle( $atteint, $crans ) : '',
		);

		$niveau = 'disponible' === $etat && null !== $cran
			? array(
				'cle'     => (string) $cran['cle'],
				'libelle' => (string) $cran['libelle'],
			)
			: null;

		// DERNIER VERROU. Si l'un des invariants de cohérence n'est pas tenu, on
		// ne rectifie pas, on ne rogne pas, on ne complète pas : on retombe sur
		// l'absence. Une valeur fausse ne doit jamais devenir une valeur
		// plausible.
		if ( 'disponible' === $etat && ! self::coherent( $niveau, $echelle ) ) {
			return self::repli( $jour, $instantane );
		}

		return self::assembler( $jour, $etat, $niveau, $echelle, $instantane );
	}

	/**
	 * Résout l'état public.
	 *
	 * @param \DateTimeImmutable        $date        Jour demandé.
	 * @param array<string,mixed>|null  $instantane  Instantané couvrant ce jour, ou null.
	 * @param bool                      $exploitable Le jour est-il dans la période d'exploitation ?
	 * @param array<string,mixed>|null  $cran        Cran confirmé correspondant, ou null.
	 */
	private static function resoudre_etat( \DateTimeImmutable $date, ?array $instantane, bool $exploitable, ?array $cran ): string {
		if ( null !== $cran ) {
			return 'disponible';
		}

		if ( $exploitable
			&& null === $instantane
			&& $date->format( 'Ymd' ) === SourceCalendar::tomorrow()->format( 'Ymd' ) ) {
			return 'non_encore_publie';
		}

		return 'indisponible';
	}

	/**
	 * Invariants de cohérence de l'état `disponible`.
	 *
	 * @param array<string,string>|null $niveau  Bloc niveau.
	 * @param array<string,mixed>       $echelle Bloc échelle.
	 */
	private static function coherent( ?array $niveau, array $echelle ): bool {
		return null !== $niveau
			&& '' !== $niveau['libelle']
			&& true === $echelle['confirmee']
			&& $echelle['crans'] >= 1
			&& $echelle['atteint'] >= 1
			&& $echelle['atteint'] <= $echelle['crans']
			&& '' !== $echelle['phrase'];
	}

	/**
	 * Phrase de position sur l'échelle, rédigée par le serveur.
	 *
	 * Elle décrit la position de la donnée SUR L'ÉCHELLE DE LA SOURCE, et son
	 * libellé même dépend d'une cardinalité que seul le serveur sait confirmée :
	 * elle est donc du côté « données », pas du côté « état de notre site ».
	 *
	 * @param int $atteint Rang atteint.
	 * @param int $crans   Cardinalité de l'échelle.
	 */
	private static function phrase_echelle( int $atteint, int $crans ): string {
		$gabarit = $atteint > 1 ? '%1$d crans sur %2$d' : '%1$d cran sur %2$d';

		return sprintf( $gabarit, $atteint, $crans );
	}

	/**
	 * Assemble la réponse complète.
	 *
	 * @param string                    $jour       Jour DEMANDÉ, toujours.
	 * @param string                    $etat       État public.
	 * @param array<string,string>|null $niveau     Bloc niveau, ou null littéral.
	 * @param array<string,mixed>       $echelle    Bloc échelle.
	 * @param array<string,mixed>|null  $instantane Instantané couvrant ce jour, ou null.
	 * @return array<string,mixed>
	 */
	private static function assembler( string $jour, string $etat, ?array $niveau, array $echelle, ?array $instantane ): array {
		return array(
			'jour'        => $jour,
			'etat'        => in_array( $etat, self::ETATS, true ) ? $etat : 'indisponible',
			'niveau'      => $niveau,
			'echelle'     => $echelle,
			'zone'        => Settings::zone(),
			'releve_le'   => Releve::dernier(),
			'publie_le'   => self::publie_le( $instantane ),
			'distinction' => Settings::distinction(),
			'attribution' => Settings::attribution(),
		);
	}

	/**
	 * Instant de publication déclaré par la source POUR CE JOUR.
	 *
	 * Jamais celui d'une autre journée : il ne peut venir que de l'instantané de
	 * la date demandée, ou de nulle part.
	 *
	 * @param array<string,mixed>|null $instantane Instantané couvrant ce jour.
	 */
	private static function publie_le( ?array $instantane ): ?string {
		if ( null === $instantane || ! isset( $instantane['publie_le'] ) || ! is_string( $instantane['publie_le'] ) ) {
			return null;
		}

		$declare = $instantane['publie_le'];

		// Ré-assainissement à la LECTURE, comme pour `date_validite` dans
		// `SnapshotRepository::get()` : l'instantané vit dans une option, modifiable
		// depuis l'administration, et cette valeur traverse la frontière du contrat
		// annoncée comme un instant ISO 8601. Ce qui n'est pas un instant est écarté,
		// jamais transmis tel quel — `null` est une réponse honnête, une chaîne
		// arbitraire ne l'est pas.
		if ( '' === $declare || false === strtotime( $declare ) ) {
			return null;
		}

		return $declare;
	}

	/**
	 * Repli honnête : absence de donnée, forme complète.
	 *
	 * @param string                   $jour       Jour demandé, ou chaîne vide si malformé.
	 * @param array<string,mixed>|null $instantane Instantané couvrant ce jour, s'il est connu.
	 * @return array<string,mixed>
	 */
	private static function repli( string $jour, ?array $instantane = null ): array {
		try {
			return self::assembler( $jour, 'indisponible', null, self::echelle_vide(), $instantane );
		} catch ( \Throwable $e ) {
			return self::minimal( $jour );
		}
	}

	/**
	 * Échelle sans rang atteint.
	 *
	 * `crans` reste la CARDINALITÉ lue de la configuration — un fait, nul
	 * aujourd'hui — et `confirmee` l'état réel de la garde. Seuls `atteint` et
	 * `phrase` sont neutralisés : c'est la position sur l'échelle qui manque,
	 * pas l'échelle elle-même.
	 *
	 * @return array<string,mixed>
	 */
	private static function echelle_vide(): array {
		return array(
			'crans'     => Vocabulaire::cardinalite(),
			'atteint'   => 0,
			'confirmee' => Vocabulaire::est_confirme(),
			'phrase'    => '',
		);
	}

	/**
	 * Dernier repli, sans aucune lecture d'option ni de configuration.
	 *
	 * @param string $jour Jour demandé, ou chaîne vide.
	 * @return array<string,mixed>
	 */
	private static function minimal( string $jour ): array {
		$defauts = Settings::defaults();

		return array(
			'jour'        => $jour,
			'etat'        => 'indisponible',
			'niveau'      => null,
			'echelle'     => array(
				'crans'     => 0,
				'atteint'   => 0,
				'confirmee' => false,
				'phrase'    => '',
			),
			'zone'        => array(
				'cle'         => (string) $defauts['zone_cle'],
				'libelle'     => (string) $defauts['zone_libelle'],
				'granularite' => (string) $defauts['zone_granularite'],
			),
			'releve_le'   => null,
			'publie_le'   => null,
			'distinction' => Settings::distinction(),
			'attribution' => Settings::attribution(),
		);
	}
}
