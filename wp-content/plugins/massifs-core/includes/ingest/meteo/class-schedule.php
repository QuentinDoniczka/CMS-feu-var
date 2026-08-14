<?php
/**
 * Planification de la récupération météo.
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
 * Pose et retrait de l'évènement planifié.
 */
final class Schedule {

	/**
	 * Nom du crochet planifié. Propre à ce module.
	 *
	 * AUCUN COUPLAGE AU CRON D'UNE CHAÎNE SŒUR : se brancher sur le crochet du
	 * connecteur préfecture ferait dépendre la météo d'une fenêtre de
	 * publication qui n'est pas la sienne, et rendrait chaque chaîne capable de
	 * casser l'autre en retirant son propre évènement.
	 */
	public const HOOK = 'massifs_meteo_recuperation';

	/*
	 * POURQUOI « hourly », ET SANS AUCUNE FENÊTRE DE PUBLICATION.
	 *
	 * 1. `wp_schedule_event` stocke un horodatage UTC : un créneau fixé une fois
	 *    en heure de Paris dérive au changement d'heure.
	 * 2. WP-Cron n'est pas un vrai planificateur — il se déclenche au passage
	 *    d'un visiteur. Un créneau étroit peut être manqué en entier sur un site
	 *    peu fréquenté.
	 * 3. La récurrence horaire rend la reprise sur échec gratuite, sans aucune
	 *    boucle bloquante dans la requête d'un visiteur.
	 * 4. Surtout : l'heure à laquelle la source publie n'est établie NULLE PART.
	 *    Une fenêtre inventée déclencherait des alertes sur une heure fausse, et
	 *    des alertes fausses apprennent au gestionnaire à les ignorer.
	 *
	 * La récurrence native `hourly` suffit : aucun filtre `cron_schedules` n'est
	 * enregistré, donc aucune récurrence maison à maintenir.
	 */

	/**
	 * Garantit l'état de planification correspondant aux réglages.
	 *
	 * Auto-réparateur : si l'évènement a été perdu, il se repose de lui-même.
	 */
	public static function ensure(): void {
		if ( Settings::is_disabled() || 'manuel' === Settings::mode() ) {
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
