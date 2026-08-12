<?php
/**
 * Chargement et exposition de la légende.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * La légende, chargée depuis `legende.config.php`.
 *
 * AUCUN FILTRE WORDPRESS N'ALTÈRE LA LÉGENDE : un filtre laisserait fabriquer
 * une légende non officielle depuis n'importe quel greffon ou fichier de thème,
 * ce que le §4.2 du brief interdit. La seule façon de changer la légende est de
 * modifier le fichier de configuration versionné.
 */
final class Legende {

	/**
	 * Instance mémoïsée pour la durée du processus.
	 */
	private static ?self $instance = null;

	/**
	 * Configuration brute.
	 *
	 * @var array<string, mixed>
	 */
	private array $configuration;

	/**
	 * Niveaux d'accès au massif indexés par clé, ordonnés par sévérité croissante.
	 *
	 * @var array<string, Niveau>
	 */
	private array $niveaux;

	/**
	 * Entrées ZAPEF indexées par clé, ordonnées par sévérité croissante.
	 *
	 * @var array<string, Niveau>
	 */
	private array $zapef;

	/**
	 * Construit la légende.
	 *
	 * @param array<string, mixed>  $configuration Configuration brute.
	 * @param array<string, Niveau> $niveaux       Niveaux indexés par clé.
	 * @param array<string, Niveau> $zapef         Entrées ZAPEF indexées par clé.
	 */
	private function __construct( array $configuration, array $niveaux, array $zapef ) {
		$this->configuration = $configuration;
		$this->niveaux       = $niveaux;
		$this->zapef         = $zapef;
	}

	/**
	 * Légende courante.
	 *
	 * La mémoïsation est une simple variable statique de processus : la légende
	 * est un fichier de configuration versionné, jamais un contenu de base de
	 * données, et ne justifie donc aucun transient.
	 */
	public static function chargee(): self {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		$configuration = require __DIR__ . '/legende.config.php';

		if ( ! is_array( $configuration ) ) {
			$configuration = array();
		}

		self::$instance = new self(
			$configuration,
			self::indexer( $configuration, 'niveaux' ),
			self::indexer( $configuration, 'zapef' )
		);

		return self::$instance;
	}

	/**
	 * Indexe une liste de la configuration par clé, triée par sévérité croissante.
	 *
	 * Les deux dimensions publiées — accès au massif et ZAPEF — partagent
	 * exactement la même forme d'entrée : une seule fabrique, donc aucun risque de
	 * voir les deux dériver l'une de l'autre.
	 *
	 * @param array<string, mixed> $configuration Configuration brute.
	 * @param string               $liste         Nom de la liste à indexer.
	 *
	 * @return array<string, Niveau>
	 */
	private static function indexer( array $configuration, string $liste ): array {
		$entrees = isset( $configuration[ $liste ] ) && is_array( $configuration[ $liste ] )
			? array_values( $configuration[ $liste ] )
			: array();

		// La configuration est déjà ordonnée ; le tri garantit l'ordre de sévérité
		// croissante quelle que soit l'écriture du fichier.
		usort(
			$entrees,
			static function ( array $gauche, array $droite ): int {
				return ( (int) ( $gauche['severite'] ?? 0 ) ) <=> ( (int) ( $droite['severite'] ?? 0 ) );
			}
		);

		$total   = count( $entrees );
		$indexes = array();
		$rang    = 0;

		foreach ( $entrees as $entree ) {
			++$rang;
			$niveau = Niveau::depuis_configuration( $entree, $rang, $total );

			if ( '' === $niveau->cle ) {
				continue;
			}

			$indexes[ $niveau->cle ] = $niveau;
		}

		return $indexes;
	}

	/**
	 * La légende reproduit-elle des valeurs officielles vérifiées ?
	 */
	public function est_confirmee(): bool {
		return true === ( $this->configuration['confirme'] ?? false );
	}

	/**
	 * Le dispositif publie-t-il des consignes par état ?
	 *
	 * `false` est un FAIT relevé, pas une donnée manquante : la légende officielle
	 * du 13 ne porte aucune consigne. Le consommateur n'affiche donc aucun
	 * intitulé « Consigne ».
	 */
	public function consignes_publiees(): bool {
		return true === ( $this->configuration['consignes_publiees'] ?? false );
	}

	/**
	 * Heure locale de publication, la veille du jour de validité.
	 */
	public function publication_heure(): string {
		return (string) ( $this->configuration['publication_heure'] ?? '' );
	}

	/**
	 * Révision de la légende.
	 */
	public function revision(): string {
		return (string) ( $this->configuration['revision'] ?? '' );
	}

	/**
	 * Mention de source de la légende.
	 */
	public function source(): string {
		return (string) ( $this->configuration['source'] ?? '' );
	}

	/**
	 * Adresse de la carte officielle.
	 */
	public function source_officielle_url(): string {
		return (string) ( $this->configuration['source_officielle_url'] ?? '' );
	}

	/**
	 * Niveaux ordonnés par sévérité croissante.
	 *
	 * @return list<Niveau>
	 */
	public function niveaux(): array {
		return array_values( $this->niveaux );
	}

	/**
	 * Clés des niveaux, ordonnées par sévérité croissante.
	 *
	 * @return list<string>
	 */
	public function cles(): array {
		return array_keys( $this->niveaux );
	}

	/**
	 * Niveau correspondant à une clé, ou `null`.
	 *
	 * @param string $cle Clé texte du niveau.
	 */
	public function niveau( string $cle ): ?Niveau {
		return $this->niveaux[ $cle ] ?? null;
	}

	/**
	 * La clé existe-t-elle dans la légende courante ?
	 *
	 * @param string $cle Clé texte du niveau.
	 */
	public function existe( string $cle ): bool {
		return isset( $this->niveaux[ $cle ] );
	}

	/**
	 * Entrées ZAPEF ordonnées par sévérité croissante.
	 *
	 * @return list<Niveau>
	 */
	public function zapef(): array {
		return array_values( $this->zapef );
	}

	/**
	 * Entrée ZAPEF correspondant à une clé, ou `null`.
	 *
	 * @param string $cle Clé texte de l'entrée ZAPEF.
	 */
	public function zapef_entree( string $cle ): ?Niveau {
		return $this->zapef[ $cle ] ?? null;
	}

	/**
	 * La clé existe-t-elle dans la dimension ZAPEF ?
	 *
	 * @param string $cle Clé texte de l'entrée ZAPEF.
	 */
	public function zapef_existe( string $cle ): bool {
		return isset( $this->zapef[ $cle ] );
	}

	/**
	 * Note de bas de légende de la dimension ZAPEF.
	 */
	public function zapef_note(): string {
		return (string) ( $this->configuration['zapef_note'] ?? '' );
	}

	/**
	 * Projette un `level` brut de la source sur les clés affichées.
	 *
	 * SEUL point de traduction entre l'entier de la source et notre vocabulaire.
	 * Un `level` absent de la table n'est jamais projeté par défaut : il retourne
	 * `null`, et l'appelant refuse la donnée plutôt que d'en inventer une.
	 *
	 * @param int $niveau_source `level` brut tel que la source l'émet.
	 *
	 * @return array{niveau_cle: string|null, zapef_cle: string|null}|null
	 */
	public function projeter_source( int $niveau_source ): ?array {
		$table = isset( $this->configuration['correspondance_source'] ) && is_array( $this->configuration['correspondance_source'] )
			? $this->configuration['correspondance_source']
			: array();

		if ( ! isset( $table[ $niveau_source ] ) || ! is_array( $table[ $niveau_source ] ) ) {
			return null;
		}

		$entree = $table[ $niveau_source ];

		$niveau_cle = isset( $entree['niveau_cle'] ) && is_string( $entree['niveau_cle'] ) ? $entree['niveau_cle'] : null;
		$zapef_cle  = isset( $entree['zapef_cle'] ) && is_string( $entree['zapef_cle'] ) ? $entree['zapef_cle'] : null;

		// Une table qui désignerait une clé absente des listes serait une erreur de
		// configuration : mieux vaut refuser la projection que peindre un massif
		// avec un niveau qui n'existe pas.
		if ( ( null !== $niveau_cle && ! $this->existe( $niveau_cle ) )
			|| ( null !== $zapef_cle && ! $this->zapef_existe( $zapef_cle ) ) ) {
			return null;
		}

		return array(
			'niveau_cle' => $niveau_cle,
			'zapef_cle'  => $zapef_cle,
		);
	}

	/**
	 * Liste blanche des `level` bruts acceptables en entrée.
	 *
	 * @return list<int>
	 */
	public function niveaux_source_autorises(): array {
		return $this->entiers( 'niveaux_source_autorises' );
	}

	/**
	 * Liste blanche des valeurs de `procedure` acceptables en entrée.
	 *
	 * @return list<int>
	 */
	public function procedures_source_autorisees(): array {
		return $this->entiers( 'procedures_source_autorisees' );
	}

	/**
	 * Liste d'entiers de la configuration, assainie.
	 *
	 * @param string $cle Nom de la liste.
	 *
	 * @return list<int>
	 */
	private function entiers( string $cle ): array {
		$brut = isset( $this->configuration[ $cle ] ) && is_array( $this->configuration[ $cle ] )
			? $this->configuration[ $cle ]
			: array();

		$valeurs = array();

		foreach ( $brut as $valeur ) {
			if ( is_int( $valeur ) ) {
				$valeurs[] = $valeur;
			}
		}

		return array_values( array_unique( $valeurs ) );
	}

	/**
	 * Bornes calendaires du dispositif.
	 *
	 * @return array{debut_mois: int, debut_jour: int, fin_mois: int, fin_jour: int, confirme: bool}
	 */
	public function bornes_saison(): array {
		$saison = isset( $this->configuration['saison'] ) && is_array( $this->configuration['saison'] )
			? $this->configuration['saison']
			: array();

		return array(
			'debut_mois' => (int) ( $saison['debut_mois'] ?? 6 ),
			'debut_jour' => (int) ( $saison['debut_jour'] ?? 1 ),
			'fin_mois'   => (int) ( $saison['fin_mois'] ?? 9 ),
			'fin_jour'   => (int) ( $saison['fin_jour'] ?? 30 ),
			'confirme'   => true === ( $saison['confirme'] ?? false ),
		);
	}

	/**
	 * États hors niveau : structure seulement, aucune phrase.
	 *
	 * `jeton_encre_css` est l'encre du MOTIF, jamais une encre de texte. Elle est
	 * exposée pour les trois états afin que le consommateur n'ait aucun cas
	 * particulier à écrire ; sa valeur est vide seulement si la configuration ne
	 * la déclare pas.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function etats_hors_niveau(): array {
		$etats  = isset( $this->configuration['etats_hors_niveau'] ) && is_array( $this->configuration['etats_hors_niveau'] )
			? $this->configuration['etats_hors_niveau']
			: array();
		$sortie = array();

		foreach ( $etats as $cle => $etat ) {
			if ( ! is_array( $etat ) ) {
				continue;
			}

			$sortie[ (string) $cle ] = array(
				'cle'             => (string) ( $etat['cle'] ?? $cle ),
				'motif'           => (string) ( $etat['motif'] ?? 'aucun' ),
				'jeton_css'       => (string) ( $etat['jeton_css'] ?? '' ),
				'jeton_encre_css' => (string) ( $etat['jeton_encre_css'] ?? '' ),
			);
		}

		return $sortie;
	}

	/**
	 * Forme exposée aux consommateurs.
	 *
	 * @return array<string, mixed>
	 */
	public function en_tableau(): array {
		$niveaux = array();

		foreach ( $this->niveaux() as $niveau ) {
			$niveaux[] = $niveau->en_tableau();
		}

		$zapef = array();

		foreach ( $this->zapef() as $entree ) {
			$zapef[] = $entree->en_tableau();
		}

		// La table de correspondance `level` → clés et les listes blanches ne sont
		// PAS exposées ici : ce sont des règles d'entrée, jamais une donnée
		// d'affichage. Le consommateur ne doit jamais traduire un entier lui-même.
		return array(
			'confirmee'             => $this->est_confirmee(),
			'consignes_publiees'    => $this->consignes_publiees(),
			'revision'              => $this->revision(),
			'source'                => $this->source(),
			'source_officielle_url' => $this->source_officielle_url(),
			'publication_heure'     => $this->publication_heure(),
			'niveaux'               => $niveaux,
			'zapef'                 => $zapef,
			'zapef_note'            => $this->zapef_note(),
			'etats_hors_niveau'     => $this->etats_hors_niveau(),
		);
	}
}
