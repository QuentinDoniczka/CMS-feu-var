/**
 * Relevé de recette ad hoc pour l'issue #18 — hors dépôt, jamais commité.
 * Produit : violations axe (serious/critical), origines réseau, poids transféré,
 * titre du document, structure de titres, rendu sans JavaScript.
 *
 * Usage : node recette.mjs <url> [<url>...]
 */
import { chromium } from 'playwright-core';
import { createRequire } from 'node:module';
import path from 'node:path';

const require = createRequire( import.meta.url );
const axeChemin = require.resolve( 'axe-core' );

const cibles = process.argv.slice( 2 );
if ( cibles.length === 0 ) {
	console.error( 'usage: node recette.mjs <url> [<url>...]' );
	process.exit( 2 );
}

const origineDuSite = new URL( cibles[ 0 ] ).origin;

/** @param {import('playwright-core').Browser} navigateur @param {string} url @param {boolean} avecJs */
async function releverUne( navigateur, url, avecJs ) {
	// bypassCSP UNIQUEMENT sur la passe axe : depuis #16 le site sert
	// `script-src 'self'`, qui bloque l'injection d'axe-core. La passe réseau
	// (avecJs === false, et le relevé d'origines ci-dessous) tourne SANS
	// dérogation, donc les origines mesurées restent celles du vrai site.
	const contexte = await navigateur.newContext( {
		javaScriptEnabled: avecJs,
		bypassCSP: avecJs,
	} );
	const requetes = [];
	contexte.on( 'request', ( r ) => requetes.push( r.url() ) );

	const page = await contexte.newPage();
	const reponses = [];
	page.on( 'response', async ( r ) => {
		try {
			const l = ( await r.headerValue( 'content-length' ) ) || '0';
			reponses.push( { url: r.url(), octets: parseInt( l, 10 ) || 0, type: r.request().resourceType() } );
		} catch { /* réponse disparue */ }
	} );

	const reponse = await page.goto( url, { waitUntil: 'networkidle', timeout: 30000 } );
	const statut = reponse ? reponse.status() : null;

	const titre = await page.title();
	const langue = await page.getAttribute( 'html', 'lang' );
	const titres = await page.$$eval( 'h1,h2,h3,h4', ( ns ) =>
		ns.map( ( n ) => `${ n.tagName.toLowerCase() } · ${ ( n.textContent || '' ).trim().slice( 0, 90 ) }` )
	);
	const description = await page.getAttribute( 'meta[name="description"]', 'content' ).catch( () => null );
	const texteVisible = ( await page.innerText( 'body' ).catch( () => '' ) ).length;

	let violations = null;
	if ( avecJs ) {
		await page.addScriptTag( { path: axeChemin } );
		const resultat = await page.evaluate( async () => {
			// eslint-disable-next-line no-undef
			return await axe.run( document, { resultTypes: [ 'violations' ] } );
		} );
		violations = resultat.violations.map( ( v ) => ( {
			id: v.id,
			impact: v.impact,
			noeuds: v.nodes.length,
			cibles: v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) ),
		} ) );
	}

	const tierces = [ ...new Set( requetes.filter( ( u ) => {
		if ( u.startsWith( 'data:' ) || u.startsWith( 'blob:' ) || u.startsWith( 'about:' ) ) return false;
		return new URL( u ).origin !== origineDuSite;
	} ) ) ];

	const poids = reponses.reduce( ( t, r ) => t + r.octets, 0 );
	const parType = {};
	for ( const r of reponses ) {
		parType[ r.type ] = ( parType[ r.type ] || 0 ) + r.octets;
	}

	await contexte.close();
	return { url, avecJs, statut, titre, langue, description, titres, texteVisible, violations, tierces, poids, parType, nbRequetes: requetes.length };
}

const navigateur = await chromium.launch();
const releves = [];
for ( const url of cibles ) {
	releves.push( await releverUne( navigateur, url, true ) );
	releves.push( await releverUne( navigateur, url, false ) );
}
await navigateur.close();

console.log( JSON.stringify( releves, null, 2 ) );
