<?php
/**
 * Couplage — unique et confiné — au registre de fraîcheur du domaine.
 *
 * TOUT LE COUPLAGE DU MODULE AU DOMAINE « FRAÎCHEUR » TIENT DANS CE FICHIER.
 * Ailleurs, personne ne nomme `RegistreReleves` ni
 * `massifs_enregistrer_releve_reussi()`. Le jour où ce registre change de forme,
 * il y a un seul fichier à relire.
 *
 * CE MODULE N'UTILISE PAS `massifs_fraicheur()`, ET C'EST UNE DÉCISION
 *
 * Sa valeur ajoutée est `perimee`, calculée sur un seuil qui est une RÈGLE DES
 * STATUTS et n'existe, pour le danger météo, dans aucune source. L'employer
 * ferait de plus dépendre ce module de la saison, de la légende et de l'horloge
 * des statuts — trois choses qui n'ont rien à faire ici.
 *
 * L'honnêteté vient d'ailleurs, et plus fort : un instantané n'est courant que
 * POUR SON PROPRE JOUR DE VALIDITÉ. Aucun seuil, aucune bannière, aucune valeur
 * de la veille servie comme courante. `releve_le` voyage comme FAIT — « voici
 * quand nous avons parlé à la source » —, jamais comme autorisation d'afficher.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Ingest\Meteo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Écriture et lecture du dernier relevé réussi de la source météo.
 */
final class Releve {

	/**
	 * Clé de source dans le registre du domaine.
	 */
	public const SOURCE = 'meteo';

	/**
	 * Enregistre un relevé RÉUSSI.
	 *
	 * À N'APPELER QU'APRÈS un instantané validé ET enregistré. Jamais sur un
	 * 404, jamais sur un rejet de validation, jamais sur un échec réseau : la
	 * fraîcheur mentirait, et une fraîcheur qui ment est pire qu'une fraîcheur
	 * absente.
	 *
	 * @param string|null $instant_iso_utc Instant du relevé, `null` pour maintenant.
	 */
	public static function enregistrer( ?string $instant_iso_utc = null ): void {
		if ( ! function_exists( 'massifs_enregistrer_releve_reussi' ) ) {
			return;
		}

		massifs_enregistrer_releve_reussi( self::SOURCE, $instant_iso_utc );
	}

	/**
	 * Instant du dernier relevé réussi de la source météo, ISO 8601 UTC.
	 *
	 * Rend `null` si le domaine « fraîcheur » est absent : un module frère
	 * manquant produit une absence de fait, jamais une erreur.
	 */
	public static function dernier(): ?string {
		if ( ! class_exists( \Massifs\Domain\Fraicheur\RegistreReleves::class ) ) {
			return null;
		}

		try {
			$instant = ( new \Massifs\Domain\Fraicheur\RegistreReleves() )->dernier_releve( self::SOURCE );
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_string( $instant ) && '' !== $instant ? $instant : null;
	}
}
