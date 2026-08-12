<?php
/**
 * Harnais des scénarios d'intégration MASSIFS.
 *
 * Ces scénarios ne sont PAS des tests unitaires : chacun joue une histoire
 * complète dans un WordPress réellement amorcé, à l'intérieur de la stack
 * Docker du dépôt, et n'affirme que des faits observables — la base, la valeur
 * rendue au thème, la réponse HTTP, l'état persisté. Aucune fonction privée
 * n'est appelée, aucune classe interne n'est instrumentée.
 *
 * Lancement : voir `tests/README.md`.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( defined( 'MASSIFS_TESTS_HARNAIS' ) ) {
	return;
}

define( 'MASSIFS_TESTS_HARNAIS', true );

$massifs_tests_argv = isset( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array( 'scenario' );

$GLOBALS['massifs_tests'] = array(
	'nom' => basename( (string) end( $massifs_tests_argv ) ),
	'ok'  => 0,
	'ko'  => 0,
);

/**
 * Assertion réussie.
 *
 * @param string $message Intitulé.
 */
function t_ok( string $message ): void {
	++$GLOBALS['massifs_tests']['ok'];
	fwrite( STDOUT, '  ok   ' . $message . "\n" );
}

/**
 * Assertion échouée.
 *
 * @param string $message Intitulé.
 * @param mixed  $attendu Valeur attendue.
 * @param mixed  $obtenu  Valeur obtenue.
 */
function t_ko( string $message, $attendu = null, $obtenu = null ): void {
	++$GLOBALS['massifs_tests']['ko'];
	fwrite( STDOUT, '  ECHEC ' . $message . "\n" );
	fwrite( STDOUT, '         attendu : ' . t_dump( $attendu ) . "\n" );
	fwrite( STDOUT, '         obtenu  : ' . t_dump( $obtenu ) . "\n" );
}

/**
 * Représentation lisible d'une valeur.
 *
 * @param mixed $valeur Valeur.
 */
function t_dump( $valeur ): string {
	return is_string( $valeur ) ? $valeur : var_export( $valeur, true );
}

/**
 * Assertion booléenne.
 *
 * @param bool   $condition Condition.
 * @param string $message   Intitulé.
 * @param mixed  $attendu   Valeur attendue.
 * @param mixed  $obtenu    Valeur obtenue.
 */
function t_assert( bool $condition, string $message, $attendu = null, $obtenu = null ): bool {
	if ( $condition ) {
		t_ok( $message );

		return true;
	}

	t_ko( $message, $attendu, $obtenu );

	return false;
}

/**
 * Assertion d'égalité stricte.
 *
 * @param mixed  $attendu Valeur attendue.
 * @param mixed  $obtenu  Valeur obtenue.
 * @param string $message Intitulé.
 */
function t_egal( $attendu, $obtenu, string $message ): bool {
	return t_assert( $attendu === $obtenu, $message, $attendu, $obtenu );
}

/**
 * Observation consignée, sans verdict.
 *
 * @param string $texte Observation.
 */
function t_note( string $texte ): void {
	fwrite( STDOUT, '  note ' . $texte . "\n" );
}

/**
 * Clôt le scénario. Code de sortie non nul si une assertion a échoué.
 */
function t_bilan(): void {
	$bilan = $GLOBALS['massifs_tests'];

	fwrite( STDOUT, sprintf( "BILAN %s : %d ok, %d echec(s)\n", $bilan['nom'], $bilan['ok'], $bilan['ko'] ) );

	if ( $bilan['ko'] > 0 ) {
		exit( 1 );
	}
}

/**
 * Remet l'état d'ingestion, de fraîcheur et de statuts à zéro.
 *
 * Chaque scénario est autonome : il commence et finit par cette purge, et doit
 * passer lancé seul.
 */
function t_reset(): void {
	global $wpdb;

	delete_option( 'massifs_prefecture_etat' );
	delete_option( 'massifs_prefecture_snapshots' );
	delete_option( 'massifs_prefecture_reglages' );
	// Registre des relevés réussis (§4.5) : sans cette purge, un scénario
	// hériterait de la fraîcheur du précédent et mentirait sur sa propre cause.
	delete_option( 'massifs_dernier_releve' );

	wp_cache_flush();

	$table = $wpdb->prefix . 'massifs_statuts';
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB
}

/**
 * Réarme le connecteur sur la stack de développement.
 *
 * `WP_ENVIRONMENT_TYPE=local` désarme volontairement le connecteur tant qu'aucun
 * modèle d'URL n'est redéfini (`Settings::is_disabled()`), pour qu'une machine
 * de développement ne bombarde jamais le serveur de la préfecture. Pour éprouver
 * le chemin d'ingestion, on redéfinit ce modèle vers une URL INTRA-STACK : le
 * connecteur se réarme et atteindre la source réelle devient impossible par
 * construction. Les réponses restent bouchonnées à la frontière `wp_remote_get`.
 *
 * @param string $modele Modèle d'URL, jeton `{date}`.
 */
function t_armer_connecteur( string $modele = 'http://wordpress/massifs-bouchon/{date}.json' ): void {
	if ( ! defined( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE' ) ) {
		define( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE', $modele );
	}
}

/**
 * Charge utile réaliste de la source : 27 identifiants, « 13 » + 1..27.
 *
 * @param int $niveau    Valeur `level` émise pour chaque massif.
 * @param int $procedure Valeur `procedure` émise pour chaque massif.
 *
 * @return array<string, mixed>
 */
function t_charge_source( int $niveau = 2, int $procedure = 0 ): array {
	$massifs = array();

	for ( $n = 1; $n <= 27; $n++ ) {
		$massifs[ '13' . $n ] = array( $niveau, $procedure );
	}

	return array(
		'massifs' => $massifs,
		'zm'      => array( '131' => $niveau, '132' => $niveau ),
	);
}

/**
 * Instantané au format publié par le connecteur, tel que le domaine le reçoit.
 *
 * @param array<string, array<string, int>> $massifs       Entrées résolues.
 * @param string                            $jour_validite Jour `YYYY-MM-DD`.
 *
 * @return array<string, mixed>
 */
function t_instantane( array $massifs, string $jour_validite ): array {
	return array(
		'date_validite'     => $jour_validite,
		'massifs'           => $massifs,
		'source_modifie_le' => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
	);
}

/**
 * Les 27 entrées d'un instantané, au format publié par le validateur.
 *
 * @param int $niveau    Valeur `level`.
 * @param int $procedure Valeur `procedure`.
 *
 * @return array<string, array<string, int>>
 */
function t_instantane_massifs( int $niveau = 2, int $procedure = 0 ): array {
	$massifs = array();

	for ( $n = 1; $n <= 27; $n++ ) {
		$massifs[ '13' . $n ] = array(
			'niveau_source'    => $niveau,
			'procedure_source' => $procedure,
		);
	}

	return $massifs;
}

/**
 * Bouchonne la frontière réseau du connecteur.
 *
 * C'est le SEUL point de contact avec l'extérieur : bouchonné ici, aucun octet
 * ne peut quitter la stack, quelle que soit la suite du chemin d'ingestion.
 *
 * @param mixed $reponse Réponse à rendre, ou fonction recevant l'URL.
 */
function t_bouchon_http( $reponse ): void {
	add_filter(
		'pre_http_request',
		static function ( $court_circuit, $args, $url ) use ( $reponse ) {
			return is_callable( $reponse ) ? $reponse( $url ) : $reponse;
		},
		10,
		3
	);
}

/**
 * Réponse HTTP 200 portant une charge utile JSON.
 *
 * `Last-Modified` par défaut : il y a une heure. C'est réaliste — la source
 * dépose le fichier du lendemain vers 17 h la veille — et c'est surtout la
 * seule valeur qui reste valide quelle que soit la date de validité visée : la
 * couche temporelle du validateur rejette (`fichier_perime`) un fichier modifié
 * plus de 48 h avant le début de validité demandé. Un horodatage figé dans le
 * passé ferait échouer les scénarios portant sur demain, pour une raison qui
 * n'aurait rien à voir avec ce qu'ils éprouvent.
 *
 * @param array<string, mixed> $charge        Charge utile.
 * @param string|null          $last_modified En-tête `Last-Modified`, ou `null` pour « il y a une heure ».
 *
 * @return array<string, mixed>
 */
function t_reponse_200( array $charge, ?string $last_modified = null ): array {
	$last_modified = $last_modified ?? gmdate( 'D, d M Y H:i:s \G\M\T', time() - HOUR_IN_SECONDS );
	return array(
		'headers'  => array(
			'content-type'  => 'application/json',
			'last-modified' => $last_modified,
		),
		'body'     => (string) wp_json_encode( $charge ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Réponse HTTP arbitraire.
 *
 * @param int    $code  Code de statut.
 * @param string $corps Corps.
 *
 * @return array<string, mixed>
 */
function t_reponse_code( int $code, string $corps = '' ): array {
	return array(
		'headers'  => array( 'content-type' => 'text/html' ),
		'body'     => $corps,
		'response' => array(
			'code'    => $code,
			'message' => 'x',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Intercepte les courriels sans jamais en envoyer un.
 *
 * @param array<int, mixed> $boite Boîte de réception, passée par référence.
 */
function t_intercepter_mail( array &$boite ): void {
	add_filter(
		'pre_wp_mail',
		static function ( $court_circuit, $attributs ) use ( &$boite ) {
			$boite[] = $attributs;

			return true;
		},
		10,
		2
	);
}

/**
 * Nombre de lignes de statut en base.
 */
function t_lignes_statuts(): int {
	global $wpdb;

	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}massifs_statuts" ); // phpcs:ignore WordPress.DB
}

/**
 * Jour civil suivant un jour donné, dans le fuseau du dispositif.
 *
 * @param string $jour Jour `YYYY-MM-DD`.
 */
function t_jour_apres( string $jour ): string {
	return ( new DateTimeImmutable( $jour, new DateTimeZone( 'Europe/Paris' ) ) )->modify( '+1 day' )->format( 'Y-m-d' );
}

/**
 * Jour civil précédant un jour donné, dans le fuseau du dispositif.
 *
 * @param string $jour Jour `YYYY-MM-DD`.
 */
function t_jour_avant( string $jour ): string {
	return ( new DateTimeImmutable( $jour, new DateTimeZone( 'Europe/Paris' ) ) )->modify( '-1 day' )->format( 'Y-m-d' );
}
