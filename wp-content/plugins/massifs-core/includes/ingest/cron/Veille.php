<?php
/**
 * Veille de fraîcheur : observer ce que le site AFFICHE.
 *
 * Cette veille ne regarde ni un connecteur, ni un transport, ni un mode de
 * fonctionnement : elle lit la fraîcheur telle que le thème la lit, et signale
 * l'incident quand la donnée servie est périmée. C'est ce qui la rend utile là
 * où aucun connecteur automatique ne tourne — repli manuel, connecteur
 * désactivé, environnement de développement.
 *
 * Elle ne DÉCIDE que d'une chose : « il y a incident ». L'envoi du courriel
 * appartient au module d'alertes, abonné à l'action émise ici.
 *
 * @package Massifs\Ingest\Cron
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constat horaire de péremption.
 */
final class Veille {

	/**
	 * Observe la fraîcheur et émet le constat s'il y a incident.
	 *
	 * Le paramètre `$jour` n'est jamais passé par le cron : il existe pour que
	 * la recette puisse placer le dispositif hors période d'activité sans
	 * attendre l'hiver.
	 *
	 * @param string|null $jour Jour de validité `YYYY-MM-DD`, `null` pour aujourd'hui.
	 */
	public static function executer( ?string $jour = null ): void {
		if ( ! Planificateur::est_armee() ) {
			return;
		}

		// Aucune fonction de domaine n'est appelée à l'inclusion du module : toute
		// lecture se fait ici, dans un rappel tardif, sous garde. Sur cet arbre de
		// travail partagé, le module de domaine peut être absent ou incomplet — la
		// garde est ce qui empêche une erreur fatale au déclenchement du cron.
		if ( ! function_exists( 'massifs_fraicheur' ) ) {
			return;
		}

		try {
			$fraicheur = massifs_fraicheur( $jour );
		} catch ( \Throwable ) {
			// La lecture traverse des modules écrits par d'autres chaînes et
			// peut lever. Un rappel cron ne fait jamais tomber la requête d'un
			// visiteur : le passage suivant réessaiera dans une heure.
			return;
		}

		// Hors période d'activité, silence total. Cette garde est volontairement
		// redondante avec la suivante — le domaine garantit déjà que `perimee`
		// implique `dispositif_actif`. Elle est conservée parce que « la veille
		// se tait hors saison » est une décision de CE module et doit être
		// lisible dans son code. Si les deux clés divergeaient un jour, le
		// silence l'emporte, par conception.
		if ( true !== $fraicheur['dispositif_actif'] ) {
			return;
		}

		// UNIQUE PRÉDICAT D'INCIDENT. La règle de péremption n'est jamais
		// recalculée ici : ni comparaison d'âge, ni seuil, ni heure. Elle vit
		// dans le domaine, et `perimee` en est la seule expression.
		if ( true !== $fraicheur['perimee'] ) {
			return;
		}

		/**
		 * Signale que la donnée servie est périmée.
		 *
		 * Émise à CHAQUE exécution où l'incident tient : la déduplication
		 * appartient à l'abonné. Un abonné qui n'en poserait aucune enverrait 24
		 * messages par jour.
		 *
		 * @param array<string, mixed> $fraicheur Tableau de `massifs_fraicheur()`,
		 *                                        avec `perimee` et `dispositif_actif` à `true`.
		 */
		do_action( 'massifs_donnee_perimee_constatee', $fraicheur );
	}
}
