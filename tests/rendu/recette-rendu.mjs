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

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, renameSync } from 'node:fs';
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
 * @param {string} mode absente | jour-nominal | veille-seule
 * @return {string} Ligne d'état rendue par la fabrique.
 */
function poserEtat( mode ) {
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

	for ( const cible of PAGES ) {
		const { page, requetes, echecs } = await charger( contexte, cible.chemin );

		for ( const r of requetes ) {
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
		for ( const u of urlsAbsolues( corps ) ) {
			const origine = new URL( u ).origin;
			originesCitees.set( origine, ( originesCitees.get( origine ) ?? 0 ) + 1 );
			if ( origine !== ORIGINE ) {
				ko( `feuille tierce référencée dans ${ feuille }`, ORIGINE, u );
			}
		}
		const relatives = [ ...corps.matchAll( /url\(\s*['"]?([^)'"]+)/g ) ].map( ( m ) => m[ 1 ] );
		note( `${ feuille.replace( BASE, '' ) } : url() → ${ relatives.join( ', ' ) || '(aucune)' }` );
	}

	const tierces = [ ...originesDemandees.keys() ].filter( ( o ) => o !== ORIGINE );
	egal( [], tierces, 'aucune origine tierce n’a été CONTACTÉE par le navigateur' );
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

	// L'accueil ne dépend d'aucun script : aucun n'est enfilé par ce lot.
	egal( 0, await page.locator( 'script[src]' ).count(), 'aucun script externe n’est nécessaire à l’information' );

	// GAP connu : le repli statique sans JS (image du département + lien vers la
	// liste) n'existe pas encore — la bande carte est vide.
	const carte = await page.locator( '#carte' ).innerHTML();
	note( `#carte (repli sans JS attendu au §5.5) : ${ carte.trim() === '' ? 'VIDE — aucune image de repli, aucun lien vers la liste' : carte.slice( 0, 120 ) }` );

	await contexte.close();
}

async function s03_structureEtAncres( navigateur ) {
	scenario( '03 — un seul h1, aucun id en double, ancres d’évitement résolues' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext();

	for ( const cible of PAGES ) {
		const { page } = await charger( contexte, cible.chemin );

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
			egal( 2, structure.h2, 'accueil : deux h2 (légende, liste) — aucun doublon de titre' );
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
	const fuites = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__libelle' ) ]
			.filter( ( e ) => ! e.closest( '#legende' ) )
			.map( ( e ) => e.textContent.trim() )
	);
	egal( [], fuites, 'aucun libellé de statut hors de la légende : rien de la veille ne fuit' );

	egal( 1, await page.locator( '.ardoise__peremption' ).count(), 'la mention de péremption est ajoutée, sans masquer quoi que ce soit' );

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

	const releve = await page.evaluate( () => {
		const marques = [ ...document.querySelectorAll( '.statut__marque' ) ];
		const libelles = [ ...document.querySelectorAll( '.statut__libelle' ) ];
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
	const enveloppe = tailles.filter( ( t ) => ! estPolice( t ) && ! estGeometrie( t ) );
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
		'5ad802a3708fe1734845e7a76b46de5382f2421268542584cafa270d29aa3835',
		somme,
		'tokens.css : sha256 conforme à celui gelé par le contrat #4'
	);

	const texte = octets.toString( 'utf8' );
	const racineBloc = /:root\s*\{([\s\S]*?)\n\}/.exec( texte );
	// Sans ancre de début de ligne : plusieurs jetons partagent une même ligne.
	const proprietes = [ ...racineBloc[ 1 ].matchAll( /(--[a-z0-9-]+)\s*:/g ) ].map( ( m ) => m[ 1 ] );
	egal( 111, proprietes.length, 'tokens.css : 111 propriétés personnalisées sur :root' );
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
	return page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__marque' ) ].map( ( e ) => {
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

	egal(
		[ 'massifs-fonts-css', 'massifs-tokens-css', 'massifs-layout-css', 'massifs-composants-css', 'massifs-print-css' ],
		liens.map( ( l ) => l.id ),
		'les cinq feuilles sont servies dans l’ordre du contrat, et aucune autre'
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
		[ 'all', 'all', 'all', 'all' ],
		liens.filter( ( l ) => l.id !== 'massifs-print-css' ).map( ( l ) => l.media ),
		'les quatre feuilles d’écran sont en media="all"'
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
	const orphelines = await page.evaluate( () =>
		[ ...document.querySelectorAll( '.statut__marque' ) ].filter( ( m ) => {
			const s = m.nextElementSibling;
			return ! s || ! s.classList.contains( 'statut__libelle' ) || s.textContent.trim() === '';
		} ).length
	);
	egal( 0, orphelines, 'sous couleurs forcées, chaque marque garde son libellé en toutes lettres' );

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
			return {
				tableau: d( '.liste-statuts__tableau' ),
				thead: q( '.liste-statuts__tableau thead' ) ? getComputedStyle( q( '.liste-statuts__tableau thead' ) ).display : 'ABSENT',
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

	const mode = await page.evaluate( () => ( {
		tableau: getComputedStyle( document.querySelector( '.liste-statuts__tableau' ) ).display,
		thead: getComputedStyle( document.querySelector( '.liste-statuts__tableau thead' ) ).display,
		tbody: getComputedStyle( document.querySelector( '.liste-statuts__tableau tbody' ) ).display,
		ligne: getComputedStyle( document.querySelector( '.liste-statuts__ligne' ) ).display,
	} ) );
	egal(
		{ tableau: 'block', thead: 'none', tbody: 'block', ligne: 'block' },
		mode,
		'à 320 px, la liste est en cartes empilées : c’est la base mobile-first'
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
	scenario( '20 — arbre d’accessibilité en mode cartes (thead masqué)' );
	poserEtat( 'jour-nominal' );

	const contexte = await navigateur.newContext( { viewport: { width: 320, height: 900 } } );
	const page = await contexte.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );

	const aplati = await arbreAccessibilite( page );
	const roles = aplati.reduce( ( acc, n ) => ( { ...acc, [ n.role ]: ( acc[ n.role ] ?? 0 ) + 1 } ), {} );
	note( `rôles exposés à 320 px : ${ Object.entries( roles ).map( ( [ r, n ] ) => `${ r }×${ n }` ).join( ' · ' ) }` );

	// Ce que le CSS NE PEUT PAS casser : les rôles ARIA explicites du gabarit
	// survivent au passage en `display: block`. C'est la raison pour laquelle
	// `display: contents` a été refusé.
	assert( ( roles.table ?? roles.grid ?? 0 ) >= 1, 'le tableau reste exposé comme table malgré display: block', '≥ 1 table', roles.table ?? 0 );
	assert( ( roles.row ?? 0 ) >= 25, 'les 25 rangées restent exposées comme rangées', '≥ 25 row', roles.row ?? 0 );
	assert( ( roles.rowheader ?? 0 ) >= 25, 'chaque massif reste un en-tête de rangée', '≥ 25 rowheader', roles.rowheader ?? 0 );
	assert( ( roles.cell ?? 0 ) >= 25, 'les cellules restent des cellules', '≥ 25 cell', roles.cell ?? 0 );

	// Ce que le CSS CASSE, et qu'il faut dire : `thead` est `display: none`, donc
	// les `columnheader` ne sont plus dans l'arbre. `attr(data-etiquette)` peint
	// une étiquette mais NE RÉTABLIT PAS l'association columnheader ↔ cell.
	const colonnes = roles.columnheader ?? 0;
	note(
		`columnheader exposés à 320 px : ${ colonnes } — le thead est display:none, ` +
			`et le contenu généré par ::before ne rétablit AUCUNE association columnheader ↔ cell. ` +
			`Constat, pas assertion : seul un contrôle humain au lecteur d’écran peut trancher l’utilisabilité.`
	);

	// Contre-épreuve en mode colonnes : l'en-tête revient, la preuve que la perte
	// vient bien du `display: none` et non du gabarit.
	const large = await navigateur.newContext( { viewport: { width: 900, height: 900 } } );
	const pageLarge = await large.newPage();
	await pageLarge.goto( BASE + '/', { waitUntil: 'load' } );
	const arbreLarge = await arbreAccessibilite( pageLarge );
	const colonnesLarge = arbreLarge.filter( ( n ) => n.role === 'columnheader' ).length;
	assert( colonnesLarge >= 4, 'au-dessus de --bp-s, les 4 en-têtes de colonne sont exposés', '≥ 4', colonnesLarge );
	note( `columnheader exposés à 900 px : ${ colonnesLarge } — la perte à 320 px est bien imputable au display:none` );
	await large.close();

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
