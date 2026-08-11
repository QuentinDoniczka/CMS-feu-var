#!/usr/bin/env bash
# Réinitialise complètement la stack MASSIFS (supprime les volumes : base de
# données et fichiers WordPress) puis la recrée et la reprovisionne.
# Usage : bash docker/reset.sh
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

docker compose down -v
bash docker/up.sh
