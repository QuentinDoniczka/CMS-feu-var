<?php
/**
 * Validation d'un corps de réponse de la source.
 *
 * Cinq couches successives, dans un ordre non négociable : transport, forme,
 * géométrie, emprise, temporel. Chaque couche rejette pour son propre motif, et
 * le motif voyage dans les données de l'erreur (`couche`, `detail`).
 *
 * LA SIMULATION EST UNE ORIGINE, JAMAIS UNE BRANCHE. Il n'existe dans ce module
 * aucun `if ( $simule )`, aucune fixture chargée depuis le disque : une charge
 * simulée traverse `Fetcher` puis les cinq couches ci-dessous, exactement comme
 * une charge réelle. Sans quoi la validation serait contournée précisément dans
 * le mode où l'on tourne réellement.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation en cinq couches d'un relevé source.
 */
final class Validator {

	/**
	 * Types de géométrie acceptés. Liste FERMÉE.
	 *
	 * Un `Point` n'est pas une géométrie dégradée : il signale qu'on a
	 * interrogé la mauvaise couche, celle des détections ponctuelles. Le lot
	 * entier est refusé.
	 */
	public const TYPES_GEOMETRIE = array( 'Polygon', 'MultiPolygon' );

	/**
	 * Plafond de taille du corps, en octets.
	 *
	 * AUCUN PLANCHER : un `FeatureCollection` vide est court, et il est
	 * parfaitement légitime — c'est même le cas nominal la plupart des jours.
	 */
	private const OCTETS_MAX = 2097152;

	/**
	 * Plafond d'entités du lot, appliqué AVANT le filtre départemental.
	 *
	 * La source est continentale : un lot européen entier doit être refusé
	 * avant qu'on n'en parcoure la géométrie, pas après.
	 */
	private const ENTITES_MAX = 2000;

	/**
	 * Plafonds de sommets, par entité et pour le lot entier.
	 */
	private const SOMMETS_MAX_PAR_ENTITE = 20000;
	private const SOMMETS_MAX_PAR_LOT    = 200000;

	/**
	 * Profondeur maximale d'imbrication d'un tableau de coordonnées.
	 */
	private const PROFONDEUR_MAX = 8;

	/**
	 * Espace insécable (U+00A0), imposée par la typographie française devant
	 * une unité.
	 */
	private const INSECABLE = "\u{00A0}";

	/*
	 * CE QUI N'EST PAS UNE ABERRATION — à ne jamais réintroduire comme rejet.
	 *
	 * 1. ZÉRO ENTITÉ. C'est le cas nominal : la plupart des jours, aucune zone
	 *    n'est détectée dans le département. Rejeter un lot vide ferait
	 *    disparaître la couche précisément quand elle dit vrai.
	 * 2. UN LOT IDENTIQUE AU PRÉCÉDENT. La fenêtre glissante change lentement ;
	 *    deux relevés successifs identiques sont la normale, pas un signal.
	 * 3. UNE ENTITÉ TRÈS GRANDE OU TRÈS PETITE. La surface ne se juge pas : ce
	 *    module ne sait pas quelle taille est plausible, et une borne inventée
	 *    écarterait justement l'évènement exceptionnel qui compte.
	 * 4. UNE ENTITÉ CHEVAUCHANT PLUSIEURS MASSIFS. Une zone parcourue par le
	 *    feu ne connaît pas nos découpages.
	 */

	/**
	 * Valide un corps brut et produit le relevé normalisé.
	 *
	 * @param string              $body     Corps de la réponse, verbatim.
	 * @param array<string,mixed> $headers  En-têtes de réponse, clés libres.
	 * @param array<string,mixed> $contexte Contexte d'appel : `source_url`.
	 * @return array<string,mixed>|\WP_Error Relevé normalisé, ou rejet motivé.
	 */
	public static function validate( string $body, array $headers, array $contexte = array() ) {
		$transport = self::couche_transport( $body );
		if ( is_wp_error( $transport ) ) {
			return $transport;
		}

		$forme = self::couche_forme( $body );
		if ( is_wp_error( $forme ) ) {
			return $forme;
		}

		$geometrie = self::couche_geometrie( $forme['features'] );
		if ( is_wp_error( $geometrie ) ) {
			return $geometrie;
		}

		$emprise = self::couche_emprise( $geometrie['entites'] );
		if ( is_wp_error( $emprise ) ) {
			return $emprise;
		}

		$temporel = self::couche_temporel( $headers );
		if ( is_wp_error( $temporel ) ) {
			return $temporel;
		}

		return array(
			'schema'     => ReleveRepository::SCHEMA,
			// L'horodatage qui fait autorité est l'instant du relevé RÉUSSI ET
			// VALIDÉ. Jamais l'instant de la tentative, jamais un
			// `Last-Modified` de la source.
			'releve_le'  => gmdate( Settings::FORMAT_ISO_UTC ),
			'source_url' => isset( $contexte['source_url'] ) ? esc_url_raw( (string) $contexte['source_url'] ) : '',
			'octets'     => strlen( $body ),
			'hash'       => hash( 'sha256', $body ),
			'ecartees'   => $emprise['ecartees'],
			'connecteur' => Settings::connecteur(),
			'zones'      => $emprise['zones'],
		);
	}

	/**
	 * Couche 1 — transport : taille et nature du corps reçu.
	 *
	 * Le `Content-Type` n'est jamais un motif de rejet : c'est la forme du
	 * corps qui tranche.
	 *
	 * @param string $body Corps brut.
	 * @return true|\WP_Error
	 */
	private static function couche_transport( string $body ) {
		$octets = strlen( $body );

		if ( $octets > self::OCTETS_MAX ) {
			return self::erreur( 'corps_trop_long', 'transport', sprintf( 'Corps de %d octets, maximum toléré %d.', $octets, self::OCTETS_MAX ), $octets );
		}

		$premier = substr( ltrim( $body ), 0, 1 );

		if ( '<' === $premier ) {
			return self::erreur( 'html_sous_200', 'transport', 'Réponse HTML servie en HTTP 200 : page d\'erreur ou portail captif, pas une couche de données.', $premier );
		}

		if ( '{' !== $premier ) {
			return self::erreur( 'corps_non_json', 'transport', 'Le corps ne commence pas par un objet JSON.', $premier );
		}

		return true;
	}

	/**
	 * Couche 2 — forme : structure GeoJSON stricte.
	 *
	 * @param string $body Corps brut.
	 * @return array{features:array<int,array<string,mixed>>}|\WP_Error
	 */
	private static function couche_forme( string $body ) {
		$document = json_decode( $body, true, 16 );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $document ) ) {
			return self::erreur( 'json_invalide', 'forme', 'JSON illisible : ' . json_last_error_msg() );
		}

		if ( 'FeatureCollection' !== ( $document['type'] ?? '' ) ) {
			return self::erreur( 'type_racine_invalide', 'forme', 'La racine n\'est pas un FeatureCollection.', is_scalar( $document['type'] ?? null ) ? (string) $document['type'] : '' );
		}

		if ( ! isset( $document['features'] ) || ! is_array( $document['features'] ) ) {
			return self::erreur( 'features_absentes', 'forme', 'Clé « features » absente ou non tabulaire.' );
		}

		// `[]` est valide, et c'est le cas nominal : voir le bloc « ce qui
		// n'est pas une aberration » ci-dessus.
		$features = array_values( $document['features'] );

		if ( count( $features ) > self::ENTITES_MAX ) {
			return self::erreur( 'trop_d_entites', 'forme', sprintf( '%d entités reçues, maximum toléré %d avant filtre.', count( $features ), self::ENTITES_MAX ), count( $features ) );
		}

		foreach ( $features as $rang => $feature ) {
			if ( ! is_array( $feature ) ) {
				return self::erreur( 'entite_invalide', 'forme', sprintf( 'Entité %d : objet attendu.', $rang ), $rang );
			}

			$geometrie = $feature['geometry'] ?? null;

			if ( ! is_array( $geometrie ) ) {
				return self::erreur( 'geometrie_absente', 'forme', sprintf( 'Entité %d : géométrie absente ou non tabulaire.', $rang ), $rang );
			}

			$type = is_scalar( $geometrie['type'] ?? null ) ? (string) $geometrie['type'] : '';

			if ( ! in_array( $type, self::TYPES_GEOMETRIE, true ) ) {
				return self::erreur( 'geometrie_hors_type', 'forme', sprintf( 'Entité %d : type de géométrie « %s » refusé, surface attendue.', $rang, $type ), $type );
			}

			if ( ! isset( $geometrie['coordinates'] ) || ! is_array( $geometrie['coordinates'] ) ) {
				return self::erreur( 'coordonnees_absentes', 'forme', sprintf( 'Entité %d : coordonnées absentes ou non tabulaires.', $rang ), $rang );
			}
		}

		return array( 'features' => $features );
	}

	/**
	 * Couche 3 — géométrie : coordonnées finies, bornées, et plafonnées.
	 *
	 * @param array<int,array<string,mixed>> $features Entités de forme valide.
	 * @return array{entites:array<int,array<string,mixed>>}|\WP_Error
	 */
	private static function couche_geometrie( array $features ) {
		$entites = array();
		$sommets = 0;

		foreach ( $features as $rang => $feature ) {
			$geometrie = $feature['geometry'];
			$mesure    = self::parcourir( $geometrie['coordinates'], 0 );

			if ( is_wp_error( $mesure ) ) {
				return $mesure;
			}

			if ( 0 === $mesure['sommets'] ) {
				return self::erreur( 'entite_sans_sommet', 'geometrie', sprintf( 'Entité %d : aucune coordonnée exploitable.', $rang ), $rang );
			}

			if ( $mesure['sommets'] > self::SOMMETS_MAX_PAR_ENTITE ) {
				return self::erreur( 'entite_trop_dense', 'geometrie', sprintf( 'Entité %d : %d sommets, maximum toléré %d.', $rang, $mesure['sommets'], self::SOMMETS_MAX_PAR_ENTITE ), $mesure['sommets'] );
			}

			$sommets += $mesure['sommets'];

			if ( $sommets > self::SOMMETS_MAX_PAR_LOT ) {
				return self::erreur( 'lot_trop_dense', 'geometrie', sprintf( 'Lot de plus de %d sommets.', self::SOMMETS_MAX_PAR_LOT ), $sommets );
			}

			$entites[] = array(
				'rang'       => (int) $rang,
				'proprietes' => isset( $feature['properties'] ) && is_array( $feature['properties'] ) ? $feature['properties'] : array(),
				'geometrie'  => array(
					'type'        => (string) $geometrie['type'],
					'coordinates' => $geometrie['coordinates'],
				),
				'bbox'       => $mesure['bbox'],
			);
		}

		return array( 'entites' => $entites );
	}

	/**
	 * Parcourt récursivement un tableau de coordonnées.
	 *
	 * @param mixed $noeud      Nœud courant.
	 * @param int   $profondeur Profondeur atteinte.
	 * @return array{sommets:int,bbox:array{ouest:float,sud:float,est:float,nord:float}}|\WP_Error
	 */
	private static function parcourir( $noeud, int $profondeur ) {
		if ( $profondeur > self::PROFONDEUR_MAX ) {
			return self::erreur( 'coordonnees_trop_imbriquees', 'geometrie', 'Coordonnées imbriquées au-delà de la profondeur admise.', $profondeur );
		}

		if ( ! is_array( $noeud ) || array() === $noeud ) {
			return self::erreur( 'coordonnees_invalides', 'geometrie', 'Nœud de coordonnées vide ou non tabulaire.' );
		}

		// Une position est un couple de nombres ; tout le reste est un
		// conteneur à parcourir.
		if ( is_numeric( $noeud[0] ?? null ) ) {
			return self::position( $noeud );
		}

		$sommets = 0;
		$bbox    = null;

		foreach ( $noeud as $enfant ) {
			$mesure = self::parcourir( $enfant, $profondeur + 1 );

			if ( is_wp_error( $mesure ) ) {
				return $mesure;
			}

			$sommets += $mesure['sommets'];
			$bbox     = null === $bbox ? $mesure['bbox'] : self::fusionner( $bbox, $mesure['bbox'] );

			if ( $sommets > self::SOMMETS_MAX_PAR_LOT ) {
				return self::erreur( 'lot_trop_dense', 'geometrie', sprintf( 'Lot de plus de %d sommets.', self::SOMMETS_MAX_PAR_LOT ), $sommets );
			}
		}

		if ( null === $bbox ) {
			return self::erreur( 'coordonnees_invalides', 'geometrie', 'Aucune position exploitable dans ce nœud.' );
		}

		return array(
			'sommets' => $sommets,
			'bbox'    => $bbox,
		);
	}

	/**
	 * Valide une position et en fait une emprise ponctuelle.
	 *
	 * EPSG:4326, ordre GeoJSON `[lon, lat]`. Une altitude éventuelle est
	 * ignorée, jamais refusée : elle est licite en GeoJSON.
	 *
	 * @param array<int,mixed> $position Position brute.
	 * @return array{sommets:int,bbox:array{ouest:float,sud:float,est:float,nord:float}}|\WP_Error
	 */
	private static function position( array $position ) {
		if ( ! is_numeric( $position[0] ?? null ) || ! is_numeric( $position[1] ?? null ) ) {
			return self::erreur( 'position_invalide', 'geometrie', 'Position sans couple de coordonnées numériques.' );
		}

		$lon = (float) $position[0];
		$lat = (float) $position[1];

		if ( ! is_finite( $lon ) || ! is_finite( $lat ) ) {
			return self::erreur( 'position_non_finie', 'geometrie', 'Coordonnée non finie.' );
		}

		if ( $lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0 ) {
			return self::erreur( 'position_hors_bornes', 'geometrie', sprintf( 'Coordonnée hors bornes terrestres : %s, %s.', (string) $lon, (string) $lat ) );
		}

		return array(
			'sommets' => 1,
			'bbox'    => array(
				'ouest' => $lon,
				'sud'   => $lat,
				'est'   => $lon,
				'nord'  => $lat,
			),
		);
	}

	/**
	 * Fusionne deux emprises.
	 *
	 * @param array{ouest:float,sud:float,est:float,nord:float} $a Première emprise.
	 * @param array{ouest:float,sud:float,est:float,nord:float} $b Seconde emprise.
	 * @return array{ouest:float,sud:float,est:float,nord:float}
	 */
	private static function fusionner( array $a, array $b ): array {
		return array(
			'ouest' => min( $a['ouest'], $b['ouest'] ),
			'sud'   => min( $a['sud'], $b['sud'] ),
			'est'   => max( $a['est'], $b['est'] ),
			'nord'  => max( $a['nord'], $b['nord'] ),
		);
	}

	/**
	 * Couche 4 — emprise : filtre départemental.
	 *
	 * FAIL CLOSED. La source est continentale : sans emprise de référence, il
	 * est impossible de tenir la promesse « filtrée sur le département » du
	 * §4.4 du brief, et le lot entier est refusé. Une entité hors emprise, en
	 * revanche, est écartée EN SILENCE : ce n'est pas une anomalie de la
	 * source, c'est la définition même du filtre.
	 *
	 * @param array<int,array<string,mixed>> $entites Entités mesurées.
	 * @return array{zones:array<int,array<string,mixed>>,ecartees:int}|\WP_Error
	 */
	private static function couche_emprise( array $entites ) {
		$reference = null;

		if ( function_exists( 'massifs_emprise' ) ) {
			$emprise   = massifs_emprise();
			$reference = is_array( $emprise ) && isset( $emprise['bbox'] ) && is_array( $emprise['bbox'] ) ? $emprise['bbox'] : null;
		}

		foreach ( array( 'ouest', 'sud', 'est', 'nord' ) as $borne ) {
			if ( ! is_array( $reference ) || ! isset( $reference[ $borne ] ) || ! is_numeric( $reference[ $borne ] ) ) {
				return self::erreur( 'emprise_indisponible', 'emprise', 'Emprise départementale indisponible : le filtre ne peut pas être appliqué, lot refusé.' );
			}
		}

		$attributs = Settings::correspondance_attributs();
		$zones     = array();
		$ecartees  = 0;

		foreach ( $entites as $entite ) {
			if ( ! self::intersecte( $entite['bbox'], $reference ) ) {
				++$ecartees;
				continue;
			}

			$zones[] = self::projeter( $entite, $attributs, count( $zones ) );
		}

		return array(
			'zones'    => $zones,
			'ecartees' => $ecartees,
		);
	}

	/**
	 * Deux emprises se recoupent-elles ?
	 *
	 * @param array{ouest:float,sud:float,est:float,nord:float} $zone      Emprise de l'entité.
	 * @param array<string,mixed>                               $reference Emprise départementale.
	 */
	private static function intersecte( array $zone, array $reference ): bool {
		return $zone['ouest'] <= (float) $reference['est']
			&& $zone['est'] >= (float) $reference['ouest']
			&& $zone['sud'] <= (float) $reference['nord']
			&& $zone['nord'] >= (float) $reference['sud'];
	}

	/**
	 * Projette une entité retenue en entrée de zone du contrat.
	 *
	 * @param array<string,mixed>  $entite    Entité mesurée.
	 * @param array<string,string> $attributs Table de correspondance.
	 * @param int                  $rang      Rang dans le lot filtré.
	 * @return array<string,mixed>
	 */
	private static function projeter( array $entite, array $attributs, int $rang ): array {
		$proprietes = $entite['proprietes'];
		$surface_ha = self::surface( $proprietes[ $attributs['surface_ha'] ] ?? null );

		return array(
			'id'                     => self::identifiant( $proprietes[ $attributs['id'] ] ?? null, $rang ),
			'surface_texte'          => self::surface_texte( $surface_ha ),
			'surface_ha'             => $surface_ha,
			'premiere_observation'   => self::instant_iso( $proprietes[ $attributs['premiere_observation'] ] ?? null ),
			'derniere_observation'   => self::instant_iso( $proprietes[ $attributs['derniere_observation'] ] ?? null ),
			// Résolue ICI, au moment de l'ingestion, et JAMAIS au rendu : le
			// panneau est rendu par le serveur, et une résolution à la lecture
			// ouvrirait la géométrie communale à chaque affichage de la page
			// d'accueil.
			//
			// LA GÉOMÉTRIE ENTIÈRE EST PASSÉE, jamais un point qui la résume.
			// Une zone de 30 ha et plus est couramment en croissant, en L, ou
			// en plusieurs parties : le centre de son emprise peut tomber hors
			// d'elle, dans une commune que le feu n'a jamais touchée, ou en
			// mer. Le plafond de 5 km ne rattrape pas cela — un centre situé à
			// 2 km du foyer réel, dans une commune voisine, renverrait le nom
			// de cette voisine avec assurance et SOUS le plafond. C'est le
			// quatrième contournement refusé, et c'est le raisonnement exact
			// qui avait déjà fait refuser les points chefs-lieux, le massif le
			// plus proche et l'attribut `commune` de la source.
			//
			// Chaîne vide quand aucune commune n'est résolue à moins de 5 km,
			// ou quand la zone déborde l'emprise couverte : le gabarit omet
			// alors proprement la paire.
			'commune_la_plus_proche' => function_exists( 'massifs_commune_de_la_zone_nom' )
				? massifs_commune_de_la_zone_nom( $entite['geometrie'] )
				: '',
			'geometrie'              => $entite['geometrie'],
		);
	}

	/**
	 * Identifiant opaque, stable dans un relevé.
	 *
	 * @param mixed $valeur Valeur brute de l'attribut.
	 * @param int   $rang   Rang dans le lot filtré.
	 */
	private static function identifiant( $valeur, int $rang ): string {
		$brut = is_scalar( $valeur ) ? trim( (string) $valeur ) : '';

		if ( 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $brut ) ) {
			return $brut;
		}

		// `zpf` : zones parcourues par le feu. Repli positionnel, stable dans
		// un relevé et sans aucune prétention à l'être d'un relevé à l'autre.
		return sprintf( 'zpf-%04d', $rang + 1 );
	}

	/**
	 * Surface en hectares. Attribut absent ou aberrant : `0.0`.
	 *
	 * @param mixed $valeur Valeur brute de l'attribut.
	 */
	private static function surface( $valeur ): float {
		if ( ! is_numeric( $valeur ) ) {
			return 0.0;
		}

		$surface = (float) $valeur;

		return is_finite( $surface ) && $surface > 0.0 ? $surface : 0.0;
	}

	/**
	 * Surface déjà formatée pour l'affichage, unité comprise.
	 *
	 * COMPOSÉE ICI, EN PHP, et jamais par le thème : le thème ne formate
	 * jamais un nombre. Espace INSÉCABLE avant l'unité.
	 *
	 * Arrondi à l'hectare au-delà de 10 ha, une décimale en deçà : le seuil de
	 * détection annoncé par la source est de l'ordre de 30 ha, et une décimale
	 * sur une valeur à trois chiffres afficherait une précision que l'estimation
	 * satellite n'a pas. Sous 10 ha, l'arrondi entier coûterait au contraire un
	 * dixième de la valeur.
	 *
	 * @param float $surface_ha Surface en hectares.
	 */
	private static function surface_texte( float $surface_ha ): string {
		if ( $surface_ha <= 0.0 ) {
			return '';
		}

		$nombre = $surface_ha < 10.0
			? number_format( $surface_ha, 1, ',', self::INSECABLE )
			: number_format( $surface_ha, 0, ',', self::INSECABLE );

		return $nombre . self::INSECABLE . 'ha';
	}

	/**
	 * Instant ISO 8601 UTC complet, ou chaîne vide.
	 *
	 * SEUL NORMALISEUR D'INSTANT DU MODULE, employé aux deux frontières : à
	 * l'ingestion d'une entité source, et à la relecture de l'option par
	 * `ReleveRepository`. Deux implémentations de la même règle finiraient par
	 * diverger, et l'une des deux laisserait alors passer un instant qui n'en
	 * est pas un.
	 *
	 * JAMAIS DE MIDI FABRIQUÉ. Une partie horaire est exigée : `YYYY-MM-DD` nu
	 * est refusé, parce que midi UTC n'est pas l'heure d'une observation
	 * satellite. Le champ vaut alors `''` et la paire est omise à l'affichage.
	 *
	 * @param mixed $valeur Valeur brute.
	 */
	public static function instant_iso( $valeur ): string {
		if ( ! is_string( $valeur ) ) {
			return '';
		}

		$brut = trim( $valeur );

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $brut ) ) {
			return '';
		}

		$horodatage = strtotime( $brut );

		return false === $horodatage ? '' : gmdate( Settings::FORMAT_ISO_UTC, $horodatage );
	}

	/**
	 * Couche 5 — temporel.
	 *
	 * `Last-Modified` absent : contrôle sauté EN SILENCE, rien à inventer. Un
	 * lot antérieur à deux fois la péremption est en revanche refusé : il ne
	 * pourrait jamais être servi honnêtement.
	 *
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @return true|\WP_Error
	 */
	private static function couche_temporel( array $headers ) {
		$brut = self::entete( $headers, 'last-modified' );

		if ( '' === $brut ) {
			return true;
		}

		$horodatage = strtotime( $brut );

		if ( false === $horodatage ) {
			return true;
		}

		$plancher = time() - ( 2 * Settings::peremption_secondes() );

		if ( $horodatage < $plancher ) {
			return self::erreur(
				'lot_perime',
				'temporel',
				sprintf( 'Lot modifié le %s, soit au-delà de deux fois la péremption.', gmdate( Settings::FORMAT_ISO_UTC, $horodatage ) ),
				gmdate( Settings::FORMAT_ISO_UTC, $horodatage )
			);
		}

		return true;
	}

	/**
	 * Lecture insensible à la casse d'un en-tête.
	 *
	 * @param array<string,mixed> $headers En-têtes de réponse.
	 * @param string              $nom     Nom recherché, en minuscules.
	 */
	private static function entete( array $headers, string $nom ): string {
		foreach ( $headers as $cle => $valeur ) {
			if ( strtolower( (string) $cle ) !== $nom ) {
				continue;
			}

			if ( is_array( $valeur ) ) {
				$valeur = reset( $valeur );
			}

			return is_scalar( $valeur ) ? trim( (string) $valeur ) : '';
		}

		return '';
	}

	/**
	 * Fabrique une erreur de validation portant sa couche d'origine.
	 *
	 * @param string $code    Code d'erreur.
	 * @param string $couche  Couche d'origine.
	 * @param string $message Message lisible, en français.
	 * @param mixed  $detail  Détail structuré facultatif.
	 */
	private static function erreur( string $code, string $couche, string $message, $detail = null ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array(
				'couche' => $couche,
				'detail' => $detail,
			)
		);
	}
}
