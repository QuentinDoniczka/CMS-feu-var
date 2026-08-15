<?php
/**
 * Amorçage du module « rest/portail/publication ».
 *
 * INVERSION DE COUCHE ASSUMÉE, ET SA CONDITION DE SORTIE
 *
 * Ce fichier n'est PAS découvert par le chargeur de l'extension : celui-ci ne
 * descend que d'un niveau et ne connaît que `includes/rest/<module>/module.php`.
 * Il est donc chargé par `includes/admin/ecran-publication/module.php`, sous garde
 * `is_file()`. Le fichier de niveau `includes/rest/portail/module.php`, qui serait
 * le chemin naturel, est partagé avec d'autres chaînes et n'appartient à personne
 * ici.
 *
 * Le jour où `includes/rest/portail/module.php` existe et parcourt ses
 * sous-répertoires, ce `require_once` amont devient redondant et se retire : la
 * garde d'idempotence ci-dessous rend la transition sans risque.
 *
 * Le chargement de la couche `admin` est inconditionnel — le chargeur ne teste pas
 * `is_admin()` — et `rest_api_init` se déclenche bien après : la route est donc
 * enregistrée y compris sur une requête REST.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'idempotence : ce module peut être atteint par deux chemins pendant la
// transition décrite ci-dessus, et rien ne doit être redéclaré deux fois.
if ( defined( 'MASSIFS_REST_PORTAIL_PUBLICATION_VERSION' ) ) {
	return;
}

define( 'MASSIFS_REST_PORTAIL_PUBLICATION_VERSION', '1.0.0' );

require_once __DIR__ . '/route-publication.php';

// Seul hook consommé par ce module. Aucun filtre site-wide, aucun
// `rest_authentication_errors`, aucun `do_action` ni `apply_filters` émis.
add_action( 'rest_api_init', 'massifs_rest_portail_publication_enregistrer_routes' );
