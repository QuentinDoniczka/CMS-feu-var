<?php
/**
 * Émission de la réponse HTTP : en-têtes, ETag faible, `304`, erreurs.
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` : voir l'en-tête de
 * `charge-statuts.php`.
 *
 * Aucun filtre site-wide n'est enregistré depuis ce module : ni
 * `rest_send_nocache_headers`, ni `rest_jsonp_enabled`, ni
 * `rest_pre_serve_request`. La route s'exclut du cache par son propre
 * `Cache-Control`, sur sa propre réponse.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_rest_public_reponse' ) ) {
	/**
	 * Réponse `200`, ou `304` si le client détient déjà cette représentation.
	 *
	 * `Cache-Control: no-cache` est posé ici, sur notre propre réponse : le cœur
	 * n'envoie ses en-têtes `nocache` que pour un utilisateur connecté, donc rien
	 * sur une requête anonyme — le cas nominal. L'en-tête devient ainsi invariant
	 * par session.
	 *
	 * Jamais de `max-age` : il faudrait le borner sur les secondes restant
	 * jusqu'à minuit heure de Paris, faute de quoi un `max-age` posé à 23 h 55
	 * servirait la journée de la veille dix minutes durant — le §4.2 par la porte
	 * de derrière. `no-cache` et non `no-store` : le client garde sa copie, ce qui
	 * rend le `304` utile, mais revalide à chaque fois.
	 *
	 * @param array<string,mixed> $charge  Charge utile assemblée.
	 * @param WP_REST_Request     $requete Requête entrante.
	 *
	 * @return WP_REST_Response
	 */
	function massifs_rest_public_reponse( array $charge, WP_REST_Request $requete ): WP_REST_Response {
		$entetes = array( 'Cache-Control' => 'no-cache' );

		if ( massifs_rest_public_etag_applicable( $requete ) ) {
			$etag            = massifs_rest_public_etag( $charge );
			$entetes['ETag'] = $etag;

			if ( massifs_rest_public_correspond_etag( $requete->get_header( 'if_none_match' ), $etag ) ) {
				$non_modifie = new WP_REST_Response( null, 304 );
				$non_modifie->set_headers( $entetes );

				return $non_modifie;
			}
		}

		$reponse = new WP_REST_Response( $charge, 200 );
		$reponse->set_headers( $entetes );

		return $reponse;
	}
}

if ( ! function_exists( 'massifs_rest_public_etag' ) ) {
	/**
	 * ETag faible de la charge utile entière.
	 *
	 * Faible et non fort : le cœur peut réencoder la structure — ordre des
	 * options de `wp_json_encode`, enveloppe — sans que la donnée ait changé.
	 * Le calcul porte sur la charge utile complète, ce qui n'est possible que
	 * parce qu'aucun instant courant n'y figure.
	 *
	 * @param array<string,mixed> $charge Charge utile assemblée.
	 */
	function massifs_rest_public_etag( array $charge ): string {
		return 'W/"' . sha1( (string) wp_json_encode( $charge ) ) . '"';
	}
}

if ( ! function_exists( 'massifs_rest_public_etag_sans_prefixe' ) ) {
	/**
	 * Retire le préfixe de validateur faible `W/` d'une empreinte.
	 *
	 * Écrit une seule fois : la RFC 9110 impose de retirer ce préfixe des DEUX
	 * côtés de la comparaison, et deux normalisations distinctes finiraient par
	 * diverger.
	 *
	 * @param string $valeur Empreinte, préfixée `W/` ou non.
	 */
	function massifs_rest_public_etag_sans_prefixe( string $valeur ): string {
		return str_starts_with( $valeur, 'W/' ) ? substr( $valeur, 2 ) : $valeur;
	}
}

if ( ! function_exists( 'massifs_rest_public_etag_applicable' ) ) {
	/**
	 * L'ETag décrit-il bien les octets qui seront servis ?
	 *
	 * `_fields`, `_jsonp` et `_envelope` modifient la réponse APRÈS notre
	 * callback : un ETag qui ne décrit pas le corps servi est pire qu'aucun ETag.
	 * Condition locale à ces trois noms — aucun filtre site-wide, `_jsonp` n'est
	 * pas désarmé pour le reste du site.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 */
	function massifs_rest_public_etag_applicable( WP_REST_Request $requete ): bool {
		foreach ( array( '_fields', '_jsonp', '_envelope' ) as $parametre ) {
			if ( null !== $requete->get_param( $parametre ) ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'massifs_rest_public_correspond_etag' ) ) {
	/**
	 * Comparaison FAIBLE d'un `If-None-Match` avec notre ETag (RFC 9110).
	 *
	 * L'en-tête peut porter plusieurs valeurs séparées par des virgules, chacune
	 * éventuellement préfixée de `W/`. `If-None-Match` impose la comparaison
	 * faible : le préfixe est retiré des deux côtés. `*` correspond à toute
	 * représentation existante.
	 *
	 * @param string|null $entete Valeur brute de `If-None-Match`.
	 * @param string      $etag   Notre ETag, préfixe `W/` compris.
	 */
	function massifs_rest_public_correspond_etag( ?string $entete, string $etag ): bool {
		if ( null === $entete || '' === trim( $entete ) ) {
			return false;
		}

		$attendu = massifs_rest_public_etag_sans_prefixe( $etag );

		foreach ( explode( ',', $entete ) as $candidat ) {
			$candidat = trim( $candidat );

			if ( '*' === $candidat ) {
				return true;
			}

			$candidat = massifs_rest_public_etag_sans_prefixe( $candidat );

			if ( '' !== $candidat && $candidat === $attendu ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'massifs_rest_public_erreur' ) ) {
	/**
	 * Fabrique une erreur de cette route.
	 *
	 * Le message d'une exception ne voyage JAMAIS : un code stable et une phrase
	 * fixe et neutre, rien d'autre. Une trace PHP sur un point d'accès anonyme est
	 * une fuite.
	 *
	 * `carte_officielle_url` est jointe dès qu'elle est obtenable, y compris dans
	 * les corps `503` : c'est le repli imposé par le §4.2, et un réutilisateur
	 * doit pouvoir le relayer sans l'écrire en dur.
	 *
	 * Aucun `Retry-After` : nous ne connaissons pas le délai de rétablissement, et
	 * l'inventer mettrait une donnée fausse dans un en-tête.
	 *
	 * @param string $code   Code d'erreur stable.
	 * @param int    $statut Statut HTTP.
	 *
	 * @return WP_Error
	 */
	function massifs_rest_public_erreur( string $code, int $statut ): WP_Error {
		$messages = array(
			'massifs_jour_hors_bornes'         => 'Seuls le jour courant et le jour suivant sont servis par ce point d\'accès.',
			'massifs_jour_invalide'            => 'Le jour demandé n\'est pas une date exploitable.',
			'massifs_api_indisponible'         => 'Le service de lecture des statuts est momentanément indisponible.',
			'massifs_referentiel_indisponible' => 'Le référentiel des massifs est momentanément indisponible.',
			'massifs_domaine_en_erreur'        => 'Les statuts du jour n\'ont pas pu être assemblés.',
		);

		$donnees = array( 'status' => $statut );
		$carte   = massifs_rest_public_carte_officielle_url();

		if ( '' !== $carte ) {
			$donnees['carte_officielle_url'] = $carte;
		}

		return new WP_Error(
			$code,
			isset( $messages[ $code ] ) ? $messages[ $code ] : 'Les statuts du jour ne sont pas disponibles.',
			$donnees
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_erreur_enrichie' ) ) {
	/**
	 * Même erreur, augmentée de données de diagnostic non sensibles.
	 *
	 * `WP_Error::add_data()` REMPLACE les données du code : la fusion est ce qui
	 * préserve `status` et `carte_officielle_url` posés par
	 * `massifs_rest_public_erreur()`. Écrite ici une seule fois plutôt qu'à chaque
	 * appelant.
	 *
	 * Le supplément ne porte jamais de message d'exception, de trace, ni
	 * d'identifiant d'utilisateur : seulement une donnée déjà publique.
	 *
	 * @param string              $code       Code d'erreur stable.
	 * @param int                 $statut     Statut HTTP.
	 * @param array<string,mixed> $supplement Données à joindre au corps d'erreur.
	 *
	 * @return WP_Error
	 */
	function massifs_rest_public_erreur_enrichie( string $code, int $statut, array $supplement ): WP_Error {
		$erreur = massifs_rest_public_erreur( $code, $statut );

		$erreur->add_data( array_merge( (array) $erreur->get_error_data(), $supplement ) );

		return $erreur;
	}
}

if ( ! function_exists( 'massifs_rest_public_journaliser' ) ) {
	/**
	 * Consigne le détail d'une exception, en debug seulement.
	 *
	 * Le détail reste côté serveur : c'est la contrepartie de la phrase neutre
	 * servie au client. Hors `WP_DEBUG`, rien n'est écrit.
	 *
	 * @param string    $code      Code d'erreur stable associé.
	 * @param Throwable $exception Exception interceptée.
	 */
	function massifs_rest_public_journaliser( string $code, Throwable $exception ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[massifs] /massifs/v1/statuts %s : %s dans %s:%d',
				$code,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			)
		);
	}
}
