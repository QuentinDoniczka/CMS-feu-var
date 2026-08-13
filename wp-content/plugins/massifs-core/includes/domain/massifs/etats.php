<?php
/**
 * États et raisons du domaine « massifs ».
 *
 * L'extension fournit les CODES, le thème fournit les MOTS : aucune chaîne
 * d'interface ici. Un consommateur compare des constantes, jamais des libellés.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Domain\Massifs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Version du module. Définie ici, dans la feuille de l'arbre de dépendances :
 * n'importe lequel des fichiers du module est un point d'entrée valide, et tous
 * requièrent celui-ci. `module.php` s'appuie sur cette constante pour ne rien
 * refaire s'il est chargé deux fois.
 */
if ( ! defined( 'MASSIFS_DOMAINE_MASSIFS_VERSION' ) ) {
	define( 'MASSIFS_DOMAINE_MASSIFS_VERSION', '1.0.0' );
}

/** Référentiel lisible et cohérent. */
const ETAT_REFERENTIEL_OK = 'referentiel_ok';

/** Référentiel absent, illisible, de schéma inconnu, vide, ou porteur d'une ligne invalide. */
const ETAT_REFERENTIEL_INDISPONIBLE = 'referentiel_indisponible';

/** Massif disparu d'une révision source, conservé pour l'historique. */
const ETAT_MASSIF_RETIRE = 'massif_retire';

/** Aucun attribut de commune dans la source : la ligne « communes » s'omet, jamais « aucune commune ». */
const ETAT_COMMUNES_INCONNUES = 'communes_inconnues';

/** Métadonnées de géométrie absentes : la carte ne s'initialise pas, la liste porte l'information. */
const ETAT_GEOMETRIE_INDISPONIBLE = 'geometrie_indisponible';

/** Le fichier de données n'existe pas ou n'est pas lisible. */
const RAISON_FICHIER_ABSENT = 'fichier_absent';

/** Le fichier n'a pas retourné la structure attendue. */
const RAISON_CONTENU_INVALIDE = 'contenu_invalide';

/** Le fichier annonce un schéma plus récent que celui que ce code sait lire. */
const RAISON_SCHEMA_INCOMPATIBLE = 'schema_incompatible';

/** Le fichier est valide mais ne contient aucun massif. */
const RAISON_REFERENTIEL_VIDE = 'referentiel_vide';

/** Au moins une ligne est malformée : le fichier ENTIER est rejeté (voir referentiel.php). */
const RAISON_LIGNE_INVALIDE = 'ligne_invalide';

/** Forme d'un code de massif, contrainte dure partagée avec le domaine « statuts ». */
const CODE_REGEX = '/^[a-z0-9_-]{1,64}$/';

/**
 * Zoom maximal de la couche massifs.
 *
 * Mesuré au build (écart max 93,62 m, soit 0,844 px à z10 et 1,688 px à z11 à la
 * latitude 43,5°) et recopié ici pour que la valeur survive à l'absence du
 * fichier de données : le contrat impose que `massifs_emprise()['zoom_max']`
 * reste renseigné en mode dégradé.
 */
const ZOOM_MAX_DEFAUT = 11;

/** Format de l'artefact géométrique, conservé en mode dégradé. */
const FORMAT_GEOMETRIE_DEFAUT = 'geojson';

/**
 * Statut de la lacune « communes », conservé en mode dégradé.
 *
 * Jamais une chaîne vide : c'est la seule valeur qui ne puisse pas être relue
 * comme « aucune commune concernée ».
 */
const STATUT_COMMUNES_DEFAUT = 'inconnue';
