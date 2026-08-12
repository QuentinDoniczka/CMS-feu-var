<?php
/**
 * Partie de gabarit — bloc de légende officielle (MASTER.md §8.5).
 *
 * Reproduit la légende publiée — quatre entrées et une note — puis, sur une
 * seconde ligne séparée et introduite par « Sur ce site », les états hors
 * niveau qui sont les nôtres. Cette séparation empêche d'attribuer à la
 * préfecture un état que nous avons rédigé. Jamais masquée derrière un bouton,
 * un accordéon ou un survol : visible en permanence, sans JavaScript.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/legende', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   ancre              string  Défaut : `legende`. sanitize_key(), préfixe de
 *                              TOUS les `id` de la partie. Une seconde
 *                              inclusion sur la même page DOIT recevoir une
 *                              ancre distincte : la partie ne peut pas le
 *                              détecter, c'est une obligation de l'appelant.
 *   niveau_titre       int     Défaut : 2, retenu dans 2..6. Jamais 1 : le
 *                              `h1` appartient à l'appelant.
 *   legende            array   Défaut : massifs_legende(). Absente ET fonction
 *                              absente ⇒ la partie rend zéro octet.
 *   etats_sur_ce_site  array   Défaut : indisponible, hors_saison. Clés lues
 *                              dans legende['etats_hors_niveau'] ; une clé
 *                              absente de ce tableau est ignorée en silence.
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

$ancre = isset( $arguments['ancre'] ) && is_string( $arguments['ancre'] ) ? sanitize_key( $arguments['ancre'] ) : '';

if ( '' === $ancre ) {
	$ancre = 'legende';
}

$niveau_titre = isset( $arguments['niveau_titre'] ) && is_int( $arguments['niveau_titre'] )
	&& in_array( $arguments['niveau_titre'], array( 2, 3, 4, 5, 6 ), true )
	? $arguments['niveau_titre']
	: 2;

$balise_titre = 'h' . (string) $niveau_titre;

$legende = isset( $arguments['legende'] ) && is_array( $arguments['legende'] ) ? $arguments['legende'] : array();

if ( array() === $legende && function_exists( 'massifs_legende' ) ) {
	$legende = massifs_legende();
}

// Sans légende, pas de titre orphelin : la partie disparaît entièrement.
if ( array() === $legende ) {
	return;
}

$niveaux = isset( $legende['niveaux'] ) && is_array( $legende['niveaux'] ) ? $legende['niveaux'] : array();
$zapef   = isset( $legende['zapef'] ) && is_array( $legende['zapef'] ) ? $legende['zapef'] : array();

if ( array() === $niveaux && array() === $zapef ) {
	return;
}

$zapef_note = isset( $legende['zapef_note'] ) && is_string( $legende['zapef_note'] ) ? $legende['zapef_note'] : '';

$etats_hors_niveau = isset( $legende['etats_hors_niveau'] ) && is_array( $legende['etats_hors_niveau'] )
	? $legende['etats_hors_niveau']
	: array();

$etats_demandes = isset( $arguments['etats_sur_ce_site'] ) && is_array( $arguments['etats_sur_ce_site'] )
	? $arguments['etats_sur_ce_site']
	: array( 'indisponible', 'hors_saison' );

// Ordre imposé par MASTER §8.5 — massif autorisé, massif interdit, ZAPEF
// autorisé, ZAPEF interdite — obtenu SANS aucun tri du thème : les deux listes
// arrivent ordonnées par sévérité croissante.
$entrees_massif = array();

foreach ( $niveaux as $entree_source ) {
	if ( ! is_array( $entree_source ) ) {
		continue;
	}

	$libelle = isset( $entree_source['libelle'] ) && is_string( $entree_source['libelle'] ) ? $entree_source['libelle'] : '';

	if ( '' === $libelle ) {
		continue;
	}

	$marque = '';

	// Table FERMÉE : aucune classe n'est dérivée de `jeton_css` ni calculée. Une
	// clé inconnue ne produit AUCUN aplat et AUCUN motif — l'échec est bruyant,
	// jamais une teinte fausse (MASTER §4.1.d règle 7).
	try {
		$marque = match ( isset( $entree_source['cle'] ) && is_string( $entree_source['cle'] ) ? $entree_source['cle'] : '' ) {
			'autorise' => 'pastille pastille--autorise',
			'interdit' => 'pastille pastille--interdit',
		};
	} catch ( \UnhandledMatchError ) {
		_doing_it_wrong( 'templates/parts/legende.php', 'Clé de niveau inconnue ; aucune marque colorée rendue.', '0.1.0' );
	}

	$entrees_massif[] = array(
		'marque'  => $marque,
		'libelle' => $libelle,
	);
}

$entrees_zapef = array();

foreach ( $zapef as $entree_source ) {
	if ( ! is_array( $entree_source ) ) {
		continue;
	}

	$libelle = isset( $entree_source['libelle'] ) && is_string( $entree_source['libelle'] ) ? $entree_source['libelle'] : '';

	if ( '' === $libelle ) {
		continue;
	}

	$marque = '';

	try {
		$marque = match ( isset( $entree_source['cle'] ) && is_string( $entree_source['cle'] ) ? $entree_source['cle'] : '' ) {
			'autorise' => 'jalon jalon--autorise',
			'interdit' => 'jalon jalon--interdit',
		};
	} catch ( \UnhandledMatchError ) {
		_doing_it_wrong( 'templates/parts/legende.php', 'Clé ZAPEF inconnue ; aucune marque colorée rendue.', '0.1.0' );
	}

	$entrees_zapef[] = array(
		'marque'  => $marque,
		'libelle' => $libelle,
	);
}

// Étiquettes courtes VERBATIM de MASTER §8.5 pour les deux premiers états. Le
// troisième n'a pas d'étiquette courte publiée : en fabriquer une serait une
// invention, la phrase entière du §11.3 est donc rendue telle quelle.
$entrees_sur_ce_site = array();

foreach ( $etats_demandes as $etat_demande ) {
	if ( ! is_string( $etat_demande ) || ! isset( $etats_hors_niveau[ $etat_demande ] ) ) {
		continue;
	}

	try {
		$entrees_sur_ce_site[] = match ( $etat_demande ) {
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
		_doing_it_wrong( 'templates/parts/legende.php', 'État hors niveau inconnu ; entrée de légende omise.', '0.1.0' );
	}
}

$legende_a_verifier = function_exists( 'massifs_legende_est_confirmee' ) && ! massifs_legende_est_confirmee();
?>
<section id="<?php echo esc_attr( $ancre ); ?>" class="legende" aria-labelledby="<?php echo esc_attr( $ancre . '-titre' ); ?>">
<<?php echo esc_html( $balise_titre ); ?> id="<?php echo esc_attr( $ancre . '-titre' ); ?>" class="legende__titre repere">Légende de la carte</<?php echo esc_html( $balise_titre ); ?>>
<?php if ( array() !== $entrees_massif ) : ?>
<ul class="legende__entrees legende__entrees--massif">
<?php foreach ( $entrees_massif as $entree_rendue ) : ?>
<?php
$statut_classe_marque = $entree_rendue['marque'];
$statut_libelle       = $entree_rendue['libelle'];
?>
<li class="legende__entree">
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ( array() !== $entrees_zapef ) : ?>
<ul class="legende__entrees legende__entrees--zapef">
<?php foreach ( $entrees_zapef as $entree_rendue ) : ?>
<?php
$statut_classe_marque = $entree_rendue['marque'];
$statut_libelle       = $entree_rendue['libelle'];
?>
<li class="legende__entree">
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php if ( '' !== $zapef_note ) : ?>
<p class="legende__note"><?php echo esc_html( $zapef_note ); ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ( array() !== $entrees_sur_ce_site ) : ?>
<div class="legende__hors-niveau">
<p class="legende__etiquette" id="<?php echo esc_attr( $ancre . '-sur-ce-site' ); ?>">Sur ce site</p>
<ul class="legende__entrees legende__entrees--hors-niveau" aria-labelledby="<?php echo esc_attr( $ancre . '-sur-ce-site' ); ?>">
<?php foreach ( $entrees_sur_ce_site as $entree_rendue ) : ?>
<?php
$statut_classe_marque = $entree_rendue['marque'];
$statut_libelle       = $entree_rendue['libelle'];
?>
<li class="legende__entree">
<?php if ( '' !== $statut_libelle ) : ?>
<span class="statut">
<?php if ( '' !== $statut_classe_marque ) : ?>
<span class="statut__marque <?php echo esc_attr( $statut_classe_marque ); ?>" aria-hidden="true"></span>
<?php endif; ?>
<span class="statut__libelle"><?php echo esc_html( $statut_libelle ); ?></span>
</span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php if ( $legende_a_verifier ) : ?>
<p class="legende__avertissement">Légende en cours de vérification</p>
<?php endif; ?>
</section>
