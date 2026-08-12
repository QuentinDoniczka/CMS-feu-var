#!/usr/bin/env bash
# Démarre la stack MASSIFS et la provisionne — commande unique, idempotente.
# Usage : bash docker/up.sh   (Git Bash sur Windows, ou tout shell POSIX)
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Git Bash (MSYS) traduit les chemins Unix-style passés en argument (ex.
# /provision/provision.sh) en chemins Windows avant d'appeler docker.exe, ce
# qui casse les chemins destinés à l'intérieur des conteneurs. À désactiver
# pour ce script. Sans effet ailleurs (WSL, Linux, macOS).
export MSYS_NO_PATHCONV=1

if [ ! -f .env ]; then
	echo "Pas de .env : copie de .env.example."
	cp .env.example .env
fi

docker compose up -d --build db wordpress tiles

echo "En attente que db et wordpress soient en bonne santé..."
for container in massifs_db massifs_wordpress; do
	tries=0
	until [ "$(docker inspect --format '{{.State.Health.Status}}' "$container" 2>/dev/null)" = "healthy" ]; do
		tries=$((tries + 1))
		if [ "$tries" -gt 60 ]; then
			echo "!! $container n'est jamais devenu healthy — voir 'docker compose logs'." >&2
			exit 1
		fi
		sleep 2
	done
done

echo "Provisionnement (wp-cli)..."
docker compose run --rm wpcli sh /provision/provision.sh

echo "Stack prête : http://localhost:${WORDPRESS_PORT:-3002}/"
