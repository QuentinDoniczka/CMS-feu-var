<?php
/**
 * Gabarit de la page d'accueil — bandes dans l'ordre normatif de MASTER.md §7.1.
 *
 * Tout est rendu par PHP : la page est identique avec et sans JavaScript, et
 * cette issue n'enfile aucun script.
 *
 * Ce gabarit ne calcule aucune règle métier. Le jour, la saison, la péremption,
 * la sévérité et le formatage des dates appartiennent à l'extension.
 *
 * @package Massifs
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde d'existence de l'API — un seul point. Elle porte sur six fonctions
// parce qu'elles proviennent de trois modules de domaine distincts, qui peuvent
// échouer à charger indépendamment les uns des autres.
$massifs_api = function_exists( 'massifs_codes' )
	&& function_exists( 'massifs_jour_courant' )
	&& function_exists( 'massifs_synthese_du_jour' )
	&& function_exists( 'massifs_fraicheur' )
	&& function_exists( 'massifs_horodatage' )
	&& function_exists( 'massifs_attribution_statuts' );

$massifs_peremption = false;

if ( $massifs_api ) {
	// Aucun jour n'est passé : `null` laisse le serveur décider du jour civil
	// de Paris. Le thème ne calcule jamais « aujourd'hui ».
	$massifs_synthese   = massifs_synthese_du_jour( massifs_codes(), null );
	$massifs_fraicheur  = massifs_fraicheur( null );
	$massifs_peremption = true === $massifs_fraicheur['perimee'];

	// Ce tableau existe parce que le bras `indisponible` et le repli du `catch`
	// doivent rendre EXACTEMENT la même chose, à la clé `journal` près. Deux
	// copies divergeraient à la première retouche de la phrase §11.3 ou de la
	// source de l'URL — or la divergence entre deux chemins de rendu d'un même
	// état est précisément le défaut que cette issue referme : dupliquer ici
	// serait refermer une divergence en en ouvrant une autre, de même nature, à
	// trois lignes d'écart.
	//
	// Le hissage n'entame PAS l'interdit d'anti-factorisation du contrat #5 :
	// celui-ci porte sur les quatre lectures chiffrées du bras `disponible`
	// (`par_niveau['autorise']`, `partiel`, `disponibles`, `sans_donnee`), et son
	// motif est de ne laisser aucune phrase chiffrée plausible et fausse en
	// mémoire dans un état sans chiffre. Ce tableau ne lit AUCUNE de ces clés et
	// ne contient AUCUN chiffre : il est l'inverse exact du danger visé, et il
	// rend l'unique chemin de repli provablement dépourvu de chiffre.
	// CRITÈRE DE REVUE : une variable hissée au-dessus du `match()` est un défaut
	// si et seulement si elle peut contenir un nombre, ou une phrase contenant un
	// nombre.
	//
	// massifs_attribution_statuts() ne prend aucun argument, ne lève rien et
	// retourne `carte_officielle_url` sur ses deux chemins de retour ; elle est
	// déjà appelée sans condition sur cette page (templates/footer.php l. 52,
	// parts/bandeau-non-officialite.php l. 41, parts/liste-statuts.php l. 129).
	// Un appel de plus ne coûte rien.
	//
	// Ce tableau doit rester DANS la garde $massifs_api : cette fonction n'existe
	// pas quand elle est fausse. Il n'est jamais réutilisable dans la branche
	// `else`, dont la phrase tient en un seul fragment et n'a pas de lien.
	$massifs_ardoise_absente = array(
		'chiffre'               => '',
		'chiffre_total'         => '',
		'titre_debut'           => 'Information du jour non disponible. Consultez',
		'titre_url'             => massifs_attribution_statuts()['carte_officielle_url'],
		'titre_lien'            => 'la carte officielle de la préfecture',
		'titre_fin'             => '.',
		'publication_partielle' => '',
		'fraicheur'             => false,
		'journal'               => '',
	);

	// `match` à quatre bras et SANS bras `default`, imposé par le contrat #3.
	// L'enveloppe ci-dessous ne rend PAS silencieux l'ajout d'un cinquième état :
	// un bras ajouté est retenu par le `match()`, et le `catch` n'est alors jamais
	// atteint.
	//
	// Pourquoi l'enveloppe existe : ce `match()` s'exécute avant le premier octet
	// de sortie. Sans elle, un `etat_global` hors des quatre bras lève
	// \UnhandledMatchError, que WP_Fatal_Error_Handler convertit en HTTP 500 + la
	// page « Erreur critique sur ce site. » du cœur de WordPress — et non en écran
	// blanc : WORDPRESS_DEBUG vaut 0 et il n'existe pas de wp-content/php-error.php.
	// Le visiteur reçoit alors zéro statut et zéro lien officiel, à rebours du
	// brief §4.2.
	//
	// Deux déclencheurs, pas un seul : un cinquième état ajouté à api.php, OU la
	// clé `etat_global` renommée ou retirée — son accès direct, imposé par le
	// contrat sans isset() ni ??, fait alors valoir l'expression `null`, ce qui
	// lève le même UnhandledMatchError. Le second est le plus probable des deux.
	//
	// Réconciliation, non superstition : les sept `match()` de templates/parts/**
	// portent déjà la même enveloppe (contrat #6, arbitrage E), et elle était
	// inatteignable tant que celui-ci levait avant les inclusions. Deux contrats
	// gelés se contredisaient sur un même chemin de rendu ; ils sont ici mis
	// d'accord.
	//
	// Où passe le bruit : l'échec reste bruyant par le rendu dégradé VISIBLE —
	// l'ardoise cesse d'afficher un chiffre sur la page la plus lue du site — et
	// par une ligne de journal sous WP_DEBUG, jamais par une panne. Le repli est
	// une absence, jamais une donnée, et jamais un chiffre.
	//
	// `non_encore_publie` est inatteignable pour aujourd'hui ; le bras est écrit
	// parce que `match` sans `default` l'exige et parce qu'un sélecteur de date
	// le rendra atteignable.
	try {
		$massifs_ardoise = match ( $massifs_synthese['etat_global'] ) {
			// LE CHIFFRE N'EST ÉCRIT QUE DANS CE BRAS. La règle « jamais un statut
			// périmé présenté comme courant » est ainsi tenue par la structure, pas
			// par la vigilance. Accès direct aux clés du contrat, sans isset() ni ?? :
			// une clé absente doit produire un avertissement PHP visible.
			//
			// La répétition des lectures de clés dans ce bras est délibérée : PHP
			// n'évalue que le bras retenu, donc `partiel`, `disponibles` et
			// `sans_donnee` ne sont même pas lus dans les états indisponible /
			// hors_saison / non_encore_publie. Les hisser au-dessus du `match()`
			// laisserait en mémoire, dans ces états, une phrase chiffrée plausible et
			// fausse, à portée de copier-coller. C'est le prix de la garantie
			// structurelle : NE PAS factoriser ces lectures. L'interdit porte sur CES
			// QUATRE LECTURES CHIFFRÉES, non sur le tableau d'absence hissé au-dessus
			// du `match()`, qui ne contient aucun chiffre : une variable hissée est un
			// défaut si et seulement si elle peut contenir un nombre, ou une phrase
			// contenant un nombre.
			'disponible'            => array(
				'chiffre'               => (string) $massifs_synthese['par_niveau']['autorise'],
				// Le dénominateur reste un ternaire EXPLICITE sur `partiel`. Qu'en
				// journée complète `disponibles` vaille `total` est un fait de code,
				// pas un fait de contrat : toujours écrire `disponibles` masquerait le
				// glissement de sens le jour où le référentiel changerait de taille.
				'chiffre_total'         => '/' . ( true === $massifs_synthese['partiel'] ? $massifs_synthese['disponibles'] : $massifs_synthese['total'] ),
				// Accords par ternaires « > 1 » et NON par _n() : le thème déclare un
				// domaine de texte mais ne charge aucun catalogue, or la règle de repli
				// de WordPress est « n == 1 », qui écrirait « 0 massifs » là où le
				// français écrit « 0 massif ». Zéro et un au singulier, pluriel à partir
				// de deux. Ne pas « corriger » vers _n() : ce serait rouvrir le défaut.
				//
				// Le groupe dénominateur (%3$s) porte son nombre ET son qualificatif :
				// le mot « renseigné » n'existe ainsi que là où il doit exister, et
				// chaque espace du gabarit reste réel dans les deux cas, sans jamais
				// produire de double espace en journée complète.
				'titre_debut'           => sprintf(
					'Aujourd’hui, %1$s %2$s sur %3$s %4$s d’accès autorisé.',
					$massifs_synthese['par_niveau']['autorise'],
					$massifs_synthese['par_niveau']['autorise'] > 1 ? 'massifs' : 'massif',
					true === $massifs_synthese['partiel']
						? sprintf(
							'%1$s %2$s',
							$massifs_synthese['disponibles'],
							$massifs_synthese['disponibles'] > 1 ? 'renseignés' : 'renseigné'
						)
						: $massifs_synthese['total'],
					$massifs_synthese['par_niveau']['autorise'] > 1 ? 'sont' : 'est'
				),
				'titre_url'             => '',
				'titre_lien'            => '',
				'titre_fin'             => '',
				// `sans_donnee` est LU, jamais recomposé en « total - disponibles » :
				// le thème ne calcule jamais un décompte ni un dénominateur.
				// `journal` reste vide — une publication incomplète est un état
				// d'exploitation normal, pas une rupture de contrat.
				'publication_partielle' => true === $massifs_synthese['partiel']
					? sprintf(
						'%1$s %2$s %3$s sans information du jour.',
						$massifs_synthese['sans_donnee'],
						$massifs_synthese['sans_donnee'] > 1 ? 'massifs' : 'massif',
						$massifs_synthese['sans_donnee'] > 1 ? 'restent' : 'reste'
					)
					: '',
				'fraicheur'             => true,
				'journal'               => '',
			),
			// Le mot « INDISPONIBLE » de MASTER.md §8.2 n'est pas rendu : il serait
			// un second bloc --fs-700 adjacent au h1, qui dit déjà exactement cela.
			// Les trois fragments du tableau d'absence hissé plus haut, concaténés, sont
			// la chaîne §11.3 mot pour mot ; ils ne sont séparés que pour porter le lien.
			'indisponible'          => $massifs_ardoise_absente,
			// Phrase §11.3 tronquée à sa première proposition : « Reprise le {date}. »
			// exigerait de formater une date nue, ce que massifs_horodatage() refuse.
			// La proposition est omise et journalisée, jamais inventée (demande B-1).
			'hors_saison'           => array(
				'chiffre'               => '',
				'chiffre_total'         => '',
				'titre_debut'           => 'Dispositif estival inactif.',
				'titre_url'             => '',
				'titre_lien'            => '',
				'titre_fin'             => '',
				'publication_partielle' => '',
				'fraicheur'             => false,
				'journal'               => 'massifs: état hors_saison — proposition « Reprise le {date}. » omise, faute de massifs_horodatage_jour() (demande B-1).',
			),
			'non_encore_publie'     => array(
				'chiffre'               => '',
				'chiffre_total'         => '',
				'titre_debut'           => 'Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.',
				'titre_url'             => '',
				'titre_lien'            => '',
				'titre_fin'             => '',
				'publication_partielle' => '',
				'fraicheur'             => false,
				'journal'               => '',
			),
		};
	} catch ( \UnhandledMatchError $massifs_erreur ) {
		// Repli sur l'absence AVEC le lien : l'URL vient du serveur et le serveur
		// répond ici, puisque $massifs_api est vrai. Le repli SANS lien est réservé
		// à la branche `else`, où l'URL est inobtenable. parts/liste-statuts.php
		// (l. 157-161) et parts/etats-vides.php (l. 86-90) retiennent le même
		// repli : les trois rendus de la page convergent sur le même état et le
		// même lien.
		//
		// Le message de PHP nomme la valeur inattendue — c'est PHP qui la met en
		// forme, le thème n'en compose rien — et il distingue les deux causes : une
		// valeur entre guillemets désigne un cinquième état, `null` désigne une clé
		// disparue du contrat. Deux causes racines, dans deux fichiers différents.
		//
		// Pas de _doing_it_wrong() ici : son trigger_error( E_USER_WARNING ) reste
		// dans error_reporting() hors WP_DEBUG et s'imprimerait dans la page du
		// visiteur si display_errors était actif. Le thème n'expose aucun texte de
		// repli au visiteur.
		$massifs_ardoise            = $massifs_ardoise_absente;
		$massifs_ardoise['journal'] = 'massifs: etat_global hors des quatre états du gabarit — ardoise rendue en état indisponible (' . $massifs_erreur->getMessage() . ').';
	}
} else {
	// API absente : la branche indisponible, SANS le lien — l'adresse vient du
	// serveur, qui est absent. Aucune copie inventée, le h1 unique est conservé,
	// et l'affirmation est vraie : nous n'avons pas l'information.
	$massifs_ardoise = array(
		'chiffre'               => '',
		'chiffre_total'         => '',
		'titre_debut'           => 'Information du jour non disponible. Consultez la carte officielle de la préfecture.',
		'titre_url'             => '',
		'titre_lien'            => '',
		'titre_fin'             => '',
		'publication_partielle' => '',
		'fraicheur'             => false,
		'journal'               => 'massifs: API de lecture de massifs-core absente — ardoise rendue en état indisponible.',
	);
}

// Journal de recette seulement : massifs_journaliser() ignore un message vide
// et n'écrit rien hors WP_DEBUG.
massifs_journaliser( $massifs_ardoise['journal'] );

get_template_part( 'templates/header' );
?>

		<section id="ardoise" class="bande bande--ardoise sur-sombre">
			<div class="bande__contenu ardoise">
				<?php if ( '' !== $massifs_ardoise['chiffre'] ) : ?>
					<p class="ardoise__chiffre repere repere--bloc">
						<span class="ardoise__chiffre-valeur"><?php echo esc_html( $massifs_ardoise['chiffre'] ); ?></span>
						<span class="ardoise__chiffre-total"><?php echo esc_html( $massifs_ardoise['chiffre_total'] ); ?></span>
					</p>
				<?php endif; ?>

				<div class="ardoise__texte">
					<h1 id="titre-du-jour" class="ardoise__titre">
						<?php
						echo esc_html( $massifs_ardoise['titre_debut'] );

						if ( '' !== $massifs_ardoise['titre_url'] ) {
							printf(
								' <a href="%1$s">%2$s</a>%3$s',
								esc_url( $massifs_ardoise['titre_url'] ),
								esc_html( $massifs_ardoise['titre_lien'] ),
								esc_html( $massifs_ardoise['titre_fin'] )
							);
						}
						?>
					</h1>

					<?php
					// La mention du manque est un <p> HORS du h1, jamais la fin du
					// titre : le h1 est en min(var(--fs-700), 3rem), soit jusqu'à
					// 48 px en famille d'affichage condensée. L'y loger ajouterait
					// trois à quatre lignes sur 360 px et repousserait la suite sous
					// la ligne de flottaison — irrattrapable sans CSS, hors empreinte
					// de cette issue. La page conserve un seul h1.
					if ( '' !== $massifs_ardoise['publication_partielle'] ) :
						?>
						<p class="ardoise__publication-partielle"><?php echo esc_html( $massifs_ardoise['publication_partielle'] ); ?></p>
						<?php
					endif;
					?>

					<?php
					if ( $massifs_ardoise['fraicheur'] ) {
						// Gabarit de MASTER.md §11.3. Les variantes s'obtiennent
						// UNIQUEMENT en supprimant la proposition dont la valeur
						// serveur est nulle : aucun mot n'est réécrit.
						$massifs_propositions = array();

						// Passerelle bornée : massifs_horodatage() exige un instant et
						// refuse une date nue, or « Statuts du {jour de validité} »
						// porte un jour civil. `evalue_le` est un instant serveur réel
						// dont la date parisienne est, SOUS CETTE GARDE, le jour de
						// validité lui-même. La garde ne compare que trois valeurs du
						// serveur — aucune arithmétique de date ici.
						// À RETIRER dès que massifs_horodatage_jour() existe (B-1).
						if ( $massifs_fraicheur['jour_validite'] === $massifs_synthese['jour_validite']
							&& $massifs_synthese['jour_validite'] === massifs_jour_courant() ) {

							$massifs_jour_formate = massifs_horodatage( $massifs_fraicheur['evalue_le'] );

							$massifs_propositions['validite'] = sprintf(
								'Statuts du <time datetime="%1$s">%2$s</time>',
								esc_attr( $massifs_synthese['jour_validite'] ),
								esc_html( $massifs_jour_formate['date_longue'] )
							);
						}

						if ( null !== $massifs_fraicheur['publie_prefecture_le'] ) {
							$massifs_publication = massifs_horodatage( $massifs_fraicheur['publie_prefecture_le'] );

							$massifs_propositions['publication'] = sprintf(
								'publiés la veille à %s par la préfecture',
								esc_html( $massifs_publication['heure'] )
							);
						}

						if ( null !== $massifs_fraicheur['dernier_releve_le'] ) {
							$massifs_releve = massifs_horodatage( $massifs_fraicheur['dernier_releve_le'] );

							$massifs_propositions['releve'] = sprintf(
								'relevés sur ce site le <time datetime="%1$s">%2$s</time> à %3$s',
								esc_attr( $massifs_releve['attr_datetime'] ),
								esc_html( $massifs_releve['date_longue'] ),
								esc_html( $massifs_releve['heure'] )
							);
						}

						if ( array_key_exists( 'validite', $massifs_propositions ) ) {
							$massifs_phrase = $massifs_propositions['validite'];

							if ( array_key_exists( 'publication', $massifs_propositions ) ) {
								$massifs_phrase .= ', ' . $massifs_propositions['publication'];
							}

							if ( array_key_exists( 'releve', $massifs_propositions ) ) {
								$massifs_phrase .= ' — ' . $massifs_propositions['releve'];
							}

							// Chaque valeur du serveur a été échappée à la construction
							// ci-dessus ; seuls les <time> et la ponctuation du gabarit
							// sont du balisage écrit ici.
							echo '<p class="ardoise__fraicheur">' . $massifs_phrase . '.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							massifs_journaliser( 'massifs: ligne de fraîcheur omise — la date de validité ne peut pas être mise en forme sans massifs_horodatage_jour() (demande B-1).' );
						}
					}

					// La péremption AJOUTE une phrase. Elle ne masque, ne remplace et
					// ne conditionne rien : le chiffre, le titre et la ligne de
					// fraîcheur restent identiques.
					if ( $massifs_peremption ) :
						?>
						<p class="ardoise__peremption">Donnée périmée.</p>
						<?php
					endif;
					?>
				</div>
			</div>
		</section>

		<section id="non-officialite" class="bande bande--non-officialite">
			<div class="bande__contenu">
				<?php massifs_partie( 'bandeau-non-officialite' ); ?>
			</div>
		</section>

		<?php
		// Emplacement de la carte (MASTER.md §7.1). La bande n'émet PAS de
		// .bande__contenu : c'est ainsi qu'elle touche les deux bords de la
		// fenêtre à toutes les tailles, sans marge négative.
		// Sans nom accessible (ni h2, ni aria-labelledby, ni aria-label), elle est
		// exposée comme « generic » et ne crée donc aucun landmark vide.
		// La carte elle-même, sa hauteur et son repli statique appartiennent à la
		// chaîne « carte ».
		?>
		<section id="carte" class="bande bande--carte"></section>

		<?php
		// Arbitrage A-23 : les deux bandes ci-dessous n'ont DÉLIBÉRÉMENT ni `id`,
		// ni nom accessible, ni `tabindex`, ni titre. Les parties de la chaîne #6
		// sont auto-portantes : elles émettent elles-mêmes leur <section id>, leur
		// titre officiel et, pour la liste, le `tabindex="-1"` visé par le second
		// lien d'évitement. Les rétablir ici recréerait les doublons d'`id` et de
		// `h2` qui rendaient la page invalide. Ces enveloppes ne servent plus qu'à
		// la mise en page.
		//
		// Aucun `$args` n'est passé : les valeurs par défaut des parties produisent
		// déjà `id="legende"` et `id="liste"`, exactement les ancres attendues.
		?>
		<div class="bande bande--legende">
			<div class="bande__contenu">
				<?php massifs_partie( 'legende' ); ?>
			</div>
		</div>

		<div class="bande bande--liste">
			<div class="bande__contenu">
				<?php massifs_partie( 'liste-statuts' ); ?>
			</div>
		</div>

		<?php
		// Bande « Danger météo du jour » (MASTER.md §8.6) — non émise : elle
		// appartient à la chaîne « meteo ». Une <section> portant un h2 et rien
		// dedans est un landmark vide, donc un défaut d'accessibilité, pas un
		// emplacement réservé. Elle s'insérera ici, entre #liste et le pied.
		//
		// Bande « Zones parcourues par le feu » (MASTER.md §7.1) — non émise :
		// elle appartient à la chaîne « effis », même raison, même place.
		?>

<?php
get_template_part( 'templates/footer' );
