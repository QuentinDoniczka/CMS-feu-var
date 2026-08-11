# Connecteur préfecture — ingestion des statuts d'accès

Récupère, valide et met en cache le fichier de statuts quotidiens publié par la
préfecture des Bouches-du-Rhône. Il **stocke des jetons source bruts** : il ne
traduit jamais un entier en libellé, en couleur ou en sévérité. Toute la
sémantique appartient au domaine (`includes/domain/`).

Raison : `design-system/MASTER.md` §4.1 est marqué `À CONFIRMER`, et le §4.2 du
brief interdit d'inventer la légende. Figer une énumération de niveaux ici
figerait une invention.

---

## Câblage (une ligne)

```php
require_once MASSIFS_CORE_CHEMIN . 'includes/ingest/prefecture/bootstrap.php';
```

C'est tout. `bootstrap.php` requiert les onze classes dans l'ordre de dépendance
puis appelle lui-même :

```php
\Massifs\Ingest\Prefecture\Bootstrap::register();
```

`register()` est idempotent (`Bootstrap::is_registered()` le confirme) : un
double `require` ne double pas les crochets.

L'inclusion **ne produit aucune sortie, n'écrit aucune option et n'émet aucun
appel réseau**. Tant que la ligne ci-dessus n'existe pas, ce sous-arbre est
totalement inerte et ne peut pas casser le site.

### Deux points d'attention pour le chargeur de l'extension

1. **L'autoloader de l'extension ne doit PAS mapper `Massifs\Ingest\Prefecture\*`.**
   Les fichiers suivent la convention WPCS `class-*.php`, délibérément non
   PSR-4 : ce sous-arbre se charge lui-même.
2. **Constantes optionnelles**, lues si elles existent, jamais requises :
   - `MASSIFS_CORE_CHEMIN` (ou `MASSIFS_CORE_DIR`) — répertoire de l'extension,
     avec barre oblique finale. Utilisée uniquement dans la ligne de `require`
     ci-dessus, donc jamais lue par le connecteur lui-même.
   - `MASSIFS_CORE_FICHIER` (ou `MASSIFS_CORE_FILE`) — chemin du fichier
     principal, pour `register_deactivation_hook()`. **Les deux nommages sont
     acceptés** : le chargeur de l'extension emploie le nommage français, le
     contrat initial annonçait le nommage anglais. À défaut, le chemin est
     déduit de la position du connecteur — mais ce repli est moins sûr : si
     l'extension était déplacée, un événement cron orphelin survivrait à sa
     désactivation.

---

## Façade publique

`Connector` est la **seule** classe que le reste de l'extension a le droit de
nommer. Tout le reste est de l'implémentation.

```php
use Massifs\Ingest\Prefecture\Connector;

Connector::snapshot_for( string $date_iso ): ?array
Connector::has_snapshot_for( string $date_iso ): bool
Connector::state(): array
Connector::attribution(): array
Connector::validate_payload( array $massifs, string $date_iso ): true|WP_Error
Connector::run_now( string $date_iso ): true|WP_Error
Connector::mode(): string
```

`$date_iso` est **toujours** au format `YYYY-MM-DD` et **toujours obligatoire**.

Il n'existe volontairement **aucun accesseur « dernier instantané »**. Un
`latest()` finirait par servir le statut de la veille comme s'il était courant le
jour où la récupération échoue — ce que le §4.2 du brief interdit. L'absence
d'instantané pour la date demandée est une réponse : `null`, que le domaine
traduit en « information non disponible, consultez la carte officielle ».

### Échappement

Les valeurs `brut`, `source_url` et les messages d'erreur sont d'**origine
tierce**. Tout consommateur qui les affiche doit les échapper lui-même
(`esc_html`, `esc_url`, `esc_attr`). Le connecteur ne produit aucun HTML.

### `attribution()`

```php
array(
    'texte'        => "D'après les publications de la préfecture des Bouches-du-Rhône",
    'url_carte'    => 'https://www.risque-prevention-incendie.fr/13',
    'url_bulletin' => '…/{date}.pdf',
)
```

`url_bulletin` est un **modèle** contenant le jeton `{date}`, pas un lien prêt à
poser : le bulletin n'existe que pour une date effectivement publiée. Le
consommateur substitue lui-même `{date}` par un `Ymd`, et seulement pour une date
dont il possède un instantané.

### `validate_payload()`

Le portail de saisie manuelle passe par **exactement la même validation** que le
cron : le tableau fourni est ré-encodé en JSON et traverse les cinq couches.
Forme attendue, identique à celle de la source :

```php
array( '131' => array( 2, 0 ), '132' => array( 2, 0 ), /* … */ );
```

Deux entiers, jamais des chaînes numériques ni des flottants : un changement de
type est un signal, donc un rejet.

Le contrôle de taille minimale de la couche transport (200 octets) s'applique
aussi à cette forme ré-encodée. Le référentiel réel (27 massifs) produit un
corps d'environ 360 octets, largement au-dessus du seuil ; un référentiel
volontairement réduit en test devrait en tenir compte.

---

## Stockage

Trois options, toutes en `autoload = false`, **aucune table personnalisée**,
aucun crochet d'activation.

| Option | Contenu |
|---|---|
| `massifs_prefecture_snapshots` | Carte `Ymd` → instantané. Élaguée à l'écriture (`conserver_jours`, 7 par défaut) ; aujourd'hui et demain ne sont jamais élagués. |
| `massifs_prefecture_etat` | Tentatives, réussites, échecs consécutifs, verrous d'alerte, journal FIFO de 20 entrées. |
| `massifs_prefecture_reglages` | Mode, listes blanches, référentiel attendu, fenêtre de publication. **Amorcée paresseusement au premier usage**, jamais à l'inclusion ni à l'activation. |

La carte d'instantanés n'est pas un instantané unique : à 18 h le jour J on
obtient J+1 alors que J est toujours le statut courant. Les deux coexistent.

L'unité de persistance est **une option**, écrite par un seul `update_option`
après validation complète : aucune écriture partielle n'est représentable.

Les valeurs par défaut des réglages sont un **relevé du 2026-08-11, non
officiel**, et sont commentées comme telles dans `class-settings.php`.

---

## Planification

Crochet `massifs_prefecture_recuperation`, récurrence **`hourly`** — récurrence
**native** de WordPress, aucun filtre `cron_schedules` n'est ajouté.

Pourquoi horaire et non un créneau quotidien à 18 h :

1. `wp_schedule_event` stocke un horodatage UTC ; un créneau fixé une fois en
   heure de Paris dérive au changement d'heure, et toute la saison du dispositif
   est en heure d'été ;
2. WP-Cron se déclenche au passage d'un visiteur : un créneau étroit peut être
   manqué en entier sur un site peu fréquenté ;
3. la récurrence horaire rend la reprise sur échec (§4.5 du brief) gratuite,
   sans aucune boucle bloquante dans la requête d'un visiteur.

L'évènement est posé sur `init` (priorité 20) et **auto-réparateur** : s'il a été
perdu, il se repose de lui-même. Il est retiré à la désactivation de l'extension,
et dès que le mode passe à `manuel` ou que le coupe-circuit est actif.

### Hébergement (o2switch)

WP-Cron dépend du trafic. En production, poser dans `wp-config.php` :

```php
define( 'DISABLE_WP_CRON', true );
```

puis une tâche cron système toutes les 15 minutes :

```
*/15 * * * * /usr/bin/php /home/<compte>/public_html/wp-cron.php >/dev/null 2>&1
```

Sans cela, la fenêtre de publication du soir peut être entièrement manquée.

### Vérification manuelle

```bash
wp cron event list
wp cron event run massifs_prefecture_recuperation
```

---

## Fenêtre de publication et saison

- **Fenêtre** : de 16 h à 23 h (Europe/Paris). La page de la préfecture annonce
  « vers 18 heures », la carte « à 18h », mais le bulletin porte « 17h00 » et
  l'en-tête HTTP `Last-Modified` confirme 17 h 00 Paris. Démarrer à 16 h coûte
  deux 404 inoffensifs et couvre une publication en avance.
- **Saison** : 1er juin – 30 septembre inclus, évaluée sur la **date de
  validité visée**, jamais sur « maintenant ». Le 31 mai à 18 h la cible est le
  1er juin, donc en saison. Le 30 septembre à 18 h la cible est le 1er octobre,
  donc hors saison : aucun appel, aucune alerte.

Un **404 est le signal normal « pas encore publié »**, pas une erreur : il
n'incrémente pas le compteur d'échecs consécutifs.

---

## Alertes (§4.5 du brief)

- **Fenêtre close** — une fois, à partir de l'heure de fin de fenêtre, si la
  saison est active et que le lendemain n'est toujours pas couvert.
- **Rejet** — immédiate, une fois par date, quand un HTTP 200 échoue à la
  validation. Classe d'évènement différente : la forme de la source a changé, un
  humain doit regarder aujourd'hui.

Verrou par date **et** par type : une alerte, jamais une par tentative.
Destinataires : `admin_email` par défaut, filtrables. Texte brut, `wp_mail`.

Le corps rappelle explicitement que le site affiche « information non
disponible, consultez la carte officielle » — et **pas** une donnée périmée.

---

## Coupe-circuit et profil de test

`Settings::is_disabled()` renvoie vrai si :

- `MASSIFS_PREFECTURE_DISABLE` est définie à une valeur vraie, **ou**
- `wp_get_environment_type()` vaut `local` ou `development` **et**
  `MASSIFS_PREFECTURE_JSON_URL_TEMPLATE` n'a pas été redéfinie.

Conséquence directe : **une stack Docker non configurée n'émet jamais d'appel
sortant réel** (exigence de `docker/README.md`). Il n'y a rien à désactiver à la
main.

### Pointer le connecteur sur des bouchons locaux

Le service nginx `tiles` de la stack sert déjà des fichiers statiques : il suffit
d'y déposer les bouchons. **Aucun nouveau service Docker n'est nécessaire.**

1. Déposer les fichiers dans `docker/tiles/data/stubs/prefecture/` :
   `20260811.json`, `20260812.json`, … (contenu au format exact de la source).
2. Dans `wp-config.php` de la stack :

```php
define( 'MASSIFS_PREFECTURE_JSON_URL_TEMPLATE', 'http://tiles/stubs/prefecture/{date}.json' );
define( 'MASSIFS_PREFECTURE_TIMEZONE', 'Europe/Paris' );
```

Redéfinir le modèle lève le coupe-circuit d'environnement : le connecteur
appelle alors le service `tiles`, à l'intérieur du réseau Docker, jamais un
domaine tiers.

Pour couper totalement, quel que soit l'environnement :

```php
define( 'MASSIFS_PREFECTURE_DISABLE', true );
```

Un fichier absent côté bouchon produit un 404, c'est-à-dire exactement le
scénario « pas encore publié ».

---

## Constantes reconnues

Toutes optionnelles, toutes sous `defined()`.

| Constante | Défaut |
|---|---|
| `MASSIFS_PREFECTURE_JSON_URL_TEMPLATE` | `https://www.risque-prevention-incendie.fr/static/13/import_data/{date}.json` |
| `MASSIFS_PREFECTURE_PDF_URL_TEMPLATE` | idem en `.pdf` |
| `MASSIFS_PREFECTURE_HTTP_TIMEOUT` | `10` (borné à 1–30 s) |
| `MASSIFS_PREFECTURE_USER_AGENT` | `MASSIFS/1.0 (+<home_url>; <admin_email>)` |
| `MASSIFS_PREFECTURE_TIMEZONE` | `Europe/Paris` |
| `MASSIFS_PREFECTURE_DISABLE` | non définie |

`{date}` est le **seul** jeton reconnu. Il n'est substitué qu'après validation
stricte contre `/^\d{8}$/`, et l'URL finale passe par `esc_url_raw()`.

`sslverify` est **ré-imposé à `true` après le filtre** `massifs_prefecture_http_args` :
la vérification TLS n'est pas une option de confort.

---

## Filtres

| Filtre | Arguments |
|---|---|
| `massifs_prefecture_json_url` | `$url`, `$date_ymd` |
| `massifs_prefecture_http_args` | `$args`, `$url` |
| `massifs_prefecture_reglages` | `$reglages` |
| `massifs_prefecture_niveaux_autorises` | `$niveaux` |
| `massifs_prefecture_procedures_autorisees` | `$procedures` |
| `massifs_prefecture_massifs_attendus` | `$codes` |
| `massifs_prefecture_est_en_saison` | `$bool`, `$date_ymd` |
| `massifs_prefecture_fenetre_publication` | `array{debut:int,fin:int}` |
| `massifs_prefecture_valider_payload` | `true\|WP_Error`, `$instantane`, `$date_ymd` |
| `massifs_prefecture_alerte_destinataires` | `$emails` |
| `massifs_prefecture_conserver_jours` | `$jours` |

Chaîne de résolution des réglages, invariable :
**constante > passerelle `function_exists` > option > défaut d'investigation >
filtre (dernier mot)**. Toute valeur lue depuis une option est ré-assainie : une
option est modifiable depuis l'administration, ce n'est pas une source de
confiance.

Passerelles attendues des chaînes voisines, utilisées si elles existent :

- `massifs_referentiel_codes_source()` — chaîne #2 (référentiel) ;
- `massifs_niveaux_source_autorises()` et `massifs_procedures_source_autorisees()`
  — chaîne #3 (modèle de statuts).

## Actions

| Action | Arguments |
|---|---|
| `massifs_prefecture_snapshot_enregistre` | `$instantane` |
| `massifs_prefecture_echec` | `WP_Error $erreur`, `array $etat` |
| `massifs_prefecture_tentative` | `$date_ymd`, `$declencheur` |

`massifs_prefecture_snapshot_enregistre` est l'**unique couture d'intégration**.
Ce connecteur ne projette jamais dans un modèle de statut, n'invalide aucun cache
de page et ne touche à aucune option d'une autre chaîne. C'est au domaine de s'y
abonner.

---

## Ce qui n'est PAS une aberration

Ces trois cas sont documentés dans `class-validator.php` et ne doivent **jamais**
être réintroduits comme motifs de rejet :

1. **Tous les massifs au niveau le plus sévère** — c'est le jour de canicule où
   l'information compte le plus. Le rejeter afficherait « information non
   disponible » précisément ce jour-là.
2. **Un lot identique à celui de la veille** — c'est le cas nominal en juin.
3. **Un saut d'amplitude de niveau**, quelle qu'en soit la hauteur.

Le seul rapprochement par hachage du connecteur sert à distinguer « pas encore
publié » d'une vraie publication, jamais à rejeter une donnée.
