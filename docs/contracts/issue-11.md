# Contrat d'interface — Issue #11 — Afficher les zones parcourues par le feu (EFFIS/Copernicus) en couche surfacique

**Gelé le** 14 août 2026 · **Par** `lead-issue-cms`, chaîne #11
**Lignes de DoD servies** : §12 du brief (couche EFFIS, nominal + indisponibilité) · §4.4 (Zones parcourues
par le feu, en entier) · et, par conséquence directe, §5.3 (équivalent textuel) et §9 (attribution).
**Statut** : contraignant. Les deux plans amont — `leaddev-back-cms` et `leaddev-front-cms` — ont été
produits en aveugle l'un de l'autre. Ce document est le point de réconciliation ; en cas de divergence
entre un plan et ce contrat, **c'est ce contrat qui fait foi**.

> Règle de lecture reprise de `MASTER.md` : ce document décrit des **décisions**, pas des suggestions.
> Une divergence constatée en revue est un défaut, pas une variante. Les blocs marqués **`OUVERT`** sont
> des trous de connaissance assumés — on ne les comble jamais par déduction (§4.2 du brief).

---

## 0. Approche retenue, et ce que cette chaîne ne livre pas

**« Le module d'ingestion complet, servi par une route de même origine ; la couche cartographique
escaladée, jamais contournée. »**

1. **Connecteur simulé**, arrêté par `docs/decisions/portee-non-publiee.md` §4, qui se déclare opposable
   à l'issue #11 par son numéro. Ce contrat **enregistre** cette décision, il ne la reprend pas.
2. **La simulation est une origine, jamais une branche.** Le `Validator` est traversé dans tous les modes.
3. **Le nominal simulé est valide et vide.** Aucun polygone fictif n'est dessiné sur un vrai massif.
4. **Les octets vivent dans une option et sont servis par une route REST de même origine.** Aucun
   fichier de cache, aucune écriture hors empreinte.
5. **La couche cartographique n'est pas livrée** — elle est mécaniquement inatteignable depuis
   l'empreinte, et c'est démontré au §9, fichier et ligne à l'appui.

### 0.1 Ce que cette chaîne écrit — l'empreinte, et rien d'autre

| Chemin | Propriétaire | Nature |
|---|---|---|
| `wp-content/plugins/massifs-core/includes/ingest/effis/**` | `dev-back-cms` | neuf |
| `wp-content/themes/massifs/templates/parts/panneau-feu.php` | `dev-front-cms` | neuf |
| `tests/scenarios/40-*.php` … `49-*.php` | `dev-back-cms` | neuf, **plage 40–49 exclusivement** |
| `docs/contracts/issue-11.md` | `lead-issue-cms` | ce document |

**Aucune autre écriture, sous aucun prétexte.** Arbre de travail unique, mono-branche, deux chaînes
sœurs actives (#10, #12) : la disjonction des empreintes est la seule protection contre l'écrasement
mutuel, et il n'existe aucune branche pour récupérer.

### 0.2 Ce que cette chaîne n'écrit pas, et pourquoi — décision, pas omission

**`wp-content/themes/massifs/assets/js/carte/couche-effis.js` n'est pas écrit.** Le fichier est nommé
par l'empreinte de l'issue ; il est néanmoins **impossible à écrire correctement**, pour trois raisons
cumulatives dont chacune suffirait :

1. **Aucune poignée sur la carte.** `assets/js/carte/carte.js` ouvre une IIFE l. 26, déclare `var carte;`
   l. 158, l'affecte l. 168 par `L.map( toile, … )` et **n'expose rien** — zéro `window.*`, zéro
   `CustomEvent`, zéro `dispatchEvent` (vérifié par `grep`, les deux seules occurrences de `window` sont
   `window.location` l. 246 et 250, pour le contrôle d'origine). Leaflet ne tient aucun registre global
   d'instances.
2. **Aucun chemin d'enfilage.** `functions.php` est hors empreinte. L'enfilage depuis l'extension
   exigerait `get_theme_file_uri()`, interdit par la frontière stricte du `CLAUDE.md` et par l'interdit 12
   du contrat #9 — **et serait de toute façon sans effet**, faute de poignée.
3. **Aucun pigment.** `assets/css/tokens.css` est **gelé, sha256 épinglé, 111 propriétés** (invariant du
   contrat #4) et ne porte aucun jeton EFFIS ; la table de classes du contrat #7 §8.2 est **déclarée
   fermée** et ne porte aucune `.carte__feu*`. Un `L.geoJSON` monté sans classe peinte rendrait le bleu
   par défaut de Leaflet — une couleur d'interface inventée, violant §2.1 et §4.1.d de `MASTER.md`.

**Livrer ce fichier reviendrait donc à livrer du code qui, s'il était un jour activé, violerait le
design system.** Il n'est pas écrit. La couche est escaladée au §9, avec ses coutures nommées à la ligne.

---

## 1. Fonctions de lecture exposées par l'extension

Deux fonctions publiques, et deux seulement. Toutes préfixées `massifs_`, toutes **totales** — aucune
exception, aucun `WP_Error`, aucun `null` —, rendant un tableau associatif **brut et non échappé** dont
**toutes les clés sont toujours présentes** : le thème n'écrit jamais `isset()`.

Implémentation namespacée sous `Massifs\Ingest\Effis\` ; **seule la surface `massifs_*()` est publique**,
chaque fonction gardée par `function_exists()` dans `compat.php`, sur le patron de
`includes/ingest/tuiles/compat.php`.

### 1.1 `massifs_zones_parcourues_par_le_feu(): array`

**Aucun argument.** Volontairement pas de paramètre `$jour` : cette couche est une **fenêtre glissante**,
pas un statut daté. Un accesseur indexé par date laisserait croire qu'on peut servir une fenêtre passée —
on ne le peut pas, et on ne le doit pas.

```php
[
  'etat'                => 'aucune_zone', // string — énumération FERMÉE à trois valeurs, §3
  'zones'               => [],            // list<array> — voir 1.1.a
  'nombre'              => 0,             // int — cardinal de `zones`. JAMAIS un discriminant, §3.1
  'releve_le'           => '2026-08-14T05:12:03Z', // string ISO 8601 UTC ; '' SSI couche indisponible
  'expire_le'           => '2026-08-15T05:12:03Z', // string ISO 8601 UTC ; '' SSI couche indisponible
  'peremption_secondes' => 86400,         // int — T, publié pour être vérifiable en recette
  'fenetre_jours'       => 7,             // int — fenêtre glissante de la couche source
  'surface_minimale_ha' => 30,            // int — seuil de détection annoncé au §4.4 du brief
]
```

#### 1.1.a Une entrée de `zones`

```php
[
  'id'                   => 'zpf-2026-0142', // string — opaque, stable dans un relevé
  'surface_texte'        => '42 ha',         // string — DÉJÀ FORMATÉE, unité comprise, espace
                                             //   INSÉCABLE U+00A0 avant l'unité ; '' si inconnue
  'surface_ha'           => 42.0,            // float — fait brut. JAMAIS LU PAR LE THÈME (§5, interdit 4)
  'premiere_observation' => '',              // string ISO 8601 UTC complet ; '' si inconnue
  'derniere_observation' => '',              // string ISO 8601 UTC complet ; '' si inconnue
  'commune_la_plus_proche' => '',            // string — TOUJOURS '' à ce jour, §7 A-8
  'geometrie'            => [ 'type' => 'Polygon', 'coordinates' => [] ],
                                             // array GeoJSON, EPSG:4326 [lon, lat].
                                             // JAMAIS LUE PAR LE THÈME (§5, interdit 5)
]
```

`surface_ha` et `geometrie` sont **présentes et jamais lues par le thème**. C'est l'idiome déjà en
service au contrat #7 §3.1 pour `auteur_id` et `statut_id` : une clé de transport se déclare, elle ne se
supprime pas — la route REST du §2 et la future chaîne cartographique les consomment.

### 1.2 `massifs_attribution_zones_parcourues_par_le_feu(): array`

```php
[
  'phrase' => '© Union européenne, Copernicus Emergency Management Service / EFFIS',
  'faits'  => [
      'producteur'          => 'Copernicus Emergency Management Service',
      'service'             => 'European Forest Fire Information System (EFFIS)',
      'couche'              => '',   // '' — nom de couche source jamais relevé, §8
      'methode'             => 'estimation satellite',
      'fenetre_jours'       => '7',
      'surface_minimale_ha' => '30',
      'frequence_par_jour'  => '2',
      'connecteur'          => 'simule',  // 'simule' | 'reel'
  ],
]
```

**Pas de clé `lien_licence`.** Le §9 du brief impose la phrase EFFIS **sans URL**, contrairement à OSM.
Geler une chaîne vide dans un contrat est ce que l'arbitrage A-5 du contrat #9 a écarté pour
`lien_source`. Conséquence liante pour le thème : **l'attribution EFFIS est rendue en texte nu, jamais
dans un `<a>`** — il n'y a aucune destination à décrire.

**Interdit de découpe**, repris mot pour mot du contrat #9 §1.3 : la phrase se rend **entière**. Jamais
abrégée, jamais reformulée, jamais coupée en « Copernicus / EFFIS », jamais complétée.

**`faits` est conservée avec justification**, comme l'exige le précédent #9 §1.3 : la page « La démarche »
(§5.1 et §9 du brief) doit documenter sources et limites, et la faire rouvrir ce contrat pour obtenir des
faits que le module connaît déjà serait un coût inutile. `connecteur` rend la portée simulée **auditable
en production**. Aucun autre consommateur n'est autorisé à s'y ajouter sans révision de ce contrat.

### 1.3 Fonctions explicitement NON créées

`massifs_couche_effis_etat()`, `massifs_zones_parcourues_disponibles()`, `massifs_effis_fraicheur()` —
**écartées, aucun consommateur** : `etat`, `nombre` et `releve_le` sont déjà des clés du retour unique.
Précédent contrat #9 §1.4 : « une seconde manière de poser la même question est une divergence en
attente ».

**Aucun accesseur « dernier relevé », à perpétuité.** Discipline recopiée de
`includes/ingest/prefecture/class-connector.php` l. 25-35. Le **seul** chemin vers les octets traverse la
garde de péremption du §4 : il n'existe aucune fonction capable de rendre un polygone sans l'avoir
franchie. La règle est tenue **par l'absence de chemin**, pas par une garde qu'on pourrait oublier.

---

## 2. Route REST

`GET /wp-json/massifs/v1/zones-parcourues-par-le-feu`

| | |
|---|---|
| **Méthode** | `WP_REST_Server::READABLE` uniquement. **Aucune route d'écriture n'est déclarée dans ce module** — §6 du brief est tenu par construction, pas par une garde. |
| **`permission_callback`** | `'__return_true'`, **écrit explicitement**, jamais absent. Idiome en service à `includes/rest/public/route-statuts.php` l. 37-44. |
| **`args`** | **aucun**. Pas de `jour` (voir §1.1), pas de `bbox` (le filtre départemental est notre décision, pas celle du client), pas de `format`. Toute surface de paramètre est une surface d'attaque et une divergence de cache pour un gain nul. |
| **Codes** | **`200` dans tous les états de la donnée.** Aucun `503` : « la couche est indisponible » est un état légitime et attendu, pas une panne serveur — un `503` enverrait le client dans une branche d'erreur, où la tentation est le retry, le repli, ou un cache tiers. `304` sur `If-None-Match` concordant. |
| **Cache** | `Cache-Control: no-cache` + **ETag faible** sur la charge complète, exactement l'idiome de `includes/rest/public/reponse.php` l. 27-36, dont le raisonnement s'applique mot pour mot : un `max-age` posé peu avant l'expiration servirait des octets périmés le temps de sa durée — la règle de sécurité par la porte de derrière. |

Réponse, forme exacte :

```json
{
  "etat": "aucune_zone",
  "releve_le": "2026-08-14T05:12:03Z",
  "expire_le": "2026-08-15T05:12:03Z",
  "fenetre_jours": 7,
  "surface_minimale_ha": 30,
  "nombre": 0,
  "attribution": "© Union européenne, Copernicus Emergency Management Service / EFFIS",
  "zones": { "type": "FeatureCollection", "features": [] }
}
```

`zones` est un **FeatureCollection GeoJSON valide**, directement consommable par `L.geoJSON`. Chaque
`Feature` porte en `properties` : `id`, `surface_texte`, `surface_ha`, `premiere_observation`,
`derniere_observation`, `commune_la_plus_proche`. Quand `etat === 'couche_effis_indisponible'` :
`releve_le` et `expire_le` valent `""`, `attribution` vaut `""`, `nombre` vaut `0`, `features` vaut `[]`.

**Pourquoi cette route est conservée bien qu'aucun gabarit livré ne la consomme** — la question a été
posée par le plan back, et elle est légitime au titre du §1.4 du contrat #9. Elle est tranchée en **A-5**.

---

## 3. États spéciaux

Énumération **fermée à trois valeurs**. Toute quatrième valeur est un **acte de contrat**, jamais une
surprise d'exécution.

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `zones_disponibles` | Relevé **validé**, âge ≤ T, **≥ 1** zone après filtre départemental | `h2` + liste des zones + fraîcheur + phrase de limites §11.3 + attribution |
| `aucune_zone` | Relevé **validé**, âge ≤ T, **0** zone après filtre | `h2` + « Aucune zone parcourue par le feu détectée. » + fraîcheur + **phrase de limites §11.3** + attribution |
| `couche_effis_indisponible` | Aucun relevé validé **jamais** · ou âge > T · ou connecteur désarmé · ou dernier relevé rejeté sans relevé valide dans T | `h2` + « Donnée momentanément indisponible. » · **ni fraîcheur, ni limites, ni attribution** |
| `information_indisponible` · `hors_saison` · `non_encore_publie` · `donnee_perimee` | Contrats #3 / #5, inchangés | **Aucun effet sur cette issue, par construction** — voir §4, invariant I-11.5 |

### 3.1 La règle centrale de l'issue — à ne jamais affaiblir

> **Le test discriminant est `etat`. Jamais `nombre`. Jamais `count( $zones )`.**

`aucune_zone` et `couche_effis_indisponible` portent **tous deux** `nombre === 0`. Ce qui les sépare est
**`releve_le`** : renseigné dans le premier, `''` dans le second. « Vide parce que mesuré » porte une date
de mesure ; « vide parce que muet » n'en porte aucune.

Un consommateur qui teste `count( $zones ) === 0` pour décider quoi afficher **produit le défaut de
sécurité de cette issue** : il écrit « aucune zone parcourue par le feu » alors que la vérité est « nous ne
savons pas ». C'est un **faux négatif sur une donnée de sécurité** — le mode de défaillance du §4.2 du
brief, atteint par la route inverse.

### 3.2 Deux états qui n'existent pas dans ce module, et pourquoi

**`hors_saison` n'existe pas.** La couche est servie **toute l'année**. Elle n'est pas un statut, ne dépend
d'aucun régime préfectoral, et les feux ne s'arrêtent pas le 30 septembre ; le §4.4 du brief ne lui attache
aucune saisonnalité. Le module **n'appelle jamais** `massifs_saison()` et ne charge jamais `Saison.php` :
la propriété est tenue **par l'absence de couplage**, donc vérifiable par `grep` — `saison` doit rendre
zéro sur `includes/ingest/effis/**`, `README.md` excepté.

**`donnee_perimee` n'existe pas, et il n'y a aucune clé `perimee`.** C'est un arbitrage, tranché en **A-4**.

---

## 4. Invariants — vérifiables en revue

| # | Invariant | Ce que sa violation casse |
|---|---|---|
| **I-11.1** | **La simulation est une ORIGINE, jamais une BRANCHE.** Aucun `if ( $simule ) { return $fixture; }`, aucun `file_get_contents()` de fixture, nulle part dans le module. La charge simulée traverse `Fetcher` puis les cinq couches de `Validator`, exactement comme la charge réelle | `portee-non-publiee.md` §2. Une branche ferait **contourner le `Validator` précisément dans le mode où l'on tourne réellement**, et la ligne de DoD §12 serait prouvée contre un chemin de code qui n'existe pas en production |
| **I-11.2** | **Un seul fichier du module touche le réseau** : `class-fetcher.php`. Un `grep` de `wp_remote_`, `curl_`, `file_get_contents(` sur `includes/ingest/effis/**` ne doit rendre **aucune** ligne hors de ce fichier | Contrainte #2, et la traçabilité de I-11.1 |
| **I-11.3** | **Le nominal simulé est un `FeatureCollection` valide et VIDE.** Aucun polygone non vide n'existe hors des fixtures de `tests/scenarios/4x` | Un polygone de zone brûlée simulé est une **affirmation géographique fausse** — un incendie qui n'a pas eu lieu, dessiné sur un vrai massif, attribué à « © Union européenne, Copernicus EMS / EFFIS ». `portee-non-publiee.md` §3 interdit d'inventer un fait de domaine. Un scalaire météo faux et un polygone faux ne sont pas de même nature |
| **I-11.4** | **La péremption s'applique à la LECTURE**, dans la projection de couche, jamais par effacement du stockage. Au-delà de T : `zones = []`, `releve_le = ''`, `etat = couche_effis_indisponible` | Effacer perdrait la trace d'exploitation et ferait dépendre une règle de sécurité d'une tâche de nettoyage qui peut ne jamais tourner. **La garde est à la lecture parce que c'est le seul endroit qu'on ne peut pas sauter** |
| **I-11.5** | `panneau-feu.php` **n'appelle aucune fonction de statut** — ni `massifs_statuts_du_jour()`, ni `massifs_synthese_du_jour()`, ni `massifs_fraicheur()`, ni `massifs_jour_courant()`, ni `massifs_saison()` | Il est ainsi **structurellement incapable** de présenter un statut périmé comme courant. Transposition littérale de l'invariant I-9.5 du contrat #9 : la règle est tenue par l'absence de couplage, pas par une garde |
| **I-11.6** | **L'attribution et la donnée n'existent que l'une avec l'autre.** La garde d'attribution est évaluée **avant** toute autre ; son échec fait rendre **zéro octet** à la partie entière. Et `couche_effis_indisponible` ne rend **aucune** attribution | Deux règles convergentes, transposition d'I-9.4 : afficher une donnée EFFIS **sans** attribution manque au §9 du brief ; créditer une source dont **aucune** donnée n'est affichée est « une affirmation fausse » (`templates/footer.php` l. 13-15) |
| **I-11.7** | **Aucune URL tierce nulle part** — ni attribut, ni `fetch`, ni `errorTileUrl`, ni commentaire de code « pour mémoire ». L'URL de la source vient exclusivement de la résolution du §6 | DoD §12. Le piège n'est pas le cas nominal, c'est le cas dégradé |
| **I-11.8** | **Aucun octet de cache n'est écrit dans un fichier.** Le relevé vit dans une option `autoload = false`, écrite par un seul `update_option` après validation complète | §7 A-6. Un répertoire de code d'extension inscriptible par le serveur web en production prend le durcissement du §9 à revers |
| **I-11.9** | Le module **n'émet aucun HTML**, **ne compose aucune date lisible**, **n'échappe rien**. Aucun `wp_date()`, `date_i18n()`, ni `date()` de présentation — `gmdate( DATE_ATOM )` pour le stockage seulement | Frontière stricte du `CLAUDE.md`. Les mots appartiennent au thème, les instants au serveur |
| **I-11.10** | Le vocabulaire de `MASTER.md` §11.2 est tenu **jusque dans les identifiants** : « incendie », « feu actif », « zone brûlée » sont absents des noms de fichiers, classes, fonctions, constantes, variables, classes CSS et commentaires | §11.2 est une table de vocabulaire fixe, pas une consigne de rédaction. Ce sont les trois mots que tout développeur écrira spontanément |

---

## 5. Chaînes — qui les fournit, qui les rend

### 5.1 Fournies par le serveur

| Chaîne | Origine | Traitement |
|---|---|---|
| `© Union européenne, Copernicus Emergency Management Service / EFFIS` | `massifs_attribution_zones_parcourues_par_le_feu()['phrase']` | §9 du brief, **verbatim**. Rendue **entière**, **en texte nu** (aucun `lien_licence` n'existe, §1.2). Jamais découpée |
| `42 ha` et toute surface | `zones[]['surface_texte']` | **Déjà formatée par PHP**, unité et espace insécable compris. Le thème ne formate jamais un nombre |
| `mardi 11 août 2026`, `19 h 04` | `massifs_horodatage( instant_iso_utc )` | Espace insécable inclus. Le thème ne compose jamais une date |

### 5.2 Recopiées verbatim de `MASTER.md` par le gabarit

Idiome déjà établi par `liste-statuts.php`, `legende.php`, `etats-vides.php` et `carte.php` (contrat #7
§5.2) — reproduit, pas inventé. **Le serveur ne les publie pas** : deux sources pour une même chaîne
divergeront. Tranché en **A-3**.

- §11.3 · limites EFFIS : « Périmètres estimés par satellite (feux d'environ 30 ha et plus). Zone déjà
  parcourue par le feu, ce n'est pas un périmètre officiel d'interdiction. »

> **Rendue mot pour mot, ni enrichie ni complétée.** Le §4.4 du brief porte deux faits que §11.3 omet —
> « mise à jour de l'ordre de deux fois par jour » et « ou d'évacuation ». **Ils ne sont pas ajoutés.**
> Une paraphrase d'une chaîne de liste fermée est un défaut bloquant (`MASTER.md` §11.1 règle 8), **y
> compris si elle est plus complète que l'original**. Dette remontée à `lead-design-cms`, §10 D-2.

### 5.3 Chaînes de chrome introduites par cette chaîne — autorisées, §7 A-7

- Titre de bande : `Zones parcourues par le feu` — nom de bande du croquis §7.1 l. 760, en **casse
  normale** (D-26 ; la note de lecture [v2.3] écrit que les capitales du croquis « ne prescrivent aucune
  casse »). Même statut que « La liste du jour ».
- État `aucune_zone` : **`Aucune zone parcourue par le feu détectée.`**
- État `couche_effis_indisponible` : **`Donnée momentanément indisponible.`**
- Étiquettes de champ : `Surface estimée` · `Première observation` · `Dernière observation` ·
  `Commune la plus proche` — reprises **mot pour mot** du §5.2 du brief.
- Fraîcheur : `Relevé le {date_courte} à {heure}` — **déjà en service**, `liste-statuts.php` l. 342, et
  imposée à l'identique par l'arbitrage A-4 du contrat #7. Réemployée, jamais reformulée.

### 5.4 Interdits — gravés

**Portant sur le thème**

1. Tester `nombre` ou `count( $zones )` pour décider du rendu. **Le discriminant est `etat`.** C'est le
   défaut n° 1 de cette issue (§3.1).
2. Rendre une attribution, une fraîcheur ou la phrase de limites quand `etat === 'couche_effis_indisponible'`.
3. Écrire la chaîne d'attribution en dur, la découper, l'abréger, la reformuler, ou l'envelopper dans un
   `<a>` — il n'existe aucune URL de licence EFFIS dans ce contrat.
4. Lire `surface_ha`. La surface affichable est `surface_texte`, déjà formatée.
5. Lire `geometrie`. Ce gabarit rend du texte ; il ne projette rien.
6. Formater une date, un nombre, une surface, une durée ou un âge. Aucun `date()`, `strtotime()`,
   `number_format()`, `round()`, `sprintf()` numérique. Seule voie : `massifs_horodatage()` et les chaînes
   déjà formatées du serveur.
7. Composer une phrase chiffrée (« 3 zones… ») — `MASTER.md` §16, « le thème calculant lui-même un décompte ».
8. Calculer une règle métier : saison, péremption, fraîcheur, distance, commune la plus proche, seuil de
   détection, filtre départemental.
9. Trier, filtrer, dédoublonner ou re-borner la liste des zones. L'ordre et le périmètre viennent du serveur.
10. Écrire « commune inconnue », « aucune commune », « — », « non renseigné », ou substituer **le massif le
    plus proche** à la commune manquante. **La ligne est omise** (§7 A-8).
11. Émettre une pastille, un jalon, un aplat, ou une classe des familles `.statut*`, `.pastille*`,
    `.jalon*`, `.liste-statuts*`, `.legende*`, `.bandeau-alerte*` — invariant I-1 du contrat #22.
12. Écrire une valeur hexadécimale, une couleur, un espacement, une durée, un nom de jeton, ou un style
    en ligne.
13. Rendre une `<section>` portant un titre et rien d'autre — landmark vide, contrat #5 A-16.
14. Appeler une route REST, instancier une classe `Massifs\`, interroger `$wpdb`, appeler une fonction
    d'ingestion, contacter une origine tierce.
15. Mettre quoi que ce soit en cache (transient, `wp_cache_*`, fichier).
16. `if/else` avec branche « sinon » sur `etat`. `match()` **sans `default`**, enveloppé d'un
    `catch ( \UnhandledMatchError )` repliant sur **`couche_effis_indisponible`** — jamais sur
    `aucune_zone`. Un repli est une **absence déclarée**, jamais un faux négatif de sécurité.

**Portant sur l'extension**

17. Émettre du HTML de présentation publique, ou composer une date lisible.
18. Émettre la phrase de limites §11.3 — elle appartient au thème (§5.2, A-3).
19. Appeler `massifs_saison()`, charger `Saison.php`, ou faire dépendre la couche du dispositif estival.
20. Exposer un accesseur « dernier relevé » contournant la péremption (§1.3).
21. Écrire un octet de cache dans un fichier, sous `data/`, sous `includes/`, ou ailleurs (I-11.8).
22. Déclarer une route en écriture, un rôle, une capability, un écran d'administration.
23. Planifier autre chose que le crochet nommé au §6, ou boucler/`sleep()` dans une reprise — WP-Cron
    s'exécute dans la requête d'un visiteur.
24. Écrire en dur une URL EFFIS ou Copernicus comme valeur par défaut. Le défaut est **la chaîne vide**,
    qui produit honnêtement `couche_effis_indisponible` (§8).

---

## 6. Ingestion, fraîcheur, péremption

| | |
|---|---|
| **Origine** | `Settings::url()` résout **constante > passerelle `function_exists` > option > défaut > filtre**, patron littéral de `includes/ingest/prefecture/class-settings.php`. Constante : `MASSIFS_EFFIS_URL`. **Défaut : chaîne vide.** Bascule vers la source réelle = changement d'URL, et rien d'autre (I-11.1) |
| **Coupe-circuit** | `Settings::is_disabled()` : vrai si `MASSIFS_EFFIS_DISABLE`, **ou** si `wp_get_environment_type()` vaut `local`/`development` **et** que `MASSIFS_EFFIS_URL` n'a pas été redéfinie. **Ne lit aucune option** et **n'est jamais mémoïsé** — il doit être ré-évaluable en cours de requête, sans quoi un scénario ne pourrait plus l'armer (c'est ce qui fait fonctionner `tests/scenarios/06`). Une stack non configurée n'émet **zéro octet sortant** |
| **Planification** | Crochet `massifs_effis_recuperation`, récurrence **`hourly`** native, `ensure()` auto-réparateur sur `init`, décalage `+1 min` à la pose. Aucun filtre `cron_schedules`, aucune récurrence maison. **La récurrence horaire *est* la politique de reprise du §4.5**, sans une seule boucle bloquante. **Aucun créneau calé sur l'heure de publication** : les heures de publication d'EFFIS n'ont pas été relevées, les caler serait inventer un fait de domaine |
| **Gardes avant tout octet réseau** | désarmé → retour · anti-rafale : rien si la dernière **tentative** date de moins de 30 min · suffisance : rien si le dernier **succès** date de moins de 6 h. Plafond nominal **≤ 4 appels sortants par jour** |
| **Récupération** | `wp_remote_get` seul. `sslverify => true` **ré-imposé après le filtre d'arguments**, temporisation bornée **après** filtre : un filtre ne peut ni désactiver TLS ni supprimer la borne de temps. `redirection => 2`. **Un 404 est ici un ÉCHEC**, contrairement à la préfecture : il n'existe aucun état « pas encore publié » pour une fenêtre glissante |
| **Validation, cinq couches ordonnées** | **transport** (plafond de corps 2 MiB ; corps ne commençant pas par `{` ; premier caractère `<` = page d'erreur servie en 200 ; **aucun plancher de taille**, un `FeatureCollection` vide est court et légitime) → **forme** (`type === 'FeatureCollection'` ; `features` tabulaire, **`[]` valide** ; `geometry.type ∈ {Polygon, MultiPolygon}` — un `Point` signale qu'on a interrogé la mauvaise couche) → **géométrie** (coordonnées finies et bornées, plafonds de sommets par entité et par lot, plafond d'entités **avant** filtre) → **emprise** (`massifs_emprise()['bbox']` sous garde `function_exists()` ; hors-emprise **écarté en silence**, la source est continentale ; mais **`bbox` absente ⇒ lot entier rejeté**, *fail closed*, sans quoi « filtrée sur le département » du §4.4 n'est pas tenable) → **temporel** (`Last-Modified` absent ⇒ contrôle sauté en silence ; antérieur à `2 × T` ⇒ rejet) |
| **Ce qui n'est PAS une aberration** — à écrire en commentaire pour ne jamais être réintroduit | zéro entité (cas nominal) · un lot identique au précédent (la fenêtre change lentement) · une entité très grande ou très petite (la surface ne se juge pas) · une entité chevauchant plusieurs massifs |
| **Stockage** | Option `massifs_effis_releve`, **`autoload = false`**, un seul `update_option` après validation complète — écriture atomique, aucun état partiel représentable. **Un seul relevé conservé** : une fenêtre glissante n'a pas de valeur passée, et le §4.2 n'impose l'historique que pour les **statuts** |
| **Horodatage faisant autorité** | L'instant du dernier relevé **réussi ET validé**, ISO 8601 UTC. Jamais l'instant de la tentative, jamais un `Last-Modified` de la source. **Un échec n'écrit rien** — sinon la fraîcheur mentirait (§4.5) |
| **Péremption T** | **86 400 s (24 h)** par défaut, redéfinissable par `MASSIFS_EFFIS_PEREMPTION_SECONDES`, bornée `[3600, 604800]`. Appliquée **à la lecture** (I-11.4). **Motif, à écrire dans le code** : la source est une fenêtre glissante de 7 jours publiée ~2×/jour ; servir une fenêtre plus vieille que 24 h, c'est afficher une carte où un feu survenu depuis est **absent**, et un visiteur y lit « aucune zone parcourue par le feu ». C'est le §4.2 atteint par la route inverse — non pas un statut périmé présenté comme courant, mais **une absence périmée présentée comme une mesure** |
| **Alertes courriel** | **Deux, une seule fois chacune par épisode** : (a) **traversée de T**, l'instant où la couche disparaît du site — le seul qui mérite un courriel ; (b) **rejet de validation**, qui signale une source ayant changé de forme. Verrou posé **quel que soit le retour de `wp_mail`** (sinon un relais en panne relance un envoi par heure), ré-armé au premier succès. Texte brut, disant explicitement **ce que le site affiche** |
| **Registre transverse** | Le `Runner` appelle `massifs_enregistrer_releve_reussi( 'effis', $instant )` sous garde `function_exists()`. **Il ne le relit jamais** : l'horloge qui fait autorité pour T vit avec la couche |

---

## 7. Arbitrages — les points où les deux plans ne disaient pas la même chose

### A-1 · Nommage des fonctions — *le désaccord classique du travail en parallèle*

- **Back** : `massifs_zones_parcourues_par_le_feu()`, `massifs_attribution_zones_parcourues_par_le_feu()`.
- **Front** : `massifs_zones_parcourues()`, `massifs_attribution_zones_parcourues()` — en acceptant
  explicitement l'autre au prix d'une ligne de garde (sa clause E-11).

**Décision : le nommage du back.** La convention en service suffixe l'attribution par **la chose
attribuée** — `massifs_attribution()` pour les périmètres, `massifs_attribution_statuts()`,
`massifs_attribution_fond_de_carte()`. Et `MASTER.md` §11.2 fixe le terme **« zone parcourue par le
feu »** : `zones_parcourues` en est une troncature. Le raisonnement de l'arbitrage A-1 du contrat #9
s'applique littéralement — la forme courte « aurait vieilli mal ». La longueur est le prix de la
non-ambiguïté, et ce projet l'a déjà payé une fois en connaissance de cause.

### A-2 · Vocabulaire des états

- **Back** : `zones_disponibles` | `aucune_zone` | `couche_effis_indisponible`.
- **Front** : `disponible` | `aucune_zone` | `indisponible`.

**Décision : le vocabulaire du back, sans amendement.** Deux raisons. **(a)** `couche_effis_indisponible`
est déjà nommé dans **neuf contrats gelés** (#2, #3, #4, #5, #6, #9, #20, #21, #22, #23, #24, #25, #27,
#28) comme le nom de projet de cet état ; l'abréger ici créerait une deuxième orthographe pour une chose
déjà nommée. **(b)** `disponible` et `indisponible` nus sont **déjà employés par le vocabulaire des
statuts** (contrat #7 §3.1 : `etat ∈ {disponible, indisponible, hors_saison, non_encore_publie}`). Les
réemployer pour un objet de domaine différent invite très exactement la confusion que le §3.1 de ce
contrat existe pour empêcher.

### A-3 · La phrase de limites §11.3 — rendue par le thème *(question bloquante du plan back)*

Le plan back a posé la question franchement : contradiction apparente entre « l'extension fournit les
codes, le thème fournit les mots » (contrat #9 §3) et le précédent de l'attribution des statuts, que
§11.3 marque « jamais rédigée à la main par le thème ».

**Décision : le thème la recopie verbatim ; l'extension ne la publie pas.** La contradiction n'est
qu'apparente, et §11.3 la résout lui-même par son propre titre : **« Chaînes fixes rédigées par le
site »**. La phrase de limites y figure ; l'attribution y figure aussi, mais **avec une mention explicite
de provenance** (« elle vient de l'extension »), et elle seule. Le thème recopie déjà quatre chaînes
§11.3 dans `carte.php` (contrat #7 §5.2) et trois autres dans `liste-statuts.php`, `legende.php`,
`etats-vides.php`. Publier la phrase depuis l'extension créerait **deux sources pour une même chaîne**,
qui divergeront.

### A-4 · **La clé `perimee` est supprimée** — le front avait planifié une branche qui ne doit pas exister

- **Front** : une clé `perimee: bool` ajoutant « Donnée périmée. » (§8.3) aux états 3 et 4, sans rien masquer.
- **Back** : aucune clé `perimee` ; la péremption **retire** la couche.

**Décision : le back. La clé `perimee` n'existe pas, et le gabarit ne rend jamais « Donnée périmée. »
pour cette bande.**

**Raison, et elle est de fond.** Pour les **statuts**, `perimee` est une bannière qui **s'ajoute sans
masquer**, parce qu'un statut périmé reste la meilleure information disponible et que la masquer
priverait le visiteur de tout. Pour **cette couche**, la péremption signifie que la fenêtre glissante est
fausse, et qu'**un feu survenu depuis serait absent** : montrer la donnée avec un avertissement
laisserait lire « voici les zones parcourues par le feu » sous une phrase que l'œil saute. Les deux
règles ne se ressemblent que de loin, et **elles ne doivent pas partager un nom**.

Conséquence directe : au-delà de T, la couche bascule **entièrement** en `couche_effis_indisponible`.
Il n'existe pas d'état intermédiaire, et c'est délibéré.

### A-5 · La route REST est conservée *(question bloquante du plan back)*

Le back a signalé honnêtement que la route n'a **aucun consommateur livré dans ce lot**, ce qui la met
sous le coup du §1.4 du contrat #9.

**Décision : conservée.** Trois raisons, dans cet ordre.

1. **Elle est le seul artefact qui serve la deuxième case de la checklist de l'issue** — « Mettre les
   polygones en cache et **les servir depuis notre propre domaine** ». Sans elle, cette case est vide, et
   elle est entièrement dans mon empreinte. Une fonction PHP ne démontre pas « servi depuis notre
   hébergement ».
2. **Elle rend la moitié « nominal » de la ligne §12 démontrable sans une ligne de front**, par
   `rest_do_request()` dans un scénario.
3. Le §1.4 du contrat #9 vise « une **seconde** manière de poser la même question ». Ce n'est pas le cas :
   la fonction de lecture sert le rendu serveur, la route sert la contrainte de re-diffusion du §4.4. Deux
   questions distinctes, une seule source de données.

### A-6 · Où vivent les octets — option + route, **pas** `data/effis/`

Le back a refusé `data/effis/**` malgré le précédent explicite du contrat #9 §10, où `data/tuiles/**` a
été accepté comme « écriture hors empreinte assumée et notifiée », et malgré `data/.gitattributes` qui
annonce nommément les « caches météo / EFFIS / tuiles à venir ».

**Décision : le refus du back est retenu, et le précédent #9 ne s'applique pas.** La différence est de
nature, pas de degré :

- Les tuiles sont **immuables et produites au build**, hors ligne, à la main. **Rien n'écrit dans
  `data/tuiles/` à l'exécution.**
- Les zones parcourues par le feu sont **réécrites par le cron, en production, jusqu'à 4 fois par jour**.
  Écrire dans `data/` à l'exécution rend un répertoire de **code d'extension inscriptible par le serveur
  web** — le durcissement du §9 pris à revers, et sur un mutualisé o2switch précisément la classe de
  configuration qu'on cherche à éviter. Cela introduit en outre une écriture **non atomique** (fichier +
  métadonnées) là où `update_option` en offre une gratuitement.

**Bénéfice décisif d'orchestration** : ce choix supprime **toute** écriture hors empreinte de cette
chaîne. Dans un arbre partagé par trois chaînes sans isolation, cela vaut mieux qu'un précédent.

### A-7 · Les chaînes de chrome sont autorisées, y compris les deux phrases d'état

`MASTER.md` §11.3 est une **liste fermée de chaînes de statut**, et elle ne porte pour EFFIS que la
phrase de limites. Le front a signalé — à juste titre — que les deux phrases d'état vides n'y sont pas.

**Décision : autorisées, sous le précédent A-7 du contrat #7**, qui a admis six chaînes de chrome en
signalant la dette plutôt qu'en bloquant. Traitement par phrase :

- **`Donnée momentanément indisponible.`** — ce ne sont **pas** nos mots : le §4.4 du brief les écrit
  entre guillemets (« donnée momentanément indisponible »). Nous ne faisons que les capitaliser et les
  ponctuer, comme §8.3 ponctue ses propres phrases d'état. Elle respecte §8.3 (le premier mot porte
  l'information) et §11.1 règle 3 (l'erreur ne s'excuse pas).
- **`Aucune zone parcourue par le feu détectée.`** — aucune source, ni brief ni MASTER. Elle est **dérivée
  du vocabulaire fixe** de §11.2 plutôt qu'inventée en ton, et elle est **délibérément sans chiffre** : y
  écrire « sur les 7 derniers jours » ferait composer au thème une phrase numérique à partir de
  `fenetre_jours`, ce que §16 sanctionne. La qualification manquante est portée, immédiatement en dessous,
  par la phrase de limites §11.3 — c'est précisément pourquoi le §3 impose de la rendre **aussi** dans
  l'état `aucune_zone`.
- **Les quatre étiquettes de champ** sont reprises **mot pour mot** du §5.2 du brief. Aucune rédaction.

**Dette signalée à `lead-design-cms`, pas blocage** (§10 D-1, D-2).

### A-8 · « Commune la plus proche » — l'emplacement existe et se tait

Les deux plans convergent, et leur convergence est confirmée. `includes/domain/massifs/README.md`
l. 405-408 acte qu'**aucun référentiel communal n'existe dans le projet** — `communes` est toujours vide,
`lacunes.communes.statut` vaut `inconnue` — et que l'import IGN ADMIN EXPRESS est « une issue à part
entière ». La promesse « communes concernées » du §5.2 du brief est **déjà non tenue** pour le panneau
massif, pour cette raison exacte.

**Décision : la clé existe, vaut `''` en permanence, et le gabarit omet purement la paire `<dt>`/`<dd>`.**
C'est le traitement que ce projet a déjà rendu deux fois (`MASTER.md` §4.1.e, §8.4 ; contrat #7 A-6) :
*l'emplacement existe, il se tait proprement quand il est vide, et il accueillera la donnée sans aucune
refonte*. Aucun tiret, aucun « non renseigné », aucune hauteur réservée.

**Deux substitutions sont explicitement refusées** : (a) **le massif le plus proche** — substituer une
notion à une autre est la conflation refusée en A-6 du contrat #7 ; (b) **un attribut `commune` du WFS
EFFIS** — nous n'avons jamais interrogé cette couche et ne connaissons pas son schéma ; l'écrire dans une
fixture serait inventer un fait sur la forme de la source, ce que `portee-non-publiee.md` §3 interdit.

**Question bloquante remontée au propriétaire** (§11 Q1).

### A-9 · Le nom du fichier reste `panneau-feu.php`, le bloc BEM devient `.zones-parcourues`

Le front a demandé le renommage en `zones-parcourues.php`, avec trois arguments justes : « panneau » a
déjà un référent unique et écrit dans ce projet (le panneau massif, `MASTER.md` §8.4, `.carte__panneau*`)
et désigne donc exactement ce que cette chaîne a décidé de **ne pas** faire ; « feu » nu est une
troncature du terme fixé par §11.2 ; et le slug devient un préfixe BEM voisin de `.carte__panneau-*`.

**Décision : le fichier garde le nom que l'empreinte lui donne ; le bloc BEM prend le nom que §11.2
impose.**

**Raison.** `panneau-feu.php` est écrit **dans l'empreinte de l'issue**, fixée hors de cette chaîne.
Créer `zones-parcourues.php` à la place, c'est créer un fichier **hors empreinte** — exactement l'acte que
ce contrat interdit partout ailleurs, et je ne m'en dispense pas pour une raison de confort de nommage.
**Les objections 2 et 3 du front sont entièrement satisfaites** par le bloc `.zones-parcourues`, qui est à
ma main et que je retiens. Seule l'objection 1 survit — le risque qu'une chaîne future lise le nom comme
« le panneau de clic de la carte » — et elle est neutralisée par un **en-tête de fichier qui dit
explicitement ce que la partie est et ce qu'elle n'est pas**, exigé au §8.

**Renommage recommandé à l'orchestrateur** (§10 D-5), avec les trois arguments du front. Il coûte une
ligne : le slug n'apparaît qu'une fois, dans la couture `massifs_partie()`.

### A-10 · Forme de la surface : `surface_texte` **et** `surface_ha`

- **Back** : `surface_ha` en `float`.
- **Front** : `surface_texte` en chaîne déjà formatée, refusant tout nombre — « §16 : le thème qui
  formaterait un nombre ».

**Décision : les deux clés, avec des rôles écrits.** Le front a raison sur la règle, qui est celle du
projet : `MASTER.md` §11.1 règle 6 et §16 interdisent au thème de formater. Le back a raison sur le fait :
la surface brute est une donnée que la route REST et la future chaîne cartographique consommeront.
`surface_texte` est composée **en PHP par l'extension**, unité et espace insécable compris ;
**`surface_ha` est marquée « jamais lue par le thème »**, sur l'idiome déjà en service au contrat #7 §3.1
pour `auteur_id` et `statut_id`. Une clé de transport se déclare, elle ne se supprime pas.

### A-11 · Instants ISO 8601 complets — le « midi UTC » n'est pas reconduit

Le front a relevé que `massifs_horodatage()` refuse un jour civil nu et que `liste-statuts.php` l. 177-181
contourne par `T12:00:00Z` — et il **refuse de reconduire ce contournement pour une observation
satellite**, au motif que midi UTC n'est pas l'heure d'observation.

**Décision : le refus du front est retenu.** Le contrat exige des **instants ISO 8601 UTC complets** à la
frontière. Si la source ne portait qu'un jour civil, deux sorties, dans cet ordre : **(a)** l'extension
choisit et publie un instant, en le documentant ; **(b)** à défaut, le champ vaut `''` et la paire est
omise. **Jamais un midi fabriqué.** La demande **B-1** (`massifs_horodatage_jour( string $jour )`),
ouverte depuis la chaîne #5 et reprise par #7, reste la bonne réponse longue et est reportée au §10.

### A-12 · Le `h2` de la bande — famille typographique, divergence déclarée

Le front a buté sur un défaut réel qu'il ne peut pas fermer, et il a eu raison de ne pas l'improviser :

- `layout.css` l. 90-96 pose `h1, h2, h3 { font-family: var(--police-titre) }` comme **défaut normatif**
  (`MASTER.md` §5.1 borne (b)) ;
- §5.1 confine la famille d'affichage à **trois zones** — ardoise, légende, **titres de statut** — et une
  zone parcourue par le feu **n'est pas un statut** (§11.2 les sépare, §4.4 du brief le répète) ;
- la corriger exigerait une règle CSS, or `composants.css` est **gelé** et cette chaîne ne possède aucune
  feuille.

**Décision : la partie est livrée telle quelle, et la divergence est déclarée ici plutôt que corrigée en
silence.** Elle est de la même classe que les neuf divergences du §17 de `MASTER.md` : ce n'est pas un
défaut à compter deux fois en revue, c'est un arbitrage écrit. **Elle frappe à l'identique la bande
« Danger météo du jour » de la chaîne #10, qui tourne en ce moment** — la réponse doit être donnée une
fois pour les deux bandes. Remontée en §10 D-3.

**Le repère, en revanche, n'est pas posé.** `MASTER.md` §3.2 emplacement n° 2, **amendé en v2.3**, ne vise
plus que les `h2` **en portée de la famille d'affichage**, et §16 en fait une ligne de revue. Le croquis
§7.1 l. 760 dessine pourtant `▌ ZONES PARCOURUES PAR LE FEU` : divergence tranchée en faveur de §3.2, sur
le précédent écrit du §17 divergence 2 — **« un croquis est une intention de composition, pas une
mesure »**. Même remarque pour `▌ DANGER MÉTÉO DU JOUR` l. 758 (§10 D-4).

### A-13 · La partie est auto-portante jusqu'à la bande incluse

Le front l'a déduit de la contrainte « une seule ligne de couture, et c'est tout ce qui sera jamais
nécessaire », et c'est confirmé. Contrairement aux parties de la chaîne #6, dont `front-page.php`
l. 399-409 émet les enveloppes, `panneau-feu.php` émet **lui-même** son `<div class="bande
bande--zones-parcourues">` et son `<section class="bande__contenu zones-parcourues">`.

Le `<section>` porte **lui-même** `bande__contenu`, et non un `<div>` intermédiaire : les règles de
rythme, de mesure de ligne et de remise à zéro de `layout.css` l. 130-145 ne visent que les **enfants
directs** de `.bande__contenu`. La version imbriquée rendrait des paragraphes à `margin: 0`. La version
retenue rend correctement **avec zéro octet de CSS neuf**.

### A-14 · Liste de `<dl>`, jamais un tableau

Le front a écarté l'idiome tabulaire de `liste-statuts.php`, et l'argument est mesuré : ce tableau ne
tient à 360 px que grâce à ≈ 110 lignes de `composants.css` (l. 247-315, l. 509-592) et à la réplique de
`print.css` l. 106-162, dont cette chaîne ne possède **aucune** ligne, et dont les sélecteurs
`.liste-statuts__*` lui sont interdits par l'invariant I-1 du contrat #22. **Un tableau de quatre colonnes
sans CSS produit un défilement horizontal à 360 px** — `MASTER.md` §10.6 règle 6, défaut bloquant.

**Décision : confirmée.** Et la raison sémantique la double : ce ne sont pas les lignes homogènes d'une
matrice comparable, ce sont des **paires nom/valeur dont un membre peut être absent** (A-8).

### A-15 · Une zone illisible ne bascule jamais sur « aucune zone »

Le front l'a posé de lui-même et c'est confirmé, parce que c'est la règle de sécurité de l'issue appliquée
à un cas que personne n'avait nommé : si toutes les zones sont omises pour cause de champs vides alors que
`etat === 'zones_disponibles'`, la partie rend **`couche_effis_indisponible`**, jamais `aucune_zone`.

Affirmer une absence **mesurée** à partir d'une donnée **illisible** est le faux négatif que le §3.1
interdit. Le repli sûr est le silence déclaré.

---

## 8. Frontière `dev-back-cms` / `dev-front-cms` / `dev-ux-cms`

`dev-back-cms` possède **la totalité de `includes/ingest/effis/**` et les scénarios 40–49**.
`dev-front-cms` possède **`templates/parts/panneau-feu.php`**, et rien d'autre.

**`dev-ux-cms` n'est pas lancé sur cette issue, et c'est une décision.** Cette chaîne ne possède **aucune
feuille de style** : `tokens.css` est gelé, `composants.css` est gelé, `layout.css`, `print.css` et
`carte.css` sont hors empreinte. Toutes les classes `.zones-parcourues__*` sont des **crochets sans
règle** — précédent assumé et déjà en production pour `.carte-secours__*` et `.bandeau-alerte__texte`. Le
rendu livré est la géométrie de bande et la typographie de base, ce qui est lisible, accessible et
conforme. La feuille est une dette nommée (§10 D-6), pas un oubli.

**Trois obligations d'écriture posées à `dev-front-cms`, vérifiables en revue :**

1. **En-tête de fichier disant ce que la partie est et n'est pas** — neutralisation d'A-9. Il écrit
   explicitement que ce gabarit rend l'équivalent textuel serveur des zones parcourues par le feu, qu'il
   **n'est pas** le panneau de sélection de la carte Leaflet, et que la couche cartographique est
   escaladée au §9 de ce contrat.
2. **`declare(strict_types=1);`**, garde `if ( ! defined( 'ABSPATH' ) ) { exit; }`, en-tête `@package
   Massifs` / `@license GPL-2.0-or-later`, **gabarit pur sans aucune déclaration de fonction** —
   `load_template()` fait un `require`, pas un `require_once`. Idiome des cinq parties livrées.
3. **Niveau de titre réglable** par `$args['niveau_titre']`, borné à `2..6`, défaut `2`, **jamais 1** —
   idiome littéral de `legende.php` l. 51-56 et `liste-statuts.php` l. 74-79. Ancre par
   `sanitize_key( $args['ancre'] )`, défaut `zones-parcourues`.

### 8.1 Échappement — chaque sortie, sur l'idiome en service

| Sortie | Fonction | Précédent |
|---|---|---|
| `id`, `aria-labelledby`, `class` variable | `esc_attr()` | `liste-statuts.php` l. 214, `legende.php` l. 184 |
| nom de balise de titre | `esc_html()` | `liste-statuts.php` l. 215 |
| `surface_texte`, `commune_la_plus_proche`, dates formatées, `phrase` d'attribution, phrases §11.3 et de chrome, étiquettes de champ | `esc_html()` | `liste-statuts.php` l. 362, `footer.php` l. 47 |
| `datetime="…"` | `esc_attr()` sur `attr_datetime` **du serveur**, jamais une valeur reconstruite | `liste-statuts.php` l. 379 |

**`wp_kses_post()` n'est employé nulle part, et c'est une décision.** Aucune chaîne du serveur n'est du
HTML ; toutes sont du texte. Accepter du balisage ouvrirait une surface d'injection **sur une donnée
d'ingestion externe**. Corollaire opposable au serveur, et c'est un invariant : **`phrase`,
`surface_texte` et `commune_la_plus_proche` sont du texte brut — aucune balise, aucune entité
pré-échappée.**

### 8.2 Accessibilité — exigences contraignantes

- Un **seul `h2`** ajouté au plan de titres de l'accueil, en dernier, sous le `h1` unique de l'ardoise.
  Aucun `h3`.
- `<section aria-labelledby>` → landmark `region` **nommé, jamais vide** : les gardes du §3 et le `match()`
  garantissent qu'aucune `<section>` ne peut être émise sans contenu (contrat #5 A-16).
- **Aucune information portée par la couleur** — la partie n'émet **aucune** pastille, aucun jalon, aucun
  aplat, aucune classe `--statut-*`. Ce n'est pas une austérité, c'est la seule issue conforme :
  `tokens.css` est gelé sans jeton EFFIS, et les teintes conventionnelles d'une cicatrice de feu (rouge,
  orange, brun) tombent **toutes** dans les bandes interdites de §2.1 — 330°–25°, réservée au rouge
  officiel, et 26°–94°, interdite par implication parce qu'un ambre posé entre un vert et un rouge se lit
  comme un cran intermédiaire inexistant. §10.6 règles 1 et 5 sont satisfaites **par construction**, y
  compris sous `forced-colors: active`, qui n'a rien à reconstruire.
- **Aucun élément interactif** hors liens en ligne dans la prose : aucun bouton, aucune bascule, aucun
  `<details>`/`<summary>`. La couche n'est **jamais masquée derrière une interaction** (esprit du §8.5).
  Aucun piège clavier possible : aucun focus programmatique, aucun `tabindex`, rien à fermer par Échap.
- **Pas de `tabindex="-1"` sur la section** : aucun lien d'évitement ne vise cette ancre. `liste-statuts.php`
  l. 214 en porte un parce que `header.php` l. 49 pointe dessus ; l'ajouter ici serait un attribut sans cause.
- **360 px et zoom 200 %** : aucun tableau, aucune largeur fixe, aucune valeur en `px`, aucun
  `position: fixed`. `overflow-wrap: break-word` (`layout.css` l. 50) couvre un toponyme long. **Aucun
  défilement horizontal possible.**
- **Impression** : la partie s'imprime par défaut — `print.css` l. 98-103 ne masque que `.lien-evitement`,
  `.barre__nav`, `.pied__nav` et `.bande--carte`, dont cette bande n'est pas.
- **Sans JavaScript** : la partie est **identique**. Elle n'enfile aucun script, n'en dépend d'aucun, et
  c'est très exactement ce qui fait qu'elle sert le §5.3 du brief.

---

## 9. La couche cartographique — escaladée, avec ses coutures nommées à la ligne

Trois cases de la checklist de l'issue — couche activable, entrée de légende, reflet dans l'équivalent
textuel de la carte — **ne sont pas livrées par cette chaîne**, et ce n'est pas une difficulté contournée :
c'est une impossibilité mécanique, établie par lecture du code et non par déduction.

| Couture requise | Fichier · ligne | Pourquoi rien d'autre ne marche |
|---|---|---|
| Poignée sur l'instance Leaflet (export, ou `CustomEvent` émis après montage réussi) | `assets/js/carte/carte.js` **l. 26** (ouverture IIFE), **l. 158** (`var carte;`), **l. 168** (`L.map(…)`) | Le fichier n'exporte rien. Leaflet ne tient aucun registre global. Aucun second fichier JS ne peut atteindre la carte |
| Clé `effis` dans l'îlot JSON + enfilage du script + point d'inclusion du panneau | `templates/parts/carte.php` | L'îlot est la seule voie serveur → carte (contrat #7 §4) |
| Classes `.carte__feu*` et leurs motifs | `assets/css/carte.css` | Table de classes **déclarée fermée**, contrat #7 §8.2 |
| Entrée de légende de la couche | `templates/parts/legende.php` | Les deux voies sont fermées : `massifs_partie()` (`functions.php` l. 91-105) ne transmet **aucun `$args`** — le mur d'A-8 du contrat #7 — et il n'existe **aucun** filtre sur `massifs_legende()` |
| Inclusion de la bande textuelle — **une ligne** | `front-page.php` **l. 417-419** | Emplacement **nommément réservé** par un commentaire : « elle appartient à la chaîne "effis", même raison, même place ». Contrat #5 **A-16** |
| Enfilage d'une feuille de style | `functions.php` **l. 199-233** (`$feuilles`) | Aucun autre point d'entrée |
| Tout jeton de couleur | `assets/css/tokens.css` | **Gelé, sha256 épinglé**, 111 propriétés (contrat #4) |

**Le thème ne contient aucun `do_action` ni `apply_filters`** — vérifié, zéro occurrence sur tout
`themes/massifs/`. Il n'existe donc aucun crochet par lequel se greffer.

**La couture n° 5 est de loin la moins litigieuse** : une ligne, à un emplacement que le code réserve
nommément à cette chaîne, sans laquelle `panneau-feu.php` est un gabarit que rien n'appelle. **C'est celle
que je recommande d'accorder en priorité au niveau lot** ; elle seule fait passer le §5.3 du brief — non
négociable — de non tenu à tenu.

**Trois questions de conception que la couche pose et qu'aucun dev ne doit trancher seul**, consignées ici
pour la chaîne qui la portera :

1. **Le pigment.** `tokens.css` étant gelé, la zone ne peut être peinte qu'avec des jetons existants.
   Piste étudiée au brainstorm et jugée solide : **aucun aplat** — hachure ouverte `--c-charbon` sur
   remplissage nul, plus liseré en tirets. Zéro couleur ⇒ §2.1 satisfait trivialement, et la zone **ne peut
   structurellement pas se lire comme un troisième état d'accès**, les deux états réels étant des aplats
   saturés. Le motif doit être **distinct des quatre déjà dépensés** (hachure croisée, hachure descendante,
   pointillé, barre).
2. **L'ordre des couches.** Sous les statuts : invisible, les aplats sont opaques (§4.1.d règle 1).
   Au-dessus : la seule position lisible, **à condition que la couche ne pose aucun opaque** — ce qui est
   exactement l'argument de la hachure ouverte.
3. **Le modèle clavier.** Le contrat #7 §9 impose **un seul arrêt de tabulation**, puis les flèches sur les
   25 `<path>`. Une zone de feu sélectionnable ajoute une 26ᵉ famille focusable — soit mêlée au même cycle
   de flèches, soit en second arrêt de tabulation, ce que §9.3 interdit. **Il n'y a pas de réponse
   évidente.**

---

## 10. Dettes et coutures hors empreinte — signalées, non exécutées

| # | Dette / couture | Porteur proposé |
|---|---|---|
| **D-1** | Phrase de l'état `aucune_zone` — **aucune source**, ni brief ni MASTER §11.3 (liste fermée). Rendue sous le précédent A-7 du contrat #7, à faire entrer au §11.3 | `lead-design-cms` |
| **D-2** | Phrase `Donnée momentanément indisponible.` — les mots sont ceux du §4.4 du brief, mais **absents de §11.3**. Et les **deux faits du §4.4 que §11.3 omet** (« mise à jour de l'ordre de deux fois par jour », « ou d'évacuation ») : §11.3 dit **moins** que le brief | `lead-design-cms` |
| **D-3** | **Famille typographique du `h2` hors portée** (A-12). Défaut §16 tant que ce n'est pas tranché. **Frappe à l'identique la bande météo de la chaîne #10** | `lead-design-cms`, une réponse pour les deux bandes |
| **D-4** | Les deux `▌` du croquis §7.1 **l. 758 et 760** contredisent §3.2 amendé en v2.3 et la ligne §16 correspondante | `lead-design-cms` |
| **D-5** | **Renommage recommandé** `panneau-feu.php` → `zones-parcourues.php` (A-9). Coût : une ligne, le slug n'apparaissant qu'une fois | orchestrateur / propriétaire |
| **D-6** | **Feuille de style de la partie** : toutes les classes `.zones-parcourues__*` sont des crochets sans règle. Cible probable : une feuille neuve enfilée comme `massifs-carte`, `composants.css` étant gelé | `dev-ux-cms`, chaîne ultérieure |
| **D-7** | `break-inside: avoid` sur chaque zone à l'impression, équivalent de `print.css` l. 169-172 — **`print.css` est gelé**, c'est une décision, pas un correctif | `lead-design-cms` |
| **D-8** | **Aller-retour HTTP réel**, sur le modèle de `tests/scenarios/31-meteo-bouchon-http-reel.php` de la chaîne #10 : un bouchon réellement servi et réellement requêté, plutôt que court-circuité à `pre_http_request`. **Confort de recette, non bloquant** — voir l'avenant du §13 | `docker-cms` |
| **D-9** | ~~`docker/tiles/` redevient employé~~ — **rédaction erronée, corrigée par l'avenant du §13.** Rien dans l'empreinte livrée ne touche `docker/tiles/` ; la mention « sans emploi » du contrat #9 **C-7** reste vraie et n'est pas à révoquer | — |
| **D-10** | **Défaut lu, hors empreinte** : `includes/ingest/prefecture/README.md` l. 239 documente les bouchons à `http://tiles/stubs/…`, chemin qui rend **404** — `docker/tiles/nginx.conf` ne sert que `location /tiles/`, le chemin réel est `http://tiles/tiles/stubs/…`. Resté invisible parce qu'un 404 **est** le scénario « pas encore publié » côté préfecture ; il ne le sera pas côté EFFIS, où un 404 est une source muette | chaîne `statuts` ou `infra` |
| **D-11** | `tests/run.sh` l. 25 code en dur un armement **préfecture** : un futur scénario `.arme.php` EFFIS recevrait la mauvaise constante. **Contourné par conception** — aucun scénario 40–49 n'est suffixé `.arme.php` | `test-integration-cms` |
| **D-12** | `massifs_horodatage_jour( string $jour )` — demande **B-1**, ouverte depuis la chaîne #5, reprise par #7, reprise ici (A-11) | back, issue ultérieure |
| **D-13** | **Référentiel communal** (IGN ADMIN EXPRESS, sa licence, son millésime, sa ligne d'attribution §9) : `commune_la_plus_proche` restera `''` tant qu'il n'existe pas | issue `referentiel` dédiée |
| **D-14** | **La couche cartographique elle-même** — §9 de ce contrat, avec ses sept coutures et ses trois questions de conception | jonction de lot, puis issue dédiée |
| **D-15** | Constat d'audit hors empreinte : `legende.php` l. 187/207/232 émet des `<ul>` que `composants.css` l. 446-451 passe en `display: grid` avec `list-style: none`, **sans `role="list"`** — Safari/VoiceOver perd alors la sémantique de liste. **Corollaire liant pour D-6** : si une feuille pose un jour `grid` ou `flex` sur `.zones-parcourues__liste`, un `role="list"` explicite devient obligatoire | signalé, non corrigé |

---

## 11. `OUVERT` et questions bloquantes — à ne jamais combler par déduction

**Domaine — propriétaire du projet**

1. **Commune la plus proche.** Aucun référentiel communal n'existe (A-8). (a) laisser l'emplacement vide
   et silencieux, comme retenu ; (b) ouvrir une issue d'import IGN ADMIN EXPRESS ; ou (c) retirer
   l'exigence du §5.2. **Rien n'est inventé en attendant.**
2. **Licence de redistribution des polygones EFFIS.** La contrainte #2 nous **oblige** à re-servir la
   géométrie depuis notre domaine ; le §9 du brief ne donne que la chaîne d'attribution, jamais les
   conditions de réutilisation. Même classe que la question 8 `OUVERT` de `MASTER.md` §4.1.e et la Q1 du
   contrat #8. Possiblement en sommeil au titre de `portee-non-publiee.md` §5 — **à confirmer, pas à
   supposer.**
3. **Péremption T = 24 h.** Tranchée par ce contrat (§6) faute d'être dérivable, avec son motif écrit.
   **Seuil de sécurité soumis à confirmation** : il est redéfinissable par constante, sans changement de code.

> **`OUVERT` — l'URL et le protocole réels du service EFFIS.** Jamais interrogés, jamais lus. **Aucune URL
> réelle n'est écrite en dur comme valeur par défaut** : le défaut est la chaîne vide, qui produit
> honnêtement `couche_effis_indisponible`. Le module contractualise **GeoJSON `FeatureCollection`** à la
> frontière `Fetcher` → `Validator` ; si la source réelle rend du GML ou du WMS, le changement porte sur
> ces deux classes seulement — soit exactement « un changement de connecteur », périmètre autorisé par
> `portee-non-publiee.md` §2.

> **`OUVERT` — les noms d'attributs du schéma source** (surface, dates d'observation). Jamais relevés.
> Une table de correspondance configurable les expose ; **ses valeurs par défaut sont les noms de NOTRE
> connecteur simulé**, et le `README.md` du module doit écrire noir sur blanc que **ce ne sont pas les noms
> d'EFFIS**. Attribut absent ⇒ `''` ou `0.0`, **jamais une valeur fabriquée**.

> **`OUVERT` — le nom exact de la couche source** (« Burnt Areas 7 Days », §4.4). `faits.couche` vaut `''`.
> Le libellé du brief est cité **dans le README seulement**, comme nom de source, jamais comme donnée publiée.

> **`OUVERT` — EFFIS publie-t-il des instants ou des jours civils ?** Décide entre A-11 tel quel et
> l'activation de la demande B-1 (D-12). **Aucun midi UTC n'est fabriqué en attendant.**

**Orchestration — niveau lot**

4. **Élargissement d'empreinte pour la couche cartographique** — les sept coutures du §9, fichier et
   ligne. **La couture n° 5 (une ligne dans `front-page.php` l. 419) est recommandée en priorité.**
5. **La checklist de l'issue contredit une décision arrêtée.** « Récupérer côté serveur les couches OGC
   EFFIS » contre `portee-non-publiee.md` §4 (« connecteur simulé »). Ce contrat enregistre la simulation ;
   **la board est à corriger.**
6. **Bouchon HTTP et survie de `docker/tiles/`** — D-8, D-9.

---

## 12. Assertions de recette léguées à `test-integration-cms`

1. **`aucune_zone` et `couche_effis_indisponible` ne produisent jamais le même rendu**, alors que
   `nombre` vaut `0` dans les deux. **C'est l'assertion n° 1 de cette issue** (§3.1).
2. **Péremption dure** : à `T + 1 s`, la couche n'est plus servie ; à `T − 1 s`, elle l'est ; **et l'option
   contient toujours les polygones** — la péremption s'applique à la lecture, jamais par effacement (I-11.4).
3. **Zéro requête du navigateur vers un domaine tiers** sur une page portant cette bande, et **zéro appel
   sortant** quand le coupe-circuit est armé.
4. `grep` de `wp_remote_`, `curl_`, `file_get_contents(` sur `includes/ingest/effis/**` → **zéro** hors
   `class-fetcher.php` (I-11.2).
5. `grep` de `saison` sur `includes/ingest/effis/**` (hors `README.md`) → **zéro** (§3.2).
6. `grep` de `incendie`, `feu actif`, `zone brûlée` sur toute l'empreinte → **zéro** (I-11.10).
7. **La phrase de limites §11.3 est rendue octet pour octet**, sans les deux faits du §4.4 que §11.3 omet,
   et l'attribution est rendue **entière et en texte nu**.
8. **Aucune paire `<dt>`/`<dd>` « Commune la plus proche »** n'est émise tant que la donnée est vide —
   aucun tiret, aucun « non renseigné » (A-8).
9. **Sans JavaScript, la bande est identique** : c'est ce qui fait qu'elle sert le §5.3 du brief.
10. **360 px** : aucun défilement horizontal sur la bande. **Zoom 200 %** : aucune perte.
11. Route publique : `200` dans tous les états, `permission_callback` explicite, ETag + `304`, **aucune
    route d'écriture enregistrée** par ce module, réponse invariante par session et sans cookie.

---

## 13. AVENANT du 14 août 2026 — corrections après jonction front↔back

`dev-integration-cms` a vérifié le contrat **dans le code** et a rejoué les scénarios dans la stack.
**Aucune dérive n'a été trouvée entre les deux moitiés** : les §1 à §9 sont conformes clé par clé,
littéral par littéral, y compris les trois valeurs d'`etat` comparées octet pour octet et les deux
chaînes verbatim (§11.3 de `MASTER.md`, §9 du brief) comparées par extraction de points de code.

Deux inexactitudes ont en revanche été relevées **dans le §10 de ce contrat**, et elles sont corrigées
ci-dessus plutôt que laissées en l'état : un contrat qui affirme une chose fausse est un défaut, au même
titre qu'un code qui la fait.

### A-16 · D-8 était trop fort — les scénarios prouvent bien le nominal

D-8 écrivait : « sans lui, la moitié *nominal* de la ligne §12 n'est prouvée que par les scénarios ».
La formule se contredisait elle-même — **les scénarios la prouvent**, et l'arbitrage A-5, raison 2, le
disait déjà en toutes lettres.

Constat d'exécution : les scénarios 40–47 posent `MASSIFS_EFFIS_URL` **uniquement** pour rendre
`Settings::url()` non vide et ré-armer `Settings::is_disabled()` en cours de requête ; l'URL n'est
**jamais requêtée**, `t_bouchon_http()` court-circuitant à `pre_http_request`. **Ils sont donc pleinement
auto-portants** : aucune dépendance à un service Docker, aucun fichier à déposer, et ils passent sur une
stack où rien n'est bouchonné.

Ce que D-8 garde d'utile, et à quoi il est réduit : un **aller-retour HTTP réel**, sur le modèle déjà en
service de `tests/scenarios/31-meteo-bouchon-http-reel.php` (chaîne #10). C'est un confort de recette,
**pas un prérequis de la DoD**.

### A-17 · D-9 était faux — `docker/tiles/` n'est pas ressuscité

D-9 annonçait que `docker/tiles/` « redevient employé » et demandait de révoquer la mention « sans
emploi » du **C-7** du contrat #9. **C'est faux** : rien dans l'empreinte livrée ne touche `docker/tiles/`.
L'erreur venait du plan back, qui projetait de réemployer l'hôte de bouchons de la préfecture ; les
scénarios livrés ne le font pas.

**C-7 du contrat #9 reste vrai et n'est pas à révoquer.** Si D-8 est un jour honoré, il doit suivre le
précédent de la chaîne sœur #10 — bouchon sous `wp-content/plugins/massifs-core/data/<module>/bouchons/`,
servi par le conteneur `wordpress` lui-même — et non ressusciter un service que le contrat #9 a
délibérément mis hors d'emploi.

**D-10 n'est pas touchée par cet avenant** : le défaut de chemin nginx du `README.md` de la préfecture
(`http://tiles/stubs/…`, qui rend 404) reste réel et reste à la charge d'une chaîne `statuts` ou `infra`.
Il ne nous concerne simplement plus.

### A-18 · Une assertion de recette de plus, et pourquoi elle manquait

`tests/scenarios/47-zones-jonction-plugin-gabarit.php` est ajouté par `dev-integration-cms`, dans
l'empreinte. Il est **la seule jonction extension → gabarit du lot** : aucun scénario 40–46 ne rendait le
gabarit, aucun scénario du thème n'appelait l'extension. **L'assertion n° 1 du §12 était donc prouvée sur
des tableaux PHP, jamais sur des octets de HTML** — c'est-à-dire pas prouvée là où elle compte.

Il rejoue les trois états à travers le gabarit réel alimenté par la fonction de lecture réelle, compare
les rendus deux à deux, vérifie les littéraux d'état un à un contre `Couche::ETATS`, éprouve la garde
d'attribution, les deux replis (état inconnu, zones illisibles) et l'échappement sous charge hostile.

### A-19 · Deux dérives réelles dans l'instrumentation des scénarios, corrigées

Relevé par `dev-integration-cms`, et c'est le genre de défaut qu'aucune relecture ne trouve : le compteur
d'appels sortants des scénarios 40 et 45 était branché sur `http_api_debug`, **crochet qui n'est jamais
déclenché** quand `pre_http_request` court-circuite la requête (`WP_Http::request()` rend la main avant
tout `do_action`). Sept assertions étaient **rouges**, et surtout **les gardes de cadence du §6 —
anti-rafale 30 min, suffisance 6 h, plafond de 4 appels par jour — n'étaient pas éprouvées du tout**,
reposant sur un compteur structurellement vide.

Comptage déplacé dans le bouchon lui-même, via la forme *callable* que `tests/bootstrap.php` l. 223-232
prévoit. Les deux lignes de commentaire qui expliquent **pourquoi** `http_api_debug` ne convient pas sont
conservées, pour qu'il ne soit pas réintroduit.

### Ce que cet avenant ne change pas

Aucun élément des §1 à §9 : aucune forme de retour, aucun nom de fonction, aucun état, aucun invariant
I-11.*, aucun interdit du §5.4, aucune chaîne, aucun `OUVERT` du §11. **Seul le §10 est corrigé**, sur
deux de ses quinze dettes, et le §12 reçoit une assertion qui lui manquait.
