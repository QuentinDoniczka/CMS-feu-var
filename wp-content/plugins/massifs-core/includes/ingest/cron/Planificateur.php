<?php
/**
 * Planification de la veille de fraîcheur.
 *
 * @package Massifs\Ingest\Cron
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pose, retrait et armement de l'évènement planifié.
 */
final class Planificateur {

	/**
	 * Nom du crochet planifié.
	 */
	public const HOOK = 'massifs_veille_fraicheur';

	/*
	 * POURQUOI « hourly », ET NI « daily » NI « twicedaily ».
	 *
	 * 1. `wp_schedule_event` stocke un horodatage UTC. Un créneau fixé une fois
	 *    à une heure de Paris dérive d'une heure au changement d'heure — et
	 *    toute la période d'activité du dispositif est en heure d'été.
	 * 2. WP-Cron n'est pas un planificateur : il se déclenche au passage d'un
	 *    visiteur. Un créneau quotidien étroit peut être manqué en entier sur un
	 *    site peu fréquenté, soit exactement le trou que cette veille referme.
	 * 3. Une récurrence horaire N'ENCODE AUCUNE HEURE. C'est la seule qui
	 *    permette de ne rien trancher sur l'heure de publication, dont ce dépôt
	 *    porte trois valeurs contradictoires. Aucune constante horaire n'existe
	 *    donc dans ce module.
	 * 4. Latence contre le seuil de fraîcheur : « daily » laisserait jusqu'à
	 *    deux fois le seuil sans courriel, « twicedaily » une fois et demie ;
	 *    « hourly » tient le seuil plus une heure.
	 *
	 * La récurrence native suffit : AUCUN filtre `cron_schedules` n'est
	 * enregistré. Ce filtre est site-wide, et un module d'ingestion n'ajoute pas
	 * une récurrence au vocabulaire cron de tout le site.
	 *
	 * Coût dans le cas nominal : un `get_option` sur une option déjà en mémoire,
	 * deux comparaisons de booléens, zéro écriture, zéro requête SQL, zéro octet
	 * réseau.
	 */

	/**
	 * Garantit l'état de planification correspondant à l'armement.
	 *
	 * Idempotent : deux appels successifs ne produisent jamais deux évènements.
	 */
	public static function assurer(): void {
		if ( ! self::est_armee() ) {
			// Ne toucher au cron que s'il y a réellement quelque chose à
			// retirer : le chemin nominal n'écrit aucune option.
			if ( false !== wp_next_scheduled( self::HOOK ) ) {
				self::retirer();
			}

			return;
		}

		// Même idiome qu'au-dessus, et pour la même raison : `wp_next_scheduled()`
		// rend `false` OU un horodatage. Un horodatage de 0 est théoriquement
		// « falsy » sans être `false` ; comparer strictement à `false` aux deux
		// endroits évite qu'un même prédicat s'écrive de deux façons.
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			// Décalage d'une minute : ne jamais faire travailler la requête qui
			// vient d'activer ou de recharger l'extension.
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Retire toute occurrence planifiée du crochet.
	 *
	 * Branché sur la désactivation de l'extension : un évènement `massifs_*`
	 * orphelin ne doit jamais survivre dans le cron d'un site où l'extension est
	 * désactivée.
	 */
	public static function retirer(): void {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * La veille est-elle armée ?
	 *
	 * Aucune option en base, donc aucun écran de réglages, aucune capability et
	 * aucune seconde source de vérité : la constante se pose dans
	 * `wp-config.php`, le filtre dans un mu-plugin.
	 *
	 * Appelée deux fois — à la planification et au déclenchement — pour qu'un
	 * désarmement produise son effet immédiatement, même si l'évènement déjà
	 * planifié survit une heure.
	 */
	public static function est_armee(): bool {
		if ( defined( 'MASSIFS_VEILLE_FRAICHEUR_DESARMEE' ) && MASSIFS_VEILLE_FRAICHEUR_DESARMEE ) {
			return false;
		}

		/**
		 * Filtre l'armement de la veille de fraîcheur.
		 *
		 * Enregistré avant `init` priorité 20, il influence la planification ;
		 * enregistré plus tard, il n'influence que le comportement.
		 *
		 * @param bool $armee La veille est-elle armée ? Par défaut oui.
		 */
		return (bool) apply_filters( 'massifs_veille_fraicheur_armee', true );
	}
}
