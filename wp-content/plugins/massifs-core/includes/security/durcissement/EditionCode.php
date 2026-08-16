<?php
/**
 * Interdiction de l'édition de code depuis l'administration.
 *
 * POURQUOI CE FILTRE ALORS QUE LA CONSTANTE EXISTE (arbitrage A-7)
 *
 * `DISALLOW_FILE_EDIT` est évaluée par `map_meta_cap()` du cœur, et l'extension est
 * chargée bien avant `admin_menu` : la constante fonctionne. Ce filtre est le
 * DOUBLON DÉFENSIF, et il ne fait pas double emploi — il reste vrai le jour où
 * `wp-config.php`, un correctif d'hébergeur ou une extension tierce définit
 * `DISALLOW_FILE_EDIT` à `false`. Deux mécanismes indépendants, et
 * `massifs_durcissement_politique_mises_a_jour()` les rapporte séparément pour que
 * la chute de l'un reste diagnosticable.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  INVARIANT : POUR TOUTE CAPACITÉ NON RECONNUE, RENDRE `$caps` INCHANGÉ.       │
 * │                                                                               │
 * │  JAMAIS `do_not_allow` par défaut. `map_meta_cap` est traversé par CHAQUE      │
 * │  contrôle de droits du site — un refus par défaut ici verrouillerait           │
 * │  l'administration entière, y compris le portail et les comptes.                │
 * │                                                                               │
 * │  `Roles\Comptes::proteger_meta_caps` est abonné au MÊME crochet à la MÊME      │
 * │  priorité. Les deux tiennent ensemble parce que leurs jeux de capacités sont   │
 * │  DISJOINTS (`edit_files`/`edit_plugins`/`edit_themes` ici,                     │
 * │  `delete_user`/`remove_user` là-bas) ET que chacun respecte cet invariant.     │
 * │  Élargir ce jeu de capacités sans vérifier l'autre casserait la propriété.     │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * HORS PÉRIMÈTRE, ET ASSUMÉ : l'installation et la mise à jour d'extensions
 * restent permises. Les fermer demanderait `DISALLOW_FILE_MODS`, qui tuerait les
 * mises à jour mineures automatiques exigées par la même issue (A-6).
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Durcissement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fermeture des éditeurs de fichiers de l'administration.
 */
final class EditionCode {

	/**
	 * Capacités qui ouvrent un éditeur de code dans `wp-admin`.
	 *
	 * Exactement les trois que `map_meta_cap()` du cœur traite dans la même branche
	 * que `DISALLOW_FILE_EDIT` : le doublon couvre le même périmètre que la
	 * constante, ni plus — un périmètre plus large serait un durcissement non
	 * décidé — ni moins.
	 *
	 * @var list<string>
	 */
	private const CAPACITES = array( 'edit_files', 'edit_plugins', 'edit_themes' );

	/**
	 * Retire les capacités d'édition de code.
	 *
	 * @param array<int, string> $caps    Capacités primitives requises.
	 * @param string             $cap     Méta-capacité demandée.
	 * @param int                $user_id Compte demandeur, non utilisé.
	 * @param array<int, mixed>  $args    Arguments contextuels, non utilisés.
	 *
	 * @return array<int, string>
	 */
	public static function interdire_edition_de_code( array $caps, string $cap, int $user_id, array $args ): array {
		unset( $user_id, $args );

		if ( ! in_array( $cap, self::CAPACITES, true ) ) {
			return $caps;
		}

		if ( ! Politique::interdire_edition_code() ) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}
}
