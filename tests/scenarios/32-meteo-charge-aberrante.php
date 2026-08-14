<?php
/**
 * Charges aberrantes : les cinq couches de validation, une charge par motif.
 *
 * Chaque rejet porte SA couche, et une charge rejetée ne remplace jamais la
 * valeur déjà en cache. On éprouve aussi, et c'est aussi important, CE QUI N'EST
 * PAS UNE ABERRATION : un niveau au maximum, une valeur identique à la veille,
 * un saut d'amplitude. Les rejeter afficherait une absence précisément quand la
 * donnée est bonne.
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

if ( ! defined( 'MASSIFS_METEO_JSON_URL_TEMPLATE' ) ) {
	define( 'MASSIFS_METEO_JSON_URL_TEMPLATE', 'http://wordpress/massifs-bouchon-meteo/{date}.json' );
}

add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$boite = array();
t_intercepter_mail( $boite );

$aujourdhui = massifs_jour_courant();
$demain     = massifs_jour_suivant();
$hier       = t_jour_avant( $aujourdhui );

/**
 * Réponse HTTP 200 portant un corps BRUT.
 *
 * `t_reponse_200()` du harnais encode elle-même un tableau : elle ne permet pas
 * de servir un corps volontairement malformé, qui est tout l'objet d'ici.
 *
 * @param string $corps Corps servi verbatim.
 *
 * @return array<string,mixed>
 */
$reponse = static function ( string $corps ): array {
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => $corps,
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};

/**
 * Corps JSON bien formé, surchargeable clé par clé.
 *
 * @param array<string,mixed> $remplacements Clés à remplacer.
 */
$corps = static function ( array $remplacements ) use ( $aujourdhui ): string {
	return (string) wp_json_encode(
		array_merge(
			array(
				'schema'        => 1,
				'zone'          => '13',
				'jour'          => $aujourdhui,
				'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
				'niveau_source' => 2,
			),
			$remplacements
		)
	);
};

// Une valeur de référence est d'abord mise en cache : chaque rejet devra la
// laisser intacte.
t_bouchon_http( $reponse( $corps( array( 'niveau_source' => 2 ) ) ) );
t_egal( true, Connector::run_now( $aujourdhui ), 'charge de référence acceptée' );
$reference = Connector::snapshot_for( $aujourdhui );
t_egal( 2, $reference['niveau_source'], 'valeur de référence en cache' );

// ---------------------------------------------------------------------------
// Un motif de rejet par couche.
// ---------------------------------------------------------------------------
$rejets = array(
	'transport / corps trop court'    => array( 'transport', 'corps_trop_court', '{"a":1}' ),
	'transport / HTML sous un 200'    => array( 'transport', 'html_sous_200', '<html><body>Page d\'erreur servie en 200, avec assez d\'octets pour passer la borne basse.</body></html>' ),
	'forme / JSON illisible'          => array( 'forme', 'json_invalide', '{"schema":1,"zone":"13","jour":"' . $aujourdhui . '","niveau' ),
	'forme / version inconnue'        => array( 'forme', 'schema_inconnu', $corps( array( 'schema' => 2 ) ) ),
	'forme / niveau non entier'       => array( 'forme', 'type_invalide', $corps( array( 'niveau_source' => '2' ) ) ),
	'forme / niveau flottant'         => array( 'forme', 'type_invalide', $corps( array( 'niveau_source' => 2.5 ) ) ),
	'référentiel / zone divergente'   => array( 'referentiel', 'zone_divergente', $corps( array( 'zone' => '83' ) ) ),
	'sémantique / niveau négatif'     => array( 'semantique', 'niveau_negatif', $corps( array( 'niveau_source' => -1 ) ) ),
	'temporel / jour divergent'       => array( 'temporel', 'jour_divergent', $corps( array( 'jour' => $demain ) ) ),
	'temporel / publication périmée'  => array( 'temporel', 'publication_perimee', $corps( array( 'publie_le' => gmdate( DATE_ATOM, time() - ( 10 * DAY_IN_SECONDS ) ) ) ) ),
);

foreach ( $rejets as $intitule => $cas ) {
	list( $couche_attendue, $code_attendu, $charge ) = $cas;

	t_bouchon_http( $reponse( $charge ) );

	$r = Connector::run_now( $aujourdhui );

	t_assert( is_wp_error( $r ), 'rejet attendu — ' . $intitule, 'WP_Error', is_wp_error( $r ) ? 'WP_Error' : $r );

	if ( is_wp_error( $r ) ) {
		$donnees = $r->get_error_data();
		t_egal( $code_attendu, $r->get_error_code(), 'motif — ' . $intitule );
		t_egal( $couche_attendue, is_array( $donnees ) ? ( $donnees['couche'] ?? '' ) : '', 'couche d\'origine — ' . $intitule );
	}

	$apres = Connector::snapshot_for( $aujourdhui );
	t_egal( 2, $apres['niveau_source'] ?? null, 'la valeur précédente reste INTACTE — ' . $intitule );
}

// Une seule alerte de rejet pour cette date, malgré dix rejets d'affilée.
$rejets_courriel = 0;
foreach ( $boite as $courriel ) {
	if ( false !== strpos( (string) ( $courriel['subject'] ?? '' ), 'rejetée' ) ) {
		++$rejets_courriel;
	}
}
t_egal( 1, $rejets_courriel, 'verrou d\'alerte : UNE alerte de rejet pour cette date, jamais une par tentative' );

$etat = StateRepository::get();
t_assert( (int) $etat['echecs_consecutifs'] >= count( $rejets ), 'chaque rejet compte comme un échec', '>= ' . count( $rejets ), (int) $etat['echecs_consecutifs'] );
t_egal( 'rejet', $etat['journal'][ count( $etat['journal'] ) - 1 ]['issue'] ?? '', 'journal : issue « rejet » tracée' );

// ---------------------------------------------------------------------------
// CE QUI N'EST PAS UNE ABERRATION — à ne jamais réintroduire comme rejet.
// ---------------------------------------------------------------------------
$purge();

$non_aberrant = array(
	'valeur identique à celle déjà connue' => 2,
	'saut d\'amplitude quelconque'          => 11,
	'valeur au maximum plausible'           => 999,
	'valeur nulle'                          => 0,
);

foreach ( $non_aberrant as $intitule => $valeur ) {
	$purge();
	t_bouchon_http( $reponse( $corps( array( 'niveau_source' => $valeur ) ) ) );

	$r = Connector::run_now( $aujourdhui );
	t_egal( true, $r, 'accepté, ce n\'est PAS une aberration — ' . $intitule );
	t_egal( $valeur, Connector::snapshot_for( $aujourdhui )['niveau_source'] ?? null, 'jeton source stocké brut — ' . $intitule );
	t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et rien n\'est affiché pour autant — ' . $intitule );
}

// Deux journées différentes portant la même valeur : jamais un doublon. Ici les
// corps diffèrent parce que le format porte sa date — raison de plus pour que le
// hachage ne serve jamais de détecteur de non-publication.
$purge();
t_bouchon_http(
	static function ( $url ) use ( $reponse, $aujourdhui, $demain ) {
		$jour = false !== strpos( (string) $url, str_replace( '-', '', $demain ) ) ? $demain : $aujourdhui;

		return $reponse(
			(string) wp_json_encode(
				array(
					'schema'        => 1,
					'zone'          => '13',
					'jour'          => $jour,
					'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
					'niveau_source' => 2,
				)
			)
		);
	}
);

t_egal( true, Connector::run_now( $aujourdhui ), 'jour courant enregistré' );
t_egal( true, Connector::run_now( $demain ), 'lendemain enregistré alors qu\'il porte la MÊME valeur' );
t_egal( true, Connector::has_snapshot_for( $aujourdhui ), 'les deux journées coexistent — aujourd\'hui' );
t_egal( true, Connector::has_snapshot_for( $demain ), 'les deux journées coexistent — demain' );

// Une date hors de la plage aujourd'hui/demain est refusée AVANT tout octet.
$r = Connector::run_now( $hier );
t_assert( is_wp_error( $r ) && 'massifs_meteo_date_hors_plage' === $r->get_error_code(), 'une date hors plage est refusée avant tout octet réseau', 'massifs_meteo_date_hors_plage', is_wp_error( $r ) ? $r->get_error_code() : $r );
t_egal( false, Connector::has_snapshot_for( $hier ), 'et rien n\'est écrit pour cette date' );

$purge();
t_reset();
t_bilan();
