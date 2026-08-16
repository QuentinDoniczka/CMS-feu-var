# Module « durcissement »

Durcissement du site public : en-têtes de sécurité, fermeture des surfaces d'énumération de comptes,
interdiction de l'édition de code, politique de mise à jour automatique.

Le module est **invisible par construction** : il ne rend aucun HTML, n'écrit aucune option, ne crée
aucune table, n'ajoute aucun écran d'administration et ne pose aucune route REST. Il se lit par ses
trois fonctions de lecture, jamais par un écran.

---

## Fonctions de lecture

Toutes **totales** : jamais `null`, jamais `WP_Error`, toutes les clés toujours présentes.

| Fonction | Rend |
|---|---|
| `massifs_durcissement_entetes()` | `array<string,string>` — les en-têtes qui **seraient** émis pour la requête courante, dans l'ordre d'émission. Pure, sans effet de bord. |
| `massifs_durcissement_politique_mises_a_jour()` | `mineures_auto`, `majeures_auto`, `extensions_auto`, `themes_auto`, `edition_code_interdite`, `constante_posee`. |
| `massifs_durcissement_enumeration_fermee()` | `bool` — le réglage, pas l'état de la requête. |

`constante_posee` est séparée de `edition_code_interdite` **à dessein** : la première dit que
`DISALLOW_FILE_EDIT` interdit, la seconde que le filtre `map_meta_cap` interdit. Les deux mécanismes sont
indépendants et redondants ; les fondre rendrait impossible de savoir lequel tient le jour où l'autre
tombe.

---

## En-têtes, et pourquoi chacun

Émis sur `send_headers`, donc **uniquement sur le front public** : ce crochet n'est appelé que depuis
`WP::main()`, jamais dans `wp-admin`, jamais sur `wp-login.php`, jamais en REST. Le périmètre est une
propriété du crochet, pas une liste de conditions à maintenir.

| En-tête | Valeur | Condition | Pourquoi |
|---|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'` | **visiteur anonyme** | Adosse la contrainte n° 2 du projet — zéro requête navigateur vers un domaine tiers — au navigateur lui-même. Une injection de `<script src="https://…">` ne s'exécute pas, une police distante ne se charge pas. `object-src 'none'` et `base-uri 'none'` ferment deux vecteurs classiques de détournement ; `frame-ancestors 'none'` interdit l'encadrement. |
| `X-Content-Type-Options` | `nosniff` | toujours | Empêche le navigateur de deviner un type MIME : un fichier téléversé mal typé ne peut pas être réinterprété en script. |
| `Referrer-Policy` | `no-referrer` | toujours | Le site n'a rien à dire à personne sur la page d'où vient un visiteur. Cohérent avec le §9 (zéro donnée personnelle). |
| `X-Frame-Options` | `DENY` | toujours | Doublon historique de `frame-ancestors`, honoré par des navigateurs et des proxys qui ignorent la CSP. |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=(), usb=()` | toujours | Le site ne demande jamais la position réelle du visiteur : la carte est centrée sur le département. Un script injecté ne le pourrait pas non plus. |
| `Strict-Transport-Security` | `max-age=15552000; includeSubDomains` | **`is_ssl()` seulement** | Écrit une fois, inerte en HTTP : il s'arme seul à la publication et **ne ment jamais en local**. |

**`preload` n'est jamais ajouté à HSTS** : l'inscription à la liste de préchargement est irréversible à
l'échelle du navigateur et ne se décide pas depuis un module d'extension.

### Deux en-têtes délibérément absents

- **`Cross-Origin-Resource-Policy`** — fermerait les données ouvertes du §5.4 à toute origine.
- **`X-Robots-Tag`** et toute directive d'indexation — propriété de `seo-meta.php`, chaîne sœur #18.

### La CSP est réservée à l'anonyme, et ce n'est pas un oubli

Le **cœur de WordPress** émet deux blocs `<style>` en ligne sur le front dès qu'une session est ouverte
(`wp_admin_bar_header`, `_admin_bar_bump_cb`), et la présence de la barre d'administration est gelée par
le contrat #25. Trois issues, une seule tenable :

- `'unsafe-inline'` dans `style-src` — annulerait l'intérêt de l'en-tête ;
- les hachages des blocs du cœur — se cassent d'une version de WordPress à l'autre ;
- **CSP à l'anonyme seulement** — retenu. C'est 100 % du public réel.

**Affaiblissement borné et assumé** : un administrateur qui navigue sur le front ne reçoit pas de CSP.

### Ce que la CSP impose au thème

Contraintes opposables, à respecter dans toute page publique :

1. Aucun `<script>` en ligne exécutable, aucun bloc `<style>`, aucun `cssText`, aucun
   `setAttribute('style')`, aucune URL `data:`.
2. Un îlot `<script type="application/json">` reste permis : c'est un *data block*, écarté du contrôle
   CSP avant évaluation.
3. **Jamais** `'unsafe-inline'` dans `script-src` pour faire passer un besoin ponctuel. Le repli légitime
   est le hachage, ou le déplacement de la donnée dans un attribut `data-`.

### Recette : basculer la CSP en observation

```php
add_filter( 'massifs_durcissement_csp_mode', static fn (): string => 'report-only' );
```

Bascule le **nom** de l'en-tête vers `Content-Security-Policy-Report-Only` : le navigateur signale sans
bloquer. Aucune autre valeur que `enforce` et `report-only` n'est acceptée.

### Un `<style>` du cœur est bloqué, et c'est assumé

Mesuré dans Chrome sur l'accueil public. Le **cœur** de WordPress émet
`<style id="wp-img-auto-sizes-contain-inline-css">` — 135 caractères, une règle `contain-intrinsic-size`
sur `img[sizes="auto"]`. `style-src 'self'` le refuse.

```
{ id: "wp-img-auto-sizes-contain-inline-css", chars: 135, sheet: false, rules: null }
```

**`style-src-attr 'unsafe-inline'` couvre les attributs `style=`, jamais les balises `<style>`.** C'est
la confusion qui fera « corriger » la politique un jour.

Deux voies ont été **écartées explicitement** :

- **`'unsafe-inline'` sur `style-src`** — rouvrirait toute la surface des styles en ligne du seul
  document public du site, pour 135 caractères de confort. Mauvais échange.
- **Hachage CSP** — les en-têtes partent **avant** le corps, le hachage devrait donc être écrit en dur ;
  il se périmerait **silencieusement** à la première mise à jour mineure du cœur, que notre propre
  `allow_minor_auto_core_updates` → `true` active. Un correctif qui s'auto-annule sans bruit est pire que
  pas de correctif.

**Impact réel nul aujourd'hui** : l'accueil ne sert aucune image `sizes="auto"`.

**Condition de déclenchement, à surveiller** : le jour où une chaîne ajoute une image `sizes="auto"` à
une page publique, la règle passe dans la feuille du thème et l'émetteur du cœur est retiré — couture
**S-11** du contrat #16.

### Revérifier cette CSP : méthode imposée

> **Chrome n'émet AUCUNE violation en console alors que la feuille est refusée.** Capture armée, page
> rechargée, filtre large : zéro `Refused to apply inline style`. **Quiconque revérifie cette CSP à la
> console conclura « rien n'est bloqué » et se trompera.**

Ce qui tranche, c'est l'état de l'élément `<style>` lui-même — `sheet: false` / `rules: null` :

```js
[...document.querySelectorAll('style')].map(s => ({ id: s.id, sheet: !!s.sheet, rules: s.sheet ? s.sheet.cssRules.length : null }))
```

C'est la seule méthode de vérification retenue pour cette politique. Ne pas conclure depuis la console.

### Portée, pour couper court à un faux procès d'incohérence

- **Notre CSP n'est jamais émise dans `wp-admin`** : `send_headers` ne part que de `WP::main()`. Mesuré —
  `/wp-admin/` ne porte aucun en-tête de nous. Il n'y a donc **rien à différencier** entre administration
  et public : il n'y a pas deux politiques, il y en a une, et elle ne couvre que le front.
- **`wp-login.php` porte `Content-Security-Policy: frame-ancestors 'self'` — c'est le CŒUR qui l'émet,
  pas nous.** Sans cette note, quelqu'un conclura un jour que notre politique est incohérente.
- **Le blocage de l'injection d'axe-core par Playwright dans la recette est le comportement CORRECT
  d'une CSP stricte, pas un défaut.** Il se traite par `bypassCSP` côté pilote de test, au niveau du lot,
  **jamais** en assouplissant la politique du site.

---

## Énumération de comptes

Quatre surfaces publient l'identifiant de connexion d'un gestionnaire. Toutes fermées, toutes
conditionnées à l'anonymat **sauf le plan de site**.

| Surface | Anonyme | En session |
|---|---|---|
| `GET /wp-json/wp/v2/users` et `/wp/v2/users/<id>` | 404 `rest_no_route` | **200, inchangé** |
| `GET /wp-json/wp/v2/users/me` | 401, **non touché** | inchangé |
| `?author=N` | 404, **sans redirection** | 404 |
| `/author/<slug>/` | 404 | 404 |
| `wp-sitemap-users-*.xml` | absent | absent |
| oEmbed | sans `author_name` ni `author_url` | inchangé |

Trois points qui ne se devinent pas à la lecture du résultat :

- **Les deux routes REST sont retirées par leur littéral exact.** Un filtrage par préfixe emporterait
  `/wp/v2/users/me`, dont l'éditeur de blocs dépend.
- **La fuite de `?author=N` était dans l'en-tête `Location`**, pas dans le corps : `redirect_canonical`
  émettait un 301 vers `/author/<identifiant-de-connexion>/`. D'où l'intervention en priorité 1 sur
  `parse_request`, avant lui. Un test qui ne lit que le HTML final ne verrait rien.
- **Le plan de site est traité inconditionnellement.** Les fournisseurs s'enregistrent sur `init` :
  conditionner produirait un document public dont la composition dépend de la session de l'appelant, et
  qui serait mis en cache dans cet état.

**Portée exacte du coupe-circuit `massifs_durcissement_fermer_enumeration`** — elle n'est pas celle
qu'on suppose. Il couvre les **quatre surfaces conditionnées à l'anonyme**, et elles seules : routes REST
`wp/v2/users`, `?author=N`, archive d'auteur, retrait de `author_name`/`author_url` d'une réponse oEmbed.
**Deux gestes lui échappent**, tous deux inconditionnels par conception :

| Geste hors coupe-circuit | Pourquoi |
|---|---|
| Retrait du fournisseur de plan de site `users` | Une composition qui varierait selon la session de l'appelant serait un défaut neuf, pas un durcissement (A-4). |
| Retrait de la découverte oEmbed du `<head>` | Posé à l'amorce, où la session n'est pas résolue et où lire un réglage n'aurait pas de sens. Décision de sécurité autonome, du même registre que le `rsd_link` du module `auth`. |

Conséquence pratique : poser le réglage à `false` pour diagnostiquer un problème **ne ramènera ni le
fournisseur de plan de site, ni l'annonce de découverte oEmbed**. Ne pas chercher la cause ailleurs.

**Reste ouvert, et ce n'est pas un manquement** : le flux RSS expose `<dc:creator>` avec le **nom
affiché**, jamais l'identifiant de connexion.

**Jamais `rest_authentication_errors`**, ni aucun filtre global d'authentification REST, pour obtenir ce
résultat : il court-circuite `WP_REST_Server::dispatch` pour toute l'API et renverrait 401 sur
`GET /massifs/v1/statuts`, cassant les données ouvertes du §5.4 et la carte publique.

---

## Édition de code

Deux mécanismes indépendants, et il faut les deux :

1. **`DISALLOW_FILE_EDIT`**, définie dans `constantes.php` sous garde `defined()`. Son **domicile
   durable est `wp-config.php`**, hors empreinte de cette chaîne : le `define()` du module est le
   **doublon défensif** qui tient en attendant, et la garde lui interdit d'écraser une valeur déjà
   décidée en amont — y compris un `false` délibéré d'exploitant.
2. **Le filtre `map_meta_cap`**, qui retire `edit_files`, `edit_plugins` et `edit_themes`. Il reste vrai
   le jour où la constante est définie à `false` ailleurs.

`massifs_durcissement_politique_mises_a_jour()` les rapporte **séparément**.

### `DISALLOW_FILE_MODS` est délibérément absent

Il paraît être le durcissement suivant, il ne l'est pas : il désactive `WP_Automatic_Updater` **en
entier** et tuerait les mises à jour mineures automatiques exigées par la même issue. Contradiction
interne, tranchée : non posé. `DISALLOW_FILE_EDIT` suffit à l'objectif énoncé.

`automatic_updater_disabled` n'est pas posé non plus : le forcer écraserait le coupe-circuit d'un
exploitant.

---

## Politique de mise à jour

| Cible | Automatique | Pourquoi |
|---|---|---|
| Cœur, mineures | **oui** | Ce sont les correctifs de sécurité. |
| Cœur, majeures | **non** | Change le cœur sous un thème sur mesure et une extension qui greffe `map_meta_cap`, `authenticate`, `rest_endpoints` et le rendu de l'écran des comptes. |
| Extensions | **non** | Une seule extension, sur mesure et **non distribuée** : aucune source d'où une mise à jour pourrait venir, et le scénario du « slug volé » resterait ouvert. |
| Thèmes | **non** | Même raison. |
| Courriel de rapport | **oui, toujours** | Sans lui, l'échec d'une mise à jour mineure est **silencieux**. Le courriel de succès est le battement de cœur qui rend son absence significative. |

### Procédure de mise à jour majeure du cœur

Elle est manuelle, et elle se fait dans cet ordre. Chaque étape existe parce que l'étape suivante peut
échouer.

1. **Sauvegarder**, et vérifier que la sauvegarde existe :
   ```
   wp massifs sauvegarde creer
   wp massifs sauvegarde lister
   ```
   Une archive marquée `complet:false` n'est pas une sauvegarde : reprendre avant d'aller plus loin.
2. **Constater la version disponible**, sans rien appliquer :
   ```
   wp core check-update
   ```
3. **Basculer d'abord sur la démonstration**, jamais sur la production, et y appliquer la mise à jour.
4. **Mettre à jour** :
   ```
   wp core update
   wp core update-db
   ```
5. **Recette sur la démonstration**, dans cet ordre : la carte publique et la liste textuelle sans
   JavaScript, la connexion au portail avec second facteur, une publication de statut, l'export CSV de
   l'historique, puis les en-têtes (`curl -I`) et les quatre surfaces d'énumération.
6. **Production** seulement après recette verte, sauvegarde fraîche à la main, puis mêmes commandes et
   même recette.

En cas de régression : restaurer l'archive de l'étape 1 (voir le README du module `sauvegardes`).

*Domicile durable de cette procédure : `docs/`. Elle est logée ici en attendant que la chaîne
documentation la reprenne (couture S-5 du contrat #16).*

---

## Ce que ce module ne fait pas

- **Aucun en-tête sur les ressources servies directement par Apache** (CSS, polices, tuiles) : elles ne
  traversent pas PHP, `send_headers` ne s'exécute pas pour elles. Relève de la configuration serveur.
- **Aucun en-tête sur les réponses REST** : `send_headers` ne s'y déclenche pas. Le cœur y pose `nosniff`
  de lui-même. Ne pas tenter de rattraper cela par un filtre global d'authentification REST.
- **Aucun en-tête sur `wp-admin` ni `wp-login.php`** : le cœur y injecte massivement de l'inline, une CSP
  stricte y casserait l'administration. Non traité, délibérément.
- **Aucune protection au niveau HTTP de `xmlrpc.php`** : le module `auth` le vide de ses méthodes, mais
  le fichier continue de répondre. Relève de la configuration serveur.
- **Aucune vérification que HTTPS est actif** : HSTS est écrit et reste inerte tant que `is_ssl()` est
  faux.

**Le `.htaccess` de ce répertoire n'est pas une preuve de protection** : `AllowOverride` n'a pas été
mesuré sur l'image `wordpress:php8.3-apache`, et il peut n'y avoir aucun effet. La protection effective
vient aujourd'hui de `docker/wordpress/plugins-guard.conf`. Le fichier est là pour l'hébergement de
production, où il en faut un.

---

## Filtres

Tous résolus dans `Politique`, et **nulle part ailleurs**. Une valeur de type inattendu retombe sur le
défaut — jamais sur une valeur à demi valide.

| Filtre | Défaut | Effet |
|---|---|---|
| `massifs_durcissement_entetes` | carte composée | Dernier mot sur la carte complète avant émission. Le résultat est revalidé : nom non conforme ou valeur porteuse d'un caractère de contrôle écartée, jamais corrigée. |
| `massifs_durcissement_csp` | politique ci-dessus | Contenu de la CSP. |
| `massifs_durcissement_csp_mode` | `enforce` | `enforce` ou `report-only`. |
| `massifs_durcissement_csp_anonyme_seulement` | `true` | Émettre la CSP en session aussi ? |
| `massifs_durcissement_hsts_actif` | `is_ssl()` | Émettre HSTS ? |
| `massifs_durcissement_hsts_max_age` | `15552000` | Durée de vie HSTS, en secondes. |
| `massifs_durcissement_fermer_enumeration` | `true` | Coupe-circuit des **quatre surfaces conditionnées à l'anonyme** seulement. Ne couvre ni le fournisseur de plan de site, ni la découverte oEmbed — voir le chapitre « Énumération de comptes ». |
| `massifs_durcissement_interdire_edition_code` | `true` | Coupe-circuit du filtre `map_meta_cap`. |
| `massifs_durcissement_mises_a_jour_mineures` | `true` | Cœur, mineures. |
| `massifs_durcissement_mises_a_jour_majeures` | `false` | Cœur, majeures. |
| `massifs_durcissement_mises_a_jour_extensions` | `false` | Extensions. |
| `massifs_durcissement_mises_a_jour_themes` | `false` | Thèmes. |

Toute valeur d'en-tête traverse un nettoyage des caractères de contrôle avant `header()` : un filtre mal
écrit ne doit pas pouvoir injecter un second en-tête.
