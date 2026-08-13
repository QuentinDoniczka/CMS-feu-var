<?php
/**
 * Route publique de lecture des statuts du jour.
 *
 * `GET /wp-json/massifs/v1/statuts[?jour=YYYY-MM-DD]`
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` : voir l'en-tête de
 * `charge-statuts.php`.
 *
 * Aucune route en écriture n'existe dans l'espace `massifs/v1` : seule
 * `WP_REST_Server::READABLE` est déclarée, et la réponse ne varie ni selon
 * l'utilisateur, ni selon la session, ni selon un cookie.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_rest_public_enregistrer_routes' ) ) {
	/**
	 * Déclare les routes publiques de lecture.
	 *
	 * Appelée sur `rest_api_init`, seul hook consommé par ce module.
	 */
	function massifs_rest_public_enregistrer_routes(): void {
		register_rest_route(
			'massifs/v1',
			'/statuts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'massifs_rest_public_servir_statuts',
				/*
				 * Lecture publique, sans authentification : c'est l'objet même du
				 * §5.4 du brief, qui promet un point d'accès JSON réutilisable servi
				 * depuis notre domaine. Écrit explicitement — jamais absent, jamais
				 * `is_user_logged_in` : une route publique dont la sortie varie par
				 * session est un cache empoisonnable.
				 */
				'permission_callback' => '__return_true',
				'args'                => massifs_rest_public_args_statuts(),
			)
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_args_statuts' ) ) {
	/**
	 * Arguments de la route.
	 *
	 * Aucun `default` : l'absence du paramètre et une valeur vide sont deux
	 * situations différentes. Absent, le jour courant est servi ; vide, la requête
	 * est refusée — jamais de repli silencieux sur aujourd'hui.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_args_statuts(): array {
		return array(
			'jour' => array(
				'description'       => 'Jour de validité demandé, au format YYYY-MM-DD. Seuls le jour courant et le jour suivant, en Europe/Paris, sont servis.',
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'massifs_rest_public_valider_jour',
			),
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_valider_jour' ) ) {
	/**
	 * Valide le paramètre `jour`.
	 *
	 * LA VALEUR EST BRUTE ICI : le cœur exécute la validation AVANT
	 * l'assainissement. La forme est donc contrôlée sans aucune tolérance —
	 * `?jour=%202026-08-13` échoue, et c'est voulu.
	 *
	 * Aucune erreur `503` n'est émise depuis ici : le cœur agrège les erreurs de
	 * validation dans un `rest_invalid_param` en `400`, et le statut d'une erreur
	 * individuelle n'est pas propagé. La garde de disponibilité de l'API
	 * appartient au callback. Si l'horloge du domaine est absente, seule la forme
	 * est validée — le callback re-contrôlera les bornes.
	 *
	 * @param mixed           $valeur  Valeur brute du paramètre.
	 * @param WP_REST_Request $requete Requête entrante, non utilisée.
	 * @param string          $cle     Nom du paramètre, non utilisé.
	 *
	 * @return bool|WP_Error
	 */
	function massifs_rest_public_valider_jour( mixed $valeur, WP_REST_Request $requete, string $cle ): bool|WP_Error {
		$brut = is_scalar( $valeur ) ? (string) $valeur : '';

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $brut ) ) {
			return massifs_rest_public_erreur( 'massifs_jour_invalide', 400 );
		}

		if ( ! function_exists( 'massifs_jour_courant' ) || ! function_exists( 'massifs_jour_suivant' ) ) {
			return true;
		}

		if ( $brut !== massifs_jour_courant() && $brut !== massifs_jour_suivant() ) {
			return massifs_rest_public_erreur( 'massifs_jour_hors_bornes', 400 );
		}

		return true;
	}
}

if ( ! function_exists( 'massifs_rest_public_fonctions_requises' ) ) {
	/**
	 * Fonctions de domaine sans lesquelles aucun statut honnête ne peut être produit.
	 *
	 * Liste FERMÉE. Elles viennent de trois modules de domaine indépendants, qui
	 * peuvent échouer à charger séparément : leur absence doit produire un `503`
	 * explicite, jamais une erreur fatale du cœur ni un écran blanc.
	 *
	 * `massifs_geometrie`, `massifs_emprise` et `massifs_attribution` n'y figurent
	 * pas : elles sont optionnelles et leur absence dégrade un bloc, sans jamais
	 * empêcher de servir les statuts.
	 *
	 * @return list<string>
	 */
	function massifs_rest_public_fonctions_requises(): array {
		return array(
			'massifs_jour_courant',
			'massifs_jour_suivant',
			'massifs_referentiel',
			'massifs_lacunes',
			'massifs_statuts_du_jour',
			'massifs_synthese_du_jour',
			'massifs_legende',
			'massifs_legende_est_confirmee',
			'massifs_fraicheur',
			'massifs_saison',
			'massifs_attribution_statuts',
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_api_disponible' ) ) {
	/**
	 * Toutes les fonctions requises sont-elles chargées ?
	 */
	function massifs_rest_public_api_disponible(): bool {
		return array() === massifs_rest_public_fonctions_absentes();
	}
}

if ( ! function_exists( 'massifs_rest_public_fonctions_absentes' ) ) {
	/**
	 * Fonctions requises manquantes, dans l'ordre de la liste fermée.
	 *
	 * @return list<string>
	 */
	function massifs_rest_public_fonctions_absentes(): array {
		$absentes = array();

		foreach ( massifs_rest_public_fonctions_requises() as $fonction ) {
			if ( ! function_exists( $fonction ) ) {
				$absentes[] = $fonction;
			}
		}

		return $absentes;
	}
}

if ( ! function_exists( 'massifs_rest_public_servir_statuts' ) ) {
	/**
	 * Sert les statuts du jour demandé.
	 *
	 * L'ORDRE DES GARDES EST CONTRACTUEL et ne se réarrange pas :
	 *
	 * 1. disponibilité de l'API de domaine ;
	 * 2. résolution des bornes, UNE SEULE FOIS, mémorisées ;
	 * 3. re-contrôle de l'appartenance du jour à ces bornes ;
	 * 4. référentiel vide ;
	 * 5. assemblage sous `try` ;
	 * 6. émission.
	 *
	 * L'étape 3 n'est pas une redite du `validate_callback` : une garde qui dépend
	 * d'une autre garde n'est pas une garde. La validation peut être court-circuitée
	 * par un `rest_request_before_callbacks`, par un appel interne via
	 * `rest_do_request()`, ou disparaître à la faveur d'un refactor.
	 *
	 * Course de minuit assumée : une requête validée à 23:59:59,9 pour le jour D
	 * peut être re-contrôlée à 00:00:00,1 contre `{D+1, D+2}` et sortir en `400`.
	 * Le sens de la défaillance est le bon — un refus franc, jamais un statut de la
	 * veille présenté comme courant.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	function massifs_rest_public_servir_statuts( WP_REST_Request $requete ): WP_REST_Response|WP_Error {
		if ( ! massifs_rest_public_api_disponible() ) {
			return massifs_rest_public_erreur_enrichie(
				'massifs_api_indisponible',
				503,
				array( 'fonctions_absentes' => massifs_rest_public_fonctions_absentes() )
			);
		}

		$jours = array(
			'aujourd_hui' => massifs_jour_courant(),
			'demain'      => massifs_jour_suivant(),
		);

		$demande = $requete->get_param( 'jour' );

		// Paramètre absent : le jour courant. Paramètre vide : refusé plus bas par
		// le contrôle des bornes, jamais replié sur aujourd'hui.
		$jour = null === $demande ? $jours['aujourd_hui'] : (string) $demande;

		if ( $jour !== $jours['aujourd_hui'] && $jour !== $jours['demain'] ) {
			return massifs_rest_public_erreur_enrichie(
				'massifs_jour_hors_bornes',
				400,
				array( 'jours_disponibles' => $jours )
			);
		}

		// Référentiel vide : une panne, jamais un état de la donnée. Une liste de
		// massifs vide servie en `200` se lirait « aucune restriction ».
		if ( array() === massifs_referentiel() ) {
			return massifs_rest_public_erreur( 'massifs_referentiel_indisponible', 503 );
		}

		$jour_relatif = $jour === $jours['aujourd_hui'] ? 'aujourd_hui' : 'demain';

		try {
			$charge = massifs_rest_public_charge( $jour, $jour_relatif, $jours );
		} catch ( InvalidArgumentException $exception ) {
			massifs_rest_public_journaliser( 'massifs_jour_invalide', $exception );

			return massifs_rest_public_erreur( 'massifs_jour_invalide', 400 );
		} catch ( Throwable $exception ) {
			massifs_rest_public_journaliser( 'massifs_domaine_en_erreur', $exception );

			return massifs_rest_public_erreur( 'massifs_domaine_en_erreur', 503 );
		}

		return massifs_rest_public_reponse( $charge, $requete );
	}
}
