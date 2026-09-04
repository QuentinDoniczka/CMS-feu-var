<?php
/**
 * Source UNIQUE des bornes calendaires du dispositif estival.
 *
 * POURQUOI CE FICHIER VIT DANS `domain/fraicheur` ET PAS DANS `domain/statuts`
 *
 * `fraicheur` est la couche calendrier et horloge sur laquelle `statuts` se
 * construit. Lire les bornes DEPUIS la légende faisait donc remonter `fraicheur`
 * vers son propre consommateur — une inversion de couches, pas un cycle
 * symétrique. Masquer `domain/statuts` rendait alors 500 sur tout le site
 * (issue #94). Les bornes descendent ici : l'arête disparaît, et `fraicheur`
 * tient debout seul.
 *
 * Le commentaire de provenance ci-dessous est reproduit VERBATIM depuis son
 * emplacement d'origine, sans reformulation ni reflow. Séparer un fait de
 * terrain de sa provenance transformerait un déplacement en invention, ce que la
 * règle en tête de `docs/BRIEF.md` interdit.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// 1er juin – 30 septembre INCLUS. Établi par le titre officiel de la carte,
	// le texte de la page, et le comportement du flux lui-même.
	'confirme'   => true,
	'debut_mois' => 6,
	'debut_jour' => 1,
	'fin_mois'   => 9,
	'fin_jour'   => 30,
);
