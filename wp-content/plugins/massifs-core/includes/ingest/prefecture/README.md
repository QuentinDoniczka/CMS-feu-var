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

C'est tout. `bootstrap.php` requiert les douze classes dans l'ordre de dépendance
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
| `massifs_prefecture_snapshots` | Carte `Ymd` → instantané, plus sa clé `projection`. Élaguée à l'écriture (`conserver_jours`, 7 par défaut) ; aujourd'hui et demain ne sont jamais élagués. |
| `massifs_prefecture_etat` | Tentatives (globale **et** carte par date), re-contrôles par date, réussites, échecs consécutifs, verrous d'alerte, journal FIFO de 20 entrées. |
| `massifs_prefecture_reglages` | Mode, listes blanches, référentiel attendu, fenêtre de publication. **Amorcée paresseusement au premier usage**, jamais à l'inclusion ni à l'activation. |

La carte d'instantanés n'est pas un instantané unique : à 18 h le jour J on
obtient J+1 alors que J est toujours le statut courant. Les deux coexistent.

L'unité de persistance est **une option**, écrite par un seul `update_option`
après validation complète : aucune écriture partielle n'est représentable.

Les valeurs par défaut des réglages sont un **relevé du 2026-08-11, non
officiel**, et sont commentées comme telles dans `class-settings.php`.

### La clé `projection` d'un instantané

Chaque instantané porte l'issue de sa projection par le domaine. C'est ce qui
permet de distinguer « la donnée n'a pas été récupérée » de « la donnée est en
cache mais personne n'a réussi à l'écrire ».

```php
'projection' => array(
    'resultat'  => 'inconnue'|'complet'|'partiel'|'rejete'|'sans_projecteur',
    'le'        => string|null,  // ISO 8601 UTC
    'motif'     => string,       // motif du bilan, tronqué à 300 caractères
    'rejeux'    => int,          // rejeux déjà consommés pour cette date
    'rejeux_le' => string,       // `Ymd` du jour auquel le compteur appartient
)
```

**Aucune migration, aucun bump de schéma.** `SnapshotRepository::all()` conserve
les clés inconnues : un instantané écrit avant l'introduction de cette clé se
relit tel quel, et **l'absence totale de la clé se lit `inconnue`**. La lecture
normalisée passe par `SnapshotRepository::projection( string $date_ymd ): array`,
qui rend toujours les cinq clés.

L'écriture est **ciblée** : `SnapshotRepository::update_projection()` relit la
carte, ne touche que la clé `projection` de la date visée, fusionne les clés
fournies sur l'état courant, et réécrit l'option. Elle **ne crée jamais de
date** : sur une date sans instantané, elle rend `false` sans rien écrire.

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

## Re-contrôle, rejeu, et états terminaux

### Pourquoi une date déjà couverte reste candidate

La préfecture peut republier en cours de journée. Le modèle de statuts est
append-only et absorbe parfaitement une correction — mais le connecteur, qui
écartait toute date déjà instantanée, n'en livrait jamais une. Une date couverte
reste donc candidate, sous conditions cumulatives :

- elle est **aujourd'hui ou demain** et **en saison** ;
- le dernier re-contrôle remonte à plus de `RECONTROLE_SECONDES` ;
- la **borne quotidienne** `RECONTROLES_MAX_PAR_JOUR` n'est pas épuisée ;
- le garde `ANTI_RAFALE_SECONDES` est franchi.

`SourceCalendar::pending_dates()` **nomme** les dates candidates ; le `Runner`
**décide** du travail. La politique vit à un seul endroit — la duplication entre
les deux est ce qui rendait le défaut invisible.

### Ce qui déclenche une ré-émission

Un instantané n'est ré-émis vers le domaine que si **le corps a réellement
changé**, ou si **la projection précédente a échoué**. Jamais sur la seule foi
d'un passage : le dépôt de statuts ne déduplique pas, donc chaque ré-émission
acceptée ajoute une ligne d'historique par massif, et l'écran Historique est un
livrable produit. Un corps inchangé dont la projection est `complet` **ne se
rejoue jamais**.

Le court-circuit « corps identique » **journalise son passage** (`succes`, avec
une note distincte). Il ne sort plus en silence.

### Un rejeu ne révoque jamais une décision humaine plus récente

Le statut courant est résolu par **la dernière écriture**, sans préséance de
source, et le portail écrit dans la même table que la projection. Un rejeu
ré-émet un corps **ancien** : sans garde, une correction saisie au portail entre
la publication et le rejeu cesserait d'être courante, sans alerte, alors que la
donnée rejouée n'apporte aucune information nouvelle.

`Runner::rejeu_du()` s'abstient donc dès qu'il existe, pour ce jour de validité,
une écriture de source `saisie_manuelle` **postérieure au `recupere_le` de
l'instantané**. Un rejeu est une **reprise technique**, pas un arbitrage.

Le **re-contrôle réseau n'est pas concerné** : son corps est réellement plus
frais, et la préséance de la source officielle s'y défend.

Le garde lit le domaine par `massifs_statuts_du_jour()` — jamais par
`massifs_journal_statuts()`, réservée au portail — et n'y écrit rien. Tout est
gardé par `function_exists()` : sans domaine, aucune saisie manuelle n'est
possible, donc le garde ne bloque pas. En cas de doute (horodatage illisible,
date incohérente), il **s'abstient** : sur une donnée de sécurité, l'échec fermé
est le bon défaut.

**Une seule porte.** La politique de rejeu est interrogée depuis trois chemins —
la sélection des dates, le rejeu direct, le chemin « corps inchangé » d'une
récupération réussie. Elle vit dans **une seule fonction**, `Runner::rejeu_du()`.
Une règle posée ailleurs serait contournable par les deux autres chemins.

### Table des états terminaux

| `projection.resultat` | Rejeu ? | Re-contrôle réseau ? | Pourquoi |
|---|---|---|---|
| `inconnue` | non | oui | Rien ne dit que la projection a échoué ; rejouer doublerait l'historique d'une publication déjà projetée. |
| `complet` | non | oui | Il n'y a rien à réparer. |
| `partiel` | oui, borné | oui | Une partie du lot manque en base ; l'écrire est le seul remède. |
| `rejete` | oui, borné | oui | Le lot entier a été refusé ; la cause peut être passagère. |
| `sans_projecteur` | **seulement si un abonné est revenu** | oui | Personne n'a conclu de projection : le domaine est absent ou désarmé. Réémettre dans le vide ne réparerait rien — mais condamner la date jusqu'à minuit alors que la donnée est en cache et le domaine réparé une heure plus tard non plus. |

Toute ligne du tableau reste soumise au garde « décision humaine plus récente »
ci-dessus, et à `REJEUX_MAX_PAR_JOUR`.

**`sans_projecteur` n'est pas terminal : il attend le retour d'un abonné.** La
sonde est `has_action( 'massifs_prefecture_snapshot_enregistre' )`. Si le domaine
est réellement absent elle est fausse, **aucun rejeu n'a lieu**, et la boucle que
ce garde-fou interdit reste impossible. Si un abonné est revenu, l'émission a une
chance d'aboutir et la date se rattrape le jour même. La sonde compte **tout
abonné**, pas seulement un projecteur : un observateur passif suffit à la rendre
vraie, pour un coût borné à `REJEUX_MAX_PAR_JOUR` émissions sans effet — zéro
octet réseau, zéro ligne d'historique, puisque personne n'écrit.

`sans_projecteur` n'a jamais empêché le **re-contrôle** : les deux n'ont pas le
même motif. Le rejeu répond à une projection en échec ; le re-contrôle répond à
une republication possible de la source. Le domaine peut être absent sans que
cela justifie de cesser de surveiller la source.

Le rejeu **ne coûte aucun octet réseau** : le corps vient du dépôt. Dans une
passe planifiée, il **prime donc toujours** sur une requête sortante.
`Runner::rejouer_projection( string $date_ymd ): bool` l'expose.

### Constantes dérivées, et leur budget

Cadence réelle : **96 passes par jour** (`DISABLE_WP_CRON` + tâche système au
quart d'heure, cf. §Hébergement), dont **28 dans la fenêtre 16 h → 23 h**, avec
au plus **2 dates par passe**.

| Constante | Valeur | Dérivation |
|---|---|---|
| `Runner::ANTI_RAFALE_SECONDES` (privée) | 15 min | Une passe exactement. Empêche une passe cron doublée par une visite de sortir deux fois. |
| `Runner::RECONTROLE_SECONDES` | 3 h | 12 passes. Une republication est captée en moins de trois heures, sans recharger une date couverte à chacune des 96 passes (192 requêtes par jour). |
| `Runner::RECONTROLES_MAX_PAR_JOUR` | 4 | 4 × 3 h = 12 h, la plage entière où une republication a un sens. Coût plafond ajouté : 4 × 2 dates = **8 requêtes par jour**. |
| `Runner::REJEUX_MAX_PAR_JOUR` | 3 | Traverse une cause passagère, borne une cause permanente (référentiel absent) à trois passages au lieu de 96. Zéro octet réseau, mais au plus 3 lots d'historique. |

Budget total en saison : environ **8 requêtes par jour** avant, **16 au plus**
après. Même ordre de grandeur, ce qui était la contrainte. **Zéro hors saison**,
inchangé.

Les deux compteurs quotidiens portent le jour auquel ils appartiennent et se
**réarment d'eux-mêmes au changement de jour**, sans tâche de purge.

Les trois dernières constantes sont **publiques à dessein** :
`tests/scenarios/57-rejeu-republication-et-projection.php` les lit au lieu de
recopier leurs valeurs, pour que la recette ne dérive pas du code. Les repasser
en `private` casserait ce scénario.

### Mémoire du garde anti-rafale

`last_attempt_for()` lit une **carte dédiée par date** (`tentatives`), écrite
avant tout octet réseau et élaguée à 3 jours. Elle ne dépend plus du journal :
à 96 passes par jour et 2 dates par passe, un journal FIFO de 20 entrées couvre
une dizaine de minutes, et la dernière tentative en sortait avant que le garde
ait fini de la protéger. `JOURNAL_MAX` **reste à 20** — ce plafond ne gouverne
plus que la lisibilité de l'écran d'exploitation. Le balayage du journal subsiste
en **repli**, pour un état écrit avant l'introduction de la carte
(rétro-compatibilité, sans migration).

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

- `massifs_niveaux_source_autorises()` et `massifs_procedures_source_autorisees()`
  — chaîne #3 (modèle de statuts).

### Référentiel : aucune passerelle, et c'est délibéré

Le garde-fou référentiel compare les identifiants **émis par le flux**
(`131`…`1327`, format `/^\d{3,4}$/`) — **pas** les codes du référentiel métier
de la chaîne #2, qui sont des codes kebab (`alpilles`, `sainte-victoire`).

Ce sont deux vocabulaires distincts. Y brancher le référentiel métier ferait
**rejeter 100 % des charges réelles**. Le contrat de la chaîne #2 l'écrit
explicitement : `massifs_referentiel_codes_source()` n'existe pas et ne doit pas
être appelée. Aucun appel de ce genre ne subsiste dans ce connecteur.

La correspondance entre les deux vocabulaires existe, mais elle appartient à la
chaîne #2 et s'emploie **en aval** de l'ingestion, pas dedans :
`massifs_code_depuis_source( '131' ): ?string`.

Seul point de surcharge légitime de l'ensemble de référence : le filtre
`massifs_prefecture_massifs_attendus`, qui doit recevoir des **identifiants
source**.

## Actions

### Émises

| Action | Arguments |
|---|---|
| `massifs_prefecture_snapshot_enregistre` | `array $instantane`, `string $motif` |
| `massifs_prefecture_echec` | `WP_Error $erreur`, `array $etat` |
| `massifs_prefecture_tentative` | `$date_ymd`, `$declencheur` |

`massifs_prefecture_snapshot_enregistre` est la couture d'intégration : ce
connecteur ne projette jamais dans un modèle de statut, n'invalide aucun cache de
page et ne touche à aucune option d'une autre chaîne. C'est au domaine de s'y
abonner.

Son **second argument** `$motif` vaut `publication`, `republication` ou `rejeu`.
Il est ajouté sans risque pour les abonnés existants : un `add_action` sans
`accepted_args` n'en reçoit qu'un.

### Écoutée — la frontière n'est plus à sens unique

| Action | Arguments | Abonné |
|---|---|---|
| `massifs_projection_prefecture` | `array $bilan` | `ProjectionListener::capter()` |

**Ce point renverse une propriété que ce README et l'en-tête de
`ProjecteurPrefecture` décrivaient comme acquise.** La frontière avec
`includes/domain/statuts/` était à sens unique ; elle ne pouvait pas le rester.
Un instantané enregistré dont la projection échoue laisse le site sans statut
sans que personne, côté ingestion, ne le sache — et donc sans que rien ne relance
l'essai.

Le récepteur est **strictement défensif et purement passif** : il n'émet rien,
n'appelle rien, ne lit aucun statut, n'écrit dans aucune table du domaine. Il
consigne le résultat sur l'instantané et pose un drapeau en mémoire pour la
requête courante. **La décision de rejeu appartient au `Runner`, et à lui seul.**
Un bilan non tabulaire, un `jour` illisible ou une date inconnue du dépôt
n'écrivent rien et ne cassent rien.

Le drapeau en mémoire répond à une question et une seule : « quelqu'un a-t-il
conclu une projection pour l'instantané que je viens de publier ? ». Drapeau
absent = `sans_projecteur`. C'est ce qui empêche un connecteur dont le domaine
est absent de réémettre en boucle : cet état ne redevient rejouable que si un
abonné réapparaît.

**« A répondu » et « a répondu de façon exploitable » sont deux choses
distinctes**, et les confondre est un défaut grave. Le drapeau est posé en
**première instruction** de `capter()`, avant tout contrôle de forme : un bilan
non tabulaire, un `resultat` inconnu, un `jour` illisible n'écrivent rien, mais
comptent tous comme une réponse. Un projecteur cassé n'est pas un projecteur
absent — conclure `sans_projecteur` sur sa réponse difforme poserait sur
l'instantané un diagnostic faux (« le domaine est absent ») au moment précis où
le domaine vient de parler.

Voir `docs/decisions/rejeu-ingestion-prefecture.md`.

---

## Ce qui n'est PAS une aberration

Ces trois cas sont documentés dans `class-validator.php` et ne doivent **jamais**
être réintroduits comme motifs de rejet :

1. **Tous les massifs au niveau le plus sévère** — c'est le jour de canicule où
   l'information compte le plus. Le rejeter afficherait « information non
   disponible » précisément ce jour-là.
2. **Un lot identique à celui de la veille** — c'est le cas nominal en juin.
3. **Un saut d'amplitude de niveau**, quelle qu'en soit la hauteur.

### Ce que fait le hachage — et ce qu'il ne fait pas

Le hachage `sha256` du corps brut **ne provoque jamais de rejet et ne bloque
jamais un enregistrement**. Il sert exactement à deux choses :

1. **Éviter une réécriture inutile** quand le corps reçu est identique à
   l'instantané *déjà enregistré pour cette même date* ;
2. **Journaliser** qu'un contenu est identique à celui d'une autre journée —
   information d'exploitation utile, sans aucun effet sur l'enregistrement.

Le chemin « corps identique » **écrit désormais une entrée de journal**
(`succes`, avec une note distincte) au lieu de sortir en silence. Sans cela, le
seul chemin nominal d'une date déjà couverte serait devenu invisible à
l'exploitation le jour où cette date est redevenue candidate d'une passe à
l'autre.

Le signal de « pas encore publié » n'est **pas** le hachage, c'est le **404** :
la source répond 404 sur `{date}.json` tant que la journée n'est pas publiée, et
un **200 sur cette URL EST la publication de cette date**. Le corps ne contient
d'ailleurs aucune date, donc deux journées stables produisent nécessairement le
même hachage — c'est le cas nominal, pas une anomalie.
