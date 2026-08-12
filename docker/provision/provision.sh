#!/bin/sh
# Provisionnement idempotent de la stack MASSIFS.
# Exécuté à l'intérieur du conteneur wpcli :
#   docker compose run --rm wpcli sh /provision/provision.sh
# (docker/up.sh fait ça pour vous).
#
# Rejouer ce script sur une stack déjà provisionnée ne doit rien casser ni
# dupliquer de données : chaque étape vérifie l'état avant d'agir.
set -eu

WP="wp --path=/var/www/html --allow-root"

TARGET_URL="http://localhost:${WORDPRESS_PORT:-3002}"

echo "==> Installation de WordPress"
if ! $WP core is-installed 2>/dev/null; then
	$WP core install \
		--url="$TARGET_URL" \
		--title="${WP_TITLE:-MASSIFS}" \
		--admin_user="${WP_ADMIN_USER:-admin}" \
		--admin_password="${WP_ADMIN_PASSWORD:-admin}" \
		--admin_email="${WP_ADMIN_EMAIL:-admin@massifs.local}" \
		--locale=fr_FR \
		--skip-email
	echo "   WordPress installé."
else
	echo "   déjà installé, on continue."
fi

echo "==> Adresse du site (siteurl / home)"
# `core install` ne fixe siteurl/home qu'à la création. Sur une stack déjà
# provisionnée (volume existant), changer WORDPRESS_PORT dans .env ne les met
# pas à jour tout seul — WordPress continuerait de répondre sur l'ancien port
# et un accès au nouveau port serait redirigé vers l'ancien (panne apparente
# pour qui ne connaît pas cette mécanique). On resynchronise donc les deux
# options à chaque provisionnement, idempotent (aucun changement si déjà à
# jour) : le port hôte publié et l'adresse en base bougent toujours ensemble.
CURRENT_SITEURL="$($WP option get siteurl 2>/dev/null || true)"
if [ "$CURRENT_SITEURL" != "$TARGET_URL" ]; then
	$WP option update siteurl "$TARGET_URL"
	$WP option update home "$TARGET_URL"
	echo "   siteurl/home : '$CURRENT_SITEURL' -> '$TARGET_URL'."
else
	echo "   siteurl/home déjà à jour ($TARGET_URL)."
fi

echo "==> Locale fr_FR"
if ! $WP language core is-installed fr_FR 2>/dev/null; then
	if $WP language core install fr_FR --activate 2>/dev/null; then
		echo "   pack de langue fr_FR installé et activé."
	else
		echo "   !! téléchargement du pack fr_FR impossible (pas d'accès réseau ?) — locale interface non traduite, on continue."
	fi
else
	$WP language core activate fr_FR >/dev/null 2>&1 || true
	echo "   fr_FR déjà installé."
fi
$WP option update WPLANG fr_FR >/dev/null 2>&1 || true

echo "==> Fuseau horaire"
# Dépendance signalée par le contrat gelé de l'issue #3
# (docs/contracts/issue-3.md, « Dépendances hors empreinte », #1) : sans
# effet sur le domaine (Horloge.php fige déjà le fuseau), mais évite que
# l'administration affiche des heures UTC au gestionnaire sur un site dont
# tout le propos est le statut du jour. `option update` est idempotent par
# construction (positionne la valeur, ne duplique rien).
$WP option update timezone_string 'Europe/Paris'

echo "==> Activation du thème massifs"
$WP theme activate massifs

echo "==> Activation de l'extension massifs-core"
$WP plugin activate massifs-core

echo "==> Suppression des thèmes tiers/par défaut (Twenty*)"
for theme in $($WP theme list --field=name --status=inactive 2>/dev/null || true); do
	case "$theme" in
	twenty*)
		echo "   - $theme"
		$WP theme delete "$theme" || true
		;;
	esac
done

echo "==> Suppression des extensions tierces (akismet, hello dolly)"
for plugin in akismet hello; do
	if $WP plugin is-installed "$plugin" 2>/dev/null; then
		$WP plugin deactivate "$plugin" >/dev/null 2>&1 || true
		$WP plugin delete "$plugin" || true
		echo "   - $plugin supprimé"
	fi
done

echo "==> Permaliens"
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush --hard

echo "==> Rôle gestionnaire"
if ! $WP role list --field=role 2>/dev/null | grep -qx gestionnaire; then
	$WP role create gestionnaire "Gestionnaire" --clone=subscriber
	echo "   rôle 'gestionnaire' créé (capacités minimales, calquées sur 'subscriber' : accès à l'administration, aucune capacité de contenu)."
else
	echo "   rôle 'gestionnaire' déjà présent."
fi

echo "==> Purge des capacités de contenu du rôle gestionnaire"
# Le rôle 'gestionnaire' ne doit porter AUCUNE capacité de contenu (brief §6 :
# consulter/mettre à jour les statuts, voir l'historique — rien d'autre). Et
# c'est le rôle du compte de démonstration dont le brief §6 impose de publier
# les identifiants sur le site public : `unfiltered_html` sur ce compte serait
# un XSS stocké offert à quiconque lit la page de démo du portail.
# On énumère et retire explicitement les capacités éditoriales/administratives
# à *chaque* provisionnement — idempotent (`wp cap remove` sur une capacité
# déjà absente ne fait rien) — pour couvrir aussi bien un rôle fraîchement créé
# que ce même rôle hérité d'un ancien clone d'`editor` sur une stack existante.
# Les capacités métier du portail (mise à jour des statuts, lecture de
# l'historique) sont ajoutées par l'extension massifs-core elle-même (chaîne
# 'securite', épique 5) — jamais par ce script de provisionnement.
# Inclut aussi les niveaux `level_1`…`level_10` : système de "user level"
# historique de WordPress (antérieur aux capacités, WP < 3.0), déprécié mais
# encore vérifié par du code ancien comme un proxy des droits d'édition —
# un clone d'`editor` porte `level_7`. On ne garde que `level_0` (subscriber).
CAPACITES_INTERDITES="edit_posts edit_others_posts edit_published_posts edit_private_posts \
	publish_posts delete_posts delete_others_posts delete_published_posts delete_private_posts \
	read_private_posts edit_pages edit_others_pages edit_published_pages edit_private_pages \
	publish_pages delete_pages delete_others_pages delete_published_pages delete_private_pages \
	read_private_pages manage_categories manage_links moderate_comments upload_files \
	unfiltered_html unfiltered_upload edit_theme_options switch_themes edit_users delete_users \
	create_users list_users promote_users remove_users manage_options activate_plugins \
	edit_plugins delete_plugins install_plugins edit_themes delete_themes install_themes \
	update_core update_plugins update_themes export import \
	level_1 level_2 level_3 level_4 level_5 level_6 level_7 level_8 level_9 level_10"
for cap in $CAPACITES_INTERDITES; do
	$WP cap remove gestionnaire "$cap" >/dev/null 2>&1 || true
done
$WP cap add gestionnaire read >/dev/null 2>&1 || true
echo "   capacités de contenu/administration retirées ; 'read' garanti (accès admin minimal)."

echo "==> Compte gestionnaire de démonstration"
MANAGER_USER="${WP_MANAGER_USER:-gestionnaire-demo}"
if ! $WP user get "$MANAGER_USER" --field=ID >/dev/null 2>&1; then
	$WP user create "$MANAGER_USER" "${WP_MANAGER_EMAIL:-gestionnaire@massifs.local}" \
		--role=gestionnaire \
		--user_pass="${WP_MANAGER_PASSWORD:-gestionnaire}" \
		--display_name="Gestionnaire (démo)"
	echo "   compte '$MANAGER_USER' créé."
else
	$WP user set-role "$MANAGER_USER" gestionnaire || true
	echo "   compte '$MANAGER_USER' déjà présent."
fi

echo "==> Fixtures"
FIXTURES_DIR="/provision/fixtures"
if [ -f "$FIXTURES_DIR/seed.php" ]; then
	$WP eval-file "$FIXTURES_DIR/seed.php"
	echo "   fixtures rejouées."
else
	echo "   aucun seed.php pour l'instant (voir $FIXTURES_DIR/README.md) — étape prête, sans effet."
fi

echo "==> Provisionnement terminé."
