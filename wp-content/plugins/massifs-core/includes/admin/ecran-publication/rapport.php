<?php
/**
 * Compte rendu déposé par le handler POST et retiré par l'écran, par-dessus la
 * redirection PRG.
 *
 * POURQUOI UN DÉPÔT SERVEUR PLUTÔT QUE DES PARAMÈTRES D'URL
 *
 * Le compte rendu porte des listes de massifs, des clés d'erreur par ligne et les
 * choix de l'opérateur à réafficher après un refus. Les faire voyager en `GET`
 * produirait une URL manipulable : un tiers pourrait alors faire afficher
 * « 25 statuts publiés » à un gestionnaire qui n'a rien publié. Le seul jeton qui
 * circule est un identifiant opaque ; le contenu reste sur le serveur.
 *
 * Le dépôt est un transient — pas un cache de statut : rien de ce qui est ici
 * n'est une donnée de statut, tout est relu du domaine au rendu. Il est nominatif
 * (clé indexée sur l'utilisateur), de durée courte, et sa lecture ne le détruit
 * pas : le compte rendu est un bloc persistant et imprimable, un rechargement de
 * la page ne doit pas l'effacer.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_publication_rapport_vide' ) ) {
	/**
	 * Forme complète d'un compte rendu, toutes clés présentes.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_rapport_vide(): array {
		return array(
			'present'      => false,
			'utilisateur'  => 0,
			'jour_jeton'   => '',
			'ton'          => '',
			'niveaux'      => array(),
			'ecrits'       => array(),
			'inchanges'    => array(),
			'manquants'    => array(),
			'zapef_perdue' => array(),
			'refuses'      => array(),
			'erreurs'      => array(),
			'publie_le'    => '',
		);
	}
}

if ( ! function_exists( 'massifs_publication_duree_rapport' ) ) {
	/**
	 * Durée de vie d'un compte rendu.
	 *
	 * Assez longue pour survivre à une relecture et à une impression, assez courte
	 * pour qu'un compte rendu oublié n'encombre pas la base.
	 */
	function massifs_publication_duree_rapport(): int {
		return 15 * MINUTE_IN_SECONDS;
	}
}

if ( ! function_exists( 'massifs_publication_cle_rapport' ) ) {
	/**
	 * Clé de stockage d'un compte rendu.
	 *
	 * NOMINATIVE : le compte rendu d'un gestionnaire n'est jamais lisible par un
	 * autre, même en devinant son jeton.
	 *
	 * @param int    $utilisateur Compte propriétaire.
	 * @param string $jeton       Jeton opaque du compte rendu.
	 */
	function massifs_publication_cle_rapport( int $utilisateur, string $jeton ): string {
		return 'massifs_publication_' . $utilisateur . '_' . $jeton;
	}
}

if ( ! function_exists( 'massifs_publication_deposer_rapport' ) ) {
	/**
	 * Dépose un compte rendu et retourne son jeton.
	 *
	 * @param array<string, mixed> $rapport Compte rendu complet.
	 *
	 * @return string Jeton à joindre à la redirection, chaîne vide si le dépôt a échoué.
	 */
	function massifs_publication_deposer_rapport( array $rapport ): string {
		$utilisateur = get_current_user_id();

		if ( $utilisateur <= 0 ) {
			return '';
		}

		$jeton = sanitize_key( wp_generate_uuid4() );

		if ( '' === $jeton ) {
			return '';
		}

		$rapport['present']     = true;
		$rapport['utilisateur'] = $utilisateur;

		$depose = set_transient(
			massifs_publication_cle_rapport( $utilisateur, $jeton ),
			$rapport,
			massifs_publication_duree_rapport()
		);

		return false === $depose ? '' : $jeton;
	}
}

if ( ! function_exists( 'massifs_publication_lire_rapport' ) ) {
	/**
	 * Retire un compte rendu déposé, ou un compte rendu vide.
	 *
	 * Le compte rendu d'un autre compte n'est jamais servi, et une forme
	 * inattendue — dépôt d'une version antérieure du code, valeur altérée — est
	 * traitée comme une absence : mieux vaut ne rien annoncer qu'annoncer faux.
	 *
	 * @param string $jeton Jeton reçu de la redirection.
	 *
	 * @return array<string, mixed>
	 */
	function massifs_publication_lire_rapport( string $jeton ): array {
		$vide        = massifs_publication_rapport_vide();
		$jeton       = sanitize_key( $jeton );
		$utilisateur = get_current_user_id();

		if ( '' === $jeton || $utilisateur <= 0 ) {
			return $vide;
		}

		$depose = get_transient( massifs_publication_cle_rapport( $utilisateur, $jeton ) );

		if ( ! is_array( $depose ) ) {
			return $vide;
		}

		$rapport = array();

		foreach ( $vide as $cle => $defaut ) {
			$valeur = isset( $depose[ $cle ] ) ? $depose[ $cle ] : $defaut;

			if ( gettype( $valeur ) !== gettype( $defaut ) ) {
				return $vide;
			}

			$rapport[ $cle ] = $valeur;
		}

		if ( true !== $rapport['present'] || $utilisateur !== (int) $rapport['utilisateur'] ) {
			return $vide;
		}

		return $rapport;
	}
}
