<?php
/**
 * Emprise cartographique et artefact géométrique.
 *
 * PHP n'ouvre JAMAIS `data/massifs-13.geometrie.json` : ni `file_get_contents`,
 * ni `json_decode`, ni `filesize`, ni `hash_file`, ni `filemtime`. Taille,
 * empreinte et jeton de version viennent du build, écrits dans les métadonnées.
 * Le fichier est servi en statique par le serveur web, sans amorçage WordPress.
 * `disponible` atteste donc la présence des MÉTADONNÉES, jamais celle du
 * fichier : le front dégrade vers la liste textuelle si la requête échoue.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Domain\Massifs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/referentiel.php';

/**
 * Emprise de la couche massifs.
 *
 * Leaflet attend `[[sud, ouest], [nord, est]]` : la conversion appartient au
 * front. Aucune coordonnée n'est codée en dur côté présentation.
 *
 * @return array{bbox:?array,centre:?array,zoom_max:int}
 */
function emprise(): array {
	$emprise = bloc( donnees()['meta'], 'emprise' );
	$bbox    = normaliser_bbox( isset( $emprise['bbox'] ) ? $emprise['bbox'] : null );
	$centre  = normaliser_centre( isset( $emprise['centre'] ) ? $emprise['centre'] : null );

	return array(
		'bbox'     => is_array( $bbox ) ? $bbox : null,
		'centre'   => is_array( $centre ) ? $centre : null,
		'zoom_max' => entier( $emprise, 'zoom_max', ZOOM_MAX_DEFAUT ),
	);
}

/**
 * Métadonnées de l'artefact géométrique statique.
 *
 * @return array{disponible:bool,url:string,version:string,sha256:string,octets:int,format:string,zoom_max:int}
 */
function geometrie(): array {
	$meta     = bloc( donnees()['meta'], 'geometrie' );
	$version  = texte( $meta, 'version' );
	$sha256   = texte( $meta, 'sha256' );
	$octets   = entier( $meta, 'octets' );
	$zoom_max = entier( $meta, 'zoom_max', ZOOM_MAX_DEFAUT );
	$url      = url_geometrie( $version );

	return array(
		'disponible' => '' !== $version && '' !== $sha256 && $octets > 0 && '' !== $url,
		'url'        => $url,
		'version'    => $version,
		'sha256'     => $sha256,
		'octets'     => $octets,
		'format'     => texte( $meta, 'format', FORMAT_GEOMETRIE_DEFAUT ),
		'zoom_max'   => $zoom_max,
	);
}

/**
 * URL publique de l'artefact, avec jeton de cache-busting.
 *
 * Construite à partir du chemin du fichier lui-même : ne dépend ni du nom du
 * fichier principal de l'extension, ni d'une constante appartenant à une autre
 * chaîne. Hors WordPress, on renvoie une chaîne vide plutôt que de fataler.
 *
 * @param string $version Jeton de version (8 hexadécimaux du sha256).
 * @return string
 */
function url_geometrie( string $version ): string {
	if ( ! function_exists( 'plugins_url' ) || '' === $version ) {
		return '';
	}

	$fichier = chemin_geometrie();

	return plugins_url( basename( $fichier ), $fichier ) . '?v=' . $version;
}
