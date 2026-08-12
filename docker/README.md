# Stack Docker locale — MASSIFS

Stack de développement/test pour faire tourner le site WordPress avec le thème
`massifs` et l'extension `massifs-core` du dépôt, montés en direct (jamais
copiés dans une image). Sert de socle pour `test-integration-cms`.

## Services

| Service     | Rôle                                                                 | Port hôte (défaut) |
|-------------|-----------------------------------------------------------------------|---------------------|
| `wordpress` | PHP 8.3 + WordPress (Apache), thème et extension montés depuis le dépôt | `8080`              |
| `db`        | MariaDB 11.4, données dans un volume nommé                            | `3306`              |
| `wpcli`     | WP-CLI — installation, activation, rôles, fixtures (outil, pas un service persistant) | — |
| `tiles`     | Source de tuiles locale (nginx statique), reversée en même origine sous `/tiles/` par `wordpress` | `8081` (accès direct, debug uniquement) |

Les ports sont fixés dans `.env` (voir `.env.example`) : `WORDPRESS_PORT`,
`TILES_PORT`, `DB_PORT`. Modifiez-les si ces ports sont déjà pris sur votre
machine.

## Démarrer

```bash
# depuis la racine du dépôt, Git Bash sous Windows (ou tout shell POSIX)
bash docker/up.sh
```

Ce script unique : copie `.env.example` vers `.env` s'il n'existe pas encore,
construit et démarre `db`, `wordpress`, `tiles`, attend qu'ils soient
**healthy** (pas de `sleep` à l'aveugle — poll actif sur l'état des
healthchecks), puis lance le provisionnement WP-CLI.

Le site est alors sur `http://localhost:8080/` (ou le port choisi dans `.env`).

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

## Provisionner manuellement / rejouer le provisionnement

```bash
docker compose run --rm wpcli sh /provision/provision.sh
```

Le script (`docker/provision/provision.sh`) est **idempotent** : il vérifie
l'état avant chaque action (WordPress déjà installé ? thème déjà actif ? rôle
déjà créé ? compte déjà présent ?) et ne duplique jamais rien. Il peut être
rejoué à tout moment sur une stack déjà provisionnée.

Il effectue, dans l'ordre :
1. installation de WordPress (locale `fr_FR`, identifiants issus de `.env`) ;
2. activation du thème `massifs` et de l'extension `massifs-core` ;
3. suppression des thèmes par défaut (`Twenty*`) et extensions tierces
   (`akismet`, `hello dolly`) — surface tierce réduite à zéro (brief §3, §9) ;
4. réglage des permaliens en structure `/%postname%/` (routes REST et pages
   propres) ;
5. création du rôle `gestionnaire` (calqué sur `editor` — les capacités fines
   sont affinées par la chaîne `securite`) et d'un compte de démonstration
   (`WP_MANAGER_USER` dans `.env`) ;
6. rejeu des fixtures si `docker/provision/fixtures/seed.php` existe (voir
   `docker/provision/fixtures/README.md` — vide pour l'instant, câblé pour les
   chaînes fonctionnelles).

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
  accès complet, `http://localhost:8080/wp-admin/`.
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
fond de carte, seulement `http://localhost:8080/tiles/...`. C'est
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
`spawn_cron()` à chaque chargement de page) est **cassé dans cette stack** :
le `siteurl` provisionné est `http://localhost:${WORDPRESS_PORT:-8080}`, un
port publié côté hôte sur lequel rien n'écoute *à l'intérieur* du conteneur
`wordpress` (Apache y écoute sur le port 80). Une requête vers
`http://localhost:8080/...` lancée depuis l'intérieur du conteneur échoue
donc en connexion refusée.

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
divisée par ~3,8 (mesuré : 278 728 o bruts → ~74 Ko gzippés). C'est le budget
poids §10 du brief, et ce qui permet à la chaîne `carte` de resserrer la
tolérance de simplification de la géométrie (90 m → 20 m) en s'appuyant sur
une compression confirmée plutôt que supposée.

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
