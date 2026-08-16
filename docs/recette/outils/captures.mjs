/**
 * Captures desktop + mobile pour les preuves de recette (§11 du brief).
 * Usage : node captures.mjs <dossier-sortie> <nom>=<url> [<nom>=<url>...]
 * Aucune dérogation CSP : ce sont des captures du vrai rendu servi.
 */
import { chromium } from 'playwright-core';
import path from 'node:path';
import { mkdirSync } from 'node:fs';

const [ sortie, ...paires ] = process.argv.slice( 2 );
if ( ! sortie || paires.length === 0 ) {
	console.error( 'usage: node captures.mjs <dossier> <nom>=<url> ...' );
	process.exit( 2 );
}
mkdirSync( sortie, { recursive: true } );

const formats = [
	{ suffixe: 'desktop', viewport: { width: 1440, height: 900 } },
	{ suffixe: 'mobile-360', viewport: { width: 360, height: 800 } },
];

const navigateur = await chromium.launch();

for ( const paire of paires ) {
	const separateur = paire.indexOf( '=' );
	const nom = paire.slice( 0, separateur );
	const url = paire.slice( separateur + 1 );

	for ( const format of formats ) {
		const contexte = await navigateur.newContext( { viewport: format.viewport } );
		const page = await contexte.newPage();
		await page.goto( url, { waitUntil: 'networkidle', timeout: 30000 } );

		// Défilement horizontal : exigence §8 (aucun à 320/360 px).
		const debordement = await page.evaluate(
			() => document.documentElement.scrollWidth - document.documentElement.clientWidth
		);

		const fichier = path.join( sortie, `${ nom }-${ format.suffixe }.png` );
		await page.screenshot( { path: fichier, fullPage: true } );
		console.log( `${ nom } ${ format.suffixe } : débordement horizontal = ${ debordement } px → ${ fichier }` );
		await contexte.close();
	}
}

await navigateur.close();
