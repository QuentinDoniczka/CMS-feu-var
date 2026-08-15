<?php
/**
 * Contexte de l'écran de mise à jour des statuts : capacité, jetons de jour,
 * garde de disponibilité du domaine.
 *
 * AUCUNE CLASSE, AUCUN `namespace`, AUCUN `use` DANS CE RÉPERTOIRE. L'autoloader
 * de l'extension (`massifs-core.php`) minuscule les segments de namespace :
 * `Massifs\Admin\EcranPublication\X` viserait `includes/admin/ecranpublication/X.php`,
 * qui n'existe pas — notre répertoire porte un tiret. Fonctions préfixées
 * uniquement, chargées par `require_once` depuis `module.php`. Même posture que
 * `includes/rest/public/`.
 *
 * LES DROITS SONT CONSOMMÉS, JAMAIS CRÉÉS NI ÉLARGIS. La capacité
 * `massifs_publier_statuts` et le rôle `massifs_gestionnaire` appartiennent au
 * module `security/roles`. Aucun repli sur `manage_options`, aucun test de rôle :
 * l'administrateur porte la capacité sans porter le rôle.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_slug' ) ) {
	/**
	 * Identifiant de l'écran, gelé par le contrat.
	 *
	 * C'est une surface de contrat : il apparaît dans l'URL, dans le suffixe de
	 * hook et dans la redirection après publication.
	 */
	function massifs_publication_slug(): string {
		return 'massifs-publication';
	}
}

if ( ! function_exists( 'massifs_publication_action' ) ) {
	/**
	 * Nom de l'action `admin-post.php` et du nonce du formulaire.
	 */
	function massifs_publication_action(): string {
		return 'massifs_publier_statuts';
	}
}

if ( ! function_exists( 'massifs_publication_nonce_champ' ) ) {
	/**
	 * Nom du champ portant le nonce du formulaire.
	 */
	function massifs_publication_nonce_champ(): string {
		return 'massifs_publication_nonce';
	}
}

if ( ! function_exists( 'massifs_publication_capacite' ) ) {
	/**
	 * Capacité exigée pour publier ou corriger les statuts.
	 *
	 * Lue à sa source dès que le module des rôles est chargé. Le littéral n'est
	 * qu'un repli FAIL-CLOSED : c'est la chaîne gelée du contrat des rôles, et
	 * personne ne la porte tant que ce module n'a pas installé le rôle — refuser
	 * est réversible, laisser écrire ne l'est pas.
	 */
	function massifs_publication_capacite(): string {
		if ( function_exists( 'massifs_capacite_publier' ) ) {
			return massifs_capacite_publier();
		}

		return 'massifs_publier_statuts';
	}
}

if ( ! function_exists( 'massifs_publication_jetons_jour' ) ) {
	/**
	 * Les deux jetons de jour acceptés, dans l'ordre d'affichage.
	 *
	 * JETONS RELATIFS, JAMAIS DES DATES. C'est une décision de sécurité : une date
	 * brute rendrait `?massifs_jour=2024-08-02` concevable, le jeton rend la classe
	 * d'attaque inexistante.
	 *
	 * @return list<string>
	 */
	function massifs_publication_jetons_jour(): array {
		return array( 'aujourd_hui', 'demain' );
	}
}

if ( ! function_exists( 'massifs_publication_jeton_jour' ) ) {
	/**
	 * Normalise un jeton de jour reçu d'une requête.
	 *
	 * Jeton absent ou invalide vaut `demain` : c'est le jour que le §6 du brief
	 * demande de publier, et jamais une date arbitraire.
	 *
	 * @param string $brut Valeur brute reçue.
	 */
	function massifs_publication_jeton_jour( string $brut ): string {
		$jeton = sanitize_key( $brut );

		return in_array( $jeton, massifs_publication_jetons_jour(), true ) ? $jeton : 'demain';
	}
}

if ( ! function_exists( 'massifs_publication_jours' ) ) {
	/**
	 * Résolution des deux jetons en jours civils de Paris.
	 *
	 * Résolue par le domaine, JAMAIS par `date()` ni `current_time()` : la pile
	 * tourne en UTC et le jour de validité basculerait à 2 h du matin.
	 *
	 * @return array<string, string> Vide si l'horloge du domaine est absente.
	 */
	function massifs_publication_jours(): array {
		if ( ! function_exists( 'massifs_jour_courant' ) || ! function_exists( 'massifs_jour_suivant' ) ) {
			return array();
		}

		return array(
			'aujourd_hui' => massifs_jour_courant(),
			'demain'      => massifs_jour_suivant(),
		);
	}
}

if ( ! function_exists( 'massifs_publication_resoudre_jour' ) ) {
	/**
	 * Jour civil désigné par un jeton, résolu MAINTENANT.
	 *
	 * `Statuts::RETROACTIVITE_JOURS` n'est jamais invoqué : corriger un jour passé
	 * n'est pas dans le périmètre de cet écran.
	 *
	 * @param string $jeton Jeton de jour, déjà normalisé.
	 *
	 * @return string Chaîne vide si le jeton est inconnu ou l'horloge absente.
	 */
	function massifs_publication_resoudre_jour( string $jeton ): string {
		$jours = massifs_publication_jours();

		return isset( $jours[ $jeton ] ) ? $jours[ $jeton ] : '';
	}
}

if ( ! function_exists( 'massifs_publication_fonctions_requises' ) ) {
	/**
	 * Fonctions de domaine sans lesquelles aucune publication honnête n'est possible.
	 *
	 * Liste FERMÉE. Elles viennent de trois modules de domaine indépendants, qui
	 * peuvent échouer à charger séparément sur un arbre de travail partagé : leur
	 * absence doit produire un refus explicite, jamais une erreur fatale.
	 *
	 * @return list<string>
	 */
	function massifs_publication_fonctions_requises(): array {
		return array(
			'massifs_jour_courant',
			'massifs_jour_suivant',
			'massifs_referentiel',
			'massifs_statuts_du_jour',
			'massifs_legende',
			'massifs_legende_est_confirmee',
			'massifs_enregistrer_statuts',
			'massifs_horodatage',
		);
	}
}

if ( ! function_exists( 'massifs_publication_fonctions_absentes' ) ) {
	/**
	 * Fonctions requises manquantes, dans l'ordre de la liste fermée.
	 *
	 * @return list<string>
	 */
	function massifs_publication_fonctions_absentes(): array {
		$absentes = array();

		foreach ( massifs_publication_fonctions_requises() as $fonction ) {
			if ( ! function_exists( $fonction ) ) {
				$absentes[] = $fonction;
			}
		}

		return $absentes;
	}
}

if ( ! function_exists( 'massifs_publication_domaine_disponible' ) ) {
	/**
	 * Toutes les fonctions de domaine requises sont-elles chargées ?
	 */
	function massifs_publication_domaine_disponible(): bool {
		return array() === massifs_publication_fonctions_absentes();
	}
}

if ( ! function_exists( 'massifs_publication_url' ) ) {
	/**
	 * Adresse de l'écran, éventuellement portant des paramètres de navigation.
	 *
	 * Retourne une URL BRUTE, non échappée : l'échappement a lieu en sortie, dans
	 * le gabarit.
	 *
	 * @param array<string, string> $arguments Paramètres à joindre.
	 */
	function massifs_publication_url( array $arguments = array() ): string {
		$parametres = array_merge( array( 'page' => massifs_publication_slug() ), $arguments );

		return add_query_arg( $parametres, admin_url( 'admin.php' ) );
	}
}

if ( ! function_exists( 'massifs_publication_classe_marque_niveau' ) ) {
	/**
	 * Suffixe de classe de la marque d'un niveau.
	 *
	 * Un SUFFIXE DE CLASSE, jamais une couleur : aucune valeur hexadécimale ne
	 * traverse cette frontière. Les classes reprennent celles du thème
	 * (`composants.css`), qui portent le liseré et le motif dont dépend la
	 * conformité AA des statuts.
	 *
	 * @param string $cle Clé de niveau de la légende.
	 */
	function massifs_publication_classe_marque_niveau( string $cle ): string {
		return '' === $cle ? '' : 'pastille--' . str_replace( '_', '-', $cle );
	}
}

if ( ! function_exists( 'massifs_publication_classe_marque_etat' ) ) {
	/**
	 * Suffixe de classe de la marque d'un état hors niveau.
	 *
	 * `match()` SANS `default` : le vocabulaire de `EtatStatut` est fermé à quatre
	 * valeurs, et l'ajout d'un cinquième état doit casser bruyamment plutôt que
	 * peindre un statut inconnu comme s'il était connu.
	 *
	 * @param string $etat État résolu par le domaine.
	 *
	 * @throws UnhandledMatchError Si le domaine émet un état hors du vocabulaire fermé.
	 */
	function massifs_publication_classe_marque_etat( string $etat ): string {
		return match ( $etat ) {
			'disponible'        => '',
			'non_encore_publie' => 'pastille--non-publie',
			'indisponible'      => 'pastille--indisponible',
			'hors_saison'       => 'pastille--hors-saison',
		};
	}
}

if ( ! function_exists( 'massifs_publication_date_lettres' ) ) {
	/**
	 * Jour civil écrit en toutes lettres par le domaine.
	 *
	 * COUTURE ASSUMÉE, idiome déjà en service dans le thème :
	 * `massifs_horodatage()` exige un instant complet et refuse un jour civil nu.
	 * Midi UTC vaut 13 h ou 14 h à Paris, le jour civil ne bascule donc jamais.
	 * Seul `date_longue` est lu ; l'heure de cet appel décrirait midi et n'est
	 * jamais exposée.
	 *
	 * @param string $jour Jour civil `YYYY-MM-DD`.
	 */
	function massifs_publication_date_lettres( string $jour ): string {
		if ( '' === $jour || ! function_exists( 'massifs_horodatage' ) ) {
			return '';
		}

		try {
			$horodatage = massifs_horodatage( $jour . 'T12:00:00Z' );
		} catch ( InvalidArgumentException ) {
			return '';
		}

		return isset( $horodatage['date_longue'] ) && is_string( $horodatage['date_longue'] )
			? $horodatage['date_longue']
			: '';
	}
}

if ( ! function_exists( 'massifs_publication_journaliser' ) ) {
	/**
	 * Consigne un refus côté serveur, en debug seulement.
	 *
	 * Le détail reste sur le serveur : c'est la contrepartie de la phrase neutre
	 * servie à l'appelant. Hors `WP_DEBUG`, rien n'est écrit.
	 *
	 * @param string $origine Émetteur de la ligne : les deux appelants du service
	 *                        (`ecran`, `rest`), ou le module qui journalise pour son
	 *                        propre compte (`service`, `messages`).
	 * @param string $cle     Clé d'erreur stable.
	 * @param string $detail  Détail non sensible.
	 */
	function massifs_publication_journaliser( string $origine, string $cle, string $detail = '' ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf( '[massifs] publication (%s) %s%s', $origine, $cle, '' === $detail ? '' : ' : ' . $detail )
		);
	}
}
