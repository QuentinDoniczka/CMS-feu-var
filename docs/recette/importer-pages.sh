#!/usr/bin/env sh
#
# Import des trois pages éditoriales de l'issue #18 — « La démarche »,
# « Accessibilité », « Mentions légales ».
#
# POURQUOI CE SCRIPT EXISTE.
# Les gabarits de ces trois pages vivent dans le thème, mais leur PROSE est du
# CONTENU, pas du code (contrat #18, arbitrage A-1) : `MASTER.md` §11.3 est une
# liste fermée des phrases qu'une page publique a le droit de rédiger, et le §16
# tranche que ce type de rédaction « vient du contenu, jamais du code ». La
# copie vit donc sous docs/recette/contenu/, versionnée, et ce script la pousse
# en base.
#
# IDEMPOTENT. Rejouable autant de fois qu'on veut : il cherche la page par son
# slug, la crée si elle manque, la met à jour sinon. Il ne duplique ni page ni
# entrée de menu. Aucune suppression, jamais.
#
# CE QU'IL NE FAIT PAS. Il ne modifie pas docker/provision/ : le provisionnement
# durable est une issue de suivi. Ce script est l'outil de recette qui rend les
# trois pages observables — et donc la ligne de DoD « pages rédigées »
# vérifiable.
#
# USAGE, depuis la racine du dépôt, stack démarrée :
#   docker compose run --rm -v "$PWD/docs:/docs" wpcli sh /docs/recette/importer-pages.sh
#
# VÉRIFIER LE RÉSULTAT :
#   docker compose run --rm wpcli wp post list --post_type=page \
#     --fields=ID,post_title,post_name,post_status --allow-root
#
set -eu

CONTENU="${CONTENU:-/docs/recette/contenu}"

echo "== Import des pages éditoriales =="

# PAS de `echo "$PAGES" | while` : un `while` en aval d'un tube s'exécute dans un
# SOUS-SHELL, où `exit 1` ne quitte que le sous-shell. Le script continuerait
# jusqu'au menu après un abandon, et affecterait des entrées à des pages
# absentes — un échec qui se lit comme un succès.
#
# Le here-document ci-dessous (voir le `done <<'FIN'`) garde la boucle dans le
# shell courant, où `exit` et `set -e` portent réellement. Une boucle `while
# read` SANS redirection lirait l'entrée standard — c'est-à-dire rien — et
# n'importerait AUCUNE page tout en affichant un déroulé parfaitement normal :
# c'est la panne la plus trompeuse de ce script, et elle s'est produite.
#
# Colonnes : slug | titre | fichier de contenu | gabarit
while IFS='|' read -r slug titre fichier gabarit; do
	[ -z "$slug" ] && continue

	chemin="$CONTENU/$fichier"
	if [ ! -f "$chemin" ]; then
		echo "ABANDON : contenu introuvable — $chemin" >&2
		exit 1
	fi

	# `wp post list` plutôt que `wp post exists` : on veut l'ID pour décider
	# entre création et mise à jour, et un slug est unique par type de contenu.
	#
	# `--post_status=any` n'est pas décoratif : sans lui, `wp post list` ne voit
	# que les pages publiées. Une page laissée en brouillon (import interrompu,
	# dépublication manuelle) ne serait pas trouvée, une seconde serait créée, et
	# le cœur lui donnerait le slug `la-demarche-2`. L'idempotence tomberait
	# exactement là où on en a le plus besoin : au rejeu après incident.
	id=$(wp post list --post_type=page --post_status=any --name="$slug" --field=ID --posts_per_page=1 --allow-root 2>/dev/null || true)

	if [ -n "$id" ]; then
		wp post update "$id" "$chemin" \
			--post_title="$titre" \
			--post_status=publish \
			--allow-root >/dev/null
		echo "  mise à jour  $slug (ID $id)"
	else
		id=$(wp post create "$chemin" \
			--post_type=page \
			--post_title="$titre" \
			--post_name="$slug" \
			--post_status=publish \
			--porcelain \
			--allow-root)
		echo "  création     $slug (ID $id)"
	fi

	# Le gabarit est réaffecté à chaque passage : c'est ce qui rend le script
	# réparateur et pas seulement créateur. Un gabarit perdu (thème réinstallé,
	# page recréée à la main) se rattrape en rejouant l'import.
	wp post meta update "$id" _wp_page_template "$gabarit" --allow-root >/dev/null
done <<'FIN'
la-demarche|La démarche|la-demarche.html|templates/page-la-demarche.php
accessibilite|Accessibilité|accessibilite.html|templates/page-accessibilite.php
mentions-legales|Mentions légales|mentions-legales.html|templates/page-mentions-legales.php
FIN

echo "== Menu de pied =="

# Le pied ne code EN DUR aucun lien vers ces trois pages : templates/footer.php
# rend l'EMPLACEMENT de menu `pied` et se tait tant qu'aucun menu n'y est
# affecté (contrat #23, arbitrage A-6). Les trois entrées sont donc des entrées
# de MENU, et c'est ici qu'elles sont posées.
MENU="Pied de page"

# `tr -d '"'` n'est PAS cosmétique : WP-CLI met entre guillemets toute valeur CSV
# qui contient une espace, et « Pied de page » en contient. Sans ce filtre, la
# comparaison échoue au deuxième passage, `wp menu create` refuse un nom déjà
# pris, et `set -e` interrompt le script — l'idempotence tombe précisément là où
# on croyait l'avoir. Défaut trouvé en rejouant l'import, pas en le relisant.
if ! wp menu list --fields=name --format=csv --allow-root 2>/dev/null | tr -d '"' | grep -qx "$MENU"; then
	wp menu create "$MENU" --allow-root >/dev/null
	echo "  création du menu « $MENU »"
else
	echo "  menu « $MENU » déjà présent"
fi

for slug in la-demarche accessibilite mentions-legales; do
	id=$(wp post list --post_type=page --post_status=any --name="$slug" --field=ID --posts_per_page=1 --allow-root)
	[ -z "$id" ] && continue

	# Anti-duplication : on ne rajoute l'entrée que si aucun élément du menu ne
	# pointe déjà sur cette page. Sans ce test, chaque rejeu empilerait une
	# entrée de plus, et le pied afficherait trois fois la même chose.
	if wp menu item list "$MENU" --fields=object_id --format=csv --allow-root 2>/dev/null | grep -qx "$id"; then
		echo "  entrée déjà présente  $slug"
	else
		wp menu item add-post "$MENU" "$id" --allow-root >/dev/null
		echo "  entrée ajoutée        $slug"
	fi
done

# Affectation à l'emplacement déclaré par le thème (functions.php,
# massifs_emplacements_de_menu()). Réaffecter un menu déjà affecté est sans
# effet : l'opération est idempotente côté cœur.
wp menu location assign "$MENU" pied --allow-root >/dev/null
echo "  emplacement « pied » affecté"

echo "== Terminé =="
