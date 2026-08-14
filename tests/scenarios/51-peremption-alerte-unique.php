<?php
/**
 * Donnée périmée : UNE alerte, par source et par jour de validité.
 *
 * Second trou refermé par l'issue #12 : `perimee` était calculée par le domaine
 * et lue par le seul thème, pour l'afficher. Personne, dans l'extension, ne la
 * surveillait — un site pouvait afficher « Donnée périmée. » pendant des
 * semaines sans qu'aucun courriel ne parte.
 *
 * Deux causes de péremption sont éprouvées : aucun relevé du tout (l'état d'une
 * base vierge en période d'activité) et un relevé plus vieux que le seuil.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
t_reset();

use Massifs\Domain\Fraicheur\RegistreReleves;
use Massifs\Ingest\Cron\Veille;
use Massifs\Security\Alertes\Verrou;

delete_option( Verrou::OPTION );

// Jour fixe en période d'activité : la recette ne dépend pas du mois où elle
// est jouée (règle 3 de tests/README.md — on demande le jour, on n'attend pas
// que l'horloge coopère).
$jour = '2026-07-15';

t_assert( is_email( (string) get_option( 'admin_email', '' ) ), 'préalable : le site a un destinataire d’alerte', 'une adresse valide', get_option( 'admin_email' ) );

$boite = array();
t_intercepter_mail( $boite );

// --- Témoin réseau posé sur le CHEMIN D'INCIDENT COMPLET.
//
// C'est ici, et pas dans le scénario 50, que l'assertion « aucun octet réseau »
// a un sens : les exécutions ci-dessous traversent les cinq gardes, émettent
// l'action de constat, atteignent l'abonné d'alerte et vont jusqu'à `wp_mail`.
// Le scénario 50 pose le même témoin, mais sur un jour hors période d'activité
// où `executer()` sort à sa garde 3 — il ne peut donc rien observer du chemin
// qui compte. Le seul appel sortant autorisé de tout ce module est `wp_mail`,
// intercepté par le harnais et invisible de `http_api_debug`.
$urls = array();
add_action( 'http_api_debug', static function ( $r, $c, $cl, $a, $url ) use ( &$urls ) {
	$urls[] = $url;
}, 10, 5 );

// --- CAS A : aucun relevé du tout.
$f = massifs_fraicheur( $jour );

t_egal( true, $f['dispositif_actif'], 'préalable : le 15 juillet est en période d’activité' );
t_egal( null, $f['dernier_releve_le'], 'base vierge : aucun relevé réussi enregistré' );
t_egal( null, $f['age_secondes'], 'base vierge : âge inconnu' );
t_egal( true, $f['perimee'], 'base vierge en période d’activité : la donnée servie est périmée' );

Veille::executer( $jour );
t_egal( 1, count( $boite ), 'aucun relevé : exactement un courriel' );
t_egal( array(), $urls, 'chemin d’incident complet jusqu’à wp_mail : aucun appel sortant' );

$message = $boite[0];
$sujet   = (string) $message['subject'];
$corps   = (string) $message['message'];
$entetes = (array) ( $message['headers'] ?? array() );

t_assert( str_starts_with( $sujet, '[MASSIFS]' ), 'sujet préfixé [MASSIFS]', '[MASSIFS]…', $sujet );
t_assert( str_contains( $sujet, $jour ), 'le sujet cite le jour de validité', $jour, $sujet );
t_assert( str_contains( $sujet, (string) $f['dernier_releve_source'] ), 'le sujet cite la source', $f['dernier_releve_source'], $sujet );
t_assert( in_array( 'Content-Type: text/plain; charset=UTF-8', $entetes, true ), 'courriel en texte brut UTF-8', 'Content-Type: text/plain; charset=UTF-8', $entetes );

t_assert( str_contains( $corps, (string) $f['dernier_releve_source'] ), 'le corps cite la source' );
t_assert( str_contains( $corps, (string) $f['seuil_secondes'] ), 'le corps cite le seuil, LU dans le domaine et jamais écrit en dur', (string) $f['seuil_secondes'], $corps );
t_assert( str_contains( $corps, 'Aucun relevé réussi' ), 'le cas « aucun relevé » a sa propre formulation, pas un « depuis le … » vide' );
t_assert( str_contains( $corps, 'CE QUE LE SITE AFFICHE' ), 'le corps dit ce que le site affiche' );
t_assert( str_contains( $corps, 'bannière de péremption' ), 'le corps nomme la bannière de péremption' );
t_assert( str_contains( $corps, 'information non disponible' ), 'le corps distingue explicitement la bannière du signal « information non disponible »' );
t_assert( str_contains( $corps, 'MASSIFS_VEILLE_FRAICHEUR_DESARMEE' ), 'le corps dit comment faire taire l’alerte (constante)' );
t_assert( str_contains( $corps, 'massifs_veille_fraicheur_armee' ), 'le corps dit comment faire taire l’alerte (filtre)' );
t_assert( str_contains( $corps, admin_url() ), 'le corps donne l’adresse de l’administration' );
// L'URL doit être exigée NON VIDE avant d'être cherchée : `str_contains( $x, '' )`
// est toujours vrai, et l'URL vide est précisément le cas où `Peremption` OMET la
// ligne. Sans cette garde, l'assertion passerait au vert exactement quand le
// comportement qu'elle prétend vérifier n'a pas lieu.
$carte = massifs_attribution_statuts()['carte_officielle_url'];
t_assert( '' !== $carte && str_contains( $corps, $carte ), 'le corps donne la carte officielle, LUE et jamais écrite en dur', $carte, $corps );
t_assert( ! str_contains( $corps, '&#' ) && ! str_contains( $corps, '&amp;' ) && ! str_contains( $corps, '&laquo;' ), 'aucune entité HTML : un courriel texte n’est jamais échappé', 'aucune entité', $corps );

// --- Le verrou : une seule alerte pour ce jour et cette source.
Veille::executer( $jour );
t_egal( 1, count( $boite ), 'seconde exécution horaire le même jour : aucun second courriel' );

$verrous = get_option( Verrou::OPTION, array() );
$cle     = 'peremption:' . $f['dernier_releve_source'] . ':' . $jour;

t_assert( is_array( $verrous ) && array_key_exists( $cle, $verrous ), 'clé de verrou « {type}:{source}:{jour_validite} »', $cle, $verrous );
t_assert( is_string( $verrous[ $cle ] ?? null ) && '' !== $verrous[ $cle ], 'le verrou retient l’instant d’évaluation', 'un instant ISO', $verrous[ $cle ] ?? null );

global $wpdb;
$autoload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Verrou::OPTION ) );
t_assert( in_array( $autoload, array( 'no', 'off' ), true ), 'option de verrou NON autoloadée (lue depuis le cron seulement)', 'no|off', $autoload );

// --- Verrou purgé : l'alerte repart. Une péremption qui dure plusieurs jours
// mérite un rappel par jour, jamais « une seule fois pour toujours ».
delete_option( Verrou::OPTION );
Veille::executer( $jour );
t_egal( 2, count( $boite ), 'verrou purgé : l’alerte repart' );

// --- CAS B : un relevé plus vieux que le seuil.
t_reset();
delete_option( Verrou::OPTION );
$boite = array();

$instant = gmdate( 'Y-m-d\TH:i:s\Z', time() - 30 * HOUR_IN_SECONDS );
$ecrit   = massifs_enregistrer_releve_reussi( RegistreReleves::SOURCE_PREFECTURE, $instant );
t_egal( true, $ecrit['enregistre'], 'relevé de 30 h enregistré' );

$f2 = massifs_fraicheur( $jour );
t_egal( true, $f2['perimee'], 'relevé de 30 h : le domaine déclare la donnée périmée' );
t_assert( is_int( $f2['age_secondes'] ), 'relevé de 30 h : l’âge est connu', 'un entier', $f2['age_secondes'] );

Veille::executer( $jour );
t_egal( 1, count( $boite ), 'relevé périmé : exactement un courriel' );

$corps = (string) $boite[0]['message'];
t_assert( str_contains( $corps, (string) $f2['dernier_releve_le'] ), 'le corps cite l’instant du dernier relevé', (string) $f2['dernier_releve_le'], $corps );

$capture = array();
t_assert( 1 === preg_match( '/Âge du dernier relevé\s*:\s*(\d+) secondes/u', $corps, $capture ), 'le corps cite l’âge du dernier relevé, en secondes', 'Âge du dernier relevé : N secondes', $corps );
t_assert( isset( $capture[1] ) && abs( (int) $capture[1] - 30 * HOUR_IN_SECONDS ) <= 120, 'l’âge cité est bien celui du relevé posé', '≈ ' . ( 30 * HOUR_IN_SECONDS ) . ' s', $capture[1] ?? null );

// Bilan cumulé du témoin réseau sur les quatre exécutions du scénario — dont
// trois ont émis un courriel. `t_reset()` ne retire aucun crochet : le témoin
// posé plus haut a bien observé la totalité du scénario.
t_egal( array(), $urls, 'aucun appel sortant sur l’ensemble du scénario, wp_mail excepté' );

remove_all_filters( 'pre_wp_mail' );
delete_option( Verrou::OPTION );

t_reset();
t_bilan();
