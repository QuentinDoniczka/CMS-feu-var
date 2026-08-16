# Contrat d'interface — Issue #16 — Durcir la sécurité du site et mettre en place les sauvegardes

> **Issue mono-côté (extension uniquement).** Aucun fichier de thème n'est écrit par cette chaîne, et
> aucun `leaddev-front-cms` n'a tourné. Ce contrat est néanmoins gelé, et ce n'est pas une formalité :
> la CSP posée ici **lie le balisage futur du thème**, et la fermeture de `wp/v2/users` **invalide une
> assertion de recette gelée**. Les deux contraintes s'imposent à des fichiers que cette chaîne n'a pas
> le droit de toucher. Un contrat est le seul endroit où elles peuvent être opposables.

**Empreinte de la chaîne** — écriture autorisée nulle part ailleurs :

- `wp-content/plugins/massifs-core/includes/security/durcissement/**`
- `wp-content/plugins/massifs-core/includes/security/sauvegardes/**`

**Aucun fichier d'amorçage hors empreinte n'est modifié.** `massifs-core.php` (l. 122-167) charge les
modules par convention (`scandir` sur `includes/security/`, puis `<module>/module.php`). Créer les deux
répertoires suffit. Ordre alphabétique effectif : `alertes` → `auth` → **`durcissement`** → `roles` →
**`sauvegardes`**.

---

## Fonctions de lecture exposées par l'extension

Toutes **totales** : jamais `null`, jamais `WP_Error`, toutes les clés toujours présentes. **Aucune n'est
destinée au thème** — elles existent pour rendre le durcissement vérifiable sans navigation.

```php
massifs_durcissement_entetes(): array<string,string>
```
Les en-têtes qui **seraient** émis pour la requête courante, dans l'ordre d'émission. Pure, sans effet de
bord. C'est la surface qu'un test interroge pour prouver la CSP.

```php
massifs_durcissement_politique_mises_a_jour(): array{
    mineures_auto: bool, majeures_auto: bool,
    extensions_auto: bool, themes_auto: bool,
    edition_code_interdite: bool, constante_posee: bool
}
```
`constante_posee` distingue « `DISALLOW_FILE_EDIT` est définie » de « le filtre interdit ». Les deux
mécanismes sont visibles **séparément** : sinon on ne saurait pas lequel des deux tient, et un
diagnostic serait impossible le jour où l'un des deux tombe.

```php
massifs_durcissement_enumeration_fermee(): bool
massifs_sauvegardes_derniere(): array{ existe, nom, chemin, genere_le, octets, complet, tables, lignes }
massifs_sauvegardes_lister(): list<array{ nom, genere_le, octets, complet }>
massifs_sauvegardes_repertoire(): string
```

---

## Routes REST

**Aucune route créée.** Deux routes du **cœur** sont retirées **pour l'appelant anonyme uniquement**, via
`rest_endpoints`, par `unset()` de deux clés **nommées en littéral**.

> **Le littéral est contractuel.** Un `str_starts_with( '/wp/v2/users' )` emporterait
> `/wp/v2/users/me`, dont l'éditeur de blocs dépend. Ne jamais matcher par préfixe.

| Route | Anonyme avant | Anonyme après | En session |
|---|---|---|---|
| `GET /wp-json/wp/v2/users` | 200, liste peuplée | **404 `rest_no_route`** | **200 inchangé** |
| `GET /wp-json/wp/v2/users/<id>` | 200 | **404** | **200 inchangé** |
| `POST /wp-json/wp/v2/users` | 401/403 | **404** | inchangé |
| `GET /wp-json/wp/v2/users/me` | 401 | **401 inchangé** | inchangé |
| `GET /wp-json/massifs/v1/statuts` | 200 | **200 — intact, §5.4** | 200 |

**Interdit absolu, et c'est le réflexe naturel :** `rest_authentication_errors`, ou tout filtre global
d'authentification REST. L'encadré en tête de `security/auth/GardeRest.php` (l. 6-14) l'explique — il
court-circuite `WP_REST_Server::dispatch` pour **toute** l'API et renverrait 401 sur
`GET /massifs/v1/statuts`, cassant le §5.4 (données ouvertes) et la carte publique.

---

## Les quatre surfaces d'énumération

Toutes conditionnées `! is_user_logged_in()`, **sauf le plan de site** (voir A-4).

| Surface | Geste | Hook, priorité |
|---|---|---|
| `GET /wp/v2/users[/<id>]` | `unset()` des deux clés littérales | `rest_endpoints`, 10 |
| `?author=N` | retrait de `author`/`author_name` de `$wp->query_vars` + `error = '404'` | `parse_request`, **1** |
| `/author/<slug>/` | `set_404()` + `status_header(404)` + `nocache_headers()` | `template_redirect`, **0** |
| `wp-sitemap-users-*.xml` | fournisseur `users` → `false` | `wp_sitemaps_add_provider`, 10 |
| oEmbed | `unset( author_name, author_url )` + retrait de la découverte | `oembed_response_data`, 10 |

> **Les priorités 1 et 0 sont porteuses, pas décoratives.** La fuite réelle de `?author=N` n'est pas dans
> le corps de la page : c'est `redirect_canonical` (sur `template_redirect`, priorité 10) qui émet un
> **301 avec `Location: /author/<identifiant-de-connexion>/`**. Un test qui ne lit que le HTML final ne
> verrait rien. Intervenir avant lui est ce qui ferme la surface ; intervenir après ne ferme rien.

**Non-régression administration, opposable :** `GET /wp-json/wp/v2/users` **avec** cookie admin ⇒ 200
peuplé ; **sans** ⇒ 404. `Roles\EcranComptes` greffe sur le rendu PHP du cœur (`manage_users_columns`,
`user_row_actions`, `bulk_actions-users`) et ne consomme jamais cette route.

**Reste ouvert, dit pour que personne ne le redécouvre :** le flux RSS expose `<dc:creator>` avec le **nom
affiché**, jamais l'identifiant de connexion. Ce n'est pas de l'énumération de comptes au sens du §9.

---

## En-têtes garantis sur le front public

`send_headers` **ne se déclenche ni dans `wp-admin`, ni sur `wp-login.php`, ni sur une requête REST** —
`WP::send_headers()` n'est appelé que depuis `WP::main()`. Le périmètre est donc obtenu **par
construction**, pas par une liste de conditions à maintenir.

| En-tête | Valeur | Condition |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'` | **`! is_user_logged_in()`** (A-2) |
| `X-Content-Type-Options` | `nosniff` | toujours |
| `Referrer-Policy` | `no-referrer` | toujours |
| `X-Frame-Options` | `DENY` | toujours |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=(), usb=()` | toujours |
| `Strict-Transport-Security` | `max-age=15552000; includeSubDomains` | **`is_ssl()` seulement** |
| `Cross-Origin-Resource-Policy` | **non posé** | fermerait l'open data du §5.4 à toute origine |
| `X-Robots-Tag` | **non touché** | propriété de `seo-meta.php`, chaîne sœur #18 |

**HSTS sans `preload`, jamais.** `preload` est irréversible à l'échelle du navigateur.

**HSTS conditionné à `is_ssl()`** est la lecture retenue de « en sommeil, pas supprimé » : l'en-tête est
écrit, il ne part qu'en HTTPS, il s'arme donc seul à la publication et **ne ment jamais en local**.

---

## États spéciaux

Le vocabulaire habituel du domaine (`information_indisponible`, `hors_saison`, `donnee_perimee`,
`couche_effis_indisponible`) est **sans objet** : cette issue ne rend aucune donnée de statut et ne touche
aucun gabarit. Elle introduit deux états **d'exploitation seulement, jamais rendus à un visiteur**.

| État | Émis par le serveur | Rendu par le thème |
| `information_indisponible` | sans objet | sans objet |
| `hors_saison` | sans objet | sans objet |
| `donnee_perimee` | sans objet | sans objet |
| `couche_effis_indisponible` | sans objet | sans objet |
| `sauvegarde_incomplete` | manifeste `complet:false` + code de retour non nul | **jamais rendu** |
| `sauvegarde_absente` | `massifs_sauvegardes_derniere()['existe'] === false` | **jamais rendu** |

---

## Chaînes fournies par le serveur

**Aucune chaîne d'interface publique n'est écrite.** Le durcissement est invisible par construction ;
la sauvegarde parle exclusivement à WP-CLI. Aucun libellé de niveau, aucune consigne, aucune attribution,
aucune phrase de fraîcheur n'est composée ou modifiée par cette issue.

Les seules chaînes produites sont des **sorties WP-CLI et des README**, en français, jamais du HTML.

---

## Commandes WP-CLI

Espace : `wp massifs sauvegarde <sous-commande>`. **Forme sous-commande retenue** (A-1).

| Sous-commande | Options | Codes de retour |
|---|---|---|
| `creer` | `--sans-fichiers`, `--sans-base`, `--repertoire=`, `--porcelain` | `0` complet · `1` `complet:false` · `2` échec |
| `lister` | `--format=`, `--garder-filets` | `0` · `2` |
| `inspecter <archive>` | `--format=` | `0` · `1` manifeste illisible · `2` |
| `restaurer <archive>` | `--oui`, `--sans-filet`, `--sans-fichiers`, `--sans-base`, `--nom-base=`, `--forcer` | `0` · `1` incomplète · `2` échec · `3` refusée |
| `verifier` | `--nom-base=`, `--je-sais-ce-que-je-fais`, `--conserver-archive` | `0` vert · **`1` aller-retour infidèle** · `2` échec · `3` refusée |
| `purger` | `--garder=`, `--simuler` | `0` · `2` |

**Garde de cible, dans le service et pas seulement dans la commande** : `verifier` et `restaurer`
refusent (code 3) sauf si `wp_get_environment_type()` vaut `local`/`development`, **ou** si
`--je-sais-ce-que-je-fais` est passé **avec** `--nom-base=` égal à `DB_NAME` saisi à la main. `DB_NAME`,
`DB_HOST`, `site_url()` et l'environnement sont **toujours imprimés avant tout geste**.

---

## Le moteur de dump — les garanties opposables

C'est le seul endroit du lot où un bug silencieux produit une archive **restaurable en apparence et fausse
en contenu**. Un backup qui ment est pire que pas de backup. Ces garanties ne sont pas des détails
d'implémentation : elles sont contractuelles.

1. **`NULL` ≠ `''`.** Le test de nullité porte sur la valeur **brute, avant tout cast**, et c'est la
   première ligne de `litteral()`. Directement porteur : `wp_massifs_statuts.niveau_cle` est NULLABLE, et
   son `NULL` signifie « la source a publié une ligne sans statut d'accès » — fait **distinct** de « la
   source n'a rien publié ». Les confondre fausserait l'historique du §4.2.
2. **Binaire et UTF-8 invalide** ⇒ littéral hexadécimal `0x…`, sur type binaire **ou** sur
   `! mb_check_encoding( $valeur, 'UTF-8' )`. Cas limite nommé : chaîne binaire vide ⇒ `''`, jamais `0x`.
3. **`SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'`**, jamais `SHOW TABLES` : une vue dumpée en
   `CREATE TABLE` casse la restauration.
4. **Noms de tables issus d'une liste blanche du serveur**, ré-encadrés en accents graves. Un nom absent
   de cette liste n'est **jamais** interpolé.
5. **Pagination par clé, pas par `OFFSET`**, dès qu'une clé primaire mono-colonne entière existe. Sous
   écriture concurrente, `OFFSET` **saute des lignes** : le dump finit vert et l'archive est incomplète.
   Le repli `OFFSET` est marqué `pagination:"offset"` **dans le manifeste**, pour que la faiblesse soit
   lisible dans l'archive et pas seulement dans le code.
6. **`COUNT(*)` avant la boucle**, comparé aux lignes émises. Divergence ⇒ `complet:false` **et** code de
   retour non nul. **Un dump partiel n'est jamais étiqueté complet.**
7. **`$wpdb->last_error` contrôlé après chaque requête** ; toute erreur interrompt le dump entier et
   supprime le temporaire. Un dump qui avale une erreur est l'archétype du backup qui ment.
8. **`AUTO_INCREMENT=<n>` n'est normalisé que dans la projection de comparaison, jamais dans l'archive.**
   Le retirer de l'archive « pour simplifier » produirait une restauration subtilement fausse.
9. **`NO_AUTO_VALUE_ON_ZERO`** : sans lui, une colonne `auto_increment` portant `0` serait renumérotée au
   rejeu. Rare, silencieux, irréversible.
10. **Une instruction par ligne, terminée par `;\n`.** Le lecteur découpe sur le saut de ligne et
    **refuse** toute ligne non terminée par `;`. Le découpage sûr est acheté par une contrainte sur
    l'écrivain, **pas** par un analyseur syntaxique : un `explode(';')` naïf casse sur toute chaîne PHP
    sérialisée, c'est-à-dire sur presque chaque ligne de `wp_options`.
11. **Transients exclus** (`_transient_%`, `_site_transient_%`). Ce n'est pas de la propreté :
    restaurer un transient vieux de trois semaines réinjecte un état périmé sous les règles de fraîcheur
    du §4.5 — exactement le « statut périmé présenté comme courant » que le brief interdit.
12. **Écriture atomique** : `.tmp-<uuid>` puis `rename()`. Une archive à moitié écrite ne porte jamais un
    nom valide. La rotation ne s'exécute qu'après un `rename` réussi.
13. **Le répertoire d'archives est exclu du périmètre fichiers, en dur et non filtrable.** Une sauvegarde
    qui contient les précédentes double de taille à chaque exécution. C'est une garde, pas un réglage.

**Parade globale** : dump A → empreinte → restauration → re-dump B → empreinte → comparaison. La
projection normalise **exactement trois choses, imprimées à chaque exécution** : `AUTO_INCREMENT=\d+`,
l'ordre des tables, et la liste nommée des lignes exclues. Rien d'autre n'est jamais normalisé — **c'est
dans « exclu de la comparaison » que se cache un faux vert.**

---

## Périmètre de la sauvegarde

**Base-centrée.** Ce qui est irremplaçable : la base (statuts, historique §4.2, comptes) et les fichiers
**non versionnés**. Le thème et l'extension sont dans git ; le cœur WordPress vit dans une image
reproductible. Sauvegarder trente fois ce que git tient déjà n'est pas de la prudence, c'est du bruit.

- **Inclus** : toutes les tables du préfixe, **y compris `wp_users` et `wp_usermeta`** (A-5) ;
  `wp-content/uploads/` ; `plugins/massifs-core/data/`.
- **Exclus** : `data/tuiles/**` (pyramide volumineuse, **commitée et régénérable** — double raison),
  `uploads/massifs-tiles/**`, caches, `**/*.tmp`, et le répertoire d'archives lui-même.

**Rotation : par nombre, 30 archives, en défaut opposable.** Sans cron quotidien, « 30 jours » et
« 30 archives » divergent, et seul le compte a un sens observable ici. La rétention par âge existe,
filtrable, désactivée par défaut.

---

## Interdits

- Le thème n'appelle jamais une source externe ni une fonction d'ingestion.
- Le thème ne calcule jamais une règle métier (saison, péremption, formatage de niveau).
- L'extension n'émet jamais de HTML de présentation publique.

**Et, propres à cette issue — opposables au thème, aux tests et aux chaînes futures :**

1. **Aucun `<script>` en ligne exécutable, bloc `<style>`, `cssText`, `setAttribute('style')` ou URL
   `data:`** dans une page publique. La CSP les bloque, sans repli. Un îlot
   `<script type="application/json">` reste permis (A-3).
2. **Ne jamais appeler `/wp-json/wp/v2/users` depuis le navigateur** — 404 en anonyme, par conception.
3. **Ne jamais poser `rest_authentication_errors`** ni aucun filtre global d'authentification REST.
4. **Ne jamais ajouter `'unsafe-inline'` à `script-src`** pour faire passer un besoin ponctuel. Le repli
   légitime est le hachage, ou le déplacement de la donnée dans un attribut `data-`.
5. **Ne jamais appeler `Restauration` ou `DumpSql` hors WP-CLI.** Les gardes refusent, mais la tentative
   est déjà le défaut.
6. **Aucun bouton, page de réglages ou route REST de sauvegarde/restauration.** Une restauration à un clic
   depuis `wp-admin` est une arme braquée sur le pied du site, et un cadeau à un compte compromis.
7. **Ne jamais écrire dans `sauvegardes/archives/`** en dehors du moteur.
8. **Ne jamais supposer qu'une planification tourne.** `DISABLE_WP_CRON` est vrai sur les deux services.
9. **Ne jamais poser `DISALLOW_FILE_MODS`** : il désactive `WP_Automatic_Updater` en entier et tuerait les
   mises à jour mineures automatiques exigées par la même issue.
10. **Ne jamais écrire « protégé par `.htaccess` » comme un fait** tant que `AllowOverride` n'a pas été
    mesuré sur l'image `wordpress:php8.3-apache`. La protection effective vient aujourd'hui de
    `plugins-guard.conf`, pas des `.htaccess` livrés.

---

## Arbitrages

Issue mono-côté : il n'y avait pas deux plans à réconcilier. Les arbitrages ci-dessous tranchent soit une
contradiction **interne à l'issue**, soit une question que le plan a remontée, soit un conflit entre cette
chaîne et un artefact gelé par une chaîne antérieure.

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| **A-1** | `--verifier` (option, ma formulation initiale) vs `verifier` (sous-commande, proposition du plan) | **Sous-commande** | Idiome WP-CLI, et laisse les options libres pour la forme de sortie. Je retire ma formulation. |
| **A-2** | CSP sur le front en session : `'unsafe-inline'`, hachages du cœur, ou anonyme seulement | **Anonyme seulement** | Fait absent de mon brief au plan, trouvé par lui : le **cœur** émet deux blocs `<style>` en ligne sur le front en session (`wp_admin_bar_header`, `_admin_bar_bump_cb`), et le scénario `barre_admin_toujours_rendue` du contrat #25 **gèle** la présence de la barre. `'unsafe-inline'` annulerait l'intérêt de l'en-tête ; les hachages du cœur sont fragiles d'une version à l'autre. L'anonyme, c'est 100 % du public réel. **Affaiblissement borné et assumé** : un administrateur naviguant sur le front ne reçoit pas de CSP. |
| **A-3** | L'îlot `<script type="application/json" id="carte-donnees">` survit-il à `script-src 'self'` ? | **Oui, il est permis** | C'est un *data block* : l'algorithme de préparation d'un élément `script` sort avant le contrôle CSP quand le type n'est ni classique ni module. `script-src 'self'` **sans** `'unsafe-inline'` reste donc atteignable. **Seul point de la CSP qui ne se prouve pas sur le papier** — à confirmer dans Chrome à la recette de lot. Repli si infirmé : déplacer la charge dans un attribut `data-` (thème, hors empreinte ⇒ couture). |
| **A-4** | Fournisseur de plan de site `users` : retrait conditionnel à l'anonyme, ou inconditionnel ? | **Inconditionnel** | Seule dérogation à « tout conditionné à l'anonyme », et elle est motivée : les fournisseurs sont enregistrés sur `init`, une fois par requête. Conditionner produirait un **plan de site dont la composition dépend de la session de l'appelant**, alors qu'il est public par définition. Ce serait un défaut neuf, pas un durcissement. |
| **A-5** | `wp_users` / `wp_usermeta` dans l'archive : sécurité du dump vs réalité de la restauration | **Inclus** | Une restauration sans comptes n'est pas une restauration (§6). **C'est le compromis le plus lourd de cette issue et il est daté** : l'archive contient des **hachages de mots de passe et des secrets TOTP**, et vit sous la racine web jusqu'à la publication. Quatre protections cumulées, et le filtre `massifs_sauvegardes_repertoire` existe pour que la publication la sorte de la racine web — **c'est la première chose à faire en production**. Écrit comme un compromis dans le README, jamais comme une bonne pratique. |
| **A-6** | `DISALLOW_FILE_MODS` : l'issue le suggère implicitement (« édition de code impossible ») | **Non posé** | **Contradiction interne de l'issue, confirmée** : il désactive `WP_Automatic_Updater::is_disabled()` en entier et tuerait la tâche « mises à jour mineures automatiques » de la même liste de tâches. `DISALLOW_FILE_EDIT` suffit à l'objectif énoncé. |
| **A-7** | `DISALLOW_FILE_EDIT` : constante ou filtre ? | **Les deux** | La constante est évaluée à l'exécution par `map_meta_cap`, et l'extension est chargée bien avant `admin_menu` : elle fonctionne. Le filtre `map_meta_cap` est le **doublon défensif** qui reste vrai si la constante est définie à `false` ailleurs. Domicile durable de la constante : `wp-config.php`, hors empreinte (couture, déjà inscrite au contrat #13 l. 406). |
| **A-8** | `automatic_updater_disabled` forcé à `false` ? | **Non posé** | Le forcer écraserait le coupe-circuit d'un exploitant. La preuve opposable de la politique n'est pas « une mise à jour a eu lieu », c'est `massifs_durcissement_politique_mises_a_jour()`, lisible par un test. |
| **A-9** | `Cross-Origin-Resource-Policy` | **Non posé** | Risquerait de fermer l'open data du §5.4 à toute origine. |
| **A-10** | Rotation « 30 jours » (littéral de l'issue) vs « 30 archives » | **30 archives**, défaut opposable ; rétention par âge filtrable et désactivée | Sans cron quotidien les deux divergent, et seul le compte a un sens observable ici. |
| **A-11** | Écrans d'administration pour la sauvegarde | **Aucun** | Décision, pas omission. Ce que le portail gagnerait en confort, il le paierait en surface d'attaque. |
| **A-12** | `style-src 'self'` refuse-t-il un `<style>` en ligne sur le front public ? | **Oui, un seul — et on l'assume** | **Mesuré dans Chrome, pas déduit.** Le cœur émet `<style id="wp-img-auto-sizes-contain-inline-css">` (135 c., règle `contain-intrinsic-size` sur `img[sizes="auto"]`). `style-src-attr 'unsafe-inline'` couvre les attributs `style=`, **jamais** les balises `<style>`. Écartés : `'unsafe-inline'` sur `style-src` (rouvrirait toute la surface des styles en ligne du seul document public pour 135 caractères de confort) et le **hachage CSP** (les en-têtes partent avant le corps, le hachage serait écrit en dur et **se périmerait silencieusement** à la première mise à jour mineure du cœur — que `allow_minor_auto_core_updates` → `true` **active de notre propre fait** ; un correctif qui s'auto-annule sans bruit est pire que pas de correctif). Impact §10 **nul aujourd'hui**, l'accueil ne servant aucune image `sizes="auto"`. Sortie propre ordonnancée : **S-11**. |
| **A-13** | Comment vérifie-t-on qu'une CSP bloque réellement un style en ligne ? | **Par `document.styleSheets`, JAMAIS par la console** | **Piège mesuré, à ne pas redécouvrir.** Chrome n'a émis **aucune** violation en console alors que la feuille était bel et bien refusée : capture armée, rechargement, filtre large ⇒ zéro `Refused to apply inline style`. Seul `sheet: false` / `rules: null` sur l'élément `<style>` tranche. **Quiconque revérifie cette CSP à la console conclura « rien n'est bloqué » et se trompera.** Méthode imposée, consignée aussi dans `durcissement/README.md`. |

---

## Coutures hors empreinte — à porter au niveau du lot, jamais des tâches pour cette chaîne

| # | Couture | Porteur |
|---|---|---|
| **S-1** | **Test gelé à mettre à jour, DEUX endroits.** `tests/rendu/recette-rendu.mjs` **l. 2600-2637** : le scénario `enumeration_toujours_ouverte` asserte `GET /wp-json/wp/v2/users` **200 et peuplé** en anonyme — non-régression **inverse** gelée par le contrat #25 (état `enumeration_toujours_ouverte`, l. 112) dont le B-3 disait « **interdit de corriger dans cette issue** » et **renvoyait nommément à #16**. Cette issue est celle-là : l'assertion **doit devenir 404**. Et **l. 1191** : `POST /wp-json/wp/v2/users` anonyme attendu `401 ou 403` devient **404**. Les assertions de disparition de `avatar_urls` (l. 2610-2624) deviennent trivialement vraies et **perdent leur pouvoir de preuve**. | `test-integration-cms`, niveau lot |
| **S-2** | En-têtes sur les ressources servies directement par Apache (CSS, polices, tuiles) : `send_headers` ne s'exécute pas pour elles. | `docker-cms` / `infra` |
| **S-3** | En-têtes sur les réponses REST : `send_headers` ne s'y déclenche pas non plus. Le cœur y pose `nosniff` de lui-même — **à confirmer**, et surtout **pas** par un filtre global d'authentification REST. | `infra` |
| **S-4** | Domicile durable de `DISALLOW_FILE_EDIT` : `wp-config.php`. Déjà inscrit au contrat #13 l. 406. | `infra` |
| **S-5** | Domicile durable des deux procédures (restauration, mise à jour majeure) : `docs/`, **pris par la chaîne sœur #18 ce lot-ci**. Logées en README de module en attendant. | issue `contenu`/`infra` |
| **S-6** | **Copie hors hébergeur** : **en sommeil** par décision du propriétaire (`portee-non-publiee.md` §1.2, issue #40). Non tenue, et ce n'est pas un manquement. | #40, si publication |
| **S-7** | Déménagement du moteur vers `docker/` : rendu possible sans réécriture par le filtre de répertoire et la commande WP-CLI (`--repertoire=/backups --porcelain`). | `docker-cms` |
| **S-8** | **Planification réelle.** `DISABLE_WP_CRON` est `true` sur les deux services (`docker-compose.yml` l. 63-64). La promesse « quotidienne » du §9 se tient par un cron système ou une tâche d'hébergeur appelant `wp massifs sauvegarde creer`. | `infra` |
| **S-9** | Recouvrement possible avec #18 sur le plan de site (`seo-meta.php`). Pas de conflit de fichier ; à signaler pour que #18 ne réenregistre pas le fournisseur `users`. | niveau lot |
| **S-10** | `AllowOverride` non mesuré sur l'image : les `.htaccess` livrés sont peut-être décoratifs sur cette stack. Ne change pas la conception (o2switch en a besoin), mais interdit d'en faire un fait. | `docker-cms` |
| **S-11** | **`contain-intrinsic-size` à déplacer dans la feuille du thème.** La CSP refuse le bloc `<style id="wp-img-auto-sizes-contain-inline-css">` émis par le cœur (135 caractères, règle `contain-intrinsic-size` sur `img[sizes="auto"]`, mesurée bloquée — voir A-12). **Condition de déclenchement, et c'est elle qui compte : aujourd'hui l'accueil ne sert aucune image `sizes="auto"`, l'impact du §10 (« pas de sauts de mise en page perceptibles ») est donc NUL. Il cesse de l'être le jour où une chaîne ajoute une telle image.** Ce jour-là : porter la règle `contain-intrinsic-size` dans `assets/css/**` du thème **et** retirer l'émetteur du cœur depuis le module `durcissement`. Ni hachage CSP (il se périmerait silencieusement, voir A-12), ni `'unsafe-inline'`. | `dev-ux-cms`, sur la prochaine issue rouvrant `assets/css/**` |

---

## Tâches de l'issue non satisfaites — déclarées, jamais simulées

| Tâche | Verdict |
|---|---|
| « Sauvegardes **quotidiennes** » | **Non satisfaite.** `DISABLE_WP_CRON=true` : aucune planification interne ne s'exécute. Livré : moteur + rotation + planification **désarmée** + commande manuelle. La périodicité exige un déclencheur hôte (S-8). |
| « Copie hors hébergeur » | **En sommeil** par décision du propriétaire — sans objet, pas un manquement. |
| « Vérifier que HTTPS est actif partout » | **En sommeil.** HSTS écrit, inerte en HTTP. |
| En-têtes stricts sur les **assets statiques** | **Partiellement hors de portée depuis PHP** (S-2). |
| CSP sur `wp-login.php` et `wp-admin` | **Volontairement non traité** : le cœur y injecte massivement de l'inline ; une CSP stricte casserait l'administration. |
| « Restauration **testée** » | **Outillée, pas exécutée par cette chaîne.** Elle n'a pas le droit d'invoquer `test-integration-cms`. Livré : une commande à code de retour dont le vert est reproductible au niveau lot. |
