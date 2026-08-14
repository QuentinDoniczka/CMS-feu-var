<?php
/**
 * Rendu de l'écran d'historique — SEUL fichier de ce module qui échappe.
 *
 * La vue NE COMPOSE AUCUNE CHAÎNE : ni libellé de niveau, ni phrase de
 * transition, ni libellé de source, ni date. Tout arrive déjà rédigé de
 * `vocabulaire.php` et de `donnees.php` ; ici on échappe et on pose.
 *
 * AUCUN JAVASCRIPT. Les filtres sont un `<form method="get">`, la pagination des
 * ancres, et l'export un second bouton de soumission qui change de destination
 * par l'attribut `formaction` — du HTML pur.
 *
 * L'ÉCRAN N'ÉCRIT RIEN et n'affiche AUCUNE bannière de fraîcheur : un journal
 * est un relevé d'écritures passées, il ne prétend jamais dire l'état du jour.
 *
 * Les classes employées sont exactement celles du contrat de balisage : la
 * feuille de style est écrite en aveugle par une autre chaîne, et une classe de
 * plus serait une classe jamais stylée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'massifs_historique_rendre_ecran' ) ) {
	/**
	 * Rend l'écran d'historique.
	 *
	 * PREMIÈRE DES TROIS PORTES DE CAPACITÉ, indépendante de celle du menu :
	 * `add_submenu_page()` ne gouverne que l'affichage de l'entrée, jamais
	 * l'accès à la page.
	 */
	function massifs_historique_rendre_ecran(): void {
		if ( ! current_user_can( MASSIFS_HISTORIQUE_CAPACITE ) ) {
			wp_die(
				esc_html( massifs_historique_mot( 'acces_refuse' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Formulaire de filtrage en GET, en lecture stricte : rien n'est écrit, il n'y a donc aucune intention à confirmer, et le nonce du formulaire n'existe que pour l'export, qui le vérifie. L'assainissement de chaque champ appartient à l'analyseur unique, qui reçoit le tableau brut débarrassé de ses antislashs.
		$filtres = massifs_historique_filtres_depuis_requete( (array) wp_unslash( $_GET ) );
		$donnees = massifs_historique_donnees( $filtres );

		?>
		<div class="wrap massifs-historique">
			<h1 id="massifs-historique-titre"><?php echo esc_html( massifs_historique_mot( 'titre' ) ); ?></h1>

			<?php
			massifs_historique_rendre_avertissements( $filtres );
			massifs_historique_rendre_filtres( $filtres );

			if ( 'disponible' === $donnees['etat'] ) {
				massifs_historique_rendre_resume( $donnees );
				massifs_historique_rendre_tableau( $donnees );
				massifs_historique_rendre_pagination( $filtres, $donnees );
			} else {
				massifs_historique_rendre_etat_vide( $filtres, (string) $donnees['etat'] );
			}
			?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'massifs_historique_rendre_avertissements' ) ) {
	/**
	 * Rend un avertissement par filtre rejeté.
	 *
	 * Chaque message porte un identifiant stable, cible de l'`aria-describedby`
	 * du champ fautif : sans lui, un utilisateur de lecteur d'écran entendrait
	 * l'avertissement sans savoir quel champ le déclenche.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 */
	function massifs_historique_rendre_avertissements( array $filtres ): void {
		$rejets = isset( $filtres['rejets'] ) && is_array( $filtres['rejets'] ) ? $filtres['rejets'] : array();

		foreach ( $rejets as $rejet ) {
			$message = massifs_historique_mot( 'rejet_' . $rejet );

			if ( '' === $message ) {
				continue;
			}

			printf(
				'<p class="massifs-historique-avertissement" id="%1$s">%2$s</p>',
				esc_attr( massifs_historique_id_avertissement( (string) $rejet ) ),
				esc_html( $message )
			);
		}
	}
}

if ( ! function_exists( 'massifs_historique_id_avertissement' ) ) {
	/**
	 * Identifiant HTML du message d'un rejet.
	 *
	 * @param string $rejet Clé de rejet.
	 */
	function massifs_historique_id_avertissement( string $rejet ): string {
		return 'massifs-historique-avertissement-' . str_replace( '_', '-', $rejet );
	}
}

if ( ! function_exists( 'massifs_historique_decrit_par' ) ) {
	/**
	 * Valeur d'`aria-describedby` d'un champ : son aide, puis son avertissement.
	 *
	 * @param string       $aide   Identifiant de l'aide, chaîne vide si aucune.
	 * @param list<string> $rejets Rejets actifs.
	 * @param list<string> $cles   Rejets qui concernent ce champ.
	 */
	function massifs_historique_decrit_par( string $aide, array $rejets, array $cles ): string {
		$identifiants = '' === $aide ? array() : array( $aide );

		foreach ( $cles as $cle ) {
			if ( in_array( $cle, $rejets, true ) ) {
				$identifiants[] = massifs_historique_id_avertissement( $cle );
			}
		}

		return implode( ' ', $identifiants );
	}
}

if ( ! function_exists( 'massifs_historique_rendre_champ_jour' ) ) {
	/**
	 * Rend un champ de jour, son étiquette, son aide et son état d'erreur.
	 *
	 * `<label for>` explicite et jamais un attribut `placeholder` en guise
	 * d'étiquette : un `placeholder` disparaît à la saisie et n'est pas une
	 * étiquette.
	 *
	 * @param string               $nom         Nom du paramètre.
	 * @param string               $etiquette   Étiquette visible.
	 * @param string               $valeur      Valeur saisie, conservée même rejetée.
	 * @param string               $aide        Identifiant du texte d'aide.
	 * @param list<string>         $rejets      Rejets actifs.
	 * @param list<string>         $cles_rejet  Rejets qui concernent ce champ.
	 */
	function massifs_historique_rendre_champ_jour(
		string $nom,
		string $etiquette,
		string $valeur,
		string $aide,
		array $rejets,
		array $cles_rejet
	): void {
		$identifiant = 'massifs-historique-' . str_replace( '_', '-', $nom );
		$decrit_par  = massifs_historique_decrit_par( $aide, $rejets, $cles_rejet );
		$invalide    = in_array( $nom, $rejets, true );

		?>
		<div class="massifs-historique-filtres__champ">
			<label for="<?php echo esc_attr( $identifiant ); ?>"><?php echo esc_html( $etiquette ); ?></label>
			<input
				type="text"
				inputmode="numeric"
				maxlength="10"
				pattern="\d{4}-\d{2}-\d{2}"
				id="<?php echo esc_attr( $identifiant ); ?>"
				name="<?php echo esc_attr( $nom ); ?>"
				value="<?php echo esc_attr( $valeur ); ?>"
				<?php echo '' === $decrit_par ? '' : 'aria-describedby="' . esc_attr( $decrit_par ) . '"'; ?>
				<?php echo $invalide ? 'aria-invalid="true"' : ''; ?>
			>
		</div>
		<?php
	}
}

if ( ! function_exists( 'massifs_historique_rendre_filtres' ) ) {
	/**
	 * Rend le formulaire de filtrage.
	 *
	 * TROIS `<fieldset>`, dont DEUX pour les dates : le jour de validité et
	 * l'instant d'enregistrement sont deux dates différentes — une correction
	 * faite le 15 août porte sur le statut du 12 — et la légende du groupe est
	 * le seul moyen, sans JavaScript, qu'un lecteur d'écran annonce laquelle des
	 * deux est saisie.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 */
	function massifs_historique_rendre_filtres( array $filtres ): void {
		$rejets = isset( $filtres['rejets'] ) && is_array( $filtres['rejets'] ) ? $filtres['rejets'] : array();

		?>
		<form class="massifs-historique-filtres" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<?php foreach ( massifs_historique_champs_caches( $filtres ) as $nom => $valeur ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $nom ); ?>" value="<?php echo esc_attr( $valeur ); ?>">
			<?php endforeach; ?>

			<fieldset class="massifs-historique-filtres__groupe">
				<legend><?php echo esc_html( massifs_historique_mot( 'groupe_identite' ) ); ?></legend>

				<div class="massifs-historique-filtres__champ">
					<label for="massifs-historique-massif"><?php echo esc_html( massifs_historique_mot( 'champ_massif' ) ); ?></label>
					<select
						id="massifs-historique-massif"
						name="massif"
						<?php echo in_array( 'massif', $rejets, true ) ? 'aria-invalid="true" aria-describedby="' . esc_attr( massifs_historique_id_avertissement( 'massif' ) ) . '"' : ''; ?>
					>
						<option value=""><?php echo esc_html( massifs_historique_mot( 'option_tous_massifs' ) ); ?></option>
						<?php foreach ( massifs_historique_options_massifs() as $code => $libelle ) : ?>
							<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( (string) $code, (string) $filtres['massif_code'] ); ?>>
								<?php echo esc_html( (string) $libelle ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="massifs-historique-filtres__champ">
					<label for="massifs-historique-source"><?php echo esc_html( massifs_historique_mot( 'champ_source' ) ); ?></label>
					<select
						id="massifs-historique-source"
						name="source"
						<?php echo in_array( 'source', $rejets, true ) ? 'aria-invalid="true" aria-describedby="' . esc_attr( massifs_historique_id_avertissement( 'source' ) ) . '"' : ''; ?>
					>
						<option value=""><?php echo esc_html( massifs_historique_mot( 'option_toutes_sources' ) ); ?></option>
						<?php foreach ( massifs_historique_options_sources() as $valeur => $libelle ) : ?>
							<option value="<?php echo esc_attr( (string) $valeur ); ?>" <?php selected( (string) $valeur, (string) $filtres['source'] ); ?>>
								<?php echo esc_html( (string) $libelle ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="massifs-historique-filtres__champ">
					<label for="massifs-historique-auteur"><?php echo esc_html( massifs_historique_mot( 'champ_auteur' ) ); ?></label>
					<select
						id="massifs-historique-auteur"
						name="auteur"
						<?php echo in_array( 'auteur', $rejets, true ) ? 'aria-invalid="true" aria-describedby="' . esc_attr( massifs_historique_id_avertissement( 'auteur' ) ) . '"' : ''; ?>
					>
						<option value="0"><?php echo esc_html( massifs_historique_mot( 'option_tous_auteurs' ) ); ?></option>
						<?php foreach ( massifs_historique_options_auteurs() as $auteur_id => $nom ) : ?>
							<option value="<?php echo esc_attr( (string) $auteur_id ); ?>" <?php selected( (int) $auteur_id, (int) $filtres['auteur_id'] ); ?>>
								<?php echo esc_html( (string) $nom ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</fieldset>

			<fieldset class="massifs-historique-filtres__groupe">
				<legend><?php echo esc_html( massifs_historique_mot( 'groupe_jour' ) ); ?></legend>
				<p class="massifs-historique-filtres__aide" id="massifs-historique-aide-jour">
					<?php echo esc_html( massifs_historique_mot( 'aide_format_jour' ) ); ?>
				</p>
				<?php
				massifs_historique_rendre_champ_jour(
					'jour_debut',
					massifs_historique_mot( 'champ_debut' ),
					(string) $filtres['jour_debut'],
					'massifs-historique-aide-jour',
					$rejets,
					array( 'jour_debut', 'jour_intervalle' )
				);
				massifs_historique_rendre_champ_jour(
					'jour_fin',
					massifs_historique_mot( 'champ_fin' ),
					(string) $filtres['jour_fin'],
					'massifs-historique-aide-jour',
					$rejets,
					array( 'jour_fin', 'jour_intervalle' )
				);
				?>
			</fieldset>

			<fieldset class="massifs-historique-filtres__groupe">
				<legend><?php echo esc_html( massifs_historique_mot( 'groupe_enregistre' ) ); ?></legend>
				<p class="massifs-historique-filtres__aide" id="massifs-historique-aide-enregistre">
					<?php echo esc_html( massifs_historique_mot( 'aide_format_jour' ) ); ?>
				</p>
				<?php
				massifs_historique_rendre_champ_jour(
					'enregistre_debut',
					massifs_historique_mot( 'champ_debut' ),
					(string) $filtres['enregistre_debut'],
					'massifs-historique-aide-enregistre',
					$rejets,
					array( 'enregistre_debut', 'enregistre_intervalle' )
				);
				massifs_historique_rendre_champ_jour(
					'enregistre_fin',
					massifs_historique_mot( 'champ_fin' ),
					(string) $filtres['enregistre_fin'],
					'massifs-historique-aide-enregistre',
					$rejets,
					array( 'enregistre_fin', 'enregistre_intervalle' )
				);
				?>
			</fieldset>

			<div class="massifs-historique-filtres__actions">
				<button type="submit" class="massifs-bouton massifs-bouton--primaire">
					<?php echo esc_html( massifs_historique_mot( 'action_filtrer' ) ); ?>
				</button>
				<?php // L'export change de destination par `formaction` : aucun JavaScript, et le formulaire lui transmet les filtres saisis. ?>
				<button
					type="submit"
					class="massifs-bouton massifs-bouton--secondaire"
					name="action"
					value="<?php echo esc_attr( MASSIFS_HISTORIQUE_ACTION_EXPORT ); ?>"
					formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				>
					<?php echo esc_html( massifs_historique_mot( 'action_exporter' ) ); ?>
				</button>
				<?php if ( true === ( $filtres['actifs'] ?? false ) ) : ?>
					<a
						class="massifs-historique-filtres__reinitialiser"
						href="<?php echo esc_url( massifs_historique_url( massifs_historique_filtres_vides() ) ); ?>"
					><?php echo esc_html( massifs_historique_mot( 'action_reinitialiser' ) ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}
}

if ( ! function_exists( 'massifs_historique_rendre_resume' ) ) {
	/**
	 * Rend le compte de résultats.
	 *
	 * `role="status"` : le compte change après chaque filtrage, et un lecteur
	 * d'écran doit l'entendre sans avoir à repartir en exploration.
	 *
	 * @param array<string, mixed> $donnees Page de journal.
	 */
	function massifs_historique_rendre_resume( array $donnees ): void {
		printf(
			'<p class="massifs-historique-resume" role="status">%s</p>',
			esc_html(
				massifs_historique_resume(
					(int) $donnees['total'],
					(int) $donnees['page'],
					(int) $donnees['pages']
				)
			)
		);
	}
}

if ( ! function_exists( 'massifs_historique_rendre_etat_vide' ) ) {
	/**
	 * Rend un état sans tableau.
	 *
	 * JAMAIS un tableau vide : « le journal est en panne », « le journal n'a rien
	 * reçu » et « vos filtres ne ramènent rien » sont trois faits différents, et
	 * un tableau vide les confondrait tous les trois en « il ne s'est rien
	 * passé ».
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 * @param string               $etat    État émis par l'adaptateur.
	 */
	function massifs_historique_rendre_etat_vide( array $filtres, string $etat ): void {
		printf(
			'<p class="massifs-historique-vide">%s</p>',
			esc_html( massifs_historique_mot( 'etat_' . $etat ) )
		);

		if ( 'aucun_resultat' !== $etat || true !== ( $filtres['actifs'] ?? false ) ) {
			return;
		}

		printf(
			'<p class="massifs-historique-vide"><a class="massifs-historique-filtres__reinitialiser" href="%1$s">%2$s</a></p>',
			esc_url( massifs_historique_url( massifs_historique_filtres_vides() ) ),
			esc_html( massifs_historique_mot( 'action_reinitialiser' ) )
		);
	}
}

if ( ! function_exists( 'massifs_historique_rendre_valeur' ) ) {
	/**
	 * Rend une valeur de niveau : pastille muette d'un côté, libellé de l'autre.
	 *
	 * LA PASTILLE EST TOUJOURS VIDE et `aria-hidden` : aucun texte n'est posé sur
	 * un aplat de statut, et le libellé officiel est un frère, jamais un enfant.
	 * `data-motif` est toujours présent — l'information ne repose jamais sur la
	 * couleur seule.
	 *
	 * @param array<string, mixed> $valeur    Valeur préparée par l'adaptateur.
	 * @param string               $variante  `ancienne` ou `nouvelle`.
	 */
	function massifs_historique_rendre_valeur( array $valeur, string $variante ): void {
		if ( true === $valeur['inconnu'] ) {
			printf(
				'<span class="massifs-historique-niveau-inconnu">%1$s <code>%2$s</code></span>',
				esc_html( (string) $valeur['libelle'] ),
				esc_html( (string) $valeur['cle'] )
			);

			return;
		}

		if ( true !== $valeur['pastille'] ) {
			printf(
				'<span class="massifs-historique-sans-niveau">%s</span>',
				esc_html( (string) $valeur['libelle'] )
			);

			return;
		}

		$classes = 'massifs-pastille massifs-pastille--' . ( 'ancienne' === $variante ? 'ancienne' : 'nouvelle' );

		if ( true === $valeur['zapef'] ) {
			$classes .= ' massifs-pastille--zapef';
		}

		printf(
			'<span class="%1$s" data-niveau="%2$s" data-motif="%3$s" aria-hidden="true"></span><span class="massifs-pastille-libelle">%4$s</span>',
			esc_attr( $classes ),
			esc_attr( (string) $valeur['niveau'] ),
			esc_attr( (string) $valeur['motif'] ),
			esc_html( (string) $valeur['libelle'] )
		);
	}
}

if ( ! function_exists( 'massifs_historique_rendre_fleche' ) ) {
	/**
	 * Rend la flèche « ancienne → nouvelle », en SVG EN LIGNE.
	 *
	 * JAMAIS LE CARACTÈRE `→` (U+2192) : il est hors du sous-ensemble `latin` et
	 * absent des deux polices du projet (MASTER §5 et D-25). Écrit en caractère,
	 * il afficherait un rectangle vide, ou irait chercher une police système,
	 * donc hors du design system. Le §16 en fait un défaut bloquant.
	 *
	 * En ligne et jamais un fichier : aucune requête supplémentaire.
	 * `fill="currentColor"` : la flèche hérite de la couleur du texte — aucune
	 * valeur littérale, et surtout AUCUNE teinte de diff, que MASTER §7.2
	 * interdit nommément.
	 *
	 * Le sens est porté par le « remplacé par », POSÉ HORS DU `<svg>` : le dessin
	 * n'est que sa traduction visuelle.
	 */
	function massifs_historique_rendre_fleche(): void {
		?>
		<span class="massifs-historique-transition__fleche">
			<svg viewBox="0 0 16 8" width="16" height="8" aria-hidden="true" focusable="false">
				<path d="M0 3.25h10.5V0.5L16 4l-5.5 3.5V4.75H0z" fill="currentColor" />
			</svg>
			<span class="screen-reader-text"><?php echo esc_html( massifs_historique_mot( 'transition_remplace' ) ); ?></span>
		</span>
		<?php
	}
}

if ( ! function_exists( 'massifs_historique_rendre_transition' ) ) {
	/**
	 * Rend la transition « ancienne → nouvelle » d'une dimension.
	 *
	 * La flèche est un SVG EN LIGNE (voir `massifs_historique_rendre_fleche()`),
	 * jamais un fichier et jamais une couleur de diff, et elle est doublée d'un
	 * « remplacé par » réservé aux lecteurs d'écran : une flèche seule n'est pas
	 * une information accessible.
	 *
	 * @param array<string, mixed> $transition Transition préparée par l'adaptateur.
	 */
	function massifs_historique_rendre_transition( array $transition ): void {
		$classes = 'massifs-historique-transition';

		if ( true === $transition['premiere'] ) {
			$classes .= ' massifs-historique-transition--premiere';
		}

		echo '<span class="' . esc_attr( $classes ) . '">';

		if ( true !== $transition['premiere'] && is_array( $transition['ancienne'] ) ) {
			massifs_historique_rendre_valeur( $transition['ancienne'], 'ancienne' );
			massifs_historique_rendre_fleche();
		}

		massifs_historique_rendre_valeur( $transition['nouvelle'], 'nouvelle' );

		if ( '' !== (string) $transition['mention'] ) {
			printf(
				'<span class="massifs-historique-transition__mention">%s</span>',
				esc_html( (string) $transition['mention'] )
			);
		}

		echo '</span>';
	}
}

if ( ! function_exists( 'massifs_historique_rendre_tableau' ) ) {
	/**
	 * Rend le tableau des écritures.
	 *
	 * La zone défilante est un `role="region"` focusable : sans elle, un
	 * utilisateur au clavier ne pourrait pas atteindre les colonnes de droite sur
	 * un écran étroit.
	 *
	 * @param array<string, mixed> $donnees Page de journal.
	 */
	function massifs_historique_rendre_tableau( array $donnees ): void {
		?>
		<div
			class="massifs-historique-defilant"
			role="region"
			tabindex="0"
			aria-labelledby="massifs-historique-titre"
		>
			<table class="massifs-historique-table">
				<caption class="massifs-historique-table__legende">
					<?php echo esc_html( massifs_historique_mot( 'legende_tableau' ) ); ?>
					<span><?php echo esc_html( massifs_historique_mot( 'zapef_note' ) ); ?></span>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_massif' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_jour' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_niveau' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_zapef' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_source' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_auteur' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_enregistre' ) ); ?></th>
						<th scope="col"><?php echo esc_html( massifs_historique_mot( 'colonne_reference' ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $donnees['entrees'] as $entree ) : ?>
						<?php
						$presentation = $entree['presentation'];

						// Modificateurs DÉCORATIFS : aucune information n'y est
						// portée, le changement est dit en toutes lettres dans la
						// cellule de niveau.
						$classes = 'massifs-historique-ligne massifs-historique-ligne--'
							. (string) $presentation['modificateur'];
						?>
						<tr class="<?php echo esc_attr( $classes ); ?>">
							<th scope="row" class="massifs-historique-cellule--massif">
								<?php echo esc_html( (string) $presentation['massif'] ); ?>
							</th>
							<td class="massifs-historique-cellule--jour">
								<?php echo esc_html( (string) $presentation['jour_validite'] ); ?>
							</td>
							<td class="massifs-historique-cellule--niveau">
								<?php massifs_historique_rendre_transition( $presentation['niveau'] ); ?>
							</td>
							<td class="massifs-historique-cellule--zapef">
								<?php massifs_historique_rendre_transition( $presentation['zapef'] ); ?>
							</td>
							<td class="massifs-historique-cellule--source">
								<?php echo esc_html( (string) $presentation['source'] ); ?>
							</td>
							<td class="massifs-historique-cellule--auteur">
								<?php echo esc_html( (string) $presentation['auteur'] ); ?>
							</td>
							<td class="massifs-historique-cellule--enregistre">
								<time datetime="<?php echo esc_attr( (string) $presentation['enregistre_attribut'] ); ?>">
									<?php echo esc_html( (string) $presentation['enregistre_le'] ); ?>
								</time>
							</td>
							<td class="massifs-historique-cellule--reference">
								<?php echo esc_html( (string) $presentation['reference'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

if ( ! function_exists( 'massifs_historique_rendre_pagination' ) ) {
	/**
	 * Rend la pagination.
	 *
	 * @param array<string, mixed> $filtres Filtres issus de l'analyseur.
	 * @param array<string, mixed> $donnees Page de journal.
	 */
	function massifs_historique_rendre_pagination( array $filtres, array $donnees ): void {
		$elements = massifs_historique_pagination( $filtres, (int) $donnees['page'], (int) $donnees['pages'] );

		if ( array() === $elements ) {
			return;
		}

		?>
		<nav class="massifs-historique-pagination" aria-label="<?php echo esc_attr( massifs_historique_mot( 'pagination_titre' ) ); ?>">
			<?php foreach ( $elements as $element ) : ?>
				<?php if ( true === $element['courant'] ) : ?>
					<span
						class="massifs-historique-pagination__lien massifs-historique-pagination__lien--courant"
						aria-current="page"
					><?php echo esc_html( (string) $element['libelle'] ); ?></span>
				<?php else : ?>
					<a
						class="massifs-historique-pagination__lien"
						href="<?php echo esc_url( (string) $element['url'] ); ?>"
						aria-label="<?php echo esc_attr( (string) $element['titre'] ); ?>"
					><?php echo esc_html( (string) $element['libelle'] ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}
