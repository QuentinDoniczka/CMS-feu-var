<?php
/**
 * Construction du modèle de vue de l'écran de mise à jour des statuts.
 *
 * TOUTES LES CLÉS SONT TOUJOURS PRÉSENTES : le gabarit n'écrit jamais `isset()`
 * ni `??`. Aucune clé ne vaut `null` là où une chaîne est attendue — chaîne vide,
 * et le gabarit n'affiche rien.
 *
 * LES VALEURS SONT BRUTES ET NON ÉCHAPPÉES. L'échappement a lieu en sortie, dans
 * le gabarit, qui seul connaît son contexte.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_modele' ) ) {
	/**
	 * Modèle de vue complet de l'écran.
	 *
	 * Mémoïsé pour la requête : le filtre `admin_title` le demande avant le rendu
	 * de la page, et deux constructions donneraient deux nonces, deux empreintes et
	 * deux lectures de la base pour un seul écran.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_modele(): array {
		static $modele = null;

		if ( null === $modele ) {
			$modele = massifs_publication_construire_modele();
		}

		return $modele;
	}
}

if ( ! function_exists( 'massifs_publication_construire_modele' ) ) {
	/**
	 * Assemble le modèle de vue.
	 *
	 * Les deux paramètres de navigation sont assainis ICI, jamais par le gabarit :
	 * `massifs_jour` est un jeton relatif — absent ou invalide, il vaut `demain` —
	 * et `massifs_resultat` est un jeton opaque de compte rendu.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_construire_modele(): array {
		// Deux paramètres de navigation en lecture seule, sur un écran déjà protégé
		// par la capacité : ils ne déclenchent aucune écriture, et un nonce sur un
		// lien de navigation ne protégerait rien.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$jeton_brut    = isset( $_GET['massifs_jour'] ) && is_scalar( $_GET['massifs_jour'] )
			? sanitize_key( wp_unslash( (string) $_GET['massifs_jour'] ) )
			: '';
		$jeton_rapport = isset( $_GET['massifs_resultat'] ) && is_scalar( $_GET['massifs_resultat'] )
			? sanitize_key( wp_unslash( (string) $_GET['massifs_resultat'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$jeton   = massifs_publication_jeton_jour( $jeton_brut );
		$rapport = massifs_publication_lire_rapport( $jeton_rapport );

		// Un compte rendu ne s'affiche que sur le jour qu'il concerne : montrer le
		// bilan d'hier au-dessus des statuts de demain serait exactement le genre de
		// confusion de jour que ce projet refuse.
		if ( $rapport['jour_jeton'] !== $jeton ) {
			$rapport = massifs_publication_rapport_vide();
		}

		$chaines    = massifs_publication_chaines();
		$disponible = massifs_publication_domaine_disponible();
		$jours      = massifs_publication_jours();
		$jour       = $disponible ? massifs_publication_resoudre_jour( $jeton ) : '';

		$date_lettres               = massifs_publication_date_lettres( $jour );
		$chaines['sous_titre_jour'] = massifs_publication_sous_titre_jour( $date_lettres );

		$codes   = $disponible ? massifs_publication_codes_actifs() : array();
		$prete   = $disponible && array() !== $codes && '' !== $jour;
		$etat    = $prete ? massifs_publication_etat_du_jour( $jour ) : array();
		$courant = isset( $jours['aujourd_hui'] ) ? $jours['aujourd_hui'] : '';

		// La colonne de lecture seule montre TOUJOURS le jour courant : la
		// redondance quand on édite aujourd'hui est un fait de donnée, signalé par
		// `reference_redondante`, jamais un calcul laissé à la vue. Éditer
		// aujourd'hui évite une seconde lecture, puisque c'est le même jour.
		$reference = 'aujourd_hui' === $jeton || ! $prete
			? $etat
			: massifs_publication_etat_du_jour( $courant );

		$niveaux = massifs_publication_options_niveaux();

		return array(
			'ecran'                  => array(
				'titre'          => $chaines['titre_ecran'],
				'titre_document' => massifs_publication_intitule_document(
					$date_lettres,
					(string) $rapport['ton'],
					count( $rapport['ecrits'] )
				),
				'action_url'     => admin_url( 'admin-post.php' ),
				'action_nom'     => massifs_publication_action(),
				'nonce_champ'    => massifs_publication_nonce_champ(),
				'nonce'          => wp_create_nonce( massifs_publication_action() ),
				'empreinte'      => array() === $etat ? '' : massifs_publication_empreinte( $etat, $jour ),
				'page_url'       => massifs_publication_url(),
				'slug'           => massifs_publication_slug(),
			),
			'jour'                   => array(
				'jeton'                => $jeton,
				'date'                 => $jour,
				'date_lettres'         => $date_lettres,
				'choix'                => massifs_publication_choix_jour( $jeton, $jours, $chaines ),
				'reference_redondante' => 'aujourd_hui' === $jeton,
			),
			'niveaux'                => $niveaux,
			'lignes'                 => massifs_publication_lignes( $codes, $etat, $reference, $rapport, $niveaux ),
			'preremplissage'         => array(
				array(
					'valeur'  => 'preremplir_autorise',
					'libelle' => $chaines['tout_autoriser'],
				),
				array(
					'valeur'  => 'preremplir_interdit',
					'libelle' => $chaines['tout_interdire'],
				),
			),
			'publier'                => array(
				'valeur'  => 'publier',
				'libelle' => massifs_publication_libelle_publier(),
			),
			'compteur'               => array(
				'modifies' => count( $rapport['ecrits'] ),
				'texte'    => massifs_publication_texte_compteur( count( $rapport['ecrits'] ) ),
			),
			'recapitulatif'          => massifs_publication_recapitulatif( $rapport, $date_lettres ),
			'bandeaux'               => massifs_publication_bandeaux( $disponible, $prete ),
			'referentiel_disponible' => $prete,
			'legende_confirmee'      => function_exists( 'massifs_legende_est_confirmee' ) && massifs_legende_est_confirmee(),
			'chaines'                => $chaines,
		);
	}
}

if ( ! function_exists( 'massifs_publication_choix_jour' ) ) {
	/**
	 * Les deux entrées du sélecteur de jour, dans l'ordre imposé.
	 *
	 * Ce sont des liens `GET` : changer de jour est une navigation, jamais une
	 * saisie, et cela n'écrit rien.
	 *
	 * @param string                $jeton   Jeton actif.
	 * @param array<string, string> $jours   Résolution des jetons en jours civils.
	 * @param array<string, string> $chaines Chaînes du portail.
	 *
	 * @return list<array<string, mixed>>
	 */
	function massifs_publication_choix_jour( string $jeton, array $jours, array $chaines ): array {
		$libelles = array(
			'aujourd_hui' => $chaines['jour_aujourdhui'],
			'demain'      => $chaines['jour_demain'],
		);

		$choix = array();

		foreach ( massifs_publication_jetons_jour() as $candidat ) {
			$choix[] = array(
				'jeton'        => $candidat,
				'libelle'      => $libelles[ $candidat ],
				'date_lettres' => massifs_publication_date_lettres( isset( $jours[ $candidat ] ) ? $jours[ $candidat ] : '' ),
				'url'          => massifs_publication_url( array( 'massifs_jour' => $candidat ) ),
				'actif'        => $candidat === $jeton,
			);
		}

		return $choix;
	}
}

if ( ! function_exists( 'massifs_publication_options_niveaux' ) ) {
	/**
	 * Options de la paire segmentée, dans l'ordre de la légende.
	 *
	 * Les libellés sont ceux de la légende officielle, verbatim : ni abrégés, ni
	 * reformulés. Seuls des NOMS DE CLASSE traversent la frontière, jamais un
	 * pigment.
	 *
	 * @return list<array<string, mixed>>
	 */
	function massifs_publication_options_niveaux(): array {
		if ( ! function_exists( 'massifs_legende' ) ) {
			return array();
		}

		$legende = massifs_legende();

		if ( ! isset( $legende['niveaux'] ) || ! is_array( $legende['niveaux'] ) ) {
			return array();
		}

		$options = array();

		foreach ( $legende['niveaux'] as $niveau ) {
			if ( ! is_array( $niveau ) || ! isset( $niveau['cle'], $niveau['libelle'] ) ) {
				continue;
			}

			$cle = (string) $niveau['cle'];

			$options[] = array(
				'cle'           => $cle,
				'libelle'       => (string) $niveau['libelle'],
				'classe_marque' => massifs_publication_classe_marque_niveau( $cle ),
				'motif'         => isset( $niveau['motif'] ) ? (string) $niveau['motif'] : 'aucun',
				'severite'      => isset( $niveau['severite'] ) ? (int) $niveau['severite'] : 0,
				'rang'          => isset( $niveau['rang'] ) ? (int) $niveau['rang'] : 0,
				'total'         => isset( $niveau['total'] ) ? (int) $niveau['total'] : 0,
			);
		}

		return $options;
	}
}

if ( ! function_exists( 'massifs_publication_bloc_etat' ) ) {
	/**
	 * Bloc d'affichage d'un état enregistré.
	 *
	 * `libelle` reste vide hors de `disponible` — le gabarit n'a alors rien à
	 * afficher d'officiel — tandis que `phrase` n'est JAMAIS vide : un état sans
	 * phrase laisserait une case blanche à interpréter, et une case blanche se lit
	 * « rien à signaler ».
	 *
	 * Un état `disponible` dont le libellé de niveau a disparu de la légende est
	 * traité comme une absence d'information, jamais comme un niveau sans nom.
	 *
	 * @param array<string, mixed> $ligne Ligne d'état produite par le service.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_bloc_etat( array $ligne ): array {
		$etat    = (string) $ligne['etat'];
		$cle     = (string) $ligne['niveau_cle'];
		$libelle = '';

		if ( 'disponible' === $etat ) {
			$libelle = '' !== (string) $ligne['niveau_libelle']
				? (string) $ligne['niveau_libelle']
				: massifs_publication_libelle_niveau( $cle );

			if ( '' === $libelle ) {
				$etat = 'indisponible';
			}
		}

		if ( 'disponible' === $etat ) {
			return array(
				'etat'          => $etat,
				'classe_marque' => massifs_publication_classe_marque_niveau( $cle ),
				'libelle'       => $libelle,
				'phrase'        => $libelle,
			);
		}

		return array(
			'etat'          => $etat,
			'classe_marque' => massifs_publication_classe_marque_etat( $etat ),
			'libelle'       => '',
			'phrase'        => massifs_publication_phrase_etat( $etat ),
		);
	}
}

if ( ! function_exists( 'massifs_publication_bloc_modification' ) ) {
	/**
	 * Bloc « dernière modification » du jour édité.
	 *
	 * L'instant et l'auteur viennent de la ligne enregistrée : le portail ne
	 * compose jamais une date lui-même, et le nom de l'auteur est celui que le
	 * module des rôles sait rendre lisible même pour un compte supprimé.
	 *
	 * @param array<string, mixed> $ligne Ligne d'état du jour édité.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_bloc_modification( array $ligne ): array {
		$bloc = array(
			'renseignee'    => false,
			'auteur'        => '',
			'source'        => '',
			'attr_datetime' => '',
			'texte'         => '',
			'phrase'        => massifs_publication_phrase_modification_absente(),
		);

		if ( 'disponible' !== (string) $ligne['etat'] ) {
			return $bloc;
		}

		$bloc['renseignee'] = true;
		$bloc['source']     = (string) $ligne['source'];

		$auteur_id = (int) $ligne['auteur_id'];

		if ( $auteur_id > 0 && function_exists( 'massifs_nom_auteur' ) ) {
			$bloc['auteur'] = massifs_nom_auteur( $auteur_id );
		}

		$instant = (string) $ligne['enregistre_le'];

		if ( '' === $instant || ! function_exists( 'massifs_horodatage' ) ) {
			return $bloc;
		}

		try {
			$horodatage = massifs_horodatage( $instant );
		} catch ( InvalidArgumentException ) {
			return $bloc;
		}

		$bloc['attr_datetime'] = (string) $horodatage['attr_datetime'];
		$bloc['texte']         = massifs_publication_texte_modification(
			(string) $horodatage['date_longue'],
			(string) $horodatage['heure']
		);

		return $bloc;
	}
}

if ( ! function_exists( 'massifs_publication_lignes' ) ) {
	/**
	 * Une entrée par massif actif, dans l'ordre `tri` du référentiel.
	 *
	 * JAMAIS RETRIÉ, jamais filtré : l'ordre du référentiel est celui de l'écran.
	 *
	 * @param list<string>                        $codes     Codes actifs.
	 * @param array<string, array<string, mixed>> $etat      État du jour édité.
	 * @param array<string, array<string, mixed>> $reference État du jour courant.
	 * @param array<string, mixed>                $rapport   Compte rendu retiré.
	 * @param list<array<string, mixed>>          $niveaux   Options de niveau.
	 *
	 * @return list<array<string, mixed>>
	 */
	function massifs_publication_lignes( array $codes, array $etat, array $reference, array $rapport, array $niveaux ): array {
		if ( array() === $codes || ! function_exists( 'massifs_referentiel' ) ) {
			return array();
		}

		$referentiel = massifs_referentiel();
		$cles        = array();

		foreach ( $niveaux as $niveau ) {
			$cles[] = (string) $niveau['cle'];
		}

		$refus_par_code = massifs_publication_refus_par_code( $rapport );
		$lignes         = array();

		foreach ( $codes as $code ) {
			$ligne_etat      = isset( $etat[ $code ] ) ? $etat[ $code ] : massifs_publication_etat_defaut();
			$ligne_reference = isset( $reference[ $code ] ) ? $reference[ $code ] : massifs_publication_etat_defaut();
			$libelle         = isset( $referentiel[ $code ]['libelle'] ) ? (string) $referentiel[ $code ]['libelle'] : $code;
			$id_base         = 'massifs-' . $code;

			$lignes[] = array(
				'code'          => $code,
				'libelle'       => $libelle,
				'ancre'         => 'massif-' . $code,
				'champ'         => 'massifs_niveau[' . $code . ']',
				'id_base'       => $id_base,
				'valeur_cochee' => massifs_publication_valeur_cochee( $code, $ligne_etat, $rapport, $cles ),
				'reference'     => massifs_publication_bloc_etat( $ligne_reference ),
				'enregistre'    => massifs_publication_bloc_etat( $ligne_etat ),
				'modification'  => massifs_publication_bloc_modification( $ligne_etat ),
				'refus'         => array(
					'present' => isset( $refus_par_code[ $code ] ),
					'id'      => $id_base . '-erreur',
					'message' => isset( $refus_par_code[ $code ] )
						? massifs_publication_message_erreur( $refus_par_code[ $code ], $libelle )
						: '',
				),
			);
		}

		return $lignes;
	}
}

if ( ! function_exists( 'massifs_publication_etat_defaut' ) ) {
	/**
	 * Ligne d'état à retenir quand le domaine n'en fournit aucune.
	 *
	 * `indisponible` et jamais un niveau : une absence de lecture n'est pas une
	 * absence de restriction.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_etat_defaut(): array {
		return array(
			'etat'           => 'indisponible',
			'niveau_cle'     => '',
			'niveau_libelle' => '',
			'zapef_cle'      => '',
			'source'         => '',
			'auteur_id'      => 0,
			'enregistre_le'  => '',
		);
	}
}

if ( ! function_exists( 'massifs_publication_valeur_cochee' ) ) {
	/**
	 * Valeur à cocher pour un massif.
	 *
	 * Dans cet ordre : la valeur POSTÉE (pré-remplissage, ou soumission refusée à
	 * réafficher) ; sinon le niveau enregistré POUR LE JOUR ÉDITÉ LUI-MÊME ; sinon
	 * rien.
	 *
	 * JAMAIS la valeur du jour courant reportée sur demain : « repartir des statuts
	 * d'aujourd'hui » institutionnaliserait « je suppose que rien n'a changé » sur
	 * un site dont la règle centrale est de ne jamais rejouer la veille.
	 *
	 * @param string               $code    Code du massif.
	 * @param array<string, mixed> $etat    Ligne d'état du jour édité.
	 * @param array<string, mixed> $rapport Compte rendu retiré.
	 * @param list<string>         $cles    Clés de niveau admises.
	 */
	function massifs_publication_valeur_cochee( string $code, array $etat, array $rapport, array $cles ): string {
		if ( isset( $rapport['niveaux'][ $code ] ) && is_string( $rapport['niveaux'][ $code ] ) ) {
			$postee = $rapport['niveaux'][ $code ];

			if ( in_array( $postee, $cles, true ) ) {
				return $postee;
			}
		}

		if ( 'disponible' === (string) $etat['etat'] && in_array( (string) $etat['niveau_cle'], $cles, true ) ) {
			return (string) $etat['niveau_cle'];
		}

		return '';
	}
}

if ( ! function_exists( 'massifs_publication_refus_par_code' ) ) {
	/**
	 * Première clé d'erreur retenue pour chaque massif refusé.
	 *
	 * @param array<string, mixed> $rapport Compte rendu retiré.
	 *
	 * @return array<string, string>
	 */
	function massifs_publication_refus_par_code( array $rapport ): array {
		$refus = array();

		foreach ( $rapport['refuses'] as $entree ) {
			if ( ! is_array( $entree ) || ! isset( $entree['code'] ) || ! is_string( $entree['code'] ) ) {
				continue;
			}

			$cles = isset( $entree['erreurs'] ) && is_array( $entree['erreurs'] ) ? array_values( $entree['erreurs'] ) : array();

			$refus[ $entree['code'] ] = isset( $cles[0] ) && is_string( $cles[0] ) ? $cles[0] : 'echec_insertion';
		}

		return $refus;
	}
}

if ( ! function_exists( 'massifs_publication_massifs_nommes' ) ) {
	/**
	 * Nomme une liste de codes pour le compte rendu.
	 *
	 * Les massifs concernés sont NOMMÉS et liés à leur ligne : une liste de codes
	 * techniques obligerait le gestionnaire à traduire lui-même.
	 *
	 * @param list<string>          $codes    Codes concernés.
	 * @param array<string, string> $messages Message par code, pour les refus.
	 *
	 * @return list<array<string, string>>
	 */
	function massifs_publication_massifs_nommes( array $codes, array $messages = array() ): array {
		$referentiel = function_exists( 'massifs_referentiel' ) ? massifs_referentiel() : array();
		$nommes      = array();

		foreach ( $codes as $code ) {
			if ( ! is_string( $code ) || '' === $code ) {
				continue;
			}

			$libelle = isset( $referentiel[ $code ]['libelle'] ) ? (string) $referentiel[ $code ]['libelle'] : $code;

			$nommes[] = array(
				'code'    => $code,
				'libelle' => $libelle,
				'ancre'   => 'massif-' . $code,
				'message' => isset( $messages[ $code ] ) ? massifs_publication_message_erreur( $messages[ $code ], $libelle ) : '',
			);
		}

		return $nommes;
	}
}

if ( ! function_exists( 'massifs_publication_recapitulatif' ) ) {
	/**
	 * Bloc du compte rendu de la dernière soumission.
	 *
	 * Bloc PERSISTANT ET IMPRIMABLE, jamais une notification fugitive : c'est la
	 * trace de ce qui vient d'être publié.
	 *
	 * @param array<string, mixed> $rapport      Compte rendu retiré.
	 * @param string               $date_lettres Jour édité, en toutes lettres.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_recapitulatif( array $rapport, string $date_lettres ): array {
		$vide = array(
			'present'            => false,
			'ton'                => 'succes',
			'titre'              => '',
			'resume'             => '',
			'manquants_intitule' => '',
			'manquants'          => array(),
			'omission_zapef'     => '',
			'zapef_perdue'       => array(),
			'refus'              => array(),
		);

		if ( true !== $rapport['present'] ) {
			return $vide;
		}

		$ton     = (string) $rapport['ton'];
		$erreurs = array_values( $rapport['erreurs'] );
		$premier = isset( $erreurs[0] ) && is_string( $erreurs[0] ) ? $erreurs[0] : '';

		$manquants    = massifs_publication_massifs_nommes( array_values( $rapport['manquants'] ) );
		$zapef_perdue = massifs_publication_massifs_nommes( array_values( $rapport['zapef_perdue'] ) );
		$par_code     = massifs_publication_refus_par_code( $rapport );
		$refus        = massifs_publication_massifs_nommes( array_keys( $par_code ), $par_code );

		return array(
			'present'            => true,
			'ton'                => $ton,
			'titre'              => massifs_publication_titre_recapitulatif( $ton, $premier ),
			'resume'             => massifs_publication_resume_recapitulatif(
				$ton,
				array(
					'ecrits'    => count( $rapport['ecrits'] ),
					'inchanges' => count( $rapport['inchanges'] ),
					'refuses'   => count( $refus ),
				),
				$date_lettres,
				massifs_publication_heure_publication( (string) $rapport['publie_le'] ),
				'' === $premier ? '' : massifs_publication_message_erreur( $premier )
			),
			'manquants_intitule' => array() === $manquants ? '' : massifs_publication_intitule_manquants(),
			'manquants'          => $manquants,
			'omission_zapef'     => array() === $zapef_perdue ? '' : massifs_publication_intitule_zapef(),
			'zapef_perdue'       => $zapef_perdue,
			'refus'              => $refus,
		);
	}
}

if ( ! function_exists( 'massifs_publication_heure_publication' ) ) {
	/**
	 * Heure de la publication, formatée par le domaine.
	 *
	 * @param string $instant Instant ISO 8601 UTC posé par le domaine.
	 */
	function massifs_publication_heure_publication( string $instant ): string {
		if ( '' === $instant || ! function_exists( 'massifs_horodatage' ) ) {
			return '';
		}

		try {
			$horodatage = massifs_horodatage( $instant );
		} catch ( InvalidArgumentException ) {
			return '';
		}

		return (string) $horodatage['heure'];
	}
}

if ( ! function_exists( 'massifs_publication_bandeaux' ) ) {
	/**
	 * Bandeaux d'état de page.
	 *
	 * Référentiel ou domaine indisponible : l'écran ne rend que ce bandeau — ni
	 * liste, ni formulaire, ni bouton. Une liste vide se lirait « aucun massif à
	 * mettre à jour », ce qui est faux.
	 *
	 * @param bool $domaine Le domaine est-il disponible ?
	 * @param bool $prete   L'écran peut-il rendre la liste ?
	 *
	 * @return list<array<string, string>>
	 */
	function massifs_publication_bandeaux( bool $domaine, bool $prete ): array {
		$bandeaux = array();

		if ( ! $domaine ) {
			$bandeaux[] = massifs_publication_bandeau( massifs_publication_message_erreur( 'domaine_indisponible' ) );

			return $bandeaux;
		}

		if ( ! $prete ) {
			$bandeaux[] = massifs_publication_bandeau(
				massifs_publication_message_erreur( 'referentiel_indisponible' ),
				massifs_publication_libelle_carte_officielle(),
				massifs_publication_carte_officielle_url()
			);

			return $bandeaux;
		}

		// MASTER §4.1 : tant que la légende n'est pas confirmée, le consommateur
		// l'annonce et ne compte pas sur ses consignes.
		if ( function_exists( 'massifs_legende_est_confirmee' ) && ! massifs_legende_est_confirmee() ) {
			$bandeaux[] = massifs_publication_bandeau( massifs_publication_message_legende_non_confirmee() );
		}

		return $bandeaux;
	}
}

if ( ! function_exists( 'massifs_publication_bandeau' ) ) {
	/**
	 * Une entrée de bandeau, toutes clés présentes.
	 *
	 * @param string $texte      Texte du bandeau.
	 * @param string $lien_texte Libellé du lien, s'il y en a un.
	 * @param string $lien_url   Adresse du lien, s'il y en a une.
	 *
	 * @return array<string, string>
	 */
	function massifs_publication_bandeau( string $texte, string $lien_texte = '', string $lien_url = '' ): array {
		return array(
			'texte'      => $texte,
			'lien_texte' => '' === $lien_url ? '' : $lien_texte,
			'lien_url'   => '' === $lien_texte ? '' : $lien_url,
		);
	}
}

if ( ! function_exists( 'massifs_publication_carte_officielle_url' ) ) {
	/**
	 * Adresse de la carte officielle, servie par le domaine.
	 *
	 * Jamais écrite en dur : c'est le repli imposé par le §4.2 du brief, et le
	 * domaine en est la seule source.
	 */
	function massifs_publication_carte_officielle_url(): string {
		if ( ! function_exists( 'massifs_attribution_statuts' ) ) {
			return '';
		}

		$attribution = massifs_attribution_statuts();

		return isset( $attribution['carte_officielle_url'] ) && is_string( $attribution['carte_officielle_url'] )
			? $attribution['carte_officielle_url']
			: '';
	}
}
