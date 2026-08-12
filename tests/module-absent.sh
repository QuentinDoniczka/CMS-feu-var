#!/usr/bin/env bash
# Tolérance du chargeur par convention à l'absence d'un module frère.
#
# C'est la propriété qui protège les chaînes de développement parallèles :
# l'arbre de travail est partagé, un module peut être absent ou à moitié écrit,
# et le site doit continuer de booter. On déplace le répertoire HORS de l'arbre
# — un simple renommage sur place ne masquerait rien, le chargeur balaie tous
# les sous-répertoires — puis on le restaure.
#
#   bash tests/module-absent.sh
#
# Prérequis : la stack tourne (`bash docker/up.sh`).
set -uo pipefail
export MSYS_NO_PATHCONV=1

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE"

INCLUDES="$RACINE/wp-content/plugins/massifs-core/includes"
GARAGE="$(mktemp -d)"
PORT="$(grep -E "^WORDPRESS_PORT=" "$RACINE/.env" 2>/dev/null | cut -d= -f2)"
SITE="http://localhost:${PORT:-8080}/"

restaurer_tout() {
	for parque in "$GARAGE"/*; do
		[ -d "$parque" ] || continue
		echo "!! restauration de secours : $parque"
	done
}
trap restaurer_tout EXIT

essai() {
	local relatif="$1"
	local etiquette="$2"
	local source="$INCLUDES/$relatif"
	local parque="$GARAGE/$etiquette"

	echo "=============================== module masqué : includes/$relatif"

	if [ ! -d "$source" ]; then
		echo "!! introuvable : $source"
		return 1
	fi

	mv "$source" "$parque"

	# La page est capturée en mémoire : sous Git Bash, `curl` est un binaire
	# Windows qui ne sait pas résoudre un chemin MSYS de type /tmp/…
	local reponse code corps
	reponse=$(curl -s -w '\n%{http_code}' "$SITE")
	code="${reponse##*$'\n'}"
	corps="${reponse%$'\n'*}"
	echo "accueil sans le module : HTTP $code, ${#corps} octets"

	if printf '%s' "$corps" | grep -qiE 'erreur critique|critical error|Fatal error'; then
		echo '!! ERREUR FATALE DANS LA PAGE'
	else
		echo 'aucune erreur fatale dans la page'
	fi

	docker compose run --rm -T -v "$RACINE/tests:/massifs-tests:ro" wpcli \
		wp eval-file /massifs-tests/outils/module-absent.php "$relatif" 2>&1 \
		| grep -v -e 'level=warning' -e 'Container massifs' -e '^$'

	mv "$parque" "$source"
	[ -d "$source" ] && echo 'module restauré' || echo '!! RESTAURATION ÉCHOUÉE'
}

essai 'domain/massifs' 'massifs'
essai 'ingest/prefecture' 'prefecture'
essai 'domain/statuts' 'statuts'

echo '=============================== état git après restauration'
git status --porcelain -- wp-content
echo '(vide = arbre intact)'
