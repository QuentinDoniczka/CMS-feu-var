<?php
/**
 * ARTEFACT DE LOOKUP MALFORMÉ — le refus EN BLOC, et son trou.
 *
 * `communes.php::normaliser_communes()` porte l'invariant en toutes lettres :
 * « Une seule entrée invalide fait rejeter l'artefact ENTIER, comme une ligne
 * invalide fait rejeter le référentiel entier : une commune silencieusement
 * absente de la recherche renverrait sa voisine, et un nom faux se lit comme un
 * fait. » Le §7 du contrat #45 lui donne son état : `communes_artefact_invalide`.
 *
 * Ce scénario éprouve cet invariant sur la forme de malformation que la chaîne
 * de développement a elle-même signalée sans la corriger : une `bbox` qui a bien
 * quatre éléments, mais dont les clés ne sont pas `0..3`. Le code contrôle
 * `is_array()` et `count() === 4`, puis lit `$bbox[0]` à `$bbox[3]`.
 *
 * L'artefact réel est sauvegardé, remplacé, puis REMIS, y compris en cas
 * d'erreur fatale.
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
 * Deux communes voisines. La première est irréprochable ; la seconde porte une
 * `bbox` de quatre éléments dont les clés sont NOMMÉES au lieu d'être
 * numériques — exactement ce qu'un producteur qui change de sérialisation
 * produirait, et exactement ce que la chaîne de développement a signalé.
 */
$malforme = array(
	'a_propos'   => 'Artefact de recette du scénario 56. Jamais commité, jamais servi.',
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
	'nombre'     => 2,
	'communes'   => array(
		array(
			'insee'   => '99001',
			'nom'     => 'Communette-Valide',
			'dep'     => '99',
			'bbox'    => array( 5.0, 43.0, 5.1, 43.1 ),
			'parties' => array( array( array( 5.0, 43.0, 5.1, 43.0, 5.1, 43.1, 5.0, 43.1, 5.0, 43.0 ) ) ),
		),
		array(
			'insee'   => '99002',
			'nom'     => 'Communette-Bancale',
			'dep'     => '99',
			// Quatre éléments, `count() === 4`, `is_array()` vrai — mais aucune clé
			// `0..3`. Le lecteur d'indices n'y trouvera rien.
			'bbox'    => array(
				'ouest' => 5.2,
				'sud'   => 43.0,
				'est'   => 5.3,
				'nord'  => 43.1,
			),
			'parties' => array( array( array( 5.2, 43.0, 5.3, 43.0, 5.3, 43.1, 5.2, 43.1, 5.2, 43.0 ) ) ),
		),
	),
);

t_assert(
	false !== file_put_contents( $chemin_lookup, (string) wp_json_encode( $malforme ) ),
	'l\'artefact malformé est en place',
	'écrit',
	'échec d\'écriture'
);

// ---------------------------------------------------------------------------
// CE QUE L'INVARIANT PROMET.
// ---------------------------------------------------------------------------

$dans_valide = massifs_commune_de_la_zone( t_rect( 5.04, 43.04, 5.06, 43.06 ) );

t_note( 'zone au cœur de la commune SAINE — etat=' . $dans_valide['etat'] . ', nom=«' . $dans_valide['nom'] . '»' );

t_egal(
	'communes_artefact_invalide',
	$dans_valide['etat'],
	'l\'invariant écrit dans `normaliser_communes()` : UNE seule entrée invalide fait rejeter l\'artefact ENTIER'
);
t_egal( false, $dans_valide['trouvee'], 'un artefact refusé ne résout aucune commune, pas même celles qui sont saines' );
t_egal( '', $dans_valide['nom'], 'et il n\'en nomme aucune' );

// ---------------------------------------------------------------------------
// CE QUE LA MALFORMATION FAIT DE LA COMMUNE CONCERNÉE.
// ---------------------------------------------------------------------------

$dans_bancale = massifs_commune_de_la_zone( t_rect( 5.24, 43.04, 5.26, 43.06 ) );

t_note( 'zone au cœur de la commune BANCALE — etat=' . $dans_bancale['etat'] . ', nom=«' . $dans_bancale['nom'] . '»' );

t_assert(
	'' === $dans_bancale['nom'],
	'une commune dont l\'emprise n\'a pas pu être lue ne doit JAMAIS voir sa voisine servie à sa place',
	'aucun nom',
	$dans_bancale['nom']
);

// ---------------------------------------------------------------------------
// LES MALFORMATIONS DÉJÀ COUVERTES — pour situer le trou.
// ---------------------------------------------------------------------------

foreach ( array(
	'type inconnu'          => array( 'type' => 'autre-chose' ),
	'version inconnue'      => array( 'version' => 2 ),
	'plafond en désaccord'  => array( 'plafond_m' => 3000 ),
	'projection absente'    => array( 'projection' => array() ),
	'liste vide'            => array( 'communes' => array() ),
) as $etiquette => $mutation ) {
	$variante = array_merge( $malforme, $mutation );
	$variante['communes'][1]['bbox'] = array( 5.2, 43.0, 5.3, 43.1 );

	file_put_contents( $chemin_lookup . '.variante', (string) wp_json_encode( $variante ) );

	$verdict = \Massifs\Domain\Massifs\charger_lookup_communes( $chemin_lookup . '.variante' );

	t_egal( false, $verdict['disponible'], 'artefact refusé : ' . $etiquette );
	t_egal( 'communes_artefact_invalide', $verdict['raison'], 'et la raison le nomme : ' . $etiquette );

	unlink( $chemin_lookup . '.variante' );
}

$absent = \Massifs\Domain\Massifs\charger_lookup_communes( $chemin_lookup . '.inexistant' );
t_egal( 'communes_artefact_absent', $absent['raison'], 'fichier absent et fichier malformé sont deux causes DISTINCTES' );

// ---------------------------------------------------------------------------
// REMISE EN PLACE.
// ---------------------------------------------------------------------------

t_assert( $restaurer(), 'l\'artefact réel est remis, octet pour octet', $empreinte, is_file( $chemin_lookup ) ? hash_file( 'sha256', $chemin_lookup ) : 'absent' );
t_egal( $empreinte, hash_file( 'sha256', $chemin_lookup ), 'MÉNAGE : le dépôt retrouve exactement l\'artefact commité' );
t_assert( ! file_exists( $chemin_lookup . '.variante' ), 'MÉNAGE : aucune variante de recette ne survit', 'aucune', 'résidu' );

t_reset();

t_bilan();
