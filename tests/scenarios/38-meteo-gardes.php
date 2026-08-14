<?php
/**
 * Les gardes du module météo, éprouvées une par une.
 *
 * Chacune protège la même chose : une valeur fausse ne doit jamais devenir une
 * valeur plausible. Le gabarit refuse de dessiner plutôt que d'écrêter, et il
 * ne dessine jamais une géométrie que le texte ne dit pas.
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
 * Charge de recette à la forme exacte du contrat #10 §1.1.
 *
 * @param array<string, mixed> $remplacements Clés de premier niveau à remplacer.
 *
 * @return array<string, mixed>
 */
function t_charge_meteo( array $remplacements = array() ): array {
	$charge = array(
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

	return array_replace( $charge, $remplacements );
}

// --- GARDE D'EXTENSION : sans porte de lecture ni charge injectée, zéro octet.
if ( function_exists( 'massifs_meteo_du_jour' ) ) {
	t_note( 'extension présente : aucune fonction ne se dé-déclare depuis PHP, la garde d’extension n’est donc pas exerçable ici. Elle l’est en HTTP, massifs-core désactivée.' );
} else {
	t_egal( '', t_rendre_partie( 'meteo' ), 'extension absente : zéro octet — ni section, ni titre orphelin, ni ancre morte' );
}

// Le pendant observable en toutes circonstances : une charge vide vaut charge
// absente, et la partie retombe sur la lecture du domaine sans jamais échouer.
$html_vide = t_rendre_partie( 'meteo', array( 'meteo' => array() ) );

if ( function_exists( 'massifs_meteo_du_jour' ) ) {
	t_assert( str_contains( $html_vide, 'class="meteo"' ), 'une charge vide vaut charge absente : la partie lit le domaine', 'la section météo', $html_vide );
}

// --- GARDE DE SENS : jamais de carrés sans libellé.
$html_sans_libelle = t_rendre_partie(
	'meteo',
	array(
		'meteo' => t_charge_meteo(
			array(
				'niveau' => array(
					'cle'     => 'cran-de-recette',
					'libelle' => '',
				),
			)
		),
	)
);

t_assert( ! str_contains( $html_sans_libelle, '<svg' ), 'libellé vide : aucune échelle dessinée, la géométrie ne porte jamais seule l’information', 'aucun <svg', $html_sans_libelle );
t_egal( 0, substr_count( $html_sans_libelle, '<rect' ), 'libellé vide : aucun carré dessiné' );
t_assert( str_contains( $html_sans_libelle, 'Danger météo du jour non disponible.' ), 'libellé vide : la partie bascule sur le rendu indisponible' );
t_assert( ! str_contains( $html_sans_libelle, 'Données Météo-France' ), 'libellé vide : aucune attribution, aucune donnée n’est affichée' );
t_assert( ! str_contains( $html_sans_libelle, 'meteo__crans' ), 'libellé vide : la phrase d’échelle ne survit pas seule à son libellé' );
t_assert( str_contains( $html_sans_libelle, 'meteo__distinction' ), 'libellé vide : la distinction demeure, elle est vraie sans donnée' );

// --- GARDE DE CARDINALITÉ : hors bornes, aucune échelle, aucun écrêtage.
$cas_hors_bornes = array(
	'cardinalité nulle'      => array(
		'crans'     => 0,
		'atteint'   => 0,
		'confirmee' => true,
		'phrase'    => 'Phrase d’échelle de recette',
	),
	'cardinalité négative'   => array(
		'crans'     => -3,
		'atteint'   => 0,
		'confirmee' => true,
		'phrase'    => 'Phrase d’échelle de recette',
	),
	'cardinalité démesurée'  => array(
		'crans'     => 13,
		'atteint'   => 4,
		'confirmee' => true,
		'phrase'    => 'Phrase d’échelle de recette',
	),
	'rang au-delà du maximum' => array(
		'crans'     => 4,
		'atteint'   => 9,
		'confirmee' => true,
		'phrase'    => 'Phrase d’échelle de recette',
	),
	'rang négatif'           => array(
		'crans'     => 4,
		'atteint'   => -1,
		'confirmee' => true,
		'phrase'    => 'Phrase d’échelle de recette',
	),
);

foreach ( $cas_hors_bornes as $intitule => $echelle_fausse ) {
	$html_borne = t_rendre_partie( 'meteo', array( 'meteo' => t_charge_meteo( array( 'echelle' => $echelle_fausse ) ) ) );

	t_egal( 0, substr_count( $html_borne, '<rect' ), $intitule . ' : aucune échelle dessinée' );
	t_assert( ! str_contains( $html_borne, '<svg' ), $intitule . ' : aucun SVG, plutôt qu’un écrêtage silencieux', 'aucun <svg', $html_borne );
	t_assert(
		str_contains( $html_borne, esc_html( 'Libellé de recette, jamais officiel' ) ),
		$intitule . ' : le libellé demeure, il porte seul le sens',
		'le libellé du cran',
		$html_borne
	);
	t_assert(
		str_contains( $html_borne, esc_html( 'Phrase d’échelle de recette' ) ),
		$intitule . ' : la phrase d’échelle demeure',
		'la phrase d’échelle',
		$html_borne
	);
	t_assert( ! str_contains( $html_borne, 'Danger météo du jour non disponible.' ), $intitule . ' : l’état reste disponible, seule l’échelle disparaît' );
}

// --- GARDE DE VOCABULAIRE : un état hors des trois bras replie sur indisponible.
$html_inconnu = t_rendre_partie( 'meteo', array( 'meteo' => t_charge_meteo( array( 'etat' => 'danger_maximal_absolu' ) ) ) );

t_assert( str_contains( $html_inconnu, 'class="meteo"' ), 'état inconnu : la page n’est pas blanchie pour un module de bas de page', 'la section météo', $html_inconnu );
t_assert(
	str_contains( $html_inconnu, '<p class="meteo__indisponible">Danger météo du jour non disponible.</p>' ),
	'état inconnu : repli sur « indisponible » par le catch UnhandledMatchError',
	'Danger météo du jour non disponible.',
	$html_inconnu
);
t_assert( ! str_contains( $html_inconnu, '<svg' ), 'état inconnu : aucune échelle' );
t_assert( ! str_contains( $html_inconnu, esc_html( 'Libellé de recette, jamais officiel' ) ), 'état inconnu : aucun libellé de cran n’est affiché' );
t_assert( ! str_contains( $html_inconnu, 'Données Météo-France' ), 'état inconnu : aucune attribution' );
t_assert( str_contains( $html_inconnu, 'meteo__distinction' ), 'état inconnu : la distinction demeure' );

// --- Un jour malformé ne lève jamais et ne produit jamais zéro octet.
if ( function_exists( 'massifs_meteo_du_jour' ) ) {
	$malforme = massifs_meteo_du_jour( 'pas-une-date' );

	t_egal( 'indisponible', $malforme['etat'], 'jour malformé : le serveur rend « indisponible », il ne lève pas' );
	t_egal( '', $malforme['jour'], 'jour malformé : le jour rendu est vide' );

	$html_malforme = t_rendre_partie( 'meteo', array( 'jour' => 'pas-une-date' ) );

	t_assert( str_contains( $html_malforme, 'class="meteo"' ), 'jour malformé : le module rend sa section, jamais zéro octet', 'la section météo', $html_malforme );
	t_assert( str_contains( $html_malforme, 'Danger météo du jour non disponible.' ), 'jour malformé : la phrase d’indisponibilité est rendue' );
}

t_reset();
t_bilan();
