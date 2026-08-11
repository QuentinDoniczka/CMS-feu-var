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
