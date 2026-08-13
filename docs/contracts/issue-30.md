# Contrat d'interface — Issue #30 — Autoriser le build vendorisé de la carte dans le garde d'accès du conteneur WordPress

Gelé le 2026-08-13. Domaines : `carte`, `infra`.

Cette issue ne crée ni fonction de lecture, ni route REST, ni état spécial, ni chaîne d'interface.
Les sections correspondantes du gabarit sont **sans objet** : les contrats `issue-2`, `issue-3`,
`issue-20` et `issue-27` restent intégralement en vigueur, et le thème n'a rien à changer dans son
code. Ce contrat porte sur une **surface d'exposition HTTP** : ce que le serveur web du conteneur
accorde et refuse, et la recette qui le prouve.

---

## Empreinte fichiers — périmètre d'écriture strict

| Fichier | Nature de l'écriture |
|---|---|
| `docker/wordpress/plugins-guard.conf` | ajout de deux blocs **en fin de fichier** |
| `tests/verifier-http.sh` | correction du repli de port, fixture éphémère, assertions |
| `docs/contracts/issue-30.md` | ce document, écrit par le lead |

**Aucune autre écriture.** En particulier : aucun fichier persistant créé sous
`wp-content/themes/massifs/assets/vendor/`, aucun `.htaccess`, aucun fichier de l'extension, aucune
modification de `.gitignore`, aucune modification d'un contrat déjà gelé.

---

## 1. Le défaut, établi par lecture — et non par mesure

`plugins-guard.conf` l. 54 refuse
`^/var/www/html/wp-content/(plugins|themes)/[^/]+/(.*/)?(includes|build|node_modules)/`.

Un répertoire `/var/www/html/wp-content/themes/massifs/assets/vendor/<paquet>/build/` satisfait cette
regex : `themes` · `massifs` · `assets/vendor/<paquet>/` · `build/`. Il part donc en 403. Preuve par
un chemin de code identique déjà en recette :
`plugins/massifs-core/includes/domain/massifs/build/identites.json` est en 403.

**Ce défaut n'est pas observable en HTTP aujourd'hui.** `wp-content/themes/massifs/assets/` ne contient
que `css/` et `fonts/` — vérifié. La marche de répertoires d'Apache s'interrompt au premier segment
inexistant, donc une requête vers `assets/vendor/leaflet/build/leaflet.js` rend aujourd'hui un 404,
pas un 403. Conséquence **liante** :

> Aucun agent, aucun rapport, aucun commit ne doit affirmer « 403 mesuré avant, 200 mesuré après » sur
> un chemin Leaflet. Le défaut est établi par lecture de la regex, ce qui suffit. La mécanique de la
> correction, elle, est mesurée — sur la fixture de la §4, pas sur Leaflet.

## 2. Sévérité — cadrage opposable

Un 403 sur **notre propre origine** n'émet aucune requête vers un domaine tiers. **La contrainte #2
n'est pas en cause**, ni par le défaut ni par sa correction. La conséquence directe du défaut serait une
carte cassée, et la contrainte #3 pose la carte comme enrichissement progressif.

Le risque réel est **comportemental** : la chaîne #7, devant un 403 inexpliqué sur une bibliothèque
qu'elle vient de déposer, élargirait le garde à la hache ou basculerait sur un CDN — et c'est *cette
réaction-là* qui casserait la contrainte #2.

La formule « violation de la contrainte #2 » est **interdite** dans le fichier, dans le message de
commit et dans tout rapport de cette chaîne.

## 3. Ce que le serveur accorde et refuse après correction

### 3.1 Forme exacte des deux blocs ajoutés

Ajoutés **en fin de fichier**, dans cet ordre, le bloc de refus existant (l. 25-62) **non touché,
pas d'un caractère** :

```
grant   : ^/var/www/html/wp-content/themes/massifs/assets/vendor/(.*/)?build(/|$)
re-refus: ^/var/www/html/wp-content/themes/massifs/assets/vendor/(.*/)?node_modules(/|$)
```

| Propriété | Valeur gelée |
|---|---|
| Type de section | `<DirectoryMatch>` — **jamais** `<Directory>`, **jamais** `<Location>`/`<LocationMatch>` |
| Racine | littérale, en dur, ancrée `^`. Aucune alternation, aucun `[^/]+`, aucun `.*` avant `assets/vendor/` |
| Profondeur | `build` à n'importe quelle profondeur sous `vendor/`, **et ses descendants** |
| Terminaison | `(/|$)` sur les deux regex |
| Ordre | **grant puis re-refus** ; les deux en dernier dans le fichier |
| `Options` | `Options -Indexes` dans le bloc de grant, **forme relative uniquement** |
| Repli `!mod_authz_core` | **absent du grant** (délibéré), **présent sur le re-refus** |
| `AuthMerging` | **jamais écrit**, nulle part |

### 3.2 Pourquoi le re-refus est porteur, et non décoratif

Une section `<DirectoryMatch>` est éprouvée à **chaque pas** de la marche de répertoires. Une regex non
ancrée à droite matche donc aussi les descendants. Conséquence :

| Chemin | Sans le re-refus | Avec |
|---|---|---|
| `vendor/<p>/node_modules/x` | 403 (bloc 2) | 403 — le re-refus y est redondant |
| `vendor/<p>/build/node_modules/x` | **200** : le grant matche via `build/` et, `AuthMerging` valant `Off`, **remplace** le refus du bloc 2 | **403** |

**Le re-refus est le seul dispositif qui ferme la seconde ligne.** Le placer avant le grant le rendrait
inopérant : même phase de fusion, c'est l'ordre du fichier qui tranche. Les deux leaddev l'ont établi
indépendamment ; c'est la propriété la plus facile à détruire par une « simplification » ultérieure, et
c'est pourquoi le commentaire de la §3.4 est obligatoire.

### 3.3 Ce qui reste refusé — opposable aux chaînes suivantes

| Objet | Code | Porté par |
|---|---|---|
| tout `.php` sous `themes/` ou `plugins/`, **y compris sous `vendor/**/build/`** | 403 | bloc 1, phase 3, postérieure au grant |
| `vendor/**/node_modules/**`, y compris **sous** un `build/` accordé | 403 | bloc 2 + bloc 4 |
| `vendor/**/includes/**` (hors d'un `build/`) | 403 | bloc 2 |
| tout `includes/`, `build/`, `node_modules/` sous `plugins/` | 403 | bloc 2, **inchangé** |
| listing du contenu de `vendor/**/build/` | 403 | `Options -Indexes` |
| `.htaccess` / `.htpasswd`, y compris sous un `build/` accordé | 403 | `<FilesMatch "^\.ht">` de l'image, phase 3 |

### 3.4 Commentaires obligatoires dans `plugins-guard.conf`

Le fichier porte ses invariants dans sa prose : c'est sa convention établie, et le registre est
« pourquoi », jamais « quoi ». Six points doivent y figurer. Sans eux le prochain relecteur croira le
grant mort et le retirera.

1. **Mécanique de fusion Apache 2.4.** Phases : (1) `<Directory>` non-regex + `.htaccess` ·
   (2) `<DirectoryMatch>` **dans l'ordre du fichier** · (3) `<Files>`/`<FilesMatch>` ·
   (4) `<Location>`/`<LocationMatch>` · (5) `<If>`. `AuthMerging Off` par défaut : l'autorisation d'une
   section **remplace** celle de la précédente.
2. **Pourquoi `<DirectoryMatch>` et pas `<Directory>`** : phase 1, il serait écrasé par le refus de la
   phase 2. Le grant serait livré mort tout en paraissant correct. Piège n° 1.
3. **Pourquoi `<LocationMatch>` est interdit** malgré le précédent de `tiles-proxy.conf` : phase 4,
   donc **après** le `<FilesMatch "\.php$">` de la phase 3 — il rouvrirait les `.php`. `<Location>`
   reste légitime pour un mandataire, où aucune autorisation n'est en jeu.
4. **Pourquoi le re-refus est après le grant**, et ce qu'il ferme exactement (§3.2).
5. **L'asymétrie `themes/` vs `plugins/`** est voulue (§3.6), avec la consigne de la corriger en
   déplaçant l'asset, jamais en élargissant le grant.
6. **Le trou connu `build/**/includes/**`** (§3.5) et **`dist/` doit rester hors de l'alternation de
   refus** (§3.7).

### 3.5 Trou connu et assumé : `vendor/**/build/**/includes/**`

Le grant rouvre `includes/` **situé sous un `build/` accordé**. Le re-refus ne couvre que
`node_modules`.

**Arbitrage : on n'ajoute pas `includes` au re-refus.** Re-refuser un répertoire d'un arbre étranger sur
la foi de son nom est exactement la classe de problème que #30 corrige. `node_modules/` est traité
différemment parce que sa présence dans un paquet vendorisé est en elle-même une erreur de
vendorisation, jamais un asset. Le risque résiduel est borné : un fichier **non-PHP** d'une
bibliothèque **publique par nature**, les `.php` restant fermés par la phase 3.

**Le trou doit être écrit en commentaire dans le fichier.** Un trou connu et écrit vaut mieux qu'un trou
implicite. Le rouvrir un jour serait un arbitrage neuf, pas un ajustement.

### 3.6 Asymétrie thème / extension — assumée

`plugins/massifs-core/assets/vendor/**/build/` n'est **pas** couvert par le grant, et reste 403.
L'architecture (`CLAUDE.md`) loge la vendorisation dans le thème.

> Un asset côté extension qui buterait sur un 403 se corrige **en le déplaçant dans le thème**, jamais
> en élargissant ce grant à `plugins/`. Un grant qui nommerait `plugins/` rouvrirait le sous-arbre dont
> l'issue #20 a fait un invariant opposable.

Inchangé par ailleurs côté extension : `plugins/massifs-core/data/**` reste **servi** (caches météo /
EFFIS / tuiles à venir), `plugins/massifs-core/assets/**/dist/**` resterait servi.

### 3.7 `dist/` — ne jamais ajouter à l'alternation de refus

`dist` est absent de l'alternation de refus : `assets/vendor/<p>/dist/<p>.js` est **déjà servi 200
aujourd'hui**. L'y ajouter « par symétrie » créerait à l'instant même le défaut que #30 corrige.

Conséquence à porter au rapport : le paquet npm `leaflet` publie son artefact dans `dist/`, pas dans
`build/` — le `build/` du dépôt GitHub porte l'outillage. Sous cette hypothèse, **#30 ne débloque rien
de #7 : c'est une assurance, pas un prérequis.** La classe de risque reste réelle pour d'autres paquets
(`three` publie dans `build/`).

**Legs à la chaîne #7** : la vraie protection est de vendoriser un arbre ne contenant aucun répertoire
nommé `build/`, `includes/` ou `node_modules/`. `plugins-guard.conf` vit dans `docker/` et n'existe pas
en production o2switch — une vendorisation qui *dépendrait* du grant marcherait en prod et casserait en
Docker.

## 4. La recette — `tests/verifier-http.sh`

### 4.1 Correction préalable du repli de port

L. 11 : `${PORT:-8080}` → `${PORT:-3002}`. Le port canonique est 3002 (`.env`, `.env.example`,
`docker-compose.yml`, `docker/README.md`). Le repli ne mord que sur un clone frais, `.env` étant
gitignoré — défaut latent, corrigé quand même : sans lui, toutes les assertions échouent pour une raison
étrangère aux gardes.

### 4.2 Fixture éphémère — arbitrage tranché

Le plan serveur proposait de renoncer à toute fixture au motif qu'écrire sous
`themes/massifs/assets/vendor/` sort de l'empreinte, et de la remplacer par des assertions tolérantes
(`200|404` accepté, `403` en échec).

**Arbitrage : la fixture est retenue, les assertions tolérantes sont écartées.** Une assertion tolérante
sur un chemin qui n'existera pas avant #7 est verte pour de mauvaises raisons et ne prouve **aucune** des
trois propriétés de mécanique que #30 doit établir. Un arbre créé, sondé puis supprimé **dans la même
exécution du test** n'est pas une écriture persistante hors empreinte : c'est un échafaudage de recette,
et l'approche a été explicitement retenue en amont. Le montage du thème est **rw** (vérifié :
`docker-compose.yml` l. 75, sans `:ro`, là où d'autres montages en portent un délibérément) et
`assets/vendor/` n'existe pas encore, donc aucune collision possible.

Arbre, créé sous `wp-content/themes/massifs/assets/vendor/_recette-garde-30/` :

```
build/sonde.js                 build/sous/sonde.js
build/sonde.php                build/node_modules/sonde.js
node_modules/sonde.js          dist/sonde.js
```

`sonde.php` **ne doit rien émettre** — une sonde qui peut imprimer quelque chose est une sonde qui peut
fuiter. Son seul rôle est son code HTTP.

### 4.3 Règles de sûreté de la fixture — liantes

| Règle | Raison |
|---|---|
| `trap … EXIT INT TERM` pour le nettoyage | l'arbre ne survit à aucune sortie, y compris Ctrl-C. `.gitignore` ancre `/vendor/` et `/node_modules/` à la **racine** : un résidu apparaîtrait dans `git status` et pourrait être commité |
| garde `[ -e ]` avant création | si le chemin existe déjà : cas **NON JOUÉ**, `statut=1`, **rien n'est créé, rien n'est supprimé, aucune sonde n'est jouée**. Jamais d'écrasement, jamais de suppression d'un arbre dont on n'est pas l'auteur |
| drapeau positionné **après** succès complet de la création | on ne supprime que ce qu'on a effectivement créé |
| jamais de `rm -rf` sur un chemin construit depuis une variable vide | garde explicite sur la non-vacuité |
| boucle de propagation : **on ne boucle que sur `404` et `000`, jamais sur `403`** | une boucle « répéter jusqu'à 200 » masquerait exactement le défaut que la recette détecte. Un `403` sort immédiatement et part en assertion : c'est un échec, pas une latence |
| épuisement de la boucle → **NON JOUÉ** + `statut=1` | `404` persistant : fixture non propagée. `000` persistant : stack non démarrée. Messages distincts. Jamais un vert par défaut |

Fenêtre de propagation : ~5 s, pas de 0,2 s, sur la seule sonde `build/sonde.js` — les autres vivent sur
le même montage.

### 4.4 Assertions — liste nominative et codes attendus

Toutes via la fonction `verifier()` existante (l. 64). **Aucune seconde fonction d'assertion.**
Placement : la fixture juste après la définition de `verifier()`, les assertions en fin de liste des
gardes, avant `verifier '/' 200`.

| Réf. | Chemin | Attendu | Ce que ça prouve |
|---|---|---|---|
| S1 | `…/vendor/_recette-garde-30/build/sonde.js` | **200** | le grant sert `build/` |
| S1b | `…/vendor/_recette-garde-30/build/sous/sonde.js` | **200** | cas imbriqué — discriminant quelle que soit la sémantique du slash final |
| S2 | `…/vendor/_recette-garde-30/build/sonde.php` | **403** | la phase 3 (`<FilesMatch "\.php$">`) survit au grant de la phase 2 |
| S3 | `…/vendor/_recette-garde-30/build/node_modules/sonde.js` | **403** | **seule sonde du re-refus** : le grant ne fuit pas vers le bas |
| S4 | `…/vendor/_recette-garde-30/node_modules/sonde.js` | **403** | le grant ne fuit pas latéralement |
| S5 | `…/vendor/_recette-garde-30/dist/sonde.js` | **200** | servi parce que **non refusé**, pas parce qu'accordé — sentinelle contre l'ajout de `dist` à l'alternation |
| S6 | `…/vendor/_recette-garde-30/build/` **avec slash final** | **403** | `Options -Indexes`. À lire **conjointement à S1 vert** : seule, elle ne distingue pas « pas de listing » de « refusé ». Sans le slash final on mesurerait le 301 de `mod_dir` |
| S7 | `/wp-content/plugins/massifs-core/includes/domain/massifs/build/node_modules/tinyqueue/README.md` | **403** | **fichier réel déjà commité** (vérifié) : le `node_modules` de l'extension reste fermé, sur un vrai refus d'autorisation et non un 404 déguisé |

S3, S5 et S6 portent **obligatoirement** leur commentaire explicatif : sans lui elles seront
mésinterprétées, et S5 sera « corrigée » en 403 par symétrie.

### 4.5 Non-régression — aucune fixture nécessaire

Les 12 chemins verrouillés et les 8 chemins servis existants sont des fichiers réels déjà commités. Ils
**ne bougent pas d'une ligne**. Leur maintien au vert est la preuve de non-régression.

**Toute modification, tout affaiblissement, toute suppression d'une assertion existante est un signal
d'alarme, pas un ajustement.** Un rouge est soit un défaut, soit une attente fausse ; ni l'un ni l'autre
ne se corrige en baissant l'assertion.

### 4.6 Preuve d'échec fermé — à jouer réellement

Commenter temporairement le bloc de re-refus doit faire **virer S3 au rouge**. Si S3 reste vert, le bloc
ne sert à rien et le dispositif n'est pas prouvé.

## 5. Non-régression serveur — démontrée, pas mesurée

La regex de grant est **ancrée** (`^`) sur le littéral `/var/www/html/wp-content/themes/`. Tout chemin
de l'extension commence par `/var/www/html/wp-content/plugins/` : la comparaison échoue au premier
caractère du segment qui suit `wp-content/`. **Il n'existe aucune chaîne à la fois sous `plugins/` et
acceptée par le grant.** Ce n'est pas une observation empirique mais une propriété de la regex : la
préservation est **structurelle**.

Second argument, indépendant : le grant est le motif du refus existant avec quatre restrictions et
aucun élargissement — `(plugins|themes)` réduit à `themes`, `[^/]+` réduit à `massifs`, `(.*/)?` préfixé
du littéral obligatoire `assets/vendor/`, alternation à trois branches réduite à `build`. L'ensemble
accordé est un **sous-ensemble strict** de l'ensemble refusé.

Troisième barrière, indépendante des deux premières : même dans le contrefactuel où un chemin `.php` de
l'extension serait matché par le grant, il resterait 403 — le `<FilesMatch "\.php$">` du bloc 1 est en
phase 3, postérieure.

**Les 12 refus restent refusés, les 8 services restent servis. Aucun blocage.** En particulier
l'invariant #20 — « `data/` = servi, `build/` = jamais servi » — est intégralement préservé.

Nuance à connaître sans la dramatiser : le `.htaccess` de
`plugins/massifs-core/includes/domain/massifs/` est fusionné en phase 1, donc déjà **masqué par le bloc
2** dans ce conteneur — les deux disent `Require all denied`, le résultat observable est identique, la
provenance non. #30 ne change rien à cela. Hors de cette image (o2switch), le `.htaccess` protège seul :
la défense en profondeur reste réelle, et **le supprimer au motif que le garde Apache ferait double
emploi est interdit**.

## 6. Interdits

1. Ne **jamais** toucher au bloc de refus existant (l. 25-62) : ni sa regex, ni son alternation, ni son
   rang. Toute la démonstration de la §5 est adossée à son immutabilité.

   **Amendement A-9, après refacto** — la formulation initiale gelait aussi les **commentaires** de ce
   bloc. Levée pour eux seuls. La passe de refacto a établi que le commentaire l. 40-45 affirme que
   `themes/massifs/assets/` est « DÉLIBÉRÉMENT épargné », « et `assets/vendor/` qui porte Leaflet
   vendorisé » — **ce qui est faux de la regex qu'il surplombe** : elle refuse `vendor/**/build/`,
   `vendor/**/includes/` et `vendor/**/node_modules/`. C'est exactement le défaut de #30, et ce
   commentaire est ce qui l'a rendu invisible en review.

   Laisser en place une prose qui affirme le contraire de sa directive, cinquante lignes au-dessus de
   deux blocs qui la corrigent, reconduirait la cause racine de l'issue — dont le risque est justement
   qu'un lecteur soit induit en erreur par ce fichier. **Correction autorisée, strictement limitée au
   texte du commentaire** : ni la regex, ni l'alternation, ni le rang du bloc ne changent, donc la
   démonstration de non-régression de la §5 est intacte, et le comportement du serveur inchangé.
2. Ne **jamais** introduire de regex négative (`(?!…)`) dans une règle de refus d'exposition : une règle
   de refus conditionnelle échoue **ouvert**. Une exception postérieure échoue **fermé**. C'est le motif
   du choix d'A′, pas une préférence de style.
3. Ne **jamais** ajouter `dist` à l'alternation de refus (§3.7).
4. Ne **jamais** élargir le grant à `wp-content/plugins/`, ni par `(plugins|themes)`, ni par `[^/]+` à
   la place de `massifs`, ni par un `.*` avant `assets/vendor/`.
5. Ne **jamais** écrire de directive `AuthMerging` : le défaut `Off` est le socle de la mécanique.
6. Ne **jamais** convertir un `<DirectoryMatch>` de ce fichier en `<Directory>`, ni écrire le grant en
   `<Location>`/`<LocationMatch>`.
7. Ne **jamais** placer le re-refus avant le grant.
8. Ne **jamais** écrire `Options` sous forme absolue (`Options None`, `Options Indexes FollowSymLinks`) :
   la forme absolue **remplace** l'hérité et pourrait retirer `FollowSymLinks`. Uniquement `-Indexes`.
9. Ne **jamais** affaiblir, réécrire ni supprimer une assertion existante de `tests/verifier-http.sh`.
10. Ne **jamais** boucler jusqu'à obtenir un 200 ; ne jamais écraser un arbre que la recette n'a pas
    créé ; ne jamais supprimer ce qu'elle n'a pas créé.
11. Ne **jamais** revendiquer une mesure du grant sur un chemin Leaflet : il n'existe pas avant #7.
12. Ne **jamais** écrire que ce défaut viole la contrainte #2 (§2).
13. Ne **jamais** modifier ni supprimer `plugins/massifs-core/includes/domain/massifs/.htaccess`.
14. **Aucune écriture hors des trois fichiers de l'empreinte.** En particulier aucun fichier persistant
    sous `assets/vendor/` pour « rendre une sonde verte », et aucune modification de `.gitignore`.
15. Fins de ligne **LF** préservées sur les deux fichiers : ne pas produire un diff intégral sur un
    correctif de quelques lignes.

## 7. Arbitrages

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| A-1 | **Fixture éphémère** (front) contre **assertions tolérantes `200\|404`** (back, au motif de l'empreinte) | **Fixture** ; assertions tolérantes écartées | Une assertion tolérante sur un chemin inexistant est verte pour de mauvaises raisons et ne prouve aucune des trois propriétés de mécanique. Un arbre créé et supprimé dans la même exécution n'est pas une écriture persistante. Approche retenue en amont |
| A-2 | Terminaison `/` (back : indifférent, le grant miroite le refus) contre `(/\|$)` (front) | **`(/\|$)` sur les deux regex** | Sur le re-refus, `/` peut échouer **ouvert** sur un `node_modules/` feuille selon la sémantique du slash final — soit précisément le mode de défaillance que A′ a été choisie pour éviter. `(/\|$)` est un sur-ensemble strict qui n'élargit rien (`buildzz/` ne matche pas) |
| A-3 | `includes` dans l'alternation du re-refus : **oui** (back) contre **non** (front) | **Non**, mais le trou est **écrit en commentaire** dans le fichier | Le fond revient à front : refuser un arbre étranger sur la foi d'un nom est le problème que #30 corrige, et `.php` reste fermé par la phase 3. La forme revient à back : un trou connu et écrit vaut mieux qu'un trou implicite |
| A-4 | Repli `<IfModule !mod_authz_core.c>` sur le grant : **présent** (front, cohérence de style) contre **absent** (back, échec fermé) | **Absent du grant**, présent sur le re-refus, avec un commentaire disant l'omission délibérée | La cohérence de style ne prime pas sur l'échec fermé. C'est le seul endroit du fichier où une branche de repli pourrait **ouvrir** ; sur Apache 2.4 elle est de toute façon morte. Sans le commentaire, un relecteur lira un oubli et « corrigera » |
| A-5 | Sonde 403 supplémentaire sur un fichier **réel** sous `plugins/…/build/node_modules/` (back) | **Retenue** (S7), sur `tinyqueue/README.md`, existence vérifiée | Complète la fixture par un vrai refus d'autorisation sur un fichier commité, là où la fixture ne prouve que la mécanique |

Trois écarts de lettre soulevés par l'implémentation, **arbitrés après coup et retenus** — ils servent
l'intention du contrat mieux que sa lettre :

| # | Écart | Décision | Raison |
|---|---|---|---|
| A-6 | Drapeau de nettoyage posé **aussi** dans la branche d'échec de création, là où le §4.3 le voulait après succès complet | **Retenu** | La garde `[ -e ]` a déjà prouvé que le chemin n'existait pas : un résidu partiel ne peut être que le nôtre, et le trap doit l'emporter. La lettre du §4.3 laisserait un arbre commitable après un échec à mi-chemin — exactement le risque que la règle protège. L'invariant « jamais supprimer un arbre dont on n'est pas l'auteur » est intact |
| A-7 | `rmdir` du parent `assets/vendor/` au nettoyage, non prévu | **Retenu** | `mkdir -p` crée `assets/vendor/`, que `rm -rf "$FIXTURE"` ne retire pas. `rmdir` ne réussit que si le répertoire est vide : après l'issue #7 il échouera silencieusement sur l'arbre vendorisé, sans jamais y toucher. Mesuré : `assets/` revient à `css fonts` |
| A-8 | Deux variables non nommées au contrat (`FIXTURE_URL`, `SONDES_PRETES`) | **Retenu** | `FIXTURE_URL` supprime la dérive entre chemins créés et chemins sondés ; `SONDES_PRETES` garantit que S1..S6 ne sont jouées que si la fixture est réellement en place — sans quoi un NON JOUÉ produirait six faux rouges |

## 8. Points de vigilance pour l'exécution et la review

- **Le `.conf` est `COPY`é dans l'image** (`docker/wordpress/Dockerfile`), il n'est pas monté. **Une
  modification du fichier n'a aucun effet sur un conteneur existant sans `docker compose build
  wordpress`.** C'est le premier faux négatif que la chaîne rencontrera.
- Contrôle statique avant démarrage : `apache2ctl configtest` — une regex mal formée doit échouer là,
  pas en recette.
- Les deux nouveaux blocs doivent rester les **derniers `<DirectoryMatch>` de toute la configuration** à
  toucher ce sous-arbre. `conf-enabled/*.conf` est inclus en ordre alphabétique
  (`massifs-deflate`, `massifs-plugins-guard`, `tiles-proxy`) : un futur fichier trié après
  `massifs-plugins-guard.conf` et portant un `DirectoryMatch` concurrent gagnerait.
- `tiles-proxy.conf` (`<Location>` + `ProxyPass`, phase 4, chemin disjoint) et `deflate.conf` (filtre de
  sortie) n'interagissent pas avec les deux nouveaux blocs.

## 9. Hors empreinte — à remonter, jamais à écrire

- **`plugins-guard.conf` ne vit que dans l'image Docker.** Rien de ce dispositif ne s'applique en
  production o2switch, et la matrice de `tests/verifier-http.sh` ne décrit que l'exposition **locale**.
  Une issue `infra` dédiée est à ouvrir.
- **`.gitignore`** ancre `/vendor/` et `/node_modules/` à la racine : la fixture n'est pas ignorée. Le
  risque est couvert par le `trap` et la garde `[ -e ]`, pas éliminé.
- **`docs/contracts/issue-2.md`, interdit 4** décrit le 403 de `includes/domain/massifs/build/*` comme
  porté « par le `.htaccess` du module, doublé par `plugins-guard.conf` ». Sur cette stack la provenance
  est inverse (§5). Contrat gelé, hors empreinte : ne pas répéter la formule, ne pas la corriger ici.
