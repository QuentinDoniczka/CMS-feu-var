# Preuves de recette — accessibilité, performance, origines réseau

**Issue #18** · Épic 6 · relevés du **16 août 2026**

> **Règle qui gouverne ce document.** Rien n'y est écrit comme vérifié qui ne l'ait pas été. Ce qui n'a
> pas été mesuré est nommé, avec son motif, au §5. Consigner une vérification non exécutée serait la
> même faute que présenter un statut périmé comme courant — c'est le §4.2 du brief, et il s'applique
> aussi aux preuves.

---

## 1. Conditions du relevé — à lire avant les chiffres

| | |
|---|---|
| **Date** | 16 août 2026, soirée |
| **Environnement** | stack Docker locale (`docker/up.sh`), `http://localhost:3002/` |
| **État de l'arbre** | thème + extension du dépôt montés en direct, **travail non commité des issues #16 et #18 présent** |
| **Outils** | `axe-core` et `playwright-core`, installés **hors dépôt** ; Chromium de Playwright |
| **Scripts** | [`outils/recette.mjs`](outils/recette.mjs) et [`outils/captures.mjs`](outils/captures.mjs) |

### 1.1 La dérogation `bypassCSP`, et pourquoi elle ne fausse rien

Depuis le durcissement de l'issue #16, le site sert
`Content-Security-Policy: … script-src 'self' …`. Cette politique **bloque l'injection d'axe-core** :
`page.addScriptTag()` lève `Executing inline script violates the following Content Security Policy
directive 'script-src 'self''`.

Les passes axe de ce relevé ont donc été prises avec **`bypassCSP: true` sur le contexte de navigation
du pilote de test, et uniquement sur ces passes**. La passe qui relève les **origines réseau** tourne
**sans aucune dérogation** : les origines mesurées sont donc bien celles que sert le vrai site.

La dérogation appartient au **pilote**, pas au site : elle ne retire pas l'en-tête, ne modifie pas ce
que le serveur envoie, et ne change donc ni la preuve « zéro requête tierce » ni la CSP elle-même.

**C'est écrit ici parce qu'un relevé qui tait ses conditions vaut moins qu'aucun relevé** : la
prochaine personne qui verra la CSP se demandera comment axe a pu tourner.

> **Conséquence de niveau lot, hors de l'empreinte de cette issue** : `tests/rendu/recette-rendu.mjs`
> injecte axe-core par `page.addScriptTag()` **aux lignes 1060, 3005 et 4699**. Sous cette CSP, ces
> trois assertions **échouent à l'exécution** — ce qui se lit comme une panne de harnais, pas comme un
> défaut d'accessibilité. Signalé à l'orchestrateur, qui le traite avant `test-integration-cms`.

### 1.2 Une fenêtre d'indisponibilité, dite plutôt que masquée

Entre **20 h 21 et 20 h 24 locales**, le site a répondu **500** : la chaîne sœur #16 avait, dans l'arbre
partagé, un `require_once` puis un `add_filter` pointant sur des fichiers et des classes pas encore
écrits. Tous les relevés de ce document sont **postérieurs au rétablissement**, sauf mention contraire.
Aucun relevé pris pendant la fenêtre n'est réutilisé.

---

## 2. Accessibilité — axe-core

Seuil du projet, repris de `tests/README.md` : une violation est **bloquante** si son impact est
`serious` ou `critical`.

| Page | Violations bloquantes | Détail |
|---|---|---|
| Accueil `/` | **0** | aucune violation, quel que soit l'impact |
| `/la-demarche/` | **0** | aucune violation, quel que soit l'impact |
| `/accessibilite/` | **0** | aucune violation, quel que soit l'impact |
| `/mentions-legales/` | **0** | aucune violation, quel que soit l'impact |
| Connexion `/wp-login.php` | **0** | 2 violations `moderate` — voir ci-dessous |

**Les deux violations de la page de connexion** sont `landmark-one-main` et `region`. Elles sont
`moderate`, donc **sous le seuil bloquant**, et elles sont produites par **l'écran de connexion livré
avec le cœur de WordPress**, pas par le code écrit pour ce projet. Elles sont consignées, pas
dissimulées, et elles ne sont pas corrigées : corriger le balisage de `wp-login.php` demanderait de
modifier `functions.php`, hors de l'empreinte de cette issue.

**Structure de titres**, relevée sur les trois pages éditoriales : un seul `h1` par page — le titre de
la page —, hiérarchie du corps commençant à `h2`, **aucun identifiant en double** (17, 14 et 13
identifiants respectivement, tous uniques). Aucune collision entre les ancres posées par le gabarit
(`sources`, `editeur`, `signalement`) et celles posées par le contenu importé.

Relevés bruts : [`releves/accueil-et-connexion.json`](releves/accueil-et-connexion.json) ·
[`releves/pages-editoriales.json`](releves/pages-editoriales.json)

---

## 3. Zéro requête vers un domaine tiers

Contrainte n° 2 du projet, et ligne de DoD du §12. Mesuré en interceptant **toutes** les requêtes du
navigateur et en comparant leur origine à celle du site.

| Page | JavaScript activé | JavaScript désactivé |
|---|---|---|
| Accueil | **0 origine tierce** | **0 origine tierce** |
| `/la-demarche/` | **0** | **0** |
| `/accessibilite/` | **0** | **0** |
| `/mentions-legales/` | **0** | **0** |
| `/wp-login.php` | **0** | **0** |

Polices, fond de carte, bibliothèque cartographique et images sont servis depuis le domaine du site.

---

## 4. Performance — budgets du §10

Toutes les valeurs sont des **octets transférés** (`content-length`), donc **compressés** : le serveur
compresse. À ne pas confondre avec la taille sur disque — `leaflet.js` pèse 147 517 o sur disque pour
52 930 o de scripts transférés au total sur l'accueil.

### 4.1 Accueil

| Catégorie | Transféré |
|---|---|
| Document HTML | 4 340 o |
| Feuilles de style | 48 351 o |
| Scripts | 52 930 o |
| **HTML + CSS + JS** | **105 621 o ≈ 103 Ko** |
| Polices | 69 432 o, en **2 fichiers** |
| Images | 177 172 o avec JS, 138 964 o sans |

**Budget : « HTML + CSS + JS < 250 Ko transférés, hors fond de carte et géométries ». Tenu — 103 Ko,
soit 41 % du budget.**

`carte-statique.png` (138 964 o) est l'image de repli du fond de carte : elle n'appartient à aucune des
trois catégories comptées et n'entre donc pas dans ce budget. Elle n'est pas passée sous silence pour
autant — c'est le plus gros objet de la page, et le seul candidat sérieux à une optimisation future.

**Budget : « deux fichiers de police maximum ». Tenu — exactement 2**, tous deux variables et
auto-hébergés.

**Le même budget lu en octets bruts, pour qu'une revue n'ait pas à le recalculer.** Le §10 du brief
écrit « transférés » ; c'est la lecture retenue ci-dessus. Mesurés sur disque le 16 août 2026, les
mêmes ressources pèsent **333 177 o bruts** (document 20 078 + feuilles 136 654 + scripts 176 445, dont
`leaflet.js` 147 517 à lui seul). Sous la lecture brute — celle que l'arbitrage B-11 du contrat #2 a
retenue pour les **géométries**, où le brief n'écrit pas « transférés » — ce total **dépasserait** les
250 Ko. Les deux nombres sont donnés ; aucun n'est présenté à la place de l'autre, et l'écart tient
presque entièrement à la bibliothèque cartographique, qui n'est chargée que sur l'accueil.

### 4.2 Pages éditoriales

| Page | Transféré, total | Texte rendu |
|---|---|---|
| `/la-demarche/` | 119 768 o | 7 237 caractères |
| `/accessibilite/` | 118 682 o | 4 780 caractères |
| `/mentions-legales/` | 117 713 o | 2 716 caractères |

Ces pages **n'enfilent aucune ressource propre** : elles héritent des feuilles déjà en place et
n'ajoutent pas un octet de CSS ni de JavaScript. L'essentiel de leur poids est constitué des polices
(69 Ko) et des feuilles de style communes (48 Ko), tous deux mis en cache après la première page vue.

### 4.3 Sans JavaScript

Le texte rendu est **identique** avec et sans JavaScript sur les trois pages éditoriales (7 237, 4 780
et 2 716 caractères dans les deux cas). Ces pages ne dépendent d'aucun script.

---

## 5. Ce qui n'a **pas** été vérifié — et pourquoi

| Vérification | État | Motif |
|---|---|---|
| **Contrôle humain au lecteur d'écran** | **non relevé** | Aucun agent ne peut l'exécuter. Procédure et gabarit de preuve : [`controle-lecteur-ecran.md`](controle-lecteur-ecran.md) |
| **Écran gestionnaire** (§8, page clé) | **non relevé dans cette passe** | Demande une session authentifiée. Déjà couvert par le scénario `portail` du harnais de lot |
| **Zoom texte 200 %** sur les trois pages | **non relevé** | Hors du périmètre outillé ici ; à ajouter au harnais de lot |
| **`forced-colors: active`** sur les trois pages | **non relevé** | Idem |
| **Impression** des trois pages | **non relevé** | `print.css` existe mais n'a jamais été éprouvé face à une page éditoriale — signalé comme sans propriétaire par le contrat #24 |
| **Chargement < 2,5 s sur mobile simulé** (§10) | **non relevé** | Demande un profil réseau bridé, non outillé ici |

---

## 6. Points d'attention relevés, hors empreinte de cette issue

1. **`<h2>` vide dans le DOM initial de l'accueil** — `class="carte__panneau-titre"`, rempli par le
   JavaScript à la sélection d'un massif. axe ne le classe pas bloquant (`empty-heading` est
   `moderate`), mais un titre vide est précisément ce qu'un lecteur d'écran annonce mal. Hors empreinte.
2. **Deux attributions apparaissent deux fois sur les mentions légales** — périmètres DDTM et statuts
   préfecture, une fois dans la table des sources et une fois dans le pied, qui les rend sur *toutes*
   les pages. Duplication **pré-existante**, déjà enregistrée au contrat #24 report F-3 ; la résoudre
   demande `templates/footer.php`, hors empreinte.
3. **Les `h1` et `h2` des pages éditoriales sont rendus en famille d'affichage**, alors que le §7.3 du
   design system les veut en famille de texte, sans repère. Divergence **déjà enregistrée** au contrat
   #24, A-8/F-2 ; la corriger demande `assets/css/editorial.css` **et** un handle dans `functions.php`,
   deux fichiers hors empreinte. **Constatée à l'écran**, pas déduite — voir les captures.

4. **Trois `<style>` en ligne par page éditoriale, bloqués par la CSP.** Relevé le 16 août 2026 à
   20 h 55 UTC dans le HTML servi, non déduit :

   | Page | `<style>` en ligne | Identifiants |
   |---|---|---|
   | Accueil | 1 | `wp-img-auto-sizes-contain-inline-css` |
   | `/la-demarche/`, `/accessibilite/` | 4 | le précédent + `wp-block-heading`, `wp-block-list`, `wp-block-paragraph` |
   | `/mentions-legales/` | 3 | le précédent + `wp-block-heading`, `wp-block-paragraph` (pas de liste dans cette copie) |

   Le cœur de WordPress imprime une feuille en ligne **par type de bloc rendu**. Le retrait de
   `wp-block-library` par le thème ne les atteint pas : ce sont des styles **par bloc**, enregistrés
   ailleurs. Et le site sert `style-src 'self'` **sans** `'unsafe-inline'` : le navigateur les
   **bloque**, et journalise trois violations de CSP par page dans la console.

   **Portée réelle, dite sans dramatiser** : aucune conséquence visuelle — aucune feuille du thème ne
   dépend de ces règles, et les pages sont rendues correctement, captures à l'appui. Ce qui est en
   cause, c'est (a) l'interdit « aucun `<style>` en ligne » du contrat §6, (b) le bruit dans la console
   d'un site dont l'argument est justement la propreté du réseau.

   **Cause et emplacement du correctif** : le contenu ne peut rien y faire — c'est le rendu de
   `wp:paragraph`, `wp:heading` et `wp:list` qui les déclenche, et les remplacer par du HTML brut
   rendrait la copie non modifiable dans l'éditeur. Le correctif est un retrait de file d'attente dans
   `functions.php`, **hors empreinte de cette issue**. Le défaut est **pré-existant sur l'accueil**
   (une occurrence, `wp-img-auto-sizes-contain-inline-css`) : les pages éditoriales l'amplifient, elles
   ne l'introduisent pas.

---

## 6 bis. Défilement horizontal — mesuré à **320 px**, la largeur que le §8 nomme

Le §8 du brief exige « pas de défilement horizontal à **320 px** ». Les captures du §7 sont prises à
360 px ; **320 px a donc été mesuré séparément**, sur les cinq pages, en relevant
`scrollWidth - clientWidth`.

| Page | 320 px | 360 px |
|---|---|---|
| Accueil | **0 px** | **0 px** |
| `/la-demarche/` | **0 px** | **0 px** |
| `/accessibilite/` | **0 px** | **0 px** |
| `/mentions-legales/` | **0 px** | **0 px** |
| `/wp-login.php` | **0 px** | **0 px** |

**Dix mesures, dix fois zéro.** La ligne du §8 est tenue à la largeur qu'elle nomme, et pas seulement à
celle qui était commode à capturer.

## 6 ter. Feuilles de style en ligne bloquées par la CSP — sans conséquence visuelle

Constat, pas défaut. Le cœur de WordPress imprime une feuille en ligne **par type de bloc rendu**, et la
copie éditoriale est faite de blocs (`wp:paragraph`, `wp:heading`, `wp:list`) — c'est ce que
l'arbitrage A-1 impose. Sous `style-src 'self'`, le navigateur les **bloque** et journalise une
violation par balise.

| Page | Balises `<style>` dans le DOM |
|---|---|
| Accueil | 1 |
| `/la-demarche/` | 4 |
| `/accessibilite/` | 4 |
| `/mentions-legales/` | 3 |

**Aucune conséquence visuelle constatée** : ces feuilles ne stylent que des blocs dont ce thème ne
dépend pas, et `wp-block-library` est déjà retiré du front. **Correctif nommé et porté en dette** :
`wp_dequeue_style()` dans `functions.php`, hors empreinte de cette issue.

C'est écrit ici parce que la console d'un relecteur affichera ces violations, et qu'il faut qu'il sache
qu'elles sont connues, mesurées et sans effet — plutôt qu'il les découvre et les prenne pour une panne.

## 7. Captures

Desktop 1440 × 900 et mobile 360 × 800, page entière, **sans aucune dérogation CSP**.
Le débordement horizontal (`scrollWidth - clientWidth`) est **nul sur les dix captures**.

`captures/` — `accueil`, `connexion`, `demarche`, `accessibilite`, `mentions`, chacune en
`-desktop.png` et `-mobile-360.png`.

### 7.1 Débordement horizontal — les valeurs, pas seulement la conclusion

`captures.mjs` imprime le débordement sur la sortie standard mais ne l'écrit dans aucun fichier : la
conclusion ci-dessus survivait sans ses chiffres. Passe de contrôle **indépendante**, rejouée le
**16 août 2026 à 21 h 00 UTC**, sur la même stack et sans aucune dérogation, avec la **largeur 320 px**
ajoutée — c'est celle que le §8 du brief nomme (« pas de défilement horizontal à 320 px »), et elle
n'avait jamais été mesurée :

| Page | 360 × 800 | 320 × 800 |
|---|---|---|
| Accueil `/` | `scrollWidth` 360, `clientWidth` 360 → **0 px** | 320 / 320 → **0 px** |
| Connexion `/wp-login.php` | 360 / 360 → **0 px** | 320 / 320 → **0 px** |
| `/la-demarche/` | 360 / 360 → **0 px** | 320 / 320 → **0 px** |
| `/accessibilite/` | 360 / 360 → **0 px** | 320 / 320 → **0 px** |
| `/mentions-legales/` | 360 / 360 → **0 px** | 320 / 320 → **0 px** |

Dix mesures, deux largeurs, cinq pages, aucun débordement. La mesure est `scrollWidth - clientWidth`
sur `documentElement` après `networkidle` — la même expression que `captures.mjs`, pour que les deux
passes soient comparables.

---

## 8. Rejouer ces mesures

```sh
# Prérequis : la stack tourne, et axe-core + playwright-core sont résolubles.
# Ils ne le sont PAS depuis le dépôt : les installer hors dépôt.
#   npm install axe-core playwright-core
# tests/README.md documente la variable MASSIFS_NODE_MODULES prévue pour cela.

node docs/recette/outils/recette.mjs  http://localhost:3002/ http://localhost:3002/la-demarche/
node docs/recette/outils/captures.mjs ./captures accueil=http://localhost:3002/
```

`recette.mjs` relève axe, les origines, le poids par type, les titres et le rendu sans JavaScript.
`captures.mjs` prend les captures et mesure le débordement horizontal.
