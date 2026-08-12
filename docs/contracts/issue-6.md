# Contrat d'interface — Issue #6 — Équivalent textuel des statuts, légende officielle et bandeau de non-officialité

**Gelé le** 12 août 2026 par `lead-issue-cms` (chaîne #6) · **Statut** : contraignant.

Ce contrat n'est **pas** une frontière front↔back : l'issue #6 ne touche que le thème. C'est la frontière
entre les **quatre parties de gabarit** que la chaîne #6 possède et **tout appelant** — aujourd'hui la
chaîne #5 (`front-page.php`, `header.php`, `functions.php`), la chaîne #4 (`assets/css/`), demain la
chaîne carte et les pages éditoriales. Une divergence constatée en revue est un défaut, pas une variante.

**Périmètre d'écriture de la chaîne #6 — quatre fichiers, aucun autre :**

- `wp-content/themes/massifs/templates/parts/liste-statuts.php`
- `wp-content/themes/massifs/templates/parts/legende.php`
- `wp-content/themes/massifs/templates/parts/bandeau-non-officialite.php`
- `wp-content/themes/massifs/templates/parts/etats-vides.php`

Aucun CSS, aucun `functions.php`, aucun gabarit de page, aucun cinquième fichier. Le contrat de classes
du §4 remplace le CSS que la chaîne #6 n'a pas le droit d'écrire.

---

## Convention d'inclusion — la seule prise en main possible

```php
get_template_part( 'templates/parts/liste-statuts',           null, array( /* … */ ) );
get_template_part( 'templates/parts/legende',                 null, array( /* … */ ) );
get_template_part( 'templates/parts/bandeau-non-officialite', null, array( /* … */ ) );
get_template_part( 'templates/parts/etats-vides',             null, array( /* … */ ) );
```

`$args` de `get_template_part()` exige **WP ≥ 5.5** — vérifié : la stack est `wordpress:php8.3-apache`
et `style.css` déclare `Requires at least: 6.4`.

**Les quatre fichiers sont du gabarit pur.** Aucune `function`, aucun `add_action`/`add_filter`, aucun
`define`, aucune constante, aucun `global`, aucun `static`, aucun `<script>`, aucune sortie hors de leur
conteneur racine unique. `load_template()` fait un `require`, **pas** un `require_once` : une partie
incluse deux fois est ré-exécutée — d'où l'interdiction absolue de toute déclaration.

**Piège de portée, traité :** avant l'inclusion, WordPress exécute `extract( $wp_query->query_vars )`.
`$name`, `$page`, `$order`, `$error`, `$day`, `$year`, `$hour`, `$title` existent donc déjà. **Toutes les
variables locales des quatre parties sont en français** (`$jour`, `$statuts`, `$synthese`,
`$niveau_titre`…) : aucune collision possible. `$args` est posé après l'`extract` et fait autorité.

**Toute clé `$args` absente, vide ou de mauvais type est traitée comme absente** — jamais de `TypeError`,
jamais d'avertissement PHP visible par le visiteur. Chaque partie s'auto-alimente à défaut, sous garde
`function_exists()`. **Toute clé non listée ci-dessous est ignorée.**

### `templates/parts/liste-statuts`

| Clé | Type | Défaut | Comportement |
|---|---|---|---|
| `jour` | `string` `YYYY-MM-DD` | `massifs_jour_courant()` | Contrôle de **forme** seul (`/^\d{4}-\d{2}-\d{2}$/`). Forme invalide ⇒ traitée comme absente + `_doing_it_wrong()`. Un contrôle de forme n'est pas une règle métier |
| `ancre` | `string` | `'liste'` | `sanitize_key()` ; vide après assainissement ⇒ `'liste'`. Préfixe de **tous** les `id` de la partie |
| `niveau_titre` | `int` | `2` | Retenu seulement dans `array(2,3,4,5,6)`. **Jamais 1** : le `h1` appartient à l'appelant |
| `massifs` | `array<string,array>` | `massifs_referentiel()` | Forme de `issue-2.md`. **Jamais retrié** |
| `statuts` | `array<string,array>` | `massifs_statuts_du_jour( array_keys( $massifs ), $jour )` | Forme de `issue-3.md` |
| `synthese` | `array` | `massifs_synthese_du_jour( array_keys( $massifs ), $jour )` | Seule source de la décision « tableau ou état vide » |
| `fraicheur` | `array` | `massifs_fraicheur( $jour )` | Alimente le `<caption>` |
| `attribution` | `array` | `massifs_attribution_statuts()` | **Seule** source de `carte_officielle_url`, transmise telle quelle à `etats-vides` |
| `legende` | `array` | `massifs_legende()` | Utilisée pour `zapef_note`. **Non transmise à `etats-vides`**, qui n'en tire aucune valeur — passer une clé ignorée serait de la donnée morte |
| `note_zapef` | `bool` | `true` | Rend la note `*ZAPEF : …` sous le tableau si au moins une cellule ZAPEF est remplie |

### `templates/parts/legende`

| Clé | Type | Défaut | Comportement |
|---|---|---|---|
| `ancre` | `string` | `'legende'` | `sanitize_key()`, préfixe des `id` |
| `niveau_titre` | `int` | `2` | Blanc-liste 2–6 |
| `legende` | `array` | `massifs_legende()` | Absente **et** fonction absente ⇒ la partie rend **zéro octet** |
| `etats_sur_ce_site` | `list<string>` | `array( 'indisponible', 'hors_saison' )` | Clés lues dans `legende['etats_hors_niveau']` ; une clé absente de ce tableau est ignorée en silence. La chaîne #5 y ajoutera `'non_encore_publie'` le jour du sélecteur de date |

### `templates/parts/bandeau-non-officialite`

| Clé | Type | Défaut | Comportement |
|---|---|---|---|
| `attribution` | `array` | `massifs_attribution_statuts()` | Absente **et** fonction absente ⇒ **phrase sans lien** (arbitrage F) |

### `templates/parts/etats-vides`

| Clé | Type | Défaut | Comportement |
|---|---|---|---|
| `etat` | `string` | `massifs_synthese_du_jour( massifs_codes(), $jour )['etat_global']` | `disponible` ⇒ **zéro octet**. Autres : `indisponible`, `hors_saison`, `non_encore_publie` |
| `jour` | `string` | `massifs_jour_courant()` | Même contrôle de forme |
| `saison` | `array` | `massifs_saison( $jour )` | Consommé par la seule branche `hors_saison` (`prochaine_ouverture`) |
| `attribution` | `array` | `massifs_attribution_statuts()` | Lien de la branche `indisponible` |

**Règle d'unicité des `id`** : `ancre` préfixe tous les `id`. Une **seconde inclusion d'une même partie
sur une même page DOIT passer un `ancre` distinct**. Les parties ne peuvent pas le détecter (aucun
`global`, aucun `static`) : c'est une obligation de l'appelant, opposable en revue.

## Ancre garantie au lien d'évitement

```html
<section id="liste" tabindex="-1" aria-labelledby="liste-titre">
```

Premier élément émis par `liste-statuts.php`, présent dès que l'extension est chargée. Le `h2` porte
`id="liste-titre"`.

`tabindex="-1"` est **obligatoire** : sans lui, plusieurs lecteurs d'écran (NVDA/Firefox, VoiceOver)
déplacent le curseur virtuel mais **pas** le focus clavier, et la tabulation suivante repart du haut de
page. Le nom accessible vient de `aria-labelledby` : la région est annoncée « La liste du jour » à
l'arrivée du saut. Aucun `role="region"` explicite — un `<section>` nommé mappe déjà sur `region`.

**Contrat pour la chaîne #5** (`header.php`) : `<a href="#liste">Aller à la liste des statuts</a>`,
**gardé par la même condition d'extension** que la partie (voir §Dépendances 5‑2), sinon le lien
pointerait vers un `id` inexistant.

## Titres émis

| Partie | Titre |
|---|---|
| `liste-statuts` | **un seul**, niveau `niveau_titre` (défaut `h2`), texte `La liste du jour` |
| `legende` | **un seul**, niveau `niveau_titre` (défaut `h2`), texte `Légende de la carte` (verbatim §11.4) |
| `bandeau-non-officialite` | aucun |
| `etats-vides` | aucun |

**Aucune partie n'émet jamais de `h1`.** Les capitales du rendu viennent de `text-transform: uppercase`,
**jamais** de capitales littérales dans le HTML — sinon lecture lettre à lettre par certains lecteurs
d'écran (MASTER §10.6 règle 6).

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` (`etat`/`etat_global` = `indisponible`) | `massifs_statuts_du_jour()` / `massifs_synthese_du_jour()` | **Page** : pas de `<table>`, `etats-vides` seul → « Information du jour non disponible. Consultez la carte officielle de la préfecture. » + lien. **Ligne** : `<th>` massif + cellule fusionnée `colspan="3"`, pastille `--indisponible` + libellé `information non disponible` |
| `hors_saison` | idem | « Dispositif estival inactif. Reprise le {date}. », `{date}` = `massifs_saison()['prochaine_ouverture']`. Étiquette de ligne et de légende : `dispositif estival inactif` |
| `non_encore_publie` | idem, seulement pour un jour futur | Phrase **entière** §11.3 : « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. » (espace insécable U+00A0). **Inatteignable avec `jour = massifs_jour_courant()`** |
| `donnee_perimee` | **Pas un `etat`** : `massifs_fraicheur()['perimee']` | **Hors de la chaîne #6.** `perimee` n'est lu par aucune des quatre parties. La bannière §8.3 est page-level et appartient à la chaîne #5 ; elle **s'ajoute** aux statuts, ne les masque jamais |
| `couche_effis_indisponible` | Hors périmètre | Aucune des quatre parties ne le connaît |
| `referentiel_indisponible` (`massifs_referentiel()` = `array()`) | `issue-2.md` | Section + titre + `etats-vides` en `indisponible`. **Aucune chaîne nouvelle n'est créée** pour ce cas |

**Décision de niveau page**, lue dans `synthese['etat_global']`, **jamais recalculée** :

| `etat_global` | Rendu |
|---|---|
| `disponible` | Tableau complet, **toutes** les lignes, y compris celles sans donnée d'une journée partielle. `synthese['partiel']` ne pose que la classe `liste-statuts--partielle` — aucune phrase supplémentaire |
| `indisponible` · `hors_saison` · `non_encore_publie` | **Pas de `<table>` du tout.** Un seul appel à `etats-vides` |

## Chaînes fournies par le serveur

| Fourni par l'extension — reproduit verbatim, jamais paraphrasé | Composé par le thème |
|---|---|
| `niveau['libelle']` : `Accès au massif autorisé` / `Accès au massif interdit` | Les chaînes fixes de MASTER §11.3 (non-officialité, indisponible, hors saison, non encore publié) |
| `zapef['libelle']` : `Accès à la ZAPEF* autorisé` / `Accès à la ZAPEF* interdite` — **`autorisé` masculin, `interdite` féminin, c'est la source** | Les étiquettes courtes hors niveau de MASTER §8.5 |
| `legende['zapef_note']` : `*ZAPEF : Zones d’Accueil du Public en Forêt` — apostrophe **U+2019** | Les en-têtes de colonnes `Massif`, `ZAPEF`, `Fraîcheur` |
| `massifs_horodatage()` → `date_longue`, `date_courte`, `heure`, `attr_datetime` | Tout HTML, tout échappement, tout `id`, toute classe |
| `attribution['carte_officielle_url']` | L'intitulé du lien (`carte officielle de la préfecture`) |
| `massif['libelle']` — jamais `source.nom_massif` | — |

**`Niveau d'Accès`** est un en-tête de colonne **officiel** (MASTER §11.4) : apostrophe **droite U+0027**,
majuscule à `Accès`. Il n'est pas fourni par l'extension mais il est **reproduit**, pas rédigé.

**Les deux apostrophes divergent volontairement** : `Zones d’Accueil` porte U+2019, `Niveau d'Accès`
porte U+0027. Toute « uniformisation typographique » — réflexe naturel d'une passe de nettoyage, d'un
linter ou d'un correcteur — est un **défaut bloquant**.

## Le fragment de marque — garantie inviolable de la chaîne #6

Faute de cinquième fichier (arbitrage G), ce fragment est **dupliqué à l'octet près** entre
`liste-statuts.php` et `legende.php` :

```html
<span class="statut">
  <span class="statut__marque {pastille|jalon} {--variante}" aria-hidden="true"></span>
  <span class="statut__libelle">{libellé en toutes lettres}</span>
</span>
```

Règles opposables en revue :

1. `.statut__marque` n'est **jamais** émis ailleurs que comme premier enfant de `.statut`, immédiatement
   suivi de `.statut__libelle`.
2. La marque et le libellé sont émis **dans le même bloc de sortie**. Il ne doit exister **aucun** chemin
   de code où la marque est écrite et le libellé non.
3. Libellé vide (donnée serveur vide) ⇒ **la marque n'est pas émise non plus**, le `.statut` entier est omis.
4. `aria-hidden="true"` sur la marque : elle double visuellement le libellé ; l'annoncer ferait entendre
   deux fois la même chose.
5. **Aucun texte, jamais, à l'intérieur de `.statut__marque`** (MASTER §4.1.d règle 3 : aucune encre
   n'atteint 4,5:1 sur `#E63A3C`).

**Table fermée des variantes — aucune classe n'est calculée ni dérivée de `jeton_css` :**

| Origine | Valeur | Classe de marque |
|---|---|---|
| `niveau['cle']` | `autorise` | `pastille pastille--autorise` |
| `niveau['cle']` | `interdit` | `pastille pastille--interdit` |
| `zapef['cle']` | `autorise` | `jalon jalon--autorise` |
| `zapef['cle']` | `interdit` | `jalon jalon--interdit` |
| `etat` | `indisponible` | `pastille pastille--indisponible` |
| `etat` | `hors_saison` | `pastille pastille--hors-saison` |
| `etat` | `non_encore_publie` | `pastille pastille--non-publie` |

Discontinuité volontaire `non_encore_publie` → `--non-publie` : c'est le nom fixé par le CSS de MASTER
§8.1. Une transformation automatique de la clé produirait `--non-encore-publie`, classe inexistante,
donc **aucun aplat et aucun motif** — d'où la table fermée.

## Classes CSS — liste fermée, contrat opposable pour les chaînes #4 et #5

**Aucune valeur n'est proposée ici** : ni couleur, ni taille, ni espacement, ni durée. Uniquement des
noms et le renvoi à la section de `MASTER.md` qui spécifie déjà l'effet. Toute classe supplémentaire
constatée en revue est un défaut de la chaîne #6 ; toute classe de cette liste non stylée est un défaut
des chaînes #4/#5.

### Partagées

| Classe | Effet attendu | MASTER |
|---|---|---|
| `repere` | Signature devant chaque `h2` | §3.1, §3.2 (2) |
| `repere--bloc` | Variante pleine hauteur, bord gauche de bandeau | §3.1, §3.2 (6) |
| `sur-sombre` | Bascule `--statut-lisere` et les encres de motif sur chrome sombre | §12, exception 2 |
| `statut` | Conteneur en ligne marque + libellé | §8.1 |
| `statut__marque` | L'aplat ; jamais de texte dessus | §4.1.d (3), §8.1 |
| `statut__libelle` | Libellé `--fs-250`, capitales par `text-transform`, encre sur fond de page | §8.1, §5.1 |
| `pastille` · `pastille--autorise` · `pastille--interdit` · `pastille--indisponible` · `pastille--hors-saison` · `pastille--non-publie` | Rectangle 26 × 16, liseré 2 px, motifs | §8.1 (CSS verbatim) |
| `jalon` · `jalon--autorise` · `jalon--interdit` | Carré planté 18 × 18 + hampe, liseré 2 px | §8.1 (CSS verbatim) |

### `liste-statuts.php`

`liste-statuts` (§7.1, §6.1) · `liste-statuts--partielle` (crochet d'état, **aucun changement de teinte
de statut**, §9.2) · `liste-statuts__titre` (§5.1, §7.1) · `liste-statuts__tableau` (vraies colonnes ≥
`--bp-s`, **cartes empilées en dessous**, filets `--bord-fin` — §7.1, §6.3) · `liste-statuts__resume`
(`<caption>`, `--fs-200`) · `liste-statuts__entete` (`--fs-250`, `--ls-etiquette`) ·
`liste-statuts__ligne` (survol `--c-calcaire-ombre`, `page-break-inside: avoid` — §9.2, §13) ·
`liste-statuts__ligne--entete` (`display: table-header-group` conservé à l'impression, §13) ·
`liste-statuts__ligne--hors-niveau` · `liste-statuts__massif` · `liste-statuts__cellule` (+ `--niveau`,
`--zapef`, `--fraicheur`, `--hors-niveau` ; **`:empty` masqué en mode cartes** ;
`content: attr(data-etiquette)` avant la valeur en mode cartes) · `liste-statuts__fraicheur` (`<time>`,
chiffres tabulaires) · `liste-statuts__note` (`--fs-100`).

### `legende.php`

`legende` (bloc, `--ombre-decalee`, **jamais masqué ni replié**, §8.5, §6.4) · `legende__titre` ·
`legende__entrees` (+ `--massif`, `--zapef`, `--hors-niveau` ; bande 2 + 2 ≥ `--bp-s`, 2 colonnes à
360 px, listes sans puce, §7.1) · `legende__entree` · `legende__note` (rattachée aux deux entrées ZAPEF,
§8.5) · `legende__hors-niveau` (seconde ligne, séparée par `--bord-fin`, §8.5) · `legende__etiquette`
(`Sur ce site`, capitales par `text-transform`) · `legende__avertissement`.

### `bandeau-non-officialite.php` et `etats-vides.php`

`bandeau-non-officialite` (bande pleine largeur `--c-calcaire-ombre`, `--bord-fort` en haut, `--fs-200`,
**jamais `display: none` en `@media print`**, §7.1, §6.3, §13) · `bandeau-non-officialite__texte` ·
`bandeau-non-officialite__lien` · `bandeau-alerte` (+ `--indisponible`, `--hors-saison`, `--non-publie` ;
fond `--c-mistral-nuit`, `--bord-fort` en bas, hachure `--c-mistral` ; **les trois variantes ne diffèrent
par aucune couleur**, ce sont des crochets structurels, §8.3) · `bandeau-alerte__texte` ·
`bandeau-alerte__lien` (`--c-mistral-clair`, **interdit sur fond clair**, §4.2, §9.1).

**Deux obligations transverses pour #4/#5 :**
1. Les capitales sont **toujours** produites par `text-transform: uppercase`, jamais par des capitales
   littérales dans le HTML.
2. Les libellés officiels ne sont **jamais** tronqués (`text-overflow: ellipsis` interdit sur
   `.statut__libelle`) ni abrégés, à aucune largeur.

## Fonctions de lecture consommées — liste fermée

`massifs_jour_courant()` · `massifs_referentiel()` · `massifs_statuts_du_jour()` ·
`massifs_synthese_du_jour()` · `massifs_fraicheur()` · `massifs_saison()` · `massifs_horodatage()` ·
`massifs_legende()` · `massifs_legende_est_confirmee()` · `massifs_attribution_statuts()` ·
`massifs_codes()` (uniquement dans le repli auto-alimenté de `etats-vides`).

**Toutes sous garde `function_exists()`. Aucune autre fonction, aucune classe `Massifs\`, aucune
constante de l'extension.** Les clés `severite`, `rang`, `total`, `consigne`, `jeton_css`,
`jeton_encre_css`, `niveau_source_brut`, `procedure_source`, `statut_id`, `auteur_id` ne sont **jamais
lues** par ces gabarits.

## Interdits

**Pour les quatre parties**

1. Interroger `$wpdb` ou une table de l'extension.
2. Instancier ou appeler une classe `Massifs\`. Seules les fonctions `massifs_*` sont publiques.
3. Calculer « aujourd'hui » ou « demain » : aucun `date()`, `time()`, `strtotime()`, `current_time()`,
   `wp_date()`, `date_i18n()`. Le `preg_match` de forme sur `$args['jour']` ne calcule aucune date.
4. Formater une date hors `massifs_horodatage()`.
5. Fabriquer, paraphraser, corriger, tronquer ou abréger un libellé officiel, une consigne, un ordre de
   sévérité ou une couleur.
6. Écrire une valeur hexadécimale, une taille, un espacement ou une durée — les quatre fichiers ne
   contiennent **aucune** valeur visuelle.
7. Afficher un `niveau` ou un `zapef` quand `etat !== 'disponible'`.
8. Rejouer un statut d'un autre jour, le mémoriser, ou afficher une donnée de la veille.
9. Lire `perimee` ou traiter la péremption : elle est page-level (chaîne #5) et **s'ajoute** aux statuts.
10. Contacter une origine tierce, émettre un `<script>`, un attribut `on*`, une image ou une police distante.
11. Utiliser un `if/else` avec branche « sinon » sur `etat` — voir arbitrage E.
12. Trier la liste : `massifs_referentiel()` arrive trié par `tri`. Aucun `sort`, `usort`, `ksort`,
    `setlocale`.
13. Écrire « aucune commune », « aucune ZAPEF », « — », « non renseigné » ou tout squelette d'absence.
14. Émettre un `h1`.
15. Rendre une marque de couleur sans son libellé adjacent en toutes lettres.
16. Écrire une URL en dur, y compris celle de la carte officielle.
17. Toucher un fichier hors des quatre du périmètre.
18. Employer `__()`, `_e()`, `esc_html__()`, `esc_html_e()` — voir arbitrage I.
19. Appliquer `wptexturize()`, `sanitize_text_field()`, `wp_kses*()`, `ucfirst()`, `mb_convert_case()` ou
    tout `str_replace` typographique aux chaînes officielles.

**Pour l'appelant (chaînes #4, #5 et suivantes)**

20. Rendre un `h2` pour la liste ou pour la légende : les deux parties portent leur propre titre.
21. Inclure deux fois la même partie sans passer un `ancre` distinct.
22. Masquer la légende derrière un bouton, un accordéon ou un survol (MASTER §8.5).
23. Masquer `bandeau-non-officialite` à l'impression.
24. Appliquer `display: block` aux éléments de tableau **sans** conserver les rôles ARIA explicites, ou
    en ajouter de concurrents.

## Arbitrages

| # | Point ouvert | Décision retenue | Raison |
|---|---|---|---|
| A | « Aucune restriction en cours » (case 4 de l'issue, §5.3 du brief) | **Non rendue.** Les états vides sont exactement les **trois** de MASTER §11.3 | La phrase est héritée d'une hypothèse à 5 crans ; le dispositif réel est binaire. Une journée où les 25 massifs sont `autorise` est une journée de **donnée complète et disponible** : substituer une phrase à la liste supprimerait 25 statuts réellement publiés, l'inverse exact du §5.3. La synthèse de journée a déjà son emplacement et sa formulation (l'ardoise, MASTER §7.1, chaîne #5), et le mot « restriction » est hors du vocabulaire fixe §11.2. **Divergence documentée avec la lettre du brief, à acter par le propriétaire** |
| B | `massifs_horodatage()` refuse un jour civil nu (`Horloge::instant_depuis_chaine()` exige la partie horaire) | **Couture `massifs_horodatage( $jour . 'T12:00:00Z' )`**, dont on ne lit **que** `date_longue` et `date_courte` ; `heure` et `attr_datetime` de cet appel sont **interdits**. Commentée en toutes lettres à chaque occurrence | Midi UTC vaut 13 h ou 14 h à Paris : le jour civil ne bascule jamais. L'alternative — formater la date dans le thème — violerait l'interdit 4 de `issue-3.md`. Dépendance déposée auprès de la chaîne #3 : exposer `massifs_horodatage_jour( string $jour ): array`. Pour l'attribut `datetime=` d'un jour civil, c'est le **`YYYY-MM-DD` brut** qui est écrit, jamais une valeur reconstruite |
| C | Colonne ZAPEF : pour tous les massifs ou seulement ceux qui en portent ? | **Pour tous**, pilotée uniquement par `zapef !== null` | Le domaine émet la dimension ZAPEF par massif ; le thème rend ce que le serveur émet et ne décide jamais quel massif « porte » des ZAPEF (ce serait une règle métier). La retirer ferait disparaître du site la moitié de la légende publiée. `zapef === null` ⇒ cellule **strictement vide** : aucun tiret, aucune mention d'absence |
| D | Ligne « SUR CE SITE » de la légende : deux ou trois états ? | **Deux par défaut** — `indisponible`, `hors_saison` — étiquettes **verbatim de MASTER §8.5** : `information non disponible`, `dispositif estival inactif`. `$args['etats_sur_ce_site']` permet d'ajouter `non_encore_publie` | §8.5 est normatif et n'en nomme que deux ; en inventer une troisième serait une invention. Le troisième état n'apparaît qu'avec le sélecteur de date, que la chaîne #6 ne livre pas |
| E | Interdit 11 de `issue-3.md` : `match()` **sans** `default` | **`match()` sans `default`, entouré d'un `try { } catch ( \UnhandledMatchError $e ) { }`** qui retient `indisponible` et appelle `_doing_it_wrong()` | Pris au pied de la lettre dans un gabarit d'accueil public, un cinquième `etat` produirait un `\UnhandledMatchError` non rattrapé, c'est-à-dire **un écran blanc pour tous les visiteurs**. L'enveloppe préserve l'intention de l'interdit — l'ajout d'un état reste **bruyant** (journal + état dégradé visible) et jamais silencieux — sans faire d'une évolution du domaine une panne de site. Le repli est `indisponible`, c'est-à-dire une **absence**, jamais une donnée |
| F | Repli quand l'extension est absente : d'où vient l'URL de la carte officielle ? | **Elle ne vient de nulle part.** Le bandeau rend alors **sa phrase sans lien** | Sans extension, aucun statut n'est affiché nulle part, donc l'obligation §5.6 (« sur toute page affichant un statut ») n'est pas déclenchée. Coder l'URL en dur violerait l'interdit 5 de `issue-3.md`. Un lien mort serait pire qu'une phrase seule |
| F′ | Deux fonctions portent aujourd'hui la même URL : `massifs_legende()['source_officielle_url']` et `massifs_attribution_statuts()['carte_officielle_url']` | **`massifs_attribution_statuts()` partout**, sans exception | `issue-3.md` la désigne explicitement (« le thème ne rédige jamais cette chaîne ni cette URL à la main »). Deux sources d'une même valeur finissent par diverger ; une seule ne le peut pas |
| G | Extension d'empreinte demandée : `templates/parts/pastille.php`, pour ne pas dupliquer le balisage de l'objet le plus répété du site | **Refusée.** La duplication `liste-statuts.php` ⇄ `legende.php` est assumée, et le fragment doit être **identique à l'octet près** | L'empreinte est fixée par l'orchestrateur et c'est la seule protection contre l'écrasement entre trois chaînes partageant un arbre de travail. Deux blocs de dix lignes, gardés par la table fermée du §Fragment et vérifiés par diff en passe de conformité, coûtent moins qu'un précédent d'élargissement d'empreinte |
| H | Contenu du `<caption>` — MASTER §11.3 fige « Statuts du {jour}, publiés **la veille** à {heure} par la préfecture — relevés sur ce site le {date} à {heure} » | **Sous-ensemble de la chaîne §11.3, jamais une reformulation** : « Statuts du {`date_longue` du jour de validité}. », suivi de « — relevés sur ce site le {date} à {heure}. » **si et seulement si** `fraicheur['dernier_releve_le']` est non nul. **La clause « publiés la veille à {heure} par la préfecture » n'est pas rendue par la liste** | Le plan front proposait de la remplacer par « publiés le {date} à {heure} », ce qui **réécrit une chaîne fixe** du design system — que la chaîne #6 n'a pas le droit de modifier. Or « la veille » est une affirmation métier que le thème ne peut pas produire, et `publication_heure` (`'17:00'`) est une chaîne d'horloge que `massifs_horodatage()` refuse. La seule sortie qui n'invente rien et ne réécrit rien est de **n'employer que les clauses adossées à une donnée**. La phrase de fraîcheur complète appartient de toute façon à l'ardoise (MASTER §7.1, chaîne #5), qui la rendra une fois ; la liste porte le **jour de validité**, exigé par §13 à l'impression, et la fraîcheur **par ligne** dans sa colonne dédiée |
| I | Internationalisation | **Aucune fonction `__()` / `_e()` / `esc_html__()` dans ces quatre fichiers** | Le site est en français uniquement (brief §2 ; multilingue hors périmètre §13) ; les libellés officiels ne sont pas traduisibles par nature ; et la mention de non-officialité du §5.6 ne doit pas pouvoir être altérée par un pack de langue. Divergence de style avec `index.php` signalée à l'orchestrateur |
| J | Colonne `Fraîcheur` : que porte-t-elle, `massifs_fraicheur()` étant **globale au jour** ? | **`enregistre_le` de la ligne**, via `massifs_horodatage()` : `attr_datetime` dans l'attribut, `date_courte` + `heure` en texte. Cellule vide si l'instant est absent | `enregistre_le` est le seul instant garanti non nul quand `etat === 'disponible'` (`publie_prefecture_le` peut être nul). La colonne dit donc **toujours la même chose** — « quand ce site a relevé cette donnée », soit la définition même de *fraîcheur* (§11.2). Une colonne dont la sémantique changerait d'une ligne à l'autre serait illisible au lecteur d'écran. L'instant de publication préfectorale est porté une seule fois, par l'ardoise |
| K | Lignes sans donnée : trois cellules vides ou une cellule fusionnée ? | **Cellule fusionnée `colspan="3"` + `aria-colspan="3"`** portant la marque et le libellé de l'état | Sans donnée, il n'y a rien à mettre dans `ZAPEF` ni dans `Fraîcheur`. Trois cellules obligeraient soit à laisser deux cellules vides (« blank, blank » au lecteur d'écran), soit à **inventer** deux chaînes — ce que §11.3 interdit. `aria-colspan` double le `colspan` natif, qui n'est plus fiable sous `display: block` + rôles explicites |
| L | Rôles ARIA explicites sur un vrai `<table>` : redondance ou nécessité ? | **Nécessité.** `role="table"`, `rowgroup`, `row`, `columnheader`, `rowheader`, `cell` **en plus** de `scope="col"` / `scope="row"` | Deux exigences de MASTER se contredisent en apparence : §7.1 impose des **cartes empilées à 360 px** (donc `display: block` appliqué par #4/#5, ce qui **détruit les rôles implicites** de tableau dans les navigateurs — le tableau devient un groupe générique, les lignes disparaissent de l'arbre, l'association en-tête ↔ cellule est perdue) et §13 impose `thead { display: table-header-group }` à l'impression (donc un **vrai `<table>`**). Les rôles explicites survivent au changement de `display` et réconcilient les deux **sur un seul balisage**. C'est une régression d'accessibilité invisible jusqu'à l'audit si elle n'est pas traitée maintenant, et elle tombe précisément entre deux empreintes |

## Dépendances hors empreinte — signalées, non traitées par cette chaîne

| # | Destinataire | Attendu |
|---|---|---|
| 5‑1 | chaîne #5 (`front-page.php`) | Rendre `bandeau-non-officialite` **une fois** sur toute page incluant `liste-statuts` (§5.6), placé **entre l'ardoise et la carte** (MASTER §7.1), **pas** en pied de page |
| 5‑2 | chaîne #5 (`header.php`) | `<a href="#liste">Aller à la liste des statuts</a>`, **gardé par** `function_exists( 'massifs_statuts_du_jour' ) && function_exists( 'massifs_referentiel' )` — sinon la partie ne rend rien et l'ancre n'existe pas. Conserver `#contenu-principal` pour « Aller au contenu » |
| 5‑3 | chaîne #5 | N'émettre **aucun `h2`** pour la liste ni pour la légende. Le `h1` unique de la page lui appartient |
| 5‑4 | chaîne #5 | Rendre la bannière de péremption §8.3 quand `massifs_fraicheur()['perimee'] === true`, et la phrase de fraîcheur complète §11.3 dans l'ardoise. Non couvert par `etats-vides` |
| 5‑5 | chaîne #5 | Passer les `$args` pré-résolus pour éviter des appels domaine redondants ; passer un `ancre` distinct à toute seconde inclusion |
| 4‑1 | chaîne #4 (`tokens.css`) | Les jetons de MASTER §12, **y compris** `--statut-hors-saison`, `--statut-non-publie` et leurs `-encre` |
| 4‑2 | chaînes #4/#5 (`layout.css`) | Le contrat de classes ci-dessus : cartes empilées sous `--bp-s` via `display: block` + `content: attr(data-etiquette)` + masquage des cellules `:empty` ; `thead { display: table-header-group }` et `page-break-inside: avoid` à l'impression ; `bandeau-non-officialite` jamais masqué à l'impression ; capitales par `text-transform` uniquement ; jamais de troncature de `.statut__libelle` |
| 3‑1 | chaîne #3 | Exposer `massifs_horodatage_jour( string $jour ): array` pour supprimer la couture de l'arbitrage B. Et arbitrer la double source de l'heure de publication : `publication_heure` (donnée de `legende.config.php`) vs le « 17 h » figé en dur dans MASTER §11.3 |
| D‑1 | `lead-design-cms` | **Étiquette courte manquante** pour `non_encore_publie` en §8.5, alors que les deux autres états hors niveau en ont une. Bloquant pour le sélecteur de date de #5, pas pour #6 |
| D‑2 | `lead-design-cms` + orchestrateur | **25 massifs, pas 27.** Le référentiel gelé (`issue-2.md`, B‑15/B‑16) contient 25 entrées ; les 27 sont des identifiants du flux préfectoral, dont 2 sans correspondance. MASTER §7.1 (« 12 MASSIFS SUR 27 ») et §8.2 (« frise de 27 marques ») sont à corriger. La liste rendra 25 lignes |
| D‑3 | `lead-design-cms` | La note `*ZAPEF : …` apparaît **deux fois** sur l'accueil : dans la légende (§8.5) et sous la liste (pour que celle-ci reste compréhensible après un saut par `#liste` ou imprimée seule). Doublon **accepté par défaut** ; `note_zapef => false` permet de le retirer |
| O‑1 | orchestrateur | Divergence de style i18n entre ces quatre fichiers (aucune fonction de traduction, arbitrage I) et `index.php` (`esc_html_e`). À généraliser ou à trancher au niveau du thème |
