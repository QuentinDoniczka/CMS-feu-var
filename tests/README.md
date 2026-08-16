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

# Les trois pages éditoriales de l'issue #18 ne sont PAS provisionnées : leur prose
# est du contenu versionné. Sans cet import elles répondent 404 et les scénarios
# `pages`, `tierce`, `structure`, `mobile` et `a11y` rougissent. `docker/reset.sh`
# les perd — rejouer l'import après toute remise à zéro.
MSYS_NO_PATHCONV=1 docker compose run --rm -v "$PWD/docs:/docs" wpcli \
	sh /docs/recette/importer-pages.sh

bash tests/run.sh          # tous les scénarios
bash tests/run.sh 13       # un seul, par numéro
bash tests/run.sh jointure # ou par mot-clé

bash tests/verifier-http.sh   # origines tierces, gardes 403, budget de transfert
                              # + « tout fichier enfilé pour un navigateur est SERVI »
bash tests/module-absent.sh   # tolérance du chargeur à un module frère absent

node tests/rendu/recette-rendu.mjs            # recette de rendu, vrai navigateur
node tests/rendu/recette-rendu.mjs --filtre=tierce

# EN DERNIER, et jamais dans une suite : la commande ÉCRASE la base cible
# (DROP/CREATE de toutes les tables) puis la restaure. C'est la seule preuve que
# les archives ne mentent pas.
docker compose run --rm wpcli wp massifs sauvegarde verifier

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
| `tests/rendu/etats.php` | fabrique d'états observables : place la base dans un état connu, puis rend la main. Modes : `absente`, `jour-nominal`, `veille-seule`, `jour-complet <autorises>`, `jour-partiel <renseignes> <autorises>`, `deux-jours`. Tous rapportent l'état **relu dans le domaine**, jamais celui que la fabrique a cru écrire. `deux-jours` publie aujourd'hui ET demain, avec des ensembles de massifs interdits **différents** : c'est le seul état où le sélecteur de date de la carte est réellement exerçable |
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

**Autonome dans le TEMPS aussi, et `t_reset()` n'y suffit pas.** L'écluse anti-force-brute persiste
par nature : registre de verrous en **option**, compteurs en **transients à fenêtre fixe de 900 s**.
`t_reset()` ne les touche pas — et ne le doit pas, ce n'est pas de l'état de statut. Un scénario qui
sature l'écluse laisse donc, pour un quart d'heure, de quoi faire échouer sa propre relance. Défaut
réellement rencontré le 16 août 2026 : `60-portail-journal-exact` passait lancé seul et rougissait
sur trois assertions quand la suite complète repassait derrière lui dans la fenêtre. Tout scénario
qui touche l'écluse **purge avant ET après**, localement, et l'asserte (`MÉNAGE : l'écluse est rendue
au repos`). Côté navigateur, `purgerEcluse()` joue le même rôle, et pour la même raison :
sans elle, l'origine de la recette reste verrouillée et **tous** les scénarios suivants échouent à la
connexion pour une raison qui n'est pas la leur.

**La recette de rendu lit ses identifiants dans `.env`, jamais ailleurs** (`WP_ADMIN_*`,
`WP_MANAGER_*`). Si la base et `.env` divergent — un développeur qui « remet en état » un mot de
passe à la mauvaise valeur —, la recette rougit en bloc sur des assertions qui n'ont rien à voir :
15 rouges observés le 16 août 2026, tous réductibles à `WP_MANAGER_PASSWORD`. Avant de suspecter le
code, **comparer `.env` à la base**. `.env` fait foi, c'est ce que `provision.sh` consomme.

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
| **`22-api-publique-statuts`** | **issue #8** — le point d'accès `/wp-json/massifs/v1/statuts` interrogé **en HTTP réel et sans cookie** depuis le réseau de la stack : les douze clés de l'enveloppe présentes dans l'état le plus pauvre, les 25 massifs dans tous les états, `niveau`/`zapef` en `null` littéral, `par_niveau` encodé en **objet** et non en tableau, le bornage du paramètre `jour` (absent, vide, passé, au-delà de demain, malformé), `POST`/`PUT`/`PATCH`/`DELETE` et les deux contournements de méthode, l'ETag et son `304`, l'absence d'ETag sous `_fields`, l'invariance par cookie, et la veille en base qui ne remplit jamais la journée courante | API publique, statut périmé, chaîne des données |
| `30` à `39` — **météo** | **issue #10** — la garde de vocabulaire qui tient « indisponible » tant que les libellés officiels des crans ne sont pas sourcés, un aller-retour HTTP **réel** intra-stack sans `pre_http_request`, les cinq couches de validation et ce qui n'est *pas* une aberration, la panne et son alerte unique sans chiffre dans le courriel, le coupe-circuit et la porte saisonnière (zéro octet sortant), le §4.2 appliqué au danger météo, l'état peuplé par la clé d'injection du gabarit, et l'indicateur **jamais fusionné** avec le statut réglementaire | chaîne des données, zéro requête tierce |
| `40` à `47` — **zones parcourues** | **issue #11** — l'ingestion nominale où la charge simulée est une **origine** et non une branche, le lot vide qui est le cas nominal, **`42` et `47` C : `aucune_zone` contre `couche_effis_indisponible`**, tous deux `nombre === 0` et jamais le même rendu, la péremption dure appliquée à la **lecture** (les polygones restent en base), les charges aberrantes qui n'écrasent rien, les gardes de cadence, la route publique `/massifs/v1/zones-parcourues-par-le-feu`, et **`47` la jonction extension ↔ `panneau-feu.php`** | chaîne des données, couche EFFIS |
| **`60-portail-journal-exact`** | **Épic 5 (#13, #14, #15)** — trois gestionnaires corrigent tour à tour le **même** couple (massif, jour), et le journal reste exact : ordre décroissant, **filtre par auteur**, **filtre par source**, **pagination à cheval sur une frontière** — les quatre façons dont la dérivation par parcours du lot 1 mentait (contrat #15 §0.2). Plus la matrice de capacités **à la négative** (23 capacités du cœur refusées au gestionnaire), la création de compte **refusée sans acteur**, la suspension qui retire les capacités **à chaud** et **détruit la session**, la suppression bloquée par `map_meta_cap`, le registre d'évènements de compte et son interdit 8 (ni secret, ni code, ni IP), l'écluse et son **arbitrage A-13** (verrou par ORIGINE, jamais par identifiant — la démonstration publique n'est pas éteignable), et la rampe 2FA qui **n'exige jamais un code qu'on ne peut pas produire** | portail, journal exact, force brute |
| **`70-durcissement-et-sauvegardes`** | **issue #16** — le durcissement du §9 et le moteur de sauvegarde, en **HTTP réel** depuis le réseau de la stack. En tête, la **non-régression la plus importante du lot** : `GET /wp-json/massifs/v1/statuts` **200 en anonyme** — le réflexe naturel (`rest_authentication_errors`) aurait fermé toute l'API, donc l'open data du §5.4 et la carte publique. Puis les **quatre surfaces d'énumération**, les en-têtes relevés sur la réponse réelle (HSTS **absent** en HTTP, et c'est correct), la politique de mise à jour, l'**édition de code mesurée sur la CAPACITÉ** et non sur la constante, une **archive réellement créée puis tentée en téléchargement anonyme** (arbitrage A-5 : elle contient des hachages de mots de passe et des secrets TOTP), et la **rotation dont le seuil est franchi pour de bon** — un mécanisme de suppression jamais déclenché n'est pas un mécanisme vérifié | sauvegardes, énumération, zéro requête tierce |
| `50` à `54` — **veille de fraîcheur** | **issue #12** — la veille planifiée **même quand le connecteur d'ingestion est désarmé** (le trou de la stack de développement), l'alerte de péremption émise **une fois par source et par jour de validité**, le silence total hors saison, la veille désarmée par constante puis par filtre, et le retrait du crochet `massifs_veille_fraicheur` à la désactivation de l'extension | fraîcheur, chaîne des données |

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
| `artefacts` | sha256 de `tokens.css`, **116 jetons sur `:root` / 133 dans le fichier** (amendement v2.4 de MASTER, chaîne #50), identité au bloc normatif de MASTER §12, 2 polices, jetons déclarés-non-consommés, `print.css` intégralement sous `@media print` | design system |
| `couche-statut` | les 44 marques réellement peintes : aplat, liseré 2 px sur quatre côtés, motif présent où il doit l'être **et absent où il ne doit pas l'être**, boîtes du §8.1, hampe du jalon | jamais la couleur seule |
| `feuilles` | les cinq `<link>` du `<head>` : ordre, `media`, `print` après `composants`, aucune fuite de la feuille d'impression vers l'écran | design system |
| `casse` | plus aucune capitale forcée sur les titres ; les capitales ne survivent que sur les étiquettes `--fs-250` | design system |
| `couleurs-forcees` | `forced-colors: active` émulé : chaque motif reconstruit en `CanvasText`, et les états nus le restent | jamais la couleur seule |
| `impression` | aperçu d'impression à **A4 (703 px) ET A5 (469 px)** : colonnes restaurées sans requête de largeur, bandeau de non-officialité imprimé, liseré et hachure en charbon y compris sous `.sur-sombre`, et le `thead` en `display: table-header-group` / `position: static` / `clip-path: none` — **seule sonde capable de détecter le retrait de la garde `@media screen`** du déport (invariant I-5 rév. #28) | équivalent textuel imprimable |
| `cartes` | mode cartes à 320 px, en-tête **déporté hors cadre et non retiré** (boîte de 2 px, aucun pixel de défilement, jamais focusable), étiquettes reprises de `data-etiquette`, et **le piège des cellules vides** : aucun champ étiqueté vide, aucun octet d'espace entre les balises | mobile réel |
| `arbre` | l'arbre d'accessibilité réellement construit par le moteur (CDP), **aux deux largeurs** : comptes stricts par rôle, noms accessibles des quatre en-têtes, et le sous-ensemble tabulaire identique à 320 px et à 900 px — `cell` excepté (63 / 75) | accessibilité |
| `partielle` | **journée de publication partielle (issue #26)** : huit journées posées par la fabrique — trois complètes (X = 0, 1, 20) et cinq partielles (X/Y/Z couvrant 0, 1 et pluriel sur les **trois** axes d'accord) — dont le dénominateur affiché, la phrase de synthèse mot pour mot, la présence *et l'absence* du `<p class="ardoise__publication-partielle">`, sa position entre le `h1` et la ligne de fraîcheur, la concordance de la liste textuelle avec les chiffres du domaine, axe-core et 360 px sur une journée partielle | statut périmé, utilisable sans JS, accessibilité, mobile |
| `gravatar` | aucune empreinte d'e-mail composée ni servie — anonymement, sous `admin` et sous `gestionnaire-demo`, sur `/`, `/wp-admin/`, `profile.php`, `users.php` et les routes REST du cœur — où `avatar_urls` / `author_avatar_urls` ont disparu de la charge utile **et du schéma** (`OPTIONS`) ; la coupe tient **même sous `force_display`**, donc imprenable par une valeur en base. **Depuis l'issue #16, ce scénario porte aussi la FERMETURE de l'énumération de comptes** : `wp/v2/users` et `wp/v2/users/<id>` en **404 `rest_no_route`** pour l'anonyme, `users/me` en **401 et jamais 404** (retrait littéral, jamais par préfixe), `?author=N` et `/author/<identifiant>/` en **404 sans en-tête `Location`** — la fuite réelle étant le 301 de `redirect_canonical`, pas le corps —, et l'index du plan de site sans fournisseur `users` | zéro requête tierce, donnée personnelle, énumération |
| **`csp`** | **issue #16** — la CSP mesurée **dans** le navigateur, sans aucune dérogation : en-tête servi mot pour mot, `<script>` en ligne réellement refusé, et l'audit feuille par feuille de `document.styleSheets` — **seul juge**, voir A-13. Le **seul** style bloqué est le `<style id="wp-img-auto-sizes-contain-inline-css">` du cœur (A-12, couture S-11) ; aucune feuille du thème n'est emportée. **A-3 confirmé dans Chrome** : l'îlot `<script type="application/json">` survit à `script-src 'self'` et la carte se monte. Enfin A-2 : **aucune CSP sur le front en session**, affaiblissement borné et assumé | zéro requête tierce, accessibilité |
| **`pages`** | **issue #18** — les trois pages du §5.1 servies **JavaScript coupé** : gabarit éditorial (et non le repli `page.php`), un `h1`, zéro `<script src>`, `<meta name="description">`, **aucun statut rendu** (donc aucun statut à périmer), les **cinq attributions du §9 verbatim** en mentions légales, le « zéro cookie » expliqué sur la démarche, le **point d'accès JSON documenté puis RÉELLEMENT SUIVI** (une page qui documente une route morte est pire qu'une page muette), et le moyen de signalement de la page Accessibilité | pages rédigées, attributions, sans JS, API publique |
| **`carte`** | **issues #7 et #9** — la carte réellement montée dans un navigateur : `.carte--prete`, 25 tracés, repli statique retiré **après** montage et attribution OSM conservée, `attributionControl: false`, tuiles servies depuis notre origine et vérifiées **sur leur signature de fichier**, plafond de zoom 11 contre pyramide 12, bascule de jour (phrase de jour affichée, ensemble des polygones repeint, panneau daté du jour affiché, aucune persistance au rechargement), roving tabindex à **un seul** arrêt, `aria-label` composé de deux chaînes serveur, Entrée / Échap / retour du focus, et **le pas de hachure à l'écran mesuré à trois niveaux de zoom** (A-13) | zéro requête tierce, statut périmé, accessibilité |
| **`carte-degradee`** | **issues #7 et #9, chemins d'échec** — Leaflet empêché de se charger : le repli statique **reste**, avec son image, son lien vers la liste et son attribution, la liste garde ses 25 statuts, et **aucune origine tierce n'est contactée sur le chemin dégradé** (le vrai piège de I-9.2). Puis tuiles renvoyées en 404 : la carte se monte quand même, les 25 massifs restent tracés, aucune erreur JavaScript | utilisable sans JS, zéro requête tierce |
| **`bandes`** | **lot de l'Épic 4** — les trois bandes neuves mesurées **dans la page d'accueil servie**, JavaScript coupé : les huit `.bande--*` présentes une fois chacune et dans l'ordre du §7.1, la bande de péremption **au-dessus** de la carte et sa mention rendue **une seule fois** (`1 × .bandeau-alerte--peremption`, `0 × .ardoise__peremption`), la même bande à **hauteur zéro** le jour nominal, les deux `<section>` nommées `#meteo` et `#zones-parcourues` réellement peintes, météo **avant** zones et toutes deux après la liste, aucun libellé de niveau d'accès dans l'une ou l'autre, et aucun débordement à 360 px. Les scénarios PHP 30 à 54 rendent ces parties **isolément** ; c'est le seul contrôle qui prouve qu'elles sont **câblées** dans `front-page.php` | chaîne des données, utilisable sans JS, mobile réel |
| **`portail`** | **Épic 5 (#13, #14, #15)** — un vrai gestionnaire ouvre une vraie session : menu réduit aux seuls écrans du portail, **douze écrans du cœur refusés par URL directe**, écran de publication (25 massifs, 50 radios, deux formulaires **frères**, un seul bouton de soumission), **les 25 massifs renseignés au clavier seul** (gestes comptés), publication chronométrée, PRG et récapitulatif, **propagation mesurée jusqu'à la page publique**, historique avec qui/quoi/quand, **filtre par auteur à cheval sur une frontière de page**, export CSV (servi au gestionnaire, refusé à l'anonyme, sans secret ni IP), axe-core sur les deux écrans, 360 px, et **zéro origine tierce dans `wp-admin`**. C'est aussi ce scénario qui asserte que **les feuilles de style du portail sont SERVIES et APPLIQUÉES**, pas seulement enfilées, et que **chaque `<select>` réserve à droite la voie du chevron de wp-admin** — voir ci-dessous | portail, accessibilité, mobile réel, zéro requête tierce |
| **`portail-anonyme`** | **contrat #13** — la lecture publique `GET /massifs/v1/statuts` **reste 200 en anonyme** (non-régression : l'interdit 3 proscrit `rest_authentication_errors`), et **aucune** écriture ne passe : les deux routes du portail × quatre verbes, la sonde 401 avec un **corps valide** (un POST vide sort en 400 avant la garde et ne prouverait rien), l'historique REST refusé en lecture anonyme, les deux actions `admin-post.php`, et **aucun cookie posé** au visiteur anonyme | API publique, portail |
| **`2fa`** | **contrat #13** — la **rampe** (administrateur exigé mais non enrôlé : la connexion **aboutit** et tout écran renvoie au profil, `plugins.php` compris — jamais un refus, qui enfermerait dehors l'administrateur de production) ; puis le second facteur **armé** : étape 1 sans aucun cookie, code faux refusé, **code calculé en Node par une réimplémentation indépendante de la RFC 6238** accepté, **anti-rejeu A-17**, écran atteint une fois enrôlé. Puis la **suspension qui tue la session en cours** et le message contractuel du §11. Puis l'**écluse sur le vrai formulaire** : message uniforme jusqu'au seuil, délai chiffré au franchissement, verrou tenant contre le bon mot de passe, et **A-13 vérifié sur le formulaire** — un autre identifiant reste joignable depuis la même origine. Enfin, **l'étape 2 présentée SOUS VERROU** — voir ci-dessous | portail, force brute, 2FA |
| **`carte-selection`** | **issue #50** — la carte réellement peinte, **Regagnas sélectionné** : l'aplat de statut et son motif **restent entiers**, `--carte-cerne-clair` vaut **0** au palier département et **aucun pixel calcaire** n'est posé (*l'assertion qui a manqué à la v2.3*), `fill: none` et `stroke-linejoin: round` sur les deux couches du cerne, panes 400/410 et ordre DOM, les **trois paliers atteints par le zoom réel**, plancher du liseré à 1,5 px, survol = 1,5 × liseré **arrondi au demi-pixel supérieur**, halo du cerne tabulé, un massif **interdit garde son motif**, **Échap ferme le panneau et le cerne reste**, puis **360 px, 320 px (zoom texte 200 %) et `forced-colors: active`** | zéro requête tierce, accessibilité, mobile réel |
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

### `portail` — la voie du chevron, et pourquoi elle se mesure sur la RÉSERVE

Défaut trouvé le 16 août 2026 **en regardant l'écran**, invisible à axe-core comme à toute assertion
de débordement : wp-admin peint la flèche des `<select>` en `background-image` et lui réserve sa voie
par un `padding-right` **asymétrique** ; `historique.css` écrasait les deux côtés d'un coup
(`padding-inline: var(--esp-xs)`) sans rien remettre à droite, et les derniers glyphes du libellé
affiché passaient sous la flèche. Pas un cas limite : un `<select>` se dimensionne sur son option la
plus large, donc le pire cas **était** le cas nominal, et le filtre « Auteur » y tombait dès le
chargement. Corrigé par `3814a31`.

La sonde a été **reformulée** après le constat M-8 de la revue. Elle ne compare plus la largeur de
l'option la plus large à la voie — cette mesure-là avait deux vices : elle aurait rougi sur un
`<select>` légitimement bridé par `max-inline-size: 100%` sans qu'un pixel de libellé soit couvert,
et elle se jouait sur ±1 px de métrique de police. Elle asserte désormais la **réserve** :
`padding-inline-end` au moins égal à la voie, **lue sur le style calculé** et jamais codée en dur.
C'est la condition géométrique **suffisante** — la boîte de contenu finit à
`clientWidth - paddingRight`, donc un texte trop long est **rogné** par la boîte, jamais glissé sous
l'icône. Le recouvrement du libellé réellement sélectionné reste relevé, en `note` : c'est le
symptôme, pas le juge.

### `2fa`, jambe 5 — l'étape 2 sous verrou, seul chemin qui contournait l'écluse

L'étape 2 du second facteur **ne traverse pas `authenticate`** : elle est servie par
`Deuxfacteurs::traiter()`, qui appelle directement `wp_set_auth_cookie()`. Les trois greffes de
l'écluse — `barrer()` en 1, `constater()` en 40, `reaffirmer()` en 100 — n'y sont d'aucun secours.
Un jeton d'étape 2 obtenu **avant** un verrou, présenté avec le bon code **pendant** ce verrou,
ouvrait donc une session : le verrou était contournable en deux temps par qui connaît le mot de
passe. `Ecluse::attente()` est désormais opposé **en tête** de `traiter()`, avant le comptage des
essais et avant toute vérification du code (correctif `2ffba8d`).

Ce chemin est **inobservable en PHP** : la seule preuve opposable est qu'aucun cookie de session
n'est posé par une vraie soumission du vrai formulaire d'étape 2. D'où trois précautions dans la
jambe :

- le verrou est posé par **cinq échecs sur le vrai formulaire**, jamais écrit à la main — un verrou
  posé de l'extérieur ne prouverait pas que le chemin réel y mène ;
- le message attendu est celui de l'**écluse**, chiffré, et non « Code incorrect » : sans cette
  distinction, l'absence de session passerait pour vraie alors que le refus viendrait du code ;
- un **témoin obligatoire** rejoue la même séquence verrou levé et exige que la session s'ouvre —
  sans lui, la jambe serait verte si le second facteur était simplement cassé. Le témoin porte
  lui-même une garde (`action=massifs_2fa` dans l'URL) : à sa première exécution il était vert sans
  rien contrôler, l'administrateur ayant été **désenrôlé** par le `finally` de la jambe 2 et sa
  connexion empruntant la rampe jusqu'à `profile.php`.

**Ce que la jambe ne dit pas** : une session **déjà ouverte** continue d'être honorée pendant un
verrou. L'écluse verrouille des **tentatives de connexion**, pas des sessions vivantes. C'est un
non-objectif assumé, pas un trou : la révocation d'une session vivante est portée par la
**suspension** (A-16), éprouvée dans la jambe 3.

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

## `bypassCSP` — où il est posé, et surtout où il ne l'est JAMAIS

Depuis l'issue #16 le front public porte `script-src 'self'`. `page.addScriptTag()` est un script en
ligne : Chrome le refuse, et les trois passes axe-core tombaient **en panne d'exécution** — un rouge
qui se lisait comme un défaut d'accessibilité alors que c'était une panne de harnais.

La dérogation appartient au **pilote de test**, jamais au site : elle ne retire aucun en-tête et ne
change rien à ce que le serveur envoie. Elle est posée sur des **contextes dédiés**, et sur eux seuls :

| Scénario | Contexte dérogatoire | Ce qui reste sans dérogation |
|---|---|---|
| `a11y` | le contexte de la passe axe | — |
| `partielle` | le contexte de la passe axe | la mesure de débordement à 360 px, dans un contexte neuf |
| `portail` | un contexte dédié, muni des cookies de la session | le contexte principal, qui mesure les origines contactées dans `wp-admin` |

**Trois mesures ne la connaissent pas, et ne doivent jamais la connaître** : la preuve « zéro requête
tierce » (`tierce`), la mesure de la CSP elle-même (`csp`), et les budgets (`budgets`). Poser
`bypassCSP` sur l'une des trois la rendrait verte en n'ayant rien mesuré.

## Ce que cette suite ne couvre pas

Elle ne remplace ni un contrôle humain au lecteur d'écran, ni un vrai téléphone à 360 px, ni HTTPS en
production.

**Sauvegardes — ce qui est prouvé et ce qui ne l'est pas.** `70-durcissement-et-sauvegardes` crée de
vraies archives, éprouve la rotation jusqu'à la suppression, et vérifie qu'aucune n'est téléchargeable
par un visiteur anonyme. L'**aller-retour complet** — dump A, empreinte, restauration réelle par
`DROP`/`CREATE`, re-dump B, comparaison — est porté par `wp massifs sauvegarde verifier`, qui n'est
**pas** dans `tests/run.sh` : la commande écrase la base cible, et l'enchaîner aux autres scénarios
rendrait leurs mesures ininterprétables. Elle se joue **en dernier, à la main**, et son vert est le
seul fondement de la ligne « restauration testée » :

```bash
docker compose run --rm wpcli wp massifs sauvegarde verifier
```

Ce qui reste **non couvert** : la périodicité quotidienne (aucun déclencheur hôte), la copie hors
hébergeur (en sommeil par décision du propriétaire), et une restauration sur une **autre** machine que
celle qui a produit l'archive.

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
explicitement dans sa première jambe.

**Ce scénario affirmait l'INVERSE de ce qu'il affirme aujourd'hui, et c'est écrit ici pour que
personne ne le relise de travers.** Le contrat #25 avait gelé un état nommé
`enumeration_toujours_ouverte` : `GET /wp-json/wp/v2/users` devait répondre **200 et peuplé**, afin de
prouver que la chaîne Gravatar n'avait pas débordé sur un défaut voisin — son point B-3 renvoyait
nommément à l'issue **#16** pour corriger l'énumération. #16 l'a fait, et la couture **S-1** de son
contrat désignait ces lignes de test. L'attente est donc **retournée** : 404 en anonyme.

Conséquence à connaître : la preuve « la clé `avatar_urls` a disparu » ne peut plus être portée par
l'anonyme sur cette route — un 404 ne contient aucune clé, l'assertion y serait trivialement verte.
Elle est reprise **en session administrateur**, où la route répond 200 (non-régression d'administration
du contrat #16). **Le cookie n'y suffit pas** : `rest_cookie_check_errors()` du cœur remet l'utilisateur
courant à 0 quand une requête porte un cookie valide **sans nonce `wp_rest`**, et la route répond alors
404 comme pour un anonyme. Mesurée sans nonce, la non-régression se lirait comme une régression. Le
nonce est prélevé sur l'écran d'administration réellement servi.

Les trois pages « La démarche », « Accessibilité » et « Mentions légales » existent depuis l'issue #18
et sont couvertes par le scénario `pages`, ainsi que par `tierce`, `structure`, `mobile` et `a11y` —
elles sont entrées dans la constante `PAGES`. **Mais elles ne sont pas provisionnées** : leur prose est
du contenu, versionné sous `docs/recette/contenu/` et poussé en base par
`docs/recette/importer-pages.sh`. Sans cet import, elles répondent 404 et les scénarios ci-dessus
rougissent — c'est voulu. **`docker/reset.sh` les perd** : rejouer l'import après toute remise à zéro.

```bash
MSYS_NO_PATHCONV=1 docker compose run --rm -v "$PWD/docs:/docs" wpcli sh /docs/recette/importer-pages.sh
```

**Le portail est couvert depuis le lot de l'Épic 5** — `60-portail-journal-exact` côté domaine,
`portail`, `portail-anonyme` et `2fa` côté navigateur. Trois limites y sont **structurelles** :

- **Le chronomètre de la publication mesure la MACHINE, pas un opérateur.** Playwright frappe plus vite
  qu'une personne. Le scénario `portail` ne **prouve donc pas** la ligne « mise à jour complète en moins
  d'une minute » du §6 : il prouve que l'écran ne l'interdit pas, et il compte les **gestes** réellement
  nécessaires — 50 pour les 25 massifs au clavier —, qui est la grandeur qu'un humain paie. Le chiffre
  qui est, lui, réellement mesuré et opposable est l'**aller-retour serveur** de la publication, et la
  **propagation** jusqu'à la page publique.
- **L'écluse ne peut pas être éprouvée sur deux origines depuis un navigateur** : toutes les requêtes de
  la recette partent de la même IP. La granularité par origine (arbitrage A-13) est donc éprouvée en PHP,
  par le filtre public `massifs_auth_ip_client`, dans `60-portail-journal-exact` ; le navigateur, lui,
  n'éprouve que la granularité **par identifiant depuis une même origine**. Les deux ensemble couvrent
  l'arbitrage ; ni l'une ni l'autre seule.
- **Aucun scénario n'éprouve la remise à zéro de la démonstration publique** (§12, ligne « Démo
  publique ») : elle n'existe pas encore comme mécanisme.
- **Une session déjà ouverte reste honorée pendant un verrou d'écluse.** Non-objectif assumé, écrit
  ici pour n'être pas déduit : l'écluse verrouille des *tentatives de connexion*, pas des sessions
  vivantes. La révocation d'une session vivante est un mécanisme distinct — la **suspension** (A-16),
  éprouvée par la jambe 3 de `2fa`.
- **La granularité par origine reste non éprouvée sur le formulaire réel.** Éprouvée en PHP par le
  filtre `massifs_auth_ip_client`, et par identifiant depuis une même origine dans le navigateur.
  Aucune des deux ne frappe `wp-login.php` depuis deux IP distinctes ; il y faudrait deux machines,
  ou un mandataire de confiance.
- **La force de l'écluse n'est pas éprouvée derrière un proxy.** `X-Forwarded-For` n'est pas honoré
  par défaut, et le filtre qui l'ouvre n'est activé sur aucune stack de recette : le comportement en
  production derrière un répartiteur de charge n'est observé nulle part.
- **`Ecluse::message()` est passée de `private` à `public`** (arbitrage A-24), l'étape 2 devant rendre
  le refus avec la **même phrase** que la chaîne `authenticate`. Élargissement de surface assumé ; la
  recette éprouve l'identité de la phrase des deux côtés, pas l'innocuité de l'élargissement.

La couche EFFIS, l'indicateur Météo-France et la veille de fraîcheur sont couverts depuis le lot de
l'Épic 4 (scénarios `30` à `54`, et `bandes` pour le câblage dans la page servie). Deux limites y sont
**structurelles**, et aucune recette ne les lèvera :

- **Le connecteur météo ne rend jamais « disponible » par la donnée.** La garde de vocabulaire du
  contrat #10 reste fermée tant que les libellés officiels des crans ne sont pas sourcés. L'état
  peuplé n'est donc éprouvé que par la clé d'injection du gabarit (`37`), jamais par un aller-retour
  d'ingestion complet.
- **Aucun polygone de zone brûlée non vide n'existe hors des fixtures des scénarios `4x`**
  (invariant I-11.3). Le nominal simulé est un `FeatureCollection` **valide et vide** : sur la page
  servie, la bande des zones n'est donc jamais observée dans son état `zones_disponibles`. Ce que le
  navigateur voit d'elle est `couche_effis_indisponible`, et rien d'autre.

Conséquence directe : la fabrique `tests/rendu/etats.php` ne pose **aucun** état météo ni EFFIS. Les
deux bandes neuves sont observées par la recette de rendu dans leur état d'absence — ce qui prouve
leur câblage, leur place, leur accessibilité et leur innocuité réseau, mais **pas** le rendu de leurs
états peuplés dans un navigateur.

La carte, son fond auto-hébergé, son repli statique et le point d'accès JSON public sont, eux,
couverts depuis le lot des issues #7, #8 et #9 — `carte`, `carte-degradee`, `sans-js` et
`22-api-publique-statuts`. Ce qui, là-dedans, reste **hors de portée d'une recette automatisée** est
énoncé plus bas.

Enfin, l'horloge du domaine n'est pilotée par aucun filtre : « hors saison » et « demain non publié »
sont donc éprouvés en demandant explicitement un jour aux gabarits (`21-rendu-etats-hors-saison`),
jamais sur la page d'accueil servie, qui suit l'horloge réelle du conteneur.

**Ce que la recette de la carte ne couvre pas.** Le scénario `carte` mesure le pas de hachure **à
l'écran**, dérivé du rapport `viewBox / largeur du <svg>` : c'est ce que le moteur applique, ce n'est
pas une lecture de pixels. Il n'observe ni la lisibilité réelle du motif, ni le contraste du fond
monochrome sous un vrai écran. La **cible tactile ≥ 44 px** sur les polygones n'est pas éprouvée — le
contrat #7 §9 écrit lui-même que la limite est assumée et que l'équivalent garanti est la liste
textuelle. Le **rendu à l'impression du repli statique** n'est pas couvert : `print.css` masque
`.bande--carte` entière et la couture C-1 du contrat #9 n'a pas de porteur. Enfin, la carte est
éprouvée dans **Chromium seul** : ni Firefox, ni Safari, ni un vrai appareil tactile.

Conséquence directe et **non couverte** : `front-page.php` appelle le domaine avec `null` (« aujourd'hui »)
et n'accepte aucun jour. Les bras `hors_saison` et `non_encore_publie` de son `match()` sont donc
**inatteignables en HTTP** tant que l'horloge du conteneur est en saison — aucun scénario n'observe
l'ardoise dans ces deux états. Les deux autres états sans chiffre le sont, eux : `indisponible` par les
modes `absente` et `veille-seule`, la branche « API absente » par le scénario `extension`.
