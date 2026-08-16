<?php
/**
 * Amorçage du module « sauvegardes ».
 *
 * Ce fichier ne déclare QUE des crochets et n'inclut que ses propres fonctions de
 * lecture. Les classes sont résolues par l'autoloader de l'extension, au premier
 * usage : le chargeur n'inclut donc jamais les fichiers de classe de ce module,
 * et aucun d'eux ne peut faire tomber le site pendant qu'une chaîne sœur écrit
 * dans le même arbre de travail.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  LE GESTIONNAIRE CRON EST BRANCHÉ, L'ÉVÈNEMENT N'EST JAMAIS PLANIFIÉ PAR      │
 * │  DÉFAUT.                                                                      │
 * │                                                                               │
 * │  `DISABLE_WP_CRON` VAUT `true` SUR LES DEUX SERVICES DU PROJET : UN ÉVÈNEMENT │
 * │  PLANIFIÉ Y SERAIT INSCRIT, VISIBLE DANS `wp cron event list`, ET JAMAIS      │
 * │  EXÉCUTÉ. UNE SAUVEGARDE QU'ON CROIT AVOIR EST PIRE QUE PAS DE SAUVEGARDE.    │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI `init` EST CONDITIONNÉ À L'ADMINISTRATION OU À WP-CLI
 *
 * `init` se déclenche sur CHAQUE requête, y compris la page publique servie à un
 * visiteur. La synchronisation du cron n'a rien à y faire : le §10 interdit
 * d'alourdir le rendu public, et une lecture d'option par requête pour un
 * évènement désarmé par défaut serait exactement cela. La priorité 20 laisse aux
 * mu-plugins le temps de poser leurs filtres de réglages avant qu'on ne les lise.
 *
 * AUCUNE ROUTE REST, AUCUN ÉCRAN, AUCUN BOUTON n'est enregistré ici, et ce n'est
 * pas un oubli (arbitrage A-11 du contrat) : une restauration à un clic depuis
 * `wp-admin` est une arme braquée sur le pied du site.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Massifs\Security\Sauvegardes\Commande;
use Massifs\Security\Sauvegardes\Journal;
use Massifs\Security\Sauvegardes\Planification;

/*
 * `Classe::class` est résolu à la compilation et ne déclenche AUCUN chargement.
 * `Classe::CONSTANTE` en déclencherait un, sur chaque requête publique. Le nom du
 * crochet est donc écrit en littéral ci-dessous, malgré `Planification::HOOK` :
 * l'orthographe est verrouillée par ce commentaire et par la constante, et le
 * rendu public ne charge aucun fichier de ce module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$massifs_sauvegardes_en_cli = defined( 'WP_CLI' ) && constant( 'WP_CLI' );

if ( $massifs_sauvegardes_en_cli && class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'massifs sauvegarde', Commande::class );
}

// Gestionnaire branché en toutes circonstances : si un exploitant arme la
// planification par filtre, ou si un cron système déclenche le crochet à la main,
// il doit trouver quelqu'un au bout du fil. Ce qui est désarmé, c'est la POSE de
// l'évènement, pas son écoute.
add_action( 'massifs_sauvegarde_quotidienne', array( Planification::class, 'executer' ) );

if ( is_admin() || $massifs_sauvegardes_en_cli ) {
	add_action( 'init', array( Planification::class, 'synchroniser' ), 20 );
}

add_action( 'massifs_sauvegarde_echouee', array( Journal::class, 'alerter' ) );

require_once __DIR__ . '/api.php';
