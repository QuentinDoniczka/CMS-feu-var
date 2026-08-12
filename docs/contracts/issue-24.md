# Contrat d'interface — Issue #24 — Combler le repli hors accueil : `index.php`, `page.php`, `404.php`

**Gelé le 12 août 2026** par `lead-issue-cms` (chaîne #24). Liant à partir de ce point.

Cette issue **ne touche aucun fichier de l'extension** et **n'écrit aucune ligne de CSS**.
`leaddev-back-cms` et `dev-ux-cms` n'ont donc pas été lancés, et il n'y a **pas de jonction
front↔back** à faire. Le contrat porte sur quatre frontières :

1. thème #24 → **`templates/**` de la chaîne #5** — armature d'ouverture et de fermeture du document ;
2. thème #24 → **`assets/css/**`** — crochets de classe consommés, et la dette typographique F-2 ;
3. thème #24 → **chaînes sœurs #25 (`functions.php`) et #28 (`composants.css`)** — non-collision ;
4. thème #24 → **chaîne `contenu`** — ce que le corps d'une page éditoriale n'a pas le droit de contenir.

`docs/contracts/issue-5.md` listait `index.php` en « hors périmètre et **sans propriétaire** ».
**Ce contrat lui en donne un.** La ligne correspondante du §« Hors périmètre » du contrat #5 est
close par celui-ci.

## Empreinte d'écriture — exhaustive et fermée

```
wp-content/themes/massifs/index.php     (corrigé)
wp-content/themes/massifs/page.php      (créé)
wp-content/themes/massifs/404.php       (créé)
docs/contracts/issue-24.md              (ce fichier, écrit par le lead)
```

Rien d'autre. Sont **hors empreinte** et ne sont ni créés, ni modifiés, ni déplacés :
`functions.php` (**chaîne #25, en parallèle**), `assets/css/composants.css` (**chaîne #28, en
parallèle**), `assets/css/layout.css`, `assets/css/tokens.css`, `assets/fonts/**`, `front-page.php`,
`style.css`, `templates/**`, `tests/**`, `docker/**`, `design-system/MASTER.md`, toute l'extension
`massifs-core`.

**Aucun** `home.php`, `single.php`, `singular.php`, `archive.php`, `search.php`, `attachment.php`,
`header.php`, `footer.php`, `searchform.php` n'est créé — la liste est exhaustive et opposable.
Arbre de travail unique, mono-branche : écrire hors de cette empreinte détruit le travail d'une
chaîne sœur, sans branche pour rattraper.

---

## Fonctions de lecture exposées par l'extension

**Aucune.** C'est le fait le plus important de ce contrat.

Les trois gabarits ne lisent **aucune** donnée de domaine : ni statut, ni niveau, ni saison, ni
fraîcheur, ni attribution. Ils n'appellent **aucune** fonction `massifs_*` de l'extension.
**Ils rendent rigoureusement le même HTML que `massifs-core` soit active ou désactivée** — la seule
variation vient de `templates/footer.php`, qui garde déjà ses attributions par `function_exists()`.

Corollaire : **aucune garde `function_exists()` n'est écrite** dans les trois gabarits, parce qu'il
n'y a rien à garder. Une garde sans objet est du bruit qui suggère une dépendance inexistante.

### Fonctions du thème consommées (`functions.php`, hors empreinte, en lecture seule)

| Fonction | Signature | Emploi |
|---|---|---|
| `massifs_journaliser` | `( string $message ): void` | diagnostic sous `WP_DEBUG` seulement — branche non singulière d'`index.php`, branche `have_posts() === false` de `page.php`. N'atteint jamais le visiteur |
| `massifs_partie` | `( string $slug ): bool` | **non appelée** — aucune partie de `templates/parts/` n'est rendue |
| `massifs_menu` | `( string, string ): void` | **non appelée directement** — `templates/header.php` et `templates/footer.php` s'en chargent |
| `massifs_version_asset`, `massifs_emplacements_de_menu` | — | non appelées |

**Dépendance déclarée envers la chaîne #25** : `massifs_journaliser()` doit continuer d'exister avec
cette signature. Sa disparition produirait une erreur fatale sur toute page non singulière et sur
toute `page.php` sans post. C'est la seule surface de couplage entre #24 et #25.

## Routes REST

**Aucune.** L'issue #24 n'expose, ne consomme et ne déclare aucune route REST.

---

## États spéciaux

| État | Émis par le serveur | Rendu par le thème (#24) |
|---|---|---|
| `information_indisponible` | — | **hors périmètre** : aucun de ces gabarits n'affiche de statut |
| `hors_saison` | — | **hors périmètre**, même raison |
| `donnee_perimee` | — | **hors périmètre**, même raison |
| `couche_effis_indisponible` | — | **hors périmètre**, même raison |
| **`is_404()`** | cœur WordPress (`WP::handle_404()`, `status_header(404)`) | `404.php` — `h1` de D-7, lien de retour, **aucune Boucle** |
| **`is_singular()` vrai** | requête principale | `h1` = `the_title()`, puis `the_content()` |
| **contexte non singulier** (recherche, archives, auteur, date) | requête principale | **chrome complet, contenu vide, aucun `h1`** — trou assumé, voir A-4 |

**Le bandeau de non-officialité n'est pas rendu, et c'est délibéré.** Le brief §5.6 l'impose sur
« toute page affichant un statut » ; aucun de ces trois gabarits n'en affiche. L'ajouter exigerait
`massifs_partie( 'bandeau-non-officialite' )` sur une page sans donnée — une mention de
non-officialité portant sur rien.

**La règle de sécurité §4.2 (« jamais un statut périmé présenté comme courant ») est tenue
structurellement** : ces gabarits ne peuvent pas présenter un statut, périmé ou non, puisqu'ils
n'en lisent aucun. Il n'y a rien à vérifier par vigilance.

---

## Chaînes fournies par le serveur

**Aucune.** Aucune chaîne de l'extension n'est consommée.

### Chaînes rédigées par le thème — deux, et elles sont **NON RATIFIÉES**

| Emploi | Chaîne exacte, à reprendre mot pour mot | Fichier |
|---|---|---|
| `h1` de la page introuvable | `Cette adresse ne correspond à aucune page de ce site.` | `404.php` |
| Libellé du lien de retour | `Aller à l’accueil` — apostrophe **U+2019** | `404.php` |

**Statut : livrées non ratifiées, dette écrite, pas défaut découvert.** `MASTER.md` §11.3 est
présenté par le §7.3 comme la liste **fermée** des phrases que le site a le droit de rédiger, et le
§16 en fait un point de revue (précédent : la phrase « zéro cookie », refusée par la chaîne #23).
Aucune entrée du §11.3 ne couvre une page 404 ; contrôlé, `MASTER.md` ne connaît même pas
l'existence d'une telle page (ses quatre occurrences de « 404 » visent le 404 HTTP de la source
préfectorale et le risque de lien de pied mort). **Demande F-1 portée à `lead-design-cms`.**

Aucune variante, aucune troisième chaîne, aucun « vous cherchiez peut-être », aucun formulaire de
recherche, aucune liste de pages, aucune excuse.

### Chaînes du **cœur WordPress** rendues — une seule, et jamais visible

`wp_get_document_title()`, appelée par `templates/header.php` l. 25, produit le `<title>` de chaque
page — dont « Page non trouvée » sur la 404. **C'est le mécanisme standard de génération de titre
exigé par la case 1 de l'issue**, et il satisfait la case 2 (« `<title>` distinct des autres
gabarits ») **sans qu'une ligne soit écrite**. Le `<title>` n'est pas de la copie visible : le thème
ne rédige donc aucun titre.

**Aucune chaîne du cœur n'est rendue comme copie visible** — ni `get_the_archive_title()`, ni
« Résultats de recherche pour … » : voir A-4.

---

## Interdits

Récapitulatif exécutoire. Chacun est vérifiable par lecture, sans exécution.

**Armature du document**
- `get_header()` et `get_footer()` restent **bannis** dans ce thème (interdit hérité du contrat #5,
  arbitrage A-8). L'inclusion passe par `get_template_part( 'templates/header' )` / `( 'templates/footer' )`.
- **Aucun** `<!DOCTYPE>`, `<html>`, `<head>`, `<meta>`, `<title>`, `<body>`, `<main>`, `wp_head()`,
  `wp_body_open()`, `wp_footer()`, `body_class()`, `language_attributes()` dans les trois gabarits.
  Les émettre produirait un second `<main>` et un **doublon d'`id="contenu-principal"`**.
- `add_theme_support( 'title-tag' )` n'est **pas** déclaré et ne doit pas l'être : `templates/header.php`
  imprime déjà le `<title>`, le support en ferait imprimer un second. Structurellement garanti ici,
  `functions.php` étant hors empreinte.

**Requête tierce — contrainte non négociable n° 2**
- `comments_template()`, `comments_number()`, `comments_open()`, `wp_list_comments()`,
  `comment_form()`, `post_comments_feed_link()`, **`get_avatar()`** sont **interdits**, et pas
  seulement omis. Motif mesurable : `/hello-world/` porte le commentaire de démonstration de
  WordPress ; le rendre affiche un avatar **Gravatar**, soit une requête navigateur vers
  `secure.gravatar.com`, sur l'URL même que les scénarios `s01` et `s03` parcourent. Le brief §2
  exclut par ailleurs tout commentaire du périmètre.

**Domaine et présentation**
- Le thème n'appelle **jamais** une source externe, ni une fonction d'ingestion, ni une classe
  `Massifs\`, ni `$wpdb`.
- Le thème ne calcule **jamais** une règle métier ; `date()`, `time()`, `current_time()`,
  `strtotime()`, `wp_date()` sont interdits.
- L'extension n'émet **aucun** HTML de présentation publique — inchangé, aucune ligne d'extension écrite.
- **Aucune CSS** : aucun `<style>`, aucun attribut `style=`, aucun fichier de `assets/css/` touché.
- **Aucune classe nouvelle** hors `bande--editorial` (A-3). **Aucun `class="repere"`** nulle part
  (`MASTER.md` §3.2 amendé, §16 « repère hors portée »).
- **Aucun `id`** émis par les trois gabarits, **aucun `a[href^="#"]"`** (A-5).
- Aucune URL en dur : le lien de retour passe par `home_url( '/' )` + `esc_url()`, jamais
  `site_url()` ni `get_bloginfo( 'url' )`.

**Métadonnées d'article — interdites faute de propriétaire éditorial**
- `the_post_thumbnail()`, `the_author()`, `the_category()`, `the_tags()`, `the_date()`,
  `wp_link_pages()`, `edit_post_link()`, `the_post_navigation()`, `posts_nav_link()`,
  `get_search_form()`. Aucune n'a de spécification dans `MASTER.md` ; les rendre serait de la copie
  inventée **et** le registre « template institutionnel » que borne la contrainte n° 4.

**Internationalisation**
- **Aucune** fonction de traduction : `__`, `_e`, `_x`, `_n`, `esc_html__`, `esc_html_e`,
  `esc_attr__`, `esc_attr_e`. Voir A-2. `esc_html()` / `esc_url()` / `esc_attr()` restent obligatoires.

**Échappement — le contre-interdit, à ne pas « corriger » en revue**
- `the_title()` et `the_content()` sont rendus **sans `esc_html()`**, et ce n'est pas un oubli :
  `the_title` applique `wptexturize()`, qui **produit des entités HTML** (`&#8217;`), qu'un
  `esc_html()` ré-encoderait — la première page éditoriale française afficherait littéralement
  `Mentions l&#8217;égales`. `the_content()` est un flux HTML filtré par construction ; l'échapper
  viderait la page. Le seul `esc_*` obligatoire des trois gabarits est
  `esc_url( home_url( '/' ) )` dans `404.php`.

**Structure — la règle la plus facile à casser par mégarde**
- **`the_content()` est appelé directement à l'intérieur de `.bande__contenu`, sans aucune
  enveloppe.** `layout.css` pose le rythme vertical en `:where(.bande__contenu, .pied__contenu) > * + *`
  (l. 134), l'espacement renforcé avant les titres en `> * + :where(h2, h3)` (l. 138) et la **mesure
  68ch de `MASTER.md` §7.3** en `> p` (l. 143) — **les trois en enfant direct**. Une `<div>`, un
  `<article>` ou un `entry-content` autour du contenu fait perdre **les trois règles d'un coup**,
  silencieusement.

---

## Plan de titres — un seul `h1` par page

| Cible | `<title>` | `h1` unique | Origine | `h2` / `h3` |
|---|---|---|---|---|
| `/sample-page/` (`page.php`) | `wp_get_document_title()` | titre de la page | `the_title()`, une seule itération possible | ceux de `the_content()` — jamais émis par le gabarit |
| `/hello-world/` (`index.php`, branche singulière) | `wp_get_document_title()` | titre de l'article | idem | idem |
| `/?p=99999999` (`404.php`) | `wp_get_document_title()` → chaîne du cœur, distincte | `Cette adresse ne correspond à aucune page de ce site.` | littéral du gabarit (D-7) | aucun |
| archives / recherche (`index.php`, branche non singulière) | `wp_get_document_title()` | **aucun** — trou assumé (A-4) | — | aucun |
| accueil (`front-page.php`, **non touché**) | inchangé | phrase de synthèse de l'ardoise, `#titre-du-jour` | contrat #5 | `Légende de la carte`, `La liste du jour` |

**Le nom du site dans la barre reste un `<p class="barre__nom">`, jamais un `h1`** — cohérence avec
le plan de titres de l'accueil du contrat #5. Vérifié par lecture de `templates/header.php` l. 55-58,
fichier non touché par cette issue.

**Aucun des trois gabarits n'émet de `h2`.** Les seuls `h2` possibles viennent de `the_content()` et
appartiennent donc à la chaîne `contenu` (F-6).

### L'unicité du `h1` est structurelle, pas surveillée

`if ( have_posts() ) : the_post();` — **jamais `while`**. Avec `while`, le nombre de `h1` émis est
une fonction de `$wp_query->post_count`, valeur que le gabarit ne contrôle pas et qu'un
`pre_get_posts` d'extension ou un `posts_per_page` filtré peut porter à 2 ou à 25. Avec
`if` + un seul `the_post()`, ce nombre est une **constante du code source** : 0 ou 1, jamais plus,
quelle que soit la requête. Sur une requête singulière les deux formes sont équivalentes : la
garantie ne coûte rien.

Deuxième moitié de la garantie : `index.php` n'émet son bloc que sous `is_singular()`, et
`WP_Query::set_404()` réinitialise `is_single` / `is_page` / `is_attachment` — `is_singular()` et
`is_404()` ne peuvent donc pas être vrais ensemble, et `index.php` et `404.php` ne peuvent pas
émettre chacun un `h1` sur la même requête.

Troisième source possible, écartée par lecture : `templates/header.php` (nom du site en `<p>`) et
`templates/footer.php` (aucun titre).

---

## Non-régression — exigence explicite du propriétaire pour ce lot

### L'accueil reste servi par `front-page.php`, et rien ne peut le lui prendre

Vérifié dans le provisionnement **et** dans la hiérarchie, pas supposé :

1. `docker/provision/provision.sh` ne positionne ni `show_on_front`, ni `page_on_front`, ni
   `page_for_posts` — seulement `siteurl`, `home`, `WPLANG`, `timezone_string`. `show_on_front`
   reste donc à `posts`.
2. `wp-includes/template-loader.php` évalue `is_404` → `is_search` → **`is_front_page`** → `is_home`
   → … → `is_page`. **`is_front_page()` est évalué avant `is_home()` et avant `is_page()`.**
3. Donc, dans les **deux** configurations possibles : en `show_on_front = 'posts'`, `/` est
   `is_home()` **et** `is_front_page()` → `front-page.php` ; en `show_on_front = 'page'`, `/` est
   `is_page()` **et** `is_front_page()` → la branche antérieure gagne, **`page.php` n'est jamais
   consulté sur l'accueil**.

**Créer `page.php` ne peut pas voler l'accueil. Créer `404.php` non plus** (`is_404` est une branche
distincte, et `is_404()` est faux sur `/`). Aucun `home.php` n'est créé — le seul fichier qui
pourrait théoriquement entrer dans la course.

**Constat consigné, sans rapport avec cette issue** : en `show_on_front = 'posts'`, `is_front_page()`
est vrai **aussi sur `/page/2/`** — `front-page.php` sert donc déjà les pages 2+ de l'index
d'articles, avant comme après #24. Ce n'est pas une régression de cette issue.

### Ce que la correction d'`index.php` **retire** de l'existant

Tableau exhaustif exigé par le propriétaire. **Aucune capacité livrée n'est perdue** : le seul
contenu supprimé est du texte de chantier ; tout le reste est remplacé par un **sur-ensemble strict**.

| Lignes retirées | Ce qui est retiré | Ce qui le remplace | Perte ? |
|---|---|---|---|
| 2-11 | docbloc « squelette d'amorçage » | docbloc décrivant le repli réel | non |
| 18-22 | `<!DOCTYPE>`, `<html lang>`, `<head>`, `charset`, `viewport` | `templates/header.php` l. 18-23 | non — identique |
| **23** | **`<title><?php bloginfo( 'name' ); ?></title>`** | header l. 25, `wp_get_document_title()` | **gain** — titre unique par page |
| 24 | `wp_head()` | header l. 26 | non |
| 26 | `<body <?php body_class(); ?>>` | header l. 28 **+ `wp_body_open()`** l. 29 | **gain** — le point d'accroche n'était pas appelé |
| — | *(rien)* | header l. 46-51, lien d'évitement « Aller au contenu » | **gain** — absent hors accueil |
| — | *(rien)* | header l. 53-65, barre haute et emplacement de menu `principal` | **gain** |
| **27** | **`<main id="contenu-principal">` sans `tabindex`** | header l. 68, **avec `tabindex="-1"`** | **gain** — le lien d'évitement déplace enfin le focus, pas seulement le défilement |
| **28** | **`<p>Thème Massifs — squelette d'amorçage.</p>` en `esc_html_e()`** | `h1` + `the_content()`, ou rien | **gain** — copie de chantier retirée du public |
| 29-32 | `</main>`, `wp_footer()`, `</body></html>` | `templates/footer.php` l. 27, 62-64 | non |
| — | *(rien)* | footer l. 29-60, pied et attributions | gain de structure, **avec la réserve F-3** |

### Preuve de non-régression par la mesure, pas par le raisonnement

`node tests/rendu/recette-rendu.mjs --filtre=sans-js` et `--filtre=perime` affirment des empreintes
que **seul** `front-page.php` produit : 25 lignes de `#liste tbody tr`, `.ardoise__fraicheur`,
`#legende`, `.bandeau-non-officialite`, le `h1` de synthèse mot pour mot, l'état « information non
disponible ». Si `index.php` ou `page.php` prenait la main sur `/`, elles passeraient au rouge.

---

## Recette — ce qui doit être exécuté et ce qui doit en sortir

Harnais `tests/rendu/recette-rendu.mjs` : **hors empreinte, LECTURE SEULE**. Ni retouché, ni élargi,
ni affaibli. Stack levée requise (`bash docker/up.sh`, port `3002`).

| # | Commande | Attendu |
|---|---|---|
| 0 | ligne de base **avant** correction : `--filtre=structure` | 3 rouges « exactement un h1 exposé » — à relever et à citer dans le rapport |
| 1 | `node tests/rendu/recette-rendu.mjs --filtre=structure` | 0 rouge. L'assertion visée est l. 455, `egal( 1, structure.h1, … )`, sur les cibles `/hello-world/`, `/sample-page/`, `/?p=99999999` |
| 2 | `--filtre=tierce` | 0 rouge — **c'est la preuve mesurable de l'interdit `get_avatar()`/`comments_template()`** |
| 3 | `--filtre=a11y` · `--filtre=mobile` | 0 rouge |
| 4 | `--filtre=sans-js` · `--filtre=perime` | 0 rouge — non-régression de l'accueil |
| 5 | passe complète `node tests/rendu/recette-rendu.mjs` | 0 rouge |
| 6 | codes HTTP hors harnais : `/hello-world/`, `/sample-page/`, `/`, `/?p=99999999` | `200`, `200`, `200`, `404` |
| 7 | `wp option get show_on_front` · `page_on_front` | `posts` · `0` |

**Le point 6 n'est pas du zèle.** `s03` relève le code HTTP (l. 243) mais ne l'affirme jamais : si
`/hello-world/` répondait 404, la page serait servie par `404.php` et l'assertion « un seul h1 »
passerait **pour la mauvaise raison**. Un vert obtenu ainsi est un faux vert.

**Si un slug est francisé** (`bonjour-tout-le-monde`, `page-d-exemple`), `dev-front-cms` **ne corrige
rien** : il ne crée pas de contenu, ne renomme aucun slug — ce serait un vert obtenu par une
modification de base non versionnée, effacée au prochain `docker/reset.sh` — et ne touche pas le
harnais. Il **rapporte** (F-5).

### Assertions aujourd'hui muettes qui s'activent, et ce qu'elles exigent

| Assertion | Muette parce que | S'activera et exigera | Prévision |
|---|---|---|---|
| « le focus du premier lien est visible » (l. 474-488) | `document.querySelector('a')` retourne `null` — `index.php` n'émet aucun lien | le 1ᵉʳ `<a>` porte un `outline` non nul ou un `box-shadow` au focus | **vert** : le 1ᵉʳ `<a>` devient `.lien-evitement`, que `layout.css` l. 446-453 stylise sur `:focus` (et non `:focus-visible`) — le focus programmatique du test suffit |
| « aucun id en double » | `#contenu-principal` était le seul `id` | l'unicité de `#contenu-principal` | **vert** si l'interdit « aucun `<main>` réémis » tient |
| « aucun lien d'évitement vers une ancre inexistante » | aucun `<a>` dans le document | tout `a[href^="#"]` résout — le scénario balaie **tous** les liens d'ancre, pas seulement `.lien-evitement` | **vert** : `#contenu-principal` seul ; `#liste` est gardé par `is_front_page()` dans `templates/header.php` (arbitrage A-26 du contrat #5), donc **jamais rendu ici** |
| `s01` origines tierces, 5 pages | page quasi vide | aucune origine hors `localhost:3002` | **vert si et seulement si** l'interdit commentaires/avatar tient |

**`page-has-heading-one` d'axe-core n'est pas une quatrième assertion rouge** : `s08` ne fait échouer
que `impact === 'critical' || 'serious'` (l. 709), et cette règle est `moderate` — elle ressort en
note. Il n'y a donc rien à « faire passer » de ce côté, seulement à ne pas y **introduire** de
violation `serious` ou `critical`.

---

## Arbitrages

Décisions du lead. Chacune tranche un désaccord, une ambiguïté ou un trou constaté.

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-1** | Copie de la 404 : `MASTER.md` §11.3 est une liste **fermée** (§7.3, §16) et ne couvre aucune page 404. Précédent : la phrase « zéro cookie », refusée par la chaîne #23. `brainstorm-cms` la classait **question bloquante** | **Chaîne non arrêtée. Je tranche, et je déclare la dette.** `h1` = « Cette adresse ne correspond à aucune page de ce site. » ; lien = « Aller à l’accueil ». Inscrites **non ratifiées** au présent contrat, demande F-1 portée à `lead-design-cms` | Ce n'est **pas un fait de domaine** : aucun libellé de niveau, aucune couleur, aucune consigne préfectorale n'est en jeu — la règle « ne jamais inventer un fait de domaine » ne mord pas ici. La checklist de l'issue **commande explicitement** cette copie, et l'alternative « ne rien écrire » est exactement le défaut que l'issue corrige : une 404 sans `h1`. Les deux chaînes respectent §11.1 (voix active, sujet explicite, aucune excuse, aucun superlatif) et §11.2 (aucun terme du vocabulaire fixe détourné). **Écartées** : « Page introuvable. » (nominale, viole §11.1 règle 1) ; toute formule employant « publier » ou « carte » (termes réservés §11.2) ; toute excuse (« Oups », « Désolé », §11.1 règle 3). Arrêter trois chaînes parallèles sur une phrase de 404 serait disproportionné — **mais la faire passer en silence serait pire**, d'où l'inscription explicite |
| **A-1 bis** | Glyphe de l'apostrophe du libellé de lien. `leaddev-front-cms` a **contesté** ma formulation initiale en U+0027 | **Objection retenue : U+2019.** « Aller à l’accueil » | L'arbitrage **A-15 du contrat #5** est gelé et explicite : U+2019 pour toute prose rédigée par le thème ; seules les chaînes officielles du §11.4 sont reproduites octet pour octet. Ce libellé est de la prose du thème. Aligne aussi sur `Aujourd’hui, …` de `front-page.php` l. 54. Le leaddev avait raison de ne pas l'appliquer en silence |
| **A-2** | Divergence i18n **O-1** du contrat #6 : `index.php` employait `esc_html_e()` quand les quatre autres gabarits n'emploient aucune fonction de traduction | **Fermée dans le sens de l'arbitrage I du contrat #6 : aucune fonction de traduction**, dans les trois gabarits | `index.php` était le **dernier** fichier porteur de la divergence, et il est dans l'empreinte : c'est la seule occasion de fermer O-1 sans toucher un fichier d'autrui. Brief §2 « français uniquement », §13 « multilingue hors périmètre ». Surtout : `esc_html_e( …, 'massifs' )` **sans** `load_theme_textdomain()` ni répertoire `languages/` est un **no-op qui se fait passer pour de l'i18n** — pire que son absence. `Text Domain: massifs` reste dans `style.css` (hors empreinte, et l'en-tête reste valide) |
| **A-3** | Crochet de classe pour la feuille éditoriale future : `body_class()` suffit-il ? | **`bande--editorial`** émis sur les trois gabarits, déclaré « crochet offert, consommé par personne aujourd'hui, sémantique cédée à la feuille future » | `body.page` seul ne suffit pas : il distingue les pages mais mélange la 404 et les articles servis par `index.php`, dont les `h1` relèvent du même traitement typographique. Précédent dans le fichier voisin **gelé** : `front-page.php` l. 258 et 264 émettent `.bande--legende` et `.bande--liste`, que `layout.css` ne consomme pas — un crochet inconsommé est une forme déjà admise dans ce thème. Coût réel : zéro octet de CSS. **Réserve du leaddev consignée** : la 404 n'est pas une page éditoriale au sens du §7.3 ; la feuille future tranchera si elle veut un `bande--404` distinct |
| **A-4** | `h1` des archives et de la recherche. Trois issues, aucune gratuite : chaîne du cœur (`get_the_archive_title()`), 404 forcée, ou rien | **Rien. `index.php` n'émet son bloc que sous `is_singular()`.** Les archives et la recherche servent le chrome complet **sans `h1`** — trou **déclaré**, non maquillé | `get_the_archive_title()` (« Catégorie : Non classé ») et « Résultats de recherche pour … » sont du registre **« template institutionnel »** que borne la contrainte non négociable n° 4, et de la copie visible sans propriétaire au §11.3. Le site n'a **aucun blog au périmètre** (brief §5.1 : quatre pages ; §13). **Un `h1` inventé sur une archive serait pire que le défaut corrigé.** La vraie fermeture est de faire disparaître ces contextes (`pre_get_posts` / `template_redirect`), ce qui s'écrit dans `functions.php` — **hors empreinte** : demande F-4(a). Aucun scénario de recette ne parcourt une URL non singulière : ce trou n'est masqué par aucun vert |
| **A-5** | `id` émis par les gabarits | **Aucun.** Aucun `<section id>`, aucun `id` de titre, aucun `aria-labelledby`, aucun `tabindex`, aucun `a[href^="#"]` | Le seul `id` du document reste `#contenu-principal` (`templates/header.php` l. 68), cible du seul lien d'évitement rendu sur ces pages. `s03` balaie **tous** les `a[href^="#"]` : chaque ancre émise est une occasion de doublon ou de lien mort, pour zéro besoin actuel. Une `<section>` sans nom accessible est exposée `generic`, donc **aucun landmark vide** n'est créé — même raison que l'arbitrage A-17 du contrat #5 pour la bande `#carte` |
| **A-6** | `404.php` doit-il ouvrir une Boucle ? | **Non. `the_post()`, `the_title()` et `have_posts()` y sont interdits** | `WP_Query::set_404()` réinitialise tous les drapeaux, mais `$post` global peut porter un reliquat : c'est le **seul chemin** par lequel le titre d'un autre document pourrait s'afficher comme titre de la 404. Interdit par construction plutôt que par vigilance |
| **A-7** | Mutualisation de l'armature (~8 lignes répétées ×3). `brainstorm-cms` proposait un `require` de gabarit à gabarit, seul véhicule possible (`templates/**` et `functions.php` hors empreinte) | **Refusée. Trois gabarits autonomes**, chacun lisible de bout en bout | Un `require` de gabarit à gabarit court-circuite la hiérarchie WordPress et le filtre `template_include` ; personne ne s'attend à trouver le rendu d'une 404 dans `index.php` ; et un futur `single.php` créé par une autre chaîne romprait l'aiguillage **sans que rien ne le signale**. Huit lignes dupliquées valent mieux qu'un anti-idiome. Écartée aussi l'option « ne pas créer `page.php` » : le brief §5.1 **garantit** trois pages éditoriales à venir, et `page.php` est leur point d'accroche naturel |
| **A-8** | Dette typographique : `MASTER.md` §5.1 et §7.3 veulent les titres éditoriaux en famille de **texte**, or le sélecteur nu `h1, h2, h3` de `layout.css` l. 90-96 les rend en famille d'**affichage** ; §16 en fait un défaut bloquant | **Aucune CSS écrite, aucune surcharge inline. Dette déclarée, demande F-2 portée à `lead-design-cms` pour une entrée au §17** | Le défaut **n'est pas causé par `page.php`** : il naît de l'émission d'un `h1` hors accueil, que l'issue existe pour produire — ne pas créer `page.php` ne l'éviterait pas, corriger `index.php` suffit à le déclencher. §5.1 borne (b) l'a **délibérément accepté** et **nomme le propriétaire du correctif** : « une page éditoriale future retire la famille d'affichage **dans sa propre feuille** ». Ce correctif exige `assets/css/editorial.css` **et** un handle dans `functions.php` — **deux fichiers hors empreinte**, donc une issue à part. Le §17 existe précisément pour que `review-cms` ne compte pas ce point deux fois |
| **A-9** | `templates/footer.php` imprime les attributions **inconditionnellement**, et sera désormais servi sur trois pages qui n'affichent **aucune** donnée | **Accepté et signalé, non corrigé** : `templates/**` est hors empreinte. Demande F-3 | Contredit le docbloc du fichier lui-même (« créditer une source dont aucune donnée n'est affichée est une affirmation fausse »). **Défaut de vérité, pas défaut de rendu** — aucune assertion de la recette ne le relève. Ce n'est pas une régression de ce qui était livré (`front-page.php` est inchangé) mais une **surface nouvelle**, créée par le fait qu'`index.php` inclut désormais le pied. Le corriger exigerait d'écrire dans le fichier d'une autre chaîne |
| **A-10** | `h1` saisi dans `the_content()` par un rédacteur — le gabarit ne peut pas l'empêcher sans filtre, et un filtre s'écrit dans `functions.php` | **Aucun filtre, aucun `wp_kses`, aucune réécriture du contenu.** Règle éditoriale opposable à la chaîne `contenu` (F-6) + demande de filtre à la chaîne `functions.php` (F-4b) | Réécrire le contenu depuis un gabarit serait une règle métier éditoriale dans la couche présentation. Sur les deux cibles du test, le contenu par défaut de WordPress ne porte **aucun titre** — mais cela se **vérifie par la mesure**, pas par la mémoire : le tag Docker n'est pas épinglé (arbitrage A-20 du contrat #5, déjà matérialisé une fois). Si `s03` rend `2`, la cause est le contenu, pas le gabarit |

### Deux imprécisions relevées par `refacto-cms` après implémentation — décidées, non corrigées

`refacto-cms` n'a trouvé **aucun défaut corrigeable** dans les trois fichiers et n'a donc appliqué
aucune correction. Il m'a renvoyé deux points, qui relèvent du contrat et non du refacto. Je les
tranche ici plutôt que de rouvrir le code :

| # | Constat | Décision |
|---|---|---|
| **A-11** | `index.php` : la garde est `is_singular() && have_posts()`, donc la branche `else` couvre **aussi** le cas *singulier sans post*, alors que son commentaire et son message journalisé disent « contexte non singulier ». Écart entre le libellé et la condition | **Laissé tel quel, imprécision consignée.** Le cas est quasi inatteignable : `WP::handle_404()` bascule en 404 **avant** le gabarit quand une requête singulière ne rapporte aucun post — la branche ne peut donc être atteinte que par un `pre_get_posts` pathologique. Reformuler le message changerait une **sortie** (le journal sous `WP_DEBUG`), et découper la condition en deux branches changerait la structure du fichier : ni l'un ni l'autre n'est du refacto à comportement constant. À reprendre au prochain dégel de ce fichier |
| **A-12** | Asymétrie de diagnostic : `index.php` émet un commentaire HTML `<!-- massifs: … -->` **et** journalise ; `page.php`, dans sa branche symétrique sans post, journalise **seulement** | **Voulue, et voici pourquoi.** Les deux situations ne sont pas de même nature : la branche non singulière d'`index.php` est un **état attendu et atteignable** (toute archive, toute recherche), qu'il est utile d'observer dans le HTML en recette — c'est le motif maison de `massifs_partie()` (`functions.php` l. 100). La branche vide de `page.php` est **pathologique** : elle ne survient que si un tiers a cassé la requête principale, et n'a rien à observer en recette. Aligner l'une sur l'autre changerait le HTML servi d'une des deux pages |

---

## Dépendances reportées — hors empreinte, à ordonnancer

| # | Destinataire | Attendu | Motif |
|---|---|---|---|
| **F-1** | `lead-design-cms` (propriétaire de `MASTER.md`) | Faire entrer au **§11.3** les deux chaînes de la 404, ou en fournir d'autres mot pour mot | §11.3 est une liste fermée qui ne couvre aucune page 404. Les chaînes sont livrées **non ratifiées** (A-1) |
| **F-2** | `lead-design-cms` + une chaîne CSS future | Entrée au **§17** actant l'écart, puis `assets/css/editorial.css` + son handle dans `functions.php` | Les `h1` hors accueil sont rendus en famille d'affichage, contre §5.1 et §7.3 (A-8). Deux fichiers hors empreinte |
| **F-3** | propriétaire de `templates/**` | Garder les deux blocs d'attribution de `templates/footer.php` l. 43-57 sur la présence effective d'une couche de données | Les attributions préfecture et DDTM s'affichent désormais sur trois pages sans donnée (A-9) |
| **F-4** | propriétaire de `functions.php` (chaîne #25 aujourd'hui) | (a) neutraliser archives, recherche et flux — le site n'a pas de blog : c'est la **vraie** fermeture du trou A-4 ; (b) filtre rétrogradant un `h1` saisi dans `the_content()`, avec journalisation | Les deux exigent `functions.php`, hors empreinte de #24 |
| **F-5** | orchestrateur / `docker-cms` | Si `/hello-world/` ou `/sample-page/` ne répond pas `200` (slugs francisés), corriger le **provisionnement ou le harnais**, jamais le thème | Sinon `s03` passerait via `404.php`, donc pour la mauvaise raison |
| **F-6** | chaîne `contenu` | Règles éditoriales opposables pour « La démarche », « Accessibilité », « Mentions légales » : **aucun `h1` dans le corps** (la hiérarchie commence à `h2`) · **blocs plats uniquement** — un bloc conteneur (`wp:group`, `wp:columns`, `wp:cover`) enveloppe ses enfants dans une `<div>` et leur fait perdre **les 68ch et le rythme vertical en silence** · aucune ancre en double, aucun lien `#…` non résolu, jamais l'ancre `contenu-principal` · **aucun contenu protégé par mot de passe** (le formulaire du cœur pose un cookie `wp-postpass_`, contre « zéro cookie côté public », brief §2 et §9) · **aucun taux ni qualificatif de conformité RGAA** (`MASTER.md` §16) | Ces risques sont des risques de **rédaction**, pas de gabarit. Rappel connexe : `wp-block-library` est retiré du front (`functions.php` l. 297-311), donc tout bloc dont la mise en page dépend de cette feuille rend nu |

---

## Hors périmètre et **sans propriétaire** après cette issue — à attribuer

`single.php`, `archive.php`, `search.php`, `attachment.php` (aucun n'est nécessaire tant que
`index.php` fait office de repli, mais aucun n'a de propriétaire) · les archives et la recherche
elles-mêmes (A-4, F-4a) · la feuille éditoriale `assets/css/editorial.css` (A-8, F-2) · le contenu
des quatre pages du brief §5.1 · `print.css` face aux pages éditoriales · `robots.txt` et
l'indexation · Gravatar / `show_avatars` en session connectée (hérité du contrat #5, arbitrage A-12,
**toujours ouvert**) · `wp_generator` et le durcissement §9.
