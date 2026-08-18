# Contrat d'interface — Issue #9 — Servir le fond de carte OpenStreetMap auto-hébergé et le repli sans JavaScript

**Gelé le** 13 août 2026 · **Par** `lead-issue-cms`, chaîne #9
**Lignes de DoD servies** : §12 (aucune requête du navigateur vers un domaine tiers) · §5.5 (sans
JavaScript, en entier) · §9 (attribution OSM)
**Statut** : contraignant. Les deux plans amont — `leaddev-back-cms` et `leaddev-front-cms` — ont été
produits en aveugle l'un de l'autre. Ce document est le point de réconciliation ; en cas de divergence
entre un plan et ce contrat, **c'est ce contrat qui fait foi**.

> Règle de lecture reprise de `MASTER.md` : ce document décrit des **décisions**, pas des suggestions.
> Une divergence constatée en revue est un défaut, pas une variante. Les blocs marqués **`OUVERT`** sont
> des trous de connaissance assumés — on ne les comble jamais par déduction (§4.2 du brief).

---

## 0. Approche retenue, en cinq lignes

**« Le fond cuit au build — pyramide raster bornée z5–z12, un seul pipeline, deux artefacts. »**

1. **Aucun runtime de récupération, aucun cron, aucun fetcher.** Le fond de carte ne change jamais. La
   génération appartient au **build hors ligne** ; l'hôte mutualisé ne fait que servir des octets statiques.
2. **Les données viennent d'OSM brut sous ODbL** (Overpass API, bbox départementale). **Jamais** de
   `tile.openstreetmap.org` — la *tile usage policy* de l'OSMF proscrit le téléchargement systématique.
3. **Le monochrome est cuit à la génération**, jamais par `filter: grayscale()` (MASTER §4.2 note
   d'implémentation, D-01). Le build **relit `tokens.css`** et **sort en code ≠ 0** si un jeton diverge.
4. **L'image statique du repli sans JS ne porte JAMAIS les statuts du jour** — elle porte le fond et les
   contours des 25 massifs. Elle dit *où* ; la liste textuelle dit *quoi*.
5. **Le repli est rendu par défaut**, jamais dans `<noscript>`, et le JS ne le retire qu'après montage
   réussi.

**Alternatives écartées** — chacune l'est pour une raison écrite, pas par préférence :

| Écartée | Motif décisif |
|---|---|
| Proxy-cache des tuiles standard OSM | Manquement à la politique de l'OSMF **et** le monochrome n'est pas obtenu : une LUT sur un rendu aplati laisse les POI, les milliers d'étiquettes et cinq teintes de voirie. On livrerait une carte grise bruyante, à l'opposé du §1 de `MASTER.md`. |
| PMTiles vectorielles + MapLibre GL | Le budget §10 du brief (250 Ko hors fond de carte) est violé **par la bibliothèque elle-même**. Et la chaîne #7 travaille **en ce moment** sur Leaflet vendorisé : lui imposer une bascule à chaud, sans posséder un seul de ses fichiers, est exclu. |
| Fond vectoriel unique sans pyramide (SVG en `L.svgOverlay`) | Écartée à la réconciliation, pas au brainstorm : voir **A-2**. Un artefact vectoriel ne pourrait porter que les contours DDTM, donc **aucune donnée OpenStreetMap** — et le bandeau d'attribution OSM posé dessous serait l'affirmation fausse que `templates/footer.php` l. 13-15 interdit nommément. |
| Styles raster commerciaux (Toner, Positron, Thunderforest) | CGU incompatibles avec la mise en cache et la redistribution, et crédit tiers hors §9. |

---

## 1. Fonctions de lecture exposées par l'extension

Trois fonctions, et trois seulement. Toutes préfixées `massifs_`, toutes **totales** (aucune exception,
aucun `WP_Error`, aucun `null`), toutes rendant un tableau associatif **brut et non échappé** dont
**toutes les clés sont toujours présentes** — le thème n'écrit jamais `isset()`.

Implémentation namespacée sous `Massifs\Ingest\Tuiles\` ; **seule la surface `massifs_*()` est publique**,
chaque fonction gardée par `function_exists()` dans `compat.php`, sur le patron de
`includes/domain/massifs/compat.php`.

### 1.1 `massifs_fond_de_carte(): array`

```php
[
  'disponible'   => true,      // bool   — atteste les MÉTADONNÉES, jamais le fichier
  'type'         => 'tuiles',  // string — littéral, gelé
  'url_gabarit'  => 'https://…/wp-content/plugins/massifs-core/data/tuiles/a1b2c3d4/{z}/{x}/{y}.png',
                               // string — accolades NON substituées, AUCUNE query string
  'zoom_min'     => 5,         // int
  'zoom_max'     => 12,        // int    — borne de la PYRAMIDE, jamais une autorisation de zoom (§5, F-11)
  'taille_tuile' => 256,       // int
  'format'       => 'png',     // string — gelé
  'nombre'       => 280,       // int    — diagnostic
  'bbox'         => [ 'ouest' => 0.0, 'sud' => 0.0, 'est' => 0.0, 'nord' => 0.0 ],
                               // emprise RÉELLEMENT couverte, alignée sur la grille de tuiles.
                               // Sur-ensemble strict de massifs_emprise()['bbox'].
                               // Sert à borner la couche (`bounds`), JAMAIS à cadrer la vue initiale.
                               // [AMENDÉ le 17 août 2026 par #71 — lire le §14.] Cette emprise est
                               // désormais une grandeur DÉCLARÉE, en coordonnées entières de tuile
                               // à z12, et ne se recalcule plus depuis la géométrie du référentiel.
                               // La phrase « sur-ensemble strict » reste vraie MOT POUR MOT, mais
                               // elle est devenue un CONTRÔLE en sortie ≠ 0, et non plus une
                               // définition. `nombre` passe de 280 à 295.
  'mode'         => 'complet', // string — 'complet' | 'degrade' (diagnostic de recette, §3)
  'version'      => 'a1b2c3d4',// string — /^[0-9a-f]{8}$/, segment de chemin
  'sha256'       => '…',       // string — 64 hex, empreinte du manifeste de pyramide
  'octets'       => 1310720,   // int    — somme des octets des tuiles, précalculée au build
]
```

Quand `disponible === false` : `url_gabarit` vaut `''`, `nombre` et `octets` valent `0`, `version` et
`sha256` valent `''`, `bbox` porte quatre `0.0`. `zoom_min`, `zoom_max`, `taille_tuile`, `format` et `type`
conservent leurs valeurs de repli — ce sont des constantes de format, pas des données.

### 1.2 `massifs_fond_de_carte_statique(): array`

```php
[
  'disponible'        => true,   // bool — false ⇒ le thème rend zéro octet
  'largeur'           => 1600,   // int  — dimension intrinsèque en px, > 0
  'hauteur'           => 1541,   // int  — dimension intrinsèque en px, > 0, DÉRIVÉE de la bbox projetée
  'porte_les_statuts' => false,  // bool — TOUJOURS false, gelé à vie (§4, invariant I-9.3)
  'contours_massifs'  => 25,     // int
  'version'           => 'a1b2c3d4',
  'sha256'            => '…',
  'octets'            => 0,      // int
]
```

**Il n'y a délibérément pas de clé `url`.** L'artefact vit dans le **thème** (§2) ; le thème résout son
propre chemin d'asset. Faire publier par l'extension l'URL d'un fichier de thème l'obligerait à appeler
`get_theme_file_uri()`, c'est-à-dire à **dépendre du thème**, à rebours de la frontière stricte du
`CLAUDE.md` — et à casser sur un thème enfant ou un renommage. Voir **A-3**.

`largeur` et `hauteur` sont fournies **pour que le thème les pose sur `<img>`** et n'introduise aucun saut
de mise en page (§10 du brief). Elles sont **calculées par le build** depuis la bbox projetée en Web
Mercator, jamais choisies. Quand `disponible === false`, tous les entiers valent `0` et les chaînes `''`.

> **Ordre de grandeur, non normatif, à titre de contrôle de vraisemblance seulement.** La bbox du
> référentiel donne, en Web Mercator, un rapport largeur/hauteur **proche de 1,04** — l'emprise est presque
> carrée. Une hauteur rendue très inférieure à la largeur signalerait une erreur de projection. **Ce nombre
> n'est pas une valeur à écrire** : le plan front avait supposé 1,4–1,6 et le plan back 1,131 ; **les deux
> sont faux**, et c'est exactement pourquoi la dimension est une donnée de build et non une constante.

### 1.3 `massifs_attribution_fond_de_carte(): array`

```php
[
  'phrase'       => '© les contributeurs d\'OpenStreetMap',        // §9 du brief, verbatim
  'lien_licence' => 'https://www.openstreetmap.org/copyright',     // §9 du brief
  'faits'        => [                                              // NON consommé par #9 — voir ci-dessous
      'canal'           => 'Overpass API',
      'canal_url'       => 'https://overpass-api.de/',
      'extrait_le'      => '2026-…-…',
      'licence_nom'     => 'Open Database License',
      'licence_version' => '1.0',
      'licence_url'     => 'https://opendatacommons.org/licenses/odbl/1-0/',
      'rendu'           => 'monochrome, cuit à la génération',
  ],
]
```

**`faits` n'est lu par aucun gabarit de l'issue #9.** Il est gelé quand même, et c'est délibéré : la page
« La démarche » (§5.1 et §9 du brief) doit documenter les sources et licences, et la faire rouvrir ce
contrat pour obtenir des faits que le build connaît déjà serait un coût inutile. Aucun autre consommateur
n'est autorisé à s'y ajouter sans révision de ce contrat.

**Interdit de découpe** : le thème rend `phrase` **entière** comme texte du lien —
`<a href="{lien_licence}">{phrase}</a>`. Il ne la coupe pas, ne l'abrège pas, ne la reformule pas, et
n'invente aucun libellé de lien. C'est la seule forme qui satisfait à la fois l'interdiction de découpe
(`templates/footer.php` l. 37-42), la fermeture de la liste §11.3 de `MASTER.md`, et l'exigence d'un nom
accessible décrivant la destination.

### 1.4 Fonctions explicitement NON créées

`massifs_fond_de_carte_etat()` et `massifs_fond_de_carte_disponible()` — proposées par le plan back,
**écartées**. Aucun consommateur : `disponible` et `mode` sont déjà des clés de `massifs_fond_de_carte()`.
Une seconde manière de poser la même question est une divergence en attente.

---

## 2. Emplacement des artefacts — tranché

| Artefact | Chemin | Propriétaire |
|---|---|---|
| Pyramide de tuiles | `wp-content/plugins/massifs-core/data/tuiles/<version>/{z}/{x}/{y}.png` | extension |
| En-têtes de cache | `wp-content/plugins/massifs-core/data/tuiles/.htaccess` | extension |
| Métadonnées runtime | `wp-content/plugins/massifs-core/data/tuiles/fond-13.php` | extension |
| **Image statique du repli** | **`wp-content/themes/massifs/assets/img/carte-statique.png`** | **thème** |
| Code du module | `wp-content/plugins/massifs-core/includes/ingest/tuiles/**` | extension |
| Pipeline de build | `wp-content/plugins/massifs-core/includes/ingest/tuiles/build/**` | extension |

**`includes/**` sous `plugins/` est 403 par construction** (`plugins-guard.conf` bloc 2 + `.htaccess` du
module, vérifié en HTTP par le contrat #2 B-18) : il porte **du code, jamais un octet servi au navigateur**.

**Le service Docker `tiles` et le chemin `/tiles/` ne sont utilisés par personne dans cette issue.** Ils
n'ont aucun équivalent en production o2switch, et une URL qui diverge entre Docker et la prod est la classe
de panne dont le contrat #30 §3 met en garde. `data/` fonctionne à l'identique dans les deux
environnements, sans une ligne d'Apache.

**Le format de l'image statique est gelé à PNG-8 indexé.** La bascule WebP proposée par le plan front est
**retirée** : geler l'extension supprime toute ambiguïté sur le nom de fichier, donc toute nécessité pour
l'extension de publier un basename de thème. Si le PNG dépasse le plafond de **150 Ko transférés**, les
mitigations s'appliquent **dans cet ordre** — (1) réduire la palette indexée à 6–8 couleurs, (2) supprimer
les couches de fond les moins informatives, (3) ramener la largeur intrinsèque à 1280 px. **Jamais** une
compression avec perte, jamais un second artefact, jamais un `srcset`. Un PNG-8 d'aplats plats compresse
très fortement ; le plafond doit être **mesuré**, pas supposé.

---

## 3. États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `fond_indisponible` — `massifs_fond_de_carte()['disponible'] === false` | Métadonnées absentes, corrompues, de schéma inconnu, ou build en mode dégradé | **Aucune couche de fond n'est montée. Point.** Les polygones de massifs se peignent sur la toile nue `--c-carte-fond`. **Aucun repli vers une URL tierce, sous aucune forme.** |
| `fond_statique_indisponible` — `massifs_fond_de_carte_statique()['disponible'] === false` | Métadonnées de l'image absentes | `carte-secours.php` rend **zéro octet**. Jamais d'`<img>` cassée, jamais d'`alt` de substitution, jamais de texte de repli. Conforme au §13 de `MASTER.md` : « remplacée par l'image statique **si elle est disponible, sinon par rien** ». |
| `attribution_fond_indisponible` — fonction absente ou `phrase` vide après `trim()` | — | `carte-secours.php` rend **zéro octet, image comprise**. Voir §4, invariant I-9.4. |
| `lien_licence_indisponible` — `'' === esc_url( lien_licence )` | — | La phrase est rendue en **texte nu**, sans `<a>`. Idiome déjà en service dans `bandeau-non-officialite.php` l. 50-52 : on ne rend un lien que s'il survit à l'échappement. |
| `ancre_liste_absente` — la garde de `header.php` l. 41-44 est fausse | — | Image et attribution rendues, **lien omis**. Un lien vers une ancre absente est pire que son absence. |
| `fond_degrade` — `mode === 'degrade'` | Build joué sans archive OSM | **Le thème n'a rien de particulier à faire.** Le fond est indisponible et l'image statique disponible ; les deux `disponible` suffisent. C'est un signal de **recette**, pas un état d'interface. |
| `information_indisponible` · `hors_saison` · `non_encore_publie` · `donnee_perimee` | Contrats #3 / #5, inchangés | **Aucun effet sur cette issue, par construction** — voir §4, invariant I-9.3. |
| `couche_effis_indisponible` | Hors périmètre de #9 | — |

**L'extension fournit les codes, le thème fournit les mots.** L'issue #9 n'expose **aucune** chaîne
d'interface hors l'attribution du §9 du brief.

**Le fond de carte n'a pas d'indicateur de fraîcheur, et ne doit pas en avoir.** Le §4.5 du brief attache
la fraîcheur aux **statuts** — une donnée qui a un jour de validité. Un fond de carte n'a pas de « fond du
jour » ; lui coller un indicateur de fraîcheur banaliserait celui des statuts et l'affaiblirait.

---

## 4. Invariants — vérifiables en revue

| # | Invariant | Ce que sa violation casse |
|---|---|---|
| **I-9.1** | Le repli est **rendu par défaut dans le HTML servi par PHP**, jamais dans `<noscript>` | §5.5 en entier. Trois raisons cumulatives : `<noscript>` est **inatteignable par `print.css`** quand JS est actif (son contenu n'est pas parsé en éléments), donc le §13 de `MASTER.md` serait inapplicable ; le cas « JS actif, carte en échec » ne serait pas couvert ; et `MASTER.md` §3.4 décrit déjà ce monde. |
| **I-9.2** | **Aucune URL tierce nulle part** — ni attribut, ni `srcset`, ni `errorTileUrl`, ni commentaire de code « pour mémoire », ni métadonnée d'image (`tEXt`, `iTXt`, `XMP`) | DoD §12. Le piège n'est pas le cas nominal, c'est le cas dégradé. |
| **I-9.3** | L'image statique **ne porte aucun aplat de statut** : `#22B14C` et `#E63A3C` sont **absents du fichier**, sous toute forme. `porte_les_statuts` vaut `false` à vie | La règle de sécurité absolue « ne jamais présenter un statut périmé comme courant » (§4.2 du brief). Une image portant les couleurs du jour se périmerait par un chemin que le PHP ne contrôle plus (cache HTTP, CDN de l'hébergeur). |
| **I-9.4** | L'image et son attribution **n'existent que l'une avec l'autre**. La garde d'attribution est évaluée **avant** la garde d'image ; son échec fait rendre zéro octet à la partie entière | Deux règles convergentes : afficher un rendu ODbL **sans** attribution est une violation de licence ; créditer une source dont **aucune** donnée n'est affichée est « une affirmation fausse » (`footer.php` l. 13-15). |
| **I-9.5** | `carte-secours.php` **n'appelle aucune fonction de statut** — ni `massifs_statuts_du_jour()`, ni `massifs_synthese_du_jour()`, ni `massifs_fraicheur()`, ni `massifs_jour_courant()` | Il est ainsi **structurellement incapable** de présenter un statut périmé comme courant. La règle est tenue par l'absence de couplage, pas par une garde — et c'est ce qui autorise un `Cache-Control` long sans arbitrage. |
| **I-9.6** | Le bandeau d'attribution reste **dans le flux, sous la carte**, et ne flotte **jamais** au-dessus de la toile | C'est ce qui satisfait le corollaire de la règle 8 du §4.1.d et **D-24** — « tout élément de chrome de carte **flottant** repose sur un aplat opaque `--c-calcaire` » — **sans aucune règle CSS** : dans le flux, la surface de page *est* l'aplat calcaire. Voir **A-6**. |
| **I-9.7** | Le build **relit les six `--c-carte-*` dans `themes/massifs/assets/css/tokens.css`** et **sort en code ≠ 0** sur toute divergence | D-01 et le §4.2 de `MASTER.md` deviennent opposables. C'est aussi ce qui empêche un `filter: grayscale()` de revenir par la fenêtre. |
| **I-9.8** | Aucun `wp_remote_*`, aucun `curl`, aucun `file_get_contents` sur une URL dans `includes/ingest/tuiles/**` | Contrainte #2. **Contrôle de revue mécanique** : un `grep` de ces motifs sur le module doit rendre **zéro**. Le seul fichier du dépôt qui touche le réseau est `build/recuperer.mjs`, joué à la main, jamais un prérequis de `npm run construire`. |
| **I-9.9** | La ligne de DoD §5.5 **ne dépend d'aucun accès réseau au build** | En mode dégradé, l'image statique est **quand même produite**, depuis `data/massifs-13.geometrie.json` seule, que nous possédons hors ligne. La pyramide, elle, n'est pas émise — 280 aplats uniformes seraient une carte qui affirme quelque chose de faux sur la géographie. |

---

## 5. Clauses opposables à la chaîne #7 — à réconcilier avec son contrat

La chaîne #7 possède `templates/parts/carte.php`, `assets/js/carte/**` et `assets/vendor/**`. Elle est le
consommateur de tout ce que cette issue produit. **Ces clauses sont la moitié du contrat que je ne peux pas
faire appliquer moi-même** ; elles remontent à l'orchestrateur pour réconciliation.

| # | Clause | Ce que sa violation casse |
|---|---|---|
| **F-1** | `carte-secours.php` est inclus **sans aucune condition** dans `<section id="carte" class="bande bande--carte">`. **Position amendée le 14 août 2026 — lire A-13 du §13 avant toute correction** : l'inclusion appartient à `front-page.php` et vient **après** `massifs_partie( 'carte' )`, et non en premier enfant. L'inconditionnalité, elle, est entière et c'est la moitié opposable de la clause | §5.5 **en silence** : sans inclusion inconditionnelle, le repli disparaît dès que JS est actif, et `carte-secours.php` devient du code mort qu'un refacto supprimera. **Éprouvé par un test nommé** : `tests/rendu/recette-rendu.mjs` l. 549-568. |
| **F-2** | Le JS ne retire **que** `.carte-secours__repli`, et **seulement après montage réussi** — jamais sur un simple test de présence de Leaflet | Un retrait anticipé produit un trou blanc quand le montage échoue. |
| **F-3** | `.carte-secours__attribution` n'est **jamais** retiré, jamais masqué, jamais dupliqué | Attribution orpheline ou doublée ; DoD §9 et licence ODbL. |
| **F-4** | Leaflet est configuré **`attributionControl: false`** | Une seconde attribution, non maîtrisée, posée par la bibliothèque **sur la toile nue** — D-24. |
| **F-5** | Le conteneur de carte est inséré de sorte que `.carte-secours__attribution` reste **sous la carte visible**, dans le flux, jamais au-dessus | I-9.6 : c'est ce qui rend D-24 satisfait sans règle CSS. |
| **F-6** | Le retrait se fait **sans transition, sans animation** (§9.5, `prefers-reduced-motion`) | §16 de `MASTER.md`. |
| **F-7** | **La hauteur de la bande carte est portée par le conteneur de #7**, jamais par le repli. `carte-secours.php` ne pose aucune hauteur, aucun `min-block-size`, aucun `aspect-ratio` | Un repli qui impose la hauteur fait sauter la mise en page au montage. |
| **F-8** | **Aucune URL de fond écrite dans le JS** : ni gabarit `{z}/{x}/{y}`, ni `errorTileUrl` distant, ni URL en commentaire. Elles viennent toutes de `massifs_fond_de_carte()` | DoD §12. L'`errorTileUrl` tiers est le piège classique du cas dégradé. |
| **F-9** | #7 ne rend **aucune seconde image statique** et ne réécrit ni `src`, ni `width`, ni `height`, ni `alt` de `.carte-secours__image` | Artefact dédoublé, ratio faussé. |
| **F-10** | #7 **n'écrit jamais** dans `templates/parts/carte-secours.php` | Disjonction des empreintes. |
| **F-11** | **`maxZoom` de la carte reste `massifs_emprise()['zoom_max']` (= 11), jamais `fond.zoom_max` (= 12)** | Le douzième niveau de la pyramide existe pour la netteté sur écran à forte densité (`detectRetina` demande z+1) et pour `zoomSnap` fractionnaire — **pas** pour autoriser un cran de zoom de plus. Sans cette clause, #7 réglera `maxZoom: 12` de bonne foi et **affichera un fond sans polygones**, la couche massifs étant plafonnée à 11 par l'interdit 12 du contrat #2. |
| **F-12** | **`url_gabarit` ne passe JAMAIS par `esc_url()`** — `esc_attr()` ou `wp_json_encode()` | `esc_url()` **supprime** `{` et `}`, hors de sa liste blanche, et produit `…/zxy.png`. Panne **silencieuse**, à l'exécution seulement. |
| **F-13** | La vue initiale est cadrée sur `massifs_emprise()`, **jamais** sur `fond.bbox` | `fond.bbox` est l'emprise de la **pyramide**, alignée sur la grille de tuiles : c'est un sur-ensemble strict. |

---

## 6. Chaînes fournies par le serveur, chaînes écrites par le thème

| Chaîne | Origine | Statut |
|---|---|---|
| `© les contributeurs d'OpenStreetMap` | **serveur**, `massifs_attribution_fond_de_carte()['phrase']` | §9 du brief, **verbatim**. Rendue et échappée, jamais composée ni découpée. |
| `https://www.openstreetmap.org/copyright` | **serveur** | §9 du brief. `esc_url()`, jamais écrite en dur dans le thème. |
| `Aller à la liste des statuts` | **thème** | **Déjà en service** dans `templates/header.php` l. 49, écrite au §7.1 de `MASTER.md` et au §5.3 du brief. **Aucune invention.** |
| Alternative textuelle de l'image | **aucune** — `alt=""` | §11.3 de `MASTER.md` est une **liste fermée** et ne contient aucune phrase d'`alt`. Rédiger une description serait inventer une chaîne de site. C'est par ailleurs le cas canonique de l'alternative vide : l'information exploitable de l'image est intégralement portée par la liste adjacente (§8 du brief). |

**Le nom accessible est porté par le lien visible, pas par l'image.** L'image et le lien sont **frères**,
jamais imbriqués : `<a><img alt=""></a>` sans texte visible serait un lien sans nom accessible, défaut
bloquant.

Le libellé « Aller à la liste des statuts » apparaît **deux fois** dans la page — lien d'évitement du
`header` et lien visible du repli. **Ce n'est pas un défaut** : deux liens de même nom vers **la même**
destination satisfont WCAG 2.4.4, et la redondance est utile.

---

## 7. Interdits

### Portant sur le thème

1. Le thème **n'écrit jamais une URL de fond de carte**, même partielle, même en Docker, même « pour
   tester ». Même règle que la bbox (interdit 11 du contrat #2). Seul point d'entrée :
   `massifs_fond_de_carte()['url_gabarit']`.
2. Le thème **ne se replie jamais sur une URL tierce** quand `disponible === false`. Aucune couche n'est
   montée, point.
3. Le thème **ne substitue jamais `{z}/{x}/{y}` en PHP** et ne construit jamais une URL de tuile à la main.
4. Le thème **n'ajoute jamais de query string** à une URL de tuile — la version est dans le chemin, un
   `?v=` détruirait la sémantique `immutable` sur certains proxies.
5. Le thème **n'applique jamais `filter: grayscale()`, `saturate()` ou toute autre retouche CSS au fond** :
   le monochrome est cuit, le filtre casserait les ratios mesurés du §10 de `MASTER.md` et coûterait en
   peinture sur mobile.
6. Le thème **n'ouvre jamais** `data/tuiles/fond-13.php` ni un PNG en PHP, et **n'appelle jamais** une
   fonction du namespace `Massifs\Ingest\Tuiles\` ni un script de `build/`.
7. Le thème **ne calcule jamais** un ratio, une dimension, un poids, une date, un statut, une fraîcheur ni
   une saison. Il **n'appelle jamais** `filesize()`, `getimagesize()`, `file_exists()` ni `hash_file()` sur
   l'artefact.
8. Le thème **ne suppose jamais** que `disponible === true` garantit la présence physique d'un octet : PHP
   ne stat jamais ces fichiers. Une tuile manquante se dégrade en trou visuel, jamais en erreur PHP.
9. `carte-secours.php` **n'émet aucun titre** (`h1`…`h6`), aucun **repère** (§3.2 de `MASTER.md` est une
   liste fermée à sept emplacements, aucun ne vise le repli ; §3.3 l'interdit sur les liens), aucun
   `<figure>`/`<figcaption>`, aucun `<noscript>`, aucun `srcset`/`sizes`/`<picture>`, aucun
   `loading="lazy"` (image au-dessus de la ligne de flottaison), aucun `aria-hidden`/`role`/`aria-label`/
   `title`, et **aucun `forced-color-adjust: none`**.
10. Le thème **n'écrit jamais** une valeur hexadécimale, une couleur, un espacement ou une durée dans le
    gabarit.

### Portant sur l'extension

11. L'extension **n'émet aucun HTML de présentation publique**. `carte-secours.php` rend et échappe ; il ne
    compose pas.
12. L'extension **ne dépend jamais du thème au runtime** : aucun `get_theme_file_uri()`, aucun
    `get_stylesheet_directory()`. La lecture de `tokens.css` a lieu **au build uniquement**, jamais à
    l'exécution.
13. **Aucune planification** : ni `wp_schedule_event`, ni hook cron, ni `wp_remote_get`, dans tout le
    module. Aucune alerte email non plus — il n'y a pas de tâche planifiée qui puisse échouer en
    production, et le seul destinataire d'un échec est le développeur qui lance le build, par le code de
    sortie.
14. **Aucun hook, aucun filtre offert** : `add_action`, `add_filter`, `apply_filters` sont absents du
    module. Un filtre sur l'URL du fond serait un moyen de faire pointer la carte ailleurs que chez nous —
    exactement ce que la contrainte #2 interdit.
15. **Aucune route REST, aucun écran d'administration, aucun rôle, aucune capability.** 280 amorçages
    WordPress pour servir des octets immuables contrediraient les 2,5 s du §10. Corollaire opposable : **la
    surface d'écriture du fond de carte en production est nulle.**
16. Les métadonnées et les octets **ne sont jamais écrits à la main** : seul `build/construire.mjs` les
    produit, et l'émission est **atomique** — un build à moitié appliqué laisserait des tuiles neuves et des
    métadonnées anciennes, donc une URL qui ment.
17. **`tile.openstreetmap.org` et tout serveur de tuiles rendues sont interdits au build comme au runtime.**

---

## 8. Arbitrages — chaque désaccord entre les deux plans, la décision, sa raison

### A-1 — Nommage des fonctions *(le désaccord classique du travail en parallèle)*

- **Back** : `massifs_fond_de_carte()`, `massifs_fond_de_carte_statique()`,
  `massifs_attribution_fond_de_carte()`, plus `massifs_fond_de_carte_etat()` et
  `massifs_fond_de_carte_disponible()`.
- **Front** : `massifs_carte_statique()`, `massifs_attribution_fond()`.

**Décision : le nommage du back**, moins les deux fonctions surnuméraires (§1.4).
**Raison** : la convention en service suffixe l'attribution par **la chose attribuée** —
`massifs_attribution()` pour les périmètres, `massifs_attribution_statuts()` pour les statuts. La chose
attribuée ici est le **fond de carte**, pas « le fond ». `massifs_attribution_fond()` est ambigu et aurait
vieilli mal dès l'arrivée de la couche EFFIS. Le front n'ayant jamais vu la proposition du back, ce n'est
pas une faute de sa part : c'est exactement le point que ce contrat existe pour trancher.

### A-2 — Format de l'artefact statique : SVG ou raster ?

Le brainstorm recommandait un fond **vectoriel** ; le plan front a écarté le SVG pour une raison que le
brainstorm n'avait pas vue et qui est décisive :

> Un SVG ne pourrait porter que les contours issus du référentiel DDTM — c'est-à-dire **aucune donnée
> OpenStreetMap**. Le bandeau d'attribution OSM posé sous une telle image serait précisément
> l'**affirmation fausse** que `templates/footer.php` l. 13-15 interdit nommément. Or la DoD de #9 **exige**
> l'attribution OSM.

**Décision : raster, PNG-8 indexé, format gelé.** Le plan back convergeait déjà vers le raster par un autre
chemin (le monochrome cuit à la génération, §4.2 de `MASTER.md`). La bascule WebP proposée par le front est
**retirée** : geler l'extension supprime toute ambiguïté de nom de fichier, donc toute nécessité pour
l'extension de publier un basename de thème — ce qui ferme A-3 proprement. Les mitigations de poids
(§2) suffisent.

### A-3 — Où vit l'image statique *(le plus structurant)*

- **Back** : dans l'extension, `data/tuiles/<version>/statique/`, avec trois arguments — l'extension ne
  doit pas dépendre du thème pour publier une URL ; un seul pipeline pour tous les artefacts ; le contrat
  #30 §3.6 a nommément autorisé `data/**`.
- **Front** : dans le thème, `assets/img/carte-statique.png` — **ce que dit l'empreinte de l'issue**.

**Décision : dans le thème, et l'extension ne publie pas son URL.**

Le back a raison sur le **problème** — publier depuis l'extension l'URL d'un fichier de thème l'obligerait à
appeler `get_theme_file_uri()` et à dépendre du thème, à rebours de la frontière du `CLAUDE.md` — mais sa
solution déplaçait l'empreinte de l'issue, ce qui n'est pas à sa main. **La troisième voie ferme le problème
sans toucher l'empreinte** : l'extension publie **les dimensions, la version et l'empreinte** de l'artefact
que son build a produit — des **faits**, pas un chemin — et le thème résout **son propre** chemin d'asset,
ce que fait déjà tout gabarit du projet.

Le couplage qui subsiste est **au build**, et il est **symétrique et déjà accepté** : le build lit un
fichier du thème (`tokens.css`, I-9.7) et écrit un fichier du thème (le PNG). Au **runtime**, aucune des
deux moitiés ne connaît l'autre. C'est strictement meilleur que la proposition du back : les dimensions
restent justes même sur un thème enfant ou après un renommage, parce qu'elles décrivent l'artefact et non
son emplacement.

### A-4 — Une variante d'image ou deux ?

- **Back** : deux variantes (800 px et 1600 px), `variantes[]` triée, prête pour un `srcset`.
- **Front** : **une seule**, `srcset` explicitement refusé — « l'empreinte dit un fichier ».

**Décision : une seule, 1600 px de large.** L'empreinte nomme `carte-statique.*` — un fichier. Le `srcset`
n'achèterait rien : `max-inline-size: 100%` plus `block-size: auto` mettent déjà l'image à l'échelle, et
c'est la variante large qui est requise par l'impression (§13 de `MASTER.md` : ≈ 219 ppp sur une largeur
utile A4 de 186 mm). Deux artefacts, c'est deux chemins de génération à garder d'accord pour un gain nul.
La clé `variantes` du plan back disparaît ; ses champs remontent à plat dans le retour (§1.2).

### A-5 — Forme de l'attribution

- **Back** : `phrase`, `phrase_courte`, `lien_licence`, `lien_source`, `faits{}`.
- **Front** : `phrase` + `lien_licence` seulement, rendus `<a href="{lien_licence}">{phrase}</a>`.

**Décision : la forme de rendu du front, le contenu du back moins deux clés.** `phrase_courte` et
`lien_source` sont **retirées** : aucune consommatrice, et `lien_source` serait une chaîne vide gelée dans
un contrat. `faits` est **conservée** avec une justification écrite (§1.3) — sans elle, la page « La
démarche » rouvrirait ce contrat pour obtenir des faits que le build connaît déjà.

### A-6 — L'aplat opaque sous le bandeau d'attribution (D-24)

Le front a signalé comme **couture bloquante** l'aplat opaque `--c-calcaire` exigé « mot pour mot » par le
corollaire de la règle 8 du §4.1.d, inatteignable depuis un gabarit puisque `composants.css` est gelé.

**Décision : il n'y a pas de couture — la prescription est satisfaite par la structure.** D-24 vise « tout
élément de chrome de carte **flottant** ». Le bandeau d'attribution de `carte-secours.php` est **dans le
flux, sous l'image puis sous la carte**, et n'est jamais posé sur la toile. Dans le flux, la **surface de
page est déjà l'aplat calcaire** : il n'y a rien à peindre. C'est la clause **F-5** qui maintient cette
propriété quand #7 monte Leaflet, et l'invariant **I-9.6** qui la rend vérifiable en revue.

**Ce qui reste une vraie couture**, en revanche, et qui n'est pas dans mon empreinte : la **cible tactile
≥ 44 px** sur `.carte-secours__lien` (§5.2 du brief, `MASTER.md` §7.1). Elle appartient à `dev-ux-cms`, sur
l'issue qui rouvrira une feuille de composants. Elle est reportée, pas résolue — voir §10.

### A-7 — `zoom_max` de la pyramide contre l'interdit 12 du contrat #2

Le back a vu le piège ; le front ne l'a pas traité (c'est le domaine de #7).

> **[AMENDÉ le 17 août 2026 par #71 — lire le §14.]** La décision ci-dessous est **intacte**. S'y
> **ajoute** une clause : le jeu d'étiquettes de z12 **est exactement celui de z11, cuit au double**,
> précisément parce qu'une tuile z12 n'est jamais rendue à sa propre échelle. Les comptes passent de
> 195 à 210 tuiles à z12, et de 280 à 295 au total, sous l'effet de l'emprise déclarée.

**Décision : la pyramide monte à z12, la carte reste plafonnée à z11.** Les 195 tuiles de z12 (sur 280)
servent la netteté sur écran à forte densité et le `zoomSnap` fractionnaire, pour ~0,9 Mo — un coût
négligeable contre un flou visible. **Gelé en clause F-11**, sans quoi #7 réglera `maxZoom: 12` de bonne foi
et affichera un fond sans polygones.

### A-8 — Canal d'extraction des données OSM

**Décision : Overpass API**, sur la bbox départementale, à la main, jamais en intégration continue,
`User-Agent` nommant le projet et son dépôt.
**Raison** : l'archive résultante est **commitable** (≤ 6 Mo), donc `git clone` + `docker compose up` donne
une carte fonctionnelle sans réseau — c'est le §11 du brief tenu. Un extrait Geofabrik PACA pèse ~200 Mo :
inarchivable, et le §11 se dégraderait en « télécharger 200 Mo avant de construire ». Geofabrik reste un
repli documenté, pas la voie nominale.

### A-9 — Toponymes

> **[RENVERSÉ le 17 août 2026 par #71 — lire le §14.]** Cet arbitrage est **caduc**. Les toponymes
> sont cuits dans **les deux** artefacts. Les deux motifs qui le fondaient sont tombés, mesure à
> l'appui : le placement d'étiquettes n'est pas « un moteur à lui seul » puisque la toile-par-zoom
> existe déjà, et la police est chargeable au build (`fontkit` lit le `woff2` directement, et seuls
> des `<path>` sont émis — jamais un `<text>`). Le texte ci-dessous est conservé **comme archive de
> la décision v1**, non comme prescription.

Les deux plans convergent : **aucun toponyme en v1**, et c'est confirmé.
**Raison** : le placement d'étiquettes (collision, priorité, cohérence entre zooms) est un moteur à lui
seul ; un placement naïf produit des chevauchements illisibles, incompatibles avec le registre « atelier »
du §7 du brief. `resvg` exigerait par ailleurs une police chargeable au build, et les deux familles du
projet sont des `woff2` variables appartenant à l'empreinte du front.
**Coût nommé et assumé** : l'orientation repose sur la forme du littoral, l'Étang de Berre et les 25
contours, pas sur des noms de lieux. `--c-carte-encre` n'a donc **aucun consommateur** en v1 — absence
délibérée, pas défaut. **L'ordre de couches de D-24 est écrit malgré tout** (interdit 9 du §7 côté thème),
pour que l'ajout de toponymes en v2 n'exige aucune reprise du thème.

### A-10 — Dimensions de l'image : aucune n'était juste

Le plan front supposait un rapport largeur/hauteur de **1,4–1,6** ; le plan back annonçait **1,131** et en
dérivait 800×707 et 1600×1414. **Les deux sont faux** : la bbox du référentiel projetée en Web Mercator
donne un rapport **proche de 1,04**, l'emprise étant presque carrée.

**Décision : aucune dimension n'est écrite dans ce contrat comme valeur normative.** Le build les calcule
depuis la bbox projetée et les écrit dans les métadonnées ; le contrat ne fixe que la **largeur cible
(1600 px)**, le fait que ce sont des **entiers > 0**, et que le thème les **rend sans les recalculer**. Le
rapport de 1,04 figure au §1.2 **uniquement** comme contrôle de vraisemblance. C'est la démonstration
directe de la raison d'être de ce contrat : deux agents compétents, en aveugle, ont produit deux nombres
différents et tous deux erronés pour la même grandeur.

---

## 9. `OUVERT` — à ne jamais combler par déduction

> **`OUVERT` — la mention de la source de l'extrait.** Le §9 du brief impose
> « © les contributeurs d'OpenStreetMap » avec lien vers openstreetmap.org/copyright, **« + mention de la
> source de l'extrait le cas échéant »**. La condition « le cas échéant » n'est pas levée : Overpass est un
> service d'interrogation, pas un redistributeur revendiquant un crédit propre, et l'ODbL exige
> d'attribuer **OpenStreetMap**, ce que la phrase fait.
> **Traitement retenu** : `phrase` porte la chaîne du §9 **seule et verbatim** ; `faits.canal` porte le fait
> brut, citable sur « La démarche » le jour venu ; **aucune formulation supplémentaire n'est rédigée.**
> C'est le seul traitement qui honore à la fois le §9 et l'interdiction d'inventer du §4.2 du brief. À
> confirmer avant mise en production, avec la question 8 du §4.1.e de `MASTER.md`, déjà `OUVERT` et
> bloquante au même titre.

> ~~**`OUVERT` — les toponymes.** Voir A-9. Aucune sélection de noms de lieux n'est validée ;
> `--c-carte-encre` n'a aucun consommateur en v1. Une v2 les ajouterait par un simple changement de
> `version` de la pyramide, absorbé par la version-dans-le-chemin.~~
>
> **CLOS le 17 août 2026 par #71.** C'est bien la v2 annoncée, et elle a été absorbée par la
> version-dans-le-chemin comme prévu. La règle de sélection est gelée au §3 de
> `docs/contracts/issue-71.md` ; `--c-carte-encre` a désormais **exactement un** consommateur, ce qui
> rend son dénombrement de pixels exact. **L'autre `OUVERT` de cette section — la mention de la source
> de l'extrait — reste ouvert et n'est pas touché.**

---

## 10. Coutures hors empreinte — signalées, non exécutées

Aucune n'est écrite par cette chaîne. Elles remontent à l'orchestrateur.

| # | Couture | Pourquoi hors de #9 | Porteur proposé |
|---|---|---|---|
| **C-1** | **Impression.** `print.css` l. 98-103 (**gelé**) pose `display: none` sur `.bande--carte` **entière** ; un descendant d'un `display: none` ne peut pas être ré-affiché. L'image **et** son attribution sont donc absentes de la feuille imprimée. C'est l'invariant **I-6 du contrat #22**, sans porteur depuis deux lots. Trois chemins, tous hors empreinte : (a) sortir le repli de `.bande--carte` → `front-page.php` ou `carte.php` ; (b) règle `@media print` plus spécifique portée sur `.bande--carte` elle-même, dans une feuille enfilée après `massifs-print` → `assets/css/**` + `functions.php` ; (c) rouvrir `print.css` | `print.css` gelé ; `front-page.php`, `functions.php`, `assets/css/**` non attribués ; `carte.php` appartient à #7 | **Décision d'orchestration.** Sert §13 de `MASTER.md` et la clause « attributions toujours imprimées » |
| **C-2** | **Cible tactile ≥ 44 px** sur `.carte-secours__lien`, et mesure de ligne du bandeau d'attribution — `.bande--carte` n'émet pas de `.bande__contenu`, donc la règle de mesure ne s'y applique pas | `composants.css` gelé, `layout.css` non attribué | `dev-ux-cms`, sur l'issue qui rouvrira une feuille de composants |
| **C-3** | **Garde d'ancre partagée.** La condition de `header.php` l. 41-44 existera en **trois** exemplaires (header, liste-statuts, carte-secours). Un `massifs_ancre_liste_existe()` dans `functions.php` supprimerait la dérive. En attendant : **renvoi croisé écrit dans les trois fichiers** | `functions.php` non attribué | Issue `a11y` ou `infra` ultérieure |
| **C-4** | **`MASTER.md` §17, divergence 10** — l'attribut de style en ligne `style="block-size:auto"` (formulation exacte au §11). Et **clôture possible de la divergence 8** : le filet `--bord-fort` de tête de bande carte s'allume avec ce repli via `.bande--carte:has(*)` | `design-system/MASTER.md` hors empreinte | `lead-design-cms`, à la révision suivante |
| **C-5** | **Recette.** `tests/rendu/recette-rendu.mjs` l. 417-420 porte le GAP en toutes lettres (« VIDE — aucune image de repli, aucun lien vers la liste ») ; l. 415 affirme `0 script[src]`, assertion qui deviendra fausse avec #7 | `tests/**` hors empreinte | `test-integration-cms`, au niveau du lot |
| **C-6** | **CSP future.** Le §9 du brief promet des en-têtes de sécurité stricts ; aucun `Content-Security-Policy` n'existe aujourd'hui. Un `style-src` strict **casserait** l'attribut de style en ligne : il faudra `style-src-attr 'unsafe-inline'`, ou avoir résolu C-2 entre-temps | domaine `securite` | Issue CSP, avec ce contrat en référence |
| **C-7** | **`docker/tiles/`, le chemin `/tiles/` et `docker/tiles/data/README.md`** deviennent sans emploi. Le README y oriente encore la chaîne carte vers un chemin **sans équivalent en production o2switch** | `docker/**` hors empreinte | `docker-cms`, en fin de lot |
| **C-8** | **`.gitignore` racine l. 33**, `package-lock.json` non ancré — dépendance hors empreinte n° 3 du contrat #2, toujours ouverte. Contournée localement par `!package-lock.json` dans `build/.gitignore`, comme #2 | racine hors empreinte | Issue `infra` ultérieure |

**Écriture hors empreinte assumée et notifiée** : `wp-content/plugins/massifs-core/data/tuiles/**`.
L'empreinte de l'issue nomme `includes/ingest/tuiles/**`, qui est **403 par construction** et ne peut donc
porter un octet servi au navigateur. `data/**` est la destination **nommément autorisée par le contrat #30
§3.6** (« caches météo / EFFIS / **tuiles à venir** ») et le `data/.gitattributes` existant **avertit
nommément** les chaînes tuiles à venir. Le sous-arbre `data/tuiles/` **n'existe pas encore** et n'est
touché par aucune chaîne sœur : sa création ne peut écraser aucun travail en cours. L'orchestrateur en est
informé.

---

## 11. Divergence à enregistrer au §17 de `MASTER.md` — formulation exacte

À insérer en ligne **10** du tableau du §17, dans le format des neuf existantes :

> | **10** | `templates/parts/carte-secours.php` porte un attribut de style **en ligne**, `style="block-size:auto"`, sur `.carte-secours__image` — seul style en ligne du thème | #9 | `layout.css` l. 56-59 pose `img, svg { max-inline-size: 100% }` **sans `block-size: auto`**. Une `<img>` portant `width`/`height` — attributs **obligatoires** pour réserver sa boîte et supprimer le saut de mise en page (§10 du brief) — est donc **écrasée verticalement** dès que sa largeur est contrainte, et l'image du département s'imprime aplatie. Les deux autres sorties sont fermées : **omettre `width`/`height`** rouvre le saut de mise en page ; **corriger `layout.css`** est hors de l'empreinte de #9 et rouvrirait un fichier appartenant à une autre chaîne, dans un arbre de travail partagé où la disjonction des empreintes est la seule protection. La déclaration en ligne a une propriété que ni l'une ni l'autre n'a : elle **fonctionne sans aucune feuille de style**, ce qui est précisément le mode que cette issue sert (§5.5). **Elle sort de cette section le jour où `layout.css` reçoit `block-size: auto` sur `img, svg`** — par une décision écrite, jamais par une correction silencieuse. |

---

## 12. Ce que la revue doit regarder en premier

1. **F-1 tenue ?** Sans inclusion inconditionnelle de `carte-secours.php`, la DoD §5.5 tombe **en
   silence**. C'est le point de rupture le plus probable de tout le lot — **et il s'est effectivement
   produit** : voir **A-13** au §13, qui amende la position de la clause et rappelle le correctif.
   L'inclusion n'appartient plus à `carte.php` mais à `front-page.php`, **après** la carte.
2. **`grep` de `wp_remote_`, `curl_`, `file_get_contents(` sur `includes/ingest/tuiles/**`** → doit rendre
   zéro, `build/recuperer.mjs` excepté (I-9.8).
3. **`grep` de `22B14C`, `E63A3C`, `openstreetmap.org/{z}`, `tile.openstreetmap`** sur tout le lot → doit
   rendre zéro hors `tokens.css` et hors la chaîne d'attribution (I-9.2, I-9.3).
4. **`esc_url()` appliqué à `url_gabarit`** — panne silencieuse, invisible à la lecture, visible seulement
   à l'exécution (F-12).
5. **L'image statique contient-elle vraiment zéro pixel vert ou rouge officiel ?** À vérifier sur le binaire,
   pas sur l'intention (I-9.3).
6. **Le build échoue-t-il vraiment** quand on falsifie un `--c-carte-*` dans `tokens.css` ? Un contrôle
   qu'on n'a pas vu rougir n'est pas un contrôle (I-9.7).

---

## 13. AVENANT du 14 août 2026 — réconciliation avec la chaîne #7 livrée

**Contexte.** Le §5 de ce contrat énonçait treize clauses « opposables à la chaîne #7 », en écrivant
qu'elles étaient « la moitié du contrat que je ne peux pas faire appliquer moi-même ». La chaîne #7 a
depuis été **livrée et commitée** (`3dda996`), et `templates/parts/carte.php` est désormais **hors de
l'empreinte de #9** : il ne peut plus être modifié par cette chaîne. Le point de réconciliation s'est
donc déplacé — il ne reste qu'un seul fichier capable de bouger, le module PHP de lecture, et c'est lui
qui s'aligne.

Trois clés divergent entre le §1.1 gelé et le consommateur livré (`carte.php` l. 428-457) :

| Clé | §1.1 d'origine | `carte.php` livré | Décision |
|---|---|---|---|
| Gabarit d'URL | `url_gabarit` | `url_modele` | **`url_modele`** |
| `format` | `'png'` | testé `=== 'raster'` | **`format` = `'raster'`** (classe de média) + **`format_tuile` = `'png'`** (extension de fichier) |
| Attribution | fonction séparée seulement | lit aussi `$fond['attribution']` et `$fond['attribution_url']` | **ajoutées au retour de `massifs_fond_de_carte()`** |

### A-11 — Nommage : c'est le module de lecture qui s'aligne, pas le thème

**Décision : les noms livrés par #7 deviennent les noms canoniques, en un seul exemplaire.**

**Raison.** Trois sorties existaient, deux sont fermées. (a) Modifier `carte.php` — interdit, empreinte
d'une autre chaîne, arbre de travail partagé sans branche. (b) Publier **les deux** orthographes
(`url_modele` **et** `url_gabarit`) — c'est exactement ce que le §1.4 de ce contrat proscrit : « une
seconde manière de poser la même question est une divergence en attente », et rien ne consomme
`url_gabarit`. (c) Aligner le module de lecture, seul fichier encore mobile : **retenue**.

`format = 'raster'` n'est pas une concession : c'est la **classe de média** de la couche, la question que
`carte.php` pose réellement avant de monter un `L.tileLayer`. L'extension de fichier reste publiée, sous
son propre nom `format_tuile`. Les deux faits sont distincts et le §1.1 les confondait.

`attribution` et `attribution_url` dans le retour de `massifs_fond_de_carte()` **ne créent pas une
seconde source de vérité** : elles sont lues du même bloc `attribution` de `data/tuiles/fond-13.php` que
`massifs_attribution_fond_de_carte()`, dont elles sont la projection en deux chaînes plates. Un seul
producteur, une seule donnée. Elles sont **liées au fond** : quand `disponible === false`, elles valent
`''`, ce qui est la traduction exacte de l'invariant I-9.4 (« on n'attribue pas une ressource absente »)
côté carte, déjà tenu par `carte.js` l. 197-219.

**Le §1.1 se lit désormais avec ce tableau.** Les clés `type`, `zoom_min`, `zoom_max`, `taille_tuile`,
`nombre`, `bbox`, `mode`, `version`, `sha256`, `octets` sont inchangées. Le §12.4 se lit « `esc_url()`
appliqué à `url_modele` » ; la clause **F-12** reste vraie mot pour mot sous le nouveau nom, et elle est
tenue : `carte.php` passe l'îlot par `wp_json_encode()`, jamais par `esc_url()`.

### A-12 — Le rapport largeur/hauteur de l'image statique : le §1.2 se corrigeait lui-même

Le §1.2 donnait **≈ 1,04** comme « contrôle de vraisemblance », en déclarant faux le 1,131 du plan back.
Le build a produit **1600 × 1421**, soit **1,1254**, dérivé de la bbox du référentiel projetée en Web
Mercator — `Δx = 1,15683°` en radians contre `Δy` mercatorien de `43,15731°` à `43,90238°`.

> **[SUPERSÉDÉ le 17 août 2026 par #71 — lire le §14.]** Conséquence directe de l'amendement du §1.1 :
> l'image statique se projette désormais sur l'**emprise déclarée**, et non plus sur la bbox du
> référentiel. Ses dimensions deviennent **1600 × 1493**, soit un rapport de **15/14 = 1,0714** —
> un rapport d'entiers, et non plus le produit d'une projection en virgule flottante. Le raisonnement
> ci-dessous est intact et sa leçon tient ; seul le **nombre** est remplacé. C'est le **quatrième**
> chiffre écrit pour cette grandeur, et le premier qui ne puisse plus dériver, parce qu'il ne dépend
> plus de la géométrie du jour.

**Décision : 1421 est juste ; c'est le 1,04 du §1.2 qui était faux.** La valeur de contrôle du §1.2 est
remplacée par **1,125**. Cela ne change aucune règle : le §1.2 posait déjà que **la dimension est une
donnée de build et jamais une constante**, et c'est précisément ce qui a permis à l'erreur du contrat de
rester sans conséquence. Troisième nombre erroné pour la même grandeur — le contrat inclus, cette fois.

### A-13 — F-1 : l'inconditionnalité est tenue, la **position** est arbitrée en faveur de F-5 / I-9.6

**Contexte.** Le §5 gelait F-1 en deux moitiés soudées : l'inclusion de `carte-secours.php` est
**inconditionnelle**, et elle est **premier enfant** de la bande. La chaîne #7 livrée plaçait l'appel en
dernière ligne de `templates/parts/carte.php`, donc **après** huit sorties anticipées de ce gabarit —
trois gardes, référentiel vide, refus d'un jour par le domaine, libellés de jour, absence de tout massif
libellé, échec de sérialisation de l'îlot. Une seule ressource vendorisée illisible suffisait à faire
disparaître tout le repli. C'était très exactement le **risque n° 1 du §12.1**, réalisé.

**Décision : l'appel sort de `carte.php` et vit dans `front-page.php`, immédiatement APRÈS
`massifs_partie( 'carte' )`.** Corrigé sur la chaîne #7 (commit `6be9408`).

**1. L'inconditionnalité est restaurée, entière.** C'est la propriété que le §12.1 nommait, et c'est
elle que le correctif protège : plus aucun `return` du gabarit de carte n'est en mesure d'emporter le
repli, puisque l'appel n'est plus dans ce fichier. `massifs_partie( 'carte-secours' )` n'apparaît
**qu'une seule fois** dans tout le thème — l'unicité est structurelle, donc **F-3** ne peut plus être
violée par une seconde inclusion défensive.

**2. « Premier enfant » est abandonné, et ce n'est pas une facilité.** La lettre de F-1 et les clauses
**F-5 / I-9.6** sont **réellement contradictoires** sous un point d'insertion unique : le repli ne peut
pas être à la fois le premier enfant de la bande et rester **sous** la carte visible. Il fallait trancher,
et c'est F-5 / I-9.6 qui l'emporte, pour une raison de fond — c'est la position dans le flux, et elle
seule, qui satisfait **D-24** sans une seule règle CSS. La review l'a vérifié indépendamment :
`.carte-secours` **ne porte aucune règle CSS dans tout le thème** et `.bande--carte` n'est ni `flex` ni
`grid` ; l'ordre du document **est** l'ordre visuel. Placer l'appel en premier enfant pousserait
l'attribution OpenStreetMap **au-dessus** de la carte, et casserait I-9.6 en silence.

**3. Le porteur est `front-page.php`, et ce contrat l'avait déjà désigné.** La couture **C-1** (§10)
nommait `front-page.php` comme voie (a) légitime pour sortir le repli des chemins de `.bande--carte`. Le
legs réciproque du contrat #7 §6, qui écrivait que « la chaîne #9 n'a aucune raison d'écrire dans
`front-page.php` », visait un monde où l'appel était dans le `<noscript>` de `carte.php` — monde supprimé
par le fix `27e267c`. L'écriture est **chirurgicale**, une seule ligne, au titre de l'arbitrage A-0 du
contrat #7.

**4. L'arbitrage est verrouillé par un test, pas par ce paragraphe.**
`tests/rendu/recette-rendu.mjs` l. 549-568 mesure la place réelle des deux nœuds par
`compareDocumentPosition` et exige `memeParent: true, descendant: false, apres: true`. Déplacer l'appel
**avant** `massifs_partie( 'carte' )` satisferait toujours « sans condition » **sans qu'aucune autre
assertion ne bronche** — d'où ce contrôle direct de l'ordre du document. Un futur développeur qui
« corrigerait » la position vers la lettre d'origine de F-1 fera **rougir ce test**.

**Ce que A-13 ne change pas** : ni F-2, ni F-3, ni F-5, ni aucun invariant I-9.*. Le repli reste rendu
par défaut hors de tout `<noscript>` (**I-9.1**), `carte.js` ne retire que `.carte-secours__repli` après
montage réussi (**F-2**), et `.carte-secours__attribution` reste unique et debout dans tous les états
(**F-3**). La couture **C-1** reste **ouverte** : le repli vit toujours dans `.bande--carte`, que
`print.css` l. 98-103 masque à l'impression.

### Ce que cet avenant ne change pas

Aucun invariant I-9.*, aucun interdit du §7, aucun `OUVERT` du §9. **Seule la clause F-1 est amendée**,
et sur sa seule moitié de *position* — voir A-13 ; son exigence d'inconditionnalité est inchangée et
désormais mieux tenue qu'au gel. `url_modele` reste
une URL de **même origine** sans query string, la version reste un **segment de chemin**, et le repli
sans JavaScript reste rendu par défaut hors de tout `<noscript>`.

---

## 14. AVENANT du 17 août 2026 — l'emprise devient déclarée, les toponymes sont cuits

**Porté par l'issue #71**, dont le contrat propre est [`docs/contracts/issue-71.md`](issue-71.md).
Cet avenant amende **§1.1, A-7 et A-9**, et **eux seuls**, plus la correction consécutive d'A-12 que
l'amendement du §1.1 rend inévitable. **§1.2 n'est pas amendé** — aucune clé n'est ajoutée à
`massifs_fond_de_carte_statique()`. Aucun invariant `I-9.*`, aucun interdit du §7, aucune clause
`F-*` n'est modifié.

### A-14 — L'emprise de la pyramide cesse d'être dérivée de la géométrie

**Le défaut, nommé.** Le §1.1 décrivait `bbox` comme « l'emprise réellement couverte, alignée sur la
grille de tuiles, sur-ensemble strict de `massifs_emprise()['bbox']` ». Cette phrase était vraie et
son contrôle était **une tautologie** : `verifier.mjs` testait un sur-ensemble sur une bbox
elle-même dérivée de la même géométrie, si bien qu'il ne pouvait jamais rougir. C'est très
exactement ce qui a permis à `ded0f2f` (retrait des îlots de moins de 25 ha) d'invalider 280 tuiles
**en silence** et de forcer `fd2c10f`.

**Décision : l'emprise est une grandeur déclarée, en coordonnées entières de tuile à z12.**

```
EMPRISE_DECLAREE = { zoom: 12, x0: 2100, x1: 2114, y0: 1490, y1: 1503 }
```

**Pourquoi des entiers de tuile et non quatre degrés.** `grille()` calcule `Math.floor( norm · 2^z )`.
L'identité `floor( floor(a·2¹²) / 2^(12−z) ) === floor( a·2^z )` fait dériver z5 à z11 des entiers z12
par simple décalage `x0 >> (12 − z)` : emboîtement **prouvé**, aucun flottant, et aucun décalage d'une
colonne quand un bord tombe pile sur une frontière de tuile. `bboxDeGrille()` rend alors exactement
l'emprise déclarée — **déclaré === publié**, propriété que quatre degrés n'auraient pas donnée.

**La marge est appliquée une fois, puis gelée.** 0,05° sur les quatre bords de
`massifs_emprise()['bbox']`, arrondis vers l'extérieur à la grille z12, **le 17 août 2026**. Elle
documente d'où viennent les quatre entiers ; elle n'est **jamais réévaluée au build** — la réévaluer
rétablirait le couplage que cet avenant supprime. Marges résiduelles obtenues : ouest 0,0861° · est
0,0754° · nord ≈ 0,058° · sud ≈ 0,089°. Seul le bord **sud** franchit une frontière de tuile
(`y1` 1502 → 1503).

**Effet sur les comptes.** z5–z11 **inchangés** ; z12 passe de 15 × 13 = 195 à 15 × 14 = **210** ;
total **280 → 295**. Les 15 tuiles ajoutées sont en mer au sud de la côte, uniformément
`--c-carte-fond` (le découpage est fait sur `terre`) : ~300–600 o pièce. La pyramide n'a **pas** de
plafond — le §10 du brief dit « hors fond de carte ».

**La phrase du §1.1 reste vraie, et devient enfin opposable.** « Sur-ensemble strict de
`massifs_emprise()['bbox']` » n'est plus une définition mais un **contrôle**, porté deux fois et de
deux manières indépendantes : un balayage O(n) sur **chaque sommet** de chaque contour dans
`construire.mjs`, avant toute rasterisation, et un contrôle au niveau de la tuile dans `verifier.mjs`.
Les deux **sortent en code ≠ 0**. Le message d'erreur prescrit de **décider** une nouvelle emprise,
jamais de la recalculer.

**Ce que cela achète, et c'était le but de l'issue** : une retouche du référentiel qui reste dans
l'emprise déclarée **ne déplace plus la bbox de la pyramide, ni ses grilles, ni son compte de tuiles**.
C'est le socle que #43 et #36 réécriront — sur un repère fixe.

> **CORRECTION du 18 août 2026 — cette phrase disait initialement « ne change plus la version publiée,
> et ne re-cuit plus 295 tuiles ». C'était trop large, et ce n'était pas mesuré.**
>
> Ce qui est **prouvé par construction** : `bboxDeclaree()` prend **zéro argument** et
> `grilleDeclaree( z )` **le seul zoom** — la géométrie ne peut structurellement pas les atteindre. Et
> la pyramide ne rend **pas** les contours du référentiel (`construire.mjs` l. 1021, `contours: null`) :
> seule l'image statique les porte.
>
> Ce qui est **faux** : le plafond de densité des toponymes vaut
> `round( densite_par_mpx × airePlacementMpx( emprise, z ) )` où `emprise` est la bbox **du
> référentiel** (l. 987 et l. 1236), choix rendu **normatif** par le §3 du contrat #71. Ce plafond
> **mord** — 4/4, 16/16, 63/63 — si bien qu'une variation d'aire de **+0,71 %** change le compte z11,
> donc les tuiles, donc `sha256( manifeste )`, donc la **version publiée**.
>
> **Le couplage est conservé et assumé**, motifs et sensibilité écrits à l'**A-13 du contrat #71**, et
> remonté en couture **C-17** à #36/#43. Le socle visé — un repère fixe — est acquis ; c'est la
> promesse d'invariance de la *version* qui était surestimée.

### A-15 — Le rapport de l'image statique cesse de dériver

L'image statique se projette désormais sur l'**emprise déclarée**, comme la pyramide : une seule
constante, une seule projection, deux artefacts qui montrent la même zone. `etendueX = 15/4096`,
`etendueY = 14/4096` sont des rationnels exacts, d'où `hauteur = round( 1600 × 14/15 ) = 1493` et un
rapport de **15/14 = 1,0714**. Résolution au sol ≈ 66,5 m/px, contre ≈ 58,7 auparavant.

`largeur` et `hauteur` restent des **données de build** (A-10), jamais des constantes — la règle du
§1.2 est inchangée. Ce qui change, c'est que la dimension ne bouge plus quand le référentiel est
retouché. **A-12 est supersédé en son seul nombre** ; son raisonnement, et la leçon qu'il tirait,
tiennent entièrement.

### A-16 — A-9 est renversé : les toponymes sont cuits dans les deux artefacts

**Les deux motifs d'A-9 sont tombés, et l'un des deux était une erreur de fait.**

- « Le placement d'étiquettes est un moteur à lui seul » — `construirePyramide()` rasterise **déjà**
  une toile entière par zoom avant de la découper. Le solveur de collision s'exécute sur cette toile,
  gratuitement, et une étiquette à cheval sur deux tuiles se recolle d'elle-même côté Leaflet.
- « `resvg` exigerait une police chargeable au build, et les deux familles sont des `woff2` » — **faux
  en pratique** : `fontkit` lit le `woff2` directement (brotli embarqué). Et la question ne se pose
  même pas, car **aucun `<text>` n'est émis** : les toponymes sont des contours de glyphes, en
  `<path>` seulement, si bien qu'aucune police n'est présente au moment de la rasterisation et
  qu'aucune substitution système n'est possible.

**Ce qu'A-9 avait vu juste et qui est conservé** : `--c-carte-encre` plafonne à **2,03:1 sur
`#E63A3C`** ; aucun rattrapage ne sauve ce ratio. C'est pourquoi la règle 8 du §4.1.d de `MASTER.md`
place les étiquettes **sous** la couche des aplats de statut, et pourquoi un nom de lieu intérieur à
un massif est **occulté, volontairement**. En raster c'est structurel : les étiquettes sont cuites
dans la tuile (`tilePane`, z-index 200), les polygones de statut sont peints au-dessus en SVG
(z-index 400). **Aucune règle CSS, aucun `createPane`, aucun `z-index` n'est requis ni autorisé.**

**Le halo est permis, et il ne contredit pas la règle 8.** L'interdit « ni halo, ni contour, ni
assombrissement de l'aplat » est **arithmétique et porté sur les aplats de statut** : il constate
qu'un halo ne rattrape pas 2,03:1. Notre halo est posé sur le **fond de carte** — et il ne peut
**structurellement** pas atteindre un aplat de statut, puisqu'il est cuit sous le calque SVG. Il peint
`--c-carte-fond`, jeton déjà présent : la palette reste **fermée à 7**.

**Mesures qui fondent la décision** — `--c-carte-encre` `#4A4E48` contre les quatre aplats du fond :
`--c-carte-fond` **6,82:1** · `--c-carte-terre` **6,33:1** · `--c-carte-vegetation` **6,03:1** ·
`--c-carte-eau` **5,68:1**. Les quatre passent AA 4,5:1. Les deux échecs — `--c-carte-trait` **4,17:1**
et `--c-charbon` **2,02:1** — sont des couleurs de **trait**, jamais d'aplat : c'est un problème de
**recouvrement**, réglé par le halo et par une règle de placement, jamais par un jeton nouveau.

**Aucun nom de massif n'est cuit, dans aucun artefact.** Dans la pyramide il serait occulté par
construction ; le cuire dans la seule statique ferait **diverger les deux artefacts**, alors que le
§5.5 du brief exige que la statique soit l'équivalent de la carte interactive. Le nom du massif est
déjà porté deux fois — liste textuelle (§5.3) et bloc 1 du panneau (`MASTER.md` §8.4). Contrôlé
mécaniquement par intersection avec les 25 noms du référentiel.

**Restent interdits, absolument, dans les deux artefacts** : titre, légende, échelle graphique, rose
des vents, libellé de statut, date, et toute mention d'attribution ou de source. Le §11.3 de
`MASTER.md` est une **liste fermée** et n'est pas touché : un toponyme est une valeur `name` d'OSM
reproduite **verbatim**, c'est-à-dire une **donnée**, jamais une chaîne de site rédigée par nous.

### Ce que cet avenant ne change pas

`massifs_fond_de_carte_statique()` conserve ses huit clés ; `SCHEMA_CONNU` reste `1` ; aucune ligne de
PHP du module ne change. Ne bougent que des **valeurs lues dans le fichier généré** : `nombre`
280 → 295, `bbox`, `hauteur` 1421 → 1493, `version`, `sha256`, `octets`. **Le thème ne change pas d'une
ligne** — il ne lit ni `nombre`, ni `bbox`, et il rend `largeur`/`hauteur` sans les recalculer.

**F-11 est intacte et le reste** : le `maxZoom` de la carte demeure `massifs_emprise()['zoom_max']`
(= 11), jamais le `zoom_max` de la pyramide. Les clauses F-1 à F-13, les invariants I-9.1 à I-9.9 et
les interdits du §7 sont inchangés, mot pour mot.
