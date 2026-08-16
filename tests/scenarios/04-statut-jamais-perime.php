<?php
/**
 * RÈGLE ABSOLUE §4.2 : un statut périmé n'est jamais présenté comme
 * courant. Scénario complet, joué sur le domaine tel que le thème l'appellera.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';
global $wpdb;
t_reset();

$aujourdhui = massifs_jour_courant();
$hier       = ( new DateTimeImmutable( $aujourdhui, new DateTimeZone( 'Europe/Paris' ) ) )->modify( '-1 day' )->format( 'Y-m-d' );
$demain     = massifs_jour_suivant();
$codes      = array( 'sainte-victoire', 'calanques' );

// --- CAS 1 : aucune donnée pour aujourd'hui.
$statuts = massifs_statuts_du_jour( $codes, $aujourdhui );
t_egal( 2, count( $statuts ), 'une entrée par code demandé, même sans donnée' );
foreach ( $codes as $c ) {
	t_egal( 'indisponible', $statuts[ $c ]['etat'], "sans donnée : {$c} est indisponible" );
	t_egal( null, $statuts[ $c ]['niveau'], "sans donnée : {$c} ne porte aucun niveau" );
	t_egal( $aujourdhui, $statuts[ $c ]['jour_validite'], "jour_validite = jour DEMANDÉ pour {$c}" );
}
$synthese = massifs_synthese_du_jour( $codes, $aujourdhui );
t_egal( 'indisponible', $synthese['etat_global'], 'synthèse : état global indisponible' );
t_egal( 2, $synthese['sans_donnee'], 'synthèse : 2 massifs sans donnée' );

// --- CAS 2 : une donnée existe pour HIER, rien pour aujourd'hui.
foreach ( $codes as $c ) {
	$r = massifs_enregistrer_statut( array(
		'massif_code'   => $c,
		'jour_validite' => $hier,
		'niveau_cle'    => 'interdit',
		'zapef_cle'     => 'autorise',
		'source'        => 'saisie_manuelle',
		'auteur_id'     => 1,
	) );
	t_assert( $r['enregistre'], "statut de la veille enregistré pour {$c}", true, $r );
}

$statuts = massifs_statuts_du_jour( $codes, $aujourdhui );
foreach ( $codes as $c ) {
	t_egal( 'indisponible', $statuts[ $c ]['etat'], "donnée de la veille : {$c} reste indisponible aujourd'hui" );
	t_egal( null, $statuts[ $c ]['niveau'], "donnée de la veille : aucun niveau reporté sur aujourd'hui ({$c})" );
	t_egal( $aujourdhui, $statuts[ $c ]['jour_validite'], "jour_validite reste le jour demandé ({$c})" );
}
// La donnée d'hier reste consultable À SA DATE — l'historique n'est pas détruit.
$hier_lu = massifs_statuts_du_jour( $codes, $hier );
t_egal( 'disponible', $hier_lu['sainte-victoire']['etat'], 'la donnée de la veille reste lisible à SA date' );
t_egal( 'interdit', $hier_lu['sainte-victoire']['niveau']['cle'], 'niveau de la veille correct à sa date' );

// --- CAS 3 : la source publie « level 0 » = aucune donnée. Jamais « autorisé ».
$r = massifs_enregistrer_statut( array(
	'massif_code'        => 'sainte-victoire',
	'jour_validite'      => $aujourdhui,
	'niveau_source_brut' => 0,
	'procedure_source'   => 0,
	'source'             => 'saisie_manuelle',
	'auteur_id'          => 1,
) );
t_assert( $r['enregistre'], 'ligne « level 0 » enregistrée', true, $r );
$s = massifs_statut_du_jour( 'sainte-victoire', $aujourdhui );
t_egal( 'indisponible', $s['etat'], 'level 0 => état indisponible (jamais « autorisé par défaut »)' );
t_egal( null, $s['niveau'], 'level 0 => aucun niveau exposé' );
t_egal( null, $s['zapef'], 'level 0 => aucune ZAPEF « ouverte » inventée (A-16)' );
$ligne = $wpdb->get_row( $wpdb->prepare( "SELECT niveau_cle, niveau_source_brut FROM {$wpdb->prefix}massifs_statuts WHERE jour_validite = %s AND massif_code = %s ORDER BY id DESC LIMIT 1", $aujourdhui, 'sainte-victoire' ), ARRAY_A );
t_egal( null, $ligne['niveau_cle'], 'en base : niveau_cle NULL pour level 0' );
t_egal( '0', (string) $ligne['niveau_source_brut'], 'en base : le level brut 0 est conservé' );

// --- CAS 4 : hors saison, sans donnée => dispositif inactif, jamais un statut.
foreach ( array( '2026-01-15', '2026-05-31', '2026-10-01', '2025-12-24' ) as $jour_hs ) {
	$hs = massifs_statut_du_jour( 'sainte-victoire', $jour_hs );
	t_egal( 'hors_saison', $hs['etat'], "hors saison ({$jour_hs}) : état hors_saison" );
	t_egal( null, $hs['niveau'], "hors saison ({$jour_hs}) : aucun niveau" );
	$saison = massifs_saison( $jour_hs );
	t_egal( false, $saison['active'], "saison inactive le {$jour_hs}" );
	t_assert( is_string( $saison['prochaine_ouverture'] ) && '' !== $saison['prochaine_ouverture'], "prochaine ouverture annoncée le {$jour_hs} : " . $saison['prochaine_ouverture'] );
}
// Bornes incluses du dispositif.
t_egal( true, massifs_saison( '2026-06-01' )['active'], 'saison active le 1er juin (borne incluse)' );
t_egal( true, massifs_saison( '2026-09-30' )['active'], 'saison active le 30 septembre (borne incluse)' );

// --- CAS 5 : jour futur sans publication => non_encore_publie, jamais un report.
$d = massifs_statut_du_jour( 'calanques', $demain );
t_egal( 'non_encore_publie', $d['etat'], 'demain non publié : état non_encore_publie' );
t_egal( null, $d['niveau'], 'demain non publié : aucun niveau' );

// --- CAS 6 : fraîcheur. Un relevé vieux de 30 h en saison => péremption.
massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z', time() - 30 * HOUR_IN_SECONDS ) );
$f = massifs_fraicheur( $aujourdhui );
t_egal( true, $f['dispositif_actif'], 'dispositif actif en saison' );
t_egal( true, $f['perimee'], 'relevé de plus de 24 h => péremption signalée' );
t_assert( $f['age_secondes'] > 86400, 'âge du relevé > seuil', '>86400', $f['age_secondes'] );
t_egal( 86400, $f['seuil_secondes'], 'seuil de 24 h conforme au §4.5' );

// La péremption N'EST PAS un masque : la donnée du jour reste affichée (interdit 9).
$s = massifs_statut_du_jour( 'sainte-victoire', $hier );
t_egal( 'disponible', $s['etat'], 'la bannière de péremption ne masque aucune donnée valide' );

// Hors saison, aucune péremption n'est annoncée (le dispositif est inactif).
$f_hs = massifs_fraicheur( '2026-01-15' );
t_egal( false, $f_hs['dispositif_actif'], 'hors saison : dispositif inactif' );
t_egal( false, $f_hs['perimee'], 'hors saison : pas de bannière de péremption' );

// --- CAS 7 : aucune fonction « dernier statut connu » sans date (clause §8.4).
//
// LE GARDE EST NOMINAL, DONC IL ATTRAPE AUSSI DES HOMONYMES. Ce qu'il interdit
// est une lecture du DOMAINE DES STATUTS qui rendrait « la dernière valeur
// connue » sans qu'on lui ait demandé un jour — c'est par là qu'un statut périmé
// redeviendrait courant. Une fonction qui porte le même mot sans appartenir à ce
// domaine n'est pas ce défaut-là.
//
// LES EXEMPTIONS SONT NOMMÉES UNE À UNE, JAMAIS OBTENUES PAR UN MOTIF PLUS
// LÂCHE. Élargir le filtre (« sauf ce qui contient sauvegarde ») rouvrirait
// silencieusement la porte à toute future famille de noms. Ici, la liste reste
// courte et chaque entrée porte sa raison ; une fonction neuve qui contiendrait
// « dernier » sera ROUGE tant que personne n'aura écrit pourquoi elle est
// légitime.
//
// `massifs_sauvegardes_derniere()` (issue #16) rend la dernière ARCHIVE de
// sauvegarde. Elle ne lit aucun statut, ne connaît ni jour de validité ni
// fraîcheur, et n'est jamais appelée par un gabarit : le contrat #16 écrit
// qu'aucune de ses fonctions n'est destinée au thème.
$exemptees = array(
	'massifs_sauvegardes_derniere' => 'issue #16 — dernière ARCHIVE de sauvegarde ; hors du domaine des statuts, jamais lue par le thème',
);

$suspectes = array_values( array_filter(
	get_defined_functions()['user'],
	static fn( $f ) => str_starts_with( $f, 'massifs_' ) && ( str_contains( $f, 'dernier' ) || str_contains( $f, 'courant_connu' ) )
) );

$interdites = array_values( array_diff( $suspectes, array_keys( $exemptees ) ) );
t_egal( array(), $interdites, 'aucune fonction « dernier statut connu » n\'existe' );

// Une exemption qui ne correspond plus à rien est une exemption morte : elle
// masquerait la réapparition du nom qu'elle couvre. On l'affirme donc présente.
foreach ( $exemptees as $nom => $raison ) {
	t_assert(
		function_exists( $nom ),
		sprintf( 'l’exemption nominale « %s » couvre une fonction qui existe réellement (%s)', $nom, $raison ),
		'la fonction existe',
		'exemption morte, à retirer'
	);
}

t_reset();
t_bilan();
