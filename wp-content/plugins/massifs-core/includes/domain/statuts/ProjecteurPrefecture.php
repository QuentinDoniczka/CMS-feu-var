<?php
/**
 * Projection d'un instantané d'ingestion dans le modèle de statuts.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Fraicheur\Horloge;

/**
 * Abonné de `massifs_prefecture_snapshot_enregistre`.
 *
 * C'EST LE DOMAINE QUI S'ABONNE, jamais l'ingestion qui écrit. Le connecteur
 * publie un instantané validé et s'arrête là ; décider quoi en faire nous
 * appartient. Cette classe est donc la SEULE frontière entre les deux modules,
 * et elle est à sens unique.
 *
 * ELLE EST STRICTEMENT DÉFENSIVE. La forme de l'instantané n'est pas figée par
 * un contrat : elle est déduite du code de l'ingestion, qui peut changer. Une
 * forme inattendue N'ÉCRIT RIEN et journalise. Une projection approximative
 * écrirait une donnée fausse, ce que le §4.2 du brief interdit — et l'absence
 * d'écriture laisse le site dire honnêtement « information non disponible »,
 * ce qui est toujours préférable à un chiffre inventé.
 */
final class ProjecteurPrefecture {

	/**
	 * Nom de la source dans le registre des relevés.
	 */
	private const SOURCE = 'prefecture';

	/**
	 * Projette un instantané validé par l'ingestion.
	 *
	 * @param mixed $instantane Instantané publié par le connecteur, de forme non garantie.
	 */
	public static function projeter( $instantane = null ): void {
		if ( ! is_array( $instantane ) ) {
			self::refuser( 'instantané non tabulaire' );

			return;
		}

		$jour = isset( $instantane['date_validite'] ) && is_string( $instantane['date_validite'] )
			? trim( $instantane['date_validite'] )
			: '';

		if ( ! Horloge::jour_est_valide( $jour ) ) {
			self::refuser( 'date de validité absente ou mal formée' );

			return;
		}

		if ( ! isset( $instantane['massifs'] ) || ! is_array( $instantane['massifs'] ) || array() === $instantane['massifs'] ) {
			self::refuser( 'aucun massif dans l\'instantané du ' . $jour );

			return;
		}

		$statuts = self::convertir( $instantane['massifs'], $jour, self::publie_le( $instantane ) );

		// Tout ou rien : un seul massif illisible signale un changement de forme de
		// la source. Écrire les autres produirait une carte partielle, c'est-à-dire
		// un mensonge par omission.
		if ( null === $statuts ) {
			self::refuser( 'forme de massif inattendue dans l\'instantané du ' . $jour );

			return;
		}

		$bilan = massifs_enregistrer_statuts( $statuts );

		if ( $bilan['refuses'] > 0 || 0 === $bilan['enregistres'] ) {
			self::refuser(
				sprintf( 'projection du %s incomplète : %d enregistrés, %d refusés', $jour, $bilan['enregistres'], $bilan['refuses'] )
			);

			return;
		}

		// Le relevé n'est déclaré réussi qu'ici, après une projection intégrale.
		// Le déclarer plus tôt ferait mentir la fraîcheur, ce que le §4.5 interdit.
		massifs_enregistrer_releve_reussi( self::SOURCE );
	}

	/**
	 * Convertit les entrées de l'instantané en statuts enregistrables.
	 *
	 * @param array<string|int, mixed> $massifs   Entrées de l'instantané.
	 * @param string                   $jour      Jour de validité.
	 * @param string|null              $publie_le Instant de publication de la source.
	 *
	 * @return list<array<string, mixed>>|null `null` si une seule entrée est illisible.
	 */
	private static function convertir( array $massifs, string $jour, ?string $publie_le ): ?array {
		$statuts = array();

		foreach ( $massifs as $identifiant => $entree ) {
			$code = self::code_massif( (string) $identifiant );

			if ( ! Statuts::code_est_valide( $code ) || ! is_array( $entree ) ) {
				return null;
			}

			// `is_int` strict : une chaîne numérique ou un flottant est un
			// changement de type de la source, donc un refus, jamais une
			// conversion silencieuse.
			if ( ! isset( $entree['niveau_source'] ) || ! is_int( $entree['niveau_source'] ) ) {
				return null;
			}

			$statut = array(
				'massif_code'        => $code,
				'jour_validite'      => $jour,
				'niveau_source_brut' => $entree['niveau_source'],
				'source'             => SourceStatut::RecuperationOfficielle->value,
			);

			if ( isset( $entree['procedure_source'] ) ) {
				if ( ! is_int( $entree['procedure_source'] ) ) {
					return null;
				}

				$statut['procedure_source'] = $entree['procedure_source'];
			}

			if ( null !== $publie_le ) {
				$statut['publie_prefecture_le'] = $publie_le;
			}

			$statuts[] = $statut;
		}

		return $statuts;
	}

	/**
	 * Code de massif correspondant à un identifiant de la source.
	 *
	 * SEUL point de correspondance entre l'identité publiée par la source et la
	 * nôtre. Le domaine `statuts` ne connaît aucun référentiel : `massif_code` est
	 * une chaîne opaque validée sur sa forme, jamais sur son existence. Tant
	 * qu'aucune correspondance n'est publiée par le référentiel, l'identifiant de
	 * la source EST le code — c'est la seule identité réellement observée, et
	 * n'en inventer aucune autre garde la projection réversible.
	 *
	 * @param string $identifiant Identifiant émis par la source.
	 */
	private static function code_massif( string $identifiant ): string {
		return Statuts::normaliser_code( $identifiant );
	}

	/**
	 * Instant de publication de la source, s'il est exploitable.
	 *
	 * @param array<string, mixed> $instantane Instantané publié par le connecteur.
	 */
	private static function publie_le( array $instantane ): ?string {
		if ( ! isset( $instantane['source_modifie_le'] ) || ! is_string( $instantane['source_modifie_le'] ) ) {
			return null;
		}

		return Horloge::stockage_vers_iso_utc( $instantane['source_modifie_le'] );
	}

	/**
	 * Journalise un refus de projection.
	 *
	 * Un refus ne doit JAMAIS être silencieux : le site continuera d'annoncer
	 * « information non disponible », ce qui est honnête mais doit être vu par un
	 * humain le jour même.
	 *
	 * @param string $motif Motif du refus, en clair.
	 */
	private static function refuser( string $motif ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Un refus de projection est un incident d'exploitation, il ne doit pas être silencieux.
		error_log( 'MASSIFS : instantané préfecture non projeté — ' . $motif . '.' );
	}
}
