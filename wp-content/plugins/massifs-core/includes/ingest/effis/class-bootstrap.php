<?php
/**
 * Amorçage du module « zones parcourues par le feu ».
 *
 * Ce sous-arbre est INERTE par défaut : il ne fait rien tant que
 * `Bootstrap::register()` n'a pas été appelé, et l'appeler n'émet aucune sortie,
 * n'écrit rien et ne touche pas au réseau. Il pose seulement des crochets.
 *
 * L'ORDRE DES ENREGISTREMENTS EST SIGNIFIANT : la route publique est déclarée
 * AVANT le coupe-circuit. Une couche non alimentée reste une couche qui doit
 * répondre, et elle répond `couche_effis_indisponible` en `200` — un état
 * légitime, pas une panne.
 *
 * Aucun crochet d'activation n'est requis : la planification est
 * auto-réparatrice sur `init`. Seule la désactivation est câblée, pour qu'une
 * extension désactivée ne laisse pas d'évènement orphelin dans le cron.
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
 * Enregistrement des crochets du module.
 */
final class Bootstrap {

	/**
	 * Garde d'idempotence : un double `require` ne double pas les crochets.
	 */
	private static bool $registered = false;

	/**
	 * Enregistre le module. Idempotent.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		register_deactivation_hook( self::plugin_file(), array( Schedule::class, 'unschedule' ) );

		// Déclarée dans tous les cas : la route est le seul artefact qui serve
		// les octets depuis notre propre domaine, et elle doit répondre
		// honnêtement même quand rien n'alimente la couche.
		add_action( 'rest_api_init', array( Route::class, 'register' ) );

		if ( Settings::is_disabled() ) {
			// Un profil qui aurait hérité d'un évènement planifié doit devenir
			// réellement inerte, et pas seulement silencieux.
			if ( wp_next_scheduled( Schedule::HOOK ) ) {
				Schedule::unschedule();
			}

			return;
		}

		add_action( 'init', array( Schedule::class, 'ensure' ), 20 );
		add_action( Schedule::HOOK, array( Runner::class, 'run_scheduled' ) );

		// Chargeur tardif : si `init` est déjà passé, le crochet ci-dessus ne se
		// déclenchera jamais pour cette requête.
		if ( did_action( 'init' ) > 0 ) {
			Schedule::ensure();
		}
	}

	/**
	 * Le module a-t-il déjà été enregistré ?
	 */
	public static function is_registered(): bool {
		return self::$registered;
	}

	/**
	 * Fichier principal de l'extension, pour le crochet de désactivation.
	 *
	 * Les deux nommages sont acceptés à dessein, sur le patron du connecteur
	 * préfecture : à défaut, un évènement cron orphelin survivrait à la
	 * désactivation de l'extension.
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
