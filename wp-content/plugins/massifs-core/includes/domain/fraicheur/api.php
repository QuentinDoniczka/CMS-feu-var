<?php
/**
 * Fonctions publiques du domaine « fraîcheur ».
 *
 * AUCUNE FONCTION DE LECTURE NE RETOURNE `null` NI `false`.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Massifs\Domain\Fraicheur\Fraicheur;
use Massifs\Domain\Fraicheur\Horloge;
use Massifs\Domain\Fraicheur\Horodatage;
use Massifs\Domain\Fraicheur\RegistreReleves;
use Massifs\Domain\Fraicheur\Saison;
use Massifs\Domain\Statuts\Legende;

if ( ! function_exists( 'massifs_jour_courant' ) ) {
	/**
	 * Jour civil courant à Paris, `YYYY-MM-DD`.
	 *
	 * SEULE SOURCE LÉGITIME DU JOUR. Aucun consommateur ne calcule
	 * « aujourd'hui » lui-même : la stack tourne en UTC, et `current_time()`
	 * ferait basculer le jour à 2 h du matin heure de Paris.
	 */
	function massifs_jour_courant(): string {
		return Horloge::jour_courant();
	}
}

if ( ! function_exists( 'massifs_jour_suivant' ) ) {
	/**
	 * Jour civil suivant à Paris, `YYYY-MM-DD`.
	 */
	function massifs_jour_suivant(): string {
		return Horloge::jour_suivant();
	}
}

if ( ! function_exists( 'massifs_saison' ) ) {
	/**
	 * Activité du dispositif à une date, selon le CALENDRIER SEUL.
	 *
	 * La résolution d'un statut fait primer la donnée sur le calendrier : cette
	 * fonction ne dit pas « il n'y a pas de statut », elle dit « le calendrier
	 * place ce jour hors dispositif ».
	 *
	 * @param string|null $jour Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array<string, bool|string>
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé.
	 */
	function massifs_saison( ?string $jour = null ): array {
		$jour_demande = Horloge::jour_demande( $jour );

		return Saison::depuis_bornes( Legende::chargee()->bornes_saison() )
			->evaluer( $jour_demande )
			->en_tableau();
	}
}

if ( ! function_exists( 'massifs_fraicheur' ) ) {
	/**
	 * Fraîcheur des données pour un jour donné.
	 *
	 * `perimee` est une bannière, jamais un filtre : elle s'ajoute aux statuts
	 * affichés et n'en masque aucun.
	 *
	 * @param string|null $jour Jour `YYYY-MM-DD`, `null` pour aujourd'hui.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException Si le jour est mal formé.
	 */
	function massifs_fraicheur( ?string $jour = null ): array {
		$jour_demande = Horloge::jour_demande( $jour );
		$saison       = Saison::depuis_bornes( Legende::chargee()->bornes_saison() );

		return ( new Fraicheur( new RegistreReleves(), $saison ) )->evaluer( $jour_demande )->en_tableau();
	}
}

if ( ! function_exists( 'massifs_horodatage' ) ) {
	/**
	 * Met en forme un instant pour l'affichage, en heure de Paris.
	 *
	 * Le consommateur ne compose JAMAIS une date lui-même : format strict de
	 * MASTER.md §11.1 règle 6, noms de mois et de jours français en dur.
	 *
	 * @param string $instant_iso_utc Instant ISO 8601 UTC.
	 *
	 * @return array{iso: string, attr_datetime: string, date_longue: string, heure: string, date_courte: string}
	 *
	 * @throws InvalidArgumentException Si la chaîne n'est pas un instant valide.
	 */
	function massifs_horodatage( string $instant_iso_utc ): array {
		return Horodatage::formater( $instant_iso_utc );
	}
}

if ( ! function_exists( 'massifs_enregistrer_releve_reussi' ) ) {
	/**
	 * Enregistre un relevé RÉUSSI ET VALIDÉ d'une source externe.
	 *
	 * CETTE FONCTION NE VÉRIFIE AUCUNE CAPABILITY. L'authentification et
	 * l'autorisation appartiennent entièrement à l'appelant.
	 *
	 * À n'appeler qu'après un relevé réussi et validé : un échec n'écrit rien,
	 * sinon la fraîcheur mentirait — exactement ce que le §4.5 interdit.
	 *
	 * @param string      $source_cle      Clé de source, forme `/^[a-z0-9_-]{1,32}$/`.
	 * @param string|null $instant_iso_utc Instant du relevé, `null` pour maintenant.
	 *
	 * @return array{enregistre: bool, id: int|null, erreurs: list<string>}
	 */
	function massifs_enregistrer_releve_reussi( string $source_cle, ?string $instant_iso_utc = null ): array {
		$cle = RegistreReleves::normaliser_cle( $source_cle );

		if ( ! RegistreReleves::cle_est_valide( $cle ) ) {
			return array(
				'enregistre' => false,
				'id'         => null,
				'erreurs'    => array( 'source_invalide' ),
			);
		}

		if ( null === $instant_iso_utc ) {
			$instant = Horloge::maintenant();
		} else {
			try {
				$instant = Horloge::instant_depuis_chaine( $instant_iso_utc );
			} catch ( InvalidArgumentException ) {
				return array(
					'enregistre' => false,
					'id'         => null,
					'erreurs'    => array( 'instant_invalide' ),
				);
			}
		}

		( new RegistreReleves() )->enregistrer( $cle, Horloge::vers_iso_utc( $instant ) );

		return array(
			'enregistre' => true,
			'id'         => null,
			'erreurs'    => array(),
		);
	}
}
