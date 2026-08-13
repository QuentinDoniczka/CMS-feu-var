# Point d'accès public — statuts du jour au format JSON

Documentation de **ce que la route rend**. Elle est destinée à alimenter la page « La démarche » du
site public et la documentation d'un réutilisateur tiers.

Ce répertoire est servi en `403` par le serveur web (règle d'exposition sur `includes/` de
l'extension) : ce fichier n'est jamais publié. Il est la source de la rédaction, pas la publication.

---

## 1. La route

```
GET /wp-json/massifs/v1/statuts
GET /wp-json/massifs/v1/statuts?jour=AAAA-MM-JJ
```

- **Lecture seule.** Aucune méthode d'écriture n'existe dans l'espace `massifs/v1`.
- **Sans authentification**, sans cookie, sans donnée personnelle. La réponse ne varie jamais selon
  l'utilisateur, la session ou un en-tête d'identification.
- **Servie depuis notre domaine.** Aucune redirection vers un tiers, aucun CDN.
- `Access-Control-Allow-Origin: *` est posé par le cœur de WordPress : la réutilisation depuis un
  autre domaine est possible.

### Le paramètre `jour`

| Situation | Résultat |
|---|---|
| Paramètre absent | Le jour civil courant, en `Europe/Paris`. |
| `?jour=` égal au jour courant ou au jour suivant | Ce jour-là. |
| `?jour=` vide | `400`. Aucun repli silencieux sur aujourd'hui. |
| `?jour=` mal formé, ou date passée, ou date plus lointaine | `400`. |

**Il n'y a pas d'archive publique.** Le point d'accès sert les statuts *du jour* : aujourd'hui et
demain, rien d'autre. L'historique existe, mais dans le portail authentifié.

Le format attendu est strictement `AAAA-MM-JJ`, sans espace ni tolérance de mise en forme.

---

## 2. La réponse

`HTTP 200`, `Content-Type: application/json; charset=UTF-8`.

**Toutes les clés décrites ci-dessous sont toujours présentes**, dans tous les états, y compris quand
une information manque. Un consommateur n'a jamais à tester l'existence d'une clé : il teste des
valeurs.

### 2.1 Enveloppe

| Clé | Type | Contenu |
|---|---|---|
| `jour` | chaîne | Le jour **demandé**, `AAAA-MM-JJ`. |
| `jour_relatif` | chaîne | `aujourd_hui` ou `demain`. |
| `jours_disponibles` | objet | `{ "aujourd_hui": "AAAA-MM-JJ", "demain": "AAAA-MM-JJ" }`. Permet de construire le sélecteur de jour **sans jamais calculer une date de Paris côté client**. |

### 2.2 `saison` — le dispositif estival

| Clé | Type | Contenu |
|---|---|---|
| `active` | booléen | Le dispositif est-il actif ce jour-là, selon le calendrier. |
| `debut`, `fin` | chaînes | Bornes du dispositif pour l'année du jour demandé. |
| `prochaine_ouverture` | chaîne | Premier jour actif à venir. **Toujours une date**, jamais `null`. |
| `confirmee` | booléen | Les bornes sont-elles confirmées par la préfecture. |

### 2.3 `fraicheur` — l'âge de la donnée

| Clé | Type | Contenu |
|---|---|---|
| `dernier_releve_le` | chaîne ISO 8601 UTC ou `null` | Instant du dernier relevé réussi de la source. |
| `dernier_releve_source` | chaîne | Clé de la source relevée. |
| `seuil_secondes` | entier | Au-delà de ce délai, la donnée est considérée périmée. |
| `perimee` | booléen | **Une bannière, jamais un filtre.** Elle se superpose aux statuts affichés et n'en masque aucun, n'en retire aucun. |
| `publie_prefecture_le` | chaîne ISO 8601 UTC ou `null` | Instant de publication préfectorale connu pour ce jour. |
| `dispositif_actif` | booléen | Le dispositif est-il actif ce jour-là. |

L'âge en secondes n'est volontairement pas transmis : il se déduit de `dernier_releve_le` et de
l'en-tête `Date` de la réponse. Une valeur qui change à chaque seconde rendrait toute revalidation de
cache inutile.

### 2.4 `synthese` — la lecture d'ensemble

| Clé | Type | Contenu |
|---|---|---|
| `etat_global` | chaîne | Même vocabulaire que `massifs[].etat` (§3). |
| `partiel` | booléen | `true` quand une partie seulement des massifs a un statut. Le dénominateur à afficher est alors `disponibles`, **jamais** `total`. |
| `total` | entier | Nombre de massifs du référentiel. |
| `disponibles` | entier | Nombre de massifs porteurs d'un statut pour ce jour. |
| `sans_donnee` | entier | `total − disponibles`. |
| `par_niveau` | objet | Nombre de massifs par clé de niveau. **Toutes les clés de la légende y figurent**, à `0` si aucun massif ne les porte. |
| `niveau_le_moins_severe` | chaîne ou `null` | Clé de niveau. |
| `niveau_le_plus_severe` | chaîne ou `null` | Clé de niveau. |

Ces valeurs sont **fournies**, jamais recalculées par le consommateur : la sémantique de la synthèse
appartient au serveur.

### 2.5 `massifs` — la liste

Liste **ordonnée**, dans l'ordre d'affichage du référentiel. **Tous les massifs y figurent toujours**,
dans tous les états : une liste raccourcie se lirait « aucune restriction ».

| Clé | Type | Contenu |
|---|---|---|
| `code` | chaîne | Identifiant stable du massif. |
| `libelle` | chaîne | Nom affichable, issu du référentiel. |
| `communes` | tableau de chaînes | Communes concernées. **Vide aujourd'hui** : l'attribut n'existe pas dans la couche source (voir `referentiel.communes_statut`). |
| `etat` | chaîne | Voir §3. |
| `jour_validite` | chaîne | Toujours identique au `jour` de l'enveloppe. |
| `niveau` | objet ou `null` | Objet **si et seulement si** `etat` vaut `disponible`. Sinon `null`. |
| `zapef` | objet ou `null` | Même forme que `niveau`, pour la dimension ZAPEF. `null` hors de `disponible`. |
| `source` | chaîne ou `null` | `recuperation_officielle` ou `saisie_manuelle`. |
| `publie_prefecture_le` | chaîne ISO 8601 UTC ou `null` | Instant de publication préfectorale. |

Forme d'un objet `niveau` (et `zapef`) :

| Clé | Type | Contenu |
|---|---|---|
| `cle` | chaîne | Clé stable du niveau. |
| `libelle` | chaîne | **Libellé officiel**, reproduit tel quel. |
| `consigne` | chaîne | Consigne officielle. **Vide aujourd'hui** : le dispositif n'en publie aucune — c'est un fait relevé, pas une donnée manquante. |
| `severite` | entier | Croissante et comparable. Ce n'est ni une identité ni un rang. |
| `motif` | chaîne | Clé de motif graphique : l'information ne repose jamais sur la couleur seule. |
| `jeton_css`, `jeton_encre_css` | chaînes | **Noms** de jetons CSS. Aucune couleur ne traverse cette frontière. |
| `rang`, `total` | entiers | Position et cardinal, pour un affichage « 2 sur 2 ». Ne se comparent jamais entre deux dates. |

### 2.6 `legende`

La légende complète : `confirmee`, `consignes_publiees`, `revision`, `source`,
`source_officielle_url`, `publication_heure`, `niveaux[]`, `zapef[]`, `zapef_note`,
`etats_hors_niveau{}`.

`confirmee` et `consignes_publiees` voyagent **toujours**. Tant que `confirmee` vaut `false`, un
libellé ne doit pas être présenté comme officiel vérifié ; tant que `consignes_publiees` vaut `false`,
aucun intitulé « Consigne » ne doit être affiché.

### 2.7 `referentiel`

| Clé | Type | Contenu |
|---|---|---|
| `nombre` | entier | Nombre de massifs servis. |
| `communes_statut` | chaîne | Drapeau de lacune. `inconnue` signifie **« nous ne savons pas »**, et non « aucune commune concernée ». |

### 2.8 `geometrie` et `emprise`

`geometrie` est un **pointeur** : les polygones ne sont pas dans cette réponse.

| Clé | Type | Contenu |
|---|---|---|
| `disponible` | booléen | Les métadonnées de l'artefact sont-elles exploitables. |
| `url` | chaîne | Adresse du GeoJSON, servie en statique depuis notre domaine, avec jeton de version. |
| `version` | chaîne | Jeton de version. |
| `sha256` | chaîne | Empreinte du fichier. |
| `octets` | entier | Taille du fichier. |
| `format` | chaîne | Format de l'artefact. |
| `zoom_max` | entier | Zoom au-delà duquel la simplification des contours devient visible. |

`emprise` porte le cadrage : `bbox` (`ouest`, `sud`, `est`, `nord`) et `centre` (`lon`, `lat`), en
EPSG:4326, ou `null` chacun si le référentiel ne les porte pas. La conversion vers l'ordre attendu par
une bibliothèque cartographique appartient au consommateur.

### 2.9 `attribution`

| Chemin | Contenu |
|---|---|
| `attribution.statuts.texte` | Mention de source des statuts, à afficher avec la donnée. |
| `attribution.statuts.carte_officielle_url` | Adresse de la carte officielle. **Voyage toujours**, dans tous les états et jusque dans les corps d'erreur. C'est le repli à proposer dès qu'un statut n'est pas disponible. |
| `attribution.statuts.bulletin_url_modele` | **Modèle** d'adresse portant le jeton `{AAAAMMJJ}`, à substituer par la date au format `AAAAMMJJ`. Le bulletin se lie, il ne se récupère ni ne se re-sert. |
| `attribution.perimetres.phrase` | Mention de source des périmètres (Licence Ouverte 2.0), à reproduire telle quelle. |
| `attribution.perimetres.lien_source`, `lien_licence` | Adresses de la source et de la licence. |

Ces phrases et ces adresses ne se rédigent jamais côté consommateur : elles sont imposées et voyagent
dans la réponse.

### 2.10 Ce que la réponse ne contient pas

- **Aucun champ `licence` pour notre agrégat.** La licence de la réutilisation de nos propres données
  n'est **pas encore tranchée** par le propriétaire du projet ; aucune valeur n'est donc émise. Les
  attributions ci-dessus concernent les sources **amont**, pas notre agrégat. C'est un fait, pas un
  oubli — le champ apparaîtra le jour où la décision sera prise.
- Aucun identifiant d'utilisateur, aucun auteur de saisie, aucune donnée personnelle.
- Aucun identifiant interne de statut, aucun instant d'enregistrement.
- Aucune valeur brute de la source (entier de niveau, code de procédure).
- Aucune géométrie ni coordonnée par massif : suivre `geometrie.url`.

---

## 3. Les états

`massifs[].etat` et `synthese.etat_global` partagent un vocabulaire **fermé de quatre valeurs**.

| Valeur | Signification | Lecture attendue |
|---|---|---|
| `disponible` | Un statut existe pour ce massif et ce jour. | `niveau` est renseigné ; l'afficher tel quel. |
| `non_encore_publie` | Rien n'est publié pour le jour demandé. Cas nominal pour « demain », avant la publication de fin d'après-midi. | « Statut de demain non encore publié ». **Jamais** « autorisé ». |
| `indisponible` | Aucune information pour ce jour. | « Information non disponible — consulter la carte officielle », avec `attribution.statuts.carte_officielle_url`. **Jamais** « aucune restriction ». |
| `hors_saison` | Le calendrier place ce jour hors dispositif, et aucune donnée n'existe. | « Dispositif estival inactif », avec la date de reprise **lue** dans `saison.prochaine_ouverture`, jamais recalculée. |

Deux situations ne sont **pas** des états et se lisent ailleurs :

- **Donnée périmée** : `fraicheur.perimee === true`. Bannière superposée, qui ne masque ni ne filtre
  aucun statut.
- **Publication partielle** : `synthese.partiel === true`. Le dénominateur affiché devient
  `synthese.disponibles`.

Un ajout d'état est un changement de version de l'API. Un consommateur robuste traite les quatre
valeurs de façon exhaustive et échoue bruyamment sur une cinquième, plutôt que de la ranger dans un
cas par défaut.

---

## 4. Erreurs

Le corps d'erreur suit la convention de l'API REST de WordPress :
`{ "code": …, "message": …, "data": { "status": …, … } }`.

| Code | HTTP | Déclencheur | `data` supplémentaire |
|---|---|---|---|
| `rest_invalid_param` | `400` | `jour` mal formé, vide, ou hors des deux jours servis | `params`, `details` |
| `massifs_jour_hors_bornes` | `400` | Le jour demandé n'est plus dans les bornes au moment de servir — notamment au passage de minuit | `jours_disponibles` |
| `massifs_jour_invalide` | `400` | Le domaine refuse la date | — |
| `massifs_api_indisponible` | `503` | Une brique de lecture du serveur est absente | `fonctions_absentes` |
| `massifs_referentiel_indisponible` | `503` | Le référentiel des massifs est illisible | — |
| `massifs_domaine_en_erreur` | `503` | Erreur inattendue pendant l'assemblage | — |
| `rest_no_route` | `404` | Chemin inconnu | — |
| Méthode non permise | `405` | `POST`, `PUT`, `PATCH`, `DELETE` | — |

`data.carte_officielle_url` est jointe **à tous les corps d'erreur** dès qu'elle est obtenable : même
en panne, le repli officiel doit pouvoir être relayé sans être écrit en dur.

Trois règles de lecture :

1. **Un référentiel vide sort en `503`, jamais en `200` avec une liste vide.** Une liste vide servie
   en `200` se lirait « aucune restriction » : c'est exactement l'erreur que ce point d'accès existe
   pour empêcher.
2. **Les états `indisponible`, `hors_saison` et `non_encore_publie` sortent en `200`.** Jamais `404`,
   jamais `204` : un corps absent se lit « rien à signaler ».
3. **Aucun message d'exception ne voyage.** Un code stable et une phrase neutre, rien de plus. Aucun
   en-tête `Retry-After` n'est émis : le délai de rétablissement n'est pas connu, et l'inventer
   mettrait une donnée fausse dans la réponse.

---

## 5. En-têtes et cache

| En-tête | Valeur |
|---|---|
| `Content-Type` | `application/json; charset=UTF-8` |
| `Cache-Control` | `no-cache` |
| `ETag` | `W/"<empreinte>"` |
| `X-Robots-Tag` | `noindex` |
| `Access-Control-Allow-Origin` | `*` |

`no-cache` — et non `no-store` : le client conserve sa copie et la revalide à chaque requête. Aucune
durée de validité n'est annoncée : elle devrait être bornée sur les secondes restant jusqu'à minuit,
faute de quoi une réponse mise en cache à 23 h 55 servirait la journée de la veille.

**Revalidation.** Renvoyer l'`ETag` reçu dans `If-None-Match` ; si la charge utile n'a pas changé, la
réponse est `304 Not Modified`. La comparaison est faible et accepte `*` ainsi qu'une liste de valeurs
séparées par des virgules.

**L'`ETag` n'est pas émis** si la requête porte `_fields`, `_jsonp` ou `_envelope` : ces trois
paramètres modifient les octets servis, et une empreinte qui ne décrit pas le corps servi est pire
qu'une absence d'empreinte.

---

## 6. Poids

Mesuré sur le référentiel réel, 25 massifs :

| Cas | Brut | Compressé (gzip) |
|---|---|---|
| Journée complète, tous les massifs avec un statut | ≈ 18,5 Ko | ≈ 1,8 Ko |
| Aucun statut disponible (`niveau: null` partout) | ≈ 7,7 Ko | ≈ 1,7 Ko |

La compression est très favorable : les mêmes objets `niveau` et `zapef` se répètent d'un massif à
l'autre. Les réponses `application/json` sont compressées par le serveur web.

---

## 7. Exemple

```sh
# Statuts du jour courant
curl -s https://exemple.tld/wp-json/massifs/v1/statuts

# Statuts de demain, avec les en-têtes
curl -si "https://exemple.tld/wp-json/massifs/v1/statuts?jour=2026-08-14"

# Revalidation : rejouer l'ETag reçu
curl -si -H 'If-None-Match: W/"…"' https://exemple.tld/wp-json/massifs/v1/statuts
```

---

## 8. Ce qu'un réutilisateur ne doit pas faire

1. **Calculer « aujourd'hui » ou « demain » lui-même.** `jours_disponibles` fait foi : la date est
   celle du fuseau `Europe/Paris`, pas celle du poste client.
2. **Composer, traduire ou reformuler** un libellé de niveau, une consigne, une couleur, une
   attribution ou une phrase de fraîcheur. Toutes voyagent dans la réponse.
3. **Afficher `niveau` ou `zapef` quand `etat` ne vaut pas `disponible`.**
4. **Afficher un statut d'un autre jour**, le mémoriser durablement, ou rejouer une réponse
   précédente.
5. **Traiter `fraicheur.perimee` comme un masque** ou un filtre.
6. **Interpréter un corps vide, un `404` ou un `5xx` comme « aucune restriction ».** C'est la lecture
   dangereuse que toute la conception de ce point d'accès cherche à rendre impossible.
7. **Écrire en dur `carte_officielle_url` ou `bulletin_url_modele`.**
8. **Tester l'existence d'une clé** : elles sont toutes toujours présentes.
9. **Demander une date passée** : c'est un `400` par construction, pas un défaut.
10. **Attendre un `ETag` en utilisant `_fields`** : il n'est pas émis dans ce cas.
