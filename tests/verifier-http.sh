#!/usr/bin/env bash
# Vérifications vues du navigateur : origines tierces, gardes d'exposition,
# budget de transfert. Ce que PHP ne peut pas prouver depuis l'intérieur.
#
#   bash tests/verifier-http.sh
#
# Prérequis : la stack tourne (`bash docker/up.sh`).
set -uo pipefail

PORT="$(grep -E "^WORDPRESS_PORT=" "$(dirname "${BASH_SOURCE[0]}")/../.env" 2>/dev/null | cut -d= -f2)"
BASE="http://localhost:${PORT:-8080}"
GEO="$BASE/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json"
TRAVAIL="$(mktemp -d)"
statut=0

echo '=== §12 — ZÉRO REQUÊTE TIERCE ==============================================='
: > "$TRAVAIL/urls.txt"
: > "$TRAVAIL/ressources.txt"

for page in '/' '/hello-world/' '/sample-page/' '/wp-login.php' '/?p=404introuvable' '/feed/'; do
	code=$(curl -s -o "$TRAVAIL/page.html" -w '%{http_code}' "$BASE$page")
	printf 'PAGE %-24s HTTP %s  %s octets\n' "$page" "$code" "$(wc -c < "$TRAVAIL/page.html")"
	grep -oE 'https?://[^"'"'"' <>()]+' "$TRAVAIL/page.html" >> "$TRAVAIL/urls.txt"
	grep -oE '(href|src)="[^"]+\.(css|js)[^"]*"' "$TRAVAIL/page.html" \
		| sed -E 's/^(href|src)="//; s/"$//' >> "$TRAVAIL/ressources.txt"
done

sort -u "$TRAVAIL/ressources.txt" -o "$TRAVAIL/ressources.txt"

while read -r ressource; do
	[ -z "$ressource" ] && continue
	url="$ressource"
	case "$ressource" in
		http*) ;;
		/*) url="$BASE$ressource" ;;
		*) url="$BASE/$ressource" ;;
	esac
	code=$(curl -s -o "$TRAVAIL/ressource" -w '%{http_code}' "$url")
	printf 'RESSOURCE %s -> %s (%s octets)\n' "$url" "$code" "$(wc -c < "$TRAVAIL/ressource")"
	grep -oE 'https?://[^"'"'"' <>()]+' "$TRAVAIL/ressource" >> "$TRAVAIL/urls.txt"
	grep -oE '@import[^;]+' "$TRAVAIL/ressource" >> "$TRAVAIL/urls.txt"
	grep -oE 'url\(([^)]*)\)' "$TRAVAIL/ressource" >> "$TRAVAIL/urls.txt"
done < "$TRAVAIL/ressources.txt"

echo
echo 'origines rencontrées (HTML + CSS + JS) :'
sed -E 's#(https?://[^/]+).*#\1#' "$TRAVAIL/urls.txt" | sed 's/[",]$//' | sort | uniq -c | sort -rn

echo
echo '=== §10 — BUDGET DE LA GÉOMÉTRIE ============================================'
brut=$(curl -s -o /dev/null -w '%{size_download}' "$GEO")
compresse=$(curl -s -H 'Accept-Encoding: gzip' -o /dev/null -w '%{size_download}' "$GEO")
printf 'sans compression : %s octets\n' "$brut"
printf 'avec compression : %s octets transférés\n' "$compresse"
curl -s -H 'Accept-Encoding: gzip' -D - -o /dev/null "$GEO" | tr -d '\r' \
	| grep -iE 'HTTP/|content-encoding|vary|content-type'
if [ "$brut" -ge 307200 ]; then
	echo '!! BUDGET DÉPASSÉ : la géométrie brute doit rester sous 300 Ko'
	statut=1
fi

echo
echo '=== GARDES D EXPOSITION ====================================================='
verifier() {
	local chemin="$1" attendu="$2"
	local code
	code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$chemin")
	if [ "$code" = "$attendu" ]; then
		printf 'ok    %-95s %s\n' "$chemin" "$code"
	else
		printf 'ECHEC %-95s %s (attendu %s)\n' "$chemin" "$code" "$attendu"
		statut=1
	fi
}

# Jamais accessibles : archive source de 3 Mo, outillage de build, code PHP.
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/source/massifs-13.full.geojson' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/identites.json' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/statuts/legende.config.php' 403
verifier '/wp-content/plugins/massifs-core/includes/ingest/prefecture/class-connector.php' 403
verifier '/wp-content/plugins/massifs-core/massifs-core.php' 403
verifier '/wp-content/plugins/massifs-core/data/massifs-13.php' 403
verifier '/wp-content/themes/massifs/index.php' 403
# Doivent rester servis : rien de fonctionnel ne doit être cassé par les gardes.
verifier '/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json' 200
verifier '/wp-content/themes/massifs/style.css' 200
verifier '/' 200
verifier '/wp-login.php' 200
verifier '/wp-json/' 200

rm -rf "$TRAVAIL"
exit "$statut"
