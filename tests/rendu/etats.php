<?php
/**
 * Fabrique d'états observables pour la recette de rendu.
 *
 * Exécutée par `wp eval-file` dans le conteneur d'outillage, elle place la base
 * dans un état de départ CONNU, puis rend la main : c'est le navigateur qui
 * observe ensuite le site réel, en HTTP, depuis l'hôte.
 *
 *   wp eval-file /massifs-tests/rendu/etats.php absente
 *   wp eval-file /massifs-tests/rendu/etats.php jour-nominal
 *   wp eval-file /massifs-tests/rendu/etats.php veille-seule
 *   wp eval-file /massifs-tests/rendu/etats.php jour-complet 0
 *   wp eval-file /massifs-tests/rendu/etats.php jour-partiel 5 3
 *
 * Aucune source externe n'est contactée : les statuts sont écrits par la
 * fonction d'écriture publique du domaine, exactement comme le fera le portail.
 * Aucun état n'est hérité d'une exécution précédente — chaque mode commence par
 * une purge complète.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

// Pas de `declare(strict_types=1)` : `wp eval-file` évalue ce fichier, et une
// déclaration stricte n'est légale qu'en toute première instruction d'un script.

global $wpdb;

/**
 * Purge des statuts et des relevés. Aucun mode ne s'appuie sur l'état précédent.
 */
function massifs_recette_purger(): void {
	global $wpdb;

	delete_option( 'massifs_prefecture_etat' );
	delete_option( 'massifs_prefecture_snapshots' );
	delete_option( 'massifs_prefecture_reglages' );
	delete_option( 'massifs_dernier_releve' );

	$table = $wpdb->prefix . 'massifs_statuts';
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB

	wp_cache_flush();
}

/**
 * Écrit un jeu complet de statuts pour un jour donné.
 *
 * Les niveaux sont posés de façon déterministe (le premier massif sur cinq est
 * interdit) : le chiffre de l'ardoise et le nombre de lignes du tableau sont
 * donc prévisibles sans dépendre d'un aléa.
 *
 * @param string $jour Jour de validité `AAAA-MM-JJ`.
 *
 * @return array{autorises:int, total:int}
 */
function massifs_recette_publier_jour( string $jour ): array {
	$codes     = massifs_codes();
	$autorises = 0;
	$rang      = 0;

	foreach ( $codes as $code ) {
		$interdit = 0 === $rang % 5;
		$niveau   = $interdit ? 'interdit' : 'autorise';

		if ( ! $interdit ) {
			++$autorises;
		}

		massifs_enregistrer_statut(
			array(
				'massif_code'   => $code,
				'jour_validite' => $jour,
				'niveau_cle'    => $niveau,
				// Une ZAPEF sur deux : la colonne doit rester strictement vide
				// pour les autres, sans tiret ni mention d'absence.
				'zapef_cle'     => 0 === $rang % 2 ? $niveau : null,
				'source'        => 'saisie_manuelle',
				'auteur_id'     => 1,
			)
		);

		++$rang;
	}

	return array(
		'autorises' => $autorises,
		'total'     => count( $codes ),
	);
}

/**
 * Écrit un jeu de statuts pour aujourd'hui en choisissant COMBIEN de massifs
 * sont renseignés et combien d'entre eux sont autorisés.
 *
 * C'est la fabrique des journées de publication partielle (issue #26) : la
 * préfecture publie parfois une partie seulement des massifs, et l'ardoise doit
 * alors compter sur les massifs RENSEIGNÉS, pas sur le référentiel entier. Les
 * massifs sont pris dans l'ordre du référentiel — aucun aléa, aucune dépendance
 * à l'ordre d'exécution.
 *
 * @param string $jour       Jour de validité `AAAA-MM-JJ`.
 * @param int    $renseignes Nombre de massifs qui reçoivent un statut.
 * @param int    $autorises  Parmi eux, nombre de massifs d'accès autorisé.
 */
function massifs_recette_publier_selection( string $jour, int $renseignes, int $autorises ): void {
	$codes = massifs_codes();

	if ( $renseignes < 1 || $renseignes > count( $codes ) || $autorises < 0 || $autorises > $renseignes ) {
		fwrite( STDERR, "Sélection impossible : 1 <= renseignes <= total et 0 <= autorises <= renseignes.\n" );
		exit( 1 );
	}

	$rang = 0;

	foreach ( $codes as $code ) {
		if ( $rang >= $renseignes ) {
			break;
		}

		$niveau = $rang < $autorises ? 'autorise' : 'interdit';

		massifs_enregistrer_statut(
			array(
				'massif_code'   => $code,
				'jour_validite' => $jour,
				'niveau_cle'    => $niveau,
				'zapef_cle'     => 0 === $rang % 2 ? $niveau : null,
				'source'        => 'saisie_manuelle',
				'auteur_id'     => 1,
			)
		);

		++$rang;
	}
}

/**
 * Décrit l'état obtenu, tel que le DOMAINE le voit.
 *
 * La ligne rendue n'est pas ce que la fabrique a cru écrire : elle est relue
 * dans `massifs_synthese_du_jour()`. La recette de rendu compare donc le HTML
 * servi aux chiffres du serveur, jamais à une hypothèse du fichier de fixtures.
 *
 * @param string $mode Nom du mode.
 */
function massifs_recette_rapporter( string $mode ): void {
	$synthese = massifs_synthese_du_jour( massifs_codes(), null );

	printf(
		"ETAT %s etat=%s partiel=%d autorises=%d renseignes=%d sans_donnee=%d total=%d jour=%s\n",
		$mode,
		$synthese['etat_global'],
		true === $synthese['partiel'] ? 1 : 0,
		$synthese['par_niveau']['autorise'],
		$synthese['disponibles'],
		$synthese['sans_donnee'],
		$synthese['total'],
		$synthese['jour_validite']
	);
}

$mode = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : '';

switch ( $mode ) {
	case 'absente':
		// Aucune donnée valide pour aujourd'hui, aucun relevé : l'état que le
		// §4.2 du brief oblige à afficher comme « information non disponible ».
		massifs_recette_purger();
		echo "ETAT absente\n";
		break;

	case 'jour-nominal':
		massifs_recette_purger();
		$compte = massifs_recette_publier_jour( massifs_jour_courant() );
		massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z' ) );
		printf( "ETAT jour-nominal autorises=%d total=%d jour=%s\n", $compte['autorises'], $compte['total'], massifs_jour_courant() );
		break;

	case 'veille-seule':
		// Le piège du §4.2 : une donnée EXISTE, mais elle est datée d'hier. Elle
		// ne doit apparaître nulle part comme le statut du jour.
		massifs_recette_purger();
		$hier   = ( new DateTimeImmutable( massifs_jour_courant(), new DateTimeZone( 'Europe/Paris' ) ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$compte = massifs_recette_publier_jour( $hier );
		massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z', time() - 30 * HOUR_IN_SECONDS ) );
		printf( "ETAT veille-seule autorises=%d total=%d jour=%s\n", $compte['autorises'], $compte['total'], $hier );
		break;

	case 'jour-complet':
		// Journée COMPLÈTE paramétrée : les 25 massifs sont renseignés, et l'on
		// choisit combien sont autorisés. Sert les cas limites d'accord — 0, 1,
		// plusieurs — que `jour-nominal` (20 autorisés) ne peut pas produire.
		massifs_recette_purger();
		$autorises = isset( $args[1] ) ? (int) $args[1] : 0;
		massifs_recette_publier_selection( massifs_jour_courant(), count( massifs_codes() ), $autorises );
		massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z' ) );
		massifs_recette_rapporter( 'jour-complet' );
		break;

	case 'jour-partiel':
		// Journée de publication PARTIELLE (issue #26) : la préfecture n'a publié
		// qu'une partie des massifs. Le reste n'a pas de statut du jour — et ne
		// doit jamais être compté dans le dénominateur de l'ardoise.
		massifs_recette_purger();
		$renseignes = isset( $args[1] ) ? (int) $args[1] : 1;
		$autorises  = isset( $args[2] ) ? (int) $args[2] : 1;
		massifs_recette_publier_selection( massifs_jour_courant(), $renseignes, $autorises );
		massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z' ) );
		massifs_recette_rapporter( 'jour-partiel' );
		break;

	case 'deux-jours':
		// Aujourd'hui ET demain publiés. C'est le seul état où le sélecteur de
		// date de la carte (§5.2 du brief, contrat #7 A-1) est réellement
		// exerçable : sans lui, le bouton « Demain » reste `aria-disabled` et la
		// bascule ne peut pas être jouée. Les deux journées portent des niveaux
		// DIFFÉRENTS (le décalage d'un rang change quels massifs sont interdits),
		// sans quoi une bascule sans effet passerait pour une bascule réussie.
		massifs_recette_purger();
		$codes  = massifs_codes();
		$demain = massifs_jour_suivant();
		$rang   = 0;

		foreach ( $codes as $code ) {
			massifs_enregistrer_statut(
				array(
					'massif_code'   => $code,
					'jour_validite' => massifs_jour_courant(),
					'niveau_cle'    => 0 === $rang % 5 ? 'interdit' : 'autorise',
					'zapef_cle'     => 0 === $rang % 2 ? 'autorise' : null,
					'source'        => 'saisie_manuelle',
					'auteur_id'     => 1,
				)
			);
			massifs_enregistrer_statut(
				array(
					'massif_code'   => $code,
					'jour_validite' => $demain,
					'niveau_cle'    => 1 === $rang % 5 ? 'interdit' : 'autorise',
					'zapef_cle'     => null,
					'source'        => 'saisie_manuelle',
					'auteur_id'     => 1,
				)
			);
			++$rang;
		}

		massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z' ) );

		$courant_interdits = 0;
		$demain_interdits  = 0;
		foreach ( massifs_statuts_du_jour( $codes, massifs_jour_courant() ) as $statut ) {
			$courant_interdits += ( is_array( $statut['niveau'] ) && 'interdit' === $statut['niveau']['cle'] ) ? 1 : 0;
		}
		foreach ( massifs_statuts_du_jour( $codes, $demain ) as $statut ) {
			$demain_interdits += ( is_array( $statut['niveau'] ) && 'interdit' === $statut['niveau']['cle'] ) ? 1 : 0;
		}

		printf(
			"ETAT deux-jours total=%d jour=%s interdits_aujourdhui=%d demain=%s interdits_demain=%d\n",
			count( $codes ),
			massifs_jour_courant(),
			$courant_interdits,
			$demain,
			$demain_interdits
		);
		break;

	default:
		fwrite( STDERR, "Mode inconnu. Modes : absente | jour-nominal | veille-seule | jour-complet <autorises> | jour-partiel <renseignes> <autorises> | deux-jours\n" );
		exit( 1 );
}
