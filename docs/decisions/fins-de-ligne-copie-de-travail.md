# Décision — Renormaliser une copie de travail déjà en CRLF

**Domaines** `infra`
**Date de la décision** : 31 août 2026 · **Auteur** : chaîne de l'issue #78
**Statut** : décision arrêtée

> Ce document complète l'en-tête de [`.gitattributes`](../../.gitattributes). Celui-ci explique pourquoi
> l'effet du fichier est **différé par conception** et proscrit la renormalisation de masse pendant un lot
> parallèle. Il ne disait pas quoi faire quand on a légitimement le droit de la mener. C'est ce trou-là que
> ce document comble, et il est opposable aux chaînes aval.

---

## 1. Le défaut, et pourquoi il est invisible

Le poste de développement tourne sous Windows avec `core.autocrlf=true`. Git stocke donc des blobs en LF
— l'index est propre et l'est resté — mais restitue les fichiers en CRLF à chaque écriture.

`.gitattributes` (livré par #35) pose `text eol=lf` sur `*.sh`, `*.conf` et `.htaccess`. Mais **git
n'applique jamais un attribut nouveau en balayant l'arbre : il ne convertit un fichier qu'au moment où il
l'écrit** — clone, ou checkout de ce fichier précis. Les fichiers déjà présents sur le disque avant #35
restent donc en CRLF, et `git status` les voit **propres**, puisque le blob indexé, lui, est déjà celui
qu'il faut.

La dérive ne se voit pas dans un diff. Elle se voit dans une seule commande :

```sh
git ls-files --eol | grep 'w/crlf' | grep 'eol=lf'
```

Un **clone frais** n'est pas concerné : il obtient bien du LF sur les chemins couverts. Le correctif de
#35 fonctionne — pour les clones postérieurs à son push, et pour eux seuls.

Ce n'est pas une coquetterie de fins de ligne. Un CR collé à un mot réservé est une erreur de syntaxe :
`docker/provision/provision.sh` mourait sur `set: line 9: illegal option -` (code 2), et la recette
d'intégration — l'instrument qui prouve les lignes de la DoD — était inexécutable.

## 2. `git add --renormalize` ne peut pas faire ce travail

La task list de #78 nommait `git add --renormalize .`. **Ce geste ne peut pas atteindre l'objectif, et
il faut le savoir avant de le lancer, pas après.**

`git add --renormalize` réapplique le filtre *clean* aux fichiers suivis et **réécrit l'index**. Aucune
variante de `git add` n'écrit dans la copie de travail. Or ici l'index **est déjà** à la valeur cible :

- les chemins visés sont `i/lf w/crlf attr/text eol=lf` — le blob indexé est déjà en LF ;
- les fichiers sans attribut `text` retombent sur `core.autocrlf=true`, qui nettoie déjà à l'entrée :
  leurs blobs sont en LF eux aussi ;
- les artefacts à contrat d'octets sont `-text` : le clean y est l'identité.

**C'est donc un no-op intégral** : index inchangé, disque inchangé, `git status` propre avant comme
après, rien à commiter. La commande rend la main sans erreur ni sortie — ce qui a toutes les chances
d'être lu comme un succès et de faire clore l'issue sur une non-correction. `git status` ne démasque pas
ce faux positif ; `git ls-files --eol` est le seul contrôle qui le fait.

`--renormalize` est l'outil du cas inverse : un index **sale**, contenant des blobs CRLF. Ce n'est pas
notre cas, précisément parce que `core.autocrlf=true` a toujours nettoyé à l'entrée.

## 3. Le geste retenu

Écrire est le seul moment où git convertit. Il n'existe donc que deux leviers : re-cloner, ou forcer git
à **réécrire** les fichiers concernés. Le clone frais a été écarté — il orpheline les volumes Docker (le
nom de projet compose dérive du basename du répertoire), se heurte aux `container_name` globaux, et perd
tout le non-suivi, `.env` en tête.

**Retenu : supprimer du disque les seuls fichiers concernés, puis les rechercher depuis l'index.** Un
fichier absent *doit* être écrit — git ne peut pas le juger « à jour » via le stat-cache, ce qui est le
piège d'un `git checkout --` sur un fichier présent. À l'écriture, le filtre smudge applique `eol=lf`.

**La liste se dérive de git, elle ne se transcrit pas :**

```sh
docker compose down          # précondition, cf. §4

git ls-files --eol | grep 'w/crlf' | grep 'eol=lf' | cut -f2 > /tmp/eol.txt
cat /tmp/eol.txt             # relecture humaine avant tout effacement

xargs -d '\n' -a /tmp/eol.txt rm --
git status --porcelain       # n lignes « D » : état intermédiaire, récupérable
xargs -d '\n' -a /tmp/eol.txt git checkout --
```

`cut -f2` et non un découpage sur les espaces : `git ls-files --eol` sépare les colonnes du chemin par une
**tabulation**, et la colonne d'attributs contient elle-même un espace (`text eol=lf`).

Trois propriétés font préférer ce sélecteur calculé à une liste écrite à la main :

1. **Un fichier `-text` ne peut structurellement pas être sélectionné** — il rapporte `attr/-text`, jamais
   `eol=lf`. Les artefacts dont un sha256 est calculé au build sur leurs octets sont protégés **par
   construction du sélecteur**, pas par la vigilance de l'opérateur.
2. Le sélecteur voit les `.gitattributes` **imbriqués**, pas seulement les trois familles de la racine.
3. **La commande de sélection est aussi la commande de vérification** : avant elle liste les chemins,
   après elle en liste zéro. Le contrôle n'est pas une commande différente qu'on espère cohérente.

Sont proscrits : `git reset --hard`, `git checkout .` sur tout l'arbre, `git rm --cached -r .`,
`git stash`. Les deux premiers sont nommément désignés comme destructeurs par l'en-tête de
`.gitattributes`, dans un arbre mono-branche partagé par plusieurs chaînes en parallèle ; tous
exposent l'ensemble du dépôt — artefacts à contrat d'octets compris — pour un bénéfice qui ne porte que
sur une poignée de fichiers.

**Filet** : entre le `rm` et le `checkout`, `git checkout -- .` restaure tout depuis l'index. Rien ne peut
être perdu **à condition d'avoir vérifié l'arbre propre avant** — cette vérification *est* le filet.

## 4. Préconditions

- **Lot solo, arbre propre** — l'en-tête de `.gitattributes` proscrit toute renormalisation de masse
  pendant un lot parallèle, et réserve la généralisation à un lot solo sur arbre propre. En arbre
  mono-branche partagé, il n'y a pas de branche pour rattraper ce qu'un geste de masse efface.
- `git status --porcelain` vide et `git rev-list --left-right --count HEAD...@{u}` à `0 0`. Sinon, on ne
  fait rien.
- **Pile Docker descendue** (`docker compose down`, jamais `-v`). `docker/tiles/nginx.conf` est monté en
  fichier unique `:ro` dans nginx (`docker-compose.yml`, service `tiles`), et les 7 `.htaccess` vivent
  sous le bind-mount de répertoire `./wp-content/plugins/massifs-core`, lu à l'exécution par
  Apache. Supprimer un fichier bind-monté en fichier unique laisse le conteneur accroché à l'inode
  disparu : il continuerait de servir l'ancienne configuration, et l'on conclurait à tort que le geste a
  échoué.

## 5. Vérification exigée

Le geste n'est réputé fait que si **tous** ces contrôles passent :

```sh
git ls-files --eol | grep 'w/crlf' | grep -c 'eol=lf'   # 0
git status --porcelain                                  # vide
git diff --stat HEAD                                    # vide
git rev-parse HEAD                                      # inchangé
git ls-files --eol | grep 'attr/-text' | grep -c 'w/crlf'  # inchangé avant/après
```

Le dernier contrôle se lit comme un **avant/après identique**, pas comme un zéro absolu : le geste ne
touche par construction aucun chemin `-text`, mais si l'un d'eux était déjà en CRLF sur le disque avant
le geste, il le resterait après — et un « = 0 » ferait conclure à tort à un échec. (Au moment de #78, il
valait bien `0` des deux côtés.)

Et, fichier par fichier, que la copie de travail est désormais **identique octet pour octet** au blob
indexé (`git show :<chemin>`) — c'est ce qui prouve que la seule chose retirée est le CR, et non un
fragment de contenu.

Si `git status` ou `git diff --stat HEAD` n'est pas vide après le geste, **s'arrêter** : quelque chose
d'autre qu'un CR a changé.

## 6. Cette classe d'issue ne produit pas de commit de contenu

Le contenu versionné ne change pas d'un octet : la copie de travail **rejoint** l'index, elle ne le
modifie pas. Un commit vide (`--allow-empty`) affirmerait qu'il s'est passé quelque chose dans le dépôt
alors qu'il ne s'est passé quelque chose que sur un disque. `docs/recette/README.md` tient la même
exigence pour ses propres preuves dans son § « La règle qui gouverne ce répertoire » — « rien n'est
écrit ici comme vérifié qui ne l'ait pas été » ; elle vaut aussi pour le journal git. **À proscrire.**

Un commit est bien produit, mais il ne porte **que ce document** : il ajoute une décision, il ne
renormalise rien de versionné. La trace est donc triple — ce document, le corps de son commit, et le
relevé avant/après consigné dans l'issue. C'est ce que `projet-vitrine.md` §2 exige d'une issue
mono-face qui se dispense du gel de contrat : consigner son raisonnement dans l'en-tête du fichier
livré et dans le corps du commit.

## 7. Portée, et ce qui reste à faire

Ce document décrit un **rattrapage de poste**, pas un correctif de dépôt. Il ne dispense de rien :

- **Aucune CI Linux ne détecterait cette dérive** — et le dépôt n'en a aujourd'hui aucune. Sur un runner
  Linux `core.autocrlf` vaut `false` par défaut, le checkout est en LF, le contrôle serait vert à
  perpétuité et ne prouverait rien. Un garde-fou utile
  s'exécuterait sur le poste — sa valeur retombe donc sur « le développeur pense à le lancer ».
- **On ne protège pas un script shell de son propre CRLF depuis un autre script shell couvert par la même
  règle.** `provision.sh` meurt avant qu'une garde puisse s'exécuter, et `docker/up.sh` comme
  `tests/run.sh` sont eux-mêmes concernés. Une garde ne survit que dans un hôte insensible aux fins de
  ligne.
- Le correctif durable reste la **généralisation `* text=auto eol=lf`** que l'en-tête de `.gitattributes`
  a déjà promise sous forme d'issue dédiée, en lot solo sur arbre propre. Elle seule fait disparaître la
  classe entière pour les clones futurs. Elle n'a pas été absorbée dans #78 : elle modifie un fichier
  versionné et re-litige un arbitrage de #35, ce qui est une autre issue.

Tranché à l'issue #78.
