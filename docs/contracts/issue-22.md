# Contrat d'interface — Issue #22 — Écrire la couche visuelle des statuts et la feuille d'impression

**Gelé par `lead-issue-cms` le 12 août 2026.** Opposable à partir de ce point.

Cette issue **ne touche aucun fichier de l'extension**. Il n'existe donc pas de frontière
front ↔ back à réconcilier : le contrat porte sur la frontière **`composants.css` / `print.css` →
toute chaîne future touchant le CSS du thème**, et sur la consommation de la **liste fermée de
classes** émise par les gabarits gelés de la chaîne #6.

## Empreinte d'écriture — exhaustive

| Fichier | Nature |
|---|---|
| `wp-content/themes/massifs/assets/css/composants.css` | nouveau |
| `wp-content/themes/massifs/assets/css/print.css` | nouveau |
| `wp-content/themes/massifs/functions.php` | **enfilage seul**, l. 189‑214 |

Interdits absolus : `assets/css/tokens.css`, `assets/css/layout.css`, `front-page.php`,
`templates/**`, `design-system/MASTER.md`, `docs/BRIEF.md`, `CLAUDE.md`.

## Fonctions de lecture exposées par l'extension

**Aucune.** Cette issue ne consomme ni fonction `massifs_*`, ni classe `Massifs\`, ni constante.
Elle ne style que des classes déjà émises par les quatre parties gelées de la chaîne #6.

## Routes REST

**Aucune.**

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `disponible` / `autorise` | `pastille--autorise` | aplat `--statut-autorise`, **aucun motif** (l'opposition nu/marqué encode le sens, §10.3), liseré 2 px |
| `disponible` / `interdit` | `pastille--interdit` | aplat `--statut-interdit` + hachure croisée, liseré 2 px |
| ZAPEF `autorise` / `interdit` | `jalon--autorise` / `jalon--interdit` | carré planté + hampe, barre oblique unique si interdit, liseré 2 px hampe comprise |
| `information_indisponible` | `pastille--indisponible` | `--statut-indisponible` + hachure descendante `--statut-indisponible-encre` |
| `hors_saison` | `pastille--hors-saison` | `--statut-hors-saison`, aucun motif |
| `non_encore_publie` | `pastille--non-publie` | `--statut-non-publie` + pointillé |
| `donnee_perimee` | `.ardoise__peremption` (chaîne #5) | **hors périmètre** — page-level, s'ajoute aux statuts, ne les masque jamais |
| `couche_effis_indisponible` | — | **hors périmètre** — aucune couche EFFIS n'existe |

Rendu sous `forced-colors: active` et à l'impression : voir §Invariants I‑7 et I‑10.

## Chaînes fournies par le serveur

**Aucune chaîne n'est composée par ces deux feuilles.** Deux exceptions, toutes deux non
rédactionnelles :

- `content: " (" attr(href) ")"` dans `print.css` — ponctuation autour d'une valeur d'attribut,
  verbatim de `MASTER.md` §13.
- `content: attr(data-etiquette)` — **relecture d'un attribut écrit par le gabarit**
  (`Massif`, `Niveau d'Accès`, `ZAPEF`, `Fraîcheur`, apostrophe U+0027 comprise). Le CSS ne
  fabrique aucune étiquette.

Les capitales sont **toujours** un `text-transform`, jamais une édition : le DOM conserve
`Légende de la carte`, `Niveau d'Accès` et les quatre libellés officiels octet pour octet
(§14.3 entrée 5 c).

## Classes stylées — confrontation à la liste fermée des contrats #5 et #6

> **Rectification du 12 août 2026, après refacto.** La première rédaction de cette table
> annonçait comme « stylées » sept classes qui ne portent aucune déclaration propre parce
> qu'elles sont **déjà couvertes par `layout.css` ou par héritage**. L'implémentation avait
> raison, la table avait tort : les redéclarer aurait été le doublon de propriété que ce même
> contrat interdit. La table est scindée en deux.

**Stylées par déclaration propre dans `composants.css`** : `statut` · `statut__marque` ·
`statut__libelle` · `pastille` · `pastille--autorise` · `pastille--interdit` ·
`pastille--indisponible` · `pastille--hors-saison` · `pastille--non-publie` · `jalon` ·
`jalon--autorise` · `jalon--interdit` · `liste-statuts` · `liste-statuts__tableau` ·
`liste-statuts__resume` · `liste-statuts__entete` · `liste-statuts__ligne` ·
`liste-statuts__massif` · `liste-statuts__cellule` · `liste-statuts__fraicheur` ·
`liste-statuts__note` · `legende` · `legende__entrees` · `legende__entree` · `legende__note` ·
`legende__hors-niveau` · `legende__etiquette` · `legende__avertissement` · `bandeau-alerte`.

**Présentes en sélecteur, sans déclaration propre** : `liste-statuts__ligne--entete` et
`liste-statuts__ligne--hors-niveau`, qui n'apparaissent qu'en **exclusions `:not()`** de la
règle de survol (arbitrage 19).

**Couvertes sans déclaration propre — ce n'est pas un manque** :

| Classe | Ce qui la couvre |
|---|---|
| `liste-statuts__titre` · `legende__titre` | Ce sont des titres portant `.repere` : famille, poids, corps, capitales et interligne viennent de `layout.css` l. 92‑96, le retrait de signature de `.repere` l. 412‑417, le rythme vertical de `.liste-statuts > * + *` |
| `bandeau-non-officialite` · `__texte` · `__lien` | **Invariant I‑4** : le slab §7.1 vit dans `layout.css` sur `.bande--non-officialite` l. 171‑179. Le texte hérite du `--fs-200` de la bande, le lien de la règle `a` globale |
| `bandeau-alerte__texte` · `bandeau-alerte__lien` | `layout.css` l. 336‑343 peint déjà `.sur-sombre` et `.sur-sombre a` → `--c-mistral-clair`, exactement l'effet attendu par le contrat #6 |
| `liste-statuts__cellule--niveau` · `--zapef` · `--fraicheur` · `--hors-niveau` | Aucun traitement différentiel : mise en page par la classe de base, étiquette de carte par `attr(data-etiquette)`. La cellule fusionnée est traitée par l'**absence** de `data-etiquette`, pas par son modificateur |
| `legende__entrees--massif` · `--zapef` · `--hors-niveau` | Même grille pour les trois listes ; la séparation « Sur ce site » est portée par l'enveloppe `.legende__hors-niveau` |
| `repere` · `repere--bloc` · `sur-sombre` | `layout.css` l. 336‑343 et 412‑475 |

**Non stylées, délibérément** — décision, pas oubli :

| Classe | Raison |
|---|---|
| `liste-statuts--partielle` | Le contrat #6 la qualifie lui-même de « crochet d'état, **aucun changement de teinte de statut** ». L'implémentation correcte est l'absence de déclaration |
| `bandeau-alerte--indisponible` · `--hors-saison` · `--non-publie` | Le contrat #6 écrit « les trois variantes **ne diffèrent par aucune couleur**, ce sont des crochets structurels » |
| `repere` · `repere--bloc` · `sur-sombre` | Déjà stylées par `layout.css` (chaîne #5), l. 336‑343 et 412‑475. Les redéclarer serait un doublon de propriété |

**Non écrit faute de source normative** — signalé, jamais inventé :

- la hachure `--c-mistral` du `.bandeau-alerte` (§8.3) : « faible opacité » n'est chiffré nulle
  part, aucun jeton d'opacité n'existe ;
- la frise : **aucun gabarit ne l'émet, aucun contrat ne fixe son nom de classe**. Écrire son
  `display: none` d'impression (§13) obligerait à inventer un sélecteur ;
- l'échelle typographique d'impression en `pt` (10,5 / 14 / 20 / 34) : aucun jeton `pt` n'existe.

## Interdits

1. Le thème n'appelle jamais une source externe ni une fonction d'ingestion.
2. Le thème ne calcule jamais une règle métier (saison, péremption, formatage de niveau).
3. L'extension n'émet jamais de HTML de présentation publique.
4. Aucun `url()`, aucun `@import` — zéro requête vers un domaine tiers.
5. Aucune custom property définie hors `tokens.css`.
6. Aucune valeur hexadécimale, aucune durée, aucune taille de police hors jeton.
7. Aucun jeton créé : les 111 du contrat #4 sont figés, sha256 épinglé.
8. Aucun rôle ARIA ajouté — le CSS ne peut pas, et l'interdit 24 du contrat #6 est tenu par
   construction.
9. Aucun texte posé sur un aplat de statut, y compris à l'impression (§10.4).
10. Aucune troncature de `.statut__libelle` : `text-overflow: ellipsis` interdit à toute largeur.
11. `bandeau-non-officialite` jamais masqué à l'impression (interdit 23 du contrat #6).
12. Aucune animation, aucune transition.
13. `--ombre-decalee` et `--ombre-decalee-sombre` **non consommés** (direction du propriétaire).

## Arbitrages

| # | Point | Décision | Raison |
|---|---|---|---|
| **1** | « Copie fidèle de §8.1 » contre « aucune valeur brute » | **Jeton là où il existe** (`--pastille-l/-h`, `--jalon-cote/-hampe`, `--statut-lisere-epaisseur`, `--statut-motif-trait`, `--statut-motif-pas`) ; **reproduction de la référence** pour les 8 littéraux sans jeton (`45deg`/`-45deg`, `2px 9px`, `1.2px`/`1.4px`/`6px 6px`, `calc(50% ± 1.5px)`, `calc(50% - 1px)`) ; signalement | Les deux exigences de l'issue sont inconciliables à la lettre. Précédent A‑13 du contrat #5, déjà appliqué dans `layout.css` pour les trois mesures du repère. Le bloc reste diffable par substitution contre §8.1 |
| **2** | Quel jeton porte le liseré | **Jamais `--bord-moyen`.** Toujours `var(--statut-lisere-epaisseur) solid var(--statut-lisere)` | `--bord-moyen` code `--c-charbon` en dur et annule l'exception `.sur-sombre` de `tokens.css` l. 162‑168. Le liseré est le mécanisme **porteur d'AA** (§10.2, pire cas 4,11:1) : le figer rendrait la frise et le portail illisibles sur chrome sombre |
| **3** | `.statut__marque` est un `<span>` ; §8.1 ne pose aucun `display` | `.statut { display: inline-flex; align-items: center; gap: var(--esp-2xs) }` — le flex **blockifie** ses enfants inline, donc les tailles de §8.1 deviennent effectives **sans toucher au bloc copié**. Plus, hors bloc : `.statut__marque { display: inline-block; flex: 0 0 auto }` | Seule voie qui rende §8.1 fonctionnel sans y ajouter une déclaration. `inline-block` est inerte sous flex et porteur si le flex disparaît — ceinture-bretelles sur un élément porteur d'AA. `flex: 0 0 auto` empêche un libellé long d'écraser l'aplat de 26 px jusqu'à son seul liseré |
| **4** | Hampe du jalon, absolue, hors flux | `.statut__marque.jalon { margin-block-end: var(--jalon-hampe) }`, règle additive scopée à l'usage en ligne | Réserve la place sans toucher `.jalon`, qui reste réutilisable tel quel par la carte et le portail. Sans elle, la hampe chevauche la ligne suivante ou est rognée par tout `overflow: hidden` d'un ancêtre |
| **5** | Cibles 44 px sur les marques | **Non applicable.** Aucun padding de 44 px | §8.1 est explicite : « ≥ 44 px **quand l'objet est cliquable**, taille nominale quand il est informatif ». Les marques portent `aria-hidden="true"`, ne sont ni focusables ni cliquables. Seule règle applicable : §10.6 n° 6, aucune pastille sous 12 px |
| **6** | `forced-colors: active` | **Ne pas redéclarer** aplats ni bordures (l'UA force déjà `Canvas` / `CanvasText`). **Reconstruire** les 4 motifs par pseudo-éléments en `background-color: CanvasText`, **et redéclarer la hampe** `.jalon::after` en `CanvasText`. Le liseré n'est **jamais** détourné en motif | Les `background-image` en dégradé sont ramenés à `none` en couleurs forcées, or le motif porte **la moitié** de l'information (§10.3). La hampe est un `background-color` : elle devient **Canvas sur Canvas**, et le jalon perd la silhouette « point planté » qui porte la distinction ZAPEF ↔ massif (§10.6 n° 3). Vocabulaire aligné sur `layout.css` l. 467‑475. **Rejeté** : motif en `url()` SVG data-URI — il exigerait une couleur hexadécimale hors `tokens.css`, défaut bloquant §16 |
| **6 bis** | Conséquence assumée de l'arbitrage 6 | En couleurs forcées, `--autorise` et `--hors-saison` deviennent **identiques** (Canvas + liseré CanvasText, aucun motif de part et d'autre) | §10.8 le couvre : « le libellé officiel reste le porteur de sens ». **Consigné, pas masqué** |
| **7** | Cartes empilées sous `--bp-s` | `display: block` (**jamais `display: contents`**) ; `::before` **gardé par `[data-etiquette]`** ; `:empty { display: none }` en mode cartes seul ; `thead { display: none }` en mode cartes ; filet `--bord-fin` **entre cellules d'une carte**, **espacement** entre cartes | `display: contents` sort la boîte de l'arbre de rendu avec un mapping d'accessibilité inégal. Le garde `[data-etiquette]` est **obligatoire** : la cellule fusionnée `colspan="3"` (`liste-statuts.php` l. 385) n'en porte pas et recevrait une étiquette vide. 25 cartes séparées par 2 px violeraient la quantité imposée de la règle de portée C |
| **7 bis** | Erreur du contrat #6 | `table-header-group` est attribué à `.liste-statuts__ligne--entete`, qui est le `<tr>` : **cette valeur ne s'applique qu'au `<thead>`**. Le sélecteur correct est `.liste-statuts__tableau thead` | Correction de contrat, à relayer |
| **8** | `--ombre-decalee` contre `MASTER.md` §8.5 et §6.4 | **Non consommé** (direction du propriétaire). Substitut : filet `--bord-moyen` 2 px en tête de bande — le « 2 px en tête de bande » de la règle de portée C — plus le repère déjà porté par le `h2` | La direction du propriétaire l'emporte. Divergence à enregistrer par la chaîne #21, propriétaire de `MASTER.md` |
| **8 bis** | Fond du bloc de légende | **`--c-calcaire`, jamais `--c-calcaire-ombre`** | `--statut-indisponible`, `--statut-hors-saison` et `--statut-non-publie` valent **tous les trois** `var(--c-calcaire-ombre)` (`tokens.css` l. 51‑56). Un slab calcaire-ombre effacerait les trois aplats hors niveau **à 1:1**, ne laissant que liseré et hachure. Argument mesuré, pas préférence |
| **9** | Légende à 360 px | **Une colonne** sous `--bp-s` ; bande 2 + 2 à partir de `--bp-s` | §7.1 esquisse deux colonnes à 360 px, mais `Accès à la ZAPEF* interdite` en condensée capitale sur ≈ 140 px empile trois lignes. §10.6 n° 6 (aucun libellé tronqué, aucun défilement horizontal à 320 px) l'emporte sur un croquis de mise en page antérieur à l'établissement verbatim des libellés. Divergence signalée |
| **10** | Capitales de `.statut__libelle` sur une phrase entière | Capitales conservées, **sauf** pour `non_encore_publie`, via le sélecteur adjacent `.pastille--non-publie + .statut__libelle` → `text-transform: none` ; `letter-spacing: normal` | La chaîne #6 place une phrase de deux propositions dans un `.statut__libelle` que le contrat spécifie en capitales, or §14.3 entrée 5 (b) les interdit sur un paragraphe. La marque est **toujours** émise pour cet état et précède **toujours** le libellé (`liste-statuts.php` l. 392‑394, `legende.php` l. 241‑244) : un sélecteur adjacent simple suffit, sans `:has()`, sans modification de gabarit |
| **10 bis** | *(ruling D‑1)* Famille typographique du même libellé | **Étendu** : le même sélecteur adjacent pose `font-family: var(--police-texte)` et `font-weight: var(--poids-texte)` | §5.1 lie `--fs-250` à la famille de titrage **au titre de son emploi « Étiquette : capitales »** — prémisse que l'arbitrage 10 supprime précisément pour ce cas. Rendre un paragraphe de deux phrases en Big Shoulders Display 700 à 13 px, police « faite pour être lue de loin sur un panneau » (§5), serait un contresens de lisibilité |
| **10 ter** | *(ruling D‑2)* `align-items: center` face à un libellé multi-ligne | **Non adopté.** `align-items: center` est maintenu | Prévisible et correct pour **tous** les libellés d'une ligne, soit la totalité de la bande 2 + 2 et des cellules ≥ `--bp-s`. Le seul cas multi-ligne est la phrase `non_encore_publie`, que le ruling 10 bis recompose déjà en famille de labeur et casse normale. Introduire `:has()` ou un second mode d'alignement pour un état rare est plus de mécanisme que le défaut n'en justifie. **Imperfection cosmétique acceptée et consignée** |
| **11** | `.legende__avertissement`, sans aucune spécification dans `MASTER.md` | **Stylée** en prose : `--fs-100`, `--c-charbon-doux` | Ne pas la styler choisit aussi — le corps à `--fs-300`, trop fort pour une réserve. Les deux jetons portent déjà exactement ce rôle ailleurs (§8.4, note §8.5). Absence de spécification signalée |
| **12** | Deux fichiers, ou un `@media print` | **Deux.** `print.css` enfilée **après** `composants.css`, en `media="print"` | Raison décisive : **la cascade**, pas HTTP. Les cartes empilées sont la base mobile-first et les vraies colonnes sont restaurées par `min-width: 37.5rem` ; une page A4 moins 12 mm fait ≈ 703 px ≈ 43,9 rem, donc la requête s'applique **par chance** — sur A5, sur un autre format ou en aperçu zoomé, elle ne s'applique plus et **on imprimerait des cartes empilées au lieu du tableau exigé par §13**. `print.css` restaure `display: table` et `table-header-group` **sans condition**. Raison secondaire : `media="print"` est non bloquante au rendu écran (brief §10) |
| **12 bis** | Dépendances d'enfilage | **RÉVISÉ le 12 août 2026 après refacto.** `massifs-composants` → `['massifs-tokens', 'massifs-layout']` ; `massifs-print` → `['massifs-tokens', 'massifs-composants']` | Règle universelle du contrat #4 : une feuille qui emploie `var(--…)` en déclarant `[]` est un défaut. La dépendance de `print.css` à `massifs-composants` **garantit l'ordre des balises**, donc sa victoire dans les égalités de spécificité. **Première rédaction : `massifs-composants` → `['massifs-tokens']` seul**, au motif que l'invariant I‑1 rend les deux feuilles disjointes en sélecteurs, donc l'ordre indifférent. **Ce raisonnement était incomplet** : la dépendance n'est pas d'ordre, elle est **sémantique**. `composants.css` suppose le `box-sizing: border-box` global de `layout.css` l. 27‑31 — sans lui, `.pastille` mesure 30 × 20 au lieu de 26 × 16 et le calcul « 26 × 16 est la boîte extérieure » du §2 est faux — et `.bandeau-alerte` ne pose que `padding-block` / `padding-inline-end` parce que son `padding-inline-start` vient de `.repere` (`layout.css` l. 416). Une dépendance déclarée doit dire la vérité |
| **13** | Piège `.sur-sombre` à l'impression | Dans `@media print` : forcer `border-color: var(--c-charbon)` sur `.pastille`/`.jalon` et `background-color: var(--c-charbon)` sur `.jalon::after` | `tokens.css` bascule `--statut-lisere` en **calcaire** sous `.sur-sombre` ; §13 convertit le chrome sombre en blanc. Toute pastille de l'ardoise ou de la frise imprimerait **un liseré blanc sur blanc**, c'est-à-dire la perte du mécanisme porteur d'AA. Aucun jeton redéfini, §12 intact. Latent aujourd'hui, **bloquant le jour de la frise** |
| **13 bis** | *(écart É‑5)* Encre de motif à l'impression | Étendu : `print.css` redéclare le `background-image` de `.pastille--interdit` et `.jalon--interdit` avec `var(--c-charbon)` | `tokens.css` l. 164‑167 bascule aussi `--statut-interdit-encre` et `--statut-zapef-interdit-encre` en calcaire sous `.sur-sombre`. La hachure croisée s'imprimerait en `#EDEEEC` sur papier blanc, **≈ 1,04:1** : disparition de la moitié de l'information. §16 en fait un défaut bloquant, et §13 exige « hachure croisée **noire** ». `--indisponible` et `--non-publie` non touchés : leur encre `--c-charbon-doux` n'est jamais basculée |
| **14** | Le repère à l'impression — §13 se contredit | **Non forcé à l'impression.** `print-color-adjust: exact` reste limité à `.pastille`, `.jalon`, `.jalon::after` | §13 limite `print-color-adjust: exact` aux pastilles et jalons **et** demande que le repère s'imprime, alors qu'il est fait de `background-color` sur pseudo-éléments (`layout.css` l. 419‑439) que les navigateurs n'impriment pas sans cette propriété. §3.4 déclare le repère **décoratif** et pose que sa disparition ne retire rien : honorer le `uniquement` explicite est le choix qui n'invente pas. Le redessiner en `border` serait une **seconde implémentation de l'élément de signature**, contre §3.1. Divergence signalée |
| **15** | « Gris 45 % » de §13 | **Pas `--c-trace`.** Le motif `indisponible` conserve `var(--statut-indisponible-encre)` (= `--c-charbon-doux`, 6,33:1) à l'impression | §16 interdit `--c-trace` dans **tout** motif de statut (mesuré 1,96:1, décision D‑17). Même mécanisme qu'à l'écran, plus sombre que 45 % — ce qui ne peut qu'aider sur papier. Divergence avec le littéral « 45 % » signalée |
| **16** | Échelle typographique d'impression en `pt` | **Non écrite.** Seul littéral retenu : `@page { margin: 12mm }`, que l'issue mandate verbatim | Aucun jeton `pt` n'existe et l'issue interdit les valeurs brutes. Le §5.3 du brief demande « imprimable proprement », pas « composé à 10,5 pt ». Retire 4 des 8 valeurs brutes non tokenisables |
| **17** | *(écart É‑1)* `color` sur `.statut__libelle` | **Non déclaré** — héritage | §8.1 écrit « `--c-charbon` **sur le fond de page** ». Le figer produirait, le jour où une marque apparaît dans `.sur-sombre` (frise §8.2, barre d'action §7.2), un libellé charbon sur mistral-nuit mesuré **1,16:1**. L'héritage donne 14,74:1 sur la page et 12,66:1 sur chrome sombre via `layout.css` l. 336‑339. La clause « sur le fond de page » est **honorée, pas contournée** |
| **18** | *(écart É‑2)* `line-height` sur `.statut__libelle` | **Non déclaré** — hérite `--lh-corps` | §5.1 assigne 1,2 à `--fs-250` ; aucun jeton ne le porte (`--lh-sous` 1,15, `--lh-dense` 1,35 seraient des substitutions). Et 1,15 à 13 px sur le paragraphe `non_encore_publie` serait une régression de lisibilité. Divergence signalée |
| **19** | *(écart É‑3)* Survol de ligne | **Exclut `.liste-statuts__ligne--hors-niveau`** | Le survol §9.2 peint la ligne en `--c-calcaire-ombre`, qui est exactement la valeur des trois aplats hors niveau : rapport **1:1** au survol. Pour `hors_saison`, sans motif, il ne resterait qu'un rectangle de liseré vide. Même argument mesuré que 8 bis, appliqué à un état d'interaction |
| **20** | *(écart É‑4)* Pied à l'impression — §13 se contredit | **Seul `.pied__nav` est masqué.** `.pied` et `.pied__attribution` restent imprimés, convertis en encre noire | §13 dit « sauf dans les menus et le pied, masqués » puis « **Toujours imprimés** : … les attributions ». Or les attributions vivent **dans** le pied (`templates/footer.php` l. 47, 55). Seule lecture qui n'annule pas une ligne de DoD (brief §12, « attributions et licences toutes présentes »). Noms de classes vérifiés : `functions.php` l. 148‑150 |
| **21** | *(ruling D‑3)* Propriété du slab de non-officialité | **Hors périmètre.** Consigné en invariant I‑4 | L'effet §7.1 est aujourd'hui porté par `.bande--non-officialite` (`layout.css`, chaîne #5), pas par `.bandeau-non-officialite`. `layout.css` est hors de mon empreinte. À ordonnancer dans l'issue qui livrera la deuxième page affichant un statut |
| **22** | Localisation des cartes empilées | **`composants.css`**, alors que la dépendance 4‑2 du contrat #6 les assignait à `layout.css` | La **disjonction des empreintes est la seule protection dans un arbre partagé**. Invariant I‑1 opposable à la chaîne #23, qui possède `layout.css` ensuite |
| **23** | *(trou de couverture relevé au refacto)* `MASTER.md` §13 : « `--c-mistral-nuit` → blanc **avec `--bord-fort` en haut** et texte noir ». `print.css` convertissait le fond et l'encre mais ne posait aucun filet supérieur | **Écrit.** `border-block-start: var(--bord-fort)` sur `.sur-sombre` dans `@media print` | Ce n'était **pas une décision** — la clause ne figurait ni dans les arbitrages, ni dans la liste « délibérément non écrit ». Elle est **spécifiée verbatim**, emploie un **jeton existant** et n'exige aucune invention : l'implémenter, c'est suivre `MASTER.md`, pas l'interpréter. Sans elle, l'ardoise et le pied se dissolvent dans le blanc du papier et la feuille perd la limite de bloc que §13 lui donne |
| **24** | *(signalé au refacto, non appliqué)* Le bloc typographique du rôle « Étiquette » (§5.1) est écrit **4 fois à l'identique** — `.statut__libelle`, `.liste-statuts__entete`, `.liste-statuts__cellule[data-etiquette]::before`, `.legende__etiquette` — soit 15 déclarations redondantes | **Non factorisé.** Les quatre blocs restent écrits en toutes lettres | La factorisation serait techniquement sûre (aucun conflit de cascade vérifié), mais le seul mécanisme disponible est un **sélecteur groupé** : l'interdit 7 interdit un jeton, la liste fermée du contrat #6 interdit une classe utilitaire. Un sélecteur groupé **coupleraient quatre composants appartenant à des chaînes différentes** — la chaîne #23, propriétaire de la légende, ne pourrait plus toucher `.legende__etiquette` sans écrire hors de son empreinte. **La duplication est ici le prix de la disjonction des empreintes**, qui est la seule protection de l'arbre partagé. Choix de plan, pas de refacto |

## Invariants opposables à toute chaîne future touchant le CSS du thème

| # | Invariant |
|---|---|
| **I‑1** | `layout.css` ne porte **jamais** un sélecteur `.liste-statuts*`, `.legende*`, `.statut*`, `.pastille*`, `.jalon*` ou `.bandeau-*` |
| **I‑2** | Le liseré des statuts est toujours `var(--statut-lisere-epaisseur) solid var(--statut-lisere)`, **jamais `--bord-moyen`** |
| **I‑3** | **Aucune surface `--c-calcaire-ombre` derrière une marque hors niveau** — les trois aplats hors niveau valent `--c-calcaire-ombre` et s'y effacent à 1:1. Vaut pour la légende, le survol, et tout encart, slab ou ligne alternée à venir |
| **I‑4** | Le slab du bandeau de non-officialité vit dans `layout.css` sur `.bande--non-officialite`. La première page qui rendra `bandeau-non-officialite` hors de cette bande devra déplacer le slab vers `composants.css` **dans la même édition**, sinon §5.6 est violée en silence |
| **I‑5** | `print.css` restaure la liste en colonnes **sans aucune requête de largeur**. Toute règle de liste ajoutée à `composants.css` sous `@media (min-width: 37.5rem)` doit être répliquée dans `print.css`, sinon elle disparaît sur A5 |
| **I‑6** | La chaîne « carte » doit sortir son image statique de repli de `.bande--carte`, que `print.css` masque, ou la ré-afficher elle-même |
| **I‑7** | Toute nouvelle marque de statut doit avoir sa reconstruction sous `forced-colors: active`. Les pseudo-éléments de `.jalon` sont **déjà occupés** — `::after` par la hampe |
| **I‑8** | Ne **jamais réindenter** `templates/parts/liste-statuts.php` : les cellules `<td …></td>` doivent rester sans nœud de texte, sans quoi `:empty { display: none }` cesse d'agir et le mode cartes affiche des champs vides étiquetés |
| **I‑9** | `--statut-autorise-encre`, `--statut-zapef-autorise-encre` et `--statut-hors-saison-encre` restent **non consommés** : les consommer poserait un motif sur `autorisé`, défaut bloquant §16 |
| **I‑10** | `print-color-adjust: exact` reste limité à `.pastille`, `.jalon` et `.jalon::after` |

## Divergences avec `MASTER.md` — à enregistrer par la chaîne #21, propriétaire du document

1. `--ombre-decalee` / `--ombre-decalee-sombre` deviennent des jetons **déclarés et consommés par
   personne** — contre §8.5 et §6.4 (arbitrage 8).
2. Légende sur **une** colonne sous `--bp-s` — contre le tableau de points de rupture de §7.1
   (arbitrage 9).
3. Échelle typographique d'impression en `pt` **non écrite** — contre §13 (arbitrage 16).
4. « Gris 45 % » d'impression rendu par `--c-charbon-doux` — contre le littéral de §13
   (arbitrage 15).
5. Le repère **ne s'imprime pas** — §13 étant contradictoire avec lui-même (arbitrage 14).
6. Interligne 1,2 de `--fs-250` **non appliqué** — contre §5.1 (arbitrage 18).

Sans enregistrement, `review-cms` signalera des **faux défauts** au prochain lot.
