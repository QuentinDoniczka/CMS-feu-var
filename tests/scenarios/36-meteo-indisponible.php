<?php
/**
 * Le cas nominal du module météo : « indisponible », et rien d'autre.
 *
 * Tant que les libellés officiels des crans ne sont pas sourcés (contrat #10,
 * Q2), la garde de vocabulaire tient fermée et la seule réponse honnête du
 * serveur est une absence. Ce scénario éprouve donc le module tel qu'il est
 * réellement servi aujourd'hui : le gabarit réel, alimenté par la fonction de
 * lecture réelle, dont on observe le HTML produit.
 *
 * Il asserte autant ce qui est rendu que ce qui ne l'est PAS : aucune échelle
 * « en attendant », aucune marque de statut, aucune attribution d'une source
 * dont aucune donnée n'est affichée.
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

// --- La porte unique du contrat existe, et elle est TOTALE.
$porte_ouverte = t_assert(
	function_exists( 'massifs_meteo_du_jour' ),
	'l’extension expose massifs_meteo_du_jour(), porte unique du contrat #10'
);

if ( ! $porte_ouverte ) {
	t_bilan();
}

$meteo = massifs_meteo_du_jour();

t_egal( 'indisponible', $meteo['etat'], 'aujourd’hui : le serveur rend « indisponible », le vocabulaire n’étant pas confirmé' );
t_egal( null, $meteo['niveau'], 'hors de « disponible », le niveau vaut null LITTÉRAL, jamais un tableau vide' );
t_egal( 0, $meteo['echelle']['crans'], 'aucune cardinalité d’échelle n’est affirmée' );
t_egal( 0, $meteo['echelle']['atteint'], 'aucun cran atteint n’est affirmé' );
t_egal( false, $meteo['echelle']['confirmee'], 'l’échelle n’est pas confirmée' );
t_egal( '', $meteo['echelle']['phrase'], 'aucune phrase d’échelle hors de « disponible »' );
t_assert( '' !== $meteo['distinction'], 'la phrase de distinction voyage dans TOUS les états', 'une phrase non vide', $meteo['distinction'] );

// --- Le module rendu, sans aucun argument : c'est ainsi que massifs_partie() l'appelle.
$html = t_rendre_partie( 'meteo' );

t_assert( '' !== $html, 'le cas nominal ne rend PAS zéro octet : le module est livrable dès aujourd’hui', 'du HTML', $html );
t_assert( str_contains( $html, '<section id="meteo" class="meteo" aria-labelledby="meteo-titre">' ), 'la section porte son ancre et son nom accessible', '<section id="meteo" …>', substr( $html, 0, 300 ) );
t_assert( str_contains( $html, '<h2 id="meteo-titre" class="meteo__titre">Danger météo du jour</h2>' ), 'le titre est un h2 en casse normale, verbatim', '<h2 …>Danger météo du jour</h2>', $html );

t_assert(
	str_contains( $html, '<p class="meteo__indisponible">Danger météo du jour non disponible.</p>' ),
	'la phrase d’état gelée est rendue mot pour mot',
	'Danger météo du jour non disponible.',
	$html
);

t_assert(
	str_contains( $html, esc_html( $meteo['distinction'] ) ),
	'la distinction du serveur est rendue verbatim, y compris en état indisponible',
	$meteo['distinction'],
	$html
);

t_egal(
	'Le danger météo décrit les conditions du jour ; il ne détermine pas l\'accès au massif, qui relève de l\'arrêté préfectoral.',
	$meteo['distinction'],
	'la distinction est celle de MASTER §8.6, mot pour mot'
);

// --- Ce qui ne doit surtout PAS être là.
t_assert( ! str_contains( $html, '<svg' ), 'aucune échelle n’est dessinée « en attendant »', 'aucun <svg', $html );
t_assert( ! str_contains( $html, '<rect' ), 'aucun carré n’est dessiné', 'aucun <rect', $html );
t_assert( ! str_contains( $html, 'pastille' ), 'aucune pastille : le danger météo n’est pas une information de statut' );
t_assert( ! str_contains( $html, 'statut' ), 'aucune classe de la famille statut' );
t_assert( ! str_contains( $html, 'jalon' ), 'aucun jalon ZAPEF' );
t_assert( ! str_contains( $html, 'repere' ), 'aucun repère sur le h2 : la signature reste réservée à l’information de statut' );
t_assert( ! str_contains( $html, 'bandeau-alerte' ), 'aucun bandeau d’alerte : une absence de danger météo n’alerte de rien' );

t_assert(
	'' !== $meteo['attribution']['texte'] && ! str_contains( $html, $meteo['attribution']['texte'] ),
	'aucune attribution : créditer une source dont aucune donnée n’est affichée est une affirmation fausse',
	'la chaîne d’attribution absente du HTML',
	$html
);
t_assert( ! str_contains( $html, 'Etalab' ), 'aucune mention de licence sans donnée affichée' );

// --- Ni tiret, ni zéro, ni « n. d. » à la place d'un niveau.
t_assert( ! str_contains( $html, 'meteo__libelle' ), 'aucun libellé de cran n’est rendu hors de « disponible »' );
t_assert( ! str_contains( $html, 'meteo__crans' ), 'aucune phrase d’échelle n’est rendue hors de « disponible »' );

// --- Rien ne se met à jour : l'affirmer serait faux.
t_assert( ! str_contains( $html, 'aria-live' ), 'aucune région live : le module est statique' );
t_assert( ! str_contains( $html, 'role="status"' ), 'aucun role status' );
t_assert( ! str_contains( $html, 'tabindex' ), 'aucun tabindex : le module n’est pas une cible d’évitement' );

t_reset();
t_bilan();
