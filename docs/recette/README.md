# `docs/recette/` — preuves, contenu éditorial et livrables documentaires

Produit par l'**issue #18** (Épic 6). Deux choses vivent ici, et il vaut mieux savoir laquelle on lit.

## 1. La copie éditoriale des trois pages publiques — `contenu/`

`la-demarche.html` · `accessibilite.html` · `mentions-legales.html`

C'est la **source versionnée** du texte des trois pages du §5.1 du brief. Elle est ici, et pas dans le
thème, pour une raison de fond : `design-system/MASTER.md` §11.3 est une **liste fermée** des phrases
qu'une page publique a le droit de rédiger, et son §16 tranche que ce genre de rédaction « vient du
**contenu**, jamais du **code** ». Les gabarits du thème portent donc la structure et les chaînes
servies par l'extension ; la prose est du contenu, saisi en base.

→ Détail et justification : [`docs/contracts/issue-18.md`](../contracts/issue-18.md) §4.1, arbitrage A-1.

**Ces fichiers ne s'affichent pas tout seuls.** Ils sont poussés en base par :

```sh
docker compose run --rm -v "$PWD/docs:/docs" wpcli sh /docs/recette/importer-pages.sh
```

`importer-pages.sh` est **idempotent** — rejouable sans dupliquer ni page ni entrée de menu — et
crée aussi le menu de pied. Sous Git Bash / MSYS2, préfixer par `MSYS_NO_PATHCONV=1`.

> **Si vous modifiez une page dans l'éditeur WordPress, reportez la modification dans le fichier
> source.** Sinon le prochain import l'écrase. C'est le prix de la source versionnée, et il est assumé.

[`contenu/README.md`](contenu/README.md) porte les titres, les slugs, les gabarits, les descriptions
servies, les règles de rédaction tenues, et la liste des endroits où il aurait été facile d'inventer un
fait — avec ce qui a été écrit à la place.

**L'idempotence de l'import est établie par l'exécution, pas par la lecture** : le script a été rejoué
**deux fois de suite** sur la stack déjà importée le 16 août 2026 à 21 h 05 UTC. Les trois pages gardent
les identifiants 4, 5 et 6 ; les trois entrées de menu gardent 7, 8 et 9 ; le menu compte toujours 3
entrées et reste affecté à l'emplacement `pied` ; les trois `_wp_page_template` pointent toujours sur
leur gabarit. Aucun doublon, aucune erreur.

## 2. Les preuves et les livrables

| Fichier | Ce qu'il contient |
|---|---|
| [`preuves-a11y-et-perf.md`](preuves-a11y-et-perf.md) | Relevés axe-core, origines réseau, budgets §10 — **avec leurs conditions**, et la liste de ce qui n'a **pas** été mesuré |
| [`controle-lecteur-ecran.md`](controle-lecteur-ecran.md) | Procédure et **gabarit de preuve vide** du contrôle humain. Ce n'est pas un résultat |
| [`checklist-12.md`](checklist-12.md) | La Definition of Done du §12, ligne à ligne, avec l'état réel de chacune |
| [`livrables-11.md`](livrables-11.md) | Où se trouve chaque livrable du §11 — **pointe, ne recopie pas** |
| [`administration.md`](administration.md) | Documentation d'administration courte du §11 |
| `captures/` | 10 captures — 5 pages × desktop 1440 et mobile 360 |
| `releves/` | Relevés bruts au format JSON |
| `outils/` | Les deux scripts qui produisent tout cela, pour rejouer les mesures |

---

## La règle qui gouverne ce répertoire

**Rien n'est écrit ici comme vérifié qui ne l'ait pas été.**

Ce qui n'a pas été mesuré est nommé, avec son motif — voir `preuves-a11y-et-perf.md` §5 et les lignes
`non vérifiée` de `checklist-12.md`. Un gabarit vide est préférable à un chiffre inventé, et une preuve
qui tait ses conditions vaut moins qu'aucune preuve : c'est pourquoi la dérogation `bypassCSP` du
pilote de test est écrite noir sur blanc plutôt que passée sous silence.

C'est la même exigence que le §4.2 du brief applique aux statuts — ne jamais présenter comme courant ce
qui ne l'est pas. Elle vaut aussi pour les preuves qu'on produit sur soi-même.
