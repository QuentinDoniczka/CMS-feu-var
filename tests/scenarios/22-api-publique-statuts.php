<?php
/**
 * Le point d'accès public en lecture des statuts du jour (§5.4 du brief,
 * contrat #8) — éprouvé en HTTP RÉEL, anonymement, depuis l'extérieur de
 * WordPress.
 *
 * Ce scénario ne lit jamais le tableau rendu par le callback : il interroge
 * `http://wordpress/wp-json/massifs/v1/statuts` par le réseau de la stack,
 * exactement comme le fera un réutilisateur tiers. C'est le seul protocole qui
 * éprouve à la fois la route, les gardes du cœur, l'authentification (absente),
 * les en-têtes et le JSON réellement servi. Aucun cookie n'est présenté :
 * l'anonymat est un fait du transport, pas une hypothèse.
 *
 * Aucune source externe n'est contactée. L'état de la base est posé par la
 * fonction d'écriture publique du domaine, jamais par une récupération.
 *
 * Lignes du §12 servies : « chaîne des données » (l'écriture traverse jusqu'au
 * JSON servi), « statut périmé jamais présenté comme courant » (le bornage du
 * paramètre `jour` et l'état `indisponible` en HTTP 200), « API publique ».
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/../bootstrap.php';

t_reset();

/**
 * Interroge le point d'accès par le réseau de la stack, sans authentification.
 *
 * L'URL publique du site est `http://localhost:<port>` vue de l'hôte ; vu du
 * conteneur d'outillage, le MÊME serveur s'appelle `wordpress`. On substitue
 * l'origine sans jamais coder le port en dur — il est réglable dans `.env` et il
 * a déjà changé une fois.
 *
 * @param string               $requete Query string, `?` compris, ou chaîne vide.
 * @param array<string, mixed> $args    Arguments supplémentaires de `wp_remote_request`.
 *
 * @return array{code:int, corps:string, json:mixed, entetes:array<string,string>}
 */
function t_api( string $requete = '', array $args = array() ): array {
	$url = 'http://wordpress' . (string) substr( rest_url( 'massifs/v1/statuts' ), strlen( (string) home_url() ) ) . $requete;

	$reponse = wp_remote_request(
		$url,
		array_merge(
			array(
				'timeout'     => 20,
				'redirection' => 0,
			),
			$args
		)
	);

	if ( is_wp_error( $reponse ) ) {
		return array(
			'code'    => 0,
			'corps'   => $reponse->get_error_message(),
			'json'    => null,
			'entetes' => array(),
		);
	}

	$corps = (string) wp_remote_retrieve_body( $reponse );

	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $reponse ),
		'corps'   => $corps,
		'json'    => json_decode( $corps, true ),
		'entetes' => array_change_key_case( (array) wp_remote_retrieve_headers( $reponse )->getAll(), CASE_LOWER ),
	);
}

/**
 * Écrit un jeu complet de statuts pour un jour donné, un massif sur cinq interdit.
 *
 * @param string $jour Jour de validité `AAAA-MM-JJ`.
 *
 * @return array{autorises:int, interdits:int, total:int}
 */
function t_publier_journee( string $jour ): array {
	$codes     = massifs_codes();
	$autorises = 0;
	$rang      = 0;

	foreach ( $codes as $code ) {
		$interdit = 0 === $rang % 5;

		if ( ! $interdit ) {
			++$autorises;
		}

		massifs_enregistrer_statut(
			array(
				'massif_code'   => $code,
				'jour_validite' => $jour,
				'niveau_cle'    => $interdit ? 'interdit' : 'autorise',
				'zapef_cle'     => 0 === $rang % 2 ? ( $interdit ? 'interdit' : 'autorise' ) : null,
				'source'        => 'saisie_manuelle',
				'auteur_id'     => 1,
			)
		);

		++$rang;
	}

	return array(
		'autorises' => $autorises,
		'interdits' => count( $codes ) - $autorises,
		'total'     => count( $codes ),
	);
}

$aujourd_hui = massifs_jour_courant();
$demain      = massifs_jour_suivant();
$hier        = t_jour_avant( $aujourd_hui );
$codes       = massifs_codes();

t_note( 'jour courant = ' . $aujourd_hui . ' · jour suivant = ' . $demain );
t_note( 'point d\'accès interrogé : ' . rest_url( 'massifs/v1/statuts' ) . ' (via http://wordpress/, sans cookie)' );

// ---------------------------------------------------------------------------
// 1. Base vide : aucun statut pour aujourd'hui. C'est l'état que le §4.2 oblige
//    à présenter comme « information non disponible », et que le contrat #8
//    oblige à servir en HTTP 200 — jamais 404, jamais 204, jamais liste vide.
// ---------------------------------------------------------------------------

$vide = t_api();

t_egal( 200, $vide['code'], 'aucune donnée du jour : HTTP 200 (I-2 — ni 404 ni 204)' );
t_assert( is_array( $vide['json'] ), 'la réponse est un objet JSON', 'array', gettype( $vide['json'] ) );
t_egal( $aujourd_hui, $vide['json']['jour'] ?? '', 'sans paramètre, le jour servi est le jour courant' );
t_egal( 'aujourd_hui', $vide['json']['jour_relatif'] ?? '', 'jour_relatif = aujourd_hui' );
t_egal(
	array(
		'aujourd_hui' => $aujourd_hui,
		'demain'      => $demain,
	),
	$vide['json']['jours_disponibles'] ?? array(),
	'jours_disponibles porte les deux bornes, calculées par le serveur (§7.2 interdit 2)'
);
t_egal( count( $codes ), count( (array) ( $vide['json']['massifs'] ?? array() ) ), 'I-1 : les 25 massifs sont présents même sans aucune donnée' );
t_egal( 'indisponible', $vide['json']['synthese']['etat_global'] ?? '', 'etat_global = indisponible' );

$etats_vides       = array();
$niveaux_non_nuls  = array();
$jours_divergents  = array();
$codes_servis      = array();
foreach ( (array) ( $vide['json']['massifs'] ?? array() ) as $entree ) {
	$etats_vides[]  = $entree['etat'] ?? '(absent)';
	$codes_servis[] = $entree['code'] ?? '(absent)';

	// `array_key_exists` et non `??` : l'opérateur de coalescence se déclenche
	// précisément sur `null`, c'est-à-dire sur la valeur que cet invariant exige.
	// Il rendrait donc « clé absente » et « null littéral » indiscernables — la
	// distinction même que I-3 et I-7 posent.
	if ( ! array_key_exists( 'niveau', $entree ) || null !== $entree['niveau']
		|| ! array_key_exists( 'zapef', $entree ) || null !== $entree['zapef'] ) {
		$niveaux_non_nuls[] = $entree['code'] ?? '?';
	}

	if ( ( $entree['jour_validite'] ?? '' ) !== ( $vide['json']['jour'] ?? '' ) ) {
		$jours_divergents[] = $entree['code'] ?? '?';
	}
}

t_egal( array( 'indisponible' ), array_values( array_unique( $etats_vides ) ), 'chaque massif porte l\'état `indisponible`' );
t_egal( array(), $niveaux_non_nuls, 'I-3 : `niveau` et `zapef` valent null littéral hors de `disponible`' );
t_egal( array(), $jours_divergents, 'I-6 : chaque `jour_validite` est celui de l\'enveloppe' );
t_egal( $codes, $codes_servis, 'les codes sont servis dans l\'ordre du référentiel, sans retri (§7.1 interdit 11)' );

// I-4 — le repli du §4.2 voyage toujours, y compris quand il n'y a pas de statut.
t_egal(
	'https://www.risque-prevention-incendie.fr/13',
	$vide['json']['attribution']['statuts']['carte_officielle_url'] ?? '',
	'I-4 : `carte_officielle_url` voyage même sans donnée — le réutilisateur peut relayer le repli'
);
t_assert(
	is_bool( $vide['json']['legende']['confirmee'] ?? null ) && is_bool( $vide['json']['legende']['consignes_publiees'] ?? null ),
	'I-5 : `legende.confirmee` et `legende.consignes_publiees` voyagent',
	'deux booléens',
	wp_json_encode( array( $vide['json']['legende']['confirmee'] ?? null, $vide['json']['legende']['consignes_publiees'] ?? null ) )
);

// I-7 — toutes les clés du contrat sont toujours présentes : le consommateur
// n'écrit jamais `isset()`. On les affirme sur l'état LE PLUS PAUVRE, celui où
// un `array_filter` malencontreux les ferait disparaître.
$attendues = array( 'jour', 'jour_relatif', 'jours_disponibles', 'saison', 'fraicheur', 'synthese', 'massifs', 'legende', 'referentiel', 'geometrie', 'emprise', 'attribution' );
t_egal( $attendues, array_keys( (array) $vide['json'] ), 'I-7 : les douze clés de l\'enveloppe, dans l\'ordre du contrat' );

$attendues_massif = array( 'code', 'libelle', 'communes', 'etat', 'jour_validite', 'niveau', 'zapef', 'source', 'publie_prefecture_le' );
t_egal( $attendues_massif, array_keys( (array) ( $vide['json']['massifs'][0] ?? array() ) ), 'I-7 : les neuf clés d\'une entrée de massif' );

$attendues_synthese = array( 'etat_global', 'partiel', 'total', 'disponibles', 'sans_donnee', 'par_niveau', 'niveau_le_moins_severe', 'niveau_le_plus_severe' );
t_egal( $attendues_synthese, array_keys( (array) ( $vide['json']['synthese'] ?? array() ) ), 'I-7 : les huit clés de la synthèse' );

$attendues_fraicheur = array( 'dernier_releve_le', 'dernier_releve_source', 'seuil_secondes', 'perimee', 'publie_prefecture_le', 'dispositif_actif' );
t_egal( $attendues_fraicheur, array_keys( (array) ( $vide['json']['fraicheur'] ?? array() ) ), 'I-7 : les six clés de fraîcheur' );

$attendues_saison = array( 'active', 'debut', 'fin', 'prochaine_ouverture', 'confirmee' );
t_egal( $attendues_saison, array_keys( (array) ( $vide['json']['saison'] ?? array() ) ), 'I-7 : les cinq clés de saison' );

// `par_niveau` est TOUJOURS un objet JSON, jamais `[]` : `Object.keys()` doit
// être sûr côté client. Sur une légende sans niveau, un tableau PHP vide
// s'encoderait en `[]` et casserait le consommateur. On le mesure sur les
// OCTETS servis, pas sur le tableau décodé — `json_decode` efface la distinction.
t_assert(
	str_contains( $vide['corps'], '"par_niveau":{' ),
	'`par_niveau` est encodé en OBJET JSON, jamais en tableau vide',
	'"par_niveau":{…}',
	(string) ( strstr( substr( $vide['corps'], (int) strpos( $vide['corps'], '"par_niveau"' ) ), ',', true ) )
);

// I-10 — la géométrie n'est pas dans la charge utile : seulement son pointeur.
t_assert(
	! str_contains( $vide['corps'], 'MultiPolygon' ) && ! str_contains( $vide['corps'], 'coordinates' ),
	'I-10 : aucune géométrie dans la réponse — seulement le pointeur `geometrie.url`',
	'aucune coordonnée',
	'des coordonnées voyagent'
);
t_egal(
	wp_parse_url( home_url(), PHP_URL_HOST ),
	wp_parse_url( (string) ( $vide['json']['geometrie']['url'] ?? '' ), PHP_URL_HOST ),
	'le pointeur de géométrie vise NOTRE origine (contrainte #2)'
);

// §2.1 — ce qui ne doit PAS voyager. `auteur_id` est une donnée personnelle sur
// un point d'accès anonyme (§9 du brief) ; les autres sont des identités ou des
// instants internes, ou une exposition interdite par le contrat #3.
foreach ( array( 'auteur_id', 'statut_id', 'enregistre_le', 'niveau_source_brut', 'procedure_source', 'age_secondes', 'evalue_le' ) as $interdite ) {
	t_assert( ! str_contains( $vide['corps'], '"' . $interdite . '"' ), 'clé écartée absente de la charge utile : `' . $interdite . '`', 'absente', 'présente' );
}

// §6 — en-têtes. `no-cache` et JAMAIS `max-age` : un `max-age` posé à 23 h 55
// servirait la journée d'hier après minuit — le §4.2 par la porte de derrière.
t_assert( str_contains( (string) ( $vide['entetes']['cache-control'] ?? '' ), 'no-cache' ), '§6 : `Cache-Control: no-cache` sur notre réponse', 'no-cache', $vide['entetes']['cache-control'] ?? '(absent)' );
t_assert( ! str_contains( (string) ( $vide['entetes']['cache-control'] ?? '' ), 'max-age' ), '§6 : aucun `max-age` — il servirait la veille après minuit', 'aucun max-age', $vide['entetes']['cache-control'] ?? '(absent)' );
t_assert( ! str_contains( (string) ( $vide['entetes']['cache-control'] ?? '' ), 'no-store' ), '§6 : `no-store` écarté — le 304 doit rester utile', 'aucun no-store', $vide['entetes']['cache-control'] ?? '(absent)' );
t_egal( 'noindex', (string) ( $vide['entetes']['x-robots-tag'] ?? '' ), '§6 : `X-Robots-Tag: noindex`' );
t_assert( str_contains( (string) ( $vide['entetes']['content-type'] ?? '' ), 'application/json' ), '§6 : `Content-Type: application/json`', 'application/json', $vide['entetes']['content-type'] ?? '(absent)' );
t_assert( '' !== (string) ( $vide['entetes']['etag'] ?? '' ), '§6.1 : un ETag est émis', 'W/"…"', '(absent)' );

// ---------------------------------------------------------------------------
// 2. Bornage du paramètre `jour` — invariant de contrat (§1.1). Il n'y a PAS
//    d'archive publique : une date passée est un 400 par construction.
// ---------------------------------------------------------------------------

$demain_reponse = t_api( '?jour=' . rawurlencode( $demain ) );
t_egal( 200, $demain_reponse['code'], '`jour=demain` est servi' );
t_egal( $demain, $demain_reponse['json']['jour'] ?? '', 'l\'enveloppe porte TOUJOURS le jour DEMANDÉ' );
t_egal( 'demain', $demain_reponse['json']['jour_relatif'] ?? '', 'jour_relatif = demain' );

$vide_param = t_api( '?jour=' );
t_egal( 400, $vide_param['code'], '`?jour=` vide ⇒ 400, jamais un repli silencieux sur aujourd\'hui' );

$passe = t_api( '?jour=' . rawurlencode( $hier ) );
t_egal( 400, $passe['code'], 'une date PASSÉE ⇒ 400 : il n\'y a pas d\'archive publique (§1.1)' );
t_assert(
	in_array( (string) ( $passe['json']['code'] ?? '' ), array( 'rest_invalid_param', 'massifs_jour_hors_bornes' ), true ),
	'le refus porte un code stable',
	'rest_invalid_param | massifs_jour_hors_bornes',
	$passe['json']['code'] ?? '(absent)'
);

$futur = t_api( '?jour=' . rawurlencode( t_jour_apres( $demain ) ) );
t_egal( 400, $futur['code'], 'un jour au-delà de demain ⇒ 400' );

$malforme = t_api( '?jour=pas-une-date' );
t_egal( 400, $malforme['code'], 'un `jour` malformé ⇒ 400' );

// Le message d'une exception ne voyage jamais : ni trace PHP, ni chemin de
// fichier, sur un point d'accès anonyme (§5, §9 du brief).
foreach ( array( $passe, $malforme, $futur ) as $refus ) {
	t_assert(
		! preg_match( '#(/var/www|\.php[: ]|Stack trace|Fatal error)#', $refus['corps'] ),
		'aucun détail d\'implémentation dans un corps d\'erreur',
		'code + phrase neutre',
		substr( $refus['corps'], 0, 160 )
	);
}

// ---------------------------------------------------------------------------
// 3. I-11 — aucune écriture n'est atteignable. Trois sondes, dont les deux
//    contournements de méthode que le contrat #8 (Q3) demandait de mesurer.
// ---------------------------------------------------------------------------

$post = t_api( '', array( 'method' => 'POST' ) );
t_assert( in_array( $post['code'], array( 404, 405 ), true ), 'I-11 : `POST` sur la route ⇒ 404 ou 405, jamais 200', '404 | 405', $post['code'] );
t_assert( ! str_contains( $post['corps'], '"massifs"' ), '`POST` ne sert aucune charge utile de statuts', 'un corps d\'erreur', substr( $post['corps'], 0, 120 ) );

$override_query = t_api( '?_method=POST' );
t_assert( in_array( $override_query['code'], array( 200, 404, 405 ), true ), 'Q3 : `?_method=POST` n\'atteint aucune écriture', '200 | 404 | 405', $override_query['code'] );

$override_entete = t_api( '', array( 'method' => 'POST', 'headers' => array( 'X-HTTP-Method-Override' => 'GET' ) ) );
t_assert( in_array( $override_entete['code'], array( 200, 404, 405 ), true ), 'Q3 : `X-HTTP-Method-Override: GET` ne mène qu\'à une lecture', '200 | 404 | 405', $override_entete['code'] );

foreach ( array( 'PUT', 'PATCH', 'DELETE' ) as $methode ) {
	$refus = t_api( '', array( 'method' => $methode ) );
	t_assert( in_array( $refus['code'], array( 404, 405 ), true ), 'I-11 : `' . $methode . '` refusé', '404 | 405', $refus['code'] );
}

// La surface REST de l'espace `massifs/v1` est une LISTE EXACTE, jamais une
// borne inférieure : une route d'écriture ajoutée par mégarde doit rougir ici.
$serveur = rest_get_server();
do_action( 'rest_api_init', $serveur );
$routes = $serveur->get_routes();
$nôtres = array_values( array_filter( array_keys( $routes ), static fn( $r ) => str_starts_with( $r, '/massifs/' ) ) );
sort( $nôtres );
// Quatre entrées depuis l'Épic 5 : #15 a posé `portail/historique` DANS
// `massifs/v1` (contrat #15 §4), là où #14 a créé l'espace de noms distinct
// `massifs-portail/v1`. La divergence est constatée, pas lissée. Ce qui reste
// intact et compte pour I-11 : `portail/historique` est une LECTURE GARDÉE, elle
// n'ouvre aucune écriture dans l'espace public — assertion ci-dessous.
t_egal( array( '/massifs/v1', '/massifs/v1/portail/historique', '/massifs/v1/statuts', '/massifs/v1/zones-parcourues-par-le-feu' ), $nôtres, 'l\'espace `massifs/v1` expose exactement quatre entrées : son index, les deux lectures publiques, et l\'historique du portail (gardé)' );

$methodes_ecriture = array();
foreach ( $routes as $chemin => $poignees ) {
	if ( ! str_starts_with( (string) $chemin, '/massifs/' ) ) {
		continue;
	}
	foreach ( $poignees as $poignee ) {
		foreach ( array( 'POST', 'PUT', 'PATCH', 'DELETE' ) as $verbe ) {
			if ( ! empty( $poignee['methods'][ $verbe ] ) ) {
				$methodes_ecriture[] = $chemin . ' ' . $verbe;
			}
		}
	}
}
sort( $methodes_ecriture );
t_egal( array(), array_values( array_unique( $methodes_ecriture ) ), 'I-11 : aucune méthode d\'écriture n\'est déclarée dans l\'espace `massifs/v1`' );

$methodes = array();
foreach ( $routes['/massifs/v1/statuts'] as $definition ) {
	foreach ( array_keys( (array) ( $definition['methods'] ?? array() ) ) as $methode ) {
		$methodes[ $methode ] = true;
	}
}
t_egal( array( 'GET' ), array_keys( $methodes ), 'I-11 : la route ne déclare que `GET`' );

// ---------------------------------------------------------------------------
// 4. Chaîne des données : une publication traverse jusqu'au JSON servi.
// ---------------------------------------------------------------------------

$compte = t_publier_journee( $aujourd_hui );
massifs_enregistrer_releve_reussi( 'prefecture', gmdate( 'Y-m-d\TH:i:s\Z' ) );
t_note( sprintf( 'journée publiée : %d autorisés / %d interdits / %d massifs', $compte['autorises'], $compte['interdits'], $compte['total'] ) );

$plein = t_api();

t_egal( 200, $plein['code'], 'journée publiée : HTTP 200' );
t_egal( 'disponible', $plein['json']['synthese']['etat_global'] ?? '', 'etat_global = disponible' );
t_egal( false, $plein['json']['synthese']['partiel'] ?? null, 'la journée n\'est pas partielle' );
t_egal( $compte['total'], $plein['json']['synthese']['disponibles'] ?? -1, 'les 25 massifs sont renseignés' );
t_egal(
	array(
		'autorise' => $compte['autorises'],
		'interdit' => $compte['interdits'],
	),
	(array) ( $plein['json']['synthese']['par_niveau'] ?? array() ),
	'`par_niveau` reflète EXACTEMENT ce qui a été écrit en base'
);

$niveaux_manquants = array();
$libelles          = array();
foreach ( (array) ( $plein['json']['massifs'] ?? array() ) as $entree ) {
	if ( 'disponible' !== ( $entree['etat'] ?? '' ) || ! is_array( $entree['niveau'] ?? null ) ) {
		$niveaux_manquants[] = $entree['code'] ?? '?';
		continue;
	}
	$libelles[] = (string) $entree['niveau']['libelle'];
}
t_egal( array(), $niveaux_manquants, 'chaque massif publié porte un objet `niveau`' );

$distincts = array_values( array_unique( $libelles ) );
sort( $distincts );
t_egal(
	array( 'Accès au massif autorisé', 'Accès au massif interdit' ),
	$distincts,
	'§4 : les libellés officiels voyagent dans la réponse — le consommateur n\'en rédige aucun'
);

// Les jetons de présentation voyagent aussi : sans eux, un client peindrait un
// massif avec une couleur de son cru (§7.2 interdit 3).
$premier = (array) ( $plein['json']['massifs'][0]['niveau'] ?? array() );
t_egal(
	array( 'cle', 'libelle', 'consigne', 'severite', 'motif', 'jeton_css', 'jeton_encre_css', 'rang', 'total' ),
	array_keys( $premier ),
	'l\'objet `niveau` est la forme du domaine verbatim (A-6), sans renommage'
);

// §5.4 du brief : le point d'accès est PUBLIC. La réponse ne varie ni par
// session ni par cookie (I-9) — on présente un cookie de session bidon et l'on
// compare les octets servis.
$avec_cookie = t_api( '', array( 'headers' => array( 'Cookie' => 'wordpress_logged_in_bidon=1; wp-settings-1=x' ) ) );
t_egal( 200, $avec_cookie['code'], 'I-9 : la route répond identiquement avec un cookie présenté' );
t_egal( $plein['corps'], $avec_cookie['corps'], 'I-9 : les octets servis ne varient pas selon le cookie' );

// §6.1 — ETag et 304. L'échange est joué en vrai : on renvoie l'ETag reçu.
//
// La négociation de compression est coupée sur CETTE jambe (`Accept-Encoding:
// identity`), et ce n'est pas un confort de recette : Apache applique par défaut
// `DeflateAlterETag AddSuffix` et suffixe l'ETag de « -gzip » à la sortie SANS
// retirer ce suffixe à l'entrée. Un client qui accepte gzip et rejoue fidèlement
// l'ETag reçu n'obtient donc jamais de 304 — dégradation mesurée et affirmée
// quelques lignes plus bas, à sa place, comme un fait observable et non comme un
// bruit qu'on éteint. Ici, on éprouve ce que le contrat #8 §6.1 décrit : la
// comparaison faible faite par NOTRE callback.
$identite = array( 'headers' => array( 'Accept-Encoding' => 'identity' ) );

$plein_nu = t_api( '', $identite );
$etag     = (string) ( $plein_nu['entetes']['etag'] ?? '' );
t_assert( '' !== $etag, 'un ETag accompagne la journée publiée', 'W/"…"', '(absent)' );
t_assert( str_starts_with( $etag, 'W/"' ), '§6.1 : l\'ETag est FAIBLE — la comparaison de `If-None-Match` l\'est aussi (RFC 9110)', 'W/"…"', $etag );

$revalide = t_api( '', array( 'headers' => array( 'Accept-Encoding' => 'identity', 'If-None-Match' => $etag ) ) );
t_egal( 304, $revalide['code'], '§6.1 : `If-None-Match` sur l\'ETag courant ⇒ 304' );
t_assert(
	strlen( $revalide['corps'] ) <= 4,
	'§6.1 : la « verrue » du cœur mesurée — le corps d\'un 304 est inoffensif',
	'≤ 4 octets',
	strlen( $revalide['corps'] ) . ' octets : ' . substr( $revalide['corps'], 0, 40 )
);
t_note( 'corps réellement servi sur le 304 : ' . strlen( $revalide['corps'] ) . ' octet(s)' );

// La forme forte du même ETag doit être acceptée : RFC 9110 impose la
// comparaison FAIBLE pour `If-None-Match`, préfixe retiré des DEUX côtés.
$forte = t_api( '', array( 'headers' => array( 'Accept-Encoding' => 'identity', 'If-None-Match' => substr( $etag, 2 ) ) ) );
t_egal( 304, $forte['code'], '§6.1 : la forme forte du même ETag est acceptée (comparaison faible)' );

$joker = t_api( '', array( 'headers' => array( 'Accept-Encoding' => 'identity', 'If-None-Match' => '*' ) ) );
t_egal( 304, $joker['code'], '§6.1 : `If-None-Match: *` est accepté (RFC 9110)' );

// Dégradation d'INFRASTRUCTURE, mesurée et nommée, jamais tue.
//
// Quand la compression est négociée, Apache sert `W/"<sha1>-gzip"`. Le client
// honnête rejoue ce jeton, notre callback ne le reconnaît pas, et la charge
// utile complète repart. Ce que le contrat garantit reste tenu — la réponse
// servie est TOUJOURS juste, jamais une charge périmée. Ce qui est perdu est un
// gain de bande passante, pas une exactitude. La cause est
// `docker/wordpress/deflate.conf`, qui laisse le défaut `DeflateAlterETag
// AddSuffix` d'Apache 2.4 ; elle n'appartient pas au module `rest/public`.
$compresse = t_api( '', array( 'headers' => array( 'Accept-Encoding' => 'gzip' ) ) );
$etag_gzip = (string) ( $compresse['entetes']['etag'] ?? '' );
t_note( 'ETag servi sans compression : ' . $etag . ' · avec compression : ' . $etag_gzip );

if ( $etag_gzip !== $etag ) {
	$rejoue = t_api( '', array( 'headers' => array( 'Accept-Encoding' => 'gzip', 'If-None-Match' => $etag_gzip ) ) );
	t_note( 'DÉGRADATION D\'INFRASTRUCTURE : `DeflateAlterETag AddSuffix` (défaut Apache 2.4) suffixe l\'ETag à la sortie sans le désuffixer à l\'entrée — un client qui accepte gzip ne peut pas obtenir de 304 sur cette route.' );
	t_egal( 200, $rejoue['code'], 'sous compression, le rejeu de l\'ETag rend une charge utile COMPLÈTE (jamais un corps vide, jamais une donnée périmée)' );
	t_egal(
		$plein_nu['corps'],
		$rejoue['corps'],
		'sous compression, la charge utile re-servie est identique à celle de référence — la dégradation coûte de la bande passante, jamais de l\'exactitude'
	);
} else {
	t_ok( 'aucun mutilage d\'ETag par la couche de compression : le 304 est atteignable en toute négociation' );
}

$perime = t_api( '', array( 'headers' => array( 'If-None-Match' => 'W/"une-empreinte-qui-n-est-plus-la-bonne"' ) ) );
t_egal( 200, $perime['code'], '§6.1 : un ETag périmé rend la charge utile complète' );

// A-3 — ni ETag ni 304 quand `_fields` est présent : il modifie les octets
// servis APRÈS notre callback, et un ETag qui ne décrit pas le corps servi est
// pire qu'aucun ETag.
$champs = t_api( '?_fields=jour' );
t_egal( 200, $champs['code'], '`?_fields=jour` est servi' );
t_egal( '', (string) ( $champs['entetes']['etag'] ?? '' ), 'A-3 : aucun ETag quand `_fields` est présent' );

// Le jour suivant, non publié, reste servi en 200 avec ses 25 massifs.
$demain_vide = t_api( '?jour=' . rawurlencode( $demain ) );
t_egal( 200, $demain_vide['code'], 'demain non publié : HTTP 200 (I-2)' );
t_egal( count( $codes ), count( (array) ( $demain_vide['json']['massifs'] ?? array() ) ), 'I-1 : les 25 massifs sont là aussi pour demain' );
$etats_demain = array_values( array_unique( array_map( static fn( $m ) => $m['etat'], (array) $demain_vide['json']['massifs'] ) ) );
t_assert(
	array( 'disponible' ) !== $etats_demain,
	'demain non publié n\'est JAMAIS présenté comme `disponible`',
	'non_encore_publie | indisponible | hors_saison',
	wp_json_encode( $etats_demain )
);
t_note( 'états servis pour demain : ' . wp_json_encode( $etats_demain ) );

// La règle absolue du §4.2, vue de l'API : la donnée d'hier existe en base et
// n'est servie NULLE PART comme celle d'aujourd'hui.
t_reset();
t_publier_journee( $hier );

$apres_veille = t_api();
t_egal( 200, $apres_veille['code'], 'donnée de la veille en base : HTTP 200' );
t_egal( $aujourd_hui, $apres_veille['json']['jour'] ?? '', 'l\'enveloppe porte bien AUJOURD\'HUI' );
t_egal( 'indisponible', $apres_veille['json']['synthese']['etat_global'] ?? '', '§4.2 : la veille ne remplit pas la journée courante' );
$niveaux_veille = array_values( array_filter( array_map( static fn( $m ) => $m['niveau'], (array) $apres_veille['json']['massifs'] ) ) );
t_egal( array(), $niveaux_veille, '§4.2 : AUCUN niveau de la veille n\'est servi comme courant' );
t_assert(
	! str_contains( $apres_veille['corps'], 'Accès au massif autorisé' ) && ! str_contains( $apres_veille['corps'], 'Accès au massif interdit' ),
	'§4.2 : aucun libellé de niveau ne fuit dans les octets servis',
	'aucun libellé',
	'un libellé de la veille est servi'
);
t_egal(
	'https://www.risque-prevention-incendie.fr/13',
	$apres_veille['json']['attribution']['statuts']['carte_officielle_url'] ?? '',
	'§4.2 : le lien de la carte officielle est servi à la place de la donnée'
);

t_reset();

t_bilan();
