<?php
/**
 * Rejouer une date déjà instantanée : republication et projection en échec.
 *
 * Deux défauts éprouvés ici, et leurs deux garde-fous :
 *
 * 1. Une date déjà couverte n'était plus jamais relue. La préfecture peut
 *    republier en cours de journée ; le modèle de statuts est append-only et
 *    absorbe parfaitement une correction, mais le connecteur n'en livrait
 *    jamais une.
 * 2. Un instantané enregistré dont la PROJECTION échouait ne laissait aucune
 *    trace côté ingestion, et rien ne relançait l'essai : le site annonçait
 *    « information non disponible » alors que la donnée était en cache.
 * 3. Et le défaut NÉ de la correction des deux premiers : un rejeu ré-émet un
 *    corps ANCIEN, et le statut courant se résout par la dernière écriture, sans
 *    préséance de source. Une reprise technique pouvait donc faire cesser d'être
 *    courante une correction saisie entre-temps au portail (§H).
 *
 * Et les deux dangers de la correction, qui comptent autant que la correction :
 * ne pas polluer l'historique en ré-émettant un corps inchangé déjà projeté
 * (miroir de `13-jours-consecutifs-identiques.php`, lignes 110-115), et ne pas
 * boucler indéfiniment quand personne n'écoute — l'état `sans_projecteur` est
 * terminal.
 *
 * MÉTHODE — ÉTAT DE BASE PARTAGÉ. La stack Docker est partagée par les chaînes
 * du lot : la table des statuts peut être écrite par un autre processus PENDANT
 * ce scénario. Aucun cas ne suppose donc un état de départ, pas même celui que
 * le cas précédent vient d'écrire : chaque cas purge, pose sa précondition,
 * agit, puis affirme un DELTA relevé juste avant l'acte — jamais un compte
 * absolu, qui serait un faux vert le jour où la base se trouve dans le bon état
 * par accident. Purge en entrée ET en sortie, filtres retirés.
 *
 * MÉTHODE — RÉSEAU. Les appels sortants sont comptés DANS le bouchon lui-même :
 * `pre_http_request` court-circuite avant `http_api_debug`, qui ne verrait donc
 * rien. `http_api_debug` reste posé en ceinture — « zéro octet a réellement
 * quitté la stack ». La saison et la fenêtre sont pilotées PAR FILTRES, jamais
 * par options : `Settings` a un cache statique.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Domain\Statuts\ProjecteurPrefecture;
use Massifs\Ingest\Prefecture\Runner;
use Massifs\Ingest\Prefecture\SnapshotRepository;
use Massifs\Ingest\Prefecture\StateRepository;

global $wpdb;

t_reset();
t_armer_connecteur();

$jour = massifs_jour_courant();
$ymd  = str_replace( '-', '', $jour );

// La saison est forcée sur AUJOURD'HUI seulement : demain devient hors saison,
// donc écarté sans appel. Le scénario ne dépend ainsi ni du mois où il est joué
// ni de l'heure à laquelle il tombe (au-delà de 16 h, demain serait candidat).
add_filter(
	'massifs_prefecture_est_en_saison',
	static function ( $en_saison, $date_ymd ) use ( $ymd ) {
		return $date_ymd === $ymd;
	},
	10,
	2
);

// --- Bouchon réseau COMPTEUR. C'est ici, et pas ailleurs, que les appels se
// comptent : le court-circuit `pre_http_request` empêche `http_api_debug` de
// voir quoi que ce soit.
$appels = 0;
$corps  = t_charge_source( 3, 1 );

add_filter(
	'pre_http_request',
	static function ( $court_circuit, $args, $url ) use ( &$appels, &$corps ) {
		++$appels;

		return t_reponse_200( $corps );
	},
	10,
	3
);

// --- Ceinture : aucun octet ne doit réellement sortir de la stack.
$urls = array();
add_action(
	'http_api_debug',
	static function ( $r, $c, $cl, $a, $url ) use ( &$urls ) {
		$urls[] = $url;
	},
	10,
	5
);

// --- Témoin des ré-émissions, branché AVANT le projecteur.
$emissions = 0;
$motifs    = array();
$temoin    = static function ( $instantane, $motif = '' ) use ( &$emissions, &$motifs ) {
	++$emissions;
	$motifs[] = (string) $motif;
};
add_action( 'massifs_prefecture_snapshot_enregistre', $temoin, 5, 2 );

/** Recule la tentative datée d'une date, pour simuler le temps écoulé. */
$vieillir = static function ( string $cible, int $secondes ): void {
	$etat                         = StateRepository::get();
	$etat['tentatives'][ $cible ] = time() - $secondes;
	update_option( StateRepository::OPTION, $etat, false );
};

/**
 * Recule l'instant de RÉCUPÉRATION d'un instantané.
 *
 * Ce n'est pas le même horodatage que celui du garde anti-rafale : `recupere_le`
 * date le CORPS, et c'est lui que le garde de rejeu compare à l'instant d'une
 * saisie manuelle. Sans ce recul, les deux instants tomberaient dans la même
 * seconde et le cas §H ne prouverait rien.
 */
$vieillir_instantane = static function ( string $cible, int $secondes ): void {
	$tous = get_option( SnapshotRepository::OPTION );

	$tous[ $cible ]['recupere_le'] = gmdate( 'c', time() - $secondes );

	update_option( SnapshotRepository::OPTION, $tous, false );
};

/**
 * Lignes de statut POUR LA SEULE JOURNÉE que ce scénario possède.
 *
 * Jamais `t_lignes_statuts()`, qui compte toute la table : celle-ci est partagée
 * par les chaînes du lot et par les autres scénarios. Un comptage global est
 * fragile par construction ici, et une assertion fragile qui passe est un faux
 * vert — exactement le défaut que ce lot combat.
 */
$lignes_du_jour = static function () use ( $jour ): int {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}massifs_statuts WHERE jour_validite = %s", // phpcs:ignore WordPress.DB
			$jour
		)
	);
};

/** Dernier niveau source réellement enregistré pour un massif, un jour donné. */
$dernier_niveau = static function ( string $code, string $jour_cible ) {
	global $wpdb;

	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT niveau_source_brut FROM {$wpdb->prefix}massifs_statuts WHERE massif_code = %s AND jour_validite = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB
			$code,
			$jour_cible
		)
	);
};

/** Dernière entrée de journal portant cette date cible. */
$derniere_entree = static function ( string $cible ): ?array {
	$trouve = null;

	foreach ( StateRepository::get()['journal'] as $entree ) {
		if ( is_array( $entree ) && ( $entree['date_cible'] ?? '' ) === $cible ) {
			$trouve = $entree;
		}
	}

	return $trouve;
};

t_note( 'constantes retenues : RECONTROLE_SECONDES=' . Runner::RECONTROLE_SECONDES
	. ', RECONTROLES_MAX_PAR_JOUR=' . Runner::RECONTROLES_MAX_PAR_JOUR
	. ', REJEUX_MAX_PAR_JOUR=' . Runner::REJEUX_MAX_PAR_JOUR );

// ====================================================================== §1
// Première récupération, puis REPUBLICATION EN COURS DE JOURNÉE.
t_reset();
$appels    = 0;
$emissions = 0;
$motifs    = array();
$corps     = t_charge_source( 3, 1 );
$base      = $lignes_du_jour();

Runner::run_scheduled();

t_egal( 1, $appels, 'première passe : un seul appel sortant' );
t_assert( SnapshotRepository::has( $ymd ), 'première passe : instantané enregistré' );
t_egal( $base + 25, $lignes_du_jour(), 'première passe : les 25 massifs nommés sont projetés' );
t_egal( 'complet', SnapshotRepository::projection( $ymd )['resultat'], 'l\'état de projection est consigné sur l\'instantané : complet' );
t_egal( 3, (int) $dernier_niveau( 'sainte-victoire', $jour ), 'première passe : le niveau publié est celui de la source' );

// --- §5 (ici, parce que c'est son moment) : ANTI-RAFALE.
$appels_avant = $appels;
$lignes_avant = $lignes_du_jour();
Runner::run_scheduled();
t_egal( $appels_avant, $appels, 'ANTI-RAFALE : deux passes rapprochées, la seconde ne redéclenche rien' );
t_egal( $lignes_avant, $lignes_du_jour(), 'anti-rafale : aucune ligne ajoutée' );

// --- La préfecture republie le même jour, avec des valeurs corrigées.
$corps = t_charge_source( 1, 0 );
$vieillir( $ymd, 4 * HOUR_IN_SECONDS );

$appels_avant    = $appels;
$emissions_avant = $emissions;
$lignes_avant    = $lignes_du_jour();
Runner::run_scheduled();

t_egal( $appels_avant + 1, $appels, 'REPUBLICATION : la date déjà couverte est bien re-contrôlée' );
t_egal( $emissions_avant + 1, $emissions, 'republication : l\'action est ré-émise' );
t_egal( $lignes_avant + 25, $lignes_du_jour(), 'republication : 25 lignes de plus, historique intact (jamais d\'écrasement)' );
t_egal( 1, (int) $dernier_niveau( 'sainte-victoire', $jour ), 'REPUBLICATION : la nouvelle valeur est visible' );
t_assert( in_array( 'republication', $motifs, true ), 'le motif voyage en second argument de l\'action', 'republication', $motifs );

// ====================================================================== §3
// Corps INCHANGÉ et projection COMPLÈTE : aucun rejeu, aucune ligne ajoutée.
// C'est le miroir de 13:112-115, et la garantie que l'historique reste lisible.
$vieillir( $ymd, 4 * HOUR_IN_SECONDS );

$appels_avant    = $appels;
$emissions_avant = $emissions;
$lignes_avant    = $lignes_du_jour();
Runner::run_scheduled();

t_egal( $appels_avant + 1, $appels, 'corps inchangé : le re-contrôle a bien eu lieu' );
t_egal( $emissions_avant, $emissions, 'CORPS INCHANGÉ + PROJECTION COMPLÈTE : aucune ré-émission' );
t_egal( $lignes_avant, $lignes_du_jour(), 'corps inchangé + projection complète : AUCUNE ligne ajoutée' );

$entree = $derniere_entree( $ymd );
t_egal( 'succes', (string) ( $entree['issue'] ?? '' ), 'le court-circuit par hachage est JOURNALISÉ, il ne sort plus en silence' );
t_assert(
	str_contains( (string) ( $entree['detail'] ?? '' ), 'aucune réécriture' ),
	'la note du journal distingue le cas « corps identique »',
	'… aucune réécriture.',
	$entree['detail'] ?? null
);

// ====================================================================== §2
// PROJECTION EN ÉCHEC : rejouée depuis le stockage, SANS aucun appel réseau.
//
// La précondition est POSÉE ICI et vérifiée avant l'acte : le cas ne repose pas
// sur ce que §3 vient de laisser derrière lui.
t_assert( SnapshotRepository::has( $ymd ), 'précondition §2 : un instantané couvre bien la date' );
SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'rejete', 'motif' => 'panne de base simulée' ) );
t_egal( 'rejete', SnapshotRepository::projection( $ymd )['resultat'], 'préalable : la projection est déclarée en échec' );

$appels_avant    = $appels;
$emissions_avant = $emissions;
$lignes_avant    = $lignes_du_jour();

t_egal( true, Runner::rejouer_projection( $ymd ), 'projection en échec : le rejeu est effectué' );
t_egal( $appels_avant, $appels, 'REJEU : ZÉRO APPEL SORTANT — le corps vient du stockage, pas du réseau' );
t_egal( $emissions_avant + 1, $emissions, 'rejeu : l\'action est ré-émise' );
t_egal( $lignes_avant + 25, $lignes_du_jour(), 'rejeu : la projection écrit enfin ses 25 lignes' );
t_egal( 'complet', SnapshotRepository::projection( $ymd )['resultat'], 'rejeu réussi : l\'état repasse à complet' );
t_assert( in_array( 'rejeu', $motifs, true ), 'le motif « rejeu » est déclaré à l\'abonné', 'rejeu', $motifs );

// --- Et par le chemin planifié : un rejeu gratuit PRIME sur une requête réseau.
SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'rejete' ) );
$appels_avant    = $appels;
$emissions_avant = $emissions;
Runner::run_scheduled();

t_egal( $appels_avant, $appels, 'passe planifiée : le rejeu gratuit prime, aucune requête réseau' );
t_egal( $emissions_avant + 1, $emissions, 'passe planifiée : l\'action est ré-émise depuis le stockage' );

// ====================================================================== §H
// UN REJEU NE RÉVOQUE JAMAIS UNE DÉCISION HUMAINE PLUS RÉCENTE.
//
// La séquence est atteignable telle quelle :
//   07 h — projection `partiel`, une écriture refusée par la base ;
//   09 h — le gestionnaire corrige un massif depuis l'écran de publication
//          (« correction du jour », §6 du brief) ;
//   10 h — passe planifiée, le corps de 07 h est candidat au rejeu.
//
// Le corps de 07 h n'apporte AUCUNE information nouvelle. Le rejouer ferait
// cesser d'être courante la correction de 09 h, sans alerte et sans autre trace
// que 25 lignes d'historique de plus : le statut courant est « la dernière
// écriture gagne », sans préséance de source.
t_reset();
$appels    = 0;
$emissions = 0;
$motifs    = array();
$corps     = t_charge_source( 3, 1 );
$base      = $lignes_du_jour();

// --- 07 h.
Runner::run_scheduled();
t_egal( $base + 25, $lignes_du_jour(), 'précondition §H : la passe de 07 h a projeté ses 25 lignes' );

// Le corps date de 07 h, trois heures avant la passe qui suit. Et la base a
// refusé une écriture : la projection est partielle, donc rejouable.
$vieillir_instantane( $ymd, 3 * HOUR_IN_SECONDS );
SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'partiel', 'motif' => 'une écriture refusée par la base' ) );

// --- CONTRÔLE NÉGATIF, sans lequel l'assertion centrale ne prouverait rien :
// dans cet état exact, et tant qu'aucun humain n'a tranché, le rejeu a lieu.
$lignes_avant = $lignes_du_jour();
t_egal( true, Runner::rejouer_projection( $ymd ), 'CONTRÔLE NÉGATIF : sans saisie manuelle, cet état EST rejouable' );
t_egal( $lignes_avant + 25, $lignes_du_jour(), 'contrôle négatif : le rejeu écrit bien ses 25 lignes' );

SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'partiel', 'motif' => 'une écriture refusée par la base' ) );

// --- 09 h : le gestionnaire corrige un massif depuis le portail.
$correction = massifs_enregistrer_statut(
	array(
		'massif_code'   => 'sainte-victoire',
		'jour_validite' => $jour,
		// « autorise », et surtout PAS le libellé vers lequel le corps de 07 h
		// projette (`level 3` donne `interdit`) : une correction qui tomberait sur
		// le même libellé que la source rendrait le rejeu indétectable dans le
		// rendu, et l'assertion sur le niveau ne mordrait pas.
		'niveau_cle'    => 'autorise',
		'zapef_cle'     => null,
		'source'        => 'saisie_manuelle',
		'auteur_id'     => 1,
	)
);
t_assert( $correction['enregistre'], 'préalable §H : la correction du gestionnaire est enregistrée', true, $correction );

$apres_saisie = massifs_statut_du_jour( 'sainte-victoire', $jour );
t_egal( 'saisie_manuelle', $apres_saisie['source'], 'préalable §H : la correction est bien le statut courant' );
t_egal( 'autorise', $apres_saisie['niveau']['cle'], 'préalable §H : c\'est bien le niveau saisi par le gestionnaire' );

// --- 10 h : la passe planifiée. C'EST ICI QUE LE DÉFAUT SE JOUAIT.
$emissions_avant = $emissions;
$appels_avant    = $appels;
$lignes_avant    = $lignes_du_jour();

t_egal( false, Runner::rejouer_projection( $ymd ), 'DÉFAUT CORRIGÉ : le rejeu s\'abstient devant une saisie manuelle postérieure' );

Runner::run_scheduled();

t_egal( $emissions_avant, $emissions, 'passe planifiée : le corps de 07 h n\'est pas ré-émis' );
t_egal( $lignes_avant, $lignes_du_jour(), 'passe planifiée : aucune ligne préfectorale ré-insérée' );

$apres_passe = massifs_statut_du_jour( 'sainte-victoire', $jour );
t_egal( 'saisie_manuelle', $apres_passe['source'], 'LA CORRECTION DE 09 H RESTE LE STATUT COURANT' );
t_egal( 'autorise', $apres_passe['niveau']['cle'], 'la décision humaine n\'a pas été révoquée par une copie périmée' );
t_egal( 'partiel', SnapshotRepository::projection( $ymd )['resultat'], 'le garde s\'abstient, il ne prétend pas que la projection est réparée' );

// --- La surveillance de la source, elle, N'EST PAS gelée. Corps inchangé : le
// re-contrôle a lieu, et n'ouvre pas non plus de rejeu — c'est le TROISIÈME
// chemin qui interroge la politique, et il doit rendre le même verdict.
//
// L'état est REPOSÉ à `partiel` : sans cela, la projection resterait `complet`,
// ce chemin serait écarté pour cette raison-là, et l'assertion documenterait au
// lieu de mordre.
SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'partiel', 'motif' => 'une écriture refusée par la base' ) );
$vieillir( $ymd, 4 * HOUR_IN_SECONDS );
$emissions_avant = $emissions;
$appels_avant    = $appels;
$lignes_avant    = $lignes_du_jour();
Runner::run_scheduled();

t_egal( $appels_avant + 1, $appels, 'le garde ne gèle pas la surveillance : le re-contrôle réseau a bien lieu' );
t_egal( $emissions_avant, $emissions, 'corps inchangé + saisie manuelle postérieure : aucune ré-émission' );
t_egal( $lignes_avant, $lignes_du_jour(), 'corps inchangé : aucune ligne préfectorale ré-insérée' );
t_egal( 'saisie_manuelle', massifs_statut_du_jour( 'sainte-victoire', $jour )['source'], 'la correction tient après le re-contrôle' );

// --- Et la préséance qui SUBSISTE, parce qu'elle se défend : un corps
// réellement nouveau n'est pas une copie périmée, c'est une donnée plus fraîche
// que la préfecture vient de publier. Elle prime, y compris sur une saisie
// manuelle antérieure.
$corps = t_charge_source( 1, 0 );
$vieillir( $ymd, 4 * HOUR_IN_SECONDS );
$emissions_avant = $emissions;
$lignes_avant    = $lignes_du_jour();
Runner::run_scheduled();

t_egal( $emissions_avant + 1, $emissions, 'republication : un corps réellement nouveau est ré-émis' );
t_egal( $lignes_avant + 25, $lignes_du_jour(), 'republication : les 25 lignes officielles sont écrites' );
t_egal(
	'recuperation_officielle',
	massifs_statut_du_jour( 'sainte-victoire', $jour )['source'],
	'PRÉSÉANCE DU RE-CONTRÔLE : une donnée réellement plus fraîche prime, elle'
);

// ====================================================================== §6
// BORNE DURE : une cause PERMANENTE d'échec de projection ne boucle pas.
t_reset();
$appels    = 0;
$emissions = 0;
$base      = $lignes_du_jour();

// Instantané dont le lot est irrécupérable par construction : `niveau_source`
// est une chaîne, le projecteur rejette le lot entier à chaque passage.
$instantane_casse = array(
	'schema'            => 1,
	'date_validite'     => $jour,
	'source_url'        => 'http://wordpress/massifs-bouchon/' . $ymd . '.json',
	'recupere_le'       => gmdate( 'c' ),
	'source_modifie_le' => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
	'hash'              => hash( 'sha256', 'lot-irrecuperable' ),
	'octets'            => 400,
	'brut'              => '{"massifs":{}}',
	'massifs'           => array( '131' => array( 'niveau_source' => '3' ) ),
	'zm'                => array(),
	'cles_inconnues'    => array(),
	'lot_sans_donnee'   => false,
	'mode'              => 'automatique',
	'confiance'         => 'nominale',
);

t_egal( true, SnapshotRepository::save( $instantane_casse ), 'préalable : instantané à lot irrécupérable posé' );
SnapshotRepository::update_projection( $ymd, array( 'resultat' => 'rejete', 'motif' => 'cause permanente' ) );

$rejeux = 0;
for ( $n = 0; $n < 10; $n++ ) {
	if ( Runner::rejouer_projection( $ymd ) ) {
		++$rejeux;
	}
}

t_egal( Runner::REJEUX_MAX_PAR_JOUR, $rejeux, 'BORNE DURE : jamais plus de REJEUX_MAX_PAR_JOUR rejeux pour cette date le même jour' );
t_egal( 'rejete', SnapshotRepository::projection( $ymd )['resultat'], 'la cause est permanente : la projection reste en échec' );
t_egal( $base, $lignes_du_jour(), 'lot irrécupérable : aucune ligne écrite, historique intact' );
t_egal( 0, $appels, 'la borne se tient sans le moindre appel sortant' );
t_egal( false, Runner::rejouer_projection( $ymd ), 'borne épuisée : plus aucun rejeu pour cette date aujourd\'hui' );

// ====================================================================== §4
// PROJECTEUR ABSENT, PUIS PROJECTEUR REVENU.
//
// Tant que personne n'écoute, réémettre ne répare rien : aucun rejeu, sinon le
// connecteur boucle jusqu'à sa borne, chaque jour, pour rien. Mais si le domaine
// est réparé une heure plus tard, laisser la date sans statut jusqu'à minuit
// alors que la donnée est en cache est le défaut 2, simplement décalé dans le
// temps. La PRÉSENCE D'UN ABONNÉ tranche entre les deux.
//
// LE TÉMOIN EST RETIRÉ ICI, ET C'EST INDISPENSABLE : il est lui-même un abonné
// de l'action, donc la sonde le verrait et conclurait qu'un projecteur est
// revenu. Ce qu'il comptait se lit ailleurs — le compteur de rejeux consommés,
// qui vit sur l'instantané.
t_reset();
remove_all_actions( 'massifs_prefecture_snapshot_enregistre' );

$appels = 0;
$corps  = t_charge_source( 2, 0 );
$base   = $lignes_du_jour();

Runner::run_scheduled();

t_egal( 1, $appels, 'projecteur absent : la récupération a bien lieu' );
t_assert( SnapshotRepository::has( $ymd ), 'projecteur absent : l\'instantané est quand même en cache' );
t_egal( 'sans_projecteur', SnapshotRepository::projection( $ymd )['resultat'], '« sans_projecteur », jamais confondu avec « rejete »' );
t_egal( $base, $lignes_du_jour(), 'projecteur absent : aucun statut écrit — le site dira « information non disponible »' );
t_egal( false, Runner::rejouer_projection( $ymd ), 'ABONNÉ ABSENT : le rejeu est refusé' );

// Douze passes, chacune assez espacée pour que le re-contrôle soit dû.
for ( $n = 0; $n < 12; $n++ ) {
	$vieillir( $ymd, 4 * HOUR_IN_SECONDS );
	Runner::run_scheduled();
}

t_egal( 0, SnapshotRepository::projection( $ymd )['rejeux'], 'APRÈS 12 PASSES, ABONNÉ TOUJOURS ABSENT : aucun rejeu consommé' );
t_egal( 'sans_projecteur', SnapshotRepository::projection( $ymd )['resultat'], 'l\'état n\'a pas bougé' );
t_egal( $base, $lignes_du_jour(), 'abonné absent : toujours aucun statut écrit' );
t_egal(
	1 + Runner::RECONTROLES_MAX_PAR_JOUR,
	$appels,
	'BORNE DURE de re-contrôles : le budget réseau tient malgré 12 passes'
);

// --- LE DOMAINE EST RÉPARÉ, EN COURS DE JOURNÉE. La donnée est en cache depuis
// le début : la date doit se rattraper le jour même, pas à minuit.
add_action( 'massifs_prefecture_snapshot_enregistre', $temoin, 5, 2 );
add_action( 'massifs_prefecture_snapshot_enregistre', array( ProjecteurPrefecture::class, 'projeter' ) );

$emissions    = 0;
$appels_avant = $appels;

t_egal( true, Runner::rejouer_projection( $ymd ), 'ABONNÉ REVENU : la date se rattrape le jour même' );
t_egal( $appels_avant, $appels, 'rattrapage : ZÉRO APPEL SORTANT, le corps vient du cache' );
t_egal( 1, $emissions, 'rattrapage : l\'instantané est ré-émis une fois' );
t_egal( $base + 25, $lignes_du_jour(), 'RATTRAPAGE : les 25 statuts sont enfin écrits, depuis le cache' );
t_egal( 'complet', SnapshotRepository::projection( $ymd )['resultat'], 'rattrapage : la projection est complète' );

// ====================================================================== §D
// BILAN NON TABULAIRE : un projecteur CASSÉ n'est pas un projecteur ABSENT.
//
// Le domaine répond, mais sa charge est inexploitable. Rien ne doit être écrit
// — et surtout pas `sans_projecteur`, qui est TERMINAL : le conclure ici
// condamnerait la date à ne plus jamais être rejouée, au moment précis où le
// domaine signale qu'il va mal.
t_reset();
remove_all_actions( 'massifs_prefecture_snapshot_enregistre' );
add_action( 'massifs_prefecture_snapshot_enregistre', $temoin, 5, 2 );

$projecteur_difforme = static function () {
	do_action( 'massifs_projection_prefecture', 'ceci est une chaine, pas un tableau' );
};
add_action( 'massifs_prefecture_snapshot_enregistre', $projecteur_difforme, 20 );

$appels    = 0;
$emissions = 0;
$corps     = t_charge_source( 2, 0 );
$base      = $lignes_du_jour();

Runner::run_scheduled();

t_assert( SnapshotRepository::has( $ymd ), 'bilan difforme : instantané enregistré' );
t_egal( 1, $emissions, 'bilan difforme : instantané publié une fois' );
t_egal( $base, $lignes_du_jour(), 'bilan difforme : aucun statut écrit, rien n\'est inventé' );

$resultat_difforme = SnapshotRepository::projection( $ymd )['resultat'];
t_assert(
	'sans_projecteur' !== $resultat_difforme,
	'BILAN DIFFORME : état PAS « sans_projecteur » — un projecteur existe et vient de parler',
	'tout sauf sans_projecteur',
	$resultat_difforme
);
t_egal( 'inconnue', $resultat_difforme, 'bilan difforme : rien d\'exploitable, donc état « inconnue »' );

// La conséquence qui compte : la date n'est pas condamnée. Dès que le domaine
// conclut un échec exploitable, le rejeu repart — ce qu'un état terminal aurait
// interdit pour toujours.
do_action(
	'massifs_projection_prefecture',
	array(
		'resultat' => 'rejete',
		'jour'     => $jour,
		'motif'    => 'le domaine finit par se déclarer en échec',
	)
);
t_egal( 'rejete', SnapshotRepository::projection( $ymd )['resultat'], 'bilan difforme puis bilan exploitable : état suit le domaine' );
t_egal( true, Runner::rejouer_projection( $ymd ), 'BILAN DIFFORME : la date reste rejouable, elle n\'a pas été condamnée' );

remove_action( 'massifs_prefecture_snapshot_enregistre', $projecteur_difforme, 20 );

// ====================================================================== §7
// MÉMOIRE DE L'ANTI-RAFALE : découplée du journal FIFO.
t_reset();
StateRepository::record_attempt_for( $ymd );
$memoire = StateRepository::last_attempt_for( $ymd );
t_assert( is_int( $memoire ) && $memoire > 0, 'la tentative datée est mémorisée', 'un horodatage', $memoire );

// 192 entrées : une journée entière à la cadence réelle (96 passes, deux dates
// par passe). Le journal FIFO de 20 a roulé neuf fois. C'est exactement ce
// volume qui effaçait la mémoire du garde anti-rafale.
for ( $n = 0; $n < 192; $n++ ) {
	StateRepository::record_issue( '20260101', 'non_publie', 'passe de bruit ' . $n );
}

$journal = StateRepository::get()['journal'];
t_egal( StateRepository::JOURNAL_MAX, count( $journal ), 'le journal a bien roulé jusqu\'à son plafond' );
t_assert(
	! in_array( $ymd, array_column( $journal, 'date_cible' ), true ),
	'le journal ne porte plus aucune trace de la date visée',
	'aucune',
	array_unique( array_column( $journal, 'date_cible' ) )
);
t_egal( $memoire, StateRepository::last_attempt_for( $ymd ), 'MÉMOIRE PRÉSERVÉE : 192 entrées de journal plus tard, le garde sait encore' );

// Repli : un état écrit AVANT l'introduction de la carte se lit encore.
$etat = StateRepository::get();
unset( $etat['tentatives'] );
$etat['journal'] = array(
	array(
		'le'         => gmdate( DATE_ATOM, time() - 60 ),
		'date_cible' => $ymd,
		'issue'      => 'succes',
		'detail'     => '',
	),
);
update_option( StateRepository::OPTION, $etat, false );
t_assert(
	is_int( StateRepository::last_attempt_for( $ymd ) ),
	'REPLI sans migration : un état sans carte de tentatives se lit encore dans le journal',
	'un horodatage',
	StateRepository::last_attempt_for( $ymd )
);

// ====================================================================== §E
// Écriture ciblée : elle ne crée jamais de date, et l'absence de clé se lit
// « inconnue ».
t_reset();
t_egal( 'inconnue', SnapshotRepository::projection( '20200101' )['resultat'], 'date sans instantané : projection « inconnue »' );
t_egal( false, SnapshotRepository::update_projection( '20200101', array( 'resultat' => 'complet' ) ), 'écriture ciblée sur une date inconnue : refusée' );
t_egal( false, SnapshotRepository::has( '20200101' ), 'écriture ciblée : aucune date créée' );

// ====================================================================== §G
// Marqueur hors saison : dédoublonné PAR DATE CIBLE, pas contre la seule
// dernière entrée. Sans quoi J et J+1 alternent et écrivent deux entrées par
// passe — 192 par jour dans un journal de 20.
t_reset();
remove_all_filters( 'massifs_prefecture_est_en_saison' );
add_filter( 'massifs_prefecture_est_en_saison', '__return_false' );
add_filter( 'massifs_prefecture_fenetre_publication', static fn() => array( 'debut' => 0, 'fin' => 23 ) );

$appels_avant = $appels;
for ( $n = 0; $n < 5; $n++ ) {
	Runner::run_scheduled();
}

$issues = array_column( StateRepository::get()['journal'], 'issue' );
t_note( 'journal hors saison après 5 passes : ' . wp_json_encode( $issues ) );
t_egal( array( 'hors_saison', 'hors_saison' ), $issues, 'HORS SAISON, 5 PASSES : deux entrées (J et J+1), pas dix' );
t_egal( $appels_avant, $appels, 'hors saison : zéro appel sortant' );

// ====================================================================== Ceinture
t_egal( array(), $urls, 'CEINTURE : aucun octet n\'a réellement quitté la stack sur tout le scénario' );

remove_all_filters( 'massifs_prefecture_est_en_saison' );
remove_all_filters( 'massifs_prefecture_fenetre_publication' );
remove_all_filters( 'pre_http_request' );
remove_all_actions( 'http_api_debug' );
remove_action( 'massifs_prefecture_snapshot_enregistre', $temoin, 5 );

// Le projecteur avait été débranché au §4 pour simuler son absence : il est
// remis en place, sans quoi ce scénario laisserait la stack dans un état où
// aucune ingestion n'écrit plus de statut.
add_action( 'massifs_prefecture_snapshot_enregistre', array( ProjecteurPrefecture::class, 'projeter' ) );

// Purge EN SORTIE : ce scénario ne doit pas laisser la base dans un état qui
// ferait rougir la recette d'une autre chaîne.
t_reset();
t_bilan();
