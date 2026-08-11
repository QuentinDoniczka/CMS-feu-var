<?php
/**
 * Alertes courriel du connecteur préfecture.
 *
 * Une alerte par date et par type, jamais une par tentative : la récurrence est
 * horaire, un envoi par tentative noierait la boîte du gestionnaire et
 * finirait ignoré — donc inutile le jour où il compte.
 *
 * Texte brut uniquement : ces messages passent par des relais et des filtres
 * anti-spam, et leur contenu n'a aucun besoin de mise en forme.
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
 * Envoi des alertes d'exploitation.
 */
final class Notifier {

	/**
	 * Alerte : fenêtre de publication close sans statut récupéré.
	 *
	 * @param string              $date_ymd Date de validité visée, format `Ymd`.
	 * @param array<string,mixed> $etat     État courant du connecteur.
	 */
	public static function alert_window_closed( string $date_ymd, array $etat ): void {
		if ( StateRepository::was_alerted( $date_ymd, 'fenetre' ) ) {
			return;
		}

		$lignes = array(
			sprintf( 'Aucun statut n\'a pu être récupéré pour le %s.', self::date_lisible( $date_ymd ) ),
			'',
			'La fenêtre de publication de la préfecture est close et le fichier attendu n\'a pas été obtenu.',
		);

		self::envoyer(
			$date_ymd,
			'fenetre',
			sprintf( '[MASSIFS] Statuts préfecture du %s non récupérés', self::date_lisible( $date_ymd ) ),
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Alerte : payload récupéré mais rejeté par la validation.
	 *
	 * @param string              $date_ymd Date de validité visée, format `Ymd`.
	 * @param \WP_Error           $e        Rejet motivé.
	 * @param array<string,mixed> $etat     État courant du connecteur.
	 */
	public static function alert_rejected( string $date_ymd, \WP_Error $e, array $etat ): void {
		if ( StateRepository::was_alerted( $date_ymd, 'rejet' ) ) {
			return;
		}

		$donnees = $e->get_error_data();
		$couche  = is_array( $donnees ) && isset( $donnees['couche'] ) ? (string) $donnees['couche'] : 'inconnue';

		$lignes = array(
			sprintf( 'Le fichier de statuts du %s a été récupéré, puis REJETÉ par la validation.', self::date_lisible( $date_ymd ) ),
			'',
			sprintf( 'Couche : %s', $couche ),
			sprintf( 'Motif  : %s', $e->get_error_code() ),
			sprintf( 'Détail : %s', wp_strip_all_tags( (string) $e->get_error_message() ) ),
			'',
			'Un rejet signale soit une source qui a changé de forme, soit une donnée aberrante. Dans les deux cas, rien n\'a été enregistré.',
		);

		self::envoyer(
			$date_ymd,
			'rejet',
			sprintf( '[MASSIFS] Statuts préfecture du %s rejetés', self::date_lisible( $date_ymd ) ),
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Bloc de pied commun aux deux alertes.
	 *
	 * Il dit explicitement ce que le site affiche : c'est l'information qui
	 * détermine l'urgence de la réaction du gestionnaire.
	 *
	 * @param array<string,mixed> $etat État courant du connecteur.
	 * @return string[]
	 */
	private static function bloc_commun( array $etat ): array {
		$attribution = Settings::attribution();

		return array(
			'',
			'CE QUE LE SITE AFFICHE',
			'Le site affiche « information non disponible, consultez la carte officielle ».',
			'Il n\'affiche PAS une donnée périmée : aucun statut de la veille n\'est présenté comme courant.',
			'',
			'Carte officielle de la préfecture : ' . $attribution['url_carte'],
			'',
			sprintf( 'Échecs consécutifs : %d', absint( $etat['echecs_consecutifs'] ?? 0 ) ),
			sprintf( 'Dernière réussite  : %s', self::valeur( $etat['derniere_reussite'] ?? null ) ),
			sprintf( 'Dernière tentative : %s', self::valeur( $etat['derniere_tentative'] ?? null ) ),
			'',
			'Administration du site : ' . admin_url(),
		);
	}

	/**
	 * Envoie l'alerte puis pose le verrou.
	 *
	 * Le verrou est posé quel que soit le retour de `wp_mail` : sans cela, un
	 * relais courriel en panne relancerait un envoi à chaque exécution horaire.
	 *
	 * @param string   $date_ymd Date de validité visée.
	 * @param string   $type     Type d'alerte.
	 * @param string   $sujet    Sujet du message.
	 * @param string[] $lignes   Corps, ligne à ligne.
	 */
	private static function envoyer( string $date_ymd, string $type, string $sujet, array $lignes ): void {
		$destinataires = Settings::destinataires_alerte();

		if ( array() === $destinataires ) {
			return;
		}

		wp_mail(
			$destinataires,
			$sujet,
			implode( "\n", $lignes ) . "\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		StateRepository::mark_alerted( $date_ymd, $type );
	}

	/**
	 * Date lisible en français à partir d'un `Ymd`.
	 *
	 * @param string $date_ymd Date au format `Ymd`.
	 */
	private static function date_lisible( string $date_ymd ): string {
		$date = SourceCalendar::from_ymd( $date_ymd );

		return null === $date ? $date_ymd : $date->format( 'd/m/Y' );
	}

	/**
	 * Rend une valeur d'état lisible.
	 *
	 * @param mixed $valeur Valeur brute.
	 */
	private static function valeur( $valeur ): string {
		return is_scalar( $valeur ) && '' !== (string) $valeur ? (string) $valeur : 'jamais';
	}
}
