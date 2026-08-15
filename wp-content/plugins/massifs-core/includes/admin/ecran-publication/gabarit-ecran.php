<?php
/**
 * Gabarit complet de l'écran de mise à jour des statuts.
 *
 * `massifs_publication_rendre()` est le SEUL point d'entrée du rendu (contrat #14
 * §3). Il consomme le modèle de vue, échappe tout en sortie, et ne rédige, ne
 * formate, ne traduit, ne trie et ne compte aucune valeur : toutes les chaînes,
 * toutes les dates et tous les ordres viennent du serveur.
 *
 * Plan de titres : un `h1`, deux `h2` au maximum (récapitulatif, liste), aucun
 * `h3`, aucun titre par massif — 25 titres feraient de la navigation par titres du
 * bruit, alors que le `<ul>` donne déjà « 7 sur 25 » et que chaque groupe porte son
 * nom par son `<legend>`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_rendre_bandeaux' ) ) {
	/**
	 * Rend les bandeaux d'état de page servis par le serveur.
	 *
	 * `.bandeau-alerte`, `.sur-sombre` et `.repere.repere--bloc` reprennent le
	 * composant du thème (`templates/parts/etats-vides.php`) : MASTER §3.2
	 * emplacement 6 pose le repère sur le bord gauche d'un bandeau d'alerte.
	 *
	 * @param array $bandeaux Entrées `['texte','lien_texte','lien_url']`.
	 */
	function massifs_publication_rendre_bandeaux( array $bandeaux ): void {
		if ( array() === $bandeaux ) {
			return;
		}
		?>
		<div class="massifs-ecran__bandeaux">
			<?php foreach ( $bandeaux as $bandeau ) : ?>
			<p class="bandeau-alerte sur-sombre repere repere--bloc massifs-ecran__bandeau">
				<span class="massifs-ecran__bandeau-texte"><?php echo esc_html( $bandeau['texte'] ); ?></span>
				<?php if ( '' !== $bandeau['lien_url'] && '' !== $bandeau['lien_texte'] ) : ?>
				<a class="massifs-ecran__bandeau-lien" href="<?php echo esc_url( $bandeau['lien_url'] ); ?>"><?php echo esc_html( $bandeau['lien_texte'] ); ?></a>
				<?php endif; ?>
			</p>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'massifs_publication_rendre_jours' ) ) {
	/**
	 * Rend le sélecteur de jour.
	 *
	 * Deux liens `GET` dans un `<nav>`, jamais un contrôle de formulaire : changer
	 * de jour est une navigation, pas une saisie ; un `<form>` imbriqué dans le
	 * `<form>` de publication serait du HTML invalide ; et une paire segmentée
	 * visuellement jumelle de celle des statuts ferait confondre « choisir un jour »
	 * et « choisir un niveau ». Changer de jour n'écrit jamais rien.
	 *
	 * @param array $jour    Bloc `jour` du modèle de vue.
	 * @param array $chaines Chaînes rédigées par le serveur.
	 */
	function massifs_publication_rendre_jours( array $jour, array $chaines ): void {
		?>
		<nav class="massifs-jours" aria-labelledby="massifs-jours-intitule">
			<span class="massifs-jours__intitule" id="massifs-jours-intitule"><?php echo esc_html( $chaines['jours_intitule'] ); ?></span>
			<ul class="massifs-jours__liste">
				<?php foreach ( $jour['choix'] as $choix ) : ?>
				<li class="massifs-jours__element">
					<a class="massifs-jours__lien" href="<?php echo esc_url( $choix['url'] ); ?>"<?php echo $choix['actif'] ? ' aria-current="page"' : ''; ?>>
						<span class="massifs-jours__libelle"><?php echo esc_html( $choix['libelle'] ); ?></span>
						<span class="massifs-jours__date"><?php echo esc_html( $choix['date_lettres'] ); ?></span>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( '' !== $chaines['jours_avertissement'] ) : ?>
			<p class="massifs-jours__avertissement"><?php echo esc_html( $chaines['jours_avertissement'] ); ?></p>
			<?php endif; ?>
		</nav>
		<?php
	}
}

if ( ! function_exists( 'massifs_publication_rendre_champs_communs' ) ) {
	/**
	 * Rend les quatre champs cachés que les deux formulaires frères partagent.
	 *
	 * Même `action`, même nonce, même jour, même empreinte : un seul chemin
	 * d'écriture et un seul jeu de gardes côté serveur, quelle que soit l'intention
	 * soumise.
	 *
	 * @param array $ecran Bloc `ecran` du modèle de vue.
	 * @param array $jour  Bloc `jour` du modèle de vue.
	 */
	function massifs_publication_rendre_champs_communs( array $ecran, array $jour ): void {
		?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $ecran['action_nom'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $ecran['nonce_champ'] ); ?>" value="<?php echo esc_attr( $ecran['nonce'] ); ?>">
		<?php // Jeton relatif, jamais une date : `?massifs_jour=2024-08-02` doit rester inconcevable. ?>
		<input type="hidden" name="massifs_jour" value="<?php echo esc_attr( $jour['jeton'] ); ?>">
		<?php // Sans ce champ, la détection de concurrence optimiste du service serait morte. ?>
		<input type="hidden" name="massifs_empreinte" value="<?php echo esc_attr( $ecran['empreinte'] ); ?>">
		<?php
	}
}

if ( ! function_exists( 'massifs_publication_rendre' ) ) {
	/**
	 * Rend l'écran de mise à jour des statuts.
	 *
	 * @param array $modele Modèle de vue produit par `massifs_publication_modele()`.
	 */
	function massifs_publication_rendre( array $modele ): void {
		$ecran   = $modele['ecran'];
		$jour    = $modele['jour'];
		$chaines = $modele['chaines'];
		?>
		<div class="wrap massifs-ecran">
			<h1 class="massifs-ecran__titre"><?php echo esc_html( $ecran['titre'] ); ?></h1>

			<?php
			/*
			 * Le jour édité appartient à la bande de tête : rendu plus bas, il tombait
			 * à ≈ 400 px sous le h1 à 360 px. Il reste néanmoins sous la garde de
			 * disponibilité du référentiel — §6 veut alors le bandeau « et rien
			 * d'autre », et annoncer un jour à publier quand rien n'est publiable
			 * serait un mensonge de la bande de tête.
			 */
			?>
			<?php if ( $modele['referentiel_disponible'] && '' !== $chaines['sous_titre_jour'] ) : ?>
			<p class="massifs-ecran__sous-titre"><?php echo esc_html( $chaines['sous_titre_jour'] ); ?></p>
			<?php endif; ?>

			<?php // Obligatoire : sans lui, WordPress déplace les notices d'administration à un endroit imprévisible de notre balisage. ?>
			<hr class="wp-header-end">

			<?php massifs_publication_rendre_recapitulatif( $modele['recapitulatif'] ); ?>
			<?php massifs_publication_rendre_bandeaux( $modele['bandeaux'] ); ?>

			<?php if ( $modele['referentiel_disponible'] ) : ?>

			<?php massifs_publication_rendre_jours( $jour, $chaines ); ?>

			<?php
			/*
			 * FORMULAIRE FRÈRE, jamais imbriqué (R-1). La soumission implicite de HTML
			 * déclenche le premier bouton de soumission du formulaire : avec les trois
			 * intentions réunies, `Entrée` frappé sur un radio passait les 25 massifs en
			 * « accès autorisé ». Sur un site d'accès aux massifs en risque incendie,
			 * c'est un défaut de sécurité, pas une gêne. Séparés, `Entrée` dans le
			 * formulaire principal ne peut plus déclencher que « Publier les statuts ».
			 *
			 * Ce formulaire ne transporte aucun radio, et c'est correct par
			 * construction : le pré-remplissage écrase la saisie partielle (A-2), il n'a
			 * rien à préserver.
			 */
			?>
			<form class="massifs-preremplissage__formulaire" method="post" action="<?php echo esc_url( $ecran['action_url'] ); ?>">
				<?php massifs_publication_rendre_champs_communs( $ecran, $jour ); ?>

				<div class="massifs-preremplissage" role="group" aria-labelledby="massifs-preremplissage-intitule">
					<span class="massifs-preremplissage__intitule" id="massifs-preremplissage-intitule"><?php echo esc_html( $chaines['preremplissage_intitule'] ); ?></span>
					<?php foreach ( $modele['preremplissage'] as $bouton ) : ?>
					<button class="massifs-bouton massifs-bouton--secondaire" type="submit" name="massifs_intention" value="<?php echo esc_attr( $bouton['valeur'] ); ?>"><?php echo esc_html( $bouton['libelle'] ); ?></button>
					<?php endforeach; ?>
				</div>
			</form>

			<form class="massifs-ecran__formulaire" method="post" action="<?php echo esc_url( $ecran['action_url'] ); ?>">
				<?php massifs_publication_rendre_champs_communs( $ecran, $jour ); ?>

				<h2 class="massifs-liste__titre" id="massifs-liste-titre"><?php echo esc_html( $chaines['liste_titre'] ); ?></h2>
				<ul class="massifs-liste" aria-labelledby="massifs-liste-titre">
					<?php foreach ( $modele['lignes'] as $ligne ) : ?>
						<?php massifs_publication_rendre_ligne( $ligne, $modele['niveaux'], $chaines, (bool) $jour['reference_redondante'] ); ?>
					<?php endforeach; ?>
				</ul>

				<?php // Dernier enfant du formulaire : dernière dans l'ordre de tabulation, donc jamais un piège clavier (déviation D-3). Son bouton est l'UNIQUE bouton de soumission de ce formulaire, donc la cible de la soumission implicite (R-1). ?>
				<div class="massifs-barre-action sur-sombre repere repere--bloc">
					<p class="massifs-barre-action__compteur"><?php echo esc_html( $modele['compteur']['texte'] ); ?></p>
					<button class="massifs-bouton massifs-bouton--principal" type="submit" name="massifs_intention" value="<?php echo esc_attr( $modele['publier']['valeur'] ); ?>"><?php echo esc_html( $modele['publier']['libelle'] ); ?></button>
				</div>
			</form>

			<?php endif; ?>
		</div>
		<?php
	}
}
