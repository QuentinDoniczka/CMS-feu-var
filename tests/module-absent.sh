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
# CE SCRIPT AFFIRME, IL NE DÉCRIT PLUS. Il sort 0 si tout passe, 1 sinon, 130 sur
# Ctrl-C. Jusqu'à l'issue #94 il imprimait un constat et sortait toujours 0 : il
# a donc affiché « ERREUR FATALE DANS LA PAGE » sur `domain/statuts` pendant que
# la recette rendait vert. Un script qui observe sans affirmer ne mesure rien.
#
# PAS DE CAS `domain/fraicheur` ICI, ET C'EST DÉLIBÉRÉ.
#
# L'arête `statuts -> fraicheur` est STRUCTURELLE : le module `statuts` nomme des
# symboles de `fraicheur` à même son code, et non derrière un chargeur tolérant.
# Masquer `fraicheur` fait donc toujours tomber le site À TRAVERS `statuts`, y
# compris après le correctif de l'issue #94. Ajouter ce cas ici, c'est commiter
# une assertion rouge à demeure. C'est un défaut réel et distinct, à traiter par
# une issue de suivi.
#
# L'ARBRE DOIT ÊTRE RENDU TEL QU'IL A ÉTÉ TROUVÉ, et c'est ce qu'on affirme —
# jamais qu'il est propre. Trois chaînes partagent cet arbre et y ont du travail
# non commité : exiger un `git status` vide rendrait ce script rouge pour une
# raison étrangère à ce qu'il mesure. On compare donc un instantané d'avant à un
# instantané d'après, pris sur le seul sous-arbre que ce script déplace — la
# portée est justifiée à l'endroit où l'instantané est pris. C'est strictement
# plus fort que la propreté : une amputation dans un arbre déjà sale est vue
# elle aussi.
#
# Prérequis : la stack tourne (`bash docker/up.sh`).
set -uo pipefail
export MSYS_NO_PATHCONV=1

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RACINE"

INCLUDES_REL='wp-content/plugins/massifs-core/includes'
INCLUDES="$RACINE/$INCLUDES_REL"
GARAGE="$(mktemp -d)"
TRAVAIL="$(mktemp -d)"
PORT="$(grep -E "^WORDPRESS_PORT=" "$RACINE/.env" 2>/dev/null | cut -d= -f2)"
# Repli à 3002, port canonique du projet ; le fait est détenu par
# `tests/verifier-http.sh`, qui l'établit.
SITE="http://localhost:${PORT:-3002}/"
statut=0

# Module actuellement déplacé, s'il y en a un. Posé JUSTE AVANT le `mv` qui
# déplace, effacé APRÈS le `mv` qui restaure ET SEULEMENT S'IL A RÉUSSI — voir
# l'effacement conditionnel en fin d'`essai`, qui dit pourquoi. Un seul module
# est masqué à la fois, une seule paire suffit donc, et elle se vérifie à l'œil.
EN_COURS_SOURCE=''
EN_COURS_PARQUE=''

verifier() {
	local libelle="$1" obtenu="$2" attendu="$3"
	if [ "$obtenu" = "$attendu" ]; then
		printf 'ok    %-70s %s\n' "$libelle" "$obtenu"
	else
		printf 'ECHEC %-70s %s (attendu %s)\n' "$libelle" "$obtenu" "$attendu"
		statut=1
	fi
}

nettoyer() {
	# Le cas qui compte : une interruption entre les deux `mv`. Sans ce bloc, un
	# répertoire entier de l'extension reste dans un `mktemp -d` et l'arbre
	# partagé est amputé pour les trois chaînes, sans branche d'où le reprendre.
	# La triple garde évite d'écraser un répertoire qu'une chaîne sœur aurait
	# légitimement recréé entre-temps.
	if [ -n "$EN_COURS_PARQUE" ] && [ -d "$EN_COURS_PARQUE" ] && [ ! -e "$EN_COURS_SOURCE" ]; then
		mv "$EN_COURS_PARQUE" "$EN_COURS_SOURCE"
		# Rien à écrire dans `$statut` : ce gestionnaire ne tourne qu'APRÈS que le
		# code de sortie a été figé, et tout chemin qui arrive ici est déjà sorti
		# non nul. Le signal, ici, est la ligne imprimée ci-dessus.
		echo "!! restauration de secours : $EN_COURS_SOURCE"
	fi

	# MÊME RAISONNEMENT QUE DANS `essai`, ET IL S'APPLIQUE AUSSI ICI : le `mv` de
	# secours ci-dessus peut échouer, ou la triple garde peut l'avoir refusé.
	# `$GARAGE` n'est donc effacé que s'il est VIDE — sinon on détruirait la seule
	# copie du module, exactement l'amputation que ce gestionnaire existe pour
	# empêcher. Un `mktemp -d` orphelin dont on imprime le chemin coûte moins cher
	# qu'un répertoire de l'extension perdu sans branche d'où le reprendre.
	if [ -n "$(ls -A "$GARAGE" 2>/dev/null)" ]; then
		echo "!! module NON restauré, conservé ici : $GARAGE"
		ls -A "$GARAGE"
	else
		rm -rf "$GARAGE"
	fi

	rm -rf "$TRAVAIL"
}
# Ce gestionnaire n'appelle JAMAIS `exit` : bash conserve ainsi le code du
# `exit "$statut"` final, ou celui du signal.
trap nettoyer EXIT
# Un gestionnaire INT/TERM qui se contente de nettoyer rend la main, et le
# script REPREND là où il en était, arbre restauré : les essais restants
# partent en échec pour rien, et une interruption tardive sortirait 0 — un vert
# sur une exécution interrompue. On sort donc explicitement (128 + numéro de
# signal) ; le trap EXIT ci-dessus fait le nettoyage, une seule fois.
trap 'exit 130' INT
trap 'exit 143' TERM

# PORTÉE DE L'INSTANTANÉ : `massifs-core/includes`, et c'est exactement le sujet
# de l'assertion — ce script a-t-il rendu chacun des répertoires qu'il a
# déplacés. Les trois `essai` ci-dessous ne déplacent que des chemins sous ce
# répertoire ; rien d'autre n'est à leur portée.
#
# CETTE ASSERTION EST UN AJOUT, PAS UN RESSERREMENT. Jusqu'à cette issue, ce
# script n'affirmait rien du tout : il imprimait l'état de l'arbre et sortait 0
# en toutes circonstances. Le commit qui porte cette ligne est le premier à faire
# de l'intégrité de l'arbre une condition de sortie — il n'y avait aucune portée
# antérieure à réduire, et personne ne doit lire celle-ci comme le vestige d'une
# portée plus large.
#
# POURQUOI CE RÉPERTOIRE ET PAS TOUT `wp-content` : l'arbre de travail est
# partagé entre chaînes de développement (`CLAUDE.md`, §Conventions, isolation à
# l'exécution). Une chaîne sœur qui écrit ailleurs pendant l'exécution ferait
# rougir une assertion large pour un travail qui n'est pas le sujet de ce script
# — et une assertion qui rougit pour autrui est une assertion qu'on apprend à
# ignorer, c'est-à-dire le vice que cette issue retire d'ici. L'état large de
# `wp-content` reste imprimé plus bas, en note : du contexte, jamais un juge.
# Tout ce que ce script peut abîmer reste couvert — un module qu'il ne rend pas
# le fait rougir.
git status --porcelain -- "$INCLUDES_REL" > "$TRAVAIL/git-avant.txt"

# TROIS SIGNAUX INDÉPENDANTS, ET CE QU'ILS COUVRENT CHACUN.
#
#  1. le code HTTP     — porte le défaut de l'issue #94 : 500 au lieu de 200.
#  2. le marqueur      — le corps de la page d'erreur du cœur. Porte le même
#                        défaut, et survit si un jour le code n'était plus 500.
#  3. le </html>       — NE DÉTECTE PAS le défaut #94. Mesuré le 4 septembre 2026
#                        sur la stack : `WP_Fatal_Error_Handler` rend un document
#                        COMPLET et bien formé, `</html>` compris, sur ses 500 —
#                        la page d'erreur n'était pas tronquée. Cette
#                        sonde vaut pour une VRAIE troncature — crash hors du
#                        gestionnaire d'erreurs, OOM, mandataire qui coupe le
#                        flux — où ni la ligne de statut ni le marqueur textuel
#                        ne diraient quoi que ce soit. Elle est aussi la seule
#                        qui tienne si WordPress change la formulation de son
#                        message ou si `WP_DEBUG_DISPLAY` est coupé.
#
# Aucune table de code attendu par module. Le jour où l'un des trois casse, la
# ligne de moindre résistance serait d'y écrire 500 : c'est l'affaiblissement
# d'assertion que `docs/decisions/isolation-des-chaines-paralleles.md` §5 interdit.
essai() {
	local relatif="$1"
	local etiquette="$2"
	local source="$INCLUDES/$relatif"
	local parque="$GARAGE/$etiquette"

	echo "=============================== module masqué : includes/$relatif"

	# Un module introuvable veut dire que la recette sonde un chemin qui n'existe
	# plus : elle a cessé de mesurer quoi que ce soit, en silence.
	if [ ! -d "$source" ]; then
		echo "!! introuvable : $source"
		verifier "$etiquette : module présent avant masquage" 'non' 'oui'
		return
	fi

	EN_COURS_SOURCE="$source"
	EN_COURS_PARQUE="$parque"
	mv "$source" "$parque"

	# La page est capturée en mémoire : sous Git Bash, `curl` est un binaire
	# Windows qui ne sait pas résoudre un chemin MSYS de type /tmp/…
	local reponse code corps marqueur ferme
	reponse=$(curl -s -w '\n%{http_code}' "$SITE")
	code="${reponse##*$'\n'}"
	corps="${reponse%$'\n'*}"
	# Taille imprimée pour information seulement, jamais affirmée : `${#corps}`
	# compte des caractères, pas des octets.
	echo "accueil sans le module : HTTP $code, ${#corps} caractères"

	if printf '%s' "$corps" | grep -qiE 'erreur critique|critical error|Fatal error'; then
		marqueur='oui'
	else
		marqueur='non'
	fi

	case "$corps" in
		*'</html>'*) ferme='oui' ;;
		*) ferme='non' ;;
	esac

	verifier "$etiquette : accueil" "$code" 200
	verifier "$etiquette : aucun marqueur d'erreur fatale" "$marqueur" 'non'
	verifier "$etiquette : document fermé" "$ferme" 'oui'

	# `set -uo pipefail` est posé SANS `-e`, et `pipefail` seul ne fait pas sortir
	# le script. C'est la capture dans une variable qui rend le statut observable,
	# comme dans `tests/run.sh`.
	local sortie bilan ko restaure
	sortie=$(docker compose run --rm -T -v "$RACINE/tests:/massifs-tests:ro" wpcli \
		wp eval-file /massifs-tests/outils/module-absent.php "$relatif" 2>&1)
	echo "$sortie" | grep -v -e 'level=warning' -e 'Container massifs' -e '^$'

	bilan=$(echo "$sortie" | grep -oE 'BILAN [^:]+: [0-9]+ ok, [0-9]+ echec' | tail -1)
	ko=$(echo "$bilan" | grep -oE '[0-9]+ echec' | grep -oE '[0-9]+')
	# Ligne `BILAN` absente ⇒ rouge, d'où le repli à 1 : un conteneur qui meurt
	# avant `t_bilan()` rend zéro assertion et zéro échec — vert par le vide,
	# jumeau exact du défaut que cette issue corrige.
	verifier "$etiquette : volet PHP" "${ko:-1}" 0

	mv "$parque" "$source"
	if [ -d "$source" ]; then
		restaure='oui'
	else
		restaure='non'
	fi
	verifier "$etiquette : module restauré" "$restaure" 'oui'

	# EFFACEMENT CONDITIONNEL, ET C'EST TOUT LE POINT. Un `mv` de restauration qui
	# échoue laisse le module dans `$GARAGE` et l'arbre amputé : effacer la paire
	# là désarmerait le trap EXIT à l'instant précis où il sert, et le
	# `rm -rf "$GARAGE"` détruirait la seule copie du répertoire. La paire ne se
	# vide donc qu'une fois le module revenu à sa place.
	if [ 'oui' = "$restaure" ]; then
		EN_COURS_SOURCE=''
		EN_COURS_PARQUE=''
	fi
}

essai 'domain/massifs' 'massifs'
essai 'ingest/prefecture' 'prefecture'
essai 'domain/statuts' 'statuts'

echo '=============================== état de massifs-core/includes après restauration'
git status --porcelain -- "$INCLUDES_REL" > "$TRAVAIL/git-apres.txt"

if diff -u "$TRAVAIL/git-avant.txt" "$TRAVAIL/git-apres.txt" > "$TRAVAIL/git-diff.txt"; then
	rendu='oui'
else
	rendu='non'
fi
verifier 'modules rendus tels qu’ils ont été trouvés' "$rendu" 'oui'

if [ 'oui' != "$rendu" ]; then
	cat "$TRAVAIL/git-diff.txt"
fi

# NOTE, ET UNE NOTE NE JUGE JAMAIS. L'état large de `wp-content` est du contexte
# utile à qui relit une exécution — il n'entre pas dans `$statut`, l'assertion
# ci-dessus ayant déjà tranché sur son propre sujet.
large="$(git status --porcelain -- wp-content)"
if [ -n "$large" ]; then
	echo 'note  wp-content porte des modifications non commitées :'
	printf '%s\n' "$large"
	echo '      L’arbre est partagé par trois chaînes ; une salissure hors des trois'
	echo '      répertoires déplacés ci-dessus n’est pas de ce script, et n’est pas jugée ici.'
fi

exit "$statut"
