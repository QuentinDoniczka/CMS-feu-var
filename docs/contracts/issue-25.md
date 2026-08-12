# Contrat d'interface — Issue #25 — Supprimer la fuite Gravatar en session connectée

**Gelé le 12 août 2026** par `lead-issue-cms` (chaîne #25). Liant à partir de ce point.

Cette issue **ne touche aucun fichier de l'extension** ni aucun fichier de `docker/`.
`leaddev-back-cms` n'a donc pas été lancé — même forme que le contrat #5.

Ce n'est pas un contrat d'interface front↔back : c'est un **contrat de vérification**. Ce qu'il gèle,
c'est *où vit la garantie*, *ce que le test prouve*, et *ce qui reste ouvert et à qui*.

## Empreinte d'écriture — exhaustive

```
wp-content/themes/massifs/functions.php     (AJOUT EN FIN DE FICHIER — aucune ligne existante modifiée)
tests/rendu/recette-rendu.mjs               (2 remplacements chirurgicaux, additifs)
tests/README.md                             (2 remplacements chirurgicaux, additifs)
docs/contracts/issue-25.md                  (création — ce fichier)
```

Rien d'autre. **`docker/provision/provision.sh` et `docker-compose.yml` sont hors empreinte** — c'est
l'objet de l'arbitrage A-1. `index.php`, `page.php`, `404.php` (chaîne #24) et
`assets/css/composants.css` (chaîne #28) sont **interdits**.

---

## La violation — mesurée, jamais supposée

Relevés par le lead sur la stack du dépôt, WordPress **7.0.3**, avant tout correctif.

### Les empreintes sont bien celles des comptes

| Empreinte observée dans l'URL | `sha256` de |
|---|---|
| `37893279225a7075606a8c4d5209c3e1123b4ef67bcc31d37e2902c6c7eefd34` | `admin@massifs.local` |
| `b9150cd01ae2ac7b8023fa87e9eee9b461bc29af2583be57844ce8ead0ebe56d` | `gestionnaire@massifs.local` |
| `8e1606e6fba450a9362af43874c1b2dfad34c782e33d0a51e1b46c18a2a567dd` | `wapuu@wordpress.example` (auteur du commentaire de graine du cœur) |

La fuite de donnée personnelle annoncée par l'issue est donc **confirmée par recoupement**, pas admise
sur parole.

### Où elle se produit

| Surface | Session | Constat |
|---|---|---|
| `/` (accueil public) | connectée | `https://secure.gravatar.com/avatar/<sha256>?s=26&d=mm&r=g`, plus `s=52 2x`, `s=64`, `s=128 2x` |
| `/wp-admin/` | connectée | mêmes URL, plus l'empreinte de l'auteur du commentaire de graine (widget « Activité ») |
| **`/wp-json/wp/v2/users`** | **anonyme** | **HTTP 200**, `avatar_urls` en 24/48/96 px portant l'empreinte de l'**administrateur** |
| `/`, `/hello-world/`, `/sample-page/`, `/wp-login.php`, `/feed/` | anonyme | **propres** — 0 occurrence |

**Le titre de l'issue sous-estime la fuite.** « En session connectée » est faux au sens strict :
l'empreinte de l'administrateur est servie à **n'importe quel appelant non authentifié** par notre
propre API REST. Consigné ici ; correction du titre sur GitHub hors empreinte.

### Le chemin de code — lu dans le conteneur, pas cité de mémoire

```php
// wp-includes/admin-bar.php:279 et :325
$avatar = get_avatar( $user_id, 26 );   //   →  s=26
$user_info = get_avatar( $user_id, 64 );//   →  s=64

// wp-includes/pluggable.php:3258 — DANS get_avatar(), et nulle part ailleurs
if ( ! $args['force_display'] && ! get_option( 'show_avatars' ) ) { return false; }

// wp-includes/link-template.php:4492-4497 — DANS get_avatar_data()
$args = apply_filters( 'pre_get_avatar_data', $args, $id_or_email );
if ( isset( $args['url'] ) ) {
    return apply_filters( 'get_avatar_data', $args, $id_or_email );
}
$email_hash = '';   // ← l'empreinte est composée ICI, après le filtre
```

**Conséquence gelée** : `show_avatars` n'est consulté que par `get_avatar()`. Le chemin
`rest_get_avatar_urls()` → `get_avatar_url()` → `get_avatar_data()` **ne le consulte jamais**. C'est la
raison mesurée pour laquelle un correctif par réglage de site est insuffisant (A-1).

---

## Fonctions de lecture exposées par l'extension

**Aucune.** Cette issue n'appelle, ne déclare et ne modifie aucune fonction de l'extension. La surface
gelée par les contrats #2, #3, #5 et #6 est intacte.

## Routes REST

**Aucune n'est créée, supprimée, enregistrée ni filtrée.**

L'issue **modifie la charge utile** de deux routes du cœur, par effet du filtre 2, et c'est tout :

| Route | Avant | Après | Gelé |
|---|---|---|---|
| `GET /wp-json/wp/v2/users` | `avatar_urls` porte 3 URL Gravatar | la clé `avatar_urls` **disparaît du schéma** | la route reste **200**, et continue de lister **les mêmes utilisateurs** |
| `GET /wp-json/wp/v2/comments` | `author_avatar_urls` idem | idem | idem |

**Interdiction explicite** : ni `rest_endpoints`, ni `rest_prepare_user`, ni aucune modification de
*quels* utilisateurs REST expose. L'énumération est un défaut **distinct**, hors périmètre (A-4, B-3).

---

## États spéciaux

Le vocabulaire habituel de ce tableau (`information_indisponible`, `hors_saison`, `donnee_perimee`,
`couche_effis_indisponible`) est **sans objet** : cette issue ne rend aucune donnée de statut et ne
touche à aucun gabarit. Les états que le contrat gèle sont ceux que **le test doit pouvoir constater**.

| État | Émis / observable | Constaté par le test |
|---|---|---|
| `aucune_origine_tierce` | requêtes réellement émises par le navigateur | `egal( [], tierces )` sur chaque page |
| `aucune_empreinte_composee` | HTML et corps REST servis | aucune chaîne de 64 hex ; aucune des 3 empreintes nommées |
| `aucune_occurrence_gravatar` | HTML et corps REST servis | `/gravatar/i` absent |
| `session_reellement_ouverte` | cookie + HTML + accès admin | 4 gardes — voir « anti-faux-vert » |
| `barre_admin_toujours_rendue` | HTML du front en session | `#wpadminbar` compté à 1 — **le correctif ne retire pas la barre** |
| `enumeration_toujours_ouverte` | `GET /wp-json/wp/v2/users` anonyme | **200 et peuplé** — non-régression **inverse** : on n'a pas corrigé ce qu'on n'a pas le droit de corriger |
| `coupe_tenue_sous_force_display` | `get_avatar( 1, [ 'force_display' => true ] )` | `false` — **imprenable par un réglage de base** |

---

## Chaînes fournies par le serveur

**Aucune chaîne d'interface n'est écrite, ni par le thème, ni par l'extension.** Aucune copie
éditoriale n'est inventée : le correctif **retire** un élément décoratif, il n'en rédige aucun.

Ce qui disparaît de l'interface, et qui n'était porteur d'aucune information :

| Écran | Ce qui disparaît | Ce qui reste |
|---|---|---|
| Barre d'admin, « Bonjour, … » | vignette 26 px | **le nom du compte, en toutes lettres** |
| Menu déroulant de la barre | vignette 64 px | nom + tous les liens, dont « Se déconnecter » |
| `/wp-admin/users.php` | vignette 32 px | identifiant, rôle, e-mail |
| Tableau de bord, widget « Activité » | vignette de l'auteur | nom de l'auteur |
| `/wp-admin/profile.php` | ligne « Image de profil » **et son lien vers gravatar.com** | tout le reste du profil |

**Aucune perte d'information, aucune régression d'accessibilité** : le cœur produit ces avatars avec
`alt=""` (images décoratives), et lorsque l'URL est vide il **omet l'élément entier**
(`if ( ! $url ) return false;`) au lieu d'émettre un `<img src="">`. C'est exactement le piège traité
par A-21 du contrat #5 pour `emoji_svg_url`, résolu de la même façon.

---

## La garantie normative — deux filtres, un seul garant

| Rang | Filtre | Priorité | Garantit |
|---|---|---|---|
| **1** | `pre_get_avatar_data` → `$args['url'] = ''` | 100 | **Aucune empreinte d'e-mail n'est jamais composée**, sur tout chemin : `get_avatar()`, `get_avatar_url()`, `get_avatar_data()`, `rest_get_avatar_urls()`, `force_display`, `force_default`. **C'est LA garantie ; elle seule ferme la fuite REST anonyme.** |
| **2** | `option_show_avatars` → `'0'` | 100 | Court-circuit précoce de `get_avatar()` ; retrait de `avatar_urls` / `author_avatar_urls` du **schéma** REST ; retrait de la ligne « Image de profil » et de son lien `gravatar.com` de `profile.php` ; cohérence de l'écran Réglages → Discussion |

**`''` et jamais `null`** : le cœur teste `isset( $args['url'] )`. `null` rendrait `isset()` faux, le
cœur poursuivrait, l'empreinte serait composée — le correctif serait un **no-op silencieux**. La chaîne
vide est `isset()`-vraie et falsy : c'est exactement ce qu'il faut.

**Aucune garde de contexte** — ni `is_admin()`, ni `is_user_logged_in()`, ni `is_rest()`. La fuite est
mesurée anonymement **et** en session ; une garde de contexte rouvrirait la moitié du trou.

**Redondance voulue** : les deux filtres se recoupent sur le chemin REST par **deux mécanismes
différents** — suppression du champ (filtre 2), vidage de la valeur (filtre 1). Doctrine A-21.

Retirer le filtre 1 rouvre la fuite **et** rend le test rouge (`force_display`). Retirer le filtre 2
laisse la garantie tenue **et** rend le test rouge (chaîne « gravatar » sur `profile.php`).
**Le test ne peut pas devenir vert par une valeur en base de données** — c'est sa raison d'être.

---

## Ce que le test prouve, et ce qu'il ne prouve pas

**Prouve** : sur `/`, `/wp-admin/`, `/wp-admin/profile.php`, `/wp-admin/users.php`,
`/wp-json/wp/v2/users` et `/wp-json/wp/v2/comments` — anonymement **et** sous les deux comptes
`admin` et `gestionnaire-demo` — aucune requête navigateur vers un tiers, aucune empreinte servie, et
la coupe tient **même sous `force_display`**.

**Ne prouve pas** : le comportement sous un autre thème (la fuite revient si WordPress bascule sur un
thème de repli → **B-1**) ; les écrans d'administration volontairement non visités ; l'absence
d'énumération d'utilisateurs (**B-3**) ; le comportement sur l'hébergement de production, où aucun
`provision.sh` ne tourne — ce qui est précisément pourquoi le correctif est du **code** (A-1).

### La garde anti-faux-vert — la partie la plus importante

Une connexion silencieusement ratée produirait un « aucun gravatar » **trivialement vert**. Quatre
gardes rendent tout échec bruyant **avant** que la moindre assertion de fuite ne s'exprime :

1. cookie dont le nom commence par `wordpress_logged_in_` présent dans le contexte ;
2. sur `/` : `#wpadminbar` compté à 1, `body.logged-in`, nom d'affichage non vide ;
3. `/wp-admin/profile.php` → **200**, URL finale sans `wp-login.php` ;
4. sur `profile.php`, `input#user_login[value]` vaut exactement l'identifiant attendu.

Aucun mot de passe n'entre dans le dépôt : les identifiants sont lus dans `.env` par le helper
`lireEnv()` déjà présent (`WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` / `WP_MANAGER_USER` /
`WP_MANAGER_PASSWORD`).

### Écrans interdits au test — et pourquoi

`/wp-admin/plugin-install.php` · `/wp-admin/update-core.php` · `/wp-admin/theme-install.php` ·
`/wp-admin/about.php`.

Ces écrans chargent **légitimement** des images depuis `ps.w.org` / `s.w.org`. Les visiter rendrait
l'assertion d'origine rouge **pour une cause que cette issue n'a pas le droit de corriger** (case 6 de
la checklist, arbitrages A-11/A-24 du contrat #5).

---

## Interdits

- Le thème n'appelle **jamais** une source externe, ni une fonction d'ingestion, ni `$wpdb`.
- Le thème ne calcule **jamais** une règle métier. *(Inchangé — cette issue n'en approche aucune.)*
- **Aucune ligne existante de `functions.php` n'est retirée, modifiée ni réordonnée.** Le fichier porte
  les enfilements et retraits livrés par les chaînes #4, #5, #6, #22, #23 : les 5 poignées de feuilles,
  le préchargement des 2 polices, les 5 retraits du cœur sur **deux** accroches (A-24), et toute la
  neutralisation des émoji (A-20, A-21). **On ajoute en fin de fichier, point.**
- **Aucune modification du provisionnement**, ni de la valeur en base de `show_avatars` : le test ne
  doit **pas** en dépendre, elle vaut `1` sur la stack et doit le rester.
- **La barre d'administration n'est pas retirée** (`show_admin_bar` interdit — A-6).
- **L'énumération d'utilisateurs n'est pas corrigée** (A-4 / B-3).
- **L'occurrence `s.w.org` de `/wp-admin/*` n'est pas touchée** (case 6, A-11/A-24 du contrat #5).
- **Aucune assertion existante de la recette n'est modifiée ni affaiblie.** `s01` (l. 340) et `s10`
  restent intacts et verts.
- **Jamais de réécriture de `recette-rendu.mjs` ni de `tests/README.md`** : remplacements chirurgicaux
  sur ancre unique, relue juste avant écriture — deux chaînes sœurs travaillent dans le même arbre.
- **Aucune empreinte en dur dans le test** : elle se recalcule depuis l'adresse lue dans `.env`.
- **Aucune assertion de boîte blanche** (`has_filter()` et consorts) : `tests/README.md` règle 2
  n'admet que l'observable. `force_display` en est l'équivalent observable.

---

## Arbitrages

Décisions du lead. Chacune tranche un désaccord, une ambiguïté ou un trou constaté.

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-1** | **Thème vs provisionnement** — l'arbitrage que l'issue refuse explicitement de deviner (case 2) | **Le correctif vit dans le code du thème. `provision.sh` n'est pas touché.** | Deux raisons, la seconde **mesurée** : (a) `provision.sh` ne tourne **que** dans la stack Docker — sur l'hébergement cible, rien ne garantirait le réglage, et la preuve du §12 serait produite par la configuration d'un environnement de développement au lieu du livrable ; (b) **le réglage ne ferme pas la fuite** : `GET /wp-json/wp/v2/users` est servi anonymement par `rest_get_avatar_urls()` → `get_avatar_url()` → `get_avatar_data()`, chemin qui **ne consulte jamais `show_avatars`**. Un correctif par option aurait clos l'issue en laissant ouverte la fuite la plus grave. Vérifié par bascule réelle de l'option, puis **restaurée à `1`** |
| **A-2** | Quel levier exactement | **`pre_get_avatar_data` est la garantie** ; `option_show_avatars` est la ceinture | `pre_get_avatar_data` coupe **avant** `$email_hash` (`link-template.php:4497`) : l'empreinte n'est pas masquée, elle n'est **jamais composée**. Traite la dimension « donnée personnelle » du ticket, pas seulement la dimension « requête tierce ». Doctrine ceinture-bretelles d'**A-21** du contrat #5, même contrainte, même fichier |
| **A-3** | La fuite REST anonyme est-elle dans le périmètre d'une issue intitulée « en session connectée » ? | **Oui.** Même cause racine, fermée par le **même geste unique** | La case 6 de la checklist prévoit exactement ce cas de figure (« sauf si … elles partagent la même cause corrigible d'un seul geste (à documenter) »). L'exclure aurait signifié livrer un correctif qui laisse fuiter l'empreinte de l'administrateur **anonymement**, en le sachant. Documenté ici, comme la case l'exige |
| **A-4** | L'énumération d'utilisateurs par `/wp-json/wp/v2/users` (200 anonyme, `id`/`name`/`slug`) | **Hors périmètre. Rapportée en B-3, non corrigée** | Cause distincte, geste distinct, exigence distincte (brief §9). La corriger ici serait exactement le débordement que la case 6 interdit. Le test **asserte que la route reste 200 et peuplée** : non-régression **inverse**, pour prouver qu'on n'y a pas touché |
| **A-5** | Les deux `<script src>` supplémentaires (case 4 de la checklist) | **Aucun correctif dû. Réponse documentaire** | **Mesurés même origine** : `http://localhost:3002/wp-includes/js/hoverintent-js.min.js` et `…/admin-bar.min.js`. Ils ne violent **ni** la contrainte n° 2 (domaine tiers) **ni** le budget §10, qui porte sur l'accueil du **visiteur anonyme**, lequel n'en charge aucun. Ils viennent de la barre d'admin, pas des avatars — vérifié : ils **persistent** avec `show_avatars=0`. Origine distincte, donc **à traiter séparément si un jour on le décide**, ce que la case 4 autorise explicitement |
| **A-6** | Retirer la barre d'administration du front public | **Refusé, dans cette issue** | Deux raisons : elle **ne peut pas** clore l'issue (`is_admin_bar_showing()` retourne `true` avant le filtre sur les écrans d'administration — `/wp-admin/*` continuerait de fuir) ; et c'est une **décision produit** engageant le parcours du compte de démonstration publié (brief §6) et le rendu vu par un décideur communal (contrainte n° 4). Une chaîne d'exécution n'invente pas une règle produit. Le test **asserte que la barre est toujours rendue** |
| **A-7** | `tests/README.md` n'est pas nommé dans l'empreinte que l'orchestrateur m'a donnée | **Inclus**, en ajout strictement additif (1 ligne de tableau + 1 phrase) | L'empreinte accorde « le fichier de test de non-régression dans le répertoire de recette existant » ; or la convention du dépôt (`tests/README.md`, tableau des scénarios) impose d'y inscrire tout scénario. Livrer le scénario sans sa ligne produirait une documentation fausse dès le commit. Extension **minimale, additive et déclarée** — jamais silencieuse |
| **A-8** | **A-12 du contrat #5 est infirmé** | Le contrat #5 écrivait : « `show_avatars` est un **réglage de site**, propriété du provisionnement ou de la chaîne `securite` ». **C'est faux au niveau du code** | Ce n'est pas un réglage : c'est un **chemin de code qui compose une empreinte d'e-mail**, dont la moitié la plus exposée ignore le réglage. A-12 avait raison de refuser que trois chaînes l'écrivent chacune de leur côté, et raison de le signaler ; il se trompait sur la **nature** de la chose. La propriété laissée vacante est reprise ici, **à titre transitoire jusqu'à B-1** |
| **A-9** | Le filtre 2 rend la case « Afficher les avatars » de Réglages → Discussion **inerte** : la cocher reste sans effet | **Compromis assumé et documenté. Traité définitivement par B-2** | L'état affiché devient **exact** (décoché = aucun avatar rendu), ce qui vaut mieux que la situation inverse — cocher, et ne rien voir s'afficher. Un écran qui dit la vérité et n'obéit pas est préférable à un écran qui ment. Les écrans d'administration appartiennent à l'extension : le rendre lecture seule **avec son explication** est une demande ferme, pas un bricolage du thème |
| **A-10** | Posture défensive du rappel de filtre sur une entrée illisible | **Remplacer** une `$args` non-tableau par un tableau minimal, plutôt que la laisser passer | Écart **délibéré** avec `massifs_indice_vise_hote()` du même fichier, qui **conserve** ce qu'il ne sait pas lire. La raison est symétrique : là il s'agissait de ne jamais supprimer à l'aveugle ; ici il s'agit de ne **jamais composer une empreinte**. À écrire dans le docblock, sans quoi la divergence passerait pour une inattention |
| **A-11** | Priorité des deux filtres | **100** | Même idiome que `massifs_retirer_feuilles_du_coeur` : dernier mot après tout filtre tiers. Conséquence à documenter dans le docblock : un futur avatar **local** du portail devrait s'enregistrer **après** 100 |
| **A-12** | `option_show_avatars` et non `pre_option_show_avatars` | **`option_`** | `update_option()` appelle `get_option()` pour comparer avant d'écrire ; un `pre_option_` court-circuiterait cette comparaison et pourrait empêcher une écriture légitime en base. `option_` filtre la lecture sans toucher au chemin d'écriture |
| **A-13** | Où vit le test | **Nouveau scénario `gravatar` dans `recette-rendu.mjs`**, `s10_apiPublique` non modifié | Seul un vrai navigateur prouve les requêtes **réellement émises** — raison d'être déclarée de ce fichier. Un scénario PHP affirmant `get_avatar() === false` serait un test unitaire du cœur de WordPress, interdit par `tests/README.md`. Et `s10` ne fait **aucun `GET`** sur `/wp-json/wp/v2/users` : son sujet est le contrôle d'accès, pas la charge utile. Un seul propriétaire pour toute la question Gravatar |
| **A-14** | Ordre d'implémentation | **Le test est écrit et vu ROUGE avant le correctif** | Un test de non-régression qui n'a jamais été rouge ne prouve rien. C'est la seule façon de savoir qu'il observe la bonne chose |
| **A-15** | `refacto-cms` constate que le repli `''` de `lireEnv()` sur les adresses e-mail rendrait **deux assertions nommées vertes à vide** si une clé disparaissait de `.env` (`sha256('')` = `e3b0c442…`, jamais présent) | **Corrigé** : les adresses lues sont validées (non vides, contenant `@`) **avant** tout calcul d'empreinte, en rouge bruyant sinon. **Pas** de recopie des valeurs par défaut de `docker-compose.yml` / `provision.sh` dans le test | Ce n'est **pas** une cinquième garde anti-faux-vert — les quatre gelées prouvent que *la session est ouverte* ; celle-ci valide la *configuration du scénario lui-même*. Recopier les défauts aurait créé une seconde source de vérité, exactement ce que le contrat évite. Le balayage générique 64-hex rattrapait déjà une vraie fuite : le trou était **latent**, jamais aveugle |
| **A-16** | Le balayage « aucune occurrence gravatar » de `/wp-admin/` doit son vert à `get_comment_excerpt()` (20 mots + `strip_tags`), qui tronque le commentaire de graine du cœur **juste avant** le mot « Gravatar » | **Assertion conservée telle quelle, ni exclue ni restreinte. Documentée par un commentaire** | Affaiblir une assertion verte pour une raison fragile est interdit (`tests/README.md` règle 4). Le risque résiduel est un **faux rouge** futur, jamais une fuite manquée : c'est le bon côté pour se tromper. La conduite en cas de rouge est écrite sur place — étendre l'exclusion éditoriale à cette surface, **jamais** toucher aux balayages d'empreinte, qui sont ceux qui prouvent le correctif |

### Demandes fermes portées au back — hors lot, à ordonnancer

| # | Demande | Motif |
|---|---|---|
| **B-1** | Créer `wp-content/plugins/massifs-core/includes/security/<module>/module.php` et y **déplacer** la coupe des avatars, le thème n'en conservant au plus qu'un rappel documentaire | **Le thème n'est pas le domicile durable d'une garantie de sécurité.** Un changement de thème actif, ou un `Fatal error` qui fait basculer WordPress sur un thème de repli, **rouvre la fuite instantanément**. L'architecture cible de `CLAUDE.md` place `securite` dans l'extension — et le chargeur de modules déclare déjà `security` comme **première couche**, avant `domain` : le module y serait chargé automatiquement, **sans aucune modification du chargeur**. Le répertoire **n'existe pas** (`includes/` ne contient que `domain/` et `ingest/`) et est **hors de mon empreinte**. Jusqu'à sa création, le filtre du thème **est** la garantie |
| **B-2** | Retirer, ou rendre lecture seule **avec son explication**, le champ « Afficher les avatars » de Réglages → Discussion | Conséquence directe d'**A-9** : l'état affiché est exact, mais l'interaction est muette. Les écrans d'administration appartiennent à l'extension |
| **B-3** | Bloquer l'énumération d'utilisateurs : `/wp-json/wp/v2/users` anonyme, `?author=N`, `/wp-sitemap-users-1.xml` | **Brief §9, exigence explicite** (« énumération d'utilisateurs bloquée »). **Mesuré** : 200 anonyme exposant `id`, `name`, `slug` de l'administrateur. Cause distincte de celle-ci, geste distinct. **Interdit de corriger dans cette issue** (A-4) |

---

## Hors périmètre et **sans propriétaire** — à attribuer

Le provisionnement Docker et la valeur en base de `show_avatars` · `show_admin_bar` et le rendu de la
barre d'administration sur le front public (décision produit) · les deux `<script src>` et les feuilles
`admin-bar.min.css` / `dashicons.min.css` de la barre, **toutes même origine** · le décalage
`margin-top: 32px !important` de `_admin_bar_bump_cb` sur le front en session (sujet de rendu,
contrainte n° 4, aucun scénario de recette n'éprouve aujourd'hui la variante connectée) ·
l'occurrence `s.w.org` de `/wp-admin/*` (A-11 / A-24 du contrat #5) · l'énumération d'utilisateurs
(B-3) · la qualification juridique d'une empreinte `sha256` d'adresse e-mail, si elle doit figurer dans
les mentions légales ou la page « La démarche » — le §9 (« aucune ressource tierce ») suffit à fonder
le correctif **sans** avoir à trancher ce point.
