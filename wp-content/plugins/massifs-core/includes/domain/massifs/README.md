# Domaine « massifs » — référentiel des périmètres (Bouches-du-Rhône)

Ce module possède **l'identité, les libellés et la géométrie** des 25 massifs forestiers du 13. Il est en
lecture seule : aucun hook, aucune table, aucune option, aucun transient, aucun cron, aucune route REST,
aucun écran, aucune écriture, aucun HTML. Le charger ne fait rien d'observable.

Le contrat d'interface fait foi : [`docs/contracts/issue-2.md`](../../../../../../docs/contracts/issue-2.md).
Ce fichier ne décrit que la **procédure de ré-import**.

## Fichiers

| Fichier | Rôle |
|---|---|
| `module.php` | Point d'entrée. Nom imposé : le chargeur de l'extension ne cherche que `<couche>/<module>/module.php` |
| `etats.php` | Constantes d'état et de raison, version du module |
| `referentiel.php` | Chargement, validation, lecture des lignes de massif |
| `geometrie.php` | Emprise et métadonnées de l'artefact géométrique |
| `attribution.php` | Mention §9 et lacunes assumées |
| `compat.php` | Les 16 fonctions publiques `massifs_*()` |
| `.htaccess` | Interdit l'accès web à tout ce répertoire — code, outillage, et surtout l'archive source de 3 Mo |
| `build/package.json` · `build/package-lock.json` · `build/.nvmrc` · `build/.gitignore` | Outillage de build seulement ; jamais exécuté à l'exécution du site. Le lockfile et le `.nvmrc` sont ce qui rend l'import **rejouable à l'octet** |
| `build/.gitattributes` · `../../../data/.gitattributes` | Interdisent toute conversion de fins de ligne sur les fichiers dont les octets sont mesurés |
| `build/identites.json` | **Registre d'identités gelées, édité à la main, en ajout seul. Fait autorité sur l'identité** |
| `build/importer.mjs` | Chaîne d'import reproductible |
| `build/verifier.mjs` | Recette : rejoue tous les contrôles sans rien réécrire |
| `build/source/massifs-13.full.geojson` | Source archivée (3 Mo), entrée du pipeline |
| `build/massifs-13.fidelite.json` | **Généré.** Recette de fidélité, hors contrat, aucun consommateur applicatif |
| `build/reference.json` | **Généré.** Empreinte de référence des artefacts : c'est elle que la recette compare pour détecter une dérive |
| `../../../data/massifs-13.php` | **Généré.** Métadonnées et 25 lignes de massif |
| `../../../data/massifs-13.geometrie.json` | **Généré.** Géométrie simplifiée, servie en statique au navigateur |

**Invariant de rangement : `data/` est servi au navigateur ; `build/` ne l'est jamais.** Les deux
artefacts de recette vivent donc dans `build/`, sous le `.htaccess` de ce répertoire et sous le garde-fou
Apache de `docker/wordpress/plugins-guard.conf`. Un artefact de recette accessible à une URL publique
exposerait la mécanique interne du projet sans qu'aucun consommateur en ait besoin.

Les **quatre** artefacts générés ne s'éditent **jamais** à la main, `reference.json` compris — il est émis
par l'import, en même temps que les trois autres.

## Source et licence

- Jeu de données : « Massifs forestiers dans les Bouches-du-Rhône », producteur **DDTM des Bouches-du-Rhône**,
  publié sur data.gouv.fr / DataSud — couche `L_MASSIFS_FORESTIERS_S_013`, données du **14 février 2023**.
- Licence : **Licence Ouverte / Etalab 2.0** — réutilisation libre **avec citation de la source et de la date**.
  Cette obligation est la raison pour laquelle la phrase d'attribution est fournie par l'extension
  (`massifs_attribution()`) et non rédigée par le thème : trois consommateurs qui l'assembleraient
  produiraient trois variantes, dont deux non conformes.
- Base réglementaire des massifs : arrêté préfectoral n° 13-2018-05-28-005 du 28 mai 2018.
- La récupération est **strictement côté serveur et au build**. Le navigateur ne contacte jamais data.gouv.fr,
  GeoIDE ni DataSud. Il n'y a **aucun cron de surveillance** : la source est révisée tous les ~2 ans, et un
  import automatique pourrait re-lier des identités en silence — exactement ce que la règle ci-dessous interdit.

`source.sha256` dans `massifs-13.php` est l'empreinte de **la source archivée dans `build/source/`**, celle que
relit le pipeline et que n'importe qui peut recalculer depuis le dépôt. Ce n'est pas l'empreinte du
téléchargement d'origine, qui a transité par une conversion GML → GeoJSON.

## Le modèle à deux clés

| Clé | Rôle |
|---|---|
| `code` (ex. `sainte-victoire`) | **Identité. Gelée à vie, jamais recalculée.** C'est elle qui joint les statuts, l'historique, les URLs et le JSON public |
| `source.gid` + `source.nom_massif` | **Provenance et rapprochement seulement.** Jamais une clé de jointure, jamais affichés |
| `source.identifiant_prefecture` (ex. `1323`) | **Traduction du flux préfectoral vers `code`, gelée.** Sert à décoder une charge utile entrante, jamais à identifier un massif en interne : la jointure reste `code`, partout |

### Pourquoi `gid` est disqualifié comme identifiant

Ce n'est pas une précaution de principe, c'est un constat : les 25 `gid` de la couche valent exactement
**1 à 25, dans l'ordre alphabétique des noms**. C'est donc un rang, pas un identifiant.

Insérer un seul massif renumérote tout ce qui le suit. Le cas n'est pas théorique : la couche sœur
`L_MASSIFS_EXPOSES_FDF_S_013`, publiée par le même producteur, contient déjà **`Camargue`**. Son insertion
décalerait **22 massifs sur 25** — et tout l'historique des statuts, s'il était lié par `gid`, pointerait
silencieusement vers le mauvais massif. Cette même couche sœur livre par ailleurs **28 lignes pour 27 `fid`
distincts** : les clés de substitution du producteur ne sont ni stables ni uniques.

Un slug **recalculé** à chaque import a la bonne forme et la mauvaise règle : une correction de diacritiques
ou un renommage casserait l'identité. **Gelé**, il est invariant, lisible dans les URLs, et il nous appartient.

### Libellés

Le libellé affiché reproduit l'orthographe officielle. **Une seule correction** sur les 25 :
`Chaine des Cotes` → `Chaîne des Côtes`, attestée par le bulletin journalier de la préfecture et par la table
des massifs de `risque-prevention-incendie.fr/13`. `Cote Bleue`, `Etoile` et `Trevaresse` restent **sans
accent** : les publications officielles les écrivent ainsi, dans des documents qui portent `î`, `ô` et `ç` sur
la même ligne. Les accentuer nous ferait diverger du document que le visiteur consulte pour nous vérifier.

**Règle contraignante** : si `libelle` diffère de `source.nom_massif`, `note_provenance` doit être non vide et
citer la source qui atteste la forme retenue. L'import **refuse d'émettre** sinon.

## Correspondance avec le flux préfectoral

Le flux journalier de la préfecture désigne les massifs par des identifiants numériques (`131`…`1327`) ;
notre référentiel les désigne par leur `code` (`alpilles`, `sainte-victoire`…). Sans table de passage, la
jointure entre un statut ingéré et un massif est **vide** : deux ensembles de clés disjoints.

Cette table de passage est une **donnée gelée**, portée par `build/identites.json`
(`identifiant_prefecture`), recopiée telle quelle dans `data/massifs-13.php` — par ligne, dans
`source.identifiant_prefecture`, et en bloc racine `correspondance_source` pour la lecture inverse.

**Elle ne se calcule jamais.** Elle vaut aujourd'hui `13` suivi de `source.gid` ; l'écrire ainsi serait un
défaut, pour exactement la raison qui a fait geler les `code` : `gid` est le rang alphabétique, insérer un
massif en renumérote 22 sur 25 **en silence**, et le statut du jour d'un massif s'afficherait alors sur un
autre. Aucun code de l'extension ne concatène `'13'` et un `gid`.

### Comment elle a été vérifiée

Les 25 paires ont été contrôlées **une par une**, le 11 août 2026, contre la table des massifs publiée par
la préfecture elle-même sur `risque-prevention-incendie.fr/13` — la table HTML rendue côté serveur, dont
chaque ligne porte l'identifiant en attribut. Le rapprochement a été fait sur les noms, **diacritiques
repliés des deux côtés** (`Chaîne des Côtes` ≡ `chaine des cotes`). **25 correspondances, aucun écart.**

### `1326` et `1327` : en surnombre, sans nom

Le flux journalier porte **27** identifiants. La table HTML de la préfecture n'a que **25** lignes et le
bulletin PDF journalier ne nomme que **25** massifs : `1326` et `1327` ne correspondent à **aucun massif
publié**. Ils n'ont donc **aucune correspondance**, délibérément — `massifs_code_depuis_source()` renvoie
`null` pour eux, comme pour n'importe quel identifiant inconnu, et l'ingestion n'écrit rien.

Aucun nom n'a été inventé pour combler l'écart. Deux pistes existent (la géométrie publiée par la
préfecture porte une entité `Montagnette Partie Incendiée` absente de l'open data ; la couche sœur
`L_MASSIFS_EXPOSES_FDF_S_013` ajoute `ZIP de Fos` et `Camargue`), **aucune n'est tranchée** : ce serait
présenter une supposition comme une information officielle.

### Lecture

```php
massifs_code_depuis_source( '131' );        // 'alpilles'
massifs_code_depuis_source( '1326' );       // null — en surnombre
massifs_code_depuis_source( '9999' );       // null — inconnu
massifs_source_depuis_code( 'trevaresse' ); // '1325'
massifs_correspondance_source();            // 25 entrées, code => identifiant
```

L'entrée n'est **jamais normalisée** : ni `trim()`, ni changement de casse, ni transtypage. Replier une
valeur approchante sur un massif réel présenterait une donnée fausse comme juste. Les massifs retirés
gardent leur correspondance : traduire un identifiant n'affirme pas qu'un massif est actif, c'est
`massifs_massif_existe()` qui répond à cela.

### Règle de ré-import

**Un identifiant préfectoral nouveau, déplacé ou disparu se tranche à la main. Jamais automatiquement.**

| Cas | Que faire |
|---|---|
| Le flux expose un identifiant inconnu | Rien d'automatique. Vérifier dans la table officielle **à quel nom** il correspond, puis, si et seulement si ce nom est celui d'un massif que nous connaissons, ajouter `identifiant_prefecture` à l'entrée concernée de `identites.json` |
| Un identifiant connu change de massif | **Arrêt.** C'est une renumérotation côté préfecture : elle invalide la table entière, qui doit être revérifiée ligne à ligne contre la table officielle avant toute réémission |
| Un nouveau massif est gelé (cas 4 de la table de réconciliation) | Son `identifiant_prefecture` se relève dans la table officielle. En son absence, l'import **refuse d'émettre** : un massif sans correspondance ne recevrait jamais de statut officiel, en silence |
| Un identifiant reste sans massif publié | Le laisser sans correspondance et l'ajouter à `identifiants_prefecture_en_surnombre` dans `identites.json` |

L'import s'arrête, sans rien écrire, si l'une des 25 identités n'a pas d'`identifiant_prefecture`, si deux
massifs en partagent un, si une valeur ne respecte pas `^\d{3,4}$`, ou si un massif revendique un
identifiant déclaré en surnombre.

## Table de réconciliation

Appliquée par `importer.mjs` à chaque exécution, entité source par entité source.

| Cas | Règle | Que faire |
|---|---|---|
| 1. `gid` **et** slug(`nom_massif`) correspondent | Même massif. Géométrie et `source.revision` mis à jour automatiquement | Rien |
| 2. Slug seul correspond (`gid` a bougé) | Même massif. Dérive journalisée, `code` **inchangé** | Rien ; la dérive s'affiche dans la sortie de l'import |
| 3. `gid` seul correspond (le nom a changé) | **Arrêt.** Renommage ou redécoupage — jamais de re-liaison automatique | Décider : simple renommage → corriger `libelle` **et** `source.nom_massif` dans `identites.json`, avec une `note_provenance` citée. Vrai nouveau massif → cas 4 |
| 4. Entité source sans correspondance | **Arrêt.** Aucun `code` n'est créé par une machine | Vérifier s'il s'agit d'un renommage (recouvrement ≥ 80 % avec un massif existant ⇒ probable). Sinon, ajouter une entrée dans `identites.json` avec un `code` neuf, en kebab-case ASCII, et le geler |
| 5. Ligne existante sans correspondance source | **Jamais supprimée** — l'historique (§4.2) et l'URL restent valides | Poser `retire_le` (`YYYY-MM-DD`) dans `identites.json`. La ligne sort de la liste du jour (`actif === false`) mais reste lisible via `massifs_massif( $code, true )` |
| 6. Scission ou fusion (recouvrement 1↔N) | Aucune règle automatique | Décision humaine. **L'historique n'est jamais ré-attribué au travers d'une scission** : les nouveaux massifs reçoivent de nouveaux `code`, l'ancien reçoit `retire_le` |

Sur un **Arrêt**, rien n'est écrit : les artefacts en place restent cohérents entre eux.

## Ré-importer

```sh
cd wp-content/plugins/massifs-core/includes/domain/massifs/build
nvm use                # lit .nvmrc : Node 24 (le majeur, pas le correctif)
npm ci                 # JAMAIS `npm install` : `ci` installe le lockfile à l'identique
# remplacer build/source/massifs-13.full.geojson par la nouvelle source (EPSG:4326)
npm run importer       # émet les 4 artefacts, ou s'arrête sans rien écrire
npm run verifier       # rejoue tous les contrôles, code de sortie ≠ 0 en cas de dérive
```

`npm ci` et non `npm install` : `install` peut **remonter** une dépendance transitive de mapshaper et
produire une géométrie aux octets différents à source identique. C'est arrivé, et c'est mesuré — voir
« Reproductibilité ».

### Jouer la recette sans PHP sur la machine

`npm run verifier` lit `data/massifs-13.php` via `php -r` : c'est pour cela que la garde de ce fichier
généré est volontairement **sans `exit`** — hors WordPress, il retourne un tableau vide au lieu
d'interrompre le processus. Sans PHP joignable, la recette **échoue** ; elle ne passe jamais un contrôle
en silence.

Aucun PHP sur l'hôte est un cas courant (Windows, poste sans stack locale). Deux variables suffisent :

| Variable | Rôle |
|---|---|
| `PHP_BIN` | Commande PHP, **arguments admis**. Défaut : `php` |
| `MASSIFS_PHP_RACINE` | Racine de l'extension **telle que la voit le PHP invoqué**. Sans elle, un PHP conteneurisé échouerait sur « fichier introuvable » |

```sh
PHP_BIN="docker compose run --rm -T wpcli php" \
MASSIFS_PHP_RACINE=/var/www/html/wp-content/plugins/massifs-core \
npm run verifier
```

Sous Git Bash, préfixer par `MSYS_NO_PATHCONV=1` : sinon MSYS traduit le chemin de conteneur en chemin
Windows avant de le passer à `docker.exe`.

### `reference.json` — l'empreinte de référence

`reference.json` porte l'empreinte des artefacts au dernier import **assumé** : sha256 et octets de la
géométrie et de la source archivée, nombre de sommets, écart maximal, version de mapshaper, majeur de Node.
Il est **émis par l'import**, jamais édité à la main.

C'est ce qui sépare deux questions que les seuils confondaient :

- les **seuils** disent que la géométrie est acceptable, quelle qu'elle soit ;
- `reference.json` dit qu'elle est **la même** qu'au dernier import assumé.

Un artefact peut tenir tous les seuils et avoir néanmoins changé sans que personne l'ait décidé. Quand la
recette signale une dérive : la comprendre d'abord. Si le changement est voulu, régénérer les artefacts
**et** `reference.json` par `npm run importer`, **dans le même commit** — les quatre artefacts forment un
tout, et un `reference.json` en retard laisse la recette rouge en permanence, donc bientôt ignorée.

L'import affiche de lui-même, avant d'écrire, un bloc `DÉRIVE PAR RAPPORT À reference.json` clé par clé.
Cet affichage est **informatif** : une dérive n'arrête pas l'import — c'est la recette qui échoue dessus,
et elle seule. Dans la recette, le majeur de Node est le seul écart **non bloquant** : il s'affiche en
avertissement, y compris dans le bloc d'échec comme contexte de diagnostic. Un échec dur sur Node 26 alors
que les octets concordent est un faux positif, et un faux positif répété apprend à régénérer la référence
par réflexe.

### `.gitattributes` — pourquoi

**Les octets sont le contrat.** L'empreinte sha256 de la géométrie, son jeton de cache-busting, la taille
consignée : tout est calculé sur les octets du fichier. Avec `core.autocrlf=true` — valeur courante sous
Windows — un clone convertirait ces octets sans changer les empreintes consignées. La recette échouerait
sur trois contrôles d'empreinte sans jamais nommer la cause, et `massifs_geometrie()['version']` mentirait.

Les deux `.gitattributes` (`data/` et `build/`) posent `-text` sur les fichiers mesurés :
`massifs-13.geometrie.json`, `massifs-13.php`, `source/massifs-13.full.geojson`,
`massifs-13.fidelite.json`, `reference.json`. `-text` et non `binary` (qui implique `-diff`, or relire le
diff de `massifs-13.php` est un contrôle imposé ci-dessous) ; `-text` et non `text eol=lf` (qui
absorberait la conversion au commit et découplerait les octets du disque de ceux du blob).

`verifier.mjs` contrôle en plus la présence d'un octet `0x0D` dans les deux artefacts de `data/`,
**indépendamment de git et de sa configuration**. C'est ce qui transforme une heure de recherche en dix
secondes.

Relire enfin le diff de `data/massifs-13.php` avant de commiter : **aucun `code` ne doit changer**. Un
`code` modifié dans un diff est un défaut, jamais une variante. Sur un ré-import à source inchangée, les
seules lignes qui ont le droit de bouger sont `genere_le` et `geometrie.{version, sha256, octets}` —
`bbox`, `centre` et `emprise` sont mesurés sur la source, pas sur la sortie simplifiée, et n'ont donc
aucune raison de bouger.

## Reproductibilité

**La géométrie publiée est reproductible à l'octet ; les métadonnées portent un horodatage d'import
assumé.**

Vérifié : deux imports consécutifs, à `MASSIFS_GENERE_LE` figé, produisent les **mêmes sha256 sur les
quatre artefacts**. Ce qui le garantit :

| Élément | Ce qu'il fixe |
|---|---|
| `package-lock.json` | mapshaper **et toutes ses dépendances transitives**. Épingler `mapshaper: "0.6.102"` dans `package.json` ne suffisait pas |
| `.nvmrc` | Le majeur de Node |
| `.gitattributes` | Les octets sur le disque après clone |
| `reference.json` | Le point de comparaison, et donc la détection de dérive |

`genere_le` est le seul champ non reproductible, **délibérément** : il date l'import. Il n'est pas dérivé
de la révision de la source (`2023-02-14`), parce que ce serait annoncer une date de génération fausse —
dans un projet dont la règle cardinale est de ne jamais présenter une date pour une autre (§4.2, §4.5).

`MASSIFS_GENERE_LE=AAAA-MM-JJThh:mm:ssZ` fige cet horodatage. C'est un **outil de preuve**, à n'utiliser
que pour démontrer la reproductibilité : il ne sert **jamais** à produire des artefacts commités, qui
doivent porter l'heure réelle de leur génération. Une forme non conforme arrête l'import au lieu d'être
réinterprétée.

## Contrôles bloquants de l'import

Aucun artefact n'est écrit si l'un d'eux échoue (émission atomique : fichiers temporaires puis renommage).

- une entité source sans entrée dans `identites.json` ;
- un `code` qui ne respecte pas `^[a-z0-9_-]{1,64}$` ;
- un `libelle` différent de `source.nom_massif` sans `note_provenance` ;
- deux entités source rapportées au même `code` ; deux `gid` identiques ;
- une identité sans `identifiant_prefecture`, deux identités qui en partagent un, une valeur qui ne
  respecte pas `^\d{3,4}$`, ou une identité qui revendique un identifiant déclaré en surnombre ;
- une identité sans entité source et sans `retire_le` ;
- un `libelle` vide ;
- un nombre d'entités géométriques différent de 25 ;
- une `Feature` publiée portant autre chose que `properties.code` ;
- une bbox de massif qui déborde de l'emprise ;
- géométrie > 300 Ko bruts, écart max > 120 m, écart de surface global > 0,5 %, pire massif > 3 %,
  anneaux supprimés > 0,5 % de la surface ;
- un `MASSIFS_GENERE_LE` qui ne respecte pas `AAAA-MM-JJThh:mm:ssZ` ;
- `node_modules/mapshaper` absent (la version consignée est lue dans son manifeste, jamais codée en dur).

**Un seuil ne se desserre jamais pour faire passer un import.** Si l'import s'arrête sur un seuil, c'est le
seuil qui a raison.

Le renommage final porte sur les quatre artefacts. S'il échouait à mi-parcours, le dépôt porterait une
géométrie neuve avec des métadonnées anciennes — donc un jeton de cache-busting **faux**. Dans ce cas
l'import purge les temporaires restants, **nomme les fichiers déjà remplacés** et donne la commande
`git checkout --` pour les restaurer. Il ne tente **aucun retour en arrière automatique** : ce serait une
seconde écriture dans un état déjà incertain. Corollaire : partir d'un arbre de travail propre sur ces
quatre fichiers.

## Simplification et budget

```
node node_modules/mapshaper/bin/mapshaper _src_code.geojson \
  -simplify dp interval=90 keep-shapes \
  -o precision=0.0001 format=geojson _simplifie.geojson
```

Commande rejouable depuis `build/`, telle que le pipeline l'exécute. `_src_code.geojson` est la source
réduite à `properties.code` (la géométrie publiée ne porte rien d'autre) et `_simplifie.geojson` la sortie
avant émission : les deux sont créés puis supprimés par le pipeline. La commande exacte est aussi consignée
dans `build/massifs-13.fidelite.json` (`simplification.argv` / `.commande`), construite à partir de l'argv
réellement passé.

Mesuré sur la **source archivée**, avec le lockfile en place (2026-08-13) : **278 894 octets bruts**,
16 282 sommets conservés sur 160 594 (10,14 %), **écart maximal 93,62 m**, p99,9 88,25 m, p99 79,41 m,
moyenne 21,93 m, écart de surface global −0,079 %, écart absolu moyen par massif 0,47 %, pire massif
`collines-de-gardanne` à 1,44 %, 45 anneaux supprimés valant 0,086 % de la surface totale. La topologie est
préservée : les 10 frontières partagées sont simplifiées une seule fois, en arcs partagés — donc ni trou ni
recouvrement entre voisins.

Le budget de 300 Ko porte sur les **octets bruts**, pas sur le gzip.

### Compression : marge, pas béquille

Deux mesures, deux méthodes, jamais l'une présentée pour l'autre :

| Méthode | Mesure | Date |
|---|---|---|
| Transféré, `bash tests/verifier-http.sh` sur la cible Docker (`mod_deflate`, `Accept-Encoding: gzip`) | **74 023 o** | 2026-08-13 |
| Build, `zlib.gzipSync` (consigné dans `massifs-13.fidelite.json`) | **73 737 o** | 2026-08-13 |

Le budget reste exprimé en **octets bruts** : la cible mesurée est la stack Docker locale, **la production
o2switch ne l'est pas**. Une compression confirmée sur un environnement ne l'est pas sur l'autre ; elle
reste une marge.

### `zoom_max = 11`, et pourquoi pas `interval=20`

`zoom_max = 11` est **mesuré** : 93,62 m valent 0,844 px à z10 et 1,688 px à z11 à la latitude 43,5°
(échelle calculée, non saisie : 110,89 m/px à z10, 55,45 à z11, 27,72 à z12, 13,86 à z13). L'écart est
donc sous-pixel jusqu'à z10 inclus. La carte officielle de référence est elle-même départementale : l'écart
est invisible à l'échelle où l'information est publiée.

`interval=20 m` a été mesuré et **écarté**. Trois raisons, écrites une fois pour que la question ne se
redécouvre pas à chaque revue :

1. **L'interdit 12 du contrat plafonne la couche massifs à z11.** Une fidélité sous-pixel à z12 n'est
   visible à aucun zoom que le front est autorisé à proposer.
2. **Le coût est hors budget** : 809 966 octets bruts, soit **2,64 × le budget brut** de 300 Ko, et
   47 931 sommets, soit **2,94 ×** ceux d'aujourd'hui — autant à décompresser, analyser et rasteriser sur
   mobile, contre les 2,5 s du §10 (mesuré le 2026-08-13, même outillage).
3. **Le consommateur n'existe pas encore** : la carte n'est pas écrite. Resserrer une tolérance pour un
   besoin que personne n'a exprimé, c'est payer un coût mesuré contre un bénéfice supposé.

Ce n'est pas une porte fermée : `SIMPLIFICATION.intervalle_m` dans `importer.mjs` est un seul paramètre,
aucun code applicatif à retoucher. Mais la décision appartient à la chaîne qui construira la carte, avec
une mesure de terrain à l'appui.

**Question ouverte, adressée à la chaîne front qui construira la carte** : le §10 du brief écrit
« géométries < 300 Ko » sans préciser **bruts ou transférés**. Le référentiel a tranché pour les octets
bruts (arbitrage B-11), l'hypothèse la plus stricte. Si le propriétaire du projet confirme que le budget
porte sur le transféré, la marge disponible change complètement — et la question du point 2 se rouvre.

### Pourquoi les octets peuvent bouger à source inchangée

**Une dépendance transitive de mapshaper qui remonte change la sortie.** C'est constaté, pas supposé : à
source rigoureusement identique (même sha256 `d0316cbc…`, 3 022 441 o), l'import initial — sans lockfile,
dépendances transitives résolues par intervalle sémantique — produisait **278 728 octets**, le ré-import
sous lockfile en produit **278 894**. C'est précisément la raison du lockfile, de `.nvmrc` et de
`reference.json`.

### Trois écarts maximaux, trois provenances

Trois nombres circulent dans ce dépôt pour « l'écart maximal ». **Ils sont tous les trois exacts et ils ne
mesurent pas la même chose.** Aucun ne peut être présenté à la place d'un autre.

| Valeur | Géométrie mesurée | Source de référence | Qui l'a produite |
|---|---|---|---|
| **94,55 m** | l'ancienne, 278 728 o | le téléchargement **pleine précision**, 3 664 738 o | chaîne #2, avec son propre outillage. **Cette source est absente du dépôt** : le chiffre n'est donc plus recalculable |
| **94,31 m** | **la même**, 278 728 o | l'**archive à 5 décimales**, 3 022 441 o | `verifier.mjs`, re-mesure. Même géométrie que ci-dessus, même code de mesure : seule la source de référence change |
| **93,62 m** | la nouvelle, 278 894 o | la même archive à 5 décimales | l'import sous lockfile. **C'est la valeur courante**, celle que porte `reference.json` |

Deux variables distinctes, à ne pas confondre :

- **94,55 → 94,31** : la géométrie n'a pas bougé d'un octet. Seule la **source contre laquelle on mesure**
  change — pleine précision contre archive arrondie à 5 décimales (~1,1 m). L'ancien artefact de recette le
  disait lui-même : « les métriques ci-dessous ont été mesurées sur la source pleine précision ». C'est
  aussi la raison d'être de `TOLERANCES.ecart_m = 2` dans `verifier.mjs`, écrite dès la chaîne #2 : sans
  elle, la recette aurait été rouge en permanence sur une différence attendue.
- **94,31 → 93,62** : la source de mesure est la même. C'est la **géométrie** qui a changé, sous l'effet de
  la remontée de dépendance décrite ci-dessus.

Depuis le lockfile, cette ambiguïté est fermée : les seuils disent que la géométrie est acceptable,
`reference.json` dit qu'elle est **la même**, et les deux se mesurent contre l'archive versionnée — la
seule source que n'importe qui peut recalculer depuis le dépôt.

## Lacunes assumées

- **Communes concernées** : l'attribut n'existe nulle part dans la couche source. `communes` est donc
  **toujours vide**, et `lacunes.communes.statut` vaut `inconnue`. Le thème **omet** la ligne ; il n'écrit
  jamais « aucune commune ». Les peupler demande un second import (IGN ADMIN EXPRESS), avec sa licence, sa
  propre attribution §9 et son millésime : une issue à part entière. **Absent vaut mieux que faux.**
- **27 identifiants côté préfecture pour 25 massifs** : `1326` et `1327` sont en surnombre et **ne
  correspondent à aucun massif de la couche réglementaire**. Aucun nom n'a été inventé pour combler l'écart :
  `massifs_code_depuis_source()` renvoie `null` pour eux, `massifs_massif_existe()` renvoie `false`, et
  l'ingestion comme le portail doivent traiter un identifiant inconnu **sans rien écrire**. Voir
  « Correspondance avec le flux préfectoral » ci-dessus.
