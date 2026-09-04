# Décision — Généraliser `.gitattributes` à `* text=auto eol=lf`

**Domaines** `infra`
**Date de la décision** : 3 septembre 2026 · **Auteur** : chaîne de l'issue #80
**Statut** : décision arrêtée

> Ce document clôt la promesse ouverte à l'issue #35 dans l'en-tête de
> [`.gitattributes`](../../.gitattributes) — que #80 réécrit — et reprise par
> [`fins-de-ligne-copie-de-travail.md`](fins-de-ligne-copie-de-travail.md) §7 : « la généralisation
> `* text=auto eol=lf` […] sous forme d'issue dédiée, en lot solo sur arbre propre ». Il consigne
> l'arbitrage re-litigé, les mesures qui l'autorisent, et **deux défauts découverts en chemin**, hors de
> l'empreinte du commit de bascule. La review du lot en a fait corriger un (§6.2) ;
> ~~l'autre reste ouvert~~ — l'autre a été clos depuis, voir l'erratum daté en tête du §6.1.

---

## 1. L'arbitrage de #35 n'est pas invalidé — il est purgé

#35 écartait `* text=auto eol=lf` pour un motif **opérationnel**, jamais technique : la bascule marque
d'un coup des centaines de fichiers comme candidats à conversion différée, et ce demi-état visible pousse
au geste qui « nettoie » (`git checkout .`, `git reset --hard`), destructeur dans un arbre mono-branche
partagé. #35 posait donc deux conditions pour lever son propre veto :

1. **un lot solo sur arbre propre** ;
2. **un geste de renormalisation sûr** — qui n'existait pas alors, d'où son « Ne pas tenter de
   renormalisation en masse ».

Les deux sont remplies : #80 a été menée en lot solo sur arbre propre, et #78 a depuis établi le geste
manquant. Rien n'a changé qui invaliderait le raisonnement de #35 ; ce sont ses **préconditions** qui sont
enfin réunies. Le demi-état qu'il redoutait n'a pas été laissé derrière : la copie de travail a été
renormalisée dans la foulée, dans la même chaîne.

## 2. Mesures (708 fichiers suivis, HEAD `c881cdf`)

| | avant | après |
|---|---|---|
| `w/crlf` sur le disque | 335 | **0** |
| `attr/-text` (contrat d'octets) | 308 | **308, inchangés** |
| `attr/text eol=lf` (trois familles racine + règles positives des `build/`) | 38 | 38 |
| recouvrement `attr/-text` ∩ `w/crlf` | 0 | 0 |

Les 335 candidats étaient **tous** `i/lf w/crlf attr/` — index déjà en LF, aucun attribut. Aucun artefact à
contrat d'octets n'était en CRLF sur le disque : c'est ce qui rendait l'opération nettement moins risquée
que ne le craignait #35.

**Innocuité prouvée AVANT le geste**, et non constatée après : pour chacun des 335 fichiers,
`CRLF→LF(disque)` valait exactement le blob indexé. La renormalisation ne pouvait donc retirer que des CR,
jamais un fragment de contenu. Cette garde a été posée comme **condition d'exécution** du geste, qui
s'interrompait si un seul fichier divergeait autrement.

Après le geste : `git diff --stat HEAD` ne porte que sur `.gitattributes`, HEAD est inchangé, et aucun
contenu versionné n'a bougé d'un octet — conformément à #78 §6, cette classe d'opération **ne produit pas
de commit de contenu**. Les seuls fichiers commités sont `.gitattributes` lui-même et ce document.

## 3. `text=auto` protège les binaires, et c'est vérifié, pas supposé

Sous la règle générale, les binaires rapportent `i/-text w/-text attr/text=auto eol=lf` : l'**attribut**
dit `text=auto eol=lf`, mais la **détection de contenu** tranche, et ils ne sont convertis dans aucun sens.
Vérifié à la bascule sur les `.woff2`, les PNG de `docs/recette/captures/` et `assets/img/carte-statique.png`.

Corollaire qui vaut d'être écrit : **aucun fichier n'est à la fois `w/-text` et `w/crlf`**. Le sélecteur de
#78 (`w/crlf` ET `eol=lf`) ne peut donc **structurellement** pas désigner un binaire — la propriété n° 1 de
#78 §3 survit intacte au passage au fourre-tout.

## 4. Précédence : le fourre-tout ne menace pas les fichiers imbriqués

Mesuré après bascule : les 308 chemins `attr/-text` le sont restés, et les règles positives `text eol=lf`
des deux `build/.gitattributes` continuent de primer. Un motif fourre-tout à la racine ne met pas les
`.gitattributes` imbriqués plus en danger que trois motifs ciblés — la racine reste le maillon le plus
faible, par construction de git.

## 5. Pourquoi les trois familles survivent en queue

Sous le fourre-tout seul, `*.sh`, `*.conf` et `.htaccess` hériteraient de `text=auto` — « texte **si** ça
ressemble à du texte » — au lieu de `text`, inconditionnel. L'écart est étroit, et nul aujourd'hui : les 18
fichiers concernés ne portent aucun octet NUL, donc la détection les classe texte avec certitude. Il porte
sur l'avenir, et sur la seule famille dont la casse est **attestée** : un `.sh` que git jugerait binaire
garderait ses CR et mourrait sur `then\r` — le sinistre exact de #78. Trois lignes pour rendre la garantie
inconditionnelle là où l'échec est prouvé : le rapport coût/bénéfice ne se discute pas.

## 6. Deux défauts découverts, hors empreinte de #80

Aucun des deux n'était corrigé par le commit de #80. Le second (§6.2) l'a été à la review du lot ; le
premier (§6.1) ~~reste ouvert et attend son issue~~ **est clos depuis — voir l'erratum en tête du §6.1**.

### 6.1 Six artefacts à contrat d'octets ne portent aucun `-text` — et deux d'entre eux ne sont gardés par rien

> **Erratum — 4 septembre 2026, chaîne de l'issue #92. Ce défaut est CLOS.** L'issue #92 a livré
> (`540b677`) les `.gitattributes` imbriqués que ce paragraphe appelait, sous
> `wp-content/themes/massifs/`, en posant l'attribut à côté de chaque artefact plutôt que dans le
> fichier racine — c'est-à-dire de la manière exacte que ce paragraphe prescrivait.
>
> Le constat ci-dessous **reste lisible tel qu'il fut écrit** : il était exact au moment où il a été
> dressé. Il était en revanche **incomplet d'un artefact**. `assets/vendor/leaflet/leaflet.js` porte
> une empreinte dans la même table de `PROVENANCE.md` §1 que les deux fichiers voisins relevés ici,
> et n'était pas mieux gardé qu'eux ; #92 l'a traité avec eux plutôt que de laisser un tiers de ce
> répertoire à la détection de contenu.
>
> Le corollaire du présent §6.1 s'applique donc **à l'inventaire lui-même**, et c'est la leçon à en
> retenir : la sûreté du geste de #78 dépend de la complétude de l'inventaire `-text`, et un
> inventaire ne prouve jamais sa propre complétude. Le titre et le décompte ci-dessous sont
> conservés pour cette raison — les corriger effacerait la seule trace de ce que l'inventaire
> d'origine n'avait pas vu.
>
> **Cette clôture ne porte que sur le présent §6.1**, c'est-à-dire sur les `.gitattributes`
> imbriqués. Le réalignement annoncé en fin de §6.2 — les mentions prescriptives qui affirment
> encore une identité amont que l'historique a perdue — reste entier et attend son issue : « l'issue
> dédiée » qu'il désigne n'est pas celle-ci.

**Précision de vocabulaire, qui compte ici.** Depuis #80, plus aucun fichier du dépôt n'est « sans
attribut » : le fourre-tout leur donne à tous `text=auto eol=lf`. Ce qui manque aux six artefacts
ci-dessous, c'est le **`-text` explicite** que la doctrine du dépôt réserve aux contrats d'octets. Pour les
deux derniers, l'attribut hérité n'est d'ailleurs pas neutre : c'est lui qui les convertit.

`assets/fonts/atkinson-hyperlegible-next-var.woff2`, `assets/fonts/big-shoulders-display-var.woff2` et
`assets/img/carte-statique.png` (sous `wp-content/themes/massifs/`) portent chacun un sha256 épinglé dans le
dépôt, mais **aucun `-text`** : ils ne doivent leur intégrité qu'à la détection de contenu. Or la doctrine
du dépôt, écrite noir sur blanc dans `massifs-core/data/tuiles/.gitattributes`, dit l'inverse : « la
détection porte sur le CONTENU : **on ne la laisse pas décider d'un contrat** ». Deux d'entre eux —
`carte-statique.png` et la police — sont pourtant bel et bien **surveillés**, hachés contre une empreinte
épinglée par `ingest/tuiles/build/verifier.mjs` (§6.2) : ce qui leur manque est l'attribut, pas le contrôle.

**Deux artefacts de plus sont dans ce cas, et leur situation est pire** : `assets/vendor/leaflet/leaflet.css`
et `assets/vendor/leaflet/LICENSE` portent eux aussi un sha256 épinglé (`vendor/leaflet/PROVENANCE.md` §1)
et aucun `-text` — mais, contrairement aux trois premiers, git les classe **texte**. La détection ne les
protège donc **pas du tout** : ils faisaient partie des 335 fichiers renormalisés, et leurs empreintes
consignées ne correspondaient plus à leurs octets. C'est le sinistre décrit au §6.2, et il s'est produit
précisément là où cet inventaire, dans sa première rédaction, ne regardait pas.

**Un sixième, et c'est le cas nominal.** `assets/css/tokens.css` remplit le même critère — sha256 épinglé
par le contrat #4, aucun `-text`, classé texte — mais il est le seul dont le contrôle **regarde les octets
qui ont bougé** : le scénario 12 de la recette de rendu hache ses octets sur disque contre une empreinte
figée. Son empreinte épinglée n'a jamais changé et vaut celle du blob ; tant que la copie de travail était
en CRLF il rougissait donc, et la renormalisation l'a fait reverdir. C'est la seule alerte que le dépôt
ait réellement émise sur cette classe de défaut — et par contraste, la mesure de ce qui manque aux cinq
autres.

**Corollaire, à porter au crédit de l'inventaire plutôt qu'à celui du sélecteur.** La propriété de sûreté
n° 1 de [`fins-de-ligne-copie-de-travail.md`](fins-de-ligne-copie-de-travail.md) — « les artefacts dont un
sha256 est calculé au build sur leurs octets sont protégés **par construction du sélecteur** » — est
**réfutée par ce cas**. Elle n'est vraie que des artefacts explicitement marqués `-text`. Le constat
« aucun fichier n'est à la fois `w/-text` et `w/crlf` » reste exact et structurel, mais il ne protège que
les fichiers **déjà marqués** : il ne dit rien de ceux qui auraient dû l'être. Le geste de renormalisation
de #78 n'est sûr que dans la mesure où l'inventaire `-text` est complet — et il ne l'était pas.

Le comblement demande des `.gitattributes` **imbriqués** sous le thème, hors de l'empreinte du commit de
bascule, qui se limitait à la racine. Il n'a délibérément pas été posé dans le fichier racine : dans ce
dépôt une protection d'octets vit toujours à côté de l'artefact qu'elle garde, et la placer dans le maillon
le plus faible affaiblirait la convention au lieu de la servir. ~~À traiter par une issue dédiée.~~
**CLOS par #92 (`540b677`), 4 septembre 2026 — l'issue dédiée annoncée ici.**

*(Les 10 PNG de `docs/recette/captures/` sont dans le même cas mais sans empreinte épinglée : sans enjeu.)*

### 6.2 `PROVENANCE.md` de Leaflet mélangeait deux référentiels d'octets — **corrigé depuis**

Le document d'audit de la bibliothèque vendorisée annonçait `leaflet.css` et `LICENSE` « identiques » à
l'amont, avec les octets de l'amont, alors que le contenu **versionné** ne l'a jamais été : ces deux
fichiers arrivent en CRLF et leurs CR ont quitté le blob au commit, sous `core.autocrlf=true`.
L'affirmation était **déjà fausse sur tout clone Linux** avant #80 ; elle n'était vraie que par accident,
sur une copie de travail Windows. #80 n'a pas créé ce défaut : la renormalisation a rendu le disque
déterministe, donc l'écart visible partout.

**Corrigé à la review de ce lot** : la table de
[`vendor/leaflet/PROVENANCE.md`](../../wp-content/themes/massifs/assets/vendor/leaflet/PROVENANCE.md) §1
porte désormais les empreintes **servies**, l'explication de l'écart, et l'interdiction de « réparer » en
re-vendorisant l'amont — ce qui réintroduirait des CR dans des fichiers servis sans restaurer une identité
que l'historique a déjà perdue. **Les valeurs font foi là-bas, et ne sont pas recopiées ici** : c'est
précisément leur transcription en deux endroits qui a permis à l'écart de vivre.

Ce que ce document garde, parce que `PROVENANCE.md` n'a pas à le porter : **aucun contrôle du dépôt ne
regarde l'identité des octets de `leaflet.css` ni de `LICENSE`.** C'est pourquoi l'écart a pu vivre sans
être signalé, et pourquoi rien n'a rougi à la renormalisation. Le contrôle 12 de la recette de rendu, lui,
hache bien des octets sur disque — `tokens.css` — et il a rougi puis reverdi. Les deux vérificateurs de
build en hachent d'autres, et **tous n'étaient pas protégés** : `carte-statique.png` et la police
`.woff2` (`ingest/tuiles/build/verifier.mjs`) ne portaient aucun `-text` et ne devaient leur survie qu'à
la détection de contenu — ils figuraient à l'inventaire du §6.1, clos depuis par #92 (voir l'erratum qui
l'ouvre). Ceux de `domain/massifs/build/verifier.mjs`
(géométrie, source archivée, communes limitrophes, lookup communes) portent `-text` et sont protégés par
construction.

**Ce qui reste à réaligner, et qui n'est pas seulement une affaire de tailles.** La phrase retirée de
`PROVENANCE.md` §4 survit **mot pour mot** à deux autres endroits, et c'est la plus dangereuse des
mentions parce qu'elle est **prescriptive**, non descriptive :

- `docs/contracts/issue-7.md:807-808` — « `leaflet.css` ← 1.9.4 dist, **octet pour octet, JAMAIS édité** »
  et « `LICENSE` ← BSD-2-Clause, **verbatim** ». C'est le contrat gelé de la vendorisation, donc la source
  d'autorité qu'une chaîne aval consultera en premier ;
- `tests/rendu/recette-rendu.mjs:305` — un commentaire qui relaie la même affirmation en citant ce contrat.

Une chaîne qui les lit y trouve une identité octet à octet qui n'existe pas, et son réflexe — re-vendoriser
l'amont — réintroduirait des CR dans des fichiers servis. La garde opérationnelle tient déjà, elle est
co-localisée avec le risque (`PROVENANCE.md` §1 et §5) ; mais **ces deux mentions sont à réaligner**.
Le contrat est gelé et ne se réécrit pas ici (« pas de contrat rétroactif ») : c'est l'objet de l'issue
dédiée, avec les tables de `docs/contracts/issue-7.md` §11 et de `docs/recette/preuves-a11y-et-perf.md`,
qui portent encore les anciennes tailles. Ces dernières sont **descriptives** ; §11 rappelle que « le seul
budget normatif est celui du §10 du brief » — un budget de 250 Ko que le retrait des CR ne peut que
desserrer.

## 7. Effet résiduel, et ce que ce document ne dispense pas de faire

`* text=auto eol=lf` éteint la classe pour tout **clone futur**. La règle ne touche pas une copie de
travail déjà clonée : git n'applique jamais un attribut nouveau en balayant l'arbre, il ne convertit un
fichier qu'en l'**écrivant**. Une copie clonée avant ce commit reste en CRLF et `git status` la voit
propre. Le diagnostic (`git ls-files --eol | grep 'w/crlf'`) et le remède (`rm` puis `git checkout --`,
jamais `git add --renormalize`) restent ceux de [#78](fins-de-ligne-copie-de-travail.md) — ce document ne
les remplace pas, il en réduit la population future à zéro.

Tranché à l'issue #80.
