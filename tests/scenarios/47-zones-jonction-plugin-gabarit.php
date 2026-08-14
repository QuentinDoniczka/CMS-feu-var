<?php
/**
 * LA JONCTION — ce que l'extension rend, ce que le gabarit en fait.
 *
 * Les scénarios 40 à 46 éprouvent l'extension seule ; le gabarit
 * `templates/parts/panneau-feu.php` a été écrit en aveugle, contre le contrat
 * #11 et non contre ce code. Ce scénario est le SEUL endroit où les deux
 * moitiés se rencontrent : il alimente le gabarit RÉEL par la fonction de
 * lecture RÉELLE et observe le HTML produit.
 *
 * Il éprouve, dans cet ordre :
 *
 *  A. `couche_effis_indisponible` — le titre et la phrase d'absence, et RIEN
 *     d'autre : ni fraîcheur, ni limites, ni attribution (I-11.6, interdit 2).
 *  B. `aucune_zone` — la phrase d'absence MESURÉE, sa fraîcheur, la phrase de
 *     limites §11.3 et l'attribution.
 *  C. L'ASSERTION N° 1 DE L'ISSUE, au niveau du RENDU cette fois : les deux
 *     états portent `nombre === 0` et ne produisent JAMAIS les mêmes octets.
 *  D. `zones_disponibles` — la liste, les surfaces déjà formatées par le
 *     serveur, les instants, et l'omission pure de la commune (A-8).
 *  E. La garde d'attribution — évaluée AVANT toute autre, zéro octet à son
 *     échec, LISTE COMPRISE (I-11.6).
 *  F. Le repli A-15 et le repli d'état inconnu — tous deux vers
 *     `couche_effis_indisponible`, JAMAIS vers `aucune_zone`.
 *  G. Les invariants transverses de la jonction : aucune origine tierce, aucun
 *     script, échappement de toute valeur traversant la frontière.
 *
 * Le vocabulaire d'état est comparé LITTÉRAL POUR LITTÉRAL contre
 * `Couche::ETATS` : une faute de frappe d'un côté ferait tomber le gabarit dans
 * son repli et afficherait « indisponible » à perpétuité pendant que
 * l'extension croit servir de la donnée. C'est la défaillance classique du
 * travail en parallèle, et elle est silencieuse.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Effis\Couche;
use Massifs\Ingest\Effis\Runner;

if ( ! function_exists( 't_effis_purge' ) ) {
	/**
	 * Purge les options de ce module, que `t_reset()` ne connaît pas.
	 */
	function t_effis_purge(): void {
		delete_option( 'massifs_effis_releve' );
		delete_option( 'massifs_effis_etat' );
		delete_option( 'massifs_effis_reglages' );
		delete_option( 'massifs_dernier_releve' );
	}
}

if ( ! function_exists( 't_rendre_partie' ) ) {
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
}

if ( ! function_exists( 't_effis_carre' ) ) {
	/**
	 * Polygone carré centré sur un point. Fixture de scénario, jamais du module.
	 *
	 * @param float $lon Longitude du centre.
	 * @param float $lat Latitude du centre.
	 *
	 * @return array<string, mixed>
	 */
	function t_effis_carre( float $lon, float $lat ): array {
		$d = 0.01;

		return array(
			'type'        => 'Polygon',
			'coordinates' => array(
				array(
					array( $lon - $d, $lat - $d ),
					array( $lon + $d, $lat - $d ),
					array( $lon + $d, $lat + $d ),
					array( $lon - $d, $lat + $d ),
					array( $lon - $d, $lat - $d ),
				),
			),
		);
	}
}

t_reset();
t_effis_purge();

// Armement en cours de requête : voir l'en-tête du scénario 40.
if ( ! defined( 'MASSIFS_EFFIS_URL' ) ) {
	define( 'MASSIFS_EFFIS_URL', 'http://wordpress/massifs-bouchon-effis.json' );
}

// Les deux replis du gabarit passent par `_doing_it_wrong()` : on les compte
// plutôt que de les subir, pour prouver qu'ils sont empruntés QUAND il le faut
// et JAMAIS autrement.
$replis = 0;
add_action(
	'doing_it_wrong_run',
	static function () use ( &$replis ) {
		++$replis;
	}
);

// Les chaînes du serveur, lues UNE SEULE FOIS et jamais recopiées ici : ce
// scénario compare le gabarit à l'extension, pas à sa propre idée des deux.
$attribution_serveur = massifs_attribution_zones_parcourues_par_le_feu()['phrase'];

// Recopiée de MASTER.md §11.3, la seule chaîne que le contrat confie au thème
// (A-3). Elle est ici pour vérifier que le gabarit la rend mot pour mot ET que
// l'extension ne la publie pas.
$limites_master = 'Périmètres estimés par satellite (feux d\'environ 30 ha et plus). Zone déjà parcourue par le feu, ce n\'est pas un périmètre officiel d\'interdiction.';

// ---------------------------------------------------------------------------
// A. `couche_effis_indisponible` — rien n'a jamais été relevé.
// ---------------------------------------------------------------------------
$couche_indispo = massifs_zones_parcourues_par_le_feu();
t_egal( 'couche_effis_indisponible', $couche_indispo['etat'], 'A. l\'extension sert bien couche_effis_indisponible' );

$html_indispo = t_rendre_partie( 'panneau-feu' );
t_note( 'HTML indisponible = ' . $html_indispo );

t_assert( '' !== $html_indispo, 'A. l\'état indisponible rend du HTML : une absence se déclare, elle ne se tait pas', 'du HTML', $html_indispo );
t_assert( str_contains( $html_indispo, '<div class="bande bande--zones-parcourues">' ), 'A. la partie est auto-portante jusqu\'à la bande (A-13)', '<div class="bande bande--zones-parcourues">', $html_indispo );
t_assert( str_contains( $html_indispo, '<section id="zones-parcourues" class="bande__contenu zones-parcourues" aria-labelledby="zones-parcourues-titre">' ), 'A. le landmark porte son ancre et son nom accessible', '<section id="zones-parcourues" …>', $html_indispo );
t_assert( str_contains( $html_indispo, '<h2 id="zones-parcourues-titre" class="zones-parcourues__titre">Zones parcourues par le feu</h2>' ), 'A. un h2 en casse normale, jamais un h1', '<h2 …>Zones parcourues par le feu</h2>', $html_indispo );
t_assert( str_contains( $html_indispo, 'Donnée momentanément indisponible.' ), 'A. la phrase d\'état du §4.4 du brief, ponctuée', 'Donnée momentanément indisponible.', $html_indispo );

t_assert( ! str_contains( $html_indispo, $attribution_serveur ), 'A. AUCUNE attribution : créditer une source dont rien n\'est affiché est une affirmation fausse (I-11.6)', 'attribution absente', $html_indispo );
t_assert( ! str_contains( $html_indispo, 'zones-parcourues__fraicheur' ), 'A. AUCUNE fraîcheur : rien n\'est daté puisque rien n\'est mesuré (interdit 2)' );
t_assert( ! str_contains( $html_indispo, 'zones-parcourues__limites' ), 'A. AUCUNE phrase de limites : rien à qualifier (interdit 2)' );
t_assert( ! str_contains( $html_indispo, '<ul' ) && ! str_contains( $html_indispo, '<dl' ), 'A. aucune liste, aucun couple nom/valeur' );
t_assert( ! str_contains( $html_indispo, 'Donnée périmée' ), 'A. « Donnée périmée. » n\'existe pas pour cette bande (A-4) : la péremption RETIRE, elle n\'annote pas' );
t_egal( 0, $replis, 'A. l\'état servi est reconnu par le match() du gabarit : AUCUN repli emprunté' );

// ---------------------------------------------------------------------------
// B. `aucune_zone` — un relevé validé, et il est vide.
// ---------------------------------------------------------------------------
t_bouchon_http(
	t_reponse_200(
		array(
			'type'     => 'FeatureCollection',
			'features' => array(),
		)
	)
);

t_assert( true === Runner::executer(), 'B. le lot vide est accepté : zéro entité est le cas nominal', true, 'rejet' );

$couche_aucune = massifs_zones_parcourues_par_le_feu();
t_egal( 'aucune_zone', $couche_aucune['etat'], 'B. l\'extension sert bien aucune_zone' );

$html_aucune = t_rendre_partie( 'panneau-feu' );
t_note( 'HTML aucune_zone = ' . $html_aucune );

t_assert( str_contains( $html_aucune, 'Aucune zone parcourue par le feu détectée.' ), 'B. la phrase d\'absence MESURÉE, sans aucun chiffre (A-7)', 'Aucune zone parcourue par le feu détectée.', $html_aucune );
t_assert( str_contains( $html_aucune, 'zones-parcourues__fraicheur' ), 'B. la fraîcheur est rendue : « vide parce que mesuré » porte une date de mesure' );
t_assert( str_contains( $html_aucune, '>Relevé le ' ), 'B. la formule de fraîcheur déjà en service (liste-statuts.php l. 342), réemployée sans reformulation', 'Relevé le …', $html_aucune );
t_assert( str_contains( $html_aucune, '<p class="zones-parcourues__limites">' . esc_html( $limites_master ) . '</p>' ), 'B. la phrase de limites §11.3 est rendue OCTET POUR OCTET, et aussi dans aucune_zone (§3)', $limites_master, $html_aucune );
t_assert( str_contains( $html_aucune, '<p class="zones-parcourues__attribution">' . esc_html( $attribution_serveur ) . '</p>' ), 'B. l\'attribution du serveur est rendue ENTIÈRE', $attribution_serveur, $html_aucune );
t_assert( ! str_contains( $html_aucune, '<a ' ), 'B. l\'attribution est en TEXTE NU : aucun `lien_licence` n\'existe, donc aucune destination à décrire (§1.2)' );
t_assert( ! str_contains( $html_aucune, '<ul' ), 'B. aucune liste vide : le <ul> ne s\'émet que sur zones_disponibles' );
t_assert( ! str_contains( $html_aucune, 'Donnée momentanément indisponible.' ), 'B. l\'absence mesurée ne se dit PAS avec les mots de l\'absence de mesure' );

// L'extension ne publie PAS la phrase de limites : deux sources pour une même
// chaîne divergeraient (A-3, interdit 18).
$publiees = wp_json_encode( array( $couche_aucune, massifs_attribution_zones_parcourues_par_le_feu() ) );
t_assert( ! str_contains( (string) $publiees, 'Périmètres estimés' ), 'B. l\'extension ne publie AUCUNE phrase de limites : elle appartient au thème (A-3, interdit 18)', 'absente du retour serveur', (string) $publiees );

// ---------------------------------------------------------------------------
// C. L'ASSERTION N° 1 DE L'ISSUE, au niveau du RENDU.
// ---------------------------------------------------------------------------
t_egal( $couche_indispo['nombre'], $couche_aucune['nombre'], 'C. les DEUX états portent nombre === 0 : `nombre` ne discrimine RIEN' );
t_assert( $html_indispo !== $html_aucune, 'C. …et pourtant les deux RENDUS diffèrent : le discriminant est `etat` (§3.1)', 'deux rendus distincts', 'identiques' );
t_assert( ! str_contains( $html_indispo, 'Aucune zone parcourue par le feu' ), 'C. « nous ne savons pas » ne s\'écrit JAMAIS « aucune zone parcourue par le feu » — le faux négatif de sécurité de l\'issue' );

// ---------------------------------------------------------------------------
// D. `zones_disponibles` — la liste.
// ---------------------------------------------------------------------------
$emprise    = massifs_emprise();
$centre_lon = ( (float) $emprise['bbox']['ouest'] + (float) $emprise['bbox']['est'] ) / 2;
$centre_lat = ( (float) $emprise['bbox']['sud'] + (float) $emprise['bbox']['nord'] ) / 2;

t_effis_purge();
t_bouchon_http(
	t_reponse_200(
		array(
			'type'     => 'FeatureCollection',
			'features' => array(
				array(
					'type'       => 'Feature',
					'properties' => array(
						'id'                   => 'zpf-2026-0142',
						'surface_ha'           => 42.0,
						'premiere_observation' => '2026-08-12T09:30:00Z',
						'derniere_observation' => '2026-08-13T21:05:00Z',
					),
					'geometry'   => t_effis_carre( $centre_lon, $centre_lat ),
				),
				array(
					'type'       => 'Feature',
					'properties' => array(
						'id'                   => 'zpf-2026-0143',
						'surface_ha'           => 4.5,
						// Jour civil nu : l'extension rend '' et la paire est omise.
						'premiere_observation' => '2026-08-11',
						'derniere_observation' => '2026-08-13T18:00:00Z',
					),
					'geometry'   => t_effis_carre( $centre_lon + 0.05, $centre_lat + 0.03 ),
				),
			),
		)
	)
);

t_assert( true === Runner::executer(), 'D. le lot de deux zones est accepté', true, 'rejet' );

$couche_zones = massifs_zones_parcourues_par_le_feu();
t_egal( 'zones_disponibles', $couche_zones['etat'], 'D. l\'extension sert bien zones_disponibles' );

$html_zones = t_rendre_partie( 'panneau-feu' );
t_note( 'HTML zones_disponibles = ' . $html_zones );

t_assert( str_contains( $html_zones, '<ul class="zones-parcourues__liste" role="list">' ), 'D. la liste porte un role="list" explicite (corollaire liant du §10 D-15)', '<ul … role="list">', $html_zones );
t_egal( $couche_zones['nombre'], substr_count( $html_zones, '<li class="zones-parcourues__zone"' ), 'D. le gabarit rend TOUTES les zones servies : ni tri, ni filtre, ni re-bornage (interdit 9)' );

// Les surfaces viennent du serveur, DÉJÀ FORMATÉES, espace insécable comprise.
foreach ( $couche_zones['zones'] as $zone ) {
	t_assert(
		'' !== $zone['surface_texte'] && str_contains( $html_zones, '<dd class="zones-parcourues__valeur">' . esc_html( $zone['surface_texte'] ) . '</dd>' ),
		'D. la surface « ' . $zone['surface_texte'] .' » est rendue telle que le serveur l\'a composée : le thème ne formate jamais un nombre',
		$zone['surface_texte'],
		$html_zones
	);
}

t_assert( str_contains( $html_zones, '<dt class="zones-parcourues__etiquette">Surface estimée</dt>' ), 'D. étiquette « Surface estimée », mot pour mot du §5.2 du brief' );
t_assert( str_contains( $html_zones, '<dt class="zones-parcourues__etiquette">Première observation</dt>' ), 'D. étiquette « Première observation »' );
t_assert( str_contains( $html_zones, '<dt class="zones-parcourues__etiquette">Dernière observation</dt>' ), 'D. étiquette « Dernière observation »' );

// Une seule « Première observation » pour deux zones : celle dont la source ne
// donnait qu'un jour civil est PUREMENT OMISE, jamais comblée par un midi UTC.
t_egal( 1, substr_count( $html_zones, 'Première observation' ), 'D. jour civil nu ⇒ la paire est omise : AUCUN midi fabriqué (A-11)' );
t_egal( 2, substr_count( $html_zones, 'Dernière observation' ), 'D. les deux instants complets sont rendus' );
t_assert( ! str_contains( $html_zones, 'Commune la plus proche' ), 'D. aucune paire « Commune la plus proche » tant que la donnée est vide : ni tiret, ni « non renseigné » (A-8)' );

// Les deux clés de transport ne traversent jamais la frontière du rendu.
t_assert( ! str_contains( $html_zones, 'coordinates' ) && ! str_contains( $html_zones, 'Polygon' ), 'D. `geometrie` n\'est jamais lue : ce gabarit rend du texte, il ne projette rien (interdit 5)' );
t_assert( ! str_contains( $html_zones, '42.0' ) && ! str_contains( $html_zones, '4.5' ), 'D. `surface_ha` n\'est jamais lue : la surface affichable est `surface_texte` (interdit 4)' );

t_assert( str_contains( $html_zones, 'zones-parcourues__fraicheur' ), 'D. la fraîcheur accompagne la donnée' );
t_assert( str_contains( $html_zones, esc_html( $limites_master ) ), 'D. la phrase de limites accompagne la donnée' );
t_assert( str_contains( $html_zones, esc_html( $attribution_serveur ) ), 'D. l\'attribution accompagne la donnée (I-11.6)' );
t_assert( ! str_contains( $html_zones, 'Aucune zone' ) && ! str_contains( $html_zones, 'Donnée momentanément indisponible.' ), 'D. aucune phrase d\'absence quand des zones sont servies' );

t_egal( 0, $replis, 'D. les TROIS états de l\'énumération fermée sont reconnus par le gabarit : aucun repli emprunté' );

// ---------------------------------------------------------------------------
// D bis. Les littéraux d'état, comparés UN À UN entre les deux moitiés.
// ---------------------------------------------------------------------------
foreach ( Couche::ETATS as $etat_serveur ) {
	$avant = $replis;
	$rendu = t_rendre_partie(
		'panneau-feu',
		array(
			'zones_parcourues' => array_merge(
				$couche_zones,
				array(
					'etat'   => $etat_serveur,
					'zones'  => 'zones_disponibles' === $etat_serveur ? $couche_zones['zones'] : array(),
					'nombre' => 'zones_disponibles' === $etat_serveur ? $couche_zones['nombre'] : 0,
				)
			),
		)
	);

	t_assert( $replis === $avant, 'D bis. « ' . $etat_serveur .' » est un littéral que le gabarit connaît : aucun repli (faute de frappe = panne silencieuse)', 'match() satisfait', 'repli emprunté' );
	t_assert( '' !== $rendu, 'D bis. « ' . $etat_serveur . ' » rend du HTML', 'du HTML', $rendu );
}

// ---------------------------------------------------------------------------
// E. La garde d'attribution — évaluée AVANT toute autre (I-11.6).
// ---------------------------------------------------------------------------
$html_sans_mention = t_rendre_partie( 'panneau-feu', array( 'attribution' => array( 'phrase' => '   ', 'faits' => array() ) ) );
t_egal( '', $html_sans_mention, 'E. attribution vide ⇒ ZÉRO OCTET, alors même que des zones sont servies : afficher une donnée EFFIS sans sa mention manque au §9 du brief' );

$html_avec_mention = t_rendre_partie( 'panneau-feu' );
t_assert( '' !== $html_avec_mention, 'E. …et la même couche rend bien du HTML quand la mention existe', 'du HTML', $html_avec_mention );

// ---------------------------------------------------------------------------
// F. Les deux replis — vers `couche_effis_indisponible`, JAMAIS vers `aucune_zone`.
// ---------------------------------------------------------------------------
$avant   = $replis;
$illisible = t_rendre_partie(
	'panneau-feu',
	array(
		'zones_parcourues' => array_merge(
			$couche_zones,
			array(
				'etat'  => 'zones_disponibles',
				'zones' => array(
					array(
						'id'                     => 'zpf-illisible',
						'surface_texte'          => '',
						'surface_ha'             => 0.0,
						'premiere_observation'   => '',
						'derniere_observation'   => '',
						'commune_la_plus_proche' => '',
						'geometrie'              => array(
							'type'        => 'Polygon',
							'coordinates' => array(),
						),
					),
				),
			)
		),
	)
);

t_assert( str_contains( $illisible, 'Donnée momentanément indisponible.' ), 'F. A-15 : une zone illisible dans un relevé validé bascule sur couche_effis_indisponible', 'Donnée momentanément indisponible.', $illisible );
t_assert( ! str_contains( $illisible, 'Aucune zone' ), 'F. A-15 : et JAMAIS sur aucune_zone — affirmer une absence MESURÉE depuis une donnée ILLISIBLE est le faux négatif du §3.1' );
t_assert( ! str_contains( $illisible, esc_html( $attribution_serveur ) ), 'F. A-15 : le repli retire aussi l\'attribution et les limites' );
t_assert( $replis > $avant, 'F. A-15 : le repli est signalé par _doing_it_wrong(), jamais silencieux', 'un repli journalisé', $replis );

$avant   = $replis;
$inconnu = t_rendre_partie( 'panneau-feu', array( 'zones_parcourues' => array_merge( $couche_zones, array( 'etat' => 'zones_dispo' ) ) ) );

t_assert( str_contains( $inconnu, 'Donnée momentanément indisponible.' ), 'F. un quatrième état non contractuel replie sur couche_effis_indisponible', 'Donnée momentanément indisponible.', $inconnu );
t_assert( ! str_contains( $inconnu, 'Aucune zone' ), 'F. un repli est une ABSENCE DÉCLARÉE, jamais un faux négatif de sécurité (interdit 16)' );
t_assert( $replis > $avant, 'F. le match() est SANS default : l\'ajout d\'un état par l\'extension reste bruyant', 'un repli journalisé', $replis );

// ---------------------------------------------------------------------------
// G. Invariants transverses de la jonction.
// ---------------------------------------------------------------------------
$tous = $html_indispo . $html_aucune . $html_zones;

t_assert( ! str_contains( $tous, '<script' ), 'G. sans JavaScript, la bande est IDENTIQUE : elle n\'enfile aucun script et n\'en dépend d\'aucun (§5.3 du brief)' );
t_assert( ! str_contains( $tous, 'http://' ) && ! str_contains( $tous, 'https://' ), 'G. aucune origine tierce, ni même de même origine : la bande n\'émet aucune URL (contrainte n° 2)' );
t_assert( ! str_contains( $tous, 'pastille' ) && ! str_contains( $tous, 'jalon' ) && ! str_contains( $tous, 'statut' ) && ! str_contains( $tous, 'bandeau-alerte' ), 'G. aucune classe des familles interdites : aucune information portée par la couleur (interdit 11)' );
t_assert( ! str_contains( $tous, 'tabindex' ), 'G. aucun tabindex : aucun lien d\'évitement ne vise cette ancre' );
t_assert( ! str_contains( $tous, '<table' ), 'G. des <dl>, jamais un tableau : un tableau de quatre colonnes défilerait à 360 px (A-14)' );
t_assert( ! str_contains( $tous, '<h1' ) && ! str_contains( $tous, '<h3' ), 'G. un seul niveau de titre ajouté au plan de l\'accueil : un h2, aucun h3, jamais un h1' );
t_assert( 1 === substr_count( $html_zones, '<section' ) && 1 === substr_count( $html_zones, '</section>' ), 'G. un seul landmark, et il n\'est jamais vide (contrat #5 A-16)' );

// Échappement à la jonction : toute valeur traversant la frontière est échappée
// au rendu. La donnée d'ingestion est le pire vecteur possible.
$hostile = t_rendre_partie(
	'panneau-feu',
	array(
		'zones_parcourues' => array_merge(
			$couche_zones,
			array(
				'etat'  => 'zones_disponibles',
				'zones' => array(
					array(
						'id'                     => 'zpf-hostile',
						'surface_texte'          => '<script>alert(1)</script>',
						'surface_ha'             => 0.0,
						'premiere_observation'   => '',
						'derniere_observation'   => '',
						'commune_la_plus_proche' => '"><img src=x>',
						'geometrie'              => array(
							'type'        => 'Polygon',
							'coordinates' => array(),
						),
					),
				),
			)
		),
	)
);

t_assert( ! str_contains( $hostile, '<script>' ) && ! str_contains( $hostile, '<img' ), 'G. échappement à la jonction : aucune balise d\'une charge d\'ingestion n\'atteint le document', 'tout échappé', $hostile );
t_assert( str_contains( $hostile, '&lt;script&gt;' ), 'G. …et la valeur est bien rendue, échappée, jamais silencieusement supprimée', '&lt;script&gt;', $hostile );

t_effis_purge();
t_reset();
t_bilan();
