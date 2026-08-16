# Livrables du §11 — où ils sont

**Issue #18** · brief §11 · Épic 6

> **Ce document pointe, il ne recopie pas.** Une bonne partie des livrables du §11 existe déjà,
> ailleurs, écrite par les chaînes qui possédaient le sujet. En recopier le contenu ici créerait une
> **seconde vérité** qui divergerait de la première au premier changement — et personne ne saurait
> laquelle fait foi. Ce fichier dit donc où chaque livrable se trouve, et n'écrit que ce qui manquait
> réellement.

---

## 1. Le site en production et l'environnement de démonstration

**En sommeil.** Le propriétaire du projet a arrêté que le site ne serait pas publié — voir
[`docs/decisions/portee-non-publiee.md`](../decisions/portee-non-publiee.md). Nom de domaine,
hébergement et instance de démonstration sont consignés à l'issue **#40** et se rallument tels quels si
la décision change.

Ce qui n'est **pas** en sommeil : tout le reste de ce tableau.

---

## 2. Code source et instructions d'installation

| Livrable | Où | État |
|---|---|---|
| Code source complet | `wp-content/themes/massifs/` et `wp-content/plugins/massifs-core/` | livré |
| **Installation locale** | [`docker/README.md`](../../docker/README.md) | **déjà écrit — ne pas dupliquer** |
| Import des trois pages éditoriales | [`importer-pages.sh`](importer-pages.sh) | livré par cette issue |

`docker/README.md` couvre le démarrage (`bash docker/up.sh`), l'arrêt, la réinitialisation complète, le
changement de port et l'idempotence du provisionnement. Un développeur qui découvre le projet monte la
stack avec cette seule page.

**Ce qui manquait, et que cette issue ajoute** : les trois pages éditoriales ne sont créées par aucun
provisionnement. Après `docker/up.sh`, il faut donc, une fois :

```sh
docker compose run --rm -v "$PWD/docs:/docs" wpcli sh /docs/recette/importer-pages.sh
```

> **Sous Git Bash / MSYS2 (Windows)**, préfixer par `MSYS_NO_PATHCONV=1` : sans cela, le shell
> réécrit `/docs/...` en chemin Windows et le conteneur ne trouve pas le script. Constaté à l'exécution.

**Dette connue** : ce script est un outil de recette, pas un provisionnement durable. Tant que
`docker/provision/` ne crée pas ces pages, une réinitialisation (`docker/reset.sh`) les perd et il faut
rejouer l'import. Une issue de suivi est ouverte par l'orchestrateur.

---

## 3. Plan de design

| Livrable | Où |
|---|---|
| Plan de design — jetons, typographies, élément signature | [`design-system/MASTER.md`](../../design-system/MASTER.md) |

Palette nommée et ancrée (§2, §4), échelle typographique et les deux familles auto-hébergées (§5),
élément signature — le repère — et sa liste fermée d'emplacements (§3), preuve d'accessibilité mesurée
(§10), jetons CSS exacts (§12), impression (§13), et les passes d'autocritique (§14).

---

## 4. Journal des décisions

| Livrable | Où | Contenu |
|---|---|---|
| Décisions de **design** | [`MASTER.md` §15](../../design-system/MASTER.md) | D-01 à D-31, chacune avec sa raison **et l'alternative écartée** |
| Décisions de **portée et de sources** | [`docs/decisions/`](../decisions/) | `portee-non-publiee.md`, `source-prefecture.md` |
| Décisions **d'interface, par issue** | [`docs/contracts/`](../contracts/) | un contrat gelé par issue, chacun avec sa table d'arbitrages et ses questions ouvertes |

C'est le corpus réutilisable dans un mémoire technique : chaque décision y porte sa raison et ce qu'elle
a coûté. Les contrats d'issue sont la partie la moins évidente à trouver et la plus utile — ils
enregistrent les arbitrages **au moment où ils ont été rendus**, avec ce qui était connu alors.

Les décisions propres à cette issue sont dans [`docs/contracts/issue-18.md`](../contracts/issue-18.md),
§7 « Arbitrages ».

---

## 5. Documentation d'administration

→ [`administration.md`](administration.md) — écrite par cette issue, c'est ce qui manquait vraiment.

Mettre à jour les statuts, gérer un compte gestionnaire, comprendre les alertes.

---

## 6. Preuves de recette

| Preuve exigée par le §11 | Où |
|---|---|
| Export réseau démontrant l'absence de requêtes tierces | [`preuves-a11y-et-perf.md`](preuves-a11y-et-perf.md) §3, relevés bruts dans [`releves/`](releves/) |
| Captures desktop **et** mobile | [`captures/`](captures/) — 10 captures, 5 pages × 2 formats |
| Résultat des vérifications d'accessibilité | [`preuves-a11y-et-perf.md`](preuves-a11y-et-perf.md) §2 |
| Checklist §12 remplie | [`checklist-12.md`](checklist-12.md) |

Les **conditions** de chaque relevé — outillage, dérogation `bypassCSP` du pilote, état de l'arbre,
fenêtre d'indisponibilité — sont au §1 de `preuves-a11y-et-perf.md`. Ce qui **n'a pas** été mesuré est
au §5 du même fichier, avec son motif.
