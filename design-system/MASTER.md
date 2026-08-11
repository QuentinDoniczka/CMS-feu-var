# MASSIFS — Design System

**Version** 1.0 · **Date** 11 août 2026 · **Auteur** `lead-design-cms`
**Statut** source de vérité visuelle. Tout travail d'intégration (`dev-ux-cms`, `dev-front-cms`) et toute
relecture (`review-cms`) s'y réfèrent. Livrable §11 du brief (« plan de design »).

> Règle de lecture : ce document décrit des **décisions**, pas des suggestions. Une valeur qui n'est pas
> ici n'existe pas dans le CSS. Une divergence constatée en revue est un défaut, pas une variante.
> Les blocs marqués **`À CONFIRMER`** sont des trous de connaissance assumés : ils bloquent la mise en
> production de la légende, jamais l'intégration du reste. On ne les remplit pas par déduction (§4.2 du brief).

---

## 1. Intention de design — en cinq lignes

1. Le site ressemble à un **panneau de départ de sentier repeint chaque soir** : peinture mate sur support
   minéral, angles vifs, aucune matière, aucune profondeur simulée.
2. La carte est **le héros absolu** : elle est le seul endroit du site où de la couleur saturée apparaît,
   parce que la seule couleur saturée autorisée est celle de la légende préfectorale.
3. Tout le reste — chrome, texte, portail — est **calcaire et bleu mistral** : deux familles de tons froids,
   minérales, qui ne peuvent jamais être confondues avec un niveau de danger.
4. La hiérarchie ne vient ni de l'ombre ni du contour arrondi : elle vient de **l'échelle, de la casse et
   d'un unique repère peint**, la signature du site (§3).
5. Ce que ça doit faire ressentir : *une information officielle relayée par quelqu'un de sérieux* — sobriété
   de service public, mais avec la brutalité graphique d'une signalétique de terrain, pas d'un intranet.

**Le pari central, celui qui tient tout** : le fond de carte auto-hébergé est **monochrome calcaire**.
Aucune route bleue, aucun bois vert, aucun bâti rose. Résultat : les massifs colorés sont les seules taches
de couleur de l'écran. C'est à la fois le geste le plus spectaculaire (une capture d'écran illisible chez
les concurrents devient évidente ici) et le plus fonctionnel (le contraste des statuts n'est plus pollué).
Tout le système en découle.

---

## 2. Ancrage dans le sujet — d'où viennent les tons

| Anchor | Ce qu'on en tire | Ce qu'on refuse d'en tirer |
|---|---|---|
| **Calcaire** (Calanques, Sainte-Victoire) | Les surfaces : un blanc-gris **froid, légèrement vert**, pas un crème chaud | Le crème beige « papier ancien » (tell IA §7) |
| **Mistral** (le ciel lavé après le vent) | Le chrome : bleu profond, froid, un peu grisé — la seule couleur d'interface | Le bleu « corporate SaaS » saturé et lumineux |
| **Pin d'Alep** | Le vert-gris rabattu du fond de carte végétal, à très faible chroma | Un vert d'interface — il entrerait en collision avec le niveau « autorisé » |
| **Garrigue** | Le gris-vert des filets, des textes tertiaires, des courbes | Un olive décoratif |
| **Charbon** (le bois brûlé) | L'encre. Un noir tiré vers le vert, jamais un `#000` | Le noir pur + accent acide (tell IA §7) |
| **Balisage peint** (blaze GR/PR) | **La signature** (§3) et l'idée de la marque repeinte par-dessus l'ancienne | Le rouge/blanc littéral du GR : ce sont presque les couleurs de la légende |
| **Panneaux DFCI** | La discipline typographique : capitales condensées, sérigraphie, rayon nul | Le pastiche de panneau (bordure double, coin coupé, « effet plaque ») |
| **Barrière DFCI** | Le vocabulaire de **hachures** obligatoire pour l'encodage non chromatique (§4.3) | La hachure comme décor |

**Terracotta et ocre sont bannis du système**, alors qu'ils seraient le réflexe « Provence ». Deux raisons :
c'est le tell IA nommé au §7 du brief, et surtout la terre cuite est à quelques degrés de teinte de l'orange
de la légende — un aplat décoratif terracotta à côté d'un massif orange créerait une ambiguïté de sens.
La contrainte fonctionnelle et la contrainte esthétique disent la même chose.

### 2.1 Règle de non-collision chromatique (structurante)

> **La palette du site ne contient aucune teinte comprise entre 15° et 160° (jaune, orange, rouge, vert) au-delà
> de 12 % de saturation. Ces teintes appartiennent exclusivement à la légende officielle.**

Conséquences directes, à respecter sans exception :
- pas de bouton vert « valider », pas de message d'erreur rouge, pas d'alerte orange ;
- le succès, l'erreur et la péremption sont portés par le **chrome mistral + un libellé explicite + une hachure**,
  jamais par une couleur sémantique (§9.4) ;
- l'indicateur « Météo des forêts » (§4.3 du brief) n'utilise **aucune couleur** : il utilise une échelle de
  points en charbon (§8.4). C'est la traduction visuelle de l'exigence « jamais fusionné avec le statut ».

---

## 3. La signature : **le repère**

> **Une phrase** : toute information de statut est précédée d'un *repère* — une barre peinte de 8 px doublée
> d'une trace décalée de 3 px vers la droite et 4 px vers le bas, comme une balise de sentier repeinte
> par-dessus celle de la saison précédente.

C'est le sujet même du site rendu visible : **une marque qu'on repeint tous les soirs**, l'ancienne encore
visible dessous. L'indicateur de fraîcheur n'est plus une mention en petits caractères, c'est la forme
de base du système.

### 3.1 Construction CSS (référence normative)

```css
/* Le repère — élément de signature. Une seule implémentation, réutilisée partout. */
.repere {
  position: relative;
  padding-left: var(--esp-l);          /* 24px : 8 (barre) + 3 (décalage) + 13 (respiration) */
}
.repere::before,                        /* la trace : l'ancienne peinture */
.repere::after {                        /* le repère : la peinture du jour */
  content: "";
  position: absolute;
  left: 0;
  top: 0.14em;                          /* aligné sur la hauteur de capitale, pas sur la boîte */
  width: 8px;
  height: 0.86em;
  min-height: 20px;
}
.repere::before {
  transform: translate(3px, 4px);
  background: var(--c-trace);
}
.repere::after {
  background: var(--repere-couleur, var(--c-mistral-nuit));
}

/* Variante longue : bord gauche d'un panneau ou d'un bandeau */
.repere--bloc::before,
.repere--bloc::after { height: 100%; top: 0; min-height: 0; }

/* Variante inversée sur chrome sombre */
.sur-sombre .repere::before { background: var(--c-mistral); }
.sur-sombre .repere::after  { background: var(--c-calcaire); }
```

`--repere-couleur` est la **seule** custom property que les composants ont le droit de redéfinir localement :
elle prend la couleur officielle du niveau quand le repère précède une information de statut.

### 3.2 Où il apparaît — liste fermée

1. Devant le **chiffre du jour** dans l'ardoise (version `--bloc`, pleine hauteur, à gauche du slab).
2. Devant **chaque `h2`** du site (couleur `--c-mistral-nuit`).
3. Devant **chaque puce de statut** dans la légende et dans la liste du jour (couleur = niveau officiel).
4. Sur le **bord gauche du panneau massif** (version `--bloc`, couleur = niveau du massif sélectionné).
5. Sur le **massif sélectionné dans la carte** : contour `--c-calcaire` 4 px + contour `--c-charbon` 4 px
   décalé de (3 px, 4 px), rendu par duplication du tracé dans un pane Leaflet dédié
   `transform: translate(3px, 4px)` sous le pane des tracés.
6. Sur le **bord gauche du bandeau d'alerte** (péremption, source indisponible, hors-saison).
7. Sur le **bord gauche de la barre d'action** du portail (« Publier les statuts »).

### 3.3 Où il ne doit jamais apparaître

- Dans le corps de texte, dans les listes à puces éditoriales, dans les notes de bas de page.
- Sur les `h3`, `h4` (la hiérarchie basse se fait à la taille et à la casse).
- Sur les boutons, les champs de formulaire, les liens.
- Dans le pied de page, sur les logos, en filet décoratif horizontal.
- **Plus d'une fois par bloc visuel** : deux repères adjacents cassent la métaphore (on ne repeint pas deux
  fois la même balise). Si deux candidats coexistent, le plus proche de l'information de statut gagne.
- **Jamais animé.** La peinture ne bouge pas.

### 3.4 Dégradation

- **Sans JS** : intégralement présent — c'est du CSS pur sur du HTML rendu par PHP. Sur la carte remplacée
  par l'image statique, le repère reste sur les titres, la légende et la liste.
- **À 360 px** : inchangé (8 px + 3 px = 11 px de gouttière, `padding-left` réduit à `--esp-m` = 16 px).
- **Sans CSS** : rien à dégrader, c'est décoratif — les pseudo-éléments ne portent aucune information.
- **`forced-colors: active`** : `background: CanvasText` pour `::after`, `background: GrayText` pour `::before`.
- **Impression** : conservé en noir 100 % (`::after`) et gris 45 % (`::before`), c'est ce qui donne à la
  page imprimée sa signature.

---

## 4. Palette

### 4.1 Statuts officiels — **non modifiable, reproduit la légende préfectorale**

> **`À CONFIRMER` — bloquant avant mise en ligne.**
> Le §4.2 du brief interdit d'inventer la légende. Les valeurs ci-dessous sont des **substituts de travail**
> permettant d'intégrer et de tester le système ; elles ne sont pas la légende officielle et doivent être
> remplacées telles quelles par les valeurs relevées sur `risque-prevention-incendie.fr/13`.
> Le système est conçu pour que **seuls les hex changent** : structure, hachures, encres et libellés sont
> paramétrés par niveau.

| Niveau | Libellé (substitut) | Couleur (substitut) | Motif obligatoire | Encre sur l'aplat | vs légende `--c-calcaire` | vs fond carte `--c-calcaire-ombre` |
|---|---|---|---|---|---|---|
| 1 | Accès autorisé | `#1E7A3C` | aucun (aplat) | `--c-calcaire` (5,38:1) | **4,62:1** conforme | **4,01:1** conforme |
| 2 | Accès autorisé, vigilance | `#E8B21C` | pointillé 1 px / pas 6 px | `--c-charbon` (8,84:1) | 1,67:1 ÉCHEC → liseré obligatoire | 1,45:1 ÉCHEC → liseré obligatoire |
| 3 | Accès réglementé | `#D2621B` | hachure montante `/` 1,5 px / pas 8 px | `--c-charbon` (5,31:1) | 3,28:1 conforme (non-texte) | 2,85:1 ÉCHEC → liseré obligatoire |
| 4 | Accès interdit | `#B4231E` | hachure croisée `×` 2 px / pas 6 px | `--c-calcaire` (6,56:1) | **5,64:1** conforme | **4,89:1** conforme |
| 5 | Accès interdit, risque exceptionnel | `#171717` | aplat + croisillon calcaire 1,5 px / pas 7 px | `--c-calcaire` (17,93:1) | **15,42:1** conforme | **13,37:1** conforme |
| — | Information non disponible | `--c-calcaire-ombre` | hachure descendante `\` `--c-trace` 2 px / pas 10 px | `--c-charbon` (12,79:1) | — | — |
| — | Dispositif estival inactif | `--c-calcaire-ombre` | aucun | `--c-charbon-doux` (6,33:1) | — | — |

**Règles inviolables attachées à ce tableau :**

1. **`fill-opacity: 1`** sur tous les polygones de massif. Aucune transparence : les ratios mesurés ci-dessus
   ne tiennent que sur aplat opaque. C'est aussi ce qui donne à la carte son aspect « formes peintes ».
2. **Liseré `--c-charbon` 2 px** sur tout polygone de massif, **sauf le niveau 5** (liseré `--c-calcaire` 2 px).
   Ce liseré n'est pas décoratif : c'est lui qui garantit le 3:1 de limite de forme (WCAG 1.4.11) pour les
   niveaux 2 et 3, qui ne peuvent pas l'atteindre par leur teinte.
3. **Aucun texte n'est jamais posé sur une couleur de statut sur la carte.** Les libellés vivent dans la
   légende et dans le panneau, sur `--c-calcaire`. La colonne « encre sur l'aplat » ne sert qu'aux puces de
   légende du portail, où le libellé court peut être incrusté.
4. **Le motif est obligatoire partout où la couleur apparaît** : carte, légende, liste du jour, panneau,
   écran gestionnaire, impression. Une puce sans motif est un défaut bloquant.
5. L'ordre des niveaux est un **ordre de sévérité croissante**, et la densité du motif croît avec lui.
   La carte reste donc lisible en niveaux de gris et en vision dichromatique.

#### Questions exactes à poser au propriétaire du projet (bloquantes)

1. Combien de niveaux compte le dispositif des Bouches-du-Rhône **en vigueur cette saison** — 4 ou 5 ?
   Existe-t-il un niveau intermédiaire « jaune » distinct du vert, ou le dispositif est-il vert / orange /
   rouge / noir ?
2. Quel est le **libellé officiel exact, mot pour mot**, de chaque niveau (celui affiché dans la légende de
   la carte préfectorale, pas une paraphrase) ?
3. Quels sont les **codes couleur exacts** (hex ou RVB) tels que publiés ? Fournir une capture d'écran de la
   légende officielle en pleine résolution, non compressée, permettant un relevé au pipette.
4. Quelle est la **consigne officielle exacte** associée à chaque niveau (horaires d'accès type « 6 h – 11 h »,
   interdiction de travaux, interdiction de circulation/stationnement) ?
5. Le dispositif distingue-t-il **accès piéton**, **circulation/stationnement** et **travaux** par des mentions
   séparées pour un même niveau ? Si oui, comment sont-elles présentées ?
6. Quel est le libellé officiel employé lorsque **le statut du lendemain n'est pas encore publié** ?
7. Quelles sont les **dates exactes** de début et de fin du dispositif, et le libellé employé hors période ?
8. La reproduction de la légende (couleurs + libellés) est-elle autorisée, et sous quelle **mention de source** ?

Tant que ces réponses manquent, la légende affichée porte la mention `Légende en cours de vérification` et
le lien vers la carte officielle est mis en avant. On ne publie pas une légende approximative.

### 4.2 Palette du site

Encres et surfaces — **ratios mesurés (WCAG 2.x, sRGB)**.

| Token | Nom | Valeur | Usage | Contraste |
|---|---|---|---|---|
| `--c-calcaire` | Calcaire | `#EDEEEC` | Surface principale de page | réf. |
| `--c-calcaire-ombre` | Calcaire à l'ombre | `#DEDFD9` | Surfaces secondaires, lignes alternées, terre du fond de carte | 1,16:1 vs calcaire (surfaces uniquement) |
| `--c-poussiere` | Poussière | `#C3C5BC` | Filets 1 px non informatifs, séparateurs | 1,50:1 vs calcaire — **jamais de texte, jamais de bordure porteuse de sens** |
| `--c-trace` | Trace | `#9EA197` | La peinture ancienne : `::before` du repère, décalages, hachure « indisponible » | 2,26:1 vs calcaire — décoratif assumé |
| `--c-garrigue` | Garrigue | `#5F6B5A` | Texte tertiaire, bordures de champs, filets de carte | **4,83:1** vs calcaire conforme · 4,19:1 vs calcaire-ombre ÉCHEC (grand texte ≥ 24 px uniquement) |
| `--c-charbon-doux` | Charbon doux | `#4A4E48` | Texte secondaire, méta, légendes d'image | **7,29:1** vs calcaire conforme · **6,33:1** vs calcaire-ombre conforme |
| `--c-charbon` | Charbon | `#1A1C19` | Texte principal, liserés, hachures | **14,74:1** vs calcaire conforme · **12,79:1** vs calcaire-ombre conforme |
| `--c-mistral-nuit` | Mistral de nuit | `#0B2B3C` | Chrome : ardoise, en-tête, pied, barre d'action, bandeau d'alerte | **12,66:1** vs calcaire conforme · calcaire dessus **12,66:1** conforme · blanc dessus 14,74:1 conforme |
| `--c-mistral` | Mistral | `#17567A` | Liens, boutons primaires, focus | **6,81:1** vs calcaire conforme · 5,91:1 vs calcaire-ombre conforme · calcaire dessus **6,81:1** conforme |
| `--c-mistral-clair` | Mistral clair | `#8FC3DD` | Texte et liens **sur chrome sombre**, halo de focus | **7,73:1** sur mistral-nuit conforme · 1,64:1 sur calcaire ÉCHEC — **interdit sur fond clair** |
| `--c-pin-alep` | Pin d'Alep | `#22392C` | **Usage unique** : teinte de la végétation du fond de carte, appliquée à 10 % sur `--c-calcaire-ombre`. Jamais une couleur d'interface. | n/a (surface de carte) |

Tons de carte dérivés (fond OSM auto-hébergé, restylé monochrome) :

| Token | Valeur | Rôle sur le fond de carte |
|---|---|---|
| `--c-carte-fond` | `#E6E7E1` | Mer, hors-département, fond général |
| `--c-carte-terre` | `#DEDFD9` | Terre |
| `--c-carte-vegetation` | `#D6DBD3` | Bois et végétation (calcaire + 10 % pin d'Alep) |
| `--c-carte-eau` | `#CBD5D8` | Eau — **désaturée**, ne doit jamais lire comme « bleu carte » |
| `--c-carte-trait` | `#B4B7AC` | Routes, limites administratives |
| `--c-carte-encre` | `#4A4E48` | Toponymes |

> Note d'implémentation : si le fond retenu est **raster**, le rendu monochrome est produit **à la génération
> des tuiles côté serveur**, pas par un `filter: grayscale()` navigateur (le filtre casse les ratios mesurés
> et coûte en peinture sur mobile). Si le fond est **vectoriel (PMTiles)**, la feuille de style reprend
> littéralement les six tokens ci-dessus.

---

## 5. Typographie

Deux familles, deux fichiers, **budget §10 tenu exactement** (2 fichiers max).

| Rôle | Famille | Licence | Fichier | Poids | Sous-ensemble |
|---|---|---|---|---|---|
| Titrage — *de caractère* | **Big Shoulders Display** | SIL Open Font License 1.1 | `big-shoulders-display-var.woff2` (variable, axe `wght`) | 500 → 800 | latin + latin-ext (accents FR **capitales comprises**) |
| Texte — *de labeur* | **Atkinson Hyperlegible Next** | SIL Open Font License 1.1 | `atkinson-hyperlegible-next-var.woff2` (variable, axe `wght`) | 400 → 700 | latin + latin-ext |

**Total : 2 fichiers.** conforme Aucun service tiers, aucun CDN, `@font-face` local avec `font-display: swap`
et `size-adjust` calibré pour supprimer le saut de mise en page (§10 : « pas de sauts perceptibles »).

**Pourquoi celles-là :**
- *Big Shoulders Display* est une typographie de **signalétique civique** (dessinée pour un système
  d'orientation urbaine) : ultra-condensée, industrielle, faite pour être lue de loin sur un panneau.
  Elle est le prolongement typographique du panneau DFCI, et elle est nettement moins vue que les
  condensées par défaut (Oswald, Anton, Roboto Condensed).
- *Atkinson Hyperlegible Next* est dessinée par le Braille Institute **pour la basse vision** : formes de
  caractères volontairement différenciées (`I` / `l` / `1`, `O` / `0`, `rn` / `m`). Sur un projet où
  l'accessibilité est bloquante et où l'on répond à un appel d'offres, c'est un argument défendable en
  mémoire technique, pas un choix esthétique. Le contraste de largeur avec la condensée est violent et
  mémorable — c'est exactement l'effet recherché.

**Vérifications à faire au build (`dev-front-cms`) :**
- `À VÉRIFIER` — confirmer que le **fichier variable** d'Atkinson Hyperlegible Next est disponible sous OFL
  et auto-hébergeable. Si seuls des statiques existent, **repli documenté : `Public Sans` variable**
  (OFL 1.1, USWDS) — un seul fichier, registre service public, même rôle. Ne jamais dépasser 2 fichiers.
- Confirmer que le sous-ensemble Big Shoulders contient **É È À Ç Ô Û Î** en capitales (les titres sont
  en capitales) ; sinon, corriger le sous-ensemble avant intégration.
- Piles de repli système : `--police-titre` → `"Big Shoulders Display", "Arial Narrow", sans-serif` ;
  `--police-texte` → `"Atkinson Hyperlegible Next", system-ui, sans-serif`.

### 5.1 Échelle

Base `1rem = 16px`. Corps à 17 px (l'œil d'Atkinson est large, 17 px donne la mesure juste).
Les niveaux 500 à 800 sont **fluides** (`clamp`) : pas de media query typographique.

| Token | Valeur | Rôle | Famille | Interligne | Approche |
|---|---|---|---|---|---|
| `--fs-100` | `0.8125rem` (13 px) | Attributions, mentions de licence | texte | 1,45 | 0 |
| `--fs-200` | `0.9375rem` (15 px) | Méta, fraîcheur, libellés de tableau | texte | 1,5 | 0 |
| `--fs-250` | `0.8125rem` (13 px) | **Étiquette** : capitales, `--ls-etiquette` | titre 700 | 1,2 | 0,08em |
| `--fs-300` | `1.0625rem` (17 px) | **Corps** | texte | 1,6 | 0 |
| `--fs-400` | `1.1875rem` (19 px) | Chapô, consigne du massif | texte | 1,55 | 0 |
| `--fs-500` | `clamp(1.375rem, 1.2rem + 0.9vw, 1.75rem)` | `h3` | titre 600 | 1,15 | 0,01em |
| `--fs-600` | `clamp(1.75rem, 1.4rem + 1.8vw, 2.5rem)` | `h2` | titre 700, capitales | 1,08 | 0,01em |
| `--fs-700` | `clamp(2.25rem, 1.6rem + 3.2vw, 3.75rem)` | `h1` | titre 700, capitales | 1,05 | 0,005em |
| `--fs-800` | `clamp(3.5rem, 2rem + 7.5vw, 8rem)` | **Le chiffre du jour** | titre 800, chiffres tabulaires | 0,92 | −0,01em |

**Règle de hiérarchie** : la famille de titrage n'a **que deux poids en service (700 et 800)** et s'emploie
**toujours en capitales** au-dessus de `--fs-500`. La hiérarchie vient de la taille, pas du poids ni de la
couleur. Interdit : titre en `--c-mistral`, titre en italique, titre souligné.

**Mesure de ligne** : `--mesure: 68ch` sur le corps éditorial, `--mesure-etroite: 46ch` dans le panneau massif.

**Comportement à 360 px** : `--fs-800` vaut 56 px ; le chiffre du jour n'affiche que **le nombre** (« 12 »),
la phrase complète passe en `--fs-400` en dessous. `--fs-700` vaut 36 px, `--fs-600` 28 px. Aucun titre ne
descend jamais sous 28 px : la condensée devient illisible avant de devenir petite. À 200 % de zoom, tous
les `clamp` restent bornés par leur minimum en `rem`, donc le texte grossit bien (pas de piège `vw` pur).

---

## 6. Espacement, rythme, rayons, bordures, élévation

### 6.1 Espacement — échelle base 4

| Token | Valeur | Emploi |
|---|---|---|
| `--esp-3xs` | `2px` | Décalage d'état actif, micro-ajustements |
| `--esp-2xs` | `4px` | Écart puce ↔ libellé |
| `--esp-xs` | `8px` | Padding interne des puces, gouttière de tableau serré |
| `--esp-s` | `12px` | Écart entre éléments d'un même groupe |
| `--esp-m` | `16px` | **Gouttière de page à 360 px**, padding des cellules |
| `--esp-l` | `24px` | Retrait du repère, padding du panneau, gouttière ≥ 600 px |
| `--esp-xl` | `32px` | Écart entre blocs d'une même section |
| `--esp-2xl` | `48px` | Padding vertical d'une section (mobile) |
| `--esp-3xl` | `64px` | Padding vertical d'une section (desktop) |
| `--esp-4xl` | `96px` | Respiration de l'ardoise, écart avant le pied de page |

**Règle de rythme** : le rythme vertical entre sections est `clamp(48px, 6vw, 96px)`, exposé en
`--esp-section`. Aucune valeur d'espacement hors échelle. Aucune marge négative sauf pour le
plein-cadre (`--sortie-cadre`).

### 6.2 Rayons — la contrainte la plus visible

| Token | Valeur | Emploi |
|---|---|---|
| `--r-0` | `0` | **Par défaut, partout** : sections, carte, panneaux, tableaux, boutons |
| `--r-1` | `2px` | Puces de statut, champs de formulaire, boutons — *uniquement pour éviter l'aliasing des coins* |

> **Aucun rayon supérieur à 2 px n'existe dans ce système.** Pas de carte arrondie, pas de pilule, pas
> d'avatar rond. C'est la peinture sur pierre, pas le composant d'un kit UI. Un `border-radius: 8px`
> repéré en revue est un défaut.

### 6.3 Bordures

| Token | Valeur | Emploi |
|---|---|---|
| `--bord-fin` | `1px solid var(--c-poussiere)` | Séparateurs de lignes de tableau, filets non informatifs |
| `--bord-champ` | `2px solid var(--c-garrigue)` | Champs de formulaire au repos (4,83:1 → limite ≥ 3:1 conforme) |
| `--bord-moyen` | `2px solid var(--c-charbon)` | Puces de statut, boutons secondaires, liseré des polygones |
| `--bord-fort` | `4px solid var(--c-charbon)` | Haut et bas de la carte, panneau massif, bandeau de non-officialité |

### 6.4 Élévation — aucune ombre floue

| Token | Valeur | Emploi |
|---|---|---|
| `--ombre-0` | `none` | **Défaut de tous les éléments** |
| `--ombre-decalee` | `3px 4px 0 var(--c-trace)` | Panneau massif, bloc de légende |
| `--ombre-decalee-sombre` | `3px 4px 0 var(--c-mistral)` | Les mêmes, posés sur chrome sombre |

**`blur-radius` est toujours `0`.** Le décalage `(3px, 4px)` est exactement celui du repère : l'élévation
n'est pas une seconde idée, c'est la signature appliquée à une surface au lieu d'une barre.
**Deux types de composants au maximum** peuvent porter une ombre : le panneau massif et le bloc de légende.
Les boutons n'en portent pas.

---

## 7. Mise en page

### 7.1 Accueil — la carte est le héros

Composition en **bandes horizontales pleine largeur** (strates calcaires), de haut en bas :

```
┌────────────────────────────────────────────────────────────┐
│ BARRE  mistral-nuit · 48px · nom du site · 4 liens · évitement│
├────────────────────────────────────────────────────────────┤
│ ▌                                                          │
│ ▌ L'ARDOISE   mistral-nuit, pleine largeur, ~30vh          │
│ ▌  ┌────────┐  AUJOURD'HUI, 12 MASSIFS SUR 27              │
│ ▌  │   12   │  SONT D'ACCÈS AUTORISÉ.        (fs-700, caps)│
│ ▌  │ /27    │  Statuts du mardi 11 août 2026, publiés la   │
│ ▌  └────────┘  veille par la préfecture — relevés à 19 h 04│
│ ▲ le repère, version bloc, pleine hauteur                  │
├────────────────────────────────────────────────────────────┤
│ NON-OFFICIALITÉ  calcaire-ombre · bord-fort en haut · fs-200│
├════════════════════════════════════════════════════════════┤
│                                                            │
│           LA CARTE — plein cadre, bord à bord              │
│           min(72vh, 640px) · fond calcaire monochrome      │
│           massifs = seules taches de couleur de l'écran    │
│                                                            │
├════════════════════════════════════════════════════════════┤
│ LÉGENDE  bande horizontale · puces couleur+motif+libellé   │
│          + bascule « Afficher les zones parcourues par le feu »│
├────────────────────────────────────────────────────────────┤
│ ▌ LA LISTE DU JOUR   (h2 + repère) — ancre #liste          │
│   tableau pleine largeur, lignes alternées                 │
├────────────────────────────────────────────────────────────┤
│ ▌ DANGER MÉTÉO DU JOUR  module distinct, sans couleur      │
├────────────────────────────────────────────────────────────┤
│ ▌ ZONES PARCOURUES PAR LE FEU  (texte + limites EFFIS)     │
├────────────────────────────────────────────────────────────┤
│ PIED  mistral-nuit · attributions · licences · zéro cookie │
└────────────────────────────────────────────────────────────┘
```

**Ce qui fait la capture d'écran** : l'empilement ardoise sombre → carte monochrome piquée de couleur →
bande de légende. Trois bandes, aucun bruit, un chiffre énorme. Rien d'autre n'a le droit d'attirer l'œil.

**Points non négociables de cette composition :**
- La carte **touche les deux bords** de la fenêtre à toutes les tailles. Elle n'est jamais dans un conteneur
  centré à coins arrondis. C'est la différence physique entre « une carte sur un site » et « un site qui est
  une carte ».
- Le bandeau de non-officialité (§5.6 du brief) est **entre l'ardoise et la carte**, pas en pied de page :
  il est dans le chemin du regard, mais dans une bande neutre — obligatoire sans être criard.
- **La liste du jour n'est pas un repli.** Elle a son `h2`, son repère, la pleine largeur, la même typographie
  de titrage que la carte, et elle est annoncée par le lien d'évitement « Aller à la liste des statuts ».
  Visuellement, c'est *le second héros*. On doit pouvoir lire le site en ne regardant qu'elle.
- Le module météo est **visuellement étranger au reste** : bordure fine, aucune couleur, échelle de points.
  L'écart de traitement est la traduction de « deux notions jamais fusionnées » (§4.3 du brief).

**Panneau massif** : à partir de 900 px, colonne de droite `380px` collée (`position: sticky`) à côté de la
carte, avec le repère sur son bord gauche. En dessous de 900 px, feuille du bas (`bottom sheet`) occupant
au maximum 66 % de la hauteur, avec poignée de fermeture 44 px et fermeture par Échap. Jamais une popup
Leaflet par défaut, jamais une infobulle au survol.

**Points de rupture** (mobile-first, en `rem`) :

| Token | Valeur | Ce qui change |
|---|---|---|
| base | 360 px | Une colonne, gouttière `--esp-m`, légende en 2 colonnes, tableau en cartes empilées |
| `--bp-s` | `37.5rem` (600 px) | Légende en ligne, tableau en vraies colonnes, gouttière `--esp-l` |
| `--bp-m` | `56.25rem` (900 px) | Panneau massif à droite de la carte, ardoise en deux colonnes |
| `--bp-l` | `80rem` (1280 px) | Contenu bridé à `--largeur-max: 1200px` ; **la carte reste plein cadre** |

À 360 px : aucun défilement horizontal, cibles ≥ 44 px, aucun élément en `position: fixed` autre que la
feuille du bas et la barre d'action du portail.

### 7.2 Portail gestionnaire

Même système, chrome plus dense — c'est un outil, pas une vitrine, mais il doit être aussi soigné (§6 du brief).

- **En-tête** `--c-mistral-nuit`, 56 px : « MASSIFS · Mise à jour des statuts », date de la session, déconnexion.
- **Écran unique** : un tableau, une ligne par massif. Colonnes : massif · statut d'aujourd'hui (lecture seule,
  puce couleur+motif) · **niveau pour demain** (groupe de boutons radio) · dernière modification (auteur + heure).
- Le groupe radio est rendu en **puces segmentées** : chacune ≥ 44 px de haut, couleur + motif + libellé abrégé,
  liseré `--c-charbon` 2 px, état sélectionné = liseré 4 px `--c-mistral-nuit` + repère à gauche.
  Navigation clavier par flèches dans le groupe (rôle `radiogroup`), `Tab` passe à la ligne suivante.
- **Barre d'action collée en bas**, `--c-mistral-nuit`, repère sur son bord gauche : compteur « 7 statuts
  modifiés » + bouton unique **« Publier les statuts »**. Objectif < 1 min : aucune étape intermédiaire,
  aucune modale de confirmation *avant*, une confirmation *après* (annoncée en `aria-live="polite"`).
- **Historique** : même tableau, filtres en ligne, export CSV. Les valeurs ancienne/nouvelle sont montrées
  par deux puces séparées par une flèche typographique `→`, jamais par une couleur de diff.
- Aucun bouton désactivé : si une action est impossible, elle reste focusable et explique pourquoi (§9.3).

### 7.3 Pages éditoriales (La démarche, Accessibilité, Mentions légales)

- Une seule colonne, `--mesure` 68ch, alignée à gauche de la grille (pas centrée : la page garde son bord
  gauche commun avec l'ardoise et les titres).
- `h2` en capitales condensées + repère, `--esp-section` avant chacun.
- Les citations et encarts sont des **slabs `--c-calcaire-ombre` avec `--bord-fort` en haut**, jamais des
  cartes ombrées ni des filets fins verticaux.
- Les tableaux de sources/licences reprennent exactement le tableau de la liste du jour.
- Aucun visuel décoratif. Les seules images du site sont : l'image statique du département (repli sans JS)
  et, éventuellement, des photographies personnelles créditées sur « La démarche » — jamais en fond, jamais
  en bandeau héroïque, jamais derrière du texte.

---

## 8. Composants clés — spécification visuelle

### 8.1 Puce de statut (l'objet le plus répété du site)

```
┌─────────────────────────────────┐
│ ▌▨▨▨  ACCÈS RÉGLEMENTÉ          │   ▌ = repère (couleur du niveau)
└─────────────────────────────────┘   ▨ = pastille 20×20, couleur + motif + liseré charbon 2px
```
- Hauteur minimale `44px` quand elle est cliquable, `28px` quand elle est purement informative.
- Libellé en `--fs-250` (capitales, `--ls-etiquette`), couleur `--c-charbon` **sur fond de page**, jamais
  sur l'aplat de statut.
- La pastille porte **toujours** le motif du niveau. Le motif est en `--c-charbon` (niveaux 2, 3, 4) ou
  `--c-calcaire` (niveau 5).

Motifs — CSS de référence (aucune image, budget §10) :

```css
.pastille--n2 { background-color: var(--statut-2);
  background-image: radial-gradient(var(--c-charbon) 1px, transparent 1.2px);
  background-size: 6px 6px; }
.pastille--n3 { background-color: var(--statut-3);
  background-image: repeating-linear-gradient(45deg,
    var(--c-charbon) 0 1.5px, transparent 1.5px 8px); }
.pastille--n4 { background-color: var(--statut-4);
  background-image:
    repeating-linear-gradient(45deg,  var(--c-charbon) 0 2px, transparent 2px 6px),
    repeating-linear-gradient(-45deg, var(--c-charbon) 0 2px, transparent 2px 6px); }
.pastille--n5 { background-color: var(--statut-5);
  background-image:
    repeating-linear-gradient(45deg,  var(--c-calcaire) 0 1.5px, transparent 1.5px 7px),
    repeating-linear-gradient(-45deg, var(--c-calcaire) 0 1.5px, transparent 1.5px 7px); }
.pastille--indisponible { background-color: var(--c-calcaire-ombre);
  background-image: repeating-linear-gradient(-45deg,
    var(--c-trace) 0 2px, transparent 2px 10px); }
```

Sur la carte, les mêmes motifs sont déclarés en `<pattern patternUnits="userSpaceOnUse">` dans le `defs`
du calque SVG de Leaflet. **La densité du motif doit rester constante à l'écran quel que soit le zoom** :
recalculer la taille du pattern sur `zoomend` (ou utiliser un pane non transformé). Un motif qui s'étire au
zoom cesse d'être un encodage fiable.

### 8.2 L'ardoise (le chiffre du jour)

- Fond `--c-mistral-nuit`, texte `--c-calcaire` (12,66:1 conforme), méta en `--c-mistral-clair` (7,73:1 conforme).
- Chiffre en `--fs-800`, chiffres **tabulaires** (`font-variant-numeric: tabular-nums`) pour qu'il ne
  saute pas quand il passe de 9 à 12.
- Le dénominateur (« /27 ») est en `--fs-500`, aligné sur la ligne de base basse du chiffre.
- Repère version `--bloc` sur toute la hauteur du slab, à gauche : `::after` `--c-calcaire`, `::before`
  `--c-mistral`.
- **Si l'information du jour est indisponible** : le chiffre disparaît, remplacé par le mot
  « INDISPONIBLE » en `--fs-700`, l'ardoise prend la hachure `\` `--c-mistral` en surimpression à 12 %,
  et le lien « Ouvrir la carte officielle de la préfecture » passe en bouton primaire. On ne montre
  **jamais** un chiffre de la veille.

### 8.3 Bandeau d'alerte (péremption, source indisponible, hors-saison)

Fond `--c-mistral-nuit`, texte `--c-calcaire`, repère `--bloc` à gauche, `--bord-fort` en bas, hachure
`--c-mistral` à 45° en fond à faible opacité. Le premier mot du texte porte l'information
(« Donnée périmée. », « Source indisponible. », « Dispositif estival inactif. ») : le sens ne repose ni
sur la couleur ni sur une icône.

### 8.4 Module « Danger météo du jour » — sans aucune couleur

Échelle à cinq crans rendue par des carrés de 12 px en `--c-charbon` (pleins = niveau atteint, vides =
liseré 1,5 px `--c-garrigue`), suivie du libellé officiel Météo-France en toutes lettres et de la phrase
d'explication : « Le danger météo décrit les conditions du jour ; il ne détermine pas l'accès au massif,
qui relève de l'arrêté préfectoral. » Aucune puce colorée, aucune icône de flamme, aucune proximité
visuelle avec les statuts.

---

## 9. États d'interaction

### 9.1 Anneau de focus — spécification unique

```css
:root {
  --focus-trait:  var(--c-mistral-nuit);   /* sur surfaces claires */
  --focus-trait-inverse: var(--c-calcaire);/* sur chrome sombre et aplats de statut */
  --focus-halo:   var(--c-mistral-clair);
}
:where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
  outline: 3px solid var(--focus-trait);
  outline-offset: 2px;
  box-shadow: 0 0 0 6px var(--focus-halo);
  border-radius: var(--r-1);
}
.sur-sombre :focus-visible { outline-color: var(--focus-trait-inverse);
                             box-shadow: 0 0 0 6px var(--c-mistral); }
```

- Jamais `outline: none` sans remplacement. `:focus-visible` uniquement (pas de halo à la souris),
  **sauf** sur la feuille du bas et le panneau massif, où le focus programmatique doit rester visible.
- **Sur la carte**, un massif focusé reçoit un **double contour** : `--c-calcaire` 3 px **et** `--c-charbon`
  3 px décalés. C'est indispensable : sur le niveau 2 (jaune), un anneau calcaire seul ne fait que 1,67:1 ;
  la moitié charbon monte à 8,84:1. Le double contour garantit ≥ 3:1 sur **les cinq** niveaux plus le fond
  de carte.
- Contrastes de l'anneau : `--c-mistral-nuit` vs `--c-calcaire` **12,66:1** conforme · vs `--c-calcaire-ombre`
  **10,93:1** conforme · `--c-calcaire` vs `--c-mistral-nuit` **12,66:1** conforme. Toutes les surfaces de la palette
  sont couvertes.

### 9.2 Survol, actif

| État | Traitement | Règle |
|---|---|---|
| Repos | — | Les liens de contenu sont **soulignés en permanence** (`text-underline-offset: 0.18em`, épaisseur 1,5 px) |
| Survol | Fond `--c-calcaire-ombre` (boutons/lignes) ; soulignement porté à 3 px (liens) | **Aucune information n'apparaît au survol.** Un contenu qui n'existe qu'au survol est un défaut bloquant (§5.2 du brief : « aucun survol requis ») |
| Actif | `transform: translate(1px, 1px)` ; `--ombre-decalee` réduite à `2px 3px 0` | Le geste « la peinture s'enfonce » |
| Sélectionné | Liseré porté à 4 px + repère à gauche | Jamais un simple changement de couleur de fond |
| Désactivé | **N'existe pas.** L'action reste focusable et explique la raison (« Publication impossible : aucun statut modifié. ») | Évite l'exception de contraste et le cul-de-sac clavier |

### 9.3 Clavier et pointeur

- **Cibles ≥ 44 × 44 px** partout (`--cible-min: 2.75rem`), y compris les puces radio du portail, la
  bascule de couche EFFIS, la poignée de la feuille du bas et les contrôles de zoom de Leaflet
  (les contrôles par défaut font 30 px : ils sont **redimensionnés**, pas laissés tels quels).
- **Échap ferme** le panneau massif, la feuille du bas, le sélecteur de date, et rend le focus à l'élément
  déclencheur. Aucun piège clavier.
- Ordre de tabulation : évitement → en-tête → ardoise → **carte (un seul arrêt, puis flèches pour parcourir
  les massifs)** → légende → liste → sections → pied.
- Liens d'évitement « Aller au contenu » et « Aller à la liste des statuts » : cachés hors focus, visibles
  au focus en haut à gauche, fond `--c-mistral-nuit`, texte `--c-calcaire`.

---

## 10. Mouvement

| Token | Valeur | Emploi |
|---|---|---|
| `--duree-court` | `120ms` | Changement de fond (survol, sélection de puce) |
| `--duree-moyen` | `200ms` | Ouverture/fermeture du panneau massif, zoom Leaflet |
| `--duree-long` | `320ms` | Feuille du bas mobile (translation verticale) |
| `--ease-net` | `cubic-bezier(0.2, 0, 0, 1)` | Entrées : démarrage franc, arrêt net |
| `--ease-retrait` | `cubic-bezier(0.4, 0, 1, 1)` | Sorties |

**Il n'existe que trois animations sur ce site** : le panneau (translation 12 px + opacité), les changements
d'état des puces (fond), le zoom de la carte. Rien d'autre ne bouge.

Interdits explicites : parallaxe, apparition au défilement, compteur qui s'incrémente, souffle de vent
animé (la tentation « mistral » est refusée : la métaphore vaut pour la palette, pas pour le mouvement),
squelettes pulsants, spinners. Un chargement se signale par une barre de progression de 2 px en
`--c-mistral` en haut de la zone concernée, et par un texte `aria-live`.

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```
Côté carte : quand `prefers-reduced-motion` vaut `reduce`, Leaflet est initialisé avec
`zoomAnimation: false`, `fadeAnimation: false`, `markerZoomAnimation: false` — la préférence doit
traverser la frontière CSS/JS, sinon elle n'est respectée qu'à moitié.

---

## 11. Micro-rédaction

### 11.1 Règles de voix

1. **Voix active, sujet explicite.** « La préfecture publie les statuts vers 19 h », pas « les statuts sont publiés ».
2. **Le libellé nomme l'action**, jamais son mécanisme : « Publier les statuts », « Afficher les zones
   parcourues par le feu », « Fermer le panneau ». Interdits : « Valider », « OK », « Soumettre », « En savoir plus ».
3. **Les erreurs disent quoi faire, sans s'excuser.** « Choisissez un niveau pour chaque massif modifié. »
   Interdits : « Oups », « Désolé », « Une erreur est survenue ».
4. **Aucune promesse d'officialité.** Le site « relaie », « reprend », « d'après » — il ne « garantit » jamais.
5. **Aucun superlatif, aucune exclamation, aucun emoji, aucune icône seule** porteuse de sens.
6. **Dates et heures en français long** : « mardi 11 août 2026 », « 19 h 04 » (espace insécable, pas de `:`).
7. **Chiffres écrits en chiffres** dès qu'ils sont des données (« 12 massifs sur 27 »).

### 11.2 Vocabulaire fixe — un terme, un sens, partout

| Terme retenu | Sens | Ne jamais dire |
|---|---|---|
| **massif** | Le périmètre forestier du référentiel DDTM | zone, secteur, espace, forêt |
| **niveau** | Le cran de la légende officielle (1 à 5) | couleur, code, alerte |
| **statut** | L'enregistrement « ce massif, ce jour, ce niveau » | état, situation |
| **consigne** | Ce que le niveau impose au promeneur | recommandation, conseil |
| **fraîcheur** | L'âge de la donnée affichée | actualité, mise à jour (en tant que nom) |
| **dispositif** | Le régime préfectoral estival | système, plan, saison |
| **jour de validité** | Le jour auquel le statut s'applique | date, jour J |
| **carte officielle** | La carte de la préfecture | site officiel, source officielle |
| **zone parcourue par le feu** | Le polygone EFFIS | incendie, feu actif, zone brûlée |
| **danger météo** | L'indicateur Météo-France | risque, alerte météo |
| **gestionnaire** | Le rôle qui met à jour les statuts | éditeur, modérateur, admin |
| **publier** | L'action d'enregistrer et de diffuser les statuts | valider, envoyer, sauvegarder |

### 11.3 Chaînes fixes (à reprendre mot pour mot)

- Non-officialité (§5.6, obligatoire) : « Site d'information indépendant. Seules les publications de la
  préfecture des Bouches-du-Rhône font foi : [lien carte officielle]. »
- Fraîcheur : « Statuts du {jour de validité}, publiés la veille par la préfecture — relevés sur ce site
  le {date} à {heure}. »
- Indisponible : « Information du jour non disponible. Consultez la carte officielle de la préfecture. »
- Hors saison : « Dispositif estival inactif. Reprise le {date}. »
- EFFIS : « Périmètres estimés par satellite (feux d'environ 30 ha et plus). Zone déjà parcourue par le
  feu, ce n'est pas un périmètre officiel d'interdiction. »

---

## 12. Jetons CSS — contenu exact de `assets/css/tokens.css`

À recopier **tel quel**. Aucun autre fichier ne définit de custom property ; aucune valeur littérale de
couleur, d'espacement ou de durée n'apparaît ailleurs dans le CSS.

```css
/* MASSIFS — jetons du design system. Voir design-system/MASTER.md.
   Ne pas ajouter de valeur hors échelle. Ne pas redéfinir hors :root,
   sauf --repere-couleur (documenté §3.1). */

:root {
  /* ── Surfaces et encres ─────────────────────────────────── */
  --c-calcaire:        #EDEEEC;
  --c-calcaire-ombre:  #DEDFD9;
  --c-poussiere:       #C3C5BC;
  --c-trace:           #9EA197;
  --c-garrigue:        #5F6B5A;
  --c-charbon-doux:    #4A4E48;
  --c-charbon:         #1A1C19;
  --c-mistral-nuit:    #0B2B3C;
  --c-mistral:         #17567A;
  --c-mistral-clair:   #8FC3DD;
  --c-pin-alep:        #22392C;   /* usage unique : teinte du fond de carte */

  /* ── Fond de carte monochrome ───────────────────────────── */
  --c-carte-fond:       #E6E7E1;
  --c-carte-terre:      #DEDFD9;
  --c-carte-vegetation: #D6DBD3;
  --c-carte-eau:        #CBD5D8;
  --c-carte-trait:      #B4B7AC;
  --c-carte-encre:      #4A4E48;

  /* ── Statuts officiels — À CONFIRMER (§4.1) ─────────────── */
  --statut-1: #1E7A3C;  --statut-1-encre: var(--c-calcaire);
  --statut-2: #E8B21C;  --statut-2-encre: var(--c-charbon);
  --statut-3: #D2621B;  --statut-3-encre: var(--c-charbon);
  --statut-4: #B4231E;  --statut-4-encre: var(--c-calcaire);
  --statut-5: #171717;  --statut-5-encre: var(--c-calcaire);
  --statut-lisere:       var(--c-charbon);
  --statut-lisere-n5:    var(--c-calcaire);
  --statut-indisponible: var(--c-calcaire-ombre);

  /* ── Typographie ────────────────────────────────────────── */
  --police-titre: "Big Shoulders Display", "Arial Narrow", sans-serif;
  --police-texte: "Atkinson Hyperlegible Next", system-ui, sans-serif;

  --fs-100: 0.8125rem;
  --fs-200: 0.9375rem;
  --fs-250: 0.8125rem;
  --fs-300: 1.0625rem;
  --fs-400: 1.1875rem;
  --fs-500: clamp(1.375rem, 1.2rem + 0.9vw, 1.75rem);
  --fs-600: clamp(1.75rem,  1.4rem + 1.8vw, 2.5rem);
  --fs-700: clamp(2.25rem,  1.6rem + 3.2vw, 3.75rem);
  --fs-800: clamp(3.5rem,   2rem   + 7.5vw, 8rem);

  --lh-affiche: 0.92;
  --lh-titre:   1.08;
  --lh-sous:    1.15;
  --lh-dense:   1.35;
  --lh-corps:   1.6;

  --ls-affiche:   -0.01em;
  --ls-titre:      0.01em;
  --ls-etiquette:  0.08em;

  --poids-titre: 700;
  --poids-affiche: 800;
  --poids-texte: 400;
  --poids-texte-fort: 700;

  --mesure: 68ch;
  --mesure-etroite: 46ch;

  /* ── Espacement ─────────────────────────────────────────── */
  --esp-3xs: 2px;  --esp-2xs: 4px;  --esp-xs: 8px;  --esp-s: 12px;
  --esp-m: 16px;   --esp-l: 24px;   --esp-xl: 32px; --esp-2xl: 48px;
  --esp-3xl: 64px; --esp-4xl: 96px;
  --esp-section: clamp(48px, 6vw, 96px);
  --gouttiere: var(--esp-m);
  --largeur-max: 1200px;

  /* ── Rayons, bordures, élévation ────────────────────────── */
  --r-0: 0;
  --r-1: 2px;
  --bord-fin:   1px solid var(--c-poussiere);
  --bord-champ: 2px solid var(--c-garrigue);
  --bord-moyen: 2px solid var(--c-charbon);
  --bord-fort:  4px solid var(--c-charbon);
  --ombre-0: none;
  --ombre-decalee: 3px 4px 0 var(--c-trace);
  --ombre-decalee-sombre: 3px 4px 0 var(--c-mistral);

  /* ── Signature ──────────────────────────────────────────── */
  --repere-largeur: 8px;
  --repere-decalage-x: 3px;
  --repere-decalage-y: 4px;
  --repere-couleur: var(--c-mistral-nuit);

  /* ── Focus ──────────────────────────────────────────────── */
  --focus-trait: var(--c-mistral-nuit);
  --focus-trait-inverse: var(--c-calcaire);
  --focus-halo: var(--c-mistral-clair);
  --focus-epaisseur: 3px;
  --focus-ecart: 2px;
  --focus-halo-epaisseur: 6px;

  /* ── Cibles ─────────────────────────────────────────────── */
  --cible-min: 2.75rem;   /* 44px */

  /* ── Mouvement ──────────────────────────────────────────── */
  --duree-court: 120ms;
  --duree-moyen: 200ms;
  --duree-long:  320ms;
  --ease-net:     cubic-bezier(0.2, 0, 0, 1);
  --ease-retrait: cubic-bezier(0.4, 0, 1, 1);

  /* ── Points de rupture (documentaires : @media n'accepte pas les vars) ── */
  --bp-s: 37.5rem;   /* 600px  */
  --bp-m: 56.25rem;  /* 900px  */
  --bp-l: 80rem;     /* 1280px */

  /* ── Plans (Leaflet occupe 200–1000) ────────────────────── */
  --z-carte: 0;
  --z-panneau: 1100;
  --z-barre-action: 1200;
  --z-bandeau: 1300;
  --z-evitement: 1400;
}

@media (min-width: 37.5rem) { :root { --gouttiere: var(--esp-l); } }

@media (prefers-reduced-motion: reduce) {
  :root { --duree-court: 0.01ms; --duree-moyen: 0.01ms; --duree-long: 0.01ms; }
}
```

> Convention de nommage : **les noms de jetons sont en ASCII pur**, sans accent ni caractère spécial
> (`--statut-lisere`, `--esp-...`, `--duree-...`). Les accents ne vivent que dans la documentation.

---

## 13. Impression

La page imprimée est un **livrable en soi** (§5.3 du brief : « imprimable proprement ») : c'est la feuille
qu'on affiche au gîte ou à la mairie.

```
@page { margin: 12mm; }
```

- Fonds convertis : `--c-calcaire` → blanc, `--c-mistral-nuit` → blanc avec `--bord-fort` en haut et texte noir.
- **La carte interactive n'est pas imprimée** (`display: none`) : elle est remplacée par l'image statique du
  département si elle est disponible, sinon par rien. **La liste du jour est imprimée intégralement**, en
  tableau à filets 0,5 pt, `page-break-inside: avoid` sur chaque ligne, en-tête de tableau répété
  (`thead { display: table-header-group; }`).
- Les puces de statut s'impriment en **noir et blanc** : liseré 1,5 pt noir + le motif du niveau en noir.
  La couleur n'est donc jamais nécessaire à la compréhension d'une page imprimée en niveaux de gris.
  `print-color-adjust: exact` uniquement sur les pastilles, pour préserver les motifs.
- **Toujours imprimés** : le titre, le jour de validité, la ligne de fraîcheur, le bandeau de non-officialité,
  les attributions (§9 du brief).
- Les liens de contenu voient leur URL dépliée (`a[href^="http"]::after { content: " (" attr(href) ")"; }`),
  sauf dans les menus et le pied, masqués à l'impression.
- Le repère s'imprime : `::after` noir, `::before` gris 45 %. C'est la signature de la feuille papier.
- Corps à 10,5 pt / 1,45 ; `h1` 20 pt ; `h2` 14 pt ; le chiffre du jour à 34 pt.

---

## 14. Autocritique (passe 2 — obligatoire)

Méthode : chaque décision de la passe 1 a été soumise à quatre questions — *l'aurais-je produite pour
n'importe quel site carto ? tombe-t-elle dans un tell « design IA » ? l'audace est-elle unique et tenue ?
la palette vient-elle du sujet ou d'un nuancier ?* Verdict et corrections ci-dessous.

| Décision | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Fond de carte monochrome calcaire** | Générique ? | **Non.** C'est l'inverse du réflexe carto (fond OSM standard coloré, polygones translucides par-dessus). Ici le fond est neutralisé pour que les statuts soient la seule couleur. | Conservé, et **promu au rang de pari central** : tout le reste en découle (§1). |
| **Règle de non-collision chromatique** | Générique ? | Non — aucun design system générique ne s'interdit le vert « succès » et le rouge « erreur ». | Conservée. Conséquence assumée : ni bouton vert ni erreur rouge (§4.2, §9.2). |
| **Palette crème + serif + terracotta** | Tell IA §7 ? | **Oui, refusé.** C'était le réflexe « Provence ». | **Refait** : le calcaire est tiré vers le froid/vert (`#EDEEEC`, pas `#F5F0E6`), la terracotta est bannie, aucun serif dans le système. |
| **Noir + accent acide** | Tell IA §7 ? | **Oui, évité.** | L'encre est `#1A1C19` (charbon, jamais `#000`) et l'accent est un bleu froid rabattu, jamais saturé. |
| **Look journal à filets fins** | Tell IA §7 ? | **Oui, évité.** | Les filets porteurs de sens font 2 px et 4 px ; le 1 px est cantonné aux séparateurs de tableau. Les encarts sont des slabs, pas des filets. |
| **Cartes arrondies sur fond gris** | Kit UI générique ? | **Oui, refusé.** | `border-radius` plafonné à **2 px** dans tout le système (§6.2). Aucun composant « card ». |
| **Le repère (signature)** | Une seule audace ? | Oui — et c'est la métaphore du produit (une marque repeinte chaque soir), pas un ornement. | Conservé, avec une **liste fermée de 7 emplacements** et une liste d'interdits (§3.2, §3.3), pour qu'il ne se dilue pas. |
| **Ombres décalées non floues** | Deuxième idée non reliée ? | **Risque réel** — repéré et corrigé. | Le décalage de l'ombre est **exactement** celui du repère `(3px, 4px)` et sa couleur est `--c-trace` : ce n'est pas un second geste, c'est le même appliqué à une surface. Limité à **2 types de composants**. |
| **Ombres décalées** | Tell « néo-brutalisme » ? | **Risque réel.** Le néo-brutalisme, c'est du noir pur, des aplats candy, des décalages de 6–8 px partout et une grotesque joviale. | **Différencié volontairement** : décalage de 3–4 px seulement, couleur `--c-trace` (gris-vert éteint) jamais noire, palette minérale désaturée, typographie civique. Choix délibéré, justifié ici. |
| **Big Shoulders Display** | Typo « par défaut » ? | Non : les condensées réflexes sont Oswald, Anton, Roboto Condensed. Celle-ci est une typo de signalétique civique, cohérente avec les panneaux DFCI. | Conservée. Vérification ajoutée sur les **capitales accentuées** (les titres sont en capitales). |
| **Atkinson Hyperlegible Next** | Choix esthétique gratuit ? | Non : accessibilité bloquante (§8), typo dessinée pour la basse vision. Argument défendable en mémoire technique. | Conservée + **repli documenté** (Public Sans variable) si le fichier variable n'est pas auto-hébergeable, pour ne jamais dépasser 2 fichiers. |
| **Bleu mistral en chrome** | Bleu « corporate » ? | **Risque.** Un bleu de site institutionnel est le degré zéro. | **Rabattu et refroidi** (`#0B2B3C` / `#17567A`, faible chroma) et **employé en aplats pleine largeur**, jamais en petites touches d'accent. C'est aussi une nécessité : c'est la seule famille de teintes qui ne peut pas être confondue avec un niveau. |
| **Équivalent textuel** | Traité en repli ? | **Oui au premier jet — corrigé.** Il était sous la carte, en petit, sans titre fort. | **Refait** : la liste du jour a son `h2`, son repère, la pleine largeur et la même typographie de titrage que le reste. C'est le second héros, pas une note de bas de page (§7.1). |
| **Animation « mistral »** | Idée dispersée ? | **Oui, supprimée.** Un souffle animé aurait été une deuxième audace, coûteuse et hostile au `prefers-reduced-motion`. | **Supprimée.** Le mistral vit dans la palette, pas dans le mouvement (§10). |
| **Module météo** | Fusionné avec le statut ? | Risque de confusion identifié. | **Traité sans aucune couleur** (échelle de points en charbon) et visuellement étranger au reste (§8.4). |
| **Couleurs de statut** | Inventées ? | **Non — et c'est bloquant.** | Substituts explicitement marqués `À CONFIRMER`, avec **8 questions précises** au propriétaire (§4.1). Le système est paramétré pour que seuls les hex changent. |
| **Contrastes des statuts** | Vérifiés ou supposés ? | Deux niveaux (jaune, orange) échouent au 3:1 sur fond clair — constaté, pas contourné. | **Liseré charbon 2 px rendu obligatoire** sur tous les polygones, et `fill-opacity: 1` imposé pour que les ratios mesurés restent vrais (§4.1). |

**Ce que je referais si le temps le permettait** : dessiner un jeu de pictogrammes de massif (crête, calanque,
plateau) pour la liste du jour. Écarté volontairement — ce serait une seconde audace, un coût de fichiers, et
ça affaiblirait le repère. La discipline vaut mieux que l'accumulation.

---

## 15. Journal des décisions (extrait pour le §11 du brief)

| # | Décision | Raison retenue | Alternative écartée |
|---|---|---|---|
| D-01 | Fond de carte monochrome calcaire, restylé côté serveur | Les statuts deviennent la seule couleur de l'écran : lisibilité + impact visuel | Fond OSM standard + polygones translucides (illisible, contrastes non maîtrisés) |
| D-02 | `fill-opacity: 1` sur les massifs | Les ratios de contraste mesurés ne tiennent pas sous transparence | Aplats à 70 % (esthétique carto habituelle) |
| D-03 | Aucune couleur sémantique hors légende | Empêche toute confusion entre chrome et niveau de danger | Vert « succès » / rouge « erreur » conventionnels |
| D-04 | Rayon plafonné à 2 px | Registre « signalétique peinte », anti-kit UI | Cartes arrondies 8–12 px |
| D-05 | Ombres décalées non floues, dérivées du repère | Une seule audace tenue partout | Ombres douces `0 2px 8px rgba(0,0,0,.1)` |
| D-06 | 2 fichiers de police variables | Budget §10 tenu, hiérarchie par la taille et non par le poids | 4 statiques (dépassement) |
| D-07 | Atkinson Hyperlegible Next pour le texte | Accessibilité bloquante, argument opposable en mémoire technique | Inter / Open Sans (registre par défaut) |
| D-08 | Motifs obligatoires indexés sur la sévérité | Lisible en niveaux de gris et en vision dichromatique ; conforme §8 | Couleur seule + libellé |
| D-09 | Légende officielle en `À CONFIRMER` | Interdiction d'inventer (§4.2) ; le système est paramétré pour l'échange des hex | Déduire les couleurs d'une capture approximative |
| D-10 | Liste du jour traitée en second héros | L'équivalent textuel ne doit pas se lire comme un repli | Liste discrète sous la carte |

---

## 16. Interdits — liste de contrôle de revue

Tout élément ci-dessous constaté par `review-cms` est un **défaut bloquant**.

**Fabrication**
- Constructeur de pages, thème tiers ou par défaut, kit UI, framework CSS générique.
- Toute requête navigateur vers un domaine tiers : police, icône, script, tuile, image.
- Police servie depuis un service de polices, même auto-hébergé « via » un plugin tiers.
- Plus de 2 fichiers de police. Icônes en police d'icônes (les rares symboles sont du SVG en ligne).

**Formes**
- `border-radius` > 2 px. Pilules, avatars ronds, boutons arrondis.
- Ombre floue (`blur-radius` ≠ 0), dégradé décoratif, verre dépoli, néomorphisme.
- Ombre portée sur autre chose que le panneau massif et le bloc de légende.
- Repère apparaissant hors des 7 emplacements listés au §3.2, ou deux repères dans le même bloc.
- Carte enfermée dans un conteneur centré à coins arrondis.

**Couleur et sens**
- Couleur de statut modifiée, réinterprétée, « harmonisée » avec la palette du site.
- Toute couleur du site dans la plage jaune-orange-rouge-vert au-delà de 12 % de saturation.
- Statut encodé par la couleur seule, sans motif **et** sans libellé.
- Texte posé sur un aplat de statut sur la carte.
- Danger météo présenté avec des couleurs, ou visuellement mêlé aux statuts.
- Un statut périmé présenté comme courant, ou un chiffre de la veille conservé en l'absence de donnée.

**Interaction**
- `outline: none` sans remplacement ; focus invisible sur une surface quelconque de la palette.
- Information révélée uniquement au survol ; infobulle porteuse de sens.
- Panneau que Échap ne ferme pas ; piège clavier ; cible < 44 px.
- Bouton désactivé sans explication accessible.
- Animation d'apparition au défilement, parallaxe, compteur animé, spinner.
- Mouvement subsistant sous `prefers-reduced-motion: reduce`, y compris côté Leaflet.

**Contenu**
- « Valider », « OK », « Soumettre », « En savoir plus », « Oups », « Désolé ».
- Emoji, exclamation, superlatif dans l'interface.
- Terme hors du vocabulaire fixe du §11.2.
- Bandeau de non-officialité absent d'une page affichant un statut.
- Attribution OSM, DDTM, Météo-France ou EFFIS manquante.
- Bandeau de consentement aux cookies (il n'y a rien à consentir — §9 du brief).
