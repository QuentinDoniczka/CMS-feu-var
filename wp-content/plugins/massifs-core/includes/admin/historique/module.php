<?php
/**
 * Amorçage du module « admin/historique ».
 *
 * CE FICHIER EST CHARGÉ À CHAQUE REQUÊTE, Y COMPRIS PUBLIQUE. Il ne charge donc
 * inconditionnellement que l'analyseur de filtres — des fonctions pures, sans
 * effet — et le module REST, qui ne fait que s'abonner à `rest_api_init`. Tout
 * le reste est derrière `is_admin()`. Aucune requête en base, aucune lecture de
 * `$_GET` au chargement.
 *
 * IL CHARGE LE MODULE REST, ET C'EST DÉLIBÉRÉ. Le chargeur de l'extension
 * n'auto-découvre `includes/<couche>/<module>/module.php` qu'à UN niveau de
 * profondeur ; `includes/rest/portail/historique/` est à deux, et
 * `includes/rest/portail/module.php` appartient à une chaîne sœur qui écrit
 * dans le même arbre de travail. Un `require_once` depuis ici ne touche AUCUN
 * fichier partagé, et l'amorce REST est idempotente. La petite odeur de couche
 * vaut mieux qu'un écrasement mutuel entre deux chaînes parallèles.
 *
 * Ce module ne déclare QUE des hooks et le chargement de ses propres fichiers.
 * Il n'émet ni `do_action` ni `apply_filters` : un écran d'audit qui offrirait
 * un filtre offrirait une prise pour altérer un journal.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MASSIFS_ADMIN_HISTORIQUE_VERSION' ) ) {
	return;
}

define( 'MASSIFS_ADMIN_HISTORIQUE_VERSION', '1.0.0' );

require_once __DIR__ . '/filtres.php';
require_once MASSIFS_CORE_INCLUDES . 'rest/portail/historique/module.php';

if ( ! function_exists( 'massifs_historique_charger_lecture' ) ) {
	/**
	 * Charge le vocabulaire et l'adaptateur de présentation.
	 *
	 * Partagés par l'écran, l'export et la route REST. Chargés à la demande :
	 * une page publique n'a aucune raison de les avoir en mémoire, et la route
	 * REST n'est pas en `is_admin()`.
	 */
	function massifs_historique_charger_lecture(): void {
		require_once __DIR__ . '/vocabulaire.php';
		require_once __DIR__ . '/donnees.php';
	}
}

// La route REST vit hors de `is_admin()` : elle charge donc sa lecture par ce
// crochet, jamais au chargement du module.
add_action( 'rest_api_init', 'massifs_historique_charger_lecture', 5 );

if ( ! is_admin() ) {
	return;
}

massifs_historique_charger_lecture();

require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/ecran.php';
require_once __DIR__ . '/export-csv.php';

// Priorité 99 : trois chaînes veulent une entrée « MASSIFS » sans se voir, et
// celle-ci ne crée le parent que si personne ne l'a fait avant.
add_action( 'admin_menu', 'massifs_historique_enregistrer_menu', 99 );

// AUCUNE VARIANTE `nopriv` : un export du journal ne doit pas même avoir de
// porte anonyme à refuser.
add_action( 'admin_post_' . MASSIFS_HISTORIQUE_ACTION_EXPORT, 'massifs_historique_exporter' );
