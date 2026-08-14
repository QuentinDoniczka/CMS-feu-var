<?php
/**
 * Alertes courriel du connecteur météo.
 *
 * Une alerte par date et par type, jamais une par tentative : la récurrence est
 * horaire, un envoi par tentative noierait la boîte du gestionnaire et finirait
 * ignoré — donc inutile le jour où il compte.
 *
 * AUCUN CORPS D'ALERTE NE PORTE UNE VALEUR DE NIVEAU, jamais, sous aucune
 * forme. Un chiffre de danger dans un courriel serait exactement l'information
 * que le site refuse d'afficher faute de libellé officiel, transmise par une
 * porte de service. Les corps ne parlent que de santé de récupération.
 *
 * Texte brut uniquement : ces messages passent par des relais et des filtres
 * anti-spam, et leur contenu n'a aucun besoin de mise en forme.
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
 * Envoi des alertes d'exploitation.
 */
final class Notifier {

	/**
	 * Alerte : échecs consécutifs au-delà du seuil, journée non couverte.
	 *
	 * @param string              $date_ymd Date de validité visée, format `Ymd`.
	 * @param array<string,mixed> $etat     État courant du connecteur.
	 */
	public static function alert_failures( string $date_ymd, array $etat ): void {
		if ( StateRepository::was_alerted( $date_ymd, 'panne' ) ) {
			return;
		}

		$lignes = array(
			sprintf( 'Aucun indicateur de danger météo n\'a pu être récupéré pour le %s.', self::date_lisible( $date_ymd ) ),
			'',
			sprintf(
				'Le connecteur a enchaîné %d échec(s) consécutif(s) : la source est injoignable, en erreur, ou répond une charge refusée.',
				absint( $etat['echecs_consecutifs'] ?? 0 )
			),
			'',
			'Un 404 n\'est PAS compté ici : il signifie « pas encore publié » et ne déclenche aucune alerte.',
		);

		self::envoyer(
			$date_ymd,
			'panne',
			sprintf( '[MASSIFS] Danger météo du %s non récupéré', self::date_lisible( $date_ymd ) ),
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Alerte : charge récupérée mais rejetée par la validation.
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
			sprintf( 'La charge météo du %s a été récupérée, puis REJETÉE par la validation.', self::date_lisible( $date_ymd ) ),
			'',
			sprintf( 'Couche : %s', $couche ),
			sprintf( 'Motif  : %s', (string) $e->get_error_code() ),
			// Le message du validateur ne porte jamais de valeur de niveau : la
			// valeur brute reste dans `detail`, qui n'est PAS repris ici.
			sprintf( 'Détail : %s', wp_strip_all_tags( (string) $e->get_error_message() ) ),
			'',
			'Un rejet signale soit une source qui a changé de forme, soit une donnée aberrante. Dans les deux cas, rien n\'a été enregistré.',
		);

		self::envoyer(
			$date_ymd,
			'rejet',
			sprintf( '[MASSIFS] Charge météo du %s rejetée', self::date_lisible( $date_ymd ) ),
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Bloc de pied commun aux deux alertes.
	 *
	 * Il dit explicitement ce que le site affiche : c'est cette information qui
	 * détermine l'urgence de la réaction du gestionnaire.
	 *
	 * @param array<string,mixed> $etat État courant du connecteur.
	 * @return string[]
	 */
	private static function bloc_commun( array $etat ): array {
		return array(
			'',
			'CE QUE LE SITE AFFICHE',
			'L\'indicateur de danger météo reste dans son état « indisponible ».',
			'Il n\'affiche AUCUN niveau, et surtout pas celui de la veille : un instantané n\'est servi que pour son propre jour de validité.',
			'',
			'Rappel : le danger météo ne détermine pas l\'accès au massif. Les statuts d\'accès viennent de la préfecture et ne sont pas concernés par cette alerte.',
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
