<?php
/**
 * Fonctions publiques du domaine « statuts ».
 *
 * Seules ces fonctions sont publiques : aucun consommateur n'instancie ni
 * n'appelle une classe `Massifs\`. Toutes retournent des tableaux associatifs —
 * jamais des objets — pour qu'aucun consommateur ne se couple à une classe et
 * qu'une future route REST sérialise sans adaptateur.
 *
 * AUCUNE FONCTION DE LECTURE NE RETOURNE `null` NI `false`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  CLAUSE ABSOLUE                                                          │
 * │                                                                          │
 * │  L'API de lecture est indexée exclusivement par date. Aucune fonction     │
 * │  « dernier statut connu » sans argument de date ne doit exister.          │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * C'est la traduction technique de la règle du §4.2 du brief, « ne jamais
 * présenter un statut périmé comme courant ». Tant qu'une fonction sans date
 * existe, quelqu'un finira par l'appeler. Le `$jour` optionnel des fonctions
 * ci-dessous est immédiatement résolu en date explicite, `Depot` lie
 * `jour_validite = %s`, et aucune méthode « dernier instantané » n'existe nulle
 * part dans le domaine.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Fraicheur\Horloge;
use Massifs\Domain\Statuts\Legende;
use Massifs\Domain\Statuts\ResultatStatut;
use Massifs\Domain\Statuts\Statuts;

if ( ! defined( 'MASSIFS_STATUT_HORIZON_JOURS' ) ) {
	define( 'MASSIFS_STATUT_HORIZON_JOURS', Statuts::HORIZON_JOURS );
}

if ( ! function_exists( 'massifs_statuts_du_jour' ) ) {
	/**
	 * Statut de chaque massif pour un jour donné.
	 *
	 * Retourne exactement une entrée par code fourni, dans l'ordre de
	 * fourniture, indexée par code — y compris pour un code inconnu du
	 * référentiel ou malformé, qui reçoit une entrée `indisponible`. Un code
	 * malformé n'est jamais envoyé au SQL.
	 *
	 * `jour_validite` de chaque entrée est TOUJOURS le jour demandé, jamais
	 * celui d'une ligne d'un autre jour.
	 *
	 * @param array<int|string, mixed> $codes_massifs Codes de massif.
	 * @param string|null              $jour          Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé — une coercition silencieuse vers aujourd'hui masquerait un bug du §4.2.
	 */
	function massifs_statuts_du_jour( array $codes_massifs, ?string $jour = null ): array {
		$jour_demande = Horloge::jour_demande( $jour );

		if ( array() === $codes_massifs ) {
			return array();
		}

		$demandes = array();
		$valides  = array();

		foreach ( $codes_massifs as $code_brut ) {
			if ( ! is_scalar( $code_brut ) ) {
				massifs_statuts_signaler_code_invalide( 'valeur non scalaire' );
				continue;
			}

			$code = Statuts::normaliser_code( (string) $code_brut );

			if ( array_key_exists( $code, $demandes ) ) {
				continue;
			}

			$valide = Statuts::code_est_valide( $code );

			if ( $valide ) {
				$valides[] = $code;
			} else {
				massifs_statuts_signaler_code_invalide( $code );
			}

			$demandes[ $code ] = $valide;
		}

		$resultats = array();

		$resolus = array() === $valides
			? array()
			: Statuts::service()->du_jour( $valides, $jour_demande );

		foreach ( $demandes as $code => $valide ) {
			$code = (string) $code;

			$resultats[ $code ] = isset( $resolus[ $code ] )
				? $resolus[ $code ]->en_tableau()
				: ResultatStatut::indisponible( $code, $jour_demande )->en_tableau();
		}

		return $resultats;
	}
}

if ( ! function_exists( 'massifs_statuts_signaler_code_invalide' ) ) {
	/**
	 * Signale un code de massif malformé aux développeurs, en debug seulement.
	 *
	 * @internal Détail d'implémentation de `massifs_statuts_du_jour()`.
	 *
	 * @param string $code Code fautif.
	 */
	function massifs_statuts_signaler_code_invalide( string $code ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		_doing_it_wrong(
			'massifs_statuts_du_jour',
			'Code de massif malformé : ' . $code . '. Forme attendue : /^[a-z0-9_-]{1,64}$/.',
			MASSIFS_CORE_VERSION
		);
	}
}

if ( ! function_exists( 'massifs_statut_du_jour' ) ) {
	/**
	 * Statut d'un massif pour un jour donné.
	 *
	 * @param string      $code_massif Code du massif.
	 * @param string|null $jour        Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé.
	 */
	function massifs_statut_du_jour( string $code_massif, ?string $jour = null ): array {
		$entrees = massifs_statuts_du_jour( array( $code_massif ), $jour );
		$entree  = reset( $entrees );

		if ( is_array( $entree ) ) {
			return $entree;
		}

		return ResultatStatut::indisponible(
			Statuts::normaliser_code( $code_massif ),
			Horloge::jour_demande( $jour )
		)->en_tableau();
	}
}

if ( ! function_exists( 'massifs_synthese_du_jour' ) ) {
	/**
	 * Synthèse du jour pour un ensemble de massifs.
	 *
	 * Existe pour que le consommateur ne recalcule jamais la sémantique
	 * « accès autorisé = niveau le moins sévère » : elle appartient au domaine.
	 *
	 * `par_niveau` porte TOUTES les clés de la légende courante, à `0` si
	 * aucun massif ne les porte.
	 *
	 * @param array<int|string, mixed> $codes_massifs Codes de massif.
	 * @param string|null              $jour          Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé.
	 */
	function massifs_synthese_du_jour( array $codes_massifs, ?string $jour = null ): array {
		$jour_demande = Horloge::jour_demande( $jour );
		$entrees      = massifs_statuts_du_jour( $codes_massifs, $jour_demande );
		$legende      = Legende::chargee();

		$par_niveau   = array_fill_keys( $legende->cles(), 0 );
		$total        = count( $entrees );
		$disponibles  = 0;
		$etats        = array();
		$moins_severe = null;
		$plus_severe  = null;

		foreach ( $entrees as $entree ) {
			$etat           = (string) $entree['etat'];
			$etats[ $etat ] = true;

			if ( 'disponible' !== $etat || ! is_array( $entree['niveau'] ) ) {
				continue;
			}

			++$disponibles;
			$cle      = (string) $entree['niveau']['cle'];
			$severite = (int) $entree['niveau']['severite'];

			if ( array_key_exists( $cle, $par_niveau ) ) {
				++$par_niveau[ $cle ];
			}

			if ( null === $moins_severe || $severite < $moins_severe['severite'] ) {
				$moins_severe = array(
					'cle'      => $cle,
					'severite' => $severite,
				);
			}

			if ( null === $plus_severe || $severite > $plus_severe['severite'] ) {
				$plus_severe = array(
					'cle'      => $cle,
					'severite' => $severite,
				);
			}
		}

		$sans_donnee = $total - $disponibles;

		if ( $disponibles > 0 ) {
			$etat_global = 'disponible';
		} elseif ( isset( $etats['hors_saison'] ) ) {
			$etat_global = 'hors_saison';
		} elseif ( isset( $etats['non_encore_publie'] ) ) {
			$etat_global = 'non_encore_publie';
		} else {
			$etat_global = 'indisponible';
		}

		return array(
			'jour_validite'          => $jour_demande,
			'etat_global'            => $etat_global,
			'partiel'                => $disponibles > 0 && $sans_donnee > 0,
			'total'                  => $total,
			'disponibles'            => $disponibles,
			'sans_donnee'            => $sans_donnee,
			'par_niveau'             => $par_niveau,
			'niveau_le_moins_severe' => null === $moins_severe ? null : $moins_severe['cle'],
			'niveau_le_plus_severe'  => null === $plus_severe ? null : $plus_severe['cle'],
		);
	}
}

if ( ! function_exists( 'massifs_legende' ) ) {
	/**
	 * Légende courante : sémantique des niveaux et états hors niveau.
	 *
	 * Aucun filtre n'altère cette valeur : un filtre laisserait fabriquer une
	 * légende non officielle. Les pigments ne traversent pas cette frontière —
	 * seuls des NOMS de jetons CSS sont exposés.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_legende(): array {
		return Legende::chargee()->en_tableau();
	}
}

if ( ! function_exists( 'massifs_legende_est_confirmee' ) ) {
	/**
	 * La légende reproduit-elle des valeurs officielles vérifiées ?
	 *
	 * Tant que c'est `false`, le consommateur affiche « Légende en cours de
	 * vérification » et ne compte pas sur `consigne`, qui est vide.
	 */
	function massifs_legende_est_confirmee(): bool {
		return Legende::chargee()->est_confirmee();
	}
}

if ( ! function_exists( 'massifs_niveaux_source_autorises' ) ) {
	/**
	 * Liste blanche des `level` bruts acceptables en entrée.
	 *
	 * Exposée pour la couche de validation sémantique de l'ingestion, qui la
	 * détecte par `function_exists`. La liste vit dans notre configuration
	 * versionnée et JAMAIS en constante dans le code de l'ingestion : figer une
	 * énumération dans le code reviendrait à graver une valeur que seule la
	 * source peut établir.
	 *
	 * @return list<int>
	 */
	function massifs_niveaux_source_autorises(): array {
		return Legende::chargee()->niveaux_source_autorises();
	}
}

if ( ! function_exists( 'massifs_procedures_source_autorisees' ) ) {
	/**
	 * Liste blanche des valeurs de `procedure` acceptables en entrée.
	 *
	 * Même motif que `massifs_niveaux_source_autorises()`. La sémantique de
	 * `procedure` n'est pas publiée par la source : la valeur est validée et
	 * persistée, jamais interprétée ni exposée.
	 *
	 * @return list<int>
	 */
	function massifs_procedures_source_autorisees(): array {
		return Legende::chargee()->procedures_source_autorisees();
	}
}

if ( ! function_exists( 'massifs_attribution_statuts' ) ) {
	/**
	 * Mention de source et liens officiels à afficher avec la donnée.
	 *
	 * Le consommateur ne rédige JAMAIS cette phrase ni ces adresses à la main :
	 * elles sont imposées par le §9 du brief et par le relevé de la source.
	 *
	 * Relaie le connecteur d'ingestion s'il est présent, en validant la forme
	 * reçue ; sinon retombe sur les valeurs imposées. Une valeur relayée
	 * inexploitable ne remplace jamais la valeur imposée.
	 *
	 * Le bulletin est un MODÈLE d'adresse portant le jeton `{AAAAMMJJ}`, à
	 * substituer par le consommateur. Il est LIÉ, jamais récupéré ni re-servi.
	 *
	 * @return array{texte: string, carte_officielle_url: string, bulletin_url_modele: string}
	 */
	function massifs_attribution_statuts(): array {
		$impose = array(
			'texte'                => 'D\'après les publications de la préfecture des Bouches-du-Rhône',
			'carte_officielle_url' => 'https://www.risque-prevention-incendie.fr/13',
			'bulletin_url_modele'  => 'https://www.risque-prevention-incendie.fr/static/13/import_data/{AAAAMMJJ}.pdf',
		);

		$connecteur = 'Massifs\\Ingest\\Prefecture\\Connector';

		if ( ! class_exists( $connecteur ) || ! method_exists( $connecteur, 'attribution' ) ) {
			return $impose;
		}

		$relaye = $connecteur::attribution();

		if ( ! is_array( $relaye ) ) {
			return $impose;
		}

		// Le connecteur nomme son jeton `{date}` là où le contrat d'interface
		// nomme `{AAAAMMJJ}` ; les deux désignent le même `Ymd`. La substitution
		// est faite ici, une fois, plutôt que d'imposer au consommateur de
		// connaître deux jetons.
		$modele = str_replace( '{date}', '{AAAAMMJJ}', massifs_attribution_texte( $relaye, 'url_bulletin' ) );

		return array(
			'texte'                => massifs_attribution_valeur(
				massifs_attribution_texte( $relaye, 'texte' ),
				$impose['texte'],
				''
			),
			'carte_officielle_url' => massifs_attribution_valeur(
				massifs_attribution_texte( $relaye, 'url_carte' ),
				$impose['carte_officielle_url'],
				'https://'
			),
			'bulletin_url_modele'  => massifs_attribution_valeur(
				str_contains( $modele, '{AAAAMMJJ}' ) ? $modele : '',
				$impose['bulletin_url_modele'],
				'https://'
			),
		);
	}
}

if ( ! function_exists( 'massifs_attribution_texte' ) ) {
	/**
	 * Lit une valeur de chaîne d'une attribution relayée, sans confiance de type.
	 *
	 * @internal Détail d'implémentation de `massifs_attribution_statuts()`.
	 *
	 * @param array<string, mixed> $attribution Attribution relayée.
	 * @param string               $cle         Clé attendue.
	 */
	function massifs_attribution_texte( array $attribution, string $cle ): string {
		return isset( $attribution[ $cle ] ) && is_string( $attribution[ $cle ] )
			? trim( $attribution[ $cle ] )
			: '';
	}
}

if ( ! function_exists( 'massifs_attribution_valeur' ) ) {
	/**
	 * Retient une valeur relayée si elle est exploitable, sinon la valeur imposée.
	 *
	 * @internal Détail d'implémentation de `massifs_attribution_statuts()`.
	 *
	 * @param string $relayee Valeur relayée par le connecteur.
	 * @param string $imposee Valeur imposée par le brief et le relevé de source.
	 * @param string $prefixe Préfixe exigé, chaîne vide si aucun.
	 */
	function massifs_attribution_valeur( string $relayee, string $imposee, string $prefixe ): string {
		if ( '' === $relayee ) {
			return $imposee;
		}

		if ( '' !== $prefixe && ! str_starts_with( $relayee, $prefixe ) ) {
			return $imposee;
		}

		return $relayee;
	}
}

if ( ! function_exists( 'massifs_enregistrer_statut' ) ) {
	/**
	 * Enregistre un statut.
	 *
	 * CETTE FONCTION NE VÉRIFIE AUCUNE CAPABILITY. L'authentification et
	 * l'autorisation appartiennent entièrement à l'appelant : route REST avec un
	 * vrai `permission_callback`, ou écran d'administration avec
	 * `current_user_can()` ET vérification du nonce.
	 *
	 * L'écriture est en insertion pure : une correction est une ligne de plus,
	 * jamais un écrasement. `enregistre_le` est posé par le domaine.
	 *
	 * Aucune exception n'est levée pour une donnée invalide : une charge utile
	 * aberrante est refusée et laisse la valeur précédente en place.
	 *
	 * @param array<string, mixed> $statut Clés attendues : `massif_code`, `jour_validite`,
	 *                                     `source`, `auteur_id`, `publie_prefecture_le`,
	 *                                     puis soit `niveau_source_brut` et
	 *                                     `procedure_source` (récupération officielle),
	 *                                     soit `niveau_cle` et `zapef_cle` (saisie
	 *                                     manuelle). Toute autre clé est ignorée.
	 *
	 * @return array{enregistre: bool, id: int|null, erreurs: list<string>}
	 */
	function massifs_enregistrer_statut( array $statut ): array {
		return Statuts::service()->enregistrer( $statut );
	}
}

if ( ! function_exists( 'massifs_enregistrer_statuts' ) ) {
	/**
	 * Enregistre un lot de statuts.
	 *
	 * CETTE FONCTION NE VÉRIFIE AUCUNE CAPABILITY : voir
	 * `massifs_enregistrer_statut()`.
	 *
	 * Aucune transaction : chaque ligne est indépendamment valide, et une
	 * transaction masquerait quelles lignes ont échoué.
	 *
	 * @param array<int, mixed> $statuts Statuts candidats.
	 *
	 * @return array{enregistres: int, refuses: int, resultats: list<array{enregistre: bool, id: int|null, erreurs: list<string>}>}
	 */
	function massifs_enregistrer_statuts( array $statuts ): array {
		$service     = Statuts::service();
		$resultats   = array();
		$enregistres = 0;
		$refuses     = 0;
		$publies     = array();

		foreach ( $statuts as $statut ) {
			if ( ! is_array( $statut ) ) {
				++$refuses;
				$resultats[] = array(
					'enregistre' => false,
					'id'         => null,
					'erreurs'    => array( 'massif_code_invalide' ),
				);
				continue;
			}

			$resultat    = $service->enregistrer( $statut );
			$resultats[] = $resultat;

			if ( true !== $resultat['enregistre'] ) {
				++$refuses;
				continue;
			}

			++$enregistres;

			$code = Statuts::normaliser_code(
				isset( $statut['massif_code'] ) && is_scalar( $statut['massif_code'] ) ? (string) $statut['massif_code'] : ''
			);
			$jour = trim(
				isset( $statut['jour_validite'] ) && is_scalar( $statut['jour_validite'] ) ? (string) $statut['jour_validite'] : ''
			);

			$publies[ $jour ][ $code ] = true;
		}

		foreach ( $publies as $jour => $codes ) {
			/**
			 * Des statuts viennent d'être publiés pour un jour de validité.
			 *
			 * Point d'accroche de l'invalidation du cache de page (§10 du brief).
			 *
			 * @param list<string> $codes Codes des massifs publiés.
			 * @param string       $jour  Jour de validité concerné.
			 */
			do_action( 'massifs_statuts_publies', array_keys( $codes ), (string) $jour );
		}

		return array(
			'enregistres' => $enregistres,
			'refuses'     => $refuses,
			'resultats'   => $resultats,
		);
	}
}
