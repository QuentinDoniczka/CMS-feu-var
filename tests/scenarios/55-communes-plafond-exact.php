<?php
/**
 * LE BORD DU PLAFOND DE 5 KM — contrat #45, §4.4.
 *
 * Le §4.4 dit : « **Au-delà** du plafond de 5 km, silence ». *Au-delà* exclut
 * la borne : une commune située à exactement 5 000 m doit donc être NOMMÉE,
 * avec `distance_m = 5000`. Le code compare `$mesure < $distance` avec
 * `$distance` initialisé au plafond (`communes.php`, `commune_de_la_zone()`) :
 * la borne exacte tombe du mauvais côté.
 *
 * CE CONTRÔLE EXISTE PARCE QUE LA BORNE N'EST PAS ATTEIGNABLE SUR LE
 * RÉFÉRENTIEL RÉEL. Vérifié par bissection sur l'artefact commité : entre deux
 * flottants voisins, la distance passe de 5 000,00000000059 à
 * 4 999,999999999708 sans jamais valoir 5 000,0. Sur des géométries mesurées, la
 * distance exacte est un événement de mesure nulle. Un artefact SYNTHÉTIQUE,
 * dont les coordonnées sont choisies pour que l'arithmétique flottante retombe
 * exactement sur 5 000,0, est le seul moyen d'éprouver la borne — et une borne
 * qu'on n'éprouve pas est une borne qu'on suppose.
 *
 * L'artefact réel est sauvegardé, remplacé, puis REMIS, y compris en cas
 * d'erreur fatale.
 *
 * `lookup_communes()` mémoïse : un processus, un monde. D'où un fichier à part.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

/**
 * Rectangle GeoJSON.
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

t_reset();

$chemin_lookup = WP_PLUGIN_DIR . '/massifs-core/includes/domain/massifs/communes-13.lookup.json';

t_assert( is_file( $chemin_lookup ), 'PRÉALABLE : l\'artefact réel est là avant qu\'on y touche', 'présent', 'absent' );

$sauvegarde = (string) file_get_contents( $chemin_lookup );
$empreinte  = hash( 'sha256', $sauvegarde );

$restaurer = static function () use ( $chemin_lookup, $sauvegarde, $empreinte ): bool {
	if ( is_file( $chemin_lookup ) && hash_file( 'sha256', $chemin_lookup ) === $empreinte ) {
		return true;
	}

	file_put_contents( $chemin_lookup, $sauvegarde );

	return is_file( $chemin_lookup ) && hash_file( 'sha256', $chemin_lookup ) === $empreinte;
};

register_shutdown_function( $restaurer );

/*
 * ARTEFACT SYNTHÉTIQUE — une commune carrée, une projection à facteurs ronds.
 *
 * Les facteurs 80 000 m/° de longitude et 100 000 m/° de latitude ne sont pas
 * ceux de la Provence : ils sont choisis RONDS pour que
 * `43,0 × 100 000 = 4 300 000` et `42,95 × 100 000 = 4 295 000` soient exacts en
 * double précision, et que leur différence vaille donc exactement 5 000,0. Le
 * module lit ces facteurs DANS l'artefact — c'est la clause du §3 : « les
 * facteurs de projection voyagent AVEC l'artefact » — ce qui rend ce montage
 * possible sans toucher au code.
 */
$synthetique = array(
	'a_propos'   => 'Artefact de recette du scénario 55. Jamais commité, jamais servi : écrit puis retiré dans la même exécution.',
	'type'       => 'massifs-communes-lookup',
	'version'    => 1,
	'plafond_m'  => 5000,
	'projection' => array(
		'metres_par_degre_lon' => 80000,
		'metres_par_degre_lat' => 100000,
	),
	'couverture' => array(
		'ouest' => 4.0,
		'sud'   => 42.0,
		'est'   => 6.0,
		'nord'  => 44.0,
	),
	'nombre'     => 1,
	'communes'   => array(
		array(
			'insee'   => '99001',
			'nom'     => 'Communette-du-Plafond',
			'dep'     => '99',
			'bbox'    => array( 5.0, 43.0, 5.1, 43.1 ),
			'parties' => array(
				array(
					array( 5.0, 43.0, 5.1, 43.0, 5.1, 43.1, 5.0, 43.1, 5.0, 43.0 ),
				),
			),
		),
	),
);

t_assert(
	false !== file_put_contents( $chemin_lookup, (string) wp_json_encode( $synthetique ) ),
	'l\'artefact synthétique est en place',
	'écrit',
	'échec d\'écriture'
);

// ---------------------------------------------------------------------------
// LE COUPLE FALSIFIABLE : deux zones, deux flottants voisins, un seul plafond.
// ---------------------------------------------------------------------------

/*
 * Bord sud de la commune : latitude 43,0. Les deux zones sont posées plein sud,
 * à la même longitude, et ne diffèrent que par leur bord nord :
 *
 *   - 42,95                 → distance exactement 5 000,0 m ;
 *   - 42,95000000000002     → distance 4 999,999999998 m, soit 5 000 arrondi.
 *
 * Les deux mesurent donc 5 000 m une fois arrondies. Selon le §4.4, les deux
 * doivent être nommées : « au-delà » exclut la borne.
 */
$sur_la_borne  = t_rect( 5.04, 42.94, 5.06, 42.95 );
$juste_dessous = t_rect( 5.04, 42.94, 5.06, 42.95000000000002 );

$dessous = massifs_commune_de_la_zone( $juste_dessous );

t_egal( true, $dessous['trouvee'], 'RÉFÉRENCE : sous le plafond d\'un cheveu, la commune est nommée' );
t_egal( 'Communette-du-Plafond', $dessous['nom'], 'RÉFÉRENCE : le montage synthétique fonctionne — c\'est ce qui rend le contrôle suivant falsifiable' );
t_egal( 5000, $dessous['distance_m'], 'RÉFÉRENCE : la distance servie vaut 5 000 m après arrondi' );
t_egal( 'communes_ok', $dessous['etat'], 'RÉFÉRENCE : état nominal' );

$borne = massifs_commune_de_la_zone( $sur_la_borne );

t_note( 'sur la borne — trouvee=' . var_export( $borne['trouvee'], true ) . ', etat=' . $borne['etat'] . ', nom=«' . $borne['nom'] . '», distance_m=' . var_export( $borne['distance_m'], true ) );

t_assert(
	true === $borne['trouvee'],
	'§4.4 : à EXACTEMENT 5 000,0 m, la commune doit être nommée — « au-delà du plafond » exclut la borne',
	'trouvee = true, nom = Communette-du-Plafond, distance_m = 5000',
	'trouvee = ' . var_export( $borne['trouvee'], true ) . ', etat = ' . $borne['etat'] . ', nom = «' . $borne['nom'] . '»'
);
t_egal( 'Communette-du-Plafond', $borne['nom'], '§4.4 : et c\'est bien cette commune-là' );
t_egal( 5000, $borne['distance_m'], '§4.4 : à la borne, la distance servie est le plafond lui-même' );

// Au-delà, en revanche, le silence est celui que le contrat décrit.
$au_dela = t_rect( 5.04, 42.93, 5.06, 42.94 );
$loin    = massifs_commune_de_la_zone( $au_dela );

t_egal( false, $loin['trouvee'], '§4.4 : à 6 km, au-delà du plafond, le serveur se tait' );
t_egal( 'communes_hors_couverture', $loin['etat'], '§4.4 : et il nomme la cause' );
t_egal( null, $loin['distance_m'], '§4.4 : aucune distance inventée au-delà du plafond' );

// ---------------------------------------------------------------------------
// REMISE EN PLACE.
// ---------------------------------------------------------------------------

t_assert( $restaurer(), 'l\'artefact réel est remis, octet pour octet', $empreinte, is_file( $chemin_lookup ) ? hash_file( 'sha256', $chemin_lookup ) : 'absent' );
t_egal( $empreinte, hash_file( 'sha256', $chemin_lookup ), 'MÉNAGE : le dépôt retrouve exactement l\'artefact commité' );

t_reset();

t_bilan();
