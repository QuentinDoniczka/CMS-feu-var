<?php
/**
 * Route authentifiée de lecture du journal des statuts.
 *
 * `GET /wp-json/massifs/v1/portail/historique`
 *
 * Namespace `massifs/v1`, celui de la surface publique ; chemin préfixé
 * `portail/` pour marquer la surface AUTHENTIFIÉE. La carte publique n'appelle
 * JAMAIS cette route.
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` : même convention que
 * `rest/public`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_rest_portail_historique_enregistrer_routes' ) ) {
	/**
	 * Déclare la route du journal.
	 *
	 * Idempotente : `register_rest_route()` remplace une déclaration identique
	 * sans dupliquer, et le module ne s'amorce de toute façon qu'une fois.
	 */
	function massifs_rest_portail_historique_enregistrer_routes(): void {
		register_rest_route(
			'massifs/v1',
			'/portail/historique',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'massifs_rest_portail_historique_servir',
				'permission_callback' => 'massifs_rest_portail_historique_autorise',
				'args'                => massifs_rest_portail_historique_args(),
			)
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_autorise' ) ) {
	/**
	 * Porte de la route : authentification, puis capacité.
	 *
	 * JAMAIS `__return_true`, JAMAIS de repli sur `manage_options`. La capacité
	 * appartient à la chaîne des rôles ; l'absence de définition doit refuser,
	 * jamais ouvrir.
	 *
	 * L'IDENTIFIANT DE LA CAPACITÉ N'EST ÉCRIT QU'UNE FOIS, dans
	 * `MASSIFS_HISTORIQUE_CAPACITE` (`admin/historique/filtres.php`), partagée
	 * par les trois portes. Un littéral ici survivrait à un renommage de la
	 * constante et laisserait cette porte seule sur un nom mort.
	 *
	 * AUCUNE VÉRIFICATION DE NONCE EXPLICITE, et ce n'est pas un oubli : pour une
	 * requête authentifiée par cookie, le cœur exige déjà `X-WP-Nonce` avant de
	 * considérer l'utilisateur connecté (`rest_cookie_check_errors`). Le
	 * revérifier ici casserait les authentifications par jeton, qui n'ont pas de
	 * nonce à présenter.
	 *
	 * @return bool|WP_Error
	 */
	function massifs_rest_portail_historique_autorise(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'massifs_historique_non_authentifie',
				'Le journal des statuts n\'est accessible qu\'à un compte authentifié.',
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( MASSIFS_HISTORIQUE_CAPACITE ) ) {
			return new WP_Error(
				'massifs_historique_interdit',
				'Votre compte n\'a pas l\'autorisation de consulter l\'historique des statuts.',
				array( 'status' => 403 )
			);
		}

		return true;
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_args' ) ) {
	/**
	 * Schéma d'arguments — SECONDE couche, plus stricte que l'analyseur.
	 *
	 * Elle rend `400 rest_invalid_param` là où l'analyseur se contenterait
	 * d'ignorer. L'ANALYSEUR RESTE L'AUTORITÉ : le callback lui repasse les
	 * paramètres et n'interprète rien lui-même.
	 *
	 * Les tailles de page admises viennent de l'analyseur, jamais recopiées :
	 * un `enum` qui divergerait de lui refuserait en `400` une valeur que
	 * l'écran propose, ou l'inverse.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_rest_portail_historique_args(): array {
		$sources = function_exists( 'massifs_sources_statut' ) ? massifs_sources_statut() : array();

		$jour = array(
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'massifs_rest_portail_historique_valider_jour',
		);

		return array(
			'massif'           => array(
				'description'       => 'Code de massif, y compris un massif retiré du référentiel.',
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'massifs_rest_portail_historique_valider_code',
			),
			'auteur'           => array(
				'description'       => 'Identifiant d\'un auteur présent dans le journal.',
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'massifs_rest_portail_historique_valider_entier',
			),
			'source'           => array(
				'description'       => 'Provenance du statut.',
				'type'              => 'string',
				'required'          => false,
				'enum'              => $sources,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'jour_debut'       => array_merge( $jour, array( 'description' => 'Jour de validité minimal, AAAA-MM-JJ.' ) ),
			'jour_fin'         => array_merge( $jour, array( 'description' => 'Jour de validité maximal, AAAA-MM-JJ.' ) ),
			'enregistre_debut' => array_merge( $jour, array( 'description' => 'Jour d\'enregistrement minimal, AAAA-MM-JJ, en heure de Paris.' ) ),
			'enregistre_fin'   => array_merge( $jour, array( 'description' => 'Jour d\'enregistrement maximal, AAAA-MM-JJ, en heure de Paris.' ) ),
			'paged'            => array(
				'description'       => 'Page demandée, à partir de 1.',
				'type'              => 'integer',
				'required'          => false,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'par_page'         => array(
				'description'       => 'Nombre d\'écritures par page.',
				'type'              => 'integer',
				'required'          => false,
				'enum'              => massifs_historique_par_page_admises(),
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_valider_jour' ) ) {
	/**
	 * Valide un paramètre de jour.
	 *
	 * LA VALEUR EST BRUTE ICI : le cœur valide AVANT d'assainir. La forme est
	 * donc contrôlée sans aucune tolérance. Une chaîne vide est admise : elle
	 * signifie « pas de borne ».
	 *
	 * @param mixed $valeur Valeur brute du paramètre.
	 */
	function massifs_rest_portail_historique_valider_jour( mixed $valeur ): bool {
		$brut = is_scalar( $valeur ) ? trim( (string) $valeur ) : '';

		return '' === $brut || 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $brut );
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_valider_code' ) ) {
	/**
	 * Valide la FORME d'un code de massif.
	 *
	 * L'existence, elle, est vérifiée par l'analyseur, qui la signale en
	 * `filtres_ignores` plutôt qu'en `400` : un massif retiré du référentiel
	 * garde son historique, et une demande sur un code inconnu n'est pas une
	 * requête malformée.
	 *
	 * @param mixed $valeur Valeur brute du paramètre.
	 */
	function massifs_rest_portail_historique_valider_code( mixed $valeur ): bool {
		$brut = is_scalar( $valeur ) ? (string) $valeur : '';

		return '' === $brut || 1 === preg_match( '/^[a-z0-9_-]{1,64}$/', $brut );
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_valider_entier' ) ) {
	/**
	 * Valide un paramètre entier positif ou nul.
	 *
	 * @param mixed $valeur Valeur brute du paramètre.
	 */
	function massifs_rest_portail_historique_valider_entier( mixed $valeur ): bool {
		return is_numeric( $valeur ) && (int) $valeur >= 0;
	}
}

if ( ! function_exists( 'massifs_rest_portail_historique_servir' ) ) {
	/**
	 * Sert une page du journal.
	 *
	 * `entrees` est TOUJOURS un tableau, jamais `null`. Les valeurs sont BRUTES
	 * et non échappées — c'est du JSON, et les libellés officiels y voyagent
	 * verbatim, apostrophes comprises.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	function massifs_rest_portail_historique_servir( WP_REST_Request $requete ): WP_REST_Response|WP_Error {
		if ( ! function_exists( 'massifs_historique_filtres_depuis_requete' )
			|| ! function_exists( 'massifs_historique_donnees' ) ) {
			return new WP_Error(
				'massifs_journal_indisponible',
				'Le journal des statuts est momentanément indisponible.',
				array(
					'status'             => 503,
					'fonctions_absentes' => array( 'massifs_historique_filtres_depuis_requete', 'massifs_historique_donnees' ),
				)
			);
		}

		// L'ANALYSEUR EST L'AUTORITÉ : on lui repasse les paramètres tels quels.
		$filtres = massifs_historique_filtres_depuis_requete( $requete->get_params() );
		$donnees = massifs_historique_donnees( $filtres );

		if ( array() !== $donnees['fonctions_absentes'] ) {
			return new WP_Error(
				'massifs_journal_indisponible',
				'Le journal des statuts est momentanément indisponible.',
				array(
					'status'             => 503,
					'fonctions_absentes' => $donnees['fonctions_absentes'],
				)
			);
		}

		if ( true === $donnees['erreur'] ) {
			// Le détail a déjà été consigné côté serveur sous `WP_DEBUG` : une
			// trace nue ne voyage jamais dans une réponse.
			return new WP_Error(
				'massifs_domaine_en_erreur',
				'Le journal des statuts n\'a pas pu être assemblé.',
				array( 'status' => 503 )
			);
		}

		$reponse = new WP_REST_Response(
			array(
				'entrees'         => $donnees['entrees'],
				'pagination'      => array(
					'page'     => $donnees['page'],
					'par_page' => $donnees['par_page'],
					'total'    => $donnees['total'],
					'pages'    => $donnees['pages'],
					'id_max'   => $donnees['id_max'],
				),
				'filtres'         => massifs_historique_parametres( $filtres ),
				'filtres_ignores' => $filtres['rejets'],
				'etat'            => $donnees['etat'],
			),
			200
		);

		// Cette réponse VARIE SELON L'UTILISATEUR, contrairement à la route
		// publique : aucun cache partagé ne doit pouvoir la servir à un anonyme.
		$reponse->set_headers(
			array(
				'Cache-Control' => 'no-store, private',
				'X-Robots-Tag'  => 'noindex, nofollow',
			)
		);

		return $reponse;
	}
}
