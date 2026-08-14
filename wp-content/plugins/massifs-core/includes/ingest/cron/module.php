<?php
/**
 * Amorçage du module « veille de fraîcheur ».
 *
 * Ce fichier ne déclare QUE des crochets. Il n'inclut aucun fichier : les
 * classes sont résolues par l'autoloader de l'extension, au premier usage.
 * Conséquence voulue — le chargeur n'inclut jamais les fichiers de classe de ce
 * module, donc aucun d'eux ne peut faire tomber le site pendant qu'une chaîne
 * sœur écrit dans le même arbre de travail.
 *
 * C'est aussi la raison pour laquelle le nom du crochet est écrit ici en toutes
 * lettres plutôt qu'en `Planificateur::HOOK` : lire la constante chargerait la
 * classe à l'inclusion du module. Les deux valeurs sont tenues identiques par le
 * scénario de recette 50, qui branche l'une sur l'autre.
 *
 * Aucun crochet d'activation : la stack a déjà activé l'extension, un tel
 * crochet ne se redéclencherait jamais. La planification est auto-réparatrice
 * sur `init`. Seule la désactivation est câblée — et elle est OBLIGATOIRE.
 *
 * @package Massifs\Ingest\Cron
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Ingest\Cron\Planificateur;
use Massifs\Ingest\Cron\Veille;

// Fichier principal de l'extension. Les deux nommages sont acceptés — le
// chargeur définit le nom français, le nom anglais existe en alias — et le
// repli est déduit de la position de ce fichier : `includes/ingest/cron/`, soit
// trois niveaux au-dessus. Sans ce crochet, un évènement `massifs_*` orphelin
// survivrait à la désactivation de l'extension.
$massifs_cron_fichier = dirname( __DIR__, 3 ) . '/massifs-core.php';

if ( defined( 'MASSIFS_CORE_FICHIER' ) ) {
	$massifs_cron_fichier = (string) MASSIFS_CORE_FICHIER;
} elseif ( defined( 'MASSIFS_CORE_FILE' ) ) {
	$massifs_cron_fichier = (string) MASSIFS_CORE_FILE;
}

register_deactivation_hook( $massifs_cron_fichier, array( Planificateur::class, 'retirer' ) );

add_action( 'init', array( Planificateur::class, 'assurer' ), 20 );

// `accepted_args` à 0 : la valeur par défaut `null` de `executer()` s'applique
// alors sans ambiguïté, quels que soient les arguments portés par l'évènement.
add_action( 'massifs_veille_fraicheur', array( Veille::class, 'executer' ), 10, 0 );

// Chargeur tardif : si `init` est déjà passé — activation d'extension, ligne de
// commande —, le crochet ci-dessus ne se déclenchera jamais pour cette requête
// et rien ne serait planifié.
if ( did_action( 'init' ) > 0 ) {
	Planificateur::assurer();
}
