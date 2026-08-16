<?php
/**
 * L'aller-retour : dump A → archive → altération → restauration → dump B, et
 * comparaison.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │  C'EST LA SEULE CHOSE DE CE MODULE QUI PROUVE QUELQUE CHOSE.                  │
 * │                                                                               │
 * │  TOUT LE RESTE — TAILLE DE L'ARCHIVE, ABSENCE D'ERREUR, `complet:true` — SE   │
 * │  CONTENTE D'ÊTRE COHÉRENT AVEC LUI-MÊME. UN MOTEUR QUI ÉCRIT `''` LÀ OÙ IL Y  │
 * │  AVAIT `NULL` PRODUIT UNE ARCHIVE PARFAITEMENT COHÉRENTE, PARFAITEMENT        │
 * │  RESTAURABLE, ET FAUSSE. SEUL L'ALLER-RETOUR LE VOIT.                         │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * LA TABLE DE FIXTURES EST UNE LIGNE PAR HASARD IDENTIFIÉ
 *
 * Elle n'est pas là pour « avoir des données de test » : chaque ligne reproduit un
 * mode de défaillance précis du moteur de dump, et sa disparition rendrait le vert
 * moins probant. Elle est créée ET détruite par la commande, jamais par
 * l'installation — et ce module NE CONTRIBUE PAS à
 * `massifs_core_signature_schema` : il ne crée aucune table à l'installation, et
 * il ne doit pas pouvoir forcer un rejeu global d'installation par accident.
 *
 * LES VALEURS SONT INSÉRÉES PAR UN CHEMIN INDÉPENDANT DE `DumpSql`
 *
 * Littéraux hexadécimaux systématiques, écrits ici. Si l'insertion partageait la
 * logique d'échappement du moteur, une erreur d'échappement s'annulerait
 * elle-même à l'aller-retour et le test serait vert pour la mauvaise raison.
 *
 * @package Massifs\Security\Sauvegardes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Sauvegardes;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vérification de fidélité de l'aller-retour.
 */
final class Verification {

	/**
	 * Suffixe de la table de fixtures, après le préfixe du site.
	 */
	public const SUFFIXE_TABLE = 'massifs_verif_temoin';

	/**
	 * Option témoin.
	 */
	public const OPTION_TEMOIN = 'massifs_sauvegardes_temoin';

	/**
	 * Étiquette de la fixture supprimée par l'altération contrôlée.
	 */
	private const FIXTURE_SUPPRIMEE = 'apostrophe';

	/**
	 * Exécute l'aller-retour complet.
	 *
	 * @param array{aveu?:bool,nom_base?:string,conserver_archive?:bool} $options Options.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function executer( array $options = array() ): array|WP_Error {
		if ( ! ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) ) {
			return new WP_Error( Restauration::CODE_REFUS, 'La vérification ne s\'exécute que depuis WP-CLI.' );
		}

		$garde = Restauration::garde_cible(
			true === ( $options['aveu'] ?? false ),
			isset( $options['nom_base'] ) && is_string( $options['nom_base'] ) ? $options['nom_base'] : ''
		);

		if ( is_wp_error( $garde ) ) {
			return $garde;
		}

		// EXCLUSION NOMMÉE, ET ELLE EST IMPRIMÉE. Le journal du module est écrit
		// APRÈS le dump A et AVANT le dump B : sans exclusion, l'aller-retour serait
		// rouge à cause de la trace de sa propre exécution, et le vrai signal
		// disparaîtrait dans le bruit. Toute exclusion est un risque de faux vert :
		// celle-ci porte sur une option dont le contenu est produit par ce module
		// lui-même, jamais sur une donnée métier.
		$exclusion = static function ( $exclusions ) {
			if ( ! is_array( $exclusions ) ) {
				$exclusions = array();
			}

			$options_exclues   = isset( $exclusions['options'] ) && is_array( $exclusions['options'] ) ? $exclusions['options'] : array();
			$options_exclues[] = Journal::OPTION;

			$exclusions['options'] = $options_exclues;

			return $exclusions;
		};

		add_filter( 'massifs_sauvegardes_lignes_exclues', $exclusion, 99 );

		global $wpdb;

		// Même raison que dans `DumpSql` : sans `suppress_errors( false )`,
		// `wpdb::last_error` reste vide sur une requête en échec et la vérification
		// se déroulerait joyeusement sur une table qui n'a jamais été créée.
		$suppression = $wpdb->suppress_errors( false );
		$affichage   = $wpdb->hide_errors();

		try {
			return self::derouler( true === ( $options['conserver_archive'] ?? false ) );
		} finally {
			remove_filter( 'massifs_sauvegardes_lignes_exclues', $exclusion, 99 );

			$wpdb->suppress_errors( $suppression );

			if ( $affichage ) {
				$wpdb->show_errors();
			}
		}
	}

	/**
	 * Déroule les huit étapes.
	 *
	 * @param bool $conserver Conserver l'archive à la fin ?
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function derouler( bool $conserver ): array|WP_Error {
		$preparation = self::preparer_fixtures();

		if ( is_wp_error( $preparation ) ) {
			return $preparation;
		}

		$avant  = self::lire_fixtures();
		$temoin = wp_generate_uuid4();

		if ( is_wp_error( $avant ) ) {
			self::demonter( '' );

			return $avant;
		}

		update_option( self::OPTION_TEMOIN, $temoin, false );

		$archive = Archives::creer(
			array(
				'sans_fichiers' => true,
			)
		);

		if ( is_wp_error( $archive ) ) {
			self::demonter( '' );

			return $archive;
		}

		$projection_a = self::projeter_archive( (string) $archive['chemin'] );

		if ( is_wp_error( $projection_a ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $projection_a;
		}

		// ALTÉRATION CONTRÔLÉE. Sans elle, la restauration pourrait ne rien faire du
		// tout et toutes les assertions passeraient : on prouverait seulement que
		// la base n'a pas changé.
		update_option( self::OPTION_TEMOIN, wp_generate_uuid4(), false );

		$suppression = self::supprimer_fixture( self::FIXTURE_SUPPRIMEE );

		if ( is_wp_error( $suppression ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $suppression;
		}

		$restauration = Restauration::depuis_archive(
			(string) $archive['nom'],
			array(
				'sans_filet'    => true,
				'sans_fichiers' => true,
			)
		);

		if ( is_wp_error( $restauration ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $restauration;
		}

		$apres = self::lire_fixtures();

		if ( is_wp_error( $apres ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $apres;
		}

		$assertions = self::comparer( $avant, $apres, $temoin );

		$archive_b = Archives::creer(
			array(
				'sans_fichiers' => true,
			)
		);

		if ( is_wp_error( $archive_b ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $archive_b;
		}

		$projection_b = self::projeter_archive( (string) $archive_b['chemin'] );

		if ( is_wp_error( $projection_b ) ) {
			self::demonter( $conserver ? '' : (string) $archive['nom'] );

			return $projection_b;
		}

		$divergentes = self::tables_divergentes( $projection_a, $projection_b );

		$assertions[] = array(
			'libelle' => 'Le re-dump B est identique au dump A',
			'ok'      => $projection_a['empreinte'] === $projection_b['empreinte'],
			'detail'  => array() === $divergentes
				? 'A = ' . substr( $projection_a['empreinte'], 0, 16 ) . ' / B = ' . substr( $projection_b['empreinte'], 0, 16 )
				: 'tables divergentes : ' . implode( ', ', $divergentes ),
		);

		$vert = true;

		foreach ( $assertions as $assertion ) {
			if ( true !== $assertion['ok'] ) {
				$vert = false;
			}
		}

		self::demonter( $conserver ? '' : (string) $archive['nom'] );

		if ( ! $conserver ) {
			self::supprimer_archive( (string) $archive_b['nom'] );
		}

		Journal::consigner(
			'verification',
			array(
				'vert'    => $vert,
				'archive' => (string) $archive['nom'],
			)
		);

		return array(
			'vert'           => $vert,
			'assertions'     => $assertions,
			'archive'        => (string) $archive['nom'],
			'archive_b'      => (string) $archive_b['nom'],
			'conservee'      => $conserver,
			'empreinte_a'    => $projection_a['empreinte'],
			'empreinte_b'    => $projection_b['empreinte'],
			'normalisations' => $projection_a['normalisations'],
			'exclusions'     => $projection_a['exclusions'],
		);
	}

	/**
	 * Table de fixtures de valeurs piégeuses.
	 *
	 * UNE LIGNE PAR HASARD IDENTIFIÉ. Chaque commentaire dit contre QUOI la ligne
	 * existe : sans cela, la première « simplification » venue en retirerait la
	 * moitié en croyant nettoyer un jeu de données arbitraire.
	 *
	 * @return list<array{id:int,etiquette:string,texte:string|null,binaire:string|null,pourquoi:string}>
	 */
	public static function fixtures(): array {
		return array(
			array(
				'id'        => 0,
				'etiquette' => 'auto_increment_zero',
				'texte'     => 'identifiant zéro',
				'binaire'   => null,
				// Sans `NO_AUTO_VALUE_ON_ZERO` dans l'en-tête du dump, cette ligne
				// revient avec un autre identifiant. Rare, silencieux, irréversible.
				'pourquoi'  => 'renumérotation silencieuse d\'un auto_increment à 0',
			),
			array(
				'id'        => 1,
				'etiquette' => 'nul',
				'texte'     => null,
				'binaire'   => null,
				// LE HASARD PRINCIPAL. `niveau_cle` est NULLABLE et son `NULL`
				// signifie « publié sans statut d'accès » — distinct de « rien publié ».
				'pourquoi'  => 'NULL confondu avec la chaîne vide',
			),
			array(
				'id'        => 2,
				'etiquette' => 'chaine_vide',
				'texte'     => '',
				'binaire'   => '',
				// Le pendant du précédent, et le cas limite du littéral binaire :
				// une chaîne binaire vide s'écrit `''`, jamais `0x`.
				'pourquoi'  => 'chaîne vide devenue NULL, ou binaire vide écrit « 0x »',
			),
			array(
				'id'        => 3,
				'etiquette' => 'saut_de_ligne',
				'texte'     => "avant\napres",
				'binaire'   => null,
				// Casse tout découpage naïf du dump sur le saut de ligne.
				'pourquoi'  => 'saut de ligne réel coupant une instruction en deux',
			),
			array(
				'id'        => 4,
				'etiquette' => 'retour_chariot',
				'texte'     => "avant\r\napres",
				'binaire'   => null,
				'pourquoi'  => 'retour chariot avalé par une normalisation de fin de ligne',
			),
			array(
				'id'        => 5,
				'etiquette' => self::FIXTURE_SUPPRIMEE,
				'texte'     => "l'accès au massif",
				'binaire'   => null,
				'pourquoi'  => 'apostrophe non échappée fermant le littéral',
			),
			array(
				'id'        => 6,
				'etiquette' => 'contre_oblique',
				'texte'     => 'C:\\chemin\\massifs',
				'binaire'   => null,
				'pourquoi'  => 'contre-oblique doublée ou perdue à l\'échappement',
			),
			array(
				'id'        => 7,
				'etiquette' => 'octet_nul',
				'texte'     => "a\0b",
				'binaire'   => null,
				'pourquoi'  => 'octet nul tronquant la valeur au premier zéro',
			),
			array(
				'id'        => 8,
				'etiquette' => 'emoji',
				'texte'     => '🔥 massif',
				'binaire'   => null,
				'pourquoi'  => 'caractère UTF-8 sur 4 octets perdu par un jeu de caractères trop étroit',
			),
			array(
				'id'        => 9,
				'etiquette' => 'utf8_invalide',
				'texte'     => null,
				'binaire'   => "\xC3\x28\xFF\x00\xFE",
				// Le cas qui impose le littéral hexadécimal indépendamment du type.
				'pourquoi'  => 'séquence UTF-8 invalide dans un varbinary',
			),
			array(
				'id'        => 10,
				'etiquette' => 'marqueurs_de_format',
				'texte'     => '100%% de %s et %d',
				'binaire'   => null,
				// Un `prepare()` égaré sur une instruction déjà composée mangerait ces
				// marqueurs sans rien signaler.
				'pourquoi'  => 'marqueurs de format mangés par un prepare() égaré',
			),
		);
	}

	/**
	 * Nom complet de la table de fixtures.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::SUFFIXE_TABLE;
	}

	/**
	 * Nom de la table de fixtures, encadré en accents graves.
	 *
	 * Le nom ne vient d'aucune entrée : il est composé du préfixe de l'installation
	 * et d'un suffixe littéral. L'encadrement est la règle d'échappement MySQL des
	 * identifiants, qui ne peuvent pas être liés en paramètre de `prepare()`.
	 */
	private static function table_encadree(): string {
		return '`' . str_replace( '`', '``', self::table() ) . '`';
	}

	/**
	 * Crée la table de fixtures et y insère les valeurs piégeuses.
	 *
	 * @return true|WP_Error
	 */
	private static function preparer_fixtures(): true|WP_Error {
		global $wpdb;

		$table = self::table_encadree();

		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );

		$erreur = self::erreur_sql( 'suppression de la table de fixtures' );

		if ( null !== $erreur ) {
			return $erreur;
		}

		$wpdb->query(
			'CREATE TABLE ' . $table . ' ('
			. 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,'
			. 'etiquette varchar(64) NOT NULL,'
			. 'texte longtext NULL,'
			. 'binaire varbinary(255) NULL,'
			. 'PRIMARY KEY (id)'
			. ') ' . $wpdb->get_charset_collate()
		);

		$erreur = self::erreur_sql( 'création de la table de fixtures' );

		if ( null !== $erreur ) {
			return $erreur;
		}

		// Exigé pour insérer explicitement l'identifiant 0.
		$wpdb->query( "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'" );

		$erreur = self::erreur_sql( 'préparation du mode SQL' );

		if ( null !== $erreur ) {
			return $erreur;
		}

		$controle                  = $wpdb->check_current_query;
		$wpdb->check_current_query = false;

		foreach ( self::fixtures() as $fixture ) {
			$wpdb->query(
				'INSERT INTO ' . $table . ' (id,etiquette,texte,binaire) VALUES ('
				. (int) $fixture['id'] . ','
				. self::litteral_independant( $fixture['etiquette'] ) . ','
				. self::litteral_independant( $fixture['texte'] ) . ','
				. self::litteral_independant( $fixture['binaire'] ) . ')'
			);

			$erreur = self::erreur_sql( 'insertion de la fixture « ' . $fixture['etiquette'] . ' »' );

			if ( null !== $erreur ) {
				$wpdb->check_current_query = $controle;

				return $erreur;
			}
		}

		$wpdb->check_current_query = $controle;

		return true;
	}

	/**
	 * Littéral SQL indépendant du moteur de dump.
	 *
	 * Hexadécimal systématique : aucune règle d'échappement partagée avec
	 * `DumpSql::litteral()`, donc aucune erreur d'échappement ne peut s'annuler
	 * elle-même à l'aller-retour.
	 *
	 * @param string|null $valeur Valeur brute.
	 */
	private static function litteral_independant( ?string $valeur ): string {
		if ( null === $valeur ) {
			return 'NULL';
		}

		if ( '' === $valeur ) {
			return "''";
		}

		return '0x' . bin2hex( $valeur );
	}

	/**
	 * Lit les fixtures telles que la base les rend.
	 *
	 * @return array<string, array{id:int,texte:string|null,binaire:string|null}>|WP_Error
	 */
	private static function lire_fixtures(): array|WP_Error {
		global $wpdb;

		$table  = self::table_encadree();
		$lignes = $wpdb->get_results( 'SELECT id,etiquette,texte,binaire FROM ' . $table . ' ORDER BY id ASC', ARRAY_A );

		$erreur = self::erreur_sql( 'lecture des fixtures' );

		if ( null !== $erreur ) {
			return $erreur;
		}

		$etat = array();

		foreach ( (array) $lignes as $ligne ) {
			if ( ! is_array( $ligne ) || ! isset( $ligne['etiquette'] ) ) {
				continue;
			}

			$etat[ (string) $ligne['etiquette'] ] = array(
				'id'      => (int) $ligne['id'],
				'texte'   => $ligne['texte'],
				'binaire' => $ligne['binaire'],
			);
		}

		return $etat;
	}

	/**
	 * Supprime une fixture, pour l'altération contrôlée.
	 *
	 * @param string $etiquette Étiquette de la fixture.
	 *
	 * @return true|WP_Error
	 */
	private static function supprimer_fixture( string $etiquette ): true|WP_Error {
		global $wpdb;

		$table = self::table_encadree();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE etiquette = %s', $etiquette ) );

		$erreur = self::erreur_sql( 'altération contrôlée' );

		return null === $erreur ? true : $erreur;
	}

	/**
	 * Compare l'état d'avant et l'état d'après.
	 *
	 * @param array<string, array<string, mixed>> $avant  État avant l'archive.
	 * @param array<string, array<string, mixed>> $apres  État après la restauration.
	 * @param string                              $temoin Témoin attendu.
	 *
	 * @return list<array{libelle:string,ok:bool,detail:string}>
	 */
	private static function comparer( array $avant, array $apres, string $temoin ): array {
		$assertions = array();

		$assertions[] = array(
			'libelle' => 'Le témoin d\'option est revenu à sa valeur d\'avant l\'archive',
			'ok'      => (string) get_option( self::OPTION_TEMOIN, '' ) === $temoin,
			'detail'  => 'attendu ' . $temoin,
		);

		$assertions[] = array(
			'libelle' => 'La ligne supprimée est revenue (« ' . self::FIXTURE_SUPPRIMEE . ' »)',
			'ok'      => isset( $apres[ self::FIXTURE_SUPPRIMEE ] ),
			'detail'  => isset( $apres[ self::FIXTURE_SUPPRIMEE ] ) ? 'présente' : 'absente',
		);

		// ASSERTION NOMMÉE SÉPARÉMENT, et pas noyée dans la boucle des fixtures.
		// C'est le hasard qui a le plus de chances de se produire, et le seul dont
		// la conséquence — un historique de statuts faussé au sens du §4.2 — soit
		// invisible à l'œil nu dans l'archive comme dans la base restaurée.
		$nul_avant = isset( $avant['nul'] ) && null === $avant['nul']['texte'] && null === $avant['nul']['binaire'];
		$nul_apres = isset( $apres['nul'] ) && null === $apres['nul']['texte'] && null === $apres['nul']['binaire'];

		$assertions[] = array(
			'libelle' => 'La fixture NULL était bien NULL AVANT l\'archive (sans quoi l\'assertion suivante ne prouve rien)',
			'ok'      => $nul_avant,
			'detail'  => $nul_avant ? 'NULL' : 'la base n\'a pas stocké de NULL : le test suivant serait vide de sens',
		);

		$assertions[] = array(
			'libelle' => 'NULL est resté NULL et n\'est pas devenu la chaîne vide',
			'ok'      => $nul_apres,
			'detail'  => self::decrire( self::brut( $apres, 'nul', 'texte' ) ) . ' / ' . self::decrire( self::brut( $apres, 'nul', 'binaire' ) ),
		);

		foreach ( self::fixtures() as $fixture ) {
			$etiquette = (string) $fixture['etiquette'];

			if ( ! isset( $avant[ $etiquette ] ) ) {
				$assertions[] = array(
					'libelle' => 'Fixture « ' . $etiquette . ' » : présente avant l\'archive',
					'ok'      => false,
					'detail'  => 'absente avant l\'archive — ' . (string) $fixture['pourquoi'],
				);

				continue;
			}

			$identique = isset( $apres[ $etiquette ] )
				&& $apres[ $etiquette ]['id'] === $avant[ $etiquette ]['id']
				&& $apres[ $etiquette ]['texte'] === $avant[ $etiquette ]['texte']

				// Colonne binaire comparée en hexadécimal : un `===` sur des octets
				// bruts est exact mais illisible dans un rapport, et un rapport
				// illisible ne sert à personne à trois heures du matin.
				&& self::hexa( $apres[ $etiquette ]['binaire'] ) === self::hexa( $avant[ $etiquette ]['binaire'] );

			$assertions[] = array(
				'libelle' => 'Fixture « ' . $etiquette . ' » identique après l\'aller-retour',
				'ok'      => $identique,
				'detail'  => 'contre : ' . (string) $fixture['pourquoi']
					. ' | avant texte=' . self::hexa( self::brut( $avant, $etiquette, 'texte' ) )
					. ' binaire=' . self::hexa( self::brut( $avant, $etiquette, 'binaire' ) )
					. ' | après texte=' . self::hexa( self::brut( $apres, $etiquette, 'texte' ) )
					. ' binaire=' . self::hexa( self::brut( $apres, $etiquette, 'binaire' ) ),
			);
		}

		return $assertions;
	}

	/**
	 * Projette le dump d'une archive.
	 *
	 * @param string $chemin_archive Chemin de l'archive.
	 *
	 * @return array{empreinte:string,tables:array<string,string>,normalisations:list<string>,exclusions:list<string>}|WP_Error
	 */
	private static function projeter_archive( string $chemin_archive ): array|WP_Error {
		$travail = dirname( $chemin_archive ) . '/.tmp-' . wp_generate_uuid4();

		if ( ! wp_mkdir_p( $travail ) ) {
			return new WP_Error( 'massifs_sauvegarde_repertoire', 'Répertoire de travail non créable.' );
		}

		$chemin_sql = $travail . '/' . DumpSql::NOM_FICHIER;

		try {
			$extraction = Archiveur::extraire( $chemin_archive, $travail, array( DumpSql::NOM_FICHIER ) );

			if ( is_wp_error( $extraction ) ) {
				return $extraction;
			}

			if ( ! is_file( $chemin_sql ) ) {
				return new WP_Error( 'massifs_sauvegarde_lecture', 'Le dump est absent de l\'archive.' );
			}

			$projection = self::projeter( $chemin_sql );
			$manifeste  = Manifeste::depuis_archive( $chemin_archive );

			if ( ! is_wp_error( $manifeste ) ) {
				$projection['exclusions'] = self::exclusions_nommees( $manifeste );
			}

			return $projection;
		} finally {
			// Le dump extrait contient TOUTE la base en clair : il ne survit pas à
			// la projection, quel que soit le chemin de sortie.
			if ( is_file( $chemin_sql ) ) {
				wp_delete_file( $chemin_sql );
			}

			if ( is_dir( $travail ) ) {
				rmdir( $travail );
			}
		}
	}

	/**
	 * Projection de comparaison d'un dump.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  EXACTEMENT TROIS NORMALISATIONS, ET ELLES SONT IMPRIMÉES À CHAQUE        │
	 * │  EXÉCUTION. RIEN D'AUTRE N'EST JAMAIS NORMALISÉ.                          │
	 * │                                                                           │
	 * │  C'EST DANS « EXCLU DE LA COMPARAISON » QUE SE CACHE UN FAUX VERT. UNE    │
	 * │  QUATRIÈME NORMALISATION AJOUTÉE « PARCE QUE ÇA DIVERGEAIT » NE FERAIT    │
	 * │  PAS DISPARAÎTRE LA DIVERGENCE : ELLE LA RENDRAIT INVISIBLE.              │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * Hachage table par table, en flux : la mémoire ne dépend pas de la taille du
	 * dump, et une divergence peut être NOMMÉE au lieu d'être seulement constatée.
	 *
	 * @param string $chemin_sql Chemin du dump.
	 *
	 * @return array{empreinte:string,tables:array<string,string>,normalisations:list<string>,exclusions:list<string>}
	 */
	public static function projeter( string $chemin_sql ): array {
		$normalisations = array(
			'AUTO_INCREMENT=<n> remplacé par un jeton fixe (dans la projection SEULEMENT, jamais dans l\'archive)',
			'ordre des tables ramené au tri lexical',
			'lignes exclues du dump, nommées ci-dessous',
		);

		$flux = fopen( $chemin_sql, 'rb' );

		if ( false === $flux ) {
			return array(
				'empreinte'      => '',
				'tables'         => array(),
				'normalisations' => $normalisations,
				'exclusions'     => array(),
			);
		}

		$hors_table = hash_init( 'sha256' );
		$contextes  = array();
		$courante   = '';

		while ( false !== ( $ligne = fgets( $flux ) ) ) {
			$ligne = rtrim( $ligne, "\r\n" );

			if ( str_starts_with( $ligne, DumpSql::MARQUEUR_TABLE ) ) {
				$courante = substr( $ligne, strlen( DumpSql::MARQUEUR_TABLE ) );

				if ( ! isset( $contextes[ $courante ] ) ) {
					$contextes[ $courante ] = hash_init( 'sha256' );
				}
			} elseif ( DumpSql::MARQUEUR_FIN_TABLES === $ligne ) {
				// Le pied revient au contexte hors-table : il n'appartient à aucune
				// table et ne doit pas changer de propriétaire selon le nom de la
				// dernière table rencontrée.
				$courante = '';
			}

			// NORMALISATION 1, la seule appliquée au contenu.
			$ligne = (string) preg_replace( '/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=<normalise>', $ligne );

			if ( '' === $courante ) {
				hash_update( $hors_table, $ligne . "\n" );

				continue;
			}

			hash_update( $contextes[ $courante ], $ligne . "\n" );
		}

		fclose( $flux );

		$tables = array();

		foreach ( $contextes as $nom => $contexte ) {
			$tables[ $nom ] = hash_final( $contexte );
		}

		// NORMALISATION 2 : l'ordre d'énumération des tables ne doit pas peser sur
		// la comparaison. Le moteur trie déjà à l'écriture ; ce tri-ci protège
		// contre un serveur qui énumérerait autrement.
		ksort( $tables, SORT_STRING );

		$total = hash_init( 'sha256' );

		hash_update( $total, hash_final( $hors_table ) );

		foreach ( $tables as $nom => $empreinte ) {
			hash_update( $total, $nom . ':' . $empreinte . "\n" );
		}

		return array(
			'empreinte'      => hash_final( $total ),
			'tables'         => $tables,
			'normalisations' => $normalisations,
			'exclusions'     => array(),
		);
	}

	/**
	 * Liste nommée des exclusions, telle que le manifeste la porte.
	 *
	 * @param array<string, mixed> $manifeste Manifeste relu.
	 *
	 * @return list<string>
	 */
	private static function exclusions_nommees( array $manifeste ): array {
		$liste  = array();
		$tables = isset( $manifeste['tables'] ) && is_array( $manifeste['tables'] ) ? $manifeste['tables'] : array();

		foreach ( $tables as $nom => $details ) {
			if ( ! is_array( $details ) || ! isset( $details['exclusions'] ) || ! is_array( $details['exclusions'] ) ) {
				continue;
			}

			foreach ( $details['exclusions'] as $exclusion ) {
				$liste[] = (string) $nom . ' : ' . (string) $exclusion;
			}
		}

		return $liste;
	}

	/**
	 * Tables dont l'empreinte diverge entre deux projections.
	 *
	 * @param array<string, mixed> $gauche Projection A.
	 * @param array<string, mixed> $droite Projection B.
	 *
	 * @return list<string>
	 */
	private static function tables_divergentes( array $gauche, array $droite ): array {
		$noms = array_unique( array_merge( array_keys( $gauche['tables'] ), array_keys( $droite['tables'] ) ) );
		$sale = array();

		foreach ( $noms as $nom ) {
			if ( ( $gauche['tables'][ $nom ] ?? '' ) !== ( $droite['tables'][ $nom ] ?? '' ) ) {
				$sale[] = (string) $nom;
			}
		}

		sort( $sale, SORT_STRING );

		return $sale;
	}

	/**
	 * Démonte les fixtures, le témoin et l'archive.
	 *
	 * @param string $archive Nom de l'archive à supprimer, chaîne vide pour la conserver.
	 */
	private static function demonter( string $archive ): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_encadree() );

		delete_option( self::OPTION_TEMOIN );

		if ( '' !== $archive ) {
			self::supprimer_archive( $archive );
		}
	}

	/**
	 * Supprime une archive produite par la vérification.
	 *
	 * @param string $nom Nom d'archive.
	 */
	private static function supprimer_archive( string $nom ): void {
		$chemin = Archives::chemin( $nom );

		if ( ! is_wp_error( $chemin ) ) {
			wp_delete_file( $chemin );
		}
	}

	/**
	 * Valeur brute d'une colonne de fixture, ou `false` si la ligne est absente.
	 *
	 * ┌──────────────────────────────────────────────────────────────────────────┐
	 * │  NE JAMAIS ÉCRIRE `$etat[ $etiquette ][ $colonne ] ?? false` ICI.         │
	 * │                                                                           │
	 * │  `??` REND SON REPLI QUAND LA VALEUR EXISTE ET VAUT `null` : UNE LIGNE    │
	 * │  CORRECTEMENT RESTAURÉE À `NULL` SERAIT RAPPORTÉE « ABSENTE ». LE         │
	 * │  RAPPORT DE LA SEULE COMMANDE QUI PROUVE QUELQUE CHOSE MENTIRAIT SUR LE   │
	 * │  CAS PRÉCIS QU'ELLE EXISTE POUR SURVEILLER. D'OÙ `array_key_exists`.      │
	 * └──────────────────────────────────────────────────────────────────────────┘
	 *
	 * @param array<string, array<string, mixed>> $etat      État lu.
	 * @param string                              $etiquette Étiquette de fixture.
	 * @param string                              $colonne   Colonne lue.
	 */
	private static function brut( array $etat, string $etiquette, string $colonne ): string|null|false {
		if ( ! isset( $etat[ $etiquette ] ) || ! array_key_exists( $colonne, $etat[ $etiquette ] ) ) {
			return false;
		}

		$valeur = $etat[ $etiquette ][ $colonne ];

		return null === $valeur ? null : (string) $valeur;
	}

	/**
	 * Représente une valeur pour un rapport, en hexadécimal.
	 *
	 * LES VALEURS DE FIXTURES SONT IMPRIMÉES EN `bin2hex()`, JAMAIS EN CLAIR : un
	 * octet nul, un retour chariot ou une séquence invalide écrits tels quels dans
	 * un terminal deviennent illisibles ou disparaissent, et un rapport où l'on ne
	 * distingue pas `''` de `NULL` ne prouve rien.
	 *
	 * @param string|null|false $valeur Valeur brute, `false` si absente.
	 */
	private static function hexa( string|null|false $valeur ): string {
		if ( false === $valeur ) {
			return 'ABSENT';
		}

		if ( null === $valeur ) {
			return 'NULL';
		}

		if ( '' === $valeur ) {
			return 'VIDE';
		}

		return '0x' . bin2hex( $valeur );
	}

	/**
	 * Décrit une valeur en toutes lettres, pour l'assertion sur `NULL`.
	 *
	 * @param string|null|false $valeur Valeur brute, `false` si absente.
	 */
	private static function decrire( string|null|false $valeur ): string {
		if ( false === $valeur ) {
			return 'ligne absente';
		}

		if ( null === $valeur ) {
			return 'NULL';
		}

		return '' === $valeur ? 'chaîne vide (le NULL a été écrasé)' : '0x' . bin2hex( $valeur );
	}

	/**
	 * Rend une `WP_Error` si la dernière requête a échoué.
	 *
	 * @param string $contexte Ce qui était en cours.
	 */
	private static function erreur_sql( string $contexte ): ?WP_Error {
		global $wpdb;

		$erreur = is_string( $wpdb->last_error ) ? trim( $wpdb->last_error ) : '';

		if ( '' === $erreur ) {
			return null;
		}

		return new WP_Error( 'massifs_sauvegarde_sql', 'Erreur SQL pendant ' . $contexte . ' : ' . $erreur );
	}
}
