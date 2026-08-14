<?php
/**
 * Alerte courriel : la donnée que le site affiche est périmée.
 *
 * Ce module ENVOIE ; il ne décide de rien. La constatation de l'incident
 * appartient à la veille (`Massifs\Ingest\Cron\Veille`), qui émet l'action de
 * constat à laquelle l'amorce de ce module s'abonne. Séparer les deux permet à
 * la supervision d'écouter le constat sans passer par le courriel.
 *
 * Texte brut uniquement : ces messages passent par des relais et des filtres
 * anti-spam, et leur contenu n'a aucun besoin de mise en forme.
 *
 * AUCUN ÉCHAPPEMENT HTML N'EST APPLIQUÉ À UNE VALEUR DU CORPS. Une entité HTML
 * dans un courriel texte est une corruption de donnée, pas une protection : les
 * valeurs interpolées passent par `wp_strip_all_tags()`, et rien d'autre.
 *
 * @package Massifs\Security\Alertes
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Security\Alertes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Envoi de l'alerte de péremption.
 */
final class Peremption {

	/**
	 * Type de verrou posé par cette alerte.
	 */
	private const TYPE = 'peremption';

	/**
	 * Contexte transmis au filtre des destinataires.
	 */
	private const CONTEXTE = 'peremption';

	/**
	 * Traite un constat de péremption.
	 *
	 * La charge utile est REVALIDÉE ici, bien que ce soit la veille qui l'émette :
	 * l'action de constat est déclenchable par n'importe quel tiers, avec
	 * n'importe quel tableau. Les `??` de cette méthode ne sont donc pas un repli
	 * sur une clé du domaine — qui sont toutes toujours présentes — mais la garde
	 * d'une entrée non fiable.
	 *
	 * @param array<string, mixed> $fraicheur Tableau retourné par `massifs_fraicheur()`.
	 */
	public static function alerter( array $fraicheur ): void {
		$source = self::texte( $fraicheur, 'dernier_releve_source' );
		$jour   = self::texte( $fraicheur, 'jour_validite' );

		if ( '' === $source || '' === $jour ) {
			return;
		}

		// Les deux garanties de la charge utile. La règle de péremption n'est
		// pas recalculée ici : `perimee` est LUE, jamais dérivée d'un âge.
		if ( true !== ( $fraicheur['perimee'] ?? null ) || true !== ( $fraicheur['dispositif_actif'] ?? null ) ) {
			return;
		}

		$cle = Verrou::cle( self::TYPE, $source, $jour );

		if ( Verrou::est_pose( $cle ) ) {
			return;
		}

		$destinataires = self::destinataires();

		if ( array() === $destinataires ) {
			// Rien n'a été tenté et un destinataire peut apparaître d'ici la
			// prochaine exécution : poser le verrou perdrait la journée.
			return;
		}

		// LE VERROU EST POSÉ AVANT L'ENVOI, délibérément. Si une extension SMTP
		// tierce lève une `Throwable`, poser après ne poserait jamais et la
		// tentative se rejouerait toutes les heures, avec son exception. Le
		// verrou protège contre la répétition, pas contre l'échec ; le coût est
		// borné par la granularité par jour — un envoi perdu coûte une alerte
		// pour une journée, et la clé du lendemain est différente.
		Verrou::poser( $cle, self::texte( $fraicheur, 'evalue_le' ) );

		wp_mail(
			$destinataires,
			self::sujet( $source, $jour ),
			implode( "\n", self::corps( $fraicheur, $source, $jour ) ) . "\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Sujet du message.
	 *
	 * Registre d'EXPLOITATION, et le §4.6 l'exige : le sujet ne cite aucune
	 * chaîne du thème. « Donnée périmée. » est le verbatim rendu par
	 * `bandeau-peremption.php` ; le reprendre ici accrocherait un courriel de
	 * supervision à un libellé de gabarit, qui dériverait au premier remaniement
	 * du thème sans que rien ne le signale. Le mot « périmées » relève, lui, du
	 * vocabulaire du DOMAINE (`massifs_fraicheur()['perimee']`), qui est la seule
	 * source de cette alerte.
	 *
	 * Aucune heure de publication n'y figure : ce module n'en connaît aucune.
	 *
	 * @param string $source Clé de la source.
	 * @param string $jour   Jour de validité.
	 */
	private static function sujet( string $source, string $jour ): string {
		return sprintf( '[MASSIFS] Fraîcheur : données périmées — source « %s », jour %s', $source, $jour );
	}

	/**
	 * Corps du message, ligne à ligne.
	 *
	 * @param array<string, mixed> $fraicheur Charge utile du constat.
	 * @param string               $source    Clé de la source.
	 * @param string               $jour      Jour de validité.
	 *
	 * @return string[]
	 */
	private static function corps( array $fraicheur, string $source, string $jour ): array {
		$dernier     = self::texte( $fraicheur, 'dernier_releve_le' );
		$publication = self::texte( $fraicheur, 'publie_prefecture_le' );
		$evalue      = self::texte( $fraicheur, 'evalue_le' );
		$age         = self::entier( $fraicheur, 'age_secondes' );
		$seuil       = self::entier( $fraicheur, 'seuil_secondes' );

		// 1. Le fait. Le cas « aucun relevé » est le plus fréquent sur une base
		// vierge en période d'activité : il a sa propre formulation, parce que
		// « le dernier relevé date de … » n'y voudrait rien dire.
		$lignes = array(
			'' === $dernier
				? sprintf( 'Aucun relevé réussi n\'est enregistré pour la source « %s ».', $source )
				: sprintf( 'Le dernier relevé réussi de la source « %s » remonte au %s.', $source, self::instant_lisible( $dernier ) ),
			sprintf(
				'La donnée servie pour le jour de validité %s est évaluée comme périmée. Seuil de fraîcheur : %s.',
				$jour,
				self::secondes( $seuil, 'inconnu' )
			),
		);

		// 2. Ce que le site affiche — le cœur du message. Sans le troisième
		// paragraphe, un gestionnaire recevant le même jour l'alerte du
		// connecteur et celle-ci croirait à un doublon.
		$lignes[] = '';
		$lignes[] = 'CE QUE LE SITE AFFICHE';
		$lignes[] = 'Le site affiche la bannière de péremption.';
		$lignes[] = 'Cette bannière S\'AJOUTE à la page : elle ne masque, ne filtre et ne remplace aucun statut, aucun chiffre, aucun titre. Si des statuts existent pour ce jour, ils restent affichés à l\'identique.';
		$lignes[] = 'Ce message ne veut donc PAS dire que le site répond « information non disponible ». Ce signal-là est distinct : il est émis par le connecteur d\'ingestion de la source lorsqu\'aucun statut n\'a pu être obtenu pour le jour, et il fait l\'objet de son propre courriel.';
		$lignes[] = 'Recevoir les deux messages le même jour n\'est pas un doublon : ils répondent à deux questions différentes.';

		// 3. Les faits bruts, tels que le domaine les expose.
		$lignes[] = '';
		$lignes[] = 'FAITS RELEVÉS';
		$lignes[] = sprintf( 'Source                : %s', $source );
		$lignes[] = sprintf( 'Dernier relevé réussi : %s', '' === $dernier ? 'aucun' : $dernier );
		$lignes[] = sprintf( 'Âge du dernier relevé : %s', self::secondes( $age, 'inconnu (aucun relevé)' ) );
		$lignes[] = sprintf( 'Seuil de fraîcheur    : %s', self::secondes( $seuil, 'inconnu' ) );
		$lignes[] = sprintf( 'Jour de validité      : %s', $jour );
		$lignes[] = sprintf( 'Publication connue    : %s', '' === $publication ? 'inconnue' : $publication );
		$lignes[] = sprintf( 'Évaluation faite le   : %s', '' === $evalue ? 'inconnue' : $evalue );

		// 4. La carte officielle, LUE et jamais écrite en dur. La ligne est
		// simplement omise si la fonction de domaine n'est pas disponible.
		$carte = self::url_carte_officielle();

		if ( '' !== $carte ) {
			$lignes[] = '';
			$lignes[] = 'Carte officielle : ' . $carte;
		}

		// 5. Où agir.
		$lignes[] = '';
		$lignes[] = 'Administration du site : ' . admin_url();

		// 6. Comment s'arrêter. Un courriel d'exploitation qui ne le dit pas
		// finit filtré en indésirable, et l'alerte devient inutile.
		$lignes[] = '';
		$lignes[] = 'COMMENT FAIRE TAIRE CETTE ALERTE';
		$lignes[] = 'Poser la constante MASSIFS_VEILLE_FRAICHEUR_DESARMEE à true dans wp-config.php,';
		$lignes[] = 'ou renvoyer false depuis le filtre massifs_veille_fraicheur_armee.';
		$lignes[] = 'La surveillance cesse alors entièrement : plus aucune vérification, plus aucun courriel.';

		return $lignes;
	}

	/**
	 * Met un instant en forme, avec repli sur la chaîne brute.
	 *
	 * Aucune date n'est composée ici : la mise en forme appartient au domaine,
	 * et son absence n'empêche pas le courriel de partir.
	 *
	 * @param string $instant_iso_utc Instant ISO 8601 UTC.
	 */
	private static function instant_lisible( string $instant_iso_utc ): string {
		if ( ! function_exists( 'massifs_horodatage' ) ) {
			return $instant_iso_utc;
		}

		try {
			$forme = massifs_horodatage( $instant_iso_utc );
		} catch ( \Throwable ) {
			return $instant_iso_utc;
		}

		if ( ! isset( $forme['date_longue'], $forme['heure'] ) ) {
			return $instant_iso_utc;
		}

		return sprintf( '%s à %s (%s)', (string) $forme['date_longue'], (string) $forme['heure'], $instant_iso_utc );
	}

	/**
	 * URL de la carte officielle, ou chaîne vide.
	 */
	private static function url_carte_officielle(): string {
		if ( ! function_exists( 'massifs_attribution_statuts' ) ) {
			return '';
		}

		try {
			$attribution = massifs_attribution_statuts();
		} catch ( \Throwable ) {
			return '';
		}

		if ( ! isset( $attribution['carte_officielle_url'] ) || ! is_string( $attribution['carte_officielle_url'] ) ) {
			return '';
		}

		return trim( $attribution['carte_officielle_url'] );
	}

	/**
	 * Destinataires assainis de l'alerte.
	 *
	 * @return string[]
	 */
	private static function destinataires(): array {
		$destinataires = array( (string) get_option( 'admin_email', '' ) );

		/**
		 * Filtre les destinataires des alertes d'exploitation.
		 *
		 * @param string[] $destinataires Adresses de courriel.
		 * @param string   $contexte      Contexte de l'alerte.
		 */
		$destinataires = apply_filters( 'massifs_alertes_destinataires', $destinataires, self::CONTEXTE );

		if ( ! is_array( $destinataires ) ) {
			return array();
		}

		$propres = array();

		foreach ( $destinataires as $adresse ) {
			if ( ! is_scalar( $adresse ) ) {
				continue;
			}

			$adresse = sanitize_email( (string) $adresse );

			if ( '' !== $adresse && is_email( $adresse ) ) {
				$propres[] = $adresse;
			}
		}

		return array_values( array_unique( $propres ) );
	}

	/**
	 * Valeur textuelle d'une clé de la charge utile.
	 *
	 * `wp_strip_all_tags()` et non un `esc_*` : la sortie est un courriel texte.
	 *
	 * @param array<string, mixed> $charge Charge utile.
	 * @param string               $cle    Clé lue.
	 */
	private static function texte( array $charge, string $cle ): string {
		if ( ! isset( $charge[ $cle ] ) || ! is_string( $charge[ $cle ] ) ) {
			return '';
		}

		return trim( wp_strip_all_tags( $charge[ $cle ] ) );
	}

	/**
	 * Valeur entière d'une clé de la charge utile.
	 *
	 * @param array<string, mixed> $charge Charge utile.
	 * @param string               $cle    Clé lue.
	 */
	private static function entier( array $charge, string $cle ): ?int {
		return isset( $charge[ $cle ] ) && is_int( $charge[ $cle ] ) ? $charge[ $cle ] : null;
	}

	/**
	 * Met une durée en forme, ou rend le repli fourni.
	 *
	 * Aucune conversion en heures : la durée est citée telle que le domaine
	 * l'expose, et « 24 h » n'est jamais écrit en dur.
	 *
	 * @param int|null $valeur Durée en secondes, `null` si inconnue.
	 * @param string   $repli  Texte rendu quand la durée est inconnue.
	 */
	private static function secondes( ?int $valeur, string $repli ): string {
		return null === $valeur ? $repli : $valeur . ' secondes';
	}
}
