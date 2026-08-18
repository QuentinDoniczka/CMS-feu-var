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
	'genere_le'   => '2026-08-17T22:53:48Z',
	'mode'        => 'complet',
	'pyramide'    => array(
		'version'      => 'fff4ef0f',
		'sha256'       => 'fff4ef0fb38d28f663d3575b3f39f6dcf402a5770d3accb3c3904655bf28f0e1',
		'octets'       => 635180,
		'nombre'       => 295,
		'zoom_min'     => 5,
		'zoom_max'     => 12,
		'taille_tuile' => 256,
		'format'       => 'png',
		'bbox'         => array(
			'ouest' => 4.5703125,
			'sud'   => 43.06888777416962,
			'est'   => 5.888671875,
			'nord'  => 43.96119063892025,
		),
	),
	'statique'    => array(
		'version'          => 'fff4ef0f',
		'sha256'           => 'cf6f122ffd7d54609ca017894ba49baaed3abf7d5517fc4f757a30d07a1ef88f',
		'octets'           => 109050,
		'largeur'          => 1600,
		'hauteur'          => 1493,
		'contours_massifs' => 25,
	),
	'attribution' => array(
		'phrase'       => '© les contributeurs d\'OpenStreetMap',
		'lien_licence' => 'https://www.openstreetmap.org/copyright',
		'faits'        => array(
			'canal'           => 'Overpass API',
			'canal_url'       => 'https://overpass-api.de/',
			'extrait_le'      => '2026-08-17',
			'licence_nom'     => 'Open Database License',
			'licence_version' => '1.0',
			'licence_url'     => 'https://opendatacommons.org/licenses/odbl/1-0/',
			'rendu'           => 'monochrome, cuit à la génération',
		),
	),
);
