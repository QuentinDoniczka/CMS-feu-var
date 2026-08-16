/**
 * Recette de rendu — front et back ensemble, dans un vrai navigateur, contre la
 * stack Docker du dépôt.
 *
 * Ce n'est pas une suite de tests unitaires : chaque scénario place la base dans
 * un état connu (via `wp eval-file` dans le conteneur d'outillage), charge le
 * site réel en HTTP, et n'affirme que ce qu'un visiteur observerait — les
 * requêtes réellement émises par le navigateur, le HTML servi, les octets
 * transférés, l'arbre d'accessibilité rendu.
 *
 * Aucune source externe n'est jamais contactée : les statuts sont écrits par la
 * fonction d'écriture publique du domaine, jamais récupérés chez la préfecture.
 *
 * Lancement :
 *   node tests/rendu/recette-rendu.mjs
 *   node tests/rendu/recette-rendu.mjs --filtre=tierce
 *
 * Prérequis : la stack tourne (`bash docker/up.sh`), et `playwright-core` +
 * `axe-core` sont résolubles (voir tests/README.md).
 *
 * @license GPL-2.0-or-later
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ICI = path.dirname( fileURLToPath( import.meta.url ) );
const RACINE = path.resolve( ICI, '..', '..' );

// ---------------------------------------------------------------- harnais

const bilan = { ok: 0, ko: 0, echecs: [] };
let scenarioCourant = '';

function scenario( nom ) {
	scenarioCourant = nom;
	console.log( `=============================== ${ nom }` );
}

function ok( message ) {
	bilan.ok += 1;
	console.log( `  ok   ${ message }` );
}

function ko( message, attendu, obtenu ) {
	bilan.ko += 1;
	bilan.echecs.push( `${ scenarioCourant } — ${ message }` );
	console.log( `  ECHEC ${ message }` );
	console.log( `         attendu : ${ rendre( attendu ) }` );
	console.log( `         obtenu  : ${ rendre( obtenu ) }` );
}

function rendre( valeur ) {
	if ( typeof valeur === 'string' ) {
		return valeur;
	}
	return JSON.stringify( valeur );
}

function assert( condition, message, attendu, obtenu ) {
	if ( condition ) {
		ok( message );
		return true;
	}
	ko( message, attendu, obtenu );
	return false;
}

function egal( attendu, obtenu, message ) {
	return assert(
		JSON.stringify( attendu ) === JSON.stringify( obtenu ),
		message,
		attendu,
		obtenu
	);
}

function note( texte ) {
	console.log( `  note ${ texte }` );
}

/**
 * Texte SOURCE d'un élément, jamais son rendu.
 *
 * `innerText` renvoie le texte tel que peint : les capitales produites par
 * `text-transform: uppercase` y apparaissent en capitales. Or le contrat impose
 * précisément que le HTML porte la casse normale et que les capitales viennent
 * du CSS. Comparer sur `innerText` testerait donc le CSS en croyant tester la
 * chaîne servie.
 *
 * @param {import('playwright-core').Locator} localisateur Cible.
 * @return {Promise<string>} Texte source, espaces normalisés.
 */
async function texteSource( localisateur ) {
	const brut = ( await localisateur.textContent() ) ?? '';
	return brut.replace( /\s+/g, ' ' ).trim();
}

// ---------------------------------------------------------------- stack

function lireEnv( cle, defaut ) {
	const fichier = path.join( RACINE, '.env' );
	if ( ! existsSync( fichier ) ) {
		return defaut;
	}
	const ligne = readFileSync( fichier, 'utf8' )
		.split( '\n' )
		.find( ( l ) => l.startsWith( `${ cle }=` ) );
	return ligne ? ligne.slice( cle.length + 1 ).trim() : defaut;
}

const PORT = lireEnv( 'WORDPRESS_PORT', '3002' );
const BASE = `http://localhost:${ PORT }`;
const ORIGINE = new URL( BASE ).origin;

/**
 * Place la base dans un état de départ connu, à l'intérieur de la stack.
 *
 * @param {string} mode absente | jour-nominal | veille-seule | jour-complet | jour-partiel
 * @param {...(string|number)} parametres Arguments du mode (nombre de massifs renseignés, autorisés…).
 * @return {string} Ligne d'état rendue par la fabrique.
 */
function poserEtat( mode, ...parametres ) {
	const sortie = execFileSync(
		'docker',
		[
			'compose',
			'run',
			'--rm',
			'-T',
			'-v',
			`${ RACINE }/tests:/massifs-tests:ro`,
			'wpcli',
			'wp',
			'--path=/var/www/html',
			'eval-file',
			'/massifs-tests/rendu/etats.php',
			mode,
			...parametres.map( String ),
		],
		{ cwd: RACINE, encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
	);
	const ligne = sortie.split( '\n' ).find( ( l ) => l.startsWith( 'ETAT ' ) ) ?? '';
	note( `état posé : ${ ligne.trim() }` );
	return ligne.trim();
}

// ---------------------------------------------------------------- navigateur

const require_ = createRequire( import.meta.url );

function resoudre( module ) {
	const pistes = [ module ];
	if ( process.env.MASSIFS_NODE_MODULES ) {
		pistes.unshift( path.join( process.env.MASSIFS_NODE_MODULES, module ) );
	}
	for ( const piste of pistes ) {
		try {
			return require_.resolve( piste );
		} catch {
			/* piste suivante */
		}
	}
	throw new Error(
		`Module « ${ module } » introuvable. Installez-le, ou pointez MASSIFS_NODE_MODULES ` +
			`sur un répertoire node_modules qui le contient.`
	);
}

function chercherChromium() {
	if ( process.env.MASSIFS_CHROME && existsSync( process.env.MASSIFS_CHROME ) ) {
		return process.env.MASSIFS_CHROME;
	}
	const racines = [
		process.env.LOCALAPPDATA ? path.join( process.env.LOCALAPPDATA, 'ms-playwright' ) : null,
		process.env.HOME ? path.join( process.env.HOME, '.cache', 'ms-playwright' ) : null,
	].filter( Boolean );

	for ( const racine of racines ) {
		if ( ! existsSync( racine ) ) {
			continue;
		}
		const { readdirSync } = require_( 'node:fs' );
		const versions = readdirSync( racine )
			.filter( ( d ) => d.startsWith( 'chromium-' ) )
			.sort()
			.reverse();
		for ( const version of versions ) {
			for ( const relatif of [ 'chrome-win64/chrome.exe', 'chrome-win/chrome.exe', 'chrome-linux/chrome', 'chrome-mac/Chromium.app/Contents/MacOS/Chromium' ] ) {
				const candidat = path.join( racine, version, relatif );
				if ( existsSync( candidat ) ) {
					return candidat;
				}
			}
		}
	}
	return undefined;
}

// ---------------------------------------------------------------- pages

const PAGES = [
	{ chemin: '/', nom: 'accueil', statuts: true },
	{ chemin: '/hello-world/', nom: 'article (repli index.php)' },
	{ chemin: '/sample-page/', nom: 'page (repli index.php)' },
	{ chemin: '/wp-login.php', nom: 'connexion' },
	{ chemin: '/?p=99999999', nom: 'page inexistante' },
];

/**
 * Charge une page et relève tout ce que le navigateur a réellement demandé.
 *
 * @param {import('playwright-core').BrowserContext} contexte Contexte.
 * @param {string} chemin Chemin relatif.
 * @return {Promise<object>} Relevé.
 */
async function charger( contexte, chemin ) {
	const page = await contexte.newPage();
	const requetes = [];
	const echecs = [];

	page.on( 'request', ( r ) => requetes.push( { url: r.url(), type: r.resourceType() } ) );
	page.on( 'requestfailed', ( r ) => echecs.push( `${ r.url() } (${ r.failure()?.errorText })` ) );

	const tailles = [];
	page.on( 'response', async ( reponse ) => {
		try {
			const s = await reponse.request().sizes();
			tailles.push( {
				url: reponse.url(),
				type: reponse.request().resourceType(),
				statut: reponse.status(),
				octets: ( s.responseBodySize ?? 0 ) + ( s.responseHeadersSize ?? 0 ),
			} );
		} catch {
			/* réponse déjà disparue */
		}
	} );

	const reponse = await page.goto( BASE + chemin, { waitUntil: 'networkidle' } );

	return { page, requetes, echecs, tailles, statut: reponse ? reponse.status() : 0 };
}

/**
 * Retire les commentaires `/* … *\/` d'une feuille de style.
 *
 * Une URL écrite dans un commentaire CSS ne produit AUCUNE requête : elle n'est
 * pas une déclaration, le moteur ne la voit jamais. La distinguer n'est pas un
 * assouplissement de l'exigence du §12 — c'est ce qui la rend mesurable sur une
 * bibliothèque vendorisée que le contrat #7 §10 interdit d'éditer (`leaflet.css`
 * est repris « octet pour octet » et porte deux adresses de bugtracker dans ses
 * commentaires, l. 64 et 102). Les URLs ainsi retirées ne disparaissent pas de
 * la recette : elles sont relevées et affichées à part, et l'assertion des
 * origines RÉELLEMENT CONTACTÉES par le navigateur, elle, ne connaît aucune
 * exception.
 *
 * @param {string} css Feuille servie.
 * @return {string} Feuille sans ses commentaires.
 */
function sansCommentairesCss( css ) {
	return css.replace( /\/\*[\s\S]*?\*\//g, ' ' );
}

/**
 * Toute URL absolue citée dans un texte (HTML ou CSS).
 *
 * @param {string} texte Contenu.
 * @return {string[]} URLs.
 */
function urlsAbsolues( texte ) {
	const trouvees = new Set();
	const motifs = [
		/https?:\/\/[^\s"'()<>\\]+/g,
		/url\(\s*['"]?(https?:\/\/[^)'"]+)/g,
		/@import\s+['"]?(https?:\/\/[^;'"]+)/g,
	];
	for ( const motif of motifs ) {
		for ( const m of texte.matchAll( motif ) ) {
			trouvees.add( ( m[ 1 ] ?? m[ 0 ] ).replace( /[,;'")]+$/, '' ) );
		}
	}
	return [ ...trouvees ];
}

// ---------------------------------------------------------------- scénarios

async function s01_zeroRequeteTierce( navigateur ) {
	scenario( '01 — zéro requête du navigateur vers un domaine tiers (§12, preuve)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext();
	const originesDemandees = new Map();
	const originesCitees = new Map();
	const feuilles = new Set();
	const toutesLesRequetes = [];

	for ( const cible of PAGES ) {
		const { page, requetes, echecs } = await charger( contexte, cible.chemin );

		for ( const r of requetes ) {
			toutesLesRequetes.push( r.url );
			const origine = new URL( r.url ).origin;
			originesDemandees.set( origine, ( originesDemandees.get( origine ) ?? 0 ) + 1 );
		}

		const html = await page.content();
		for ( const u of urlsAbsolues( html ) ) {
			const origine = new URL( u ).origin;
			originesCitees.set( origine, ( originesCitees.get( origine ) ?? 0 ) + 1 );
		}

		for ( const lien of await page.locator( 'link[rel="stylesheet"], link[rel="preload"]' ).all() ) {
			const href = await lien.getAttribute( 'href' );
			if ( href ) {
				feuilles.add( new URL( href, BASE + cible.chemin ).href );
			}
		}

		assert(
			echecs.length === 0,
			`${ cible.nom } : aucune requête en échec`,
			'[]',
			echecs.join( ', ' )
		);

		const scripts = await page.locator( 'script[src]' ).count();
		note( `${ cible.nom } : ${ requetes.length } requêtes, ${ scripts } <script src>` );

		await page.close();
	}

	// Les feuilles servies sont relues et fouillées : url(), @import, @font-face.
	const requeteApi = contexte.request;
	for ( const feuille of feuilles ) {
		if ( ! feuille.endsWith( '.css' ) && ! feuille.includes( '.css?' ) ) {
			continue;
		}
		const reponse = await requeteApi.get( feuille );
		const corps = await reponse.text();
		const declarations = sansCommentairesCss( corps );

		// Ce qui compte : les URLs que le moteur peut réellement suivre, donc
		// celles des DÉCLARATIONS. Une seule tierce ici est un défaut.
		for ( const u of urlsAbsolues( declarations ) ) {
			const origine = new URL( u ).origin;
			originesCitees.set( origine, ( originesCitees.get( origine ) ?? 0 ) + 1 );
			if ( origine !== ORIGINE ) {
				ko( `feuille tierce référencée dans une DÉCLARATION de ${ feuille }`, ORIGINE, u );
			}
		}

		// Les commentaires sont relevés, jamais tus : une adresse tierce y est
		// inerte, mais elle doit rester visible au rapport de recette.
		const enCommentaire = urlsAbsolues( corps ).filter(
			( u ) => ! urlsAbsolues( declarations ).includes( u ) && new URL( u ).origin !== ORIGINE
		);
		if ( enCommentaire.length ) {
			note( `${ feuille.replace( BASE, '' ) } : ${ enCommentaire.length } adresse(s) tierce(s) en COMMENTAIRE, inertes → ${ enCommentaire.join( ', ' ) }` );
		}

		const relatives = [ ...declarations.matchAll( /url\(\s*['"]?([^)'"]+)/g ) ].map( ( m ) => m[ 1 ] );
		note( `${ feuille.replace( BASE, '' ) } : url() → ${ relatives.join( ', ' ) || '(aucune)' }` );
	}

	const tierces = [ ...originesDemandees.keys() ].filter( ( o ) => o !== ORIGINE );
	egal( [], tierces, 'aucune origine tierce n’a été CONTACTÉE par le navigateur' );

	// Assertions léguées par le contrat #7 §12 (1, 2 et 6) et par le contrat #9.
	// `leaflet.css` porte trois `url(images/…)` inertes : la chaîne #7 n'ajoute ni
	// L.Control.Layers, ni L.Marker, ni L.Icon.Default, donc aucune de ces règles
	// n'est atteinte. La preuve est ici, sur les requêtes réellement émises — un
	// 404 sur `images/marker-icon.png` serait le signe qu'une API interdite a été
	// appelée. De même, la ligne `sourceMappingURL` a été retirée du build
	// vendorisé : aucune requête ne doit viser un `.map`.
	egal(
		[],
		toutesLesRequetes.filter( ( u ) => u.includes( '/vendor/leaflet/images/' ) ),
		'contrat #7 §12.1 : aucune requête vers /vendor/leaflet/images/*'
	);
	egal(
		[],
		toutesLesRequetes.filter( ( u ) => /\.map(\?|$)/.test( u ) ),
		'contrat #7 §12.2 : aucune requête vers une source map'
	);

	// Le fond de carte est la ressource la plus exposée à une fuite tierce : le
	// gabarit d'URL vient du serveur, et `carte.js` refuse structurellement toute
	// origine autre que la sienne. On mesure les tuiles RÉELLEMENT demandées.
	const tuiles = toutesLesRequetes.filter( ( u ) => /\/data\/tuiles\/.+\.png/.test( u ) );
	assert( tuiles.length > 0, 'le fond de carte est réellement chargé (des tuiles sont demandées)', '> 0 tuile', tuiles.length );
	egal(
		[],
		tuiles.filter( ( u ) => new URL( u ).origin !== ORIGINE ),
		'contrainte #2 : chaque tuile du fond de carte vient de NOTRE origine'
	);
	note( `tuiles demandées : ${ tuiles.length }, toutes sur ${ ORIGINE }` );

	// Contrat #9 I-9.2 : aucun serveur de tuiles rendues, sous aucune forme, y
	// compris en commentaire de code ou en `errorTileUrl` du cas dégradé.
	const carteJs = await ( await contexte.request.get( `${ BASE }/wp-content/themes/massifs/assets/js/carte/carte.js` ) ).text();
	egal(
		[],
		[ 'tile.openstreetmap', 'tile.osm', 'basemaps.', 'thunderforest', 'mapbox', 'maptiler', 'stadiamaps' ].filter( ( m ) => carteJs.includes( m ) ),
		'contrat #9 I-9.2 : carte.js ne nomme aucun serveur de tuiles tiers, même en commentaire'
	);
	egal(
		[],
		urlsAbsolues( carteJs ).filter( ( u ) => new URL( u ).origin !== ORIGINE ),
		'carte.js ne porte aucune URL absolue tierce'
	);
	note( `origines contactées : ${ [ ...originesDemandees.entries() ].map( ( [ o, n ] ) => `${ o } (${ n })` ).join( ' · ' ) }` );
	note( `origines citées dans le HTML/CSS : ${ [ ...originesCitees.entries() ].map( ( [ o, n ] ) => `${ o } (${ n })` ).join( ' · ' ) }` );

	// Fuites WordPress connues, vérifiées sur le HTML réellement servi.
	const accueil = await ( await contexte.request.get( BASE + '/' ) ).text();
	assert( ! accueil.includes( 's.w.org' ), 'aucune mention de s.w.org dans le HTML servi', 0, ( accueil.match( /s\.w\.org/g ) ?? [] ).length );
	assert( ! accueil.includes( 'wp--preset' ), 'aucune custom property --wp--preset--* servie', 0, ( accueil.match( /wp--preset/g ) ?? [] ).length );
	assert( ! accueil.includes( 'gravatar' ), 'aucune URL Gravatar dans le HTML public', 0, ( accueil.match( /gravatar/g ) ?? [] ).length );
	assert( ! /wp-block-library|classic-theme-styles|global-styles/.test( accueil ), 'aucune feuille de blocs ni global-styles servie', 'aucune', ( /wp-block-library|classic-theme-styles|global-styles/.exec( accueil ) ?? [ '' ] )[ 0 ] );
	assert( ! accueil.includes( 'emoji' ), 'aucun script ni style émoji', 'aucun', ( accueil.match( /emoji/g ) ?? [] ).length );

	const speculation = ( accueil.match( /type=["']speculationrules["']/g ) ?? [] ).length;
	note( `<script type="speculationrules"> du cœur : ${ speculation } (même origine ; déclenche un préchargement de document)` );

	await contexte.close();
}

async function s02_sansJavascript( navigateur ) {
	scenario( '02 — utilisable JavaScript désactivé (§3, §5.5)' );
	const etat = poserEtat( 'jour-nominal' );
	const autorises = Number( /autorises=(\d+)/.exec( etat )?.[ 1 ] ?? -1 );
	const total = Number( /total=(\d+)/.exec( etat )?.[ 1 ] ?? -1 );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const html = await page.content();

	egal( 'fr-FR', await page.getAttribute( 'html', 'lang' ), 'langue déclarée sur <html>' );
	egal( 1, await page.locator( 'h1' ).count(), 'un seul h1 sur l’accueil, sans JS' );
	egal(
		`Aujourd’hui, ${ autorises } massifs sur ${ total } sont d’accès autorisé.`,
		await texteSource( page.locator( 'h1' ) ),
		'la synthèse du jour est dans le HTML rendu par PHP'
	);
	assert( html.includes( 'ardoise__fraicheur' ), 'l’indicateur de fraîcheur est présent sans JS', 'ardoise__fraicheur', 'absent' );
	egal( 2, await page.locator( 'a.lien-evitement' ).count(), 'les deux liens d’évitement sont présents' );

	// Chaque massif du référentiel figure dans le tableau, avec son niveau en
	// toutes lettres : c'est l'équivalent textuel exigé par le §5.3.
	const lignes = await page.locator( '#liste tbody tr' ).count();
	egal( total, lignes, 'une ligne par massif du référentiel' );

	// `allTextContents()` et NON `allInnerTexts()` : depuis la chaîne #22,
	// `.statut__libelle` porte `text-transform: uppercase` et `innerText` rend le
	// texte TEL QUE PEINT — donc en capitales. Comparer sur `innerText`
	// éprouverait le CSS en croyant éprouver la chaîne servie par PHP, alors que
	// le contrat impose exactement l'inverse : casse normale dans le HTML,
	// capitales produites par la feuille. Même raison que `texteSource()` plus
	// haut.
	const libelles = await page.locator( '#liste tbody .liste-statuts__cellule--niveau .statut__libelle' ).allTextContents();
	egal( total, libelles.length, 'chaque massif porte un libellé de niveau en toutes lettres' );
	const distincts = [ ...new Set( libelles.map( ( l ) => l.trim() ) ) ].sort();
	egal( [ 'Accès au massif autorisé', 'Accès au massif interdit' ], distincts, 'les libellés rendus sont ceux de la légende officielle' );
	egal(
		autorises,
		libelles.filter( ( l ) => l.trim() === 'Accès au massif autorisé' ).length,
		'le nombre de massifs autorisés rendu correspond à la donnée écrite en base'
	);

	// Le pendant de la correction ci-dessus : les capitales DOIVENT venir du CSS,
	// jamais du HTML. Sans ce contrôle, un gabarit qui écrirait les libellés en
	// capitales en dur passerait la ligne précédente à l'envers.
	const peints = await page.locator( '#liste tbody .liste-statuts__cellule--niveau .statut__libelle' ).allInnerTexts();
	egal(
		[ 'ACCÈS AU MASSIF AUTORISÉ', 'ACCÈS AU MASSIF INTERDIT' ],
		[ ...new Set( peints.map( ( l ) => l.trim() ) ) ].sort(),
		'les capitales sont produites par la feuille, pas écrites dans le HTML'
	);

	assert( ( await page.locator( '#legende' ).count() ) === 1, 'la légende est rendue côté serveur', 1, await page.locator( '#legende' ).count() );
	assert(
		( await page.locator( '.bandeau-non-officialite' ).count() ) === 1,
		'le bandeau de non-officialité est rendu côté serveur',
		1,
		await page.locator( '.bandeau-non-officialite' ).count()
	);

	// L'accueil porte désormais deux scripts (Leaflet vendorisé + carte.js), et
	// c'est conforme : la carte est un ENRICHISSEMENT. L'assertion « zéro script »
	// disait la bonne chose au mauvais endroit — elle mesurait l'absence d'un
	// moyen au lieu de la présence de l'information. Toutes les assertions
	// ci-dessus ont été jouées dans un contexte où JavaScript est COUPÉ : elles
	// sont, elles, la preuve directe de la contrainte n° 3. Ce qui reste à
	// affirmer sur les scripts, c'est qu'ils ne peuvent pas être un point d'entrée
	// tiers, et qu'ils ne bloquent pas le rendu.
	const scripts = await page.evaluate( () =>
		[ ...document.querySelectorAll( 'script[src]' ) ].map( ( s ) => ( {
			src: s.src,
			differe: s.defer || s.async,
		} ) )
	);
	note( `scripts de l’accueil : ${ scripts.map( ( s ) => s.src.replace( /^https?:\/\/[^/]+/, '' ) ).join( ', ' ) || '(aucun)' }` );
	egal(
		[],
		scripts.filter( ( s ) => new URL( s.src ).origin !== ORIGINE ).map( ( s ) => s.src ),
		'tout script de l’accueil est servi depuis notre origine'
	);
	egal(
		[],
		scripts.filter( ( s ) => ! s.differe ).map( ( s ) => s.src ),
		'aucun script ne bloque l’analyse du document (defer ou async)'
	);

	// §5.5 du brief, en entier : sans JavaScript, la carte est remplacée par une
	// image statique du département renvoyant à la liste textuelle. Le repli est
	// rendu PAR DÉFAUT par PHP, jamais dans un <noscript> (contrat #9, I-9.1) — la
	// preuve est qu'il est ici, dans un contexte sans JavaScript, ET qu'aucune
	// balise <noscript> ne l'enveloppe.
	egal( 0, await page.locator( 'noscript' ).count(), 'contrat #9 I-9.1 : le repli n’est pas enfermé dans un <noscript>' );

	const repli = page.locator( '#carte .carte-secours' );
	egal( 1, await repli.count(), '§5.5 : le repli statique de la carte est rendu par PHP' );

	// Contrat #9, F-3 : UNE seule attribution du fond, donc un seul repli. Depuis
	// que `massifs_partie( 'carte-secours' )` est appelée par `front-page.php` et
	// non plus par la dernière ligne de `parts/carte.php`, une seconde inclusion
	// laissée par mégarde dans le gabarit de carte dupliquerait silencieusement le
	// crédit OpenStreetMap sur le chemin NOMINAL — le seul où les deux appels
	// s'exécuteraient tous les deux. Les comptes n'étaient affirmés que sur les
	// chemins d'échec (scénarios 24 et 25) ; ils le sont maintenant ici aussi.
	egal( 1, await page.locator( '.carte-secours__repli' ).count(), 'contrat #9 F-3 : le repli statique est rendu une fois et une seule sur le chemin nominal' );
	egal( 1, await page.locator( '.carte-secours__attribution' ).count(), 'contrat #9 F-3 : l’attribution OSM n’est pas dupliquée sur le chemin nominal' );

	// Contrat #9, F-5 et invariant I-9.6 : le repli est le FRÈRE de la racine
	// `.carte`, jamais son descendant, et il vient APRÈS elle. Les deux moitiés
	// comptent. Frère : un `racine.remove()` sur un chemin d'échec de `carte.js`
	// n'emporterait pas le repli avec lui. Après : une fois `.carte-secours__repli`
	// retiré par un montage réussi, l'attribution reste dans le flux SOUS la carte
	// visible, ce qui tient I-9.6 et D-24 sans aucune règle CSS. Déplacer l'appel
	// AVANT `massifs_partie( 'carte' )` satisferait toujours « sans condition »
	// tout en cassant cet arbitrage sans qu'aucune autre assertion ne bronche —
	// d'où ce contrôle direct de l'ordre du document.
	const place = await page.evaluate( () => {
		const carte = document.querySelector( '#carte .carte' );
		const secours = document.querySelector( '#carte .carte-secours' );
		if ( ! carte || ! secours ) {
			return { carte: !! carte, secours: !! secours };
		}
		return {
			carte: true,
			secours: true,
			memeParent: carte.parentElement === secours.parentElement,
			descendant: carte.contains( secours ),
			// Node.DOCUMENT_POSITION_FOLLOWING === 4 : `secours` suit `carte`.
			apres: !! ( carte.compareDocumentPosition( secours ) & 4 ),
		};
	} );
	egal(
		{ carte: true, secours: true, memeParent: true, descendant: false, apres: true },
		place,
		'contrat #9 F-5 / I-9.6 : le repli est le frère de `.carte`, hors d’elle, et APRÈS elle dans le flux'
	);

	const image = page.locator( '#carte .carte-secours__image' );
	egal( 1, await image.count(), '§5.5 : l’image statique du département est présente' );
	egal( ORIGINE, new URL( await image.getAttribute( 'src' ) ).origin, 'l’image de repli est servie depuis notre origine' );
	egal( '', await image.getAttribute( 'alt' ), 'alt="" : l’information exploitable est portée par la liste adjacente, pas par une description inventée' );
	// `width` et `height` réservent la boîte : sans eux, la page saute quand
	// l'image arrive (§10 du brief).
	assert(
		Number( await image.getAttribute( 'width' ) ) > 0 && Number( await image.getAttribute( 'height' ) ) > 0,
		'l’image de repli porte ses dimensions intrinsèques (aucun saut de mise en page)',
		'width > 0 et height > 0',
		`${ await image.getAttribute( 'width' ) } × ${ await image.getAttribute( 'height' ) }`
	);
	// L'image est réellement chargée, pas seulement déclarée : un `src` mort
	// passerait toutes les assertions d'attribut.
	const imageChargee = await image.evaluate( ( i ) => ( { w: i.naturalWidth, h: i.naturalHeight } ) );
	assert(
		imageChargee.w > 0 && imageChargee.h > 0,
		'l’image de repli est réellement décodée par le navigateur',
		'naturalWidth/Height > 0',
		JSON.stringify( imageChargee )
	);

	// Le chemin d'accès à l'équivalent textuel, et sa cible.
	const lienListe = page.locator( '#carte .carte-secours__lien' );
	egal( 1, await lienListe.count(), '§5.5 : le repli porte le lien vers la liste textuelle' );
	egal( '#liste', await lienListe.getAttribute( 'href' ), 'le lien du repli vise l’ancre de la liste' );
	egal( 'Aller à la liste des statuts', await texteSource( lienListe ), 'libellé du lien, repris du §5.3 du brief' );
	egal( 1, await page.locator( '[id="liste"]' ).count(), 'l’ancre visée par le repli existe une fois et une seule' );

	// Contrat #9, I-9.4 : l'image et son attribution n'existent que l'une avec
	// l'autre. Afficher un rendu ODbL sans attribution est une violation de
	// licence ; créditer une source dont rien n'est affiché est une affirmation
	// fausse. On affirme donc la conjonction, pas chacune de son côté.
	const attribution = page.locator( '#carte .carte-secours__attribution' );
	egal( 1, await attribution.count(), 'contrat #9 I-9.4 : l’attribution du fond accompagne l’image' );
	// U+0027 et non U+2019 : c'est la chaîne du §9 du brief, servie par
	// `massifs_attribution_fond_de_carte()['phrase']`. Toute « uniformisation
	// typographique » de cette chaîne est un défaut, pas une variante.
	egal( "© les contributeurs d'OpenStreetMap", await texteSource( attribution ), '§9 du brief : la phrase d’attribution OSM, verbatim' );
	egal(
		'https://www.openstreetmap.org/copyright',
		await page.locator( '#carte .carte-secours__attribution-lien' ).getAttribute( 'href' ),
		'§9 du brief : le lien de licence OSM'
	);

	// Contrat #9, I-9.3 : le repli ne porte JAMAIS les statuts du jour. Il dit OÙ,
	// la liste dit QUOI. La règle est tenue par l'absence de couplage
	// (`carte-secours.php` n'appelle aucune fonction de statut) — ce qui s'observe
	// ici par l'absence de tout libellé de niveau dans la partie.
	const texteRepli = await texteSource( repli );
	egal(
		[],
		[ 'Accès au massif autorisé', 'Accès au massif interdit', 'information non disponible' ].filter( ( l ) => texteRepli.includes( l ) ),
		'contrat #9 I-9.3 : le repli ne porte aucun statut — il ne peut donc pas en périmer un'
	);

	await contexte.close();
}

async function s03_structureEtAncres( navigateur ) {
	scenario( '03 — un seul h1, aucun id en double, ancres d’évitement résolues' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext();
	const titres = [];

	for ( const cible of PAGES ) {
		const { page, statut } = await charger( contexte, cible.chemin );

		// Le code HTTP est relevé ET affirmé : si `/hello-world/` répondait 404, la
		// page serait servie par `404.php` et « un seul h1 » passerait pour la
		// mauvaise raison — un vert obtenu ainsi est un faux vert.
		egal( cible.chemin === '/?p=99999999' ? 404 : 200, statut, `${ cible.nom } : code HTTP attendu` );

		titres.push( { nom: cible.nom, titre: await page.title() } );

		const structure = await page.evaluate( () => {
			const ids = [ ...document.querySelectorAll( '[id]' ) ].map( ( e ) => e.id );
			const doublons = ids.filter( ( id, i ) => ids.indexOf( id ) !== i );
			const ancresMortes = [ ...document.querySelectorAll( 'a[href^="#"]' ) ]
				.map( ( a ) => a.getAttribute( 'href' ) )
				.filter( ( h ) => h && h !== '#' && ! document.querySelector( `[id="${ h.slice( 1 ) }"]` ) );
			// Un h1 porteur de `role="presentation"` (ou masqué) n'est pas un
			// titre pour l'utilisateur d'un lecteur d'écran : on compte les
			// titres RÉELLEMENT exposés, pas les balises.
			const exposes = [ ...document.querySelectorAll( 'h1' ) ].filter( ( e ) => {
				const role = e.getAttribute( 'role' );
				return role !== 'presentation' && role !== 'none' && e.getAttribute( 'aria-hidden' ) !== 'true';
			} );
			return {
				h1: exposes.length,
				h1Balises: document.querySelectorAll( 'h1' ).length,
				h2: document.querySelectorAll( 'h2' ).length,
				doublons: [ ...new Set( doublons ) ],
				ancresMortes,
				lang: document.documentElement.lang,
			};
		} );

		egal( 1, structure.h1, `${ cible.nom } : exactement un h1 exposé` );
		if ( structure.h1Balises !== structure.h1 ) {
			note( `${ cible.nom } : ${ structure.h1Balises } balises h1 dont ${ structure.h1Balises - structure.h1 } neutralisée(s) par role="presentation"` );
		}
		egal( [], structure.doublons, `${ cible.nom } : aucun id en double` );
		egal( [], structure.ancresMortes, `${ cible.nom } : aucun lien d’évitement vers une ancre inexistante` );
		assert( structure.lang.startsWith( 'fr' ), `${ cible.nom } : lang français`, 'fr…', structure.lang );

		if ( cible.statuts ) {
			// CINQ h2 depuis le lot de l'Épic 4. Trois viennent de la chaîne #7 :
			// légende, liste, et le titre du panneau de massif de la carte — ce
			// dernier est le nom accessible de l'`<aside aria-labelledby>` exigé par
			// le contrat #7 §9 ; il est vide et masqué tant qu'aucun massif n'est
			// sélectionné, et rempli par le JS. Les deux autres sont les titres des
			// bandes neuves : « Danger météo du jour » (contrat #10) et « Zones
			// parcourues par le feu » (contrat #11 §3, A-12), tous deux imposés par
			// leur contrat et rendus en toutes circonstances, y compris quand la
			// donnée manque.
			//
			// Le compte reste une ÉGALITÉ EXACTE, jamais un « au moins » : c'est ce
			// qui détecte un titre dupliqué par une inclusion accidentelle. Et les
			// textes sont affirmés, sans quoi le seul compte laisserait passer une
			// bande rendue à la place d'une autre.
			egal( 5, structure.h2, 'accueil : cinq h2 (légende, liste, panneau de carte, météo, zones parcourues) — aucun doublon de titre' );
			const textesH2 = await page.evaluate( () =>
				[ ...document.querySelectorAll( 'h2' ) ].map( ( e ) => e.textContent.replace( /\s+/g, ' ' ).trim() )
			);
			note( `h2 servis sur l’accueil : ${ textesH2.map( ( t ) => `« ${ t } »` ).join( ' · ' ) }` );
			assert(
				textesH2.includes( 'Danger météo du jour' ),
				'accueil : le h2 de la bande météo est rendu par PHP (contrat #10)',
				'Danger météo du jour',
				textesH2.join( ' · ' )
			);
			assert(
				textesH2.includes( 'Zones parcourues par le feu' ),
				'accueil : le h2 de la bande des zones parcourues est rendu par PHP (contrat #11)',
				'Zones parcourues par le feu',
				textesH2.join( ' · ' )
			);
			const cible2 = await page.evaluate( () => {
				const e = document.getElementById( 'liste' );
				return e ? { tag: e.tagName, tabindex: e.getAttribute( 'tabindex' ), nom: e.getAttribute( 'aria-labelledby' ) } : null;
			} );
			egal( { tag: 'SECTION', tabindex: '-1', nom: 'liste-titre' }, cible2, 'l’ancre #liste est une région nommée et focusable au saut' );
		}

		// Focus visible : le premier lien d'évitement reçoit le focus et doit
		// porter un anneau. `outline: none` sans relais est un défaut bloquant.
		const focus = await page.evaluate( () => {
			const a = document.querySelector( 'a' );
			if ( ! a ) {
				return null;
			}
			a.focus();
			const s = getComputedStyle( a );
			return { outlineStyle: s.outlineStyle, outlineWidth: s.outlineWidth, boxShadow: s.boxShadow };
		} );
		if ( focus ) {
			const visible =
				( focus.outlineStyle !== 'none' && parseFloat( focus.outlineWidth ) > 0 ) ||
				( focus.boxShadow && focus.boxShadow !== 'none' );
			assert( visible, `${ cible.nom } : le focus du premier lien est visible`, 'anneau ou ombre', JSON.stringify( focus ) );
		}

		await page.close();
	}

	// « Titres de page uniques » (brief §8) : le <title> est produit par
	// `wp_get_document_title()` dans templates/header.php, donc par le mécanisme
	// standard — mais rien ne le prouvait tant que le repli hors accueil
	// imprimait `bloginfo( 'name' )` sur toutes ses pages. On mesure les titres
	// réellement servis, jamais le code qui les compose.
	note( `titres servis : ${ titres.map( ( t ) => `${ t.nom } → « ${ t.titre } »` ).join( ' · ' ) }` );
	egal( [], titres.filter( ( t ) => t.titre.trim() === '' ).map( ( t ) => t.nom ), 'aucune page servie sans <title>' );
	egal(
		titres.length,
		new Set( titres.map( ( t ) => t.titre ) ).size,
		'chaque page porte un <title> distinct des autres'
	);

	await contexte.close();
}

async function s04_statutPerimeJamaisCourant( navigateur ) {
	scenario( '04 — un statut périmé n’est jamais présenté comme courant (§4.2)' );
	poserEtat( 'veille-seule' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const h1 = await texteSource( page.locator( 'h1' ) );
	assert(
		h1.startsWith( 'Information du jour non disponible.' ),
		'zone carte : « information non disponible » à la place du statut',
		'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
		h1
	);
	egal(
		1,
		await page.locator( 'h1 a[href*="risque-prevention-incendie"]' ).count(),
		'zone carte : le lien vers la carte officielle est présent'
	);

	const alerte = page.locator( '#liste .bandeau-alerte--indisponible' );
	egal( 1, await alerte.count(), 'liste : l’état vide « indisponible » est rendu' );
	assert(
		( await texteSource( alerte ) ).includes( 'Information du jour non disponible' ),
		'liste : « information non disponible » à la place du tableau',
		'Information du jour non disponible…',
		await texteSource( alerte )
	);
	egal( 1, await page.locator( '#liste a[href*="risque-prevention-incendie"]' ).count(), 'liste : le lien vers la carte officielle est présent' );

	egal( 0, await page.locator( '#liste table' ).count(), 'liste : aucun tableau de statuts n’est rendu' );
	egal( 0, await page.locator( '#liste .statut__libelle' ).count(), 'liste : aucun libellé de niveau de la veille n’est rendu' );
	egal( 0, await page.locator( '.ardoise__chiffre' ).count(), 'zone carte : aucun chiffre du jour n’est rendu' );

	// La donnée de la veille EXISTE en base (20 autorisés / 25 écrits pour hier) :
	// le scénario ne vaut que parce qu'elle est là. Elle ne doit apparaître nulle
	// part sur la page du jour, hors de la légende — qui, elle, énumère les
	// niveaux possibles sans jamais les attribuer à un massif.
	//
	// Deux mesures, et il en faut deux depuis la chaîne #7.
	//
	// (a) Ce qui est PRÉSENTÉ. Le panneau de la carte porte désormais un gabarit
	//     de libellés pré-rendus par PHP (« information non disponible »,
	//     « dispositif estival inactif », la phrase de non-publication), tous
	//     enfermés dans des conteneurs `hidden` que le JS démasque un par un. Rien
	//     de tout cela n'est présenté au visiteur, et rien de tout cela n'est une
	//     donnée : ce sont les quatre états possibles, écrits d'avance. Ce qui doit
	//     rester vide, c'est ce qui est réellement RENDU hors de la légende.
	const fuites = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__libelle' ) ]
			.filter( ( e ) => ! e.closest( '#legende' ) )
			.filter( ( e ) => e.offsetParent !== null || e.getClientRects().length > 0 )
			.map( ( e ) => e.textContent.trim() )
	);
	egal( [], fuites, 'aucun libellé de statut PRÉSENTÉ hors de la légende : rien de la veille ne fuit' );

	// (b) La mesure qui, elle, ne connaît aucune exception : AUCUN libellé de
	//     NIVEAU de la veille n'existe où que ce soit dans le document — visible
	//     ou non, y compris dans l'îlot JSON de la carte, que le JS lirait. C'est
	//     la règle absolue du §4.2, et elle porte sur les octets servis, pas sur
	//     le rendu. La journée d'hier a bien été écrite (20 autorisés sur 25) :
	//     l'assertion ne vaut que parce que la donnée existe en base.
	const fuitesCachees = await page.evaluate( () =>
		[ ...document.querySelectorAll( '*' ) ]
			.filter( ( e ) => ! e.closest( '#legende' ) && e.children.length === 0 )
			.map( ( e ) => e.textContent.trim() )
			.filter( ( t ) => t.includes( 'Accès au massif' ) || t.includes( 'Accès à la ZAPEF' ) )
	);
	egal( [], fuitesCachees, '§4.2 : aucun libellé de NIVEAU de la veille dans le document, masqué compris' );

	// L'îlot JSON de la carte est lu par le JS : un niveau d'hier qui y survivrait
	// serait peint sur la carte à la seconde où le script démarre. On le décode et
	// l'on affirme sur la donnée, pas sur une sous-chaîne.
	const ilot = await page.evaluate( () => {
		const n = document.getElementById( 'carte-donnees' );
		return n ? JSON.parse( n.textContent ) : null;
	} );
	assert( ilot !== null, 'l’îlot de données de la carte est présent', 'un objet', 'absent' );
	if ( ilot ) {
		const jours = Object.keys( ilot.jours );
		egal( [ ilot.jour_courant, ilot.jour_suivant ], jours, 'contrat #7 §4 : l’îlot ne porte QUE le jour courant et le suivant — la veille en est absente' );
		const niveaux = jours.flatMap( ( j ) => Object.values( ilot.jours[ j ] ) ).filter( ( s ) => s.niveau !== null || s.zapef !== null );
		egal( [], niveaux, '§4.2 : aucun niveau de la veille ne voyage dans l’îlot de la carte' );
		const etats = [ ...new Set( jours.flatMap( ( j ) => Object.values( ilot.jours[ j ] ).map( ( s ) => s.etat ) ) ) ].sort();
		note( `états portés par l’îlot : ${ etats.join( ', ' ) }` );
		assert( ! etats.includes( 'disponible' ), 'aucun massif n’est « disponible » dans l’îlot le jour où la donnée manque', 'aucun disponible', etats.join( ', ' ) );
	}

	// La mention de péremption a QUITTÉ l'ardoise pour le bandeau dédié. Les deux
	// assertions ci-dessous sont indissociables : la première prouve qu'elle est
	// toujours rendue, la seconde qu'elle ne l'est plus deux fois. Supprimer l'une
	// des deux rouvrirait exactement le défaut que cette jonction referme.
	egal( 1, await page.locator( '.bandeau-alerte--peremption' ).count(), 'la mention de péremption est rendue une fois, par le bandeau dédié' );
	egal( 0, await page.locator( '.ardoise__peremption' ).count(), 'elle n’est plus rendue une seconde fois par l’ardoise' );

	await contexte.close();
}

async function s05_bandeauNonOfficialite( navigateur ) {
	scenario( '05 — bandeau de non-officialité sur toute page affichant un statut (§5.6)' );

	const contexte = await navigateur.newContext();
	for ( const mode of [ 'jour-nominal', 'veille-seule', 'absente' ] ) {
		poserEtat( mode );
		const page = await contexte.newPage();
		await page.goto( BASE + '/', { waitUntil: 'load' } );

		egal( 1, await page.locator( '.bandeau-non-officialite' ).count(), `${ mode } : bandeau présent une fois et une seule` );
		const texte = await texteSource( page.locator( '.bandeau-non-officialite' ) );
		assert(
			texte.startsWith( 'Site d\'information indépendant. Seules les publications de la préfecture des Bouches-du-Rhône font foi' ),
			`${ mode } : la mention est celle du §5.6, mot pour mot`,
			'Site d\'information indépendant. Seules les publications…',
			texte
		);
		egal(
			1,
			await page.locator( '.bandeau-non-officialite a[href*="risque-prevention-incendie"]' ).count(),
			`${ mode } : le bandeau porte le lien vers la carte officielle`
		);
		await page.close();
	}
	await contexte.close();
}

async function s06_jamaisLaCouleurSeule( navigateur ) {
	scenario( '06 — aucune information portée par la couleur seule (§8)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	// Portée de la mesure : ce que le visiteur voit RÉELLEMENT.
	//
	// Depuis la chaîne #7, le panneau de la carte porte un gabarit de marques
	// pré-rendu par PHP — une pastille et un jalon SANS classe d'état et SANS
	// libellé — que `carte.js` renseigne à la sélection d'un massif. Ces deux
	// nœuds sont enfermés dans des conteneurs `hidden` et ne sont donc jamais
	// présentés : les compter comme « marques colorées sans libellé » ferait dire
	// à ce scénario le contraire de ce qu'il éprouve. On mesure les marques
	// rendues, et l'on affirme séparément — plus bas — que les gabarits sont bien
	// masqués ET bien vides, ce qui est la seule forme sous laquelle ils sont
	// acceptables.
	const releve = await page.evaluate( () => {
		const rendu = ( e ) => e.getClientRects().length > 0;
		const marques = [ ...document.querySelectorAll( '.statut__marque' ) ].filter( rendu );
		const libelles = [ ...document.querySelectorAll( '.statut__libelle' ) ].filter( rendu );
		const orphelines = marques.filter( ( m ) => {
			const suivant = m.nextElementSibling;
			return ! suivant || ! suivant.classList.contains( 'statut__libelle' ) || suivant.textContent.trim() === '';
		} );
		return {
			marques: marques.length,
			libelles: libelles.length,
			orphelines: orphelines.map( ( m ) => m.className ),
			texteDansMarque: marques.filter( ( m ) => m.textContent.trim() !== '' ).length,
			nonMasquees: marques.filter( ( m ) => m.getAttribute( 'aria-hidden' ) !== 'true' ).length,
			libellesVides: libelles.filter( ( l ) => l.textContent.trim() === '' ).length,
			// L'aplat est-il réellement peint ? Un fond transparent signifie que
			// la marque n'existe visuellement pas.
			sansAplat: marques.filter( ( m ) => {
				const f = getComputedStyle( m ).backgroundColor;
				return f === 'rgba(0, 0, 0, 0)' || f === 'transparent';
			} ).length,
		};
	} );

	egal( releve.marques, releve.libelles, 'autant de libellés que de marques colorées' );
	egal( [], releve.orphelines, 'aucune marque colorée sans libellé adjacent en toutes lettres' );
	egal( 0, releve.texteDansMarque, 'aucun texte à l’intérieur d’une marque colorée' );
	egal( 0, releve.nonMasquees, 'chaque marque est aria-hidden (le libellé porte seul l’information)' );
	egal( 0, releve.libellesVides, 'aucun libellé vide' );
	note( `marques mesurées : ${ releve.marques } · libellés : ${ releve.libelles }` );
	// Depuis la chaîne #22 la couche visuelle existe : une marque transparente
	// n'est plus un manque annoncé, c'est une régression. Le détail par état est
	// dans le scénario « couche-statut ».
	egal( 0, releve.sansAplat, 'chaque marque colorée est réellement peinte' );

	// Le pendant de la restriction de portée : les gabarits du panneau de carte
	// n'échappent à la mesure QUE parce qu'ils sont masqués et vides. Si l'un
	// d'eux devenait visible sans libellé, ou portait un aplat d'état sans mot,
	// ce serait exactement l'information portée par la couleur seule que le §8
	// interdit — et ce contrôle rougirait.
	const gabarits = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.carte__panneau-etat .statut__marque, .carte__panneau-zapef .statut__marque' ) ].map( ( m ) => ( {
			classes: m.className,
			rendu: m.getClientRects().length > 0,
			libelle: ( m.nextElementSibling?.textContent ?? '(aucun)' ).trim(),
		} ) )
	);
	note( `gabarits de marque du panneau de carte : ${ JSON.stringify( gabarits ) }` );
	egal(
		[],
		gabarits.filter( ( g ) => g.rendu ),
		'les gabarits de marque du panneau de carte ne sont jamais présentés sans JavaScript'
	);
	egal(
		[],
		gabarits.filter( ( g ) => g.libelle !== '' || /--/.test( g.classes ) ),
		'les gabarits de marque ne portent NI libellé écrit d’avance NI classe d’état : le serveur ne préjuge d’aucun statut'
	);

	await contexte.close();
}

async function s07_mobile360 ( navigateur ) {
	scenario( '07 — mobile 360 px : aucun défilement horizontal (§8)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { viewport: { width: 360, height: 780 }, javaScriptEnabled: false } );

	for ( const cible of PAGES ) {
		const page = await contexte.newPage();
		await page.goto( BASE + cible.chemin, { waitUntil: 'load' } );
		const mesure = await page.evaluate( () => ( {
			scroll: document.documentElement.scrollWidth,
			fenetre: document.documentElement.clientWidth,
			debordants: [ ...document.querySelectorAll( 'body *' ) ]
				.filter( ( e ) => e.getBoundingClientRect().right > document.documentElement.clientWidth + 1 )
				.slice( 0, 5 )
				.map( ( e ) => `${ e.tagName.toLowerCase() }.${ e.className || '(sans classe)' }` ),
		} ) );
		assert(
			mesure.scroll <= mesure.fenetre + 1,
			`${ cible.nom } : pas de débordement horizontal à 360 px`,
			`scrollWidth ≤ ${ mesure.fenetre }`,
			`scrollWidth = ${ mesure.scroll } · premiers débordants : ${ mesure.debordants.join( ', ' ) || '(aucun)' }`
		);

		// Ce contexte a JavaScript COUPÉ : le titre de premier niveau qu'on lit ici
		// est celui que PHP a rendu, sur les cinq pages et non sur la seule
		// accueil. C'est la contrainte n° 3 vérifiée hors de la page d'accueil —
		// le repli `index.php` / `page.php` / `404.php` n'a aucun script pour
		// rattraper un titre manquant.
		const titre = await page.evaluate( () => {
			const h = document.querySelector( 'h1' );
			return { texte: h ? h.textContent.replace( /\s+/g, ' ' ).trim() : null, scripts: document.querySelectorAll( 'script[src]' ).length };
		} );
		assert(
			titre.texte !== null && titre.texte !== '',
			`${ cible.nom } : sans JavaScript, le h1 est déjà dans le HTML servi`,
			'un titre non vide',
			titre.texte === null ? '(aucun h1)' : '(h1 vide)'
		);
		await page.close();
	}

	await contexte.close();

	// 320 px : le plancher du brief §8 (« pas de défilement horizontal à 320 px »),
	// qui est aussi la largeur équivalente à un zoom de 200 % sur un écran de
	// 640 px. C'est une mesure de largeur de viewport, pas un `zoom` CSS simulé.
	const etroit = await navigateur.newContext( { viewport: { width: 320, height: 780 }, javaScriptEnabled: false } );
	const page320 = await etroit.newPage();
	await page320.goto( BASE + '/', { waitUntil: 'load' } );
	const mesure320 = await page320.evaluate( () => ( {
		scroll: document.documentElement.scrollWidth,
		fenetre: document.documentElement.clientWidth,
		debordants: [ ...document.querySelectorAll( 'body *' ) ]
			.filter( ( e ) => e.getBoundingClientRect().right > document.documentElement.clientWidth + 1 )
			.slice( 0, 5 )
			.map( ( e ) => `${ e.tagName.toLowerCase() }.${ e.className || '(sans classe)' }` ),
	} ) );
	assert(
		mesure320.scroll <= mesure320.fenetre + 1,
		'accueil : pas de débordement horizontal à 320 px',
		`scrollWidth ≤ ${ mesure320.fenetre }`,
		`scrollWidth = ${ mesure320.scroll } · premiers débordants : ${ mesure320.debordants.join( ', ' ) || '(aucun)' }`
	);
	await etroit.close();

	// Zoom texte 200 % : la taille de police de base est doublée, la mise en page
	// doit tenir sans défilement horizontal.
	const zoom = await navigateur.newContext( { viewport: { width: 360, height: 780 }, javaScriptEnabled: false } );
	const pageZoom = await zoom.newPage();
	await pageZoom.emulateMedia( {} );
	await pageZoom.addInitScript( () => {
		document.addEventListener( 'DOMContentLoaded', () => {
			document.documentElement.style.fontSize = '32px';
		} );
	} );
	await pageZoom.goto( BASE + '/', { waitUntil: 'load' } );
	const debordeZoom = await pageZoom.evaluate( () => ( {
		scroll: document.documentElement.scrollWidth,
		fenetre: document.documentElement.clientWidth,
	} ) );
	assert(
		debordeZoom.scroll <= debordeZoom.fenetre + 1,
		'accueil : zoom texte 200 % à 360 px, pas de débordement horizontal',
		`scrollWidth ≤ ${ debordeZoom.fenetre }`,
		`scrollWidth = ${ debordeZoom.scroll }`
	);
	await zoom.close();
}

async function s08_accessibiliteAutomatisee( navigateur ) {
	scenario( '08 — vérification d’accessibilité automatisée, sans erreur bloquante (§8)' );
	poserEtat( 'jour-nominal' );

	const axe = resoudre( 'axe-core' );
	const contexte = await navigateur.newContext();

	for ( const cible of PAGES ) {
		const page = await contexte.newPage();
		await page.goto( BASE + cible.chemin, { waitUntil: 'load' } );
		await page.addScriptTag( { path: axe } );
		const resultat = await page.evaluate( async () => {
			// eslint-disable-next-line no-undef
			return await axe.run( document, { resultTypes: [ 'violations' ] } );
		} );

		const bloquantes = resultat.violations.filter( ( v ) => v.impact === 'critical' || v.impact === 'serious' );
		const mineures = resultat.violations.filter( ( v ) => v.impact !== 'critical' && v.impact !== 'serious' );

		egal(
			[],
			bloquantes.map( ( v ) => `${ v.id } (${ v.impact }, ${ v.nodes.length } nœuds) : ${ v.nodes[ 0 ]?.target?.join( ' ' ) }` ),
			`${ cible.nom } : aucune violation axe bloquante (serious/critical)`
		);
		if ( mineures.length ) {
			note( `${ cible.nom } : violations mineures — ${ mineures.map( ( v ) => `${ v.id } (${ v.impact })` ).join( ', ' ) }` );
		}

		// `page-has-heading-one` est classée `moderate` par axe : elle ne serait
		// donc PAS attrapée par le filtre bloquant ci-dessus, alors que « une seule
		// h1 par page » est une exigence explicite du brief §8. On l'affirme
		// séparément, quel que soit son impact.
		egal(
			[],
			resultat.violations.filter( ( v ) => v.id === 'page-has-heading-one' ).map( ( v ) => `${ v.impact } — ${ v.nodes.length } nœud(s)` ),
			`${ cible.nom } : la règle axe « page-has-heading-one » passe`
		);
		await page.close();
	}

	await contexte.close();
}

async function s09_budgets( navigateur ) {
	scenario( '09 — budgets de performance (§10)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext();
	const { page, tailles } = await charger( contexte, '/' );

	const estPolice = ( t ) => t.type === 'font' || /\.woff2?($|\?)/.test( t.url );
	const estGeometrie = ( t ) => /geometrie|\.geojson/.test( t.url );
	// « HORS FOND DE CARTE » est écrit dans le §10 du brief, et le fond de carte a
	// deux artefacts depuis la chaîne #9 : la pyramide de tuiles servie à Leaflet,
	// et l'image statique du repli sans JavaScript — qui EST le fond de carte,
	// rendu en un seul fichier. Les compter dans l'enveloppe des 250 Ko ferait dire
	// au budget le contraire de ce qu'il énonce. Ils ne sortent pas de la mesure
	// pour autant : ils sont pesés à part, contre leurs propres plafonds.
	const estFondDeCarte = ( t ) => /\/data\/tuiles\/.+\.png/.test( t.url ) || /carte-statique\.png/.test( t.url );
	const enveloppe = tailles.filter( ( t ) => ! estPolice( t ) && ! estGeometrie( t ) && ! estFondDeCarte( t ) );
	const octetsEnveloppe = enveloppe.reduce( ( s, t ) => s + t.octets, 0 );

	for ( const t of enveloppe ) {
		note( `${ t.type.padEnd( 10 ) } ${ String( t.octets ).padStart( 7 ) } o  ${ t.url.replace( BASE, '' ) }` );
	}
	assert(
		octetsEnveloppe < 250 * 1024,
		`accueil : HTML + CSS + JS transférés sous 250 Ko (mesuré ${ octetsEnveloppe } o)`,
		'< 256000 o',
		`${ octetsEnveloppe } o`
	);

	// Fond de carte, pesé à part et affirmé contre ses propres plafonds.
	const tuiles = tailles.filter( ( t ) => /\/data\/tuiles\/.+\.png/.test( t.url ) );
	const octetsTuiles = tuiles.reduce( ( s, t ) => s + t.octets, 0 );
	note( `fond de carte, hors enveloppe §10 : ${ tuiles.length } tuiles = ${ octetsTuiles } o` );

	const statique = tailles.filter( ( t ) => /carte-statique\.png/.test( t.url ) );
	const octetsStatique = statique.reduce( ( s, t ) => s + t.octets, 0 );
	// Plafond fixé par le contrat #9 §2 pour l'image de repli : 150 Ko transférés.
	assert(
		octetsStatique > 0 && octetsStatique < 150 * 1024,
		`repli statique du fond de carte sous 150 Ko (contrat #9 §2, mesuré ${ octetsStatique } o)`,
		'0 < octets < 153600',
		`${ octetsStatique } o`
	);
	egal( 1, statique.length, 'contrat #9 F-9 : une seule image statique est demandée — aucun srcset, aucun second artefact' );

	const polices = tailles.filter( estPolice );
	const fichiersPolice = new Set( polices.map( ( t ) => t.url.split( '?' )[ 0 ] ) );
	assert( fichiersPolice.size <= 2, `au plus 2 fichiers de police (mesuré ${ fichiersPolice.size })`, '≤ 2', [ ...fichiersPolice ] );
	egal(
		[],
		[ ...fichiersPolice ].filter( ( u ) => new URL( u ).origin !== ORIGINE ),
		'toutes les polices sont servies depuis notre domaine'
	);
	// Un préchargement sans `crossorigin` fait télécharger deux fois la police.
	for ( const fichier of fichiersPolice ) {
		const n = polices.filter( ( t ) => t.url.split( '?' )[ 0 ] === fichier ).length;
		egal( 1, n, `${ fichier.split( '/' ).pop() } : téléchargée une seule fois (preload + @font-face)` );
	}
	const preloads = await page.locator( 'link[rel="preload"][as="font"]' ).all();
	egal( 2, preloads.length, 'les deux polices sont préchargées' );
	for ( const p of preloads ) {
		assert( ( await p.getAttribute( 'crossorigin' ) ) !== null, `préchargement avec crossorigin : ${ ( await p.getAttribute( 'href' ) ).split( '/' ).pop() }`, 'crossorigin', 'absent' );
	}
	note( `polices : ${ polices.map( ( t ) => `${ t.url.split( '/' ).pop() } ${ t.octets } o` ).join( ' · ' ) }` );

	const geo = await contexte.request.get( `${ BASE }/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json` );
	const octetsGeo = ( await geo.body() ).length;
	assert( octetsGeo < 300 * 1024, `géométrie sous 300 Ko (mesuré ${ octetsGeo } o)`, '< 307200 o', `${ octetsGeo } o` );

	await page.close();
	await contexte.close();
}

async function s10_apiPublique( navigateur ) {
	scenario( '10 — API publique en lecture, écriture refusée sans authentification (§5.4, §6)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext();
	const api = contexte.request;

	const racine = await api.get( `${ BASE }/wp-json/` );
	egal( 200, racine.status(), 'la racine REST répond sans authentification' );
	const index = await racine.json();
	const routesMassifs = Object.keys( index.routes ).filter( ( r ) => r.includes( 'massifs' ) );
	note( `routes REST du domaine massifs exposées : ${ routesMassifs.join( ', ' ) || '(aucune)' }` );
	assert(
		routesMassifs.length > 0,
		'une route publique de lecture des statuts du jour existe',
		'au moins une route /massifs/…',
		'aucune route massifs enregistrée'
	);

	// Écriture anonyme : refusée, sur toute route d'écriture du cœur. Les charges
	// utiles sont COMPLÈTES, sans quoi le cœur répondrait 400 (paramètre manquant)
	// avant même d'atteindre le contrôle de droits — et le scénario ne prouverait
	// rien du contrôle d'accès.
	const ecritures = [
		[ '/wp-json/wp/v2/posts', { title: 'recette', content: 'recette', status: 'publish' } ],
		[ '/wp-json/wp/v2/users', { username: 'recette', email: 'recette@massifs.local', password: 'Recette!2026#massifs' } ],
		[ '/wp-json/wp/v2/settings', { title: 'recette' } ],
	];
	for ( const [ route, charge ] of ecritures ) {
		const r = await api.post( BASE + route, { data: charge, failOnStatusCode: false } );
		assert( r.status() === 401 || r.status() === 403, `écriture anonyme refusée sur ${ route }`, '401 ou 403', `${ r.status() } — ${ ( await r.text() ).slice( 0, 120 ) }` );
	}
	const reglages = await api.get( `${ BASE }/wp-json/wp/v2/settings`, { failOnStatusCode: false } );
	assert( reglages.status() === 401 || reglages.status() === 403, 'lecture anonyme des réglages refusée', '401 ou 403', reglages.status() );

	// admin-post sans authentification ni nonce.
	const adminPost = await api.post( `${ BASE }/wp-admin/admin-post.php`, { data: { action: 'massifs_publier_statuts' }, failOnStatusCode: false } );
	note( `POST admin-post.php (action massifs_publier_statuts) → HTTP ${ adminPost.status() }` );

	// Issue #8 — le point d'accès public du §5.4, vu du navigateur et sans aucune
	// session. La forme complète et le bornage du paramètre `jour` sont éprouvés
	// en HTTP par `tests/scenarios/22-api-publique-statuts.php` ; ce qui est
	// affirmé ici, c'est ce qui n'a de sens que depuis un client web : la route
	// est atteignable anonymement, elle est réutilisable cross-origin (c'est
	// l'objet même du §5.4), et elle sert bien les 25 massifs.
	// Depuis l'issue #11 (contrat §2, arbitrage A-5), l'espace porte une seconde
	// route publique de LECTURE : la couche des zones parcourues par le feu.
	// L'égalité reste EXACTE — c'est elle qui détecterait l'apparition d'une route
	// d'écriture dans un espace qui n'en déclare aucune (§6 du brief, tenu par
	// construction et non par une garde).
	// ÉLARGIE PAR L'ÉPIC 5. Deux routes de portail entrent dans la surface, et pas
	// dans le même espace de noms : #14 publie `massifs-portail/v1/publication`,
	// #15 publie `massifs/v1/portail/historique` — divergence que le contrat #15
	// §4 déclare lui-même « à réconcilier en revue de lot ». La liste exacte est ce
	// qui la rend visible à chaque exécution ; le refus anonyme des deux, lui, est
	// éprouvé par le scénario `portail-anonyme`.
	egal(
		[
			'/massifs-portail/v1',
			'/massifs-portail/v1/publication',
			'/massifs/v1',
			'/massifs/v1/portail/historique',
			'/massifs/v1/statuts',
			'/massifs/v1/zones-parcourues-par-le-feu',
		],
		routesMassifs.sort(),
		'l’espace « massifs » expose exactement ses deux index, ses deux lectures publiques et ses deux routes de portail'
	);

	// Et la route neuve est bien en lecture seule, anonymement : elle répond en
	// `200` sans session, et aucune méthode d'écriture n'y est déclarée.
	const zones = await api.get( `${ BASE }/wp-json/massifs/v1/zones-parcourues-par-le-feu`, { failOnStatusCode: false } );
	egal( 200, zones.status(), 'contrat #11 §2 : la couche des zones est lisible en JSON, sans authentification' );
	const chargeZones = await zones.json();
	assert(
		typeof chargeZones.etat === 'string' && [ 'zones_disponibles', 'aucune_zone', 'couche_effis_indisponible' ].includes( chargeZones.etat ),
		'contrat #11 §3 : l’état servi appartient au vocabulaire fermé à trois valeurs',
		'zones_disponibles | aucune_zone | couche_effis_indisponible',
		chargeZones.etat
	);
	for ( const methode of [ 'post', 'put', 'patch', 'delete' ] ) {
		const r = await api[ methode ]( `${ BASE }/wp-json/massifs/v1/zones-parcourues-par-le-feu`, { failOnStatusCode: false } );
		assert(
			r.status() === 404 || r.status() === 405,
			`§6 : ${ methode.toUpperCase() } anonyme refusé sur la couche des zones`,
			'404 (aucune route) ou 405',
			`${ r.status() } — ${ ( await r.text() ).slice( 0, 120 ) }`
		);
	}
	const statuts = await api.get( `${ BASE }/wp-json/massifs/v1/statuts`, { failOnStatusCode: false } );
	egal( 200, statuts.status(), '§5.4 : les statuts du jour sont lisibles en JSON, sans authentification' );

	// L'en-tête CORS n'est émis par le cœur QUE si la requête porte un `Origin` :
	// une sonde sans `Origin` mesurerait son absence et conclurait à un refus. On
	// joue donc la requête telle qu'un réutilisateur tiers l'émettrait.
	//
	// Le contrat #8 §6 annonce « `*` émis par le cœur ». Mesuré : le cœur RENVOIE
	// L'ORIGINE PRÉSENTÉE (`rest_send_cors_headers` fait `header( 'Access-Control-
	// Allow-Origin: ' . get_http_origin() )`). L'effet est le même — n'importe
	// quelle origine tierce est autorisée à lire —, la lettre du contrat ne l'est
	// pas. On affirme donc l'effet observable, qui est ce que le §5.4 promet, et
	// l'écart de lettre est rapporté plutôt qu'aligné en douce.
	const tiers = 'https://un-reutilisateur.example';
	const croise = await api.get( `${ BASE }/wp-json/massifs/v1/statuts`, {
		headers: { Origin: tiers },
		failOnStatusCode: false,
	} );
	const autorise = croise.headers()[ 'access-control-allow-origin' ];
	assert(
		autorise === '*' || autorise === tiers,
		'§5.4 : la réutilisation cross-origin est ouverte — c’est la destination du point d’accès',
		`« * » ou « ${ tiers } »`,
		autorise
	);
	note( `Access-Control-Allow-Origin servi pour une origine tierce : « ${ autorise } » (le cœur renvoie l’origine présentée, pas « * » — écart de lettre avec le contrat #8 §6, sans écart d’effet)` );
	assert(
		( statuts.headers()[ 'cache-control' ] ?? '' ).includes( 'no-cache' ),
		'§4.2 : aucun cache d’âge sur les statuts — un max-age servirait la veille après minuit',
		'no-cache',
		statuts.headers()[ 'cache-control' ] ?? '(absent)'
	);
	const charge = await statuts.json();
	egal( 25, charge.massifs.length, 'les 25 massifs sont servis, quel que soit l’état de la donnée' );
	egal(
		[],
		charge.massifs.filter( ( m ) => m.jour_validite !== charge.jour ).map( ( m ) => m.code ),
		'§4.2 : chaque statut servi porte le jour de l’enveloppe, et aucun autre'
	);
	assert(
		typeof charge.attribution?.statuts?.carte_officielle_url === 'string' && charge.attribution.statuts.carte_officielle_url !== '',
		'le lien de la carte officielle voyage dans la réponse — un réutilisateur peut relayer le repli du §4.2',
		'une URL',
		charge.attribution?.statuts?.carte_officielle_url
	);

	await contexte.close();
}

async function s11_ancreListeSansPartie( navigateur ) {
	scenario( '11 — chemin de panne : la partie « liste-statuts » manque' );
	poserEtat( 'jour-nominal' );

	const partie = path.join( RACINE, 'wp-content/themes/massifs/templates/parts/liste-statuts.php' );
	const mise = `${ partie }.recette-absente`;

	if ( existsSync( mise ) ) {
		renameSync( mise, partie );
	}

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	try {
		renameSync( partie, mise );
		const page = await contexte.newPage();
		await page.goto( BASE + '/', { waitUntil: 'load' } );

		const html = await page.content();
		assert( html.includes( 'partie « liste-statuts » absente' ), 'l’absence est signalée en commentaire HTML, jamais au visiteur', 'commentaire HTML', 'absent' );
		egal( 0, await page.locator( '#liste' ).count(), 'l’ancre #liste disparaît avec la partie' );

		const lienMort = await page.evaluate( () =>
			[ ...document.querySelectorAll( 'a[href^="#"]' ) ]
				.map( ( a ) => a.getAttribute( 'href' ) )
				.filter( ( h ) => ! document.querySelector( `[id="${ h.slice( 1 ) }"]` ) )
		);
		egal( [], lienMort, 'aucun lien d’évitement ne pointe vers une ancre inexistante' );

		egal( 1, await page.locator( 'h1' ).count(), 'la page reste structurée : un h1' );
		assert( ! html.includes( 'Fatal error' ) && ! html.includes( 'Warning:' ), 'aucune erreur PHP visible', 'aucune', 'erreur PHP dans la page' );
		await page.close();
	} finally {
		if ( existsSync( mise ) ) {
			renameSync( mise, partie );
		}
		await contexte.close();
	}

	// Contrôle de remise en état : la page doit être redevenue normale.
	const verif = await navigateur.newContext();
	const p = await verif.newPage();
	await p.goto( BASE + '/', { waitUntil: 'load' } );
	egal( 1, await p.locator( '#liste' ).count(), 'remise en état : l’ancre #liste est de retour' );
	await verif.close();
}

/**
 * Commande wp-cli arbitraire dans le conteneur d'outillage.
 *
 * @param {string[]} arguments_ Arguments de `wp`.
 * @return {string} Sortie.
 */
function wp( arguments_ ) {
	return execFileSync(
		'docker',
		[ 'compose', 'run', '--rm', '-T', 'wpcli', 'wp', '--path=/var/www/html', ...arguments_ ],
		{ cwd: RACINE, encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
	);
}

async function s13_extensionDesactivee( navigateur ) {
	scenario( '13 — chemin de panne : l’extension massifs-core est désactivée' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	try {
		wp( [ 'plugin', 'deactivate', 'massifs-core' ] );
		const page = await contexte.newPage();
		const reponse = await page.goto( BASE + '/', { waitUntil: 'load' } );
		const html = await page.content();

		egal( 200, reponse.status(), 'la page publique reste servie sans l’extension' );
		assert(
			! /Fatal error|Warning:|Notice:|UnhandledMatchError/.test( html ),
			'aucune erreur PHP n’atteint le visiteur',
			'aucune',
			( /(?:Fatal error|Warning:|Notice:)[^<\n]{0,120}/.exec( html ) ?? [ '' ] )[ 0 ]
		);

		const h1 = await texteSource( page.locator( 'h1' ) );
		egal( 1, await page.locator( 'h1' ).count(), 'le h1 unique est conservé' );
		egal(
			'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
			h1,
			'sans domaine, la page annonce l’indisponibilité — jamais un statut'
		);
		egal( 0, await page.locator( 'h1 a' ).count(), 'aucun lien inventé : l’URL officielle vient du serveur, absent' );
		egal( 1, await page.locator( '.bandeau-non-officialite' ).count(), 'le bandeau de non-officialité reste rendu' );
		// Branche « API absente » de l'ardoise (issue #26) : aucun chiffre, donc
		// aucune mention de publication partielle — il n'y a rien à qualifier.
		egal( 0, await page.locator( '.ardoise__publication-partielle' ).count(), 'aucune mention de publication partielle sans domaine' );
		egal( 0, await page.locator( '.ardoise__chiffre' ).count(), 'aucun chiffre du jour sans domaine' );

		// Le point que le contrat #6 (dépendance 5-2) exige de garder : sans
		// extension, la partie « liste » ne rend rien, donc l'ancre disparaît.
		egal( 0, await page.locator( '#liste' ).count(), 'la partie « liste » ne rend rien sans domaine' );
		const liensMorts = await page.evaluate( () =>
			[ ...document.querySelectorAll( 'a[href^="#"]' ) ]
				.map( ( a ) => a.getAttribute( 'href' ) )
				.filter( ( h ) => ! document.querySelector( `[id="${ h.slice( 1 ) }"]` ) )
		);
		egal( [], liensMorts, 'aucun lien d’évitement ne pointe vers une ancre inexistante' );

		await page.close();
	} finally {
		wp( [ 'plugin', 'activate', 'massifs-core' ] );
		await contexte.close();
	}

	const verif = await navigateur.newContext();
	const p = await verif.newPage();
	await p.goto( BASE + '/', { waitUntil: 'load' } );
	egal( 1, await p.locator( '#liste' ).count(), 'remise en état : l’extension est réactivée et la liste revient' );
	await verif.close();
}

async function s12_integriteArtefacts() {
	scenario( '12 — intégrité des artefacts du design system' );

	const { createHash } = await import( 'node:crypto' );
	const tokens = path.join( RACINE, 'wp-content/themes/massifs/assets/css/tokens.css' );
	const octets = readFileSync( tokens );
	const somme = createHash( 'sha256' ).update( octets ).digest( 'hex' );
	egal(
		// Empreinte ré-épinglée par l'amendement v2.4 de MASTER (chaîne #50) et
		// enregistrée dans `docs/contracts/issue-4.md` §« Amendement ». L'ancienne,
		// caduque, valait 5ad802a3708fe1734845e7a76b46de5382f2421268542584cafa270d29aa3835.
		'104efb21fefa0e42b55ba0707ead5755e940b2cd87e4b1cff4c70148aeec112f',
		somme,
		'tokens.css : sha256 conforme à celui gelé par le contrat #4'
	);

	const texte = octets.toString( 'utf8' );
	const racineBloc = /:root\s*\{([\s\S]*?)\n\}/.exec( texte );
	// Sans ancre de début de ligne : plusieurs jetons partagent une même ligne.
	const proprietes = [ ...racineBloc[ 1 ].matchAll( /(--[a-z0-9-]+)\s*:/g ) ].map( ( m ) => m[ 1 ] );
	// 111 avant la révision v2.4 de MASTER, qui ajoute les cinq jetons de la carte
	// (--carte-lisere, --carte-survol, --carte-cerne, --carte-cerne-clair,
	// --bord-selection). 133 déclarations dans le fichier entier, les deux classes
	// de palier comprises.
	egal( 116, proprietes.length, 'tokens.css : 116 propriétés personnalisées sur :root' );
	egal(
		133,
		[ ...texte.matchAll( /(?:^|[\s{;])(--[a-z0-9-]+)\s*:/g ) ].length,
		'tokens.css : 133 déclarations dans le fichier entier (méthode de comptage du contrat #4)'
	);
	egal( proprietes.length, new Set( proprietes ).size, 'tokens.css : aucun jeton déclaré deux fois' );

	// Le bloc normatif de MASTER §12 et le fichier servi doivent être le même texte.
	const master = readFileSync( path.join( RACINE, 'design-system/MASTER.md' ), 'utf8' ).split( '\n' );
	const debut = master.findIndex( ( l ) => l.startsWith( '## 12.' ) );
	const fin = master.findIndex( ( l, i ) => i > debut && l.startsWith( '## 13.' ) );
	const section = master.slice( debut, fin );
	const bornes = section.map( ( l, i ) => ( l.startsWith( '```' ) ? i : -1 ) ).filter( ( i ) => i >= 0 );
	const bloc = section.slice( bornes[ 0 ] + 1, bornes[ 1 ] ).join( '\n' );
	egal( bloc.trimEnd(), texte.trimEnd(), 'tokens.css est la transcription exacte du bloc normatif de MASTER §12' );

	const fonts = path.join( RACINE, 'wp-content/themes/massifs/assets/fonts' );
	const { readdirSync } = await import( 'node:fs' );
	const woff2 = readdirSync( fonts ).filter( ( f ) => f.endsWith( '.woff2' ) );
	egal( 2, woff2.length, 'exactement deux fichiers de police dans le thème' );
	const fontsCss = readFileSync( path.join( fonts, 'fonts.css' ), 'utf8' );
	egal(
		[],
		[ ...fontsCss.matchAll( /url\(\s*['"]?([^)'"]+)/g ) ].map( ( m ) => m[ 1 ] ).filter( ( u ) => /^https?:|^\/\// .test( u ) ),
		'fonts.css ne référence aucune police distante'
	);
	assert( fontsCss.includes( 'font-display' ), 'fonts.css déclare une stratégie font-display', 'font-display', 'absent' );

	// Quatre jetons sont DÉCLARÉS sans être consommés (--ombre-decalee et la
	// frise) : la chaîne #22 s'y engage explicitement. Si une chaîne ultérieure
	// les branchait, le dessin changerait sans que MASTER ne bouge.
	const feuilles = [ 'tokens.css', 'layout.css', 'composants.css', 'print.css' ].map( ( f ) =>
		readFileSync( path.join( RACINE, 'wp-content/themes/massifs/assets/css', f ), 'utf8' )
	);
	const consommes = feuilles.join( '\n' ).match( /var\(\s*--(?:ombre-decalee|frise-)[a-z-]*/g ) ?? [];
	egal( [], consommes, '--ombre-decalee* et --frise-* restent déclarés mais jamais consommés' );

	// print.css ne doit RIEN savoir du bandeau de non-officialité : le §5.6 le
	// rend obligatoire partout où un statut paraît, papier compris.
	const print = readFileSync( path.join( RACINE, 'wp-content/themes/massifs/assets/css/print.css' ), 'utf8' );
	egal( 0, ( print.match( /bandeau-non-officialite/g ) ?? [] ).length, 'print.css ne mentionne jamais le bandeau de non-officialité' );

	// Ceinture-bretelles du contrat #22 : tout print.css est sous `@media print`.
	// Si la poignée était un jour enfilée en `media="all"`, aucune règle ne
	// fuirait vers l'écran. Contrôle structurel : hors commentaires et hors
	// @charset, la seule accolade ouvrante de premier niveau est celle du
	// `@media print`.
	const sansCommentaires = print.replace( /\/\*[\s\S]*?\*\//g, '' );
	let profondeur = 0;
	const premierNiveau = [];
	for ( let i = 0; i < sansCommentaires.length; i += 1 ) {
		if ( sansCommentaires[ i ] === '{' ) {
			if ( profondeur === 0 ) {
				premierNiveau.push( sansCommentaires.slice( 0, i ).split( /[};]/ ).pop().trim() );
			}
			profondeur += 1;
		} else if ( sansCommentaires[ i ] === '}' ) {
			profondeur -= 1;
		}
	}
	egal( [ '@media print' ], premierNiveau, 'print.css : tout le fichier est enveloppé dans un unique @media print' );

	// Aucune requête tierce ne peut naître d'une feuille : ni url() distante, ni
	// @import, dans aucune des quatre.
	egal(
		[],
		feuilles.flatMap( ( f, i ) =>
			[ ...f.matchAll( /(?:url\(\s*['"]?|@import\s+['"]?)([^)'";]+)/g ) ]
				.map( ( m ) => m[ 1 ] )
				.filter( ( u ) => /^https?:|^\/\//.test( u ) )
				.map( ( u ) => `${ [ 'tokens', 'layout', 'composants', 'print' ][ i ] }.css → ${ u }` )
		),
		'aucune feuille du thème ne référence une ressource distante'
	);

	// Le recadrage typographique de la chaîne #23 : plus une seule capitale
	// forcée dans la feuille de mise en page. Le contrôle sur le rendu réel est
	// dans le scénario « casse ».
	const layout = readFileSync( path.join( RACINE, 'wp-content/themes/massifs/assets/css/layout.css' ), 'utf8' );
	egal( 0, ( layout.match( /text-transform/g ) ?? [] ).length, 'layout.css : plus aucune déclaration text-transform' );

	// --- Image statique du fond de carte (issue #9) : contrôle SUR LE BINAIRE.
	//
	// Le contrat #9 §12.5 le demande nommément — « à vérifier sur le binaire, pas
	// sur l'intention ». Deux invariants s'y jouent :
	//
	//   I-9.3 : l'image ne porte AUCUN aplat de statut. Une image qui porterait
	//           les couleurs du jour se périmerait par un chemin que le PHP ne
	//           contrôle plus (cache HTTP, CDN de l'hébergeur) — la règle absolue
	//           du §4.2 tomberait par la porte de derrière. Sur un PNG indexé, la
	//           preuve est directe : les deux teintes officielles ne sont pas dans
	//           la palette, donc pas un pixel ne peut les porter.
	//   I-9.2 : aucune URL tierce nulle part, « métadonnée d'image comprise ».
	//           `tEXt`, `iTXt` et `zTXt` sont les trois porteurs possibles.
	const png = readFileSync( path.join( RACINE, 'wp-content/themes/massifs/assets/img/carte-statique.png' ) );
	const morceaux = {};
	let palette = null;
	let entete = null;
	for ( let i = 8; i + 8 <= png.length; ) {
		const longueur = png.readUInt32BE( i );
		const type = png.toString( 'latin1', i + 4, i + 8 );
		morceaux[ type ] = ( morceaux[ type ] ?? 0 ) + 1;
		if ( type === 'PLTE' ) {
			palette = png.subarray( i + 8, i + 8 + longueur );
		}
		if ( type === 'IHDR' ) {
			entete = { largeur: png.readUInt32BE( i + 8 ), hauteur: png.readUInt32BE( i + 12 ), typeCouleur: png[ i + 17 ] };
		}
		i += 12 + longueur;
	}

	egal( 3, entete?.typeCouleur, 'contrat #9 §2 : l’image de repli est un PNG INDEXÉ (type de couleur 3), format gelé' );
	const teintes = [];
	for ( let i = 0; palette && i + 2 < palette.length; i += 3 ) {
		teintes.push( `#${ palette.subarray( i, i + 3 ).toString( 'hex' ).toUpperCase() }` );
	}
	note( `palette de carte-statique.png : ${ teintes.length } couleurs — ${ teintes.join( ' ' ) }` );
	egal(
		[],
		[ '#22B14C', '#E63A3C' ].filter( ( t ) => teintes.includes( t ) ),
		'contrat #9 I-9.3 : aucune teinte de statut dans la palette — l’image ne peut pas périmer un statut'
	);
	egal(
		[],
		[ 'tEXt', 'iTXt', 'zTXt' ].filter( ( t ) => morceaux[ t ] ),
		'contrat #9 I-9.2 : aucun morceau de métadonnée textuelle dans le PNG (aucune URL tierce n’y est cachée)'
	);
	assert(
		entete.largeur === 1600 && entete.hauteur > 0,
		'contrat #9 A-4 : une seule variante, 1600 px de large, hauteur dérivée de la bbox projetée',
		'1600 × (>0)',
		`${ entete?.largeur } × ${ entete?.hauteur }`
	);
	note( `dimensions intrinsèques : ${ entete.largeur } × ${ entete.hauteur } (rapport ${ ( entete.largeur / entete.hauteur ).toFixed( 4 ) } — l’avenant du contrat #9 §13 fixe 1,125)` );
}

// ---------------------------------------------------------------- lot « direction mairie et couche visuelle »

/** Sélecteurs des états de marque, et ce que chacun doit porter. */
const ETATS_DE_MARQUE = [
	{ classe: 'pastille--autorise', motif: false, boite: [ 26, 16 ] },
	{ classe: 'pastille--interdit', motif: true, boite: [ 26, 16 ] },
	{ classe: 'pastille--indisponible', motif: true, boite: [ 26, 16 ] },
	{ classe: 'pastille--hors-saison', motif: false, boite: [ 26, 16 ] },
	{ classe: 'pastille--non-publie', motif: true, boite: [ 26, 16 ] },
	{ classe: 'jalon--autorise', motif: false, boite: [ 18, 18 ] },
	{ classe: 'jalon--interdit', motif: true, boite: [ 18, 18 ] },
];

const TRANSPARENT = new Set( [ 'rgba(0, 0, 0, 0)', 'transparent' ] );

/** --c-charbon (#1A1C19) et --c-calcaire (#EDEEEC), tels que le moteur les calcule. */
const CHARBON = 'rgb(26, 28, 25)';
const CALCAIRE = 'rgb(237, 238, 236)';

/**
 * Relevé des marques réellement peintes sur la page chargée.
 *
 * @param {import('playwright-core').Page} page Page chargée.
 * @return {Promise<object[]>} Un relevé par marque présente dans le DOM.
 */
function releverMarques( page ) {
	// Marques RÉELLEMENT RENDUES seulement.
	//
	// Depuis la chaîne #7, le panneau de la carte porte dans le HTML servi un
	// gabarit de marques que `carte.js` renseigne à la sélection : deux marques
	// sans classe d'état, et une occurrence masquée de chacun des trois états hors
	// niveau. Toutes vivent sous un conteneur `hidden`, donc sans boîte (0 × 0) et
	// sans peinture. Les mesurer reviendrait à affirmer qu'une marque non affichée
	// est mal dessinée — un rouge qui ne décrit rien. Ce que le §8 exige porte sur
	// ce que le visiteur voit ; le fait que ces gabarits soient bien masqués et
	// bien vides est affirmé, lui, par le scénario `couleur`.
	return page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__marque' ) ].filter( ( e ) => e.getClientRects().length > 0 ).map( ( e ) => {
			const s = getComputedStyle( e );
			const r = e.getBoundingClientRect();
			return {
				classes: [ ...e.classList ],
				fond: s.backgroundColor,
				motif: s.backgroundImage,
				bordure: [ s.borderTopWidth, s.borderRightWidth, s.borderBottomWidth, s.borderLeftWidth ],
				styleBordure: s.borderTopStyle,
				couleurBordure: s.borderTopColor,
				largeur: Math.round( r.width ),
				hauteur: Math.round( r.height ),
			};
		} )
	);
}

async function s14_coucheVisuelleDesStatuts( navigateur ) {
	scenario( '14 — la couche visuelle des statuts est réellement peinte (chaîne #22)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const marques = await releverMarques( page );
	note( `marques rendues sur l’accueil : ${ marques.length }` );

	// 1. Plus une seule marque transparente. C'était le défaut du lot précédent :
	//    44 marques sur 44 calculaient rgba(0, 0, 0, 0).
	const eteintes = marques.filter( ( m ) => TRANSPARENT.has( m.fond ) );
	egal( [], eteintes.map( ( m ) => m.classes.join( '.' ) ), 'aucune marque ne reste sans aplat peint' );

	// 2. Le liseré de 2 px est le mécanisme PORTEUR d'AA (la teinte seule plafonne
	//    à 4,11:1 sur le rouge officiel) : il est exigé sur CHAQUE état, sans
	//    exception, sur les quatre côtés, et jamais transparent.
	const liserePerdu = marques.filter(
		( m ) =>
			m.bordure.some( ( l ) => l !== '2px' ) ||
			m.styleBordure !== 'solid' ||
			TRANSPARENT.has( m.couleurBordure )
	);
	egal(
		[],
		liserePerdu.map( ( m ) => `${ m.classes.join( '.' ) } → ${ m.bordure.join( '/' ) } ${ m.styleBordure } ${ m.couleurBordure }` ),
		'chaque marque porte le liseré 2px solid sur ses quatre côtés'
	);

	// 3. Motif présent OÙ IL DOIT L'ÊTRE, absent OÙ IL NE DOIT PAS L'ÊTRE. C'est
	//    l'opposition nu/marqué qui encode le sens : un motif sur « autorisé »
	//    est un défaut bloquant (MASTER §16), pas une fantaisie.
	const etatsPresents = new Set();
	for ( const m of marques ) {
		const etat = ETATS_DE_MARQUE.find( ( e ) => m.classes.includes( e.classe ) );
		if ( ! etat ) {
			ko( 'marque sans état connu', ETATS_DE_MARQUE.map( ( e ) => e.classe ).join( '|' ), m.classes.join( '.' ) );
			continue;
		}
		etatsPresents.add( etat.classe );
		const aUnMotif = m.motif !== 'none';
		if ( aUnMotif !== etat.motif ) {
			ko(
				`${ etat.classe } : motif ${ etat.motif ? 'exigé' : 'INTERDIT' }`,
				etat.motif ? 'un background-image' : 'none',
				m.motif.slice( 0, 90 )
			);
		}
	}
	for ( const etat of etatsPresents ) {
		ok( `${ etat } : motif conforme sur toutes ses occurrences rendues` );
	}

	// 4. Les boîtes du §8.1, box-sizing: border-box compris.
	for ( const etat of ETATS_DE_MARQUE ) {
		const exemple = marques.find( ( m ) => m.classes.includes( etat.classe ) );
		if ( ! exemple ) {
			continue;
		}
		egal( etat.boite, [ exemple.largeur, exemple.hauteur ], `${ etat.classe } : boîte extérieure conforme au §8.1` );
	}

	// 5. La hampe du jalon — un pseudo-élément : elle n'existe que si elle est
	//    réellement peinte, et c'est elle qui distingue « point ZAPEF » de
	//    « surface massif ».
	const hampe = await page.evaluate( () => {
		const e = document.querySelector( '.jalon' );
		if ( ! e ) {
			return null;
		}
		const s = getComputedStyle( e, '::after' );
		return { contenu: s.content, fond: s.backgroundColor, hauteur: s.height, largeur: s.width };
	} );
	assert( hampe !== null, 'un jalon est rendu sur la page', 'un .jalon', 'aucun' );
	if ( hampe ) {
		assert( ! TRANSPARENT.has( hampe.fond ), 'la hampe du jalon est peinte', 'une couleur', hampe.fond );
		egal( [ '2px', '8px' ], [ hampe.largeur, hampe.hauteur ], 'la hampe fait 2 × 8 px (§8.1)' );
	}

	// 6. `pastille--non-publie` n'est JAMAIS atteignable depuis l'accueil servi :
	//    la légende ne demande que `indisponible` et `hors_saison`
	//    (legende.php l. 82-84) et l'état « demain non publié » suit l'horloge du
	//    conteneur. Son gabarit est éprouvé par le scénario PHP 21 ; sa peinture
	//    ne peut l'être qu'en la posant dans la cascade réelle de la page servie.
	//    C'est bien la feuille servie qui est mesurée, pas une feuille de test.
	const sonde = await page.evaluate( () => {
		const hote = document.querySelector( '.liste-statuts__ligne' ) ?? document.body;
		const span = document.createElement( 'span' );
		span.className = 'statut__marque pastille pastille--non-publie';
		hote.append( span );
		const s = getComputedStyle( span );
		const releve = {
			fond: s.backgroundColor,
			motif: s.backgroundImage,
			taille: s.backgroundSize,
			bordure: s.borderTopWidth,
			style: s.borderTopStyle,
			couleur: s.borderTopColor,
		};
		span.remove();
		return releve;
	} );
	note( 'sonde `pastille--non-publie` : état non atteignable depuis l’accueil servi, posé dans la cascade réelle' );
	assert( ! TRANSPARENT.has( sonde.fond ), 'pastille--non-publie : aplat peint', 'une couleur', sonde.fond );
	assert( sonde.motif.includes( 'radial-gradient' ), 'pastille--non-publie : motif pointillé présent', 'radial-gradient', sonde.motif.slice( 0, 90 ) );
	egal( '6px 6px', sonde.taille, 'pastille--non-publie : pas du pointillé de 6 px (§8.1)' );
	egal( [ '2px', 'solid' ], [ sonde.bordure, sonde.style ], 'pastille--non-publie : liseré 2px solid' );

	// 7. Le libellé qui suit `non-publie` est une PHRASE : les capitales y sont
	//    interdites (§14.3). La règle est un sélecteur adjacent — elle ne vaut que
	//    si le gabarit émet toujours la marque avant le libellé.
	const phrase = await page.evaluate( () => {
		const hote = document.querySelector( '.liste-statuts__ligne' ) ?? document.body;
		const enveloppe = document.createElement( 'span' );
		enveloppe.className = 'statut';
		enveloppe.innerHTML =
			'<span class="statut__marque pastille pastille--non-publie"></span><span class="statut__libelle">Les statuts de demain ne sont pas encore publiés.</span>';
		hote.append( enveloppe );
		const s = getComputedStyle( enveloppe.lastElementChild );
		const releve = { casse: s.textTransform, famille: s.fontFamily, interlettre: s.letterSpacing };
		enveloppe.remove();
		return releve;
	} );
	egal( 'none', phrase.casse, 'le libellé qui suit `non-publie` échappe aux capitales (§14.3)' );

	await contexte.close();
}

async function s15_ordreDesFeuilles( navigateur ) {
	scenario( '15 — ordre et media des cinq feuilles, mesurés sur la page servie' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const liens = await page.evaluate( () =>
		[ ...document.querySelectorAll( 'link[rel="stylesheet"]' ) ].map( ( l ) => ( {
			id: l.id,
			media: l.media,
			fichier: l.href.split( '/' ).pop().split( '?' )[ 0 ],
		} ) )
	);

	// Sept feuilles depuis la chaîne #7, dans un ordre que le contrat #7 §8.5 fixe
	// et qui n'est pas négociable :
	//   — `massifs-carte` s'insère ENTRE `composants` et `print`, parce qu'elle
	//     réserve la hauteur de la bande carte : enfilée plus tard, elle ferait
	//     grandir le héros après coup (§10 du brief) ;
	//   — `print` reste après `composants`, pour l'emporter à spécificité égale ;
	//   — `leaflet` est enfilée en dernier, tardivement par `carte.php`, ce qui est
	//     l'avertissement 3 du §8.3 : toute surcharge de `carte.css` doit gagner en
	//     spécificité, jamais par ordre de cascade. Sa position après `print` est
	//     sans effet, `print` étant en `media="print"`.
	// L'assertion reste une ÉGALITÉ SUR LISTE ORDONNÉE : c'est elle qui détecte
	// qu'une feuille a été insérée au mauvais rang.
	egal(
		[ 'massifs-fonts-css', 'massifs-tokens-css', 'massifs-layout-css', 'massifs-composants-css', 'massifs-carte-css', 'massifs-print-css', 'massifs-leaflet-css' ],
		liens.map( ( l ) => l.id ),
		'les sept feuilles sont servies dans l’ordre du contrat, et aucune autre'
	);
	assert(
		liens.findIndex( ( l ) => l.id === 'massifs-leaflet-css' ) > liens.findIndex( ( l ) => l.id === 'massifs-carte-css' ),
		'contrat #7 §8.3 : leaflet.css est enfilée APRÈS carte.css',
		'leaflet après carte',
		liens.map( ( l ) => l.id ).join( ' → ' )
	);
	egal(
		[ 'print' ],
		liens.filter( ( l ) => l.media === 'print' ).map( ( l ) => l.media ),
		'une seule feuille porte media="print"'
	);
	egal( 'massifs-print-css', liens.filter( ( l ) => l.media === 'print' )[ 0 ]?.id, 'c’est bien la feuille d’impression' );
	assert(
		liens.findIndex( ( l ) => l.id === 'massifs-print-css' ) > liens.findIndex( ( l ) => l.id === 'massifs-composants-css' ),
		'massifs-print vient APRÈS massifs-composants (elle doit l’emporter à spécificité égale)',
		'print après composants',
		liens.map( ( l ) => l.id ).join( ' → ' )
	);
	egal(
		[ 'all', 'all', 'all', 'all', 'all', 'all' ],
		liens.filter( ( l ) => l.id !== 'massifs-print-css' ).map( ( l ) => l.media ),
		'les six feuilles d’écran sont en media="all"'
	);

	// `media="print"` n'est pas décoratif : la feuille ne doit rien peindre à
	// l'écran. Sa règle la plus visible est l'adresse dépliée derrière les liens.
	const surEcran = await page.evaluate( () => {
		const a = document.querySelector( 'a[href^="http"]' );
		return a ? getComputedStyle( a, '::after' ).content : null;
	} );
	assert(
		surEcran === 'none' || surEcran === 'normal' || surEcran === '',
		'aucune règle de print.css ne fuit vers l’écran (adresses non dépliées)',
		'none',
		surEcran
	);

	await contexte.close();
}

async function s16_recadrageTypographique( navigateur ) {
	scenario( '16 — plus aucune capitale forcée sur les titres (chaîne #23)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const titres = await page.evaluate( () =>
		[ ...document.querySelectorAll( 'h1, h2, h3, h4, h5, h6' ) ].map( ( e ) => ( {
			balise: e.tagName.toLowerCase(),
			classe: e.className,
			casse: getComputedStyle( e ).textTransform,
		} ) )
	);
	note( `titres mesurés : ${ titres.map( ( t ) => `${ t.balise }.${ t.classe }` ).join( ' · ' ) }` );
	egal(
		[],
		titres.filter( ( t ) => t.casse !== 'none' ).map( ( t ) => `${ t.balise }.${ t.classe } → ${ t.casse }` ),
		'aucun titre n’est mis en capitales par le CSS'
	);

	// Les capitales survivent UNIQUEMENT sur les étiquettes --fs-250 (13 px).
	// Tout autre élément en capitales serait une régression du recadrage.
	const capitales = await page.evaluate( () =>
		[ ...document.querySelectorAll( 'body *' ) ]
			.filter( ( e ) => getComputedStyle( e ).textTransform === 'uppercase' )
			.map( ( e ) => ( {
				balise: e.tagName.toLowerCase(),
				classe: e.className,
				corps: getComputedStyle( e ).fontSize,
			} ) )
	);
	const AUTORISEES = [ 'statut__libelle', 'liste-statuts__entete', 'legende__etiquette' ];
	egal(
		[],
		capitales
			.filter( ( c ) => ! AUTORISEES.some( ( a ) => String( c.classe ).split( /\s+/ ).includes( a ) ) )
			.map( ( c ) => `${ c.balise }.${ c.classe }` ),
		'seules les étiquettes prévues sont en capitales'
	);
	egal(
		[],
		capitales.filter( ( c ) => c.corps !== '13px' ).map( ( c ) => `${ c.classe } → ${ c.corps }` ),
		'toutes les capitales sont au corps --fs-250 (13 px)'
	);
	note( `éléments en capitales : ${ capitales.length } (classes : ${ [ ...new Set( capitales.map( ( c ) => c.classe ) ) ].join( ' · ' ) })` );

	await contexte.close();
}

async function s17_couleursForcees( navigateur ) {
	scenario( '17 — rendu sous forced-colors: active (composants.css §12)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false, forcedColors: 'active' } );
	const page = await contexte.newPage();
	await page.emulateMedia( { forcedColors: 'active' } );
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const actif = await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches );
	if ( ! assert( actif, 'le mode couleurs forcées est bien actif dans le navigateur', true, actif ) ) {
		await contexte.close();
		return;
	}

	// Sous couleurs forcées, l'UA remplace tous les fonds et abandonne les
	// dégradés : le motif — la MOITIÉ de l'information (§10.3) — disparaîtrait
	// sans les pseudo-éléments de reconstruction.
	const releve = await page.evaluate( () => {
		const lire = ( selecteur, pseudo ) => {
			const e = document.querySelector( selecteur );
			if ( ! e ) {
				return null;
			}
			const s = getComputedStyle( e, pseudo );
			return { contenu: s.content, fond: s.backgroundColor, transformation: s.transform };
		};
		const poser = ( classe ) => {
			const span = document.createElement( 'span' );
			span.className = `statut__marque ${ classe }`;
			document.body.append( span );
			const avant = getComputedStyle( span, '::before' );
			const releve = { contenu: avant.content, fond: avant.backgroundColor };
			span.remove();
			return releve;
		};
		return {
			interditAvant: lire( '.pastille--interdit', '::before' ),
			interditApres: lire( '.pastille--interdit', '::after' ),
			indisponible: lire( '.pastille--indisponible', '::before' ),
			jalonInterdit: lire( '.jalon--interdit', '::before' ),
			jalonHampe: lire( '.jalon', '::after' ),
			autorise: lire( '.pastille--autorise', '::before' ),
			horsSaison: lire( '.pastille--hors-saison', '::before' ),
			jalonAutorise: lire( '.jalon--autorise', '::before' ),
			nonPublie: poser( 'pastille pastille--non-publie' ),
			// L'aplat est forcé par l'UA : il ne peut plus rien distinguer.
			fondForce: getComputedStyle( document.querySelector( '.pastille--interdit' ) ).backgroundColor,
		};
	} );

	const reconstruits = [
		[ 'pastille--interdit ::before (oblique 45°)', releve.interditAvant ],
		[ 'pastille--interdit ::after (oblique -45°)', releve.interditApres ],
		[ 'pastille--indisponible ::before', releve.indisponible ],
		[ 'jalon--interdit ::before', releve.jalonInterdit ],
		[ 'pastille--non-publie ::before', releve.nonPublie ],
	];
	for ( const [ nom, mesure ] of reconstruits ) {
		assert( mesure && mesure.contenu === '""', `${ nom } : le motif est reconstruit`, 'content: ""', mesure ? mesure.contenu : 'élément absent' );
		if ( mesure ) {
			assert( ! TRANSPARENT.has( mesure.fond ), `${ nom } : peint en CanvasText`, 'une couleur opaque', mesure.fond );
		}
	}

	assert(
		releve.jalonHampe && ! TRANSPARENT.has( releve.jalonHampe.fond ),
		'la hampe du jalon survit aux couleurs forcées (silhouette « point planté »)',
		'une couleur opaque',
		releve.jalonHampe ? releve.jalonHampe.fond : 'absent'
	);

	for ( const [ nom, mesure ] of [
		[ 'pastille--autorise', releve.autorise ],
		[ 'pastille--hors-saison', releve.horsSaison ],
		[ 'jalon--autorise', releve.jalonAutorise ],
	] ) {
		assert(
			mesure && ( mesure.contenu === 'none' || mesure.contenu === 'normal' ),
			`${ nom } : reste NU sous couleurs forcées (aucun motif ajouté)`,
			'content: none',
			mesure ? mesure.contenu : 'élément absent'
		);
	}

	note(
		`dégradation documentée et acceptée (arbitrage 6 bis) : sous couleurs forcées, ` +
			`« autorisé » et « hors-saison » deviennent identiques — même Canvas, même liseré CanvasText, ` +
			`aucun motif de part et d’autre. Le libellé en toutes lettres reste le seul porteur de sens.`
	);
	note( `aplat forcé par l’UA sur .pastille--interdit : ${ releve.fondForce } — la teinte ne distingue plus rien` );

	// Le libellé DOIT donc être là, et non vide : c'est lui qui porte tout.
	// Même portée que le scénario `couleur` : les marques RENDUES. Les gabarits
	// masqués du panneau de carte n'ont ni couleur ni libellé, par construction,
	// et ne portent donc rien qu'un mode « couleurs forcées » puisse effacer.
	const orphelines = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__marque' ) ]
			.filter( ( m ) => m.getClientRects().length > 0 )
			.filter( ( m ) => {
				const s = m.nextElementSibling;
				return ! s || ! s.classList.contains( 'statut__libelle' ) || s.textContent.trim() === '';
			} ).length
	);
	egal( 0, orphelines, 'sous couleurs forcées, chaque marque rendue garde son libellé en toutes lettres' );

	await contexte.close();
}

async function s18_impressionA4etA5( navigateur ) {
	scenario( '18 — aperçu d’impression, A4 ET A5 (print.css §4, invariant I-5)' );
	poserEtat( 'jour-nominal' );

	// Les deux formats, en largeur de contenu après les 12 mm de marge du @page :
	// A4 210 mm − 24 mm = 186 mm ≈ 703 px ; A5 148 mm − 24 mm = 124 mm ≈ 469 px.
	// C'est le second qui compte : à 469 px, `min-width: 37.5rem` (600 px) ne
	// s'applique PAS, et sans print.css §4 la feuille imprimerait des cartes
	// empilées au lieu du tableau exigé par MASTER §13.
	const formats = [
		{ nom: 'A4', largeur: 703 },
		{ nom: 'A5', largeur: 469 },
	];

	for ( const format of formats ) {
		const contexte = await navigateur.newContext( { viewport: { width: format.largeur, height: 900 }, javaScriptEnabled: false } );
		const page = await contexte.newPage();

		// Contrôle négatif d'abord : à l'ÉCRAN, à cette largeur, quel mode ?
		await page.goto( BASE + '/', { waitUntil: 'load' } );
		const ecran = await page.evaluate( () => getComputedStyle( document.querySelector( '.liste-statuts__tableau' ) ).display );
		note( `${ format.nom } (${ format.largeur } px) — à l’écran, la liste est en mode « ${ ecran === 'table' ? 'colonnes' : 'cartes' } »` );

		await page.emulateMedia( { media: 'print' } );

		const mesure = await page.evaluate( () => {
			const q = ( s ) => document.querySelector( s );
			const d = ( s ) => ( q( s ) ? getComputedStyle( q( s ) ).display : 'ABSENT' );
			const cellule = q( '.liste-statuts__cellule[data-etiquette]' );
			const lien = q( '.bandeau-non-officialite a[href^="http"]' );
			const thead = q( '.liste-statuts__tableau thead' );
			const styleThead = thead ? getComputedStyle( thead ) : null;
			return {
				tableau: d( '.liste-statuts__tableau' ),
				thead: thead ? styleThead.display : 'ABSENT',
				// Les deux propriétés qui font de ce scénario la SEULE sonde
				// automatisée capable de détecter le retrait de la garde
				// `@media screen` du §7 bis de composants.css — obligation (b)
				// de l'invariant I-5 (rév. #28). `print.css` ne réinitialise que
				// `display` : si le déport de l'en-tête n'était plus gardé, sa
				// `position: absolute` BLOCKIFIERAIT le thead — son `display`
				// calculé deviendrait `block` quoi que déclare print.css, sans
				// qu'aucune spécificité n'entre en jeu — et l'en-tête répété du
				// §13 disparaîtrait de la feuille, A4 comme A5. Mesurer
				// `display` seul ne suffit donc pas : c'est `position` et
				// `clip-path` qui disent que la garde est là.
				theadPosition: thead ? styleThead.position : 'ABSENT',
				theadClipPath: thead ? styleThead.clipPath : 'ABSENT',
				// Les quatre libellés doivent être présents ET peints : une
				// boîte de 2 px (le déport) ou un `clip-path` survivant les
				// rendrait invisibles sur le papier sans changer le HTML.
				entetes: thead
					? [ ...thead.querySelectorAll( 'th' ) ].map( ( e ) => ( {
						texte: e.textContent.trim(),
						largeur: Math.round( e.getBoundingClientRect().width ),
						clip: getComputedStyle( e ).clipPath,
					} ) )
					: [],
				ligne: d( '.liste-statuts__ligne' ),
				massif: d( '.liste-statuts__massif' ),
				cellule: d( '.liste-statuts__cellule' ),
				resume: d( '.liste-statuts__resume' ),
				etiquette: cellule ? getComputedStyle( cellule, '::before' ).content : 'ABSENT',
				carte: d( '.bande--carte' ),
				evitement: d( '.lien-evitement' ),
				bandeau: d( '.bandeau-non-officialite' ),
				adresse: lien ? getComputedStyle( lien, '::after' ).content : 'ABSENT',
				fondCorps: getComputedStyle( document.body ).backgroundColor,
				pastilleFond: q( '.pastille' ) ? getComputedStyle( q( '.pastille' ) ).backgroundColor : 'ABSENT',
				pastilleBordure: q( '.pastille' ) ? getComputedStyle( q( '.pastille' ) ).borderTopColor : 'ABSENT',
				pastilleMotif: q( '.pastille--interdit' ) ? getComputedStyle( q( '.pastille--interdit' ) ).backgroundImage : 'ABSENT',
			};
		} );

		egal( 'table', mesure.tableau, `${ format.nom } : la liste s’imprime en tableau, inconditionnellement` );
		egal( 'table-header-group', mesure.thead, `${ format.nom } : l’en-tête revient et se répète de page en page` );
		egal( 'static', mesure.theadPosition, `${ format.nom } : l’en-tête n’est PAS déporté au papier (garde @media screen tenue)` );
		egal( 'none', mesure.theadClipPath, `${ format.nom } : aucun clip-path ne survit sur l’en-tête imprimé` );
		egal(
			[ 'Massif', 'Niveau d\'Accès', 'ZAPEF', 'Fraîcheur' ],
			mesure.entetes.map( ( e ) => e.texte ),
			`${ format.nom } : les quatre libellés de colonne sont sur la feuille`
		);
		egal(
			[],
			mesure.entetes.filter( ( e ) => e.largeur <= 2 || e.clip !== 'none' ).map( ( e ) => `${ e.texte } (${ e.largeur } px, clip ${ e.clip })` ),
			`${ format.nom } : aucun libellé de colonne écrêté ni réduit à la boîte du déport`
		);
		egal( 'table-row', mesure.ligne, `${ format.nom } : les lignes sont des rangées` );
		egal( [ 'table-cell', 'table-cell' ], [ mesure.massif, mesure.cellule ], `${ format.nom } : les cellules sont des cellules` );
		egal( 'table-caption', mesure.resume, `${ format.nom } : le résumé redevient la légende du tableau` );
		egal( 'none', mesure.etiquette, `${ format.nom } : aucune étiquette de carte ne s’imprime` );
		egal( 'none', mesure.carte, `${ format.nom } : la bande carte ne s’imprime pas` );
		egal( 'none', mesure.evitement, `${ format.nom } : les liens d’évitement ne s’impriment pas` );
		assert( mesure.bandeau !== 'none', `${ format.nom } : le bandeau de non-officialité S’IMPRIME (§5.6)`, '≠ none', mesure.bandeau );
		assert( String( mesure.adresse ).includes( 'http' ), `${ format.nom } : les adresses sont dépliées (§13)`, '… (https://…)', mesure.adresse );
		egal( 'rgba(0, 0, 0, 0)', mesure.pastilleFond, `${ format.nom } : l’aplat des pastilles est abandonné, seuls liseré et motif s’impriment` );
		egal( CHARBON, mesure.pastilleBordure, `${ format.nom } : le liseré s’imprime en --c-charbon` );
		assert(
			mesure.pastilleMotif.includes( CHARBON ) && ! mesure.pastilleMotif.includes( CALCAIRE ),
			`${ format.nom } : la hachure de « interdit » s’imprime en charbon, jamais en calcaire`,
			`un gradient en ${ CHARBON }`,
			mesure.pastilleMotif.slice( 0, 120 )
		);

		// LE PIÈGE que print.css §7 est là pour désamorcer, et que la page servie
		// n'exerce jamais : sous `.sur-sombre`, tokens.css bascule le liseré et les
		// encres de motif en CALCAIRE. §13 convertit ce chrome en blanc à
		// l'impression — sans les quatre règles de §7, on imprimerait un liseré
		// blanc sur blanc (≈ 1,04:1) et la hachure disparaîtrait. Aucun gabarit
		// n'émet aujourd'hui de pastille dans l'ardoise : la sonde la pose dans la
		// cascade réelle de la page servie.
		const surSombre = await page.evaluate( () => {
			const hote = document.querySelector( '.sur-sombre' );
			if ( ! hote ) {
				return null;
			}
			const span = document.createElement( 'span' );
			span.className = 'statut__marque pastille pastille--interdit';
			hote.append( span );
			const s = getComputedStyle( span );
			const releve = { bordure: s.borderTopColor, motif: s.backgroundImage };
			span.remove();
			return releve;
		} );
		assert( surSombre !== null, `${ format.nom } : un chrome .sur-sombre existe sur la page`, 'un .sur-sombre', 'aucun' );
		if ( surSombre ) {
			egal( CHARBON, surSombre.bordure, `${ format.nom } : sous .sur-sombre, le liseré imprimé redevient charbon (jamais blanc sur blanc)` );
			assert(
				surSombre.motif.includes( CHARBON ) && ! surSombre.motif.includes( CALCAIRE ),
				`${ format.nom } : sous .sur-sombre, la hachure imprimée redevient charbon`,
				`un gradient en ${ CHARBON }`,
				surSombre.motif.slice( 0, 120 )
			);
		}

		await contexte.close();
	}
}

async function s19_modeCartesEtCellulesVides( navigateur ) {
	scenario( '19 — mode cartes à 320 px, et le piège des cellules vides étiquetées' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { viewport: { width: 320, height: 900 }, javaScriptEnabled: false } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	// L'en-tête de colonnes n'est PLUS retiré : depuis l'issue #28 il est DÉPORTÉ
	// hors cadre (`position: absolute`, boîte de --esp-3xs, `overflow: hidden`,
	// `clip-path: inset(50%)`), pour que ses quatre `columnheader` reviennent dans
	// l'arbre d'accessibilité — mesuré par le scénario « arbre ». L'ancienne
	// attente `thead: 'none'` affirmait le comportement que le contrat #28 a
	// délibérément changé : elle était devenue fausse, pas le code.
	// `display: block` est ce qui rend `overflow` applicable (il est sans effet sur
	// une boîte interne de tableau) ; c'est lui, et non `clip-path`, qui porte le
	// « pas de défilement horizontal à 320 px » du brief §8.
	const mode = await page.evaluate( () => {
		const thead = document.querySelector( '.liste-statuts__tableau thead' );
		const style = getComputedStyle( thead );
		const rect = thead.getBoundingClientRect();
		return {
			tableau: getComputedStyle( document.querySelector( '.liste-statuts__tableau' ) ).display,
			thead: style.display,
			theadPosition: style.position,
			theadClipPath: style.clipPath,
			theadOverflow: style.overflow,
			tbody: getComputedStyle( document.querySelector( '.liste-statuts__tableau tbody' ) ).display,
			ligne: getComputedStyle( document.querySelector( '.liste-statuts__ligne' ) ).display,
			theadBoite: { largeur: Math.round( rect.width ), droite: Math.round( rect.right ) },
			scroll: document.scrollingElement.scrollWidth,
			fenetre: document.scrollingElement.clientWidth,
		};
	} );
	egal(
		{ tableau: 'block', thead: 'block', tbody: 'block', ligne: 'block' },
		{ tableau: mode.tableau, thead: mode.thead, tbody: mode.tbody, ligne: mode.ligne },
		'à 320 px, la liste est en cartes empilées : c’est la base mobile-first'
	);
	egal(
		{ position: 'absolute', clipPath: 'inset(50%)', overflow: 'hidden' },
		{ position: mode.theadPosition, clipPath: mode.theadClipPath, overflow: mode.theadOverflow },
		'l’en-tête est déporté hors cadre, jamais retiré (contrat #28, invariant I-11)'
	);
	assert(
		mode.theadBoite.largeur <= 2 && mode.theadBoite.droite <= mode.fenetre,
		'la boîte du déport tient dans 2 px et ne dépasse pas la fenêtre',
		'largeur ≤ 2 px et bord droit ≤ 320 px',
		`largeur ${ mode.theadBoite.largeur } px, bord droit ${ mode.theadBoite.droite } px`
	);
	egal(
		mode.fenetre,
		mode.scroll,
		'le déport n’ajoute pas un pixel de défilement horizontal (brief §8, égalité stricte)'
	);

	// Risque résiduel nommé par le contrat #28 : un élément déporté hors cadre qui
	// deviendrait focusable renverrait le curseur du clavier dans une zone
	// invisible. Le parcours au clavier ne doit jamais s'y arrêter.
	const parcours = [];
	for ( let i = 0; i < 12; i += 1 ) {
		await page.keyboard.press( 'Tab' );
		parcours.push(
			await page.evaluate( () => {
				const a = document.activeElement;
				if ( ! a ) {
					return 'null';
				}
				return a.closest( 'thead' ) ? `DANS LE THEAD : ${ a.tagName.toLowerCase() }` : a.tagName.toLowerCase();
			} )
		);
	}
	egal(
		[],
		parcours.filter( ( e ) => e.startsWith( 'DANS LE THEAD' ) ),
		'l’en-tête déporté ne reçoit jamais le focus au clavier (12 tabulations)'
	);

	// L'étiquette de carte remplace l'en-tête disparu : elle doit reproduire
	// exactement `data-etiquette`, sur toute cellule qui le porte et qui reste
	// affichée.
	const etiquettes = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.liste-statuts__cellule[data-etiquette]' ) ]
			.filter( ( e ) => getComputedStyle( e ).display !== 'none' )
			.map( ( e ) => ( {
				attendu: e.getAttribute( 'data-etiquette' ),
				rendu: getComputedStyle( e, '::before' ).content.replace( /^"|"$/g, '' ),
				valeur: e.textContent.trim(),
			} ) )
	);
	egal(
		[],
		etiquettes.filter( ( e ) => e.rendu !== e.attendu ).map( ( e ) => `${ e.attendu } → ${ e.rendu }` ),
		'chaque cellule affichée porte son étiquette, reprise de data-etiquette'
	);

	// LE PIÈGE. `.liste-statuts__cellule:empty` ne masque une cellule que si elle
	// est LITTÉRALEMENT vide — pas un espace, pas un saut de ligne. Cela ne tient
	// que parce que PHP avale le saut de ligne après chaque `?>` dans
	// liste-statuts.php. Une réindentation de ce gabarit rendrait, en silence,
	// autant de champs étiquetés vides qu'il y a de ZAPEF absentes.
	egal(
		[],
		etiquettes.filter( ( e ) => e.valeur === '' ).map( ( e ) => e.attendu ),
		'aucun champ étiqueté vide : `:empty` fait bien son office en mode cartes'
	);

	const vides = await page.evaluate( () => {
		const cellules = [ ...document.querySelectorAll( '.liste-statuts__cellule' ) ];
		const sansContenu = cellules.filter( ( e ) => e.childNodes.length === 0 );
		return {
			total: cellules.length,
			sansContenu: sansContenu.length,
			affichees: sansContenu.filter( ( e ) => getComputedStyle( e ).display !== 'none' ).length,
			// Une cellule qui contient un seul nœud texte blanc : le symptôme
			// EXACT d'une réindentation du gabarit.
			blanches: cellules.filter( ( e ) => e.childNodes.length > 0 && e.textContent.trim() === '' ).length,
		};
	} );
	assert(
		vides.sansContenu > 0,
		'le jeu d’essai contient bien des cellules vides (sinon la règle ne serait pas éprouvée)',
		'> 0',
		vides.sansContenu
	);
	egal( 0, vides.affichees, 'toutes les cellules vides sont masquées en mode cartes' );
	egal(
		0,
		vides.blanches,
		'aucune cellule ne contient d’espace résiduel — le gabarit n’a pas été réindenté (invariant I-8)'
	);
	note( `cellules : ${ vides.total } dont ${ vides.sansContenu } littéralement vides` );

	// Le même invariant, sur les octets réellement servis : c'est le contrôle qui
	// survivra à un changement de CSS.
	const html = await ( await contexte.request.get( BASE + '/' ) ).text();
	const videsServies = ( html.match( /data-etiquette="[^"]+"><\/td>/g ) ?? [] ).length;
	const blanchesServies = ( html.match( /data-etiquette="[^"]+">\s+<\/td>/g ) ?? [] ).length;
	assert( videsServies > 0, 'HTML servi : des cellules sont vides sans le moindre octet d’espace', '> 0', videsServies );
	egal( 0, blanchesServies, 'HTML servi : aucune cellule ne contient d’espace entre ses balises' );

	// Au-dessus de --bp-s, les cellules vides DOIVENT revenir : les masquer
	// décalerait toutes les colonnes suivantes de la ligne.
	const large = await navigateur.newContext( { viewport: { width: 900, height: 900 }, javaScriptEnabled: false } );
	const pageLarge = await large.newPage();
	await pageLarge.goto( BASE + '/', { waitUntil: 'load' } );
	const colonnes = await pageLarge.evaluate( () => {
		const cellules = [ ...document.querySelectorAll( '.liste-statuts__cellule' ) ];
		const vide = cellules.find( ( e ) => e.childNodes.length === 0 );
		const lignes = [ ...document.querySelectorAll( '.liste-statuts__ligne:not(.liste-statuts__ligne--entete):not(.liste-statuts__ligne--hors-niveau)' ) ];
		return {
			tableau: getComputedStyle( document.querySelector( '.liste-statuts__tableau' ) ).display,
			videAffichee: vide ? getComputedStyle( vide ).display : 'AUCUNE CELLULE VIDE',
			etiquette: getComputedStyle( document.querySelector( '.liste-statuts__cellule[data-etiquette]' ), '::before' ).content,
			// Toutes les lignes doivent aligner leurs colonnes : une cellule
			// masquée se verrait immédiatement ici.
			gauches: [ ...new Set( lignes.map( ( l ) => [ ...l.children ].map( ( c ) => Math.round( c.getBoundingClientRect().left ) ).join( '|' ) ) ) ],
		};
	} );
	egal( 'table', colonnes.tableau, 'au-dessus de --bp-s, les vraies colonnes sont restaurées' );
	egal( 'table-cell', colonnes.videAffichee, 'une cellule vide reste une cellule : les colonnes ne se décalent pas' );
	egal( 'none', colonnes.etiquette, 'les étiquettes de carte disparaissent en mode colonnes' );
	egal( 1, colonnes.gauches.length, 'les 25 lignes alignent toutes leurs colonnes sur les mêmes abscisses' );
	await large.close();

	await contexte.close();
}

/**
 * Arbre d'accessibilité RÉELLEMENT construit par le moteur, via CDP.
 *
 * `page.accessibility.snapshot()` a été retiré de Playwright 1.59 : le protocole
 * de débogage de Chromium est désormais le seul accès à l'arbre tel que le
 * moteur l'expose aux technologies d'assistance. On ne déduit rien du balisage.
 *
 * @param {import('playwright-core').Page} page Page chargée.
 * @return {Promise<object[]>} Nœuds, avec leur rôle calculé.
 */
async function arbreAccessibilite( page ) {
	const cdp = await page.context().newCDPSession( page );
	await cdp.send( 'Accessibility.enable' );
	const { nodes } = await cdp.send( 'Accessibility.getFullAXTree' );
	await cdp.detach();
	return nodes
		.filter( ( n ) => ! n.ignored )
		.map( ( n ) => ( { role: n.role?.value ?? '', nom: n.name?.value ?? '' } ) );
}

async function s20_arbreAccessibiliteEnCartes( navigateur ) {
	scenario( '20 — arbre d’accessibilité en mode cartes (en-tête déporté, jamais retiré)' );
	poserEtat( 'jour-nominal' );

	/**
	 * Le sous-ensemble tabulaire de l'arbre, à une largeur donnée.
	 *
	 * @param {number} largeur Largeur de fenêtre.
	 * @return {Promise<object>} Comptes par rôle, plus les noms des en-têtes.
	 */
	async function releverArbre( largeur ) {
		const contexte = await navigateur.newContext( { viewport: { width: largeur, height: 900 } } );
		const page = await contexte.newPage();
		await page.goto( BASE + '/', { waitUntil: 'load' } );
		const aplati = await arbreAccessibilite( page );
		const roles = aplati.reduce( ( acc, n ) => ( { ...acc, [ n.role ]: ( acc[ n.role ] ?? 0 ) + 1 } ), {} );
		note( `rôles exposés à ${ largeur } px : ${ Object.entries( roles ).map( ( [ r, n ] ) => `${ r }×${ n }` ).join( ' · ' ) }` );
		const tabulaire = ( source ) => ( {
			table: source.table ?? 0,
			caption: source.caption ?? 0,
			rowgroup: source.rowgroup ?? 0,
			row: source.row ?? 0,
			columnheader: source.columnheader ?? 0,
			rowheader: source.rowheader ?? 0,
		} );
		return {
			contexte,
			page,
			roles,
			tabulaire: tabulaire( roles ),
			cell: roles.cell ?? 0,
			entetes: aplati.filter( ( n ) => n.role === 'columnheader' ).map( ( n ) => n.nom ),
		};
	}

	// ---- 320 px : mode cartes. Depuis l'issue #28, le `thead` est DÉPORTÉ hors
	// cadre et non plus retiré : ses quatre `columnheader` sont de retour dans
	// l'arbre. Un rôle ARIA explicite survit à un changement de `display` ; il ne
	// survit pas à `display: none`, et c'est exactement ce que le correctif a
	// changé. Les assertions sont STRICTES : en `>=`, elles laissaient passer
	// aussi bien le défaut que sa correction, donc ne prouvaient rien.
	const etroit = await releverArbre( 320 );

	egal(
		{ table: 1, caption: 1, rowgroup: 2, row: 26, columnheader: 4, rowheader: 25 },
		etroit.tabulaire,
		'320 px : la structure tabulaire complète est exposée, en-têtes de colonne compris'
	);
	egal( 63, etroit.cell, '320 px : 63 cellules exposées — les 12 cellules vides sont retirées par `:empty`' );
	egal(
		[ 'MASSIF', 'NIVEAU D\'ACCÈS', 'ZAPEF', 'FRAÎCHEUR' ],
		etroit.entetes,
		'320 px : les quatre en-têtes portent leur nom accessible (capitales venues du text-transform)'
	);

	// ---- 900 px : contre-épreuve en mode colonnes. Le sous-ensemble
	// {table, caption, rowgroup, row, columnheader, rowheader} doit être
	// STRICTEMENT IDENTIQUE aux deux largeurs — c'est l'énoncé fort du contrat
	// #28. `cell` en est exclu, et lui seul : `.liste-statuts__cellule:empty
	// { display: none }` (contrat #22, §7) retire les 12 cellules littéralement
	// vides en mode cartes, que le §11 rétablit en `table-cell` — 75 = 63 + 12.
	// Cette asymétrie PRÉEXISTE à #28 (mesurée identique avec et sans le
	// correctif) et la corriger sortirait de l'empreinte. Une attente
	// `cell === 63` écrite pour les deux largeurs serait fausse à 900 px.
	const large = await releverArbre( 900 );

	egal( etroit.tabulaire, large.tabulaire, 'le sous-ensemble tabulaire est identique à 320 px et à 900 px (cell exclu)' );
	egal( 75, large.cell, '900 px : 75 cellules exposées — les 12 cellules vides redeviennent des cellules' );
	egal( etroit.entetes, large.entetes, 'les noms des quatre en-têtes de colonne ne dépendent pas de la largeur' );
	await large.contexte.close();

	// Ce que cette mesure NE prouve PAS, et qu'aucun rapport ne doit arrondir :
	// `Accessibility.getFullAXTree` établit que le NŒUD `columnheader` existe et
	// n'est pas ignoré. Il n'établit rien de l'ASSOCIATION en-tête ↔ cellule,
	// calculée par le moteur et exposée aux technologies d'assistance par les API
	// plateforme, absente de tout champ de l'instantané CDP. Énoncé exact : « le
	// nœud columnheader est rétabli et l'association est rendue possible ».
	note(
		'l’arbre dit que les 4 columnheader existent ; il ne dit rien de l’association ' +
			'en-tête ↔ cellule ni de l’utilisabilité réelle. Seul un contrôle humain au ' +
			'lecteur d’écran peut trancher — il n’a jamais été exécuté sur ce projet.'
	);

	const contexte = etroit.contexte;
	const page = etroit.page;

	// Le texte des cellules, lui, reste lisible : aucune information n'est portée
	// par la seule étiquette générée.
	const contenus = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.liste-statuts__ligne' ) ]
			.slice( 0, 3 )
			.map( ( l ) => l.textContent.replace( /\s+/g, ' ' ).trim() )
	);
	egal(
		[],
		contenus.filter( ( c ) => c === '' ),
		'chaque carte porte son contenu textuel, indépendamment des étiquettes générées'
	);

	await contexte.close();
}

/**
 * Aucune fuite Gravatar — anonymement, en session, et jusque sous `force_display`.
 *
 * CE SCÉNARIO OUVRE DÉLIBÉRÉMENT DEUX VRAIES SESSIONS, et pose donc des cookies
 * `wordpress_logged_in_*` dans ses propres contextes. Ce n'est une contradiction
 * ni avec `s01` ni avec le §2 du brief : l'interdiction de cookie porte sur le
 * VISITEUR ANONYME, ce que la première jambe asserte explicitement. Chaque
 * session vit dans son `newContext()`, dont la fermeture EST la remise à zéro —
 * lancé seul, ce scénario laisse la stack exactement comme il l'a trouvée.
 *
 * Quatre jambes, parce que la fuite avait quatre visages : le visiteur anonyme
 * (à qui notre propre API REST servait l'empreinte de l'administrateur), la
 * couche PHP elle-même (seul endroit où `force_display` s'éprouve, donc seule
 * façon de rendre ce scénario IMPRENABLE par une valeur en base de données),
 * puis les deux comptes réels.
 *
 * Écrans volontairement JAMAIS visités : `plugin-install.php`,
 * `update-core.php`, `theme-install.php`, `about.php`. Ils chargent
 * légitimement des images depuis `ps.w.org` / `s.w.org` ; les visiter rendrait
 * l'assertion d'origine rouge pour une cause que cette issue n'a pas le droit de
 * corriger.
 *
 * @param {import('playwright-core').Browser} navigateur Navigateur.
 */
async function s21_aucuneFuiteGravatar( navigateur ) {
	scenario( '21 — aucune fuite Gravatar : anonyme, en session, sous force_display (§2, §9)' );
	poserEtat( 'jour-nominal' );

	const { createHash } = await import( 'node:crypto' );
	const empreinte = ( courriel ) =>
		createHash( 'sha256' ).update( courriel.trim().toLowerCase() ).digest( 'hex' );

	const COMPTES = [
		{
			login: lireEnv( 'WP_ADMIN_USER', '' ),
			motDePasse: lireEnv( 'WP_ADMIN_PASSWORD', '' ),
			courriel: lireEnv( 'WP_ADMIN_EMAIL', '' ),
			chemins: [ '/', '/wp-admin/', '/wp-admin/profile.php', '/wp-admin/users.php' ],
		},
		{
			login: lireEnv( 'WP_MANAGER_USER', '' ),
			motDePasse: lireEnv( 'WP_MANAGER_PASSWORD', '' ),
			courriel: lireEnv( 'WP_MANAGER_EMAIL', '' ),
			// Pas de `users.php` : le rôle n'a pas `list_users`, l'écran répondrait
			// 403 — ce qui n'apprendrait rien de la fuite.
			chemins: [ '/', '/wp-admin/', '/wp-admin/profile.php' ],
		},
	];

	// Validation de la CONFIGURATION du scénario, avant qu'une seule empreinte ne
	// soit calculée. `lireEnv()` se replie sur '' quand la clé manque, et
	// `empreinte( '' )` est une empreinte parfaitement valide qui n'apparaîtra
	// jamais nulle part : les deux assertions « aucune empreinte de
	// l'administrateur / du gestionnaire » passeraient au vert en ne prouvant
	// rien. Ce n'est pas une cinquième garde anti-faux-vert — les quatre gelées
	// par le contrat portent toutes sur la réalité de la session — c'est la
	// vérification que le test dispose de ses propres entrées, et son absence doit
	// être ROUGE, jamais un saut silencieux. Aucune valeur par défaut n'est
	// recopiée depuis `docker-compose.yml` ni `provision.sh` : ce serait une
	// seconde source de vérité, exactement ce qu'on évite. Les identifiants et
	// mots de passe, eux, n'ont pas besoin de la même garde : vides ou faux, la
	// connexion échoue et c'est la garde de cookie — ou l'attente de sortie de
	// `wp-login.php` — qui devient rouge.
	for ( const [ qui, compte ] of [
		[ 'administrateur', COMPTES[ 0 ] ],
		[ 'gestionnaire', COMPTES[ 1 ] ],
	] ) {
		assert(
			typeof compte.courriel === 'string' &&
				compte.courriel.trim() !== '' &&
				compte.courriel.includes( '@' ),
			`configuration : l'adresse du compte ${ qui } est bien lue dans .env`,
			'une adresse non vide contenant « @ »',
			compte.courriel === '' ? '(clé absente de .env)' : String( compte.courriel )
		);
	}

	// Recalculées, jamais recopiées : une empreinte en dur mentirait le jour où
	// l'adresse du compte change. `wapuu@wordpress.example` est l'auteur du
	// commentaire de graine du cœur, dont l'empreinte fuitait par le widget
	// « Activité » du tableau de bord.
	const EMPREINTES = [
		[ 'administrateur', empreinte( COMPTES[ 0 ].courriel ) ],
		[ 'gestionnaire', empreinte( COMPTES[ 1 ].courriel ) ],
		[ 'auteur du commentaire de graine', empreinte( 'wapuu@wordpress.example' ) ],
	];
	note( `empreintes recalculées depuis .env : ${ EMPREINTES.map( ( [ q, h ] ) => `${ q } → ${ h }` ).join( ' · ' ) }` );

	/**
	 * Corps REST débarrassé du CONTENU ÉDITORIAL.
	 *
	 * Le commentaire de graine du cœur cite lui-même
	 * `<a href="https://gravatar.com/">Gravatar</a>` dans son texte. C'est du
	 * contenu de démonstration, pas une URL d'avatar : il n'émet aucune requête,
	 * il n'est rendu sur aucune page publique du thème, et le corriger
	 * demanderait de toucher la base de démonstration — hors empreinte de cette
	 * issue. Seul le balayage « gravatar » ignore ces champs ; les balayages
	 * d'empreinte portent, eux, sur le corps entier.
	 *
	 * @param {string} texte Corps servi.
	 * @return {string} Corps sans les valeurs `rendered`.
	 */
	const sansContenuEditorial = ( texte ) =>
		texte.replace( /"rendered":"(?:[^"\\]|\\.)*"/g, '"rendered":""' );

	/**
	 * Les trois balayages de contenu, appliqués à toute surface servie.
	 *
	 * @param {string} etiquette Surface observée.
	 * @param {string} texte     Corps ou HTML servi.
	 * @param {string} pourMot   Variante fouillée par le balayage « gravatar ».
	 */
	function balayerTexte( etiquette, texte, pourMot = texte ) {
		const mots = pourMot.match( /gravatar/gi ) ?? [];
		assert(
			mots.length === 0,
			`${ etiquette } : aucune occurrence « gravatar »`,
			'aucune',
			`${ mots.length } occurrence(s)`
		);

		for ( const [ qui, hex ] of EMPREINTES ) {
			assert(
				! texte.includes( hex ),
				`${ etiquette } : aucune empreinte de ${ qui }`,
				'absente',
				hex
			);
		}

		// Générique, et les correspondances sont listées : une empreinte d'un
		// compte créé après coup serait attrapée ici, et diagnosticable.
		const hexs = [ ...new Set( texte.match( /\b[0-9a-f]{64}\b/gi ) ?? [] ) ];
		egal( [], hexs, `${ etiquette } : aucune chaîne de 64 hexadécimaux servie` );
	}

	/**
	 * Les deux balayages de réseau, plus la trace §11 des origines contactées.
	 *
	 * @param {string}   etiquette Surface observée.
	 * @param {object[]} requetes  Requêtes réellement émises.
	 * @param {string[]} echecs    Requêtes en échec.
	 */
	function balayerReseau( etiquette, requetes, echecs ) {
		const origines = new Map();
		for ( const r of requetes ) {
			const origine = new URL( r.url ).origin;
			origines.set( origine, ( origines.get( origine ) ?? 0 ) + 1 );
		}
		const tierces = [ ...origines.keys() ].filter( ( o ) => o !== ORIGINE );
		egal( [], tierces, `${ etiquette } : aucune origine tierce CONTACTÉE par le navigateur` );
		assert( echecs.length === 0, `${ etiquette } : aucune requête en échec`, '[]', echecs.join( ', ' ) );
		note(
			`${ etiquette } : ${ requetes.length } requêtes — ${ [ ...origines.entries() ]
				.map( ( [ o, n ] ) => `${ o } (${ n })` )
				.join( ' · ' ) }`
		);
	}

	// ---- jambe 1 : le visiteur anonyme
	const anonyme = await navigateur.newContext();
	try {
		const { page, requetes, echecs } = await charger( anonyme, '/' );
		balayerReseau( 'anonyme /', requetes, echecs );
		balayerTexte( 'anonyme /', await page.content() );
		egal(
			[],
			( await anonyme.cookies() ).map( ( c ) => c.name ),
			'aucun cookie posé pour le visiteur anonyme'
		);
		await page.close();

		for ( const route of [ '/wp-json/wp/v2/users', '/wp-json/wp/v2/comments' ] ) {
			const reponse = await anonyme.request.get( BASE + route, { failOnStatusCode: false } );
			egal( 200, reponse.status(), `anonyme ${ route } : la route reste servie` );
			const corps = await reponse.text();
			balayerTexte( `anonyme ${ route }`, corps, sansContenuEditorial( corps ) );

			// Le champ lui-même a disparu, pas seulement sa valeur : c'est ce que
			// le filtre `option_show_avatars` retire du SCHÉMA (contrat #25,
			// filtre 2). Le balayage d'empreinte ci-dessus resterait vert si la
			// clé revenait avec des URL vides ; celui-ci ne le resterait pas.
			const cle = route.endsWith( 'users' ) ? 'avatar_urls' : 'author_avatar_urls';
			assert(
				! corps.includes( cle ),
				`anonyme ${ route } : la clé ${ cle } a disparu de la charge utile`,
				'absente',
				'présente'
			);
			const schema = await anonyme.request.fetch( BASE + route, { method: 'OPTIONS', failOnStatusCode: false } );
			const declare = await schema.text();
			assert(
				! declare.includes( cle ),
				`anonyme ${ route } : la clé ${ cle } a disparu du schéma REST (OPTIONS)`,
				'absente',
				'présente'
			);

			if ( route.endsWith( 'users' ) ) {
				// Non-régression INVERSE : l'énumération d'utilisateurs est un défaut
				// DISTINCT, hors périmètre. On asserte qu'on ne l'a PAS corrigée au
				// passage — sans quoi un vert masquerait un débordement.
				const liste = JSON.parse( corps );
				assert(
					Array.isArray( liste ) && liste.length > 0,
					`anonyme ${ route } : liste toujours les mêmes utilisateurs — l'énumération n'a pas été corrigée ici`,
					'au moins un utilisateur',
					corps.slice( 0, 120 )
				);
			}
		}
	} finally {
		await anonyme.close();
	}

	// ---- jambe 2 : la couche PHP, seul endroit où `force_display` s'éprouve
	const code = `echo wp_json_encode( array(
		'home'              => home_url(),
		'option'            => get_option( 'show_avatars' ),
		'option_falsy'      => ! get_option( 'show_avatars' ),
		'url_id'            => get_avatar_url( 1 ),
		'url_courriel'      => get_avatar_url( '${ COMPTES[ 0 ].courriel }' ),
		'balise'            => get_avatar( 1 ),
		'balise_forcee'     => get_avatar( 1, array( 'force_display' => true ) ),
		'url_defaut_force'  => get_avatar_url( 1, array( 'force_default' => true ) ),
	) );`;
	const brut = wp( [ 'eval', code ] );
	const releve = JSON.parse( brut.slice( brut.indexOf( '{' ), brut.lastIndexOf( '}' ) + 1 ) );

	// Sanity : sans elle, un relevé vide se lirait « tout est vide », donc vert.
	egal( BASE, releve.home, 'wp-cli a bien amorcé le site visé (garde du relevé PHP)' );
	assert(
		releve.option_falsy === true,
		'get_option( "show_avatars" ) est faux côté PHP',
		'faux',
		JSON.stringify( releve.option )
	);
	egal( '', releve.url_id, 'get_avatar_url( 1 ) ne compose aucune URL' );
	egal( '', releve.url_courriel, 'get_avatar_url( <courriel> ) ne compose aucune URL' );
	egal( false, releve.balise, 'get_avatar( 1 ) ne rend aucune balise' );
	egal(
		false,
		releve.balise_forcee,
		'get_avatar( 1, force_display ) reste false — la coupe est imprenable par un réglage en base'
	);
	egal( '', releve.url_defaut_force, 'get_avatar_url( 1, force_default ) ne compose aucune URL' );

	// ---- jambes 3 et 4 : les deux comptes réels
	//
	// LE SECOND FACTEUR EST ARMÉ SUR L'ADMINISTRATEUR, ET C'EST NÉCESSAIRE.
	//
	// Défaut trouvé le 15 août 2026, à la passe d'intégration du lot de l'Épic 5 :
	// depuis la rampe d'enrôlement de #13, un administrateur EXIGÉ mais NON ENRÔLÉ
	// est redirigé vers `profile.php#massifs-2fa` sur TOUT écran d'administration.
	// `page.goto()` suit la redirection, `reponse.status()` rend le 200 de
	// `profile.php`, et l'assertion « users.php : servie » passait au vert SANS
	// QUE `users.php` NE SOIT JAMAIS CHARGÉE — le balayage Gravatar de cet écran
	// mesurait `profile.php` une seconde fois. Un faux vert, pas un échec.
	//
	// Deux corrections, l'une et l'autre nécessaires : on enrôle réellement
	// l'administrateur avec le secret de recette — ce qui ÉPROUVE la 2FA au lieu
	// de la contourner par `MASSIFS_DESACTIVER_2FA` —, et on asserte désormais que
	// l'URL finale est bien celle qu'on a demandée.
	enrolerTotp( COMPTE_ADMIN.login );

	try {
	for ( const compte of COMPTES ) {
		const contexte = await navigateur.newContext();
		try {
			const trace = await connexion( contexte, compte );

			if ( compte.login === COMPTE_ADMIN.login ) {
				assert(
					trace.etape2,
					'admin : la connexion traverse réellement l’étape 2 du second facteur',
					'wp-login.php?action=massifs_2fa',
					trace.destination
				);
			}

			// Les quatre gardes anti-faux-vert, TOUTES avant la moindre assertion de
			// fuite : une connexion silencieusement ratée produirait un « aucun
			// gravatar » trivialement vert.
			const cookies = await contexte.cookies();
			assert(
				cookies.some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
				`${ compte.login } : cookie wordpress_logged_in_* posé`,
				'wordpress_logged_in_*',
				cookies.map( ( c ) => c.name ).join( ', ' ) || '(aucun cookie)'
			);

			const accueil = await charger( contexte, '/' );
			egal( 1, await accueil.page.locator( '#wpadminbar' ).count(), `${ compte.login } : la barre d'administration est bien rendue sur le front` );
			egal( 1, await accueil.page.locator( 'body.logged-in' ).count(), `${ compte.login } : le corps porte la classe logged-in` );
			const nom = await texteSource( accueil.page.locator( '#wp-admin-bar-my-account .display-name' ).first() );
			assert( nom !== '', `${ compte.login } : le nom d'affichage reste écrit en toutes lettres`, 'un nom', '(vide)' );

			const profil = await charger( contexte, '/wp-admin/profile.php' );
			egal( 200, profil.statut, `${ compte.login } : profile.php répond 200` );
			assert(
				! profil.page.url().includes( 'wp-login.php' ),
				`${ compte.login } : profile.php n'a pas rebasculé sur l'écran de connexion`,
				'une URL d’administration',
				profil.page.url()
			);
			egal(
				compte.login,
				await profil.page.locator( 'input#user_login' ).inputValue(),
				`${ compte.login } : profile.php est bien celui du compte attendu`
			);
			await accueil.page.close();
			await profil.page.close();

			// Ces pages sont balayées sur leur HTML ENTIER, sans l'exclusion
			// `sansContenuEditorial()` dont les corps REST ont besoin : aucune ne
			// rend le texte du commentaire de graine, lequel cite « Gravatar ».
			// Nuance sur `/wp-admin/` : le widget « Activité » en rend un extrait
			// (`get_comment_excerpt()` — 20 mots, puis `strip_tags`) qui, sur le
			// texte de graine du cœur, s'arrête JUSTE AVANT le mot « Gravatar ». Le
			// vert de cette page tient donc aussi à `comment_excerpt_length`, pas
			// seulement à notre coupe. Le risque résiduel est un FAUX ROUGE, jamais
			// une fuite manquée : si un jour cette assertion vire au rouge sur un
			// « Gravatar » venu du texte éditorial du commentaire, la réponse
			// correcte est d'étendre l'exclusion éditoriale à cette surface — jamais
			// d'affaiblir les balayages d'empreinte, qui sont ceux qui prouvent
			// réellement le correctif.
			for ( const chemin of compte.chemins ) {
				const { page: vue, requetes, echecs, statut } = await charger( contexte, chemin );
				const etiquette = `${ compte.login } ${ chemin }`;
				assert( statut === 200, `${ etiquette } : servie`, 200, statut );
				// GARDE ANTI-FAUX-VERT N° 5 : `page.goto()` suit les redirections et
				// rend 200 pour la page d'ARRIVÉE. Sans cette assertion, une page
				// détournée — par la rampe 2FA, par une réauthentification, par un
				// futur garde de menu — serait balayée à la place de la page visée, et
				// le balayage passerait au vert en n'ayant rien regardé. C'est
				// exactement ce qui s'est produit sur `users.php` avant l'enrôlement
				// de l'administrateur, l. 2680.
				const arrivee = new URL( vue.url() ).pathname + new URL( vue.url() ).search;
				egal( chemin, arrivee, `${ etiquette } : c'est bien CETTE page qui a été chargée, sans redirection silencieuse` );
				balayerReseau( etiquette, requetes, echecs );
				balayerTexte( etiquette, await vue.content() );
				await vue.close();
			}
		} finally {
			// Fermer le contexte détruit les cookies de session : c'est la remise à
			// zéro, et ce qui rend le scénario autonome.
			await contexte.close();
		}
	}
	} finally {
		// Le second facteur de recette est retiré : la stack repart exactement comme
		// elle est arrivée, et l'administrateur n'est pas laissé enrôlé sur un secret
		// écrit dans un fichier du dépôt.
		retirerTotp( COMPTE_ADMIN.login );
		purgerEcluse();
	}
}

/**
 * Les huit journées éprouvées par le scénario « partielle ».
 *
 * Chaque ligne porte la phrase ATTENDUE écrite en toutes lettres, jamais
 * recomposée par une règle d'accord que le gabarit appliquerait aussi : une
 * expectative qui reproduirait l'algorithme du code ne prouverait rien. Les
 * chiffres sont ensuite confrontés à ceux que le DOMAINE rapporte, de sorte que
 * la fixture ne puisse pas mentir sur l'état qu'elle a posé.
 *
 * Trois axes d'accord INDÉPENDANTS sont couverts à 0, 1 et plusieurs :
 * X = massifs autorisés · Y = massifs renseignés · Z = massifs sans donnée.
 * Y = 0 est impossible par construction — sans aucun massif renseigné l'état
 * global n'est plus `disponible` mais `indisponible`, éprouvé par le scénario 04.
 */
const JOURNEES = [
	{
		mode: [ 'jour-complet', 20 ],
		intitule: 'journée complète, 20 autorisés — le rendu d’avant #26, inchangé',
		partiel: false,
		titre: 'Aujourd’hui, 20 massifs sur 25 sont d’accès autorisé.',
		chiffre: '20',
		total: '/25',
		manque: '',
	},
	{
		mode: [ 'jour-complet', 1 ],
		intitule: 'journée complète, X = 1 — singulier « massif … est »',
		partiel: false,
		titre: 'Aujourd’hui, 1 massif sur 25 est d’accès autorisé.',
		chiffre: '1',
		total: '/25',
		manque: '',
	},
	{
		mode: [ 'jour-complet', 0 ],
		intitule: 'journée complète, X = 0 — les 25 massifs interdits du pic de canicule',
		partiel: false,
		titre: 'Aujourd’hui, 0 massif sur 25 est d’accès autorisé.',
		chiffre: '0',
		total: '/25',
		manque: '',
	},
	{
		mode: [ 'jour-partiel', 1, 1 ],
		intitule: 'publication partielle, 1 seul massif renseigné — X = Y = 1, Z = 24',
		partiel: true,
		titre: 'Aujourd’hui, 1 massif sur 1 renseigné est d’accès autorisé.',
		chiffre: '1',
		total: '/1',
		manque: '24 massifs restent sans information du jour.',
	},
	{
		mode: [ 'jour-partiel', 2, 2 ],
		intitule: 'publication partielle, X = Y = 2 — pluriel sur les deux axes',
		partiel: true,
		titre: 'Aujourd’hui, 2 massifs sur 2 renseignés sont d’accès autorisé.',
		chiffre: '2',
		total: '/2',
		manque: '23 massifs restent sans information du jour.',
	},
	{
		mode: [ 'jour-partiel', 5, 3 ],
		intitule: 'publication partielle, X = 3 sur Y = 5 — le cas courant',
		partiel: true,
		titre: 'Aujourd’hui, 3 massifs sur 5 renseignés sont d’accès autorisé.',
		chiffre: '3',
		total: '/5',
		manque: '20 massifs restent sans information du jour.',
	},
	{
		mode: [ 'jour-partiel', 5, 0 ],
		intitule: 'publication partielle, cas MIXTE X = 0 < Y = 5 — « 0 massif … renseignés … est »',
		partiel: true,
		titre: 'Aujourd’hui, 0 massif sur 5 renseignés est d’accès autorisé.',
		chiffre: '0',
		total: '/5',
		manque: '20 massifs restent sans information du jour.',
	},
	{
		mode: [ 'jour-partiel', 24, 20 ],
		intitule: 'publication partielle, un seul massif manquant — Z = 1, « reste » au singulier',
		partiel: true,
		titre: 'Aujourd’hui, 20 massifs sur 24 renseignés sont d’accès autorisé.',
		chiffre: '20',
		total: '/24',
		manque: '1 massif reste sans information du jour.',
	},
];

async function s22_publicationPartielle( navigateur ) {
	scenario( '22 — journée de publication partielle : dénominateur et mention du manque (§4.2, §5.1, issue #26)' );

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );

	for ( const journee of JOURNEES ) {
		const etat = poserEtat( ...journee.mode );
		const lu = Object.fromEntries(
			[ ...etat.matchAll( /(\w+)=([\w-]+)/g ) ].map( ( m ) => [ m[ 1 ], m[ 2 ] ] )
		);

		// Contrôle préalable : l'état que le DOMAINE rapporte est bien celui que le
		// scénario croit éprouver. Sans lui, une fixture muette rendrait tous les
		// verts suivants sans valeur.
		egal( 'disponible', lu.etat, `${ journee.intitule } : le domaine est en état « disponible »` );
		egal( journee.partiel ? '1' : '0', lu.partiel, `${ journee.intitule } : le domaine signale partiel=${ journee.partiel ? 1 : 0 }` );
		egal( journee.chiffre, lu.autorises, `${ journee.intitule } : le domaine compte ${ journee.chiffre } massif(s) autorisé(s)` );
		egal( journee.total, `/${ lu.renseignes }`, `${ journee.intitule } : le domaine compte ${ journee.total.slice( 1 ) } massif(s) renseigné(s)` );

		const page = await contexte.newPage();
		await page.goto( BASE + '/', { waitUntil: 'load' } );
		const html = await page.content();

		assert(
			! /Fatal error|Warning:|Notice:|UnhandledMatchError/.test( html ),
			`${ journee.intitule } : aucune erreur PHP n’atteint le visiteur`,
			'aucune',
			( /(?:Fatal error|Warning:|Notice:)[^<\n]{0,120}/.exec( html ) ?? [ '' ] )[ 0 ]
		);

		// --- L'ardoise : la phrase, mot pour mot, telle que PHP l'a rendue.
		egal( 1, await page.locator( 'h1' ).count(), `${ journee.intitule } : un seul h1` );
		egal( journee.titre, await texteSource( page.locator( 'h1' ) ), `${ journee.intitule } : phrase de synthèse` );
		egal( journee.chiffre, await texteSource( page.locator( '.ardoise__chiffre-valeur' ) ), `${ journee.intitule } : le chiffre de l’ardoise` );
		egal( journee.total, await texteSource( page.locator( '.ardoise__chiffre-total' ) ), `${ journee.intitule } : le dénominateur de l’ardoise` );

		// --- La mention du manque : présente si et seulement si la journée est
		// partielle, et exacte.
		const mention = page.locator( '.ardoise__publication-partielle' );
		egal(
			journee.manque === '' ? 0 : 1,
			await mention.count(),
			`${ journee.intitule } : ${ journee.manque === '' ? 'aucune' : 'une' } mention de publication partielle`
		);
		if ( journee.manque !== '' ) {
			egal( journee.manque, await texteSource( mention ), `${ journee.intitule } : la mention annonce le bon nombre de massifs sans information` );
			egal( journee.manque.split( ' ' )[ 0 ], lu.sans_donnee, `${ journee.intitule } : ce nombre est celui du domaine` );

			// Emplacement contractuel : APRÈS le h1, AVANT la ligne de fraîcheur.
			// La lire ailleurs changerait ce que le visiteur comprend en premier.
			const ordre = await page.evaluate( () => {
				const enfants = [ ...document.querySelector( '.ardoise__texte' ).children ];
				return enfants.map( ( e ) => e.tagName.toLowerCase() + '.' + ( e.className || '' ) );
			} );
			const iTitre = ordre.findIndex( ( c ) => c.includes( 'ardoise__titre' ) );
			const iManque = ordre.findIndex( ( c ) => c.includes( 'ardoise__publication-partielle' ) );
			const iFraicheur = ordre.findIndex( ( c ) => c.includes( 'ardoise__fraicheur' ) );
			assert(
				iTitre >= 0 && iManque === iTitre + 1 && iFraicheur === iManque + 1,
				`${ journee.intitule } : la mention est entre le h1 et la ligne de fraîcheur`,
				'h1, mention, fraîcheur',
				ordre.join( ' | ' )
			);
			assert(
				( await page.locator( 'h1 .ardoise__publication-partielle' ).count() ) === 0,
				`${ journee.intitule } : la mention n’est PAS logée dans le h1`,
				0,
				await page.locator( 'h1 .ardoise__publication-partielle' ).count()
			);
		}

		// --- Le point de sécurité de l'issue : aucun chiffre de la page ne peut
		// laisser croire que les massifs non renseignés sont autorisés.
		if ( journee.partiel ) {
			assert(
				! journee.titre.includes( `sur ${ lu.total } ` ),
				`${ journee.intitule } : le dénominateur n’est PAS le référentiel entier (${ lu.total })`,
				`jamais « sur ${ lu.total } »`,
				journee.titre
			);
			assert(
				( await texteSource( page.locator( 'h1' ) ) ).includes( 'renseigné' ),
				`${ journee.intitule } : le dénominateur est qualifié « renseigné(s) »`,
				'le mot « renseigné »',
				await texteSource( page.locator( 'h1' ) )
			);
		} else {
			assert(
				! ( await texteSource( page.locator( 'h1' ) ) ).includes( 'renseigné' ),
				`${ journee.intitule } : le mot « renseigné » n’est jamais rendu en journée complète`,
				'aucun « renseigné »',
				await texteSource( page.locator( 'h1' ) )
			);
		}

		// --- La liste textuelle (§5.3) dit la même chose que l'ardoise : elle rend
		// les 25 massifs du référentiel, et ceux qui n'ont pas de statut du jour y
		// portent « information non disponible », jamais un niveau.
		egal( Number( lu.total ), await page.locator( '#liste tbody tr' ).count(), `${ journee.intitule } : la liste rend une ligne par massif du référentiel` );
		// Deux cellules distinctes : une ligne renseignée porte un
		// `--niveau`, une ligne sans statut du jour porte un `--hors-niveau` en
		// `colspan=3` — donc ni niveau, ni ZAPEF, ni fraîcheur à copier de la veille.
		const libelles = await page.locator( '#liste tbody .liste-statuts__cellule--niveau' ).allTextContents();
		const horsNiveau = await page.locator( '#liste tbody .liste-statuts__cellule--hors-niveau' ).allTextContents();
		const comptes = {
			autorise: libelles.filter( ( l ) => l.includes( 'Accès au massif autorisé' ) ).length,
			interdit: libelles.filter( ( l ) => l.includes( 'Accès au massif interdit' ) ).length,
			indisponible: horsNiveau.filter( ( l ) => l.includes( 'information non disponible' ) ).length,
		};
		egal(
			{
				autorise: Number( lu.autorises ),
				interdit: Number( lu.renseignes ) - Number( lu.autorises ),
				indisponible: Number( lu.sans_donnee ),
			},
			comptes,
			`${ journee.intitule } : la liste compte exactement autant d’autorisés, d’interdits et d’indisponibles que le domaine`
		);

		egal( 1, await page.locator( '.bandeau-non-officialite' ).count(), `${ journee.intitule } : le bandeau de non-officialité est présent` );

		await page.close();
	}

	// --- Contrôle d'accessibilité sur une journée partielle : le `<p>` ajouté ne
	// doit rien casser de ce que le scénario 08 vérifie sur une journée complète.
	poserEtat( 'jour-partiel', 5, 3 );
	const axe = resoudre( 'axe-core' );
	const vue = await ( await navigateur.newContext() ).newPage();
	await vue.goto( BASE + '/', { waitUntil: 'load' } );
	await vue.addScriptTag( { path: axe } );
	const resultat = await vue.evaluate( async () => {
		// eslint-disable-next-line no-undef
		return await axe.run( document, { resultTypes: [ 'violations' ] } );
	} );
	egal(
		[],
		resultat.violations
			.filter( ( v ) => v.impact === 'critical' || v.impact === 'serious' )
			.map( ( v ) => `${ v.id } (${ v.impact }) : ${ v.nodes[ 0 ]?.target?.join( ' ' ) }` ),
		'journée partielle : aucune violation axe bloquante'
	);
	egal(
		[],
		resultat.violations.filter( ( v ) => v.id === 'page-has-heading-one' ).map( ( v ) => v.impact ),
		'journée partielle : la règle axe « page-has-heading-one » passe'
	);

	// Débordement horizontal à 360 px : la phrase ajoutée est la plus longue de
	// l'ardoise, c'est donc elle qui déborderait la première.
	await vue.setViewportSize( { width: 360, height: 780 } );
	await vue.goto( BASE + '/', { waitUntil: 'load' } );
	const debordement = await vue.evaluate( () => ( {
		documentWidth: document.documentElement.scrollWidth,
		viewport: window.innerWidth,
	} ) );
	assert(
		debordement.documentWidth <= debordement.viewport,
		'journée partielle à 360 px : aucun défilement horizontal',
		`≤ ${ debordement.viewport } px`,
		`${ debordement.documentWidth } px`
	);
	await vue.context().close();

	// --- Les états qui ne sont PAS `disponible` n'émettent jamais la mention.
	const sansStatut = await navigateur.newContext( { javaScriptEnabled: false } );
	for ( const mode of [ 'absente', 'veille-seule' ] ) {
		poserEtat( mode );
		const p = await sansStatut.newPage();
		await p.goto( BASE + '/', { waitUntil: 'load' } );
		egal( 0, await p.locator( '.ardoise__publication-partielle' ).count(), `${ mode } : aucune mention de publication partielle` );
		egal( 0, await p.locator( '.ardoise__chiffre' ).count(), `${ mode } : aucun chiffre du jour` );
		egal( 1, await p.locator( 'h1' ).count(), `${ mode } : un seul h1` );
		assert(
			( await texteSource( p.locator( 'h1' ) ) ).startsWith( 'Information du jour non disponible.' ),
			`${ mode } : la page annonce l’indisponibilité`,
			'Information du jour non disponible.…',
			await texteSource( p.locator( 'h1' ) )
		);
		await p.close();
	}
	await sansStatut.close();

	await contexte.close();
}

// ---------------------------------------------------------------- lot « carte interactive et fond auto-hébergé »

async function s24_carteInteractive( navigateur ) {
	scenario( '24 — carte interactive : montage, fond auto-hébergé, repli et bascule de jour (issues #7 et #9)' );

	// L'état est celui où le sélecteur de date est RÉELLEMENT exerçable : sans
	// statut pour demain, le bouton reste `aria-disabled` et la bascule ne peut
	// pas être jouée. Les deux journées portent des niveaux différents.
	const etat = poserEtat( 'deux-jours' );
	const total = Number( /total=(\d+)/.exec( etat )?.[ 1 ] ?? -1 );
	const jourCourant = /jour=([\d-]+)/.exec( etat )?.[ 1 ] ?? '';
	const jourSuivant = /demain=([\d-]+)/.exec( etat )?.[ 1 ] ?? '';
	const interditsAujourdhui = Number( /interdits_aujourdhui=(\d+)/.exec( etat )?.[ 1 ] ?? -1 );
	const interditsDemain = Number( /interdits_demain=(\d+)/.exec( etat )?.[ 1 ] ?? -1 );

	const contexte = await navigateur.newContext();
	const { page, requetes } = await charger( contexte, '/' );

	// --- 1. Le montage a réellement eu lieu.
	await page.waitForSelector( '.carte--prete', { timeout: 15000 } ).catch( () => {} );
	egal( 1, await page.locator( '.carte--prete' ).count(), 'contrat #7 §8.2 : la carte est montée et peinte (.carte--prete)' );
	egal( 25, await page.locator( '.carte__massif' ).count(), 'les 25 massifs sont tracés, un <path> par massif du référentiel' );
	egal( total, await page.locator( '.carte__massif' ).count(), 'autant de tracés que de massifs au référentiel' );

	// --- 1 bis. GÉOMÉTRIE RENDUE, pas seulement présence dans le DOM.
	//
	// Ces trois assertions existent parce qu'un défaut critique est passé sous
	// 1233 assertions PHP et 697 de rendu : le <svg> des panes Leaflet était
	// écrasé à une largeur de 0 par le `max-inline-size: 100%` de layout.css
	// (un pane est `position: absolute` sans largeur, donc large de 0). Les 25
	// <path> étaient dans le DOM, bien placés, avec leur `fill` résolu — et pas
	// un pixel n'était peint.
	//
	// Une assertion de présence ne pouvait pas le voir ; la mesure du <svg> le
	// voit. C'est la deuxième ci-dessous qui porte la garde — vérifié en
	// neutralisant le correctif : elle est la SEULE des trois à rougir.
	//
	// La troisième passe dans les deux états, et c'est instructif plutôt
	// qu'inutile : `getBoundingClientRect()` sur un <path> rend sa boîte
	// GÉOMÉTRIQUE, que le viewport qui le porte ait une surface ou non. Elle
	// garde contre un tracé dégénéré, jamais contre un viewport écrasé. Ne pas
	// la prendre pour la sentinelle.
	const geometrie = await page.evaluate( () => {
		const panes = [ ...document.querySelectorAll( '.leaflet-pane' ) ]
			.map( ( p ) => p.querySelector( ':scope > svg' ) )
			.filter( Boolean )
			.map( ( s ) => s.getBoundingClientRect().width );
		const premier = document.querySelector( 'path.carte__massif' );
		const boite = premier ? premier.getBoundingClientRect() : { width: 0, height: 0 };
		return { panes, minPane: panes.length ? Math.min( ...panes ) : 0, massifL: boite.width, massifH: boite.height };
	} );
	assert( geometrie.panes.length >= 2, 'les deux panes sur mesure portent chacun un <svg> de renderer', '≥ 2 <svg>', `${ geometrie.panes.length }` );
	assert( geometrie.minPane > 0, 'le <svg> de chaque pane a une largeur NON NULLE — sans quoi aucun massif n’est peint', '> 0 px', `${ geometrie.minPane } px` );
	assert( geometrie.massifL > 0 && geometrie.massifH > 0, 'un tracé de massif occupe une surface réelle à l’écran', '> 0 × 0 px', `${ Math.round( geometrie.massifL ) } × ${ Math.round( geometrie.massifH ) } px` );

	// --- 2. Contrat #9, F-2 : le repli ne part QU'APRÈS un montage réussi, et
	//        l'attribution ne part JAMAIS (F-3, I-9.4).
	egal( 0, await page.locator( '.carte-secours__repli' ).count(), 'contrat #9 F-2 : le repli statique est retiré après le montage' );
	egal( 1, await page.locator( '.carte-secours__attribution' ).count(), 'contrat #9 F-3 : l’attribution OSM survit au montage — elle n’est jamais retirée ni dupliquée' );

	// F-4 : Leaflet ne pose PAS sa propre attribution. Deux attributions, dont
	// une non maîtrisée sur la toile nue, seraient un défaut de D-24.
	egal( 0, await page.locator( '.leaflet-control-attribution' ).count(), 'contrat #9 F-4 : Leaflet est monté avec attributionControl: false' );
	const liensLeaflet = await page.locator( 'a[href*="leafletjs.com"]' ).count();
	egal( 0, liensLeaflet, 'aucun lien vers leafletjs.com n’est rendu (l’attribution de la bibliothèque n’est pas posée)' );

	// --- 3. Le fond de carte vient de chez nous, et de nulle part ailleurs.
	const tuiles = await page.evaluate( () => [ ...document.querySelectorAll( '.leaflet-tile' ) ].map( ( t ) => t.src ) );
	assert( tuiles.length > 0, 'une couche de tuiles est montée', '> 0 tuile', tuiles.length );
	egal(
		[],
		tuiles.filter( ( u ) => new URL( u ).origin !== ORIGINE ),
		'contrainte #2 : chaque tuile affichée vient de notre origine'
	);
	const tuilesDemandees = requetes.filter( ( r ) => /\/data\/tuiles\/.+\.png/.test( r.url ) );
	assert( tuilesDemandees.length > 0, 'des tuiles ont été réellement demandées au serveur', '> 0', tuilesDemandees.length );
	// Une tuile est-elle un VRAI PNG ? Un 404 mis en cache par le navigateur
	// passerait toutes les assertions d'URL sans qu'un pixel soit peint.
	const uneTuile = await contexte.request.get( tuilesDemandees[ 0 ].url );
	egal( 200, uneTuile.status(), 'la tuile demandée est servie en 200' );
	egal( 'image/png', ( uneTuile.headers()[ 'content-type' ] ?? '' ).split( ';' )[ 0 ], 'la tuile est un PNG' );
	const entete = ( await uneTuile.body() ).subarray( 0, 8 ).toString( 'hex' );
	egal( '89504e470d0a1a0a', entete, 'les octets servis sont bien ceux d’un PNG (signature de fichier)' );

	// F-11 : la pyramide monte à z12, la carte reste plafonnée à z11 — sans quoi
	// un cran de zoom afficherait un fond SANS polygones.
	const zooms = await page.evaluate( () => {
		const ilot = JSON.parse( document.getElementById( 'carte-donnees' ).textContent );
		return { emprise: ilot.emprise.zoom_max, fond: ilot.fond ? ilot.fond.zoom_max : null };
	} );
	egal( 11, zooms.emprise, 'contrat #7 : le plafond de zoom de la carte reste 11' );
	egal( 12, zooms.fond, 'contrat #9 A-7 : la pyramide de tuiles monte à 12, pour la netteté — pas pour un cran de zoom de plus' );

	// --- 4. Aucun statut d’un autre jour rendu comme courant (contrat #7 §12.4).
	//
	// `.carte__jour` existe en DEUX exemplaires — un par jour —, un seul rendu à
	// la fois. On lit donc la phrase RÉELLEMENT AFFICHÉE, jamais le `textContent`
	// de la barre : celui-ci concatènerait les deux dates et le contrôle passerait
	// pour de mauvaises raisons, dans les deux sens.
	const jourAffiche = () =>
		page.evaluate( () => {
			const visibles = [ ...document.querySelectorAll( '.carte__jour' ) ].filter( ( e ) => e.getClientRects().length > 0 );
			return {
				nombre: visibles.length,
				texte: visibles.map( ( e ) => e.textContent.replace( /\s+/g, ' ' ).trim() ).join( ' | ' ),
				jour: visibles.map( ( e ) => e.getAttribute( 'data-jour' ) ).join( ' | ' ),
			};
		} );
	const interdits = () =>
		page.evaluate( () => [ ...document.querySelectorAll( '.carte__massif--interdit' ) ].map( ( e ) => e.getAttribute( 'aria-label' ) ).sort() );

	const avant = await jourAffiche();
	egal( 1, avant.nombre, 'contrat #7 A-1 : une seule phrase de jour est affichée à la fois' );
	assert( avant.texte !== '', 'contrat #7 A-1 : le jour affiché est écrit en toutes lettres, en permanence', 'un libellé', '(vide)' );
	egal( jourCourant, avant.jour, 'au chargement, le jour affiché est le jour COURANT du serveur' );
	note( `libellé de jour au chargement : « ${ avant.texte } »` );

	const interditsAvant = await interdits();
	egal( interditsAujourdhui, interditsAvant.length, `au chargement, ${ interditsAujourdhui } massifs sont peints « interdit » — le compte du serveur` );

	// Bascule vers « Demain ». Trois verrous du contrat #7 A-1 sont éprouvés
	// ensemble : le libellé change, les polygones changent, et le panneau porte la
	// date de validité de CE jour-là.
	const boutonDemain = page.locator( `.carte__jour-bouton[data-bascule="${ jourSuivant }"]` );
	egal( 1, await boutonDemain.count(), 'le sélecteur porte un bouton « Demain » pour le jour suivant du serveur' );
	assert(
		( await boutonDemain.getAttribute( 'aria-disabled' ) ) !== 'true',
		'demain étant publié, le bouton n’est pas neutralisé',
		'aria-disabled absent ou « false »',
		await boutonDemain.getAttribute( 'aria-disabled' )
	);
	await boutonDemain.click();
	await page.waitForTimeout( 200 );

	egal( 'true', await boutonDemain.getAttribute( 'aria-pressed' ), 'après bascule, « Demain » est le bouton pressé' );
	const apres = await jourAffiche();
	egal( 1, apres.nombre, 'après bascule, une seule phrase de jour reste affichée' );
	egal( jourSuivant, apres.jour, 'contrat #7 A-1 : le jour ÉCRIT suit la bascule — jamais une carte de demain sous le mot d’aujourd’hui' );
	assert(
		apres.texte !== avant.texte,
		'la phrase de jour rendue change réellement de date',
		'une phrase différente',
		`${ avant.texte } → ${ apres.texte }`
	);
	note( `libellé de jour après bascule : « ${ apres.texte } »` );

	// Le COMPTE peut coïncider d'un jour à l'autre : c'est l'ENSEMBLE des massifs
	// interdits qui doit changer, sans quoi une bascule sans effet passerait pour
	// une bascule réussie.
	const interditsApres = await interdits();
	egal( interditsDemain, interditsApres.length, `après bascule, ${ interditsDemain } massifs sont peints « interdit » — le compte du serveur pour demain` );
	assert(
		JSON.stringify( interditsAvant ) !== JSON.stringify( interditsApres ),
		'la bascule repeint réellement les polygones : l’ensemble des massifs interdits n’est pas celui d’aujourd’hui',
		'un ensemble différent',
		`${ interditsAvant.join( ', ' ) } / ${ interditsApres.join( ', ' ) }`
	);

	// Verrou 3 : aucune persistance. Un rechargement revient au jour courant.
	await page.reload( { waitUntil: 'networkidle' } );
	await page.waitForSelector( '.carte--prete', { timeout: 15000 } ).catch( () => {} );
	egal(
		'true',
		await page.locator( `.carte__jour-bouton[data-bascule="${ jourCourant }"]` ).getAttribute( 'aria-pressed' ),
		'contrat #7 A-1, verrou 3 : un rechargement revient au jour courant — aucune persistance'
	);

	// --- 5. Accessibilité de la carte : roving tabindex, aria-label, Échap.
	const roving = await page.evaluate( () => {
		const chemins = [ ...document.querySelectorAll( '.carte__massif' ) ];
		return {
			total: chemins.length,
			arrets: chemins.filter( ( c ) => c.getAttribute( 'tabindex' ) === '0' ).length,
			roles: [ ...new Set( chemins.map( ( c ) => c.getAttribute( 'role' ) ) ) ],
			sansNom: chemins.filter( ( c ) => ! ( c.getAttribute( 'aria-label' ) ?? '' ).trim() ).length,
			exemple: chemins[ 0 ].getAttribute( 'aria-label' ),
		};
	} );
	egal( 1, roving.arrets, 'contrat #7 §9 : un SEUL arrêt de tabulation sur les 25 massifs (roving tabindex)' );
	egal( [ 'button' ], roving.roles, 'chaque tracé porte role="button"' );
	egal( 0, roving.sansNom, 'aucun tracé sans nom accessible' );
	assert(
		roving.exemple.includes( '—' ),
		'l’aria-label est « {massif} — {libellé officiel de l’état} » : deux chaînes du serveur, aucun mot ajouté',
		'un tiret cadratin',
		roving.exemple
	);
	note( `exemple d’aria-label : « ${ roving.exemple } »` );

	// Ouverture au clavier, puis Échap : le panneau se ferme, le focus revient
	// sur le massif, et le contour reste visible (contrat #7 §9, A-9).
	await page.locator( '.carte__massif[tabindex="0"]' ).focus();
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 150 );
	egal( 1, await page.locator( '.carte--panneau-ouvert' ).count(), 'Entrée ouvre le panneau du massif' );
	const titrePanneau = await texteSource( page.locator( '.carte__panneau-titre' ) );
	assert( titrePanneau !== '', 'le panneau porte le nom du massif', 'un nom', '(vide)' );

	// Le panneau ne dit JAMAIS le statut d’un autre jour que celui affiché. La
	// mesure a lieu panneau OUVERT — c’est le seul instant où elle a un sens.
	const datePanneau = await page.evaluate( () => {
		const jour = document.querySelector( '.carte__jour-bouton[aria-pressed="true"]' )?.getAttribute( 'data-bascule' );
		const times = [ ...document.querySelectorAll( '.carte__panneau-source time' ) ]
			.filter( ( t ) => t.getClientRects().length > 0 )
			.map( ( t ) => t.getAttribute( 'data-jour' ) );
		return { jour, times };
	} );
	egal( [ datePanneau.jour ], datePanneau.times, 'contrat #7 A-1 : la date de validité montrée par le panneau est celle du jour affiché, et aucune autre' );

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 150 );
	egal( 0, await page.locator( '.carte--panneau-ouvert' ).count(), 'Échap ferme le panneau (aucun piège clavier)' );
	// `className` d'un élément SVG est un `SVGAnimatedString`, pas une chaîne :
	// c'est `getAttribute` qui rend le texte.
	const focusApres = await page.evaluate( () => document.activeElement?.getAttribute?.( 'class' ) ?? '' );
	assert(
		focusApres.includes( 'carte__massif' ),
		'contrat #7 §9 : après Échap, le focus revient sur le massif d’origine',
		'un .carte__massif focusé',
		focusApres
	);

	// --- 6. Constance du pas de hachure entre les zooms (contrat #7 A-13,
	//        assertion de recette 5). Un motif qui s’étire redevient de
	//        l’information portée par la couleur seule, SANS que rien n’ait l’air
	//        cassé. C’est le point le plus dangereux de l’issue #7.
	//
	// La mesure porte sur le pas À L'ÉCRAN, pas sur l'attribut : `carte.js` porte
	// une garde auto-corrective qui réécrit `width`/`height` des `<pattern>` si le
	// renderer venait à poser une échelle. Mesurer l'attribut seul confondrait
	// « pas constant » et « garde jamais déclenchée ». Le pas écran vaut
	// `attribut × (largeur du <svg> / largeur du viewBox)`.
	//
	// Le zoom passe par le CHEMIN RÉEL : les touches `+` et `-` réimplémentées par
	// `carte.js`, `keyboard: false` ayant retiré celui de Leaflet. Le niveau
	// atteint est relu sur les tuiles réellement demandées, jamais supposé.
	const mesurerPas = () =>
		page.evaluate( () => {
			const svg = document.querySelector( '.carte__pane--massifs svg' ) ?? document.querySelector( '.carte__toile svg' );
			const motif = document.querySelector( 'pattern' );
			if ( ! svg || ! motif || ! svg.viewBox?.baseVal?.width || ! svg.width?.baseVal?.value ) {
				return null;
			}
			const echelle = svg.width.baseVal.value / svg.viewBox.baseVal.width;
			const zooms = [ ...document.querySelectorAll( '.leaflet-tile' ) ]
				.map( ( t ) => Number( /\/tuiles\/[^/]+\/(\d+)\//.exec( t.src )?.[ 1 ] ) )
				.filter( ( z ) => Number.isFinite( z ) );
			return {
				attribut: Number( motif.getAttribute( 'width' ) ),
				unites: motif.getAttribute( 'patternUnits' ),
				ecran: Number( ( Number( motif.getAttribute( 'width' ) ) * echelle ).toFixed( 3 ) ),
				zoom: zooms.length ? Math.max( ...zooms ) : null,
			};
		} );

	await page.locator( '.carte__massif[tabindex="0"]' ).focus();
	const pas = [];
	for ( let i = 0; i < 4; i += 1 ) {
		const mesure = await mesurerPas();
		if ( mesure && ! pas.some( ( p ) => p.zoom === mesure.zoom ) ) {
			pas.push( mesure );
		}
		await page.keyboard.press( '+' );
		await page.waitForTimeout( 400 );
	}

	note( `pas de hachure mesuré : ${ pas.map( ( p ) => `z${ p.zoom } → attribut ${ p.attribut }, écran ${ p.ecran } px` ).join( ' · ' ) }` );
	assert( pas.length >= 3, 'contrat #7 A-13 : le pas de hachure a pu être mesuré à au moins trois niveaux de zoom', '≥ 3 niveaux distincts', `${ pas.length }` );
	if ( pas.length >= 3 ) {
		const ecarts = pas.map( ( p ) => Math.abs( p.ecran - pas[ 0 ].ecran ) / pas[ 0 ].ecran );
		assert(
			Math.max( ...ecarts ) <= 0.01,
			`contrat #7 A-13 : le pas de hachure À L’ÉCRAN est constant entre z${ pas[ 0 ].zoom } et z${ pas[ pas.length - 1 ].zoom }`,
			'écart ≤ 1 %',
			`écart max ${ ( Math.max( ...ecarts ) * 100 ).toFixed( 2 ) } % — ${ pas.map( ( p ) => `${ p.ecran }` ).join( ', ' ) }`
		);
		egal(
			[ 'userSpaceOnUse' ],
			[ ...new Set( pas.map( ( p ) => p.unites ) ) ],
			'contrat #7 A-13.1 : patternUnits="userSpaceOnUse" — le défaut objectBoundingBox rendrait la densité proportionnelle à la taille de chaque massif'
		);
	}

	await page.close();
	await contexte.close();
}

async function s25_carteEnEchecEtSansTuiles( navigateur ) {
	scenario( '25 — la carte en échec ne laisse jamais un trou : le repli tient (issues #7 et #9)' );
	poserEtat( 'jour-nominal' );

	// Chemin d'échec du contrat #9 F-2 : Leaflet est empêché de se charger. Le
	// montage échoue, et c'est exactement le cas que `<noscript>` NE couvre PAS —
	// JavaScript est actif, mais la carte ne peut pas naître. Le repli statique
	// doit rester debout : c'est la raison d'être de I-9.1.
	const contexte = await navigateur.newContext();
	await contexte.route( '**/vendor/leaflet/leaflet.js*', ( route ) => route.abort() );

	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );
	await page.waitForTimeout( 800 );

	egal( 0, await page.locator( '.carte--prete' ).count(), 'Leaflet absent : la carte n’est jamais déclarée prête' );
	egal( 1, await page.locator( '.carte-secours__repli' ).count(), 'contrat #9 F-2 : le repli statique est CONSERVÉ quand le montage échoue' );
	egal( 1, await page.locator( '.carte-secours__image' ).count(), 'l’image statique du département reste affichée' );
	egal( 1, await page.locator( '.carte-secours__lien' ).count(), 'le chemin vers la liste textuelle reste offert' );
	egal( 1, await page.locator( '.carte-secours__attribution' ).count(), 'l’attribution du fond reste rendue' );

	// L'information de statut, elle, n'a jamais dépendu de la carte.
	egal( 25, await page.locator( '#liste tbody tr' ).count(), 'les 25 statuts restent lisibles dans la liste, carte ou pas' );
	egal( 1, await page.locator( '.bandeau-non-officialite' ).count(), '§5.6 : le bandeau de non-officialité est là' );

	// Aucune requête de secours vers un domaine tiers n'est tentée quand la
	// carte échoue. C'est le piège du contrat #9 I-9.2 : le cas dégradé, pas le
	// cas nominal.
	const tierces = [];
	page.on( 'request', ( r ) => {
		if ( new URL( r.url() ).origin !== ORIGINE ) {
			tierces.push( r.url() );
		}
	} );
	await page.reload( { waitUntil: 'load' } );
	await page.waitForTimeout( 800 );
	egal( [], tierces, 'contrat #9 I-9.2 : sur le chemin dégradé non plus, aucune origine tierce n’est contactée' );

	await page.close();
	await contexte.close();

	// Second chemin : la métadonnée de fond est là, mais les tuiles manquent.
	// `disponible === true` atteste les métadonnées, JAMAIS le fichier
	// (contrat #9, interdit 8) : une tuile manquante doit se dégrader en trou
	// visuel, jamais en erreur de page.
	const sansTuiles = await navigateur.newContext();
	await sansTuiles.route( '**/data/tuiles/**', ( route ) => route.fulfill( { status: 404, body: '' } ) );
	const p2 = await sansTuiles.newPage();
	const erreurs = [];
	p2.on( 'pageerror', ( e ) => erreurs.push( e.message ) );
	await p2.goto( BASE + '/', { waitUntil: 'load' } );
	await p2.waitForSelector( '.carte--prete', { timeout: 15000 } ).catch( () => {} );

	egal( 1, await p2.locator( '.carte--prete' ).count(), 'tuiles absentes : la carte se monte quand même' );
	egal( 25, await p2.locator( '.carte__massif' ).count(), 'tuiles absentes : les 25 massifs restent tracés — le cœur du site tient' );
	egal( [], erreurs, 'tuiles absentes : aucune erreur JavaScript non rattrapée' );
	egal( 1, await p2.locator( '.carte-secours__attribution' ).count(), 'tuiles absentes : l’attribution reste rendue' );

	await p2.close();
	await sansTuiles.close();
}

async function s26_gardeCartePhpNEmportePasLeRepli( navigateur ) {
	scenario( '26 — une garde PHP de `parts/carte.php` n’emporte plus le repli (contrat #9, F-1)' );
	poserEtat( 'jour-nominal' );

	// Le cœur de la clause F-1, et le seul chemin qu’aucun autre scénario
	// n’atteignait. Les scénarios 24 et 25 éprouvent le RUNTIME : la carte est
	// rendue par PHP, puis JavaScript échoue. Ici, `parts/carte.php` sort par
	// `return` AVANT d’écrire son premier octet — la carte n’existe pas du tout.
	// C’est le cas où le repli était perdu jusqu’au correctif `6be9408`, parce
	// que `massifs_partie( 'carte-secours' )` était la DERNIÈRE ligne du gabarit
	// de carte et se trouvait donc derrière ses huit sorties anticipées.
	//
	// La garde choisie est la n° 3 (« ressources vendorisées ») : elle se déclenche
	// sur un `file_exists()` du disque, elle est donc atteignable sans toucher ni
	// à la base, ni à l’extension, ni au référentiel. Renommer leaflet.js ne
	// simule rien — c’est exactement la panne que la garde décrit.
	//
	// À distinguer du scénario 13 : là-bas, l’extension est coupée, donc
	// `massifs_attribution_fond_de_carte()` disparaît et `carte-secours.php`
	// s’auto-annule sur sa garde d’attribution (I-9.4 : jamais d’image ODbL sans
	// crédit). Son absence y est le comportement CONTRACTUEL. Ici l’extension
	// tourne : l’attribution existe, donc le repli DOIT être rendu.
	const vendor = path.join( RACINE, 'wp-content/themes/massifs/assets/vendor/leaflet/leaflet.js' );
	const mise = `${ vendor }.recette-absente`;

	if ( existsSync( mise ) ) {
		renameSync( mise, vendor );
	}

	const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
	try {
		renameSync( vendor, mise );
		const page = await contexte.newPage();
		const reponse = await page.goto( BASE + '/', { waitUntil: 'load' } );
		const html = await page.content();

		egal( 200, reponse.status(), 'la page publique reste servie sans Leaflet sur le disque' );
		assert(
			! /Fatal error|Warning:|Notice:/.test( html ),
			'aucune erreur PHP n’atteint le visiteur',
			'aucune',
			( /(?:Fatal error|Warning:|Notice:)[^<\n]{0,120}/.exec( html ) ?? [ '' ] )[ 0 ]
		);

		// La garde a bien mordu : sans cela, tout ce qui suit passerait au vert
		// pour la mauvaise raison, en éprouvant le chemin nominal.
		egal( 0, await page.locator( '.carte' ).count(), 'garde 3 : la racine `.carte` n’est pas rendue du tout' );
		egal( 0, await page.locator( 'script[src*="leaflet"]' ).count(), 'garde 3 : aucune URL vendorisée qui répondrait 404 n’est enfilée' );

		// F-1 : le repli survit, en entier, une fois et une seule.
		egal( 1, await page.locator( '#carte .carte-secours' ).count(), 'contrat #9 F-1 : le repli est rendu malgré la sortie anticipée du gabarit de carte' );
		egal( 1, await page.locator( '.carte-secours__repli' ).count(), 'contrat #9 F-1 : l’image de repli survit à la garde' );
		egal( 1, await page.locator( '.carte-secours__attribution' ).count(), 'contrat #9 F-3 : l’attribution OSM survit à la garde, sans duplication' );
		egal( 1, await page.locator( '.carte-secours__lien' ).count(), 'le chemin vers la liste textuelle reste offert' );
		egal( 0, await page.locator( 'noscript' ).count(), 'contrat #9 I-9.1 : toujours aucun <noscript>' );

		// I-9.9 : la bande garde un enfant, donc ses deux filets ne se touchent
		// pas. Observé sur la géométrie réelle, pas sur la feuille de style.
		const hauteur = await page.locator( '#carte' ).evaluate( ( s ) => s.getBoundingClientRect().height );
		assert(
			hauteur > 100,
			'contrat #9 I-9.9 : la bande carte n’est pas réduite à deux filets accolés',
			'hauteur > 100 px',
			`${ Math.round( hauteur ) } px`
		);

		// L’information de statut n’a jamais dépendu de la carte.
		egal( 25, await page.locator( '#liste tbody tr' ).count(), 'les 25 statuts restent lisibles sans la carte' );
		egal( 1, await page.locator( '.bandeau-non-officialite' ).count(), '§5.6 : le bandeau de non-officialité est là' );

		await page.close();
	} finally {
		if ( existsSync( mise ) ) {
			renameSync( mise, vendor );
		}
		await contexte.close();
	}

	const verif = await navigateur.newContext();
	const p = await verif.newPage();
	await p.goto( BASE + '/', { waitUntil: 'load' } );
	await p.waitForSelector( '.carte--prete', { timeout: 15000 } ).catch( () => {} );
	egal( 1, await p.locator( '.carte--prete' ).count(), 'remise en état : leaflet.js est de retour et la carte se monte' );
	egal( 1, await p.locator( '.carte-secours__attribution' ).count(), 'remise en état : une seule attribution, comme sur le chemin nominal' );
	await verif.close();
}

/**
 * Journal du conteneur wordpress depuis un instant donné.
 *
 * `display_errors` est à Off et `error_log` pointe sur `/dev/stderr` : un
 * avertissement PHP n'atteint jamais le visiteur, il n'est lisible que là.
 *
 * @param {string} depuis Horodatage UTC accepté par `docker compose logs --since`.
 * @return {string} Lignes du journal.
 */
function journalConteneur( depuis ) {
	// LES DEUX FLUX, et c'est le point.
	//
	// `docker compose logs` démultiplexe : ce que le conteneur a écrit sur sa
	// sortie standard ressort sur la sortie standard du client, et ce qu'il a
	// écrit sur son erreur standard ressort sur l'erreur standard du client. Or
	// `error_log` d'Apache pointe sur `/dev/stderr` : lire le seul `stdout`
	// d'`execFileSync` ne rendait que le journal d'ACCÈS, jamais un avertissement
	// PHP. L'assertion qui suit était donc rouge quoi qu'il arrive — un rouge qui
	// ne décrivait pas le serveur mais le harnais.
	// `maxBuffer` EXPLICITE, et c'est le second point.
	//
	// La valeur par défaut de `spawnSync` est 1 Mio. Au-delà, Node TRONQUE la
	// sortie et se contente de poser `result.error = ENOBUFS` — que ce harnais
	// ignorait. Le journal rendu était alors le PRÉFIXE le plus ANCIEN de la
	// fenêtre, jamais les lignes que l'appelant vient de provoquer : l'assertion
	// devenait rouge sans que rien ne soit cassé côté serveur, dès que la stack
	// avait assez tourné pour écrire 1 Mio de journal d'accès. Défaut du harnais,
	// à retardement, et invisible — exactement ce qu'un rouge ne doit pas être.
	//
	// 256 Mio est hors d'atteinte pour une fenêtre de quelques secondes ; si la
	// troncature survenait malgré tout, le `ko()` ci-dessous la NOMME au lieu de
	// la laisser se déguiser en absence de l'avertissement attendu.
	const sortie = spawnSync(
		'docker',
		[ 'compose', 'logs', '--since', depuis, 'wordpress' ],
		{
			cwd: RACINE,
			encoding: 'utf8',
			maxBuffer: 256 * 1024 * 1024,
			env: { ...process.env, MSYS_NO_PATHCONV: '1' },
		}
	);

	if ( sortie.error ) {
		ko(
			'journal du conteneur : lecture incomplète',
			'sortie complète de `docker compose logs`',
			`${ sortie.error.code ?? '' } ${ sortie.error.message ?? '' }`.trim()
		);
	}

	return `${ sortie.stdout ?? '' }\n${ sortie.stderr ?? '' }`;
}

/**
 * Horodatage RFC 3339 **en UTC**, accepté sans ambiguïté par `--since`.
 *
 * `toISOString().slice( 0, 19 )` amputait le `Z` final. Privé de fuseau, Docker
 * lit l'horodatage dans le fuseau LOCAL du client : sur une machine à UTC+2, la
 * fenêtre demandée s'ouvrait deux heures trop tôt et ramenait des dizaines de
 * milliers de lignes au lieu de quelques dizaines. Conjugué au `maxBuffer` par
 * défaut ci-dessus, c'est ce qui faisait lire un journal vieux de deux heures.
 *
 * La marge de sécurité couvre la dérive d'horloge entre l'hôte et le démon ; elle
 * reste très inférieure à l'intervalle entre deux exécutions du scénario, donc
 * elle ne peut pas faire passer pour neuf un avertissement d'une exécution
 * précédente.
 *
 * @param {number} margeSecondes Recul appliqué à l'instant courant.
 * @return {string} Horodatage UTC, `Z` compris.
 */
function instantJournal( margeSecondes = 5 ) {
	return new Date( Date.now() - margeSecondes * 1000 ).toISOString();
}

/**
 * Attend que le serveur ait réellement rechargé un gabarit qu'on vient d'écrire.
 *
 * `opcache.validate_timestamps` est à On mais `opcache.revalidate_freq` vaut 2
 * sur cette pile : jusqu'à deux secondes après l'écriture, Apache sert encore le
 * gabarit précédent. Sans cette attente, le scénario mesurerait la page d'AVANT
 * son injection et se croirait vert alors qu'il n'a rien exercé.
 *
 * L'attente ne se cale sur AUCUNE assertion : elle guette la seule disparition
 * du chiffre nominal. Si la garde `try/catch` venait à manquer, le serveur
 * répondrait 500 — qui ne porte pas non plus ce marqueur —, la boucle rendrait
 * la main immédiatement et l'assertion 1 rapporterait honnêtement le 500.
 *
 * @param {boolean} attenduChiffre Attend-on le chiffre présent, ou absent ?
 * @return {Promise<boolean>} Vrai si l'état visé a été atteint avant la limite.
 */
async function attendreRechargement( attenduChiffre ) {
	for ( let essai = 0; essai < 20; essai += 1 ) {
		const reponse = await fetch( BASE + '/' );
		const html = await reponse.text();
		if ( html.includes( 'ardoise__chiffre' ) === attenduChiffre ) {
			return true;
		}
		await new Promise( ( r ) => setTimeout( r, 300 ) );
	}
	return false;
}

/**
 * Recette R-27 — un `etat_global` hors des quatre bras du `match()` de l'ardoise.
 *
 * Le contrat #27 gèle cette recette et la lègue à ce fichier. Aucun chemin de
 * DONNÉE ne peut produire un cinquième état : `etat_global` naît d'une chaîne
 * `if/elseif` locale et fermée de l'extension, qu'aucun `apply_filters` ne
 * traverse. Le seul geste qui l'observe est donc une injection LOCALE et
 * TEMPORAIRE dans `front-page.php`, retirée dans le `finally`, avec assertion de
 * remise en état — même protocole que les scénarios `ancre` et `extension`.
 *
 * Ce que le scénario garde, concrètement : si quelqu'un retire le `try/catch`,
 * les cas 1 et 2 repassent en HTTP 500 + page « erreur critique » du cœur de
 * WordPress — mesuré, pas supposé — et ce scénario devient rouge.
 *
 * @param {import('playwright-core').Browser} navigateur Navigateur.
 */
async function s23_ardoiseEtatInconnu( navigateur ) {
	scenario( '23 — ardoise : etat_global hors des quatre états du gabarit (recette R-27)' );

	const gabarit = path.join( RACINE, 'wp-content/themes/massifs/front-page.php' );
	const ANCRE = "$massifs_peremption = true === $massifs_fraicheur['perimee'];";
	const origine = readFileSync( gabarit );

	// Cas 0 — non-régression : sans injection, le jour nominal reste chiffré. Le
	// hissage de `$massifs_ardoise_absente` ne doit RIEN changer ici.
	poserEtat( 'jour-nominal' );
	const nominal = await navigateur.newContext( { javaScriptEnabled: false } );
	const pageNominale = await nominal.newPage();
	await pageNominale.goto( BASE + '/', { waitUntil: 'load' } );
	egal( 1, await pageNominale.locator( '#ardoise .ardoise__chiffre' ).count(), 'cas 0 — sans injection : l’ardoise porte son chiffre' );
	assert( 0 < await pageNominale.locator( '#ardoise time' ).count(), 'cas 0 — sans injection : la ligne de fraîcheur est rendue', 'au moins un <time>', 0 );

	// L'hôte du lien officiel vient du serveur, jamais d'une URL codée en dur
	// ici : l'assertion 3 compare deux rendus d'une même source.
	const hoteAttendu = new URL(
		await pageNominale.locator( '.bandeau-non-officialite__lien' ).getAttribute( 'href' )
	).host;
	note( `hôte du lien officiel, relevé sur le bandeau : ${ hoteAttendu }` );
	await nominal.close();

	const CAS = [
		[ 'cas 1 — cinquième état', "$massifs_synthese['etat_global'] = 'etat_de_recette_27';" ],
		[ 'cas 2 — clé retirée', "unset( $massifs_synthese['etat_global'] );" ],
	];

	try {
		for ( const [ nom, injection ] of CAS ) {
			const texte = origine.toString( 'utf8' );
			if ( ! texte.includes( ANCRE ) ) {
				ko( `${ nom } : point d’injection introuvable`, ANCRE, 'absent de front-page.php' );
				return;
			}
			// BARRIÈRE MONTANTE, avant chaque cas — sans elle, le cas 2 ne
			// s'observe pas lui-même.
			//
			// `attendreRechargement( false )` attend la DISPARITION du chiffre. Le
			// cas 1 l'a déjà fait disparaître : au cas 2, la condition est vraie dès
			// la première requête, la fonction rend `true` immédiatement, et la
			// barrière ne barre plus rien. Avec `opcache.revalidate_freq=2`, le
			// serveur sert alors encore le bytecode du CAS 1 — dont le rendu est
			// exactement le même (« information non disponible »), si bien que les
			// quinze assertions du cas 2 passent au vert en éprouvant le cas 1.
			// Seule l'assertion du journal distingue les deux : elle attend
			// « Undefined array key », que le cinquième état du cas 1 ne produit
			// pas. Le rouge décrivait donc un vrai trou de la recette — dans la
			// recette, pas dans le serveur : mesuré ci-contre, le gabarit injecté
			// à la main émet bien l'avertissement.
			//
			// On repasse donc par le gabarit d'origine et on attend le RETOUR du
			// chiffre : chaque cas part d'un front franc et observe son propre code.
			writeFileSync( gabarit, origine );
			assert( await attendreRechargement( true ), `${ nom } : le gabarit d’origine est resservi avant injection`, 'chiffre nominal resservi', 'le chiffre nominal n’est pas revenu après 6 s' );

			const depuis = instantJournal();
			writeFileSync( gabarit, texte.replace( ANCRE, `${ ANCRE }\n\t${ injection }` ) );
			assert( await attendreRechargement( false ), `${ nom } : l’injection est prise en compte par le serveur`, 'gabarit rechargé', 'le chiffre nominal est encore servi après 6 s' );

			const contexte = await navigateur.newContext( { javaScriptEnabled: false } );
			const page = await contexte.newPage();
			const reponse = await page.goto( BASE + '/', { waitUntil: 'load' } );
			const html = await page.content();

			// 1 — la page est servie. Avant le correctif : 500.
			egal( 200, reponse.status(), `${ nom } : la page est servie` );

			// 2 — un seul h1, et c'est celui du jour.
			egal( 1, await page.locator( 'h1' ).count(), `${ nom } : exactement un h1` );
			egal( 1, await page.locator( 'h1#titre-du-jour' ).count(), `${ nom } : le h1 est bien #titre-du-jour` );

			// 3 — la phrase §11.3 mot pour mot, et son lien officiel.
			egal(
				'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
				await texteSource( page.locator( 'h1#titre-du-jour' ) ),
				`${ nom } : phrase §11.3 verbatim`
			);
			const lien = await page.locator( 'h1#titre-du-jour a' ).getAttribute( 'href' );
			assert( !! lien, `${ nom } : le lien officiel a un href non vide`, 'une URL', lien );
			egal(
				'la carte officielle de la préfecture',
				await texteSource( page.locator( 'h1#titre-du-jour a' ) ),
				`${ nom } : le lien porte le fragment central de la phrase`
			);
			egal( hoteAttendu, new URL( lien ).host, `${ nom } : même hôte que le bandeau — même source serveur` );

			// 4 — aucun chiffre PRÉSENTÉ. L'assertion porte sur le texte visible et
			// sur les trois porteurs de chiffre, jamais sur les octets bruts de la
			// section : l'`href` officiel contient « /13 » (le département), et
			// l'assertion 3 l'exige — voir le rapport de recette.
			const texteArdoise = await texteSource( page.locator( '#ardoise' ) );
			assert(
				! /[0-9]/.test( texteArdoise ),
				`${ nom } : aucun chiffre dans le texte visible de l’ardoise`,
				'aucun [0-9]',
				texteArdoise
			);
			egal( 0, await page.locator( '#ardoise .ardoise__chiffre' ).count(), `${ nom } : aucun .ardoise__chiffre` );
			egal( 0, await page.locator( '#ardoise time' ).count(), `${ nom } : aucun <time> (fraicheur => false)` );
			egal( 0, await page.locator( '#ardoise .ardoise__publication-partielle' ).count(), `${ nom } : aucune mention de publication partielle` );

			// 5 — l'ancre d'évitement résout. DEUX liens la visent depuis la chaîne
			// #9 : celui du `header` et celui du repli statique de la carte. Le
			// contrat #9 §6 le dit explicitement — « deux liens de même nom vers la
			// MÊME destination satisfont WCAG 2.4.4, et la redondance est utile ».
			// Ce qui doit rester unique, c'est la CIBLE, affirmée juste après.
			egal( 2, await page.locator( 'a[href="#liste"]' ).count(), `${ nom } : les deux chemins d’accès à la liste sont présents` );
			egal( 1, await page.locator( '[id="liste"]' ).count(), `${ nom } : l’ancre #liste existe exactement une fois` );

			// 6 — rien de la tuyauterie n'atteint le visiteur.
			const fuite = /Warning:|Notice:|Deprecated:|Fatal error|<b>Warning<\/b>|UnhandledMatchError|_doing_it_wrong|rreur critique sur ce site/.exec( html );
			assert( ! fuite, `${ nom } : aucune trace d’erreur PHP dans le corps`, 'aucune', fuite ? fuite[ 0 ] : '' );

			// 7 — document complet.
			egal( 1, await page.locator( 'main' ).count(), `${ nom } : un <main>` );
			assert( html.includes( '</main>' ) && html.includes( '</html>' ), `${ nom } : document fermé (</main> et </html>)`, 'les deux', html.slice( -60 ) );

			// 8 — cas 2 seulement : l'avertissement est VOULU, et il reste au journal.
			if ( injection.startsWith( 'unset' ) ) {
				const journal = journalConteneur( depuis );
				assert(
					journal.includes( 'Undefined array key "etat_global"' ),
					`${ nom } : l’avertissement PHP attendu est au journal`,
					'Undefined array key "etat_global"',
					journal.split( '\n' ).filter( ( l ) => l.includes( 'PHP' ) ).slice( -2 ).join( ' | ' ) || '(rien)'
				);
			}

			await contexte.close();
		}
	} finally {
		writeFileSync( gabarit, origine );
	}

	// Post-condition obligatoire de R-27 : l'arbre est rendu à l'octet, et la
	// valeur d'injection n'a survécu nulle part.
	egal( origine.toString( 'utf8' ), readFileSync( gabarit, 'utf8' ), 'remise en état : front-page.php est restauré à l’octet' );
	assert( await attendreRechargement( true ), 'remise en état : le serveur a rechargé le gabarit d’origine', 'gabarit rechargé', 'le repli est encore servi après 6 s' );
	const verif = await navigateur.newContext();
	const p = await verif.newPage();
	await p.goto( BASE + '/', { waitUntil: 'load' } );
	egal( 1, await p.locator( '#ardoise .ardoise__chiffre' ).count(), 'remise en état : le jour nominal est de nouveau chiffré' );
	assert(
		! ( await p.content() ).includes( 'etat_de_recette_27' ),
		'remise en état : aucune trace de la valeur d’injection',
		'aucune',
		'etat_de_recette_27 encore présent'
	);
	await verif.close();
}

/**
 * Les trois bandes du lot de l'Épic 4, mesurées dans un navigateur.
 *
 * Les scénarios PHP 30 à 54 éprouvent l'extension et rendent les parties de
 * gabarit isolément, par `get_template_part()`. Aucun d'eux n'ouvre la page
 * d'accueil : rien ne disait, avant celui-ci, que les trois bandes sont
 * réellement CÂBLÉES dans `front-page.php`, ni qu'elles occupent la place que le
 * §7.1 de `MASTER.md` leur assigne. Une partie oubliée à l'appel, ou appelée
 * deux fois, ou rendue au mauvais rang, ne produit aucune erreur PHP : elle
 * produit une page silencieusement fausse.
 *
 * Ce qui est affirmé ici n'est affirmé nulle part ailleurs :
 *   — les huit bandes sont présentes UNE FOIS et dans l'ordre du document ;
 *   — la bande de péremption est AU-DESSUS de la carte, et la mention y est
 *     rendue une seule fois (la déduplication de la jonction de lot) ;
 *   — le jour nominal, cette même bande n'occupe AUCUNE hauteur — c'est la
 *     raison écrite l. 374-377 de `front-page.php` de son absence de
 *     `.bande__contenu`, et elle n'avait jamais été mesurée ;
 *   — la bande météo précède la bande des zones parcourues, les deux sont
 *     visibles, sous la liste, et sans débordement à 360 px.
 *
 * @param {import('playwright-core').Browser} navigateur Navigateur.
 */
async function s27_bandesDeLEpic4( navigateur ) {
	scenario( '27 — les trois bandes de l’Épic 4 à leur place dans la page servie (issues #10, #11, #12)' );

	// L'ordre du §7.1 de MASTER.md, dans le sens du document. `carte-secours` est
	// à l'intérieur de `.bande--carte` : il n'apparaît pas ici.
	const ORDRE_ATTENDU = [
		'bande--ardoise',
		'bande--peremption',
		'bande--non-officialite',
		'bande--carte',
		'bande--legende',
		'bande--liste',
		'bande--meteo',
		'bande--zones-parcourues',
	];

	/**
	 * Relève les bandes servies, dans l'ordre du document, avec leur géométrie.
	 *
	 * @param {import('playwright-core').Page} page Page chargée.
	 * @return {Promise<object>} Relevé.
	 */
	const releverBandes = ( page ) =>
		page.evaluate( () =>
			[ ...document.querySelectorAll( '.bande' ) ].map( ( e ) => {
				const r = e.getBoundingClientRect();
				return {
					classe: [ ...e.classList ].find( ( c ) => c.startsWith( 'bande--' ) ) ?? '(sans modificateur)',
					haut: Math.round( r.top + window.scrollY ),
					hauteur: Math.round( r.height ),
					droite: Math.round( r.right ),
				};
			} )
		);

	// ------------------------------------------------------------------ (a)
	// Donnée de la veille : la bande de péremption a quelque chose à dire.
	// JavaScript coupé — les trois bandes sont rendues par PHP, ou elles ne sont
	// pas rendues du tout.
	poserEtat( 'veille-seule' );

	const perime = await navigateur.newContext( { javaScriptEnabled: false, viewport: { width: 1280, height: 900 } } );
	const pagePerime = await perime.newPage();
	await pagePerime.goto( BASE + '/', { waitUntil: 'load' } );

	const bandes = await releverBandes( pagePerime );
	note( `bandes servies : ${ bandes.map( ( b ) => `${ b.classe } (h=${ b.hauteur })` ).join( ' · ' ) }` );
	egal(
		ORDRE_ATTENDU,
		bandes.map( ( b ) => b.classe ),
		'les huit bandes sont servies une fois chacune, dans l’ordre du §7.1'
	);

	// La bande de péremption, et la déduplication de la jonction de lot.
	egal( 1, await pagePerime.locator( '.bande--peremption .bandeau-alerte--peremption' ).count(), 'la mention de péremption est rendue par la bande dédiée, et une seule fois' );
	egal( 0, await pagePerime.locator( '.ardoise__peremption' ).count(), 'elle n’est plus rendue une seconde fois par l’ardoise' );

	const bandePerime = bandes.find( ( b ) => b.classe === 'bande--peremption' );
	const bandeCarte = bandes.find( ( b ) => b.classe === 'bande--carte' );
	assert( bandePerime.hauteur > 0, 'donnée périmée : la bande occupe une hauteur réelle — elle est vue', '> 0 px', `${ bandePerime.hauteur } px` );
	assert( bandePerime.haut < bandeCarte.haut, 'donnée périmée : l’alerte est AU-DESSUS de la carte, pas sous elle', 'péremption avant carte', `péremption à ${ bandePerime.haut } px, carte à ${ bandeCarte.haut } px` );

	// Les deux bandes neuves : un landmark nommé chacune, une fois.
	egal( 1, await pagePerime.locator( '[id="meteo"]' ).count(), 'contrat #10 : la section « météo » existe une fois et une seule' );
	egal( 1, await pagePerime.locator( '[id="zones-parcourues"]' ).count(), 'contrat #11 : la section « zones parcourues » existe une fois et une seule' );
	egal( 1, await pagePerime.locator( '#meteo > h2#meteo-titre' ).count(), 'contrat #10 : le h2 de la bande météo nomme sa section' );
	egal( 1, await pagePerime.locator( '#zones-parcourues > h2#zones-parcourues-titre' ).count(), 'contrat #11 : le h2 de la bande des zones nomme sa section' );

	for ( const [ ancre, libelle ] of [ [ '#meteo', 'météo' ], [ '#zones-parcourues', 'zones parcourues' ] ] ) {
		const boite = await pagePerime.locator( ancre ).boundingBox();
		assert(
			boite !== null && boite.height > 0 && boite.width > 0,
			`la bande ${ libelle } est réellement peinte (boîte non nulle)`,
			'une boîte de largeur et de hauteur non nulles',
			JSON.stringify( boite )
		);
	}

	const bandeMeteo = bandes.find( ( b ) => b.classe === 'bande--meteo' );
	const bandeZones = bandes.find( ( b ) => b.classe === 'bande--zones-parcourues' );
	const bandeListe = bandes.find( ( b ) => b.classe === 'bande--liste' );
	assert( bandeMeteo.haut > bandeListe.haut, '§7.1 : les deux bandes neuves viennent APRÈS la liste textuelle', 'météo après liste', `liste à ${ bandeListe.haut } px, météo à ${ bandeMeteo.haut } px` );
	assert( bandeMeteo.haut < bandeZones.haut, '§7.1 : DANGER MÉTÉO avant ZONES PARCOURUES', 'météo avant zones', `météo à ${ bandeMeteo.haut } px, zones à ${ bandeZones.haut } px` );

	// Les deux bandes ne parlent JAMAIS de statut d'accès : I-11.5 pour les
	// zones, et la phrase de distinction du contrat #10 pour la météo. Le jour où
	// la donnée du jour manque, aucune des deux ne doit combler le silence.
	const texteMeteo = await texteSource( pagePerime.locator( '#meteo' ) );
	const texteZones = await texteSource( pagePerime.locator( '#zones-parcourues' ) );
	note( `bande météo (donnée du jour absente) : « ${ texteMeteo } »` );
	note( `bande zones (donnée du jour absente) : « ${ texteZones } »` );
	for ( const [ texte, libelle ] of [ [ texteMeteo, 'météo' ], [ texteZones, 'zones parcourues' ] ] ) {
		assert(
			! /Acc(è|e)s au massif|Acc(è|e)s à la ZAPEF/.test( texte ),
			`§4.2 : la bande ${ libelle } ne porte aucun libellé de niveau d’accès`,
			'aucun libellé de niveau',
			texte
		);
	}

	await perime.close();

	// ------------------------------------------------------------------ (b)
	// Jour nominal : la bande de péremption n'a rien à dire. Elle doit alors
	// coûter ZÉRO pixel — c'est la raison écrite de sa forme, jamais mesurée.
	poserEtat( 'jour-nominal' );

	const nominal = await navigateur.newContext( { javaScriptEnabled: false, viewport: { width: 1280, height: 900 } } );
	const pageNominal = await nominal.newPage();
	await pageNominal.goto( BASE + '/', { waitUntil: 'load' } );

	const bandesNominal = await releverBandes( pageNominal );
	egal(
		ORDRE_ATTENDU,
		bandesNominal.map( ( b ) => b.classe ),
		'jour nominal : les huit bandes sont servies, dans le même ordre'
	);
	egal( 0, await pageNominal.locator( '.bandeau-alerte--peremption' ).count(), 'jour nominal : aucune mention de péremption — la donnée est fraîche' );
	const videNominal = bandesNominal.find( ( b ) => b.classe === 'bande--peremption' );
	egal( 0, videNominal.hauteur, 'jour nominal : la bande de péremption n’injecte AUCUN vide au-dessus de la carte (front-page.php l. 374-377)' );

	egal( 1, await pageNominal.locator( '[id="meteo"]' ).count(), 'jour nominal : la bande météo est là aussi' );
	egal( 1, await pageNominal.locator( '[id="zones-parcourues"]' ).count(), 'jour nominal : la bande des zones est là aussi' );

	await pageNominal.close();

	// ------------------------------------------------------------------ (c)
	// 360 px : les deux bandes neuves ne débordent pas. Le scénario `mobile`
	// mesure le document entier ; celui-ci mesure les DEUX BANDES, qui n'y
	// existaient pas quand il a été écrit.
	const petit = await nominal.newPage();
	await petit.setViewportSize( { width: 360, height: 800 } );
	await petit.goto( BASE + '/', { waitUntil: 'load' } );

	const debordement = await petit.evaluate( () =>
		[ '#meteo', '#zones-parcourues' ].map( ( s ) => {
			const e = document.querySelector( s );
			if ( ! e ) {
				return { sel: s, absent: true };
			}
			const r = e.getBoundingClientRect();
			return {
				sel: s,
				droite: Math.round( r.right ),
				debordeX: e.scrollWidth > e.clientWidth + 1,
				fenetre: document.documentElement.clientWidth,
			};
		} )
	);
	note( `à 360 px : ${ JSON.stringify( debordement ) }` );
	for ( const m of debordement ) {
		assert(
			! m.absent && m.droite <= m.fenetre + 1 && ! m.debordeX,
			`360 px : ${ m.sel } tient dans la fenêtre, sans défilement horizontal`,
			`droite <= ${ m.fenetre } px et aucun débordement interne`,
			JSON.stringify( m )
		);
	}

	await nominal.close();
}

// ================================================================ Épic 5 — le portail
//
// Rien du portail n'avait été exercé avec un vrai gestionnaire : les capacités
// n'existaient pas au moment où #14 et #15 ont été écrites. Tout ce qui suit
// ouvre de VRAIES sessions dans un VRAI navigateur et n'affirme que ce qu'un
// gestionnaire ou un administrateur observerait.
//
// L'interdiction de cookie du §2 vise le visiteur ANONYME. Les cookies posés ici
// meurent avec les contextes de navigation qui les portent.

/** Comptes provisionnés par la stack, lus dans `.env` et jamais recopiés. */
const COMPTE_ADMIN = {
	login: lireEnv( 'WP_ADMIN_USER', '' ),
	motDePasse: lireEnv( 'WP_ADMIN_PASSWORD', '' ),
	courriel: lireEnv( 'WP_ADMIN_EMAIL', '' ),
};

const COMPTE_GESTIONNAIRE = {
	login: lireEnv( 'WP_MANAGER_USER', '' ),
	motDePasse: lireEnv( 'WP_MANAGER_PASSWORD', '' ),
	courriel: lireEnv( 'WP_MANAGER_EMAIL', '' ),
};

/**
 * Secret TOTP de recette, en base32 RFC 4648 §6.
 *
 * FIXE ET CONNU DE LA RECETTE, jamais aléatoire : c'est ce qui permet de calculer
 * le code attendu ici, en Node, et donc d'ÉPROUVER la double authentification au
 * lieu de la contourner par `MASSIFS_DESACTIVER_2FA`. Le secret ne vaut que sur
 * la stack de recette et n'est jamais provisionné ailleurs.
 */
const SECRET_TOTP_RECETTE = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/**
 * Code TOTP à six chiffres, calculé ici et jamais lu dans le code du serveur.
 *
 * Réimplémentation indépendante de la RFC 6238 en Node : si les deux moitiés
 * divergent, la connexion échoue et le scénario rougit. C'est exactement ce
 * qu'on veut d'une épreuve de second facteur — comparer notre implémentation à
 * la sienne prouverait seulement qu'elle est égale à elle-même.
 *
 * @param {string} secretB32 Secret partagé, base32.
 * @param {number} [decalage] Décalage en pas de 30 s, pour éprouver la tolérance.
 * @return {Promise<string>} Six chiffres.
 */
async function codeTotp( secretB32, decalage = 0 ) {
	const { createHmac } = await import( 'node:crypto' );
	const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	let bits = '';
	for ( const caractere of secretB32.toUpperCase().replace( /[\s=-]/g, '' ) ) {
		bits += ALPHABET.indexOf( caractere ).toString( 2 ).padStart( 5, '0' );
	}
	const octets = [];
	for ( let i = 0; i + 8 <= bits.length; i += 8 ) {
		octets.push( parseInt( bits.slice( i, i + 8 ), 2 ) );
	}

	const pas = Math.floor( Date.now() / 1000 / 30 ) + decalage;
	const compteur = Buffer.alloc( 8 );
	compteur.writeUInt32BE( Math.floor( pas / 2 ** 32 ), 0 );
	compteur.writeUInt32BE( pas >>> 0, 4 );

	const empreinte = createHmac( 'sha1', Buffer.from( octets ) ).update( compteur ).digest();
	const offset = empreinte[ empreinte.length - 1 ] & 0x0f;
	const binaire =
		( ( empreinte[ offset ] & 0x7f ) << 24 ) |
		( ( empreinte[ offset + 1 ] & 0xff ) << 16 ) |
		( ( empreinte[ offset + 2 ] & 0xff ) << 8 ) |
		( empreinte[ offset + 3 ] & 0xff );

	return String( binaire % 1000000 ).padStart( 6, '0' );
}

/**
 * Arme le second facteur d'un compte avec le secret de recette.
 *
 * Passe par `SecretUtilisateur::activer()`, le point de passage du contrat #13 —
 * jamais par un `update_user_meta` nu, que l'interdit 5 proscrit et qui ne
 * chiffrerait pas le secret.
 *
 * @param {string} login Identifiant du compte.
 */
function enrolerTotp( login ) {
	// Pas consommé à 0 : l'anti-rejeu du contrat A-17 exige un pas STRICTEMENT
	// supérieur au dernier mémorisé. Une valeur d'aujourd'hui refuserait le
	// premier code que la recette produirait.
	wp( [
		'eval',
		`$u = get_user_by( 'login', '${ login }' );` +
			`\\Massifs\\Security\\Auth\\SecretUtilisateur::activer( (int) $u->ID, '${ SECRET_TOTP_RECETTE }', 0 );` +
			`echo \\Massifs\\Security\\Auth\\SecretUtilisateur::est_actif( (int) $u->ID ) ? 'ARME' : 'RATE';`,
	] );
}

/**
 * Retire le second facteur d'un compte, et rend la stack à son état d'origine.
 *
 * @param {string} login Identifiant du compte.
 */
function retirerTotp( login ) {
	wp( [
		'eval',
		`$u = get_user_by( 'login', '${ login }' );` +
			`\\Massifs\\Security\\Auth\\SecretUtilisateur::desactiver( (int) $u->ID );` +
			`echo \\Massifs\\Security\\Auth\\SecretUtilisateur::est_actif( (int) $u->ID ) ? 'ENCORE' : 'RETIRE';`,
	] );
}

/**
 * Purge l'écluse anti-force-brute.
 *
 * Un scénario qui sature volontairement l'écluse doit rendre la stack propre,
 * sans quoi le scénario SUIVANT se ferait barrer à la connexion pour une raison
 * qui n'a rien à voir avec ce qu'il éprouve.
 */
function purgerEcluse() {
	wp( [
		'eval',
		"delete_option( 'massifs_ecluse_verrous' );" +
			'global $wpdb;' +
			"$wpdb->query( \"DELETE FROM {$wpdb->options} WHERE option_name LIKE '%massifs_ecluse_c_%'\" );" +
			"echo 'PURGE';",
	] );
}

/**
 * Ouvre une session complète, second facteur compris.
 *
 * Traverse le VRAI formulaire du cœur, puis, si l'interstitiel du second facteur
 * paraît, le VRAI formulaire d'étape 2 — jamais un `wp_set_auth_cookie` posé de
 * l'extérieur, qui ne prouverait rien de la chaîne d'authentification.
 *
 * @param {import('playwright-core').BrowserContext} contexte Contexte de navigation.
 * @param {object} compte Compte à connecter.
 * @param {object} [options] `attendre2fa` impose la présence de l'étape 2.
 * @return {Promise<object>} Ce que la connexion a réellement traversé.
 */
async function connexion( contexte, compte, options = {} ) {
	const page = await contexte.newPage();
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
	await page.fill( '#user_login', compte.login );
	await page.fill( '#user_pass', compte.motDePasse );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'load' );

	const trace = { etape2: false, urlEtape2: '', cookiesApresEtape1: [], destination: '' };

	if ( page.url().includes( 'action=massifs_2fa' ) ) {
		trace.etape2 = true;
		trace.urlEtape2 = page.url();
		// AUCUN cookie de session ne doit exister à ce stade : le mot de passe seul
		// n'ouvre rien. C'est la propriété qui distingue une vraie 2FA d'un rideau.
		trace.cookiesApresEtape1 = ( await contexte.cookies() ).map( ( c ) => c.name );

		await page.fill( '#massifs-2fa-code', await codeTotp( SECRET_TOTP_RECETTE ) );
		await page.click( 'button[type="submit"]' );
		await page.waitForLoadState( 'load' );
	}

	trace.destination = page.url();
	await page.close();

	if ( options.attendre2fa && ! trace.etape2 ) {
		ko( `${ compte.login } : l’étape 2 du second facteur était attendue`, 'wp-login.php?action=massifs_2fa', trace.destination );
	}

	return trace;
}

/**
 * Relevé réseau d'une page d'administration, origines et échecs.
 *
 * @param {import('playwright-core').BrowserContext} contexte Contexte connecté.
 * @param {string} chemin Chemin d'administration.
 * @return {Promise<object>} Relevé.
 */
async function chargerAdmin( contexte, chemin ) {
	const releve = await charger( contexte, chemin );
	const origines = new Map();
	for ( const r of releve.requetes ) {
		const origine = new URL( r.url ).origin;
		origines.set( origine, ( origines.get( origine ) ?? 0 ) + 1 );
	}
	releve.origines = origines;
	releve.tierces = [ ...origines.keys() ].filter( ( o ) => o !== ORIGINE );
	return releve;
}

/** Les écrans du cœur que le §6 interdit au gestionnaire. */
const ECRANS_INTERDITS = [
	'/wp-admin/edit.php',
	'/wp-admin/edit.php?post_type=page',
	'/wp-admin/upload.php',
	'/wp-admin/edit-comments.php',
	'/wp-admin/themes.php',
	'/wp-admin/plugins.php',
	'/wp-admin/users.php',
	'/wp-admin/user-new.php',
	'/wp-admin/options-general.php',
	'/wp-admin/tools.php',
	'/wp-admin/theme-editor.php',
	'/wp-admin/plugin-install.php',
];

async function s28_portailGestionnaire( navigateur ) {
	scenario( '28 — portail : un gestionnaire publie les 25 massifs, et rien d’autre (issues #13, #14, #15)' );

	// Garde de configuration : `lireEnv` se replie sur '' et une connexion ratée
	// rendrait « le gestionnaire ne voit pas Réglages » trivialement vert.
	assert(
		COMPTE_GESTIONNAIRE.login !== '' && COMPTE_GESTIONNAIRE.motDePasse !== '',
		'configuration : les identifiants du compte de démonstration sont lus dans .env',
		'un identifiant et un mot de passe',
		`${ COMPTE_GESTIONNAIRE.login || '(vide)' } / ${ COMPTE_GESTIONNAIRE.motDePasse ? '(présent)' : '(vide)' }`
	);

	poserEtat( 'jour-complet', 20 );

	const contexte = await navigateur.newContext();
	try {
		const trace = await connexion( contexte, COMPTE_GESTIONNAIRE );

		assert(
			! trace.etape2,
			'A-9 : le second facteur n’est PAS imposé au gestionnaire — la démonstration publique reste tenable en deux minutes',
			'aucune étape 2',
			trace.urlEtape2
		);
		assert(
			( await contexte.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
			'le gestionnaire est réellement connecté',
			'wordpress_logged_in_*',
			( await contexte.cookies() ).map( ( c ) => c.name ).join( ', ' ) || '(aucun)'
		);

		// ---- 1. Le menu : ce que le gestionnaire voit, et surtout ce qu'il ne voit pas
		const tableauDeBord = await chargerAdmin( contexte, '/wp-admin/' );
		egal( 200, tableauDeBord.statut, 'le tableau de bord est servi au gestionnaire' );

		const menu = await tableauDeBord.page.$$eval( '#adminmenu > li > a', ( liens ) =>
			liens.map( ( a ) => ( a.getAttribute( 'href' ) ?? '' ).split( '?' )[ 0 ].replace( /^.*\/wp-admin\//, '' ) )
		);
		note( `menu du gestionnaire : ${ menu.join( ' · ' ) }` );

		const menusInterdits = menu.filter( ( href ) =>
			[ 'edit.php', 'upload.php', 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php' ].includes( href )
		);
		egal( [], menusInterdits, 'A-6 : aucune entrée de contenu, de réglage, d’extension ni d’utilisateur dans le menu du gestionnaire' );
		assert(
			menu.some( ( h ) => h.includes( 'admin.php' ) ) || menu.some( ( h ) => h.includes( 'massifs' ) ),
			'le portail MASSIFS a bien son entrée de menu',
			'une entrée admin.php?page=massifs…',
			menu.join( ' · ' )
		);
		await tableauDeBord.page.close();

		// ---- 2. Les écrans interdits, atteints PAR L'URL — le menu ne protège rien
		const franchis = [];
		for ( const chemin of ECRANS_INTERDITS ) {
			const vue = await contexte.newPage();
			const reponse = await vue.goto( BASE + chemin, { waitUntil: 'load' } );
			const statut = reponse ? reponse.status() : 0;
			const corps = await vue.content();
			// Le cœur rend un 403 avec « Vous n'avez pas l'autorisation… ». Un 200
			// portant l'écran réel est le seul résultat inadmissible.
			const refuse =
				statut === 403 ||
				vue.url().includes( 'wp-login.php' ) ||
				/n(?:’|')avez pas (?:l(?:’|')autorisation|les droits)|Désolé, vous n(?:’|')avez pas/i.test( corps );
			if ( ! refuse ) {
				franchis.push( `${ chemin } → ${ statut }` );
			}
			await vue.close();
		}
		egal( [], franchis, '§6 : aucun écran de contenu, de réglage, d’extension ou d’utilisateur n’est atteignable par URL directe' );

		// ---- 3. L'écran de publication
		const publication = await chargerAdmin( contexte, '/wp-admin/admin.php?page=massifs-publication&massifs_jour=aujourd_hui' );
		egal( 200, publication.statut, 'l’écran de publication est servi au gestionnaire' );
		egal( [], publication.tierces, 'ZÉRO REQUÊTE TIERCE sur l’écran de publication (§12, périmètre étendu à wp-admin)' );
		note( `écran de publication : ${ publication.requetes.length } requêtes — ${ [ ...publication.origines.keys() ].join( ' · ' ) }` );

		const ecran = publication.page;
		egal( 1, await ecran.locator( 'h1' ).count(), 'un seul h1 sur l’écran de publication' );
		egal( 25, await ecran.locator( '.massifs-liste__element' ).count(), 'les 25 massifs sont rendus' );
		egal( 50, await ecran.locator( 'input.massifs-segmentee__radio' ).count(), 'deux niveaux par massif, soit 50 radios' );

		// Contrainte n° 3 : l'écran est complet SANS une ligne de JavaScript livrée
		// par l'issue. On mesure les scripts réellement demandés depuis l'extension.
		const scriptsExtension = publication.requetes.filter(
			( r ) => r.type === 'script' && r.url.includes( '/plugins/massifs-core/' )
		);
		egal( [], scriptsExtension.map( ( r ) => r.url ), 'contrainte 3 : l’écran de publication ne livre AUCUN JavaScript de l’extension' );

		// LA FEUILLE DE L'ÉCRAN EST-ELLE RÉELLEMENT SERVIE ?
		//
		// Une poignée `wp_enqueue_style()` correctement posée ne prouve RIEN : elle
		// n'établit que la présence d'un `<link>` dans le `<head>`. Ce que le
		// navigateur obtient au bout de cette URL est une autre question, et c'est
		// la seule qui décide de ce que l'écran donne à voir. Un 403 laisse la
		// poignée intacte, le `<link>` en place, aucune erreur PHP, aucune requête
		// « en échec » au sens de Playwright — et un écran entièrement dépouillé.
		// C'est un mode de panne strictement invisible à toute vérification qui
		// n'ouvre pas la page.
		const refusees = publication.tailles.filter( ( t ) => t.statut >= 400 );
		egal(
			[],
			refusees.map( ( t ) => `${ t.statut } ${ t.url.replace( ORIGINE, '' ) }` ),
			'aucune sous-ressource de l’écran de publication ne répond en erreur'
		);
		const feuillesAppliquees = await ecran.evaluate( () =>
			[ ...document.styleSheets ]
				.filter( ( f ) => ( f.href ?? '' ).includes( '/plugins/massifs-core/' ) )
				.map( ( f ) => {
					let regles = -1;
					try {
						regles = f.cssRules.length;
					} catch {
						regles = -1;
					}
					return { href: f.href.split( '/' ).slice( -1 )[ 0 ].split( '?' )[ 0 ], regles };
				} )
		);
		note( `feuilles de l’extension sur l’écran de publication : ${ JSON.stringify( feuillesAppliquees ) }` );
		assert(
			feuillesAppliquees.length > 0 && feuillesAppliquees.every( ( f ) => f.regles > 0 ),
			'la feuille de style de l’écran de publication est SERVIE et RÉELLEMENT APPLIQUÉE — pas seulement enfilée',
			'au moins une feuille de l’extension portant des règles',
			JSON.stringify( feuillesAppliquees )
		);

		// R-1 : deux formulaires FRÈRES, et le formulaire principal n'a qu'un seul
		// bouton de soumission. C'est ce qui empêche `Entrée` de tout passer en
		// « autorisé ».
		egal( 2, await ecran.locator( 'form.massifs-preremplissage__formulaire, form.massifs-ecran__formulaire' ).count(), 'R-1 : deux formulaires' );
		egal(
			0,
			await ecran.locator( 'form.massifs-ecran__formulaire form' ).count(),
			'R-1 : les deux formulaires sont FRÈRES, jamais imbriqués'
		);
		const soumissionsPrincipales = await ecran.locator( 'form.massifs-ecran__formulaire button[type="submit"]' ).allTextContents();
		egal( [ 'Publier les statuts' ], soumissionsPrincipales.map( ( t ) => t.trim() ), 'R-1 : le formulaire principal n’a qu’un bouton de soumission — la soumission implicite ne peut plus tout autoriser' );

		// ---- 4. Publication complète des 25 massifs, AU CLAVIER, chronométrée
		//
		// Ce chronomètre mesure la MACHINE, pas un opérateur humain : Playwright
		// frappe plus vite qu'une personne. Il ne PROUVE donc pas la ligne « moins
		// d'une minute » du §6 — il prouve que l'écran ne l'interdit pas, et il
		// compte les gestes réellement nécessaires, qui est la grandeur qu'un humain
		// paie. Les deux chiffres sont rapportés tels quels.
		const debutClavier = Date.now();
		let gestes = 0;

		await ecran.locator( 'input.massifs-segmentee__radio' ).first().focus();
		gestes += 1;

		// Dans un groupe de radios, les flèches choisissent ; Tab passe au groupe
		// suivant. C'est le parcours natif, celui qu'un gestionnaire emploiera.
		for ( let massif = 0; massif < 25; massif += 1 ) {
			if ( massif > 0 ) {
				await ecran.keyboard.press( 'Tab' );
				gestes += 1;
			}
			// Un massif sur trois passe en « interdit », les autres en « autorisé » :
			// une journée réaliste, jamais un pré-remplissage en bloc.
			if ( massif % 3 === 0 ) {
				await ecran.keyboard.press( 'ArrowRight' );
			} else {
				await ecran.keyboard.press( 'ArrowLeft' );
			}
			gestes += 1;
		}

		const coches = await ecran.locator( 'input.massifs-segmentee__radio:checked' ).count();
		const dureeSaisie = Date.now() - debutClavier;
		egal( 25, coches, 'les 25 massifs sont renseignés au CLAVIER SEUL — flèches dans le groupe, Tab entre les groupes' );
		note( `saisie clavier des 25 massifs : ${ gestes } gestes, ${ ( dureeSaisie / 1000 ).toFixed( 1 ) } s machine` );
		assert(
			gestes <= 60,
			'§6 : renseigner les 25 massifs au clavier coûte au plus 60 gestes — la contrainte « moins d’une minute » reste atteignable',
			'≤ 60 gestes',
			`${ gestes } gestes`
		);

		const attenduParMassif = await ecran.$$eval( '.massifs-liste__element', ( elements ) =>
			elements.map( ( li ) => {
				const coche = li.querySelector( 'input.massifs-segmentee__radio:checked' );
				return { code: li.id, valeur: coche ? coche.value : '' };
			} )
		);

		const debutPublication = Date.now();
		// `massifs_resultat` SEUL : un `includes( 'massifs-publication' )` en
		// alternative serait déjà vrai de l'URL COURANTE, et `waitForURL`
		// retournerait sans avoir attendu la moindre navigation.
		await Promise.all( [
			ecran.waitForURL( ( u ) => u.href.includes( 'massifs_resultat=' ), { timeout: 30000 } ),
			ecran.locator( 'form.massifs-ecran__formulaire button[type="submit"]' ).click(),
		] );
		await ecran.waitForLoadState( 'load' );
		const dureePublication = Date.now() - debutPublication;
		note( `aller-retour de publication (POST + redirection + rendu) : ${ ( dureePublication / 1000 ).toFixed( 2 ) } s` );
		assert(
			dureePublication < 60000,
			'§12 : l’aller-retour serveur de la publication complète tient sous une minute',
			'< 60 000 ms',
			`${ dureePublication } ms`
		);

		// PRG : on revient en GET, avec le fragment contractuel.
		assert(
			ecran.url().includes( 'massifs_resultat=' ),
			'la publication répond par une redirection PRG portant un jeton de rapport',
			'…&massifs_resultat=…',
			ecran.url()
		);
		egal( 1, await ecran.locator( '#massifs-recapitulatif' ).count(), 'le récapitulatif est rendu, et porte l’ancre contractuelle' );
		note( `récapitulatif : ${ await texteSource( ecran.locator( '#massifs-recapitulatif' ) ) }` );

		await ecran.close();

		// ---- 5. Propagation vers le site public
		const publicContexte = await navigateur.newContext( { javaScriptEnabled: false } );
		const vuePublique = await publicContexte.newPage();
		await vuePublique.goto( BASE + '/', { waitUntil: 'load' } );
		const propagation = Date.now() - debutPublication;

		// La ligne d'en-tête porte la MÊME classe que les lignes de données, plus un
		// modificateur : sans l'exclusion, on compterait 26 massifs.
		const rendus = await vuePublique.$$eval( '.liste-statuts__ligne:not(.liste-statuts__ligne--entete)', ( lignes ) =>
			lignes.map( ( tr ) => tr.textContent.replace( /\s+/g, ' ' ).trim() )
		);
		note( `propagation mesurée : ${ ( propagation / 1000 ).toFixed( 2 ) } s après la publication` );
		assert(
			propagation < 60000,
			'§12 : la valeur publiée est visible sur le site public en moins d’une minute',
			'< 60 000 ms',
			`${ propagation } ms`
		);

		// Ce n'est pas le texte qui compte, c'est la CONCORDANCE : le public rend
		// exactement ce que le portail vient d'écrire.
		const interditsAttendus = attenduParMassif.filter( ( m ) => m.valeur === 'interdit' ).length;
		const interditsRendus = rendus.filter( ( t ) => /interdit/i.test( t ) ).length;
		egal(
			interditsAttendus,
			interditsRendus,
			`la page publique rend exactement les ${ interditsAttendus } massifs que le portail vient de passer en « interdit »`
		);
		egal( 25, rendus.length, 'la liste publique porte toujours les 25 massifs' );

		// Bandeau de non-officialité : présent partout où un statut paraît.
		egal( 1, await vuePublique.locator( '.bandeau-non-officialite' ).count(), '§5.6 : le bandeau de non-officialité est présent sur la page qui porte les statuts publiés' );

		await vuePublique.close();
		await publicContexte.close();

		// ---- 6. L'historique : la publication y est, avec qui / quoi / quand
		const historique = await chargerAdmin( contexte, '/wp-admin/admin.php?page=massifs-historique' );
		egal( 200, historique.statut, 'l’historique est servi au gestionnaire' );
		egal( [], historique.tierces, 'ZÉRO REQUÊTE TIERCE sur l’écran d’historique' );
		egal( 1, await historique.page.locator( 'h1' ).count(), 'un seul h1 sur l’historique' );

		egal(
			[],
			historique.tailles.filter( ( t ) => t.statut >= 400 ).map( ( t ) => `${ t.statut } ${ t.url.replace( ORIGINE, '' ) }` ),
			'aucune sous-ressource de l’écran d’historique ne répond en erreur'
		);
		const feuillesHistorique = await historique.page.evaluate( () =>
			[ ...document.styleSheets ]
				.filter( ( f ) => ( f.href ?? '' ).includes( '/plugins/massifs-core/' ) )
				.map( ( f ) => {
					let regles = -1;
					try {
						regles = f.cssRules.length;
					} catch {
						regles = -1;
					}
					return { href: f.href.split( '/' ).slice( -1 )[ 0 ].split( '?' )[ 0 ], regles };
				} )
		);
		note( `feuilles de l’extension sur l’historique : ${ JSON.stringify( feuillesHistorique ) }` );
		assert(
			feuillesHistorique.length > 0 && feuillesHistorique.every( ( f ) => f.regles > 0 ),
			'la feuille de style de l’historique est SERVIE et RÉELLEMENT APPLIQUÉE',
			'au moins une feuille de l’extension portant des règles',
			JSON.stringify( feuillesHistorique )
		);

		// §7.6.11 du contrat #15 : le tableau est confiné dans sa zone défilante,
		// « la page ne bouge pas, ni à 360 px ni à 200 % de zoom ». C'est une
		// propriété de RENDU, pas de balisage : elle ne tient que si la feuille
		// arrive. Mesurée ici, à la largeur où elle compte.
		const confinement = await historique.page.evaluate( () => {
			const zone = document.querySelector( '.massifs-historique-defilant' );
			return zone
				? { role: zone.getAttribute( 'role' ), tabindex: zone.getAttribute( 'tabindex' ), overflowX: getComputedStyle( zone ).overflowX }
				: null;
		} );
		note( `zone défilante de l’historique : ${ JSON.stringify( confinement ) }` );
		egal( 'region', confinement?.role, '§7.6.11 : la zone défilante est une région annoncée' );
		egal( '0', confinement?.tabindex, '§7.6.11 : la zone défilante est atteignable au clavier' );
		assert(
			confinement && [ 'auto', 'scroll' ].includes( confinement.overflowX ),
			'§7.6.11 : la zone défilante DÉFILE réellement — sans quoi le tableau déborde la page',
			'overflow-x: auto | scroll',
			confinement?.overflowX
		);

		// ┌──────────────────────────────────────────────────────────────────────┐
		// │  LE CHEVRON DES SELECTS RECOUVRAIT LE LIBELLÉ AFFICHÉ.               │
		// │  DÉFAUT DE SOURCE trouvé le 16 août 2026 EN REGARDANT L'ÉCRAN,       │
		// │  corrigé le jour même par `3814a31`.                                  │
		// │                                                                       │
		// │  wp-admin peint la flèche des `<select>` en `background-image` et lui │
		// │  réserve sa voie par un `padding-right` ASYMÉTRIQUE. `historique.css` │
		// │  écrasait les deux côtés d'un coup — `padding-inline: var(--esp-xs)`, │
		// │  soit 8 px — sans rien remettre à droite. La voie n'était plus        │
		// │  réservée : dès que le libellé affiché remplissait sa boîte, ses      │
		// │  derniers glyphes passaient SOUS la flèche. Ce n'était pas un cas     │
		// │  limite — un `<select>` se dimensionne sur son option la plus large,  │
		// │  donc le pire cas ÉTAIT le cas nominal, et le filtre « Auteur » y     │
		// │  tombait dès le chargement, sans la moindre interaction.              │
		// └──────────────────────────────────────────────────────────────────────┘
		//
		// CE QUI EST MESURÉ, ET POURQUOI PAS AUTRE CHOSE (constat M-8 de la revue).
		//
		// La première rédaction comparait la largeur de l'option LA PLUS LARGE à la
		// voie du chevron. Deux défauts, tous deux réels :
		//
		//   1. FAUX POSITIF EN ATTENTE. L'énoncé porte sur le libellé AFFICHÉ ; la
		//      mesure portait sur une option qui peut n'être jamais choisie. Avec
		//      `max-inline-size: 100%`, un `<select>` bridé par son conteneur aurait
		//      fait rougir l'assertion sans qu'un seul pixel de libellé soit couvert.
		//   2. MESURE SUR LE FIL DU RASOIR. Un `<select>` dimensionné par son contenu
		//      a, par construction, sa plus large option qui FINIT exactement à la
		//      limite de la voie : l'écart nominal est 0, et le verdict se joue alors
		//      sur le bruit entre `measureText()` et la mise en page réelle — ±1 px
		//      de police ou d'arrondi le fait basculer.
		//
		// L'assertion porte donc sur la RÉSERVE elle-même : `padding-inline-end` au
		// moins égal à la voie de la flèche. C'est la condition géométrique
		// SUFFISANTE, et elle est exacte — le texte est disposé dans la boîte de
		// contenu, qui finit à `clientWidth - paddingRight` ; si cette limite est au
		// plus le début de la voie, aucun glyphe ne peut être peint sous la flèche, y
		// compris quand la largeur est bridée : le texte est alors ROGNÉ par la boîte,
		// jamais glissé sous l'icône. Aucune métrique de texte n'intervient, donc
		// aucune fragilité au sous-pixel, et un `<select>` contraint ne peut plus
		// produire de faux rouge.
		//
		// Le recouvrement du libellé RÉELLEMENT SÉLECTIONNÉ reste relevé, en `note` :
		// c'est le symptôme, utile à qui diagnostique, mais c'est la grandeur bruitée
		// — elle ne décide de rien.
		const chevrons = await historique.page.evaluate( () => {
			const mesure = document.createElement( 'canvas' ).getContext( '2d' );
			return [ ...document.querySelectorAll( '.massifs-historique-filtres select' ) ].map( ( s ) => {
				const cs = getComputedStyle( s );
				mesure.font = `${ cs.fontStyle } ${ cs.fontWeight } ${ cs.fontSize } ${ cs.fontFamily }`;

				// La voie est LUE sur le style calculé, jamais codée en dur : si
				// wp-admin déplace ou redimensionne sa flèche, l'exigence suit.
				// `background-position` calcule en `calc(100% - Npx)` — on en extrait
				// N ; `background-size` donne la largeur de l'icône.
				const ecart = /calc\(100% - ([\d.]+)px\)/.exec( cs.backgroundPosition );
				const taille = /^([\d.]+)px/.exec( cs.backgroundSize );
				const voie = ( ecart ? parseFloat( ecart[ 1 ] ) : 8 ) + ( taille ? parseFloat( taille[ 1 ] ) : 16 );

				const selectionne = s.options[ s.selectedIndex ].textContent.trim();
				const arrondi = ( v ) => Math.round( v * 10 ) / 10;

				return {
					nom: s.name,
					selectionne,
					voie: arrondi( voie ),
					reserve: arrondi( parseFloat( cs.paddingInlineEnd ) ),
					// Symptôme, pour le diagnostic seulement — voir ci-dessus.
					recouvrementDuLibelleAffiche: arrondi(
						parseFloat( cs.paddingInlineStart ) +
							mesure.measureText( selectionne ).width -
							( s.clientWidth - voie )
					),
				};
			} );
		} );
		note( `chevron des selects : ${ JSON.stringify( chevrons ) }` );
		egal(
			[],
			chevrons
				.filter( ( c ) => c.reserve < c.voie )
				.map( ( c ) => `${ c.nom } (réserve ${ c.reserve } px < voie ${ c.voie } px)` ),
			'§7 : chaque select réserve à droite au moins la voie du chevron de wp-admin — aucun libellé affiché ne peut passer sous la flèche'
		);

		const lignesJournal = await historique.page.locator( '.massifs-historique-table tbody tr' ).count();
		assert( lignesJournal > 0, 'l’historique porte des lignes après la publication', '> 0', lignesJournal );

		const premiere = await texteSource( historique.page.locator( '.massifs-historique-table tbody tr' ).first() );
		note( `première ligne d’historique : ${ premiere }` );
		assert(
			premiere.includes( COMPTE_GESTIONNAIRE.login ) || premiere.toLowerCase().includes( 'gestionnaire' ),
			'§12 : la ligne d’historique nomme QUI a écrit',
			`un libellé du compte ${ COMPTE_GESTIONNAIRE.login }`,
			premiere
		);
		assert( /\d{4}-\d{2}-\d{2}|\d{1,2}\s\w+\s\d{4}/.test( premiere ), '§12 : la ligne d’historique porte QUAND', 'une date', premiere );

		// L'export CSV : un bouton de soumission qui détourne le formulaire de
		// filtres par `formaction`, donc SANS JavaScript, et qui emporte les filtres
		// saisis. C'est ce que le contrat #15 §4.1 décrit.
		const boutonExport = historique.page.locator( 'button[name="action"][value="massifs_exporter_historique"]' );
		egal( 1, await boutonExport.count(), 'l’écran d’historique propose l’export CSV, par un bouton de formulaire' );
		egal(
			`${ BASE }/wp-admin/admin-post.php`,
			await boutonExport.getAttribute( 'formaction' ),
			'§4.1 : l’export part sur admin-post.php — un lien qui fonctionne sans JavaScript'
		);
		egal(
			1,
			await historique.page.locator( 'form.massifs-historique-filtres input[name="_wpnonce"]' ).count(),
			'§4.1 : le formulaire d’export porte son nonce'
		);

		// Et il exporte réellement, avec la session du gestionnaire.
		const csv = await historique.page.evaluate( async () => {
			const formulaire = document.querySelector( 'form.massifs-historique-filtres' );
			const parametres = new URLSearchParams( new FormData( formulaire ) );
			parametres.set( 'action', 'massifs_exporter_historique' );
			const reponse = await fetch( `/wp-admin/admin-post.php?${ parametres }`, { credentials: 'same-origin' } );
			return { statut: reponse.status, type: reponse.headers.get( 'content-type' ) ?? '', corps: ( await reponse.text() ).slice( 0, 400 ) };
		} );
		egal( 200, csv.statut, 'l’export CSV répond au gestionnaire' );
		assert( csv.type.includes( 'csv' ), 'l’export est servi en text/csv', 'text/csv', csv.type );
		assert(
			csv.corps.split( '\n' ).length > 1,
			'l’export porte un en-tête et au moins une ligne',
			'≥ 2 lignes',
			csv.corps.slice( 0, 120 )
		);
		// Interdit 8 du contrat #13 : ni secret, ni code de secours, ni IP.
		assert(
			! /totp|secours|\b(?:\d{1,3}\.){3}\d{1,3}\b/i.test( csv.corps ),
			'interdit 8 : l’export CSV ne porte ni secret, ni code de secours, ni adresse IP',
			'aucun',
			csv.corps.slice( 0, 200 )
		);
		note( `première ligne du CSV : ${ csv.corps.split( '\n' )[ 0 ] }` );

		// ---- 6 bis. LE DÉFAUT DE DOMAINE CORRIGÉ PAR #15, ÉPROUVÉ DANS L'ÉCRAN
		//
		// Le gestionnaire vient d'écrire une SECONDE fois sur les mêmes couples
		// (massif, jour) — la fabrique avait posé une première journée complète. Le
		// journal porte donc, pour chaque massif, une première publication PUIS une
		// modification. C'est exactement la configuration où la dérivation par
		// parcours du lot 1 mentait : filtrée par auteur, ou coupée par une
		// frontière de page, elle déclarait « Première publication » sur une
		// correction. On lit l'écran à cheval sur la frontière.
		// L'auteur visé est CELUI QUI VIENT DE PUBLIER, jamais « la première option
		// de la liste » : l'ordre du sélecteur est un détail de rendu, et s'y fier
		// ferait filtrer sur le compte de la fabrique le jour où il change.
		const optionsAuteur = await historique.page.$$eval( 'select[name="auteur"] option', ( options ) =>
			options.map( ( o ) => ( { valeur: o.value, libelle: o.textContent.trim() } ) )
		);
		note( `auteurs proposés au filtre : ${ JSON.stringify( optionsAuteur ) }` );
		const auteurId = Number(
			optionsAuteur.find( ( o ) => o.valeur !== '0' && /gestionnaire/i.test( o.libelle ) )?.valeur ?? 0
		);
		assert( auteurId > 0, 'le gestionnaire qui vient de publier figure dans le filtre « auteur »', '> 0', auteurId );

		const lire = async ( parametres ) => {
			const vue = await contexte.newPage();
			await vue.goto( `${ BASE }/wp-admin/admin.php?page=massifs-historique&${ parametres }`, { waitUntil: 'load' } );
			const lignes = await vue.$$eval( '.massifs-historique-table tbody tr', ( trs ) =>
				trs.map( ( tr ) => ( {
					reference: ( tr.querySelector( '.massifs-historique-cellule--reference' )?.textContent ?? '' ).trim(),
					massif: ( tr.querySelector( '.massifs-historique-cellule--massif' )?.textContent ?? '' ).trim(),
					premiere: tr.classList.contains( 'massifs-historique-ligne--premiere' ),
					transition: ( tr.querySelector( '.massifs-historique-cellule--niveau' )?.textContent ?? '' ).replace( /\s+/g, ' ' ).trim(),
				} ) )
			);
			const resume = ( await vue.locator( '.massifs-historique-resume' ).textContent() ?? '' ).replace( /\s+/g, ' ' ).trim();
			await vue.close();
			return { lignes, resume };
		};

		// Frontière volontairement placée AU MILIEU des 25 modifications : page 1 =
		// 20 lignes, page 2 = les suivantes.
		const page1 = await lire( `auteur=${ auteurId }&par_page=20&paged=1` );
		const page2 = await lire( `auteur=${ auteurId }&par_page=20&paged=2` );
		note( `page 1 : ${ page1.lignes.length } lignes — ${ page1.resume }` );
		note( `page 2 : ${ page2.lignes.length } lignes — ${ page2.resume }` );

		assert( page1.lignes.length === 20, 'filtre par auteur, page 1 : la pagination coupe bien à 20 lignes', 20, page1.lignes.length );
		assert( page2.lignes.length > 0, 'filtre par auteur, page 2 : la frontière est réellement franchie', '> 0 ligne', page2.lignes.length );

		const references = [ ...page1.lignes, ...page2.lignes ].map( ( l ) => l.reference );
		egal( references.length, new Set( references ).size, 'PAGINATION : aucune ligne n’apparaît deux fois de part et d’autre de la frontière' );

		// Les 25 lignes du second passage sont des MODIFICATIONS. Aucune ne doit se
		// déclarer « première publication » — ni sous le filtre d'auteur, ni à la
		// frontière de page. C'est le défaut de domaine que #15 a corrigé.
		const modifications = [ ...page1.lignes, ...page2.lignes ].filter( ( l ) =>
			/remplac/i.test( l.transition )
		);
		const mensonges = [ ...page1.lignes, ...page2.lignes ].filter(
			( l ) => l.premiere && /remplac/i.test( l.transition )
		);
		note( `lignes portant une transition « remplacé par » : ${ modifications.length } sur ${ references.length }` );
		egal( [], mensonges.map( ( l ) => l.massif ), 'contrat #15 §0.2 : AUCUNE correction ne se déclare « première publication », ni sous filtre d’auteur ni à la frontière de page' );
		assert(
			modifications.length > 0,
			'le journal filtré porte bien des corrections — sans quoi l’assertion précédente serait vide de sens',
			'> 0 transition',
			modifications.length
		);

		await historique.page.close();

		// L'export est REFUSÉ à un anonyme, jusque dans son verbe : `admin_post_`
		// sans variante `nopriv`.
		const sansSession = await navigateur.newContext();
		try {
			const nu = await sansSession.request.get(
				`${ BASE }/wp-admin/admin-post.php?action=massifs_exporter_historique`,
				{ failOnStatusCode: false }
			);
			const corpsNu = await nu.text();
			assert(
				! ( nu.headers()[ 'content-type' ] ?? '' ).includes( 'csv' ),
				'l’export CSV n’est JAMAIS servi sans capacité',
				'aucun text/csv',
				`${ nu.status() } ${ nu.headers()[ 'content-type' ] } — ${ corpsNu.slice( 0, 120 ) }`
			);
		} finally {
			await sansSession.close();
		}

		// ---- 7. Accessibilité automatisée sur les deux écrans du portail
		const axeSource = readFileSync( resoudre( 'axe-core' ).replace( /index\.js$/, 'axe.min.js' ), 'utf8' );
		for ( const [ nom, url ] of [
			[ 'écran de publication', '/wp-admin/admin.php?page=massifs-publication&massifs_jour=aujourd_hui' ],
			[ 'écran d’historique', '/wp-admin/admin.php?page=massifs-historique' ],
		] ) {
			const vue = await contexte.newPage();
			await vue.goto( BASE + url, { waitUntil: 'load' } );
			await vue.addScriptTag( { content: axeSource } );
			const resultat = await vue.evaluate( () =>
				window.axe.run( document, { resultTypes: [ 'violations' ] } ).then( ( r ) =>
					r.violations.map( ( v ) => ( { id: v.id, impact: v.impact, n: v.nodes.length, cible: v.nodes[ 0 ]?.target?.join( ' ' ) ?? '' } ) )
				)
			);
			const bloquantes = resultat.filter( ( v ) => [ 'critical', 'serious' ].includes( v.impact ) );
			note( `axe-core sur ${ nom } : ${ resultat.length } violation(s) — ${ JSON.stringify( resultat ) }` );
			egal( [], bloquantes, `axe-core : aucune violation bloquante sur l’${ nom }` );

			// lang="fr" : le cœur le sert, mais un écran qui l'aurait perdu ne serait
			// pas annoncé dans la bonne langue.
			egal( 'fr-FR', await vue.evaluate( () => document.documentElement.lang ), `${ nom } : la page est annoncée en français` );
			await vue.close();
		}

		// ---- 7 bis. JAVASCRIPT COUPÉ — le portail publie quand même
		//
		// Contrainte n° 3 du brief, appliquée au portail : l'écran est complet sans
		// une ligne de JavaScript. Le vérifier en comptant les scripts enfilés ne
		// suffit pas — c'est une preuve d'absence, pas de fonctionnement. On coupe
		// le moteur et on publie pour de bon.
		const sansJs = await navigateur.newContext( { javaScriptEnabled: false } );
		try {
			await sansJs.addCookies( await contexte.cookies() );
			const vue = await sansJs.newPage();
			await vue.goto( `${ BASE }/wp-admin/admin.php?page=massifs-publication&massifs_jour=demain`, { waitUntil: 'load' } );

			egal( 25, await vue.locator( '.massifs-liste__element' ).count(), 'sans JS : les 25 massifs sont rendus par PHP' );
			egal( 50, await vue.locator( 'input.massifs-segmentee__radio' ).count(), 'sans JS : les 50 radios sont là' );
			egal( 2, await vue.locator( 'nav.massifs-jours a' ).count(), 'sans JS : le sélecteur de jour est fait de deux LIENS, pas de contrôles de formulaire' );

			// « Tout interdire » puis publier : deux soumissions HTML, zéro script.
			await vue.locator( 'button[value="preremplir_interdit"]' ).click();
			await vue.waitForLoadState( 'load' );
			const preremplis = await vue.locator( 'input.massifs-segmentee__radio[value="interdit"]:checked' ).count();
			egal( 25, preremplis, 'sans JS : le pré-remplissage « Tout interdire » coche les 25 massifs — c’est le serveur qui le fait' );

			// LA SOUMISSION EST CONSTRUITE DEPUIS LE FORMULAIRE SERVI, puis envoyée
			// par le contexte — cookies compris.
			//
			// Pourquoi pas un `click()` : l'écran de publication fait plus de 5 000 px
			// de haut une fois sa feuille de style absente, et le moteur
			// d'actionnabilité de Playwright n'y stabilise jamais le bouton (« waiting
			// for element to be stable », mesuré). Ce n'est PAS un défaut du produit —
			// un humain fait défiler et clique. Construire la charge utile depuis le
			// balisage réel éprouve exactement ce que la contrainte n° 3 promet : le
			// `action`, la `method`, les champs cachés et les 25 radios rendus par PHP
			// suffisent à publier. Ce que cette voie NE prouve pas, et qui est dit
			// plutôt que sous-entendu : le geste de clic lui-même.
			const soumission = await vue.evaluate( () => {
				const formulaire = document.querySelector( 'form.massifs-ecran__formulaire' );
				const bouton = formulaire.querySelector( 'button[type="submit"]' );
				const corps = new URLSearchParams( new FormData( formulaire ) );
				// La valeur du bouton soumis n'entre dans `FormData` que si le
				// navigateur l'a activé : on la pose comme le ferait un clic.
				corps.set( bouton.name, bouton.value );
				// `formulaire.action` NE DONNE PAS l'attribut : le formulaire porte un
				// champ caché nommé `action` (le nom de l'action `admin-post.php`), et
				// la recherche par nom d'un HTMLFormElement le renvoie à sa place. Il
				// faut lire l'attribut. C'est un piège classique, et il est ici garanti
				// par le contrat #14 §2, qui IMPOSE ce nom de champ.
				return {
					action: formulaire.getAttribute( 'action' ),
					methode: ( formulaire.getAttribute( 'method' ) ?? '' ).toLowerCase(),
					corps: corps.toString(),
				};
			} );
			egal( 'post', soumission.methode, 'sans JS : le formulaire de publication est en POST' );
			assert(
				soumission.action.endsWith( '/wp-admin/admin-post.php' ),
				'sans JS : il poste sur admin-post.php, jamais sur une route REST',
				'…/wp-admin/admin-post.php',
				soumission.action
			);

			const reponse = await sansJs.request.post( soumission.action, {
				headers: { 'content-type': 'application/x-www-form-urlencoded' },
				data: soumission.corps,
				maxRedirects: 0,
				failOnStatusCode: false,
			} );
			egal( 303, reponse.status(), 'sans JS : la publication répond 303 See Other — le seul code qui force un GET sur tous les agents' );
			const destination = reponse.headers().location ?? '';
			note( `sans JS, redirection PRG : ${ destination }` );
			assert(
				destination.includes( 'massifs_resultat=' ) && destination.includes( '#massifs-recapitulatif' ),
				'sans JS : la redirection porte le jeton de rapport ET le fragment contractuel',
				'…&massifs_resultat=…#massifs-recapitulatif',
				destination
			);

			await vue.goto( destination, { waitUntil: 'load' } );
			egal( 1, await vue.locator( '#massifs-recapitulatif' ).count(), 'sans JS : le récapitulatif est rendu' );
			note( `sans JS, récapitulatif : ${ await texteSource( vue.locator( '#massifs-recapitulatif' ) ) }` );
			egal( 25, await vue.locator( 'input.massifs-segmentee__radio[value="interdit"]:checked' ).count(), 'sans JS : après publication, l’écran rend l’état réellement enregistré' );

			// Et la page publique du LENDEMAIN porte bien ce qu'on vient d'écrire.
			const vitrine = await sansJs.newPage();
			await vitrine.goto( BASE + '/', { waitUntil: 'load' } );
			egal( 1, await vitrine.locator( '.bandeau-non-officialite' ).count(), 'sans JS : le bandeau de non-officialité reste servi' );
			await vitrine.close();
			await vue.close();
		} finally {
			await sansJs.close();
		}

		// ---- 8. Mobile réel 360 px sur les deux écrans du portail
		const mobile = await navigateur.newContext( { viewport: { width: 360, height: 780 } } );
		try {
			// La session vit dans `contexte` : on la recopie, cookies compris.
			await mobile.addCookies( await contexte.cookies() );
			for ( const [ nom, url ] of [
				[ 'écran de publication', '/wp-admin/admin.php?page=massifs-publication&massifs_jour=aujourd_hui' ],
				[ 'écran d’historique', '/wp-admin/admin.php?page=massifs-historique' ],
			] ) {
				const vue = await mobile.newPage();
				await vue.goto( BASE + url, { waitUntil: 'load' } );
				const mesure = await vue.evaluate( () => ( {
					defilement: document.documentElement.scrollWidth,
					fenetre: window.innerWidth,
					// wp-admin pose lui-même une largeur minimale ; on mesure NOTRE
					// racine, celle que l'issue possède, en plus du document.
					notre: ( () => {
						const racine = document.querySelector( '.massifs-ecran, .massifs-historique' );
						return racine ? Math.ceil( racine.getBoundingClientRect().right ) : -1;
					} )(),
				} ) );
				note( `${ nom } à 360 px : ${ JSON.stringify( mesure ) }` );
				assert(
					mesure.notre > 0 && mesure.notre <= mesure.fenetre + 1,
					`360 px : la racine de l’${ nom } tient dans la fenêtre`,
					`≤ ${ mesure.fenetre } px`,
					`${ mesure.notre } px`
				);
				// LA MESURE QUI COMPTE : le §12 dit « mobile réel 360 px », c'est-à-dire
				// pas de défilement horizontal de la PAGE. Mesurer la seule racine de
				// l'écran laisserait passer un tableau ou un champ qui déborde à
				// l'intérieur — c'est exactement ce qui se produit quand la feuille de
				// style n'est pas servie et que la zone défilante ne défile plus.
				assert(
					mesure.defilement <= mesure.fenetre + 1,
					`360 px : l’${ nom } n’impose AUCUN défilement horizontal à la page`,
					`scrollWidth ≤ ${ mesure.fenetre } px`,
					`${ mesure.defilement } px`
				);
				await vue.close();
			}
		} finally {
			await mobile.close();
		}
	} finally {
		await contexte.close();
	}
}

async function s29_portailEcrituresRefusees( navigateur ) {
	scenario( '29 — portail : aucune écriture sans authentification, et la lecture publique intacte (§5.4, contrat #13)' );

	poserEtat( 'jour-nominal' );

	const anonyme = await navigateur.newContext();
	try {
		// NON-RÉGRESSION : la lecture publique reste ouverte. C'est l'interdit 3 du
		// contrat #13 — un `rest_authentication_errors` global casserait la carte.
		const lecture = await anonyme.request.get( `${ BASE }/wp-json/massifs/v1/statuts`, { failOnStatusCode: false } );
		egal( 200, lecture.status(), 'GET /wp-json/massifs/v1/statuts reste servi en ANONYME — la carte et l’open data survivent au portail' );
		const charge = await lecture.json();
		egal( 25, Array.isArray( charge.massifs ) ? charge.massifs.length : -1, 'la lecture publique rend toujours les 25 massifs' );

		// Écriture REST anonyme. LA SONDE PORTE UN CORPS VALIDE : un POST sans
		// paramètre sort en 400 `rest_missing_callback_param`, le cœur validant les
		// arguments requis AVANT la permission — un 400 ne prouverait rien de la
		// garde.
		const corpsValide = {
			jour: 'aujourd_hui',
			statuts: [ { massif: 'sainte-victoire', niveau: 'autorise' } ],
			niveaux: { 'sainte-victoire': 'autorise' },
			massifs: { 'sainte-victoire': 'autorise' },
			empreinte: '0'.repeat( 40 ),
		};

		for ( const route of [ '/wp-json/massifs-portail/v1/publication', '/wp-json/massifs/v1/portail/historique' ] ) {
			for ( const methode of [ 'POST', 'PUT', 'PATCH', 'DELETE' ] ) {
				const reponse = await anonyme.request.fetch( BASE + route, {
					method: methode,
					data: corpsValide,
					failOnStatusCode: false,
				} );
				const statut = reponse.status();
				assert(
					[ 401, 403, 404, 405 ].includes( statut ),
					`${ methode } ${ route } en anonyme est refusé`,
					'401 | 403 | 404 | 405',
					statut
				);
				assert(
					statut !== 200 && statut !== 201,
					`${ methode } ${ route } en anonyme n’écrit RIEN`,
					'jamais 2xx',
					statut
				);
			}
		}

		// La route d'écriture, sondée avec un corps valide : 401, pas 400.
		const ecriture = await anonyme.request.post( `${ BASE }/wp-json/massifs-portail/v1/publication`, {
			data: corpsValide,
			failOnStatusCode: false,
		} );
		egal( 401, ecriture.status(), 'POST /massifs-portail/v1/publication en anonyme, AVEC un corps valide, répond 401 — la garde mord avant l’écriture' );
		const erreur = await ecriture.json();
		note( `refus REST : ${ JSON.stringify( erreur.code ?? erreur ) }` );

		// La lecture SENSIBLE de l'historique est gardée elle aussi : c'est le point
		// que le garde global du contrat #13 ne couvre PAS, et que #15 devait poser.
		const journal = await anonyme.request.get( `${ BASE }/wp-json/massifs/v1/portail/historique`, { failOnStatusCode: false } );
		assert( [ 401, 403 ].includes( journal.status() ), 'GET /massifs/v1/portail/historique en anonyme est refusé — l’historique n’est pas une donnée ouverte', '401 | 403', journal.status() );

		// admin-post.php : l'autre porte d'écriture, celle du formulaire.
		for ( const action of [ 'massifs_publier_statuts', 'massifs_exporter_historique' ] ) {
			const post = await anonyme.request.post( `${ BASE }/wp-admin/admin-post.php`, {
				form: { action, massifs_jour: 'aujourd_hui', massifs_intention: 'publier' },
				failOnStatusCode: false,
				maxRedirects: 0,
			} );
			const corps = await post.text();
			assert(
				post.status() !== 200 || /n(?:’|')avez pas|autorisation|connect/i.test( corps ),
				`admin-post.php?action=${ action } en anonyme n’exécute rien`,
				'un refus',
				`${ post.status() } — ${ corps.slice( 0, 120 ) }`
			);
			// Sans `nopriv`, le cœur répond « 0 » ou redirige : aucune de ces
			// réponses ne doit porter le récapitulatif d'une publication réussie.
			assert(
				! corps.includes( 'massifs-recapitulatif' ),
				`admin-post.php?action=${ action } en anonyme ne rend aucun récapitulatif de publication`,
				'aucun',
				corps.slice( 0, 160 )
			);
		}

		// Et aucun cookie n'a été posé au visiteur anonyme, sur aucune de ces portes.
		egal( [], ( await anonyme.cookies() ).map( ( c ) => c.name ), '§2 : sonder les portes du portail ne pose aucun cookie au visiteur anonyme' );
	} finally {
		await anonyme.close();
	}
}

async function s30_deuxFacteursEtSuspension( navigateur ) {
	scenario( '30 — second facteur qui redirige sans jamais refuser, et suspension qui tue la session (contrat #13)' );

	// ---- Jambe 1 : la RAMPE. Administrateur EXIGÉ mais NON ENRÔLÉ.
	//
	// C'est la propriété la plus lourde de conséquences du lot : un refus
	// enfermerait dehors l'administrateur de production, sans poignée intérieure.
	retirerTotp( COMPTE_ADMIN.login );
	purgerEcluse();

	const rampe = await navigateur.newContext();
	try {
		const trace = await connexion( rampe, COMPTE_ADMIN );

		assert(
			! trace.etape2,
			'RAMPE : un administrateur non enrôlé ne se voit PAS demander un code qu’il ne peut pas produire',
			'aucune étape 2',
			trace.urlEtape2
		);
		assert(
			( await rampe.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
			'RAMPE : la connexion ABOUTIT — la 2FA redirige, elle ne refuse jamais',
			'wordpress_logged_in_*',
			( await rampe.cookies() ).map( ( c ) => c.name ).join( ', ' ) || '(aucun)'
		);

		const vue = await rampe.newPage();
		await vue.goto( `${ BASE }/wp-admin/users.php`, { waitUntil: 'load' } );
		assert(
			vue.url().includes( 'profile.php' ),
			'RAMPE : tout écran d’administration renvoie vers l’enrôlement, sur le profil',
			'…/wp-admin/profile.php#massifs-2fa',
			vue.url()
		);
		egal( 1, await vue.locator( '#massifs-2fa' ).count(), 'RAMPE : la section d’enrôlement est bien rendue sur le profil' );
		assert(
			( await vue.content() ).includes( 'otpauth://' ),
			'RAMPE : le secret est proposé en texte ET en URI otpauth — lisible par un lecteur d’écran (A-7)',
			'une URI otpauth://',
			'absente'
		);
		// A-8 : `plugins.php` est redirigé aussi, sans quoi l'administrateur enfermé
		// ne pourrait même pas désactiver l'extension pour en sortir.
		await vue.goto( `${ BASE }/wp-admin/plugins.php`, { waitUntil: 'load' } );
		assert( vue.url().includes( 'profile.php' ), 'RAMPE : plugins.php est redirigé lui aussi — d’où l’existence de MASSIFS_DESACTIVER_2FA', 'profile.php', vue.url() );
		await vue.close();
	} finally {
		await rampe.close();
	}

	// ---- Jambe 2 : le second facteur ARMÉ, éprouvé de bout en bout
	enrolerTotp( COMPTE_ADMIN.login );
	purgerEcluse();

	const avecFacteur = await navigateur.newContext();
	try {
		const page = await avecFacteur.newPage();
		await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
		await page.fill( '#user_login', COMPTE_ADMIN.login );
		await page.fill( '#user_pass', COMPTE_ADMIN.motDePasse );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'load' );

		assert(
			page.url().includes( 'action=massifs_2fa' ),
			'2FA : le mot de passe seul mène à l’étape 2, jamais à l’administration',
			'wp-login.php?action=massifs_2fa',
			page.url()
		);
		egal(
			[],
			( await avecFacteur.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
			'2FA : AUCUN cookie de session n’est posé à l’étape 1 — le mot de passe seul n’ouvre rien'
		);

		// Un code faux : refusé, et l'étape 2 reste l'étape 2.
		await page.fill( '#massifs-2fa-code', '000000' );
		await page.click( 'button[type="submit"]' );
		await page.waitForLoadState( 'load' );
		assert( page.url().includes( 'action=massifs_2fa' ), '2FA : un code faux ne fait pas entrer', 'toujours l’étape 2', page.url() );
		egal(
			[],
			( await avecFacteur.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
			'2FA : un code faux ne pose aucun cookie de session'
		);

		// Le bon code, calculé ici, par une implémentation indépendante.
		const code = await codeTotp( SECRET_TOTP_RECETTE );
		await page.fill( '#massifs-2fa-code', code );
		await page.click( 'button[type="submit"]' );
		await page.waitForLoadState( 'load' );
		assert(
			! page.url().includes( 'wp-login.php' ),
			'2FA : le code calculé par une implémentation INDÉPENDANTE de la RFC 6238 est accepté',
			'une URL d’administration',
			page.url()
		);
		assert(
			( await avecFacteur.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
			'2FA : la session s’ouvre seulement après le second facteur',
			'wordpress_logged_in_*',
			( await avecFacteur.cookies() ).map( ( c ) => c.name ).join( ', ' ) || '(aucun)'
		);

		// Enrôlé, l'administrateur n'est plus renvoyé au profil : il atteint ses
		// écrans. C'est ce qui rend le scénario `gravatar` exécutable.
		const users = await page.goto( `${ BASE }/wp-admin/users.php`, { waitUntil: 'load' } );
		egal( 200, users.status(), 'ENRÔLÉ : l’administrateur atteint users.php — la rampe s’efface une fois le facteur armé' );

		// A-17, anti-rejeu : le MÊME code ne sert pas deux fois.
		const rejeu = await navigateur.newContext();
		try {
			const autre = await rejeu.newPage();
			await autre.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
			await autre.fill( '#user_login', COMPTE_ADMIN.login );
			await autre.fill( '#user_pass', COMPTE_ADMIN.motDePasse );
			await autre.click( '#wp-submit' );
			await autre.waitForLoadState( 'load' );
			await autre.fill( '#massifs-2fa-code', code );
			await autre.click( 'button[type="submit"]' );
			await autre.waitForLoadState( 'load' );
			assert(
				autre.url().includes( 'action=massifs_2fa' ),
				'A-17 : le même code REJOUÉ est refusé — un code intercepté ne vaut pas trois tentatives',
				'toujours l’étape 2',
				autre.url()
			);
			egal(
				[],
				( await rejeu.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
				'A-17 : le rejeu n’ouvre aucune session'
			);
			await autre.close();
		} finally {
			await rejeu.close();
		}

		// ---- Jambe 3 : la SUSPENSION tue la session EN COURS
		//
		// A-16 : « une suspension qui laisse vivre la session en cours n'est pas une
		// suspension : le compte continuerait de publier des statuts pendant des
		// heures. »
		const gestionnaire = await navigateur.newContext();
		try {
			purgerEcluse();
			await connexion( gestionnaire, COMPTE_GESTIONNAIRE );
			const sienne = await gestionnaire.newPage();
			const avant = await sienne.goto( `${ BASE }/wp-admin/admin.php?page=massifs-publication`, { waitUntil: 'load' } );
			egal( 200, avant.status(), 'le gestionnaire a bien une session vivante sur son écran de publication' );

			// La suspension passe par le point de passage du contrat #13, celui que
			// l'écran Utilisateurs déclenche — jamais un `update_user_meta` nu.
			const sortie = wp( [
				'eval',
				`wp_set_current_user( (int) get_user_by( 'login', '${ COMPTE_ADMIN.login }' )->ID );` +
					`$c = get_user_by( 'login', '${ COMPTE_GESTIONNAIRE.login }' );` +
					'$r = \\Massifs\\Security\\Roles\\Comptes::suspendre( (int) $c->ID );' +
					"echo is_wp_error( $r ) ? 'ERREUR:' . $r->get_error_message() : 'SUSPENDU';",
			] );
			assert( sortie.includes( 'SUSPENDU' ), 'l’administrateur suspend le compte gestionnaire', 'SUSPENDU', sortie.trim() );

			// La requête SUIVANTE, avec le cookie déjà en main.
			const apres = await sienne.goto( `${ BASE }/wp-admin/admin.php?page=massifs-publication`, { waitUntil: 'load' } );
			const corps = await sienne.content();
			const dehors =
				sienne.url().includes( 'wp-login.php' ) ||
				apres.status() === 403 ||
				/n(?:’|')avez pas (?:l(?:’|')autorisation|les droits)|Désolé, vous n(?:’|')avez pas/i.test( corps );
			assert(
				dehors,
				'A-16 : SUSPENSION — la session en cours est tuée, le compte suspendu ne publie plus une seule seconde',
				'déconnecté ou refusé',
				`${ apres.status() } — ${ sienne.url() }`
			);

			// Et la connexion suivante est refusée, avec le message distinct du §11.
			const relance = await navigateur.newContext();
			try {
				const rentrer = await relance.newPage();
				await rentrer.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
				await rentrer.fill( '#user_login', COMPTE_GESTIONNAIRE.login );
				await rentrer.fill( '#user_pass', COMPTE_GESTIONNAIRE.motDePasse );
				await rentrer.click( '#wp-submit' );
				await rentrer.waitForLoadState( 'load' );
				const texte = ( await rentrer.content() ).replace( /\s+/g, ' ' );
				assert(
					rentrer.url().includes( 'wp-login.php' ),
					'SUSPENSION : le compte suspendu ne peut plus se reconnecter',
					'wp-login.php',
					rentrer.url()
				);
				assert(
					texte.includes( 'Ce compte est suspendu' ),
					'SUSPENSION : le message contractuel du §11 est servi, distinct de l’échec d’identifiants',
					'« Ce compte est suspendu. Contactez un administrateur. »',
					texte.slice( texte.indexOf( 'login_error' ), texte.indexOf( 'login_error' ) + 220 )
				);
				await rentrer.close();
			} finally {
				await relance.close();
			}

			await sienne.close();
		} finally {
			// REMISE EN ÉTAT : sans elle, le compte de démonstration resterait
			// suspendu et tous les scénarios suivants échoueraient pour une raison
			// qui n'est pas la leur.
			const retabli = wp( [
				'eval',
				`wp_set_current_user( (int) get_user_by( 'login', '${ COMPTE_ADMIN.login }' )->ID );` +
					`$c = get_user_by( 'login', '${ COMPTE_GESTIONNAIRE.login }' );` +
					'$r = \\Massifs\\Security\\Roles\\Comptes::retablir( (int) $c->ID );' +
					"echo massifs_compte_est_suspendu( (int) $c->ID ) ? 'ENCORE_SUSPENDU' : 'RETABLI';",
			] );
			assert( retabli.includes( 'RETABLI' ), 'remise en état : le compte de démonstration est rétabli', 'RETABLI', retabli.trim() );
			await gestionnaire.close();
		}

		await page.close();
	} finally {
		await avecFacteur.close();
		// Le second facteur de recette est retiré : la stack repart comme elle est
		// arrivée, et l'administrateur de démonstration n'est pas laissé enrôlé sur
		// un secret écrit dans un fichier du dépôt.
		retirerTotp( COMPTE_ADMIN.login );
		purgerEcluse();
	}

	// ---- Jambe 4 : l'écluse, sur le VRAI formulaire de connexion
	//
	// Le scénario PHP `60-portail-journal-exact` éprouve l'algèbre de l'écluse en
	// pilotant l'IP par filtre — c'est le seul moyen de simuler deux origines. Ce
	// qu'il ne peut pas dire, c'est que l'écluse est réellement GREFFÉE sur
	// `wp-login.php`. C'est ce qui se mesure ici, en frappant le vrai formulaire.
	//
	// SEUIL VISÉ : le couple (identifiant × IP), 5 essais. On reste sous le seuil
	// d'IP (10) pour éprouver la GRANULARITÉ — c'est l'arbitrage A-13 : le verrou
	// ne doit pas être une arme contre le compte de démonstration.
	const ecluse = await navigateur.newContext();
	try {
		/**
		 * Une tentative de connexion, et le message que le formulaire rend.
		 *
		 * @param {string} login Identifiant soumis.
		 * @param {string} motDePasse Mot de passe soumis.
		 * @return {Promise<object>} URL d'arrivée et message d'erreur.
		 */
		const tenter = async ( login, motDePasse ) => {
			const vue = await ecluse.newPage();
			await vue.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
			await vue.fill( '#user_login', login );
			await vue.fill( '#user_pass', motDePasse );
			await vue.click( '#wp-submit' );
			await vue.waitForLoadState( 'load' );
			// Une connexion RÉUSSIE ne rend aucun `#login_error` : lire le texte du
			// localisateur attendrait alors trente secondes et interromprait le
			// scénario. On compte d'abord.
			const message =
				( await vue.locator( '#login_error' ).count() ) > 0
					? await texteSource( vue.locator( '#login_error' ) )
					: '';
			const url = vue.url();
			await vue.close();
			return { message, url };
		};

		// UNIFORMITÉ DU MESSAGE (A-18). Le cœur distingue `invalid_username` de
		// `incorrect_password`, ce qui offre l'énumération des comptes sur le
		// formulaire ; #13 l'écrase.
		//
		// La propriété à éprouver n'est PAS le texte exact — le cœur préfixe
		// « Erreur : » à toute notice de connexion, et exiger l'absence de ce
		// préfixe testerait WordPress, pas nous. La propriété est l'IDENTITÉ
		// RIGOUREUSE des deux messages : compte inexistant et mot de passe faux
		// doivent être indiscernables.
		const compteInexistant = await tenter( 'ce-compte-nexiste-pas-recette', 'peu-importe' );
		const motDePasseFaux = await tenter( COMPTE_GESTIONNAIRE.login, 'mauvais-mot-de-passe-0' );
		note( `compte inexistant : « ${ compteInexistant.message } »` );
		note( `mot de passe faux : « ${ motDePasseFaux.message } »` );
		egal(
			compteInexistant.message,
			motDePasseFaux.message,
			'A-18 : identifiant inexistant et mot de passe faux rendent un message RIGOUREUSEMENT identique — le formulaire n’énumère pas les comptes'
		);
		assert(
			motDePasseFaux.message.includes( 'Identifiant ou mot de passe incorrect.' ),
			'A-18 : c’est bien la chaîne contractuelle du §11 qui est servie',
			'…Identifiant ou mot de passe incorrect.',
			motDePasseFaux.message
		);

		let dernierMessage = motDePasseFaux.message;
		// Le seuil du couple est de 5 : deux tentatives sont déjà consommées
		// ci-dessus sur cet identifiant, il en reste trois à faire.
		for ( let essai = 2; essai <= 5; essai += 1 ) {
			dernierMessage = ( await tenter( COMPTE_GESTIONNAIRE.login, `mauvais-mot-de-passe-${ essai }` ) ).message;
		}

		note( `message au 5ᵉ échec : ${ dernierMessage }` );
		assert(
			/\d/.test( dernierMessage ),
			'FORCE BRUTE : au franchissement du seuil, le formulaire annonce un DÉLAI CHIFFRÉ — l’utilisateur légitime sait combien attendre',
			'un message portant un nombre de minutes',
			dernierMessage
		);

		// LA LIGNE DE DoD « force brute bloquée ». Une fois le verrou posé, la
		// tentative SUIVANTE doit être refusée — y compris, et surtout, avec le BON
		// mot de passe : c'est ce qui distingue un verrou d'un simple message.
		// L'arbitrage A-14 en fait même la raison d'être de la priorité 1 :
		// « rejette une requête verrouillée SANS JAMAIS VÉRIFIER LE MOT DE PASSE ».
		const encoreFaux = await tenter( COMPTE_GESTIONNAIRE.login, 'mauvais-mot-de-passe-6' );
		note( `tentative suivant le verrou (mot de passe faux) : « ${ encoreFaux.message } »` );
		assert(
			/\d/.test( encoreFaux.message ),
			'FORCE BRUTE : une fois le verrou posé, la tentative SUIVANTE est barrée et le délai est rappelé',
			'un message de verrouillage portant un délai',
			encoreFaux.message
		);

		const bonMotDePasse = await tenter( COMPTE_GESTIONNAIRE.login, COMPTE_GESTIONNAIRE.motDePasse );
		assert(
			bonMotDePasse.url.includes( 'wp-login.php' ),
			'FORCE BRUTE : le verrou tient même contre le bon mot de passe — aucun oracle n’est offert',
			'wp-login.php',
			bonMotDePasse.url
		);
		egal(
			[],
			( await ecluse.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
			'FORCE BRUTE : aucune session n’est ouverte pendant le verrou'
		);

		// A-13, ÉPROUVÉ SUR LE FORMULAIRE : le verrou porte sur le COUPLE
		// (identifiant × origine), pas sur l'identifiant. Depuis la MÊME origine, un
		// autre compte reste joignable — a fortiori depuis une autre origine. Le
		// compte de démonstration ne peut donc pas être éteint par un tiers.
		const autreCompte = await tenter( COMPTE_ADMIN.login, 'un-mot-de-passe-faux' );
		note( `message pour l’autre compte, même origine : « ${ autreCompte.message } »` );
		egal(
			motDePasseFaux.message,
			autreCompte.message,
			'A-13 : le verrou d’un identifiant n’atteint PAS un autre identifiant depuis la même origine — le compte de démonstration n’est pas éteignable à volonté'
		);
	} finally {
		// REMISE EN ÉTAT INDISPENSABLE : sans elle, l'origine de la recette reste
		// verrouillée quinze minutes et TOUS les scénarios suivants échouent à la
		// connexion pour une raison qui n'est pas la leur.
		purgerEcluse();
		await ecluse.close();
	}

	// La purge est vérifiée, pas supposée.
	const apresPurge = await navigateur.newContext();
	try {
		await connexion( apresPurge, COMPTE_GESTIONNAIRE );
		assert(
			( await apresPurge.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
			'remise en état : l’écluse est purgée, le compte de démonstration se reconnecte',
			'wordpress_logged_in_*',
			( await apresPurge.cookies() ).map( ( c ) => c.name ).join( ', ' ) || '(aucun)'
		);
	} finally {
		await apresPurge.close();
	}

	// ---- Jambe 5 : L'ÉTAPE 2 DU SECOND FACTEUR SOUS VERROU (correctif `2ffba8d`)
	//
	// LE TROU QUE CETTE JAMBE FERME. L'étape 2 ne traverse PAS `authenticate` :
	// elle est servie par `Deuxfacteurs::traiter()`, qui appelle directement
	// `wp_set_auth_cookie()`. Les trois greffes de l'écluse (priorités 1, 40 et
	// 100) n'y sont donc d'aucun secours. Un jeton d'étape 2 obtenu AVANT un
	// verrou, présenté avec le bon code PENDANT ce verrou, ouvrait une session —
	// le verrou était contournable en deux temps par qui connaît le mot de passe.
	// D'où `Ecluse::attente()` opposé en TÊTE de `traiter()`, avant le comptage
	// des essais et avant toute vérification du code.
	//
	// Ce chemin est INOBSERVABLE en PHP : la seule preuve est qu'aucun cookie de
	// session n'est posé par une vraie soumission du vrai formulaire d'étape 2.
	//
	// LE SECOND FACTEUR EST RÉARMÉ ICI. La jambe 2 le retire dans son `finally`
	// pour ne pas laisser la stack enrôlée sur un secret du dépôt. Sans ce
	// réarmement, la connexion de l'administrateur emprunte la RAMPE — elle
	// aboutit directement sur `profile.php`, il n'y a pas d'étape 2, et la jambe
	// mesurerait le vide en se croyant verte. C'est exactement ce qui est arrivé
	// à sa première exécution, le 16 août 2026.
	purgerEcluse();
	enrolerTotp( COMPTE_ADMIN.login );

	const etape2 = await navigateur.newContext();
	const bourreau = await navigateur.newContext();
	try {
		const vue = await etape2.newPage();
		await vue.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
		await vue.fill( '#user_login', COMPTE_ADMIN.login );
		await vue.fill( '#user_pass', COMPTE_ADMIN.motDePasse );
		await vue.click( '#wp-submit' );
		await vue.waitForLoadState( 'load' );

		assert(
			vue.url().includes( 'action=massifs_2fa' ),
			'ÉTAPE 2 SOUS VERROU : le jeton d’étape 2 est obtenu AVANT le verrou — c’est la prémisse du contournement',
			'wp-login.php?action=massifs_2fa',
			vue.url()
		);
		egal(
			[],
			( await etape2.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
			'ÉTAPE 2 SOUS VERROU : aucune session à ce stade — le mot de passe seul n’ouvre rien'
		);

		// Le verrou est posé APRÈS, depuis la même origine, sur le couple
		// (identifiant × IP) : cinq échecs, seuil du couple. On passe par le VRAI
		// formulaire, jamais par un verrou écrit à la main — un verrou posé de
		// l'extérieur ne prouverait pas que le chemin réel y mène.
		for ( let essai = 1; essai <= 5; essai += 1 ) {
			const frappe = await bourreau.newPage();
			await frappe.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
			await frappe.fill( '#user_login', COMPTE_ADMIN.login );
			await frappe.fill( '#user_pass', `verrou-etape2-${ essai }` );
			await frappe.click( '#wp-submit' );
			await frappe.waitForLoadState( 'load' );
			await frappe.close();
		}

		// Et maintenant le BON code, sur le jeton légitime obtenu avant le verrou.
		const codeSousVerrou = await codeTotp( SECRET_TOTP_RECETTE );
		await vue.fill( '#massifs-2fa-code', codeSousVerrou );
		await vue.click( 'button[type="submit"]' );
		await vue.waitForLoadState( 'load' );

		// L'étape 2 rend ses erreurs dans son propre bloc `#massifs-2fa-erreur`,
		// jamais dans le `#login_error` du cœur — elle ne traverse pas
		// `wp_login_form()`. Viser `#login_error` ici rendait une chaîne vide, et
		// l'assertion du délai chiffré était rouge pour une raison de recette.
		const messageSousVerrou =
			( await vue.locator( '#massifs-2fa-erreur' ).count() ) > 0
				? await texteSource( vue.locator( '#massifs-2fa-erreur' ) )
				: '';
		note( `étape 2 présentée sous verrou : « ${ messageSousVerrou } » → ${ vue.url() }` );

		egal(
			[],
			( await etape2.cookies() ).filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).map( ( c ) => c.name ),
			'ÉTAPE 2 SOUS VERROU : AUCUNE SESSION n’est ouverte — un jeton d’étape 2 antérieur au verrou ne le contourne pas'
		);
		assert(
			vue.url().includes( 'wp-login.php' ),
			'ÉTAPE 2 SOUS VERROU : le bon code ne mène pas à l’administration',
			'wp-login.php',
			vue.url()
		);
		// Le message doit être celui de l'écluse — chiffré —, pas « Code incorrect » :
		// si c'était « Code incorrect », le refus viendrait de la vérification du
		// code et non du verrou, et l'assertion précédente passerait pour une
		// mauvaise raison.
		assert(
			/\d/.test( messageSousVerrou ) && messageSousVerrou.includes( 'tentatives' ),
			'ÉTAPE 2 SOUS VERROU : c’est bien le VERROU qui refuse, avec son délai chiffré — pas la vérification du code',
			'le message de l’écluse, portant un délai',
			messageSousVerrou
		);
		await vue.close();
	} finally {
		purgerEcluse();
		await bourreau.close();
		await etape2.close();
	}

	// CONTRÔLE OBLIGATOIRE : sans lui, la jambe ci-dessus passerait aussi si le
	// second facteur était tout simplement CASSÉ. On rejoue la même séquence,
	// verrou levé, et la session DOIT s'ouvrir.
	//
	// Le code est renouvelé au pas suivant si nécessaire : l'anti-rejeu A-17
	// refuserait un code déjà présenté, et le refus serait alors imputé au verrou
	// par erreur.
	const temoin = await navigateur.newContext();
	try {
		const vue = await temoin.newPage();
		await vue.goto( `${ BASE }/wp-login.php`, { waitUntil: 'load' } );
		await vue.fill( '#user_login', COMPTE_ADMIN.login );
		await vue.fill( '#user_pass', COMPTE_ADMIN.motDePasse );
		await vue.click( '#wp-submit' );
		await vue.waitForLoadState( 'load' );

		// GARDE DU TÉMOIN : sans elle, un administrateur DÉSENRÔLÉ passerait par la
		// rampe, arriverait sur `profile.php` avec un cookie, et le témoin serait
		// vert sans qu'aucune étape 2 n'ait eu lieu — il ne contrôlerait plus rien.
		assert(
			vue.url().includes( 'action=massifs_2fa' ),
			'TÉMOIN : la séquence passe bien par une étape 2 — le contrôle porte sur le même chemin que la jambe sous verrou',
			'wp-login.php?action=massifs_2fa',
			vue.url()
		);

		// Attendre la fenêtre TOTP suivante garantit un code jamais présenté.
		const resteMs = ( 30 - ( Math.floor( Date.now() / 1000 ) % 30 ) ) * 1000 + 1500;
		await vue.waitForTimeout( resteMs );

		await vue.fill( '#massifs-2fa-code', await codeTotp( SECRET_TOTP_RECETTE ) );
		await vue.click( 'button[type="submit"]' );
		await vue.waitForLoadState( 'load' );

		assert(
			( await temoin.cookies() ).some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ),
			'TÉMOIN : verrou levé, la MÊME séquence d’étape 2 ouvre bien une session — le refus précédent venait du verrou, pas d’un second facteur cassé',
			'wordpress_logged_in_*',
			( await temoin.cookies() ).map( ( c ) => c.name ).join( ', ' ) || '(aucun)'
		);
	} finally {
		purgerEcluse();
		// La stack repart désenrôlée, comme la jambe 2 la laissait.
		retirerTotp( COMPTE_ADMIN.login );
		await temoin.close();
	}
}

async function s31_carteSelectionEtPaliers( navigateur ) {
	scenario( '31 — carte : le massif sélectionné garde son aplat et son motif, à tous les paliers (issue #50)' );

	poserEtat( 'jour-complet', 20 );

	/**
	 * Relève ce qui est réellement peint sur un massif et sur le cerne.
	 *
	 * @param {import('playwright-core').Page} page Page chargée.
	 * @param {string} nom Nom du massif observé, tel qu'il ouvre son `aria-label`.
	 * @return {Promise<object>} Relevé.
	 */
	const relever = ( page, nom ) =>
		page.evaluate( ( nomMassif ) => {
			const racine = document.querySelector( '.carte' );
			const style = getComputedStyle( racine );
			// Les tracés ne portent PAS de `data-code` : leur seule prise stable est
			// l'`aria-label`, composé côté serveur de « <Massif> — <libellé du
			// niveau> ». C'est aussi ce qu'un lecteur d'écran reçoit.
			const massif = [ ...document.querySelectorAll( 'path.carte__massif' ) ].find( ( p ) =>
				( p.getAttribute( 'aria-label' ) ?? '' ).toLowerCase().startsWith( nomMassif.toLowerCase() )
			);
			const interdit = document.querySelector( 'path.carte__massif--interdit' );

			const cerne = document.querySelector( 'path.carte__cerne' );
			const separateur = document.querySelector( 'path.carte__cerne-separateur' );
			const paneCerne = document.querySelector( '.carte__pane--cerne' );
			const paneMassifs = document.querySelector( '.carte__pane--massifs' );

			const mesure = ( element ) => {
				if ( ! element ) {
					return null;
				}
				const s = getComputedStyle( element );
				return { fill: s.fill, stroke: s.stroke, epaisseur: s.strokeWidth, join: s.strokeLinejoin };
			};

			return {
				paliers: [ ...racine.classList ].filter( ( c ) => c.startsWith( 'carte--echelle-' ) ),
				jetons: {
					lisere: style.getPropertyValue( '--carte-lisere' ).trim(),
					survol: style.getPropertyValue( '--carte-survol' ).trim(),
					cerne: style.getPropertyValue( '--carte-cerne' ).trim(),
					cerneClair: style.getPropertyValue( '--carte-cerne-clair' ).trim(),
				},
				massif: mesure( massif ),
				massifLabel: massif ? massif.getAttribute( 'aria-label' ) : null,
				massifClasses: massif ? [ ...massif.classList ] : [],
				// Le motif est ce qui empêche l'information d'être portée par la
				// couleur seule : sur un massif interdit, le `fill` DOIT rester une
				// référence de `<pattern>`, sélection comprise.
				interdit: mesure( interdit ),
				cerne: mesure( cerne ),
				separateur: mesure( separateur ),
				cerneEpaisseurEcran: cerne ? Number( getComputedStyle( cerne ).strokeWidth.replace( 'px', '' ) ) : null,
				separateurEpaisseurEcran: separateur ? Number( getComputedStyle( separateur ).strokeWidth.replace( 'px', '' ) ) : null,
				zIndexCerne: paneCerne ? getComputedStyle( paneCerne ).zIndex : null,
				zIndexMassifs: paneMassifs ? getComputedStyle( paneMassifs ).zIndex : null,
				ordreDom: paneCerne && paneMassifs
					? ( paneCerne.compareDocumentPosition( paneMassifs ) & Node.DOCUMENT_POSITION_FOLLOWING ? 'cerne avant massifs' : 'massifs avant cerne' )
					: null,
				panneauOuvert: racine.classList.contains( 'carte--panneau-ouvert' ),
				zoom: window.__massifsZoomRecette ?? null,
			};
		}, nom );

	const contexte = await navigateur.newContext( { viewport: { width: 1280, height: 900 } } );
	try {
		const page = await contexte.newPage();
		const erreursJs = [];
		page.on( 'pageerror', ( e ) => erreursJs.push( e.message ) );
		await page.goto( BASE + '/', { waitUntil: 'networkidle' } );
		await page.waitForSelector( '.carte--prete', { timeout: 20000 } );

		// Le zoom réel est lu sur l'instance Leaflet quand elle est joignable ;
		// sinon, la classe de palier fait foi — c'est elle qui porte le contrat.
		await page.evaluate( () => {
			const conteneur = document.querySelector( '.leaflet-container' );
			window.__massifsZoomRecette = conteneur && conteneur._leaflet_id && window.L
				? null
				: null;
		} );

		const regagnas = page.locator( 'path.carte__massif' ).filter( { has: page.locator( 'title' ) } );
		note( `tracés de massif présents : ${ await page.locator( 'path.carte__massif' ).count() }` );

		// Regagnas est nommé par le contrat #50 §9 : c'est le massif filamenteux sur
		// lequel le défaut a été mesuré. On le retrouve par son `aria-label`, la
		// seule prise stable côté serveur.
		const cible = page.locator( 'path.carte__massif' ).filter( { hasText: /Regagnas/i } ).first();
		const parLabel = page.locator( 'path.carte__massif[aria-label*="Regagnas" i]' ).first();
		const selecteur = ( await parLabel.count() ) > 0 ? parLabel : cible;
		assert( ( await selecteur.count() ) > 0, 'contrat #50 §9 : le tracé de Regagnas est identifiable dans la carte servie', '≥ 1 tracé', 0 );

		// ---- Palier DÉPARTEMENT, Regagnas sélectionné. C'EST L'ASSERTION QUI MANQUAIT.
		const avant = await relever( page, 'Regagnas' );
		note( `au cadrage initial : paliers=${ JSON.stringify( avant.paliers ) } jetons=${ JSON.stringify( avant.jetons ) }` );
		egal( 1, avant.paliers.length, 'exactement UNE classe de palier sur la racine de la carte' );

		await selecteur.click();
		await page.waitForTimeout( 300 );
		const apres = await relever( page, 'Regagnas' );
		note( `Regagnas sélectionné : ${ JSON.stringify( { paliers: apres.paliers, massif: apres.massif, cerne: apres.cerne, separateur: apres.separateur } ) }` );

		assert( apres.panneauOuvert, 'la sélection ouvre le panneau du massif', '.carte--panneau-ouvert', 'absente' );

		// L'aplat de statut et son motif restent ENTIERS : le `fill` du polygone
		// n'est ni écrasé, ni recouvert, et il reste soit une couleur de statut soit
		// une référence de motif.
		assert(
			apres.massif && apres.massif.fill !== 'none' && ! TRANSPARENT.has( apres.massif.fill ),
			'contrat #50 §9.1 : l’aplat de statut du massif SÉLECTIONNÉ reste peint',
			'un fill non transparent',
			JSON.stringify( apres.massif )
		);
		egal( avant.massif?.fill, apres.massif?.fill, 'contrat #50 §9.1 : la sélection ne CHANGE PAS l’aplat de statut' );
		note( `massif observé : ${ apres.massifLabel } — classes ${ JSON.stringify( apres.massifClasses ) }` );

		// LE MOTIF, pas seulement la couleur. Sur un massif interdit, le `fill` doit
		// rester une référence de `<pattern>` : c'est ce qui empêche l'information
		// d'être portée par la couleur seule, sélection comprise (§12, §8 du brief).
		assert(
			apres.interdit && /url\(/.test( apres.interdit.fill ),
			'§12 : un massif INTERDIT garde son motif — l’information n’est jamais portée par la couleur seule, sélection comprise',
			'un fill url(#…) de <pattern>',
			JSON.stringify( apres.interdit )
		);

		// Les deux couches du cerne : `fill: none`, invariant I-50.1. Un `fill` ici
		// remplirait l'anneau et recouvrirait l'aplat.
		egal( 'none', apres.cerne?.fill, 'I-50.1 : la couche charbon du cerne est fill:none' );
		egal( 'none', apres.separateur?.fill, 'I-50.1 : la couche calcaire du cerne est fill:none' );
		egal( 'round', apres.cerne?.join, 'I-50.3 : stroke-linejoin:round sur le cerne — pas de pointe de 52 px sur un angle aigu' );
		egal( 'round', apres.separateur?.join, 'I-50.3 : stroke-linejoin:round sur le séparateur' );

		// LE DÉFAUT DE LA v2.3, à l'endroit exact : au palier département, AUCUNE
		// peinture claire n'est posée. `--carte-cerne-clair: 0`.
		if ( apres.paliers.includes( 'carte--echelle-departement' ) ) {
			egal( '0', apres.jetons.cerneClair, 'D5 / §9.2.a : au palier DÉPARTEMENT, --carte-cerne-clair vaut 0' );
			egal( 0, apres.separateurEpaisseurEcran, 'contrat #50 §9.1 : AUCUN pixel calcaire n’est posé sur la carte au palier département — c’est l’assertion qui a manqué à la v2.3' );
			assert(
				apres.cerneEpaisseurEcran > 0,
				'le cerne charbon, lui, est bien peint au palier département',
				'> 0 px',
				apres.cerneEpaisseurEcran
			);
		} else {
			note( `cadrage initial hors palier département (${ apres.paliers.join( ',' ) }) : l’assertion du calcaire est portée par la boucle de paliers ci-dessous` );
		}

		// Empilement : le cerne passe SOUS les massifs. Deux mécanismes, tous deux
		// vérifiés — z-index CSS et ordre d'insertion DOM (D4).
		egal( '400', apres.zIndexCerne, 'D4 : le pane du cerne est en z-index 400' );
		egal( '410', apres.zIndexMassifs, 'D4 : le pane des massifs est en z-index 410 — l’aplat n’est jamais recouvert' );
		egal( 'cerne avant massifs', apres.ordreDom, 'D4 : les panes sont insérés dans l’ordre, secours si le z-index CSS n’arrive pas' );

		// ---- Les trois paliers, par le chemin réel du zoom
		const paliersVus = {};
		for ( let cran = 0; cran < 8; cran += 1 ) {
			const releve = await relever( page, 'Regagnas' );
			const palier = releve.paliers[ 0 ] ?? '(aucun)';
			if ( ! paliersVus[ palier ] ) {
				paliersVus[ palier ] = releve;
			}
			// `+` est le chemin clavier réel de la carte, celui que le contrat #7 a
			// réimplémenté : on ne touche pas l'API Leaflet directement.
			await page.keyboard.press( '+' );
			await page.waitForTimeout( 350 );
		}
		note( `paliers traversés : ${ Object.keys( paliersVus ).join( ' · ' ) }` );

		for ( const attendu of [ 'carte--echelle-departement', 'carte--echelle-massif', 'carte--echelle-abords' ] ) {
			assert( Boolean( paliersVus[ attendu ] ), `§9.2.a : le palier ${ attendu } est atteint par le zoom réel`, 'atteint', Object.keys( paliersVus ).join( ' · ' ) );
		}

		for ( const [ palier, releve ] of Object.entries( paliersVus ) ) {
			if ( palier === '(aucun)' ) {
				continue;
			}
			note( `${ palier } : lisere=${ releve.jetons.lisere } survol=${ releve.jetons.survol } cerne=${ releve.jetons.cerne } cerne-clair=${ releve.jetons.cerneClair }` );

			// §10.2.a : le liseré ne descend JAMAIS sous 1,5 px, et 1,5 px n'existe
			// qu'au palier département — mesuré, pas choisi.
			const lisere = Number.parseFloat( releve.jetons.lisere );
			assert(
				Number.isFinite( lisere ) && lisere >= 1.5,
				`§10.2.a : au palier ${ palier }, le liseré ne descend pas sous 1,5 px`,
				'≥ 1.5 px',
				releve.jetons.lisere
			);

			// L'aplat reste peint, à tous les paliers, sélection comprise.
			assert(
				releve.massif && releve.massif.fill !== 'none' && ! TRANSPARENT.has( releve.massif.fill ),
				`au palier ${ palier }, l’aplat de statut reste peint`,
				'un fill non transparent',
				JSON.stringify( releve.massif )
			);

			// Le survol vaut 1,5 × le liseré du palier, ARRONDI AU DEMI-PIXEL
			// SUPÉRIEUR (MASTER §9.2.a, règle de tenue 1 : « 1,5 → 2,5 · 2 → 3 ·
			// 3 → 4,5 »). C'est un RAPPORT qui se recalcule, pas trois nombres — et
			// l'arrondi fait partie de la règle : 1,5 × 1,5 = 2,25 donne bien 2,5.
			const survol = Number.parseFloat( releve.jetons.survol );
			const attenduSurvol = Math.ceil( ( lisere * 1.5 ) / 0.5 ) * 0.5;
			assert(
				Number.isFinite( survol ) && Math.abs( survol - attenduSurvol ) < 0.02,
				`I-50.5 : au palier ${ palier }, le survol vaut 1,5 × le liseré, arrondi au demi-pixel supérieur`,
				`${ attenduSurvol } px`,
				releve.jetons.survol
			);

			// L'encre totale hors de la forme vaut `--carte-cerne` ÷ 2 : la seule
			// valeur que MASTER §9.2.a demande de surveiller, parce que c'est elle
			// qui fusionne d'un filament au suivant sur un massif comme Regagnas.
			const halo = Number.parseFloat( releve.jetons.cerne ) / 2;
			note( `${ palier } : encre hors de la forme = ${ halo } px` );
			assert(
				halo <= 6.5,
				`§9.2.a : au palier ${ palier }, le halo du cerne reste sous le maximum tabulé (6,5 px, palier abords)`,
				'≤ 6.5 px',
				`${ halo } px`
			);
		}

		egal(
			'0',
			paliersVus[ 'carte--echelle-departement' ]?.jetons.cerneClair,
			'D5 : au palier département, et à lui seul, la couche calcaire est à zéro'
		);
		assert(
			Number.parseFloat( paliersVus[ 'carte--echelle-abords' ]?.jetons.cerneClair ?? '0' ) > 0,
			'D5 : au palier abords, la couche calcaire est bien peinte — le cerne se lit sur le fond',
			'> 0',
			paliersVus[ 'carte--echelle-abords' ]?.jetons.cerneClair
		);

		// ---- Échap ferme le panneau, ET LE CERNE RESTE (contrat #50 §9.6)
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 250 );
		const apresEchap = await relever( page, 'Regagnas' );
		assert( ! apresEchap.panneauOuvert, 'Échap ferme le panneau du massif', 'panneau fermé', 'toujours ouvert' );
		assert(
			apresEchap.cerne !== null,
			'contrat #50 §9.6 : Échap ferme le panneau et LE CERNE RESTE — la sélection n’est pas perdue',
			'les couches du cerne toujours présentes',
			JSON.stringify( apresEchap.cerne )
		);
		assert(
			apresEchap.massif && apresEchap.massif.fill !== 'none',
			'après Échap, l’aplat de statut est toujours peint',
			'un fill non transparent',
			JSON.stringify( apresEchap.massif )
		);

		egal( [], erreursJs, 'aucune erreur JavaScript pendant tout le parcours de la carte' );
		await page.close();
	} finally {
		await contexte.close();
	}

	// ---- 360 px, zoom texte 200 %, et forced-colors : les trois contrôles que la
	// chaîne #50 n'a pas revérifiés après son correctif (§9.9 et §9.10).
	for ( const [ nom, options ] of [
		[ '360 px', { viewport: { width: 360, height: 780 } } ],
		[ 'zoom texte 200 % (équivalent 320 px de contenu)', { viewport: { width: 320, height: 780 } } ],
		[ 'forced-colors: active', { viewport: { width: 1280, height: 900 }, forcedColors: 'active' } ],
	] ) {
		const contexteMesure = await navigateur.newContext( options );
		try {
			const vue = await contexteMesure.newPage();
			await vue.goto( BASE + '/', { waitUntil: 'networkidle' } );
			await vue.waitForSelector( '.carte--prete', { timeout: 20000 } );

			const cible = vue.locator( 'path.carte__massif[aria-label*="Regagnas" i]' ).first();
			if ( ( await cible.count() ) > 0 ) {
				await cible.click( { force: true } );
				await vue.waitForTimeout( 300 );
			}

			const mesure = await vue.evaluate( () => {
				const racine = document.querySelector( '.carte' );
				const cerne = document.querySelector( 'path.carte__cerne' );
				const separateur = document.querySelector( 'path.carte__cerne-separateur' );
				const massif = document.querySelector( 'path.carte__massif' );
				return {
					defilement: document.documentElement.scrollWidth,
					fenetre: window.innerWidth,
					carteDroite: Math.ceil( racine.getBoundingClientRect().right ),
					palier: [ ...racine.classList ].filter( ( c ) => c.startsWith( 'carte--echelle-' ) ),
					cerneStroke: cerne ? getComputedStyle( cerne ).stroke : null,
					separateurStroke: separateur ? getComputedStyle( separateur ).stroke : null,
					massifFill: massif ? getComputedStyle( massif ).fill : null,
					motifs: document.querySelectorAll( '.carte__pane--massifs pattern' ).length,
				};
			} );
			note( `${ nom } : ${ JSON.stringify( mesure ) }` );

			assert(
				mesure.defilement <= mesure.fenetre + 1,
				`§9.9 : à ${ nom }, aucun défilement horizontal`,
				`scrollWidth ≤ ${ mesure.fenetre }`,
				mesure.defilement
			);
			assert(
				mesure.carteDroite <= mesure.fenetre + 1,
				`§9.9 : à ${ nom }, la carte tient dans la fenêtre`,
				`≤ ${ mesure.fenetre } px`,
				mesure.carteDroite
			);
			egal( 1, mesure.palier.length, `${ nom } : exactement une classe de palier` );
			assert(
				mesure.motifs >= 3,
				`${ nom } : les trois motifs de statut restent définis — l’information n’est jamais portée par la couleur seule`,
				'≥ 3 <pattern>',
				mesure.motifs
			);

			if ( options.forcedColors === 'active' ) {
				// §9.10 : le cerne se reconstruit en couleurs système. La seule chose
				// qu'on peut affirmer sans mentir, c'est que le trait EXISTE encore et
				// n'est pas transparent — Chromium n'expose pas le nom `CanvasText`.
				assert(
					mesure.cerneStroke && ! TRANSPARENT.has( mesure.cerneStroke ),
					'§9.10 : sous forced-colors, le cerne garde un trait visible',
					'un stroke non transparent',
					mesure.cerneStroke
				);
				note( `sous forced-colors : cerne=${ mesure.cerneStroke } séparateur=${ mesure.separateurStroke } aplat=${ mesure.massifFill }` );
			}

			await vue.close();
		} finally {
			await contexteMesure.close();
		}
	}
}

// ---------------------------------------------------------------- lancement

const SCENARIOS = [
	[ 'tierce', s01_zeroRequeteTierce ],
	[ 'sans-js', s02_sansJavascript ],
	[ 'structure', s03_structureEtAncres ],
	[ 'perime', s04_statutPerimeJamaisCourant ],
	[ 'non-officialite', s05_bandeauNonOfficialite ],
	[ 'couleur', s06_jamaisLaCouleurSeule ],
	[ 'mobile', s07_mobile360 ],
	[ 'a11y', s08_accessibiliteAutomatisee ],
	[ 'budgets', s09_budgets ],
	[ 'api', s10_apiPublique ],
	[ 'ancre', s11_ancreListeSansPartie ],
	[ 'extension', s13_extensionDesactivee ],
	[ 'artefacts', s12_integriteArtefacts ],
	[ 'couche-statut', s14_coucheVisuelleDesStatuts ],
	[ 'feuilles', s15_ordreDesFeuilles ],
	[ 'casse', s16_recadrageTypographique ],
	[ 'couleurs-forcees', s17_couleursForcees ],
	[ 'impression', s18_impressionA4etA5 ],
	[ 'cartes', s19_modeCartesEtCellulesVides ],
	[ 'arbre', s20_arbreAccessibiliteEnCartes ],
	[ 'gravatar', s21_aucuneFuiteGravatar ],
	[ 'partielle', s22_publicationPartielle ],
	[ 'etat-inconnu', s23_ardoiseEtatInconnu ],
	[ 'carte', s24_carteInteractive ],
	[ 'carte-degradee', s25_carteEnEchecEtSansTuiles ],
	[ 'carte-garde', s26_gardeCartePhpNEmportePasLeRepli ],
	[ 'bandes', s27_bandesDeLEpic4 ],
	[ 'portail', s28_portailGestionnaire ],
	[ 'portail-anonyme', s29_portailEcrituresRefusees ],
	[ '2fa', s30_deuxFacteursEtSuspension ],
	[ 'carte-selection', s31_carteSelectionEtPaliers ],
];

const filtre = ( process.argv.find( ( a ) => a.startsWith( '--filtre=' ) ) ?? '' ).slice( 9 );

const modulePlaywright = await import( pathToFileURL( resoudre( 'playwright-core' ) ).href );
const { chromium } = modulePlaywright.chromium ? modulePlaywright : modulePlaywright.default;
const executablePath = chercherChromium();
const navigateur = await chromium.launch( executablePath ? { executablePath } : {} );

console.log( `Navigateur : ${ navigateur.version() }` );
console.log( `Cible      : ${ BASE }` );
console.log();

try {
	for ( const [ cle, fn ] of SCENARIOS ) {
		if ( filtre && ! cle.includes( filtre ) ) {
			continue;
		}
		try {
			await fn( navigateur );
		} catch ( erreur ) {
			ko( `scénario interrompu : ${ erreur.message }`, 'exécution complète', String( erreur.stack ).split( '\n' ).slice( 0, 4 ).join( ' | ' ) );
		}
		console.log();
	}
} finally {
	await navigateur.close();
}

console.log( '======================================================================' );
console.log( `TOTAL : ${ bilan.ok } assertion(s) verte(s), ${ bilan.ko } rouge(s)` );
if ( bilan.ko > 0 ) {
	console.log( 'ROUGES :' );
	bilan.echecs.forEach( ( e ) => console.log( `  - ${ e }` ) );
	process.exit( 1 );
}
console.log( 'Toutes les assertions passent.' );
