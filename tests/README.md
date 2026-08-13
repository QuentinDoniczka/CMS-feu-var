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
| `tests/rendu/etats.php` | fabrique d'états observables : place la base dans un état connu, puis rend la main. Modes : `absente`, `jour-nominal`, `veille-seule`, `jour-complet <autorises>`, `jour-partiel <renseignes> <autorises>`. Les deux derniers rapportent l'état **relu dans le domaine**, jamais celui que la fabrique a cru écrire |
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
| `structure` | code HTTP attendu, un `h1` exposé, aucun `id` en double, aucune ancre d'évitement morte, focus visible, **un `<title>` non vide et distinct par page** | accessibilité |
| `perime` | donnée de la veille en base : « information non disponible » sur la carte ET dans la liste | statut périmé |
| `non-officialite` | bandeau présent dans les trois états de données | bandeau de non-officialité |
| `couleur` | chaque marque colorée est suivie de son libellé en toutes lettres | jamais la couleur seule |
| `mobile` | 360 px, 320 px, zoom texte 200 %, et le `h1` déjà rendu par PHP sur les cinq pages, JavaScript coupé | mobile réel, sans JS |
| `a11y` | axe-core sur les pages servies, zéro violation bloquante, plus `page-has-heading-one` affirmée **hors** du filtre d'impact (elle est `moderate`) | vérifications automatisées |
| `budgets` | octets réellement transférés, nombre de polices, double téléchargement, géométrie | budgets de perf |
| `api` | racine REST publique, écriture anonyme refusée | API publique |
| `ancre` | panne provoquée : la partie « liste » manque | — |
| `extension` | panne provoquée : `massifs-core` est désactivée | — |
| `artefacts` | sha256 de `tokens.css`, 111 jetons, identité au bloc normatif de MASTER §12, 2 polices, jetons déclarés-non-consommés, `print.css` intégralement sous `@media print` | design system |
| `couche-statut` | les 44 marques réellement peintes : aplat, liseré 2 px sur quatre côtés, motif présent où il doit l'être **et absent où il ne doit pas l'être**, boîtes du §8.1, hampe du jalon | jamais la couleur seule |
| `feuilles` | les cinq `<link>` du `<head>` : ordre, `media`, `print` après `composants`, aucune fuite de la feuille d'impression vers l'écran | design system |
| `casse` | plus aucune capitale forcée sur les titres ; les capitales ne survivent que sur les étiquettes `--fs-250` | design system |
| `couleurs-forcees` | `forced-colors: active` émulé : chaque motif reconstruit en `CanvasText`, et les états nus le restent | jamais la couleur seule |
| `impression` | aperçu d'impression à **A4 (703 px) ET A5 (469 px)** : colonnes restaurées sans requête de largeur, bandeau de non-officialité imprimé, liseré et hachure en charbon y compris sous `.sur-sombre`, et le `thead` en `display: table-header-group` / `position: static` / `clip-path: none` — **seule sonde capable de détecter le retrait de la garde `@media screen`** du déport (invariant I-5 rév. #28) | équivalent textuel imprimable |
| `cartes` | mode cartes à 320 px, en-tête **déporté hors cadre et non retiré** (boîte de 2 px, aucun pixel de défilement, jamais focusable), étiquettes reprises de `data-etiquette`, et **le piège des cellules vides** : aucun champ étiqueté vide, aucun octet d'espace entre les balises | mobile réel |
| `arbre` | l'arbre d'accessibilité réellement construit par le moteur (CDP), **aux deux largeurs** : comptes stricts par rôle, noms accessibles des quatre en-têtes, et le sous-ensemble tabulaire identique à 320 px et à 900 px — `cell` excepté (63 / 75) | accessibilité |
| `partielle` | **journée de publication partielle (issue #26)** : huit journées posées par la fabrique — trois complètes (X = 0, 1, 20) et cinq partielles (X/Y/Z couvrant 0, 1 et pluriel sur les **trois** axes d'accord) — dont le dénominateur affiché, la phrase de synthèse mot pour mot, la présence *et l'absence* du `<p class="ardoise__publication-partielle">`, sa position entre le `h1` et la ligne de fraîcheur, la concordance de la liste textuelle avec les chiffres du domaine, axe-core et 360 px sur une journée partielle | statut périmé, utilisable sans JS, accessibilité, mobile |
| `gravatar` | aucune empreinte d'e-mail composée ni servie — anonymement, sous `admin` et sous `gestionnaire-demo`, sur `/`, `/wp-admin/`, `profile.php`, `users.php` et les deux routes REST du cœur — où `avatar_urls` / `author_avatar_urls` ont disparu de la charge utile **et du schéma** (`OPTIONS`) ; la coupe tient **même sous `force_display`**, donc imprenable par une valeur en base | zéro requête tierce, donnée personnelle |
| `etat-inconnu` | **recette R-27 (issue #27)** : un `etat_global` hors des quatre bras du `match()` de l'ardoise, par ses **deux** déclencheurs — cinquième état, et clé retirée du tableau de synthèse. Page servie en 200, `h1` unique portant la phrase §11.3 mot pour mot avec son lien officiel **du même hôte que le bandeau**, aucun chiffre présenté, ancre `#liste` résolue, document fermé, aucune trace PHP dans le corps, et l'`Undefined array key` **au journal seulement** | statut périmé, utilisable sans JS, accessibilité |

### `etat-inconnu` — la seule sonde de la garde du `match()`

Aucun chemin de **donnée** ne peut produire un cinquième `etat_global` : il naît d'une chaîne `if/elseif`
locale et fermée de l'extension, qu'aucun `apply_filters` ne traverse. Ce scénario est donc le seul qui
exerce la garde, et il le fait par une **injection locale et temporaire** dans `front-page.php`, retirée
dans un `finally` avec assertion de remise en état à l'octet — même protocole que `ancre` et `extension`.

Mesuré des deux côtés le 13 août 2026 : sans la garde, la même injection rend **HTTP 500 et la page
« Il y a eu une erreur critique sur ce site. » du cœur de WordPress** — zéro statut, zéro lien officiel,
aucun `h1`, 2 697 octets. C'est ce que le scénario empêche de revenir : retirer le `try/catch` le rend
rouge immédiatement.

L'attente `attendreRechargement()` n'est pas du confort : `opcache.revalidate_freq` vaut **2** sur cette
pile. Sans elle, le scénario mesure la page d'**avant** son injection et se croit vert sans rien avoir
exercé — c'est le défaut qu'il a réellement eu à sa première exécution.

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
l'arbre contient ; il ne dit pas ce qu'un lecteur d'écran en fait. Depuis l'issue #28, les quatre
`columnheader` sont de retour à 320 px — le `thead` est **déporté** hors cadre au lieu d'être retiré.
`Accessibility.getFullAXTree` prouve que le **nœud** existe et n'est pas ignoré ; il ne prouve **rien**
de l'**association** en-tête ↔ cellule, calculée par le moteur et exposée par les API plateforme,
absente de tout champ de l'instantané CDP. Énoncé exact : « le nœud `columnheader` est rétabli et
l'association est rendue possible » — jamais « l'association est rétablie », jamais « conforme AA ».

Enfin `couleurs-forcees` est une **émulation** du média `forced-colors` par Chromium, pas un vrai
contraste élevé Windows : les couleurs système réelles, et les thèmes personnalisés, ne sont pas
éprouvés.

Le scénario `gravatar` ouvre **deux vraies sessions** (`admin`, `gestionnaire-demo`) et pose donc
délibérément des cookies `wordpress_logged_in_*`, détruits avec les contextes de navigation qui les
portent — l'interdiction de cookie du §2 vise le visiteur anonyme, et le scénario l'asserte
explicitement dans sa première jambe. Il **ne couvre pas** l'énumération d'utilisateurs par
`GET /wp-json/wp/v2/users`, qui reste ouverte : il asserte même que la route continue de lister les
mêmes comptes, pour prouver qu'elle n'a pas été touchée. C'est une exigence distincte, du §9 du brief.

Ne sont pas couverts non plus, faute d'exister : la carte et son repli statique sans JavaScript,
la couche EFFIS, l'indicateur Météo-France, le point d'accès JSON public, les pages « La démarche »,
« Accessibilité » et « Mentions légales », et **tout le portail** — écran de mise à jour, journal
d'audit, limitation des tentatives de connexion, double authentification. Ces lignes du §12 ne sont
déclarées couvertes nulle part.

Enfin, l'horloge du domaine n'est pilotée par aucun filtre : « hors saison » et « demain non publié »
sont donc éprouvés en demandant explicitement un jour aux gabarits (`21-rendu-etats-hors-saison`),
jamais sur la page d'accueil servie, qui suit l'horloge réelle du conteneur.

Conséquence directe et **non couverte** : `front-page.php` appelle le domaine avec `null` (« aujourd'hui »)
et n'accepte aucun jour. Les bras `hors_saison` et `non_encore_publie` de son `match()` sont donc
**inatteignables en HTTP** tant que l'horloge du conteneur est en saison — aucun scénario n'observe
l'ardoise dans ces deux états. Les deux autres états sans chiffre le sont, eux : `indisponible` par les
modes `absente` et `veille-seule`, la branche « API absente » par le scénario `extension`.
