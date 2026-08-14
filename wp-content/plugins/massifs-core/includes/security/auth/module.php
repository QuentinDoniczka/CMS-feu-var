<?php
/**
 * Amorçage du module « authentification renforcée ».
 *
 * Ce fichier ne déclare QUE des crochets, plus le chargement de ses propres fonctions
 * procédurales. Il ne touche jamais la base de données et ne suppose aucun ordre entre
 * modules : les références `::class` sont résolues à la compilation et ne déclenchent
 * aucun chargement.
 *
 * ORDRE DE CHARGEMENT, FAIT VÉRIFIÉ : `scandir` parcourt `security/`
 * alphabétiquement — `alertes`, puis `auth`, puis `roles`. Cette amorce est donc
 * chargée AVANT celle des rôles. Aucun de ses crochets ne lit le module des rôles au
 * chargement ; toute lecture croisée se fait à l'exécution, sous `class_exists`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  L'ORDRE DES PRIORITÉS SUR `authenticate` EST CONTRACTUEL.                    │
 * │                                                                               │
 * │   1  Ecluse::barrer                    verrou, AVANT toute vérification       │
 * │  20  (cœur) wp_authenticate_username_password                                 │
 * │  30  AccesCompte::refuser_si_suspendu  seulement si l'on tient un WP_User     │
 * │  40  Ecluse::constater                 a besoin du code d'erreur d'origine    │
 * │  45  MessageConnexion::uniformiser     efface ce code d'origine               │
 * │  50  Deuxfacteurs::exiger_second_facteur                                      │
 * │                                                                               │
 * │  LA PRIORITÉ 1 EST LE POINT CENTRAL : une requête verrouillée est rejetée     │
 * │  SANS QUE LE MOT DE PASSE SOIT VÉRIFIÉ. Aucun calcul de hachage offert à      │
 * │  l'attaquant, aucun oracle de temps de réponse, aucune charge inutile.        │
 * │  Intervertir 40 et 45 rendrait le verrouillage décoratif : l'écluse ne        │
 * │  verrait plus que son propre code d'erreur et ne compterait plus rien.        │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Security\Auth\AccesCompte;
use Massifs\Security\Auth\Deuxfacteurs;
use Massifs\Security\Auth\Ecluse;
use Massifs\Security\Auth\Enrolement;
use Massifs\Security\Auth\Fermetures;
use Massifs\Security\Auth\GardeRest;
use Massifs\Security\Auth\MessageConnexion;
use Massifs\Security\Auth\Motdepasse;
use Massifs\Security\Auth\Sessions;

add_filter( 'authenticate', array( Ecluse::class, 'barrer' ), 1, 3 );
add_filter( 'authenticate', array( AccesCompte::class, 'refuser_si_suspendu' ), 30, 3 );
add_filter( 'authenticate', array( Ecluse::class, 'constater' ), 40, 3 );
add_filter( 'authenticate', array( MessageConnexion::class, 'uniformiser' ), 45, 3 );
add_filter( 'authenticate', array( Deuxfacteurs::class, 'exiger_second_facteur' ), 50, 3 );

// Purge des compteurs après une connexion réussie, sans quoi neuf échecs suivis d'un
// succès laisseraient l'utilisateur légitime à un lapsus du verrouillage.
add_action( 'wp_login', array( Ecluse::class, 'sur_connexion' ), 10, 2 );

// Le formulaire de mot de passe oublié ne traverse pas `authenticate` : il est couvert
// séparément, sinon il resterait un canal non limité.
add_action( 'lostpassword_post', array( Ecluse::class, 'sur_mot_de_passe_perdu' ), 10, 1 );

// Dernier rempart contre la réintroduction, au rendu, du lien qui ne s'affiche que sur
// « mot de passe incorrect » — et qui suffirait à rouvrir l'énumération de comptes.
add_filter( 'login_errors', array( MessageConnexion::class, 'filtrer_rendu' ), 20, 1 );

// Étape 2 du second facteur. `login_form_{action}` est le crochet que le cœur fournit
// pour une action de connexion sur mesure : `wp-login.php` n'est jamais détourné.
//
// Le suffixe est écrit EN LITTÉRAL, et non `Deuxfacteurs::ACTION` : lire une constante
// de classe ici déclencherait le chargement du fichier au boot, ce qu'une amorce de
// module ne fait jamais. La contrepartie est que l'orthographe doit rester identique à
// `Deuxfacteurs::ACTION` — une divergence n'émettrait AUCUNE erreur PHP, le formulaire
// d'étape 2 ne s'afficherait simplement jamais.
add_action( 'login_form_massifs_2fa', array( Deuxfacteurs::class, 'etape_2' ), 10, 0 );

// Rampe d'enrôlement, en priorité 1 : avant que le moindre écran ne se construise.
add_action( 'admin_init', array( Enrolement::class, 'aiguiller' ), 1, 0 );

add_action( 'show_user_profile', array( Enrolement::class, 'section_profil' ), 10, 1 );
add_action( 'edit_user_profile', array( Enrolement::class, 'section_profil' ), 10, 1 );
add_action( 'personal_options_update', array( Enrolement::class, 'enregistrer_profil' ), 10, 1 );
add_action( 'edit_user_profile_update', array( Enrolement::class, 'enregistrer_profil' ), 10, 1 );

add_filter( 'auth_cookie_expiration', array( Sessions::class, 'duree' ), 10, 3 );
add_action( 'profile_update', array( Sessions::class, 'sur_mise_a_jour_profil' ), 10, 2 );

// Politique de mot de passe : les deux chemins par lesquels un mot de passe entre dans
// le site, l'écran de profil et le formulaire de réinitialisation.
add_action( 'user_profile_update_errors', array( Motdepasse::class, 'controler_profil' ), 10, 3 );
add_action( 'validate_password_reset', array( Motdepasse::class, 'controler_reinitialisation' ), 10, 2 );

// Filet fail-closed sur les écritures REST. JAMAIS `rest_authentication_errors` :
// voir l'encadré en tête de `GardeRest`.
add_filter( 'rest_request_before_callbacks', array( GardeRest::class, 'garder' ), 10, 3 );

add_filter( 'wp_is_application_passwords_available', array( Fermetures::class, 'mots_de_passe_application' ), 10, 1 );
add_filter( 'wp_is_application_passwords_available_for_user', array( Fermetures::class, 'mots_de_passe_application_pour' ), 10, 2 );
add_filter( 'xmlrpc_enabled', array( Fermetures::class, 'xmlrpc' ), 10, 1 );
add_filter( 'xmlrpc_methods', array( Fermetures::class, 'methodes_xmlrpc' ), 10, 1 );
add_filter( 'wp_headers', array( Fermetures::class, 'retirer_pingback' ), 10, 2 );

// Annonce de découverte d'XML-RPC dans le `<head>` public : elle désigne un point
// d'entrée que nous venons de vider, et n'attire plus que des robots. Retirée ici et
// non dans le thème — c'est une décision de sécurité, pas de présentation.
remove_action( 'wp_head', 'rsd_link' );

require_once __DIR__ . '/api-permissions.php';
