<?php
/**
 * Appel sortant unique vers la source météo.
 *
 * SEUL FICHIER DU MODULE AUTORISÉ À ÉMETTRE UN OCTET RÉSEAU, et seule fonction
 * employée : `wp_remote_get`, avec temporisation bornée et vérification TLS
 * imposée par `Settings::http_args()`.
 *
 * Aucun `curl_exec`, aucun `file_get_contents` sur une adresse, ici comme
 * ailleurs. Un second point d'émission rendrait la frontière d'ingestion
 * invérifiable : c'est cette unicité qui permet d'affirmer, mécaniquement, que
 * la source est désarmée quand le coupe-circuit est actif.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Meteo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Récupération brute d'une charge météo.
 */
final class Fetcher {

	/*
	 * PAS DE BOUCLE DE REPRISE, PAS DE `sleep()`.
	 *
	 * WP-Cron s'exécute à l'intérieur d'une vraie requête HTTP de visiteur : une
	 * boucle de reprise avec attente bloquerait cette requête, c'est-à-dire
	 * ferait payer au public la défaillance de la source. La récurrence horaire
	 * EST la politique de reprise.
	 */

	/**
	 * Récupère la charge d'une date de validité.
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
	 * Un 404 n'est pas une erreur : c'est le SEUL signal de non-publication de
	 * la source. Il ne compte pas comme un échec et ne déclenche aucune alerte.
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
