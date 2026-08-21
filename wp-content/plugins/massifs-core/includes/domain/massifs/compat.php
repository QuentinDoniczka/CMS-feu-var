<?php
/**
 * Surface publique du domaine « massifs » : les fonctions `massifs_*()`.
 *
 * Seules ces fonctions sont publiques ; les fonctions du namespace sont
 * l'implémentation. Chacune est gardée par `function_exists()` pour qu'une
 * double inclusion reste sans effet.
 *
 * Toutes sont TOTALES : aucune exception, aucun `WP_Error`, une valeur définie
 * même si le référentiel est absent ou corrompu, et toutes les clés du contrat
 * toujours présentes. Toutes retournent des données BRUTES, non échappées.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/referentiel.php';
require_once __DIR__ . '/geometrie.php';
require_once __DIR__ . '/attribution.php';
require_once __DIR__ . '/communes.php';

if ( ! function_exists( 'massifs_referentiel' ) ) {
	/**
	 * Référentiel complet, clé = code de massif, pré-trié par `tri`.
	 *
	 * @param bool $inclure_retires Inclure les massifs retirés.
	 * @return array<string,array> Vide si le référentiel est indisponible.
	 */
	function massifs_referentiel( bool $inclure_retires = false ): array {
		return \Massifs\Domain\Massifs\referentiel( $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_massif' ) ) {
	/**
	 * Une ligne de massif.
	 *
	 * @param string $code            Code de massif, passé tel quel, jamais normalisé.
	 * @param bool   $inclure_retires Inclure les massifs retirés.
	 * @return array|null Null si le code est inconnu.
	 */
	function massifs_massif( string $code, bool $inclure_retires = false ): ?array {
		return \Massifs\Domain\Massifs\massif( $code, $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_massif_existe' ) ) {
	/**
	 * Existence d'un code de massif.
	 *
	 * @param string $code            Code de massif.
	 * @param bool   $inclure_retires Inclure les massifs retirés.
	 * @return bool
	 */
	function massifs_massif_existe( string $code, bool $inclure_retires = false ): bool {
		return \Massifs\Domain\Massifs\existe( $code, $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_codes' ) ) {
	/**
	 * Liste des codes de massifs, dans l'ordre de tri.
	 *
	 * @param bool $inclure_retires Inclure les massifs retirés.
	 * @return list<string>
	 */
	function massifs_codes( bool $inclure_retires = false ): array {
		return \Massifs\Domain\Massifs\codes( $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_libelle' ) ) {
	/**
	 * Libellé affichable d'un massif.
	 *
	 * @param string $code Code de massif.
	 * @return string Le code lui-même s'il est inconnu.
	 */
	function massifs_libelle( string $code ): string {
		return \Massifs\Domain\Massifs\libelle( $code );
	}
}

if ( ! function_exists( 'massifs_libelles' ) ) {
	/**
	 * Table code => libellé, dans l'ordre de tri.
	 *
	 * @param bool $inclure_retires Inclure les massifs retirés.
	 * @return array<string,string>
	 */
	function massifs_libelles( bool $inclure_retires = false ): array {
		return \Massifs\Domain\Massifs\libelles( $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_compte' ) ) {
	/**
	 * Nombre de massifs.
	 *
	 * @param bool $inclure_retires Inclure les massifs retirés.
	 * @return int
	 */
	function massifs_compte( bool $inclure_retires = false ): int {
		return \Massifs\Domain\Massifs\compte( $inclure_retires );
	}
}

if ( ! function_exists( 'massifs_correspondance_source' ) ) {
	/**
	 * Correspondance gelée `massif_code` => `identifiant_source` du flux préfectoral.
	 *
	 * DONNÉE GELÉE, JAMAIS CALCULÉE : elle est recopiée depuis le registre
	 * d'identités, où elle a été vérifiée massif par massif contre la table
	 * officielle. Elle vaut aujourd'hui `13` + `source.gid`, et l'écrire ainsi
	 * serait un défaut : `gid` est un rang alphabétique qui se renumérote.
	 *
	 * 25 entrées. `1326` et `1327` en sont délibérément absents : le flux les
	 * porte, aucune publication officielle ne les nomme.
	 *
	 * @return array<string,string> Vide si le référentiel est indisponible.
	 */
	function massifs_correspondance_source(): array {
		return \Massifs\Domain\Massifs\correspondance_source();
	}
}

if ( ! function_exists( 'massifs_code_depuis_source' ) ) {
	/**
	 * Code de massif portant un identifiant du flux préfectoral.
	 *
	 * @param string $identifiant_source Identifiant du flux, passé tel quel, jamais normalisé.
	 * @return string|null Null si l'identifiant est inconnu ou en surnombre (`1326`, `1327`).
	 */
	function massifs_code_depuis_source( string $identifiant_source ): ?string {
		return \Massifs\Domain\Massifs\code_depuis_source( $identifiant_source );
	}
}

if ( ! function_exists( 'massifs_source_depuis_code' ) ) {
	/**
	 * Identifiant du flux préfectoral d'un massif.
	 *
	 * @param string $code Code de massif, passé tel quel, jamais normalisé.
	 * @return string|null Null si le code est inconnu.
	 */
	function massifs_source_depuis_code( string $code ): ?string {
		return \Massifs\Domain\Massifs\source_depuis_code( $code );
	}
}

if ( ! function_exists( 'massifs_emprise' ) ) {
	/**
	 * Emprise de la couche massifs et zoom maximal autorisé.
	 *
	 * @return array{bbox:?array,centre:?array,zoom_max:int}
	 */
	function massifs_emprise(): array {
		return \Massifs\Domain\Massifs\emprise();
	}
}

if ( ! function_exists( 'massifs_geometrie' ) ) {
	/**
	 * Métadonnées de l'artefact géométrique statique.
	 *
	 * @return array{disponible:bool,url:string,version:string,sha256:string,octets:int,format:string,zoom_max:int}
	 */
	function massifs_geometrie(): array {
		return \Massifs\Domain\Massifs\geometrie();
	}
}

if ( ! function_exists( 'massifs_attribution' ) ) {
	/**
	 * Mention de source §9 des périmètres.
	 *
	 * @return array{phrase:string,phrase_courte:string,lien_source:string,lien_licence:string,faits:array<string,string>}
	 */
	function massifs_attribution(): array {
		return \Massifs\Domain\Massifs\attribution();
	}
}

if ( ! function_exists( 'massifs_commune_de_la_zone' ) ) {
	/**
	 * Commune d'une zone de feu, ou l'absence explicite de commune.
	 *
	 * LECTURE PURE, sans hook et sans effet de bord, mais elle OUVRE un fichier :
	 * elle appartient au chemin cron (ingestion EFFIS) et jamais au chemin de
	 * rendu. L'ouverture est paresseuse — appeler n'importe quelle autre fonction
	 * du module n'ouvre rien.
	 *
	 * **Le seam est de forme géométrique, et ce n'est pas un détail de commodité.**
	 * Une signature par point serait structurellement incapable d'exprimer la
	 * règle : « la plus grande part de la zone » a besoin de la zone. Un relecteur
	 * qui déduit la règle de la signature doit y lire la bonne.
	 *
	 * Le retour est TOTAL : toutes les clés sont toujours présentes, y compris
	 * artefact absent. `nom` est le `nom_officiel` de l'archive IGN, verbatim et
	 * non échappé ; `distance_m` vaut 0 quand la zone chevauche la commune, sinon
	 * la distance entre la géométrie de la zone et le bord communal le plus
	 * proche, plafonnée à 5 000 mètres. Au-delà, ou hors de l'emprise couverte,
	 * rien n'est nommé.
	 *
	 * @param array $geometrie Géométrie GeoJSON de la zone, `Polygon` ou `MultiPolygon`.
	 * @return array{trouvee:bool,insee:string,nom:string,departement:string,distance_m:?int,etat:string}
	 */
	function massifs_commune_de_la_zone( array $geometrie ): array {
		return \Massifs\Domain\Massifs\commune_de_la_zone( $geometrie );
	}
}

if ( ! function_exists( 'massifs_commune_de_la_zone_nom' ) ) {
	/**
	 * Nom de la commune d'une zone de feu, ou chaîne vide.
	 *
	 * Commodité pour l'appelant qui ne veut que le nom, et qui doit traiter
	 * l'absence par le SILENCE : une chaîne vide se rend par l'omission propre de
	 * la paire, jamais par un tiret ni par « non renseigné ».
	 *
	 * @param array $geometrie Géométrie GeoJSON de la zone, `Polygon` ou `MultiPolygon`.
	 * @return string
	 */
	function massifs_commune_de_la_zone_nom( array $geometrie ): string {
		$commune = \Massifs\Domain\Massifs\commune_de_la_zone( $geometrie );

		return $commune['trouvee'] ? $commune['nom'] : '';
	}
}

if ( ! function_exists( 'massifs_attribution_communes' ) ) {
	/**
	 * Mention de source §9 du référentiel communal.
	 *
	 * SÉPARÉE de `massifs_attribution()`, qui porte la DDTM. Les deux ne
	 * fusionnent jamais : deux producteurs, deux licences, deux millésimes.
	 *
	 * @return array{phrase:string,phrase_courte:string,lien_source:string,lien_licence:string,faits:array<string,string>}
	 */
	function massifs_attribution_communes(): array {
		return \Massifs\Domain\Massifs\attribution_communes();
	}
}

if ( ! function_exists( 'massifs_lacunes' ) ) {
	/**
	 * Lacunes connues du référentiel.
	 *
	 * @return array{communes:array{statut:string,raison:string,source_pressentie:string}}
	 */
	function massifs_lacunes(): array {
		return \Massifs\Domain\Massifs\lacunes();
	}
}

if ( ! function_exists( 'massifs_referentiel_etat' ) ) {
	/**
	 * État de chargement du référentiel.
	 *
	 * @return array{disponible:bool,code:string,raison:?string,schema:int,genere_le:?string,nombre:int}
	 */
	function massifs_referentiel_etat(): array {
		return \Massifs\Domain\Massifs\etat();
	}
}

if ( ! function_exists( 'massifs_referentiel_disponible' ) ) {
	/**
	 * Le référentiel est-il exploitable ?
	 *
	 * @return bool
	 */
	function massifs_referentiel_disponible(): bool {
		return \Massifs\Domain\Massifs\etat()['disponible'];
	}
}
