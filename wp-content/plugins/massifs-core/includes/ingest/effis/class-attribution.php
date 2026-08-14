<?php
/**
 * Mention de source de la couche.
 *
 * La phrase est celle du §9 du brief, VERBATIM. Elle se rend ENTIÈRE : jamais
 * abrégée, jamais reformulée, jamais coupée en « Copernicus / EFFIS », jamais
 * complétée.
 *
 * IL N'EXISTE AUCUNE CLÉ `lien_licence`, et c'est une décision : le §9 du brief
 * impose cette phrase SANS URL, contrairement à celle du fond de carte. Geler
 * une chaîne vide dans un contrat a déjà été écarté une fois sur ce projet.
 * Conséquence liante pour le thème : l'attribution se rend en TEXTE NU, jamais
 * dans un `<a>` — il n'y a aucune destination à décrire.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribution de la source de la couche.
 */
final class Attribution {

	/**
	 * Phrase d'attribution, §9 du brief, verbatim.
	 */
	public const PHRASE = '© Union européenne, Copernicus Emergency Management Service / EFFIS';

	/**
	 * Attribution complète.
	 *
	 * `faits` est conservée pour la page « La démarche » (§5.1 et §9 du brief),
	 * qui doit documenter sources et limites. `connecteur` rend la portée
	 * simulée auditable en production. Aucun autre consommateur n'est autorisé
	 * à s'y ajouter sans révision du contrat.
	 *
	 * `couche` vaut la chaîne vide : le nom exact de la couche source n'a
	 * jamais été relevé, et le libellé du brief est cité dans le README du
	 * module comme nom de source, jamais comme donnée publiée.
	 *
	 * TEXTE BRUT, jamais du HTML, jamais une entité pré-échappée : le thème
	 * échappe en sortie.
	 *
	 * @return array{phrase:string,faits:array<string,string>}
	 */
	public static function attribution(): array {
		return array(
			'phrase' => self::PHRASE,
			'faits'  => array(
				'producteur'          => 'Copernicus Emergency Management Service',
				'service'             => 'European Forest Fire Information System (EFFIS)',
				'couche'              => '',
				'methode'             => 'estimation satellite',
				'fenetre_jours'       => (string) Settings::fenetre_jours(),
				'surface_minimale_ha' => (string) Settings::surface_minimale_ha(),
				'frequence_par_jour'  => (string) Settings::frequence_par_jour(),
				'connecteur'          => Settings::connecteur(),
			),
		);
	}
}
