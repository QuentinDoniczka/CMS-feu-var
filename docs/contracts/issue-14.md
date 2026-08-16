# Contrat d'interface — Issue #14 — Construire l'écran de mise à jour unique des statuts

**Gelé le** 14 août 2026 · **Par** `lead-issue-cms`, chaîne #14 · Épic 5 — Portail · **Statut** contraignant
**Lignes de DoD servies** : §12 (mise à jour complète < 1 min, propagation < 1 min) · §12 (mobile réel
360 px : écran gestionnaire) · §6 (écran de mise à jour unique).

> Ce document est le point de réconciliation de deux plans produits **en aveugle** l'un de l'autre par
> `leaddev-back-cms` et `leaddev-front-cms`. Ils étaient en désaccord sur **la totalité des noms de
> champs du formulaire**, sur le **format du jour transmis**, sur la **liste des fichiers** et sur les
> **feuilles de style à charger**. Tout est tranché ci-dessous. À partir de ce point, ce document fait
> autorité contre les deux plans.

> **RÉVISION 1 — 14 août 2026, après la première passe des trois `dev-*`.** Sept corrections, dont une
> seule change le comportement livré. Elles sont écrites ici parce qu'un contrat qui ment après
> l'implémentation est pire qu'un contrat absent : `review-cms` compterait chaque écart deux fois.
>
> | # | Correction | Origine |
> |---|---|---|
> | **R-1** | **Le pré-remplissage passe dans un `<form>` FRÈRE.** Voir §2, « Deux formulaires ». Seule correction qui change le comportement | Réserve n° 2 de `dev-front-cms` |
> | **R-2** | **§7 était factuellement faux** : `Niveau d'Accès` **n'est pas** dans `massifs_legende()`. La légende n'expose que les deux libellés d'accès, les deux libellés ZAPEF et `zapef_note`. L'en-tête officiel est reproduit verbatim (U+0027) dans `messages.php`, à l'unique endroit des chaînes. **Les deux libellés d'accès, eux, viennent bien de `massifs_legende()`** | `dev-back-cms`, point 1 |
> | **R-3** | **La liste de crochets de classe du §10 était aspirationnelle** : neuf noms ne correspondaient pas au balisage livré. **Le balisage réel fait foi**, il est reproduit au §10 bis. Aucun renommage n'est demandé à qui que ce soit | `dev-ux-cms`, point 1 |
> | **R-4** | Préchargement des polices sur **`admin_print_styles-{hook}`**, pas `admin_head` : dans `admin-header.php`, `admin_print_styles` s'exécute **avant** `admin_head`, donc avant le `<link>` de la feuille | `dev-front-cms`, réserve n° 6 |
> | **R-5** | **`:where()` du §9.1 de MASTER est remplacé par `:is()`** dans `wp-admin` : `:where()` vaut zéro de spécificité et perd contre `a:focus` et `input[type="radio"]:focus` du cœur (0,1,1). `:is()` monte à 0,3,0 sans `!important` ni sélecteur d'ID. Valeurs et jetons inchangés. **Déviation D-9** | `dev-ux-cms`, point 3 |
> | **R-6** | La dégradation `:has()` décrite au §10 est **inexacte** : le liseré 4 px est lui-même peint par `:has(:checked)` et disparaît avec lui. **Seul le point du radio natif subsiste** — ce qui reste suffisant, et c'est précisément pourquoi le contrôle natif n'est jamais masqué | `dev-ux-cms`, point 4 |
> | **R-7** | **D-5 est étendue** : la paire segmentée reste empilée **jusqu'à `--bp-l`**, pas seulement sous `--bp-s`. Mesure : à 900 px il reste ≈ 62 px de libellé et `Accès au massif autorisé` s'empile sur **quatre** lignes. C'est l'argument de l'arbitrage 9 du contrat #22, une piste plus loin. D-5 n'imposait l'empilement que sous `--bp-s` et n'interdit rien au-dessus | `dev-ux-cms`, point 2 |
>
> **Deux points laissés ouverts, sans blocage** : le repère n'est **pas** posé sur la bande de tête du
> portail — ce serait un **huitième emplacement** dans une liste que le §3.2 déclare **fermée**, et D-4 ne
> le justifie que par les emplacements 6 et 7. À amender formellement par `lead-design-cms` s'il est
> voulu. Et `massifs_publication_modele()` **mémoïse** son résultat : le §3 la disait destructrice, ce qui
> aurait fait consommer le compte rendu par le filtre `admin_title` **avant** le rendu de la page, tuant
> silencieusement le mécanisme d'annonce n° 2 du §5. `dev-back-cms` l'a mémoïsée **et** rendu le dépôt
> non destructif (transient nominatif, 15 min) : un `F5` n'efface plus le récapitulatif.

**Périmètre d'écriture de la chaîne**, strict, arbre de travail partagé sans isolation :

- `wp-content/plugins/massifs-core/includes/admin/ecran-publication/**`
- `wp-content/plugins/massifs-core/includes/rest/portail/publication/**`
- `wp-content/plugins/massifs-core/assets/css/ecran-publication.css` — **[R-9]**, ce seul fichier
- `docs/contracts/issue-14.md`

Aucun fichier du thème. Aucun `massifs-core.php`. Aucun `design-system/MASTER.md`. **Aucun `docker/**`.**
**Jamais** `includes/security/roles|auth/**`, `includes/admin/historique/**`,
`includes/rest/portail/historique/**` (chaînes #13 et #15, en cours en parallèle).
**Jamais** `includes/rest/portail/module.php` — fichier de niveau `portail`, hors des deux empreintes.
**Jamais** `massifs-core/assets/css/historique.css` — même répertoire que notre feuille depuis [R-9],
mais il appartient à la chaîne #15.

> **RÉVISION 2 — 16 août 2026, après le premier aller-retour de `test-integration-cms`.**
> Une seule correction, **[R-9]**, mais elle porte un défaut **bloquant** que la chaîne n'aurait jamais
> vu sans un œil sur l'écran : la feuille de style de l'écran était servie en **403** et l'écran était
> rendu **nu**. Cause, remède et règle générale : voir le §10, encadré [R-9]. Le périmètre d'écriture
> ci-dessus gagne un chemin, hors de `includes/`.

---

## 0. Ce que cette issue ne fait pas, et pourquoi c'est écrit ici

| Non fait | Raison |
|---|---|
| Aucune couche de cache, **aucun abonné à `massifs_statuts_publies`** | Vérifié sur tout `wp-content/` : zéro `set_transient` de statut, zéro `wp_cache_set`, pas d'`advanced-cache.php`. Un abonné vidant un cache inexistant est du **code mort** qui donnerait l'illusion que le §10 est traité. La responsabilité de #14 se limite à **garantir l'émission correcte du signal** (I-8). Le §10 est escaladé comme issue `perf` |
| Aucun relevé de fraîcheur | `Fraicheur` mesure `RegistreReleves::SOURCE_PREFECTURE` (constante vérifiée, l. 37). Y écrire affirmerait que la préfecture a été interrogée alors qu'elle ne l'a pas été — violation frontale du §4.2. `massifs_enregistrer_releve_reussi()` est **interdite** dans cette empreinte |
| Aucun bandeau de péremption sur l'écran | Voir arbitrage **A-9** |
| Aucune saisie ZAPEF | Voir arbitrage **A-8** et question bloquante **Q-1** |
| Aucun rôle, aucune capacité, aucun `remove_menu_page()`, aucune liste d'autorisation de menu | Propriété de **#13**. Sa contrainte A-6 est explicite : *« il n'existe aucune liste d'autorisation de menu, par choix […] ne cherchez pas à vous y inscrire »* |
| Aucune table, aucune option de journal, aucun lien vers l'historique | Propriété de **#15**. La journalisation est déjà gratuite : la table des statuts **est** l'historique (contrat #3 A-8), `massifs_statut_enregistre` est émis par ligne, `auteur_id` toujours renseigné |
| Aucune atténuation Gravatar | **Déjà réglé et vérifié dans le code livré** : `functions.php` l. 467-468 pose `pre_get_avatar_data` et `option_show_avatars` **sans aucune garde de contexte** (contrat #25). `wp-admin` est déjà propre. Ajouter une atténuation locale serait du code mort |

---

## 1. Répartition des fichiers — qui écrit quoi

Un fichier a **un seul** propriétaire. `dev-back-cms` et `dev-front-cms` travaillent en parallèle dans le
même répertoire ; cette table est leur seule protection mutuelle.

### `includes/admin/ecran-publication/`

| Fichier | Propriétaire | Rôle |
|---|---|---|
| `contexte.php` | **back** | Capacité, jetons de jour, garde de disponibilité du domaine |
| `messages.php` | **back** | **Toutes** les chaînes du portail et la traduction des clés d'erreur du domaine |
| `service-publication.php` | **back** | Cœur d'écriture unique. Droits, garde de jour, validation, empreinte, diff, appel batch |
| `rapport.php` | **back** | Dépôt/retrait du compte rendu par-dessus la redirection PRG |
| `modele-ecran.php` | **back** | Construit le modèle de vue (§3) |
| `page.php` | **back** | `add_menu_page`, callback de page, exposition du `hook_suffix` |
| `traitement-post.php` | **back** | Handler `admin-post.php` : gardes, dispatch, PRG |
| `assets.php` | **front** | Enfilage des feuilles, préchargement des polices, filtre `admin_title` |
| `gabarit-ecran.php` | **front** | Gabarit complet de l'écran |
| `gabarit-ligne.php` | **front** | Une ligne de massif |
| `gabarit-statut.php` | **front** | Le fragment `.statut` (marque + libellé) |
| `gabarit-recapitulatif.php` | **front** | Le bloc post-publication |
| ~~`assets/css/ecran-publication.css`~~ | — | **[R-9] Déplacée hors de `includes/`** — voir la ligne suivante et le §10 |
| `module.php` | **back**, **écrit en dernier** | Amorce |

### `includes/rest/portail/publication/`

| Fichier | Propriétaire | Rôle |
|---|---|---|
| `route-publication.php` | **back** | La route, sa permission, ses arguments, sa réponse |
| `module.php` | **back** | Garde d'idempotence, `require_once`, `add_action( 'rest_api_init', … )` |

**Aucun fichier JavaScript n'est livré par cette issue.** L'écran est complet sans lui (contrainte n° 3).

### Deux contraintes de chargement, vérifiées dans le code

1. **Aucune classe, aucun `namespace`, aucun `use`** sous `includes/admin/ecran-publication/`.
   L'autoloader (`massifs-core.php` l. 76) fait `strtolower( implode( '/', $segments ) )` :
   `Massifs\Admin\EcranPublication\X` viserait `includes/admin/ecranpublication/X.php`, qui n'existe
   pas — notre répertoire porte un **tiret**. Fonctions préfixées uniquement, chargées par
   `require_once` **eager** depuis `module.php`. Précédent : `includes/rest/public/` (contrat #8, S-1).
2. **`module.php` admin est écrit EN DERNIER.** C'est le seul chemin découvert par le chargeur. Le créer
   avant ses dépendances ferait inclure un sous-arbre à moitié écrit pendant que #13 et #15 tournent, et
   un `ParseError` de fichier inclus **n'est pas rattrapable** : écran blanc sur tout le site, pour les
   trois chaînes à la fois.

### Inversion de couche, assumée et documentée

`includes/admin/ecran-publication/module.php` se termine par :

```php
$route = MASSIFS_CORE_INCLUDES . 'rest/portail/publication/module.php';
if ( is_file( $route ) ) {
    require_once $route;
}
```

Le chargeur ne descend que d'**un** niveau : `includes/rest/portail/publication/module.php` n'est jamais
découvert, et `includes/rest/portail/module.php` est hors empreinte et partagé avec #15, qui pourrait le
créer au même instant. Vérifié : `massifs_core_charger_modules()` s'exécute **inconditionnellement**
(l. 167, hors `is_admin()`), la couche `admin` est donc chargée y compris sur une requête REST, et
`rest_api_init` se déclenche bien après. La couche `admin` charge la couche `rest` : c'est une inversion,
elle est **écrite en toutes lettres dans le docbloc des deux `module.php`**, avec sa condition de sortie —
le jour où `includes/rest/portail/module.php` existe et scanne ses sous-répertoires, ce `require_once`
devient redondant et se retire ; les gardes d'idempotence
(`MASSIFS_ECRAN_PUBLICATION_VERSION`, `MASSIFS_REST_PORTAIL_PUBLICATION_VERSION`) rendent la transition
sans risque.

---

## 2. Format de fil — **gelé mot pour mot**

C'est ici que les deux plans divergeaient le plus : le back proposait `jour` / `niveau[]` / `publier` xor
`prefixe` / `empreinte`, le front proposait `massifs_jour` (**en date ISO brute**) / `massifs_niveau[]` /
`massifs_intention` / `massifs_nonce`, et **ignorait l'empreinte**. Arbitrage : préfixe `massifs_`
systématique du front, sémantique de jeton relatif du back, champ d'intention unique du front, empreinte
du back.

### Formulaire de publication

```
POST → admin_url( 'admin-post.php' )

action                      = massifs_publier_statuts
massifs_publication_nonce   = wp_nonce_field( 'massifs_publier_statuts', 'massifs_publication_nonce' )
massifs_jour                = aujourd_hui | demain          ← JETON RELATIF, JAMAIS UNE DATE
massifs_empreinte           = <sha1 de l'état du jour édité au moment du rendu>
massifs_niveau[<code>]      = <cle de massifs_legende()['niveaux']>   (clé absente = non renseigné)
massifs_intention           = publier | preremplir_autorise | preremplir_interdit   (porté par le bouton)
```

**`massifs_jour` est un jeton relatif, et c'est une décision de sécurité, pas de style.** Une date brute
rendrait `?massifs_jour=2024-08-02` *concevable* ; le jeton rend la classe d'attaque inexistante. Le jeton
est résolu par `massifs_jour_courant()` / `massifs_jour_suivant()`, **jamais** par `date()`, et **jamais
par l'appelant** : il traverse brut jusqu'au service, qui possède l'unique garde.
`Statuts::RETROACTIVITE_JOURS` (370 jours) n'est **jamais** invoqué : corriger un jour passé n'est pas
dans le §6.

**`massifs_intention` est un champ unique**, porté par le bouton soumis. Le plan back prévoyait deux
champs (`publier` xor `prefixe`) et une garde « exactement un des deux » : le champ unique **supprime la
garde** au lieu de la vérifier.

### [R-1] Deux formulaires frères — et pourquoi ce n'est pas un détail de balisage

**Défaut trouvé par `dev-front-cms` à la première passe.** Avec les trois intentions dans un seul
`<form>` et le pré-remplissage placé au-dessus de la liste (MASTER §7.2), la **soumission implicite** de
HTML — `Entrée` frappé sur un champ — déclenche le **premier bouton de soumission dans l'ordre du
document**, c'est-à-dire **« Tout autoriser »**.

Conséquence : un gestionnaire qui parcourt les 25 massifs au clavier, coche ses niveaux et frappe
`Entrée` — le geste le plus naturel du monde pour « valider » — voit **ses 25 choix remplacés par
« autorisé »**. Rien n'est écrit, et le récapitulatif le dit. Mais il dit aussi « vérifiez, puis
publiez », et le geste suivant d'un gestionnaire pressé est de publier. **Sur un site d'accès aux massifs
en risque incendie, « la touche Entrée a tout passé en accès autorisé » est un défaut de sécurité, pas
une gêne d'ergonomie.** L'issue demande explicitement de « vérifier l'usage complet au clavier ».

**Structure gelée — deux `<form>` FRÈRES, jamais imbriqués** (imbriquer serait du HTML invalide) :

```html
<form method="post" action="{action_url}" class="massifs-preremplissage__formulaire">
  action, massifs_publication_nonce, massifs_jour, massifs_empreinte
  <button name="massifs_intention" value="preremplir_autorise">Tout autoriser</button>
  <button name="massifs_intention" value="preremplir_interdit">Tout interdire</button>
</form>

<form method="post" action="{action_url}" class="massifs-ecran__formulaire">
  action, massifs_publication_nonce, massifs_jour, massifs_empreinte
  … les 25 fieldset et leurs radios massifs_niveau[<code>] …
  <button name="massifs_intention" value="publier">Publier les statuts</button>   ← barre d'action
</form>
```

Le formulaire de pré-remplissage **ne transporte aucun radio**, et c'est correct par construction : le
pré-remplissage **écrase** la saisie partielle (arbitrage A-2), il n'a donc rien à préserver.

Ce que la scission achète, sans rien coûter :

- `Entrée` dans le formulaire principal déclenche **« Publier les statuts »**, son unique bouton de
  soumission — le comportement que tout utilisateur attend d'un formulaire ;
- l'ordre visuel de MASTER §7.2 est **conservé** (pré-remplissage au-dessus de la liste), puisque le
  formulaire frère est rendu avant ;
- la barre d'action reste **dernier enfant** du formulaire principal, donc dernière dans l'ordre de
  tabulation (déviation D-3 intacte) ;
- **aucun bouton fantôme**, aucun `order` CSS qui désynchroniserait l'ordre visuel de l'ordre de focus,
  **aucune ligne de JavaScript**.

**Conséquence côté serveur** : sur une intention `preremplir_*`, `massifs_niveau` est **absent** de la
charge utile. Le handler doit traiter l'absence comme un tableau vide — jamais comme une `saisie_invalide`.
Les deux formulaires portent le **même** `action`, le **même** nonce et le **même** handler : il n'y a
toujours qu'**un seul** chemin d'écriture et **un seul** jeu de gardes.

### Sélecteur de jour — `GET`, jamais un contrôle de formulaire

```
admin.php?page=massifs-publication&massifs_jour=aujourd_hui|demain
```

Deux liens dans un `<nav>`, `aria-current="page"` sur l'actif. Trois raisons, retenues du plan front :
changer de jour est une **navigation**, pas une saisie ; un `<form>` imbriqué dans le `<form>` de
publication est **du HTML invalide** ; et une paire segmentée visuellement identique à celle des statuts
ferait confondre « choisir un jour » et « choisir un niveau ». **Changer de jour n'écrit jamais rien.**

### Redirection PRG

```
admin.php?page=massifs-publication&massifs_jour=<jeton>&massifs_resultat=<jeton_rapport>#massifs-recapitulatif
```

Code **303 See Other**, jamais le 302 par défaut : c'est le seul code qui force un `GET` sur tous les
agents. **Le fragment `#massifs-recapitulatif` fait partie du contrat** : sans lui, le mécanisme
d'annonce du §5 perd son porteur sans JavaScript.

### Slug gelé

`massifs-publication`. C'est une surface de contrat : il apparaît dans l'URL, dans le `hook_suffix` et
dans la redirection.

---

## 3. Fonctions de frontière entre back et front

Trois fonctions, et rien d'autre, traversent la frontière interne de cette issue.

```php
massifs_publication_modele(): array          // back → front. Modèle de vue complet (§4).
massifs_publication_rendre( array $modele ): void   // front. SEUL point d'entrée du rendu.
massifs_publication_hook_suffixe(): string   // back → front. Garde d'enfilage des feuilles.
```

- `massifs_publication_modele()` assainit `$_GET` lui-même (`sanitize_key`), résout le jeton de jour,
  retire le rapport, et retourne le modèle complet. **Jeton absent ou invalide ⇒ `demain`.**
- `massifs_publication_rendre()` **échappe tout** (`esc_html`, `esc_attr`, `esc_url`) et ne rédige, ne
  formate, ne traduit, ne trie, ne compte **aucune** valeur.
- `page.php` appelle `massifs_publication_rendre()` si `function_exists`, sinon `wp_die()` explicite.
  Aucun rendu de repli : un gabarit manquant est un défaut, pas un mode dégradé.

---

## 4. Forme exacte du modèle de vue

**Toutes les clés sont toujours présentes.** Le front n'écrit jamais `isset()` ni `??`. Aucune clé ne vaut
`null` là où une chaîne est attendue : chaîne vide, et le front n'affiche rien. Les valeurs sont
**brutes et non échappées** — l'échappement est en sortie, dans le gabarit.

```php
array(
  'ecran' => array(
    'titre'          => 'Mise à jour des statuts',      // h1 — voir A-11
    'titre_document' => '…',                            // filtre admin_title, voir §5
    'action_url'     => '…/wp-admin/admin-post.php',
    'action_nom'     => 'massifs_publier_statuts',
    'nonce_champ'    => 'massifs_publication_nonce',
    'nonce'          => '…',
    'empreinte'      => 'a1b2c3…',
    'page_url'       => '…/admin.php?page=massifs-publication',
    'slug'           => 'massifs-publication',
  ),

  'jour' => array(
    'jeton'        => 'demain',                  // 'aujourd_hui'|'demain' — LISTE FERMÉE
    'date'         => '2026-08-15',              // informatif, jamais formaté par la vue
    'date_lettres' => 'samedi 15 août 2026',     // DÉJÀ FORMATÉE par massifs_horodatage()
    'choix'        => array(                     // ORDRE IMPOSÉ, toujours 2 entrées
      array( 'jeton'=>'aujourd_hui', 'libelle'=>'Aujourd’hui',
             'date_lettres'=>'vendredi 14 août 2026', 'url'=>'…', 'actif'=>false ),
      array( 'jeton'=>'demain', 'libelle'=>'Demain',
             'date_lettres'=>'samedi 15 août 2026', 'url'=>'…', 'actif'=>true ),
    ),
    'reference_redondante' => false,             // true SSI jeton === 'aujourd_hui' (A-2)
  ),

  'niveaux' => array(                            // options de la paire segmentée, ORDRE = légende
    array(
      'cle'             => 'autorise',
      'libelle'         => 'Accès au massif autorisé',   // VERBATIM §11.4, jamais abrégé
      'classe_marque'   => 'pastille--autorise',         // SUFFIXE DE CLASSE, jamais une couleur
      'motif'           => 'aucun',
      'severite'        => 10, 'rang' => 1, 'total' => 2,
    ),
    array(
      'cle'             => 'interdit',
      'libelle'         => 'Accès au massif interdit',
      'classe_marque'   => 'pastille--interdit',
      'motif'           => 'hachure_croisee',
      'severite'        => 20, 'rang' => 2, 'total' => 2,
    ),
  ),

  'lignes' => array(                             // ORDRE `tri` du référentiel. JAMAIS RETRIÉ.
    array(
      'code'    => 'sainte-victoire',
      'libelle' => 'Sainte-Victoire',
      'ancre'   => 'massif-sainte-victoire',     // cible des liens du récapitulatif
      'champ'   => 'massifs_niveau[sainte-victoire]',   // attribut name PRÊT À L'EMPLOI
      'id_base' => 'massifs-sainte-victoire',    // base des id/for des deux radios

      // Ce qu'il faut COCHER. Distinct de l'état enregistré : voir A-3.
      'valeur_cochee' => '',                     // '' = aucun radio coché

      // Colonne LECTURE SEULE — TOUJOURS le jour courant (A-2)
      'reference' => array(
        'etat'          => 'disponible',         // vocabulaire fermé, §6
        'classe_marque' => 'pastille--autorise',
        'libelle'       => 'Accès au massif autorisé',   // '' hors 'disponible'
        'phrase'        => 'Accès au massif autorisé',   // TOUJOURS non vide
      ),

      // Colonne ÉTAT ENREGISTRÉ du jour ÉDITÉ — c'est elle qui fait VOIR « non renseigné »
      'enregistre' => array(
        'etat'          => 'non_encore_publie',
        'classe_marque' => 'pastille--non-publie',
        'libelle'       => '',
        'phrase'        => 'Statut non encore publié.',
      ),

      'modification' => array(                   // du jour ÉDITÉ, pas du jour courant
        'renseignee'    => false,
        'auteur'        => '',
        'source'        => '',                   // 'recuperation_officielle'|'saisie_manuelle'|''
        'attr_datetime' => '',
        'texte'         => '',
        'phrase'        => 'Aucune modification enregistrée.',
      ),

      'refus' => array(                          // présent seulement après un refus
        'present' => false,
        'id'      => 'massifs-sainte-victoire-erreur',
        'message' => '',
      ),
    ),
  ),

  'preremplissage' => array(
    array( 'valeur'=>'preremplir_autorise', 'libelle'=>'Tout autoriser' ),
    array( 'valeur'=>'preremplir_interdit', 'libelle'=>'Tout interdire' ),
  ),

  'publier'  => array( 'valeur'=>'publier', 'libelle'=>'Publier les statuts' ),  // VERBATIM MASTER §7.2

  'compteur' => array( 'modifies'=>0, 'texte'=>'…' ),

  'recapitulatif' => array(
    'present'            => false,
    'ton'                => 'succes',            // 'succes'|'partiel'|'refus'|'prefixe' — LISTE FERMÉE
    'titre'              => '',
    'resume'             => '',
    'manquants_intitule' => '',
    'manquants'          => array(),             // [ ['code','libelle','ancre'], … ]
    'omission_zapef'     => '',
    'zapef_perdue'       => array(),             // [ ['code','libelle','ancre'], … ] — voir Q-1
    'refus'              => array(),             // [ ['code','libelle','ancre','message'], … ]
  ),

  'bandeaux' => array(),                         // [ ['texte','lien_texte','lien_url'], … ]

  'referentiel_disponible' => true,
  'legende_confirmee'      => true,
  'chaines'                => array( /* §7 */ ),
)
```

### Règle de pré-cochage — opposable

`valeur_cochee` vaut, dans cet ordre :

1. la valeur **postée** (pré-remplissage, ou soumission refusée à réafficher) ;
2. sinon, le `niveau_cle` **enregistré pour le jour édité lui-même**, si `enregistre.etat === 'disponible'` ;
3. sinon `''` — **aucun radio coché**.

**Jamais** la valeur du jour courant reportée sur demain : « repartir des statuts d'aujourd'hui » est
rejeté (institutionnalise « je suppose que rien n'a changé » sur un site dont la règle centrale est de ne
jamais rejouer la veille). La règle 2 n'est pas une supposition : c'est l'état réel du jour qu'on modifie.

---

## 5. Confirmation annoncée aux lecteurs d'écran

Le diagnostic des deux plans converge et il est exact : après une redirection PRG, une région
`aria-live` **présente dès le chargement** n'est pas une *mutation* du point de vue de l'API
d'accessibilité, et NVDA comme JAWS ne l'annoncent pas de façon fiable. Trois mécanismes superposés,
dont **deux fonctionnent sans une ligne de JavaScript** :

1. **`<title>` du document** — porteur principal. Tous les lecteurs d'écran annoncent le titre au
   chargement ; c'est le seul canal universel après une navigation. Filtre `admin_title`, chaîne
   `ecran.titre_document` **fournie par le serveur** (elle contient un compte et une date). Satisfait
   aussi « titres de page uniques » du §8.
2. **Fragment `#massifs-recapitulatif` sur un conteneur `tabindex="-1"`** — porteur secondaire, sans
   JavaScript. Une navigation vers un fragment déplace le point de départ de la navigation séquentielle
   et, sur un élément `tabindex="-1"`, le focus lui-même. C'est le patron des liens d'évitement appliqué
   au retour de POST. Le bloc étant placé juste après le `h1`, le saut ne masque aucun contenu.
3. **`role="status"` sur le même conteneur** — MASTER §7.2 exige `aria-live="polite"` ; `role="status"`
   en est la forme sémantique. **Il n'apporte rien au chargement, et le contrat le dit sans détour.** Il
   est écrit parce qu'il est normatif et parce qu'un enrichissement JavaScript ultérieur le rendrait
   correct sans retoucher le gabarit.

Interdits ici : `role="alert"` (MASTER a tranché pour `polite`), une modale de confirmation (interdite
par §7.2), un `autofocus` (il déplacerait le focus hors du récapitulatif), un JavaScript qui appelle
`.focus()` (un enrichissement n'est jamais un prérequis).

Le récapitulatif est un **bloc persistant et imprimable**, jamais une notification fugitive : c'est la
trace de ce qui vient d'être publié. Il **nomme** les massifs restés sans niveau, chacun en lien vers
`#massif-<code>`, de sorte que le gestionnaire soit à une touche `Entrée` de la ligne à corriger.

---

## 6. États spéciaux

Le vocabulaire est celui de l'enum `EtatStatut`, **fermé à quatre valeurs**, consommé par un `match()`
**sans `default`** (l'ajout d'un cinquième état doit casser bruyamment). Le plan front employait les noms
du gabarit de contrat (`information_indisponible`) : **ils sont remplacés par ceux du domaine.**

| État | Émis par le serveur | Rendu par l'écran |
|---|---|---|
| `disponible` | Une ligne existe pour ce massif et ce jour | Pastille du niveau + libellé officiel verbatim |
| `non_encore_publie` | Jour futur, rien de publié | `pastille--non-publie` + « Statut non encore publié. » **C'est l'état nominal de demain avant publication, jamais une anomalie** |
| `indisponible` | Jour passé/courant sans donnée | `pastille--indisponible` + « Information du jour non disponible. » **Jamais « aucune restriction »** |
| `hors_saison` | Calendrier inactif **et** aucune donnée | `pastille--hors-saison` + « Dispositif estival inactif. » **N'empêche jamais la publication** (A-10) |
| `information_indisponible` | — | **N'existe pas.** Nom du gabarit de contrat, remplacé par `indisponible` |
| `donnee_perimee` | **Hors périmètre de cet écran** | Voir A-9 |
| `couche_effis_indisponible` | Sans objet | Aucune couche EFFIS sur cet écran |

Deux états de page, hors vocabulaire de statut :

- **Référentiel indisponible** : l'écran rend le bandeau serveur **et rien d'autre** — ni liste, ni
  formulaire, ni bouton.
- **Légende non confirmée** (`massifs_legende_est_confirmee() === false`) : mention serveur au-dessus de
  la liste, formulaire fonctionnel.

---

## 7. Chaînes fournies par le serveur

**Le serveur possède les données ET les chaînes.** Le gabarit ne compose, ne paraphrase, n'abrège, ne
corrige et ne traduit **jamais** une chaîne. Toutes vivent dans `messages.php`, en un seul endroit
relisible.

`titre_ecran` · `titre_document` · `sous_titre_jour` · `jours_intitule` · `jour_aujourdhui` ·
`jour_demain` · `jours_avertissement` · `preremplissage_intitule` · `tout_autoriser` · `tout_interdire` ·
`liste_titre` · `etiquette_reference` · `etiquette_enregistre` · `etiquette_niveau` ·
`etiquette_modification` · `compteur.texte` · toutes les chaînes du `recapitulatif` · le libellé de
chaque état hors niveau · le message de chaque refus · les textes de bandeau.

### Chaînes officielles, reproduites verbatim — jamais éditées

| Emploi | Chaîne exacte | Piège |
|---|---|---|
| Option de la paire segmentée | `Accès au massif autorisé` | jamais « Autorisé », jamais « Ouvert » |
| Option de la paire segmentée | `Accès au massif interdit` | jamais « Interdit » seul |
| En-tête / étiquette de niveau | `Niveau d'Accès` | **apostrophe droite U+0027**, majuscule à `Accès` |

**[R-2] Correction — la phrase initiale de ce paragraphe était fausse.** Les **deux libellés d'accès**
viennent bien de `massifs_legende()`. **`Niveau d'Accès` n'y est pas** : la légende n'expose que les deux
libellés d'accès, les deux libellés ZAPEF et `zapef_note`. L'en-tête officiel est donc reproduit
**verbatim (U+0027)** dans `messages.php`, à l'unique endroit où vivent les chaînes — **jamais dans un
gabarit**. Dans tous les cas, le gabarit ne compose aucune de ces trois chaînes.

Le constat A-5 du plan front — `Niveau d'Accès` est codé en dur dans `templates/parts/liste-statuts.php`
l. 353 du thème — **ne doit pas être reproduit ici** ; ce fichier est hors empreinte et n'est pas corrigé
par cette chaîne.

### Chaînes normatives reprises de MASTER

`Publier les statuts` (§7.2) · `Publication impossible : aucun statut modifié.` (§9.2). Servies par le
serveur comme toutes les autres : un seul propriétaire vaut mieux que deux.

### Traduction des clés d'erreur du domaine

Aucune clé n'est **jamais** affichée brute. Voix active, impératif d'action, sans « Oups », sans
« Désolé », sans « Une erreur est survenue » (MASTER §11.1 règle 3).

| Clé | Phrase servie |
|---|---|
| `niveau_inconnu` | « Choisissez un niveau pour chaque massif modifié. » |
| `massif_inconnu` / `massif_code_invalide` | « {Massif} ne figure pas au référentiel. Rechargez l'écran, puis publiez à nouveau. » |
| `jour_refuse` | « Le jour a changé depuis l'ouverture de l'écran. Vérifiez la colonne, puis publiez à nouveau. » |
| `jour_validite_invalide` / `jour_validite_hors_horizon` | « Publiez les statuts d'aujourd'hui ou de demain. » |
| `auteur_requis` / `auteur_interdit` | « Reconnectez-vous, puis publiez à nouveau. » |
| `source_invalide` / `publie_prefecture_le_invalide` | « Rechargez l'écran, puis publiez à nouveau. » |
| `echec_insertion` | « {Massif} n'a pas été enregistré. Publiez à nouveau ce massif. » |
| `nonce_invalide` | « Votre session a expiré. Rechargez l'écran, refaites votre choix, puis publiez. » |
| `droits_insuffisants` | « Vous n'avez pas le droit de publier les statuts. Demandez l'accès à un administrateur. » |
| `etat_modifie` | « Les statuts de ce jour ont changé depuis l'ouverture de l'écran. Vérifiez la colonne, puis publiez à nouveau. » |
| `aucune_modification` | « Aucun statut n'a changé. Modifiez au moins un niveau, puis publiez. » |
| `saisie_invalide` | « Une valeur transmise n'est pas reconnue. Rechargez l'écran, refaites votre choix, puis publiez. » |
| `referentiel_indisponible` | « Le référentiel des massifs n'est pas disponible. Prévenez un administrateur. » |
| `domaine_indisponible` | « La publication est momentanément impossible. Prévenez un administrateur. » |
| *(inconnue)* | « Rechargez l'écran, puis publiez à nouveau. » + `error_log()` **sous `WP_DEBUG` seulement** |

---

## 8. Le service de publication — cœur d'écriture unique

```php
massifs_publication_publier( array $entree ): array
```

`$entree` : `jour_jeton` (brut, non résolu par l'appelant), `niveaux` (`massif_code => niveau_cle`,
déjà `sanitize_key`'és), `empreinte` (`''` = contrôle renoncé), `origine` (`'ecran'|'rest'`, informatif,
**ne relâche jamais une garde**).

Retour : `publie`, `jour_jeton`, `jour_validite`, `ecrits`, `inchanges`, `manquants`, `zapef_perdue`,
`refuses`, `erreurs`, `empreinte_apres`, `publie_le`.

**Le handler POST et le callback REST traversent exactement ce service.** Une seule validation, une seule
garde de jour, un seul diff, un seul appel batch. Deux chemins d'écriture avec deux validations, ce serait
deux endroits où oublier une garde et une divergence garantie à la première évolution.

| # | Invariant |
|---|---|
| **I-1** | **Assertion de droits en dernier recours** : `current_user_can( 'massifs_publier_statuts' )` **et** `get_current_user_id() > 0`. Ce n'est pas un substitut aux gardes des appelants : c'est le filet qui empêche un troisième appelant futur d'écrire sans droit |
| **I-2** | **Disponibilité du domaine** : les 8 fonctions requises, sinon `domaine_indisponible` |
| **I-3** | **Garde de jour, unique, évaluée MAINTENANT** contre `{massifs_jour_courant(), massifs_jour_suivant()}`. Aucune date brute acceptée nulle part. `RETROACTIVITE_JOURS` jamais invoqué |
| **I-4** | **Référentiel** : `massifs_referentiel()` actifs seuls. Un massif retiré n'est jamais publiable |
| **I-5** | **Validation locale, tout-ou-rien** : une clé hors référentiel ou une valeur hors légende ⇒ **aucune écriture** + `saisie_invalide`. Les radios contraignent les valeurs : une valeur hors liste est une soumission forgée, pas une publication partielle légitime |
| **I-6** | **Empreinte** : fournie et divergente ⇒ `etat_modifie`, zéro écriture |
| **I-7** | **Diff** : un code est écrit **si et seulement si** `etat !== 'disponible'` **ou** `niveau.cle !== valeur soumise`. Les autres vont dans `inchanges` et **ne sont pas transmis au domaine** |
| **I-8** | **Un seul appel batch** : `massifs_enregistrer_statuts()` (PLURIEL), **jamais** une boucle sur le singulier. Seul le pluriel émet `massifs_statuts_publies` (`api.php` l. 487-497). Delta vide ⇒ **aucun appel** |
| **I-9** | **Charge utile figée** : `massif_code`, `jour_validite`, `source => 'saisie_manuelle'`, `auteur_id => get_current_user_id()`, `niveau_cle`, `zapef_cle => null`. **Pas** de `publie_prefecture_le`, **pas** de `niveau_source_brut`, **pas** de `procedure_source` |
| **I-10** | **Aucun relevé de fraîcheur** |
| **I-11** | **Aucun abonné au cache, aucun hook nouveau.** Ni `do_action`, ni `apply_filters`, ni filtre site-wide |
| **I-12** | **`manquants`** = codes actifs sans niveau soumis **et** dont l'état du jour édité n'est pas `disponible`. **Nommés**, jamais complétés automatiquement |
| **I-13** | Le service **ne redirige pas, n'échappe rien, n'émet aucun octet.** Il retourne un tableau |

### L'invariant de correction — pourquoi le diff n'est pas une optimisation

Le modèle est en **insertion pure** et la lecture prend `MAX(id)` (`Depot::selectionner_jour()` l. 246).
Republier les 25 massifs à chaque fois écraserait, en lecture, des lignes `recuperation_officielle` par
des lignes `saisie_manuelle` attribuées au gestionnaire **alors qu'il n'a rien changé**. La vérité de la
source serait détruite dans l'historique, **définitivement**. C'est un invariant de correction.

### Empreinte et concurrence

```
empreinte = sha1( jour . '|' . implode(';', "code:etat:niveau_cle" pour chaque massif actif, ordre `tri`) )
```

Recalculée au rendu et à la soumission ; ne stocke rien, n'ajoute aucune requête (l'état courant est déjà
lu pour le diff).

**Décision : ne pas verrouiller, détecter.** Le comportement du modèle est « dernière écriture gagnante
par (massif, jour) », et aucune ligne n'est perdue. Le vrai danger n'est pas l'écrasement, c'est le
**silence** : si A choisit « interdit » pour un massif que B vient de passer à « interdit », le diff de A
conclut « inchangé » et A repart en croyant avoir publié ce qu'il voit. L'empreinte optimiste transforme
cette divergence silencieuse en refus explicite, sans verrou, sans état à nettoyer, sans délai
d'expiration à inventer. **Effet de bord souhaitable** : si le cron préfectoral écrit à 17 h pendant que
l'écran est ouvert, la publication est refusée — la préfecture vient de publier, le gestionnaire doit le
voir avant d'écraser.

### Bascule de minuit

Écran ouvert le 14 à 23 h 58 sur `massifs_jour=demain` (= le 15), soumis le 15 à 00 h 01 : le jeton
résout désormais le **16**. Le service refuse (`jour_refuse`), **zéro écriture**, PRG, réaffichage avec
les choix de l'opérateur intacts. Jamais d'écriture sur le 16 pour une saisie destinée au 15.

---

## 9. Routes REST

```
POST /wp-json/massifs-portail/v1/publication
```

**Namespace distinct, et c'est une contrainte, pas un goût.** L'invariant **I-11 du contrat #8** gèle
« aucune route en écriture dans `massifs/v1` », la sonde de recette attend `POST /massifs/v1/statuts → 405`,
et le point Q2 du même contrat impose une **égalité sur liste exacte** des routes de `massifs/v1`.
Enregistrer une route d'écriture dans `massifs/v1` casserait les trois.

| Propriété | Valeur |
|---|---|
| `methods` | `WP_REST_Server::CREATABLE` (`POST` seul) |
| `permission_callback` | `massifs_rest_portail_publication_permission` — vérification **explicite** de `current_user_can( 'massifs_publier_statuts' )`. **Jamais `__return_true`, jamais `is_user_logged_in()` seul, jamais de repli `manage_options`** |
| Authentification | Celle du cœur, aucune ajoutée : cookie + `X-WP-Nonce`, ou mot de passe d'application sur HTTPS. **Ne jamais réimplémenter le nonce REST** (interdit 5 de #13) |
| `schema` | Aucun (cohérent avec #8, A-8) |
| En-têtes | `Cache-Control: no-store` sur notre propre réponse, **jamais un filtre site-wide** |

**Arguments** — `jour` (`enum ['aujourd_hui','demain']`, requis, **aucun `default`** : un défaut
laisserait publier demain à un appelant qui pensait publier aujourd'hui) · `niveaux` (`object`, requis,
`sanitize_key` sur clés **et** valeurs, refus au-delà de 100 entrées) · `empreinte` (`string`, facultatif).

### Interaction avec le garde global de #13 — vérifiée, et elle change la surface observable

Le contrat #13 pose un garde global sur `rest_request_before_callbacks` qui teste le préfixe **`massifs`**,
pas `massifs/v1`, et son texte anticipe explicitement notre cas : *« Si #14 ou #15 choisit
`massifs-portail/v1`, le garde mord quand même. »* Il laisse passer `GET`/`HEAD`/`OPTIONS` (§5.4 open data)
et exige, pour toute autre méthode, `is_user_logged_in()` **et** au moins une des trois capacités
`massifs_*` **et** la 2FA en règle.

`rest_request_before_callbacks` s'exécute **avant** `permission_callback`. Conséquences opposables, à
connaître avant d'écrire une sonde de recette :

| Appelant | Code réellement observé | Émis par |
|---|---|---|
| Anonyme | `massifs_ecriture_non_autorisee`, **401** | garde global de #13 |
| Authentifié, aucune capacité `massifs_*` | `massifs_ecriture_non_autorisee`, **403** | garde global de #13 |
| Authentifié, porte `massifs_consulter_historique` mais **pas** `massifs_publier_statuts` | `massifs_portail_droits_insuffisants`, **403** | **notre** `permission_callback` |
| Compte suspendu | **403** | résolveur `user_has_cap` de #13, **sans code dédié chez nous** |

**Notre `permission_callback` est donc load-bearing, pas redondant** : le garde global accepte *n'importe
laquelle* des trois capacités, et seul notre callback exige la bonne. L'interdit 4 de #13 — « ne jamais
déclarer une route REST sans `permission_callback` réel en comptant sur le garde global » — est respecté.

**Erreurs propres** : `massifs_portail_droits_insuffisants` (403) · `massifs_portail_jour_refuse` (400) ·
`massifs_portail_saisie_invalide` (400) · `massifs_portail_aucune_modification` (409) ·
`massifs_portail_etat_modifie` (409) · `massifs_portail_referentiel_indisponible` (503) ·
`massifs_portail_domaine_indisponible` (503). **Le message d'une exception ne voyage jamais** : code
stable + phrase fixe, détail en `error_log()` sous `WP_DEBUG`.

**Ce que cette route coûte, dit honnêtement** : elle est **morte pour l'écran**, qui poste en HTML sans
JavaScript. Elle ajoute un second point d'entrée sur l'unique chemin d'écriture du produit et un second
namespace à tenir hors des caches et des sondes publiques. Le risque est intégralement contenu par le
fait qu'elle traverse **exactement** le même `massifs_publication_publier()`. Voir A-1.

---

## 10. Rendu, accessibilité et CSS

### Structure — `<fieldset>` par massif, pas `<table>`

**Déviation assumée à MASTER §7.2 (« un tableau, une ligne par massif »).** Un `<ul>` de `<li>`, chacun
portant un `<fieldset>` dont le `<legend>` est le nom du massif.

L'argument est décisif et vient du contrat #28, qui a mesuré le problème sur la liste publique : dans un
`<table>`, un groupe de radios logé dans un `<td>` n'a **aucun nom accessible propre**, et le seul moyen
de lui en donner un est l'association en-tête ↔ cellule — que #28 déclare explicitement **« rendue
possible », jamais « rétablie »**, faute d'outil pour la mesurer. Sur un écran d'**écriture**, la faute
équivalente est pire que sur la liste publique : entendre « Accès au massif interdit » **sans savoir de
quel massif**, et le publier. Un `<legend>` produit ce nom sans intermédiaire, dans tous les moteurs.
**Sur une donnée de sécurité, on ne place pas la désambiguïsation du massif sur un mécanisme que personne
n'a mesuré.**

Bénéfice second : le repli à 360 px ne coûte plus la machinerie de #28 (déport hors cadre du `thead`,
`content: attr()`, garde `@media screen`, invariants I-8 et I-11 à I-15). Un `<fieldset>` se replie en
changeant `grid-template-columns`. Les colonnes alignées voulues par MASTER sont obtenues sans `<table>` :
même `grid-template-columns` en unités `fr` sur chaque `<fieldset>` — des ratios sans unité, hors du champ
du §12.

Le motif **S-2** déposé par #28 — « porter l'étiquette dans le DOM plutôt qu'en contenu généré » — est
appliqué **du premier coup** : chaque donnée en lecture seule porte son étiquette dans un `<span>` réel,
**jamais un `::before`**. Nous ne recréons pas la dette.

### Feuilles chargées — liste fermée, corrigée

`wp-admin` ne charge pas la feuille du thème. Recopier les jetons côté extension les ferait dériver de
`MASTER.md` au premier ajustement — et surtout, **le liseré 2 px et le motif des pastilles sont le
mécanisme qui porte la conformité AA des statuts** (§10.2 : le vert sur rouge ne contraste qu'à 1,48:1
sans lui). Dupliquer ce mécanisme, c'est créer une divergence silencieuse sur un encodage de sécurité.
On enfile donc depuis le thème, chaque fichier sous garde `is_readable( get_theme_file_path( … ) )` :

| Poignée | Fichier du thème | Dépendances |
|---|---|---|
| `massifs-admin-fonts` | `assets/fonts/fonts.css` | — |
| `massifs-admin-tokens` | `assets/css/tokens.css` | — |
| `massifs-admin-composants` | `assets/css/composants.css` | tokens |
| `massifs-admin-print` | `assets/css/print.css` (`media="print"`) | tokens, composants |
| `massifs-ecran-publication` | **[R-9]** *notre* `massifs-core/assets/css/ecran-publication.css` — **hors de `includes/`** | les quatre ci-dessus |

> **[R-9] La feuille de l'écran ne peut pas vivre sous `includes/`, et ce n'est pas un détail de rangement.**
> Défaut **bloquant** trouvé par `test-integration-cms`, invisible à toute relecture de code.
> `docker/wordpress/plugins-guard.conf` refuse le sous-arbre `includes/` **à n'importe quelle profondeur**
> sous `wp-content/plugins/`, **pour tout fichier quelle que soit son extension** — pas seulement les
> `.php`. Une feuille servie depuis `includes/` répond donc **403**, et l'écran est rendu **nu** : radios
> empilés, pas de barre d'action, `document.styleSheets` → `regles: 0`.
>
> **Rien dans le code ne le trahissait** : la poignée `wp_enqueue_style()` était correcte, le `<link>`
> bien présent dans le `<head>`, aucune erreur PHP, et une requête refusée n'apparaît pas comme une
> requête « en échec » pour un pilote de navigateur. Le seul symptôme était **à l'écran**.
>
> **Emplacement gelé** : `wp-content/plugins/massifs-core/assets/css/ecran-publication.css`, sondé à
> **404** avant déplacement — donc autorisé. **Le contenu de la feuille n'a pas changé d'un octet** : elle
> ne contient ni `url()`, ni `@import`, ni `@font-face`, il n'y avait aucun chemin relatif à corriger.
>
> **Le garde-fou n'est pas élargi, et ne doit jamais l'être** : il prescrit lui-même ce remède —
> « Un asset de l'extension qui buterait sur ce refus se corrige en le DÉPLAÇANT. Élargir le grant à
> `plugins/` rouvrirait le sous-arbre dont l'issue #20 a fait un invariant opposable. » Le garde-fou est
> **correct** ; c'était l'emplacement du fichier qui ne l'était pas. `docker/**` reste hors empreinte.
>
> **Règle générale à retenir pour toute chaîne suivante** : sous `wp-content/plugins/`, tout ce qui est
> destiné au **navigateur** — CSS, JS, images, polices — vit sous `massifs-core/assets/` ou
> `massifs-core/data/`, **jamais** sous `includes/`, qui est réservé au code que seul PHP charge.
>
> `wp-content/plugins/massifs-core/assets/css/` est **partagé** : la chaîne #15 y a déposé
> `historique.css` pour le même défaut, au même moment. Fichiers distincts, aucune collision.

> **Correction d'une erreur de fait du plan back.** Il prévoyait `assets/css/polices.css`, **qui n'existe
> pas**. Le fichier réel est `assets/fonts/fonts.css` (`functions.php` l. 201). Sous une garde
> `is_file()`, un chemin faux **échoue en silence** : l'écran serait rendu sans polices et sans que
> personne en connaisse la cause.

Enfilage **uniquement sur `massifs_publication_hook_suffixe()`**, jamais sur `admin_enqueue_scripts`
global. Préchargement des deux `woff2` en `admin_head`, **avec `crossorigin` même en même origine** et
**sans paramètre de version**, pour que l'URL soit identique à celle demandée par `fonts.css` — sans quoi
la police est téléchargée deux fois (précédent `functions.php` l. 255-259).

**`layout.css` n'est pas enfilée** : elle style `body`, `a`, `h1,h2,h3` et les bandes, et se battrait avec
`wp-admin`. Elle porte pourtant deux choses nécessaires, reprises **scopées** dans notre feuille :

- le `box-sizing: border-box` global dont `.pastille` dépend **sémantiquement** (contrat #22 : sans lui,
  `.pastille` mesure 30 × 20 au lieu de 26 × 16), reposé sur `.massifs-ecran, .massifs-ecran *,
  .massifs-ecran *::before, .massifs-ecran *::after` ;
- **`.repere`**, l'élément de signature, reproduit **verbatim depuis MASTER §3.1** et scopé — déviation
  **D-4**, parce que ne pas le rendre violerait §3.2 emplacements 6 et 7.

### Dompter `wp-admin`

**Aucun `!important`, aucun sélecteur d'ID du cœur** (`#adminmenumain`, `#wpadminbar` : ce serait sortir
du périmètre et parier sur des identifiants de cœur). La spécificité de `wp-admin` est basse —
`input[type="radio"]` de `forms.css` vaut 0,1,1 ; notre `.massifs-ecran input[type="radio"]` vaut 0,2,1 et
gagne sans artifice. Un bloc de remise à zéro **explicite et énumérée** — jamais un `all: unset`, jamais
un reset global — porte sur les seuls éléments que nous émettons, chaque propriété étant commentée avec
la règle de `wp-admin` qu'elle neutralise.

### Paire segmentée

- **Le radio natif reste visible.** Aucun `opacity: 0`, aucun `appearance: none`, aucun déport :
  `forced-colors` fonctionne alors nativement, l'état coché porte **trois** marqueurs non chromatiques
  (point natif + liseré 4 px + repère), et l'interdit 7 de #28 n'a pas à être discuté.
- `<label>` = la cible, `min-block-size: var(--cible-min)` (44 px, §9.3), pleine largeur sous `--bp-s`.
- Liseré au repos `var(--bord-moyen)` (= `--c-charbon` 2 px de §7.2) sur la **boîte d'option** — l'aplat,
  lui, garde `.pastille` et son `var(--statut-lisere-epaisseur) solid var(--statut-lisere)`.
- Sélectionné : `var(--bord-fort)` + `border-color: var(--c-mistral-nuit)` + repère. **Zéro littéral.**
  La gouttière du repère est **réservée en permanence** sur les deux options, sinon cocher décalerait la
  ligne. Peinture pilotée par `:has(:checked)` ; là où `:has()` manque, le liseré 4 px et le point natif
  portent seuls l'état.
- Focus : spécification §9.1 reprise **telle quelle**.
- **Jamais la couleur seule** : pastille + motif + libellé officiel **posé à côté de l'aplat, jamais
  dessus**. **Aucun état d'interaction ne modifie la teinte d'un aplat de statut** (§9.2, règle absolue).
- Sous `--bp-s`, les deux options passent **empilées pleine largeur**, pas côte à côte (déviation **D-5**,
  précédent mesuré : contrat #22 arbitrage 9, où `Accès à la ZAPEF* interdite` empilait trois lignes sur
  ≈ 140 px). §10.6 règle 6 — aucun libellé tronqué, aucun défilement horizontal — l'emporte sur un croquis.

### Barre d'action — `sticky`, pas `fixed`

Déviation **D-3** à la lettre de MASTER §7.1. Elle résout les trois défauts d'un coup : elle **cesse de
flotter à la fin de son conteneur**, donc ne masque jamais le dernier massif (une barre `fixed`
obligerait à réserver une hauteur qu'on ne connaît pas, puisqu'elle varie avec la taille de police de
l'utilisateur) ; elle est **dernier enfant du formulaire**, donc dernière dans l'ordre de tabulation et
jamais un piège ; et **à 200 % de zoom elle défile simplement** au lieu de couvrir un tiers de la fenêtre,
ce qui évite une requête média de hauteur — donc un littéral de longueur sans source dans MASTER.
Complément : `padding-block-end: var(--esp-4xl)` sur la liste et `scroll-padding-block-end` sur `html`,
pour qu'un `Tab` vers les dernières lignes les amène **au-dessus** de la barre.

### Plan de titres

Un `h1` unique, deux `h2` au maximum (récapitulatif, liste), **aucun `h3`**, **aucun titre par massif** :
25 `h3` transformeraient la navigation par titres en bruit, alors que le `<ul>` donne déjà « 7 sur 25 » et
que chaque groupe porte son nom par son `<legend>`.

`<hr class="wp-header-end">` est **obligatoire** : sans lui, WordPress déplace les notices
d'administration à un endroit imprévisible dans notre balisage.

### Mode dégradé, assumé

Si le thème actif n'est pas `massifs`, les gardes ne trouvent rien, les `var(--…)` restent non résolues et
les déclarations concernées deviennent invalides au calcul : l'écran retombe sur le rendu natif de
`wp-admin` et **reste intégralement utilisable** — radios natives, `fieldset`/`legend` natifs, structure
et libellés intacts. **Aucune valeur de repli littérale n'est écrite** : un `var(--c-charbon, #1A1C19)`
serait exactement le défaut bloquant du §4.1.d règle 6.

---

## 10 bis. [R-3] Crochets de classe réellement livrés — le balisage fait foi

La liste de crochets du §10 était **aspirationnelle** : elle a été écrite au gel, avant tout code. Neuf
noms ne correspondent pas au balisage livré, et `dev-ux-cms` a **eu raison** de suivre le balisage réel
plutôt que le contrat. **Aucun renommage n'est demandé** : renommer maintenant casserait une feuille
mesurée en navigateur pour aligner une liste écrite à l'aveugle.

| Contrat (§10, périmé) | Livré, et opposable |
|---|---|
| `massifs-recap*` | `massifs-recapitulatif*` |
| `massifs-jours__entree` | `massifs-jours__lien` |
| `massifs-ligne__champ` | `massifs-ligne__donnee` |
| `massifs-ligne__groupe` | le `fieldset.massifs-ligne` lui-même |
| `massifs-segmente`, `__options` | `massifs-segmentee`, **sans** `__options` |
| `massifs-barre` | `massifs-barre-action` |
| `massifs-bouton--primaire` | `massifs-bouton--principal` |
| `massifs-ecran__entete` | **n'existe pas** — le `h1` est enfant direct de `.massifs-ecran` |
| — | **en plus** : `__sous-titre`, `__formulaire`, `__bandeaux`, `__valeur`, `__instant`, `__libelle`, `__date`, `__message` |

**Ordre du DOM réellement livré**, garanti par le gabarit et **supposé par la feuille de style** :

```
.wrap.massifs-ecran
  └ h1.massifs-ecran__titre
  └ p.massifs-ecran__sous-titre          ← bande de tête (voir la garde ci-dessous)
  └ hr.wp-header-end                      ← obligatoire
  └ .massifs-recapitulatif
  └ .massifs-ecran__bandeaux
  └ nav.massifs-jours                     ┐
  └ form.massifs-preremplissage__formulaire│ sous `referentiel_disponible`
  └ form.massifs-ecran__formulaire        ┘
```

> **[R-8] Correction de la révision 1 elle-même.** La première rédaction de ce §10 bis plaçait
> `.massifs-ecran__sous-titre` **après** `.massifs-ecran__bandeaux`. C'est faux : le gabarit le rend
> **juste après le `h1`, avant `hr.wp-header-end`**, et la feuille de `dev-ux-cms` suppose ce nouvel
> ordre. Rendu plus bas, le jour édité tombait à ≈ 400 px sous le `h1` à 360 px, hors de la bande de tête
> à laquelle il appartient.
>
> **Nuance à ne pas perdre** : le sous-titre est dans la bande de tête **mais reste sous la garde
> `referentiel_disponible`** (et sous une garde de chaîne non vide). Le §6 exige qu'un référentiel
> indisponible fasse rendre le bandeau **« et rien d'autre »** ; annoncer un jour à publier quand rien
> n'est publiable serait un mensonge de la bande de tête. Les deux règles tiennent ensemble, et le
> gabarit porte cette raison en commentaire à l'endroit exact.

**Deux crochets sont porteurs, pas décoratifs** :

- `.massifs-ligne__donnee--reference` est **load-bearing pour le CSS** : la feuille s'en sert dans un
  `:not(:has(…))` pour passer à trois pistes quand la colonne de référence est repliée
  (`jour.reference_redondante === true`). **Ne pas le retirer.**
- Les modificateurs `massifs-recapitulatif--succes|--partiel|--refus|--prefixe` sont **volontairement non
  peints** : §2.1 interdit la couleur sémantique. Ils restent des crochets ; **le ton ne doit jamais être
  distingué par la seule couleur**, il l'est par le titre et le texte.

## 11. Interdits

1. Le thème n'est **jamais** modifié par cette chaîne. Aucun fichier hors de l'empreinte du §0.
2. L'extension n'émet **aucun HTML de présentation publique**. Les écrans de `wp-admin` sont
   explicitement le domaine de l'extension (rappel de frontière n° 10 du contrat #13).
3. Le gabarit n'appelle **jamais** `massifs_enregistrer_statut[s]()`, `massifs_enregistrer_releve_reussi()`,
   une classe `Massifs\`, une fonction d'ingestion, ni `$wpdb`.
4. Le gabarit ne vérifie **jamais** lui-même le nonce ou la capacité — c'est le back.
5. Le gabarit ne calcule **jamais** une règle métier : saison, péremption, « ce statut a-t-il changé »,
   « ce jour est-il publiable ».
6. Le gabarit ne compose **jamais** une date ni une heure. `date_lettres`, `attr_datetime`, `texte`
   voyagent déjà formatés par `massifs_horodatage()`. Jamais `date()`, jamais `current_time()`.
7. Le gabarit ne compose, ne paraphrase, n'abrège et ne corrige **jamais** un libellé officiel (§11.4).
8. Le gabarit ne traduit **jamais** un code d'erreur en phrase française.
9. Le gabarit ne trie, ne filtre et ne regroupe **jamais** `lignes` ni `niveaux`.
10. Aucune valeur hexadécimale, aucune durée, aucune taille de police, **aucune custom property définie
    hors `tokens.css`** (§12). Seuls des **noms de jetons** et des **suffixes de classe** traversent la
    frontière — jamais un pigment.
11. Aucun bouton `disabled` ni `aria-disabled` (§9.2). L'action reste focusable et explique pourquoi.
12. Aucune information ne dépend de JavaScript, du survol, ou d'une modale.
13. Aucun `isset()` ni `??` sur une clé du modèle : toutes sont toujours présentes.
14. Aucune date brute dans `massifs_jour`. Aucun test de rôle (`in_array( 'massifs_gestionnaire', … )`) :
    **on teste la capacité** — l'administrateur la porte sans porter le rôle (#13, interdit 1).
15. Aucune entrée de menu exigeant une capacité hors `massifs_*` (#13, interdit 2).
16. Aucun `rest_authentication_errors`, ni aucun filtre global d'authentification REST (#13, interdit 3) :
    il renverrait 401 sur `GET /massifs/v1/statuts` et casserait l'open data et la carte.
17. Aucun slug d'écran de #15 codé en dur, aucun lien vers l'historique.

---

## 12. Arbitrages

Chaque ligne est un désaccord réel entre les deux plans, ou une correction que j'ai apportée sur pièce.

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| **A-1** | Le `brainstorm-cms` recommandait de **supprimer** la route REST du périmètre ; l'arbitrage de lot de `/lead-CMS` l'exige | **Route maintenue**, dans un namespace **distinct**, traversant le même service | L'arbitrage de lot nomme explicitement l'endpoint et exige qu'il refuse toute écriture non authentifiée ; l'empreinte le contient. L'objection du brainstorm — « deux chemins de validation divergeront » — est **désarmée par le service unique**, pas ignorée. L'objection restante (surface d'écriture créée) est réelle et écrite au §9 |
| **A-2** | Le front demandait (Q-2) quel jour occupe la colonne de lecture seule quand on édite « aujourd'hui » | `reference` est **toujours** le jour courant, plus un drapeau `reference_redondante` | La redondance est un fait de donnée, pas un calcul de vue. Le serveur ne cache pas l'information au gabarit ; le gabarit décide de replier la colonne |
| **A-3** | Le back nommait `edition.niveau_coche`, le front `valeur_affichee`, avec des sémantiques proches mais non identiques | Un seul champ : **`valeur_cochee`**, distinct de `enregistre` | Confondre « ce qu'il faut cocher » et « ce qui est enregistré » ferait mentir l'affichage « non renseigné » ou perdrait le pré-remplissage. Les deux plans avaient raison de les séparer ; ils les nommaient différemment |
| **A-4** | **Format du jour** : jeton relatif (back) contre date ISO brute (front) | **Jeton relatif**, partout | Décision de sécurité gelée en amont. Une date brute rend `?massifs_jour=2024-08-02` concevable. Le front avait planifié en aveugle de cette contrainte |
| **A-5** | **Noms de champs** : les deux plans en proposaient des jeux entièrement disjoints | Préfixe `massifs_` du front + sémantique du back + `massifs_intention` unique | C'est le défaut classique du travail parallèle. Le champ d'intention unique **supprime** la garde « exactement un bouton parmi deux » que le back devait écrire |
| **A-6** | Le front ignorait l'**empreinte** (concurrence optimiste) : son formulaire ne la transportait pas | `massifs_empreinte` **ajouté** aux champs obligatoires du gabarit | Cas d'école du « une garde qu'un côté suppose et que l'autre n'a jamais planifiée ». Sans elle, la détection de concurrence du back est morte |
| **A-7** | **Feuilles à charger** : 2 fichiers (back, dont un **chemin inexistant**) contre 4 (front) | **4 fichiers, chemins corrigés** | `assets/css/polices.css` n'existe pas ; le vrai fichier est `assets/fonts/fonts.css`. Charger `composants.css` évite de dupliquer le mécanisme qui porte la conformité AA des statuts |
| **A-8** | **ZAPEF** : les deux plans convergent sur « pas de saisie », mais le back a découvert une conséquence non énoncée | `zapef_cle => null`, **plus** une liste `zapef_perdue` nommant précisément les massifs concernés | Écrire `null` **n'invente rien** : c'est « pas d'information ». Dériver la ZAPEF serait une invention interdite — `interdit` provient de `level` 3 (ZAPEF autorisée) *comme* de `level` 4 (ZAPEF interdite). La perte est **rendue visible et nominative** plutôt que tue. Voir **Q-1** |
| **A-9** | Le front voulait un bandeau de péremption (`massifs_fraicheur().perimee`) ; le back l'excluait | **Pas de bandeau de fraîcheur sur cet écran** | La fraîcheur mesure le relevé **préfectoral**. L'afficher sur l'écran de publication laisserait croire que publier la remet à zéro — ce qu'elle ne fait pas (I-10). Ce n'est ni dans les tâches de l'issue ni dans MASTER §7.2. Alternative écartée : un bandeau accompagné d'une phrase expliquant qu'il ne se réinitialise pas — cela reviendrait à rédiger une chaîne de domaine non sourcée |
| **A-10** | Publication hors saison | **Autorisée**, avec phrase informative | « La donnée bat le calendrier » (contrat #3, A-4). `hors_saison` signifie « calendrier inactif **et aucune donnée** » : publier écrase donc l'affichage « dispositif inactif ». **Interdire serait inventer une règle** que la préfecture n'énonce pas ; masquer serait malhonnête. On montre le fait, on autorise l'acte, la ligne enregistre l'auteur |
| **A-11** | Libellé du `h1` (front, Q-7) | **« Mise à jour des statuts »**, servi par le serveur | MASTER §7.2 écrit « MASSIFS · Mise à jour des statuts » pour la bande de 56 px. Le préfixe « MASSIFS · » est une identité de chrome que `wp-admin` porte déjà ; le redoubler serait du chrome sur du chrome (déviation **D-7**) |
| **A-12** | Champ « motif de la correction » (front, Q-3) | **Aucun** | Le §6 journalise « qui, quoi, quand, ancienne/nouvelle valeur » — **jamais un motif**. En ajouter un coûterait un champ par ligne et mettrait en danger l'objectif « moins d'une minute », sans source dans le brief |
| **A-13** | `publie_prefecture_le` pour une saisie manuelle (front, Q-5) | **Jamais transmis**, aucun champ à l'écran | Le domaine ne l'exige pas pour `saisie_manuelle` (validation l. 304-321). Une saisie manuelle n'a pas d'instant de publication préfectorale : en inventer un serait affirmer un fait |
| **A-14** | Atténuation Gravatar locale proposée par le front (constat A-4, issue de suivi S-B) | **Rejetée, sans suite** | Vérifié dans le code livré : `functions.php` l. 467-468 pose `pre_get_avatar_data` **et** `option_show_avatars` **sans garde de contexte**, et la recette de #25 prouve `/wp-admin/`, `/wp-admin/profile.php` et `/wp-admin/users.php` propres. L'ajout serait du code mort. **Le constat A-4 du plan front est une fausse alerte** |
| **A-15** | Capacité : le front la déclarait **question bloquante** (Q-1) | **Non bloquante** — `massifs_publier_statuts`, gelée au niveau du lot | Le contrat #13 était déjà gelé au moment de la réconciliation. Sa matrice confirme que **`administrator` porte aussi la capacité** (A-2), ce qui annule le risque « écran invisible pour tout le monde ». La suspension de compte est en outre appliquée **gratuitement** par le résolveur `user_has_cap` de #13 : ne pas écrire de contrôle de suspension redondant |
| **A-16** | Propriétaire de `module.php`, revendiqué par les deux plans | **back**, écrit **en dernier** | Un seul fichier découvert par le chargeur ; le créer tôt exposerait les trois chaînes à un `ParseError` non rattrapable |
| **A-17** | Enfilage : le back le plaçait dans `page.php`, le front dans `assets.php` | `assets.php` (**front**) porte l'enfilage ; `page.php` (**back**) expose `massifs_publication_hook_suffixe()` | Sépare la compétence CSS de la compétence menu sans partager un fichier |
| **A-18** | Vocabulaire des états : le front employait `information_indisponible` | **`indisponible`** — vocabulaire de l'enum `EtatStatut` | Le front reprenait les noms du gabarit de contrat. Le domaine ferme la liste à quatre valeurs, consommées par un `match()` sans `default` |
| **A-19** | Contradiction interne de MASTER sur le repère (front, Q-6) : §3.3 l'interdit sur les champs de formulaire, §7.2 l'impose sur l'option sélectionnée | **§7.2 l'emporte** | Plus spécifique, plus récent, et portant sur ce composant précis. Signalé à `lead-design-cms` : ce contrat ne modifie pas MASTER |
| **A-20** | `recapitulatif['refus']` est rendu **sans intitulé**, alors que `manquants` a `manquants_intitule` et `zapef_perdue` a `omission_zapef` (réserve n° 4 de `dev-front-cms`) | **Asymétrie assumée, livrée telle quelle** | Le gabarit a eu **raison** de ne pas en inventer un : le §7 réserve toute chaîne de portail à `messages.php`, et rédiger un intitulé depuis un gabarit aurait violé l'interdit 8. L'asymétrie est sans conséquence pour l'utilisateur : chaque refus est déjà une **phrase complète et actionnable** (« Choisissez un niveau pour chaque massif modifié. »), précédée du nom du massif et liée à sa ligne — un intitulé de section ne lui ajouterait rien. **Reporté**, non corrigé : ajouter la chaîne est une ligne dans `messages.php` le jour où un intitulé devient utile |
| **A-21** | `legende_confirmee` n'est **consommée par aucun gabarit**, faute de chaîne associée (réserve n° 5 de `dev-front-cms`) | **Clé conservée, non rendue ; l'exigence du §6 est portée par `bandeaux`** | Le §6 demande une « mention serveur au-dessus de la liste » quand `massifs_legende_est_confirmee()` est faux, mais le modèle n'expose qu'un **booléen** — or le rendu n'a pas le droit de composer la phrase (interdit 8). Le porteur correct est `bandeaux`, que le serveur remplit et que le gabarit rend déjà en bloc. **Le drapeau reste au modèle** : il est le crochet d'un futur traitement visuel distinct, et le retirer coûterait plus qu'il ne rapporte. **État réel à connaître pour la revue : la légende du 13 est confirmée** (`massifs_legende_est_confirmee() === true`), le cas ne se produit donc pas aujourd'hui — c'est une garde dormante, pas un défaut visible |
| **A-22** | **Collision inter-chaînes sur le slug du menu parent** : #15 supposait un parent `massifs`, #14 a livré `massifs-publication` | **Arbitrage de lot, prononcé par `/lead-CMS` : le parent est `massifs-publication`** | Tranché **hors de cette chaîne** et déjà commité côté #15 sous forme d'une détection par préfixe `massifs` qui le trouve. Rien n'est modifié ici. **Réserve inscrite pour la revue** : une détection par préfixe rend le parent retenu dépendant de l'ordre de `admin_page_hooks`, donc **non déterministe** si un troisième écran préfixé `massifs` apparaît. À consolider par l'issue de chrome de portail, jamais par un patch silencieux d'un côté ou de l'autre |

### Déviations à MASTER, à enregistrer par le propriétaire du document

`design-system/MASTER.md` est **hors de notre empreinte** : ces déviations sont consignées ici et
escaladées, **jamais écrites dans MASTER par cette chaîne**. Sans enregistrement au §17 de MASTER,
`review-cms` les comptera comme de faux défauts — c'est exactement ce qui est arrivé entre les chaînes
#22 et #21.

| # | Déviation | Section | Raison |
|---|---|---|---|
| **D-1** | `<fieldset>`/`<legend>` par massif au lieu d'un `<table>` | §7.2 | Nom accessible du groupe **garanti** au lieu d'une association en-tête↔cellule que #28 déclare non mesurée ; repli 360 px sans la machinerie ni les invariants I-8/I-11..I-15 |
| **D-2** | Pas de `role="radiogroup"` explicite ; `fieldset` + radios natives | §7.2 | Le **comportement** prescrit (flèches, `Tab` sort du groupe) est natif. Le rôle explicite remplacerait `group` et fragiliserait le nommage par `legend` |
| **D-3** | Barre d'action en `position: sticky`, pas `fixed` | §7.1 | Ne masque pas le dernier massif, ne piège pas le focus, ne casse pas à 200 %, et évite un littéral de hauteur sans source |
| **D-4** | Le bloc `.repere` de §3.1 est reproduit, scopé, dans la feuille de l'extension | §3.1 | `layout.css` est inchargeable dans `wp-admin` ; ne pas rendre le repère violerait §3.2 emplacements 6 et 7 |
| **D-5** | Paire segmentée sur une colonne sous `--bp-s` | §7.2 | Précédent mesuré : contrat #22 arbitrage 9 |
| **D-6** | « Publication impossible : aucun statut modifié. » rendue **après** soumission, pas au repos | §9.2 | Sans JavaScript, l'état « rien de modifié » n'est connu qu'après un aller-retour ; l'afficher au chargement serait **faux** |
| **D-7** | Pas d'en-tête de portail séparé de 56 px avec date de session et déconnexion ; bande interne à notre conteneur | §7.2 | `wp-admin` porte déjà ce chrome |
| **D-8** | L'extension enfile quatre feuilles du thème | aucune section — **précédent nouveau** | Aucun contrat n'autorisait encore ce couplage extension → thème. Ratifié ici, et **borné à une liste fermée de quatre fichiers** |
| **D-9** | **[R-5]** L'anneau de focus s'écrit en `:is()`, pas en `:where()` | §9.1 | `:where()` vaut **zéro** de spécificité : dans `wp-admin` il perd contre `a:focus` et `input[type="radio"]:focus` du cœur (0,1,1), et l'anneau ne s'afficherait pas. `:is()` prend la spécificité de son argument le plus fort et monte à 0,3,0 — **sans `!important`, sans sélecteur d'ID**. Valeurs, jetons et géométrie **inchangés** : c'est le sélecteur qui change, jamais le dessin |
| **D-5 bis** | **[R-7]** La paire segmentée reste empilée **jusqu'à `--bp-l`**, pas seulement sous `--bp-s` | §7.2 | **Mesuré** : dans la piste `Niveau d'Accès` (2/5,5 de la largeur utile), chaque option consomme 74 px de chrome avant le premier caractère ; à 900 px il reste ≈ 62 px de libellé et `Accès au massif autorisé` s'empile sur **quatre** lignes. C'est exactement l'argument de l'arbitrage 9 du contrat #22, une piste plus loin. §10.6 règle 6 — aucun libellé tronqué — l'emporte sur un croquis, et D-5 n'interdisait rien au-dessus de `--bp-s` |

---

## 13. Questions bloquantes remontées au propriétaire

**Q-1 — La correction manuelle d'une ligne officielle efface la ZAPEF publiée par la préfecture.
Est-ce acceptable ?**

Cas concret : la préfecture publie `level` 3 pour demain → ligne `niveau_cle = interdit`,
`zapef_cle = autorise`. Le gestionnaire corrige le massif en `autorise`. Notre ligne porte
`zapef_cle = null`, et `MAX(id)` la rend faisant autorité : **l'information ZAPEF publiée par la
préfecture disparaît de l'affichage public pour ce jour.** Ce n'est ni une invention ni un oubli d'écran,
c'est une perte d'information officielle causée par une correction partielle.

Trois issues, aucune déductible du brief :
**(a)** écrire `null` — la correction manuelle ne prétend rien sur la ZAPEF ;
**(b)** reporter le `zapef_cle` de la ligne précédente — mais une ligne `saisie_manuelle` affirmerait
alors une valeur que le gestionnaire n'a pas saisie ;
**(c)** saisir la ZAPEF à l'écran — double le temps de saisie et met en danger le « < 1 min ».

**Comportement livré, en l'absence d'arbitrage : (a)**, complété par la liste `zapef_perdue` qui **nomme
les massifs concernés** dans le récapitulatif. **La chaîne n'est pas bloquée** parce que (a) n'invente
rien : `null` signifie « pas d'information », ce qui est vrai. Mais la perte est réelle et mérite une
décision du propriétaire.

> Une piste examinée puis **écartée** : dériver `zapef = autorise` quand le niveau saisi est `autorise`,
> au motif que les `level` 1 et 2 produisent tous deux `zapef = autorise`. L'inférence est mathématiquement
> valide sur la table gelée, mais elle reste une **inférence de domaine non ratifiée**, appliquée à une
> saisie qui n'est pas un `level`. Le projet interdit l'invention silencieuse ; `null` est le seul choix
> qui n'affirme rien.

**Q-2 — MASTER §11.3 ne couvre aucune chaîne du portail.** Le §11.3 se présente comme la liste **fermée**
des phrases que le site a le droit de rédiger, et elle est entièrement publique. Cette issue écrit une
vingtaine de chaînes de portail. Ma lecture — que MASTER §7.2 corrobore, puisqu'il rédige lui-même
« Publier les statuts » et « 7 statuts modifiés » — est que **§11.3 borne le public, pas le portail**.
Toutes nos chaînes sont regroupées dans `messages.php` et listées au §7 pour relecture en un seul endroit.
**Arbitrage plus que blocage**, mais il doit être prononcé.

---

## 14. Escalades à l'orchestrateur (hors empreinte de cette chaîne)

1. **§10 — « pages publiques servies en cache, invalidé à chaque publication » n'est satisfait par aucune
   chaîne.** Aucune couche de cache n'existe. Une issue `perf` doit la construire et **s'abonner à
   `massifs_statuts_publies`**, que le domaine émet déjà et que #14 garantit correctement émis.
2. **Enregistrement des dix déviations D-1 à D-9 et D-5 bis au §17 de `design-system/MASTER.md`**, par la
   chaîne propriétaire de ce document. Le précédent #22/#21 montre qu'une déviation non enregistrée
   revient en revue comme un faux défaut. **S'y ajoute une décision de design à prononcer** : le repère
   sur la **bande de tête du portail** serait un **huitième emplacement** dans une liste que le §3.2
   déclare fermée — il n'est **pas** posé, et l'ajouter demanderait un amendement formel de la liste,
   comme la v2.3 a amendé l'emplacement 2 et la v2.4 retiré l'emplacement 5.
3. **Contradiction interne de MASTER** entre §3.3 et §7.2 sur le repère appliqué à une option de
   formulaire (A-19), à trancher par `lead-design-cms`.
4. **Dette de suivi, hors périmètre** : extraire `.repere` et les marques de statut dans une feuille
   consommable par le thème **et** par `wp-admin` (lèverait D-4) ; feuille d'impression d'administration
   masquant le chrome WordPress ; enrichissement JavaScript facultatif (compteur vivant, pré-remplissage
   sans aller-retour), **jamais un prérequis**.
