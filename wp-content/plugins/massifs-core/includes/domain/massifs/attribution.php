<?php
/**
 * Attribution §9 des périmètres, et lacunes assumées du référentiel.
 *
 * La phrase d'attribution est une DONNÉE, pas de la rédaction de thème : la
 * Licence Ouverte 2.0 impose la citation exacte de la source et de la date de
 * mise à jour, et cette date appartient au référentiel. Trois consommateurs qui
 * l'assembleraient chacun de leur côté produiraient trois variantes, dont deux
 * non conformes. Le thème reçoit la phrase et ne fait que la placer et l'échapper.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Massifs\Domain\Massifs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/etats.php';
require_once __DIR__ . '/referentiel.php';

/**
 * Mention de source à afficher en pied de carte et en mentions légales.
 *
 * Les URLs sont brutes : c'est le thème qui les passe à `esc_url()`.
 *
 * @return array{phrase:string,phrase_courte:string,lien_source:string,lien_licence:string,faits:array<string,string>}
 */
function attribution(): array {
	$meta        = donnees()['meta'];
	$attribution = bloc( $meta, 'attribution' );
	$source      = bloc( $meta, 'source' );
	$licence     = bloc( $meta, 'licence' );

	return array(
		'phrase'        => texte( $attribution, 'phrase' ),
		'phrase_courte' => texte( $attribution, 'phrase_courte' ),
		'lien_source'   => texte( $attribution, 'lien_source' ),
		'lien_licence'  => texte( $attribution, 'lien_licence' ),
		// Les faits sont énumérés un à un, et non recopiés en bloc : toutes les
		// clés du contrat restent présentes même quand le référentiel est absent,
		// pour que le thème n'écrive jamais `isset()`.
		'faits'         => array(
			'producteur'          => texte( $source, 'producteur' ),
			'jeu_de_donnees'      => texte( $source, 'jeu_de_donnees' ),
			'couche'              => texte( $source, 'couche' ),
			'dataset_id'          => texte( $source, 'dataset_id' ),
			'geoide_id'           => texte( $source, 'geoide_id' ),
			'donnees_du'          => texte( $source, 'donnees_du' ),
			'donnees_du_libelle'  => texte( $source, 'donnees_du_libelle' ),
			'recupere_le'         => texte( $source, 'recupere_le' ),
			'sha256_source'       => texte( $source, 'sha256' ),
			'licence_nom'         => texte( $licence, 'nom' ),
			'licence_version'     => texte( $licence, 'version' ),
			'licence_identifiant' => texte( $licence, 'identifiant' ),
			'crs_source'          => texte( $source, 'crs_source' ),
			'crs_publie'          => texte( $source, 'crs_publie' ),
			'base_reglementaire'  => texte( $source, 'base_reglementaire' ),
		),
	);
}

/**
 * Lacunes connues et citables du référentiel.
 *
 * `communes` est vide parce que l'attribut n'existe nulle part dans la couche
 * source, et non parce qu'aucune commune ne serait concernée : le thème omet la
 * ligne, il n'écrit jamais « aucune commune ».
 *
 * @return array{communes:array{statut:string,raison:string,source_pressentie:string}}
 */
function lacunes(): array {
	$communes = bloc( bloc( donnees()['meta'], 'lacunes' ), 'communes' );

	return array(
		// Repli sur « inconnue » et non sur une chaîne vide : c'est la valeur qui
		// ne peut jamais être relue comme « aucune commune concernée ».
		'communes' => array(
			'statut'            => texte( $communes, 'statut', STATUT_COMMUNES_DEFAUT ),
			'raison'            => texte( $communes, 'raison' ),
			'source_pressentie' => texte( $communes, 'source_pressentie' ),
		),
	);
}
