# Contrat d'interface — Issue #12 — Garantir la fraîcheur et l'honnêteté des données affichées

**Gelé le** 14 août 2026 · **Par** `lead-issue-cms`, chaîne #12 · Épic 4 · domaines `statuts`, `infra`
**Lignes de DoD servies** : §12 (bannière de péremption) · §4.5 du brief (*Fraîcheur et honnêteté des
données*, en entier)
**Statut** : contraignant. Les deux plans amont — `leaddev-back-cms` et `leaddev-front-cms` — ont été
produits **en aveugle l'un de l'autre**. Ce document est le point de réconciliation ; en cas de
divergence entre un plan et ce contrat, **c'est ce contrat qui fait foi**.

**Erratum du 4 septembre 2026 (issue #94)** — la **garde n° 2 du rappel cron** porte un erratum : la
justification de dépendance qu'elle donnait est barrée, avec le verdict à sa suite. Elle était **exacte au
gel** et a été **périmée** par le commit `69c52f4` (#94), qui a repointé `massifs_fraicheur()` sur
`Saison::officielle()` et retiré de `domain/fraicheur` toute mention d'un symbole `Massifs\Domain\Statuts\`.
**Aucune décision n'est rouverte** : aucune clause, aucun invariant, aucune clé, aucune signature ne bouge,
et **la règle opposable de la garde n° 2 est intacte** — le `try`/`catch` reste prescrit, et un rappel cron
ne fait jamais tomber la requête d'un visiteur. Paragraphe d'en-tête ajouté le 5 septembre 2026 à la revue
du lot, pour que l'annotation soit visible dès l'ouverture du document.

> Règle de lecture reprise des contrats #8 et #9 : ce document décrit des **décisions**, pas des
> suggestions. Une divergence constatée en revue est un défaut, pas une variante. Les points marqués
> **`OUVERT`** sont des trous de connaissance assumés — on ne les comble **jamais** par déduction
> (§4.2 du brief).

**Empreinte fichiers de l'issue**, hors de laquelle cette chaîne n'écrit **rien** :

```
wp-content/plugins/massifs-core/includes/ingest/cron/**
wp-content/plugins/massifs-core/includes/security/alertes/**
wp-content/themes/massifs/templates/parts/bandeau-peremption.php
docs/contracts/issue-12.md          (ce fichier)
tests/scenarios/50-… à 59-…         (plage réservée à cette chaîne)
```

Projet **mono-branche**, arbre de travail partagé, aucune isolation. Les chaînes **#10 (météo)** et
**#11 (EFFIS)** écrivent dans le même arbre **pendant** cette chaîne. La disjonction des empreintes est
la seule protection contre l'écrasement mutuel.

---

## 0. Approche retenue, et ce que cette issue n'est pas

**« La veille observe ce que le site AFFICHE, jamais ce que le transport a fait. »**

### 0.1 Constat de conformité — quatre points de la checklist étaient déjà tenus

Vérifié à la lecture du code, pas déduit. Ce constat fait **partie du livrable** : il est verrouillé par
les scénarios `50-…` à `54-…` pour qu'aucun lot ultérieur ne le régresse en silence.

| Point de la checklist | État réel **avant** #12 | Preuve |
|---|---|---|
| 1. Planification quotidienne + nouvelles tentatives | **tenu** | `includes/ingest/prefecture/class-schedule.php` l. 47-59 (`hourly`, `ensure()` idempotent) ; `class-runner.php` l. 42-98 (gardes, anti-rafale 15 min, `DATES_MAX = 2`) ; verrouillé par `tests/scenarios/20-cron-complet.arme.php` l. 28-42 |
| 2. Alerte courriel si tout échoue | **tenu — en mode `automatique` armé SEULEMENT** | `class-notifier.php` (`alert_window_closed`, `alert_rejected`, verrou par date **et** par type) ; trou décrit en §0.2 |
| 3. Indicateur de fraîcheur partout où un statut apparaît | **tenu** | `front-page.php` l. 291-355 (phrase §11.3 complète) ; `liste-statuts.php` l. 168-208 et 379 ; `carte.php` l. 383-402 |
| 4. Bannière automatique au-delà de 24 h | **partiel — un `<p>` gras, pas la bannière §8.3** | `front-page.php` l. 362 ; unique style `layout.css` l. 323-325. Dette **déjà nommée** par le contrat #5 A-4 et le contrat #6 reste-à-faire 5-4 |
| 5. Mode « dispositif inactif » hors saison | **tenu** | `etats-vides.php` l. 157-158 ; `carte.php` l. 245-250. Manque une demi-phrase au `h1` de `front-page.php` l. 185-195, hors empreinte, cause B-1 (§11) |

**Conséquence opposable : #12 ne réécrit rien de tout cela.** Toute proposition de reprendre la
planification préfectorale, ses tentatives ou ses alertes est un défaut, pas une amélioration.

### 0.2 Ce qui n'était PAS tenu — les deux seuls trous, tous deux dans l'empreinte

**Trou n° 1 — en mode `manuel` et en mode désactivé, il n'existe ni cron ni alerte, du tout.**
`Schedule::ensure()` l. 48-52 **désarme** l'évènement quand `Settings::mode() === 'manuel'` **ou**
`Settings::is_disabled()`. `Runner::run_scheduled()` l. 43-51 sort **avant** `surveiller_fenetre()`
dans ces deux cas. Or le brief §4.2 déclare le **repli manuel explicitement acceptable**, et
`Settings::is_disabled()` est vrai en environnement `local`/`development` — **la stack Docker de ce
projet**, alors que `docs/decisions/portee-non-publiee.md` §2 pose « fraîcheur, péremption : **réel** ».
Dans la configuration réellement exploitée, la ligne §4.5 « alerte email si tout échoue » **n'est jamais
atteignable**.

**Trou n° 2 — `Fraicheur::evaluer()` n'est observée par personne.**
`perimee` est calculée (`includes/domain/fraicheur/Fraicheur.php` l. 74) et lue **uniquement par le
thème, pour l'afficher**. Aucun code de l'extension ne la surveille. Un site peut afficher « Donnée
périmée. » pendant des semaines sans qu'aucun courriel ne parte, parce que le connecteur, lui, n'a rien
à signaler.

### 0.3 Alternatives écartées — chacune pour une raison écrite

| Écartée | Motif décisif |
|---|---|
| **Ordonnanceur générique** au-dessus des connecteurs (prefecture + météo + EFFIS) | Les chaînes #10 et #11 écrivent leur propre planification **en ce moment**, en aveugle, dans des fichiers hors empreinte : impossible de les y brancher. Le connecteur préfecture a son ordonnanceur, son test et 15 lignes de justification. Résultat garanti : **du code mort** plus une seconde source de vérité sur la planification. |
| **Constat de conformité seul** (issue de pure preuve, zéro ligne de production) | Laisserait les **deux** trous du §0.2 ouverts, dont la ligne §4.5 la plus littérale, inatteignable dans la configuration réellement exploitée. Réduire le périmètre d'une issue n'est pas à la main de cette chaîne. |
| **Répéter la péremption dans chaque ligne de liste et dans le panneau de carte** | Violerait l'interdit 14 du contrat #7 et produirait 25 répétitions de la même phrase au lecteur d'écran. Voir l'arbitrage **A-1**, où je reconnais m'être trompé de cadrage. |
| **Créer `massifs_horodatage_jour()`** pour solder la dette B-1 | `includes/domain/fraicheur/` est **hors empreinte**. Le dupliquer ailleurs recréerait la table des mois français que `Horodatage.php` l. 16-27 déclare **règle métier de fuseau**, donc une seconde source de vérité. Remontée en §11, jamais comblée. |

---

## 1. Fonctions de lecture exposées par l'extension

### 1.1 Aucune nouvelle — décision explicite, pas un oubli

**La surface front/back de cette issue est VIDE.** C'est le résultat le plus important de la
réconciliation : les deux plans y sont arrivés indépendamment.

La surface de lecture de la fraîcheur reste **exactement** celle des contrats #3 et #8 §2, **inchangée
d'un octet** :

```php
massifs_fraicheur( ?string $jour = null ): array
// jour_validite (string) · dernier_releve_le (string ISO 8601 UTC|null)
// dernier_releve_source (string) · age_secondes (int|null) · seuil_secondes (int, 86400)
// perimee (bool) · publie_prefecture_le (string ISO 8601 UTC|null)
// dispositif_actif (bool) · evalue_le (string ISO 8601 UTC)
//                                   — TOUTES les clés sont TOUJOURS présentes

massifs_saison( ?string $jour = null ): array
// jour · active · debut · fin · prochaine_ouverture · confirmee

massifs_horodatage( string $instant_iso_utc ): array
// iso · attr_datetime · date_longue · heure · date_courte
//                                   — EXIGE un instant complet, REFUSE une date nue

massifs_jour_courant(): string        massifs_jour_suivant(): string
```

### 1.2 Pourquoi aucune fonction `massifs_veille_*()` ni `massifs_peremption_*()`

1. `massifs_fraicheur()['perimee']` **répond déjà** à « les données affichées sont-elles périmées ? ».
   Une seconde fonction serait **une seconde manière de poser la même question, c'est-à-dire une
   divergence en attente** — §1.4 du contrat #9, appliqué ici sans exception.
2. La seule information que le module produit et que le domaine ne porte pas est « une alerte est-elle
   partie ? ». C'est un **fait d'exploitation**, pas un fait public. L'exposer sur une surface anonyme
   publierait l'état interne de la supervision (§9 du brief).
3. Le rendu de l'indicateur de fraîcheur et du mode « dispositif inactif » est **déjà tenu** par le
   thème avant cette issue (§0.1). Il n'y a rien de plus à lui donner.

**Clause opposable** : si une revue trouve une fonction `massifs_*()` créée par cette issue, c'est un
défaut, quelle que soit sa qualité.

---

## 2. Routes REST

**Aucune, ni créée, ni modifiée.** Cette issue ne touche pas `includes/rest/**`, n'appelle ni
`register_rest_route`, ni `register_rest_field`, ni `show_in_rest`, et ne consomme pas `rest_api_init`.

`GET /wp-json/massifs/v1/statuts` (contrat #8) est **inchangée** : aucune clé ajoutée au bloc
`fraicheur`, aucun code de statut, aucun en-tête, aucun ETag modifié.

---

## 3. Hooks, filtres, constantes et options exposés

Tous sont des **surfaces d'exploitation serveur**. **Aucun n'est destiné au thème** (§7.2, interdit 1).

| Nom | Type | Signature exacte | Consommateur légitime |
|---|---|---|---|
| `massifs_donnee_perimee_constatee` | **action**, émise par `ingest/cron` | `do_action( 'massifs_donnee_perimee_constatee', array $fraicheur )` | `security/alertes`, supervision serveur |
| `massifs_veille_fraicheur_armee` | filtre | `apply_filters( 'massifs_veille_fraicheur_armee', bool $armee ): bool` | `wp-config.php` / mu-plugin, **avant `init` prio 20** |
| `massifs_alertes_destinataires` | filtre | `apply_filters( 'massifs_alertes_destinataires', string[] $destinataires, string $contexte ): string[]` | `$contexte === 'peremption'` aujourd'hui |
| `MASSIFS_VEILLE_FRAICHEUR_DESARMEE` | constante | `true` ⇒ veille désarmée. **Défaut : armée.** | `wp-config.php` |
| `Massifs\Ingest\Cron\Planificateur::HOOK` | constante publique | `'massifs_veille_fraicheur'` | recette |
| `Massifs\Security\Alertes\Verrou::OPTION` | constante publique | `'massifs_alertes_verrous'` | recette (purge) |

### 3.1 Orthographe gelée — `massifs_donnee_perimee_constatee`

**Un seul `n` à `donnee`, deux `e` à `perimee`, `constatee` au féminin.** La proposition du plan back
l'écrivait `massifs_doncee_perimee_constatee` dans un intertitre et `massifs_donnee_perimee_constatee`
partout ailleurs. C'est **exactement** la classe de défaut que ce contrat existe pour attraper : un nom
orthographié de deux façons produit une action émise que personne n'écoute, **sans aucune erreur PHP**.

**La forme ci-dessus est la seule valide.** Elle est écrite **une fois** dans l'émetteur et **une fois**
dans l'abonné ; toute troisième occurrence dans le code de production est un défaut.

### 3.2 Charge utile de l'action — clause gelée

`$fraicheur` est **exactement** le tableau retourné par `massifs_fraicheur( $jour )`, forme du contrat
#8 §2, **sans aucune clé ajoutée, retirée ni renommée**, avec **deux garanties** :

- `$fraicheur['perimee'] === true`
- `$fraicheur['dispositif_actif'] === true`

**Aucune nouvelle forme n'entre dans le système** : pas d'objet valeur `Constat`, qui serait une seconde
représentation de la fraîcheur, vouée à diverger.

**L'action n'est PAS dédoublonnée par l'émetteur** : elle est émise **à chaque exécution horaire** où
l'incident tient. La déduplication appartient à l'abonné. Un abonné tiers naïf enverrait 24 courriels
par jour — **clause à opposer en review de tout futur abonné**.

---

## 4. Architecture des deux modules d'extension

### 4.1 Arborescence et ordre d'écriture — l'ordre est normatif

```
includes/
├── security/                                  ← couche CRÉÉE par cette issue
│   └── alertes/
│       ├── Verrou.php        Massifs\Security\Alertes\Verrou        (1)
│       ├── Peremption.php    Massifs\Security\Alertes\Peremption    (2)
│       └── module.php        hooks seuls                            (3)
└── ingest/
    └── cron/
        ├── Planificateur.php Massifs\Ingest\Cron\Planificateur      (4)
        ├── Veille.php        Massifs\Ingest\Cron\Veille             (5)
        └── module.php        hooks seuls — DERNIER FICHIER ÉCRIT    (6)
```

**S-1. `module.php` s'écrit EN DERNIER dans chaque module.** Reprise de l'interdit 12 du contrat #8.
C'est le **seul** chemin que `massifs_core_charger_modules()` découvre (`massifs-core.php` l. 155-161) :
tant qu'il n'existe pas, le module est invisible et sans effet. Le créer en premier ferait charger un
module à moitié écrit **alors que les chaînes #10 et #11 tournent sur le même arbre** — et un
`ParseError` de fichier inclus **n'est pas rattrapable par `try/catch`** : écran blanc sur tout le site,
pour les trois chaînes à la fois.

**S-2. Aucun fichier hors empreinte n'est modifié — surtout pas `massifs-core.php`.** Le chargeur
découvre les modules par convention (couches `security`, `domain`, `ingest`, `rest`, `admin`, puis
`module.php` **ou à défaut** `bootstrap.php`). Clause S-2 du contrat #8, appliquée.

**S-3. `security/alertes/` est chargé AVANT `domain/fraicheur/`.** À l'inclusion, `massifs_fraicheur()`,
`massifs_saison()` et `massifs_horodatage()` **n'existent pas encore**. Le contrat d'amorce
(`massifs-core.php` l. 116-120) est explicite : une amorce « ne déclare QUE des hooks et des
`require_once` de ses propres fichiers […] ne doit jamais supposer un ordre de chargement entre
modules ». **Toute lecture du domaine se fait dans un rappel de hook tardif, sous garde
`function_exists()`.**

**S-4. Aucun `require_once` dans les deux `module.php`.** Ils ne contiennent que des `add_action` /
`register_deactivation_hook` ; les classes sont résolues par l'autoloader, **au premier usage
seulement**. Conséquence voulue et opposable : **le chargeur de l'extension n'inclut jamais les fichiers
de classe de cette issue**, donc un fichier à moitié écrit **ne peut pas** produire d'écran blanc pour
les chaînes sœurs.

**S-5. Interdit de couplage.** Ces modules **ne référencent jamais** `Massifs\Ingest\Prefecture\*` — ni
`Settings`, ni `StateRepository`, ni `Notifier`, ni `Schedule`, ni `Runner`, ni `Connector` — ni ses
options (`massifs_prefecture_*`), ni ses filtres. Cet espace de noms est **exclu de l'autoloader**
(`massifs-core.php` l. 65-67) et appartient à une autre chaîne. **La chaîne littérale `'prefecture'`
n'apparaît nulle part dans ces deux modules** : `dernier_releve_source` est **lu** dans le tableau.

### 4.2 Planification

| Propriété | Valeur gelée | Raison |
|---|---|---|
| Crochet | `massifs_veille_fraicheur` (`Planificateur::HOOK`) | — |
| Récurrence | **`hourly`**, récurrence **native** | Voir les quatre raisons ci-dessous |
| Créneau horaire | **aucun** | Le dépôt porte trois heures de publication contradictoires (§11, Q1) ; il est **interdit** d'en encoder une |
| `cron_schedules` | **jamais** | Filtre **site-wide** : un module d'ingestion n'ajoute pas une récurrence au vocabulaire cron de tout le site. Le connecteur préfecture n'en enregistre aucune non plus |
| Idempotence | `wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', HOOK )` sous garde `! wp_next_scheduled( HOOK )` | Patron de `Schedule::ensure()` |
| Amorçage | `add_action( 'init', array( Planificateur::class, 'assurer' ), 20 )` **plus** `if ( did_action( 'init' ) > 0 ) { Planificateur::assurer(); }` | Sans le chargeur tardif, un chargement postérieur à `init` ne planifierait **jamais** |
| Activation | **aucun `register_activation_hook`** | La stack a déjà activé l'extension ; il ne se redéclencherait jamais. Auto-réparation sur `init` |
| Désactivation | **`register_deactivation_hook( <fichier extension>, array( Planificateur::class, 'retirer' ) )` — OBLIGATOIRE** | Voir §4.3 |
| `accepted_args` du crochet | **0** | Pour que la valeur par défaut `null` de `executer()` s'applique sans ambiguïté |

**Pourquoi `hourly`, et pas `daily` ni `twicedaily` :**

1. **`wp_schedule_event` stocke un horodatage UTC.** Un créneau fixé à une heure de Paris dérive d'une
   heure au changement d'heure — et **toute** la saison du dispositif (1er juin – 30 septembre) est en
   heure d'été.
2. **WP-Cron n'est pas un planificateur** : il se déclenche au passage d'un visiteur. Un créneau
   quotidien étroit peut être manqué en entier sur un site peu fréquenté — soit exactement le trou que
   cette issue referme.
3. **Une récurrence horaire n'encode aucune heure.** C'est la seule qui permette de ne rien trancher sur
   Q1 (§11).
4. **Latence contre le seuil de 24 h.** `daily` ⇒ jusqu'à **48 h** de bannière sans courriel ;
   `twicedaily` ⇒ 36 h ; **`hourly` ⇒ 25 h**, soit le seuil plus une heure. Seule récurrence native qui
   tienne la ligne « alerte email si tout échoue ».

**Coût dans le cas nominal** : un `get_option` sur une option **autoloadée** (déjà en mémoire), deux
comparaisons de booléens, **zéro écriture**, zéro requête SQL, zéro octet réseau. Moins cher qu'un rendu
de page.

### 4.3 Le crochet doit disparaître à la désactivation — test d'acceptation existant

`tests/scenarios/20-cron-complet.arme.php` l. 100-108, **hors empreinte et non modifié**, compte les
crochets cron après `deactivate_plugins()` :

```php
if ( str_starts_with( (string) $crochet, 'massifs_' ) ) { ++$restant; }
t_egal( 0, $restant, 'désactivation : plus aucun crochet « massifs_* » dans le cron' );
```

`massifs_veille_fraicheur` **commence par `massifs_`**. Si `register_deactivation_hook` est omis, **ce
scénario existant passe au rouge**. C'est le test d'acceptation gratuit de la clause de retrait, et
c'est le premier point que la review doit vérifier (§10).

### 4.4 Prédicat d'incident — `Veille::executer( ?string $jour = null ): void`

Gardes dans cet **ordre exact**, chacune en sortie anticipée :

| # | Lecture | Sortie |
|---|---|---|
| 0 | `Planificateur::est_armee()` | `false` ⇒ `return` |
| 1 | `function_exists( 'massifs_fraicheur' )` | `false` ⇒ `return` (S-3) |
| 2 | `try { $f = massifs_fraicheur( $jour ); } catch ( \Throwable )` | exception ⇒ `return`. ~~`massifs_fraicheur()` traverse `Legende::chargee()` (module `statuts`, chaîne sœur) et peut lever.~~ — **PÉRIMÉ** (erratum du 4 septembre 2026, issue #94) : `69c52f4` a repointé `massifs_fraicheur()` sur `Saison::officielle()` et retiré de `domain/fraicheur` toute mention d'un symbole `Massifs\Domain\Statuts\`. **La règle opposable ne bouge pas** : le `try`/`catch` de la colonne de gauche reste prescrit. **Un rappel cron ne fait jamais tomber la requête d'un visiteur.** |
| 3 | `true !== $f['dispositif_actif']` | ⇒ `return` — **hors saison, silence total** |
| 4 | `true !== $f['perimee']` | ⇒ `return` — **unique prédicat d'incident** |
| 5 | — | `do_action( 'massifs_donnee_perimee_constatee', $f );` |

**Trois règles opposables :**

- **Aucune heure n'est lue ni écrite.** Ni heure de publication, ni fenêtre, ni créneau. **Aucune
  constante horaire n'existe dans ce module.**
- **La règle des 24 h n'est jamais recalculée.** `age_secondes > seuil_secondes`, ou toute variante,
  est **interdite**. Le seuil vit dans `Fraicheur::SEUIL_SECONDES` et la règle dans `Fraicheur.php`
  l. 74.
- **La garde 3 est volontairement redondante avec la garde 4** — `perimee` ne peut être vrai que si
  `dispositif_actif` l'est (`Fraicheur.php` l. 74). Elle est conservée parce que « la veille se tait
  hors saison » est une **décision de cette issue** et doit être lisible dans **son** code, pas
  implicite dans le `&&` d'un autre module. Si les deux clés divergeaient un jour, **le silence
  l'emporte, par conception**.

**Aucune sortie anticipée sur le mode du connecteur.** `Settings::mode()` et `Settings::is_disabled()`
ne sont ni lus, ni référencés, ni devinés (S-5). **C'est tout l'objet du trou n° 1.**

**Le paramètre `?string $jour`** existe pour que la recette puisse placer le dispositif **hors saison**
sans attendre décembre (`tests/README.md` règle 3 ; patron déjà en service dans
`21-rendu-etats-hors-saison.php`). Le cron n'en passe aucun.

### 4.5 Verrou d'alerte

| Propriété | Valeur gelée |
|---|---|
| Stockage | **option** `massifs_alertes_verrous`, **`autoload = false`** |
| Forme | `array<string, string>` — clé de verrou → instant de pose ISO 8601 UTC |
| Clé | `'{type}:{source}:{jour_validite}'`, ex. `peremption:prefecture:2026-08-14`. **Chaque fragment est normalisé** — `strtolower`, retrait de tout caractère hors `[a-z0-9_-]`, troncature à 32 — pour qu'une clé de source exotique ne produise pas une clé de verrou imprévisible. *(Clause ajoutée au gel après la passe de refacto : l'implémentation la portait, le §4.5 ne la décrivait pas. Sur les valeurs d'aujourd'hui, le résultat est identique à la concaténation brute — la recette peut donc reconstruire la clé par concaténation, et le scénario 51 le fait. Ce ne sera plus vrai d'une source portant une majuscule, un accent ou un point : dans ce cas, c'est la clé normalisée qui fait foi.)* |
| Bornage | `MAX_ENTREES = 30`, `array_slice( $registre, -30, null, true )` à chaque écriture. **Aucun calcul de date** : l'ordre d'insertion suffit |
| Écriture | **uniquement quand une alerte part**. Zéro écriture dans le chemin nominal horaire |
| Instant | provient de `$fraicheur['evalue_le']`, **jamais d'une horloge propre au module** |

**Option et non transient** : un transient est évincible à tout moment par un cache objet sous pression,
et une éviction = un doublon de courriel. Même posture que `StateRepository`, qui est une option pour la
même raison.

**`autoload = false`**, contrairement à `RegistreReleves::OPTION` : cette option n'est lue que depuis un
rappel cron, jamais au rendu d'une page.

**Granularité `peremption:{source}:{jour_validite}` — une alerte par source et par jour de validité :**
- **par jour**, et non « une fois jusqu'à résolution » : une péremption qui dure trois jours mérite
  trois rappels ; « une seule fois pour toujours » refermerait le trou n° 2 à moitié ;
- **jamais par heure** : reprise verbatim de `class-notifier.php` l. 5-7 — la récurrence est horaire,
  24 envois par jour noieraient la boîte du gestionnaire et finiraient ignorés, **donc inutiles le jour
  où ils comptent** ;
- **par source** : pour qu'un futur incident `meteo` ne soit pas avalé par un verrou `prefecture` du
  même jour.

**Le verrou est posé AVANT `wp_mail()`, pas après.** C'est un **écart délibéré** à `Notifier`
(`class-notifier.php` l. 118-141), qui pose après. Raison : si une extension SMTP tierce lève une
`Throwable`, le verrou n'est jamais posé et la tentative se rejoue **toutes les heures**, avec son
exception. Poser d'abord ferme cette boucle. La valeur du verrou ne dépend pas de la délivrance : il
protège contre la **répétition**, pas contre l'échec. Le coût est borné par la granularité **par
jour** — un envoi perdu coûte une alerte pour une journée, et la clé du lendemain est différente.

**Aucun destinataire ⇒ `return` AVANT de poser le verrou** (reprise de `Notifier::envoyer()`
l. 129-131) : rien n'a été tenté, et un destinataire peut apparaître.

**Concurrence** : deux exécutions simultanées pourraient toutes deux voir le verrou absent. Limitation
**assumée et non outillée** — le cœur garde déjà `wp_cron()` par son transient `doing_cron`, la fenêtre
est d'une milliseconde une fois par jour, et le dommage maximal est un courriel en double. Même posture
que `Notifier`.

### 4.6 Contenu du courriel

Texte brut, français, en-tête `Content-Type: text/plain; charset=UTF-8`. Rédigé **librement** — c'est un
texte d'exploitation interne — mais **ne citant aucune chaîne officielle**, aucun libellé de niveau,
aucune consigne, **et aucune chaîne du thème** (décrire l'état en termes d'exploitation évite qu'un
courriel dérive au premier remaniement d'un gabarit).

**Sujet** : préfixe `[MASSIFS]`, source et jour de validité. **Aucune heure de publication.**

**Corps, dans cet ordre :**

1. **Le fait.** Deux formulations selon `dernier_releve_le` : non nul ⇒ instant mis en forme par
   `massifs_horodatage()` **sous `function_exists`, avec repli sur la chaîne ISO brute** ; **`null` ⇒
   « aucun relevé réussi n'est enregistré »** — cas réel et fréquent (en saison, base vierge : l'état de
   la stack Docker). Le seuil est écrit depuis `{seuil_secondes}`, **jamais « 24 h » en dur**.
   **Aucune date n'est composée par ce module.**
2. **Bloc « CE QUE LE SITE AFFICHE »** — le cœur du message, sur le modèle de `Notifier::bloc_commun()`
   l. 96-113, **avec une correction de fond que `Notifier` n'a pas à faire** :
   - le site affiche **la bannière de péremption** ;
   - **la bannière s'ajoute, elle ne masque rien** : si des statuts existent pour le jour, ils restent
     affichés. Cette alerte ne signifie **pas** que le site dit « information non disponible » ;
   - si en outre aucun statut n'existe, le site dit « information non disponible » — mais c'est **un
     autre signal**, porté par l'alerte du connecteur.

   **Cette précision est la raison d'être de deux alertes distinctes (A-3) et doit figurer dans le
   courriel**, sans quoi un gestionnaire recevant deux messages le même jour croira à un doublon.
3. **Les faits bruts** : `dernier_releve_source`, `dernier_releve_le`, `age_secondes`, `seuil_secondes`,
   `jour_validite`, `publie_prefecture_le`, `evalue_le`.
4. **Carte officielle** : `massifs_attribution_statuts()['carte_officielle_url']` sous
   `function_exists`, ligne omise si indisponible. **URL jamais écrite en dur.**
5. **Administration** : `admin_url()`.
6. **Comment faire taire cette alerte** : nom de la constante et du filtre. *Un courriel d'exploitation
   qui ne dit pas comment s'arrêter finit filtré en spam.*

**Destinataires** : `array( (string) get_option( 'admin_email', '' ) )`, puis
`apply_filters( 'massifs_alertes_destinataires', $destinataires, 'peremption' )`, puis
`sanitize_email()` + `is_email()` + `array_values( array_unique( … ) )`.
**`massifs_prefecture_alerte_destinataires` n'est ni lu, ni consommé, ni relayé** (S-5).

**Aucun échappement HTML sur une valeur du corps.** Ni `esc_html`, ni `esc_attr`, ni `esc_url`, ni
`wp_kses*`. **Une entité HTML dans un courriel texte est une corruption de donnée, pas une
protection** — même raisonnement que l'interdit 8 du contrat #8 sur le JSON. Les chaînes interpolées
passent par `wp_strip_all_tags()`.

### 4.7 Armement

`Planificateur::est_armee(): bool`, dans cet ordre :
1. `defined( 'MASSIFS_VEILLE_FRAICHEUR_DESARMEE' ) && MASSIFS_VEILLE_FRAICHEUR_DESARMEE` ⇒ `false` ;
2. `apply_filters( 'massifs_veille_fraicheur_armee', true )`, retypé `bool` — **dernier mot** ;
3. défaut : **armée**.

**Aucune option en base**, donc aucun écran de réglages, aucune capability, aucune seconde source de
vérité. Appelée **deux fois** — par `assurer()` sur `init` et par `executer()` au déclenchement — pour
que le désarmement produise son effet immédiatement, même si l'évènement planifié survit une heure.
Même ceinture-et-bretelles que `Runner::run_scheduled()`.

Un filtre `massifs_veille_fraicheur_armee` doit être enregistré **avant `init` priorité 20** pour
influencer la planification ; enregistré plus tard, il n'influence que le comportement.

---

## 5. Le gabarit `bandeau-peremption.php`

### 5.1 Séquence — sortie fermée, jamais ouverte, jamais silencieuse

| # | Ce que fait la ligne | Si ça échoue |
|---|---|---|
| 1-2 | `declare(strict_types=1);` puis garde `ABSPATH` | — |
| 3 | `$arguments = isset( $args ) && is_array( $args ) ? $args : array();` | — |
| 4 | `jour` accepté seulement si `is_string` **et** `/^\d{4}-\d{2}-\d{2}$/`. **Contrôle de forme, jamais de calcul de date** | `_doing_it_wrong()`, `$jour = null` |
| 5 | `null === $jour && function_exists( 'massifs_jour_courant' )` ⇒ `$jour = massifs_jour_courant()` | `$jour` reste `null`, valeur par défaut légitime |
| 6 | `fraicheur` depuis `$args` si tableau non vide ; sinon garde `function_exists( 'massifs_fraicheur' )` | API absente ⇒ **`return;` — zéro octet** |
| 7 | `massifs_fraicheur( $jour )` **dans un `try`**, `catch ( \InvalidArgumentException )` | `_doing_it_wrong()` + **zéro octet** |
| 8 | `$perimee = isset( $fraicheur['perimee'] ) && true === $fraicheur['perimee'];` — **comparaison stricte** | clé absente ⇒ `false` + `_doing_it_wrong()` |
| 9 | `if ( ! $perimee ) { return; }` — **unique porte de sortie nominale** | zéro octet |
| 10 | Balisage du §5.2 | — |

**Échec fermé.** Toute anomalie produit **zéro octet**. Une bannière de péremption affichée à tort
serait un **mensonge sur la donnée** ; son absence n'en est pas un, la règle absolue du §4.2 étant portée
par `etat_global === 'indisponible'`, **règle disjointe** (contrat #3, A-5).

**Vocabulaire de journalisation** : `_doing_it_wrong()`, pour rester homogène avec `etats-vides.php` et
`liste-statuts.php`, famille dont ce gabarit relève. Réserve enregistrée : `front-page.php` l. 220-224
documente une préférence inverse (`trigger_error( E_USER_WARNING )` s'imprimerait dans la page si
`display_errors` était actif). **Signalé, non tranché en silence** — §11, Q5.

### 5.2 Balisage émis — forme exacte, gelée

```html
<div class="bandeau-alerte bandeau-alerte--peremption sur-sombre repere repere--bloc">
<p class="bandeau-alerte__texte">Donnée périmée.</p>
</div>
```

**Élément racine : `<div>`, et rien d'autre.** `<aside>` est **toujours** un landmark `complementary`,
nommé ou non — polluer l'arbre de landmarks pour deux mots est ce que l'interdit 9 du contrat #9 §7
refusait à `carte-secours.php`. `<section>` + `aria-label` exigerait une **chaîne de site inventée hors
§11.3** ; `<section>` sans nom est exposé « generic », donc identique au `<div>` pour 8 octets de plus.
Un `id` non consommé est de la dette. Un titre casserait le plan de titres (`h2` intercalé entre le `h1`
de l'ardoise et le `h2` de la légende) **et** exigerait, lui aussi, une chaîne inventée.

**Aucune donnée serveur n'est émise** : le texte est un littéral du gabarit. Il n'y a donc **aucun**
`esc_html()`/`esc_attr()`/`esc_url()` à poser, et c'est **vérifiable par revue** — le fichier ne contient
aucune interpolation dans sa partie HTML. Propriété rare, à préserver.

**Aucun CSS n'est écrit, et aucun n'est nécessaire — vérifié, pas supposé.** La chaîne `bandeau-alerte`
n'apparaît que quatre fois dans `assets/css/**`, dont **une seule** règle (`composants.css` l. 502-506) ;
**aucun sélecteur `.bandeau-alerte--*` n'existe nulle part**. Tout le rendu vient de trois sources déjà
en service : `composants.css` l. 502-506 (`--bord-fort` en bas, `padding`), `layout.css` l. 406-409
(fond `--c-mistral-nuit`, texte `--c-calcaire`, **12,66:1**), et `layout.css` l. 525-532, variante
auto-portée `.repere.sur-sombre::before/::after`, écrite précisément parce qu'un bandeau d'alerte porte
`.sur-sombre` **et** `.repere` sur le même élément. **Cette issue n'ouvre aucune couture CSS.**

### 5.3 Chaîne rendue — « Donnée périmée. », et rien de plus

Verbatim `MASTER.md` §8.3 l. 1034, point final compris. **Aucun âge, aucun lien, aucune date, aucune
seconde phrase.**

**Raison, et elle est opposable** : `MASTER.md` §11.3 est une **liste FERMÉE** des chaînes rédigées par
le site, et **elle ne contient aucune chaîne de péremption**. Y ajouter quoi que ce soit serait inventer
une chaîne de site — précédent exact : le contrat #9 §6 a refusé de rédiger un texte d'`alt` pour cette
raison même. La chaîne rendue est par ailleurs **identique à l'octet près** à celle que `front-page.php`
l. 362 rend déjà : le vocabulaire reste constant (§7 du brief).

**Réserve consignée, non comblée** (§11, Q3) : « Donnée périmée. » **ne dit pas quoi faire**, ce que
`MASTER.md` §11.1 règle 3 exige des messages d'anomalie, et les trois autres bandeaux d'alerte offrent
tous une suite. Une révision `lead-design-cms` est demandée. **Elle n'est pas anticipée d'un octet.**

### 5.4 Ce que le gabarit ne lit pas, et pourquoi c'est structurant

- **`massifs_horodatage()` n'est jamais appelée.** Le gabarit n'affiche **aucune date, aucune heure,
  aucun âge**. Il ne construit donc **jamais** de date nue, n'emploie **pas** la couture
  `$jour . 'T12:00:00Z'`, et **ne dépend pas de la demande B-1**. C'est le seul gabarit de statut du
  thème dans ce cas. Corollaire gratuit : le cas `dernier_releve_le === null` — **le plus périmé de
  tous** — est traité par construction, cette clé n'étant jamais lue.
- **`dispositif_actif` n'est jamais lu.** Le contrat #3 l. 161-162 garantit que `perimee === true`
  implique `dispositif_actif === true`. Le lire et le combiner en `&&` serait **recalculer côté thème une
  règle déjà appliquée par le serveur**. L'exigence « sans effet hors saison » est tenue
  **transitivement** — d'où la clause **B-1** du §6, qui la rend opposable au back.
- **`etat_global`, `saison`, `synthese`, `attribution` ne sont jamais lus.** `perimee` **n'est pas un
  `etat`** (contrat #8 §3, contrat #6, contrat #3). Aucun `match()` n'est donc nécessaire : il n'y a pas
  de vocabulaire fermé à couvrir, donc **pas d'`\UnhandledMatchError` à envelopper**. C'est la seule
  partie du thème sans `match()`, et c'est correct.

### 5.5 Accessibilité — aucun `role`, aucun `aria-live`

**Décision : aucun `role`, `aria-live`, `aria-atomic`, `aria-label`, `tabindex`.**

Le contenu est présent **au premier octet du HTML**. Une région live n'annonce que les mutations
survenant **après** que la région est connue de l'API d'accessibilité ; posée sur du contenu déjà présent
au parsing, elle n'annonce rien dans le cas nominal et se comporte de façon erratique selon le moteur —
**un défaut d'implémentation, pas une amélioration**. `role="alert"` serait pire : il interromprait la
lecture en cours pour deux mots, sur une page où aucune interaction n'a eu lieu. Précédent interne :
`etats-vides.php`, `bandeau-non-officialite.php` et `carte-secours.php` n'émettent aucun `role`.

Le reste du §8 du brief est tenu : le **premier mot porte le sens** (« **Donnée** périmée. »), aucune
icône, aucune couleur signifiante — la preuve étant que la bannière porte **le même chrome** que
`--hors-saison` et `--non-publie`, qui disent tout autre chose. Contraste **12,66:1**. À 360 px : 15
caractères, aucune largeur fixe, aucun défilement horizontal possible. Zoom 200 % : aucune dimension en
`px` posée par le gabarit. **Aucun nœud focusable n'est ajouté** — propriété à conserver, elle rend le
composant inerte vis-à-vis de tous les invariants clavier déjà gelés.

### 5.6 Impression

`print.css` l. 98-103 pose `display: none` sur `.bande--carte` **entière** ; la couture **C-1 du contrat
#9 est encore ouverte**. **Conséquence opposable : la bannière n'est insérée nulle part à l'intérieur de
`.bande--carte`** — une bannière de péremption absente de la feuille imprimée serait un défaut §4.2 sur
un support que le §5.3 exige imprimable.

Au point d'insertion retenu (§6.2), elle s'imprime sans une ligne de CSS supplémentaire :
`print.css` l. 59-65 (`.sur-sombre` → fond transparent, encre `--c-charbon`) + l. 79-81
(`border-block-start`) + `composants.css` l. 502-503 (`border-block-end`) ⇒ **un bloc encadré haut et
bas, en noir sur blanc**. Cas déjà anticipé par le commentaire de `print.css` l. 76-78.

---

## 6. Point d'insertion — jonction remontée, NON exécutée par cette chaîne

### 6.1 Le risque, nommé

**Personne n'inclut cette partie.** Les inclusions se font par `massifs_partie( '<slug>' )` depuis
`front-page.php`, **hors empreinte**. Un composant livré et jamais atteint est du **code mort**, et un
refacto le supprimera. C'est le mode de défaillance **A-13 du contrat #9** (l. 571-610) : « la DoD tombe
**en silence** ».

**Cette chaîne n'écrit pas dans `front-page.php`.** L'arbitrage appartient à l'orchestrateur.

### 6.2 La ligne exacte à écrire — proposition gelée

> **Fichier** : `wp-content/themes/massifs/front-page.php`
> **Ligne** : **369** — la ligne actuellement **vide** entre le `</section>` de `#ardoise` (l. 368) et
> l'ouverture de `<section id="non-officialite">` (l. 370). Indentation de la ligne 370.
>
> ```php
> <div class="bande bande--peremption"><?php massifs_partie( 'bandeau-peremption' ); ?></div>
> ```
>
> **Aucune condition. Aucun `$args`. Aucun autre changement.**

**Cinq raisons, par ordre de force :**

1. **Elle précède la bande carte ET la bande liste** — un seul nœud, en amont des deux, dans l'ordre du
   document, l'ordre visuel, l'arbre d'accessibilité et la feuille imprimée.
2. **Elle est adjacente à ce qu'elle qualifie** : la ligne de fraîcheur §11.3 est rendue juste au-dessus
   (`front-page.php` l. 351).
3. **Elle n'invente aucune bande dans l'ordre normatif `MASTER.md` §7.1.** Le §7.1 décrit le chrome
   **nominal** ; le bandeau d'alerte est un **état exceptionnel** — `layout.css` l. 206-209 le dit mot
   pour mot. §7.1 est **silencieux** sur §8.3, pas contraire. Sa seule contrainte opposable ici — « le
   bandeau de non-officialité est entre l'ardoise et la carte » — reste vraie.
4. **Le filet composite est le moins mauvais des trois placements.** En 369, le `--bord-fort` (4 px) de
   la bannière rencontre le `--bord-moyen` (2 px) de `.bande--non-officialite` ⇒ 6 px. Placée entre
   non-officialité et carte, elle rencontrerait le `--bord-fort` d'entrée de `.bande--carte:has(*)` ⇒
   8 px, **et casserait l'arbitrage A-3 de `layout.css` l. 186-193** : « le 4 px est l'UNIQUE filet fort
   du chrome nominal, il marque l'ENTRÉE du héros ».
5. **Elle s'imprime** (§5.6) : hors de `.bande--carte`.

**Pourquoi `<div class="bande">` et surtout PAS `.bande__contenu` :** `.bande` ne pose que
`inline-size: 100%` (`layout.css` l. 164-166), donc un conteneur **vide mesure exactement 0 px de
haut** — décisif, puisque 364 jours sur 365 la partie rend zéro octet. `.bande__contenu` poserait
`padding-block: var(--esp-section) var(--esp-2xl)` (l. 179-184), soit **~96 px de vide injectés au-dessus
de la carte tous les jours nominaux**, sur la page la plus lue du site, **irrattrapable** puisque
`assets/css/**` est hors empreinte. **C'est cette contrainte qui tranche la forme du conteneur, pas une
préférence.** Précédents : `.bande--carte` n'émet pas de `.bande__contenu` ; `.bande--legende` et
`.bande--liste` sont des modificateurs **sans aucune règle CSS**.

### 6.3 Clauses opposables à l'appelant — `P-1` à `P-9`

Sur le modèle des `F-*` du contrat #9 §5. **C'est la moitié du contrat que cette chaîne ne peut pas faire
appliquer elle-même.**

| # | Clause | Ce que sa violation casse |
|---|---|---|
| **P-1** | L'appel est **inconditionnel**. L'appelant ne lit **jamais** `perimee`, `dispositif_actif` ni `massifs_fraicheur()` pour décider d'appeler | Une condition dans l'appelant duplique la décision métier en deux endroits qui divergeront ; et c'est **exactement** le risque A-13 — un `return` ou une garde en amont fait tomber la DoD §12 **en silence**. La décision d'afficher appartient au gabarit, **une fois** |
| **P-2** | L'appel apparaît **exactement une fois** dans tout le thème | Seconde bannière ⇒ interdit 14 du contrat #7. L'unicité doit être **structurelle**, vérifiable par simple comptage — même critère que `massifs_partie( 'carte-secours' )` (A-13, point 1) |
| **P-3** | L'appel est **hors de `.bande--carte`** et hors de tout conteneur masqué par `print.css` | Bannière absente de la feuille imprimée = défaut §4.2 sur un support exigé imprimable (§5.3). Couture C-1 du contrat #9, **ouverte** |
| **P-4** | Le conteneur n'émet **pas** de `.bande__contenu`, ni aucun conteneur à `padding-block` | ~96 px de vide au-dessus de la carte **tous les jours nominaux**. Irrattrapable, `assets/css/**` hors empreinte |
| **P-5** | Le conteneur n'émet **ni titre, ni `id`, ni `aria-label`, ni `role`, ni `aria-live`, ni `tabindex`**, et n'est ni `<aside>` ni `<section>` nommée | Landmark vide, ou chaîne de site inventée hors §11.3 — interdit 9 du contrat #9 §7 |
| **P-6** | Aucun `$args` n'est passé (convention `functions.php` l. 79-80). Si un appelant en passe un jour, seules `jour` (`AAAA-MM-JJ`) et `fraicheur` (tableau) sont lues | Divergence avec la convention figée de la chaîne #6 |
| **P-7** | Position : **immédiatement après** `</section>` de `#ardoise`, **avant** `<section id="non-officialite">` | §6.2, raisons 1 à 5 ; en particulier l'unicité du filet fort d'entrée de la carte |
| **P-8** | Si le repli **B** de A-2 est retenu, la duplication est **enregistrée** au §17 de `MASTER.md` et au rapport de lot, avec fichier et lignes exactes. Elle n'est **jamais** refermée en conditionnant la partie ni en ajoutant un second appel | Une dette non écrite se fossilise — précédents A-4 du contrat #5 et C-1 du contrat #9 |
| **P-9** | Personne d'autre que cette chaîne n'écrit dans `templates/parts/bandeau-peremption.php` | Disjonction des empreintes (miroir de F-10) |

---

## 7. États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | `etat`/`etat_global === 'indisponible'`, **inchangé par #12** | « Information du jour non disponible. Consultez la carte officielle de la préfecture. » + lien. **Aucun effet sur la bannière de péremption : règles disjointes** (contrat #3, A-5) |
| `hors_saison` | `etat === 'hors_saison'` ; `massifs_saison()['active'] === false` ; reprise dans `prochaine_ouverture` | « Dispositif estival inactif. Reprise le {date}. » **Inchangé par #12.** Côté veille : **silence total**, aucune action, aucun courriel (§4.4, garde 3) |
| `non_encore_publie` | `etat === 'non_encore_publie'`, **inchangé** | « Les statuts de demain ne sont pas encore publiés. » Aucun effet sur #12 |
| **`donnee_perimee`** | **`massifs_fraicheur()['perimee'] === true`, et rien d'autre. N'est PAS un `etat`** | **`bandeau-alerte--peremption`, une fois, au niveau page.** Bannière **superposée** : **ne masque, ne filtre, ne remplace, ne conditionne aucun statut, aucun chiffre, aucun titre, ni la ligne de fraîcheur** |
| `couche_effis_indisponible` | **Hors périmètre de #12** (issue #11) | Sans objet ici |

**Table état → classe — quatrième entrée, à inscrire au vocabulaire :**

| Déclencheur | Nature | Classe | Masque un statut ? |
|---|---|---|---|
| `etat_global === 'indisponible'` | `etat` | `bandeau-alerte--indisponible` | oui |
| `… === 'hors_saison'` | `etat` | `bandeau-alerte--hors-saison` | oui |
| `… === 'non_encore_publie'` | `etat` | `bandeau-alerte--non-publie` | oui |
| **`fraicheur.perimee === true`** | **drapeau orthogonal, PAS un `etat`** | **`bandeau-alerte--peremption`** | **non, jamais — elle s'ajoute** |

**Aucun cinquième `etat` n'est créé par cette issue.** Le vocabulaire fermé du contrat #8 §3 —
`disponible` · `non_encore_publie` · `indisponible` · `hors_saison` — est **inchangé**. Une revue qui
conclurait à un cinquième état lirait mal la table ci-dessus.

---

## 8. Chaînes fournies par le serveur, chaînes écrites par le thème

| Chaîne | Origine | Statut |
|---|---|---|
| `Donnée périmée.` | **thème** | Verbatim `MASTER.md` §8.3 l. 1034. **Aucune invention** : déjà rendue à l'identique par `front-page.php` l. 362 (chaîne #5) |
| Libellés de niveau, consignes, attributions, phrase de fraîcheur | **serveur** | **Inchangés par #12.** Aucun n'est lu par le gabarit de cette issue |
| URL de la carte officielle | **serveur**, `massifs_attribution_statuts()['carte_officielle_url']` | Lue **par le courriel uniquement**, jamais écrite en dur. **Non rendue** par la bannière (§5.3) |
| Âge lisible, date, heure de la donnée périmée | **aucune — non rendue** | §11.3 est une **liste fermée** sans chaîne de péremption. Réserve Q3 consignée, **non comblée** |
| Heure de publication préfectorale | **aucune — jamais écrite par cette issue** | Trois valeurs contradictoires dans le dépôt. **Question bloquante Q1 (§11), jamais tranchée par déduction** |

---

## 9. Interdits

### 9.1 Portant sur l'extension (`ingest/cron/**`, `security/alertes/**`)

1. **Ne référence jamais `Massifs\Ingest\Prefecture\*`** — ni `Settings`, `StateRepository`, `Notifier`,
   `Schedule`, `Runner`, `Connector`, ni ses options, ni ses filtres. **La chaîne `'prefecture'`
   n'apparaît nulle part** (S-5).
2. **N'écrit aucun fichier hors empreinte.** Jamais `massifs-core.php` (S-2), jamais
   `includes/domain/**`, jamais `includes/ingest/prefecture/**`, jamais le thème, jamais
   `tests/bootstrap.php`.
3. **Aucun appel sortant** : `wp_remote_*`, `curl_*`, `fsockopen`, `file_get_contents` d'URL sont
   absents. **`wp_mail` excepté** (relais SMTP serveur — jamais le navigateur, contrainte #2 intacte).
4. **N'enfile aucun script ni feuille**, ne pose aucun cookie, n'émet aucun en-tête HTTP.
5. **Ne produit aucun HTML.** Aucun `esc_html`/`esc_attr`/`esc_url`/`wp_kses*` — et il est **interdit**
   d'en appliquer à une valeur de courriel texte (§4.6).
6. **N'expose aucune fonction `massifs_*()` de lecture**, aucune route REST, aucun écran d'administration,
   aucun rôle, aucune capability, aucun CPT, aucune taxonomie, aucune option de réglage.
7. **Ne recalcule jamais la règle de péremption.** `age_secondes > seuil_secondes`, ou toute variante,
   est interdit. **Seul `massifs_fraicheur()['perimee']` fait foi.**
8. **N'écrit aucune heure de publication**, aucun créneau, aucune fenêtre, aucune constante horaire.
   **Aucun `cron_schedules`**, aucune récurrence maison.
9. **N'invente aucun libellé de niveau, aucune consigne, aucune formulation officielle.** N'écrit en dur
   ni l'URL de la carte officielle, ni le seuil en heures — **les deux sont lus**.
10. **N'appelle aucune fonction de domaine à l'inclusion** (S-3). Toute lecture se fait dans un rappel de
    hook, sous `function_exists()` et sous `try/catch ( \Throwable )`.
11. **N'écrit jamais de relevé.** `massifs_enregistrer_releve_reussi()` est **interdite** ici : une veille
    qui enregistre un relevé ferait **mentir la fraîcheur**, exactement ce que le §4.5 proscrit. Ne lit ni
    n'écrit `massifs_dernier_releve` directement.
12. **Ne dédoublonne pas avec `Notifier`** (A-3). Deux alertes, deux verrous, deux questions.
13. **N'invalide aucun cache de page**, n'écrit aucun statut, ne touche pas à `{prefix}massifs_statuts`.
14. **Aucun `$wpdb`, aucune requête SQL.**
15. **Aucune écriture d'option dans le chemin nominal.** Pas de compteur d'exécutions, pas de « dernière
    exécution » : une veille horaire qui écrit à chaque passage ferait 24 écritures par jour pour rien.
16. **N'émet aucune action quand l'incident se résorbe** — aucun consommateur ne l'attend.
17. **`module.php` en dernier** dans chaque module (S-1).
18. **Ne crée aucun fichier hors de `includes/security/alertes/`** dans la couche `security`. Le futur
    rôle `gestionnaire` (§6 du brief) appartient à une autre issue et vivra dans un module frère,
    `includes/security/roles/`. **Ce module ne squatte pas ce nom.**

### 9.2 Portant sur le thème

1. **Ne s'abonne jamais à `massifs_donnee_perimee_constatee`.** Un gabarit qui rendrait une bannière
   depuis un signal d'exploitation créerait **un second chemin de rendu du même état** — précisément la
   divergence que `front-page.php` l. 39-45 s'emploie à refermer.
2. N'appelle jamais `Veille::executer()`, `Planificateur::*`, `Verrou::*` ni `Peremption::*`.
3. Ne lit jamais l'option `massifs_alertes_verrous`, et n'affiche jamais qu'une alerte est partie.
4. **Ne recalcule jamais la règle des 24 h** : ne compare pas `age_secondes` à `seuil_secondes`, n'écrit
   pas « 24 h » en dur. **`perimee` est la seule réponse.**
5. **Ne calcule jamais la saison** : `massifs_saison()['active']` et `prochaine_ouverture` font foi ;
   jamais un `date('m')`.
6. **Ne traite jamais `perimee` comme un masque ou un filtre** — la bannière **s'ajoute**.
7. Ne compose jamais une date, une heure ou une durée : `massifs_horodatage()` fait foi.
8. N'écrit jamais en dur l'URL de la carte officielle.
9. N'écrit jamais `isset()` ni `??` sur une clé de `massifs_fraicheur()` — **toutes sont toujours
   présentes**. *(La garde `isset()` de la ligne 8 du §5.1 est une exception nommée : elle détecte une
   **rupture de contrat** et journalise, elle ne fournit pas de repli silencieux.)*
10. N'écrit jamais une valeur hexadécimale, une couleur, un espacement, une dimension ou une durée dans
    le gabarit.
11. N'émet jamais de `<noscript>`, d'élément focusable, de `role`, d'`aria-live` ou de titre depuis ce
    gabarit (§5.5).
12. N'écrit jamais dans `assets/css/**`, `assets/js/**`, `front-page.php`, `tests/**` — cette chaîne ne
    possède **que** `templates/parts/bandeau-peremption.php`.

---

## 10. Invariants opposables en review

| # | Invariant |
|---|---|
| **I-1** | La veille est planifiée et s'exécute **quel que soit le mode du connecteur** — `manuel`, `automatique`, désactivé, `WP_ENVIRONMENT_TYPE=local`. *(Trou n° 1.)* |
| **I-2** | La veille est **silencieuse hors période d'activité** : `dispositif_actif !== true` ⇒ sortie anticipée, aucune action, aucun courriel. |
| **I-3** | Le prédicat d'incident est **exactement** `massifs_fraicheur()['perimee'] === true`. Aucune comparaison d'âge, aucun seuil, aucune heure réécrits. |
| **I-4** | **Aucune heure de publication** n'est écrite nulle part. Récurrence `hourly`, sans créneau, sans `cron_schedules`. |
| **I-5** | **Au plus un courriel par source et par jour de validité.** Verrou posé **avant** `wp_mail`, quel que soit son retour ; **aucun destinataire ⇒ ni envoi ni verrou**. |
| **I-6** | Le courriel dit **ce que le site affiche** et distingue explicitement « bannière de péremption » de « information non disponible ». |
| **I-7** | À la désactivation, **aucun crochet `massifs_*` ne survit dans le cron**. Vérifié par `tests/scenarios/20-cron-complet.arme.php` l. 100-108, **hors empreinte et non modifié**. |
| **I-8** | **Aucun appel sortant**, `wp_mail` excepté. Aucun script, aucun cookie, aucun en-tête. |
| **I-9** | **Aucune écriture d'option dans le chemin nominal.** Le verrou n'est écrit que lorsqu'une alerte part, et il est borné à 30 entrées. |
| **I-10** | **Aucune référence à `Massifs\Ingest\Prefecture\*`**, à ses options ou à ses filtres ; la chaîne `'prefecture'` est absente des deux modules. |
| **I-11** | La surface publique côté thème est **inchangée** : zéro fonction, zéro route, zéro clé, zéro `etat` ajoutés. |
| **I-12** | `bandeau-peremption.php` rend **zéro octet** dans les quatre cas d'anomalie et quand `perimee !== true`. Il ne rend **jamais** de bannière sur une valeur non strictement `true`. |
| **I-13** | `bandeau-peremption.php` **n'appelle ni `massifs_horodatage()`, ni `massifs_saison()`, ni aucune fonction de statut**, et ne construit **aucune date nue**. Il est le seul gabarit de statut indépendant de la dette B-1. |
| **I-14** | La bannière **s'ajoute** : quand `perimee === true` **et** `etat_global === 'disponible'`, le chiffre de l'ardoise, le titre, la ligne de fraîcheur, la liste et la carte sont rendus **à l'identique**. |
| **I-15** | **Aucun octet de CSS n'est ajouté par cette issue**, et aucun n'est nécessaire : `.bandeau-alerte--peremption` n'a **aucune règle propre**, comme les trois variantes déjà en service. |
| **I-16** | `massifs_donnee_perimee_constatee` est orthographié **de façon identique** dans l'émetteur et dans l'abonné (§3.1). |

---

## 11. Arbitrages

### A-1 — « sur la carte ET dans la liste » : mon cadrage était faux, le plan front avait raison

**Désaccord réel, et il portait sur le périmètre même de l'issue.** J'avais transmis aux deux leaddev que
l'absence de mention de péremption dans `liste-statuts.php` et `carte.php` était « le manque réel » au
regard du §4.2. `leaddev-front-cms` l'a **contesté, sources à l'appui**, et il a raison :

- La phrase du §4.2 — « sur la carte ET dans la liste » — a pour sujet l'état **`indisponible`**, pas la
  péremption. Cet état **est** déjà rendu dans les deux (`etats-vides.php` l. 155 ; panneau de carte,
  contrat #7).
- La bannière de plus de 24 h relève du **§4.5**, et le contrat #3 arbitrage A-5 a **déjà statué** que ce
  sont **deux règles disjointes** : « §4.2, absolue et sans seuil […] §4.5, seuil de 24 h […] déclenche
  UNIQUEMENT une bannière. Les fusionner produirait soit des faux positifs, soit un trou de sécurité. »
- Cinq contrats gelés convergent sur « niveau page, jamais répétée » : #3 interdit 9, #6 interdit 9,
  **#7 interdit 14** (« Rendre une **seconde** bannière de péremption »), #8 §3, #22.

**Décision : une bannière unique, de niveau page, en amont de la bande carte et de la bande liste.**
Elle est donc rencontrée avant l'une comme avant l'autre — à la lecture, au clavier, au lecteur d'écran
et à l'impression. **Répéter la péremption dans 25 lignes de liste et dans le panneau de carte serait un
défaut**, pas une couverture plus complète.

Si l'orchestrateur juge malgré tout qu'une répétition est due, c'est un **amendement de l'interdit 14 du
contrat #7** et une issue distincte — **jamais une extension silencieuse de celle-ci**.

### A-2 — Le sort du `<p class="ardoise__peremption">` : recommandation A, repli B, jamais de conditionnement

`front-page.php` l. 362 rend déjà `<p class="ardoise__peremption">Donnée périmée.</p>`, et
`tests/rendu/recette-rendu.mjs` l. 818 **asserte sa présence** (`count() === 1`). **Les deux fichiers
sont hors empreinte.**

Deux mentions identiques à moins de 100 px l'une de l'autre sont un défaut de micro-rédaction (§7 du
brief, « vocabulaire constant d'un bout à l'autre ») et, pour un lecteur d'écran, la même phrase lue deux
fois de suite.

| Issue | Ce qu'on gagne | Ce qu'on perd |
|---|---|---|
| **A — retirer le `<p>`** *(recommandée)* | Une seule mention, à la forme §8.3, verrouillée par un test **plus fort**. La dette A-4 du contrat #5 et le reste-à-faire 5-4 du contrat #6 sont **soldés** | Deux écritures hors empreinte, dans un lot parallèle |
| **B — garder les deux** *(repli acceptable)* | Zéro écriture hors empreinte, sauf la ligne d'insertion. La DoD §12 est **servie** : la bannière existe | Une redondance visible et audible, contraire au §7 du brief et à l'esprit de l'interdit 14 du contrat #7 |
| **C — ne pas insérer la bannière** | — | **L'issue #12 n'est pas servie.** Rejeté |

**Si A est retenue, deux écritures exactes, à exécuter par qui l'orchestrateur désignera :**

1. `front-page.php` — **suppression des l. 357-364** (le commentaire de trois lignes **et** le bloc
   `if ( $massifs_peremption ) : … endif;`). **`$massifs_peremption` DOIT RESTER définie** l. 30 et
   l. 37 : elle est verrouillée comme **ancre de test** par `recette-rendu.mjs` l. 3474, et le contrat
   #27 §7 clause 7 impose qu'elle reste calculée **hors du `try`**. Elle devient inutilisée par le
   gabarit — **un commentaire d'une ligne doit l'expliquer**, sinon un refacto la supprimera et fera
   rougir le scénario 23.
2. `recette-rendu.mjs` l. 818 — **conversion, jamais suppression** : remplacer l'assertion unique par
   **deux** — `egal( 1, … '.bandeau-alerte--peremption' … )` **et** `egal( 0, … '.ardoise__peremption'
   … )`. La garantie n'est pas affaiblie, elle est **renforcée** : elle passe de « la forme dégradée
   existe » à « la forme spécifiée §8.3 existe, **et** la forme dégradée a disparu ».

**Dans tous les cas, cette chaîne ne conditionne jamais sa partie à l'existence du `<p>`, n'ajoute jamais
un second appel, et n'écrit jamais hors empreinte** (P-8).

**Séquencement à surveiller** : `front-page.php` est **la zone chaude du lot** — les chaînes #10 et #11 y
ont des emplacements réservés en commentaire l. 411-419. Les lignes visées ici sont **357-369** :
**disjointes**, mais le fichier est unique. L'orchestrateur seul peut séquencer.

### A-3 — Deux alertes distinctes, sans déduplication

Le même jour, l'administrateur peut recevoir l'alerte du connecteur (« la récupération a échoué ») **et**
celle de la veille (« ce que le site affiche est périmé »). **Décision : les deux partent.**

Dédupliquer exigerait de lire `Massifs\Ingest\Prefecture\StateRepository::was_alerted()` — espace de noms
**exclu de l'autoloader** et propriété d'une autre chaîne : c'est l'interdit S-5, et le franchir pour
économiser un courriel serait un mauvais échange. Les deux messages répondent d'ailleurs à **deux
questions différentes**, et **le courriel de la veille explique la différence en toutes lettres** (§4.6,
bloc 2) — sans quoi un gestionnaire croirait à un doublon.

Remonté comme jonction (§12) : si le doublon est jugé inacceptable, la réconciliation appartient à une
issue qui possédera **les deux** modules.

### A-4 — Le verrou est posé avant l'envoi, contrairement à `Notifier`

Voir §4.5. C'est un **écart assumé** au patron existant, avec une raison de fond : `Notifier` pose le
verrou **après** `wp_mail`, donc une `Throwable` levée par une extension SMTP tierce rejoue la tentative
**toutes les heures**. Le coût de l'inversion est borné par la granularité **par jour**.

### A-5 — Aucune fonction de lecture créée : les deux plans y sont arrivés séparément

Le plan back conclut « surface front/back **vide par décision** » ; le plan front ne demande **rien de
nouveau** — il ne lit que `massifs_fraicheur()['perimee']` et `massifs_jour_courant()`, tous deux
préexistants. **Aucun désaccord à trancher**, et c'est le meilleur résultat possible pour une issue
transverse : elle ne creuse aucune nouvelle frontière.

Le plan front énonce toutefois **trois exigences opposables au back**, que je gèle telles quelles :

| # | Exigence | Pourquoi elle est opposable |
|---|---|---|
| **B-1** | **`perimee === true` implique `dispositif_actif === true`** — garantie du contrat #3 l. 161-162, **reconduite explicitement dans ce lot** | C'est elle, **et elle seule**, qui rend « sans effet hors saison » sans que le thème calcule une saison. Si elle tombe, la bannière s'affiche hors saison et le thème **n'a aucun moyen légitime de s'en apercevoir** |
| **B-2** | **`perimee` est toujours présente et toujours un `bool`**, dans tous les états, y compris quand `dernier_releve_le === null`. Jamais `null`, jamais absente, jamais `0`/`""` | Une clé absente fait rendre zéro octet : **la DoD §12 tomberait en silence** |
| **B-3** | **`perimee` n'est jamais un filtre ni un masque** : `massifs_statuts_du_jour()` ne consulte jamais `Fraicheur`. Les statuts restent servis à l'identique quand `perimee === true` | La bannière **s'ajoute** (§8.3). Si le serveur filtrait, la bannière deviendrait un `etat` et le contrat #8 §3 se contredirait |

Les trois sont **déjà vraies** dans le code d'aujourd'hui (`Fraicheur.php` l. 72-74, contrat #3 A-5).
Elles sont gelées ici pour qu'une modification future du domaine ne les casse pas sans qu'on le voie.

### A-6 — `massifs_horodatage_jour()` (dette B-1) : laissée ouverte, délibérément

`Horodatage.php` l. 16-27 déclare que sa table de mois français est une **règle métier de fuseau**, et
écarte nommément `wp_date()`/`date_i18n()`. La dupliquer dans un module de **courriel** ou dans un
gabarit serait architecturalement absurde et créerait la seconde source de vérité que le domaine refuse.

**Décision : la dette est rendue SANS OBJET pour ce qui est livré**, et remontée intacte (§12). Le
gabarit `bandeau-peremption.php` n'affiche aucune date, donc ne construit aucune date nue : il est **le
seul gabarit de statut du thème indépendant de B-1** (I-13). Le module d'extension ne compose aucune
date non plus (§4.6). **Cette issue ne creuse pas la couture et ne la referme pas.**

---

## 12. `OUVERT` et questions bloquantes — à ne jamais combler par déduction

| # | Point | Nature |
|---|---|---|
| **Q1** | **Heure de publication préfectorale — TROIS valeurs contradictoires dans le dépôt.** `docs/BRIEF.md` §4.2 dit « vers **18-19 h** » ; `MASTER.md` §11.3 et le thème (`front-page.php` l. 199, `etats-vides.php` l. 161) affichent **au visiteur** « La préfecture publie vers **17 h** » ; `class-settings.php` l. 89-91 retient une fenêtre **16 h → 23 h**, si bien que l'alerte de fenêtre close ne part qu'à partir de 23 h locales. **Ces trois nombres ne peuvent pas être tous exacts, et le deuxième est une affirmation factuelle servie au public.** Sans effet sur cette issue — aucun de ses livrables n'encode d'heure (I-4) — mais **bloquant pour le projet**. | **Arbitrage du propriétaire, jamais une invention.** Détermine à la fois la copie du front et l'heure à laquelle une absence devient un incident |
| **Q2** | **Insertion de `bandeau-peremption.php`** — §6.2, plus l'arbitrage **A-2** sur le sort du `<p class="ardoise__peremption">` et la conversion de `recette-rendu.mjs` l. 818. **Sans cette jonction, la partie est du code mort et la DoD §12 tombe en silence (A-13).** | **Décision d'orchestration, au niveau du lot.** Recommandation : insertion + issue A |
| **Q3** | **La bannière réduite à « Donnée périmée. » est signalée comme sous-dimensionnée.** Elle **ne dit pas quoi faire**, ce que `MASTER.md` §11.1 règle 3 exige des messages d'anomalie, alors que les trois autres bandeaux d'alerte offrent tous une suite. Formulation **recommandée à la révision `lead-design-cms`, non anticipée d'un octet** : « Donnée périmée. Consultez la carte officielle de la préfecture. » + lien — elle n'emploie que des fragments **déjà présents mot pour mot** au §11.3, et n'exigerait que de lire `massifs_attribution_statuts()['carte_officielle_url']`. **Un âge lisible exigerait en plus une chaîne pré-formatée par le serveur** (nom proposé : `massifs_fraicheur()['age_lisible']`, `string`, vide si inconnu) — **à ne pas construire aujourd'hui**, le thème ne formatant jamais une durée (§11.1 règle 6). | **Révision `lead-design-cms`.** `OUVERT` : ne jamais combler par déduction |
| **Q4** | **Dette B-1 — `massifs_horodatage_jour()` n'existe pas.** Signature suggérée : `massifs_horodatage_jour( string $jour ): array{attr_datetime, date_longue, date_courte}` — **sans clé `heure`**, qui est précisément le piège de la couture actuelle. Cinq points d'appel à nettoyer : `front-page.php` l. 182-194 (proposition « Reprise le {date}. » **omise** du `h1`) et l. 298-315 ; `liste-statuts.php` l. 175-181 ; `carte.php` l. 145-156 et 246 ; `etats-vides.php` l. 133-140 (couture `$jour . 'T12:00:00Z'`). | **Demande de domaine**, `includes/domain/fraicheur/` — **hors empreinte**. Sans effet sur cette issue (A-6, I-13) |
| **Q5** | **Doublon d'alerte** — voir A-3. Si le doublon est jugé inacceptable, la réconciliation appartient à une issue possédant **les deux** modules. Et **vocabulaire de journalisation** du thème : `_doing_it_wrong()` (retenu, homogène avec `etats-vides.php`) contre `massifs_journaliser()` (préférence documentée en `front-page.php` l. 220-224). **Signalé, non tranché en silence.** | Décision d'orchestration ou issue ultérieure |
| **Q6** | **`t_reset()` (`tests/bootstrap.php`, hors empreinte) ne purge pas `massifs_alertes_verrous`.** Les scénarios `50-…` à `54-…` le purgent eux-mêmes par `delete_option( Verrou::OPTION )` — **rien ne bloque**. Si l'uniformité est préférée, une ligne dans `t_reset()` est à affecter à qui détient ce fichier. | Cosmétique, à affecter |
| **Q7** | **Bruit sur la stack Docker** : en saison, base vierge, la veille alertera **une fois par jour**. C'est **voulu** — `portee-non-publiee.md` §2 pose « fraîcheur, péremption : réel » — et sans MTA, `wp_mail()` échoue, le verrou est posé, une tentative par jour au maximum. Si le silence est souhaité en développement, `MASSIFS_VEILLE_FRAICHEUR_DESARMEE` se pose dans `docker/**`, **hors empreinte**. **La veille n'est PAS désarmée par défaut en `local` : ce serait rouvrir le trou n° 1.** | À affecter à `docker-cms` si le silence est souhaité |
| **Q8** | **Alignement de la bannière sur grand écran** : en plein cadre, le texte commence à `--esp-m` du bord de fenêtre alors que le reste de la page est bridé à `--largeur-max` (1200 px). Visible **au-dessus de 1280 px seulement**. Non corrigeable ici (`assets/css/**` hors empreinte) et **surtout pas par un `.bande__contenu`**, qui coûterait 96 px de vide tous les jours nominaux (P-4). Crochet laissé à `dev-ux-cms` : `.bande--peremption`. **Question pour `lead-design-cms` : la bannière §8.3 est-elle plein cadre ou bridée à la colonne de contenu ?** `MASTER.md` §8.3 ne le dit pas | `OUVERT`, non bloquant |
| **Q9** | **Hachure §8.3 non rendue** — la hachure `--c-mistral` à 45° « à faible opacité » n'est écrite pour **aucune** variante de bandeau d'alerte, faute de jeton d'opacité (`composants.css` l. 499-501 : « inventer la valeur serait pire que l'omission »). La variante `--peremption` **hérite** de cette absence, déjà signalée et partagée par les trois autres. Aucune information n'en dépend, le sens étant dans le premier mot. **Non ouvert par cette issue** | Cosmétique connu, non bloquant |

---

## 13. Scénarios de recette — plage 50-59, propriété de cette chaîne

Aucun ne porte le suffixe `.arme.php` et aucun n'appelle `t_armer_connecteur()` — **c'est en soi
l'assertion centrale de l'issue** : sur la stack Docker où `Settings::is_disabled()` est vrai et où
`massifs_prefecture_recuperation` n'existe pas, **`massifs_veille_fraicheur` est planifié**.

| Fichier | Histoire vérifiée |
|---|---|
| `50-veille-armee-connecteur-desarme.php` | Le connecteur est désarmé sur cette stack ; **la veille est planifiée quand même**, en `hourly`, en **une seule** occurrence ; `assurer()` est idempotent. *(Trou n° 1, I-1.)* |
| `51-peremption-alerte-unique.php` | En saison, relevé vieux de 30 h **ou aucun relevé** ⇒ `perimee === true` **et exactement 1** courriel, dont le corps cite la source, l'âge et le seuil ; seconde exécution ⇒ **0** courriel ; verrou purgé ⇒ 1 de nouveau. *(Trou n° 2, I-5.)* |
| `52-hors-saison-silence.php` | Jour d'hiver, aucun relevé ⇒ `massifs_fraicheur( $jour )['perimee'] === false` **et 0 courriel**. *(I-2.)* |
| `53-veille-desarmee.php` | Filtre à `false` ⇒ `assurer()` **retire** l'évènement ; `executer()` n'émet ni action ni courriel, **même avec une donnée périmée**. |
| `54-donnee-fraiche-et-desactivation.php` | Relevé de l'instant ⇒ 0 courriel, **aucune écriture d'option** ; puis `deactivate_plugins()` ⇒ `wp_next_scheduled( HOOK ) === false` **et** plus aucun crochet `massifs_*` dans le cron ; réactivation en fin de scénario. *(I-7, I-9.)* |

**Surface publique strictement nécessaire à la recette**, et rien de plus :
`Planificateur::HOOK` · `Planificateur::assurer()` · `Veille::executer( ?string $jour )` ·
`Verrou::OPTION` · le filtre `massifs_veille_fraicheur_armee` · `massifs_enregistrer_releve_reussi()`
(**fonction de domaine publique existante**, aucune instrumentation ajoutée) · `t_intercepter_mail()`
(harnais existant).

---

## 14. Ce que la review doit regarder en premier

1. **`register_deactivation_hook` présent ?** Sans lui, `tests/scenarios/20-cron-complet.arme.php`
   l. 100-108 **passe au rouge** — test existant, hors empreinte (§4.3, I-7).
2. **`grep` de `Prefecture`, `'prefecture'`, `massifs_prefecture_` sur `includes/ingest/cron/**` et
   `includes/security/alertes/**`** → doit rendre **zéro** (S-5, I-10).
3. **`grep` de `wp_remote_`, `curl_`, `file_get_contents(`** sur les deux modules → **zéro** (I-8).
4. **`grep` de `seuil_secondes`, `age_secondes`, `86400`, `24`** dans une comparaison → la règle des 24 h
   ne doit être **nulle part** réécrite (I-3).
5. **`massifs_donnee_perimee_constatee` orthographié à l'identique** dans l'émetteur et l'abonné — un
   seul `n` à `donnee`. Une divergence **n'émet aucune erreur PHP** et rend l'alerte muette (§3.1, I-16).
6. **`module.php` écrit en dernier**, et **aucun `require_once`** dedans (S-1, S-4).
7. **`bandeau-peremption.php` rend-il vraiment zéro octet** dans les quatre cas d'anomalie et sur
   `perimee === false` ? À vérifier sur le rendu, pas sur l'intention (I-12).
8. **Le gabarit n'appelle-t-il vraiment aucune fonction de date ?** `grep massifs_horodatage` sur le
   fichier → **zéro** (I-13).
9. **Aucun octet de CSS ajouté** par cette issue (I-15).
10. **La bannière s'ajoute-t-elle sans rien masquer ?** Avec `perimee === true` **et**
    `etat_global === 'disponible'`, le chiffre de l'ardoise doit **rester présent** (I-14).
