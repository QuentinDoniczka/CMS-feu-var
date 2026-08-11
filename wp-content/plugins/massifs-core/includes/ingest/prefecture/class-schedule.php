<?php
/**
 * Planification de la récupération.
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
 * Pose et retrait de l'évènement planifié.
 */
final class Schedule {

	/**
	 * Nom du crochet planifié.
	 */
	public const HOOK = 'massifs_prefecture_recuperation';

	/*
	 * POURQUOI « hourly », ET PAS UN CRÉNEAU QUOTIDIEN À 18 H.
	 *
	 * 1. `wp_schedule_event` stocke un horodatage UTC. Un créneau fixé une fois
	 *    à 18 h de Paris dérive d'une heure au changement d'heure — et toute la
	 *    saison du dispositif (1er juin au 30 septembre) est en heure d'été.
	 * 2. WP-Cron n'est pas un vrai planificateur : il se déclenche au passage
	 *    d'un visiteur. Un créneau quotidien étroit peut être manqué en entier
	 *    sur un site peu fréquenté à cette heure-là, et le statut du lendemain
	 *    ne serait jamais récupéré.
	 * 3. La récurrence horaire rend la reprise sur échec (§4.5) gratuite, sans
	 *    aucune boucle bloquante dans la requête du visiteur.
	 *
	 * La récurrence native `hourly` suffit : aucun filtre `cron_schedules` n'est
	 * enregistré, donc aucune récurrence maison à maintenir.
	 */

	/**
	 * Garantit l'état de planification correspondant aux réglages.
	 */
	public static function ensure(): void {
		if ( 'manuel' === Settings::mode() || Settings::is_disabled() ) {
			self::unschedule();

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Décalage d'une minute : ne jamais lancer un appel sortant dans la
			// requête qui vient d'activer ou de reconfigurer l'extension.
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Retire toute occurrence planifiée du crochet.
	 */
	public static function unschedule(): void {
		wp_unschedule_hook( self::HOOK );
	}
}
