/**
 * Encodeur PNG-8 indexé, minimal et déterministe.
 *
 * POURQUOI ÉCRIRE L'ENCODEUR PLUTÔT QUE D'APPELER UN QUANTIFICATEUR TOUT FAIT
 *
 * Trois exigences du contrat #9 le demandent, et aucune bibliothèque ne les
 * offre ensemble :
 *
 *   - I-9.2 — « aucune métadonnée d'image (`tEXt`, `iTXt`, `XMP`) ». Ici, seuls
 *     `IHDR`, `PLTE`, `IDAT` et `IEND` sont émis : l'absence de métadonnée est
 *     STRUCTURELLE, pas le résultat d'une option qu'une mise à jour pourrait
 *     changer d'avis ;
 *   - la palette doit être FERMÉE et RECALCULABLE par la recette. Un
 *     quantificateur adaptatif choisit ses couleurs selon l'image : la recette
 *     ne pourrait plus rien contrôler que l'image contre elle-même ;
 *   - l'encodage doit être déterministe. `deflateSync` l'est ; un binaire natif
 *     de quantification ne l'est pas d'une plateforme à l'autre.
 *
 * La rasterisation, elle, reste native (`resvg`) : voir le README, la
 * reproductibilité binaire inter-plateformes n'est PAS revendiquée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { deflateSync } from 'node:zlib';

const SIGNATURE = Buffer.from( [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ] );

/** Chunks autorisés dans nos artefacts. Toute autre présence est un défaut, pas une variante. */
export const CHUNKS_ATTENDUS = Object.freeze( [ 'IHDR', 'PLTE', 'IDAT', 'IEND' ] );

const TABLE_CRC = ( () => {
	const table = new Int32Array( 256 );

	for ( let n = 0; n < 256; n += 1 ) {
		let c = n;

		for ( let k = 0; k < 8; k += 1 ) {
			c = 1 === ( c & 1 ) ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
		}

		table[ n ] = c;
	}

	return table;
} )();

function crc32( donnees ) {
	let c = -1;

	for ( let i = 0; i < donnees.length; i += 1 ) {
		c = TABLE_CRC[ ( c ^ donnees[ i ] ) & 0xff ] ^ ( c >>> 8 );
	}

	return ( c ^ -1 ) >>> 0;
}

function chunk( type, corps ) {
	const longueur = Buffer.alloc( 4 );
	longueur.writeUInt32BE( corps.length, 0 );

	const typeEtCorps = Buffer.concat( [ Buffer.from( type, 'ascii' ), corps ] );
	const controle = Buffer.alloc( 4 );
	controle.writeUInt32BE( crc32( typeEtCorps ), 0 );

	return Buffer.concat( [ longueur, typeEtCorps, controle ] );
}

/**
 * Encode une image indexée en PNG-8.
 *
 * Le filtre de ligne est systématiquement `0` (aucun). Sur des index de palette,
 * les filtres différentiels de PNG produisent des différences d'INDEX, qui n'ont
 * aucun sens de voisinage : ils dégradent la compression au lieu de l'améliorer.
 *
 * @param {Uint8Array}  indices  Un index de palette par pixel, en balayage ligne par ligne.
 * @param {number}      largeur  Largeur en pixels.
 * @param {number}      hauteur  Hauteur en pixels.
 * @param {number[][]}  palette  Triplets RGB, 256 entrées au plus.
 * @return {Buffer}
 */
export function encoderPng8( indices, largeur, hauteur, palette ) {
	if ( palette.length > 256 ) {
		throw new Error( `Palette de ${ palette.length } couleurs : le format indexé en accepte 256 au plus.` );
	}

	if ( indices.length !== largeur * hauteur ) {
		throw new Error( `Image de ${ indices.length } index pour ${ largeur }x${ hauteur } pixels attendus.` );
	}

	const entete = Buffer.alloc( 13 );
	entete.writeUInt32BE( largeur, 0 );
	entete.writeUInt32BE( hauteur, 4 );
	entete[ 8 ] = 8; // Profondeur de bits.
	entete[ 9 ] = 3; // Type couleur : indexé.
	entete[ 10 ] = 0; // Compression : deflate.
	entete[ 11 ] = 0; // Filtrage : adaptatif standard.
	entete[ 12 ] = 0; // Entrelacement : aucun.

	const plte = Buffer.alloc( palette.length * 3 );

	for ( let i = 0; i < palette.length; i += 1 ) {
		plte[ i * 3 ] = palette[ i ][ 0 ];
		plte[ i * 3 + 1 ] = palette[ i ][ 1 ];
		plte[ i * 3 + 2 ] = palette[ i ][ 2 ];
	}

	const brut = Buffer.alloc( hauteur * ( largeur + 1 ) );

	for ( let ligne = 0; ligne < hauteur; ligne += 1 ) {
		brut[ ligne * ( largeur + 1 ) ] = 0;
		brut.set( indices.subarray( ligne * largeur, ( ligne + 1 ) * largeur ), ligne * ( largeur + 1 ) + 1 );
	}

	return Buffer.concat( [
		SIGNATURE,
		chunk( 'IHDR', entete ),
		chunk( 'PLTE', plte ),
		chunk( 'IDAT', deflateSync( brut, { level: 9 } ) ),
		chunk( 'IEND', Buffer.alloc( 0 ) ),
	] );
}

/**
 * Types de chunks présents dans un PNG, dans l'ordre du fichier.
 *
 * Sert à la recette : c'est ce qui rend « aucune métadonnée d'image » VÉRIFIABLE
 * sur le binaire, et non affirmée sur l'intention.
 *
 * @param {Buffer} octets Fichier PNG.
 * @return {string[]}
 */
export function chunksPng( octets ) {
	if ( ! octets.subarray( 0, 8 ).equals( SIGNATURE ) ) {
		throw new Error( 'Signature PNG absente.' );
	}

	const types = [];
	let position = 8;

	while ( position + 8 <= octets.length ) {
		const longueur = octets.readUInt32BE( position );
		const type = octets.toString( 'ascii', position + 4, position + 8 );

		types.push( type );
		position += 12 + longueur;

		if ( 'IEND' === type ) {
			break;
		}
	}

	return types;
}
