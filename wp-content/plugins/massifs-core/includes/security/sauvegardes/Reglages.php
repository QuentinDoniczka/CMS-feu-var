<?php
/**
 * Résolution UNIQUE des réglages du moteur de sauvegarde.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  AUCUN AUTRE FICHIER DE CE MODULE N'APPELLE `apply_filters()` SUR UN RÉGLAGE. │
 * │                                                                               │
 * │  UN FILTRE LU À DEUX ENDROITS EST UN FILTRE QUI FINIT PAR AVOIR DEUX          │
 * │  DÉFAUTS DIFFÉRENTS. ICI, LA DIVERGENCE NE SE VERRAIT PAS : ELLE PRODUIRAIT   │
 * │  UNE ARCHIVE ÉCRITE AVEC UN JEU DE RÉGLAGES ET RELUE AVEC UN AUTRE.           │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * POURQUOI UN FILTRE AU TYPE INATTENDU RETOMBE SUR LE DÉFAUT, SANS ERREUR
 *
 * Un mu-plugin mal écrit ne doit pas pouvoir empêcher une sauvegarde de tourner :
 * refuser de sauvegarder parce qu'un réglage est mal typé, c'est choisir « pas de
 * sauvegarde » plutôt que « sauvegarde avec les défauts ». Les bornes existent
 * pour la même raison — un `lignes_par_page` à 10 000 000 ferait tomber PHP en
 * mémoire au premier `SELECT`.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Les quatorze réglages du module, résolus, validés et normalisés.
 */
final class Reglages {

	/**
	 * Nom du répertoire d'archives, relatif à ce module.
	 */
	public const NOM_REPERTOIRE = 'archives';

	/**
	 * Répertoire d'archives.
	 *
	 * COUTURE DE SORTIE N° 1, ET C'EST SA RAISON D'ÊTRE. Le défaut vit sous la
	 * racine web, dans un arbre qu'un redéploiement écrase, et une archive contient
	 * des hachages de mots de passe et des secrets TOTP (arbitrage A-5 du contrat).
	 * Ce filtre est ce qui permet à la publication de sortir les archives de là
	 * sans réécrire une ligne de ce module. Voir README.md — c'est un compromis
	 * daté, jamais une bonne pratique.
	 *
	 * Chemin absolu, séparateurs POSIX, sans barre oblique finale. Aucune création
	 * de répertoire ici : cette méthode est une résolution, pas un effet de bord.
	 * `Archives::repertoire()` crée.
	 */
	public static function repertoire(): string {
		$defaut = rtrim( wp_normalize_path( __DIR__ . '/' . self::NOM_REPERTOIRE ), '/' );

		/**
		 * Filtre le répertoire de stockage des archives.
		 *
		 * @param string $repertoire Chemin absolu du répertoire d'archives.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_repertoire', $defaut );

		if ( ! is_string( $valeur ) ) {
			return $defaut;
		}

		$valeur = rtrim( wp_normalize_path( trim( $valeur ) ), '/' );

		// Un chemin relatif dépendrait du répertoire courant du processus, qui
		// n'est pas le même sous WP-CLI, sous Apache et sous un cron système.
		if ( '' === $valeur || ! path_is_absolute( $valeur ) ) {
			return $defaut;
		}

		return $valeur;
	}

	/**
	 * Nombre d'archives de sauvegarde conservées.
	 *
	 * 30, DÉFAUT OPPOSABLE (arbitrage A-10) : sans cron quotidien, « 30 jours » et
	 * « 30 archives » divergent, et seul le compte a un sens observable.
	 */
	public static function retention_nombre(): int {
		/**
		 * Filtre le nombre d'archives de sauvegarde conservées.
		 *
		 * @param int $nombre Nombre d'archives conservées.
		 */
		return self::entier( 'massifs_sauvegardes_retention_nombre', 30, 1, 10000 );
	}

	/**
	 * Âge maximal d'une archive, en jours. `0` désactive la rétention par âge.
	 */
	public static function retention_jours(): int {
		/**
		 * Filtre la rétention par âge des archives, en jours.
		 *
		 * @param int $jours Âge maximal en jours, `0` pour désactiver.
		 */
		return self::entier( 'massifs_sauvegardes_retention_jours', 0, 0, 3650 );
	}

	/**
	 * Nombre d'archives « avant restauration » conservées.
	 */
	public static function retention_filets(): int {
		/**
		 * Filtre le nombre de filets « avant restauration » conservés.
		 *
		 * @param int $nombre Nombre de filets conservés.
		 */
		return self::entier( 'massifs_sauvegardes_retention_filets', 5, 1, 1000 );
	}

	/**
	 * Tables exclues du dump, nommées SANS le préfixe.
	 *
	 * @return list<string>
	 */
	public static function tables_exclues(): array {
		/**
		 * Filtre les tables exclues du dump, nommées sans le préfixe.
		 *
		 * @param list<string> $tables Noms de tables sans préfixe.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_tables_exclues', array() );

		return self::liste_de_chaines( $valeur );
	}

	/**
	 * Lignes exclues du dump, par table.
	 *
	 * FORME NORMALISÉE : `array<table_sans_prefixe, array<colonne, list<motif LIKE>>>`.
	 *
	 * La forme abrégée `array<table, list<motif>>` — celle du défaut — est acceptée
	 * et la colonne est alors résolue contre les colonnes RÉELLES de la table
	 * (`option_name`, `meta_key`, `name`, dans cet ordre). Une abréviation dont
	 * aucune colonne candidate n'existe est reportée dans le manifeste sous
	 * `lignes_exclues_ignorees` : une exclusion qui ne s'applique pas doit être
	 * lisible dans l'archive, pas seulement supposée par le lecteur du code.
	 *
	 * LE DÉFAUT EST UNE RÈGLE MÉTIER, PAS DE LA PROPRETÉ. Restaurer un transient
	 * vieux de trois semaines réinjecte un état périmé sous les règles de fraîcheur
	 * du §4.5 — exactement le « statut périmé présenté comme courant » que le brief
	 * interdit en règle absolue.
	 *
	 * CONSÉQUENCE À CONNAÎTRE AVANT D'AJOUTER QUOI QUE CE SOIT ICI : une ligne
	 * exclue n'est pas seulement absente de l'archive, elle DISPARAÎT à la
	 * restauration, puisque celle-ci commence par `DROP TABLE`.
	 *
	 * @return array<string, mixed>
	 */
	public static function lignes_exclues(): array {
		$defaut = array(
			'options' => array( '_transient_%', '_site_transient_%' ),
		);

		/**
		 * Filtre les lignes exclues du dump, par table.
		 *
		 * @param array<string, mixed> $exclusions Motifs `LIKE` par table sans préfixe.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_lignes_exclues', $defaut );

		if ( ! is_array( $valeur ) ) {
			return $defaut;
		}

		$propres = array();

		foreach ( $valeur as $table => $motifs ) {
			if ( ! is_string( $table ) || '' === trim( $table ) ) {
				continue;
			}

			$table = strtolower( trim( $table ) );

			if ( is_array( $motifs ) && array() !== $motifs && ! array_is_list( $motifs ) ) {
				$explicite = array();

				foreach ( $motifs as $colonne => $liste ) {
					if ( ! is_string( $colonne ) || '' === trim( $colonne ) ) {
						continue;
					}

					$liste = self::liste_de_chaines( $liste );

					if ( array() !== $liste ) {
						$explicite[ trim( $colonne ) ] = $liste;
					}
				}

				if ( array() !== $explicite ) {
					$propres[ $table ] = $explicite;
				}

				continue;
			}

			$liste = self::liste_de_chaines( $motifs );

			if ( array() !== $liste ) {
				$propres[ $table ] = $liste;
			}
		}

		// ┌────────────────────────────────────────────────────────────────────────┐
		// │  VIDE VOULU ET VIDE ACCIDENTEL SONT DEUX FAITS DIFFÉRENTS.             │
		// │                                                                         │
		// │  UN FILTRE QUI REND UN TABLEAU NON VIDE DONT PLUS RIEN NE SURVIT À LA   │
		// │  NORMALISATION EST MALFORMÉ : SON INTENTION EST INCONNUE, ET LE PRENDRE │
		// │  POUR « AUCUNE EXCLUSION » RÉINTÉGRERAIT `_transient_%` ET              │
		// │  `_site_transient_%` AU DUMP, EN SILENCE. C'EST LA GARANTIE N° 11 DU    │
		// │  CONTRAT #16, ET CE N'EST PAS DE LA PROPRETÉ : RESTAURER UN TRANSIENT   │
		// │  VIEUX DE TROIS SEMAINES RÉINJECTE UN ÉTAT PÉRIMÉ SOUS LES RÈGLES DE    │
		// │  FRAÎCHEUR DU §4.5 — EXACTEMENT LE « STATUT PÉRIMÉ PRÉSENTÉ COMME       │
		// │  COURANT » QUE LE BRIEF INTERDIT EN RÈGLE ABSOLUE.                      │
		// │                                                                         │
		// │  LE CAS `array()` EXPLICITE RESTE HONORÉ, ET CE N'EST PAS UN OUBLI :    │
		// │  C'EST LE SEUL LEVIER DONT DISPOSE UN EXPLOITANT POUR DÉSACTIVER TOUTE  │
		// │  EXCLUSION EN CONNAISSANCE DE CAUSE. NE PAS « CORRIGER » EN RETOMBANT   │
		// │  SUR LE DÉFAUT DANS LES DEUX CAS : CELA LUI RETIRERAIT CE LEVIER SANS   │
		// │  RIEN PROTÉGER DE PLUS.                                                 │
		// │                                                                         │
		// │  LA DISTINCTION S'OBSERVE SUR `$valeur`, C'EST-À-DIRE AVANT             │
		// │  NORMALISATION. APRÈS, LES DEUX CAS SONT INDISCERNABLES.                │
		// └────────────────────────────────────────────────────────────────────────┘
		if ( array() === $propres && array() !== $valeur ) {
			return $defaut;
		}

		return $propres;
	}

	/**
	 * Racines de fichiers sauvegardées, `étiquette => chemin absolu`.
	 *
	 * L'ÉTIQUETTE N'EST PAS COSMÉTIQUE : c'est elle qui vit dans l'archive
	 * (`fichiers/<étiquette>/…`) et c'est par elle que la restauration retrouve où
	 * réécrire. Un chemin absolu stocké dans l'archive rendrait celle-ci
	 * irrestaurable sur une autre installation.
	 *
	 * @return array<string, string>
	 */
	public static function racines_fichiers(): array {
		$uploads = wp_get_upload_dir();
		$racine  = defined( 'MASSIFS_CORE_CHEMIN' ) ? (string) MASSIFS_CORE_CHEMIN : dirname( __DIR__, 3 ) . '/';

		$defaut = array(
			'uploads' => rtrim( wp_normalize_path( is_array( $uploads ) && isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) && '' !== $uploads['basedir'] ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads' ), '/' ),
			'data'    => rtrim( wp_normalize_path( $racine . 'data' ), '/' ),
		);

		/**
		 * Filtre les racines de fichiers sauvegardées.
		 *
		 * @param array<string, string> $racines Chemins absolus, indexés par étiquette.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_racines_fichiers', $defaut );

		if ( ! is_array( $valeur ) ) {
			return $defaut;
		}

		$propres = array();

		foreach ( $valeur as $etiquette => $chemin ) {
			if ( ! is_string( $etiquette ) || ! is_string( $chemin ) ) {
				continue;
			}

			$etiquette = self::etiquette( $etiquette );
			$chemin    = rtrim( wp_normalize_path( trim( $chemin ) ), '/' );

			if ( '' === $etiquette || '' === $chemin || ! path_is_absolute( $chemin ) ) {
				continue;
			}

			$propres[ $etiquette ] = $chemin;
		}

		return array() === $propres ? $defaut : $propres;
	}

	/**
	 * Motifs d'exclusion appliqués au chemin RELATIF dans l'archive.
	 *
	 * Le motif est une expression à jokers (`*`, `?`), comparée à
	 * `<étiquette>/<chemin relatif>`, séparateurs POSIX. `data/tuiles/*` est exclu
	 * pour DEUX raisons cumulées, et il faut les deux : la pyramide est volumineuse,
	 * et elle est commitée donc régénérable. Une seule des deux n'aurait pas suffi.
	 *
	 * @return list<string>
	 */
	public static function exclusions_fichiers(): array {
		$defaut = array(
			'data/tuiles/*',
			'uploads/massifs-tiles/*',
			'*/cache/*',
			'*.tmp',
		);

		/**
		 * Filtre les motifs d'exclusion du périmètre fichiers.
		 *
		 * @param list<string> $motifs Motifs à jokers appliqués au chemin relatif.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_exclusions_fichiers', $defaut );

		$propres = self::liste_de_chaines( $valeur );

		return array() === $propres ? $defaut : $propres;
	}

	/**
	 * Taille maximale d'un fichier embarqué, en octets.
	 *
	 * Un fichier plus gros est IGNORÉ et nommé dans le manifeste : il vaut mieux
	 * une archive dont on sait ce qu'elle omet qu'une archive qui n'aboutit pas.
	 */
	public static function taille_max_fichier(): int {
		/**
		 * Filtre la taille maximale d'un fichier embarqué, en octets.
		 *
		 * @param int $octets Taille maximale en octets.
		 */
		return self::entier( 'massifs_sauvegardes_taille_max_fichier', 67108864, 1024, 2147483647 );
	}

	/**
	 * Nombre de lignes lues par page.
	 */
	public static function lignes_par_page(): int {
		/**
		 * Filtre le nombre de lignes lues par page de dump.
		 *
		 * @param int $lignes Nombre de lignes par page.
		 */
		return self::entier( 'massifs_sauvegardes_lignes_par_page', 500, 1, 10000 );
	}

	/**
	 * Taille maximale d'une instruction `INSERT`, en octets.
	 */
	public static function octets_par_insert(): int {
		/**
		 * Filtre la taille maximale d'une instruction `INSERT`, en octets.
		 *
		 * @param int $octets Taille maximale en octets.
		 */
		return self::entier( 'massifs_sauvegardes_octets_par_insert', 1048576, 4096, 16777216 );
	}

	/**
	 * La planification interne est-elle armée ?
	 *
	 * DÉSARMÉE PAR DÉFAUT, ET CE N'EST PAS DE LA TIMIDITÉ : `DISABLE_WP_CRON` vaut
	 * `true` sur les deux services du projet. Un évènement planifié y serait un
	 * mensonge — inscrit, jamais exécuté, et lisible comme « les sauvegardes
	 * tournent ». La périodicité passe par un déclencheur hôte (couture S-8).
	 */
	public static function planification_active(): bool {
		/**
		 * Filtre l'armement de la planification interne des sauvegardes.
		 *
		 * @param bool $active La planification est-elle armée ?
		 */
		return true === apply_filters( 'massifs_sauvegardes_planification_active', false );
	}

	/**
	 * Heure locale de la sauvegarde planifiée, `HH:MM`.
	 */
	public static function heure_planifiee(): string {
		$defaut = '03:15';

		/**
		 * Filtre l'heure locale de la sauvegarde planifiée.
		 *
		 * @param string $heure Heure au format `HH:MM`.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_heure_planifiee', $defaut );

		if ( ! is_string( $valeur ) || 1 !== preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', trim( $valeur ) ) ) {
			return $defaut;
		}

		return trim( $valeur );
	}

	/**
	 * Destinataire de l'alerte d'échec.
	 *
	 * Chaîne vide si aucune adresse valide : l'appelant décide alors de ne rien
	 * envoyer plutôt que d'envoyer à une adresse inventée.
	 */
	public static function destinataire_alerte(): string {
		$defaut = (string) get_option( 'admin_email', '' );

		/**
		 * Filtre le destinataire de l'alerte d'échec de sauvegarde.
		 *
		 * @param string $adresse Adresse de courriel.
		 */
		$valeur = apply_filters( 'massifs_sauvegardes_destinataire_alerte', $defaut );

		if ( ! is_string( $valeur ) ) {
			$valeur = $defaut;
		}

		$adresse = sanitize_email( trim( $valeur ) );

		return is_email( $adresse ) ? $adresse : '';
	}

	/**
	 * Normalise une étiquette de racine de fichiers.
	 *
	 * L'étiquette devient un segment de chemin DANS l'archive : elle est réduite à
	 * un alphabet sûr avant d'y entrer, sinon une racine filtrée pourrait poser un
	 * `..` dans l'arborescence de l'archive.
	 *
	 * @param string $valeur Étiquette brute.
	 */
	private static function etiquette( string $valeur ): string {
		$propre = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( trim( $valeur ) ) );

		return substr( $propre, 0, 32 );
	}

	/**
	 * Lit un réglage entier, borné, avec repli sur le défaut.
	 *
	 * @param string $filtre  Nom du filtre.
	 * @param int    $defaut  Valeur par défaut.
	 * @param int    $minimum Borne basse incluse.
	 * @param int    $maximum Borne haute incluse.
	 */
	private static function entier( string $filtre, int $defaut, int $minimum, int $maximum ): int {
		$valeur = apply_filters( $filtre, $defaut );

		if ( is_string( $valeur ) && '' !== $valeur && ctype_digit( $valeur ) ) {
			$valeur = (int) $valeur;
		}

		if ( ! is_int( $valeur ) || $valeur < $minimum || $valeur > $maximum ) {
			return $defaut;
		}

		return $valeur;
	}

	/**
	 * Réduit une valeur filtrée à une liste de chaînes non vides.
	 *
	 * @param mixed $valeur Valeur filtrée.
	 *
	 * @return list<string>
	 */
	private static function liste_de_chaines( mixed $valeur ): array {
		if ( ! is_array( $valeur ) ) {
			return array();
		}

		$propres = array();

		foreach ( $valeur as $element ) {
			if ( ! is_string( $element ) ) {
				continue;
			}

			$element = trim( $element );

			if ( '' !== $element ) {
				$propres[] = $element;
			}
		}

		return array_values( array_unique( $propres ) );
	}
}
