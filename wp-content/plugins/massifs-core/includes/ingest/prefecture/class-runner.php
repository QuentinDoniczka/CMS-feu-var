<?php
/**
 * Orchestration d'une exécution de récupération.
 *
 * Toutes les portes d'entrée sont franchies AVANT le moindre octet réseau :
 * hors saison, mode manuel, connecteur désactivé, re-contrôle pas encore dû,
 * plafond quotidien atteint ou tentative trop récente, aucun appel sortant
 * n'est émis. Un rejeu de projection, lui, ne coûte aucun octet et passe donc
 * AVANT toute décision de requête.
 *
 * DEUX DÉFAUTS CORRIGÉS ICI, ET LEUR MOTIF :
 *
 * 1. Une date déjà instantanée n'était plus jamais relue. La préfecture peut
 *    republier en cours de journée ; le modèle de statuts est append-only et
 *    absorbe parfaitement une correction. Le connecteur, lui, n'en livrait
 *    jamais une.
 * 2. Un instantané enregistré dont la PROJECTION échouait ne laissait aucune
 *    trace côté ingestion, et rien ne relançait l'essai : le site annonçait
 *    « information non disponible » alors que la donnée était en cache, à un
 *    appel de fonction de la base.
 *
 * @package Massifs\Ingest\Prefecture
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Massifs\Ingest\Prefecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exécution planifiée et exécution ciblée.
 */
final class Runner {

	/*
	 * BUDGET D'APPELS SORTANTS — LA CADENCE QUI DIMENSIONNE TOUT.
	 *
	 * La cadence réelle recommandée en production est une tâche système au quart
	 * d'heure (`DISABLE_WP_CRON` plus une entrée crontab toutes les 15 minutes,
	 * cf. README, section Hébergement), soit 96 passes par jour, et non la
	 * récurrence `hourly` du planificateur WordPress —
	 * celle-ci n'est qu'un repli quand aucune tâche système n'existe. La fenêtre
	 * de publication par défaut va de 16 h à 23 h, soit 7 h, soit 28 passes.
	 * `DATES_MAX = 2` : au plus aujourd'hui et demain.
	 *
	 * Coût sans re-contrôle (état antérieur) : environ 8 requêtes par jour en
	 * saison — une ou deux pour rattraper aujourd'hui, quatre à cinq 404 entre
	 * 16 h et la publication de 17 h, puis plus rien. Les constantes ci-dessous
	 * sont dimensionnées pour rester du MÊME ORDRE DE GRANDEUR : chaque requête
	 * évitée sert la contrainte n°2 du projet (aucune dépendance à un domaine
	 * tiers au-delà du strict nécessaire).
	 */

	/**
	 * Délai minimal entre deux tentatives pour une même date, en secondes.
	 *
	 * 15 minutes = exactement une passe à la cadence réelle. Ce garde reste une
	 * limite RÉELLE : il empêche deux exécutions rapprochées (une passe cron
	 * doublée par une visite qui réveille WP-Cron) de sortir deux fois.
	 */
	private const ANTI_RAFALE_SECONDES = 15 * MINUTE_IN_SECONDS;

	/**
	 * Nombre maximal de dates traitées par exécution.
	 *
	 * Deux au plus : aujourd'hui et demain. Donc deux appels sortants au plus,
	 * quelle que soit la fréquence de déclenchement du cron.
	 */
	private const DATES_MAX = 2;

	/**
	 * Intervalle minimal entre deux RE-CONTRÔLES réseau d'une date déjà couverte.
	 *
	 * Dérivation : 3 h = 12 passes à la cadence réelle. Une republication en
	 * cours de journée est donc captée en moins de trois heures, sans qu'une
	 * date couverte soit rechargée à chacune des 96 passes — ce qui coûterait
	 * 192 requêtes par jour au lieu de 8.
	 */
	public const RECONTROLE_SECONDES = 3 * HOUR_IN_SECONDS;

	/**
	 * Borne dure de re-contrôles réseau, par date de validité et par jour.
	 *
	 * Dérivation : 4 re-contrôles espacés de 3 h couvrent 12 h, c'est-à-dire la
	 * totalité de la plage où une republication a un sens (de la publication du
	 * soir à la fin de la journée de validité). Coût plafond ajouté :
	 * 4 × `DATES_MAX` = 8 requêtes par jour, exactement l'ordre de grandeur du
	 * budget existant. Le compteur se réarme au changement de jour.
	 */
	public const RECONTROLES_MAX_PAR_JOUR = 4;

	/**
	 * Borne dure de REJEUX de projection, par date de validité et par jour.
	 *
	 * Un rejeu ne coûte aucun octet réseau, mais il n'est pas gratuit pour
	 * autant : `Depot::inserer()` ne déduplique pas, donc chaque projection
	 * réussie ajoute une ligne d'historique par massif. 3 rejeux suffisent
	 * largement à traverser une cause passagère (une panne de base d'une
	 * poignée de minutes) et bornent une cause permanente (référentiel absent)
	 * à trois passages au lieu de 96. Le compteur se réarme au changement de
	 * jour : le lendemain, une cause réparée mérite un nouvel essai.
	 */
	public const REJEUX_MAX_PAR_JOUR = 3;

	/**
	 * Exécution déclenchée par le planificateur.
	 */
	public static function run_scheduled(): void {
		if ( Settings::is_disabled() ) {
			StateRepository::record_marker( '', 'desactive', 'Connecteur désactivé : aucun appel sortant.' );

			return;
		}

		if ( 'manuel' === Settings::mode() ) {
			return;
		}

		$maintenant = SourceCalendar::now();
		$dates      = self::dates_a_traiter( $maintenant );

		foreach ( $dates as $date ) {
			$date_ymd = $date->format( 'Ymd' );

			// UN REJEU GRATUIT PRIME TOUJOURS SUR UNE REQUÊTE RÉSEAU. Si la
			// donnée est déjà en cache et que c'est la PROJECTION qui a échoué,
			// recharger le fichier ne répare rien : il faut le reprojeter.
			if ( self::rejouer_projection( $date_ymd ) ) {
				continue;
			}

			// Un re-contrôle est une requête sortante sur une date déjà
			// couverte : il se compte, et sa borne quotidienne est dure.
			if ( SnapshotRepository::has( $date_ymd ) ) {
				StateRepository::record_recontrole( $date_ymd );
			}

			self::run_for( $date, 'cron' );
		}

		self::surveiller_fenetre( $maintenant );
	}

	/**
	 * Republie un instantané DÉJÀ STOCKÉ pour relancer sa projection.
	 *
	 * ZÉRO APPEL SORTANT : le corps vient du dépôt, pas du réseau. C'est la
	 * réponse au cas où la donnée est bonne et en cache, mais où le domaine n'a
	 * pas réussi à l'écrire.
	 *
	 * @param string $date_ymd Date de validité au format `Ymd`.
	 * @return bool Vrai si un rejeu a réellement été émis.
	 */
	public static function rejouer_projection( string $date_ymd ): bool {
		$instantane = SnapshotRepository::get( $date_ymd );

		if ( null === $instantane || ! self::rejeu_autorise( $date_ymd ) ) {
			return false;
		}

		SnapshotRepository::consommer_rejeu( $date_ymd );

		// Issue dédiée : un rejeu n'est ni une réussite de récupération — il ne
		// doit pas rafraîchir `derniere_reussite`, aucun octet n'a été lu — ni
		// un échec. C'est un troisième fait, et il se journalise comme tel.
		StateRepository::record_issue(
			$date_ymd,
			'rejeu',
			'Projection précédente en échec : nouvelle tentative depuis l\'instantané stocké, aucun appel sortant.'
		);

		// L'état de projection est une annotation du connecteur : il n'a rien à
		// faire dans la charge remise au domaine.
		unset( $instantane['projection'] );

		self::publier( $instantane, $date_ymd, 'rejeu' );

		return true;
	}

	/**
	 * Un rejeu de projection est-il autorisé pour cette date ?
	 *
	 * TABLE DES ÉTATS TERMINAUX, ET C'EST LE PIÈGE PRINCIPAL DE CETTE MÉCANIQUE :
	 *
	 * - `partiel` / `rejete`   → rejeu autorisé, dans la limite quotidienne ;
	 * - `complet`              → rien à rejouer ;
	 * - `inconnue`             → aucune raison de croire que la projection a
	 *                            échoué ; rejouer ré-émettrait une publication
	 *                            déjà projetée et doublerait l'historique ;
	 * - `sans_projecteur`      → TERMINAL. Personne n'a conclu de projection :
	 *                            le domaine `statuts` est absent ou désarmé.
	 *                            Rejouer reviendrait à réémettre indéfiniment
	 *                            une action que personne n'écoute. Aucun rejeu,
	 *                            jamais.
	 *
	 * @param string $date_ymd Date de validité au format `Ymd`.
	 */
	private static function rejeu_autorise( string $date_ymd ): bool {
		$projection = SnapshotRepository::projection( $date_ymd );

		if ( ! in_array( $projection['resultat'], SnapshotRepository::PROJECTION_RESULTATS_REJOUABLES, true ) ) {
			return false;
		}

		return SnapshotRepository::rejeux_du_jour( $date_ymd ) < self::REJEUX_MAX_PAR_JOUR;
	}

	/**
	 * Publie un instantané vers le domaine, et consigne l'issue de la projection.
	 *
	 * @param array<string,mixed> $instantane Instantané à publier.
	 * @param string              $date_ymd   Date de validité au format `Ymd`.
	 * @param string              $motif      `publication`, `republication` ou `rejeu`.
	 */
	private static function publier( array $instantane, string $date_ymd, string $motif ): void {
		ProjectionListener::armer();

		/**
		 * Couture d'intégration du connecteur.
		 *
		 * Le connecteur ne projette jamais l'instantané dans un modèle de
		 * statut et n'invalide jamais un cache de page : c'est au domaine, en
		 * aval, de décider quoi en faire. Il écoute en revanche le bilan que le
		 * domaine publie en retour — voir `ProjectionListener`.
		 *
		 * Le second argument est ajouté sans risque pour les abonnés existants :
		 * un `add_action` sans `accepted_args` n'en reçoit qu'un.
		 *
		 * @param array<string,mixed> $instantane Instantané validé et enregistré.
		 * @param string              $motif      Motif de l'émission.
		 */
		do_action( 'massifs_prefecture_snapshot_enregistre', $instantane, $motif );

		if ( ProjectionListener::a_repondu() ) {
			// Le domaine a répondu. Si sa réponse était exploitable, le récepteur
			// a déjà consigné le résultat sur l'instantané ; si elle ne l'était
			// pas, l'état reste tel quel — mais dans les deux cas un projecteur
			// EXISTE, et conclure à son absence condamnerait la date.
			return;
		}

		/*
		 * PERSONNE N'A RÉPONDU — ET C'EST UN ÉTAT TERMINAL, PAS UN ÉCHEC À
		 * RÉESSAYER.
		 *
		 * Si `domain/statuts` est absent de l'arbre ou désarmé, l'action est
		 * émise dans le vide et aucun bilan ne revient — quel que soit le nombre
		 * de fois où on la réémet. Confondre ce cas avec un `rejete` ferait
		 * boucler le connecteur jusqu'à sa borne quotidienne, chaque jour, pour
		 * rien. `sans_projecteur` interdit donc tout rejeu, définitivement.
		 */
		SnapshotRepository::update_projection(
			$date_ymd,
			array(
				'resultat' => 'sans_projecteur',
				'le'       => gmdate( DATE_ATOM ),
				'motif'    => 'aucun abonné n\'a conclu de projection pour cet instantané',
			)
		);
	}

	/**
	 * Sélectionne les dates sur lesquelles il y a réellement du travail.
	 *
	 * C'EST LE SEUL ENDROIT OÙ LA POLITIQUE SE DÉCIDE. `SourceCalendar` nomme
	 * les dates candidates, ce runner décide ce qu'on en fait. La duplication de
	 * cette politique entre les deux — le calendrier écartait lui aussi les
	 * dates déjà couvertes — est exactement ce qui a produit le défaut : une
	 * republication en cours de journée n'était relue par personne.
	 *
	 * @param \DateTimeImmutable $maintenant Instant de référence.
	 * @return \DateTimeImmutable[]
	 */
	private static function dates_a_traiter( \DateTimeImmutable $maintenant ): array {
		$retenues = array();

		foreach ( SourceCalendar::pending_dates( $maintenant ) as $date ) {
			$date_ymd = $date->format( 'Ymd' );

			// La saison s'évalue sur la DATE CIBLE, jamais sur « maintenant ».
			// Hors saison : la source ne publie rien, donc pas d'appel et
			// surtout aucune alerte — une absence attendue n'est pas un
			// incident.
			if ( ! SourceCalendar::is_in_season( $date ) ) {
				StateRepository::record_marker( $date_ymd, 'hors_saison', 'Date hors saison du dispositif.' );
				continue;
			}

			if ( SnapshotRepository::has( $date_ymd ) ) {
				// Rejeu gratuit : aucun octet à économiser, donc ni anti-rafale
				// ni intervalle de re-contrôle ne s'y appliquent. Sa seule borne
				// est `REJEUX_MAX_PAR_JOUR`.
				if ( self::rejeu_autorise( $date_ymd ) ) {
					$retenues[] = $date;
					continue;
				}

				if ( ! self::recontrole_du( $date, $maintenant ) ) {
					continue;
				}
			}

			$derniere = StateRepository::last_attempt_for( $date_ymd );

			if ( null !== $derniere && ( time() - $derniere ) < self::ANTI_RAFALE_SECONDES ) {
				continue;
			}

			$retenues[] = $date;
		}

		return array_slice( $retenues, 0, self::DATES_MAX );
	}

	/**
	 * Un re-contrôle réseau est-il dû pour cette date déjà couverte ?
	 *
	 * Trois conditions cumulatives, toutes évaluées avant le moindre octet :
	 * la date est encore présentable (aujourd'hui ou demain), la borne
	 * quotidienne n'est pas épuisée, et l'intervalle minimal est écoulé.
	 *
	 * NOTE SUR `sans_projecteur` : cet état interdit tout rejeu, mais pas le
	 * re-contrôle. Les deux n'ont pas le même motif — le rejeu répond à une
	 * projection en échec, le re-contrôle à une republication possible de la
	 * source. Rien ne justifie de cesser de surveiller la source parce que le
	 * domaine est absent.
	 *
	 * @param \DateTimeImmutable $date       Date de validité visée.
	 * @param \DateTimeImmutable $maintenant Instant de référence.
	 */
	private static function recontrole_du( \DateTimeImmutable $date, \DateTimeImmutable $maintenant ): bool {
		if ( ! SourceCalendar::is_within_range( $date, $maintenant ) ) {
			return false;
		}

		$date_ymd = $date->format( 'Ymd' );

		if ( StateRepository::recontroles_for( $date_ymd ) >= self::RECONTROLES_MAX_PAR_JOUR ) {
			return false;
		}

		$derniere = StateRepository::last_attempt_for( $date_ymd );

		return null === $derniere || ( time() - $derniere ) >= self::RECONTROLE_SECONDES;
	}

	/**
	 * Alerte si la fenêtre de publication s'est close sans statut pour demain.
	 *
	 * INCHANGÉ, ET DÉLIBÉRÉMENT. Cette alerte répond à une question précise —
	 * « la source a-t-elle publié quelque chose pour demain ? » — à laquelle
	 * `SnapshotRepository::has()` est la bonne réponse, avant comme après.
	 * L'assouplissement du garde `has()` porte sur la SÉLECTION DES DATES À
	 * TRAVAILLER, pas sur ce constat.
	 *
	 * Ce qui change tout de même, sans effet sur l'alerte : une date couverte
	 * peut désormais être rechargée. Comme le rechargement n'efface jamais
	 * l'instantané en place (un rejet laisse le précédent intact, un corps
	 * inchangé n'est pas réécrit), `has()` ne peut pas repasser à faux et
	 * déclencher une alerte parasite en fin de fenêtre.
	 *
	 * Ce qui n'est PAS couvert ici : un instantané présent dont la projection a
	 * échoué. C'est une autre classe d'incident — la donnée existe, elle n'est
	 * pas écrite — et elle est traitée par le rejeu, pas par un courriel.
	 *
	 * @param \DateTimeImmutable $maintenant Instant de référence.
	 */
	private static function surveiller_fenetre( \DateTimeImmutable $maintenant ): void {
		$demain     = SourceCalendar::tomorrow( $maintenant );
		$demain_ymd = $demain->format( 'Ymd' );

		if ( (int) $maintenant->format( 'G' ) < Settings::fenetre()['fin'] ) {
			return;
		}

		if ( ! SourceCalendar::is_in_season( $demain ) || SnapshotRepository::has( $demain_ymd ) ) {
			return;
		}

		Notifier::alert_window_closed( $demain_ymd, StateRepository::get() );
	}

	/**
	 * Récupère, valide et enregistre le fichier d'une date de validité.
	 *
	 * @param \DateTimeImmutable $date        Date de validité visée.
	 * @param string             $declencheur Origine de l'exécution.
	 * @return true|\WP_Error Vrai si la date est couverte à l'issue de l'appel.
	 */
	public static function run_for( \DateTimeImmutable $date, string $declencheur ) {
		$date_ymd = $date->format( 'Ymd' );

		/**
		 * Une tentative de récupération démarre.
		 *
		 * @param string $date_ymd    Date de validité visée.
		 * @param string $declencheur Origine de l'exécution.
		 */
		do_action( 'massifs_prefecture_tentative', $date_ymd, $declencheur );

		StateRepository::record_attempt();

		// Mémoire DATÉE du garde anti-rafale, écrite au même endroit et pour la
		// même raison : avant tout octet réseau.
		StateRepository::record_attempt_for( $date_ymd );

		$reponse = Fetcher::fetch( $date );

		if ( is_wp_error( $reponse ) ) {
			return self::echouer( $date_ymd, 'reseau', $reponse );
		}

		switch ( Fetcher::classify( $reponse['code'] ) ) {
			case 'non_publie':
				// État légitime, pas un échec : le fichier du lendemain n'est
				// déposé qu'en fin d'après-midi. Aucun compteur d'échec, aucune
				// alerte, aucune action `echec`.
				StateRepository::record_issue( $date_ymd, 'non_publie', 'HTTP 404 : fichier non encore publié.' );

				return new \WP_Error(
					'non_publie',
					sprintf( 'Statuts du %s non encore publiés par la source.', $date->format( 'Y-m-d' ) ),
					array(
						'couche' => 'transport',
						'detail' => 404,
					)
				);

			case 'source_indisponible':
				return self::echouer(
					$date_ymd,
					'source_indisponible',
					self::erreur_http( 'source_indisponible', $reponse['code'], 'Source indisponible' )
				);

			case 'transport':
				return self::echouer(
					$date_ymd,
					'transport',
					self::erreur_http( 'transport_inattendu', $reponse['code'], 'Réponse HTTP inattendue' )
				);
		}

		return self::traiter_succes( $date, $reponse );
	}

	/**
	 * Traite une réponse HTTP 200.
	 *
	 * @param \DateTimeImmutable                                                          $date    Date de validité visée.
	 * @param array{code:int,body:string,headers:array<string,mixed>,url:string,octets:int} $reponse Réponse brute.
	 * @return true|\WP_Error
	 */
	private static function traiter_succes( \DateTimeImmutable $date, array $reponse ) {
		$date_ymd = $date->format( 'Ymd' );

		$instantane = Validator::validate(
			$reponse['body'],
			$reponse['headers'],
			$date,
			array(
				'source_url' => $reponse['url'],
				'mode'       => Settings::mode(),
			)
		);

		if ( is_wp_error( $instantane ) ) {
			StateRepository::record_issue( $date_ymd, 'rejet', (string) $instantane->get_error_message(), $instantane );
			Notifier::alert_rejected( $date_ymd, $instantane, StateRepository::get() );
			self::signaler_echec( $instantane );

			return $instantane;
		}

		// Déjà enregistré à l'identique POUR CETTE DATE : rien à réécrire.
		// La comparaison porte sur l'instantané de cette date précise, jamais
		// sur un balayage par hachage : voir le bloc ci-dessous.
		$existant = SnapshotRepository::get( $date_ymd );
		$inchange = null !== $existant
			&& isset( $existant['hash'] )
			&& hash_equals( (string) $existant['hash'], (string) $instantane['hash'] );

		/*
		 * Le hachage NE PROVOQUE JAMAIS DE REJET. Il ne sert qu'à journaliser.
		 *
		 * Le corps servi par la source ne contient aucune date
		 * (`{"massifs":{…},"zm":{…}}`). Deux journées où les 27 massifs portent
		 * les mêmes couples `[niveau, procedure]` produisent donc un corps
		 * octet pour octet identique — et c'est le CAS NOMINAL en juin comme
		 * lors de tout épisode stable (constaté les 8 et 11 août 2026, et le
		 * 15 août 2025 : les 27 massifs au même niveau).
		 *
		 * Le signal de « pas encore publié » n'est pas le hachage : c'est le
		 * 404. La source répond 404 sur `{date}.json` tant que la journée
		 * n'est pas publiée, et un 200 sur cette URL EST la publication de
		 * cette date. Rejeter un corps identique à la veille reviendrait à
		 * afficher « information non disponible » pendant toute la durée d'un
		 * épisode stable, c'est-à-dire précisément quand la donnée est bonne.
		 */
		if ( $inchange ) {
			$note = sprintf(
				'Corps identique à l\'instantané déjà enregistré pour cette date (%d octets) : aucune réécriture.',
				(int) $instantane['octets']
			);
		} else {
			$note = sprintf( '%d octets, confiance %s.', (int) $instantane['octets'], (string) $instantane['confiance'] );

			$identique_a = SnapshotRepository::find_by_hash( (string) $instantane['hash'] );

			if ( null !== $identique_a && $identique_a !== $date_ymd ) {
				$note .= sprintf( ' Contenu identique à celui du %s (information d\'exploitation, sans effet sur l\'enregistrement).', $identique_a );
			}

			SnapshotRepository::save( $instantane );
		}

		/*
		 * QUAND RÉ-ÉMET-ON ? Corps différent, OU projection précédente en échec.
		 * Jamais sur la seule foi d'un passage.
		 *
		 * `Depot::inserer()` ne déduplique pas : chaque ré-émission acceptée
		 * ajoute une ligne d'historique par massif, et l'écran Historique est un
		 * livrable produit (§6 du brief). Ré-émettre un corps inchangé dont la
		 * projection est complète ne réparerait rien et polluerait l'historique
		 * à chacune des 96 passes du jour.
		 */
		$rejeu = $inchange && self::rejeu_autorise( $date_ymd );

		if ( $inchange && ! $rejeu ) {
			// LE PASSAGE EST JOURNALISÉ QUAND MÊME. Sortir ici sans écrire au
			// journal rendait le chemin nominal « corps identique » totalement
			// invisible — et, la date restant désormais candidate d'une passe à
			// l'autre, aurait rendu l'anti-rafale aveugle sur ce chemin.
			StateRepository::record_issue( $date_ymd, 'succes', $note );

			return true;
		}

		if ( $rejeu ) {
			SnapshotRepository::consommer_rejeu( $date_ymd );
			$note .= ' Projection précédente en échec : nouvelle tentative de projection.';
		}

		StateRepository::record_issue( $date_ymd, 'succes', $note );

		$motif = 'publication';

		if ( $rejeu ) {
			$motif = 'rejeu';
		} elseif ( null !== $existant ) {
			$motif = 'republication';
		}

		self::publier( $instantane, $date_ymd, $motif );

		return true;
	}

	/**
	 * Journalise un échec, le signale, et le retourne.
	 *
	 * @param string    $date_ymd Date de validité visée.
	 * @param string    $issue    Issue à journaliser.
	 * @param \WP_Error $erreur   Erreur associée.
	 */
	private static function echouer( string $date_ymd, string $issue, \WP_Error $erreur ): \WP_Error {
		StateRepository::record_issue( $date_ymd, $issue, (string) $erreur->get_error_message(), $erreur );
		self::signaler_echec( $erreur );

		return $erreur;
	}

	/**
	 * Diffuse l'échec avec l'état consolidé.
	 *
	 * @param \WP_Error $erreur Erreur survenue.
	 */
	private static function signaler_echec( \WP_Error $erreur ): void {
		/**
		 * Un échec de récupération est survenu.
		 *
		 * @param \WP_Error           $erreur Erreur survenue.
		 * @param array<string,mixed> $etat   État du connecteur après journalisation.
		 */
		do_action( 'massifs_prefecture_echec', $erreur, StateRepository::get() );
	}

	/**
	 * Fabrique une erreur de transport à partir d'un code HTTP.
	 *
	 * @param string $code    Code d'erreur.
	 * @param int    $statut  Code HTTP reçu.
	 * @param string $libelle Libellé court.
	 */
	private static function erreur_http( string $code, int $statut, string $libelle ): \WP_Error {
		return new \WP_Error(
			$code,
			sprintf( '%s : HTTP %d.', $libelle, $statut ),
			array(
				'couche' => 'transport',
				'detail' => $statut,
			)
		);
	}
}
