# Contrat d'interface — Issue #2 — Référentiel des périmètres de massifs (Bouches-du-Rhône)

**Gelé le** 11 août 2026 par `lead-issue-cms` (chaîne #2) · **Statut** : contraignant.

Ce contrat est la frontière entre le module `Massifs\Domain\Massifs` de l'extension `massifs-core` (qui
possède l'identité, les libellés et la géométrie des massifs) et tout consommateur — thème, portail,
future couche REST, chaînes sœurs #1 (ingestion préfecture) et #3 (statuts). Une divergence constatée en
revue est un défaut, pas une variante.

**Périmètre de l'issue #2** : le référentiel seul. Aucune route REST, aucun cron, aucun rôle, aucun écran,
aucun hook, aucun HTML, aucune écriture.

**Réconcilié avec** [`issue-3.md`](issue-3.md), gelé antérieurement. Les points de divergence sont traités
en Arbitrages ; le vocabulaire de #3 fait foi partout où il existait déjà.

---

## Fonctions de lecture exposées par l'extension

Toutes préfixées `massifs_`, toutes retournant des **tableaux associatifs** (jamais des objets), toutes
**totales** : aucune exception, aucun `WP_Error`, une valeur de retour définie même si le référentiel est
absent ou corrompu. Les valeurs sont **brutes et non échappées** — le thème échappe une fois, à la sortie.

```php
massifs_referentiel( bool $inclure_retires = false ): array
massifs_massif( string $code, bool $inclure_retires = false ): ?array
massifs_massif_existe( string $code, bool $inclure_retires = false ): bool
massifs_codes( bool $inclure_retires = false ): array
massifs_libelle( string $code ): string
massifs_libelles( bool $inclure_retires = false ): array
massifs_compte( bool $inclure_retires = false ): int
massifs_emprise(): array
massifs_geometrie(): array
massifs_attribution(): array
massifs_lacunes(): array
massifs_referentiel_etat(): array
massifs_referentiel_disponible(): bool

// Correspondance avec le flux préfectoral — voir B-16
massifs_code_depuis_source( string $identifiant_source ): ?string  // '131' => 'alpilles' ; '1326' => null
massifs_source_depuis_code( string $code ): ?string                // 'alpilles' => '131'
massifs_correspondance_source(): array                             // massif_code => identifiant_source, 25 entrées
```

**`massifs_referentiel_codes_source()` n'existe pas et ne doit pas être appelée.** Le garde-fou de la
chaîne #1 assainit sur `/^\d{3,4}$/` et compare le jeu d'identifiants **émis par le flux** (27) : lui
passer nos codes kebab rejetterait 100 % des charges réelles. Ce garde-fou est correct tel quel.

Implémentation équivalente sous `Massifs\Domain\Massifs\{referentiel, massif, existe, codes, libelle,
libelles, compte, emprise, geometrie, attribution, lacunes, etat}`. **Seules les fonctions `massifs_*` sont
publiques** (règle 2 des interdits de #3, reprise telle quelle).

### `massifs_referentiel( bool $inclure_retires = false ): array`

Retour : tableau **clé = `massif_code`**, **pré-trié par `tri`**, valeurs de la forme ci-dessous.
Usage : `foreach ( massifs_referentiel() as $code => $massif )`.
Référentiel indisponible → `array()`.

### Forme d'une ligne de massif

```php
[
  'code'            => 'sainte-victoire',   // string, GELÉ À VIE, === la clé du tableau
  'libelle'         => 'Sainte-Victoire',   // string non vide — le seul champ affichable
  'tri'             => 'sainte-victoire',   // string ASCII minuscule — ordre stable, sans setlocale
  'communes'        => array(),             // list<string> — TOUJOURS vide dans l'issue #2
  'communes_source' => 'inconnue',          // string
  'actif'           => true,                // bool — déjà dérivé, ne comparez aucune date
  'retire_le'       => null,                // ?string 'YYYY-MM-DD'
  'bbox'            => array( 'ouest' => 0.0, 'sud' => 0.0, 'est' => 0.0, 'nord' => 0.0 ), // EPSG:4326
  'centre'          => array( 'lon' => 0.0, 'lat' => 0.0 ),                                // EPSG:4326
  'source'          => array(
      'gid'                    => 0,                 // int — PROVENANCE SEULE, jamais une clé de jointure
      'nom_massif'             => 'Sainte-Victoire', // string — verbatim DDTM, jamais affiché
      'revision'               => '2023-02-14',
      'identifiant_prefecture' => '1323',            // string — GELÉ, jamais calculé (B-16)
  ),
  'note_provenance' => null,                // ?string — non vide SSI libelle !== source.nom_massif
]
```

Les valeurs numériques sont des **exemples de type**, pas des coordonnées réelles.

### `massifs_emprise(): array`

```php
[
  'bbox'     => array( 'ouest' => float, 'sud' => float, 'est' => float, 'nord' => float ) | null,
  'centre'   => array( 'lon' => float, 'lat' => float ) | null,
  'zoom_max' => 11,   // int — terme de contrat pour l'épique carte, MESURÉ (voir B-14)
]
```

**La carte ne code aucune coordonnée en dur.** Leaflet attend `[[sud, ouest], [nord, est]]` : la
conversion appartient au front. `zoom_max = 11` est **mesuré, pas postulé** : l'écart maximal de la
géométrie simplifiée est de **94,55 m**, soit 0,85 px à z10 et 1,71 px à z11 (14,0 m/px à z13 ;
111,8 m/px à z10, à la latitude 43,5°). Le front ne propose pas de zoom supérieur sur la couche massifs.
`zoom_min` est une décision front. Voir B-14 pour la voie de relèvement à z12.

### `massifs_geometrie(): array`

```php
[
  'disponible' => true,        // bool — présence des MÉTADONNÉES, pas du fichier (voir Interdits 3)
  'url'        => '…/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json?v=a1b2c3d4',
  'version'    => 'a1b2c3d4',  // string — 8 hex du sha256, jeton de cache-busting
  'sha256'     => '…',
  'octets'     => 0,           // int — précalculé au build, aucun filesize() au runtime
  'format'     => 'geojson',
  'zoom_max'   => 11,
]
```

Fichier **statique**, même origine, servi par le serveur web. Aucune route REST, aucun PHP dans le chemin.
Contenu : `FeatureCollection` GeoJSON EPSG:4326 ; chaque `Feature` porte `properties.code` **strictement
égal** au `massif_code`, et **rien d'autre** — pas de nom, pas de niveau, pas de couleur.

### `massifs_attribution(): array`

```php
[
  'phrase'        => 'Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du 14 février 2023',
  'phrase_courte' => 'DDTM 13 / data.gouv.fr — Licence Ouverte 2.0',
  'lien_source'   => 'https://www.data.gouv.fr/…',
  'lien_licence'  => 'https://www.etalab.gouv.fr/…',
  'faits'         => array( /* producteur, jeu_de_donnees, couche, dataset_id, geoide_id,
                              donnees_du, donnees_du_libelle, recupere_le, sha256_source,
                              licence_nom, licence_version, licence_identifiant,
                              crs_source, crs_publie, base_reglementaire */ ),
]
```

### `massifs_lacunes(): array`

```php
[ 'communes' => array(
    'statut'            => 'inconnue',
    'raison'            => "aucun attribut de commune dans la couche L_MASSIFS_FORESTIERS_S_013",
    'source_pressentie' => 'IGN ADMIN EXPRESS',
) ]
```

### `massifs_referentiel_etat(): array`

`disponible` `bool` · `code` `referentiel_ok|referentiel_indisponible` · `raison` `null|fichier_absent|
contenu_invalide|schema_incompatible|referentiel_vide|ligne_invalide` · `schema` `int` · `genere_le`
`?string` ISO 8601 · `nombre` `int`.

### Valeurs dégradées (référentiel indisponible)

`massifs_referentiel()` → `array()` · `massifs_massif()` → `null` · `massifs_massif_existe()` → `false` ·
`massifs_compte()` → `0` · `massifs_libelle( $code )` → `$code` · `massifs_emprise()['bbox']` → `null`
(`zoom_max` conservé) · `massifs_geometrie()['disponible']` → `false` · `massifs_attribution()['phrase']`
→ `''`. **Toutes les clés du contrat restent présentes** : le thème n'écrit jamais `isset()`.

## Routes REST

**Aucune dans cette issue.** La géométrie est un fichier statique, pas une route : une route REST imposerait
un amorçage WordPress complet par requête pour une donnée immuable. Le point d'accès §5.4 appartient à #3
et joindra sur `massif_code`. Aucun consommateur ne doit planifier d'appel REST contre l'issue #2.

## Chargement du module

- **Point d'entrée** : `wp-content/plugins/massifs-core/includes/domain/massifs/module.php`
- **Nom imposé par l'arbitrage A-9 de `issue-3.md`** : le chargeur de #3 découvre un chemin unique et
  prédit `<couche>/<module>/module.php` sur une liste de couches fixe. Un fichier nommé autrement ne serait
  **jamais chargé**.
- Le module **ne dépend d'aucun chargeur** : chaque fichier `require_once` ses propres dépendances, et
  n'importe lequel est un point d'entrée valide quel que soit l'ordre.
- **Idempotent** : garde par constante + `function_exists`. `require_once` deux fois est sans effet.
- **Zéro hook, zéro effet de bord, zéro sortie.** Aucun `add_action`, aucun `add_filter`, aucun
  `register_activation_hook`, aucun `dbDelta`, aucune table. Le module ne s'abonne ni à
  `massifs_core_amorcage`, ni à `massifs_core_installation`, ni à `massifs_core_signature_schema` (#3) :
  il n'a rien à installer. Charger le module ne fait rien d'observable ; ne pas le charger n'est pas fatal.
- Constante exposée : `MASSIFS_DOMAINE_MASSIFS_VERSION`.
- **Aucune classe**, donc aucune interaction avec l'autoloader `Massifs\` de #3.

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `referentiel_indisponible` | Fichier de données absent, illisible, de schéma incompatible, vide, ou contenant une ligne invalide | **Plus grave que `information_indisponible`** : sans référentiel il n'y a ni carte ni liste. Message de repli + lien vers la carte officielle. Rédaction : thème |
| `massif_retire` (`actif === false`) | Massif disparu d'une révision source, conservé pour l'historique | Sorti de la liste du jour ; son URL et son historique restent valides |
| `communes_inconnues` (`communes === array()`) | Aucun attribut de commune dans la source | **La ligne « communes concernées » est omise.** Jamais « aucune commune » |
| `geometrie_indisponible` (`disponible === false`) | Métadonnées de géométrie absentes | Carte non initialisée ; la liste textuelle porte toute l'information (§5.5) |
| `information_indisponible` · `hors_saison` · `donnee_perimee` · `couche_effis_indisponible` | **Hors périmètre de l'issue #2** — définis par `issue-3.md` et par EFFIS | — |

Le plugin fournit les **codes**, le thème fournit les **mots**. L'issue #2 n'expose aucune chaîne
d'interface en dehors de l'attribution §9.

## Chaînes fournies par le serveur

| Fourni par l'extension (#2) | Composé par le thème |
|---|---|
| `libelle` de chaque massif — nom officiel reproduit | Toute phrase rédactionnelle |
| `attribution.phrase` / `phrase_courte` — mention §9 exacte | La mise en page du pied de carte et des mentions légales |
| `attribution.lien_source` / `lien_licence` — URLs brutes | Tout HTML, tout échappement |
| `lacunes.raison` — limite assumée, citable sur « La démarche » | La rédaction qui l'entoure |

## Interdits

**Pour le thème et tout consommateur**

1. Ouvrir `data/massifs-13.geometrie.json` en PHP (`file_get_contents`, `json_decode`, `filesize`,
   `hash_file`). Utiliser `massifs_geometrie()['url']`.
2. Ouvrir `data/massifs-13.php` directement (`require`, `include`). Passer par les fonctions.
3. Supposer que `geometrie()['disponible'] === true` garantit la présence physique du fichier : **PHP ne
   stat jamais cet artefact**. Le front dégrade vers la liste textuelle si la requête échoue (§5.5).
4. Lire ou dépendre de `data/massifs-13.fidelite.json` — artefact de recette, hors contrat.
5. Normaliser un `massif_code` avant de le passer (`sanitize_title`, `strtolower`, repli de diacritiques).
   Passer le segment d'URL tel quel et traiter le `null`. Une normalisation ferait silencieusement
   correspondre `Sainte Victoire!` à un massif réel — une donnée fausse présentée comme juste.
6. Construire un chemin de fichier à partir d'un `massif_code`.
7. Afficher `source.nom_massif` — c'est `libelle` qui s'affiche.
8. Utiliser `source.gid` comme clé de jointure. La clé est `massif_code`, partout, sans exception.
9. Trier la liste, ou dépendre de `setlocale` : elle arrive triée par `tri`.
10. Écrire « aucune commune » quand `communes` est vide.
11. Coder une coordonnée, une bbox ou un centre en dur dans le CSS, le JS ou un gabarit.
12. Autoriser un zoom > `massifs_emprise()['zoom_max']` (= 11) sur la couche massifs.
13. Échapper à la lecture puis ré-échapper au rendu : le module renvoie du brut, le thème échappe une fois.
14. Éditer `data/massifs-13.*` à la main — artefacts générés ; passer par `includes/domain/massifs/build/`.
15. Écrire dans le référentiel : il n'y a **aucune** fonction d'écriture. §4.1 exige des périmètres
    importés puis maintenus, **jamais dessinés à la main**.

**Pour l'extension**

16. Le thème n'appelle jamais une source externe ni une fonction d'ingestion. La récupération de l'open
    data est strictement côté serveur, **au build**, jamais au runtime : le navigateur ne contacte jamais
    data.gouv.fr, GeoIDE ni DataSud.
17. Le thème ne calcule jamais une règle métier (saison, péremption, formatage de niveau) — voir #3.
18. L'extension n'émet aucun HTML de présentation publique.
19. Le domaine `massifs` ne connaît, n'appelle et ne valide jamais `Massifs\Domain\Statuts` (#3). La
    dépendance est unidirectionnelle : #3 valide `massif_code` **sur sa forme**, jamais sur son existence
    (interdit 15 de `issue-3.md`) ; c'est le **portail** qui appelle `massifs_massif_existe()` avant
    d'enregistrer.

## Arbitrages

| # | Désaccord ou point ouvert | Décision retenue | Raison |
|---|---|---|---|
| B-1 | **Nom de la clé d'identité** : `id` (mon plan back) vs `massif_code` (`issue-3.md`, gelé avant moi) | **`massif_code` partout ; le champ de la ligne se nomme `code`** | C'est exactement la panne classique du travail parallèle — une clé nommée différemment de chaque côté. `issue-3.md` est gelé et tout son vocabulaire d'écriture et de lecture porte `massif_code` ; le thème ferait `$statuts[ $massif['id'] ]` sans savoir que c'est la même chose. Le contrat gelé en premier fait foi |
| B-2 | **Style de nommage des fonctions** : `massifs_get_liste()` (mon plan) vs `massifs_<nom>()` (#3 : `massifs_legende`, `massifs_fraicheur`, `massifs_saison`) | **Sans `get_`** : `massifs_referentiel()`, `massifs_massif()`, `massifs_libelle()`… | Une seule maison, un seul style. Deux conventions dans le même préfixe `massifs_` obligeraient à mémoriser laquelle s'applique à quoi |
| B-3 | **Nom du point d'entrée** : `bootstrap.php` (mon plan) vs `module.php` (arbitrage A-9 de #3) | **`module.php`** | #3 possède `massifs-core.php` et a gelé un chargeur qui découvre **un chemin unique et prédit** `<couche>/<module>/module.php`, en rejetant explicitement le `glob` récursif. Un `bootstrap.php` ne serait jamais chargé — le module aurait été livré mort |
| B-4 | **Forme du code** | **`/^[a-z0-9_-]{1,64}$/`, contrôlé au build**, sortie en échec sinon | Contrainte dure posée par la dépendance n° 3 de `issue-3.md`. Les 25 codes gelés la respectent (`sainte-victoire`, `cote-bleue`, `lancon`…) |
| B-5 | `massifs_massif()` retourne `?array`, alors que #3 pose « aucune fonction de lecture ne retourne `null` ni `false`, jamais » | **`?array` maintenu**, systématiquement doublé de `massifs_massif_existe()` | Les deux cas ne sont pas les mêmes. #3 **doit** rendre une ligne par code demandé, car un tableau de statuts à trous casserait le rendu. Un référentiel interrogé sur un code inconnu n'a rien d'honnête à répondre : un tableau vide « en forme de massif » inviterait à afficher un massif fantôme. `null` est le refus explicite. Divergence assumée et documentée, pas un oubli |
| B-6 | Qui possède la phrase d'attribution §9 des périmètres ? #3 range « les attributions rédigées du §9 » côté thème | **L'extension possède la phrase des périmètres** ; le thème possède le rendu | §9 énumère cinq attributions d'origines différentes ; chacune appartient à la chaîne qui possède la donnée. Celle-ci n'est pas de la rédaction : la Licence Ouverte 2.0 **impose** la citation exacte de la source et de la date, et cette date (`2023-02-14`) est une donnée du référentiel. Trois chaînes qui l'assembleraient produiraient trois variantes, dont deux non conformes |
| B-7 | **Identifiant stable : `gid` source, slug recalculé, ou clé gelée ?** | **Clé kebab-case ASCII que nous possédons, gelée au premier import et jamais recalculée.** `gid` et `nom_massif` rétrogradés en indices de rapprochement | **`gid` est un rang alphabétique, c'est vérifié, pas supposé** : les 25 valeurs sont exactement 1…25 dans l'ordre alphabétique des noms. Insérer un massif — `Camargue`, que la couche sœur `L_MASSIFS_EXPOSES_FDF_S_013` contient déjà — **renumérote 22 massifs sur 25 en silence**, et tout l'historique des statuts pointerait vers le mauvais massif. Cette même couche sœur livre 28 lignes pour 27 `fid` distincts : les clés de substitution DDTM ne sont ni stables ni uniques. Un slug **recalculé** à chaque import a la bonne forme et la mauvaise règle (une détroncature ou un renommage casserait l'identité) ; **gelé**, il est invariant sous correction de diacritiques, lisible dans les URLs et dans le JSON §5.4, et il nous appartient |
| B-8 | **Libellés d'affichage vs orthographes source** | **24 libellés verbatim ; une seule correction : `Chaine des Cotes` → `Chaîne des Côtes`** | Vérification en sources officielles : le PDF journalier de la préfecture et la table HTML de `risque-prevention-incendie.fr/13` énumèrent les 25 noms et **n'accentuent que `Chaîne des Côtes` et `Lançon`**. `Cote Bleue`, `Etoile` et `Trevaresse` y sont **sans accent, dans un document qui porte `î`, `ô` et `ç` sur la même ligne** — ce n'est donc pas un artefact d'encodage mais l'orthographe officielle. Écrire `Côte Bleue` nous ferait diverger du document que le visiteur consulte pour nous vérifier. `note_provenance` cite l'attestation pour la seule ligne corrigée |
| B-9 | `Collines de Gardanne` fait exactement 20 caractères, la largeur du champ dBase `C(20)` — troncature ? | **Non tronqué, nom complet** | Attesté dans le PDF officiel journalier, en milieu de phrase, suivi d'une virgule, hors de toute contrainte de largeur. Question fermée |
| B-10 | Communes concernées, exigées par §5.2 et par la case 3 de l'issue | **Champ modélisé, valeur vide, lacune documentée et machine-lisible** | L'attribut n'existe nulle part dans la source. Le peupler exigerait un **second import open data** (IGN ADMIN EXPRESS), avec sa licence, sa propre attribution §9, son millésime, et un risque de recalage entre une couche DDTM de 2023 et une couche IGN plus récente — une deuxième issue déguisée en case à cocher. **Absent vaut mieux que faux.** Conséquence assumée : §5.2 n'est pas entièrement satisfait à la fin de #2 |
| B-11 | Budget §10 : brut ou après compression ? | **Budgété sur les octets bruts < 300 Ko** | Ni l'image Docker ni la configuration Apache Debian par défaut n'appliquent `AddOutputFilterByType DEFLATE application/json`, et je ne peux pas créer `data/.htaccess` (hors empreinte). Budgéter sur le gzip serait s'appuyer sur une compression non vérifiée ; elle reste une marge, pas une béquille. **Coût assumé, mesuré** : ce choix divise par ~6 la finesse géométrique atteignable — voir B-14 |
| B-14 | **Tolérance de simplification et zoom maximal** | **Douglas-Peucker, `interval=90 m`, précision 4 décimales** → 278 728 o bruts / 74 133 o gzip, 16 272 sommets (10,1 % des 160 602 d'origine), **écart max 94,55 m**. `zoom_max = 11` | Mesuré, pas postulé. Sous un budget **brut** de 300 Ko, c'est la seule famille de configurations qui passe : `interval=60 m` pèse déjà 379 940 o bruts. Le paramètre DP **est** la borne de déviation (94,55 m mesurés pour 90 m demandés), ce qui rend la garantie §4.1 directement opposable. Écart de surface global −0,07 %, pire massif 1,49 % (`collines-de-gardanne`), 47 anneaux supprimés valant 0,089 % de la surface totale. La topologie est préservée : mapshaper simplifie les 10 frontières partagées **une seule fois, en arcs partagés**, donc ni trou ni recouvrement entre massifs voisins. **Fidélité §4.1** : la carte préfectorale de référence est elle-même une carte à l'échelle du département ; à z10 l'écart vaut 0,85 px, il est donc invisible à l'échelle où l'information officielle est publiée. **Voie de relèvement documentée** : si `docker-cms` confirme la compression HTTP sur la cible, `interval=20 m` donne 196 905 o gzip (809 833 o bruts), écart 20 m, **sous-pixel à z12** — c'est un simple changement de paramètre du build, aucun code à retoucher |
| B-16 | **La jointure statut ↔ massif était vide en production** : statuts sous `131`…`1327`, référentiel sous `alpilles`… — intersection nulle | **Le référentiel possède et gèle la correspondance.** Trois fonctions : `massifs_code_depuis_source()`, `massifs_source_depuis_code()`, `massifs_correspondance_source()` | La correspondance fait partie de l'identité d'un massif, et le référentiel est le propriétaire de l'identité. **Vérifiée exhaustivement, pas échantillonnée** : les 25 paires ont été confrontées une par une à la table `<tr id='…'>` servie par `risque-prevention-incendie.fr/13`, diacritiques repliés des deux côtés — **25 correspondances, 0 divergence**. **Gelée, jamais calculée** : elle vaut aujourd'hui `'13' . gid`, mais `gid` est le rang alphabétique — insérer un massif renumérote 22 entités sur 25 en silence. Une correspondance dérivée d'un rang hériterait exactement du défaut qui nous a fait geler le slug (B-7). Le build **refuse d'émettre** si l'une des 25 manque, si deux massifs partagent un identifiant, ou si une valeur échoue `/^\d{3,4}$/`. `schema` passe de 1 à 2 |
| B-15 | **Le flux préfectoral porte 27 identifiants (`131`…`1327`) ; le référentiel DDTM n'en contient que 25** | **Le référentiel reste à 25. Aucun nom inventé pour combler l'écart.** `1326` / `1327` restent **sans correspondance** (B-16) : `massifs_code_depuis_source()` renvoie `null` | *Partiellement dépassé par B-16* : la correspondance des 25 est désormais détenue et gelée. Ce qui subsiste : | Constat croisé, à porter aux chaînes #1 et #3. La couche réglementaire `L_MASSIFS_FORESTIERS_S_013` — celle qui délimite les massifs de l'arrêté d'accès — contient exactement **25** entités, `gid` 1…25. Les identifiants préfectoraux sont manifestement `13` + rang alphabétique (`136` = `Chaîne des Côtes`, 6ᵉ ; `1310` = `Cote Bleue`, 10ᵉ ; `1325` = `Trevaresse`, 25ᵉ), donc `131`…`1325` recouvrent les 25 massifs nommés et **`1326` / `1327` sont en surnombre**. Deux pistes non tranchées, à ne pas deviner : la géométrie publiée par la préfecture porte une entité `Montagnette Partie Incendiée` absente de l'open data, et la couche sœur `L_MASSIFS_EXPOSES_FDF_S_013` ajoute `ZIP de Fos` et `Camargue` tout en scindant `Calanques`. **Conséquence contractuelle** : `massifs_massif_existe()` renvoie `false` pour `1326`/`1327` ; l'ingestion (#1) et le portail doivent traiter un code inconnu sans écrire, jamais en inventant un massif |
| B-12 | Extension de l'artefact géométrique | **`.json`, pas `.geojson`** | `.geojson` n'est pas garanti dans la table MIME d'un mutualisé ; servi en `application/octet-stream` il échapperait à toute règle de compression fondée sur le type MIME. Reste conforme à l'empreinte `massifs-13.*` |
| B-13 | Cron de surveillance de la source | **Aucun** | Source révisée en 2023, cadence ~2 ans. Un import automatique pourrait re-lier des identités en silence — exactement ce que B-7 interdit. Une **procédure de ré-import documentée** la remplace |

## Politique de ré-import — contraignante

**En une phrase : l'import peut mettre à jour une géométrie automatiquement ; il ne peut jamais créer,
supprimer, renommer ni re-lier une identité sans décision humaine.**

| Cas | Règle |
|---|---|
| `gid` **et** slug(`nom_massif`) correspondent | Même massif. Géométrie et `source.revision` mis à jour automatiquement |
| Slug seul correspond (`gid` a bougé) | Même massif. Dérive de `gid` journalisée, `code` inchangé |
| `gid` seul correspond (nom changé) | **Arrêt.** Renommage ou redécoupage : décision humaine, jamais de re-liaison automatique |
| Feature source sans correspondance | **Arrêt.** Nouveau `code` gelé après confirmation. Aide : recouvrement ≥ 80 % ⇒ probable renommage |
| Ligne existante sans correspondance source | **Jamais supprimée.** `retire_le` posé ; historique conservé (§4.2), URL non cassée |
| Scission / fusion | Détectées par recouvrement 1↔N. Aucune règle automatique. L'historique n'est **jamais** ré-attribué au travers d'une scission |

Procédure complète, cas par cas :
`wp-content/plugins/massifs-core/includes/domain/massifs/README.md`.
Registre d'identités gelées, en append-only, édité à la main :
`wp-content/plugins/massifs-core/includes/domain/massifs/build/identites.json`.
Le build **refuse d'émettre** si une feature source n'y a pas d'entrée.

## Dépendances hors empreinte — signalées, non traitées par cette chaîne

| # | Élément | Attendu | Destinataire |
|---|---|---|---|
| 1 | Chargeur de `massifs-core.php` | Découvrir `includes/domain/massifs/module.php`. Le module est inerte et sans hook : l'ordre de chargement est indifférent | chaîne #3 |
| 2 | Compression HTTP | `AddOutputFilterByType DEFLATE application/json` absent de l'image `wordpress:php8.3-apache` et du `Dockerfile`. Sans elle, la géométrie est transférée non compressée | `docker-cms` |
| 3 | `.gitignore` racine | Ligne 33 `package-lock.json` est un motif **non ancré** : il exclut le lockfile à toute profondeur. Contourné par un `.gitignore` local dans `build/`, mais la ligne racine mériterait d'être ancrée | hors chaîne |
| 4 | Communes concernées | Second import IGN ADMIN EXPRESS + jointure spatiale, avec sa propre attribution §9 | issue de suivi |
| 5 | Attribution §9 des périmètres | À afficher en pied de carte **et** en mentions légales via `massifs_attribution()` | chaîne front |
