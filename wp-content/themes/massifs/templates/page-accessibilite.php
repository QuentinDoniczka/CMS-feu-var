<?php
/**
 * Template Name: Accessibilité
 *
 * Gabarit de la page « Accessibilité » (brief §5.1 et §8).
 *
 * CE QUE CE FICHIER PORTE : la structure, et le moyen de signalement construit
 * sur la constante partagée `MASSIFS_CONTACT`.
 *
 * CE QU'IL NE PORTE PAS : la démarche suivie et les résultats des vérifications.
 * C'est de la prose éditoriale, elle vit dans le contenu (arbitrage A-1 du
 * contrat #18), et elle a une raison supplémentaire de ne pas vivre ici — voir
 * l'interdit ci-dessous.
 *
 * INTERDIT LE PLUS IMPORTANT DE CE FICHIER, et il est bloquant :
 * AUCUN taux ni qualificatif de conformité RGAA, nulle part. Ni « non
 * conforme », ni « partiellement conforme », ni « totalement conforme », ni
 * « x % des critères ». Aucun audit n'a été mené, et ces trois qualificatifs
 * sont eux-mêmes des RÉSULTATS d'audit : en écrire un, fût-ce le plus
 * pessimiste par prudence apparente, serait affirmer un fait non établi
 * (brief §4.2, MASTER.md §16). Et une valeur figée dans un gabarit devient
 * FAUSSE EN SILENCE au premier audit suivant — exactement la classe d'erreur
 * que le §16 interdit déjà pour le chiffre du jour. Le jour où un audit existe,
 * son résultat vient du CONTENU, jamais du code.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// AVANT l'en-tête : c'est templates/header.php qui appelle wp_head().
// Gardé : `includes/` absent doit dégrader la page, jamais la faire tomber.
$massifs_seo = get_theme_file_path( 'includes/seo-meta.php' );

if ( is_readable( $massifs_seo ) ) {
	require_once $massifs_seo;
}

if ( function_exists( 'massifs_declarer_description' ) ) {
	// N'ANNONCE AUCUN RÉSULTAT. La rédaction précédente promettait « les
	// résultats des vérifications menées », ce que le docblock ci-dessus
	// contredit dans le même fichier : aucun audit n'a été mené. Une
	// description est une promesse faite au lecteur avant qu'il n'ouvre la
	// page ; celle-ci ne promet que ce que la page tient.
	massifs_declarer_description(
		'Démarche d\'accessibilité suivie pour ce site et moyen de signaler un problème d\'accès à l\'information.'
	);
}

get_template_part( 'templates/header' );

if ( have_posts() ) :
	the_post();
	?>

		<section class="bande bande--editorial">
			<div class="bande__contenu">
				<?php
				// Sans esc_html() : voir page.php et page-la-demarche.php — le
				// filtre `the_title` produit des entités, les ré-encoder les
				// afficherait littéralement.
				//
				// Tout ce qui suit reste ENFANT DIRECT de .bande__contenu :
				// layout.css y accroche le rythme vertical, l'espacement des titres
				// et la mesure de 68ch.
				the_title( '<h1>', '</h1>' );
				the_content();
				?>

				<?php
				/*
				 * L'ADRESSE, ET RIEN QU'ELLE. La phrase qui invite à écrire est de
				 * la prose éditoriale : elle vit dans le contenu, comme tout le
				 * reste de cette page (arbitrage A-1). Ce gabarit ne porte que le
				 * FAIT — l'adresse — parce que lui seul ne peut pas vivre dans le
				 * contenu : il est déclaré une seule fois dans includes/seo-meta.php
				 * et partagé avec les mentions légales, et le recopier dans le
				 * contenu le ferait diverger au premier changement d'adresse.
				 *
				 * `antispambot()` est du cœur WordPress : il encode l'adresse en
				 * entités HTML dans le TEXTE visible. Le `mailto:` reste en clair,
				 * sans quoi le lien ne fonctionnerait pas. `esc_html()` ne
				 * double-encode pas — il passe `double_encode = false`, les entités
				 * survivent.
				 *
				 * Gardé : sans includes/seo-meta.php, la page rend son contenu au
				 * lieu de tomber en erreur fatale.
				 */
				// Vide ⇒ aucun lien. Un `mailto:` sans adresse est un lien mort sur
				// le canal de signalement d'accessibilité : pire que son absence.
				if ( defined( 'MASSIFS_CONTACT' ) && '' !== MASSIFS_CONTACT ) {
					printf(
						'<dl id="signalement"><dt>Signaler un problème d\'accessibilité</dt><dd><a href="%1$s">%2$s</a></dd></dl>',
						// `esc_url()` et non `esc_attr()` : c'est une URL, et la règle
						// 2 du §1.1 du contrat #18 l'impose. Le schéma est concaténé
						// AVANT l'échappement — `esc_url()` doit voir `mailto:…` en
						// entier pour reconnaître le protocole ; appliqué à l'adresse
						// nue il la traiterait comme une URL relative.
						esc_url( 'mailto:' . MASSIFS_CONTACT ),
						esc_html( antispambot( MASSIFS_CONTACT ) )
					);
				}
				?>
			</div>
		</section>

	<?php
else :
	massifs_journaliser( 'massifs: page-accessibilite.php atteint sans post (contrat #18) — aucun titre de page rendu.' );
endif;

get_template_part( 'templates/footer' );
