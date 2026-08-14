<?php
/**
 * Le module météo n'appelle personne, et ne se confond avec aucun statut.
 *
 * Deux exigences distinctes, éprouvées ensemble parce qu'elles portent sur le
 * même octet de HTML : la contrainte n° 2 du projet (zéro requête vers un
 * domaine tiers — ici, littéralement aucune requête du tout), et l'interdiction
 * de fusion visuelle ou lexicale avec l'information de statut (MASTER §8.6).
 *
 * Le module est du PHP pur dans le HTML initial : il n'émet ni script, ni image,
 * ni feuille, ni le moindre lien.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

t_reset();

/**
 * Rend une partie de gabarit du thème et retourne son HTML.
 *
 * @param string               $slug Nom de la partie.
 * @param array<string, mixed> $args Arguments.
 */
function t_rendre_partie( string $slug, array $args = array() ): string {
	ob_start();
	get_template_part( 'templates/parts/' . $slug, null, $args );

	return (string) ob_get_clean();
}

/**
 * Charge de recette à la forme exacte du contrat #10 §1.1, état peuplé.
 *
 * @return array<string, mixed>
 */
function t_charge_meteo(): array {
	return array(
		'jour'        => '2026-08-14',
		'etat'        => 'disponible',
		'niveau'      => array(
			'cle'     => 'cran-de-recette',
			'libelle' => 'Libellé de recette, jamais officiel',
		),
		'echelle'     => array(
			'crans'     => 4,
			'atteint'   => 2,
			'confirmee' => true,
			'phrase'    => 'Phrase d’échelle de recette',
		),
		'zone'        => array(
			'cle'         => '13',
			'libelle'     => 'Bouches-du-Rhône',
			'granularite' => 'departement',
		),
		'releve_le'   => '2026-08-14T06:00:00+00:00',
		'publie_le'   => '2026-08-14T05:30:00+00:00',
		'distinction' => 'Le danger météo décrit les conditions du jour ; il ne détermine pas l\'accès au massif, qui relève de l\'arrêté préfectoral.',
		'attribution' => array(
			'texte'        => 'Données Météo-France — Licence Etalab 2.0',
			'lien_licence' => '',
			'lien_source'  => '',
		),
	);
}

// --- Aucune origine tierce, dans l'état servi ET dans l'état le plus riche.
$interdits = array( 'http://', 'https://', 'src=', 'href=', '<script', '<img', '<iframe', '<link', '@import', 'meteofrance', 'data.gouv' );

$fragments = array(
	'état servi aujourd’hui' => t_rendre_partie( 'meteo' ),
	'état peuplé'            => t_rendre_partie( 'meteo', array( 'meteo' => t_charge_meteo() ) ),
);

foreach ( $fragments as $intitule => $fragment ) {
	t_assert( '' !== $fragment, $intitule . ' : le module rend bien du HTML', 'du HTML', $fragment );

	foreach ( $interdits as $interdit ) {
		t_assert(
			! str_contains( $fragment, $interdit ),
			$intitule . ' : aucune occurrence de « ' . $interdit . ' »',
			'aucune occurrence',
			$fragment
		);
	}
}

// --- Jamais fusionné avec l'information de statut.
$html = $fragments['état servi aujourd’hui'];

t_assert( ! str_contains( $html, 'Accès au massif' ), 'le module n’emprunte jamais le vocabulaire des niveaux d’accès' );
t_assert( ! str_contains( $html, 'ZAPEF' ), 'le module ne mentionne aucune ZAPEF' );
t_assert( ! str_contains( $html, '--statut-' ), 'aucun jeton de statut' );
t_assert( ! str_contains( $html, 'repere' ), 'aucun repère : la signature reste réservée à l’information de statut' );

// --- Trois parties à la suite : aucun `id` en double.
$document = $html
	. t_rendre_partie( 'liste-statuts' )
	. t_rendre_partie( 'legende' );

preg_match_all( '/\sid="([^"]*)"/', $document, $releve_ids );
$ids = isset( $releve_ids[1] ) ? $releve_ids[1] : array();

t_assert( array() !== $ids, 'le document de recette porte bien des ancres', 'au moins un id', $document );
t_egal( count( $ids ), count( array_unique( $ids ) ), 'météo, liste et légende à la suite : aucun id en double' );
t_assert( in_array( 'meteo', $ids, true ), 'l’ancre du module météo est présente dans le document', 'meteo', implode( ', ', $ids ) );

// --- L'ancre est paramétrable : une seconde inclusion peut éviter les doublons.
$html_ancre = t_rendre_partie( 'meteo', array( 'ancre' => 'meteo-demain' ) );

t_assert( str_contains( $html_ancre, 'id="meteo-demain"' ), 'l’ancre est paramétrable', 'id="meteo-demain"', $html_ancre );
t_assert( str_contains( $html_ancre, 'id="meteo-demain-titre"' ), 'l’ancre préfixe TOUS les id de la partie', 'id="meteo-demain-titre"', $html_ancre );
t_assert( str_contains( $html_ancre, 'aria-labelledby="meteo-demain-titre"' ), 'le nom accessible suit l’ancre' );
t_assert( ! str_contains( $html_ancre, 'id="meteo"' ), 'l’ancre par défaut ne subsiste pas quand une autre est demandée' );

// --- Le niveau de titre est paramétrable, et jamais un h1.
$html_h3 = t_rendre_partie( 'meteo', array( 'niveau_titre' => 3 ) );

t_assert( str_contains( $html_h3, '<h3 id="meteo-titre"' ), 'le niveau de titre demandé est respecté', '<h3 …>', $html_h3 );

$html_h1 = t_rendre_partie( 'meteo', array( 'niveau_titre' => 1 ) );

t_assert( str_contains( $html_h1, '<h2 id="meteo-titre"' ), 'un niveau 1 est refusé : le h1 appartient à l’appelant', '<h2 …>', $html_h1 );

t_reset();
t_bilan();
