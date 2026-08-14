<?php
/**
 * Appel sortant unique vers la source de la couche.
 *
 * SEUL FICHIER DU MODULE AUTORISÉ À ÉMETTRE UN OCTET RÉSEAU, et seule fonction
 * employée : `wp_remote_get`, avec temporisation bornée et vérification TLS
 * ré-imposées par `Settings::http_args()`. Un `grep` de `wp_remote_`, `curl_`
 * ou `file_get_contents(` sur `includes/ingest/effis/**` ne doit rendre aucune
 * ligne hors de ce fichier.
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
 * Récupération brute de la couche source.
 */
final class Fetcher {

	/*
	 * PAS DE BOUCLE DE REPRISE, PAS DE `sleep()`.
	 *
	 * WP-Cron s'exécute à l'intérieur d'une vraie requête HTTP de visiteur : une
	 * boucle d'attente ferait payer au public la défaillance de la source. La
	 * récurrence horaire EST la politique de reprise du §4.5 du brief — un échec
	 * à 17 h est retenté à 18 h, sans qu'aucun visiteur n'attende.
	 */

	/**
	 * Récupère la couche.
	 *
	 * @return array{code:int,body:string,headers:array<string,mixed>,url:string,octets:int}|\WP_Error
	 */
	public static function fetch() {
		$url = Settings::url();

		if ( '' === $url ) {
			return new \WP_Error(
				'url_absente',
				'Aucune URL de source résolue : aucun appel sortant n\'est émis.',
				array( 'couche' => 'transport' )
			);
		}

		$reponse = wp_remote_get( $url, Settings::http_args( $url ) );

		if ( is_wp_error( $reponse ) ) {
			$reponse->add_data( array( 'couche' => 'transport' ), $reponse->get_error_code() );

			return $reponse;
		}

		$corps = (string) wp_remote_retrieve_body( $reponse );

		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $reponse ),
			'body'    => $corps,
			'headers' => self::entetes( $reponse ),
			'url'     => $url,
			'octets'  => strlen( $corps ),
		);
	}

	/**
	 * Traduit un code HTTP en classe d'issue.
	 *
	 * UN 404 EST ICI UN ÉCHEC, contrairement au connecteur préfecture : il
	 * n'existe aucun état « pas encore publié » pour une fenêtre glissante. Une
	 * source muette est une source indisponible, pas une source en attente.
	 *
	 * @param int $code Code de réponse HTTP.
	 */
	public static function classify( int $code ): string {
		if ( 200 === $code ) {
			return 'succes';
		}

		if ( $code >= 500 && $code <= 599 ) {
			return 'source_indisponible';
		}

		return 'transport';
	}

	/**
	 * Normalise les en-têtes de réponse en tableau simple.
	 *
	 * @param array<string,mixed>|\WP_Error $reponse Réponse `wp_remote_get`.
	 * @return array<string,mixed>
	 */
	private static function entetes( $reponse ): array {
		$entetes = wp_remote_retrieve_headers( $reponse );

		if ( is_object( $entetes ) && method_exists( $entetes, 'getAll' ) ) {
			$entetes = $entetes->getAll();
		}

		if ( ! is_array( $entetes ) ) {
			return array();
		}

		$propres = array();

		foreach ( $entetes as $cle => $valeur ) {
			$propres[ strtolower( (string) $cle ) ] = $valeur;
		}

		return $propres;
	}
}
