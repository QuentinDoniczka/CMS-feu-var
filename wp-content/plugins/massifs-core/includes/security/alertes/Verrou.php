<?php
/**
 * Verrou d'alerte : au plus une alerte par type, par source et par jour.
 *
 * POURQUOI UNE OPTION ET PAS UN TRANSIENT
 *
 * Un transient est évincible à tout moment par un cache objet sous pression, et
 * une éviction vaut un courriel en double. L'option ne l'est pas.
 *
 * POURQUOI `autoload = false`
 *
 * Ce registre n'est lu que depuis un rappel cron, jamais au rendu d'une page :
 * le charger à chaque requête coûterait sans rien servir.
 *
 * @package Massifs\Security\Alertes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Alertes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registre des alertes déjà émises.
 *
 * Le verrou protège contre la RÉPÉTITION, pas contre l'échec : sa valeur ne
 * dépend pas de la délivrance du courriel.
 */
final class Verrou {

	/**
	 * Option de stockage du registre.
	 */
	public const OPTION = 'massifs_alertes_verrous';

	/**
	 * Nombre maximal d'entrées conservées.
	 *
	 * Le bornage se fait sur l'ORDRE D'INSERTION, jamais sur un calcul de date :
	 * un registre de verrous n'a pas à savoir lire une date pour rester borné.
	 */
	private const MAX_ENTREES = 30;

	/**
	 * Longueur maximale d'un fragment de clé.
	 */
	private const LONGUEUR_FRAGMENT = 32;

	/**
	 * Compose une clé de verrou.
	 *
	 * Granularité `{type}:{source}:{jour_validite}` — une alerte par source et
	 * par jour de validité. Par jour, et non « une fois jusqu'à résolution » :
	 * une péremption qui dure trois jours mérite trois rappels. Jamais par
	 * heure : la récurrence de la veille est horaire, et 24 envois par jour
	 * noieraient la boîte du gestionnaire, donc seraient ignorés le jour où ils
	 * comptent.
	 *
	 * @param string $type          Type d'alerte.
	 * @param string $source        Clé de la source concernée.
	 * @param string $jour_validite Jour de validité `YYYY-MM-DD`.
	 */
	public static function cle( string $type, string $source, string $jour_validite ): string {
		return self::fragment( $type ) . ':' . self::fragment( $source ) . ':' . self::fragment( $jour_validite );
	}

	/**
	 * Une alerte a-t-elle déjà été émise pour cette clé ?
	 *
	 * @param string $cle Clé de verrou.
	 */
	public static function est_pose( string $cle ): bool {
		return array_key_exists( $cle, self::registre() );
	}

	/**
	 * Pose le verrou.
	 *
	 * L'instant est fourni par l'appelant — il provient de l'évaluation qui a
	 * motivé l'alerte, jamais d'une horloge propre à ce module.
	 *
	 * @param string $cle             Clé de verrou.
	 * @param string $instant_iso_utc Instant de pose, ISO 8601 UTC.
	 */
	public static function poser( string $cle, string $instant_iso_utc ): void {
		$registre = self::registre();

		// Réinsertion en fin de tableau : l'ordre d'insertion porte à lui seul
		// l'ancienneté, ce qui rend le bornage ci-dessous exact sans lire une
		// seule date.
		unset( $registre[ $cle ] );
		$registre[ $cle ] = trim( $instant_iso_utc );

		$registre = array_slice( $registre, -self::MAX_ENTREES, null, true );

		update_option( self::OPTION, $registre, false );
	}

	/**
	 * Registre assaini.
	 *
	 * @return array<string, string>
	 */
	private static function registre(): array {
		$brut = get_option( self::OPTION, array() );

		if ( ! is_array( $brut ) ) {
			return array();
		}

		$propre = array();

		foreach ( $brut as $cle => $instant ) {
			if ( ! is_string( $cle ) || '' === $cle || ! is_scalar( $instant ) ) {
				continue;
			}

			$propre[ $cle ] = (string) $instant;
		}

		return $propre;
	}

	/**
	 * Normalise un fragment de clé.
	 *
	 * Une clé de verrou est persistée, puis recomparée à l'identique d'une
	 * exécution à l'autre : chaque fragment est borné en longueur et réduit à un
	 * alphabet sûr, quelle que soit la valeur reçue.
	 *
	 * @param string $valeur Fragment brut.
	 */
	private static function fragment( string $valeur ): string {
		$propre = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( trim( $valeur ) ) );

		return '' === $propre ? 'inconnu' : substr( $propre, 0, self::LONGUEUR_FRAGMENT );
	}
}
