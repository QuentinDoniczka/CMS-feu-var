<?php
/**
 * Partie de gabarit — équivalent textuel des zones parcourues par le feu
 * (§4.4 et §5.3 du brief, MASTER.md §7.1 et §11.3).
 *
 * CE QUE CETTE PARTIE EST. Le rendu SERVEUR, dans le HTML produit par PHP, de
 * la bande « Zones parcourues par le feu » : le titre, la liste des zones
 * relevées, la fraîcheur du relevé, la phrase de limites et l'attribution.
 * Elle n'enfile aucun script et ne dépend d'aucun ; sans JavaScript elle est
 * identique, et c'est exactement ce qui lui fait servir le §5.3 du brief.
 *
 * CE QUE CETTE PARTIE N'EST PAS. Elle n'est PAS le panneau de sélection de la
 * carte Leaflet. Elle ne réagit à aucun clic, ne connaît aucune instance de
 * carte, ne lit aucune géométrie et ne projette rien. Dans ce projet, « panneau »
 * a un référent unique et déjà écrit — le panneau massif de MASTER.md §8.4 et
 * ses classes `.carte__panneau*` — qui est précisément ce que cette chaîne a
 * décidé de ne pas faire. Le nom de ce fichier vient de l'empreinte de
 * l'issue #11, pas de sa fonction ; son bloc BEM est `.zones-parcourues`, nom
 * imposé par le vocabulaire fixe de MASTER.md §11.2 (contrat #11, A-9).
 *
 * LA COUCHE CARTOGRAPHIQUE N'EST PAS LIVRÉE. Elle est escaladée au §9 du
 * contrat #11, qui en nomme les sept coutures manquantes, fichier et ligne à
 * l'appui, et les trois questions de conception qu'aucun dev ne tranche seul.
 * Rien ici ne la prépare, ne la simule ni ne la remplace.
 *
 * Aucune fonction de statut, de synthèse, de fraîcheur, de jour courant ni de
 * saison n'est appelée (contrat #11, invariant I-11.5) : la partie est
 * structurellement incapable de présenter un statut périmé comme courant, et la
 * couche est servie toute l'année, sans dépendance au dispositif estival.
 *
 * Convention d'appel :
 *   get_template_part( 'templates/parts/panneau-feu', null, $args );
 *
 * Clés de $args reconnues — toute clé absente, vide ou de mauvais type vaut
 * absente, toute clé non listée est ignorée :
 *
 *   ancre             string  Défaut : `zones-parcourues`. sanitize_key(),
 *                             préfixe de TOUS les `id` de la partie. Une
 *                             seconde inclusion sur la même page DOIT recevoir
 *                             une ancre distincte : la partie ne peut pas le
 *                             détecter, c'est une obligation de l'appelant.
 *   niveau_titre      int     Défaut : 2, retenu dans 2..6. Jamais 1 : le `h1`
 *                             appartient à l'appelant.
 *   zones_parcourues  array   Défaut : massifs_zones_parcourues_par_le_feu().
 *                             Absente ET fonction absente ⇒ zéro octet.
 *   attribution       array   Défaut :
 *                             massifs_attribution_zones_parcourues_par_le_feu().
 *                             Fournit `phrase`. Absente, ou `phrase` vide après
 *                             trim() ⇒ zéro octet, LISTE COMPRISE (I-11.6).
 *
 * Les deux tableaux fournis par l'extension sont TOTAUX (contrat #11 §1.1,
 * §1.1.a et §1.2) : toutes leurs clés sont toujours présentes. Aucune garde
 * d'existence par clé n'est donc écrite — les `isset()` ci-dessous ne portent
 * que sur `$args`, dont les clés sont facultatives par convention d'appel. Un
 * appelant qui passerait un tableau amputé rompt le contrat, et l'échec doit
 * rester bruyant.
 *
 * `perimee` n'existe pas pour cette couche et « Donnée périmée. » n'y est jamais
 * rendue (contrat #11, A-4) : pour un statut la péremption ANNOTE, ici elle
 * RETIRE — une fenêtre glissante périmée ferait lire « aucune zone » là où un
 * feu survenu depuis serait absent. Au-delà de la péremption, l'extension rend
 * `couche_effis_indisponible`, et il n'existe aucun état intermédiaire.
 *
 * Gabarit pur, sans aucune déclaration : `load_template()` fait un `require` et
 * non un `require_once`, une partie incluse deux fois est donc ré-exécutée.
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arguments = isset( $args ) && is_array( $args ) ? $args : array();

// Garde d'attribution, évaluée AVANT toute autre (contrat #11, invariant
// I-11.6, transposé d'I-9.4) : afficher une donnée EFFIS sans son attribution
// manque au §9 du brief, et créditer une source dont aucune donnée n'est
// affichée est une affirmation fausse. La donnée et sa mention n'existent que
// l'une avec l'autre ; l'ordre des gardes est ce qui le garantit.
$attribution = isset( $arguments['attribution'] ) && is_array( $arguments['attribution'] )
	? $arguments['attribution']
	: array();

if ( array() === $attribution && function_exists( 'massifs_attribution_zones_parcourues_par_le_feu' ) ) {
	$attribution = massifs_attribution_zones_parcourues_par_le_feu();
}

if ( array() === $attribution ) {
	return;
}

// Rendue ENTIÈRE et en TEXTE NU. Il n'existe aucune clé `lien_licence` : le §9
// du brief impose la phrase EFFIS sans URL, contrairement à celle d'OSM
// (contrat #11 §1.2). Jamais abrégée, jamais reformulée, jamais découpée en
// « Copernicus / EFFIS », jamais enveloppée dans un `<a>` — il n'y a aucune
// destination à décrire.
$phrase_attribution = is_string( $attribution['phrase'] ) ? trim( $attribution['phrase'] ) : '';

if ( '' === $phrase_attribution ) {
	return;
}

$couche = isset( $arguments['zones_parcourues'] ) && is_array( $arguments['zones_parcourues'] )
	? $arguments['zones_parcourues']
	: array();

if ( array() === $couche && function_exists( 'massifs_zones_parcourues_par_le_feu' ) ) {
	$couche = massifs_zones_parcourues_par_le_feu();
}

// Sans couche, pas de titre orphelin : la partie disparaît entièrement.
if ( array() === $couche ) {
	return;
}

$ancre = isset( $arguments['ancre'] ) && is_string( $arguments['ancre'] ) ? sanitize_key( $arguments['ancre'] ) : '';

if ( '' === $ancre ) {
	$ancre = 'zones-parcourues';
}

$niveau_titre = isset( $arguments['niveau_titre'] ) && is_int( $arguments['niveau_titre'] )
	&& in_array( $arguments['niveau_titre'], array( 2, 3, 4, 5, 6 ), true )
	? $arguments['niveau_titre']
	: 2;

$balise_titre = 'h' . (string) $niveau_titre;

// LA RÈGLE CENTRALE DE L'ISSUE (contrat #11 §3.1) : le test discriminant est
// `etat`, JAMAIS `nombre`, JAMAIS count( $zones ). `aucune_zone` et
// `couche_effis_indisponible` portent tous deux zéro zone ; ce qui les sépare
// est le relevé qui les fonde. Décider sur un décompte écrirait « aucune zone
// parcourue par le feu » là où la vérité est « nous ne savons pas » — un faux
// négatif sur une donnée de sécurité.
//
// match() SANS default : l'ajout d'un quatrième état par l'extension doit rester
// bruyant. Le repli est `couche_effis_indisponible`, JAMAIS `aucune_zone` : un
// repli est une absence déclarée, jamais un faux négatif (interdit 16).
$etat = $couche['etat'];

// Écrite une seule fois : les trois chemins qui mènent à l'indisponibilité —
// l'état servi, l'état inconnu, et le repli A-15 — doivent rendre exactement les
// mêmes octets. Trois littéraux auraient fini par diverger.
$phrase_indisponible = 'Donnée momentanément indisponible.';

try {
	$message = match ( $etat ) {
		'zones_disponibles'         => '',
		'aucune_zone'               => 'Aucune zone parcourue par le feu détectée.',
		'couche_effis_indisponible' => $phrase_indisponible,
	};
} catch ( \UnhandledMatchError ) {
	_doing_it_wrong( 'templates/parts/panneau-feu.php', 'État de couche inconnu ; repli sur « couche_effis_indisponible ».', '0.1.0' );
	$etat    = 'couche_effis_indisponible';
	$message = $phrase_indisponible;
}

$zones_rendues = array();

if ( 'zones_disponibles' === $etat ) {
	// Ni tri, ni filtre, ni dédoublonnage : l'ordre et le périmètre viennent du
	// serveur (interdit 9). `surface_ha` et `geometrie` ne sont jamais lues
	// (interdits 4 et 5) — ce gabarit rend du texte, il ne projette rien.
	$zones = is_array( $couche['zones'] ) ? $couche['zones'] : array();

	foreach ( $zones as $zone ) {
		if ( ! is_array( $zone ) ) {
			continue;
		}

		$champs = array();

		// Déjà formatée par l'extension, unité et espace insécable compris. Le
		// thème ne formate jamais un nombre (MASTER §16, interdit 6).
		$surface = is_string( $zone['surface_texte'] ) ? trim( $zone['surface_texte'] ) : '';

		if ( '' !== $surface ) {
			$champs[] = array(
				'etiquette' => 'Surface estimée',
				'valeur'    => $surface,
				'datetime'  => '',
			);
		}

		// Étiquettes reprises mot pour mot du §5.2 du brief. Les deux instants
		// sont des instants ISO 8601 complets : aucun midi UTC n'est fabriqué
		// pour une observation satellite (contrat #11, A-11). La valeur reprend
		// l'assemblage déjà en service pour la fraîcheur — deux chaînes composées
		// par le domaine, jointes, jamais formatées ici.
		$observations = array(
			'Première observation' => is_string( $zone['premiere_observation'] ) ? trim( $zone['premiere_observation'] ) : '',
			'Dernière observation' => is_string( $zone['derniere_observation'] ) ? trim( $zone['derniere_observation'] ) : '',
		);

		foreach ( $observations as $etiquette_observation => $instant ) {
			if ( '' === $instant || ! function_exists( 'massifs_horodatage' ) ) {
				continue;
			}

			try {
				$horodatage_zone = massifs_horodatage( $instant );
			} catch ( \InvalidArgumentException ) {
				_doing_it_wrong( 'templates/parts/panneau-feu.php', 'Instant d\'observation refusé par le domaine ; la paire est omise.', '0.1.0' );
				continue;
			}

			if ( ! is_string( $horodatage_zone['attr_datetime'] )
				|| ! is_string( $horodatage_zone['date_courte'] )
				|| ! is_string( $horodatage_zone['heure'] ) ) {
				continue;
			}

			$champs[] = array(
				'etiquette' => $etiquette_observation,
				'valeur'    => $horodatage_zone['date_courte'] . ' à ' . $horodatage_zone['heure'],
				'datetime'  => $horodatage_zone['attr_datetime'],
			);
		}

		// Aucun référentiel communal n'existe encore dans le projet : la clé vaut
		// `''` en permanence et la paire est PUREMENT OMISE (contrat #11, A-8).
		// Aucun tiret, aucun « non renseigné », aucune hauteur réservée, et
		// surtout aucune substitution par le massif le plus proche — l'emplacement
		// existe, il se tait proprement, il accueillera la donnée sans refonte.
		$commune = is_string( $zone['commune_la_plus_proche'] ) ? trim( $zone['commune_la_plus_proche'] ) : '';

		if ( '' !== $commune ) {
			$champs[] = array(
				'etiquette' => 'Commune la plus proche',
				'valeur'    => $commune,
				'datetime'  => '',
			);
		}

		if ( array() === $champs ) {
			continue;
		}

		$zones_rendues[] = $champs;
	}

	// Contrat #11, A-15 : si toutes les zones sont illisibles, la partie bascule
	// sur `couche_effis_indisponible` et JAMAIS sur `aucune_zone`. Affirmer une
	// absence MESURÉE à partir d'une donnée ILLISIBLE est le faux négatif que le
	// §3.1 interdit ; le repli sûr est le silence déclaré.
	if ( array() === $zones_rendues ) {
		_doing_it_wrong( 'templates/parts/panneau-feu.php', 'Aucune zone lisible dans un relevé validé ; repli sur « couche_effis_indisponible ».', '0.1.0' );
		$etat    = 'couche_effis_indisponible';
		$message = $phrase_indisponible;
	}
}

// `couche_effis_indisponible` ne rend NI fraîcheur, NI phrase de limites, NI
// attribution (interdit 2, invariant I-11.6) : rien n'est daté, rien n'est
// qualifié, rien n'est crédité, parce qu'aucune donnée n'est affichée.
$couche_servie = 'couche_effis_indisponible' !== $etat;

$fraicheur_texte    = '';
$fraicheur_datetime = '';

if ( $couche_servie ) {
	$releve = is_string( $couche['releve_le'] ) ? trim( $couche['releve_le'] ) : '';

	if ( '' !== $releve && function_exists( 'massifs_horodatage' ) ) {
		try {
			$horodatage_releve = massifs_horodatage( $releve );

			if ( is_string( $horodatage_releve['attr_datetime'] )
				&& is_string( $horodatage_releve['date_courte'] )
				&& is_string( $horodatage_releve['heure'] ) ) {
				$fraicheur_datetime = $horodatage_releve['attr_datetime'];
				// Formule déjà en service (liste-statuts.php l. 342), réemployée et
				// jamais reformulée.
				$fraicheur_texte = 'Relevé le ' . $horodatage_releve['date_courte'] . ' à ' . $horodatage_releve['heure'];
			}
		} catch ( \InvalidArgumentException ) {
			_doing_it_wrong( 'templates/parts/panneau-feu.php', 'Instant de relevé refusé par le domaine ; la fraîcheur est omise.', '0.1.0' );
		}
	}
}

// Recopiée VERBATIM de MASTER.md §11.3, comme les sept chaînes déjà reprises par
// les parties livrées (contrat #11, A-3) : l'extension ne la publie pas, deux
// sources pour une même chaîne divergeraient. Les deux faits que le §4.4 du
// brief porte en plus — « mise à jour de l'ordre de deux fois par jour » et
// « ou d'évacuation » — ne sont PAS ajoutés : compléter une chaîne de liste
// fermée est un défaut bloquant, y compris quand le complément est vrai. Dette
// remontée à lead-design-cms (contrat #11, §10 D-2).
$limites = 'Périmètres estimés par satellite (feux d\'environ 30 ha et plus). Zone déjà parcourue par le feu, ce n\'est pas un périmètre officiel d\'interdiction.';

// La partie est auto-portante jusqu'à la bande incluse (contrat #11, A-13). Le
// <section> porte LUI-MÊME `bande__contenu`, sans <div> intermédiaire : les
// règles de rythme et de mesure de ligne de layout.css l. 130-145 ne visent que
// les ENFANTS DIRECTS de `.bande__contenu`, et la version imbriquée rendrait des
// paragraphes à margin nulle. Cette version rend correctement avec zéro octet de
// CSS neuf.
//
// Aucune pastille, aucun jalon, aucun aplat, aucune classe de statut : aucune
// information n'est portée par la couleur, ici par construction et non par
// vigilance (contrat #11 §8.2). Pas de `tabindex="-1"` sur la section — aucun
// lien d'évitement ne vise cette ancre, ce serait un attribut sans cause. Aucun
// élément interactif, donc aucun piège clavier et rien à fermer par Échap.
//
// `role="list"` / `role="listitem"` sont explicites, et c'est le corollaire
// liant du §10 D-15 du contrat #11 : une future feuille posant `display: grid`
// ou `flex` sur la liste ferait perdre la sémantique de liste à Safari/VoiceOver.
// Ils neutralisent le défaut d'avance, au moment où la feuille n'existe pas
// encore et où personne ne pourra plus faire le lien.
//
// La liste ne s'émet que sur `zones_disponibles` : le discriminant est `etat`,
// jamais un décompte (contrat #11 §3.1). Le repli A-15 ci-dessus garantit que
// cet état implique au moins une zone rendue, donc jamais de `<ul>` vide.
?>
<div class="bande bande--zones-parcourues">
<section id="<?php echo esc_attr( $ancre ); ?>" class="bande__contenu zones-parcourues" aria-labelledby="<?php echo esc_attr( $ancre . '-titre' ); ?>">
<<?php echo esc_html( $balise_titre ); ?> id="<?php echo esc_attr( $ancre . '-titre' ); ?>" class="zones-parcourues__titre">Zones parcourues par le feu</<?php echo esc_html( $balise_titre ); ?>>
<?php if ( 'zones_disponibles' === $etat ) : ?>
<ul class="zones-parcourues__liste" role="list">
<?php foreach ( $zones_rendues as $champs_zone ) : ?>
<li class="zones-parcourues__zone" role="listitem">
<dl class="zones-parcourues__champs">
<?php foreach ( $champs_zone as $champ ) : ?>
<dt class="zones-parcourues__etiquette"><?php echo esc_html( $champ['etiquette'] ); ?></dt>
<dd class="zones-parcourues__valeur"><?php if ( '' !== $champ['datetime'] ) : ?><time datetime="<?php echo esc_attr( $champ['datetime'] ); ?>"><?php echo esc_html( $champ['valeur'] ); ?></time><?php else : ?><?php echo esc_html( $champ['valeur'] ); ?><?php endif; ?></dd>
<?php endforeach; ?>
</dl>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ( '' !== $message ) : ?>
<p class="zones-parcourues__message"><?php echo esc_html( $message ); ?></p>
<?php endif; ?>
<?php if ( '' !== $fraicheur_texte ) : ?>
<p class="zones-parcourues__fraicheur"><time datetime="<?php echo esc_attr( $fraicheur_datetime ); ?>"><?php echo esc_html( $fraicheur_texte ); ?></time></p>
<?php endif; ?>
<?php if ( $couche_servie ) : ?>
<p class="zones-parcourues__limites"><?php echo esc_html( $limites ); ?></p>
<p class="zones-parcourues__attribution"><?php echo esc_html( $phrase_attribution ); ?></p>
<?php endif; ?>
</section>
</div>
