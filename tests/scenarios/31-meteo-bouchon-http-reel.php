<?php
/**
 * ALLER-RETOUR HTTP RÉEL, SANS `pre_http_request`.
 *
 * C'est la seule manière de prouver la troisième case de la checklist du §3 du
 * brief : consommer côté serveur, mettre en cache, re-servir depuis notre
 * domaine. Un bouchon posé sur `pre_http_request` court-circuite le tuyau et ne
 * prouve rien du tuyau.
 *
 * Le bouchon est déposé dans `data/meteo/bouchons/`, servi par NOTRE serveur —
 * même origine, aucune requête vers un domaine tiers. Le connecteur y fait un
 * vrai `wp_remote_get`, et la suppression du fichier produit un 404, c'est-à-dire
 * exactement le scénario « pas encore publié ».
 *
 * Le répertoire est remis dans l'état où il a été trouvé par un `finally`, avec
 * assertion de remise en état.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Meteo\Connector;
use Massifs\Ingest\Meteo\StateRepository;

$purge = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge();

// Le connecteur est désarmé tant que le modèle d'URL n'est pas défini, dans TOUS
// les environnements. On le pointe vers notre propre serveur : il s'arme, et la
// source réelle devient inatteignable par construction.
if ( ! defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' ) ) {
	define( 'MASSIFS_METEO_JSON_URL_TEMPLATE', 'http://wordpress/wp-content/plugins/massifs-core/data/meteo/bouchons/{date}.json' );
}

add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

/*
 * ARTEFACT DE LA STACK, PAS DU CONNECTEUR — et il faut le dire, parce qu'il
 * décide de ce que ce scénario prouve.
 *
 * Depuis le réseau interne, le site s'atteint par l'hôte `wordpress`, alors que
 * son URL canonique porte l'hôte ET LE PORT de l'hôte de développement. Un
 * fichier ABSENT sous `wp-content/` n'est donc pas servi en 404 par Apache : la
 * réécriture de permaliens le confie à WordPress, qui répond un 301 canonique
 * vers son propre hôte:port — injoignable depuis l'intérieur du réseau. Le
 * connecteur verrait alors une panne réseau là où la source dit « pas encore
 * publié », c'est-à-dire l'inverse de ce qu'il faut observer.
 *
 * On présente donc l'hôte canonique du site, par le filtre PUBLIC prévu pour
 * les en-têtes sortants. L'aller-retour reste entièrement réel — même serveur,
 * même tuyau, même code de statut : seule l'en-tête `Host` est corrigée.
 */
add_filter(
	'massifs_meteo_http_args',
	static function ( array $args ): array {
		$hote = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$port = wp_parse_url( home_url(), PHP_URL_PORT );

		$args['headers']         = is_array( $args['headers'] ?? null ) ? $args['headers'] : array();
		$args['headers']['Host'] = null === $port ? $hote : $hote . ':' . $port;

		return $args;
	}
);

$boite = array();
t_intercepter_mail( $boite );

$aujourdhui = massifs_jour_courant();
$ymd        = str_replace( '-', '', $aujourdhui );

$repertoire = MASSIFS_CORE_CHEMIN . 'data/meteo/bouchons/';
$fichier    = $repertoire . $ymd . '.json';

t_assert( is_dir( $repertoire ), 'le répertoire de bouchons existe', $repertoire, is_dir( $repertoire ) );
t_assert( ! file_exists( $fichier ), 'aucun bouchon daté n\'est commité : le fichier du jour n\'existe pas au départ', false, file_exists( $fichier ) );

try {
	$charge = (string) wp_json_encode(
		array(
			'schema'        => 1,
			'zone'          => '13',
			'jour'          => $aujourdhui,
			'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
			'niveau_source' => 2,
		)
	);

	$ecrit = file_put_contents( $fichier, $charge );
	t_assert( false !== $ecrit && $ecrit > 0, 'le bouchon du jour est déposé dans le répertoire servi', '> 0 octets', $ecrit );

	// -----------------------------------------------------------------------
	// 1. Le connecteur traverse le VRAI tuyau et met en cache.
	// -----------------------------------------------------------------------
	$r = Connector::run_now( $aujourdhui );
	t_assert( true === $r, 'run_now() : aller-retour HTTP réel réussi', true, is_wp_error( $r ) ? $r->get_error_code() . ' / ' . $r->get_error_message() : $r );

	t_egal( true, Connector::has_snapshot_for( $aujourdhui ), 'un instantané couvre désormais le jour demandé' );

	$instantane = Connector::snapshot_for( $aujourdhui );
	t_egal( $aujourdhui, $instantane['date_validite'], 'l\'instantané porte la date de validité DEMANDÉE' );
	t_egal( 2, $instantane['niveau_source'], 'le jeton source est stocké BRUT, jamais traduit' );
	t_egal( '13', $instantane['zone_cle'], 'la zone reçue est celle que nous couvrons' );
	t_egal( hash( 'sha256', $charge ), $instantane['hash'], 'le hachage porte sur le corps réellement servi' );
	t_egal( strlen( $charge ), $instantane['octets'], 'les octets comptés sont ceux réellement transférés' );

	$origine_bouchon = wp_parse_url( $instantane['source_url'], PHP_URL_HOST );
	t_egal( 'wordpress', $origine_bouchon, 'la charge vient de NOTRE serveur, jamais d\'un domaine tiers' );

	// Le relevé de fraîcheur n'est écrit qu'après une charge validée.
	$m = massifs_meteo_du_jour( $aujourdhui );
	t_assert( is_string( $m['releve_le'] ) && '' !== $m['releve_le'], 'un relevé RÉUSSI est enregistré pour la source « meteo »', 'instant ISO', $m['releve_le'] );
	t_assert( is_string( $m['publie_le'] ) && '' !== $m['publie_le'], 'la publication déclarée POUR CE JOUR voyage comme fait', 'instant ISO', $m['publie_le'] );

	// … et pourtant, rien n'est affichable : la garde de vocabulaire tient même
	// après un aller-retour parfaitement réussi.
	t_egal( 'indisponible', $m['etat'], 'charge réelle validée et mise en cache : l\'état reste « indisponible »' );
	t_egal( null, $m['niveau'], 'aucun niveau composé' );

	$etat = StateRepository::get();
	t_egal( 0, (int) $etat['echecs_consecutifs'], 'aucun échec compté sur un aller-retour réussi' );
	t_egal( $ymd, (string) $etat['derniere_date_obtenue'], 'la date obtenue est journalisée' );

	// -----------------------------------------------------------------------
	// 2. Fichier supprimé : 404, c'est-à-dire « pas encore publié ».
	// -----------------------------------------------------------------------
	t_assert( unlink( $fichier ), 'le bouchon est retiré du répertoire servi' );
	$purge();

	// Le code de statut est d'abord constaté sur le fil, pour que l'assertion
	// suivante porte sur un VRAI 404 et non sur une panne déguisée.
	$brut = wp_remote_get(
		'http://wordpress/wp-content/plugins/massifs-core/data/meteo/bouchons/' . $ymd . '.json',
		array(
			'timeout'     => 10,
			'redirection' => 0,
			'headers'     => array( 'Host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . (string) wp_parse_url( home_url(), PHP_URL_PORT ) ),
		)
	);
	t_egal( 404, (int) wp_remote_retrieve_response_code( $brut ), 'le fichier absent est bien servi en HTTP 404 par notre serveur' );

	$r404 = Connector::run_now( $aujourdhui );
	t_assert( is_wp_error( $r404 ) && 'non_publie' === $r404->get_error_code(), '404 réel => code « non_publie »', 'non_publie', is_wp_error( $r404 ) ? $r404->get_error_code() : $r404 );

	$apres = StateRepository::get();
	t_egal( 0, (int) $apres['echecs_consecutifs'], '404 : le compteur d\'échecs consécutifs N\'EST PAS incrémenté' );
	t_egal( null, $apres['derniere_erreur'], '404 : aucune erreur enregistrée' );
	t_egal( array(), $boite, '404 : aucun courriel d\'alerte envoyé' );

	$dernier = end( $apres['journal'] );
	t_egal( 'non_publie', $dernier['issue'] ?? '', 'journal : issue « non_publie » tracée' );
	t_egal( $ymd, $dernier['date_cible'] ?? '', 'journal : date cible correcte' );

	t_egal( false, Connector::has_snapshot_for( $aujourdhui ), 'aucun instantané inventé sur un 404' );
	t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'le visiteur voit une absence, jamais un niveau' );
} finally {
	if ( file_exists( $fichier ) ) {
		unlink( $fichier );
	}

	// Assertion de remise en état : ce scénario, lancé seul, laisse le dépôt
	// exactement comme il l'a trouvé.
	t_assert( ! file_exists( $fichier ), 'REMISE EN ÉTAT : aucun bouchon daté ne subsiste dans le dépôt', false, file_exists( $fichier ) );
	t_assert( is_dir( $repertoire ), 'REMISE EN ÉTAT : le répertoire de bouchons est intact' );
}

$purge();
t_reset();
t_bilan();
