<?php
/**
 * Partie de gabarit — module « Danger météo du jour » (MASTER.md §8.6).
 *
 * Rend, DANS LE HTML PRODUIT PAR PHP, l'indicateur de danger météo des forêts :
 * une échelle de carrés sans aucune couleur, le libellé officiel du cran en
 * toutes lettres, sa position sur l'échelle, et la phrase qui distingue ce
 * danger de l'accès au massif. Zéro octet de JavaScript, aucune balise externe,
 * aucun lien.
 *
 * Le module n'est PAS une information de statut : pas de repère sur son titre,
 * aucune classe des familles `statut` / `pastille` / `jalon`, aucune couleur.
 * Colorer cette échelle installerait à l'écran une gradation qui serait prise
 * pour la granularité du dispositif préfectoral, lequel n'a que deux niveaux
 * (MASTER.md §8.6, contrat #10 §4.6).
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/meteo', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   ancre         string  Défaut : `meteo`. sanitize_key(), préfixe de TOUS les
 *                         `id`. Une seconde inclusion sur la même page DOIT
 *                         recevoir une ancre distincte : la partie ne peut pas
 *                         le détecter, c'est une obligation de l'appelant.
 *   niveau_titre  int     Défaut : 2, retenu dans 2..6. Jamais 1 : le `h1`
 *                         appartient à l'appelant.
 *   jour          string  `AAAA-MM-JJ`. Défaut : null, c'est-à-dire aujourd'hui
 *                         pour le domaine. Contrôle de FORME seul, jamais de
 *                         calcul de date.
 *   meteo         array   Défaut : massifs_meteo_du_jour( $jour ). Clé UNIQUE
 *                         d'injection de recette : elle porte le retour entier,
 *                         attribution comprise. Absente ET fonction absente ⇒ la
 *                         partie rend zéro octet.
 *
 * `massifs_meteo_du_jour()` est TOTALE (contrat #10 §1) : toutes ses clés sont
 * toujours présentes, un jour malformé rend `indisponible` et ne lève pas. Le
 * gabarit n'écrit donc jamais `isset()` ni `??` sur une clé du contrat ; il
 * contrôle les TYPES, ce qui est une autre question, parce qu'une valeur du
 * mauvais type ne doit jamais être rendue comme si elle était bonne.
 *
 * La clé `jour` du retour n'est jamais lue : le module n'affiche aucune date, et
 * « aujourd'hui » comme « demain » sont des faits de domaine que le thème ne
 * produit pas.
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

$meteo_fournie = isset( $arguments['meteo'] ) && is_array( $arguments['meteo'] ) && array() !== $arguments['meteo'];

// Garde d'extension. Sans lecture possible, la partie rend zéro octet : pas de
// section, pas de titre orphelin, pas d'ancre morte.
if ( ! $meteo_fournie && ! function_exists( 'massifs_meteo_du_jour' ) ) {
	return;
}

$ancre = isset( $arguments['ancre'] ) && is_string( $arguments['ancre'] ) ? sanitize_key( $arguments['ancre'] ) : '';

if ( '' === $ancre ) {
	$ancre = 'meteo';
}

// Bornes exprimées en intervalle plutôt qu'en énumération : la cardinalité de
// l'échelle météo n'est pas établie (contrat #10, Q2) et aucun chiffre d'échelle
// ne doit pouvoir être relu dans ce fichier.
$niveau_titre = isset( $arguments['niveau_titre'] ) && is_int( $arguments['niveau_titre'] )
	&& $arguments['niveau_titre'] >= 2 && $arguments['niveau_titre'] <= 6
	? $arguments['niveau_titre']
	: 2;

$balise_titre = 'h' . (string) $niveau_titre;

// Contrôle de FORME seul. Un contrôle de forme ne calcule aucune date.
$jour = null;

if ( isset( $arguments['jour'] ) ) {
	if ( is_string( $arguments['jour'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['jour'] ) ) {
		$jour = $arguments['jour'];
	} else {
		_doing_it_wrong( 'templates/parts/meteo.php', 'La clé « jour » attend une chaîne AAAA-MM-JJ.', '0.1.0' );
	}
}

$meteo = $meteo_fournie ? $arguments['meteo'] : array();

// Garde défensive. Le contrat rend cette enveloppe théoriquement inatteignable —
// la fonction est totale et un jour malformé rend `indisponible`. Elle reste en
// ceinture et bretelles, et son repli est une ABSENCE, jamais une donnée : un
// module secondaire de bas de page n'a pas le droit de blanchir la page des
// statuts, qui en sont la raison d'être.
if ( ! $meteo_fournie ) {
	try {
		$meteo = massifs_meteo_du_jour( $jour );
	} catch ( \Throwable ) {
		_doing_it_wrong( 'templates/parts/meteo.php', 'La lecture météo a échoué ; la partie disparaît entièrement.', '0.1.0' );

		return;
	}
}

if ( ! is_array( $meteo ) || array() === $meteo ) {
	return;
}

$etat = is_string( $meteo['etat'] ) ? $meteo['etat'] : '';

// Garde de vocabulaire. match() SANS default (contrat #6, arbitrage E) :
// l'ajout d'un quatrième état par le domaine doit rester bruyant. Trois bras et
// trois seulement — `hors_saison` n'existe pas pour la météo (contrat #10, A-3) :
// affirmer au visiteur que Météo-France ne publie pas hors du dispositif
// préfectoral serait inventer un fait sur une source tierce.
try {
	$variante = match ( $etat ) {
		'disponible'        => 'disponible',
		'indisponible'      => 'indisponible',
		'non_encore_publie' => 'non_encore_publie',
	};
} catch ( \UnhandledMatchError ) {
	_doing_it_wrong( 'templates/parts/meteo.php', 'État météo inconnu du gabarit ; repli sur « indisponible ».', '0.1.0' );
	$variante = 'indisponible';
}

$distinction = is_string( $meteo['distinction'] ) ? $meteo['distinction'] : '';

$libelle_niveau    = '';
$phrase_echelle    = '';
$attribution_texte = '';
$crans             = 0;
$atteint           = 0;
$largeur           = 0;
$dessine_echelle   = false;

if ( 'disponible' === $variante ) {
	// `niveau` vaut `null` littéral hors de `disponible` : un bloc annulable rend
	// « pas de niveau » non représentable par une chaîne vide.
	$niveau = is_array( $meteo['niveau'] ) ? $meteo['niveau'] : array();

	$libelle_niveau = array() !== $niveau && is_string( $niveau['libelle'] ) ? $niveau['libelle'] : '';
}

// Garde de sens, structurante pour l'accessibilité : JAMAIS de carrés sans
// libellé. Une géométrie seule ne porte aucune information, et un dessin sans
// mot serait exactement l'échelle inventée que ce module existe pour empêcher.
if ( 'disponible' === $variante && '' === $libelle_niveau ) {
	_doing_it_wrong( 'templates/parts/meteo.php', 'Niveau disponible sans libellé ; aucune échelle dessinée, repli sur « indisponible ».', '0.1.0' );
	$variante = 'indisponible';
}

if ( 'disponible' === $variante ) {
	$echelle = is_array( $meteo['echelle'] ) ? $meteo['echelle'] : array();

	$crans          = array() !== $echelle && is_int( $echelle['crans'] ) ? $echelle['crans'] : 0;
	$atteint        = array() !== $echelle && is_int( $echelle['atteint'] ) ? $echelle['atteint'] : 0;
	$phrase_echelle = array() !== $echelle && is_string( $echelle['phrase'] ) ? $echelle['phrase'] : '';

	// Garde de cardinalité. La borne haute est un garde-fou de RENDU, jamais un
	// fait de domaine : elle n'affirme rien de l'échelle réelle et n'est jamais
	// affichée. Elle est nommée pour ne pas se lire comme le côté du carré, qui
	// vaut le même nombre trois lignes plus bas mais est une longueur en pixels.
	// Hors bornes, on refuse de dessiner plutôt que d'écrêter — une valeur fausse
	// ne devient jamais une valeur plausible — et les phrases, qui portent seules
	// le sens, demeurent.
	$crans_maximum = 12;

	if ( $crans >= 1 && $crans <= $crans_maximum && $atteint >= 0 && $atteint <= $crans ) {
		$dessine_echelle = true;

		// Carré de 12 px, gouttière de 4 px. À la borne défensive, la largeur reste
		// sous les 328 px disponibles à 360 px : aucun débordement horizontal
		// possible, prouvé par l'arithmétique et non par une requête de largeur.
		$largeur = $crans * 16 - 4;
	} else {
		_doing_it_wrong( 'templates/parts/meteo.php', 'Cardinalité d’échelle hors bornes ; aucune échelle dessinée.', '0.1.0' );
	}

	$attribution = is_array( $meteo['attribution'] ) ? $meteo['attribution'] : array();

	// Créditer une source dont aucune donnée n'est affichée est une affirmation
	// fausse : l'attribution ne voyage qu'avec la branche qui montre la donnée.
	$attribution_texte = array() !== $attribution && is_string( $attribution['texte'] ) ? $attribution['texte'] : '';
}
?>
<section id="<?php echo esc_attr( $ancre ); ?>" class="meteo" aria-labelledby="<?php echo esc_attr( $ancre . '-titre' ); ?>">
<<?php echo esc_html( $balise_titre ); ?> id="<?php echo esc_attr( $ancre . '-titre' ); ?>" class="meteo__titre">Danger météo du jour</<?php echo esc_html( $balise_titre ); ?>>
<?php if ( 'disponible' === $variante ) : ?>
<p class="meteo__echelle">
<?php if ( $dessine_echelle ) : ?>
<svg class="meteo__carres" width="<?php echo esc_attr( (string) $largeur ); ?>" height="12" viewBox="<?php echo esc_attr( '0 0 ' . (string) $largeur . ' 12' ); ?>" aria-hidden="true" focusable="false">
<?php for ( $i = 0; $i < $crans; $i++ ) : ?>
<?php if ( $i < $atteint ) : ?>
<rect class="meteo__carre meteo__carre--plein" x="<?php echo esc_attr( (string) ( $i * 16 ) ); ?>" y="0" width="12" height="12" fill="currentColor" />
<?php else : ?>
<rect class="meteo__carre meteo__carre--vide" x="<?php echo esc_attr( (string) ( $i * 16 + 1 ) ); ?>" y="1" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.5" />
<?php endif; ?>
<?php endfor; ?>
</svg>
<?php endif; ?>
<span class="meteo__libelle"><?php echo esc_html( $libelle_niveau ); ?></span>
</p>
<?php if ( '' !== $phrase_echelle ) : ?>
<p class="meteo__crans"><?php echo esc_html( $phrase_echelle ); ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ( 'indisponible' === $variante ) : ?>
<p class="meteo__indisponible">Danger météo du jour non disponible.</p>
<?php endif; ?>
<?php if ( 'non_encore_publie' === $variante ) : ?>
<p class="meteo__indisponible">Le danger météo de demain n'est pas encore publié.</p>
<?php endif; ?>
<?php if ( '' !== $distinction ) : ?>
<p class="meteo__distinction"><?php echo esc_html( $distinction ); ?></p>
<?php endif; ?>
<?php if ( 'disponible' === $variante && '' !== $attribution_texte ) : ?>
<p class="meteo__attribution"><?php echo esc_html( $attribution_texte ); ?></p>
<?php endif; ?>
</section>
