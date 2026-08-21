/**
 * Primitives géométriques partagées de l'outillage de build du domaine.
 *
 * Extraites de `importer.mjs` le jour où un second consommateur est apparu
 * (`communes.mjs`, issue #45). Les recopier dans le second script aurait donné
 * deux projections locales, deux calculs d'aire et deux points-en-polygone qui
 * dériveraient l'un de l'autre — et les surfaces des massifs, celles des
 * intersections communales et les distances au bord ne se compareraient plus.
 *
 * Rien ici n'est spécifique aux massifs : ce fichier ne lit aucun artefact, ne
 * connaît aucun chemin et n'écrit rien.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

/** Arrêt volontaire d'un script de build : rien n'a été écrit. */
export class Arret extends Error {}

/**
 * Projection locale équirectangulaire dans laquelle TOUTES les distances et
 * surfaces de l'outillage sont mesurées, à la latitude de référence du
 * département.
 *
 * Les définitions consignées dans les artefacts de recette sont composées à
 * partir de ces constantes : une formule réécrite à la main dans la phrase
 * finirait par décrire une autre projection que celle qui a produit les mesures.
 */
export const LAT_REFERENCE = 43.5;
export const METRES_PAR_DEGRE_LAT = 110540;
export const METRES_PAR_DEGRE_LON_EQUATEUR = 111320;
export const METRES_PAR_DEGRE_LON =
	METRES_PAR_DEGRE_LON_EQUATEUR * Math.cos( ( LAT_REFERENCE * Math.PI ) / 180 );

/**
 * Décimales conservées sur les bbox et les centres publiés.
 *
 * C'est la précision que portent déjà les sources archivées (~1,1 m) : en écrire
 * davantage annoncerait une précision que la donnée n'a pas.
 */
export const DECIMALES_COORDONNEES = 5;

export function arrondir( valeur, decimales ) {
	const facteur = 10 ** decimales;
	return Math.round( valeur * facteur ) / facteur;
}

/** Découpe une géométrie GeoJSON en parties, chaque partie = [extérieur, ...trous]. */
export function parties( geometrie ) {
	if ( 'Polygon' === geometrie.type ) {
		return [ geometrie.coordinates ];
	}

	if ( 'MultiPolygon' === geometrie.type ) {
		return geometrie.coordinates;
	}

	throw new Arret( `Géométrie non surfacique : ${ geometrie.type }` );
}

export function projeter( [ lon, lat ] ) {
	return [ lon * METRES_PAR_DEGRE_LON, lat * METRES_PAR_DEGRE_LAT ];
}

export function aireAnneau( anneau ) {
	let somme = 0;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = projeter( anneau[ i ] );
		const [ xj, yj ] = projeter( anneau[ j ] );
		somme += xj * yi - xi * yj;
	}

	return Math.abs( somme ) / 2;
}

export function airePartie( partie ) {
	const trous = partie.slice( 1 ).reduce( ( total, trou ) => total + aireAnneau( trou ), 0 );
	return aireAnneau( partie[ 0 ] ) - trous;
}

export function aireGeometrie( geometrie ) {
	return parties( geometrie ).reduce( ( total, partie ) => total + airePartie( partie ), 0 );
}

export function anneaux( geometrie ) {
	return parties( geometrie ).flat();
}

export function bboxGeometrie( geometrie ) {
	const boite = { ouest: Infinity, sud: Infinity, est: -Infinity, nord: -Infinity };

	for ( const anneau of anneaux( geometrie ) ) {
		for ( const [ lon, lat ] of anneau ) {
			boite.ouest = Math.min( boite.ouest, lon );
			boite.est = Math.max( boite.est, lon );
			boite.sud = Math.min( boite.sud, lat );
			boite.nord = Math.max( boite.nord, lat );
		}
	}

	return boite;
}

export function centroideAnneau( anneau ) {
	let aire2 = 0;
	let cx = 0;
	let cy = 0;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];
		const croix = xj * yi - xi * yj;
		aire2 += croix;
		cx += ( xj + xi ) * croix;
		cy += ( yj + yi ) * croix;
	}

	if ( 0 === aire2 ) {
		return anneau[ 0 ];
	}

	return [ cx / ( 3 * aire2 ), cy / ( 3 * aire2 ) ];
}

export function dansAnneau( [ lon, lat ], anneau ) {
	let dedans = false;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const [ xi, yi ] = anneau[ i ];
		const [ xj, yj ] = anneau[ j ];

		if ( yi > lat !== yj > lat && lon < ( ( xj - xi ) * ( lat - yi ) ) / ( yj - yi ) + xi ) {
			dedans = ! dedans;
		}
	}

	return dedans;
}

export function dansPartie( point, partie ) {
	if ( ! dansAnneau( point, partie[ 0 ] ) ) {
		return false;
	}

	return ! partie.slice( 1 ).some( ( trou ) => dansAnneau( point, trou ) );
}

/**
 * Point intérieur représentatif : milieu du plus long segment intérieur de la
 * ligne horizontale passant par le centroïde. Le centre ancre les étiquettes de
 * la carte ; posé hors du polygone sur un massif concave ou troué, il pointerait
 * un massif voisin.
 */
export function pointInterieur( partie ) {
	const centroide = centroideAnneau( partie[ 0 ] );

	if ( dansPartie( centroide, partie ) ) {
		return centroide;
	}

	const lat = centroide[ 1 ];
	const abscisses = [];

	for ( const anneau of partie ) {
		for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
			const [ xi, yi ] = anneau[ i ];
			const [ xj, yj ] = anneau[ j ];

			if ( yi > lat !== yj > lat ) {
				abscisses.push( ( ( xj - xi ) * ( lat - yi ) ) / ( yj - yi ) + xi );
			}
		}
	}

	abscisses.sort( ( a, b ) => a - b );

	let meilleur = centroide;
	let largeur = -1;

	for ( let i = 0; i + 1 < abscisses.length; i += 2 ) {
		const milieu = ( abscisses[ i ] + abscisses[ i + 1 ] ) / 2;

		if ( abscisses[ i + 1 ] - abscisses[ i ] > largeur && dansPartie( [ milieu, lat ], partie ) ) {
			largeur = abscisses[ i + 1 ] - abscisses[ i ];
			meilleur = [ milieu, lat ];
		}
	}

	return meilleur;
}

/** bbox + centre d'un massif, calculés sur la géométrie PRÉCISE, jamais sur la simplifiée. */
export function mesurerMassif( geometrie ) {
	const listeParties = parties( geometrie );
	const principale = listeParties.reduce( ( a, b ) => ( airePartie( b ) > airePartie( a ) ? b : a ) );
	const [ lon, lat ] = pointInterieur( principale );
	const boite = bboxGeometrie( geometrie );

	return {
		bbox: {
			ouest: arrondir( boite.ouest, DECIMALES_COORDONNEES ),
			sud: arrondir( boite.sud, DECIMALES_COORDONNEES ),
			est: arrondir( boite.est, DECIMALES_COORDONNEES ),
			nord: arrondir( boite.nord, DECIMALES_COORDONNEES ),
		},
		centre: {
			lon: arrondir( lon, DECIMALES_COORDONNEES ),
			lat: arrondir( lat, DECIMALES_COORDONNEES ),
		},
	};
}

export function distancePointSegment( p, a, b ) {
	const [ px, py ] = projeter( p );
	const [ ax, ay ] = projeter( a );
	const [ bx, by ] = projeter( b );
	const dx = bx - ax;
	const dy = by - ay;
	const carre = dx * dx + dy * dy;
	let t = 0;

	if ( carre > 0 ) {
		t = Math.max( 0, Math.min( 1, ( ( px - ax ) * dx + ( py - ay ) * dy ) / carre ) );
	}

	const ex = ax + t * dx - px;
	const ey = ay + t * dy - py;

	return Math.sqrt( ex * ex + ey * ey );
}

export function distanceAnneau( point, anneau ) {
	let minimum = Infinity;

	for ( let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i++ ) {
		const distance = distancePointSegment( point, anneau[ j ], anneau[ i ] );

		if ( distance < minimum ) {
			minimum = distance;
		}
	}

	return minimum;
}
