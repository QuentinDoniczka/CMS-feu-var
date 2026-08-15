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

echo "==> Rôle massifs_gestionnaire (vocabulaire gelé par le contrat de l'issue #13)"
# Ce rôle et ses trois capacités (massifs_publier_statuts, massifs_consulter_historique,
# massifs_gerer_gestionnaires) sont installés par l'extension massifs-core elle-même,
# à l'activation ci-dessus et réconciliés à chaque chargement
# (wp-content/plugins/massifs-core/includes/security/roles/Installation.php, source
# unique du vocabulaire : Capacites.php). Ce script ne le crée JAMAIS à la main : un
# second exemplaire fabriqué ici divergerait au premier changement de capacités côté
# extension, sans la moindre erreur pour le signaler. On se contente de vérifier qu'il
# est bien là avant de rattacher le compte de démonstration.
if ! $WP role list --field=role 2>/dev/null | grep -qx massifs_gestionnaire; then
	echo "   !! rôle 'massifs_gestionnaire' absent après activation de massifs-core." >&2
	echo "      L'extension est censée l'installer elle-même (includes/security/roles/module.php)." >&2
	echo "      Vérifier son activation ci-dessus avant de poursuivre." >&2
	exit 1
fi
echo "   rôle 'massifs_gestionnaire' présent (installé par l'extension, capacités non dupliquées ici)."

echo "==> Compte gestionnaire de démonstration"
MANAGER_USER="${WP_MANAGER_USER:-gestionnaire-demo}"
MANAGER_ROLE="massifs_gestionnaire"
if ! $WP user get "$MANAGER_USER" --field=ID >/dev/null 2>&1; then
	$WP user create "$MANAGER_USER" "${WP_MANAGER_EMAIL:-gestionnaire@massifs.local}" \
		--role="$MANAGER_ROLE" \
		--user_pass="${WP_MANAGER_PASSWORD:-gestionnaire}" \
		--display_name="Gestionnaire (démo)"
	echo "   compte '$MANAGER_USER' créé avec le rôle '$MANAGER_ROLE'."
else
	$WP user set-role "$MANAGER_USER" "$MANAGER_ROLE" || true
	echo "   compte '$MANAGER_USER' déjà présent, rôle '$MANAGER_ROLE' réaffirmé."
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
