# Contrat d'interface — Issue #29 — Garder les appels à `massifs_horodatage()` dans `front-page.php`

**Gelé le 17 août 2026** par `lead-issue-cms` (chaîne #29). Liant à partir de ce point.

Cette issue **ne touche aucun fichier de l'extension**. `leaddev-back-cms` n'a donc pas été lancé, et
**aucune demande nouvelle n'est portée au back** : l'issue consomme une surface serveur déjà gelée par le
contrat #3. `dev-integration-cms` n'est pas lancé non plus — un seul côté est touché.

## Empreinte d'écriture — exhaustive

```
wp-content/themes/massifs/front-page.php
docs/contracts/issue-5.md          (révision 7 — correction de l'arbitrage A-2)
docs/contracts/issue-29.md         (ce fichier)
```

Rien d'autre. `tests/**`, `templates/parts/**`, `assets/css/**` et toute l'extension sont **hors
empreinte** et ne sont ni créés, ni modifiés, ni déplacés.

> **Note d'empreinte.** L'énoncé de l'issue nommait `front-page.php` et `docs/contracts/issue-5.md`. Ce
> fichier-ci est ajouté sur le **précédent de l'issue #27**, dont le contrat de chaîne
> (`docs/contracts/issue-27.md`) coexiste avec une révision de `issue-5.md`. Le risque de collision est
> nul : aucun autre chaîne ne peut posséder un fichier nommé d'après le numéro de la mienne.

---

## Le défaut réel — le diagnostic de l'issue est faux, son remède est bon

**Ce que l'issue affirme** : `massifs_horodatage()` lève `\InvalidArgumentException` ; un horodatage
malformé **en base** tronque donc la page.

**Ce qui est vérifié dans le code** :

1. L'`\InvalidArgumentException` existe bien (`domain/fraicheur/Horloge.php` l. 249 et 266) — mais elle
   est **inatteignable par tout chemin de donnée**. Les trois instants sont assainis en amont :
   `evalue_le` est produit par la machine (`Fraicheur.php` l. 85) et déclaré `readonly string`
   (`ResultatFraicheur.php` l. 46) ; `publie_prefecture_le` et `dernier_releve_le` passent par
   `RegistreReleves::entree()` (l. 172-182), qui re-parse chaque champ et **supprime la clé** quand le
   parsing échoue ; `dernier_releve_le` porte une seconde ceinture (`Fraicheur.php` l. 64-69). Aucun
   `apply_filters` ne traverse `includes/domain/fraicheur/`.
2. **Le déclencheur réellement atteignable est un `\TypeError`, que l'issue ne mentionne pas.**
   `front-page.php` déclare `strict_types=1` et `massifs_horodatage( string $instant_iso_utc )` refuse
   `null`. La lecture de `evalue_le` n'a **aucune garde de type** : une clé renommée ou retirée fait
   valoir l'expression `null` et lève un `\TypeError` **en cours d'émission**. `\TypeError extends
   \Error`, `\InvalidArgumentException extends \LogicException` : **hiérarchies disjointes** — la garde
   demandée par l'issue ne l'attraperait pas.
3. `front-page.php` est le **seul fichier du thème** dont les appels à `massifs_horodatage()` ne portent
   aucune protection. Les **neuf** autres appels du thème en portent une (compte **vérifié** :
   `parts/carte.php` l. 156/246/385, `parts/liste-statuts.php` l. 181/198/335, `parts/panneau-feu.php`
   l. 205/270, `parts/etats-vides.php` l. 140).

**Conséquence pour la revue** : le remède de l'issue (garder les trois appels) est retenu, mais il ne
suffit pas seul — c'est la garde de **type** qui ferme le seul trou atteignable.

> **MESURÉ le 17 août 2026 par la chaîne #29, dans le conteneur `massifs_wordpress` — l'énoncé de l'issue
> est partiellement faux.** Protocole : injection temporaire dans `front-page.php`, garde `is_string()`
> neutralisée, `unset( $massifs_fraicheur['evalue_le'] )`, puis restauration **vérifiée par `md5sum`
> identique au préalable** et zéro résidu d'injection.
>
> | Scénario | Résultat mesuré |
> |---|---|
> | **Garde neutralisée** + clé `evalue_le` retirée | **HTTP 500**, 5 438 octets. `</main>` **absent**, ancre `#liste` **absente**. Le `h1` **est** émis, puis le cœur appose sa page « Il y a eu une erreur critique sur ce site. » ; `</html>` **est** donc présent, mais c'est celui du cœur |
> | **Garde en place** + clé retirée (le défaut réel) | **HTTP 200**, 20 547 octets. `h1` unique, `#liste` présente, `</main>` et `</html>` présents, ligne de fraîcheur **omise**, zéro `Fatal error` / `TypeError` / « rreur critique » |
> | **Garde en place** + instant malformé (`'pas-une-date'`) | **HTTP 200**, 20 547 octets, mêmes assertions — le `catch` a bien retenu l'`\InvalidArgumentException` |
> | **Témoin**, instant valide | **HTTP 200**, 20 761 octets, ligne de fraîcheur **rendue** : « Statuts du \<time\>lundi 17 août 2026\</time\> — relevés sur ce site le … » |
>
> **Le témoin est ce qui rend la mesure falsifiable** : sans lui, l'absence de ligne de fraîcheur serait
> indistinguable de l'état `indisponible` du jeu de données, dans lequel le bloc n'est jamais atteint.
>
> **Corrections à porter à l'énoncé de l'issue** : (a) ce n'est **pas** « une page tronquée » au sens
> strict, c'est **HTTP 500 + page « Erreur critique » du cœur**, exactement comme le cas pré-émission de
> #27 — la différence étant qu'ici le `h1` a déjà été émis avant l'apposition ; (b) l'affirmation « pas de
> `</html>` » est **fausse** : `</html>` est présent, apposé par le cœur ; (c) en revanche « `<main>`
> jamais fermé » et « l'ancre `#liste` jamais émise alors que le lien d'évitement la cible » sont **toutes
> deux confirmées** — c'est le vrai dommage d'accessibilité, et il est réel.
>
> Le témoin confirme au passage **la combinaison 2 du tableau A-2 corrigé** (`publication` absente,
> `releve` présente), rendue en conditions réelles.

---

## Fonctions de lecture exposées par l'extension — consommées, inchangées

| Appel | Clés lues | Type | Garde côté thème (après #29) |
|---|---|---|---|
| `massifs_fraicheur( null )` | `jour_validite` | `string` | comparaison directe |
| | `evalue_le` | `string` | **`is_string()` + `try/catch`** |
| | `publie_prefecture_le` | `?string` | **`is_string()` + `try/catch`** |
| | `dernier_releve_le` | `?string` | **`is_string()` + `try/catch`** |
| | `perimee` | `bool` | hors bloc |
| `massifs_synthese_du_jour( massifs_codes(), null )` | `jour_validite` | `string` | comparaison directe |
| `massifs_jour_courant()` | — | `string` | — |
| `massifs_horodatage( string )` | `date_longue` · `heure` · `attr_datetime` | `string` | **retour NON revérifié** |

**Aucune clé nouvelle, aucune fonction nouvelle, aucune route REST.** L'issue #29 n'expose, ne consomme
et ne déclare **aucune** route REST.

### Pourquoi le retour de `massifs_horodatage()` n'est pas revérifié

Divergence **délibérée** avec le patron de `parts/liste-statuts.php` (l. 183, 200-201, 337-340), qui fait
`isset( $horodatage['date_longue'] )` sur le retour. Reproduire cela **violerait** l'interdit du contrat
#5 l. 121 (« accès direct, jamais `isset()`, jamais `??` » sur les tableaux du contrat). De plus
`Horodatage::formater()` a un **unique `return`** (l. 88-95) écrivant littéralement ses cinq clés : une
clé manquante exigerait d'éditer ce littéral, et le contrat #5 veut alors l'**avertissement PHP**, pas
une omission silencieuse. `liste-statuts.php` relève du contrat #6, pas du mien.

**On calque la structure (garde de type + `try/catch` + omission), jamais le canal ni la vérification du
retour.**

---

## Invariants serveur dont dépend la validité du HTML de l'accueil

Opposables. Tenus **hors du thème**, donc relâchables par une chaîne future qui l'ignorerait.

| # | Invariant | Effet de sa violation **après** #29 |
|---|---|---|
| **I-1** | `massifs_fraicheur()` retourne toujours `evalue_le`, toujours une `string`, toujours un instant acceptable par `Horloge::instant_depuis_chaine()` | Ligne de fraîcheur **entière omise** + journal. **Plus de page cassée.** |
| **I-2** | `publie_prefecture_le` / `dernier_releve_le` sont `string|null`, jamais un autre type | Proposition **omise**, rendu identique au cas `null`, jamais faux |
| **I-3** | `massifs_horodatage()` retourne ses cinq clés sur tous ses chemins de retour | Avertissement PHP visible (voulu) — le thème ne vérifie pas ce retour |

---

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | `synthese['etat_global']` | inchangé (contrat #5) |
| `hors_saison` | `synthese['etat_global']` | inchangé |
| `non_encore_publie` | `synthese['etat_global']` | inchangé |
| `donnee_perimee` | `fraicheur['perimee']` | inchangé — bandeau de péremption, hors ardoise |
| `publication_partielle` | `synthese['partiel']` | inchangé |
| `etat_global` inconnu | — | inchangé (#27) |
| `couche_effis_indisponible` | — | hors périmètre |
| **instant inexploitable** (#29) | **par aucun chemin de donnée** — voir « défaut réel » | **omission** de la proposition, ou de la ligne entière si `validite` échoue. Journalisé. **Jamais** de valeur de secours affichée |

**Aucun état neuf n'est créé.** #29 n'ajoute qu'un mode de **dégradation** : l'application d'A-2.

---

## Chaînes fournies par le serveur

`massifs_horodatage()['date_longue' | 'heure' | 'attr_datetime']`, rendues telles quelles, **jamais
composées ni reformatées**. Les fragments de prose de la ligne de fraîcheur restent ceux de
`MASTER.md` §11.3, **mot pour mot**, découpés en trois propositions et jamais réécrits.

**Aucune chaîne nouvelle n'est rédigée par cette issue.** Les seules chaînes ajoutées sont des **lignes de
journal**, jamais vues par un visiteur, donc hors de la liste fermée du §11.3.

---

## Interdits

- **Aucune valeur de secours affichée.** Ni ISO brut, ni tiret, ni « date indisponible ». **Seule
  l'omission** (A-1, A-2). L'objectif de l'issue parle d'une « valeur de secours » : elle est **refusée**,
  voir arbitrage **A-40**.
- Le thème ne formate **jamais** une date hors `massifs_horodatage()`. #29 **protège** l'unicité de ce
  point de passage.
- **Ne jamais attraper `\TypeError` ni `\Throwable`.** On ne rattrape pas son propre défaut de
  programmation : on le **prévient** par `is_string()`.
- `isset()` et `??` restent interdits sur les tableaux du contrat. `is_string( $tableau['cle'] )` est
  **autorisé et conforme** — voir arbitrage **A-41**.
- `_doing_it_wrong()` reste **interdit dans `front-page.php`** (A-33).
- Aucune classe CSS neuve, aucun élément neuf, aucun attribut neuf, aucun script, aucun cookie, aucune
  requête tierce. `assets/css/**` est hors empreinte : **aucun crochet CSS ne doit exiger de style**.
- Le thème ne calcule aucune règle métier. `is_string()` est une vérification de **type du langage**, pas
  une règle métier.
- **Le rendu nominal doit être identique octet pour octet.** C'est le critère de recette le plus fort de
  cette issue.

---

## Arbitrages

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-39** | Le défaut décrit par l'issue (`\InvalidArgumentException` sur donnée en base) est **inatteignable** ; le défaut atteignable (`\TypeError` sur clé renommée) n'est **pas** dans l'issue | **Les deux sont traités** : `is_string()` (ferme le trou réel) **et** `try/catch ( \InvalidArgumentException )` (défense en profondeur documentée). Priorité de l'issue ramenée de **HAUTE à MOYENNE** | Le `try/catch` seul serait **décoratif**. La garde de type seule laisserait `front-page.php` désaligné des neuf autres appels du thème, et la preuve d'inatteignabilité vit dans `RegistreReleves::entree()`, **hors du thème** : faire dépendre la validité du HTML de l'accueil d'un invariant maintenu ailleurs sans filet local est la classe de couplage non réconcilié que A-31 existe pour refermer. Le filet coûte trois `catch` et **zéro octet réseau** |
| **A-40** | « soit afficher l'ardoise avec une **valeur de secours**, soit **s'arrêter proprement** » (objectif de l'issue) | **Les deux branches sont refusées. Seule l'omission est retenue.** | (a) `date_longue`, `heure` et `attr_datetime` proviennent **toutes** de l'appel qui vient d'échouer : aucune seconde source n'existe. A-1 a déjà tranché le cas jumeau — « Afficher un `2027-06-01` brut serait le thème choisissant un format. **Omettre, jamais inventer.** » (b) « S'arrêter proprement » est **impossible** : le seul mécanisme (`ob_start()` + `ob_end_clean()`) jetterait le `h1#titre-du-jour`, produisant une page à **zéro `h1`** — violation du brief §8 et du plan de titres du contrat #5. **Ce n'est pas une question bloquante** : l'issue elle-même nomme la règle d'omission A-2 dans sa tâche 1 et dans son « Pourquoi cette issue existe ». Le mot « secours » de l'objectif est un lapsus, contredit par le corps de l'issue |
| **A-41** | `is_string( $tableau['cle'] )` viole-t-il l'interdit « accès direct, jamais `isset()`, jamais `??` » du contrat #5 l. 121 ? | **Non. Explicitement autorisé.** | L'accès au tableau est **direct** : PHP émet `Warning: Undefined array key` **avant** d'évaluer `is_string()`, qui reçoit une valeur déjà lue. `isset()` et `??` sont des **constructions de langage** qui suppriment l'accès diagnostiqué ; `is_string()` est une **fonction**. Le motif écrit de l'interdit — qu'une clé absente produise un avertissement visible — est **intégralement tenu**. La garde de type n'achète pas le silence, elle achète la page |
| **A-42** | Portée du `try` face à A-37 (« la seule affectation ») | Le `try` contient **l'appel et la seule expression qui consomme son résultat** — rien d'autre : aucun autre appel domaine, aucune autre proposition | Lecture **fidèle au motif** d'A-37, non à sa lettre. Argument (2) d'A-37 est ici **inversé** : laisser le `sprintf` hors du `try` le ferait s'exécuter après un `catch` avec `$massifs_jour_formate` **indéfinie** → `null['date_longue']` → **la panne se déplacerait**, exactement ce qu'A-37 refuse. Les comparaisons de la passerelle A-1 restent **hors** du `try` : elles ne peuvent rien lever (argument 1 d'A-37) |
| **A-43** | `catch` capturant (précédent A-34) ou non capturant ? | **Non capturant** : `catch ( \InvalidArgumentException )` sans variable | **Vérifié dans le code** : les **deux** sites de `throw` (`Horloge.php` l. 249 et 266) émettent le **même littéral constant**, `'Instant attendu au format ISO 8601.'`. `getMessage()` ne discrimine donc **rien** — au contraire d'`UnhandledMatchError`, dont le message porte le fait discriminant qui **justifiait** A-34. Trois variables inutilisées seraient signalées par WPCS. Le fait discriminant (quelle proposition a échoué) est porté par le **libellé** de chacune des trois lignes de journal |
| **A-44** | **Correction d'A-2, qui est factuellement faux depuis son gel** | La ligne 3 d'A-2 (« `dernier_releve_le === null` ⇒ *Statuts du {date}.* ») décrit un rendu que le code **ne produit pas**. Remplacée par un tableau à **quatre** combinaisons. Consigné en **révision 7 de `docs/contracts/issue-5.md`** | Sous la **seule** condition `dernier_releve_le === null`, le code produit « Statuts du {date}, **publiés la veille à {heure} par la préfecture.** » Le rendu « Statuts du {date}. » correspond à la conjonction des **deux** nulles. Le tableau n'est pas non plus une liste de décision lue dans l'ordre, sa ligne 2 s'appliquant alors à la combinaison 4 en prétendant rendre un relevé inexistant. **Défaut relevé par `leaddev-front-cms`, contre ma propre formulation de briefing, qui était erronée** — consigné comme tel |
| **A-45** | Le journal de la l. 360 (« la date de validité ne peut pas être mise en forme sans `massifs_horodatage_jour()`, demande B-1 ») devient **partiellement faux** : le chemin est désormais atteint par trois causes | **Reformulé (J-0).** Vrai dans les trois cas, conserve le pointeur B-1, renvoie sans déixis vers J-1 ou vers l'avertissement PHP | A-38 rend **opposable en revue** un message qui devient faux quand le code autour de lui change. Sans reformulation, le message **attribuerait à l'absence de B-1 une rupture de contrat serveur**. Seule modification d'un comportement existant, et elle porte sur une chaîne de journal **jamais vue par un visiteur** |
| **A-46** | **Extension de zone, dans l'empreinte** : `massifs_journaliser( $massifs_ardoise['journal'] )` porte le **même défaut, en pire** — `massifs_journaliser( string )` est strictement typée, et cet appel s'exécute **avant** le premier octet de sortie | **Gardé.** Une garde de type sur `journal`, de la même forme que les trois autres | Défaut de la **même classe**, dans **le même fichier**, servant **la même ligne de DoD** (§12 robustesse), et **plus grave** : pré-émission ⇒ HTTP 500 + page « Erreur critique » du cœur ⇒ **zéro statut, zéro lien officiel** — précisément ce que #27 vient de refermer dix lignes plus haut, par un autre déclencheur. Laisser un défaut de sévérité maximale, corrigible en une ligne, dans le fichier même que l'on durcit, serait indéfendable en revue. **Atténuation notée** : les cinq sites d'écriture de `journal` sont **locaux à ce fichier** (contrairement à `etat_global`, qui vient de l'extension), donc le déclencheur est moins probable que celui d'A-39 — c'est ce qui en fait une **extension de zone** et non une issue à part. Relevé par `leaddev-front-cms` (Q-1) |
| **A-51** | Le journal **J-0 sur-affirmait** : « toute autre cause fait l'objet d'une ligne distincte » est **faux** pour la cause « clé présente, type invalide », qui ne produit ni J-1 ni avertissement PHP | **Corrigé** : J-0 nomme désormais les **deux** causes qui laissent réellement une trace — « l'instant refusé fait l'objet d'une ligne distincte, la clé absente d'un avertissement PHP » — et **cesse de prétendre à l'exhaustivité** | Même classe de défaut qu'A-45, qui a motivé la réécriture de ce message : **un journal qui affirme plus que le code ne tient est un défaut**, et il induit en erreur exactement au moment où l'on diagnostique. A-45 exigeait par ailleurs que J-0 renvoie « vers J-1 **ou vers l'avertissement PHP** » : la rédaction précédente ne nommait ni l'un ni l'autre. **L'objection de `refacto-cms` (« réécrire une chaîne de journal change les octets émis ») est écartée** : la contrainte d'identité octet pour octet porte sur le **HTML rendu**, jamais sur `error_log`, et R-29 n'étant pas encore écrite, aucune assertion ne dépend de ce texte |
| **A-47** | Résidu : une valeur **non-`null` d'un autre type** est omise **sans journal** et sans avertissement PHP (la clé existe). **Étendu, sur signalement de `refacto-cms`, à `evalue_le`** et non aux seules deux clés `?string` : `evalue_le` est `readonly string`, un autre type n'y est atteignable que par rupture du même invariant, et la garde l'omettrait tout aussi silencieusement — **en emportant la ligne entière, pas une proposition** | **Accepté et documenté**, sans journal dédié | L'omission est alors **indistinguable du rendu contractuel de `null`** : la page n'est jamais **fausse**, seulement moins complète. Seul le *diagnostic* manque, pas la *correction*. Le journaliser coûterait deux `if` et deux libellés pour une observabilité que la DoD de cette issue ne demande pas. **Signalé comme dette candidate**, non traité ici |
| **A-48 bis** | **La formulation non bornée d'A-32 est DUPLIQUÉE** : le commentaire du bras `disponible` du `match()` répète le même critère mot pour mot, et A-48 n'en nommait qu'un site | **Les deux emplacements sont rebornés, dans une formulation alignée** | Reborner un seul site **ne referme rien** : il déplace le piège en laissant la version dangereuse à l'endroit le plus lu des deux. Le fichier porterait **deux versions divergentes du même critère de revue** — la classe de défaut que #27 et A-48 existent précisément pour refermer. Que A-48 n'ait nommé qu'un site est une **limite de mon arbitrage**, pas une borne voulue : il a été écrit sur un signalement qui ne mentionnait qu'un emplacement. Deux rédactions différentes du même critère rouvriraient la divergence sous une autre forme, d'où l'alignement de formulation. **Relevé par `dev-front-cms`, qui a eu raison de le signaler plutôt que de l'élargir seul** |
| **A-49** | Le commentaire §11.3 du bloc de fraîcheur énonce encore « en supprimant la proposition dont la valeur serveur est **nulle** » | **Mis au niveau de la révision 7** : trois causes nommées (`null`, valeur non-`string`, instant refusé) | Le commentaire n'est pas faux vis-à-vis du **code**, il est **en retard sur le contrat qui le gouverne** (A-2 révisé, « valeur inexploitable ») et il contredit le bloc d'interdit situé trois lignes plus bas. Il doit énoncer la propriété qui rend la dégradation sûre : **les trois causes produisent exactement le même rendu — l'omission — donc la page est moins complète, jamais fausse** |
| **A-50** | **A-36 s'étend aux quatre nouvelles lignes de journal** : `massifs_journaliser()` n'écrit rien hors `WP_DEBUG` | **Accepté, et consigné sans détour** : en production, une dégradation par `catch` ne laisse **aucune trace dans les logs** | Identique au raisonnement d'A-36 pour l'ardoise. Le caractère « bruyant » est porté par le **rendu**, non par le journal : le seul signal en production est l'**omission visible** de la ligne de fraîcheur. À ne pas découvrir en exploitation |
| **A-48** | Commentaire l. 58-63 : il reproduit la version **non bornée** d'A-32 (« ce tableau … ne contient **AUCUN** chiffre », « CRITÈRE DE REVUE : … si et seulement si elle peut contenir un nombre ») | **Reborné**, sans renégocier A-32 : le hissage reste, son motif reste. Le commentaire dit désormais que le tableau ne porte aucun chiffre **dans le texte présenté au visiteur**, le seul chiffre étant celui de l'URL officielle, portée en **attribut `href`** | **Littéralement faux tel qu'écrit, vérifié** : `massifs_attribution_statuts()['carte_officielle_url']` vaut `https://www.risque-prevention-incendie.fr/13` (`domain/statuts/api.php` l. 325) — donc « 13 ». Le contrat #27 vient d'être borné au **texte rendu** ; le commentaire du code était devenu **le plus laxiste des deux**, et un relecteur l'appliquant à la lettre **supprimerait le lien officiel** exigé par le brief §4.2. Défaut relevé par la **chaîne #31**, qui a eu raison de ne pas y toucher : le fichier est hors de son empreinte |

---

## Recette R-29 — **spécifiée ici, léguée à `test-integration-cms`**

**La tâche 3 de l'issue n'est pas réalisable dans cette empreinte.** La recette vit dans `tests/rendu/`
(`recette-rendu.mjs`, `etats.php`) et `tests/scenarios/**`, **tous hors empreinte**. **Précédent
décisif** : l'empreinte du contrat #27 ne contient pas `tests/` non plus, et pourtant la recette R-27
existe — elle y a été écrite **au niveau lot**, par `test-integration-cms`. C'est le protocole suivi.

**Protocole disponible** : injection locale et temporaire dans `front-page.php`, restauration en
`finally`, assertion de remise en état à l'octet — `recette-rendu.mjs` l. 3817-3846 et 3920-3926.
**Ancre existante** : la ligne affectant `$massifs_peremption`, où `$massifs_fraicheur` est en portée.

**Cas à couvrir**

| # | Injection | Attendu |
|---|---|---|
| 1 | `$massifs_fraicheur['evalue_le'] = 'pas-une-date';` | ligne de fraîcheur **entière** omise, page complète |
| 2 | `unset( $massifs_fraicheur['evalue_le'] );` | idem (**le cas à valeur réelle** : c'est le `TypeError` d'aujourd'hui) |
| 3 | `$massifs_fraicheur['publie_prefecture_le'] = 'pas-une-date';` | « Statuts du {date} — relevés sur ce site le {date} à {heure}. » |
| 4 | `$massifs_fraicheur['dernier_releve_le'] = 'pas-une-date';` | « Statuts du {date}, publiés la veille à {heure} par la préfecture. » |
| 5 | `unset( $massifs_ardoise['journal'] );` (A-46) | page complète, **pas de HTTP 500** |
| 6 | aucune | **rendu identique octet pour octet** à la référence — l'assertion la plus forte |

**Assertions** (recopiables de s23, l. 3855-3905) : HTTP 200 · exactement un `h1#titre-du-jour` · **chiffre
de l'ardoise conservé** · zéro `<time>` dans `#ardoise` (cas 1, 2) · `[id="liste"]` présent exactement une
fois · `</main>` et `</html>` présents · aucune trace de `Fatal error` / `TypeError` / `rreur critique`
dans le corps.

**À écrire dans la recette, pas à cacher** : les cas 1, 3, 4 ne sont observables **que** par injection de
source, aucun chemin de donnée ne pouvant les produire. Sans cette mention, R-29 se lirait comme une
couverture de production qu'elle **n'est pas**.

**Cas 2 excepté** : lui est un vrai défaut atteignable, et c'est celui que #29 ferme.

> **À NE PAS ASSERTER COMME ANOMALIE.** Dans les cas 1 et 2, **deux** lignes de journal sont émises pour
> un seul défaut : **J-1 puis J-0**. Ce n'est pas un doublon — J-1 porte la **cause** (l'instant a été
> refusé), J-0 porte la **conséquence** (la ligne entière est omise, publication et relevé avec elle), et
> J-0 énonce lui-même que « toute autre cause fait l'objet d'une ligne distincte ». La recette doit
> attendre les deux. Relevé par `dev-front-cms`.
>
> **Cas 6 — état réel après mesure.** Les cas 1, 2 et le témoin **ont été exercés** par la chaîne #29 (voir
> le tableau de mesure plus haut) : les trois gardes et le `catch` de `evalue_le` ont tourné, et le chemin
> nominal rend correctement sa ligne de fraîcheur. **Ce qui reste non mesuré** est plus étroit que ce que
> la chaîne croyait : l'**identité octet pour octet du rendu nominal avant/après le patch**, faute de
> référence pré-patch prise dans le même état de données injecté. L'identité repose sur un **argument de
> types vérifié à la source** : `evalue_le` est `readonly string` (troisième conjoint toujours vrai),
> `publie_prefecture_le` et `dernier_releve_le` sont `?string` (`is_string( X )` ≡ `null !== X`), et un
> `try` sans levée n'émet rien.
>
> **Restent à la charge de `test-integration-cms`** : le cas 3 (`publie_prefecture_le` malformé), le cas 4
> (`dernier_releve_le` malformé — la combinaison 3 du tableau A-2 corrigé, jamais rendue à ce jour), le
> cas 5 (`journal` retiré, A-46), et le cas 6 sur une **fixture seedée** plutôt que par injection.

---

## Signalé, non traité

- **Chiffre sans ligne de fraîcheur.** Sous omission, l'ardoise peut afficher un chiffre **sans** son
  indicateur, alors que le brief §4.5 demande un indicateur « partout où un statut apparaît ». **Ce
  comportement existe déjà** (arbitré par A-1) et le bandeau de péremption reste rendu à part : #29
  n'introduit rien de neuf. L'alternative — basculer l'ardoise sur `indisponible`, donc **sans chiffre** —
  est plus sévère, plus conforme au §4.5, et **hors de la lettre de l'issue**. À trancher par le
  propriétaire, pas à hériter par inertie.
- **A-47** : résidu de type silencieux sur les deux clés `?string`.
- **Priorité de l'issue** : HAUTE → MOYENNE (A-39).
- **Texte de l'issue à corriger** : retirer « valeur de secours » et « s'arrêter proprement » (A-40) ;
  remplacer le défaut constaté par le vrai (A-39).
