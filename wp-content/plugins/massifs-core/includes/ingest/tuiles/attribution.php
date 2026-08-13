<?php
/**
 * Attribution §9 du fond de carte.
 *
 * La phrase est une DONNÉE, pas de la rédaction de thème : l'ODbL impose
 * d'attribuer OpenStreetMap, et trois consommateurs qui l'assembleraient chacun
 * de leur côté produiraient trois variantes, dont deux non conformes. Le thème
 * reçoit la phrase, la place et l'échappe.
 *
 * INTERDIT DE DÉCOUPE : le thème rend `phrase` ENTIÈRE comme texte du lien,
 * `<a href="{lien_licence}">{phrase}</a>`. Il ne la coupe pas, ne l'abrège pas,
 * ne la reformule pas, et n'invente aucun libellé de lien.
 *
 * `faits` n'est lu par aucun gabarit de l'issue #9, et c'est délibéré : la page
 * « La démarche » (§5.1 et §9 du brief) doit documenter sources et licences, et
 * rouvrir un contrat gelé pour obtenir des faits que le build connaît déjà serait
 * un coût inutile. Aucun autre consommateur ne s'y ajoute sans révision.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Tuiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/metadonnees.php';

/**
 * Mention de source à afficher sous la carte et en mentions légales.
 *
 * Les URLs sont brutes : c'est le thème qui les passe à `esc_url()`.
 *
 * @return array{phrase:string,lien_licence:string,faits:array<string,string>}
 */
function attribution(): array {
	return donnees()['attribution'];
}
