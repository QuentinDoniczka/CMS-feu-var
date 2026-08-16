<?php
/**
 * Amorçage du module « durcissement ».
 *
 * Ce fichier ne déclare QUE des crochets, plus le chargement de ses propres
 * fichiers procéduraux. Il ne touche jamais la base de données et ne suppose aucun
 * ordre entre modules : les références `::class` sont résolues à la compilation et
 * ne déclenchent aucun chargement.
 *
 * ORDRE DE CHARGEMENT, FAIT VÉRIFIÉ : `scandir` parcourt `security/`
 * alphabétiquement — `alertes`, `auth`, puis `durcissement`, puis `roles`. Cette
 * amorce est donc chargée AVANT celle des rôles, qui abonne elle aussi
 * `map_meta_cap` en priorité 10. Les deux jeux de capacités sont DISJOINTS
 * (`edit_files`/`edit_plugins`/`edit_themes` ici, `delete_user`/`remove_user`
 * là-bas) et les deux rappels rendent `$caps` inchangé hors de leur périmètre :
 * l'ordre entre eux est donc sans effet, et ce n'est pas un hasard.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  CE QUI EST VOLONTAIREMENT NON POSÉ ICI, ET NE DOIT PAS ÊTRE « COMPLÉTÉ »     │
 * │                                                                               │
 * │  • `DISALLOW_FILE_MODS` (A-6) — désactiverait `WP_Automatic_Updater` en       │
 * │    entier et tuerait les mises à jour mineures automatiques exigées par la    │
 * │    MÊME issue. Contradiction interne, tranchée : non posé.                    │
 * │  • `automatic_updater_disabled` (A-8) — le forcer à `false` écraserait le     │
 * │    coupe-circuit d'un exploitant. La preuve opposable de la politique est     │
 * │    `massifs_durcissement_politique_mises_a_jour()`, pas un forçage.           │
 * │  • `rest_authentication_errors`, ET TOUT FILTRE GLOBAL D'AUTHENTIFICATION     │
 * │    REST — INTERDIT ABSOLU. Voir l'encadré en tête de `auth/GardeRest.php` :   │
 * │    il court-circuite `WP_REST_Server::dispatch` pour TOUTE l'API et           │
 * │    renverrait 401 sur `GET /massifs/v1/statuts`, cassant le §5.4 du brief.    │
 * │    L'énumération se ferme par `rest_endpoints`, route par route.              │
 * │  • `X-Robots-Tag` et toute directive d'indexation — propriété de              │
 * │    `seo-meta.php`, chaîne sœur #18.                                           │
 * │  • `Cross-Origin-Resource-Policy` (A-9) — fermerait l'open data du §5.4 à     │
 * │    toute origine.                                                             │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Security\Durcissement\EditionCode;
use Massifs\Security\Durcissement\Entetes;
use Massifs\Security\Durcissement\Enumeration;
use Massifs\Security\Durcissement\MisesAJour;

// EN PREMIÈRE LIGNE : `DISALLOW_FILE_EDIT` doit exister avant la plus précoce des
// évaluations de `map_meta_cap`, qui survient au plus tôt sur `admin_menu`.
require_once __DIR__ . '/constantes.php';

// Doublon défensif de la constante : il reste vrai le jour où `wp-config.php`
// définit `DISALLOW_FILE_EDIT` à `false`.
add_filter( 'map_meta_cap', array( EditionCode::class, 'interdire_edition_de_code' ), 10, 4 );

// `send_headers` n'est appelé que depuis `WP::main()` : il ne se déclenche NI dans
// `wp-admin`, NI sur `wp-login.php`, NI en REST. Le périmètre « front public » est
// donc obtenu par construction, pas par une liste de conditions à maintenir.
add_action( 'send_headers', array( Entetes::class, 'poser' ), 10, 1 );

// Se déclenche après résolution de la session : `is_user_logged_in()` y est fiable.
add_filter( 'rest_endpoints', array( Enumeration::class, 'retirer_routes_utilisateurs' ), 10, 1 );

// PRIORITÉ 1, PORTEUSE ET NON DÉCORATIVE. La fuite de `?author=N` n'est pas dans le
// corps de la page : c'est `redirect_canonical` (sur `template_redirect`, priorité
// 10) qui émet un 301 avec `Location: /author/<identifiant-de-connexion>/`.
// Intervenir avant lui ferme la surface ; intervenir après ne ferme rien, et un
// test qui ne lit que le HTML final ne verrait aucune différence.
add_action( 'parse_request', array( Enumeration::class, 'neutraliser_auteur' ), 1, 1 );

// Ceinture pour le permalien joli, elle aussi AVANT `redirect_canonical`.
add_action( 'template_redirect', array( Enumeration::class, 'couper_archive_auteur' ), 0, 0 );

add_filter( 'wp_sitemaps_add_provider', array( Enumeration::class, 'retirer_fournisseur_utilisateurs' ), 10, 2 );
add_filter( 'oembed_response_data', array( Enumeration::class, 'depouiller_oembed' ), 10, 2 );

// Découverte oEmbed dans le `<head>` public : elle publie un point d'entrée dont
// nous venons de retirer l'auteur, et n'a aucun usage sur ce site sans commentaire
// ni contenu embarquable. Retirée ici et non dans le thème — décision de sécurité,
// pas de présentation, exactement comme le `rsd_link` de `auth/module.php` l. 127.
//
// INCONDITIONNEL, ET DONC HORS DE `massifs_durcissement_fermer_enumeration` : le
// geste est posé à l'amorce, où la session n'est pas résolue et où lire un réglage
// n'aurait pas de sens. Poser ce réglage à `false` pour diagnostiquer un problème
// ne ramènera PAS cette annonce — c'est écrit ici pour qu'on ne cherche pas la
// cause ailleurs. Même statut que le retrait du fournisseur de plan de site
// `users`, inconditionnel lui aussi (A-4).
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

add_filter( 'allow_minor_auto_core_updates', array( MisesAJour::class, 'mineures' ), 10, 1 );
add_filter( 'allow_major_auto_core_updates', array( MisesAJour::class, 'majeures' ), 10, 1 );
add_filter( 'auto_update_plugin', array( MisesAJour::class, 'extensions' ), 10, 2 );
add_filter( 'auto_update_theme', array( MisesAJour::class, 'themes' ), 10, 2 );

// Sans rapport par courriel, un échec de mise à jour mineure est SILENCIEUX et la
// promesse du §9 devient invérifiable.
add_filter( 'auto_core_update_send_email', array( MisesAJour::class, 'courriel_de_rapport' ), 10, 4 );

require_once __DIR__ . '/api.php';
