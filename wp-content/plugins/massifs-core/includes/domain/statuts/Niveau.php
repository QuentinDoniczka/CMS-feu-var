<?php
/**
 * Objet valeur : un niveau de la légende.
 *
 * @package Massifs
 */

declare(strict_types=1);

namespace Massifs\Domain\Statuts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Un niveau de la légende officielle.
 *
 * La même forme sert les DEUX dimensions publiées — accès au massif et ZAPEF —
 * qui portent deux entrées chacune : la légende du 13 est binaire, jamais
 * graduée. Les cinq crans de la révision 1 étaient des substituts de travail.
 *
 * `severite` est comparable et croissante ; elle n'est ni une identité (c'est
 * `cle` qui l'est) ni un rang. `rang` et `total` n'existent que pour l'affichage
 * (« 2 sur 2 ») : ils ne sont jamais persistés ni comparés entre deux dates.
 */
final class Niveau {

	/**
	 * Construit un niveau.
	 *
	 * @param string $cle             Clé texte stable, persistée telle quelle.
	 * @param string $libelle         Libellé de la légende.
	 * @param string $consigne        Consigne officielle, chaîne vide tant que non confirmée.
	 * @param int    $severite        Sévérité croissante.
	 * @param string $motif           Clé de motif (l'information ne repose jamais sur la couleur seule).
	 * @param string $jeton_css       Nom du jeton CSS de l'aplat.
	 * @param string $jeton_encre_css Nom du jeton CSS de l'encre posée sur l'aplat.
	 * @param int    $rang            Position 1-based, affichage seulement.
	 * @param int    $total           Nombre d'entrées de la dimension courante.
	 */
	public function __construct(
		public readonly string $cle,
		public readonly string $libelle,
		public readonly string $consigne,
		public readonly int $severite,
		public readonly string $motif,
		public readonly string $jeton_css,
		public readonly string $jeton_encre_css,
		public readonly int $rang,
		public readonly int $total
	) {}

	/**
	 * Construit un niveau depuis une entrée de `legende.config.php`.
	 *
	 * @param array<string, mixed> $entree Entrée de configuration.
	 * @param int                  $rang   Position 1-based.
	 * @param int                  $total  Nombre d'entrées de la dimension courante.
	 */
	public static function depuis_configuration( array $entree, int $rang, int $total ): self {
		return new self(
			isset( $entree['cle'] ) ? (string) $entree['cle'] : '',
			isset( $entree['libelle'] ) ? (string) $entree['libelle'] : '',
			isset( $entree['consigne'] ) ? (string) $entree['consigne'] : '',
			isset( $entree['severite'] ) ? (int) $entree['severite'] : 0,
			isset( $entree['motif'] ) ? (string) $entree['motif'] : 'aucun',
			isset( $entree['jeton_css'] ) ? (string) $entree['jeton_css'] : '',
			isset( $entree['jeton_encre_css'] ) ? (string) $entree['jeton_encre_css'] : '',
			$rang,
			$total
		);
	}

	/**
	 * Forme exposée aux consommateurs.
	 *
	 * @return array<string, int|string>
	 */
	public function en_tableau(): array {
		return array(
			'cle'             => $this->cle,
			'libelle'         => $this->libelle,
			'consigne'        => $this->consigne,
			'severite'        => $this->severite,
			'motif'           => $this->motif,
			'jeton_css'       => $this->jeton_css,
			'jeton_encre_css' => $this->jeton_encre_css,
			'rang'            => $this->rang,
			'total'           => $this->total,
		);
	}
}
