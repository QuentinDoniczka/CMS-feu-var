<?php
/**
 * Partie de gabarit — carte interactive des massifs (MASTER.md §7.1, §8.4).
 *
 * Rend le balisage de la carte, ses trois motifs SVG, la barre de jour, le
 * panneau massif et un îlot JSON à DEUX JOURS que `assets/js/carte/carte.js`
 * lit une seule fois. La carte est un ENRICHISSEMENT : la liste textuelle du
 * jour, rendue par `parts/liste-statuts.php`, reste l'équivalent garanti (§5.3
 * du brief), et `parts/carte-secours.php` est rendue SANS CONDITION à côté de
 * la racine `.carte` — jamais dans un `<noscript>` (contrat #9, F-1, I-9.1).
 *
 * Aucune règle métier ici : le jour, la saison, l'état, la sévérité et le
 * formatage des dates viennent des fonctions de lecture de l'extension. Aucune
 * route REST n'est appelée, ni par ce gabarit, ni par `carte.js`.
 *
 * Convention d'appel : `massifs_partie( 'carte' )`, sans `$args`.
 *
 * Gabarit pur, sans aucune déclaration : `load_template()` fait un `require` et
 * non un `require_once`, une partie incluse deux fois est donc ré-exécutée.
 *
 * Contrat d'interface : `docs/contracts/issue-7.md`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ═══ Garde 1 — surface de lecture ════════════════════════════════════════
//
// Garde COMPOSITE, avant tout octet de sortie, sur l'ensemble indispensable :
// ces onze fonctions viennent de trois modules de domaine distincts, qui
// peuvent échouer à charger indépendamment. Une seule absente et la carte ne
// peut plus être ni peinte ni décrite : elle rend alors ZÉRO OCTET.
//
// Pourquoi zéro octet et non un conteneur vide : `layout.css` l. 198-201 pose
// ses filets sur `.bande--carte:has(*)`. Un seul enfant invisible fait
// apparaître un filet 4 px en haut et un filet 2 px en bas ; sans contenu entre
// eux, ils se touchent et dessinent un trait noir au milieu de la page.
//
// Ce retour emporte aussi le repli de la chaîne #9, rendu en fin de fichier :
// la bande est alors entièrement vide et ses filets ne s'allument pas. C'est le
// seul écart subsistant à la LETTRE de la clause F-1 (« sans aucune
// condition ») ; le combler par une seconde inclusion en tête de fichier
// dupliquerait l'attribution du fond, ce que F-3 interdit.
if ( ! function_exists( 'massifs_referentiel' )
	|| ! function_exists( 'massifs_jour_courant' )
	|| ! function_exists( 'massifs_jour_suivant' )
	|| ! function_exists( 'massifs_statuts_du_jour' )
	|| ! function_exists( 'massifs_synthese_du_jour' )
	|| ! function_exists( 'massifs_horodatage' )
	|| ! function_exists( 'massifs_legende' )
	|| ! function_exists( 'massifs_attribution_statuts' )
	|| ! function_exists( 'massifs_saison' )
	|| ! function_exists( 'massifs_geometrie' )
	|| ! function_exists( 'massifs_emprise' ) ) {
	return;
}

// ═══ Garde 2 — données cartographiques ═══════════════════════════════════
//
// `disponible === true` atteste la présence des MÉTADONNÉES de l'artefact,
// jamais celle du fichier : c'est `carte.js` qui traite l'échec de son propre
// `fetch`. Sans emprise, aucun cadrage n'est possible et aucune coordonnée
// n'est écrite en dur pour y suppléer.
$geometrie = massifs_geometrie();
$emprise   = massifs_emprise();

if ( true !== $geometrie['disponible'] || null === $emprise['bbox'] ) {
	massifs_journaliser( 'massifs: carte non rendue — géométrie ou emprise absente du référentiel.' );

	return;
}

// ═══ Garde 3 — ressources vendorisées ════════════════════════════════════
//
// Sans Leaflet ni `carte.js` sur le disque, le balisage resterait masqué pour
// toujours (la barre et la toile sont démasquées par le JS) et la bande
// n'afficherait que ses deux filets accolés. On rend donc zéro octet plutôt
// qu'un trait noir, et on n'enfile jamais une URL qui répondrait 404.
$chemins_assets = array(
	'leaflet_css' => 'assets/vendor/leaflet/leaflet.css',
	'leaflet_js'  => 'assets/vendor/leaflet/leaflet.js',
	'carte_js'    => 'assets/js/carte/carte.js',
);

foreach ( $chemins_assets as $chemin_asset ) {
	if ( ! is_readable( get_theme_file_path( $chemin_asset ) ) ) {
		massifs_journaliser( sprintf( 'massifs: carte non rendue — ressource « %s » illisible.', $chemin_asset ) );

		return;
	}
}

// ═══ Lectures de domaine ═════════════════════════════════════════════════
//
// Le référentiel est DÉJÀ TRIÉ par `tri` : il n'est jamais retrié ici, et
// l'ordre des flèches du clavier est celui de la clé `ordre` de l'îlot.
$referentiel = massifs_referentiel();

if ( array() === $referentiel ) {
	massifs_journaliser( 'massifs: carte non rendue — référentiel vide.' );

	return;
}

$jour_courant = massifs_jour_courant();
$jour_suivant = massifs_jour_suivant();
$jours_rendus = array( $jour_courant, $jour_suivant );
$codes        = array_keys( $referentiel );

// Chaque jour est résolu EXACTEMENT UNE FOIS, et le tableau obtenu sert à
// l'îlot comme aux phrases : deux résolutions du même jour pourraient diverger
// si une écriture atterrissait entre elles (contrat #7, arbitrage A-11).
// `massifs_statut_du_jour()` n'est jamais appelée dans une boucle : ce serait
// 25 requêtes garanties.
$statuts_par_jour = array();

try {
	foreach ( $jours_rendus as $jour_demande ) {
		$statuts_par_jour[ $jour_demande ] = massifs_statuts_du_jour( $codes, $jour_demande );
	}

	// Un seul appel, pour le jour SUIVANT (arbitrage A-3) : le seul usage
	// légitime est de savoir si demain est publié, pour l'état du bouton
	// « Demain » et sa phrase. L'état global du jour courant est déjà rendu par
	// l'ardoise et par la liste ; le redire ici serait une troisième bannière.
	$synthese_suivant = massifs_synthese_du_jour( $codes, $jour_suivant );
} catch ( \InvalidArgumentException ) {
	massifs_journaliser( 'massifs: carte non rendue — le domaine a refusé un des deux jours.' );

	return;
}

// COUTURE assumée, idiome de `liste-statuts.php` l. 181 et `etats-vides.php`
// l. 140 : `massifs_horodatage()` exige un instant complet et refuse un jour
// civil nu. Midi UTC vaut 13 h ou 14 h à Paris, le jour civil ne bascule
// jamais. Seul `date_longue` est lu ; `heure` et `attr_datetime` de CET appel
// décriraient midi et sont interdits. L'attribut `datetime` reçoit le
// `AAAA-MM-JJ` brut. À retirer dès que `massifs_horodatage_jour()` existe (B-1).
$libelles_jour = array();

foreach ( $jours_rendus as $jour_demande ) {
	$libelles_jour[ $jour_demande ] = '';

	try {
		$horodatage_jour = massifs_horodatage( $jour_demande . 'T12:00:00Z' );

		if ( isset( $horodatage_jour['date_longue'] ) && is_string( $horodatage_jour['date_longue'] ) ) {
			$libelles_jour[ $jour_demande ] = $horodatage_jour['date_longue'];
		}
	} catch ( \InvalidArgumentException ) {
		$libelles_jour[ $jour_demande ] = '';
	}
}

// Le premier des trois verrous de l'arbitrage A-1 : le jour affiché est écrit
// EN TOUTES LETTRES, en permanence, au-dessus de la carte. Sans lui, un écran
// pourrait montrer une carte « demain » au-dessus d'une liste « aujourd'hui »
// sans que le jour soit écrit nulle part — exactement la règle de sécurité
// produit que ce projet refuse d'enfreindre. La carte n'est donc pas rendue.
if ( '' === $libelles_jour[ $jour_courant ] || '' === $libelles_jour[ $jour_suivant ] ) {
	massifs_journaliser( 'massifs: carte non rendue — un des deux jours ne peut pas être mis en toutes lettres (demande B-1).' );

	return;
}

$legende     = massifs_legende();
$attribution = massifs_attribution_statuts();

$zapef_note = isset( $legende['zapef_note'] ) && is_string( $legende['zapef_note'] ) ? $legende['zapef_note'] : '';

$attribution_texte = isset( $attribution['texte'] ) && is_string( $attribution['texte'] ) ? $attribution['texte'] : '';

// SEULE source légitime de cette adresse : `massifs_legende()['source_officielle_url']` n'est jamais lue ici.
$carte_officielle = isset( $attribution['carte_officielle_url'] ) && is_string( $attribution['carte_officielle_url'] )
	? trim( $attribution['carte_officielle_url'] )
	: '';

// ═══ Message du jour suivant ═════════════════════════════════════════════
//
// `match()` SANS bras `default`, enveloppé — idiome des sept `match()` de
// `templates/parts/**` : l'ajout d'un cinquième état par le domaine doit
// rester bruyant, et le repli est une ABSENCE, jamais une donnée.
//
// `\u{00A0}` et non le caractère littéral : l'espace INSÉCABLE avant « h »
// fait partie de la chaîne verbatim de MASTER §11.3 (contrat #7 §5.2). Sous
// cette forme il reste VISIBLE en relecture et survit à tout éditeur ou
// correcteur qui le normaliserait silencieusement en espace ordinaire — c'est
// exactement l'accident qui l'avait fait tomber ici.
$etat_suivant_brut = isset( $synthese_suivant['etat_global'] ) && is_string( $synthese_suivant['etat_global'] )
	? $synthese_suivant['etat_global']
	: '';

try {
	$message_suivant = match ( $etat_suivant_brut ) {
		'disponible'        => array(
			'etat'  => 'disponible',
			'texte' => '',
		),
		'indisponible'      => array(
			'etat'  => 'indisponible',
			'texte' => 'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
		),
		'hors_saison'       => array(
			'etat'  => 'hors_saison',
			'texte' => 'Dispositif estival inactif.',
		),
		'non_encore_publie' => array(
			'etat'  => 'non_encore_publie',
			'texte' => "Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17\u{00A0}h.",
		),
	};
} catch ( \UnhandledMatchError ) {
	massifs_journaliser( 'massifs: état de synthèse du jour suivant inconnu — repli sur « indisponible » pour le message de la carte.' );

	$message_suivant = array(
		'etat'  => 'indisponible',
		'texte' => 'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
	);
}

// « Reprise le {date}. » ne s'obtient que du calendrier de saison, et
// `prochaine_ouverture` est la SEULE clé lue : l'état, lui, vient de `etat`,
// jamais de `massifs_saison()['active']`.
$reprise_texte = '';
$reprise_brute = '';

if ( 'hors_saison' === $message_suivant['etat'] ) {
	try {
		$saison    = massifs_saison( $jour_suivant );
		$prochaine = isset( $saison['prochaine_ouverture'] ) && is_string( $saison['prochaine_ouverture'] )
			? $saison['prochaine_ouverture']
			: '';

		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $prochaine ) ) {
			$horodatage_reprise = massifs_horodatage( $prochaine . 'T12:00:00Z' );

			if ( isset( $horodatage_reprise['date_courte'] ) && is_string( $horodatage_reprise['date_courte'] )
				&& '' !== $horodatage_reprise['date_courte'] ) {
				$reprise_texte = $horodatage_reprise['date_courte'];
				$reprise_brute = $prochaine;
			}
		}
	} catch ( \InvalidArgumentException ) {
		massifs_journaliser( 'massifs: date de reprise du dispositif refusée par le domaine ; proposition omise sur la carte.' );
	}
}

// ═══ Îlot JSON ═══════════════════════════════════════════════════════════
//
// Forme figée par le contrat #7 §4. Ce que l'îlot NE contient JAMAIS : aucune
// valeur hexadécimale, aucun `jeton_css`, aucun `severite`, `rang`, `total`,
// `consigne`, `statut_id`, `auteur_id`, aucune coordonnée de massif, aucune
// phrase rédigée dépendante du jour — la seule chaîne composée est la
// fraîcheur, imposée par l'arbitrage A-4.
//
// Il n'est JAMAIS mis en cache : ni transient, ni `wp_cache_*`, ni fichier. La
// garantie de source unique vient de ce que la carte et la liste lisent la même
// base dans la même requête HTTP.
$ordre        = array();
$ilot_massifs = array();

foreach ( $referentiel as $code_massif => $massif ) {
	if ( ! is_array( $massif ) ) {
		continue;
	}

	// `libelle` est le seul champ affichable d'un massif ; `source.nom_massif`
	// ne l'est jamais. Sans libellé, aucun `aria-label` honnête n'est possible :
	// le massif sort de l'ordre du curseur, bruyamment.
	$libelle_massif = isset( $massif['libelle'] ) && is_string( $massif['libelle'] ) ? trim( $massif['libelle'] ) : '';

	if ( '' === $libelle_massif ) {
		massifs_journaliser( 'massifs: massif sans libellé — retiré de l\'ordre du curseur de la carte.' );

		continue;
	}

	$ordre[]                      = (string) $code_massif;
	$ilot_massifs[ $code_massif ] = array( 'libelle' => $libelle_massif );
}

if ( array() === $ordre ) {
	massifs_journaliser( 'massifs: carte non rendue — aucun massif ne porte de libellé.' );

	return;
}

$ilot_jours = array();

foreach ( $jours_rendus as $jour_demande ) {
	$ilot_jours[ $jour_demande ] = array();

	foreach ( $ordre as $code_massif ) {
		$entree = isset( $statuts_par_jour[ $jour_demande ][ $code_massif ] )
			&& is_array( $statuts_par_jour[ $jour_demande ][ $code_massif ] )
			? $statuts_par_jour[ $jour_demande ][ $code_massif ]
			: array();

		$etat_brut = isset( $entree['etat'] ) && is_string( $entree['etat'] ) ? $entree['etat'] : '';

		try {
			$etat = match ( $etat_brut ) {
				'disponible'        => 'disponible',
				'indisponible'      => 'indisponible',
				'hors_saison'       => 'hors_saison',
				'non_encore_publie' => 'non_encore_publie',
			};
		} catch ( \UnhandledMatchError ) {
			massifs_journaliser( 'massifs: état de statut inconnu sur la carte ; repli sur « indisponible ».' );

			$etat = 'indisponible';
		}

		$blocs = array(
			'niveau' => null,
			'zapef'  => null,
		);

		$fraicheur = null;

		// `niveau` et `zapef` ne sont lus QUE dans la branche disponible : un
		// niveau n'est jamais affiché quand l'état n'est pas `disponible`.
		if ( 'disponible' === $etat ) {
			foreach ( array( 'niveau', 'zapef' ) as $nom_bloc ) {
				$source_bloc = isset( $entree[ $nom_bloc ] ) && is_array( $entree[ $nom_bloc ] )
					? $entree[ $nom_bloc ]
					: array();

				if ( array() === $source_bloc ) {
					continue;
				}

				$libelle_bloc = isset( $source_bloc['libelle'] ) && is_string( $source_bloc['libelle'] )
					? $source_bloc['libelle']
					: '';

				if ( '' === $libelle_bloc ) {
					continue;
				}

				// Table FERMÉE : aucune clé n'est dérivée d'un `jeton_css` ni
				// calculée. Une clé inconnue ne produit AUCUN aplat et AUCUN
				// motif — l'échec est bruyant, jamais une teinte fausse.
				try {
					$cle_bloc = match ( isset( $source_bloc['cle'] ) && is_string( $source_bloc['cle'] ) ? $source_bloc['cle'] : '' ) {
						'autorise' => 'autorise',
						'interdit' => 'interdit',
					};
				} catch ( \UnhandledMatchError ) {
					massifs_journaliser( 'massifs: clé de niveau ou de ZAPEF inconnue sur la carte ; bloc omis.' );

					continue;
				}

				$blocs[ $nom_bloc ] = array(
					'cle'     => $cle_bloc,
					'libelle' => $libelle_bloc,
				);
			}

			// Arbitrage A-4 : la fraîcheur du panneau est celle de CE statut,
			// pas la phrase de l'ardoise. `enregistre_le` est le seul instant
			// garanti non nul quand l'état est `disponible`. Formulation reprise
			// à l'identique du contrat #6 arbitrage J, déjà rendue par
			// `liste-statuts.php` l. 342 : en inventer une seconde ferait dire
			// deux choses au même fait. Composée ICI, en PHP : `carte.js` la
			// recopie, il ne la compose pas.
			$enregistre = isset( $entree['enregistre_le'] ) && is_string( $entree['enregistre_le'] )
				? $entree['enregistre_le']
				: '';

			if ( '' !== $enregistre ) {
				try {
					$horodatage_releve = massifs_horodatage( $enregistre );

					if ( isset( $horodatage_releve['date_courte'], $horodatage_releve['heure'] )
						&& is_string( $horodatage_releve['date_courte'] )
						&& is_string( $horodatage_releve['heure'] ) ) {
						$fraicheur = 'Relevé le ' . $horodatage_releve['date_courte'] . ' à ' . $horodatage_releve['heure'];
					}
				} catch ( \InvalidArgumentException ) {
					massifs_journaliser( 'massifs: instant de relevé refusé par le domaine ; fraîcheur omise sur la carte.' );
				}
			}
		}

		$ilot_jours[ $jour_demande ][ $code_massif ] = array(
			'etat'      => $etat,
			'niveau'    => $blocs['niveau'],
			'zapef'     => $blocs['zapef'],
			'fraicheur' => $fraicheur,
		);
	}
}

$ilot = array(
	'version'      => 1,
	'jour_courant' => $jour_courant,
	'jour_suivant' => $jour_suivant,
	// `ordre` est EXPLICITE et n'est jamais déduit de l'ordre des clés d'un
	// objet JSON, dont aucune spécification ne garantit la stabilité.
	'ordre'        => $ordre,
	'massifs'      => $ilot_massifs,
	'jours'        => $ilot_jours,
	'geometrie'    => array(
		// L'URL est reprise TELLE QUELLE, jeton `?v=` compris : la composer à la
		// main ou lui retirer son jeton est un interdit gravé.
		'url'      => $geometrie['url'],
		'format'   => $geometrie['format'],
		'zoom_max' => $geometrie['zoom_max'],
	),
	'emprise'      => array(
		// Leaflet attend `[[sud, ouest], [nord, est]]` : la conversion appartient
		// à `carte.js`. Aucune coordonnée n'est écrite en dur, nulle part.
		'bbox'     => $emprise['bbox'],
		'zoom_max' => $emprise['zoom_max'],
	),
);

// ═══ Fond de carte — point d'attache unique avec la chaîne #9 ════════════
//
// Les quatre conditions sont cumulatives ; à défaut, la clé `fond` est
// ENTIÈREMENT ABSENTE de l'îlot, et `carte.js` ne pose aucune couche de tuiles.
//
// `url_modele` ne passe JAMAIS par `esc_url()` (F-12) : la fonction supprime
// `{` et `}`, hors de sa liste blanche, et produirait `…/zxy.png` — une panne
// silencieuse, visible seulement à l'exécution. L'îlot part par
// `wp_json_encode()`, qui échappe sans amputer.
//
// Les clés `attribution` et `attribution_url` de ce retour ne sont PAS lues :
// l'attribution du fond est portée EN PERMANENCE, dans tous les états, par
// `.carte-secours__attribution` de la chaîne #9, que `carte.js` ne retire
// jamais. En rendre une seconde ici la dupliquerait dès qu'un montage réussit —
// ce que F-3 interdit nommément, et l'ODbL avec.
$fond_expose = false;

if ( function_exists( 'massifs_fond_de_carte' ) ) {
	$fond = massifs_fond_de_carte();

	$fond_expose = isset( $fond['disponible'], $fond['format'], $fond['url_modele'] )
		&& true === $fond['disponible']
		&& 'raster' === $fond['format']
		&& is_string( $fond['url_modele'] )
		&& '' !== $fond['url_modele'];

	if ( $fond_expose ) {
		$ilot['fond'] = array(
			'url_modele' => $fond['url_modele'],
			'format'     => $fond['format'],
			'zoom_min'   => isset( $fond['zoom_min'] ) ? (int) $fond['zoom_min'] : 0,
			'zoom_max'   => isset( $fond['zoom_max'] ) ? (int) $fond['zoom_max'] : $emprise['zoom_max'],
		);
	}
}

// `JSON_HEX_TAG` est STRUCTUREL et obligatoire : il échappe `<` en `<`,
// donc `</script>` ne peut pas apparaître dans une valeur et refermer la
// balise. C'est le seul drapeau porteur de sécurité ; `JSON_HEX_APOS` et
// `JSON_HEX_QUOT` protègent un contexte d'attribut, or l'îlot est du contenu
// d'élément.
$ilot_serialise = wp_json_encode(
	$ilot,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ( ! is_string( $ilot_serialise ) ) {
	massifs_journaliser( 'massifs: carte non rendue — sérialisation de l\'îlot impossible.' );

	return;
}

// ═══ Enfilage ════════════════════════════════════════════════════════════
//
// `leaflet.css` est enfilée TARDIVEMENT et imprimée dans le pied : aucun FOUC
// possible, elle ne style que des éléments que Leaflet n'a pas encore créés, et
// elle passe APRÈS `carte.css` dans la cascade — toute surcharge de
// `dev-ux-cms` gagne donc en spécificité en se préfixant `.carte`.
//
// `strategy => defer` sur LES DEUX scripts : `defer` conserve l'ordre entre
// scripts différés, un mélange défer / non-défer casserait cette garantie.
wp_enqueue_style(
	'massifs-leaflet',
	get_theme_file_uri( $chemins_assets['leaflet_css'] ),
	array(),
	massifs_version_asset( $chemins_assets['leaflet_css'] )
);

wp_register_script(
	'massifs-leaflet',
	get_theme_file_uri( $chemins_assets['leaflet_js'] ),
	array(),
	massifs_version_asset( $chemins_assets['leaflet_js'] ),
	array(
		'in_footer' => true,
		'strategy'  => 'defer',
	)
);
wp_enqueue_script( 'massifs-leaflet' );

wp_register_script(
	'massifs-carte',
	get_theme_file_uri( $chemins_assets['carte_js'] ),
	array( 'massifs-leaflet' ),
	massifs_version_asset( $chemins_assets['carte_js'] ),
	array(
		'in_footer' => true,
		'strategy'  => 'defer',
	)
);
wp_enqueue_script( 'massifs-carte' );

// Idiome de `liste-statuts.php` : les variables de rendu sont réaffectées dans
// la boucle de balisage, elles sont donc nommées ici une fois pour toutes.
$etiquettes_hors_niveau = array(
	// Étiquettes courtes VERBATIM de MASTER §8.5 pour les deux premiers états,
	// identiques à celles de `liste-statuts.php` et de `legende.php` : aucune
	// chaîne nouvelle n'est créée pour la carte. Le troisième n'a pas
	// d'étiquette courte publiée — la phrase entière du §11.3 est rendue telle
	// quelle, et `composants.css` l. 195 porte l'exception de casse qui va avec.
	// `\u{00A0}` : le même espace insécable verbatim que ci-dessus.
	'indisponible'      => array(
		'marque'  => 'pastille pastille--indisponible',
		'libelle' => 'information non disponible',
	),
	'hors_saison'       => array(
		'marque'  => 'pastille pastille--hors-saison',
		'libelle' => 'dispositif estival inactif',
	),
	'non_encore_publie' => array(
		'marque'  => 'pastille pastille--non-publie',
		'libelle' => "Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17\u{00A0}h.",
	),
);

$demain_publie = 'disponible' === $message_suivant['etat'];
?>
<div class="carte">
<?php
/*
 * SEULE SORTIE NON ÉCHAPPÉE DU THÈME, et c'est délibéré.
 *
 * `esc_html()` n'est JAMAIS appliqué à un îlot JSON : le contenu d'un
 * `<script>` est du *raw text*, les entités n'y sont pas décodées, et
 * `esc_html()` produirait un JSON corrompu que `JSON.parse` refuserait. La
 * sécurité est portée par `JSON_HEX_TAG` ci-dessus, qui rend `</script>`
 * inexprimable dans une valeur.
 *
 * Ce n'est PAS un script exécutable : `type="application/json"` est un type de
 * données, le navigateur ne l'évalue pas.
 */
?>
<script type="application/json" id="carte-donnees"><?php echo $ilot_serialise; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
<?php
/*
 * Les trois motifs, rendus PAR PHP et non par le JS.
 *
 * `patternUnits="userSpaceOnUse"` est écrit EXPLICITEMENT : sa valeur par
 * défaut, `objectBoundingBox`, rendrait la densité de hachure proportionnelle à
 * la taille de chaque massif et variable à chaque zoom. Rien n'aurait l'air
 * cassé pendant que l'information redeviendrait portée par la couleur seule —
 * c'est le vrai piège de cette issue, plus encore que le zoom lui-même.
 *
 * Aucune couleur n'est posée ici : `.carte__motif-aplat` (le fond du carreau)
 * et `.carte__motif-trait` (les traits) sont le point de contact exact avec
 * `dev-ux-cms`, qui les peint depuis `carte.css` avec les jetons `--statut-*`.
 * `fill="none"` sur les traits n'est pas une couleur, c'est une absence : sans
 * lui, le remplissage par défaut d'un `<circle>` SVG est noir. Une déclaration
 * CSS l'emporte de toute façon sur cet attribut de présentation.
 *
 * L-C1 : le carreau mesure 10 unités, valeur de `--statut-motif-pas`
 * (`tokens.css` l. 63) recopiée en géométrie SVG parce que `width` / `height`
 * d'un `<pattern>` ne sont pas pilotables par CSS de façon portable. Les
 * segments qui débordent du carreau (de -1 à 11) referment les diagonales d'un
 * carreau à l'autre ; `overflow: hidden` du `<pattern>` les rogne exactement où
 * le carreau voisin les reprend.
 */
?>
<svg class="carte__defs" aria-hidden="true" focusable="false"><defs>
<pattern id="carte-motif-interdit" patternUnits="userSpaceOnUse" width="10" height="10">
<rect class="carte__motif-aplat" width="10" height="10"></rect>
<path class="carte__motif-trait" fill="none" d="M-1,9 L1,11 M0,0 L10,10 M9,-1 L11,1 M-1,1 L1,-1 M0,10 L10,0 M9,11 L11,9"></path>
</pattern>
<pattern id="carte-motif-indisponible" patternUnits="userSpaceOnUse" width="10" height="10">
<rect class="carte__motif-aplat" width="10" height="10"></rect>
<path class="carte__motif-trait" fill="none" d="M-1,1 L1,-1 M0,10 L10,0 M9,11 L11,9"></path>
</pattern>
<pattern id="carte-motif-non-publie" patternUnits="userSpaceOnUse" width="10" height="10">
<rect class="carte__motif-aplat" width="10" height="10"></rect>
<circle class="carte__motif-trait" fill="none" cx="5" cy="5" r="1.25"></circle>
</pattern>
</defs></svg>
<?php
/*
 * Barre de jour AU-DESSUS de la toile, jamais flottante (arbitrage A-10) : à
 * 360 px, un chrome flottant recouvrirait le héros et sa lisibilité dépendrait
 * d'un aplat opaque posé dessus.
 *
 * Les deux libellés de jour sont rendus par PHP, un par jour : `carte.js` ne
 * fait que basculer `hidden`, il n'écrit jamais une phrase et ne formate jamais
 * une date. Les boutons portent `data-bascule` et non `data-jour` — tout
 * élément portant `data-jour` est un nœud à visibilité pilotée par le jour, et
 * les boutons, eux, restent visibles en permanence.
 */
?>
<div class="carte__barre" hidden>
<?php foreach ( $jours_rendus as $jour_demande ) : ?>
<p class="carte__jour" data-jour="<?php echo esc_attr( $jour_demande ); ?>"<?php echo $jour_demande === $jour_courant ? '' : ' hidden'; ?>>Jour affiché : <time datetime="<?php echo esc_attr( $jour_demande ); ?>"><?php echo esc_html( $libelles_jour[ $jour_demande ] ); ?></time></p>
<?php endforeach; ?>
<div class="carte__jours" role="group" aria-label="Jour affiché">
<button type="button" class="carte__jour-bouton" data-bascule="<?php echo esc_attr( $jour_courant ); ?>" aria-pressed="true">Aujourd'hui</button>
<?php
/*
 * `aria-disabled` et JAMAIS l'attribut `disabled` : le bouton reste focusable,
 * reste dans l'ordre de tabulation, porte `aria-describedby` vers la phrase
 * §11.3, et son activation annonce cette phrase dans la région live sans
 * changer de jour. Un `disabled` HTML le sortirait du parcours clavier et
 * supprimerait toute explication.
 */
?>
<button type="button" class="carte__jour-bouton" data-bascule="<?php echo esc_attr( $jour_suivant ); ?>" aria-pressed="false"<?php echo $demain_publie ? '' : ' aria-disabled="true" aria-describedby="carte-message"'; ?>>Demain</button>
</div>
</div>
<?php
/*
 * La toile. `role="group"` et non `role="application"` : les 25 polygones sont
 * des `<path role="button">` que Leaflet crée dans un `<svg>` interne, et ce
 * groupe leur donne un nom accessible commun. Elle est masquée jusqu'à ce que
 * `carte.js` l'ait démasquée : un conteneur en `display: none` mesure 0 × 0, et
 * Leaflet initialisé dessus ne rendrait rien.
 */
?>
<div class="carte__toile" role="group" aria-label="Carte des massifs" aria-describedby="carte-aide" hidden></div>
<?php
/*
 * Phrase §11.3 du sélecteur (arbitrage A-3). Sa seule PRÉSENCE dit que demain
 * n'est pas publié : elle n'est rendue que dans ce cas, qui est exactement
 * celui où le bouton « Demain » porte `aria-disabled`.
 *
 * Elle ne porte donc PAS `data-jour` : sa visibilité n'est pas pilotée par le
 * jour affiché, et la bascule de `carte.js` ne doit pas l'atteindre — un
 * passage sur « Aujourd'hui » la masquerait définitivement, le bouton
 * « Demain » désactivé ne pouvant plus la ramener.
 *
 * Elle reste masquée dans le HTML servi et c'est `carte.js` qui la démasque
 * avec la barre, sans interaction : elle est SŒUR de `.carte__barre` et non
 * fille (carte.css l. 308-311), elle serait sinon visible sans JavaScript, où
 * aucun sélecteur de jour n'existe — « Information du jour non disponible »
 * y contredirait la liste des statuts du jour rendue juste au-dessus.
 */
?>
<?php if ( '' !== $message_suivant['texte'] ) : ?>
<p class="carte__message" id="carte-message" hidden><?php echo esc_html( $message_suivant['texte'] ); ?><?php if ( '' !== $reprise_texte ) : ?> Reprise le <time datetime="<?php echo esc_attr( $reprise_brute ); ?>"><?php echo esc_html( $reprise_texte ); ?></time>.<?php endif; ?></p>
<?php endif; ?>
<?php if ( '' !== $attribution_texte ) : ?>
<p class="carte__attribution" data-attribution="statuts" hidden><?php echo esc_html( $attribution_texte ); ?></p>
<?php endif; ?>
<?php
/*
 * Le panneau n'est NI `aria-modal`, NI `role="dialog"` : il est hors du flux de
 * focus par simple ordre du DOM, le fond n'est jamais `inert`, le défilement de
 * la page n'est jamais verrouillé, et il n'existe donc aucun piège clavier, y
 * compris sur la feuille du bas à 360 px.
 *
 * Le repère (§3.2) est posé sur le `h2` — un titre de statut le garde (§8.4
 * ligne 1) — et NON sur le bord gauche du panneau (emplacement 4) : deux
 * repères dans le même bloc sont un défaut §16, et la couleur d'état
 * qu'exigerait l'emplacement 4 ne peut être posée par le JS, à qui toute
 * écriture de propriété personnalisée est interdite.
 *
 * Ordre vertical FIXE de MASTER §8.4 : nom, état, ZAPEF, consigne, fraîcheur et
 * source, lien. Il ne varie pas selon l'état — c'est ce qui rend le panneau
 * prévisible au clavier et au lecteur d'écran.
 */
?>
<aside class="carte__panneau" aria-labelledby="carte-panneau-titre" hidden>
<h2 class="carte__panneau-titre repere" id="carte-panneau-titre"></h2>
<button type="button" class="carte__panneau-fermer">Fermer le panneau</button>
<div class="carte__panneau-etat" hidden>
<p class="statut">
<span class="statut__marque pastille" aria-hidden="true"></span>
<span class="statut__libelle"></span>
</p>
</div>
<div class="carte__panneau-zapef" hidden>
<p class="statut">
<span class="statut__marque jalon" aria-hidden="true"></span>
<span class="statut__libelle"></span>
</p>
</div>
<?php if ( '' !== $zapef_note ) : ?>
<p class="carte__panneau-note-zapef" hidden><?php echo esc_html( $zapef_note ); ?></p>
<?php endif; ?>
<?php
/*
 * Les états hors niveau sont rendus par PHP, un bloc par jour et par état :
 * `carte.js` ne fait que basculer `hidden`, et c'est aussi dans ces blocs qu'il
 * lit — par `textContent` — la seconde moitié de l'`aria-label` d'un polygone.
 * Il ne rédige donc aucune phrase, il recopie une chaîne serveur.
 */
?>
<?php foreach ( $jours_rendus as $jour_demande ) : ?>
<?php foreach ( $etiquettes_hors_niveau as $etat_hors_niveau => $etiquette ) : ?>
<div class="carte__panneau-hors-niveau" data-jour="<?php echo esc_attr( $jour_demande ); ?>" data-etat="<?php echo esc_attr( $etat_hors_niveau ); ?>" hidden>
<p class="statut">
<span class="statut__marque <?php echo esc_attr( $etiquette['marque'] ); ?>" aria-hidden="true"></span>
<span class="statut__libelle"><?php echo esc_html( $etiquette['libelle'] ); ?></span>
</p>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<?php
/*
 * Emplacement de la consigne (MASTER §8.4). L'extension expose une `consigne`
 * VIDE en permanence : aucun intitulé, aucun gabarit vide, aucun tiret, aucune
 * hauteur réservée — une seule phrase factuelle.
 *
 * Elle est rendue SANS son lien (arbitrage A-6) : aucun document du projet ne
 * donne l'adresse de l'arrêté préfectoral. `carte_officielle_url` désigne la
 * CARTE, et `base_reglementaire` est celle du référentiel des PÉRIMÈTRES : les
 * substituer serait conflater deux faits. Le slot se remplira sans refonte le
 * jour où l'adresse sera fournie. Question bloquante remontée au propriétaire.
 */
?>
<p class="carte__panneau-consigne">Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en vigueur fait foi.</p>
<p class="carte__panneau-fraicheur" hidden></p>
<?php
/*
 * Deuxième verrou de l'arbitrage A-1 : le panneau affiche la DATE DE VALIDITÉ
 * du statut qu'il montre, écrite en toutes lettres et rendue par PHP pour
 * chacun des deux jours. « Statuts du {jour} » est l'amorce du gabarit de
 * fraîcheur §11.3, déjà rendue telle quelle par `liste-statuts.php` l. 184.
 *
 * La source est la MENTION D'ORIGINE de la donnée, jamais son auteur :
 * `auteur_id` n'est pas lu, et n'est jamais résolu en nom — ce serait une
 * donnée personnelle sur une page publique.
 */
?>
<p class="carte__panneau-source">
<?php foreach ( $jours_rendus as $jour_demande ) : ?>
<time data-jour="<?php echo esc_attr( $jour_demande ); ?>" datetime="<?php echo esc_attr( $jour_demande ); ?>"<?php echo $jour_demande === $jour_courant ? '' : ' hidden'; ?>>Statuts du <?php echo esc_html( $libelles_jour[ $jour_demande ] ); ?>.</time>
<?php endforeach; ?>
<?php echo esc_html( $attribution_texte ); ?>
</p>
<?php if ( '' !== esc_url( $carte_officielle ) ) : ?>
<p class="carte__panneau-lien"><a href="<?php echo esc_url( $carte_officielle ); ?>">Ouvrir la carte officielle de la préfecture</a></p>
<?php endif; ?>
</aside>
<?php
/*
 * `.carte__aide` et `.carte__annonce` sont masquées VISUELLEMENT par
 * `carte.css`, jamais par `display: none` : une région live en `display: none`
 * n'annonce rien.
 *
 * La région live ne sert qu'à ce qui ne déplace pas le focus — changement de
 * jour, activation du bouton désactivé, sélection au pointeur. Au clavier,
 * c'est l'`aria-label` du polygone focusé qui parle ; doubler l'annonce ferait
 * bégayer le lecteur d'écran.
 */
?>
<p class="carte__aide" id="carte-aide">Flèches : parcourir les massifs. Entrée : ouvrir le panneau. Échap : le fermer.</p>
<p class="carte__annonce" role="status" aria-live="polite"></p>
</div>
<?php
/*
 * Repli de la chaîne #9, rendu SANS AUCUNE CONDITION et jamais dans un
 * `<noscript>` (contrat #9, clause F-1 et invariant I-9.1). Il couvre les TROIS
 * états, et non le seul « JS désactivé » : JavaScript absent, montage réussi,
 * montage en échec. `carte.js` ne retire que `.carte-secours__repli`, et
 * seulement après un montage réussi (F-2) ; `.carte-secours__attribution`, qui
 * en est le FRÈRE et non le descendant, reste donc debout par construction
 * plutôt que par vigilance (F-3), et porte SEULE l'attribution du fond de carte
 * dans tous les états.
 *
 * Placé APRÈS `</div>` : frère de la racine `.carte`, jamais son descendant.
 * Deux propriétés en dépendent. Une fois le repli retiré, l'attribution reste
 * dans le flux SOUS la carte, ce qui satisfait I-9.6 et D-24 sans une règle CSS
 * (F-5). Et un `racine.remove()` sur un chemin d'échec de `carte.js` ne
 * l'emporte plus avec lui : la bande dégrade vers l'image statique au lieu de
 * disparaître, tout en gardant un enfant sous le `:has(*)` de `layout.css`.
 *
 * La partie appartient à la chaîne #9 et peut ne pas exister :
 * `massifs_partie()` dégrade alors en commentaire HTML, invisible.
 *
 * Couture C-1 du contrat #9, non résolue ici : ce repli vit toujours dans
 * `.bande--carte`, que `print.css` l. 98-103 masque à l'impression.
 */
?>
<?php massifs_partie( 'carte-secours' ); ?>
