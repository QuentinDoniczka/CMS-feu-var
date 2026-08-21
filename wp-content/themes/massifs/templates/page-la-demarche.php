<?php
/**
 * Template Name: La démarche
 *
 * Gabarit de la page « La démarche » (brief §5.1 et §9).
 *
 * CE QUE CE FICHIER PORTE, ET CE QU'IL NE PORTE PAS — c'est l'arbitrage A-1 du
 * contrat #18, et c'est le point le plus facile à casser de bonne foi :
 *
 * - Il porte la STRUCTURE et les FAITS SERVIS PAR L'EXTENSION. Rien d'autre.
 * - Il ne porte AUCUNE prose éditoriale. Pourquoi le site existe, comment il
 *   fonctionne, ses limites, le choix « zéro cookie », le format JSON : tout
 *   cela arrive par `the_content()`, depuis la base. `MASTER.md` §11.3 est une
 *   liste FERMÉE des phrases qu'une page publique a le droit de rédiger, et le
 *   §16 tranche que ce type de rédaction « vient du contenu, jamais du code ».
 *   Écrire ici un paragraphe explicatif serait un défaut bloquant, pas une
 *   amélioration.
 *
 * Les blocs `faits` consommés plus bas ont été RÉSERVÉS À CETTE PAGE par
 * l'extension elle-même, en commentaire de code (voir
 * includes/ingest/tuiles/attribution.php, includes/domain/massifs/attribution.php
 * et includes/ingest/effis/class-attribution.php). Cette page est leur premier
 * et unique consommateur.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// AVANT l'en-tête, impérativement : c'est templates/header.php qui appelle
// wp_head(). Après lui, le <head> est clos et la description ne s'imprimerait
// jamais, sans la moindre erreur visible.
// Gardé : `includes/` absent doit dégrader la page, jamais la faire tomber.
$massifs_seo = get_theme_file_path( 'includes/seo-meta.php' );

if ( is_readable( $massifs_seo ) ) {
	require_once $massifs_seo;
}

if ( function_exists( 'massifs_declarer_description' ) ) {
	massifs_declarer_description(
		'Comment ce site relaie les statuts d\'accès aux massifs forestiers des Bouches-du-Rhône : sources, licences, limites et point d\'accès public au format JSON.'
	);
}

if ( ! function_exists( 'massifs_demarche_fait' ) ) {
	/**
	 * Rend une paire terme/valeur, et RIEN quand la valeur est vide.
	 *
	 * Une valeur vide signifie « ce fait n'est pas établi », jamais « ce fait
	 * vaut zéro » : afficher l'étiquette avec un tiret ou « non renseigné »
	 * inventerait une information. Même règle que l'emplacement de consigne du
	 * §8.4 de MASTER.md, pour la même raison.
	 *
	 * @param string $terme  Étiquette du fait.
	 * @param string $valeur Valeur brute servie par l'extension.
	 * @param bool   $jeton  Vrai si la valeur est un jeton technique et non une
	 *                       valeur rédigée en français — rendue alors en <code>.
	 */
	function massifs_demarche_fait( string $terme, string $valeur, bool $jeton = false ): void {
		if ( '' === $valeur ) {
			return;
		}

		printf(
			'<dt>%1$s</dt><dd>%2$s</dd>',
			esc_html( $terme ),
			$jeton
				? '<code>' . esc_html( $valeur ) . '</code>'
				: esc_html( $valeur )
		);
	}
}

get_template_part( 'templates/header' );

// `if` et NON `while`, un seul the_post() : le nombre de h1 émis reste une
// constante du code source, pas une fonction de $wp_query->post_count. Même
// raison que page.php.
if ( have_posts() ) :
	the_post();
	?>

		<section class="bande bande--editorial">
			<div class="bande__contenu">
				<?php
				/*
				 * the_title() et the_content() sont rendus SANS esc_html(), et ce
				 * n'est pas un oubli — ne pas le « corriger » : le filtre
				 * `the_title` applique wptexturize(), qui PRODUIT des entités, et
				 * les ré-encoder afficherait « La d&#8217;émarche ». the_content()
				 * est un flux HTML filtré par construction.
				 *
				 * the_content() est appelé SANS AUCUNE ENVELOPPE, et tout ce qui
				 * suit reste ENFANT DIRECT de .bande__contenu : layout.css y
				 * accroche le rythme vertical, l'espacement des titres et la
				 * mesure de 68ch. Une <div> ici ferait perdre les trois en silence.
				 */
				the_title( '<h1>', '</h1>' );
				the_content();

				// ---------------------------------------------------------------
				// Sources et licences — FAITS SERVIS, jamais rédigés.
				// La hiérarchie commence à h2 : l'unique h1 est le titre de page.
				// ---------------------------------------------------------------

				$massifs_perimetres = function_exists( 'massifs_attribution' ) ? massifs_attribution() : null;
				$massifs_communes   = function_exists( 'massifs_attribution_communes' ) ? massifs_attribution_communes() : null;
				$massifs_fond       = function_exists( 'massifs_attribution_fond_de_carte' ) ? massifs_attribution_fond_de_carte() : null;
				$massifs_statuts    = function_exists( 'massifs_attribution_statuts' ) ? massifs_attribution_statuts() : null;
				$massifs_zones      = function_exists( 'massifs_attribution_zones_parcourues_par_le_feu' ) ? massifs_attribution_zones_parcourues_par_le_feu() : null;

				// Seule la clé `attribution` est lue, et aucune date du retour ne
				// doit transparaître : cette page ne montre pas l'état du jour.
				// La fonction est TOTALE, donc l'attribution est toujours peuplée,
				// y compris quand la météo est indisponible.
				$massifs_meteo = function_exists( 'massifs_meteo_du_jour' ) ? massifs_meteo_du_jour()['attribution'] : null;

				// On ne rend pas de titre de section vide — et « vide » ne veut pas
				// seulement dire « extension absente » : chaque sous-bloc ci-dessous
				// exige EN PLUS une phrase non vide, donc les six lectures peuvent
				// répondre et n'imprimer rien. La garde rejoue donc exactement la
				// condition des sous-blocs, sinon un <h2> annoncerait une section
				// absente — ce qu'interdit le §3 du contrat #18 (« ni intitulé, ni
				// tiret »). Même garde que celle des mentions légales.
				$massifs_a_une_source = ( null !== $massifs_perimetres && '' !== $massifs_perimetres['phrase'] )
					|| ( null !== $massifs_communes && '' !== $massifs_communes['phrase'] )
					|| ( null !== $massifs_fond && '' !== $massifs_fond['phrase'] )
					|| ( null !== $massifs_statuts && '' !== $massifs_statuts['texte'] )
					|| ( null !== $massifs_zones && '' !== $massifs_zones['phrase'] )
					|| ( null !== $massifs_meteo && '' !== $massifs_meteo['texte'] );

				if ( $massifs_a_une_source ) :
					?>
					<h2 id="sources">Sources et licences</h2>
					<?php

					// --- Périmètres des massifs ---
					if ( null !== $massifs_perimetres && '' !== $massifs_perimetres['phrase'] ) :
						?>
						<h3>Périmètres des massifs</h3>
						<p><?php echo esc_html( $massifs_perimetres['phrase'] ); ?></p>
						<dl>
							<?php
							$massifs_faits = $massifs_perimetres['faits'];
							massifs_demarche_fait( 'Producteur', $massifs_faits['producteur'] );
							massifs_demarche_fait( 'Jeu de données', $massifs_faits['jeu_de_donnees'] );
							massifs_demarche_fait( 'Couche', $massifs_faits['couche'] );
							// `donnees_du_libelle` est la date DÉJÀ MISE EN FORME par
							// l'extension. Le thème ne compose jamais une date
							// lui-même (MASTER.md §11.1 règle 6) : `donnees_du` et
							// `recupere_le`, bruts en AAAA-MM-JJ, ne sont donc pas
							// affichés — les formater ici serait la violation.
							massifs_demarche_fait( 'Données du', $massifs_faits['donnees_du_libelle'] );
							// Nom et version RENDUS SÉPARÉMENT : les concaténer ferait
							// composer au thème une valeur que le serveur n'a pas
							// composée. Le serveur possède les données, le thème les
							// affiche — il ne les assemble pas.
							massifs_demarche_fait( 'Licence', $massifs_faits['licence_nom'] );
							massifs_demarche_fait( 'Version de la licence', $massifs_faits['licence_version'] );
							massifs_demarche_fait( 'Base réglementaire', $massifs_faits['base_reglementaire'] );
							massifs_demarche_fait( 'Identifiant du jeu de données', $massifs_faits['dataset_id'] );
							?>
						</dl>
						<?php
					endif;

					// --- Référentiel communal ---
					// Placé juste après les périmètres : les deux sont des
					// référentiels géographiques vectoriels sous Licence Ouverte, et
					// c'est le second qui fournit les noms de communes rattachés aux
					// massifs du premier.
					if ( null !== $massifs_communes && '' !== $massifs_communes['phrase'] ) :
						?>
						<h3>Référentiel communal</h3>
						<p>
							<?php
							/*
							 * INTERDIT DE DÉCOUPE, même règle qu'au fond de carte :
							 * la Licence Ouverte 2.0 impose une formulation exacte,
							 * et `phrase` porte le millésime résolu du référentiel
							 * (contrat #45 §2.1). Elle est rendue ENTIÈRE.
							 */
							if ( '' !== $massifs_communes['lien_licence'] ) :
								?>
								<a href="<?php echo esc_url( $massifs_communes['lien_licence'] ); ?>"><?php echo esc_html( $massifs_communes['phrase'] ); ?></a>
								<?php
							else :
								echo esc_html( $massifs_communes['phrase'] );
							endif;
							/*
							 * Aucune <dl> de faits, à la différence des périmètres et
							 * du fond de carte : le contrat #45 §5 gèle la FORME du
							 * retour, pas les clés de son bloc `faits`. Nommer ici un
							 * `<dt>` sur une clé non contractée serait inventer une
							 * étiquette et lire une clé qui peut ne pas exister. Les
							 * statuts et la météo se rendent déjà ainsi.
							 */
							?>
						</p>
						<?php
					endif;

					// --- Fond de carte ---
					if ( null !== $massifs_fond && '' !== $massifs_fond['phrase'] ) :
						?>
						<h3>Fond de carte</h3>
						<p>
							<?php
							/*
							 * INTERDIT DE DÉCOUPE : `phrase` est rendue ENTIÈRE comme
							 * texte du lien. L'ODbL impose d'attribuer OpenStreetMap ;
							 * couper la phrase ou inventer un libellé de lien
							 * produirait une attribution non conforme.
							 */
							if ( '' !== $massifs_fond['lien_licence'] ) :
								?>
								<a href="<?php echo esc_url( $massifs_fond['lien_licence'] ); ?>"><?php echo esc_html( $massifs_fond['phrase'] ); ?></a>
								<?php
							else :
								echo esc_html( $massifs_fond['phrase'] );
							endif;
							?>
						</p>
						<dl>
							<?php
							$massifs_faits = $massifs_fond['faits'];
							massifs_demarche_fait( 'Canal de récupération', $massifs_faits['canal'] );
							// Séparés, pour la même raison qu'au-dessus.
							massifs_demarche_fait( 'Licence', $massifs_faits['licence_nom'] );
							massifs_demarche_fait( 'Version de la licence', $massifs_faits['licence_version'] );
							massifs_demarche_fait( 'Rendu', $massifs_faits['rendu'] );
							?>
						</dl>
						<?php
					endif;

					// --- Statuts quotidiens ---
					if ( null !== $massifs_statuts && '' !== $massifs_statuts['texte'] ) :
						?>
						<h3>Statuts quotidiens</h3>
						<p><?php echo esc_html( $massifs_statuts['texte'] ); ?></p>
						<?php
					endif;

					// --- Danger météo ---
					if ( null !== $massifs_meteo && '' !== $massifs_meteo['texte'] ) :
						?>
						<h3>Danger météo</h3>
						<p>
							<?php
							// `lien_licence` est VIDE dans la version livrée : un
							// href="" pointerait sur la page courante. La phrase se
							// rend alors nue — un lien qui ment est pire qu'un lien
							// absent.
							if ( '' !== $massifs_meteo['lien_licence'] ) :
								?>
								<a href="<?php echo esc_url( $massifs_meteo['lien_licence'] ); ?>"><?php echo esc_html( $massifs_meteo['texte'] ); ?></a>
								<?php
							else :
								echo esc_html( $massifs_meteo['texte'] );
							endif;
							?>
						</p>
						<?php
					endif;

					// --- Zones parcourues par le feu ---
					if ( null !== $massifs_zones && '' !== $massifs_zones['phrase'] ) :
						?>
						<h3>Zones parcourues par le feu</h3>
						<p><?php echo esc_html( $massifs_zones['phrase'] ); ?></p>
						<dl>
							<?php
							$massifs_faits = $massifs_zones['faits'];
							massifs_demarche_fait( 'Producteur', $massifs_faits['producteur'] );
							massifs_demarche_fait( 'Service', $massifs_faits['service'] );
							massifs_demarche_fait( 'Méthode', $massifs_faits['methode'] );
							massifs_demarche_fait( 'Fenêtre observée, en jours', $massifs_faits['fenetre_jours'] );
							massifs_demarche_fait( 'Surface minimale détectée, en hectares', $massifs_faits['surface_minimale_ha'] );
							massifs_demarche_fait( 'Récupérations par jour', $massifs_faits['frequence_par_jour'] );
							/*
							 * `connecteur` expose la portée SIMULÉE de la source. Le
							 * publier n'est pas une option : le §2 de
							 * docs/decisions/portee-non-publiee.md exige que le
							 * bouchon reste auditable, et une page « La démarche » qui
							 * tairait que la source est simulée mentirait sur
							 * exactement ce qu'elle prétend documenter.
							 *
							 * La valeur est un JETON TECHNIQUE — `simule`, sans
							 * accent — et non un mot français. Elle est publiée
							 * VERBATIM, parce que reformuler une donnée serveur est
							 * interdit, mais rendue en <code> pour qu'on lise un
							 * identifiant et non une faute d'orthographe. Un libellé
							 * lisible relève de l'extension, hors empreinte : dette.
							 */
							massifs_demarche_fait( 'Connecteur', $massifs_faits['connecteur'], true );
							?>
						</dl>
						<?php
					endif;

				endif;
				?>
			</div>
		</section>

	<?php
else :
	// Cas pathologique — une page servie sans post, ce qu'un pre_get_posts tiers
	// est seul à pouvoir produire. Rien de visible n'est rendu : écrire une
	// phrase de repli serait de la copie d'interface inventée.
	massifs_journaliser( 'massifs: page-la-demarche.php atteint sans post (contrat #18) — aucun titre de page rendu.' );
endif;

get_template_part( 'templates/footer' );
