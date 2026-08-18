# Contrat d'interface — Issue #71 — Emprise de fond de carte déclarée, découplée de la géométrie, et toponymes cuits

**Gelé le** 17 août 2026 · **Par** `lead-issue-cms`, chaîne #71
**Re-gelé le 18 août 2026** — passe de finition et de preuve, après le commit `a1e2e3b`. Trois écarts
entre ce contrat et le code livré sont réconciliés (§4 `zoom_percu_statique`, §4 seuils de luma, §5
C-a), les octets projetés sont remplacés par les octets **mesurés**, et un **couplage résiduel** que le
gel initial n'avait pas vu est enregistré en **A-13**. Voir le §13, « Ce que la passe du 18 août a
changé ».
**Amende** [`docs/contracts/issue-9.md`](issue-9.md) — §1.1, A-7, A-9, plus la correction consécutive
d'A-12. L'amendement est écrit **au §14 du contrat #9**, et il l'a été **avant** toute modification du
pipeline.
**Lignes de DoD servies** : §12 (repli statique équivalent à la carte · AA bloquante · aucune requête
tierce) · §10 (budgets chiffrés avant et après)
**Statut** : contraignant. Les deux plans amont — `leaddev-back-cms` et `leaddev-front-cms` — ont été
produits en aveugle l'un de l'autre. Ce document est le point de réconciliation ; en cas de divergence
entre un plan et ce contrat, **c'est ce contrat qui fait foi**.

> Règle de lecture reprise de `MASTER.md` : ce document décrit des **décisions**, pas des suggestions.
> Une divergence constatée en revue est un défaut, pas une variante. Les blocs marqués **`OUVERT`**
> sont des trous de connaissance assumés — on ne les comble jamais par déduction (§4.2 du brief).

---

## 0. L'approche retenue, en cinq lignes

**« L'emprise se déclare, les noms se cuisent — un seul build, un seul bump de version. »**

1. **L'emprise de la pyramide devient une grandeur déclarée**, en coordonnées entières de tuile à z12.
   Elle ne se recalcule plus depuis la géométrie ; un sommet du référentiel qui en sort **arrête le
   build**.
2. **Les toponymes sont cuits dans les deux artefacts** — pyramide z9–z12 et `carte-statique.png` —
   en `--c-carte-encre`, avec halo `--c-carte-fond`. Palette **fermée à 7**, aucun jeton nouveau.
3. **Aucun `<text>` n'est jamais émis.** Les glyphes sont extraits en contours et posés en `<path>` :
   aucune police au moment de la rasterisation, donc **aucune substitution système possible**.
4. **Aucun nom de massif n'est cuit**, nulle part — il serait occulté dans la pyramide, et le cuire
   dans la seule statique ferait diverger les deux artefacts.
5. **« Texture, jamais bouillie » est un contrôle, pas une intention** : six critères mécaniques,
   sortie en code ≠ 0. S'ils ne peuvent pas être tenus, **les toponymes sortent de l'image statique**.

**Ordre d'exécution imposé** : l'emprise d'abord, **build vert à 295 tuiles**, puis les toponymes.
Un seul commit, un seul bump de version — mais deux points d'arrêt vérifiables.

---

## 1. Ce que le build garantit

| Garantie | Comment elle est tenue |
|---|---|
| L'emprise est **déclarée**, en entiers de tuile à z12 | `EMPRISE_DECLAREE = { zoom:12, x0:2100, x1:2114, y0:1490, y1:1503 }` ; z5–z11 dérivent par décalage entier `x0 >> (12 − z)` |
| **Déclaré === publié** | `massifs_fond_de_carte()['bbox'] === bboxDeGrille( grilleDeclaree( 12 ) )`, à l'octet |
| Une géométrie qui déborde **arrête le build** | Balayage O(n) sur **chaque sommet**, avant toute rasterisation ; rejoué par la recette |
| Les toponymes sont cuits dans **les deux** artefacts | Pyramide z9–z12 et `carte-statique.png`. **Aucune étiquette à z5–z8** |
| Aucun nom de massif cuit, nulle part | Intersection mécanique avec les 25 noms du référentiel, relus **par PHP** |

**Comptes gelés** : z5 1 · z6 2 · z7 2 · z8 2 · z9 6 · z10 16 · z11 56 · z12 **210** — **total 295**
(auparavant 280, z12 195). Image statique **1600 × 1493**, rapport **15/14 = 1,0714**.

**Surface PHP inchangée.** Aucune clé ajoutée, aucune renommée, `SCHEMA_CONNU` reste `1`. Le contrat #9
**§1.2 n'est pas amendé**, et #71 n'en a pas le droit. Ne bougent que des **valeurs lues dans le
fichier généré** :

| Clé | Avant | Après |
|---|---|---|
| `massifs_fond_de_carte()['nombre']` | 280 | **295** |
| `massifs_fond_de_carte()['bbox']` | dérivée de la géométrie | **emprise déclarée** |
| `massifs_fond_de_carte_statique()['hauteur']` | 1421 | **1493** |
| `version`, `sha256`, `octets` | `926dab38`, … | nouveaux |

**`etats.php` et `metadonnees.php` ne changent pas.** `metadonnees.php` lit par liste blanche
(l. 165-360) et ignore les clés inconnues. **#71 est une issue build + artefacts + documentation, sans
une ligne de PHP modifiée.**

---

## 2. Ce que le thème fait — rien

**Aucun fichier source de `themes/massifs/**` n'est modifié.** Vérifié, non supposé :

- `carte-secours.php` lit `largeur`/`hauteur` sous garde `is_int()` (l. 79-80), ne fait **aucune
  arithmétique**, et `massifs_version_asset()` (l. 34-43) dérive le `?ver=` de `filemtime()` — le
  cache se casse tout seul quand le build réécrit le PNG.
- `style="block-size:auto"` est **agnostique au rapport** : la divergence 10 du §17 de `MASTER.md`
  porte sur l'absence de `block-size: auto` dans `layout.css`, jamais sur une valeur de rapport. Elle
  est **inchangée, mot pour mot**. **La boîte de `carte-secours__image` ne décale rien** : elle est
  réservée par le **mécanisme** (attributs `width`/`height` + `block-size:auto`), pas par les nombres,
  si bien que le passage de 1421 à 1493 px n'y produit **aucun décalage**.

  > **Portée, corrigée le 18 août 2026.** Ce gel écrivait « le CLS reste **nul** », sans portée. C'est
  > faux à l'échelle de la page : la recette mesure un **CLS de 0,10686** à 1280 × 900. La cause est
  > **ailleurs et hors de l'empreinte de #71** — le `<svg>` du pane des massifs de Leaflet passe de 239
  > à 481 px après l'initialisation de la carte. `carte-secours__image` n'y contribue pas, et le
  > mécanisme décrit ci-dessus est intact.
  >
  > **Un contrat ne doit pas énoncer une propriété de page pour justifier une propriété de boîte** :
  > la phrase est donc restreinte à ce qu'elle a réellement vérifié. Le CLS du pane Leaflet appartient
  > à la chaîne carte — voir la couture **C-18**.
- `carte.php` (l. 448-465) lit **exactement quatre clés** de `massifs_fond_de_carte()` : `disponible`,
  `format`, `url_modele`, `zoom_min`, `zoom_max`. Il ne lit **ni `nombre`, ni `bbox`**. Le passage de
  280 à 295 lui est invisible.
- **F-7** tenue : `.carte-secours` ne porte **aucune règle CSS dans tout le thème**, et
  `carte-secours.php` ne pose ni hauteur, ni `min-block-size`, ni `aspect-ratio`.
- **F-11** et **F-13** vraies mot pour mot (`carte.js` l. 198-201 et l. 204-209).
- Aucune valeur en dur : `grep` de `1421|280|bbox|version` sur `themes/massifs/**` ne rend aucune
  occurrence pertinente.

**Croissance de page, jamais saut** — conséquence mesurée du rapport 1,1259 → 1,0717 :

| Fenêtre | avant | après | Δ |
|---|---|---|---|
| 360 px | 319,7 px | 336,0 px | +16,3 px |
| 900 px | 799,2 px | 839,9 px | +40,7 px |
| ≥ 1600 px | 1421 px | 1493 px | +72 px |

**Aucun travail CSS.** `--c-carte-encre` existe déjà (`tokens.css` l. 27) et **reçoit ici son premier
consommateur** : il quitte la rubrique « jetons déclarés et consommés par personne » du §12.1 de
`MASTER.md`. `--c-carte-fond` (halo) existe l. 22. `dev-ux-cms` **n'est pas lancé**.

---

## 3. Règle de sélection des toponymes — gelée

```
node["place"~"^(city|town|village)$"]
```

`hamlet`, `suburb`, `locality` sont **exclus**. Rang, entièrement dérivé d'attributs OSM :

1. **classe** — `city` > `town` > `village` ;
2. **`population`** décroissante — absente ou illisible = `0`, donc dernière. Une population réelle de
   0 est impossible, la sentinelle est donc non ambiguë ;
3. **`nom`** lexicographique, par `<` **sur la chaîne brute**. **Jamais `localeCompare`** : il dépend
   de l'ICU embarqué dans Node et rendrait l'empreinte de version **dépendante de la machine**.

**Densité** : `round( 25 × airePlacementMpx( z ) )`, où `airePlacementMpx( z )` est l'aire, **en pixels
de toile au zoom `z`, de `massifs_emprise()['bbox']`** — jamais la toile entière, jamais l'emprise
déclarée. Cette précision est normative : la toile z9 fait 0,393 Mpx contre 0,158 Mpx pour l'emprise, et
un développeur qui écrirait `g.largeur_px * g.hauteur_px` livrerait **2,5 fois trop d'étiquettes**.

**Comptes gelés** : z5–z8 **0** · z9 **4** · z10 **16** · z11 **63** · z12 **63** (= z11, par règle) ·
statique **≤ 6, et ⊆ jeu z10** *(z9 au gel initial — révisé le 18 août 2026, voir **A-8**)*.

**Vérifié sur le manifeste livré, le 18 août 2026** : les comptes z9/z10/z11/z12 sont exacts, la
monotonie **I-71.10** tient à chaque palier (4 ⊂ 16 ⊂ 63), et le jeu z12 est **identique** au jeu z11,
corps 19 → 38. Le jeu de la statique est
`{ Marseille, Salon-de-Provence, Istres, Marignane, Allauch, Port-de-Bouc }` — **inclus dans le jeu
z10, et non dans le jeu z9**.

**Les toponymes sont découpés au département**, comme les quatre autres couches. Hors du département la
carte est **uniformément `--c-carte-fond`** (§4.2 de `MASTER.md`) : un nom de ville flottant sur un
terrain que nous avons délibérément effacé affirmerait une géographie retirée. L'emprise du référentiel
déborde sur le Vaucluse, le Gard et le Var — ce n'est pas théorique. Découpe **par mapshaper**, jamais
à la main : le département est un multipolygone à trous et à îles détachées.

**Bornes de garde Overpass** — `BORNES_OSM.toponymes`, mesurées puis gelées comme les quatre
existantes, par la **procédure en deux passes du §8**. Trois gardes d'attribut s'y ajoutent, et elles
sont nécessaires : c'est la **première couche dont les attributs portent l'information**, et un
dénombrement seul ne peut pas attraper une charge où chaque `name` serait arrivé vide — laquelle
produirait **moins d'étiquettes au lieu d'un échec**, exactement la dégradation silencieuse que ce
module refuse partout ailleurs.

1. **complétude** — `rejetes / retournes ≤ 0,20`, sinon `Arret` nommant les deux comptes ;
2. **coordonnées** — zéro point à `lat`/`lon` non finie ;
3. **plancher après découpe** — `≥ plancher / 2`. Une découpe qui vide la couche signifie que le
   polygone `terre` est faux, et cela ne doit pas se manifester par « une pyramide sans noms » ;
4. **`place=city` ∈ [1, 10]** — **adoptée** (arbitrage A-11). Marseille et Aix sont structurellement
   présentes ; une charge des Bouches-du-Rhône sans aucune `city` est tronquée. Le risque nommé — un
   retaggage OSM ferait rougir la garde — est accepté : l'échec est **bruyant et réparable par une
   décision humaine**, ce qui est la philosophie du module.

---

## 4. Réglages — mesurés ou dérivés, jamais choisis

```
TOPONYMES = {
  zoom_min_etiquettes:      9,      // règle, pas réglage
  densite_par_mpx:         25,
  zoom_percu_statique:     10,      // 9 au gel initial — RÉVISÉ, voir A-8
  corps_px:                19,      // PYRAMIDE — voir A-1
  facteur_z12:              2,
  halo_px:                1.5,
  padding_px:               3,
  corps_statique_px:       28,
  corps_min_statique_px:   25,      // plancher d'impression, arrondi VERS LE HAUT
  halo_statique_px:         2,
  padding_statique_px:      4,
  marge_contour_px:         6,
  ecart_min_statique_px:   14,
  etiquettes_statique_max:  6,
  couverture_encre_max: 0.005,
  facteur_360:          0.225,
  plage_luma_min_360:      89,      // MESURÉ le 18/08/2026 puis gelé — min 112,4 (Istres) − 20 %
  luma_moyenne_min_360:   159,      // MESURÉ le 18/08/2026 puis gelé — min 199,7 (Marignane) − 20 %
  recul_sombres_max_360: 0.05,
  ancrages: [ 'C', 'N', 'S', 'E', 'O' ],
}
```

Dérivations, une ligne chacune, **à reproduire en JSDoc** :

- **`zoom_min_etiquettes: 9`** — à z8 la région utile fait **211 × 187 px** (210,594 × 187,084) ; « Aix-en-Provence »
  cuit à 19 px y mesure 137 px, soit **65,1 % de la largeur** — un seul mot en occuperait plus de la
  moitié. **Règle, pas réglage.**
  *[Corrigé le 18 août 2026 : le gel initial écrivait **210 × 188**, obtenu en **doublant les chiffres z7
  déjà arrondis** (105 × 94) au lieu de projeter z8 directement. L'écart est d'1 px et ne touche pas
  l'argument, mais deux valeurs pour une même grandeur dans deux documents gelés sont une dette. La
  valeur retenue est celle du **calcul depuis la source**, et elle est la même ici, au §7 du README et
  dans les deux JSDoc du build.]*
- **`corps_px: 19`** — voir **A-1**. Deux dérivations indépendantes convergent vers un plancher, et
  c'est la plus haute qui l'emporte.
- **`facteur_z12: 2`** — une tuile z12 est **toujours** rendue à l'échelle de z11 (F-11 + A-7). Corps,
  halo et padding sont multipliés ; **le jeu de noms et les ancres géographiques sont identiques**.
- **`halo_px: 1.5` / `halo_statique_px: 2`** — D-28 avait rejeté un halo calcaire de 4 px parce qu'il
  **remplissait la boîte englobante** d'une petite forme. Les rapports ici sont 1,5/19 = **0,079** et
  2/28 = **0,071**, un ordre de grandeur sous le mode de défaillance de D-28. **Écrire le rapport, pas
  seulement le pixel.**
- **`corps_min_statique_px: 25`** — dérivé : 1600 px sur les 186 mm utiles de l'A4 (§13 de
  `MASTER.md`) = **218,5 ppp** ; 8 pt = 8/72 in × 218,5 = **24,3 px**. C'est un **plancher**, donc il
  s'arrondit **vers le haut**. `corps_statique_px = 28` le franchit de 12 %.
- **`marge_contour_px: 6`** — `DESSIN.contour_px` (2) + `halo_statique_px` (2) + 2 px de fond pur, pour
  que halo et contour ne se touchent pas même après l'étalement d'un pixel par la quantification.
- **`ecart_min_statique_px: 14`** — voir **A-3**.
- **`etiquettes_statique_max: 6`** — écrit au gel initial comme une **garde, pas un moteur**, le moteur
  étant la règle `statique = jeu du zoom perçu` (z9 → 4), le jeu de 2 servant à empêcher qu'un
  changement de `densite_par_mpx` n'inonde la statique en silence.
  **Depuis le passage de `zoom_percu_statique` à 10, ce plafond MORD** : le zoom perçu propose 16
  candidats et le plafond en retient 6. **La garde est devenue le moteur, et c'est écrit plutôt que
  subi** — voir **A-8**. Ce qui reste vrai, et c'est ce qui compte : le nombre livré n'est pas
  *choisi*, il est le minimum de deux règles, et les six noms retenus le sont par le **rang** gelé au
  §3 (classe, puis population décroissante, puis nom), jamais à la main.
- **`couverture_encre_max: 0.005`** — voir **A-6**.

---

## 5. Les six critères de « texture, jamais bouillie » — la condition ferme du propriétaire

La ligne de DoD « lisible à 360 px » est **arithmétiquement inatteignable au sens littéral** : 1600 px
sous `max-inline-size: 100%` s'affichent à **22,5 %** sur une fenêtre de 360 px, donc une étiquette
cuite à 28 px rend **6,3 px CSS**, tandis que le plancher d'impression exige **24,3 px cuits**. Les
deux exigences ne se rencontrent pas.

**Lecture retenue, arbitrée par le propriétaire** : le plancher de lisibilité est fixé par
l'**impression**, et **aucune information ne dépend de la lecture d'un toponyme** — le §5.5 du brief
pose que l'image renvoie à la liste textuelle, où tout le contenu informationnel reste disponible.

**La condition attachée est tenue par six contrôles mécaniques, pas par une phrase.** Chacun sort en
code ≠ 0, dans `construire` **et** dans `verifier` :

| # | Critère | Valeur |
|---|---|---|
| **C-a** | Nombre d'étiquettes de la statique | **≤ 6**, et **⊆ jeu z10** *(z9 au gel initial — voir **A-8**)* |
| **C-b** | Écart minimal entre deux boîtes dilatées, halo compris | **≥ 14 px cuits** |
| **C-c** | Couverture d'encre dans le PNG livré | **≤ 0,5 %** de la toile |
| **C-d** | Corps cuit minimal, statique | **≥ 25 px** |
| **C-e** | Dégagement de tout pixel `--c-charbon`, **et** de l'intérieur de tout polygone de massif | **≥ 6 px** |
| **C-f** | **Après ré-échantillonnage à 360 px** — plage et moyenne de luma sur chaque boîte, **et** non-recul des pixels sombres | seuils **mesurés puis gelés** |

**C-f est le seul qui mesure la propriété énoncée** ; C-a à C-e sont des propriétés des métadonnées, et
un build pourrait les satisfaire toutes en produisant une bouillie grise. C-f a **deux moitiés
complémentaires**, l'une venue de chaque plan (arbitrage **A-7**) :

- **survie de l'étiquette** — sur le PNG ré-échantillonné à 360 px (`sharp`, `kernel: 'lanczos3'`), la
  **plage** `max_luma − min_luma` et la **moyenne** de chaque boîte restent au-dessus de seuils gelés.
  Une étiquette qui s'effondre en gris uniforme perd sa plage la première. Luma en sRGB pondéré
  (`0,2126 R + 0,7152 G + 0,0722 B`), **non linéarisé** : on mesure une texture apparente, pas une
  photométrie ;
- **non-noyage de l'information** — le compte de pixels sombres (les 25 contours) après réduction **ne
  recule pas de plus de 5 %** contre la référence pré-#71. Le vrai risque n'est pas que les noms soient
  illisibles — ils le sont, et c'est assumé — c'est qu'ils **noient ce qui compte**.

**Seuils mesurés puis gelés, jamais prédits.** Le style de la maison est « réglages mesurés, non
choisis » — `PALIERS` et `COUCHES_STATIQUE` l'ont tous deux été. Le build **imprime** les deux nombres
par étiquette ; le développeur gèle le **minimum mesuré moins 20 %**, dans le même commit, daté en
README §7. Prédiction de contrôle de vraisemblance, **non normative** : luma du fond 230,3, de l'encre
76,7, plage attendue ≈ 69 et moyenne ≈ 207. **Une plage de 20 au premier build signalerait un défaut de
pipeline, pas un seuil mal placé.**

**Si C-f ne peut pas être tenu, les toponymes sortent de l'image statique** et restent dans la seule
pyramide. C'est une issue prévue, écrite d'avance, et **ce n'est pas un échec**.

### Le contrôle qui fonde la conformité AA

| **C-g** | Sur un échantillon de tuiles décodées, dans chaque boîte : `#{pixels encre} / #{pixels non-fond} ≥ 0,35` | code ≠ 0 |

**Sans lui, la ligne « 4,5:1 » de ce contrat serait une intention.** La palette fermée fournit une
**rampe accidentelle à 5 niveaux** (encre → trait → végétation → terre → fond), si bien que la frange
d'anticrénelage de chaque glyphe est peinte en `--c-carte-trait`, qui vaut **4,17:1**. Le 6,82:1 repose
donc sur le **cœur** du glyphe. À corps trop faible, une hampe d'Atkinson à cheval sur une frontière de
pixel peut se quantifier **entièrement** en `trait` — et l'étiquette n'a alors **aucun cœur à 6,82:1**.
C-g rend cette propriété **assertée** au lieu d'espérée, et c'est la seconde raison de `corps_px = 19`.

---

## 6. Invariants — vérifiables en revue

| # | Invariant | Contrôle |
|---|---|---|
| **I-71.1** | **Aucun toponyme cuit ne porte d'information nécessaire à l'usage du site.** Massif, statut, consigne, date de validité, source et fraîcheur restent portés par la liste textuelle (§5.3) et le panneau (§8.4). **Corollaire opposable** : retirer la totalité des toponymes des deux artefacts ne retirerait **aucune** information au visiteur | Revue, sur le corollaire. Voir **A-2** |
| **I-71.2** | **Aucune étiquette n'est rognée par le bord de la toile.** Une boîte dilatée qui sortirait de la toile est **rejetée**, jamais coupée — rogner un nom est une troncature, donc une invention (§4.2) | Contrôle de confinement, à chaque zoom |
| **I-71.3** | **Le nom cuit est `tags.name` verbatim.** Ni `name:fr`, ni `int_name`, ni repli, ni abréviation, ni troncature, ni changement de casse, ni traduction, ni translittération, ni suffixe de désambiguïsation | Revue. C'est la ligne la plus susceptible d'être « améliorée » plus tard : elle porte son propre commentaire disant qu'il ne faut pas |
| **I-71.4** | **Code de sortie ≠ 0 si un sommet du référentiel quitte l'emprise déclarée.** Le message nomme le massif, le bord, la valeur, la borne et le débordement, et prescrit de **décider** une nouvelle emprise, jamais de la recalculer | Balayage dans `construire`, rejoué dans `verifier` |
| **I-71.5** | **Aucun nom de massif n'est cuit, dans aucun artefact** | Intersection avec les 25 noms du référentiel, relus **par PHP**, casse et accents normalisés |
| **I-71.6** | **Aucun halo ne touche jamais un aplat de statut.** Structurel : en raster les étiquettes sont cuites dans la tuile (`tilePane`, 200), les aplats sont peints au-dessus en SVG (400) | **Déjà tenu par deux contrôles existants** — « aucune couleur hors palette » et I-9.3. **Aucun contrôle nouveau** |
| **I-71.7** | **La police cuite est contrôlée comme les jetons le sont.** `sha256`, `nomPostScript` et `upem` du `woff2` sont consignés au manifeste et **relus par la recette**. Instance par défaut uniquement : **aucun appel à `getVariation`** | Symétrique de I-9.7 : un fichier de thème cuit dans les octets sans être contrôlé serait le trou que I-9.7 ferme pour les couleurs |
| **I-71.8** | **Aucun `<text>` n'est jamais émis**, et `resvg` tourne avec **`loadSystemFonts: false`** | `grep` de `<text` / `font-family` / `getVariation` sur `build/**` → zéro. La substitution silencieuse devient **structurellement impossible**, pas seulement improbable |
| **I-71.9** | **Le jeu z12 est exactement le jeu z11, cuit au double.** Mêmes noms, mêmes ancres, boîtes ×2 | Sans quoi un écran ordinaire et un écran dense afficheraient des **noms différents** — une divergence de données, pas une nuance de rendu |
| **I-71.10** | **Le jeu d'étiquettes est monotone** : `noms(z) ⊇ noms(z−1)` | **Produit, non espéré** : le jeu de `z` est semé de force dans `z+1` avant tout nouveau candidat. Un nom forcé non plaçable à un zoom plus fin **lève `Arret`** |
| **I-71.11** | **Sur la statique, aucune étiquette ne s'approche à moins de 6 px d'un pixel `--c-charbon`, ni n'intersecte l'intérieur d'un polygone de massif** | Mesuré sur le **PNG décodé**, avec le même utilitaire que le build. Voir **A-9** |
| **I-71.12** | **« Texture, jamais bouillie » est un critère testé** — C-a à C-f. **Si C-f ne peut pas être tenu, les toponymes sortent de l'image statique** | Six contrôles, code ≠ 0 |
| **I-71.13** | **Les toponymes cuits passent 4,5:1** — halo `--c-carte-fond` sous encre `--c-carte-encre` = **6,82:1** | **C-g**, qui prouve l'existence d'un cœur d'encre. La frange est en `--c-carte-trait` (4,17:1) et **n'est pas le texte** |
| **I-71.14** | **Une archive sans couche `toponymes` exploitable ⇒ `mode = 'degrade'`** : aucune pyramide émise, `verifier` rouge. **I-9.9 intact** — la statique est produite quand même, hors ligne, sans étiquettes | Seul le *contenu de « complet »* change ; jamais la garantie |

---

## 7. Interdits

### Portant sur le thème — en plus de ceux du contrat #9 §7, tous maintenus

1. Le thème **n'affiche, ne réutilise ni ne recompose jamais un toponyme cuit.** Ce sont des **pixels,
   pas des données** ; aucune fonction PHP ne les expose, et c'est délibéré.
2. Le thème **ne superpose jamais de texte à la carte raster** pour « compléter » ou « corriger » un
   toponyme : la règle 8 place les étiquettes **sous** les aplats de statut, et une étiquette DOM les
   remettrait dessus.
3. Le thème **ne pose jamais `image-rendering`, `filter`, ni aucune transformation** sur la statique ou
   sur les tuiles : le halo, la rampe à 5 niveaux et le ratio 6,82:1 sont **cuits**, et toute retouche
   navigateur les casse en silence.
4. Le thème **ne remplace jamais `alt=""` par une description.** Le §11.3 de `MASTER.md` est une liste
   **fermée** : rédiger une phrase d'`alt` décrivant les toponymes serait inventer une chaîne de site.

### Portant sur le build

5. **Aucun `<text>`, aucun `font-family`, aucun `getVariation`.**
6. **Aucun nom abrégé, tronqué, recassé, traduit, translittéré ou désambiguïsé.**
7. **Aucun `localeCompare`** dans le classement — l'empreinte de version deviendrait dépendante de la
   machine.
8. **Aucun titre, aucune légende, aucune échelle graphique, aucune rose des vents, aucun libellé de
   statut, aucune date, aucune attribution** cuits. La flèche U+2192 est de surcroît **absente des deux
   polices** (D-25).
9. **Aucune couleur hors des 7 jetons**, halo compris.
10. **Aucun dépassement silencieux du plafond de 153 600 o.**
11. **Aucun toponyme hors du département** — la couche est découpée comme les quatre autres.

---

## 8. Arbitrage d'octets, et la procédure de récupération

### Le plafond n'est plus saturé — la prémisse est retirée

| Poste | Valeur | Base |
|---|---|---|
| Statique **avant** #71 | **120 768 o** | mesuré le 17 août 2026 |
| Plafond | **153 600 o** | `PLAFOND_STATIQUE_OCTETS`, gelé |
| Marge **avant** #71 | 32 832 o (21,4 %) | |
| Effet du changement d'emprise | **−4 000 à +1 000 o** | îlots filtrés +28 %, intervalle 29 → 33 m, littoral −12 % en px, contre +72 lignes quasi uniformes |
| 6 étiquettes, 28 px, halo 2 px | **≈ +9 200 o** | ≈ 25 000 px d'encre+halo × ≈ 0,22 o/px, coefficient dérivé de la couche `routes` |
| Total **projeté** | ≈ 126 000 – 131 000 o | 82–85 % du plafond |

**Mesuré après livraison — c'est cette ligne qui fait foi, et elle infirme la projection dans le bon
sens :**

| Poste | Valeur | Base |
|---|---|---|
| **Statique livrée** | **109 050 o** | mesurée sur le fichier le 18 août 2026, identique à l'objet commité dans `a1e2e3b`, `sha256` `cf6f122f…` conforme à `reference.json` |
| **Marge réelle** | **44 550 o (29,0 %)** | le plafond est tenu à **71,0 %** |

**La projection était fausse d'un facteur ≈ 30 sur le coût d'une étiquette** — redoutée à ≈ 1 500 o
pièce, mesurée à **464 o**. Et la statique **a maigri** de 11 718 o alors que sa surface et son encre
augmentaient : la cause est la **marge d'emprise**, une zone uniforme que le PNG-8 comprime à presque
rien. **Ce n'est pas une optimisation, c'est un effet de bord de A-14** — à ne pas relire comme un
gain acquis, il disparaîtrait avec la marge.

> **Aucun de ces nombres ne doit être recopié ailleurs.** Le poids courant vit dans
> `build/reference.json`, clé `statique.octets`, et **la mesure se fait sur le fichier, jamais sur un
> chiffre relayé par un rapport**. Le 120 768 o ci-dessus est **historique et daté** ; il a déjà
> circulé comme s'il était courant.

**La mer n'est pas un coût** : la statique rétrécit probablement, pour trois raisons cumulatives que le
plan back a établies. Ne pas prélever le changement d'emprise sur le budget des étiquettes.

Le **142 464 o** du §7 du README est **périmé depuis `ded0f2f`/`fd2c10f`**, et sa correction est une
tâche de l'issue. Il n'est pas remplacé par un autre nombre — il se périmerait pareil : le §7 passe aux
**écarts entre couches**, qui sont stables, datés « mesures du build initial du 13 août 2026, ordre de
grandeur, non normatives », **plus une ligne disant que le poids courant vit dans
`build/reference.json`, clé `statique.octets`, et dans aucune prose.**

**Fait à écrire, parce qu'il sera reposé — et il a changé de nature à la livraison.** Au gel initial,
**1 palier d'anticrénelage était hors d'atteinte, et de peu** : le rapport de 1,2886 appliqué aux
120 768 o d'alors donnait **155 641 o** contre 153 600, un dépassement de **1,3 %**. `PALIERS = 0`
était donc un **réglage tenu, pas une dette**.

**Sur la statique livrée, ce calcul bascule** : le même rapport appliqué au poids courant retombe
**sous le plafond**. 1 palier redeviendrait finançable en octets. **Il n'est pas activé pour autant, et
la justification a changé de nature** — ce n'est plus le plafond d'octets qui tient `PALIERS = 0`,
c'est la **fermeture de la palette à 7** (interdit 9 du §7). C'est désormais une **décision de design**,
à rouvrir par une révision de contrat et non par une mesure d'octets. La rampe accidentelle à 5 niveaux
la rend indolore pour le texte.

### Ordre des leviers si le plafond est menacé

**En amont** des trois mitigations du §2 du contrat #9, qui répondent à un autre problème :

1. **Moins de toponymes** — abaisser `zoom_percu_statique` ou `densite_par_mpx`. **En premier, parce
   que c'est le seul levier qui améliore aussi la lisibilité.**
2. **Retirer le halo**, remplacé par une règle de placement plus stricte (n'accepter que si la boîte
   dilatée ne couvre que fond/terre/eau) — ≈ 60 % des octets d'étiquette.
3. **Réduire le corps** — **course quasi nulle : le plancher d'impression est 24,3 px.** À écrire comme
   tel, sinon quelqu'un l'actionnera en premier parce que c'est le curseur le plus facile.
4. **Alors seulement** les mitigations du §2 du contrat #9 : palette, couches, puis 1280 px.

**Jamais** : compression avec perte, second artefact, `srcset`, couleur hors des 7 jetons.

### Procédure de récupération — deux passes, et c'est la seconde qu'on commite

Les bornes de garde **ne peuvent pas être fixées sans un appel réseau**, et les inventer serait
exactement l'invention que le module refuse.

1. Poser une enveloppe de vraisemblance large : `{ plancher: 50, plafond: 5000 }` — défendable en soi
   (moins de 50 lieux nommés dans les Bouches-du-Rhône est absurde ; plus de 5 000 nœuds
   `city|town|village` dans un département est impossible). Elle attrape déjà une charge catastrophique.
2. `npm run recuperer`. Lire `n = comptes_overpass.toponymes`.
3. Resserrer à `{ plancher: round(n/2), plafond: round(3n) }` — la règle de la maison, écrite à
   `commun.mjs` l. 243-246.
4. `npm run recuperer` **de nouveau**. Les bornes resserrées sont maintenant éprouvées sur une charge
   réelle ; **c'est cette archive-là qui est commitée**.
5. `npm run construire`, puis **regarder le rendu dans Chrome** — 360 px, 100 %, 200 % — et finaliser
   `corps_px` / `corps_statique_px`. **Aucun test ne remplace ce regard.**
6. Geler les deux seuils `*_360` mesurés, puis un dernier `npm run construire` pour que manifeste,
   version et `reference.json` soient cohérents.
7. `npm run verifier` avec PHP atteignable.

**`npm run recuperer` ne tourne jamais en intégration continue** (A-8 du contrat #9), et **une
récupération en échec n'écrase jamais l'archive en place**.

---

## 9. Arbitrages — chaque désaccord entre les deux plans, la décision, sa raison

### A-1 — Corps des étiquettes de la pyramide : deux dérivations, deux nombres, le plancher le plus haut l'emporte

- **Back** : `corps_px = 15`, contre les 13 du cadrage. Motif : à 13 px les hampes d'Atkinson peuvent se
  quantifier **entièrement** en `--c-carte-trait` (4,17:1), et l'étiquette perd son cœur à 6,82:1.
- **Front** : **≥ 19 px**. Motif entièrement différent — `carte.js` pose `zoomSnap: 0.25`, donc
  `L.GridLayer` charge `Math.round(zoom)` et **applique une mise à l'échelle CSS** au reste. Le facteur
  garanti est **0,7071** (et vaut exactement cela sur la vue initiale desktop, z 9,5). Une étiquette
  cuite à 13 px ne rend donc **pas** 13 px CSS mais **9,2**. Pour tenir le plus petit corps du système,
  `--fs-100` = 13 px, il faut `13 / 0,7071 = 18,4` ⇒ **19**.

**Décision : `corps_px = 19`**, et **les deux motifs sont écrits**.

**Raison.** Ce sont deux planchers indépendants sur la même grandeur, l'un typographique, l'autre
optique ; le respect du plus bas ne satisfait pas l'autre. Le front a vu un fait que le back ne pouvait
pas voir — la mise à l'échelle fractionnaire vit dans `carte.js`, hors du module. La pyramide **n'a
aucun plafond d'octets**, donc rien n'argumente en faveur du plus petit nombre. **C'est le cas d'école
qui justifie ce contrat : deux agents compétents, en aveugle, ont produit deux planchers corrects et
différents pour la même constante.**

### A-2 — `I-71.1` tel que je l'avais rédigé était **faux**

Le back l'a attrapé, et il a raison : « aucun toponyme cuit ne porte une information **absente de la
liste textuelle** » serait **falsifié par la première étiquette** — *Marseille* **est** absente d'une
liste qui porte des massifs et des statuts.

**Décision : la formulation du back (« aucune information *nécessaire à l'usage du site* »), avec le
corollaire opposable du front en prime** (« retirer tous les toponymes ne retirerait aucune
information »). Le premier énonce la propriété, le second la rend **applicable mécaniquement par un
relecteur, sans juger d'intention**. Les deux plans avaient chacun une moitié.

Ce gel est **porteur de charge pour `alt=""`** : le contrat #9 §6 fixe l'alternative vide parce que
« l'information exploitable de l'image est intégralement portée par la liste adjacente ». Le gel et
`alt=""` sont **la même décision vue deux fois**, et ils se verrouillent mutuellement.

### A-3 — Écart minimal : mon 9 px était dérivé du facteur d'affichage optimiste

Les deux plans convergent **contre le cadrage**, sur deux motifs qui s'additionnent : 2 px CSS est le
seuil où deux marques **fusionnent**, alors que la cible est « n'approche jamais la fusion » ; et
**0,225 est le bout optimiste** du facteur — l'image vit dans `.bande--carte`, dont les gouttières
ramènent le facteur réel vers ≈ 0,205.

**Décision : `ecart_min_statique_px = 14`** (cible 3 px CSS ⇒ 13,3 ⇒ 14). Rend 2,9 px CSS à 0,225 et
**2,7 px à 0,205**. Sur une toile de 1600 × 1493 portant 4 à 6 étiquettes, c'est gratuit.

### A-4 — Plafond du nombre d'étiquettes de la statique

- **Front** : `≤ contours_massifs` (= 25), auto-calibré sur une clé déjà contrôlée.
- **Back** : `etiquettes_statique_max = 6`, garde adossée au moteur `statique = jeu z9` (= 4).

**Décision : 6.** La borne du front est élégante mais **trop lâche d'un facteur quatre** : 25 étiquettes
coûteraient ≈ 21 600 o et violeraient C-c. Elle est conservée comme **contrôle de vraisemblance
redondant**, jamais comme la borne mordante.

### A-5 — Couverture d'encre : la mesure exacte bat la mesure relative

- **Front** : `≤ ⅓ × pixels --c-charbon`, plus un plafond absolu de 2,5 %.
- **Back** : `≤ 0,5 %` de la toile, comptée sur **l'index de palette `--c-carte-encre` seul**.

**Décision : celle du back.** Elle est **exacte**, et pour une raison que #71 hérite du design existant :
`--c-carte-encre` **n'a aucun autre consommateur dans la statique** (`COUCHES_STATIQUE` = terre + eau,
qui emploient terre/trait/eau/fond ; les contours emploient charbon). Compter les pixels d'index encre
compte donc **exactement l'encre des toponymes**, sans estimation. Estimation pour 6 étiquettes à
28 px : **0,29 %**, soit une marge de 1,7×. Le halo **n'est pas borné** : il est couleur de fond et
invisible sur le fond — le borner serait borner la mauvaise chose.

### A-6 — Le critère de texture : les deux moitiés sont complémentaires, pas concurrentes

- **Back** : plage et moyenne de luma par boîte après réduction à 360 px — mesure si **l'étiquette
  survit**.
- **Front** : non-recul de plus de 5 % du compte de pixels sombres — mesure si **l'information n'est
  pas noyée**.

**Décision : les deux, sous le seul code `C-f`.** Elles répondent à deux questions différentes et coûtent
chacune quelques millisecondes. Le back avait raison sur le fond — **cinq critères sur six mesurent les
métadonnées, aucun ne mesure l'image** — et le front a nommé le risque que le back ne mesurait pas.

### A-7 — L'exclusion sur la statique : la formulation forte du front l'emporte

- **Back** : rejet si la boîte dilatée s'approche à moins de 6 px d'un pixel `--c-charbon`.
- **Front** : rejet si la boîte **intersecte l'intérieur d'un polygone de massif**.

**Décision : les deux, cumulées.** Le raisonnement du front est décisif et le back ne pouvait pas le
faire : dans la pyramide, un nom intérieur à un massif est **toujours** occulté par un aplat opaque —
les quatre états en peignent un. Dans la statique, **aucun aplat n'est jamais peint** (I-9.3). Le même
nom serait donc **visible dans un artefact et caché dans l'autre**, ce que le §5.5 du brief interdit.

**Formulation gelée : l'équivalence entre les deux artefacts se mesure sur ce qui est VISIBLE, jamais
sur ce qui est cuit.** Le coût attendu est nul — le jeu z9 est fait de grandes villes, rarement
intérieures à un massif forestier — et le bénéfice est de fermer la divergence **par construction**.

> **CORRECTION du 18 août 2026 — « le coût attendu est nul » était faux, et c'est la seule prévision de
> ce contrat que la mesure a franchement démentie.** L'exclusion coûte **trois noms sur quatre** :
> Aix-en-Provence, Martigues et Aubagne sont rejetés **aux cinq ancres**, par les **deux moitiés d'A-7
> indépendamment** — ils sont contre Sainte-Victoire, la Côte Bleue et le Garlaban. La prémisse
> « rarement intérieures à un massif forestier » ne tient pas dans un département dont les villes
> bordent les massifs.
>
> **La décision d'A-7 n'est pas remise en cause** — elle reste juste, et pour son motif d'origine. C'est
> son **coût** qui était mal estimé, et ce coût est ce qui a rendu z9 intenable et forcé la révision
> d'**A-8**. À lire comme la leçon de méthode : *« coût attendu nul » est une prévision, pas un fait, et
> ce contrat exige ailleurs qu'on mesure avant de geler.*

### A-8 — `zoom_percu_statique` : 9 (4 noms) ou 10 (16 noms) ?

Le back a chiffré les deux et recommande 9, en signalant honnêtement que **10 ferait une meilleure
feuille imprimée** pour un gîte ou une mairie, au prix de ≈ 143 000 o = **93 % du plafond**.

**Décision au gel initial : 9.** 93 % du plafond n'est pas un endroit où s'installer, surtout quand
l'estimation porte une incertitude de l'ordre de ±100 %. Le degré de liberté est réduit à **un seul
entier** : si le premier regard dans Chrome montre la feuille trop nue, la correction honnête est de
bouger cette constante et de re-mesurer — **jamais de choisir des noms à la main**.

#### RÉVISION du 18 août 2026 — `zoom_percu_statique` passe à **10**

**Les deux prévisions qui fondaient le 9 ont été infirmées par la mesure, et elles l'ont été dans le
sens qui rouvre la question.**

1. **A-7 annonçait un coût nul** pour l'exclusion des intérieurs de massif. **Mesuré, elle coûte trois
   noms sur quatre** : Aix-en-Provence, Martigues et Aubagne sont rejetés **aux cinq ancres**, par les
   **deux moitiés d'A-7 indépendamment** — ils sont contre Sainte-Victoire, la Côte Bleue et le
   Garlaban. À z9, la feuille ne portait plus que **« Marseille »**.
2. **A-8 redoutait ≈ 143 000 o** pour 16 étiquettes, soit 93 % du plafond. **Mesuré, une étiquette
   coûte 464 o** : six en coûtent ≈ 2 800, et la statique tient à **71,0 %** du plafond. La prévision
   était fausse d'un facteur **≈ 30**.

**Décision : 10.** Une image de repli portant **un seul nom** n'honore la règle « la statique est
l'équivalent de la carte » (§5.5 du brief) que **formellement**. Le motif qui imposait 9 — le coût
d'octets — n'existe pas.

**Cette révision était pré-autorisée par A-8 lui-même**, qui prescrivait exactement ce geste : bouger ce
seul entier et re-mesurer. Ce n'est donc **pas une dérive**, mais l'application de l'arbitrage. **Ce qui
était une dérive, c'est que le contrat ne l'ait pas enregistrée** : le code et le README portaient 10
depuis `a1e2e3b` pendant que le §3, le §4, C-a et A-8 portaient 9. **Un contrôle vert contre une
constante du code ne prouve rien contre un contrat qui dit autre chose** — `verifier.mjs` relit
`TOPONYMES.zoom_percu_statique`, pas ce document. C'est ce que cette passe corrige.

**Conséquence assumée, écrite au §4** : `etiquettes_statique_max = 6` cesse d'être une garde et devient
le moteur.

### A-9 — Garde `place=city ∈ [1, 10]` : adoptée

Le back la recommandait en laissant le contrat trancher. **Je la prends.** Elle est beaucoup plus forte
qu'une borne de dénombrement, et le risque nommé — un retaggage OSM la ferait rougir à tort — est
précisément le mode de défaillance que ce module préfère : **bruyant, daté, réparable par une décision
humaine écrite.** Le mode inverse, une charge tronquée qui produit silencieusement moins d'étiquettes,
est celui qu'il refuse partout.

### A-10 — Typographie : convergence, et **aucune divergence de design system à enregistrer**

Les deux plans concluent identiquement, et la conclusion est meilleure qu'un repli subi. La **Règle de
portée typographique** de `MASTER.md` (l. 612-644) est déclarée **frontière opposable**, énumère
**trois zones** pour la famille d'affichage — ardoise, légende de la carte, titres de statut — et pose
l. 620 : « **partout ailleurs, la famille de texte (Atkinson Hyperlegible Next) est seule employée** ».
Un toponyme cuit n'appartient à aucune des trois. **Atkinson est donc prescrite, pas subie.**

La contrainte technique **converge** avec la prescription au lieu de la contredire : `getVariation()`
échoue sur woff2 en fontkit 2.0.4, donc seule l'instance par défaut est atteignable — et celle
d'Atkinson **est** Regular/400, à l'intérieur de la plage 400→700 déclarée au §5. Big Shoulders est
inutilisable pour la raison exactement symétrique : son instance par défaut est **Thin/100**, ce que
`MASTER.md` l. 574-576 et `PROVENANCE.md` l. 50 documentent tous deux.

**Aucun item §18, aucune divergence §17.** À écrire comme le cas heureux, pas comme une contrainte subie.

### A-11 — z12 est probablement inatteignable, et on cuit ses étiquettes quand même

Le front a établi que **z12 n'est jamais chargé aujourd'hui** : `L.GridLayer` charge `Math.round(zoom)`,
`maxZoom` est plafonné à 11 par F-11, et **`detectRetina` est absent de `carte.js`**. Les deux
justifications écrites d'A-7 sont donc inopérantes **telles que livrées**.

**Décision : cuire les étiquettes de z12 comme prévu (jeu de z11, corps ×2), et ne toucher ni à A-7, ni
à F-11, ni à `carte.js`.**

**Raison.** Les 195 tuiles z12 sont déjà cuites aujourd'hui — #71 ne crée pas ce coût, il en hérite. Le
surcoût d'encre est de l'ordre de 10 à 20 Ko sur une pyramide **sans plafond**. À l'inverse, livrer un
z12 **sans** étiquettes créerait un défaut latent pour le jour où `detectRetina` sera activé (couture
**C-13**), et rendrait z12 incohérent avec z11 sans contrepartie. La question « faut-il garder z12 »
est réelle mais **appartient à `#36`/`#43` et à la chaîne carte**, pas à #71 : elle est remontée en
couture **C-14**, pas tranchée ici.

> **A-12 est délibérément sauté.** Ce document en cite déjà un — celui du **contrat #9** — au §11,
> couture C-10. Réutiliser le numéro dans la numérotation propre à #71 rendrait les deux
> indiscernables en revue.

### A-13 — L'emprise est découplée de la géométrie ; la **version publiée** ne l'est pas, et ne peut pas l'être

**Ajouté le 18 août 2026.** C'est le seul point où la passe de preuve a trouvé le gel initial en
défaut — non dans ses décisions, mais dans une propriété qu'il **annonçait sans l'avoir mesurée**.

**L'affirmation en cause**, écrite trois fois : à la tâche 3 de l'issue, au §14 A-14 du contrat #9
(« ne déplace plus la bbox de la pyramide, **ne change plus la version publiée**, et ne re-cuit plus
295 tuiles »), et implicitement au §0 d'ici.

**Ce qui est vrai — prouvé par construction, pas par un essai :**

| Grandeur | Dépend de la géométrie ? | Preuve |
|---|---|---|
| `bbox` de la pyramide | **Non** | `bboxDeclaree()` prend **zéro argument** : aucune géométrie ne peut l'atteindre |
| Grilles z5–z12, nombre de tuiles (**295**) | **Non** | `grilleDeclaree( z )` prend **le seul zoom**, et dérive de `EMPRISE_DECLAREE` par décalage entier |
| Pixels des tuiles, via les contours du référentiel | **Non** | `construire.mjs` l. 1021 passe `contours: null` à la pyramide — **seule l'image statique porte les 25 contours** |

Une preuve par **signature de fonction** est plus forte qu'un essai : elle ne dit pas « ça n'a pas
changé cette fois », elle dit « ça **ne peut pas** changer ».

**Ce qui est faux.** Le plafond de densité des toponymes est
`round( densite_par_mpx × airePlacementMpx( emprise, z ) )` — `construire.mjs` l. 987 — où `emprise`
vaut `lireEmprise( CHEMINS.referentiel )`, l. 1236 : **la bbox du référentiel, donc une grandeur
dérivée de la géométrie**. Le §3 rend ce choix **normatif**, et pour une bonne raison : la densité doit
refléter la **région utile**, jamais la toile ni l'emprise déclarée — s'en écarter livrerait 2,5 fois
trop d'étiquettes.

Or **le plafond mord** : la recette rapporte **4/4, 16/16, 63/63**. Il n'y a aucun jeu. Toute variation
du plafond change donc le jeu d'étiquettes, donc les tuiles, donc `sha256( manifeste )`, donc la
**version publiée**, qui en est la troncature.

**Sensibilité mesurée.** Une variation d'aire de la bbox du référentiel de **+0,71 %** fait passer le
plafond z11 de 63 à 64 ; **−0,87 %** le fait tomber à 62. z10 bascule à +4,8 % / −1,6 %. **Ce n'est pas
théorique** : `ded0f2f`, le retrait des îlots de moins de 25 ha, a précisément déplacé cette bbox.

**Décision : garder le couplage, corriger la phrase — pas le code.** Quatre raisons.

1. **La formulation était trop large ; la propriété visée, elle, est atteinte.** Ce que #43 et #36
   réclamaient comme socle, c'est un **repère fixe** — une bbox et une grille qui ne bougent pas sous
   eux. Ils l'ont, entièrement.
2. **Geler aussi l'aire de placement serait un remède pire que le mal.** La densité refléterait une
   région utile **périmée**, divergeant silencieusement du référentiel à chaque retouche — très
   exactement le mode de défaillance muet que ce module refuse partout ailleurs.
3. **Le coût immédiat est disproportionné** : rebuild complet, nouvelle `version`, 295 tuiles et un PNG
   à re-cuire, seuils de luma à re-mesurer, nouveau regard dans Chrome — pour un bénéfice spéculatif,
   en fin de chaîne.
4. **#36 et #43 réécrivent la logique d'emprise.** Y introduire maintenant une seconde constante
   déclarée serait du travail à défaire.

**Ce que le couplage coûte réellement, dit sans adoucissement** : une retouche du référentiel qui reste
dans l'emprise **peut encore** invalider la pyramide entière. Elle ne le fait plus *par déplacement de
la grille* — ce que #71 a supprimé — mais *par franchissement d'un seuil d'arrondi de densité*. Mode de
défaillance **bien plus rare et bien moins ample**, et désormais **nommé**.

**Remonté en couture C-17**, à l'intention de #36/#43, qui possèdent la logique d'emprise.

---

## 10. `OUVERT` — à ne jamais combler par déduction

> **`OUVERT` — la taille d'une étiquette de carte n'est prescrite nulle part.** `MASTER.md` est muet :
> le §5.1 s'arrête à `--fs-100` = 13 px à l'écran, et la plus petite valeur d'impression du §13 est
> **10,5 pt** (= 31,9 px cuits). Le plancher de 8 pt retenu ici est une **convention cartographique**,
> légitime mais **non écrite dans le document**. C'est un **trou du design system, pas une divergence du
> code** — donc §18, jamais §17.
> **Traitement retenu** : `corps_statique_px = 28` (≈ 8,57 pt, hauteur d'x 13,9 px), plancher
> `corps_min_statique_px = 25` dérivé de 218,5 ppp. **À confirmer par `lead-design-cms`** — couture
> **C-12**. Conséquence à dire sans détour : **si `lead-design-cms` aligne l'étiquette de carte sur le
> corps du §13 (10,5 pt ⇒ 32 px cuits), les toponymes ne sont probablement pas finançables dans l'image
> statique à 1600 px**, ni en encre ni en octets, et ils doivent en sortir. C'est une issue légitime de
> la condition ferme du propriétaire.

> **`OUVERT` — le plancher d'impression n'a aujourd'hui aucun chemin d'existence.** `print.css`
> l. 98-103 (**gelé**) masque `.bande--carte` **en entier** : l'image statique **ne s'imprime pas, et ne
> s'est jamais imprimée**. Fonder la taille cuite sur une lisibilité papier que rien ne produit, c'est
> fonder une décision sur une **intention**. Ce n'est pas rédhibitoire — le §13 est normatif et la
> couture **C-1** est censée se refermer — **mais cela est écrit ici plutôt que supposé**. Si C-1 ne se
> referme jamais, la justification retenue au §5 s'évapore et la question du §10 se rouvre.

> **`OUVERT` — la mention de la source de l'extrait.** Inchangé, hérité du §9 du contrat #9. **#71 n'y
> touche pas.**

---

## 11. Coutures hors empreinte — signalées, non exécutées

| # | Couture | Preuve | Porteur proposé |
|---|---|---|---|
| **C-1** *(rappel, aggravée)* | `print.css` l. 98-103 masque `.bande--carte` en entier ⇒ image **et** attribution ne s'impriment jamais. #71 fonde la taille des toponymes sur un plancher d'impression **qui n'existe pas** : la couture passe de « perte de confort » à **prémisse manquante d'une décision de #71** | `print.css` l. 98-103 ; `MASTER.md` §13 | **Décision d'orchestration** — voie (a) du contrat #9 §10 |
| **C-2** *(rappel)* | Cible tactile ≥ 44 px sur `.carte-secours__lien` | contrat #9 §10 | `dev-ux-cms` |
| **C-10** | `recette-rendu.mjs` l. 1648 imprime « l'avenant du contrat #9 §13 fixe 1,125 ». Le rapport devient **1,0714**. C'est un `note()`, pas un `assert()` : **aucun test ne rougit, mais la preuve de recette imprimera une ligne fausse** | `recette-rendu.mjs` l. 1648 | `test-integration-cms`. **A-12 est déjà supersédé par moi au §14 du contrat #9** |
| **C-11** | `carte.js` l. 183 justifie `zoomSnap: 0.25` par « le fond est monochrome et **SANS TOPONYME** : l'échelle fractionnaire n'y floute aucun texte ». **#71 rend cette phrase fausse.** Le §17 divergence 22 de `MASTER.md` repose sur le même argument | `carte.js` l. 181-187 ; `MASTER.md` §17 | chaîne carte + `lead-design-cms` |
| **C-12** | **`MASTER.md` ne fixe aucune taille d'étiquette de carte** — voir le §10. **Bloquant pour figer `corps_statique_px`** | `MASTER.md` §5.1, §13 | `lead-design-cms`, §18 |
| **C-13** | `carte.js` **ne pose pas `detectRetina`**. Sur DPR ≥ 2 et à 200 % de zoom, les tuiles sont suréchantillonnées ×2 : invisible sur des traits, **visible comme un défaut sur du texte** | `carte.js` l. 283-287 | chaîne carte ; toute activation impose de revérifier **F-11** |
| **C-14** | **z12 est très probablement inatteignable** — `round(zoom) ≤ 11` et pas de `detectRetina`. Les deux justifications d'A-7 sont inopérantes telles que livrées. #71 cuit z12 quand même (**A-11**), mais la question « faut-il garder z12 » reste entière | contrat #9 A-7 ; `carte.js` l. 198-201, 283-287 | **Décision d'orchestration**, avec #36/#43 |
| **C-15** *(partiellement close)* | Le poids de `carte-statique.png` était écrit **à quatre endroits avec quatre valeurs**. **Réglé dans mon empreinte** le 18 août 2026 : README §7 et ce contrat portent désormais la mesure datée **109 050 o** et renvoient à `reference.json`. **Restent hors de mon empreinte, non corrigés** : `docs/recette/preuves-a11y-et-perf.md` l. 138 (*138 964 o*) et le **corps de l'issue #71 elle-même**, qui porte encore « 120 768 o utilisés / marge 32 832 o » — chiffre du 17 août, antérieur à la régénération finale, et **qui a déjà circulé comme s'il était courant** | fichiers cités | `docs`/`infra` ; le corps de l'issue au **lead orchestrateur**, à la clôture |
| **C-16** | La couche `toponymes` a été récupérée depuis un **miroir Overpass différent** des quatre autres — `overpass.kumi.systems` contre `overpass-api.de` (`build/source/manifeste.json`, `points_acces`). **Aucune violation** : la récupération est un acte de build hors ligne, jamais une requête du navigateur (contrainte #2 intacte). Mais c'est un **fait de provenance non déclaré** dans le contrat, et deux miroirs peuvent servir des instantanés OSM différents | `build/source/manifeste.json` | `infra` — à déclarer ou à unifier lors du prochain `recuperer` |
| **C-18** | **CLS de 0,10686 à 1280 × 900**, mesuré en recette. Cause : le `<svg>` du pane des massifs de Leaflet passe de **239 à 481 px** après l'initialisation de la carte. **Ni `carte-secours__image` ni #71 n'y contribuent** — la boîte de l'image est réservée par `width`/`height` + `block-size:auto`. Enregistré ici parce que le §2 de ce contrat affirmait « le CLS reste nul » sans portée, ce qui est **corrigé** | recette de rendu ; `carte.js` | **chaîne carte** |
| **C-17** | **Le plafond de densité des toponymes reste couplé à la géométrie du référentiel** — voir **A-13**. L'emprise et la grille sont découplées ; la version publiée ne l'est pas. Une variation d'aire de la bbox du référentiel de **0,71 %** suffit à changer le compte z11, donc les tuiles, donc la version | `construire.mjs` l. 987 et l. 1236 ; §3 de ce contrat | **#36 / #43**, qui réécrivent la logique d'emprise |

**Point de licence, réglé pour ne pas être rouvert** : extraire des contours de glyphes d'un `.woff2`
sous OFL 1.1 pour les émettre en `<path>` puis les rasteriser ne constitue **ni une redistribution ni
une modification du *Font Software*** au sens de l'OFL — le PNG livré n'est pas une police, et le SVG
intermédiaire n'est jamais servi. Les deux fichiers de licence restent en place et **ne sont pas
touchés**.

---

## 12. Ce que la revue doit regarder en premier

1. **Le contrôle de débordement rougit-il vraiment ?** Falsifier un sommet du référentiel hors de
   l'emprise déclarée **doit** faire sortir `construire` en code ≠ 0. *Un contrôle qu'on n'a pas vu
   rougir n'est pas un contrôle* — et c'est très exactement le défaut que #71 corrige : l'ancien test
   était une **tautologie** qui ne pouvait pas échouer.

   > **EXÉCUTÉ le 18 août 2026, dans les deux moteurs. Le fait, plutôt que le raisonnement.**
   >
   > **(a) Dans `construire`** — `EMPRISE_DECLAREE.x0` porté de 2100 à 2105 : sortie **code 1**, avant
   > toute rasterisation et sans qu'aucun artefact soit écrit :
   > `Massif « alpilles » : un sommet sort de l'emprise déclarée par le bord ouest — valeur 5.0093,`
   > `borne 5.009765625, débordement 0.000466°.`
   >
   > **(b) Dans `verifier`, par la falsification que ce point prescrit** — un sommet d'`alpilles`
   > déplacé de `[5.0543, 43.7691]` à `[5.95, 43.7691]` dans
   > `data/massifs-13.geometrie.json`, au-delà du bord est (5,888671875) : sortie **code 1**, et
   > **I-71.4 rougit SEUL** — `alpilles (5.95, 43.7691)`.
   >
   > **Correction d'une lecture de revue** : il a été conclu qu'I-71.4 « ne peut jamais rougir seul
   > dans `verifier` », la contenance de grille étant strictement plus précoce. **C'est faux, et
   > l'erreur vient de la perturbation choisie.** Déplacer `EMPRISE_DECLAREE` déplace le *référent
   > commun* à tous les contrôles d'emprise, si bien que les plus précoces tombent d'abord. Déplacer un
   > **sommet** ne touche que le balayage : la contenance de grille est restée **verte**
   > (`x 2100..2114 dans 2100..2114, y 1490..1502 dans 1490..1503`), parce qu'elle lit la bbox stockée
   > dans `massifs-13.php`, **jamais les sommets**. Les deux contrôles portent donc sur deux entrées
   > différentes, et **I-71.4 a une valeur propre dans `verifier` aussi**.
   >
   > Géométrie restaurée et vérifiée au sha256 (`97f48258…`) ; aucun artefact touché.
2. **`grep` de `<text`, `font-family`, `getVariation`, `localeCompare` sur `build/**`** → doit rendre
   **zéro** (I-71.8, I-71.3, §7 interdit 7).
3. **`loadSystemFonts: false` est-il réellement passé à `Resvg` ?** Une ligne, et sans elle la
   non-substitution est incidente au lieu d'être structurelle.

   > **DÉFAUT TROUVÉ ET CORRIGÉ le 18 août 2026, sur signalement de revue.** Le contrôle testait la
   > source **brute** de `construire.mjs`. Or la chaîne y figure **3 fois en commentaire**
   > (`construire.mjs` l. 468 et 552, `etiquettes.mjs` l. 15) contre **1 fois en code** (l. 559) :
   > supprimer la ligne réelle en laissant la documentation l'aurait laissé **vert**. Le contrôle
   > censé fermer I-71.8 était donc lui-même de la famille qu'il dénonce.
   >
   > **Corrigé** : la source passe par `sansCommentaires()` avant le test, comme le contrôle voisin
   > le faisait déjà. **Falsification exécutée** — ligne 559 amputée de
   > `font: { loadSystemFonts: false }`, les deux commentaires de `construire.mjs` laissés en place :
   > sortie **code 1**, `toponymes : loadSystemFonts: false passé à Resvg` **rouge**. Fichier restauré,
   > recette **CONFORME — 146 contrôles**.
4. **C-g passe-t-il ?** Sans lui, la ligne « 4,5:1 » de ce contrat est **non étayée**. C'est le seul
   contrôle qui prouve qu'un cœur d'encre existe.
5. **`airePlacementMpx( z )` porte-t-il bien sur la bbox du référentiel** et non sur la toile ? L'erreur
   livre **2,5× trop d'étiquettes** et ne se voit qu'à l'œil.
6. **La monotonie est-elle produite par amorçage forcé**, et non simplement constatée ? Un nom qui
   disparaît en zoomant est une carte qui semble perdre de l'information.
7. **Le §7 du README ne contient-il plus aucun octet absolu périmé**, et renvoie-t-il bien à
   `reference.json` ?
8. **L'image statique contient-elle toujours zéro pixel vert ou rouge officiel** (I-9.3), halo compris ?
   À vérifier sur le binaire, pas sur l'intention.

---

## 13. Contraste AA des toponymes, aplat par aplat — la ligne de DoD, chiffrée

**Mesuré le 18 août 2026**, WCAG 2.x, luminance relative sRGB linéarisée, sur les sept jetons de la
palette fermée relus dans `tokens.css`. Encre des toponymes : `--c-carte-encre` `#4A4E48`.

| Aplat | Valeur | Encre sur cet aplat | AA 4,5:1 |
|---|---|---|---|
| `--c-carte-fond` | `#E6E7E1` | **6,82:1** | ✅ |
| `--c-carte-terre` | `#DEDFD9` | **6,33:1** | ✅ |
| `--c-carte-vegetation` | `#D6DBD3` | **6,03:1** | ✅ |
| `--c-carte-eau` | `#CBD5D8` | **5,68:1** | ✅ |
| `--c-carte-trait` | `#B4B7AC` | **4,17:1** | ❌ |
| `--c-charbon` | `#1A1C19` | **2,02:1** | ❌ |
| *(rappel, hors fond de carte)* vert officiel | `#22B14C` | **3,02:1** | ❌ |
| *(rappel, hors fond de carte)* rouge officiel | `#E63A3C` | **2,03:1** | ❌ |

Ces huit valeurs **reproduisent exactement** celles déjà consignées à l'A-16 du §14 du contrat #9 et à
la règle 8 du §4.1.d de `MASTER.md` — recalculées ici indépendamment, elles concordent au centième.

### Pourquoi les quatre échecs ne sont pas des défauts d'accessibilité

**Aucun d'eux n'est jamais l'arrière-plan d'un toponyme.** Ce n'est pas une atténuation, c'est une
propriété **structurelle**, tenue par trois mécanismes distincts :

1. **Le halo, pour les deux couleurs de trait.** `svgEtiquettes()` peint **tous les halos d'abord**, en
   trait de largeur `2 × halo_px` **centré sur le contour du glyphe**, puis **tous les remplissages
   par-dessus**. Chaque glyphe est donc **intégralement ceint** de `--c-carte-fond` sur `halo_px` vers
   l'extérieur — 1,5 px dans la pyramide, 2 px sur la statique. L'arrière-plan effectif de l'encre est
   **toujours** `--c-carte-fond`, soit **6,82:1**. `--c-carte-trait` et `--c-charbon` sont des couleurs
   de **trait**, jamais d'aplat : le problème est un problème de **recouvrement**, et il est réglé par
   le halo, jamais par un jeton nouveau.
2. **Le dégagement de 6 px, pour `--c-charbon` sur la statique.** **C-e** interdit qu'une boîte
   approche à moins de 6 px d'un pixel `--c-charbon` — les 25 contours de massifs. Contrôlé sur le
   **PNG décodé** : **0 pixel en contact** sur les six étiquettes livrées.
3. **L'ordre des couches, pour les deux aplats officiels.** Règle 8 du §4.1.d de `MASTER.md` : les
   étiquettes sont **cuites dans la tuile** (`tilePane`, 200) et les aplats de statut peints **au-dessus
   en SVG** (400). Un toponyme intérieur à un massif est **occulté, et c'est voulu**. Sur la statique,
   **aucun aplat n'est jamais peint** (I-9.3), et **A-7** interdit de surcroît qu'une étiquette
   intersecte l'intérieur d'un polygone de massif — contrôlé, 6 étiquettes contre 25 polygones.

### La réserve, écrite plutôt que tue

La frange d'anticrénelage d'un glyphe est peinte en `--c-carte-trait`, à **4,17:1** — et **ce n'est pas
le texte**. Le 6,82:1 repose sur le **cœur** du glyphe. Cette propriété n'est **pas espérée mais
assertée**, par **C-g** : sur des tuiles **décodées**, `#encre / (#encre + #trait) ≥ 0,35`. Mesuré sur
28 boîtes, le rapport va de **47 % à 78 %**. C'est la seconde raison de `corps_px = 19`.

**Sans C-g, la ligne « 4,5:1 » de ce contrat serait une intention.**

---

## 14. Ce que la passe du 18 août 2026 a changé

Passe de **finition et de preuve**, après `a1e2e3b`. **Aucune décision de design n'est repensée** ; le
§12 de `MASTER.md` n'est pas ouvert ; aucun jeton n'est déclaré, renommé ni modifié.

1. **Trois écarts contrat ↔ code réconciliés**, tous en faveur du code, dont la justification était
   mesurée et écrite mais n'avait pas été reportée ici : `zoom_percu_statique` 9 → **10** (§3, §4, C-a,
   **A-8**), les deux seuils de luma passés de `<mesuré puis gelé>` à **89** et **159** (§4), et
   `etiquettes_statique_max` requalifié de garde en **moteur** (§4).
2. **Les octets projetés cèdent la place aux octets mesurés** (§8) : **109 050 o**, marge **44 550 o**,
   plafond tenu à **71,0 %**. La note « 1 palier » change de nature — ce n'est plus le plafond d'octets
   qui tient `PALIERS = 0`, c'est la fermeture de la palette.
3. **A-13, nouveau** : le couplage résiduel entre la géométrie et la version publiée est établi,
   quantifié et assumé ; la phrase trop large est corrigée ici et au §14 du contrat #9.
4. **§13, nouveau** : le contraste AA est chiffré **aplat par aplat**, avec le mécanisme qui rend chaque
   échec inatteignable.
5. **C-15 partiellement close ; C-16 et C-17 ouvertes.**

**Ce que cette passe ne change pas** : `EMPRISE_DECLAREE`, les comptes de tuiles (295), la règle de
sélection du §3, les invariants I-71.1 à I-71.14, les interdits du §7, et la surface PHP —
`SCHEMA_CONNU` reste `1`, `etats.php` et `metadonnees.php` ne changent pas d'une ligne.

---

## 15. Passe corrective du 18 août 2026, sur revue de lot

Revue rendue **OK avec réserves, aucun CRITICAL, aucun HIGH**. Quatre points MEDIUM, tous de la même
famille — **une prose gelée ou un contrôle qui affirme plus qu'il ne prouve**, c'est-à-dire très
exactement ce que #71 existait pour fermer. Aucun n'a rouvert une décision.

1. **`verifier.mjs` — le contrôle `loadSystemFonts` ne pouvait pas rougir.** Corrigé par
   `sansCommentaires()`, **falsification exécutée et vue rouge**. Voir §12 point 3. C'était le point le
   plus grave : le contrôle qui garde I-71.8 était lui-même une tautologie.
2. **§2 — « le CLS reste nul »**, écrit sans portée dans un document gelé. Restreint à la boîte de
   `carte-secours__image`, qui est ce qui avait réellement été vérifié. Le CLS de page mesuré,
   **0,10686**, vient du pane Leaflet et est hors empreinte — couture **C-18**.
3. **`verifier.mjs` — assertion auto-référentielle sur z5–z8.** `ZOOMS_SANS_ETIQUETTE` dérivait de
   `zoom_min_etiquettes` : abaisser la constante aurait **renommé** le contrôle au lieu de le faire
   rougir. **Choix retenu : porter les entiers normatifs en clair**, le §3 gelant « z5–z8 0 » comme une
   clause et non comme une conséquence du réglage. Un contrôle sur `[ 5, 6, 7, 8 ]` littéraux, plus un
   second vérifiant que le réglage **s'accorde** au seuil normatif. Falsification exécutée à 8.
4. **§12 point 1 — la falsification prescrite n'avait pas été exécutée.** Elle l'est. Elle a en outre
   **infirmé une lecture de revue** : I-71.4 **rougit seul** dans `verifier` dès qu'on falsifie un
   **sommet**, la contenance de grille restant verte parce qu'elle lit une **autre entrée**. Voir §12
   point 1.

**Recette : 145 → 146 contrôles** (un contrôle dérivé remplacé par deux, dont un normatif).
**Aucun artefact régénéré** : pyramide 295 tuiles sous `fff4ef0f`, statique 109 050 o, inchangées.
