<?php
/**
 * Surface publique du danger météo : la fonction `massifs_*()`.
 *
 * UNE fonction, et une seule (contrat #10 §1). Seule celle-ci est publique ; les
 * classes du namespace `Massifs\Ingest\Meteo\` sont l'implémentation et le thème
 * n'en nomme aucune. Elle est gardée par `function_exists()` pour qu'une double
 * inclusion reste sans effet.
 *
 * `massifs_danger_meteo()`, `massifs_attribution_meteo()`,
 * `massifs_meteo_disponible()` et `massifs_meteo_niveau()` ne sont PAS créées :
 * `etat`, `niveau` et `attribution` sont déjà des clés du retour, et une seconde
 * manière de poser la même question est une divergence en attente.
 *
 * Le retour est BRUT, non échappé. Toute valeur d'origine tierce qui en sort —
 * `niveau.libelle` au premier chef — doit être échappée par le consommateur
 * (`esc_html`), jamais rendue telle quelle.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_meteo_du_jour' ) ) {
	/**
	 * Danger météo des forêts pour un jour donné.
	 *
	 * TOTALE : aucune exception, aucun `null`, aucun `false`, aucun `WP_Error`,
	 * et toutes les clés du contrat toujours présentes. Un `$jour` malformé rend
	 * `etat = 'indisponible'` et `jour = ''` — il ne lève pas.
	 *
	 * `jour` est TOUJOURS le jour DEMANDÉ. `niveau` vaut `null` LITTÉRAL hors de
	 * `etat === 'disponible'` — jamais `array()`, jamais une clé vide.
	 * `echelle.crans` à zéro signifie « aucune échelle n'est affichable », jamais
	 * « échelle vide à dessiner en attendant ».
	 *
	 * @param string|null $jour Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array{jour:string,etat:string,niveau:array{cle:string,libelle:string}|null,echelle:array{crans:int,atteint:int,confirmee:bool,phrase:string},zone:array{cle:string,libelle:string,granularite:string},releve_le:string|null,publie_le:string|null,distinction:string,attribution:array{texte:string,lien_licence:string,lien_source:string}}
	 */
	function massifs_meteo_du_jour( ?string $jour = null ): array {
		return \Massifs\Ingest\Meteo\Lecture::du_jour( $jour );
	}
}
