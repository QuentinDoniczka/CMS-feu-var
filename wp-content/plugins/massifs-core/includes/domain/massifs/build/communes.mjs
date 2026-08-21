/**
 * Référentiel communal : extrait archivé, communes par massif, artefact de lookup.
 *
 * HORS LIGNE. Ce fichier ne touche jamais le réseau : il relit l'extrait
 * commité sous `source/`, que `recuperer-communes.mjs` — le seul script réseau
 * du module — a produit. C'est ce qui rend l'import rejouable depuis le seul
 * dépôt (§10 du contrat #45).
 *
 * DEUX ARTEFACTS, DEUX MÉCANISMES, ET ILS ÉCHOUENT INDÉPENDAMMENT (§4.6) :
 *
 *   - les communes PAR MASSIF sont calculées ici, au build, et bakées dans
 *     `data/massifs-13.php`. À l'exécution, elles ne coûtent rien et ne
 *     dépendent d'aucun fichier de géométrie ;
 *   - les polygones communaux partent dans un artefact de lookup sous
 *     `includes/domain/massifs/`, ouvert paresseusement par le seul chemin cron.
 *
 * Supprimer le second ne retire pas le premier. C'est délibéré, et la recette
 * le prouve plutôt que de l'affirmer.
 *
 * L'INTERSECTION SE CALCULE SUR LA SOURCE PLEINE PRÉCISION, jamais sur
 * `data/massifs-13.geometrie.json` (§4.2) : cette dernière a perdu ses îlots de
 * moins de 25 ha et subi 90 m de Douglas-Peucker. Une commune y apparaîtrait ou
 * en disparaîtrait POUR DES RAISONS DE RENDU.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
	Arret,
	DECIMALES_COORDONNEES,
	LAT_REFERENCE,
	METRES_PAR_DEGRE_LAT,
	METRES_PAR_DEGRE_LON,
	aireGeometrie,
	arrondir,
	bboxGeometrie,
	parties,
} from './geometrie.mjs';
import { CHEMIN_MAPSHAPER, MAPSHAPER_ABSENT } from './mapshaper.mjs';

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );
const EXTENSION = path.resolve( RACINE, '../../../..' );

/**
 * Chemins des artefacts communaux.
 *
 * L'artefact de lookup ne vit PAS sous `data/` (§3.1) :
 * `docker/wordpress/plugins-guard.conf` épargne délibérément `data/`, qui sert
 * la géométrie au navigateur. Y poser un mégaoctet de polygones communaux
 * publierait à une URL publique une donnée qu'aucun client ne demande. Sous
 * `includes/`, le garde le refuse — comportement voulu, pas effet de bord.
 */
export const CHEMINS_COMMUNES = {
	extrait: path.join( RACINE, 'source/communes-13-limitrophes.geojson' ),
	manifeste: path.join( RACINE, 'source/communes-13-limitrophes.manifeste.json' ),
	lookup: path.join( EXTENSION, 'includes/domain/massifs/communes-13.lookup.json' ),
};

/** Chemins relatifs à la racine de l'extension, tels que consignés dans les artefacts. */
export const CHEMINS_COMMUNES_RELATIFS = {
	extrait: 'includes/domain/massifs/build/source/communes-13-limitrophes.geojson',
	lookup: 'includes/domain/massifs/communes-13.lookup.json',
};

/**
 * Provenance du référentiel communal. Faits vérifiables, jamais de rédaction.
 *
 * `millesime` N'EST JAMAIS L'ALIAS (§2.1). La couche est publiée derrière
 * `ADMINEXPRESS-COG-CARTO.LATEST`, qui dérive en silence : une fusion ou un
 * renommage de commune afficherait un nom périmé comme courant sans qu'aucun
 * artefact ne le signale. `recuperer-communes.mjs` résout l'alias vers son
 * millésime daté et REFUSE d'écrire si le millésime résolu diffère de celui
 * épinglé ici — la montée de millésime est une décision humaine.
 */
export const SOURCE_COMMUNES = {
	producteur: 'IGN',
	jeu_de_donnees: 'ADMIN EXPRESS COG Carto',
	couche: 'commune',
	millesime: '2026',
	edition: '2026-01-01',
	edition_libelle: '1er janvier 2026',
	dataset_url: 'https://www.data.gouv.fr/datasets/admin-express/',
	crs: 'EPSG:4326',
	departements: [ '13', '83', '84', '30' ],
	attributs: [ 'code_insee', 'nom_officiel', 'code_insee_du_departement', 'superficie_cadastrale' ],
};

/** Licence de publication d'ADMIN EXPRESS. */
export const LICENCE_COMMUNES = {
	nom: 'Licence Ouverte',
	version: '2.0',
	identifiant: 'etalab-2.0',
	url: 'https://www.etalab.gouv.fr/wp-content/uploads/2017/04/ETALAB-Licence-Ouverte-v2.0.pdf',
};

/**
 * Paramètres du calcul, tous décidés par le propriétaire du projet (§4).
 *
 * `seuil_massif_pct` — une commune est « concernée » par un massif dès qu'elle
 * en couvre 1 %. Limitation connue et acceptée, consignée ici plutôt que
 * découverte : sur un très grand massif, 1 % reste une surface importante, et
 * une petite commune réellement concernée peut passer sous le seuil.
 *
 * `plafond_m` — au-delà, le serveur n'émet RIEN plutôt qu'un nom trompeur.
 *
 * `marge_clip_deg` — l'extrait retient les communes dont l'emprise recoupe
 * l'emprise des massifs élargie de cette marge. Elle doit rester STRICTEMENT
 * supérieure au plafond converti en degrés : c'est ce qui garantit que toute
 * commune située à moins de 5 km d'un point de la couverture figure bien dans
 * l'extrait. Sans quoi le lookup nommerait une commune plus lointaine mais
 * présente, à la place d'une commune plus proche mais absente.
 */
export const SEUILS_COMMUNES = {
	seuil_massif_pct: 1,
	plafond_m: 5000,
	marge_clip_deg: 0.15,
};

/**
 * Simplification de l'artefact de lookup.
 *
 * 25 m de Douglas-Peucker : deux ordres de grandeur sous le plafond de 5 000 m,
 * et sous l'incertitude d'un périmètre estimé par satellite. Un sommet de zone à
 * moins de 25 m d'une limite communale est de toute façon ambigu — ce que
 * l'artefact ne peut pas trancher, il ne doit pas prétendre le trancher.
 *
 * mapshaper construit une topologie d'arcs partagés à l'import : les limites
 * mitoyennes sont simplifiées UNE FOIS, en arc partagé. Aucun interstice ni
 * recouvrement ne peut donc apparaître entre deux communes voisines — ce qui
 * ferait tomber un sommet dans aucune commune, ou dans deux.
 */
export const LOOKUP = {
	type: 'massifs-communes-lookup',
	version: 1,
	algorithme: 'douglas-peucker',
	intervalle_m: 25,
	precision_decimales: DECIMALES_COORDONNEES,
	octets_max: 1572864,
};

/**
 * Lance mapshaper et lève si l'exécution échoue.
 *
 * `importer.mjs` garde la sienne : elle consigne son argv dans l'artefact de
 * recette de la géométrie et n'a donc pas la même sortie. Seule la mécanique de
 * lancement est commune, et elle tient en six lignes.
 *
 * @param {string[]} options Arguments passés à mapshaper.
 */
function lancerMapshaper( options ) {
	if ( ! fs.existsSync( CHEMIN_MAPSHAPER ) ) {
		throw new Arret( MAPSHAPER_ABSENT );
	}

	const execution = spawnSync( process.execPath, [ CHEMIN_MAPSHAPER, ...options ], {
		encoding: 'utf8',
	} );

	if ( 0 !== execution.status ) {
		throw new Arret( `mapshaper a échoué : ${ execution.stderr || execution.stdout }` );
	}
}

/** Deux emprises se recoupent-elles ? */
export function recoupe( a, b ) {
	return a.ouest <= b.est && a.est >= b.ouest && a.sud <= b.nord && a.nord >= b.sud;
}

/** Emprise élargie d'une marge en degrés. */
export function elargir( bbox, marge ) {
	return {
		ouest: bbox.ouest - marge,
		sud: bbox.sud - marge,
		est: bbox.est + marge,
		nord: bbox.nord + marge,
	};
}

/**
 * Emprise de couverture du lookup : l'emprise de découpe RÉTRÉCIE du plafond.
 *
 * C'est la seule zone où une réponse est honnête. Au-delà, une commune plus
 * proche a pu ne pas être retenue dans l'extrait, et le lookup nommerait la
 * deuxième plus proche en la présentant comme la plus proche.
 */
export function couvertureDepuisDecoupe( decoupe ) {
	const marge_lat = SEUILS_COMMUNES.plafond_m / METRES_PAR_DEGRE_LAT;
	const marge_lon = SEUILS_COMMUNES.plafond_m / METRES_PAR_DEGRE_LON;

	return {
		ouest: arrondir( decoupe.ouest + marge_lon, DECIMALES_COORDONNEES ),
		sud: arrondir( decoupe.sud + marge_lat, DECIMALES_COORDONNEES ),
		est: arrondir( decoupe.est - marge_lon, DECIMALES_COORDONNEES ),
		nord: arrondir( decoupe.nord - marge_lat, DECIMALES_COORDONNEES ),
	};
}

/**
 * Relit l'extrait commité et son manifeste, en contrôlant ce qui peut l'être.
 *
 * Un extrait absent n'est pas rattrapé en silence : sans lui, les communes par
 * massif seraient vides, et une liste vide se lit « aucune commune concernée ».
 *
 * @return {{fc:object,manifeste:object,brut:Buffer,octets:number}}
 */
export function lireExtraitCommunes() {
	if ( ! fs.existsSync( CHEMINS_COMMUNES.extrait ) || ! fs.existsSync( CHEMINS_COMMUNES.manifeste ) ) {
		throw new Arret(
			`Extrait communal absent (${ CHEMINS_COMMUNES_RELATIFS.extrait }). Il est COMMITÉ : un dépôt ` +
				'complet le porte. Le régénérer demande le réseau : `npm run recuperer-communes`.'
		);
	}

	const brut = fs.readFileSync( CHEMINS_COMMUNES.extrait );
	const fc = JSON.parse( brut.toString( 'utf8' ) );
	const manifeste = JSON.parse( fs.readFileSync( CHEMINS_COMMUNES.manifeste, 'utf8' ) );

	if ( 'FeatureCollection' !== fc.type || ! Array.isArray( fc.features ) || 0 === fc.features.length ) {
		throw new Arret( 'Extrait communal illisible : aucune entité.' );
	}

	if ( SOURCE_COMMUNES.millesime !== manifeste.millesime ) {
		throw new Arret(
			`Millésime de l'extrait (${ manifeste.millesime }) différent du millésime épinglé ` +
				`(${ SOURCE_COMMUNES.millesime }). Une montée de millésime est une décision humaine.`
		);
	}

	// L'alias mouvant ne doit se lire NULLE PART dans un artefact (§2.1). Le
	// contrôle est ici, au plus près de la lecture, et non seulement en recette :
	// un extrait qui le porterait ne doit pas produire un import.
	if ( JSON.stringify( manifeste ).includes( 'LATEST' ) ) {
		throw new Arret( 'Le manifeste de l\'extrait porte la chaîne « LATEST » : millésime non résolu.' );
	}

	// Les octets lus sont RENDUS À L'APPELANT, jamais relus : l'import consigne
	// leur empreinte, et un second `readFileSync` du même fichier ouvrirait la
	// porte à une taille et une empreinte prises sur deux lectures différentes.
	return { fc, manifeste, brut, octets: brut.length };
}

/**
 * Communes concernées par chaque massif, calculées à l'intersection.
 *
 * L'intersection est faite par mapshaper — un clip topologique éprouvé — et non
 * par un découpage de polygones écrit ici : une implémentation maison du clip
 * de Vatti se serait trompée sur les trous et les îlots, c'est-à-dire
 * exactement la forme des massifs des Bouches-du-Rhône.
 *
 * Les communes sont pré-filtrées par emprise avant chaque clip : une commune
 * dont l'emprise ne recoupe pas celle du massif ne peut pas l'intersecter, et
 * l'écarter d'avance divise le travail de mapshaper par dix.
 *
 * @param {Array<{code:string,feature:object|null}>} appariement Massifs appariés à leur entité source.
 * @param {object}                                   communesFC  Extrait communal.
 * @return {{parMassif:Object<string,string[]>,mesures:Array<object>}}
 */
export function communesParMassif( appariement, communesFC ) {
	const travail = path.join( RACINE, '_communes' );
	const cheminMassif = `${ travail }-massif.geojson`;
	const cheminCommunes = `${ travail }-source.geojson`;
	const cheminSortie = `${ travail }-clip.geojson`;

	const communes = communesFC.features.map( ( f ) => ( {
		insee: String( f.properties.code_insee ),
		nom: String( f.properties.nom_officiel ),
		bbox: bboxGeometrie( f.geometry ),
		feature: f,
	} ) );

	const parMassif = {};
	const mesures = [];

	try {
		for ( const { code, feature } of appariement ) {
			if ( ! feature ) {
				// Massif retiré : il n'a plus d'entité source, donc plus de surface
				// à intersecter. Sa liste est vide — inventer les communes d'un
				// périmètre qui n'est plus publié serait affirmer une géographie
				// que la source ne porte plus.
				parMassif[ code ] = [];
				continue;
			}

			const aireMassif = aireGeometrie( feature.geometry );
			const emprise = bboxGeometrie( feature.geometry );
			const candidates = communes.filter( ( commune ) => recoupe( commune.bbox, emprise ) );

			if ( 0 === candidates.length ) {
				parMassif[ code ] = [];
				mesures.push( { code, aire_m2: Math.round( aireMassif ), candidates: 0, retenues: 0, parts: [] } );
				continue;
			}

			fs.writeFileSync(
				cheminMassif,
				JSON.stringify( { type: 'FeatureCollection', features: [ { type: 'Feature', properties: {}, geometry: feature.geometry } ] } )
			);
			fs.writeFileSync(
				cheminCommunes,
				JSON.stringify( {
					type: 'FeatureCollection',
					features: candidates.map( ( commune ) => ( {
						type: 'Feature',
						properties: { insee: commune.insee },
						geometry: commune.feature.geometry,
					} ) ),
				} )
			);

			lancerMapshaper( [ cheminCommunes, '-clip', cheminMassif, '-o', 'format=geojson', cheminSortie ] );

			const clip = JSON.parse( fs.readFileSync( cheminSortie, 'utf8' ) );
			const parts = [];

			for ( const morceau of clip.features || [] ) {
				if ( ! morceau.geometry || ! morceau.geometry.coordinates ) {
					continue;
				}

				const aire = aireGeometrie( morceau.geometry );
				const pct = ( aire / aireMassif ) * 100;
				const commune = candidates.find( ( c ) => c.insee === String( morceau.properties.insee ) );

				if ( ! commune ) {
					throw new Arret( `Clip de ${ code } : code INSEE inconnu « ${ morceau.properties.insee } ».` );
				}

				parts.push( { insee: commune.insee, nom: commune.nom, aire_m2: aire, part_pct: arrondir( pct, 3 ) } );
			}

			// Tri par surface DÉCROISSANTE (§4.1) : la première commune de la liste
			// est celle qui porte la plus grande part du massif. À surface égale, le
			// code INSEE tranche — sans quoi l'ordre dépendrait de mapshaper.
			parts.sort( ( a, b ) => ( b.aire_m2 - a.aire_m2 ) || a.insee.localeCompare( b.insee ) );

			const retenues = parts.filter( ( part ) => part.part_pct >= SEUILS_COMMUNES.seuil_massif_pct );

			parMassif[ code ] = retenues.map( ( part ) => part.nom );
			mesures.push( {
				code,
				aire_m2: Math.round( aireMassif ),
				candidates: candidates.length,
				retenues: retenues.length,
				parts: parts.map( ( part ) => ( { insee: part.insee, nom: part.nom, part_pct: part.part_pct } ) ),
			} );
		}
	} finally {
		for ( const fichier of [ cheminMassif, cheminCommunes, cheminSortie ] ) {
			if ( fs.existsSync( fichier ) ) {
				fs.unlinkSync( fichier );
			}
		}
	}

	return { parMassif, mesures };
}

/** Aplatit un anneau `[[lon,lat],…]` en `[lon,lat,lon,lat,…]`. */
function aplatirAnneau( anneau ) {
	const plat = [];

	for ( const [ lon, lat ] of anneau ) {
		plat.push( lon, lat );
	}

	return plat;
}

/**
 * Construit l'artefact de lookup, simplifié et strictement serveur.
 *
 * Format : anneaux APLATIS en `[lon, lat, lon, lat, …]`. Un tableau de couples
 * ferait allouer à PHP un `array` de deux flottants par sommet — 76 000 tableaux
 * pour rien. Le format plat est aussi ce qui rend la mémoire mesurable et basse
 * (voir la mesure consignée dans `reference.json`).
 *
 * Aucune population, aucune date de recensement, aucun code SIREN : l'artefact
 * ne porte que ce que le rendu consomme.
 *
 * @param {object} communesFC Extrait communal.
 * @param {object} decoupe    Emprise de découpe de l'extrait.
 * @return {{contenu:Buffer,communes:number,sommets:number,couverture:object}}
 */
export function construireLookup( communesFC, decoupe ) {
	const travail = path.join( RACINE, '_communes-lookup' );
	const entree = `${ travail }-in.geojson`;
	const sortie = `${ travail }-out.geojson`;
	let simplifie;

	try {
		fs.writeFileSync(
			entree,
			JSON.stringify( {
				type: 'FeatureCollection',
				features: communesFC.features.map( ( f ) => ( {
					type: 'Feature',
					properties: {
						insee: String( f.properties.code_insee ),
						nom: String( f.properties.nom_officiel ),
						dep: String( f.properties.code_insee_du_departement ),
					},
					geometry: f.geometry,
				} ) ),
			} )
		);

		lancerMapshaper( [
			entree,
			'-simplify',
			'dp',
			`interval=${ LOOKUP.intervalle_m }`,
			'keep-shapes',
			'-o',
			`precision=0.${ '0'.repeat( LOOKUP.precision_decimales - 1 ) }1`,
			'format=geojson',
			sortie,
		] );

		simplifie = JSON.parse( fs.readFileSync( sortie, 'utf8' ) );
	} finally {
		for ( const fichier of [ entree, sortie ] ) {
			if ( fs.existsSync( fichier ) ) {
				fs.unlinkSync( fichier );
			}
		}
	}

	const couverture = couvertureDepuisDecoupe( decoupe );
	let sommets = 0;

	// Tri par code INSEE : l'ordre de l'artefact est l'ordre de départage d'une
	// égalité de distance côté PHP. Un ordre hérité de mapshaper le rendrait
	// dépendant de la version de l'outil.
	const entites = simplifie.features
		.filter( ( f ) => f.geometry && f.geometry.coordinates )
		.sort( ( a, b ) => a.properties.insee.localeCompare( b.properties.insee ) );

	const communes = entites.map( ( f ) => {
		const boite = bboxGeometrie( f.geometry );
		const listeParties = parties( f.geometry ).map( ( partie ) =>
			partie.map( ( anneau ) => {
				sommets += anneau.length;

				return aplatirAnneau( anneau );
			} )
		);

		return {
			insee: f.properties.insee,
			nom: f.properties.nom,
			dep: f.properties.dep,
			bbox: [
				arrondir( boite.ouest, DECIMALES_COORDONNEES ),
				arrondir( boite.sud, DECIMALES_COORDONNEES ),
				arrondir( boite.est, DECIMALES_COORDONNEES ),
				arrondir( boite.nord, DECIMALES_COORDONNEES ),
			],
			parties: listeParties,
		};
	} );

	const artefact = {
		a_propos:
			'Artefact de lookup communal. FICHIER GÉNÉRÉ, jamais édité à la main. Strictement SERVEUR : jamais servi au navigateur (voir docker/wordpress/plugins-guard.conf), lu uniquement sur le chemin cron par massifs_commune_de_la_zone(). Régénéré par `npm run importer`.',
		type: LOOKUP.type,
		version: LOOKUP.version,
		producteur: SOURCE_COMMUNES.producteur,
		jeu_de_donnees: SOURCE_COMMUNES.jeu_de_donnees,
		millesime: SOURCE_COMMUNES.millesime,
		edition: SOURCE_COMMUNES.edition,
		licence: LICENCE_COMMUNES.identifiant,
		crs: SOURCE_COMMUNES.crs,
		simplification: {
			algorithme: LOOKUP.algorithme,
			intervalle_m: LOOKUP.intervalle_m,
			precision_decimales: LOOKUP.precision_decimales,
		},
		// Les facteurs de la projection locale voyagent AVEC l'artefact : PHP ne
		// redéfinit aucune constante de build. Deux définitions de la même
		// projection finiraient par mesurer deux distances différentes.
		projection: {
			lat_reference: LAT_REFERENCE,
			metres_par_degre_lat: METRES_PAR_DEGRE_LAT,
			metres_par_degre_lon: arrondir( METRES_PAR_DEGRE_LON, 3 ),
		},
		plafond_m: SEUILS_COMMUNES.plafond_m,
		decoupe: {
			ouest: arrondir( decoupe.ouest, DECIMALES_COORDONNEES ),
			sud: arrondir( decoupe.sud, DECIMALES_COORDONNEES ),
			est: arrondir( decoupe.est, DECIMALES_COORDONNEES ),
			nord: arrondir( decoupe.nord, DECIMALES_COORDONNEES ),
		},
		couverture,
		nombre: communes.length,
		communes,
	};

	const contenu = Buffer.from( `${ JSON.stringify( artefact ) }\n`, 'utf8' );

	if ( contenu.length > LOOKUP.octets_max ) {
		throw new Arret(
			`Artefact de lookup de ${ contenu.length } octets, au-dessus du plafond de ${ LOOKUP.octets_max }. ` +
				'Il est ouvert en mémoire par PHP sur le chemin cron : le plafond garde cette mémoire bornée.'
		);
	}

	return { contenu, communes: communes.length, sommets, couverture };
}

/**
 * Mention §9 du référentiel communal, SÉPARÉE de celle des périmètres.
 *
 * Deux producteurs, deux licences, deux millésimes : les fusionner produirait
 * une phrase qui n'attribue correctement ni l'un ni l'autre. La phrase porte le
 * MILLÉSIME RÉSOLU, jamais l'alias (§2.1), et elle est composée à partir des
 * constantes de provenance — une phrase saisie à la main finirait par annoncer
 * un millésime que l'artefact ne porte plus.
 */
export function attributionCommunes() {
	const jeu = `${ SOURCE_COMMUNES.jeu_de_donnees } ${ SOURCE_COMMUNES.millesime }`;

	return {
		phrase:
			`Source : ${ SOURCE_COMMUNES.producteur } — ${ jeu } (édition du ${ SOURCE_COMMUNES.edition_libelle }), ` +
			`via data.gouv.fr — ${ LICENCE_COMMUNES.nom } ${ LICENCE_COMMUNES.version }`,
		phrase_courte: `${ SOURCE_COMMUNES.producteur } — ${ jeu } — ${ LICENCE_COMMUNES.nom } ${ LICENCE_COMMUNES.version }`,
		lien_source: SOURCE_COMMUNES.dataset_url,
		lien_licence: LICENCE_COMMUNES.url,
	};
}

/**
 * Phrase de provenance de la liste par massif, servie telle quelle au JSON public.
 *
 * Elle dit que la liste RÉSULTE DE NOTRE PROPRE CALCUL. Un réutilisateur du JSON
 * ne doit pas pouvoir la prendre pour une publication officielle de la DDTM.
 */
export function sourceCommunesParMassif() {
	return (
		`calculée par intersection des périmètres DDTM avec ${ SOURCE_COMMUNES.producteur } ` +
		`${ SOURCE_COMMUNES.jeu_de_donnees } ${ SOURCE_COMMUNES.millesime }, seuil de ` +
		`${ SEUILS_COMMUNES.seuil_massif_pct } % de la surface du massif`
	);
}
