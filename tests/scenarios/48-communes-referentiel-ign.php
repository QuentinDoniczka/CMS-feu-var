<?php
/**
 * LE RÉFÉRENTIEL COMMUNAL IGN, DE L'ARTEFACT AU HTML SERVI.
 *
 * Recette nommée du contrat gelé de l'issue #45, §13 — contrôles 1, 2, 3, 5, 6
 * et 7 — plus les deux règles de domaine que le §4.3 et le §4.4 gèlent et que
 * personne n'avait encore fait rougir : le DÉPARTAGE par la plus grande part de
 * surface, et le PLAFOND de 5 km.
 *
 * Ce scénario ne teste aucune fonction isolée. Il joue une histoire complète :
 * la source EFFIS est bouchonnée à la frontière réseau, l'ingestion résout la
 * commune de chaque zone à partir de sa GÉOMÉTRIE, le relevé est persisté, puis
 * la page d'accueil est demandée EN HTTP RÉEL, sans exécuter une ligne de
 * JavaScript, et l'on regarde ce que le visiteur reçoit.
 *
 * Ordre :
 *
 *  A. L'artefact déployé — unicité des codes INSEE, Marseille une seule fois,
 *     aucun `LATEST` nulle part (§13.1, §13.2, §13.6, §2.1).
 *  B. UTF-8 verbatim à travers le seam et jusqu'au JSON public (§13.3).
 *  C. §4.3 — la commune vient de la géométrie, et le chevauchement se départage
 *     par la plus grande PART DE SURFACE. Contrôle FALSIFIABLE : les deux mêmes
 *     communes, deux rectangles, deux gagnants.
 *  D. §4.4 et §4.5 — hors couverture et au-delà du plafond : le serveur se tait.
 *     « Aucune commune » est un chemin NORMAL, pas un coin dégradé.
 *  E. La chaîne entière, en HTTP, sans JavaScript : le nom résolu est dans le
 *     HTML rendu par PHP, échappé exactement une fois, et la paire est
 *     purement omise pour la zone sur laquelle le serveur se tait.
 *  F. §13.7 — aucune origine tierce, et l'artefact communal n'est jamais servi.
 *  G. L'attribution IGN de la Licence Ouverte 2.0, sur les deux gabarits.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Runner;
use Massifs\Ingest\Effis\Settings;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options du module EFFIS, que `t_reset()` ne connaît pas.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

/**
 * Rectangle GeoJSON, coins sud-ouest et nord-est. Fixture de scénario.
 *
 * @param float $ouest Longitude ouest.
 * @param float $sud   Latitude sud.
 * @param float $est   Longitude est.
 * @param float $nord  Latitude nord.
 *
 * @return array<string, mixed>
 */
function t_rect( float $ouest, float $sud, float $est, float $nord ): array {
	return array(
		'type'        => 'Polygon',
		'coordinates' => array(
			array(
				array( $ouest, $sud ),
				array( $est, $sud ),
				array( $est, $nord ),
				array( $ouest, $nord ),
				array( $ouest, $sud ),
			),
		),
	);
}

/**
 * Carré centré sur un point, demi-côté en degrés.
 *
 * @param float $lon Longitude du centre.
 * @param float $lat Latitude du centre.
 * @param float $d   Demi-côté, en degrés.
 *
 * @return array<string, mixed>
 */
function t_carre( float $lon, float $lat, float $d = 0.002 ): array {
	return t_rect( $lon - $d, $lat - $d, $lon + $d, $lat + $d );
}

/**
 * GET intra-stack d'une page publique, sans redirection canonique.
 *
 * `siteurl` vaut `http://localhost:3002` — l'adresse vue de l'HÔTE. Depuis le
 * conteneur d'outillage, ce port n'écoute nulle part : une requête sur
 * `http://wordpress/` reçoit d'abord la redirection canonique de WordPress vers
 * `localhost:3002`, que le conteneur ne peut pas suivre, et la lecture échoue
 * pour une raison qui n'a rien à voir avec ce que le scénario éprouve. L'en-tête
 * `Host` fait correspondre la requête à l'adresse du site : WordPress ne
 * redirige plus, et les octets rendus sont EXACTEMENT ceux qu'un navigateur
 * recevrait.
 *
 * @param string $chemin Chemin absolu, `/` compris.
 *
 * @return array{code:int,corps:string}
 */
function t_page( string $chemin ): array {
	$hote = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
	$port = wp_parse_url( (string) get_option( 'home' ), PHP_URL_PORT );

	$reponse = wp_remote_get(
		'http://wordpress' . $chemin,
		array(
			'timeout' => 30,
			'headers' => array( 'Host' => $hote . ( $port ? ':' . $port : '' ) ),
		)
	);

	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $reponse ),
		'corps' => is_wp_error( $reponse ) ? $reponse->get_error_message() : (string) wp_remote_retrieve_body( $reponse ),
	);
}

/**
 * Origines absolues rencontrées dans un texte.
 *
 * @param string $texte HTML ou CSS.
 *
 * @return list<string>
 */
function t_origines( string $texte ): array {
	$trouvees = array();
	preg_match_all( '#https?://[^"\'\s<>()]+#i', $texte, $correspondances );

	foreach ( $correspondances[0] as $url ) {
		$hote = wp_parse_url( $url, PHP_URL_HOST );

		if ( is_string( $hote ) && '' !== $hote ) {
			$trouvees[ strtolower( $hote ) ] = true;
		}
	}

	return array_keys( $trouvees );
}

t_reset();
t_effis_purge();

// Armement en cours de requête : l'URL vise la stack, la source réelle est
// inatteignable par construction, et les réponses restent bouchonnées.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

t_egal( false, Settings::is_disabled(), 'module EFFIS réarmé en cours de requête' );

// ---------------------------------------------------------------------------
// A. L'ARTEFACT DÉPLOYÉ — §13.1, §13.2, §13.6.
// ---------------------------------------------------------------------------

$racine  = WP_PLUGIN_DIR . '/massifs-core';
$chemin  = $racine . '/includes/domain/massifs/communes-13.lookup.json';
$brut    = is_readable( $chemin ) ? (string) file_get_contents( $chemin ) : '';
$artefact = json_decode( $brut, true );

t_assert( is_array( $artefact ) && isset( $artefact['communes'] ), 'l\'artefact de lookup est déployé et lisible dans le conteneur', $chemin, substr( $brut, 0, 80 ) );

$insee = array_map(
	static function ( array $commune ): string {
		return (string) $commune['insee'];
	},
	$artefact['communes']
);

t_egal( 298, count( $insee ), '§13 : 298 communes — le 13 et ses départements limitrophes (§4.2)' );
t_egal( count( $insee ), count( array_unique( $insee ) ), '§13.1 : chaque code INSEE est présent une seule fois' );
t_egal( 1, count( array_keys( $insee, '13055', true ) ), '§13.2 : Marseille 13055 exactement une fois — aucun arrondissement municipal n\'a été importé' );

$arrondissements = array_values(
	array_filter(
		$insee,
		static function ( string $code ): bool {
			// COG CARTO publie les arrondissements municipaux sous 132xx (Marseille),
			// 1320x pour Lyon/Paris n'existe pas ici : ce filtre rougit si un
			// arrondissement marseillais se glisse dans l'extrait.
			return 1 === preg_match( '/^132(0[1-9]|1[0-6])$/', $code );
		}
	)
);
t_egal( array(), $arrondissements, '§13.2 : aucun arrondissement municipal marseillais dans l\'extrait' );

$departements = array_values( array_unique( array_map( static function ( array $c ): string {
	return (string) $c['dep'];
}, $artefact['communes'] ) ) );
sort( $departements );
t_egal( array( '13', '30', '83', '84' ), $departements, '§4.2 : la zone tampon couvre bien le 13 et ses trois départements limitrophes' );

$vides = array_values( array_filter( $artefact['communes'], static function ( array $c ): bool {
	return '' === trim( (string) $c['nom'] );
} ) );
t_egal( array(), $vides, 'aucun `nom_officiel` vide sur les 298' );

// §13.6 — `LATEST` ne s'écrit dans AUCUN artefact produit.
$artefacts = array(
	'communes-13.lookup.json'                     => $chemin,
	'data/massifs-13.php'                         => $racine . '/data/massifs-13.php',
	'build/reference.json'                        => $racine . '/includes/domain/massifs/build/reference.json',
	'build/source/communes-13-limitrophes.manifeste.json' => $racine . '/includes/domain/massifs/build/source/communes-13-limitrophes.manifeste.json',
);

foreach ( $artefacts as $etiquette => $fichier ) {
	$contenu = is_readable( $fichier ) ? (string) file_get_contents( $fichier ) : '';
	t_assert( '' !== $contenu, 'artefact lisible : ' . $etiquette, 'non vide', 'vide/illisible' );
	t_assert( ! str_contains( $contenu, 'LATEST' ), '§13.6 : aucun `LATEST` dans ' . $etiquette . ' — le millésime est résolu, jamais aliasé', 'aucune occurrence', 'occurrence trouvée' );
}

$millesime = massifs_attribution_communes()['faits']['millesime'];
t_egal( '2026', $millesime, '§2.1 : le millésime consigné est daté, résolu par mesure' );
t_assert( str_contains( massifs_attribution_communes()['phrase'], '2026' ), '§2.1 : la phrase d\'attribution porte le millésime résolu', '…2026…', massifs_attribution_communes()['phrase'] );

// ---------------------------------------------------------------------------
// B. UTF-8 VERBATIM À TRAVERS LE SEAM — §13.3.
// ---------------------------------------------------------------------------

// Un point intérieur mesuré pour chacune des trois communes que le §13.3 nomme.
$temoins = array(
	'Lançon-Provence'            => array( 5.1018, 43.5952 ),
	'Belcodène'                  => array( 5.5618, 43.4211 ),
	'Saint-Pierre-de-Mézoargues' => array( 4.6454, 43.8652 ),
);

foreach ( $temoins as $attendu => $point ) {
	$resolue = massifs_commune_de_la_zone( t_carre( $point[0], $point[1] ) );

	t_egal( $attendu, $resolue['nom'], '§13.3 : « ' . $attendu .' » traverse le seam SANS mutation ni double échappement' );
	t_egal( true, $resolue['trouvee'], '§13.3 : « ' . $attendu . ' » est bien résolue' );
	t_egal( 'communes_ok', $resolue['etat'], '§13.3 : état nominal pour « ' . $attendu . ' »' );
	t_egal( 0, $resolue['distance_m'], '§13.3 : la zone chevauche « ' . $attendu . ' », donc distance nulle' );
	t_assert(
		$resolue['nom'] === html_entity_decode( $resolue['nom'], ENT_QUOTES, 'UTF-8' ),
		'§13.3 : « ' . $attendu . ' » n\'est PAS pré-échappé par le module — l\'échappement appartient au point de sortie',
		$attendu,
		$resolue['nom']
	);
	t_egal( $attendu, massifs_commune_de_la_zone_nom( t_carre( $point[0], $point[1] ) ), '§5 : la commodité rend le même nom que la fonction totale' );
}

// Toutes les clés du contrat, toujours présentes : le consommateur n'écrit
// jamais `isset()`.
$cles = array_keys( massifs_commune_de_la_zone( t_carre( 5.1018, 43.5952 ) ) );
sort( $cles );
t_egal( array( 'departement', 'distance_m', 'etat', 'insee', 'nom', 'trouvee' ), $cles, '§5 : les six clés du retour total, ni plus ni moins' );

// Le même UTF-8, mais dans le JSON public servi en HTTP : c'est là que le
// visiteur le reçoit.
$reponse_json = wp_remote_get( 'http://wordpress/wp-json/massifs/v1/statuts', array( 'timeout' => 20 ) );
t_egal( 200, (int) wp_remote_retrieve_response_code( $reponse_json ), 'la route publique répond 200 sans authentification' );

$json = json_decode( (string) wp_remote_retrieve_body( $reponse_json ), true );
t_assert( is_array( $json ) && isset( $json['massifs'] ), 'la route publique rend un objet exploitable', 'array', gettype( $json ) );
t_egal( 'calculee', $json['referentiel']['communes_statut'], '§7 / §14.1 : `communes_statut` vaut « calculee », jamais « inconnue » en nominal' );

$noms_publies = array();
$massifs_sans_communes = array();

foreach ( $json['massifs'] as $ligne ) {
	if ( ! isset( $ligne['communes'] ) || array() === $ligne['communes'] ) {
		$massifs_sans_communes[] = $ligne['code'];
		continue;
	}

	foreach ( $ligne['communes'] as $nom ) {
		$noms_publies[ $nom ] = true;
	}
}

t_egal( array(), $massifs_sans_communes, '§6 : les 25 massifs actifs portent tous leurs communes dans le JSON public' );
t_egal( 25, count( $json['massifs'] ), '25 massifs actifs servis' );
t_assert( isset( $noms_publies['Lançon-Provence'] ), '§13.3 : « Lançon-Provence » arrive intact jusqu\'au JSON public', 'présent', array_slice( array_keys( $noms_publies ), 0, 5 ) );
t_assert( isset( $noms_publies['Belcodène'] ), '§13.3 : « Belcodène » arrive intact jusqu\'au JSON public', 'présent', 'absent' );

$codes_insee_publies = array_values( array_filter( array_keys( $noms_publies ), static function ( string $nom ): bool {
	return 1 === preg_match( '/^\d{5}$/', $nom );
} ) );
t_egal( array(), $codes_insee_publies, '§11.6 : AUCUN code INSEE dans `massifs[].communes` — ce tableau porte des noms' );

// ---------------------------------------------------------------------------
// C. §4.3 — DÉPARTAGE PAR LA PLUS GRANDE PART DE SURFACE, ET SA FALSIFIABILITÉ.
// ---------------------------------------------------------------------------

/*
 * Deux rectangles posés sur la MÊME frontière communale — Peypin (13073) au
 * sud, Belcodène (13013) au nord — et décalés de 0,003° l'un par rapport à
 * l'autre. Les deux chevauchent les deux mêmes communes ; seule la part change.
 *
 * Le premier donne la majorité à PEYPIN, dont le code INSEE est le PLUS GRAND :
 * c'est ce qui rend le contrôle falsifiable. Un départage qui retomberait sur
 * l'ordre de l'artefact (code INSEE croissant) répondrait « Belcodène » aux
 * DEUX rectangles, et la première assertion rougirait sans que la seconde
 * bouge. Sans ce couple, un tri par INSEE passerait pour une règle de surface.
 */
$rect_peypin    = t_rect( 5.5720, 43.4000, 5.5780, 43.4060 );
$rect_belcodene = t_rect( 5.5720, 43.4030, 5.5780, 43.4090 );

$gagnant_sud  = massifs_commune_de_la_zone( $rect_peypin );
$gagnant_nord = massifs_commune_de_la_zone( $rect_belcodene );

t_egal( 'Peypin', $gagnant_sud['nom'], '§4.3 : à cheval sur deux communes, la plus grande PART DE SURFACE l\'emporte — ici le code INSEE le PLUS GRAND (13073)' );
t_egal( '13073', $gagnant_sud['insee'], '§4.3 : le départage n\'est pas un tri par code INSEE' );
t_egal( 0, $gagnant_sud['distance_m'], '§5 : une zone chevauchante est à distance nulle' );

t_egal( 'Belcodène', $gagnant_nord['nom'], '§4.3 : le même couple de communes, la part inversée, l\'autre commune l\'emporte' );
t_egal( '13013', $gagnant_nord['insee'], '§4.3 : contrôle falsifiable — le gagnant SUIT la part, il n\'est pas constant' );
t_egal( 0, $gagnant_nord['distance_m'], '§5 : distance nulle des deux côtés' );

t_egal( '13', $gagnant_nord['departement'], '§5 : le département voyage avec la commune' );

/*
 * Interdit 7.d — la distance se mesure depuis la GÉOMÉTRIE, jamais depuis un
 * point qui la résume. La zone est un U ouvert vers l'est dont le centre
 * d'emprise tombe DANS LE CREUX, donc hors de la zone et dans une autre
 * commune. Les deux méthodes donnent deux réponses différentes.
 */
$zone_en_u = array(
	'type'        => 'Polygon',
	'coordinates' => array(
		array(
			array( 5.5600, 43.3900 ),
			array( 5.5900, 43.3900 ),
			array( 5.5900, 43.3954 ),
			array( 5.5684, 43.3954 ),
			array( 5.5684, 43.4146 ),
			array( 5.5900, 43.4146 ),
			array( 5.5900, 43.4200 ),
			array( 5.5600, 43.4200 ),
			array( 5.5600, 43.3900 ),
		),
	),
);

t_egal( 'Peypin', massifs_commune_de_la_zone_nom( $zone_en_u ), 'interdit 7.d : la commune vient de la géométrie entière de la zone' );
t_egal( 'Belcodène', massifs_commune_de_la_zone_nom( t_carre( 5.5750, 43.4050 ) ), 'interdit 7.d : le centre de l\'emprise de ce U désigne une AUTRE commune — l\'assertion ci-dessus sait rougir' );

// Un `MultiPolygon` — la forme que la source publie réellement — traverse aussi.
$multi = array(
	'type'        => 'MultiPolygon',
	'coordinates' => array(
		t_carre( 5.1018, 43.5952 )['coordinates'],
		t_carre( 5.1028, 43.5962 )['coordinates'],
	),
);
t_egal( 'Lançon-Provence', massifs_commune_de_la_zone_nom( $multi ), '§5 : `MultiPolygon` est accepté au même titre que `Polygon`' );

// ---------------------------------------------------------------------------
// D. §4.4 / §4.5 — LE SILENCE EST UN CHEMIN NORMAL.
// ---------------------------------------------------------------------------

/*
 * En mer, à plus de 5 km de toute commune, mais DANS l'emprise couverte : le
 * filtre EFFIS conserve les entités par intersection de bbox, une zone dont
 * l'emprise effleure le rectangle départemental garde sa géométrie entière et
 * son point représentatif peut tomber en pleine Méditerranée. Le chemin est
 * normal, pas dégradé.
 */
$au_large = massifs_commune_de_la_zone( t_carre( 5.30, 43.10 ) );

t_egal( false, $au_large['trouvee'], '§4.5 : au large, aucune commune — et c\'est une issue NORMALE du filtre livré' );
t_egal( 'communes_hors_couverture', $au_large['etat'], '§4.4 : l\'état nomme la cause plutôt que de se taire muettement' );
t_egal( '', $au_large['nom'], '§4.4 : aucun nom trompeur n\'est composé au-delà du plafond' );
t_egal( '', $au_large['insee'], '§4.4 : ni code' );
t_egal( null, $au_large['distance_m'], '§4.4 : aucune distance inventée' );
t_egal( '', massifs_commune_de_la_zone_nom( t_carre( 5.30, 43.10 ) ), '§5 : la commodité rend la chaîne vide, que le gabarit omet' );

/*
 * FALSIFIABILITÉ DU PLAFOND : la même mer, mais à 2,4 km de Marseille. Si le
 * silence ci-dessus venait de « la zone est en mer » et non du plafond, ce
 * contrôle-ci resterait muet lui aussi.
 */
$au_large_proche = massifs_commune_de_la_zone( t_carre( 5.35, 43.16 ) );

t_egal( true, $au_large_proche['trouvee'], '§4.4 : en mer AUSSI, sous le plafond, la commune est résolue — c\'est la distance qui tranche, pas l\'élément' );
t_egal( 'Marseille', $au_large_proche['nom'], '§4.4 : la commune du littoral le plus proche' );
t_assert(
	is_int( $au_large_proche['distance_m'] ) && $au_large_proche['distance_m'] > 0 && $au_large_proche['distance_m'] <= 5000,
	'§4.4 : la distance est mesurée, non nulle, et jamais au-dessus du plafond',
	'0 < d <= 5000',
	$au_large_proche['distance_m']
);
t_note( 'distance mesurée au large de Marseille : ' . $au_large_proche['distance_m'] . ' m' );

/*
 * La zone doit tenir ENTIÈRE dans la couverture. Ce rectangle-ci a une commune
 * à quelques centaines de mètres, mais son bord sud descend sous la couverture
 * de l'artefact : le serveur se tait, parce qu'une commune hors extrait
 * pourrait être plus proche et qu'il nommerait alors la deuxième en la
 * présentant comme la plus proche.
 */
$couverture_sud = 43.05254;
$a_cheval       = t_rect( 5.60, $couverture_sud - 0.01, 5.62, $couverture_sud + 0.01 );

t_egal( 'communes_hors_couverture', massifs_commune_de_la_zone( $a_cheval )['etat'], '§4.5 : une zone qui DÉBORDE la couverture est refusée en bloc — l\'emprise entière est testée, pas un point' );

// Hors de tout : Nice, à 130 km à l'est de l'extrait.
t_egal( 'communes_hors_couverture', massifs_commune_de_la_zone( t_carre( 7.262, 43.71 ) )['etat'], '§4.5 : très loin de l\'extrait, silence explicite' );

// Géométrie inexploitable : on ne sait pas, et on le dit.
t_egal( 'communes_inconnues', massifs_commune_de_la_zone( array( 'type' => 'Point', 'coordinates' => array( 5.37, 43.29 ) ) )['etat'], '§7 : une géométrie qu\'on n\'a pas su lire ne fait deviner AUCUNE commune' );
t_egal( 'communes_inconnues', massifs_commune_de_la_zone( array() )['etat'], '§7 : géométrie vide, même refus explicite' );
t_egal( 'communes_inconnues', massifs_commune_de_la_zone( array( 'type' => 'Polygon', 'coordinates' => array( array( array( 'x', 43.0 ) ) ) ) )['etat'], '§7 : coordonnée non numérique — refusée, jamais redressée' );

// ---------------------------------------------------------------------------
// E. LA CHAÎNE ENTIÈRE, EN HTTP, SANS JAVASCRIPT.
// ---------------------------------------------------------------------------

/*
 * Trois zones : deux sur des communes réelles, une en pleine mer au-delà du
 * plafond. LES DEUX BRANCHES du §4.4 traversent donc la même ingestion, la même
 * persistance et le même rendu — c'est ce qui fait de ce contrôle une histoire
 * plutôt qu'une paire d'assertions.
 */
$charge = array(
	'type'     => 'FeatureCollection',
	'features' => array(
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-recette-lancon',
				'surface_ha'           => 120.0,
				'premiere_observation' => '2026-08-12T09:30:00Z',
				'derniere_observation' => '2026-08-13T21:05:00Z',
			),
			'geometry'   => t_carre( 5.1018, 43.5952, 0.002 ),
		),
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-recette-belcodene',
				'surface_ha'           => 64.0,
				'premiere_observation' => '2026-08-12T10:00:00Z',
				'derniere_observation' => '2026-08-13T20:00:00Z',
			),
			'geometry'   => t_carre( 5.5618, 43.4211, 0.002 ),
		),
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-recette-berre',
				'surface_ha'           => 51.0,
				'premiere_observation' => '2026-08-12T08:00:00Z',
				'derniere_observation' => '2026-08-13T18:30:00Z',
			),
			// Berre-l'Étang porte une APOSTROPHE : c'est ce qui rend le contrôle
			// d'échappement falsifiable. Sans un nom qui contient un caractère à
			// échapper, « échappé exactement une fois » et « jamais échappé »
			// produisent les mêmes octets et l'assertion ne prouve rien.
			'geometry'   => t_carre( 5.1187, 43.5033, 0.002 ),
		),
		array(
			'type'       => 'Feature',
			'properties' => array(
				'id'                   => 'zpf-recette-au-large',
				'surface_ha'           => 90.0,
				'premiere_observation' => '2026-08-12T11:00:00Z',
				'derniere_observation' => '2026-08-13T19:00:00Z',
			),
			// EN MER, et pourtant RETENUE : son emprise recoupe le rectangle
			// départemental (bord sud 43,15731) alors que sa géométrie entière est
			// à plus de 17 km de la commune la plus proche. C'est exactement le cas
			// que le §4.5 décrit — le filtre EFFIS conserve par INTERSECTION de
			// bbox, donc « aucune commune » est une issue normale du filtre livré.
			'geometry'   => t_rect( 4.99, 43.10, 5.01, 43.17 ),
		),
	),
);

/*
 * Bouchon SÉLECTIF : il ne court-circuite que l'URL du connecteur EFFIS et
 * laisse passer les requêtes vers notre propre serveur. Sans cette sélectivité,
 * le GET de la page d'accueil plus bas recevrait la charge EFFIS à la place du
 * HTML, et le contrôle le plus important du scénario deviendrait vert pour une
 * raison qui n'a rien à voir avec ce qu'il éprouve.
 */
$appels_sortants = array();

add_filter(
	'pre_http_request',
	static function ( $court_circuit, $args, $url ) use ( &$appels_sortants, $charge ) {
		if ( ! str_contains( (string) $url, 'massifs-bouchon-effis' ) ) {
			return $court_circuit;
		}

		$appels_sortants[] = (string) $url;

		return t_reponse_200( $charge );
	},
	10,
	3
);

$verdict = Runner::executer();
t_assert( true === $verdict, 'le relevé EFFIS est accepté', true, is_wp_error( $verdict ) ? $verdict->get_error_code() : $verdict );
t_egal( 1, count( $appels_sortants ), 'un seul appel sortant, et il reste dans la stack' );
t_egal( 'wordpress', (string) wp_parse_url( (string) ( $appels_sortants[0] ?? '' ), PHP_URL_HOST ), 'aucun domaine tiers n\'est atteint pendant l\'ingestion' );

$couche = massifs_zones_parcourues_par_le_feu();
t_egal( 'zones_disponibles', $couche['etat'], 'quatre zones retenues, la couche est servie' );
t_egal( 4, $couche['nombre'], 'les quatre zones recoupent l\'emprise départementale' );

$par_id = array();

foreach ( $couche['zones'] as $zone ) {
	$par_id[ $zone['id'] ] = $zone['commune_la_plus_proche'];
}

t_egal( 'Lançon-Provence', $par_id['zpf-recette-lancon'] ?? '', '§3 : la commune est RÉSOLUE À L\'INGESTION et figée dans le relevé' );
t_egal( 'Belcodène', $par_id['zpf-recette-belcodene'] ?? '', '§3 : chaque zone résout SA commune — la valeur n\'est pas constante' );
t_egal( 'Berre-l\'Étang', $par_id['zpf-recette-berre'] ?? '', '§13.3 : une apostrophe traverse le domaine BRUTE, jamais pré-échappée' );
t_egal( '', $par_id['zpf-recette-au-large'] ?? 'x', '§4.4 : la zone au large repart avec la chaîne vide, jamais avec un nom' );

// La résolution est figée DANS L'OPTION persistée : le chemin de rendu ne
// rouvre jamais la géométrie communale.
$releve_persiste = get_option( 'massifs_effis_releve' );
$noms_persistes  = is_array( $releve_persiste ) && isset( $releve_persiste['zones'] ) && is_array( $releve_persiste['zones'] )
	? array_column( $releve_persiste['zones'], 'commune_la_plus_proche' )
	: array();
sort( $noms_persistes );

t_egal(
	array( '', 'Belcodène', 'Berre-l\'Étang', 'Lançon-Provence' ),
	$noms_persistes,
	'§3 : les noms sont PERSISTÉS avec le relevé — dont le silence de la zone au large. Le chemin de rendu ne rouvre jamais la géométrie communale'
);

// --- Le HTML, tel qu'un visiteur sans JavaScript le reçoit. -----------------

/*
 * LE GABARIT RÉEL, ALIMENTÉ PAR LA FONCTION DE LECTURE RÉELLE, elle-même
 * alimentée par le relevé que l'ingestion vient de persister. Rien n'est
 * fabriqué à la main : la géométrie est entrée par `wp_remote_get`, la commune
 * a été résolue par le domaine, le nom a été écrit en base, relu, et c'est
 * cette valeur-là que le gabarit reçoit.
 *
 * POURQUOI PAS EN HTTP. La stack Docker désarme délibérément le connecteur
 * EFFIS en environnement `local` tant qu'aucune constante d'URL n'est posée
 * (`Settings::is_disabled()`), et une constante ne traverse pas d'un processus
 * wp-cli à une requête Apache. La page d'accueil servie en HTTP rend donc
 * « Donnée momentanément indisponible » quoi qu'il arrive sur cette stack —
 * c'est une propriété du garde-fou d'ingestion, pas de cette issue. Le contrôle
 * HTTP de la page d'accueil est joué plus bas pour ce qu'il peut prouver ; la
 * jonction gabarit/extension est prouvée ici, contre le gabarit réel.
 */
$html = ( static function ( array $couche_reelle ): string {
	ob_start();
	get_template_part( 'templates/parts/panneau-feu', null, array( 'zones_parcourues' => $couche_reelle ) );

	return (string) ob_get_clean();
} )( $couche );

t_assert( str_contains( $html, 'Zones parcourues par le feu' ), 'la partie « zones parcourues » est rendue par le serveur', 'titre présent', 'absent' );
t_assert( ! str_contains( $html, '<script' ), 'contrainte #3 : le gabarit ne produit AUCUN script — l\'information ne dépend pas de JavaScript', 'aucun <script', 'trouvé' );
t_egal( 3, substr_count( $html, 'Commune la plus proche' ), 'contrainte #3 : TROIS paires « Commune la plus proche » dans le HTML rendu par PHP — une par zone résolue, aucune pour celle sur laquelle le serveur se tait' );
t_assert( str_contains( $html, '<dd class="zones-parcourues__valeur">Lançon-Provence</dd>' ), 'contrainte #3 : le nom est dans le HTML rendu par PHP, sans exécuter une ligne de JavaScript', '<dd …>Lançon-Provence</dd>', 'absent' );
t_assert( str_contains( $html, '<dd class="zones-parcourues__valeur">Belcodène</dd>' ), 'contrainte #3 : la seconde commune aussi', '<dd …>Belcodène</dd>', 'absent' );

/*
 * ÉCHAPPEMENT : EXACTEMENT UNE FOIS, ET LE CONTRÔLE SAIT ROUGIR.
 *
 * « Lançon-Provence » et « Belcodène » n'ont aucun caractère à échapper : sur
 * eux, « échappé une fois » et « jamais échappé » produisent les MÊMES octets, et
 * une assertion ne prouverait rien. « Berre-l'Étang » porte une apostrophe, que
 * `esc_html()` rend `&#039;` : les trois états — brut, échappé une fois, échappé
 * deux fois — deviennent alors distinguables, et c'est le seul montage qui rend
 * la règle vérifiable.
 */
t_assert( str_contains( $html, '<dd class="zones-parcourues__valeur">Berre-l&#039;Étang</dd>' ), 'échappement : l\'apostrophe est échappée UNE FOIS par le gabarit', '<dd …>Berre-l&#039;Étang</dd>', 'absent' );
t_assert( ! str_contains( $html, 'Berre-l\'Étang</dd>' ), 'échappement : la valeur brute ne fuit JAMAIS telle quelle dans le HTML', 'aucune apostrophe nue', 'apostrophe nue rendue' );
t_assert( ! str_contains( $html, '&amp;#039;' ), 'échappement : et jamais deux fois — le module rend du BRUT, le point de sortie échappe', 'aucun &amp;#039;', 'double échappement trouvé' );
t_assert( ! str_contains( $html, 'Lan&ccedil;on' ) && ! str_contains( $html, 'Belcod&egrave;ne' ), 'échappement : les accents ne sont pas transformés en entités — l\'UTF-8 traverse intact', 'aucune entité d\'accent', 'entité HTML trouvée' );

// La branche silencieuse : aucun substitut n'est fabriqué.
t_assert( ! str_contains( $html, 'non renseigné' ), '§4.4 : aucune mention « non renseigné » ne remplace le nom absent', 'aucune', 'trouvée' );
t_assert( ! str_contains( $html, '<dd class="zones-parcourues__valeur">—</dd>' ), '§4.4 : aucun tiret ne remplace le nom absent — la paire est PUREMENT omise', 'aucun', 'trouvé' );

// La géométrie de la zone ne traverse jamais la frontière du rendu.
t_assert( ! str_contains( $html, 'coordinates' ) && ! str_contains( $html, 'Polygon' ), 'interdit 5 : la géométrie n\'est jamais rendue — le gabarit rend du texte', 'aucune géométrie', 'trouvée' );

// --- La page d'accueil, en HTTP réel, sans JavaScript. ----------------------

$accueil = t_page( '/' );
t_egal( 200, $accueil['code'], 'la page d\'accueil répond 200' );
t_note( 'accueil : ' . strlen( $accueil['corps'] ) . ' octets' );

$page = $accueil['corps'];

t_assert( str_contains( $page, 'Zones parcourues par le feu' ), 'la section « zones parcourues » est dans le HTML servi', 'titre présent', 'absent' );

if ( preg_match( '#<section id="zones-parcourues".*?</section>#s', $page, $extrait ) ) {
	t_note( 'section servie en HTTP : ' . substr( preg_replace( '/\s+/', ' ', $extrait[0] ), 0, 400 ) );
	t_note( 'NON COUVERT EN HTTP : le connecteur EFFIS est désarmé sur la stack `local`, la section ne peut pas porter de zone. Voir le bloc ci-dessus, joué contre le gabarit réel.' );
}

// Les statuts de massif, eux, sont bien dans le HTML rendu par PHP.
t_assert( str_contains( $page, 'massif' ) || str_contains( $page, 'Massif' ), 'contrainte #3 : les massifs sont dans le HTML servi', 'présents', 'absents' );
t_assert( str_contains( $page, 'officiel' ), 'DoD §12 : la page portant les statuts porte son bandeau de non-officialité', 'mention présente', 'absente' );

// ---------------------------------------------------------------------------
// F. §13.7 — AUCUNE ORIGINE TIERCE, ET L'ARTEFACT N'EST JAMAIS SERVI.
// ---------------------------------------------------------------------------

$pages = array(
	'accueil'          => '/',
	'la démarche'      => '/la-demarche/',
	'mentions légales' => '/mentions-legales/',
	'accessibilité'    => '/accessibilite/',
);

$origines_vues = array();

foreach ( $pages as $etiquette => $chemin_page ) {
	$reponse = t_page( $chemin_page );
	t_egal( 200, $reponse['code'], 'page servie : ' . $etiquette );

	$corps = $reponse['corps'];

	t_assert( ! str_contains( $corps, 'geopf.fr' ), '§13.7 : aucune URL `geopf.fr` dans ' . $etiquette . ' — l\'acquisition IGN est hors ligne, au build', 'aucune', 'trouvée' );
	t_assert( 0 === preg_match( '#https?://([a-z0-9.-]*\.)?ign\.fr#i', $corps ), '§13.7 : aucune URL `ign.fr` dans ' . $etiquette, 'aucune', 'trouvée' );

	// Rien de tiers n'est CHARGÉ par le navigateur : les `src` et les `link`
	// sont les seules balises qui déclenchent une requête sans clic.
	preg_match_all( '#(?:src|srcset)="([^"]+)"|<link[^>]+href="([^"]+)"#i', $corps, $ressources );
	$absolues = array_filter( array_merge( $ressources[1], $ressources[2] ), static function ( string $u ): bool {
		return 1 === preg_match( '#^https?://#i', $u );
	} );

	foreach ( $absolues as $u ) {
		$hote = (string) wp_parse_url( $u, PHP_URL_HOST );
		t_egal( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ), $hote, 'contrainte #2 : ressource chargée par le navigateur servie depuis notre hôte (' . $etiquette . ') — ' . $u );
	}

	$origines_vues = array_merge( $origines_vues, t_origines( $corps ) );
}

$origines_vues = array_values( array_unique( $origines_vues ) );
sort( $origines_vues );
t_note( 'origines absolues rencontrées (liens compris, requêtes ou non) : ' . implode( ', ', $origines_vues ) );

// L'artefact communal est strictement serveur : le garde d'exposition le refuse.
foreach ( array(
	'includes/domain/massifs/communes-13.lookup.json',
	'includes/domain/massifs/communes.php',
	'includes/domain/massifs/build/source/communes-13-limitrophes.geojson',
	'includes/domain/massifs/build/communes.mjs',
) as $relatif ) {
	$sonde = wp_remote_get( 'http://wordpress/wp-content/plugins/massifs-core/' . $relatif, array( 'timeout' => 20 ) );
	t_egal( 403, (int) wp_remote_retrieve_response_code( $sonde ), '§3.1 : jamais servi au navigateur — ' . $relatif );
}

// ---------------------------------------------------------------------------
// G. ATTRIBUTION — §9, Licence Ouverte 2.0.
// ---------------------------------------------------------------------------

$attribution = massifs_attribution_communes();

foreach ( array( 'phrase', 'phrase_courte', 'lien_source', 'lien_licence' ) as $cle ) {
	t_assert( '' !== $attribution[ $cle ], '§5 : `massifs_attribution_communes()` est TOTALE — ' . $cle . ' est peuplée', 'non vide', $attribution[ $cle ] );
}

t_assert( $attribution['phrase'] !== massifs_attribution()['phrase'], '§5 : l\'attribution IGN est SÉPARÉE de celle de la DDTM — deux producteurs, deux licences', 'deux phrases distinctes', $attribution['phrase'] );

foreach ( array( 'la démarche' => '/la-demarche/', 'mentions légales' => '/mentions-legales/' ) as $etiquette => $chemin_page ) {
	$corps = t_page( $chemin_page )['corps'];

	t_assert(
		str_contains( $corps, esc_html( $attribution['phrase'] ) ),
		'§9 : la mention IGN est rendue VERBATIM du serveur sur « ' . $etiquette . ' » — le thème ne compose aucune phrase de licence',
		$attribution['phrase'],
		'absente'
	);
	t_assert( str_contains( $corps, 'Référentiel communal' ), '§9 : la sixième source est nommée sur « ' . $etiquette . ' »', 'présente', 'absente' );
	t_assert( ! str_contains( $corps, 'LATEST' ), '§2.1 : aucun alias `LATEST` rendu au visiteur sur « ' . $etiquette . ' »', 'aucun', 'trouvé' );
}

// ---------------------------------------------------------------------------
// MÉNAGE — le scénario ne laisse aucun état derrière lui.
// ---------------------------------------------------------------------------

t_effis_purge();
t_reset();

t_egal( 'couche_effis_indisponible', massifs_zones_parcourues_par_le_feu()['etat'], 'MÉNAGE : le relevé de recette est retiré, la stack repart propre' );

t_bilan();
