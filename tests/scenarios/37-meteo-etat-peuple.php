<?php
/**
 * Le module météo alimenté par une charge peuplée, à deux cardinalités.
 *
 * La garde de vocabulaire tient fermée aujourd'hui : l'état « disponible » est
 * inatteignable par la donnée. Il reste éprouvable par la clé d'injection de
 * recette `$args['meteo']`, qui est l'interface publique du gabarit.
 *
 * Deux cardinalités DIFFÉRENTES sont injectées, précisément pour prouver que
 * rien dans le gabarit ne fige un nombre de crans : c'est la charge du serveur
 * qui décide, et elle seule. Le nombre de carrés dessinés égale `crans`, le
 * nombre de carrés pleins égale `atteint`.
 *
 * La forme de la charge injectée est confrontée à celle que rend réellement
 * `massifs_meteo_du_jour()` : un gabarit éprouvé contre une forme fantaisiste
 * ne prouve rien.
 *
 * LA DERNIÈRE SECTION FERME LA BOUCLE, et c'est la seule qui n'appartenait à
 * aucun des deux côtés : le vocabulaire est ouvert par son filtre public, un
 * instantané valide est mis en cache, et le gabarit est rendu SANS AUCUNE
 * INJECTION — il lit donc le serveur réel. C'est la seule preuve que le chemin
 * `disponible` est atteignable de bout en bout le jour où la table de crans
 * sera remplie, et que la cardinalité comme le libellé traversent réellement la
 * frontière jusqu'au HTML. Les libellés y sont FICTIFS et marqués comme tels ;
 * ils ne vivent que dans ce scénario et jamais dans `vocabulaire.config.php`,
 * qui reste vide tant qu'aucune source écrite ne donne les libellés officiels
 * (contrat #10, Q2).
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

// Les trois options météo ne sont pas purgées par `t_reset()` (couture J-4) :
// le scénario s'en charge lui-même, au début ET à la fin.
$purge_meteo = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge_meteo();

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
 * Le libellé de cran est un libellé de RECETTE, jamais un libellé officiel :
 * ceux-ci ne sont sourcés nulle part (contrat #10, Q2) et les inventer, même
 * dans un scénario, est ce que ce lot existe pour empêcher.
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

// --- La charge de recette a la forme que le serveur rend réellement.
$porte_ouverte = t_assert(
	function_exists( 'massifs_meteo_du_jour' ),
	'l’extension expose massifs_meteo_du_jour(), porte unique du contrat #10'
);

if ( ! $porte_ouverte ) {
	t_bilan();
}

$reelle = massifs_meteo_du_jour();
$charge = t_charge_meteo();

$cles_reelles = array_keys( $reelle );
$cles_charge  = array_keys( $charge );
sort( $cles_reelles );
sort( $cles_charge );
t_egal( $cles_reelles, $cles_charge, 'la charge injectée porte exactement les clés du retour réel' );

$cles_echelle_reelles = array_keys( $reelle['echelle'] );
$cles_echelle_charge  = array_keys( $charge['echelle'] );
sort( $cles_echelle_reelles );
sort( $cles_echelle_charge );
t_egal( $cles_echelle_reelles, $cles_echelle_charge, 'le bloc echelle porte exactement les quatre clés du contrat' );

$cles_zone_reelles = array_keys( $reelle['zone'] );
$cles_zone_charge  = array_keys( $charge['zone'] );
sort( $cles_zone_reelles );
sort( $cles_zone_charge );
t_egal( $cles_zone_reelles, $cles_zone_charge, 'le bloc zone porte exactement les trois clés du contrat' );

$cles_attribution_reelles = array_keys( $reelle['attribution'] );
$cles_attribution_charge  = array_keys( $charge['attribution'] );
sort( $cles_attribution_reelles );
sort( $cles_attribution_charge );
t_egal( $cles_attribution_reelles, $cles_attribution_charge, 'le bloc attribution porte exactement les trois clés du contrat' );

// Le bloc `niveau` vaut null dans le retour réel : ses clés se lisent au contrat.
t_egal( array( 'cle', 'libelle' ), array_keys( $charge['niveau'] ), 'le bloc niveau porte exactement ses deux clés' );

// --- Première cardinalité : 4 crans, 2 atteints.
$html = t_rendre_partie( 'meteo', array( 'meteo' => $charge ) );

t_egal( 4, substr_count( $html, '<rect' ), 'le nombre de carrés dessinés égale « crans »' );
t_egal( 2, substr_count( $html, 'meteo__carre--plein' ), 'le nombre de carrés pleins égale « atteint »' );
t_egal( 2, substr_count( $html, 'meteo__carre--vide' ), 'les carrés restants sont dessinés vides' );
t_assert( str_contains( $html, 'width="60"' ), 'la largeur du SVG est portée par le SVG lui-même : 4 × 16 − 4', 'width="60"', $html );
t_assert( str_contains( $html, 'viewBox="0 0 60 12"' ), 'la géométrie est en attributs, jamais en style', 'viewBox="0 0 60 12"', $html );
t_assert( str_contains( $html, 'aria-hidden="true"' ), 'le SVG est masqué à l’arbre d’accessibilité' );
t_assert( str_contains( $html, 'focusable="false"' ), 'le SVG n’est jamais focusable' );
t_assert( str_contains( $html, 'fill="currentColor"' ), 'les carrés pleins héritent de la couleur du texte' );
t_assert( str_contains( $html, 'stroke="currentColor"' ), 'les carrés vides héritent de la couleur du texte' );
t_assert( ! str_contains( $html, 'style=' ), 'aucun attribut style' );

// Aucune valeur de couleur : la seule chose que le module peut dire de sa
// teinte, c'est qu'il hérite de celle du texte.
$peintures = array();
preg_match_all( '/(?:fill|stroke)="([^"]*)"/', $html, $releve_peintures );

if ( isset( $releve_peintures[1] ) ) {
	$peintures = array_values( array_unique( $releve_peintures[1] ) );
	sort( $peintures );
}

t_egal( array( 'currentColor', 'none' ), $peintures, 'les seules peintures du SVG sont « currentColor » et « none » : aucune couleur n’est écrite' );

t_assert(
	str_contains( $html, '<span class="meteo__libelle">' . esc_html( $charge['niveau']['libelle'] ) . '</span>' ),
	'le libellé du cran est rendu verbatim, en toutes lettres',
	$charge['niveau']['libelle'],
	$html
);
t_assert(
	str_contains( $html, '<p class="meteo__crans">' . esc_html( $charge['echelle']['phrase'] ) . '</p>' ),
	'la phrase « n crans sur N » du serveur est rendue verbatim, sans aucune composition du thème',
	$charge['echelle']['phrase'],
	$html
);
t_assert(
	str_contains( $html, '<p class="meteo__attribution">' . esc_html( $charge['attribution']['texte'] ) . '</p>' ),
	'l’attribution accompagne la donnée affichée, entière et non découpée',
	$charge['attribution']['texte'],
	$html
);
t_assert( str_contains( $html, esc_html( $charge['distinction'] ) ), 'la distinction est rendue aussi dans l’état disponible' );
t_assert( ! str_contains( $html, 'pastille' ) && ! str_contains( $html, 'jalon' ) && ! str_contains( $html, 'repere' ), 'aucune marque de statut, même avec une donnée affichée' );

// --- Seconde cardinalité, différente : 7 crans, 7 atteints. Rien n'est figé.
$charge_haute = t_charge_meteo(
	array(
		'niveau'  => array(
			'cle'     => 'cran-haut-de-recette',
			'libelle' => 'Autre libellé de recette',
		),
		'echelle' => array(
			'crans'     => 7,
			'atteint'   => 7,
			'confirmee' => true,
			'phrase'    => 'Autre phrase d’échelle de recette',
		),
	)
);

$html_haute = t_rendre_partie( 'meteo', array( 'meteo' => $charge_haute ) );

t_egal( 7, substr_count( $html_haute, '<rect' ), 'seconde cardinalité : le nombre de carrés suit la charge, jamais une constante du thème' );
t_egal( 7, substr_count( $html_haute, 'meteo__carre--plein' ), 'un cran maximal n’est pas une aberration : les sept carrés sont pleins' );
t_egal( 0, substr_count( $html_haute, 'meteo__carre--vide' ), 'aucun carré vide quand tous les crans sont atteints' );
t_assert( str_contains( $html_haute, 'viewBox="0 0 108 12"' ), 'la largeur suit la cardinalité : 7 × 16 − 4', 'viewBox="0 0 108 12"', $html_haute );
t_assert( str_contains( $html_haute, esc_html( 'Autre libellé de recette' ) ), 'le libellé de la seconde charge est rendu' );

// --- Troisième bras du vocabulaire fermé : demain, pas encore publié.
$charge_demain = t_charge_meteo(
	array(
		'jour'      => '2026-08-15',
		'etat'      => 'non_encore_publie',
		'niveau'    => null,
		'echelle'   => array(
			'crans'     => 0,
			'atteint'   => 0,
			'confirmee' => false,
			'phrase'    => '',
		),
		'releve_le' => null,
		'publie_le' => null,
	)
);

$html_demain = t_rendre_partie( 'meteo', array( 'meteo' => $charge_demain ) );

t_assert(
	str_contains( $html_demain, '<p class="meteo__indisponible">Le danger météo de demain n\'est pas encore publié.</p>' ),
	'non encore publié : la phrase d’état gelée est rendue mot pour mot',
	'Le danger météo de demain n\'est pas encore publié.',
	$html_demain
);
t_assert( ! str_contains( $html_demain, '<svg' ), 'non encore publié : aucune échelle, jamais un niveau bas déguisé' );
t_assert( ! str_contains( $html_demain, 'Danger météo du jour non disponible.' ), 'non encore publié : la phrase d’indisponibilité n’est pas rendue en plus' );
t_assert( ! str_contains( $html_demain, $charge_demain['attribution']['texte'] ), 'non encore publié : aucune attribution' );
t_assert( str_contains( $html_demain, esc_html( $charge_demain['distinction'] ) ), 'non encore publié : la distinction demeure' );
t_assert( ! str_contains( $html_demain, '2026-08-15' ), 'le gabarit ne lit jamais la clé « jour » : aucune date n’est affichée' );

// ---------------------------------------------------------------------------
// LA JONCTION : le chemin `disponible` de bout en bout, SANS INJECTION.
//
// Tout ce qui précède éprouve un gabarit contre une charge écrite à la main. Ce
// qui suit éprouve le gabarit contre le SERVEUR RÉEL : on ouvre le vocabulaire
// par son filtre public, on met en cache un instantané valide, et on rend la
// partie sans le moindre `$args`, c'est-à-dire exactement comme
// `massifs_partie( 'meteo' )` l'appellera. C'est la seule preuve que le chemin
// est atteignable le jour où la table de crans sera remplie.
//
// LES LIBELLÉS CI-DESSOUS SONT FICTIFS ET LE DISENT. Ils ne peuvent pas être
// pris pour des libellés officiels de Météo-France, ils ne vivent que dans ce
// fichier, et `vocabulaire.config.php` reste vide (contrat #10, Q2). L'un porte
// des caractères à échapper : c'est ainsi qu'on éprouve l'échappement à la
// jonction plutôt que de le relire.
// ---------------------------------------------------------------------------
$aujourdhui = massifs_jour_courant();
$demain     = t_jour_apres( $aujourdhui );

// La période d'exploitation est une porte opérationnelle : on la gèle par son
// filtre public plutôt que d'attendre que l'horloge du conteneur coopère.
add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$libelle_fictif = 'Cran de recette <fictif & jamais officiel>';

$vocabulaire_de_recette = static function ( array $v ) use ( $libelle_fictif ): array {
	return array(
		'confirme'              => true,
		'revision'              => 'recette-jonction',
		'source'                => 'recette-jonction',
		'crans'                 => array(
			array(
				'cle'     => 'recette-un',
				'libelle' => 'Cran de recette un (fictif)',
				'rang'    => 1,
			),
			array(
				'cle'     => 'recette-deux',
				'libelle' => $libelle_fictif,
				'rang'    => 2,
			),
			array(
				'cle'     => 'recette-trois',
				'libelle' => 'Cran de recette trois (fictif)',
				'rang'    => 3,
			),
			array(
				'cle'     => 'recette-quatre',
				'libelle' => 'Cran de recette quatre (fictif)',
				'rang'    => 4,
			),
		),
		'correspondance_source' => array(
			1 => 'recette-un',
			2 => 'recette-deux',
			3 => 'recette-trois',
			4 => 'recette-quatre',
		),
	);
};

update_option(
	'massifs_meteo_snapshots',
	array(
		str_replace( '-', '', $aujourdhui ) => array(
			'schema'        => 1,
			'date_validite' => $aujourdhui,
			'zone_cle'      => '13',
			'niveau_source' => 2,
			'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
			'recupere_le'   => gmdate( DATE_ATOM ),
			'hash'          => hash( 'sha256', 'jonction' ),
			'octets'        => 120,
		),
	),
	false
);

add_filter( 'massifs_meteo_vocabulaire', $vocabulaire_de_recette );

$servi = massifs_meteo_du_jour();

t_egal( 'disponible', $servi['etat'], 'jonction : vocabulaire fourni et instantané en cache, le SERVEUR rend « disponible »' );
t_egal( $libelle_fictif, $servi['niveau']['libelle'], 'jonction : le libellé du cran vient du vocabulaire, verbatim' );
t_egal( 4, $servi['echelle']['crans'], 'jonction : la cardinalité servie est celle de la table, jamais une constante' );
t_egal( 2, $servi['echelle']['atteint'], 'jonction : le rang atteint suit le jeton de la source' );
t_egal( '2 crans sur 4', $servi['echelle']['phrase'], 'jonction : la phrase de position est rédigée par le SERVEUR' );

// Le gabarit sans aucun argument : il appelle lui-même la fonction de lecture.
$html_jonction = t_rendre_partie( 'meteo' );

t_egal( 4, substr_count( $html_jonction, '<rect' ), 'jonction : le HTML porte autant de carrés que le serveur annonce de crans' );
t_egal( 2, substr_count( $html_jonction, 'meteo__carre--plein' ), 'jonction : autant de carrés pleins que de crans atteints' );
t_assert( str_contains( $html_jonction, 'viewBox="0 0 60 12"' ), 'jonction : la géométrie suit la cardinalité servie — 4 × 16 − 4', 'viewBox="0 0 60 12"', $html_jonction );
t_assert(
	str_contains( $html_jonction, '<span class="meteo__libelle">' . esc_html( $libelle_fictif ) . '</span>' ),
	'jonction : le libellé du serveur traverse jusqu’au HTML',
	esc_html( $libelle_fictif ),
	$html_jonction
);
t_assert(
	! str_contains( $html_jonction, '<fictif & jamais officiel>' ),
	'jonction : la chaîne d’origine tierce est ÉCHAPPÉE au rendu, jamais servie brute',
	'aucune occurrence non échappée',
	$html_jonction
);
t_assert(
	str_contains( $html_jonction, '<p class="meteo__crans">2 crans sur 4</p>' ),
	'jonction : la phrase du serveur est rendue verbatim, sans composition du thème',
	'<p class="meteo__crans">2 crans sur 4</p>',
	$html_jonction
);
t_assert(
	str_contains( $html_jonction, '<p class="meteo__attribution">' . esc_html( $servi['attribution']['texte'] ) . '</p>' ),
	'jonction : l’attribution du serveur n’apparaît QUE là où une donnée est affichée',
	$servi['attribution']['texte'],
	$html_jonction
);
t_assert( str_contains( $html_jonction, esc_html( $servi['distinction'] ) ), 'jonction : la distinction du serveur demeure dans l’état disponible' );
t_assert( ! str_contains( $html_jonction, 'Danger météo du jour non disponible.' ), 'jonction : aucune phrase d’indisponibilité quand la donnée est là' );

// --- Troisième bras, de bout en bout lui aussi : demain n'a aucun instantané.
$html_demain_reel = t_rendre_partie( 'meteo', array( 'jour' => $demain ) );

t_egal( 'non_encore_publie', massifs_meteo_du_jour( $demain )['etat'], 'jonction : demain sans instantané, le serveur rend « non encore publié »' );
t_assert(
	str_contains( $html_demain_reel, '<p class="meteo__indisponible">Le danger météo de demain n\'est pas encore publié.</p>' ),
	'jonction : le troisième bras est rendu par le gabarit à partir du serveur réel',
	'Le danger météo de demain n\'est pas encore publié.',
	$html_demain_reel
);
t_assert( ! str_contains( $html_demain_reel, '<svg' ), 'jonction : demain n’emprunte jamais l’échelle d’aujourd’hui' );
t_assert( ! str_contains( $html_demain_reel, 'Etalab' ), 'jonction : aucune attribution sur un jour sans donnée' );

// --- La garde se referme, et RIEN de la veille ne survit à sa fermeture.
// L'instantané reste en cache : c'est précisément le cas où un repli sur « le
// dernier état connu » serait une violation.
remove_filter( 'massifs_meteo_vocabulaire', $vocabulaire_de_recette );

$html_referme = t_rendre_partie( 'meteo' );

t_egal( 'indisponible', massifs_meteo_du_jour()['etat'], 'garde refermée : le serveur retombe sur « indisponible » malgré l’instantané en cache' );
t_assert(
	str_contains( $html_referme, '<p class="meteo__indisponible">Danger météo du jour non disponible.</p>' ),
	'garde refermée : le gabarit rend l’absence, jamais le dernier niveau connu',
	'Danger météo du jour non disponible.',
	$html_referme
);
t_assert( ! str_contains( $html_referme, '<rect' ), 'garde refermée : aucun carré ne subsiste' );
t_assert( ! str_contains( $html_referme, 'fictif' ), 'garde refermée : aucun libellé ne subsiste' );
t_assert( ! str_contains( $html_referme, 'Etalab' ), 'garde refermée : l’attribution disparaît avec la donnée' );

remove_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$purge_meteo();
t_reset();
t_bilan();
