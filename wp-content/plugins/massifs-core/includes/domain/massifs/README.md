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
| `compat.php` | Les 13 fonctions publiques `massifs_*()` |
| `.htaccess` | Interdit l'accès web à tout ce répertoire — code, outillage, et surtout l'archive source de 3 Mo |
| `build/package.json` · `build/.gitignore` | Outillage de build seulement ; jamais exécuté à l'exécution du site |
| `build/identites.json` | **Registre d'identités gelées, édité à la main, en ajout seul. Fait autorité sur l'identité** |
| `build/importer.mjs` | Chaîne d'import reproductible |
| `build/verifier.mjs` | Recette : rejoue tous les contrôles sans rien réécrire |
| `build/source/massifs-13.full.geojson` | Source archivée (3 Mo), entrée du pipeline |
| `../../../data/massifs-13.php` | **Généré.** Métadonnées et 25 lignes de massif |
| `../../../data/massifs-13.geometrie.json` | **Généré.** Géométrie simplifiée, servie en statique au navigateur |
| `../../../data/massifs-13.fidelite.json` | **Généré.** Recette de fidélité, hors contrat, aucun consommateur applicatif |

Les trois artefacts de `data/` ne s'éditent **jamais** à la main.

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
npm install            # mapshaper épinglé à 0.6.102 ; `npm ci` une fois le lockfile commité
# remplacer build/source/massifs-13.full.geojson par la nouvelle source (EPSG:4326)
npm run importer       # émet les 3 artefacts, ou s'arrête sans rien écrire
npm run verifier       # rejoue tous les contrôles, code de sortie ≠ 0 en cas de dérive
```

`npm run verifier` a besoin d'un binaire `php` (variable `PHP_BIN` pour le désigner) : il lit
`data/massifs-13.php` via `php -r`. C'est pour cela que la garde de ce fichier généré est volontairement
**sans `exit`** — hors WordPress, il retourne un tableau vide au lieu d'interrompre le processus.

Relire ensuite le diff de `data/massifs-13.php` avant de commiter : **aucun `code` ne doit changer**. Un
`code` modifié dans un diff est un défaut, jamais une variante.

### Contrôles bloquants de l'import

Aucun artefact n'est écrit si l'un d'eux échoue (émission atomique : fichiers temporaires puis renommage).

- une entité source sans entrée dans `identites.json` ;
- un `code` qui ne respecte pas `^[a-z0-9_-]{1,64}$` ;
- un `libelle` différent de `source.nom_massif` sans `note_provenance` ;
- deux entités source rapportées au même `code` ; deux `gid` identiques ;
- une identité sans entité source et sans `retire_le` ;
- un `libelle` vide ;
- un nombre d'entités géométriques différent de 25 ;
- une `Feature` publiée portant autre chose que `properties.code` ;
- une bbox de massif qui déborde de l'emprise ;
- géométrie > 300 Ko bruts, écart max > 120 m, écart de surface global > 0,5 %, pire massif > 3 %,
  anneaux supprimés > 0,5 % de la surface.

## Simplification et budget

```
mapshaper source.geojson -simplify dp interval=90 keep-shapes -o precision=0.0001 format=geojson sortie.json
```

Mesuré : **278 728 octets bruts** (74 133 gzip), 16 272 sommets conservés sur 160 602 (10,1 %),
**écart maximal 94,55 m**, p99 79,3 m, moyenne 21,9 m, écart de surface global −0,07 %, pire massif
`collines-de-gardanne` à 1,49 %. La topologie est préservée : les 10 frontières partagées sont simplifiées
une seule fois, en arcs partagés — donc ni trou ni recouvrement entre voisins.

Le budget de 300 Ko porte sur les **octets bruts**, pas sur le gzip : la compression HTTP n'est vérifiée sur
aucune cible, elle reste une marge et non une béquille.

`zoom_max = 11` est **mesuré** : 94,55 m valent 0,85 px à z10 et 1,71 px à z11 à la latitude 43,5°. La carte
officielle de référence est elle-même départementale ; l'écart est invisible à l'échelle où l'information est
publiée. **Voie de relèvement documentée** : si la compression HTTP est confirmée sur la cible, `interval=20`
donne un écart de 20 m, sous-pixel à z12, pour 809 833 octets bruts / 196 905 gzip — c'est un simple changement
de `SIMPLIFICATION.intervalle_m` dans `importer.mjs`, aucun code applicatif à retoucher.

La source archivée est arrondie à 5 décimales (~1,1 m), très en dessous de l'intervalle de 90 m : un ré-import
peut produire des octets différents de l'artefact actuel, mais des métriques équivalentes. La recette porte sur
les **seuils**, jamais sur une égalité binaire.

## Lacunes assumées

- **Communes concernées** : l'attribut n'existe nulle part dans la couche source. `communes` est donc
  **toujours vide**, et `lacunes.communes.statut` vaut `inconnue`. Le thème **omet** la ligne ; il n'écrit
  jamais « aucune commune ». Les peupler demande un second import (IGN ADMIN EXPRESS), avec sa licence, sa
  propre attribution §9 et son millésime : une issue à part entière. **Absent vaut mieux que faux.**
- **27 identifiants côté préfecture pour 25 massifs** : le flux préfectoral expose `131`…`1327`, où le suffixe
  est le rang alphabétique ; `1326` et `1327` sont en surnombre et **ne correspondent à aucun massif de la
  couche réglementaire**. Aucun nom n'a été inventé pour combler l'écart : `massifs_massif_existe()` renvoie
  `false` pour ces codes, et l'ingestion comme le portail doivent traiter un code inconnu **sans rien écrire**.
