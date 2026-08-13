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
 * ligne, et n'émet AUCUNE tuile : 280 aplats uniformes seraient une carte qui
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
import {
	ATTRIBUTION,
	Arret,
	CHEMINS,
	COUCHES,
	COUCHES_STATIQUE,
	DESSIN,
	FORMAT,
	JETONS_CARTE,
	JETON_CONTOUR,
	LARGEUR_STATIQUE,
	MODE_COMPLET,
	MODE_DEGRADE,
	NORMALISATION,
	PLAFOND_STATIQUE_OCTETS,
	RACINE,
	SCHEMA,
	TAILLE_TUILE,
	ZOOM_MAX,
	ZOOM_MIN,
	bboxDeGrille,
	divergencesJetons,
	ecrireFc,
	grilles,
	jetonVersion,
	jsonCanonique,
	lireEmprise,
	lireFc,
	lireJetons,
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
	versionMapshaper,
} from './commun.mjs';
import { encoderPng8 } from './png8.mjs';
import { flottant, rendreValeur } from './php.mjs';

/** Rayon de la sphère Web Mercator, en mètres. */
const RAYON = 6378137;

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

/** Les contours de massifs, lus dans l'artefact géométrique publié. */
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

	return brut.features.map( ( feature ) => feature.geometry ).filter( Boolean );
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
 * AUCUN TEXTE n'y est jamais émis : ni toponyme, ni titre, ni légende, ni
 * échelle. Un texte cuit ne zoome pas (WCAG 1.4.4), et toute chaîne hors §11.3
 * de `MASTER.md` serait une invention (A-9, `OUVERT`).
 *
 * AUCUNE URL non plus : `xmlns` est un espace de noms, jamais une requête — il
 * n'est pas déréférencé, et aucune ressource externe n'est référencée (I-9.2).
 */
function toile( { largeur, hauteur, jetons, couches, retenues, contours, projeter } ) {
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

	return (
		`<svg xmlns="http://www.w3.org/2000/svg" width="${ largeur }" height="${ hauteur }" ` +
		`viewBox="0 0 ${ largeur } ${ hauteur }">${ corps.join( '' ) }</svg>`
	);
}

/** Rasterise une toile SVG et rend ses pixels RGBA bruts. */
function rasteriser( svg, largeur ) {
	return new Resvg( svg, { fitTo: { mode: 'width', value: largeur } } ).render().pixels;
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
function construireStatique( { archive, jetons, emprise, contours, palette, quantifier } ) {
	const largeur = LARGEUR_STATIQUE;
	const etendueX = normX( emprise.est ) - normX( emprise.ouest );
	const etendueY = normY( emprise.sud ) - normY( emprise.nord );

	// Hauteur DÉRIVÉE de la bbox projetée en Web Mercator, jamais choisie (A-10).
	const hauteur = Math.round( ( largeur * etendueY ) / etendueX );
	const mpp = ( 2 * Math.PI * RAYON * Math.cos( ( ( ( emprise.sud + emprise.nord ) / 2 ) * Math.PI ) / 180 ) * etendueX ) / largeur;

	const projeter = ( [ lon, lat ] ) => [
		( ( normX( lon ) - normX( emprise.ouest ) ) / etendueX ) * largeur,
		( ( normY( lat ) - normY( emprise.nord ) ) / etendueY ) * hauteur,
	];

	const svg = toile( {
		largeur,
		hauteur,
		jetons,
		couches: null === archive ? {} : preparer( archive, mpp ),
		retenues: COUCHES_STATIQUE,
		contours,
		projeter,
	} );

	const octets = encoderPng8( quantifier( rasteriser( svg, largeur ), largeur * hauteur ), largeur, hauteur, palette );

	return { largeur, hauteur, mpp, octets };
}

/**
 * Pyramide de tuiles.
 *
 * UNE toile par zoom, découpée ENSUITE : une toile par tuile couperait deux fois
 * différemment un trait à cheval sur deux tuiles, et la couture se verrait.
 */
function construirePyramide( { archive, jetons, emprise, palette, quantifier } ) {
	const tuiles = [];
	const empreintes = {};
	const rendus = [];

	for ( const g of grilles( emprise ) ) {
		const mpp = metresParPixel( emprise, g.zoom );
		const cote = Math.pow( 2, g.zoom ) * TAILLE_TUILE;
		const originX = g.x0 * TAILLE_TUILE;
		const originY = g.y0 * TAILLE_TUILE;

		const svg = toile( {
			largeur: g.largeur_px,
			hauteur: g.hauteur_px,
			jetons,
			couches: preparer( archive, mpp ),
			contours: null,
			projeter: ( [ lon, lat ] ) => [ normX( lon ) * cote - originX, normY( lat ) * cote - originY ],
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

		journal( `  z${ g.zoom } — ${ g.colonnes } x ${ g.lignes } = ${ g.nombre } tuiles (toile ${ g.largeur_px } x ${ g.hauteur_px }, ~${ Math.round( mpp ) } m/px)` );
	}

	return {
		tuiles,
		empreintes,
		grilles: rendus,
		nombre: tuiles.length,
		octets: tuiles.reduce( ( total, tuile ) => total + tuile.octets.length, 0 ),
		bbox: bboxDeGrille( grilles( emprise )[ grilles( emprise ).length - 1 ] ),
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

function principal() {
	const jetons = controlerJetons();

	journal( `Jetons contrôlés dans ${ relatifAuDepot( CHEMINS.tokens ) } : les ${ Object.keys( JETONS_CARTE ).length } jetons --c-carte-* et ${ JETON_CONTOUR }` );

	const emprise = lireEmprise( CHEMINS.referentiel );
	const contours = lireContours();
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
	journal( `Contours de massifs : ${ contours.length }` );
	journal( `Palette fermée : ${ palette.length } couleurs, dérivées des seuls jetons` );

	let statique;
	let pyramide = null;

	try {
		statique = construireStatique( { archive, jetons, emprise, contours, palette, quantifier } );

		journal( `Image statique : ${ statique.largeur } x ${ statique.hauteur } px (~${ Math.round( statique.mpp ) } m/px), ${ statique.octets.length } octets` );

		if ( MODE_COMPLET === mode ) {
			pyramide = construirePyramide( { archive, jetons, emprise, palette, quantifier } );

			journal( `Pyramide : ${ pyramide.nombre } tuiles, ${ pyramide.octets } octets` );
		}
	} finally {
		purger();
	}

	if ( statique.octets.length > PLAFOND_STATIQUE_OCTETS ) {
		throw new Arret(
			`Image statique : ${ statique.octets.length } octets, plafond ${ PLAFOND_STATIQUE_OCTETS }. ` +
				'Mitigations, DANS CET ORDRE (contrat #9 §2) : (1) réduire la palette indexée, (2) supprimer les ' +
				'couches de fond les moins informatives, (3) ramener la largeur intrinsèque à 1280 px. ' +
				'JAMAIS une compression avec perte, jamais un second artefact, jamais un srcset.'
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
		emprise,
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
		outillage: { mapshaper: versionMapshaper(), node_major: nodeMajeur() },
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
		avertir( 'aucune pyramide n\'a été émise — 280 aplats uniformes seraient une carte qui affirme quelque chose de faux sur la géographie.' );

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
	principal();
} catch ( erreur ) {
	purger();
	process.stderr.write( `${ erreur instanceof Arret ? erreur.message : erreur.stack }\n` );
	process.exitCode = 1;
}
