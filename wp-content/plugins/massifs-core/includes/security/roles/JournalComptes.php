<?php
/**
 * Journal de repli des évènements de compte.
 *
 * STATUT EXPLICITE : REPLI (arbitrage A-21). Le §6 du brief exige que la création,
 * la suspension et la réinitialisation d'un compte soient journalisées. Cette ligne
 * ne doit dépendre d'AUCUNE chaîne sœur : si #15 construit une vraie table d'audit,
 * elle s'abonne à l'action `massifs_compte_evenement` et ce registre devient
 * redondant sans gêner personne. Si elle ne la construit pas, la DoD tient quand même.
 *
 * POURQUOI UNE OPTION ET PAS UN TRANSIENT — même raison que
 * `Alertes\Verrou` : un transient est évincible à tout moment par un cache objet sous
 * pression, et une éviction vaut ici une preuve d'audit perdue.
 *
 * POURQUOI `autoload = false` — le registre n'est lu que depuis un écran
 * d'administration ; le charger à chaque requête publique coûterait sans rien servir.
 *
 * AUCUN SECRET, AUCUNE ADRESSE IP, AUCUN MOT DE PASSE n'entre ici (interdit 8) : les
 * détails sont réduits aux scalaires que l'appelant a explicitement fournis, et
 * aucun appelant n'y met de valeur sensible.
 *
 * @package Massifs\Security\Roles
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registre borné des évènements de compte.
 */
final class JournalComptes {

	/**
	 * Option de stockage du registre.
	 */
	public const OPTION = 'massifs_journal_comptes';

	/**
	 * Nombre maximal d'entrées conservées.
	 *
	 * Le bornage se fait sur l'ORDRE D'INSERTION, jamais sur un calcul de date :
	 * un registre n'a pas à savoir lire une date pour rester borné. 200 entrées
	 * couvrent plusieurs années d'un portail à quelques comptes.
	 */
	private const MAX_ENTREES = 200;

	/**
	 * Longueur maximale d'un détail textuel conservé.
	 */
	private const LONGUEUR_DETAIL = 200;

	/**
	 * Enregistre un évènement émis par `massifs_compte_evenement`.
	 *
	 * Abonné à notre propre action plutôt qu'appelé directement : la journalisation
	 * emprunte exactement le même chemin que celle d'une chaîne sœur, donc un
	 * évènement absent du registre est un évènement jamais émis, jamais un
	 * évènement mal branché.
	 *
	 * @param array<string, mixed> $evenement Charge de l'action.
	 */
	public static function enregistrer( array $evenement ): void {
		$type = isset( $evenement['type'] ) ? sanitize_key( (string) $evenement['type'] ) : '';

		if ( '' === $type ) {
			return;
		}

		$entree = array(
			'type'            => $type,
			'cible_id'        => isset( $evenement['cible_id'] ) ? absint( $evenement['cible_id'] ) : 0,
			'cible_login'     => isset( $evenement['cible_login'] ) ? sanitize_user( (string) $evenement['cible_login'], true ) : '',
			'acteur_id'       => isset( $evenement['acteur_id'] ) ? absint( $evenement['acteur_id'] ) : 0,
			'instant_iso_utc' => isset( $evenement['instant_iso_utc'] ) ? sanitize_text_field( (string) $evenement['instant_iso_utc'] ) : '',
			'details'         => self::details( $evenement['details'] ?? array() ),
		);

		$registre   = self::entrees();
		$registre[] = $entree;
		$registre   = array_slice( $registre, -self::MAX_ENTREES );

		update_option( self::OPTION, $registre, false );
	}

	/**
	 * Entrées du registre, de la plus ancienne à la plus récente.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function entrees(): array {
		$brut = get_option( self::OPTION, array() );

		if ( ! is_array( $brut ) ) {
			return array();
		}

		$propres = array();

		foreach ( $brut as $entree ) {
			if ( is_array( $entree ) && isset( $entree['type'] ) ) {
				$propres[] = $entree;
			}
		}

		return $propres;
	}

	/**
	 * Réduit les détails à des scalaires bornés.
	 *
	 * Une charge imbriquée ou un objet ne sont pas conservés : un journal d'audit
	 * qui stocke une structure arbitraire finit par stocker ce qu'on n'y voulait
	 * pas.
	 *
	 * @param mixed $details Détails bruts.
	 *
	 * @return array<string, scalar>
	 */
	private static function details( mixed $details ): array {
		if ( ! is_array( $details ) ) {
			return array();
		}

		$propres = array();

		foreach ( $details as $cle => $valeur ) {
			$nom = sanitize_key( (string) $cle );

			if ( '' === $nom || ! is_scalar( $valeur ) ) {
				continue;
			}

			if ( is_bool( $valeur ) || is_int( $valeur ) ) {
				$propres[ $nom ] = $valeur;

				continue;
			}

			$propres[ $nom ] = substr( sanitize_text_field( (string) $valeur ), 0, self::LONGUEUR_DETAIL );
		}

		return $propres;
	}
}
