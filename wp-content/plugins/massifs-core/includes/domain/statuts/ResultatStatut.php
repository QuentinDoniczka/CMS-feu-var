<?php
/**
 * Objet valeur : le statut d'un massif pour un jour demandé.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LogicException;

/**
 * Le statut résolu d'un massif, pour le jour DEMANDÉ.
 *
 * Cette classe est le verrou du §4.2 du brief : « ne jamais présenter un statut
 * périmé comme courant ». Le constructeur est privé et `disponible()` est le
 * SEUL constructeur nommé qui accepte un `Niveau` — il exige que le jour de
 * validité de la ligne soit identique au jour demandé, sinon il lève. Un
 * refactor futur ne peut donc pas fabriquer un état « disponible » à partir
 * d'une ligne d'un autre jour, même par erreur.
 */
final class ResultatStatut {

	/**
	 * Construit un résultat.
	 *
	 * @param string            $massif_code          Code du massif.
	 * @param EtatStatut        $etat                 État résolu.
	 * @param string            $jour_validite        Jour DEMANDÉ.
	 * @param Niveau|null       $niveau               Niveau d'accès au massif, uniquement si disponible.
	 * @param Niveau|null       $zapef                Entrée ZAPEF, uniquement si disponible.
	 * @param SourceStatut|null $source               Provenance, uniquement si disponible.
	 * @param int|null          $auteur_id            Auteur, uniquement si saisie manuelle.
	 * @param string|null       $publie_prefecture_le Instant de publication préfectorale, ISO 8601 UTC.
	 * @param string|null       $enregistre_le        Instant d'enregistrement, ISO 8601 UTC.
	 * @param int|null          $statut_id            Identifiant de la ligne.
	 */
	private function __construct(
		public readonly string $massif_code,
		public readonly EtatStatut $etat,
		public readonly string $jour_validite,
		public readonly ?Niveau $niveau,
		public readonly ?Niveau $zapef,
		public readonly ?SourceStatut $source,
		public readonly ?int $auteur_id,
		public readonly ?string $publie_prefecture_le,
		public readonly ?string $enregistre_le,
		public readonly ?int $statut_id
	) {}

	/**
	 * Statut disponible : une ligne existe pour ce massif ET ce jour, ET elle
	 * porte un niveau d'accès.
	 *
	 * Le type NON NULLABLE de `$niveau` est le second verrou de cette classe : une
	 * ligne qui ne porte aucun niveau — la source a publié « aucune donnée » — ne
	 * peut structurellement pas passer par ici. Elle doit passer par
	 * `indisponible()`.
	 *
	 * @param string       $massif_code          Code du massif.
	 * @param string       $jour_demande         Jour demandé par l'appelant.
	 * @param string       $jour_ligne           Jour de validité porté par la ligne.
	 * @param Niveau       $niveau               Niveau d'accès au massif.
	 * @param Niveau|null  $zapef                Entrée ZAPEF, `null` si la ligne n'en porte pas.
	 * @param SourceStatut $source               Provenance de la ligne.
	 * @param int|null     $auteur_id            Auteur de la saisie manuelle.
	 * @param string|null  $publie_prefecture_le Instant de publication préfectorale.
	 * @param string       $enregistre_le        Instant d'enregistrement.
	 * @param int          $statut_id            Identifiant de la ligne.
	 *
	 * @throws LogicException Si la ligne ne porte pas exactement le jour demandé.
	 */
	public static function disponible(
		string $massif_code,
		string $jour_demande,
		string $jour_ligne,
		Niveau $niveau,
		?Niveau $zapef,
		SourceStatut $source,
		?int $auteur_id,
		?string $publie_prefecture_le,
		string $enregistre_le,
		int $statut_id
	): self {
		if ( $jour_ligne !== $jour_demande ) {
			throw new LogicException(
				'Un statut ne peut être présenté comme disponible que pour son propre jour de validité.'
			);
		}

		return new self(
			$massif_code,
			EtatStatut::Disponible,
			$jour_demande,
			$niveau,
			$zapef,
			$source,
			$auteur_id,
			$publie_prefecture_le,
			$enregistre_le,
			$statut_id
		);
	}

	/**
	 * Aucune information disponible pour ce jour.
	 *
	 * DEUX CAUSES, UN SEUL ÉTAT : soit aucune ligne n'existe pour ce couple, soit
	 * une ligne existe mais la source a explicitement publié « aucune donnée »
	 * (`level` 0). Le visiteur ne distingue pas les deux, et c'est voulu : dans
	 * les deux cas nous n'avons pas l'information.
	 *
	 * @param string $massif_code Code du massif.
	 * @param string $jour        Jour demandé.
	 */
	public static function indisponible( string $massif_code, string $jour ): self {
		return self::sans_niveau( $massif_code, EtatStatut::Indisponible, $jour );
	}

	/**
	 * Dispositif inactif ce jour-là et aucune donnée.
	 *
	 * @param string $massif_code Code du massif.
	 * @param string $jour        Jour demandé.
	 */
	public static function hors_saison( string $massif_code, string $jour ): self {
		return self::sans_niveau( $massif_code, EtatStatut::HorsSaison, $jour );
	}

	/**
	 * Jour demandé futur, rien de publié.
	 *
	 * @param string $massif_code Code du massif.
	 * @param string $jour        Jour demandé.
	 */
	public static function non_encore_publie( string $massif_code, string $jour ): self {
		return self::sans_niveau( $massif_code, EtatStatut::NonEncorePublie, $jour );
	}

	/**
	 * Fabrique commune des états sans niveau.
	 *
	 * @param string     $massif_code Code du massif.
	 * @param EtatStatut $etat        État résolu.
	 * @param string     $jour        Jour demandé.
	 */
	private static function sans_niveau( string $massif_code, EtatStatut $etat, string $jour ): self {
		return new self( $massif_code, $etat, $jour, null, null, null, null, null, null, null );
	}

	/**
	 * Forme exposée aux consommateurs.
	 *
	 * @return array<string, mixed>
	 */
	public function en_tableau(): array {
		// `niveau_source_brut` et `procedure_source` n'apparaissent volontairement
		// PAS ici : le consommateur ne doit jamais afficher un entier de source ni
		// le traduire lui-même en libellé.
		return array(
			'massif_code'          => $this->massif_code,
			'etat'                 => $this->etat->value,
			'jour_validite'        => $this->jour_validite,
			'niveau'               => null === $this->niveau ? null : $this->niveau->en_tableau(),
			'zapef'                => null === $this->zapef ? null : $this->zapef->en_tableau(),
			'source'               => null === $this->source ? null : $this->source->value,
			'auteur_id'            => $this->auteur_id,
			'publie_prefecture_le' => $this->publie_prefecture_le,
			'enregistre_le'        => $this->enregistre_le,
			'statut_id'            => $this->statut_id,
		);
	}
}
