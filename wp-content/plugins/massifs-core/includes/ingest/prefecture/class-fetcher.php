<?php
/**
 * Appel sortant unique vers la source préfecture.
 *
 * Seul fichier du connecteur autorisé à émettre un octet réseau, et seule
 * fonction employée : `wp_remote_get`, avec temporisation bornée et
 * vérification TLS imposée par `Settings::http_args()`.
 *
 * @package Massifs\Ingest\Prefecture
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Prefecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Récupération brute d'un fichier de statuts.
 */
final class Fetcher {

	/*
	 * PAS DE BOUCLE DE REPRISE, PAS DE `sleep()`.
	 *
	 * WP-Cron s'exécute à l'intérieur d'une vraie requête HTTP de visiteur :
	 * une boucle de reprise avec attente bloquerait cette requête pendant
	 * plusieurs dizaines de secondes, c'est-à-dire ferait payer au public la
	 * défaillance de la source. La récurrence horaire EST la politique de
	 * reprise : un échec à 17 h est retenté à 18 h, sans qu'aucun visiteur
	 * n'attende.
	 */

	/**
	 * Récupère le fichier de statuts d'une date de validité.
	 *
	 * @param \DateTimeImmutable $date Date de validité visée.
	 * @return array{code:int,body:string,headers:array<string,mixed>,url:string,octets:int}|\WP_Error
	 */
	public static function fetch( \DateTimeImmutable $date ) {
		$url = Settings::url_for( $date->format( 'Ymd' ) );

		if ( '' === $url ) {
			return new \WP_Error(
				'url_indisponible',
				'URL source vide ou invalide pour la date demandée.',
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
	 * Un 404 n'est pas une erreur : c'est l'état légitime « pas encore publié ».
	 * La source ne dépose le fichier du lendemain qu'en fin d'après-midi.
	 *
	 * @param int $code Code de réponse HTTP.
	 */
	public static function classify( int $code ): string {
		if ( 200 === $code ) {
			return 'succes';
		}

		if ( 404 === $code ) {
			return 'non_publie';
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
