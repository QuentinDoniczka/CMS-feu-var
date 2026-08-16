<?php
/**
 * Épic 5 — le journal du portail est exact, sous filtre et à cheval sur une page.
 *
 * L'histoire : trois gestionnaires corrigent tour à tour le MÊME massif pour le
 * MÊME jour. Le §12 exige que le journal dise « qui, quoi, quand, ancienne et
 * nouvelle valeur ». Ce scénario éprouve les quatre façons documentées dont la
 * dérivation naïve du lot 1 se trompait (contrat #15 §0.2) :
 *
 *   1. l'ordre DESCENDANT — la ligne « du dessus » est l'écriture SUIVANTE, donc
 *      une valeur FUTURE présentée comme ancienne ;
 *   2. le filtre par AUTEUR — le couple (massif, jour) est tronqué, une correction
 *      se déclare « première publication » ;
 *   3. le filtre par SOURCE — même mensonge ;
 *   4. la PAGINATION — le même mensonge à chaque frontière de page.
 *
 * Chaque assertion porte sur ce que le domaine RÉPOND au portail, jamais sur une
 * méthode privée. Le scénario est autonome : il purge avant et après, et passe
 * lancé seul.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once '/massifs-tests/bootstrap.php';

t_reset();

// La création d'un compte déclenche le courriel « définissez votre mot de passe »
// du cœur. Intercepté : aucun octet ne sort de la stack, et le scénario ne dépend
// d'aucun serveur de messagerie.
$courriels = array();
t_intercepter_mail( $courriels );

// ---------------------------------------------------------------- décor

$capacite_publier    = massifs_capacite_publier();
$capacite_historique = massifs_capacite_historique();
$role                = massifs_role_gestionnaire();

$codes = massifs_codes();
t_egal( 25, count( $codes ), 'le référentiel porte bien 25 massifs' );

$massif = 'sainte-victoire';
$jour   = massifs_jour_courant();
$autre  = t_jour_apres( $jour );

// Les trois actions de compte du §6 exigent `massifs_gerer_gestionnaires` de
// l'ACTEUR : on se met donc dans la peau d'un administrateur, comme le fait
// l'écran Utilisateurs. Sans cela, `Comptes::creer()` refuse — et c'est le bon
// comportement, éprouvé juste en dessous à la négative.
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
t_assert( $admin > 0, 'un administrateur existe sur cette installation', '> 0', $admin );

$refus_anonyme = \Massifs\Security\Roles\Comptes::creer(
	array(
		'identifiant' => 'recette-jamais-cree',
		'email'       => 'recette-jamais-cree@massifs.invalid',
	)
);
t_assert(
	is_wp_error( $refus_anonyme ),
	'sans acteur authentifié, la création d’un compte gestionnaire est REFUSÉE',
	'un WP_Error',
	var_export( $refus_anonyme, true )
);
t_assert( false === get_user_by( 'login', 'recette-jamais-cree' ), 'et aucun compte n’a été écrit', 'absent', 'présent' );

wp_set_current_user( $admin );

// Trois auteurs réels, créés par le point de passage du contrat #13 : jamais un
// `wp_insert_user` nu, qui contournerait la validation de mot de passe et la
// journalisation des évènements de compte.
$auteurs = array();
foreach ( array( 'recette-gest-a', 'recette-gest-b', 'recette-gest-c' ) as $login ) {
	$existant = get_user_by( 'login', $login );

	if ( $existant instanceof WP_User ) {
		$auteurs[ $login ] = (int) $existant->ID;
		continue;
	}

	$cree = \Massifs\Security\Roles\Comptes::creer(
		array(
			'identifiant' => $login,
			'email'       => $login . '@massifs.invalid',
			'nom_affiche' => 'Recette ' . strtoupper( substr( $login, -1 ) ),
		)
	);

	t_assert( is_int( $cree ) && $cree > 0, 'Comptes::creer() crée le compte ' . $login, 'un identifiant entier', is_wp_error( $cree ) ? $cree->get_error_message() : var_export( $cree, true ) );

	$auteurs[ $login ] = is_int( $cree ) ? $cree : 0;
}

$a = $auteurs['recette-gest-a'];
$b = $auteurs['recette-gest-b'];
$c = $auteurs['recette-gest-c'];

foreach ( $auteurs as $login => $id ) {
	$utilisateur = get_userdata( $id );
	t_egal( array( $role ), array_values( (array) $utilisateur->roles ), $login . ' porte le rôle gelé du lot, et lui seul' );
	t_assert( user_can( $id, $capacite_publier ), $login . ' peut publier des statuts', true, false );
	t_assert( user_can( $id, $capacite_historique ), $login . ' peut consulter l’historique', true, false );
}

// ---------------------------------------------------------------- la matrice de capacités, à la négative
//
// Ce que le §6 refuse au gestionnaire : ni contenus, ni réglages, ni extensions,
// ni utilisateurs. Testé sur les capacités du CŒUR, seule chose que WordPress
// consulte pour construire le menu et garder les écrans.

$interdites = array(
	'edit_posts',
	'edit_pages',
	'publish_posts',
	'upload_files',
	'manage_options',
	'activate_plugins',
	'install_plugins',
	'edit_themes',
	'switch_themes',
	'list_users',
	'create_users',
	'edit_users',
	'delete_users',
	'promote_users',
	'moderate_comments',
	'edit_theme_options',
	'export',
	'import',
	'update_core',
	'unfiltered_html',
	'edit_files',
	'manage_categories',
	'massifs_gerer_gestionnaires',
);

$accordees_a_tort = array();
foreach ( $interdites as $capacite ) {
	if ( user_can( $a, $capacite ) ) {
		$accordees_a_tort[] = $capacite;
	}
}
t_egal( array(), $accordees_a_tort, 'le gestionnaire ne porte AUCUNE capacité de contenu, de réglage, d’extension ni d’utilisateur' );

$attendues = array( 'read', $capacite_publier, $capacite_historique );
$portees   = array_keys( array_filter( wp_roles()->roles[ $role ]['capabilities'] ) );
sort( $attendues );
sort( $portees );
t_egal( $attendues, $portees, 'le rôle ' . $role . ' porte exactement read + les deux capacités du portail' );

// L'administrateur, lui, porte les trois — sinon l'interdit 1 du contrat #13
// (« tester la capacité, jamais le rôle ») n'aurait aucun sens.
foreach ( massifs_capacites_massifs() as $capacite ) {
	t_assert( user_can( $admin, $capacite ), 'l’administrateur porte ' . $capacite, true, false );
}

// ---------------------------------------------------------------- trois écritures successives, même couple

$sequence = array(
	array( 'auteur' => $a, 'niveau' => 'autorise' ),
	array( 'auteur' => $b, 'niveau' => 'interdit' ),
	array( 'auteur' => $c, 'niveau' => 'autorise' ),
);

$ids = array();
foreach ( $sequence as $rang => $ecriture ) {
	$resultat = massifs_enregistrer_statut(
		array(
			'massif_code'   => $massif,
			'jour_validite' => $jour,
			'source'        => 'saisie_manuelle',
			'auteur_id'     => $ecriture['auteur'],
			'niveau_cle'    => $ecriture['niveau'],
			'zapef_cle'     => null,
		)
	);

	t_assert( true === $resultat['enregistre'], 'écriture ' . ( $rang + 1 ) . ' acceptée', 'enregistrée', wp_json_encode( $resultat['erreurs'] ) );
	$ids[] = (int) $resultat['id'];

	// La colonne `enregistre_le` est un `datetime` à la seconde : sans cette
	// pause, les trois écritures partagent l'horodatage et l'ordre de la vue
	// chronologique dépendrait du seul `id`. On veut éprouver l'ordre RÉEL.
	sleep( 1 );
}

t_egal( 3, count( array_unique( $ids ) ), 'les trois écritures sont trois lignes distinctes — la table est en insertion pure' );

// Une quatrième écriture, sur un AUTRE jour, par le même auteur : c'est elle qui
// piège une dérivation qui oublierait de partitionner par jour.
massifs_enregistrer_statut(
	array(
		'massif_code'   => $massif,
		'jour_validite' => $autre,
		'source'        => 'saisie_manuelle',
		'auteur_id'     => $a,
		'niveau_cle'    => 'interdit',
		'zapef_cle'     => null,
	)
);

// ---------------------------------------------------------------- le journal, en ordre chronologique inverse

$journal = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour ) );
t_egal( 3, count( $journal ), 'le journal du couple (massif, jour) porte les trois écritures, jamais une de plus' );

// L'ordre contractuel est « de la plus récente à la plus ancienne ».
$ordre = array_map( static fn( $e ) => (int) $e['id'], $journal );
$attendu_ordre = $ids;
rsort( $attendu_ordre );
t_egal( $attendu_ordre, $ordre, 'le journal est rendu de la plus récente à la plus ancienne' );

$par_id = array();
foreach ( $journal as $entree ) {
	$par_id[ (int) $entree['id'] ] = $entree;
}

// C'EST L'ASSERTION CENTRALE DU §12 « journal exact ». La valeur précédente de
// chaque ligne est celle de l'écriture qui la PRÉCÈDE, jamais celle qui la suit.
$attendu_transitions = array(
	$ids[0] => array( 'precedent' => null,       'nouveau' => 'autorise', 'auteur' => $a, 'changement' => 'premiere_publication' ),
	$ids[1] => array( 'precedent' => 'autorise', 'nouveau' => 'interdit', 'auteur' => $b, 'changement' => 'modification' ),
	$ids[2] => array( 'precedent' => 'interdit', 'nouveau' => 'autorise', 'auteur' => $c, 'changement' => 'modification' ),
);

// `?? 'ABSENTE'` serait un piège ici : la valeur ATTENDUE est `null` sur la
// première écriture, et l'opérateur de coalescence ne distingue pas « clé
// absente » de « valeur nulle ». On lit donc la clé explicitement.
$lire = static function ( array $entree, string $cle ) {
	return array_key_exists( $cle, $entree ) ? $entree[ $cle ] : 'CLE_ABSENTE';
};

foreach ( $attendu_transitions as $id => $attendu ) {
	$entree = $par_id[ $id ] ?? array();

	t_egal( $attendu['precedent'], $lire( $entree, 'niveau_precedent_cle' ), 'ligne ' . $id . ' : ancienne valeur exacte' );
	t_egal( $attendu['nouveau'], $entree['niveau_cle'] ?? 'ABSENTE', 'ligne ' . $id . ' : nouvelle valeur exacte' );
	t_egal( $attendu['auteur'], $entree['auteur_id'] ?? 0, 'ligne ' . $id . ' : QUI est exact' );
	t_egal( $attendu['changement'], $entree['changement'] ?? 'ABSENTE', 'ligne ' . $id . ' : la qualification du changement est exacte' );
	t_egal( 'saisie_manuelle', $entree['source'] ?? 'ABSENTE', 'ligne ' . $id . ' : la provenance est exacte' );
	t_egal( $jour, $entree['jour_validite'] ?? 'ABSENTE', 'ligne ' . $id . ' : QUOI — le jour de validité est exact' );
	t_egal( $massif, $entree['massif_code'] ?? 'ABSENTE', 'ligne ' . $id . ' : QUOI — le massif est exact' );

	// QUAND : ISO 8601 UTC, jamais un horodatage local ni une chaîne vide.
	$quand = (string) ( $entree['enregistre_le'] ?? '' );
	t_assert(
		1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|\+00:00)$/', $quand ),
		'ligne ' . $id . ' : QUAND est un instant ISO 8601 UTC',
		'YYYY-MM-DDTHH:MM:SSZ',
		$quand
	);
}

// Le nom d'auteur est toujours résoluble et jamais vide (contrat #13).
foreach ( array( $a, $b, $c, 999999 ) as $identifiant ) {
	$nom = massifs_nom_auteur( $identifiant );
	t_assert( '' !== $nom, 'massifs_nom_auteur(' . $identifiant . ') ne rend jamais une chaîne vide', 'un libellé', '(vide)' );
}
t_egal( 'Auteur inconnu', massifs_nom_auteur( 999999 ), 'un auteur qui ne résout plus reste lisible — l’historique ne se troue pas' );

// ---------------------------------------------------------------- filtre par auteur : le piège du couple tronqué

$par_auteur = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'auteur_id' => $b ) );
t_egal( 1, count( $par_auteur ), 'filtré par l’auteur du milieu, le journal ne rend que sa ligne' );
t_egal(
	'autorise',
	$par_auteur[0]['niveau_precedent_cle'] ?? 'ABSENTE',
	'FILTRE PAR AUTEUR : l’ancienne valeur reste celle de l’écriture précédente — la correction ne se déclare PAS « première publication »'
);
t_egal( 'modification', $par_auteur[0]['changement'] ?? 'ABSENTE', 'FILTRE PAR AUTEUR : le changement reste qualifié « modification »' );
t_egal( false, $par_auteur[0]['premiere_publication'] ?? 'ABSENTE', 'FILTRE PAR AUTEUR : `premiere_publication` reste faux' );

// Le troisième auteur, isolé : son précédent est la ligne du DEUXIÈME, pas la
// sienne — et surtout pas `null`.
$par_auteur_c = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'auteur_id' => $c ) );
t_egal( 'interdit', $par_auteur_c[0]['niveau_precedent_cle'] ?? 'ABSENTE', 'FILTRE PAR AUTEUR : la dernière écriture voit toujours l’avant-dernière' );

// Le PREMIER auteur, isolé : lui est bien une première publication — l'assertion
// symétrique, sans laquelle la précédente pourrait passer en dérivant « jamais null ».
$par_auteur_a = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'auteur_id' => $a ) );
t_egal( null, $lire( $par_auteur_a[0], 'niveau_precedent_cle' ), 'FILTRE PAR AUTEUR : la première écriture n’invente aucune valeur précédente' );
t_egal( true, $par_auteur_a[0]['premiere_publication'] ?? 'ABSENTE', 'FILTRE PAR AUTEUR : la première écriture est bien qualifiée « première publication »' );

// ---------------------------------------------------------------- filtre par source

$par_source = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'source' => 'saisie_manuelle' ) );
t_egal( 3, count( $par_source ), 'filtré sur la provenance des trois écritures, le journal les rend toutes' );
$par_source_precedents = array_map( static fn( $e ) => $e['niveau_precedent_cle'], $par_source );
t_egal( array( 'interdit', 'autorise', null ), $par_source_precedents, 'FILTRE PAR SOURCE : les valeurs précédentes restent exactes, dans l’ordre décroissant' );

// ---------------------------------------------------------------- pagination à cheval sur une frontière
//
// Une page de 1 : chaque ligne est SEULE dans son ensemble résultat. C'est le cas
// le plus dur pour une dérivation par parcours, qui n'a plus aucune ligne voisine
// à regarder.

$pages = array();
for ( $decalage = 0; $decalage < 3; $decalage++ ) {
	$page = massifs_journal_statuts(
		array(
			'massif_code' => $massif,
			'jour_debut'  => $jour,
			'jour_fin'    => $jour,
			'limite'      => 1,
			'decalage'    => $decalage,
		)
	);
	t_egal( 1, count( $page ), 'page de taille 1, décalage ' . $decalage . ' : une entrée' );
	$pages[] = $page[0];
}

t_egal(
	array_map( static fn( $e ) => (int) $e['id'], $pages ),
	$attendu_ordre,
	'les trois pages de taille 1 reconstituent exactement l’ordre du journal complet, sans doublon ni trou'
);
t_egal(
	array( 'interdit', 'autorise', null ),
	array_map( static fn( $e ) => $e['niveau_precedent_cle'], $pages ),
	'PAGINATION : à chaque frontière de page, l’ancienne valeur reste celle de la partition NON FILTRÉE'
);

// Une page de 2, puis une page de 1 : la frontière tombe ENTRE la deuxième et la
// troisième écriture, exactement là où une dérivation par parcours ment.
$page_1 = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'limite' => 2, 'decalage' => 0 ) );
$page_2 = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'limite' => 2, 'decalage' => 2 ) );
t_egal( 2, count( $page_1 ), 'page 1 de taille 2 : deux entrées' );
t_egal( 1, count( $page_2 ), 'page 2 de taille 2 : la dernière entrée' );

// LA frontière : la DERNIÈRE ligne de la page 1 est la deuxième écriture, dont la
// valeur précédente est celle de la PREMIÈRE — laquelle n'est PAS dans la page.
// C'est très exactement ce que la dérivation par parcours du lot 1 ne pouvait pas
// voir, et elle déclarait donc « première publication ».
t_egal( $ids[1], (int) $page_1[1]['id'], 'la page 1 se termine bien sur la deuxième écriture' );
t_egal( 'autorise', $lire( $page_1[1], 'niveau_precedent_cle' ), 'FRONTIÈRE DE PAGE : la dernière ligne de la page 1 voit une écriture qui n’est pas dans sa page' );
t_egal( 'modification', $page_1[1]['changement'] ?? 'ABSENTE', 'FRONTIÈRE DE PAGE : elle reste qualifiée « modification », pas « première publication »' );
t_egal( $ids[0], (int) $page_2[0]['id'], 'la page 2 porte la plus ancienne écriture' );
t_egal( null, $lire( $page_2[0], 'niveau_precedent_cle' ), 'FRONTIÈRE DE PAGE : la plus ancienne écriture reste sans valeur précédente' );

// Le total et la borne sont normalisés par les MÊMES critères que la liste :
// sinon la pagination affiche un nombre de pages qui ne correspond à rien.
t_egal( 3, massifs_journal_statuts_total( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour ) ), 'le total ignore `limite` et `decalage`' );
t_egal( 1, massifs_journal_statuts_total( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour, 'auteur_id' => $b ) ), 'le total suit le filtre par auteur' );
t_egal( max( $ids ), massifs_journal_statuts_borne( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour ) ), 'la borne d’export fige la fenêtre sur le plus grand identifiant' );

// ---------------------------------------------------------------- l'autre jour n'est jamais mélangé

$journal_autre = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $autre, 'jour_fin' => $autre ) );
t_egal( 1, count( $journal_autre ), 'l’écriture du jour suivant est une entrée distincte' );
t_egal( null, $lire( $journal_autre[0], 'niveau_precedent_cle' ), 'la partition est bien (massif, JOUR) : l’écriture du lendemain est une première publication' );

// ---------------------------------------------------------------- les auteurs du journal, jamais les comptes de l'installation

$auteurs_journal = massifs_journal_auteurs();
sort( $auteurs_journal );
$attendus_auteurs = array( $a, $b, $c );
sort( $attendus_auteurs );
t_egal( $attendus_auteurs, $auteurs_journal, 'massifs_journal_auteurs() ne liste QUE les auteurs présents dans le journal — jamais tous les comptes (§9)' );

$comptes = get_users( array( 'fields' => 'ID' ) );
t_assert(
	count( $comptes ) > count( $auteurs_journal ),
	'l’installation porte plus de comptes que le journal n’a d’auteurs — la garde d’énumération est donc réellement éprouvée',
	'strictement plus',
	count( $comptes ) . ' comptes contre ' . count( $auteurs_journal ) . ' auteurs'
);

// ---------------------------------------------------------------- suspension : capacités ET session

$sessions_avant = WP_Session_Tokens::get_instance( $b );
$sessions_avant->create( time() + 3600 );
t_assert( count( $sessions_avant->get_all() ) > 0, 'une session est ouverte pour le gestionnaire B', '≥ 1', 0 );

$suspendu = \Massifs\Security\Roles\Comptes::suspendre( $b );
t_assert( true === $suspendu, 'Comptes::suspendre() aboutit', true, is_wp_error( $suspendu ) ? $suspendu->get_error_message() : var_export( $suspendu, true ) );

t_assert( massifs_compte_est_suspendu( $b ), 'le compte est marqué suspendu', true, false );
t_assert( ! user_can( $b, $capacite_publier ), 'SUSPENSION : la capacité de publier est retirée à chaud', false, true );
t_assert( ! user_can( $b, $capacite_historique ), 'SUSPENSION : la capacité de consulter l’historique est retirée à chaud', false, true );
t_assert( user_can( $b, 'read' ), 'SUSPENSION : `read` est conservé — sans lui le cœur éjecterait le compte de wp-admin', true, false );

$sessions_apres = WP_Session_Tokens::get_instance( $b );
t_egal( 0, count( $sessions_apres->get_all() ), 'SUSPENSION : la session en cours est DÉTRUITE — une suspension qui laisse publier n’est pas une suspension' );

// Le journal de l'historique ne perd rien : le §4.2 exige l'historique intégral.
$journal_apres_suspension = massifs_journal_statuts( array( 'massif_code' => $massif, 'jour_debut' => $jour, 'jour_fin' => $jour ) );
t_egal( 3, count( $journal_apres_suspension ), 'suspendre un auteur ne retire AUCUNE ligne de l’historique' );
t_egal( $b, $journal_apres_suspension[1]['auteur_id'] ?? 0, 'l’écriture d’un compte suspendu reste attribuée à ce compte' );

// Suppression : bloquée par `map_meta_cap`, pas seulement « non proposée ».
t_assert(
	! user_can( $admin, 'delete_user', $b ),
	'un administrateur ne peut PAS supprimer un gestionnaire — l’historique ne peut pas être orphelin (interdit 6)',
	false,
	true
);

// Rétablissement.
$retabli = \Massifs\Security\Roles\Comptes::retablir( $b );
t_assert( true === $retabli, 'Comptes::retablir() aboutit', true, is_wp_error( $retabli ) ? $retabli->get_error_message() : var_export( $retabli, true ) );
t_assert( ! massifs_compte_est_suspendu( $b ), 'le compte n’est plus suspendu', false, true );
t_assert( user_can( $b, $capacite_publier ), 'RÉTABLISSEMENT : la capacité de publier revient', true, false );

// Réinitialisation : elle aussi ferme les sessions.
$sessions_b = WP_Session_Tokens::get_instance( $b );
$sessions_b->create( time() + 3600 );
$reinitialise = \Massifs\Security\Roles\Comptes::reinitialiser( $b );
t_assert( true === $reinitialise, 'Comptes::reinitialiser() aboutit', true, is_wp_error( $reinitialise ) ? $reinitialise->get_error_message() : var_export( $reinitialise, true ) );
t_egal( 0, count( WP_Session_Tokens::get_instance( $b )->get_all() ), 'RÉINITIALISATION : les sessions sont fermées' );

// Les trois actions du §6 sont journalisées, avec qui / quoi / quand.
$journal_comptes = get_option( 'massifs_journal_comptes', array() );
t_assert( is_array( $journal_comptes ) && array() !== $journal_comptes, 'le registre des évènements de compte est alimenté', 'un registre non vide', var_export( $journal_comptes, true ) );

$types = array();
foreach ( $journal_comptes as $evenement ) {
	if ( isset( $evenement['cible_id'] ) && (int) $evenement['cible_id'] === $b ) {
		$types[] = (string) ( $evenement['type'] ?? '' );
	}
}
foreach ( array( 'compte_suspendu', 'compte_retabli', 'compte_reinitialise' ) as $type ) {
	t_assert( in_array( $type, $types, true ), 'l’évènement « ' . $type .' » est journalisé', 'présent', implode( ', ', $types ) );
}

$dernier = null;
foreach ( $journal_comptes as $evenement ) {
	if ( isset( $evenement['cible_id'] ) && (int) $evenement['cible_id'] === $b ) {
		$dernier = $evenement;
	}
}
foreach ( array( 'type', 'cible_id', 'cible_login', 'acteur_id', 'instant_iso_utc' ) as $champ ) {
	t_assert( isset( $dernier[ $champ ] ), 'l’évènement de compte porte le champ « ' . $champ . ' »', 'présent', wp_json_encode( array_keys( (array) $dernier ) ) );
}

// Interdit 8 du contrat #13 : aucun secret, aucun code, aucune IP journalisés.
$serialise = (string) wp_json_encode( $journal_comptes );
foreach ( array( 'totp_secret', 'codes_secours', 'remote_addr', 'ip_client' ) as $interdit ) {
	t_assert( ! str_contains( $serialise, $interdit ), 'le registre des comptes ne porte jamais « ' . $interdit . ' »', 'absent', 'présent' );
}
t_assert(
	1 !== preg_match( '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $serialise ),
	'aucune adresse IP en clair dans le registre des comptes (§9, interdit 8)',
	'aucune',
	$serialise
);

// ---------------------------------------------------------------- écluse : jamais un déni de service contre la démo (A-13)
//
// Les identifiants du compte de démonstration sont PUBLIÉS (§6). Si l'écluse
// comptait par identifiant seul, dix requêtes ratées depuis n'importe où
// suffiraient à éteindre la démonstration publique. On éprouve exactement cela :
// on sature une IP contre un identifiant, puis on regarde si une AUTRE IP est
// atteinte.

$ip_attaquant = '203.0.113.77';
$ip_visiteur  = '198.51.100.42';
$ip_legitime  = '192.0.2.31';
$cible        = 'gestionnaire-demo';

$ip_courante = $ip_attaquant;
add_filter( 'massifs_auth_ip_client', static function () use ( &$ip_courante ): string {
	return $ip_courante;
} );

/**
 * Remet l'écluse à l'état neuf.
 *
 * DÉFAUT DE RECETTE trouvé le 16 août 2026, en lançant la suite complète moins de
 * quinze minutes après ce seul scénario : `t_reset()` purge les statuts, pas
 * l'écluse. Or l'écluse persiste par nature — registre de verrous en option,
 * compteurs en transients à FENÊTRE FIXE de 900 s. La rafale de cette section
 * laissait donc, pour un quart d'heure, un verrou et des compteurs qui faisaient
 * échouer les trois assertions de départ de l'exécution suivante. Le scénario
 * passait lancé seul et rougissait relancé : ce n'est pas un défaut du code, c'est
 * ce fichier qui n'était pas autonome dans le temps.
 *
 * On purge donc AVANT et APRÈS. Rien n'est affaibli : la purge remet l'état
 * initial, elle ne remplace aucune assertion — celle du repos est conservée juste
 * après, et c'est elle qui prouve que la purge a bien eu lieu.
 */
$purger_ecluse = static function () use ( &$wpdb ): void {
	global $wpdb;

	delete_option( \Massifs\Security\Auth\Ecluse::OPTION_VERROUS );

	// Les compteurs sont des transients préfixés, dont le nom dépend d'un HMAC :
	// on ne peut pas les nommer, seulement les balayer par leur préfixe.
	$wpdb->query( // phpcs:ignore WordPress.DB
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_massifs_ecluse_c_%' OR option_name LIKE '_transient_timeout_massifs_ecluse_c_%'"
	);

	wp_cache_flush();
};

$purger_ecluse();

t_egal( 0, \Massifs\Security\Auth\Ecluse::attente( $cible ), 'écluse au repos avant la rafale' );

// Le seuil du couple est 5 / 15 min, celui de l'IP 10 / 15 min : quinze échecs
// franchissent les deux, ce qui est le pire cas pour la démonstration.
for ( $tentative = 0; $tentative < 15; $tentative++ ) {
	\Massifs\Security\Auth\Ecluse::signaler_echec( $cible );
}

$attente_attaquant = \Massifs\Security\Auth\Ecluse::attente( $cible );
t_assert( $attente_attaquant > 0, 'FORCE BRUTE : après quinze échecs, l’origine fautive est verrouillée', '> 0 s', $attente_attaquant );
t_note( 'délai annoncé à l’origine verrouillée : ' . $attente_attaquant . ' s' );

// ┌──────────────────────────────────────────────────────────────────────────┐
// │  CETTE ASSERTION NE PROUVE PAS QUE LA FORCE BRUTE EST BLOQUÉE.           │
// │                                                                           │
// │  Elle prouve que l'ALGÈBRE de l'écluse est juste : appelée directement,   │
// │  `barrer()` rend bien un WP_Error. Elle ne dit RIEN de ce que le          │
// │  formulaire de connexion en fait — et c'est là qu'est le défaut, mesuré   │
// │  le 15 août 2026 par le scénario de rendu `2fa` : greffée en priorité 1   │
// │  sur `authenticate`, la valeur de retour de `barrer()` est ÉCRASÉE par    │
// │  `wp_authenticate_username_password()` du cœur (priorité 20), qui ne      │
// │  court-circuite que sur un `WP_User`, jamais sur un `WP_Error`.           │
// │                                                                           │
// │  Ne jamais lire le vert ci-dessous comme la ligne de DoD « force brute    │
// │  bloquée ». Celle-ci est portée par le scénario de rendu `2fa`, sur le    │
// │  vrai formulaire. Le défaut a été corrigé (`bd1cd6d`, `05e614c`,          │
// │  `2ffba8d`) : le refus ne repose plus sur la valeur de retour de la       │
// │  priorité 1 mais sur `constater()` (40) et `reaffirmer()` (100).          │
// │                                                                           │
// │  L'APPEL DIRECT CI-DESSOUS EST CONSERVÉ EXPRÈS, et il n'est pas           │
// │  redondant : appeler `barrer()` HORS crochet laisse les rappels du cœur   │
// │  désarmés pour tout le reste du processus, la priorité 100 n'étant jamais │
// │  atteinte. C'est ce cas de figure qui a révélé le second défaut (faux     │
// │  refus sur la tentative légitime suivante), fermé par le réarmement en    │
// │  tête de `barrer()` (`05e614c`). Le retirer rouvrirait cet angle mort.    │
// └──────────────────────────────────────────────────────────────────────────┘
$refus = \Massifs\Security\Auth\Ecluse::barrer( null, $cible, 'peu-importe' );
t_assert( is_wp_error( $refus ), 'l’algèbre de l’écluse : appelée directement, `barrer()` refuse une origine verrouillée', 'un WP_Error', var_export( $refus, true ) );
t_assert(
	is_wp_error( $refus ) && 1 === preg_match( '/\d/', $refus->get_error_message() ),
	'le message de verrouillage annonce un délai chiffré — l’utilisateur légitime sait combien attendre',
	'un message portant un nombre',
	is_wp_error( $refus ) ? $refus->get_error_message() : ''
);

// A-13 — l'arbitrage à éprouver : le compte de démonstration reste joignable
// depuis une autre origine.
$ip_courante = $ip_visiteur;
t_egal(
	0,
	\Massifs\Security\Auth\Ecluse::attente( $cible ),
	'A-13 : le verrou est porté par l’ORIGINE, jamais par l’identifiant seul — la démonstration publique reste joignable depuis une autre IP'
);
t_egal(
	null,
	\Massifs\Security\Auth\Ecluse::barrer( null, $cible, 'peu-importe' ),
	'A-13 : une tentative venue d’une autre origine n’est pas barrée — pas de déni de service à un clic'
);

// ---------------------------------------------------------------- la purge, et ce qu'elle ne fait PAS
//
// Deux faits distincts, et le second ne vaut que parce que le premier tient.
//
// CE QUI A CHANGÉ LE 16 AOÛT 2026. La passe du 15 août affirmait ici, depuis
// l'origine ENCORE VERROUILLÉE, que `sur_connexion()` ramenait `attente()` à 0.
// C'était l'attente qui était fausse, pas le code : `wp_login` est une action
// PUBLIQUE — l'étape 2 du second facteur l'émet elle-même —, si bien qu'une purge
// inconditionnelle offrait à qui connaît le mot de passe le moyen d'effacer son
// propre verrou. Le correctif `2ffba8d` fait sortir `sur_connexion()` sans rien
// toucher tant qu'un verrou vit. L'ancienne assertion encodait donc exactement le
// trou ; elle est retournée, pas retirée.

$ip_courante = $ip_attaquant;
\Massifs\Security\Auth\Ecluse::sur_connexion( $cible );
t_assert(
	\Massifs\Security\Auth\Ecluse::attente( $cible ) > 0,
	'un `wp_login` survenu PENDANT un verrou ne l’efface pas — un verrou n’est jamais effaçable par qui connaît le mot de passe',
	'verrou toujours actif',
	\Massifs\Security\Auth\Ecluse::attente( $cible ) . ' s'
);
t_assert(
	is_wp_error( \Massifs\Security\Auth\Ecluse::barrer( null, $cible, 'peu-importe' ) ),
	'et la tentative suivante reste barrée après ce `wp_login` — la purge n’a pas rouvert la porte',
	'un WP_Error',
	'null'
);

// 2. HORS VERROU, la purge fait son travail : neuf échecs suivis d'un succès ne
//    doivent pas laisser un compteur armé contre l'utilisateur légitime.
//
// L'observable n'est pas `attente() === 0` juste après la purge — il l'est déjà
// avant, la mesure ne distinguerait rien. C'est la MARGE RESTANTE qui se mesure :
// on consomme quatre des cinq essais du couple, on purge, puis on en consomme
// quatre de plus. Si la purge n'avait rien fait, le cinquième échec cumulé aurait
// posé un verrou. Rester libre à huit échecs cumulés est la preuve.
$ip_courante = $ip_legitime;

// LE FAUX REFUS, éprouvé ici en algèbre et sur le vrai formulaire par `2fa`.
//
// L'appel direct à `barrer()` fait quelques lignes plus haut a désarmé les trois
// rappels de mot de passe du cœur et n'a jamais atteint la priorité 100 qui les
// remet. Sans le réarmement en tête de `barrer()` (`05e614c`), ils resteraient
// absents pour tout le processus : la tentative LÉGITIME suivante échouerait sans
// qu'aucun verrou ne s'y oppose, avec le code générique du cœur. On mesure donc
// l'état réel du crochet, avant et après une tentative venue d'une origine libre.
t_assert(
	false === has_filter( 'authenticate', 'wp_authenticate_username_password' ),
	'constat de départ : l’appel direct à `barrer()` sous verrou a bien désarmé la vérification du cœur (c’est le piège)',
	'désarmé',
	'encore armé'
);
\Massifs\Security\Auth\Ecluse::barrer( null, $cible, 'peu-importe' );
t_egal(
	20,
	has_filter( 'authenticate', 'wp_authenticate_username_password' ),
	'PAS DE FAUX REFUS : une tentative venue d’une origine LIBRE réarme la vérification du cœur à sa priorité 20 — le désarmement ne survit pas à la tentative verrouillée'
);
foreach ( array( 'wp_authenticate_email_password', 'wp_authenticate_application_password' ) as $rappel ) {
	t_egal( 20, has_filter( 'authenticate', $rappel ), 'le rappel `' . $rappel . '` est réarmé lui aussi' );
}

// Neutralise la SEULE temporisation, qui coûte 5 s par échec au-delà du cinquième
// et ne porte aucune décision d'accès (`temporiser()` s'exécute APRÈS que la
// réponse est décidée). Aucun seuil n'est touché.
add_filter( 'massifs_auth_ecluse_temporisation', '__return_zero' );

t_egal( 0, \Massifs\Security\Auth\Ecluse::attente( $cible ), 'l’origine légitime part libre' );

for ( $tentative = 0; $tentative < 4; $tentative++ ) {
	\Massifs\Security\Auth\Ecluse::signaler_echec( $cible );
}
t_egal( 0, \Massifs\Security\Auth\Ecluse::attente( $cible ), 'quatre échecs sur cinq : encore libre, le seuil du couple n’est pas franchi' );

\Massifs\Security\Auth\Ecluse::sur_connexion( $cible );

for ( $tentative = 0; $tentative < 4; $tentative++ ) {
	\Massifs\Security\Auth\Ecluse::signaler_echec( $cible );
}
t_egal(
	0,
	\Massifs\Security\Auth\Ecluse::attente( $cible ),
	'une connexion réussie purge les compteurs : huit échecs cumulés, dont quatre après la purge, ne verrouillent pas — sans purge, le cinquième aurait suffi'
);

remove_filter( 'massifs_auth_ecluse_temporisation', '__return_zero' );

// ---------------------------------------------------------------- 2FA : redirige, jamais ne refuse

$compte_admin = get_userdata( $admin );
t_assert(
	\Massifs\Security\Auth\Deuxfacteurs::est_requis( $compte_admin ),
	'le second facteur est EXIGÉ de l’administrateur (§6)',
	true,
	false
);
t_assert(
	! \Massifs\Security\Auth\Deuxfacteurs::est_requis( get_userdata( $a ) ),
	'le second facteur n’est PAS imposé au gestionnaire — la démonstration publique reste possible (A-9)',
	false,
	true
);
t_assert(
	! \Massifs\Security\Auth\Deuxfacteurs::doit_verifier( $compte_admin ),
	'RAMPE : un administrateur EXIGÉ mais NON ENRÔLÉ n’est jamais soumis à l’étape 2 — sinon il serait enfermé dehors',
	false,
	true
);

// ---------------------------------------------------------------- ménage

foreach ( $auteurs as $login => $id ) {
	// `wp_delete_user()` est bloqué sur un gestionnaire par `map_meta_cap`
	// (interdit 6) : on retire d'abord le rôle, ce qui est le chemin admis.
	$utilisateur = get_userdata( $id );
	if ( $utilisateur instanceof WP_User ) {
		$utilisateur->set_role( '' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $id );
	}
	t_assert( false === get_user_by( 'login', $login ), 'le compte de recette ' . $login . ' est retiré' , 'absent', 'présent' );
}

update_option( 'massifs_journal_comptes', array(), false );

// L'écluse survit à `t_reset()` par construction (option + transients à fenêtre
// fixe de 900 s). Sans cette seconde purge, ce scénario laisserait derrière lui
// un verrou vivant pour un quart d'heure — et se ferait échouer lui-même à la
// relance suivante.
$purger_ecluse();
t_egal( 0, \Massifs\Security\Auth\Ecluse::attente( $cible ), 'MÉNAGE : l’écluse est rendue au repos — le scénario ne laisse aucun verrou derrière lui' );

t_reset();

t_bilan();
