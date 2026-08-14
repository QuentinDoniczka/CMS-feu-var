<?php
/**
 * Le coupe-circuit et la porte saisonnière : deux façons de n'émettre AUCUN
 * octet, et de le prouver.
 *
 * Le coupe-circuit météo est plus strict que celui du connecteur préfecture, à
 * dessein : sans `MASSIFS_METEO_JSON_URL_TEMPLATE`, le module est désarmé dans
 * TOUS les environnements, production comprise. Le point d'entrée réel de l'API
 * n'est pas connu, et une URL par défaut inventée serait un appel sortant vers
 * une adresse fausse en production.
 *
 * L'absence d'octet est portée par un `pre_http_request` qui FAIT ÉCHOUER le
 * test s'il est appelé — pas par une simple absence d'effet observable.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Meteo\Connector;
use Massifs\Ingest\Meteo\Lecture;
use Massifs\Ingest\Meteo\Schedule;
use Massifs\Ingest\Meteo\Settings;
use Massifs\Ingest\Meteo\StateRepository;

$purge = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge();

$boite = array();
t_intercepter_mail( $boite );

// SONDE D'ÉMISSION. Toute requête sortante, quelle qu'en soit l'origine, la fait
// basculer : c'est elle qui transforme « aucun effet observé » en « aucun octet
// émis ».
$emis = array();
add_filter(
	'pre_http_request',
	static function ( $court_circuit, $args, $url ) use ( &$emis ) {
		$emis[] = (string) $url;

		return new WP_Error( 'massifs_recette_octet_interdit', 'Un octet réseau a été émis alors que le module devait être muet.' );
	},
	10,
	3
);

$aujourdhui = massifs_jour_courant();

// ---------------------------------------------------------------------------
// 1. Sans la constante : désarmé, quel que soit l'environnement.
// ---------------------------------------------------------------------------
t_egal( false, defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' ), 'le modèle d\'URL n\'est pas défini au départ' );
t_egal( true, Settings::is_disabled(), 'coupe-circuit ACTIF : sans modèle d\'URL, le module est désarmé' );
t_note( 'environnement de la stack : ' . wp_get_environment_type() );

// Le verdict ne peut pas dépendre de l'environnement : le fichier de réglages ne
// le consulte nulle part. C'est vérifiable mécaniquement, et c'est ce qui rend
// l'affirmation « dans les trois environnements » opposable sans avoir à
// redéfinir une constante du cœur en cours de requête.
$source_reglages = (string) file_get_contents( MASSIFS_CORE_CHEMIN . 'includes/ingest/meteo/class-settings.php' );
t_assert( false === strpos( $source_reglages, 'wp_get_environment_type' ), 'le coupe-circuit ne consulte JAMAIS le type d\'environnement' );
t_assert( false === strpos( $source_reglages, 'WP_ENVIRONMENT' ), 'ni la constante d\'environnement du cœur' );
t_assert( false === strpos( $source_reglages, 'URL_JSON_DEFAUT' ), 'et aucune URL par défaut n\'existe pour la source' );

t_egal( '', Settings::url_for( str_replace( '-', '', $aujourdhui ) ), 'aucune URL n\'est constructible sans le modèle' );

$r = Connector::run_now( $aujourdhui );
t_assert( is_wp_error( $r ) && 'massifs_meteo_desactive' === $r->get_error_code(), 'run_now() refuse : connecteur désactivé', 'massifs_meteo_desactive', is_wp_error( $r ) ? $r->get_error_code() : $r );

t_egal( false, wp_next_scheduled( Schedule::HOOK ), 'aucun évènement planifié quand le coupe-circuit est actif' );
t_egal( array(), $emis, 'ZÉRO OCTET RÉSEAU pendant toute la phase désarmée' );
t_egal( array(), $boite, 'et aucun courriel : un module désarmé n\'est pas un incident' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'la lecture reste honnête : « indisponible »' );

// ---------------------------------------------------------------------------
// 2. Armé, mais hors période d'exploitation : toujours zéro octet.
// ---------------------------------------------------------------------------
define( 'MASSIFS_METEO_JSON_URL_TEMPLATE', 'http://wordpress/massifs-bouchon-meteo/{date}.json' );

t_egal( false, Settings::is_disabled(), 'la constante définie lève le coupe-circuit' );

add_filter( 'massifs_meteo_saison_operationnelle', '__return_false' );

$r = Connector::run_now( $aujourdhui );
t_assert( is_wp_error( $r ) && 'massifs_meteo_hors_saison' === $r->get_error_code(), 'hors période : refus AVANT tout octet réseau', 'massifs_meteo_hors_saison', is_wp_error( $r ) ? $r->get_error_code() : $r );

t_egal( array(), $emis, 'HORS PÉRIODE : zéro octet réseau' );
t_egal( array(), $boite, 'HORS PÉRIODE : zéro alerte — une absence attendue n\'est pas un incident' );

$journal = StateRepository::get()['journal'];
t_egal( 'hors_saison', end( $journal )['issue'] ?? '', 'le journal d\'exploitation trace « hors_saison »' );

// ---------------------------------------------------------------------------
// 3. « hors_saison » n'existe PAS comme état public, et ne doit pas être créé.
// ---------------------------------------------------------------------------
$m = massifs_meteo_du_jour( $aujourdhui );
t_egal( 'indisponible', $m['etat'], 'hors période, le visiteur lit « indisponible » — jamais « hors saison »' );
t_egal( array( 'disponible', 'indisponible', 'non_encore_publie' ), Lecture::ETATS, 'le vocabulaire d\'état est FERMÉ à trois valeurs' );
t_assert( ! in_array( 'hors_saison', Lecture::ETATS, true ), '« hors_saison » n\'est pas un état public de la météo' );
t_assert( ! in_array( 'donnee_perimee', Lecture::ETATS, true ), '« donnee_perimee » non plus : il n\'y a rien entre « courant » et « absent »' );
t_note( 'affirmer que la source ne publie pas hors du dispositif préfectoral serait inventer un fait de domaine sur une source tierce.' );

$purge();
t_reset();
t_bilan();
