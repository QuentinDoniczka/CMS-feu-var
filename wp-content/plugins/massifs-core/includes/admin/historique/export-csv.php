<?php
/**
 * Export CSV du journal des statuts.
 *
 * `admin_post_` ET PAS UNE ROUTE REST : un lien `admin-post.php` fonctionne sans
 * JavaScript, là où une route REST rendant du `text/csv` devrait court-circuiter
 * `rest_pre_serve_request` et `exit` — se battre contre le sérialiseur JSON pour
 * rien.
 *
 * AUCUNE VARIANTE `nopriv` : un export du journal ne doit pas même avoir de
 * porte anonyme à refuser.
 *
 * L'EXPORT N'EST JAMAIS TRONQUÉ. Le domaine plafonne à 5000 lignes par appel et
 * une saison peut dépasser : la diffusion se fait en flux, par tranches, avec la
 * borne d'identifiant FIGÉE au démarrage. Un CSV tronqué présenté comme
 * « l'historique filtré » serait la même classe de mensonge que celle que cette
 * issue corrige.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MASSIFS_HISTORIQUE_TRANCHE_CSV' ) ) {
	define( 'MASSIFS_HISTORIQUE_TRANCHE_CSV', 500 );
}

if ( ! function_exists( 'massifs_historique_exporter' ) ) {
	/**
	 * Diffuse l'export CSV de l'ensemble filtré.
	 *
	 * SÉQUENCE CONTRACTUELLE, dans cet ordre : capacité, nonce, analyseur
	 * partagé, borne d'identifiant, en-têtes, flux. La capacité d'abord :
	 * vérifier un nonce avant une capacité revient à répondre différemment selon
	 * la validité d'un jeton à un visiteur qui n'a de toute façon aucun droit.
	 *
	 * TROISIÈME DES TROIS PORTES DE CAPACITÉ, indépendante de celle du menu et de
	 * celle de l'écran.
	 */
	function massifs_historique_exporter(): void {
		if ( ! current_user_can( MASSIFS_HISTORIQUE_CAPACITE ) ) {
			wp_die(
				esc_html( massifs_historique_mot( 'acces_refuse' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( MASSIFS_HISTORIQUE_ACTION_EXPORT );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce vérifié juste au-dessus ; l'assainissement de chaque champ appartient à l'analyseur unique, qui reçoit le tableau brut débarrassé de ses antislashs.
		$filtres = massifs_historique_filtres_depuis_requete( (array) wp_unslash( $_GET ) );

		if ( array() !== massifs_historique_fonctions_absentes() ) {
			wp_die(
				esc_html( massifs_historique_mot( 'etat_journal_indisponible' ) ),
				'',
				array( 'response' => 503 )
			);
		}

		// LA BORNE EST FIGÉE ICI, UNE FOIS. La table étant en insertion pure,
		// `id <= borne` rend l'ensemble résultat immuable pendant toute la
		// diffusion : aucune ligne ne peut être dupliquée ni sautée, même si le
		// cron écrit pendant l'export. Un curseur par `id` serait faux — l'ordre
		// est `enregistre_le DESC`, la dernière ligne d'une tranche n'est donc pas
		// le plus petit identifiant restant.
		try {
			$borne = massifs_journal_statuts_borne( massifs_historique_criteres( $filtres ) );
		} catch ( Throwable $exception ) {
			// La panne est constatée AVANT le premier octet : on peut encore
			// répondre honnêtement. Une fois le flux commencé, plus aucun code
			// d'erreur ne peut être émis — d'où cette lecture préalable.
			massifs_historique_journaliser( $exception );

			wp_die(
				esc_html( massifs_historique_mot( 'etat_journal_indisponible' ) ),
				'',
				array( 'response' => 503 )
			);
		}

		massifs_historique_entetes_csv();

		$sortie = fopen( 'php://output', 'w' );

		if ( false === $sortie ) {
			exit;
		}

		// BOM UTF-8 : sans lui, Excel en fr-FR lit le fichier en ANSI et les
		// accents des noms de massifs deviennent illisibles.
		echo "\xEF\xBB\xBF";

		massifs_historique_ecrire_ligne_csv( $sortie, massifs_historique_colonnes_csv() );

		$decalage = 0;

		do {
			$criteres = array_merge(
				massifs_historique_criteres( $filtres, $borne ),
				array(
					'limite'   => MASSIFS_HISTORIQUE_TRANCHE_CSV,
					'decalage' => $decalage,
				)
			);

			$entrees = massifs_journal_statuts( $criteres );

			foreach ( $entrees as $entree ) {
				massifs_historique_ecrire_ligne_csv(
					$sortie,
					massifs_historique_ligne_csv( massifs_historique_presenter( $entree ) )
				);
			}

			$decalage += MASSIFS_HISTORIQUE_TRANCHE_CSV;

			flush();
		} while ( count( $entrees ) === MASSIFS_HISTORIQUE_TRANCHE_CSV );

		fclose( $sortie );
		exit;
	}
}

if ( ! function_exists( 'massifs_historique_entetes_csv' ) ) {
	/**
	 * Pose les en-têtes HTTP de l'export et vide tout tampon de sortie.
	 *
	 * TOUS les tampons sont vidés : un tampon actif accumulerait l'export entier
	 * en mémoire avant de l'émettre, ce qui annule l'intérêt du flux et fait
	 * tomber les grands exports sur la limite de mémoire.
	 *
	 * Aucun `Content-Length` : la taille n'est pas connue à l'avance, et
	 * l'annoncer fausse condamnerait le téléchargement.
	 */
	function massifs_historique_entetes_csv(): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=' . massifs_historique_nom_fichier_csv() );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
}

if ( ! function_exists( 'massifs_historique_nom_fichier_csv' ) ) {
	/**
	 * Nom du fichier exporté, sans accent ni espace.
	 *
	 * Le jour vient du domaine, jamais recomposé à la main : c'est le jour civil
	 * de Paris, le même que celui qui gouverne les statuts.
	 */
	function massifs_historique_nom_fichier_csv(): string {
		$jour = function_exists( 'massifs_jour_courant' ) ? massifs_jour_courant() : '';

		return '' === $jour ? 'massifs-historique.csv' : 'massifs-historique-' . $jour . '.csv';
	}
}

if ( ! function_exists( 'massifs_historique_colonnes_csv' ) ) {
	/**
	 * En-têtes de colonnes du CSV, liste fermée.
	 *
	 * @return list<string>
	 */
	function massifs_historique_colonnes_csv(): array {
		return array(
			massifs_historique_mot( 'csv_reference' ),
			massifs_historique_mot( 'csv_massif_code' ),
			massifs_historique_mot( 'csv_massif' ),
			massifs_historique_mot( 'csv_jour' ),
			massifs_historique_mot( 'csv_niveau_precedent' ),
			massifs_historique_mot( 'csv_niveau' ),
			massifs_historique_mot( 'csv_zapef_precedent' ),
			massifs_historique_mot( 'csv_zapef' ),
			massifs_historique_mot( 'csv_changement' ),
			massifs_historique_mot( 'csv_source' ),
			massifs_historique_mot( 'csv_auteur' ),
			massifs_historique_mot( 'csv_enregistre' ),
		);
	}
}

if ( ! function_exists( 'massifs_historique_ligne_csv' ) ) {
	/**
	 * Une entrée présentée, en cellules de CSV.
	 *
	 * AUCUN ÉCHAPPEMENT HTML ICI : une entité HTML dans un CSV est une corruption
	 * de donnée, pas une protection. La valeur ancienne d'une première
	 * publication est une cellule VIDE — il n'y a pas de valeur ancienne, et en
	 * inventer une serait exactement le mensonge que cette issue corrige.
	 *
	 * `enregistre_le` est l'instant ISO 8601 UTC produit par le domaine, jamais
	 * une date recomposée : c'est la seule forme non ambiguë pour un tableur.
	 *
	 * @param array<string, mixed> $entree Entrée présentée par l'adaptateur.
	 *
	 * @return list<string>
	 */
	function massifs_historique_ligne_csv( array $entree ): array {
		$presentation = $entree['presentation'];
		$niveau       = $presentation['niveau'];
		$zapef        = $presentation['zapef'];

		return array(
			(string) $presentation['reference'],
			(string) $presentation['massif_code'],
			(string) $presentation['massif'],
			(string) $presentation['jour_validite'],
			is_array( $niveau['ancienne'] ) ? (string) $niveau['ancienne']['texte'] : '',
			(string) $niveau['nouvelle']['texte'],
			is_array( $zapef['ancienne'] ) ? (string) $zapef['ancienne']['texte'] : '',
			(string) $zapef['nouvelle']['texte'],
			(string) $presentation['changement_libelle'],
			(string) $presentation['source'],
			(string) $presentation['auteur'],
			(string) $presentation['enregistre_iso'],
		);
	}
}

if ( ! function_exists( 'massifs_historique_ecrire_ligne_csv' ) ) {
	/**
	 * Écrit une ligne de CSV, chaque cellule neutralisée.
	 *
	 * `$escape` EST PASSÉ EXPLICITEMENT À LA CHAÎNE VIDE : son défaut historique
	 * `\` produit un échappement non conforme au format CSV, que les tableurs
	 * relisent de travers. Le paramètre étant positionnel, le séparateur et le
	 * délimiteur doivent être passés eux aussi.
	 *
	 * Séparateur `;` : c'est celui qu'attend Excel en fr-FR.
	 *
	 * @param resource     $sortie   Flux de sortie.
	 * @param list<string> $cellules Cellules de la ligne.
	 */
	function massifs_historique_ecrire_ligne_csv( $sortie, array $cellules ): void {
		$neutralisees = array();

		foreach ( $cellules as $cellule ) {
			$neutralisees[] = massifs_historique_neutraliser_cellule( $cellule );
		}

		fputcsv( $sortie, $neutralisees, ';', '"', '' );
	}
}

if ( ! function_exists( 'massifs_historique_neutraliser_cellule' ) ) {
	/**
	 * Neutralise une cellule contre l'injection de formule.
	 *
	 * APPLIQUÉE À TOUTES LES CELLULES, SANS EXCEPTION. Trier les colonnes en
	 * « sûres » et « à risque » est précisément la façon dont ce défaut revient :
	 * un libellé de massif, un nom de compte ou une clé de niveau peuvent tous
	 * changer un jour.
	 *
	 * Une apostrophe de garde est préfixée quand le premier caractère est `=`,
	 * `+`, `-`, `@`, une tabulation (U+0009) ou un retour chariot (U+000D) : les
	 * six amorces qu'Excel et LibreOffice interprètent comme une formule.
	 *
	 * @param string $cellule Valeur brute.
	 */
	function massifs_historique_neutraliser_cellule( string $cellule ): string {
		if ( '' === $cellule ) {
			return '';
		}

		$amorces = array( '=', '+', '-', '@', "\t", "\r" );

		return in_array( substr( $cellule, 0, 1 ), $amorces, true ) ? "'" . $cellule : $cellule;
	}
}
