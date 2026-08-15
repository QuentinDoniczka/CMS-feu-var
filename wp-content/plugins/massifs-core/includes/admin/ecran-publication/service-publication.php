<?php
/**
 * Cœur d'écriture unique de la publication des statuts.
 *
 * LE HANDLER POST ET LE CALLBACK REST TRAVERSENT EXACTEMENT CE SERVICE. Une seule
 * validation, une seule garde de jour, un seul diff, un seul appel batch. Deux
 * chemins d'écriture avec deux validations, ce seraient deux endroits où oublier
 * une garde et une divergence garantie à la première évolution.
 *
 * CE SERVICE NE REDIRIGE PAS, N'ÉCHAPPE RIEN, N'ÉMET AUCUN OCTET : il retourne un
 * tableau de clés stables, dont la rédaction appartient à `messages.php`.
 *
 * IL N'ÉMET AUCUN HOOK. Ni `do_action`, ni `apply_filters`, ni filtre site-wide :
 * le seul signal de publication est `massifs_statuts_publies`, que le domaine
 * émet lui-même depuis `massifs_enregistrer_statuts()`.
 *
 * IL N'ENREGISTRE AUCUN RELEVÉ DE FRAÎCHEUR. `massifs_enregistrer_releve_reussi()`
 * mesure le relevé PRÉFECTORAL : l'appeler ici affirmerait que la préfecture a été
 * interrogée alors qu'elle ne l'a pas été.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_resultat_vide' ) ) {
	/**
	 * Forme complète du résultat d'une publication.
	 *
	 * Toutes les clés sont toujours présentes : un appelant n'écrit jamais
	 * `isset()` sur ce retour.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_resultat_vide(): array {
		return array(
			'publie'          => false,
			'jour_jeton'      => '',
			'jour_validite'   => '',
			'ecrits'          => array(),
			'inchanges'       => array(),
			'manquants'       => array(),
			'zapef_perdue'    => array(),
			'refuses'         => array(),
			'erreurs'         => array(),
			'empreinte_apres' => '',
			'publie_le'       => '',
		);
	}
}

if ( ! function_exists( 'massifs_publication_assainir_niveaux' ) ) {
	/**
	 * Assainit une table `massif_code => niveau_cle` reçue d'une requête.
	 *
	 * `sanitize_key()` sur les clés ET sur les valeurs. L'assainissement ne valide
	 * rien : une clé hors référentiel ou une valeur hors légende reste refusée plus
	 * loin, par la validation tout-ou-rien du service.
	 *
	 * @param mixed $brut Valeur brute reçue.
	 *
	 * @return array<string, string>
	 */
	function massifs_publication_assainir_niveaux( mixed $brut ): array {
		if ( ! is_array( $brut ) ) {
			return array();
		}

		$niveaux = array();

		foreach ( $brut as $code => $valeur ) {
			if ( ! is_scalar( $valeur ) ) {
				continue;
			}

			$code_propre = sanitize_key( (string) $code );

			if ( '' === $code_propre ) {
				continue;
			}

			$niveaux[ $code_propre ] = sanitize_key( (string) $valeur );
		}

		return $niveaux;
	}
}

if ( ! function_exists( 'massifs_publication_codes_actifs' ) ) {
	/**
	 * Codes des massifs actifs, dans l'ordre `tri` du référentiel.
	 *
	 * Les massifs retirés ne sont jamais publiables : le référentiel possède
	 * l'identité, et un massif qu'il ne porte plus n'a pas de statut à recevoir.
	 *
	 * @return list<string>
	 */
	function massifs_publication_codes_actifs(): array {
		if ( ! function_exists( 'massifs_referentiel' ) ) {
			return array();
		}

		$codes = array();

		foreach ( array_keys( massifs_referentiel() ) as $code ) {
			$codes[] = (string) $code;
		}

		return $codes;
	}
}

if ( ! function_exists( 'massifs_publication_cles_niveaux' ) ) {
	/**
	 * Clés de niveau admises, dans l'ordre de la légende.
	 *
	 * @return list<string>
	 */
	function massifs_publication_cles_niveaux(): array {
		if ( ! function_exists( 'massifs_legende' ) ) {
			return array();
		}

		$legende = massifs_legende();

		if ( ! isset( $legende['niveaux'] ) || ! is_array( $legende['niveaux'] ) ) {
			return array();
		}

		$cles = array();

		foreach ( $legende['niveaux'] as $niveau ) {
			if ( is_array( $niveau ) && isset( $niveau['cle'] ) && is_string( $niveau['cle'] ) ) {
				$cles[] = $niveau['cle'];
			}
		}

		return $cles;
	}
}

if ( ! function_exists( 'massifs_publication_etat_du_jour' ) ) {
	/**
	 * État enregistré de chaque massif actif, pour un jour donné.
	 *
	 * Lu par le domaine, indexé par date : aucune fonction « dernier statut connu »
	 * n'existe et aucune ligne d'un autre jour ne peut apparaître ici.
	 *
	 * @param string $jour Jour civil `YYYY-MM-DD`.
	 *
	 * @return array<string, array<string, mixed>> Vide si la lecture est impossible.
	 */
	function massifs_publication_etat_du_jour( string $jour ): array {
		$codes = massifs_publication_codes_actifs();

		if ( '' === $jour || array() === $codes || ! function_exists( 'massifs_statuts_du_jour' ) ) {
			return array();
		}

		try {
			$statuts = massifs_statuts_du_jour( $codes, $jour );
		} catch ( InvalidArgumentException $exception ) {
			massifs_publication_journaliser( 'service', 'jour_validite_invalide', $exception->getMessage() );

			return array();
		}

		$etat = array();

		foreach ( $codes as $code ) {
			$entree = isset( $statuts[ $code ] ) && is_array( $statuts[ $code ] ) ? $statuts[ $code ] : array();
			$niveau = isset( $entree['niveau'] ) && is_array( $entree['niveau'] ) ? $entree['niveau'] : array();
			$zapef  = isset( $entree['zapef'] ) && is_array( $entree['zapef'] ) ? $entree['zapef'] : array();

			$etat[ $code ] = array(
				'etat'           => isset( $entree['etat'] ) && is_string( $entree['etat'] ) ? $entree['etat'] : 'indisponible',
				'niveau_cle'     => isset( $niveau['cle'] ) ? (string) $niveau['cle'] : '',
				'niveau_libelle' => isset( $niveau['libelle'] ) ? (string) $niveau['libelle'] : '',
				'zapef_cle'      => isset( $zapef['cle'] ) ? (string) $zapef['cle'] : '',
				'source'         => isset( $entree['source'] ) && is_string( $entree['source'] ) ? $entree['source'] : '',
				'auteur_id'      => isset( $entree['auteur_id'] ) ? (int) $entree['auteur_id'] : 0,
				'enregistre_le'  => isset( $entree['enregistre_le'] ) && is_string( $entree['enregistre_le'] ) ? $entree['enregistre_le'] : '',
			);
		}

		return $etat;
	}
}

if ( ! function_exists( 'massifs_publication_empreinte' ) ) {
	/**
	 * Empreinte de l'état d'un jour, pour la concurrence optimiste.
	 *
	 * NE PAS VERROUILLER, DÉTECTER. Le modèle est en insertion pure et aucune ligne
	 * n'est perdue : le vrai danger n'est pas l'écrasement, c'est le SILENCE. Si A
	 * choisit « interdit » pour un massif que B vient de passer à « interdit », le
	 * diff de A conclut « inchangé » et A repart en croyant avoir publié ce qu'il
	 * voit. L'empreinte transforme cette divergence silencieuse en refus explicite,
	 * sans verrou, sans état à nettoyer, sans délai d'expiration à inventer.
	 *
	 * Effet de bord souhaitable : si le cron préfectoral écrit pendant que l'écran
	 * est ouvert, la publication est refusée — la préfecture vient de publier, le
	 * gestionnaire doit le voir avant d'écraser.
	 *
	 * @param array<string, array<string, mixed>> $etat État lu pour ce jour.
	 * @param string                              $jour Jour civil `YYYY-MM-DD`.
	 */
	function massifs_publication_empreinte( array $etat, string $jour ): string {
		$morceaux = array();

		foreach ( $etat as $code => $ligne ) {
			$morceaux[] = $code . ':' . (string) $ligne['etat'] . ':' . (string) $ligne['niveau_cle'];
		}

		return sha1( $jour . '|' . implode( ';', $morceaux ) );
	}
}

if ( ! function_exists( 'massifs_publication_bascule_de_minuit' ) ) {
	/**
	 * L'empreinte soumise décrit-elle l'autre jour du sélecteur ?
	 *
	 * Écran ouvert le 14 à 23 h 58 sur `demain` (le 15), soumis le 15 à 00 h 01 :
	 * le jeton résout désormais le 16, et l'empreinte soumise décrit le 15 —
	 * devenu « aujourd'hui ». Le refus est le bon comportement dans les deux cas,
	 * mais dire « le jour a changé » plutôt que « l'état a changé » est la seule
	 * phrase vraie. Aucune écriture n'a lieu de toute façon.
	 *
	 * @param string $jeton             Jeton soumis.
	 * @param string $empreinte_soumise Empreinte soumise.
	 */
	function massifs_publication_bascule_de_minuit( string $jeton, string $empreinte_soumise ): bool {
		$autre = 'aujourd_hui' === $jeton ? 'demain' : 'aujourd_hui';
		$jour  = massifs_publication_resoudre_jour( $autre );

		if ( '' === $jour ) {
			return false;
		}

		return $empreinte_soumise === massifs_publication_empreinte( massifs_publication_etat_du_jour( $jour ), $jour );
	}
}

if ( ! function_exists( 'massifs_publication_publier' ) ) {
	/**
	 * Publie un lot de niveaux pour un jour, ou refuse le lot entier.
	 *
	 * L'ORDRE DES GARDES EST CONTRACTUEL et ne se réarrange pas : droits,
	 * disponibilité du domaine, garde de jour, référentiel, validation
	 * tout-ou-rien, empreinte, diff, écriture.
	 *
	 * LE DIFF N'EST PAS UNE OPTIMISATION. Le modèle est en insertion pure et la
	 * lecture prend la dernière ligne : republier les 25 massifs écraserait, en
	 * lecture, des lignes officielles par des lignes manuelles attribuées au
	 * gestionnaire ALORS QU'IL N'A RIEN CHANGÉ. La vérité de la source serait
	 * détruite dans l'historique, définitivement.
	 *
	 * @param array<string, mixed> $entree Clés : `jour_jeton` (brut, non résolu par
	 *                                     l'appelant), `niveaux`
	 *                                     (`massif_code => niveau_cle`), `empreinte`
	 *                                     (chaîne vide = contrôle renoncé),
	 *                                     `origine` (`ecran`|`rest`, informatif,
	 *                                     ne relâche jamais une garde).
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_publier( array $entree ): array {
		$resultat = massifs_publication_resultat_vide();

		$origine = isset( $entree['origine'] ) && is_string( $entree['origine'] )
			? sanitize_key( $entree['origine'] )
			: 'ecran';

		$jeton = isset( $entree['jour_jeton'] ) && is_scalar( $entree['jour_jeton'] )
			? sanitize_key( (string) $entree['jour_jeton'] )
			: '';

		$empreinte_soumise = isset( $entree['empreinte'] ) && is_scalar( $entree['empreinte'] )
			? strtolower( trim( (string) $entree['empreinte'] ) )
			: '';

		$niveaux = massifs_publication_assainir_niveaux( isset( $entree['niveaux'] ) ? $entree['niveaux'] : array() );

		$resultat['jour_jeton'] = $jeton;

		// I-1. Assertion de droits EN DERNIER RECOURS : ce n'est pas un substitut
		// aux gardes des appelants, c'est le filet qui empêche un troisième
		// appelant futur d'écrire sans droit. On teste la CAPACITÉ, jamais le rôle.
		if ( get_current_user_id() <= 0 || ! current_user_can( massifs_publication_capacite() ) ) {
			return massifs_publication_refuser( $resultat, 'droits_insuffisants', $origine );
		}

		// I-2.
		if ( ! massifs_publication_domaine_disponible() ) {
			return massifs_publication_refuser(
				$resultat,
				'domaine_indisponible',
				$origine,
				implode( ', ', massifs_publication_fonctions_absentes() )
			);
		}

		// I-3. Garde de jour UNIQUE, évaluée MAINTENANT. Aucune date brute n'est
		// acceptée nulle part, et la rétroactivité du domaine n'est jamais invoquée.
		if ( ! in_array( $jeton, massifs_publication_jetons_jour(), true ) ) {
			return massifs_publication_refuser( $resultat, 'jour_refuse', $origine );
		}

		$jour = massifs_publication_resoudre_jour( $jeton );

		if ( '' === $jour ) {
			return massifs_publication_refuser( $resultat, 'jour_refuse', $origine );
		}

		$resultat['jour_validite'] = $jour;

		// I-4.
		$codes = massifs_publication_codes_actifs();

		if ( array() === $codes ) {
			return massifs_publication_refuser( $resultat, 'referentiel_indisponible', $origine );
		}

		// I-5. Validation locale TOUT-OU-RIEN : les radios contraignent les valeurs,
		// une valeur hors liste est une soumission forgée, pas une publication
		// partielle légitime.
		$cles = massifs_publication_cles_niveaux();

		if ( array() === $cles ) {
			return massifs_publication_refuser( $resultat, 'domaine_indisponible', $origine, 'legende sans niveau' );
		}

		foreach ( $niveaux as $code => $cle ) {
			if ( ! in_array( $code, $codes, true ) || ! in_array( $cle, $cles, true ) ) {
				return massifs_publication_refuser( $resultat, 'saisie_invalide', $origine );
			}
		}

		$etat = massifs_publication_etat_du_jour( $jour );

		if ( array() === $etat ) {
			return massifs_publication_refuser( $resultat, 'domaine_indisponible', $origine, 'etat du jour illisible' );
		}

		$empreinte                   = massifs_publication_empreinte( $etat, $jour );
		$resultat['empreinte_apres'] = $empreinte;

		// I-6. Empreinte fournie et divergente : zéro écriture.
		if ( '' !== $empreinte_soumise && $empreinte_soumise !== $empreinte ) {
			$cle_refus = massifs_publication_bascule_de_minuit( $jeton, $empreinte_soumise )
				? 'jour_refuse'
				: 'etat_modifie';

			return massifs_publication_refuser( $resultat, $cle_refus, $origine );
		}

		// I-7. Un code est écrit SI ET SEULEMENT SI son état n'est pas `disponible`
		// ou si son niveau enregistré diffère de la valeur soumise. Les autres ne
		// sont pas transmis au domaine.
		$delta     = array();
		$inchanges = array();
		$manquants = array();

		foreach ( $codes as $code ) {
			$courant = $etat[ $code ];

			if ( ! isset( $niveaux[ $code ] ) ) {
				// I-12. Un massif déjà publié ce jour-là et non resoumis n'est pas
				// « manquant » : son statut existe.
				if ( 'disponible' !== $courant['etat'] ) {
					$manquants[] = $code;
				}

				continue;
			}

			if ( 'disponible' === $courant['etat'] && $courant['niveau_cle'] === $niveaux[ $code ] ) {
				$inchanges[] = $code;

				continue;
			}

			$delta[ $code ] = $niveaux[ $code ];
		}

		$resultat['inchanges'] = $inchanges;
		$resultat['manquants'] = $manquants;

		// I-8. Delta vide : aucun appel au domaine, donc aucun signal de publication.
		if ( array() === $delta ) {
			return massifs_publication_refuser( $resultat, 'aucune_modification', $origine );
		}

		$auteur   = get_current_user_id();
		$charges  = array();
		$ordonnes = array();

		foreach ( $delta as $code => $cle ) {
			$ordonnes[] = $code;

			// I-9. Charge utile FIGÉE. Pas de `publie_prefecture_le` : une saisie
			// manuelle n'a pas d'instant de publication préfectorale, et en inventer
			// un serait affirmer un fait. Pas de `niveau_source_brut` : elle n'a pas
			// de `level`. `zapef_cle` vaut `null`, c'est-à-dire « pas
			// d'information » — dériver la ZAPEF depuis le niveau saisi serait une
			// inférence de domaine non ratifiée.
			$charges[] = array(
				'massif_code'   => $code,
				'jour_validite' => $jour,
				'source'        => 'saisie_manuelle',
				'auteur_id'     => $auteur,
				'niveau_cle'    => $cle,
				'zapef_cle'     => null,
			);

			if ( '' !== $etat[ $code ]['zapef_cle'] ) {
				$resultat['zapef_perdue'][] = $code;
			}
		}

		// I-8. UN SEUL APPEL BATCH, au pluriel : seul le pluriel émet
		// `massifs_statuts_publies`, sur lequel s'accrochera l'invalidation du cache
		// de page. Jamais une boucle sur le singulier.
		$bilan = massifs_enregistrer_statuts( $charges );

		$lignes = isset( $bilan['resultats'] ) && is_array( $bilan['resultats'] ) ? $bilan['resultats'] : array();

		foreach ( $ordonnes as $position => $code ) {
			$ligne   = isset( $lignes[ $position ] ) && is_array( $lignes[ $position ] ) ? $lignes[ $position ] : array();
			$ecrit   = isset( $ligne['enregistre'] ) && true === $ligne['enregistre'];
			$erreurs = isset( $ligne['erreurs'] ) && is_array( $ligne['erreurs'] ) ? array_values( $ligne['erreurs'] ) : array();

			if ( $ecrit ) {
				$resultat['ecrits'][] = $code;

				continue;
			}

			$resultat['refuses'][] = array(
				'code'    => $code,
				'erreurs' => array() === $erreurs ? array( 'echec_insertion' ) : $erreurs,
			);

			massifs_publication_journaliser( $origine, 'refus_ecriture', $code . ' : ' . implode( ',', $erreurs ) );
		}

		// Un massif refusé perd sa ZAPEF nulle part : rien n'a été écrit pour lui.
		$resultat['zapef_perdue'] = array_values( array_intersect( $resultat['zapef_perdue'], $resultat['ecrits'] ) );
		$resultat['publie']       = array() !== $resultat['ecrits'];

		$apres                       = massifs_publication_etat_du_jour( $jour );
		$resultat['empreinte_apres'] = array() === $apres ? $empreinte : massifs_publication_empreinte( $apres, $jour );
		$resultat['publie_le']       = massifs_publication_instant_publication( $apres, $resultat['ecrits'] );

		return $resultat;
	}
}

if ( ! function_exists( 'massifs_publication_instant_publication' ) ) {
	/**
	 * Instant d'enregistrement le plus récent parmi les lignes écrites.
	 *
	 * L'instant est POSÉ PAR LE DOMAINE et relu, jamais fabriqué ici : c'est la
	 * seule valeur qui décrive réellement ce qui a été écrit. Les instants sont en
	 * ISO 8601 UTC, donc comparables tels quels.
	 *
	 * @param array<string, array<string, mixed>> $etat   État relu après écriture.
	 * @param list<string>                        $ecrits Codes réellement écrits.
	 */
	function massifs_publication_instant_publication( array $etat, array $ecrits ): string {
		$instant = '';

		foreach ( $ecrits as $code ) {
			if ( ! isset( $etat[ $code ] ) ) {
				continue;
			}

			$candidat = (string) $etat[ $code ]['enregistre_le'];

			if ( '' !== $candidat && $candidat > $instant ) {
				$instant = $candidat;
			}
		}

		return $instant;
	}
}

if ( ! function_exists( 'massifs_publication_refuser' ) ) {
	/**
	 * Referme un résultat sur un refus, sans aucune écriture.
	 *
	 * @param array<string, mixed> $resultat Résultat en cours de construction.
	 * @param string               $cle      Clé d'erreur stable.
	 * @param string               $origine  Origine de l'appel.
	 * @param string               $detail   Détail non sensible pour le journal.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_refuser( array $resultat, string $cle, string $origine, string $detail = '' ): array {
		$resultat['publie']    = false;
		$resultat['ecrits']    = array();
		$resultat['erreurs'][] = $cle;

		massifs_publication_journaliser( $origine, $cle, $detail );

		return $resultat;
	}
}

if ( ! function_exists( 'massifs_publication_ton' ) ) {
	/**
	 * Ton du compte rendu déduit d'un résultat de publication.
	 *
	 * Liste fermée : `succes`, `partiel`, `refus`. Le ton `prefixe` n'est pas un
	 * résultat d'écriture — il est posé par le pré-remplissage, qui n'écrit rien.
	 *
	 * @param array<string, mixed> $resultat Résultat de `massifs_publication_publier()`.
	 */
	function massifs_publication_ton( array $resultat ): string {
		if ( array() === $resultat['ecrits'] ) {
			return 'refus';
		}

		if ( array() !== $resultat['refuses'] || array() !== $resultat['manquants'] || array() !== $resultat['erreurs'] ) {
			return 'partiel';
		}

		return 'succes';
	}
}
