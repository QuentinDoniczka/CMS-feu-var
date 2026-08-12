# Contrat d'interface — Issue #28 — Rétablir les en-têtes de colonne dans l'arbre d'accessibilité en mode cartes

**Chaîne** `lead-issue-cms` #28 · **Lot** « Dette — lot 1 » (milestone #7) · **Date** 12 août 2026
**Labels** `a11y`, `design` · **Branche** `main` (projet mono-branche, arbre de travail partagé)

> **Nature de ce contrat.** L'issue #28 ne touche **que le thème**, et dans le thème **qu'une feuille de
> style**. Il n'y a donc eu **ni plan back, ni réconciliation entre deux plans** : ce document ne gèle pas
> une frontière extension ↔ thème, il gèle des **décisions de portée CSS** et les **invariants** qu'elles
> imposent aux chaînes futures. Les rubriques « Fonctions de lecture », « Routes REST », « États spéciaux »
> et « Chaînes fournies par le serveur » sont **sans objet** et sont conservées vides pour que l'absence
> soit lisible, jamais déduite.

---

## Empreinte d'écriture — exhaustive

| Fichier | Nature |
|---|---|
| `wp-content/themes/massifs/assets/css/composants.css` | Une règle supprimée, une section ajoutée, un commentaire réécrit |
| `docs/contracts/issue-28.md` | Ce document |

**Rien d'autre.** En particulier, et parce que deux chaînes (#24, #25) écrivent dans le même arbre au même
moment : `print.css`, `layout.css`, `tokens.css`, `functions.php`, tout gabarit de `templates/`, `tests/`,
`index.php`, `page.php`, `404.php` sont **hors empreinte** et n'ont pas été touchés.

---

## Fonctions de lecture exposées par l'extension

**Sans objet.** Cette issue ne franchit pas la frontière extension → thème. Aucune fonction `massifs_…`
n'est appelée, ajoutée ni modifiée.

## Routes REST

**Sans objet.** Aucune route n'est ajoutée, consommée ni modifiée.

## États spéciaux

**Sans objet pour cette issue.** Le correctif est un changement de **portée de masquage** sur un élément de
structure (`thead`) ; il ne rend, ne compose et n'interprète aucun état de statut. Les quatre états du
tableau ci-dessous restent **exactement** ce que les contrats #5, #6 et #22 en ont fait, et cette chaîne
n'y touche pas.

| État | Émis par le serveur | Rendu par le thème | Touché par #28 |
|---|---|---|---|
| `information_indisponible` | inchangé (contrat #5) | inchangé (contrat #22) | **non** |
| `hors_saison` | inchangé (contrat #5) | inchangé (contrat #22) | **non** |
| `donnee_perimee` | inchangé (contrat #5) | inchangé (contrat #22) | **non** |
| `couche_effis_indisponible` | non encore livré | non encore livré | **non** |

## Chaînes fournies par le serveur

**Sans objet.** Aucune chaîne n'est composée, reformulée ni déplacée. Les quatre libellés d'en-tête de
colonne — `Massif`, `Niveau d'Accès`, `ZAPEF`, `Fraîcheur` — sont **déjà dans le gabarit**
(`templates/parts/liste-statuts.php` l. 223-226, empreinte gelée de la chaîne #6) et cette chaîne ne les
lit que pour les **assertions de recette**. Le CSS n'en produit aucune.

---

## Le défaut, et ce que le correctif fait exactement

**Mesuré au CDP `Accessibility.getFullAXTree`, jamais déduit du balisage.** À 320 px, la liste du jour
exposait `columnheader × 0` contre 4 à 900 px : `.liste-statuts__tableau thead { display: none }`
(`composants.css` l. 277-279, règle de **base**, hors requête média) ne rognait pas l'en-tête, il
**retirait de l'arbre d'accessibilité** le `thead`, son `tr` et ses quatre `th` — **rôles ARIA explicites
compris**. Un rôle explicite survit à un changement de `display` ; il ne survit pas à `display: none`.

Le correctif **déporte** le `thead` hors cadre au lieu de le retirer : hors du flux visuel, présent dans le
document et dans l'arbre. Le rendu des cartes est inchangé au pixel.

### Pourquoi ce correctif, et non un autre — l'argument qui le porte

`templates/parts/liste-statuts.php` pose **`role="table"` en dur** (l. 217), et ce gabarit est **hors de
l'empreinte de cette chaîne**. À 320 px, le site affirmait donc « ceci est un tableau », exposait 25 `row`,
25 `rowheader`, 63 `cell` — et **zéro colonne**. Une structure qui se déclare tableau sans en-têtes de
colonne est un **tableau défectueux**, pas un tableau simplifié.

Les deux états cohérents sont : (a) tableau complet aux deux points de rupture, (b) ne pas se déclarer
tableau à 320 px. **(b) est inatteignable depuis le CSS** — aucune règle CSS ne retire un rôle ARIA
(interdit 24 du contrat #6, tenu par construction). Dans l'empreinte donnée, **(a) est la seule sortie
cohérente**.

### Argument de non-régression le plus fort

À 320 px, le tableau est **déjà** en `display: block`, avec `tbody`, `tr`, `th` et `td` tous blockifiés :
toute la sémantique tabulaire y repose **déjà** exclusivement sur les rôles ARIA explicites du contrat #6.
Passer le `thead` de `none` à `block` **n'introduit aucune divergence nouvelle** entre `display` et rôle —
il aligne le `thead` sur ses six frères.

---

## Comptes ARIA cibles — la checklist de l'issue était fausse

L'issue #28 demandait « `columnheader` 4 **sans perte** des `rowheader` 25, `row` 25, `cell` 63 ». C'est
arithmétiquement impossible : restaurer le `thead` dans l'arbre **ajoute une rangée et un groupe de
rangées**. **La checklist telle qu'écrite aurait validé un échec.**

| Rôle | Avant (320 px) | **Après (320 px)** | Après (900 px) |
|---|---|---|---|
| `table` | 1 | **1** | 1 |
| `caption` | 1 | **1** | 1 |
| `rowgroup` | 1 | **2** | 2 |
| `row` | 25 | **26** | 26 |
| `columnheader` | **0** | **4** | 4 |
| `rowheader` | 25 | **25** | 25 |
| `cell` | 63 | **63** | **75** — voir ci-dessous |

- **`row × 25` après correction est un ÉCHEC. `rowgroup × 1` après correction est un ÉCHEC.**
- Noms accessibles exigés des quatre `columnheader` : `Massif`, `Niveau d'Accès`, `ZAPEF`, `Fraîcheur` —
  garde contre un `thead` restauré mais vidé. **Mesurés en capitales** (`MASSIF`, `NIVEAU D'ACCÈS`,
  `ZAPEF`, `FRAÎCHEUR`) : le `text-transform: uppercase` du §6 remonte dans le nom accessible.
- **Delta attendu et non fautif** : `StaticText` +4 à 320 px (les quatre libellés d'en-tête).

> ### Correction apportée à ce contrat après mesure — `cell` n'est PAS identique aux deux largeurs
>
> **Première rédaction de ce contrat : `cell` 63 à 320 px comme à 900 px, et « le sous-ensemble
> `{table, caption, rowgroup, row, columnheader, rowheader, cell}` est strictement identique à 320 px et à
> 900 px ».** C'était **faux sur `cell`**, et la mesure l'a établi : **63 à 320 px, 75 à 900 px**.
>
> **Cause, mesurée et étrangère à #28** : `.liste-statuts__cellule:empty { display: none }` (§7) retire les
> **12 cellules littéralement vides** en mode cartes, que le §11 rétablit en `table-cell` — `75 = 63 + 12`.
> Le scénario 19 relevait déjà « 75 dont 12 littéralement vides », **avant comme après** le correctif. Un
> A/B au même instant, même DOM, même chargement, donne à 900 px des comptes **identiques avec et sans
> #28** : cette asymétrie **préexiste** et n'est ni causée ni aggravée par cette issue. La corriger
> sortirait de l'empreinte (règle `:empty` du §7, contrat #22, invariant I-8).
>
> **Énoncé le plus fort, corrigé** : après correction, le sous-ensemble
> `{table, caption, rowgroup, row, columnheader, rowheader}` — **`cell` exclu, et lui seul** — est
> **strictement identique à 320 px et à 900 px**.
>
> **Conséquence opérationnelle pour S-3** : une assertion `cell === 63` écrite pour **les deux** largeurs
> **échouerait à 900 px**. Écrire `cell === 63` à 320 px et `cell === 75` à 900 px.

---

## Ce que la mesure prouve, et ce qu'elle ne prouve pas

**À écrire sans arrondi dans tout rapport qui cite cette issue.**

`Accessibility.getFullAXTree` prouve que le **nœud** `columnheader` existe et n'est pas `ignored`. Il ne
prouve **rien** sur l'**association** en-tête ↔ cellule : celle-ci est calculée par le moteur et exposée
aux technologies d'assistance via les **API plateforme** (IAccessible2 / AX API), et **n'apparaît dans
aucun champ de l'instantané CDP**.

> **Énoncé exact du résultat : « le nœud `columnheader` est rétabli et l'association est rendue
> possible ».** Jamais « l'association est rétablie ». Jamais « conforme AA ».

**Cette issue est la cible n° 1 du contrôle humain au lecteur d'écran** exigé par le brief §8 (« un
contrôle humain final au lecteur d'écran, documenté sur la page Accessibilité ») et repris au §12. **Ce
contrôle n'a jamais été exécuté sur ce projet à ce jour.** L'issue #28 **ne clôt pas** cette ligne de DoD
et ne doit jamais être présentée comme la clôturant.

**Correction factuelle sur l'énoncé de l'issue** : la seconde ligne de DoD qu'elle cite — « structure de
tableau/titres accessible sur tout point de rupture » — **n'existe pas dans le brief §8**. Le §8 porte :
structure de titres, liens d'évitement, clavier, contrastes, jamais la couleur seule, alternatives, zoom
200 %, **pas de défilement horizontal à 320 px**, vérifications automatisées sans erreur bloquante, et le
contrôle humain. La justification honnête de #28 est : **dette d'accessibilité relevée en revue, cible n° 1
du futur contrôle humain** — pas une ligne de DoD inventée.

---

## Interdits

1. Le thème n'appelle jamais une source externe ni une fonction d'ingestion. *(inchangé)*
2. Le thème ne calcule jamais une règle métier. *(inchangé)*
3. L'extension n'émet jamais de HTML de présentation publique. *(inchangé)*
4. **Ne jamais retirer la garde `@media screen`** du §7 bis de `composants.css` (voir I-12). C'est le seul
   endroit de cette issue où l'erreur serait grave.
5. **Ne jamais ajouter d'`inset-*`** au déport : les insets `auto` sont ce qui conserve la **position
   statique** et garantit le zéro delta de mise en page.
6. **Ne jamais employer `transform`** pour déporter : il crée un bloc conteneur, contribue à la région de
   défilement et ne borne pas la largeur. Le patron `.lien-evitement` de `layout.css` (`translateY(-100%)`)
   est un patron **révélé au focus**, d'intention différente, et **n'est pas réutilisable ici** : appliqué
   au tableau, il ferait remonter l'en-tête **par-dessus le `h2`**, visible.
7. **Ne jamais employer** `visibility: hidden`, `content-visibility: hidden`, `display: contents`,
   `opacity: 0`, `text-indent`, `font-size: 0` pour ce masquage.
8. **Ne jamais toucher `content: attr(data-etiquette)`** (§7, l. 308-317) tant que la preuve humaine au
   lecteur d'écran n'est pas faite. Voir « issues de suivi », S-1.
9. **Ne jamais déplacer le §7 bis après le §11** (voir I-14).
10. **Ne jamais renuméroter les sections 8 à 12** de `composants.css` : `print.css` et les contrats #22 et
    #23 les citent par leur numéro.
11. **Ne jamais écrire de littéral de longueur** dans ce bloc — l'en-tête du fichier ferme la série L-1 à
    L-8, et MASTER ne spécifie **aucun** patron de masquage qui autoriserait un L-9 de ce type.
12. Aucun jeton créé, aucune valeur hexadécimale, aucun rôle ARIA ajouté, aucun gabarit modifié.

---

## Invariants opposables à toute chaîne future touchant le CSS du thème

Ils prolongent I-1 à I-10 du contrat #22, qu'ils ne remplacent pas.

| # | Invariant |
|---|---|
| **I-11** | `composants.css` ne retire **jamais** un élément de tableau de l'arbre d'accessibilité — ni `display: none`, ni `visibility: hidden`, ni `content-visibility: hidden`, ni `display: contents`. Le mode cartes **déporte**, il ne supprime pas. Un rôle ARIA explicite survit à un changement de `display` ; il ne survit à **aucun** de ces quatre. |
| **I-12** | Tout déport hors cadre écrit dans `composants.css` est **gardé par `@media screen`** et **réinitialisé à partir de `37.5rem`**, **`clip-path` compris**. La garde est **porteuse** : `massifs-composants` est enfilée en `media="all"` (`functions.php` l. 237) et `print.css` ne réinitialise que `display` (l. 124-126). Sans elle, `position: absolute` **blockifie** le `thead` — son `display` calculé devient `block` quoi que déclare `print.css`, sans qu'aucune spécificité n'entre en jeu — et l'en-tête répété du §13 **disparaît de la feuille, A4 comme A5**. |
| **I-13** | La protection contre le défilement horizontal d'un déport est portée par **la contrainte de taille + `overflow: hidden`**, **jamais** par `clip-path`, qui n'écrête que la peinture et le test de pointage. `overflow` exige `display: block` : sur une boîte interne de tableau, il est sans effet. |
| **I-14** | Un déport est écrit **par paire** — bloc de déport + bloc de réinitialisation — dans une **section propre**, **avant** le §11. La réinitialisation ne déclare **pas** `display` : il vient du §11, à spécificité **égale** (0,1,1), et ne gagne que par sa **position dans la source**. Déplacer la section après le §11 détruirait les vraies colonnes à toutes les largeurs. |
| **I-15** | Un déport ne prend **jamais** une taille nulle ni un littéral de longueur. `--esp-3xs` est le jeton retenu ; le précédent d'un jeton d'espacement consommé comme **dimension** est au §12 (reconstruction du pointillé sous `forced-colors`). |

### Révision de I-5 (contrat #22) — amendement, et non dérogation ponctuelle

I-5 dit aujourd'hui : « Toute règle de liste ajoutée à `composants.css` sous `@media (min-width: 37.5rem)`
doit être répliquée dans `print.css`, sinon elle disparaît sur A5. » Pris **à la lettre**, il obligerait à
répliquer dans `print.css` une réinitialisation qui, en média `print`, annulerait des déclarations
**inexistantes** — et à écrire dans un fichier hors empreinte. Une dérogation ponctuelle laisserait la
faille ouverte : la chaîne suivante relirait I-5 à la lettre.

> **I-5 (rév. #28)** — `print.css` restaure la liste en colonnes **sans aucune requête de largeur**. Toute
> règle de liste ajoutée à `composants.css` sous `@media (min-width: 37.5rem)` **et non gardée par
> `@media screen`** doit être répliquée dans `print.css`, sinon elle disparaît sur A5.
>
> Une règle **gardée `screen`** est **hors du champ** de I-5 : elle est neutre à l'impression **par
> construction**. En contrepartie, **trois obligations** : **(a)** elle est écrite **par paire** — déport +
> réinitialisation — dans une **section propre**, jamais insérée dans le bloc `@media (min-width: 37.5rem)`
> du §11 ; **(b)** sa neutralité à l'impression est **mesurée, pas affirmée**, par le scénario 18 sur **A4
> ET A5**, sur `display`, `position` **et** `clip-path` ; **(c)** la garde `@media screen` ne peut être
> retirée sans repasser par un contrat.

**Justification.** I-5 protège contre une règle qui **agit** à l'écran et **manque** au papier. Une règle
gardée `screen` n'agit pas au papier, donc rien n'y manque ; la répliquer y **introduirait** ce qu'elle
prétend annuler. La faille réelle que I-5 ne couvrait pas est **ailleurs** : une règle **non gardée** dont
un effet — ici `position`, par blockification — **ne peut pas être annulé** par `print.css`. La clause (b)
transforme la dérogation en **obligation de mesure**. L'invariant n'est pas affaibli, il est précisé.

**Cet amendement est proposé par la chaîne #28 ; la chaîne #22 est close et ne peut plus le porter.** Il
appartient à l'orchestrateur de le ratifier ou de le renvoyer.

### Amendement de l'arbitrage 7 du contrat #22

L'arbitrage 7 (`docs/contracts/issue-22.md` l. 136) énonce, pour les cartes empilées :
« `thead { display: none }` en mode cartes ». **Il est amendé** : le mode cartes **déporte** le `thead`, il
ne le retire pas. Sans cet enregistrement, `review-cms` signalera un **faux défaut** au prochain lot.

---

## Arbitrages

| # | Point | Décision | Raison |
|---|---|---|---|
| **1** | Quel mécanisme de masquage | **Déport hors cadre** : `position: absolute` sans `inset-*`, taille `--esp-3xs`, `overflow: hidden`, `clip-path: inset(50%)`, sur `display: block` | Seul mécanisme qui retire du **flux visuel** sans retirer de l'**arbre**. `visibility`, `content-visibility` et `display: contents` retirent de l'arbre — c'est le mal soigné. `display: contents` était **déjà** refusé par l'arbitrage 7 du contrat #22 (mapping d'accessibilité inégal selon les moteurs) et ce refus **tient** |
| **2** | Portée de la règle : base nue ou gardée | **`@media screen`, non négociable** | `massifs-composants` est en `media="all"` (`functions.php` l. 237) : une règle de base s'applique **au papier**. `print.css` ne réinitialise que `display` (l. 124-126), et cela **ne suffirait pas** : `position: absolute` **blockifie**, donc `table-header-group` est court-circuité **sans qu'aucune spécificité n'entre en jeu**. L'en-tête répété du §13 ne serait pas rogné, il **disparaîtrait**, A4 comme A5. Régression sur un livrable commité de la chaîne #22, dans un fichier hors empreinte |
| **2 bis** | Pourquoi pas `@media screen and (max-width: …)` | **Rejeté** | Exigerait `37.4375rem` — **littéral fractionnaire inventé**, hors de la liste des valeurs structurelles de l'en-tête du fichier — ou `37.5rem`, qui **chevauche** le §11 à exactement 600 px et laisserait le masquage actif sur ce point, donc une réinitialisation resterait requise. Zéro gain, un littéral de plus |
| **2 ter** | Pourquoi pas la seule réinitialisation dans `@media (min-width: 37.5rem)` | **Insuffisant** | Une A5 moins les 12 mm du `@page` fait ≈ 124 mm ≈ **468,7 px ≈ 29,3 rem**, donc **sous** 37,5 rem : la requête ne s'applique pas. (A4 : 186 mm ≈ 703,1 px ≈ 43,9 rem — elle s'y applique **par chance**, exactement l'argument de l'arbitrage 12 du contrat #22.) C'est le trou que I-5 décrit |
| **3** | Dimension du déport | **`var(--esp-3xs)` = 2 px** | **Pas `1px`** : ce serait un neuvième littéral **sans source**, MASTER ne spécifiant **aucun** patron de masquage — l'en-tête du fichier ferme la série L-1 à L-8, tous « reproduits verbatim de MASTER §8.1 ». **Pas `0`** : les boîtes de dimension nulle sont sujettes à l'élagage de l'arbre, et le harnais filtre `! n.ignored` — le défaut reviendrait **sous un autre nom**, mesuré comme réparé. Précédent d'un jeton d'espacement consommé en dimension : §12, `--esp-2xs` sur la reconstruction du pointillé |
| **4** | Qui porte le « pas de défilement horizontal à 320 px » (brief §8) | **La contrainte de taille + `overflow: hidden`**, jamais `clip-path` | Aucun ancêtre du `thead` n'est positionné : son bloc conteneur est l'**ICB**, donc son débordement rejoint **directement** la région de défilement du document. Sans `overflow: hidden`, les quatre `th` en `display: table-cell` engendrent une **boîte de tableau anonyme** dimensionnée en shrink-to-fit contre 2 px, donc à sa largeur min-content, qui déborde et **propage**. `MASSIF · NIVEAU D'ACCÈS · ZAPEF · FRAÎCHEUR` en condensée capitale espacée frôle les 320 px : le résultat est **indécidable a priori**, et on ne parie pas dessus. `clip-path` n'écrête que la **peinture** et le **test de pointage** |
| **5** | `display: block` dans le déport | **Écrit** | Rend `overflow` **applicable** — il est sans effet sur une boîte interne de tableau — et interdit la boîte de tableau **anonyme** qui naîtrait d'un `table-header-group` dans un tableau en `display: block`. Aligne le `thead` sur `tbody`/`tr`/cellules du §7 |
| **6** | Emplacement de la section | **§7 bis, entre le §7 et le §8**, jamais après le §11 | **L'ordre est porteur** : le §11 redonne `display: table-header-group` avec le **même sélecteur**, spécificité **égale** (0,1,1) ; les requêtes média n'ajoutent **aucune** spécificité, seul l'ordre source départage. Placé après le §11, le `display: block` l'emporterait **à toutes les largeurs** et détruirait les vraies colonnes. C'est la dépendance d'ordre qui liait **déjà** l'ancien `display: none` au §11 : elle est ici **écrite, pas créée**. « 7 bis » plutôt qu'une renumérotation parce que `print.css` et les contrats #22 et #23 citent les sections **par leur numéro** |
| **7** | Contenu de la réinitialisation | `position: static`, `clip-path: none` **porteuses** ; `inline-size: auto`, `block-size: auto`, `overflow: visible` **défensives**. **Aucun `display`** | `clip-path` s'applique à **tous** les éléments, `table-header-group` compris : sans `clip-path: none`, la ligne d'en-tête serait dans l'arbre **et invisible à l'écran** de 600 px à l'infini — **le seul défaut silencieux possible de ce bloc**. `position: static` évite qu'un `absolute` survivant blockifie le `thead` et écrase le §11. Les trois autres sont inertes **aujourd'hui**, mais leur inertie dépend d'un `display` posé par une **autre section** : une déclaration ne doit pas reposer sur ce couplage tacite. Pas de `display` : il vient du §11, plus bas dans la source, à spécificité égale |
| **8** | La double annonce au lecteur d'écran | **Acceptée et consignée**, non corrigée dans cette chaîne | Rétablir les 4 `columnheader` ramène l'annonce d'en-tête **en plus** de l'étiquette peinte par `attr(data-etiquette)`. Trois raisons de l'accepter : elle est **confinée à la navigation tabulaire** (en lecture linéaire, l'en-tête de colonne n'est pas annoncé) ; le surcoût est de **+4 libellés, une seule fois**, en tête de tableau — la duplication par cellule **préexistait** et n'est pas modifiée ; et surtout **son mode de défaillance est la verbosité, quand celui de l'absence est la perte d'information**. Sur un site à finalité de sécurité, on choisit la verbosité |
| **9** | `content: attr(data-etiquette) / ""` — texte alternatif vide | **Écartée de cette chaîne**, déposée en S-1 | Elle supprimerait la double annonce **sans changer un pixel**, mais transférerait **100 % du contexte** sur l'association en-tête ↔ cellule — c'est-à-dire sur exactement ce que le CDP **ne sait pas mesurer** et que **personne n'a encore vérifié** au lecteur d'écran. Son bénéfice et son risque sont **au même endroit**. Elle se livre **après** la preuve humaine, jamais avant. Là où elle échouerait, l'utilisateur entendrait « Accès au massif autorisé » **sans savoir de quelle colonne** — strictement pire que l'état actuel, et **invisible à tout outil automatisé** |
| **10** | Porter l'étiquette dans le DOM (`<span>` réel au gabarit) | **Refusée dans cette chaîne**, déposée en S-2 | C'est la correction **structurelle** — elle traite la cause, supprime la dépendance au contenu généré, supprime la double annonce sans pari, et supprime l'invariant fragile I-8. Mais `templates/parts/liste-statuts.php` est **hors empreinte**, gelé par le contrat #6, et **deux chaînes écrivent dans le même arbre au même moment** : y écrire est exactement l'écrasement mutuel contre lequel la disjonction protège |
| **11** | Le §7 bis écrit un patron de masquage **sans source normative** | **Assumé et signalé** (S-4) | `MASTER.md` ne spécifie **aucun** patron de masquage accessible. Même situation que l'arbitrage 11 du contrat #22 pour `.legende__avertissement` : on ne peut pas ne pas choisir, donc on choisit **et on le déclare**. Question ouverte à `lead-design-cms` : le patron entre-t-il au §9 ou au §12 de `MASTER.md` ? |

---

## Ce qui casse hors empreinte — à ordonnancer par l'orchestrateur

`tests/rendu/recette-rendu.mjs` est **hors empreinte** : cette chaîne le **déclare**, ne le modifie pas.

| Scénario | Effet de la correction | Ce qu'il doit devenir |
|---|---|---|
| **19 — `--filtre=cartes`** | **ÉCHOUE** — l. 1526-1530 assertent `thead: 'none'` à 320 px, ce que la correction change délibérément | `thead: 'block'` ; ajouter `position: 'absolute'`, `clipPath: 'inset(50%)'`, largeur de boîte ≤ 2 px. Le reste (`:empty`, `data-etiquette`, invariant I-8) est indépendant et doit passer **inchangé** |
| **20 — `--filtre=arbre`** | **Passe, mais ne prouve rien** : assertions en `>=`, `columnheader` en simple `note()`. Son titre (« thead masqué ») et ses commentaires l. 1662-1670 deviennent **faux** | Assertions strictes à 320 px : `columnheader === 4`, `rowgroup === 2`, `row === 26`, `rowheader === 25`, `cell === 63` ; **et l'assertion de tête** : sous-ensemble identique à 320 px et à 900 px |
| **18 — `--filtre=impression`** | Passe inchangé — mais ne mesure que `display` | Ajouter `position` et `clipPath` du `thead` sur A4 **et** A5 : **seule sonde automatisée capable de détecter le retrait de la garde `@media screen`** (obligation (b) de I-5 rév. #28) |
| **07 — 320 px** | **Passe, 7 vertes / 0 rouge.** *(Anticipation de ce contrat **infirmée par la mesure** : les `th` n'apparaissent **pas** dans la liste `debordants` — ils mesurent 40 / 43 / 31 / 56 px et leur `right` maximal est **194 px**, loin sous 320. L'inquiétude est levée.)* | Garde-fou conservé : si `debordants` était un jour promue en assertion, exclure les descendants d'un ancêtre `overflow: hidden`, car `getBoundingClientRect()` ignore l'écrêtage d'un ancêtre |
| **14, 17** | Aucun sélecteur commun | Inchangés |

**Conséquence pour le rapport de lot** : le scénario 19 sera **rouge** dès le commit. C'est un échec
**attendu et documenté**, pas une régression. S'il n'est pas annoncé comme tel, `test-integration-cms` le
remontera en régression.

---

## Issues de suivi à déposer

| # | Objet | Labels | Condition |
|---|---|---|---|
| **S-1** | `content: attr(data-etiquette) / ""` — alt vide, retrait de l'étiquette générée de l'arbre, fin de la double annonce | `a11y`, `design` | **Bloquée** par la preuve humaine au lecteur d'écran (S-5) |
| **S-2** | Porter l'étiquette de carte dans le DOM (`<span>` réel dans `liste-statuts.php`) au lieu du contenu généré — dette structurelle ; supprime aussi la fragilité I-8 | `a11y`, `contenu` | Gabarit gelé (contrat #6), hors empreinte, arbre partagé |
| **S-3** | Mettre à jour `tests/rendu/recette-rendu.mjs` — scénarios **19** (échoue), **20** (n'assertait rien), **18** (`position` + `clip-path`) | `infra`, `a11y` | **Immédiate — le lot est rouge sans elle** |
| **S-4** | Enregistrer dans `MASTER.md` un patron normatif « hors cadre accessible », aujourd'hui absent | `design` | Question à `lead-design-cms` |
| **S-5** | Exécuter et documenter le contrôle humain au lecteur d'écran sur la page Accessibilité (brief §8, §12) | `a11y`, `contenu` | **Débloque S-1.** Jamais exécuté sur ce projet à ce jour |
| **S-6** | Ratifier l'amendement de l'arbitrage 7 du contrat #22 et la révision I-5 (rév. #28) | `infra` | Avant le prochain lot, sinon faux défauts en revue |

---

## Recette exigée

**Préalable non négociable : exécuter la recette AVANT l'édition** et archiver la sortie — le scénario 19
va casser, et la capture « avant » à 320 px doit être prise sur le même état.

- Largeurs écran **320 / 359 / 599 / 600 / 900 px**. 599/600 contrôle le point de rupture ; **600 px
  prouve l'ordre des sections**.
- **Aperçu d'impression A4 (703 px) et A5 (469 px)** : `thead` en `display: table-header-group`,
  **`position: static`**, **`clip-path: none`**, quatre libellés présents et non écrêtés.
- **`document.scrollingElement.scrollWidth === clientWidth` à 320 px** (égalité stricte visée), plus le
  rect du `thead` : `width ≤ 2` et `right ≤ clientWidth`.
- **Comptes par rôle ARIA avant/après**, via CDP, filtre `! ignored`, aux deux largeurs.
- **Capture avant/après à 320 px**, page entière, `document.fonts.ready` — **diff attendu : 0 pixel**.
- **Ordre de tabulation à 320 px** : le `thead` ne doit **jamais** recevoir le focus.
- Scénarios **14** et **17** inchangés ; scénario **08** (axe) sans erreur bloquante.

## Risques résiduels et échelle d'escalade

| Risque | Détection | Repli |
|---|---|---|
| Chromium marque les nœuds déportés `ignored` | Scénario 20 : `columnheader × 0` persiste | **Dans cet ordre : (1)** `--esp-2xs` (4 px), précédent §12 ; **(2)** retirer `clip-path`, ne garder que `overflow: hidden` ; **(3) arrêter et remonter — ne jamais inventer un troisième mécanisme** |
| Le déport devient focusable au clavier | Ordre de tabulation à 320 px | Remonter : aucune correction possible dans l'empreinte |
| Un moteur non-Chromium expose l'arbre autrement | Non mesurable par ce harnais | S-5 |
| Une chaîne future insère une règle entre §7 bis et §11 | Recette à 600 px | I-14 |

**Non prouvé à l'issue de cette chaîne, et à écrire tel quel :** l'association en-tête ↔ cellule ;
l'utilisabilité réelle au lecteur d'écran à 320 px ; la sortie papier sur imprimante réelle (la recette
mesure `emulateMedia({ media: 'print' })` dans Chromium headless) ; les moteurs non-Chromium.
