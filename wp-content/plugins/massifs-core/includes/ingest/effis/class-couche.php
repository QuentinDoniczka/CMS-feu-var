<?php
/**
 * Projection de la couche : le seul chemin vers les octets stockés.
 *
 * LA PÉREMPTION S'APPLIQUE À LA LECTURE, ici, et jamais par effacement du
 * stockage. Effacer perdrait la trace d'exploitation et ferait dépendre une
 * règle de sécurité d'une tâche de nettoyage qui peut ne jamais tourner. La
 * garde est à la lecture parce que c'est le seul endroit qu'on ne peut pas
 * sauter.
 *
 * Au-delà de T, la couche bascule ENTIÈREMENT en `couche_effis_indisponible` :
 * il n'existe aucun état intermédiaire, aucune clé `perimee`, et c'est
 * délibéré. Pour un statut, une bannière de péremption s'ajoute sans masquer,
 * parce qu'un statut périmé reste la meilleure information disponible. Pour
 * cette couche, la péremption signifie que la fenêtre glissante est fausse et
 * qu'une zone survenue depuis serait ABSENTE : montrer la donnée sous un
 * avertissement laisserait lire « voici les zones parcourues par le feu » sous
 * une phrase que l'œil saute.
 *
 * @package Massifs\Ingest\Effis
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Effis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lecture de la couche des zones parcourues par le feu.
 */
final class Couche {

	/**
	 * États possibles. Énumération FERMÉE à trois valeurs.
	 *
	 * Toute quatrième valeur est un acte de contrat, jamais une surprise
	 * d'exécution.
	 */
	public const ETATS = array( 'zones_disponibles', 'aucune_zone', 'couche_effis_indisponible' );

	/**
	 * Projection complète de la couche.
	 *
	 * TOTALE : aucune exception, aucun `WP_Error`, aucun `null`, et toutes les
	 * clés toujours présentes.
	 *
	 * LE TEST DISCRIMINANT EST `etat`, JAMAIS `nombre`. `aucune_zone` et
	 * `couche_effis_indisponible` portent tous deux `nombre === 0` ; ce qui les
	 * sépare est `releve_le`, renseigné dans le premier et vide dans le second.
	 * « Vide parce que mesuré » porte une date de mesure ; « vide parce que
	 * muet » n'en porte aucune.
	 *
	 * @return array<string,mixed>
	 */
	public static function etat(): array {
		$indisponible = self::indisponible();

		// Coupe-circuit armé : la couche n'est alimentée par rien, elle se
		// déclare indisponible plutôt que de servir un relevé orphelin.
		if ( Settings::is_disabled() ) {
			return $indisponible;
		}

		$releve = ReleveRepository::get();

		if ( null === $releve ) {
			return $indisponible;
		}

		$instant = strtotime( (string) $releve['releve_le'] );

		if ( false === $instant ) {
			return $indisponible;
		}

		$peremption = Settings::peremption_secondes();
		$age        = time() - $instant;

		// Un relevé daté du futur est aussi peu exploitable qu'un relevé
		// périmé : dans les deux cas l'horloge ne permet pas d'affirmer la
		// fraîcheur.
		if ( $age < 0 || $age > $peremption ) {
			return $indisponible;
		}

		$zones = array_values( $releve['zones'] );

		return array(
			'etat'                => array() === $zones ? 'aucune_zone' : 'zones_disponibles',
			'zones'               => $zones,
			'nombre'              => count( $zones ),
			'releve_le'           => gmdate( Settings::FORMAT_ISO_UTC, $instant ),
			'expire_le'           => gmdate( Settings::FORMAT_ISO_UTC, $instant + $peremption ),
			'peremption_secondes' => $peremption,
			'fenetre_jours'       => Settings::fenetre_jours(),
			'surface_minimale_ha' => Settings::surface_minimale_ha(),
		);
	}

	/**
	 * Projection de l'état d'indisponibilité.
	 *
	 * `releve_le` et `expire_le` valent la chaîne vide, et c'est ce qui rend
	 * cet état structurellement distinguable de `aucune_zone`.
	 *
	 * @return array<string,mixed>
	 */
	private static function indisponible(): array {
		return array(
			'etat'                => 'couche_effis_indisponible',
			'zones'               => array(),
			'nombre'              => 0,
			'releve_le'           => '',
			'expire_le'           => '',
			'peremption_secondes' => Settings::peremption_secondes(),
			'fenetre_jours'       => Settings::fenetre_jours(),
			'surface_minimale_ha' => Settings::surface_minimale_ha(),
		);
	}

	/**
	 * Un relevé est-il stocké et vient-il de franchir la péremption ?
	 *
	 * Sert uniquement à l'alerte d'exploitation : c'est l'instant où la couche
	 * disparaît du site, le seul qui mérite un courriel. Ne rend aucun octet de
	 * la couche.
	 */
	public static function peremption_traversee(): bool {
		$releve = ReleveRepository::get();

		if ( null === $releve ) {
			return false;
		}

		$instant = strtotime( (string) $releve['releve_le'] );

		if ( false === $instant ) {
			return true;
		}

		return ( time() - $instant ) > Settings::peremption_secondes();
	}
}
