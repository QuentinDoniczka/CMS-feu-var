<?php
/**
 * Table FERMÉE de toutes les chaînes de l'écran d'historique.
 *
 * LA VUE N'EN COMPOSE AUCUNE. `ecran.php` échappe et pose des valeurs déjà
 * rédigées ; `donnees.php` est le seul endroit où un format à substitution est
 * appliqué, et il l'est côté serveur, sur une chaîne de cette table.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  CHAÎNES OFFICIELLES — §11.4 de design-system/MASTER.md                  │
 * │                                                                          │
 * │  `Niveau d'Accès` porte l'apostrophe DROITE U+0027.                       │
 * │  `Zones d’Accueil` porte l'apostrophe TYPOGRAPHIQUE U+2019.               │
 * │                                                                          │
 * │  LA DIVERGENCE EST VOLONTAIRE : c'est ce que publie la préfecture. Une    │
 * │  « uniformisation typographique » — réflexe d'un intégrateur              │
 * │  consciencieux, d'un linter ou d'un correcteur — casserait la             │
 * │  reproduction fidèle exigée par le §4.2 du brief. Toute modification, y   │
 * │  compris orthographique, est un défaut BLOQUANT.                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Les quatre libellés d'état (`Accès au massif autorisé`, `Accès au massif
 * interdit`, `Accès à la ZAPEF* autorisé`, `Accès à la ZAPEF* interdite`) ne
 * sont VOLONTAIREMENT PAS recopiés ici : ils appartiennent à la légende du
 * domaine, qui les reproduit déjà verbatim et les sert avec chaque entrée. Les
 * dupliquer créerait une seconde vérité, et c'est la copie qui finirait par
 * diverger de la source.
 *
 * Vocabulaire imposé (§11.2 de MASTER) : massif, niveau, statut, jour de
 * validité, gestionnaire, publier. JAMAIS zone, état, date, valider, secteur,
 * alerte.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_historique_vocabulaire' ) ) {
	/**
	 * Toutes les chaînes de l'écran, indexées par clé stable.
	 *
	 * Aucun filtre ne l'altère : un filtre laisserait réécrire depuis n'importe
	 * quel greffon les chaînes officielles du §11.4.
	 *
	 * @return array<string, string>
	 */
	function massifs_historique_vocabulaire(): array {
		return array(
			// Officielles §11.4 — ne jamais éditer, apostrophes comprises.
			'colonne_niveau'                  => 'Niveau d\'Accès',
			'zapef_note'                      => '*ZAPEF : Zones d’Accueil du Public en Forêt',

			// Titre et structure.
			'titre'                           => 'Historique des statuts',
			'legende_tableau'                 => 'Historique des statuts',

			// En-têtes de colonnes.
			'colonne_massif'                  => 'Massif',
			'colonne_jour'                    => 'Jour de validité',
			'colonne_zapef'                   => 'ZAPEF',
			'colonne_source'                  => 'Source',
			'colonne_auteur'                  => 'Auteur',
			'colonne_enregistre'              => 'Enregistré le',
			'colonne_reference'               => 'Référence',

			// Légendes des trois groupes de filtres.
			'groupe_identite'                 => 'Massif, source et auteur',
			'groupe_jour'                     => 'Jour de validité',
			'groupe_enregistre'               => 'Enregistré le',

			// Étiquettes de champs.
			'champ_massif'                    => 'Massif',
			'champ_source'                    => 'Source',
			'champ_auteur'                    => 'Auteur',
			'champ_debut'                     => 'Du',
			'champ_fin'                       => 'Au',
			'option_tous_massifs'             => 'Tous les massifs',
			'option_toutes_sources'           => 'Toutes les sources',
			'option_tous_auteurs'             => 'Tous les auteurs',
			'aide_format_jour'                => 'Format AAAA-MM-JJ',

			// Actions.
			'action_filtrer'                  => 'Filtrer',
			'action_exporter'                 => 'Exporter en CSV',
			'action_reinitialiser'            => 'Réinitialiser les filtres',

			// Pagination.
			'pagination_titre'                => 'Pages de l\'historique',
			'pagination_precedente'           => 'Page précédente',
			'pagination_suivante'             => 'Page suivante',
			'pagination_page'                 => 'Page %d',

			// Sources — les deux termes du §4.2 du brief.
			'source_recuperation_officielle'  => 'Récupération officielle',
			'source_saisie_manuelle'          => 'Saisie manuelle',

			// Changements.
			'changement_premiere_publication' => 'Première publication',
			'changement_modification'         => 'Modification',
			'changement_sans_changement'      => 'Sans changement',

			// Valeurs de cellule.
			'aucun_niveau'                    => 'Aucun niveau publié',
			'niveau_inconnu'                  => 'Niveau non reconnu',
			'niveau_inconnu_texte'            => 'Niveau non reconnu (%s)',
			'auteur_officiel'                 => 'Récupération officielle',
			'auteur_supprime'                 => 'Compte supprimé (#%d)',
			// La flèche « ancienne vers nouvelle » N'EST PAS UNE CHAÎNE : le
			// caractère U+2192 est hors du sous-ensemble `latin` et absent des deux
			// polices du projet (MASTER §5, D-25). Elle est dessinée en SVG en
			// ligne par `ecran.php`, et seul ce « remplacé par » en porte le sens.
			'transition_remplace'             => 'remplacé par',
			'horodatage'                      => '%1$s à %2$s',

			// États de l'écran.
			'etat_journal_indisponible'       => 'Le journal des statuts est momentanément indisponible.',
			'etat_journal_vide'               => 'Aucune écriture n\'a encore été journalisée.',
			'etat_aucun_resultat'             => 'Aucune écriture ne correspond à ces filtres.',

			// Résumé de résultats.
			'resume_singulier'                => '%1$d écriture · page %2$d sur %3$d',
			'resume_pluriel'                  => '%1$d écritures · page %2$d sur %3$d',

			// Filtres rejetés — un message par champ, relié au champ par aria-describedby.
			'rejet_massif'                    => 'Ce massif est inconnu du référentiel : le filtre a été ignoré.',
			'rejet_auteur'                    => 'Cet auteur n\'apparaît dans aucune écriture : le filtre a été ignoré.',
			'rejet_source'                    => 'Cette source est inconnue : le filtre a été ignoré.',
			'rejet_jour_debut'                => 'Ce jour de validité est inexploitable : le filtre a été ignoré.',
			'rejet_jour_fin'                  => 'Ce jour de validité est inexploitable : le filtre a été ignoré.',
			'rejet_enregistre_debut'          => 'Ce jour est inexploitable : le filtre a été ignoré.',
			'rejet_enregistre_fin'            => 'Ce jour est inexploitable : le filtre a été ignoré.',
			'rejet_jour_intervalle'           => 'Le début est postérieur à la fin : aucun résultat possible.',
			'rejet_enregistre_intervalle'     => 'Le début est postérieur à la fin : aucun résultat possible.',
			'rejet_paged'                     => 'Ce numéro de page est inexploitable : la première page est affichée.',
			'rejet_par_page'                  => 'Ce nombre d\'écritures par page n\'est pas proposé : 50 sont affichées.',

			// Porte de capacité.
			'acces_refuse'                    => 'Vous n\'avez pas l\'autorisation de consulter l\'historique des statuts.',

			// Colonnes du CSV.
			'csv_reference'                   => 'Référence',
			'csv_massif_code'                 => 'Code du massif',
			'csv_massif'                      => 'Massif',
			'csv_jour'                        => 'Jour de validité',
			'csv_niveau_precedent'            => 'Niveau d\'Accès précédent',
			'csv_niveau'                      => 'Niveau d\'Accès',
			'csv_zapef_precedent'             => 'ZAPEF précédente',
			'csv_zapef'                       => 'ZAPEF',
			'csv_changement'                  => 'Changement',
			'csv_source'                      => 'Source',
			'csv_auteur'                      => 'Auteur',
			'csv_enregistre'                  => 'Enregistré le (ISO 8601 UTC)',
		);
	}
}

if ( ! function_exists( 'massifs_historique_mot' ) ) {
	/**
	 * Une chaîne de la table fermée.
	 *
	 * Une clé absente rend la chaîne vide plutôt qu'une clé technique : un écran
	 * d'audit n'affiche pas de jargon interne au gestionnaire.
	 *
	 * @param string $cle Clé de la table.
	 */
	function massifs_historique_mot( string $cle ): string {
		$vocabulaire = massifs_historique_vocabulaire();

		return $vocabulaire[ $cle ] ?? '';
	}
}
