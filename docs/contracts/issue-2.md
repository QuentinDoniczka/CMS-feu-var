# Contrat d'interface — Issue #2 — Référentiel des périmètres de massifs (Bouches-du-Rhône)

**Gelé le** 11 août 2026 par `lead-issue-cms` (chaîne #2) · **Statut** : contraignant.
**Amendé le 13 août 2026 par `lead-issue-cms` (chaîne #20)** — voir [`issue-20.md`](issue-20.md).
Amendements : prose de `massifs_emprise()` (résolutions désormais calculées), interdits 4 et 14,
arbitrages B-11 et B-14 (**valeurs réalignées sur la mesure**), nouveaux arbitrages **B-17**
(reproductibilité) et **B-18** (artefact de recette hors de l'accès public), table des dépendances hors
empreinte. **Aucune signature, aucune route, aucune clé de réponse ne change** : `zoom_max` reste `11`,
`massifs_geometrie()` et `massifs_emprise()` gardent leur forme exacte, et **le thème n'a rien à changer**
— seules les *valeurs* de `geometrie.{version, sha256, octets}` bougent, ce qui est précisément ce que le
jeton de cache-busting existe pour absorber.

**Erratum du 4 septembre 2026 (issue #93)** — la **dépendance hors empreinte n° 3** est close. Son texte
était **exact au gel** et a été **périmé** par deux commits ultérieurs ; il reste lisible, barré, avec le
verdict à sa suite. **Aucune décision n'est rouverte** : aucune clause, aucun invariant, aucun arbitrage,
aucune signature, aucune clé ne bouge, et aucun consommateur n'a rien à changer.

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
géométrie simplifiée est de **93,62 m**, soit **0,844 px à z10 et 1,688 px à z11**. Résolutions
**calculées, non saisies à la main** — `( 111320 · cos 43,5° · 360 ) / ( 256 · 2^z )` — : **110,89 m/px
à z10, 55,45 à z11, 27,72 à z12, 13,86 à z13**, à la latitude 43,5°. Le front ne propose pas de zoom
supérieur sur la couche massifs. `zoom_min` est une décision front. Voir B-14.

> **Réaligné le 13 août 2026 par l'issue #20.** Les valeurs publiées avant #20 (94,55 m ; 0,85 px à z10 ;
> 1,71 px à z11 ; 111,8 m/px à z10 ; 14,0 m/px à z13) provenaient de la géométrie pré-verrouillage et
> d'une table de résolutions saisie à la main : z11 et z12 étaient justes, **z10 était faux de 0,81 % et
> z13 de 1,00 %**. La table est désormais calculée par le build à partir des constantes de projection
> qui servent à mesurer l'écart, de sorte qu'elle ne peut plus diverger de la mesure qu'elle qualifie.

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
4. Lire ou dépendre de `includes/domain/massifs/build/massifs-13.fidelite.json` — artefact de recette,
   hors contrat. **Déplacé de `data/` vers `build/` le 13 août 2026 par l'issue #20** : il n'est plus
   atteignable en HTTP (403 mesuré, par le `.htaccess` du module, doublé par
   `docker/wordpress/plugins-guard.conf`). L'invariant est désormais structurel — **`data/` = servi au
   navigateur ; `build/` = jamais servi** — donc cet interdit est vrai par construction, plus par la prose.
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
    **Étendu le 13 août 2026 par l'issue #20 : aucune preuve générée ne se maintient à la main.**
    `build/massifs-13.fidelite.json` et `build/reference.json` sont émis par `npm run importer`, dans la
    même écriture atomique que les artefacts qu'ils décrivent. Constat qui motive l'extension : le
    `fidelite.json` maintenu à la main d'avant #20 consignait **9 contrôles là où le build en émet 13** —
    les cinq contrôles de correspondance préfectorale (B-16) manquaient, `seuils` était dépourvu de
    `identifiant_prefecture_regex`, et `source.file` désignait
    `dataset/scout/massifs_forestiers_13_wgs84.geojson`, **chemin absent du dépôt et de tout son
    historique**. Une preuve que personne ne peut rejouer n'est pas une preuve.
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
| B-11 | Budget §10 : brut ou après compression ? | **Budgété sur les octets bruts < 300 Ko — décision maintenue, motif réécrit le 13 août 2026 par l'issue #20** | Le motif d'origine (« compression non vérifiée sur la cible ») **est tombé** : `docker/wordpress/deflate.conf` applique bien `AddOutputFilterByType DEFLATE application/json`, et la géométrie est servie en `Content-Encoding: gzip` avec `Vary: Accept-Encoding` — **278 894 o bruts → 74 023 o transférés**, mesurés par `tests/verifier-http.sh` sur la stack Docker le 13 août 2026 ; `zlib.gzipSync` au build donne **73 737 o**. **Deux méthodes, deux nombres, jamais l'un présenté pour l'autre.** La décision ne change pas pour autant : la compression **reste une marge, pas une béquille** — la production o2switch n'est pas mesurée, et le §10 du brief ne dit pas si « géométries < 300 Ko » porte sur le brut ou sur le transféré (**ambiguïté consignée, non tranchée** : voir B-14, dernier paragraphe). Budgéter sur le transféré reviendrait à faire dépendre le respect d'un budget produit d'une configuration serveur qu'aucune ligne de notre code ne garantit chez l'hébergeur. La dépendance hors empreinte n° 2 est **satisfaite** |
| B-14 | **Tolérance de simplification et zoom maximal** | **Douglas-Peucker, `interval=90 m`, précision 4 décimales** → **278 894 o bruts / 73 737 o gzip au build (74 023 o transférés mesurés), 16 282 sommets (10,139 % des 160 594 d'origine), écart max 93,62 m**. `zoom_max = 11`, **inchangé** | Mesuré, pas postulé. Sous un budget **brut** de 300 Ko (B-11), c'est la seule famille de configurations qui passe. Le paramètre DP **est** la borne de déviation (93,62 m mesurés pour 90 m demandés), ce qui rend la garantie §4.1 directement opposable. Écart de surface global **−0,0788 %**, pire massif **1,4418 %** (`collines-de-gardanne`), **45 anneaux supprimés valant 0,0859 %** de la surface totale, sous-pixel jusqu'à **z10 inclus**. La topologie est préservée : mapshaper simplifie les 10 frontières partagées **une seule fois, en arcs partagés**, donc ni trou ni recouvrement entre massifs voisins. **Fidélité §4.1** : la carte préfectorale de référence est elle-même une carte à l'échelle du département ; à z10 l'écart vaut 0,844 px, il est donc invisible à l'échelle où l'information officielle est publiée.<br><br>**Réaligné le 13 août 2026 par l'issue #20, sur la mesure et non l'inverse.** Le déplacement se décompose en **deux effets distincts, à ne jamais confondre** — le second n'est pas une dérive.<br>**(a) La géométrie a changé** : 278 728 → 278 894 o, 16 272 → 16 282 sommets. Cause isolée et vérifiée : l'import de la chaîne #2 tournait **sans fichier de verrouillage**, donc sur des dépendances transitives de mapshaper re-résolues à chaque installation. La source archivée est **rigoureusement identique** (`sha256 d0316cbc…`, 3 022 441 o) : mêmes octets d'entrée, sortie différente — l'outillage est la seule variable, et c'est exactement le défaut §11 que #20 ferme (voir **B-17**).<br>**(b) L'écart maximal se lit contre trois références**, dont deux ne diffèrent que par la source de mesure : **94,55 m** = ancienne géométrie mesurée contre la **source pleine précision** (3 664 738 o, **absente du dépôt et de tout son historique — cette valeur n'est plus recalculable par personne**) ; **94,31 m** = **la même ancienne géométrie, au même octet**, mesurée contre la **source archivée arrondie à 5 décimales**, seule rejouable — vérifié mécaniquement en re-mesurant la géométrie de `HEAD` avec le code actuel, qui redonne 94,31 m, donc **le code de mesure n'est pas la variable** ; **93,62 m** = la géométrie régénérée, contre cette même archive. L'existence de l'écart (a) explique 94,31 → 93,62 ; l'écart 94,55 → 94,31 n'est **pas** une dérive mais un changement de référentiel de mesure, que la chaîne #2 avait elle-même anticipé en posant `TOLERANCES.ecart_m = 2` avec le commentaire « la source archivée est arrondie à 5 décimales ».<br><br>**`interval = 20 m` reste écarté**, et l'absence de compression n'est plus l'une des raisons — elle est confirmée (B-11). Les trois raisons qui subsistent, consignées ici pour qu'aucune revue n'ait à les redécouvrir : **(i)** l'interdit 12 plafonne la couche massifs à z11, donc une finesse sous-pixel à z12 est **invisible** ; **(ii)** re-mesuré le 13 août 2026 : **809 966 o bruts** (2,7× le budget brut) / 196 651 o gzip, et ~2,9× de sommets à décompresser, parser et rasteriser sur mobile, contre les 2,5 s du §10 — on paierait un coût réel pour un gain nul ; **(iii)** le consommateur, la carte, n'est pas écrit. **Relever `zoom_max` à 12 est une modification de contrat qui touche le front**, hors périmètre de #20 : à instruire par la chaîne carte, et seulement après avoir tranché l'ambiguïté §10 « brut ou transféré ? » (B-11). *Honnêteté de mesure : `interval=60 m → 379 940 o bruts`, cité en 2026-08-11, n'a pas été re-mesuré sous verrouillage ; ce chiffre reste indicatif.* |
| B-16 | **La jointure statut ↔ massif était vide en production** : statuts sous `131`…`1327`, référentiel sous `alpilles`… — intersection nulle | **Le référentiel possède et gèle la correspondance.** Trois fonctions : `massifs_code_depuis_source()`, `massifs_source_depuis_code()`, `massifs_correspondance_source()` | La correspondance fait partie de l'identité d'un massif, et le référentiel est le propriétaire de l'identité. **Vérifiée exhaustivement, pas échantillonnée** : les 25 paires ont été confrontées une par une à la table `<tr id='…'>` servie par `risque-prevention-incendie.fr/13`, diacritiques repliés des deux côtés — **25 correspondances, 0 divergence**. **Gelée, jamais calculée** : elle vaut aujourd'hui `'13' . gid`, mais `gid` est le rang alphabétique — insérer un massif renumérote 22 entités sur 25 en silence. Une correspondance dérivée d'un rang hériterait exactement du défaut qui nous a fait geler le slug (B-7). Le build **refuse d'émettre** si l'une des 25 manque, si deux massifs partagent un identifiant, ou si une valeur échoue `/^\d{3,4}$/`. `schema` passe de 1 à 2 |
| B-15 | **Le flux préfectoral porte 27 identifiants (`131`…`1327`) ; le référentiel DDTM n'en contient que 25** | **Le référentiel reste à 25. Aucun nom inventé pour combler l'écart.** `1326` / `1327` restent **sans correspondance** (B-16) : `massifs_code_depuis_source()` renvoie `null` | *Partiellement dépassé par B-16* : la correspondance des 25 est désormais détenue et gelée. Ce qui subsiste : | Constat croisé, à porter aux chaînes #1 et #3. La couche réglementaire `L_MASSIFS_FORESTIERS_S_013` — celle qui délimite les massifs de l'arrêté d'accès — contient exactement **25** entités, `gid` 1…25. Les identifiants préfectoraux sont manifestement `13` + rang alphabétique (`136` = `Chaîne des Côtes`, 6ᵉ ; `1310` = `Cote Bleue`, 10ᵉ ; `1325` = `Trevaresse`, 25ᵉ), donc `131`…`1325` recouvrent les 25 massifs nommés et **`1326` / `1327` sont en surnombre**. Deux pistes non tranchées, à ne pas deviner : la géométrie publiée par la préfecture porte une entité `Montagnette Partie Incendiée` absente de l'open data, et la couche sœur `L_MASSIFS_EXPOSES_FDF_S_013` ajoute `ZIP de Fos` et `Camargue` tout en scindant `Calanques`. **Conséquence contractuelle** : `massifs_massif_existe()` renvoie `false` pour `1326`/`1327` ; l'ingestion (#1) et le portail doivent traiter un code inconnu sans écrire, jamais en inventant un massif |
| B-12 | Extension de l'artefact géométrique | **`.json`, pas `.geojson`** | `.geojson` n'est pas garanti dans la table MIME d'un mutualisé ; servi en `application/octet-stream` il échapperait à toute règle de compression fondée sur le type MIME. Reste conforme à l'empreinte `massifs-13.*` |
| B-13 | Cron de surveillance de la source | **Aucun** | Source révisée en 2023, cadence ~2 ans. Un import automatique pourrait re-lier des identités en silence — exactement ce que B-7 interdit. Une **procédure de ré-import documentée** la remplace |
| B-17 | **Reproductibilité de l'import** — ajouté le 13 août 2026 par l'issue #20 | **La géométrie publiée est reproductible à l'octet ; les métadonnées portent un horodatage d'import assumé.** Six mesures, détaillées ci-contre | **1. `build/package-lock.json` est commité**, et la procédure est `npm ci` partout. `package.json` épinglait déjà mapshaper en version exacte, mais ses dépendances transitives étaient re-résolues à chaque installation — et le `npm ci` que le code documentait **échouait faute de verrou** : la procédure d'installation du §11 était littéralement inexécutable. **2. Node est épinglé en majeure** (`build/.nvmrc` = `24`, plus un `engines` **indicatif**). Pas d'`engine-strict` : un blocage dur empêcherait de rejouer la recette sur un Node plus récent alors même que les octets concordent. **3. `data/.gitattributes` et `build/.gitattributes` marquent `-text`** les artefacts dont l'empreinte est contractuelle. **`-text` et non `binary`** (`binary` implique `-diff`, or la relecture du diff de `massifs-13.php` est un contrôle d'identité imposé par le README), **et non `text eol=lf`** (qui absorberait la conversion à la validation, désolidarisant les octets du disque — ceux dont on calcule le sha256 — de ceux du blob). **Panne réelle fermée** : sans attribut, un clone sous `core.autocrlf=true` convertissait les 26 LF de la géométrie en CRLF, soit +26 o, rendant **faux** les `sha256` / `version` / `octets` exposés par `massifs_geometrie()`. **4. `build/reference.json` porte l'empreinte de référence** (sha256 + octets de la source archivée ; sha256 + version + octets + sommets + écart max de la géométrie ; version mapshaper résolue et majeure Node). **Émis par l'import, jamais édité à la main** — le tenir à la main reproduirait le défaut que #20 ferme. `npm run verifier` le compare et **sort en code ≠ 0**. C'est ce qui transforme une dérive silencieuse de +10 sommets en échec bruyant, y compris pour des causes non anticipées. Le majeur de Node y est un **avertissement**, pas un échec : un faux positif répété entraînerait à régénérer la référence par réflexe, ce qui détruirait le dispositif. Aucune taille gzip n'y figure — la sortie de zlib varie avec sa version et créerait une dérive fantôme. **5. `genere_le` n'est pas rendu déterministe.** Le dériver de la révision source énoncerait une **fausse date de génération**, dans un projet dont la règle cardinale est de ne jamais présenter une date pour une autre. L'identité binaire se **prouve à la demande** par `MASSIFS_GENERE_LE=<ISO 8601>`, réservé à la recette et jamais employé pour produire les artefacts commités. **6. La version de mapshaper est lue** dans son manifeste, plus codée en dur : avant #20 l'artefact de recette pouvait revendiquer une version d'outil qui n'était pas celle employée |
| B-18 | **L'artefact de recette était récupérable en HTTP** (`data/massifs-13.fidelite.json`, 16 429 o) alors que l'interdit 4 le déclare hors contrat | **Protégé par relocation, pas par une règle** : déplacé dans `includes/domain/massifs/build/`, où le `.htaccess` du module renvoie déjà 403. **En défense en profondeur**, `docker/wordpress/plugins-guard.conf` nie désormais les sous-arbres `includes/`, `build/`, `node_modules/` à toute profondeur sous `wp-content/(plugins\|themes)/`, au-delà des seuls `.php` | Le fichier ne porte **ni secret ni donnée personnelle** : c'est de l'hygiène de surface, et surtout la mise en conformité **par construction** de l'interdit 4 — un fichier servi publiquement et déclaré « hors contrat » est une invitation à en dépendre. La relocation était préférable à une règle Apache nouvelle : le `.htaccess` du module a une **efficacité prouvée** (`tests/verifier-http.sh` obtient 403 sur `build/identites.json`, qu'aucune règle Docker ne couvrait), il est **livré par l'extension** donc il suit sur un mutualisé o2switch, et il restaure l'invariant structurel **`data/` = servi ; `build/` = jamais servi**. Le durcissement Apache le double parce qu'un `.htaccess` est **inerte si `AllowOverride` est désactivé`**. **Épargnés délibérément, et c'est contraignant pour les chaînes à venir** : `assets/` du thème — dont le futur `assets/vendor/` qui portera Leaflet vendorisé (§3) — et `data/` de l'extension, qui sert la géométrie et servira les caches navigateur des chaînes #4+ (météo, EFFIS, tuiles). La règle vise des **sous-arbres**, jamais une liste noire de noms : une liste noire n'aurait protégé que le fichier connu, et aurait été un piège pour la chaîne suivante. **Vérifié en HTTP sur la stack** : géométrie 200, artefact de recette 403, `reference.json` 403, `build/source/` 403, `includes/ingest/prefecture/README.md` 403 (nouveau), `style.css` / `tokens.css` / `.woff2` du thème 200, `/`, `/wp-login.php`, `/wp-json/`, `/tiles/…` 200. **Point de vigilance légué** : un futur `assets/vendor/<paquet>/build/` serait refusé — la chaîne qui vendorisera Leaflet doit le vérifier à ce moment-là |

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
| 2 | Compression HTTP | ~~`AddOutputFilterByType DEFLATE application/json` absent de l'image `wordpress:php8.3-apache` et du `Dockerfile`~~ — **SATISFAITE** (`docker/wordpress/deflate.conf`). Mesuré le 13 août 2026 : 278 894 o → 74 023 o transférés, `Content-Encoding: gzip`, `Vary: Accept-Encoding`. Voir B-11 : cela ne change pas le budget, qui reste exprimé en octets bruts | ~~`docker-cms`~~ — clos par #20 |
| 3 | `.gitignore` racine | ~~Ligne 33 `package-lock.json` est un motif **non ancré** : il exclut le lockfile à toute profondeur. **Toujours ouverte.** Neutralisée pour nous — `build/.gitignore` l. 8 réintègre explicitement le verrou, et `git check-ignore -v` le confirme — mais **la prochaine chaîne qui créera un lockfile ailleurs devra refaire la négation** ou le perdra en silence~~ — **CLOSE** (erratum du 4 septembre 2026, issue #93). Le texte barré était **exact au gel** : il a été **périmé** par deux commits ultérieurs. `09fd99d` (#33) a ancré le motif à la racine sous la forme `/package-lock.json` ; `c881cdf` (#77) a retiré la négation `!package-lock.json` des deux `build/.gitignore`. **Aucune chaîne future n'a de négation à refaire — la prescription barrée ne doit pas être suivie.** Erratum jumeau à la couture hors empreinte C-8 de [`issue-9.md`](issue-9.md). | ~~hors chaîne~~ — clos par #93 |
| 4 | Fins de ligne du reste du dépôt | Les `.conf` et `.sh` de `docker/` restent sous le régime `core.autocrlf` (git avertit « LF will be replaced by CRLF »). Défaut **pré-existant et sans conséquence connue** — Apache tolère CRLF et la stack fonctionne. Le remède général serait un `.gitattributes` racine (`* text=auto eol=lf`), qui fermerait cette classe de panne pour tout le dépôt et pas seulement pour les trois artefacts empreintés de #20. Hors empreinte de #20 | hors chaîne |
| 5 | `tests/verifier-http.sh` | Ajouter `403` sur `build/massifs-13.fidelite.json` et `build/reference.json` : même `DirectoryMatch` que les gardes déjà assérés, donc même résultat, mais **la non-exposition de l'artefact de recette est l'objet même de #20** et mérite d'être épinglée. Ajouter aussi deux `200` sur `themes/massifs/assets/css/tokens.css` et une police `.woff2`, qui verrouilleraient l'épargne d'`assets/` face au nouveau `DirectoryMatch`. Aucune assertion existante ne casse : rien dans `tests/` ne référence `fidelite` | `test-integration-cms` (niveau lot) |
| 6 | `docker/README.md` — section « Garde-fou » | Le paragraphe compression et la description du garde-fou ont été réalignés par #20. Le reste du document n'a pas été relu | `docker-cms` |
| 7 | Communes concernées | Second import IGN ADMIN EXPRESS + jointure spatiale, avec sa propre attribution §9 | issue de suivi |
| 8 | Attribution §9 des périmètres | À afficher en pied de carte **et** en mentions légales via `massifs_attribution()` | chaîne front |

**Règle que la dépendance hors empreinte n° 3 laisse au dépôt** — `.gitignore` ne gouverne que les
fichiers **non suivis** : une négation locale posée sur un lockfile déjà commité ne protège rien, et
c'est le motif du retrait opéré par `c881cdf` (#77). Un lockfile créé plus tard sera, lui, non suivi à sa
naissance ; ce qui décidera de son sort est la section « Dépendances » du `.gitignore` racine, qui fait
autorité et dont aucun contrat ne transcrit l'état. Aucun numéro de ligne n'est réintroduit ci-dessus :
c'est le numéro, et non le fait, qui avait péri.
