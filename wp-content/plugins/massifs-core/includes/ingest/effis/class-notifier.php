<?php
/**
 * Alertes courriel du module.
 *
 * DEUX ALERTES, UNE SEULE FOIS CHACUNE PAR ÉPISODE :
 *
 * (a) TRAVERSÉE DE LA PÉREMPTION — l'instant où la couche disparaît du site.
 *     C'est le seul évènement qui mérite un courriel : un échec isolé n'a
 *     aucune conséquence visible tant que le dernier relevé reste frais.
 * (b) REJET DE VALIDATION — une source récupérée puis refusée signale une
 *     source qui a changé de forme, ce qui demande une intervention humaine.
 *
 * Le verrou est posé QUEL QUE SOIT LE RETOUR DE `wp_mail` : sans cela, un relais
 * courriel en panne relancerait un envoi à chaque exécution horaire. Il se
 * ré-arme au premier succès.
 *
 * Texte brut uniquement, et disant explicitement CE QUE LE SITE AFFICHE : c'est
 * l'information qui détermine l'urgence de la réaction.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Envoi des alertes d'exploitation.
 */
final class Notifier {

	/**
	 * Alerte : la couche vient de franchir la péremption.
	 *
	 * @param array<string,mixed> $etat État courant du module.
	 */
	public static function alert_peremption( array $etat ): void {
		if ( StateRepository::was_alerted( 'peremption' ) ) {
			return;
		}

		$lignes = array(
			'Le dernier relevé des zones parcourues par le feu a dépassé sa durée de validité.',
			'',
			sprintf( 'Durée de validité configurée : %d secondes.', Settings::peremption_secondes() ),
			'',
			'Passé ce délai, la fenêtre glissante servie ne peut plus être tenue pour exacte :',
			'une zone survenue depuis en serait absente, et le site afficherait une absence',
			'périmée comme si elle avait été mesurée.',
		);

		self::envoyer(
			'peremption',
			'[MASSIFS] Zones parcourues par le feu : couche périmée, retirée du site',
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Alerte : couche récupérée puis rejetée par la validation.
	 *
	 * @param \WP_Error           $erreur Rejet motivé.
	 * @param array<string,mixed> $etat   État courant du module.
	 */
	public static function alert_rejected( \WP_Error $erreur, array $etat ): void {
		if ( StateRepository::was_alerted( 'rejet' ) ) {
			return;
		}

		$donnees = $erreur->get_error_data();
		$couche  = is_array( $donnees ) && isset( $donnees['couche'] ) ? (string) $donnees['couche'] : 'inconnue';

		$lignes = array(
			'La couche des zones parcourues par le feu a été récupérée, puis REJETÉE par la validation.',
			'',
			sprintf( 'Couche : %s', $couche ),
			sprintf( 'Motif  : %s', $erreur->get_error_code() ),
			sprintf( 'Détail : %s', wp_strip_all_tags( (string) $erreur->get_error_message() ) ),
			'',
			'Un rejet signale soit une source qui a changé de forme, soit une donnée aberrante.',
			'Dans les deux cas, RIEN N\'A ÉTÉ ENREGISTRÉ : le relevé précédent reste en place',
			'jusqu\'à sa propre péremption.',
		);

		self::envoyer(
			'rejet',
			'[MASSIFS] Zones parcourues par le feu : source rejetée par la validation',
			array_merge( $lignes, self::bloc_commun( $etat ) )
		);
	}

	/**
	 * Bloc de pied commun aux deux alertes.
	 *
	 * @param array<string,mixed> $etat État courant du module.
	 * @return string[]
	 */
	private static function bloc_commun( array $etat ): array {
		$couche = Couche::etat();

		return array(
			'',
			'CE QUE LE SITE AFFICHE',
			sprintf( 'État servi de la couche : %s', (string) $couche['etat'] ),
			'couche_effis_indisponible : la bande annonce une donnée momentanément indisponible,',
			'sans fraîcheur et sans attribution. Aucune zone périmée n\'est présentée comme mesurée,',
			'et aucune absence périmée n\'est présentée comme une mesure.',
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
	 * @param string   $type   Type d'alerte, parmi StateRepository::ALERTES.
	 * @param string   $sujet  Sujet du message.
	 * @param string[] $lignes Corps, ligne à ligne.
	 */
	private static function envoyer( string $type, string $sujet, array $lignes ): void {
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

		StateRepository::mark_alerted( $type );
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
