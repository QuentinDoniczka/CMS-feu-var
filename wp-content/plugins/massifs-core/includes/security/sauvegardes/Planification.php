<?php
/**
 * Armement et désarmement de l'évènement cron de sauvegarde.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  DÉSARMÉ PAR DÉFAUT, ET CE N'EST PAS DE LA PRUDENCE MAL PLACÉE.               │
 * │                                                                               │
 * │  `DISABLE_WP_CRON` VAUT `true` SUR LES DEUX SERVICES DU PROJET. UN ÉVÈNEMENT  │
 * │  PLANIFIÉ Y SERAIT INSCRIT ET JAMAIS EXÉCUTÉ — ET `wp cron event list`        │
 * │  L'AFFICHERAIT, CE QUI SE LIT « LES SAUVEGARDES TOURNENT ». UNE SAUVEGARDE    │
 * │  QU'ON CROIT AVOIR EST PIRE QUE PAS DE SAUVEGARDE DU TOUT : ON N'EN CHERCHE   │
 * │  PAS D'AUTRE.                                                                 │
 * │                                                                               │
 * │  LE GESTIONNAIRE EST DONC BRANCHÉ, L'ÉVÈNEMENT N'EST PAS PLANIFIÉ. LA         │
 * │  PÉRIODICITÉ DU §9 SE TIENT PAR UN DÉCLENCHEUR HÔTE APPELANT                  │
 * │  `wp massifs sauvegarde creer` (COUTURE S-8).                                 │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cycle de vie de l'évènement planifié.
 */
final class Planification {

	/**
	 * Nom du crochet planifié.
	 */
	public const HOOK = 'massifs_sauvegarde_quotidienne';

	/**
	 * Aligne l'état du cron sur l'armement.
	 *
	 * Idempotent : deux appels successifs ne produisent jamais deux évènements.
	 * Le chemin nominal — désarmé, rien de planifié — n'écrit rien du tout.
	 */
	public static function synchroniser(): void {
		$planifie = wp_next_scheduled( self::HOOK );

		if ( ! Reglages::planification_active() ) {
			if ( false !== $planifie ) {
				self::retirer();
			}

			return;
		}

		if ( false !== $planifie ) {
			return;
		}

		wp_schedule_event( self::prochain_creneau(), 'daily', self::HOOK );
	}

	/**
	 * Retire toute occurrence planifiée.
	 */
	public static function retirer(): void {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Gestionnaire du crochet planifié.
	 *
	 * L'armement est réévalué ICI aussi : un désarmement doit produire son effet
	 * immédiatement, même si l'évènement déjà planifié survit jusqu'à sa prochaine
	 * échéance.
	 */
	public static function executer(): void {
		if ( ! Reglages::planification_active() ) {
			return;
		}

		Archives::creer();
	}

	/**
	 * Prochain créneau, exprimé en horodatage UTC.
	 *
	 * `wp_schedule_event` stocke un horodatage absolu : l'heure locale est
	 * convertie une fois, ici, avec le fuseau du site.
	 */
	public static function prochain_creneau(): int {
		$fuseau     = wp_timezone();
		$maintenant = new DateTimeImmutable( 'now', $fuseau );
		$creneau    = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$maintenant->format( 'Y-m-d' ) . ' ' . Reglages::heure_planifiee() . ':00',
			$fuseau
		);

		if ( false === $creneau ) {
			return $maintenant->getTimestamp() + DAY_IN_SECONDS;
		}

		if ( $creneau->getTimestamp() <= $maintenant->getTimestamp() ) {
			$creneau = $creneau->modify( '+1 day' );
		}

		return $creneau->getTimestamp();
	}
}
