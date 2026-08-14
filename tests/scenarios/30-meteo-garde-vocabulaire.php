<?php
/**
 * LE CŒUR DE L'ISSUE #10 — la garde de vocabulaire.
 *
 * Un instantané météo PLEINEMENT VALIDE et mis en cache rend QUAND MÊME
 * « indisponible » tant que les libellés officiels des crans ne sont pas
 * sourcés. La garde est dans notre code, pas dans la source : un bouchon bavard
 * qui injecterait un libellé et une cardinalité ne peut pas la contourner, et
 * basculer le seul booléen `confirme` n'ouvre rien.
 *
 * On éprouve aussi que la garde est une VRAIE porte et non un `false` en dur :
 * fournie complète, elle s'ouvre — c'est ce qui rend l'assertion « fermée »
 * signifiante.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */
require_once __DIR__ . '/../bootstrap.php';

use Massifs\Ingest\Meteo\Vocabulaire;

// Les trois options météo ne sont pas purgées par `t_reset()` (couture J-4) :
// chaque scénario s'en charge lui-même, au début ET à la fin.
$purge = static function (): void {
	delete_option( 'massifs_meteo_snapshots' );
	delete_option( 'massifs_meteo_etat' );
	delete_option( 'massifs_meteo_reglages' );
};

t_reset();
$purge();

// La période d'exploitation est une porte opérationnelle : on la gèle par son
// filtre public plutôt que d'attendre que l'horloge du conteneur coopère.
add_filter( 'massifs_meteo_saison_operationnelle', '__return_true' );

$aujourdhui = massifs_jour_courant();
$ymd        = str_replace( '-', '', $aujourdhui );

// ---------------------------------------------------------------------------
// 1. Un instantané pleinement valide EN CACHE, et un bouchon BAVARD par-dessus.
// ---------------------------------------------------------------------------
update_option(
	'massifs_meteo_snapshots',
	array(
		$ymd => array(
			'schema'        => 1,
			'date_validite' => $aujourdhui,
			'zone_cle'      => '13',
			'niveau_source' => 3,
			'publie_le'     => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
			'recupere_le'   => gmdate( DATE_ATOM ),
			'hash'          => hash( 'sha256', 'bouchon-bavard' ),
			'octets'        => 120,
			// Le bouchon se fait bavard : il prétend porter le libellé et la
			// cardinalité de l'échelle. Rien de tout cela ne doit ressortir.
			'libelle'       => 'LIBELLE INVENTE PAR LE BOUCHON',
			'crans'         => 42,
			'couleur'       => '#ff0000',
		),
	),
	false
);

$m = massifs_meteo_du_jour( $aujourdhui );

t_egal( 'indisponible', $m['etat'], 'instantané valide en cache : l\'état reste « indisponible »' );
t_egal( null, $m['niveau'], 'aucun niveau n\'est composé, `niveau` vaut null LITTÉRAL' );
t_egal( 0, $m['echelle']['crans'], 'la cardinalité reste nulle' );
t_egal( 0, $m['echelle']['atteint'], 'aucun rang atteint' );
t_egal( false, $m['echelle']['confirmee'], 'l\'échelle n\'est pas confirmée' );
t_egal( '', $m['echelle']['phrase'], 'aucune phrase de position' );
t_assert(
	false === strpos( (string) wp_json_encode( $m ), 'LIBELLE INVENTE PAR LE BOUCHON' ),
	'le libellé injecté par le bouchon ne traverse JAMAIS la frontière de lecture'
);
t_assert(
	false === strpos( (string) wp_json_encode( $m ), 'couleur' ),
	'aucune clé inventée par le bouchon ne traverse la frontière de lecture'
);
t_egal( false, Vocabulaire::est_confirme(), 'Vocabulaire::est_confirme() est faux' );
t_egal( array(), Vocabulaire::crans(), 'la table de crans est vide' );
t_egal( 0, Vocabulaire::cardinalite(), 'la cardinalité lue de la configuration vaut zéro' );

// ---------------------------------------------------------------------------
// 2. Le filtre qui bascule le SEUL booléen n'ouvre rien.
// ---------------------------------------------------------------------------
$forcer_booleen = static function ( array $v ): array {
	$v['confirme'] = true;

	return $v;
};
add_filter( 'massifs_meteo_vocabulaire', $forcer_booleen );

t_egal( false, Vocabulaire::est_confirme(), '`confirme => true` SEUL n\'ouvre pas la garde' );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et l\'état public ne bouge pas' );

remove_filter( 'massifs_meteo_vocabulaire', $forcer_booleen );

// Table de crans présente mais incomplète : rangs non contigus, libellé vide,
// correspondance pointant sur un cran inexistant. Aucune de ces variantes
// n'ouvre la garde.
$variantes = array(
	'rangs non contigus'            => array(
		'crans'                 => array(
			array(
				'cle'     => 'bas',
				'libelle' => 'Bas',
				'rang'    => 1,
			),
			array(
				'cle'     => 'haut',
				'libelle' => 'Haut',
				'rang'    => 3,
			),
		),
		'correspondance_source' => array( 1 => 'bas' ),
	),
	'libellé vide'                  => array(
		'crans'                 => array(
			array(
				'cle'     => 'bas',
				'libelle' => '',
				'rang'    => 1,
			),
		),
		'correspondance_source' => array( 1 => 'bas' ),
	),
	'correspondance vide'           => array(
		'crans'                 => array(
			array(
				'cle'     => 'bas',
				'libelle' => 'Bas',
				'rang'    => 1,
			),
		),
		'correspondance_source' => array(),
	),
	'correspondance orpheline'      => array(
		'crans'                 => array(
			array(
				'cle'     => 'bas',
				'libelle' => 'Bas',
				'rang'    => 1,
			),
		),
		'correspondance_source' => array( 1 => 'inconnu' ),
	),
	'rang non entier'               => array(
		'crans'                 => array(
			array(
				'cle'     => 'bas',
				'libelle' => 'Bas',
				'rang'    => '1',
			),
		),
		'correspondance_source' => array( 1 => 'bas' ),
	),
);

foreach ( $variantes as $intitule => $partiel ) {
	$filtre = static function ( array $v ) use ( $partiel ): array {
		return array_merge( $v, $partiel, array( 'confirme' => true ) );
	};

	add_filter( 'massifs_meteo_vocabulaire', $filtre );
	t_egal( false, Vocabulaire::est_confirme(), 'vocabulaire incomplet (' . $intitule . ') : la garde reste fermée' );
	t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'et l\'état public reste « indisponible » (' . $intitule . ')' );
	remove_filter( 'massifs_meteo_vocabulaire', $filtre );
}

// ---------------------------------------------------------------------------
// 3. Fournie COMPLÈTE, la garde s'ouvre : c'est une porte, pas un `false` figé.
// ---------------------------------------------------------------------------
$complet = static function ( array $v ): array {
	return array(
		'confirme'              => true,
		'revision'              => 'recette',
		'source'                => 'recette',
		'crans'                 => array(
			array(
				'cle'     => 'un',
				'libelle' => 'Libellé de recette un',
				'rang'    => 1,
			),
			array(
				'cle'     => 'deux',
				'libelle' => 'Libellé de recette deux',
				'rang'    => 2,
			),
			array(
				'cle'     => 'trois',
				'libelle' => 'Libellé de recette trois',
				'rang'    => 3,
			),
		),
		'correspondance_source' => array(
			1 => 'un',
			2 => 'deux',
			3 => 'trois',
		),
	);
};
add_filter( 'massifs_meteo_vocabulaire', $complet );

t_egal( true, Vocabulaire::est_confirme(), 'vocabulaire COMPLET : la garde s\'ouvre' );

$ouvert = massifs_meteo_du_jour( $aujourdhui );
t_egal( 'disponible', $ouvert['etat'], 'l\'instantané en cache devient alors présentable' );
t_egal( 'trois', $ouvert['niveau']['cle'], 'la clé du cran correspond au jeton source' );
t_egal( 'Libellé de recette trois', $ouvert['niveau']['libelle'], 'le libellé vient du vocabulaire, jamais de la charge' );
t_egal( array( 'cle', 'libelle' ), array_keys( $ouvert['niveau'] ), '`niveau` porte DEUX clés : `rang` ne traverse pas la frontière' );
t_egal( 3, $ouvert['echelle']['crans'], 'la cardinalité vient de la configuration' );
t_egal( 3, $ouvert['echelle']['atteint'], 'le rang atteint est porté par `echelle.atteint`' );
t_egal( true, $ouvert['echelle']['confirmee'], '`echelle.confirmee` suit la garde' );
t_egal( '3 crans sur 3', $ouvert['echelle']['phrase'], 'la phrase de position est rédigée par le SERVEUR' );

remove_filter( 'massifs_meteo_vocabulaire', $complet );
t_egal( 'indisponible', massifs_meteo_du_jour( $aujourdhui )['etat'], 'filtre retiré : la garde se referme' );

// ---------------------------------------------------------------------------
// 4. Contrôle mécanique du module : aucun littéral « 5 », aucune URL.
// ---------------------------------------------------------------------------
$racine   = MASSIFS_CORE_CHEMIN . 'includes/ingest/meteo/';
$fichiers = glob( $racine . '*' );
$fichiers = is_array( $fichiers ) ? $fichiers : array();

t_assert( count( $fichiers ) > 0, 'le module météo est bien présent sur le disque', '> 0', count( $fichiers ) );

$avec_cinq = array();
$avec_url  = array();

foreach ( $fichiers as $fichier ) {
	if ( ! is_file( $fichier ) ) {
		continue;
	}

	$contenu = (string) file_get_contents( $fichier );

	// Le littéral isolé, jamais un chiffre pris dans un nombre plus grand
	// (`sha256`, `65536`, `500`…) : c'est la CARDINALITÉ inventée qu'on traque.
	if ( 1 === preg_match( '/(^|[^0-9A-Za-z_])5([^0-9]|$)/', $contenu ) ) {
		$avec_cinq[] = basename( $fichier );
	}

	if ( 1 === preg_match( '#https?://#', $contenu ) ) {
		$avec_url[] = basename( $fichier );
	}
}

t_egal( array(), $avec_cinq, 'le littéral « 5 » n\'apparaît NULLE PART dans le module, pas même en commentaire' );
t_egal( array(), $avec_url, 'aucune URL dans le module : aucune origine tierce, pas même en commentaire' );

$purge();
t_reset();
t_bilan();
