# Source de tuiles locale

Ce dossier est le chemin servi par le service `tiles` (voir `docker-compose.yml` et
`docker/tiles/nginx.conf`), lui-même reversé en même origine que le site sous
`/tiles/` par le conteneur `wordpress` (voir `docker/wordpress/tiles-proxy.conf`).

Il existe pour que le fond de carte ne dépende **jamais** d'un CDN de tuiles tiers
(contrainte §3 du brief) : structurellement, le navigateur ne peut appeler que
notre propre origine.

## Ce qui n'est PAS fait ici

Aucune tuile n'est téléchargée ou générée par ce dossier de provisionnement. C'est
volontaire — le choix du format (tuiles raster PNG/JPEG classiques, tuiles
vectorielles PMTiles, etc.) et le pipeline de génération/mise en cache
appartiennent à la chaîne fonctionnelle `carte` (voir `CLAUDE.md`, domaine
`includes/ingest/` de l'extension).

## Comment ce dossier sera rempli

1. La chaîne `carte` choisit le format (probable : tuiles vectorielles PMTiles ou
   raster pré-générées, limitées à l'emprise des Bouches-du-Rhône).
2. Un job (cron WP ou script d'import ponctuel) écrit les fichiers ici, sous une
   arborescence du type `{z}/{x}/{y}.png` (raster classique) ou un fichier unique
   `.pmtiles` servi par un petit backend de tuiles vectorielles.
3. `docker/tiles/nginx.conf` sert ces fichiers tels quels sous `/tiles/` — aucune
   modification de configuration ne devrait être nécessaire pour du raster ; un
   format différent (ex. PMTiles) peut nécessiter un service serveur dédié plutôt
   que nginx statique, à la charge de la chaîne `carte`.

Ce dossier n'est jamais versionné avec de vraies tuiles (voir `.gitignore` :
`/wp-content/uploads/massifs-tiles/`, `*.mbtiles`, `*.pmtiles`) — seul ce
`README.md` l'est, pour documenter l'emplacement.
