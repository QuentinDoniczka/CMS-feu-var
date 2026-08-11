<?php
/**
 * Mise en forme française des instants.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Fraicheur;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formate un instant selon MASTER.md §11.1 règle 6.
 *
 * Le formatage d'une date est ici une RÈGLE MÉTIER (fuseau), pas de la
 * présentation : la stack tourne en UTC, `wp_date()`, `date_i18n()` et
 * `current_time()` rendraient l'heure UTC et dépendraient du pack de langue,
 * que `provision.sh` peut échouer à installer hors ligne.
 *
 * Les tables de mois et de jours sont donc en français EN DUR, et le rendu est
 * strict : « mardi 11 août 2026 », « 19 h 04 » avec une espace insécable et
 * jamais de deux-points.
 */
final class Horodatage {

	/**
	 * Espace insécable (U+00A0), imposée par la typographie française de l'heure.
	 */
	private const INSECABLE = "\u{00A0}";

	/**
	 * Noms des mois, indexés par leur numéro.
	 */
	private const MOIS = array(
		1  => 'janvier',
		2  => 'février',
		3  => 'mars',
		4  => 'avril',
		5  => 'mai',
		6  => 'juin',
		7  => 'juillet',
		8  => 'août',
		9  => 'septembre',
		10 => 'octobre',
		11 => 'novembre',
		12 => 'décembre',
	);

	/**
	 * Noms des jours, indexés comme le format `w` de PHP.
	 */
	private const JOURS = array(
		0 => 'dimanche',
		1 => 'lundi',
		2 => 'mardi',
		3 => 'mercredi',
		4 => 'jeudi',
		5 => 'vendredi',
		6 => 'samedi',
	);

	/**
	 * Formate un instant ISO 8601 UTC pour l'affichage, en heure de Paris.
	 *
	 * @param string $instant_iso_utc Instant à formater.
	 *
	 * @return array{iso: string, attr_datetime: string, date_longue: string, heure: string, date_courte: string}
	 *
	 * @throws \InvalidArgumentException Si la chaîne n'est pas un instant valide.
	 */
	public static function formater( string $instant_iso_utc ): array {
		$instant = Horloge::instant_depuis_chaine( $instant_iso_utc );
		$local   = $instant->setTimezone( Horloge::fuseau() );

		$jour_semaine = self::JOURS[ (int) $local->format( 'w' ) ];
		$quantieme    = (int) $local->format( 'j' );
		$mois         = self::MOIS[ (int) $local->format( 'n' ) ];
		$annee        = $local->format( 'Y' );

		// « 1er juin », mais « 2 juin » : règle typographique du français.
		$quantieme_ecrit = 1 === $quantieme ? '1er' : (string) $quantieme;
		$date_courte     = $quantieme_ecrit . ' ' . $mois . ' ' . $annee;

		return array(
			'iso'           => Horloge::vers_iso_utc( $instant ),
			'attr_datetime' => $local->format( Horloge::FORMAT_ISO_DECALAGE ),
			'date_longue'   => $jour_semaine . ' ' . $date_courte,
			// `G` : heure sans zéro initial ; `i` : minutes toujours sur deux chiffres.
			'heure'         => $local->format( 'G' ) . self::INSECABLE . 'h' . self::INSECABLE . $local->format( 'i' ),
			'date_courte'   => $date_courte,
		);
	}
}
