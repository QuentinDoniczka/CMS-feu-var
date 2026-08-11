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
		public readonly string $enregistre_le
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
}
