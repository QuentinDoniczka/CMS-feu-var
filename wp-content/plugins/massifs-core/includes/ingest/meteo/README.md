# Connecteur météo — indicateur de danger météo des forêts

Récupère, valide et met en cache l'indicateur quotidien de danger météo des
forêts. Il **stocke un jeton source brut** : il ne traduit jamais une valeur en
libellé, en couleur ou en sévérité. La traduction n'a lieu qu'à la **lecture**,
et seulement si le vocabulaire officiel est sourcé — ce qu'il n'est pas.

Raison, écrite une fois pour toutes : les libellés des crans et la cardinalité
de l'échelle ne sont **établis nulle part dans le dépôt**. Les figer ici
figerait une invention.

---

## Ce que ce module rend aujourd'hui, sans exception

`etat = 'indisponible'`, `niveau = null`, `echelle.crans = 0`.

**Quelle que soit la charge reçue et mise en cache.** La garde est dans notre
code, pas dans la source : un bouchon bavard qui injecterait un libellé et une
cardinalité ne peut pas la contourner. C'est le cas nominal de recette, pas un
état dégradé.

---

## Câblage

Aucun. Le chargeur de l'extension découvre les modules **par convention** :
pour chaque couche, il charge `<couche>/<module>/module.php` ou, à défaut,
`<couche>/<module>/bootstrap.php`. Ce module est donc chargé dès l'instant où
`bootstrap.php` existe, et **aucune ligne n'est à ajouter dans
`massifs-core.php`**.

`bootstrap.php` requiert les quatorze fichiers dans l'ordre de dépendance, puis
enregistre les crochets. L'inclusion **ne produit aucune sortie, n'écrit aucune
option et n'émet aucun appel réseau**. Elle est idempotente : un double
`require` ne double pas les crochets.

L'autoloader PSR-4 de l'extension ne résout pas `Massifs\Ingest\Meteo\*` : les
fichiers suivent la convention WPCS `class-*.php`, délibérément non PSR-4 — ce
sous-arbre se charge lui-même. Nommer l'espace de noms sans que le répertoire
existe reste sans effet, l'autoloader étant gardé par `is_file()`.

---

## Surface publique — une fonction, une seule

```php
massifs_meteo_du_jour( ?string $jour = null ): array
```

**TOTALE** : aucune exception, aucun `null`, aucun `false`, aucun `WP_Error`, et
toutes les clés toujours présentes. Le consommateur n'écrit jamais `isset()` ni
`??` sur une clé du contrat. Un `$jour` malformé rend `etat = 'indisponible'` et
`jour = ''` — il ne lève pas.

| Clé | Type | Remarque |
|---|---|---|
| `jour` | `string` | toujours le jour **demandé** ; `''` si malformé |
| `etat` | `string` | liste FERMÉE : `disponible`, `indisponible`, `non_encore_publie` |
| `niveau` | `array\|null` | `null` **littéral** hors de `disponible` ; sinon `cle` + `libelle` |
| `echelle` | `array` | `crans`, `atteint`, `confirmee`, `phrase` |
| `zone` | `array` | `cle`, `libelle`, `granularite` (liste fermée) |
| `releve_le` | `string\|null` | dernier relevé **réussi** de la source, ISO 8601 UTC |
| `publie_le` | `string\|null` | publication déclarée **pour ce jour** |
| `distinction` | `string` | verbatim, **toujours non vide, dans tous les états** |
| `attribution` | `array` | `texte`, `lien_licence`, `lien_source` |

`rang` **ne traverse pas la frontière** : il reste interne à
`vocabulaire.config.php`, et c'est `echelle.atteint` qui porte la valeur.

Le retour est **brut, non échappé**. `niveau.libelle` est d'origine tierce : il
s'échappe (`esc_html`) chez le consommateur, jamais ici — ce module ne produit
aucun HTML.

### Il n'y a pas de seconde fonction

Ni `massifs_danger_meteo()`, ni `massifs_attribution_meteo()`, ni
`massifs_meteo_disponible()`, ni `massifs_meteo_niveau()`. `etat`, `niveau` et
`attribution` sont déjà des clés ; une seconde manière de poser la même question
est une divergence en attente.

### Les trois états

| État | Émis quand |
|---|---|
| `disponible` | instantané validé pour ce jour **ET** vocabulaire confirmé **ET** un cran correspond au jeton source. **Inatteignable aujourd'hui.** |
| `indisponible` | aucun instantané pour ce jour ; ou vocabulaire non confirmé ; ou jeton sans correspondance ; ou jour malformé ; ou hors période d'exploitation |
| `non_encore_publie` | jour demandé = demain, aucun instantané |

`hors_saison` **n'existe pas** pour la météo et ne doit pas être créé :
affirmer que la source ne publie pas hors du dispositif préfectoral serait
inventer un fait de domaine sur une source tierce. `donnee_perimee` n'existe pas
non plus — un instantané n'est servi que pour son propre jour de validité, il
n'y a donc aucun état entre « courant » et « absent ».

---

## Façade interne

`Connector` est la **seule** classe que le reste de l'extension a le droit de
nommer. Le thème, lui, n'en nomme aucune.

```php
use Massifs\Ingest\Meteo\Connector;

Connector::snapshot_for( string $date_iso ): ?array
Connector::has_snapshot_for( string $date_iso ): bool
Connector::state(): array
Connector::attribution(): array
Connector::run_now( string $date_iso ): true|WP_Error
Connector::mode(): string
```

**Il n'existe aucun `latest()`, ni aucun accesseur « dernier instantané ».** Il
serait immédiatement employé pour afficher un indicateur, et le jour où la
récupération échoue il servirait celui de la veille comme s'il était courant.
Toute lecture exige une date ; l'absence de réponse pour cette date **est** une
réponse. La recette vérifie cette absence par `method_exists()`.

`run_now()` porte trois refus, tous **avant le moindre octet réseau** :
utilisateur connecté sans `manage_options`, coupe-circuit actif, date hors
aujourd'hui/demain. Un quatrième vit dans le runner, seul entonnoir vers le
réseau : hors période d'exploitation.

Ce module n'expose **aucune route REST**, aucun formulaire, aucun écran, aucune
table, aucun rôle, aucune capability, aucun crochet d'activation.

---

## La garde de vocabulaire

C'est le cœur du module. Elle vit dans `vocabulaire.config.php` et
`class-vocabulaire.php`.

`Vocabulaire::est_confirme()` ne rend vrai que si **tout** ce qui suit tient :

- `confirme === true` ; **et**
- la table de crans est non vide ; **et**
- chaque cran porte `cle` (forme `/^[a-z0-9_-]{1,32}$/`), `libelle` non vide et
  `rang` entier ; **et**
- les rangs sont distincts et contigus depuis 1 ; **et**
- la correspondance source → cran est non vide et ne pointe que sur des crans
  existants.

**Basculer le booléen seul n'ouvre rien.** Le filtre `massifs_meteo_vocabulaire`
est **ré-validé après application** : un filtre n'ouvre jamais la garde à lui
seul. **Aucune constante d'ouverture n'est offerte**, et il ne faut pas en
créer — elle permettrait d'ouvrir sans fournir un seul libellé.

La cardinalité de l'échelle est lue **là et nulle part ailleurs**. Aucun autre
fichier du module ne connaît, ne suppose ni ne borne le nombre de crans ;
`Vocabulaire::crans()` en est la seule source, et elle rend `0` aujourd'hui.

**Comment cette garde s'ouvre** : par une source **écrite** du propriétaire du
projet donnant, verbatim, le libellé officiel de chaque cran et l'ordre de
l'échelle. Jamais en la déduisant d'un bouchon.

---

## Stockage

Trois options, toutes en `autoload = false`, **aucune table personnalisée**,
aucun crochet d'activation.

| Option | Contenu |
|---|---|
| `massifs_meteo_snapshots` | carte `Ymd` → instantané. Élaguée à l'écriture ; aujourd'hui et demain ne sont jamais élagués |
| `massifs_meteo_etat` | tentatives, réussites, échecs consécutifs, verrous d'alerte, journal FIFO de 20 entrées |
| `massifs_meteo_reglages` | mode, liste blanche d'entrée, zone. **Amorcée paresseusement au premier usage**, jamais à l'inclusion |

L'unité de persistance est **une option**, écrite par un seul `update_option`
après validation complète : aucune écriture partielle n'est représentable.

Le module ne pose **aucun transient** et n'invalide aucun cache : la lecture va
droit à l'option. Il n'y a donc rien qui puisse rester périmé côté cache. Un
consommateur qui mettrait en cache s'abonne à `massifs_meteo_snapshot_enregistre`.

---

## Fraîcheur

Tout le couplage au domaine « fraîcheur » tient dans **un seul fichier**,
`class-releve.php` :

- écriture par `massifs_enregistrer_releve_reussi( 'meteo' )`, **uniquement**
  après une charge validée couvrant la date — jamais sur un 404, un rejet ou un
  échec réseau, sinon la fraîcheur mentirait ;
- lecture par `RegistreReleves::dernier_releve( 'meteo' )`.

`massifs_fraicheur()` n'est **pas** employée : sa valeur ajoutée est `perimee`,
calculée sur un seuil qui est une règle **des statuts** et n'existe pour le
danger météo dans aucune source.

L'honnêteté vient d'ailleurs, et plus fort : un instantané n'est courant que
**pour son propre jour de validité**. `releve_le` voyage comme **fait**, jamais
comme autorisation d'afficher.

---

## Planification

Crochet `massifs_meteo_recuperation`, récurrence **`hourly`** — récurrence
**native** de WordPress, aucun filtre `cron_schedules` n'est ajouté. L'évènement
est posé sur `init` (priorité 20), **auto-réparateur**, retiré à la désactivation
de l'extension et dès que le mode passe à `manuel` ou que le coupe-circuit est
actif.

**Aucune fenêtre de publication**, et c'est délibéré : l'heure à laquelle la
source publie n'est établie nulle part, et en inventer une ferait déclencher des
alertes sur une heure fausse — donc apprendrait au gestionnaire à les ignorer.

**Aucun couplage au cron d'une chaîne sœur** : se brancher sur le crochet du
connecteur préfecture ferait dépendre la météo d'une fenêtre qui n'est pas la
sienne, et rendrait chaque chaîne capable de casser l'autre.

Deux dates au plus par exécution — aujourd'hui et demain — donc deux appels
sortants au plus, quelle que soit la fréquence de déclenchement.

---

## Réseau, coupe-circuit et bouchons

`class-fetcher.php` est le **seul** fichier du module autorisé à émettre un
octet. `wp_remote_get` uniquement, temporisation bornée 1–30 s, `sslverify`
**ré-imposé à `true` après** le filtre `massifs_meteo_http_args` — qui est aussi
le point d'accroche prévu pour un futur en-tête d'authentification. Une clé
d'API se pose là, côté serveur : elle ne traverse jamais une réponse ni le DOM.

**Aucune boucle de reprise, aucun `sleep()`** : la récurrence horaire EST la
politique de reprise.

### Coupe-circuit — plus strict que celui du connecteur préfecture

`Settings::is_disabled()` rend vrai si :

- `MASSIFS_METEO_DISABLE` est définie à une valeur vraie, **ou**
- `MASSIFS_METEO_JSON_URL_TEMPLATE` **n'est pas définie — dans TOUS les
  environnements, production comprise.**

La constante n'a **aucune valeur par défaut**. Le point d'entrée réel de l'API
n'est pas connu et ne se déduit pas ; une URL par défaut inventée serait le pire
des deux mondes — un appel sortant vers une adresse fausse, en production. Sans
la constante, le module ne peut **structurellement** pas émettre un octet.

### Pointer le connecteur sur les bouchons

Voir `data/meteo/bouchons/README.md`. Le répertoire est servi par notre propre
serveur ; le connecteur y fait donc un **vrai aller-retour HTTP**, de même
origine, à travers le vrai tuyau. Un fichier absent produit un 404, c'est-à-dire
exactement le scénario « pas encore publié » — à une réserve près, propre à la
stack de développement et documentée dans ce README-là : sans en-tête `Host`
canonique, un fichier absent y déclenche un 301 de WordPress au lieu d'un 404.

Le format du bouchon est **le nôtre**, déclaré comme tel et versionné
(`schema: 1`). Il n'est **jamais** présenté comme une imitation du format réel,
qui est inconnu. Le jour où ce format sera connu, **seule la couche `forme` du
validateur change**.

---

## Constantes reconnues

Toutes optionnelles sauf indication, toutes sous `defined()`.

| Constante | Défaut |
|---|---|
| `MASSIFS_METEO_JSON_URL_TEMPLATE` | **aucun** — son absence désarme le module |
| `MASSIFS_METEO_HTTP_TIMEOUT` | `10` (borné à 1–30 s) |
| `MASSIFS_METEO_USER_AGENT` | `MASSIFS/1.0 (+<home_url>; <admin_email>)` |
| `MASSIFS_METEO_TIMEZONE` | `Europe/Paris` |
| `MASSIFS_METEO_DISABLE` | non définie |

`{date}` est le **seul** jeton reconnu dans le modèle d'URL. Il n'est substitué
qu'après validation stricte contre `/^\d{8}$/`, et l'URL finale passe par
`esc_url_raw()`.

---

## Validation — cinq couches

`transport`, `forme`, `référentiel`, `sémantique`, `temporel`. Chaque rejet
porte sa `couche` dans les données de l'erreur.

**La couche sémantique ne consulte JAMAIS `Vocabulaire`.** Une charge dont le
niveau n'a aucun libellé connu reste **valide et mise en cache** ; c'est la
couche de **lecture** qui refuse de la servir. C'est ce qui permet d'exercer
réellement, dès aujourd'hui, le cache, la fraîcheur, les alertes et la reprise —
au lieu d'un module inerte qui ne prouverait rien.

Aucun message d'erreur ne porte de valeur de niveau : les messages voyagent
jusque dans les alertes courriel, et un chiffre de danger dans un courriel serait
l'information que le site refuse d'afficher, transmise par une porte de service.
La valeur brute reste dans `detail`, structuré.

### Ce qui n'est PAS une aberration

À ne **jamais** réintroduire comme motif de rejet :

1. un niveau **au maximum** de l'échelle — c'est le jour où l'information compte
   le plus ;
2. une valeur **identique à celle de la veille** — cas nominal d'un épisode
   stable ;
3. un **saut d'amplitude** quelconque.

Le hachage ne provoque **jamais** de rejet : il évite une réécriture pour la
**même** date, et il journalise. Le seul signal de non-publication est le
**404**, qui n'incrémente pas le compteur d'échecs et ne déclenche aucune alerte.

---

## Alertes

Deux types, verrou **par date et par type** : une alerte, jamais une par
tentative.

- **`panne`** — au-delà du seuil d'échecs consécutifs (`3` par défaut), la source
  restant injoignable, en erreur, ou répondant une charge refusée.
- **`rejet`** — immédiate, une fois par date, quand un HTTP 200 échoue à la
  validation. Classe d'évènement différente : la forme de la source a changé, un
  humain doit regarder aujourd'hui.

Destinataires : `admin_email` par défaut, filtrables. Texte brut, `wp_mail`.
**Aucun corps d'alerte ne porte une valeur de niveau.** Le corps dit
explicitement ce que le site affiche : l'indicateur reste « indisponible », et
surtout pas la valeur de la veille.

---

## Filtres

| Filtre | Arguments |
|---|---|
| `massifs_meteo_vocabulaire` | `$vocabulaire` — **ré-validé après application** |
| `massifs_meteo_reglages` | `$reglages` |
| `massifs_meteo_json_url` | `$url`, `$date_ymd` |
| `massifs_meteo_http_args` | `$args`, `$url` |
| `massifs_meteo_niveaux_source_autorises` | `$valeurs` |
| `massifs_meteo_saison_operationnelle` | `$bool`, `$date_ymd` |
| `massifs_meteo_valider_payload` | `true\|WP_Error`, `$instantane`, `$date_ymd` |
| `massifs_meteo_alerte_destinataires` | `$emails` |
| `massifs_meteo_conserver_jours` | `$jours` |
| `massifs_meteo_seuil_alerte_echecs` | `$seuil` |

Chaîne de résolution des réglages, invariable :
**constante > option > défaut > filtre (dernier mot)**. Toute valeur lue depuis
une option est ré-assainie : une option est modifiable depuis l'administration,
ce n'est pas une source de confiance.

## Actions

| Action | Arguments |
|---|---|
| `massifs_meteo_snapshot_enregistre` | `$instantane` |
| `massifs_meteo_echec` | `WP_Error $erreur`, `array $etat` |
| `massifs_meteo_tentative` | `$date_ymd`, `$declencheur` |

`massifs_meteo_snapshot_enregistre` est l'**unique couture d'intégration**. Ce
module ne projette rien dans un autre modèle, n'invalide aucun cache de page et
ne touche à aucune option d'une autre chaîne.

---

## Période d'exploitation — une porte, pas une affirmation

Hors période, le module n'émet **ni octet réseau ni alerte**, et la lecture rend
`indisponible`. Il n'affirme **rien** au visiteur : nous ne savons pas si la
source publie hors du dispositif préfectoral, et l'affirmer serait inventer un
fait de domaine sur une source tierce.

La période est celle de `massifs_saison()` si cette fonction existe. **Si elle
est absente, la porte ne s'applique pas et le module procède** : un module frère
manquant ne doit pas en éteindre un autre.

---

## Questions ouvertes, jamais comblées par déduction

1. **Format réel de la réponse de l'API** : point d'entrée, authentification,
   forme JSON, nom et type du champ de niveau. Bloquant avant bascule vers le
   connecteur réel.
2. **Libellés officiels des crans et cardinalité de l'échelle.** *Bloquant avant
   tout affichage d'un niveau.* Se comble par une source écrite du propriétaire,
   jamais par un bouchon. La table reste vide, l'indicateur reste
   `indisponible`.
3. **Découpage géographique** : le bloc `zone` porte déjà sa `granularite` dans
   une liste fermée ; il n'y a qu'une valeur à changer. À trancher avant toute
   publication par zone.
4. **Mention de source et licence** : l'URL canonique de la licence n'est pas
   vérifiée, et il n'est pas établi que la source exige une mention plus
   spécifique que celle du §9 du brief. `lien_licence` et `lien_source` restent
   vides — une URL plausible mais fausse serait une invention, pas un lien.
