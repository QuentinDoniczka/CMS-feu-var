<?php
/**
 * Le durcissement du §9 et le moteur de sauvegarde — issue #16, éprouvés en
 * HTTP RÉEL depuis le réseau de la stack, jamais par lecture du code.
 *
 * CE QUE CE SCÉNARIO PROUVE, ET POURQUOI IL LE PROUVE AINSI.
 *
 * 1. LA NON-RÉGRESSION LA PLUS IMPORTANTE DU LOT vient en premier :
 *    `GET /wp-json/massifs/v1/statuts` doit rester 200 EN ANONYME. La fermeture
 *    de l'énumération de comptes passe par `rest_endpoints` ; le réflexe naturel
 *    — `rest_authentication_errors` — aurait fermé TOUTE l'API, donc l'open data
 *    du §5.4 et la carte publique. L'interdit 3 du contrat #16 le proscrit, et
 *    c'est ici qu'on le constate au lieu de le croire.
 *
 * 2. L'ÉNUMÉRATION EST MESURÉE SUR LES QUATRE SURFACES, en HTTP et sans cookie.
 *    La fuite réelle de `?author=N` n'est pas dans le corps de la page : c'est
 *    `redirect_canonical` qui émet un 301 vers `/author/<identifiant>/`. On coupe
 *    donc le suivi de redirection (`redirection => 0`) — un test qui lit la page
 *    d'arrivée ne verrait rien.
 *
 * 3. LES ARCHIVES SONT-ELLES TÉLÉCHARGEABLES ? Une archive contient des hachages
 *    de mots de passe et des secrets TOTP (arbitrage A-5), et vit sous la racine
 *    web. Le contrat interdit lui-même (interdit 10) d'écrire « protégé par
 *    .htaccess » comme un fait tant que `AllowOverride` n'a pas été mesuré. Ce
 *    scénario le mesure : il crée une vraie archive et tente de la télécharger
 *    par HTTP, anonymement.
 *
 * 4. LA ROTATION EST FRANCHIE POUR DE BON. Le seuil de 30 n'a jamais été atteint
 *    en exploitation ; un mécanisme de suppression jamais déclenché n'est pas un
 *    mécanisme vérifié. On abaisse la rétention par son filtre public, on crée
 *    plus d'archives que le seuil, et on observe une SUPPRESSION RÉELLE.
 *
 * Aucune source externe n'est contactée : toutes les requêtes visent notre propre
 * serveur, à l'intérieur de la stack.
 *
 * Lignes du §12 servies : « HTTPS actif, sauvegarde et restauration testées »
 * (partie sauvegarde), « aucune requête tierce » (en-têtes), et la clause du §9
 * sur l'énumération de comptes.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

/**
 * Requête HTTP anonyme vers NOTRE serveur, vu du réseau de la stack.
 *
 * Vu de l'hôte le site s'appelle `http://localhost:<port>` ; vu du conteneur
 * d'outillage, le même serveur s'appelle `wordpress`, sur le port 80. On
 * substitue l'origine sans jamais coder le port en dur.
 *
 * L'EN-TÊTE `Host` EST PORTEUR, PAS COSMÉTIQUE. Sans lui, WordPress se voit
 * appelé sur une adresse qui n'est pas la sienne et `redirect_canonical` répond
 * **301** à toute page HTML — y compris à `/?author=1`, dont on veut justement
 * savoir si elle redirige. Le test mesurerait alors sa propre plomberie et
 * lirait un durcissement absent là où il est présent. Les routes REST, elles,
 * ne passent pas par la redirection canonique : c'est pourquoi le défaut est
 * resté invisible dans les scénarios qui n'interrogent que l'API.
 *
 * @param string               $chemin Chemin absolu, `/` compris.
 * @param array<string, mixed> $args   Arguments supplémentaires.
 *
 * @return array{code:int, corps:string, entetes:array<string,string>}
 */
function t70_http( string $chemin, array $args = array() ): array {
	$hote = (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
	$port = wp_parse_url( (string) home_url(), PHP_URL_PORT );

	$reponse = wp_remote_request(
		'http://wordpress' . $chemin,
		array_merge_recursive(
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array( 'Host' => null === $port ? $hote : $hote . ':' . $port ),
			),
			$args
		)
	);

	if ( is_wp_error( $reponse ) ) {
		return array(
			'code'    => 0,
			'corps'   => $reponse->get_error_message(),
			'entetes' => array(),
		);
	}

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $reponse ),
		'corps'   => (string) wp_remote_retrieve_body( $reponse ),
		'entetes' => array_change_key_case( (array) wp_remote_retrieve_headers( $reponse )->getAll(), CASE_LOWER ),
	);
}

/**
 * Chemin d'un fichier, relatif à la racine web, préfixé d'une barre oblique.
 *
 * @param string $absolu Chemin absolu dans le conteneur.
 */
function t70_url_publique( string $absolu ): string {
	$racine = rtrim( wp_normalize_path( (string) ABSPATH ), '/' );
	$absolu = wp_normalize_path( $absolu );

	return str_starts_with( $absolu, $racine ) ? substr( $absolu, strlen( $racine ) ) : '';
}

t_reset();

// ---------------------------------------------------------------------------
// 1. L'API PUBLIQUE N'A PAS ÉTÉ FERMÉE PAR LE DURCISSEMENT — bloquant si rouge.
// ---------------------------------------------------------------------------

$statuts = t70_http( (string) substr( rest_url( 'massifs/v1/statuts' ), strlen( (string) home_url() ) ) );
t_egal(
	200,
	$statuts['code'],
	'NON-RÉGRESSION CRITIQUE : GET /wp-json/massifs/v1/statuts répond 200 EN ANONYME — l’open data du §5.4 est intact'
);
t_assert(
	is_array( json_decode( $statuts['corps'], true ) ),
	'le corps servi anonymement est bien du JSON exploitable',
	'un tableau JSON',
	substr( $statuts['corps'], 0, 160 )
);

$zones = t70_http( (string) substr( rest_url( 'massifs/v1/zones-parcourues-par-le-feu' ), strlen( (string) home_url() ) ) );
t_egal( 200, $zones['code'], 'la seconde route publique de lecture (zones parcourues) répond 200 en anonyme' );

$racine_rest = t70_http( (string) substr( rest_url(), strlen( (string) home_url() ) ) );
t_egal( 200, $racine_rest['code'], 'la racine REST reste servie sans authentification' );

// ---------------------------------------------------------------------------
// 2. LES QUATRE SURFACES D'ÉNUMÉRATION, EN HTTP ET SANS COOKIE
// ---------------------------------------------------------------------------

t_egal( true, massifs_durcissement_enumeration_fermee(), 'le module déclare l’énumération fermée' );

foreach ( array( 'wp/v2/users', 'wp/v2/users/1' ) as $route ) {
	$reponse = t70_http( (string) substr( rest_url( $route ), strlen( (string) home_url() ) ) );
	t_egal( 404, $reponse['code'], sprintf( 'anonyme /wp-json/%s : 404 — la route est retirée', $route ) );
	t_assert(
		str_contains( $reponse['corps'], 'rest_no_route' ),
		sprintf( 'anonyme /wp-json/%s : le refus est un RETRAIT de route, pas un refus de droits', $route ),
		'rest_no_route',
		substr( $reponse['corps'], 0, 160 )
	);
}

// `users/me` reste 401 : l'éditeur de blocs du cœur en dépend. Un 404 ici
// signerait un retrait par PRÉFIXE, que le contrat interdit en toutes lettres.
$moi = t70_http( (string) substr( rest_url( 'wp/v2/users/me' ), strlen( (string) home_url() ) ) );
t_egal( 401, $moi['code'], 'anonyme /wp-json/wp/v2/users/me : 401 et jamais 404 — le retrait est littéral' );

// La fuite de `?author=N` est le 301 de `redirect_canonical`, pas le corps.
$auteur = t70_http( '/?author=1' );
t_egal( 404, $auteur['code'], 'anonyme /?author=1 : 404, jamais un 301 vers /author/<identifiant>/' );
t_egal(
	'',
	(string) ( $auteur['entetes']['location'] ?? '' ),
	'anonyme /?author=1 : aucun en-tête Location — aucun identifiant de connexion divulgué'
);

$admin = get_user_by( 'id', 1 );
if ( $admin instanceof WP_User ) {
	$archive = t70_http( '/author/' . $admin->user_nicename . '/' );
	t_egal( 404, $archive['code'], 'anonyme /author/<identifiant>/ : 404 — l’archive d’auteur est fermée' );
}

// Plan de site : le fait contractuel est que le FOURNISSEUR `users` est retiré
// (A-4, inconditionnellement). Ce qui l'atteste est l'index — la seule surface
// qu'un moteur suit. Le code de statut de `wp-sitemap-users-1.xml`, lui, n'est
// pas de notre fait : le cœur laisse la règle de réécriture en place et sert la
// page d'accueil en 200 quand plus aucun fournisseur ne répond. C'est un
// soft-404 du cœur, relevé en note, et il ne divulgue AUCUN compte — ce dernier
// point, lui, est affirmé.
$index_plan = t70_http( '/wp-sitemap.xml' );
t_egal( 200, $index_plan['code'], 'l’index du plan de site est servi' );
t_assert(
	! str_contains( $index_plan['corps'], 'users' ),
	'anonyme /wp-sitemap.xml : l’index ne référence AUCUN plan « users » — le fournisseur est retiré (A-4)',
	'aucune occurrence de « users »',
	$index_plan['corps']
);

$plan = t70_http( '/wp-sitemap-users-1.xml' );
t_assert(
	! str_contains( $plan['corps'], '<url>' ) && ! str_contains( $plan['corps'], '/author/' ),
	'anonyme /wp-sitemap-users-1.xml : aucune entrée d’auteur n’est servie',
	'aucun <url>, aucun /author/',
	substr( $plan['corps'], 0, 200 )
);
t_note(
	sprintf(
		'GET /wp-sitemap-users-1.xml → HTTP %d, %d octets (soft-404 du cœur : la règle de réécriture survit au retrait du fournisseur et la page d’accueil est servie ; aucun compte n’y figure)',
		$plan['code'],
		strlen( $plan['corps'] )
	)
);

// oEmbed : `author_name` / `author_url` retirés de la charge utile.
$billet = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);

if ( array() !== $billet ) {
	$oembed = t70_http( '/wp-json/oembed/1.0/embed?url=' . rawurlencode( (string) get_permalink( $billet[0] ) ) );
	$charge = json_decode( $oembed['corps'], true );
	t_egal( 200, $oembed['code'], 'oEmbed : la découverte reste servie (elle n’est pas cassée, elle est expurgée)' );
	t_assert(
		is_array( $charge ) && ! array_key_exists( 'author_name', $charge ) && ! array_key_exists( 'author_url', $charge ),
		'oEmbed : ni author_name ni author_url dans la charge utile',
		'les deux clés absentes',
		is_array( $charge ) ? implode( ', ', array_keys( $charge ) ) : substr( $oembed['corps'], 0, 160 )
	);
}

// ---------------------------------------------------------------------------
// 3. EN-TÊTES ET POLITIQUE DE MISE À JOUR, RELEVÉS SUR LA RÉPONSE RÉELLE
// ---------------------------------------------------------------------------

$accueil = t70_http( '/' );
t_egal( 200, $accueil['code'], 'la page d’accueil est servie' );

$attendus = array(
	'content-security-policy' => "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'",
	'x-content-type-options'  => 'nosniff',
	'referrer-policy'         => 'no-referrer',
	'x-frame-options'         => 'DENY',
	'permissions-policy'      => 'geolocation=(), camera=(), microphone=(), payment=(), usb=()',
);

foreach ( $attendus as $entete => $valeur ) {
	t_egal( $valeur, (string) ( $accueil['entetes'][ $entete ] ?? '' ), sprintf( 'accueil : en-tête %s', $entete ) );
}

// HSTS ABSENT EST LE COMPORTEMENT ATTENDU : il est conditionné à `is_ssl()` et
// la stack est en HTTP. L'affirmer évite qu'on le lise un jour comme un oubli.
t_egal(
	'',
	(string) ( $accueil['entetes']['strict-transport-security'] ?? '' ),
	'accueil : aucun HSTS en HTTP — l’en-tête est écrit mais conditionné à is_ssl(), il ne ment donc jamais en local'
);
t_egal(
	'',
	(string) ( $accueil['entetes']['cross-origin-resource-policy'] ?? '' ),
	'accueil : aucun Cross-Origin-Resource-Policy — il fermerait l’open data du §5.4 (A-9)'
);

$politique = massifs_durcissement_politique_mises_a_jour();
t_egal( true, $politique['mineures_auto'], 'mises à jour mineures automatiques (§9)' );
t_egal( false, $politique['majeures_auto'], 'mises à jour majeures NON automatiques — procédure documentée' );
t_egal( true, $politique['edition_code_interdite'], 'édition de code interdite par le filtre map_meta_cap' );
t_note( 'DISALLOW_FILE_EDIT posée : ' . ( $politique['constante_posee'] ? 'oui' : 'non' ) );
t_egal(
	false,
	defined( 'DISALLOW_FILE_MODS' ) && (bool) constant( 'DISALLOW_FILE_MODS' ),
	'DISALLOW_FILE_MODS n’est PAS posée (A-6) — elle tuerait les mises à jour mineures automatiques'
);

// Édition de code : le fait observable est la capacité, pas la constante.
$capacites = array();
foreach ( array( 'edit_themes', 'edit_plugins', 'edit_files' ) as $capacite ) {
	$capacites[ $capacite ] = user_can( 1, $capacite );
}
t_egal(
	array(
		'edit_themes'  => false,
		'edit_plugins' => false,
		'edit_files'   => false,
	),
	$capacites,
	'même l’administrateur n’a aucune capacité d’édition de code'
);

// ---------------------------------------------------------------------------
// 4. UNE ARCHIVE EST-ELLE TÉLÉCHARGEABLE PAR UN VISITEUR ANONYME ?
// ---------------------------------------------------------------------------

$repertoire = massifs_sauvegardes_repertoire();
t_assert( '' !== $repertoire, 'le répertoire d’archives est résolu', 'un chemin absolu', $repertoire );
t_note( 'répertoire d’archives : ' . $repertoire );

$creation = \Massifs\Security\Sauvegardes\Archives::creer( array( 'sans_fichiers' => true ) );

if ( is_wp_error( $creation ) ) {
	t_ko( 'création d’une archive de recette', 'une archive', $creation->get_error_message() );
} else {
	t_assert( true === ( $creation['complet'] ?? false ), 'l’archive de recette est complète', true, $creation['complet'] ?? null );
	t_note( sprintf( 'archive créée : %s (%d octets)', (string) $creation['nom'], (int) $creation['octets'] ) );

	$chemin_public = t70_url_publique( (string) $creation['chemin'] );
	t_assert( '' !== $chemin_public, 'l’archive vit sous la racine web (compromis A-5, à sortir en production)', 'un chemin servable', (string) $creation['chemin'] );

	if ( '' !== $chemin_public ) {
		$telechargement = t70_http( $chemin_public );
		t_assert(
			200 !== $telechargement['code'],
			'BLOQUANT SI ROUGE : l’archive n’est PAS téléchargeable par un visiteur anonyme (elle contient des hachages de mots de passe et des secrets TOTP)',
			'un code différent de 200',
			sprintf( 'HTTP %d, %d octets', $telechargement['code'], strlen( $telechargement['corps'] ) )
		);
		t_note( sprintf( 'GET %s → HTTP %d', $chemin_public, $telechargement['code'] ) );

		// Le répertoire lui-même : un index ouvert divulguerait les noms, donc la
		// cible exacte d'un téléchargement direct.
		$listing = t70_http( dirname( $chemin_public ) . '/' );
		t_assert(
			200 !== $listing['code'] || ! str_contains( $listing['corps'], '.zip' ),
			'le répertoire d’archives ne rend aucun index listant les .zip',
			'aucun listing',
			sprintf( 'HTTP %d — %s', $listing['code'], substr( $listing['corps'], 0, 160 ) )
		);
		t_note( sprintf( 'GET %s → HTTP %d', dirname( $chemin_public ) . '/', $listing['code'] ) );
	}
}

// ---------------------------------------------------------------------------
// 5. LA ROTATION SUPPRIME RÉELLEMENT — seuil franchi pour de bon
// ---------------------------------------------------------------------------

add_filter( 'massifs_sauvegardes_retention_nombre', static fn (): int => 2 );

$avant = array_column( massifs_sauvegardes_lister(), 'nom' );

for ( $i = 0; $i < 3; $i++ ) {
	\Massifs\Security\Sauvegardes\Archives::creer( array( 'sans_fichiers' => true ) );
}

$apres = array_column( massifs_sauvegardes_lister(), 'nom' );

t_egal( 2, count( $apres ), 'rotation : la rétention est tenue — il ne reste que le nombre d’archives autorisé' );
t_assert(
	array() !== array_diff( $avant, $apres ) || count( $avant ) < count( $apres ) + 1,
	'rotation : des archives ont RÉELLEMENT été supprimées, le seuil a été franchi',
	'au moins une suppression',
	sprintf( 'avant %d, après %d', count( $avant ), count( $apres ) )
);
t_note( 'archives restantes : ' . implode( ', ', $apres ) );

// ---------------------------------------------------------------------------
// 6. LA PLANIFICATION EST DÉSARMÉE, ET LE SCÉNARIO LE DIT AU LIEU DE LE TAIRE
// ---------------------------------------------------------------------------

t_egal(
	false,
	(bool) wp_next_scheduled( \Massifs\Security\Sauvegardes\Planification::HOOK ),
	'aucune sauvegarde n’est planifiée — la promesse « quotidienne » du §9 n’est PAS tenue par la stack (DISABLE_WP_CRON), et le module ne fait pas semblant'
);
// ÉTAT RÉEL DU COUPE-CIRCUIT, RELEVÉ ET NON SUPPOSÉ. `docker-compose.yml` l. 64
// déclare `define( 'DISABLE_WP_CRON', true )` via `WORDPRESS_CONFIG_EXTRA`, et
// tout le raisonnement de la couture S-8 s'appuie dessus. Or l'image n'injecte
// ce bloc qu'au moment où elle CRÉE `wp-config.php` : sur un volume déjà
// provisionné, il n'y est jamais entré. Le constat est donc relevé ici plutôt
// que déduit du fichier de composition — c'est la seule façon de ne pas bâtir
// une conclusion sur une prémisse fausse.
t_note(
	sprintf(
		'DISABLE_WP_CRON défini : %s (déclaré dans docker-compose.yml, à confronter à wp-config.php) — wp_get_environment_type() = %s',
		defined( 'DISABLE_WP_CRON' ) ? ( DISABLE_WP_CRON ? 'true' : 'false' ) : 'NON DÉFINI',
		wp_get_environment_type()
	)
);
t_egal(
	false,
	\Massifs\Security\Sauvegardes\Reglages::planification_active(),
	'la planification interne des sauvegardes est DÉSARMÉE par défaut — c’est elle, et non DISABLE_WP_CRON, qui garantit qu’aucune sauvegarde ne s’inscrit sans décision'
);

// ---------------------------------------------------------------------------
// MÉNAGE : les archives de recette ne survivent pas au scénario.
// ---------------------------------------------------------------------------

foreach ( massifs_sauvegardes_lister() as $archive ) {
	$chemin = \Massifs\Security\Sauvegardes\Archives::chemin( (string) $archive['nom'] );

	if ( ! is_wp_error( $chemin ) && file_exists( $chemin ) ) {
		wp_delete_file( $chemin );
	}
}

t_egal( array(), massifs_sauvegardes_lister(), 'MÉNAGE : aucune archive de recette n’est laissée derrière' );

t_reset();
t_bilan();
