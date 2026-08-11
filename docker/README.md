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

Rien n'ingère de données externes pour l'instant (aucun code d'ingestion
n'existe encore — chaînes `meteo`, `effis`, `statuts` à venir). Quand ce code
existera, il devra lire les URL de source (préfecture, Météo-France, EFFIS)
depuis des constantes/options configurables, afin que les tests puissent les
pointer vers des stubs locaux plutôt que les vraies APIs — jamais d'appel
réel dans le profil de test (voir `CLAUDE.md`, contrainte §2 du brief,
applicable aussi côté serveur pour les tests).

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
