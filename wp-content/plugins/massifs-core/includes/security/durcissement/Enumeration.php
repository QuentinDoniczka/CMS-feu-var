<?php
/**
 * Fermeture des quatre surfaces d'énumération de comptes.
 *
 * CE QU'ON DÉFEND : l'identifiant de connexion d'un gestionnaire. WordPress le
 * publie par quatre canaux distincts, tous anodins pris un à un, tous équivalents
 * pris ensemble — un attaquant n'a besoin que du plus faible. Fermer trois surfaces
 * sur quatre ne ferme rien.
 *
 * TOUTES CONDITIONNÉES À L'ANONYMAT, SAUF LE PLAN DE SITE (arbitrage A-4). En
 * session, l'éditeur de blocs et l'écran des comptes du cœur ont besoin de
 * `wp/v2/users` : la non-régression est opposable — `GET /wp-json/wp/v2/users`
 * AVEC cookie administrateur rend 200 peuplé, SANS cookie rend 404.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  JAMAIS `rest_authentication_errors` NI AUCUN FILTRE GLOBAL                   │
 * │  D'AUTHENTIFICATION REST POUR OBTENIR CE RÉSULTAT.                            │
 * │                                                                               │
 * │  C'EST LE RÉFLEXE NATUREL, ET IL EST FAUX : il court-circuite                 │
 * │  `WP_REST_Server::dispatch` pour TOUTE l'API et renverrait 401 sur            │
 * │  `GET /massifs/v1/statuts`, cassant le §5.4 du brief (données ouvertes) et    │
 * │  la carte publique qui la consomme. Voir l'encadré de `auth/GardeRest.php`.   │
 * │  On retire DEUX ROUTES NOMMÉES, et rien d'autre.                              │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * PORTÉE EXACTE DE `massifs_durcissement_fermer_enumeration`, PARCE QU'ELLE N'EST
 * PAS CELLE QU'ON SUPPOSE
 *
 * Ce réglage couvre les QUATRE SURFACES CONDITIONNÉES À L'ANONYME, et elles seules :
 * les routes REST `wp/v2/users`, `?author=N`, l'archive d'auteur, et le retrait de
 * `author_name`/`author_url` d'une réponse oEmbed. DEUX GESTES LUI ÉCHAPPENT, tous
 * deux inconditionnels par conception :
 *
 *  • le retrait du fournisseur de plan de site `users` — une composition qui
 *    varierait selon la session de l'appelant serait un défaut neuf, pas un
 *    durcissement (arbitrage A-4) ;
 *  • le retrait de la découverte oEmbed du `<head>`, posé à l'amorce
 *    (`module.php`) : à cet instant la session n'est pas résolue, et c'est une
 *    décision de sécurité autonome, du même registre que le `rsd_link` de
 *    `auth/module.php`.
 *
 * Cette précision n'est pas de la pédanterie : qui poserait le réglage à `false`
 * pour diagnostiquer un problème constaterait que ces deux gestes tiennent
 * toujours, et chercherait la cause au mauvais endroit.
 *
 * RESTE OUVERT, DIT POUR QUE PERSONNE NE LE REDÉCOUVRE : le flux RSS expose
 * `<dc:creator>` avec le NOM AFFICHÉ, jamais l'identifiant de connexion. Ce n'est
 * pas de l'énumération de comptes au sens du §9.
 *
 * @package Massifs\Security\Durcissement
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Durcissement;

use WP;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Neutralisation des canaux qui publient l'identifiant d'un compte.
 */
final class Enumeration {

	/**
	 * Routes REST du cœur retirées à l'appelant anonyme.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  CES DEUX LITTÉRAUX SONT CONTRACTUELS. JAMAIS DE FILTRAGE PAR PRÉFIXE.   │
	 * │                                                                           │
	 * │  Un `str_starts_with( $route, '/wp/v2/users' )` emporterait               │
	 * │  `/wp/v2/users/me`, dont l'ÉDITEUR DE BLOCS dépend : l'administration     │
	 * │  cesserait de fonctionner, et pour un anonyme `/me` rend déjà 401. Le     │
	 * │  gain serait nul, le coût total.                                          │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * @var list<string>
	 */
	private const ROUTES_UTILISATEURS = array(
		'/wp/v2/users',
		'/wp/v2/users/(?P<id>[\d]+)',
	);

	/**
	 * Retire les routes d'utilisateurs du cœur pour l'appelant anonyme.
	 *
	 * `rest_endpoints` se déclenche après résolution de la session :
	 * `is_user_logged_in()` y est fiable, ce qui n'est pas vrai partout.
	 *
	 * Résultat pour l'anonyme : 404 `rest_no_route` — la route n'existe pas, plutôt
	 * que 401 « elle existe mais pas pour vous », qui confirmerait sa présence.
	 *
	 * @param array<string, mixed> $routes Routes déclarées.
	 *
	 * @return array<string, mixed>
	 */
	public static function retirer_routes_utilisateurs( array $routes ): array {
		if ( ! self::fermeture_active() ) {
			return $routes;
		}

		foreach ( self::ROUTES_UTILISATEURS as $route ) {
			unset( $routes[ $route ] );
		}

		return $routes;
	}

	/**
	 * Neutralise `?author=N` et `/author/<slug>/` dès l'analyse de la requête.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  PRIORITÉ 1 SUR `parse_request` : PORTEUSE, PAS DÉCORATIVE.              │
	 * │                                                                           │
	 * │  La fuite de `?author=N` N'EST PAS DANS LE CORPS DE LA PAGE. C'est        │
	 * │  `redirect_canonical` (sur `template_redirect`, priorité 10) qui émet un  │
	 * │  301 avec `Location: /author/<identifiant-de-connexion>/`. Un test qui ne │
	 * │  lit que le HTML final ne verrait RIEN. Retirer la variable de requête    │
	 * │  AVANT lui est ce qui ferme la surface : après lui, l'en-tête est parti.  │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * `error => '404'` plutôt qu'un simple retrait des variables : sans lui, la
	 * requête retomberait sur la page d'accueil, servie en 200 sous une URL
	 * d'auteur. `WP_Query::get_posts()` honore cette variable et appelle
	 * `set_404()`.
	 *
	 * @param WP $wp Environnement de requête, modifié en place.
	 */
	public static function neutraliser_auteur( WP $wp ): void {
		if ( ! self::fermeture_active() ) {
			return;
		}

		$porte_un_auteur = isset( $wp->query_vars['author'] ) || isset( $wp->query_vars['author_name'] );

		if ( ! $porte_un_auteur ) {
			return;
		}

		unset( $wp->query_vars['author'], $wp->query_vars['author_name'] );

		$wp->query_vars['error'] = '404';
	}

	/**
	 * Ceinture : coupe une archive d'auteur qui aurait survécu à l'analyse.
	 *
	 * PRIORITÉ 0, donc encore avant `redirect_canonical`. Ce rappel ne mord que si
	 * la requête est TOUJOURS une archive d'auteur à ce stade — c'est-à-dire si
	 * `neutraliser_auteur()` n'a pas été atteint, ou si un tiers a réintroduit la
	 * variable après lui. Sur le chemin nominal il ne fait rien, et c'est bien le
	 * signe qu'il est une ceinture.
	 */
	public static function couper_archive_auteur(): void {
		if ( ! self::fermeture_active() || ! is_author() ) {
			return;
		}

		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Retire le fournisseur d'utilisateurs du plan de site.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  SEULE SURFACE INCONDITIONNELLE, ET C'EST MOTIVÉ (A-4).                  │
	 * │                                                                           │
	 * │  Les fournisseurs sont enregistrés une fois par requête, sur `init`.      │
	 * │  Conditionner à `! is_user_logged_in()` produirait un PLAN DE SITE DONT   │
	 * │  LA COMPOSITION DÉPEND DE LA SESSION DE L'APPELANT, alors qu'il est       │
	 * │  public par définition — et il serait mis en cache dans cet état. Ce      │
	 * │  serait un défaut neuf, pas un durcissement.                              │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * Le réglage `massifs_durcissement_fermer_enumeration` reste honoré ici : ce
	 * qu'il ne conditionne pas, c'est la SESSION, pas ce geste. Voir la portée
	 * exacte du réglage en tête de fichier — le retrait de la découverte oEmbed,
	 * lui, lui échappe entièrement.
	 *
	 * @param mixed  $fournisseur Fournisseur proposé.
	 * @param string $nom         Nom du fournisseur.
	 *
	 * @return mixed
	 */
	public static function retirer_fournisseur_utilisateurs( mixed $fournisseur, string $nom ): mixed {
		if ( ! Politique::fermer_enumeration() ) {
			return $fournisseur;
		}

		return 'users' === $nom ? false : $fournisseur;
	}

	/**
	 * Retire l'auteur d'une réponse oEmbed.
	 *
	 * `author_name` et `author_url` republient exactement ce que les trois autres
	 * surfaces viennent de fermer, et `author_url` porte le slug d'auteur.
	 *
	 * Conditionné à l'anonymat comme les autres réponses de requête — à la
	 * différence du plan de site, cette réponse est calculée à chaque appel et non
	 * composée une fois sur `init`.
	 *
	 * @param mixed $donnees Données de la réponse oEmbed.
	 * @param mixed $publication Publication concernée, non utilisée.
	 *
	 * @return mixed
	 */
	public static function depouiller_oembed( mixed $donnees, mixed $publication = null ): mixed {
		unset( $publication );

		if ( ! is_array( $donnees ) || ! self::fermeture_active() ) {
			return $donnees;
		}

		unset( $donnees['author_name'], $donnees['author_url'] );

		return $donnees;
	}

	/**
	 * La fermeture s'applique-t-elle à cette requête ?
	 *
	 * Réglage ET anonymat, en un seul endroit : trois surfaces posaient la même
	 * paire de conditions, et deux copies auraient divergé au premier ajustement.
	 */
	private static function fermeture_active(): bool {
		return Politique::fermer_enumeration() && ! is_user_logged_in();
	}
}
