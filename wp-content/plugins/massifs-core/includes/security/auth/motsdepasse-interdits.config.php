<?php
/**
 * Liste de mots de passe triviaux refusés.
 *
 * COURTE ET ASSUMÉE. Ce n'est pas une liste de fuites — un dictionnaire de plusieurs
 * millions d'entrées n'a pas sa place dans un dépôt Git, et le chargerait à chaque
 * changement de mot de passe. C'est un filet contre le réflexe : la poignée de
 * chaînes qu'un utilisateur pressé tape en premier. La longueur minimale de douze
 * caractères fait l'essentiel du travail ; cette liste attrape ce qu'elle laisse
 * passer.
 *
 * Toutes les entrées sont EN MINUSCULES : la comparaison normalise la casse, sans
 * quoi `Motdepasse2026` passerait quand `motdepasse2026` échoue.
 *
 * @package Massifs\Security\Auth
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'motdepasse',
	'motdepasse1',
	'motdepasse123',
	'monmotdepasse',
	'password',
	'password1',
	'password123',
	'passw0rd',
	'motdepasse2025',
	'motdepasse2026',
	'password2025',
	'password2026',
	'azertyuiop',
	'qwertyuiop',
	'azerty123456',
	'qwerty123456',
	'123456789012',
	'1234567890123',
	'000000000000',
	'111111111111',
	'abcdefghijkl',
	'administrateur',
	'administrator',
	'admin1234567',
	'adminadmin',
	'wordpress',
	'wordpress123',
	'bienvenue1',
	'bienvenue123',
	'welcome12345',
	'changemoi',
	'changeme1234',
	'aaaaaaaaaaaa',
	'lemotdepasse',
	'soleilsoleil',
	'marseille13',
	'bouchesdurhone',
	'prefecture13',
	'massifsmassifs',
	'gestionnaire1',
	'connexion1234',
	'iloveyou1234',
	'letmein12345',
	'monkey123456',
	'dragon123456',
);
