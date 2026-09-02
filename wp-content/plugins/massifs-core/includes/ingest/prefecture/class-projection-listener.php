<?php
/**
 * Récepteur des bilans de projection émis par le domaine.
 *
 * RENVERSEMENT DE FRONTIÈRE ASSUMÉ. Jusqu'ici le connecteur publiait un
 * instantané et s'arrêtait là : la frontière avec `includes/domain/statuts/`
 * était à sens unique. Elle ne pouvait pas le rester, parce qu'un instantané
 * enregistré dont la projection échoue laisse le site sans statut sans que
 * personne, côté ingestion, ne le sache — et donc sans que rien ne relance
 * l'essai. Le connecteur s'abonne désormais à `massifs_projection_prefecture`.
 *
 * Ce que ce récepteur ne fait PAS : il ne lit aucun statut, n'écrit dans aucune
 * table du domaine, ne rejoue rien de lui-même. Il consigne un état sur
 * l'instantané et pose un drapeau en mémoire. La décision de rejeu appartient
 * au `Runner`, et à lui seul.
 *
 * DEUX NOTIONS DISTINCTES, ET LES CONFONDRE EST UN DÉFAUT GRAVE :
 *
 * 1. « le domaine a RÉPONDU » — un abonné existe et s'est exprimé, quelle que
 *    soit la forme de ce qu'il a dit ;
 * 2. « la réponse est EXPLOITABLE » — le bilan est tabulaire, son `resultat`
 *    est reconnu, son `jour` est lisible et connu du dépôt.
 *
 * Seule la seconde conditionne une écriture. La PREMIÈRE, et elle seule,
 * conditionne le verdict `sans_projecteur` du `Runner`. Un bilan difforme
 * n'écrit donc rien, mais il compte comme une réponse : c'est la preuve qu'un
 * projecteur existe, et il ne doit jamais faire conclure à son absence.
 * Conclure `sans_projecteur` sur un bilan difforme dirait « le domaine est
 * absent » au moment précis où il vient de parler — un diagnostic faux, posé
 * sur l'état de l'instantané, que rien en aval ne pourrait plus corriger de
 * lui-même.
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
 * Capture des bilans de projection, et report sur l'instantané concerné.
 */
final class ProjectionListener {

	/**
	 * Le domaine a-t-il répondu depuis le dernier `armer()` ?
	 *
	 * Drapeau EN MÉMOIRE, pour la requête courante seulement : il répond à une
	 * question et une seule — « quelqu'un a-t-il conclu une projection pour
	 * l'instantané que je viens de publier ? ». Un drapeau persistant
	 * répondrait à la question d'hier.
	 *
	 * Un booléen, et pas le bilan lui-même : un bilan difforme n'est pas
	 * stockable dans une structure typée, alors qu'il constitue bel et bien une
	 * réponse. Séparer les deux notions est ce qui empêche un projecteur
	 * bavard-mais-cassé d'être pris pour un projecteur absent.
	 */
	private static bool $repondu = false;

	/**
	 * Réarme le drapeau avant une émission.
	 */
	public static function armer(): void {
		self::$repondu = false;
	}

	/**
	 * Quelqu'un a-t-il conclu une projection depuis le dernier `armer()` ?
	 *
	 * Vrai dès qu'un abonné s'est exprimé, même de façon inexploitable.
	 */
	public static function a_repondu(): bool {
		return self::$repondu;
	}

	/**
	 * Abonné de `massifs_projection_prefecture`.
	 *
	 * STRICTEMENT DÉFENSIF : la forme du bilan appartient au domaine et n'est
	 * figée par aucun contrat. Un bilan non tabulaire, un `resultat` inconnu, un
	 * `jour` illisible ou une date absente du dépôt n'écrivent rien — mais tous
	 * comptent comme une réponse, et laissent donc la date rejouable.
	 *
	 * @param mixed $bilan Bilan publié par le domaine, de forme non garantie.
	 */
	public static function capter( $bilan = null ): void {
		/*
		 * PREMIÈRE INSTRUCTION, AVANT TOUT RETOUR ANTICIPÉ.
		 *
		 * Le placer plus bas — après le contrôle de forme, par exemple — ferait
		 * conclure `sans_projecteur` sur un bilan difforme, alors qu'un
		 * projecteur vient précisément de parler : l'instantané porterait
		 * durablement un diagnostic faux sur l'état du domaine.
		 */
		self::$repondu = true;

		if ( ! is_array( $bilan ) ) {
			return;
		}

		$resultat = isset( $bilan['resultat'] ) && is_string( $bilan['resultat'] ) ? $bilan['resultat'] : '';

		if ( ! in_array( $resultat, SnapshotRepository::PROJECTION_RESULTATS_DU_DOMAINE, true ) ) {
			return;
		}

		$jour = isset( $bilan['jour'] ) && is_string( $bilan['jour'] ) ? trim( $bilan['jour'] ) : '';
		$date = SourceCalendar::from_iso( $jour );

		if ( null === $date ) {
			return;
		}

		SnapshotRepository::update_projection(
			$date->format( 'Ymd' ),
			array(
				'resultat' => $resultat,
				'le'       => gmdate( DATE_ATOM ),
				'motif'    => isset( $bilan['motif'] ) && is_string( $bilan['motif'] ) ? $bilan['motif'] : '',
			)
		);
	}
}
