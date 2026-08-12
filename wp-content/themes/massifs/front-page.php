<?php
/**
 * Gabarit de la page d'accueil — bandes dans l'ordre normatif de MASTER.md §7.1.
 *
 * Tout est rendu par PHP : la page est identique avec et sans JavaScript, et
 * cette issue n'enfile aucun script.
 *
 * Ce gabarit ne calcule aucune règle métier. Le jour, la saison, la péremption,
 * la sévérité et le formatage des dates appartiennent à l'extension.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'existence de l'API — un seul point. Elle porte sur six fonctions
// parce qu'elles proviennent de trois modules de domaine distincts, qui peuvent
// échouer à charger indépendamment les uns des autres.
$massifs_api = function_exists( 'massifs_codes' )
	&& function_exists( 'massifs_jour_courant' )
	&& function_exists( 'massifs_synthese_du_jour' )
	&& function_exists( 'massifs_fraicheur' )
	&& function_exists( 'massifs_horodatage' )
	&& function_exists( 'massifs_attribution_statuts' );

$massifs_peremption = false;

if ( $massifs_api ) {
	// Aucun jour n'est passé : `null` laisse le serveur décider du jour civil
	// de Paris. Le thème ne calcule jamais « aujourd'hui ».
	$massifs_synthese   = massifs_synthese_du_jour( massifs_codes(), null );
	$massifs_fraicheur  = massifs_fraicheur( null );
	$massifs_peremption = true === $massifs_fraicheur['perimee'];

	// `match` SANS bras `default`, imposé par le contrat #3 : un cinquième état
	// lèvera UnhandledMatchError plutôt que de produire un rendu silencieusement
	// faux. Sur une donnée de sécurité, l'échec bruyant est le bon comportement.
	// `non_encore_publie` est inatteignable pour aujourd'hui ; le bras est écrit
	// parce que `match` sans `default` l'exige et parce qu'un sélecteur de date
	// le rendra atteignable.
	$massifs_ardoise = match ( $massifs_synthese['etat_global'] ) {
		// LE CHIFFRE N'EST ÉCRIT QUE DANS CE BRAS. La règle « jamais un statut
		// périmé présenté comme courant » est ainsi tenue par la structure, pas
		// par la vigilance. Accès direct aux clés du contrat, sans isset() ni ?? :
		// une clé absente doit produire un avertissement PHP visible.
		'disponible'        => array(
			'chiffre'       => (string) $massifs_synthese['par_niveau']['autorise'],
			'chiffre_total' => '/' . $massifs_synthese['total'],
			'titre_debut'   => sprintf(
				'Aujourd’hui, %1$s massifs sur %2$s sont d’accès autorisé.',
				$massifs_synthese['par_niveau']['autorise'],
				$massifs_synthese['total']
			),
			'titre_url'     => '',
			'titre_lien'    => '',
			'titre_fin'     => '',
			'fraicheur'     => true,
			'journal'       => '',
		),
		// Le mot « INDISPONIBLE » de MASTER.md §8.2 n'est pas rendu : il serait
		// un second bloc --fs-700 adjacent au h1, qui dit déjà exactement cela.
		// Les trois fragments ci-dessous, concaténés, sont la chaîne §11.3 mot
		// pour mot ; ils ne sont séparés que pour porter le lien.
		'indisponible'      => array(
			'chiffre'       => '',
			'chiffre_total' => '',
			'titre_debut'   => 'Information du jour non disponible. Consultez',
			'titre_url'     => massifs_attribution_statuts()['carte_officielle_url'],
			'titre_lien'    => 'la carte officielle de la préfecture',
			'titre_fin'     => '.',
			'fraicheur'     => false,
			'journal'       => '',
		),
		// Phrase §11.3 tronquée à sa première proposition : « Reprise le {date}. »
		// exigerait de formater une date nue, ce que massifs_horodatage() refuse.
		// La proposition est omise et journalisée, jamais inventée (demande B-1).
		'hors_saison'       => array(
			'chiffre'       => '',
			'chiffre_total' => '',
			'titre_debut'   => 'Dispositif estival inactif.',
			'titre_url'     => '',
			'titre_lien'    => '',
			'titre_fin'     => '',
			'fraicheur'     => false,
			'journal'       => 'massifs: état hors_saison — proposition « Reprise le {date}. » omise, faute de massifs_horodatage_jour() (demande B-1).',
		),
		'non_encore_publie' => array(
			'chiffre'       => '',
			'chiffre_total' => '',
			'titre_debut'   => 'Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.',
			'titre_url'     => '',
			'titre_lien'    => '',
			'titre_fin'     => '',
			'fraicheur'     => false,
			'journal'       => '',
		),
	};
} else {
	// API absente : la branche indisponible, SANS le lien — l'adresse vient du
	// serveur, qui est absent. Aucune copie inventée, le h1 unique est conservé,
	// et l'affirmation est vraie : nous n'avons pas l'information.
	$massifs_ardoise = array(
		'chiffre'       => '',
		'chiffre_total' => '',
		'titre_debut'   => 'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
		'titre_url'     => '',
		'titre_lien'    => '',
		'titre_fin'     => '',
		'fraicheur'     => false,
		'journal'       => 'massifs: API de lecture de massifs-core absente — ardoise rendue en état indisponible.',
	);
}

// Journal de recette seulement : massifs_journaliser() ignore un message vide
// et n'écrit rien hors WP_DEBUG.
massifs_journaliser( $massifs_ardoise['journal'] );

get_template_part( 'templates/header' );
?>

		<section id="ardoise" class="bande bande--ardoise sur-sombre">
			<div class="bande__contenu ardoise">
				<?php if ( '' !== $massifs_ardoise['chiffre'] ) : ?>
					<p class="ardoise__chiffre repere repere--bloc">
						<span class="ardoise__chiffre-valeur"><?php echo esc_html( $massifs_ardoise['chiffre'] ); ?></span>
						<span class="ardoise__chiffre-total"><?php echo esc_html( $massifs_ardoise['chiffre_total'] ); ?></span>
					</p>
				<?php endif; ?>

				<div class="ardoise__texte">
					<h1 id="titre-du-jour" class="ardoise__titre">
						<?php
						echo esc_html( $massifs_ardoise['titre_debut'] );
	
						if ( '' !== $massifs_ardoise['titre_url'] ) {
							printf(
								' <a href="%1$s">%2$s</a>%3$s',
								esc_url( $massifs_ardoise['titre_url'] ),
								esc_html( $massifs_ardoise['titre_lien'] ),
								esc_html( $massifs_ardoise['titre_fin'] )
							);
						}
						?>
					</h1>
	
					<?php
					if ( $massifs_ardoise['fraicheur'] ) {
						// Gabarit de MASTER.md §11.3. Les variantes s'obtiennent
						// UNIQUEMENT en supprimant la proposition dont la valeur
						// serveur est nulle : aucun mot n'est réécrit.
						$massifs_propositions = array();
	
						// Passerelle bornée : massifs_horodatage() exige un instant et
						// refuse une date nue, or « Statuts du {jour de validité} »
						// porte un jour civil. `evalue_le` est un instant serveur réel
						// dont la date parisienne est, SOUS CETTE GARDE, le jour de
						// validité lui-même. La garde ne compare que trois valeurs du
						// serveur — aucune arithmétique de date ici.
						// À RETIRER dès que massifs_horodatage_jour() existe (B-1).
						if ( $massifs_fraicheur['jour_validite'] === $massifs_synthese['jour_validite']
							&& $massifs_synthese['jour_validite'] === massifs_jour_courant() ) {
	
							$massifs_jour_formate = massifs_horodatage( $massifs_fraicheur['evalue_le'] );
	
							$massifs_propositions['validite'] = sprintf(
								'Statuts du <time datetime="%1$s">%2$s</time>',
								esc_attr( $massifs_synthese['jour_validite'] ),
								esc_html( $massifs_jour_formate['date_longue'] )
							);
						}
	
						if ( null !== $massifs_fraicheur['publie_prefecture_le'] ) {
							$massifs_publication = massifs_horodatage( $massifs_fraicheur['publie_prefecture_le'] );
	
							$massifs_propositions['publication'] = sprintf(
								'publiés la veille à %s par la préfecture',
								esc_html( $massifs_publication['heure'] )
							);
						}
	
						if ( null !== $massifs_fraicheur['dernier_releve_le'] ) {
							$massifs_releve = massifs_horodatage( $massifs_fraicheur['dernier_releve_le'] );
	
							$massifs_propositions['releve'] = sprintf(
								'relevés sur ce site le <time datetime="%1$s">%2$s</time> à %3$s',
								esc_attr( $massifs_releve['attr_datetime'] ),
								esc_html( $massifs_releve['date_longue'] ),
								esc_html( $massifs_releve['heure'] )
							);
						}
	
						if ( array_key_exists( 'validite', $massifs_propositions ) ) {
							$massifs_phrase = $massifs_propositions['validite'];
	
							if ( array_key_exists( 'publication', $massifs_propositions ) ) {
								$massifs_phrase .= ', ' . $massifs_propositions['publication'];
							}
	
							if ( array_key_exists( 'releve', $massifs_propositions ) ) {
								$massifs_phrase .= ' — ' . $massifs_propositions['releve'];
							}
	
							// Chaque valeur du serveur a été échappée à la construction
							// ci-dessus ; seuls les <time> et la ponctuation du gabarit
							// sont du balisage écrit ici.
							echo '<p class="ardoise__fraicheur">' . $massifs_phrase . '.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							massifs_journaliser( 'massifs: ligne de fraîcheur omise — la date de validité ne peut pas être mise en forme sans massifs_horodatage_jour() (demande B-1).' );
						}
					}
	
					// La péremption AJOUTE une phrase. Elle ne masque, ne remplace et
					// ne conditionne rien : le chiffre, le titre et la ligne de
					// fraîcheur restent identiques.
					if ( $massifs_peremption ) :
						?>
						<p class="ardoise__peremption">Donnée périmée.</p>
						<?php
					endif;
					?>
				</div>
			</div>
		</section>

		<section id="non-officialite" class="bande bande--non-officialite">
			<div class="bande__contenu">
				<?php massifs_partie( 'bandeau-non-officialite' ); ?>
			</div>
		</section>

		<?php
		// Emplacement de la carte (MASTER.md §7.1). La bande n'émet PAS de
		// .bande__contenu : c'est ainsi qu'elle touche les deux bords de la
		// fenêtre à toutes les tailles, sans marge négative.
		// Sans nom accessible (ni h2, ni aria-labelledby, ni aria-label), elle est
		// exposée comme « generic » et ne crée donc aucun landmark vide.
		// La carte elle-même, sa hauteur et son repli statique appartiennent à la
		// chaîne « carte ».
		?>
		<section id="carte" class="bande bande--carte"></section>

		<?php
		// Arbitrage A-23 : les deux bandes ci-dessous n'ont DÉLIBÉRÉMENT ni `id`,
		// ni nom accessible, ni `tabindex`, ni titre. Les parties de la chaîne #6
		// sont auto-portantes : elles émettent elles-mêmes leur <section id>, leur
		// titre officiel et, pour la liste, le `tabindex="-1"` visé par le second
		// lien d'évitement. Les rétablir ici recréerait les doublons d'`id` et de
		// `h2` qui rendaient la page invalide. Ces enveloppes ne servent plus qu'à
		// la mise en page.
		//
		// Aucun `$args` n'est passé : les valeurs par défaut des parties produisent
		// déjà `id="legende"` et `id="liste"`, exactement les ancres attendues.
		?>
		<div class="bande bande--legende">
			<div class="bande__contenu">
				<?php massifs_partie( 'legende' ); ?>
			</div>
		</div>

		<div class="bande bande--liste">
			<div class="bande__contenu">
				<?php massifs_partie( 'liste-statuts' ); ?>
			</div>
		</div>

		<?php
		// Bande « Danger météo du jour » (MASTER.md §8.6) — non émise : elle
		// appartient à la chaîne « meteo ». Une <section> portant un h2 et rien
		// dedans est un landmark vide, donc un défaut d'accessibilité, pas un
		// emplacement réservé. Elle s'insérera ici, entre #liste et le pied.
		//
		// Bande « Zones parcourues par le feu » (MASTER.md §7.1) — non émise :
		// elle appartient à la chaîne « effis », même raison, même place.
		?>

<?php
get_template_part( 'templates/footer' );
