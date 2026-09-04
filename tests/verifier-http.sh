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
# Sentinelle nominative sur le fichier qui porte les bornes de la saison.
verifier '/wp-content/plugins/massifs-core/includes/domain/fraicheur/saison.config.php' 403
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

# ÉPIC 5 — TOUT FICHIER ENFILÉ POUR UN NAVIGATEUR DOIT ÊTRE SERVI, OÙ QU'IL VIVE.
#
# Défaut trouvé le 15 août 2026, à la passe d'intégration du lot #13/#14/#15 :
# les deux écrans du portail enfilent leur feuille de style depuis
# `plugins/massifs-core/includes/**`, que le second garde-fou de
# `docker/wordpress/plugins-guard.conf` REFUSE — « includes/ à n'importe quelle
# profondeur ». Résultat : `wp_enqueue_style()` correct, `<link>` présent, aucune
# erreur PHP, aucune requête « en échec », et les DEUX écrans du portail rendus
# entièrement dépouillés. Un mode de panne invisible à tout contrôle qui n'ouvre
# pas la page.
#
# CORRIGÉ le 16 août 2026 (`cb4be90`, `bd060b0`) : les deux feuilles ont été
# DÉPLACÉES vers `massifs-core/assets/css/`, hors de l'alternation de refus. Le
# garde-fou n'a PAS été élargi — c'est la moitié importante de la correction, et
# les deux sondes ci-dessous la tiennent des deux côtés.
#
# Sens de lecture : la feuille est servie à son NOUVEAU chemin (200)…
verifier '/wp-content/plugins/massifs-core/assets/css/ecran-publication.css' 200
verifier '/wp-content/plugins/massifs-core/assets/css/historique.css' 200
# …et l'ANCIEN chemin sous `includes/` reste REFUSÉ (403). Ces deux lignes sont
# des sentinelles de l'invariant de l'issue #20 : si l'une d'elles vire au 200,
# quelqu'un a élargi `plugins-guard.conf` au lieu de déplacer un fichier, et le
# `403` sous `includes/` — qui protège tout le code PHP de l'extension — a
# cessé d'être vrai. Ne jamais les retirer « parce que le fichier n'existe plus » :
# un 403 est rendu par le garde-fou AVANT tout constat d'existence, c'est
# précisément ce qu'elles mesurent.
verifier '/wp-content/plugins/massifs-core/includes/admin/ecran-publication/assets/css/ecran-publication.css' 403
verifier '/wp-content/plugins/massifs-core/includes/admin/historique/assets/css/historique.css' 403
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

# --- Issues #7 et #9 : ce que le navigateur demande réellement pour la carte.
#
# Le fond de carte est la ressource la plus exposée à une fuite tierce : c'est le
# seul artefact du site dont l'équivalent « naturel » est un CDN public. Les
# sondes ci-dessous prouvent qu'il vient de chez nous, qu'il est servi, et que ce
# qui l'entoure reste fermé.
#
# La version est un SEGMENT DE CHEMIN : elle est relue dans les métadonnées, et
# jamais écrite ici — un chemin codé en dur mentirait au premier rebuild.
VERSION_TUILES="$(sed -n "s/.*'version' *=> *'\([0-9a-f]\{8\}\)'.*/\1/p" \
	"$RACINE/wp-content/plugins/massifs-core/data/tuiles/fond-13.php" | head -1)"

if [ -z "$VERSION_TUILES" ]; then
	echo 'ECHEC : version de la pyramide de tuiles illisible dans data/tuiles/fond-13.php'
	statut=1
else
	echo "version de pyramide relue dans les métadonnées : $VERSION_TUILES"
	# Une tuile à chaque extrémité de la pyramide : le plancher z5 et le plafond
	# z12, qui existe pour la netteté sur écran dense (contrat #9, A-7).
	verifier "/wp-content/plugins/massifs-core/data/tuiles/$VERSION_TUILES/5/16/11.png" 200
	verifier "/wp-content/plugins/massifs-core/data/tuiles/$VERSION_TUILES/12/2102/1500.png" 200
	# Une tuile est-elle un VRAI PNG ? Un 404 déguisé en 200 passerait la sonde
	# précédente sans qu'un pixel soit peint.
	signature=$(curl -s "$BASE/wp-content/plugins/massifs-core/data/tuiles/$VERSION_TUILES/8/131/93.png" \
		| head -c 8 | od -An -tx1 | tr -d ' \n')
	if [ "$signature" = '89504e470d0a1a0a' ]; then
		printf 'ok    %-95s %s\n' 'signature PNG de la tuile z8/131/93' "$signature"
	else
		printf 'ECHEC %-95s %s (attendu 89504e470d0a1a0a)\n' 'signature PNG de la tuile z8/131/93' "$signature"
		statut=1
	fi
	# Aucun listing de la pyramide (`Options -Indexes` du .htaccess livré).
	verifier "/wp-content/plugins/massifs-core/data/tuiles/$VERSION_TUILES/8/" 403

	# Politique de cache des tuiles. Le `.htaccess` livré par l'extension pose
	# `Cache-Control: public, max-age=31536000, immutable` — ce qui est honnête,
	# la version étant un SEGMENT DE CHEMIN. Mais la directive est enveloppée dans
	# `<IfModule mod_headers.c>` : si le module n'est pas chargé, elle est ignorée
	# EN SILENCE. On mesure donc l'en-tête réellement servi, et l'on affirme le
	# minimum qui, lui, ne dépend d'aucun module : une tuile porte toujours un
	# validateur, donc un rechargement coûte au pire un 304 et jamais 4 Ko.
	entetes_tuile=$(curl -sI "$BASE/wp-content/plugins/massifs-core/data/tuiles/$VERSION_TUILES/8/131/93.png" | tr -d '\r')
	cache_tuile=$(printf '%s\n' "$entetes_tuile" | grep -i '^cache-control:' | cut -d' ' -f2-)

	if printf '%s\n' "$entetes_tuile" | grep -qiE '^(etag|last-modified):'; then
		printf 'ok    %-95s %s\n' 'la tuile porte un validateur de cache (ETag ou Last-Modified)' 'présent'
	else
		printf 'ECHEC %-95s %s\n' 'la tuile porte un validateur de cache (ETag ou Last-Modified)' 'aucun'
		statut=1
	fi

	if printf '%s\n' "$cache_tuile" | grep -q 'immutable'; then
		printf 'ok    %-95s %s\n' 'cache long des tuiles (Cache-Control immutable)' "$cache_tuile"
	else
		echo "note  Cache-Control des tuiles : «${cache_tuile:-absent}» — le .htaccess de l'extension"
		echo "      pose bien « public, max-age=31536000, immutable », mais la directive est sous"
		echo "      <IfModule mod_headers.c> et mod_headers n'est PAS chargé dans cette image Apache."
		echo "      Conséquence : chaque visite revalide les tuiles au lieu de les servir du cache."
		echo "      COÛT DE PERFORMANCE, jamais une panne — à porter à docker-cms, pas à l'extension."
	fi
fi

# Les métadonnées du fond sont lues par PHP en interne, jamais par une requête.
verifier '/wp-content/plugins/massifs-core/data/tuiles/fond-13.php' 403
# Le pipeline de build ne doit rien servir : il vit sous `includes/`, 403 par
# construction, et c'est ce qui rend l'invariant vrai sans vigilance.
verifier '/wp-content/plugins/massifs-core/includes/ingest/tuiles/fond.php' 403
verifier '/wp-content/plugins/massifs-core/includes/ingest/tuiles/build/construire.mjs' 403

# L'image statique du repli sans JavaScript — la ligne §5.5 du brief en dépend.
verifier '/wp-content/themes/massifs/assets/img/carte-statique.png' 200

# Leaflet vendorisé : servi, licence et provenance comprises. Et les DEUX
# absences qui comptent — la source map, dont la ligne a été retirée du build, et
# les images du CSS amont, qu'aucune API appelée ne réclame (contrat #7 §10).
verifier '/wp-content/themes/massifs/assets/vendor/leaflet/leaflet.js' 200
verifier '/wp-content/themes/massifs/assets/vendor/leaflet/leaflet.css' 200
verifier '/wp-content/themes/massifs/assets/vendor/leaflet/LICENSE' 200
verifier '/wp-content/themes/massifs/assets/vendor/leaflet/leaflet.js.map' 404
verifier '/wp-content/themes/massifs/assets/vendor/leaflet/images/marker-icon.png' 404

# Issue #8 — le point d'accès public en lecture répond sans authentification, et
# aucune écriture n'est atteignable dans son espace de noms. Le comportement
# détaillé est éprouvé par `tests/scenarios/22-api-publique-statuts.php`.
verifier '/wp-json/massifs/v1/statuts' 200
code_post=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/wp-json/massifs/v1/statuts")
if [ "$code_post" = '404' ] || [ "$code_post" = '405' ]; then
	printf 'ok    %-95s %s\n' 'POST /wp-json/massifs/v1/statuts (aucune écriture atteignable)' "$code_post"
else
	printf 'ECHEC %-95s %s (attendu 404 ou 405)\n' 'POST /wp-json/massifs/v1/statuts' "$code_post"
	statut=1
fi

exit "$statut"
