/**
 * Où se trouve mapshaper, et quoi dire quand il n'y est pas.
 *
 * Extrait le jour où un second consommateur est apparu (`communes.mjs`, issue
 * #45) : `importer.mjs` et lui recopiaient le MÊME chemin et le MÊME message,
 * chacun sous un commentaire annonçant une définition unique. La règle de la
 * maison — écrite en tête de `CHEMINS`, dans `importer.mjs` — est qu'une
 * seconde liste de chemins recopiée finit par désigner un autre fichier que
 * celui qu'on croit ; elle vaut aussi pour l'outillage.
 *
 * Le chemin ne peut pas vivre dans `geometrie.mjs`, qui ne connaît par
 * construction aucun chemin, ni dans `communes.mjs`, qui n'a pas l'exclusivité
 * de mapshaper. D'où ce fichier, qui ne fait rien d'autre.
 *
 * Les DEUX lancements restent distincts, et c'est délibéré : celui de
 * `importer.mjs` consigne son argv dans l'artefact de recette de la géométrie,
 * celui de `communes.mjs` n'a rien à consigner. Seule la localisation du binaire
 * est commune.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = path.dirname( fileURLToPath( import.meta.url ) );

/** Binaire mapshaper, tel qu'installé par `npm ci` dans ce répertoire. */
export const CHEMIN_MAPSHAPER = path.join( RACINE, 'node_modules/mapshaper/bin/mapshaper' );

/** Manifeste de mapshaper : c'est lui qui porte la version réellement installée. */
export const CHEMIN_MAPSHAPER_MANIFESTE = path.join( RACINE, 'node_modules/mapshaper/package.json' );

/** Message unique : deux formulations divergentes du même remède se périmeraient séparément. */
export const MAPSHAPER_ABSENT = 'mapshaper est absent : lancer `npm ci` dans includes/domain/massifs/build/.';
