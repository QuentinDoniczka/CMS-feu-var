<?php
/**
 * Chemin nominal : la veille ne coûte rien, et elle ne survit pas à la
 * désactivation de l'extension.
 *
 * Une veille horaire qui écrirait une option à chaque passage ferait 24
 * écritures par jour pour ne rien dire. Et un évènement `massifs_*` orphelin
 * dans le cron d'un site où l'extension est désactivée est un défaut : ce
 * scénario reprend, pour la veille, le contrôle que
 * `20-cron-complet.arme.php` applique déjà au connecteur.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Domain\Fraicheur\RegistreReleves;
use Massifs\Ingest\Cron\Planificateur;
use Massifs\Ingest\Cron\Veille;
use Massifs\Security\Alertes\Verrou;

delete_option( Verrou::OPTION );

$jour = '2026-07-15';

// --- Donnée fraîche : rien ne part, rien ne s'écrit.
$ecrit = massifs_enregistrer_releve_reussi( RegistreReleves::SOURCE_PREFECTURE );
t_egal( true, $ecrit['enregistre'], 'relevé de l’instant enregistré' );

$f = massifs_fraicheur( $jour );
t_egal( true, $f['dispositif_actif'], 'préalable : le 15 juillet est en période d’activité' );
t_egal( false, $f['perimee'], 'relevé de l’instant : la donnée servie est fraîche' );

$constats = 0;
$temoin   = static function () use ( &$constats ) {
	++$constats;
};
add_action( 'massifs_donnee_perimee_constatee', $temoin );

$boite = array();
t_intercepter_mail( $boite );

Veille::executer( $jour );

t_egal( 0, $constats, 'donnée fraîche : aucun constat émis' );
t_egal( 0, count( $boite ), 'donnée fraîche : aucun courriel' );

global $wpdb;
$presente = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", Verrou::OPTION ) );
t_egal( null, $presente, 'chemin nominal : l’option de verrou n’est même pas créée' );

// --- Désactivation : aucun crochet « massifs_* » ne survit dans le cron.
Planificateur::assurer();
t_assert( false !== wp_next_scheduled( Planificateur::HOOK ), 'évènement de veille planifié avant désactivation', 'un horodatage', wp_next_scheduled( Planificateur::HOOK ) );

deactivate_plugins( 'massifs-core/massifs-core.php' );

t_egal( false, wp_next_scheduled( Planificateur::HOOK ), 'désactivation : évènement de veille retiré (aucun orphelin)' );

$restant = 0;
foreach ( (array) _get_cron_array() as $creneau ) {
	foreach ( array_keys( $creneau ) as $crochet ) {
		if ( str_starts_with( (string) $crochet, 'massifs_' ) ) {
			++$restant;
		}
	}
}
t_egal( 0, $restant, 'désactivation : plus aucun crochet « massifs_* » dans le cron' );

activate_plugin( 'massifs-core/massifs-core.php' );
t_assert( is_plugin_active( 'massifs-core/massifs-core.php' ), 'extension réactivée en fin de scénario' );

remove_action( 'massifs_donnee_perimee_constatee', $temoin );
remove_all_filters( 'pre_wp_mail' );
Planificateur::retirer();
delete_option( Verrou::OPTION );

t_reset();
t_bilan();
