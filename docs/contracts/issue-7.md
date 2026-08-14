# Contrat d'interface — Issue #7 — Afficher la carte interactive des massifs avec leurs statuts du jour

**Gelé le 13 août 2026** par `lead-issue-cms` (chaîne #7), à partir du plan de `leaddev-front-cms` et de
l'audit serveur de `leaddev-back-cms`, tous deux produits en aveugle l'un de l'autre.

> Ce document est **contraignant** à partir de ce point. Une divergence constatée en revue est un défaut,
> pas une variante. Les treize arbitrages du §7 sont les points où les deux plans ne disaient pas la même
> chose ; ils tranchent, ils ne résument pas.

---

## 1. Approche retenue

**Leaflet 1.9.4 vendorisé + îlot JSON deux jours rendu par `templates/parts/carte.php`.**

`carte.php` appelle les fonctions de lecture PHP de l'extension et sérialise le résultat dans un
`<script type="application/json">`. `assets/js/carte/carte.js` lit cet îlot **une seule fois** et récupère
la géométrie par un `fetch` sur l'URL statique de `massifs_geometrie()`.

**Aucune route REST n'est appelée par `assets/js/carte/**`.** Zéro couplage avec la chaîne #8.

**Motif, corrigé par l'audit serveur** — et il faut le lire, parce qu'il n'est pas celui que le brainstorm
avait avancé. Le brainstorm invoquait « le même cache, invalidé par `massifs_statuts_publies` ». **Ce cache
n'existe pas** : vérifié dans le code, il n'y a ni `set_transient`, ni `wp_cache_set`, ni mémoïsation dans
`includes/domain/statuts/`, et le hook `massifs_statuts_publies` (`api.php` l. 496) **n'a aucun abonné** —
il n'est de surcroît émis que par la voie **lot** (`massifs_enregistrer_statuts()`), jamais par la voie
unitaire. La garantie de source unique ne vient donc **pas** d'un cache partagé : elle vient de ce que
**la carte et la liste lisent la même base dans la même requête HTTP**, sans intermédiaire qui puisse
vieillir séparément. Une route REST, elle, aurait son propre cycle et pourrait diverger.

**Corollaire de contrat : l'îlot n'est jamais mis en cache.** Ni transient, ni `wp_cache_*`, ni fichier.

---

## 2. Empreinte fichiers de la chaîne #7

Autorisé en écriture, et **rien d'autre** :

| Chemin | Propriétaire | Nature |
|---|---|---|
| `wp-content/themes/massifs/assets/vendor/leaflet/**` | `dev-front-cms` | neuf |
| `wp-content/themes/massifs/assets/js/carte/carte.js` | `dev-front-cms` | neuf |
| `wp-content/themes/massifs/templates/parts/carte.php` | `dev-front-cms` | neuf |
| `wp-content/themes/massifs/assets/css/carte.css` | `dev-ux-cms` | **neuf** — élargissement d'empreinte, §7 A-0 |
| `wp-content/themes/massifs/front-page.php` | `dev-front-cms` | **UNE ligne**, §7 A-0 |
| `wp-content/themes/massifs/functions.php` | `dev-front-cms` | **UNE entrée** de tableau, §7 A-0 |
| `docs/contracts/issue-7.md` | `lead-issue-cms` | ce document |

Les deux derniers fichiers sont **partagés avec les chaînes sœurs sur le même arbre de travail, sans
isolation**. Toute écriture y est chirurgicale : jamais une réécriture de fichier, jamais un `Write`, un
`Edit` ciblé et rien d'autre.

Interdits en écriture, sans exception : `liste-statuts.php`, `legende.php`, `etats-vides.php`,
`bandeau-non-officialite.php`, `header.php`, `footer.php`, `layout.css`, `composants.css`, `print.css`,
`tokens.css`, `style.css`, `assets/fonts/**`, `templates/parts/carte-secours.php` (chaîne #9),
`assets/img/**` (chaîne #9), `includes/ingest/tuiles/**` (chaîne #9), `includes/rest/**` (chaîne #8),
et **la totalité de `wp-content/plugins/massifs-core/`**.

---

## 3. Fonctions de lecture consommées par `carte.php`

Toutes existent et sont vérifiées dans le code. **Aucune n'est à écrire.**

```php
massifs_referentiel()                              // libellés + ordre (déjà trié par `tri` — ne jamais retrier)
massifs_jour_courant() · massifs_jour_suivant()    // SEULE source légitime du jour
massifs_statuts_du_jour( array $codes, string $jour )    // × 2 (jour courant, jour suivant)
massifs_synthese_du_jour( array $codes, string $jour )   // × 1 — jour SUIVANT uniquement (§7 A-3)
massifs_saison( string $jour )                     // `prochaine_ouverture` UNIQUEMENT
massifs_horodatage( string $instant_iso_utc )      // date_courte / heure / date_longue
massifs_legende()                                  // `zapef_note` UNIQUEMENT
massifs_attribution_statuts()                      // `texte` + `carte_officielle_url`
massifs_geometrie()                                // `url` + `zoom_max` — JAMAIS ouverte en PHP
massifs_emprise()                                  // `bbox` + `zoom_max`
massifs_fond_de_carte()                            // SI ELLE EXISTE — garde `function_exists`, chaîne #9
```

**`massifs_fraicheur()` n'est PAS appelée par `carte.php`** (§7 A-4). **`massifs_statut_du_jour()` n'est
jamais appelée** — un appel par massif est un N+1 garanti (25 requêtes).

### 3.1 Formes de retour qui font foi

**`massifs_statuts_du_jour()`** → tableau indexé par code normalisé, une entrée par code scalaire fourni,
dans l'ordre de première apparition. Clés de chaque entrée :

| Clé | Type | Renseignée |
|---|---|---|
| `massif_code` | `string` | toujours |
| `etat` | `string` | toujours — `disponible` \| `indisponible` \| `hors_saison` \| `non_encore_publie` |
| `jour_validite` | `string` `YYYY-MM-DD` | toujours — **le jour DEMANDÉ** |
| `niveau` | `array\|null` | non-`null` **SSI** `etat === 'disponible'` |
| `zapef` | `array\|null` | non-`null` SSI `disponible` **ET** la ligne portait un `zapef_cle` |
| `source` | `string\|null` | `recuperation_officielle` \| `saisie_manuelle` ; `null` hors `disponible` |
| `auteur_id` | `int\|null` | **jamais lu par le thème** (§5 interdit 12) |
| `publie_prefecture_le` | `string\|null` | ISO 8601 UTC — **`null` possible même en `disponible`** |
| `enregistre_le` | `string\|null` | ISO 8601 UTC — **seul instant garanti non-`null` quand `disponible`** |
| `statut_id` | `int\|null` | **jamais lu par le thème** |

Sous-tableau `niveau` / `zapef` : `cle`, `libelle`, `consigne` (`''` en permanence), `severite`, `motif`,
`jeton_css`, `jeton_encre_css`, `rang`, `total`. **Le thème n'en lit que `cle` et `libelle`.**

**`massifs_geometrie()`** → `disponible`, `url` (avec jeton `?v=`), `version`, `sha256`, `octets`,
`format`, `zoom_max`. Toutes les clés sont toujours présentes ; jamais d'`isset()`.
`disponible === true` atteste **la présence des métadonnées, jamais celle du fichier** — le `fetch` doit
traiter son propre échec.

**Artefact de géométrie** : `FeatureCollection`, 25 `Feature`, `geometry.type === "MultiPolygon"`,
EPSG:4326 `[lon, lat]`. Propriétés d'une `Feature` : **`{"code": "…"}` et rien d'autre**.

> **Clé de jointure, unique et sans exception :**
> `feature.properties.code` === `massif_code` === clé de `massifs_referentiel()` === clé de
> `massifs_statuts_du_jour()`. **Ni `source.gid`, ni `identifiant_prefecture`** — ce sont des clés de
> transport, jamais des clés de jointure, jamais affichées.

**`massifs_emprise()`** → `bbox {ouest, sud, est, nord}`, `centre {lon, lat}`, `zoom_max` (= **11**).
Leaflet attend `[[sud, ouest], [nord, est]]` : **la conversion appartient au front**, et aucune coordonnée
n'est écrite en dur nulle part.

---

## 4. L'îlot JSON — forme exacte, clé par clé

`<script type="application/json" id="carte-donnees">`, **une seule occurrence**, à l'intérieur de `.carte`.

```jsonc
{
  "version": 1,                          // int — si ≠ 1, carte.js retire la racine et s'arrête

  "jour_courant": "AAAA-MM-JJ",          // massifs_jour_courant()
  "jour_suivant": "AAAA-MM-JJ",          // massifs_jour_suivant()

  "ordre": ["alpilles", "arbois", "…"],  // list<string>, 25 codes, ORDRE DU RÉFÉRENTIEL.
                                         // Explicite — jamais déduit de l'ordre des clés d'un objet.

  "massifs": {
    "<code>": { "libelle": "Sainte-Victoire" }   // SEUL champ affichable du référentiel
  },

  "jours": {                             // EXACTEMENT DEUX clés : jour_courant et jour_suivant.
    "<AAAA-MM-JJ>": {                    // Le client ne peut basculer que vers ces deux-là.
      "<code>": {
        "etat":      "disponible" | "indisponible" | "hors_saison" | "non_encore_publie",
        "niveau":    { "cle": "autorise"|"interdit", "libelle": "Accès au massif autorisé" } | null,
        "zapef":     { "cle": "autorise"|"interdit", "libelle": "Accès à la ZAPEF* autorisé" } | null,
        "fraicheur": "Relevé le 11 août 2026 à 19 h 04" | null    // §7 A-4 — pré-formaté par PHP
      }
    }
  },

  "geometrie": { "url": "…/massifs-13.geometrie.json?v=744fba53",
                 "format": "geojson", "zoom_max": 11 },

  "emprise":   { "bbox": { "ouest": f, "sud": f, "est": f, "nord": f },
                 "zoom_max": 11 },

  "fond":      { "url_modele": "/tiles/{z}/{x}/{y}.png",
                 "format": "raster", "zoom_min": int, "zoom_max": int }
                                         // CLÉ ENTIÈREMENT ABSENTE si aucun fond n'est exposé (§6)
}
```

**Ce que l'îlot ne contient jamais** : aucune valeur hexadécimale · aucun `jeton_css` ni `jeton_encre_css`
· aucun `severite`, `rang`, `total`, `consigne`, `statut_id`, `auteur_id`, `niveau_source_brut`,
`procedure_source` · aucune coordonnée de massif (elles sont dans le fichier de géométrie) · aucune phrase
rédigée dépendante du jour.

**Les libellés de niveau sont dénormalisés** (répétés par jour et par massif) : ≈ 10,5 Ko bruts,
≈ 2 Ko transférés. Choisi contre une table de légende + clés parce qu'une clé manquante y produirait un
**libellé vide** au lieu d'un échec bruyant, et parce que c'est la forme même du contrat #3.

### 4.1 Sérialisation — arbitré

```php
wp_json_encode( $donnees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
```

- **`JSON_HEX_TAG` est structurel et obligatoire** : il échappe `<` en `<`, donc `</script>` ne peut
  pas apparaître dans une valeur et refermer la balise. C'est le seul drapeau porteur de sécurité.
- `JSON_HEX_APOS` et `JSON_HEX_QUOT` ne sont **pas** requis : le contenu d'un `<script>` est du *raw text*,
  pas une valeur d'attribut. Les exiger ne protégerait de rien et gonflerait l'îlot.
- `JSON_UNESCAPED_UNICODE` et `JSON_UNESCAPED_SLASHES` sont **autorisés** : ils ne changent que la taille
  en octets, jamais la correction. Avec ou sans, `JSON.parse` restitue U+2019 exactement.
- **`esc_html()` n'est JAMAIS appliqué à l'îlot.** Le contenu d'un `<script>` est du *raw text* : les
  entités n'y sont pas décodées, et `esc_html` produirait un JSON corrompu que `JSON.parse` refuserait.
  C'est la **seule** sortie du thème qui ne s'échappe pas en HTML ; elle porte un commentaire qui le dit.

---

## 5. Ce que le thème rend, et ce qu'il ne compose jamais

### 5.1 Chaînes fournies par le serveur — recopiées par `textContent` / `esc_html()`, jamais rédigées

| Chaîne | Origine exacte |
|---|---|
| `Accès au massif autorisé` / `Accès au massif interdit` | `statut['niveau']['libelle']` |
| `Accès à la ZAPEF* autorisé` / `Accès à la ZAPEF* interdite` | `statut['zapef']['libelle']` |
| `*ZAPEF : Zones d’Accueil du Public en Forêt` (**U+2019**) | `massifs_legende()['zapef_note']` |
| `D'après les publications de la préfecture des Bouches-du-Rhône` (**U+0027**) | `massifs_attribution_statuts()['texte']` |
| `https://www.risque-prevention-incendie.fr/13` | `massifs_attribution_statuts()['carte_officielle_url']` — **jamais** `massifs_legende()['source_officielle_url']` |
| `Alpilles`, `Sainte-Victoire`… | `massif['libelle']` — **jamais** `source.nom_massif` |
| `mardi 11 août 2026`, `19 h 04`, `11 août 2026` | `massifs_horodatage()` — espace insécable inclus |
| date de reprise du dispositif | `massifs_saison()['prochaine_ouverture']` |

> **Les deux apostrophes divergent volontairement** — U+2019 dans `Zones d’Accueil`, U+0027 dans
> `D'après`. C'est ce que publie la source. Ces chaînes doivent survivre à toute passe de linter, de
> `wptexturize()` et de correcteur orthographique. Toute « uniformisation typographique » est un défaut
> **bloquant** (MASTER §11.4).

### 5.2 Chaînes recopiées verbatim de MASTER par `carte.php`

Idiome déjà établi par `liste-statuts.php`, `legende.php` et `etats-vides.php` — reproduit, pas inventé.

- §11.3 · indisponible : « Information du jour non disponible. Consultez la carte officielle de la préfecture. »
- §11.3 · hors saison : « Dispositif estival inactif. Reprise le {date}. »
- §11.3 · non encore publié : « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. » (espace **insécable** avant `h`)
- §8.4 · consigne absente : « Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en vigueur fait foi. » — **sans lien** (§7 A-6)

### 5.3 Chaînes de chrome introduites par cette chaîne — autorisées, §7 A-7

`Carte des massifs` · `Jour affiché` · `Aujourd'hui` · `Demain` · `Fermer le panneau` ·
`Flèches : parcourir les massifs. Entrée : ouvrir le panneau. Échap : le fermer.`

### 5.4 Interdits — gravés

**Géométrie et jointure**
1. Ouvrir `data/massifs-13.geometrie.json` en PHP, sous quelque forme que ce soit.
2. Composer l'URL de la géométrie à la main, ou retirer son jeton `?v=`.
3. Supposer que `disponible === true` garantit la présence physique du fichier.
4. Joindre sur `source.gid` ou `identifiant_prefecture`. La clé est `massif_code` === `properties.code`.
5. Normaliser un `massif_code` (`sanitize_title`, `strtolower`, repli de diacritiques) avant appariement.
6. Dépasser `zoom_max` = 11, ou instruire son relèvement.
7. Coder une coordonnée, une bbox, un centre ou un zoom en dur — CSS, JS ou gabarit.
   **Portée précisée le 15 août 2026 (chaîne #50).** Cet interdit protège le **cadrage** — c'est bien
   pourquoi il est rangé ici, sous « Géométrie et jointure » : la vue, son centre, son emprise et son
   plafond viennent de `massifs_emprise()`, jamais du code. Il **ne vise pas** les deux **bornes de
   palier de présentation** du §9.2.a de MASTER (`10` et `11`), qui sont des seuils d'épaisseur de trait
   exprimés en entiers de zoom et que MASTER impose d'écrire ainsi — « la borne du palier est un entier
   de zoom, pas une épaisseur ». Elles **ne doivent pas** être dérivées de `donnees.emprise.zoom_max` :
   cela coupleraient une frontière de design mesurée en échelle-sol au plafond de simplification
   géométrique, deux choses qui ne coïncident aujourd'hui que par accident. **La précision restreint la
   portée de l'interdit, elle ne l'affaiblit pas** : aucun cadrage n'est autorisé en dur, et l'interdit 6
   (`zoom_max` = 11) reste entier. Voir `docs/contracts/issue-50.md`, D3 et A-50.3.

**Données et règles métier**
8. Appeler `massifs_statut_du_jour()` dans une boucle — N+1 garanti.
9. Calculer « aujourd'hui » ou « demain », en PHP **ou en JS**. `new Date()` comme source du jour est
   interdit au même titre que `date()`.
10. Formater une date en JavaScript. Toute date lisible est pré-formatée par `massifs_horodatage()`.
11. Déduire `hors_saison` de `massifs_saison()['active']` ou de `massifs_fraicheur()['dispositif_actif']`.
    L'état vient de `etat`. `massifs_saison()` ne sert qu'à `prochaine_ouverture`.
12. Lire `auteur_id`, ou le résoudre en nom via `get_userdata()` — **donnée personnelle sur une page
    publique**, interdite par le §9 du brief. Le panneau dit la **source**, jamais qui.
13. Afficher `niveau` ou `zapef` quand `etat !== 'disponible'`.
14. Rendre une **seconde** bannière de péremption — l'ardoise la rend déjà, et elle est de niveau page.
15. Conserver un statut au changement de date. Aucun `localStorage`, `sessionStorage`, paramètre d'URL
    ou `history`. Un rechargement revient au jour courant.
16. `if/else` avec branche « sinon » sur `etat`. En PHP : `match()` **sans `default`**, enveloppé d'un
    `catch ( \UnhandledMatchError )` repliant sur `indisponible` + `massifs_journaliser()`. En JS : un état
    inconnu dégrade sur le rendu `indisponible` **avec** son message, jamais sur « ni couleur ni message ».
17. Appeler `massifs_niveaux_source_autorises()`, `massifs_procedures_source_autorisees()`,
    `massifs_correspondance_source()`, `massifs_code_depuis_source()`, `massifs_source_depuis_code()`.
18. Lire `severite`, `rang` ou `total` — ils se laissent confondre avec un niveau gradué qui n'existe pas.

**Présentation**
19. Écrire une valeur hexadécimale ailleurs que dans `tokens.css`, ou **dériver une classe CSS de
    `jeton_css`**. La table état → classe est **fermée** (§8.2) : `non_encore_publie` → `--non-publie` est
    une discontinuité volontaire qu'aucune transformation automatique ne produit.
20. Poser du texte sur un aplat de statut, sur la carte comme ailleurs. Corollaire carte : les étiquettes
    du fond vivent **sous** les polygones, et tout chrome flottant repose sur un aplat opaque `--c-calcaire`.
21. Rendre une légende sur la carte — `parts/legende.php` est déjà dans la page (MASTER §8.5).
22. Rendre un jalon ZAPEF sur la carte — la géométrie des points est `OUVERT` (MASTER §4.1.e).
23. Poser un `L.Popup`, un `L.Tooltip`, ou toute information portée par le survol.
24. Écrire depuis le JS une couleur, un style en ligne, un `element.style.*`, un `setProperty`, une
    propriété personnalisée ou un nom de jeton. **Zéro.** La seule mutation de présentation autorisée est
    `classList.add/remove/toggle` et l'attribut `hidden`.
25. Contacter une origine tierce. `carte.js` **refuse structurellement toute URL de tuile dont l'origine
    diffère de `location.origin`**.
26. Appeler une route REST, instancier une classe `Massifs\`, interroger `$wpdb`, appeler une fonction
    d'ingestion.
27. Mettre l'îlot en cache (transient, `wp_cache_*`, fichier).

---

## 6. Garde du fond de carte — point d'attache unique avec la chaîne #9

Le fond de carte appartient à la chaîne #9 et **n'existe pas au moment de ce gel** (`docker/tiles/data/`
ne contient qu'un `README.md`).

Forme **attendue** de la fonction de lecture de #9 — dont la chaîne #7 ne contrôle pas le nom :

```php
massifs_fond_de_carte(): array{
  disponible: bool, url_modele: string, format: string,
  zoom_min: int, zoom_max: int, attribution: string, attribution_url: string
}
```

**Garde côté `carte.php`** : `function_exists( 'massifs_fond_de_carte' )` **et** `disponible === true`
**et** `format === 'raster'` **et** `url_modele` non vide ⇒ la clé `fond` entre dans l'îlot ; sinon elle en
est **entièrement absente**.

**Garde côté `carte.js`** : clé `fond` absente, ou origine ≠ `location.origin` ⇒ **aucune couche de
tuiles**, et **l'attribution OSM n'est pas rendue** — on n'attribue pas une ressource absente. Les
polygones reposent sur `--c-carte-fond` nu, ce qui est un rendu lisible et conforme (les ratios du §10.1
de MASTER sont mesurés contre cette surface).

**Si #9 nomme sa fonction autrement, seule la ligne de garde de `carte.php` change.** L'îlot, `carte.js` et
`carte.css` ne bougent pas. C'est le point d'attache unique, et c'est délibéré.

**Legs réciproque à la chaîne #9**, écrit ici pour qu'il ne se perde pas :
- `carte.php` appelle `massifs_partie( 'carte-secours' )` **dans son `<noscript>`**. La chaîne #9 n'a donc
  **aucune raison d'écrire dans `front-page.php`** — et ne doit pas, nos deux écritures s'écraseraient sans
  conflit git visible.
- Conséquence que #9 doit traiter : le `<noscript>` vit **dans** `.bande--carte`, que `print.css` l. 98-103
  masque à l'impression (invariant I-6). Si l'image statique de #9 doit s'imprimer, c'est à #9 de la sortir
  de cette bande ou de la ré-afficher.
- L'image de repli doit occuper la **même hauteur réservée** que la carte, sans quoi la page saute entre le
  mode JS et le mode sans JS.

---

## 7. Arbitrages — les treize points où les deux plans divergeaient

### A-0 · Élargissement d'empreinte — trois écritures hors des trois chemins de l'issue

**Décision : accordé, chirurgicalement.** Trois choses sont **mécaniquement** impossibles sans cela, et je
les distingue de ce qui n'est que confortable.

| Écriture | Pourquoi elle est mécanique | Portée accordée |
|---|---|---|
| `front-page.php` | La `<section id="carte">` l. 385 est **vide, sans `massifs_partie()`, sans hook**. Rien dans WordPress ne permet à une partie de gabarit de s'auto-injecter dans une `<section>` d'un autre gabarit. | **Une ligne**, entre les deux balises. Rien d'autre. |
| `functions.php` | `carte.css` **réserve la hauteur** de la bande carte. Enfilée tardivement, elle imprimerait dans le pied et ferait grandir le héros après coup — un saut de mise en page massif (§10 du brief). | **Une entrée** dans `$feuilles`, insérée **entre `massifs-composants` et `massifs-print`** (l'ordre du tableau est l'ordre des balises, et `print` doit rester dernier). |
| `assets/css/carte.css` | Leaflet sur un conteneur de hauteur nulle ne rend rien. `layout.css` l. 186-188 écrit explicitement que la hauteur « appartient à la chaîne carte ». Un `<style>` en ligne est refusé : MASTER §12 et §9 du brief (en-têtes stricts — un style en ligne imposerait `'unsafe-inline'`). | **Fichier neuf.** Aucune collision : ni #8 ni #9 n'y touchent. |

**Ce qui n'est PAS accordé** : modifier `massifs_partie()` pour qu'elle transmette des `$args` (elle n'en
transmet aucun aujourd'hui, `functions.php` l. 91). C'est une fonction partagée par toutes les parties du
thème ; en changer la signature dépasse une écriture chirurgicale. Conséquence directe en A-8.

### A-1 · Le sélecteur de date ne pilote que la carte

La liste textuelle reste sur le jour courant — son fichier est gelé par le contrat #6 et hors empreinte.

**Conséquence obligatoire, sans laquelle §4.2 est violé** : la carte porte un **libellé de jour permanent
et visible** (jamais seulement l'état actif d'un bouton), et le panneau massif affiche la **date de
validité** du statut qu'il montre. Un écran ne doit jamais pouvoir montrer une carte « demain » au-dessus
d'une liste « aujourd'hui » sans que le jour soit **écrit**.

Trois verrous cumulatifs contre « un statut périmé présenté comme courant » :
1. le jour affiché est écrit en toutes lettres, en permanence, au-dessus de la carte ;
2. le client ne peut basculer que vers les **deux** jours que le serveur a émis dans l'îlot ;
3. aucune persistance — un rechargement revient au jour courant.

### A-2 · Libellés du sélecteur

« Aujourd'hui » / « Demain » — **autorisés**. Ce sont les mots du §5.2 du brief lui-même, pas une invention
de domaine. Voir A-7 pour leur enregistrement.

### A-3 · `massifs_synthese_du_jour()` — appelée pour le jour SUIVANT uniquement

Le plan front l'appelait pour les deux jours. **Réduit à un seul appel**, deux raisons :

1. Le seul usage légitime dans la carte est de savoir si **demain** est publié, pour l'état du bouton
   « Demain » et sa phrase. L'état global du jour courant est déjà rendu par l'ardoise de `front-page.php`
   et par la liste ; le rendre une troisième fois sur la carte serait la seconde bannière que l'interdit 14
   proscrit.
2. Chaque appel est un `SELECT` supplémentaire : `massifs_synthese_du_jour()` rappelle
   `massifs_statuts_du_jour()` en interne et **n'accepte pas de statuts pré-résolus**.

**Budget de requêtes de la chaîne #7 : 3 `SELECT` ajoutés** (statuts × 2 jours + synthèse du jour suivant).

### A-4 · La fraîcheur du panneau est **par massif**, pas la phrase de l'ardoise

Le plan front rendait dans le panneau la phrase de fraîcheur complète du §11.3, en double, par jour.
**Refusé.** Deux raisons :

1. La phrase §11.3 complète est celle de l'**ardoise**, rendue **une seule fois** (contrat #6,
   dépendance 5-4). La répéter dans le panneau dirait deux fois le même fait, avec le risque de deux
   valeurs différentes.
2. Le panneau montre le statut d'**un massif** : sa fraîcheur honnête est l'`enregistre_le` de **ce
   statut-là**, seul instant garanti non-`null` quand `etat === 'disponible'`.

**Formulation imposée, reprise à l'identique du contrat #6 arbitrage J** — en inventer une seconde ferait
dire deux choses au même fait :

> `Relevé le {date_courte} à {heure}`

Composée **en PHP** par `massifs_horodatage( enregistre_le )`, sérialisée **pré-formatée** dans l'îlot sous
la clé `jours[jour][code].fraicheur`, `null` quand `etat !== 'disponible'`. `carte.js` la pose par
`textContent` — il ne la compose pas, il la recopie, exactement comme `niveau.libelle`.

**Conséquence : `massifs_fraicheur()` n'est pas appelée par `carte.php`.** Elle disparaît de la liste du §3.
Bénéfice secondaire : on évite le piège `evalue_le`, qui change à chaque requête.

### A-5 · Drapeaux de sérialisation JSON

Tranché au §4.1. `JSON_HEX_TAG` est le seul drapeau porteur ; `JSON_HEX_APOS` / `JSON_HEX_QUOT`, réclamés
par l'audit serveur, ne sont **pas** requis — ils protègent un contexte d'attribut, or l'îlot est du
contenu d'élément.

### A-6 · L'adresse de l'arrêté préfectoral — la phrase est rendue **sans lien**

MASTER §8.4 et §11.3 imposent verbatim : « Cette carte ne publie pas de consigne détaillée. L'arrêté
préfectoral en vigueur fait foi : [lien]. » **Aucun document du projet ne donne cette adresse.**

Deux substitutions ont été proposées, **toutes deux refusées** :
- `carte_officielle_url` désigne **la carte**, pas l'arrêté ;
- `massifs_attribution()['faits']['base_reglementaire']` (`Arrêté préfectoral n° 13-2018-05-28-005 du
  28 mai 2018`) est la base réglementaire du **référentiel des périmètres**, pas nécessairement l'arrêté
  qui porterait les consignes. Le substituer serait exactement la conflation que §4.2 interdit.

**Décision : la phrase est rendue sans son lien**, et sans référence textuelle de remplacement. Le « où
aller » est déjà servi par le bloc 6 du panneau, 24 px plus bas. Le slot `[lien]` se remplira sans refonte
le jour où l'adresse sera fournie. **Question bloquante remontée au propriétaire** (§9).

### A-7 · Les six chaînes de chrome sont autorisées

§11.3 est une liste fermée de chaînes **de statut**. Les chaînes de chrome vivent hors d'elle et il en
existe déjà en production : « Aller à la liste des statuts » (§9.3), « Massif », « Fraîcheur »
(`liste-statuts.php`). Les six chaînes du §5.3 sont de même nature, conformes à la voix du §11.1.

**Autorisées. Dette signalée à `lead-design-cms`**, pas blocage.

### A-8 · Le trou de légende du `pointille` — non corrigeable dans l'empreinte, atténué et remonté

Quand la carte affiche « demain » avant la publication de 17 h, elle peint un aplat `--statut-non-publie`
**dont l'entrée de légende n'est pas sur la page** : `front-page.php` appelle `massifs_partie( 'legende' )`
et le défaut de `legende.php` est `['indisponible', 'hors_saison']`.

Le plan front proposait de passer un second argument. **Impossible** : `massifs_partie()` ne transmet
**aucun** `$args` (A-0), le paramètre de `legende.php` est donc inatteignable depuis la page d'accueil. Le
corriger exigerait de changer une fonction partagée — hors empreinte.

**Atténuation, entièrement dans l'empreinte** : l'état est nommé **en toutes lettres** à trois endroits que
la chaîne #7 possède — l'`aria-label` du polygone, le panneau, et le message de jour. L'exigence bloquante
du §8 du brief (« l'information n'est jamais portée par la couleur seule ») est donc **tenue** : le motif
est un encodage redondant, et les mots sont présents.

**Reste un défaut réel de §8.5 au niveau de la page** — une entrée de légende manquante pour un motif
affiché. Il n'est **pas** corrigé par cette chaîne, et il est **remonté comme issue de suivi** (§9). Ce
n'est pas le cas limite d'un jour de panne : c'est le cas **nominal** de toute consultation avant 17 h.

### A-9 · Le traitement « sélectionné » de la carte — **amendée le 15 août 2026 (chaîne #50)**

> **Amendement — lire ceci avant toute correction.** La moitié « un seul contour rendu, à 4 px (L-12) »
> et le renvoi à **§3.2 emplacement 5** appliquaient le §9.2 de MASTER **v2.3**. Cet emplacement
> **n'existe plus** : la décision **D-28** de la révision **v2.4** supprime le repère décalé de la carte
> et ramène la liste fermée du §3.2 de sept à six. Le traitement « sélectionné » de la carte est
> désormais **le cerne** (MASTER §9.2.a) : un anneau posé **entièrement hors du polygone**, rendu dans un
> pane placé **sous** celui des massifs, d'épaisseur variable **par palier de zoom**. Le contrat
> d'application est `docs/contracts/issue-50.md`.
>
> **Ce qui est retiré** : « un seul contour rendu, à 4 px », le jeton L-12, la duplication décalée de
> (3 px, 4 px), et le renvoi à l'emplacement 5.
>
> **Ce qui survit à l'amendement, entier et opposable** : *le contour suit le **focus**, pas le panneau.*

La sélection **suit le focus** : les deux états coïncident toujours.

Le cerne suit le **focus**, pas le panneau : après Échap, le panneau se ferme et le cerne **reste** —
sans quoi un élément aurait le focus DOM sans indicateur visible (échec WCAG 2.4.7). **C'est la moitié
opposable de la clause**, et la v2.4 la reprend mot pour mot (§9.2.a, « il survit à la fermeture du
panneau »).

**Pourquoi l'ancienne rédaction devait tomber.** Un trait SVG est **centré** : il consomme la moitié de
son épaisseur **à l'intérieur** de la forme. À 4 px sur une languette de 3 px de large — le cas de
Regagnas à z9 — la moitié intérieure suffisait à effacer l'aplat officiel entier. La règle du §9.2
interdisait de *changer* le pigment ; elle n'interdisait pas de le *cacher*. La v2.4 ferme ce trou par
une règle de même force : **aucun état d'interaction ne recouvre un aplat de statut.**

**Enregistrement au §17 de MASTER : sans objet désormais.** La divergence §9.1 / §3.2 emplacement 5
n'existe plus puisque l'emplacement 5 n'existe plus. Elle est remplacée par **§17.1 de MASTER**, qui
liste ce que la v2.4 ouvre en aval — et dont cette clause est la ligne 1.

### A-10 · La barre de jour est **au-dessus de la toile**, pas flottante

Non prévue au croquis §7.1 (qui ne montre aucun sélecteur de date). **Confirmée** : à 360 px, un chrome
flottant recouvrirait le héros et sa lisibilité dépendrait d'un aplat opaque posé dessus. Une barre
au-dessus de la toile satisfait §4.1.d règle 8 *a fortiori*, sans recouvrement ni débordement horizontal.
Confirmation de composition demandée à `lead-design-cms` — non bloquante.

### A-11 · Cohérence carte ↔ liste pour le jour courant — risque résiduel déclaré

`carte.php` et `liste-statuts.php` appellent chacun `massifs_statuts_du_jour()` pour le jour courant, dans
le même rendu. Une écriture atterrissant **entre** les deux appels produirait deux vérités sur un écran.

`carte.php` résout **chaque jour exactement une fois** et réutilise ce tableau pour l'îlot comme pour le
panneau. Au-delà, la couture ne se ferme que par le passage de statuts pré-résolus via `$args` — refusé en
A-0. **Le risque résiduel est identique en nature à celui qui existe déjà entre `front-page.php` l. 35 et
`liste-statuts.php` l. 113** : préexistant, non introduit par cette chaîne. Remonté en §9.

### A-12 · Un seul fichier JS, `L.SVG` imposé

`assets/js/carte/carte.js`, fichier unique, IIFE, `'use strict'`, ≤ 700 lignes / ≤ 22 Ko bruts, en 11
sections numérotées. Le thème n'a aucun bundler ; les modules ES exigeraient un filtre `script_loader_tag`
(hors empreinte) et Leaflet 1.9 ne publie pas de build ESM minifié.

**`preferCanvas: false` — `L.SVG` est imposé** : le renderer canvas ne rend pas les `<pattern>` d'un
`<defs>`, et le motif est la moitié de l'information sur une légende binaire.

Critère de réouverture pour `refacto-cms` : au-delà de 900 lignes, découper — mais en introduisant alors un
vrai mécanisme de portée, pas deux `<script>`.

### A-13 · Densité du motif au zoom — trois mesures cumulatives

Le point le plus dangereux de l'issue : un motif qui s'étire redevient une information portée par la
couleur seule, **sans que rien n'ait l'air cassé**.

1. **`patternUnits="userSpaceOnUse"` écrit explicitement** sur les trois `<pattern>`, **dans le PHP**. Sa
   valeur par défaut, `objectBoundingBox`, rendrait la densité proportionnelle à la taille de chaque massif
   et variable à chaque zoom — c'est le vrai piège, plus encore que le zoom lui-même.
2. **Garde auto-corrective sur `zoomend`**, chemin nominal = no-op : lire `viewBox.width /
   svg.width.baseVal.value` ; si le rapport s'écarte de 1 de plus de 1 %, remettre à l'échelle
   `width`/`height` des patterns.
3. **Assertion de recette obligatoire** : mesurer le pas de hachure à l'écran à z8, z10 et z11 et vérifier
   qu'il est constant. Sans cette mesure, §16 n'est pas prouvé, il est affirmé.

`zoomAnimation: false` supprime par ailleurs la seule phase où Leaflet applique une transformation
d'échelle.

### A-14 · Budget de `carte.css` — voir §11

### A-15 · L'emplacement 4 du §3.2 (repère au bord gauche du panneau) reste **vacant sur la carte**

Les deux devs l'ont buté indépendamment, et leur résolution est **confirmée**.

MASTER §3.2 emplacement 4 place un repère `--bloc` sur le bord gauche du panneau massif, coloré par l'état.
Deux règles de MASTER l'en empêchent, et ce sont **ses propres règles** :
- §8.4 ligne 1 : le `h2` du nom du massif **garde son repère** — il est un titre de statut ;
- §3.3 : **jamais plus d'un repère par bloc visuel**, « si deux candidats coexistent, **le plus proche de
  l'information de statut gagne** ».

Le `h2` est plus proche de l'information de statut que le bord du panneau. **Le repère va sur le `h2`,
l'emplacement 4 reste vacant.** C'est l'application de la règle d'arbitrage que §3.3 énonce lui-même, pas
une omission.

**Ce qui n'est PAS fait, et pourquoi** : ouvrir une classe d'état sur `.carte__panneau` pour que le CSS y
pose `--repere-couleur` serait techniquement possible sans violer l'interdit 24 (le JS poserait une classe,
le CSS la couleur) — mais cela **créerait** le défaut de double repère que §3.3 proscrit. La table de
classes du §8.2 reste donc fermée, sans classe d'état sur le panneau.

**À trancher par `lead-design-cms`** : retirer l'emplacement 4 de la liste fermée, ou l'y maintenir en
excluant explicitement le panneau de carte. Enregistrement au §17 attendu.

### A-16 · L'anneau de focus générique est **conservé** sur les polygones — précision d'A-9

> **Renvoi mis en cohérence le 15 août 2026 (chaîne #50).** A-9 est amendée ; **le fond de A-16 ne change
> pas** et la révision v2.4 le confirme explicitement (§9.2.a, « ce que le cerne ne fait pas » : « il ne
> remplace pas l'anneau de focus générique du §9.1, qui reste posé sur le polygone focusé — contrat #7,
> A-16 »).

A-9 disait « seul le 4 px est rendu ». **À lire comme : aucun traitement de focus séparé n'est *écrit* pour
la carte** — et non comme une instruction de supprimer l'anneau que `layout.css` pose en `:where()`.
Depuis l'amendement d'A-9, la même lecture vaut pour **le cerne** : il est le traitement de *sélection*,
il n'est pas un traitement de *focus*, et il n'en tient pas lieu.

L'anneau est **conservé**. Le supprimer exigerait un `outline: none` dont le seul remplaçant serait un
tracé **créé par le JS** : si la duplication échoue, le focus devient invisible et WCAG 2.4.7 tombe. Un
polygone focusé porte donc l'anneau générique **et** le cerne de sélection.

`review-cms` ne doit pas compter cela comme une divergence : c'est l'arbitrage, pas une variante choisie en
silence.

**Limite constatée, enregistrée en V-50.1 du contrat #50.** Sur un `<path>` SVG, Chrome dessine
l'`outline` autour de la **boîte englobante**, et le `box-shadow` du halo ne se rend pas : sur un massif
filamenteux comme Regagnas à z9, l'anneau paraît comme un rectangle de 94 × 55 px, visuellement plus fort
que le cerne vu à 1,5 px à ce palier. **Ce n'est pas ce qui produisait le défaut de l'issue #50**
(`:focus-visible` ne s'arme pas au clic souris ; le cadre observé était le contour charbon décalé). Un
traitement de focus propre au SVG **n'existe pas dans MASTER** et la v2.4 ne l'a pas rouvert : question
remontée à `lead-design-cms`, **hors de la chaîne #50**.

### A-17 · Le pointillé de `non_encore_publie` est un **point plein**, pas un anneau

MASTER §8.1 donne pour référence un `radial-gradient` **plein**. Le `<circle r="1.25">` reçoit donc `fill`
**et** `stroke` de la même encre — point plein de ⌀ 3,5 px. Lecture fidèle de la référence.

**Conséquence liante** : une règle globale `.carte__motif-trait { fill: none }` ferait **disparaître** le
pointillé. Toute passe de nettoyage sur ce fichier doit préserver le `fill` du `<circle>`.

### A-18 · Le docblock de `massifs_enfiler_styles()` — correction autorisée

L'entrée `massifs-carte` porte le nombre de feuilles de cinq à six ; le docblock de
`massifs_enfiler_styles()` dit toujours « les **cinq** feuilles ». `dev-front-cms` a eu raison de ne pas y
toucher de sa propre initiative.

**Autorisé à `refacto-cms`, et à lui seul** : la correction du nombre dans ce docblock. Motif — c'est la
réparation d'une incohérence que **l'édition de cette chaîne a elle-même créée**, dans la fonction
qu'elle a modifiée, et non un travail nouveau sur un fichier partagé. Le risque de collision est de la même
classe que celui de l'entrée de tableau déjà accordée. Livrer sciemment un commentaire faux est le pire des
deux termes.

**Rien d'autre dans `functions.php`.**

### A-19 · Ajouts nécessaires relevés par `dev-ux-cms` — confirmés

- **`z-index` explicite sur les deux panes** (littéral `L-15`) : `leaflet.css` donne 400 aux deux et le JS
  n'a pas le droit d'écrire un style ; sans cette règle, l'ordre des deux panes dépendrait du hasard du
  DOM. **Confirmé.** **[15 août 2026, chaîne #50]** La règle **devient plus critique encore**, et sa
  justification change : ce n'est plus la signature du §3.2 emplacement 5 (supprimée par D-28) qui en
  dépend, c'est **la conformité elle-même**. Le pane du cerne doit rester **sous** celui des massifs
  (400 < 410) : c'est ce qui fait recouvrir la moitié intérieure du trait par l'aplat opaque, **par
  construction**. Les deux panes inversés, le cerne se peindrait **par-dessus** l'aplat de statut —
  exactement le défaut de l'issue #50, et une violation directe de « aucun état d'interaction ne recouvre
  un aplat de statut » (MASTER §9.2.a).
- **Anneau de focus sur `.carte__panneau:focus` et `.carte__panneau :focus`** (`:focus`, pas
  `:focus-visible`) : c'est l'exception que §9.1 nomme lui-même pour la feuille du bas et le panneau
  massif, où le focus **programmatique** doit rester visible. **Confirmé.**
- **Hauteur réservée sur `.carte` et non sur `.carte__toile`** : la toile est rendue avec `hidden`, une
  hauteur posée sur elle serait inerte jusqu'au JS et la bande sauterait de 0 à ~700 px — l'assertion de
  recette 3 tomberait. **Confirmé. Ni le PHP ni le JS ne posent de hauteur sur `.carte__toile`.**

### A-20 · La phrase §11.3 du sélecteur est démasquée **par le JS**, pas rendue visible par PHP

Défaut trouvé par `refacto-cms` : `.carte__message` n'avait **aucun chemin d'affichage visuel**. Le nœud
n'est rendu que quand le bouton « Demain » porte `aria-disabled`, or `carte.js` n'appelle jamais
`changerJour()` pour un bouton `aria-disabled`, et seul `changerJour()` levait son `hidden`. Les deux
conditions étant strictement complémentaires, la phrase n'apparaissait **dans aucun scénario** — y compris
le cas nominal de toute consultation avant 17 h. **Défaut réel contre A-3.**

Le correctif évident — retirer le `hidden` en PHP — a été **écarté**, et la raison est de fond.
`.carte__message` est **frère** de `.carte__barre`, pas fils. Rendu visible par PHP, il paraîtrait donc
**JavaScript désactivé**, où aucun sélecteur de jour n'existe. Deux de ses trois variantes deviendraient
alors trompeuses : « Information du jour non disponible… » et « Dispositif estival inactif… » ne portent
pas le mot « demain » et se liraient comme portant sur **aujourd'hui**, au-dessus d'une liste PHP qui
affiche les statuts du jour. **C'est très exactement la règle de sécurité produit du §4.2** — présenter
l'information d'un jour comme celle d'un autre.

**Décision retenue** : le nœud reste `hidden` dans le HTML servi, et `carte.js` le démasque **au même
instant que la barre**, avant `L.map`, **sans aucune interaction**. La phrase est donc visible d'emblée dès
que le bouton désactivé qu'elle explique existe, et jamais hors du sélecteur auquel elle se rapporte.
A-3 est tenu, sans créer de défaut sans JS.

Deux conséquences à ne pas défaire :
- **`.carte__message` ne porte pas `data-jour`.** Sa visibilité n'est pas pilotée par le jour affiché : un
  passage sur « Aujourd'hui » la masquerait définitivement, le bouton « Demain » désactivé ne pouvant plus
  la ramener.
- Le nœud occupe sa hauteur dans la colonne flex **dès la mesure de la toile** : aucune `invalidateSize()`
  n'est requise, et la toile est plus courte de cette hauteur les jours où demain n'est pas publié — c'est
  le cas nominal avant 17 h, et c'est sans conséquence sur l'assertion de recette 3.

---

## 8. Frontière `dev-front-cms` / `dev-ux-cms`

`dev-front-cms` possède **le balisage, les noms de classe, les attributs et le JS**.
`dev-ux-cms` possède **la totalité de `assets/css/carte.css`**, et rien d'autre.

### 8.1 Règle absolue posée au JS — vérifiable par `grep`

> `carte.js` ne contient **aucune** valeur hexadécimale, **aucun** nom de jeton CSS, **aucune** propriété
> personnalisée, **aucun** `element.style.*`, **aucun** `setProperty`. La seule mutation de présentation
> autorisée est `classList.add/remove/toggle` et l'attribut `hidden`.

### 8.2 Contrat de classes — table fermée

| Sélecteur | Posé par | Quand |
|---|---|---|
| `.carte` | PHP | racine, toujours |
| `.carte--prete` | JS | carte vivante et peinte |
| `.carte--panneau-ouvert` | JS | panneau visible |
| `.carte__barre`, `.carte__jour`, `.carte__jours`, `.carte__jour-bouton`, `.carte__message`, `.carte__attribution` | PHP | toujours (masqués par `hidden`) |
| `.carte__toile`, `.carte__aide`, `.carte__annonce`, `.carte__defs` | PHP | toujours |
| `.carte__panneau`, `.carte__panneau-{titre,fermer,etat,zapef,note-zapef,hors-niveau,consigne,fraicheur,source,lien}` | PHP | toujours (masqués par `hidden`) |
| `.carte__pane--massifs`, `.carte__pane--cerne` | JS | sur les deux panes Leaflet — **`--repere` renommé `--cerne` le 15 août 2026 (chaîne #50, D-28)** : le pane ne porte plus une trace décalée mais les deux couches du cerne |
| `.carte--echelle-departement` · `.carte--echelle-massif` · `.carte--echelle-abords` | JS | **[15 août 2026, chaîne #50]** sur la **racine**, **exactement une** à la fois, posée au montage puis remplacée à chaque `zoomend`. Table fermée, `Math.floor( getZoom() )`, deux comparaisons (`< 10`, `< 11`). Elles ne portent qu'une **épaisseur de trait** (MASTER §9.2.a) |
| `.carte__massif` | JS | sur les 25 `<path>`, via l'option `className` de `L.geoJSON` — donc **dès la création**, pas de flash du bleu Leaflet |
| `.carte__massif--autorise` · `--interdit` · `--indisponible` · `--hors-saison` · `--non-publie` | JS | **table fermée**, jamais dérivée d'un `jeton_css`, jamais calculée |
| `.carte__massif--courant` | JS | massif sous le curseur roving |
| `.carte__cerne` · `.carte__cerne-separateur` (**tous deux** dans le pane du cerne) | JS | **[15 août 2026, chaîne #50]** duplication du tracé courant, **sous** les massifs. Remplacent `.carte__contour` / `.carte__contour-trace`, dont l'une vivait dans le pane des massifs — c'était le défaut de l'issue #50. Charbon **toujours inséré avant** calcaire ; les deux `interactive: false` et `fill: none` |
| `[hidden]` | PHP + JS | **unique mécanisme de bascule de visibilité** |

**Table état → classe, fermée** (recopiée du contrat #6) : `autorise` → `--autorise` · `interdit` →
`--interdit` · `indisponible` → `--indisponible` · `hors_saison` → `--hors-saison` ·
**`non_encore_publie` → `--non-publie`**. La discontinuité de la dernière est **volontaire** : une
transformation automatique produirait une classe inexistante. Clé inconnue ⇒ **aucune classe d'état** ⇒
aucun aplat : l'échec est bruyant et visible.

### 8.3 Trois avertissements liants pour `dev-ux-cms`

1. **`[hidden]` est écrasé par tout `display`.** Toute règle `display:` sur un élément susceptible de
   porter `hidden` s'accompagne de `.carte [hidden] { display: none !important; }` — **le seul
   `!important` autorisé du fichier** — ou est conditionnée à `.carte--prete`.
2. **Le `<svg class="carte__defs">` n'est JAMAIS masqué par `display: none`** : plusieurs moteurs
   invalident alors les *paint servers* qu'il contient. `position: absolute; inline-size: 0;
   block-size: 0; overflow: hidden`, et rien d'autre.
3. **`leaflet.css` est enfilée APRÈS `carte.css`** dans la cascade (§8.5). Toute surcharge gagne en
   spécificité en se préfixant `.carte`. **Jamais d'`!important`** en dehors du cas 1.

### 8.4 Cahier des charges de `carte.css` — résultats exigés, valeurs tranchées par `dev-ux-cms`

| # | Exigence | Référence |
|---|---|---|
| 1 | `.carte` : hauteur réservée, pleine largeur, `--r-0`, fond `--c-carte-fond`, **aucun `--bord-fort`** — il est déjà porté par `layout.css` et §16 n'en autorise qu'une occurrence dans le chrome nominal | §7.1, §16 |
| 2 | `.carte__massif` : `fill-opacity: 1` **sans exception**, liseré `--statut-lisere` d'épaisseur **`--carte-lisere`** — variable par palier de zoom (**amendé le 15 août 2026, chaîne #50** : c'était « 2 px », valeur devenue celle du seul palier `massif`). `--statut-lisere-epaisseur` est désormais l'épaisseur **hors carte** et ne doit plus être consommée ici | §4.1.d règles 1-2, §10.2, §9.2.a |
| 3 | `--interdit` → hachure croisée · `--indisponible` → hachure descendante · `--non-publie` → pointillé · `--autorise` et `--hors-saison` → **aplat nu** | §4.1.a, §4.1.c |
| 4 | Contenu des trois `<pattern>` peint **par classe** avec les jetons `--statut-*` / `--statut-*-encre` ; pas `--statut-motif-pas`, trait `--statut-motif-trait` | §8.1, §4.1.d règle 6 |
| 5 | **Réécrite le 15 août 2026 (chaîne #50) — l'emplacement 5 n'existe plus (D-28).** Le cerne : `.carte__cerne` `--c-charbon` d'épaisseur `--carte-cerne` · `.carte__cerne-separateur` `--c-calcaire` d'épaisseur `--carte-cerne-clair` · les deux `fill: none` et `stroke-linejoin: round`, dans le pane `.carte__pane--cerne` (400), **sous** les massifs (410). **La règle `transform: translate(…)` sur `> svg > g` est SUPPRIMÉE** : laissée en place, elle décalerait tout le cerne et reproduirait le halo | §9.2.a |
| 6 | Survol : liseré porté à **`--carte-survol`**, soit **1,5 × le liseré du palier courant** — un **rapport**, pas trois nombres (**amendé le 15 août 2026, chaîne #50** : c'était « de 2 à 4 px », soit le double). **Jamais** un changement de teinte, jamais d'opacité. Reste sous `@media (hover: hover)` | §9.2, §9.2.a règle de tenue 1 |
| 7 | Chrome : contrôles de zoom Leaflet ≥ `--cible-min` (44 px, ils font 30 px par défaut), boutons de jour ≥ 44 px, fermeture ≥ 44 px ; aplat opaque `--c-calcaire`, `--r-0` | §9.3, §4.1.d règle 8 |
| 8 | `.carte__toile` : `position: relative; z-index: var(--z-carte)` — crée le contexte d'empilement qui empêche un contrôle Leaflet (z-index interne jusqu'à 700) de passer au-dessus du panneau | §12 |
| 9 | Panneau : ≥ 900 px colonne collante ; < 900 px feuille du bas, `--z-panneau`, défilement interne, **coins non arrondis**, **poignée non-pilule** | §7.1, §16 |
| 10 | `.carte__aide` et `.carte__annonce` masquées **visuellement** — idiome de `composants.css` (`position: absolute` + `clip-path: inset(50%)`), **jamais `display: none`** : une région live en `display: none` n'annonce rien | §9.4 |
| 11 | Animation du panneau : translation + opacité, jetons de durée existants — déjà annulés sous `prefers-reduced-motion` par `tokens.css` | §9.4, §9.5 |
| 12 | `@media (forced-colors: active)` : les `fill: url(#…)` disparaissent — les motifs sont reconstruits en traits `CanvasText`, sur le modèle de `composants.css` §12 | §10.8, §16 |
| 13 | `@media print` : **rien à écrire**, `print.css` masque déjà `.bande--carte` | §13 |
| 14 | Aucun `prefers-color-scheme`, aucun `border-radius` > 2 px, aucune ombre floue, aucun jeton nouveau | §16, §12 |
| 15 | Les littéraux hors jeton (hauteur de carte, largeur de colonne) sont **signalés sur place**, dans le style des `L-1…L-8` de `composants.css`. **Amendé le 15 août 2026 (chaîne #50)** : `tokens.css` **a été rouvert par la révision v2.4 de MASTER**, qui y ajoute cinq jetons et deux classes de palier. Son invariant n'est plus « 111 propriétés, sha256 épinglé » mais **116 sur `:root` / 133 dans le fichier**, sha256 ré-épinglé (contrat #4 amendé). **`L-12 disparaît`** de la liste des littéraux, remplacé par les quatre jetons `--carte-*` et par `--bord-selection` ; L-11, L-13, L-14 et L-15 **ne sont pas renumérotés**, ce sont des identifiants cités ailleurs. Aucune épaisseur de trait de carte ne subsiste en littéral | §12, §9.2.a, contrat #4 |

`carte.css` **ne déclare aucun** sélecteur `.statut*`, `.pastille*`, `.jalon*`, `.liste-statuts*`,
`.legende*`, `.bandeau-alerte*` : l'invariant I-1 de `composants.css` réserve ces familles à ce fichier.
Le panneau **réutilise le balisage** `.statut > .statut__marque.pastille--X + .statut__libelle` tel quel —
la peinture arrive gratuitement et I-1 reste intact.

### 8.5 Enfilage

| Ressource | Où | Comment |
|---|---|---|
| `carte.css` | `functions.php`, **une entrée** dans `$feuilles`, **entre `massifs-composants` et `massifs-print`** | `deps: [massifs-tokens, massifs-layout, massifs-composants]` |
| `leaflet.css` | `carte.php`, `wp_enqueue_style()` tardif | aucune dépendance. Aucun FOUC possible : elle ne style que des éléments que Leaflet n'a pas encore créés, et le HTML bloque l'exécution d'un script sur toute feuille pendante |
| `leaflet.js` puis `carte.js` | `carte.php`, `wp_register_script` + `wp_enqueue_script` | `[ 'in_footer' => true, 'strategy' => 'defer' ]` sur **les deux** ; `massifs-carte` dépend de `massifs-leaflet`. `defer` conserve l'ordre entre scripts différés — un mélange défer / non-défer casserait cette garantie |

Versions par `massifs_version_asset()`, qui existe et ne retourne jamais `false`.

---

## 9. Accessibilité — exigences contraignantes

- **Roving tabindex sur les 25 `<path>`** : `tabindex="-1"` + `role="button"` + `aria-label` sur chacun,
  **un seul** portant `tabindex="0"`. Un seul arrêt de tabulation (§9.3), et le focus DOM est **réel** —
  ce qui entraîne le curseur virtuel des lecteurs d'écran, contrairement à un `aria-activedescendant`.
- **`keyboard: false`** à l'initialisation de Leaflet : sans quoi il lierait les flèches au panoramique et
  volerait la navigation entre massifs. Le zoom clavier (`+` `-`) est réimplémenté, borné par
  `minZoom` / `zoom_max`.
- **Ordre des flèches = `ordre` de l'îlot = ordre du référentiel.** Le thème ne trie jamais.
- **Pas de `role="application"`**, pas de `role="listbox"` / `option`.
- **Le panneau n'est PAS `aria-modal`, PAS `role="dialog"`** : `<aside aria-labelledby>`, hors du flux de
  focus par simple ordre du DOM. Aucun piège clavier, y compris sur la feuille du bas à 360 px. Le fond
  n'est jamais `inert`, le défilement de la page n'est jamais verrouillé.
- **Échap** ferme le panneau ; si le focus était dedans, il **revient sur le massif d'origine** ; le
  contour du curseur **reste visible** (A-9).
- **Bouton « Demain » quand demain n'est pas publié** : `aria-disabled="true"`, **jamais l'attribut
  `disabled`**. Il reste focusable, reste dans l'ordre de tabulation, porte `aria-describedby` vers la
  phrase §11.3, et son activation **annonce la phrase** dans la région live sans changer de jour. Un
  `disabled` HTML le sortirait du parcours clavier et supprimerait toute explication — ce que §16 sanctionne.
- **Région live** `role="status" aria-live="polite"` : **uniquement** pour ce qui ne déplace pas le focus —
  changement de jour, activation du bouton désactivé, sélection **au pointeur**. Au clavier, c'est
  l'`aria-label` du `<path>` focusé qui parle ; doubler l'annonce ferait bégayer le lecteur d'écran.
- **`aria-label` d'un massif** : `« {libelle} — {libellé officiel de l'état} »`, ou
  `« {libelle} — {phrase §11.3 de son état} »`. Deux chaînes serveur et un tiret cadratin. **Aucun mot
  ajouté.**
- **Aucun survol porteur d'information**, jamais.
- **≥ 44 px** : boutons de jour, fermeture, contrôles de zoom. **Limite écrite, pas masquée** : les
  polygones ne sont pas agrandis — un petit massif peut mesurer moins de 44 px à 360 px, et c'est **la
  liste textuelle qui est l'équivalent garanti** (§5.3 du brief), plus le curseur clavier qui atteint les
  25 massifs quelle que soit leur taille.
- **Aucun état transitoire** avant l'arrivée de la géométrie (§9.4) : la hauteur est réservée par
  `carte.css`, la surface est un aplat `--c-carte-fond` nu. Pas de spinner, pas de squelette, pas
  d'annonce. C'est déjà exactement ce à quoi ressemble la carte.
- **Échec fatal ⇒ `racine.remove()`.** `layout.css` pose ses filets sur `.bande--carte:has(*)` : laisser un
  conteneur vide dessinerait deux filets qui se touchent, un trait noir au milieu de la page. Retirer la
  racine rend à la page exactement sa mise en page d'avant #7. Coût assumé et écrit : c'est un saut de mise
  en page, **uniquement sur le chemin d'échec**.

---

## 10. Vendorisation

`assets/vendor/leaflet/` — **à plat**, quatre fichiers :

```
leaflet.js       ← 1.9.4 dist minifié, ligne //# sourceMappingURL= RETIRÉE
leaflet.css      ← 1.9.4 dist, octet pour octet, JAMAIS édité
LICENSE          ← BSD-2-Clause, verbatim
PROVENANCE.md    ← URL amont, version, sha256 AMONT + sha256 SERVI, date, écart déclaré
```

- **Aucun répertoire `build/`, `includes/`, `dist/` ou `node_modules/` sous `vendor/`.** Le contrat #30
  §3.7 lègue cette consigne à cette chaîne : la vraie protection est structurelle, `plugins-guard.conf`
  vivant dans `docker/` et n'existant pas en production o2switch.
- **Aucun `.map`**, et la ligne `sourceMappingURL` retirée — elle déclenche une requête vers un fichier
  absent quand les devtools sont ouverts.
- **Les deux sha256 sont consignés** dans `PROVENANCE.md`, avec la nature exacte de l'écart (une ligne
  retirée, rien d'autre). Une empreinte unique serait invérifiable, puisque le fichier servi diffère de
  l'amont.
- **Aucune image.** `leaflet.css` porte trois `url(images/…)` inertes : nous n'ajoutons **ni
  `L.Control.Layers`, ni aucun `L.Marker`, ni `L.Icon.Default`**. On ne fourche pas un CSS vendorisé pour
  trois règles jamais atteintes ; la preuve est par les API non appelées, consignée dans `PROVENANCE.md`,
  et par l'assertion de recette « zéro requête vers `/vendor/leaflet/images/` ».

---

## 11. Budget — **révisé le 13 août 2026 sur mesures réelles** (A-14)

Les valeurs de la première rédaction étaient des **estimations dérivées**, écrites avant l'existence des
fichiers. Elles sont remplacées par les mesures. **Le seul budget normatif est celui du §10 du brief :
250 Ko transférés hors fond de carte, polices et géométries.**

| Ressource | Brut mesuré | Transféré mesuré (gzip -9) |
|---|---|---|
| `leaflet.js` 1.9.4 min | 147 517 o | 42 329 o |
| `leaflet.css` 1.9.4 | 14 806 o | 3 534 o |
| `carte.js` | 21 038 o | 7 109 o |
| `carte.css` | 25 703 o | 9 379 o |
| îlot JSON + balisage (**pire cas** : 25 massifs × 2 jours `disponible` avec niveau **et** ZAPEF) | 17 595 o | 2 225 o |
| **Total ajouté** | **226 659 o** | **64 576 o** |
| `massifs-13.geometrie.json` | 278 894 o | 74 023 o — **budget géométrie 300 Ko, hors enveloppe** |

**Enveloppe après #7 : ≈ 86 Ko sur 250 Ko. Marge ≈ 164 Ko.** Aucune mitigation nécessaire.

> **A-14 · Pourquoi le plafond de `carte.css` est amendé plutôt que tenu.** Le **code** de `carte.css` pèse
> 7 318 o bruts / 1 758 o transférés — largement sous le plafond initial de 12 000 o. Le dépassement vient
> de la **densité de commentaires**, qui est la convention établie de l'arbre et non un excès :
> `layout.css` porte 6 068 o de code pour 18 959 o de commentaires, `composants.css` 7 602 pour 20 308,
> `carte.css` 7 318 pour 18 385. Ces commentaires sont **opposables en revue** — ceux de `composants.css`
> portent ses invariants. Les retirer pour tenir un plafond que je avais dérivé, et qu'**aucune feuille de
> ce thème ne respecte**, détruirait de la matière de revue pour économiser 6,4 Ko sur une marge de
> 164 Ko. Le plafond était faux ; il est corrigé.

**Le vrai coût est le CPU mobile : 16 282 sommets.** Quatre décisions le tiennent :
1. **`zoom_max` reste 11**, `interval = 90 m` n'est pas rouvert (contrat #2, interdit 12). Le relever à 12
   coûterait 809 966 o bruts et ~2,9× de sommets pour une finesse invisible sous le plafond de zoom. La
   chaîne carte est nommée comme celle qui pourrait l'instruire : **elle ne l'instruit pas.**
2. `smoothFactor` laissé à 1 : Leaflet applique Douglas-Peucker **en espace écran** à chaque reprojection.
3. **Le changement de jour ne reprojette rien** — c'est un échange de classes sur 25 `<path>` existants.
   Zéro recréation de couche. C'est la décision de performance structurante du sélecteur de date.
4. `updateWhenIdle: true`, `keepBuffer: 1` sur la couche de tuiles, si elle existe un jour.

---

## 12. Assertions de recette léguées à `test-integration-cms`

1. **Zéro requête hors origine**, et aucune requête vers `/vendor/leaflet/images/*`.
2. Aucune requête vers `leaflet.js.map`.
3. Aucun saut de mise en page mesurable sur le chemin nominal — la hauteur est réservée avant le JS.
4. **Aucun statut d'un autre jour rendu comme courant** : basculer sur « Demain », puis vérifier que le
   libellé de jour, celui du panneau et l'état des polygones concordent.
5. **Constance du pas de hachure à l'écran entre z8, z10 et z11** (A-13). Sans cette mesure, §16 n'est pas
   prouvé. **Étendue le 15 août 2026 (chaîne #50) aux crans fractionnaires `9,5` et `10,5`** : depuis
   `zoomSnap: 0.25`, les zooms atteignables ne sont plus des puissances de 2 et le chemin nominal de la
   garde doit être prouvé, pas supposé. *(Vérifié en lecture dans Leaflet 1.9.4 : `SVG._update()` écrit
   `width`/`height` et `viewBox` depuis la même valeur, sans facteur d'échelle — le rapport vaut 1 à tout
   zoom. La mesure reste due.)*
6. Assertion `200` sur un fichier de `assets/vendor/leaflet/` (legs déjà ouvert par le contrat #2,
   dépendance 7).
7. Parcours clavier complet : un seul arrêt de tabulation sur la carte, flèches, Échap, retour du focus,
   aucun piège.
8. **[chaîne #50] La sélection ne recouvre jamais un aplat de statut** — à vérifier **dans le navigateur**,
   pas dans une suite de tests : c'est précisément ce qui a manqué à la v2.3. Les onze assertions sont au
   §9 de `docs/contracts/issue-50.md`, la première étant Regagnas sélectionné au palier département, aplat
   et motif entiers, **aucune peinture claire sur la carte**.

---

## 13. Questions bloquantes et dettes — remontées, jamais comblées par déduction

**Bloquantes (propriétaire du projet)**
1. **Adresse de l'arrêté préfectoral** (A-6). Le slot `[lien]` des phrases §8.4 / §11.3 reste vide. Déjà
   ouverte au §4.1.e de MASTER.

**Défauts remontés, non corrigés par cette chaîne**
2. **Entrée de légende manquante pour `non_encore_publie`** (A-8) — cas nominal avant 17 h, non corrigeable
   sans changer `massifs_partie()`. Issue de suivi.
3. **Cohérence carte ↔ liste pour le jour courant** (A-11) — risque préexistant, non aggravé.

**Dettes de design system (`lead-design-cms`)**
4. Les six chaînes de chrome du §5.3 (A-7).
5. ~~La divergence de contour §9.1 / §3.2 emplacement 5 (A-9), à enregistrer au §17.~~
   **Close le 15 août 2026.** `lead-design-cms` n'a pas enregistré la divergence : il a **révisé la règle**
   (MASTER v2.4, D-28 — l'emplacement 5 est supprimé, la sélection devient le cerne du §9.2.a). A-9 et
   A-16 sont amendées ci-dessus ; l'application est la chaîne **#50** (`docs/contracts/issue-50.md`).
   **Dette ouverte à la place** : MASTER ne spécifie **aucun traitement de focus propre au SVG**, et
   l'anneau générique se rend en rectangle de boîte englobante sur un `<path>` filamenteux (V-50.1).
6. La composition de la barre de jour, absente du croquis §7.1 (A-10).

**Demandes non bloquantes au back**
7. Mémoïsation par requête de `massifs_statuts_du_jour()` / `massifs_synthese_du_jour()` — aucun cache
   n'existe ; l'accueil passe à 6 `SELECT`. Issue `perf`.
8. `massifs_horodatage_jour( string $jour )` — demande B-1 de la chaîne #5, toujours ouverte. Elle
   supprimerait la couture « midi UTC » de trois gabarits.

**Divergences relevées entre contrats gelés et code réel** (à trancher hors de cette chaîne)
9. `issue-3.md` l. 166 annonce `massifs_saison()['confirmee'] === false` ; le code retourne **`true`**.
10. `issue-3.md` promet « exactement une entrée par code fourni » ; vrai pour les codes **scalaires**
    seulement. Sans effet ici, `array_keys( massifs_referentiel() )` étant passé.
11. MASTER §11.4 annonce « ces **huit** chaînes » ; le tableau en énumère **sept**.
