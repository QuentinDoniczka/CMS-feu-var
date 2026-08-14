<?php
/**
 * Amorçage du module « alertes d'exploitation ».
 *
 * Ce fichier ne déclare QUE des crochets. Il n'inclut aucun fichier : les
 * classes sont résolues par l'autoloader de l'extension, au premier usage.
 * Conséquence voulue — le chargeur n'inclut jamais les fichiers de classe de ce
 * module, donc aucun d'eux ne peut faire tomber le site pendant qu'une chaîne
 * sœur écrit dans le même arbre de travail.
 *
 * `Peremption::class` est résolu à la compilation : le nommer ici ne déclenche
 * aucun chargement.
 *
 * @package Massifs\Security\Alertes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Security\Alertes\Peremption;

// Abonné au constat de péremption émis par la veille. L'orthographe du nom
// d'action est la seule chose qui relie l'émetteur à cet abonné : une
// divergence n'émettrait AUCUNE erreur PHP, l'action partirait et personne ne
// l'écouterait. Elle est verrouillée par les scénarios de recette 51 et 53.
add_action( 'massifs_donnee_perimee_constatee', array( Peremption::class, 'alerter' ), 10, 1 );
