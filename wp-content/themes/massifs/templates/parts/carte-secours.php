<?php
/**
 * Partie de gabarit — repli sans JavaScript de la carte (§5.5 du brief).
 *
 * Rend, DANS LE HTML PRODUIT PAR PHP, l'image statique du département, le
 * chemin d'accès vers l'équivalent textuel des statuts, puis l'attribution de
 * la source du fond de carte. Rendue PAR DÉFAUT et jamais dans un <noscript>
 * (contrat #9, invariant I-9.1) : la chaîne carte retire elle-même le seul
 * nœud `.carte-secours__repli` après un montage réussi, ce qui laisse
 * l'attribution debout par construction plutôt que par vigilance.
 *
 * L'image ne porte JAMAIS les statuts du jour — elle porte le fond et les
 * contours des massifs. Elle dit OÙ ; la liste textuelle dit QUOI. Aucune
 * fonction de statut, de fraîcheur, de synthèse ni de jour courant n'est
 * appelée ici (invariant I-9.5) : la partie est structurellement incapable de
 * présenter un statut périmé comme courant.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/carte-secours', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   carte        array   Défaut : massifs_fond_de_carte_statique(). Fournit
 *                        `disponible`, `largeur` et `hauteur`. Il n'existe
 *                        délibérément aucune clé `url` : l'extension publie
 *                        des FAITS sur l'artefact, et le thème résout son
 *                        propre chemin d'asset (contrat #9, arbitrage A-3).
 *                        Absente ET fonction absente ⇒ zéro octet.
 *   attribution  array   Défaut : massifs_attribution_fond_de_carte(). Fournit
 *                        `phrase` et `lien_licence`. Absente, ou `phrase` vide
 *                        après trim() ⇒ zéro octet, IMAGE COMPRISE
 *                        (invariant I-9.4).
 *   ancre        string  Défaut : `liste`. sanitize_key(), cible du lien
 *                        visible. La passer ne dispense PAS de la garde
 *                        d'existence : un appelant ne peut pas prouver qu'une
 *                        ancre est rendue.
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

$attribution = isset( $arguments['attribution'] ) && is_array( $arguments['attribution'] )
	? $arguments['attribution']
	: array();

if ( array() === $attribution && function_exists( 'massifs_attribution_fond_de_carte' ) ) {
	$attribution = massifs_attribution_fond_de_carte();
}

$phrase = isset( $attribution['phrase'] ) && is_string( $attribution['phrase'] ) ? trim( $attribution['phrase'] ) : '';

// Garde d'attribution, évaluée AVANT la garde d'image (invariant I-9.4) :
// afficher un rendu ODbL SANS attribution est une violation de licence, et
// créditer une source dont AUCUNE donnée n'est affichée est une affirmation
// fausse (templates/footer.php l. 13-15). L'image et sa mention n'existent que
// l'une avec l'autre ; l'ordre des deux gardes est ce qui le garantit.
if ( '' === $phrase ) {
	return;
}

$carte = isset( $arguments['carte'] ) && is_array( $arguments['carte'] ) ? $arguments['carte'] : array();

if ( array() === $carte && function_exists( 'massifs_fond_de_carte_statique' ) ) {
	$carte = massifs_fond_de_carte_statique();
}

$largeur = isset( $carte['largeur'] ) && is_int( $carte['largeur'] ) ? $carte['largeur'] : 0;
$hauteur = isset( $carte['hauteur'] ) && is_int( $carte['hauteur'] ) ? $carte['hauteur'] : 0;

// Garde d'image. `disponible` est la SEULE autorité sur l'artefact : le thème
// ne stat jamais le fichier et ne recalcule aucune dimension (contrat #9,
// interdits 7 et 8). Les deux dimensions sont rendues telles quelles — elles
// réservent la boîte de l'image et suppriment le saut de mise en page.
// Métadonnées absentes ⇒ zéro octet : jamais d'<img> cassée, jamais d'`alt` de
// substitution, jamais de texte de repli.
if ( ! isset( $carte['disponible'] ) || true !== $carte['disponible'] || $largeur <= 0 || $hauteur <= 0 ) {
	return;
}

// Nom de fichier gelé par le contrat #9 §2. L'artefact vit dans le thème,
// c'est donc le thème qui résout son chemin, avec la mécanique d'asset déjà en
// service pour les feuilles de style et les polices (functions.php).
$chemin_image = 'assets/img/carte-statique.png';

$image = add_query_arg(
	'ver',
	massifs_version_asset( $chemin_image ),
	get_theme_file_uri( $chemin_image )
);

// Même idiome que le lien de licence ci-dessous : on ne rend une adresse que
// si elle survit à l'échappement, plutôt qu'un `src` vide.
if ( '' === esc_url( $image ) ) {
	return;
}

$ancre = isset( $arguments['ancre'] ) && is_string( $arguments['ancre'] ) ? sanitize_key( $arguments['ancre'] ) : '';

if ( '' === $ancre ) {
	$ancre = 'liste';
}

// Garde d'ancre REJOUÉE À L'IDENTIQUE de templates/header.php l. 41-44,
// `is_front_page()` compris : ce lien visible et le lien d'évitement du header
// visent la même ancre, et un lien vers une ancre absente est pire que son
// absence. Le `tabindex="-1"` de la cible est garanti par
// templates/parts/liste-statuts.php l. 214.
//
// COUTURE C-3 du contrat #9 : cette condition existe en TROIS exemplaires
// (header.php, la garde d'extension de liste-statuts.php, et ici) jusqu'à ce
// qu'un helper la factorise. Toute évolution de l'une doit être répercutée sur
// les deux autres.
$ancre_rendue = is_front_page()
	&& function_exists( 'massifs_referentiel' )
	&& function_exists( 'massifs_statuts_du_jour' )
	&& '' !== locate_template( 'templates/parts/liste-statuts.php' );

$lien_licence = isset( $attribution['lien_licence'] ) && is_string( $attribution['lien_licence'] )
	? trim( $attribution['lien_licence'] )
	: '';

// esc_url() vide toute adresse de protocole non autorisé : on ne rend le lien
// que s'il survit à l'échappement, plutôt que d'écrire un lien mort — idiome
// déjà en service dans templates/parts/bandeau-non-officialite.php l. 50-52.
// À défaut, la phrase est rendue en texte nu ; elle n'est jamais omise.
$licence_liee = '' !== esc_url( $lien_licence );

// `block-size:auto` en ligne, seul style en ligne du thème, enregistré au §11
// du contrat #9 et au §17 de MASTER.md : layout.css l. 56-59 pose
// `max-inline-size: 100%` SANS `block-size: auto`, et une <img> portant
// width/height serait donc écrasée verticalement dès que sa largeur est
// contrainte. La déclaration en ligne a la propriété que cette issue sert :
// elle fonctionne sans aucune feuille de style.
//
// L'ordre des nœuds est délibéré : l'image, puis le chemin d'accès à
// l'information, puis la mention légale. Le lien est le nœud focusable qui
// suit immédiatement l'image ; l'attribution, qui est du chrome, ne s'interpose
// jamais entre le plan et son équivalent textuel.
?>
<div class="carte-secours">
<div class="carte-secours__repli">
<img class="carte-secours__image" src="<?php echo esc_url( $image ); ?>" width="<?php echo esc_attr( (string) $largeur ); ?>" height="<?php echo esc_attr( (string) $hauteur ); ?>" style="block-size:auto" alt="" decoding="async">
<?php if ( $ancre_rendue ) : ?>
<?php // alt="" sur l'image : le nom accessible est porté par ce lien visible frère, jamais par une description inventée hors de la liste fermée du §11.3. ?>
<p class="carte-secours__acces"><a class="carte-secours__lien" href="<?php echo esc_url( '#' . $ancre ); ?>">Aller à la liste des statuts</a></p>
<?php endif; ?>
</div>
<p class="carte-secours__attribution"><?php if ( $licence_liee ) : ?><a class="carte-secours__attribution-lien" href="<?php echo esc_url( $lien_licence ); ?>"><?php echo esc_html( $phrase ); ?></a><?php else : ?><?php echo esc_html( $phrase ); ?><?php endif; ?></p>
</div>
