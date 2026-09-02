# Contrat d'interface — Issue #70 — Exercer la recette R-29 : horodatage malformé sur fixture seedée

**Gelé le 2 septembre 2026** par `lead-issue-cms` (chaîne #70). Liant à partir de ce point.

**Lignes de DoD servies** : §12 robustesse (aucune page tronquée sur donnée invalide) · §3 (utilisable sans
JavaScript) · règle produit *jamais un statut périmé comme courant* — l'omission plutôt que l'invention.

---

## 0. Pourquoi ce contrat existe alors que l'issue est mono-face

`CLAUDE.md` et `docs/decisions/projet-vitrine.md` §2 dispensent de gel une issue **mono-face**. Celle-ci
l'est **par les fichiers** — elle ne touche que `tests/rendu/**`, ni thème ni extension. Elle ne l'est
**pas par les auteurs** : `leaddev-back-cms` a planifié `tests/rendu/fraicheur.php` et
`leaddev-front-cms` a planifié le scénario de `tests/rendu/recette-rendu.mjs`, **en aveugle l'un de
l'autre**, et ils ont figé **deux interfaces incompatibles** :

| Face | Interface proposée |
|---|---|
| back | `wp eval-file …/fraicheur.php <mode>` — **sept modes nommés, un seul argument**, aucun n'en accepte un second |
| front | `executerFixture( 'fraicheur', publication, releve )` — **un mode unique à deux paramètres** |

Ni l'une ni l'autre ne fonctionne contre l'autre : le mode `fraicheur` n'existe pas côté fixture, et la
fixture refuse un second argument. C'est exactement la classe de défaut que le gel de contrat existe pour
refermer — la dispense du §2 vise le cas où *aucune* interface n'est à réconcilier, pas celui-ci.

**Le gel est donc rétabli pour cette issue.** Ce document fait foi contre les deux plans.

## Empreinte d'écriture — exhaustive

```
tests/rendu/fraicheur.php         (neuf — la fabrique d'états de fraîcheur)
tests/rendu/recette-rendu.mjs     (le scénario neuf + l'extraction d'executerFixture)
docs/contracts/issue-70.md        (ce fichier)
```

Rien d'autre. Sont **hors empreinte** et ne sont ni créés, ni modifiés, ni déplacés : tout
`wp-content/**` (thème **et** extension), `docker-compose.yml`, `tests/README.md`, `tests/run.sh`,
`tests/scenarios/**`, `docs/BRIEF.md`, et `docs/contracts/issue-29.md`.

> **Deux autres chaînes écrivent dans le même arbre de travail.** #19 possède
> `includes/ingest/prefecture/**`, `tests/scenarios/**` et `tests/run.sh` ; #69 possède
> `domain/massifs/build/verifier.mjs`. `tests/README.md` est hors empreinte **délibérément** : la chaîne
> #19 ajoute un scénario, et le tableau des scénarios de ce README est l'endroit naturel où elle
> l'inscrira. Le nouveau scénario de rendu n'y sera donc **pas documenté** — voir §8, dette assumée.

---

## 1. Le défaut réel — l'énoncé de l'issue est partiellement faux

**Ce que l'issue affirme** : il suffirait de « seeder une fixture stable avec horodatage malformé » pour
exercer les gardes livrées par #29 (trois `is_string()` + trois `try/catch ( \InvalidArgumentException )`).

**Ce qui est vérifié dans le code et MESURÉ dans le conteneur** : **c'est impossible, par construction.**
Un horodatage malformé ne peut pas atteindre le thème sous forme de chaîne refusée. Quatre barrages,
tous **hors du thème** :

1. `RegistreReleves::entree()` re-parse `instant` et `publie_le` via `Horloge::instant_depuis_chaine()`
   et **supprime la clé** (`continue`) au parsing raté.
2. `Fraicheur::evaluer()` porte une **seconde ceinture** : `catch ( InvalidArgumentException ) { $releve = null; }`.
3. `massifs_enregistrer_releve_reussi()` refuse un instant malformé en amont (`erreurs: ['instant_invalide']`).
4. `massifs_enregistrer_statut()` refuse un `publie_prefecture_le` malformé (`publie_prefecture_le_invalide`).

Aucun `apply_filters` ne traverse `includes/domain/fraicheur/`.

> **MESURÉ le 2 septembre 2026 dans le conteneur `massifs_wordpress`.** En seedant l'option directement —
> `update_option( 'massifs_dernier_releve', array( 'prefecture' => array( 'instant' => 'pas-une-date',
> 'publie_jour' => massifs_jour_courant(), 'publie_le' => 'pas-une-date' ) ), true )` —
> `massifs_fraicheur( null )` rend :
> `{"dernier_releve_le":null, …, "perimee":true, "publie_prefecture_le":null, "dispositif_actif":true}`.
> **La valeur malformée est neutralisée en `null`, et `perimee` bascule à `true`.**

**Conséquence, et c'est le cœur du recadrage** : la fixture prouve un **résultat**, pas un **mécanisme**.

- **Résultat prouvé** — *un horodatage corrompu en base ne produit jamais ni page tronquée, ni valeur
  inventée, et rend le site plus prudent, pas moins.* C'est une propriété produit, et elle sert
  littéralement les trois lignes de DoD de l'issue.
- **Mécanisme non prouvé** — le `try/catch` de `front-page.php` reste **non exercé par fixture**. Le seul
  geste qui l'atteindrait est l'injection de source dans `front-page.php` (protocole `s23`), **hors
  empreinte**, ou le relâchement d'une ceinture de l'extension, **formellement interdit** : affaiblir le
  code qui protège contre l'affichage d'une donnée inventée pour prouver qu'un filet de sécurité
  fonctionne serait un marché perdant.

**Cette distinction est écrite dans l'en-tête des deux fichiers livrés et dans le corps du commit.** Le
contrat #29 l'exige en toutes lettres : « À écrire dans la recette, pas à cacher. »

---

## 2. Le livrable réel — les quatre combinaisons du tableau A-2

`docs/contracts/issue-5.md` (révision 7, arbitrage A-44) gèle quatre rendus de
`<p class="ardoise__fraicheur">`, `validite` présente. **Trois des quatre n'avaient jamais été rendues
par aucune fixture** : `etats.php jour-nominal` ne produit que la combinaison 2.

| # | `publication` | `releve` | Rendu de l'ardoise |
|---|---|---|---|
| **c1** | présente | présente | `Statuts du {date_longue}, publiés la veille à {heure} par la préfecture — relevés sur ce site le {date_longue} à {heure}.` |
| **c2** | absente | présente | `Statuts du {date_longue} — relevés sur ce site le {date_longue} à {heure}.` |
| **c3** | présente | absente | `Statuts du {date_longue}, publiés la veille à {heure} par la préfecture.` |
| **c4** | absente | absente | `Statuts du {date_longue}.` |

> **MESURÉ le 2 septembre 2026, en HTTP réel sur `http://localhost:3002/`.** Les quatre existent, et
> **c1 a été rendue pour la première fois de l'histoire du projet** :
>
> | # | Ardoise, relevée octet pour octet |
> |---|---|
> | c1 | `Statuts du <time datetime="2026-09-02">mercredi 2 septembre 2026</time>, publiés la veille à 18 h 40 par la préfecture — relevés sur ce site le <time datetime="2026-09-02T17:30:40+02:00">mercredi 2 septembre 2026</time> à 17 h 30.` |
> | c2 | `Statuts du <time datetime="2026-09-02">mercredi 2 septembre 2026</time> — relevés sur ce site le <time datetime="2026-09-02T17:30:19+02:00">mercredi 2 septembre 2026</time> à 17 h 30.` |
> | c3 | `Statuts du <time datetime="2026-09-02">mercredi 2 septembre 2026</time>, publiés la veille à 18 h 40 par la préfecture.` |
> | c4 | `Statuts du <time datetime="2026-09-02">mercredi 2 septembre 2026</time>.` |
>
> Dans les quatre : **HTTP 200**, un `</main>`, un `</html>`, **une** ancre `id="liste"`, **zéro**
> occurrence de `Fatal error` / `Warning:` / `Notice:` / `rreur critique`. Aucune virgule orpheline,
> aucun tiret suspendu, aucun double espace : **l'omission est pure**.

### La preuve, ce sont les PAIRES

Chaque combinaison est atteignable de **deux façons**, et les deux doivent rendre **exactement la même
chose** :

- **absence légitime** — la donnée n'existe pas (chemin nominal) ;
- **corruption seedée** — la donnée existe mais est malformée, donc neutralisée en `null` par
  l'assainisseur (chemin de l'issue #70).

**C'est cette égalité qui est la preuve produit** : la page est moins complète, elle n'est jamais fausse.
Un cas isolé ne prouverait rien ; la paire prouve que la corruption ne crée aucun rendu nouveau.

---

## 3. ARBITRAGE A-1 — Une grille à deux axes, arité 2

**Décision : l'interface de `leaddev-front-cms` est retenue. La forme à sept modes nommés de
`leaddev-back-cms` est écartée — c'est le back qui s'aligne.**

**Invocation gelée** :

```
wp eval-file /massifs-tests/rendu/fraicheur.php fraicheur <publication> <releve>
```

Un mode unique, `fraicheur`, **arité 2**. Chaque axe prend l'une de trois valeurs :

| Valeur d'axe | Ce que la fixture écrit |
|---|---|
| `valide` | un instant réel, figé (§ « Instants figés ») |
| `malforme` | `'pas-une-date'` **seedé dans l'option**, donc neutralisé en `null` par l'assainisseur |
| `absent` | rien — la clé n'existe pas |

Un axe hors de ces trois valeurs, ou une arité autre que 2, écrit la grammaire complète sur `STDERR` et
sort en code 1 — patron d'`etats.php`.

### Pourquoi la grille plutôt que sept noms

La démonstration produit de cette issue est **« corruption ≡ absence »**. Dans une grille, cette
équivalence est une **invariance structurelle** — `malforme` et `absent` sur le **même axe** rendent le
même HTML, et l'axe est le sujet du test. Avec sept noms plats, le jumelage n'est qu'implicite : il
repose sur le fait que quelqu'un a bien nommé ses cas, et un renommage malheureux le dissoudrait sans
qu'aucune assertion ne rougisse. **La grille rend la propriété falsifiable ; les noms la rendaient
seulement vérifiable à la lecture.**

La grille mappe en outre **directement les deux coordonnées du tableau A-2** (`publication` × `releve`),
ce que sept noms plats ne font pas.

> **Écarté, et pourquoi c'est écrit ici** : la forme `temoin-complet` / `sans-publication` /
> `publication-corrompue` / `sans-releve` / `releve-corrompu` / `sans-fraicheur` / `fraicheur-corrompue`
> avait pour elle la lisibilité de la trace de recette. Elle est perdue — compensée par l'intitulé de
> chaque cas, qui nomme sa combinaison et son chemin dans la sortie. Un contrat sert autant à dire ce
> qu'on n'a pas fait qu'à figer ce qu'on fait.

### Instants figés — déterminisme obligatoire

| Constante | Valeur | Fondement |
|---|---|---|
| `INSTANT_PUBLICATION` | **la veille à 17 h 00 Europe/Paris** | `docs/decisions/source-prefecture.md` §4.10, « Heure de publication — contradiction résolue ». Établie **par relevé** : en-tête HTTP `Last-Modified` du JSON = `Mon, 10 Aug 2026 15:00:05 GMT` = 17 h 00 Paris. Le « vers 18–19 h » de `docs/BRIEF.md` §4.2 est la source **périmée** ; le brief n'est pas modifié — l'écart est remonté au niveau lot |
| `INSTANT_RELEVE` | **le jour courant à 06 h 00 Europe/Paris** | doit rester **strictement dans les 24 h** pour que `perimee` reste `false` sur `temoin-complet`, `sans-publication` et `publication-corrompue`. Une valeur figée, jamais `time()` : comparer deux pages dont les heures diffèrent pour une raison qui n'est pas celle qu'on teste produirait une preuve fausse |

**Aucun instant ne dérive de l'heure d'exécution.** C'est la condition pour que deux cas soient
comparables octet pour octet.

---

## 4. ARBITRAGE A-2 — La ligne `ETAT`, et l'interdit de recomposer une date

### Contrainte dure du harnais
`recette-rendu.mjs` parse la ligne par `/(\w+)=([\w-]+)/g`. **Aucune valeur ne peut contenir d'espace, de
`:` ou de `+`** — or un instant ISO contient les trois, et une date longue française contient des espaces.

### Forme gelée

```
ETAT fraicheur axe_publication=<valide|malforme|absent> axe_releve=<valide|malforme|absent> etat=<etat_global> bloc=<0|1> validite=<0|1> publication=<0|1> releve=<0|1> perimee=<0|1> actif=<0|1> jour=<AAAA-MM-JJ> combinaison=<c1|c2|c3|c4> jour_long=<b64u> pub_heure=<b64u> rel_long=<b64u> rel_court=<b64u> rel_heure=<b64u>
```

| Clé | Type | Valeurs | Ce qu'elle rend possible |
|---|---|---|---|
| `axe_publication`·`axe_releve` | mot | `valide\|malforme\|absent` | **l'écho des arguments reçus** : le scénario vérifie que la fixture a bien compris la demande, et l'intitulé du cas devient lisible dans la trace |
| `etat` | mot | `disponible` attendu | **précondition anti-vacuité** (§5) |
| `bloc` | `0\|1` | `1` attendu | le bloc de fraîcheur est-il atteint |
| `validite`·`publication`·`releve` | `0\|1` | — | quelle combinaison A-2 le **domaine** rapporte |
| `perimee`·`actif` | `0\|1` | — | conditionne l'assertion du bandeau de péremption (§6) |
| `jour` | `AAAA-MM-JJ` | — | contrôle de cohérence |
| `combinaison` | mot | `c1\|c2\|c3\|c4` | ce que la fixture **prétend** produire, à confronter au triplet `validite`/`publication`/`releve` **relu dans le domaine** — un désaccord est un défaut de fixture, pas de thème |
| `jour_long` | **base64url** | — | `massifs_horodatage( fraicheur['evalue_le'] )['date_longue']` |
| `pub_heure` | **base64url** | vide si absente | `massifs_horodatage( publie_prefecture_le )['heure']` |
| `rel_long`·`rel_court`·`rel_heure` | **base64url** | vides si absent | `date_longue`, `date_courte`, `heure` de `dernier_releve_le` |

**Toutes les valeurs sont relues dans le DOMAINE** (`massifs_fraicheur()`, `massifs_synthese_du_jour()`,
`massifs_horodatage()`), **jamais celles que la fabrique a cru écrire.** Règle explicite d'`etats.php`,
reprise sans amendement.

**base64url** (alphabet `A-Za-z0-9-_`, sans remplissage) : c'est le seul encodage qui satisfasse
`[\w-]+`. Le base64 standard est **interdit** ici — il contient `+` et `/`, que le motif du harnais
coupe. Côté JS : `Buffer.from( v, 'base64url' ).toString( 'utf8' )`.

### Interdit de recomposition — qui possède quoi

- **Le serveur possède les VALEURS.** Le scénario ne formate jamais une date, ne traduit jamais un nom de
  mois ou de jour, ne calcule jamais une heure. Il les reçoit encodées.
- **Le scénario possède le GABARIT.** Les quatre phrases du §2 sont la **spécification testée** : elles
  vivent dans le scénario, en littéraux, et c'est leur confrontation au rendu qui constitue la preuve.
- **La fixture n'assemble JAMAIS la phrase attendue.** Si elle le faisait, elle dupliquerait la logique
  de séparateurs de `front-page.php` et le test comparerait deux implémentations de la même règle au lieu
  de vérifier la règle. **C'est la ligne de partage la plus importante de ce contrat.**

### Espaces insécables
`Horodatage::formater()` construit `heure` avec `INSECABLE = "\u{00A0}"` (vérifié, `Horodatage.php` l. 33
et 93) : `17` U+00A0 `h` U+00A0 `30`. Or `texteSource()` applique `replace( /\s+/g, ' ' )`, et le `\s` de
JavaScript **matche U+00A0** (vérifié : `/\s/.test(" ")` → `true`). **Les deux côtés de chaque
comparaison sont donc normalisés par la MÊME expression** avant `egal()`. Une comparaison qui
normaliserait un seul côté rougirait pour une raison qui n'est pas celle qu'elle teste.

---

## 5. ARBITRAGE A-3 — Chaque cas purge et pose sa propre précondition

L'issue #70 existe parce qu'une recette **passait sans rien prouver** : le jeu du conteneur était en
`indisponible`, le bloc gardé n'était jamais atteint, et tous les `count() === 0` étaient verts pour la
mauvaise raison.

> **VÉRIFIÉ le 2 septembre 2026** : `curl http://localhost:3002/` sur l'état par défaut du conteneur rend
> HTTP 200, 21 192 octets, **zéro `ardoise__fraicheur`**, zéro `ardoise__chiffre`. La prémisse de
> l'énoncé sur l'état **par défaut** est donc exacte — c'est sa généralisation à la fabrique qui est
> fausse : `jour-nominal` atteint bien le bloc, et `s23` l'asserte déjà.

**La disjonction d'empreintes protège les FICHIERS, jamais l'ÉTAT RUNTIME de la stack.** Trois chaînes
partagent la même base de données. Un état posé par une autre chaîne entre deux mesures est un scénario
d'apparence verte sans valeur.

**Gelé** :
1. Chaque mode **commence par une purge complète** et ne s'appuie sur aucun état antérieur.
2. Chaque cas du scénario **repose son mode** avant d'observer, sans exception ni mutualisation.
3. Avant toute assertion de rendu, le scénario asserte sa précondition sur la ligne `ETAT`, patron de
   `s22_publicationPartielle` (l. ~3053) et son commentaire : « Sans lui, une fixture muette rendrait
   tous les verts suivants sans valeur. » Au minimum `etat=disponible` et `bloc=1`, plus l'accord entre
   `combinaison` et le triplet `validite`/`publication`/`releve`.

### La base est PARTAGÉE, et l'interférence est MESURÉE — pas supposée

Trois chaînes écrivent dans la même base pendant ce lot. **Un cas peut voir sa précondition réécrite
entre son `arrange` et son `assert` par une chaîne voisine.** Ce n'est pas une hypothèse :

> **MESURÉ le 2 septembre 2026.** La chaîne #19 a instrumenté la stack et relevé, **alors que rien de sa
> propre chaîne ne tournait** : le compte de `wp_massifs_statuts` passant **25 → 16 → 25**, `MAX(id)`
> glissant de 4611 à 4636, et une lecture rendant `emissions=1, bilans=1` sur **100 lignes et 4
> identifiants distincts** pour un même massif, à deux secondes d'intervalle. Ses propres scénarios 13 et
> 57 ont rougi par intermittence pour cette seule raison, puis rendu sept verts consécutifs en fenêtre
> calme.
>
> **Constaté sur ce scénario même, fichier strictement inchangé entre les quatre exécutions** :
>
> | # | Conditions | Résultat |
> |---|---|---|
> | 1 | écriture concurrente de la chaîne #19 | 151 vertes / **7 rouges** — cas 3, cas 6, jumeaux 2/3, jumeaux 6/7 |
> | 2 | écriture concurrente de la chaîne #19 | 174 / **3** — cas 3, jumeaux 2/3 |
> | 3 | **fenêtre calme** (90 s sans writer, vérifiée avant et après) | **186 / 0** |
> | 4 | **fenêtre calme**, après le correctif #19 sur `ProjecteurPrefecture` | 172 / **4** — cas 6 seul, jumeaux 6/7 par conséquence |
>
> La fixture, elle, rejouée **trois fois de suite en isolation** sur le mode `malforme malforme`, rend
> `etat=disponible bloc=1` à l'identique. **Elle est déterministe : la non-reproductibilité vient de
> l'extérieur du fichier.**

**La cause n'est pas une course sur la DONNÉE, c'est une course sur le CODE.** Vérifié par
`docker inspect massifs_wordpress` : `wp-content/plugins/massifs-core` et `wp-content/themes/massifs`
sont **montés en direct** (`rw=true`) depuis l'arbre de travail partagé. Le code de production **non
committé** d'une chaîne voisine s'exécute donc à chaque requête HTTP de cette recette et à chaque
`wp eval-file`. Aucune base par chaîne ne refermerait ce trou : ce n'est pas la donnée qui change sous
les pieds du scénario, c'est le code qui la produit. Symptôme observé au cas 6 : la ligne `ETAT` rapporte
`etat=disponible bloc=1` — la fixture a bien écrit ses 25 statuts et le domaine les voit — puis la page
servie un instant plus tard rend `indisponible`, **sans qu'aucun conteneur `wpcli` ne tourne**.

**Conséquence de méthode, opposable** : un vert non reproductible n'est pas un vert. L'exécution 3
(186/0) **n'est pas retenue comme preuve** ; seules deux exécutions vertes consécutives, obtenues après
séquencement des chaînes voisines, valent recette. Retenir un vert isolé aurait fabriqué exactement le
faux vert que l'issue #70 existe pour supprimer.

**Règle opposable, à écrire dans l'en-tête du scénario** : ce scénario tourne sur une base partagée ;
chaque cas pose sa propre précondition, et **un rouge isolé se rejoue en fenêtre calme avant d'être
cru**. C'est une propriété du harnais, pas un aléa d'exécution. La chaîne suivante qui touchera ce
fichier doit le savoir — sans quoi elle « corrigera » un scénario sain, ou pire, **assouplira une
assertion qui rougissait légitimement**, fabriquant le faux vert que l'issue #70 existe pour supprimer.

**Pas d'`attendreRechargement()`.** Ce scénario ne touche **aucun** fichier de gabarit : la barrière
d'opcache (`opcache.revalidate_freq=2`, vérifié) ne le concerne pas, et il n'y a **ni cache de page ni
drop-in `object-cache.php`** dans la stack (vérifié : `wp-content/` ne contient ni `advanced-cache.php`
ni `object-cache.php`). Un changement d'état est visible à la requête suivante. **Ne pas copier cette
attente par mimétisme** — une attente qui n'attend rien masque les vrais écarts.

---

## 6. ARBITRAGE A-4 — Le bandeau de péremption, assertion la plus forte du lot

**Découverte non prévue par l'énoncé de l'issue, mesurée le 2 septembre 2026.**

Un horodatage de relevé corrompu neutralise `dernier_releve_le` en `null`, donc `perimee` bascule à
`true`, donc le bandeau apparaît :

```html
<div class="bandeau-alerte bandeau-alerte--peremption sur-sombre repere repere--bloc">
<p class="bandeau-alerte__texte">Donnée périmée.</p>
```

Compté sur les rendus réels : **0** occurrence de `bandeau-alerte--peremption` en c1 et c2, **1** en c3 et
c4.

**Autrement dit : un horodatage corrompu rend le site PLUS prudent, pas moins.** C'est la règle produit
« jamais un statut périmé comme courant » démontrée **en positif**, et non par simple absence. Elle est
gelée comme assertion obligatoire, **dans les deux sens** — présence quand elle est due, absence quand
elle ne l'est pas.

**Condition de saison, à ne pas oublier** : `perimee = actif && ( null === releve || age > seuil )`. Hors
période d'activité, `actif` est faux, `perimee` reste faux, et **le bandeau n'apparaît pas** même sans
relevé. L'assertion du bandeau est donc **conditionnée à `actif=1` lu sur la ligne `ETAT`**, jamais posée
en dur. Une assertion inconditionnelle rougirait tous les 1ers octobre, pour une raison qui n'est pas un
défaut.

---

## 7. Ce que le scénario asserte, cas par cas

**Clé du scénario : `fraicheur`. Fonction : `s34_ligneDeFraicheurQuatreCombinaisons`.** Inscrite au
tableau `SCENARIOS` (l. ~6334) après `pages`. Le dernier numéro utilisé était `s33`.

### Les huit cas — le livrable, non réductible

| # | Fixture | Combinaison | Rôle |
|---|---|---|---|
| 1 | `fraicheur valide valide` | **c1** | **témoin** — les trois propositions, jamais rendues avant #70 |
| 2 | `fraicheur malforme valide` | c2 | corruption de la publication |
| 3 | `fraicheur absent valide` | c2 | **jumeau `absent` de 2** |
| 4 | `fraicheur valide malforme` | c3 | corruption du relevé |
| 5 | `fraicheur valide absent` | c3 | **jumeau `absent` de 4** |
| 6 | `fraicheur malforme malforme` | c4 | corruption des deux |
| 7 | `fraicheur absent absent` | c4 | **jumeau `absent` de 6** |
| 8 | `etats.php absente` | — | **contre-témoin** : état `indisponible`, **aucune** `.ardoise__fraicheur` |

> **Les cas 3, 5 et 7 ne sont pas une variable d'ajustement.** Ce sont les jumeaux `absent` qui portent
> l'assertion **« malformé ≡ absent »**. Les couper viderait l'issue de sa démonstration et ne laisserait
> qu'une vérification de rendu de plus. **Les huit cas sont le livrable.**
>
> **Le cas 8 est obligatoire.** C'est lui — et lui seul — qui distingue « ligne de fraîcheur **omise** »
> de « état **`indisponible`** ». Sans lui, la recette redevient exactement ce que l'issue #70 reproche à
> l'existant : verte sans rien prouver. Il consomme le mode `absente` d'`etats.php`, ce qui est la
> justification directe de l'extraction du §8.

Pour **chacun** des huit cas, dans l'ordre, JavaScript **désactivé** (§3 du brief — l'information vit
dans le HTML rendu par PHP) :

1. **Précondition** (§5) : `etat=disponible`, `bloc=1`, accord `combinaison` ↔ triplet, et accord
   `axe_publication`/`axe_releve` ↔ arguments demandés.

   > **AMENDÉ le 2 septembre 2026, avant livraison — arbitrage A-10.** Le gel initial exigeait du **cas 8**
   > qu'il lise `etat=indisponible` sur une ligne `ETAT`. **C'est impossible** : le cas 8 consomme le mode
   > `absente` d'`etats.php`, qui n'émet que `ETAT absente`, sans aucune clé — et `etats.php` est **hors
   > empreinte**. Le contrat exigeait donc une chose que son empreinte lui interdisait de produire.
   >
   > **Le cas 8 lit sa précondition SUR LA PAGE** : `h1` unique portant « Information du jour non
   > disponible. », zéro `.ardoise__chiffre`, zéro `.ardoise__fraicheur`. C'est **plus fort** que la ligne
   > `ETAT`, pas un repli : c'est exactement ce que le visiteur reçoit, et l'état `indisponible` n'a de
   > sens que par ce qu'il montre. Relevé par `dev-front-cms`, qui a eu raison de diverger plutôt que de
   > forcer le contrat.

   **Une précondition en défaut ABANDONNE le cas** (`continue`), elle ne le laisse pas se poursuivre :
   sans cela, Playwright expire sur un sélecteur absent et emporte **les cas suivants et le
   contre-témoin** avec lui. Perdre le contre-témoin par effet de bord viderait la recette de sa
   falsifiabilité — c'est-à-dire exactement l'objet de l'issue.

   De même, la comparaison de jumeaux **rougit en NOMMANT le cas manquant** plutôt que de comparer deux
   chaînes vides : deux vides sont égaux, et le vert obtenu ne prouverait rien.
2. **HTTP 200.** Jamais 500.
3. **Document complet** : `</main>` **et** `</html>` présents.
4. **Ancre** : `[id="liste"]` présent **exactement une fois**.
5. **Ardoise** : la phrase attendue de la combinaison, **mot pour mot**, gabarit du scénario + valeurs
   serveur décodées, les deux côtés normalisés (§4). Pour c1/c2/c3/c4, `count( '.ardoise__fraicheur' ) === 1`.
6. **Caption** : `#liste caption.liste-statuts__resume`, mot pour mot (§7.1).
7. **Aucune valeur de repli** (§7.2).
8. **Bandeau de péremption** (§6), conditionné à `actif`, **et** vérification qu'il ne porte **aucune
   date interpolée** : son texte est exactement `Donnée périmée.`, sans quantième, sans heure, sans âge.
   Un bandeau qui **ne peut pas** fabriquer de valeur est une garantie structurelle, pas une observation
   de circonstance.
9. **La publication ne fuit jamais dans l'équivalent textuel** : `! caption.includes( 'publiés la veille' )`,
   **dans les huit cas**, y compris ceux où l'ardoise la rend. C'est la garde du contrat #6, arbitrage H.
10. **Aucune fuite technique** : `/Fatal error|Warning:|Notice:|Deprecated:|rreur critique/` absent du corps.
11. **Égalité de jumeaux** (§2) : le rendu du cas `malforme` est **identique** à celui du cas `absent` de
    la même combinaison — ardoise **et** caption. Trois paires : (2,3), (4,5), (6,7).

### 7.1 Le caption ne suit pas l'ardoise

`templates/parts/liste-statuts.php` (l. 165-220) compose son propre résumé, et **`dernier_releve_le` est
la seule clé de fraîcheur qu'il lit** — la clause « publiés la veille … » n'y est **jamais** rendue
(contrat #6, arbitrage H). Mesuré :

| Combinaison | `<caption id="liste-resume">` |
|---|---|
| c1, c2 | `Statuts du mercredi 2 septembre 2026 — relevés sur ce site le 2 septembre 2026 à 17 h 30.` |
| c3, c4 | `Statuts du mercredi 2 septembre 2026.` |

**Piège de format, gelé** : le caption emploie **`date_courte`** (« 2 septembre 2026 ») là où l'ardoise
emploie **`date_longue`** (« mercredi 2 septembre 2026 »). Les deux ne sont **pas** interchangeables :
d'où deux clés distinctes, `rel_court` et `rel_long`, sur la ligne `ETAT`. Une assertion qui les
confondrait rougirait pour une raison qui n'est pas celle qu'elle teste.

**Deux invariances croisées, à asserter** :
- de **c1 à c3** (la publication reste, le relevé part) : l'ardoise change **et** le caption change ;
- de **c1 à c2** (la publication part, le relevé reste) : l'ardoise change et le caption **reste
  identique**.

C'est ce qui démontre que les deux rendus sont réellement indépendants, et non deux vues d'un même calcul.

### 7.2 « Aucune valeur de repli » — assertion positive, jamais un simple zéro

Compter `0` élément ne prouve rien : c'est précisément ce que l'état `indisponible` produisait déjà. Là où
une proposition est omise, le scénario asserte **positivement** que la zone observée ne contient :

- **aucun ISO brut** — `/\d{4}-\d{2}-\d{2}T/` ;
- **aucun tiret ni mention de remplacement** — `« — »` isolé, `« - »`, `« n/a »`, `« date indisponible »`,
  `« inconnue »` ;
- **aucun fragment de la proposition omise** — ni `publiés la veille`, ni `relevés sur ce site`, selon la
  combinaison ;
- **aucune ponctuation orpheline** — la phrase se termine par exactement un `.`, sans `, .` ni ` — .`.

---

## 8. Extraction d'`executerFixture()` — refonte autorisée, preuve exigée

`poserEtat()` (l. 126-149) code en dur `etats.php`. Le scénario a besoin du **même** transport vers
`fraicheur.php`. **Autorisé** : extraire le corps en `executerFixture( fichier, ...parametres )`, et
réécrire `poserEtat()` comme `executerFixture( 'etats', mode, ...parametres )` — extraction **pure**,
signature et valeur de retour de `poserEtat()` **inchangées**.

> **~30 appelants existants en dépendent.** « Zéro changement de comportement » se **démontre en
> exécutant la recette complète**, jamais en lisant le diff. Si elle ne peut pas tourner en entier, le
> rapport dit **ce qui a réellement été exécuté** et ce qui ne l'a pas été. Une non-régression déduite
> n'est pas une non-régression vérifiée.

**Dette assumée** : le nouveau scénario **ne sera pas inscrit** au tableau des scénarios de rendu de
`tests/README.md` — fichier hors empreinte, que la chaîne #19 est susceptible de modifier au même moment.
À porter au niveau lot.

---

## 9. Interdits

- **Aucune modification de code de production**, sous aucun prétexte, **pas même temporairement pour
  prouver un mécanisme**. En particulier `Fraicheur.php`, `RegistreReleves.php` et `fraicheur/api.php` :
  relâcher une ceinture d'assainissement pour atteindre un `catch` affaiblirait exactement le code qui
  protège contre l'affichage d'une donnée inventée.
- **Aucune injection de source dans `front-page.php`.** Le protocole `s23` est légitime en soi, mais le
  fichier est hors empreinte et deux chaînes partagent l'arbre.
- **`docs/contracts/issue-29.md` n'est pas modifié.** Contrat gelé d'une issue livrée. Son défaut est
  **signalé** au §10, jamais réécrit.
- **Aucune assertion sur une ligne de journal `massifs_journaliser()`** — voir §10.
- La fixture n'assemble jamais une phrase attendue ; le scénario ne formate jamais une date (§4).
- Aucune source externe n'est contactée. Aucun cookie, aucune requête tierce, aucun JavaScript requis.
- Aucun cas ne suppose un état de départ (§5).

---

## 10. Signalé, non traité — une assertion de R-29 est INEXÉCUTABLE

**Le contrat #29 exige, en tête de sa recette R-29 :** « Dans les cas 1 et 2, **deux** lignes de journal
sont émises pour un seul défaut : **J-1 puis J-0**. […] **La recette doit attendre les deux.** »

**Cette assertion est inexécutable dans le conteneur du projet.**

`massifs_journaliser()` (`functions.php` l. 68-74) sort immédiatement sans `WP_DEBUG` :
```php
if ( '' === $message || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
```
Or `docker-compose.yml` fixe `WORDPRESS_DEBUG: 0`. **Vérifié dans le conteneur** :
`WP_DEBUG=false`, `log_errors=1`, `error_log=/dev/stderr`. **Aucune ligne J-0 ni J-1 n'est donc écrite,
nulle part.** Le contrat #29 l'avait d'ailleurs lui-même pressenti en A-50 (« en production, une
dégradation par `catch` ne laisse aucune trace dans les logs »), sans en tirer la conséquence sur R-29.

`docker-compose.yml` est **hors empreinte** et **partagé par trois chaînes en cours** : le basculer
changerait le comportement du test d'intégration de lot sous les pieds des autres. **La limitation est
consignée ; aucune ligne de journal n'est assertée ; aucune assertion contournée n'est présentée comme
vérifiée.** À corriger dans le contrat #29 par une issue dédiée, ouverte au niveau lot — précédent :
`issue-27.md` l. 41, et les issues #31 et #68 qui ont déjà borné des assertions de ce contrat.

Distinction à conserver : les **avertissements PHP natifs** (`Undefined array key`), eux, partent bien sur
`stderr` et restent lisibles par `journalConteneur()`. Ce sont deux canaux différents ; seul celui de
`massifs_journaliser()` est muet.

### Autres points signalés
- **`phpcs` (WPCS) est indisponible partout.** Vérifié : absent de l'hôte (où **`php` lui-même n'existe
  pas**), absent de `massifs_wordpress`, absent de `wpcli` ; ni `composer`, ni `vendor/`. La conformité
  WPCS de `fraicheur.php` est donc **revendiquée par lecture, jamais par outil** — écrit tel quel dans le
  rapport et dans le corps du commit.
- **Écart de fait dans `docs/BRIEF.md` §4.2** : « vers 18–19 h » contredit le relevé de
  `docs/decisions/source-prefecture.md` §4.10 (17 h 00 Paris, établi par `Last-Modified`). Le brief est le
  document produit du propriétaire : **non modifié**, écart remonté au niveau lot.
- **Le `try/catch` de `front-page.php` reste non exercé par fixture** (§1). Résultat acceptable, à
  condition d'être écrit — il l'est ici, dans les deux en-têtes de fichier et dans le commit.

---

## 11. Arbitrages

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-1** | Deux interfaces de fixture incompatibles, figées en aveugle : sept modes nommés à un argument (back) contre `fraicheur <publication> <releve>` (front) | **La grille à deux axes, arité 2.** La forme à sept modes est écartée ; c'est le back qui s'aligne | La démonstration de l'issue est « **corruption ≡ absence** ». Dans une grille, c'est une **invariance structurelle** — `malforme` et `absent` sur le même axe rendent le même HTML — donc une propriété **falsifiable**. Avec sept noms plats, le jumelage est implicite, adossé au bon nommage des cas, et un renommage le dissoudrait sans qu'aucune assertion ne rougisse : la propriété n'y serait que **vérifiable à la lecture**. La grille mappe en outre les deux coordonnées du tableau A-2, ce que sept noms plats ne font pas. Contrepartie assumée : la lisibilité de la trace, rendue par l'intitulé de chaque cas |
| **A-8** | Les cas jumeaux `absent` (3, 5, 7) sont-ils réductibles « si le budget serre » ? | **Non. Les huit cas sont le livrable** | Ce sont eux qui portent l'assertion « malformé ≡ absent ». Les couper laisserait une vérification de rendu de plus et **viderait l'issue de sa démonstration** — on aurait mesuré quatre rendus sans jamais prouver la propriété qui les relie |
| **A-9** | Le contre-témoin (cas 8, état `indisponible`) est-il redondant avec le témoin (cas 1) ? | **Non, il est obligatoire** | Le témoin prouve que la ligne **peut** être rendue ; le contre-témoin prouve que son absence a **deux causes distinctes** et que le scénario les distingue. Sans lui, « ligne omise » et « état indisponible » restent confondues — exactement le défaut que l'issue #70 reproche à l'existant, et le motif récurrent des issues #32, #69 et #71 |
| **A-2** | Comment transmettre une date longue française à travers un motif `[\w-]+` | **base64url sur la ligne `ETAT`**, décodé côté JS ; le serveur possède les valeurs, le scénario possède le gabarit | Le base64 standard contient `+` et `/`, que le motif du harnais coupe silencieusement — un défaut qui se serait manifesté en tronquant une valeur, pas en levant une erreur. Et **la fixture n'assemble jamais la phrase** : elle dupliquerait la logique de séparateurs qu'on teste, et le test comparerait deux implémentations de la même règle au lieu de vérifier la règle |
| **A-3** | Un cas peut-il hériter de l'état posé par le cas précédent ? | **Non. Chaque cas purge et repose son mode** | La disjonction d'empreintes protège les fichiers, pas l'état runtime : trois chaînes partagent la base. Un état posé par une autre chaîne entre deux mesures produit un vert sans valeur — le défaut même que ce lot combat |
| **A-4** | Le bandeau « Donnée périmée. » apparu sous corruption : bruit de fond ou assertion ? | **Assertion obligatoire, dans les deux sens, conditionnée à `actif`** | C'est la règle produit démontrée **en positif** : un horodatage corrompu rend le site plus prudent. Bien plus fort qu'une absence. La condition sur `actif` évite un rouge automatique chaque 1er octobre, qui ne décrirait aucun défaut |
| **A-5** | « Seeder une fixture avec horodatage malformé » (tâche 2 de l'issue) | **Reformulé, pas refusé** : la fixture seede bien un horodatage malformé **stable**, mais ce qu'elle exerce est l'**assainisseur de l'extension**, pas le `try/catch` du thème | Les quatre barrages du §1 rendent l'autre lecture impossible sans affaiblir le code de production. Le résultat prouvé sert les trois lignes de DoD ; le mécanisme non prouvé est **écrit**. Précédent assumé du projet : le contrat #29 a refusé les deux branches de l'objectif de sa propre issue (A-40) |
| **A-6** | Faut-il un contrat alors que `projet-vitrine.md` §2 en dispense les issues mono-face ? | **Oui.** Mono-face par les fichiers, deux faces par les auteurs | La dispense vise le cas où aucune interface n'est à réconcilier. Ici deux interfaces incompatibles ont été figées en aveugle : sans gel, les deux dev écrivaient l'un contre l'autre |
| **A-7** | Le nouveau scénario doit-il être inscrit à `tests/README.md` ? | **Non — dette assumée, remontée au lot** | Fichier hors empreinte, et la chaîne #19 y inscrira son propre scénario au même moment. Un fichier partagé n'a pas de branche pour rattraper un écrasement |
