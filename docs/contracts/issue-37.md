# Contrat d'interface — Issue #37 — Corriger le placement de `.carte__message` dans le sélecteur de jour

**Gelé le** 18 août 2026 · **Par** `lead-issue-cms`, chaîne #37 · **Statut** contraignant.

**Amende** [`docs/contracts/issue-7.md`](issue-7.md) — **A-20**. L'amendement est écrit **dans le contrat
#7 lui-même**, à A-20, dans la forme employée par la chaîne #50 pour A-9, et il l'a été **avant** toute
modification de code. Le lire là-bas : personne ne doit pouvoir relire A-20 sans voir la rectification.

**Applique** `design-system/MASTER.md` **v2.6**, §11.3 et **§17.2 ligne 1** — la seule ligne que cette
révision ouvre en aval, et elle nomme `templates/parts/carte.php` comme le seul fichier à corriger.

---

## 1. Périmètre d'écriture

Arbre de travail unique, mono-branche, deux chaînes sœurs actives (#39, #45). **Quatre fichiers, pas un
de plus** :

| Fichier | Qui écrit |
|---|---|
| `wp-content/themes/massifs/templates/parts/carte.php` | `dev-front-cms` |
| `wp-content/themes/massifs/assets/css/carte.css` | `dev-ux-cms` |
| `docs/contracts/issue-7.md` (bloc A-20 **seul**) | le lead, fait |
| `docs/contracts/issue-37.md` | le lead, ce document |

**Hors empreinte, et la règle est absolue** : `assets/js/carte/carte.js`, `functions.php`,
`front-page.php` (chaîne #39), tout `wp-content/plugins/**` et `page-mentions-legales.php` (chaîne #45),
`design-system/MASTER.md` (`lead-design-cms`), et **tout** `tests/rendu/` — qui se **lance**, jamais ne
s'édite, ne se remise ni ne se commite.

---

## 2. L'approche retenue — C

`.carte__message` devient **fils de `.carte__barre`, placé après `.carte__jours`**. Il cesse d'être
élément de grille et élément flex de la racine.

**Le bloc `@media (min-width: 56.25rem)` de `carte.css` (§9) n'est pas ouvert** — ni la ligne `1fr`, ni
`.carte__toile { grid-row }`, ni la colonne de 380 px. Ce n'est pas un oubli : c'est la décision. Le
placement automatique devient sans objet **définitivement**, y compris pour une hypothétique seconde
attribution.

### Pourquoi la recette littérale de l'issue est refusée

L'issue prescrit `grid-template-rows: auto auto 1fr`, `.carte__toile { grid-row: 3 }` et
`.carte__panneau { grid-row: 3 }`. **Cette recette régresse dans le cas nominal.**

Quand demain est publié, `.carte__message` est **absent du DOM** — la garde
`'' !== $message_suivant['texte']` ne rend pas le nœud, elle ne le masque pas. Le curseur de placement
automatique trouve alors la ligne 2 libre et y pose `.carte__attribution`, **entre la barre de jour et la
toile**.

**Mesuré dans Chrome** (playwright, `channel: chrome`), `getBoundingClientRect().top` de
`.carte__attribution` contre celui de `.carte__toile` :

| Disposition | message présent | message absent (nominal) |
|---|---|---|
| Recette de l'issue, 1200 px et 900 px | 414 vs 131 — sous la toile | **559 vs 598 — AU-DESSUS de la toile** |
| Approche C, 1200 px et 900 px | 1332 vs 1057 — sous la toile | 1791 vs 1463 — sous la toile |

Sous le point de rupture (899 px, 360 px) tout est en flux flex et aucune disposition ne régresse.

---

## 3. Le nœud `.carte__message` — forme figée

```
.carte__barre[hidden]
├── .carte__jour[data-jour]        (un par jour)
├── .carte__jours[role=group]      (les deux boutons)
└── .carte__message#carte-message[hidden]   ← NOUVELLE POSITION, dernier enfant
```

Invariants, tous opposables :

- **`id="carte-message"` inchangé.** `aria-describedby` se résout par identifiant, indépendamment de
  l'ordre du DOM, et la cible n'est **jamais** un ancêtre du bouton qui la référence.
- **L'attribut `hidden` initial est conservé.** C'est la moitié opposable d'A-20.
- **`.carte__message` ne porte pas `data-jour`.** Conséquence d'A-20 qui survit à l'amendement.
- **Le contenu du nœud n'est pas restructuré** : le `<time>` conditionnel « Reprise le {date}. » reste tel
  quel, composé depuis `massifs_saison()['prochaine_ouverture']`. **Le thème ne rédige jamais cette date.**
- La garde `'' !== $message_suivant['texte']` est **conservée telle quelle** : la présence du nœud reste le
  signal que demain n'est pas publié.

### Sans JavaScript — le point qui a motivé l'amendement

Deux verrous au lieu d'un : `.carte__barre` porte `hidden`, et `.carte [hidden] { display: none !important }`
(`carte.css` §1) masque **tout le sous-arbre**. Le nœud est donc **plus** protégé qu'aujourd'hui, pas
moins. L'affirmation contraire, portée par A-20 et recopiée dans un commentaire de `carte.php`, est fausse
et rectifiée aux deux endroits.

### Ce que `carte.js` fait, et qu'il ne faut pas casser — fichier HORS EMPREINTE

Aucune modification n'est requise, et **aucune n'est autorisée** :

- `racine.querySelector('.carte__message')` est un sélecteur de **descendant** : il continue de trouver le
  nœud fils de la barre.
- `barre.hidden = false; message.hidden = false; toile.hidden = false;` puis `L.map(toile, …)` : le
  message contribue toujours sa hauteur **avant** que Leaflet mesure la toile. Aucune `invalidateSize()`
  n'est requise, et l'assertion de recette 3 reste tenue.
- `querySelectorAll('.carte__attribution')` itère au pluriel — le nombre d'attributions ne change pas.

**Legs** : le commentaire de `carte.js` qui décrit barre et message comme « ses frères dans la colonne
flex de la racine » devient inexact. Hors empreinte, **non corrigé**, remonté au rapport.

---

## 4. Chaînes fournies par le serveur — §11.3 v2.6

`MASTER.md` §11.3 est une **liste fermée**, portée à **dix chaînes au lieu de huit** par l'amendement
`[v2.6 — 18 août 2026]`. Trois littéraux de `carte.php` rendent, **pour le jour suivant**, la chaîne du
**jour affiché**. Ils reçoivent la variante datée, **mot pour mot** :

| Emplacement | Avant | Après |
|---|---|---|
| bras `'indisponible'` du `match()` | `Information du jour non disponible. Consultez la carte officielle de la préfecture.` | `Information de demain non disponible. Consultez la carte officielle de la préfecture.` |
| bras `'hors_saison'` du `match()` | `Dispositif estival inactif.` | `Dispositif estival inactif demain.` |
| repli `catch ( \UnhandledMatchError )` | `Information du jour non disponible. Consultez la carte officielle de la préfecture.` | `Information de demain non disponible. Consultez la carte officielle de la préfecture.` |

**Le repli du `catch` est corrigé, et ce n'est pas un détail** : c'est le chemin par lequel un état inconnu
devient une phrase. Le laisser en l'état ferait de lui le seul endroit du gabarit qui affirme encore le
mauvais jour, et c'est la branche la moins observée.

**Ne bougent pas** : le bras `'disponible'` (texte vide), le bras `'non_encore_publie'` (il nomme déjà son
jour), et le suffixe conditionnel ` Reprise le {date}.`. Sans date de reprise, la variante se réduit à
`Dispositif estival inactif demain.` — exactement ce que §11.3 prescrit.

### Interdit nommé, et il est le vrai piège

**Aucune chaîne du *jour affiché* n'est touchée nulle part.** Elles sont justes, elles sont recopiées dans
du code livré et dans une recette, et une passe de « cohérence » qui les réécrirait au passage **casserait
la liste fermée dans l'autre sens** (§17.2, avertissement final). Surface vérifiée au `grep` sur tout
`wp-content/` — les copies suivantes sont **légitimes et hors empreinte** :
`front-page.php` l. 93 / 217 / 264 · `etats-vides.php` l. 155 / 158 ·
`plugins/massifs-core/includes/admin/ecran-publication/messages.php` l. 163-164.

**Interdit également** : composer la phrase par variable (« Information du {jour} non disponible. »).
`MASTER` §15, **D-32**, la range explicitement parmi les alternatives écartées — la liste fixe des
**chaînes entières**.

### Hors scope, et pourquoi

`$etiquettes_hors_niveau` (`carte.php`) porte `information non disponible` et
`dispositif estival inactif` comme **étiquettes courtes de pastille**, rendues pour **les deux jours** via
`data-jour`. Ce sont des chaînes de **MASTER §8.5**, pas du §11.3. **§8.5 n'a pas été amendé.** Leur
inventer une variante datée serait inventer un fait de domaine — interdit. Elles ne bougent pas.

---

## 5. Le commentaire A-20 de `carte.php` — rectification

Le commentaire qui précède le nœud est **factuellement faux** et doit être réécrit, pas déplacé. Il :

1. affirme qu'être **fille** de `.carte__barre` rendrait la phrase « visible sans JavaScript » — c'est
   l'inverse de la vérité (§3 ci-dessus) ;
2. porte un renvoi périmé, `carte.css l. 308-311`.

Le texte réécrit doit : **restater la moitié opposable d'A-20** (le nœud reste `hidden` dans le HTML servi,
`carte.js` seul le démasque, au même instant que la barre, avant `L.map`, sans interaction) ; **conserver**
la conséquence « ne porte pas `data-jour` » ; **renvoyer à l'amendement** daté de `docs/contracts/issue-7.md` ;
et **nommer le sélecteur** `.carte [hidden] { display: none !important }` **plutôt qu'un numéro de ligne** —
les deux éditions de cette chaîne déplacent les lignes l'une de l'autre, et un numéro écrit aujourd'hui est
faux demain.

---

## 6. Frontière `dev-front-cms` / `dev-ux-cms`

Les deux travaillent **en parallèle**, chacun sur **un seul fichier**, et ne se lisent jamais.

| | `dev-front-cms` — `carte.php` | `dev-ux-cms` — `carte.css` |
|---|---|---|
| Déplacement du nœud dans `.carte__barre` | ✅ | ❌ |
| Les trois littéraux §11.3 (dont le `catch`) | ✅ | ❌ |
| Réécriture du commentaire A-20 | ✅ | ❌ |
| `padding-inline` de `.carte__message` | ❌ | ✅ |
| Passage à la ligne dans le flex de la barre | ❌ | ✅ |
| Commentaires de `carte.css` | ❌ | ✅ |

**Aucun nom de classe, aucun attribut, aucun identifiant ne change.** Le seul fait nouveau que `dev-ux-cms`
doit tenir pour acquis est **la position du nœud**, et il n'a pas besoin de lire `carte.php` pour cela.

### Ce que `dev-ux-cms` doit faire, et rien d'autre

- **Retirer `padding-inline: var(--gouttiere)`** de `.carte__message` : la barre le fournit déjà
  (`.carte__barre`, `padding-inline: var(--gouttiere)`). Le laisser doublerait la gouttière.
- **Forcer le message sur sa propre ligne** dans le `flex-wrap: wrap` de la barre, pour qu'il ne partage
  jamais une rangée avec `.carte__jours`.
- **Réécrire le commentaire** de la règle : « SŒUR de la barre, pas fille : elle porte donc sa propre
  gouttière » devient faux dans ses deux moitiés.
- **Corriger le ratio cité** : le message passe de `--c-charbon-doux` sur `--c-carte-fond` (**6,82:1**) à
  `--c-charbon-doux` sur `--c-calcaire` (**7,29:1**). Les deux sont AA ; le second est **meilleur**.
  **7,29:1 est déjà mesuré et publié** — `carte.css` §7 le cite pour la même paire, et `MASTER` l. 480 le
  porte. **Aucun nombre nouveau n'est calculé ni inventé.**
- **Gain acquis sans rien écrire** : `.carte__barre > * { min-inline-size: 0 }` s'applique désormais au
  message — un garde-fou contre le défilement horizontal à 360 px qu'il n'avait pas.

**Aucun littéral brut.** L'en-tête de `carte.css` ferme la liste : jetons, zéros, mots-clés, et les valeurs
**structurelles** (`100 %`, `56.25rem`) plus quatre littéraux nommés `L-11 / L-13 / L-14 / L-15`. Il
n'existe **aucun `flex-basis` dans tout le thème** — pas de précédent maison à suivre ; `100 %` est en
revanche explicitement autorisé par cet en-tête, et `.carte` l'emploie déjà en `inline-size: 100%`. Le
mécanisme retenu doit être **commenté sur place** pour que `refacto-cms` ne le lise pas comme une valeur nue.

---

## 7. Interdits

- Le thème ne calcule **aucune** règle métier : ni saison, ni péremption, ni date de reprise, ni formatage
  de niveau. La date de reprise vient de `massifs_saison()`.
- Le thème ne rédige **aucune** phrase publique hors de la liste fermée du §11.3.
- **`carte.js` n'est pas modifié** — pas une ligne, pas un commentaire.
- **Le bloc `@media (min-width: 56.25rem)` n'est pas ouvert.** Son absence de diff est la décision.
- **Aucune correction opportuniste.** Le tassement à 200 % / 1366×768 (R ≈ 276 px) est une **condition
  préexistante** : elle s'observe et se rapporte, elle ne se corrige pas ici.
- **`tests/rendu/` ne reçoit aucune écriture.** `recette-rendu.mjs` est modifié-non-commité et
  `fraicheur.php` non suivi — reliquats d'un lot précédent, propriété d'autrui.
- Le commit est **scopé à une liste explicite de fichiers**. Jamais `git add -A` : `design-system/MASTER.md`
  est modifié-non-commité par `lead-design-cms` et **ne m'appartient pas**.

---

## 8. Contrôle de recette légué — à router vers `test-integration-cms`

**Cette chaîne n'écrit pas ce contrôle** ; elle en fournit le texte exact au niveau lot.

Il doit s'affirmer **au-dessus de 900 px**, dans **les deux cas de DOM** — message présent, et message
absent (demain publié) : `.carte__attribution` a un `getBoundingClientRect().top` **strictement supérieur**
à celui de `.carte__toile`.

Le second cas est celui qui compte : c'est le cas **nominal**, c'est celui que la recette prescrite par
l'issue cassait, et c'est le seul qui distingue l'approche C de la recette refusée.

---

## 9. Trou de couverture — déclaré, non refermé

**`recette-rendu.mjs` l. 1015 crée le contexte 360 px avec `javaScriptEnabled: false`**, comme la quasi-
totalité des scénarios. **Aucun scénario automatisé ne mesure la barre de jour à 360 px avec JavaScript
actif** — c'est précisément le cas que l'approche C modifie.

Ce trou **n'est pas refermable par cette chaîne** (`tests/rendu/` est en lecture seule pour elle). La
vérification est donc **manuelle**, et son résultat est rapporté **tel qu'observé** — jamais déduit,
jamais présenté comme fait si elle n'a pas pu être menée.

---

## 10. Arbitrages

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| **A-37.1** | La liste de tâches de l'issue prescrit une reprise de la grille | **Refusée, et non « adaptée »** | Elle régresse dans le cas nominal, **mesuré** : l'attribution remonte entre la barre et la toile quand le message est absent du DOM. Les trois cases de grille sont **supersédées, jamais cochées** |
| **A-37.2** | Où vit l'amendement daté d'A-20 | **Dans `docs/contracts/issue-7.md`, à A-20** — et référencé ici | Un amendement qui ne vit que dans le contrat neuf est **invisible** à la chaîne qui rouvrira l'ancien. C'est ainsi qu'une décision corrigée se ré-applique dans sa forme fausse six mois plus tard. Précédents : #50 dans le contrat #7, #71 au §14 du contrat #9 |
| **A-37.3** | Le `catch ( \UnhandledMatchError )` est-il dans le périmètre §11.3 ? | **Oui** | Il porte la même chaîne fautive et il est la branche qui **s'exécute sur état inconnu**. Le laisser en ferait le dernier endroit qui affirme le mauvais jour |
| **A-37.4** | Les étiquettes courtes `$etiquettes_hors_niveau` suivent-elles ? | **Non** | Elles relèvent de **§8.5**, qui n'a pas été amendé. Leur inventer une variante datée serait inventer un fait de domaine |
| **A-37.5** | Corriger le tassement 200 % / 1366×768 au passage ? | **Non** | Condition **préexistante**, sans rapport avec #37 et non aggravée par C. La corriger ici serait une extension de périmètre non arbitrée |
| **A-37.6** | Le commentaire périmé de `carte.js` | **Non corrigé, légué** | Hors empreinte. Une chaîne sœur partage l'arbre et il n'existe aucune branche pour rattraper un écrasement |
| **A-37.7** | Faut-il un `invalidateSize()` ? | **Non, et la question est distincte de #37** | Le message est démasqué **avant** `L.map`, dans la barre comme avant dans la racine. Il n'existe par ailleurs **aucun `invalidateSize()` dans tout `carte.js`** — défaut réel mais **antérieur**, sans rapport, remonté pour une issue séparée |
