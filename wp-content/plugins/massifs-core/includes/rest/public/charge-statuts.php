<?php
/**
 * Assemblage de la charge utile publique des statuts du jour.
 *
 * CE FICHIER NE CONNAÎT RIEN À HTTP : ni requête, ni réponse, ni en-tête, ni
 * code de statut. Il traduit les formes du domaine en la forme gelée par le
 * contrat d'interface de l'issue #8, et rien d'autre.
 *
 * AUCUN `namespace`, AUCUNE classe, AUCUN `use` : `public` est un mot-clé
 * réservé de PHP, `namespace Massifs\Rest\Public;` serait une erreur d'analyse
 * fatale et non rattrapable, et l'autoloader de l'extension ne peut résoudre
 * aucun nom de namespace légal vers ce répertoire. Même posture que
 * `includes/domain/massifs/compat.php`.
 *
 * LES VALEURS SONT BRUTES ET NON ÉCHAPPÉES. Aucun `esc_*()` ni
 * `sanitize_text_field()` n'est appliqué à une valeur de charge utile : une
 * entité HTML dans du JSON est une corruption de donnée, pas une protection.
 * L'encodeur de cette frontière est `wp_json_encode`, appliqué une seule fois
 * par `WP_REST_Server` ; l'échappement HTML a lieu dans le thème, au rendu.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Les deux seules valeurs du vocabulaire fermé des états que ce module ÉCRIT
 * lui-même, quand il ramène un statut douteux à « information indisponible ».
 * `hors_saison` et `non_encore_publie` n'y figurent pas : ils sont relayés
 * verbatim depuis le domaine et ne sont jamais écrits ici.
 */
if ( ! defined( 'MASSIFS_REST_PUBLIC_ETAT_DISPONIBLE' ) ) {
	define( 'MASSIFS_REST_PUBLIC_ETAT_DISPONIBLE', 'disponible' );
}

if ( ! defined( 'MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE' ) ) {
	define( 'MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE', 'indisponible' );
}

if ( ! function_exists( 'massifs_rest_public_charge' ) ) {
	/**
	 * Charge utile complète d'une réponse `200`.
	 *
	 * Suppose que les onze fonctions de domaine requises existent et que le
	 * référentiel n'est pas vide : les deux gardes appartiennent au callback de
	 * la route, qui les joue avant d'appeler cette fonction.
	 *
	 * L'ordre des clés reproduit celui du contrat : il est stable, et l'ETag
	 * étant calculé sur la charge utile entière, une réorganisation changerait
	 * l'empreinte sans que la donnée ait bougé.
	 *
	 * @param string               $jour          Jour demandé, `YYYY-MM-DD`, déjà borné par le callback.
	 * @param string               $jour_relatif  `aujourd_hui` ou `demain`.
	 * @param array<string,string> $jours         Bornes résolues une seule fois par le callback.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws InvalidArgumentException Si le domaine refuse le jour demandé.
	 */
	function massifs_rest_public_charge( string $jour, string $jour_relatif, array $jours ): array {
		$referentiel = massifs_referentiel();
		$codes       = array_keys( $referentiel );

		// `massifs_synthese_du_jour()` rappelle `massifs_statuts_du_jour()` en
		// interne. Ce n'est pas une redondance à supprimer : la sémantique de la
		// synthèse — dénominateur, niveau le moins sévère, répartition par niveau —
		// appartient au domaine, et la recalculer ici serait un défaut.
		$statuts  = massifs_statuts_du_jour( $codes, $jour );
		$synthese = massifs_synthese_du_jour( $codes, $jour );

		$massifs = array();

		foreach ( $referentiel as $code => $ligne_referentiel ) {
			$code   = (string) $code;
			$statut = isset( $statuts[ $code ] ) && is_array( $statuts[ $code ] ) ? $statuts[ $code ] : array();

			// VERROU DU §4.2. Un statut dont le jour de validité n'est pas exactement
			// le jour demandé n'est pas un statut du jour : il est remplacé par
			// l'état explicite « information indisponible », jamais présenté comme
			// courant. Le domaine garantit déjà cette égalité ; la garde existe pour
			// que la garantie ne dépende pas d'une seule couche.
			$aligne = isset( $statut['etat'], $statut['jour_validite'] )
				&& is_string( $statut['etat'] )
				&& $jour === $statut['jour_validite'];

			if ( ! $aligne ) {
				$statut = array(
					'etat'                 => MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE,
					'jour_validite'        => $jour,
					'niveau'               => null,
					'zapef'                => null,
					'source'               => null,
					'publie_prefecture_le' => null,
				);
			}

			$massifs[] = massifs_rest_public_ligne_massif(
				$code,
				is_array( $ligne_referentiel ) ? $ligne_referentiel : array(),
				$statut
			);
		}

		$lacunes  = massifs_lacunes();
		$communes = isset( $lacunes['communes'] ) && is_array( $lacunes['communes'] ) ? $lacunes['communes'] : array();

		return array(
			'jour'              => $jour,
			'jour_relatif'      => $jour_relatif,
			'jours_disponibles' => array(
				'aujourd_hui' => isset( $jours['aujourd_hui'] ) && is_string( $jours['aujourd_hui'] ) ? $jours['aujourd_hui'] : '',
				'demain'      => isset( $jours['demain'] ) && is_string( $jours['demain'] ) ? $jours['demain'] : '',
			),
			'saison'            => massifs_rest_public_bloc_saison( massifs_saison( $jour ) ),
			'fraicheur'         => massifs_rest_public_bloc_fraicheur( massifs_fraicheur( $jour ) ),
			'synthese'          => massifs_rest_public_bloc_synthese( $synthese ),
			'massifs'           => $massifs,
			// Verbatim, sans aucun renommage de clé : le consommateur ne doit jamais
			// avoir à rapprocher deux vocabulaires pour nommer un niveau.
			'legende'           => massifs_legende(),
			'referentiel'       => array(
				'nombre'          => count( $referentiel ),
				// Drapeau de lacune. `inconnue` ne peut pas être relu comme
				// « aucune commune concernée », contrairement à une liste vide seule.
				'communes_statut' => isset( $communes['statut'] ) && is_string( $communes['statut'] ) ? $communes['statut'] : '',
			),
			'geometrie'         => massifs_rest_public_bloc_geometrie(),
			'emprise'           => massifs_rest_public_bloc_emprise(),
			'attribution'       => massifs_rest_public_bloc_attribution(),
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_ligne_massif' ) ) {
	/**
	 * Une entrée de la liste `massifs`.
	 *
	 * Chaque massif du référentiel produit une entrée, dans tous les états :
	 * une liste raccourcie se lirait « aucune restriction ».
	 *
	 * @param string              $code              Code du massif.
	 * @param array<string,mixed> $ligne_referentiel Ligne du référentiel.
	 * @param array<string,mixed> $statut            Statut résolu pour le jour demandé.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_ligne_massif( string $code, array $ligne_referentiel, array $statut ): array {
		$etat   = isset( $statut['etat'] ) && is_string( $statut['etat'] ) ? $statut['etat'] : MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE;
		$niveau = isset( $statut['niveau'] ) && is_array( $statut['niveau'] ) ? $statut['niveau'] : null;
		$zapef  = isset( $statut['zapef'] ) && is_array( $statut['zapef'] ) ? $statut['zapef'] : null;

		// Un état « disponible » sans niveau n'est pas un statut : il est ramené à
		// « indisponible » plutôt que servi avec un niveau vide, qu'un consommateur
		// lirait comme une absence de restriction.
		$disponible = MASSIFS_REST_PUBLIC_ETAT_DISPONIBLE === $etat && null !== $niveau;

		if ( ! $disponible ) {
			$etat   = MASSIFS_REST_PUBLIC_ETAT_DISPONIBLE === $etat ? MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE : $etat;
			$niveau = null;
			$zapef  = null;
		}

		$communes = array();

		if ( isset( $ligne_referentiel['communes'] ) && is_array( $ligne_referentiel['communes'] ) ) {
			foreach ( $ligne_referentiel['communes'] as $commune ) {
				if ( is_string( $commune ) ) {
					$communes[] = $commune;
				}
			}
		}

		$source = isset( $statut['source'] ) && is_string( $statut['source'] ) ? $statut['source'] : null;
		$publie = isset( $statut['publie_prefecture_le'] ) && is_string( $statut['publie_prefecture_le'] )
			? $statut['publie_prefecture_le']
			: null;

		return array(
			'code'                 => $code,
			'libelle'              => isset( $ligne_referentiel['libelle'] ) && is_string( $ligne_referentiel['libelle'] )
				? $ligne_referentiel['libelle']
				: $code,
			'communes'             => $communes,
			'etat'                 => $etat,
			'jour_validite'        => isset( $statut['jour_validite'] ) && is_string( $statut['jour_validite'] )
				? $statut['jour_validite']
				: '',
			// `null` littéral hors de l'état « disponible » : jamais `{}`, jamais
			// `{"cle": ""}`. Les deux formes voyagent verbatim depuis le domaine.
			'niveau'               => null === $niveau ? null : $niveau,
			'zapef'                => null === $zapef ? null : $zapef,
			'source'               => $disponible ? $source : null,
			'publie_prefecture_le' => $disponible ? $publie : null,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_fraicheur' ) ) {
	/**
	 * Bloc `fraicheur`.
	 *
	 * `age_secondes` et `evalue_le` sont écartés : ce sont des instants courants,
	 * dont la présence ferait changer la charge utile à chaque seconde et rendrait
	 * tout ETag décoratif. `age_secondes` reste dérivable de `dernier_releve_le`
	 * et de l'en-tête `Date`.
	 *
	 * Deux drapeaux se replient du côté prudent quand la valeur est absente ou
	 * malformée : `perimee` vers `true` — la bannière de péremption s'affiche —
	 * et `dispositif_actif` vers `true`, pour qu'aucune donnée dégradée ne se lise
	 * comme « aucune règle ne s'applique ».
	 *
	 * @param array<string,mixed> $fraicheur Retour de `massifs_fraicheur()`.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_fraicheur( array $fraicheur ): array {
		return array(
			'dernier_releve_le'     => isset( $fraicheur['dernier_releve_le'] ) && is_string( $fraicheur['dernier_releve_le'] )
				? $fraicheur['dernier_releve_le']
				: null,
			'dernier_releve_source' => isset( $fraicheur['dernier_releve_source'] ) && is_string( $fraicheur['dernier_releve_source'] )
				? $fraicheur['dernier_releve_source']
				: '',
			'seuil_secondes'        => isset( $fraicheur['seuil_secondes'] ) && is_int( $fraicheur['seuil_secondes'] )
				? $fraicheur['seuil_secondes']
				: 0,
			'perimee'               => isset( $fraicheur['perimee'] ) && is_bool( $fraicheur['perimee'] )
				? $fraicheur['perimee']
				: true,
			'publie_prefecture_le'  => isset( $fraicheur['publie_prefecture_le'] ) && is_string( $fraicheur['publie_prefecture_le'] )
				? $fraicheur['publie_prefecture_le']
				: null,
			'dispositif_actif'      => isset( $fraicheur['dispositif_actif'] ) && is_bool( $fraicheur['dispositif_actif'] )
				? $fraicheur['dispositif_actif']
				: true,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_saison' ) ) {
	/**
	 * Bloc `saison`.
	 *
	 * La clé `jour` du domaine est écartée : elle répète l'enveloppe.
	 * `prochaine_ouverture` est toujours une date, jamais `null` — c'est le
	 * domaine qui l'établit, jamais le consommateur qui la calcule.
	 *
	 * `confirmee` se replie sur `false` : un repli à `true` ferait présenter des
	 * bornes non vérifiées comme officielles.
	 *
	 * @param array<string,mixed> $saison Retour de `massifs_saison()`.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_saison( array $saison ): array {
		return array(
			'active'              => isset( $saison['active'] ) && is_bool( $saison['active'] ) ? $saison['active'] : true,
			'debut'               => isset( $saison['debut'] ) && is_string( $saison['debut'] ) ? $saison['debut'] : '',
			'fin'                 => isset( $saison['fin'] ) && is_string( $saison['fin'] ) ? $saison['fin'] : '',
			'prochaine_ouverture' => isset( $saison['prochaine_ouverture'] ) && is_string( $saison['prochaine_ouverture'] )
				? $saison['prochaine_ouverture']
				: '',
			'confirmee'           => isset( $saison['confirmee'] ) && is_bool( $saison['confirmee'] ) ? $saison['confirmee'] : false,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_synthese' ) ) {
	/**
	 * Bloc `synthese`.
	 *
	 * Recopié du domaine, jamais recalculé. La clé `jour_validite` est écartée :
	 * elle répète l'enveloppe.
	 *
	 * `par_niveau` est transtypé en objet pour que le JSON porte toujours un
	 * objet, y compris si la légende courante ne déclarait aucun niveau — un
	 * tableau PHP vide s'encoderait en `[]`, et le contrat annonce un objet.
	 *
	 * `partiel` se replie sur `true` : le dénominateur affiché est alors
	 * `disponibles`, jamais `total`.
	 *
	 * @param array<string,mixed> $synthese Retour de `massifs_synthese_du_jour()`.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_synthese( array $synthese ): array {
		$par_niveau = array();

		if ( isset( $synthese['par_niveau'] ) && is_array( $synthese['par_niveau'] ) ) {
			foreach ( $synthese['par_niveau'] as $cle => $compte ) {
				$par_niveau[ (string) $cle ] = is_int( $compte ) ? $compte : 0;
			}
		}

		return array(
			'etat_global'            => isset( $synthese['etat_global'] ) && is_string( $synthese['etat_global'] )
				? $synthese['etat_global']
				: MASSIFS_REST_PUBLIC_ETAT_INDISPONIBLE,
			'partiel'                => isset( $synthese['partiel'] ) && is_bool( $synthese['partiel'] ) ? $synthese['partiel'] : true,
			'total'                  => isset( $synthese['total'] ) && is_int( $synthese['total'] ) ? $synthese['total'] : 0,
			'disponibles'            => isset( $synthese['disponibles'] ) && is_int( $synthese['disponibles'] ) ? $synthese['disponibles'] : 0,
			'sans_donnee'            => isset( $synthese['sans_donnee'] ) && is_int( $synthese['sans_donnee'] ) ? $synthese['sans_donnee'] : 0,
			'par_niveau'             => (object) $par_niveau,
			'niveau_le_moins_severe' => isset( $synthese['niveau_le_moins_severe'] ) && is_string( $synthese['niveau_le_moins_severe'] )
				? $synthese['niveau_le_moins_severe']
				: null,
			'niveau_le_plus_severe'  => isset( $synthese['niveau_le_plus_severe'] ) && is_string( $synthese['niveau_le_plus_severe'] )
				? $synthese['niveau_le_plus_severe']
				: null,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_geometrie' ) ) {
	/**
	 * Bloc `geometrie` : un POINTEUR, jamais la géométrie elle-même.
	 *
	 * `massifs_geometrie()` est optionnelle — son absence ne justifie aucun
	 * `503` : elle laisse les lectures ci-dessous produire le bloc dégradé,
	 * toutes clés présentes, pour que le consommateur n'écrive jamais `isset()`.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_geometrie(): array {
		$geometrie = function_exists( 'massifs_geometrie' ) ? massifs_geometrie() : array();

		return array(
			'disponible' => isset( $geometrie['disponible'] ) && true === $geometrie['disponible'],
			'url'        => isset( $geometrie['url'] ) && is_string( $geometrie['url'] ) ? $geometrie['url'] : '',
			'version'    => isset( $geometrie['version'] ) && is_string( $geometrie['version'] ) ? $geometrie['version'] : '',
			'sha256'     => isset( $geometrie['sha256'] ) && is_string( $geometrie['sha256'] ) ? $geometrie['sha256'] : '',
			'octets'     => isset( $geometrie['octets'] ) && is_int( $geometrie['octets'] ) ? $geometrie['octets'] : 0,
			'format'     => isset( $geometrie['format'] ) && is_string( $geometrie['format'] ) ? $geometrie['format'] : '',
			'zoom_max'   => isset( $geometrie['zoom_max'] ) && is_int( $geometrie['zoom_max'] ) ? $geometrie['zoom_max'] : 0,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_emprise' ) ) {
	/**
	 * Bloc `emprise` : cadrage initial de la carte.
	 *
	 * `zoom_max` n'est pas repris ici : il voyage dans le bloc `geometrie`, et
	 * deux copies d'une même valeur finissent par diverger.
	 *
	 * Les coordonnées sont en EPSG:4326. La conversion vers l'ordre attendu par
	 * une bibliothèque cartographique appartient au consommateur.
	 *
	 * `massifs_emprise()` est optionnelle : absente, les deux clés restent
	 * présentes et valent `null`.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_emprise(): array {
		$emprise = function_exists( 'massifs_emprise' ) ? massifs_emprise() : array();

		return array(
			'bbox'   => isset( $emprise['bbox'] ) && is_array( $emprise['bbox'] ) ? $emprise['bbox'] : null,
			'centre' => isset( $emprise['centre'] ) && is_array( $emprise['centre'] ) ? $emprise['centre'] : null,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_bloc_attribution' ) ) {
	/**
	 * Bloc `attribution` : mentions de source imposées par le §9 du brief.
	 *
	 * `statuts` vient d'une fonction requise ; `perimetres` d'une fonction
	 * optionnelle, dégradée en trois chaînes vides. Publier une adresse de
	 * données sous Licence Ouverte oblige à en citer la source : les deux
	 * mentions voyagent ensemble, et le consommateur ne les rédige jamais.
	 *
	 * @return array<string,mixed>
	 */
	function massifs_rest_public_bloc_attribution(): array {
		$statuts = massifs_attribution_statuts();
		$relaye  = function_exists( 'massifs_attribution' ) ? massifs_attribution() : array();

		$perimetres = array(
			'phrase'       => isset( $relaye['phrase'] ) && is_string( $relaye['phrase'] ) ? $relaye['phrase'] : '',
			'lien_source'  => isset( $relaye['lien_source'] ) && is_string( $relaye['lien_source'] ) ? $relaye['lien_source'] : '',
			'lien_licence' => isset( $relaye['lien_licence'] ) && is_string( $relaye['lien_licence'] ) ? $relaye['lien_licence'] : '',
		);

		return array(
			'statuts'    => array(
				'texte'                => isset( $statuts['texte'] ) && is_string( $statuts['texte'] ) ? $statuts['texte'] : '',
				// Repli imposé par le §4.2 : un réutilisateur doit pouvoir relayer
				// cette adresse sans jamais l'écrire en dur.
				'carte_officielle_url' => massifs_rest_public_carte_officielle_url(),
				// MODÈLE d'adresse portant le jeton `{AAAAMMJJ}` : il est lié, jamais
				// récupéré ni re-servi.
				'bulletin_url_modele'  => isset( $statuts['bulletin_url_modele'] ) && is_string( $statuts['bulletin_url_modele'] )
					? $statuts['bulletin_url_modele']
					: '',
			),
			'perimetres' => $perimetres,
		);
	}
}

if ( ! function_exists( 'massifs_rest_public_carte_officielle_url' ) ) {
	/**
	 * Adresse de la carte officielle, ou chaîne vide si elle n'est pas obtenable.
	 *
	 * Isolée parce qu'elle voyage aussi dans les corps d'erreur, où le domaine
	 * peut être absent : c'est le repli du §4.2, et il ne doit dépendre d'aucune
	 * garde de disponibilité.
	 */
	function massifs_rest_public_carte_officielle_url(): string {
		if ( ! function_exists( 'massifs_attribution_statuts' ) ) {
			return '';
		}

		$statuts = massifs_attribution_statuts();

		return isset( $statuts['carte_officielle_url'] ) && is_string( $statuts['carte_officielle_url'] )
			? $statuts['carte_officielle_url']
			: '';
	}
}
