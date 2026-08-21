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

/**
 * Aucune commune résolue : la ligne « communes » s'omet, jamais « aucune commune ».
 *
 * Cet état a CESSÉ D'ÊTRE PERMANENT et il est devenu atteignable. Tant qu'aucun
 * référentiel communal n'existait, il était la seule réponse possible ; il est
 * désormais celui d'une géométrie de zone INEXPLOITABLE — type inattendu,
 * structure malformée, coordonnée non finie. On ne sait pas, et on le dit :
 * deviner une commune depuis une géométrie qu'on n'a pas su lire serait affirmer
 * sans avoir mesuré.
 */
const ETAT_COMMUNES_INCONNUES = 'communes_inconnues';

/** Commune résolue pour la zone demandée. */
const ETAT_COMMUNES_OK = 'communes_ok';

/** Zone débordant l'emprise couverte par l'artefact, ou commune la plus proche au-delà du plafond. */
const RAISON_COMMUNES_HORS_COUVERTURE = 'communes_hors_couverture';

/** Fichier de lookup absent : les communes PAR MASSIF, elles, restent servies. */
const RAISON_COMMUNES_ARTEFACT_ABSENT = 'communes_artefact_absent';

/** Fichier de lookup illisible ou malformé : rien n'est deviné, rien n'est servi. */
const RAISON_COMMUNES_ARTEFACT_INVALIDE = 'communes_artefact_invalide';

/**
 * Plafond de distance à une commune, en mètres.
 *
 * Au-delà, le serveur n'émet RIEN plutôt qu'un nom trompeur : sur un feu au
 * large ou dans un département voisin, la commune « la plus proche » cesse
 * d'être une information et devient une affirmation fausse. La valeur est
 * recopiée ici pour survivre à l'absence de l'artefact ; l'artefact la porte
 * aussi, et le module refuse un artefact qui annoncerait autre chose.
 */
const PLAFOND_COMMUNE_M = 5000;

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
