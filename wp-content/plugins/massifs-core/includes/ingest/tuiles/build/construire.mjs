/**
 * Génération du fond de carte auto-hébergé.
 *
 *   node construire.mjs      (ou : npm run construire)
 *
 * TOUJOURS HORS LIGNE. Ce script ne connaît pas le réseau et n'a jamais
 * `recuperer.mjs` pour prérequis : il consomme l'archive COMMITÉE sous
 * `source/`. `git clone` puis `npm run construire` suffisent (§11 du brief).
 *
 * Deux artefacts, un seul pipeline :
 *
 *   - la pyramide `data/tuiles/<version>/{z}/{x}/{y}.png`, z5 à z12 ;
 *   - l'image statique `themes/massifs/assets/img/carte-statique.png`, repli
 *     sans JavaScript, qui ne porte JAMAIS les statuts du jour (I-9.3).
 *
 * MODE DÉGRADÉ (I-9.9) — sans archive OSM lisible, le script produit QUAND MÊME
 * l'image statique, depuis la seule géométrie des massifs que nous possédons hors
 * ligne, et n'émet AUCUNE tuile : 295 aplats uniformes seraient une carte qui
 * affirme quelque chose de faux sur la géographie. Il sort en code 0, et c'est
 * `npm run verifier` qui refuse un artefact dégradé — constructible en local,
 * jamais commitable en silence.
 *
 * ÉMISSION ATOMIQUE — rien n'est posé tant que tout n'est pas produit et
 * contrôlé. Un build à moitié appliqué laisserait des tuiles neuves et des
 * métadonnées anciennes, donc une URL qui ment.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import fs from 'node:fs';
import path from 'node:path';
import { Resvg } from '@resvg/resvg-js';
import sharp from 'sharp';
import {
	ATTRIBUTION,
	Arret,
	CHEMINS,
	CLASSES_TOPONYMES,
	COUCHES,
	COUCHES_STATIQUE,
	COUCHE_TOPONYMES,
	DESSIN,
	EMPRISE_DECLAREE,
	FORMAT,
	JETONS_CARTE,
	JETON_CONTOUR,
	LARGEUR_STATIQUE,
	MARGE_MIN_DEG,
	MODE_COMPLET,
	MODE_DEGRADE,
	NORMALISATION,
	PLAFOND_STATIQUE_OCTETS,
	RACINE,
	SCHEMA,
	SELECTEURS,
	TAILLE_TUILE,
	TOPONYMES,
	ZOOM_MAX,
	ZOOM_MIN,
	airePlacementMpx,
	bboxDeclaree,
	controlerPoint,
	divergencesJetons,
	ecrireFc,
	grillesDeclarees,
	jetonVersion,
	jsonCanonique,
	lireEmprise,
	lireFc,
	lireJetons,
	luma,
	mapshaper,
	metresParPixel,
	nodeMajeur,
	normX,
	normY,
	paletteAutorisee,
	quantificateur,
	relatifAuDepot,
	sha256,
	versHexadecimal,
	versRgb,
	versionMapshaper,
} from './commun.mjs';
import { ouvrirPolice, reposerJeu, resoudrePlacement, svgEtiquettes } from './etiquettes.mjs';
import { encoderPng8 } from './png8.mjs';
import { flottant, rendreValeur } from './php.mjs';

/** Rayon de la sphère Web Mercator, en mètres. */
const RAYON = 6378137;

/**
 * Masque de travail des intérieurs de massif — noir sur blanc, jamais un artefact.
 *
 * Deux couleurs volontairement HORS de la palette fermée : le masque est rasterisé
 * en mémoire pour répondre à une question booléenne (« ce pixel est-il dans un
 * massif ? »), lu, puis jeté. Aucun de ses octets n'atteint la pyramide ni l'image
 * statique. Le seuil est le milieu de l'échelle 0-255 : le masque n'a que deux
 * teintes, seule sa frange d'anticrénelage se trouve entre les deux.
 */
const MASQUE_DEHORS = '#FFFFFF';
const MASQUE_DEDANS = '#000000';
const MASQUE_SEUIL = 128;

/** Répertoire de travail : sous `build/`, jamais sous `data/`, qui est servi. */
const TRAVAIL = path.join( RACINE, '_travail' );

/** Répertoire d'émission de la pyramide, renommé en bloc à la toute fin. */
const EMISSION = path.join( CHEMINS.tuiles, '_emission' );

const avertissements = [];

function journal( ligne ) {
	process.stdout.write( `${ ligne }\n` );
}

function avertir( ligne ) {
	avertissements.push( ligne );
}

/* -------------------------------------------------------------------------- */
/* Entrées                                                                     */
/* -------------------------------------------------------------------------- */

/**
 * Contrôle des jetons — invariant I-9.7, joué AVANT tout le reste.
 *
 * Le monochrome est cuit ici. Si `tokens.css` a bougé, tout ce qui suit
 * peindrait des couleurs que le site ne connaît plus : autant s'arrêter à la
 * première ligne plutôt qu'après la rasterisation.
 */
function controlerJetons() {
	const jetons = lireJetons( CHEMINS.tokens );
	const divergences = divergencesJetons( jetons );

	if ( divergences.length > 0 ) {
		throw new Arret(
			`Jetons de couleur divergents dans ${ relatifAuDepot( CHEMINS.tokens ) } :\n  - ${ divergences.join( '\n  - ' ) }\n` +
				'Le monochrome est CUIT à la génération (D-01, §4.2 de MASTER.md) : le build refuse de peindre des ' +
				'couleurs que la feuille de jetons ne déclare plus. Réaligner l\'un ou l\'autre, par une décision écrite.'
		);
	}

	return jetons;
}

/**
 * Les contours de massifs, lus dans l'artefact géométrique publié.
 *
 * Le `code` accompagne la géométrie pour une seule raison, et elle suffit : le
 * contrôle de débordement doit pouvoir NOMMER le massif fautif. Un message qui
 * dirait « un sommet déborde de 0,004° » sans dire lequel obligerait à rouvrir
 * la géométrie à la main.
 */
function lireContours() {
	if ( ! fs.existsSync( CHEMINS.geometrie ) ) {
		throw new Arret(
			`Géométrie des massifs introuvable : ${ relatifAuDepot( CHEMINS.geometrie ) }. C'est la SEULE entrée ` +
				'dont le mode dégradé ne peut pas se passer : sans elle, l\'image statique ne porterait rien.'
		);
	}

	const brut = JSON.parse( fs.readFileSync( CHEMINS.geometrie, 'utf8' ) );

	if ( ! brut || 'FeatureCollection' !== brut.type || ! Array.isArray( brut.features ) || 0 === brut.features.length ) {
		throw new Arret( `Géométrie des massifs illisible : ${ relatifAuDepot( CHEMINS.geometrie ) }` );
	}

	return brut.features
		.filter( ( feature ) => feature && feature.geometry )
		.map( ( feature ) => ( {
			code: feature.properties && feature.properties.code ? String( feature.properties.code ) : '(sans code)',
			geometry: feature.geometry,
		} ) );
}

/**
 * Un sommet du référentiel quitte-t-il l'emprise déclarée ? — invariant I-71.4.
 *
 * Balayage O(n) sur CHAQUE sommet de CHAQUE contour, joué avant toute
 * rasterisation : rien ne sert de cuire 295 tuiles pour découvrir ensuite
 * qu'elles ne couvrent pas la géométrie. C'est le contrôle qui remplace la
 * tautologie de l'ancienne recette — laquelle testait un sur-ensemble sur une
 * bbox dérivée de la même géométrie, et ne pouvait donc jamais rougir.
 *
 * @param {{code:string,geometry:object}[]} contours Contours lus.
 * @param {object}                          bbox     Emprise déclarée, en degrés.
 */
function controlerDebordement( contours, bbox ) {
	const bords = [
		{ nom: 'ouest', axe: 0, borne: bbox.ouest, sortie: ( v ) => bbox.ouest - v },
		{ nom: 'est', axe: 0, borne: bbox.est, sortie: ( v ) => v - bbox.est },
		{ nom: 'sud', axe: 1, borne: bbox.sud, sortie: ( v ) => bbox.sud - v },
		{ nom: 'nord', axe: 1, borne: bbox.nord, sortie: ( v ) => v - bbox.nord },
	];

	for ( const { code, geometry } of contours ) {
		const visiter = ( noeud ) => {
			if ( 'number' === typeof noeud[ 0 ] ) {
				for ( const bord of bords ) {
					const debordement = bord.sortie( noeud[ bord.axe ] );

					if ( debordement > 0 ) {
						throw new Arret(
							`Massif « ${ code } » : un sommet sort de l'emprise déclarée par le bord ${ bord.nom } — ` +
								`valeur ${ noeud[ bord.axe ] }, borne ${ bord.borne }, débordement ${ debordement.toFixed( 6 ) }°.\n` +
								'L\'emprise est une grandeur DÉCLARÉE. Ne la recalculez pas depuis la géométrie : DÉCIDEZ une ' +
								'nouvelle `EMPRISE_DECLAREE`, en coordonnées entières de tuile à z12, écrivez la décision, et ' +
								'rejouez le build complet. Recalculer rétablirait exactement le couplage que #71 supprime.'
						);
					}
				}

				return;
			}

			for ( const enfant of noeud ) {
				visiter( enfant );
			}
		};

		visiter( geometry.coordinates );
	}
}

/**
 * Les marges résiduelles se resserrent-elles ? — AVERTISSEMENT, jamais un arrêt.
 *
 * Une marge qui fond n'est pas une faute : c'est le signal qu'une prochaine
 * retouche du référentiel arrêtera le build. Le dire tôt, à voix basse, évite de
 * le découvrir tard, en rouge.
 */
function controlerMargeResiduelle( emprise, bbox ) {
	const marges = [
		[ 'ouest', emprise.ouest - bbox.ouest ],
		[ 'est', bbox.est - emprise.est ],
		[ 'sud', emprise.sud - bbox.sud ],
		[ 'nord', bbox.nord - emprise.nord ],
	];

	for ( const [ bord, marge ] of marges ) {
		if ( marge < MARGE_MIN_DEG ) {
			avertir(
				`emprise déclarée : marge résiduelle de ${ marge.toFixed( 5 ) }° au bord ${ bord }, sous le seuil de ` +
					`${ MARGE_MIN_DEG }°. La prochaine extension du référentiel de ce côté ARRÊTERA le build : décider une ` +
					'nouvelle `EMPRISE_DECLAREE` avant, plutôt qu\'après.'
			);
		}
	}
}

/**
 * Archive OSM, ou `null` si elle est absente ou invalide.
 *
 * Une archive invalide ne fait PAS échouer le build : elle le fait basculer en
 * mode dégradé, bruyamment. C'est la ligne de DoD §5.5 qui ne doit dépendre
 * d'aucun accès réseau, jamais l'inverse.
 */
function lireArchive() {
	if ( ! fs.existsSync( CHEMINS.archive ) ) {
		avertir( `archive OSM absente (${ relatifAuDepot( CHEMINS.archive ) }) — jouer \`npm run recuperer\` pour la produire.` );
		return null;
	}

	let archive;

	try {
		archive = JSON.parse( fs.readFileSync( CHEMINS.archive, 'utf8' ) );
	} catch ( erreur ) {
		avertir( `archive OSM illisible : ${ erreur.message }` );
		return null;
	}

	if ( ! archive || 'massifs-fond-de-carte-source' !== archive.type || ! archive.couches ) {
		avertir( 'archive OSM de schéma inconnu.' );
		return null;
	}

	for ( const couche of COUCHES ) {
		const bloc = archive.couches[ couche.nom ];

		if ( ! bloc || ! Array.isArray( bloc.features ) || 0 === bloc.features.length ) {
			avertir( `archive OSM : couche « ${ couche.nom } » absente ou vide.` );
			return null;
		}
	}

	/*
	 * La couche `toponymes` est traitée EXACTEMENT comme `terre` : absente ou mal
	 * formée, elle bascule le build en mode dégradé, bruyamment. Une archive
	 * antérieure à #71 n'en porte pas et sort donc en recette rouge — une pyramide
	 * sans noms n'est jamais commitable en silence (I-71.14). L'image statique,
	 * elle, est produite quand même, hors ligne et sans étiquettes : I-9.9 est
	 * intact, seul le CONTENU de « complet » change.
	 */
	const bloc = archive.couches[ COUCHE_TOPONYMES.nom ];

	if ( ! bloc || ! Array.isArray( bloc.features ) || 0 === bloc.features.length ) {
		avertir( `archive OSM : couche « ${ COUCHE_TOPONYMES.nom } » absente ou vide — archive antérieure à #71 ? Rejouer \`npm run recuperer\`.` );
		return null;
	}

	try {
		archive.toponymes = bloc.features.map( ( feature, rang ) => controlerPoint( feature, `l'archive OSM, toponyme ${ rang }` ) );
	} catch ( erreur ) {
		avertir( `archive OSM : ${ erreur.message }` );
		return null;
	}

	return archive;
}

/* -------------------------------------------------------------------------- */
/* Préparation des couches par résolution                                      */
/* -------------------------------------------------------------------------- */

const preparees = new Map();

/**
 * Couches simplifiées et filtrées pour une résolution donnée.
 *
 * @param {object} archive Archive OSM.
 * @param {number} mpp     Mètres par pixel du rendu visé.
 * @return {object} Nom de couche -> géométries.
 */
function preparer( archive, mpp ) {
	const intervalle = Math.max( NORMALISATION.intervalle_m, Math.round( mpp / 2 ) );
	const aireMin = Math.round( Math.pow( DESSIN.seuil_entite_px * mpp, 2 ) );
	const routes = mpp <= DESSIN.routes_mpp_max;
	const cle = `${ intervalle }|${ aireMin }|${ routes }`;

	if ( preparees.has( cle ) ) {
		return preparees.get( cle );
	}

	const sortie = {};

	fs.mkdirSync( TRAVAIL, { recursive: true } );

	for ( const couche of COUCHES ) {
		if ( 'routes' === couche.nom && ! routes ) {
			sortie[ couche.nom ] = [];
			continue;
		}

		const entree = path.join( TRAVAIL, `${ couche.nom }.json` );
		const resultat = path.join( TRAVAIL, `${ couche.nom }.out.json` );

		ecrireFc( entree, archive.couches[ couche.nom ].features.map( ( feature ) => feature.geometry ) );

		const options = [ entree, '-simplify', 'dp', `interval=${ intervalle }`, 'keep-shapes' ];

		if ( couche.surfacique ) {
			options.push( '-filter-islands', `min-area=${ aireMin }` );
		}

		options.push( '-o', `precision=0.${ '0'.repeat( NORMALISATION.decimales - 1 ) }1`, 'format=geojson', resultat );

		mapshaper( options );
		sortie[ couche.nom ] = lireFc( resultat );
	}

	preparees.set( cle, sortie );

	return sortie;
}

/** Purge les fichiers de travail et l'émission inachevée, quoi qu'il arrive. */
function purger() {
	for ( const chemin of [ TRAVAIL, EMISSION ] ) {
		if ( fs.existsSync( chemin ) ) {
			fs.rmSync( chemin, { recursive: true, force: true } );
		}
	}
}

/* -------------------------------------------------------------------------- */
/* Rendu SVG                                                                   */
/* -------------------------------------------------------------------------- */

/** Aire signée d'un anneau. Sert à normaliser le sens de parcours. */
function aireSignee( anneau ) {
	let somme = 0;

	for ( let i = 0; i < anneau.length - 1; i += 1 ) {
		somme += anneau[ i ][ 0 ] * anneau[ i + 1 ][ 1 ] - anneau[ i + 1 ][ 0 ] * anneau[ i ][ 1 ];
	}

	return somme / 2;
}

/**
 * Chemin SVG d'un jeu de géométries, projeté par la fonction fournie.
 *
 * Les anneaux extérieurs sont forcés dans le sens direct et les trous dans le
 * sens indirect, ce qui permet de peindre TOUTE une couche en un seul `<path>`
 * en règle `nonzero` : les trous restent des trous, et deux polygones qui se
 * chevauchent se remplissent au lieu de s'annuler — ce que la règle `evenodd`
 * ferait, en creusant des lucarnes là où deux bois se superposent.
 *
 * @param {object[]} geometries Géométries GeoJSON.
 * @param {Function} projeter   `[lon,lat] -> [x,y]` en pixels de toile.
 * @return {string}
 */
function cheminSvg( geometries, projeter ) {
	const morceaux = [];

	const tracer = ( points ) => {
		let rendu = '';

		for ( let i = 0; i < points.length; i += 1 ) {
			const [ x, y ] = projeter( points[ i ] );

			rendu += `${ 0 === i ? 'M' : 'L' }${ x.toFixed( 1 ) } ${ y.toFixed( 1 ) }`;
		}

		return rendu;
	};

	const anneau = ( points, direct ) => {
		const ordonne = aireSignee( points ) >= 0 === direct ? points : points.slice().reverse();

		morceaux.push( `${ tracer( ordonne ) }Z` );
	};

	const polygone = ( anneaux ) => {
		for ( let i = 0; i < anneaux.length; i += 1 ) {
			anneau( anneaux[ i ], 0 === i );
		}
	};

	for ( const geometrie of geometries ) {
		if ( 'Polygon' === geometrie.type ) {
			polygone( geometrie.coordinates );
			continue;
		}

		if ( 'MultiPolygon' === geometrie.type ) {
			for ( const partie of geometrie.coordinates ) {
				polygone( partie );
			}

			continue;
		}

		if ( 'LineString' === geometrie.type ) {
			morceaux.push( tracer( geometrie.coordinates ) );
			continue;
		}

		if ( 'MultiLineString' === geometrie.type ) {
			for ( const partie of geometrie.coordinates ) {
				morceaux.push( tracer( partie ) );
			}
		}
	}

	return morceaux.join( '' );
}

/**
 * Toile SVG complète.
 *
 * AUCUN ÉLÉMENT `<text>` N'Y EST JAMAIS ÉMIS (I-71.8). Les toponymes cuits sont
 * des CONTOURS DE GLYPHES, posés en `<path>` : aucune police n'est requise au
 * moment de la rasterisation, et aucune substitution système n'est possible —
 * `rasteriser()` passe de surcroît `loadSystemFonts: false` à `resvg`, pour que
 * cette propriété soit structurelle et non incidente.
 *
 * Les chaînes cuites sont des valeurs `name` d'OpenStreetMap reproduites
 * VERBATIM, c'est-à-dire des DONNÉES — jamais une chaîne de site rédigée par
 * nous. Le §11.3 de `MASTER.md` reste donc une liste fermée, non touchée.
 *
 * Restent interdits absolument, dans les deux artefacts : titre, légende, échelle
 * graphique, rose des vents, libellé de statut, date, et toute mention
 * d'attribution ou de source.
 *
 * Le point de WCAG 1.4.4 tient entier et c'est pourquoi il est conservé ici : un
 * texte cuit ne zoome pas. C'est très exactement la raison pour laquelle
 * l'information du site est portée par la LISTE TEXTUELLE et non par l'image, et
 * pourquoi aucun toponyme cuit ne porte d'information nécessaire à l'usage du
 * site (I-71.1) — les retirer tous ne retirerait aucune information au visiteur.
 *
 * ORDRE DE PEINTURE, déclaré et non incident : couches de fond, puis contours
 * charbon, puis TOUS les halos, puis TOUS les remplissages.
 *
 * AUCUNE URL non plus : `xmlns` est un espace de noms, jamais une requête — il
 * n'est pas déréférencé, et aucune ressource externe n'est référencée (I-9.2).
 */
function toile( { largeur, hauteur, jetons, couches, retenues, contours, projeter, etiquettes = [] } ) {
	const corps = [ `<rect width="${ largeur }" height="${ hauteur }" fill="${ jetons.get( '--c-carte-fond' ) }"/>` ];

	for ( const couche of COUCHES ) {
		if ( retenues && ! retenues.includes( couche.nom ) ) {
			continue;
		}

		const geometries = couches[ couche.nom ] || [];

		if ( 0 === geometries.length ) {
			continue;
		}

		const d = cheminSvg( geometries, projeter );

		if ( '' === d ) {
			continue;
		}

		const attributs = [
			`d="${ d }"`,
			`fill="${ couche.remplissage ? jetons.get( couche.remplissage ) : 'none' }"`,
			'fill-rule="nonzero"',
		];

		if ( couche.trait ) {
			attributs.push(
				`stroke="${ jetons.get( couche.trait ) }"`,
				`stroke-width="${ DESSIN.trait_px }"`,
				'stroke-linejoin="round"',
				'stroke-linecap="round"'
			);
		}

		corps.push( `<path ${ attributs.join( ' ' ) }/>` );
	}

	if ( contours && contours.length > 0 ) {
		// Les contours sont tracés PAR UN SEUL chemin, dans un seul style : une
		// différence de rendu entre massifs se lirait comme un statut. Aucun
		// remplissage — et surtout pas un aplat `--c-calcaire-ombre`, qui est
		// l'aplat de l'état `indisponible` : l'image affirmerait silencieusement
		// « 25 massifs sans information ».
		corps.push(
			`<path d="${ cheminSvg( contours, projeter ) }" fill="none" stroke="${ jetons.get( JETON_CONTOUR ) }"` +
				` stroke-width="${ DESSIN.contour_px }" stroke-linejoin="round" stroke-linecap="round"/>`
		);
	}

	corps.push( ...etiquettes );

	return (
		`<svg xmlns="http://www.w3.org/2000/svg" width="${ largeur }" height="${ hauteur }" ` +
		`viewBox="0 0 ${ largeur } ${ hauteur }">${ corps.join( '' ) }</svg>`
	);
}

/**
 * Rasterise une toile SVG et rend ses pixels RGBA bruts.
 *
 * `loadSystemFonts: false` n'est pas une précaution décorative : `resvg-js` le met
 * à `true` par défaut, et sans cette ligne l'absence de substitution système
 * serait INCIDENTE — vraie parce que nous n'émettons pas de `<text>` — au lieu
 * d'être STRUCTURELLE. Une ligne, et la non-substitution devient impossible à
 * perdre par accident (I-71.8).
 */
function rasteriser( svg, largeur ) {
	return new Resvg( svg, { fitTo: { mode: 'width', value: largeur }, font: { loadSystemFonts: false } } ).render().pixels;
}

/* -------------------------------------------------------------------------- */
/* Masques et mesures sur le raster                                            */
/* -------------------------------------------------------------------------- */

/** Index d'une couleur dans la palette fermée. */
function indexPalette( palette, hexadecimal ) {
	const [ r, v, b ] = versRgb( hexadecimal );
	const index = palette.findIndex( ( couleur ) => couleur[ 0 ] === r && couleur[ 1 ] === v && couleur[ 2 ] === b );

	if ( -1 === index ) {
		throw new Arret( `Couleur ${ hexadecimal } absente de la palette fermée : la palette et les jetons ont divergé.` );
	}

	return index;
}

/**
 * Image intégrale d'un masque booléen.
 *
 * Une somme sur une boîte devient alors une soustraction à quatre termes, quelle
 * que soit sa taille : le solveur interroge le masque des dizaines de fois par
 * étiquette, et un balayage naïf coûterait le carré de la marge à chaque essai.
 */
function integrale( marque, largeur, hauteur ) {
	const somme = new Uint32Array( ( largeur + 1 ) * ( hauteur + 1 ) );

	for ( let y = 0; y < hauteur; y += 1 ) {
		let ligne = 0;

		for ( let x = 0; x < largeur; x += 1 ) {
			ligne += marque( y * largeur + x ) ? 1 : 0;
			somme[ ( y + 1 ) * ( largeur + 1 ) + x + 1 ] = somme[ y * ( largeur + 1 ) + x + 1 ] + ligne;
		}
	}

	return somme;
}

/** Nombre de pixels marqués dans une boîte, bornes incluses, écrêtée à la toile. */
function sommeBoite( somme, largeur, hauteur, [ x0, y0, x1, y1 ] ) {
	const a = Math.max( 0, Math.min( largeur, x0 ) );
	const b = Math.max( 0, Math.min( hauteur, y0 ) );
	const c = Math.max( 0, Math.min( largeur, x1 ) );
	const d = Math.max( 0, Math.min( hauteur, y1 ) );

	if ( c <= a || d <= b ) {
		return 0;
	}

	return somme[ d * ( largeur + 1 ) + c ] - somme[ b * ( largeur + 1 ) + c ] - somme[ d * ( largeur + 1 ) + a ] + somme[ b * ( largeur + 1 ) + a ];
}

/* -------------------------------------------------------------------------- */
/* Artefacts                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * Image statique du repli sans JavaScript.
 *
 * Elle porte le fond et les contours des massifs. Elle ne porte AUCUN aplat de
 * statut, à vie : une image portant les couleurs du jour se périmerait par un
 * chemin que le PHP ne contrôle plus (cache HTTP, CDN de l'hébergeur), et la
 * règle de sécurité absolue « ne jamais présenter un statut périmé comme
 * courant » tomberait sans qu'aucune ligne de PHP soit fautive.
 */
function construireStatique( { archive, jetons, contours, palette, quantifier, police, jeuPercu } ) {
	// L'image statique se projette sur l'EMPRISE DÉCLARÉE, comme la pyramide : une
	// seule constante, une seule projection, deux artefacts qui montrent la même
	// zone. `etendueX` et `etendueY` sont alors des rationnels exacts (15/4096 et
	// 14/4096) et le rapport devient un rapport d'entiers, 15/14, au lieu du
	// produit d'une projection en virgule flottante qui bougeait à chaque retouche
	// du référentiel. Largeur et hauteur restent des DONNÉES DE BUILD (A-10) :
	// aucune n'est écrite en constante.
	const declaree = bboxDeclaree();
	const largeur = LARGEUR_STATIQUE;
	const etendueX = normX( declaree.est ) - normX( declaree.ouest );
	const etendueY = normY( declaree.sud ) - normY( declaree.nord );

	const hauteur = Math.round( ( largeur * etendueY ) / etendueX );
	const mpp = ( 2 * Math.PI * RAYON * Math.cos( ( ( ( declaree.sud + declaree.nord ) / 2 ) * Math.PI ) / 180 ) * etendueX ) / largeur;

	const projeter = ( [ lon, lat ] ) => [
		( ( normX( lon ) - normX( declaree.ouest ) ) / etendueX ) * largeur,
		( ( normY( lat ) - normY( declaree.nord ) ) / etendueY ) * hauteur,
	];

	const geometries = contours.map( ( contour ) => contour.geometry );
	const base = {
		largeur,
		hauteur,
		jetons,
		couches: null === archive ? {} : preparer( archive, mpp ),
		retenues: COUCHES_STATIQUE,
		contours: geometries,
		projeter,
	};

	/*
	 * PREMIÈRE PASSE — la toile sans étiquettes. Elle sert trois fois : de masque de
	 * contours pour le placement, de référence de pixels sombres pour C-f, et de
	 * repli si aucune étiquette n'est plaçable.
	 */
	const indicesBase = quantifier( rasteriser( toile( base ), largeur ), largeur * hauteur );
	const octetsBase = encoderPng8( indicesBase, largeur, hauteur, palette );
	const pixels = largeur * hauteur;

	/*
	 * Repli SANS étiquette, écrit UNE fois : la toile nue est alors l'artefact
	 * livré, et les compteurs d'encre des toponymes sont nuls par construction. Deux
	 * copies de ce littéral à onze champs finiraient par ne plus rendre la même forme
	 * selon le chemin de sortie emprunté.
	 */
	const sansEtiquette = ( rejets ) => ( {
		largeur,
		hauteur,
		mpp,
		octets: octetsBase,
		octetsBase,
		etiquettes: [],
		rejets,
		encre_totale: 0,
		encre_fond: 0,
		encre_boites: 0,
		couverture_encre: 0,
	} );

	if ( 0 === jeuPercu.length ) {
		return sansEtiquette( [] );
	}

	/*
	 * DEUX EXCLUSIONS CUMULÉES, et la seconde n'est pas redondante (arbitrage A-7
	 * du contrat #71).
	 *
	 *   - un pixel `--c-charbon` à moins de `marge_contour_px` de la boîte dilatée.
	 *     Le halo protège le texte ; les 25 contours, eux, SONT l'information ;
	 *   - l'intérieur d'un polygone de massif, même sans contour proche. Dans la
	 *     pyramide, un nom intérieur à un massif est TOUJOURS occulté par un aplat
	 *     de statut opaque — les quatre états en peignent un. Dans la statique,
	 *     AUCUN aplat n'est jamais peint (I-9.3). Le même nom serait donc visible
	 *     dans un artefact et caché dans l'autre, ce que le §5.5 du brief interdit.
	 *     L'ÉQUIVALENCE ENTRE LES DEUX ARTEFACTS SE MESURE SUR CE QUI EST VISIBLE,
	 *     JAMAIS SUR CE QUI EST CUIT.
	 *
	 * Les deux sont mesurées sur le RASTER, pas sur la géométrie : l'invariant
	 * énonce une propriété de pixels, et on contrôle ce qu'on énonce.
	 */
	const indexCharbon = indexPalette( palette, jetons.get( JETON_CONTOUR ) );
	const integraleCharbon = integrale( ( p ) => indicesBase[ p ] === indexCharbon, largeur, hauteur );

	/*
	 * Le masque des intérieurs est une toile de TRAVAIL, jamais un artefact : il est
	 * rasterisé en mémoire, lu, puis jeté. Ses deux couleurs sont donc délibérément
	 * HORS de la palette fermée — un masque peint dans les jetons du fond serait
	 * indissociable du fond lui-même. Aucun de ces deux octets n'atteint la pyramide
	 * ni l'image statique, que la recette contrôle pixel par pixel contre la palette.
	 *
	 * Comme dans `toile()`, `xmlns` est un ESPACE DE NOMS et non une requête : il
	 * n'est jamais déréférencé, et aucune ressource externe n'est référencée (I-9.2).
	 */
	const svgMassifs =
		`<svg xmlns="http://www.w3.org/2000/svg" width="${ largeur }" height="${ hauteur }" viewBox="0 0 ${ largeur } ${ hauteur }">` +
		`<rect width="${ largeur }" height="${ hauteur }" fill="${ MASQUE_DEHORS }"/>` +
		`<path d="${ cheminSvg( geometries, projeter ) }" fill="${ MASQUE_DEDANS }" fill-rule="nonzero"/></svg>`;
	const rgbaMassifs = rasteriser( svgMassifs, largeur );
	const integraleMassifs = integrale( ( p ) => rgbaMassifs[ p * 4 ] < MASQUE_SEUIL, largeur, hauteur );

	const marge = TOPONYMES.marge_contour_px;
	const exclusion = ( [ x0, y0, x1, y1 ] ) =>
		sommeBoite( integraleCharbon, largeur, hauteur, [ x0 - marge, y0 - marge, x1 + marge, y1 + marge ] ) > 0 ||
		sommeBoite( integraleMassifs, largeur, hauteur, [ x0, y0, x1, y1 ] ) > 0;

	/*
	 * Le jeu de la statique est un SOUS-ENSEMBLE du jeu du zoom perçu, et c'est
	 * exactement ce qui rend la règle de cohérence contrôlable. Abandonner une
	 * étiquette ici est donc légitime — chaque abandon est consigné avec son motif.
	 */
	const { acceptes, rejets } = resoudrePlacement( {
		candidats: jeuPercu,
		plafond: TOPONYMES.etiquettes_statique_max,
		projeter,
		toile: { largeur, hauteur },
		police: police.police,
		upem: police.upem,
		corps_px: TOPONYMES.corps_statique_px,
		halo_px: TOPONYMES.halo_statique_px,
		padding_px: TOPONYMES.padding_statique_px,
		ecart_min: TOPONYMES.ecart_min_statique_px,
		exclusion,
	} );

	if ( acceptes.length < 2 ) {
		avertir(
			`image statique : ${ acceptes.length } toponyme(s) placé(s) sur ${ jeuPercu.length } candidat(s) du zoom perçu ` +
				`z${ TOPONYMES.zoom_percu_statique }. Regarder le rendu avant de conclure.`
		);
	}

	if ( 0 === acceptes.length ) {
		return sansEtiquette( rejets );
	}

	/* SECONDE PASSE — la même toile, étiquettes comprises. */
	const indices = quantifier(
		rasteriser( toile( { ...base, etiquettes: svgEtiquettes( acceptes, jetons, TOPONYMES.halo_statique_px ) } ), largeur ),
		pixels
	);
	const octets = encoderPng8( indices, largeur, hauteur, palette );

	/*
	 * C-c — couverture d'encre des TOPONYMES, mesurée par DIFFÉRENCE entre la toile
	 * étiquetée et la toile nue du même build.
	 *
	 * L'arbitrage A-5 du contrat #71 fondait une mesure absolue sur la prémisse que
	 * `--c-carte-encre` n'a « aucun autre consommateur dans la statique ». MESURE À
	 * L'APPUI, CETTE PRÉMISSE EST FAUSSE : la toile SANS étiquette porte déjà
	 * 37 237 pixels d'index encre, soit 1,559 % — `--c-carte-encre` est le plus
	 * proche voisin d'une bande de la rampe d'anticrénelage charbon → terre, et
	 * `PALIERS = 0` fait tomber cette frange sur lui. Le coût est ANTÉRIEUR à #71 et
	 * n'a rien à voir avec les toponymes.
	 *
	 * La différence, elle, est exactement ce que A-5 voulait compter, et elle est
	 * exacte au même titre : les deux toiles ne diffèrent que par les étiquettes. Le
	 * plafond de 0,5 % et son dénominateur — la toile entière — sont inchangés.
	 *
	 * Le halo n'est toujours pas borné : il est couleur de fond, invisible sur le
	 * fond, et le borner serait borner la mauvaise chose.
	 */
	const indexEncre = indexPalette( palette, jetons.get( '--c-carte-encre' ) );
	let encre = 0;
	let encreBase = 0;

	for ( let p = 0; p < pixels; p += 1 ) {
		if ( indices[ p ] === indexEncre ) {
			encre += 1;
		}

		if ( indicesBase[ p ] === indexEncre ) {
			encreBase += 1;
		}
	}

	/*
	 * La grandeur CONTRÔLÉE est l'encre DANS LES BOÎTES, et non l'encre de la toile.
	 * C'est la seule que la recette puisse remesurer seule, sans reconstruire la
	 * toile nue — et elle coïncide avec la différence ci-dessus, puisque C-e interdit
	 * tout pixel charbon à moins de `marge_contour_px` d'une boîte, donc aucune
	 * frange d'anticrénelage de contour n'y entre.
	 */
	let encreBoites = 0;

	for ( const etiquette of acceptes ) {
		const [ x0, y0, x1, y1 ] = etiquette.boite_dilatee;

		for ( let y = Math.max( 0, y0 ); y < Math.min( hauteur, y1 ); y += 1 ) {
			for ( let x = Math.max( 0, x0 ); x < Math.min( largeur, x1 ); x += 1 ) {
				if ( indices[ y * largeur + x ] === indexEncre ) {
					encreBoites += 1;
				}
			}
		}
	}

	return {
		largeur,
		hauteur,
		mpp,
		octets,
		octetsBase,
		etiquettes: acceptes,
		rejets,
		encre_totale: encre,
		encre_fond: encreBase,
		encre_boites: encreBoites,
		couverture_encre: encreBoites / pixels,
	};
}

/**
 * C-f — « texture, jamais bouillie », mesuré sur l'IMAGE, pas sur les métadonnées.
 *
 * C'est le seul des six critères qui mesure la propriété énoncée : C-a à C-e sont
 * des propriétés des métadonnées, et un build pourrait les satisfaire toutes en
 * produisant une bouillie grise. Deux moitiés complémentaires (arbitrage A-6) :
 *
 *   - SURVIE DE L'ÉTIQUETTE — sur le PNG ré-échantillonné à 360 px, la PLAGE
 *     `max − min` et la MOYENNE de luma de chaque boîte. Une étiquette qui
 *     s'effondre en gris uniforme perd sa plage la première ;
 *   - NON-NOYAGE DE L'INFORMATION — le compte de pixels sombres HORS des boîtes
 *     d'étiquettes ne recule pas de plus de 5 % contre la MÊME image sans
 *     étiquettes. Le vrai risque n'est pas que les noms soient illisibles — ils le
 *     sont, et c'est assumé — c'est qu'ils NOIENT les 25 contours.
 *
 * Le référent est l'image sans étiquettes DU MÊME BUILD, et non un nombre
 * historique : un nombre historique se périmerait au premier changement d'emprise,
 * de palette ou de couche, et il faudrait alors le regeler sans savoir pourquoi.
 *
 * Le seuil de « pixel sombre » est DÉRIVÉ, non choisi : c'est la luma de
 * `--c-carte-trait`, la couleur la plus foncée du fond qui ne soit ni de l'encre
 * ni un contour. Tout pixel plus sombre qu'elle doit sa noirceur au charbon ou à
 * l'encre.
 */
async function mesurerTexture360( { octets, octetsBase, largeur, etiquettes, jetons } ) {
	const facteur = TOPONYMES.facteur_360;
	const cible = Math.round( largeur * facteur );
	const seuilSombre = luma( ...versRgb( jetons.get( '--c-carte-trait' ) ) );

	const reduire = async ( source ) => {
		const { data, info } = await sharp( source ).resize( { width: cible, kernel: 'lanczos3' } ).raw().toBuffer( { resolveWithObject: true } );

		return { data, largeur: info.width, hauteur: info.height, canaux: info.channels };
	};

	const avec = await reduire( octets );
	const sans = await reduire( octetsBase );
	const echelle = avec.largeur / largeur;

	const boites = etiquettes.map( ( etiquette ) => [
		Math.floor( etiquette.boite_dilatee[ 0 ] * echelle ),
		Math.floor( etiquette.boite_dilatee[ 1 ] * echelle ),
		Math.ceil( etiquette.boite_dilatee[ 2 ] * echelle ),
		Math.ceil( etiquette.boite_dilatee[ 3 ] * echelle ),
	] );

	const mesures = etiquettes.map( ( etiquette, rang ) => {
		const [ x0, y0, x1, y1 ] = boites[ rang ];
		let minimum = Infinity;
		let maximum = -Infinity;
		let total = 0;
		let compte = 0;

		for ( let y = Math.max( 0, y0 ); y < Math.min( avec.hauteur, y1 ); y += 1 ) {
			for ( let x = Math.max( 0, x0 ); x < Math.min( avec.largeur, x1 ); x += 1 ) {
				const p = ( y * avec.largeur + x ) * avec.canaux;
				const valeur = luma( avec.data[ p ], avec.data[ p + 1 ], avec.data[ p + 2 ] );

				minimum = Math.min( minimum, valeur );
				maximum = Math.max( maximum, valeur );
				total += valeur;
				compte += 1;
			}
		}

		return {
			nom: etiquette.nom,
			boite_360: [ x0, y0, x1, y1 ],
			plage: 0 === compte ? 0 : Math.round( ( maximum - minimum ) * 10 ) / 10,
			moyenne: 0 === compte ? 0 : Math.round( ( total / compte ) * 10 ) / 10,
		};
	} );

	const dansUneBoite = ( x, y ) => boites.some( ( [ x0, y0, x1, y1 ] ) => x >= x0 && x < x1 && y >= y0 && y < y1 );

	const compterSombres = ( image ) => {
		let compte = 0;

		for ( let y = 0; y < image.hauteur; y += 1 ) {
			for ( let x = 0; x < image.largeur; x += 1 ) {
				const p = ( y * image.largeur + x ) * image.canaux;

				if ( luma( image.data[ p ], image.data[ p + 1 ], image.data[ p + 2 ] ) < seuilSombre && ! dansUneBoite( x, y ) ) {
					compte += 1;
				}
			}
		}

		return compte;
	};

	return {
		largeur_360: avec.largeur,
		seuil_sombre: Math.round( seuilSombre * 10 ) / 10,
		pixels_sombres_sans_etiquettes: compterSombres( sans ),
		pixels_sombres_avec_etiquettes: compterSombres( avec ),
		etiquettes: mesures,
	};
}

/**
 * Pyramide de tuiles.
 *
 * UNE toile par zoom, découpée ENSUITE : une toile par tuile couperait deux fois
 * différemment un trait à cheval sur deux tuiles, et la couture se verrait.
 */
function construirePyramide( { archive, jetons, emprise, palette, quantifier, police } ) {
	const tuiles = [];
	const empreintes = {};
	const rendus = [];
	const jeux = {};
	const rejets = [];
	let precedent = [];

	for ( const g of grillesDeclarees() ) {
		const mpp = metresParPixel( emprise, g.zoom );
		const cote = Math.pow( 2, g.zoom ) * TAILLE_TUILE;
		const originX = g.x0 * TAILLE_TUILE;
		const originY = g.y0 * TAILLE_TUILE;
		const projeter = ( [ lon, lat ] ) => [ normX( lon ) * cote - originX, normY( lat ) * cote - originY ];

		/*
		 * z5–z8 : AUCUNE étiquette, par règle et non par réglage — à z8 la région
		 * utile fait 210 x 188 px, et un seul mot en occuperait plus de la moitié.
		 *
		 * z12 : EXACTEMENT le jeu de z11, cuit au double (I-71.9). Une tuile z12 est
		 * toujours rendue à l'échelle de z11 ; un jeu différent afficherait des NOMS
		 * DIFFÉRENTS selon la densité de l'écran, ce qui est une divergence de
		 * données et non une nuance de rendu.
		 */
		let jeu = [];

		if ( ZOOM_MAX === g.zoom ) {
			jeu = reposerJeu( {
				jeu: precedent,
				projeter,
				toile: { largeur: g.largeur_px, hauteur: g.hauteur_px },
				police: police.police,
				upem: police.upem,
				corps_px: TOPONYMES.corps_px * TOPONYMES.facteur_z12,
				halo_px: TOPONYMES.halo_px * TOPONYMES.facteur_z12,
				padding_px: TOPONYMES.padding_px * TOPONYMES.facteur_z12,
			} );
		} else if ( g.zoom >= TOPONYMES.zoom_min_etiquettes ) {
			// Densité rapportée à l'aire de l'EMPRISE DU RÉFÉRENTIEL, jamais à celle de
			// la toile : la toile z9 fait 0,393 Mpx contre 0,158 Mpx pour l'emprise, et
			// l'erreur livrerait 2,5 fois trop d'étiquettes.
			const plafond = Math.round( TOPONYMES.densite_par_mpx * airePlacementMpx( emprise, g.zoom ) );

			const resolution = resoudrePlacement( {
				candidats: archive.toponymes,
				// LA MONOTONIE EST PRODUITE : le jeu du zoom précédent est semé DE FORCE,
				// placé avant tout nouveau candidat (I-71.10).
				forces: precedent,
				plafond,
				projeter,
				toile: { largeur: g.largeur_px, hauteur: g.hauteur_px },
				police: police.police,
				upem: police.upem,
				corps_px: TOPONYMES.corps_px,
				halo_px: TOPONYMES.halo_px,
				padding_px: TOPONYMES.padding_px,
			} );

			jeu = resolution.acceptes;

			for ( const rejet of resolution.rejets ) {
				rejets.push( { nom: rejet.nom, zoom: g.zoom, raison: rejet.raison } );
			}
		}

		if ( g.zoom >= TOPONYMES.zoom_min_etiquettes ) {
			jeux[ g.zoom ] = jeu;
			precedent = jeu;
		}

		const svg = toile( {
			largeur: g.largeur_px,
			hauteur: g.hauteur_px,
			jetons,
			couches: preparer( archive, mpp ),
			contours: null,
			projeter,
			etiquettes: svgEtiquettes( jeu, jetons, ZOOM_MAX === g.zoom ? TOPONYMES.halo_px * TOPONYMES.facteur_z12 : TOPONYMES.halo_px ),
		} );

		const indices = quantifier( rasteriser( svg, g.largeur_px ), g.largeur_px * g.hauteur_px );

		for ( let ligne = 0; ligne < g.lignes; ligne += 1 ) {
			for ( let colonne = 0; colonne < g.colonnes; colonne += 1 ) {
				const tuile = new Uint8Array( TAILLE_TUILE * TAILLE_TUILE );

				for ( let y = 0; y < TAILLE_TUILE; y += 1 ) {
					const depart = ( ligne * TAILLE_TUILE + y ) * g.largeur_px + colonne * TAILLE_TUILE;

					tuile.set( indices.subarray( depart, depart + TAILLE_TUILE ), y * TAILLE_TUILE );
				}

				const octets = encoderPng8( tuile, TAILLE_TUILE, TAILLE_TUILE, palette );
				const cle = `${ g.zoom }/${ g.x0 + colonne }/${ g.y0 + ligne }`;

				tuiles.push( { cle, octets } );
				empreintes[ cle ] = sha256( octets );
			}
		}

		rendus.push( {
			zoom: g.zoom,
			x0: g.x0,
			x1: g.x1,
			y0: g.y0,
			y1: g.y1,
			nombre: g.nombre,
			metres_par_pixel: Math.round( mpp * 10 ) / 10,
		} );

		journal(
			`  z${ g.zoom } — ${ g.colonnes } x ${ g.lignes } = ${ g.nombre } tuiles (toile ${ g.largeur_px } x ${ g.hauteur_px }, ` +
				`~${ Math.round( mpp ) } m/px)${ g.zoom >= TOPONYMES.zoom_min_etiquettes ? `, ${ jeu.length } toponyme(s)` : '' }`
		);
	}

	return {
		tuiles,
		empreintes,
		grilles: rendus,
		jeux,
		rejets,
		nombre: tuiles.length,
		octets: tuiles.reduce( ( total, tuile ) => total + tuile.octets.length, 0 ),
		bbox: bboxDeclaree(),
	};
}

/* -------------------------------------------------------------------------- */
/* Rendu des fichiers                                                          */
/* -------------------------------------------------------------------------- */

/** En-têtes de cache, livrés par l'extension : ils suivent sur l'hébergement mutualisé. */
function rendreHtaccess() {
	return `# Fond de carte auto-hébergé — en-têtes de cache et fermeture du listing.
#
# Ce fichier est LIVRÉ PAR L'EXTENSION, et c'est tout son intérêt : il suit le
# dépôt sur l'hébergement mutualisé, là où une configuration de conteneur
# (docker/wordpress/*.conf) n'existe pas. Son absence est un COÛT DE PERFORMANCE,
# jamais une panne : sans lui, les tuiles sont servies sans cache long.
#
# FICHIER GÉNÉRÉ par includes/ingest/tuiles/build/construire.mjs — ne pas éditer.

# Forme RELATIVE obligatoire. La forme absolue (\`Options None\`) remplacerait
# l'hérité et pourrait retirer \`FollowSymLinks\`, dont dépend le reste du site.
Options -Indexes

# La version est dans le CHEMIN, jamais en query : c'est ce qui mérite
# \`immutable\`. Un \`?v=\` détruirait cette sémantique sur certains proxies.
# Portée limitée aux \`.png\` : le fichier de métadonnées voisin n'est pas
# immuable, et le déclarer tel gèlerait une URL qui ment après un rebuild.
<IfModule mod_headers.c>
	<FilesMatch "\\.png$">
		Header set Cache-Control "public, max-age=31536000, immutable"
	</FilesMatch>
</IfModule>

# Les métadonnées sont lues par PHP en interne, jamais par une requête HTTP.
# \`plugins-guard.conf\` le fait déjà en Docker ; ici, l'extension se protège
# elle-même, sur tout hébergement.
<Files "fond-13.php">
	<IfModule mod_authz_core.c>
		Require all denied
	</IfModule>
	<IfModule !mod_authz_core.c>
		Order allow,deny
		Deny from all
	</IfModule>
</Files>
`;
}

/** Métadonnées lues par le module PHP. */
function rendreMetadonnees( donnees ) {
	return `<?php
/**
 * Fond de carte auto-hébergé — métadonnées.
 *
 * FICHIER GÉNÉRÉ — NE PAS ÉDITER À LA MAIN.
 * Produit par \`includes/ingest/tuiles/build/construire.mjs\` (npm run construire)
 * à partir de l'archive OpenStreetMap commitée sous \`build/source/\`.
 *
 * Ce fichier ne s'ouvre pas directement : il se lit par les fonctions
 * \`massifs_fond_de_carte()\`, \`massifs_fond_de_carte_statique()\` et
 * \`massifs_attribution_fond_de_carte()\`. Il ne contient aucune coordonnée de
 * tuile : les octets vivent à côté, servis en statique, sans amorçage WordPress.
 *
 * \`pyramide.version\` est un segment de CHEMIN, jamais une query : c'est ce qui
 * rend la mise en cache \`immutable\` honnête.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/*
 * Garde volontairement SANS \`exit\` : hors WordPress, le fichier retourne un
 * tableau vide au lieu d'interrompre le processus. C'est ce qui permet à la
 * recette de build de le lire (\`php -r\` avec MASSIFS_VERIFICATION) sans amorcer
 * WordPress. Ne pas « corriger » en \`exit\`.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'MASSIFS_VERIFICATION' ) ) {
	return array();
}

return ${ rendreValeur( donnees, 0 ) };
`;
}

/* -------------------------------------------------------------------------- */
/* Déroulé                                                                     */
/* -------------------------------------------------------------------------- */

/** Version de fontkit réellement installée, lue dans son manifeste. */
function versionFontkit() {
	const manifeste = path.join( RACINE, 'node_modules/fontkit/package.json' );

	if ( ! fs.existsSync( manifeste ) ) {
		throw new Arret( 'fontkit est absent : lancer `npm ci` dans includes/ingest/tuiles/build/.' );
	}

	return JSON.parse( fs.readFileSync( manifeste, 'utf8' ) ).version;
}

/** Champs consignés d'une étiquette placée. Les boîtes sont déjà arrondies vers l'extérieur. */
function consignerEtiquette( etiquette ) {
	return {
		nom: etiquette.nom,
		classe: etiquette.classe,
		ancrage: etiquette.ancrage,
		corps_px: etiquette.corps_px,
		boite: etiquette.boite,
		boite_dilatee: etiquette.boite_dilatee,
	};
}

/** Bloc `toponymes` du manifeste : réglages, police, source, jeux, rejets, texture. */
function buildToponymes( { archive, police, pyramide, statique, texture } ) {
	const retenus = null === archive ? [] : archive.toponymes;
	const jeux = {};

	if ( null !== pyramide ) {
		for ( const zoom of Object.keys( pyramide.jeux ) ) {
			jeux[ zoom ] = pyramide.jeux[ zoom ].map( consignerEtiquette );
		}
	}

	jeux.statique = statique.etiquettes.map( consignerEtiquette );

	return {
		reglages: TOPONYMES,
		police: {
			fichier: relatifAuDepot( CHEMINS.police_texte ),
			sha256: police.sha256,
			octets: police.octets,
			nomPostScript: police.nomPostScript,
			upem: police.upem,
			variation: 'defaut',
		},
		source: {
			selecteur: SELECTEURS[ COUCHE_TOPONYMES.nom ],
			classes: CLASSES_TOPONYMES,
			retenus: retenus.length,
			par_classe: Object.fromEntries(
				CLASSES_TOPONYMES.map( ( classe ) => [ classe, retenus.filter( ( entite ) => classe === entite.classe ).length ] )
			),
		},
		jeux,
		rejets: [ ...( null === pyramide ? [] : pyramide.rejets ), ...statique.rejets.map( ( rejet ) => ( { ...rejet, zoom: 'statique' } ) ) ],
		/*
		 * `fond` est consigné pour que le prochain lecteur n'ait pas à redécouvrir que
		 * `--c-carte-encre` a un second consommateur : la frange d'anticrénelage des
		 * contours charbon, antérieure à #71 et sans rapport avec les toponymes.
		 */
		encre: {
			dans_les_boites: statique.encre_boites,
			toile_entiere: statique.encre_totale,
			fond: statique.encre_fond,
			couverture: statique.couverture_encre,
		},
		texture,
	};
}

async function principal() {
	const jetons = controlerJetons();

	journal( `Jetons contrôlés dans ${ relatifAuDepot( CHEMINS.tokens ) } : les ${ Object.keys( JETONS_CARTE ).length } jetons --c-carte-* et ${ JETON_CONTOUR }` );

	const emprise = lireEmprise( CHEMINS.referentiel );
	const contours = lireContours();
	const declaree = bboxDeclaree();

	// Avant TOUTE rasterisation : cuire 295 tuiles pour découvrir ensuite qu'elles
	// ne couvrent pas la géométrie serait du temps perdu et un message tardif.
	controlerDebordement( contours, declaree );
	controlerMargeResiduelle( emprise, declaree );

	const archive = lireArchive();
	const mode = null === archive ? MODE_DEGRADE : MODE_COMPLET;
	const palette = paletteAutorisee( jetons );
	const quantifier = quantificateur( palette );

	if ( MODE_DEGRADE === mode ) {
		journal( '' );
		journal( 'MODE DÉGRADÉ — aucune pyramide ne sera émise. Voir l\'avertissement en fin de sortie.' );
		journal( '' );
	}

	journal( `Emprise du référentiel : ${ JSON.stringify( emprise ) }` );
	journal( `Emprise déclarée (z${ EMPRISE_DECLAREE.zoom }, entiers de tuile) : x ${ EMPRISE_DECLAREE.x0 }..${ EMPRISE_DECLAREE.x1 }, y ${ EMPRISE_DECLAREE.y0 }..${ EMPRISE_DECLAREE.y1 } — ${ JSON.stringify( declaree ) }` );
	journal( `Contours de massifs : ${ contours.length }` );
	journal( `Palette fermée : ${ palette.length } couleurs, dérivées des seuls jetons` );

	const police = ouvrirPolice( CHEMINS.police_texte );

	journal( `Police de texte : ${ police.nomPostScript }, upem ${ police.upem }, ${ police.octets } octets (instance par défaut, aucune variation)` );

	let statique;
	let pyramide = null;
	let texture = null;

	try {
		/*
		 * LA PYRAMIDE D'ABORD, la statique ensuite : la statique porte le jeu du zoom
		 * perçu (`TOPONYMES.zoom_percu_statique`), et ce jeu est produit par la
		 * résolution de la pyramide — le zoom est un réglage, jamais un nombre à
		 * recopier en commentaire, où il se périme au premier arbitrage remesuré. En mode
		 * dégradé il n'y a pas de pyramide, donc pas de jeu, donc pas d'étiquette sur
		 * la statique — et la statique est produite QUAND MÊME, hors ligne (I-9.9).
		 */
		if ( MODE_COMPLET === mode ) {
			pyramide = construirePyramide( { archive, jetons, emprise, palette, quantifier, police } );

			journal( `Pyramide : ${ pyramide.nombre } tuiles, ${ pyramide.octets } octets` );
		}

		const jeuPercu = null === pyramide ? [] : pyramide.jeux[ TOPONYMES.zoom_percu_statique ] || [];

		statique = construireStatique( { archive, jetons, contours, palette, quantifier, police, jeuPercu } );

		journal(
			`Image statique : ${ statique.largeur } x ${ statique.hauteur } px (~${ Math.round( statique.mpp ) } m/px), ` +
				`${ statique.octets.length } octets (${ statique.octetsBase.length } sans étiquette), ` +
				`${ statique.etiquettes.length } toponyme(s) : ${ statique.etiquettes.map( ( e ) => e.nom ).join( ', ' ) || '(aucun)' }`
		);
		journal(
			`  encre : ${ statique.encre_totale } pixels au total, dont ${ statique.encre_fond } d'anticrénelage de contour ` +
				`déjà présents SANS étiquette — toponymes ${ statique.encre_boites } px dans les boîtes ` +
				`(${ statique.encre_totale - statique.encre_fond } px par différence), soit ` +
				`${ ( statique.couverture_encre * 100 ).toFixed( 3 ) } % de la toile`
		);

		if ( statique.rejets.length > 0 ) {
			journal( `  écartés : ${ statique.rejets.map( ( rejet ) => `${ rejet.nom } (${ rejet.raison })` ).join( ', ' ) }` );
		}

		if ( statique.etiquettes.length > 0 ) {
			texture = await mesurerTexture360( {
				octets: statique.octets,
				octetsBase: statique.octetsBase,
				largeur: statique.largeur,
				etiquettes: statique.etiquettes,
				jetons,
			} );
		}
	} finally {
		purger();
	}

	/*
	 * C-f — les deux moitiés, imprimées puis contrôlées. Les seuils sont MESURÉS
	 * PUIS GELÉS : le build imprime, le développeur gèle le minimum mesuré moins
	 * 20 %, dans le même commit, daté au §7 du README.
	 */
	if ( null !== texture ) {
		journal( `Texture à ${ texture.largeur_360 } px (seuil de pixel sombre : luma ${ texture.seuil_sombre }) :` );

		for ( const mesure of texture.etiquettes ) {
			journal( `  ${ mesure.nom } — plage ${ mesure.plage }, moyenne ${ mesure.moyenne }` );
		}

		journal(
			`  pixels sombres hors boîtes : ${ texture.pixels_sombres_sans_etiquettes } sans étiquettes, ` +
				`${ texture.pixels_sombres_avec_etiquettes } avec`
		);

		const plageMin = Math.min( ...texture.etiquettes.map( ( mesure ) => mesure.plage ) );
		const moyenneMin = Math.min( ...texture.etiquettes.map( ( mesure ) => mesure.moyenne ) );

		if ( plageMin < TOPONYMES.plage_luma_min_360 || moyenneMin < TOPONYMES.luma_moyenne_min_360 ) {
			throw new Arret(
				`C-f : plage minimale ${ plageMin } (seuil ${ TOPONYMES.plage_luma_min_360 }), moyenne minimale ${ moyenneMin } ` +
					`(seuil ${ TOPONYMES.luma_moyenne_min_360 }) après réduction à ${ texture.largeur_360 } px. Une étiquette qui ` +
					's\'effondre en gris uniforme perd sa plage la première. Si C-f ne peut pas être tenu, LES TOPONYMES ' +
					'SORTENT DE L\'IMAGE STATIQUE et restent dans la seule pyramide : c\'est une issue prévue, écrite ' +
					'd\'avance, et ce n\'est pas un échec.'
			);
		}

		const recul =
			0 === texture.pixels_sombres_sans_etiquettes
				? 0
				: 1 - texture.pixels_sombres_avec_etiquettes / texture.pixels_sombres_sans_etiquettes;

		if ( recul > TOPONYMES.recul_sombres_max_360 ) {
			throw new Arret(
				`C-f : les pixels sombres reculent de ${ ( recul * 100 ).toFixed( 1 ) } % après réduction à ` +
					`${ texture.largeur_360 } px, plafond ${ TOPONYMES.recul_sombres_max_360 * 100 } %. Le vrai risque n'est pas ` +
					'que les noms soient illisibles — ils le sont, et c\'est assumé — c\'est qu\'ils NOIENT les 25 contours.'
			);
		}
	}

	if ( statique.couverture_encre > TOPONYMES.couverture_encre_max ) {
		throw new Arret(
			`C-c : couverture d'encre ${ ( statique.couverture_encre * 100 ).toFixed( 3 ) } %, plafond ` +
				`${ TOPONYMES.couverture_encre_max * 100 } %. Abaisser \`zoom_percu_statique\` ou \`densite_par_mpx\`.`
		);
	}

	if ( statique.octets.length > PLAFOND_STATIQUE_OCTETS ) {
		throw new Arret(
			`Image statique : ${ statique.octets.length } octets, plafond ${ PLAFOND_STATIQUE_OCTETS }.\n` +
				'Leviers, DANS CET ORDRE. D\'ABORD les trois leviers de #71, qui répondent au coût des toponymes :\n' +
				'  (0a) MOINS DE TOPONYMES — abaisser `TOPONYMES.zoom_percu_statique` ou `densite_par_mpx`. EN PREMIER, ' +
				'parce que c\'est le seul levier qui améliore AUSSI la lisibilité.\n' +
				'  (0b) RETIRER LE HALO, remplacé par une règle de placement plus stricte (n\'accepter que si la boîte ' +
				'dilatée ne couvre que fond/terre/eau) — environ 60 % des octets d\'étiquette.\n' +
				'  (0c) RÉDUIRE LE CORPS — COURSE QUASI NULLE : le plancher d\'impression est 24,3 px, et ' +
				'`corps_min_statique_px` vaut 25. Ne pas actionner celui-ci en premier sous prétexte qu\'il est le ' +
				'curseur le plus facile.\n' +
				'ENSUITE SEULEMENT les trois mitigations du §2 du contrat #9 : (1) réduire la palette indexée, ' +
				'(2) supprimer les couches de fond les moins informatives, (3) ramener la largeur intrinsèque à 1280 px.\n' +
				'JAMAIS une compression avec perte, jamais un second artefact, jamais un srcset, jamais une couleur ' +
				'hors des 7 jetons.'
		);
	}

	/* --- Manifeste et version --------------------------------------------- */

	const genereLe = new Date().toISOString().replace( /\.\d{3}Z$/, 'Z' );
	const manifesteSource = fs.existsSync( CHEMINS.manifeste_source )
		? JSON.parse( fs.readFileSync( CHEMINS.manifeste_source, 'utf8' ) )
		: null;

	const manifeste = {
		a_propos:
			'Manifeste du fond de carte. ÉMIS PAR `npm run construire`, jamais édité à la main. La version publiée est la troncature à 8 hexadécimaux de son sha256 : la version DÉRIVE du contenu, elle ne se choisit pas.',
		mode,
		// `emprise` reste l'emprise du RÉFÉRENTIEL — c'est un fait sur l'entrée.
		// `emprise_declaree` est la grandeur qui gouverne les deux artefacts.
		emprise,
		emprise_declaree: EMPRISE_DECLAREE,
		jetons: Object.fromEntries( [ ...Object.keys( JETONS_CARTE ), JETON_CONTOUR ].map( ( nom ) => [ nom, jetons.get( nom ) ] ) ),
		palette: palette.map( versHexadecimal ),
		dessin: DESSIN,
		source:
			null === archive
				? null
				: {
						extrait_le: archive.extrait_le,
						sha256: manifesteSource ? manifesteSource.archive.sha256 : null,
						octets: manifesteSource ? manifesteSource.archive.octets : null,
						comptes: manifesteSource ? manifesteSource.comptes_normalises : null,
				  },
		pyramide:
			null === pyramide
				? null
				: {
						zoom_min: ZOOM_MIN,
						zoom_max: ZOOM_MAX,
						taille_tuile: TAILLE_TUILE,
						format: FORMAT,
						bbox: pyramide.bbox,
						nombre: pyramide.nombre,
						octets: pyramide.octets,
						grilles: pyramide.grilles,
						tuiles: pyramide.empreintes,
				  },
		statique: {
			largeur: statique.largeur,
			hauteur: statique.hauteur,
			contours_massifs: contours.length,
			octets: statique.octets.length,
			sha256: sha256( statique.octets ),
		},
		/*
		 * Les métadonnées d'étiquetage vivent SOUS `build/`, qui n'est jamais servi
		 * (interdit 5 du contrat #20) : aucune n'atteint `data/tuiles/fond-13.php`,
		 * donc aucune n'atteint le thème. Les toponymes cuits sont des PIXELS, pas
		 * des données, et aucune fonction PHP ne les expose — c'est délibéré.
		 *
		 * Mettre `reglages` dans le manifeste n'est pas cosmétique : la version est le
		 * sha256 du manifeste canonique, donc PASSER LE CORPS DE 19 À 20 PX CHANGE LA
		 * VERSION. C'est ce qui rend `immutable` honnête, et c'est facile à oublier.
		 *
		 * Les boîtes sont en pixels entiers ARRONDIS VERS L'EXTÉRIEUR : un sur-ensemble
		 * conservateur du vrai contour, de sorte que tout contrôle de confinement ou de
		 * séparation qui passe sur la boîte consignée passe aussi sur la boîte réelle.
		 * C'est ce qui rend le manifeste utilisable comme PREUVE.
		 */
		toponymes: buildToponymes( { archive, police, pyramide, statique, texture } ),
		outillage: { mapshaper: versionMapshaper(), node_major: nodeMajeur(), fontkit: versionFontkit() },
	};

	const octetsManifeste = Buffer.from( `${ jsonCanonique( manifeste ) }`, 'utf8' );
	const empreinteManifeste = sha256( octetsManifeste );
	const version = jetonVersion( empreinteManifeste );

	journal( `Version dérivée du contenu : ${ version } (sha256 du manifeste ${ empreinteManifeste })` );

	/* --- Métadonnées PHP --------------------------------------------------- */

	const zero = { ouest: flottant( 0 ), sud: flottant( 0 ), est: flottant( 0 ), nord: flottant( 0 ) };
	const donnees = {
		schema: SCHEMA,
		genere_le: genereLe,
		mode,
		pyramide: {
			version: null === pyramide ? '' : version,
			sha256: null === pyramide ? '' : empreinteManifeste,
			octets: null === pyramide ? 0 : pyramide.octets,
			nombre: null === pyramide ? 0 : pyramide.nombre,
			zoom_min: ZOOM_MIN,
			zoom_max: ZOOM_MAX,
			taille_tuile: TAILLE_TUILE,
			format: FORMAT,
			bbox:
				null === pyramide
					? zero
					: {
							ouest: flottant( pyramide.bbox.ouest ),
							sud: flottant( pyramide.bbox.sud ),
							est: flottant( pyramide.bbox.est ),
							nord: flottant( pyramide.bbox.nord ),
					  },
		},
		statique: {
			version,
			sha256: sha256( statique.octets ),
			octets: statique.octets.length,
			largeur: statique.largeur,
			hauteur: statique.hauteur,
			contours_massifs: contours.length,
		},
		attribution: {
			phrase: ATTRIBUTION.phrase,
			lien_licence: ATTRIBUTION.lien_licence,
			faits: {
				canal: 'Overpass API',
				canal_url: 'https://overpass-api.de/',
				extrait_le: null === archive ? '' : String( archive.extrait_le ).slice( 0, 10 ),
				licence_nom: ATTRIBUTION.licence_nom,
				licence_version: ATTRIBUTION.licence_version,
				licence_url: ATTRIBUTION.licence_url,
				rendu: ATTRIBUTION.rendu,
			},
		},
	};

	/* --- Émission atomique ------------------------------------------------- */

	fs.mkdirSync( CHEMINS.tuiles, { recursive: true } );
	fs.mkdirSync( path.dirname( CHEMINS.statique ), { recursive: true } );

	const sorties = [
		{ chemin: CHEMINS.metadonnees, contenu: Buffer.from( rendreMetadonnees( donnees ), 'utf8' ) },
		{ chemin: path.join( CHEMINS.tuiles, '.htaccess' ), contenu: Buffer.from( rendreHtaccess(), 'utf8' ) },
		{ chemin: CHEMINS.statique, contenu: statique.octets },
	];

	try {
		if ( null !== pyramide ) {
			for ( const tuile of pyramide.tuiles ) {
				const cible = path.join( EMISSION, `${ tuile.cle }.${ FORMAT }` );

				fs.mkdirSync( path.dirname( cible ), { recursive: true } );
				fs.writeFileSync( cible, tuile.octets );
			}
		}

		for ( const { chemin, contenu } of sorties ) {
			fs.writeFileSync( `${ chemin }.tmp`, contenu );
		}

		if ( null !== pyramide ) {
			const cible = path.join( CHEMINS.tuiles, version );

			if ( fs.existsSync( cible ) ) {
				fs.rmSync( cible, { recursive: true, force: true } );
			}

			fs.renameSync( EMISSION, cible );
		}

		for ( const { chemin } of sorties ) {
			fs.renameSync( `${ chemin }.tmp`, chemin );
		}
	} catch ( erreur ) {
		purger();

		for ( const { chemin } of sorties ) {
			if ( fs.existsSync( `${ chemin }.tmp` ) ) {
				fs.unlinkSync( `${ chemin }.tmp` );
			}
		}

		throw new Arret(
			`Émission impossible : ${ erreur.message }\nAucun retour en arrière automatique — réécrire dans un ` +
				'état incertain l\'aggraverait. Restaurer par `git status` puis `git checkout --` les fichiers déjà remplacés.'
		);
	}

	/* --- Purge des versions précédentes, APRÈS succès complet -------------- */

	const restantes = [];

	if ( null !== pyramide ) {
		for ( const entree of fs.readdirSync( CHEMINS.tuiles, { withFileTypes: true } ) ) {
			if ( entree.isDirectory() && entree.name !== version ) {
				fs.rmSync( path.join( CHEMINS.tuiles, entree.name ), { recursive: true, force: true } );
				restantes.push( entree.name );
			}
		}
	} else {
		for ( const entree of fs.readdirSync( CHEMINS.tuiles, { withFileTypes: true } ) ) {
			if ( entree.isDirectory() ) {
				restantes.push( entree.name );
			}
		}
	}

	/* --- Manifeste et empreinte de référence ------------------------------- */

	const reference = {
		a_propos:
			'Empreinte de référence des artefacts du fond de carte. ÉMIS PAR `npm run construire`, jamais édité à la main. `npm run verifier` compare les artefacts en place à ces valeurs : une différence est une dérive à expliquer. Si le changement est voulu, régénérer les artefacts ET ce fichier par `npm run construire`, dans le même commit. La reproductibilité BINAIRE inter-plateformes n\'est PAS revendiquée — resvg est un binaire natif ; ce fichier garantit la DÉTECTION DE DÉRIVE, et rien de plus.',
		genere_le: genereLe,
		mode,
		outillage: manifeste.outillage,
		jetons: manifeste.jetons,
		manifeste: { sha256: empreinteManifeste, version },
		pyramide: null === pyramide ? null : { nombre: pyramide.nombre, octets: pyramide.octets },
		statique: manifeste.statique,
		// Des COMPTES, jamais les listes : les listes pilotent déjà l'empreinte du
		// manifeste, et les redoubler ici ferait deux sources pour un même fait.
		toponymes: {
			...Object.fromEntries( Object.keys( manifeste.toponymes.jeux ).map( ( cle ) => [ cle, manifeste.toponymes.jeux[ cle ].length ] ) ),
			rejets: manifeste.toponymes.rejets.length,
			source: manifeste.toponymes.source.retenus,
		},
	};

	fs.writeFileSync( CHEMINS.manifeste, `${ JSON.stringify( manifeste, null, '\t' ) }\n` );
	fs.writeFileSync( CHEMINS.reference, `${ JSON.stringify( reference, null, '\t' ) }\n` );

	/* --- Rapport ------------------------------------------------------------ */

	journal( '' );
	journal( `Métadonnées : ${ relatifAuDepot( CHEMINS.metadonnees ) }` );
	journal( `En-têtes de cache : ${ relatifAuDepot( path.join( CHEMINS.tuiles, '.htaccess' ) ) }` );
	journal( `Image statique : ${ relatifAuDepot( CHEMINS.statique ) }` );

	if ( null !== pyramide ) {
		journal( `Pyramide : ${ relatifAuDepot( path.join( CHEMINS.tuiles, version ) ) } — ${ pyramide.nombre } tuiles, ${ pyramide.octets } octets` );

		if ( restantes.length > 0 ) {
			journal( `Versions précédentes supprimées : ${ restantes.join( ', ' ) }` );
		}
	}

	journal( `Manifeste : ${ relatifAuDepot( CHEMINS.manifeste ) }` );
	journal( `Référence : ${ relatifAuDepot( CHEMINS.reference ) }` );

	if ( MODE_DEGRADE === mode ) {
		avertir( 'aucune pyramide n\'a été émise — 295 aplats uniformes seraient une carte qui affirme quelque chose de faux sur la géographie.' );

		if ( restantes.length > 0 ) {
			avertir( `pyramide(s) déjà en place laissée(s) INTACTE(S) : ${ restantes.join( ', ' ) }. Les métadonnées déclarent le fond indisponible, donc personne n'en publie l'URL.` );
		}

		avertir( '`npm run verifier` SORTIRA EN ÉCHEC tant que le mode vaut `degrade` : un artefact dégradé est constructible en local, jamais commitable en silence.' );
	}

	if ( avertissements.length > 0 ) {
		process.stdout.write( `\nAVERTISSEMENT(S) :\n  - ${ avertissements.join( '\n  - ' ) }\n` );
	}

	journal( '' );
	journal( `Terminé — mode ${ mode }.` );
}

try {
	// L'avertissement de mode dégradé doit être lisible EN TÊTE comme en pied :
	// une sortie de plusieurs dizaines de lignes ferait disparaître un message
	// posé d'un seul côté.
	await principal();
} catch ( erreur ) {
	purger();
	process.stderr.write( `${ erreur instanceof Arret ? erreur.message : erreur.stack }\n` );
	process.exitCode = 1;
}
