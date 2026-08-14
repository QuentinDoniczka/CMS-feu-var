<?php
/**
 * Planification de la récupération.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pose et retrait de l'évènement planifié.
 */
final class Schedule {

	/**
	 * Nom du crochet planifié. Le seul de ce module.
	 */
	public const HOOK = 'massifs_effis_recuperation';

	/*
	 * POURQUOI « hourly », ET AUCUN CRÉNEAU CALÉ SUR UNE HEURE DE PUBLICATION.
	 *
	 * 1. Les heures de publication de la source n'ont JAMAIS ÉTÉ RELEVÉES. Caler
	 *    un créneau dessus serait inventer un fait de domaine — l'acte que la
	 *    règle en tête du brief interdit.
	 * 2. WP-Cron n'est pas un vrai planificateur : il se déclenche au passage
	 *    d'un visiteur. Un créneau quotidien étroit peut être manqué en entier
	 *    sur un site peu fréquenté à cette heure-là.
	 * 3. La récurrence horaire EST la politique de reprise du §4.5 du brief, sans
	 *    une seule boucle bloquante dans la requête du visiteur. Les gardes de
	 *    cadence du `Runner` ramènent le trafic réel à quatre appels par jour au
	 *    plus.
	 *
	 * La récurrence native `hourly` suffit : aucun filtre `cron_schedules`, donc
	 * aucune récurrence maison à maintenir.
	 */

	/**
	 * Garantit l'état de planification. Auto-réparateur, appelé sur `init`.
	 */
	public static function ensure(): void {
		if ( Settings::is_disabled() ) {
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
