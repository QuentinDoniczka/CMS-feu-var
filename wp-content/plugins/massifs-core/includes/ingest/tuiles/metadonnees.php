<?php
/**
 * Lecture et validation des métadonnées du fond de carte.
 *
 * Le module est en LECTURE SEULE et sans effet de bord : aucun hook, aucun
 * filtre, aucune table, aucune option, aucun transient, aucun cron, aucune route
 * REST, aucun écran, aucune sortie. Le charger ne fait rien d'observable ; ne pas
 * le charger n'est pas fatal.
 *
 * PHP N'OUVRE JAMAIS UNE TUILE, NI L'IMAGE STATIQUE : ni `file_get_contents`, ni
 * `getimagesize`, ni `filesize`, ni `hash_file`, ni `file_exists`. Dimensions,
 * empreintes et poids viennent du build, écrits dans ce fichier de métadonnées.
 * `disponible` atteste donc la présence des MÉTADONNÉES, jamais celle des octets :
 * une tuile manquante se dégrade en trou visuel, jamais en erreur PHP, et 280
 * amorçages WordPress pour servir des octets immuables contrediraient les 2,5 s
 * du §10 du brief.
 *
 * VALIDATION STRICTE, TOUT OU RIEN. Une clé manquante ou d'un type inattendu fait
 * rejeter le fichier ENTIER, jamais la seule clé : un fond partiellement décrit
 * produirait une couche montée sur des bornes fausses — une carte qui affirme
 * quelque chose de faux sur la géographie, ce qui est pire qu'une carte absente.
 *
 * Les valeurs retournées sont BRUTES ET NON ÉCHAPPÉES : c'est le thème qui
 * échappe, une fois, à la sortie.
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

/**
 * Chemin absolu du fichier de métadonnées généré.
 *
 * Déduit de l'emplacement du module, jamais d'une constante appartenant au
 * fichier principal de l'extension : le module reste chargeable seul.
 */
function chemin_metadonnees(): string {
	return dirname( __DIR__, 3 ) . '/data/tuiles/fond-13.php';
}

/**
 * Accès mémoïsé aux métadonnées.
 *
 * Un seul `static` : le fichier est inclus une fois par requête, OPcache fait le
 * reste. Volontairement aucun transient ni cache objet — mettre en cache une
 * donnée immuable déjà compilée par OPcache ajouterait une requête à la base et
 * une classe d'incohérence sans rien gagner.
 *
 * @return array{valide:bool,mode:string,pyramide:array,statique:array,attribution:array}
 */
function donnees(): array {
	static $donnees = null;

	if ( null === $donnees ) {
		$donnees = charger( chemin_metadonnees() );
	}

	return $donnees;
}

/**
 * Repli, de forme rigoureusement identique au retour nominal.
 *
 * Il n'y a délibérément AUCUN code de raison. Le §1.4 du contrat #9 a écarté
 * `massifs_fond_de_carte_etat()`, seul consommateur possible d'un tel code : le
 * conserver serait du code mort. Le diagnostic appartient à la recette de build
 * (`npm run verifier`), qui nomme précisément ce qui cloche — l'exécution, elle,
 * n'a qu'une décision à prendre : monter la couche, ou ne pas la monter.
 *
 * @return array
 */
function repli(): array {
	return array(
		'valide'      => false,
		'mode'        => MODE_DEGRADE,
		'pyramide'    => array(
			'version'      => '',
			'sha256'       => '',
			'octets'       => 0,
			'nombre'       => 0,
			'zoom_min'     => ZOOM_MIN_DEFAUT,
			'zoom_max'     => ZOOM_MAX_DEFAUT,
			'taille_tuile' => TAILLE_TUILE_DEFAUT,
			'format'       => FORMAT_TUILE_DEFAUT,
			'bbox'         => BBOX_NULLE,
		),
		'statique'    => array(
			'version'          => '',
			'sha256'           => '',
			'octets'           => 0,
			'largeur'          => 0,
			'hauteur'          => 0,
			'contours_massifs' => 0,
		),
		'attribution' => array(
			'phrase'       => '',
			'lien_licence' => '',
			'faits'        => array_fill_keys( FAITS_ATTRIBUTION, '' ),
		),
	);
}

/**
 * Charge et valide le fichier de métadonnées.
 *
 * @param string $chemin Chemin absolu du fichier généré.
 * @return array
 */
function charger( string $chemin ): array {
	if ( ! is_file( $chemin ) || ! is_readable( $chemin ) ) {
		return repli();
	}

	// `require` et non `require_once` : on a besoin de la valeur de retour, que
	// `require_once` remplacerait par `true` si le fichier était déjà inclus.
	$brut = require $chemin;

	if ( ! is_array( $brut ) || ! isset( $brut['schema'] ) || ! is_int( $brut['schema'] ) ) {
		return repli();
	}

	// Un schéma plus récent se REJETTE, il ne se devine pas : lire un format futur
	// avec les règles d'aujourd'hui produirait des données fausses.
	if ( $brut['schema'] > SCHEMA_CONNU || $brut['schema'] < 1 ) {
		return repli();
	}

	if ( ! isset( $brut['mode'] ) || ! is_string( $brut['mode'] ) ) {
		return repli();
	}

	$pyramide    = valider_pyramide( bloc( $brut, 'pyramide' ) );
	$statique    = valider_statique( bloc( $brut, 'statique' ) );
	$attribution = valider_attribution( bloc( $brut, 'attribution' ) );

	if ( null === $pyramide || null === $statique || null === $attribution ) {
		return repli();
	}

	return array(
		'valide'      => true,
		// Énumération fermée : tout ce qui n'est pas exactement `complet` est `degrade`.
		'mode'        => MODE_COMPLET === $brut['mode'] ? MODE_COMPLET : MODE_DEGRADE,
		'pyramide'    => $pyramide,
		'statique'    => $statique,
		'attribution' => $attribution,
	);
}

/**
 * Valide le bloc de la pyramide.
 *
 * @param array $bloc Bloc brut.
 * @return array|null Null si le bloc est invalide : le fichier ENTIER est alors rejeté.
 */
function valider_pyramide( array $bloc ): ?array {
	$version      = jeton( $bloc, 'version', VERSION_REGEX );
	$sha256       = jeton( $bloc, 'sha256', SHA256_REGEX );
	$octets       = entier( $bloc, 'octets' );
	$nombre       = entier( $bloc, 'nombre' );
	$zoom_min     = entier( $bloc, 'zoom_min' );
	$zoom_max     = entier( $bloc, 'zoom_max' );
	$taille_tuile = entier( $bloc, 'taille_tuile' );
	$format       = texte( $bloc, 'format' );
	$bbox         = normaliser_bbox( isset( $bloc['bbox'] ) ? $bloc['bbox'] : null );

	if ( null === $version || null === $sha256 || null === $octets || null === $nombre ) {
		return null;
	}

	if ( null === $zoom_min || null === $zoom_max || null === $taille_tuile || null === $bbox ) {
		return null;
	}

	if ( null === $format || '' === $format ) {
		return null;
	}

	// Bornes aberrantes : une pyramide qui annonce z0-z22 ou une tuile de 0 pixel
	// n'est pas une donnée à interpréter, c'est un artefact corrompu.
	if ( $zoom_min < 0 || $zoom_max > ZOOM_PLAFOND || $zoom_min > $zoom_max || $taille_tuile < 1 ) {
		return null;
	}

	return array(
		'version'      => $version,
		'sha256'       => $sha256,
		'octets'       => $octets,
		'nombre'       => $nombre,
		'zoom_min'     => $zoom_min,
		'zoom_max'     => $zoom_max,
		'taille_tuile' => $taille_tuile,
		'format'       => $format,
		'bbox'         => $bbox,
	);
}

/**
 * Valide le bloc de l'image statique.
 *
 * @param array $bloc Bloc brut.
 * @return array|null Null si le bloc est invalide.
 */
function valider_statique( array $bloc ): ?array {
	$version = jeton( $bloc, 'version', VERSION_REGEX );
	$sha256  = jeton( $bloc, 'sha256', SHA256_REGEX );
	$octets  = entier( $bloc, 'octets' );
	$largeur = entier( $bloc, 'largeur' );
	$hauteur = entier( $bloc, 'hauteur' );
	$nombre  = entier( $bloc, 'contours_massifs' );

	if ( null === $version || null === $sha256 || null === $octets ) {
		return null;
	}

	if ( null === $largeur || null === $hauteur || null === $nombre ) {
		return null;
	}

	return array(
		'version'          => $version,
		'sha256'           => $sha256,
		'octets'           => $octets,
		'largeur'          => $largeur,
		'hauteur'          => $hauteur,
		'contours_massifs' => $nombre,
	);
}

/**
 * Valide le bloc d'attribution.
 *
 * Les faits sont énumérés un à un, et non recopiés en bloc : toutes les clés du
 * contrat restent présentes, et une clé surnuméraire ajoutée au fichier n'atteint
 * jamais le thème.
 *
 * @param array $bloc Bloc brut.
 * @return array|null Null si le bloc est invalide.
 */
function valider_attribution( array $bloc ): ?array {
	$phrase = texte( $bloc, 'phrase' );
	$lien   = texte( $bloc, 'lien_licence' );

	if ( null === $phrase || null === $lien ) {
		return null;
	}

	$source = bloc( $bloc, 'faits' );
	$faits  = array();

	foreach ( FAITS_ATTRIBUTION as $cle ) {
		$valeur = texte( $source, $cle );

		if ( null === $valeur ) {
			return null;
		}

		$faits[ $cle ] = $valeur;
	}

	return array(
		'phrase'       => $phrase,
		'lien_licence' => $lien,
		'faits'        => $faits,
	);
}

/**
 * Lit une chaîne. Null si la clé manque ou n'est pas une chaîne.
 *
 * @param array  $bloc Bloc source.
 * @param string $cle  Clé recherchée.
 * @return string|null
 */
function texte( array $bloc, string $cle ): ?string {
	return isset( $bloc[ $cle ] ) && is_string( $bloc[ $cle ] ) ? $bloc[ $cle ] : null;
}

/**
 * Lit un entier positif ou nul. Null si la clé manque, n'est pas un entier, ou est négative.
 *
 * @param array  $bloc Bloc source.
 * @param string $cle  Clé recherchée.
 * @return int|null
 */
function entier( array $bloc, string $cle ): ?int {
	if ( ! isset( $bloc[ $cle ] ) || ! is_int( $bloc[ $cle ] ) || $bloc[ $cle ] < 0 ) {
		return null;
	}

	return $bloc[ $cle ];
}

/**
 * Lit un jeton de version ou une empreinte : chaîne vide, ou conforme à sa forme.
 *
 * La chaîne vide est la valeur d'absence — un build en mode dégradé n'a pas de
 * pyramide, donc pas de version. Une chaîne NON VIDE et NON CONFORME, elle, est
 * une corruption : elle fait rejeter le fichier entier plutôt que de composer une
 * URL sur un segment de chemin arbitraire.
 *
 * @param array  $bloc  Bloc source.
 * @param string $cle   Clé recherchée.
 * @param string $forme Expression régulière de contrôle.
 * @return string|null
 */
function jeton( array $bloc, string $cle, string $forme ): ?string {
	$valeur = texte( $bloc, $cle );

	if ( null === $valeur ) {
		return null;
	}

	if ( '' === $valeur ) {
		return '';
	}

	return 1 === preg_match( $forme, $valeur ) ? $valeur : null;
}

/**
 * Lit un sous-bloc. Tableau vide si la clé manque — la validation qui suit s'en charge.
 *
 * @param array  $bloc Bloc source.
 * @param string $cle  Clé recherchée.
 * @return array
 */
function bloc( array $bloc, string $cle ): array {
	return isset( $bloc[ $cle ] ) && is_array( $bloc[ $cle ] ) ? $bloc[ $cle ] : array();
}

/**
 * Valide une emprise rectangulaire EPSG:4326.
 *
 * @param mixed $bbox Valeur brute.
 * @return array|null
 */
function normaliser_bbox( $bbox ): ?array {
	if ( ! is_array( $bbox ) ) {
		return null;
	}

	$normalisee = array();

	foreach ( array( 'ouest', 'sud', 'est', 'nord' ) as $borne ) {
		if ( ! isset( $bbox[ $borne ] ) || ( ! is_float( $bbox[ $borne ] ) && ! is_int( $bbox[ $borne ] ) ) ) {
			return null;
		}

		$normalisee[ $borne ] = (float) $bbox[ $borne ];
	}

	if ( $normalisee['ouest'] > $normalisee['est'] || $normalisee['sud'] > $normalisee['nord'] ) {
		return null;
	}

	return $normalisee;
}
