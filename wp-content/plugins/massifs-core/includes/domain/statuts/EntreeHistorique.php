<?php
/**
 * Objet valeur : une ligne d'historique, avec sa valeur précédente dérivée.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Fraicheur\Horloge;

/**
 * Une écriture de l'historique, telle que le §6 du brief la présentera.
 *
 * `niveau_precedent_cle` n'est pas stocké : il est DÉRIVÉ de la ligne
 * précédente du même couple (massif, jour). C'est ce qui permet d'avoir une
 * seule table — donc une seule vérité — pour l'historique et le journal
 * d'écriture.
 *
 * DEUX FABRIQUES, ET ELLES NE SE VALENT PAS : `depuis_lignes()` dérive le
 * précédent en parcourant les lignes reçues, ce qui n'est juste que sur un
 * ensemble complet trié par (massif, jour, id) croissants ; `depuis_lignes_jointes()`
 * lit un précédent ÉTABLI EN SQL sur la partition non filtrée et reste donc vrai
 * sous n'importe quel filtre, n'importe quelle page et n'importe quel ordre —
 * c'est la seule des deux que le journal du §6 a le droit d'employer.
 */
final class EntreeHistorique {

	/**
	 * Construit une entrée.
	 *
	 * @param int          $id                   Identifiant de la ligne.
	 * @param string       $massif_code          Code du massif.
	 * @param string       $jour_validite        Jour de validité.
	 * @param string|null  $niveau_cle           Clé du niveau écrit, `null` si la source n'a publié aucun statut.
	 * @param string|null  $niveau_precedent_cle Clé du niveau qu'il remplace, ou `null` si première écriture.
	 * @param string|null  $zapef_cle            Clé ZAPEF écrite.
	 * @param string|null  $zapef_precedent_cle  Clé ZAPEF qu'elle remplace, ou `null` si première écriture.
	 * @param SourceStatut $source               Provenance.
	 * @param int|null     $auteur_id            Auteur de la saisie manuelle.
	 * @param string|null  $publie_prefecture_le Publication préfectorale, ISO 8601 UTC.
	 * @param string       $enregistre_le        Instant d'enregistrement, ISO 8601 UTC.
	 * @param int|null     $precedent_id         Identifiant de la ligne précédente ÉTABLIE EN SQL, `null` s'il n'en existe aucune.
	 * @param bool         $precedent_etabli     `true` seulement si l'existence du précédent a été établie ; `false` = personne ne l'a cherchée.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $massif_code,
		public readonly string $jour_validite,
		public readonly ?string $niveau_cle,
		public readonly ?string $niveau_precedent_cle,
		public readonly ?string $zapef_cle,
		public readonly ?string $zapef_precedent_cle,
		public readonly SourceStatut $source,
		public readonly ?int $auteur_id,
		public readonly ?string $publie_prefecture_le,
		public readonly string $enregistre_le,
		public readonly ?int $precedent_id = null,
		public readonly bool $precedent_etabli = false
	) {}

	/**
	 * Construit les entrées depuis des lignes de dépôt.
	 *
	 * @param list<array<string, mixed>> $lignes Lignes ordonnées par (massif_code, jour_validite, id) croissants.
	 *
	 * @return list<self>
	 */
	public static function depuis_lignes( array $lignes ): array {
		$entrees         = array();
		$precedent       = array();
		$precedent_zapef = array();

		foreach ( $lignes as $ligne ) {
			$source = SourceStatut::tryFrom( (string) ( $ligne['source'] ?? '' ) );

			if ( null === $source ) {
				continue;
			}

			$massif_code   = (string) ( $ligne['massif_code'] ?? '' );
			$jour_validite = (string) ( $ligne['jour_validite'] ?? '' );
			$niveau_cle    = self::cle( $ligne, 'niveau_cle' );
			$zapef_cle     = self::cle( $ligne, 'zapef_cle' );
			$couple        = $massif_code . '|' . $jour_validite;
			$auteur_id     = isset( $ligne['auteur_id'] ) && null !== $ligne['auteur_id']
				? (int) $ligne['auteur_id']
				: null;

			$entrees[] = new self(
				(int) ( $ligne['id'] ?? 0 ),
				$massif_code,
				$jour_validite,
				$niveau_cle,
				$precedent[ $couple ] ?? null,
				$zapef_cle,
				$precedent_zapef[ $couple ] ?? null,
				$source,
				$auteur_id,
				Horloge::stockage_vers_iso_utc( isset( $ligne['publie_prefecture_le'] ) ? (string) $ligne['publie_prefecture_le'] : null ),
				(string) Horloge::stockage_vers_iso_utc( (string) ( $ligne['enregistre_le'] ?? '' ) )
			);

			$precedent[ $couple ]       = $niveau_cle;
			$precedent_zapef[ $couple ] = $zapef_cle;
		}

		return $entrees;
	}

	/**
	 * Lit une clé de légende d'une ligne, en distinguant absente et vide.
	 *
	 * @param array<string, mixed> $ligne Ligne du dépôt.
	 * @param string               $champ Nom de la colonne.
	 */
	private static function cle( array $ligne, string $champ ): ?string {
		if ( ! isset( $ligne[ $champ ] ) ) {
			return null;
		}

		$valeur = trim( (string) $ligne[ $champ ] );

		return '' === $valeur ? null : $valeur;
	}

	/**
	 * Forme tabulaire de l'entrée.
	 *
	 * @return array<string, mixed>
	 */
	public function en_tableau(): array {
		// `niveau_source_brut` et `procedure_source` n'apparaissent volontairement
		// pas dans l'historique exposé : l'écran du §6 montre ce qui a été publié,
		// pas les entiers de la source.
		return array(
			'id'                   => $this->id,
			'massif_code'          => $this->massif_code,
			'jour_validite'        => $this->jour_validite,
			'niveau_cle'           => $this->niveau_cle,
			'niveau_precedent_cle' => $this->niveau_precedent_cle,
			'zapef_cle'            => $this->zapef_cle,
			'zapef_precedent_cle'  => $this->zapef_precedent_cle,
			'source'               => $this->source->value,
			'auteur_id'            => $this->auteur_id,
			'publie_prefecture_le' => $this->publie_prefecture_le,
			'enregistre_le'        => $this->enregistre_le,
		);
	}

	/**
	 * Construit les entrées depuis des lignes PORTANT LEUR PRÉCÉDENT.
	 *
	 * SANS AUCUN ÉTAT ENTRE DEUX LIGNES : chaque ligne porte déjà son
	 * `precedent_id`, son `precedent_niveau_cle` et son `precedent_zapef_cle`,
	 * établis par l'auto-jointure corrélée de `Depot::selectionner_journal()` sur
	 * la partition NON FILTRÉE du couple (massif, jour). C'est ce qui rend la
	 * fabrique insensible à l'ordre, aux filtres et à la pagination — les trois
	 * façons dont `depuis_lignes()` se trompe.
	 *
	 * @param list<array<string, mixed>> $lignes Lignes de `Depot::selectionner_journal()`, dans n'importe quel ordre.
	 *
	 * @return list<self>
	 */
	public static function depuis_lignes_jointes( array $lignes ): array {
		$entrees = array();

		foreach ( $lignes as $ligne ) {
			$source = SourceStatut::tryFrom( (string) ( $ligne['source'] ?? '' ) );

			if ( null === $source ) {
				continue;
			}

			$entrees[] = new self(
				(int) ( $ligne['id'] ?? 0 ),
				(string) ( $ligne['massif_code'] ?? '' ),
				(string) ( $ligne['jour_validite'] ?? '' ),
				self::cle( $ligne, 'niveau_cle' ),
				self::cle( $ligne, 'precedent_niveau_cle' ),
				self::cle( $ligne, 'zapef_cle' ),
				self::cle( $ligne, 'precedent_zapef_cle' ),
				$source,
				isset( $ligne['auteur_id'] ) && null !== $ligne['auteur_id'] ? (int) $ligne['auteur_id'] : null,
				Horloge::stockage_vers_iso_utc( isset( $ligne['publie_prefecture_le'] ) ? (string) $ligne['publie_prefecture_le'] : null ),
				(string) Horloge::stockage_vers_iso_utc( (string) ( $ligne['enregistre_le'] ?? '' ) ),
				isset( $ligne['precedent_id'] ) && null !== $ligne['precedent_id'] ? (int) $ligne['precedent_id'] : null,
				true
			);
		}

		return $entrees;
	}

	/**
	 * Une ligne antérieure existe-t-elle, et cela a-t-il été ÉTABLI ?
	 *
	 * `false` couvre deux situations volontairement confondues du point de vue de
	 * l'affichage : il n'y a pas de précédent, ou personne ne l'a cherché. Aucune
	 * des deux n'autorise à écrire quoi que ce soit sur une valeur ancienne.
	 */
	public function precedent_est_connu(): bool {
		return $this->precedent_etabli && null !== $this->precedent_id;
	}

	/**
	 * Est-ce la toute première écriture de ce couple (massif, jour) ?
	 *
	 * VRAI UNIQUEMENT SI L'ABSENCE DE PRÉCÉDENT A ÉTÉ ÉTABLIE. Une entrée issue de
	 * `depuis_lignes()` répond donc toujours `false` : elle n'a pas de quoi le
	 * savoir, et l'affirmer serait précisément le mensonge que le §12 interdit.
	 */
	public function est_premiere_publication(): bool {
		return $this->precedent_etabli && null === $this->precedent_id;
	}

	/**
	 * Forme tabulaire ENRICHIE, pour le journal du §6.
	 *
	 * N'A DE SENS QUE SUR UNE ENTRÉE DE `depuis_lignes_jointes()`. Sur une entrée
	 * dont le précédent n'a pas été établi, `changement` retombe sur
	 * `modification` : c'est la seule des trois valeurs qui n'affirme rien
	 * — ni « première publication », ni « sans changement ».
	 *
	 * @return array<string, mixed>
	 */
	public function en_tableau_journal(): array {
		if ( $this->est_premiere_publication() ) {
			$changement = 'premiere_publication';
		} elseif ( $this->precedent_est_connu()
			&& $this->niveau_cle === $this->niveau_precedent_cle
			&& $this->zapef_cle === $this->zapef_precedent_cle ) {
			$changement = 'sans_changement';
		} else {
			$changement = 'modification';
		}

		return array_merge(
			$this->en_tableau(),
			array(
				'premiere_publication' => $this->est_premiere_publication(),
				'changement'           => $changement,
			)
		);
	}
}
