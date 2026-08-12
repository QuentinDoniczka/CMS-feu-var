<?php
/**
 * Les états que l'horloge réelle ne permet pas d'atteindre en HTTP.
 *
 * « Dispositif estival inactif » et « statuts de demain pas encore publiés »
 * dépendent du jour civil. La stack n'expose aucun filtre pour geler l'horloge
 * du domaine : on ne triche donc pas avec le temps, on demande explicitement le
 * JOUR à la partie de gabarit — ce qui est exactement l'interface publique de
 * celle-ci — et on rend le gabarit réel, avec le domaine réel derrière.
 *
 * Ce n'est pas un test unitaire : c'est le même fichier de gabarit que sert
 * Apache, alimenté par les mêmes fonctions de lecture, dont on observe le HTML
 * produit.
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

$jour_hors_saison = '2026-01-15';
$jour_en_saison   = massifs_jour_courant();
$demain           = massifs_jour_suivant();

// --- Contrôle préalable : le domaine dit bien ce que le gabarit va rendre.
t_egal( 'hors_saison', massifs_synthese_du_jour( massifs_codes(), $jour_hors_saison )['etat_global'], 'domaine : le 15 janvier est hors saison' );
t_egal( 'non_encore_publie', massifs_synthese_du_jour( massifs_codes(), $demain )['etat_global'], 'domaine : demain n’est pas encore publié' );

// --- HORS SAISON : la liste rend l'état vide, jamais un tableau.
$html = t_rendre_partie( 'liste-statuts', array( 'jour' => $jour_hors_saison ) );

t_assert( str_contains( $html, 'id="liste"' ), 'hors saison : l’ancre #liste est conservée', 'id="liste"', substr( $html, 0, 200 ) );
t_assert( str_contains( $html, 'La liste du jour' ), 'hors saison : le titre de la liste est conservé' );
t_assert( ! str_contains( $html, '<table' ), 'hors saison : aucun tableau de statuts', 'aucun <table>', 'un <table> est rendu' );
t_assert( str_contains( $html, 'bandeau-alerte--hors-saison' ), 'hors saison : le bandeau d’état vide « hors saison » est rendu' );
t_assert( str_contains( $html, 'Dispositif estival inactif.' ), 'hors saison : la phrase officielle est rendue mot pour mot' );
t_assert( str_contains( $html, 'Reprise le ' ), 'hors saison : la date de reprise est annoncée' );
t_assert( ! str_contains( $html, 'Accès au massif' ), 'hors saison : aucun niveau d’accès n’est affiché', 'aucun libellé de niveau', 'un libellé de niveau est rendu' );

$saison = massifs_saison( $jour_hors_saison );
t_assert(
	str_contains( $html, 'datetime="' . $saison['prochaine_ouverture'] . '"' ),
	'hors saison : la date de reprise rendue est celle du domaine',
	$saison['prochaine_ouverture'],
	$html
);

// --- JOUR FUTUR NON PUBLIÉ : phrase entière du §11.3, jamais un report de la veille.
$html = t_rendre_partie( 'liste-statuts', array( 'jour' => $demain ) );

t_assert( ! str_contains( $html, '<table' ), 'demain non publié : aucun tableau de statuts' );
t_assert( str_contains( $html, 'bandeau-alerte--non-publie' ), 'demain non publié : le bandeau d’état vide correspondant est rendu' );
t_assert(
	str_contains( $html, 'Les statuts de demain ne sont pas encore publiés.' ),
	'demain non publié : la phrase officielle est rendue',
	'Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.',
	$html
);
t_assert( ! str_contains( $html, 'Accès au massif' ), 'demain non publié : aucun niveau d’accès n’est affiché' );

// --- La veille EXISTE, le jour demandé non : rien ne doit être reporté.
$hier = t_jour_avant( $jour_en_saison );
foreach ( massifs_codes() as $code ) {
	massifs_enregistrer_statut(
		array(
			'massif_code'   => $code,
			'jour_validite' => $hier,
			'niveau_cle'    => 'autorise',
			'source'        => 'saisie_manuelle',
			'auteur_id'     => 1,
		)
	);
}

$html = t_rendre_partie( 'liste-statuts', array( 'jour' => $jour_en_saison ) );
t_assert( ! str_contains( $html, '<table' ), 'donnée de la veille en base : aucun tableau rendu pour aujourd’hui' );
t_assert( str_contains( $html, 'Information du jour non disponible.' ), 'donnée de la veille en base : « information non disponible » est rendue' );
t_assert( str_contains( $html, 'la carte officielle de la préfecture' ), 'donnée de la veille en base : le lien officiel est rendu' );
t_assert( ! str_contains( $html, 'Accès au massif autorisé' ), 'donnée de la veille en base : le niveau d’hier n’est jamais rendu aujourd’hui' );

// La même partie, sur le jour d'HIER, rend bien le tableau : la donnée existe,
// c'est sa date qui décide, jamais un repli sur « la dernière connue ».
$html_hier = t_rendre_partie( 'liste-statuts', array( 'jour' => $hier ) );
t_assert( str_contains( $html_hier, '<table' ), 'la donnée d’hier reste rendue à SA date' );
t_egal( 25, substr_count( $html_hier, 'Accès au massif autorisé' ), 'la donnée d’hier rend bien ses 25 lignes à sa date' );

// --- Le bandeau de non-officialité ne dépend d'aucun état.
$bandeau = t_rendre_partie( 'bandeau-non-officialite' );
t_assert( str_contains( $bandeau, 'Seules les publications de la préfecture des Bouches-du-Rhône font foi' ), 'bandeau de non-officialité : mention du §5.6 rendue' );
t_assert( str_contains( $bandeau, 'href="https://www.risque-prevention-incendie.fr/13"' ), 'bandeau de non-officialité : lien officiel rendu' );

// --- Une seconde inclusion avec la même ancre produirait des id en double :
// le contrat l'interdit à l'appelant. On vérifie que l'ancre paramétrable existe.
$html_ancre = t_rendre_partie( 'liste-statuts', array( 'jour' => $jour_hors_saison, 'ancre' => 'liste-demain' ) );
t_assert( str_contains( $html_ancre, 'id="liste-demain"' ), 'l’ancre est paramétrable : une seconde inclusion peut éviter les doublons d’id' );
t_assert( str_contains( $html_ancre, 'id="liste-demain-titre"' ), 'l’ancre préfixe tous les id de la partie' );

t_reset();
t_bilan();
