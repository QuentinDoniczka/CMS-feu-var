<?php
/**
 * Journal borné du module et alerte courriel sur échec.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  NI MOT DE PASSE, NI SECRET, NI CONTENU DE DUMP N'ENTRE ICI.                  │
 * │                                                                               │
 * │  CE JOURNAL VIT DANS `wp_options`, DONC DANS CHAQUE ARCHIVE, DONC DANS UN     │
 * │  FICHIER QUI PEUT ÊTRE COPIÉ HORS DU SITE. SEULS Y ENTRENT DES CHEMINS, DES   │
 * │  COMPTEURS ET DES CODES — ET CHAQUE FAIT EST TRONQUÉ, PARCE QU'UN MESSAGE     │
 * │  D'ERREUR SQL PEUT CITER UNE VALEUR DE LIGNE.                                 │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI UNE OPTION `autoload = false` ET PAS UN TRANSIENT
 *
 * Même raison que `Alertes\Verrou` : un transient est évincible à tout moment par
 * un cache objet sous pression, et un journal évincé est un journal qui ment sur
 * ce qui s'est passé. `autoload = false` parce que rien de ceci n'est lu au rendu
 * d'une page — le charger à chaque requête coûterait sans rien servir.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use Massifs\Security\Alertes\Verrou;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Journal d'exploitation du module de sauvegarde.
 */
final class Journal {

	/**
	 * Option de stockage du journal.
	 */
	public const OPTION = 'massifs_sauvegardes_journal';

	/**
	 * Option de repli du verrou d'alerte.
	 *
	 * Utilisée uniquement quand `Alertes\Verrou` est absent — module désactivé ou
	 * arbre incomplet. Un module de sauvegarde qui n'alerte pas parce qu'un AUTRE
	 * module manque serait un défaut de conception ; un module qui alerte deux fois
	 * le même jour ne serait qu'un désagrément.
	 */
	private const OPTION_VERROU = 'massifs_sauvegardes_verrou_alerte';

	/**
	 * Nombre d'entrées conservées.
	 *
	 * Bornage sur l'ORDRE D'INSERTION, jamais sur un calcul de date : un journal
	 * n'a pas à savoir lire une date pour rester borné.
	 */
	private const MAX_ENTREES = 60;

	/**
	 * Longueur maximale d'un fait consigné.
	 */
	private const LONGUEUR_FAIT = 200;

	/**
	 * Type de verrou posé par l'alerte d'échec.
	 */
	private const TYPE_ALERTE = 'sauvegarde';

	/**
	 * Consigne un évènement.
	 *
	 * @param string               $code  Code d'évènement, alphabet réduit.
	 * @param array<string, mixed> $faits Faits scalaires, tronqués.
	 */
	public static function consigner( string $code, array $faits = array() ): void {
		$entree = array(
			'code'  => self::code( $code ),
			'le'    => gmdate( 'c' ),
			'faits' => self::faits( $faits ),
		);

		$registre = self::entrees();

		$registre[] = $entree;
		$registre   = array_slice( $registre, -self::MAX_ENTREES );

		update_option( self::OPTION, $registre, false );

		/**
		 * Signale un évènement du moteur de sauvegarde.
		 *
		 * @param array<string, mixed> $entree Entrée de journal.
		 */
		do_action( 'massifs_sauvegarde_evenement', $entree );
	}

	/**
	 * Entrées du journal, assainies.
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
			if ( ! is_array( $entree ) || ! isset( $entree['code'] ) || ! is_string( $entree['code'] ) ) {
				continue;
			}

			$propres[] = array(
				'code'  => $entree['code'],
				'le'    => isset( $entree['le'] ) && is_string( $entree['le'] ) ? $entree['le'] : '',
				'faits' => isset( $entree['faits'] ) && is_array( $entree['faits'] ) ? $entree['faits'] : array(),
			);
		}

		return $propres;
	}

	/**
	 * Envoie l'alerte d'échec de sauvegarde.
	 *
	 * Abonné à `massifs_sauvegarde_echouee`. Texte brut : ces messages passent par
	 * des relais et des filtres anti-spam, et leur contenu n'a aucun besoin de mise
	 * en forme. Aucun échappement HTML n'est appliqué — une entité HTML dans un
	 * courriel texte est une corruption de donnée, pas une protection.
	 *
	 * @param WP_Error $erreur Erreur remontée par le moteur.
	 */
	public static function alerter( WP_Error $erreur ): void {
		$destinataire = Reglages::destinataire_alerte();

		if ( '' === $destinataire ) {
			// Rien n'a été tenté et une adresse peut apparaître d'ici la prochaine
			// exécution : poser le verrou perdrait la journée.
			return;
		}

		$jour = gmdate( 'Y-m-d' );

		if ( self::verrou_pose( $jour ) ) {
			return;
		}

		// LE VERROU EST POSÉ AVANT L'ENVOI, comme dans `Alertes\Peremption` et pour
		// la même raison : si une extension SMTP tierce lève une `Throwable`, poser
		// après ne poserait jamais et la tentative se rejouerait à chaque exécution.
		// Le verrou protège contre la RÉPÉTITION, pas contre l'échec.
		self::poser_verrou( $jour );

		$lignes = array(
			'La sauvegarde MASSIFS a échoué.',
			'',
			'Code    : ' . wp_strip_all_tags( $erreur->get_error_code() ),
			'Message : ' . wp_strip_all_tags( $erreur->get_error_message() ),
			'Site    : ' . get_site_url(),
			'Instant : ' . gmdate( 'c' ),
			'',
			'AUCUNE ARCHIVE N\'A ÉTÉ PRODUITE PAR CETTE EXÉCUTION.',
			'La dernière archive valide, si elle existe, est intacte : le moteur écrit',
			'sous un nom temporaire et ne renomme qu\'après succès.',
			'',
			'Relancer à la main :',
			'    wp massifs sauvegarde creer',
			'Inspecter la dernière archive :',
			'    wp massifs sauvegarde lister',
			'',
			'Cette alerte est émise au plus une fois par jour.',
		);

		wp_mail(
			$destinataire,
			'[MASSIFS] Sauvegarde en échec',
			implode( "\n", $lignes ) . "\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Le verrou d'alerte du jour est-il posé ?
	 *
	 * @param string $jour Jour `YYYY-MM-DD`.
	 */
	private static function verrou_pose( string $jour ): bool {
		if ( class_exists( Verrou::class ) ) {
			return Verrou::est_pose( Verrou::cle( self::TYPE_ALERTE, 'moteur', $jour ) );
		}

		return (string) get_option( self::OPTION_VERROU, '' ) === $jour;
	}

	/**
	 * Pose le verrou d'alerte du jour.
	 *
	 * @param string $jour Jour `YYYY-MM-DD`.
	 */
	private static function poser_verrou( string $jour ): void {
		if ( class_exists( Verrou::class ) ) {
			Verrou::poser( Verrou::cle( self::TYPE_ALERTE, 'moteur', $jour ), gmdate( 'c' ) );

			return;
		}

		update_option( self::OPTION_VERROU, $jour, false );
	}

	/**
	 * Normalise un code d'évènement.
	 *
	 * @param string $valeur Code brut.
	 */
	private static function code( string $valeur ): string {
		$propre = (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( $valeur ) ) );

		return '' === $propre ? 'inconnu' : substr( $propre, 0, 48 );
	}

	/**
	 * Réduit les faits à des scalaires tronqués.
	 *
	 * @param array<string, mixed> $faits Faits bruts.
	 *
	 * @return array<string, string>
	 */
	private static function faits( array $faits ): array {
		$propres = array();

		foreach ( $faits as $cle => $valeur ) {
			if ( ! is_string( $cle ) || ! is_scalar( $valeur ) ) {
				continue;
			}

			if ( is_bool( $valeur ) ) {
				$valeur = $valeur ? 'oui' : 'non';
			}

			$propres[ self::code( $cle ) ] = substr( wp_strip_all_tags( (string) $valeur ), 0, self::LONGUEUR_FAIT );
		}

		return $propres;
	}
}
