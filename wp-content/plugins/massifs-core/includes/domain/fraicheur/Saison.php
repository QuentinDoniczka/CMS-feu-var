<?php
/**
 * Activité calendaire du dispositif estival.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Le dispositif préfectoral, vu par le calendrier SEUL.
 *
 * Cette classe ne consulte aucune donnée : elle répond « le calendrier dit
 * actif / inactif », rien de plus. La résolution d'un statut fait primer la
 * donnée sur le calendrier (voir `Statuts`) : si la préfecture prolonge le
 * dispositif au-delà de la borne de fin, le vrai statut est affiché plutôt
 * qu'un mensonge « hors saison ».
 *
 * Les bornes sont injectées à la construction : `officielle()` les lit dans
 * `saison.config.php`, seule source de configuration du dispositif réel.
 */
final class Saison {

	/**
	 * Calendrier officiel mémoïsé pour la durée du processus.
	 *
	 * `Saison` est un objet-valeur à propriétés `readonly` : le partager entre
	 * appelants ne peut donc rien altérer.
	 */
	private static ?self $officielle = null;

	/**
	 * Construit le calendrier du dispositif.
	 *
	 * @param int  $debut_mois Mois de début (1-12).
	 * @param int  $debut_jour Jour de début (1-31).
	 * @param int  $fin_mois   Mois de fin (1-12).
	 * @param int  $fin_jour   Jour de fin (1-31).
	 * @param bool $confirmee  Les bornes sont-elles confirmées ?
	 */
	public function __construct(
		private readonly int $debut_mois,
		private readonly int $debut_jour,
		private readonly int $fin_mois,
		private readonly int $fin_jour,
		private readonly bool $confirmee
	) {}

	/**
	 * Calendrier officiel du dispositif.
	 *
	 * SEULE fabrique du calendrier officiel : aucun appelant ne compose ses
	 * propres bornes. La configuration est `require`ée ICI, au point d'usage, et
	 * jamais par l'amorce du module — un `require_once` sur un fichier qui
	 * RETOURNE un tableau rendrait `true` au second appel.
	 *
	 * Configuration qui ne RETOURNE PAS un tableau ⇒ dates par défaut SANS
	 * `confirme` : le calendrier répond « bornes non confirmées », la réponse
	 * honnête. Un fichier ABSENT, lui, reste une erreur fatale — le `require` est
	 * nu, comme au chargement de la légende. Un module amputé de sa propre
	 * configuration ne se rattrape pas, il se voit.
	 */
	public static function officielle(): self {
		if ( null !== self::$officielle ) {
			return self::$officielle;
		}

		$bornes = require __DIR__ . '/saison.config.php';

		if ( ! is_array( $bornes ) ) {
			$bornes = array();
		}

		self::$officielle = self::depuis_bornes( $bornes );

		return self::$officielle;
	}

	/**
	 * Construit le calendrier depuis un jeu de bornes explicite.
	 *
	 * @param array<string, bool|int> $bornes Bornes du dispositif.
	 */
	public static function depuis_bornes( array $bornes ): self {
		return new self(
			(int) ( $bornes['debut_mois'] ?? 6 ),
			(int) ( $bornes['debut_jour'] ?? 1 ),
			(int) ( $bornes['fin_mois'] ?? 9 ),
			(int) ( $bornes['fin_jour'] ?? 30 ),
			true === ( $bornes['confirme'] ?? false )
		);
	}

	/**
	 * Le dispositif est-il actif ce jour-là, selon le calendrier seul ?
	 *
	 * @param string $jour Jour civil `YYYY-MM-DD`.
	 *
	 * @throws \InvalidArgumentException Si le jour est mal formé.
	 */
	public function est_active( string $jour ): bool {
		$annee = (int) Horloge::jour_vers_debut( $jour )->format( 'Y' );

		return Horloge::comparer_jours( $jour, $this->debut( $annee ) ) >= 0
			&& Horloge::comparer_jours( $jour, $this->fin( $annee ) ) <= 0;
	}

	/**
	 * Évalue le dispositif à une date.
	 *
	 * @param string $jour Jour civil `YYYY-MM-DD`.
	 *
	 * @throws \InvalidArgumentException Si le jour est mal formé.
	 */
	public function evaluer( string $jour ): ResultatSaison {
		$annee  = (int) Horloge::jour_vers_debut( $jour )->format( 'Y' );
		$debut  = $this->debut( $annee );
		$fin    = $this->fin( $annee );
		$active = $this->est_active( $jour );

		return new ResultatSaison(
			$jour,
			$active,
			$debut,
			$fin,
			$this->prochaine_ouverture( $jour, $annee ),
			$this->confirmee
		);
	}

	/**
	 * Premier jour actif à venir, à partir du jour demandé inclus.
	 *
	 * Toujours une date, jamais `null` : le consommateur affiche « Reprise le
	 * {date} » sans jamais avoir à gérer une absence. La valeur est monotone —
	 * elle n'est jamais antérieure au jour demandé.
	 *
	 * @param string $jour  Jour civil `YYYY-MM-DD`.
	 * @param int    $annee Année du jour demandé.
	 */
	private function prochaine_ouverture( string $jour, int $annee ): string {
		if ( $this->est_active( $jour ) ) {
			return $jour;
		}

		$debut = $this->debut( $annee );

		if ( Horloge::comparer_jours( $jour, $debut ) < 0 ) {
			return $debut;
		}

		return $this->debut( $annee + 1 );
	}

	/**
	 * Début du dispositif pour une année.
	 *
	 * @param int $annee Année civile.
	 */
	private function debut( int $annee ): string {
		return $this->composer( $annee, $this->debut_mois, $this->debut_jour );
	}

	/**
	 * Fin du dispositif pour une année.
	 *
	 * @param int $annee Année civile.
	 */
	private function fin( int $annee ): string {
		return $this->composer( $annee, $this->fin_mois, $this->fin_jour );
	}

	/**
	 * Compose un jour civil à partir de bornes de configuration.
	 *
	 * @param int $annee Année civile.
	 * @param int $mois  Mois (1-12).
	 * @param int $jour  Jour (1-31).
	 */
	private function composer( int $annee, int $mois, int $jour ): string {
		return sprintf( '%04d-%02d-%02d', $annee, max( 1, min( 12, $mois ) ), max( 1, min( 31, $jour ) ) );
	}
}
