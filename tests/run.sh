#!/usr/bin/env bash
# Lance les scénarios d'intégration MASSIFS dans la stack Docker du dépôt.
#
#   bash tests/run.sh                      # tous les scénarios
#   bash tests/run.sh 13                   # les scénarios dont le nom contient « 13 »
#   bash tests/run.sh jours-consecutifs    # idem, par mot-clé
#
# Prérequis : la stack tourne (`bash docker/up.sh`). Voir tests/README.md.
set -uo pipefail

# Git Bash (MSYS) réécrit les chemins Unix passés à docker.exe : à neutraliser.
export MSYS_NO_PATHCONV=1

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE"

# Les scénarios sont montés en lecture seule dans le conteneur d'outillage.
# Ils sont dans le dépôt, jamais dans l'image.
MONTAGE="$RACINE/tests:/massifs-tests:ro"

# Un scénario suffixé `.arme.php` a besoin que le connecteur soit armé AVANT
# l'amorçage de WordPress : le modèle d'URL est redéfini vers notre propre
# serveur, ce qui réarme le connecteur tout en rendant la source réelle
# inatteignable par construction.
ARMEMENT='define("MASSIFS_PREFECTURE_JSON_URL_TEMPLATE","http://wordpress/massifs-bouchon/{date}.json");'

filtre="${1:-}"
total_ok=0
total_ko=0
echecs=()

for fichier in tests/scenarios/*.php; do
	nom="$(basename "$fichier")"

	if [ -n "$filtre" ] && [[ "$nom" != *"$filtre"* ]]; then
		continue
	fi

	echo "=============================== $nom"

	if [[ "$nom" == *.arme.php ]]; then
		sortie=$(docker compose run --rm -T -v "$MONTAGE" wpcli \
			wp --exec="$ARMEMENT" eval-file "/massifs-tests/scenarios/$nom" 2>&1)
	else
		sortie=$(docker compose run --rm -T -v "$MONTAGE" wpcli \
			wp eval-file "/massifs-tests/scenarios/$nom" 2>&1)
	fi

	echo "$sortie" | grep -v -e 'level=warning' -e 'Container massifs' -e '^$'

	bilan=$(echo "$sortie" | grep -oE 'BILAN [^:]+: [0-9]+ ok, [0-9]+ echec' | tail -1)
	ok=$(echo "$bilan" | grep -oE '[0-9]+ ok' | grep -oE '[0-9]+')
	ko=$(echo "$bilan" | grep -oE '[0-9]+ echec' | grep -oE '[0-9]+')
	total_ok=$(( total_ok + ${ok:-0} ))
	total_ko=$(( total_ko + ${ko:-0} ))

	if [ -z "$bilan" ] || [ "${ko:-1}" -ne 0 ]; then
		echecs+=("$nom")
	fi
done

echo
echo "======================================================================"
printf 'TOTAL : %d assertion(s) vertes, %d rouge(s)\n' "$total_ok" "$total_ko"

if [ ${#echecs[@]} -gt 0 ]; then
	printf 'SCÉNARIOS EN ÉCHEC : %s\n' "${echecs[*]}"
	exit 1
fi

echo 'Tous les scénarios passent.'
