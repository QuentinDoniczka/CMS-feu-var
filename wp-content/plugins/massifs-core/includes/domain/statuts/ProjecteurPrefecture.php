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
 * forme inattendue N'ÉCRIT RIEN et le déclare. Une projection approximative
 * écrirait une donnée fausse, ce que le §4.2 du brief interdit — et l'absence
 * d'écriture laisse le site dire honnêtement « information non disponible »,
 * ce qui est toujours préférable à un chiffre inventé.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  CONTRAT D'ÉCRITURE DE CETTE CLASSE                                      │
 * │                                                                          │
 * │  1. LE LOT ENTIER EST VALIDÉ AVANT LA PREMIÈRE INSERTION. Forme des      │
 * │     entrées, listes blanches `level` / `procedure`, résolution des codes │
 * │     par le référentiel, puis passage de chaque ligne dans les règles     │
 * │     d'écriture RÉELLES du domaine (`Statuts::erreurs_de()`). Une seule   │
 * │     ligne irrécupérable et RIEN n'est écrit. Le tout-ou-rien est donc    │
 * │     structurel, au lieu d'être constaté après coup sur une base déjà     │
 * │     à moitié remplie.                                                    │
 * │                                                                          │
 * │  2. AUCUNE TRANSACTION N'EST DISPONIBLE ICI. Si une insertion échoue     │
 * │     malgré la pré-validation — panne de base, colonne trop courte — le   │
 * │     lot est déclaré PARTIEL, avec le nombre réellement écrit. On ne      │
 * │     prétend jamais une atomicité qu'on n'a pas.                          │
 * │                                                                          │
 * │  3. `massifs_enregistrer_releve_reussi()` N'EST APPELÉE QUE SI LE LOT    │
 * │     EST INTÉGRALEMENT ÉCRIT. Un relevé déclaré réussi sur un lot         │
 * │     incomplet ferait mentir la fraîcheur, ce que le §4.5 interdit.       │
 * │                                                                          │
 * │  4. CHAQUE PROJECTION EST OBSERVABLE. `massifs_projection_prefecture`    │
 * │     porte les compteurs à chaque passage, succès compris, en plus du     │
 * │     journal. Le silence sur un échec d'écriture est le défaut d'origine. │
 * └──────────────────────────────────────────────────────────────────────────┘
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
			self::conclure( self::rejet( '', 0, 'instantané non tabulaire' ) );

			return;
		}

		$jour = isset( $instantane['date_validite'] ) && is_string( $instantane['date_validite'] )
			? trim( $instantane['date_validite'] )
			: '';

		if ( ! Horloge::jour_est_valide( $jour ) ) {
			self::conclure( self::rejet( '', 0, 'date de validité absente ou mal formée' ) );

			return;
		}

		if ( ! isset( $instantane['massifs'] ) || ! is_array( $instantane['massifs'] ) || array() === $instantane['massifs'] ) {
			self::conclure( self::rejet( $jour, 0, 'aucun massif dans l\'instantané' ) );

			return;
		}

		$recus = count( $instantane['massifs'] );

		// L'IDENTITÉ APPARTIENT AU RÉFÉRENTIEL. Sans sa table de correspondance,
		// nous ne savons pas sous quelle clé ranger un statut — et le ranger sous
		// l'identifiant de la source produirait des lignes que personne ne lira
		// jamais : un statut perdu qui se croit enregistré. On refuse le lot, et
		// on le dit.
		if ( ! function_exists( 'massifs_code_depuis_source' ) ) {
			self::conclure(
				self::rejet( $jour, $recus, 'le référentiel n\'expose pas massifs_code_depuis_source() : aucune identité de rangement disponible' )
			);

			return;
		}

		$lot = self::preparer( $instantane['massifs'], $jour, self::publie_le( $instantane ) );

		if ( '' !== $lot['motif'] ) {
			self::conclure( self::rejet( $jour, $recus, $lot['motif'], $lot['ignores'] ) );

			return;
		}

		$refus_domaine = self::pre_valider( $lot['statuts'] );

		if ( '' !== $refus_domaine ) {
			self::conclure( self::rejet( $jour, $recus, $refus_domaine, $lot['ignores'] ) );

			return;
		}

		$attendus = count( $lot['statuts'] );
		$ecriture = massifs_enregistrer_statuts( $lot['statuts'] );
		$ecrits   = (int) $ecriture['enregistres'];
		$refuses  = (int) $ecriture['refuses'];

		$bilan = array(
			'resultat'             => $ecrits === $attendus && 0 === $refuses ? 'complet' : 'partiel',
			'jour'                 => $jour,
			'recus'                => $recus,
			'resolus'              => $attendus,
			'ecrits'               => $ecrits,
			'refuses'              => $refuses,
			'ignores'              => count( $lot['ignores'] ),
			'identifiants_ignores' => $lot['ignores'],
			'motif'                => $ecrits === $attendus && 0 === $refuses
				? ''
				: sprintf( 'lot partiel : %d ligne(s) écrite(s) sur %d, %d refusée(s) — la base a refusé une écriture que la pré-validation avait acceptée', $ecrits, $attendus, $refuses ),
		);

		// Le relevé n'est déclaré réussi qu'ici, sur un lot INTÉGRALEMENT écrit.
		// Le déclarer sur un lot partiel ferait mentir la fraîcheur (§4.5).
		if ( 'complet' === $bilan['resultat'] ) {
			massifs_enregistrer_releve_reussi( self::SOURCE );
		}

		self::conclure( $bilan );
	}

	/**
	 * Construit le lot enregistrable, ou son motif de rejet.
	 *
	 * AUCUNE ÉCRITURE ICI. Toutes les causes de refus connues d'avance sont
	 * éprouvées sur le lot entier avant qu'une seule ligne ne parte en base.
	 *
	 * @param array<string|int, mixed> $massifs   Entrées de l'instantané.
	 * @param string                   $jour      Jour de validité.
	 * @param string|null              $publie_le Instant de publication de la source.
	 *
	 * @return array{statuts: list<array<string, mixed>>, ignores: list<string>, motif: string}
	 */
	private static function preparer( array $massifs, string $jour, ?string $publie_le ): array {
		$legende            = Legende::chargee();
		$niveaux_admis      = $legende->niveaux_source_autorises();
		$procedures_admises = $legende->procedures_source_autorisees();

		$statuts = array();
		$ignores = array();

		foreach ( $massifs as $identifiant_brut => $entree ) {
			$identifiant = trim( (string) $identifiant_brut );

			if ( ! is_array( $entree ) ) {
				return self::lot_rejete( 'entrée non tabulaire pour l\'identifiant ' . $identifiant );
			}

			// `is_int` strict : une chaîne numérique ou un flottant est un changement
			// de type de la source, donc un signal, jamais une conversion silencieuse.
			if ( ! isset( $entree['niveau_source'] ) || ! is_int( $entree['niveau_source'] ) ) {
				return self::lot_rejete( 'niveau_source absent ou non entier pour l\'identifiant ' . $identifiant );
			}

			// La liste blanche est éprouvée AVANT la résolution du code : un `level`
			// inconnu porté par un identifiant surnuméraire reste un changement de
			// forme de la source, et doit faire échouer le lot bruyamment.
			if ( ! in_array( $entree['niveau_source'], $niveaux_admis, true ) ) {
				return self::lot_rejete(
					sprintf( 'niveau_source %d hors liste blanche pour l\'identifiant %s', $entree['niveau_source'], $identifiant )
				);
			}

			$procedure = null;

			if ( isset( $entree['procedure_source'] ) ) {
				if ( ! is_int( $entree['procedure_source'] ) || ! in_array( $entree['procedure_source'], $procedures_admises, true ) ) {
					return self::lot_rejete( 'procedure_source invalide ou hors liste blanche pour l\'identifiant ' . $identifiant );
				}

				$procedure = $entree['procedure_source'];
			}

			$code = self::code_massif( $identifiant );

			// IDENTIFIANT SANS CORRESPONDANCE PUBLIÉE. La source émet 27 identifiants
			// là où le référentiel n'en nomme que 25 : les deux surnuméraires sont
			// ATTENDUS à chaque récupération. Faire échouer le lot pour eux
			// reviendrait à ne jamais rien écrire, donc à afficher « information non
			// disponible » toute la saison — un défaut pire que celui qu'on évite.
			// La ligne est donc écartée, comptée et déclarée ; elle n'est JAMAIS
			// rangée sous l'identifiant de la source.
			if ( null === $code ) {
				$ignores[] = $identifiant;
				continue;
			}

			// Un code non conforme rendu par le référentiel n'est pas un événement
			// attendu, c'est un défaut : le lot entier échoue.
			if ( ! Statuts::code_est_valide( $code ) ) {
				return self::lot_rejete(
					sprintf( 'le référentiel a rendu un code malformé (%s) pour l\'identifiant %s', $code, $identifiant )
				);
			}

			$statut = array(
				'massif_code'        => $code,
				'jour_validite'      => $jour,
				'niveau_source_brut' => $entree['niveau_source'],
				'source'             => SourceStatut::RecuperationOfficielle->value,
			);

			if ( null !== $procedure ) {
				$statut['procedure_source'] = $procedure;
			}

			if ( null !== $publie_le ) {
				$statut['publie_prefecture_le'] = $publie_le;
			}

			$statuts[] = $statut;
		}

		// Correspondance présente mais qui ne résout rien : la table est vide ou
		// désaccordée de la source. Écrire zéro ligne en silence serait exactement
		// le défaut d'origine.
		if ( array() === $statuts ) {
			return self::lot_rejete( 'aucun identifiant de la source ne correspond au référentiel' );
		}

		return array(
			'statuts' => $statuts,
			'ignores' => $ignores,
			'motif'   => '',
		);
	}

	/**
	 * Éprouve chaque ligne du lot dans les règles d'écriture réelles du domaine.
	 *
	 * On ne réimplémente PAS ces règles ici : `Statuts::erreurs_de()` est le même
	 * code que celui qui décidera d'accepter la ligne. Une pré-validation qui
	 * divergerait de l'écriture ne vaudrait rien.
	 *
	 * @param list<array<string, mixed>> $statuts Lot candidat.
	 *
	 * @return string Motif de rejet, chaîne vide si le lot entier est écrivable.
	 */
	private static function pre_valider( array $statuts ): string {
		$service = Statuts::service();

		foreach ( $statuts as $statut ) {
			$erreurs = $service->erreurs_de( $statut );

			if ( array() !== $erreurs ) {
				return sprintf(
					'lot refusé avant toute écriture — %s : %s',
					(string) $statut['massif_code'],
					implode( ', ', $erreurs )
				);
			}
		}

		return '';
	}

	/**
	 * Code de massif correspondant à un identifiant de la source.
	 *
	 * SEUL point de correspondance entre l'identité publiée par la source et la
	 * nôtre, et il est délégué : L'IDENTITÉ APPARTIENT AU RÉFÉRENTIEL. Le domaine
	 * `statuts` ne valide toujours pas l'existence d'un massif — `massif_code`
	 * reste une chaîne opaque validée sur sa forme — mais il ne fabrique plus
	 * l'identité lui-même.
	 *
	 * IL N'EXISTE AUCUN REPLI SUR L'IDENTIFIANT DE LA SOURCE. Ranger un statut
	 * sous une clé que le référentiel ne connaît pas produirait une ligne que
	 * personne ne lira : un statut perdu qui se croit enregistré.
	 *
	 * @param string $identifiant Identifiant émis par la source.
	 *
	 * @return string|null `null` si le référentiel ne publie aucune correspondance pour cet identifiant.
	 */
	private static function code_massif( string $identifiant ): ?string {
		// Garde `function_exists` comme partout ailleurs : le référentiel est un
		// module sœur qui peut être absent de l'arbre. Son absence complète est
		// traitée en amont, sur le lot entier.
		if ( ! function_exists( 'massifs_code_depuis_source' ) ) {
			return null;
		}

		$code = massifs_code_depuis_source( $identifiant );

		if ( ! is_string( $code ) ) {
			return null;
		}

		// Une chaîne vide n'est pas une absence de correspondance, c'est une
		// correspondance cassée : elle échouera à la validation de forme et fera
		// échouer le lot, ce qui est le bon comportement.
		return Statuts::normaliser_code( $code );
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
	 * Lot rejeté avant écriture.
	 *
	 * @param string $motif Motif du rejet, en clair.
	 *
	 * @return array{statuts: list<array<string, mixed>>, ignores: list<string>, motif: string}
	 */
	private static function lot_rejete( string $motif ): array {
		return array(
			'statuts' => array(),
			'ignores' => array(),
			'motif'   => $motif,
		);
	}

	/**
	 * Bilan d'une projection refusée : aucune ligne écrite.
	 *
	 * @param string       $jour    Jour de validité, chaîne vide s'il n'a pas pu être lu.
	 * @param int          $recus   Nombre d'entrées reçues.
	 * @param string       $motif   Motif du rejet, en clair.
	 * @param list<string> $ignores Identifiants sans correspondance rencontrés avant le rejet.
	 *
	 * @return array<string, mixed>
	 */
	private static function rejet( string $jour, int $recus, string $motif, array $ignores = array() ): array {
		return array(
			'resultat'             => 'rejete',
			'jour'                 => $jour,
			'recus'                => $recus,
			'resolus'              => 0,
			'ecrits'               => 0,
			'refuses'              => 0,
			'ignores'              => count( $ignores ),
			'identifiants_ignores' => $ignores,
			'motif'                => $motif,
		);
	}

	/**
	 * Clôt la projection : journal, puis action portant les compteurs.
	 *
	 * L'ACTION EST ÉMISE À CHAQUE PASSAGE, succès compris. C'est ce qui rend la
	 * projection observable autrement qu'en fouillant `error_log` : une extension
	 * de supervision, un écran d'administration ou une alerte peuvent s'y brancher
	 * sans que nous ayons à décider ici de la forme de la remontée.
	 *
	 * @param array<string, mixed> $bilan Bilan de la projection.
	 */
	private static function conclure( array $bilan ): void {
		if ( 'complet' !== $bilan['resultat'] ) {
			self::journaliser(
				sprintf(
					'projection préfecture %s (%s) — %s',
					(string) $bilan['resultat'],
					'' === $bilan['jour'] ? 'jour illisible' : (string) $bilan['jour'],
					(string) $bilan['motif']
				)
			);
		}

		// Un identifiant écarté est déclaré à chaque projection, y compris quand
		// tout le reste s'est bien passé : c'est la seule façon de voir qu'un
		// nouvel identifiant est apparu dans la source sans nom au référentiel.
		if ( $bilan['ignores'] > 0 ) {
			self::journaliser(
				sprintf(
					'projection préfecture %s — %d identifiant(s) sans correspondance au référentiel, écarté(s) : %s',
					'' === $bilan['jour'] ? 'jour illisible' : (string) $bilan['jour'],
					(int) $bilan['ignores'],
					implode( ', ', $bilan['identifiants_ignores'] )
				)
			);
		}

		/**
		 * Une projection d'instantané préfectoral vient de se terminer.
		 *
		 * @param array<string, mixed> $bilan `resultat` (`complet`|`partiel`|`rejete`),
		 *                                    `jour`, `recus`, `resolus`, `ecrits`,
		 *                                    `refuses`, `ignores`, `identifiants_ignores`,
		 *                                    `motif`.
		 */
		do_action( 'massifs_projection_prefecture', $bilan );
	}

	/**
	 * Journalise un incident de projection.
	 *
	 * Un refus ne doit JAMAIS être silencieux : le site continuera d'annoncer
	 * « information non disponible », ce qui est honnête mais doit être vu par un
	 * humain le jour même.
	 *
	 * @param string $message Message d'exploitation, en clair.
	 */
	private static function journaliser( string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Un incident de projection est un événement d'exploitation, il ne doit pas être silencieux.
		error_log( 'MASSIFS : ' . $message . '.' );
	}
}
