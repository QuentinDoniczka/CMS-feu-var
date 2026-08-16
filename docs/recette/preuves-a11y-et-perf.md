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

---

## 7. Captures

Desktop 1440 × 900 et mobile 360 × 800, page entière, **sans aucune dérogation CSP**.
Le débordement horizontal (`scrollWidth - clientWidth`) est **nul sur les dix captures**.

`captures/` — `accueil`, `connexion`, `demarche`, `accessibilite`, `mentions`, chacune en
`-desktop.png` et `-mobile-360.png`.

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
