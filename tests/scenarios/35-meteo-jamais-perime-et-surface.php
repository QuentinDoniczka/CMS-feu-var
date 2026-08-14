<?php
/**
 * §4.2 appliqué au danger météo, et la surface contractuelle de lecture.
 *
 * Deux choses, indissociables : il n'existe AUCUN chemin par lequel une donnée
 * d'un autre jour pourrait être servie comme courante — l'absence d'accesseur
 * « dernier instantané » est vérifiée, pas seulement écrite — et la fonction de
 * lecture est TOTALE, avec toutes ses clés, dans les trois états.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Meteo\Connector;
use Massifs\Ingest\Meteo\Lecture;

$purge = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge();

add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$aujourdhui = massifs_jour_courant();
$demain     = massifs_jour_suivant();
$hier       = t_jour_avant( $aujourdhui );

// ---------------------------------------------------------------------------
// 1. Aucun accesseur « dernier instantané », et c'est VÉRIFIÉ.
// ---------------------------------------------------------------------------
foreach ( array( 'latest', 'last', 'dernier', 'dernier_instantane', 'current', 'courant', 'snapshot' ) as $interdit ) {
	t_assert( ! method_exists( Connector::class, $interdit ), 'aucun accesseur « ' . $interdit . '() » sur la façade', false, method_exists( Connector::class, $interdit ) );
}

t_assert( method_exists( Connector::class, 'snapshot_for' ), 'toute lecture EXIGE une date : `snapshot_for()` existe' );
t_assert( ! function_exists( 'massifs_danger_meteo' ), 'aucune seconde fonction publique : `massifs_danger_meteo()`' );
t_assert( ! function_exists( 'massifs_attribution_meteo' ), 'aucune seconde fonction publique : `massifs_attribution_meteo()`' );
t_assert( ! function_exists( 'massifs_meteo_disponible' ), 'aucune seconde fonction publique : `massifs_meteo_disponible()`' );
t_assert( ! function_exists( 'massifs_meteo_niveau' ), 'aucune seconde fonction publique : `massifs_meteo_niveau()`' );
t_assert( function_exists( 'massifs_meteo_du_jour' ), 'une fonction publique, une seule : `massifs_meteo_du_jour()`' );

// ---------------------------------------------------------------------------
// 2. §4.2 — jamais la donnée d'un autre jour.
// ---------------------------------------------------------------------------
update_option(
	'massifs_meteo_snapshots',
	array(
		str_replace( '-', '', $hier ) => array(
			'schema'        => 1,
			'date_validite' => $hier,
			'zone_cle'      => '13',
			'niveau_source' => 3,
			'publie_le'     => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'recupere_le'   => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'hash'          => hash( 'sha256', 'veille' ),
			'octets'        => 120,
		),
	),
	false
);

t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'la veille en cache ne remplit jamais la journée courante' );
t_egal( null, massifs_meteo_du_jour( $aujourdhui )['publie_le'], 'et sa publication ne fuit pas non plus' );
t_egal( null, Connector::snapshot_for( $aujourdhui ), 'la façade rend `null` pour une date non couverte' );

// Un enregistrement déplacé — rangé sous une clé de date qui n'est pas la
// sienne — est ÉCARTÉ à la lecture, jamais recalé sur la clé qui le porte.
update_option(
	'massifs_meteo_snapshots',
	array(
		str_replace( '-', '', $aujourdhui ) => array(
			'schema'        => 1,
			'date_validite' => $hier,
			'zone_cle'      => '13',
			'niveau_source' => 3,
			'publie_le'     => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'recupere_le'   => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ),
			'hash'          => hash( 'sha256', 'deplace' ),
			'octets'        => 120,
		),
	),
	false
);

t_egal( false, Connector::has_snapshot_for( $aujourdhui ), 'un enregistrement déplacé n\'est pas servi pour la date qui le range' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et la lecture reste « indisponible »' );

$purge();

// ---------------------------------------------------------------------------
// 3. Totalité : jamais d'exception, jamais `null`, toutes les clés.
// ---------------------------------------------------------------------------
$cles_attendues = array( 'jour', 'etat', 'niveau', 'echelle', 'zone', 'releve_le', 'publie_le', 'distinction', 'attribution' );

$entrees = array(
	'null (aujourd\'hui)'        => null,
	'chaîne vide'                => '',
	'date malformée'             => '32/13/2026',
	'date inexistante'           => '2026-02-31',
	'chaîne quelconque'          => 'demain matin',
	'jour valide (aujourd\'hui)' => $aujourdhui,
	'jour valide (demain)'       => $demain,
	'jour valide (hier)'         => $hier,
	'jour lointain'              => '2099-12-31',
);

foreach ( $entrees as $intitule => $entree ) {
	$m = massifs_meteo_du_jour( $entree );

	t_assert( is_array( $m ), 'retour tabulaire — ' . $intitule, 'array', gettype( $m ) );
	t_egal( $cles_attendues, array_keys( $m ), 'les 9 clés de premier niveau, dans l\'ordre — ' . $intitule );
	t_assert( in_array( $m['etat'], Lecture::ETATS, true ), 'état dans le vocabulaire fermé — ' . $intitule, 'un des trois', $m['etat'] );
	t_assert( is_string( $m['jour'] ), '`jour` est une chaîne — ' . $intitule );
	t_egal( array( 'crans', 'atteint', 'confirmee', 'phrase' ), array_keys( $m['echelle'] ), '`echelle` porte QUATRE clés — ' . $intitule );
	t_egal( array( 'cle', 'libelle', 'granularite' ), array_keys( $m['zone'] ), '`zone` porte trois clés — ' . $intitule );
	t_egal( array( 'texte', 'lien_licence', 'lien_source' ), array_keys( $m['attribution'] ), '`attribution` porte trois clés — ' . $intitule );
	t_assert( '' !== $m['distinction'], '`distinction` est TOUJOURS non vide — ' . $intitule );

	if ( 'disponible' !== $m['etat'] ) {
		t_egal( null, $m['niveau'], '`niveau` vaut null LITTÉRAL hors de « disponible » — ' . $intitule );
		t_egal( 0, $m['echelle']['atteint'], '`echelle.atteint` vaut 0 hors de « disponible » — ' . $intitule );
		t_egal( '', $m['echelle']['phrase'], '`echelle.phrase` est vide hors de « disponible » — ' . $intitule );
	}
}

// Un jour malformé rend `jour = ''` et ne lève PAS.
t_egal( '', massifs_meteo_du_jour( '32/13/2026' )['jour'], 'un jour malformé rend `jour = \'\'`' );
t_egal( 'indisponible', massifs_meteo_du_jour( '32/13/2026' )['etat'], 'et l\'état « indisponible » — jamais une exception' );
t_egal( '', massifs_meteo_du_jour( '2026-02-31' )['jour'], 'une date inexistante est malformée, pas recalée' );
t_egal( $aujourdhui, massifs_meteo_du_jour( null )['jour'], '`null` vaut « aujourd\'hui »' );
t_egal( $aujourdhui, massifs_meteo_du_jour()['jour'], 'et l\'argument est facultatif' );

// ---------------------------------------------------------------------------
// 4. Les trois états sont atteignables, et le troisième n'est pas oublié.
// ---------------------------------------------------------------------------
t_egal( 'non_encore_publie', massifs_meteo_du_jour( $demain )['etat'], 'demain sans instantané : « non encore publié », jamais un niveau bas' );
t_egal( null, massifs_meteo_du_jour( $demain )['niveau'], 'et aucun niveau composé' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'aujourd\'hui sans instantané : « indisponible »' );
t_egal( 'indisponible', massifs_meteo_du_jour( $hier )['etat'], 'un jour passé n\'est jamais « non encore publié »' );

// ---------------------------------------------------------------------------
// 5. Les deux chaînes serveur, mot pour mot.
// ---------------------------------------------------------------------------
$m = massifs_meteo_du_jour( $aujourdhui );

t_egal(
	'Le danger météo décrit les conditions du jour ; il ne détermine pas l\'accès au massif, qui relève de l\'arrêté préfectoral.',
	$m['distinction'],
	'`distinction` — verbatim, mot pour mot'
);
t_egal(
	'Données Météo-France — Licence Etalab 2.0',
	$m['attribution']['texte'],
	'`attribution.texte` — verbatim, tiret cadratin compris'
);
t_egal( '', $m['attribution']['lien_licence'], 'aucun lien de licence inventé' );
t_egal( '', $m['attribution']['lien_source'], 'aucun lien de source inventé' );

// La distinction voyage dans TOUS les états : c'est quand l'indicateur manque
// qu'un lecteur risque de le rabattre sur le statut d'accès.
foreach ( array( $aujourdhui, $demain, $hier, '32/13/2026' ) as $jour ) {
	t_assert( '' !== massifs_meteo_du_jour( $jour )['distinction'], '`distinction` émise même sans donnée — ' . $jour );
}

// ---------------------------------------------------------------------------
// 6. Zone et attribution de la façade.
// ---------------------------------------------------------------------------
t_egal( '13', $m['zone']['cle'], 'zone : clé départementale' );
t_egal( 'Bouches-du-Rhône', $m['zone']['libelle'], 'zone : libellé' );
t_assert( in_array( $m['zone']['granularite'], array( 'departement', 'zone_meteo', 'massif' ), true ), 'zone : granularité dans la liste FERMÉE', 'une des trois', $m['zone']['granularite'] );
t_egal( $m['attribution'], Connector::attribution(), 'la façade interne et la surface publique portent la MÊME attribution' );
t_egal( 'automatique', Connector::mode(), 'mode par défaut' );

$purge();
t_reset();
t_bilan();
