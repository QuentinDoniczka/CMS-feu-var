#!/usr/bin/env bash
# Vérifications vues du navigateur : origines tierces, gardes d'exposition,
# budget de transfert. Ce que PHP ne peut pas prouver depuis l'intérieur.
#
#   bash tests/verifier-http.sh
#
# Prérequis : la stack tourne (`bash docker/up.sh`).
set -uo pipefail

PORT="$(grep -E "^WORDPRESS_PORT=" "$(dirname "${BASH_SOURCE[0]}")/../.env" 2>/dev/null | cut -d= -f2)"
# Repli aligné sur le `${WORDPRESS_PORT:-3002}` du compose : 3002 est le port
# canonique du projet. Le repli ne mord que sur un clone frais, `.env` étant
# gitignoré — mais sur ce clone-là, toutes les assertions échoueraient pour une
# raison étrangère aux gardes.
BASE="http://localhost:${PORT:-3002}"
GEO="$BASE/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json"
TRAVAIL="$(mktemp -d)"
RACINE="$(dirname "${BASH_SOURCE[0]}")/.."
# Arbre de sondes de la recette du garde d'exposition (issue #30) : créé, sondé
# et supprimé dans la même exécution. Rien ne doit lui survivre — `.gitignore`
# n'ancre `/vendor/` qu'à la racine du dépôt, un résidu serait commitable.
#
# Le chemin n'est écrit qu'une fois : le chemin sur disque, l'URL sondée et le
# répertoire à retirer au nettoyage doivent désigner le même arbre, sinon la
# recette créerait ici et mesurerait là.
VENDOR_URL='/wp-content/themes/massifs/assets/vendor'
FIXTURE_URL="$VENDOR_URL/_recette-garde-30"
FIXTURE="$RACINE$FIXTURE_URL"
FIXTURE_CREEE=''
SONDES_PRETES=''
# Fenêtre de propagation de l'arbre jusqu'au conteneur : 25 × 0,2 s ≈ 5 s.
PROPAGATION_ESSAIS=25
PROPAGATION_PAS=0.2
statut=0

nettoyer() {
	rm -rf "$TRAVAIL"
	# On ne supprime que ce qu'on a soi-même créé, et jamais un chemin construit
	# depuis une variable vide.
	if [ -n "$FIXTURE_CREEE" ] && [ -n "$FIXTURE" ]; then
		rm -rf "$FIXTURE"
		# `mkdir -p` a pu créer le parent : `rmdir` ne l'emporte que s'il est
		# vide, donc jamais l'arbre vendorisé d'une chaîne ultérieure.
		rmdir "$RACINE$VENDOR_URL" 2>/dev/null
	fi
}
# L'arbre de sondes ne survit à aucune sortie, y compris Ctrl-C.
trap nettoyer EXIT
# Un gestionnaire INT/TERM qui se contente de nettoyer rend la main, et le
# script REPREND là où il en était, arbre supprimé : les sondes restantes
# partent en échec pour rien, et une interruption tardive sortirait 0 — un vert
# sur une exécution interrompue. On sort donc explicitement (128 + numéro de
# signal) ; le trap EXIT ci-dessus fait le nettoyage, une seule fois.
trap 'exit 130' INT
trap 'exit 143' TERM

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

# Issue #30 — les chemins qui prouvent la mécanique du garde n'existent pas dans
# le dépôt : `assets/vendor/` n'est pas encore peuplé. On monte donc un arbre de
# sondes, on le sonde, on le supprime. Une assertion tolérante sur un chemin
# inexistant serait verte pour de mauvaises raisons.
if [ -e "$FIXTURE" ]; then
	echo "NON JOUÉ : $FIXTURE existe déjà — rien créé, rien supprimé, aucune sonde jouée."
	echo "        La recette ne touche jamais un arbre dont elle n'est pas l'auteur."
	statut=1
elif mkdir -p "$FIXTURE/build/sous" "$FIXTURE/build/node_modules" "$FIXTURE/node_modules" "$FIXTURE/dist" \
	&& printf '/* sonde de recette */\n' > "$FIXTURE/build/sonde.js" \
	&& printf '/* sonde de recette */\n' > "$FIXTURE/build/sous/sonde.js" \
	&& printf '/* sonde de recette */\n' > "$FIXTURE/build/node_modules/sonde.js" \
	&& printf '/* sonde de recette */\n' > "$FIXTURE/node_modules/sonde.js" \
	&& printf '/* sonde de recette */\n' > "$FIXTURE/dist/sonde.js" \
	&& printf '<?php\n// Sonde muette : seul son code HTTP compte. Une sonde qui peut imprimer\n// quelque chose est une sonde qui peut fuiter.\n' > "$FIXTURE/build/sonde.php"
then
	# Drapeau posé après succès complet de la création.
	FIXTURE_CREEE=1
	# Le montage du thème peut mettre un instant à propager l'arbre dans le
	# conteneur. On ne boucle QUE sur 404 (pas encore vu) et 000 (rien au bout du
	# port) : boucler jusqu'à obtenir un 200 masquerait exactement le défaut que
	# cette recette détecte. Un 403 sort d'ici et part en assertion — c'est un
	# échec, pas une latence.
	essais=0
	propagation=''
	while [ "$essais" -lt "$PROPAGATION_ESSAIS" ]; do
		code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$FIXTURE_URL/build/sonde.js")
		case "$code" in
			404|000) sleep "$PROPAGATION_PAS" ;;
			*) propagation="$code"; break ;;
		esac
		essais=$((essais + 1))
	done
	if [ -n "$propagation" ]; then
		SONDES_PRETES=1
	elif [ "$code" = '000' ]; then
		echo 'NON JOUÉ : rien ne répond sur le port — la stack ne tourne pas (bash docker/up.sh).'
		statut=1
	else
		echo 'NON JOUÉ : 404 persistant sur la sonde — arbre non propagé au conteneur.'
		statut=1
	fi
else
	# La garde ci-dessus a prouvé que le chemin n'existait pas avant nous : un
	# résidu partiel ne peut être que le nôtre, le trap doit l'emporter.
	FIXTURE_CREEE=1
	echo "NON JOUÉ : création de l'arbre de sondes impossible sous $FIXTURE."
	statut=1
fi

# Jamais accessibles : archive source de 3 Mo, outillage de build, code PHP.
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/source/massifs-13.full.geojson' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/identites.json' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/statuts/legende.config.php' 403
verifier '/wp-content/plugins/massifs-core/includes/ingest/prefecture/class-connector.php' 403
verifier '/wp-content/plugins/massifs-core/massifs-core.php' 403
verifier '/wp-content/plugins/massifs-core/data/massifs-13.php' 403
verifier '/wp-content/themes/massifs/index.php' 403
# Issue #20 — l'artefact de recette de la géométrie et son empreinte de référence
# ne sont PAS des fichiers destinés au navigateur. Ils ont quitté `data/` pour
# `build/` : la relocation est ce qui rend l'interdit vrai par construction, le
# garde-fou Apache ne fait que la doubler. L'invariant « data/ = servi,
# build/ = jamais servi » est opposable aux chaînes suivantes.
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/massifs-13.fidelite.json' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/reference.json' 403
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/package-lock.json' 403
# L'ancien emplacement public ne doit plus rien servir du tout.
verifier '/wp-content/plugins/massifs-core/data/massifs-13.fidelite.json' 404
# Le garde-fou de sous-arbres couvre aussi ce qui n'est pas .php sous includes/.
verifier '/wp-content/plugins/massifs-core/includes/ingest/prefecture/README.md' 403
# Doivent rester servis : rien de fonctionnel ne doit être cassé par les gardes.
verifier '/wp-content/plugins/massifs-core/data/massifs-13.geometrie.json' 200
verifier '/wp-content/themes/massifs/style.css' 200
# `assets/css/` et `assets/fonts/` sont DÉLIBÉRÉMENT épargnés par le garde-fou
# de sous-arbres : le CSS et les polices doivent être servis depuis NOTRE
# origine, aucun domaine tiers n'étant admis. La généralité « tout `assets/` est
# épargné » serait fausse — `assets/vendor/**/build|includes|node_modules/` est
# refusé par ce même garde-fou, et seul le `build/` est ré-accordé, par le bloc
# de grant en fin de `plugins-guard.conf` (sondes ci-dessous).
verifier '/wp-content/themes/massifs/assets/css/tokens.css' 200
verifier '/wp-content/themes/massifs/assets/fonts/atkinson-hyperlegible-next-var.woff2' 200
verifier '/wp-content/themes/massifs/assets/fonts/big-shoulders-display-var.woff2' 200
# Issue #30 — le `build/` d'une bibliothèque vendorisée est servi, et le grant
# qui l'ouvre ne fuit ni vers le bas ni latéralement. Sondes éphémères.
if [ -n "$SONDES_PRETES" ]; then
	verifier "$FIXTURE_URL/build/sonde.js" 200
	verifier "$FIXTURE_URL/build/sous/sonde.js" 200
	verifier "$FIXTURE_URL/build/sonde.php" 403
	# Seule sonde du re-refus : sans lui, le grant matcherait aussi ce descendant
	# et le servirait. Un vert ici est la preuve que le bloc de re-refus travaille.
	verifier "$FIXTURE_URL/build/node_modules/sonde.js" 403
	verifier "$FIXTURE_URL/node_modules/sonde.js" 403
	# Servi parce que NON REFUSÉ, jamais parce qu'accordé : `dist/` est hors de
	# l'alternation de refus. Sentinelle — passer cette ligne à 403 « par
	# symétrie » avec les autres créerait le défaut que l'issue #30 corrige.
	verifier "$FIXTURE_URL/dist/sonde.js" 200
	# Pas de listing du contenu accordé (`Options -Indexes`). À lire avec la
	# première sonde au vert : seule, cette ligne ne distingue pas « pas de
	# listing » de « répertoire refusé ». Le slash final est obligatoire, sans lui
	# on mesurerait la redirection 301 de mod_dir.
	verifier "$FIXTURE_URL/build/" 403
fi
# Fichier réel déjà commité : le `node_modules/` de l'extension reste fermé, sur
# un vrai refus d'autorisation et non sur un 404 déguisé.
verifier '/wp-content/plugins/massifs-core/includes/domain/massifs/build/node_modules/tinyqueue/README.md' 403
verifier '/' 200
verifier '/wp-login.php' 200
verifier '/wp-json/' 200

exit "$statut"
