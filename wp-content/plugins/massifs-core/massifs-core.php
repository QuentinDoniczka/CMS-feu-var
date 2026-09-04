<?php
/**
 * Plugin Name: Massifs Core
 * Plugin URI: https://github.com/QuentinDoniczka/CMS-feu-var
 * Description: Extension dédiée — domaine métier (massifs, niveaux, statuts, fraîcheur, dispositif estival), ingestion côté serveur, fonctions de lecture pour le thème et portail sécurisé de mise à jour de l'accès quotidien aux massifs forestiers des Bouches-du-Rhône.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: MASSIFS
 * Author URI: https://github.com/QuentinDoniczka/CMS-feu-var
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: massifs-core
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MASSIFS_CORE_VERSION', '0.2.0' );
define( 'MASSIFS_CORE_FICHIER', __FILE__ );
define( 'MASSIFS_CORE_CHEMIN', plugin_dir_path( __FILE__ ) );
define( 'MASSIFS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'MASSIFS_CORE_INCLUDES', MASSIFS_CORE_CHEMIN . 'includes/' );
define( 'MASSIFS_CORE_SCHEMA_VERSION', '2.1.0' );

// Alias anglais des deux constantes de chemin. Les noms français restent les
// canoniques du projet ; ces deux-là existent parce qu'un module peut les
// attendre sous cette forme, et deux lignes valent mieux qu'un module qui se
// charge en mode dégradé.
define( 'MASSIFS_CORE_DIR', MASSIFS_CORE_CHEMIN );
define( 'MASSIFS_CORE_FILE', MASSIFS_CORE_FICHIER );

/**
 * Autoloader des classes du namespace `Massifs\`.
 *
 * `Massifs\Domain\Statuts\Legende` → `includes/domain/statuts/Legende.php` :
 * les segments de répertoire sont en minuscules, le nom de fichier conserve la
 * casse de la classe.
 *
 * La garde `is_file()` BORNE le mode de panne, elle ne l'annule pas. L'arbre de
 * travail est partagé entre plusieurs chaînes de développement, et une classe
 * `Massifs\` peut être référencée alors que son fichier n'existe pas encore.
 * Sans la garde, `require` émet une erreur fatale NON RATTRAPABLE ; avec elle,
 * PHP lève un `Error: Class not found`, rattrapable et lisible.
 *
 * ELLE NE SUFFIT PAS À FAIRE BOOTER LE SITE. Un `Error` que personne ne rattrape
 * reste une erreur fatale, donc un HTTP 500 servi par `WP_Fatal_Error_Handler`.
 * C'est ce qui est arrivé le 4 septembre 2026 (issue #94) : `domain/fraicheur`
 * nommait en dur `Massifs\Domain\Statuts\Legende`, et masquer `domain/statuts`
 * rendait 500 sur tout le site. Seule l'ABSENCE de référence dure d'un module
 * vers un module frère fait booter le site sans lui — c'est la propriété que
 * `tests/module-absent.sh` mesure, module par module, et la seule raison pour
 * laquelle `domain/massifs`, `ingest/prefecture` et, depuis cette issue,
 * `domain/statuts` passent aujourd'hui.
 */
spl_autoload_register(
	static function ( string $classe ): void {
		$prefixe = 'Massifs\\';

		// Sans ce filtre, l'autoloader serait appelé pour toutes les classes de WordPress.
		if ( ! str_starts_with( $classe, $prefixe ) ) {
			return;
		}

		// EXCLUSION EXPLICITE. Ce sous-arbre suit le nommage WPCS `class-*.php`,
		// délibérément non résolvable en PSR-4, et se charge lui-même depuis son
		// amorce. Tenter de le résoudre ici produirait un chemin qui n'existe pas.
		// C'est un `return`, jamais un `require` : comme la garde `is_file()`
		// ci-dessous, il BORNE le mode de panne sans l'annuler. Répertoire absent,
		// nommer une de ces classes lève un `Error: Class not found` rattrapable
		// et lisible, jamais l'erreur fatale non rattrapable du `require` — mais
		// un `Error` que personne ne rattrape reste un 500, cf. l'issue #94.
		if ( str_starts_with( $classe, 'Massifs\\Ingest\\Prefecture\\' ) ) {
			return;
		}

		$segments = explode( '\\', substr( $classe, strlen( $prefixe ) ) );
		$nom      = array_pop( $segments );

		if ( '' === $nom ) {
			return;
		}

		$dossiers = array() === $segments ? '' : strtolower( implode( '/', $segments ) ) . '/';
		$chemin   = MASSIFS_CORE_INCLUDES . $dossiers . $nom . '.php';

		if ( is_file( $chemin ) ) {
			require_once $chemin;
		}
	}
);

/**
 * Charge les modules de l'extension par convention de chemin.
 *
 * POURQUOI CE MÉCANISME, ET PAS UN `require` NOMMÉ EN DUR
 *
 * Les modules sont écrits par des chaînes de développement parallèles qui
 * partagent le même arbre de travail : `includes/ingest/prefecture/`,
 * `includes/domain/massifs/`, `includes/domain/statuts/`… Ce fichier ne nomme
 * aucun d'entre eux : il ne serait pas modifiable par deux chaînes à la fois
 * sans écrasement mutuel. La stack doit donc booter même si le répertoire d'un
 * module est absent, vide ou incomplet — d'où les gardes `is_dir()` et
 * `is_file()`.
 *
 * POURQUOI PAS UN `glob` RÉCURSIF QUI CHARGERAIT TOUT
 *
 * Un `glob` récursif finirait par inclure un fichier à moitié écrit par une
 * chaîne sœur. Or un `ParseError` de fichier inclus N'EST PAS rattrapable par
 * `try/catch` : le résultat serait un écran blanc sur tout le site, pour les
 * trois chaînes à la fois. On ne charge donc que des chemins PRÉDITS par
 * module : `<couche>/<module>/module.php`, ou à défaut
 * `<couche>/<module>/bootstrap.php`.
 *
 * POURQUOI DEUX NOMS D'AMORCE
 *
 * Les deux noms sont d'usage courant pour la même intention, et un module qui
 * choisit le second ne doit pas se retrouver chargé par personne. Essayer les
 * deux généralise la convention sans nommer aucun module en dur : l'interdit de
 * `require` nommé tient, et l'absence du répertoire reste sans effet. Un seul
 * fichier est chargé — le premier trouvé — pour qu'un module ne puisse pas
 * s'amorcer deux fois.
 *
 * CONTRAT D'UNE AMORCE DE MODULE
 *
 * Elle ne déclare QUE des hooks et des `require_once` de ses propres fichiers.
 * Elle est chargée avant `plugins_loaded`, ne doit jamais toucher la base de
 * données, ni supposer un ordre de chargement entre modules.
 */
function massifs_core_charger_modules(): void {
	// Liste fixe et ordonnée : la sécurité s'arme avant le domaine, qui précède
	// l'ingestion, l'API et l'administration.
	$couches = array( 'security', 'domain', 'ingest', 'rest', 'admin' );

	// Ordre de préférence, jamais cumulatif.
	$amorces = array( 'module.php', 'bootstrap.php' );

	foreach ( $couches as $couche ) {
		$racine = MASSIFS_CORE_INCLUDES . $couche;

		if ( ! is_dir( $racine ) ) {
			continue;
		}

		// `scandir` donne un ordre alphabétique déterministe, contrairement à `readdir`.
		$entrees = scandir( $racine );

		if ( false === $entrees ) {
			continue;
		}

		foreach ( $entrees as $entree ) {
			if ( '.' === $entree || '..' === $entree ) {
				continue;
			}

			$module = $racine . '/' . $entree;

			if ( ! is_dir( $module ) ) {
				continue;
			}

			foreach ( $amorces as $nom ) {
				$amorce = $module . '/' . $nom;

				if ( is_file( $amorce ) ) {
					require_once $amorce;
					break;
				}
			}
		}
	}
}

massifs_core_charger_modules();

/**
 * Vérifie et rejoue l'installation du schéma quand la signature a changé.
 *
 * Chaque module déclare sa propre version de schéma via le filtre
 * `massifs_core_signature_schema` ; une chaîne sœur peut donc forcer le rejeu de
 * l'installation de SA table sans modifier ce fichier.
 *
 * Les handlers de `massifs_core_installation` doivent être idempotents : ils
 * sont rejoués à chaque changement de signature, quelle que soit son origine.
 * Aucun verrou de concurrence n'est nécessaire — `dbDelta` est idempotent et un
 * `CREATE TABLE` concurrent est sans effet destructeur.
 */
function massifs_core_verifier_installation(): void {
	$signatures = apply_filters( 'massifs_core_signature_schema', array() );
	$signature  = MASSIFS_CORE_SCHEMA_VERSION . ':' . md5( (string) wp_json_encode( $signatures ) );

	if ( get_option( 'massifs_schema_version' ) === $signature ) {
		return;
	}

	do_action( 'massifs_core_installation', (string) get_option( 'massifs_schema_version', '' ) );

	// Après l'action seulement : un échec d'installation doit pouvoir être rejoué.
	update_option( 'massifs_schema_version', $signature, true );
}

// `register_activation_hook` ne suffit pas : la stack Docker a déjà activé
// l'extension, le hook d'activation ne se redéclenchera donc jamais et la table
// ne serait jamais créée. La vérification sur `plugins_loaded` couvre les deux cas.
add_action( 'plugins_loaded', 'massifs_core_verifier_installation', 5 );
register_activation_hook( MASSIFS_CORE_FICHIER, 'massifs_core_verifier_installation' );

do_action( 'massifs_core_amorcage' );
