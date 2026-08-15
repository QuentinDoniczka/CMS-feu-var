<?php
/**
 * Route d'écriture du portail : publication des statuts d'un jour.
 *
 * `POST /wp-json/massifs-portail/v1/publication`
 *
 * ESPACE DE NOMS DISTINCT, ET C'EST UNE CONTRAINTE, PAS UN GOÛT. Le contrat de la
 * route publique gèle « aucune route en écriture dans `massifs/v1` » : la sonde de
 * recette y attend un `405` sur `POST`, et la liste des routes de `massifs/v1` est
 * vérifiée par égalité exacte. Enregistrer une écriture là-bas casserait les deux.
 *
 * AUCUNE CLASSE, AUCUN `namespace`, AUCUN `use` : ce module est chargé par
 * `require_once` depuis l'amorce de l'écran, jamais par l'autoloader. Fonctions
 * préfixées `massifs_rest_portail_publication_`.
 *
 * CETTE ROUTE EST MORTE POUR L'ÉCRAN, qui poste en HTML sans JavaScript. Le risque
 * qu'elle ajoute — un second point d'entrée sur l'unique chemin d'écriture du
 * produit — est contenu par le fait qu'elle traverse EXACTEMENT le même
 * `massifs_publication_publier()`, avec les mêmes gardes, dans le même ordre.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_rest_portail_publication_enregistrer_routes' ) ) {
	/**
	 * Déclare la route de publication.
	 *
	 * Appelée sur `rest_api_init`, seul hook consommé par ce module. Aucun filtre
	 * global d'authentification REST n'est posé : `rest_authentication_errors`
	 * renverrait `401` sur la lecture publique et casserait l'open data.
	 */
	function massifs_rest_portail_publication_enregistrer_routes(): void {
		register_rest_route(
			'massifs-portail/v1',
			'/publication',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'massifs_rest_portail_publication_servir',
				'permission_callback' => 'massifs_rest_portail_publication_permission',
				'args'                => massifs_rest_portail_publication_args(),
			)
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_capacite' ) ) {
	/**
	 * Capacité exigée par cette route.
	 *
	 * Lue à sa source dès que le module des rôles est chargé ; le littéral est le
	 * repli fail-closed, avec la chaîne gelée par le contrat des rôles.
	 */
	function massifs_rest_portail_publication_capacite(): string {
		if ( function_exists( 'massifs_publication_capacite' ) ) {
			return massifs_publication_capacite();
		}

		return 'massifs_publier_statuts';
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_permission' ) ) {
	/**
	 * Contrôle de permission de la route.
	 *
	 * CE CALLBACK EST PORTEUR, PAS REDONDANT. Le garde global du portail
	 * (`rest_request_before_callbacks`) accepte n'importe laquelle des trois
	 * capacités `massifs_*` ; seul ce callback exige LA BONNE. Jamais
	 * `__return_true`, jamais `is_user_logged_in()` seul, jamais de repli sur
	 * `manage_options` — l'administrateur porte déjà la capacité.
	 *
	 * La branche `401` est INATTEIGNABLE en pratique : le garde global s'exécute
	 * avant les `permission_callback` et refuse déjà les anonymes. Elle est écrite
	 * parce qu'une garde ne se repose pas sur une autre garde.
	 *
	 * Un compte suspendu échoue ici SANS code dédié : le résolveur de suspension
	 * lui a déjà retiré ses capacités `massifs_*`.
	 *
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 *
	 * @return true|WP_Error
	 */
	function massifs_rest_portail_publication_permission( WP_REST_Request $requete ): true|WP_Error {
		unset( $requete );

		if ( ! is_user_logged_in() ) {
			return massifs_rest_portail_publication_erreur( 'massifs_portail_droits_insuffisants', 401 );
		}

		if ( ! current_user_can( massifs_rest_portail_publication_capacite() ) ) {
			return massifs_rest_portail_publication_erreur( 'massifs_portail_droits_insuffisants', 403 );
		}

		return true;
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_args' ) ) {
	/**
	 * Arguments de la route.
	 *
	 * `jour` n'a AUCUN `default` : un défaut laisserait publier demain à un
	 * appelant qui pensait publier aujourd'hui. C'est un jeton relatif, jamais une
	 * date — le service reste le seul à le résoudre.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_rest_portail_publication_args(): array {
		return array(
			'jour'      => array(
				'description'       => 'Jour à publier, jeton relatif : aujourd_hui ou demain.',
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'aujourd_hui', 'demain' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'niveaux'   => array(
				'description'       => 'Table massif_code => clé de niveau de la légende officielle.',
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => 'massifs_rest_portail_publication_valider_niveaux',
				'sanitize_callback' => 'massifs_rest_portail_publication_assainir_niveaux',
			),
			'empreinte' => array(
				'description'       => 'Empreinte de l\'état du jour au moment de la lecture. Absente, le contrôle de concurrence est renoncé.',
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_plafond' ) ) {
	/**
	 * Nombre maximal d'entrées acceptées dans `niveaux`.
	 *
	 * Le référentiel en compte 25 : un lot de plus de cent entrées n'est pas une
	 * publication, c'est une charge utile aberrante.
	 */
	function massifs_rest_portail_publication_plafond(): int {
		return 100;
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_valider_niveaux' ) ) {
	/**
	 * Valide la forme de `niveaux`.
	 *
	 * LA VALEUR EST BRUTE ICI : le cœur valide avant d'assainir. La forme est donc
	 * contrôlée sans aucune tolérance, et la validation de FOND — code du
	 * référentiel, clé de la légende — reste au service, qui refuse le lot entier.
	 *
	 * @param mixed           $valeur  Valeur brute du paramètre.
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 * @param string          $cle     Nom du paramètre, non utilisé.
	 *
	 * @return bool|WP_Error
	 */
	function massifs_rest_portail_publication_valider_niveaux( mixed $valeur, WP_REST_Request $requete, string $cle ): bool|WP_Error {
		unset( $requete, $cle );

		if ( ! is_array( $valeur ) || array() === $valeur ) {
			return massifs_rest_portail_publication_erreur( 'massifs_portail_saisie_invalide', 400 );
		}

		if ( count( $valeur ) > massifs_rest_portail_publication_plafond() ) {
			return massifs_rest_portail_publication_erreur( 'massifs_portail_saisie_invalide', 400 );
		}

		foreach ( $valeur as $entree ) {
			if ( ! is_string( $entree ) ) {
				return massifs_rest_portail_publication_erreur( 'massifs_portail_saisie_invalide', 400 );
			}
		}

		return true;
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_assainir_niveaux' ) ) {
	/**
	 * Assainit `niveaux` : `sanitize_key()` sur les clés ET sur les valeurs.
	 *
	 * @param mixed           $valeur  Valeur brute du paramètre.
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 * @param string          $cle     Nom du paramètre, non utilisé.
	 *
	 * @return array<string, string>
	 */
	function massifs_rest_portail_publication_assainir_niveaux( mixed $valeur, WP_REST_Request $requete, string $cle ): array {
		unset( $requete, $cle );

		if ( ! function_exists( 'massifs_publication_assainir_niveaux' ) ) {
			return array();
		}

		return massifs_publication_assainir_niveaux( $valeur );
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_correspondances' ) ) {
	/**
	 * Correspondance entre clés d'erreur du service et erreurs de la route.
	 *
	 * @return array<string, array{code: string, statut: int}>
	 */
	function massifs_rest_portail_publication_correspondances(): array {
		return array(
			'droits_insuffisants'      => array(
				'code'   => 'massifs_portail_droits_insuffisants',
				'statut' => 403,
			),
			'jour_refuse'              => array(
				'code'   => 'massifs_portail_jour_refuse',
				'statut' => 400,
			),
			'saisie_invalide'          => array(
				'code'   => 'massifs_portail_saisie_invalide',
				'statut' => 400,
			),
			'aucune_modification'      => array(
				'code'   => 'massifs_portail_aucune_modification',
				'statut' => 409,
			),
			'etat_modifie'             => array(
				'code'   => 'massifs_portail_etat_modifie',
				'statut' => 409,
			),
			'referentiel_indisponible' => array(
				'code'   => 'massifs_portail_referentiel_indisponible',
				'statut' => 503,
			),
			'domaine_indisponible'     => array(
				'code'   => 'massifs_portail_domaine_indisponible',
				'statut' => 503,
			),
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_erreur' ) ) {
	/**
	 * Fabrique une erreur de cette route.
	 *
	 * LE MESSAGE D'UNE EXCEPTION NE VOYAGE JAMAIS : un code stable et une phrase
	 * fixe, rien d'autre. Le détail part dans le journal, sous `WP_DEBUG`
	 * seulement.
	 *
	 * @param string $code   Code d'erreur stable.
	 * @param int    $statut Statut HTTP.
	 *
	 * @return WP_Error
	 */
	function massifs_rest_portail_publication_erreur( string $code, int $statut ): WP_Error {
		$messages = array(
			'massifs_portail_droits_insuffisants'      => 'Ce compte n\'a pas le droit de publier les statuts.',
			'massifs_portail_jour_refuse'              => 'Seuls le jour courant et le jour suivant sont publiables.',
			'massifs_portail_saisie_invalide'          => 'Une valeur transmise n\'est pas reconnue.',
			'massifs_portail_aucune_modification'      => 'Aucun statut n\'a changé.',
			'massifs_portail_etat_modifie'             => 'Les statuts de ce jour ont changé depuis la lecture.',
			'massifs_portail_referentiel_indisponible' => 'Le référentiel des massifs n\'est pas disponible.',
			'massifs_portail_domaine_indisponible'     => 'La publication est momentanément impossible.',
		);

		return new WP_Error(
			$code,
			isset( $messages[ $code ] ) ? $messages[ $code ] : 'La publication n\'a pas abouti.',
			array( 'status' => $statut )
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_publication_servir' ) ) {
	/**
	 * Publie les niveaux soumis, ou refuse.
	 *
	 * Le callback ne valide rien de plus que la présence du service : toutes les
	 * gardes — droits, disponibilité du domaine, garde de jour, référentiel,
	 * validation tout-ou-rien, empreinte, diff — vivent dans
	 * `massifs_publication_publier()`, et il n'en existe pas de copie ici.
	 *
	 * Un refus PAR LIGNE n'est pas une erreur de la route : le lot a été traité, la
	 * réponse `200` nomme ce qui a été écrit et ce qui ne l'a pas été. Seules les
	 * erreurs globales, qui n'écrivent rien, deviennent des codes HTTP d'erreur.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	function massifs_rest_portail_publication_servir( WP_REST_Request $requete ): WP_REST_Response|WP_Error {
		if ( ! function_exists( 'massifs_publication_publier' ) ) {
			return massifs_rest_portail_publication_erreur( 'massifs_portail_domaine_indisponible', 503 );
		}

		$niveaux = $requete->get_param( 'niveaux' );

		$resultat = massifs_publication_publier(
			array(
				'jour_jeton' => (string) $requete->get_param( 'jour' ),
				'niveaux'    => is_array( $niveaux ) ? $niveaux : array(),
				'empreinte'  => (string) $requete->get_param( 'empreinte' ),
				'origine'    => 'rest',
			)
		);

		$erreurs = array_values( $resultat['erreurs'] );

		if ( array() !== $erreurs ) {
			$correspondances = massifs_rest_portail_publication_correspondances();
			$premiere        = (string) $erreurs[0];

			$erreur = isset( $correspondances[ $premiere ] )
				? $correspondances[ $premiere ]
				: array(
					'code'   => 'massifs_portail_saisie_invalide',
					'statut' => 400,
				);

			return massifs_rest_portail_publication_erreur( $erreur['code'], $erreur['statut'] );
		}

		$reponse = new WP_REST_Response( $resultat, 200 );

		// Posé sur NOTRE réponse, jamais par un filtre site-wide : une écriture de
		// portail ne se met jamais en cache, et un filtre global toucherait aussi la
		// lecture publique.
		$reponse->set_headers( array( 'Cache-Control' => 'no-store' ) );

		return $reponse;
	}
}
