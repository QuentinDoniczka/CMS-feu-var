<?php
/**
 * Adaptateur de présentation du journal des statuts.
 *
 * Trois consommateurs, une seule mise en forme : l'écran, la route REST et
 * l'export CSV lisent tous le même tableau. Chaque entrée conserve les clés
 * BRUTES du domaine et se voit adjoindre une sous-clé `presentation` rédigée
 * côté serveur — la vue n'aura donc aucune chaîne à composer.
 *
 * RIEN N'EST ÉCHAPPÉ ICI : les mêmes valeurs partent en HTML, en JSON et en CSV.
 * Seul `ecran.php` échappe.
 *
 * CE FICHIER N'ÉCRIT RIEN. Lecture stricte : aucune entrée d'audit produite,
 * aucune invalidation de cache, aucun appel à `massifs_enregistrer_statut*()`.
 * Il ne touche jamais `$wpdb` et n'instancie aucune classe `Massifs\`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_historique_fonctions_requises' ) ) {
	/**
	 * Fonctions de domaine sans lesquelles aucun journal honnête ne peut être rendu.
	 *
	 * Liste FERMÉE. Leur absence produit un état `journal_indisponible` explicite,
	 * JAMAIS un tableau vide : un tableau vide se lirait « il ne s'est rien
	 * passé », ce qui est un mensonge d'une tout autre gravité qu'une panne
	 * annoncée.
	 *
	 * @return list<string>
	 */
	function massifs_historique_fonctions_requises(): array {
		return array(
			'massifs_journal_statuts',
			'massifs_journal_statuts_total',
			'massifs_journal_statuts_borne',
			'massifs_journal_auteurs',
			'massifs_sources_statut',
		);
	}
}

if ( ! function_exists( 'massifs_historique_fonctions_absentes' ) ) {
	/**
	 * Fonctions requises manquantes, dans l'ordre de la liste fermée.
	 *
	 * @return list<string>
	 */
	function massifs_historique_fonctions_absentes(): array {
		$absentes = array();

		foreach ( massifs_historique_fonctions_requises() as $fonction ) {
			if ( ! function_exists( $fonction ) ) {
				$absentes[] = $fonction;
			}
		}

		return $absentes;
	}
}

if ( ! function_exists( 'massifs_historique_options_massifs' ) ) {
	/**
	 * Massifs proposés au filtre, code => libellé.
	 *
	 * Massifs RETIRÉS inclus : leur historique existe toujours et doit rester
	 * consultable.
	 *
	 * @return array<string, string>
	 */
	function massifs_historique_options_massifs(): array {
		if ( ! function_exists( 'massifs_codes' ) ) {
			return array();
		}

		$options = array();

		foreach ( massifs_codes( true ) as $code ) {
			$options[ $code ] = function_exists( 'massifs_libelle' ) ? massifs_libelle( $code ) : $code;
		}

		return $options;
	}
}

if ( ! function_exists( 'massifs_historique_options_sources' ) ) {
	/**
	 * Sources proposées au filtre, valeur => libellé.
	 *
	 * @return array<string, string>
	 */
	function massifs_historique_options_sources(): array {
		if ( ! function_exists( 'massifs_sources_statut' ) ) {
			return array();
		}

		$options = array();

		foreach ( massifs_sources_statut() as $source ) {
			$options[ $source ] = massifs_historique_source_libelle( $source );
		}

		return $options;
	}
}

if ( ! function_exists( 'massifs_historique_options_auteurs' ) ) {
	/**
	 * Auteurs proposés au filtre, identifiant => nom affiché.
	 *
	 * UNIQUEMENT les comptes présents dans le journal. Lister tous les comptes
	 * WordPress serait une énumération d'utilisateurs, que le §9 du brief exige
	 * de bloquer. Seul `display_name` sort d'ici : jamais `user_login`, jamais
	 * `user_email`.
	 *
	 * @return array<int, string>
	 */
	function massifs_historique_options_auteurs(): array {
		if ( ! function_exists( 'massifs_journal_auteurs' ) ) {
			return array();
		}

		$options = array();

		foreach ( massifs_journal_auteurs() as $auteur_id ) {
			$options[ $auteur_id ] = massifs_historique_auteur_libelle( $auteur_id );
		}

		return $options;
	}
}

if ( ! function_exists( 'massifs_historique_source_libelle' ) ) {
	/**
	 * Libellé d'une source, depuis la table fermée du vocabulaire.
	 *
	 * @param string $source Valeur de source du domaine.
	 */
	function massifs_historique_source_libelle( string $source ): string {
		$libelle = massifs_historique_mot( 'source_' . $source );

		// Une source inconnue du vocabulaire est affichée par sa valeur brute
		// plutôt que par une case vide : une provenance non nommée reste une
		// information, et la taire serait pire que de la montrer telle quelle.
		return '' === $libelle ? $source : $libelle;
	}
}

if ( ! function_exists( 'massifs_historique_auteur_libelle' ) ) {
	/**
	 * Libellé de la colonne « Auteur ».
	 *
	 * `null` = récupération officielle : le domaine INTERDIT un auteur sur cette
	 * source, l'absence est donc un fait, pas une lacune. Un compte disparu est
	 * nommé avec son identifiant — une case vide se confondrait avec « aucun
	 * auteur ».
	 *
	 * @param int|null $auteur_id Identifiant d'auteur.
	 */
	function massifs_historique_auteur_libelle( ?int $auteur_id ): string {
		if ( null === $auteur_id || $auteur_id <= 0 ) {
			return massifs_historique_mot( 'auteur_officiel' );
		}

		$compte = get_userdata( $auteur_id );

		if ( false === $compte || '' === (string) $compte->display_name ) {
			return sprintf( massifs_historique_mot( 'auteur_supprime' ), $auteur_id );
		}

		// SEUL `display_name` traverse cette frontière.
		return (string) $compte->display_name;
	}
}

if ( ! function_exists( 'massifs_historique_instant_libelle' ) ) {
	/**
	 * Instant d'enregistrement, en heure de Paris.
	 *
	 * La mise en forme est une RÈGLE DE DOMAINE (fuseau, noms de mois français) :
	 * elle vient de `massifs_horodatage()`, jamais de `wp_date()` ni de
	 * `date_i18n()`, que la stack rendrait en UTC.
	 *
	 * @param string $instant_iso_utc Instant ISO 8601 UTC.
	 *
	 * @return array{texte: string, attribut: string}
	 */
	function massifs_historique_instant_libelle( string $instant_iso_utc ): array {
		if ( '' === $instant_iso_utc || ! function_exists( 'massifs_horodatage' ) ) {
			return array(
				'texte'    => $instant_iso_utc,
				'attribut' => $instant_iso_utc,
			);
		}

		try {
			$horodatage = massifs_horodatage( $instant_iso_utc );
		} catch ( Throwable ) {
			// Un instant illisible est rendu tel qu'il est stocké : le journal
			// montre ce qu'il a, il n'invente pas une date de repli.
			return array(
				'texte'    => $instant_iso_utc,
				'attribut' => $instant_iso_utc,
			);
		}

		return array(
			'texte'    => sprintf(
				massifs_historique_mot( 'horodatage' ),
				(string) $horodatage['date_courte'],
				(string) $horodatage['heure']
			),
			'attribut' => (string) $horodatage['attr_datetime'],
		);
	}
}

if ( ! function_exists( 'massifs_historique_valeur_niveau' ) ) {
	/**
	 * Une valeur de niveau prête à rendre — aplat, motif, libellé, texte brut.
	 *
	 * TROIS CAS, ET UN SEUL A UNE PASTILLE :
	 * - clé nulle : « Aucun niveau publié », SANS pastille — une pastille se
	 *   lirait comme un niveau ;
	 * - clé absente de la légende courante : « Niveau non reconnu » et la clé
	 *   brute, SANS pastille — un échec de configuration, jamais silencieux ;
	 * - clé résolue : pastille + libellé officiel POSÉ À CÔTÉ de l'aplat, jamais
	 *   dessus, et `motif` toujours présent, l'information ne reposant jamais sur
	 *   la couleur seule.
	 *
	 * @param string|null               $cle    Clé stockée.
	 * @param array<string, mixed>|null $niveau Niveau résolu par le domaine.
	 * @param bool                      $zapef  La valeur relève-t-elle de la dimension ZAPEF ?
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_valeur_niveau( ?string $cle, ?array $niveau, bool $zapef ): array {
		if ( null === $cle ) {
			return array(
				'pastille' => false,
				'inconnu'  => false,
				'zapef'    => $zapef,
				'cle'      => '',
				'libelle'  => massifs_historique_mot( 'aucun_niveau' ),
				'texte'    => massifs_historique_mot( 'aucun_niveau' ),
				'motif'    => '',
				'niveau'   => '',
			);
		}

		if ( ! is_array( $niveau ) ) {
			return array(
				'pastille' => false,
				'inconnu'  => true,
				'zapef'    => $zapef,
				'cle'      => $cle,
				'libelle'  => massifs_historique_mot( 'niveau_inconnu' ),
				'texte'    => sprintf( massifs_historique_mot( 'niveau_inconnu_texte' ), $cle ),
				'motif'    => '',
				'niveau'   => '',
			);
		}

		$libelle = (string) ( $niveau['libelle'] ?? '' );
		$motif   = (string) ( $niveau['motif'] ?? '' );

		return array(
			'pastille' => true,
			'inconnu'  => false,
			'zapef'    => $zapef,
			'cle'      => $cle,
			'libelle'  => $libelle,
			'texte'    => $libelle,
			// `motif` est OBLIGATOIRE partout où une pastille apparaît, écran
			// gestionnaire compris (MASTER §4.1.d règle 4) : une pastille sans
			// attribut de motif est un statut porté par la seule couleur. Le
			// niveau autorisé porte `aucun`, qui est une valeur, pas une absence.
			'motif'    => '' === $motif ? 'aucun' : $motif,
			'niveau'   => (string) ( $niveau['cle'] ?? $cle ),
		);
	}
}

if ( ! function_exists( 'massifs_historique_transition' ) ) {
	/**
	 * La transition « ancienne → nouvelle » d'une dimension.
	 *
	 * `premiere` n'est vrai que si le SQL a établi qu'aucune ligne antérieure
	 * n'existe pour le couple (massif, jour) : UNE pastille, AUCUNE flèche, et la
	 * mention « Première publication ». Sans ce fait établi en base, la mention
	 * serait fausse une ligne sur deux — c'est tout l'objet de l'issue.
	 *
	 * @param array<string, mixed> $entree    Entrée résolue du domaine.
	 * @param string               $suffixe   `niveau` ou `zapef`.
	 * @param bool                 $est_zapef La dimension est-elle la ZAPEF ?
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_transition( array $entree, string $suffixe, bool $est_zapef ): array {
		$premiere = true === ( $entree['premiere_publication'] ?? false );

		$nouvelle = massifs_historique_valeur_niveau(
			isset( $entree[ $suffixe . '_cle' ] ) ? $entree[ $suffixe . '_cle' ] : null,
			isset( $entree[ $suffixe ] ) && is_array( $entree[ $suffixe ] ) ? $entree[ $suffixe ] : null,
			$est_zapef
		);

		if ( $premiere ) {
			return array(
				'premiere' => true,
				// La mention est portée UNE FOIS par ligne, par la dimension
				// principale : elle qualifie l'écriture entière, pas chacune de ses
				// deux dimensions, et la répéter la ferait lire comme deux faits.
				'mention'  => $est_zapef ? '' : massifs_historique_mot( 'changement_premiere_publication' ),
				'ancienne' => null,
				'nouvelle' => $nouvelle,
			);
		}

		return array(
			'premiere' => false,
			'mention'  => '',
			'ancienne' => massifs_historique_valeur_niveau(
				isset( $entree[ $suffixe . '_precedent_cle' ] ) ? $entree[ $suffixe . '_precedent_cle' ] : null,
				isset( $entree[ $suffixe . '_precedent' ] ) && is_array( $entree[ $suffixe . '_precedent' ] )
					? $entree[ $suffixe . '_precedent' ]
					: null,
				$est_zapef
			),
			'nouvelle' => $nouvelle,
		);
	}
}

if ( ! function_exists( 'massifs_historique_modificateur' ) ) {
	/**
	 * Modificateur de classe de ligne pour un changement.
	 *
	 * TABLE FERMÉE, et non une transformation mécanique de la valeur : le
	 * contrat de balisage nomme `--premiere`, là où le domaine dit
	 * `premiere_publication`. Une classe dérivée par `str_replace` produirait un
	 * sélecteur que la feuille de style ne connaît pas, et le défaut ne se
	 * verrait qu'à l'œil.
	 *
	 * Ces modificateurs sont DÉCORATIFS : aucune information ne s'y appuie, le
	 * changement est dit en toutes lettres dans la cellule.
	 *
	 * @param string $changement Valeur de changement du domaine.
	 */
	function massifs_historique_modificateur( string $changement ): string {
		$table = array(
			'premiere_publication' => 'premiere',
			'modification'         => 'modification',
			'sans_changement'      => 'sans-changement',
		);

		return $table[ $changement ] ?? 'modification';
	}
}

if ( ! function_exists( 'massifs_historique_presenter' ) ) {
	/**
	 * Ajoute à une entrée brute du domaine sa forme rédigée.
	 *
	 * Les clés brutes du contrat sont CONSERVÉES telles quelles : la route REST
	 * sérialise l'ensemble sans adaptateur, et l'écran ne lit que
	 * `presentation`.
	 *
	 * @param array<string, mixed> $entree Entrée résolue du domaine.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_presenter( array $entree ): array {
		$code       = (string) ( $entree['massif_code'] ?? '' );
		$changement = (string) ( $entree['changement'] ?? 'modification' );
		$auteur_id  = isset( $entree['auteur_id'] ) && null !== $entree['auteur_id'] ? (int) $entree['auteur_id'] : null;
		$instant    = massifs_historique_instant_libelle( (string) ( $entree['enregistre_le'] ?? '' ) );

		$entree['presentation'] = array(
			'massif_code'         => $code,
			'massif'              => function_exists( 'massifs_libelle' ) ? massifs_libelle( $code ) : $code,
			'jour_validite'       => (string) ( $entree['jour_validite'] ?? '' ),
			'niveau'              => massifs_historique_transition( $entree, 'niveau', false ),
			'zapef'               => massifs_historique_transition( $entree, 'zapef', true ),
			'source'              => massifs_historique_source_libelle( (string) ( $entree['source'] ?? '' ) ),
			'auteur'              => massifs_historique_auteur_libelle( $auteur_id ),
			'enregistre_le'       => $instant['texte'],
			'enregistre_attribut' => $instant['attribut'],
			'enregistre_iso'      => (string) ( $entree['enregistre_le'] ?? '' ),
			'reference'           => (int) ( $entree['id'] ?? 0 ),
			'changement'          => $changement,
			'changement_libelle'  => massifs_historique_mot( 'changement_' . $changement ),
			'modificateur'        => massifs_historique_modificateur( $changement ),
		);

		return $entree;
	}
}

if ( ! function_exists( 'massifs_historique_resume' ) ) {
	/**
	 * Phrase de résumé des résultats.
	 *
	 * @param int $total Nombre d'écritures de l'ensemble filtré.
	 * @param int $page  Page courante.
	 * @param int $pages Nombre de pages.
	 */
	function massifs_historique_resume( int $total, int $page, int $pages ): string {
		$format = 1 === $total
			? massifs_historique_mot( 'resume_singulier' )
			: massifs_historique_mot( 'resume_pluriel' );

		return sprintf( $format, $total, $page, max( 1, $pages ) );
	}
}

if ( ! function_exists( 'massifs_historique_pagination' ) ) {
	/**
	 * Éléments de pagination, déjà rédigés et déjà adressés.
	 *
	 * Ce sont des ancres : la pagination fonctionne sans une ligne de
	 * JavaScript. La page courante est un élément NON cliquable.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 * @param int                  $page    Page courante.
	 * @param int                  $pages   Nombre de pages.
	 *
	 * @return list<array<string, mixed>>
	 */
	function massifs_historique_pagination( array $filtres, int $page, int $pages ): array {
		if ( $pages < 2 ) {
			return array();
		}

		$elements = array();

		if ( $page > 1 ) {
			$elements[] = array(
				'type'    => 'precedente',
				'libelle' => massifs_historique_mot( 'pagination_precedente' ),
				'titre'   => massifs_historique_mot( 'pagination_precedente' ),
				'url'     => massifs_historique_url( $filtres, array( 'paged' => $page - 1 ) ),
				'courant' => false,
			);
		}

		// Fenêtre glissante : au-delà d'une poignée de liens, la barre déborde à
		// 360 px et devient un obstacle clavier plutôt qu'un raccourci.
		$debut = max( 1, $page - 2 );
		$fin   = min( $pages, $page + 2 );

		for ( $numero = $debut; $numero <= $fin; $numero++ ) {
			$elements[] = array(
				'type'    => 'numero',
				'libelle' => (string) $numero,
				'titre'   => sprintf( massifs_historique_mot( 'pagination_page' ), $numero ),
				'url'     => massifs_historique_url( $filtres, array( 'paged' => $numero ) ),
				'courant' => $numero === $page,
			);
		}

		if ( $page < $pages ) {
			$elements[] = array(
				'type'    => 'suivante',
				'libelle' => massifs_historique_mot( 'pagination_suivante' ),
				'titre'   => massifs_historique_mot( 'pagination_suivante' ),
				'url'     => massifs_historique_url( $filtres, array( 'paged' => $page + 1 ) ),
				'courant' => false,
			);
		}

		return $elements;
	}
}

if ( ! function_exists( 'massifs_historique_donnees' ) ) {
	/**
	 * Une page du journal, prête à rendre.
	 *
	 * `entrees` est TOUJOURS un tableau, jamais `null`.
	 *
	 * Quatre états, et la distinction compte : `journal_indisponible` est une
	 * panne, `journal_vide` un journal qui n'a encore rien reçu,
	 * `aucun_resultat` un filtre trop étroit. Les rendre tous par un tableau vide
	 * reviendrait à dire « il ne s'est rien passé » dans les trois cas.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 * @param int                  $id_max  Borne haute d'identifiant, `0` pour la calculer ici.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_donnees( array $filtres, int $id_max = 0 ): array {
		$page     = max( 1, (int) ( $filtres['paged'] ?? 1 ) );
		$par_page = (int) ( $filtres['par_page'] ?? massifs_historique_par_page_defaut() );

		$vide = array(
			'etat'               => 'journal_indisponible',
			'entrees'            => array(),
			'total'              => 0,
			'pages'              => 1,
			'page'               => $page,
			'par_page'           => $par_page,
			'id_max'             => 0,
			'erreur'             => false,
			'fonctions_absentes' => massifs_historique_fonctions_absentes(),
		);

		if ( array() !== $vide['fonctions_absentes'] ) {
			return $vide;
		}

		try {
			// LA BORNE EST POSÉE AVANT LE COMPTE, et le compte avant la page : le
			// total, la page servie et la pagination décrivent ainsi le MÊME
			// ensemble. Compter sur un ensemble et lister sur un autre est la façon
			// dont une pagination se met à sauter des lignes.
			$borne = $id_max > 0 ? $id_max : massifs_journal_statuts_borne( massifs_historique_criteres( $filtres ) );
			$total = massifs_journal_statuts_total( massifs_historique_criteres( $filtres, $borne ) );
			$pages = $par_page > 0 ? max( 1, (int) ceil( $total / $par_page ) ) : 1;

			// Une page hors de portée est ramenée à la dernière : afficher
			// « page 9 sur 2 » au-dessus d'un tableau vide se lirait comme un
			// journal amputé.
			$page = min( $page, $pages );

			$filtres['paged'] = $page;

			$entrees = $total > 0
				? massifs_journal_statuts( massifs_historique_criteres( $filtres, $borne ) )
				: array();
		} catch ( Throwable $exception ) {
			massifs_historique_journaliser( $exception );

			$vide['erreur'] = true;

			return $vide;
		}

		$presentees = array();

		foreach ( $entrees as $entree ) {
			$presentees[] = massifs_historique_presenter( $entree );
		}

		if ( 0 === $total ) {
			$etat = true === ( $filtres['actifs'] ?? false ) ? 'aucun_resultat' : 'journal_vide';
		} else {
			$etat = 'disponible';
		}

		return array(
			'etat'               => $etat,
			'entrees'            => $presentees,
			'total'              => $total,
			'pages'              => max( 1, $pages ),
			'page'               => $page,
			'par_page'           => $par_page,
			'id_max'             => $borne,
			'erreur'             => false,
			'fonctions_absentes' => array(),
		);
	}
}

if ( ! function_exists( 'massifs_historique_journaliser' ) ) {
	/**
	 * Consigne le détail d'une exception du domaine, en debug seulement.
	 *
	 * Le détail reste côté serveur : le gestionnaire reçoit une phrase neutre,
	 * jamais une trace.
	 *
	 * @param Throwable $exception Exception interceptée.
	 */
	function massifs_historique_journaliser( Throwable $exception ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[massifs] historique : %s dans %s:%d',
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			)
		);
	}
}
