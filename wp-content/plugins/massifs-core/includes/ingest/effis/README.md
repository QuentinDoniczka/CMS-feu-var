# Module `ingest/effis` — zones parcourues par le feu

Récupération côté serveur, validation, mise en cache et re-diffusion depuis notre propre domaine des
**zones parcourues par le feu** publiées par le service EFFIS du Copernicus Emergency Management Service.

Contrat d'interface : [`docs/contracts/issue-11.md`](../../../../../docs/contracts/issue-11.md). Portée de
déploiement : [`docs/decisions/portee-non-publiee.md`](../../../../../docs/decisions/portee-non-publiee.md).

---

## 1. Surface publique

Deux fonctions, et deux seulement, déclarées dans `compat.php` :

| Fonction | Rôle |
|---|---|
| `massifs_zones_parcourues_par_le_feu(): array` | La couche : `etat`, `zones`, `nombre`, `releve_le`, `expire_le`, `peremption_secondes`, `fenetre_jours`, `surface_minimale_ha` |
| `massifs_attribution_zones_parcourues_par_le_feu(): array` | La mention de source du §9 du brief : `phrase`, `faits` |

Toutes deux sont **totales** — aucune exception, aucun `WP_Error`, aucun `null`, toutes les clés toujours
présentes — et rendent des données **brutes et non échappées**. Elles ne prennent **aucun argument** : la
couche est une fenêtre glissante, pas un statut daté.

Une route publique en lecture seule sert les mêmes octets depuis notre domaine :

```
GET /wp-json/massifs/v1/zones-parcourues-par-le-feu
```

`200` dans tous les états de la donnée, `304` sur `If-None-Match` concordant, `Cache-Control: no-cache`,
ETag faible. Aucun paramètre, aucune route d'écriture.

### 1.1 La règle centrale — à ne jamais affaiblir

> **Le test discriminant est `etat`. Jamais `nombre`. Jamais `count( $zones )`.**

`aucune_zone` et `couche_effis_indisponible` portent **tous deux** `nombre === 0`. Ce qui les sépare est
`releve_le` : renseigné dans le premier, chaîne vide dans le second. « Vide parce que mesuré » porte une
date de mesure ; « vide parce que muet » n'en porte aucune.

Un consommateur qui teste `count( $zones ) === 0` écrit « aucune zone parcourue par le feu » alors que la
vérité est « nous ne savons pas ». C'est un faux négatif sur une donnée de sécurité.

### 1.2 Ce que le thème ne lit pas

`zones[]['surface_ha']` et `zones[]['geometrie']` sont **présentes et jamais lues par le thème** : ce sont
des clés de transport, consommées par la route REST et par la future couche cartographique. La surface
affichable est `surface_texte`, **déjà formatée en PHP**, unité et espace insécable compris.

La phrase de limites de `MASTER.md` §11.3 **n'est pas publiée par ce module** : elle appartient au thème,
qui la recopie verbatim. Deux sources pour une même chaîne divergeraient.

---

## 2. États

Énumération **fermée à trois valeurs**. Toute quatrième valeur est un acte de contrat.

| État | Émis quand |
|---|---|
| `zones_disponibles` | Relevé validé, âge ≤ T, au moins une zone après filtre départemental |
| `aucune_zone` | Relevé validé, âge ≤ T, aucune zone après filtre |
| `couche_effis_indisponible` | Aucun relevé validé jamais · ou âge > T · ou coupe-circuit armé · ou dernier relevé rejeté sans relevé valide dans T |

**Il n'existe aucune clé `perimee` et aucun état intermédiaire.** Au-delà de T, la couche bascule
entièrement en `couche_effis_indisponible`. Pour un statut, une bannière de péremption s'ajoute sans
masquer, parce qu'un statut périmé reste la meilleure information disponible. Pour cette couche, la
péremption signifie que la fenêtre glissante est fausse et qu'une zone survenue depuis serait **absente** :
montrer la donnée sous un avertissement laisserait lire « voici les zones parcourues par le feu » sous une
phrase que l'œil saute.

**Le régime préfectoral estival n'a aucun effet sur ce module.** La couche est servie toute l'année : elle
n'est pas un statut, et le §4.4 du brief ne lui attache aucune périodicité. La propriété est tenue par
l'absence de couplage — ce module n'appelle aucune fonction du calendrier du dispositif et n'en charge
aucune classe, ce qui se vérifie par `grep` sur tout le répertoire.

---

## 3. Fichiers

| Fichier | Rôle |
|---|---|
| `bootstrap.php` | Point d'entrée découvert par `massifs-core.php` l. 122-167 ; charge le module et l'amorce |
| `class-bootstrap.php` | Pose les crochets. La route REST est déclarée **avant** le coupe-circuit |
| `class-settings.php` | Résolution constante > passerelle > option > défaut > filtre. Coupe-circuit. Péremption T |
| `class-fetcher.php` | **Seul fichier du module qui touche le réseau** |
| `class-validator.php` | Cinq couches : transport, forme, géométrie, emprise, temporel |
| `class-releve-repository.php` | Option `massifs_effis_releve`, `autoload = false`, un seul relevé conservé |
| `class-state-repository.php` | Option `massifs_effis_etat` : tentatives, succès, échecs, verrous d'alerte, journal |
| `class-couche.php` | Projection : **la garde de péremption, à la lecture** |
| `class-attribution.php` | Mention de source du §9 du brief |
| `class-notifier.php` | Deux alertes courriel, une fois chacune par épisode |
| `class-runner.php` | Orchestration et gardes de cadence |
| `class-schedule.php` | Crochet `massifs_effis_recuperation`, récurrence `hourly` |
| `class-route.php` | Route publique de lecture, ETag faible, `304` |
| `compat.php` | Les deux fonctions `massifs_*()`, gardées par `function_exists()` |

**Un seul fichier touche le réseau.** Un `grep` des primitives HTTP de WordPress et de PHP sur ce
répertoire ne doit rendre aucune ligne hors de `class-fetcher.php` — pas même dans ce README, dont la
rédaction évite délibérément de citer les jetons recherchés.

---

## 4. Ingestion

| | |
|---|---|
| **Origine** | `Settings::url()`. Constante `MASSIFS_EFFIS_URL` > `massifs_effis_url_source()` > option > **défaut : chaîne vide** > filtre `massifs_effis_url` |
| **Coupe-circuit** | `Settings::is_disabled()` : vrai si `MASSIFS_EFFIS_DISABLE`, ou si l'environnement est `local`/`development` sans `MASSIFS_EFFIS_URL`. Ne lit aucune option, n'est jamais mémoïsé |
| **Planification** | Crochet `massifs_effis_recuperation`, récurrence native `hourly`, `ensure()` auto-réparateur sur `init`, décalage `+1 min`. Aucun filtre `cron_schedules` |
| **Gardes avant tout octet réseau** | coupe-circuit armé → retour · URL vide → retour · dernière **tentative** de moins de 30 min → retour · dernier **succès** de moins de 6 h → retour. Plafond nominal : **4 appels sortants par jour** |
| **Récupération** | La primitive HTTP de WordPress, seule. `sslverify` et temporisation **ré-imposés après le filtre d'arguments**. `redirection => 2`. **Un 404 est un échec** : il n'existe aucun état « pas encore publié » pour une fenêtre glissante |
| **Stockage** | Option `massifs_effis_releve`, `autoload = false`, un seul `update_option` après validation complète — écriture atomique, aucun état partiel représentable |
| **Horodatage faisant autorité** | L'instant du relevé **réussi et validé**, ISO 8601 UTC. Jamais l'instant de la tentative, jamais un `Last-Modified` de la source. **Un échec n'écrit rien** |
| **Péremption T** | **86 400 s (24 h)** par défaut, redéfinissable par `MASSIFS_EFFIS_PEREMPTION_SECONDES`, bornée `[3600, 604800]`. Appliquée **à la lecture**, jamais par effacement du stockage |
| **Alertes** | Deux, une seule fois chacune par épisode : traversée de T, et rejet de validation. Verrou posé quel que soit le retour de `wp_mail`, ré-armé au premier succès |
| **Registre transverse** | `massifs_enregistrer_releve_reussi( 'effis', $instant )` sous garde `function_exists()`, **écrit et jamais relu** |

### 4.1 Validation — cinq couches ordonnées

1. **Transport** — plafond de corps 2 MiB ; premier caractère `<` = page d'erreur servie en 200 ; corps ne
   commençant pas par `{` refusé. **Aucun plancher de taille** : un `FeatureCollection` vide est court et
   légitime.
2. **Forme** — `type === 'FeatureCollection'` ; `features` tabulaire, `[]` **valide** ;
   `geometry.type ∈ {Polygon, MultiPolygon}`. Un `Point` signale qu'on a interrogé la mauvaise couche, et
   le lot entier est refusé.
3. **Géométrie** — coordonnées finies et bornées (EPSG:4326, `[lon, lat]`) ; plafonds de sommets par
   entité et par lot ; plafond d'entités **avant** filtre.
4. **Emprise** — `massifs_emprise()['bbox']` sous garde `function_exists()`. Hors emprise : **écarté en
   silence**, la source est continentale. `bbox` absente : **lot entier rejeté**, *fail closed*, sans quoi
   « filtrée sur le département » du §4.4 du brief n'est pas tenable.
5. **Temporel** — `Last-Modified` absent : contrôle sauté en silence. Antérieur à `2 × T` : rejet.

### 4.2 Ce qui n'est **pas** une aberration

À ne jamais réintroduire comme motif de rejet :

- **zéro entité** — c'est le cas nominal ;
- **un lot identique au précédent** — la fenêtre change lentement ;
- **une entité très grande ou très petite** — la surface ne se juge pas ;
- **une entité chevauchant plusieurs massifs** — une zone parcourue par le feu ignore nos découpages.

---

## 5. La simulation est une **origine**, jamais une **branche**

`portee-non-publiee.md` §4 arrête un **connecteur simulé** pour cette source. Ce module l'applique de la
seule manière qui préserve la valeur de la validation :

- il n'existe **aucun** `if ( $simule )`, **aucune** fixture chargée depuis le disque, nulle part ;
- une charge simulée traverse `Fetcher` puis les **cinq couches** de `Validator`, exactement comme une
  charge réelle ;
- basculer vers la source réelle est **un changement d'URL**, et rien d'autre.

Une branche ferait contourner le validateur précisément dans le mode où l'on tourne réellement, et la
ligne de recette « couche EFFIS, nominal + indisponibilité » serait prouvée contre un chemin de code qui
n'existe pas en production.

**Le nominal simulé est valide et vide.** Aucun polygone fictif n'est dessiné sur un vrai massif : un
polygone de zone parcourue par le feu simulé est une **affirmation géographique fausse**, attribuée à
« © Union européenne, Copernicus Emergency Management Service / EFFIS ». Les seuls polygones non vides du
projet vivent dans les fixtures de `tests/scenarios/40-*` à `46-*`.

---

## 6. Points ouverts — à ne jamais combler par déduction

> **L'URL et le protocole réels du service.** Jamais interrogés, jamais relevés. **Aucune URL réelle n'est
> écrite en dur comme valeur par défaut** : le défaut est la chaîne vide, qui produit honnêtement
> `couche_effis_indisponible`. Le module contractualise **GeoJSON `FeatureCollection`** à la frontière
> `Fetcher` → `Validator` ; si la source réelle rend du GML ou du WMS, le changement porte sur ces deux
> classes seulement.

> **Les noms d'attributs du schéma source** (surface, dates d'observation). Jamais relevés. La table de
> correspondance de `Settings::attributs_defaut()` les expose, et **ses valeurs par défaut sont les noms de
> NOTRE connecteur simulé** — `id`, `surface_ha`, `premiere_observation`, `derniere_observation`.
> **Ce ne sont pas les noms d'EFFIS.** Attribut absent ⇒ `''` ou `0.0`, jamais une valeur fabriquée.

> **Le nom exact de la couche source.** Le §4.4 du brief cite « Burnt Areas 7 Days ». Ce libellé est cité
> **ici, comme nom de source**, et nulle part ailleurs : `faits['couche']` vaut la chaîne vide, parce que
> nous n'avons jamais interrogé cette couche et ne pouvons pas affirmer son nom exact comme donnée publiée.

> **La source publie-t-elle des instants ou des jours civils ?** Inconnu. Le contrat exige des **instants
> ISO 8601 UTC complets** à la frontière. Un jour civil nu est refusé et le champ vaut `''` : **aucun midi
> UTC n'est fabriqué**, midi n'étant pas l'heure d'une observation satellite.

> **Commune la plus proche.** Aucun référentiel communal n'existe dans le projet
> (`includes/domain/massifs/README.md` l. 405-408). La clé existe, vaut `''` en permanence, et le gabarit
> omet purement la paire `<dt>`/`<dd>`. Aucun tiret, aucun « non renseigné », et surtout **pas de
> substitution du massif le plus proche** — substituer une notion à une autre est une conflation.

> **Licence de redistribution des polygones.** La contrainte n° 2 du projet nous **oblige** à re-servir la
> géométrie depuis notre domaine ; le §9 du brief ne donne que la chaîne d'attribution, jamais les
> conditions de réutilisation. Question ouverte, possiblement en sommeil au titre de
> `portee-non-publiee.md` §5 — **à confirmer, pas à supposer**.

> **Péremption T = 24 h.** Tranchée par le contrat faute d'être dérivable, avec son motif écrit. Seuil de
> sécurité **soumis à confirmation** ; redéfinissable par constante, sans changement de code.

---

## 7. Constantes et filtres

| Constante | Effet |
|---|---|
| `MASSIFS_EFFIS_URL` | URL de la source. La poser **réarme** le module en environnement local |
| `MASSIFS_EFFIS_DISABLE` | Coupe-circuit inconditionnel |
| `MASSIFS_EFFIS_PEREMPTION_SECONDES` | Péremption T, bornée `[3600, 604800]` |
| `MASSIFS_EFFIS_CONNECTEUR` | Portée déclarée : `simule` ou `reel` |
| `MASSIFS_EFFIS_HTTP_TIMEOUT` | Temporisation HTTP, bornée `[1, 30]` |
| `MASSIFS_EFFIS_USER_AGENT` | Identification du robot |

| Filtre | Effet |
|---|---|
| `massifs_effis_url` | Dernier mot sur l'URL résolue |
| `massifs_effis_connecteur` | Dernier mot sur la portée déclarée |
| `massifs_effis_attributs` | Table de correspondance des attributs source |
| `massifs_effis_peremption_secondes` | Péremption T, **re-bornée après filtre** |
| `massifs_effis_http_args` | Arguments sortants ; `sslverify` et `timeout` sont **ré-imposés après** |
| `massifs_effis_alerte_destinataires` | Destinataires des alertes |

| Action | Émise quand |
|---|---|
| `massifs_effis_tentative` | Une tentative de récupération démarre |
| `massifs_effis_releve_enregistre` | Un relevé validé vient d'être enregistré |
| `massifs_effis_echec` | Un échec de récupération est survenu |
| `massifs_effis_cache_a_invalider` | La couche publiée a changé : le cache de page la portant est caduc |

---

## 8. Recette

Scénarios `tests/scenarios/40-*` à `46-*`. Aucun n'est suffixé `.arme.php` : `tests/run.sh` l. 25 code en
dur un armement **préfecture**, et un scénario EFFIS y recevrait la mauvaise constante. L'armement se fait
**en cours de requête**, ce que rend possible un coupe-circuit non mémoïsé.
