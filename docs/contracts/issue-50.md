# Contrat d'interface — Issue #50 — Corriger la sélection, l'épaisseur des liserés et la granularité du zoom de la carte

**Gelé le** 15 août 2026 · **Par** `lead-issue-cms` (chaîne #50) · **Statut** contraignant.

Cette issue ne touche **que le thème**. Aucune écriture dans `massifs-core`, aucune route REST, aucune
fonction de lecture nouvelle. Il n'y a donc **pas de contrat front↔back** au sens habituel.

La couture réelle de cette chaîne est **interne au thème** : `dev-front-cms` possède
`assets/js/carte/carte.js`, `dev-ux-cms` possède **tout** `assets/css/`. Les deux travaillent en
aveugle l'un de l'autre sur le **même défaut visuel**. Ce document est la frontière JS → CSS : les noms
de classes, la structure des panes et l'ordre d'insertion DOM. Une divergence d'un seul nom entre les
deux moitiés rend le cerne invisible **sans qu'aucune erreur ne paraisse** — c'est le mode de panne que
ce contrat existe pour fermer.

**Amont contraignant** : `design-system/MASTER.md` **v2.4** (§3.2, §9.2, §9.2.a, §10.2.a, §10.2.b, §12,
§17.1). La révision est **appliquée, jamais rediscutée**.

---

## Fonctions de lecture exposées par l'extension

**Aucune.** Cette issue n'ajoute, ne modifie et ne consomme aucune fonction PHP de `massifs-core`.
Les données lues restent celles de l'îlot JSON du contrat #7 §4 (`donnees.emprise.bbox`,
`donnees.emprise.zoom_max`), sans changement de forme.

## Routes REST

**Aucune.**

---

## 1. Décisions gelées

| # | Décision | Raison |
|---|---|---|
| **D1** | `zoomSnap: 0.25`, `zoomDelta: 0.5` dans les options de `L.map` | Le « z9 » de MASTER §9.2.a **décrit** ce que produisait `fitBounds` en pas entier ; il n'est pas normatif. Le contrat #9 F-11 nomme explicitement « `zoomSnap` fractionnaire » comme raison d'être du douzième niveau de pyramide. Le fond est monochrome et **sans toponyme** : une mise à l'échelle fractionnaire ne floute aucun texte. Mesuré : le cadrage desktop passe de z9 à **z 9,5**, 360 px de z8 à **z 8,75** — les bandes latérales de ~184 px disparaissent |
| **D2** | Palier par `Math.floor( carte.getZoom() )`, **deux comparaisons** : `< 10` → `departement` · `< 11` → `massif` · sinon `abords` | Partition **totale et sans trou sur les réels**, strictement équivalente au tableau §9.2.a sur les entiers. **`floor`, jamais `round`** : à z 9,5 — la valeur d'atterrissage la plus probable en desktop — `round` basculerait au palier massif et poserait de la peinture claire à une échelle à peine supérieure à z9, **exactement le défaut que la v2.4 existe pour empêcher**. Avec `floor`, l'encre posée correspond toujours à une échelle **inférieure ou égale** à l'échelle réelle : l'erreur est systématiquement du côté sûr |
| **D3** | Les bornes **10** et **11** sont écrites en **littéraux entiers** dans `carte.js` | L'interdit 7 du contrat #7 vise un **cadrage** (coordonnée, bbox, centre, zoom de vue) — il est rangé sous « Géométrie et jointure ». Une **borne de palier de présentation** n'en relève pas. Interdit 7 amendé en ce sens. **Ne jamais dériver de `donnees.emprise.zoom_max`** : cela couplerait une frontière de design mesurée en échelle-sol au plafond de simplification géométrique, deux choses qui ne coïncident aujourd'hui que par accident |
| **D4** | Les **deux** couches du cerne passent sous le pane des massifs. **Restructuration, pas renommage** | Aujourd'hui `contourMassifs` (calcaire) vit dans le pane des massifs (410) et est ajouté **après** la couche des massifs : c'est littéralement le défaut 1. La couche calcaire **change de pane** |
| **D5** | `--carte-cerne-clair: 0` fait le travail seul ; les deux couches existent **en permanence** | `stroke-width: 0` ne peint rien par spécification : ni demi-pixel, ni résidu d'anticrénelage. Interdits : créer la couche conditionnellement en JS (mettrait une notion d'épaisseur en JS), masquer par `display: none` (deux mécanismes pour un même fait) |
| **D6** | `zoomer()` supprimée ; `carte.zoomIn()` / `carte.zoomOut()` **sans argument** | Leaflet applique `zoomDelta` et borne lui-même. Les deux littéraux `±1` disparaissent. Sans cela, clavier et boutons Leaflet avanceraient de pas différents dès que `zoomDelta` vaut 0,5 |
| **D7** | Le cerne recouvert par un **massif voisin contigu** le long d'une frontière partagée est **assumé** | Panes 400 sous 410 : sur une frontière partagée, la moitié extérieure du cerne passe sous l'aplat du voisin. **Structurellement inévitable** sous la règle « aucun état ne recouvre un aplat de statut » ; le schéma du §9.2.a suppose « fond de carte » à l'extérieur. Assumé **par écrit**, jamais découvert en revue |
| **D8** | L'anneau de focus générique est **conservé** (contrat #7 A-16) | La v2.4 ne l'a pas rouvert. Voir la divergence enregistrée au §6 |
| **D9** | Noms gelés — MASTER ne les nomme pas : pane Leaflet `carte-cerne`, classe `.carte__pane--cerne` ; couches `.carte__cerne` (charbon) et `.carte__cerne-separateur` (calcaire) | Les noms sont la seule chose que les deux devs ne peuvent pas déduire l'un de l'autre |
| **D10** | `.carte__massif--courant` **reste posée par le JS** et **perd sa règle CSS** | C'est le curseur roving du contrat #7 §8.2. Aucune épaisseur n'est plus ajoutée au liseré du massif sélectionné (§9.2.a). **Ne pas la supprimer côté JS comme code mort** |
| **D11** | `carte.setMinZoom( carte.getZoom() )` — **REJETÉ** | (a) Ne supprime pas le vide visé : la pyramide couvre `zoom_min: 5`, dézoomer sous 9,5 tombe sur des tuiles **qui existent** ; le vrai vide est **latéral**. (b) **Dirimant** : le zoom d'ajustement est mesuré **une seule fois**, au montage ; Leaflet ne recadre jamais sur `resize`. Après une rotation, un passage à 200 % **après** chargement ou une réduction de fenêtre, `minZoom = 9,5` interdit de redescendre au 8,75 que le nouveau cadre exige — **le département entier devient inatteignable** : perte de contenu, WCAG 1.4.4 / 1.4.10, §8 du brief. (c) Au montage `zoom === minZoom` : Leaflet met « − » en `leaflet-disabled` dès la première image, un contrôle mort que §9.2 proscrit |

---

## 2. Frontière JS → CSS — ce que `carte.js` garantit

`dev-ux-cms` peut s'appuyer sur ce qui suit **sans rien vérifier**. `dev-front-cms` en est responsable.

### 2.1 Classes sur la racine `.carte`

`carte--prete` · `carte--panneau-ouvert` · **exactement une** de :

| Classe | Condition | Jetons redéfinis |
|---|---|---|
| `.carte--echelle-departement` | `floor(zoom) < 10` | les 4 épaisseurs, dont `--carte-cerne-clair: 0` |
| `.carte--echelle-massif` | `floor(zoom) < 11` | **aucun** — les valeurs sont celles de `:root` |
| `.carte--echelle-abords` | sinon | les 4 épaisseurs |

Posée **au montage, immédiatement après `fitBounds`**, puis remplacée à chaque `zoomend`. Table fermée,
jamais calculée, jamais dérivée d'une donnée serveur.

> **Pourquoi l'appel au montage est obligatoire** : `fitBounds` → `setView` → `_resetView` émet
> `zoomend` **avant** que `cabler()` n'enregistre l'écouteur. Sans l'appel explicite, le zoom initial
> n'est jamais vu.

> **Aucun écouteur nouveau** (MASTER §9.2.a) : l'unique `carte.on('zoomend', …)` existant appelle un
> handler qui fait **les deux** — pose du palier d'abord, garde de densité A-13 ensuite.

### 2.2 Panes et empilement

| Pane Leaflet | Classe posée par le JS | Contenu | `z-index` attendu du CSS |
|---|---|---|---|
| `carte-cerne` | `.carte__pane--cerne` | **un seul** `<svg>`, un `<g>`, **0 ou 2** `<path>` | **400** |
| `carte-massifs` | `.carte__pane--massifs` | `<svg>` portant le `<defs>` des trois `<pattern>` + les 25 `<path>` | **410** |

Panes créés dans cet ordre (secours si le `z-index` CSS n'arrive pas). Échelle interne de Leaflet
inchangée. **Un seul renderer** `L.svg({ pane: 'carte-cerne' })` partagé par les deux couches.

### 2.3 Couches du cerne

`path.carte__cerne` (charbon) **toujours insérée avant** `path.carte__cerne-separateur` (calcaire),
dans le même `<g>`. Les deux : `interactive: false`, aucune classe d'état, aucun `leaflet-interactive`.

### 2.4 Polygones

`path.carte__massif` + au plus une de `--autorise` `--interdit` `--indisponible` `--hors-saison`
`--non-publie` ; `.carte__massif--courant` sur le massif du curseur roving — **posée, sans règle CSS
attendue** (D10).

### 2.5 Ce que le JS ne fera jamais

Écrire un style, une couleur, un `setProperty`, une propriété personnalisée, un nom de jeton ; créer ou
détruire une couche selon le palier ; masquer le séparateur par `display: none` ; poser une épaisseur.
Seuls `classList.add/remove/toggle` et l'attribut `hidden` (contrat #7 §8.1, interdit 24).

### 2.6 Ce que `carte.css` doit fournir en retour

Les cinq jetons et les deux blocs de palier dans `tokens.css` ; `stroke-width` du cerne, du séparateur,
du liseré et du survol **uniquement par jeton** ; `fill: none` sur les deux couches du cerne ;
**suppression** de la règle `translate` sur `> svg > g` ; `z-index: 400` sur `.carte__pane--cerne`.

> **Échec par défaut = état valide.** Si le CSS de palier n'arrive pas, la carte se rend aux valeurs de
> `:root`, c'est-à-dire au palier massif, **conforme partout** (§9.2.a, règle de tenue 3).

---

## 3. Invariants — à ne jamais refermer en silence

### I-50.1 · Les deux couches du cerne sont `fill: none`, et le resteront

Le `<defs>` porteur des trois `<pattern>` a été **déplacé dans le `<svg>` des massifs** par `carte.js`
précisément pour rendre toute référence `fill: url(#…)` **intra-`<svg>`**. Donner un `fill` au cerne
rouvrirait une référence inter-racines SVG, réintroduirait le mode de panne que ce déplacement a fermé,
**et remplirait l'anneau** — ce qui recouvrirait l'aplat du massif : violation directe de la règle « aucun
état d'interaction ne recouvre un aplat de statut ».
Leaflet écrit `fill="#3388ff"` en attribut de présentation sur ces `<path>` : **le `fill: none` du CSS
est ce qui le neutralise**, il n'est pas décoratif.

### I-50.2 · L'ordre des `addData` porte la conformité

Avec un renderer partagé, l'ordre DOM final n'est **pas** fixé par `addTo` mais par l'ordre des
`addData` : `clearLayers()` retire les `<path>` du `<g>` à chaque sélection et `addData` les ré-append.
Dans `selectionner()`, l'ordre est donc **impératif** :

```
cerne.clearLayers();  cerneSeparateur.clearLayers();
cerne.addData(…);     cerneSeparateur.addData(…);
```

Inverser les deux dernières lignes remet le calcaire **sous** le charbon et reproduit un anneau
**invisible sur le fond de carte** — 1,07:1, §10.2.b. C'est le défaut de la v2.3, à l'identique.

### I-50.3 · `stroke-linejoin: round` déclaré sur les deux couches

Leaflet pose déjà l'attribut de présentation, mais le défaut SVG est `miter` avec
`stroke-miterlimit: 4` : une règle écrite ailleurs, ou un `lineJoin` retiré des options par un refacto,
produirait des pointes de **jusqu'à 4 × 13 = 52 px** sur les angles aigus des massifs filamenteux.
Défense en profondeur, coût nul, aucun littéral numérique.

### I-50.4 · `--statut-lisere-epaisseur` est l'épaisseur **hors carte**

Pastille, jalon, légende, liste, panneau, portail, impression. Elle reste à **2 px** et **ne devient
jamais variable**. Sur la carte, l'épaisseur suit le palier (`--carte-lisere`). L'employer sur un
`stroke` de carte est un défaut.

### I-50.5 · Le survol est le seul trait qui consomme de l'aplat

Il est **centré** sur le tracé. Autorisé par la règle de tenue 1 du §9.2.a : transitoire, pointeur seul
(`@media (hover: hover)`), **ne porte aucune information**. Il vaut **1,5 × le liseré du palier** —
un **rapport**, pas trois nombres.

---

## 4. Comptes et empreintes

| | Avant | Après |
|---|---|---|
| `tokens.css` — `:root` | 111 | **116** |
| `tokens.css` — fichier entier | 120 | **133** |
| sha256 de `tokens.css` | `5ad802a3…` | **ré-épinglé après écriture** |

Méthode de comptage **opposable** (plusieurs jetons partagent une ligne, une ancre `^` fausserait le
compte) :

```
grep -oE '(^|[[:space:]{;])--[a-z0-9-]+[[:space:]]*:' tokens.css | wc -l
```

`tokens.css` est la **transcription verbatim** du bloc normatif de MASTER §12 — éprouvé octet pour
octet par `tests/rendu/recette-rendu.mjs`. Il se transcrit, il ne s'édite pas à la main.

---

## 5. États spéciaux

Aucun état de statut n'est créé, modifié ni supprimé par cette issue. Les cinq états
(`autorise`, `interdit`, `indisponible`, `hors_saison`, `non_encore_publie`) et leurs motifs sont
**inchangés**. La table état → classe du contrat #7 §8.2 n'est pas rouverte.

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | inchangé (contrat #6) | inchangé — aplat + hachure descendante |
| `hors_saison` | inchangé | inchangé — aplat nu |
| `donnee_perimee` | inchangé — bandeau de niveau page | hors périmètre #50 |
| `couche_effis_indisponible` | inchangé | hors périmètre #50 |

**Le motif reste entier à tous les paliers** : l'information n'est jamais portée par la couleur seule.

---

## 6. Divergences enregistrées

### V-50.1 · L'anneau de focus des polygones se rend en rectangle de boîte englobante

`layout.css` pose l'anneau en `:where(…[tabindex]):focus-visible` ; les `<path>` portent `tabindex`.
Sur un `<path>` SVG, Chrome dessine l'`outline` autour de la **boîte englobante** : sur Regagnas à z9,
un rectangle de 94 × 55 px. Le `box-shadow` du halo, lui, **ne se rend pas** sur une forme SVG dans
Chrome.

Deux conséquences :

- **le « cadre noir » observé dans l'issue n'est PAS l'anneau de focus** — `:focus-visible` ne s'arme pas
  au clic souris. C'était le contour charbon décalé, que cette issue supprime ;
- **au parcours clavier**, le rectangle englobant restera visuellement plus fort que le cerne vu à
  1,5 px au palier département.

Conforme à A-16 et au §9.2.a. **Non corrigé ici** : le retirer imposerait un `outline: none` dont le
seul remplaçant serait un tracé créé par le JS — si la duplication échoue, le focus devient invisible et
WCAG 2.4.7 tombe. **Remonté à `lead-design-cms`** : MASTER ne spécifie aucun traitement de focus propre
au SVG, et la v2.4 ne l'a pas rouvert. Hors de cette chaîne.

### V-50.2 · Bandes latérales résiduelles

`zoomSnap: 0.25` ramène le trou sans tuiles de ~310 px à ~110 px au total. Le reste (~97 px/côté) est
**irréductible** : c'est l'écart entre le rapport 1,126 de l'emprise et celui de la toile. Aucun réglage
de zoom ne le supprime, et le supprimer par recadrage sortirait le département de l'écran (§5.2 du
brief). Aucun `maxBounds` n'est posé — le panoramique reste libre ; **hors empreinte de cette issue**.

---

## 7. Interdits

- Le thème n'appelle jamais une source externe ni une fonction d'ingestion.
- Le thème ne calcule jamais une règle métier (saison, péremption, formatage de niveau).
- L'extension n'émet jamais de HTML de présentation publique.
- **`carte.js` n'écrit aucun style** : ni `element.style`, ni `setProperty`, ni hexadécimal, ni nom de
  jeton, ni propriété personnalisée. `classList` et `hidden`, rien d'autre.
- **Aucun littéral** de couleur, d'espacement, de durée, de corps **ni d'épaisseur de trait de carte**
  dans `carte.css`. Les épaisseurs vivent au §12 de MASTER, donc dans `tokens.css`, et nulle part
  ailleurs.
- **L-12 disparaît.** Ne pas renuméroter L-11 / L-13 / L-14 / L-15 : ce sont des identifiants cités
  ailleurs dans le fichier.
- Aucune requête vers un domaine tiers. Aucun `@import`, aucun `url()` d'origine externe.
- `maxZoom` reste `donnees.emprise.zoom_max` (= 11) — interdit 6 du contrat #7 intact.
- Aucune écriture dans `design-system/MASTER.md` ni dans `wp-content/plugins/massifs-core/**`.

---

## 8. Arbitrages

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| **A-50.1** | Le brainstorm remontait 4 questions comme **bloquantes** | **Aucune n'est bloquante** | Une question bloquante est un **fait de domaine** que le brief ne tranche pas (libellé officiel, couleur, consigne). Les quatre étaient des arbitrages de conception ou de contrat, donc de mon ressort. Tranchées en D1, D3, D7, D8 |
| **A-50.2** | Zoom initial fractionnaire vs « z9 » de MASTER | **Fractionnaire** (D1) | Le corps de l'issue demande lui-même d'adoucir `zoomSnap`/`zoomDelta` et que « le cadrage initial remplisse le cadre ». F-11 anticipe explicitement le `zoomSnap` fractionnaire |
| **A-50.3** | Interdit 7 du contrat #7 (« aucun zoom en dur ») vs MASTER §9.2.a (« la borne du palier est un entier de zoom ») — **contradiction réelle entre deux textes gelés** | **MASTER l'emporte, interdit 7 est amendé** (D3) | Interdit 7 est rangé sous « Géométrie et jointure » : il protège le **cadrage**, pas les seuils de présentation. L'amendement **précise sa portée, il ne l'affaiblit pas** |
| **A-50.4** | `setMinZoom` recommandé par le brainstorm, rejeté par le leaddev | **Rejeté** (D11) | L'argument (c) du leaddev est dirimant : perte de contenu au redimensionnement, WCAG 1.4.4 / 1.4.10. J'avais initialement recommandé de l'adopter ; le plan m'a corrigé sur un fait vérifiable |
| **A-50.5** | MASTER §17.1 n'amende que **A-9 et A-16** du contrat #7 | **Périmètre d'amendement élargi** | Les §8.2 (table de classes) et §8.4 (exigences 2, 5, 6, 15) du contrat #7 **restatent la même règle obsolète**. Les laisser ferait lire l'implémentation conforme comme une infraction — exactement le motif de l'amendement F-1 (`31d7dbd`). §17.1 était **sous-dimensionné** ; signalé à `lead-design-cms` |
| **A-50.6** | Le compte « 111 propriétés » et le sha256 de `tokens.css` sont aussi épinglés **hors de mon empreinte** | **Non corrigé, remonté** | `tests/rendu/recette-rendu.mjs` l. 1415 et 1424, et les contrats #11, #21, #23. **La recette du lot échouera** tant que l. 1415/1424 ne sont pas reprises. Appartient à l'orchestrateur |

---

## 9. Assertions de recette — à prouver **dans le navigateur**, pas dans une suite de tests

C'est la clause centrale de cette issue : le défaut a passé une revue et une recette automatisée sans
être vu, **parce que personne n'a regardé les pixels** (MASTER §17.1, ligne 6).

Sur **Regagnas** (`regagnas`), http://localhost:3002, Chrome :

1. **z 9,5 (cadrage initial desktop), Regagnas sélectionné** — l'**aplat vert et son motif restent
   entiers** ; **aucun pixel calcaire sur la carte** (`--carte-cerne-clair` calculé = `0`) ; le halo
   charbon fait ~2,25 px hors de la forme et **ne fusionne pas d'un filament au suivant** ; le fond de
   carte reste visible autour. **C'est l'assertion qui a manqué à la v2.3.**
2. **Bandes latérales** — plus de bande vide de ~184 px ; `getZoom()` = **9.5** et la racine porte
   **`carte--echelle-departement`** (preuve du `floor`, et non du `round`).
3. **z 10 et z 10,5** — `carte--echelle-massif` aux **deux** ; anneau et séparateur visibles ; aplat intact.
4. **z 11** — `carte--echelle-abords` ; **aucune pointe** sur les angles aigus (`stroke-linejoin`).
5. **Survol** — liseré à 1,5 × celui du palier, **sans changement de teinte**, retour au repos.
6. **Clavier** — `+`/`−` avancent de 0,5 et s'accordent avec les boutons Leaflet ; Échap ferme le
   panneau et **le cerne reste** ; focus visible.
7. **Cible de clic** — cliquer dans l'anneau, hors du polygone, **n'ouvre pas** le panneau
   (preuve de `interactive: false`).
8. **Pas de hachure constant** à z 9 / 9,5 / 10 / 10,5 / 11 — A-13 **étendu aux crans fractionnaires**.
9. **360 px** et **200 % de zoom texte** — département entier cadré, aucun défilement horizontal.
10. **`forced-colors: active`** — cerne `CanvasText`, séparateur `Canvas`, motifs préservés.
11. **JS désactivé** — repli statique et attribution intacts, **aucune régression** (F-1 / F-2).

> **Note A-13** : vérifié dans Leaflet 1.9.4, `SVG._update()` écrit `width`/`height` **et** `viewBox`
> depuis la même valeur et positionne le `<svg>` par un `translate3d` **sans facteur d'échelle**. Le
> rapport vaut donc 1 à tout zoom, entier ou fractionnaire : le chemin nominal de la garde **reste un
> no-op** à z 9,5. Le zoom fractionnaire n'échelonne que la couche de tuiles, dans son propre conteneur.
