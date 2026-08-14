# Contrat d'interface — Issue #4 — Produire le système de design de l'atelier

**Gelé le** 12 août 2026 · **Par** `lead-issue-cms` (chaîne #4) · **Statut** contraignant.

Cette issue ne touche **que le thème** (présentation). Aucune écriture dans `massifs-core`.
Il n'y a donc pas de contrat front↔back au sens habituel : ce document est le contrat
**design-system → chaînes d'intégration**, c'est-à-dire ce que les chaînes #5 et #6 peuvent
consommer sans risque, et ce qu'elles doivent faire pour que ces artefacts servent.

---

## Empreinte fichiers de la chaîne #4

Écriture autorisée, et rien d'autre :

- `design-system/MASTER.md`
- `wp-content/themes/massifs/assets/css/tokens.css`
- `wp-content/themes/massifs/assets/fonts/**`
- `docs/contracts/issue-4.md` (ce fichier)

**Explicitement hors empreinte**, propriété des chaînes #5/#6 : `functions.php`, `style.css`,
`assets/css/layout.css` et toute autre feuille de `assets/css/`, `front-page.php`, `templates/**`.

---

## Fonctions de lecture exposées par l'extension

**Aucune.** Cette issue n'ajoute, ne modifie et ne consomme aucune fonction PHP de `massifs-core`.

## Routes REST

**Aucune.**

---

## Artefacts produits

| Fichier | Rôle |
|---|---|
| `assets/css/tokens.css` | **116 propriétés personnalisées** sur `:root`, 5 redéclarées sous `.sur-sombre`, **4 sous `.carte--echelle-departement`**, **4 sous `.carte--echelle-abords`**, 1 sous `@media (min-width: 37.5rem)`, 3 sous `@media (prefers-reduced-motion: reduce)` — **133 dans le fichier entier**. Transcription **verbatim** du bloc normatif de `MASTER.md` §12. **Invariant amendé le 15 août 2026 (chaîne #50)** : il valait 111 / 120 ; voir le §« Amendement v2.4 » en fin de document. |
| `assets/fonts/fonts.css` | Les deux `@font-face`, et **rien d'autre** (aucun sélecteur, aucun `:root`). |
| `assets/fonts/big-shoulders-display-var.woff2` | Titrage, variable `wght`, 35 436 o. |
| `assets/fonts/atkinson-hyperlegible-next-var.woff2` | Labeur, variable `wght`, 33 996 o. |
| `assets/fonts/LICENSE-big-shoulders-display.txt` | OFL 1.1 amont, verbatim. |
| `assets/fonts/LICENSE-atkinson-hyperlegible-next.txt` | OFL 1.1 amont, verbatim. |
| `assets/fonts/PROVENANCE.md` | URL source, version, sha256, date, relevés de vérification. |

---

## Table des jetons CSS exposés par `tokens.css`

**Contrat de nommage** : noms en **ASCII pur**, sans accent. Aucune valeur littérale de couleur,
d'espacement ou de durée ne doit apparaître ailleurs dans le CSS du thème.
Aucun jeton de cette table n'est renommé par l'issue #4.

### Surfaces et encres (10)
`--c-calcaire` · `--c-calcaire-ombre` · `--c-poussiere` · `--c-trace` · `--c-garrigue`
`--c-charbon-doux` · `--c-charbon` · `--c-mistral-nuit` · `--c-mistral` · `--c-mistral-clair`

### Fond de carte (6)
`--c-carte-fond` · `--c-carte-terre` · `--c-carte-vegetation` · `--c-carte-eau`
`--c-carte-trait` · `--c-carte-encre`

### Statuts officiels — aplats (4) — **valeurs non modifiables**
`--statut-autorise` · `--statut-interdit` · `--statut-zapef-autorise` · `--statut-zapef-interdit`

### Statuts officiels — encres de motif (4)
`--statut-autorise-encre` · `--statut-interdit-encre`
`--statut-zapef-autorise-encre` · `--statut-zapef-interdit-encre`

### États hors niveau (6)
`--statut-indisponible` · `--statut-indisponible-encre`
`--statut-hors-saison` · `--statut-hors-saison-encre`
`--statut-non-publie` · `--statut-non-publie-encre`

> Les **14 jetons** des trois groupes ci-dessus sont exactement ceux qu'émet
> `wp-content/plugins/massifs-core/includes/domain/statuts/legende.config.php`
> (clés `jeton_css` / `jeton_encre_css`). Correspondance vérifiée ligne à ligne.
> **Renommer l'un d'eux casse la chaîne #6 en silence.**

### Liseré et motif de statut (4)
`--statut-lisere` · `--statut-lisere-epaisseur` · `--statut-motif-trait` · `--statut-motif-pas`

> `--statut-lisere` n'est émis par **personne** côté extension : il est purement thème.
> C'est lui qui **porte la conformité AA**, pas la teinte.

> **[15 août 2026, chaîne #50] `--statut-lisere-epaisseur` est l'épaisseur HORS CARTE** — pastille,
> jalon, légende, liste, panneau, portail, impression : des objets de taille fixe à l'écran. Elle reste
> à **2 px** et **ne devient jamais variable**. Sur la carte, l'épaisseur suit le palier de zoom (groupe
> suivant). La consommer sur un `stroke` de carte est un défaut.

### Épaisseurs de trait de la carte (4) — **[v2.4, ajoutées le 15 août 2026]**
`--carte-lisere` · `--carte-survol` · `--carte-cerne` · `--carte-cerne-clair`

> Variables **par palier de zoom** (MASTER §9.2.a). Les valeurs de `:root` sont celles du palier
> **`massif`** (z10), délibérément le **milieu** de l'échelle : une carte à laquelle aucune classe de
> palier n'a été posée se rend donc dans un état **conforme**, jamais sans trait. Un trait SVG est
> **centré** — ces valeurs sont des `stroke-width`, **jamais des largeurs vues**.
> **Plancher mesuré, jamais franchi : 1,5 px** (§10.2.a).

### Liseré de sélection hors carte (1) — **[v2.4, ajouté le 15 août 2026]**
`--bord-selection`

> Le liseré de l'état « sélectionné » dans le **chrome** — bouton de jour de la carte, paire segmentée
> du portail. Sans rapport avec la carte : ces objets ne changent pas d'échelle. **Ce n'est pas
> `--bord-fort`**, qui est l'abréviation « 4px solid … », plafonnée à une occurrence par page et
> inutilisable comme simple épaisseur.

### Typographie — familles (2)
`--police-titre` · `--police-texte`

### Typographie — tailles (9)
`--fs-100` · `--fs-200` · `--fs-250` · `--fs-300` · `--fs-400` · `--fs-500` · `--fs-600` · `--fs-700` · `--fs-800`

### Typographie — interlignes (5)
`--lh-affiche` · `--lh-titre` · `--lh-sous` · `--lh-dense` · `--lh-corps`

### Typographie — approches (3)
`--ls-affiche` · `--ls-titre` · `--ls-etiquette`

### Typographie — poids (4)
`--poids-titre` · `--poids-affiche` · `--poids-texte` · `--poids-texte-fort`

### Typographie — mesures de ligne (2)
`--mesure` · `--mesure-etroite`

### Espacement (13)
`--esp-3xs` · `--esp-2xs` · `--esp-xs` · `--esp-s` · `--esp-m` · `--esp-l` · `--esp-xl`
`--esp-2xl` · `--esp-3xl` · `--esp-4xl` · `--esp-section` · `--gouttiere` · `--largeur-max`

### Rayons, bordures, élévation (9)
`--r-0` · `--r-1` · `--bord-fin` · `--bord-champ` · `--bord-moyen` · `--bord-fort`
`--ombre-0` · `--ombre-decalee` · `--ombre-decalee-sombre`

### Signature « le repère » (4)
`--repere-largeur` · `--repere-decalage-x` · `--repere-decalage-y` · `--repere-couleur`

### Pastilles, jalons, frise (6)
`--pastille-l` · `--pastille-h` · `--jalon-cote` · `--jalon-hampe` · `--frise-l` · `--frise-h`

### Focus (6)
`--focus-trait` · `--focus-trait-inverse` · `--focus-halo`
`--focus-epaisseur` · `--focus-ecart` · `--focus-halo-epaisseur`

### Cibles (1)
`--cible-min`

### Mouvement (5)
`--duree-court` · `--duree-moyen` · `--duree-long` · `--ease-net` · `--ease-retrait`

### Points de rupture — documentaires (3)
`--bp-s` · `--bp-m` · `--bp-l`

> `@media` n'accepte pas les `var()` : ces trois jetons sont **documentaires**.
> Les requêtes média s'écrivent en dur avec la même valeur.

### Plans d'empilement (5)
`--z-carte` · `--z-panneau` · `--z-barre-action` · `--z-bandeau` · `--z-evitement`

**Total : 116 propriétés sur `:root`** — 111 à l'origine, **+ 5 par la révision v2.4** de `MASTER.md`
(les quatre épaisseurs de carte et `--bord-selection`).

### Les **trois** seules redéfinitions locales autorisées

1. `--repere-couleur` — posée par un composant quand le repère précède une information de statut.
2. Le groupe `--statut-lisere` / `--statut-*-encre` sous `.sur-sombre`.
3. **[v2.4, 15 août 2026]** Les quatre épaisseurs de trait de la carte sous les **classes de palier de
   zoom**, en fin de fichier : `.carte--echelle-departement` (4 déclarations, dont
   `--carte-cerne-clair: 0`) et `.carte--echelle-abords` (4 déclarations). **Il n'existe aucun bloc
   `.carte--echelle-massif`, et il ne faut pas en écrire un vide** : le palier médian est celui de
   `:root`, et l'absence *est* la décision (§9.2.a, règle de tenue 3).

Toute autre redéfinition d'un jeton hors `:root` est un défaut.

---

## Amendement v2.4 — l'invariant de comptage et l'empreinte — **15 août 2026, chaîne #50**

Le §12 de `MASTER.md` n'avait **pas été rouvert** depuis la v2.0 ; trois révisions s'en étaient
abstenues, et elles avaient raison. La **v2.4** le rouvre, et le motif est d'une autre nature qu'une
préférence de composition : sans épaisseur de trait variable par palier de zoom, **un aplat de statut est
recouvert à l'écran et un massif devient illisible** (défaut constaté en Chrome, issue #50). Il
n'existait aucune autre voie — le JS de la carte n'a pas le droit d'écrire un style, la valeur doit donc
vivre en CSS et varier par classe.

| | Avant (v2.0 → v2.3) | Après (v2.4) |
|---|---|---|
| `:root` | 111 | **116** |
| `.sur-sombre` | 5 | 5 |
| `.carte--echelle-departement` | — | **4** |
| `.carte--echelle-abords` | — | **4** |
| Les deux `@media` | 4 | 4 |
| **Fichier entier** | **120** | **133** |

**Le sha256 épinglé tombe avec le compte.** C'est la conséquence mécanique et **acceptée** de la
révision, annoncée au §12 de `MASTER.md` et au §17.1, pas un effet de bord découvert après coup.

- Ancienne empreinte, **caduque** : `5ad802a3708fe1734845e7a76b46de5382f2421268542584cafa270d29aa3835`
- Nouvelle empreinte : `104efb21fefa0e42b55ba0707ead5755e940b2cd87e4b1cff4c70148aeec112f`
  (**vérifiée par le lead après écriture**, et non recopiée : le fichier porte bien cette empreinte, et
  les comptes 116 / 133 ont été mesurés, pas repris de la révision)

**Méthode de comptage opposable.** Plusieurs jetons partagent une même ligne ; une ancre `^` fausserait
le compte. La commande fait foi, et un compte mesuré prime toujours sur un compte recopié :

```
grep -oE '(^|[[:space:]{;])--[a-z0-9-]+[[:space:]]*:' tokens.css | wc -l
```

**Aucune valeur de couleur n'est touchée. Aucun jeton n'est supprimé ni renommé.** Les cinq déclarations
et les deux classes de palier sont des **ajouts**, insérés à leur rubrique.

### Ce que cet amendement laisse ouvert, hors empreinte de la chaîne #50

Le compte `111` et l'ancienne empreinte sont épinglés **ailleurs que dans ce contrat**, dans des fichiers
que la chaîne #50 n'a pas le droit d'écrire. **À reprendre par leurs propriétaires** :

| Fichier | Ligne | Ce qui doit changer |
|---|---|---|
| `tests/rendu/recette-rendu.mjs` | 1415 | sha256 épinglé → la nouvelle empreinte |
| `tests/rendu/recette-rendu.mjs` | 1424 | `egal( 111, … )` → **116** |
| `docs/contracts/issue-11.md` | 57, 679 | « gelé, sha256 épinglé, 111 propriétés » |
| `docs/contracts/issue-21.md` | 70, 288 | empreinte recopiée, « inchangé » |
| `docs/contracts/issue-23.md` | 29, 411 | « gelé, sha256 épinglé, 111 propriétés » |

> L'assertion « `tokens.css` est la transcription exacte du bloc normatif de MASTER §12 »
> (`recette-rendu.mjs` l. 1434) **reste vraie et doit le rester** : le fichier est transcrit, jamais
> édité à la main.

---

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | `legende.config.php` → `etats_hors_niveau.indisponible`, motif `hachure_descendante`, jetons `--statut-indisponible` / `-encre` | Aplat + motif + libellé. **Jamais** présenté comme un niveau. |
| `hors_saison` | `etats_hors_niveau.hors_saison`, motif `aucun` | Idem, bloc de légende séparé `SUR CE SITE`. |
| `donnee_perimee` | Bandeau d'alerte (hors périmètre #4) | Bandeau, bord gauche portant le repère. |
| `couche_effis_indisponible` | Hors périmètre #4 | — |
| `non_encore_publie` | `etats_hors_niveau.non_encore_publie`, motif `pointille` | Aplat + motif + libellé. |

L'issue #4 ne fait que **fournir les pigments** de ces états. Elle n'en rend aucun.

---

## Chaînes fournies par le serveur

Aucune chaîne n'est produite par cette issue. Rappel de la frontière, inchangé :
les libellés de niveau, les consignes, les attributions et la phrase de fraîcheur
appartiennent au serveur. Le thème les rend, il ne les compose jamais.

---

## Interdits

- Le thème n'appelle jamais une source externe ni une fonction d'ingestion.
- Le thème ne calcule jamais une règle métier (saison, péremption, formatage de niveau).
- **Le thème ne calcule pas non plus le chiffre du jour, son dénominateur, ni une date** :
  ces valeurs viennent de l'extension.
- L'extension n'émet jamais de HTML de présentation publique.
- **Aucun `@font-face` ailleurs que dans `assets/fonts/fonts.css`** (D-21).
- **Aucun fichier de police servi hors de `assets/fonts/`**, et jamais depuis un CDN.
- **Jamais plus de 2 fichiers de police.** Le budget est saturé exactement.
- Aucune valeur hexadécimale de statut écrite hors de `tokens.css`.
- Aucun jeton de statut numéroté (`--statut-1`, `--statut-niveau-3`…), sous quelque forme que ce soit.
- **Aucun bloc `prefers-color-scheme`, aucune palette sombre alternative** (D-23).

---

## Décisions gelées par cette issue

| # | Décision | Raison |
|---|---|---|
| **D-20** | Sous-ensemble **`latin` seul** | Vérifié sur la cmap réelle : tous les glyphes français utiles sont dans `latin` ; `latin-ext` ne contient que `U+0100+`. Prendre les deux ferait **4 fichiers contre un budget dur de 2**, pour zéro glyphe utile. |
| **D-21** | Les `@font-face` vivent dans `assets/fonts/fonts.css` | Répertoire autosuffisant, `url("./…")` incassable, **et le bloc normatif §12 reste intact** pendant que les chaînes #5/#6 le lisent. |
| **D-22** | `font-display: optional` + **preload obligatoire** des deux fichiers, aucun descripteur de métriques | Seule option qui garantit structurellement le « pas de sauts perceptibles » du brief §10 sans inventer un `size-adjust` indérivable. Voir l'arbitrage A-3. |
| **D-23** | **Pas de mode sombre** ; `color-scheme: light` à déclarer par la chaîne #5 | Toute la preuve §10 est calculée contre la palette claire ; les deux teintes officielles ne sont pas re-tonalisables. |
| **D-24** | **Ordre des couches** : les libellés du fond de carte sont rendus **sous** les aplats de statut ; tout chrome de carte flottant repose sur un aplat opaque `--c-calcaire` | `--c-carte-encre` plafonne à **2,03:1** sur `#E63A3C`. La règle §4.1.d n°3 (« aucun texte sur un aplat de statut ») n'est applicable sur la carte que par un ordre de couches. Coût nul en raster comme en vectoriel. |
| **D-25** | La flèche `→` (U+2192) de §7.2 est rendue en **SVG en ligne**, jamais en caractère | Mesuré : U+2192 est **absent des deux polices**. Un caractère afficherait un rectangle. Cohérent avec §16 (« les rares symboles sont du SVG en ligne »). |

---

## Arbitrages

| # | Désaccord | Décision retenue | Raison |
|---|---|---|---|
| **A-1** | Le cadrage de l'issue dit « rédiger le plan de design », or `MASTER.md` v2.0 existe déjà et vaut 1580 lignes | **Issue re-cadrée en issue d'artefacts.** `MASTER.md` est amendé **par ajout et correction ciblée**, jamais réécrit | Les chaînes #5 et #6 lisent le document **en vol**. Le risque dominant n'est pas de sous-livrer, c'est d'écraser leur référence. Les lignes 1, 2 et 5 de la checklist étaient déjà tenues sur le fond |
| **A-2** | Où vivent les `@font-face` : dans `tokens.css` (mon inclination initiale) ou dans un fichier dédié | **`assets/fonts/fonts.css`** (D-21) | `tokens.css` en tête aurait imposé de modifier le bloc normatif §12 au moment précis où deux chaînes le consomment. `fonts.css` est dans mon empreinte, et `@font-face` étant insensible à la cascade, ce fichier ne peut entrer en concurrence avec aucune feuille des chaînes #5/#6, quel que soit l'ordre d'enqueue |
| **A-3** | `font-display` : `swap` (statu quo de `MASTER.md` §5) contre `optional` | **`optional` + preload obligatoire** | `MASTER.md` §5 promet un `size-adjust` « calibré pour supprimer le saut ». Mesuré, **cette promesse n'est pas tenable** : posé sur la face web, `size-adjust` ne supprime aucun saut, il met la police à l'échelle ; la technique correcte exige une **3ᵉ face de repli**, interdite par le budget de 2 fichiers. Et aucune valeur unique n'est dérivable, `system-ui` désignant quatre polices selon l'OS et `Arial Narrow` étant absent d'Android et de la plupart des Linux. **Alternative écartée, à assumer** : `optional` fait que le tout premier affichage d'un visiteur sur connexion lente se fait en police système. Contrepartie acceptée parce que l'identité du site ne repose pas sur la fonte (ardoise sombre, aplats, liseré charbon, repère, rayon nul, fond monochrome survivent tous) et que la police s'applique dès la vue suivante |
| **A-4** | `forced-colors: active` : `MASTER.md` §3.4 et §10.6 l'exigent, mais §12 ne fournit ni jeton ni bloc pour l'obtenir | **Ne pas toucher `tokens.css`.** Traité dans le CSS de rendu des chaînes #5/#6 ; exigence + ligne de revue consignées dans `MASTER.md` | Les motifs de statut sont des `background-image` : leur survie en couleurs forcées est une affaire de **règles de rendu**, pas de jetons. Ajouter un bloc spéculatif à §12 aurait modifié le bloc normatif sans bénéfice certain |
| **A-5** | `MASTER.md` §8.4 et §7.1 ne portent nulle part les « communes concernées », que le brief §5.2 exige explicitement | **Ne pas écrire cette édition.** Remontée à l'orchestrateur comme constat inter-chaînes | Elle crée un **besoin de donnée côté extension**, donc modifie le contrat de la chaîne #6 en cours d'exécution. Hors périmètre de l'issue #4. Conséquence assumée : D-24 est justifié **sans** s'appuyer sur l'argument « les communes sont portées par le texte », qui était faux |
| **A-6** | `MASTER.md` §8.2 promet un chiffre du jour stable via `font-variant-numeric: tabular-nums` | **Promesse corrigée dans `MASTER.md`** ; la réservation de largeur revient aux chaînes #5/#6 | Mesuré : **Big Shoulders Display n'expose pas la feature `tnum`** et ses chiffres sont fortement proportionnels (au poids 800, `1` = 511 unités contre `5` = 961, soit ~450/2000 em ≈ **29 px d'écart à `--fs-800`**). `font-variant-numeric` y est un **no-op silencieux**. Atkinson, lui, expose bien `tnum` |

---

## Dépendances rapportées — pour la chaîne #5 (`functions.php`)

Sans ces éléments, **les artefacts de l'issue #4 ne sont chargés par rien**. Le thème ne
contient aujourd'hui que `style.css` et `index.php` ; il n'y a **aucun `functions.php`**.

### Enqueues

| Poignée | Fichier | Dépendances | Version |
|---|---|---|---|
| `massifs-fonts` | `assets/fonts/fonts.css` | `[]` | `filemtime` |
| `massifs-tokens` | `assets/css/tokens.css` | `[]` | `filemtime` |
| `massifs-style` | `style.css` | `['massifs-tokens']` | `filemtime` |
| *(toute autre feuille du thème)* | — | **`['massifs-tokens']` obligatoire** | `filemtime` |

- Utiliser `get_theme_file_uri()` / `get_theme_file_path()`.
- `massifs-tokens` est dépendance **universelle** : une feuille qui emploie `var(--…)` en
  déclarant `[]` est un défaut.
- **Preload obligatoire** des deux `.woff2` dans `wp_head` (priorité basse, avant
  `wp_print_styles`), avec `as="font" type="font/woff2"` et **`crossorigin`** — obligatoire
  même en même origine ; l'omettre provoque un **double téléchargement**.
  Sans preload, **D-22 perd tout son intérêt**.

### Dequeues — contrainte #2 (zéro requête tierce)

Aujourd'hui **aucun dequeue n'existe**, donc toutes les fuites WordPress par défaut sont actives :

- script de détection émoji + repli twemoji → **`s.w.org`** ;
- `dns-prefetch` vers **`s.w.org`** ;
- oEmbed distant, `wp-embed` ;
- avatars Gravatar → `secure.gravatar.com` ;
- `wp-block-library`, `global-styles`, filtres duotone SVG (poids mort).

`add_filter( 'emoji_svg_url', '__return_false' )` est la ceinture-bretelles décisive :
même si un script émoji fuit, plus aucune URL distante n'est composée.

### Autres

- `html { color-scheme: light; }` (D-23) — sans quoi, sous un OS en thème sombre, le
  navigateur assombrit d'office les contrôles natifs du portail et invalide les hypothèses
  de `--bord-champ`.
- `font-synthesis: none`.
- `--z-carte: 0` crée un **contexte d'empilement** si le conteneur de carte porte
  `position: relative` : le panneau à `--z-panneau: 1100` doit alors être rendu **hors** de ce
  conteneur.
- Page « Mentions légales » : créditer les deux familles, « SIL Open Font License 1.1 »,
  avec lien vers les fichiers de licence locaux. **Ligne de DoD §12 du brief.**
- Contrôler la composition de l'ardoise **polices désactivées** (repli non condensé), à
  360 px et à 200 % de zoom — conséquence directe de D-22.
- Réserver une largeur fixe au chiffre du jour (A-6), `tabular-nums` étant inopérant.

### Infra

`Content-Type: font/woff2` · `Cache-Control: public, max-age=31536000, immutable` ·
**ne pas recompresser les `.woff2`** (déjà Brotli en interne).

---

## Questions bloquantes remontées à l'orchestrateur

1. **Budget** — les deux fichiers de police comptent-ils **dans** les 250 Ko HTML+CSS+JS du
   brief §10, ou seulement contre le plafond « deux fichiers » ? Le brief les énumère
   séparément, ce qui suggère qu'ils sont hors enveloppe, mais ce n'est pas écrit.
   L'écart est de **69,4 Ko**, soit 27,8 % du budget — matériel pour la chaîne carto.
   **Non tranché par déduction.**
2. **`OUVERT` préexistant** — la reproduction de la légende préfectorale est-elle autorisée,
   et sous quelle mention de source ? (`MASTER.md` §4.1.e Q8.) Également une ligne de DoD §12.
3. **`OUVERT` préexistant** — la consigne officielle par état (`MASTER.md` §4.1.e Q4).
   Non bloquant pour #4 : l'emplacement §8.4 est conçu pour rester muet.
4. **Constat inter-chaînes (A-5)** — « communes concernées » exigé par le brief §5.2, absent
   de `MASTER.md` §8.4 et §7.1, et absent de la charge du panneau massif.
   À traiter dans une issue dédiée ; impacte le contrat de la chaîne #6.
