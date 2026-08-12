# Contrat d'interface — Issue #5 — Squelette du thème sur mesure et gabarit de la page d'accueil

**Gelé le 12 août 2026** par `lead-issue-cms` (chaîne #5). Liant à partir de ce point.

Cette issue **ne touche aucun fichier de l'extension**. `leaddev-back-cms` n'a donc pas été lancé.
Le contrat porte sur trois frontières :

1. thème #5 → **chaîne #6** (`templates/parts/**`) — la seule couture parallèle réelle ;
2. thème #5 → **chaîne #4** (`assets/css/tokens.css`, `assets/fonts/**`) — jetons et handles ;
3. thème #5 → **extension `massifs-core`** — consommation en lecture seule de l'API gelée du contrat #3.

## Empreinte d'écriture — exhaustive

```
wp-content/themes/massifs/style.css
wp-content/themes/massifs/functions.php
wp-content/themes/massifs/templates/header.php
wp-content/themes/massifs/templates/footer.php
wp-content/themes/massifs/front-page.php
wp-content/themes/massifs/assets/css/layout.css
```

Rien d'autre. `index.php`, `assets/css/tokens.css`, `assets/fonts/**`, `templates/parts/**` et toute
l'extension sont **hors empreinte** et ne sont ni créés, ni modifiés, ni déplacés.

---

## Fonctions de lecture exposées par l'extension — consommées par le thème

Signatures **vérifiées dans le code**, pas seulement dans le contrat #3.

```php
massifs_codes(): array                                       // list<string>, 25 codes, déjà triés
massifs_jour_courant(): string                               // 'YYYY-MM-DD', jour civil Paris
massifs_synthese_du_jour( array $codes, ?string $jour = null ): array
massifs_fraicheur( ?string $jour = null ): array
massifs_saison( ?string $jour = null ): array
massifs_horodatage( string $instant_iso_utc ): array         // EXIGE un instant, pas une date nue
massifs_attribution_statuts(): array
massifs_attribution(): array
```

Clés consommées, et **aucune autre** :

| Appel | Clés lues |
|---|---|
| `massifs_synthese_du_jour( massifs_codes(), null )` | `etat_global` · `total` · `par_niveau['autorise']` · `jour_validite` |
| `massifs_fraicheur( null )` | `perimee` · `publie_prefecture_le` · `dernier_releve_le` · `evalue_le` · `jour_validite` |
| `massifs_saison( null )` | `prochaine_ouverture` |
| `massifs_horodatage( $instant )` | `date_longue` · `heure` · `attr_datetime` |
| `massifs_attribution_statuts()` | `texte` · `carte_officielle_url` |
| `massifs_attribution()` | `phrase` |

**Dépendance déclarée** : la phrase de synthèse de l'accueil est liée à l'existence de la clé de niveau
`autorise` dans `par_niveau`. Un changement de légende qui la supprimerait casse la phrase — bruyamment,
ce qui est le comportement voulu.

**Accès direct, jamais `isset()`, jamais `??`** sur les tableaux du contrat : une clé absente est une
rupture de contrat qui doit produire un avertissement PHP visible, pas un `0` silencieux.

### Garde d'existence de l'API — un seul point

```php
$api = function_exists( 'massifs_codes' )
    && function_exists( 'massifs_jour_courant' )
    && function_exists( 'massifs_synthese_du_jour' )
    && function_exists( 'massifs_fraicheur' )
    && function_exists( 'massifs_horodatage' )
    && function_exists( 'massifs_attribution_statuts' );
```

La garde porte sur **six** fonctions parce qu'elles proviennent de **trois modules de domaine distincts**
(`domain/statuts`, `domain/fraicheur`, `domain/massifs`) qui peuvent échouer à charger indépendamment.
`$api === false` → l'ardoise rend la branche `indisponible`, **sans le lien** (l'URL vient du serveur,
qui est absent), et journalise sous `WP_DEBUG`. Aucune copie inventée, le `h1` unique est conservé.

## Routes REST

**Aucune.** L'issue #5 n'expose, ne consomme et ne déclare aucune route REST.

---

## États spéciaux

| État | Émis par le serveur | Rendu par le thème (#5) |
|---|---|---|
| `disponible` | `synthese['etat_global']` | chiffre `par_niveau['autorise']` + `/total`, `h1` de synthèse, ligne de fraîcheur |
| `information_indisponible` (`indisponible`) | `synthese['etat_global']` | `h1` « Information du jour non disponible. Consultez la carte officielle de la préfecture. » + lien `carte_officielle_url`. **Jamais de chiffre.** |
| `hors_saison` | `synthese['etat_global']` | `h1` **tronqué à sa première proposition** : « Dispositif estival inactif. » — voir arbitrage A-1 |
| `non_encore_publie` | `synthese['etat_global']` | `h1` « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. » Bras inatteignable aujourd'hui, écrit quand même (`match` sans `default`) |
| `donnee_perimee` | `fraicheur['perimee'] === true` | phrase « Donnée périmée. » **ajoutée** sous la ligne de fraîcheur. Ne masque, ne remplace, ne conditionne **rien** |
| `referentiel_indisponible` | `massifs_codes() === []` | `total = 0` et `etat_global = 'indisponible'` → branche indisponible, sans cas particulier |
| `couche_effis_indisponible` | — | **hors périmètre** : aucune couche EFFIS n'existe |
| API absente | garde `function_exists` | branche indisponible, sans lien |

### Le `match()` — sans bras `default`

```php
match ( $synthese['etat_global'] ) {
    'disponible'        => …,
    'indisponible'      => …,
    'hors_saison'       => …,
    'non_encore_publie' => …,
}
```

Imposé par le contrat #3. Un cinquième état lève `UnhandledMatchError` sur l'accueil public : c'est
délibéré. Sur une donnée de sécurité, l'échec bruyant vaut mieux qu'un rendu silencieusement faux, et un
cinquième état ne peut apparaître que par une modification de l'extension **dans ce même dépôt**.

**Le chiffre n'est écrit que dans le bras `disponible`**, et ce bras produit à la fois le chiffre et sa
phrase. La règle « jamais un statut périmé présenté comme courant » est donc tenue structurellement,
pas par vigilance.

---

## Chaînes fournies par le serveur

Rendues telles quelles, **jamais composées ni reformatées par le thème** :
`massifs_horodatage()['date_longue' | 'heure']` · `massifs_attribution()['phrase']` ·
`massifs_attribution_statuts()['texte' | 'carte_officielle_url']`.

Chaînes fixes reprises **mot pour mot** de `design-system/MASTER.md` §11.3 / §11.4 (la rédaction
éditoriale du thème lui appartient par le contrat #3) : les quatre phrases hors niveau, le gabarit de la
phrase de fraîcheur, `Légende de la carte` (verbatim officiel), `La liste du jour`,
« Aujourd'hui, {X} massifs sur {Y} sont d'accès autorisé. », et les deux libellés de lien d'évitement
« Aller au contenu » / « Aller à la liste des statuts ».

**Apostrophes** : U+2019 pour toute prose rédigée par le thème ; les chaînes officielles de §11.4 sont
reproduites **octet pour octet**, `Niveau d'Accès` en U+0027 et `Zones d’Accueil` en U+2019 compris.

### La ligne de fraîcheur — variantes par omission seule

Gabarit §11.3. Les variantes sont produites **uniquement en supprimant la proposition dont la valeur
serveur est `null`**, jamais en réécrivant des mots :

| Condition | Rendu |
|---|---|
| `publie_prefecture_le !== null` et `dernier_releve_le !== null` | phrase complète |
| `publie_prefecture_le === null` | « Statuts du {date} — relevés sur ce site le {date} à {heure}. » |
| `dernier_releve_le === null` | « Statuts du {date}. » |

Les instants (`publie_prefecture_le`, `dernier_releve_le`) sont des ISO valides et passent par
`massifs_horodatage()`. Les dates s'affichent dans un `<time datetime="…">` alimenté par `attr_datetime`.

---

## Interdits

- Le thème n'appelle **jamais** une source externe, ni une fonction d'ingestion, ni une classe `Massifs\`,
  ni `$wpdb`.
- Le thème ne calcule **jamais** une règle métier : saison, péremption, sévérité, formatage de niveau.
- Le thème ne calcule **jamais** « aujourd'hui » ou « demain » : `date()`, `current_time()`, `time()`,
  `strtotime()`, `wp_date()` sont interdits pour cet usage. Seul `massifs_jour_courant()` fait foi.
- Le thème ne **formate jamais** une date hors `massifs_horodatage()`.
- Le thème ne fabrique **jamais** un libellé de niveau, une consigne, une couleur ou une sévérité.
- **Aucune valeur hexadécimale de statut, aucune custom property, hors `assets/css/tokens.css`.**
  `layout.css` en **consomme**, n'en **définit aucune**, et ne contient aucun littéral de couleur,
  d'espacement ou de durée.
- **Aucun jeton n'est créé.** Un jeton nécessaire absent du §12 de `MASTER.md` est **signalé**, jamais
  inventé.
- L'extension n'émet **aucun** HTML de présentation publique — inchangé, aucune ligne d'extension écrite.
- **Aucune requête navigateur vers un domaine tiers** : aucun CDN, aucune police distante, aucun script
  externe, aucun `dns-prefetch` vers `s.w.org`.
- **Aucun JavaScript n'est enfilé par l'issue #5.** L'accueil est identique avec et sans JS.
- **Aucun cookie**, aucune collecte, aucun traceur.
- `get_header()` et `get_footer()` sont **bannis dans ce thème** (l'inclusion passe par
  `get_template_part( 'templates/header' )` / `('templates/footer')`).
- **Aucun `theme.json`**, aucun constructeur de pages, aucun framework CSS générique.
- **Aucun texte de repli visible par le visiteur** quand une partie de la chaîne #6 est absente.
- `add_theme_support( 'title-tag' )` n'est **pas** déclaré (collision avec le `<title>` en dur de
  `index.php`, hors empreinte).

---

## Frontière #5 ↔ chaîne #6 — la couture parallèle

### Les quatre slugs et qui les appelle

| Slug | Fichier attendu | Appelé par | Emplacement |
|---|---|---|---|
| `bandeau-non-officialite` | `templates/parts/bandeau-non-officialite.php` | **`front-page.php` (#5)** | bande `#non-officialite` > `.bande__contenu` |
| `legende` | `templates/parts/legende.php` | **`front-page.php` (#5)** | bande `#legende` > `.bande__contenu`, **après** le `h2` |
| `liste-statuts` | `templates/parts/liste-statuts.php` | **`front-page.php` (#5)** | bande `#liste` > `.bande__contenu`, **après** le `h2` |
| `etats-vides` | `templates/parts/etats-vides.php` | **`liste-statuts.php` (#6)** — **jamais par #5** | à l'intérieur du rendu de #6 |

### Convention d'appel — figée

```php
massifs_partie( 'liste-statuts' );   // helper de functions.php
```

- Le helper appelle `get_template_part( 'templates/parts/' . $slug )` et exploite sa **valeur de retour**
  (`false` quand rien n'a été chargé — disponible depuis WP 5.5, notre `style.css` exige 6.4).
- Partie absente → **commentaire HTML** `<!-- massifs: partie « <slug> » absente -->` + `error_log()` sous
  `WP_DEBUG` uniquement. **Jamais** de texte visible par le visiteur : inventer « liste indisponible »
  serait de la copie d'interface inventée (`MASTER.md` §16) **et** un mensonge — la donnée n'est pas
  indisponible, c'est un fichier de gabarit qui manque.
- **Aucun `$args` n'est passé.** Les parties appellent elles-mêmes l'API publique gelée. La surface de
  couture entre deux chaînes qui ne se parlent pas se réduit ainsi à **quatre slugs et sept identifiants**.
  Coût réel : une requête SQL préparée supplémentaire par page, hors cache. Négligeable.

> **RÉVISION 3 — 12 août 2026. La chaîne #6 a livré des parties auto-portantes : #5 lui cède
> l'enveloppe sémantique.** Constaté dans le code, pas supposé : `templates/parts/legende.php`
> (l. 184-185) et `templates/parts/liste-statuts.php` (l. 214-215) émettent **chacune leur propre
> `<section id="…" aria-labelledby="…">` et leur propre titre**, avec `$ancre` valant par défaut
> `legende` et `liste` — exactement mes identifiants de bande. Rendu mesuré : **`id="legende"` et
> `id="liste"` en double, deux `h2` en double, sections imbriquées.** HTML invalide et cible du 2ᵉ
> lien d'évitement ambiguë : **défaut bloquant**.
>
> **Arbitrage A-23 — c'est #5 qui cède**, pour trois raisons : `templates/parts/**` est **hors de
> mon empreinte** et je ne peux physiquement pas le corriger ; les parties de #6 sont conçues
> auto-portantes (elles acceptent `$args['ancre']` et un niveau de titre paramétrable) ; et leurs
> titres portent déjà `class="repere"`, conforme à `MASTER.md` §3.2 point 3.
>
> **Conséquence sur `front-page.php`** — pour les **deux seules** bandes `legende` et `liste` :
> l'enveloppe devient un `<div class="bande bande--legende">` / `<div class="bande bande--liste">`
> **purement de mise en page**, sans `id`, sans `aria-labelledby`, sans `tabindex`, **et sans `h2`**.
> La `<section>` sémantique, l'`id`, le nom accessible, le `tabindex="-1"` et le titre appartiennent
> désormais à la partie. **Aucun `$args` n'est passé** : les valeurs par défaut des parties
> produisent déjà `id="legende"` et `id="liste"`.
>
> Les bandes `ardoise`, `non-officialite` et `carte` sont **inchangées** :
> `bandeau-non-officialite.php` et `etats-vides.php` n'émettent qu'un `<div>`, sans `id` — aucune
> collision. **Le lien d'évitement `#liste` continue de résoudre**, la partie fournissant l'ancre
> avec son `tabindex="-1"`. Couplage accepté et documenté : si `liste-statuts.php` disparaissait,
> `#liste` disparaîtrait avec elle.
>
> **A-6 est vérifié honoré dans le code** : `liste-statuts.php` (l. 418) appelle bien
> `etats-vides` lui-même. Le principal risque de réconciliation du lot ne s'est pas matérialisé.

### Ce que la chaîne #6 peut tenir pour acquis

- Les trois parties appelées par #5 le sont **dans l'ordre des bandes**, chacune **à l'intérieur** d'un
  `.bande__contenu` déjà positionné et gouttièré.
- Les parties émettent **le contenu intérieur seulement** : ni `<section>`, ni `h1`, ni `h2`, ni
  gouttière, ni largeur maximale. Les enveloppes, les `id` et les `h2` appartiennent à #5.
- Les parties peuvent employer `h3` et au-delà. Elles n'émettent **jamais** de `h1` ni de `h2`.

### Identifiants d'ancrage garantis par #5

| `id` | Élément | Garantie |
|---|---|---|
| `contenu-principal` | `<main>` | `tabindex="-1"`, cible du 1ᵉʳ lien d'évitement |
| `ardoise` | bande | contient le `h1` `#titre-du-jour` |
| `non-officialite` | bande | — |
| `carte` | bande | **plein cadre**, aucune hauteur imposée |
| `legende` | bande | `aria-labelledby="titre-legende"` |
| `liste` | bande | `tabindex="-1"`, `aria-labelledby="titre-liste"`, cible du 2ᵉ lien d'évitement |
| `titre-du-jour` / `titre-legende` / `titre-liste` | `h1` / `h2` / `h2` | fournis par #5 |

**Classes de structure garanties** : `.bande`, `.bande__contenu`, `.sur-sombre`, `.repere`,
`.repere--bloc`, `.lien-evitement`.

### Plan de titres de l'accueil — un seul `h1`

```
h1  Aujourd'hui, {X} massifs sur {Y} sont d'accès autorisé.   (ardoise, id=titre-du-jour)
h2  Légende de la carte                                       (id=titre-legende)
h2  La liste du jour                                          (id=titre-liste)
```

Le nom du site dans la barre est un `<p>`, **jamais un `h1`**. Les titres sont écrits en **casse normale**
dans le HTML et mis en capitales par `text-transform` en CSS : plusieurs lecteurs d'écran épellent un mot
écrit tout en capitales.

---

## Frontière #5 ↔ chaîne #4 — jetons, handles, polices

> **RÉVISION 2 — 12 août 2026.** La chaîne #4 a livré pendant l'implémentation. Elle apporte
> `assets/css/tokens.css`, les deux `.woff2` **et `assets/fonts/fonts.css`**. Son contrat
> (`docs/contracts/issue-4.md` §« Dépendances rapportées — pour la chaîne #5 ») place des
> obligations nouvelles sur **mon `functions.php`**. Elles sont intégrées ci-dessous et
> **supersèdent l'arbitrage A-14**.

| Handle | Fichier | Dépendances |
|---|---|---|
| `massifs-fonts` | `assets/fonts/fonts.css` (**chaîne #4**) | aucune |
| `massifs-tokens` | `assets/css/tokens.css` (**chaîne #4**) | aucune |
| `massifs-layout` | `assets/css/layout.css` (**#5**) | `array( 'massifs-tokens' )` |

**`style.css` n'est PAS enfilé**, malgré la ligne `massifs-style` du tableau de la chaîne #4 :
ce fichier ne porte **aucune règle CSS** (il est la feuille d'identité du thème). Enfiler une
feuille vide coûterait une requête HTTP pour zéro effet. La règle universelle de la chaîne #4
(« toute autre feuille du thème → `['massifs-tokens']` obligatoire ») est honorée par
`massifs-layout`, qui est la seule autre feuille de `assets/css/`.

**Preload obligatoire des deux `.woff2`** dans `wp_head` (priorité basse, **avant**
`wp_print_styles`), avec `as="font"`, `type="font/woff2"` et **`crossorigin`** — obligatoire
**même en même origine** : l'omettre provoque un double téléchargement. `fonts.css` emploie
`font-display: optional` (décision D-22 de la chaîne #4) ; **sans le preload, ce choix perd tout
son intérêt**. Les deux fichiers sont `assets/fonts/big-shoulders-display-var.woff2` et
`assets/fonts/atkinson-hyperlegible-next-var.woff2`, servis depuis notre domaine — la contrainte
« zéro requête tierce » reste tenue.

**Le piège, et sa résolution.** `WP_Dependencies::all_deps()` retire **silencieusement** un handle dont
une dépendance n'est pas *enregistrée* : si `massifs-tokens` n'était pas enregistré, `massifs-layout`
serait éliminé sans erreur et la page perdrait **tout** son CSS. Les deux handles sont donc **toujours
enregistrés**, y compris quand `tokens.css` est physiquement absent — mais avec `$src = false` quand le
fichier n'existe pas, ce qui produit un **handle-alias** : aucune balise imprimée, **aucune 404**, et la
dépendance se résout. `tokens.css` est chargé dès qu'il apparaît sur disque, sans modification de code.

**Versionnage** : helper `massifs_version_asset()` — `is_readable()` **avant** tout `filemtime()`
(`filemtime()` sur fichier absent émet un `E_WARNING` et retourne `false`), repli sur la version du thème.
Ne retourne jamais `false` ni `null` : un `$ver` faux imprimerait l'URL sans cache-busting.

### Jetons consommés par `layout.css` — 49, exhaustif, aucun créé

**Couleurs (8)** `--c-calcaire` · `--c-calcaire-ombre` · `--c-charbon` · `--c-charbon-doux` ·
`--c-trace` · `--c-mistral-nuit` · `--c-mistral` · `--c-mistral-clair`

**Bordures (1)** `--bord-fort`

**Typographie (17)** `--police-titre` · `--police-texte` · `--fs-100` · `--fs-200` · `--fs-300` ·
`--fs-500` · `--fs-600` · `--fs-700` · `--fs-800` · `--lh-corps` · `--lh-dense` · `--lh-sous` ·
`--lh-titre` · `--lh-affiche` · `--ls-titre` · `--ls-affiche` · `--mesure`

**Poids (3)** `--poids-texte` · `--poids-titre` · `--poids-affiche`

**Espacement et largeur (9)** `--esp-xs` · `--esp-s` · `--esp-m` · `--esp-l` · `--esp-xl` · `--esp-4xl` ·
`--esp-section` · `--gouttiere` · `--largeur-max`

**Signature (4)** `--repere-largeur` · `--repere-decalage-x` · `--repere-decalage-y` · `--repere-couleur`

**Focus (6)** `--focus-trait` · `--focus-trait-inverse` · `--focus-halo` · `--focus-epaisseur` ·
`--focus-ecart` · `--focus-halo-epaisseur`

**Cibles et plans (2)** `--cible-min` · `--z-evitement`

**Consommés documentairement** (leur valeur en `rem` est recopiée dans les `@media`, qui n'acceptent pas
`var()` — `MASTER.md` §12 le prévoit) : `--bp-s` (37.5rem) · `--bp-m` (56.25rem). `--bp-l` n'a pas de
requête média : `max-inline-size: var(--largeur-max)` produit le même effet.

### Le plein cadre de la carte, sans `--sortie-cadre`

`MASTER.md` §6.1 nomme `--sortie-cadre` mais **ce jeton est absent de la liste normative du §12**. Il
n'est **pas créé** et il n'est **pas nécessaire** : c'est la **bande** qui est pleine largeur et le
**contenu** qui est bridé, jamais l'inverse.

```css
.bande          { inline-size: 100%; }
.bande__contenu { max-inline-size: var(--largeur-max); margin-inline: auto;
                  padding-inline: var(--gouttiere); padding-block: var(--esp-section); }
.bande--carte   { /* n'émet PAS de .bande__contenu : la carte touche les deux bords */ }
```

Aucune marge négative, aucun jeton inexistant, vrai à toutes les tailles, incassable par un futur
`overflow: hidden` sur un ancêtre.

---

## Arbitrages

Décisions du lead. Chacune tranche un désaccord, une ambiguïté ou un trou constaté.

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-1** | `massifs_horodatage()` **refuse une date nue** (`Horloge::instant_depuis_chaine()`, motif ligne 246, partie horaire obligatoire — **vérifié dans le code**). Deux phrases de §11.3 en dépendent | **Ligne de fraîcheur** : passerelle bornée — `massifs_fraicheur()['evalue_le']` est un instant serveur réel, employé **uniquement** sous la garde `fraicheur['jour_validite'] === synthese['jour_validite'] && synthese['jour_validite'] === massifs_jour_courant()`. Garde tombée → la proposition « Statuts du {date} » est **omise**, jamais inventée. **Branche `hors_saison`** : aucune passerelle possible → la phrase est **tronquée à « Dispositif estival inactif. »**, « Reprise le {date}. » est **omise** et journalisée | Passer un instant serveur au formateur du serveur n'est pas composer une date ; la garde ne compare que des valeurs serveur, sans arithmétique. Afficher un `2027-06-01` brut serait le thème choisissant un format. **Omettre, jamais inventer.** Demande ferme **B-1** portée au back |
| **A-2** | Trois variantes de la phrase de fraîcheur, absentes de §11.3 | Approuvées, avec la **règle d'omission seule** : on supprime la proposition dont la valeur serveur est `null`, on ne réécrit aucun mot | Mécanique, pas éditorial. À confirmer par `lead-design-cms` |
| **A-3** | Ligne « zéro cookie » du pied de page | **Non écrite.** Le pied porte l'**emplacement** des mentions légales (menu `pied`), pas la copie | La case 2 de l'issue demande un *emplacement*, pas un texte. Cette phrase est de la copie éditoriale sans propriétaire ; elle appartient à la page « Mentions légales » / à la chaîne `contenu`. Une chaîne inventée de moins |
| **A-4** | Péremption sans son composant (`MASTER.md` §8.3 sans propriétaire) | Phrase « Donnée périmée. » **ajoutée** sous la ligne de fraîcheur. Elle ne masque, ne remplace et ne conditionne rien | Le contrat #3 (interdit 9) impose que `perimee` **ajoute**. Une phrase n'est pas la bannière du brief §4.5 : forme dégradée assumée, **signalée** |
| **A-5** | `MASTER.md` §8.2 met « INDISPONIBLE » en `--fs-700`, et §5.1 met le `h1` en `--fs-700` → **deux blocs `--fs-700` adjacents** disant la même chose | Le mot « INDISPONIBLE » **n'est pas rendu**. Le `h1` porte la phrase §11.3, l'emplacement du chiffre reste vide | Aucune information perdue — la phrase dit exactement « Information du jour non disponible ». La hiérarchie de MASTER vient de l'échelle ; deux blocs de même taille la détruisent. Divergence **signalée** à `lead-design-cms` |
| **A-6** | Qui appelle `etats-vides` ? **Principal risque de réconciliation avec #6** | **`liste-statuts.php` (#6) l'appelle. #5 ne l'appelle jamais.** | L'état vide **remplace** le tableau, il ne s'y ajoute pas. Seul le fichier qui sait s'il a imprimé un tableau peut décider de l'imprimer. Si #5 appelait les deux, il dupliquerait le `match()` de #6 dans un fichier qui ne possède pas la liste, avec deux endroits à corriger au prochain état. **À relayer à la chaîne #6 par l'orchestrateur** |
| **A-7** | Doublon apparent « information non disponible » entre l'ardoise (#5) et `etats-vides` (#6) | **Ce n'est pas un défaut.** Les deux rendus coexistent | Le brief §4.2 exige explicitement cet état **sur la carte ET dans la liste** |
| **A-8** | Amendement d'empreinte demandé par le brainstorm (`header.php`, `footer.php`, `page.php` à la racine) | **Refusé.** `get_template_part( 'templates/header' )`, et `get_header()`/`get_footer()` bannis, avec commentaire d'interdiction dans `style.css`, `functions.php` et `templates/header.php` | Renégocier une empreinte en cours de lot est exactement ce que le protocole interdit : les deux chaînes sœurs ont été briefées sur l'empreinte actuelle. Forme canonique à reprendre dans une issue ultérieure |
| **A-9** | CSS de composant (`.repere`, `.pastille`, `.jalon`, frise, motifs) sans propriétaire dans le lot | **`layout.css` implémente `.repere` et rien d'autre** de la couche composant : base, variante `--bloc`, variante `.sur-sombre`, `forced-colors: active` | Le repère est la **contrainte non négociable n° 4** (élément signature), `MASTER.md` §3.1 en donne l'implémentation **normative verbatim**, c'est du CSS pur, et aucune autre chaîne du lot ne possède de fichier CSS hors `tokens.css`. `.pastille`, `.jalon`, la frise et les motifs restent **hors périmètre et sans propriétaire** — signalés |
| **A-10** | Deux extensions de périmètre de `layout.css` soumises par le leaddev | **Approuvées** : (a) l'anneau de focus §9.1 — « focus visible partout » est bloquant (brief §8, DoD §12) et le bloc est entièrement piloté par des jetons ; (b) six déclarations typographiques pour le chiffre de l'ardoise, toutes issues de jetons | Sans (a) la page retombe sur l'anneau par défaut du navigateur ; sans (b) le chiffre s'affiche à 17 px et l'ardoise n'existe pas visuellement |
| **A-11** | `wp-block-library`, `wp-block-library-theme`, `classic-theme-styles`, `global-styles` | **Retirés** du front public, `wp_dequeue_style` à la priorité **100**, jamais `wp_deregister_style` | `global-styles` injecte un **second système de custom properties** (`--wp--preset--*`), frontalement interdit par `MASTER.md` §12 ; le CSS de blocs est un **framework CSS générique**, interdit par la contrainte n° 1. ~100 Ko retirés, soit quatre fois le poids total de notre page |
| **A-12** | Gravatar / `show_avatars` (fuite tierce en session connectée) | **Non touché par cette issue** | La violation mesurée est `s.w.org` seule. `show_avatars` est un **réglage de site**, propriété du provisionnement ou de la chaîne `securite` ; trois chaînes l'écrivant chacune de leur côté serait pire. **Signalé** |
| **A-13** | Valeurs de `MASTER.md` sans jeton au §12 : interligne `h1` 1,05 · approche `h1` 0,005em · poids `h3` 600 (**qui contredit §5.1** : « la famille de titrage n'a que deux poids en service, 700 et 800 ») · `text-underline-offset` 0,18em · hauteur de barre 48 px | **Aucun jeton créé.** Emploi des jetons voisins existants (`--lh-titre`, `--ls-titre`, `--poids-titre`) ; aucune hauteur fixe de barre (padding + plancher `--cible-min`) | Un jeton absent du §12 n'existe pas. Écarts **signalés** à `lead-design-cms` |
| **A-14** | ~~`@font-face` sans aucun propriétaire~~ — **SUPERSÉDÉ par la révision 2** | **Trou refermé par la chaîne #4** : elle a livré `assets/fonts/fonts.css` (sa décision D-21), seul fichier du thème autorisé à porter un `@font-face`. #5 n'en écrit toujours aucun, **mais enfile désormais `massifs-fonts` et émet le preload des deux `.woff2`** | `@font-face` étant insensible à la cascade, `fonts.css` n'entre en concurrence avec aucune feuille quel que soit l'ordre d'enfilement. Sans l'enfilement et le preload par mon `functions.php`, **les artefacts de la chaîne #4 ne sont chargés par rien** |
| **A-21** | `add_filter( 'emoji_svg_url', '__return_false' )` — le plan l'avait **rejeté** (crainte d'un `src=""`), la chaîne #4 le demande comme ceinture-bretelles | **Adopté** | Les `remove_action` ayant déjà retiré les scripts, plus rien ne compose l'URL : le filtre ne peut produire aucun `src=""`. Il ne reste que sa valeur d'assurance — si un jour un script émoji refuit, **aucune URL distante n'est composée**. Assurance gratuite sur une **contrainte non négociable** |
| **A-22** | `html { color-scheme: light; }` (D-23 de la chaîne #4) et `font-synthesis: none` | **Ajoutés à `layout.css`** (`dev-ux-cms`) | Sans `color-scheme: light`, un OS en thème sombre fait assombrir d'office les contrôles natifs par le navigateur, ce qui invalide les hypothèses de contraste de `--bord-champ`. Ce sont des déclarations de mise en page, sans littéral de couleur |
| **A-15** | Apostrophes de la prose rédigée par le thème | **U+2019** pour toute prose du thème ; les sept chaînes officielles de §11.4 reproduites **octet pour octet** | Norme typographique française, cohérente avec `Zones d’Accueil` ; la reproduction fidèle du §4.2 du brief l'emporte partout ailleurs |
| **A-16** | Bandes « Danger météo » et « Zones parcourues par le feu » | **Non émises.** Un commentaire PHP marque la place | Une `<section>` portant un `h2` et rien dedans est un **landmark vide**, donc un défaut d'accessibilité, pas un emplacement réservé |
| **A-17** | Bande carte émise mais vide | **Émise**, sans nom accessible (ni `h2`, ni `aria-labelledby`, ni `aria-label`) donc exposée comme `generic` et non comme landmark `region`, et **sans hauteur** | La case 3 exige l'emplacement de la carte. Sans nom accessible, aucun landmark vide n'est créé ; sans hauteur, aucun trou visible |
| **A-18** | Navigation quand aucun menu n'est affecté | `register_nav_menus` (`principal`, `pied`) + `wp_nav_menu` avec `'fallback_cb' => false`, **enveloppé dans `has_nav_menu()`** | Sans menu affecté : **aucun `<nav>` du tout**, plutôt qu'un landmark vide. `fallback_cb => false` évite `wp_page_menu()`, qui listerait « Page d'exemple » de WordPress. Aucun lien en dur vers des pages inexistantes (ce seraient des 404 dans le chrome de chaque page) |
| **A-19** | `total` vaut **25**, `MASTER.md` §7.1/§8.2 en dessinent **27** | Le thème rend `$synthese['total']`, **jamais un littéral** | Le référentiel gelé (contrat #2) contient 25 massifs. Sans effet en #5 (la frise n'est pas rendue). **Signalé** à `lead-design-cms` avant construction de la frise |
| **A-20** | Version de WordPress non épinglée (`FROM wordpress:php8.3-apache`) | La neutralisation des émoji est rendue **indifférente à la version** : les noms pré-6.4 **et** post-6.4 sont retirés ; le filtre `wp_resource_hints` **compare l'hôte**, pas l'URL exacte | Un `remove_action()` sur un couple inexistant est un no-op silencieux, donc gratuit. La recette classique par `array_diff` sur l'URL casse à chaque montée de version du jeu d'émoji et ignore la forme tableau. **Signalé** à `docker-cms` |

| **A-23** | La chaîne #6 émet ses propres `<section id>` + titres → **doublons d'`id` et de `h2`**, HTML invalide, cible d'évitement ambiguë | **#5 cède l'enveloppe sémantique** des bandes `legende` et `liste` : `<div class="bande bande--…">` de mise en page seule, sans `id`, sans `aria-labelledby`, sans `tabindex`, sans `h2` | `templates/parts/**` est hors de mon empreinte — c'est le seul côté que je peux corriger. Les parties de #6 sont auto-portantes et leurs titres portent déjà `.repere` (§3.2). Détail complet en révision 3 ci-dessus |
| **A-24** | `A-11` **incomplet face à WordPress 7.0.2** : depuis WP 6.9, `wp_enqueue_global_styles()` n'enfile plus `global-styles` sur `wp_enqueue_scripts` pour un thème classique — il pose une poignée-placeholder, enfile la vraie feuille sur `wp_footer` (prio 1), et `wp_hoist_late_printed_styles()` la remonte dans le `<head>`. Les quatre dequeues à la priorité 100 **laissaient donc passer l'intégralité des `--wp--preset--*`** (mesuré) | **Étendu** : la poignée `wp-global-styles-placeholder` est ajoutée à la liste, et le même callback est accroché **aussi** à `wp_footer` priorité 2. Après correctif, `grep -c "wp--preset"` = **0** | Sans cette extension, `MASTER.md` §12 (« aucun autre fichier ne définit de custom property ») est violé en production. **A-20 s'est matérialisé** : le tag Docker non épinglé a changé le comportement du cœur sans qu'aucun fichier du dépôt ne bouge. À signaler fermement à `docker-cms` |
| **A-25** | `A-10 (b)` (typographie du chiffre de l'ardoise) non appliqué au premier passage : les deux devs tournant en parallèle, `dev-ux-cms` ignorait les crochets de classe réellement émis | **Réappliqué** sur les crochets constatés : `.ardoise__chiffre` et `.ardoise__texte` sont **enfants directs** de `.bande__contenu.ardoise` — exactement le regroupement qui rend écrivable la grille à deux colonnes de §7.1 à `--bp-m` | Sans cela le chiffre s'affiche **à 17 px en police de labeur** et l'ardoise n'existe pas visuellement, en écart direct à §7.1 et §8.2 |

### Demandes fermes portées au back — hors lot, à ordonnancer

| # | Demande | Motif |
|---|---|---|
| **B-1** | `massifs_horodatage_jour( string $jour_ymd ): array` — mêmes clés que `massifs_horodatage()`, `heure => ''` | `massifs_horodatage()` refuse une date nue. Deux phrases fixes de §11.3 sont aujourd'hui **incomposables** sans violer l'interdit « le thème ne compose jamais une date ». Débloque A-1 |
| **B-2** | `massifs_legende()['publication_heure_libelle']` = `'17 h 00'` (espaces insécables), à côté de `publication_heure` = `'17:00'` | Le « 17 h » de §11.3 est figé dans `MASTER.md` alors que la valeur vit dans `legende.config.php`. Transformer `'17:00'` en `'17 h 00'` côté thème serait du formatage de date par le thème |

---

## Hors périmètre et **sans propriétaire** dans ce lot — à attribuer

~~`@font-face` et le chargement des deux familles~~ — **refermé par la chaîne #4** (révision 2) ·
`print.css` et `MASTER.md` §13 ·
le bandeau d'alerte §8.3 · `.pastille`, `.jalon`, la frise des 27 marques, les motifs de statut ·
l'image statique de repli sans JS · toute la carte · `page.php` / `singular.php` / `404.php` (donc
`index.php` reste le repli hors accueil, avec son `<title>` en dur → titres non uniques hors accueil) ·
`blogdescription` non provisionné (`<title>` de l'accueil portera le slogan WordPress par défaut) ·
Gravatar / `show_avatars` · `wp_generator` et le durcissement §9.
