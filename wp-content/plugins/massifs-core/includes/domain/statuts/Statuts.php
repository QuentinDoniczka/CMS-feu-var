<?php
/**
 * Service de résolution et d'écriture des statuts.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use Massifs\Domain\Fraicheur\Horloge;
use Massifs\Domain\Fraicheur\Saison;

/**
 * Le seul producteur de `ResultatStatut`.
 *
 * Ce service porte la règle absolue du §4.2 : un statut n'est « disponible »
 * que pour SON jour de validité, et l'absence de donnée n'invente jamais de
 * statut.
 *
 * Il ne consulte JAMAIS la fraîcheur des relevés : le seuil de 24 h du §4.5 est
 * une règle disjointe, qui déclenche une bannière et ne masque jamais une
 * donnée valide. Les deux règles ne se rencontrent nulle part dans le code.
 */
final class Statuts {

	/**
	 * Horizon d'écriture, en jours à partir d'aujourd'hui.
	 *
	 * La préfecture publie le statut du lendemain : au-delà de J+2, la donnée
	 * est aberrante et refusée.
	 */
	public const HORIZON_JOURS = 2;

	/**
	 * Profondeur maximale d'écriture dans le passé, en jours.
	 */
	public const RETROACTIVITE_JOURS = 370;

	/**
	 * Forme admise d'un code de massif.
	 *
	 * Le domaine `statuts` ne connaît, n'appelle et ne valide jamais le
	 * référentiel des massifs : `massif_code` est une chaîne opaque validée sur
	 * sa FORME, jamais sur son existence. Dépendance inversée assumée.
	 */
	private const MOTIF_CODE = '/^[a-z0-9_-]{1,64}$/';

	/**
	 * Construit le service.
	 *
	 * @param Depot   $depot   Accès à la table des statuts.
	 * @param Legende $legende Légende courante.
	 * @param Saison  $saison  Calendrier du dispositif.
	 */
	public function __construct(
		private readonly Depot $depot,
		private readonly Legende $legende,
		private readonly Saison $saison
	) {}

	/**
	 * Service prêt à l'emploi.
	 */
	public static function service(): self {
		$legende = Legende::chargee();

		return new self( new Depot(), $legende, Saison::depuis_bornes( $legende->bornes_saison() ) );
	}

	/**
	 * Normalise un code de massif.
	 *
	 * @param string $code Code brut.
	 */
	public static function normaliser_code( string $code ): string {
		return strtolower( trim( $code ) );
	}

	/**
	 * Le code a-t-il une forme admissible ?
	 *
	 * @param string $code Code normalisé.
	 */
	public static function code_est_valide( string $code ): bool {
		return 1 === preg_match( self::MOTIF_CODE, $code );
	}

	/**
	 * Résout le statut de chaque massif pour le jour demandé.
	 *
	 * @param list<string> $codes Codes normalisés et valides.
	 * @param string       $jour  Jour demandé `YYYY-MM-DD`.
	 *
	 * @return array<string, ResultatStatut>
	 */
	public function du_jour( array $codes, string $jour ): array {
		$lignes    = array() === $codes ? array() : $this->depot->selectionner_jour( $codes, $jour );
		$resultats = array();

		foreach ( $codes as $code ) {
			$resultats[ $code ] = isset( $lignes[ $code ] )
				? $this->depuis_ligne( $code, $jour, $lignes[ $code ] )
				: $this->sans_donnee( $code, $jour );
		}

		return $resultats;
	}

	/**
	 * Historique dérivé, socle de l'écran du §6.
	 *
	 * @param array<string, mixed> $criteres Filtres transmis au dépôt.
	 *
	 * @return list<EntreeHistorique>
	 */
	public function historique( array $criteres ): array {
		return EntreeHistorique::depuis_lignes( $this->depot->selectionner_historique( $criteres ) );
	}

	/**
	 * Valide, normalise puis insère un statut.
	 *
	 * CETTE MÉTHODE NE VÉRIFIE AUCUNE CAPABILITY. L'authentification et
	 * l'autorisation appartiennent à l'appelant.
	 *
	 * Aucune exception n'est levée pour une donnée invalide : une source externe
	 * qui envoie n'importe quoi ne doit pas tuer un cron. Une charge utile
	 * refusée laisse simplement la valeur précédente en place.
	 *
	 * @param array<string, mixed> $statut Statut candidat. Clés reconnues :
	 *                                     `massif_code`, `jour_validite`, `source`,
	 *                                     `auteur_id`, `publie_prefecture_le`, puis
	 *                                     soit `niveau_source_brut` (+ `procedure_source`)
	 *                                     pour une récupération officielle, soit
	 *                                     `niveau_cle` (+ `zapef_cle`) pour une saisie
	 *                                     manuelle. Toute autre clé est ignorée.
	 *
	 * @return array{enregistre: bool, id: int|null, erreurs: list<string>}
	 */
	public function enregistrer( array $statut ): array {
		$erreurs = array();

		$code = self::normaliser_code( $this->chaine( $statut, 'massif_code' ) );

		if ( ! self::code_est_valide( $code ) ) {
			$erreurs[] = 'massif_code_invalide';
		}

		$jour = trim( $this->chaine( $statut, 'jour_validite' ) );

		if ( ! Horloge::jour_est_valide( $jour ) ) {
			$erreurs[] = 'jour_validite_invalide';
		} else {
			$ecart = Horloge::ecart_jours( Horloge::jour_courant(), $jour );

			if ( $ecart > self::HORIZON_JOURS || $ecart < -self::RETROACTIVITE_JOURS ) {
				$erreurs[] = 'jour_validite_hors_horizon';
			}
		}

		$niveaux = $this->resoudre_niveaux( $statut );
		$erreurs = array_merge( $erreurs, $niveaux['erreurs'] );

		$source = SourceStatut::tryFrom( trim( $this->chaine( $statut, 'source' ) ) );

		if ( null === $source ) {
			$erreurs[] = 'source_invalide';
		}

		$auteur_id = isset( $statut['auteur_id'] ) && is_scalar( $statut['auteur_id'] )
			? absint( $statut['auteur_id'] )
			: 0;

		if ( SourceStatut::SaisieManuelle === $source && 0 === $auteur_id ) {
			$erreurs[] = 'auteur_requis';
		}

		if ( SourceStatut::RecuperationOfficielle === $source && $auteur_id > 0 ) {
			$erreurs[] = 'auteur_interdit';
		}

		$publie_prefecture_le = null;
		$publie_brut          = trim( $this->chaine( $statut, 'publie_prefecture_le' ) );

		if ( '' !== $publie_brut ) {
			try {
				$instant        = Horloge::instant_depuis_chaine( $publie_brut );
				$ecart_secondes = $instant->getTimestamp() - Horloge::maintenant()->getTimestamp();

				// Une publication annoncée loin dans le futur ou vieille de plus
				// d'un an est une donnée aberrante : on la refuse plutôt que de
				// la stocker.
				if ( $ecart_secondes > 2 * HOUR_IN_SECONDS || $ecart_secondes < -( self::RETROACTIVITE_JOURS * DAY_IN_SECONDS ) ) {
					$erreurs[] = 'publie_prefecture_le_invalide';
				} else {
					$publie_prefecture_le = $instant;
				}
			} catch ( InvalidArgumentException ) {
				$erreurs[] = 'publie_prefecture_le_invalide';
			}
		}

		if ( array() !== $erreurs ) {
			return array(
				'enregistre' => false,
				'id'         => null,
				'erreurs'    => array_values( array_unique( $erreurs ) ),
			);
		}

		if ( ! $source instanceof SourceStatut ) {
			// Inatteignable : la liste d'erreurs ci-dessus garantit déjà une source valide.
			return array(
				'enregistre' => false,
				'id'         => null,
				'erreurs'    => array( 'source_invalide' ),
			);
		}

		// `enregistre_le` est posé par le domaine, jamais par l'appelant.
		$enregistre_le = Horloge::maintenant();

		$id = $this->depot->inserer(
			$code,
			$jour,
			$niveaux['niveau_cle'],
			$niveaux['zapef_cle'],
			$niveaux['niveau_source_brut'],
			$niveaux['procedure_source'],
			$source->value,
			$auteur_id > 0 ? $auteur_id : null,
			null === $publie_prefecture_le ? null : Horloge::vers_mysql( $publie_prefecture_le ),
			Horloge::vers_mysql( $enregistre_le )
		);

		if ( null === $id ) {
			return array(
				'enregistre' => false,
				'id'         => null,
				'erreurs'    => array( 'echec_insertion' ),
			);
		}

		// `niveau_source_brut` et `procedure_source` ne voyagent PAS dans le hook :
		// il est écouté par de la journalisation et de l'invalidation de cache,
		// jamais par de l'affichage, et un entier de source n'a rien à y faire.
		$normalise = array(
			'massif_code'          => $code,
			'jour_validite'        => $jour,
			'niveau_cle'           => $niveaux['niveau_cle'],
			'zapef_cle'            => $niveaux['zapef_cle'],
			'source'               => $source->value,
			'auteur_id'            => $auteur_id > 0 ? $auteur_id : null,
			'publie_prefecture_le' => null === $publie_prefecture_le ? null : Horloge::vers_iso_utc( $publie_prefecture_le ),
			'enregistre_le'        => Horloge::vers_iso_utc( $enregistre_le ),
		);

		/**
		 * Un statut vient d'être enregistré.
		 *
		 * @param int                  $id        Identifiant de la ligne insérée.
		 * @param array<string, mixed> $normalise Statut normalisé tel qu'enregistré.
		 */
		do_action( 'massifs_statut_enregistre', $id, $normalise );

		return array(
			'enregistre' => true,
			'id'         => $id,
			'erreurs'    => array(),
		);
	}

	/**
	 * Résout les clés affichées et les valeurs brutes à persister.
	 *
	 * DEUX CHEMINS D'ÉCRITURE, UNE SEULE FORME EN LECTURE :
	 *
	 * - une récupération officielle fournit le `level` brut ; la table de
	 *   correspondance de la légende — SEUL point de traduction entier → clé —
	 *   en déduit `niveau_cle` et `zapef_cle`, et le brut est persisté tel quel ;
	 * - une saisie manuelle n'a pas de `level` : elle nomme directement les clés,
	 *   et les deux colonnes brutes restent `NULL`.
	 *
	 * Le `level` brut GAGNE quand il est fourni : c'est la vérité de la source, et
	 * laisser un appelant le contredire ouvrirait la porte à une ligne dont la
	 * clé affichée ne correspond pas à son propre brut.
	 *
	 * Toute valeur hors liste blanche est REFUSÉE, jamais stockée ni tronquée :
	 * une charge utile aberrante laisse la valeur précédente en place. La clé
	 * d'erreur `niveau_inconnu` couvre les trois cas — clé absente de la légende,
	 * `level` hors liste blanche, `procedure` hors liste blanche : le vocabulaire
	 * de clés d'erreur est figé par le contrat et n'est pas étendu ici.
	 *
	 * @param array<string, mixed> $statut Statut candidat.
	 *
	 * @return array{niveau_cle: string|null, zapef_cle: string|null, niveau_source_brut: int|null, procedure_source: int|null, erreurs: list<string>}
	 */
	private function resoudre_niveaux( array $statut ): array {
		$procedure = null;

		if ( isset( $statut['procedure_source'] ) ) {
			if ( ! is_int( $statut['procedure_source'] )
				|| ! in_array( $statut['procedure_source'], $this->legende->procedures_source_autorisees(), true ) ) {
				return $this->refus_niveaux();
			}

			$procedure = $statut['procedure_source'];
		}

		if ( isset( $statut['niveau_source_brut'] ) ) {
			if ( ! is_int( $statut['niveau_source_brut'] )
				|| ! in_array( $statut['niveau_source_brut'], $this->legende->niveaux_source_autorises(), true ) ) {
				return $this->refus_niveaux();
			}

			$projection = $this->legende->projeter_source( $statut['niveau_source_brut'] );

			if ( null === $projection ) {
				return $this->refus_niveaux();
			}

			return array(
				'niveau_cle'         => $projection['niveau_cle'],
				'zapef_cle'          => $projection['zapef_cle'],
				'niveau_source_brut' => $statut['niveau_source_brut'],
				'procedure_source'   => $procedure,
				'erreurs'            => array(),
			);
		}

		$erreurs = array();

		// Sans `level`, l'appelant DOIT nommer le niveau. Une saisie manuelle ne
		// peut pas produire « aucune donnée » : c'est un fait publié par la
		// source, pas une option de saisie.
		$niveau_cle = trim( $this->chaine( $statut, 'niveau_cle' ) );

		if ( ! $this->legende->existe( $niveau_cle ) ) {
			$erreurs[] = 'niveau_inconnu';
		}

		$zapef_cle = trim( $this->chaine( $statut, 'zapef_cle' ) );

		if ( '' !== $zapef_cle && ! $this->legende->zapef_existe( $zapef_cle ) ) {
			$erreurs[] = 'niveau_inconnu';
		}

		return array(
			'niveau_cle'         => '' === $niveau_cle ? null : $niveau_cle,
			'zapef_cle'          => '' === $zapef_cle ? null : $zapef_cle,
			'niveau_source_brut' => null,
			'procedure_source'   => $procedure,
			'erreurs'            => array_values( array_unique( $erreurs ) ),
		);
	}

	/**
	 * Refus de résolution : rien à écrire, une seule clé d'erreur.
	 *
	 * @return array{niveau_cle: null, zapef_cle: null, niveau_source_brut: null, procedure_source: null, erreurs: list<string>}
	 */
	private function refus_niveaux(): array {
		return array(
			'niveau_cle'         => null,
			'zapef_cle'          => null,
			'niveau_source_brut' => null,
			'procedure_source'   => null,
			'erreurs'            => array( 'niveau_inconnu' ),
		);
	}

	/**
	 * Construit le résultat depuis une ligne du dépôt.
	 *
	 * Une ligne illisible — niveau retiré de la légende, source inconnue — ne
	 * produit jamais une invention : elle retombe sur l'état sans donnée.
	 *
	 * @param string               $code  Code du massif.
	 * @param string               $jour  Jour demandé.
	 * @param array<string, mixed> $ligne Ligne du dépôt.
	 */
	private function depuis_ligne( string $code, string $jour, array $ligne ): ResultatStatut {
		$source = SourceStatut::tryFrom( (string) ( $ligne['source'] ?? '' ) );

		if ( null === $source ) {
			return $this->sans_donnee( $code, $jour );
		}

		$niveau_cle = isset( $ligne['niveau_cle'] ) && null !== $ligne['niveau_cle']
			? trim( (string) $ligne['niveau_cle'] )
			: '';

		// UNE LIGNE SANS NIVEAU EST UNE ABSENCE D'INFORMATION, PAS UN AUTORISÉ PAR
		// DÉFAUT. La source a publié qu'elle n'avait pas de statut pour ce massif
		// ce jour-là. On ne remonte SURTOUT PAS à une ligne antérieure du même
		// couple pour lui retrouver un niveau : ce serait présenter une donnée que
		// la source a explicitement retirée, ce que le §4.2 du brief interdit.
		if ( '' === $niveau_cle ) {
			return ResultatStatut::indisponible( $code, $jour );
		}

		$niveau = $this->legende->niveau( $niveau_cle );

		if ( null === $niveau ) {
			return $this->sans_donnee( $code, $jour );
		}

		$zapef_cle = isset( $ligne['zapef_cle'] ) && null !== $ligne['zapef_cle']
			? trim( (string) $ligne['zapef_cle'] )
			: '';

		return ResultatStatut::disponible(
			$code,
			$jour,
			(string) ( $ligne['jour_validite'] ?? '' ),
			$niveau,
			'' === $zapef_cle ? null : $this->legende->zapef_entree( $zapef_cle ),
			$source,
			isset( $ligne['auteur_id'] ) && null !== $ligne['auteur_id'] ? (int) $ligne['auteur_id'] : null,
			Horloge::stockage_vers_iso_utc( isset( $ligne['publie_prefecture_le'] ) ? (string) $ligne['publie_prefecture_le'] : null ),
			(string) Horloge::stockage_vers_iso_utc( (string) ( $ligne['enregistre_le'] ?? '' ) ),
			(int) ( $ligne['id'] ?? 0 )
		);
	}

	/**
	 * État à retenir quand aucune ligne n'existe pour ce couple.
	 *
	 * La donnée bat le calendrier : `hors_saison` n'est émis que si le
	 * calendrier dit inactif ET qu'aucune ligne valide n'existe. Si la
	 * préfecture prolonge le dispositif, le vrai statut est affiché plutôt
	 * qu'un mensonge.
	 *
	 * @param string $code Code du massif.
	 * @param string $jour Jour demandé.
	 */
	private function sans_donnee( string $code, string $jour ): ResultatStatut {
		if ( ! $this->saison->est_active( $jour ) ) {
			return ResultatStatut::hors_saison( $code, $jour );
		}

		if ( Horloge::comparer_jours( $jour, Horloge::jour_courant() ) > 0 ) {
			return ResultatStatut::non_encore_publie( $code, $jour );
		}

		return ResultatStatut::indisponible( $code, $jour );
	}

	/**
	 * Lit une valeur scalaire d'une charge utile, sans jamais faire confiance à son type.
	 *
	 * @param array<string, mixed> $donnees Charge utile.
	 * @param string               $cle     Clé attendue.
	 */
	private function chaine( array $donnees, string $cle ): string {
		return isset( $donnees[ $cle ] ) && is_scalar( $donnees[ $cle ] ) ? (string) $donnees[ $cle ] : '';
	}
}
