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

	const libelles = await page.locator( '#liste tbody .liste-statuts__cellule--niveau .statut__libelle' ).allInnerTexts();
	egal( total, libelles.length, 'chaque massif porte un libellé de niveau en toutes lettres' );
	const distincts = [ ...new Set( libelles.map( ( l ) => l.trim() ) ) ].sort();
	egal( [ 'Accès au massif autorisé', 'Accès au massif interdit' ], distincts, 'les libellés rendus sont ceux de la légende officielle' );
	egal(
		autorises,
		libelles.filter( ( l ) => l.trim() === 'Accès au massif autorisé' ).length,
		'le nombre de massifs autorisés rendu correspond à la donnée écrite en base'
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
	note( `marques sans aplat peint (CSS de statut absent du lot) : ${ releve.sansAplat }/${ releve.marques }` );

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
