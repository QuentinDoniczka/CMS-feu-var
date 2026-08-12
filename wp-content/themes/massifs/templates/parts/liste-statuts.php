<?php
/**
 * Partie de gabarit — équivalent textuel des statuts du jour (§5.3 du brief).
 *
 * Rend, DANS LE HTML PRODUIT PAR PHP, la section « La liste du jour », son
 * ancre d'évitement, le tableau des massifs et, à défaut de donnée, l'état vide
 * correspondant. Rien ici ne dépend de JavaScript. Aucune règle métier, aucun
 * tri, aucun calcul de date : la décision « tableau ou état vide » est lue dans
 * la synthèse du domaine et jamais recalculée.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/liste-statuts', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   jour          string  `AAAA-MM-JJ`. Défaut : massifs_jour_courant().
 *                         Contrôle de FORME seul, jamais de calcul de date.
 *   ancre         string  Défaut : `liste`. sanitize_key(), préfixe de TOUS les
 *                         `id`. Une seconde inclusion sur la même page DOIT
 *                         recevoir une ancre distincte : la partie ne peut pas
 *                         le détecter, c'est une obligation de l'appelant.
 *   niveau_titre  int     Défaut : 2, retenu dans 2..6. Jamais 1 : le `h1`
 *                         appartient à l'appelant.
 *   massifs       array   Défaut : massifs_referentiel(). JAMAIS retrié.
 *   statuts       array   Défaut : massifs_statuts_du_jour( … , $jour ).
 *   synthese      array   Défaut : massifs_synthese_du_jour( … , $jour ).
 *   fraicheur     array   Défaut : massifs_fraicheur( $jour ). Alimente le
 *                         `<caption>`.
 *   attribution   array   Défaut : massifs_attribution_statuts(). Seule source
 *                         de `carte_officielle_url`, transmise à `etats-vides`.
 *   legende       array   Défaut : massifs_legende(). Lue pour `zapef_note`.
 *   note_zapef    bool    Défaut : true. Rend la note sous le tableau si au
 *                         moins une cellule ZAPEF est remplie.
 *
 * Ancre garantie à l'appelant : `<section id="{ancre}" tabindex="-1">` est le
 * premier élément émis. Le `tabindex="-1"` est obligatoire — sans lui, plusieurs
 * lecteurs d'écran déplacent le curseur virtuel mais pas le focus clavier, et la
 * tabulation suivante repart du haut de page.
 *
 * Gabarit pur, sans aucune déclaration : `load_template()` fait un `require` et
 * non un `require_once`, une partie incluse deux fois est donc ré-exécutée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arguments = isset( $args ) && is_array( $args ) ? $args : array();

$massifs_fournis = isset( $arguments['massifs'] ) && is_array( $arguments['massifs'] );
$statuts_fournis = isset( $arguments['statuts'] ) && is_array( $arguments['statuts'] );

// Garde d'extension. Sans référentiel NI statuts résolubles, la partie rend zéro
// octet : pas de section, pas de titre, pas d'ancre orpheline. L'appelant garde
// son lien d'évitement sur la même condition, sinon le lien pointerait vers un
// `id` inexistant.
if ( ( ! $massifs_fournis && ! function_exists( 'massifs_referentiel' ) )
	|| ( ! $statuts_fournis && ! function_exists( 'massifs_statuts_du_jour' ) ) ) {
	return;
}

$ancre = isset( $arguments['ancre'] ) && is_string( $arguments['ancre'] ) ? sanitize_key( $arguments['ancre'] ) : '';

if ( '' === $ancre ) {
	$ancre = 'liste';
}

$niveau_titre = isset( $arguments['niveau_titre'] ) && is_int( $arguments['niveau_titre'] )
	&& in_array( $arguments['niveau_titre'], array( 2, 3, 4, 5, 6 ), true )
	? $arguments['niveau_titre']
	: 2;

$balise_titre = 'h' . (string) $niveau_titre;

$note_zapef = isset( $arguments['note_zapef'] ) && is_bool( $arguments['note_zapef'] ) ? $arguments['note_zapef'] : true;

// Contrôle de FORME seul : une chaîne mal formée passée au domaine lèverait une
// exception et blanchirait la page. Un contrôle de forme ne calcule aucune date.
$jour = null;

if ( isset( $arguments['jour'] ) ) {
	if ( is_string( $arguments['jour'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['jour'] ) ) {
		$jour = $arguments['jour'];
	} else {
		_doing_it_wrong( 'templates/parts/liste-statuts.php', 'La clé « jour » attend une chaîne AAAA-MM-JJ.', '0.1.0' );
	}
}

if ( null === $jour && function_exists( 'massifs_jour_courant' ) ) {
	$jour = massifs_jour_courant();
}

$echec_domaine = false;
$massifs       = $massifs_fournis ? $arguments['massifs'] : array();
$statuts       = $statuts_fournis ? $arguments['statuts'] : array();
$synthese      = isset( $arguments['synthese'] ) && is_array( $arguments['synthese'] ) ? $arguments['synthese'] : array();
$fraicheur     = isset( $arguments['fraicheur'] ) && is_array( $arguments['fraicheur'] ) ? $arguments['fraicheur'] : array();
$attribution   = isset( $arguments['attribution'] ) && is_array( $arguments['attribution'] ) ? $arguments['attribution'] : array();
$legende       = isset( $arguments['legende'] ) && is_array( $arguments['legende'] ) ? $arguments['legende'] : array();

try {
	if ( ! $massifs_fournis ) {
		$massifs = massifs_referentiel();
	}

	if ( ! $statuts_fournis ) {
		$statuts = massifs_statuts_du_jour( array_keys( $massifs ), $jour );
	}

	if ( array() === $synthese && function_exists( 'massifs_synthese_du_jour' ) ) {
		$synthese = massifs_synthese_du_jour( array_keys( $massifs ), $jour );
	}

	if ( array() === $fraicheur && function_exists( 'massifs_fraicheur' ) ) {
		$fraicheur = massifs_fraicheur( $jour );
	}
} catch ( \InvalidArgumentException ) {
	_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Le domaine a refusé le jour demandé ; repli sur une absence.', '0.1.0' );
	$echec_domaine = true;
}

if ( array() === $attribution && function_exists( 'massifs_attribution_statuts' ) ) {
	$attribution = massifs_attribution_statuts();
}

if ( array() === $legende && function_exists( 'massifs_legende' ) ) {
	$legende = massifs_legende();
}

$zapef_note = isset( $legende['zapef_note'] ) && is_string( $legende['zapef_note'] ) ? $legende['zapef_note'] : '';

// Décision de niveau page : LUE dans la synthèse, jamais recalculée. Un
// référentiel vide est plus grave qu'une absence de statut — mêmes mots, aucune
// chaîne nouvelle créée pour ce cas.
$etat_page = 'indisponible';

if ( ! $echec_domaine && array() !== $massifs
	&& isset( $synthese['etat_global'] ) && is_string( $synthese['etat_global'] ) ) {
	$etat_page = $synthese['etat_global'];
}

// match() SANS default (contrat #6, arbitrage E) : l'ajout d'un cinquième état
// par le domaine doit rester bruyant. Le repli est une ABSENCE, jamais une donnée.
try {
	$rend_tableau = match ( $etat_page ) {
		'disponible'        => true,
		'indisponible'      => false,
		'hors_saison'       => false,
		'non_encore_publie' => false,
	};
} catch ( \UnhandledMatchError ) {
	_doing_it_wrong( 'templates/parts/liste-statuts.php', 'État de synthèse inconnu ; repli sur « indisponible ».', '0.1.0' );
	$etat_page    = 'indisponible';
	$rend_tableau = false;
}

// `partiel` ne pose qu'un crochet de style : aucune phrase supplémentaire, et
// aucune ligne réellement publiée n'est masquée.
$partielle = isset( $synthese['partiel'] ) && true === $synthese['partiel'];

// Le `<caption>` porte le JOUR DE VALIDITÉ, exigé sur la feuille imprimée, et la
// seule clause de fraîcheur adossée à une donnée. La clause « publiés la veille
// à … par la préfecture » de MASTER §11.3 n'est PAS rendue ici (contrat #6,
// arbitrage H) : « la veille » est une affirmation métier que le thème ne peut
// pas produire, et l'heure de publication est une chaîne d'horloge que
// massifs_horodatage() refuse. La phrase complète appartient à l'ardoise.
$resume = '';

if ( $rend_tableau && null !== $jour && function_exists( 'massifs_horodatage' ) ) {
	try {
		// COUTURE assumée (contrat #6, arbitrage B) : massifs_horodatage() exige un
		// instant complet et refuse un jour civil nu. Midi UTC vaut 13 h ou 14 h à
		// Paris : le jour civil ne bascule jamais. Seul `date_longue` est lu ;
		// `heure` et `attr_datetime` de cet appel sont interdits.
		$horodatage_jour = massifs_horodatage( $jour . 'T12:00:00Z' );

		if ( isset( $horodatage_jour['date_longue'] ) && is_string( $horodatage_jour['date_longue'] ) ) {
			$resume = 'Statuts du ' . $horodatage_jour['date_longue'];
		}
	} catch ( \InvalidArgumentException ) {
		_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Jour de validité refusé par le domaine ; résumé omis.', '0.1.0' );
	}
}

if ( '' !== $resume ) {
	$releve = isset( $fraicheur['dernier_releve_le'] ) && is_string( $fraicheur['dernier_releve_le'] )
		? $fraicheur['dernier_releve_le']
		: '';

	if ( '' !== $releve && function_exists( 'massifs_horodatage' ) ) {
		try {
			$horodatage_releve = massifs_horodatage( $releve );

			if ( isset( $horodatage_releve['date_courte'], $horodatage_releve['heure'] )
				&& is_string( $horodatage_releve['date_courte'] ) && is_string( $horodatage_releve['heure'] ) ) {
				$resume .= ' — relevés sur ce site le ' . $horodatage_releve['date_courte'] . ' à ' . $horodatage_releve['heure'];
			}
		} catch ( \InvalidArgumentException ) {
			_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Instant de relevé refusé par le domaine ; clause omise.', '0.1.0' );
		}
	}

	$resume .= '.';
}

$zapef_rendue = false;
?>
<section id="<?php echo esc_attr( $ancre ); ?>" class="liste-statuts<?php echo $partielle ? ' liste-statuts--partielle' : ''; ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr( $ancre . '-titre' ); ?>">
<<?php echo esc_html( $balise_titre ); ?> id="<?php echo esc_attr( $ancre . '-titre' ); ?>" class="liste-statuts__titre repere">La liste du jour</<?php echo esc_html( $balise_titre ); ?>>
<?php if ( $rend_tableau ) : ?>
<table role="table" class="liste-statuts__tableau"<?php if ( '' !== $resume ) : ?> aria-describedby="<?php echo esc_attr( $ancre . '-resume' ); ?>"<?php endif; ?>>
<?php if ( '' !== $resume ) : ?>
<caption id="<?php echo esc_attr( $ancre . '-resume' ); ?>" class="liste-statuts__resume"><?php echo esc_html( $resume ); ?></caption>
<?php endif; ?>
<thead role="rowgroup">
<tr role="row" class="liste-statuts__ligne liste-statuts__ligne--entete">
<th scope="col" role="columnheader" class="liste-statuts__entete">Massif</th>
<th scope="col" role="columnheader" class="liste-statuts__entete">Niveau d'Accès</th>
<th scope="col" role="columnheader" class="liste-statuts__entete">ZAPEF</th>
<th scope="col" role="columnheader" class="liste-statuts__entete">Fraîcheur</th>
</tr>
</thead>
<tbody role="rowgroup">
<?php foreach ( $massifs as $code_massif => $massif ) : ?>
<?php
if ( ! is_array( $massif ) ) {
	continue;
}

// `libelle` est le seul champ affichable d'un massif ; `source.nom_massif` ne
// l'est jamais. Une ligne sans nom n'a rien à dire : elle est omise, bruyamment.
$libelle_massif = isset( $massif['libelle'] ) && is_string( $massif['libelle'] ) ? trim( $massif['libelle'] ) : '';

if ( '' === $libelle_massif ) {
	_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Massif sans libellé ; ligne omise.', '0.1.0' );
	continue;
}

$entree     = isset( $statuts[ $code_massif ] ) && is_array( $statuts[ $code_massif ] ) ? $statuts[ $code_massif ] : array();
$etat_ligne = isset( $entree['etat'] ) && is_string( $entree['etat'] ) ? $entree['etat'] : 'indisponible';

// Étiquettes courtes VERBATIM de MASTER §8.5 pour les deux premiers états. Le
// troisième n'a pas d'étiquette courte publiée : en fabriquer une serait une
// invention, la phrase entière du §11.3 est donc rendue telle quelle.
try {
	$hors_niveau = match ( $etat_ligne ) {
		'disponible'        => array(),
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
			'libelle' => 'Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.',
		),
	};
} catch ( \UnhandledMatchError ) {
	_doing_it_wrong( 'templates/parts/liste-statuts.php', 'État de statut inconnu ; repli sur « indisponible ».', '0.1.0' );
	$hors_niveau = array(
		'marque'  => 'pastille pastille--indisponible',
		'libelle' => 'information non disponible',
	);
}

$ligne_disponible = array() === $hors_niveau;

$niveau_marque      = '';
$niveau_libelle     = '';
$zapef_marque       = '';
$zapef_libelle      = '';
$fraicheur_datetime = '';
$fraicheur_texte    = '';

// `niveau` et `zapef` ne sont lus QUE dans la branche disponible : un niveau
// n'est jamais affiché quand l'état n'est pas `disponible`.
if ( $ligne_disponible ) {
	$niveau = isset( $entree['niveau'] ) && is_array( $entree['niveau'] ) ? $entree['niveau'] : array();

	if ( array() !== $niveau ) {
		$niveau_libelle = isset( $niveau['libelle'] ) && is_string( $niveau['libelle'] ) ? $niveau['libelle'] : '';

		// Table FERMÉE : aucune classe n'est dérivée de `jeton_css` ni calculée.
		// Une clé inconnue ne produit AUCUN aplat et AUCUN motif — l'échec est
		// bruyant, jamais une teinte fausse.
		try {
			$niveau_marque = match ( isset( $niveau['cle'] ) && is_string( $niveau['cle'] ) ? $niveau['cle'] : '' ) {
				'autorise' => 'pastille pastille--autorise',
				'interdit' => 'pastille pastille--interdit',
			};
		} catch ( \UnhandledMatchError ) {
			_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Clé de niveau inconnue ; aucune marque colorée rendue.', '0.1.0' );
		}
	}

	// La colonne ZAPEF existe pour TOUS les massifs, pilotée uniquement par la
	// présence de la donnée. Sans donnée, la cellule reste STRICTEMENT vide :
	// aucun tiret, aucune mention d'absence.
	$zapef = isset( $entree['zapef'] ) && is_array( $entree['zapef'] ) ? $entree['zapef'] : array();

	if ( array() !== $zapef ) {
		$zapef_libelle = isset( $zapef['libelle'] ) && is_string( $zapef['libelle'] ) ? $zapef['libelle'] : '';

		try {
			$zapef_marque = match ( isset( $zapef['cle'] ) && is_string( $zapef['cle'] ) ? $zapef['cle'] : '' ) {
				'autorise' => 'jalon jalon--autorise',
				'interdit' => 'jalon jalon--interdit',
			};
		} catch ( \UnhandledMatchError ) {
			_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Clé ZAPEF inconnue ; aucune marque colorée rendue.', '0.1.0' );
		}
	}

	if ( '' !== $zapef_libelle ) {
		$zapef_rendue = true;
	}

	// `enregistre_le` est le seul instant garanti non nul quand l'état est
	// `disponible` : la colonne dit donc TOUJOURS la même chose — quand ce site a
	// relevé cette donnée. L'instant de publication préfectorale relève de
	// l'ardoise, pas d'une colonne à sémantique variable.
	$enregistre = isset( $entree['enregistre_le'] ) && is_string( $entree['enregistre_le'] ) ? $entree['enregistre_le'] : '';

	if ( '' !== $enregistre && function_exists( 'massifs_horodatage' ) ) {
		try {
			$horodatage_ligne = massifs_horodatage( $enregistre );

			if ( isset( $horodatage_ligne['attr_datetime'], $horodatage_ligne['date_courte'], $horodatage_ligne['heure'] )
				&& is_string( $horodatage_ligne['attr_datetime'] )
				&& is_string( $horodatage_ligne['date_courte'] )
				&& is_string( $horodatage_ligne['heure'] ) ) {
				$fraicheur_datetime = $horodatage_ligne['attr_datetime'];
				$fraicheur_texte    = 'Relevé le ' . $horodatage_ligne['date_courte'] . ' à ' . $horodatage_ligne['heure'];
			}
		} catch ( \InvalidArgumentException ) {
			_doing_it_wrong( 'templates/parts/liste-statuts.php', 'Instant de relevé refusé par le domaine ; cellule vide.', '0.1.0' );
		}
	}
}
?>
<?php if ( $ligne_disponible ) : ?>
<tr role="row" class="liste-statuts__ligne">
<th scope="row" role="rowheader" class="liste-statuts__massif" data-etiquette="Massif"><?php echo esc_html( $libelle_massif ); ?></th>
<td role="cell" class="liste-statuts__cellule liste-statuts__cellule--niveau" data-etiquette="Niveau d'Accès"><?php
$statut_classe_marque = $niveau_marque;
$statut_libelle       = $niveau_libelle;
?>
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</td>
<td role="cell" class="liste-statuts__cellule liste-statuts__cellule--zapef" data-etiquette="ZAPEF"><?php
$statut_classe_marque = $zapef_marque;
$statut_libelle       = $zapef_libelle;
?>
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</td>
<td role="cell" class="liste-statuts__cellule liste-statuts__cellule--fraicheur" data-etiquette="Fraîcheur"><?php if ( '' !== $fraicheur_texte ) : ?><time class="liste-statuts__fraicheur" datetime="<?php echo esc_attr( $fraicheur_datetime ); ?>"><?php echo esc_html( $fraicheur_texte ); ?></time><?php endif; ?></td>
</tr>
<?php endif; ?>
<?php if ( ! $ligne_disponible ) : ?>
<tr role="row" class="liste-statuts__ligne liste-statuts__ligne--hors-niveau">
<th scope="row" role="rowheader" class="liste-statuts__massif" data-etiquette="Massif"><?php echo esc_html( $libelle_massif ); ?></th>
<td role="cell" colspan="3" aria-colspan="3" class="liste-statuts__cellule liste-statuts__cellule--hors-niveau"><?php
$statut_classe_marque = $hors_niveau['marque'];
$statut_libelle       = $hors_niveau['libelle'];
?>
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</tbody>
</table>
<?php if ( $note_zapef && $zapef_rendue && '' !== $zapef_note ) : ?>
<p class="liste-statuts__note"><?php echo esc_html( $zapef_note ); ?></p>
<?php endif; ?>
<?php endif; ?>
<?php
if ( ! $rend_tableau ) {
	$arguments_etats_vides = array(
		'etat'        => $etat_page,
		'attribution' => $attribution,
	);

	if ( null !== $jour ) {
		$arguments_etats_vides['jour'] = $jour;
	}

	get_template_part( 'templates/parts/etats-vides', null, $arguments_etats_vides );
}
?>
</section>
