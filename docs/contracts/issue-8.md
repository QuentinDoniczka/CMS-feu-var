# Contrat d'interface — Issue #8 — Exposer l'API REST publique de lecture des statuts du jour au format JSON

**Amendé le 21 août 2026 par `lead-issue-cms` (chaîne #45)** — voir [`issue-45.md`](issue-45.md) §14.1.
Amendement : **A-11**, `massifs[].communes` devient **peuplé** et `referentiel.communes_statut` vaut
`'calculee'` en nominal. **Aucune clé n'est ajoutée ni retirée ; aucun consommateur n'a d'adaptation à
faire.** Le raisonnement d'A-11 reste **intact** — voir le renvoi porté sur A-11 même.

> **Gelé le 13 août 2026.** Epic 3 — Carte interactive (milestone #3). Domaine : `statuts`.
>
> Ce contrat fait **autorité sur la forme des données** servies par l'extension aux consommateurs
> publics — la carte interactive de l'issue #7 comme le réutilisateur tiers du §5.4 du brief.
>
> Règle par défaut du projet, appliquée sans exception ici : **le serveur possède les données et les
> chaînes ; le thème les affiche, il ne les compose jamais.** Aucun libellé de niveau, aucune consigne,
> aucune attribution, aucune phrase de fraîcheur n'est rédigée par un consommateur : toutes voyagent
> dans la réponse.
>
> **Empreinte fichiers de l'issue** : `wp-content/plugins/massifs-core/includes/rest/public/**`,
> et rien d'autre. Ce contrat (`docs/contracts/issue-8.md`) est le seul fichier hors empreinte produit
> par la chaîne #8.

---

## 0. Approche retenue et contraintes structurelles

**Une route enveloppe unique, paramètre `jour` borné à deux valeurs.** La réponse est auto-portante :
un consommateur obtient en une requête le jour de validité, la liste complète des massifs avec leur
statut, la synthèse, la fraîcheur, la saison, la légende, le pointeur de géométrie et les attributions.

Deux contraintes structurelles conditionnent l'implémentation et ne sont pas des choix de style :

| # | Contrainte | Conséquence |
|---|-----------|-------------|
| S-1 | **`public` est un mot-clé réservé de PHP** | `namespace Massifs\Rest\Public;` est un `ParseError` **non rattrapable**, et l'autoloader de `massifs-core.php` (l. 69-77, `strtolower(implode('/', $segments))`) ne peut résoudre aucun nom de namespace légal vers `includes/rest/public/`. **Le module n'expose donc AUCUNE classe et ne déclare AUCUN `namespace`** : uniquement des fonctions préfixées `massifs_rest_public_`, chargées par `require_once` depuis `module.php`. Même posture que `includes/domain/massifs/compat.php`. |
| S-2 | **Le chargeur découvre le module par convention** | `massifs_core_charger_modules()` (l. 122-165) parcourt la couche `rest` et charge `includes/rest/<module>/module.php`. **Aucun fichier hors empreinte n'a besoin d'être modifié** — surtout pas `massifs-core.php`, qui appartient à toutes les chaînes à la fois. |

---

## 1. Routes REST

```
GET /wp-json/massifs/v1/statuts
GET /wp-json/massifs/v1/statuts?jour=YYYY-MM-DD
```

| Propriété | Valeur |
|---|---|
| `methods` | `WP_REST_Server::READABLE` (`GET`). **Aucune autre méthode n'est déclarée dans l'espace `massifs/v1`.** |
| `permission_callback` | `'__return_true'`, **écrit explicitement** — jamais absent (le cœur émet un `_doing_it_wrong` depuis WP 5.5), **jamais `is_user_logged_in`**. Lecture publique imposée par le §5.4 du brief, à commenter dans le code. |
| Argument `jour` | `type: string`, `required: false`, **aucun `default`**, `sanitize_callback: 'sanitize_text_field'`, `validate_callback: 'massifs_rest_public_valider_jour'`. |
| Callback `schema` | **Aucun** (arbitrage A-8). |

### 1.1 Bornage du `jour` — invariant de contrat, pas détail d'implémentation

`jour` accepte **exclusivement** le jour civil courant ou le jour suivant, en `Europe/Paris`.

- `jour` absent ⇒ jour courant.
- `?jour=` **vide** ⇒ `400`. Jamais de repli silencieux sur aujourd'hui.
- Toute autre valeur, **y compris une date passée**, ⇒ `400` avec un code stable.

**Il n'y a pas d'archive publique.** §5.4 dit « les statuts **du jour** », §5.2 borne le sélecteur à
« aujourd'hui / demain », §6 place l'historique dans le portail authentifié. Élargir ce bornage est une
autre issue, avec sa pagination, sa politique de cache et son propre arbitrage §4.2 — jamais un
élargissement de celle-ci.

### 1.2 Ordre imposé des gardes du callback

```
1. Disponibilité de l'API de domaine (11 function_exists)  → 503 massifs_api_indisponible
2. Résolution des bornes { courant, suivant } — UNE SEULE FOIS, mémorisées
3. RE-CONTRÔLE de l'appartenance de `jour` aux bornes      → 400 massifs_jour_hors_bornes
4. Référentiel vide (massifs_referentiel() === array())    → 503 massifs_referentiel_indisponible
5. try { assemblage }
     catch ( InvalidArgumentException )                    → 400 massifs_jour_invalide
     catch ( Throwable )                                   → 503 massifs_domaine_en_erreur
6. Émission de la réponse (ETag, en-têtes)
```

**L'étape 3 est non négociable.** Une garde qui dépend d'une autre garde n'est pas une garde : le
`validate_callback` peut être court-circuité par un `rest_request_before_callbacks`, par un appel
interne via `rest_do_request()`, ou disparaître à la faveur d'un refactor.

**Course de minuit, assumée.** Une requête validée à 23:59:59,9 sur le jour D peut être re-contrôlée à
00:00:00,1 contre `{D+1, D+2}` et sortir en `400 massifs_jour_hors_bornes`. Le sens de la défaillance
est le bon : un 400 franc, jamais un statut de la veille présenté comme courant.

### 1.3 Garde de disponibilité — liste fermée de 11 fonctions requises

`massifs_jour_courant` · `massifs_jour_suivant` · `massifs_referentiel` · `massifs_lacunes` ·
`massifs_statuts_du_jour` · `massifs_synthese_du_jour` · `massifs_legende` ·
`massifs_legende_est_confirmee` · `massifs_fraicheur` · `massifs_saison` · `massifs_attribution_statuts`

Elles viennent de **trois modules de domaine indépendants** qui peuvent échouer à charger séparément.
L'arbre de travail est partagé : API absente ⇒ `503` explicite, **jamais un `500` du cœur ni un écran
blanc**.

**Optionnelles, jamais cause de `503`** : `massifs_geometrie`, `massifs_emprise`, `massifs_attribution`.
Absentes ⇒ bloc dégradé, **toutes les clés présentes**, `disponible: false`.

> Règle : le `503` couvre exactement les fonctions sans lesquelles **aucun statut honnête** ne peut
> être produit.

---

## 2. Forme exacte de la réponse `200`

```jsonc
{
  "jour": "2026-08-13",                        // string YYYY-MM-DD — TOUJOURS le jour DEMANDÉ
  "jour_relatif": "aujourd_hui",               // string — "aujourd_hui" | "demain" (liste fermée)
  "jours_disponibles": {                       // object — permet de bâtir le sélecteur §5.2
    "aujourd_hui": "2026-08-13",               //   sans jamais calculer une date de Paris en JS
    "demain": "2026-08-14"
  },

  "saison": {                                  // massifs_saison($jour) moins sa clé `jour`
    "active": true,                            // bool
    "debut": "2026-06-01",                     // string YYYY-MM-DD
    "fin": "2026-09-30",                       // string YYYY-MM-DD
    "prochaine_ouverture": "2027-06-01",       // string YYYY-MM-DD — TOUJOURS une date, jamais null
    "confirmee": true                          // bool
  },

  "fraicheur": {
    "dernier_releve_le": "2026-08-12T15:02:11Z",    // string ISO 8601 UTC | null
    "dernier_releve_source": "prefecture",          // string
    "seuil_secondes": 86400,                        // int
    "perimee": false,                               // bool — BANNIÈRE, jamais un filtre
    "publie_prefecture_le": "2026-08-12T15:00:00Z", // string ISO 8601 UTC | null
    "dispositif_actif": true                        // bool
  },

  "synthese": {                                // massifs_synthese_du_jour() moins `jour_validite`
    "etat_global": "disponible",               // string — même vocabulaire que `massifs[].etat`
    "partiel": false,                          // bool
    "total": 25,                               // int
    "disponibles": 25,                         // int
    "sans_donnee": 0,                          // int
    "par_niveau": { "autorise": 20, "interdit": 5 }, // object<string,int> — TOUTES les clés de
                                                    //   légende, à 0 si aucun massif ne les porte.
                                                    //   TOUJOURS un objet JSON, jamais [] : émis par
                                                    //   un transtypage (object) côté serveur, pour
                                                    //   qu'une légende sans niveau ne s'encode pas
                                                    //   en tableau vide. Object.keys() est sûr.
    "niveau_le_moins_severe": "autorise",      // string | null
    "niveau_le_plus_severe": "interdit"        // string | null
  },

  "massifs": [                                 // LISTE ORDONNÉE (ordre `tri` du référentiel).
    {                                          //   TOUJOURS 25 entrées, dans TOUS les états.
      "code": "sainte-victoire",               // string
      "libelle": "Sainte-Victoire",            // string
      "communes": [],                          // array<string> — TOUJOURS présente ; [] aujourd'hui
      "etat": "disponible",                    // string — liste fermée, cf. §3
      "jour_validite": "2026-08-13",           // string — TOUJOURS === enveloppe `jour`
      "niveau": {                              // object | null littéral — objet SI ET SEULEMENT SI
        "cle": "interdit",                     //   etat === "disponible"
        "libelle": "Accès au massif interdit",
        "consigne": "",
        "severite": 20,
        "motif": "hachure_croisee",
        "jeton_css": "--statut-interdit",
        "jeton_encre_css": "--statut-interdit-encre",
        "rang": 2,
        "total": 2
      },
      "zapef": null,                           // object (même forme que `niveau`) | null littéral
      "source": "recuperation_officielle",     // string "recuperation_officielle"|"saisie_manuelle" | null
      "publie_prefecture_le": "2026-08-12T15:00:00Z"  // string ISO 8601 UTC | null
    }
  ],

  "legende": { /* massifs_legende() VERBATIM, sans aucun renommage de clé :
                  confirmee, consignes_publiees, revision, source, source_officielle_url,
                  publication_heure, niveaux[], zapef[], zapef_note, etats_hors_niveau{} */ },

  "referentiel": {
    "nombre": 25,                              // int
    "communes_statut": "inconnue"              // string — drapeau de lacune, cf. A-11
  },

  "geometrie": {                               // POINTEUR — la géométrie n'est PAS dans cette réponse
    "disponible": true, "url": "https://…/massifs-13.geometrie.json?v=744fba53",
    "version": "744fba53", "sha256": "…", "octets": 278894,
    "format": "geojson", "zoom_max": 11
  },

  "emprise": {
    "bbox": { "ouest": 4.65642, "sud": 43.15731, "est": 5.81325, "nord": 43.90238 }, // object | null
    "centre": { "lon": 5.23484, "lat": 43.52985 }                                    // object | null
  },

  "attribution": {
    "statuts": {                               // massifs_attribution_statuts() verbatim
      "texte": "D'après les publications de la préfecture des Bouches-du-Rhône",
      "carte_officielle_url": "https://www.risque-prevention-incendie.fr/13",
      "bulletin_url_modele": "https://…/{AAAAMMJJ}.pdf"  // MODÈLE portant le jeton {AAAAMMJJ},
    },                                                   //   LIÉ, jamais récupéré ni re-servi
    "perimetres": {                            // massifs_attribution() — dégradé en 3 chaînes vides
      "phrase": "Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du …",
      "lien_source": "https://…", "lien_licence": "https://…"
    }
  }
}
```

### 2.1 Ce qui n'est PAS dans la charge utile, et pourquoi

| Clé écartée | Motif |
|---|---|
| `licence` (de notre agrégat) | **Non tranchée par le brief** — le §9 n'énumère que les attributions amont. Question ouverte Q1, remontée à l'orchestrateur. **Jamais inventée.** |
| `age_secondes`, `evalue_le` | Instants courants : leur présence ferait changer la charge utile **à chaque seconde** et rendrait tout ETag décoratif. `age_secondes` est dérivable de `dernier_releve_le` + l'en-tête `Date`. Arbitrage A-2. |
| `auteur_id` | **Identifiant utilisateur WordPress sur un point d'accès anonyme.** Le §9 exige zéro donnée personnelle publique **et** le blocage de l'énumération d'utilisateurs. |
| `statut_id`, `enregistre_le` | Identités et instants internes, sans sens pour un réutilisateur, et `enregistre_le` ferait churner l'ETag sur une republication sans changement. |
| `niveau_source_brut`, `procedure_source` | Exposition interdite par le contrat #3 (A-14). |
| Géométrie et coordonnées par massif | Le §10 sépare explicitement les budgets (250 Ko page / 300 Ko géométries). Le **pointeur** `geometrie.url` suffit. |
| Clé de version de format | `massifs/v1` la porte déjà. Un second marqueur pouvant diverger du namespace est de l'abstraction spéculative. |

---

## 3. États spéciaux

`etat` et `synthese.etat_global` partagent un **vocabulaire fermé de quatre valeurs** :
`disponible` · `non_encore_publie` · `indisponible` · `hors_saison`.

| État | Émis par le serveur | Rendu par le thème / la carte |
|---|---|---|
| `information_indisponible` | `massifs[].etat === "indisponible"` et/ou `synthese.etat_global === "indisponible"`, **en HTTP 200** | « Information non disponible — consultez la carte officielle », en relayant `attribution.statuts.carte_officielle_url`. Jamais « aucune restriction ». |
| `hors_saison` | `etat === "hors_saison"` ; date de reprise dans `saison.prochaine_ouverture` (toujours renseignée) | « Dispositif estival inactif », avec la date de reprise **lue**, jamais calculée. |
| `non_encore_publie` | `etat === "non_encore_publie"` — cas nominal du jour `demain` avant ~18-19 h | « Statut de demain non encore publié ». Jamais présenté comme « autorisé ». |
| `donnee_perimee` | **N'est PAS un `etat`** : `fraicheur.perimee === true` | Bannière **superposée** aux statuts. **Ne masque jamais un statut, n'en filtre aucun.** |
| `publication_partielle` | **N'est PAS un `etat_global`** : `synthese.partiel === true` | Le dénominateur affiché est `synthese.disponibles`, jamais `total`. |
| `couche_effis_indisponible` | **Hors périmètre de cette route.** Aucune donnée EFFIS n'y figure. | Sans objet ici. |
| API ou référentiel absents | **`503`**, jamais un `200` à liste vide | « Information non disponible ». Un 5xx n'est **jamais** un état de la donnée. |

### 3.1 Trois règles opposables sur les états

1. **Chaque massif est toujours présent**, dans tous les états. Jamais d'omission : une liste
   raccourcie se lit « aucune restriction ».
2. **Toujours `HTTP 200`** pour `indisponible`, `hors_saison`, `non_encore_publie`. **Jamais `404`,
   jamais `204`** — un réutilisateur traite un corps absent comme « rien à signaler », ce qui est
   exactement la violation du §4.2 que ce contrat existe pour empêcher.
3. `niveau` et `zapef` valent **`null` littéral** hors de `etat === "disponible"`. **Jamais `{}`,
   jamais `{"cle": ""}`.** Produits par `null === $x ? null : …`, jamais par un `array_filter` ni un
   `?? array()`.

---

## 4. Chaînes fournies par le serveur

Aucun consommateur ne rédige, ne compose ni ne traduit ce qui suit. Tout voyage dans la réponse :

| Chaîne | Origine | Clé |
|---|---|---|
| Libellé officiel de niveau | `Legende` (configuration versionnée) | `massifs[].niveau.libelle`, `legende.niveaux[].libelle` |
| Consigne officielle | idem — **vide aujourd'hui**, fait relevé, **jamais à combler** | `massifs[].niveau.consigne` |
| Sévérité, rang, total, motif, jetons CSS | idem | `massifs[].niveau.*` |
| Libellé de massif | référentiel DDTM | `massifs[].libelle` |
| Attribution des statuts | §9 du brief, imposée | `attribution.statuts.texte` |
| Lien de la carte officielle | §4.2, repli obligatoire | `attribution.statuts.carte_officielle_url` |
| Modèle d'URL du bulletin | relevé de source | `attribution.statuts.bulletin_url_modele` |
| Attribution des périmètres | §9, Licence Ouverte 2.0 | `attribution.perimetres.phrase` |
| Bornes et dates de saison | `massifs_saison()` | `saison.*` |
| Instants de fraîcheur | `massifs_fraicheur()` | `fraicheur.*` |

**`attribution.statuts.carte_officielle_url` voyage TOUJOURS** — dans tous les états, y compris
`indisponible` et `hors_saison`, **et dans les corps d'erreur `503`** dès qu'elle est obtenable. C'est
le repli imposé par le §4.2 ; un réutilisateur doit pouvoir le relayer sans l'écrire en dur.

**`legende.confirmee` et `legende.consignes_publiees` voyagent toujours**, quelles que soient leurs
valeurs courantes. Un tiers ne doit ni présenter un libellé non vérifié comme officiel, ni afficher un
intitulé « Consigne » alors que le dispositif n'en publie aucune.

---

## 5. Erreurs

| Code | HTTP | Déclencheur | `data` supplémentaire |
|---|---|---|---|
| `rest_invalid_param` (cœur) | `400` | `jour` malformé, vide, ou hors des deux jours autorisés | `params`, `details` (cœur) |
| `massifs_jour_hors_bornes` | `400` | seconde garde du callback (validation contournée, bascule de minuit) | `jours_disponibles` |
| `massifs_jour_invalide` | `400` | `InvalidArgumentException` remontée du domaine | — |
| `massifs_api_indisponible` | `503` | une des 11 fonctions requises manque | `fonctions_absentes` (liste de noms) |
| `massifs_referentiel_indisponible` | `503` | `massifs_referentiel()` retourne `array()` | `carte_officielle_url` si obtenable |
| `massifs_domaine_en_erreur` | `503` | `Throwable` inattendue pendant l'assemblage | — |
| `rest_no_route` / méthode non permise (cœur) | `404` / `405` | chemin inconnu / `POST`, `PUT`, `PATCH`, `DELETE` | — |

**Deux règles de fond :**

- **Le message d'une exception ne voyage jamais** dans la réponse : uniquement un code stable et une
  phrase fixe et neutre. Le détail part dans `error_log()` **sous `WP_DEBUG` seulement**. Une trace PHP
  sur un point d'accès anonyme est une fuite (§9).
- **Pas de `Retry-After`** : nous ne connaissons pas le délai de rétablissement, et l'inventer
  mettrait une donnée fausse dans un en-tête.

**Référentiel vide ⇒ `503`, et non `200` à liste vide** (A-4) : ce n'est pas un état de la donnée mais
une panne, et `"massifs": []` servi en `200` se lit « aucune restriction » chez un consommateur naïf.

---

## 6. En-têtes, cache et ETag

| En-tête | Valeur | Émis par |
|---|---|---|
| `Content-Type` | `application/json; charset=UTF-8` | cœur |
| `X-Robots-Tag` | `noindex` | cœur (toutes routes REST) |
| `Access-Control-Allow-Origin` | **l'origine présentée**, en écho — jamais `*` ; **absent** si la requête ne porte pas d'en-tête `Origin` | **cœur — on n'y touche pas.** La réutilisation cross-origin est l'objet même du §5.4 |
| `Access-Control-Allow-Credentials`, `Access-Control-Allow-Methods`, `Vary: Origin` | posés par le cœur en même temps que l'écho ci-dessus | **cœur — on n'y touche pas** |
| `Cache-Control` | **`no-cache`** | **notre réponse** |
| `ETag` | `W/"<sha1 de la charge utile>"` | notre réponse, **sauf** `_fields` / `_jsonp` / `_envelope` |

### 6.0 Correction factuelle — ce que le cœur émet réellement en CORS

**Le gel initial de ce contrat décrivait un `Access-Control-Allow-Origin: *`. C'est faux**, et la
description est corrigée ici sans qu'une ligne de code change : le défaut était dans le contrat, pas
dans l'implémentation.

`rest_send_cors_headers()` **renvoie l'origine présentée**, pas une étoile :

- requête portant un en-tête `Origin` ⇒ le cœur émet `Access-Control-Allow-Origin: <cette origine>`,
  plus `Access-Control-Allow-Methods`, `Access-Control-Allow-Credentials: true` et `Vary: Origin` ;
- requête **sans** `Origin` — `curl` nu, cron, appel serveur à serveur ⇒ le cœur n'émet **aucun**
  `Access-Control-Allow-Origin`. Il n'y a rien à y lire, et l'absence n'est pas une restriction : les
  en-têtes CORS ne s'adressent qu'au navigateur.

**L'effet promis par le §5.4 tient intégralement** : un navigateur en contexte cross-origin envoie
**toujours** `Origin`, l'obtient en écho, et la lecture publique fonctionne depuis n'importe quel
domaine. La permissivité effective est celle d'un `*` — l'écho inconditionnel n'autorise pas moins —,
c'est seulement la **forme** de l'en-tête qui diffère de ce que ce contrat annonçait.

**La consigne opposable est inchangée et tenue : cœur, on n'y touche pas.** Aucun
`rest_pre_serve_request`, aucun `add_filter`, aucune manipulation d'en-tête CORS dans
`includes/rest/public/` — c'est l'interdit 5 du §7.1, et la review a confirmé qu'il est respecté.
**Corriger cette ligne ne justifie surtout pas d'ajouter une manipulation CORS** : rétablir un `*`
littéral demanderait précisément le filtre site-wide que l'interdit 5 proscrit, pour un gain nul.

**Relevé sur la stack en fonctionnement** (et non déduit de la lecture du cœur — c'est l'absence de ce
relevé qui avait laissé passer l'erreur) :

```
$ curl -sI -H 'Origin: https://exemple-tiers.fr' .../wp-json/massifs/v1/statuts
Access-Control-Allow-Origin: https://exemple-tiers.fr
Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE
Access-Control-Allow-Credentials: true
Vary: Origin,Accept-Encoding
ETag: W/"a303cee89281d0975a263c99a1532cf59109be98"

$ curl -sI .../wp-json/massifs/v1/statuts          # aucune en-tête Origin
Vary: Origin,Accept-Encoding
ETag: W/"a303cee89281d0975a263c99a1532cf59109be98"
                                                  # ← AUCUN Access-Control-Allow-Origin
```

**ETag identique dans les deux cas** : la charge utile ne varie pas avec l'`Origin`, I-9 tient.

Deux conséquences à retenir :

1. **Ne jamais tester l'égalité à `*`.** Une sonde d'intégration vérifie que l'en-tête **reflète
   l'`Origin` envoyée**, et qu'il est **absent** quand aucune `Origin` n'est envoyée.
2. `Vary: Origin` vient du cœur et ne contredit pas l'interdit 6 du §7.1, qui vise la variation **par
   utilisateur ou par session** (`Vary: Cookie`). La réponse reste identique pour tous : `Origin`
   n'influence que les en-têtes CORS, jamais un octet de la charge utile (I-9).

**Correction factuelle intégrée au contrat** : le cœur n'envoie ses en-têtes `nocache` **que si
`is_user_logged_in()`** (défaut du filtre `rest_send_nocache_headers`). Sur une requête anonyme — le cas
nominal — il n'envoie **aucun** `Cache-Control`. Il n'y a donc rien à « conserver » : nous le posons
nous-mêmes sur notre propre `WP_REST_Response`, ce qui le rend invariant par session.

**Jamais de `max-age`.** Il faudrait le clamper sur les secondes restant jusqu'à minuit heure de Paris,
sinon un `max-age=900` posé à 23 h 55 sert la journée d'hier pendant dix minutes — le §4.2 par la porte
de derrière. `no-cache` et **non** `no-store` : le client garde sa copie, ce qui rend le `304` utile,
mais revalide à chaque fois.

**Exclusion du cache de page** : la route s'exclut **par son propre `Cache-Control`**, sur sa propre
réponse, **jamais par un filtre site-wide**. Le point d'accroche de l'invalidation du cache de page
reste l'action `massifs_statuts_publies` déjà émise par le domaine — elle appartient à une issue
`perf`, **pas à celle-ci**, et rien dans ce module ne s'y abonne.

### 6.1 ETag et `304`

```
etag = 'W/"' . sha1( wp_json_encode( $charge ) ) . '"'
```

Calculé sur **la charge utile entière** — possible précisément parce que `evalue_le` et `age_secondes`
en sont exclus (A-2). `If-None-Match` est lu via `$requete->get_header( 'if_none_match' )` (le cœur
normalise en minuscules et `-`→`_`), découpé sur `,`, chaque entrée `trim()`ée, le préfixe `W/` retiré
des **deux** côtés (RFC 9110 impose la comparaison faible pour `If-None-Match`), `*` accepté.

**Ni ETag ni `304` si la requête porte `_fields`, `_jsonp` ou `_envelope`** (A-3). Ces trois paramètres
du cœur modifient les octets réellement servis **après** notre callback ; un ETag qui ne décrit pas le
corps servi est pire qu'aucun ETag. C'est une condition locale à trois noms, **aucun filtre
site-wide** — `rest_jsonp_enabled` n'est pas touché.

**Verrue connue, documentée et acceptée** : le cœur `echo wp_json_encode( $result )` sans cas
particulier pour un `304`, ce qui émet 4 octets (`null`) sur une réponse qui ne devrait pas avoir de
corps. Inoffensif pour `curl` et les navigateurs. **Point à mesurer par `test-integration-cms`** ; si
cela pose problème, l'échappatoire est de retirer le `304` et de garder l'ETag seul — la justesse n'en
dépend pas.

---

## 7. Interdits

### 7.1 Interdits d'implémentation (module `rest/public`)

1. **Aucune classe, aucun `namespace`, aucun `use`** dans les fichiers PHP de ce module (S-1).
2. **Aucun fichier hors de `includes/rest/public/`.** Ni `massifs-core.php` (inutile, S-2), ni
   `includes/domain/**`, ni le thème, ni `tests/**`.
3. **Aucun accès à `$wpdb`**, aucune classe `Massifs\`, aucune fonction `massifs_enregistrer_*`,
   aucune fonction d'ingestion. Liste fermée de 11 + 3 fonctions de domaine, et rien d'autre.
4. **Aucun `do_action`, aucun `apply_filters` émis** par ce module. Une route de lecture qui émet un
   hook offre une prise d'écriture à un tiers.
5. **Aucun filtre site-wide enregistré** : ni `rest_jsonp_enabled`, ni `rest_send_nocache_headers`,
   ni `rest_authentication_errors`, ni `rest_pre_serve_request`. Seul hook consommé : `rest_api_init`.
6. **Aucune variation par utilisateur** : pas de `current_user_can`, pas de `is_user_logged_in()`, pas
   de paramètre `context`, pas de `Vary: Cookie`. Une route publique dont la sortie varie par session
   est un cache empoisonnable.
7. **Aucun `show_in_rest`, `register_rest_field`, CPT ni taxonomie** créés par ce module.
8. **Aucun `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses*()` ni `sanitize_text_field()` sur une
   valeur de charge utile.** Une entité HTML dans du JSON est une **corruption de donnée**, pas une
   protection. L'encodeur correct de cette frontière est `wp_json_encode`, appliqué **une seule fois**
   par `WP_REST_Server`. L'échappement HTML a lieu dans le thème, au rendu.
9. **Aucun cache serveur** (transient, cache objet) dans cette issue.
10. **Ne pas « optimiser » la double requête** : le callback appelle `massifs_statuts_du_jour()` **et**
    `massifs_synthese_du_jour()`, qui rappelle la première en interne. C'est le prix de la règle « le
    consommateur ne recalcule jamais la sémantique de la synthèse ». **Recalculer `par_niveau`
    localement est un défaut bloquant, pas une optimisation.**
11. **Aucun tri.** `massifs_referentiel()` est déjà trié par `tri` ; aucun `sort`, `usort`, `ksort`,
    `setlocale`. La liste JSON se construit en itérant sur le référentiel (l'ordre), pas sur le retour
    de `massifs_statuts_du_jour()` (indexé par code).
12. **`module.php` est écrit EN DERNIER.** C'est le seul chemin que le chargeur découvre : tant qu'il
    n'existe pas, le module est invisible. Le créer en premier ferait charger un module à moitié écrit
    par les chaînes #7 et #9 qui tournent sur le même arbre — et un `ParseError` de fichier inclus
    **n'est pas rattrapable par `try/catch`** : écran blanc sur tout le site, pour les trois chaînes.

### 7.2 Interdits pour le consommateur (thème, carte #7, réutilisateur tiers)

1. Appeler une fonction d'ingestion, une classe `Massifs\`, ou `$wpdb`.
2. **Calculer « aujourd'hui » ou « demain »** : `jours_disponibles` fait foi côté client,
   `massifs_jour_courant()` côté PHP. Jamais un `new Date()` en JS.
3. Formater, traduire ou composer un libellé de niveau, une consigne, une sévérité, un ordre, une
   couleur, une attribution ou une phrase de fraîcheur. **Toutes voyagent.**
4. Afficher `niveau` ou `zapef` quand `etat !== "disponible"`.
5. Afficher un statut d'un autre jour, le mémoriser en `localStorage` ou en session, ou rejouer une
   réponse précédente.
6. Traiter `fraicheur.perimee` comme un masque ou un filtre.
7. **Interpréter un corps vide, un `404` ou un `5xx` comme « aucune restriction ».**
8. Écrire en dur `carte_officielle_url` ou `bulletin_url_modele`.
9. Requêter cette route depuis un domaine tiers, ou la servir par un CDN (contrainte #2).
10. Utiliser `?_fields` en escomptant un ETag : il n'est pas envoyé dans ce cas.
11. Demander une date passée : c'est un `400` par construction, pas un bug.
12. Écrire `isset()` ou `??` sur une clé du contrat : **toutes sont toujours présentes** (I-7).

---

## 8. Invariants opposables en review

| # | Invariant |
|---|---|
| I-1 | Les 25 massifs sont **toujours** présents, dans tous les états. Jamais d'omission. |
| I-2 | **Toujours `HTTP 200`** pour `indisponible`, `hors_saison`, `non_encore_publie`. Jamais `404`, jamais `204`. |
| I-3 | `niveau` et `zapef` valent **`null` littéral** hors de `etat === "disponible"`. |
| I-4 | `attribution.statuts.carte_officielle_url` voyage **toujours**, corps d'erreur `503` compris. |
| I-5 | `legende.confirmee` et `legende.consignes_publiees` voyagent toujours. |
| I-6 | `massifs[].jour_validite === jour` de l'enveloppe, sans exception. |
| I-7 | Toutes les clés du contrat sont **toujours présentes**. Le consommateur n'écrit jamais `isset()` ni `??`. |
| I-8 | `etat` et `etat_global` se filtrent par `match()` **sans `default`**, sous `try/catch ( UnhandledMatchError )` (arbitrage #27). |
| I-9 | La réponse **ne varie jamais** selon l'utilisateur, la session ou un cookie. |
| I-10 | **La géométrie n'est pas dans cette réponse** — suivre `geometrie.url`. |
| I-11 | Aucune écriture n'est atteignable : `READABLE` seul, aucune route en écriture dans `massifs/v1`. |

---

## 9. Arbitrages

Cette issue ne touche que l'extension : il n'y a **pas eu de plan front à réconcilier**, donc aucun
désaccord entre deux plans. Les arbitrages ci-dessous sont ceux que j'ai tranchés entre le brainstorm,
le plan back et les règles du projet.

| # | Point | Décision | Raison |
|---|---|---|---|
| **A-0** | La carte #7 doit-elle `fetch()` cette route ? | **Le contrat gèle la FORME, pas le TRANSPORT.** Recommandation portée à l'orchestrateur : la carte s'hydrate **depuis la page** (JSON inline ou attributs de données), pas depuis le réseau. | La contrainte #3 impose que les statuts soient déjà dans le HTML rendu par PHP. Un `fetch()` sur la même donnée ajoute un boot WordPress complet sur le chemin critique de l'accueil (§10) **et ouvre une fenêtre de divergence §4.2** : le HTML dit A à 18:59:58, le fetch dit B à 18:59:59 — deux statuts différents pour le même massif, sur la même page. Décision finale : #7 et l'orchestrateur. |
| **A-1** | Une route enveloppe ou plusieurs ressources ? | **Une route auto-portante.** | Le §5.4 promet « un point d'accès ». Séparer `/legende` créerait une corrélation de versions : un client pourrait peindre un massif avec un `niveau.cle` qu'il ne sait pas nommer. Le domaine élimine ce risque gratuitement en inlinant `niveau` complet. |
| **A-2** | `evalue_le` / `age_secondes` : hors charge utile ou hors ETag ? | **Hors charge utile.** | `age_secondes` est dérivable de `dernier_releve_le` + l'en-tête `Date` ; `evalue_le` décrit la requête, pas la donnée. Les exclure du seul ETag produirait deux `200` aux octets différents partageant un ETag — correct mais invérifiable à la lecture. **Une** règle vaut mieux que deux. **Aucun impact sur les contrats #5/#6** : le thème lit les fonctions PHP, où les deux clés restent. |
| **A-3** | `_fields` / `_jsonp` / `_envelope` et l'ETag | **Ni ETag ni `304`** quand l'un des trois est présent. | Ils modifient les octets servis après notre callback. Condition locale à trois noms ; **aucun filtre site-wide**. |
| **A-4** | Référentiel vide : `200` à liste vide ou `503` ? | **`503`.** | Ce n'est pas un état de la donnée mais une panne, et `"massifs": []` en `200` se lit « aucune restriction » — la violation exacte du §4.2. |
| **A-5** | Pointeur de géométrie : inclus ou omis ? | **Inclus** (`geometrie` + `emprise`), avec `attribution.perimetres` en contrepartie obligatoire. | Vérifié : `massifs_geometrie()` **n'ouvre aucun fichier** (`geometrie.php` l. 5-10) — coût I/O nul. Un réutilisateur qui reçoit 25 statuts sans savoir où sont les polygones ne peut rien en faire. Publier une adresse de données Licence Ouverte **oblige** sa citation (§9). |
| **A-6** | `niveau` inliné ou référencé vers `legende.niveaux` ? | **Inliné, forme du domaine verbatim.** | Le contrat #3 pose que les formes du domaine sont `wp_json_encode`ables telles quelles, sans adaptateur ni renommage. Coût ~12 Ko de répétition, ~1,5 Ko après gzip. Une jointure côté client est une occasion de plus d'inventer un libellé. |
| **A-7** | `auteur_id` exposé ? | **Non.** | Identifiant utilisateur WordPress sur un point d'accès anonyme : le §9 exige zéro donnée personnelle publique **et** le blocage de l'énumération d'utilisateurs. |
| **A-8** | Callback `'schema'` sur la route ? | **Non.** | Doublerait la taille du module et créerait une **seconde représentation de la même forme**, qui dérivera. Ce contrat + le `README.md` du module sont la documentation ; `OPTIONS` expose déjà les `args`. |
| **A-9** | `Retry-After` sur les `503` ? | **Non.** | Nous ne connaissons pas le délai de rétablissement ; l'inventer mettrait une donnée fausse dans un en-tête. |
| **A-10** | Clé de version de format ? | **Non.** | `massifs/v1` la porte. |
| **A-11** | `communes: []` exposé ou clé omise ? | **Exposé**, plus `referentiel.communes_statut: "inconnue"`. | La lacune est **documentée par le domaine** (`massifs_lacunes()`, `STATUT_COMMUNES_DEFAUT`), pas inventée. Une clé omise obligerait à un `isset()` (contre I-7) ; une liste vide **seule** se lirait « aucune commune concernée », ce que le contrat #6 interdit. Le drapeau est la seule valeur qui ne puisse pas être relue ainsi. L'enrichissement par IGN ADMIN EXPRESS relève d'une issue `referentiel` distincte.<br><br>**Amendé le 21 août 2026 par l'issue #45 — l'issue `referentiel` annoncée ci-dessus est celle-là.** `communes` **reste exposé et devient peuplé** (noms officiels IGN, triés par surface décroissante de la part du massif, jamais de code INSEE) ; `communes_statut` vaut **`'calculee'`** en nominal et **conserve `'inconnue'` en replié**. **Le raisonnement d'A-11 n'est pas corrigé, il est confirmé** : une liste vide **seule** se lirait toujours « aucune commune concernée », et le drapeau reste la seule valeur qui ne puisse pas être relue ainsi — c'est pourquoi il survit au remplissage au lieu de disparaître avec la lacune. `'calculee'` plutôt que `'disponible'` dit au réutilisateur que la liste **résulte de notre propre calcul** (intersection avec ADMIN EXPRESS COG Carto 2026, seuil de 1 % de la surface du massif) et **n'est pas une publication officielle de la DDTM**. **Aucune clé ajoutée ni retirée ; aucun consommateur n'a d'adaptation à faire.** |
| **A-12** | Champ `licence` de notre agrégat ? | **Absent.** Question remontée, jamais comblée. | Le §9 n'énumère que les attributions **amont**. La licence de notre agrégat est une décision du propriétaire du projet, pas une déduction. |
| **A-13** | `?_jsonp=` désarmé ? | **Non, laissé tel quel.** | `rest_jsonp_enabled` est **site-wide** : un module `rest/public` n'a pas à trancher pour tout le site. Et le cœur renvoie déjà **l'origine présentée** en écho (§6.0), ce qui autorise en pratique n'importe quel domaine : JSONP n'ajoute donc aucune exposition — la donnée est publique par destination (§5.4). |

---

## 10. Poids et budget

Chiffres **mesurés** contre le vrai `data/massifs-13.php` et le vrai `legende.config.php`, 25 massifs
(et non estimés) :

| Cas | Brut | gzip -6 |
|---|---|---|
| Journée complète, 25 massifs `disponible` | 18 915 o (18,5 Ko) | 1 882 o (1,8 Ko) |
| Aucun statut (25 × `niveau: null`) | 7 897 o (7,7 Ko) | 1 735 o (1,7 Ko) |

Taux de compression très favorable : les mêmes objets `niveau` / `zapef` sont répétés 25 fois.
`application/json` est compressé par `mod_deflate` (`docker/wordpress/deflate.conf` l. 11). **Hors du
budget §10 de la page** : cette réponse est un enrichissement progressif, servi après le premier rendu.

---

## 11. Points ouverts remontés à l'orchestrateur — non comblés

| # | Point | Nature |
|---|---|---|
| **Q1** | **Licence de notre agrégat et autorisation de reproduction.** Cette issue transforme la question ouverte du contrat #3 en publication effective : un point d'accès anonyme, ouvert à toute origine par l'écho CORS du cœur (§6.0), servant une reproduction lisible par machine de la légende officielle et des statuts du jour, **sans licence déclarée**. Deux décisions attendues du propriétaire : (a) la licence de notre agrégat ; (b) le maintien de cette ouverture cross-origin tant que (a) n'est pas tranché. | **Bloquante avant mise en production**, pas avant le commit. Se comble par un arbitrage du propriétaire, jamais par une invention. |
| **Q2** | `tests/scenarios/12-geometrie-et-rest.php` — l. 5, 64 et surtout **l. 69** affirment « aucune route REST massifs ». Cette issue rend l'assertion fausse **par construction**. Fichier **hors empreinte**. Correction minimale : remplacer l'assertion par une **égalité sur liste exacte** (jamais une suppression ni un affaiblissement, contrat #30 interdit 9) — `sort( $nôtres )` puis `t_egal( array( '/massifs/v1', '/massifs/v1/statuts' ), $nôtres, … )`. **Deux entrées, pas une** : le cœur enregistre automatiquement la route d'index d'espace de noms `/massifs/v1`. | À affecter par l'orchestrateur au niveau du lot. |
| **Q3** | `?_method` et `X-HTTP-Method-Override` — non vérifiables par lecture (WordPress n'est pas vendorisé dans le dépôt). **La mitigation n'en dépend pas** : aucune route en écriture n'existe dans `massifs/v1`, donc un override honoré n'atteint qu'un `404`/`405`. Trois sondes à jouer par `test-integration-cms` : `POST /statuts` → `405` attendu ; `?_method=POST` → `200` ou `405` ; `POST` + `X-HTTP-Method-Override: GET` → `200` acceptable. **Défaut** = un `200` sur un `POST` nu. | Vérification de lot. |
| **Q4** | **Aucune limitation de débit** sur un point d'accès anonyme à 19 Ko. Le §9 n'en demande pas côté public. | Relève d'une issue `perf` ou `securite`. |
| **Q5** | **Découvrabilité** de l'endpoint depuis les pages publiques (`<link rel="alternate" type="application/json">` ou en-tête `Link:`). Modification du `<head>` du thème, **hors empreinte**. | Décision d'orchestration, épic 6 (« La démarche »). |
