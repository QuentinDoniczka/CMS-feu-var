<?php
/**
 * Partie de gabarit — bannière de péremption (§8.3 de MASTER.md, §4.5 du brief,
 * ligne 12 de la DoD).
 *
 * Rend le bandeau d'alerte du §8.3 quand la donnée affichée est périmée, et
 * ZÉRO OCTET hors péremption — soit 364 jours sur 365. La bannière est de
 * niveau page, rendue une seule fois : elle S'AJOUTE aux statuts, elle ne les
 * masque, ne les filtre, ne les remplace et ne les conditionne jamais.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/bandeau-peremption', null, $args );
 *
 * La convention figée avec la chaîne #6 (functions.php l. 79-80) ne passe AUCUN
 * $args : la partie appelle elle-même l'API publique de l'extension. Les deux
 * clés ci-dessous n'existent que pour la recette et pour un appelant futur.
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   jour       string  `AAAA-MM-JJ`. Défaut : massifs_jour_courant().
 *                      Contrôle de FORME seul, jamais de calcul de date.
 *   fraicheur  array   Défaut : massifs_fraicheur( $jour ). Seule la clé
 *                      `perimee` est lue ; aucune autre, jamais.
 *
 * Gabarit pur, sans aucune déclaration : `load_template()` fait un `require` et
 * non un `require_once`, une partie incluse deux fois est donc ré-exécutée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arguments = isset( $args ) && is_array( $args ) ? $args : array();

// Contrôle de FORME seul : une chaîne mal formée passée au domaine lèverait une
// exception et blanchirait la page. Un contrôle de forme ne calcule aucune date.
$jour = null;

if ( isset( $arguments['jour'] ) ) {
	if ( is_string( $arguments['jour'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['jour'] ) ) {
		$jour = $arguments['jour'];
	} else {
		_doing_it_wrong( 'templates/parts/bandeau-peremption.php', 'La clé « jour » attend une chaîne AAAA-MM-JJ.', '0.1.0' );
	}
}

if ( null === $jour && function_exists( 'massifs_jour_courant' ) ) {
	$jour = massifs_jour_courant();
}

$fraicheur = isset( $arguments['fraicheur'] ) && is_array( $arguments['fraicheur'] )
	? $arguments['fraicheur']
	: array();

if ( array() === $fraicheur ) {
	// ÉCHEC FERMÉ, jamais ouvert, jamais silencieux : sans le domaine, rien ne
	// prouve que la donnée est périmée, et une bannière de péremption affichée à
	// tort est un mensonge sur la donnée. Son absence n'en est pas un : la règle
	// absolue du §4.2 du brief est portée par `etat_global === 'indisponible'`,
	// règle DISJOINTE de celle-ci (contrat #3, arbitrage A-5).
	if ( ! function_exists( 'massifs_fraicheur' ) ) {
		_doing_it_wrong( 'templates/parts/bandeau-peremption.php', 'massifs_fraicheur() est introuvable ; la bannière est omise.', '0.1.0' );

		return;
	}

	try {
		$fraicheur = massifs_fraicheur( $jour );
	} catch ( \InvalidArgumentException ) {
		_doing_it_wrong( 'templates/parts/bandeau-peremption.php', 'Le domaine a refusé le jour demandé ; la bannière est omise.', '0.1.0' );

		return;
	}
}

// Rupture de contrat détectée, et non repli silencieux : `perimee` est TOUJOURS
// présente et TOUJOURS booléenne (contrat #12, exigence B-2). Son absence ferait
// tomber la ligne 12 de la DoD sans un mot ; la journalisation est la seule
// chose qui l'empêche. Elle ne change rien au rendu, qui reste zéro octet.
if ( ! isset( $fraicheur['perimee'] ) || ! is_bool( $fraicheur['perimee'] ) ) {
	_doing_it_wrong( 'templates/parts/bandeau-peremption.php', 'La clé « perimee » de massifs_fraicheur() attend un booléen toujours présent.', '0.1.0' );
}

// Comparaison STRICTE : jamais de bannière sur une valeur seulement « vraie-ish ».
//
// `perimee` est l'UNIQUE réponse. La règle des 24 h n'est pas rejouée ici :
// `age_secondes` et `seuil_secondes` ne sont pas lus, et aucune fonction
// d'horodatage du domaine n'est appelée — le gabarit n'affiche aucune date,
// aucune heure, aucun âge, donc ne construit aucune date nue. Le nom même de
// cette fonction est tenu hors du fichier pour que la vérification par `grep`
// de l'invariant I-13 rende zéro. Corollaire gratuit : le cas
// `dernier_releve_le === null`, le plus périmé de tous, est traité par
// construction, cette clé n'étant jamais lue.
//
// `dispositif_actif` n'est pas lu non plus : le contrat #3 garantit que
// `perimee === true` implique `dispositif_actif === true`. Le combiner en `&&`
// serait recalculer côté thème une règle déjà appliquée par le serveur ;
// « sans effet hors saison » est tenu transitivement.
//
// Aucun `match()` : `perimee` n'est PAS un `etat`. Il n'y a pas de vocabulaire
// fermé à couvrir, donc pas d'`\UnhandledMatchError` à envelopper. C'est la
// seule partie du thème dans ce cas, et c'est correct.
$perimee = isset( $fraicheur['perimee'] ) && true === $fraicheur['perimee'];

if ( ! $perimee ) {
	return;
}

// Racine <div>, et rien d'autre. <aside> est TOUJOURS un landmark
// `complementary`, nommé ou non : polluer l'arbre de landmarks pour deux mots
// est ce que l'interdit 9 du contrat #9 refusait déjà. <section> nommée
// exigerait une chaîne de site inventée hors de la liste FERMÉE du §11.3 ;
// <section> anonyme est exposée « generic », donc identique au <div> pour huit
// octets de plus. Un `id` non consommé est de la dette, et un titre casserait
// le plan de titres entre le h1 de l'ardoise et le h2 de la légende.
//
// Aucun `role`, `aria-live`, `aria-atomic`, `aria-label` ni `tabindex` : le
// contenu est présent au premier octet du HTML. Une région live n'annonce que
// les mutations survenant APRÈS qu'elle est connue de l'API d'accessibilité ;
// posée sur du contenu déjà parsé, elle n'annonce rien et se comporte de façon
// erratique selon le moteur. `role="alert"` serait pire : il interromprait la
// lecture en cours pour deux mots, sans qu'aucune interaction ait eu lieu.
// Aucun nœud focusable n'est ajouté : la partie est inerte au clavier.
//
// Le sens est porté par le PREMIER MOT (« Donnée périmée. »), jamais par la
// couleur ni par une icône — preuve : le même chrome sert aux variantes qui
// disent tout autre chose.
//
// Aucune donnée serveur n'est émise : le texte est un littéral du gabarit. Il
// n'y a donc AUCUN esc_html()/esc_attr()/esc_url() à poser, et la partie HTML
// ne contient AUCUNE interpolation — propriété rare, vérifiable par revue, à
// préserver.
//
// Chaîne verbatim de MASTER.md §8.3, point final compris : aucun âge, aucune
// date, aucun lien, aucune seconde phrase. Le §11.3 est une liste FERMÉE des
// chaînes rédigées par le site et ne contient aucune chaîne de péremption ; y
// ajouter quoi que ce soit serait inventer une chaîne de site.
//
// Aucune règle CSS n'est nécessaire : `.bandeau-alerte--peremption` n'a aucun
// sélecteur propre, comme les trois variantes déjà en service. Tout le rendu
// vient de `.bandeau-alerte`, `.sur-sombre` et `.repere.repere--bloc`.
?>
<div class="bandeau-alerte bandeau-alerte--peremption sur-sombre repere repere--bloc">
<p class="bandeau-alerte__texte">Donnée périmée.</p>
</div>
