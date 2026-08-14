<?php
/**
 * Amorçage du module « rest/portail/historique ».
 *
 * CE FICHIER N'EST PAS AUTO-DÉCOUVERT. Le chargeur de l'extension ne prédit que
 * `includes/<couche>/<module>/module.php`, à UN niveau de profondeur ; ce
 * module est à deux, et `includes/rest/portail/module.php` appartient à une
 * chaîne sœur qui écrit dans le même arbre de travail. Il est donc chargé par
 * `require_once` depuis `includes/admin/historique/module.php`, et son
 * enregistrement est idempotent — si une amorce `rest/portail/` apparaît un
 * jour et le charge aussi, rien n'est déclaré deux fois.
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` : `public` et les répertoires
 * voisins suivent la même convention que `rest/public`, dont l'autoloader ne
 * peut résoudre aucun nom de namespace légal.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MASSIFS_REST_PORTAIL_HISTORIQUE_VERSION' ) ) {
	return;
}

define( 'MASSIFS_REST_PORTAIL_HISTORIQUE_VERSION', '1.0.0' );

require_once __DIR__ . '/route.php';

// Seul hook consommé par ce module. Il n'émet ni `do_action` ni
// `apply_filters` : un journal qui offrirait un filtre offrirait une prise pour
// l'altérer.
add_action( 'rest_api_init', 'massifs_rest_portail_historique_enregistrer_routes' );
