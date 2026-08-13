/**
 * Rendu du fichier de métadonnées PHP.
 *
 * Même forme que `includes/domain/massifs/build/importer.mjs` : tableaux
 * `array()`, indentation par tabulations, flèches alignées. Le fichier doit
 * rester RELISIBLE en diff — c'est le seul contrôle humain sur ce que le build
 * publie au thème.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

/** Marque une valeur comme flottante, pour qu'elle ne soit pas rendue en entier. */
export function flottant( valeur ) {
	return { __flottant: valeur };
}

/**
 * Rend une valeur JavaScript en littéral PHP.
 *
 * @param {*}      valeur  Valeur à rendre.
 * @param {number} retrait Profondeur d'indentation.
 * @return {string}
 */
export function rendreValeur( valeur, retrait ) {
	if ( null === valeur || undefined === valeur ) {
		return 'null';
	}

	if ( 'boolean' === typeof valeur ) {
		return valeur ? 'true' : 'false';
	}

	if ( 'number' === typeof valeur ) {
		return String( valeur );
	}

	if ( 'string' === typeof valeur ) {
		return `'${ valeur.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'`;
	}

	if ( 'object' === typeof valeur && '__flottant' in valeur ) {
		const rendu = String( valeur.__flottant );

		// Sans le `.0`, PHP relirait un entier là où le contrat annonce un flottant,
		// et `is_float()` ferait rejeter l'emprise entière côté module.
		return rendu.includes( '.' ) || rendu.includes( 'e' ) ? rendu : `${ rendu }.0`;
	}

	if ( Array.isArray( valeur ) ) {
		if ( 0 === valeur.length ) {
			return 'array()';
		}

		return `array( ${ valeur.map( ( element ) => rendreValeur( element, retrait ) ).join( ', ' ) } )`;
	}

	const entrees = Object.entries( valeur );

	if ( 0 === entrees.length ) {
		return 'array()';
	}

	const marge = '\t'.repeat( retrait + 1 );
	const largeur = Math.max( ...entrees.map( ( [ cle ] ) => cle.length ) ) + 2;
	const lignes = entrees.map( ( [ cle, sousValeur ] ) => {
		const clef = `'${ cle }'`.padEnd( largeur, ' ' );

		return `${ marge }${ clef } => ${ rendreValeur( sousValeur, retrait + 1 ) },`;
	} );

	return `array(\n${ lignes.join( '\n' ) }\n${ '\t'.repeat( retrait ) })`;
}
