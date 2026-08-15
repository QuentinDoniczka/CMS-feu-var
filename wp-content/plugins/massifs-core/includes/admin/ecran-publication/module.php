<?php
/**
 * Amorçage du module « admin/ecran-publication ».
 *
 * SEUL CHEMIN DÉCOUVERT PAR LE CHARGEUR de l'extension, et donc écrit EN DERNIER :
 * tant qu'il n'existe pas, le sous-arbre est invisible. Le créer avant ses
 * dépendances ferait inclure un répertoire à moitié écrit pendant que des chaînes
 * sœurs travaillent sur le même arbre, et un `ParseError` de fichier inclus n'est
 * pas rattrapable par `try/catch` : écran blanc sur tout le site. Chaque
 * `require_once` est donc gardé par `is_file()`.
 *
 * AUCUNE CLASSE, AUCUN `namespace`, AUCUN `use` DANS CE RÉPERTOIRE : l'autoloader
 * minuscule les segments de namespace et ne peut pas résoudre un répertoire à
 * tiret (`ecran-publication` deviendrait `ecranpublication`). Fonctions préfixées
 * uniquement, chargées ici en `require_once` eager.
 *
 * DEUX GROUPES DE FICHIERS, ET LA RAISON DE LES SÉPARER
 *
 * Le socle — contexte, chaînes, service d'écriture — est chargé sur TOUTE requête,
 * parce que la route REST du portail le consomme et qu'une requête REST n'est pas
 * une requête d'administration. Les fichiers d'écran ne sont chargés que dans
 * `wp-admin` : les inclure sur chaque page publique coûterait des inclusions que
 * personne n'appelle.
 *
 * INVERSION DE COUCHE ASSUMÉE : ce module charge celui de la route REST. Le
 * chargeur ne descend que d'un niveau, `includes/rest/portail/publication/module.php`
 * n'est donc jamais découvert, et `includes/rest/portail/module.php` — le chemin
 * naturel — est un fichier de niveau supérieur qui n'appartient pas à cette
 * chaîne. Condition de sortie : le jour où ce fichier de niveau existe et parcourt
 * ses sous-répertoires, le `require_once` final ci-dessous devient redondant et se
 * retire ; les gardes d'idempotence des deux amorces rendent la transition sans
 * risque.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'idempotence : le chargeur ne prend qu'une amorce par module, mais un
// second `require` depuis un outil ou un test ne doit rien redéclarer.
if ( defined( 'MASSIFS_ECRAN_PUBLICATION_VERSION' ) ) {
	return;
}

define( 'MASSIFS_ECRAN_PUBLICATION_VERSION', '1.0.0' );

foreach ( array( 'contexte.php', 'messages.php', 'service-publication.php' ) as $massifs_publication_fichier ) {
	$massifs_publication_chemin = __DIR__ . '/' . $massifs_publication_fichier;

	if ( is_file( $massifs_publication_chemin ) ) {
		require_once $massifs_publication_chemin;
	}
}

if ( is_admin() ) {
	// Ordre volontaire : le compte rendu et le modèle avant la page et le handler
	// qui les appellent, les gabarits en dernier. Chacun est gardé — les gabarits
	// appartiennent à une autre chaîne et peuvent manquer.
	$massifs_publication_ecran = array(
		'rapport.php',
		'modele-ecran.php',
		'page.php',
		'traitement-post.php',
		'assets.php',
		'gabarit-statut.php',
		'gabarit-ligne.php',
		'gabarit-recapitulatif.php',
		'gabarit-ecran.php',
	);

	foreach ( $massifs_publication_ecran as $massifs_publication_fichier ) {
		$massifs_publication_chemin = __DIR__ . '/' . $massifs_publication_fichier;

		if ( is_file( $massifs_publication_chemin ) ) {
			require_once $massifs_publication_chemin;
		}
	}

	unset( $massifs_publication_ecran );

	if ( function_exists( 'massifs_publication_enregistrer_menu' ) ) {
		add_action( 'admin_menu', 'massifs_publication_enregistrer_menu' );
	}

	// Une seule action, réservée aux comptes connectés : il n'existe pas de
	// variante `nopriv`, et il n'en existera pas — le portail n'écrit jamais pour
	// un anonyme.
	if ( function_exists( 'massifs_publication_traiter_post' ) ) {
		add_action( 'admin_post_massifs_publier_statuts', 'massifs_publication_traiter_post' );
	}
}

unset( $massifs_publication_fichier, $massifs_publication_chemin );

$massifs_publication_route = MASSIFS_CORE_INCLUDES . 'rest/portail/publication/module.php';

if ( is_file( $massifs_publication_route ) ) {
	require_once $massifs_publication_route;
}

unset( $massifs_publication_route );
