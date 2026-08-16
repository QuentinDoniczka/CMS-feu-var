# Contrat d'interface — Issue #5 — Squelette du thème sur mesure et gabarit de la page d'accueil

**Gelé le 12 août 2026** par `lead-issue-cms` (chaîne #5). Liant à partir de ce point.

> **RÉVISION 7 — 17 août 2026, issue #29 « Garder les appels à `massifs_horodatage()` dans
> `front-page.php` pour éviter une page tronquée ».** Les trois appels à `massifs_horodatage()` de la
> ligne de fraîcheur n'avaient **aucune garde**, et ils s'exécutent **après le premier octet de sortie**.
>
> **CORRECTION FACTUELLE — le défaut décrit par l'issue #29 n'est pas celui qui existe.**
> L'`\InvalidArgumentException` visée est **inatteignable par tout chemin de donnée** : les trois instants
> sont assainis en amont (`RegistreReleves::entree()` l. 172-182 re-parse et **supprime la clé** en cas
> d'échec ; `evalue_le` est produit par la machine et déclaré `readonly string`). Le déclencheur
> **réellement atteignable** est un `\TypeError` — `strict_types=1` plus `massifs_horodatage( string )`
> plus une lecture de `evalue_le` **sans garde de type** — et `\TypeError extends \Error` quand
> `\InvalidArgumentException extends \LogicException` : **la garde demandée par l'issue ne l'aurait pas
> attrapé.** Les deux sont donc traités : `is_string()` ferme le trou réel, le `try/catch` est une défense
> en profondeur assumée. Contrat complet : `docs/contracts/issue-29.md`, arbitrage **A-39**.
>
> **`is_string( $tableau['cle'] )` ne viole PAS l'interdit `isset()`/`??` de ce contrat** (l. 121) :
> l'accès reste **direct**, l'avertissement `Undefined array key` est **conservé**, `is_string()` est une
> fonction qui reçoit une valeur déjà lue. Arbitrage **A-41**.
>
> **Répercussion normative dans ce contrat : le tableau de la section « La ligne de fraîcheur — variantes
> par omission seule » était FAUX depuis son gel.** Sa troisième ligne décrivait un rendu que le code ne
> produit pas. Il est remplacé ci-dessous par un tableau à **quatre** combinaisons. Arbitrage **A-44**.
>
> **Aucune ligne d'extension n'a été écrite**, aucune demande nouvelle portée au back : l'issue consomme
> une surface serveur déjà gelée.

> **RÉVISION 6 — 13 août 2026, issue #27 « Réconcilier la garde du `match()` de `front-page.php` avec
> l'arbitrage écran-blanc de la chaîne #6 ».** Le `match()` de l'ardoise s'exécute **avant le premier
> octet de sortie** et **avant toute inclusion de partie**, et il n'était **ni gardé ni enveloppé**. Un
> `etat_global` hors de ses quatre bras levait `\UnhandledMatchError`, que `WP_Fatal_Error_Handler`
> convertit en **HTTP 500 + la page « Erreur critique sur ce site. » du cœur de WordPress** — donc **zéro
> statut, zéro lien officiel**, aucun de nos jetons, hors de toute vérification d'accessibilité, et un
> contenu qui ne nous appartient pas. Le brief §4.2 et la contrainte non négociable #3 étaient annulés.
>
> **CORRECTION FACTUELLE — ce n'était pas un « écran blanc ».** `WORDPRESS_DEBUG: 0` et aucun
> `wp-content/php-error.php` dans le dépôt : l'`E_ERROR` est capté par le cœur. Le défaut est aussi grave
> que décrit, **pour une autre raison que celle invoquée**. Un contrat qui invoque un fait faux se fait
> contredire en revue.
>
> **La mitigation censée couvrir ce risque était inatteignable.** Les **sept** `match()` de
> `templates/parts/**` sont enveloppés d'un `try/catch ( \UnhandledMatchError )` (contrat #6, arbitrage
> E) précisément pour cela — mais le `throw` de l'ardoise précédait les inclusions. **Deux contrats gelés
> donnaient la réponse inverse au même risque sur le même chemin de rendu, et c'est celui qui disait
> « panne » qui gagnait, parce qu'il s'exécute le premier.**
>
> **Deux déclencheurs, pas un seul.** (1) un cinquième `etat_global` ajouté à `api.php` ; (2) la clé
> `etat_global` **renommée ou retirée** — l'accès étant direct, sans `isset()` ni `??` (interdit
> délibéré de ce contrat), l'expression vaut alors `null` et `match(null)` lève la **même** erreur. **Le
> second est le plus probable des deux.** L'arbitrage originel n'était donc pas naïf : il était
> proportionné à un risque de **code** (aucun `apply_filters` ne traverse `etat_global`, chaîne
> `if/elseif` fermée, `api.php` l. 223-231) ; ce qui le renverse, ce sont **la sévérité et le lieu** — la
> sanction tombait sur le visiteur anonyme de l'accueil — plus le déclencheur (2).
>
> **Décision : enveloppe `try/catch ( \UnhandledMatchError )` au niveau page, calquée sur l'arbitrage E.**
> Le `match()` **conserve ses quatre bras et son absence de `default`** : l'interdit 11 du contrat #3 est
> honoré **à la lettre** et **dans son intention**, cet interdit visant la **non-silence** de l'ajout d'un
> état, jamais la panne. **Le contrat #3 n'est pas révisé et n'a pas besoin de l'être : #27 généralise
> #6, il ne renégocie pas #3.**
>
> Répercussions dans ce contrat : une ligne au tableau des états spéciaux, la réécriture de la section
> « Le `match()` — sans bras `default` », et les arbitrages **A-31** à **A-38**. Contrat complet de
> l'issue : `docs/contracts/issue-27.md`. **Aucune ligne d'extension n'a été écrite**, aucune demande
> nouvelle portée au back.

> **RÉVISION 5 — 13 août 2026, issue #26 « Qualifier l'ardoise sur une journée de publication
> partielle ».** L'ardoise ignorait `synthese['partiel']` et rendait donc, les jours de publication
> incomplète, un dénominateur **trompeur** : « Aujourd'hui, 1 massifs sur 25 sont d'accès autorisé. »
> couvrait 24 massifs de statut en réalité **inconnu** ce jour-là. L'erreur penchait du côté sûr — elle
> n'impliquait aucune autorisation implicite — mais l'ardoise, **premier message lu de la page**, restait
> littéralement fausse par omission, en écart au brief §4.2 étendu (« ne jamais présenter un compte global
> comme complet quand il ne l'est pas ») et au §5.1.
>
> **Arbitrage du propriétaire du projet** : dénominateur = **massifs renseignés**, plus une mention
> explicite du manque. La journée complète est **inchangée**. Répercussions dans ce contrat : le tableau
> des clés consommées (trois clés ajoutées, déjà offertes par le contrat #3), une ligne
> `publication_partielle` au tableau des états spéciaux, la section « la publication partielle n'est pas
> un état », les trois gabarits de la phrase de synthèse, une correction factuelle sur leur provenance,
> et les arbitrages **A-27** à **A-30**.
>
> **Aucune ligne d'extension n'a été écrite** : l'issue consomme une surface serveur déjà gelée.

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
| `massifs_synthese_du_jour( massifs_codes(), null )` | `etat_global` · `partiel` · `total` · `disponibles` · `sans_donnee` · `par_niveau['autorise']` · `jour_validite` |
| `massifs_fraicheur( null )` | `perimee` · `publie_prefecture_le` · `dernier_releve_le` · `evalue_le` · `jour_validite` |
| `massifs_saison( null )` | `prochaine_ouverture` |
| `massifs_horodatage( $instant )` | `date_longue` · `heure` · `attr_datetime` |
| `massifs_attribution_statuts()` | `texte` · `carte_officielle_url` |
| `massifs_attribution()` | `phrase` |

**Dépendance déclarée** : la phrase de synthèse de l'accueil est liée à l'existence de la clé de niveau
`autorise` dans `par_niveau`. Un changement de légende qui la supprimerait casse la phrase — bruyamment,
ce qui est le comportement voulu.

**Dépendance déclarée (révision 5, issue #26)** : la phrase de synthèse dépend désormais de **quatre**
clés de synthèse au lieu d'une — `partiel`, `disponibles`, `sans_donnee` et `par_niveau['autorise']`.
Les trois premières sont **déjà gelées côté back** par le contrat #3 (`docs/contracts/issue-3.md` l. 98-99 :
`partiel bool · total int · disponibles int · sans_donnee int`) et **vérifiées dans le code**
(`includes/domain/statuts/api.php` l. 233-243). L'issue #26 n'a donc porté **aucune demande nouvelle au
back** : elle élargit la surface **déjà offerte** que le thème consomme. `niveau_le_moins_severe` et
`niveau_le_plus_severe` restent **non consommés**.

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
| `publication_partielle` (**issue #26**) | `synthese['partiel'] === true` | **Variation INTERNE au bras `disponible`, jamais un cinquième `etat_global`.** Dénominateur = `disponibles` au lieu de `total`, sur le chiffre **et** dans le `h1`, qualifié « renseigné(s) ». Phrase **ajoutée** « {`sans_donnee`} massif(s) reste(nt) sans information du jour. » dans un `<p class="ardoise__publication-partielle">`, **après le `h1`, avant la ligne de fraîcheur**. Aucun bras ajouté au `match()` |
| `information_indisponible` (`indisponible`) | `synthese['etat_global']` | `h1` « Information du jour non disponible. Consultez la carte officielle de la préfecture. » + lien `carte_officielle_url`. **Jamais de chiffre.** |
| `hors_saison` | `synthese['etat_global']` | `h1` **tronqué à sa première proposition** : « Dispositif estival inactif. » — voir arbitrage A-1 |
| `non_encore_publie` | `synthese['etat_global']` | `h1` « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. » Bras inatteignable aujourd'hui, écrit quand même (`match` sans `default`) |
| **`etat_global` inconnu** (**révision 6, issue #27**) — cinquième état, **ou** clé `etat_global` renommée/retirée ⇒ l'expression vaut `null` | **Par aucun chemin de donnée** : ni ingestion, ni base, ni portail, ni option, ni filtre. `etat_global` naît d'une chaîne `if/elseif` locale fermée (`api.php` l. 223-231) que **aucun `apply_filters`** ne traverse. Exige une modification de `api.php`, dans ce même dépôt | `try { … } catch ( \UnhandledMatchError $massifs_erreur )` autour du `match()`. Repli sur l'**ardoise d'absence** `$massifs_ardoise_absente` — état `indisponible`, **AVEC** le lien `carte_officielle_url` (l'URL est obtenable, `$massifs_api === true`). **Jamais de chiffre** : `chiffre`, `chiffre_total`, `publication_partielle` vides, `fraicheur => false`. Clé `journal` **distincte** du bras `indisponible` normal, émise par `massifs_journaliser()`. **Aucun `_doing_it_wrong()`** (A-33). Le `catch` **ne touche pas** `$massifs_peremption` : le repli se comporte exactement comme le bras `indisponible` réel |
| `donnee_perimee` | `fraicheur['perimee'] === true` | phrase « Donnée périmée. » **ajoutée** sous la ligne de fraîcheur. Ne masque, ne remplace, ne conditionne **rien** |
| `referentiel_indisponible` | `massifs_codes() === []` | `total = 0` et `etat_global = 'indisponible'` → branche indisponible, sans cas particulier |
| `couche_effis_indisponible` | — | **hors périmètre** : aucune couche EFFIS n'existe |
| API absente | garde `function_exists` | branche indisponible, sans lien |

### Le `match()` — sans bras `default`, **sous enveloppe** (révisé, issue #27)

```php
$massifs_ardoise_absente = array( /* ardoise d'absence, indisponible AVEC le lien */ );

try {
    $massifs_ardoise = match ( $synthese['etat_global'] ) {
        'disponible'        => …,
        'indisponible'      => $massifs_ardoise_absente,
        'hors_saison'       => …,
        'non_encore_publie' => …,
    };
} catch ( \UnhandledMatchError $massifs_erreur ) {
    $massifs_ardoise            = $massifs_ardoise_absente;
    $massifs_ardoise['journal'] = 'massifs: etat_global hors des quatre états du gabarit — …';
}
```

Les **quatre bras et l'absence de `default`** sont imposés par le contrat #3 et **conservés**.

> **SUPERSÉDÉ — révision 6, issue #27.** Ce contrat écrivait : « Un cinquième état lève
> `UnhandledMatchError` sur l'accueil public : c'est délibéré. Sur une donnée de sécurité, l'échec
> bruyant vaut mieux qu'un rendu silencieusement faux. » **La phrase est conservée pour l'histoire, elle
> n'est plus le comportement.** Le `throw` n'était pas un 500 délibéré : c'était un **bord non
> réconcilié** entre deux contrats gelés, dont l'un (#6, arbitrage E) enveloppait le même risque à
> quelques lignes de là. Ce qui était juste dans cette phrase — l'échec doit être **bruyant** — est
> conservé ; ce qui était faux — bruyant *signifie fatal* — est abandonné.
>
> **Comportement délibéré, désormais** : quatre bras, aucun `default`, **enveloppés d'un
> `try/catch ( \UnhandledMatchError )`** qui retient l'**absence**, jamais une donnée, et **jamais un
> chiffre**. L'interdit 11 du contrat #3 est honoré **à la lettre** (aucune branche « sinon » dans le
> `match`) **et dans son intention** : son motif écrit est qu'un `if/else` « rendrait silencieux l'ajout
> d'un cinquième état », or l'ajout reste ici **bruyant** — par un rendu dégradé **visible** sur la page
> la plus lue du site, plus une ligne de journal sous `WP_DEBUG`. **Le contrat #3 n'est pas révisé.**
>
> L'enveloppe **ne peut pas masquer un bras légitimement ajouté** : un cinquième bras écrit est retenu par
> le `match()`, et le `catch` n'est alors jamais atteint. C'est la propriété qu'une liste blanche d'états
> en amont ne possède pas — raison pour laquelle elle est **explicitement rejetée** (interdit 2 du contrat
> #27).

**`$massifs_ardoise_absente` est déclarée à l'intérieur de la garde `if ( $massifs_api )`**, jamais
au-dessus : elle appelle `massifs_attribution_statuts()`, absente quand `$massifs_api === false`. Elle
n'est **jamais** réutilisée dans la branche `else`, dont la phrase tient en **un seul fragment** et
**sans** lien.

**Le chiffre n'est écrit que dans le bras `disponible`**, et ce bras produit à la fois le chiffre et sa
phrase. La règle « jamais un statut périmé présenté comme courant » est donc tenue structurellement,
pas par vigilance.

#### La publication partielle n'est pas un état (révision 5, issue #26)

`partiel` est un drapeau **orthogonal** à `etat_global`, au même titre que `fraicheur['perimee']`. Il est
lu **exclusivement à l'intérieur du bras `disponible`** — donc, PHP n'évaluant que le bras retenu, il
n'est pas même lu dans les trois autres états. Le `match()` conserve ses **quatre** bras et son absence de
`default`. Toute évolution qui ferait de la publication partielle un cinquième `etat_global` est une
rupture de ce contrat.

**Le dénominateur reste un ternaire explicite sur `partiel`.** Employer toujours `disponibles` produirait
aujourd'hui un rendu identique en journée complète : dans le bras `disponible`, `disponibles > 0`, donc
`partiel === false` ⇒ `sans_donnee === 0` ⇒ `disponibles === total`. Cette égalité est un **fait de code,
pas un fait de contrat** — le contrat #3 déclare les quatre clés sans jamais énoncer de relation entre
elles — et « sur 25 » (les 25 massifs du référentiel, brief §5.1) ne veut pas dire « sur 25 renseignés ».
La simplification est **explicitement rejetée** : elle masquerait le glissement de sens, sans le moindre
diff, le jour où le référentiel passerait à 26.

**Choisir n'est pas calculer.** Le gabarit **sélectionne** entre deux valeurs serveur (`disponibles` ou
`total`) ; il n'en dérive aucune. `sans_donnee` est **lu**, jamais écrit `total - disponibles`, bien que
ce soit arithmétiquement identique (`api.php` l. 221) : `MASTER.md` §16 fait du thème calculant lui-même
un décompte ou un dénominateur un **défaut bloquant**.

**La répétition des accès de clés dans le bras est délibérée.** Un bras de `match()` est une expression,
pas un bloc : aucune variable locale n'y est déclarable. `par_niveau['autorise']` y est lu quatre fois,
`partiel` trois fois. C'est le **prix de la garantie structurelle** — hisser ces lectures au-dessus du
`match()` laisserait en mémoire, dans les états `hors_saison` / `indisponible` / `non_encore_publie`, une
phrase chiffrée plausible et fausse, à portée de copier-coller. **Aucun `refacto-cms` ne doit factoriser
ces lectures au-dessus du `match()`.**

> **PORTÉE PRÉCISÉE — révision 6, issue #27.** Cet interdit porte sur **quatre clés nommées** —
> `par_niveau['autorise']`, `partiel`, `disponibles`, `sans_donnee` — lues dans le **seul** bras
> `disponible`, et son motif écrit est de ne laisser aucune **phrase chiffrée plausible et fausse** en
> mémoire dans un état sans chiffre. **Critère de revue opposable : une variable hissée au-dessus du
> `match()` est un défaut si et seulement si elle peut contenir un nombre, ou une phrase contenant un
> nombre.**
>
> `$massifs_ardoise_absente` (issue #27) ne lit **aucune** de ces quatre clés et ne contient **aucun
> chiffre** : `chiffre` et `chiffre_total` sont des chaînes vides, `publication_partielle` est vide,
> `fraicheur` vaut `false`, et sa seule valeur serveur est une **URL**. Il est **l'inverse exact** du
> danger visé, et le hisser **renforce** la garantie structurelle au lieu de l'entamer, puisqu'il rend
> l'unique chemin de repli **provablement** dépourvu de chiffre (assertion 4 de la recette R-27). La
> garantie « le chiffre n'est écrit que dans le bras `disponible` » reste **intégralement vraie**.

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

> **CORRECTION FACTUELLE (révision 5, issue #26).** Le paragraphe ci-dessus range la phrase de synthèse
> de l'accueil parmi les « chaînes reprises mot pour mot de §11.3 / §11.4 ». **C'est inexact, et vérifié
> comme tel.** La liste du §11.3 est une liste **fermée de huit chaînes** (non-officialité, fraîcheur,
> indisponible, hors saison, non encore publié, consigne absente, EFFIS, attribution) et la phrase de
> synthèse n'en fait pas partie ; le §11.4 ne fige que les **sept chaînes de la préfecture**, dont aucune
> n'est concernée. La phrase n'apparaît que (a) dans le **croquis** du §7.1 — dont le §7.1 dit lui-même
> qu'il « nomme des zones », et dont la divergence §17-2 pose « un croquis est une intention de
> composition, pas une mesure » — et (b) au **brief §5.1**, entre parenthèses, avec les caractères de
> substitution X et Y et **sans point final**. Corriger son accord grammatical ne heurte donc **aucun
> verbatim officiel**. Voir l'arbitrage **A-27**.

### La phrase de synthèse du jour — trois gabarits (révision 5, issue #26)

Rédigés par le thème. Apostrophes en **U+2019** (A-15). Le thème **sélectionne** entre valeurs serveur ;
il n'en calcule aucune.

| Condition | Gabarit | Dénominateur |
|---|---|---|
| `partiel === false` | `Aujourd’hui, {X} {massif\|massifs} sur {Y} {est\|sont} d’accès autorisé.` | Y = `total` |
| `partiel === true` | `Aujourd’hui, {X} {massif\|massifs} sur {Y} {renseigné\|renseignés} {est\|sont} d’accès autorisé.` | Y = `disponibles` |
| `partiel === true` | `{Z} {massif\|massifs} {reste\|restent} sans information du jour.` | Z = `sans_donnee` |

X = `par_niveau['autorise']`. Le mot `renseigné` **n'est jamais rendu en journée complète** : l'accord ne
se pose donc jamais sur `total`.

**Règle d'accord : pluriel à partir de 2** — 0 et 1 au singulier, donc un test `> 1`, **jamais** `=== 1`.
Trois accords **indépendants**, dont aucun ne se déduit d'un autre : `massif`/`sont` sur X,
`renseigné` sur Y, `reste` sur Z. Le cas mixte X ≤ Y est réel et doit être recetté :
« Aujourd’hui, 0 massif sur 5 renseignés **est** d’accès autorisé. »

**`_n()` est INTERDIT ici.** Le thème déclare `Text Domain: massifs` mais ne charge **aucun catalogue** de
traduction ; la règle de repli de WordPress est `n == 1`, qui rendrait « **0 massifs** » là où le français
écrit « 0 massif ». Les ternaires `> 1` sont la forme **correcte**, pas un raccourci — commentés comme
tels dans le gabarit pour qu'aucun relecteur ne « corrige » vers `_n()`.

Portée : l'accord corrige aussi un défaut **préexistant** de la journée complète, où `massifs` et `sont`
étaient figés dans le gabarit. Le cas X = 0 d'une journée complète — les 25 massifs interdits, soit le
pic de canicule d'août, **le rendu le plus visible de la saison** — écrivait « 0 massifs … sont ».

**Apostrophes** : U+2019 pour toute prose rédigée par le thème ; les chaînes officielles de §11.4 sont
reproduites **octet pour octet**, `Niveau d'Accès` en U+0027 et `Zones d’Accueil` en U+2019 compris.

### La ligne de fraîcheur — variantes par omission seule

Gabarit §11.3. Les variantes sont produites **uniquement en supprimant la proposition dont la valeur
serveur est `null`**, jamais en réécrivant des mots :

> **CORRIGÉ — révision 7, issue #29 (arbitrage A-44).** Le tableau à trois lignes gelé jusqu'ici était
> **factuellement faux**. Sa ligne « `dernier_releve_le === null` ⇒ *Statuts du {date}.* » décrivait un
> rendu que le code **ne produit pas** : sous la **seule** condition `dernier_releve_le === null`, le code
> produit « Statuts du {date}, **publiés la veille à {heure} par la préfecture.** ». Le rendu « Statuts du
> {date}. » correspond à la **conjonction des deux nulles**. Le tableau n'était pas davantage une liste de
> décision lue dans l'ordre : sa deuxième ligne se serait alors appliquée à la quatrième combinaison, en
> prétendant rendre un relevé inexistant. Les **quatre** combinaisons réelles sont énumérées ci-dessous.

Les quatre combinaisons, `validite` présente. Le séparateur est porté **par la proposition qu'il
introduit** (`', '` par `publication`, `' — '` par `releve`) et le point final est ajouté à l'assemblage :
c'est ce qui rend l'omission **pure** possible — retirer une proposition retire son séparateur avec elle,
sans virgule orpheline, sans double espace, **sans qu'un seul mot soit réécrit**.

| `publication` | `releve` | Rendu |
|---|---|---|
| présente | présente | « Statuts du {date}, publiés la veille à {heure} par la préfecture — relevés sur ce site le {date} à {heure}. » |
| **absente** | présente | « Statuts du {date} — relevés sur ce site le {date} à {heure}. » |
| présente | **absente** | « Statuts du {date}, publiés la veille à {heure} par la préfecture. » |
| **absente** | **absente** | « Statuts du {date}. » |

**Cinquième cas — `validite` absente** (passerelle A-1 fausse, type invalide, ou `\InvalidArgumentException`
attrapée) : la clé n'entre pas dans `$massifs_propositions`, le test d'assemblage est faux, et **toute la
ligne de fraîcheur est omise**, `<p class="ardoise__fraicheur">` compris. `publication` et `releve` ont
pu être construites en mémoire : elles ne sont **jamais lues**. **Aucun octet n'est émis**, et le chemin
journalise.

> **Depuis la révision 7**, « absente » recouvre trois causes et non plus une seule : valeur `null`
> (nominal), valeur non-`string` (garde `is_string()`), instant refusé par `massifs_horodatage()`
> (`catch`). **Les trois produisent exactement le même rendu** — l'omission — ce qui est la propriété qui
> rend la dégradation sûre : la page est moins complète, elle n'est jamais fausse.

Les instants (`publie_prefecture_le`, `dernier_releve_le`) sont des ISO valides et passent par
`massifs_horodatage()`. Les dates s'affichent dans un `<time datetime="…">` alimenté par `attr_datetime`.

> **PRÉCISION (révision 5, issue #26)** — phrase ci-dessus trop elliptique, relevée par `refacto-cms`.
> Elle vaut pour les deux propositions bâties sur un **instant** (`publication`, `releve`). La
> proposition `validite`, elle, alimente son `<time datetime="…">` avec **`synthese['jour_validite']`**,
> un `YYYY-MM-DD` — valeur `datetime` HTML parfaitement valide et **sémantiquement juste**, puisque cette
> proposition porte un **jour civil de validité**, non un instant. Y substituer `attr_datetime` mettrait
> un instant d'évaluation là où un jour est attendu : **c'est le code qui a raison, pas la phrase.**
> Aucun changement de comportement — précision documentaire seule.

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

> **RÉVISION 4 — 12 août 2026. Correction d'un défaut trouvé par `test-integration-cms` : le
> lien d'évitement `#liste` pouvait rester orphelin.** La révision 3 documentait ce couplage
> comme « accepté » ; **c'était une erreur d'arbitrage de ma part**. Deux chemins reproduits par
> le test laissaient un lien d'évitement pointant vers une ancre inexistante — extension
> désactivée, et partie `liste-statuts.php` absente. Attendu `[]`, obtenu `["#liste"]`.
>
> **Arbitrage A-26.** `is_front_page()` **n'est pas** la garde exigée par la dépendance 5-2 du
> contrat #6. La garde du second lien d'évitement devient la **conjonction de trois conditions**,
> évaluées dans `templates/header.php` :
> 1. `is_front_page()` — l'ancre n'existe que sur l'accueil (comportement voulu, conservé) ;
> 2. **l'API de lecture existe** : `function_exists( 'massifs_referentiel' ) && function_exists( 'massifs_statuts_du_jour' )` ;
> 3. **la partie est localisable** : `'' !== locate_template( 'templates/parts/liste-statuts.php' )`.
>
> La condition 2 **reflète exactement** la garde de `liste-statuts.php` (l. 61-66) : #5 ne passant
> **aucun `$args`**, `$massifs_fournis` et `$statuts_fournis` y valent `false`, et la garde de la
> partie se réduit à ces deux `function_exists`. La partie le documente d'ailleurs elle-même :
> « L'appelant garde son lien d'évitement sur la même condition ». La condition 3 couvre le cas
> que la dépendance 5-2 **ne** couvre pas — fichier absent alors que les fonctions existent.
>
> **Écartées** : faire porter le lien par la partie (elle appartient à #6, **hors de mon
> empreinte**) ; s'appuyer sur la valeur de retour de `massifs_partie()` (l'en-tête est rendu
> **avant** la partie, la valeur n'existe pas encore à cet instant).

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
| **A-2** | Trois variantes de la phrase de fraîcheur, absentes de §11.3 | Approuvées, avec la **règle d'omission seule** : on supprime la proposition dont la valeur serveur est inexploitable, on ne réécrit aucun mot. **RÉVISÉ en révision 7 (A-44)** : les variantes sont **quatre**, non trois, et la troisième telle que gelée était **fausse** ; « valeur `null` » devient « valeur inexploitable » (`null`, type invalide, ou instant refusé) | Mécanique, pas éditorial. À confirmer par `lead-design-cms` |
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

| **A-27** | La phrase « {Z} massifs restent sans information du jour. » et la variante « sur {Y} renseignés » **n'ont aucune chaîne normative au §11.3**, qui est une liste **fermée** (§16 en fait un défaut bloquant en revue) | **Rédigées et rendues.** Le fond est **arbitré par le propriétaire du projet** (dénominateur = massifs renseignés + mention explicite du manque) ; le mot à mot est arbitré par le lead : « sans information **du jour** », et non « à cette heure » | « à cette heure » est une affirmation **temporelle que le gabarit ne peut pas étayer**, et qui deviendra fausse dès que le sélecteur de date du brief §5.2 affichera un jour passé. « du jour » reprend le lexique **déjà gelé** (« Information du jour non disponible », `liste-statuts.php` l. 256). Précédent : la phrase de synthèse elle-même ne figure pas au §11.3 (voir la correction factuelle ci-dessus). **Divergence signalée à `lead-design-cms`** : les trois gabarits doivent entrer au §11.3, et la divergence au §17 |
| **A-28** | Crochet CSS sans règle : `assets/css/**` est **hors de l'empreinte de #26**, la classe neuve n'aura donc aucun style | **`.ardoise__publication-partielle` est émise sans aucune règle CSS.** Ce n'est **pas** un défaut de rendu, vérifié propriété par propriété | L'élément hérite `--police-texte` / `--fs-300` / `--lh-corps` de `body`, `--c-calcaire` de `.sur-sombre` (**12,66:1**, AA large), et `--esp-s` de `.ardoise__texte > * + *` (`layout.css` l. 274) — y compris à l'impression (charbon sur blanc, 14,74:1). **Précédent identique dans le thème** : `.liste-statuts--partielle`, émise par la chaîne #6 sur **exactement le même drapeau serveur**, n'a elle non plus aucune règle. La classe est une **ancre documentée** pour une chaîne CSS ultérieure, pas un style manquant |
| **A-29** | §8.2 écrit « L'ardoise garde le chiffre du jour, son dénominateur, sa ligne de fraîcheur et son repère `--bloc` : **rien d'autre n'y entre** ». Un `<p>` supplémentaire est littéralement « autre chose qui y entre » | **Ajout assumé**, au **même traitement que le précédent A-4** : arbitrage consigné ici, divergence à enregistrer au §17 de `MASTER.md` par une chaîne ultérieure | A-4 a déjà introduit `.ardoise__peremption` dans l'ardoise sur ce même régime (« forme dégradée assumée, signalée »). L'alternative — loger la seconde phrase dans le `h1` — est **irrattrapable dans cette empreinte** : le `h1` est en `min(var(--fs-700), 3rem)`, soit jusqu'à **48 px en famille d'affichage condensée** ; sur 360 px l'ardoise gagnerait trois à quatre lignes et repousserait le bandeau de non-officialité sous la ligne de flottaison, et le corriger exigerait du CSS, **hors empreinte** |
| **A-30** | Nommage de la clé de gabarit | `publication_partielle`, **et non `partiel`** | `partiel` donnerait **le même identifiant à deux choses de types différents dans le même fichier** : `$massifs_synthese['partiel']` est un `bool` du serveur, `$massifs_ardoise['partiel']` serait une chaîne de rendu. Économiser le réalignement WPCS des cinq littéraux au prix d'un piège de lecture est un mauvais échange |
| **A-31** | **Issue #27.** Deux contrats gelés répondent l'inverse au même risque sur le même chemin de rendu : ce contrat pose le `throw` du `match()` comme délibéré, le contrat #6 (arbitrage E) l'enveloppe pour éviter la panne. **Celui qui dit « panne » gagnait, parce qu'il s'exécute le premier** | **Enveloppe `try/catch ( \UnhandledMatchError )` adoptée au niveau page**, calquée sur l'arbitrage E. Le `match()` garde ses quatre bras et son absence de `default` | Le `match()` précède le **premier octet de sortie** *et* **toutes** les inclusions de parties : il précédait donc la mitigation censée le couvrir, qui était **inatteignable**. Deux déclencheurs réels, dont l'un (clé renommée ⇒ `null`) ne suppose **aucun** cinquième état et est le plus probable. Le brief §4.2 exige l'état « information non disponible » **avec lien**, sur la carte **ET** dans la liste — ce qu'une page 500 du cœur ne peut pas rendre, et ce qui annule aussi la contrainte non négociable #3. **#27 confirme et généralise #6 ; il ne renégocie pas #3** — c'est ce qui permet de tout tenir dans l'empreinte, `docs/contracts/issue-3.md` étant hors empreinte |
| **A-32** | **Issue #27.** Le bras `indisponible` et le repli du `catch` doivent rendre **la même chose** à `journal` près : hisser en variable, ou dupliquer ? | **Hissage** de `$massifs_ardoise_absente`, **avec la précision de portée** de l'interdit d'anti-factorisation (voir l'encadré « PORTÉE PRÉCISÉE » ci-dessus). Critère de revue : *une variable hissée est un défaut si et seulement si elle peut contenir un nombre* | La **duplication est rejetée** : deux copies divergeraient à la première retouche de la phrase §11.3 ou de la source de l'URL (arbitrage F′ du contrat #6 : « deux sources d'une même valeur finissent par diverger ; une seule ne le peut pas »). Or **la divergence entre deux chemins de rendu d'un même état est exactement le défaut que #27 existe pour refermer** : dupliquer serait refermer une divergence en en ouvrant une autre, de même nature, à trois lignes d'écart. Coût réel : `massifs_attribution_statuts()` appelée dans les quatre états au lieu d'un — sans argument, sans exception, `carte_officielle_url` garanti sur ses **deux** chemins de retour (`api.php` l. 332 et 353), et **déjà appelée sans condition trois fois sur cette page** (`templates/footer.php` l. 52, `bandeau-non-officialite.php` l. 41, `liste-statuts.php` l. 129) |
| **A-33** | **Issue #27.** Canal du bruit : `_doing_it_wrong()` comme les sept `catch` de `templates/parts/**`, ou `massifs_journaliser()` ? | **`massifs_journaliser()` ; `_doing_it_wrong()` est INTERDIT dans `front-page.php`** | `_doing_it_wrong()` déclenche `trigger_error( E_USER_WARNING )`, et `E_USER_WARNING` **reste dans `error_reporting()` même avec `WP_DEBUG` à faux** : si `display_errors` était actif sur l'hébergement cible, l'avertissement s'imprimerait **dans le HTML du visiteur**. Or ce contrat interdit déjà **tout texte de repli visible par le visiteur** — y ajouter un avertissement PHP serait la même faute en pire. `massifs_journaliser()` est le **point unique** de la garde `WP_DEBUG` du thème (`functions.php` l. 55-74) et la clé `journal` est **déjà consommée** en aval : zéro plomberie neuve. Divergence de canal **assumée** avec `templates/parts/**`, hors empreinte — signalée O-27-2 |
| **A-34** | **Issue #27.** `catch` avec ou sans variable d'exception ? Nommer ou non la valeur inattendue ? | **`catch ( \UnhandledMatchError $massifs_erreur )`**, message = phrase du thème + `$massifs_erreur->getMessage()` entre parenthèses | A-33 retirant `_doing_it_wrong()` — et avec lui le backtrace —, la ligne de journal est la **seule trace existante** et doit porter le fait discriminant : `case '…'` = cinquième état, `case NULL` = la clé a disparu du contrat. **Deux causes racines, dans deux fichiers différents.** `getMessage()` retourne **toujours** une `string` (indispensable : `massifs_journaliser( string )` sous `strict_types=1` lèverait un `TypeError` sur autre chose), ne lève rien, et c'est **PHP** qui met la valeur en forme — le thème n'en compose rien. **Écartées** : relire `$synthese['etat_global']` dans le `catch` (dans le scénario « clé absente », cela émettrait un **second** `Undefined array key`, exactement la classe d'exposition que A-33 refuse) ; assainir la valeur à la main. **Mesuré** (PHP 8.2 et 8.3) : PHP **tronque** la valeur à 15 caractères + points de suspension, et imprime `NULL` en capitales — donc ne jamais asserter la fin de la ligne de journal |
| **A-35** | **Issue #27.** Le repli d'un état inconnu porte-t-il le lien officiel, ou non ? | **AVEC le lien** `massifs_attribution_statuts()['carte_officielle_url']` | Trois raisons, aucune inventée : (a) le brief §4.2 **exige** le lien ; (b) le repli **sans** lien n'existe que parce que l'URL est **inobtenable** (garde `$massifs_api === false`, arbitrage F de #6 « elle ne vient de nulle part ») — or ici `$massifs_api === true` : omettre le lien serait dégrader **sans nécessité** ; (c) **cohérence carte ET liste** : `liste-statuts.php` (l. 157-161) et `etats-vides.php` (l. 86-90) retiennent déjà `indisponible` **avec** son lien via l'arbitrage E — après #27 les trois rendus de la page **convergent sur le même état et le même lien** |
| **A-36** | **Issue #27.** Le caractère « bruyant » subsiste-t-il, `massifs_journaliser()` n'écrivant **rien** hors `WP_DEBUG` ? | **Oui, et il est porté par le rendu, non par le journal.** Conséquence enregistrée sans détour : **en production, le repli ne laisse aucune trace dans les logs** | C'est exactement l'argument de l'arbitrage E (« journal + état dégradé visible »). L'ardoise **cesse d'afficher un chiffre** et annonce « Information du jour non disponible » **sur la page la plus lue du site** : un développeur qui vient d'ajouter un état le voit à la première page chargée. Ce qui est abandonné est l'échec **fatal** ; ce qui est conservé est l'échec **visible** — ce que le contrat #3 demandait réellement |
| **A-37** | **Issue #27.** Portée du `try` : la seule affectation, ou aussi les trois appels domaine qui la précèdent ? | **La seule affectation.** `massifs_synthese_du_jour()`, `massifs_fraicheur()` et le calcul de `$massifs_peremption` restent **dehors** | (1) Ces appels lèvent `\InvalidArgumentException`, pas `\UnhandledMatchError` : les englober élargirait le sens du `catch` **sans rien capter de plus**. (2) Englobés, `$massifs_synthese` et `$massifs_fraicheur` seraient potentiellement **indéfinies** en aval : la panne se **déplacerait** au lieu de disparaître. (3) `$massifs_peremption` calculé avant le `try` et **non touché** par le `catch` fait que le repli se comporte **exactement** comme le bras `indisponible` réel, qui laisse déjà « Donnée périmée. » s'ajouter (A-4) — une différence de comportement ici serait une **seconde divergence**, du type même que #27 referme |
| **A-38** | **Issue #27.** Le commentaire conservé du bras `indisponible` disait « Les trois fragments **ci-dessous** », or le hissage les a déplacés une centaine de lignes plus haut | **Déixis corrigée** : « Les trois fragments du tableau d'absence hissé plus haut, … ». Le reste du bloc (pourquoi « INDISPONIBLE » de §8.2 n'est pas rendu) est **conservé mot pour mot**, aucun octet de code touché | La phrase **devenait fausse**, soit exactement la classe de défaut que #27 referme : une divergence entre ce qui est écrit et ce que fait le code. Règle désormais **opposable en revue** : un commentaire qui situe du code par « ci-dessus » / « ci-dessous » doit être revérifié à chaque déplacement de ce code |

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
