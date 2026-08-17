<?php
/**
 * Fond de carte auto-hébergé — métadonnées.
 *
 * FICHIER GÉNÉRÉ — NE PAS ÉDITER À LA MAIN.
 * Produit par `includes/ingest/tuiles/build/construire.mjs` (npm run construire)
 * à partir de l'archive OpenStreetMap commitée sous `build/source/`.
 *
 * Ce fichier ne s'ouvre pas directement : il se lit par les fonctions
 * `massifs_fond_de_carte()`, `massifs_fond_de_carte_statique()` et
 * `massifs_attribution_fond_de_carte()`. Il ne contient aucune coordonnée de
 * tuile : les octets vivent à côté, servis en statique, sans amorçage WordPress.
 *
 * `pyramide.version` est un segment de CHEMIN, jamais une query : c'est ce qui
 * rend la mise en cache `immutable` honnête.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/*
 * Garde volontairement SANS `exit` : hors WordPress, le fichier retourne un
 * tableau vide au lieu d'interrompre le processus. C'est ce qui permet à la
 * recette de build de le lire (`php -r` avec MASSIFS_VERIFICATION) sans amorcer
 * WordPress. Ne pas « corriger » en `exit`.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'MASSIFS_VERIFICATION' ) ) {
	return array();
}

return array(
	'schema'      => 1,
	'genere_le'   => '2026-08-17T08:53:00Z',
	'mode'        => 'complet',
	'pyramide'    => array(
		'version'      => '926dab38',
		'sha256'       => '926dab383762e68434643f4d7c8fc9ea2636b586386f330a5e4ca8215e231665',
		'octets'       => 546780,
		'nombre'       => 280,
		'zoom_min'     => 5,
		'zoom_max'     => 12,
		'taille_tuile' => 256,
		'format'       => 'png',
		'bbox'         => array(
			'ouest' => 4.5703125,
			'sud'   => 43.133061162406136,
			'est'   => 5.888671875,
			'nord'  => 43.96119063892025,
		),
	),
	'statique'    => array(
		'version'          => '926dab38',
		'sha256'           => '93d19dcc611178a8197f8629e13bb47c9e49f75995ae66a3ace8889713986474',
		'octets'           => 120768,
		'largeur'          => 1600,
		'hauteur'          => 1421,
		'contours_massifs' => 25,
	),
	'attribution' => array(
		'phrase'       => '© les contributeurs d\'OpenStreetMap',
		'lien_licence' => 'https://www.openstreetmap.org/copyright',
		'faits'        => array(
			'canal'           => 'Overpass API',
			'canal_url'       => 'https://overpass-api.de/',
			'extrait_le'      => '2026-08-13',
			'licence_nom'     => 'Open Database License',
			'licence_version' => '1.0',
			'licence_url'     => 'https://opendatacommons.org/licenses/odbl/1-0/',
			'rendu'           => 'monochrome, cuit à la génération',
		),
	),
);
