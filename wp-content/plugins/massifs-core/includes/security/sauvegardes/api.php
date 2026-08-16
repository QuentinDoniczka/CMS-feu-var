<?php
/**
 * Fonctions de lecture du module « sauvegardes ».
 *
 * TOTALES : jamais `null`, jamais `WP_Error`, jamais d'exception, toutes les clés
 * toujours présentes. Elles existent pour rendre l'état des sauvegardes
 * VÉRIFIABLE sans navigation — un test ou une supervision les interroge.
 *
 * AUCUNE N'EST DESTINÉE AU THÈME, et aucune n'écrit quoi que ce soit. Lire l'état
 * des sauvegardes ne doit jamais pouvoir en déclencher une.
 *
 * L'ÉTAT `sauvegarde_absente` DU CONTRAT EST `existe === false`. Il n'est jamais
 * rendu à un visiteur : ce module ne produit aucun HTML public.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Massifs\Security\Sauvegardes\Archives;
use Massifs\Security\Sauvegardes\Manifeste;
use Massifs\Security\Sauvegardes\Reglages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_sauvegardes_derniere' ) ) {
	/**
	 * La sauvegarde la plus récente.
	 *
	 * Les archives « avant restauration » sont EXCLUES : ce sont des filets de
	 * sécurité posés par un geste manuel, pas des sauvegardes. Les compter
	 * laisserait croire qu'une sauvegarde récente existe alors qu'elle n'a été
	 * produite que par une restauration.
	 *
	 * `tables` et `lignes` sont des COMPTEURS, lus dans le manifeste de l'archive.
	 *
	 * @return array{existe:bool,nom:string,chemin:string,genere_le:string,octets:int,complet:bool,tables:int,lignes:int}
	 */
	function massifs_sauvegardes_derniere(): array {
		$absente = array(
			'existe'    => false,
			'nom'       => '',
			'chemin'    => '',
			'genere_le' => '',
			'octets'    => 0,
			'complet'   => false,
			'tables'    => 0,
			'lignes'    => 0,
		);

		if ( ! class_exists( Archives::class ) ) {
			return $absente;
		}

		try {
			$archives = Archives::lister( false );
		} catch ( Throwable ) {
			return $absente;
		}

		if ( array() === $archives ) {
			return $absente;
		}

		$derniere  = $archives[0];
		$manifeste = Manifeste::depuis_archive( $derniere['chemin'] );

		return array(
			'existe'    => true,
			'nom'       => (string) $derniere['nom'],
			'chemin'    => (string) $derniere['chemin'],
			'genere_le' => (string) $derniere['genere_le'],
			'octets'    => (int) $derniere['octets'],
			'complet'   => (bool) $derniere['complet'],
			'tables'    => is_wp_error( $manifeste ) || ! isset( $manifeste['tables'] ) || ! is_array( $manifeste['tables'] ) ? 0 : count( $manifeste['tables'] ),
			'lignes'    => is_wp_error( $manifeste ) ? 0 : (int) ( $manifeste['lignes'] ?? 0 ),
		);
	}
}

if ( ! function_exists( 'massifs_sauvegardes_lister' ) ) {
	/**
	 * Les sauvegardes présentes, de la plus récente à la plus ancienne.
	 *
	 * @return list<array{nom:string,genere_le:string,octets:int,complet:bool}>
	 */
	function massifs_sauvegardes_lister(): array {
		if ( ! class_exists( Archives::class ) ) {
			return array();
		}

		try {
			$archives = Archives::lister( false );
		} catch ( Throwable ) {
			return array();
		}

		$liste = array();

		foreach ( $archives as $archive ) {
			$liste[] = array(
				'nom'       => (string) $archive['nom'],
				'genere_le' => (string) $archive['genere_le'],
				'octets'    => (int) $archive['octets'],
				'complet'   => (bool) $archive['complet'],
			);
		}

		return $liste;
	}
}

if ( ! function_exists( 'massifs_sauvegardes_repertoire' ) ) {
	/**
	 * Répertoire de stockage des archives.
	 *
	 * Résolution SEULE : le répertoire n'est pas créé par cette lecture. Une
	 * fonction de lecture qui crée un répertoire est une fonction d'écriture qui
	 * n'a pas dit son nom.
	 */
	function massifs_sauvegardes_repertoire(): string {
		if ( ! class_exists( Reglages::class ) ) {
			return '';
		}

		try {
			return Reglages::repertoire();
		} catch ( Throwable ) {
			return '';
		}
	}
}
