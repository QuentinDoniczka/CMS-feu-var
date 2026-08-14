# Contrat d'interface — Issue #15 — Construire l'historique des statuts filtrable avec export CSV et journalisation

**Gelé le** 14 août 2026 · **Par** `lead-issue-cms`, chaîne #15 · Épic 5 · domaine `portail`
**Lignes de DoD servies** : §12 (*Journal exact*) · §6 du brief (*Historique*, en entier)
**Statut** : contraignant. En cas de divergence entre un plan amont et ce document, **c'est ce document
qui fait foi**.

> Règle de lecture reprise des contrats #8, #9 et #12 : ce document décrit des **décisions**, pas des
> suggestions. Une divergence constatée en revue est un défaut, pas une variante. Les points marqués
> **`OUVERT`** sont des trous de connaissance assumés — on ne les comble **jamais** par déduction
> (§4.2 du brief).

**Particularité de cette issue** : le thème n'est **pas** touché. Il n'y a donc pas eu de plan
`leaddev-front-cms`, et ce contrat n'est pas une réconciliation front/back. Il réconcilie trois autres
choses, qui sont les vrais points de rupture ici :
1. l'**écran**, la **route REST** et l'**export CSV**, qui doivent partager un seul analyseur de filtres ;
2. le **balisage** (écrit par `dev-back-cms`) et le **CSS** (écrit par `dev-ux-cms`), qui travaillent en
   aveugle l'un de l'autre ;
3. les **dépendances de lot** envers les chaînes sœurs **#13** et **#14**, invisibles depuis ici.

**Empreinte fichiers de l'issue**, hors de laquelle cette chaîne n'écrit **rien** :

```
wp-content/plugins/massifs-core/includes/admin/historique/**
wp-content/plugins/massifs-core/includes/rest/portail/historique/**
docs/contracts/issue-15.md                              (ce fichier)

+ ajouts STRICTEMENT ADDITIFS, autorisés pour la seule dette du lot 1 :
  wp-content/plugins/massifs-core/includes/domain/statuts/Depot.php
  wp-content/plugins/massifs-core/includes/domain/statuts/EntreeHistorique.php
  wp-content/plugins/massifs-core/includes/domain/statuts/Statuts.php
  wp-content/plugins/massifs-core/includes/domain/statuts/api.php
```

Projet **mono-branche**, arbre de travail partagé, aucune isolation. **#13** (rôles et authentification)
et **#14** (écran de publication) écrivent dans le même arbre **pendant** cette chaîne. La disjonction
des empreintes est la seule protection contre l'écrasement mutuel.

**Interdit absolu, sans exception** : `includes/security/**` · `includes/admin/ecran-publication/**` ·
`includes/rest/portail/publication/**` · `includes/rest/portail/module.php` · `massifs-core.php` ·
`includes/domain/statuts/Schema.php` · tout `wp-content/themes/massifs/**`.

---

## 0. Approche retenue, et ce que cette issue n'est pas

**« Une méthode de domaine, trois adaptateurs minces. »** `Statuts` gagne une lecture de journal
*correcte* ; l'écran, la route REST et l'export CSV en sont trois enveloppes fines, alimentées par un
**unique** analyseur de filtres.

### 0.1 Ce que l'issue n'est pas : « journaliser » ne demande aucun écrivain

L'intitulé « Journaliser chaque écriture de statut » suggère qu'il manque un écrivain. **Il n'en manque
aucun.** La table `{prefix}massifs_statuts` **est** simultanément l'historique intégral et le journal
d'écriture — décision actée dans `Schema.php` l.19-30 : « deux tables signifieraient deux vérités
possibles ». Les quatre faits exigés par le §6 y sont déjà :

| §6 exige | Colonne | Remarque |
|---|---|---|
| **qui** | `auteur_id` + `source` | `Statuts::valider()` **interdit** `auteur_id` pour `recuperation_officielle` et **l'exige** pour `saisie_manuelle` : le « qui » est toujours déterminé |
| **quoi** | `massif_code`, `jour_validite`, `niveau_cle`, `zapef_cle` | |
| **quand** | `enregistre_le` | posé par `Horloge::maintenant()` côté domaine, **jamais** par l'appelant |
| **ancienne / nouvelle** | dérivé | voir §0.2 — c'est là qu'est le vrai travail |

Le livrable de cette issue est donc un **lecteur honnête**, pas un second journal.
**Aucune seconde table, aucune nouvelle colonne, aucune option, aucun transient, aucun bump de
`Schema::VERSION`.** L'issue est purement en lecture sur le schéma existant.

### 0.2 Le vrai livrable : la dérivation de la valeur précédente est fausse

`EntreeHistorique::depuis_lignes()` (l.64-104) dérive la valeur précédente en **parcourant les lignes
retournées** et en mémorisant la dernière valeur vue par couple `massif_code|jour_validite`. C'est faux
pour l'usage que le §6 lui destine, de trois façons :

| Cause | Conséquence |
|---|---|
| filtre `auteur_id` ou `source` | le couple est tronqué → **une correction se déclare « première écriture »** |
| `limite` / `decalage` | même mensonge à **chaque frontière de page** |
| ordre `enregistre_le DESC` (celui qu'exige une vue chronologique) | la ligne « du dessus » est l'écriture **suivante** → **une valeur future présentée comme ancienne**, en silence |

Les filtres `massif_code`, `jour_debut` et `jour_fin` sont en revanche **innocents** : ils portent sur les
composantes mêmes du couple, qu'ils prennent en entier ou pas du tout.

C'est la classe de faute que le §12 (« journal exact ») et le §4.2 traitent comme absolue. La brancher
telle quelle serait **pire que la retirer**. Le lot 1 avait écrit le bon commentaire et le mauvais code.

**Décision — la valeur précédente est calculée EN SQL, sur la partition NON FILTRÉE**, par auto-jointure
corrélée. Voir §2.2.

### 0.3 Verdict sur la dette du lot 1 : **brancher en corrigeant**, jamais retirer

`Statuts::historique()`, `Depot::selectionner_historique()` et `EntreeHistorique` reçoivent un
consommateur. `EntreeHistorique` a exactement la forme dont le tableau et le CSV ont besoin, et son
`en_tableau()` porte une décision juste et documentée (ne jamais exposer `niveau_source_brut` /
`procedure_source`) qu'il serait absurde de réécrire.

Ce qui est faux n'est pas l'abstraction, c'est **une seule fabrique**. On ajoute la fabrique correcte à
côté ; **on ne supprime rien** — suppression unilatérale de code de domaine partagé interdite pendant
que deux chaînes sœurs tournent.

**`RETRAIT-DIFFÉRÉ` (à porter en validation de lot, hors de cette chaîne)** : une fois #13 et #14
atterries, `refacto-cms` doit statuer sur `EntreeHistorique::depuis_lignes()` et
`Depot::selectionner_historique()`, devenues redondantes. Les laisser, c'est garder dans l'arbre une
méthode **piégée** qui inverse le sens de la valeur précédente si quelqu'un la réutilise en ordre
descendant — exactement la façon dont cette dette est née. Nommé ici pour que le retrait ne soit jamais
oublié.

### 0.4 Refusé — ne pas reproposer

| Tentation | Motif |
|---|---|
| Stocker la valeur précédente en colonnes à l'écriture | stocke une **copie d'un fait dérivé** et réintroduit deux vérités dans une table qui existe pour n'en avoir qu'une (`Schema.php` l.19-30) ; impose une lecture avant chaque insertion dans le chemin chaud du cron |
| Fonction fenêtre `LAG()` | MySQL 5.7 est encore supporté par WordPress et ne l'a pas → historique vide, en silence |
| Seconde table de journal / extension d'audit | contredit `Schema.php` |
| Journaliser les écritures **refusées** | une écriture refusée n'est pas une écriture ; la loger imposerait une ligne « refus » dans une table dont **chaque ligne est un statut sélectionnable** — poison direct pour le §4.2 |
| Journaliser connexions, verrouillages, 2FA | puce « Sécurité du portail » du §6 → périmètre **#13** |
| Capturer IP / user-agent | §6 énumère quatre choses ; l'IP n'y est pas, et §9 revendique « seul traitement : les comptes internes » |
| Restaurer / annuler une valeur depuis l'historique | c'est une **écriture** ; appartient à #14 et contournerait ses garde-fous |
| Purge / rétention / suppression | §4.2 « l'historique intégral est conservé » ; `Depot` n'a ni `UPDATE`, ni `DELETE`, ni `TRUNCATE`, par construction |
| Couleur de diff ancienne/nouvelle | MASTER §7.2 l'interdit nommément ; §8 interdit l'information portée par la couleur seule |
| XLSX / JSON / PDF | §6 dit **CSV** |
| Tri par colonne arbitraire, options d'écran, recherche plein texte, pagination AJAX | ni §6 ni §12 ne les demandent ; chacun est un coût d'accessibilité |
| `WP_List_Table` | API `@access private` du cœur ; contredit MASTER §7.2 (« même tableau » que l'écran de publication, pastilles + `→`) ; neutraliser son chrome coûte plus de CSS qu'écrire la table |
| Exposition publique de l'historique | §5.4 ne promet que les statuts **du jour** ; exposer `auteur_id` publiquement heurte §9 |
| Afficher `niveau_source_brut` / `procedure_source` | `Depot` refuse délibérément de les SÉLECTIONNER. Ne pas « améliorer » |

---

## 1. Dépendances de lot — ce que cette chaîne CONSOMME sans le créer

### 1.1 Capacités (chaîne #13)

Identifiants **figés au niveau du lot**, que cette chaîne ne crée ni ne modifie :

| Identifiant | Nature | Propriétaire |
|---|---|---|
| `massifs_gestionnaire` | rôle | **#13** |
| `massifs_consulter_historique` | capacité | **#13** |

`massifs_consulter_historique` est exigée aux **trois** portes : écran, route REST, export CSV.

**Ces capacités n'existent pas tant que #13 n'a pas atterri.** On code contre elles sans les définir ; la
vérification effective des permissions se fait à la passe **`test-integration-cms` de fin de lot**.

> **`EXIGENCE-#13` (contraignante, à vérifier en fin de lot)** — `massifs_consulter_historique` doit être
> accordée **à `administrator` autant qu'à `massifs_gestionnaire`**. Le §6 du brief donne à
> l'administrateur « tout ». Si #13 ne l'accorde qu'au gestionnaire, **aucun administrateur ne pourra
> ouvrir l'historique**, et cette chaîne n'a structurellement pas le droit de compenser :
> **aucun repli sur `manage_options`, nulle part.** Un repli silencieux ferait passer la porte pour
> fermée alors qu'elle serait ouverte à toute personne pouvant gérer les options — exactement le défaut
> que le modèle de capacités existe pour éviter.

### 1.2 Chemin d'écriture du portail (chaîne #14)

> **`EXIGENCE-#14` (contraignante)** — le portail écrit **exclusivement** via
> `massifs_enregistrer_statut()` / `massifs_enregistrer_statuts()`, avec `source = 'saisie_manuelle'` et
> `auteur_id = get_current_user_id()`. **Aucun autre chemin d'écriture.**
>
> Si #14 écrit sans `auteur_id`, ou par un autre canal, le journal perd son « qui » et la colonne
> « Auteur » de cet écran affiche un trou **que cette chaîne ne pourra pas combler après coup** : la
> donnée n'aura jamais été enregistrée. Ce n'est pas un défaut de #15, c'est une dépendance de contrat.

Aujourd'hui le seul écrivain de l'arbre est `ProjecteurPrefecture` → `massifs_enregistrer_statuts()`.
`Depot::inserer()` est public mais n'a aucun appelant. Aucune fuite constatée.

### 1.3 `OUVERT` — lacune de couverture du §6 qui n'appartient PAS à cette chaîne

Le §6 dit « toute écriture est journalisée ». Cette issue couvre intégralement les écritures de
**statut**. Les écritures de **compte** — créer, suspendre, réinitialiser un gestionnaire, les trois
actions nommément promises par le §6 — n'ont **aucun journal** aujourd'hui. Hors empreinte (#13).
**Signalé à l'orchestrateur pour affectation à une issue ; non traité ici.**

---

## 2. Fonctions de lecture exposées par l'extension

Toutes dans `includes/domain/statuts/api.php`, chacune gardée par `if ( ! function_exists( … ) )`,
**ajoutées en fin de fichier**, aucune fonction existante touchée.

Pourquoi passer par `api.php` plutôt qu'appeler `Statuts::service()` : l'en-tête du fichier pose la règle
« aucun consommateur n'instancie ni n'appelle une classe `Massifs\` », et annonce explicitement qu'une
« future route REST sérialise sans adaptateur ». L'écran, la route et l'export sont des consommateurs.

### 2.1 Surface publique

```php
massifs_journal_statuts( array $criteres = array() ): array        // list<array>, ordre enregistre_le DESC, id DESC
massifs_journal_statuts_total( array $criteres = array() ): int
massifs_journal_statuts_borne( array $criteres = array() ): int    // MAX(id) de l'ensemble filtré, 0 si vide
massifs_journal_auteurs(): array                                   // list<int>, auteurs présents DANS le journal
massifs_sources_statut(): array                                    // list<string>, depuis SourceStatut
```

**Forme exacte d'une entrée de `massifs_journal_statuts()`, clé par clé :**

| Clé | Type | Sens |
|---|---|---|
| `id` | `int` | identifiant de la ligne = référence de l'écriture |
| `massif_code` | `string` | code normalisé |
| `jour_validite` | `string` | `YYYY-MM-DD` |
| `niveau_cle` | `?string` | `null` = la source a publié une ligne **sans** statut d'accès |
| `niveau` | `?array` | forme de `Niveau::en_tableau()`, `null` si clé nulle ou illisible |
| `niveau_lisible` | `bool` | `false` = clé non nulle **absente de la légende courante** |
| `niveau_precedent_cle` | `?string` | établi par SQL sur la partition non filtrée |
| `niveau_precedent` | `?array` | idem `niveau` |
| `niveau_precedent_lisible` | `bool` | |
| `zapef_cle` | `?string` | |
| `zapef` | `?array` | |
| `zapef_precedent_cle` | `?string` | |
| `zapef_precedent` | `?array` | |
| `premiere_publication` | `bool` | **vrai uniquement si SQL a établi qu'aucune ligne antérieure n'existe** |
| `changement` | `string` | `premiere_publication` \| `modification` \| `sans_changement` |
| `source` | `string` | valeur de `SourceStatut` |
| `auteur_id` | `?int` | `null` pour une récupération officielle |
| `publie_prefecture_le` | `?string` | ISO 8601 UTC |
| `enregistre_le` | `string` | ISO 8601 UTC |

`niveau` et ses variantes ont la forme exacte de `Niveau::en_tableau()` : `cle`, `libelle`, `consigne`,
`severite`, `motif`, `jeton_css`, `jeton_encre_css`, `rang`, `total`.

**Critères reconnus** (liste **fermée**, tout le reste ignoré en silence, jamais interpolé) :
`massif_code`, `jour_debut`, `jour_fin`, `auteur_id`, `source`, `enregistre_debut`, `enregistre_fin`,
`id_max`, `limite` (défaut 500, plafond 5000), `decalage`.

### 2.2 Ce que ces fonctions garantissent — et qui est le cœur de l'issue

**La valeur précédente est établie en SQL sur la partition NON FILTRÉE du couple
`(massif_code, jour_validite)`**, par auto-jointure corrélée :

```sql
LEFT JOIN <table> AS p
       ON p.id = ( SELECT MAX(t.id) FROM <table> AS t
                    WHERE t.massif_code   = s.massif_code
                      AND t.jour_validite = s.jour_validite
                      AND t.id            < s.id )
```

- **La sous-requête ne porte AUCUN filtre.** C'est tout l'objet du correctif : `p` est la ligne
  réellement précédente, pas la précédente du résultat affiché. Filtrer par auteur, par source ou
  paginer ne peut donc plus transformer une correction en première écriture.
- **Auto-jointure corrélée, pas `LAG()`** — portabilité MySQL 5.7. L'index existant
  `massif_jour (massif_code,jour_validite,id)` la sert directement. Volumétrie réelle : quelques milliers
  de lignes par saison, le coût est théorique.
- **`p.id` est sélectionné exprès.** C'est le SEUL discriminant entre « première écriture du couple »
  (`precedent_id IS NULL`) et « l'écriture précédente ne portait aucun niveau » (`precedent_id` non nul,
  `precedent_niveau_cle IS NULL`). Sans lui, la micro-copie du §6 mentirait un cas sur deux.
- **Ordre : `ORDER BY s.enregistre_le DESC, s.id DESC`.** `enregistre_le` est à la seconde ; sans `id` en
  départage la pagination duplique ou saute des lignes.
- **Tri et pagination sont le MÊME ordre, en SQL.** Réordonner en PHP après pagination est une faute de
  correction, pas de style : on paginerait sur un ordre et afficherait sur un autre, donc « page 2 »
  serait une tranche arbitraire du temps.
- `niveau_source_brut` et `procedure_source` ne sont sélectionnés **ni sur `s` ni sur `p`**.

### 2.3 Interdits attachés à ces fonctions

> **USAGE RÉSERVÉ AU PORTAIL. N'ALIMENTE JAMAIS UN AFFICHAGE PUBLIC.**
> Le journal est un relevé d'écritures passées ; chaque entrée porte **son** jour de validité et ne dit
> rien du jour courant. Dériver un « statut actuel » d'une entrée de journal — par exemple en prenant la
> première ligne d'un `limite: 1` — serait exactement la faute que le §4.2 interdit. Le statut public se
> lit **exclusivement** par `massifs_statuts_du_jour()`, indexée par date.

La **clause absolue** d'`api.php` (« l'API de lecture est indexée exclusivement par date ; aucune
fonction *dernier statut connu* ne doit exister ») n'est **pas** entamée : aucune de ces fonctions ne
répond à « quel est le statut maintenant ». Ce bandeau est obligatoire en docblock sur les trois
premières, plus une garde `_doing_it_wrong()` sous `WP_DEBUG` seulement, si l'appel ne survient ni en
administration ni en requête REST.

---

## 3. L'analyseur de filtres — un seul, trois consommateurs

`includes/admin/historique/filtres.php`. **C'est la pièce qui empêche l'écran, la route et l'export de
diverger.** Deux analyseurs finissent toujours par ne plus dire la même chose ; il n'y en a qu'un.

```php
massifs_historique_filtres_depuis_requete( array $brut ): array
massifs_historique_criteres( array $filtres, int $id_max = 0 ): array
massifs_historique_url( array $filtres, array $remplacements = array() ): string
massifs_historique_champs_caches( array $filtres ): array
```

Retour de `massifs_historique_filtres_depuis_requete()` :
`massif_code` string · `auteur_id` int · `source` string · `jour_debut` string · `jour_fin` string ·
`enregistre_debut` string · `enregistre_fin` string · `paged` int · `par_page` int ·
`rejets` list\<string\> · `actifs` bool.

| Champ | Assainissement | Validation | Défaut |
|---|---|---|---|
| `massif` | `sanitize_key` | présent dans `massifs_codes( true )` — **retirés inclus**, un massif retiré garde son historique ; repli `/^[a-z0-9_-]{1,64}$/` si le référentiel est indisponible | `''` = tous |
| `auteur` | `absint` | `> 0` **et** présent dans `massifs_journal_auteurs()` | `0` = tous |
| `source` | `sanitize_key` | dans `massifs_sources_statut()` | `''` = toutes |
| `jour_debut`, `jour_fin` | `sanitize_text_field` + `trim` | `/^\d{4}-\d{2}-\d{2}$/` **et** date réelle | `''` |
| `enregistre_debut`, `enregistre_fin` | idem | idem | `''` |
| `paged` | `absint` | `>= 1` | `1` |
| `par_page` | `absint` | dans `{20, 50, 100, 200}` | `50` |

- **Toute valeur rejetée entre dans `rejets`, jamais corrigée en silence.** L'écran la rend en
  avertissement relié au champ (`aria-describedby` + `aria-invalid="true"`) et **conserve la valeur
  saisie** ; la route la renvoie sous `filtres_ignores`.
- **Intervalle inversé** (`début > fin`) : les deux bornes sont conservées, le couple entre dans `rejets`
  sous `jour_intervalle` / `enregistre_intervalle`, et l'écran affiche « Le début est postérieur à la
  fin : aucun résultat possible. »
- **Aucune valeur n'est échappée ici.** L'échappement est à la **sortie**. Les mêmes tableaux alimentent
  du HTML, du JSON et un CSV : une entité HTML dans un CSV est une **corruption de donnée**, pas une
  protection.
- Lecture de `$_GET` précédée de `wp_unslash()`, avec un `phpcs:ignore
  WordPress.Security.NonceVerification.Recommended` **motivé** : un formulaire de filtrage en GET, en
  lecture seule, n'a pas de nonce à vérifier ; le nonce du formulaire n'existe que pour l'export.

### 3.1 Les deux filtres de date — pourquoi il y en a deux

Le §6 dit « filtrable (massif, **période**, auteur, source) ». « Période » est ambigu : le **jour de
validité** et l'**instant d'enregistrement** sont deux dates différentes — une correction faite le 15 août
porte sur le statut du 12 août.

**Décision : on ne devine pas, on expose les deux, explicitement étiquetés.**

- `« Jour de validité »` — terme **figé** du §11.2 de MASTER, repris **verbatim**. Filtre `jour_validite`.
- `« Enregistré le »` — filtre `enregistre_le`.

Chacun dans son propre `<fieldset><legend>`, seul moyen sans JavaScript pour qu'un lecteur d'écran
annonce laquelle des deux dates il saisit.

### 3.2 Fuseau — la conversion appartient au domaine

`enregistre_le` est stocké en **UTC** ; le gestionnaire saisit un **jour civil de Paris**. La conversion
se fait dans `Statuts::normaliser_criteres_journal()`, près de `Horloge`, seule source légitime du temps
de l'extension — ni l'admin ni le REST n'ont le droit de la refaire.

- `enregistre_debut` → `enregistre_le_min = Horloge::vers_mysql( Horloge::jour_vers_debut( $j ) )`
- `enregistre_fin` → `enregistre_le_max = Horloge::vers_mysql( Horloge::jour_vers_debut( $j )->modify( '+1 day' ) )`

**Borne haute EXCLUSIVE, calculée sur le début du jour suivant**, jamais `<= 'YYYY-MM-DD 23:59:59'` : la
colonne est un `datetime`, un seuil à la seconde perdrait toute écriture de la dernière seconde. Et sans
la conversion, une écriture du 13 août à 23 h 30 Paris (21 h 30 UTC) tomberait dans le bon jour **par
hasard**, et une du 14 août à 01 h 00 Paris (13 août 23 h 00 UTC) dans le mauvais.

---

## 4. Routes REST

```
GET /wp-json/massifs/v1/portail/historique
```

**`ARBITRAGE`** — namespace `massifs/v1` (celui de `rest/public`), chemin préfixé `portail/` pour marquer
la surface authentifiée. Choisi sans visibilité sur #14, qui ne peut pas être consultée. Si #14 a retenu
autre chose, l'impact est **d'une ligne** ; à réconcilier en revue de lot.

**`permission_callback`** : `massifs_rest_portail_historique_autorise()`
- `! is_user_logged_in()` → `WP_Error( 'massifs_historique_non_authentifie', …, [ 'status' => 401 ] )`
- `! current_user_can( 'massifs_consulter_historique' )` → `WP_Error( 'massifs_historique_interdit', …, [ 'status' => 403 ] )`
- sinon `true`

**Jamais `__return_true`. Jamais de repli sur `manage_options`.** Pas de vérification de nonce explicite :
pour une requête authentifiée par cookie, le cœur exige déjà `X-WP-Nonce` avant de considérer
l'utilisateur connecté (`rest_cookie_check_errors`) — à **commenter** pour qu'un relecteur n'y voie pas
un oubli.

**Arguments** — `massif`, `auteur`, `source`, `jour_debut`, `jour_fin`, `enregistre_debut`,
`enregistre_fin`, `paged`, `par_page`. Chacun avec `sanitize_callback` **et** `validate_callback` ; le
`enum` de `source` vient de `massifs_sources_statut()`, celui de `par_page` de `{20,50,100,200}`.
Le schéma d'arguments est une **seconde** couche, plus stricte : elle rend `400 rest_invalid_param` là où
l'analyseur se contenterait d'ignorer. **L'analyseur reste l'autorité** : le callback lui repasse
`$requete->get_params()` et n'interprète rien lui-même.

**Réponse `200`** :

```json
{
  "entrees": [ { "…forme du §2.1, enrichie des libellés du §6…" } ],
  "pagination": { "page": 3, "par_page": 50, "total": 1248, "pages": 25, "id_max": 4711 },
  "filtres": { "massif": "", "auteur": 0, "source": "", "jour_debut": "", "jour_fin": "",
               "enregistre_debut": "", "enregistre_fin": "" },
  "filtres_ignores": [],
  "etat": "disponible"
}
```

`entrees` est **toujours** un tableau, jamais `null`. `etat` ∈ `disponible` | `journal_vide` |
`aucun_resultat` | `journal_indisponible`. Valeurs **brutes et non échappées** (c'est du JSON) ; les
libellés officiels y sont verbatim, apostrophes comprises.

| Code | Quand |
|---|---|
| `200` | nominal, y compris `entrees: []` |
| `400` | `rest_invalid_param` |
| `401` | non authentifié |
| `403` | authentifié sans `massifs_consulter_historique` |
| `503` | domaine non chargé → `massifs_journal_indisponible`, `fonctions_absentes` en `data` |

Un `Throwable` du domaine est capté, journalisé sous `WP_DEBUG`, rendu en `503
massifs_domaine_en_erreur` — **jamais une trace nue**.

**En-têtes** : `Cache-Control: no-store, private` et `X-Robots-Tag: noindex, nofollow`. Obligatoires :
contrairement à la route publique, cette réponse **varie selon l'utilisateur** et aucun cache partagé ne
doit pouvoir la servir à un anonyme.

**La carte publique n'appelle JAMAIS cette route.**

### 4.1 Export CSV — ce n'est pas une route REST

```
GET /wp-admin/admin-post.php?action=massifs_exporter_historique&…filtres…&_wpnonce=…
```

Hook `admin_post_massifs_exporter_historique`, **sans variante `nopriv`** — un export du journal ne doit
pas même avoir de porte anonyme à refuser. Séquence : capacité → `check_admin_referer()` → analyseur
partagé → `id_max` → en-têtes → flux.

Pourquoi `admin_post_` et pas REST : un lien `admin-post.php` fonctionne **sans JavaScript**
(contrainte 3), là où une route REST rendant du `text/csv` devrait court-circuiter
`rest_pre_serve_request` et `exit` — se battre contre le sérialiseur JSON pour rien.

---

## 5. États spéciaux

| État | Émis par le serveur | Rendu imposé |
|---|---|---|
| `journal_indisponible` | module de domaine absent (`function_exists` en échec) | « Le journal des statuts est momentanément indisponible. » — **jamais un tableau vide**, qui se lirait « il ne s'est rien passé » |
| `journal_vide` | aucune écriture n'existe | « Aucune écriture n'a encore été journalisée. » |
| `aucun_resultat` | filtres sans résultat | « Aucune écriture ne correspond à ces filtres. » + lien « Réinitialiser les filtres » |
| `niveau_cle === null` | la source a publié une ligne **sans** statut d'accès | « Aucun niveau publié », texte simple, **aucune pastille** |
| `premiere_publication === true` | SQL a établi qu'aucune ligne antérieure n'existe | **une** pastille, **aucune flèche**, mention « Première publication » |
| `changement === 'sans_changement'` | republication à l'identique (cas nominal quand la préfecture reconduit) | deux pastilles identiques séparées par la flèche — ce n'est **pas** une anomalie |
| `niveau_lisible === false` | clé stockée absente de la légende courante | « Niveau non reconnu » + la clé brute en `<code>` — échec de configuration, **jamais silencieux** |
| `filtres_ignores` non vide | valeur de filtre rejetée | avertissement relié au champ, valeur saisie conservée |

Les états publics `information_indisponible`, `hors_saison`, `donnee_perimee` et
`couche_effis_indisponible` **ne concernent pas cet écran** : le journal est un relevé d'écritures
passées, pas un affichage de statut courant. **Il n'affiche aucune bannière de fraîcheur** — et il n'a
pas à en afficher, puisqu'il ne prétend jamais dire l'état du jour.

---

## 6. Chaînes fournies par le serveur

**La vue n'en compose aucune.** `ecran.php` échappe et concatène des valeurs déjà rédigées ; toutes les
chaînes vivent dans la table fermée de `includes/admin/historique/vocabulaire.php`.

### 6.1 Officielles — verbatim, §11.4 de MASTER, ne jamais éditer

`Accès au massif autorisé` · `Accès au massif interdit` · `Accès à la ZAPEF* autorisé` ·
`Accès à la ZAPEF* interdite` · `Niveau d'Accès` · `*ZAPEF : Zones d’Accueil du Public en Forêt`

> **Les deux apostrophes divergent volontairement.** `Zones d’Accueil` porte U+2019 ; `Niveau d'Accès`
> porte U+0027. C'est ce que publie la source. Une « uniformisation typographique » — réflexe naturel
> d'un intégrateur consciencieux, d'un linter ou d'un correcteur — casserait la reproduction fidèle
> exigée par le §4.2. **Toute modification, y compris orthographique, est un défaut bloquant.**

### 6.2 Micro-copie tranchée par le lead — non négociable pour les agents

| Chaîne | Emploi | Pourquoi celle-là |
|---|---|---|
| `Aucun niveau publié` | `niveau_cle === null` | dit exactement le fait — la source a publié, sans statut d'accès — et ne peut pas se confondre avec « inconnu ». Rendue **sans pastille** : une pastille se lirait comme un niveau |
| `Première publication` | `premiere_publication === true` | vocabulaire §11.2 (`publier`). **N'est vraie que grâce au correctif SQL du §2.2** : sans lui, cette mention serait un mensonge une ligne sur deux |
| `Récupération officielle` | colonne **Auteur** quand `auteur_id` est `null` | **arbitrage du lead** : réutilise le terme exact de la colonne Source plutôt que d'inventer un troisième mot (« Récupération automatique » a été **écarté** — §11.2 impose un terme, un sens, partout) |
| `Compte supprimé (#7)` | `get_userdata()` rend `false` | affiche explicitement le compte disparu **avec son identifiant** ; une case vide serait ambiguë avec « aucun auteur » |

### 6.3 Rédigées par cette chaîne — vocabulaire §11.2 respecté

Titre `Historique des statuts` · en-têtes `Massif`, `Jour de validité`, `Niveau d'Accès`, `ZAPEF`,
`Source`, `Auteur`, `Enregistré le`, `Référence` · légendes de groupe `Jour de validité`, `Enregistré le`
· sources `Récupération officielle`, `Saisie manuelle` (les deux termes du §4.2 du brief) · changements
`Modification`, `Sans changement` · actions `Filtrer`, `Exporter en CSV`, `Réinitialiser les filtres` ·
pagination `Page précédente`, `Page suivante` · aide `Format AAAA-MM-JJ` · transition lecteur d'écran
`remplacé par`.

Vocabulaire imposé : **massif**, **niveau**, **statut**, **jour de validité**, **gestionnaire**,
**publier**. **Jamais** zone, état, date, valider, secteur, alerte.

### 6.4 Donnée personnelle

Seul `display_name` est exposé. **Jamais `user_login`, jamais `user_email`** (§9 du brief, blocage de
l'énumération d'utilisateurs). Le select « auteur » ne liste que les comptes **présents dans le
journal** — jamais tous les comptes WordPress, ce qui serait une énumération.

---

## 7. Contrat de balisage — `dev-back-cms` écrit, `dev-ux-cms` style à l'aveugle

**C'est le second point de réconciliation de ce contrat.** Les deux agents ne se voient pas. Aucune
classe hors de cette liste n'apparaîtra dans le rendu ; aucune autre ne doit être stylée.

### 7.1 Structure

| Classe / sélecteur | Élément |
|---|---|
| `.massifs-historique` | `<div>` racine de la page (portée du CSS) |
| `#massifs-historique-titre` | `<h1>` — unique |
| `.massifs-historique-resume` | `<p role="status">` — compte de résultats + page |
| `.massifs-historique-avertissement` | `<p>` — filtres ignorés, intervalle inversé |
| `.massifs-historique-vide` | `<p>` — états vides |

### 7.2 Filtres

`.massifs-historique-filtres` (`<form method="get">`) · `.massifs-historique-filtres__groupe`
(`<fieldset>` ×3) · `.massifs-historique-filtres__champ` · `.massifs-historique-filtres__aide`
(cible d'`aria-describedby`) · `.massifs-historique-filtres__actions` ·
`.massifs-historique-filtres__reinitialiser` (`<a>`) · `.massifs-bouton`, `.massifs-bouton--primaire`,
`.massifs-bouton--secondaire` (`<button type="submit">`).

### 7.3 Tableau

`.massifs-historique-defilant` (`<div role="region" tabindex="0">` — **doit recevoir un focus visible**) ·
`.massifs-historique-table` · `.massifs-historique-table__legende` (`<caption>`) ·
`.massifs-historique-ligne` · `.massifs-historique-ligne--premiere` / `--modification` /
`--sans-changement` (**décoratifs seulement, aucune information ne doit y être portée**) ·
`.massifs-historique-cellule--massif` / `--jour` / `--niveau` / `--zapef` / `--source` / `--auteur` /
`--enregistre` / `--reference`.

### 7.4 Transition ancienne → nouvelle (MASTER §7.2)

| Classe / attribut | Élément | Note |
|---|---|---|
| `.massifs-historique-transition` | `<span>` conteneur | |
| `.massifs-historique-transition--premiere` | modificateur | **une** pastille, **pas** de flèche |
| `.massifs-historique-transition__fleche` | `<span>` contenant un **SVG EN LIGNE** | voir `CORRECTIF-1` ci-dessous — **jamais le caractère `→`**, **jamais une couleur de diff** |
| `.massifs-historique-transition__mention` | `<span>` | porte « Première publication » |
| `.massifs-historique-sans-niveau` | `<span>` | « Aucun niveau publié », **aucune pastille** |
| `.massifs-historique-niveau-inconnu` | `<span>` | clé absente de la légende |
| `.massifs-pastille` | `<span aria-hidden="true">` | **toujours vide**, jamais de texte dedans |
| `.massifs-pastille--ancienne` / `--nouvelle` | modificateurs | |
| `.massifs-pastille--zapef` | modificateur | la ZAPEF est un **point**, pas une surface (§8.1) |
| `.massifs-pastille-libelle` | `<span>` | le libellé officiel, **à côté** de l'aplat |
| `[data-niveau="autorise\|interdit"]` | sur `.massifs-pastille` | pilote le jeton d'aplat |
| `[data-motif="aucun\|hachure_croisee\|barre\|hachure_descendante\|pointille"]` | sur `.massifs-pastille` | **obligatoire** |

### 7.4 bis `CORRECTIF-1` — la flèche est un SVG en ligne, jamais le caractère `→`

**Ce contrat a été gelé avec une erreur sur ce point ; elle est corrigée ici, et cette version fait foi.**

La rédaction initiale du §7.4 imposait `<span aria-hidden="true">→</span>`, en s'appuyant sur le §7.2 de
MASTER (« une flèche typographique `→` »). **C'est faux**, et `dev-ux-cms` a eu raison de le signaler au
lieu de l'appliquer :

> MASTER §5 l.502-503 et **D-25** : « La flèche `→` (U+2192) de §7.2 est **hors du sous-ensemble `latin`
> et absente des deux polices**. Elle est donc rendue en **SVG en ligne, jamais en caractère**. »
> Le §16 en fait un **défaut bloquant** : « `→` — ou tout autre symbole hors du sous-ensemble `latin` —
> écrit en caractère plutôt qu'en SVG en ligne ».

Écrite en caractère, la flèche afficherait un **rectangle vide** (tofu) dans l'historique du portail, ou
serait empruntée à une police système — donc hors du design system, et hors de la contrainte 2 dans
l'esprit sinon dans la lettre. Le §7.2 de MASTER dit « typographique » au sens de *sobre et non
décorative*, pas au sens de *caractère Unicode* ; le §5 et D-25, plus récents et explicitement mesurés,
tranchent.

**Balisage imposé** — convention SVG en ligne déjà en vigueur dans le thème
(`templates/parts/meteo.php`, `templates/parts/carte.php`) :

```html
<span class="massifs-historique-transition__fleche">
  <svg viewBox="0 0 16 8" width="16" height="8" aria-hidden="true" focusable="false">
    <path d="…" fill="currentColor" />
  </svg>
  <span class="screen-reader-text">remplacé par</span>
</span>
```

- **SVG en ligne**, jamais un fichier externe, jamais une image : aucune requête supplémentaire, donc
  contrainte 2 intacte.
- `aria-hidden="true"` **et** `focusable="false"` sur le `<svg>` — convention du thème.
- `fill="currentColor"` : la flèche hérite de la couleur du texte, aucune valeur littérale, aucun jeton à
  redéfinir. **`dev-ux-cms` n'a donc rien à styler dessus** hormis l'alignement — et surtout **aucune
  teinte de diff**.
- Le texte « remplacé par » reste **hors** du `<svg>`, en `screen-reader-text` : c'est lui qui porte le
  sens pour un lecteur d'écran, la flèche n'étant que sa traduction visuelle.
- La flèche n'apparaît **pas** sur une première publication (`--premiere`), qui n'a qu'une pastille.

### 7.5 Pagination

`.massifs-historique-pagination` (`<nav aria-label="Pages de l'historique">`) ·
`.massifs-historique-pagination__lien` (`<a>`) · `.massifs-historique-pagination__lien--courant`
(`<span aria-current="page">`, **pas un lien**).

### 7.6 Contraintes CSS — non négociables

1. **Aucune custom property définie, aucune valeur littérale de couleur, d'espacement ou de durée.**
   MASTER §12 : `tokens.css` est le seul fichier qui en définit. Uniquement des `var(--…)`.
   *Seule exception tolérée* : les géométries de motif que MASTER §8.1 écrit lui-même en littéral
   (`2px`/`9px` pour la hachure descendante, `1.2px`/`1.4px`/`6px` pour le pointillé) — à recopier
   **verbatim**, avec le commentaire de justification, car les jetons de motif y modifieraient le dessin
   normatif.
2. **Les jetons viennent du thème.** Le module enregistre sa **propre poignée** pointant sur
   `get_theme_file_uri( 'assets/css/tokens.css' )`, gardée par `is_readable( get_theme_file_path( … ) )`.
   La poignée `massifs-tokens` du thème n'est **pas** disponible : elle est enregistrée sur
   `wp_enqueue_scripts`, qui ne se déclenche pas en `wp-admin`. Même domaine → contrainte 2 intacte.
   `massifs_version_asset()` est une fonction **du thème** : la garder derrière `function_exists()` ou
   calculer `filemtime()` soi-même.
3. **Ne JAMAIS enfiler `layout.css` ni `composants.css` en administration** : le `box-sizing: border-box`
   global de `layout.css` casserait la mise en page propre de wp-admin.
4. Les règles de pastille sont **recopiées, scopées** sous `.massifs-historique`, avec un commentaire
   nommant `assets/css/composants.css` comme source de vérité. *(Voir §9, `ARBITRAGE-CSS`.)*
5. **Aucun texte posé sur un aplat de statut** (§4.1.d règle 3 — `--c-charbon` sur `#E63A3C` plafonne à
   4,11:1).
6. **Motif obligatoire** partout où une pastille apparaît, **écran gestionnaire compris** (§4.1.d règle 4).
7. **Liseré `--c-charbon` 2 px** sur toute pastille (§4.1.d règle 2).
8. Chrome du portail **en famille de texte, casse normale** ; **seules** les étiquettes de statut restent
   en famille d'affichage (§7.2 [v2.3]).
9. **Aucun bouton désactivé** (§7.2) : `.massifs-bouton` n'a pas d'état `:disabled` à styler.
10. **`outline: none` interdit.** Focus visible sur tous les contrôles **et** sur
    `.massifs-historique-defilant`.
11. 360 px sans défilement horizontal **de page**, cibles ≥ 44 px, zoom 200 % sans perte.
12. Feuille d'impression : `overflow: visible` sur la zone défilante et **motifs conservés**
    (`print-color-adjust: exact`) — un tableau imprimé sans motif redevient une information portée par la
    couleur seule.
13. Le CSS est enfilé **uniquement sur le `$hook_suffix` de l'écran**, jamais globalement.
14. **Le balisage doit rester accessible sans aucun CSS** : si le thème n'est pas actif, les jetons sont
    absents et la page doit rester lisible et navigable.

---

## 8. Interdits

- Le thème **n'appelle jamais** `massifs_journal_statuts()`, ses variantes, ni la route
  `massifs/v1/portail/historique`. Le journal est **réservé au portail**.
- Le thème **ne dérive jamais un « statut du jour »** d'une entrée de journal. Le statut public se lit
  **exclusivement** par `massifs_statuts_du_jour()`, indexée par date (§4.2).
- L'écran, la route et l'export **ne touchent jamais `$wpdb`**, ni `get_results`, ni aucun SQL brut.
  `Depot` est la **seule** classe de l'extension qui voit `$wpdb`, par sa propre déclaration.
- L'écran, la route et l'export **n'instancient aucune classe `Massifs\`** — uniquement les fonctions
  `massifs_*()`.
- **Aucun consommateur ne réordonne ni ne re-pagine les entrées** en PHP ou en JS après réception :
  l'ordre `enregistre_le DESC, id DESC` est celui du SQL, et le rompre rend la pagination fausse.
- **L'extension n'émet aucun HTML de présentation publique.** Cet écran est du `wp-admin` ; il n'a aucun
  rendu public et ne doit jamais être embarqué dans une page publique.
- La vue **ne compose aucune chaîne** : ni libellé de niveau, ni phrase de transition, ni libellé de
  source, ni date en français. Tout vient du serveur, déjà rédigé.
- **Aucun `do_action`, aucun `apply_filters` émis par cette chaîne.** Un écran d'audit qui offrirait un
  filtre offrirait une prise pour **altérer un journal** — précisément ce que le §12 interdit. Seuls
  `admin_menu`, `admin_post_massifs_exporter_historique` et `rest_api_init` sont consommés.
- **L'écran d'historique n'écrit rien.** Lecture stricte : aucune entrée d'audit produite, aucune
  invalidation de cache, aucun appel à `massifs_enregistrer_statut*()`.
- **Aucune suppression, aucune purge, aucune rétention.** L'historique est intégral et perpétuel (§4.2).

---

## 9. Arbitrages

Chaque désaccord tranché, et sa raison.

**`ARBITRAGE-1` — Dérivation du précédent : SQL, pas PHP.** Auto-jointure corrélée plutôt que fonction
fenêtre (`LAG()` absent de MySQL 5.7, encore supporté par WordPress → historique vide en silence),
plutôt que ligne d'ancrage (ne résout pas les filtres auteur/source), plutôt que stockage à l'écriture
(deux vérités, contredit `Schema.php`). *Le principe « une seule table » est intégralement respecté : la
valeur précédente est **calculée** à la lecture au lieu d'être **devinée** dans une fenêtre.*

**`ARBITRAGE-2` — Empreinte étendue à quatre fichiers de domaine.** L'empreinte déclarée de l'issue rendait
l'issue **inimplémentable honnêtement** : `Depot` se déclare seule classe voyant `$wpdb`, il fallait donc
soit amender l'empreinte, soit violer l'invariant architectural le plus explicite de l'extension.
Extension autorisée par l'orchestrateur pour la seule dette du lot 1, **strictement additive**.

**`ARBITRAGE-3` — Nouvelles méthodes plutôt que modification des existantes.** `selectionner_historique()`
reste **octet pour octet identique** ; `depuis_lignes()` et `en_tableau()` ne sont pas touchés. Les deux
paramètres ajoutés à `EntreeHistorique::__construct()` sont **en fin de signature avec défaut sûr**, donc
invisibles pour l'appel existant à 11 arguments. Le drapeau `precedent_etabli` distingue « SQL a établi
qu'il n'y a pas de précédent » de « personne ne l'a établi » : sans lui, une entrée de l'ancienne
fabrique serait indistinguable d'une vraie première publication — le mensonge qu'on corrige.

**`ARBITRAGE-4` — « Période » : deux filtres, pas un choix.** Le brief est ambigu et je refuse de deviner.
Les deux dates sont exposées, explicitement étiquetées (§3.1). Coût : un `<fieldset>` de plus. Bénéfice :
aucune interprétation silencieuse d'un mot du brief.

**`ARBITRAGE-5` — Auteur d'une récupération officielle : « Récupération officielle ».**
« Récupération automatique », proposé en amont, est **écarté** : il aurait introduit un **troisième** terme
pour une notion qui en a déjà un, contre le §11.2 (« un terme, un sens, partout »). La redondance avec la
colonne Source est assumée et sans danger ; un terme de plus ne l'aurait pas été.

**`ARBITRAGE-6` — Trois questions signalées « bloquantes » en amont, tranchées ici.** Le `brainstorm` les
avait remontées comme arrêt de chaîne. Vérification faite, **aucune n'est un fait de domaine** : §11.4 est
la liste **fermée** des chaînes appartenant à la préfecture, et aucune des trois n'y figure. Ce sont des
micro-copies **de notre plume** pour un écran neuf, réversibles, contenues dans un seul fichier
(`vocabulaire.php`), et bornées par les règles de voix du §11.1 et le vocabulaire figé du §11.2. Tranchées
au §6.2 ; **soumises à confirmation du propriétaire en revue**, sans bloquer trois chaînes parallèles.

**`ARBITRAGE-7` — `id_max` fige la fenêtre d'export.** Un curseur par `id` serait **faux** ici : l'ordre est
`enregistre_le DESC`, la dernière ligne d'une tranche n'est donc pas le plus petit `id` restant. Avec
`id <= id_max` figé au démarrage et une table en **insertion pure** (`Depot` n'expose ni `UPDATE` ni
`DELETE`), l'ensemble résultat est **immuable** pendant tout l'export : `OFFSET` redevient parfaitement
stable, aucune ligne ne peut être dupliquée ni sautée. *C'est la propriété d'insertion pure qui rend
l'export honnête.*

**`ARBITRAGE-8` — L'export n'est jamais tronqué.** `Depot` plafonne à 5000 lignes par appel ; une saison
peut dépasser. Un CSV tronqué présenté comme « l'historique filtré » est **la même classe de mensonge**
que le défaut corrigé au §2.2. Diffusion en flux par tranches de 500, plafond sans effet sur le contenu.

**`ARBITRAGE-9` — L'amorce d'administration charge le module REST.** Le chargeur n'auto-découvre
`includes/<couche>/<module>/module.php` qu'à **un** niveau de profondeur ;
`includes/rest/portail/historique/` est à deux, et `includes/rest/portail/module.php` appartient à #14.
`require_once` depuis `admin/historique/module.php` + enregistrement idempotent. **Aucun fichier partagé
n'est touché**, ce qui vaut largement la petite odeur de couche — à commenter dans le code.
Corollaire : `module.php` est chargé à **chaque** requête, y compris publique. Il ne charge donc
inconditionnellement que `filtres.php` et le module REST ; **tout le reste est derrière `is_admin()`**.

**`ARBITRAGE-10` — Menu défensif.** #13, #14 et #15 veulent tous une entrée « MASSIFS » sans se voir.
`admin_menu` en priorité **99** (les autres enregistreront à 10) ; si
`$GLOBALS['admin_page_hooks']['massifs']` existe → `add_submenu_page()` seul ; sinon on crée le parent
**puis** le sous-menu. Le parent n'est jamais un lien mort, le doublon est impossible, et l'état dégradé
n'existe que tant que ni #13 ni #14 n'ont livré. Slug de page `massifs-historique`, distinct par
construction.

**`ARBITRAGE-CSS` — Recopie scopée des règles de pastille.** MASTER §12 interdit de redéfinir des jetons ;
il n'interdit pas de réécrire des règles de classe. Les définitions de pastille de
`themes/massifs/assets/css/composants.css` sont **recopiées, scopées** sous `.massifs-historique`, parce
qu'aucune empreinte du lot ne possède de feuille de chrome de portail partagée et qu'enfiler
`composants.css` en administration y importerait `layout.css` et son `box-sizing` global.
**Coût assumé, nommé ici pour qu'il ne soit pas découvert plus tard : risque de dérive** entre les deux
copies de la géométrie normative du §8.1. **Suivi recommandé** : consolider une feuille de chrome de
portail partagée par #14 et #15 dans une issue ultérieure — elle n'appartient à aucune des trois chaînes
du lot.

**`ARBITRAGE-11` — `auteurs_journal()` plutôt que la liste des comptes.** Le select « auteur » ne liste que
les utilisateurs **présents dans le journal**. Lister tous les comptes WordPress dans un écran ouvert au
gestionnaire serait une **énumération d'utilisateurs**, que le §9 du brief exige de bloquer.

---

## 10. Vérifications attendues à la passe de lot

Rappel : `test-integration-cms`, `review-cms`, `docker-cms` et `git push` sont de **niveau lot** et
n'appartiennent pas à cette chaîne.

1. **Porte de capacité, aux trois entrées.** Écran, route REST et export, chacun en anonyme (`401`/
   redirection), en authentifié sans la capacité (`403`), et en `massifs_gestionnaire` (`200`).
   `EXIGENCE-#13` : vérifier aussi en `administrator`.
2. **Exactitude du journal — le test qui compte.** Créer un couple (massif, jour) avec **trois** écritures
   successives, puis filtrer par auteur, par source, et paginer avec `par_page=20` sur une frontière :
   la valeur « ancienne » de chaque ligne doit rester **la vraie ligne précédente**, et
   « Première publication » ne doit apparaître **que** sur la toute première écriture du couple.
3. **Export non tronqué et déterministe.** Export d'un ensemble > 5000 lignes ; vérifier le compte exact
   et l'absence de doublon. Écrire pendant l'export : les lignes nouvelles ne doivent **pas** y figurer.
4. **CSV** : BOM présent, séparateur `;`, accents corrects à l'ouverture Excel fr-FR, aucune cellule
   commençant par `=`, `+`, `-`, `@`, tabulation ou CR sans apostrophe de garde.
5. **Sans JavaScript** : filtrer, paginer et exporter avec JS désactivé.
6. **Accessibilité** (§8, bloquant) : axe/pa11y sans erreur bloquante sur
   `admin.php?page=massifs-historique`, parcours clavier complet, focus visible y compris sur la zone
   défilante, 360 px sans défilement horizontal de page, zoom 200 %.
7. **Aucune origine tierce** sur l'écran.
8. **Aucune fuite** de `user_login` ni de `user_email` dans le HTML, le JSON ou le CSV.
