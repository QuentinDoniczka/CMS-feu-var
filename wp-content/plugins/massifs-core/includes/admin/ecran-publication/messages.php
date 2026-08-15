<?php
/**
 * Toutes les chaînes du portail de publication, et la traduction des clés
 * d'erreur du domaine.
 *
 * LE SERVEUR POSSÈDE LES DONNÉES ET LES CHAÎNES. Le gabarit ne compose, ne
 * paraphrase, n'abrège, ne corrige et ne traduit jamais une chaîne : elles vivent
 * toutes ici, en un seul endroit relisible.
 *
 * AUCUNE CLÉ D'ERREUR DU DOMAINE N'EST JAMAIS AFFICHÉE BRUTE. Les phrases sont à
 * la voix active et disent quoi faire, sans « Oups », sans « Désolé », sans « Une
 * erreur est survenue » (MASTER §11.1 règle 3).
 *
 * APOSTROPHES. La prose du portail emploie l'apostrophe typographique U+2019,
 * comme le reste du site. Les libellés officiels gardent la leur : `Niveau
 * d'Accès` porte une apostrophe droite U+0027, et les deux libellés d'accès
 * viennent de `massifs_legende()`, jamais d'un littéral.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_chaines' ) ) {
	/**
	 * Étiquettes et intitulés fixes de l'écran.
	 *
	 * `sous_titre_jour` est laissé vide ici : il porte une date, il est donc
	 * composé au moment du rendu par `massifs_publication_sous_titre_jour()`.
	 *
	 * @return array<string, string>
	 */
	function massifs_publication_chaines(): array {
		return array(
			// MASTER §7.2 écrit « MASSIFS · Mise à jour des statuts » pour la bande
			// de 56 px du portail. Le préfixe est une identité de chrome que
			// `wp-admin` porte déjà : le redoubler serait du chrome sur du chrome.
			'titre_ecran'             => 'Mise à jour des statuts',
			'sous_titre_jour'         => '',
			'jours_intitule'          => 'Jour à mettre à jour',
			'jour_aujourdhui'         => 'Aujourd’hui',
			'jour_demain'             => 'Demain',
			'jours_avertissement'     => 'Changer de jour n’enregistre rien : publiez avant de changer de jour.',
			'preremplissage_intitule' => 'Renseigner tous les massifs',
			'tout_autoriser'          => 'Tout autoriser',
			'tout_interdire'          => 'Tout interdire',
			'liste_titre'             => 'Statuts par massif',
			'etiquette_reference'     => 'État d’aujourd’hui',
			'etiquette_enregistre'    => 'Statut enregistré',
			'etiquette_niveau'        => massifs_publication_etiquette_niveau(),
			'etiquette_modification'  => 'Dernière modification',
		);
	}
}

if ( ! function_exists( 'massifs_publication_libelle_publier' ) ) {
	/**
	 * Libellé du bouton de publication, normatif (MASTER §7.2).
	 *
	 * Le libellé NOMME L'ACTION, jamais son mécanisme : ni « Valider », ni « OK »,
	 * ni « Soumettre ».
	 */
	function massifs_publication_libelle_publier(): string {
		return 'Publier les statuts';
	}
}

if ( ! function_exists( 'massifs_publication_libelle_carte_officielle' ) ) {
	/**
	 * Libellé du lien vers la carte officielle de la préfecture.
	 *
	 * L'adresse, elle, vient du domaine : elle n'est jamais écrite en dur.
	 */
	function massifs_publication_libelle_carte_officielle(): string {
		return 'Consulter la carte officielle de la préfecture';
	}
}

if ( ! function_exists( 'massifs_publication_message_legende_non_confirmee' ) ) {
	/**
	 * Mention servie tant que la légende n'est pas confirmée (MASTER §4.1).
	 *
	 * Elle n'empêche pas de publier : elle dit que les libellés reproduits n'ont
	 * pas encore été vérifiés contre la source officielle.
	 */
	function massifs_publication_message_legende_non_confirmee(): string {
		return 'Légende en cours de vérification.';
	}
}

if ( ! function_exists( 'massifs_publication_etiquette_niveau' ) ) {
	/**
	 * Intitulé officiel de la colonne de niveau, reproduit verbatim.
	 *
	 * APOSTROPHE DROITE U+0027 ET MAJUSCULE À `Accès` : c'est l'en-tête publié par
	 * la préfecture (MASTER §11.4). Ne pas l'uniformiser avec l'apostrophe
	 * typographique du reste de la prose, et ne jamais l'abréger en « Niveau ».
	 *
	 * `massifs_legende()` N'EXPOSE PAS cette chaîne — elle ne porte que les deux
	 * libellés d'accès et la note ZAPEF. Elle est donc reproduite ici, à l'unique
	 * endroit du portail où vivent les chaînes, et jamais dans un gabarit.
	 */
	function massifs_publication_etiquette_niveau(): string {
		return 'Niveau d\'Accès';
	}
}

if ( ! function_exists( 'massifs_publication_libelle_niveau' ) ) {
	/**
	 * Libellé officiel d'un niveau, lu dans la légende.
	 *
	 * JAMAIS UN LITTÉRAL : `Accès au massif autorisé` et `Accès au massif interdit`
	 * appartiennent à la légende officielle, et une copie locale dériverait le jour
	 * où la préfecture change son vocabulaire.
	 *
	 * @param string $cle Clé de niveau.
	 */
	function massifs_publication_libelle_niveau( string $cle ): string {
		if ( '' === $cle || ! function_exists( 'massifs_legende' ) ) {
			return '';
		}

		$legende = massifs_legende();

		if ( ! isset( $legende['niveaux'] ) || ! is_array( $legende['niveaux'] ) ) {
			return '';
		}

		foreach ( $legende['niveaux'] as $niveau ) {
			if ( is_array( $niveau ) && isset( $niveau['cle'], $niveau['libelle'] ) && $cle === $niveau['cle'] ) {
				return (string) $niveau['libelle'];
			}
		}

		return '';
	}
}

if ( ! function_exists( 'massifs_publication_phrase_etat' ) ) {
	/**
	 * Phrase d'un état hors niveau.
	 *
	 * `match()` SANS `default` : le vocabulaire de `EtatStatut` est fermé à quatre
	 * valeurs. `non_encore_publie` est l'état NOMINAL de demain avant publication,
	 * jamais une anomalie ; `indisponible` ne se dit jamais « aucune restriction ».
	 *
	 * `disponible` rend une chaîne vide : dans ce cas c'est le libellé officiel du
	 * niveau qui parle, et lui seul.
	 *
	 * @param string $etat État résolu par le domaine.
	 *
	 * @throws UnhandledMatchError Si le domaine émet un état hors du vocabulaire fermé.
	 */
	function massifs_publication_phrase_etat( string $etat ): string {
		return match ( $etat ) {
			'disponible'        => '',
			'non_encore_publie' => 'Statut non encore publié.',
			'indisponible'      => 'Information du jour non disponible.',
			'hors_saison'       => 'Dispositif estival inactif.',
		};
	}
}

if ( ! function_exists( 'massifs_publication_sous_titre_jour' ) ) {
	/**
	 * Sous-titre nommant le jour édité, en toutes lettres.
	 *
	 * @param string $date_lettres Date déjà formatée par le domaine.
	 */
	function massifs_publication_sous_titre_jour( string $date_lettres ): string {
		return '' === $date_lettres ? '' : 'Statuts du ' . $date_lettres;
	}
}

if ( ! function_exists( 'massifs_publication_phrase_modification_absente' ) ) {
	/**
	 * Phrase servie quand aucune modification n'est enregistrée pour le jour édité.
	 */
	function massifs_publication_phrase_modification_absente(): string {
		return 'Aucune modification enregistrée.';
	}
}

if ( ! function_exists( 'massifs_publication_texte_modification' ) ) {
	/**
	 * Instant de la dernière modification, en français long.
	 *
	 * Les deux fragments viennent de `massifs_horodatage()` : ni `date()`, ni
	 * `current_time()`, ni composition de date ailleurs qu'ici.
	 *
	 * @param string $date_longue Date longue formatée par le domaine.
	 * @param string $heure       Heure formatée par le domaine.
	 */
	function massifs_publication_texte_modification( string $date_longue, string $heure ): string {
		if ( '' === $date_longue ) {
			return $heure;
		}

		return '' === $heure ? $date_longue : $date_longue . ' à ' . $heure;
	}
}

if ( ! function_exists( 'massifs_publication_texte_compteur' ) ) {
	/**
	 * Compteur de la barre d'action.
	 *
	 * Sans JavaScript, le nombre de statuts modifiés n'est connu QU'APRÈS un
	 * aller-retour : au repos, le compteur reste vide plutôt que d'annoncer un
	 * chiffre faux. C'est la même raison qui, au §9.2 de MASTER, fait rendre
	 * « Publication impossible : aucun statut modifié. » après soumission
	 * seulement.
	 *
	 * @param int $modifies Nombre de statuts réellement écrits.
	 */
	function massifs_publication_texte_compteur( int $modifies ): string {
		if ( $modifies <= 0 ) {
			return '';
		}

		return 1 === $modifies
			? '1 statut modifié'
			: sprintf( '%d statuts modifiés', $modifies );
	}
}

if ( ! function_exists( 'massifs_publication_intitule_document' ) ) {
	/**
	 * Titre du document, porteur PRINCIPAL de la confirmation après redirection.
	 *
	 * Tous les lecteurs d'écran annoncent le titre au chargement : c'est le seul
	 * canal universel après une navigation, et il satisfait du même coup
	 * l'exigence de titres de page uniques.
	 *
	 * @param string $date_lettres Jour édité, en toutes lettres.
	 * @param string $ton          Ton du compte rendu, chaîne vide au repos.
	 * @param int    $ecrits       Nombre de statuts écrits.
	 */
	function massifs_publication_intitule_document( string $date_lettres, string $ton, int $ecrits ): string {
		$titre = massifs_publication_chaines()['titre_ecran'];

		if ( '' === $date_lettres ) {
			return $titre;
		}

		$repos = $titre . ' — ' . $date_lettres;

		$annonces = array(
			'succes'  => massifs_publication_texte_compteur( $ecrits ),
			'partiel' => massifs_publication_texte_compteur( $ecrits ),
			'refus'   => 'Publication refusée',
			'prefixe' => 'Niveaux pré-remplis',
		);

		if ( ! isset( $annonces[ $ton ] ) || '' === $annonces[ $ton ] ) {
			return $repos;
		}

		return $annonces[ $ton ] . ' pour ' . $date_lettres . ' — ' . $titre;
	}
}

if ( ! function_exists( 'massifs_publication_titre_recapitulatif' ) ) {
	/**
	 * Titre du compte rendu.
	 *
	 * La phrase normative de MASTER §9.2 est servie telle quelle quand le refus
	 * tient au seul fait qu'aucun statut n'a changé.
	 *
	 * @param string $ton     Ton du compte rendu.
	 * @param string $premier Première clé d'erreur, chaîne vide s'il n'y en a pas.
	 */
	function massifs_publication_titre_recapitulatif( string $ton, string $premier ): string {
		if ( 'refus' === $ton && 'aucune_modification' === $premier ) {
			return 'Publication impossible : aucun statut modifié.';
		}

		$titres = array(
			'succes'  => 'Statuts publiés',
			'partiel' => 'Publication partielle',
			'refus'   => 'Publication refusée',
			'prefixe' => 'Niveaux pré-remplis',
		);

		return isset( $titres[ $ton ] ) ? $titres[ $ton ] : 'Publication refusée';
	}
}

if ( ! function_exists( 'massifs_publication_resume_recapitulatif' ) ) {
	/**
	 * Résumé du compte rendu.
	 *
	 * @param string              $ton            Ton du compte rendu.
	 * @param array<string, int>  $comptes        Clés `ecrits`, `inchanges`, `refuses`.
	 * @param string              $date_lettres   Jour publié, en toutes lettres.
	 * @param string              $heure          Heure de la publication, formatée par le domaine.
	 * @param string              $message_global Phrase du refus, déjà traduite.
	 */
	function massifs_publication_resume_recapitulatif( string $ton, array $comptes, string $date_lettres, string $heure, string $message_global ): string {
		if ( 'refus' === $ton ) {
			return '' === $message_global ? massifs_publication_message_erreur( '' ) : $message_global;
		}

		if ( 'prefixe' === $ton ) {
			return 'Les niveaux sont pré-remplis. Rien n’est enregistré tant que vous n’avez pas publié.';
		}

		$ecrits    = isset( $comptes['ecrits'] ) ? (int) $comptes['ecrits'] : 0;
		$inchanges = isset( $comptes['inchanges'] ) ? (int) $comptes['inchanges'] : 0;
		$refuses   = isset( $comptes['refuses'] ) ? (int) $comptes['refuses'] : 0;

		$phrases = array();

		$publies = 1 === $ecrits ? '1 statut publié' : sprintf( '%d statuts publiés', $ecrits );

		if ( '' !== $date_lettres ) {
			$publies .= ' pour ' . $date_lettres;
		}

		if ( '' !== $heure ) {
			$publies .= ', à ' . $heure;
		}

		$phrases[] = $publies . '.';

		if ( $inchanges > 0 ) {
			$phrases[] = 1 === $inchanges
				? '1 massif inchangé.'
				: sprintf( '%d massifs inchangés.', $inchanges );
		}

		if ( $refuses > 0 ) {
			$phrases[] = 1 === $refuses
				? '1 massif n’a pas été enregistré.'
				: sprintf( '%d massifs n’ont pas été enregistrés.', $refuses );
		}

		return implode( ' ', $phrases );
	}
}

if ( ! function_exists( 'massifs_publication_intitule_manquants' ) ) {
	/**
	 * Intitulé de la liste des massifs restés sans niveau.
	 *
	 * Ils sont NOMMÉS, jamais complétés automatiquement : chacun est un lien vers
	 * sa ligne, pour que la correction soit à une touche « Entrée ».
	 */
	function massifs_publication_intitule_manquants(): string {
		return 'Massifs restés sans niveau';
	}
}

if ( ! function_exists( 'massifs_publication_intitule_zapef' ) ) {
	/**
	 * Intitulé de la liste des massifs dont la ZAPEF publiée n'est plus affichée.
	 *
	 * Une correction manuelle ne se prononce pas sur la ZAPEF : la ligne écrite ne
	 * porte aucune valeur, et c'est elle qui fait autorité pour le jour. La perte
	 * est donc RENDUE VISIBLE ET NOMINATIVE plutôt que tue — dériver une ZAPEF
	 * depuis le niveau saisi serait une invention interdite.
	 */
	function massifs_publication_intitule_zapef(): string {
		return 'ZAPEF de la préfecture non reprise pour ces massifs';
	}
}

if ( ! function_exists( 'massifs_publication_message_gabarit_absent' ) ) {
	/**
	 * Message servi quand le gabarit de rendu est absent.
	 *
	 * Un gabarit manquant est un DÉFAUT, pas un mode dégradé : aucun rendu de
	 * repli n'est produit.
	 */
	function massifs_publication_message_gabarit_absent(): string {
		return 'L’écran de mise à jour est incomplet : son gabarit d’affichage est absent. Prévenez un administrateur.';
	}
}

if ( ! function_exists( 'massifs_publication_messages_erreur' ) ) {
	/**
	 * Traduction des clés d'erreur stables en phrases servies.
	 *
	 * Table FERMÉE, gelée par le contrat. `{Massif}` est substitué par le libellé
	 * du massif concerné quand il est connu.
	 *
	 * @return array<string, string>
	 */
	function massifs_publication_messages_erreur(): array {
		return array(
			'niveau_inconnu'                => 'Choisissez un niveau pour chaque massif modifié.',
			'massif_inconnu'                => '{Massif} ne figure pas au référentiel. Rechargez l’écran, puis publiez à nouveau.',
			'massif_code_invalide'          => '{Massif} ne figure pas au référentiel. Rechargez l’écran, puis publiez à nouveau.',
			'jour_refuse'                   => 'Le jour a changé depuis l’ouverture de l’écran. Vérifiez la colonne, puis publiez à nouveau.',
			'jour_validite_invalide'        => 'Publiez les statuts d’aujourd’hui ou de demain.',
			'jour_validite_hors_horizon'    => 'Publiez les statuts d’aujourd’hui ou de demain.',
			'auteur_requis'                 => 'Reconnectez-vous, puis publiez à nouveau.',
			'auteur_interdit'               => 'Reconnectez-vous, puis publiez à nouveau.',
			'source_invalide'               => 'Rechargez l’écran, puis publiez à nouveau.',
			'publie_prefecture_le_invalide' => 'Rechargez l’écran, puis publiez à nouveau.',
			'echec_insertion'               => '{Massif} n’a pas été enregistré. Publiez à nouveau ce massif.',
			'nonce_invalide'                => 'Votre session a expiré. Rechargez l’écran, refaites votre choix, puis publiez.',
			'droits_insuffisants'           => 'Vous n’avez pas le droit de publier les statuts. Demandez l’accès à un administrateur.',
			'etat_modifie'                  => 'Les statuts de ce jour ont changé depuis l’ouverture de l’écran. Vérifiez la colonne, puis publiez à nouveau.',
			'aucune_modification'           => 'Aucun statut n’a changé. Modifiez au moins un niveau, puis publiez.',
			'saisie_invalide'               => 'Une valeur transmise n’est pas reconnue. Rechargez l’écran, refaites votre choix, puis publiez.',
			'referentiel_indisponible'      => 'Le référentiel des massifs n’est pas disponible. Prévenez un administrateur.',
			'domaine_indisponible'          => 'La publication est momentanément impossible. Prévenez un administrateur.',
		);
	}
}

if ( ! function_exists( 'massifs_publication_message_erreur' ) ) {
	/**
	 * Phrase servie pour une clé d'erreur du domaine.
	 *
	 * Une clé inconnue reçoit la phrase de repli et part dans le journal sous
	 * `WP_DEBUG` seulement : le gestionnaire lit une consigne d'action, jamais un
	 * identifiant technique.
	 *
	 * @param string $cle    Clé d'erreur stable.
	 * @param string $massif Libellé du massif concerné, s'il y en a un.
	 */
	function massifs_publication_message_erreur( string $cle, string $massif = '' ): string {
		$messages = massifs_publication_messages_erreur();
		$repli    = 'Rechargez l’écran, puis publiez à nouveau.';

		if ( ! isset( $messages[ $cle ] ) ) {
			massifs_publication_journaliser( 'messages', 'cle_erreur_inconnue', $cle );

			return $repli;
		}

		return str_replace( '{Massif}', '' === $massif ? 'Ce massif' : $massif, $messages[ $cle ] );
	}
}
