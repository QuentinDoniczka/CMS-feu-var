<?php
/**
 * Template Name: Mentions légales
 *
 * Gabarit de la page « Mentions légales » (brief §5.1 et §9).
 *
 * CE QUE CE FICHIER PORTE : les faits d'identité fournis par le propriétaire du
 * projet le 16 août 2026 (contrat #18 §4), et les CINQ attributions servies par
 * l'extension. La prose qui les encadre vit dans le contenu (arbitrage A-1).
 *
 * POURQUOI LES CINQ ATTRIBUTIONS ICI, alors que templates/footer.php en rend
 * déjà deux sur toutes les pages (arbitrage A-4) : le §9 du brief et le §16 de
 * MASTER.md exigent que les cinq figurent aux mentions légales. Le pied les
 * traite comme des CRÉDITS attachés à une donnée affichée — sa doctrine écrite
 * est « créditer une source dont aucune donnée n'est affichée est une
 * affirmation fausse » ; cette page les traite comme une TABLE DES SOURCES ET
 * LICENCES du site, ce qui est un autre objet. La duplication de deux phrases
 * est réelle, PRÉ-EXISTANTE et déjà enregistrée au contrat #24, report F-3 :
 * la résoudre demande templates/footer.php, hors empreinte de l'issue #18.
 *
 * POURQUOI UNE LISTE DE DÉFINITIONS ET NON UN TABLEAU : le §7.3 de MASTER.md
 * prescrit que la table des sources reprenne le tableau de la liste du jour.
 * Ces classes-là (`liste-statuts__*`) portent une mise en page mobile en cartes,
 * une règle `:empty` qui dépend des sauts de ligne du PHP, et du contenu généré
 * par `data-etiquette` : les réemployer ici serait fragile et faux de sens. Et
 * les rendre correctement demanderait du CSS, or assets/css/ est hors empreinte
 * et dev-ux-cms n'a pas été lancé. Une <dl> tient à 360 px sans une ligne de
 * style — et le zéro défilement horizontal est bloquant (§8). Écart signalé.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// AVANT l'en-tête : c'est templates/header.php qui appelle wp_head().
// Gardé : `includes/` absent doit dégrader la page, jamais la faire tomber en
// erreur fatale. Une métadonnée qui manque n'est pas un motif de page blanche.
$massifs_seo = get_theme_file_path( 'includes/seo-meta.php' );

if ( is_readable( $massifs_seo ) ) {
	require_once $massifs_seo;
}

if ( function_exists( 'massifs_declarer_description' ) ) {
	massifs_declarer_description(
		'Éditeur, directeur de la publication, contact, et licences des données relayées par ce site.'
	);
}

if ( ! function_exists( 'massifs_mentions_source' ) ) {
	/**
	 * Rend une source : sa phrase d'attribution, liée à sa licence si elle existe.
	 *
	 * INTERDIT DE DÉCOUPE — la phrase est rendue ENTIÈRE comme texte du lien.
	 * Elle est une DONNÉE servie par l'extension, pas de la rédaction de thème :
	 * la Licence Ouverte 2.0 et l'ODbL imposent des formulations exactes, et
	 * trois consommateurs qui les assembleraient chacun de leur côté
	 * produiraient trois variantes, dont deux non conformes.
	 *
	 * Une phrase vide ne rend RIEN : elle signifie « source indisponible », et
	 * non « source absente ».
	 *
	 * Un `lien_licence` vide ne produit AUCUN <a> : un href="" pointerait sur la
	 * page courante, et un lien qui ment est pire qu'un lien absent. C'est le
	 * cas de la météo dans la version livrée.
	 *
	 * @param string $terme        Nature de la source.
	 * @param string $phrase       Phrase d'attribution, brute.
	 * @param string $lien_licence URL de la licence, brute, éventuellement vide.
	 */
	function massifs_mentions_source( string $terme, string $phrase, string $lien_licence = '' ): void {
		if ( '' === $phrase ) {
			return;
		}

		printf( '<dt>%s</dt>', esc_html( $terme ) );

		if ( '' !== $lien_licence ) {
			printf(
				'<dd><a href="%1$s">%2$s</a></dd>',
				esc_url( $lien_licence ),
				esc_html( $phrase )
			);

			return;
		}

		printf( '<dd>%s</dd>', esc_html( $phrase ) );
	}
}

get_template_part( 'templates/header' );

if ( have_posts() ) :
	the_post();
	?>

		<section class="bande bande--editorial">
			<div class="bande__contenu">
				<?php
				// Sans esc_html() : voir page.php. Tout ce qui suit reste ENFANT
				// DIRECT de .bande__contenu (rythme vertical, titres, 68ch).
				the_title( '<h1>', '</h1>' );
				the_content();
				?>

				<h2 id="editeur">Éditeur</h2>
				<dl>
					<?php
					/*
					 * FOURNIS PAR LE PROPRIÉTAIRE DU PROJET, relayés par
					 * l'orchestrateur — voir le contrat #18 §4 et son avis de
					 * provenance. Ce ne sont ni des déductions, ni des valeurs
					 * plausibles : « OmbruStudio » est le nom de la société de
					 * l'éditeur, donné en réponse à une question posée.
					 *
					 * À reprendre VERBATIM. Une casse modifiée (« Ombru Studio »,
					 * « OMBRUSTUDIO ») serait une altération d'un nom de société sur
					 * la page qui l'engage juridiquement.
					 */
					?>
					<dt>Éditeur</dt>
					<dd>OmbruStudio</dd>

					<dt>Directeur de la publication</dt>
					<dd>Quentin Doniczka</dd>

					<?php
					// Adresse déclarée UNE SEULE FOIS dans includes/seo-meta.php,
					// et partagée avec la page « Accessibilité ».
					//
					// Gardé comme sur la page « Accessibilité » : sans
					// includes/seo-meta.php la constante n'existe pas, et la lire
					// serait une erreur fatale PHP 8 — exactement ce que l'en-tête
					// de ce fichier promet d'éviter. La garde couvre le <dt> AUTANT
					// que le <dd> : un terme sans définition annoncerait un contact
					// qu'on est incapable de donner.
					// Vide ⇒ emplacement marqué, jamais un lien mort.
					if ( defined( 'MASSIFS_CONTACT' ) && '' !== MASSIFS_CONTACT ) {
						printf(
							'<dt>Contact</dt><dd><a href="%1$s">%2$s</a></dd>',
							// `esc_url()` et non `esc_attr()` : règle 2 du §1.1 du
							// contrat. Le schéma est concaténé AVANT l'échappement,
							// sans quoi `esc_url()` prendrait l'adresse nue pour une
							// URL relative au lieu d'y reconnaître `mailto:`.
							esc_url( 'mailto:' . MASSIFS_CONTACT ),
							esc_html( antispambot( MASSIFS_CONTACT ) )
						);
					} else {
						// Sur une page de mentions légales, le contact est une ligne
						// ATTENDUE : la faire disparaître ferait passer un manque
						// pour un choix. L'emplacement reste visible et marqué.
						print '<dt>Contact</dt><dd><strong>[à fournir par le propriétaire du projet]</strong></dd>';
					}
					?>

					<dt>Hébergeur</dt>
					<?php
					/*
					 * EN SOMMEIL, dit comme tel et jamais inventé. Le propriétaire du
					 * projet a arrêté que le site ne serait pas publié
					 * (docs/decisions/portee-non-publiee.md) : il n'existe donc aucun
					 * hébergeur à nommer. Inventer un nom serait une invention
					 * interdite ; laisser la ligne vide laisserait croire à un oubli.
					 * La mention se rallume telle quelle le jour d'une publication.
					 */
					?>
					<dd>En sommeil : le site n'est pas publié et n'a donc pas d'hébergeur. Décision consignée dans <code>docs/decisions/portee-non-publiee.md</code>. Cette mention se rallume telle quelle le jour d'une publication.</dd>
				</dl>

				<?php
				// -------------------------------------------------------------------
				// Les cinq attributions du §9 du brief. Toutes servies par
				// l'extension, toutes sous garde : la page reste valide extension
				// désactivée, et aucune phrase n'est rédigée ici.
				// -------------------------------------------------------------------

				$massifs_perimetres = function_exists( 'massifs_attribution' ) ? massifs_attribution() : null;
				$massifs_fond       = function_exists( 'massifs_attribution_fond_de_carte' ) ? massifs_attribution_fond_de_carte() : null;
				$massifs_statuts    = function_exists( 'massifs_attribution_statuts' ) ? massifs_attribution_statuts() : null;
				$massifs_zones      = function_exists( 'massifs_attribution_zones_parcourues_par_le_feu' ) ? massifs_attribution_zones_parcourues_par_le_feu() : null;

				// Seule la clé `attribution` est lue. La fonction est TOTALE :
				// l'attribution est toujours peuplée, même en état indisponible.
				// AUCUNE date du retour ne transparaît — une page de mentions
				// légales n'affiche pas l'état du jour.
				$massifs_meteo = function_exists( 'massifs_meteo_du_jour' ) ? massifs_meteo_du_jour()['attribution'] : null;

				$massifs_a_une_source = ( null !== $massifs_perimetres && '' !== $massifs_perimetres['phrase'] )
					|| ( null !== $massifs_fond && '' !== $massifs_fond['phrase'] )
					|| ( null !== $massifs_statuts && '' !== $massifs_statuts['texte'] )
					|| ( null !== $massifs_zones && '' !== $massifs_zones['phrase'] )
					|| ( null !== $massifs_meteo && '' !== $massifs_meteo['texte'] );

				if ( $massifs_a_une_source ) :
					?>
					<h2 id="sources">Sources et licences</h2>
					<dl>
						<?php
						if ( null !== $massifs_perimetres ) {
							massifs_mentions_source(
								'Périmètres des massifs',
								$massifs_perimetres['phrase'],
								$massifs_perimetres['lien_licence']
							);
						}

						if ( null !== $massifs_fond ) {
							massifs_mentions_source(
								'Fond de carte',
								$massifs_fond['phrase'],
								$massifs_fond['lien_licence']
							);
						}

						if ( null !== $massifs_statuts ) {
							// Pas de lien de licence : la préfecture ne publie ni
							// licence ni conditions de réutilisation pour ce flux
							// (docs/decisions/source-prefecture.md). En inventer une
							// serait une invention interdite.
							massifs_mentions_source( 'Statuts quotidiens', $massifs_statuts['texte'] );
						}

						if ( null !== $massifs_meteo ) {
							massifs_mentions_source(
								'Danger météo',
								$massifs_meteo['texte'],
								$massifs_meteo['lien_licence']
							);
						}

						if ( null !== $massifs_zones ) {
							massifs_mentions_source( 'Zones parcourues par le feu', $massifs_zones['phrase'] );
						}
						?>
					</dl>
					<?php
				endif;
				?>

			</div>
		</section>

	<?php
else :
	massifs_journaliser( 'massifs: page-mentions-legales.php atteint sans post (contrat #18) — aucun titre de page rendu.' );
endif;

get_template_part( 'templates/footer' );
