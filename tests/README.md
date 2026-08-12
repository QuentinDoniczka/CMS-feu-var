# Recette d'intégration — MASSIFS

**Pas de tests unitaires dans ce dépôt.** Un scénario ne teste jamais une fonction isolée : il joue une
histoire complète dans un WordPress réellement amorcé, à l'intérieur de la stack Docker du dépôt, et
n'affirme que des faits **observables** — ce que la base contient, ce que le domaine rend au thème, ce
que le serveur répond en HTTP. Si un contrôle n'exerce pas le front et le back ensemble à travers une
frontière réelle, il n'a pas sa place ici.

**Aucune source externe n'est jamais appelée.** La préfecture, Météo-France et EFFIS sont bouchonnés à
la frontière d'ingestion (`pre_http_request`). Quand un scénario a besoin d'un vrai aller-retour HTTP,
il vise notre propre serveur (`http://wordpress/`), à l'intérieur de la stack.

---

## Lancer

```bash
bash docker/up.sh          # la stack doit tourner

bash tests/run.sh          # tous les scénarios
bash tests/run.sh 13       # un seul, par numéro
bash tests/run.sh jointure # ou par mot-clé

bash tests/verifier-http.sh   # origines tierces, gardes 403, budget de transfert
bash tests/module-absent.sh   # tolérance du chargeur à un module frère absent

node tests/rendu/recette-rendu.mjs            # recette de rendu, vrai navigateur
node tests/rendu/recette-rendu.mjs --filtre=tierce

docker compose down        # ne rien laisser tourner
```

`tests/run.sh` rend un code de sortie non nul dès qu'une assertion échoue, et affiche le total.
`tests/rendu/recette-rendu.mjs` fait de même.

## Comment c'est fait

| Chemin | Rôle |
|---|---|
| `tests/bootstrap.php` | assertions, purge d'état, fabriques de charges utiles, bouchons réseau |
| `tests/scenarios/*.php` | un scénario par fichier, exécuté par `wp eval-file` dans le conteneur d'outillage |
| `tests/outils/` | fichiers appelés par les scripts shell, hors de la boucle des scénarios |
| `tests/rendu/etats.php` | fabrique d'états observables : place la base dans un état connu, puis rend la main |
| `tests/rendu/recette-rendu.mjs` | recette de rendu — un vrai navigateur charge le site réel en HTTP |
| `tests/run.sh`, `tests/verifier-http.sh`, `tests/module-absent.sh` | orchestration depuis l'hôte |

### La recette de rendu

Ce que PHP ne peut pas prouver de l'intérieur : les requêtes que le navigateur émet
*réellement*, la page rendue sans JavaScript, la largeur à 360 px, l'arbre d'accessibilité,
les octets transférés. Chaque scénario pose son état par `wp eval-file` dans la stack, puis
observe le site en HTTP — jamais de source externe, jamais de fixture partagée entre
scénarios.

Dépendances hôte : `playwright-core` et `axe-core`, plus un Chromium. Si elles ne sont pas
installées dans le dépôt, deux variables d'environnement suffisent :

```bash
MASSIFS_NODE_MODULES=/chemin/vers/node_modules   # où trouver playwright-core et axe-core
MASSIFS_CHROME=/chemin/vers/chrome               # sinon, ~/AppData/Local/ms-playwright est fouillé
```

Deux scénarios (`ancre`, `extension`) provoquent volontairement une panne — renommage de
`templates/parts/liste-statuts.php`, désactivation de `massifs-core` — et remettent l'arbre
et la stack en état dans un `finally`, avec une assertion de remise en état. Lancés seuls,
ils laissent le dépôt comme ils l'ont trouvé.

**Chaque scénario est autonome** : il commence et finit par `t_reset()`, et doit passer lancé seul.
Aucun ne dépend de l'ordre.

**Le connecteur est désarmé sur la stack de développement** (`WP_ENVIRONMENT_TYPE=local`), pour qu'une
machine de développement ne puisse pas bombarder le serveur de la préfecture. Un scénario qui a besoin
du chemin d'ingestion appelle `t_armer_connecteur()`, qui redéfinit le modèle d'URL vers notre propre
serveur : le connecteur se réarme et la source réelle devient inatteignable par construction. Un
scénario qui doit être armé **dès l'amorçage** (planification du cron sur `init`) porte le suffixe
`.arme.php` ; `run.sh` lui pose la constante avant le chargement de WordPress.

## Les scénarios

| Fichier | Ce qu'il éprouve | Ligne du §12 |
|---|---|---|
| `01-amorcage` | surface contractuelle des trois chaînes, table, légende officielle, attributions | chaîne des données |
| `02-jointure-statut-massif` | une publication préfectorale traverse tout et devient un statut lisible par le thème | chaîne des données |
| `03-garde-referentiel-ingestion` | le garde-fou référentiel rejette un lot amputé, inconnu ou de cardinal différent | données aberrantes |
| `04-statut-jamais-perime` | **règle absolue §4.2** : aucune donnée, donnée de la veille, `level` 0, hors saison, jour futur, péremption | statut périmé |
| `05-non-publie-404` | un 404 est « pas encore publié » : aucun échec compté, aucune alerte | chaîne des données |
| `06-panne-reseau` | source injoignable : dernière valeur conservée, échec compté, fraîcheur honnête | échec réseau |
| `07-charge-aberrante` | 11 charges rejetées, valeur précédente intacte, alerte émise ; et ce qui n'est PAS une aberration | données aberrantes |
| `08-connecteur-desarme` | le coupe-circuit de la stack de développement est réel, pas décoratif | — |
| `09-migration-nullabilite` | base déjà installée : `niveau_cle` devient nullable, la ligne héritée survit | chaîne des données |
| `10-alter-idempotent` | l'`ALTER` de nullabilité est émis une fois, jamais deux | chaîne des données |
| `11-contrat-ecriture-projection` | une ligne irrécupérable n'écrit rien ; `1326`/`1327` écartés ; relevé réussi seulement si tout est écrit | chaîne des données |
| `12-geometrie-et-rest` | géométrie servie depuis notre origine, intègre, sous budget ; aucune route REST ; aucun asset enfilé | zéro requête tierce, budgets |
| **`13-jours-consecutifs-identiques`** | **régression permanente — voir ci-dessous** | statut périmé |
| `14-install-fraiche` | installation vierge : table créée, aucune erreur PHP, tout « indisponible » honnêtement | chaîne des données |
| `20-cron-complet.arme` | enregistrement, planification horaire, filtre d'URL de bout en bout, hors-saison sans octet réseau, retrait à la désactivation | chaîne des données |
| `21-rendu-etats-hors-saison` | les gabarits réels rendus hors saison, sur un jour futur, et avec une donnée de la veille en base | statut périmé, hors-saison |

### Les scénarios de rendu (`tests/rendu/recette-rendu.mjs`)

| Clé | Ce qu'il éprouve | Ligne du §12 |
|---|---|---|
| `tierce` | toute requête réellement émise par le navigateur, sur cinq pages, plus les `url()`/`@import` de chaque feuille servie | zéro requête tierce |
| `sans-js` | JavaScript coupé : synthèse, fraîcheur, légende, 25 lignes de statut, bandeau | utilisable sans JS |
| `structure` | un `h1` exposé, aucun `id` en double, aucune ancre d'évitement morte, focus visible | accessibilité |
| `perime` | donnée de la veille en base : « information non disponible » sur la carte ET dans la liste | statut périmé |
| `non-officialite` | bandeau présent dans les trois états de données | bandeau de non-officialité |
| `couleur` | chaque marque colorée est suivie de son libellé en toutes lettres | jamais la couleur seule |
| `mobile` | 360 px, 320 px, zoom texte 200 % | mobile réel |
| `a11y` | axe-core sur les pages servies, zéro violation bloquante | vérifications automatisées |
| `budgets` | octets réellement transférés, nombre de polices, double téléchargement, géométrie | budgets de perf |
| `api` | racine REST publique, écriture anonyme refusée | API publique |
| `ancre` | panne provoquée : la partie « liste » manque | — |
| `extension` | panne provoquée : `massifs-core` est désactivée | — |
| `artefacts` | sha256 de `tokens.css`, 111 jetons, identité au bloc normatif de MASTER §12, 2 polices, jetons déclarés-non-consommés, `print.css` intégralement sous `@media print` | design system |
| `couche-statut` | les 44 marques réellement peintes : aplat, liseré 2 px sur quatre côtés, motif présent où il doit l'être **et absent où il ne doit pas l'être**, boîtes du §8.1, hampe du jalon | jamais la couleur seule |
| `feuilles` | les cinq `<link>` du `<head>` : ordre, `media`, `print` après `composants`, aucune fuite de la feuille d'impression vers l'écran | design system |
| `casse` | plus aucune capitale forcée sur les titres ; les capitales ne survivent que sur les étiquettes `--fs-250` | design system |
| `couleurs-forcees` | `forced-colors: active` émulé : chaque motif reconstruit en `CanvasText`, et les états nus le restent | jamais la couleur seule |
| `impression` | aperçu d'impression à **A4 (703 px) ET A5 (469 px)** : colonnes restaurées sans requête de largeur, bandeau de non-officialité imprimé, liseré et hachure en charbon y compris sous `.sur-sombre` | équivalent textuel imprimable |
| `cartes` | mode cartes à 320 px, étiquettes reprises de `data-etiquette`, et **le piège des cellules vides** : aucun champ étiqueté vide, aucun octet d'espace entre les balises | mobile réel |
| `arbre` | l'arbre d'accessibilité réellement construit par le moteur (CDP) en mode cartes : ce qui survit au `display: block`, et ce que le `thead` masqué fait perdre | accessibilité |

### `13-jours-consecutifs-identiques` — à ne jamais supprimer

Le corps servi par la préfecture **ne contient aucune date**. Deux journées où les 27 massifs portent les
mêmes valeurs produisent donc un corps octet pour octet identique — c'est le cas **nominal** en juin et
pendant tout épisode stable. Un garde-fou fondé sur le hachage a classé le second jour « doublon »,
n'a rien enregistré, et aurait affiché « information non disponible » pendant toute la durée d'un
épisode stable, c'est-à-dire exactement quand la donnée est bonne.

Ce défaut a échappé à la recette **parce que cette suite affirmait le mauvais comportement**. La règle
en vigueur est sans exception :

> Le 404 est le seul signal de non-publication. Un 200 sur `{date}.json` **est** la publication de cette
> date. Le hachage ne peut que journaliser, ou éviter une réécriture pour la **même** date.

## Écrire un scénario

1. Une histoire, pas une micro-assertion : « la préfecture publie, le visiteur voit » est un scénario.
2. On n'affirme que de l'observable. Jamais « telle méthode privée a été appelée ».
3. On gèle ce qui doit l'être — saison, fraîcheur — par les filtres publics prévus à cet effet, jamais
   en attendant que l'horloge coopère.
4. **On n'affaiblit jamais une assertion pour faire passer un scénario**, et on ne supprime jamais un
   scénario rouge. Un rouge est soit un défaut du code — qui se rapporte —, soit une attente fausse —
   qui se corrige en disant pourquoi elle était fausse.

## Ce que cette suite ne couvre pas

Elle ne remplace ni un contrôle humain au lecteur d'écran, ni un vrai téléphone à 360 px, ni une
restauration de sauvegarde, ni HTTPS en production.

Le scénario `impression` éprouve `print.css` **en média émulé**, à deux largeurs de contenu (A4 et A5).
Ce n'est pas une sortie papier : la pagination réelle, les sauts de page, le rendu du moteur
d'impression du système et le comportement des pilotes ne sont pas observés. `@page { margin }` n'est
pas mesurable dans un viewport émulé — seule la largeur du viewport l'est, et elle est calculée à la
main depuis le format moins les marges.

Le scénario `arbre` relève l'arbre d'accessibilité tel que Chromium le construit. Il dit ce que
l'arbre contient ; il ne dit pas ce qu'un lecteur d'écran en fait. La perte des `columnheader` en mode
cartes est un **constat mesuré**, jamais une validation d'utilisabilité.

Enfin `couleurs-forcees` est une **émulation** du média `forced-colors` par Chromium, pas un vrai
contraste élevé Windows : les couleurs système réelles, et les thèmes personnalisés, ne sont pas
éprouvés.

Ne sont pas couverts non plus, faute d'exister : la carte et son repli statique sans JavaScript,
la couche EFFIS, l'indicateur Météo-France, le point d'accès JSON public, les pages « La démarche »,
« Accessibilité » et « Mentions légales », et **tout le portail** — écran de mise à jour, journal
d'audit, limitation des tentatives de connexion, double authentification. Ces lignes du §12 ne sont
déclarées couvertes nulle part.

Enfin, l'horloge du domaine n'est pilotée par aucun filtre : « hors saison » et « demain non publié »
sont donc éprouvés en demandant explicitement un jour aux gabarits (`21-rendu-etats-hors-saison`),
jamais sur la page d'accueil servie, qui suit l'horloge réelle du conteneur.
