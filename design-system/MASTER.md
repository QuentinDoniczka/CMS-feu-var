# MASSIFS — Design System

**Version 2.0** · **Date** 11 août 2026 · **Auteur** `lead-design-cms`
**Statut** source de vérité visuelle. Tout travail d'intégration (`dev-ux-cms`, `dev-front-cms`) et toute
relecture (`review-cms`) s'y réfèrent. Livrable §11 du brief (« plan de design »).

> Règle de lecture : ce document décrit des **décisions**, pas des suggestions. Une valeur qui n'est pas
> ici n'existe pas dans le CSS. Une divergence constatée en revue est un défaut, pas une variante.
> Les blocs marqués **`OUVERT`** sont des trous de connaissance assumés : ils bloquent la mise en
> production de ce qu'ils décrivent, jamais l'intégration du reste. On ne les remplit pas par déduction (§4.2 du brief).

## Journal de révision — l'historique est conservé, jamais réécrit en silence

| Version | Date | Ce qui change | Déclencheur |
|---|---|---|---|
| 1.0 | 11 août 2026 | Première édition. Légende officielle **inconnue** : 5 niveaux gradués **substituts**, marqués `À CONFIRMER`, accompagnés de 8 questions bloquantes. | Bootstrap |
| **2.0** | 11 août 2026 | **La légende officielle est établie.** Les 5 niveaux substituts sont **supprimés** et remplacés par les **2 états d'accès réels** + la **dimension ZAPEF** + 3 états hors niveau. Sections refaites : §2.1, §4.1, §4.2, §7.1, §8.1, §8.2, §8.5 (nouveau), §9.1, §10 (preuve d'accessibilité, refaite intégralement), §11.2, §12, §13, §14 (passe 2 bis), §15 (D-11 à D-19), §16. Les 8 questions du §4.1 v1.0 sont **répondues sauf deux**, conservées et re-marquées `OUVERT`. | `docs/decisions/source-prefecture.md` §4 (chaîne #1) et `docs/contracts/issue-3.md` révision 2 |

**Ce que la v1.0 avait raison de faire, et qui est conservé sans changement** : le pari du fond de carte
monochrome (§1), la signature « le repère » (§3), les deux familles typographiques et le budget de 2
fichiers (§5), l'échelle d'espacement et le plafond de rayon à 2 px (§6), le mouvement minimal (§9.4 et
§9.5), la voix de micro-rédaction (§11). **La révision porte sur la sémantique des statuts, pas sur le
langage visuel.** C'est la preuve que le système était correctement paramétré : le passage de 5 crans
inventés à 2 états réels n'a coûté aucune refonte de forme.

---

## 1. Intention de design — en cinq lignes

1. Le site ressemble à un **panneau de départ de sentier repeint chaque soir** : peinture mate sur support
   minéral, angles vifs, aucune matière, aucune profondeur simulée.
2. La carte est **le héros absolu** : elle est le seul endroit du site où de la couleur saturée apparaît,
   parce que les seules couleurs saturées autorisées sont les **deux** de la légende préfectorale —
   un vert, un rouge, rien entre les deux.
3. Tout le reste — chrome, texte, portail — est **calcaire et bleu mistral** : deux familles de tons froids,
   minérales, qui ne peuvent jamais être confondues avec un état d'accès.
4. La hiérarchie ne vient ni de l'ombre ni du contour arrondi : elle vient de **l'échelle, de la casse et
   d'un unique repère peint**, la signature du site (§3).
5. Ce que ça doit faire ressentir : *une information officielle relayée par quelqu'un de sérieux* — sobriété
   de service public, mais avec la brutalité graphique d'une signalétique de terrain, pas d'un intranet.

**Le pari central, celui qui tient tout** : le fond de carte auto-hébergé est **monochrome calcaire**.
Aucune route bleue, aucun bois vert, aucun bâti rose. Résultat : les massifs colorés sont les seules taches
de couleur de l'écran. C'est à la fois le geste le plus spectaculaire (une capture d'écran illisible chez
les concurrents devient évidente ici) et le plus fonctionnel (le contraste des statuts n'est plus pollué).
Tout le système en découle.

**Ce que la légende binaire change, et c'est un gain** : avec **deux** aplats seulement, la carte devient
lisible **à quatre mètres**. Ce n'est plus une carte à déchiffrer, c'est une réponse. La complexité
libérée par la disparition de 3 crans inventés est réinvestie **dans la lisibilité de loin** — aplats
opaques, liseré épais, hachure grossière, un chiffre géant, une frise de 27 marques (§8.2) — et
**nulle part ailleurs**. Aucun ornement n'a été ajouté par cette révision.

---

## 2. Ancrage dans le sujet — d'où viennent les tons

| Anchor | Ce qu'on en tire | Ce qu'on refuse d'en tirer |
|---|---|---|
| **Calcaire** (Calanques, Sainte-Victoire) | Les surfaces : un blanc-gris **froid, légèrement vert**, pas un crème chaud | Le crème beige « papier ancien » (tell IA §7) |
| **Mistral** (le ciel lavé après le vent) | Le chrome : bleu profond, froid, un peu grisé — la seule couleur d'interface | Le bleu « corporate SaaS » saturé et lumineux |
| **Pin d'Alep** | Le vert-gris rabattu du fond de carte végétal, à très faible chroma | Un vert d'interface — il entrerait en collision avec « Accès au massif autorisé » |
| **Garrigue** | Le gris-vert des filets, des textes tertiaires, des courbes | Un olive décoratif |
| **Charbon** (le bois brûlé) | L'encre — **et le liseré qui porte à lui seul la conformité AA des statuts** (§10.2). Un noir tiré vers le vert, jamais un `#000` | Le noir pur + accent acide (tell IA §7) |
| **Balisage peint** (blaze GR/PR) | **La signature** (§3) et l'idée de la marque repeinte par-dessus l'ancienne | Le rouge/blanc littéral du GR : le rouge est celui de la légende |
| **Panneaux DFCI** | La discipline typographique : capitales condensées, sérigraphie, rayon nul | Le pastiche de panneau (bordure double, coin coupé, « effet plaque ») |
| **Barrière DFCI** | Le vocabulaire de **hachures** obligatoire pour l'encodage non chromatique (§10.3), et le **jalon planté** des ZAPEF (§8.1) | La hachure comme décor |

**Terracotta et ocre sont bannis du système**, alors qu'ils seraient le réflexe « Provence ». Deux raisons :
c'est le tell IA nommé au §7 du brief, et surtout la terre cuite (teinte ≈ 18°) tombe **dans la bande
réservée au rouge officiel** — un aplat décoratif terracotta à côté d'un massif interdit créerait une
ambiguïté de sens. La contrainte fonctionnelle et la contrainte esthétique disent la même chose.

### 2.1 Règle de non-collision chromatique — **re-dérivée des deux teintes réelles**

La v1.0 réservait la bande 15°–160°, dérivée d'une échelle jaune → orange → rouge qui **n'existe pas dans
les Bouches-du-Rhône**. Mesures des deux teintes officielles réelles :

| Teinte officielle | Hex | Teinte (H) | Saturation (S) | Luminosité (L) |
|---|---|---|---|---|
| Accès au massif autorisé | `#22B14C` | **138°** | 68 % | 41 % |
| Accès au massif interdit | `#E63A3C` | **359°** | 77 % | 56 % |

> **Règle re-dérivée.** Trois bandes de teinte sont interdites à la palette du site **au-delà de 12 % de
> saturation** :
> - **95°–175°** — *réservée* : c'est la famille du vert officiel `#22B14C` ;
> - **330°–25°** (par 0°) — *réservée* : c'est la famille du rouge officiel `#E63A3C` ;
> - **26°–94°** — *interdite par implication* : jaune, ambre, or, orange clair.
>
> **La palette du site vit donc dans 176°–329°** (cyans, bleus, violets) **ou sous 12 % de saturation.**

**Pourquoi la troisième bande, qui n'appartient à personne, est quand même interdite.** Elle ne l'est pas
au titre de la légende : elle l'est parce qu'un jaune saturé posé **entre** un vert et un rouge est lu
universellement comme un **cran intermédiaire**. Or le dispositif du 13 n'en comporte aucun : il n'y a
qu'autorisé et interdit. Un ambre décoratif inventerait visuellement un troisième état — c'est-à-dire
exactement l'invention que le §4.2 du brief interdit, mais commise par la couleur au lieu du texte.
La v1.0 interdisait cette bande par confusion avec la légende ; la v2.0 l'interdit pour une raison plus
forte et plus juste.

Conséquences directes, à respecter sans exception :
- pas de bouton vert « valider », pas de message d'erreur rouge, pas d'alerte orange ni ambre ;
- le succès, l'erreur et la péremption sont portés par le **chrome mistral + un libellé explicite + une
  hachure**, jamais par une couleur sémantique (§9.2) ;
- l'indicateur « Météo des forêts » (§4.3 du brief) n'utilise **aucune couleur** : il utilise une échelle de
  carrés en charbon (§8.6). C'est la traduction visuelle de l'exigence « jamais fusionné avec le statut »
  — et elle devient **plus critique** avec deux états qu'avec cinq : une échelle météo colorée à côté
  d'une carte binaire serait immédiatement lue comme la vraie granularité du risque.

**Audit de conformité de la palette à sa propre règle** (mesuré, pas supposé) :

| Token | Hex | H | S | Bande | Verdict |
|---|---|---|---|---|---|
| `--c-calcaire` | `#EDEEEC` | 90° | 5,6 % | implicite | conforme (< 12 %) |
| `--c-calcaire-ombre` | `#DEDFD9` | 70° | 8,6 % | implicite | conforme (< 12 %) |
| `--c-poussiere` | `#C3C5BC` | 73° | 7,2 % | implicite | conforme (< 12 %) |
| `--c-trace` | `#9EA197` | 78° | 5,1 % | implicite | conforme (< 12 %) |
| `--c-garrigue` | `#5F6B5A` | 102° | **8,6 %** | **réservée vert** | conforme (< 12 %) — le seul ton du site dans la bande verte, volontairement maintenu sous le seuil |
| `--c-charbon-doux` | `#4A4E48` | 100° | 4,0 % | réservée vert | conforme (< 12 %) |
| `--c-charbon` | `#1A1C19` | 100° | 5,7 % | réservée vert | conforme (< 12 %) |
| `--c-mistral-nuit` | `#0B2B3C` | 201° | 69 % | libre | conforme |
| `--c-mistral` | `#17567A` | 202° | 68 % | libre | conforme |
| `--c-mistral-clair` | `#8FC3DD` | 200° | 51 % | libre | conforme |
| `--c-carte-vegetation` | `#D6DBD3` | 97° | **10,0 %** | **réservée vert** | conforme (< 12 %) — c'est la seule surface verte de la carte, et elle doit le rester |
| `--c-carte-eau` | `#CBD5D8` | 194° | 14,3 % | libre | conforme (hors bande, seuil non applicable) |

**Conséquence retirée de cet audit** : le token `--c-pin-alep` (`#22392C`, H 146°, **S 25 %**) est
**supprimé de `tokens.css`**. Il violait la règle s'il était peint tel quel. Il reste un **ingrédient de
mélange documenté** (§4.2), dont le seul produit — `--c-carte-vegetation` à 10 % de saturation — est,
lui, un token. On ne laisse pas traîner dans la feuille de style une valeur qu'aucune surface n'a le droit
de porter.

---

## 3. La signature : **le repère**

> **Une phrase** : toute information de statut est précédée d'un *repère* — une barre peinte de 8 px doublée
> d'une trace décalée de 3 px vers la droite et 4 px vers le bas, comme une balise de sentier repeinte
> par-dessus celle de la saison précédente.

C'est le sujet même du site rendu visible : **une marque qu'on repeint tous les soirs**, l'ancienne encore
visible dessous. L'indicateur de fraîcheur n'est plus une mention en petits caractères, c'est la forme
de base du système.

**Inchangé par la révision 2.0.** Le repère ne dépendait d'aucun nombre de niveaux : il prend la couleur de
l'état, quelle que soit la cardinalité de la légende. C'est précisément le test qu'une signature doit passer.

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

`--repere-couleur` est la **première** des deux seules custom properties que les composants ont le droit de
redéfinir localement (la seconde est le groupe `--statut-lisere` / `--statut-*-encre` sous `.sur-sombre`,
§12) : elle prend la couleur officielle de l'état quand le repère précède une information de statut.

### 3.2 Où il apparaît — liste fermée

1. Devant le **chiffre du jour** dans l'ardoise (version `--bloc`, pleine hauteur, à gauche du slab).
2. Devant **chaque `h2`** du site (couleur `--c-mistral-nuit`).
3. Devant **chaque puce de statut** dans la légende et dans la liste du jour (couleur = état officiel).
4. Sur le **bord gauche du panneau massif** (version `--bloc`, couleur = état du massif sélectionné).
5. Sur le **massif sélectionné dans la carte** : contour `--c-calcaire` 4 px + contour `--c-charbon` 4 px
   décalé de (3 px, 4 px), rendu par duplication du tracé dans un pane Leaflet dédié
   `transform: translate(3px, 4px)` sous le pane des tracés.
6. Sur le **bord gauche du bandeau d'alerte** (péremption, source indisponible, hors-saison).
7. Sur le **bord gauche de la barre d'action** du portail (« Publier les statuts »).

**Décision de révision** : les **jalons ZAPEF** (§8.1) n'entrent **pas** dans cette liste et restent à sept
emplacements. Un décalage de 3–4 px sur un marqueur de 18 px détruirait sa silhouette et diluerait la
signature dans un objet trop petit pour la porter. La discipline l'emporte sur la cohérence de façade.

### 3.3 Où il ne doit jamais apparaître

- Dans le corps de texte, dans les listes à puces éditoriales, dans les notes de bas de page.
- Sur les `h3`, `h4` (la hiérarchie basse se fait à la taille et à la casse).
- Sur les boutons, les champs de formulaire, les liens.
- Dans le pied de page, sur les logos, en filet décoratif horizontal.
- **Sur les jalons ZAPEF** et sur les 27 marques de la frise (§8.2) — trop petits, trop nombreux.
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

> **ÉTABLI le 11 août 2026.** Source : `docs/decisions/source-prefecture.md` §4, qui relève la légende
> officielle du 13 de **trois manières concordantes** (le fichier `fr.json` que la préfecture charge et
> applique, la fonction de traduction propre au département 13, et le nombre d'entrées de légende servies
> dans le HTML). Les **5 niveaux gradués substituts de la v1.0 sont supprimés** : ils ne correspondaient à
> aucun dispositif réel. L'échelle à six crans de vigilance qui existe dans le code partagé de la
> plateforme appartient à **d'autres départements** et ne s'exécute jamais sur la page du 13.
>
> **Rien de ce tableau n'est un choix de design. Rien n'y est modifiable, « harmonisable » ou arrondi.**

#### 4.1.a Les deux états d'accès au massif — surfaces (polygones)

| Clé | Libellé officiel *verbatim* | Couleur officielle | Sévérité | Motif obligatoire | Liseré obligatoire |
|---|---|---|---|---|---|
| `autorise` | `Accès au massif autorisé` | **`#22B14C`** | `10` | `aucun` (aplat nu) | `--c-charbon` 2 px |
| `interdit` | `Accès au massif interdit` | **`#E63A3C`** | `20` | `hachure_croisee` | `--c-charbon` 2 px |

Les deux hex sont **relevés au pixel** sur les pastilles de légende publiées par la préfecture
(`couleur_vert.png`, `couleur_rouge.png`). Le rendu de la carte officielle emploie par ailleurs les
couleurs CSS nommées `green` / `red` en `fillOpacity: 0.5` : **nous reproduisons les hex de la légende**,
qui sont la référence publiée, et non l'approximation du rendu.

#### 4.1.b La dimension ZAPEF — points (marqueurs), **indépendante de l'état du massif**

| Clé | Libellé officiel *verbatim* | Couleur | Sévérité | Motif |
|---|---|---|---|---|
| `autorise` | `Accès à la ZAPEF* autorisé` | `#22B14C` | `10` | `aucun` |
| `interdit` | `Accès à la ZAPEF* interdite` | `#E63A3C` | `20` | `barre` |

Note de bas de légende, *verbatim*, apostrophe typographique U+2019 comprise :
`*ZAPEF : Zones d’Accueil du Public en Forêt`

> **Les incohérences de la source sont reproduites telles quelles** : `autorisé` au masculin face à
> `interdite` au féminin ; apostrophe typographique `’` (U+2019) dans la note ZAPEF alors que les autres
> chaînes emploient une apostrophe droite `'` (voir `Niveau d'Accès`). Corriger la préfecture serait
> cesser de reproduire sa légende. Toute « correction orthographique » constatée en revue est un défaut.

**Ce que cela impose au rendu, et c'est structurant :** la carte porte **deux objets de nature différente**
— des **surfaces** (massifs, 2 états) et des **points** (ZAPEF, 2 états) — **qui ne s'accordent pas
toujours**. Un massif peut être `interdit` alors que ses ZAPEF restent `autorisé`. Ce n'est ni une erreur
ni un cas limite : c'est le comportement nominal du dispositif au `level` brut 3. Le design doit rendre
cette divergence **lisible et non contradictoire** (§8.1, jalon planté ≠ pastille de surface).

#### 4.1.c Les deux — en réalité trois — états **hors niveau**

Ce ne sont pas des niveaux, ce sont des **absences d'information**. Ils n'ont ni libellé officiel ni
couleur officielle : ils sont **à nous**, et la §11.3 fixe leurs phrases.

| Clé | Quand | Aplat | Motif | Encre du motif |
|---|---|---|---|---|
| `indisponible` | Aucune donnée pour ce jour **ou** la source a publié `level` 0 (« aucune donnée ») | `--c-calcaire-ombre` | `hachure_descendante` | `--c-charbon-doux` |
| `hors_saison` | Hors du 1er juin – 30 septembre **inclus**, et aucune donnée | `--c-calcaire-ombre` | `aucun` | — |
| `non_encore_publie` | Jour futur demandé, rien de publié (le 404 de la source **est** le signal) | `--c-calcaire-ombre` | `pointille` | `--c-charbon-doux` |

> **`level` 0 n'est jamais « autorisé par défaut ».** La carte officielle peint la ZAPEF en vert dès
> `level >= 0`, donc y compris quand elle n'a aucune donnée. **Nous ne reproduisons pas ce comportement** :
> nous reproduisons la légende, pas les défauts de rendu de la source. À `level` 0, le massif **et** ses
> jalons ZAPEF passent en `indisponible`. C'est l'application directe de la règle de sécurité produit du
> `CLAUDE.md` : ne jamais présenter comme une information ce qui est une absence d'information.

#### 4.1.d Règles inviolables attachées à ce tableau

1. **`fill-opacity: 1`** sur tous les polygones de massif. Aucune transparence : les ratios mesurés au §10
   ne tiennent que sur aplat opaque. C'est aussi ce qui donne à la carte son aspect « formes peintes »,
   et ce qui la rend lisible de loin. La source officielle peint à 50 % ; nous non, et c'est délibéré.
2. **Liseré `--c-charbon` 2 px sur tout polygone de massif et tout jalon ZAPEF, sans exception.**
   Ce liseré n'est **pas décoratif** : il est le seul élément qui atteigne 3:1 (WCAG 1.4.11) sur toutes les
   surfaces, y compris **entre un massif vert et un massif rouge voisins, qui ne contrastent qu'à 1,48:1**
   (§10.2). Sans lui, on ne voit pas où s'arrête un massif autorisé et où commence un massif interdit.
   Un polygone sans liseré est un défaut **bloquant**, pas une variante esthétique.
3. **Aucun texte n'est jamais posé sur un aplat de statut. Nulle part.** Ni sur la carte, ni dans la
   légende, ni dans la liste, ni dans le portail, ni à l'impression. Mesuré : `--c-charbon` sur `#E63A3C`
   plafonne à **4,11:1**, sous le seuil AA de 4,5:1 pour du texte normal ; le blanc pur n'y atteint que
   4,17:1. Les libellés vivent **à côté** de l'aplat, en `--c-charbon` sur `--c-calcaire` (14,74:1).
   Les jetons `--statut-*-encre` sont des **encres de motif**, jamais des encres de texte.
4. **Le motif est obligatoire partout où la couleur apparaît** : carte, légende, liste du jour, panneau,
   frise de l'ardoise, écran gestionnaire, impression. Une pastille sans motif est un défaut bloquant.
5. **Il n'y a que deux états, donc le motif n'est plus une échelle de densité mais une opposition
   binaire** : `autorisé` = aplat nu, `interdit` = hachure croisée. La sévérité (`10` / `20`) est
   **comparable, jamais une identité et jamais un rang** ; elle ne pilote aucune densité graduée. Toute
   trace de « la densité croît avec la sévérité » (règle 5 de la v1.0) est **supprimée** : avec deux
   états, une gradation n'a plus de référent et suggérerait des crans intermédiaires inexistants.
6. **La couleur ne traverse jamais la frontière extension → thème.** L'extension émet des **noms de
   jetons** (`--statut-autorise`, `--statut-interdit`, `--statut-zapef-*`, `--statut-indisponible`…) ;
   `tokens.css` (§12) porte le pigment. Une valeur hexadécimale de statut écrite ailleurs que dans
   `tokens.css` est un défaut bloquant (contrat #3, interdit 6 et 14).
7. **Les jetons de statut sont sémantiques, jamais numérotés.** `--statut-1` … `--statut-5` sont
   **bannis à perpétuité**. Motif : réutiliser un jeton numéroté après un changement de légende
   repeindrait silencieusement des massifs interdits dans la mauvaise couleur — `--statut-2` valait un
   **jaune** en v1.0. Un jeton sémantique manquant ne produit **aucune** couleur : l'échec est bruyant et
   visible à la première intégration. Sur une donnée de sécurité, l'échec bruyant est toujours le bon choix.

#### 4.1.e Ce qui reste `OUVERT` — à ne jamais combler par déduction

Les 8 questions bloquantes de la v1.0 sont désormais répondues, **sauf deux**. État à jour :

| # v1.0 | Question | État |
|---|---|---|
| 1 | Combien de niveaux ? | **RÉPONDU — deux**, plus la dimension ZAPEF |
| 2 | Libellés officiels mot pour mot | **RÉPONDU** — §4.1.a et §4.1.b, verbatim |
| 3 | Codes couleur exacts | **RÉPONDU** — `#22B14C` et `#E63A3C`, relevés au pixel |
| 4 | **Consigne officielle par niveau** | **`OUVERT`** — voir ci-dessous |
| 5 | **Distinction piéton / circulation / stationnement / travaux** | **`OUVERT`** — les travaux relèvent d'un dispositif et d'une carte séparés ; circulation et stationnement sont absents de la source |
| 6 | Libellé « demain non publié » | **RÉSOLU AUTREMENT** — le 404 est le signal ; notre phrase est fixée §11.3, celle de la source n'est pas recopiée |
| 7 | Dates du dispositif | **RÉPONDU** — 1er juin au 30 septembre **inclus** |
| 8 | Autorisation de reproduction et mention de source | **`OUVERT` et bloquant avant mise en production** — aucune mention légale, aucune CGU, aucune licence publiée |

> **`OUVERT` — les consignes.** La légende officielle **ne porte aucune consigne** : ni horaire d'accès,
> ni interdiction de travaux, ni mention de circulation ou de stationnement. L'arrêté préfectoral qui les
> contiendrait est un **PDF numérisé sans couche de texte** : il n'a pas pu être lu, et il ne sera pas
> deviné. Le §5.2 du brief promet pourtant une « consigne » dans le panneau massif.
> **Traitement retenu : l'emplacement existe, il se tait proprement quand il est vide, et il accueillera
> une transcription fournie par le propriétaire sans aucune refonte** (§8.4). C'est le seul traitement
> qui honore à la fois le §5.2 et l'interdiction d'inventer du §4.2.

> **`OUVERT` — les niveaux bruts.** Le flux porte un `level` 0–4 (1–2 → autorisé, 3–4 → interdit ; les
> ZAPEF ne ferment qu'à 4). **Aucun libellé officiel ne distingue 1 de 2, ni 3 de 4.** Il est donc
> **interdit d'en rendre un**, sous quelque forme que ce soit : pas de nuance de teinte, pas de densité de
> hachure, pas de mention « niveau élevé », pas d'infobulle. Le `level` brut est persisté par l'extension
> et **n'atteint jamais l'écran**.

> **`OUVERT` — la géométrie des ZAPEF.** La dimension ZAPEF est **établie** (libellés, états, règle de
> fermeture), mais **aucun contrat ne fournit à ce jour la position des points ZAPEF ni leur rattachement
> à un massif**. Tant que cette donnée n'existe pas, les jalons **ne sont pas rendus sur la carte** : la
> dimension ZAPEF vit alors uniquement dans le panneau massif et dans la liste du jour (§8.1, dégradation).
> Le site n'affiche pas un marqueur dont il ne connaît pas l'emplacement.

### 4.2 Palette du site

Encres et surfaces — **ratios mesurés (WCAG 2.x, sRGB)**. Preuve complète au §10.

| Token | Nom | Valeur | Usage | Contraste |
|---|---|---|---|---|
| `--c-calcaire` | Calcaire | `#EDEEEC` | Surface principale de page | réf. |
| `--c-calcaire-ombre` | Calcaire à l'ombre | `#DEDFD9` | Surfaces secondaires, lignes alternées, terre du fond de carte, **aplat des trois états hors niveau** | 1,15:1 vs calcaire (surfaces uniquement) |
| `--c-poussiere` | Poussière | `#C3C5BC` | Filets 1 px non informatifs, séparateurs | 1,50:1 vs calcaire — **jamais de texte, jamais de bordure porteuse de sens** |
| `--c-trace` | Trace | `#9EA197` | La peinture ancienne : `::before` du repère, ombres décalées | 2,26:1 vs calcaire — **décoratif exclusivement**. Retiré des motifs de statut en v2.0 (1,96:1 sur calcaire-ombre, insuffisant — §10.3) |
| `--c-garrigue` | Garrigue | `#5F6B5A` | Texte tertiaire, bordures de champs, filets de carte | **4,83:1** vs calcaire conforme · 4,19:1 vs calcaire-ombre ÉCHEC (grand texte ≥ 24 px uniquement) |
| `--c-charbon-doux` | Charbon doux | `#4A4E48` | Texte secondaire, méta, **encre des motifs hors niveau** | **7,29:1** vs calcaire conforme · **6,33:1** vs calcaire-ombre conforme |
| `--c-charbon` | Charbon | `#1A1C19` | Texte principal, **liseré des statuts**, encre des motifs de statut | **14,74:1** vs calcaire conforme · **12,79:1** vs calcaire-ombre conforme |
| `--c-mistral-nuit` | Mistral de nuit | `#0B2B3C` | Chrome : ardoise, en-tête, pied, barre d'action, bandeau d'alerte | **12,66:1** vs calcaire conforme · calcaire dessus **12,66:1** conforme |
| `--c-mistral` | Mistral | `#17567A` | Liens, boutons primaires, focus | **6,81:1** vs calcaire conforme · 5,91:1 vs calcaire-ombre conforme |
| `--c-mistral-clair` | Mistral clair | `#8FC3DD` | Texte et liens **sur chrome sombre**, halo de focus | **7,73:1** sur mistral-nuit conforme · 1,64:1 sur calcaire ÉCHEC — **interdit sur fond clair** |

**Ingrédient de mélange, absent de `tokens.css`** : *Pin d'Alep* `#22392C` (H 146°, S 25 %). Il sert à
composer `--c-carte-vegetation` (10 % sur `--c-calcaire-ombre`). Peint tel quel, il violerait la règle
§2.1 — d'où son retrait de la feuille de jetons en v2.0. L'ancre reste, le token part.

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

**Inchangée par la révision 2.0.** Deux familles, deux fichiers, **budget §10 du brief tenu exactement**.

| Rôle | Famille | Licence | Fichier | Poids | Sous-ensemble |
|---|---|---|---|---|---|
| Titrage — *de caractère* | **Big Shoulders Display** | SIL Open Font License 1.1 | `big-shoulders-display-var.woff2` (variable, axe `wght`) | 500 → 800 | latin + latin-ext (accents FR **capitales comprises**) |
| Texte — *de labeur* | **Atkinson Hyperlegible Next** | SIL Open Font License 1.1 | `atkinson-hyperlegible-next-var.woff2` (variable, axe `wght`) | 400 → 700 | latin + latin-ext |

**Total : 2 fichiers `woff2`, auto-hébergés.** Aucun service tiers, aucun CDN, `@font-face` local avec
`font-display: swap` et `size-adjust` calibré pour supprimer le saut de mise en page (§10 du brief :
« pas de sauts perceptibles »).

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
- `OUVERT` — confirmer que le **fichier variable** d'Atkinson Hyperlegible Next est disponible sous OFL
  et auto-hébergeable. Si seuls des statiques existent, **repli documenté : `Public Sans` variable**
  (OFL 1.1, USWDS) — un seul fichier, registre service public, même rôle. Ne jamais dépasser 2 fichiers.
- Confirmer que le sous-ensemble Big Shoulders contient **É È À Ç Ô Û Î** en capitales (les titres sont
  en capitales) ; sinon, corriger le sous-ensemble avant intégration.
- Le sous-ensemble de la police de texte doit contenir **`’` (U+2019)** et **`*`** : la note ZAPEF
  officielle en dépend, et un glyphe manquant afficherait un rectangle dans une chaîne reproduite verbatim.
- Piles de repli système : `--police-titre` → `"Big Shoulders Display", "Arial Narrow", sans-serif` ;
  `--police-texte` → `"Atkinson Hyperlegible Next", system-ui, sans-serif`.

### 5.1 Échelle

Base `1rem = 16px`. Corps à 17 px (l'œil d'Atkinson est large, 17 px donne la mesure juste).
Les niveaux 500 à 800 sont **fluides** (`clamp`) : pas de media query typographique.

| Token | Valeur | Rôle | Famille | Interligne | Approche |
|---|---|---|---|---|---|
| `--fs-100` | `0.8125rem` (13 px) | Attributions, mentions de licence, note ZAPEF | texte | 1,45 | 0 |
| `--fs-200` | `0.9375rem` (15 px) | Méta, fraîcheur, libellés de tableau | texte | 1,5 | 0 |
| `--fs-250` | `0.8125rem` (13 px) | **Étiquette** : capitales, `--ls-etiquette` | titre 700 | 1,2 | 0,08em |
| `--fs-300` | `1.0625rem` (17 px) | **Corps** | texte | 1,6 | 0 |
| `--fs-400` | `1.1875rem` (19 px) | Chapô, **libellé d'état dans le panneau**, consigne | texte | 1,55 | 0 |
| `--fs-500` | `clamp(1.375rem, 1.2rem + 0.9vw, 1.75rem)` | `h3` | titre 600 | 1,15 | 0,01em |
| `--fs-600` | `clamp(1.75rem, 1.4rem + 1.8vw, 2.5rem)` | `h2` | titre 700, capitales | 1,08 | 0,01em |
| `--fs-700` | `clamp(2.25rem, 1.6rem + 3.2vw, 3.75rem)` | `h1` | titre 700, capitales | 1,05 | 0,005em |
| `--fs-800` | `clamp(3.5rem, 2rem + 7.5vw, 8rem)` | **Le chiffre du jour** | titre 800, chiffres tabulaires | 0,92 | −0,01em |

**Règle de hiérarchie** : la famille de titrage n'a **que deux poids en service (700 et 800)** et s'emploie
**toujours en capitales** au-dessus de `--fs-500`. La hiérarchie vient de la taille, pas du poids ni de la
couleur. Interdit : titre en `--c-mistral`, titre en italique, titre souligné, **titre coloré par l'état**.

**Mesure de ligne** : `--mesure: 68ch` sur le corps éditorial, `--mesure-etroite: 46ch` dans le panneau massif.

**Comportement à 360 px** : `--fs-800` vaut 56 px ; le chiffre du jour n'affiche que **le nombre** (« 12 »),
la phrase complète passe en `--fs-400` en dessous. `--fs-700` vaut 36 px, `--fs-600` 28 px. Aucun titre ne
descend jamais sous 28 px : la condensée devient illisible avant de devenir petite. À 200 % de zoom, tous
les `clamp` restent bornés par leur minimum en `rem`, donc le texte grossit bien (pas de piège `vw` pur).

---

## 6. Espacement, rythme, rayons, bordures, élévation

**Inchangé par la révision 2.0.**

### 6.1 Espacement — échelle base 4

| Token | Valeur | Emploi |
|---|---|---|
| `--esp-3xs` | `2px` | Décalage d'état actif, micro-ajustements |
| `--esp-2xs` | `4px` | Écart puce ↔ libellé, gouttière de la frise |
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
| `--r-0` | `0` | **Par défaut, partout** : sections, carte, panneaux, tableaux, boutons, pastilles, jalons |
| `--r-1` | `2px` | Champs de formulaire, boutons — *uniquement pour éviter l'aliasing des coins* |

> **Aucun rayon supérieur à 2 px n'existe dans ce système.** Pas de carte arrondie, pas de pilule, pas
> d'avatar rond, **pas de pastille de statut ronde**. C'est la peinture sur pierre, pas le composant d'un
> kit UI. Un `border-radius: 8px` repéré en revue est un défaut.
>
> **Précision v2.0** : les pastilles de statut passent à `--r-0` (elles étaient à `--r-1` en v1.0). Motif
> mesuré : sur une pastille de 16 px de haut, un rayon de 2 px mange visiblement le liseré dans les
> angles, et c'est **le liseré qui porte la conformité**. On ne rogne pas un élément porteur d'accessibilité
> pour un adoucissement décoratif.

### 6.3 Bordures

| Token | Valeur | Emploi |
|---|---|---|
| `--bord-fin` | `1px solid var(--c-poussiere)` | Séparateurs de lignes de tableau, filets non informatifs |
| `--bord-champ` | `2px solid var(--c-garrigue)` | Champs de formulaire au repos (4,83:1 → limite ≥ 3:1 conforme) |
| `--bord-moyen` | `2px solid var(--c-charbon)` | Boutons secondaires, **liseré des polygones, pastilles et jalons** |
| `--bord-fort` | `4px solid var(--c-charbon)` | Haut et bas de la carte, panneau massif, bandeau de non-officialité, encart de consigne |

### 6.4 Élévation — aucune ombre floue

| Token | Valeur | Emploi |
|---|---|---|
| `--ombre-0` | `none` | **Défaut de tous les éléments** |
| `--ombre-decalee` | `3px 4px 0 var(--c-trace)` | Panneau massif, bloc de légende |
| `--ombre-decalee-sombre` | `3px 4px 0 var(--c-mistral)` | Les mêmes, posés sur chrome sombre |

**`blur-radius` est toujours `0`.** Le décalage `(3px, 4px)` est exactement celui du repère : l'élévation
n'est pas une seconde idée, c'est la signature appliquée à une surface au lieu d'une barre.
**Deux types de composants au maximum** peuvent porter une ombre : le panneau massif et le bloc de légende.
Les boutons n'en portent pas. Les pastilles et les jalons n'en portent pas.

---

## 7. Mise en page

### 7.1 Accueil — la carte est le héros

Composition en **bandes horizontales pleine largeur** (strates calcaires), de haut en bas :

```
┌────────────────────────────────────────────────────────────┐
│ BARRE  mistral-nuit · 48px · nom du site · 4 liens · évitement│
├────────────────────────────────────────────────────────────┤
│ ▌                                                          │
│ ▌ L'ARDOISE   mistral-nuit, pleine largeur, ~34vh          │
│ ▌  ┌────────┐  AUJOURD'HUI, 12 MASSIFS SUR 27              │
│ ▌  │   12   │  SONT D'ACCÈS AUTORISÉ.        (fs-700, caps)│
│ ▌  │ /27    │  Statuts du mardi 11 août 2026, publiés la   │
│ ▌  └────────┘  veille à 17 h par la préfecture             │
│ ▌  ▪▪▪▪▪▨▨▪▪▪▨▨▨▪▪▪▪▨▨▪▪▪▪▨▨   ← LA FRISE, 27 marques      │
│ ▲ le repère, version bloc, pleine hauteur                  │
├────────────────────────────────────────────────────────────┤
│ NON-OFFICIALITÉ  calcaire-ombre · bord-fort en haut · fs-200│
├════════════════════════════════════════════════════════════┤
│                                                            │
│           LA CARTE — plein cadre, bord à bord              │
│           min(72vh, 640px) · fond calcaire monochrome      │
│           2 aplats seulement : la lecture est immédiate    │
│                                                            │
├════════════════════════════════════════════════════════════┤
│ LÉGENDE DE LA CARTE   bande horizontale, 2 + 2 entrées     │
│   ▬ Accès au massif autorisé   ▬▨ Accès au massif interdit │
│   ▮ Accès à la ZAPEF* autorisé ▮ Accès à la ZAPEF* interdite│
│   *ZAPEF : Zones d’Accueil du Public en Forêt   (fs-100)   │
│   + bascule « Afficher les zones parcourues par le feu »   │
├────────────────────────────────────────────────────────────┤
│ ▌ LA LISTE DU JOUR   (h2 + repère) — ancre #liste          │
│   colonnes : Massif · Niveau d'Accès · ZAPEF · Fraîcheur   │
├────────────────────────────────────────────────────────────┤
│ ▌ DANGER MÉTÉO DU JOUR  module distinct, sans couleur      │
├────────────────────────────────────────────────────────────┤
│ ▌ ZONES PARCOURUES PAR LE FEU  (texte + limites EFFIS)     │
├────────────────────────────────────────────────────────────┤
│ PIED  mistral-nuit · attributions · licences · zéro cookie │
└────────────────────────────────────────────────────────────┘
```

**Ce qui fait la capture d'écran** : l'empilement ardoise sombre + frise → carte monochrome où **deux
couleurs seulement** se répondent → bande de légende de quatre entrées. Trois bandes, aucun bruit, un
chiffre énorme. Rien d'autre n'a le droit d'attirer l'œil.

**Points non négociables de cette composition :**
- La carte **touche les deux bords** de la fenêtre à toutes les tailles. Elle n'est jamais dans un conteneur
  centré à coins arrondis. C'est la différence physique entre « une carte sur un site » et « un site qui est
  une carte ».
- Le bandeau de non-officialité (§5.6 du brief) est **entre l'ardoise et la carte**, pas en pied de page :
  il est dans le chemin du regard, mais dans une bande neutre — obligatoire sans être criard.
- **La liste du jour n'est pas un repli.** Elle a son `h2`, son repère, la pleine largeur, la même typographie
  de titrage que la carte, et elle est annoncée par le lien d'évitement « Aller à la liste des statuts ».
  Visuellement, c'est *le second héros*. On doit pouvoir lire le site en ne regardant qu'elle. Son en-tête
  de colonne d'état reprend **verbatim** l'intitulé officiel `Niveau d'Accès` (apostrophe droite).
- Le titre de la bande de légende est **verbatim** `Légende de la carte`.
- La légende compte **quatre entrées et une note**, jamais davantage : deux pour les massifs, deux pour les
  ZAPEF, la note `*ZAPEF : …`. Les états hors niveau **ne figurent pas dans la légende officielle** ; ils
  apparaissent dans une seconde ligne, séparée par un filet `--bord-fin` et introduite par l'étiquette
  « SUR CE SITE » — pour qu'on ne puisse jamais croire que la préfecture publie « information non disponible ».
- Le module météo est **visuellement étranger au reste** : bordure fine, aucune couleur, échelle de carrés.
  L'écart de traitement est la traduction de « deux notions jamais fusionnées » (§4.3 du brief).

**Panneau massif** : à partir de 900 px, colonne de droite `380px` collée (`position: sticky`) à côté de la
carte, avec le repère sur son bord gauche. En dessous de 900 px, feuille du bas (`bottom sheet`) occupant
au maximum 66 % de la hauteur, avec poignée de fermeture 44 px et fermeture par Échap. Jamais une popup
Leaflet par défaut, jamais une infobulle au survol.

**Points de rupture** (mobile-first, en `rem`) :

| Token | Valeur | Ce qui change |
|---|---|---|
| base | 360 px | Une colonne, gouttière `--esp-m`, légende en 2 colonnes, tableau en cartes empilées, frise sur 2 rangs |
| `--bp-s` | `37.5rem` (600 px) | Légende en ligne, tableau en vraies colonnes, gouttière `--esp-l`, frise sur 1 rang |
| `--bp-m` | `56.25rem` (900 px) | Panneau massif à droite de la carte, ardoise en deux colonnes |
| `--bp-l` | `80rem` (1280 px) | Contenu bridé à `--largeur-max: 1200px` ; **la carte reste plein cadre** |

À 360 px : aucun défilement horizontal, cibles ≥ 44 px, aucun élément en `position: fixed` autre que la
feuille du bas et la barre d'action du portail.

### 7.2 Portail gestionnaire

Même système, chrome plus dense — c'est un outil, pas une vitrine, mais il doit être aussi soigné (§6 du brief).

- **En-tête** `--c-mistral-nuit`, 56 px : « MASSIFS · Mise à jour des statuts », date de la session, déconnexion.
- **Écran unique** : un tableau, une ligne par massif. Colonnes : massif · état d'aujourd'hui (lecture seule,
  pastille + libellé) · **`Niveau d'Accès` pour demain** · dernière modification (auteur + heure).
- **Le choix se fait entre deux options, pas cinq** : le groupe radio devient une **paire segmentée**
  `Accès au massif autorisé` / `Accès au massif interdit`, chacune ≥ 44 px de haut, pastille + motif +
  libellé **verbatim** posé à côté de l'aplat (jamais dessus), liseré `--c-charbon` 2 px, état sélectionné
  = liseré 4 px `--c-mistral-nuit` + repère à gauche. Navigation clavier par flèches (rôle `radiogroup`),
  `Tab` passe à la ligne suivante.
- **Conséquence directe de la légende binaire** : deux cibles au lieu de cinq divisent par deux et demi le
  temps de saisie d'une ligne. L'objectif « moins d'une minute pour 27 massifs » du §6 du brief devient
  atteignable **sans raccourci de masse**. Un bouton « tout autoriser / tout interdire » reste néanmoins
  offert au-dessus du tableau, car les journées où les 27 massifs partagent le même état sont le cas
  nominal observé.
- **Barre d'action collée en bas**, `--c-mistral-nuit`, repère sur son bord gauche : compteur « 7 statuts
  modifiés » + bouton unique **« Publier les statuts »**. Aucune étape intermédiaire, aucune modale de
  confirmation *avant*, une confirmation *après* (annoncée en `aria-live="polite"`).
- **Historique** : même tableau, filtres en ligne, export CSV. Les valeurs ancienne/nouvelle sont montrées
  par deux pastilles séparées par une flèche typographique `→`, jamais par une couleur de diff.
- Aucun bouton désactivé : si une action est impossible, elle reste focusable et explique pourquoi (§9.2).

### 7.3 Pages éditoriales (La démarche, Accessibilité, Mentions légales)

- Une seule colonne, `--mesure` 68ch, alignée à gauche de la grille (pas centrée : la page garde son bord
  gauche commun avec l'ardoise et les titres).
- `h2` en capitales condensées + repère, `--esp-section` avant chacun.
- Les citations et encarts sont des **slabs `--c-calcaire-ombre` avec `--bord-fort` en haut**, jamais des
  cartes ombrées ni des filets fins verticaux. **C'est ce même encart qui accueillera la consigne** quand
  elle sera fournie (§8.4) : le composant existe déjà, il n'y a rien à dessiner le jour venu.
- Les tableaux de sources/licences reprennent exactement le tableau de la liste du jour.
- Aucun visuel décoratif. Les seules images du site sont : l'image statique du département (repli sans JS)
  et, éventuellement, des photographies personnelles créditées sur « La démarche » — jamais en fond, jamais
  en bandeau héroïque, jamais derrière du texte.

---

## 8. Composants clés — spécification visuelle

### 8.1 Pastille de massif et jalon ZAPEF — **deux silhouettes, parce que ce sont deux dimensions**

C'est l'objet le plus répété du site, et la révision 2.0 y fait son changement structurel : il n'y a plus
une échelle de pastilles graduées, il y a **deux familles d'objets de forme différente**.

```
MASSIF  (une surface)              ZAPEF  (un point)
┌──────────────┐                        ┌────────┐
│▌▬▬▬▬▬  ACCÈS…│  pastille              │▮       │  jalon 18×18
└──────────────┘  rectangle 26×16       └───┬────┘  + hampe 2×8
                                            ┴       le point est au pied
```

| | Pastille de massif | Jalon ZAPEF |
|---|---|---|
| Silhouette | **rectangle large 26 × 16 px** — une surface | **carré 18 × 18 px planté sur une hampe de 2 × 8 px** — un point |
| Liseré | `--c-charbon` 2 px | `--c-charbon` 2 px, hampe comprise |
| Motif `autorise` | aucun (aplat nu) | aucun (aplat nu) |
| Motif `interdit` | `hachure_croisee`, trait 2,5 px, pas 10 px | `barre` : une seule oblique 3 px d'angle à angle |
| Rayon | `--r-0` | `--r-0` |

**Pourquoi deux silhouettes et non deux couleurs.** Le cas nominal du `level` brut 3 affiche un **jalon
vert sur un massif rouge**. Si les deux objets partageaient la même forme, cet affichage se lirait comme
une contradiction ou comme un bug. Larges/plates pour les surfaces, hautes/plantées pour les points :
**la forme dit de quoi on parle, la couleur dit dans quel état c'est.** C'est aussi ce qui rend la
divergence lisible sans texte, donc lisible de loin.
Mesuré : vert sur rouge ne contraste qu'à **1,48:1** — le liseré charbon (6,10:1 sur le vert, 4,11:1 sur
le rouge) est **la seule chose** qui détache le jalon de l'aplat qu'il surplombe (§10.2).

**Pourquoi la barre unique pour le jalon interdit et non la hachure croisée.** À 18 px, une hachure croisée
de pas 10 px ne montre que deux ou trois croisements et se lit comme du bruit ou du crénelage. La barre
unique reste identifiable jusqu'à 14 px. Le motif change parce que **l'échelle change**, pas parce que le
sens change : c'est la même opposition binaire aplat nu / aplat barré.

Règles communes :
- Hauteur de cible **≥ 44 px** quand l'objet est cliquable, taille nominale quand il est informatif —
  la cible s'obtient par du padding transparent, jamais en grossissant la pastille.
- Le libellé est en `--fs-250` (capitales, `--ls-etiquette`), en `--c-charbon` **sur le fond de page**,
  posé **à côté** de l'aplat. **Jamais dessus** (§4.1.d règle 3).
- Le motif est **toujours** présent sur l'état `interdit`, dans tous les contextes, y compris l'impression.

Motifs — CSS de référence (aucune image, budget §10 du brief) :

```css
/* ── Massifs : deux états, une opposition binaire ─────────────── */
.pastille { inline-size: 26px; block-size: 16px; border: 2px solid var(--statut-lisere);
            border-radius: var(--r-0); }

.pastille--autorise  { background-color: var(--statut-autorise); }   /* aplat nu, aucun motif */

.pastille--interdit  { background-color: var(--statut-interdit);
  background-image:
    repeating-linear-gradient(45deg,  var(--statut-interdit-encre) 0 2.5px, transparent 2.5px 10px),
    repeating-linear-gradient(-45deg, var(--statut-interdit-encre) 0 2.5px, transparent 2.5px 10px); }

/* ── États hors niveau : ce sont des absences, pas des niveaux ── */
.pastille--indisponible { background-color: var(--statut-indisponible);
  background-image: repeating-linear-gradient(-45deg,
    var(--statut-indisponible-encre) 0 2px, transparent 2px 9px); }

.pastille--hors-saison  { background-color: var(--statut-hors-saison); }  /* aucun motif */

.pastille--non-publie   { background-color: var(--statut-non-publie);
  background-image: radial-gradient(var(--statut-non-publie-encre) 1.2px, transparent 1.4px);
  background-size: 6px 6px; }

/* ── ZAPEF : carré planté ─────────────────────────────────────── */
.jalon { inline-size: 18px; block-size: 18px; border: 2px solid var(--statut-lisere);
         border-radius: var(--r-0); position: relative; }
.jalon::after { content: ""; position: absolute; inset-block-start: 100%;
  inset-inline-start: calc(50% - 1px); inline-size: 2px; block-size: 8px;
  background: var(--statut-lisere); }

.jalon--autorise { background-color: var(--statut-zapef-autorise); }     /* aplat nu */

.jalon--interdit { background-color: var(--statut-zapef-interdit);
  background-image: linear-gradient(45deg, transparent calc(50% - 1.5px),
    var(--statut-zapef-interdit-encre) calc(50% - 1.5px) calc(50% + 1.5px),
    transparent calc(50% + 1.5px)); }
```

**Sur la carte**, les mêmes motifs sont déclarés en `<pattern patternUnits="userSpaceOnUse">` dans le
`defs` du calque SVG de Leaflet. **La densité du motif doit rester constante à l'écran quel que soit le
zoom** : recalculer la taille du pattern sur `zoomend`, ou utiliser un pane non transformé. Un motif qui
s'étire au zoom cesse d'être un encodage fiable — et sur une légende binaire, le motif est la moitié de
l'information.

**Dégradation ZAPEF** : tant que la géométrie des points ZAPEF n'est pas fournie par un contrat (§4.1.e,
`OUVERT`), **aucun jalon n'est rendu sur la carte**. La dimension ZAPEF reste alors visible dans le
panneau massif, dans la liste du jour et dans la légende. Le jalon décrit ici est **prêt**, pas
« à venir » : le jour où les points existent, il n'y a rien à concevoir.

### 8.2 L'ardoise — le chiffre du jour et **la frise**

- Fond `--c-mistral-nuit`, texte `--c-calcaire` (12,66:1 conforme), méta en `--c-mistral-clair` (7,73:1).
- Chiffre en `--fs-800`, chiffres **tabulaires** (`font-variant-numeric: tabular-nums`) pour qu'il ne
  saute pas quand il passe de 9 à 12.
- Le dénominateur (« /27 ») est en `--fs-500`, aligné sur la ligne de base basse du chiffre.
- Repère version `--bloc` sur toute la hauteur du slab, à gauche : `::after` `--c-calcaire`, `::before`
  `--c-mistral`.

**La frise — ce que la légende binaire permet et qu'une échelle à 5 crans interdisait.**

Une rangée de **27 marques** — une par massif, dans l'ordre exact de la liste du jour — chacune étant la
pastille du §8.1 réduite à 14 × 10 px, `--esp-2xs` de gouttière. Sur un dispositif binaire, cette rangée
donne **la forme de la journée en un coup d'œil**, à quatre mètres, sans lire un mot : un bloc
majoritairement nu = journée ouverte ; un bloc majoritairement hachuré = journée fermée.

Contraintes, strictes, parce que c'est une répétition et que les répétitions dérapent :
- **Exactement un emplacement : l'ardoise.** Nulle part ailleurs. Jamais dans le pied, jamais dans le
  panneau, jamais dans le portail.
- **`aria-hidden="true"`.** Elle ne porte aucune information que la liste du jour ne porte déjà en toutes
  lettres, immédiatement en dessous. Elle n'est ni focusable, ni cliquable, ni survolable.
- Sur chrome sombre, le liseré et l'encre du motif basculent en `--c-calcaire` (§12). Mesuré : vert sur
  mistral-nuit 5,24:1, rouge sur mistral-nuit 3,53:1, liseré calcaire sur mistral-nuit 12,66:1 — la forme
  de chaque marque reste détachée du fond.
- **Elle disparaît entièrement si `etat_global !== 'disponible'`.** Une frise partielle laisserait croire
  à une journée partiellement connue.
- À 360 px, elle passe sur 2 rangs. Elle ne défile jamais horizontalement.
- **Aucun mouvement**, aucune apparition séquentielle, aucun compteur animé.

**Si l'information du jour est indisponible** : le chiffre disparaît, remplacé par le mot
« INDISPONIBLE » en `--fs-700`, la frise disparaît, l'ardoise prend la hachure `\` `--c-mistral` en
surimpression à 12 %, et le lien « Ouvrir la carte officielle de la préfecture » passe en bouton primaire.
On ne montre **jamais** un chiffre de la veille.

### 8.3 Bandeau d'alerte (péremption, source indisponible, hors-saison)

Fond `--c-mistral-nuit`, texte `--c-calcaire`, repère `--bloc` à gauche, `--bord-fort` en bas, hachure
`--c-mistral` à 45° en fond à faible opacité. Le premier mot du texte porte l'information
(« Donnée périmée. », « Source indisponible. », « Dispositif estival inactif. ») : le sens ne repose ni
sur la couleur ni sur une icône. La bannière de péremption **s'ajoute** aux statuts affichés, elle ne les
masque jamais.

### 8.4 Panneau massif — et **l'emplacement de la consigne**

Ordre vertical **fixe**, du haut vers le bas. Cet ordre ne varie pas selon l'état : c'est ce qui rend le
panneau prévisible au clavier et au lecteur d'écran.

| # | Bloc | Toujours présent ? |
|---|---|---|
| 1 | Nom du massif — `h2` + repère, capitales condensées | oui |
| 2 | **État du massif** — pastille + libellé officiel verbatim en `--fs-400` | oui, si `etat === 'disponible'` |
| 3 | **État des ZAPEF** — jalon + libellé officiel verbatim + note `*ZAPEF : …` en `--fs-100` | oui, si le massif porte des ZAPEF et `etat === 'disponible'` |
| 4 | **Emplacement de la consigne** | **conditionnel — voir ci-dessous** |
| 5 | Fraîcheur et source (§11.3) | oui |
| 6 | Lien « Ouvrir la carte officielle de la préfecture » | oui |

**Comment l'emplacement de la consigne se comporte aujourd'hui, où aucune consigne n'est publiée.**

L'extension expose `consignes_publiees === false` et une `consigne` **vide** (jamais `null`, jamais
inventée). Dans cet état :

- **Aucun intitulé « Consigne » n'est rendu.** Pas de titre orphelin.
- **Aucun gabarit vide, aucun tiret, aucun « — », aucun « non renseigné », aucun squelette.** Un
  emplacement vide qui se signale est pire qu'un emplacement absent : il donne à croire qu'une donnée
  manque alors que le fait est que la préfecture n'en publie pas.
- **Aucune hauteur réservée.** Le panneau est une simple pile en `gap: var(--esp-l)` ; un bloc absent ne
  laisse aucun trou, et rien ne se déplace le jour où il apparaît.
- À la place, **une seule phrase factuelle** en `--fs-200` / `--c-charbon-doux`, sans intitulé, sans
  excuse et sans point d'exclamation :
  > « Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en vigueur fait foi : [lien]. »

  Elle ne dit pas « information manquante » : elle dit ce qui est vrai et où aller. Voix active, pas
  d'apologie (§11.1 règle 3).

**Comment elle se remplira, sans aucune refonte.** Quand le propriétaire fournira une transcription de
l'arrêté, l'extension basculera `consignes_publiees` à `true` et renseignera `consigne`. Le bloc 4 rend
alors **un encart déjà défini au §7.3** — slab `--c-calcaire-ombre`, `--bord-fort` en haut, `--r-0` —
précédé de l'étiquette `CONSIGNE` en `--fs-250` capitales, texte en `--fs-400`, mesure `--mesure-etroite`.
**Aucun nouveau composant, aucun nouveau token, aucun nouveau motif ne sera nécessaire** : l'encart, la
bordure, l'étiquette et l'échelle typographique existent déjà et sont utilisés ailleurs. C'est la
définition opérationnelle de « ça tombe dedans sans rien redessiner ».

**Interdits attachés à cet emplacement :**
- Le thème ne **compose jamais** une consigne, ne la déduit jamais de l'état, ne la traduit jamais depuis
  un entier. Elle vient de l'extension ou elle n'existe pas.
- Tant que `consignes_publiees === false`, **aucun texte ne peut occuper l'emplacement 4** en dehors de la
  phrase factuelle ci-dessus.
- Une consigne ne peut **jamais** contredire ni nuancer le libellé officiel de l'état : elle le complète,
  elle ne le réinterprète pas.

### 8.5 Bloc de légende — reproduction fidèle

- Titre `Légende de la carte`, verbatim, en `h2` + repère.
- Quatre entrées, dans cet ordre : massif autorisé, massif interdit, ZAPEF autorisé, ZAPEF interdite.
  Libellés **verbatim**, y compris `autorisé` masculin / `interdite` féminin.
- Note `*ZAPEF : Zones d’Accueil du Public en Forêt` en `--fs-100`, apostrophe U+2019, rattachée
  typographiquement aux deux entrées ZAPEF (pas en pied de bloc isolé).
- **Une seconde ligne, séparée par `--bord-fin` et introduite par l'étiquette `SUR CE SITE`**, présente
  les états hors niveau (`information non disponible`, `dispositif estival inactif`). Cette séparation est
  **obligatoire** : elle empêche d'attribuer à la préfecture des états qui sont les nôtres.
- Le bloc porte `--ombre-decalee`. C'est, avec le panneau massif, l'un des deux seuls composants du site
  autorisés à porter une ombre.
- **La légende n'est jamais masquée derrière un bouton, un accordéon ou un survol.** Elle est visible en
  permanence, sans interaction, y compris sans JavaScript.

### 8.6 Module « Danger météo du jour » — sans aucune couleur

Échelle à cinq crans rendue par des carrés de 12 px en `--c-charbon` (pleins = niveau atteint, vides =
liseré 1,5 px `--c-garrigue`), suivie du libellé officiel Météo-France en toutes lettres et de la phrase
d'explication : « Le danger météo décrit les conditions du jour ; il ne détermine pas l'accès au massif,
qui relève de l'arrêté préfectoral. » Aucune pastille colorée, aucune icône de flamme, aucune proximité
visuelle avec les statuts.

> **Cette règle devient plus critique en v2.0, pas moins.** Le danger météo a cinq crans ; l'accès au
> massif en a deux. Colorer l'échelle météo installerait à l'écran une gradation à cinq niveaux qui serait
> immédiatement prise pour la vraie granularité du dispositif — exactement le contresens que §4.3 du
> brief cherche à empêcher. L'absence de couleur n'est pas une austérité : c'est ce qui protège la
> lecture binaire de la carte.

---

## 9. États d'interaction et mouvement

### 9.1 Anneau de focus — spécification unique

```css
:root {
  --focus-trait:  var(--c-mistral-nuit);    /* sur surfaces claires */
  --focus-trait-inverse: var(--c-calcaire); /* sur chrome sombre */
  --focus-halo:   var(--c-mistral-clair);
}
:where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
  outline: 3px solid var(--focus-trait);
  outline-offset: 2px;
  box-shadow: 0 0 0 6px var(--focus-halo);
}
.sur-sombre :focus-visible { outline-color: var(--focus-trait-inverse);
                             box-shadow: 0 0 0 6px var(--c-mistral); }
```

- Jamais `outline: none` sans remplacement. `:focus-visible` uniquement (pas de halo à la souris),
  **sauf** sur la feuille du bas et le panneau massif, où le focus programmatique doit rester visible.
- **Sur la carte**, un massif ou un jalon focusé reçoit un **double contour** : `--c-calcaire` 3 px **et**
  `--c-charbon` 3 px décalés. Ce n'est pas un raffinement : sur l'aplat vert officiel, un anneau calcaire
  seul ne fait que **2,42:1** ; la moitié charbon monte à **6,10:1**. Sur le fond de carte clair, c'est
  l'inverse qui menace (calcaire vs `--c-carte-fond` = 1,07:1) et c'est encore la moitié charbon qui
  sauve (13,79:1). **Le double contour garantit qu'au moins une de ses deux moitiés atteint 3:1 sur
  chacune des surfaces du système** — preuve complète au §10.5.

### 9.2 Survol, actif, désactivé

| État | Traitement | Règle |
|---|---|---|
| Repos | — | Les liens de contenu sont **soulignés en permanence** (`text-underline-offset: 0.18em`, épaisseur 1,5 px) |
| Survol | Fond `--c-calcaire-ombre` (boutons, lignes) ; soulignement porté à 3 px (liens) ; sur la carte, liseré du massif porté de 2 à 4 px — **jamais un changement de teinte** | **Aucune information n'apparaît au survol.** Un contenu qui n'existe qu'au survol est un défaut bloquant (§5.2 du brief) |
| Actif | `transform: translate(1px, 1px)` ; `--ombre-decalee` réduite à `2px 3px 0` | Le geste « la peinture s'enfonce » |
| Sélectionné | Liseré porté à 4 px + repère à gauche | Jamais un simple changement de couleur de fond, **jamais un éclaircissement de l'aplat officiel** |
| Désactivé | **N'existe pas.** L'action reste focusable et explique la raison (« Publication impossible : aucun statut modifié. ») | Évite l'exception de contraste et le cul-de-sac clavier |

> **Règle absolue héritée du §4.1** : aucun état d'interaction ne modifie la **teinte** d'un aplat de
> statut. Ni survol, ni focus, ni sélection, ni désactivation, ni opacité. Un vert éclairci au survol
> serait une couleur officielle altérée. Les états d'interaction agissent sur le **liseré** et sur le
> **repère**, jamais sur le pigment.

### 9.3 Clavier et pointeur

- **Cibles ≥ 44 × 44 px** partout (`--cible-min: 2.75rem`), y compris la paire segmentée du portail, la
  bascule de couche EFFIS, la poignée de la feuille du bas et les contrôles de zoom de Leaflet
  (les contrôles par défaut font 30 px : ils sont **redimensionnés**, pas laissés tels quels).
  Les jalons ZAPEF, physiquement petits, reçoivent une zone de frappe transparente de 44 px.
- **Échap ferme** le panneau massif, la feuille du bas, le sélecteur de date, et rend le focus à l'élément
  déclencheur. Aucun piège clavier.
- Ordre de tabulation : évitement → en-tête → ardoise (**la frise est sautée**, elle est `aria-hidden`) →
  **carte (un seul arrêt, puis flèches pour parcourir les massifs)** → légende → liste → sections → pied.
- Liens d'évitement « Aller au contenu » et « Aller à la liste des statuts » : cachés hors focus, visibles
  au focus en haut à gauche, fond `--c-mistral-nuit`, texte `--c-calcaire`.

### 9.4 Mouvement — durées et courbes

| Token | Valeur | Emploi |
|---|---|---|
| `--duree-court` | `120ms` | Changement de fond (survol, sélection dans la paire segmentée) |
| `--duree-moyen` | `200ms` | Ouverture/fermeture du panneau massif, zoom Leaflet |
| `--duree-long` | `320ms` | Feuille du bas mobile (translation verticale) |
| `--ease-net` | `cubic-bezier(0.2, 0, 0, 1)` | Entrées : démarrage franc, arrêt net |
| `--ease-retrait` | `cubic-bezier(0.4, 0, 1, 1)` | Sorties |

**Il n'existe que trois animations sur ce site** : le panneau (translation 12 px + opacité), les changements
d'état des puces (fond), le zoom de la carte. Rien d'autre ne bouge. **La frise ne bouge pas. Les jalons ne
bougent pas. Le repère ne bouge jamais.**

Interdits explicites : parallaxe, apparition au défilement, compteur qui s'incrémente, souffle de vent
animé (la tentation « mistral » est refusée : la métaphore vaut pour la palette, pas pour le mouvement),
squelettes pulsants, spinners, marqueur ZAPEF pulsant ou rebondissant. Un chargement se signale par une
barre de progression de 2 px en `--c-mistral` en haut de la zone concernée, et par un texte `aria-live`.

### 9.5 `prefers-reduced-motion`

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

## 10. Preuve d'accessibilité (passe 3)

Toutes les valeurs ci-dessous sont **calculées** selon WCAG 2.x en sRGB (luminance relative, seuil
`0.03928`, exposant `2.4`), pas estimées. Elles sont vraies **parce que `fill-opacity` vaut 1** : sous
transparence, aucune ne tient.

### 10.1 Où la teinte officielle échoue — dit sans détour

Seuil applicable aux aplats et aux limites de forme : **3:1** (WCAG 1.4.11, non-texte).

| Paire | Ratio | Verdict |
|---|---|---|
| `#22B14C` (autorisé) vs `--c-calcaire` `#EDEEEC` | **2,42:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-fond` `#E6E7E1` | **2,26:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-terre` `#DEDFD9` | **2,10:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-vegetation` `#D6DBD3` | **2,00:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-eau` `#CBD5D8` | **1,88:1** | **ÉCHEC** |
| `#E63A3C` (interdit) vs `--c-calcaire` | 3,58:1 | conforme |
| `#E63A3C` vs `--c-carte-fond` | 3,35:1 | conforme |
| `#E63A3C` vs `--c-carte-terre` | 3,11:1 | conforme (limite) |
| `#E63A3C` vs `--c-carte-vegetation` | **2,97:1** | **ÉCHEC** (de justesse) |
| `#E63A3C` vs `--c-carte-eau` | **2,79:1** | **ÉCHEC** |
| **`#22B14C` vs `#E63A3C`** — deux massifs voisins d'états opposés | **1,48:1** | **ÉCHEC — le plus grave de tous** |
| `--statut-indisponible` `#DEDFD9` vs `--c-carte-fond` `#E6E7E1` | **1,08:1** | **ÉCHEC** |
| `#22B14C` vs `--c-mistral-nuit` (frise sur l'ardoise) | 5,24:1 | conforme |
| `#E63A3C` vs `--c-mistral-nuit` | 3,53:1 | conforme |

**Constat, écrit noir sur blanc : la teinte officielle seule ne satisfait pas l'exigence AA du §8 du
brief.** Le vert échoue sur toutes les surfaces claires du système ; le rouge échoue sur la végétation et
sur l'eau ; et surtout, **deux massifs voisins d'états opposés ne se distinguent qu'à 1,48:1**, c'est-à-dire
pas du tout. C'est le cas le plus dangereux du site : sans traitement, on ne verrait pas où finit un massif
autorisé et où commence un massif interdit.

Le §4.2 du brief impose néanmoins de **reproduire la légende officielle**. Ces deux exigences ne sont pas
en conflit, parce que **ce n'est pas la teinte qui doit porter la conformité**.

### 10.2 Ce qui porte la conformité, mesuré : le liseré charbon 2 px

`--statut-lisere` = `--c-charbon` `#1A1C19`, épaisseur **2 px**, sur **tout** polygone, **toute** pastille,
**tout** jalon, **sans exception**.

| Le liseré contre… | Ratio | Verdict (seuil 3:1) |
|---|---|---|
| `#22B14C` (aplat autorisé) | **6,10:1** | conforme |
| `#E63A3C` (aplat interdit) | **4,11:1** | conforme |
| `--statut-indisponible` `#DEDFD9` | **12,80:1** | conforme |
| `--c-calcaire` `#EDEEEC` | **14,74:1** | conforme |
| `--c-carte-fond` `#E6E7E1` | **13,79:1** | conforme |
| `--c-carte-terre` `#DEDFD9` | **12,80:1** | conforme |
| `--c-carte-vegetation` `#D6DBD3` | **12,20:1** | conforme |
| `--c-carte-eau` `#CBD5D8` | **11,48:1** | conforme |
| **Minimum sur l'ensemble du système** | **4,11:1** | **conforme, avec 37 % de marge** |

**Le pire cas du §10.1 est résolu par ce seul mécanisme** : entre un massif vert et un massif rouge, le
liseré interpose une bande à 6,10:1 d'un côté et 4,11:1 de l'autre. La limite de forme est perceptible
partout, indépendamment de la teinte, indépendamment du daltonisme, et indépendamment du fond de carte.

Sur chrome sombre (`.sur-sombre` : ardoise, frise, bandeau, barre d'action), le liseré bascule en
`--c-calcaire` : **12,66:1 contre `--c-mistral-nuit`**. La forme reste détachée du fond dans les deux
familles de contexte.

C'est pourquoi le liseré est déclaré **porteur d'accessibilité** et non décoratif : le supprimer, l'amincir
sous 2 px, l'arrondir au point de le manger dans les angles (§6.2) ou le teinter est un défaut **bloquant**.

### 10.3 Ce qui porte l'indépendance à la couleur : le motif obligatoire

Une personne qui ne distingue pas le rouge du vert (deutéranopie, protanopie — environ 8 % des hommes)
verrait deux gris très proches. Le motif est donc le **second** canal, indépendant, et il est obligatoire.

| Motif | Encre | Sur | Ratio | Verdict |
|---|---|---|---|---|
| `hachure_croisee` (massif interdit) | `--c-charbon` | `#E63A3C` | **4,11:1** | conforme |
| `barre` (jalon ZAPEF interdit) | `--c-charbon` | `#E63A3C` | **4,11:1** | conforme |
| `hachure_croisee` sur chrome sombre | `--c-calcaire` | `#E63A3C` | **3,58:1** | conforme |
| `hachure_descendante` (indisponible) | `--c-charbon-doux` | `#DEDFD9` | **6,33:1** | conforme |
| `pointille` (non encore publié) | `--c-charbon-doux` | `#DEDFD9` | **6,33:1** | conforme |
| *(rejeté)* hachure en `--c-trace` `#9EA197` | `--c-trace` | `#DEDFD9` | **1,96:1** | **ÉCHEC — corrigé en v2.0** |

> **Correction issue de cette passe 3.** La v1.0 dessinait la hachure « indisponible » en `--c-trace`,
> par cohérence métaphorique avec la peinture ancienne du repère. Mesure faite : **1,96:1**, motif
> quasi invisible. `--c-trace` est **retiré de tous les motifs de statut** et confiné au décor (`::before`
> du repère, ombres décalées). La métaphore ne l'emporte pas sur une mesure.

**L'état `autorisé` n'a délibérément aucun motif.** Ce n'est pas un oubli : c'est l'opposition
« nu / marqué » qui encode l'information, exactement comme un panneau vierge s'oppose à un panneau barré.
Vouloir un motif « léger » sur l'autorisé affaiblirait l'écart. Mesuré, l'alternative ne tenait pas non
plus : `--c-calcaire` sur `#22B14C` ne fait que **2,42:1**, et `--c-charbon` sur `#22B14C` produirait un
vert visuellement assombri, donc une teinte officielle altérée.

**Troisième canal, toujours présent : le libellé.** Chaque pastille est accompagnée du libellé officiel
verbatim. Aucun statut n'est **jamais** encodé par la seule couleur, ni par couleur + motif sans texte.
Trois canaux indépendants : teinte, motif, mot.

### 10.4 Texte — pourquoi aucun mot ne se pose sur un aplat de statut

| Paire | Ratio | AA texte normal (4,5:1) |
|---|---|---|
| `--c-charbon` sur `#22B14C` | 6,10:1 | conforme — **mais interdit quand même** |
| `--c-charbon` sur `#E63A3C` | **4,11:1** | **ÉCHEC** |
| `#FFFFFF` sur `#E63A3C` | **4,17:1** | **ÉCHEC** |
| `--c-calcaire` sur `#E63A3C` | **3,58:1** | **ÉCHEC** |
| `--c-calcaire` sur `#22B14C` | **2,42:1** | **ÉCHEC** |
| **`--c-charbon` sur `--c-calcaire`** — la solution retenue | **14,74:1** | **conforme, très large** |

Aucune encre ne franchit 4,5:1 sur le rouge officiel. La règle « aucun texte sur un aplat de statut »
(§4.1.d règle 3) n'est donc pas une préférence graphique : c'est **la seule position tenable**, et elle
est uniforme pour les deux états afin qu'aucune exception ne s'installe. Les libellés vivent à côté de
l'aplat, sur `--c-calcaire`, à 14,74:1.

Corollaire : les jetons `--statut-*-encre` sont des **encres de motif**. Les employer comme `color` est un
défaut bloquant.

### 10.5 Anneau de focus — visible sur **chaque** surface de la palette

Seuil 3:1 contre la surface adjacente. Le double contour carte est réputé conforme si **au moins une** de
ses deux moitiés atteint 3:1.

| Surface | `--c-mistral-nuit` | `--c-calcaire` | `--c-charbon` | Verdict |
|---|---|---|---|---|
| `--c-calcaire` `#EDEEEC` | **12,66:1** | — | 14,74:1 | conforme |
| `--c-calcaire-ombre` `#DEDFD9` | **10,93:1** | — | 12,80:1 | conforme |
| `--c-carte-fond` `#E6E7E1` | 11,79:1 | 1,07:1 | **13,79:1** | conforme par le charbon |
| `--c-carte-vegetation` `#D6DBD3` | 10,43:1 | 1,21:1 | **12,20:1** | conforme par le charbon |
| `--c-carte-eau` `#CBD5D8` | 9,82:1 | 1,29:1 | **11,48:1** | conforme par le charbon |
| **`#22B14C`** (aplat autorisé) | 5,26:1 | 2,42:1 | **6,10:1** | conforme par le charbon |
| **`#E63A3C`** (aplat interdit) | 3,53:1 | 3,58:1 | **4,11:1** | conforme par les deux |
| `--c-mistral-nuit` `#0B2B3C` (chrome) | — | **12,66:1** | 1,16:1 | conforme par le calcaire |
| `--c-mistral` `#17567A` (bouton) | — | **6,81:1** | 1,35:1 | conforme par le calcaire |

**Aucune surface du système ne laisse le focus invisible.** Sur les surfaces claires et sur les deux aplats
officiels, c'est la moitié charbon qui porte ; sur le chrome sombre, la moitié calcaire. Le halo
`--c-mistral-clair` est un confort visuel, **jamais** le porteur du contraste — un halo seul serait
insuffisant sur plusieurs surfaces et ne doit jamais être livré sans son trait.

### 10.6 Indépendance à la couleur — règles fermes

1. **Aucun statut n'est jamais porté par la seule couleur.** Trois canaux obligatoires et simultanés :
   teinte + motif + libellé officiel en toutes lettres.
2. **Toute pastille est accompagnée de son libellé**, sauf dans la frise (§8.2), qui est `aria-hidden` et
   dont l'information intégrale figure en toutes lettres dans la liste du jour, immédiatement en dessous.
3. **La forme distingue les dimensions** : rectangle large = massif (surface), carré planté = ZAPEF
   (point). Un daltonien lit la dimension sans couleur ; un voyant lit l'état sans texte ; un lecteur
   d'écran lit les deux.
4. **Les trois états hors niveau ne sont jamais présentés comme des niveaux** : bloc de légende séparé,
   étiquette `SUR CE SITE`, phrases du §11.3.
5. **`forced-colors: active`** : les aplats passent en `Canvas`, les liserés et motifs en `CanvasText`, et
   **le libellé reste le porteur de sens** — c'est le seul mode où la teinte disparaît entièrement, et le
   site doit y rester intégralement compréhensible. À vérifier explicitement en revue.
6. **Zoom 200 % et 360 px** : aucun défilement horizontal, aucune pastille sous 12 px de haut, aucun
   libellé tronqué ni remplacé par une abréviation non explicitée.

---

## 11. Micro-rédaction

### 11.1 Règles de voix

1. **Voix active, sujet explicite.** « La préfecture publie les statuts vers 17 h », pas « les statuts sont publiés ».
2. **Le libellé nomme l'action**, jamais son mécanisme : « Publier les statuts », « Afficher les zones
   parcourues par le feu », « Fermer le panneau ». Interdits : « Valider », « OK », « Soumettre », « En savoir plus ».
3. **Les erreurs disent quoi faire, sans s'excuser.** « Choisissez un niveau pour chaque massif modifié. »
   Interdits : « Oups », « Désolé », « Une erreur est survenue ».
4. **Aucune promesse d'officialité.** Le site « relaie », « reprend », « d'après » — il ne « garantit » jamais.
5. **Aucun superlatif, aucune exclamation, aucun emoji, aucune icône seule** porteuse de sens.
6. **Dates et heures en français long** : « mardi 11 août 2026 », « 17 h 00 » (espace insécable, pas de `:`).
   Le thème ne compose **jamais** une date lui-même : il consomme `massifs_horodatage()`.
7. **Chiffres écrits en chiffres** dès qu'ils sont des données (« 12 massifs sur 27 »).
8. **[v2.0] Les libellés officiels sont reproduits mot pour mot, jamais paraphrasés, jamais corrigés,
   jamais abrégés.** Ni « Autorisé » seul, ni « Massif ouvert », ni « Accès autorisé » sans « au massif ».
   Les incohérences de la source sont conservées (§11.4). Une paraphrase d'un libellé officiel est un
   défaut bloquant, au même titre qu'une couleur inventée.

### 11.2 Vocabulaire fixe — un terme, un sens, partout

| Terme retenu | Sens | Ne jamais dire |
|---|---|---|
| **massif** | Le périmètre forestier du référentiel DDTM. Une **surface** | zone, secteur, espace, forêt |
| **niveau** | **[v2.0]** L'un des **deux** états d'accès publiés par la préfecture : autorisé ou interdit. Le terme est conservé parce que l'en-tête officiel est `Niveau d'Accès` | couleur, code, alerte, cran, palier |
| **ZAPEF** | Zone d'Accueil du Public en Forêt. Un **point**, une dimension **distincte** du niveau du massif | aire d'accueil, site, parking, aménagement |
| **statut** | L'enregistrement « ce massif, ce jour, ce niveau » | état, situation |
| **consigne** | Ce que le niveau impose au promeneur. **Non publiée à ce jour** (§4.1.e) | recommandation, conseil |
| **fraîcheur** | L'âge de la donnée affichée | actualité, mise à jour (en tant que nom) |
| **dispositif** | Le régime préfectoral estival, du 1er juin au 30 septembre inclus | système, plan, saison |
| **jour de validité** | Le jour auquel le statut s'applique | date, jour J |
| **carte officielle** | La carte de la préfecture | site officiel, source officielle |
| **zone parcourue par le feu** | Le polygone EFFIS | incendie, feu actif, zone brûlée |
| **danger météo** | L'indicateur Météo-France, à cinq crans, **sans rapport avec l'accès** | risque, alerte météo |
| **gestionnaire** | Le rôle qui met à jour les statuts | éditeur, modérateur, admin |
| **publier** | L'action d'enregistrer et de diffuser les statuts | valider, envoyer, sauvegarder |

**[v2.0] Termes explicitement bannis, hérités de l'hypothèse à 5 crans** : « niveau 1 » … « niveau 5 »,
« vigilance jaune / orange / noire », « risque sévère », « risque exceptionnel », « accès réglementé »,
« accès autorisé avec vigilance ». Aucun n'existe dans le dispositif des Bouches-du-Rhône. Le préfixe
« Vigilance » n'apparaît que dans le bulletin PDF (« Vigilance vert - … ») et **n'est pas notre référence** :
l'écran reproduit la formulation de la carte.

### 11.3 Chaînes fixes rédigées par le site (à reprendre mot pour mot)

- Non-officialité (§5.6 du brief, obligatoire) : « Site d'information indépendant. Seules les publications
  de la préfecture des Bouches-du-Rhône font foi : [lien carte officielle]. »
- Fraîcheur : « Statuts du {jour de validité}, publiés la veille à {heure} par la préfecture — relevés sur
  ce site le {date} à {heure}. »
- Indisponible : « Information du jour non disponible. Consultez la carte officielle de la préfecture. »
- Hors saison : « Dispositif estival inactif. Reprise le {date}. »
- Non encore publié : « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. »
- Consigne absente (§8.4) : « Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en
  vigueur fait foi : [lien]. »
- EFFIS : « Périmètres estimés par satellite (feux d'environ 30 ha et plus). Zone déjà parcourue par le
  feu, ce n'est pas un périmètre officiel d'interdiction. »
- Attribution des statuts (§9 du brief, verbatim, **jamais rédigée à la main par le thème** — elle vient de
  l'extension) : « D'après les publications de la préfecture des Bouches-du-Rhône ».

### 11.4 Chaînes **officielles** reproduites verbatim — ne jamais éditer

Ces huit chaînes appartiennent à la préfecture. Elles sont fournies par l'extension et rendues telles
quelles. **Toute modification, y compris orthographique, est un défaut bloquant.**

| Emploi | Chaîne exacte | Piège |
|---|---|---|
| État de massif | `Accès au massif autorisé` | — |
| État de massif | `Accès au massif interdit` | — |
| État de ZAPEF | `Accès à la ZAPEF* autorisé` | **`autorisé` au masculin** — c'est la source |
| État de ZAPEF | `Accès à la ZAPEF* interdite` | **`interdite` au féminin** — c'est la source |
| Note de légende | `*ZAPEF : Zones d’Accueil du Public en Forêt` | apostrophe **typographique U+2019** |
| Titre de légende | `Légende de la carte` | — |
| En-tête de colonne | `Niveau d'Accès` | apostrophe **droite U+0027**, majuscule à `Accès` |

> **Les deux apostrophes divergent volontairement.** `Zones d’Accueil` porte U+2019 ; `Niveau d'Accès`
> porte U+0027. C'est ce que publie la source. Une « uniformisation typographique » — réflexe naturel
> d'un intégrateur consciencieux — casserait la reproduction fidèle exigée par le §4.2 du brief.
> Ces chaînes doivent survivre à toute passe de nettoyage, de linter et de correcteur orthographique.
> Le sous-ensemble de police doit contenir `’` et `*` (§5).

---

## 12. Jetons CSS — contenu exact de `assets/css/tokens.css`

À recopier **tel quel**. Aucun autre fichier ne définit de custom property ; aucune valeur littérale de
couleur, d'espacement ou de durée n'apparaît ailleurs dans le CSS. Ce bloc est la **charge utile
normative** attendue par la chaîne front.

```css
/* MASSIFS — jetons du design system. Voir design-system/MASTER.md (v2.0).
   Ne pas ajouter de valeur hors échelle. Ne pas redéfinir hors :root, sauf
   les deux exceptions documentées : --repere-couleur (§3.1) et le groupe
   liseré/encre sous .sur-sombre (fin de ce fichier). */

:root {
  /* ── Surfaces et encres ─────────────────────────────────── */
  --c-calcaire:        #EDEEEC;
  --c-calcaire-ombre:  #DEDFD9;
  --c-poussiere:       #C3C5BC;
  --c-trace:           #9EA197;   /* décor uniquement — jamais un motif de statut */
  --c-garrigue:        #5F6B5A;
  --c-charbon-doux:    #4A4E48;
  --c-charbon:         #1A1C19;
  --c-mistral-nuit:    #0B2B3C;
  --c-mistral:         #17567A;
  --c-mistral-clair:   #8FC3DD;   /* interdit sur fond clair : 1,64:1 */

  /* ── Fond de carte monochrome ───────────────────────────── */
  --c-carte-fond:       #E6E7E1;
  --c-carte-terre:      #DEDFD9;
  --c-carte-vegetation: #D6DBD3;
  --c-carte-eau:        #CBD5D8;
  --c-carte-trait:      #B4B7AC;
  --c-carte-encre:      #4A4E48;

  /* ══ STATUTS OFFICIELS — REPRODUITS, NON MODIFIABLES ══════════════════
     Source : docs/decisions/source-prefecture.md §4.2 et §4.3.
     Deux états d'accès au massif + une dimension ZAPEF indépendante.
     Relevé au pixel sur les pastilles de légende publiées par la préfecture.
     NE JAMAIS harmoniser, éclaircir, désaturer ni « adapter » ces valeurs.
     NE JAMAIS créer de jeton numéroté (--statut-1…) : un jeton numéroté
     réutilisé après un changement de légende repeint des massifs interdits
     dans la mauvaise couleur, en silence. Un jeton sémantique manquant ne
     produit aucune couleur : l'échec est bruyant, donc sûr.               */

  --statut-autorise:        #22B14C;   /* Accès au massif autorisé  */
  --statut-interdit:        #E63A3C;   /* Accès au massif interdit  */
  --statut-zapef-autorise:  #22B14C;   /* Accès à la ZAPEF* autorisé */
  --statut-zapef-interdit:  #E63A3C;   /* Accès à la ZAPEF* interdite */

  /* Encres des MOTIFS posés sur l'aplat — jamais des encres de texte :
     aucun texte n'est posé sur un aplat de statut (§10.4). */
  --statut-autorise-encre:        var(--c-charbon);
  --statut-interdit-encre:        var(--c-charbon);
  --statut-zapef-autorise-encre:  var(--c-charbon);
  --statut-zapef-interdit-encre:  var(--c-charbon);

  /* États HORS NIVEAU — des absences d'information, pas des niveaux.
     Ceux-ci nous appartiennent : aucune couleur officielle en jeu. */
  --statut-indisponible:        var(--c-calcaire-ombre);
  --statut-indisponible-encre:  var(--c-charbon-doux);  /* 6,33:1 */
  --statut-hors-saison:         var(--c-calcaire-ombre);
  --statut-hors-saison-encre:   var(--c-charbon-doux);
  --statut-non-publie:          var(--c-calcaire-ombre);
  --statut-non-publie-encre:    var(--c-charbon-doux);

  /* Le liseré porte la conformité AA, pas la teinte (§10.2).
     Minimum mesuré sur tout le système : 4,11:1 (charbon sur #E63A3C). */
  --statut-lisere:            var(--c-charbon);
  --statut-lisere-epaisseur:  2px;
  --statut-motif-trait:       2.5px;
  --statut-motif-pas:         10px;

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
  --r-1: 2px;      /* champs et boutons seulement — jamais une pastille */
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

  /* ── Pastilles, jalons, frise ───────────────────────────── */
  --pastille-l: 26px;   --pastille-h: 16px;
  --jalon-cote: 18px;   --jalon-hampe: 8px;
  --frise-l: 14px;      --frise-h: 10px;

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

/* Exception documentée n° 2 : sur chrome sombre, le liseré et les encres de
   motif basculent en calcaire. Les TEINTES officielles ne changent jamais.
   Mesuré : liseré calcaire sur mistral-nuit 12,66:1 ; hachure calcaire sur
   #E63A3C 3,58:1. Voir §10.2 et §10.3. */
.sur-sombre {
  --statut-lisere:                var(--c-calcaire);
  --statut-autorise-encre:        var(--c-calcaire);
  --statut-interdit-encre:        var(--c-calcaire);
  --statut-zapef-autorise-encre:  var(--c-calcaire);
  --statut-zapef-interdit-encre:  var(--c-calcaire);
}

@media (min-width: 37.5rem) { :root { --gouttiere: var(--esp-l); } }

@media (prefers-reduced-motion: reduce) {
  :root { --duree-court: 0.01ms; --duree-moyen: 0.01ms; --duree-long: 0.01ms; }
}
```

> Convention de nommage : **les noms de jetons sont en ASCII pur**, sans accent ni caractère spécial
> (`--statut-autorise`, `--esp-…`, `--duree-…`). Les accents ne vivent que dans la documentation.

### 12.1 Ce qui a disparu de `tokens.css` en v2.0 — et pourquoi on ne le réintroduit pas

| Jeton supprimé | Raison |
|---|---|
| `--statut-1` … `--statut-5` et `--statut-N-encre` | Numérotation bannie (§4.1.d règle 7). `--statut-2` valait un **jaune** : réutilisé pour un état « interdit », il aurait peint des massifs fermés en jaune sans qu'aucun test ne le voie |
| `--statut-lisere-n5` | Le niveau 5 n'existe pas. Le liseré est unique et bascule par contexte, pas par niveau |
| `--c-pin-alep` | H 146°, S 25 % : viole la règle §2.1 s'il est peint. Reste un ingrédient de mélange documenté, dont le produit `--c-carte-vegetation` est, lui, un jeton |

**Correspondance avec le contrat de l'issue #3** — les noms ci-dessous sont ceux que l'extension émet, et
ils existent tous dans le bloc précédent : `--statut-autorise`, `--statut-interdit`,
`--statut-autorise-encre`, `--statut-interdit-encre`, `--statut-zapef-autorise`, `--statut-zapef-interdit`,
`--statut-indisponible`, `--statut-lisere`.

**Trois jetons sont ajoutés par ce document et doivent être reflétés dans `legende.config.php`** pour la
clé `etats_hors_niveau` : `--statut-hors-saison`, `--statut-non-publie`, et les `-encre` associés. Ce ne
sont pas des données officielles — ce sont nos états, et leur nommage m'appartient. Motifs attendus côté
configuration : `indisponible` → `hachure_descendante`, `hors_saison` → `aucun`,
`non_encore_publie` → `pointille`. Pour la dimension ZAPEF : `autorise` → `aucun`, `interdit` → `barre`.

---

## 13. Impression

La page imprimée est un **livrable en soi** (§5.3 du brief : « imprimable proprement ») : c'est la feuille
qu'on affiche au gîte ou à la mairie. **La légende binaire la rend nettement meilleure** : deux états
s'impriment sans ambiguïté en noir et blanc, là où cinq crans gris auraient été indiscernables.

```
@page { margin: 12mm; }
```

- Fonds convertis : `--c-calcaire` → blanc, `--c-mistral-nuit` → blanc avec `--bord-fort` en haut et texte noir.
- **La carte interactive n'est pas imprimée** (`display: none`) : elle est remplacée par l'image statique du
  département si elle est disponible, sinon par rien. **La liste du jour est imprimée intégralement**, en
  tableau à filets 0,5 pt, `page-break-inside: avoid` sur chaque ligne, en-tête de tableau répété
  (`thead { display: table-header-group; }`), colonne d'état intitulée `Niveau d'Accès`.
- **Les pastilles s'impriment en noir et blanc, et restent lisibles sans couleur** :
  - `autorisé` → aplat **blanc**, liseré 1,5 pt noir, aucun motif ;
  - `interdit` → hachure croisée noire, liseré 1,5 pt noir ;
  - `indisponible` → hachure descendante grise 45 %, liseré 1,5 pt noir.
  Le libellé officiel accompagne toujours la pastille. La couleur n'est donc **jamais** nécessaire à la
  compréhension d'une page imprimée en niveaux de gris.
  `print-color-adjust: exact` uniquement sur les pastilles et les jalons, pour préserver les motifs.
- **La frise n'est pas imprimée** (`display: none`) : elle est `aria-hidden` et redondante avec la liste.
- **Toujours imprimés** : le titre, le jour de validité, la ligne de fraîcheur, le bandeau de non-officialité,
  la légende complète (quatre entrées + note ZAPEF), les attributions (§9 du brief).
- Les liens de contenu voient leur URL dépliée (`a[href^="http"]::after { content: " (" attr(href) ")"; }`),
  sauf dans les menus et le pied, masqués à l'impression.
- Le repère s'imprime : `::after` noir, `::before` gris 45 %. C'est la signature de la feuille papier.
- Corps à 10,5 pt / 1,45 ; `h1` 20 pt ; `h2` 14 pt ; le chiffre du jour à 34 pt.

---

## 14. Autocritique

### 14.1 Passe 2 — v1.0, conservée intégralement

Méthode : chaque décision de la passe 1 a été soumise à quatre questions — *l'aurais-je produite pour
n'importe quel site carto ? tombe-t-elle dans un tell « design IA » ? l'audace est-elle unique et tenue ?
la palette vient-elle du sujet ou d'un nuancier ?*

| Décision | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Fond de carte monochrome calcaire** | Générique ? | **Non.** C'est l'inverse du réflexe carto (fond OSM standard coloré, polygones translucides par-dessus). | Conservé, **promu au rang de pari central** (§1). |
| **Règle de non-collision chromatique** | Générique ? | Non — aucun design system générique ne s'interdit le vert « succès » et le rouge « erreur ». | Conservée. Ni bouton vert ni erreur rouge. |
| **Palette crème + serif + terracotta** | Tell IA §7 ? | **Oui, refusé.** C'était le réflexe « Provence ». | **Refait** : calcaire tiré vers le froid/vert (`#EDEEEC`, pas `#F5F0E6`), terracotta bannie, aucun serif. |
| **Noir + accent acide** | Tell IA §7 ? | **Oui, évité.** | Encre `#1A1C19` (charbon, jamais `#000`), accent bleu froid rabattu. |
| **Look journal à filets fins** | Tell IA §7 ? | **Oui, évité.** | Les filets porteurs de sens font 2 px et 4 px ; le 1 px est cantonné aux séparateurs. |
| **Cartes arrondies sur fond gris** | Kit UI générique ? | **Oui, refusé.** | `border-radius` plafonné à **2 px** (§6.2). Aucun composant « card ». |
| **Le repère (signature)** | Une seule audace ? | Oui — métaphore du produit, pas ornement. | Conservé, **liste fermée de 7 emplacements** + liste d'interdits. |
| **Ombres décalées non floues** | Deuxième idée non reliée ? | **Risque réel — corrigé.** | Décalage **exactement** celui du repère `(3px, 4px)`, couleur `--c-trace`. Limité à 2 composants. |
| **Ombres décalées** | Tell « néo-brutalisme » ? | **Risque réel.** | **Différencié volontairement** : décalage 3–4 px, jamais noir, palette minérale désaturée, typo civique. Choix délibéré, justifié. |
| **Big Shoulders Display** | Typo « par défaut » ? | Non : les réflexes sont Oswald, Anton, Roboto Condensed. | Conservée + vérification des capitales accentuées. |
| **Atkinson Hyperlegible Next** | Choix esthétique gratuit ? | Non : accessibilité bloquante, typo pour la basse vision. | Conservée + repli documenté (Public Sans variable). |
| **Bleu mistral en chrome** | Bleu « corporate » ? | **Risque.** | **Rabattu et refroidi**, employé en aplats pleine largeur, jamais en petites touches. |
| **Équivalent textuel** | Traité en repli ? | **Oui au premier jet — corrigé.** | **Refait** : `h2`, repère, pleine largeur. Second héros, pas note de bas de page. |
| **Animation « mistral »** | Idée dispersée ? | **Oui, supprimée.** | Le mistral vit dans la palette, pas dans le mouvement. |
| **Module météo** | Fusionné avec le statut ? | Risque de confusion identifié. | **Sans aucune couleur**, visuellement étranger au reste. |
| **Couleurs de statut** | Inventées ? | **Non — et c'était bloquant.** | Substituts marqués `À CONFIRMER` + 8 questions précises. **C'est cette discipline qui a rendu la v2.0 possible sans refonte.** |

### 14.2 Passe 2 bis — v2.0, après l'établissement de la légende réelle

Nouvelle passe complète sur ce que la révision introduit ou modifie. Mêmes quatre questions, plus une
cinquième, propre à cette révision : *est-ce que je profite d'une simplification pour ajouter de la
décoration ?*

| Décision v2.0 | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Reproduire `#22B14C` / `#E63A3C` sans les retoucher** | Un designer ne devrait-il pas « corriger » ces teintes criardes ? | **Non — et la tentation était forte.** Ce vert et ce rouge sont typiques d'un nuancier logiciel des années 2000, pas d'un choix chromatique. Les désaturer aurait « embelli » la carte. | **Reproduits à l'identique.** §4.2 du brief. Ce ne sont pas mes couleurs, et le seul endroit du site où je n'ai pas le droit de dessiner est précisément celui qui compte. La palette du site, elle, est entièrement mienne — et c'est son écart minéral avec ces deux teintes qui les fait exister. |
| **Deux états au lieu de cinq** | Est-ce un appauvrissement visuel ? | **Non — c'est le contraire.** Cinq aplats sur une carte donnent une mosaïque à déchiffrer ; deux donnent une réponse lisible à quatre mètres. | Complexité libérée **réinvestie dans la lisibilité de loin** : aplats opaques, liseré 2 px, hachure grossie de 2 px/pas 6 px à 2,5 px/pas 10 px, frise de 27 marques. **Zéro ornement ajouté.** |
| **La frise des 27 marques** | Est-ce une deuxième audace, une décoration ? | **Risque réel, examiné sérieusement.** | **Conservée, mais bornée** : elle n'introduit **aucune forme nouvelle** (c'est la pastille du §8.1, réduite), elle est **de la donnée**, pas du décor, et elle est cantonnée à **un seul emplacement**, `aria-hidden`, immobile, et absente si la journée n'est pas connue. Si un doute subsiste en revue, elle est le premier élément à retirer — et le système tient sans elle. |
| **Deux silhouettes : rectangle massif / carré planté ZAPEF** | Idée dispersée ? Un troisième langage de forme ? | **Non.** C'est une contrainte de domaine, pas une envie : le `level` 3 affiche un jalon vert sur un massif rouge. | Même vocabulaire (rectangle, angle vif, liseré 2 px, `--r-0`) ; seule la **proportion** distingue surface et point. Aucun pictogramme, aucune icône, aucun jeu de formes libre. |
| **Le repère non étendu aux jalons ZAPEF** | Incohérence ? | **Non, discipline.** | La liste des 7 emplacements **n'est pas allongée**. Un décalage de 3–4 px sur un objet de 18 px détruirait la silhouette. La cohérence de façade aurait dilué la signature. |
| **Re-dérivation de la règle chromatique** | Ai-je juste retouché des chiffres ? | **Non — la bande jaune est ré-argumentée.** L'ancienne raison (« elle appartient à la préfecture ») était devenue fausse. | Nouvelle raison, **plus forte** : un ambre saturé entre un vert et un rouge invente visuellement un cran intermédiaire qui n'existe pas dans le 13. Plus un **audit chiffré** des 12 tokens contre la règle, qui a fait **supprimer `--c-pin-alep`**. Une règle qu'on n'audite pas n'est pas une règle. |
| **Motif : opposition binaire au lieu d'une densité graduée** | Le motif n'est-il plus qu'une béquille a11y ? | **Non.** Sur deux états, la densité n'a plus de référent : « plus dense = plus grave » n'a de sens qu'avec trois crans ou plus. | Opposition **nu / barré**, empruntée à la signalétique de terrain (panneau vierge vs panneau barré). Et c'est aussi le **signal de loin** : le rouge hachuré lit plus sombre que le vert nu, avant même qu'on distingue les teintes. |
| **Hachure « indisponible » en `--c-trace`** | Métaphore ou mesure ? | **Échec mesuré : 1,96:1.** La cohérence poétique avec la peinture ancienne du repère masquait un motif invisible. | **Corrigé** en `--c-charbon-doux` (**6,33:1**), et `--c-trace` **interdit** dans tout motif de statut. La mesure l'emporte sur la métaphore. |
| **Pastilles à `--r-1` (2 px)** | Détail sans importance ? | **Non — mesuré comme nuisible.** Sur 16 px de haut, 2 px de rayon rongent le liseré dans les angles. | **Passées à `--r-0`.** On ne rogne pas l'élément qui porte la conformité pour un adoucissement décoratif. |
| **Jetons sémantiques `--statut-autorise` / `--statut-interdit`** | Question de style de nommage ? | **Non, question de sécurité.** | Numérotation **bannie**. `--statut-2` était jaune : réutilisé pour « interdit », il aurait peint des massifs fermés en jaune sans qu'aucun test ne le voie. Un jeton absent ne peint rien — échec bruyant, donc sûr. |
| **Publier les échecs de contraste au lieu de les contourner** | Est-ce que j'affaiblis le livrable en écrivant « ÉCHEC » douze fois ? | **Non — c'est le livrable.** Un tableau tout vert aurait signifié que je n'ai pas mesuré. | §10.1 énonce chaque échec, §10.2 et §10.3 montrent ce qui porte l'exigence à la place. Le pire cas (**vert vs rouge à 1,48:1**) n'aurait jamais été trouvé sans cette passe : c'est lui qui rend le liseré **bloquant** au lieu de « recommandé ». |
| **Emplacement de consigne vide** | Un trou dans le design ? | **Non — un fait, rendu honnêtement.** | Aucun intitulé orphelin, aucun tiret, aucun squelette, aucune hauteur réservée ; une phrase factuelle et un lien. Le remplissage futur réutilise un encart **déjà existant** (§7.3) : rien à redessiner. |
| **Reproduire `autorisé`/`interdite` et les deux apostrophes** | Négligence ? | **Non, fidélité.** | Les incohérences sont **documentées comme obligatoires** (§11.4) et protégées contre les passes de nettoyage automatique — c'est précisément le genre de détail qu'un linter « améliore » tout seul. |

**Verdict global de la passe 2 bis.** La révision n'a introduit **aucune audace nouvelle**. La signature
reste unique et tenue à sept emplacements ; le pari du fond monochrome est renforcé, pas concurrencé ;
la seule addition visuelle — la frise — est une répétition d'un objet existant, portant de la donnée,
bornée à un emplacement et supprimable sans dommage. Trois décisions de la v1.0 ont été **infirmées par
la mesure** (hachure `--c-trace`, rayon des pastilles, bande de teinte réservée) et corrigées ; une
quatrième (les jetons numérotés) a été bannie pour une raison de sécurité, pas d'esthétique.

**Ce que je referais si le temps le permettait** : rien de nouveau. Le jeu de pictogrammes de massif
écarté en v1.0 le reste, et la légende binaire renforce ce refus — avec deux états, une iconographie
supplémentaire n'aurait plus rien à encoder.

---

## 15. Journal des décisions (extrait pour le §11 du brief)

| # | Décision | Raison retenue | Alternative écartée |
|---|---|---|---|
| D-01 | Fond de carte monochrome calcaire, restylé côté serveur | Les statuts deviennent la seule couleur de l'écran : lisibilité + impact | Fond OSM standard + polygones translucides |
| D-02 | `fill-opacity: 1` sur les massifs | Les ratios mesurés ne tiennent pas sous transparence | Aplats à 50 % (ce que fait la source officielle) |
| D-03 | Aucune couleur sémantique hors légende | Empêche toute confusion entre chrome et état d'accès | Vert « succès » / rouge « erreur » conventionnels |
| D-04 | Rayon plafonné à 2 px | Registre « signalétique peinte », anti-kit UI | Cartes arrondies 8–12 px |
| D-05 | Ombres décalées non floues, dérivées du repère | Une seule audace tenue partout | Ombres douces `0 2px 8px rgba(0,0,0,.1)` |
| D-06 | 2 fichiers de police variables | Budget §10 tenu, hiérarchie par la taille | 4 statiques (dépassement) |
| D-07 | Atkinson Hyperlegible Next pour le texte | Accessibilité bloquante, argument opposable en mémoire technique | Inter / Open Sans |
| D-08 | Motif obligatoire partout où la couleur apparaît | Lisible en niveaux de gris et en vision dichromatique | Couleur seule + libellé |
| D-09 | *(v1.0)* Légende officielle en `À CONFIRMER` + 8 questions | Interdiction d'inventer (§4.2) ; système paramétré pour l'échange des valeurs | Déduire les couleurs d'une capture approximative |
| D-10 | Liste du jour traitée en second héros | L'équivalent textuel ne doit pas se lire comme un repli | Liste discrète sous la carte |
| **D-11** | **Légende réelle : 2 états d'accès + dimension ZAPEF ; les 5 crans substituts sont supprimés** | Établie par trois relevés concordants. Le §4.2 impose de reproduire *exactement* la légende officielle. L'échelle à six crans du code partagé appartient à d'autres départements | Conserver 5 crans « pour la richesse visuelle » ; ou dériver des libellés depuis le `level` brut 0–4, dont **aucun libellé n'est publié** |
| **D-12** | **Teintes officielles `#22B14C` / `#E63A3C` reproduites sans retouche** | §4.2 du brief. La conformité est portée ailleurs (D-13) | Désaturer, assombrir ou « harmoniser » avec la palette minérale — ç'aurait été plus joli et faux |
| **D-13** | **La conformité AA des statuts est portée par le liseré charbon 2 px et le motif, jamais par la teinte** | Mesuré : la teinte seule échoue 8 fois sur 13 paires, dont **vert vs rouge à 1,48:1**. Le liseré tient **4,11:1 au pire cas** sur tout le système | Choisir des teintes conformes (= inventer la légende) ; ou déclarer l'exception « couleur de marque » (= abandonner le §8) |
| **D-14** | **Jetons de statut sémantiques ; numérotation bannie à perpétuité** | Un jeton numéroté réutilisé après changement de légende repeint des massifs interdits dans la mauvaise couleur, **en silence**. Un jeton absent ne peint rien : échec bruyant | `--statut-1` … `--statut-5` (v1.0), qui faisaient de « interdit » un jaune |
| **D-15** | **ZAPEF rendues par une silhouette distincte (carré planté), pas par une couleur distincte** | Au `level` 3, un jalon vert se superpose à un massif rouge : sans écart de forme, l'affichage se lit comme une contradiction. La forme dit la dimension, la couleur dit l'état | Marqueur rond classique ; ou fusion ZAPEF/massif en un seul indicateur (perte d'une dimension officielle) |
| **D-16** | **Motif binaire nu/barré, et suppression de la densité graduée** | Sur deux états, « plus dense = plus grave » n'a plus de référent et suggérerait des crans intermédiaires inexistants | Conserver une gradation de densité « au cas où » |
| **D-17** | **Encre des motifs hors niveau passée de `--c-trace` à `--c-charbon-doux`** | Mesure de la passe 3 : 1,96:1 contre 6,33:1. Le motif était invisible | Garder `--c-trace` pour la cohérence métaphorique avec le repère |
| **D-18** | **Emplacement de consigne présent, silencieux quand vide, sans hauteur réservée** | Le §5.2 du brief promet une consigne ; la préfecture n'en publie aucune et l'arrêté est illisible. Un emplacement qui se signale ferait croire à une donnée manquante | Afficher « — » ou « non renseigné » ; ou rédiger nous-mêmes une consigne plausible (interdit par le §4.2) |
| **D-19** | **Ajout de la frise des 27 marques dans l'ardoise, bornée à un emplacement et `aria-hidden`** | La légende binaire rend la forme de la journée lisible d'un coup d'œil, à quatre mètres. Réinvestit la complexité libérée dans la lisibilité, pas dans le décor | Ne rien ajouter (défendable) ; ou une jauge / un graphique en anneau (kit UI, deuxième audace) |

---

## 16. Interdits — liste de contrôle de revue

Tout élément ci-dessous constaté par `review-cms` est un **défaut bloquant**.

**Fabrication**
- Constructeur de pages, thème tiers ou par défaut, kit UI, framework CSS générique (Bootstrap, Tailwind…).
- Toute requête navigateur vers un domaine tiers : police, icône, script, tuile, image.
- Police servie depuis un service de polices, même « via » un plugin tiers. Aucun CDN, aucun asset distant.
- Plus de 2 fichiers de police. Icônes en police d'icônes (les rares symboles sont du SVG en ligne).

**Formes**
- `border-radius` > 2 px. Pilules, avatars ronds, boutons arrondis, **pastille de statut arrondie**.
- Ombre floue (`blur-radius` ≠ 0), dégradé décoratif, verre dépoli, néomorphisme.
- Ombre portée sur autre chose que le panneau massif et le bloc de légende.
- Repère hors des 7 emplacements du §3.2, deux repères dans le même bloc, repère sur un jalon ou sur la frise.
- Carte enfermée dans un conteneur centré à coins arrondis.
- Frise ailleurs que dans l'ardoise, frise focusable, frise animée.

**Couleur et sens**
- Couleur officielle modifiée, réinterprétée, désaturée, éclaircie au survol ou « harmonisée ».
- Toute couleur du site dans les bandes 95°–175°, 330°–25° ou 26°–94° au-delà de 12 % de saturation (§2.1).
- **Jeton de statut numéroté** (`--statut-1`, `--statut-n2`, `--statut-niveau-3`…), sous quelque forme que ce soit.
- Valeur hexadécimale de statut écrite ailleurs que dans `tokens.css`.
- Polygone, pastille ou jalon **sans liseré 2 px**, ou avec un liseré aminci.
- État `interdit` **sans motif** ; état `autorisé` **avec** un motif.
- Statut encodé par la couleur seule, sans motif **et** sans libellé.
- Texte posé sur un aplat de statut, où que ce soit, y compris à l'impression.
- `--statut-*-encre` employé comme `color` de texte ; `--c-trace` employé dans un motif de statut.
- Danger météo présenté avec des couleurs, ou visuellement mêlé aux statuts.
- ZAPEF et massif rendus avec la même silhouette, ou fusionnés en un seul indicateur.
- Un statut périmé présenté comme courant ; un chiffre de la veille conservé en l'absence de donnée ;
  `level` 0 rendu comme « autorisé ».

**Contenu officiel**
- Libellé officiel paraphrasé, abrégé, tronqué ou « corrigé » — y compris `autorisé`/`interdite` uniformisés.
- Apostrophe typographique U+2019 de la note ZAPEF remplacée par une apostrophe droite, ou l'inverse pour
  `Niveau d'Accès`.
- Libellé inventé pour distinguer les `level` bruts 1 et 2, ou 3 et 4 — aucun n'est publié.
- Vocabulaire hérité des 5 crans : « niveau 3 », « vigilance orange », « accès réglementé », « risque sévère ».
- Consigne rédigée, déduite ou plausible ; intitulé « Consigne » affiché sans consigne ; « — » ou
  « non renseigné » dans l'emplacement de consigne.
- États hors niveau (`information non disponible`, `dispositif estival inactif`) présentés dans la légende
  officielle sans la séparation `SUR CE SITE`.
- Jalon ZAPEF rendu sur la carte sans géométrie établie (§4.1.e).

**Interaction**
- `outline: none` sans remplacement ; focus invisible sur une surface quelconque de la palette.
- Information révélée uniquement au survol ; infobulle porteuse de sens.
- Panneau que Échap ne ferme pas ; piège clavier ; cible < 44 px.
- Bouton désactivé sans explication accessible.
- Animation d'apparition au défilement, parallaxe, compteur animé, spinner, marqueur pulsant.
- Mouvement subsistant sous `prefers-reduced-motion: reduce`, y compris côté Leaflet.
- Motif de statut qui s'étire ou se densifie au zoom de la carte.

**Contenu éditorial**
- « Valider », « OK », « Soumettre », « En savoir plus », « Oups », « Désolé ».
- Emoji, exclamation, superlatif dans l'interface.
- Terme hors du vocabulaire fixe du §11.2.
- Bandeau de non-officialité absent d'une page affichant un statut.
- Attribution préfecture, OSM, DDTM, Météo-France ou EFFIS manquante.
- Bandeau de consentement aux cookies (il n'y a rien à consentir — §9 du brief).






