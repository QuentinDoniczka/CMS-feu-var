<?php
/**
 * `permission_callback` procéduraux partagés avec les chaînes du portail.
 *
 * POURQUOI UNE FORME PROCÉDURALE ET PAS `array( GardeRest::class, '…' )`
 *
 * `includes/rest/public/` ne peut déclarer AUCUN espace de noms — `public` est un mot
 * réservé de PHP — et le module qui servira les routes du portail peut se retrouver
 * dans la même position. Une chaîne simple est utilisable telle quelle comme rappel,
 * depuis n'importe quel fichier, avec ou sans espace de noms, sans `use`.
 *
 * FAIL-CLOSED : si la classe de garde n'est pas chargée, ces fonctions REFUSENT.
 * Une route du portail dont le contrôle de permission a disparu doit fermer, jamais
 * s'ouvrir.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Massifs\Security\Auth\GardeRest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_rest_garde_indisponible' ) ) {
	/**
	 * Refus émis quand la couche d'autorisation n'est pas chargée.
	 */
	function massifs_rest_garde_indisponible(): WP_Error {
		return new WP_Error(
			'massifs_droits_insuffisants',
			"Le portail n'est pas disponible : ses droits ne sont pas chargés.",
			array( 'status' => 403 )
		);
	}
}

if ( ! function_exists( 'massifs_rest_peut_publier' ) ) {
	/**
	 * Publier ou corriger les statuts.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return true|WP_Error
	 */
	function massifs_rest_peut_publier( WP_REST_Request $requete ): true|WP_Error {
		if ( ! class_exists( GardeRest::class ) ) {
			return massifs_rest_garde_indisponible();
		}

		return GardeRest::peut_publier( $requete );
	}
}

if ( ! function_exists( 'massifs_rest_peut_consulter_historique' ) ) {
	/**
	 * Consulter et exporter l'historique.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return true|WP_Error
	 */
	function massifs_rest_peut_consulter_historique( WP_REST_Request $requete ): true|WP_Error {
		if ( ! class_exists( GardeRest::class ) ) {
			return massifs_rest_garde_indisponible();
		}

		return GardeRest::peut_consulter_historique( $requete );
	}
}

if ( ! function_exists( 'massifs_rest_peut_gerer_gestionnaires' ) ) {
	/**
	 * Gérer les comptes gestionnaires.
	 *
	 * @param WP_REST_Request $requete Requête entrante.
	 *
	 * @return true|WP_Error
	 */
	function massifs_rest_peut_gerer_gestionnaires( WP_REST_Request $requete ): true|WP_Error {
		if ( ! class_exists( GardeRest::class ) ) {
			return massifs_rest_garde_indisponible();
		}

		return GardeRest::peut_gerer_gestionnaires( $requete );
	}
}
