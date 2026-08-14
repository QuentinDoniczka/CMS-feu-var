<?php
/**
 * Veille désarmée : plus de planification, plus de constat, plus de courriel.
 *
 * L'armement n'a AUCUNE option en base — donc aucun écran de réglages, aucune
 * capability et aucune seconde source de vérité. Il se pilote par une constante
 * de `wp-config.php` et par un filtre, dans cet ordre.
 *
 * L'armement est relu DEUX FOIS — à la planification et au déclenchement — pour
 * qu'un désarmement produise son effet immédiatement, même si l'évènement déjà
 * planifié survit une heure. Ce scénario éprouve les deux.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Ingest\Cron\Planificateur;
use Massifs\Ingest\Cron\Veille;
use Massifs\Security\Alertes\Verrou;

delete_option( Verrou::OPTION );

$jour = '2026-07-15';

// --- Préalable : armée, planifiée, et la donnée du jour EST périmée.
Planificateur::assurer();
t_assert( false !== wp_next_scheduled( Planificateur::HOOK ), 'préalable : la veille est planifiée', 'un horodatage', wp_next_scheduled( Planificateur::HOOK ) );
t_egal( true, massifs_fraicheur( $jour )['perimee'], 'préalable : la donnée du jour est bien périmée' );

// --- Le filtre désarme, et `assurer()` RETIRE l'évènement.
add_filter( 'massifs_veille_fraicheur_armee', '__return_false' );

t_egal( false, Planificateur::est_armee(), 'le filtre désarme la veille' );

Planificateur::assurer();
t_egal( false, wp_next_scheduled( Planificateur::HOOK ), 'assurer() retire l’évènement quand la veille est désarmée' );

// --- Et le déclenchement lui-même reste muet, donnée périmée sous les yeux.
$constats = 0;
$temoin   = static function () use ( &$constats ) {
	++$constats;
};
add_action( 'massifs_donnee_perimee_constatee', $temoin );

$boite = array();
t_intercepter_mail( $boite );

Veille::executer( $jour );

t_egal( 0, $constats, 'désarmée : aucun constat, même sur une donnée périmée' );
t_egal( 0, count( $boite ), 'désarmée : aucun courriel' );
t_egal( false, get_option( Verrou::OPTION, false ), 'désarmée : aucune écriture d’option' );

// --- Filtre retiré : le défaut est « armée ».
remove_all_filters( 'massifs_veille_fraicheur_armee' );
t_egal( true, Planificateur::est_armee(), 'filtre retiré : la veille est de nouveau armée (défaut : armée)' );

// --- La constante prime, et n'a pas besoin du filtre pour cela.
add_filter( 'massifs_veille_fraicheur_armee', '__return_true' );
define( 'MASSIFS_VEILLE_FRAICHEUR_DESARMEE', true );

t_egal( false, Planificateur::est_armee(), 'la constante désarme, même avec le filtre à true' );

Planificateur::assurer();
t_egal( false, wp_next_scheduled( Planificateur::HOOK ), 'constante posée : aucun évènement ne subsiste' );

Veille::executer( $jour );
t_egal( 0, $constats, 'constante posée : le déclenchement reste muet' );
t_egal( 0, count( $boite ), 'constante posée : aucun courriel' );

remove_action( 'massifs_donnee_perimee_constatee', $temoin );
remove_all_filters( 'massifs_veille_fraicheur_armee' );
remove_all_filters( 'pre_wp_mail' );
delete_option( Verrou::OPTION );

t_reset();
t_bilan();
