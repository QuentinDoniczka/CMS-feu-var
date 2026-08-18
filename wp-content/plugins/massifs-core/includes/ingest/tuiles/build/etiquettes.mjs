/**
 * Étiquetage des toponymes : ouverture de la police, extraction des contours de
 * glyphes, mesure, classement et solveur de placement.
 *
 * POURQUOI UN FICHIER SÉPARÉ. `commun.mjs` s'ouvre sur une propriété dure —
 * « ce module n'a AUCUN effet de bord au chargement : ni écriture, ni réseau, ni
 * lecture de fichier ». Ouvrir une police EST une lecture de fichier : cela seul
 * l'en exclut. Et `construire.mjs` porte déjà le pipeline des deux artefacts.
 *
 * AUCUN `<text>` N'EST JAMAIS ÉMIS (I-71.8). Les toponymes sont des CONTOURS DE
 * GLYPHES, posés en `<path>`. Trois conséquences, et chacune ferme un piège :
 *
 *   - aucune police n'est présente au moment de la rasterisation, donc AUCUNE
 *     SUBSTITUTION SYSTÈME N'EST POSSIBLE. `resvg-js` charge les polices système
 *     par défaut ; `construire.mjs` lui passe de surcroît `loadSystemFonts: false`,
 *     pour que la non-substitution soit STRUCTURELLE et non incidente ;
 *   - les boîtes sont mesurées sur l'encre RÉELLE avant tout dessin, donc la
 *     non-troncature et la non-collision sont PROUVÉES, pas approchées ;
 *   - les contours de glyphes sont des entiers d'unités de police ; le seul
 *     flottant est le facteur d'échelle. Le jeu d'étiquettes est déterministe, ce
 *     qui est la condition pour qu'il entre dans l'empreinte de version.
 *
 * TYPOGRAPHIE — Atkinson Hyperlegible Next est PRESCRITE, pas subie. La Règle de
 * portée typographique de `MASTER.md` (l. 612-644) énumère TROIS zones pour la
 * famille d'affichage — ardoise, légende de la carte, titres de statut — et pose
 * l. 620 que « partout ailleurs, la famille de texte est seule employée ». Un
 * toponyme cuit n'appartient à aucune des trois. La contrainte technique CONVERGE
 * avec la prescription : `getVariation()` échoue sur woff2 en fontkit 2.0.4, donc
 * seule l'instance par défaut est atteignable — et celle d'Atkinson EST
 * Regular/400, à l'intérieur de la plage 400→700 déclarée au §5. Big Shoulders
 * est inutilisable pour la raison exactement symétrique : son instance par défaut
 * est Thin/100 (`MASTER.md` l. 574-576, `PROVENANCE.md` l. 50).
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import fs from 'node:fs';
import { openSync } from 'fontkit';
import { Arret, CLASSES_TOPONYMES, TOPONYMES, ecartBoites, relatifAuDepot, sha256 } from './commun.mjs';

/**
 * Décimales des coordonnées de glyphe.
 *
 * Deux, là où `cheminSvg()` en pose une : à 19 px de corps, un dixième de pixel
 * est un vingtième de la hampe, et l'arrondi se verrait sur les diagonales. Deux
 * décimales restent parfaitement déterministes — c'est la reproductibilité qui
 * compte ici, pas la brièveté.
 */
const DECIMALES = 2;

/** Lettres de commande émises. Grammaire FERMÉE : c'est ce qui rend `poserEtiquette` total. */
const COMMANDES = 'MLQCZ';

/**
 * Décalages des quatre ancres alternes, en FRACTIONS DU CORPS et non en pixels.
 *
 * Exprimés en fractions, les cinq ancres restent homothétiques quand le corps
 * double à z12 : le jeu de z12 est alors exactement celui de z11 — mêmes noms,
 * MÊMES ANCRES, boîtes ×2 (I-71.9). Écrits en pixels, ils décaleraient les quatre
 * ancres alternes d'un artefact à l'autre.
 *
 * `DEGAGEMENT_LATERAL_EM` s'ajoute à la DEMI-LARGEUR de la boîte d'encre : E et O
 * poussent l'étiquette hors de son point, pas seulement à côté.
 */
const DECALAGE_VERTICAL_EM = 0.8;
const DEGAGEMENT_LATERAL_EM = 0.5;

/**
 * Ouvre la police de texte du thème, dans son INSTANCE PAR DÉFAUT.
 *
 * AUCUN appel à `getVariation()` : il échoue sur woff2 en fontkit 2.0.4
 * (« Cannot read properties of undefined (reading 'tables') »), et l'instance par
 * défaut d'Atkinson est déjà celle qu'il faut. L'empreinte, le nom PostScript et
 * l'`upem` sont rendus pour être consignés au manifeste et relus par la recette
 * (I-71.7).
 *
 * @param {string} chemin Chemin du `.woff2`.
 * @return {{police:object,upem:number,nomPostScript:string,sha256:string,octets:number}}
 */
export function ouvrirPolice( chemin ) {
	if ( ! fs.existsSync( chemin ) ) {
		throw new Arret( `Police de texte introuvable : ${ relatifAuDepot( chemin ) }` );
	}

	const octets = fs.readFileSync( chemin );
	const police = openSync( chemin );

	if ( ! police || ! Number.isInteger( police.unitsPerEm ) || police.unitsPerEm <= 0 ) {
		throw new Arret( `Police illisible ou sans unitsPerEm : ${ relatifAuDepot( chemin ) }` );
	}

	return {
		police,
		upem: police.unitsPerEm,
		nomPostScript: police.postscriptName,
		sha256: sha256( octets ),
		octets: octets.length,
	};
}

/** Arrondi de coordonnée, une seule règle pour tout le fichier. */
function coordonnee( valeur ) {
	return Number( valeur.toFixed( DECIMALES ) );
}

/**
 * Mesure une étiquette et rend son tracé en coordonnées LOCALES.
 *
 * Ligne de base en (0, 0), axe des y DÉJÀ retourné : les contours de glyphes sont
 * en y-vers-le-haut, SVG est en y-vers-le-bas.
 *
 * La boîte est l'union des boîtes des glyphes NON VIDES : une espace n'a pas
 * d'encre, et la faire compter gonflerait la boîte d'un blanc que personne ne
 * voit — donc rejetterait des placements légitimes.
 *
 * @param {object} police   Police ouverte.
 * @param {number} upem     Unités par em.
 * @param {string} nom      Nom à composer, VERBATIM.
 * @param {number} corps_px Corps en pixels.
 * @return {{d:string,boiteEncre:object}}
 */
function mesurerEtiquette( police, upem, nom, corps_px ) {
	const echelle = corps_px / upem;
	const composition = police.layout( nom );
	const morceaux = [];
	const boite = { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity };
	let plume = 0;
	let notdef = 0;

	for ( let i = 0; i < composition.glyphs.length; i += 1 ) {
		const glyphe = composition.glyphs[ i ];
		const position = composition.positions[ i ];

		if ( 0 === glyphe.id ) {
			notdef += 1;
		}

		const originX = plume + position.xOffset * echelle;
		const originY = -position.yOffset * echelle;

		plume += position.xAdvance * echelle;

		const commandes = glyphe.path ? glyphe.path.commands : [];

		if ( 0 === commandes.length ) {
			continue;
		}

		const cadre = glyphe.bbox;

		boite.x0 = Math.min( boite.x0, originX + cadre.minX * echelle );
		boite.x1 = Math.max( boite.x1, originX + cadre.maxX * echelle );
		boite.y0 = Math.min( boite.y0, originY - cadre.maxY * echelle );
		boite.y1 = Math.max( boite.y1, originY - cadre.minY * echelle );

		for ( const commande of commandes ) {
			if ( 'closePath' === commande.command ) {
				morceaux.push( 'Z' );
				continue;
			}

			const lettre = { moveTo: 'M', lineTo: 'L', quadraticCurveTo: 'Q', bezierCurveTo: 'C' }[ commande.command ];

			if ( undefined === lettre ) {
				throw new Arret( `Commande de contour inconnue « ${ commande.command } » dans « ${ nom } ».` );
			}

			const nombres = [];

			for ( let a = 0; a < commande.args.length; a += 2 ) {
				nombres.push( coordonnee( originX + commande.args[ a ] * echelle ), coordonnee( originY - commande.args[ a + 1 ] * echelle ) );
			}

			morceaux.push( lettre + nombres.join( ' ' ) );
		}
	}

	if ( notdef > 0 ) {
		throw new Arret(
			`Toponyme « ${ nom } » : ${ notdef } glyphe(s) absent(s) de la police. Un rectangle vide au milieu d'un nom ` +
				'reproduit verbatim est le pire rendu possible — le build s\'arrête plutôt que de le cuire.'
		);
	}

	if ( ! Number.isFinite( boite.x0 ) ) {
		throw new Arret( `Toponyme « ${ nom } » : aucun glyphe porteur d'encre.` );
	}

	return { d: morceaux.join( '' ), boiteEncre: boite };
}

/**
 * Translate un tracé local vers la toile.
 *
 * Le tokeniseur est TOTAL parce que la grammaire est fermée : `mesurerEtiquette`
 * n'émet que M, L, Q, C et Z, et tous les arguments de M/L/Q/C sont des paires
 * (x, y). Aucune commande relative, aucun arc, aucune forme abrégée.
 *
 * @param {string} d Tracé en coordonnées locales.
 * @param {number} x Abscisse de la ligne de base, en pixels de toile.
 * @param {number} y Ordonnée de la ligne de base, en pixels de toile.
 * @return {string}
 */
function poserEtiquette( d, x, y ) {
	const morceaux = d.match( new RegExp( `[${ COMMANDES }][^${ COMMANDES }]*`, 'g' ) ) || [];

	return morceaux
		.map( ( morceau ) => {
			const lettre = morceau[ 0 ];

			if ( 'Z' === lettre ) {
				return 'Z';
			}

			const nombres = morceau
				.slice( 1 )
				.split( ' ' )
				.filter( ( jeton ) => '' !== jeton )
				.map( ( jeton, rang ) => coordonnee( Number.parseFloat( jeton ) + ( 0 === rang % 2 ? x : y ) ) );

			return lettre + nombres.join( ' ' );
		} )
		.join( '' );
}

/**
 * Classe les candidats : classe, puis population décroissante, puis nom.
 *
 * JAMAIS `localeCompare`. Il dépend de l'ICU embarqué dans la version de Node qui
 * exécute le build, et rendrait l'empreinte de version DÉPENDANTE DE LA MACHINE —
 * deux postes produiraient deux versions pour des octets identiques. Comparaison
 * `<` sur la chaîne brute, et rien d'autre.
 *
 * @param {object[]} candidats Toponymes.
 * @return {object[]} Copie classée.
 */
function rangerCandidats( candidats ) {
	return candidats.slice().sort( ( a, b ) => {
		const rangA = CLASSES_TOPONYMES.indexOf( a.classe );
		const rangB = CLASSES_TOPONYMES.indexOf( b.classe );

		if ( rangA !== rangB ) {
			return rangA - rangB;
		}

		if ( a.population !== b.population ) {
			return b.population - a.population;
		}

		if ( a.nom === b.nom ) {
			return 0;
		}

		return a.nom < b.nom ? -1 : 1;
	} );
}

/** Boîte arrondie VERS L'EXTÉRIEUR : un sur-ensemble conservateur du vrai contour. */
function arrondirDehors( boite ) {
	return [ Math.floor( boite.x0 ), Math.floor( boite.y0 ), Math.ceil( boite.x1 ), Math.ceil( boite.y1 ) ];
}

/**
 * Mesure d'un candidat, faite UNE FOIS par étiquette : tracé local, boîte d'encre,
 * point projeté et les cinq décalages d'ancre.
 *
 * Le solveur l'appelle avant d'essayer les ancres, `reposerJeu()` avant de reposer
 * la seule ancre déjà retenue. C'est la même mesure, elle n'est écrite qu'ici : deux
 * copies finiraient par diverger, et le jeu de z12 cesserait d'être celui de z11.
 *
 * @param {object} parametres `{ police, upem, entite, corps_px, projeter }`.
 * @return {{d:string,boiteEncre:object,px:number,py:number,centreX:number,centreY:number,decalages:object}}
 */
function mesurerPose( { police, upem, entite, corps_px, projeter } ) {
	const { d, boiteEncre } = mesurerEtiquette( police, upem, entite.nom, corps_px );
	const [ px, py ] = projeter( [ entite.lon, entite.lat ] );
	const largeur = boiteEncre.x1 - boiteEncre.x0;

	return {
		d,
		boiteEncre,
		px,
		py,
		centreX: ( boiteEncre.x0 + boiteEncre.x1 ) / 2,
		centreY: ( boiteEncre.y0 + boiteEncre.y1 ) / 2,
		// « C » d'abord : un nom seul se centre sur son point, et AUCUNE pastille
		// n'est dessinée sous lui. Les quatre autres ne servent qu'après collision.
		// L'ORDRE D'ESSAI est celui de `TOPONYMES.ancrages`, qui est parcouru tel
		// quel par le solveur : ce jeu-ci n'est qu'une table de décalages, et c'est
		// la constante qui décide, sans quoi elle serait un réglage que personne ne
		// lit — consigné au manifeste, donc dans l'empreinte de version.
		decalages: {
			C: [ 0, 0 ],
			N: [ 0, -corps_px * DECALAGE_VERTICAL_EM ],
			S: [ 0, corps_px * DECALAGE_VERTICAL_EM ],
			E: [ largeur / 2 + corps_px * DEGAGEMENT_LATERAL_EM, 0 ],
			O: [ -( largeur / 2 + corps_px * DEGAGEMENT_LATERAL_EM ), 0 ],
		},
	};
}

/**
 * Boîtes d'une pose à une ancre donnée : l'encre arrondie vers l'extérieur, sa
 * dilatée du halo et du padding, et la ligne de base que `poserEtiquette()` attend.
 */
function boitesDeAncre( pose, ancrage, dilatation ) {
	const [ dx, dy ] = pose.decalages[ ancrage ];
	const baseX = pose.px + dx - pose.centreX;
	const baseY = pose.py + dy - pose.centreY;

	const encre = arrondirDehors( {
		x0: pose.boiteEncre.x0 + baseX,
		y0: pose.boiteEncre.y0 + baseY,
		x1: pose.boiteEncre.x1 + baseX,
		y1: pose.boiteEncre.y1 + baseY,
	} );

	const dilatee = [ encre[ 0 ] - dilatation, encre[ 1 ] - dilatation, encre[ 2 ] + dilatation, encre[ 3 ] + dilatation ].map( ( valeur, rang ) =>
		rang < 2 ? Math.floor( valeur ) : Math.ceil( valeur )
	);

	return { baseX, baseY, encre, dilatee };
}

/** Une boîte dilatée sort-elle de la toile ? Rejetée, jamais rognée (I-71.2). */
function sortDeLaToile( dilatee, toile ) {
	return dilatee[ 0 ] < 0 || dilatee[ 1 ] < 0 || dilatee[ 2 ] > toile.largeur || dilatee[ 3 ] > toile.hauteur;
}

/** Étiquette posée, telle qu'elle est consignée au manifeste et relue par la recette. */
function etiquettePosee( entite, { ancrage, corps_px, encre, dilatee, d } ) {
	return {
		nom: entite.nom,
		classe: entite.classe,
		population: entite.population,
		lon: entite.lon,
		lat: entite.lat,
		ancrage,
		corps_px,
		boite: encre,
		boite_dilatee: dilatee,
		d,
	};
}

/**
 * Solveur de placement, glouton et déterministe, sur la TOILE ENTIÈRE du zoom.
 *
 * La toile entière est gratuite : `construirePyramide()` rasterise déjà une toile
 * par zoom avant de la découper, si bien qu'une étiquette à cheval sur deux tuiles
 * se recolle d'elle-même côté Leaflet.
 *
 * AUCUNE PASTILLE n'est dessinée sous un nom : ce serait une marque nouvelle sur
 * une carte dont les marques sont gouvernées par `MASTER.md`. Un nom seul se
 * centre sur son point ; les quatre ancres alternes ne servent qu'après collision.
 *
 * LA MONOTONIE EST PRODUITE, PAS ESPÉRÉE : `forces` porte le jeu du zoom
 * précédent, placé EN PREMIER, avant tout nouveau candidat. Un nom forcé
 * non plaçable à un zoom PLUS FIN n'est pas un abandon légitime — un zoom plus fin
 * offre proportionnellement plus de place, le corps étant constant — et lève
 * `Arret`. Un nom qui disparaît en zoomant est une carte qui semble perdre de
 * l'information.
 *
 * @param {object} parametres Paramètres du solveur.
 * @return {{acceptes:object[],rejets:object[]}}
 */
export function resoudrePlacement( {
	candidats,
	forces = [],
	plafond,
	projeter,
	toile,
	police,
	upem,
	corps_px,
	halo_px,
	padding_px,
	ecart_min = 0,
	exclusion = null,
} ) {
	const acceptes = [];
	const rejets = [];
	const dilatation = halo_px + padding_px;
	const nomsForces = new Set( forces.map( ( entite ) => entite.nom ) );
	const ordre = [ ...rangerCandidats( forces ), ...rangerCandidats( candidats.filter( ( entite ) => ! nomsForces.has( entite.nom ) ) ) ];

	for ( const candidat of ordre ) {
		const force = nomsForces.has( candidat.nom );

		if ( ! force && acceptes.length >= plafond ) {
			rejets.push( { nom: candidat.nom, raison: 'plafond' } );
			continue;
		}

		const pose = mesurerPose( { police, upem, entite: candidat, corps_px, projeter } );

		let placee = null;
		let derniereRaison = 'collision';

		for ( const ancrage of TOPONYMES.ancrages ) {
			const { baseX, baseY, encre, dilatee } = boitesDeAncre( pose, ancrage, dilatation );

			// ROGNER UN NOM EST UNE TRONCATURE, DONC UNE INVENTION (I-71.2) : une
			// boîte qui sortirait de la toile est REJETÉE, jamais coupée.
			if ( sortDeLaToile( dilatee, toile ) ) {
				derniereRaison = 'hors_toile';
				continue;
			}

			if ( acceptes.some( ( place ) => ecartBoites( place.boite_dilatee, dilatee ) < ecart_min ) ) {
				derniereRaison = 'collision';
				continue;
			}

			if ( null !== exclusion && exclusion( dilatee ) ) {
				derniereRaison = 'contour';
				continue;
			}

			placee = etiquettePosee( candidat, { ancrage, corps_px, encre, dilatee, d: poserEtiquette( pose.d, baseX, baseY ) } );
			break;
		}

		if ( null === placee ) {
			if ( force ) {
				throw new Arret(
					`Monotonie rompue : « ${ candidat.nom } », placé au zoom précédent, n'est plaçable à aucune des cinq ` +
						`ancres au corps ${ corps_px } px (motif : ${ derniereRaison }). Un zoom plus fin offre ` +
						'proportionnellement PLUS de place : ce n\'est pas un abandon légitime. Un nom qui disparaît en ' +
						'zoomant est une carte qui semble perdre de l\'information.'
				);
			}

			rejets.push( { nom: candidat.nom, raison: derniereRaison } );
			continue;
		}

		acceptes.push( placee );
	}

	return { acceptes, rejets };
}

/**
 * Repose un jeu d'étiquettes déjà résolu, à un autre corps et sur une autre toile.
 *
 * Sert au SEUL cas de z12 (I-71.9) : mêmes noms, MÊMES ANCRES, boîtes ×2. Aucune
 * recherche d'ancre n'est refaite — une ancre différente à z12 ferait diverger le
 * rendu entre un écran ordinaire et un écran dense, alors qu'une tuile z12 est
 * toujours affichée à l'échelle de z11 (F-11 + A-7 du contrat #9).
 *
 * Une étiquette qui ne tiendrait pas dans la nouvelle toile lève `Arret` : le jeu
 * de z12 DOIT être exactement celui de z11, et un jeu amputé serait une divergence
 * de données, pas une nuance de rendu.
 *
 * @param {object} parametres Paramètres.
 * @return {object[]} Le même jeu, reposé.
 */
export function reposerJeu( { jeu, projeter, toile, police, upem, corps_px, halo_px, padding_px } ) {
	const dilatation = halo_px + padding_px;

	return jeu.map( ( etiquette ) => {
		const pose = mesurerPose( { police, upem, entite: etiquette, corps_px, projeter } );
		const { baseX, baseY, encre, dilatee } = boitesDeAncre( pose, etiquette.ancrage, dilatation );

		if ( sortDeLaToile( dilatee, toile ) ) {
			throw new Arret(
				`Toponyme « ${ etiquette.nom } » : la boîte reposée au corps ${ corps_px } px sort de la toile ` +
					`${ toile.largeur } x ${ toile.hauteur }. Le jeu de z12 doit être EXACTEMENT celui de z11 (I-71.9) : ` +
					'un jeu amputé serait une divergence de données entre écran ordinaire et écran dense.'
			);
		}

		return etiquettePosee( etiquette, {
			ancrage: etiquette.ancrage,
			corps_px,
			encre,
			dilatee,
			d: poserEtiquette( pose.d, baseX, baseY ),
		} );
	} );
}

/**
 * Deux `<path>` superposés : TOUS les halos, puis TOUS les remplissages.
 *
 * Jamais `paint-order` — le support d'usvg en est incertain, et un échec
 * silencieux poserait l'encre directement sur `--c-carte-trait`, à 4,17:1. Deux
 * chemins déclarés dans cet ordre sont sans ambiguïté.
 *
 * Le halo est un TRAIT de largeur `2 x halo_px` centré sur le contour, donc
 * `halo_px` déborde vers l'extérieur. Il peint `--c-carte-fond`, jeton déjà
 * présent : la palette reste fermée à 7.
 *
 * @param {object[]}          acceptes Étiquettes placées.
 * @param {Map<string,string>} jetons  Jetons lus dans `tokens.css`.
 * @param {number}            halo_px  Débord du halo, en pixels.
 * @return {string[]} Les deux `<path>`, dans l'ordre de peinture.
 */
export function svgEtiquettes( acceptes, jetons, halo_px ) {
	if ( 0 === acceptes.length ) {
		return [];
	}

	const d = acceptes.map( ( etiquette ) => etiquette.d ).join( '' );

	return [
		`<path d="${ d }" fill="none" stroke="${ jetons.get( '--c-carte-fond' ) }" stroke-width="${ halo_px * 2 }"` +
			' stroke-linejoin="round" stroke-linecap="round"/>',
		`<path d="${ d }" fill="${ jetons.get( '--c-carte-encre' ) }" fill-rule="nonzero"/>`,
	];
}
