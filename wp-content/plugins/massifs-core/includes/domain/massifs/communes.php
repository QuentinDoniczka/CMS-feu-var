<?php
/**
 * Référentiel communal : quelle commune porte une zone de feu ?
 *
 * CE FICHIER OUVRE UN ARTEFACT EN PHP, ET CE N'EST PAS UNE ENTORSE À
 * `geometrie.php`. La règle posée là-bas — PHP n'ouvre JAMAIS
 * `data/massifs-13.geometrie.json`, ni `file_get_contents`, ni `json_decode`,
 * ni `filesize`, ni `hash_file` — vise L'ARTEFACT SERVI AU NAVIGATEUR, dont la
 * taille, l'empreinte et le jeton de version viennent du build. Elle reste
 * entière et n'est pas amendée.
 *
 * `communes-13.lookup.json` est un fichier DIFFÉRENT : strictement serveur,
 * jamais servi (`docker/wordpress/plugins-guard.conf` refuse `includes/` à
 * toute profondeur), et lu uniquement sur le CHEMIN CRON. Ne pas lire les deux
 * comme un seul cas.
 *
 * POURQUOI LE CHEMIN CRON ET PAS LE RENDU. Le panneau des zones de feu est
 * rendu par le serveur — le site fonctionne sans JavaScript. Une résolution « à
 * la lecture » ouvrirait donc un mégaoctet de géométrie communale à CHAQUE
 * rendu de la page d'accueil. La résolution appartient à l'ingestion EFFIS, qui
 * la fige dans le relevé ; ce module ne fournit que la fonction de lecture, et
 * ne s'abonne à aucun signal.
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

/** Type d'artefact accepté. Liste fermée : un autre type n'est pas une version ancienne. */
const LOOKUP_COMMUNES_TYPE = 'massifs-communes-lookup';

/** Version d'artefact que ce code sait lire. */
const LOOKUP_COMMUNES_VERSION = 1;

/**
 * Chemin absolu de l'artefact de lookup.
 *
 * Déduit de l'emplacement du module, jamais d'une constante appartenant au
 * fichier principal de l'extension : le module reste chargeable seul.
 */
function chemin_lookup_communes(): string {
	return __DIR__ . '/communes-13.lookup.json';
}

/**
 * Accès mémoïsé et PARESSEUX à l'artefact.
 *
 * Le fichier n'est ouvert qu'au premier appel effectif de
 * `commune_de_la_zone()` : charger le module, lire le référentiel, rendre la page
 * d'accueil ne l'ouvrent jamais. C'est la raison d'être du `static` ici, et non
 * une optimisation de confort.
 *
 * @return array{disponible:bool,raison:string,couverture:array,plafond_m:int,lon_m:float,lat_m:float,communes:array<int,array>}
 */
function lookup_communes(): array {
	static $lookup = null;

	if ( null === $lookup ) {
		$lookup = charger_lookup_communes( chemin_lookup_communes() );
	}

	return $lookup;
}

/**
 * Retour d'échec du chargement, de forme identique au retour nominal.
 *
 * @param string $raison Constante RAISON_COMMUNES_*.
 * @return array
 */
function echec_lookup_communes( string $raison ): array {
	return array(
		'disponible' => false,
		'raison'     => $raison,
		'couverture' => array(
			'ouest' => 0.0,
			'sud'   => 0.0,
			'est'   => 0.0,
			'nord'  => 0.0,
		),
		'plafond_m'  => PLAFOND_COMMUNE_M,
		'lon_m'      => 0.0,
		'lat_m'      => 0.0,
		'communes'   => array(),
	);
}

/**
 * Charge et valide l'artefact de lookup.
 *
 * Un artefact malformé est REFUSÉ EN BLOC, jamais réparé : servir une commune
 * calculée sur une géométrie partiellement lue produirait un nom faux présenté
 * comme un fait, dans le panneau même qui annonce une estimation satellite.
 *
 * La validation porte sur la STRUCTURE et sur les scalaires, pas sur les 28 000
 * coordonnées une à une : l'artefact est produit par notre propre build, son
 * empreinte est consignée dans `data/massifs-13.php` et dans
 * `build/reference.json`, et `npm run verifier` la recoupe. Revalider chaque
 * flottant à chaque exécution du cron coûterait sans rien apprendre de plus.
 *
 * @param string $chemin Chemin absolu de l'artefact.
 * @return array
 */
function charger_lookup_communes( string $chemin ): array {
	if ( ! is_file( $chemin ) || ! is_readable( $chemin ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_ABSENT );
	}

	$brut = file_get_contents( $chemin );

	if ( ! is_string( $brut ) || '' === $brut ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	$donnees = json_decode( $brut, true );

	if ( ! is_array( $donnees ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	if ( LOOKUP_COMMUNES_TYPE !== texte( $donnees, 'type' ) || LOOKUP_COMMUNES_VERSION !== entier( $donnees, 'version' ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	// Le plafond est une RÈGLE DE DOMAINE, portée par le code. Un artefact qui en
	// annonce une autre n'est pas une version ancienne : c'est un désaccord sur la
	// règle, et deux règles pour une même décision en font une indéterminée.
	if ( PLAFOND_COMMUNE_M !== entier( $donnees, 'plafond_m' ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	$couverture = normaliser_bbox( isset( $donnees['couverture'] ) ? $donnees['couverture'] : null );
	$projection = bloc( $donnees, 'projection' );

	if ( ! is_array( $couverture ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	// Les facteurs de projection voyagent AVEC l'artefact : les recopier ici en
	// ferait une seconde définition de la même projection, et deux définitions
	// finiraient par mesurer deux distances différentes pour un même point.
	if ( ! isset( $projection['metres_par_degre_lon'], $projection['metres_par_degre_lat'] ) ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	$lon_m = (float) $projection['metres_par_degre_lon'];
	$lat_m = (float) $projection['metres_par_degre_lat'];

	if ( $lon_m <= 0.0 || $lat_m <= 0.0 ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	$communes = normaliser_communes( isset( $donnees['communes'] ) ? $donnees['communes'] : null );

	if ( null === $communes || array() === $communes ) {
		return echec_lookup_communes( RAISON_COMMUNES_ARTEFACT_INVALIDE );
	}

	return array(
		'disponible' => true,
		'raison'     => '',
		'couverture' => $couverture,
		'plafond_m'  => PLAFOND_COMMUNE_M,
		'lon_m'      => $lon_m,
		'lat_m'      => $lat_m,
		'communes'   => $communes,
	);
}

/**
 * Valide la liste des communes de l'artefact.
 *
 * Une seule entrée invalide fait rejeter l'artefact ENTIER, comme une ligne
 * invalide fait rejeter le référentiel entier : une commune silencieusement
 * absente de la recherche renverrait sa voisine, et un nom faux se lit comme
 * un fait.
 *
 * @param mixed $brutes Valeur brute.
 * @return array<int,array{insee:string,nom:string,dep:string,bbox:array,parties:array}>|null
 */
function normaliser_communes( $brutes ): ?array {
	if ( ! is_array( $brutes ) ) {
		return null;
	}

	$communes = array();

	foreach ( $brutes as $brute ) {
		if ( ! is_array( $brute ) ) {
			return null;
		}

		$insee = texte( $brute, 'insee' );
		$nom   = texte( $brute, 'nom' );

		if ( '' === $insee || '' === trim( $nom ) ) {
			return null;
		}

		if ( ! isset( $brute['bbox'] ) || ! is_array( $brute['bbox'] ) || 4 !== count( $brute['bbox'] ) ) {
			return null;
		}

		$parties = normaliser_parties( isset( $brute['parties'] ) ? $brute['parties'] : null );

		if ( null === $parties ) {
			return null;
		}

		$communes[] = array(
			'insee'   => $insee,
			// Le nom est le `nom_officiel` de l'archive, VERBATIM, UTF-8 brut, jamais
			// échappé ici : les mêmes chaînes alimentent du JSON et un rendu HTML, et
			// des entités HTML dans du JSON seraient une corruption de donnée, pas une
			// protection. L'échappement appartient au point de sortie.
			'nom'     => $nom,
			'dep'     => texte( $brute, 'dep' ),
			'bbox'    => array(
				'ouest' => (float) $brute['bbox'][0],
				'sud'   => (float) $brute['bbox'][1],
				'est'   => (float) $brute['bbox'][2],
				'nord'  => (float) $brute['bbox'][3],
			),
			'parties' => $parties,
		);
	}

	return $communes;
}

/**
 * Valide les parties d'une commune : chaque partie = [extérieur, ...trous].
 *
 * Les anneaux sont APLATIS — `[lon, lat, lon, lat, …]`. Un tableau de couples
 * ferait allouer deux tableaux PHP par sommet, soit 56 000 tableaux pour rien.
 *
 * @param mixed $brutes Valeur brute.
 * @return array<int,array<int,array<int,float>>>|null
 */
function normaliser_parties( $brutes ): ?array {
	if ( ! is_array( $brutes ) || array() === $brutes ) {
		return null;
	}

	$parties = array();

	foreach ( $brutes as $partie ) {
		if ( ! is_array( $partie ) || array() === $partie ) {
			return null;
		}

		$anneaux = array();

		foreach ( $partie as $anneau ) {
			// Six valeurs = trois sommets : moins ne délimite aucune surface.
			if ( ! is_array( $anneau ) || count( $anneau ) < 6 || 0 !== count( $anneau ) % 2 ) {
				return null;
			}

			$anneaux[] = $anneau;
		}

		$parties[] = $anneaux;
	}

	return $parties;
}

/**
 * Réponse « aucune commune », de forme identique à la réponse nominale.
 *
 * TOUTES les clés du contrat sont présentes : le consommateur n'écrit jamais
 * `isset()`.
 *
 * @param string $etat Constante d'état du §7.
 * @return array{trouvee:bool,insee:string,nom:string,departement:string,distance_m:?int,etat:string}
 */
function commune_absente( string $etat ): array {
	return array(
		'trouvee'     => false,
		'insee'       => '',
		'nom'         => '',
		'departement' => '',
		'distance_m'  => null,
		'etat'        => $etat,
	);
}

/**
 * Commune d'une zone de feu, ou l'absence explicite de commune.
 *
 * **La distance se mesure depuis la GÉOMÉTRIE de la zone, jamais depuis un point
 * qui la résume** (§11.7.d). Une zone EFFIS de 30 ha et plus est couramment en
 * croissant, en L, ou en plusieurs parties : le centre de sa bbox peut tomber
 * hors de la zone, dans une commune que le feu n'a jamais touchée, ou en mer. Et
 * le plafond de 5 km ne rattrape pas cela — un centre situé à 2 km du foyer réel,
 * à l'intérieur d'une commune voisine, renverrait le nom de cette voisine avec
 * assurance et SOUS le plafond. C'est le raisonnement exact qui a fait refuser
 * les points chefs-lieux.
 *
 * La règle et son départage :
 *
 *   1. la commune la plus proche de la géométrie de la zone ;
 *   2. si plusieurs sont à distance zéro — la zone les chevauche — celle qui
 *      porte la plus grande PART DE SURFACE de la zone l'emporte ;
 *   3. à part strictement égale, le plus petit `code_insee` tranche. Ce dernier
 *      critère est là pour la REPRODUCTIBILITÉ, pas pour la correction : le cas
 *      est de mesure nulle, mais la recette compare des artefacts octet par
 *      octet, et une sortie non déterministe y perdrait tout sens.
 *
 * La fonction est STRUCTURELLEMENT INCAPABLE de renvoyer un nom au-delà du
 * plafond ou hors de l'emprise couverte : les deux bornes sont franchies avant
 * qu'un nom ne soit composé, jamais après.
 *
 * @param array $geometrie Géométrie GeoJSON de la zone, `Polygon` ou `MultiPolygon`.
 * @return array{trouvee:bool,insee:string,nom:string,departement:string,distance_m:?int,etat:string}
 */
function commune_de_la_zone( array $geometrie ): array {
	$lookup = lookup_communes();

	if ( ! $lookup['disponible'] ) {
		return commune_absente( $lookup['raison'] );
	}

	$zone = normaliser_geometrie_zone( $geometrie );

	// Géométrie inexploitable : on ne sait pas, et on le dit. Deviner une commune
	// depuis une géométrie qu'on n'a pas su lire serait affirmer sans mesurer.
	if ( null === $zone ) {
		return commune_absente( ETAT_COMMUNES_INCONNUES );
	}

	$couverture = $lookup['couverture'];

	/*
	 * COUVERTURE : l'emprise de la zone doit tenir ENTIÈRE dans la couverture.
	 *
	 * La couverture est l'emprise de découpe de l'extrait rétrécie du plafond.
	 * Toute commune située à moins de 5 km d'un point de la couverture a donc au
	 * moins un point dans l'emprise de découpe, et figure dans l'extrait. Cette
	 * garantie ne vaut que si la zone ENTIÈRE est dans la couverture : une zone
	 * qui déborde pourrait avoir, hors extrait, une commune plus proche que celles
	 * que nous connaissons — et nous nommerions la deuxième en la présentant comme
	 * la plus proche.
	 */
	if (
		$zone['bbox']['ouest'] < $couverture['ouest'] || $zone['bbox']['est'] > $couverture['est']
		|| $zone['bbox']['sud'] < $couverture['sud'] || $zone['bbox']['nord'] > $couverture['nord']
	) {
		return commune_absente( RAISON_COMMUNES_HORS_COUVERTURE );
	}

	$plafond    = (float) $lookup['plafond_m'];
	$marge_lon  = $plafond / $lookup['lon_m'];
	$marge_lat  = $plafond / $lookup['lat_m'];
	$candidates = array();

	foreach ( $lookup['communes'] as $commune ) {
		if ( ! emprises_se_recoupent( $zone['bbox'], $commune['bbox'], $marge_lon, $marge_lat ) ) {
			continue;
		}

		$candidates[] = array(
			'commune' => $commune,
			'ecart'   => distance_emprises( $zone['bbox'], $commune['bbox'], $lookup['lon_m'], $lookup['lat_m'] ),
		);
	}

	if ( array() === $candidates ) {
		return commune_absente( RAISON_COMMUNES_HORS_COUVERTURE );
	}

	/*
	 * Tri par écart d'EMPRISES croissant. L'écart d'emprises minore la distance
	 * réelle : la commune la plus proche est donc évaluée en premier, sa distance
	 * devient une borne serrée, et toutes les suivantes sont abandonnées d'un coup.
	 * Sans ce tri, une zone en mer parcourait intégralement chacune des communes
	 * candidates — mesuré : 7,5 s contre 0,7 s sur une zone de 20 000 sommets.
	 *
	 * `usort` est STABLE depuis PHP 8.0 : à écart égal, l'ordre de l'artefact — le
	 * code INSEE croissant — est conservé, et le départage documenté tient.
	 */
	usort(
		$candidates,
		static function ( array $a, array $b ): int {
			return $a['ecart'] <=> $b['ecart'];
		}
	);

	/*
	 * Chevauchement d'abord, distance ensuite : une commune chevauchée est à
	 * distance zéro, et aucune distance ne peut faire mieux. Seules les communes
	 * dont l'emprise recoupe celle de la zone — écart nul — peuvent la chevaucher ;
	 * les autres n'ont même pas à être testées.
	 */
	$chevauchees = array();

	foreach ( $candidates as $candidate ) {
		if ( 0.0 !== $candidate['ecart'] ) {
			break;
		}

		if ( zone_chevauche_commune( $zone, $candidate['commune'] ) ) {
			$chevauchees[] = $candidate['commune'];
		}
	}

	if ( array() !== $chevauchees ) {
		return commune_trouvee( departager_chevauchees( $zone, $chevauchees ), 0 );
	}

	$meilleure = null;
	$distance  = $plafond;

	foreach ( $candidates as $candidate ) {
		// Les candidates sont triées : dès que l'écart d'emprises dépasse la
		// meilleure distance connue, aucune suivante ne peut faire mieux.
		if ( $candidate['ecart'] > $distance ) {
			break;
		}

		$mesure = distance_zone_commune( $zone, $candidate['commune'], $lookup['lon_m'], $lookup['lat_m'], $distance );

		// Comparaison STRICTE : à distance strictement égale, la commune déjà
		// retenue gagne, et le tri stable a conservé l'ordre du code INSEE. C'est
		// le même départage que celui des parts, et pour la même raison : la
		// reproductibilité, pas une préférence entre deux communes.
		if ( $mesure < $distance ) {
			$distance  = $mesure;
			$meilleure = $candidate['commune'];
		}
	}

	if ( null === $meilleure ) {
		return commune_absente( RAISON_COMMUNES_HORS_COUVERTURE );
	}

	return commune_trouvee( $meilleure, (int) round( $distance ) );
}

/**
 * Départage des communes chevauchées par la part de surface de la zone.
 *
 * La part n'est calculée QUE s'il y a plus d'une commune chevauchée : le cas
 * courant — un feu dans une seule commune — ne paie jamais l'intersection.
 *
 * @param array               $zone        Zone normalisée.
 * @param array<int,array>    $chevauchees Communes chevauchées, dans l'ordre de l'artefact.
 * @return array
 */
function departager_chevauchees( array $zone, array $chevauchees ): array {
	if ( 1 === count( $chevauchees ) ) {
		return $chevauchees[0];
	}

	$meilleure = null;
	$part      = -1.0;

	foreach ( $chevauchees as $commune ) {
		$mesure = aire_intersection( $zone['parties'], orienter_parties( $commune['parties'] ) );

		// Comparaison STRICTE : à part strictement égale, la commune déjà retenue
		// gagne, et l'artefact est trié par code INSEE — donc le plus petit code
		// tranche. Arbitraire, stable, documenté : c'est tout ce qu'un départage de
		// mesure nulle doit être. Ce n'est PAS une préférence entre deux communes.
		if ( $mesure > $part ) {
			$part      = $mesure;
			$meilleure = $commune;
		}
	}

	return $meilleure;
}

/**
 * Réponse nominale, composée en un seul endroit.
 *
 * @param array $commune    Entrée de l'artefact.
 * @param int   $distance_m Distance au bord, en mètres. Zéro si la zone chevauche la commune.
 * @return array{trouvee:bool,insee:string,nom:string,departement:string,distance_m:?int,etat:string}
 */
function commune_trouvee( array $commune, int $distance_m ): array {
	return array(
		'trouvee'     => true,
		'insee'       => $commune['insee'],
		'nom'         => $commune['nom'],
		'departement' => $commune['dep'],
		// Jamais au-dessus du plafond : l'appelant peut le lire sans le revérifier.
		'distance_m'  => min( $distance_m, PLAFOND_COMMUNE_M ),
		'etat'        => ETAT_COMMUNES_OK,
	);
}

/**
 * Deux emprises se recoupent-elles, l'une étant élargie d'une marge ?
 *
 * @param array $a         Première emprise.
 * @param array $b         Seconde emprise, élargie de la marge.
 * @param float $marge_lon Marge en degrés de longitude.
 * @param float $marge_lat Marge en degrés de latitude.
 */
function emprises_se_recoupent( array $a, array $b, float $marge_lon, float $marge_lat ): bool {
	return $a['ouest'] <= $b['est'] + $marge_lon
		&& $a['est'] >= $b['ouest'] - $marge_lon
		&& $a['sud'] <= $b['nord'] + $marge_lat
		&& $a['nord'] >= $b['sud'] - $marge_lat;
}

/**
 * Le point est-il dans une surface ? Anneau extérieur oui, trous non.
 *
 * Sert dans les DEUX SENS — un sommet de zone dans une commune, un sommet de
 * commune dans la zone — parce que les deux portent la même forme.
 *
 * @param float $lon     Longitude.
 * @param float $lat     Latitude.
 * @param array $parties Parties de la surface.
 */
function dans_parties( float $lon, float $lat, array $parties ): bool {
	foreach ( $parties as $partie ) {
		if ( ! dans_anneau( $lon, $lat, $partie[0] ) ) {
			continue;
		}

		$dans_trou = false;

		foreach ( array_slice( $partie, 1 ) as $trou ) {
			if ( dans_anneau( $lon, $lat, $trou ) ) {
				$dans_trou = true;
				break;
			}
		}

		if ( ! $dans_trou ) {
			return true;
		}
	}

	return false;
}

/**
 * Lancer de rayon sur un anneau aplati.
 *
 * Le test se fait en degrés bruts : la projection locale est une mise à
 * l'échelle affine des deux axes, qui ne change ni le côté d'un point par
 * rapport à un segment, ni le nombre de traversées.
 *
 * @param float             $lon    Longitude.
 * @param float             $lat    Latitude.
 * @param array<int,float>  $anneau Anneau aplati `[lon, lat, lon, lat, …]`.
 */
function dans_anneau( float $lon, float $lat, array $anneau ): bool {
	$sommets = count( $anneau ) / 2;
	$dedans  = false;

	for ( $i = 0, $j = $sommets - 1; $i < $sommets; $j = $i, $i++ ) {
		$xi = (float) $anneau[ 2 * $i ];
		$yi = (float) $anneau[ 2 * $i + 1 ];
		$xj = (float) $anneau[ 2 * $j ];
		$yj = (float) $anneau[ 2 * $j + 1 ];

		if ( ( $yi > $lat ) !== ( $yj > $lat ) && $lon < ( ( $xj - $xi ) * ( $lat - $yi ) ) / ( $yj - $yi ) + $xi ) {
			$dedans = ! $dedans;
		}
	}

	return $dedans;
}

/**
 * Carré de la distance d'un point au bord le plus proche d'une surface.
 *
 * Le CARRÉ, et non la distance : la racine est prise une seule fois, tout en
 * haut, par l'appelant. Comparer des carrés est exact et épargne une racine par
 * segment — sur une zone appariée à une commune, cela se compte en dizaines de
 * milliers.
 *
 * @param float $lon     Longitude.
 * @param float $lat     Latitude.
 * @param array $parties Parties de la surface.
 * @param float $lon_m   Mètres par degré de longitude.
 * @param float $lat_m   Mètres par degré de latitude.
 */
function distance_carree_point_parties( float $lon, float $lat, array $parties, float $lon_m, float $lat_m ): float {
	$px      = $lon * $lon_m;
	$py      = $lat * $lat_m;
	$minimum = INF;

	foreach ( $parties as $partie ) {
		foreach ( $partie as $anneau ) {
			$sommets = count( $anneau ) / 2;

			for ( $i = 0, $j = $sommets - 1; $i < $sommets; $j = $i, $i++ ) {
				$ax    = (float) $anneau[ 2 * $j ] * $lon_m;
				$ay    = (float) $anneau[ 2 * $j + 1 ] * $lat_m;
				$bx    = (float) $anneau[ 2 * $i ] * $lon_m;
				$by    = (float) $anneau[ 2 * $i + 1 ] * $lat_m;
				$dx    = $bx - $ax;
				$dy    = $by - $ay;
				$carre = $dx * $dx + $dy * $dy;
				$t     = 0.0;

				if ( $carre > 0.0 ) {
					$t = max( 0.0, min( 1.0, ( ( $px - $ax ) * $dx + ( $py - $ay ) * $dy ) / $carre ) );
				}

				$ex             = $ax + $t * $dx - $px;
				$ey             = $ay + $t * $dy - $py;
				$carre_distance = $ex * $ex + $ey * $ey;

				if ( $carre_distance < $minimum ) {
					$minimum = $carre_distance;
				}
			}
		}
	}

	return $minimum;
}

/**
 * Normalise la géométrie d'une zone de feu en parties, anneaux plats, orientés.
 *
 * NE FAIT AUCUNE CONFIANCE À L'ENTRÉE. La géométrie vient d'une source externe :
 * type, structure, arité et finitude sont contrôlés ici, et une géométrie qui ne
 * passe pas est REFUSÉE plutôt que redressée. Redresser reviendrait à inventer
 * une zone.
 *
 * L'orientation est imposée PAR POSITION, comme le veut GeoJSON : l'anneau 0 est
 * l'extérieur, les suivants sont des trous. C'est ce qui rend les aires signées
 * utilisables pour l'intersection, sans dépendre d'un producteur qui respecte ou
 * non la règle d'orientation de la RFC 7946.
 *
 * @param array $geometrie Géométrie GeoJSON brute.
 * @return array{parties:array,bbox:array}|null Null si la géométrie est inexploitable.
 */
function normaliser_geometrie_zone( array $geometrie ): ?array {
	$type = isset( $geometrie['type'] ) && is_string( $geometrie['type'] ) ? $geometrie['type'] : '';

	if ( ! isset( $geometrie['coordinates'] ) || ! is_array( $geometrie['coordinates'] ) ) {
		return null;
	}

	if ( 'Polygon' === $type ) {
		$brutes = array( $geometrie['coordinates'] );
	} elseif ( 'MultiPolygon' === $type ) {
		$brutes = $geometrie['coordinates'];
	} else {
		return null;
	}

	$parties = array();
	$bbox    = array(
		'ouest' => INF,
		'sud'   => INF,
		'est'   => -INF,
		'nord'  => -INF,
	);

	foreach ( $brutes as $partie ) {
		if ( ! is_array( $partie ) || array() === $partie ) {
			return null;
		}

		$anneaux = array();
		$rang    = 0;

		foreach ( $partie as $anneau ) {
			if ( ! is_array( $anneau ) || count( $anneau ) < 3 ) {
				return null;
			}

			$plat = array();

			foreach ( $anneau as $position ) {
				if ( ! is_array( $position ) || ! isset( $position[0], $position[1] ) ) {
					return null;
				}

				if ( ! is_numeric( $position[0] ) || ! is_numeric( $position[1] ) ) {
					return null;
				}

				$lon = (float) $position[0];
				$lat = (float) $position[1];

				if ( ! is_finite( $lon ) || ! is_finite( $lat ) ) {
					return null;
				}

				$plat[] = $lon;
				$plat[] = $lat;

				$bbox['ouest'] = min( $bbox['ouest'], $lon );
				$bbox['est']   = max( $bbox['est'], $lon );
				$bbox['sud']   = min( $bbox['sud'], $lat );
				$bbox['nord']  = max( $bbox['nord'], $lat );
			}

			// Extérieur en sens direct, trous en sens indirect : c'est la convention
			// qui fait que la somme des aires signées vaut la surface réelle.
			$anneaux[] = orienter_anneau( $plat, 0 === $rang );
			++$rang;
		}

		$parties[] = $anneaux;
	}

	if ( array() === $parties || INF === $bbox['ouest'] ) {
		return null;
	}

	return array(
		'parties' => $parties,
		'bbox'    => $bbox,
	);
}

/**
 * Aire signée d'un anneau plat, en degrés carrés.
 *
 * Le signe seul est utilisé pour l'orientation ; la valeur sert aux aires
 * d'intersection, où seuls des RAPPORTS sont comparés. La projection locale
 * étant une mise à l'échelle affine des deux axes, elle multiplie toutes les
 * aires par la même constante : le rapport ne bouge pas. Travailler en degrés
 * carrés évite donc une projection inutile.
 *
 * @param array<int,float> $anneau Anneau aplati.
 */
function aire_signee_anneau( array $anneau ): float {
	$sommets = count( $anneau ) / 2;
	$somme   = 0.0;

	for ( $i = 0, $j = $sommets - 1; $i < $sommets; $j = $i, $i++ ) {
		$somme += ( (float) $anneau[ 2 * $j ] * (float) $anneau[ 2 * $i + 1 ] )
			- ( (float) $anneau[ 2 * $i ] * (float) $anneau[ 2 * $j + 1 ] );
	}

	return $somme / 2;
}

/**
 * Impose le sens d'un anneau : direct pour un extérieur, indirect pour un trou.
 *
 * @param array<int,float> $anneau    Anneau aplati.
 * @param bool             $exterieur L'anneau est-il l'extérieur de sa partie ?
 * @return array<int,float>
 */
function orienter_anneau( array $anneau, bool $exterieur ): array {
	$aire = aire_signee_anneau( $anneau );

	if ( ( $exterieur && $aire >= 0.0 ) || ( ! $exterieur && $aire <= 0.0 ) ) {
		return $anneau;
	}

	$inverse = array();

	for ( $i = count( $anneau ) - 2; $i >= 0; $i -= 2 ) {
		$inverse[] = $anneau[ $i ];
		$inverse[] = $anneau[ $i + 1 ];
	}

	return $inverse;
}

/**
 * Impose l'orientation à toutes les parties d'une surface.
 *
 * Appelé À LA DEMANDE sur une commune, et jamais au chargement : seul le
 * départage des chevauchements a besoin d'aires signées, et il ne concerne
 * qu'une poignée de communes. Orienter les 298 à chaque ouverture ferait payer
 * un cas rare au cas courant.
 *
 * @param array $parties Parties de la surface.
 * @return array
 */
function orienter_parties( array $parties ): array {
	$orientees = array();

	foreach ( $parties as $partie ) {
		$anneaux = array();
		$rang    = 0;

		foreach ( $partie as $anneau ) {
			$anneaux[] = orienter_anneau( $anneau, 0 === $rang );
			++$rang;
		}

		$orientees[] = $anneaux;
	}

	return $orientees;
}

/**
 * La zone chevauche-t-elle la commune ?
 *
 * Trois cas, et il faut les trois : un sommet de la zone dans la commune (zone
 * incluse, ou débordante) ; un sommet de la commune dans la zone (commune
 * incluse) ; deux bords qui se croisent (deux surfaces en croix, dont aucun
 * sommet n'est dans l'autre). Omettre le troisième laisserait passer une zone
 * qui traverse une commune de part en part.
 *
 * @param array $zone    Zone normalisée.
 * @param array $commune Entrée de l'artefact.
 */
function zone_chevauche_commune( array $zone, array $commune ): bool {
	if ( ! emprises_se_recoupent( $zone['bbox'], $commune['bbox'], 0.0, 0.0 ) ) {
		return false;
	}

	/*
	 * UN SEUL SOMMET PAR PARTIE suffit à conclure au chevauchement : un sommet
	 * dedans, et les deux surfaces se chevauchent, quoi que fassent les autres.
	 * Tester les 20 000 sommets d'une zone dense contre les 5 000 d'une commune
	 * n'apprendrait rien de plus et coûterait cent millions d'opérations.
	 */
	foreach ( $zone['parties'] as $partie ) {
		if ( dans_parties( (float) $partie[0][0], (float) $partie[0][1], $commune['parties'] ) ) {
			return true;
		}
	}

	foreach ( $commune['parties'] as $partie ) {
		if ( dans_parties( (float) $partie[0][0], (float) $partie[0][1], $zone['parties'] ) ) {
			return true;
		}
	}

	/*
	 * Aucun sommet témoin dedans : il reste le cas des deux surfaces EN CROIX,
	 * dont aucun sommet n'est dans l'autre et qui se chevauchent pourtant. C'est
	 * le seul cas qui exige de parcourir les bords, et c'est aussi le seul qui
	 * puisse conclure à la NON-intersection — les deux tests ci-dessus, eux, ne
	 * savent que conclure au chevauchement.
	 */
	return bords_se_croisent( $zone['parties'], $commune['parties'] );
}

/**
 * Deux jeux d'anneaux ont-ils au moins un bord qui en croise un autre ?
 *
 * @param array $a Parties de la première surface.
 * @param array $b Parties de la seconde surface.
 */
function bords_se_croisent( array $a, array $b ): bool {
	foreach ( $a as $partie_a ) {
		foreach ( $partie_a as $anneau_a ) {
			$na = count( $anneau_a ) / 2;

			for ( $i = 0, $j = $na - 1; $i < $na; $j = $i, $i++ ) {
				$ax      = (float) $anneau_a[ 2 * $j ];
				$ay      = (float) $anneau_a[ 2 * $j + 1 ];
				$bx      = (float) $anneau_a[ 2 * $i ];
				$by      = (float) $anneau_a[ 2 * $i + 1 ];
				$a_ouest = min( $ax, $bx );
				$a_est   = max( $ax, $bx );
				$a_sud   = min( $ay, $by );
				$a_nord  = max( $ay, $by );

				foreach ( $b as $partie_b ) {
					foreach ( $partie_b as $anneau_b ) {
						$nb = count( $anneau_b ) / 2;

						for ( $k = 0, $l = $nb - 1; $k < $nb; $l = $k, $k++ ) {
							$cx = (float) $anneau_b[ 2 * $l ];
							$cy = (float) $anneau_b[ 2 * $l + 1 ];
							$dx = (float) $anneau_b[ 2 * $k ];
							$dy = (float) $anneau_b[ 2 * $k + 1 ];

							// Rejet par emprise de segment : quatre comparaisons contre
							// quatre produits en croix. C'est ce qui rend la double boucle
							// tenable sur une commune de plusieurs centaines de sommets.
							if ( min( $cx, $dx ) > $a_est || max( $cx, $dx ) < $a_ouest ) {
								continue;
							}

							if ( min( $cy, $dy ) > $a_nord || max( $cy, $dy ) < $a_sud ) {
								continue;
							}

							if ( segments_se_croisent( $ax, $ay, $bx, $by, $cx, $cy, $dx, $dy ) ) {
								return true;
							}
						}
					}
				}
			}
		}
	}

	return false;
}

/**
 * Deux segments se croisent-ils PROPREMENT, en un point intérieur à chacun ?
 *
 * Le prédicat compare des signes STRICTS (`> 0`), ce qui range le zéro avec les
 * négatifs : un contact par une extrémité — produit en croix nul — n'est donc
 * détecté que selon le côté d'où vient l'autre segment, et la même configuration
 * peut répondre `true` ou `false`. LIMITE ASSUMÉE, de mesure nulle : la fonction
 * n'est appelée que par `bords_se_croisent()`, dans la branche « deux surfaces en
 * croix » de `zone_chevauche_commune()`, après les deux tests de sommet-dans-
 * polygone qui attrapent déjà tout chevauchement d'aire non nulle.
 *
 * @param float $ax Abscisse du premier point du premier segment.
 * @param float $ay Ordonnée du premier point du premier segment.
 * @param float $bx Abscisse du second point du premier segment.
 * @param float $by Ordonnée du second point du premier segment.
 * @param float $cx Abscisse du premier point du second segment.
 * @param float $cy Ordonnée du premier point du second segment.
 * @param float $dx Abscisse du second point du second segment.
 * @param float $dy Ordonnée du second point du second segment.
 */
function segments_se_croisent( float $ax, float $ay, float $bx, float $by, float $cx, float $cy, float $dx, float $dy ): bool {
	$d1 = ( $dx - $cx ) * ( $ay - $cy ) - ( $dy - $cy ) * ( $ax - $cx );
	$d2 = ( $dx - $cx ) * ( $by - $cy ) - ( $dy - $cy ) * ( $bx - $cx );
	$d3 = ( $bx - $ax ) * ( $cy - $ay ) - ( $by - $ay ) * ( $cx - $ax );
	$d4 = ( $bx - $ax ) * ( $dy - $ay ) - ( $by - $ay ) * ( $dx - $ax );

	return ( ( $d1 > 0 ) !== ( $d2 > 0 ) ) && ( ( $d3 > 0 ) !== ( $d4 > 0 ) );
}

/**
 * Distance entre la géométrie d'une zone et le bord d'une commune, en mètres.
 *
 * N'est appelée que sur des surfaces DISJOINTES — le chevauchement est tranché
 * avant, et vaut zéro. Sur deux surfaces disjointes, la distance minimale est
 * atteinte entre un sommet de l'une et un bord de l'autre : les deux sens sont
 * donc mesurés, et aucun autre couple n'est nécessaire.
 *
 * `$borne` élague : une commune dont l'emprise est déjà plus loin que la
 * meilleure distance connue n'est pas parcourue du tout.
 *
 * @param array $zone    Zone normalisée.
 * @param array $commune Entrée de l'artefact.
 * @param float $lon_m   Mètres par degré de longitude.
 * @param float $lat_m   Mètres par degré de latitude.
 * @param float $borne   Meilleure distance connue, en mètres.
 * @return float Distance en mètres, ou `INF` si l'élagage a coupé.
 */
function distance_zone_commune( array $zone, array $commune, float $lon_m, float $lat_m, float $borne ): float {
	// Élagage STRICT : une commune dont l'emprise est exactement à la borne peut
	// encore ÉGALER la meilleure distance, et l'égalité a son propre départage.
	// L'élaguer ici le court-circuiterait en silence.
	if ( distance_emprises( $zone['bbox'], $commune['bbox'], $lon_m, $lat_m ) > $borne ) {
		return INF;
	}

	$minimum = min(
		distance_carree_sommets_parties( $zone['parties'], $commune['parties'], $lon_m, $lat_m ),
		distance_carree_sommets_parties( $commune['parties'], $zone['parties'], $lon_m, $lat_m )
	);

	// La racine est prise UNE FOIS, ici : tout ce qui précède compare des carrés.
	return INF === $minimum ? INF : sqrt( $minimum );
}

/**
 * Plus petit carré de distance entre les SOMMETS d'une surface et les BORDS d'une autre.
 *
 * Les deux sens de mesure de `distance_zone_commune()` ne diffèrent que par
 * l'ordre de leurs deux arguments : les écrire deux fois donnait deux boucles
 * qu'une correction n'aurait touchées qu'à moitié.
 *
 * @param array $sommets Parties dont les sommets sont parcourus.
 * @param array $bords   Parties dont les bords sont mesurés.
 * @param float $lon_m   Mètres par degré de longitude.
 * @param float $lat_m   Mètres par degré de latitude.
 * @return float Carré de la distance, ou `INF` si aucun sommet n'est parcouru.
 */
function distance_carree_sommets_parties( array $sommets, array $bords, float $lon_m, float $lat_m ): float {
	$minimum = INF;

	foreach ( $sommets as $partie ) {
		foreach ( $partie as $anneau ) {
			$nombre = count( $anneau ) / 2;

			for ( $i = 0; $i < $nombre; $i++ ) {
				$carre = distance_carree_point_parties(
					(float) $anneau[ 2 * $i ],
					(float) $anneau[ 2 * $i + 1 ],
					$bords,
					$lon_m,
					$lat_m
				);

				if ( $carre < $minimum ) {
					$minimum = $carre;
				}
			}
		}
	}

	return $minimum;
}

/**
 * Distance entre deux emprises, en mètres. Zéro si elles se recoupent.
 *
 * @param array $a     Première emprise.
 * @param array $b     Seconde emprise.
 * @param float $lon_m Mètres par degré de longitude.
 * @param float $lat_m Mètres par degré de latitude.
 */
function distance_emprises( array $a, array $b, float $lon_m, float $lat_m ): float {
	$ecart_lon = max( 0.0, max( $a['ouest'] - $b['est'], $b['ouest'] - $a['est'] ) ) * $lon_m;
	$ecart_lat = max( 0.0, max( $a['sud'] - $b['nord'], $b['sud'] - $a['nord'] ) ) * $lat_m;

	return sqrt( $ecart_lon * $ecart_lon + $ecart_lat * $ecart_lat );
}

/**
 * Aire de l'intersection de deux surfaces, en degrés carrés.
 *
 * MÉTHODE, et ses limites, écrites ici parce qu'elles conditionnent la lecture
 * du résultat. Chaque surface est décomposée en un ÉVENTAIL DE TRIANGLES SIGNÉS
 * depuis le premier sommet de chacun de ses anneaux : la fonction indicatrice
 * d'un anneau est la somme des indicatrices de ses triangles, affectées du signe
 * de leur orientation. Il en découle
 *
 *     aire( A ∩ B ) = Σ_a Σ_b signe(a) · signe(b) · aire( |a| ∩ |b| )
 *
 * et l'intersection de deux TRIANGLES est convexe, donc calculable par un simple
 * découpage de Sutherland-Hodgman. Les trous sont pris en charge par les signes,
 * sans traitement particulier — c'est pour cela que l'orientation est imposée
 * par position en amont.
 *
 * CE N'EST PAS un découpeur de polygones général et ne doit pas le devenir : pas
 * de gestion des auto-intersections, pas de couche de robustesse numérique. La
 * seule décision qui en dépend est le DÉPARTAGE entre deux communes que la zone
 * chevauche déjà toutes les deux — chacune est donc une réponse vraie, et une
 * erreur d'arbitrage nomme la seconde plus grande, jamais une commune que le feu
 * n'a pas touchée. Ce plafond de conséquence est ce qui autorise cette méthode
 * plutôt qu'une bibliothèque.
 *
 * @param array $a Parties de la première surface, orientées.
 * @param array $b Parties de la seconde surface, orientées.
 */
function aire_intersection( array $a, array $b ): float {
	$triangles_a = triangles_signes( $a );
	$triangles_b = triangles_signes( $b );
	$total       = 0.0;

	foreach ( $triangles_a as $ta ) {
		foreach ( $triangles_b as $tb ) {
			if ( $ta['ouest'] > $tb['est'] || $ta['est'] < $tb['ouest'] ) {
				continue;
			}

			if ( $ta['sud'] > $tb['nord'] || $ta['nord'] < $tb['sud'] ) {
				continue;
			}

			$aire = aire_intersection_triangles( $ta['sommets'], $tb['sommets'] );

			if ( $aire > 0.0 ) {
				$total += $ta['signe'] * $tb['signe'] * $aire;
			}
		}
	}

	// Le total est positif par construction ; le plancher borne le bruit de
	// l'arithmétique flottante, jamais une aire réellement négative.
	return max( 0.0, $total );
}

/**
 * Éventail de triangles signés d'une surface.
 *
 * Le sommet de l'éventail est le PREMIER SOMMET DE CHAQUE ANNEAU, et non
 * l'origine du repère : les triangles restent alors petits et locaux, ce qui
 * rend le rejet par emprise efficace. L'identité de l'éventail vaut pour
 * n'importe quel sommet.
 *
 * @param array $parties Parties de la surface, orientées.
 * @return array<int,array{sommets:array<int,float>,signe:int,ouest:float,est:float,sud:float,nord:float}>
 */
function triangles_signes( array $parties ): array {
	$triangles = array();

	foreach ( $parties as $partie ) {
		foreach ( $partie as $anneau ) {
			$sommets = count( $anneau ) / 2;

			if ( $sommets < 3 ) {
				continue;
			}

			$px = (float) $anneau[0];
			$py = (float) $anneau[1];

			for ( $i = 1; $i + 1 < $sommets; $i++ ) {
				$ax    = (float) $anneau[ 2 * $i ];
				$ay    = (float) $anneau[ 2 * $i + 1 ];
				$bx    = (float) $anneau[ 2 * $i + 2 ];
				$by    = (float) $anneau[ 2 * $i + 3 ];
				$croix = ( $ax - $px ) * ( $by - $py ) - ( $ay - $py ) * ( $bx - $px );

				if ( 0.0 === $croix ) {
					continue;
				}

				// Le triangle est stocké en sens DIRECT et son orientation d'origine
				// part dans `signe` : le découpage convexe exige un sens connu.
				$triangles[] = array(
					'sommets' => $croix > 0.0
						? array( $px, $py, $ax, $ay, $bx, $by )
						: array( $px, $py, $bx, $by, $ax, $ay ),
					'signe'   => $croix > 0.0 ? 1 : -1,
					'ouest'   => min( $px, $ax, $bx ),
					'est'     => max( $px, $ax, $bx ),
					'sud'     => min( $py, $ay, $by ),
					'nord'    => max( $py, $ay, $by ),
				);
			}
		}
	}

	return $triangles;
}

/**
 * Aire de l'intersection de deux triangles en sens direct.
 *
 * Découpage de Sutherland-Hodgman : le premier triangle est rogné par les trois
 * demi-plans du second. Les deux étant convexes, le résultat l'est aussi et son
 * aire se calcule au lacet.
 *
 * @param array<int,float> $sujet    Triangle rogné, `[x1,y1,x2,y2,x3,y3]`, sens direct.
 * @param array<int,float> $rogneur  Triangle rogneur, même forme, sens direct.
 */
function aire_intersection_triangles( array $sujet, array $rogneur ): float {
	$sortie = array( array( $sujet[0], $sujet[1] ), array( $sujet[2], $sujet[3] ), array( $sujet[4], $sujet[5] ) );

	for ( $bord = 0; $bord < 3; $bord++ ) {
		$cx     = $rogneur[ 2 * $bord ];
		$cy     = $rogneur[ 2 * $bord + 1 ];
		$dx     = $rogneur[ ( 2 * $bord + 2 ) % 6 ];
		$dy     = $rogneur[ ( 2 * $bord + 3 ) % 6 ];
		$entree = $sortie;
		$sortie = array();
		$nombre = count( $entree );

		if ( 0 === $nombre ) {
			return 0.0;
		}

		for ( $i = 0; $i < $nombre; $i++ ) {
			$p      = $entree[ $i ];
			$q      = $entree[ ( $i + 1 ) % $nombre ];
			$cote_p = ( $dx - $cx ) * ( $p[1] - $cy ) - ( $dy - $cy ) * ( $p[0] - $cx );
			$cote_q = ( $dx - $cx ) * ( $q[1] - $cy ) - ( $dy - $cy ) * ( $q[0] - $cx );

			if ( $cote_p >= 0.0 ) {
				$sortie[] = $p;
			}

			if ( ( $cote_p >= 0.0 ) !== ( $cote_q >= 0.0 ) ) {
				$t = $cote_p / ( $cote_p - $cote_q );
				$sortie[] = array( $p[0] + $t * ( $q[0] - $p[0] ), $p[1] + $t * ( $q[1] - $p[1] ) );
			}
		}
	}

	$nombre = count( $sortie );

	if ( $nombre < 3 ) {
		return 0.0;
	}

	$somme = 0.0;

	for ( $i = 0, $j = $nombre - 1; $i < $nombre; $j = $i, $i++ ) {
		$somme += ( $sortie[ $j ][0] * $sortie[ $i ][1] ) - ( $sortie[ $i ][0] * $sortie[ $j ][1] );
	}

	return abs( $somme ) / 2;
}

/**
 * Mention de source §9 du référentiel communal.
 *
 * SÉPARÉE de `attribution()`, qui porte la DDTM : deux producteurs, deux
 * licences, deux millésimes. Les fusionner produirait une phrase qui n'attribue
 * correctement ni l'un ni l'autre, et la Licence Ouverte 2.0 impose une
 * citation exacte de la source et de sa date.
 *
 * TOTALE : toujours peuplée, y compris référentiel absent — le thème n'écrit
 * jamais `isset()`.
 *
 * @return array{phrase:string,phrase_courte:string,lien_source:string,lien_licence:string,faits:array<string,string>}
 */
function attribution_communes(): array {
	$communes    = bloc( donnees()['meta'], 'communes' );
	$attribution = bloc( $communes, 'attribution' );
	$licence     = bloc( $communes, 'licence' );
	$lookup      = bloc( $communes, 'lookup' );
	$archive     = bloc( $communes, 'archive' );

	return array(
		'phrase'        => texte( $attribution, 'phrase' ),
		'phrase_courte' => texte( $attribution, 'phrase_courte' ),
		'lien_source'   => texte( $attribution, 'lien_source' ),
		'lien_licence'  => texte( $attribution, 'lien_licence' ),
		// Les faits sont énumérés un à un, et non recopiés en bloc : toutes les
		// clés restent présentes même quand le référentiel est absent.
		'faits'         => array(
			'producteur'          => texte( $communes, 'producteur' ),
			'jeu_de_donnees'      => texte( $communes, 'jeu_de_donnees' ),
			'couche'              => texte( $communes, 'couche' ),
			// Le millésime RÉSOLU, jamais l'alias mouvant de la couche.
			'millesime'           => texte( $communes, 'millesime' ),
			'edition'             => texte( $communes, 'edition' ),
			'edition_libelle'     => texte( $communes, 'edition_libelle' ),
			'licence_nom'         => texte( $licence, 'nom' ),
			'licence_version'     => texte( $licence, 'version' ),
			'licence_identifiant' => texte( $licence, 'identifiant' ),
			'crs'                 => texte( $communes, 'crs' ),
			'recupere_le'         => texte( $archive, 'recupere_le' ),
			'sha256_archive'      => texte( $archive, 'sha256' ),
			'sha256_lookup'       => texte( $lookup, 'sha256' ),
			'methode_liste'       => texte( $communes, 'source_liste' ),
		),
	);
}
