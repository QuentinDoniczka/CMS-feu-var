<?php
/**
 * Commandes WP-CLI : `wp massifs sauvegarde <sous-commande>`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  AUCUNE LOGIQUE MÉTIER ICI.                                                   │
 * │                                                                               │
 * │  CETTE CLASSE VALIDE DES OPTIONS, APPELLE DES SERVICES, MET EN FORME ET       │
 * │  CHOISIT UN CODE DE RETOUR. RIEN D'AUTRE. LES GARDES DE CIBLE VIVENT DANS     │
 * │  `Restauration`, PAS ICI : UNE COMMANDE EST UNE AFFORDANCE, PAS UN CONTRÔLE   │
 * │  D'ACCÈS — MÊME DOCTRINE QUE `Roles\Comptes`. CE QUI EST CONTRÔLÉ ICI L'EST   │
 * │  UNE SECONDE FOIS, JAMAIS UNE SEULE.                                          │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * AUCUNE SURFACE WEB N'EXISTE POUR CE MODULE : ni route REST, ni écran, ni bouton,
 * ni page de réglages (arbitrage A-11). Une restauration à un clic depuis
 * `wp-admin` est une arme braquée sur le pied du site, et un cadeau à un compte
 * compromis.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use WP_CLI;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sauvegarde, restauration et vérification depuis la ligne de commande.
 */
final class Commande {

	/**
	 * Code de retour : résultat incomplet ou infidèle.
	 */
	private const INCOMPLET = 1;

	/**
	 * Code de retour : échec.
	 */
	private const ECHEC = 2;

	/**
	 * Code de retour : geste refusé par une garde.
	 */
	private const REFUS = 3;

	/**
	 * Crée une archive.
	 *
	 * ## OPTIONS
	 *
	 * [--sans-fichiers]
	 * : N'embarquer que la base.
	 *
	 * [--sans-base]
	 * : N'embarquer que les fichiers.
	 *
	 * [--repertoire=<chemin>]
	 * : Écrire l'archive dans ce répertoire plutôt que dans le répertoire par défaut.
	 *
	 * [--porcelain]
	 * : N'afficher que le chemin de l'archive produite.
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde creer
	 *     wp massifs sauvegarde creer --sans-fichiers --porcelain
	 *
	 * @param list<string>          $args    Arguments positionnels, aucun.
	 * @param array<string, string> $options Options nommées.
	 */
	public function creer( array $args, array $options ): void {
		unset( $args );

		$porcelain = isset( $options['porcelain'] );
		$filtre    = $this->filtre_repertoire( $options );

		if ( is_wp_error( $filtre ) ) {
			$this->sortir( $filtre, self::ECHEC );
		}

		$rapport = Archives::creer(
			array(
				'sans_base'     => isset( $options['sans-base'] ),
				'sans_fichiers' => isset( $options['sans-fichiers'] ),
			)
		);

		$this->retirer_filtre_repertoire( $filtre );

		if ( is_wp_error( $rapport ) ) {
			$this->sortir( $rapport, self::ECHEC );
		}

		if ( $porcelain ) {
			WP_CLI::line( (string) $rapport['chemin'] );
		} else {
			WP_CLI::log( 'Archive   : ' . (string) $rapport['nom'] );
			WP_CLI::log( 'Chemin    : ' . (string) $rapport['chemin'] );
			WP_CLI::log( 'Taille    : ' . $this->octets( (int) $rapport['octets'] ) );
			WP_CLI::log( 'Tables    : ' . (int) $rapport['tables'] );
			WP_CLI::log( 'Lignes    : ' . (int) $rapport['lignes'] );
			WP_CLI::log( 'Fichiers  : ' . (int) $rapport['fichiers'] );
			WP_CLI::log( 'Complète  : ' . ( true === $rapport['complet'] ? 'oui' : 'NON' ) );
		}

		if ( true !== $rapport['complet'] ) {
			// UN DUMP PARTIEL N'EST JAMAIS ÉTIQUETÉ COMPLET, ET LE CODE DE RETOUR
			// LE DIT AUSSI : un déclencheur hôte ne lit pas la sortie, il lit le
			// code. Sans cette ligne, une sauvegarde amputée passerait pour verte
			// dans toute supervision.
			if ( ! $porcelain ) {
				WP_CLI::warning( 'Archive INCOMPLÈTE. Voir le manifeste : wp massifs sauvegarde inspecter ' . (string) $rapport['nom'] );
			}

			WP_CLI::halt( self::INCOMPLET );
		}
	}

	/**
	 * Liste les archives présentes.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Format de sortie.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--garder-filets]
	 * : Conserver les archives « avant restauration » dans la liste.
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde lister
	 *     wp massifs sauvegarde lister --garder-filets --format=json
	 *
	 * @param list<string>          $args    Arguments positionnels, aucun.
	 * @param array<string, string> $options Options nommées.
	 */
	public function lister( array $args, array $options ): void {
		unset( $args );

		$archives = Archives::lister( isset( $options['garder-filets'] ) );
		$lignes   = array();

		foreach ( $archives as $archive ) {
			$lignes[] = array(
				'nom'       => $archive['nom'],
				'genre'     => $archive['genre'],
				'genere_le' => $archive['genere_le'],
				'octets'    => $archive['octets'],
				'complet'   => $archive['complet'] ? 'oui' : 'non',
			);
		}

		if ( array() === $lignes ) {
			WP_CLI::log( 'Aucune archive.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			$this->format( $options ),
			$lignes,
			array( 'nom', 'genre', 'genere_le', 'octets', 'complet' )
		);
	}

	/**
	 * Affiche le manifeste d'une archive.
	 *
	 * ## OPTIONS
	 *
	 * <archive>
	 * : Nom de l'archive.
	 *
	 * [--format=<format>]
	 * : Format de sortie.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde inspecter massifs-sauvegarde-20260816-031500-a1b2c3d4.zip
	 *
	 * @param list<string>          $args    Nom de l'archive.
	 * @param array<string, string> $options Options nommées.
	 */
	public function inspecter( array $args, array $options ): void {
		$chemin = Archives::chemin( isset( $args[0] ) ? (string) $args[0] : '' );

		if ( is_wp_error( $chemin ) ) {
			$this->sortir( $chemin, self::ECHEC );
		}

		$manifeste = Manifeste::depuis_archive( $chemin );

		if ( is_wp_error( $manifeste ) ) {
			// CODE 1, PAS 2 : l'archive existe, c'est son manifeste qui est illisible.
			// Un exploitant doit pouvoir distinguer « je n'ai pas trouvé le fichier »
			// de « le fichier est là mais ne dit plus ce qu'il contient ».
			$this->sortir( $manifeste, self::INCOMPLET );
		}

		if ( isset( $options['format'] ) && 'yaml' === $options['format'] ) {
			WP_CLI::print_value( $manifeste, array( 'format' => 'yaml' ) );

			return;
		}

		WP_CLI::line( (string) wp_json_encode( $manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Restaure une archive.
	 *
	 * ## OPTIONS
	 *
	 * <archive>
	 * : Nom de l'archive.
	 *
	 * [--oui]
	 * : Ne pas demander confirmation.
	 *
	 * [--sans-filet]
	 * : Ne pas créer l'archive « avant restauration ».
	 *
	 * [--sans-fichiers]
	 * : Ne restaurer que la base.
	 *
	 * [--sans-base]
	 * : Ne restaurer que les fichiers.
	 *
	 * [--nom-base=<nom>]
	 * : Nom exact de la base cible, saisi à la main.
	 *
	 * [--je-sais-ce-que-je-fais]
	 * : Lever la garde d'environnement, avec --nom-base.
	 *
	 * [--forcer]
	 * : Rejouer malgré un manifeste marqué incomplet.
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde restaurer massifs-sauvegarde-20260816-031500-a1b2c3d4.zip --oui
	 *
	 * @param list<string>          $args    Nom de l'archive.
	 * @param array<string, string> $options Options nommées.
	 */
	public function restaurer( array $args, array $options ): void {
		$this->imprimer_cible();

		$nom = isset( $args[0] ) ? (string) $args[0] : '';

		if ( ! isset( $options['oui'] ) ) {
			// SANS TTY ET SANS `--oui`, LE GESTE EST REFUSÉ. `WP_CLI::confirm` sur un
			// flux non interactif lirait EOF et pourrait être interprété comme un
			// accord : un script d'automatisation détruirait la base sans que
			// personne n'ait rien confirmé.
			if ( ! $this->interactif() ) {
				WP_CLI::warning( 'Aucun terminal interactif et pas de --oui : restauration refusée.' );
				WP_CLI::halt( self::REFUS );
			}

			WP_CLI::confirm( 'Restaurer « ' . $nom . ' » ? La base courante sera ÉCRASÉE.' );
		}

		$rapport = Restauration::depuis_archive(
			$nom,
			array(
				'aveu'          => isset( $options['je-sais-ce-que-je-fais'] ),
				'nom_base'      => isset( $options['nom-base'] ) ? (string) $options['nom-base'] : '',
				'sans_filet'    => isset( $options['sans-filet'] ),
				'sans_base'     => isset( $options['sans-base'] ),
				'sans_fichiers' => isset( $options['sans-fichiers'] ),
				'forcer'        => isset( $options['forcer'] ),
			)
		);

		if ( is_wp_error( $rapport ) ) {
			$this->sortir( $rapport, $this->code_erreur( $rapport ) );
		}

		WP_CLI::log( 'Archive      : ' . (string) $rapport['archive'] );
		WP_CLI::log( 'Filet        : ' . ( '' === $rapport['filet'] ? 'aucun (--sans-filet)' : (string) $rapport['filet'] ) );
		WP_CLI::log( 'Instructions : ' . (int) $rapport['instructions'] );
		WP_CLI::log( 'Fichiers     : ' . (int) $rapport['fichiers'] );

		if ( array() !== $rapport['ignores'] ) {
			WP_CLI::warning( count( $rapport['ignores'] ) . ' fichier(s) non restauré(s).' );
		}

		WP_CLI::success( 'Restauration terminée.' );
	}

	/**
	 * Vérifie la fidélité de l'aller-retour.
	 *
	 * ## OPTIONS
	 *
	 * [--nom-base=<nom>]
	 * : Nom exact de la base cible, saisi à la main.
	 *
	 * [--je-sais-ce-que-je-fais]
	 * : Lever la garde d'environnement, avec --nom-base.
	 *
	 * [--conserver-archive]
	 * : Conserver les archives produites par la vérification.
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde verifier
	 *
	 * @param list<string>          $args    Arguments positionnels, aucun.
	 * @param array<string, string> $options Options nommées.
	 */
	public function verifier( array $args, array $options ): void {
		unset( $args );

		$this->imprimer_cible();

		WP_CLI::log( 'CETTE COMMANDE ÉCRASE LA BASE CIBLE. Elle crée une table de fixtures, altère la base, puis la restaure.' );
		WP_CLI::log( '' );

		$rapport = Verification::executer(
			array(
				'aveu'              => isset( $options['je-sais-ce-que-je-fais'] ),
				'nom_base'          => isset( $options['nom-base'] ) ? (string) $options['nom-base'] : '',
				'conserver_archive' => isset( $options['conserver-archive'] ),
			)
		);

		if ( is_wp_error( $rapport ) ) {
			$this->sortir( $rapport, $this->code_erreur( $rapport ) );
		}

		WP_CLI::log( 'NORMALISATIONS DE LA PROJECTION — exactement trois, rien d\'autre n\'est normalisé :' );

		foreach ( $rapport['normalisations'] as $normalisation ) {
			WP_CLI::log( '  - ' . (string) $normalisation );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'LIGNES EXCLUES DU DUMP, donc exclues de la comparaison :' );

		if ( array() === $rapport['exclusions'] ) {
			WP_CLI::log( '  - aucune' );
		}

		foreach ( $rapport['exclusions'] as $exclusion ) {
			WP_CLI::log( '  - ' . (string) $exclusion );
		}

		WP_CLI::log( '' );

		foreach ( $rapport['assertions'] as $assertion ) {
			WP_CLI::log(
				( true === $assertion['ok'] ? '[  OK  ] ' : '[ÉCHEC ] ' )
				. (string) $assertion['libelle']
				. ' — ' . (string) $assertion['detail']
			);
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Empreinte A : ' . (string) $rapport['empreinte_a'] );
		WP_CLI::log( 'Empreinte B : ' . (string) $rapport['empreinte_b'] );

		if ( true !== $rapport['vert'] ) {
			// LE MESSAGE LE PLUS BRUYANT DU MODULE, ET IL DOIT LE RESTER. Un
			// aller-retour infidèle veut dire que les archives déjà produites sont
			// fausses — pas qu'une commande a échoué.
			WP_CLI::log( '' );
			WP_CLI::log( '################################################################' );
			WP_CLI::log( '#  ALLER-RETOUR INFIDÈLE.                                      #' );
			WP_CLI::log( '#  LES ARCHIVES PRODUITES PAR CE MOTEUR NE SONT PAS FIABLES.   #' );
			WP_CLI::log( '#  NE PAS S\'APPUYER SUR ELLES POUR UNE RESTAURATION.           #' );
			WP_CLI::log( '################################################################' );

			WP_CLI::halt( self::INCOMPLET );
		}

		WP_CLI::success( 'Aller-retour fidèle.' );
	}

	/**
	 * Applique la rotation.
	 *
	 * ## OPTIONS
	 *
	 * [--garder=<nombre>]
	 * : Nombre d'archives de sauvegarde conservées.
	 *
	 * [--simuler]
	 * : N'énumérer que ce qui serait supprimé.
	 *
	 * ## EXAMPLES
	 *
	 *     wp massifs sauvegarde purger --simuler
	 *
	 * @param list<string>          $args    Arguments positionnels, aucun.
	 * @param array<string, string> $options Options nommées.
	 */
	public function purger( array $args, array $options ): void {
		unset( $args );

		$garder = isset( $options['garder'] ) ? absint( $options['garder'] ) : null;

		if ( null !== $garder && $garder < 1 ) {
			WP_CLI::warning( '--garder doit valoir au moins 1.' );
			WP_CLI::halt( self::ECHEC );
		}

		$simuler = isset( $options['simuler'] );
		$rapport = Archives::purger( $garder, $simuler );

		foreach ( $rapport['supprimees'] as $nom ) {
			WP_CLI::log( ( $simuler ? 'À supprimer : ' : 'Supprimée   : ' ) . (string) $nom );
		}

		WP_CLI::log( 'Conservées  : ' . (int) $rapport['conservees'] );
		WP_CLI::success( $simuler ? 'Simulation terminée.' : 'Rotation terminée.' );
	}

	/**
	 * Imprime la cible avant tout geste destructeur.
	 *
	 * TOUJOURS, ET AVANT TOUT. La seule protection réelle contre « je croyais être
	 * sur la préproduction » est de lire, à l'écran, sur quoi on est.
	 */
	private function imprimer_cible(): void {
		WP_CLI::log( '--- CIBLE -------------------------------------------------' );
		WP_CLI::log( 'Environnement : ' . ( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'inconnu' ) );
		WP_CLI::log( 'DB_NAME       : ' . ( defined( 'DB_NAME' ) ? (string) DB_NAME : 'inconnu' ) );
		WP_CLI::log( 'DB_HOST       : ' . ( defined( 'DB_HOST' ) ? (string) DB_HOST : 'inconnu' ) );
		WP_CLI::log( 'site_url      : ' . (string) site_url() );
		WP_CLI::log( '-----------------------------------------------------------' );
	}

	/**
	 * Pose le filtre de répertoire demandé par `--repertoire`.
	 *
	 * @param array<string, string> $options Options nommées.
	 *
	 * @return callable|null|WP_Error
	 */
	private function filtre_repertoire( array $options ): callable|null|WP_Error {
		if ( ! isset( $options['repertoire'] ) ) {
			return null;
		}

		$demande = rtrim( wp_normalize_path( trim( (string) $options['repertoire'] ) ), '/' );

		if ( '' === $demande || ! path_is_absolute( $demande ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', '--repertoire exige un chemin absolu.' );
		}

		if ( ! is_dir( $demande ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire inexistant : ' . $demande . '.' );
		}

		$reel = realpath( $demande );

		if ( false === $reel || ! is_writable( $reel ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire non inscriptible : ' . $demande . '.' );
		}

		$resolu = rtrim( wp_normalize_path( $reel ), '/' );

		$filtre = static function () use ( $resolu ): string {
			return $resolu;
		};

		add_filter( 'massifs_sauvegardes_repertoire', $filtre, 99 );

		return $filtre;
	}

	/**
	 * Retire le filtre de répertoire.
	 *
	 * @param callable|null|WP_Error $filtre Filtre posé.
	 */
	private function retirer_filtre_repertoire( callable|null|WP_Error $filtre ): void {
		if ( is_callable( $filtre ) ) {
			remove_filter( 'massifs_sauvegardes_repertoire', $filtre, 99 );
		}
	}

	/**
	 * Format de sortie demandé.
	 *
	 * @param array<string, string> $options Options nommées.
	 */
	private function format( array $options ): string {
		$demande = isset( $options['format'] ) ? (string) $options['format'] : 'table';

		return in_array( $demande, array( 'table', 'json', 'csv', 'yaml' ), true ) ? $demande : 'table';
	}

	/**
	 * Code de retour associé à une erreur.
	 *
	 * @param WP_Error $erreur Erreur remontée par un service.
	 */
	private function code_erreur( WP_Error $erreur ): int {
		$code = $erreur->get_error_code();

		if ( Restauration::CODE_REFUS === $code ) {
			return self::REFUS;
		}

		if ( Restauration::CODE_INCOMPLETE === $code ) {
			return self::INCOMPLET;
		}

		return self::ECHEC;
	}

	/**
	 * Affiche une erreur et sort avec le code demandé.
	 *
	 * `WP_CLI::error` sortirait toujours en 1 : le code est choisi explicitement,
	 * parce qu'un déclencheur hôte distingue « refusé » de « échoué ».
	 *
	 * @param WP_Error $erreur Erreur.
	 * @param int      $code   Code de retour.
	 */
	private function sortir( WP_Error $erreur, int $code ): never {
		WP_CLI::warning( $erreur->get_error_message() );
		WP_CLI::halt( $code );
	}

	/**
	 * Le processus dispose-t-il d'un terminal interactif ?
	 */
	private function interactif(): bool {
		if ( ! defined( 'STDIN' ) ) {
			return false;
		}

		// `stream_isatty` d'abord : il fait partie du cœur de PHP depuis 7.2, alors
		// que `posix_isatty` dépend de l'extension `posix`, absente de certaines
		// images. LE DOUTE SE RÉSOUT EN « NON INTERACTIF », donc en refus : mieux
		// vaut exiger --oui à tort que prendre un EOF pour un accord.
		if ( function_exists( 'stream_isatty' ) ) {
			return stream_isatty( STDIN );
		}

		if ( function_exists( 'posix_isatty' ) ) {
			return posix_isatty( STDIN );
		}

		return false;
	}

	/**
	 * Met une taille en forme.
	 *
	 * @param int $octets Taille en octets.
	 */
	private function octets( int $octets ): string {
		return number_format_i18n( $octets ) . ' octets';
	}
}
