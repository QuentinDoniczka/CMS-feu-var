<?php
/**
 * Amorçage du connecteur préfecture.
 *
 * Ce sous-arbre est INERTE par défaut : il ne fait rien tant que
 * `Bootstrap::register()` n'a pas été appelé, et l'appeler n'émet aucune
 * sortie, n'écrit rien et ne touche pas au réseau. Il pose seulement des
 * crochets.
 *
 * Aucun crochet d'activation n'est requis : la planification est
 * auto-réparatrice sur `init`. Seule la désactivation est câblée, pour qu'une
 * extension désactivée ne laisse pas d'évènement orphelin dans le cron.
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
 * Enregistrement des crochets du connecteur.
 */
final class Bootstrap {

	/**
	 * Garde d'idempotence : un double `require` ne double pas les crochets.
	 */
	private static bool $registered = false;

	/**
	 * Enregistre le connecteur. Idempotent.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		register_deactivation_hook( self::plugin_file(), array( Schedule::class, 'unschedule' ) );

		/*
		 * LE RÉCEPTEUR DE BILANS EST BRANCHÉ AVANT LE COUPE-CIRCUIT, ET C'EST
		 * VOULU.
		 *
		 * Il est purement passif : il n'émet rien, n'appelle rien, et ne
		 * s'exécute que si le domaine publie un bilan de projection — ce qui
		 * suppose qu'un instantané a été publié, donc qu'un connecteur armé l'a
		 * enregistré. Le brancher sous le coupe-circuit ne protégerait de rien
		 * et le rendrait absent dans le seul cas où il compte : celui d'un
		 * connecteur armé APRÈS l'enregistrement des crochets (redéfinition
		 * tardive du modèle d'URL, profil de recette).
		 */
		add_action( 'massifs_projection_prefecture', array( ProjectionListener::class, 'capter' ) );

		if ( Settings::is_disabled() ) {
			// Un profil de test qui aurait hérité d'un évènement planifié doit
			// devenir réellement inerte, et pas seulement silencieux.
			// `wp_next_scheduled` lit le cache d'options : dans le cas nominal,
			// aucune écriture n'a lieu à l'inclusion.
			if ( wp_next_scheduled( Schedule::HOOK ) ) {
				Schedule::unschedule();
			}

			return;
		}

		add_action( 'init', array( Schedule::class, 'ensure' ), 20 );
		add_action( Schedule::HOOK, array( Runner::class, 'run_scheduled' ) );

		// Chargeur tardif : si `init` est déjà passé, le crochet ci-dessus ne
		// se déclenchera jamais pour cette requête.
		if ( did_action( 'init' ) > 0 ) {
			Schedule::ensure();
		}
	}

	/**
	 * Le connecteur a-t-il déjà été enregistré ?
	 */
	public static function is_registered(): bool {
		return self::$registered;
	}

	/**
	 * Fichier principal de l'extension, pour le crochet de désactivation.
	 *
	 * Les deux nommages sont acceptés à dessein : le chargeur de l'extension
	 * définit `MASSIFS_CORE_FICHIER` (nommage français retenu par la chaîne qui
	 * possède l'amorçage), tandis que `MASSIFS_CORE_FILE` était le nom annoncé
	 * dans le contrat initial. Accepter les deux évite que le crochet de
	 * désactivation tombe silencieusement sur le repli calculé — un événement
	 * cron orphelin survivrait alors à la désactivation de l'extension.
	 *
	 * À défaut, le chemin est déduit de la position de ce fichier :
	 * `includes/ingest/prefecture/` → trois niveaux au-dessus.
	 */
	private static function plugin_file(): string {
		if ( defined( 'MASSIFS_CORE_FICHIER' ) ) {
			return (string) MASSIFS_CORE_FICHIER;
		}

		if ( defined( 'MASSIFS_CORE_FILE' ) ) {
			return (string) MASSIFS_CORE_FILE;
		}

		return dirname( __DIR__, 3 ) . '/massifs-core.php';
	}
}
