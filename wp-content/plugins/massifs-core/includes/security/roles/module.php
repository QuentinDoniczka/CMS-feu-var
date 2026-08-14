<?php
/**
 * Amorçage du module « rôles du portail ».
 *
 * Ce fichier ne déclare QUE des crochets, plus le chargement de ses propres
 * fonctions procédurales. Il ne touche jamais la base de données et ne suppose aucun
 * ordre entre modules : `Capacites::class` et consorts sont résolus à la compilation,
 * les nommer ici ne déclenche aucun chargement.
 *
 * ORDRE DE CHARGEMENT, FAIT VÉRIFIÉ : `scandir` parcourt `security/`
 * alphabétiquement — `alertes`, puis `auth`, puis `roles`. Cette amorce est donc
 * chargée APRÈS celle de `auth`. Aucune des deux ne lit l'autre au chargement ; toute
 * lecture croisée se fait dans un rappel de crochet, donc à l'exécution, sous
 * `class_exists`.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Security\Roles\Comptes;
use Massifs\Security\Roles\EcranComptes;
use Massifs\Security\Roles\Installation;
use Massifs\Security\Roles\JournalComptes;
use Massifs\Security\Roles\Suspension;

// Installation du rôle par le mécanisme de signature déjà en service : idempotente,
// et REJOUÉE dès que `Installation::VERSION` change. C'est ce qui annule la dérive
// des capacités persistées.
add_filter( 'massifs_core_signature_schema', array( Installation::class, 'signature' ), 10, 1 );
add_action( 'massifs_core_installation', array( Installation::class, 'installer' ), 10, 1 );

// Résolveur de suspension. C'est lui, et lui seul, qui rend une suspension
// incontournable : aucun chemin d'écriture, présent ou futur, ne peut lui échapper.
add_filter( 'user_has_cap', array( Suspension::class, 'filtrer_capacites' ), 10, 4 );

// Protection des comptes gestionnaires contre la suppression (interdit 6).
add_filter( 'map_meta_cap', array( Comptes::class, 'proteger_meta_caps' ), 10, 4 );

// Sorties de session sur changement de privilèges et sur réinitialisation aboutie.
add_action( 'set_user_role', array( Comptes::class, 'sur_changement_de_role' ), 10, 3 );
add_action( 'after_password_reset', array( Comptes::class, 'sur_reinitialisation' ), 10, 2 );

// Journal de repli : abonné à notre propre action, exactement comme le serait une
// chaîne sœur. Un évènement absent du registre est donc un évènement jamais émis.
add_action( 'massifs_compte_evenement', array( JournalComptes::class, 'enregistrer' ), 10, 1 );

// Greffes sur l'écran « Utilisateurs » du cœur. Enregistrées seulement côté
// administration : ces filtres n'ont aucun sens sur une page publique, et le §12 du
// brief interdit d'alourdir le rendu public d'un octet inutile.
if ( is_admin() ) {
	add_filter( 'manage_users_columns', array( EcranComptes::class, 'colonnes' ), 10, 1 );
	add_filter( 'manage_users_custom_column', array( EcranComptes::class, 'colonne' ), 10, 3 );
	add_filter( 'user_row_actions', array( EcranComptes::class, 'actions_ligne' ), 10, 2 );
	add_filter( 'bulk_actions-users', array( EcranComptes::class, 'actions_groupees' ), 10, 1 );
	add_filter( 'handle_bulk_actions-users', array( EcranComptes::class, 'traiter_actions_groupees' ), 10, 3 );
	add_action( 'admin_init', array( EcranComptes::class, 'traiter_actions' ), 10, 0 );
	add_action( 'admin_notices', array( EcranComptes::class, 'notices' ), 10, 0 );
}

require_once __DIR__ . '/api.php';
