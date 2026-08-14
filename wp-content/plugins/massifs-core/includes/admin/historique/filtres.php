<?php
/**
 * L'analyseur UNIQUE des filtres de l'historique.
 *
 * C'EST LA PIÈCE QUI EMPÊCHE L'ÉCRAN, LA ROUTE REST ET L'EXPORT DE DIVERGER.
 * Deux analyseurs finissent toujours par ne plus dire la même chose : il n'y en
 * a qu'un, et les trois consommateurs lui repassent leurs paramètres bruts sans
 * rien interpréter eux-mêmes.
 *
 * AUCUNE VALEUR N'EST ÉCHAPPÉE ICI. L'échappement est à la SORTIE : les mêmes
 * tableaux alimentent du HTML, du JSON et un CSV, et une entité HTML dans un CSV
 * est une corruption de donnée, pas une protection.
 *
 * TOUTE VALEUR REJETÉE ENTRE DANS `rejets`, JAMAIS CORRIGÉE EN SILENCE. Les
 * champs libres conservent la valeur saisie — l'écran la réaffiche avec
 * `aria-invalid="true"` et un avertissement relié au champ — et
 * `massifs_historique_criteres()` écarte tout champ listé dans `rejets` : une
 * valeur refusée est affichée, jamais appliquée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MASSIFS_HISTORIQUE_PAGE' ) ) {
	define( 'MASSIFS_HISTORIQUE_PAGE', 'massifs-historique' );
}

if ( ! defined( 'MASSIFS_HISTORIQUE_CAPACITE' ) ) {
	/*
	 * Capacité créée et accordée par la chaîne #13. Cette chaîne la CONSOMME et
	 * ne la définit jamais. Aucun repli sur `manage_options` nulle part : un
	 * repli ferait passer la porte pour fermée alors qu'elle serait ouverte à
	 * toute personne pouvant gérer les options.
	 */
	define( 'MASSIFS_HISTORIQUE_CAPACITE', 'massifs_consulter_historique' );
}

if ( ! defined( 'MASSIFS_HISTORIQUE_ACTION_EXPORT' ) ) {
	define( 'MASSIFS_HISTORIQUE_ACTION_EXPORT', 'massifs_exporter_historique' );
}

if ( ! function_exists( 'massifs_historique_par_page_admises' ) ) {
	/**
	 * Tailles de page admises.
	 *
	 * @return list<int>
	 */
	function massifs_historique_par_page_admises(): array {
		return array( 20, 50, 100, 200 );
	}
}

if ( ! function_exists( 'massifs_historique_par_page_defaut' ) ) {
	/**
	 * Taille de page par défaut.
	 */
	function massifs_historique_par_page_defaut(): int {
		return 50;
	}
}

if ( ! function_exists( 'massifs_historique_champs_jour' ) ) {
	/**
	 * Les quatre champs de date, dans l'ordre du formulaire.
	 *
	 * Nommés UNE SEULE FOIS : l'analyse et la traduction en critères parcourent
	 * la même liste, et deux listes recopiées finiraient par ne plus contenir les
	 * mêmes champs — un filtre saisi, affiché, mais jamais appliqué.
	 *
	 * @return list<string>
	 */
	function massifs_historique_champs_jour(): array {
		return array( 'jour_debut', 'jour_fin', 'enregistre_debut', 'enregistre_fin' );
	}
}

if ( ! function_exists( 'massifs_historique_filtres_vides' ) ) {
	/**
	 * Jeu de filtres neutre — la forme de retour de l'analyseur, sans aucun filtre.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_filtres_vides(): array {
		return array(
			'massif_code'      => '',
			'auteur_id'        => 0,
			'source'           => '',
			'jour_debut'       => '',
			'jour_fin'         => '',
			'enregistre_debut' => '',
			'enregistre_fin'   => '',
			'paged'            => 1,
			'par_page'         => massifs_historique_par_page_defaut(),
			'rejets'           => array(),
			'actifs'           => false,
		);
	}
}

if ( ! function_exists( 'massifs_historique_jour_est_exploitable' ) ) {
	/**
	 * Le jour est-il bien formé ET existant ?
	 *
	 * La forme est vérifiée ici ; l'EXISTENCE est déléguée au domaine, seule
	 * autorité du temps de l'extension. Sans lui, `2026-02-31` passerait la seule
	 * expression régulière.
	 *
	 * @param string $jour Jour candidat.
	 */
	function massifs_historique_jour_est_exploitable( string $jour ): bool {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $jour ) ) {
			return false;
		}

		$parties = array_map( 'intval', explode( '-', $jour ) );

		return checkdate( $parties[1], $parties[2], $parties[0] );
	}
}

if ( ! function_exists( 'massifs_historique_filtres_depuis_requete' ) ) {
	/**
	 * Analyse les paramètres bruts d'une requête en filtres normalisés.
	 *
	 * Le tableau reçu est BRUT : l'appelant a la charge de `wp_unslash()`. Un
	 * formulaire de filtrage en GET, en lecture seule, n'a aucun nonce à vérifier
	 * — le nonce du formulaire n'existe que pour l'export, qui déclenche une
	 * action nommée.
	 *
	 * @param array<string, mixed> $brut Paramètres bruts, clés `massif`, `auteur`,
	 *                                   `source`, `jour_debut`, `jour_fin`,
	 *                                   `enregistre_debut`, `enregistre_fin`,
	 *                                   `paged`, `par_page`.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_filtres_depuis_requete( array $brut ): array {
		$filtres = massifs_historique_filtres_vides();
		$rejets  = array();

		$massif = isset( $brut['massif'] ) && is_scalar( $brut['massif'] )
			? sanitize_key( (string) $brut['massif'] )
			: '';

		if ( '' !== $massif ) {
			// Massifs RETIRÉS inclus : un massif retiré du référentiel garde son
			// historique, et le filtrer resterait légitime.
			$connus = function_exists( 'massifs_codes' ) ? massifs_codes( true ) : array();

			// Repli de forme si le référentiel est indisponible : mieux vaut un
			// filtre approximatif qu'un écran qui refuse tout.
			$admis = array() === $connus
				? 1 === preg_match( '/^[a-z0-9_-]{1,64}$/', $massif )
				: in_array( $massif, $connus, true );

			$filtres['massif_code'] = $massif;

			if ( ! $admis ) {
				$rejets[] = 'massif';
			}
		}

		$auteur = isset( $brut['auteur'] ) && is_scalar( $brut['auteur'] ) ? absint( $brut['auteur'] ) : 0;

		if ( $auteur > 0 ) {
			$presents = function_exists( 'massifs_journal_auteurs' ) ? massifs_journal_auteurs() : array();

			$filtres['auteur_id'] = $auteur;

			if ( ! in_array( $auteur, $presents, true ) ) {
				$rejets[] = 'auteur';
			}
		}

		$source = isset( $brut['source'] ) && is_scalar( $brut['source'] )
			? sanitize_key( (string) $brut['source'] )
			: '';

		if ( '' !== $source ) {
			$sources = function_exists( 'massifs_sources_statut' ) ? massifs_sources_statut() : array();

			$filtres['source'] = $source;

			if ( ! in_array( $source, $sources, true ) ) {
				$rejets[] = 'source';
			}
		}

		foreach ( massifs_historique_champs_jour() as $champ ) {
			$valeur = isset( $brut[ $champ ] ) && is_scalar( $brut[ $champ ] )
				? trim( sanitize_text_field( (string) $brut[ $champ ] ) )
				: '';

			if ( '' === $valeur ) {
				continue;
			}

			// La valeur saisie est conservée même invalide : l'écran la réaffiche
			// pour que le gestionnaire voie ce qu'il a tapé plutôt qu'un champ vide.
			$filtres[ $champ ] = $valeur;

			if ( ! massifs_historique_jour_est_exploitable( $valeur ) ) {
				$rejets[] = $champ;
			}
		}

		// Intervalle inversé : les DEUX bornes sont conservées et appliquées. Le
		// résultat vide est alors la vérité, et l'écran l'annonce plutôt que de
		// « réparer » une saisie que nous ne savons pas interpréter.
		$intervalles = array(
			'jour_intervalle'       => array( 'jour_debut', 'jour_fin' ),
			'enregistre_intervalle' => array( 'enregistre_debut', 'enregistre_fin' ),
		);

		foreach ( $intervalles as $cle => $bornes ) {
			$debut = (string) $filtres[ $bornes[0] ];
			$fin   = (string) $filtres[ $bornes[1] ];

			if ( '' === $debut || '' === $fin
				|| in_array( $bornes[0], $rejets, true )
				|| in_array( $bornes[1], $rejets, true ) ) {
				continue;
			}

			if ( strcmp( $debut, $fin ) > 0 ) {
				$rejets[] = $cle;
			}
		}

		$paged = isset( $brut['paged'] ) && is_scalar( $brut['paged'] ) ? absint( $brut['paged'] ) : 0;

		if ( $paged >= 1 ) {
			$filtres['paged'] = $paged;
		} elseif ( isset( $brut['paged'] ) && '' !== $brut['paged'] ) {
			$rejets[] = 'paged';
		}

		$par_page = isset( $brut['par_page'] ) && is_scalar( $brut['par_page'] ) ? absint( $brut['par_page'] ) : 0;

		if ( in_array( $par_page, massifs_historique_par_page_admises(), true ) ) {
			$filtres['par_page'] = $par_page;
		} elseif ( isset( $brut['par_page'] ) && '' !== $brut['par_page'] ) {
			$rejets[] = 'par_page';
		}

		$filtres['rejets'] = array_values( array_unique( $rejets ) );
		$filtres['actifs'] = '' !== $filtres['massif_code']
			|| $filtres['auteur_id'] > 0
			|| '' !== $filtres['source']
			|| '' !== $filtres['jour_debut']
			|| '' !== $filtres['jour_fin']
			|| '' !== $filtres['enregistre_debut']
			|| '' !== $filtres['enregistre_fin'];

		return $filtres;
	}
}

if ( ! function_exists( 'massifs_historique_criteres' ) ) {
	/**
	 * Traduit des filtres en critères pour les fonctions de lecture du domaine.
	 *
	 * Un champ listé dans `rejets` n'est JAMAIS appliqué : afficher une valeur
	 * refusée est honnête, l'appliquer ne le serait pas. Les bornes d'un
	 * intervalle inversé, elles, sont appliquées telles quelles — c'est ce qui
	 * rend « aucun résultat possible » vrai.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 * @param int                  $id_max  Borne haute d'identifiant, `0` pour aucune.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_historique_criteres( array $filtres, int $id_max = 0 ): array {
		$rejets   = isset( $filtres['rejets'] ) && is_array( $filtres['rejets'] ) ? $filtres['rejets'] : array();
		$criteres = array();

		if ( '' !== (string) ( $filtres['massif_code'] ?? '' ) && ! in_array( 'massif', $rejets, true ) ) {
			$criteres['massif_code'] = (string) $filtres['massif_code'];
		}

		if ( (int) ( $filtres['auteur_id'] ?? 0 ) > 0 && ! in_array( 'auteur', $rejets, true ) ) {
			$criteres['auteur_id'] = (int) $filtres['auteur_id'];
		}

		if ( '' !== (string) ( $filtres['source'] ?? '' ) && ! in_array( 'source', $rejets, true ) ) {
			$criteres['source'] = (string) $filtres['source'];
		}

		foreach ( massifs_historique_champs_jour() as $champ ) {
			if ( '' !== (string) ( $filtres[ $champ ] ?? '' ) && ! in_array( $champ, $rejets, true ) ) {
				$criteres[ $champ ] = (string) $filtres[ $champ ];
			}
		}

		if ( $id_max > 0 ) {
			$criteres['id_max'] = $id_max;
		}

		$par_page = (int) ( $filtres['par_page'] ?? massifs_historique_par_page_defaut() );
		$paged    = max( 1, (int) ( $filtres['paged'] ?? 1 ) );

		$criteres['limite']   = $par_page;
		$criteres['decalage'] = ( $paged - 1 ) * $par_page;

		return $criteres;
	}
}

if ( ! function_exists( 'massifs_historique_parametres' ) ) {
	/**
	 * Filtres exprimés comme paramètres de requête, sous les noms du formulaire.
	 *
	 * @internal Détail partagé par `massifs_historique_url()` et la route REST.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 *
	 * @return array<string, int|string>
	 */
	function massifs_historique_parametres( array $filtres ): array {
		return array(
			'massif'           => (string) ( $filtres['massif_code'] ?? '' ),
			'auteur'           => (int) ( $filtres['auteur_id'] ?? 0 ),
			'source'           => (string) ( $filtres['source'] ?? '' ),
			'jour_debut'       => (string) ( $filtres['jour_debut'] ?? '' ),
			'jour_fin'         => (string) ( $filtres['jour_fin'] ?? '' ),
			'enregistre_debut' => (string) ( $filtres['enregistre_debut'] ?? '' ),
			'enregistre_fin'   => (string) ( $filtres['enregistre_fin'] ?? '' ),
		);
	}
}

if ( ! function_exists( 'massifs_historique_url' ) ) {
	/**
	 * Adresse de l'écran pour un jeu de filtres, avec remplacements ponctuels.
	 *
	 * Sert la pagination et le lien de réinitialisation : ce sont de simples
	 * ancres, donc fonctionnelles sans JavaScript. Une valeur de remplacement
	 * vide ou nulle RETIRE le paramètre.
	 *
	 * @param array<string, mixed> $filtres       Filtres issus de l'analyseur.
	 * @param array<string, mixed> $remplacements Paramètres à forcer ou à retirer.
	 */
	function massifs_historique_url( array $filtres, array $remplacements = array() ): string {
		$parametres = massifs_historique_parametres( $filtres );

		$paged    = max( 1, (int) ( $filtres['paged'] ?? 1 ) );
		$par_page = (int) ( $filtres['par_page'] ?? massifs_historique_par_page_defaut() );

		if ( $paged > 1 ) {
			$parametres['paged'] = $paged;
		}

		if ( $par_page !== massifs_historique_par_page_defaut() ) {
			$parametres['par_page'] = $par_page;
		}

		foreach ( $remplacements as $cle => $valeur ) {
			$parametres[ (string) $cle ] = $valeur;
		}

		$requete = array( 'page' => MASSIFS_HISTORIQUE_PAGE );

		foreach ( $parametres as $cle => $valeur ) {
			if ( null === $valeur || '' === $valeur || 0 === $valeur ) {
				continue;
			}

			$requete[ (string) $cle ] = $valeur;
		}

		return add_query_arg( $requete, admin_url( 'admin.php' ) );
	}
}

if ( ! function_exists( 'massifs_historique_champs_caches' ) ) {
	/**
	 * Champs cachés du formulaire de filtrage.
	 *
	 * Le formulaire porte DEUX boutons de soumission : « Filtrer », qui va sur
	 * `admin.php`, et « Exporter en CSV », qui va sur `admin-post.php` par
	 * l'attribut `formaction` — du HTML pur, sans une ligne de JavaScript. Les
	 * deux destinations partagent donc les mêmes champs : `page` est ignoré par
	 * `admin-post.php`, et le nonce est ignoré par `admin.php`.
	 *
	 * `paged` n'y figure JAMAIS : filtrer doit ramener à la première page, sans
	 * quoi le gestionnaire atterrirait sur une page 7 qui n'existe plus. Seul
	 * `par_page`, qui n'a pas de contrôle visible, est reconduit ici quand il
	 * s'écarte du défaut.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 *
	 * @return array<string, string>
	 */
	function massifs_historique_champs_caches( array $filtres ): array {
		$champs = array(
			'page'     => MASSIFS_HISTORIQUE_PAGE,
			'_wpnonce' => wp_create_nonce( MASSIFS_HISTORIQUE_ACTION_EXPORT ),
		);

		$par_page = (int) ( $filtres['par_page'] ?? massifs_historique_par_page_defaut() );

		if ( $par_page !== massifs_historique_par_page_defaut()
			&& in_array( $par_page, massifs_historique_par_page_admises(), true ) ) {
			$champs['par_page'] = (string) $par_page;
		}

		return $champs;
	}
}
