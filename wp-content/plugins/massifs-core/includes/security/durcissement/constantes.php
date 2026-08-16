<?php
/**
 * Constantes de durcissement.
 *
 * POURQUOI UN FICHIER SÉPARÉ DE L'AMORCE
 *
 * Le contrat d'amorce de module (`massifs-core.php`, l. 116-120) est explicite :
 * une amorce ne déclare QUE des crochets et des `require_once` de ses propres
 * fichiers. Un `define()` n'est pas un crochet. Il vit donc ici, et l'amorce le
 * charge en première ligne — avant toute évaluation de `map_meta_cap`, dont la
 * plus précoce est `admin_menu`.
 *
 * DOMICILE DURABLE : `wp-config.php`, hors empreinte de cette chaîne (couture S-4
 * du contrat #16, déjà inscrite au contrat #13 l. 406). Le `define()` ci-dessous
 * est le doublon défensif qui tient tant que la couture n'est pas posée : la garde
 * `defined()` lui interdit d'écraser une valeur déjà décidée en amont, y compris
 * un `false` délibéré d'exploitant.
 *
 * `DISALLOW_FILE_MODS` EST DÉLIBÉRÉMENT ABSENT (arbitrage A-6) : il désactive
 * `WP_Automatic_Updater::is_disabled()` en entier et tuerait les mises à jour
 * mineures automatiques exigées par la même issue. Ne pas l'ajouter « pour faire
 * bonne mesure ».
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
