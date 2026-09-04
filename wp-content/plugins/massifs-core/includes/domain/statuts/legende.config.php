<?php
/**
 * Source UNIQUE de la légende — sémantique des niveaux du dispositif.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  LÉGENDE OFFICIELLE RELEVÉE — révision 2                                 │
 * │                                                                          │
 * │  Les libellés, l'heure de publication et la table                        │
 * │  de correspondance ci-dessous sont ceux du dispositif des                │
 * │  Bouches-du-Rhône, établis par trois relevés concordants et consignés    │
 * │  dans docs/decisions/source-prefecture.md §4.                            │
 * │                                                                          │
 * │  LA LÉGENDE PUBLIQUE DU 13 EST BINAIRE : deux états d'accès au massif,   │
 * │  plus une seconde dimension ZAPEF. Les cinq niveaux gradués de           │
 * │  design-system/MASTER.md §4.1 étaient des substituts de travail : ils    │
 * │  sont désormais connus comme FAUX et ont été supprimés. Les rétablir     │
 * │  violerait le §4.2 du brief, qui impose de reproduire exactement la      │
 * │  légende officielle et interdit d'en inventer une.                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Questions ENCORE OUVERTES au propriétaire du projet, et qui restent
 * bloquantes avant mise en ligne :
 *
 *  4. Quelle est la consigne officielle exacte associée à chaque état ?
 *     La légende publiée n'en porte AUCUNE, et l'arrêté préfectoral en vigueur
 *     est un PDF numérisé sans couche de texte, donc illisible. `consigne` reste
 *     une chaîne VIDE et `consignes_publiees` reste à `false` : ce n'est pas une
 *     donnée manquante à combler, c'est un fait relevé.
 *  5. Le dispositif distingue-t-il accès piéton, circulation/stationnement et
 *     travaux ? Les travaux relèvent d'un dispositif et d'une carte séparés ;
 *     circulation et stationnement sont absents de la source.
 *  8. La reproduction de la légende est-elle autorisée, et sous quelle mention
 *     de source ? Aucune mention légale, aucune CGU, aucune licence publiées.
 *     BLOQUANT AVANT MISE EN PRODUCTION.
 *  —. Que signifient `procedure` (index 1 du couple source) et `zm` (zones de
 *     granularité différente) ? `procedure` est persisté SANS être exposé ; `zm`
 *     n'est pas consommé.
 *
 * AUCUNE VALEUR HEXADÉCIMALE ICI, NI NULLE PART DANS L'EXTENSION. Chaque entrée
 * déclare le NOM de son jeton CSS ; le pigment appartient au design system
 * (`themes/massifs/assets/css/tokens.css`). Les teintes officielles relevées au
 * pixel sont consignées dans le contrat d'interface à l'intention de la chaîne
 * front. Une source pour la sémantique, une pour le pigment, zéro duplication.
 *
 * Le nom du jeton est une DONNÉE de configuration par entrée, jamais calculé
 * depuis une position : un changement du nombre d'entrées casserait sinon le
 * mapping en silence.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Libellés et teintes établis par trois relevés concordants : le fichier de
	// traduction officiel du 13, la fonction de traduction propre au 13, et le
	// compte d'entrées de légende dans le HTML rendu par la page officielle.
	'confirme'                     => true,

	// Le dispositif du 13 ne publie AUCUNE consigne. Le consommateur n'affiche
	// donc aucun intitulé « Consigne » tant que ce drapeau est faux.
	'consignes_publiees'           => false,

	'revision'                     => '2-legende-officielle-13',
	'source'                       => 'Légende officielle du dispositif d\'accès aux massifs des Bouches-du-Rhône, relevée le 11 août 2026 sur le fichier de traduction publié par la carte de la préfecture et corroborée par le bulletin quotidien officiel.',
	'source_officielle_url'        => 'https://www.risque-prevention-incendie.fr/13',

	// Heure locale (Europe/Paris) de publication, LA VEILLE du jour de validité.
	// 17 h et non 18 h : deux pages officielles annoncent « vers 18 heures », mais
	// le fichier de traduction réellement appliqué, le bulletin PDF et l'en-tête
	// `Last-Modified` du flux disent tous les trois 17 h 00.
	'publication_heure'            => '17:00',

	// ACCÈS AU MASSIF — liste ORDONNÉE PAR SÉVÉRITÉ CROISSANTE.
	// `cle` est une clé TEXTE stable, persistée telle quelle : un entier de
	// position ferait changer de sens toutes les lignes passées le jour où le
	// dispositif change de granularité.
	// Les libellés sont reproduits VERBATIM depuis la source officielle.
	'niveaux'                      => array(
		array(
			'cle'             => 'autorise',
			'libelle'         => 'Accès au massif autorisé',
			'consigne'        => '',
			'severite'        => 10,
			'motif'           => 'aucun',
			'jeton_css'       => '--statut-autorise',
			'jeton_encre_css' => '--statut-autorise-encre',
		),
		array(
			'cle'             => 'interdit',
			'libelle'         => 'Accès au massif interdit',
			'consigne'        => '',
			'severite'        => 20,
			'motif'           => 'hachure_croisee',
			'jeton_css'       => '--statut-interdit',
			'jeton_encre_css' => '--statut-interdit-encre',
		),
	),

	// ZAPEF — seconde dimension officiellement publiée. Ce sont des POINTS
	// (marqueurs d'accueil du public en forêt), pas des surfaces : la dimension
	// est distincte de l'accès au massif et ne s'y substitue jamais.
	//
	// L'ACCORD FAUTIF EST CELUI DE LA SOURCE ET DOIT ÊTRE REPRODUIT : `autorisé`
	// au masculin, `interdite` au féminin. C'est ce que publie la préfecture ; le
	// §4.2 du brief impose de reproduire, pas de corriger.
	//
	// Les motifs sont ceux de `design-system/MASTER.md` v2.0 §4.1.b : un jalon
	// ZAPEF n'a pas la même forme qu'un polygone de massif, il ne porte donc pas
	// le même motif que le niveau de même sévérité. L'information ne repose
	// JAMAIS sur la couleur seule, jalon compris.
	'zapef'                        => array(
		array(
			'cle'             => 'autorise',
			'libelle'         => 'Accès à la ZAPEF* autorisé',
			'consigne'        => '',
			'severite'        => 10,
			'motif'           => 'aucun',
			'jeton_css'       => '--statut-zapef-autorise',
			'jeton_encre_css' => '--statut-zapef-autorise-encre',
		),
		array(
			'cle'             => 'interdit',
			'libelle'         => 'Accès à la ZAPEF* interdite',
			'consigne'        => '',
			'severite'        => 20,
			'motif'           => 'barre',
			'jeton_css'       => '--statut-zapef-interdit',
			'jeton_encre_css' => '--statut-zapef-interdit-encre',
		),
	),

	// Note de bas de légende, verbatim. L'apostrophe de « d’Accueil » est
	// TYPOGRAPHIQUE (U+2019) dans la source, là où les autres chaînes emploient
	// l'apostrophe droite. Ne pas l'uniformiser.
	'zapef_note'                   => '*ZAPEF : Zones d’Accueil du Public en Forêt',

	// TABLE DE CORRESPONDANCE `level` BRUT → ENTRÉES AFFICHÉES.
	//
	// SEULE table à modifier si le propriétaire arbitre un jour en faveur d'une
	// granularité plus fine : le `level` brut restant persisté ligne à ligne, les
	// lignes passées se re-projettent alors sans perte.
	//
	// `level` 0 signifie « la source a publié qu'elle n'a pas d'information » —
	// JAMAIS « autorisé par défaut ». D'où les deux `null`, qui résolvent l'état
	// en `indisponible`.
	//
	// La source, elle, peint la ZAPEF en vert dès `level >= 0`, donc y compris
	// quand elle n'a aucune donnée. Nous ne reproduisons PAS ce comportement :
	// afficher « ouverte » à `level` 0 présenterait comme une information ce qui
	// est une absence d'information. Nous reproduisons la légende officielle,
	// pas les défauts de rendu de la source.
	'correspondance_source'        => array(
		0 => array(
			'niveau_cle' => null,
			'zapef_cle'  => null,
		),
		1 => array(
			'niveau_cle' => 'autorise',
			'zapef_cle'  => 'autorise',
		),
		2 => array(
			'niveau_cle' => 'autorise',
			'zapef_cle'  => 'autorise',
		),
		3 => array(
			'niveau_cle' => 'interdit',
			'zapef_cle'  => 'autorise',
		),
		4 => array(
			'niveau_cle' => 'interdit',
			'zapef_cle'  => 'interdit',
		),
	),

	// Listes blanches des valeurs BRUTES admises en entrée. Elles vivent ici, en
	// configuration versionnée, et jamais en constantes dans le code de
	// l'ingestion : c'est la couche sémantique du connecteur qui vient les lire.
	'niveaux_source_autorises'     => array( 0, 1, 2, 3, 4 ),
	'procedures_source_autorisees' => array( 0, 1 ),

	// Structure seulement : aucune phrase destinée au visiteur ne vient du serveur.
	// Les libellés de ces trois états appartiennent au thème (MASTER.md §11.3).
	//
	// CE NE SONT PAS DES NIVEAUX, ce sont des ABSENCES D'INFORMATION. Ils n'ont ni
	// libellé ni couleur officiels : ils sont à nous, et c'est pourquoi les nommer
	// n'invente aucun fait de domaine.
	//
	// Chaque état porte SON PROPRE jeton d'aplat, distinct des deux autres, même
	// si `tokens.css` les fait aujourd'hui pointer sur la même surface calcaire.
	// Trois états partageant un seul jeton rendraient impossible de les
	// différencier plus tard sans repasser par l'extension. Le motif, lui, les
	// distingue dès aujourd'hui — correspondance état → motif imposée par
	// MASTER.md v2.0 §4.1.c, jamais devinée :
	//
	//   indisponible      → hachure descendante
	//   hors_saison       → aucun motif (aplat nu)
	//   non_encore_publie → pointillé
	//
	// `jeton_encre_css` est l'encre DU MOTIF, jamais une encre de texte : aucun
	// texte n'est posé sur un aplat de statut (MASTER.md §4.1.d règle 3). Il est
	// déclaré même pour `hors_saison`, dont le motif est `aucun` : le jeton existe
	// dans `tokens.css`, et une clé absente obligerait le consommateur à un cas
	// particulier là où la forme doit rester uniforme.
	'etats_hors_niveau'            => array(
		'indisponible'      => array(
			'cle'             => 'indisponible',
			'motif'           => 'hachure_descendante',
			'jeton_css'       => '--statut-indisponible',
			'jeton_encre_css' => '--statut-indisponible-encre',
		),
		'hors_saison'       => array(
			'cle'             => 'hors_saison',
			'motif'           => 'aucun',
			'jeton_css'       => '--statut-hors-saison',
			'jeton_encre_css' => '--statut-hors-saison-encre',
		),
		'non_encore_publie' => array(
			'cle'             => 'non_encore_publie',
			'motif'           => 'pointille',
			'jeton_css'       => '--statut-non-publie',
			'jeton_encre_css' => '--statut-non-publie-encre',
		),
	),
);
