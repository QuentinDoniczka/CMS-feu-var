<?php
/**
 * Constantes et valeurs de repli du fond de carte.
 *
 * L'extension fournit les CODES, le thème fournit les MOTS : aucune chaîne
 * d'interface ici. L'issue #9 n'expose d'ailleurs qu'UNE chaîne, l'attribution
 * du §9 du brief, et celle-là est une DONNÉE produite par le build — pas une
 * constante de code.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Tuiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Version du module. Définie ici, dans la feuille de l'arbre de dépendances :
 * n'importe lequel des fichiers du module est un point d'entrée valide, et tous
 * requièrent celui-ci. `module.php` s'appuie sur cette constante pour ne rien
 * refaire s'il est chargé deux fois.
 */
if ( ! defined( 'MASSIFS_INGEST_TUILES_VERSION' ) ) {
	define( 'MASSIFS_INGEST_TUILES_VERSION', '1.0.0' );
}

/**
 * Version de schéma du fichier de métadonnées que ce code sait lire.
 *
 * 1 : pyramide raster z5-z12, image statique du repli, attribution §9.
 */
const SCHEMA_CONNU = 1;

/** Type de fond publié. Littéral gelé par le §1.1 du contrat #9. */
const TYPE_FOND = 'tuiles';

/**
 * Modes de génération. Énumération FERMÉE à deux valeurs.
 *
 * Tout ce qui n'est pas exactement `complet` est ramené à `degrade` : c'est la
 * seule lecture honnête de « pas complet », et cela ferme la valeur au lieu de
 * laisser passer une chaîne inconnue jusqu'au thème.
 */
const MODE_COMPLET = 'complet';
const MODE_DEGRADE = 'degrade';

/*
 * Constantes de FORMAT, pas de données : elles décrivent la forme d'une tuile,
 * qui ne dépend pas de la présence du fichier de métadonnées. Elles survivent
 * donc au repli, contrairement aux versions, empreintes et dénombrements
 * (contrat #9 §1.1).
 *
 * `zoom_max` est la borne de la PYRAMIDE, jamais une autorisation de zoom : la
 * carte reste plafonnée au `zoom_max` du référentiel (clause F-11), sans quoi le
 * douzième niveau afficherait un fond sans polygones.
 */
const ZOOM_MIN_DEFAUT = 5;
const ZOOM_MAX_DEFAUT = 12;
const TAILLE_TUILE_DEFAUT = 256;

/**
 * Classe de média de la couche, publiée sous la clé `format`.
 *
 * `carte.php` teste `'raster' === $fond['format']` avant de monter un
 * `L.tileLayer` : la question qu'il pose est la CLASSE DE MÉDIA de la couche, pas
 * l'extension des fichiers qui la composent. Le §1.1 d'origine confondait les
 * deux faits ; l'avenant A-11 les sépare, et l'extension reste publiée sous son
 * propre nom, `format_tuile`.
 */
const FORMAT_MEDIA = 'raster';

/**
 * Extension de tuile de repli, publiée sous la clé `format_tuile`.
 *
 * Nommée `FORMAT_TUILE_*` et non `FORMAT_*` depuis l'avenant A-11 : dans ce
 * module, « format » tout court désigne désormais la classe de média, et deux
 * constantes voisines nommées pareil pour deux faits distincts se confondraient
 * à la première relecture.
 */
const FORMAT_TUILE_DEFAUT = 'png';

/**
 * Forme d'une extension de tuile : un segment de chemin, rien d'autre.
 *
 * La version est déjà contrainte par `VERSION_REGEX` avant d'entrer dans une URL ;
 * l'extension mérite la même défiance. Sans ce contrôle, une métadonnée corrompue
 * portant `../..` composerait un chemin qui sort du répertoire servi.
 */
const FORMAT_TUILE_REGEX = '/^[a-z0-9]{1,8}$/';

/** Forme d'un jeton de version : segment de chemin, jamais une query string. */
const VERSION_REGEX = '/^[0-9a-f]{8}$/';

/** Forme d'une empreinte sha256. */
const SHA256_REGEX = '/^[0-9a-f]{64}$/';

/** Zoom maximal concevable en Web Mercator : au-delà, la valeur lue est aberrante. */
const ZOOM_PLAFOND = 22;

/**
 * Emprise nulle du repli : quatre flottants à zéro, jamais `null`.
 *
 * Écrite UNE fois, ici : `repli()` la pose dans le bloc pyramide et `fond()` la
 * publie quand le fond est indisponible. Deux littéraux, dans deux fichiers,
 * auraient fini par diverger sur un chiffre.
 */
const BBOX_NULLE = array(
	'ouest' => 0.0,
	'sud'   => 0.0,
	'est'   => 0.0,
	'nord'  => 0.0,
);

/** Les sept faits d'attribution, énumérés un à un pour que le thème n'écrive jamais `isset()`. */
const FAITS_ATTRIBUTION = array(
	'canal',
	'canal_url',
	'extrait_le',
	'licence_nom',
	'licence_version',
	'licence_url',
	'rendu',
);
