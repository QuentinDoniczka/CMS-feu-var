# Contrat d'interface — Issue #27 — Réconcilier la garde du `match()` de `front-page.php` avec l'arbitrage écran-blanc de la chaîne #6

**Gelé le 13 août 2026** par `lead-issue-cms` (chaîne #27). Liant à partir de ce point.

Ce contrat n'est **pas** une frontière front↔back : l'issue #27 ne touche **que le thème**, et n'écrit
**aucune ligne d'extension**. `leaddev-back-cms` n'a donc pas été lancé, et **aucune demande nouvelle
n'est portée au back**. Les deux surfaces serveur consommées sont **déjà gelées et déjà consommées** par
le contrat #5 : `massifs_synthese_du_jour()['etat_global']` et
`massifs_attribution_statuts()['carte_officielle_url']`.

Sa vraie frontière est **documentaire** : elle réconcilie deux contrats gelés qui donnaient la réponse
inverse au même risque sur le même chemin de rendu (contrat #5, section « Le `match()` — sans bras
`default` » ⇄ contrat #6, arbitrage E).

## Empreinte d'écriture — exhaustive

```
wp-content/themes/massifs/front-page.php
docs/contracts/issue-5.md      (révision 6)
docs/contracts/issue-6.md      (révision 1)
docs/contracts/issue-27.md     (ce fichier)
```

Rien d'autre. En particulier **hors empreinte, ni créés, ni modifiés, ni déplacés** :
`templates/parts/**` (chaîne #6), `templates/header.php`, `templates/footer.php`, `functions.php`,
`style.css`, tout `assets/**`, toute l'extension `massifs-core`, `data/**`, `docs/contracts/issue-2.md`
et `issue-3.md`, toute configuration Docker.

---

## Le défaut corrigé — fait mesuré, pas fait supposé

`front-page.php` l. 45 portait `$massifs_ardoise = match ( $massifs_synthese['etat_global'] ) { … }` —
quatre bras, **aucun `default`, aucun `try`**. Ce `match()` s'exécute **avant** `get_template_part(
'templates/header' )` (l. 168), donc **avant le premier octet de sortie**, et **avant toute inclusion de
partie**.

Les quatre parties de `templates/parts/**` enveloppent leurs **sept** `match()` dans un
`try/catch ( \UnhandledMatchError )` qui retient `indisponible` (contrat #6, arbitrage E), précisément
pour qu'un état inconnu ne fasse pas tomber la page. Cette mitigation était **inatteignable depuis
l'accueil** : le `throw` de la l. 45 précédait les inclusions (l. 291, 320, 326).

### Correction factuelle — ce n'est pas un « écran blanc »

`WORDPRESS_DEBUG: 0` (`docker-compose.yml` l. 45) et aucun `wp-content/php-error.php` dans le dépôt : un
`\UnhandledMatchError` non rattrapé est un `E_ERROR`, donc capté par `WP_Fatal_Error_Handler`. Le
visiteur recevait **HTTP 500 + la page « Erreur critique sur ce site. » du cœur de WordPress**. Le
`match()` précédant tout octet de sortie, les en-têtes n'étaient pas encore envoyés : le 500 s'appliquait
proprement.

Le défaut est **aussi grave que l'issue le dit, pour une autre raison que celle qu'elle écrit** : la page
servie est celle du cœur — **zéro statut, zéro lien officiel**, aucun de nos jetons, hors de toute
vérification d'accessibilité, et son contenu ne nous appartient pas. C'est le brief §4.2 (« sans donnée
valide → information non disponible, consultez la carte officielle **(lien)** ») et la contrainte non
négociable #3 (« les statuts sont dans le HTML rendu par PHP ») qui sont annulés.

**Toute formulation « écran blanc » dans les contrats #5 et #6 est corrigée par les révisions de cette
issue.** Un contrat qui invoque un fait faux se fait contredire en revue.

### Les deux déclencheurs — pas un seul

1. **Cinquième `etat_global`** ajouté à `includes/domain/statuts/api.php`.
2. **Clé `etat_global` renommée ou retirée** du tableau de retour de la synthèse. L'accès est direct, sans
   `isset()` ni `??` — c'est un **interdit du contrat #5**, délibéré : PHP émet alors un
   `Undefined array key`, l'expression vaut `null`, et `match(null)` lève le **même**
   `\UnhandledMatchError`. Ce déclencheur ne suppose aucun cinquième état et il est **le plus probable
   des deux**.

### Ce qui ne peut PAS le déclencher — vérifié

`etat_global` naît d'une chaîne `if/elseif` **locale et fermée** (`api.php` l. 223-231) que **aucun
`apply_filters` ne traverse** (les filtres du domaine sont tous dans `ingest/prefecture/**`). Un
cinquième état est donc **impossible à produire par la donnée** : ni ingestion, ni ligne de base, ni
saisie au portail, ni option, ni filtre. Il exige une **modification de `api.php`, dans ce même dépôt**.

L'arbitrage originel du contrat #5 n'était donc **pas naïf** : il était proportionné à un risque de
**code**, non de **données**. Ce qui le renverse n'est pas la probabilité, ce sont **la sévérité et le
lieu** — la sanction tombait sur le visiteur anonyme de la page d'accueil, pas sur le développeur — plus
le second déclencheur ci-dessus, qui élargit la surface bien au-delà d'un cran de légende.

---

## Fonctions de lecture exposées par l'extension

Aucune nouvelle. **Aucune demande au back.** Consommées, toutes déjà gelées par le contrat #3 et déjà
consommées par le contrat #5 :

```php
massifs_synthese_du_jour( array $codes, ?string $jour = null ): array   // clé lue : etat_global
massifs_attribution_statuts(): array                                    // clé lue : carte_officielle_url
```

`massifs_attribution_statuts()` — vérifié dans le code (`api.php` l. 322-364) : **ne prend aucun
argument**, **ne lève rien**, et retourne `carte_officielle_url` sur **ses deux** chemins de retour
(l. 332 repli imposé, l. 353 valeur relayée avec repli). Le hissage de son appel avant le premier octet
de sortie n'introduit donc **aucun nouveau chemin de fatal**.

## Routes REST

**Aucune.** L'issue #27 n'expose, ne consomme et ne déclare aucune route REST.

---

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `disponible` · `indisponible` · `hors_saison` · `non_encore_publie` | `synthese['etat_global']` | **Inchangés.** Contrat #5, tableau des états spéciaux |
| `publication_partielle` | `synthese['partiel'] === true` | **Inchangé.** Variation interne au bras `disponible` (contrat #5, révision 5) |
| `donnee_perimee` | `fraicheur['perimee'] === true` | **Inchangé.** Phrase **ajoutée**. Le `catch` **ne touche pas** `$massifs_peremption` : le chemin de repli se comporte exactement comme le bras `indisponible` réel |
| **`etat_global` inconnu** (cinquième état, **ou** clé renommée/retirée ⇒ l'expression vaut `null`) | **Par aucun chemin de donnée.** Exige une modification de `api.php`, dans ce même dépôt | `try { … } catch ( \UnhandledMatchError $massifs_erreur )` autour du `match()`. Repli sur l'**ardoise d'absence** — `indisponible`, **AVEC** le lien `carte_officielle_url`. **Jamais de chiffre** : `chiffre`, `chiffre_total`, `publication_partielle` vides, `fraicheur => false`. Clé `journal` **distincte** du bras `indisponible` normal, émise par `massifs_journaliser()`. **Aucun `_doing_it_wrong()`** |
| API absente (`$massifs_api === false`) | garde `function_exists` | **Inchangé.** Branche indisponible **sans lien**, phrase en **un seul fragment**. `$massifs_ardoise_absente` n'y est **jamais** réutilisée |
| `couche_effis_indisponible` | — | **hors périmètre** |

### Le `match()` — quatre bras, aucun `default`, sous enveloppe

```php
$massifs_ardoise_absente = array( /* … voir « Forme du code » … */ );

try {
    $massifs_ardoise = match ( $massifs_synthese['etat_global'] ) {
        'disponible'        => array( /* … inchangé … */ ),
        'indisponible'      => $massifs_ardoise_absente,
        'hors_saison'       => array( /* … inchangé … */ ),
        'non_encore_publie' => array( /* … inchangé … */ ),
    };
} catch ( \UnhandledMatchError $massifs_erreur ) {
    $massifs_ardoise            = $massifs_ardoise_absente;
    $massifs_ardoise['journal'] = 'massifs: etat_global hors des quatre états du gabarit — ardoise rendue en état indisponible (' . $massifs_erreur->getMessage() . ').';
}
```

**L'interdit 11 du contrat #3 est honoré à la lettre** : le `match()` conserve ses quatre bras et son
absence de `default`, et il n'existe **aucune** branche « sinon » à l'intérieur du `match`. **Et dans son
intention** : cet interdit vise la **non-silence** de l'ajout d'un état — « il rendrait silencieux
l'ajout d'un cinquième état » —, **jamais la panne**. Un `try/catch` qui journalise et dégrade
visiblement l'honore intégralement.

**Le contrat #3 n'est pas révisé** — et n'a pas besoin de l'être. C'est ce qui rend cette réconciliation
possible sans écrire hors empreinte : #27 **généralise #6**, il ne renégocie pas #3.

### La garantie structurelle du chiffre est intégralement préservée

« Le chiffre n'est écrit que dans le bras `disponible` » reste **vrai après #27**.
`par_niveau['autorise']`, `partiel`, `disponibles` et `sans_donnee` restent lues **uniquement** à
l'intérieur du bras `disponible`, et PHP continue de n'évaluer que le bras retenu. Le chemin de repli est
**provablement dépourvu de chiffre** (assertion 4 de la recette R-27).

---

## Chaînes fournies par le serveur

`massifs_attribution_statuts()['carte_officielle_url']` — rendue telle quelle, **jamais composée**.

**Aucune chaîne éditoriale nouvelle n'est rédigée par cette issue.** La phrase du repli existe
**verbatim** en `design-system/MASTER.md` §11.3 et était **déjà écrite** dans le fichier (bras
`indisponible`, ex-l. 113-116) : « Information du jour non disponible. Consultez la carte officielle de
la préfecture. », découpée en trois fragments pour porter le lien. **Rien à faire valider à
`lead-design-cms`.**

---

## Forme du code — gelée

### Le tableau hissé

`$massifs_ardoise_absente`, déclaré **à l'intérieur** de la garde `if ( $massifs_api ) {`, après
`$massifs_peremption`. Contenu **identique à l'octet près** à l'ex-bras `indisponible`, à ceci près que
`journal` vaut `''` et est surchargée par le `catch`.

**Emplacement non négociable — jamais au-dessus de la garde `$massifs_api`** : il appelle
`massifs_attribution_statuts()`, qui **n'existe pas** quand `$massifs_api === false`. C'est aussi ce qui
documente structurellement pourquoi la branche `else` conserve son propre littéral **sans lien**.

**Nommage** : `$massifs_ardoise_absente`, et **non** `$massifs_ardoise_indisponible` — le nom doit dire
que **le repli est une absence, jamais une donnée** (formulation de l'arbitrage E du contrat #6).

### La portée du `try` — minimale, une seule instruction

`try {` s'ouvre **immédiatement avant** `$massifs_ardoise = match (` et se ferme **immédiatement après**
le `};` du `match`. Les appels `massifs_synthese_du_jour()`, `massifs_fraicheur()` et le calcul de
`$massifs_peremption` restent **dehors**. Trois raisons :

1. Ces appels lèvent `\InvalidArgumentException`, pas `\UnhandledMatchError` : les englober élargirait le
   sens du `catch` sans rien capter de plus.
2. Englobés, `$massifs_synthese` et `$massifs_fraicheur` seraient potentiellement **indéfinies** en aval
   (ligne de fraîcheur) : la panne se **déplacerait** au lieu de disparaître.
3. `$massifs_peremption` calculé **avant** le `try` et **non touché** par le `catch` fait que le chemin de
   repli se comporte **exactement** comme le bras `indisponible` réel, qui laisse déjà « Donnée
   périmée. » s'ajouter (arbitrage A-4). Une différence de comportement ici serait une **seconde
   divergence**, du type même que #27 referme.

### Le `catch` — deux instructions, rien d'autre

`catch ( \UnhandledMatchError $massifs_erreur )`, **avec** variable d'exception. Il affecte
`$massifs_ardoise` puis surcharge sa seule clé `journal`. Il **ne touche pas** `$massifs_peremption`,
`$massifs_synthese`, `$massifs_fraicheur`, et ne fait **aucune sortie**, aucun `return`, aucun `exit` :
la page se rend entièrement.

### Le message de journal — gelé

```
massifs: etat_global hors des quatre états du gabarit — ardoise rendue en état indisponible (<getMessage()>).
```

`etat_global` est écrit **sans accent** : c'est un **identifiant de code**, et le message doit rester
greppable contre le code. Les mots de prose portent leurs accents (précédent l. 133 : « état
hors_saison »). Forme cohérente avec les deux messages existants : préfixe `massifs: `, sujet factuel,
tiret cadratin, conséquence, point final, aucun nom de fichier.

Émis par la clé `journal`, consommée par le `massifs_journaliser()` **déjà présent** en aval : **aucune
plomberie nouvelle**.

Le message **n'est pas échappé** — le journal n'est pas du HTML ; l'échapper serait une erreur de couche.
`Generic.Files.LineLength` est en **warning** sous WPCS et le fichier a trois précédents : **ne pas
couper la chaîne** sur plusieurs lignes, un message de journal doit rester greppable d'un seul morceau.

#### Ce que `getMessage()` produit réellement — mesuré, PHP 8.2.33 et 8.3.33

Vérifié en isolation (hors dépôt) sur les deux versions, qui donnent des libellés **identiques** :

| Valeur d'`etat_global` | `getMessage()` |
|---|---|
| `'etat_de_recette_27'` | `Unhandled match case 'etat_de_recette...'` |
| `null` (clé absente) | `Unhandled match case NULL` |

Trois faits qui en découlent, et qui sont **liants pour la recette** :

1. **PHP tronque la valeur** à 15 caractères suivis de points de suspension. La ligne de journal ne porte
   donc **pas nécessairement le nom complet** du cinquième état. Ce n'est pas un défaut : cela **renforce**
   A-34 du côté de la surface d'injection dans le fichier de log, et confirme qu'il ne faut **jamais
   asserter la fin de la ligne** de journal (R-27, cas 3).
2. `null` s'imprime **`NULL`** en capitales, jamais `null` : la **discrimination des deux causes racines
   est vérifiée**, pas supposée.
3. Sur le déclencheur 2, l'avertissement `Undefined array key "etat_global"` est émis **puis** le `match`
   lève, **puis** le `catch` reprend et la page continue — les deux effets coexistent, ce que l'assertion 8
   de R-27 attend.

### Les commentaires — dire *pourquoi*, jamais *quoi*

Un relecteur à six mois doit pouvoir reconstituer, **sans ouvrir `docs/contracts/`** : le fait mesuré
(HTTP 500 + page du cœur, pas écran blanc), les **deux** déclencheurs, et la raison institutionnelle
(deux contrats gelés qui se contredisaient). Aucun commentaire ne paraphrase le code.

Le bloc de commentaire existant au-dessus du `match()` **doit changer** : il affirme aujourd'hui que le
`throw` est le comportement voulu (« sur une donnée de sécurité, l'échec bruyant est le bon
comportement »), ce qui devient faux et **recréerait dans le code la divergence documentation/code que
l'issue referme**.

Le commentaire du bras `disponible` (« NE PAS factoriser ces lectures ») reçoit une **phrase de
démarcation** : sans elle, un futur relecteur voyant un tableau hissé douze lignes plus haut conclura
soit que le hissage est incohérent et le retirera, soit qu'il est autorisé et l'**étendra aux lectures
chiffrées**. C'est le seul ajout autorisé hors du périmètre `try`/`catch`/hissage, et il est de
commentaire pur.

**A-38 — la déixis du commentaire conservé du bras `indisponible`.** Ce bloc écrivait « Les trois
fragments **ci-dessous**, concaténés, sont la chaîne §11.3 mot pour mot ». Le hissage déplaçant les
fragments une centaine de lignes plus haut, la phrase **devenait fausse dans sa déixis** — soit
exactement la classe de défaut que #27 referme : une divergence entre ce qui est écrit et ce que fait le
code. **Corrigée** en « Les trois fragments du tableau d'absence hissé plus haut, concaténés, … ».
Correction de quelques mots ; le reste du bloc (pourquoi le mot « INDISPONIBLE » de §8.2 n'est pas rendu)
est **conservé mot pour mot**, et aucun octet de code n'est touché. Ce type de référence est désormais
**opposable en revue** : un commentaire qui situe du code par « ci-dessus » / « ci-dessous » doit être
revérifié à chaque déplacement de ce code.

---

## Interdits

Opposables en revue. Les huit premiers sont **propres à #27** ; ceux des contrats #3, #5 et #6 restent
intégralement en vigueur.

1. Ne **pas** ajouter de bras `default` au `match()` de l'ardoise. Quatre bras, aucun `default`
   (interdit 11 du contrat #3, hors empreinte, non renégocié).
2. Ne **pas** remplacer l'enveloppe par une liste blanche d'états en amont (`in_array`,
   `array_key_exists`) : ce serait la forme **explicitement interdite** par le contrat #3
   (« un `if/else` avec branche « sinon » est un défaut ») simplement déplacée d'un cran, et elle
   **rendrait silencieux un cinquième bras légitimement ajouté** — l'ardoise annoncerait « information
   non disponible » pour une donnée réellement publiée. L'enveloppe n'a pas cette faille : un bras ajouté
   est retenu par le `match()` et le `catch` n'est pas atteint.
3. Ne **pas** retirer le `try/catch` au motif qu'aucun test ne peut produire un cinquième état : c'est
   **précisément parce que la donnée ne peut pas le produire** que seule une modification de `api.php` le
   produira, sans qu'aucun test ne prévienne.
4. Ne **pas** hisser les quatre lectures chiffrées du bras `disponible` — `par_niveau['autorise']`,
   `partiel`, `disponibles`, `sans_donnee` (interdit existant du contrat #5, portée précisée par A-32).
5. Ne **pas** réutiliser `$massifs_ardoise_absente` dans la branche `else` de la garde d'API : son
   contenu y est **différent** (phrase en un seul fragment, **sans** lien) et
   `massifs_attribution_statuts()` n'existe pas dans ce chemin.
6. Ne **pas** sortir `$massifs_ardoise_absente` de la garde `if ( $massifs_api )`.
7. Le `catch` ne **touche pas** `$massifs_peremption`, `$massifs_synthese`, `$massifs_fraicheur`, et ne
   fait **aucune** sortie, `return` ni `exit`.
8. Ne **pas** englober les trois appels domaine (`massifs_synthese_du_jour`, `massifs_fraicheur`,
   `$massifs_peremption`) dans le `try`.
9. Ne **pas** employer `_doing_it_wrong()` dans `front-page.php` — voir A-33.
10. Le thème n'appelle **jamais** une source externe, ne calcule **jamais** une règle métier, ne fabrique
    **jamais** un libellé d'état ni une URL officielle, n'invente **jamais** une chaîne visible par le
    visiteur.
11. **Aucune requête navigateur vers un domaine tiers**, **aucun cookie**, **aucun JavaScript**, **aucun
    octet d'asset** ajouté par cette issue.
12. **Aucun jeton, aucune classe CSS, aucune custom property** créés. `assets/**` est hors empreinte.

---

## Arbitrages

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-31** | Deux contrats gelés répondent l'inverse au même risque sur le même chemin de rendu : #5 pose le `throw` comme délibéré, #6 (arbitrage E) l'enveloppe pour éviter la panne. Celui qui dit « panne » gagnait, **parce qu'il s'exécute le premier** | **Enveloppe `try/catch ( \UnhandledMatchError )` adoptée au niveau page**, calquée sur l'arbitrage E. Le `match()` garde ses quatre bras et son absence de `default` | Le `match()` précède le premier octet de sortie **et** toutes les inclusions de parties : il précédait donc la mitigation censée le couvrir. Deux déclencheurs réels, dont l'un (clé renommée ⇒ `null`) ne suppose aucun cinquième état. Le brief §4.2 exige l'état « information non disponible » avec lien **sur la carte ET dans la liste**, ce qu'une page 500 du cœur ne peut pas rendre. **#27 confirme et généralise #6 ; il ne renégocie pas #3** — c'est ce qui permet de tout tenir dans l'empreinte |
| **A-32** | Le bras `indisponible` et le repli du `catch` doivent rendre **la même chose** à `journal` près. Hisser en variable, ou dupliquer ? | **Hissage** de `$massifs_ardoise_absente`, avec **précision opposable de la portée de l'interdit d'anti-factorisation** du contrat #5 : celui-ci porte sur **quatre clés nommées** lues dans le seul bras `disponible`, et son motif écrit est de ne laisser aucune **phrase chiffrée plausible et fausse** en mémoire dans un état sans chiffre. **Critère de revue : une variable hissée au-dessus du `match()` est un défaut si et seulement si elle peut contenir un nombre, ou une phrase contenant un nombre.** `$massifs_ardoise_absente` n'en contient aucun — il est **l'inverse exact** du danger visé, et le hisser **renforce** la garantie structurelle en rendant l'unique chemin de repli provablement dépourvu de chiffre | La **duplication est rejetée** : deux copies divergeraient à la première retouche de la phrase §11.3 ou de la source de l'URL (arbitrage F′ du contrat #6 : « deux sources d'une même valeur finissent par diverger ; une seule ne le peut pas »). Or **la divergence entre deux chemins de rendu d'un même état est exactement le défaut que #27 existe pour refermer** : dupliquer serait refermer une divergence en en ouvrant une autre, de même nature, à trois lignes d'écart. Coût réel : `massifs_attribution_statuts()` appelée dans les quatre états au lieu d'un — sans argument, sans exception, `carte_officielle_url` garanti sur ses deux chemins de retour, et **déjà appelée sans condition trois fois sur cette page** (`footer.php` l. 52, `bandeau-non-officialite.php` l. 41, `liste-statuts.php` l. 129) |
| **A-33** | Canal du bruit : `_doing_it_wrong()` comme les sept `catch` des parties, ou `massifs_journaliser()` ? | **`massifs_journaliser()`, jamais `_doing_it_wrong()` dans `front-page.php`** | `_doing_it_wrong()` déclenche `trigger_error( E_USER_WARNING )`, et `E_USER_WARNING` **reste dans `error_reporting()` même avec `WP_DEBUG` à faux** : si `display_errors` était actif sur l'hébergement cible, l'avertissement s'imprimerait **dans le HTML du visiteur**. Or le thème s'interdit **tout texte de repli visible par le visiteur** (interdit existant du contrat #5) — y ajouter un avertissement PHP serait la même faute en pire. `massifs_journaliser()` est le **point unique** de la garde `WP_DEBUG` du thème (`functions.php` l. 55-74), aucun de ses messages n'atteint le visiteur, et la clé `journal` est **déjà consommée** en aval : zéro plomberie neuve. **Divergence de canal assumée** avec `templates/parts/**`, hors empreinte — voir la dépendance O-27-2 |
| **A-34** | `catch` avec ou sans variable d'exception ? Nommer ou non la valeur inattendue ? | **`catch ( \UnhandledMatchError $massifs_erreur )`**, message = notre phrase + `$massifs_erreur->getMessage()` entre parenthèses | A-33 retirant `_doing_it_wrong()` — et avec lui le backtrace —, la ligne de journal est la **seule trace existante** et doit porter le fait discriminant : `case "…"` = cinquième état, `case null` = la clé a disparu du contrat. **Deux causes racines différentes, dans deux fichiers différents.** `getMessage()` retourne **toujours** une `string` (indispensable : `massifs_journaliser( string )` sous `strict_types=1` lèverait un `TypeError` sur autre chose), ne lève rien, et c'est **PHP** qui met la valeur en forme — le thème n'en compose rien et ne peut pas se tromper de type. **Écartées** : relire `$massifs_synthese['etat_global']` dans le `catch` (dans le scénario « clé absente », cela émettrait un **second** `Undefined array key`, exactement la classe d'exposition que A-33 refuse) ; assainir la valeur à la main (pipeline de mise en forme dans un `catch` de trois lignes, que chaque revue re-litigerait). Surface d'injection dans le fichier de log : un `etat_global` contenant un retour ligne — **nulle en pratique**, la valeur naissant de quatre littéraux d'une chaîne `if/elseif` locale ; si elle cessait de l'être, le correctif appartiendrait à `api.php`, pas au gabarit |
| **A-35** | Repli **avec** ou **sans** le lien officiel ? | **AVEC le lien**, `massifs_attribution_statuts()['carte_officielle_url']` | Trois raisons, aucune inventée : (a) le brief §4.2 **exige** le lien — « information non disponible, consultez la carte officielle **(lien)** » ; (b) le repli **sans** lien n'existe que parce que l'URL est **inobtenable** (garde `$massifs_api === false` de #5, arbitrage F de #6 « elle ne vient de nulle part ») — or ici `$massifs_api === true` et l'URL est disponible : omettre le lien serait dégrader **sans nécessité** ; (c) **cohérence carte ET liste** : `liste-statuts.php` (l. 157-161) et `etats-vides.php` (l. 86-90) retiennent déjà `indisponible` **avec** son lien via l'arbitrage E — après #27, les trois rendus de la page **convergent sur le même état et le même lien** |
| **A-36** | Le caractère « bruyant » exigé par l'interdit 11 du contrat #3 subsiste-t-il, sachant que `massifs_journaliser()` **n'écrit rien hors `WP_DEBUG`** ? | **Oui, et il est porté par le rendu, non par le journal.** Conséquence enregistrée sans détour : **en production, le repli ne laisse aucune trace dans les logs** | C'est exactement l'argument de l'arbitrage E (« journal + état dégradé visible »). L'ardoise **cesse d'afficher un chiffre** et annonce « Information du jour non disponible » **sur la page la plus lue du site** : un développeur qui vient d'ajouter un état le voit à la première page chargée. Ce qui est abandonné est l'échec **fatal** ; ce qui est conservé est l'échec **visible**, qui est ce que le contrat #3 demandait réellement. L'unification du canal de bruit sur tout le thème est une question d'orchestrateur — voir O-27-2 |
| **A-37** | La case « reproduire l'écran blanc » de l'issue | **Non exécutée dans cette chaîne. Recette R-27 gelée ci-dessous et léguée à `test-integration-cms`** | Aucun chemin de donnée ne peut produire un cinquième `etat_global` (aucun `apply_filters`, chaîne `if/elseif` fermée dans `api.php`, **hors empreinte**). Le seul geste faisable est une **injection locale temporaire dans `front-page.php`** — or (a) l'observation exige une pile WordPress vivante, qui est de **niveau lot** (`docker-cms`, `test-integration-cms`) et hors de mon empreinte, et (b) une chaîne sœur travaille dans le **même arbre de travail, sans isolation** : une injection présente pendant qu'elle commite serait balayée dans son commit, et **aucune branche ne rattraperait cela**. Rapporté comme **non fait**, avec sa raison, plutôt que déclaré fait |

---

## Recette R-27 — reproduction et non-régression, léguée à `test-integration-cms`

**Préalable de méthode.** Ni la base, ni une option, ni l'ingestion, ni un filtre ne peuvent produire un
cinquième `etat_global`. Le seul geste faisable est une **injection locale temporaire dans
`front-page.php`**, essayée puis **retirée avant tout commit**. `api.php` est hors empreinte et **ne doit
pas être touché**, même temporairement.

**Avertissement d'arbre partagé.** Le projet est **mono-branche, arbre de travail unique, aucune
isolation**. La fenêtre d'injection doit être la plus courte possible, **aucun commit ne doit être fait
pendant qu'elle est présente**, et les commits passent par `commit-scoped` avec liste de fichiers
explicite.

**Configuration** : celle de production (`WORDPRESS_DEBUG: 0`, `display_errors` inactif, aucun
`wp-content/php-error.php`). Page sous test : `/`.

### Cas 0 — non-régression, sans injection

`GET /` → **200**, ardoise identique à la recette de #26 : chiffre, dénominateur, phrase d'accord, ligne
de fraîcheur. **Le hissage ne doit rien changer** au jour nominal ni au jour de publication partielle.

### Cas 1 — cinquième état

Insérer **une** ligne juste après `$massifs_peremption = true === $massifs_fraicheur['perimee'];` :

```php
$massifs_synthese['etat_global'] = 'etat_de_recette_27';
```

Valeur délibérément non plausible comme nom d'état, pour qu'un oubli soit trivial à détecter par `grep`.

### Cas 2 — clé renommée ou retirée (le déclencheur le plus probable)

Remplacer la ligne d'injection par :

```php
unset( $massifs_synthese['etat_global'] );
```

### Attendus mesurables — identiques aux cas 1 et 2

| # | Assertion | Avant correctif |
|---|---|---|
| 1 | Code HTTP = **200** | **500** |
| 2 | Exactement **un** `<h1` dans le document, `id="titre-du-jour"` | aucun |
| 3 | Texte du `h1` = `Information du jour non disponible. Consultez la carte officielle de la préfecture.` (§11.3 verbatim, un seul espace avant le lien, point final), contenant `<a href="…">la carte officielle de la préfecture</a>` dont le `href` est **non vide** et d'hôte **identique** à celui de `.bandeau-non-officialite__lien` (même source `massifs_attribution_statuts()`). **Ne pas coder l'URL en dur** comme source de vérité de l'assertion | absent |
| 4 | `<section id="ardoise">` ne contient **aucun caractère `[0-9]`** — donc aucun `.ardoise__chiffre`, aucun `<time>` (`fraicheur => false`), aucune phrase de publication partielle | sans objet |
| 5 | `<a href="#liste">` présent dans l'en-tête **et** `id="liste"` présent **exactement une fois** : l'ancre **résout** | sans objet |
| 6 | Le corps ne contient **aucun** de : `Warning:`, `Notice:`, `Deprecated:`, `Fatal error`, `<b>Warning</b>`, `UnhandledMatchError`, `_doing_it_wrong`, `Erreur critique sur ce site` | `Erreur critique sur ce site` |
| 7 | Document complet : exactement un `<main`, `</main>` et `</html>` présents | absents |
| 8 | **Cas 2 seulement** : `Undefined array key "etat_global"` présent dans le log PHP et **absent du corps** (couvert par 6). Cet avertissement est **voulu** — contrat #5, « une clé absente doit produire un avertissement PHP visible » | idem, puis 500 |

### Cas 3 — facultatif, exige `WP_DEBUG=1`

Donc une configuration Docker **hors empreinte** : ce cas **n'est pas bloquant**, et les huit assertions
obligatoires tiennent avec la configuration de production. Le log doit contenir **une** ligne commençant
par `massifs: etat_global hors des quatre états du gabarit — ardoise rendue en état indisponible (`.
**Ne pas asserter la fin de la ligne** : c'est le libellé de PHP (`Unhandled match case …`), susceptible
de varier selon la version.

### Artefact d'injection — à ne pas déclarer comme défaut

L'injection ne modifie qu'une **variable locale de `front-page.php`** : `legende.php` et
`liste-statuts.php` s'auto-alimentent et rendront le **vrai** état du jour. L'ardoise dira
« indisponible » pendant que la liste affichera son tableau. Ce n'est **pas** une incohérence du
correctif : un cinquième état **réel** atteindrait aussi `liste-statuts.php`, dont le `catch` propre
(arbitrage E) le dégraderait vers `indisponible` **avec** son lien — d'où la convergence des trois
rendus, et d'où le choix de garder le lien dans l'ardoise (A-35).

### Post-condition obligatoire

Retirer la ligne d'injection ; vérifier que
`git diff -- wp-content/themes/massifs/front-page.php` ne montre **que** le correctif #27, et que
`git grep -n "etat_de_recette_27"` ne retourne **rien**.

---

## Dépendances hors empreinte — signalées, non traitées

| # | Destinataire | Objet |
|---|---|---|
| **D-27-1** | orchestrateur → nouvelle issue de dette | **`massifs_horodatage()` appelée trois fois sans garde** dans `front-page.php` (l. 227, 237, 246 avant #27 ; **l. 308, 318, 327 après**) alors qu'elle **lève `\InvalidArgumentException`** (`includes/domain/fraicheur/api.php` l. 89-104, `@throws` documenté — c'est le motif de l'arbitrage A-1). Ces appels sont **après** le début de la sortie : un instant malformé en base ne produit pas un 500 propre mais une **page tronquée** — en-tête, `<main>` ouvert, `<h1>`, puis rien : pas de `</main>`, pas de pied, pas de `</html>`, HTML invalide, et l'ancre `#liste` **jamais émise** alors que le lien d'évitement la vise. **Même ligne de DoD que #27, défaut plus probable (piloté par une donnée d'exécution, non par une modification de code) et conséquences pires.** Correctif connu : trois `try/catch ( \InvalidArgumentException )` laissant simplement la proposition **hors** de `$massifs_propositions` — la règle d'**omission seule** est déjà gelée (arbitrage A-2), rien à inventer. `liste-statuts.php` garde déjà ses appels domaine de cette façon (l. 123-124). **Hors mandat de #27**, qui borne l'empreinte à « `front-page.php` (ligne 45) » : **non traité, non silencieux.** Note au futur correcteur : le chemin de repli de #27 rend `fraicheur => false`, donc il **n'atteint aucun** des trois appels |
| **O-27-2** | orchestrateur | **Unifier le canal de bruit du thème.** `front-page.php` emploie `massifs_journaliser()` (A-33), les sept `catch` de `templates/parts/**` emploient `_doing_it_wrong()`. L'exposition est **plus grave côté parties** : leurs `trigger_error( E_USER_WARNING )` s'exécutent **après** le début de la sortie, donc un avertissement s'imprimerait **au milieu de la page** du visiteur si `display_errors` était actif. #27 ne crée pas ce risque et n'a aucune raison de l'aggraver. À ordonnancer dans une issue dédiée |
| **O-27-3** | `docker-cms` / `review-cms` | **Mesurer et verrouiller `display_errors`** sur la pile locale et sur l'hébergement cible. Le thème compte déjà sept `_doing_it_wrong()` sur le chemin public, et `E_USER_WARNING` reste rapporté avec `WP_DEBUG` à faux. Hors empreinte de #27 |
| **C-27-4** | chaîne `carte` | Sur `etat_global` inconnu comme sur `indisponible`, la carte doit retenir **`indisponible` avec le lien officiel**, sur le **même signal**. Le brief §4.2 exige cet état **sur la carte ET dans la liste** ; la carte n'existe pas encore, la dépendance est déposée pour qu'elle ne l'oublie pas |
| **O-27-5** | orchestrateur / rédaction d'issues | Le corps de l'issue #27 comporte **deux inexactitudes factuelles**, corrigées par les révisions de #5 et #6 : « écran blanc » (c'est **HTTP 500 + la page « Erreur critique sur ce site. » du cœur**) et « ses trois `match()` » côté chaîne #6 (il y en a **sept** : `etats-vides.php` l. 86 ; `legende.php` l. 112, 142, 177 ; `liste-statuts.php` l. 157, 267, 300, 318). Le **fond de l'issue est juste** : seuls les faits invoqués sont à corriger |

---

## Ce qui ne change pas

Aucune autre ligne de `front-page.php` que le hissage, le `try`/`catch`, la réindentation d'une
tabulation des bras du `match()`, et les trois blocs de commentaire nommés. Aucun `assets/**`, aucune
classe CSS, aucun jeton, aucun JS, aucune requête tierce, aucun cookie, aucune chaîne éditoriale
nouvelle, aucun octet d'asset. Aucun fichier de `templates/parts/**`, `header.php`, `footer.php`,
`functions.php`, `style.css`, `massifs-core`, `data/**`, Docker.

**Sans JavaScript** : le chemin de repli est **intégralement rendu par PHP** et identique avec et sans
JS. Le HTML du repli est **plus court** que le nominal (pas de chiffre, pas de ligne de fraîcheur) :
budget de performance inchangé, aucun octet ajouté.

**Accessibilité** : `h1` unique conservé, plan de titres inchangé, ancre `#liste` conservée, information
portée par du **texte** et non par la couleur, aucun texte de repli inventé, aucun avertissement PHP dans
le corps. Le gain est le sujet même de l'issue : une page HTTP 500 du cœur de WordPress est **hors de
toute vérification d'accessibilité**.
