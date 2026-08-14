<?php
/**
 * Hors période d'activité : silence total de la veille.
 *
 * Une absence attendue n'est pas un incident. En janvier, aucun relevé n'est
 * attendu : la veille ne doit émettre ni constat, ni courriel, et n'écrire
 * aucune option — sans quoi le gestionnaire recevrait une alerte par jour
 * pendant huit mois et finirait par toutes les ignorer.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Ingest\Cron\Veille;
use Massifs\Security\Alertes\Verrou;

delete_option( Verrou::OPTION );

// Même jour d'hiver que le scénario 21, pour la même raison : on demande le
// jour au lieu d'attendre décembre.
$jour = '2026-01-15';

$f = massifs_fraicheur( $jour );

t_egal( false, $f['dispositif_actif'], 'le 15 janvier est hors période d’activité' );
t_egal( null, $f['dernier_releve_le'], 'aucun relevé en base' );
t_egal( false, $f['perimee'], 'hors période : aucune péremption, même sans le moindre relevé' );

$constats = 0;
$temoin   = static function () use ( &$constats ) {
	++$constats;
};
add_action( 'massifs_donnee_perimee_constatee', $temoin );

$boite = array();
t_intercepter_mail( $boite );

Veille::executer( $jour );

t_egal( 0, $constats, 'hors période : aucun constat émis' );
t_egal( 0, count( $boite ), 'hors période : aucun courriel' );
t_egal( false, get_option( Verrou::OPTION, false ), 'hors période : aucune écriture d’option' );

// Et l'absence de statuts pour ce jour, elle, reste un signal DISTINCT porté
// par le thème : la veille ne s'en mêle pas.
t_egal( 'hors_saison', massifs_synthese_du_jour( massifs_codes(), $jour )['etat_global'], 'le domaine dit toujours « hors saison » : les deux règles restent disjointes' );

remove_action( 'massifs_donnee_perimee_constatee', $temoin );
remove_all_filters( 'pre_wp_mail' );
delete_option( Verrou::OPTION );

t_reset();
t_bilan();
