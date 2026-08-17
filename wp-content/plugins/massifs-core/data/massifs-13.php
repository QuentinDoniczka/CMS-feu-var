<?php
/**
 * Référentiel des massifs forestiers des Bouches-du-Rhône — données.
 *
 * FICHIER GÉNÉRÉ — NE PAS ÉDITER À LA MAIN.
 * Produit par `includes/domain/massifs/build/importer.mjs` (npm run importer)
 * à partir de la source open data archivée dans `build/source/`.
 *
 * Politique de ré-import, en une phrase : l'import peut mettre à jour une
 * géométrie automatiquement ; il ne peut jamais créer, supprimer, renommer ni
 * re-lier une identité sans décision humaine.
 *
 * Procédure cas par cas : `includes/domain/massifs/README.md`.
 * Identités gelées, éditées à la main : `build/identites.json`.
 *
 * Ce fichier ne s'ouvre pas directement : il se lit par les fonctions
 * `massifs_*()` du module. Il ne contient aucune coordonnée de géométrie —
 * celles-ci vivent dans `massifs-13.geometrie.json`, servi en statique.
 *
 * `source.identifiant_prefecture` et le bloc `correspondance_source` portent la
 * correspondance GELÉE entre nos codes et les identifiants du flux journalier de
 * la préfecture. Elle est recopiée depuis `build/identites.json`, où elle a été
 * vérifiée à la main : elle ne se DÉDUIT JAMAIS de `source.gid`, qui n'est qu'un
 * rang alphabétique et se renumérote à la moindre insertion. Les identifiants
 * listés dans `source.flux_identifiants_sans_correspondance` sont en surnombre :
 * le flux les porte, aucune publication officielle ne les nomme, ils n'ont donc
 * volontairement aucun massif.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/*
 * Garde volontairement SANS `exit` : hors WordPress, le fichier retourne un
 * tableau vide au lieu d'interrompre le processus. C'est ce qui permet au
 * vérificateur de build de le lire (`php -d… -r` avec MASSIFS_VERIFICATION)
 * sans amorcer WordPress. Ne pas « corriger » en `exit`.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'MASSIFS_VERIFICATION' ) ) {
	return array();
}

return array(
	'schema'                => 2,
	'genere_le'             => '2026-08-17T07:54:55Z',
	'source'                => array(
		'producteur'                                 => 'DDTM des Bouches-du-Rhône',
		'jeu_de_donnees'                             => 'Massifs forestiers dans les Bouches-du-Rhône',
		'couche'                                     => 'L_MASSIFS_FORESTIERS_S_013',
		'dataset_id'                                 => '67373dd6495f49af65c40b88',
		'geoide_id'                                  => 'd2ab6ef7-9839-4e03-a4db-bdbc272a5a69',
		'dataset_url'                                => 'https://www.data.gouv.fr/datasets/massifs-forestiers-dans-les-bouches-du-rhone',
		'donnees_du'                                 => '2023-02-14',
		'donnees_du_libelle'                         => '14 février 2023',
		'recupere_le'                                => '2026-08-11',
		'sha256'                                     => 'd0316cbcf4693f7fe2d7bb663d9633c93aa9902fcdfeb871498edfecabe61394',
		'crs_source'                                 => 'EPSG:2154',
		'crs_publie'                                 => 'EPSG:4326',
		'base_reglementaire'                         => 'Arrêté préfectoral n° 13-2018-05-28-005 du 28 mai 2018',
		'dispositif'                                 => array(
			'debut' => '06-01',
			'fin'   => '09-30',
		),
		'flux_identifiants_total'                    => 27,
		'flux_identifiants_sans_correspondance'      => array( '1326', '1327' ),
		'flux_identifiants_sans_correspondance_note' => 'En surnombre dans le flux : aucune publication officielle ne les nomme, ils n\'ont donc volontairement aucun massif. Aucun nom n\'est inventé.',
		'archive'                                    => array(
			'fichier' => 'includes/domain/massifs/build/source/massifs-13.full.geojson',
			'sha256'  => 'd0316cbcf4693f7fe2d7bb663d9633c93aa9902fcdfeb871498edfecabe61394',
			'octets'  => 3022441,
		),
	),
	'licence'               => array(
		'nom'         => 'Licence Ouverte',
		'version'     => '2.0',
		'identifiant' => 'etalab-2.0',
		'url'         => 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
	),
	'attribution'           => array(
		'phrase'        => 'Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du 14 février 2023',
		'phrase_courte' => 'DDTM 13 / data.gouv.fr — Licence Ouverte 2.0',
		'lien_source'   => 'https://www.data.gouv.fr/datasets/massifs-forestiers-dans-les-bouches-du-rhone',
		'lien_licence'  => 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
	),
	'geometrie'             => array(
		'fichier'             => 'massifs-13.geometrie.json',
		'version'             => '97f48258',
		'sha256'              => '97f482581eea0efc6764e9ff96a427e33b70edbbe2cad1f274936800e9c062e3',
		'octets'              => 193137,
		'format'              => 'geojson',
		'zoom_max'            => 11,
		'algorithme'          => 'douglas-peucker',
		'tolerance_m'         => 90,
		'precision_decimales' => 4,
	),
	'emprise'               => array(
		'bbox'     => array(
			'ouest' => 4.65642,
			'sud'   => 43.15731,
			'est'   => 5.81325,
			'nord'  => 43.90238,
		),
		'centre'   => array(
			'lon' => 5.23484,
			'lat' => 43.52985,
		),
		'zoom_max' => 11,
	),
	'lacunes'               => array(
		'communes' => array(
			'statut'            => 'inconnue',
			'raison'            => 'aucun attribut de commune dans la couche L_MASSIFS_FORESTIERS_S_013',
			'source_pressentie' => 'IGN ADMIN EXPRESS',
		),
	),
	'correspondance_source' => array(
		'alpilles'             => '131',
		'arbois'               => '132',
		'calanques'            => '133',
		'cap-canaille'         => '134',
		'castillon'            => '135',
		'chaine-des-cotes'     => '136',
		'chambremont'          => '137',
		'collines-de-gardanne' => '138',
		'concors'              => '139',
		'cote-bleue'           => '1310',
		'etoile'               => '1311',
		'garlaban'             => '1312',
		'grand-caunet'         => '1313',
		'lancon'               => '1314',
		'les-roques'           => '1315',
		'montagnette'          => '1316',
		'montaiguet'           => '1317',
		'pont-de-rhaud'        => '1318',
		'quatre-termes'        => '1319',
		'regagnas'             => '1320',
		'rougadou'             => '1321',
		'sainte-baume'         => '1322',
		'sainte-victoire'      => '1323',
		'sulauze'              => '1324',
		'trevaresse'           => '1325',
	),
	'massifs'               => array(
		'alpilles'             => array(
			'code'            => 'alpilles',
			'libelle'         => 'Alpilles',
			'tri'             => 'alpilles',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.65642,
				'sud'   => 43.68515,
				'est'   => 5.09158,
				'nord'  => 43.81601,
			),
			'centre'          => array(
				'lon' => 4.88257,
				'lat' => 43.74477,
			),
			'source'          => array(
				'gid'                    => 1,
				'nom_massif'             => 'Alpilles',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '131',
			),
			'note_provenance' => null,
		),
		'arbois'               => array(
			'code'            => 'arbois',
			'libelle'         => 'Arbois',
			'tri'             => 'arbois',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.17773,
				'sud'   => 43.40398,
				'est'   => 5.40675,
				'nord'  => 43.55501,
			),
			'centre'          => array(
				'lon' => 5.30099,
				'lat' => 43.47751,
			),
			'source'          => array(
				'gid'                    => 2,
				'nom_massif'             => 'Arbois',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '132',
			),
			'note_provenance' => null,
		),
		'calanques'            => array(
			'code'            => 'calanques',
			'libelle'         => 'Calanques',
			'tri'             => 'calanques',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.34222,
				'sud'   => 43.19679,
				'est'   => 5.54193,
				'nord'  => 43.28547,
			),
			'centre'          => array(
				'lon' => 5.4564,
				'lat' => 43.23616,
			),
			'source'          => array(
				'gid'                    => 3,
				'nom_massif'             => 'Calanques',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '133',
			),
			'note_provenance' => null,
		),
		'cap-canaille'         => array(
			'code'            => 'cap-canaille',
			'libelle'         => 'Cap Canaille',
			'tri'             => 'cap-canaille',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.54508,
				'sud'   => 43.15731,
				'est'   => 5.62068,
				'nord'  => 43.23204,
			),
			'centre'          => array(
				'lon' => 5.57536,
				'lat' => 43.19526,
			),
			'source'          => array(
				'gid'                    => 4,
				'nom_massif'             => 'Cap Canaille',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '134',
			),
			'note_provenance' => null,
		),
		'castillon'            => array(
			'code'            => 'castillon',
			'libelle'         => 'Castillon',
			'tri'             => 'castillon',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.90397,
				'sud'   => 43.41047,
				'est'   => 5.06077,
				'nord'  => 43.50606,
			),
			'centre'          => array(
				'lon' => 4.95211,
				'lat' => 43.46252,
			),
			'source'          => array(
				'gid'                    => 5,
				'nom_massif'             => 'Castillon',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '135',
			),
			'note_provenance' => null,
		),
		'chaine-des-cotes'     => array(
			'code'            => 'chaine-des-cotes',
			'libelle'         => 'Chaîne des Côtes',
			'tri'             => 'chaine-des-cotes',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.19764,
				'sud'   => 43.65044,
				'est'   => 5.36145,
				'nord'  => 43.72187,
			),
			'centre'          => array(
				'lon' => 5.28363,
				'lat' => 43.69358,
			),
			'source'          => array(
				'gid'                    => 6,
				'nom_massif'             => 'Chaine des Cotes',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '136',
			),
			'note_provenance' => 'Diacritiques restaurés. Forme accentuée attestée par le bulletin journalier officiel de la préfecture des Bouches-du-Rhône et par la table des massifs de risque-prevention-incendie.fr/13 (consultés le 2026-08-11).',
		),
		'chambremont'          => array(
			'code'            => 'chambremont',
			'libelle'         => 'Chambremont',
			'tri'             => 'chambremont',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.71497,
				'sud'   => 43.65149,
				'est'   => 4.90842,
				'nord'  => 43.69209,
			),
			'centre'          => array(
				'lon' => 4.84088,
				'lat' => 43.67141,
			),
			'source'          => array(
				'gid'                    => 7,
				'nom_massif'             => 'Chambremont',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '137',
			),
			'note_provenance' => null,
		),
		'collines-de-gardanne' => array(
			'code'            => 'collines-de-gardanne',
			'libelle'         => 'Collines de Gardanne',
			'tri'             => 'collines-de-gardanne',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.4402,
				'sud'   => 43.4124,
				'est'   => 5.57832,
				'nord'  => 43.47353,
			),
			'centre'          => array(
				'lon' => 5.50753,
				'lat' => 43.44599,
			),
			'source'          => array(
				'gid'                    => 8,
				'nom_massif'             => 'Collines de Gardanne',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '138',
			),
			'note_provenance' => null,
		),
		'concors'              => array(
			'code'            => 'concors',
			'libelle'         => 'Concors',
			'tri'             => 'concors',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.45813,
				'sud'   => 43.53521,
				'est'   => 5.81325,
				'nord'  => 43.72443,
			),
			'centre'          => array(
				'lon' => 5.62801,
				'lat' => 43.61942,
			),
			'source'          => array(
				'gid'                    => 9,
				'nom_massif'             => 'Concors',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '139',
			),
			'note_provenance' => null,
		),
		'cote-bleue'           => array(
			'code'            => 'cote-bleue',
			'libelle'         => 'Cote Bleue',
			'tri'             => 'cote-bleue',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.00015,
				'sud'   => 43.32535,
				'est'   => 5.36308,
				'nord'  => 43.41314,
			),
			'centre'          => array(
				'lon' => 5.16505,
				'lat' => 43.3638,
			),
			'source'          => array(
				'gid'                    => 10,
				'nom_massif'             => 'Cote Bleue',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1310',
			),
			'note_provenance' => null,
		),
		'etoile'               => array(
			'code'            => 'etoile',
			'libelle'         => 'Etoile',
			'tri'             => 'etoile',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.3515,
				'sud'   => 43.34616,
				'est'   => 5.55947,
				'nord'  => 43.43012,
			),
			'centre'          => array(
				'lon' => 5.45006,
				'lat' => 43.3909,
			),
			'source'          => array(
				'gid'                    => 11,
				'nom_massif'             => 'Etoile',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1311',
			),
			'note_provenance' => null,
		),
		'garlaban'             => array(
			'code'            => 'garlaban',
			'libelle'         => 'Garlaban',
			'tri'             => 'garlaban',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.46719,
				'sud'   => 43.28785,
				'est'   => 5.61986,
				'nord'  => 43.38577,
			),
			'centre'          => array(
				'lon' => 5.54416,
				'lat' => 43.34611,
			),
			'source'          => array(
				'gid'                    => 12,
				'nom_massif'             => 'Garlaban',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1312',
			),
			'note_provenance' => null,
		),
		'grand-caunet'         => array(
			'code'            => 'grand-caunet',
			'libelle'         => 'Grand Caunet',
			'tri'             => 'grand-caunet',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.50502,
				'sud'   => 43.17963,
				'est'   => 5.74911,
				'nord'  => 43.28773,
			),
			'centre'          => array(
				'lon' => 5.6442,
				'lat' => 43.24092,
			),
			'source'          => array(
				'gid'                    => 13,
				'nom_massif'             => 'Grand Caunet',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1313',
			),
			'note_provenance' => null,
		),
		'lancon'               => array(
			'code'            => 'lancon',
			'libelle'         => 'Lançon',
			'tri'             => 'lancon',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.03618,
				'sud'   => 43.52544,
				'est'   => 5.22916,
				'nord'  => 43.62406,
			),
			'centre'          => array(
				'lon' => 5.18685,
				'lat' => 43.56073,
			),
			'source'          => array(
				'gid'                    => 14,
				'nom_massif'             => 'Lançon',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1314',
			),
			'note_provenance' => null,
		),
		'les-roques'           => array(
			'code'            => 'les-roques',
			'libelle'         => 'Les Roques',
			'tri'             => 'les-roques',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.08975,
				'sud'   => 43.63504,
				'est'   => 5.25155,
				'nord'  => 43.7112,
			),
			'centre'          => array(
				'lon' => 5.15037,
				'lat' => 43.67342,
			),
			'source'          => array(
				'gid'                    => 15,
				'nom_massif'             => 'Les Roques',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1315',
			),
			'note_provenance' => null,
		),
		'montagnette'          => array(
			'code'            => 'montagnette',
			'libelle'         => 'Montagnette',
			'tri'             => 'montagnette',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.67986,
				'sud'   => 43.82306,
				'est'   => 4.77258,
				'nord'  => 43.90238,
			),
			'centre'          => array(
				'lon' => 4.72524,
				'lat' => 43.86509,
			),
			'source'          => array(
				'gid'                    => 16,
				'nom_massif'             => 'Montagnette',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1316',
			),
			'note_provenance' => null,
		),
		'montaiguet'           => array(
			'code'            => 'montaiguet',
			'libelle'         => 'Montaiguet',
			'tri'             => 'montaiguet',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.39997,
				'sud'   => 43.43929,
				'est'   => 5.53015,
				'nord'  => 43.50963,
			),
			'centre'          => array(
				'lon' => 5.45545,
				'lat' => 43.49339,
			),
			'source'          => array(
				'gid'                    => 17,
				'nom_massif'             => 'Montaiguet',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1317',
			),
			'note_provenance' => null,
		),
		'pont-de-rhaud'        => array(
			'code'            => 'pont-de-rhaud',
			'libelle'         => 'Pont de Rhaud',
			'tri'             => 'pont-de-rhaud',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.00622,
				'sud'   => 43.54852,
				'est'   => 5.10323,
				'nord'  => 43.62096,
			),
			'centre'          => array(
				'lon' => 5.06737,
				'lat' => 43.58627,
			),
			'source'          => array(
				'gid'                    => 18,
				'nom_massif'             => 'Pont de Rhaud',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1318',
			),
			'note_provenance' => null,
		),
		'quatre-termes'        => array(
			'code'            => 'quatre-termes',
			'libelle'         => 'Quatre Termes',
			'tri'             => 'quatre-termes',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.13561,
				'sud'   => 43.54777,
				'est'   => 5.40565,
				'nord'  => 43.65346,
			),
			'centre'          => array(
				'lon' => 5.24779,
				'lat' => 43.60124,
			),
			'source'          => array(
				'gid'                    => 19,
				'nom_massif'             => 'Quatre Termes',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1319',
			),
			'note_provenance' => null,
		),
		'regagnas'             => array(
			'code'            => 'regagnas',
			'libelle'         => 'Regagnas',
			'tri'             => 'regagnas',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.52737,
				'sud'   => 43.35357,
				'est'   => 5.78835,
				'nord'  => 43.46277,
			),
			'centre'          => array(
				'lon' => 5.64782,
				'lat' => 43.41826,
			),
			'source'          => array(
				'gid'                    => 20,
				'nom_massif'             => 'Regagnas',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1320',
			),
			'note_provenance' => null,
		),
		'rougadou'             => array(
			'code'            => 'rougadou',
			'libelle'         => 'Rougadou',
			'tri'             => 'rougadou',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.85492,
				'sud'   => 43.84439,
				'est'   => 4.8944,
				'nord'  => 43.88613,
			),
			'centre'          => array(
				'lon' => 4.88362,
				'lat' => 43.87311,
			),
			'source'          => array(
				'gid'                    => 21,
				'nom_massif'             => 'Rougadou',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1321',
			),
			'note_provenance' => null,
		),
		'sainte-baume'         => array(
			'code'            => 'sainte-baume',
			'libelle'         => 'Sainte-Baume',
			'tri'             => 'sainte-baume',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.59348,
				'sud'   => 43.26372,
				'est'   => 5.76305,
				'nord'  => 43.37706,
			),
			'centre'          => array(
				'lon' => 5.67752,
				'lat' => 43.31055,
			),
			'source'          => array(
				'gid'                    => 22,
				'nom_massif'             => 'Sainte-Baume',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1322',
			),
			'note_provenance' => null,
		),
		'sainte-victoire'      => array(
			'code'            => 'sainte-victoire',
			'libelle'         => 'Sainte-Victoire',
			'tri'             => 'sainte-victoire',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.46686,
				'sud'   => 43.47153,
				'est'   => 5.72567,
				'nord'  => 43.55695,
			),
			'centre'          => array(
				'lon' => 5.51977,
				'lat' => 43.5277,
			),
			'source'          => array(
				'gid'                    => 23,
				'nom_massif'             => 'Sainte-Victoire',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1323',
			),
			'note_provenance' => null,
		),
		'sulauze'              => array(
			'code'            => 'sulauze',
			'libelle'         => 'Sulauze',
			'tri'             => 'sulauze',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 4.97325,
				'sud'   => 43.51314,
				'est'   => 5.01593,
				'nord'  => 43.57476,
			),
			'centre'          => array(
				'lon' => 4.98736,
				'lat' => 43.54866,
			),
			'source'          => array(
				'gid'                    => 24,
				'nom_massif'             => 'Sulauze',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1324',
			),
			'note_provenance' => null,
		),
		'trevaresse'           => array(
			'code'            => 'trevaresse',
			'libelle'         => 'Trevaresse',
			'tri'             => 'trevaresse',
			'communes'        => array(),
			'communes_source' => 'inconnue',
			'actif'           => true,
			'retire_le'       => null,
			'bbox'            => array(
				'ouest' => 5.27004,
				'sud'   => 43.58623,
				'est'   => 5.49704,
				'nord'  => 43.70871,
			),
			'centre'          => array(
				'lon' => 5.3364,
				'lat' => 43.64209,
			),
			'source'          => array(
				'gid'                    => 25,
				'nom_massif'             => 'Trevaresse',
				'revision'               => '2023-02-14',
				'identifiant_prefecture' => '1325',
			),
			'note_provenance' => null,
		),
	),
);
