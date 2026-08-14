<?php
/**
 * Source UNIQUE du vocabulaire de l'échelle de danger météo des forêts.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  AUCUN CRAN N'EST SOURCÉ. LA TABLE EST VIDE, ET C'EST UN FAIT RELEVÉ,    │
 * │  PAS UNE DONNÉE MANQUANTE À COMBLER.                                     │
 * │                                                                          │
 * │  `design-system/MASTER.md` §8.6 et §11.2 affirment une échelle graduée   │
 * │  dont ils donnent la cardinalité. Ce nombre n'est sourcé NULLE PART      │
 * │  dans le dépôt : ni relevé, ni cité, ni rattaché à une publication de    │
 * │  Météo-France. La v1.0 du MÊME document portait déjà une échelle         │
 * │  graduée inventée pour les statuts d'accès ; elle a été détruite en      │
 * │  v2.0 par une décision sourcée, et la légende réelle du 13 s'est         │
 * │  révélée binaire (voir `includes/domain/statuts/legende.config.php`).    │
 * │  La seule échelle graduée réellement relevée dans le dépôt appartient    │
 * │  à d'autres départements et n'a pas la même cardinalité.                 │
 * │                                                                          │
 * │  `docs/decisions/portee-non-publiee.md` §3 tranche le reste : « un       │
 * │  libellé officiel, un seuil, une couleur réglementaire ne se déduisent   │
 * │  pas d'un bouchon ». Un bouchon bavard qui porterait un libellé et une   │
 * │  cardinalité ne peut donc rien ouvrir ici.                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * CE QUE BASCULER `confirme` À `true` NE FAIT PAS
 *
 * Rien. `Vocabulaire::est_confirme()` exige en plus une table de crans non
 * vide, chaque cran portant `cle`, `libelle` et `rang` valides, des rangs
 * distincts et contigus depuis 1, ET une correspondance source → cran non vide
 * qui ne pointe que sur des crans existants. Le booléen seul n'ouvre rien : il
 * ne peut donc pas servir de raccourci pour afficher une échelle sans en
 * fournir un seul libellé.
 *
 * Le filtre `massifs_meteo_vocabulaire` subit EXACTEMENT la même validation,
 * appliquée APRÈS lui. Aucune constante d'ouverture n'existe, et il ne faut
 * pas en créer : elle permettrait d'ouvrir la garde sans fournir un libellé.
 *
 * COMMENT CE FICHIER S'OUVRE, LE JOUR VENU
 *
 * Par une source ÉCRITE du propriétaire du projet donnant, verbatim, le
 * libellé officiel de chaque cran et l'ordre de l'échelle. On remplit alors
 * `crans` et `correspondance_source`, on renseigne `source` et `revision`, et
 * on bascule `confirme`. Aucune autre modification du module n'est nécessaire :
 * la couche de lecture lit sa cardinalité ici et nulle part ailleurs.
 *
 * AUCUNE VALEUR HEXADÉCIMALE ICI, NI NULLE PART DANS L'EXTENSION. Le danger
 * météo ne porte d'ailleurs aucune couleur dans ce projet : le contrat
 * l'interdit explicitement, pour qu'il ne soit jamais confondu avec un statut
 * d'accès au massif.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Faux tant qu'aucune source écrite ne donne les libellés officiels.
	// Le basculer sans remplir `crans` et `correspondance_source` n'a aucun
	// effet : voir l'en-tête, et `Vocabulaire::est_confirme()`.
	'confirme'              => false,

	'revision'              => '0-vocabulaire-non-source',

	// Vide, et non « à confirmer » : rien n'a été relevé, il n'y a donc rien à
	// citer. Une phrase de provenance inventée ferait passer une absence pour
	// une piste.
	'source'                => '',

	// TABLE DES CRANS — liste ORDONNÉE PAR RANG CROISSANT.
	//
	// Forme attendue de chaque entrée, le jour où elle sera remplie :
	//
	//     array(
	//         'cle'     => 'cle_stable_minuscule',   // persistée telle quelle
	//         'libelle' => 'Libellé officiel verbatim',
	//         'rang'    => 1,                        // contigu depuis 1
	//     )
	//
	// `cle` est une clé TEXTE stable, jamais un entier de position : le jour
	// où l'échelle change de granularité, un entier ferait changer de sens
	// toutes les valeurs déjà persistées.
	//
	// `rang` reste INTERNE à ce fichier. Il ne traverse pas la frontière du
	// contrat : c'est `echelle.atteint` qui porte la valeur côté lecture.
	'crans'                 => array(),

	// TABLE DE CORRESPONDANCE valeur brute de la source → `cle` de cran.
	//
	// Séparée de la table des crans à dessein : le jeton émis par la source et
	// le cran affiché sont deux vocabulaires distincts, et les confondre est
	// exactement la faute que la chaîne préfecture a documentée pour son
	// référentiel. Vide tant que le format réel de la source est inconnu.
	'correspondance_source' => array(),
);
