<?php
/**
 * Route publique de lecture de la couche.
 *
 * `GET /wp-json/massifs/v1/zones-parcourues-par-le-feu`
 *
 * Elle sert la contrainte n° 2 du projet : les polygones sont récupérés côté
 * serveur, mis en cache, et RE-SERVIS DEPUIS NOTRE PROPRE DOMAINE. Une fonction
 * PHP ne démontre pas cela ; cette route, si.
 *
 * AUCUNE ROUTE D'ÉCRITURE N'EST DÉCLARÉE DANS CE MODULE : seule
 * `WP_REST_Server::READABLE` existe, et la réponse ne varie ni selon
 * l'utilisateur, ni selon la session, ni selon un cookie.
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
 * Déclaration et service de la route publique.
 */
final class Route {

	/**
	 * Espace de noms REST du projet.
	 */
	public const NAMESPACE_REST = 'massifs/v1';

	/**
	 * Chemin de la route.
	 */
	public const CHEMIN = '/zones-parcourues-par-le-feu';

	/**
	 * Déclare la route. Appelée sur `rest_api_init`.
	 */
	public static function register(): void {
		register_rest_route(
			self::NAMESPACE_REST,
			self::CHEMIN,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'servir' ),
				/*
				 * Lecture publique, sans authentification : c'est l'objet même
				 * de la route. Écrit explicitement — jamais absent, jamais
				 * `is_user_logged_in` : une route publique dont la sortie varie
				 * par session est un cache empoisonnable.
				 */
				'permission_callback' => '__return_true',
				/*
				 * AUCUN ARGUMENT, et c'est une décision. Pas de `jour` : la
				 * couche est une fenêtre glissante, pas un statut daté. Pas de
				 * `bbox` : le filtre départemental est notre décision, pas celle
				 * du client. Pas de `format`. Toute surface de paramètre est une
				 * surface d'attaque et une divergence de cache pour un gain nul.
				 */
				'args'                => array(),
			)
		);
	}

	/**
	 * Sert la couche.
	 *
	 * `200` DANS TOUS LES ÉTATS DE LA DONNÉE. Aucun `503` : « la couche est
	 * indisponible » est un état légitime et attendu, pas une panne serveur. Un
	 * `503` enverrait le client dans une branche d'erreur, où la tentation est
	 * la reprise, le repli, ou un cache tiers.
	 *
	 * @param \WP_REST_Request $requete Requête entrante.
	 */
	public static function servir( \WP_REST_Request $requete ): \WP_REST_Response {
		$charge = self::charge();

		$entetes = array( 'Cache-Control' => 'no-cache' );

		if ( self::etag_applicable( $requete ) ) {
			$etag            = self::etag( $charge );
			$entetes['ETag'] = $etag;

			if ( self::correspond_etag( $requete->get_header( 'if_none_match' ), $etag ) ) {
				$non_modifie = new \WP_REST_Response( null, 304 );
				$non_modifie->set_headers( $entetes );

				return $non_modifie;
			}
		}

		$reponse = new \WP_REST_Response( $charge, 200 );
		$reponse->set_headers( $entetes );

		return $reponse;
	}

	/**
	 * Charge utile, forme exacte du contrat.
	 *
	 * L'ATTRIBUTION ET LA DONNÉE N'EXISTENT QUE L'UNE AVEC L'AUTRE : quand la
	 * couche est indisponible, `attribution` vaut la chaîne vide. Créditer une
	 * source dont aucune donnée n'est servie est une affirmation fausse.
	 *
	 * @return array<string,mixed>
	 */
	private static function charge(): array {
		$couche       = Couche::etat();
		$indisponible = 'couche_effis_indisponible' === $couche['etat'];

		return array(
			'etat'                => $couche['etat'],
			'releve_le'           => $couche['releve_le'],
			'expire_le'           => $couche['expire_le'],
			'fenetre_jours'       => $couche['fenetre_jours'],
			'surface_minimale_ha' => $couche['surface_minimale_ha'],
			'nombre'              => $couche['nombre'],
			'attribution'         => $indisponible ? '' : Attribution::PHRASE,
			'zones'               => array(
				'type'     => 'FeatureCollection',
				'features' => self::features( $couche['zones'] ),
			),
		);
	}

	/**
	 * Projette les zones en entités GeoJSON, directement consommables.
	 *
	 * @param array<int,array<string,mixed>> $zones Zones de la couche.
	 * @return array<int,array<string,mixed>>
	 */
	private static function features( array $zones ): array {
		$features = array();

		foreach ( $zones as $zone ) {
			$features[] = array(
				'type'       => 'Feature',
				'properties' => array(
					'id'                     => $zone['id'],
					'surface_texte'          => $zone['surface_texte'],
					'surface_ha'             => $zone['surface_ha'],
					'premiere_observation'   => $zone['premiere_observation'],
					'derniere_observation'   => $zone['derniere_observation'],
					'commune_la_plus_proche' => $zone['commune_la_plus_proche'],
				),
				'geometry'   => $zone['geometrie'],
			);
		}

		return $features;
	}

	/**
	 * ETag FAIBLE de la charge utile entière.
	 *
	 * Idiome de `includes/rest/public/reponse.php`, reproduit ici plutôt
	 * qu'importé : les fonctions de ce module-là sont déclarées privées à leur
	 * module, et ce module ne nomme aucune fonction interne d'un autre.
	 *
	 * Faible et non fort : le cœur peut réencoder la structure sans que la
	 * donnée ait changé. Le calcul porte sur la charge complète, ce qui n'est
	 * possible que parce qu'aucun instant courant n'y figure.
	 *
	 * @param array<string,mixed> $charge Charge utile.
	 */
	private static function etag( array $charge ): string {
		return 'W/"' . sha1( (string) wp_json_encode( $charge ) ) . '"';
	}

	/**
	 * L'ETag décrit-il bien les octets qui seront servis ?
	 *
	 * `_fields`, `_jsonp` et `_envelope` modifient la réponse APRÈS ce rappel :
	 * un ETag qui ne décrit pas le corps servi est pire qu'aucun ETag.
	 *
	 * @param \WP_REST_Request $requete Requête entrante.
	 */
	private static function etag_applicable( \WP_REST_Request $requete ): bool {
		foreach ( array( '_fields', '_jsonp', '_envelope' ) as $parametre ) {
			if ( null !== $requete->get_param( $parametre ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Comparaison FAIBLE d'un `If-None-Match` avec notre ETag (RFC 9110).
	 *
	 * @param string|null $entete Valeur brute de `If-None-Match`.
	 * @param string      $etag   Notre ETag, préfixe `W/` compris.
	 */
	private static function correspond_etag( ?string $entete, string $etag ): bool {
		if ( null === $entete || '' === trim( $entete ) ) {
			return false;
		}

		$attendu = self::sans_prefixe( $etag );

		foreach ( explode( ',', $entete ) as $candidat ) {
			$candidat = trim( $candidat );

			if ( '*' === $candidat ) {
				return true;
			}

			$candidat = self::sans_prefixe( $candidat );

			if ( '' !== $candidat && $candidat === $attendu ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retire le préfixe de validateur faible `W/` d'une empreinte.
	 *
	 * La RFC 9110 impose de le retirer des DEUX côtés de la comparaison.
	 *
	 * @param string $valeur Empreinte, préfixée `W/` ou non.
	 */
	private static function sans_prefixe( string $valeur ): string {
		return str_starts_with( $valeur, 'W/' ) ? substr( $valeur, 2 ) : $valeur;
	}
}
