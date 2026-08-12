<?php
/**
 * Partie de gabarit — les trois états vides de MASTER.md §11.3.
 *
 * Rend le bandeau d'alerte du §8.3 pour `indisponible`, `hors_saison` et
 * `non_encore_publie`, et rien du tout pour `disponible`. Aucune quatrième
 * phrase : « Aucune restriction en cours » n'est pas rendue (contrat #6, A).
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/etats-vides', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   etat         string  Défaut : massifs_synthese_du_jour( massifs_codes(),
 *                        $jour )['etat_global']. `disponible` ⇒ zéro octet.
 *   jour         string  `AAAA-MM-JJ`. Défaut : massifs_jour_courant().
 *                        Contrôle de FORME seul, jamais de calcul de date.
 *   saison       array   Défaut : massifs_saison( $jour ). Lu par la seule
 *                        branche `hors_saison`, clé `prochaine_ouverture`.
 *   attribution  array   Défaut : massifs_attribution_statuts(). Lien de la
 *                        branche `indisponible`.
 *
 * `perimee` n'est pas un état et n'est jamais lu ici : la bannière de
 * péremption est de niveau page et s'ajoute aux statuts, elle ne les masque
 * jamais.
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

// Contrôle de FORME seul : une chaîne mal formée passée au domaine lèverait une
// exception et blanchirait la page. Un contrôle de forme ne calcule aucune date.
$jour = null;

if ( isset( $arguments['jour'] ) ) {
	if ( is_string( $arguments['jour'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['jour'] ) ) {
		$jour = $arguments['jour'];
	} else {
		_doing_it_wrong( 'templates/parts/etats-vides.php', 'La clé « jour » attend une chaîne AAAA-MM-JJ.', '0.1.0' );
	}
}

if ( null === $jour && function_exists( 'massifs_jour_courant' ) ) {
	$jour = massifs_jour_courant();
}

$etat = isset( $arguments['etat'] ) && is_string( $arguments['etat'] ) ? $arguments['etat'] : '';

if ( '' === $etat && function_exists( 'massifs_synthese_du_jour' ) && function_exists( 'massifs_codes' ) ) {
	try {
		$synthese = massifs_synthese_du_jour( massifs_codes(), $jour );
		$etat     = isset( $synthese['etat_global'] ) && is_string( $synthese['etat_global'] ) ? $synthese['etat_global'] : '';
	} catch ( \InvalidArgumentException ) {
		_doing_it_wrong( 'templates/parts/etats-vides.php', 'Le domaine a refusé le jour demandé ; repli sur une absence.', '0.1.0' );
		$etat = 'indisponible';
	}
}

if ( '' === $etat ) {
	return;
}

// match() SANS default (contrat #6, arbitrage E) : l'ajout d'un cinquième état
// par le domaine doit rester bruyant. L'enveloppe empêche seulement qu'il
// devienne un écran blanc pour tous les visiteurs ; le repli est une ABSENCE,
// jamais une donnée.
try {
	$variante = match ( $etat ) {
		'disponible'        => '',
		'indisponible'      => 'indisponible',
		'hors_saison'       => 'hors-saison',
		'non_encore_publie' => 'non-publie',
	};
} catch ( \UnhandledMatchError ) {
	_doing_it_wrong( 'templates/parts/etats-vides.php', 'État inconnu du gabarit ; repli sur « indisponible ».', '0.1.0' );
	$etat     = 'indisponible';
	$variante = 'indisponible';
}

if ( 'disponible' === $etat ) {
	return;
}

$carte_officielle      = '';
$carte_officielle_liee = false;

if ( 'indisponible' === $etat ) {
	$attribution = isset( $arguments['attribution'] ) && is_array( $arguments['attribution'] )
		? $arguments['attribution']
		: array();

	if ( array() === $attribution && function_exists( 'massifs_attribution_statuts' ) ) {
		$attribution = massifs_attribution_statuts();
	}

	$carte_officielle = isset( $attribution['carte_officielle_url'] ) && is_string( $attribution['carte_officielle_url'] )
		? trim( $attribution['carte_officielle_url'] )
		: '';

	$carte_officielle_liee = '' !== esc_url( $carte_officielle );
}

$ouverture_brute = '';
$ouverture_texte = '';

if ( 'hors_saison' === $etat ) {
	$saison = isset( $arguments['saison'] ) && is_array( $arguments['saison'] ) ? $arguments['saison'] : array();

	if ( array() === $saison && function_exists( 'massifs_saison' ) ) {
		try {
			$saison = massifs_saison( $jour );
		} catch ( \InvalidArgumentException ) {
			_doing_it_wrong( 'templates/parts/etats-vides.php', 'Le domaine a refusé le jour demandé pour la saison.', '0.1.0' );
		}
	}

	$prochaine = isset( $saison['prochaine_ouverture'] ) && is_string( $saison['prochaine_ouverture'] )
		? $saison['prochaine_ouverture']
		: '';

	if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $prochaine ) && function_exists( 'massifs_horodatage' ) ) {
		try {
			// COUTURE assumée (contrat #6, arbitrage B) : massifs_horodatage()
			// exige un instant complet et refuse un jour civil nu. Midi UTC vaut
			// 13 h ou 14 h à Paris : le jour civil ne bascule jamais. Seul
			// `date_courte` est lu ; `heure` et `attr_datetime` de cet appel sont
			// interdits parce qu'ils décriraient midi, pas le jour.
			$horodatage      = massifs_horodatage( $prochaine . 'T12:00:00Z' );
			$ouverture_texte = isset( $horodatage['date_courte'] ) && is_string( $horodatage['date_courte'] )
				? $horodatage['date_courte']
				: '';
			// L'attribut `datetime` reçoit le AAAA-MM-JJ BRUT, forme valide pour un
			// jour civil, jamais une valeur reconstruite par le thème.
			$ouverture_brute = '' === $ouverture_texte ? '' : $prochaine;
		} catch ( \InvalidArgumentException ) {
			_doing_it_wrong( 'templates/parts/etats-vides.php', 'Instant refusé par le domaine ; la date de reprise est omise.', '0.1.0' );
		}
	}
}
?>
<div class="bandeau-alerte bandeau-alerte--<?php echo esc_attr( $variante ); ?> sur-sombre repere repere--bloc">
<?php if ( 'indisponible' === $etat ) : ?>
<p class="bandeau-alerte__texte">Information du jour non disponible. Consultez <?php if ( $carte_officielle_liee ) : ?><a class="bandeau-alerte__lien" href="<?php echo esc_url( $carte_officielle ); ?>">la carte officielle de la préfecture</a><?php else : ?>la carte officielle de la préfecture<?php endif; ?>.</p>
<?php endif; ?>
<?php if ( 'hors_saison' === $etat ) : ?>
<p class="bandeau-alerte__texte">Dispositif estival inactif.<?php if ( '' !== $ouverture_texte ) : ?> Reprise le <time datetime="<?php echo esc_attr( $ouverture_brute ); ?>"><?php echo esc_html( $ouverture_texte ); ?></time>.<?php endif; ?></p>
<?php endif; ?>
<?php if ( 'non_encore_publie' === $etat ) : ?>
<p class="bandeau-alerte__texte">Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.</p>
<?php endif; ?>
</div>
