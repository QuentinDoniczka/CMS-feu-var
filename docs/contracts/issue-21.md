# Contrat d'interface — Issue #21 — Amender le brief et CLAUDE.md pour la cible « décideur communal », et corriger 27 → 25

**Gelé par** `lead-issue-cms` · 12 août 2026 · Approche retenue : **Option A** (amendement en trois
touches, portée déclarée, garde-fou de grep).

Cette issue est **purement documentaire**. Elle ne touche ni le thème ni l'extension : aucune fonction
de lecture, aucune route REST, aucun état spécial, aucune chaîne serveur n'est créée, modifiée ou
supprimée. Les sections normalement portées par ce contrat sont donc sans objet, et le poids du contrat
est reporté sur les **interdits** et les **arbitrages** — c'est là que vit le risque de cette issue.

---

## Fonctions de lecture exposées par l'extension

**Sans objet.** Aucune fonction `massifs_*` n'est créée, modifiée, renommée ou supprimée.
L'empreinte fichiers de l'issue ne contient aucun fichier PHP.

## Routes REST

**Sans objet.** Aucun endpoint public ni portail n'est touché.

## États spéciaux

**Sans objet.** Le contrat des états spéciaux reste celui gelé par `issue-3.md`, `issue-5.md` et
`issue-6.md`. Aucun d'eux n'est rouvert.

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | inchangé (`issue-5.md`) | inchangé |
| `hors_saison` | inchangé (`issue-5.md`) | inchangé |
| `donnee_perimee` | inchangé (`issue-5.md`) | inchangé |
| `couche_effis_indisponible` | inchangé (`issue-6.md`) | inchangé |

## Chaînes fournies par le serveur

**Sans objet en écriture.** Aucun libellé de niveau, aucune consigne, aucune attribution, aucune phrase
de fraîcheur n'est créée ni modifiée par cette issue.

**Une seule chaîne est concernée, et uniquement dans sa transcription documentaire** : `vers 17 h`.
Le contrat `issue-6.md` (l. 128) fige la phrase du §11.3 avec la mention explicite « espace insécable
U+00A0 », et `issue-5.md` (arbitrage B-2) pose `publication_heure_libelle = '17 h 00'` en espaces
insécables. **Le code des `templates/parts/` est donc conforme au contrat ; c'est `MASTER.md` qui porte
la coquille.** Voir arbitrage A-6.

---

## Empreinte fichiers — stricte

**Autorisés en écriture, et rien d'autre :**

- `docs/BRIEF.md`
- `CLAUDE.md`
- `design-system/MASTER.md`
- `docs/contracts/issue-21.md` (ce fichier, propriété du lead)

**Interdits absolus en écriture** — arbre de travail partagé, mono-branche, aucune isolation :

- `wp-content/**` **en entier**. La chaîne #22 y écrit `composants.css`, `print.css` et `functions.php`
  au moment même où cette issue s'exécute. Un fichier écrit là est une perte irrécupérable.
- `docs/contracts/` autre que `issue-21.md`. Les contrats #2 à #6 sont **gelés**.
- `docs/decisions/`, `tests/`.

---

## Interdits

### 1. Le §12 de `MASTER.md` n'est pas ouvert

Le §12 est la transcription **octet pour octet** de
`wp-content/themes/massifs/assets/css/tokens.css`, dont le sha256 est
`5ad802a3708fe1734845e7a76b46de5382f2421268542584cafa270d29aa3835`
(**vérifié par le lead avant gel** : le fichier porte bien cette empreinte).

- Ne pas ouvrir le bloc, ne pas le relire « pour vérifier », ne rien y modifier.
- **Piège nommé** : la ligne 1325, *à l'intérieur* de la clôture de code, porte
  `Voir design-system/MASTER.md (v2.0).` — identique à la ligne 1 de `tokens.css`. Le document passe en
  **v2.2** dans cette issue. **Ce commentaire ne doit PAS être « harmonisé » en v2.2.** Il transcrit un
  fichier réel ; le mettre à jour casse la transcription et fait tomber le contrat #4.
- Les jetons `--frise-l` / `--frise-h` restent en place. Leur sort appartient à #23, pas à #21.

### 2. Ne jamais faire le travail de l'issue #23

#23 s'exécute **après** cette issue et possède, dans `MASTER.md` :

- la **suppression de la frise** (l. 589 et ses treize points d'ancrage : §1 l. 52, §3.3 l. 209,
  §6.1 l. 519, §7.1 l. 648-649, §8.2 l. 806-824, §9.3 l. 969, §9.4 l. 985, §10.1 l. 1035,
  §10.2 l. 1068, §10.6 l. 1161, §12 l. 1449, §13 l. 1555, §16 l. 1775 et 1779) ;
- le **recadrage typographique normatif** : §5.1, §7.3, §14.3 entrée 5 (b), §16.

**#21 corrige des comptes ; #21 ne supprime rien et ne réécrit aucune règle normative.**
La ligne 589 voit `27 marques` → `25 marques`. Elle n'est **pas** supprimée. Un agent qui « termine le
travail » en retirant la frise ou en réécrivant §5.1 détruit le périmètre de #23.

### 3. La correction 27 → 25 est bornée à `MASTER.md`

« 27 » est **factuellement juste** ailleurs, et un `sed` large détruirait un fait établi :

- `docs/decisions/source-prefecture.md` (l. 122, 439, 529, 543), `docs/contracts/issue-3.md` (l. 223),
  `tests/README.md` (l. 119), `tests/scenarios/13-*.php`,
  `wp-content/plugins/massifs-core/includes/ingest/prefecture/class-runner.php` (l. 224, 228) : le
  **flux préfectoral** porte bien 27 identifiants. Ne pas y toucher.
- `docs/contracts/issue-5.md` (l. 422) contient « la frise des 27 marques ». Il est **gelé**. Ne pas y
  toucher. L'invariant de fin d'issue est donc « plus aucun *27 massifs / 27 marques* dans
  **`MASTER.md`** », jamais « dans le dépôt ».

### 4. `U+0027` ne se touche jamais

`MASTER.md` §11.4 (l. ~1308) contient `apostrophe droite U+0027`. Toute recherche non ancrée sur « 27 »
le capture. Il est **sans rapport** avec le nombre de massifs. Grep d'acceptation : `U\+0027` doit
**toujours** renvoyer exactement 1 résultat après la passe.

### 5. Aucune invention

Aucune valeur hexadécimale, aucun des 111 jetons, aucune mesure du §10, aucun libellé officiel n'est
approché. Aucune commune n'est nommée. Aucun contrat gelé n'est rouvert.

### 6. L'ancrage du sujet est MAINTENU

BRIEF §7 « garrigue, calcaire, pin, mistral, signalétique de sentier (balisage peint, panneaux DFCI) »
**reste entier**. L'amendement dit que le rendu atelier **vise** un décideur communal ; il ne dit ni
n'implique que l'atelier est abandonné. Toute formulation qui pourrait se lire comme une abrogation du
§7 est un défaut.

---

## Travail à exécuter

### T1 — `docs/BRIEF.md` §7, ligne liminaire

Le §7 n'a **aucune ligne d'introduction** : le titre attaque directement les puces. Insérer **une ligne
liminaire avant la puce « Processus attendu »**, car la cible gouverne les six puces et ne relève
d'aucune.

Elle doit dire : le destinataire du rendu atelier est un **décideur communal** — l'élu ou le directeur
général des services qui évalue une offre — et **l'ancrage du sujet de la puce suivante reste entier**.

Elle ne doit **pas** : nommer une commune, un client, un prospect ou un marché ; ajouter une exigence de
contenu (pas de page « références », pas d'argumentaire) ; ouvrir vers le §13.

**Ce n'est pas une dérive de périmètre, et c'est démontrable** : la cible est déjà dans le brief —
§1 l. 12 « site **communal** », §6 l. 131 « le visiteur — donc **l'élu qui évalue l'offre** ». La phrase
remonte au §7 un destinataire que le brief nomme déjà deux fois. Zéro fait nouveau.

**Lexique imposé** : « **communal** » (mot du brief §1), jamais « municipal ». Le §11.2 de `MASTER.md`
interdit les synonymes flottants. « DGS » s'écrit en toutes lettres au moins une fois.

### T2 — `CLAUDE.md`, contrainte n°4

Enrichir la **cellule « Conséquence concrète » de la ligne 4** du tableau existant.
**Ne pas créer une cinquième ligne** : le titre « Les 4 contraintes non négociables », la phrase
d'alerte au-dessus du tableau et les prompts d'agents référencent tous « les 4 contraintes ».

Le risque est **asymétrique** : « décideur communal » évoque « site institutionnel », qui est à trois
centimètres de « thème générique bleu » — l'exact opposé de la contrainte. Trois protections cumulatives,
obligatoires :

1. **Formuler en négatif d'abord** : la clause borne le registre en *interdisant* (jamais ludique, jamais
   « produit grand public », jamais registre marketing ou landing page). Une clause qui s'ouvre sur une
   interdiction ne se lit pas comme une permission.
2. **Nommer dans la même phrase ce qu'elle n'autorise pas** : ni thème acheté, ni kit UI, ni esthétique
   « template institutionnel » ; l'ancrage garrigue/calcaire/pin/DFCI du §7 du brief reste entier.
3. Rester dans la cellule existante.

### T3 — `MASTER.md` §15, entrée D-26

D-26 se place **après D-25**, dernière entrée du §15.

**Structure imposée — la perte d'abord, la raison ensuite, l'inoculation en clôture :**

1. **Ce qu'on perd, cité correctement.** Le §2 (ligne « Panneaux DFCI », l. 67) tire de la signalétique
   **trois gestes : capitales condensées, sérigraphie, rayon nul**. Le premier est abandonné.
   Le §14.3 entrée 5 (l. 1685-1701) le **défendait explicitement** — « **Verdict : conservé** — c'est le
   langage du panneau DFCI, il est le sujet et non un effet ». Cette défense est **levée**, elle n'est pas
   invalidée. Restent la sérigraphie, le rayon nul, et la condensée elle-même qui demeure la famille de
   titrage.
   **Attention à la citation** : la liste des trois gestes est au **§2**, pas au §14.3. Le §14.3 entrée 5
   porte la *défense*, et son « trois » désigne trois **règles de bornage** (a/b/c), pas trois gestes.
   Citer §14.3 comme source des « trois gestes » serait inventer une référence.
2. **La raison, et elle seule** : le déplacement de la cible de réception vers un décideur communal. Un
   `h1`/`h2` intégralement en capitales condensées lit, pour ce destinataire, comme de la signalétique ou
   de la campagne. Le §1 l. 40-41 énonce déjà la tension — « sobriété de service public, mais avec la
   brutalité graphique d'une signalétique de terrain » : l'arbitrage bascule vers le premier pôle **pour
   la typographie seule**, la couleur, les formes, le rayon nul, le repère et le fond monochrome restant
   au second.
3. **Clause d'inoculation, obligatoire et explicite.** Cette décision **n'est pas motivée par
   l'accessibilité**. Le §14.3 entrée 5 avait déjà relevé le risque a11y — « les capitales dégradent la
   vitesse de lecture », « certains lecteurs d'écran épellent les chaînes courtes » — et l'avait borné,
   donc tenu pour couvert. Le gain de lisibilité est un **effet secondaire, non recherché**, et **ne doit
   jamais être cité comme la raison**.
4. **Borne dure** : capitales admises **uniquement** sur les étiquettes ; **interdites** sur `h1`/`h2`.
5. **Référence avant, obligatoire** : « L'application normative — §5.1, §7.3, §14.3 entrée 5 (b), §16 —
   appartient à l'issue #23. Jusqu'à son exécution, ces sections restent en vigueur et cette
   contradiction est **connue et assumée**. »
   **Sans cette phrase, l'issue crée exactement le défaut de revue qu'elle prétend prévenir** : D-26
   acterait le renoncement pendant que quatre sections continuent d'imposer les capitales, et
   `review-cms` — qui tourne en fin de lot, donc entre #21 et #23 — le signalerait.

### T4 — `MASTER.md`, journal de révision en tête : ligne **v2.2**

Une seule ligne, sur le modèle exact de la ligne v2.1 (qui écrit déjà « **Le §12 n'est pas touché** »).
Son « Ce qui change » énonce quatre choses :

(a) la **cible de réception** est consignée (BRIEF §7, `CLAUDE.md` n°4) ;
(b) le compte passe à **25**, **y compris dans les sections d'archive §14.2 et §15 D-19**, avec la
raison : *une correction de compte n'est pas une réécriture de décision* — D-19 a décidé « une frise, une
marque par massif », et cette décision reste vraie ; seul le cardinal était faux. **Déclarer cette
correction ici est ce qui satisfait la règle de l'en-tête « l'historique est conservé, jamais réécrit en
silence ».** Sans la déclaration, la règle est violée à la lettre ;
(c) le renoncement aux capitales est consigné en **D-26**, **son application normative appartenant à
#23** ;
(d) **le §12 n'est pas touché.**

Le document passe en **v2.2**. Le champ *Version* de l'en-tête (l. 3) suit. **#23 ouvrira v2.3** —
voir arbitrage A-1.

### T5 — Correction 27 → 25, ligne par ligne

| Ligne | Section | Occurrence | Action |
|---|---|---|---|
| 52 | §1 | « une frise de 27 marques » | **25** |
| 209 | §3.3 | « les 27 marques de la frise » | **25** |
| 585 | §7.1 schéma | `AUJOURD'HUI, 12 MASSIFS SUR 27` | **25** |
| 587 | §7.1 schéma | `/27` | **25** |
| 589 | §7.1 schéma | `← LA FRISE, 27 marques` | **25** — **jamais la suppression** |
| 669 | §7.2 | « moins d'une minute pour 27 massifs » du §6 du brief | **recouper la citation**, voir A-3 |
| 671 | §7.2 | « les 27 massifs partagent le même état » | **25** |
| 802 | §8.2 | dénominateur « /27 » | **25** |
| 808 | §8.2 | « rangée de **27 marques** » | **25** |
| 1249 | §11.1 règle 7 | exemple « 12 massifs sur 27 » | **25** |
| 1601 | §14.2 | archive : « frise de 27 marques » | **25**, déclaré en v2.2 |
| 1602 | §14.2 | archive : « La frise des 27 marques » | **25**, déclaré en v2.2 |
| 1672 | §14.3 entrée 3 | « « 12 sur 27 » est une valeur de l'extension » | **25** |
| 1678 | §14.3 entrée 4 | même faux verbatim qu'en 669 | **traitement identique à 669, obligatoirement** |
| 1746 | §15 D-19 | archive : « frise des 27 marques » | **25**, déclaré en v2.2 |
| **1308** | §11.4 | `apostrophe droite U+0027` | **JAMAIS** |
| 796 | §8.2 | « de 11 à 25 » | déjà 25, coïncidence — **ne rien faire** |

**Le §16 ne contient aucune occurrence de « 27 ».** L'item 6 de la checklist de l'issue vise une cible
qui n'existe pas — voir arbitrage A-5. Les seuls « 27 » du §16 sont `D-25` (l. 1769) et `330°–25°`
(l. 1783), sans rapport. Ses deux mentions de la frise (l. 1775, 1779) sont **sans compte** et
appartiennent à #23.

**Alignement du cadre ASCII** : `27` → `25` conserve la largeur (deux chiffres). Les lignes 580-593
doivent rester alignées au caractère près. Vérifier après édition.

**Fait vérifié à ne pas re-litiger** : la ligne 589 dessine **déjà 25 marques**
(`▪▪▪▪▪▨▨▪▪▪▨▨▨▪▪▪▪▨▨▪▪▪▪▨▨` = 5+2+3+3+4+2+4+2 = 25). Seule la légende dit « 27 ». C'est une correction
de légende erronée par rapport à son propre dessin : **zéro glyphe à ajouter ou retirer**.

### T6 — M-4, « vers 17 h » : aligner `MASTER.md`, jamais le code

La règle §11.1-6 de `MASTER.md` impose elle-même l'espace insécable. Le code des trois
`templates/parts/` (`etats-vides.php:161`, `legende.php:174`, `liste-statuts.php:264`) porte déjà
**U+00A0** et est **conforme aux contrats gelés #5 et #6**. C'est `MASTER.md` qui diverge de sa propre
règle. Aligner le code sur `MASTER.md` rétrograderait trois fichiers conformes pour épouser une coquille.

**Corriger U+0020 → U+00A0 aux quatre emplacements de `MASTER.md`** :

| Ligne | Section | Contexte |
|---|---|---|
| 588 | §7.1 schéma | « veille à 17 h » — largeur inchangée, cadre préservé |
| 1240 | §11.1 règle 1 | « La préfecture publie les statuts vers 17 h » |
| 1247 | §11.1 règle 6 | exemple « 17 h 00 » — **la règle qui impose l'insécable est écrite avec des espaces normales** |
| 1287 | §11.3 | la chaîne normative |

**Aucun fichier PHP n'est touché.** `wp-content/themes/massifs/front-page.php:94` est un second
contrevenant réel — et le plus visible du site, c'est le `h1` de l'ardoise — mais il est **hors empreinte**
et revient à la chaîne propriétaire du thème. À porter au rapport, pas à corriger ici.

### T7 — Grep d'acceptation, borné à `MASTER.md`

Après la passe, sur `design-system/MASTER.md` **seul** :

- `27 (massifs|marques)` → **0 résultat**
- `sur 27` → **0 résultat**
- `/27` → **0 résultat**
- Un « 27 » **subsiste volontairement** dans la ligne de journal v2.2, et c'est correct : déclarer la
  correction exige de nommer les deux nombres (« passe de 27 à 25 », « les 27 identifiants sont ceux du
  flux préfectoral »). Il passe les trois greps ci-dessus. Un `grep 27` non ancré le remontera — c'est un
  fait vrai et déclaré, conforme à B-15/B-16, pas une occurrence oubliée.
- `U\+0027` → **exactement 2 résultats**, inchangés avant/après (l. ~1308, ligne de tableau du §11.4 ;
  et l. ~1311, la note explicative en dessous — « *porte U+0027. C'est ce que publie la source.* »).
  **Correction post-gel** : ce contrat avait d'abord annoncé 1 résultat. Comptage erroné du lead — la
  note du §11.4 avait été manquée. La valeur attendue est **2**. L'invariant réel reste « `U+0027` ne se
  touche jamais » : c'est le compte attendu qui était faux, jamais le fichier. Aucune occurrence ne doit
  être supprimée pour faire passer un grep — la note du §11.4 est précisément ce qui explique la
  divergence volontaire entre `U+2019` et `U+0027`.
- sha256 de `wp-content/themes/massifs/assets/css/tokens.css` → **inchangé**,
  `5ad802a3708fe1734845e7a76b46de5382f2421268542584cafa270d29aa3835`

---

## Arbitrages

Six questions ouvertes par `brainstorm-cms`. **Aucune n'est un fait de domaine** (aucun libellé officiel,
aucune couleur, aucune consigne préfectorale n'est en jeu) : toutes sont des arbitrages de portée
éditoriale, donc du ressort du lead. Les six sont tranchées ici.

| # | Désaccord | Décision | Raison |
|---|---|---|---|
| **A-1** | Numérotation de version : #21 et #23 dans la même ligne v2.2, ou une chacune ? | **#21 ouvre v2.2 ; #23 ouvrira v2.3.** | Deux chaînes séquentielles sur une table dont l'en-tête pose « l'historique est conservé, jamais réécrit en silence ». Une ligne partagée obligerait #23 à réécrire une ligne existante — exactement ce que la règle interdit. La convention est posée **avant**, pas négociée par le second arrivant |
| **A-2** | D-26 appartient-il à #21, alors que §5.1, §7.3, §14.3 (b) et §16 continuent d'imposer les capitales ? | **Oui, dans #21, avec référence avant explicite vers #23** (T3.5) | C'est la raison d'être déclarée de l'issue : faire atterrir le *pourquoi* **avant** que quiconque relise le *quoi*. Déporter D-26 dans #23 laisserait `review-cms` tourner sur un document où le retour à la casse normale n'a aucune justification écrite. La contradiction transitoire est réelle : on la rend **écrite et datée** plutôt que subie |
| **A-3** | Faux verbatim l. 669 et 1678 : « moins d'une minute pour **27 massifs** » attribué au §6 du brief, qui **ne contient aucun nombre** (l. 128 : « Objectif : mise à jour complète en moins d'une minute ») | **Recouper la citation** : le verbatim se réduit aux mots réels du brief, le compte sort des guillemets — « mise à jour complète en moins d'une minute » (§6 du brief) **pour les 25 massifs**. Aux **deux** lignes, identiquement | Passer 27 → 25 dans les guillemets ne falsifie aucune citation *existante*, mais laisserait une citation **apocryphe** dans un livrable §11. La correction porte sur la phrase que l'issue édite déjà, ne coûte rien et supprime une fausseté. **Autorisé explicitement, donc pas improvisé** |
| **A-4** | Les sections d'archive (§14.2, §15 D-19) sont-elles en périmètre, au regard de « l'historique n'est jamais réécrit en silence » ? | **Oui — corriger ET déclarer** en ligne v2.2 (T4.b) | Un compte n'est pas une décision. D-19 a décidé « une frise, une marque par massif » ; cette décision reste vraie, seul le cardinal était faux. Laisser 27 en archive ferait survivre l'erreur au grep d'acceptation. C'est la **déclaration** en v2.2, pas l'abstention, qui satisfait la règle de l'en-tête |
| **A-5** | La checklist de l'issue affirme « le §16 mentionne aussi 27 marques ». **C'est faux** | **Item retiré**, remplacé par le grep d'acceptation borné à `MASTER.md` (T7) | Vérifié : le §16 ne contient aucun « 27 » relatif aux massifs ou à la frise. Ses deux mentions de la frise sont sans compte et appartiennent à #23. Exécuter l'item tel qu'écrit forcerait un agent à inventer une correction, ou à toucher §16 — donc à empiéter sur #23 |
| **A-6** | M-4 « vers 17 h » : aligner le code ou `MASTER.md` ? Et sur quelle étendue ? | **Aligner `MASTER.md`**, sur ses **quatre** emplacements (588, 1240, 1247, 1287), pas seulement le §11.3 nommé dans le constat | Le code est conforme aux contrats gelés #5 (B-2) et #6 (l. 128), qui imposent tous deux l'insécable ; c'est `MASTER.md` qui porte la coquille. Ne corriger que le §11.3 laisserait le §11.1 contredire son propre §11.3, **et la règle 6 se contredire elle-même dans son exemple**. **Extension de périmètre assumée et déclarée**, pas silencieuse |

### Correction apportée à l'énoncé de l'issue

Outre A-5, la checklist affirme que « §14.3 entrée 5 défendait les capitales condensées comme l'un des
**trois gestes d'ancrage DFCI** ». **Vérifié : la liste des trois gestes est au §2 (l. 67)**, pas au
§14.3. Le §14.3 entrée 5 porte la défense explicite, et son « trois » désigne trois **règles de bornage**
(a/b/c). D-26 doit citer **les deux sources correctement** (T3.1) — sinon il invente une référence dans
le document même dont il consigne l'honnêteté.

### Fait vérifié indépendamment avant gel

L'écart 27/25 a été **contrôlé dans `docs/contracts/issue-2.md`, arbitrage B-15**, et non repris d'une
affirmation de tierce main : la couche réglementaire `L_MASSIFS_FORESTIERS_S_013` — celle qui délimite
les massifs de l'arrêté d'accès — contient **exactement 25 entités, `gid` 1…25**. Les identifiants
préfectoraux valent `13` + rang alphabétique, donc `131`…`1325` recouvrent les 25 massifs nommés et
**`1326` / `1327` sont en surnombre, sans correspondance** (`massifs_code_depuis_source()` renvoie
`null`). B-16 confirme : **25 correspondances vérifiées une à une, 0 divergence**.
**Le référentiel est à 25 ; le flux préfectoral est à 27. Les deux affirmations sont vraies et ne
doivent jamais être confondues** — c'est précisément la confusion que l'interdit n° 3 protège.

---

## Signalé, hors empreinte — ne pas corriger dans cette issue

1. `wp-content/plugins/massifs-core/includes/ingest/prefecture/README.md` l. 113 écrit « Le référentiel
   réel (**27 massifs**) ». C'est exactement la confusion que B-15 tranche. **Hors empreinte** — issue
   séparée.
2. `wp-content/themes/massifs/front-page.php:94` porte « vers 17 h » en **U+0020** (le `h1` de l'ardoise).
   **Hors empreinte** — chaîne propriétaire du thème. Bonne nouvelle pour ce futur correctif :
   `tests/scenarios/21-rendu-etats-hors-saison.php` n'assertionne que la première proposition (l. 68) ;
   la chaîne complète (l. 70) n'est qu'un libellé de diagnostic. **Corriger `front-page.php` ne casse
   aucun test.**
3. `docs/contracts/issue-5.md` l. 422 contient « la frise des 27 marques ». **Contrat gelé** — ne pas
   ouvrir. À traiter, s'il y a lieu, par une révision explicite de ce contrat.
4. Pour #23 : le retrait de la frise devra trancher le sort de `--frise-l` / `--frise-h`
   (`MASTER.md` §12 l. 1449, `tokens.css` l. 125). Les supprimer ferait tomber le compte de 111
   propriétés du contrat #4 ; les laisser orphelins est probablement le bon choix, mais il doit être
   **décidé**. Ce n'est pas le problème de #21.
