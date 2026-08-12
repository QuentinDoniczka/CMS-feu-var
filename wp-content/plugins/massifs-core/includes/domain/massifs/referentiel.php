<?php
/**
 * Lecture du référentiel des massifs.
 *
 * Le module est en LECTURE SEULE et sans effet de bord : aucun hook, aucune
 * table, aucune option, aucun transient, aucune requête SQL, aucune sortie.
 * Le charger ne fait rien d'observable ; ne pas le charger n'est pas fatal.
 *
 * Les valeurs retournées sont BRUTES ET NON ÉCHAPPÉES : c'est le thème qui
 * échappe, une fois, à la sortie. Ne pas « corriger » en ajoutant `esc_html()`
 * ici — les mêmes tableaux alimentent du JSON et un export CSV, où des entités
 * HTML seraient une corruption de donnée, pas une protection.
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

/**
 * Version de schéma du fichier de données que ce code sait lire.
 *
 * 2 : la correspondance gelée avec les identifiants du flux préfectoral
 * (`source.identifiant_prefecture` et bloc racine `correspondance_source`).
 */
const SCHEMA_CONNU = 2;

/**
 * Chemin absolu du fichier de métadonnées généré.
 *
 * Déduit de l'emplacement du module, jamais d'une constante appartenant au
 * fichier principal de l'extension : le module reste chargeable seul.
 */
function chemin_donnees(): string {
	return dirname( __DIR__, 3 ) . '/data/massifs-13.php';
}

/**
 * Chemin absolu de l'artefact géométrique.
 *
 * Sert UNIQUEMENT à construire une URL. Ce fichier n'est jamais ouvert, ni
 * mesuré, ni haché au runtime : taille et empreinte viennent du build.
 */
function chemin_geometrie(): string {
	return dirname( __DIR__, 3 ) . '/data/massifs-13.geometrie.json';
}

/**
 * Accès mémoïsé aux données.
 *
 * Un seul `static` : le fichier est inclus une fois par requête, OPcache fait
 * le reste. Volontairement aucun transient ni cache objet — mettre en cache une
 * donnée immuable déjà compilée par OPcache ajouterait une requête à la base
 * et une classe d'incohérence (cache tiède après ré-import) sans rien gagner.
 *
 * Les deux sens de la correspondance préfectorale sont construits ICI, une
 * seule fois, et mémoïsés avec le reste : la lecture inverse
 * (`identifiant_source` -> `code`) ne parcourt jamais les 25 lignes à l'appel.
 *
 * @return array{disponible:bool,raison:?string,schema:int,genere_le:?string,massifs:array<string,array>,correspondance:array<string,string>,index_source:array<string,string>,meta:array}
 */
function donnees(): array {
	static $donnees = null;

	if ( null === $donnees ) {
		$donnees = charger( chemin_donnees() );
	}

	return $donnees;
}

/**
 * Retour d'échec, de forme identique au retour nominal.
 *
 * @param string $raison Constante RAISON_*.
 * @return array
 */
function echec( string $raison ): array {
	return array(
		'disponible'     => false,
		'raison'         => $raison,
		'schema'         => 0,
		'genere_le'      => null,
		'massifs'        => array(),
		'correspondance' => array(),
		'index_source'   => array(),
		'meta'           => array(),
	);
}

/**
 * Charge et valide le fichier de données.
 *
 * Une ligne invalide fait rejeter le fichier ENTIER, jamais la seule ligne :
 * un massif silencieusement disparu de la liste du jour se lirait, pour un
 * visiteur, comme « aucune restriction ». Une panne visible est la seule
 * réponse honnête.
 *
 * @param string $chemin Chemin absolu du fichier généré.
 * @return array
 */
function charger( string $chemin ): array {
	if ( ! is_file( $chemin ) || ! is_readable( $chemin ) ) {
		return echec( RAISON_FICHIER_ABSENT );
	}

	// `require` et non `require_once` : on a besoin de la valeur de retour, que
	// `require_once` remplacerait par `true` si le fichier était déjà inclus.
	$brut = require $chemin;

	if ( ! is_array( $brut ) || ! isset( $brut['schema'] ) || ! is_int( $brut['schema'] ) ) {
		return echec( RAISON_CONTENU_INVALIDE );
	}

	// Un schéma plus récent se REJETTE, il ne se devine pas : lire un format
	// futur avec les règles d'aujourd'hui produirait des données fausses.
	if ( $brut['schema'] > SCHEMA_CONNU || $brut['schema'] < 1 ) {
		return echec( RAISON_SCHEMA_INCOMPATIBLE );
	}

	if ( ! isset( $brut['massifs'] ) || ! is_array( $brut['massifs'] ) ) {
		return echec( RAISON_CONTENU_INVALIDE );
	}

	if ( array() === $brut['massifs'] ) {
		return echec( RAISON_REFERENTIEL_VIDE );
	}

	$massifs = array();

	foreach ( $brut['massifs'] as $cle => $ligne ) {
		$normalisee = normaliser_ligne( (string) $cle, $ligne );

		if ( null === $normalisee ) {
			return echec( RAISON_LIGNE_INVALIDE );
		}

		$massifs[ $normalisee['code'] ] = $normalisee;
	}

	// Le fichier arrive déjà trié ; ce tri le garantit quoi qu'il arrive. `strcmp`
	// compare des octets ASCII : aucun `setlocale`, donc un ordre reproductible
	// d'un serveur à l'autre.
	uasort(
		$massifs,
		static function ( array $a, array $b ): int {
			return strcmp( $a['tri'], $b['tri'] );
		}
	);

	$correspondance = indexer_correspondance( $massifs );

	if ( null === $correspondance ) {
		return echec( RAISON_CONTENU_INVALIDE );
	}

	// Le bloc racine dit la même chose que les lignes. Il est recoupé plutôt que
	// cru : deux représentations d'une même correspondance qui divergeraient
	// rattacheraient un statut officiel au mauvais massif selon le sens de
	// lecture. En désaccord, le fichier entier est rejeté.
	if ( ! correspondance_concordante( bloc( $brut, 'correspondance_source' ), $correspondance['directe'] ) ) {
		return echec( RAISON_CONTENU_INVALIDE );
	}

	return array(
		'disponible'     => true,
		'raison'         => null,
		'schema'         => $brut['schema'],
		'genere_le'      => isset( $brut['genere_le'] ) && is_string( $brut['genere_le'] ) ? $brut['genere_le'] : null,
		'massifs'        => $massifs,
		'correspondance' => $correspondance['directe'],
		'index_source'   => $correspondance['inverse'],
		'meta'           => $brut,
	);
}

/**
 * Construit les deux sens de la correspondance préfectorale, une fois.
 *
 * Les massifs retirés sont inclus : leur identité survit à leur retrait, et un
 * statut historique doit rester traduisible. Traduire n'est pas affirmer qu'un
 * massif est actif — c'est `massifs_massif_existe()` qui répond à cela.
 *
 * @param array<string,array> $massifs Lignes déjà validées.
 * @return array{directe:array<string,string>,inverse:array<string,string>}|null Null si deux massifs partagent un identifiant.
 */
function indexer_correspondance( array $massifs ): ?array {
	$directe = array();
	$inverse = array();

	foreach ( $massifs as $code => $ligne ) {
		$identifiant = $ligne['source']['identifiant_prefecture'];

		if ( null === $identifiant ) {
			continue;
		}

		// Un identifiant partagé écraserait silencieusement une entrée de l'index
		// inverse : le massif perdant recevrait les statuts du gagnant.
		if ( isset( $inverse[ $identifiant ] ) ) {
			return null;
		}

		$directe[ $code ]        = $identifiant;
		$inverse[ $identifiant ] = $code;
	}

	return array(
		'directe' => $directe,
		'inverse' => $inverse,
	);
}

/**
 * Le bloc racine déclaré est-il compatible avec celui déduit des lignes ?
 *
 * Un bloc absent est accepté : un fichier de schéma antérieur n'en porte pas,
 * et les lignes suffisent à établir la correspondance.
 *
 * @param array<string,mixed>  $declaree Bloc `correspondance_source` du fichier.
 * @param array<string,string> $deduite  Correspondance déduite des lignes.
 * @return bool
 */
function correspondance_concordante( array $declaree, array $deduite ): bool {
	if ( array() === $declaree ) {
		return true;
	}

	if ( count( $declaree ) !== count( $deduite ) ) {
		return false;
	}

	foreach ( $declaree as $code => $identifiant ) {
		if ( ! isset( $deduite[ $code ] ) || $deduite[ $code ] !== $identifiant ) {
			return false;
		}
	}

	return true;
}

/**
 * Valide une ligne et la remet en forme de contrat.
 *
 * @param string $cle   Clé du tableau, qui doit être le code lui-même.
 * @param mixed  $ligne Ligne brute.
 * @return array|null Null si la ligne est invalide.
 */
function normaliser_ligne( string $cle, $ligne ): ?array {
	if ( ! is_array( $ligne ) ) {
		return null;
	}

	foreach ( array( 'code', 'libelle', 'tri', 'communes', 'communes_source', 'actif', 'retire_le', 'bbox', 'centre', 'source', 'note_provenance' ) as $attendu ) {
		if ( ! array_key_exists( $attendu, $ligne ) ) {
			return null;
		}
	}

	if ( ! is_string( $ligne['code'] ) || 1 !== preg_match( CODE_REGEX, $ligne['code'] ) || $ligne['code'] !== $cle ) {
		return null;
	}

	if ( ! is_string( $ligne['libelle'] ) || '' === trim( $ligne['libelle'] ) ) {
		return null;
	}

	if ( ! is_string( $ligne['tri'] ) || '' === $ligne['tri'] || ! is_array( $ligne['communes'] ) ) {
		return null;
	}

	if ( ! is_string( $ligne['communes_source'] ) || ! is_bool( $ligne['actif'] ) ) {
		return null;
	}

	if ( null !== $ligne['retire_le'] && ! is_string( $ligne['retire_le'] ) ) {
		return null;
	}

	if ( null !== $ligne['note_provenance'] && ! is_string( $ligne['note_provenance'] ) ) {
		return null;
	}

	$bbox   = normaliser_bbox( $ligne['bbox'] );
	$centre = normaliser_centre( $ligne['centre'] );
	$source = normaliser_source( $ligne['source'] );

	if ( false === $bbox || false === $centre || null === $source ) {
		return null;
	}

	$communes = array();

	foreach ( $ligne['communes'] as $commune ) {
		if ( ! is_string( $commune ) ) {
			return null;
		}

		$communes[] = $commune;
	}

	return array(
		'code'            => $ligne['code'],
		'libelle'         => $ligne['libelle'],
		'tri'             => $ligne['tri'],
		'communes'        => $communes,
		'communes_source' => $ligne['communes_source'],
		'actif'           => $ligne['actif'],
		'retire_le'       => $ligne['retire_le'],
		'bbox'            => $bbox,
		'centre'          => $centre,
		'source'          => $source,
		'note_provenance' => $ligne['note_provenance'],
	);
}

/**
 * Valide une emprise rectangulaire EPSG:4326.
 *
 * @param mixed $bbox Valeur brute.
 * @return array|null|false False si la valeur est invalide.
 */
function normaliser_bbox( $bbox ) {
	if ( null === $bbox ) {
		return null;
	}

	if ( ! is_array( $bbox ) ) {
		return false;
	}

	$normalisee = array();

	foreach ( array( 'ouest', 'sud', 'est', 'nord' ) as $borne ) {
		if ( ! isset( $bbox[ $borne ] ) || ! is_numeric( $bbox[ $borne ] ) ) {
			return false;
		}

		$normalisee[ $borne ] = (float) $bbox[ $borne ];
	}

	if ( $normalisee['ouest'] > $normalisee['est'] || $normalisee['sud'] > $normalisee['nord'] ) {
		return false;
	}

	return $normalisee;
}

/**
 * Valide un point EPSG:4326.
 *
 * @param mixed $centre Valeur brute.
 * @return array|null|false False si la valeur est invalide.
 */
function normaliser_centre( $centre ) {
	if ( null === $centre ) {
		return null;
	}

	if ( ! is_array( $centre ) || ! isset( $centre['lon'], $centre['lat'] ) ) {
		return false;
	}

	if ( ! is_numeric( $centre['lon'] ) || ! is_numeric( $centre['lat'] ) ) {
		return false;
	}

	return array(
		'lon' => (float) $centre['lon'],
		'lat' => (float) $centre['lat'],
	);
}

/**
 * Valide le bloc de provenance d'une ligne.
 *
 * `identifiant_prefecture` est optionnel — un fichier de schéma antérieur n'en
 * porte pas, et il vaut mieux un référentiel sans correspondance qu'un
 * référentiel indisponible. Présent, il doit être une chaîne : un type inattendu
 * signale un artefact corrompu, pas une version ancienne.
 *
 * @param mixed $source Valeur brute.
 * @return array|null Null si la valeur est invalide.
 */
function normaliser_source( $source ): ?array {
	if ( ! is_array( $source ) || ! isset( $source['gid'], $source['nom_massif'], $source['revision'] ) ) {
		return null;
	}

	if ( ! is_int( $source['gid'] ) || ! is_string( $source['nom_massif'] ) || ! is_string( $source['revision'] ) ) {
		return null;
	}

	$identifiant = array_key_exists( 'identifiant_prefecture', $source ) ? $source['identifiant_prefecture'] : null;

	if ( null !== $identifiant && ( ! is_string( $identifiant ) || '' === $identifiant ) ) {
		return null;
	}

	return array(
		'gid'                    => $source['gid'],
		'nom_massif'             => $source['nom_massif'],
		'revision'               => $source['revision'],
		'identifiant_prefecture' => $identifiant,
	);
}

/**
 * Lit une chaîne dans un bloc de métadonnées.
 *
 * @param array  $bloc   Bloc source.
 * @param string $cle    Clé recherchée.
 * @param string $defaut Valeur de repli.
 * @return string
 */
function texte( array $bloc, string $cle, string $defaut = '' ): string {
	return isset( $bloc[ $cle ] ) && is_string( $bloc[ $cle ] ) ? $bloc[ $cle ] : $defaut;
}

/**
 * Lit un entier dans un bloc de métadonnées.
 *
 * @param array  $bloc   Bloc source.
 * @param string $cle    Clé recherchée.
 * @param int    $defaut Valeur de repli.
 * @return int
 */
function entier( array $bloc, string $cle, int $defaut = 0 ): int {
	return isset( $bloc[ $cle ] ) && is_int( $bloc[ $cle ] ) ? $bloc[ $cle ] : $defaut;
}

/**
 * Lit un sous-bloc de métadonnées.
 *
 * @param array  $bloc Bloc source.
 * @param string $cle  Clé recherchée.
 * @return array
 */
function bloc( array $bloc, string $cle ): array {
	return isset( $bloc[ $cle ] ) && is_array( $bloc[ $cle ] ) ? $bloc[ $cle ] : array();
}

/**
 * Référentiel complet, clé = code, pré-trié par `tri`.
 *
 * @param bool $inclure_retires Inclure les massifs retirés.
 * @return array<string,array>
 */
function referentiel( bool $inclure_retires = false ): array {
	$massifs = donnees()['massifs'];

	if ( $inclure_retires ) {
		return $massifs;
	}

	return array_filter(
		$massifs,
		static function ( array $massif ): bool {
			return $massif['actif'];
		}
	);
}

/**
 * Une ligne de massif, ou null si le code est inconnu.
 *
 * Le code est cherché tel quel, par clé stricte. Il n'est JAMAIS normalisé :
 * replier « Sainte Victoire! » sur un massif réel présenterait une donnée
 * fausse comme juste. Il ne sert jamais non plus à composer un chemin.
 *
 * @param string $code            Code de massif.
 * @param bool   $inclure_retires Inclure les massifs retirés.
 * @return array|null
 */
function massif( string $code, bool $inclure_retires = false ): ?array {
	$massifs = referentiel( $inclure_retires );

	return isset( $massifs[ $code ] ) ? $massifs[ $code ] : null;
}

/**
 * Existence d'un code.
 *
 * @param string $code            Code de massif.
 * @param bool   $inclure_retires Inclure les massifs retirés.
 * @return bool
 */
function existe( string $code, bool $inclure_retires = false ): bool {
	return null !== massif( $code, $inclure_retires );
}

/**
 * Liste des codes, dans l'ordre de tri.
 *
 * @param bool $inclure_retires Inclure les massifs retirés.
 * @return list<string>
 */
function codes( bool $inclure_retires = false ): array {
	return array_keys( referentiel( $inclure_retires ) );
}

/**
 * Libellé affichable d'un massif.
 *
 * Les massifs retirés sont inclus : leur page et leur historique restent
 * valides, et une page d'historique sans nom serait illisible. Code inconnu :
 * le code est retourné tel quel, jamais une chaîne vide qui casserait une phrase.
 *
 * @param string $code Code de massif.
 * @return string
 */
function libelle( string $code ): string {
	$massif = massif( $code, true );

	return null === $massif ? $code : $massif['libelle'];
}

/**
 * Table code => libellé, dans l'ordre de tri.
 *
 * @param bool $inclure_retires Inclure les massifs retirés.
 * @return array<string,string>
 */
function libelles( bool $inclure_retires = false ): array {
	return array_map(
		static function ( array $massif ): string {
			return $massif['libelle'];
		},
		referentiel( $inclure_retires )
	);
}

/**
 * Nombre de massifs.
 *
 * @param bool $inclure_retires Inclure les massifs retirés.
 * @return int
 */
function compte( bool $inclure_retires = false ): int {
	return count( referentiel( $inclure_retires ) );
}

/**
 * Correspondance gelée `massif_code` => `identifiant_source` du flux préfectoral.
 *
 * DONNÉE GELÉE, JAMAIS CALCULÉE. Elle est recopiée depuis `build/identites.json`,
 * où elle a été vérifiée une par une contre la table officielle des massifs du
 * 13. Elle vaut aujourd'hui `13` + `source.gid`, et ce serait un défaut de
 * l'écrire ainsi : `gid` est le rang alphabétique de la couche DDTM, insérer un
 * massif renumérote 22 identifiants sur 25 sans rien signaler.
 *
 * 25 entrées. Les identifiants `1326` et `1327`, présents dans le flux
 * journalier, n'y figurent délibérément pas : aucune publication officielle ne
 * les nomme (voir `source.flux_identifiants_sans_correspondance`).
 *
 * @return array<string,string> Vide si le référentiel est indisponible.
 */
function correspondance_source(): array {
	return donnees()['correspondance'];
}

/**
 * Code de massif portant un identifiant du flux préfectoral.
 *
 * L'identifiant est cherché tel quel, par clé stricte : ni `trim()`, ni
 * changement de casse, ni transtypage. Replier une valeur approchante sur un
 * massif réel présenterait une donnée fausse comme juste.
 *
 * @param string $identifiant_source Identifiant du flux, par exemple '131'.
 * @return string|null Null si l'identifiant est inconnu, en surnombre (`1326`, `1327`) ou le référentiel indisponible.
 */
function code_depuis_source( string $identifiant_source ): ?string {
	$index = donnees()['index_source'];

	return isset( $index[ $identifiant_source ] ) ? $index[ $identifiant_source ] : null;
}

/**
 * Identifiant du flux préfectoral d'un massif.
 *
 * Le code est cherché tel quel, par clé stricte, comme partout ailleurs dans ce
 * module.
 *
 * @param string $code Code de massif.
 * @return string|null Null si le code est inconnu ou le référentiel indisponible.
 */
function source_depuis_code( string $code ): ?string {
	$correspondance = donnees()['correspondance'];

	return isset( $correspondance[ $code ] ) ? $correspondance[ $code ] : null;
}

/**
 * État de chargement du référentiel.
 *
 * @return array{disponible:bool,code:string,raison:?string,schema:int,genere_le:?string,nombre:int}
 */
function etat(): array {
	$donnees = donnees();

	return array(
		'disponible' => $donnees['disponible'],
		'code'       => $donnees['disponible'] ? ETAT_REFERENTIEL_OK : ETAT_REFERENTIEL_INDISPONIBLE,
		'raison'     => $donnees['raison'],
		'schema'     => $donnees['schema'],
		'genere_le'  => $donnees['genere_le'],
		'nombre'     => count( $donnees['massifs'] ),
	);
}
