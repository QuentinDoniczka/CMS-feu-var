<?php
/**
 * Émission des en-têtes de sécurité sur le front public.
 *
 * CETTE CLASSE NE DÉCIDE RIEN. Elle demande la carte à `Politique` et l'émet. Toute
 * règle, tout défaut, tout filtre vit dans `Politique` — sans quoi ce qui est émis
 * finirait par différer de ce que `massifs_durcissement_entetes()` annonce, et la
 * surface de preuve du module mentirait.
 *
 * PÉRIMÈTRE OBTENU PAR CONSTRUCTION, PAS PAR CONDITIONS
 *
 * `send_headers` n'est appelé que depuis `WP::main()`. Il ne se déclenche donc NI
 * dans `wp-admin`, NI sur `wp-login.php`, NI sur une requête REST — trois surfaces
 * où le cœur injecte massivement de l'inline et où une CSP stricte casserait
 * l'administration. Ce n'est pas une liste de conditions à maintenir : c'est une
 * propriété du crochet choisi. La garde `is_admin()` ci-dessous est une ceinture,
 * pas le mécanisme.
 *
 * CE QUE CETTE CLASSE NE COUVRE PAS, ET QUI RELÈVE DE L'INFRASTRUCTURE : les
 * ressources servies directement par Apache (CSS, polices, tuiles) ne traversent
 * jamais PHP, et les réponses REST ne déclenchent pas `send_headers`. Coutures S-2
 * et S-3 du contrat #16. Ne pas tenter de les rattraper d'ici par un filtre global
 * d'authentification REST : voir l'encadré de `auth/GardeRest.php`.
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Durcissement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pose les en-têtes de sécurité de la réponse publique.
 */
final class Entetes {

	/**
	 * Émet les en-têtes de sécurité de la requête courante.
	 *
	 * `headers_sent()` est contrôlé : après le premier octet de corps, `header()`
	 * n'émettrait qu'un avertissement PHP visible en haut de page publique — bruit
	 * inutile là où l'en-tête est de toute façon perdu.
	 *
	 * @param mixed $wp Environnement de requête, non utilisé.
	 */
	public static function poser( mixed $wp = null ): void {
		unset( $wp );

		if ( is_admin() || headers_sent() ) {
			return;
		}

		foreach ( Politique::entetes() as $nom => $valeur ) {
			// `replace` laissé à `true` : une politique de sécurité remplace, elle ne
			// s'ajoute pas à une valeur antérieure plus permissive.
			header( $nom . ': ' . $valeur );
		}
	}
}
