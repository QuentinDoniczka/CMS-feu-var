<?php
/**
 * Handler `admin-post.php` de la publication : gardes, dispatch, redirection PRG.
 *
 * DEUX GARDES, JAMAIS UNE SEULE : le nonce ET la capacité. Le nonce prouve que la
 * requête vient de notre formulaire, la capacité que le compte a le droit
 * d'écrire ; aucune des deux ne remplace l'autre.
 *
 * Le handler ne valide pas les données lui-même et ne résout pas le jour : le
 * jeton traverse BRUT jusqu'au service, qui possède l'unique garde de jour. Un
 * appelant qui résoudrait le jour lui-même serait un second endroit où se tromper.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_traiter_post' ) ) {
	/**
	 * Traite la soumission du formulaire, puis redirige en 303.
	 *
	 * REDIRECTION 303 See Other, jamais le 302 par défaut : c'est le seul code qui
	 * force un `GET` sur tous les agents, donc le seul qui empêche un rechargement
	 * de republier. Le fragment `#massifs-recapitulatif` fait partie du contrat :
	 * sans lui, l'annonce du compte rendu perd son porteur sans JavaScript.
	 */
	function massifs_publication_traiter_post(): void {
		check_admin_referer( massifs_publication_action(), massifs_publication_nonce_champ() );

		if ( ! current_user_can( massifs_publication_capacite() ) ) {
			wp_die(
				esc_html( massifs_publication_message_erreur( 'droits_insuffisants' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		$jeton_brut = isset( $_POST['massifs_jour'] ) && is_scalar( $_POST['massifs_jour'] )
			? sanitize_key( wp_unslash( (string) $_POST['massifs_jour'] ) )
			: '';

		$intention = isset( $_POST['massifs_intention'] ) && is_scalar( $_POST['massifs_intention'] )
			? sanitize_key( wp_unslash( (string) $_POST['massifs_intention'] ) )
			: '';

		// `sanitize_key()` ne laisse passer que `[a-z0-9_-]`, ce qui couvre
		// exactement l'alphabet d'un `sha1` hexadécimal. Une empreinte altérée n'est
		// pas écartée ici : elle divergera, et diverger est le comportement voulu.
		$empreinte = isset( $_POST['massifs_empreinte'] ) && is_scalar( $_POST['massifs_empreinte'] )
			? sanitize_key( wp_unslash( (string) $_POST['massifs_empreinte'] ) )
			: '';

		// `massifs_niveau` ABSENT EST UN CAS NOMINAL, JAMAIS UNE SAISIE INVALIDE.
		// Le pré-remplissage est soumis par un formulaire FRÈRE qui ne transporte
		// aucun radio : sans cette scission, la soumission implicite de HTML —
		// `Entrée` frappé sur un radio — déclencherait le premier bouton du
		// document, « Tout autoriser », et remplacerait 25 choix par « autorisé ».
		// N'ajoutez donc jamais ici une garde « au moins un niveau soumis » : elle
		// casserait le pré-remplissage, et sur le seul chemin de publication elle
		// ferait doublon avec `aucune_modification`, que le service produit déjà.
		$niveaux = massifs_publication_assainir_niveaux(
			isset( $_POST['massifs_niveau'] ) ? wp_unslash( $_POST['massifs_niveau'] ) : array() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini clé par clé et valeur par valeur par `massifs_publication_assainir_niveaux()`.
		);

		$rapport               = massifs_publication_rapport_intention( $intention, $jeton_brut, $niveaux, $empreinte );
		$rapport['jour_jeton'] = massifs_publication_jeton_jour( $jeton_brut );

		$jeton_rapport = massifs_publication_deposer_rapport( $rapport );
		$arguments     = array( 'massifs_jour' => $rapport['jour_jeton'] );

		if ( '' !== $jeton_rapport ) {
			$arguments['massifs_resultat'] = $jeton_rapport;
		}

		$url = massifs_publication_url( $arguments );

		if ( '' !== $jeton_rapport ) {
			$url .= '#massifs-recapitulatif';
		}

		wp_safe_redirect( $url, 303 );

		exit;
	}
}

if ( ! function_exists( 'massifs_publication_rapport_intention' ) ) {
	/**
	 * Exécute l'intention soumise et en fait un compte rendu.
	 *
	 * L'intention est un CHAMP UNIQUE porté par le bouton : il n'y a donc aucune
	 * garde « exactement un des deux boutons » à écrire, parce qu'il n'y a pas deux
	 * champs. Une intention inconnue est une soumission forgée, jamais une
	 * publication à deviner.
	 *
	 * @param string                $intention  Intention soumise.
	 * @param string                $jeton_brut Jeton de jour, non résolu.
	 * @param array<string, string> $niveaux    Niveaux soumis, déjà assainis.
	 * @param string                $empreinte  Empreinte soumise.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_rapport_intention( string $intention, string $jeton_brut, array $niveaux, string $empreinte ): array {
		$rapport            = massifs_publication_rapport_vide();
		$rapport['niveaux'] = $niveaux;

		if ( 'publier' === $intention ) {
			$resultat = massifs_publication_publier(
				array(
					'jour_jeton' => $jeton_brut,
					'niveaux'    => $niveaux,
					'empreinte'  => $empreinte,
					'origine'    => 'ecran',
				)
			);

			$rapport['ton']          = massifs_publication_ton( $resultat );
			$rapport['ecrits']       = $resultat['ecrits'];
			$rapport['inchanges']    = $resultat['inchanges'];
			$rapport['manquants']    = $resultat['manquants'];
			$rapport['zapef_perdue'] = $resultat['zapef_perdue'];
			$rapport['refuses']      = $resultat['refuses'];
			$rapport['erreurs']      = $resultat['erreurs'];
			$rapport['publie_le']    = (string) $resultat['publie_le'];

			return $rapport;
		}

		$preremplissages = array(
			'preremplir_autorise' => 'autorise',
			'preremplir_interdit' => 'interdit',
		);

		if ( isset( $preremplissages[ $intention ] ) ) {
			return massifs_publication_rapport_preremplissage( $rapport, $preremplissages[ $intention ] );
		}

		$rapport['ton']     = 'refus';
		$rapport['erreurs'] = array( 'saisie_invalide' );

		massifs_publication_journaliser( 'ecran', 'saisie_invalide', 'intention : ' . $intention );

		return $rapport;
	}
}

if ( ! function_exists( 'massifs_publication_rapport_preremplissage' ) ) {
	/**
	 * Pré-remplit tous les massifs actifs avec un même niveau, SANS RIEN ÉCRIRE.
	 *
	 * Les journées où les 25 massifs partagent le même état sont le cas nominal
	 * observé : le pré-remplissage sert cela, et rien d'autre. Il propose, la
	 * publication dispose — c'est pourquoi il ne touche jamais la base.
	 *
	 * LA SUPERPOSITION EST CALCULÉE DEPUIS LE RÉFÉRENTIEL, et les niveaux postés
	 * sont délibérément ignorés : le pré-remplissage ÉCRASE la saisie partielle.
	 * C'est pourquoi son formulaire frère n'a aucun radio à transporter, et
	 * pourquoi une charge utile sans `massifs_niveau` n'est pas une anomalie.
	 *
	 * @param array<string, mixed> $rapport Compte rendu en construction.
	 * @param string               $cle     Clé de niveau à pré-remplir.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_rapport_preremplissage( array $rapport, string $cle ): array {
		if ( ! massifs_publication_domaine_disponible() ) {
			$rapport['ton']     = 'refus';
			$rapport['erreurs'] = array( 'domaine_indisponible' );

			return $rapport;
		}

		$codes = massifs_publication_codes_actifs();

		if ( array() === $codes ) {
			$rapport['ton']     = 'refus';
			$rapport['erreurs'] = array( 'referentiel_indisponible' );

			return $rapport;
		}

		if ( ! in_array( $cle, massifs_publication_cles_niveaux(), true ) ) {
			$rapport['ton']     = 'refus';
			$rapport['erreurs'] = array( 'saisie_invalide' );

			massifs_publication_journaliser( 'ecran', 'saisie_invalide', 'niveau de pré-remplissage : ' . $cle );

			return $rapport;
		}

		$rapport['ton']     = 'prefixe';
		$rapport['niveaux'] = array_fill_keys( $codes, $cle );

		return $rapport;
	}
}
