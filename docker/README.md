# Stack Docker locale — MASSIFS

Stack de développement/test pour faire tourner le site WordPress avec le thème
`massifs` et l'extension `massifs-core` du dépôt, montés en direct (jamais
copiés dans une image). Sert de socle pour `test-integration-cms`.

## Services

| Service     | Rôle                                                                 | Port hôte (défaut) |
|-------------|-----------------------------------------------------------------------|---------------------|
| `wordpress` | PHP 8.3 + WordPress (Apache), thème et extension montés depuis le dépôt | `3002`              |
| `db`        | MariaDB 11.4, données dans un volume nommé                            | `3306`              |
| `wpcli`     | WP-CLI — installation, activation, rôles, fixtures (outil, pas un service persistant) | — |
| `tiles`     | Source de tuiles locale (nginx statique), reversée en même origine sous `/tiles/` par `wordpress` | `8081` (accès direct, debug uniquement) |

Les ports sont fixés dans `.env` (voir `.env.example`) : `WORDPRESS_PORT`,
`TILES_PORT`, `DB_PORT`. Le site est sur `http://localhost:3002/` par défaut
— voir « Changer de port » ci-dessous si vous devez modifier `WORDPRESS_PORT`.

## Démarrer

```bash
# depuis la racine du dépôt, Git Bash sous Windows (ou tout shell POSIX)
bash docker/up.sh
```

Ce script unique : copie `.env.example` vers `.env` s'il n'existe pas encore,
construit et démarre `db`, `wordpress`, `tiles`, attend qu'ils soient
**healthy** (pas de `sleep` à l'aveugle — poll actif sur l'état des
healthchecks), puis lance le provisionnement WP-CLI.

Le site est alors sur `http://localhost:3002/` (ou le port choisi dans `.env`,
voir « Changer de port »).

## Arrêter

```bash
docker compose down
```

Les volumes (base de données, fichiers WordPress) sont conservés : un
`bash docker/up.sh` ultérieur retrouve l'état existant sans rien recréer ni
dupliquer (voir « Idempotence » ci-dessous).

## Réinitialiser complètement

```bash
bash docker/reset.sh
```

Supprime les volumes (`docker compose down -v` — base de données et fichiers
WordPress perdus) puis relance `docker/up.sh` : repart d'un état strictement
vierge.

## Changer de port

Si `3002` (port canonique du site, y compris `wp-admin`) est déjà pris sur
votre machine, changez `WORDPRESS_PORT` dans `.env` — pas dans
`docker-compose.yml`, qui ne fait que lire cette variable :

```
WORDPRESS_PORT=3010
```

Puis, selon l'état de la stack :

- **Pas encore démarrée** : `bash docker/up.sh` suffit — le port publié et
  `siteurl`/`home` en base sont alignés dès l'installation initiale.
- **Déjà démarrée/provisionnée** (volume WordPress existant, éventuellement
  installé avec un autre port) : republiez le port puis **rejouez le
  provisionnement**, dans cet ordre :
  ```bash
  docker compose up -d
  docker compose run --rm wpcli sh /provision/provision.sh
  ```
  Le script compare `siteurl` en base à `http://localhost:$WORDPRESS_PORT` et
  le corrige si besoin (étape « Adresse du site (siteurl / home) »).
  **Ne sautez pas le rejeu du provisionnement après un changement de port** :
  sans lui, WordPress continue de répondre sur l'ancien port et une requête
  sur le nouveau port est silencieusement redirigée dessus — panne apparente,
  pas un vrai incident, mais qui trompe qui l'ignore. Aucune destruction de
  volume n'est nécessaire : le rejeu suffit à faire retomber une stack déjà
  provisionnée sur ses pieds, quel que soit le port avec lequel elle a été
  installée à l'origine.

Le port hôte publié (`docker-compose.yml`, piloté par `WORDPRESS_PORT`) et
l'adresse provisionnée en base (`siteurl`/`home`) doivent toujours bouger
ensemble — c'est précisément le rôle de l'étape « Adresse du site » du
provisionnement de garantir ça, y compris rétroactivement sur une stack qui
existait déjà avant un changement de port.

## Provisionner manuellement / rejouer le provisionnement

```bash
docker compose run --rm wpcli sh /provision/provision.sh
```

Le script (`docker/provision/provision.sh`) est **idempotent** : il vérifie
l'état avant chaque action (WordPress déjà installé ? thème déjà actif ? rôle
présent ? compte déjà présent ?) et ne duplique jamais rien. Il peut être
rejoué à tout moment sur une stack déjà provisionnée.

Il effectue, dans l'ordre :
1. installation de WordPress (locale `fr_FR`, identifiants issus de `.env`) ;
2. fuseau horaire `Europe/Paris` (`timezone_string`) — sans effet sur le
   domaine, qui fige déjà son fuseau (`Horloge.php` de l'extension), mais
   évite que l'administration affiche des heures UTC au gestionnaire sur un
   site dont tout le propos est le statut *du jour* ;
3. activation du thème `massifs` et de l'extension `massifs-core` — **avant**
   toute étape suivante : c'est cette activation qui installe le rôle du
   portail (voir ci-dessous) ;
4. suppression des thèmes par défaut (`Twenty*`) et extensions tierces
   (`akismet`, `hello dolly`) — surface tierce réduite à zéro (brief §3, §9) ;
5. réglage des permaliens en structure `/%postname%/` (routes REST et pages
   propres) ;
6. vérification du rôle `massifs_gestionnaire` (voir « Rôle
   `massifs_gestionnaire` » ci-dessous) et rattachement d'un compte de
   démonstration (`WP_MANAGER_USER` dans `.env`) ;
7. rejeu des fixtures si `docker/provision/fixtures/seed.php` existe (voir
   `docker/provision/fixtures/README.md` — vide pour l'instant, câblé pour les
   chaînes fonctionnelles).

### Rôle `massifs_gestionnaire` — propriété exclusive de l'extension

Le rôle et ses capacités (`massifs_publier_statuts`,
`massifs_consulter_historique`, `massifs_gerer_gestionnaires`) sont le
vocabulaire **gelé par le contrat de l'issue #13** — seul point de couplage
entre les chaînes #13, #14 et #15. Ce script ne les crée **jamais** à la
main : ils sont installés et réconciliés par l'extension elle-même
(`wp-content/plugins/massifs-core/includes/security/roles/Installation.php`,
source du vocabulaire : `Capacites.php`), à chaque activation et à chaque
chargement (`massifs_core_installation`). Un rôle fabriqué en double ici
divergerait silencieusement au premier changement de capacités côté
extension — c'est précisément ce qu'un précédent provisionnement faisait
(rôle `gestionnaire` cloné de `subscriber`, sans aucune des trois capacités
du portail : le compte de démonstration ne pouvait ni publier, ni consulter
l'historique).

Le script se contente de vérifier, après l'activation de `massifs-core`, que
`massifs_gestionnaire` existe (`wp role list`) — il échoue bruyamment sinon,
plutôt que d'improviser un rôle de repli — puis rattache le compte de
démonstration à ce rôle (`--role=massifs_gestionnaire`).

## Utiliser WP-CLI directement

```bash
docker compose run --rm wpcli wp <commande>
# exemples :
docker compose run --rm wpcli wp plugin list
docker compose run --rm wpcli wp user list
```

`wpcli` n'est pas un service persistant : au démarrage de la stack (`docker
compose up -d`), il exécute une seule commande (`wp cli version`) puis
s'arrête — c'est normal de le voir "Exited (0)" dans `docker compose ps`.
Utilisez toujours `docker compose run --rm wpcli ...` pour une commande
ponctuelle.

## Identifiants

Tous dans `.env` (copié depuis `.env.example`, jamais commité) :

- **Administrateur WordPress** : `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` —
  accès complet, `http://localhost:3002/wp-admin/`.
- **Gestionnaire de démonstration** : `WP_MANAGER_USER` /
  `WP_MANAGER_PASSWORD` — rôle restreint, préfigure le compte de démo public
  du brief (§6).
- **Base de données** : `DB_NAME` / `DB_USER` / `DB_PASSWORD` /
  `DB_ROOT_PASSWORD`.

Ce sont des identifiants de développement local uniquement — jamais de secret
réel dans ce dépôt, jamais de `.env` commité (`.gitignore` l'exclut ; seul
`.env.example` l'est).

## Fond de carte auto-hébergé (contrainte §3 du brief)

Le service `tiles` sert des fichiers statiques depuis
`docker/tiles/data/` (vide pour l'instant — voir
`docker/tiles/data/README.md`). Le conteneur `wordpress` reverse-proxifie
`/tiles/` vers ce service (`docker/wordpress/tiles-proxy.conf`) : même en
local, le navigateur n'a jamais besoin de contacter un domaine tiers pour le
fond de carte, seulement `http://localhost:3002/tiles/...`. C'est
structurel — il n'y a pas de CDN de tuiles public configuré nulle part dans
la stack.

## Réseau et sources externes en test

L'extension `massifs-core` ingère désormais une source (préfecture,
`includes/ingest/prefecture/`) ; d'autres suivront (`meteo`, `effis`). Le
connecteur lit son URL de source depuis une constante/option configurable,
afin que les tests puissent la pointer vers un bouchon local plutôt que la
vraie API — jamais d'appel réel dans le profil de test (voir `CLAUDE.md`,
contrainte §2 du brief, applicable aussi côté serveur pour les tests).

### Coupe-circuit `WP_ENVIRONMENT_TYPE` — ne pas y toucher

`docker-compose.yml` fixe **en dur** (pas dans `.env`) `WP_ENVIRONMENT_TYPE:
local` sur les services `wordpress` et `wpcli`. C'est ce que lit
`Settings::is_disabled()` dans `includes/ingest/prefecture/class-settings.php` :
tant que l'environnement vaut `local`/`development` et qu'aucune URL de bouchon
n'a été redéfinie (`MASSIFS_PREFECTURE_JSON_URL_TEMPLATE`), le connecteur
préfecture est **désarmé** — aucun `wp_remote_get` n'en sort, quel que soit le
mode (`automatique`/`manuel`) ou l'état de la planification.

Sans cette variable, `wp_get_environment_type()` retombe sur `production` par
défaut, et le connecteur est **armé** : constaté en pratique dans cette
stack — mode `automatique`, évènement horaire planifié et en retard, URL par
défaut pointant sur `https://www.risque-prevention-incendie.fr/...`. La seule
raison qu'aucune requête ne soit réellement partie jusqu'ici est un accident
(le cron ne se déclenche jamais — voir section suivante) : ce réglage retire
l'accident et ne laisse subsister que la protection voulue.

**Ne jamais retirer, commenter ou basculer cette variable sur cette stack**, y
compris "pour tester en conditions réelles" : une rafale de requêtes sorties
d'une machine de développement vers le serveur de la préfecture est le genre
d'incident qui fait bannir notre IP et abîme la relation avec la source dont
le projet dépend. Pour tester le connecteur avec de vraies données sans
jamais sortir du réseau Docker, redéfinir `MASSIFS_PREFECTURE_JSON_URL_TEMPLATE`
vers le service `tiles` (bouchons locaux) — voir
`wp-content/plugins/massifs-core/includes/ingest/prefecture/README.md`,
section « Coupe-circuit et profil de test ».

### WP-Cron : désactivé délibérément, déclenchement manuel

Le loopback WordPress (`http://<siteurl>/wp-cron.php`, appelé par
`spawn_cron()` à chaque chargement de page) est **cassé dans cette stack**,
quel que soit le port choisi : le `siteurl` provisionné est
`http://localhost:${WORDPRESS_PORT:-3002}`, un port publié côté hôte sur
lequel rien n'écoute *à l'intérieur* du conteneur `wordpress` (Apache y
écoute sur le port 80). Une requête vers `http://localhost:$WORDPRESS_PORT/...`
lancée depuis l'intérieur du conteneur échoue donc en connexion refusée —
changer `WORDPRESS_PORT` ne résout pas ce point, il ne fait que déplacer le
même problème sur un autre numéro de port.

Plutôt que de laisser WordPress retenter silencieusement cet appel voué à
l'échec à chaque page, `WORDPRESS_CONFIG_EXTRA` définit
`DISABLE_WP_CRON = true` dans `wp-config.php` : le déclenchement automatique
est coupé **explicitement**. Conséquence directe et importante pour la
lecture des résultats de test : **aucun évènement planifié ne s'exécute tout
seul dans cette stack**, y compris ceux qui semblent "en retard" dans
`wp cron event list`.

Pour déclencher les évènements dus à la main (tests du chemin d'ingestion,
vérification manuelle) :

```bash
docker compose run --rm wpcli wp cron event run --due-now
# ou un évènement précis :
docker compose run --rm wpcli wp cron event run massifs_prefecture_ingestion
```

`wp cron event run` exécute l'évènement directement dans le processus
WP-CLI — il ne dépend pas du loopback HTTP cassé, et respecte le coupe-circuit
`WP_ENVIRONMENT_TYPE` ci-dessus (le service `wpcli` a la même variable).

### Compression HTTP

L'image `wordpress` active `mod_deflate` (`docker/wordpress/deflate.conf`,
chargé via `RUN a2enmod ... deflate` dans le `Dockerfile`) pour
`text/html`, `text/css`, `text/xml`, `text/javascript`,
`application/javascript`, `application/json`, `application/xml` et
`image/svg+xml`. Ça couvre en particulier
`wp-content/plugins/massifs-core/data/massifs-13.geometrie.json`, servi tel
quel par Apache : avec un client qui négocie `Accept-Encoding: gzip`, la
réponse revient avec `Content-Encoding: gzip` et une taille transférée
divisée par ~3,8 — **278 894 o bruts → 74 023 o transférés**, mesuré le
2026-08-13 par `bash tests/verifier-http.sh` sur cette stack.

Deux précisions qui évitent de sur-interpréter ce chiffre.

**Brut ≠ transféré, et le budget porte sur le brut.** Le §10 du brief plafonne
les géométries à 300 Ko ; l'arbitrage B-11 du contrat `docs/contracts/issue-2.md`
a tranché pour les **octets bruts**, l'hypothèse la plus stricte. La cible
mesurée ici est la stack Docker locale ; **la production o2switch n'est pas
mesurée**. La compression reste donc une **marge, pas une béquille**. À ne pas
confondre non plus avec les 73 737 o mesurés au build par `zlib.gzipSync` et
consignés dans `massifs-13.fidelite.json` : deux méthodes, deux nombres, jamais
l'un présenté pour l'autre.

**Cette compression n'autorise pas à resserrer la simplification de la
géométrie** (90 m → 20 m). Trois raisons, mesurées le 2026-08-13 avec
l'outillage épinglé :

1. l'interdit 12 du contrat plafonne la couche massifs à **z11** ; une fidélité
   sous-pixel à z12 n'est visible à aucun zoom que le front est autorisé à
   proposer ;
2. `interval=20 m` pèse **809 966 o bruts**, soit 2,64 × le budget brut, et
   47 931 sommets, soit 2,94 × ceux d'aujourd'hui — autant à décompresser,
   analyser et rasteriser sur mobile, contre les 2,5 s du §10 ;
3. le consommateur — la carte — n'est pas écrit. Le choix appartient à la
   chaîne qui la construira, mesure de terrain à l'appui.

Détail et voie de relèvement :
`wp-content/plugins/massifs-core/includes/domain/massifs/README.md`,
section « Simplification et budget ».

### Garde-fou sur les fichiers .php de thème/extension

Constat : une requête HTTP directe vers un fichier `.php` du thème ou de
l'extension qui n'est pas censé être un point d'entrée (ex. une classe sous
`includes/ingest/prefecture/`) renvoie aujourd'hui 200 avec un corps vide —
PHP l'exécute, il ne contient simplement aucune sortie au niveau racine. Ce
n'est pas une faille aujourd'hui (aucune de ces classes ne produit de sortie
tant qu'elle n'est pas invoquée depuis WordPress), mais ça dépend d'un fait
qui pourrait cesser d'être vrai (une classe future qui échoue à parser, une
erreur qui fuit un chemin serveur, etc.), et seul le sous-arbre
`includes/domain/massifs/` de l'extension a son propre `.htaccess`.

**Décision** : ajouter un garde-fou large côté serveur plutôt que d'attendre
un `.htaccess` par sous-répertoire d'extension. `docker/wordpress/plugins-guard.conf`
refuse (`403`) toute requête HTTP directe vers un `.php` sous
`wp-content/plugins/` ou `wp-content/themes/`, quel que soit le fichier —
WordPress ne demande jamais ces fichiers par URL, il les charge en interne
(`require`), donc ce blocage ne change rien au fonctionnement du site. Ce
fichier vit dans `docker/`, pas dans l'arbre du thème/de l'extension : il
reste dans mon empreinte, et n'entre pas en conflit avec le `.htaccess`
existant de la chaîne `referentiel` (celui-ci continue de protéger son
sous-arbre même en dehors de cette image, ex. un hébergement mutualisé sans
cette configuration Apache globale — la défense reste en profondeur, pas en
un seul point).

**Second bloc : des sous-arbres entiers, pas seulement les `.php`.** Le premier
bloc laissait partir en 200 tout ce qui n'est pas `.php` — un `README.md` de
note interne, un `.json`, un `.geojson`. `plugins-guard.conf` refuse donc aussi
`includes/`, `build/` et `node_modules/` à n'importe quelle profondeur sous
`wp-content/plugins/` ou `wp-content/themes/` : ces trois noms désignent des
répertoires structurellement non publics (code de domaine, outillage de build,
dépendances npm). Un sous-arbre refusé protège aussi les fichiers qui n'existent
pas encore, ce qu'une liste noire de noms ne fait pas.

Deux répertoires sont **délibérément épargnés** et doivent le rester :
`wp-content/themes/massifs/assets/` (CSS, polices auto-hébergées et
`assets/vendor/` qui portera Leaflet vendorisé — le §3 du brief impose de servir
ces fichiers depuis notre origine) et `wp-content/plugins/massifs-core/data/`
(géométrie publiée, puis caches météo / EFFIS / tuiles des chaînes suivantes).
La matrice HTTP de `tests/verifier-http.sh` fixe les deux sens : `403` sur
`build/source/` et `build/identites.json`, `200` sur la géométrie de `data/` et
sur `themes/massifs/style.css`.

## Windows (win32)

- Les montages (`./wp-content/...`) fonctionnent tels quels avec Docker
  Desktop sur Windows (traduction de chemin automatique) — aucune
  configuration WSL manuelle requise.
- Tous les scripts de ce dossier (`up.sh`, `reset.sh`,
  `provision/provision.sh`) sont en fin de ligne LF (obligatoire : ils
  s'exécutent dans des conteneurs Linux). Ne pas les ré-enregistrer avec un
  éditeur qui convertit en CRLF.
- Lancez les scripts `.sh` depuis Git Bash (fourni avec Git pour Windows) ou
  WSL. Sans shell POSIX, utilisez directement les commandes `docker compose`
  documentées ci-dessus.

## Idempotence — comment c'est vérifié

`docker/provision/provision.sh` est rejoué deux fois de suite lors de la
vérification (`docker-cms`) sur la même stack : la deuxième exécution ne doit
produire aucune erreur et aucun doublon (utilisateur, rôle, thème). Chaque
étape le garantit par construction (`core is-installed`, `role list | grep`,
`user get`, suppression conditionnelle des thèmes/extensions tiers).
