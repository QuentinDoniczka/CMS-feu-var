<?php
/**
 * Fabrique d'états de FRAÎCHEUR pour la recette de rendu (issue #70).
 *
 * Exécutée par `wp eval-file` dans le conteneur d'outillage, elle place la base
 * dans un état de départ CONNU, puis rend la main : c'est le navigateur qui
 * observe ensuite le site réel, en HTTP, depuis l'hôte.
 *
 *   wp eval-file /massifs-tests/rendu/fraicheur.php fraicheur valide   valide
 *   wp eval-file /massifs-tests/rendu/fraicheur.php fraicheur malforme valide
 *   wp eval-file /massifs-tests/rendu/fraicheur.php fraicheur absent   absent
 *
 * Une grille à DEUX AXES — `publication` × `releve` — qui mappe directement les
 * deux coordonnées du tableau des quatre rendus de `<p class="ardoise__fraicheur">`
 * (contrat #70 §2). Chaque axe vaut `valide`, `malforme` ou `absent`.
 *
 * CE QUE CETTE FIXTURE PROUVE
 * ---------------------------
 * Un horodatage corrompu en base ne produit jamais ni page tronquée, ni valeur
 * inventée : il rend le site PLUS prudent, pas moins. La proposition qui
 * s'appuyait sur l'horodatage est omise — proprement, sans virgule orpheline ni
 * tiret suspendu — et le bandeau « Donnée périmée. » se lève quand le relevé
 * disparaît. Sur le même axe, `malforme` et `absent` rendent le même HTML :
 * la corruption ne crée aucun rendu nouveau.
 *
 * CE QU'ELLE NE PROUVE PAS, ET POURQUOI STRUCTURELLEMENT
 * -----------------------------------------------------
 * Elle n'exerce PAS les `try/catch ( \InvalidArgumentException )` de
 * `front-page.php`. Quatre barrages, tous situés dans l'extension, font qu'un
 * horodatage malformé n'atteint jamais le thème sous forme de chaîne refusée
 * (contrat #70 §1) : `RegistreReleves::entree()` supprime la clé au parsing
 * raté, `Fraicheur::evaluer()` porte une seconde ceinture,
 * `massifs_enregistrer_releve_reussi()` refuse un instant malformé en amont, et
 * `massifs_enregistrer_statut()` refuse un `publie_prefecture_le` malformé.
 * Ce que cette fixture exerce est donc l'ASSAINISSEUR DE L'EXTENSION, jamais le
 * filet du thème.
 *
 * Le `try/catch` de `front-page.php` reste par conséquent NON EXERCÉ PAR
 * FIXTURE. C'est un résultat acceptable parce qu'il est écrit : le seul geste
 * qui l'atteindrait serait de relâcher une ceinture d'assainissement,
 * c'est-à-dire d'affaiblir le code qui protège contre l'affichage d'une donnée
 * inventée pour prouver qu'un filet de sécurité fonctionne. Marché perdant,
 * refusé.
 *
 * AUCUNE LIGNE DE JOURNAL N'EST OBSERVABLE
 * ----------------------------------------
 * `docker-compose.yml` fixe `WORDPRESS_DEBUG: 0`, et `massifs_journaliser()`
 * sort immédiatement sans `WP_DEBUG`. Les lignes J-0 et J-1 que la recette R-29
 * du contrat #29 demande d'attendre ne sont écrites NULLE PART dans ce
 * conteneur. Aucune assertion de journal n'est donc possible ici, et aucune
 * n'est contournée en la présentant comme vérifiée.
 *
 * Aucune source externe n'est contactée : les statuts sont écrits par la
 * fonction d'écriture publique du domaine, exactement comme le fera le portail.
 * Aucun état n'est hérité d'une exécution précédente — chaque invocation
 * commence par une purge complète, trois chaînes partageant la même base.
 *
 * La fixture n'assemble JAMAIS la phrase attendue : elle émet des valeurs
 * atomiques, encodées en base64url sur la ligne `ETAT`. Le serveur possède les
 * valeurs, le scénario possède le gabarit — sans quoi le test comparerait deux
 * implémentations de la même règle de séparateurs au lieu de vérifier la règle.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

// Pas de `declare(strict_types=1)` : `wp eval-file` évalue ce fichier, et une
// déclaration stricte n'est légale qu'en toute première instruction d'un script.

global $wpdb;

/**
 * Axes admis, dans l'ordre où la grammaire les énonce.
 */
$massifs_fraicheur_axes_admis = array( 'valide', 'malforme', 'absent' );

/**
 * Purge des statuts et des relevés.
 *
 * Nom propre à ce fichier : `etats.php` porte une fonction équivalente sous le
 * nom `massifs_recette_purger()`, et l'inclure ici est impossible — son
 * `switch` final s'exécuterait à l'inclusion et sortirait en code 1 sur un mode
 * inconnu. Deux noms distincts écartent toute redéclaration.
 */
function massifs_fraicheur_recette_purger(): void {
	global $wpdb;

	delete_option( 'massifs_prefecture_etat' );
	delete_option( 'massifs_prefecture_snapshots' );
	delete_option( 'massifs_prefecture_reglages' );
	delete_option( 'massifs_dernier_releve' );

	$table = $wpdb->prefix . 'massifs_statuts';
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB

	wp_cache_flush();
}

/**
 * Instant figé, à Paris, exprimé en ISO 8601 UTC.
 *
 * Aucun instant ne dérive de l'HEURE d'exécution : seul le JOUR courant entre
 * dans le calcul, faute de quoi deux cas comparés octet pour octet différeraient
 * pour une raison qui n'est pas celle qu'on teste.
 *
 * @param int $decalage_jours Décalage en jours par rapport au jour courant.
 * @param int $heure          Heure locale de Paris.
 */
function massifs_fraicheur_recette_instant( int $decalage_jours, int $heure ): string {
	$paris = new DateTimeImmutable( massifs_jour_courant(), new DateTimeZone( 'Europe/Paris' ) );

	return $paris->modify( sprintf( '%+d days', $decalage_jours ) )
		->setTime( $heure, 0, 0 )
		->setTimezone( new DateTimeZone( 'UTC' ) )
		->format( 'Y-m-d\TH:i:s\Z' );
}

/**
 * Écrit un jeu complet de statuts pour le jour courant.
 *
 * Les niveaux sont posés de façon déterministe (le premier massif sur cinq est
 * interdit) : l'état global vaut `disponible`, condition sans laquelle le bloc
 * de fraîcheur du gabarit n'est pas atteint. La donnée bat le calendrier
 * (`Statuts::sans_donnee()`), donc cette précondition tient toute l'année ;
 * seule `perimee` reste saisonnière.
 *
 * @param string|null $publie_prefecture_le Instant de publication préfectorale, `null` pour aucun.
 */
function massifs_fraicheur_recette_publier( ?string $publie_prefecture_le ): void {
	$rang = 0;

	foreach ( massifs_codes() as $code ) {
		$niveau = 0 === $rang % 5 ? 'interdit' : 'autorise';
		$statut = array(
			'massif_code'   => $code,
			'jour_validite' => massifs_jour_courant(),
			'niveau_cle'    => $niveau,
			'zapef_cle'     => 0 === $rang % 2 ? $niveau : null,
			'source'        => 'saisie_manuelle',
			'auteur_id'     => 1,
		);

		if ( null !== $publie_prefecture_le ) {
			// Capté par `RegistreReleves::noter_publication()` sur le crochet
			// `massifs_statut_enregistre` : c'est le seul chemin d'écriture de
			// `publie_prefecture_le` dans le registre.
			$statut['publie_prefecture_le'] = $publie_prefecture_le;
		}

		massifs_enregistrer_statut( $statut );

		++$rang;
	}
}

/**
 * Corrompt des champs du registre des relevés, APRÈS écriture régulière.
 *
 * Les fonctions d'écriture du domaine refusent un horodatage malformé : la
 * seule façon d'obtenir une valeur corrompue EN BASE est donc d'écrire une
 * valeur valide, puis de réécrire l'option directement. C'est exactement le
 * scénario réel que l'issue #70 veut couvrir — une base abîmée, pas une API
 * abusée.
 *
 * @param list<string> $champs Champs de l'entrée `prefecture` à corrompre.
 */
function massifs_fraicheur_recette_corrompre( array $champs ): void {
	if ( array() === $champs ) {
		return;
	}

	$registre = get_option( 'massifs_dernier_releve', array() );
	$registre = is_array( $registre ) ? $registre : array();
	$entree   = isset( $registre['prefecture'] ) && is_array( $registre['prefecture'] ) ? $registre['prefecture'] : array();

	foreach ( $champs as $champ ) {
		$entree[ $champ ] = 'pas-une-date';
	}

	$registre['prefecture'] = $entree;

	update_option( 'massifs_dernier_releve', $registre, true );
	wp_cache_flush();
}

/**
 * Encode une valeur pour la ligne `ETAT`, en base64url sans remplissage.
 *
 * Le harnais parse la ligne par `/(\w+)=([\w-]+)/g` : aucune valeur ne peut
 * contenir d'espace, de `:` ni de `+`, or une date longue française contient des
 * espaces et le base64 standard contient `+` et `/`. Ce dernier serait coupé
 * SILENCIEUSEMENT par le motif, en tronquant la valeur sans lever d'erreur.
 *
 * Une valeur absente est rendue par le littéral `-` : le motif exige au moins un
 * caractère, si bien qu'une chaîne vide ferait DISPARAÎTRE la clé de la ligne —
 * le motif reprend simplement à la clé suivante, sans rien signaler. « Absente »
 * deviendrait alors indiscernable de « jamais écrite », et le scénario ne
 * pourrait plus asserter que le serveur transmet exactement les valeurs que la
 * combinaison exige. Aucune ambiguïté possible — le base64url d'une chaîne non
 * vide fait au moins deux caractères. Côté scénario, `-` se décode en chaîne vide.
 *
 * @param string $valeur Valeur à transmettre, éventuellement vide.
 */
function massifs_fraicheur_recette_encoder( string $valeur ): string {
	if ( '' === $valeur ) {
		return '-';
	}

	return rtrim( strtr( base64_encode( $valeur ), '+/', '-_' ), '=' );
}

/**
 * Champ mis en forme d'un instant, ou chaîne vide si l'instant est absent.
 *
 * @param mixed  $instant Instant ISO 8601 UTC relu dans le domaine, ou `null`.
 * @param string $cle     Clé de `massifs_horodatage()` : `date_longue`, `date_courte` ou `heure`.
 */
function massifs_fraicheur_recette_champ( $instant, string $cle ): string {
	if ( ! is_string( $instant ) || '' === $instant ) {
		return '';
	}

	return massifs_horodatage( $instant )[ $cle ];
}

/**
 * Décrit l'état obtenu, tel que le DOMAINE le voit.
 *
 * Aucune valeur rapportée ici n'est celle que la fabrique a cru écrire : toutes
 * sont relues dans `massifs_fraicheur()`, `massifs_synthese_du_jour()` et
 * `massifs_horodatage()`. Le scénario confronte donc le HTML servi aux valeurs
 * du serveur, jamais à une hypothèse de ce fichier.
 *
 * `combinaison` fait exception, et c'est voulu : c'est ce que la fabrique
 * PRÉTEND produire, à partir des seuls axes demandés. Son désaccord avec le
 * triplet `validite`/`publication`/`releve` relu dans le domaine est un défaut de
 * fixture, que le scénario doit voir plutôt que subir.
 *
 * @param string $axe_publication Axe demandé pour la publication préfectorale.
 * @param string $axe_releve      Axe demandé pour le relevé.
 */
function massifs_fraicheur_recette_rapporter( string $axe_publication, string $axe_releve ): void {
	$fraicheur = massifs_fraicheur( null );
	$synthese  = massifs_synthese_du_jour( massifs_codes(), null );

	// Le gabarit n'atteint le bloc de fraîcheur que dans la branche
	// `disponible` de son `match` sur `etat_global` (`front-page.php` l. 135 et
	// 203) : la condition est reproduite ici depuis la valeur du domaine, pas
	// devinée.
	$bloc = 'disponible' === $synthese['etat_global'];

	// Passerelle du gabarit (`front-page.php` l. 400-402) : sans elle, la
	// proposition « Statuts du {date} » est omise, et les deux autres avec elle.
	$validite = $fraicheur['jour_validite'] === $synthese['jour_validite']
		&& $synthese['jour_validite'] === massifs_jour_courant()
		&& is_string( $fraicheur['evalue_le'] );

	$publication = is_string( $fraicheur['publie_prefecture_le'] );
	$releve      = is_string( $fraicheur['dernier_releve_le'] );

	$combinaison = 'valide' === $axe_publication
		? ( 'valide' === $axe_releve ? 'c1' : 'c3' )
		: ( 'valide' === $axe_releve ? 'c2' : 'c4' );

	printf(
		"ETAT fraicheur axe_publication=%s axe_releve=%s etat=%s bloc=%d validite=%d publication=%d releve=%d perimee=%d actif=%d jour=%s combinaison=%s jour_long=%s pub_heure=%s rel_long=%s rel_court=%s rel_heure=%s\n",
		$axe_publication,
		$axe_releve,
		$synthese['etat_global'],
		$bloc ? 1 : 0,
		$validite ? 1 : 0,
		$publication ? 1 : 0,
		$releve ? 1 : 0,
		true === $fraicheur['perimee'] ? 1 : 0,
		true === $fraicheur['dispositif_actif'] ? 1 : 0,
		$synthese['jour_validite'],
		$combinaison,
		massifs_fraicheur_recette_encoder( massifs_fraicheur_recette_champ( $fraicheur['evalue_le'], 'date_longue' ) ),
		massifs_fraicheur_recette_encoder( massifs_fraicheur_recette_champ( $fraicheur['publie_prefecture_le'], 'heure' ) ),
		massifs_fraicheur_recette_encoder( massifs_fraicheur_recette_champ( $fraicheur['dernier_releve_le'], 'date_longue' ) ),
		massifs_fraicheur_recette_encoder( massifs_fraicheur_recette_champ( $fraicheur['dernier_releve_le'], 'date_courte' ) ),
		massifs_fraicheur_recette_encoder( massifs_fraicheur_recette_champ( $fraicheur['dernier_releve_le'], 'heure' ) )
	);
}

$massifs_fraicheur_mode            = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : '';
$massifs_fraicheur_axe_publication = isset( $args[1] ) && is_string( $args[1] ) ? $args[1] : '';
$massifs_fraicheur_axe_releve      = isset( $args[2] ) && is_string( $args[2] ) ? $args[2] : '';

if ( 'fraicheur' !== $massifs_fraicheur_mode
	|| 3 !== count( $args )
	|| ! in_array( $massifs_fraicheur_axe_publication, $massifs_fraicheur_axes_admis, true )
	|| ! in_array( $massifs_fraicheur_axe_releve, $massifs_fraicheur_axes_admis, true ) ) {

	fwrite( STDERR, "Grammaire : fraicheur <publication> <releve>, chaque axe parmi valide | malforme | absent\n" );
	exit( 1 );
}

massifs_fraicheur_recette_purger();

// Instants FIGÉS. `INSTANT_PUBLICATION` : la veille à 17 h 00 Paris — heure
// établie par relevé de l'en-tête HTTP `Last-Modified` du JSON préfectoral
// (`docs/decisions/source-prefecture.md` §4.10), et non par le « vers 18-19 h »
// périmé de `docs/BRIEF.md` §4.2. `INSTANT_RELEVE` : le jour courant à 06 h 00
// Paris, strictement dans les 24 h pour que `perimee` reste faux tant que le
// relevé est lisible ; exécutée avant 6 h, la fixture pose un instant à venir,
// dont l'âge est ramené à 0 par `Fraicheur::evaluer()`, sans changer le rendu.
$massifs_fraicheur_instant_publication = massifs_fraicheur_recette_instant( -1, 17 );
$massifs_fraicheur_instant_releve      = massifs_fraicheur_recette_instant( 0, 6 );

$massifs_fraicheur_a_corrompre = array();

massifs_fraicheur_recette_publier(
	'absent' === $massifs_fraicheur_axe_publication ? null : $massifs_fraicheur_instant_publication
);

if ( 'malforme' === $massifs_fraicheur_axe_publication ) {
	$massifs_fraicheur_a_corrompre[] = 'publie_le';
}

if ( 'absent' !== $massifs_fraicheur_axe_releve ) {
	massifs_enregistrer_releve_reussi( 'prefecture', $massifs_fraicheur_instant_releve );
}

if ( 'malforme' === $massifs_fraicheur_axe_releve ) {
	$massifs_fraicheur_a_corrompre[] = 'instant';
}

massifs_fraicheur_recette_corrompre( $massifs_fraicheur_a_corrompre );

massifs_fraicheur_recette_rapporter( $massifs_fraicheur_axe_publication, $massifs_fraicheur_axe_releve );
