<?php
/**
 * Amorçage du module « rest/public ».
 *
 * Ce fichier ne déclare qu'un hook et le chargement de ses propres fichiers. Il
 * est chargé avant `plugins_loaded` : il ne touche jamais la base de données et
 * ne suppose aucun ordre entre modules. La disponibilité des fonctions de
 * domaine est vérifiée au moment de servir la requête, jamais ici.
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` dans ce module : `public` est un
 * mot-clé réservé de PHP, et l'autoloader de l'extension ne peut résoudre aucun
 * nom de namespace légal vers ce répertoire. Les fonctions sont préfixées
 * `massifs_rest_public_` et chargées par `require_once`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'idempotence : le chargeur ne prend qu'une amorce par module, mais un
// second `require` depuis un test ou un outil ne doit rien redéclarer.
if ( defined( 'MASSIFS_REST_PUBLIC_VERSION' ) ) {
	return;
}

define( 'MASSIFS_REST_PUBLIC_VERSION', '1.0.0' );

require_once __DIR__ . '/charge-statuts.php';
require_once __DIR__ . '/reponse.php';
require_once __DIR__ . '/route-statuts.php';

// Seul hook consommé par ce module. Aucun filtre site-wide n'est enregistré, et
// ce module n'émet ni `do_action` ni `apply_filters` : une route de lecture qui
// émet un hook offre une prise d'écriture à un tiers.
add_action( 'rest_api_init', 'massifs_rest_public_enregistrer_routes' );
