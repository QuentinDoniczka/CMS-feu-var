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

echo "==> Installation de WordPress"
if ! $WP core is-installed 2>/dev/null; then
	$WP core install \
		--url="http://localhost:${WORDPRESS_PORT:-8080}" \
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
	$WP role create gestionnaire "Gestionnaire" --clone=editor
	echo "   rôle 'gestionnaire' créé (droits calqués sur editor — affinés ensuite par la chaîne 'securite')."
else
	echo "   rôle 'gestionnaire' déjà présent."
fi

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
