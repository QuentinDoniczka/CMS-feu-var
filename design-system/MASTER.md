# MASSIFS — Design System

**Version 2.5** · **Date** 15 août 2026 · **Auteur** `lead-design-cms`
**Statut** source de vérité visuelle. Tout travail d'intégration (`dev-ux-cms`, `dev-front-cms`) et toute
relecture (`review-cms`) s'y réfèrent. Livrable §11 du brief (« plan de design »).

> Règle de lecture : ce document décrit des **décisions**, pas des suggestions. Une valeur qui n'est pas
> ici n'existe pas dans le CSS. Une divergence constatée en revue est un défaut, pas une variante.
> Les blocs marqués **`OUVERT`** sont des trous de connaissance assumés : ils bloquent la mise en
> production de ce qu'ils décrivent, jamais l'intégration du reste. On ne les remplit pas par déduction (§4.2 du brief).

## Journal de révision — l'historique est conservé, jamais réécrit en silence

| Version | Date | Ce qui change | Déclencheur |
|---|---|---|---|
| 1.0 | 11 août 2026 | Première édition. Légende officielle **inconnue** : 5 niveaux gradués **substituts**, marqués `À CONFIRMER`, accompagnés de 8 questions bloquantes. | Bootstrap |
| **2.0** | 11 août 2026 | **La légende officielle est établie.** Les 5 niveaux substituts sont **supprimés** et remplacés par les **2 états d'accès réels** + la **dimension ZAPEF** + 3 états hors niveau. Sections refaites : §2.1, §4.1, §4.2, §7.1, §8.1, §8.2, §8.5 (nouveau), §9.1, §10 (preuve d'accessibilité, refaite intégralement), §11.2, §12, §13, §14 (passe 2 bis), §15 (D-11 à D-19), §16. Les 8 questions du §4.1 v1.0 sont **répondues sauf deux**, conservées et re-marquées `OUVERT`. | `docs/decisions/source-prefecture.md` §4 (chaîne #1) et `docs/contracts/issue-3.md` révision 2 |
| **2.1** | 12 août 2026 | **Révision d'artefacts — par ajout et correction ciblée, jamais par réécriture.** Les polices sont vendorisées (`latin` seul, D-20), avec `tokens.css`, `fonts.css` (D-21), les deux licences OFL et `PROVENANCE.md`. **§5** : les deux `OUVERT` typographiques sont **clos** (variable d'Atkinson confirmée sous OFL 1.1, repli `Public Sans` retiré ; capitales accentuées vérifiées), sous-ensemble corrigé en `latin`, **`size-adjust` retiré** au profit de `font-display: optional` + preload (D-22), repli `Arial Narrow` documenté comme absent d'Android/ChromeOS/Linux. **§8.2** : la promesse `tabular-nums` est corrigée — `tnum` absent de la police de titrage, largeur du chiffre désormais **réservée**. **§10.5** : six corrections mesurées, **aucun verdict ne bascule**. Ajouts : **§4.1.d règle 8** (ordre des couches), **§10.7** (paires non mesurées), **§10.8** (`forced-colors` reporté aux chaînes d'intégration), **§14.3** (passe 2 ter, motifs de mise en page et d'interaction), **dix lignes au §16**, **D-20 à D-25**. **Le §12 n'est pas touché — aucun jeton renommé, aucune valeur de couleur modifiée, aucune section réécrite.** | `docs/contracts/issue-4.md` (chaîne #4), arbitrages A-1 à A-6 |
| **2.2** | 12 août 2026 | **Cible de réception consignée, compte corrigé, parti typographique levé — par correction ciblée, jamais par réécriture.** (a) Le **destinataire du rendu** est désormais écrit : un **décideur communal**, l'élu ou le directeur général des services qui évalue une offre (§7 du brief, `CLAUDE.md` n°4). (b) **Le compte de massifs passe de 27 à 25** — §1, §3.3, §7.1, §7.2, §8.2, §11.1, §14.3 — **y compris dans les sections d'archive §14.2 et §15 D-19**. Le référentiel réglementaire porte **25** massifs ; les **27** identifiants sont ceux du **flux préfectoral**, et les deux affirmations ne doivent jamais être confondues. Les archives sont corrigées parce qu'**une correction de compte n'est pas une réécriture de décision** : D-19 a décidé « une frise, une marque par massif », et cette décision reste vraie — seul le cardinal était faux. La correction est **déclarée ici**, non faite en silence. Au passage, deux citations apocryphes du §6 du brief (§7.2, §14.3 entrée 4) sont **recoupées sur ses mots réels**, le compte sortant des guillemets. (c) Le **renoncement aux capitales condensées sur `h1`/`h2`** est consigné en **D-26** ; **son application normative — §5.1, §7.3, §14.3 entrée 5 (b), §16 — appartient à l'issue #23**, et jusque-là ces sections restent en vigueur. (d) Quatre « 17 h » passent en **espace insécable** (§7.1, §11.1 règles 1 et 6, §11.3), le document se conformant enfin à sa propre règle 6 ; **aucun fichier de gabarit n'est touché — le code portait déjà l'insécable.** **Le §12 n'est pas touché — aucun jeton renommé, aucune valeur modifiée, aucune section réécrite.** | `docs/contracts/issue-21.md` (chaîne #21), arbitrages A-1 à A-6 |
| **2.3** | 12 août 2026 | **Application normative du recadrage typographique, règle de portée écrite, retrait de la frise — par ajout et correction ciblée, jamais par réécriture.** (a) **D-26 est appliqué** : « capitales » est retiré des lignes `--fs-600` et `--fs-700` du **§5.1**, la « Règle de hiérarchie » est réécrite (capitales **réservées aux étiquettes `--fs-250`** ; `h1`, `h2` et `h3` en **casse normale**), le **§7.3** passe le `h2` éditorial en **famille de texte, casse normale, sans repère**, le **§8.4** ligne 1 perd « capitales » — le nom du massif **reste** en famille d'affichage et **garde son repère**, c'est un titre de statut —, le **§14.3 entrée 5 (b)** est réduit aux **seules** étiquettes `--fs-250`, le **§16** reçoit l'interdit « `h1` ou `h2` rendu en capitales » sans lequel la borne dure de D-26 n'aurait aucun mécanisme de revue, et le **croquis du §7.1** repasse son `h1` en casse normale, annotation « caps » retirée, largeur de ligne préservée au caractère près. **La contradiction transitoire que la v2.2 avait déclarée deux fois est close.** Les bornes qui **survivent** à D-26 sont intactes, mot pour mot : §5.1 ligne `--fs-250`, §14.3 entrée 5 (a), §16 « libellé officiel saisi en capitales dans le HTML » ; l'entrée 5 (c) n'est **pas** réécrite, une note `[v2.3]` constate seulement que son **exemple** est caduc et que sa **règle** vise désormais `Niveau d'Accès` et les quatre libellés officiels. Le §2, ligne « Panneaux DFCI », reçoit un **renvoi** — la table d'ancrage se consulte sans le §15. **Motif de D-26, non déformé : le déplacement de la cible de réception, jamais l'accessibilité.** (b) **La règle de portée typographique** est reproduite au **§5.1** telle que gelée par `docs/contracts/issue-23.md`, avec ses **deux bornes** (étiquettes `--fs-250` en famille d'affichage partout, y compris dans le portail ; défaut du sélecteur nu `h1, h2, h3` en famille d'affichage, règle **normative et non portée par la cascade**), et répercutée aux **§7.2** et **§7.3**. Le **§3.2** est **formellement amendé** en son emplacement n° 2 : le repère ne précède que les `h2` **en portée** de la famille d'affichage. **La liste reste fermée et compte toujours sept emplacements** — l'amendement **restreint** un emplacement, il n'en ajoute ni n'en retire aucun. (c) **Deux plafonds de consommation** entrent au §5.1 : `--fs-700` (`h1`) → **`3rem`**, `--fs-800` (chiffre du jour) → **`5.75rem`**. Ce sont les **milieux exacts** des `clamp` correspondants — **dérivés, pas choisis** —, posés **en consommation** (les jetons du §12 ne bougent pas) et **en `rem`, jamais en `px`** : un plafond en `rem` recule quand l'utilisateur grossit son texte, un plafond en `px` plafonnerait la réponse au zoom (WCAG 1.4.4). Le terme médian `rem + vw` reste **intact** (§14.3 entrée 2 honorée). `--fs-600` n'est pas bridé. (d) **§6.3** : le `--bord-fort` devient **l'unique 4 px du chrome nominal** et va à la **tête de bande carte**, l'entrée du héros ; le bas de la carte et le bandeau de non-officialité passent en `--bord-moyen` ; la règle de quantité est reformulée en « une occurrence dans le chrome nominal ; le bandeau d'alerte, état exceptionnel, porte le sien », **parce qu'une règle qui ment un jour sur trois n'est pas une règle**. Le **§6.1** reçoit le **rythme vertical asymétrique** (`padding-block` **start > end**, et pourquoi c'est ce sens-là), le croquis **§7.1** est mis en accord au caractère près. (e) **D-27 — la frise des 25 marques est retirée**, **abandonnée et non différée** ; ses prescriptions vivantes disparaissent des §1, §3.3, §4.1.d, §6.1, §7.1, §8.2, §9.3, §9.4, §10.6, §13 et §16. **Les archives §14.2 et §15 D-19 ne sont ni réécrites ni annotées**, et **les §10.1 / §10.2 conservent leurs mesures** — seul le mot disparaît, la mesure couvrant désormais la barre d'action du portail. (f) **§12.1** enregistre une rubrique nouvelle, les **jetons déclarés et consommés par personne** — `--ombre-decalee`, `--ombre-decalee-sombre`, `--frise-l`, `--frise-h` —, sous la règle générale de projet **« on ne supprime pas un jeton : on cesse de le consommer »** (sha256 de `tokens.css` épinglé, 111 propriétés, invariant du contrat #4). En conséquence, le **§8.5** cesse de prescrire au présent une ombre que personne ne pose ; le **§6.4** garde le jeton et sa description, le **§9.2** est signalé sans être modifié. (g) **§17, nouveau**, enregistre **neuf divergences** entre ce document et un code **déjà commité**, dont **six héritées du contrat #22** que la chaîne #21 a laissées orphelines en se fermant sans posséder ce fichier. Ce ne sont pas des défauts : c'est ce qui empêche `review-cms` de les compter deux fois. La **neuvième** est propre au §8.2 : le mot qui remplace le chiffre du jour en cas d'indisponibilité **n'est pas rendu par le thème**, le `h1` portant à sa place la chaîne du §11.3 (chaîne #5, arbitrage A-5) — la prescription est **assouplie comme celle du §8.5**, elle décrit ce qui est autorisé, pas ce qui est livré. Et ce mot **s'écrit désormais en casse normale, « Indisponible »** : composé au corps du `h1` et à la place du chiffre, il est le seul endroit du document où des capitales subsistaient hors des étiquettes `--fs-250`. Le compte de propriétés de `tokens.css` est **vérifié au shell** et écrit sans réserve : **111 dans `:root`, 120 dans le fichier entier**. (h) **Correction factuelle** au §5.1 : à 360 px, `--fs-800` vaut **59 px**, `--fs-700` **37,12 px** et `--fs-600` **28,88 px** ; les valeurs de la v2.0 étaient les **planchers** des `clamp`, atteints **sous ≈ 320 px** — 320 px pour `--fs-800`, 325 px pour `--fs-700`, 311 px pour `--fs-600`, conformément au §5.1. Une correction de mesure n'est pas une réécriture de décision, et le plancher de 28 px sous lequel aucun titre ne descend reste exact. (i) **§14.4** ouvre une passe d'autocritique dont la nature diffère des trois précédentes : elle porte sur un **choix déjà fait et défendu**, et le motif de son renversement est le **déplacement de la cible de réception**, jamais un gain d'accessibilité. (j) **§7.3** reçoit la **convention de pied du web public** écrite comme une **convention de menu administrable** — trois entrées affectées à l'emplacement `pied` le jour où les pages existent, **aucun lien codé en dur**, **aucun taux ni qualificatif de conformité RGAA** (aucun audit n'a été mené, et ces qualificatifs sont eux-mêmes des résultats d'audit) ; la phrase « zéro cookie » du croquis §7.1 est consignée **`OUVERT`**, faute de chaîne normative au §11.3, dont la liste est fermée. Le **§16** reçoit les lignes de revue correspondantes, plus une sur la **quantité de `--bord-fort`** et une sur le **repère posé devant un `h2` hors portée**. **Le §12 n'est pas touché — aucun jeton renommé, aucune valeur modifiée, aucune section réécrite.** | `docs/contracts/issue-23.md` (chaîne #23), arbitrages A-1 à A-13 |

| **2.4** | 14 août 2026 | **Le traitement « sélectionné » de la carte est refait, et les épaisseurs de trait de la carte deviennent fonction du palier de zoom — par correction ciblée, jamais par réécriture.** Déclencheur : un **défaut reproduit à l'écran**, dans Chrome sur la stack Docker locale, aux zooms 9 et 10. Sur le massif de **Regagnas** — boîte englobante **94 × 55 px au zoom 9**, un enchevêtrement de languettes de quelques pixels de large —, la règle « liseré 4 px + repère » du §9.2 remplissait toute la boîte englobante de calcaire et l'encadrait de charbon : le massif sélectionné ne se lisait plus comme un massif, mais comme **un rectangle blanc posé sur la carte**. (a) **§9.2 est refait** : la sélection devient le **cerne**, un anneau posé **entièrement hors du polygone**, dans un pane placé **sous** celui des massifs — l'aplat de statut et son motif ne sont **jamais** recouverts. (b) **§9.2.a, nouveau** : trois **paliers de zoom** — `département` (z ≤ 9), `massif` (z 10), `abords` (z ≥ 11) — portés par une **classe sur la racine de la carte**, avec des valeurs chiffrées pour le liseré, le survol et le cerne. Motif : **le liseré est centré sur le tracé, donc il consomme la moitié de son épaisseur dans l'aplat** ; une épaisseur constante ne peut pas servir un département vu en entier et un massif vu de près. (c) **D-28 — le repère est retiré de la carte.** La **liste fermée du §3.2 passe de sept à six emplacements** ; l'emplacement 5 disparaît. (d) **§10.2 est amendé et complété** : le plancher « jamais sous 2 px » devient **« jamais sous 1,5 px, et 1,5 px au seul palier département »**, sur une **mesure** — un trait de 1,5 px garantit 75 % de couverture au pire alignement sous-pixel, soit **3,18:1 sur le rouge officiel** ; à 1,25 px il tombe à **2,66:1** et à 1 px à **2,18:1**. Le plancher exact est **1,42 px** : il est **dérivé, pas choisi**. (e) **Le §12 est ouvert** — pour la première fois depuis la v2.0 — et reçoit **cinq jetons** (`--carte-lisere`, `--carte-survol`, `--carte-cerne`, `--carte-cerne-clair`, `--bord-selection`) plus une **exception documentée n° 3** (redéfinition par classe de palier). **Conséquence déclarée, non subie** : le sha256 épinglé de `tokens.css` et l'invariant « 111 propriétés dans `:root` » du contrat #4 **tombent** ; ils sont remplacés par **116 dans `:root` / 133 dans le fichier**. Aucun jeton n'est **supprimé** ni renommé, aucune valeur de couleur n'est touchée. (f) **§14.5** ouvre la passe 2 quinquies, **§15** enregistre **D-28** et **D-29**, **§16** reçoit quatre lignes de revue et en amende une, **§17.1** liste ce que la révision **ouvre en aval** — dont la clause **A-9 du contrat gelé `docs/contracts/issue-7.md`**, qui reprend l'ancienne règle et **doit être amendée par sa chaîne**, jamais par ce document. | Défaut constaté à l'écran (Chrome, Docker local, z9/z10) ; direction du propriétaire |

| **2.5** | 15 août 2026 | **Révision d'enregistrement — aucun choix visuel n'est repensé. Le §12 et toute déclaration de jeton sont GELÉS et n'ont pas été ouverts.** (a) **§11.3 — sa portée est écrite** : la liste fermée borne le **rendu public**, **pas le portail**, dont la micro-rédaction relève du §7 du brief et du §11.1 (**D-30**, arbitrage du propriétaire sur la question Q-2 du contrat #14). Quatre bornes restent opposables — §11.1/§11.2 s'appliquent au portail, les libellés officiels y restent verbatim, les chaînes de portail restent regroupées en un fichier, et toute chaîne qui **paraît en public** retombe sous la liste. (b) **Contradiction interne levée — la flèche** : le §7.2 imposait `→` en caractère, le §5, **D-25** et le §16 l'interdisaient et en faisaient un **défaut bloquant**. **D-25 l'emporte** — U+2192 est hors du sous-ensemble `latin` et absent des deux polices, il afficherait un rectangle vide ; le §7.2 est mis en cohérence (SVG en ligne, `aria-hidden`, texte « remplacé par » en `screen-reader-text`). La contradiction avait été rencontrée **en production** : le contrat #15 avait gelé la version §7.2, `dev-ux-cms` a refusé de l'appliquer, le contrat a dû être re-gelé (`CORRECTIF-1`). **Aucune décision nouvelle au §15** : c'est l'application de D-25. (c) **§17 — quinze divergences enregistrées, la table passe de neuf à vingt-quatre** : #14 `ea74b4d` (**D-1 à D-9**, **D-5 bis**, plus **A-19**), #15 `56ea7bd` (**`ARBITRAGE-CSS`**, deux copies de la géométrie de pastille), #50 `609eaef` (zoom fractionnaire lu par `floor`, cerne recouvert par un massif voisin contigu) et le **survol du bouton primaire**. La numérotation `D-n` propre à chaque contrat est **conservée en clair**. (d) **§17.1 mis en cohérence** : ses six lignes sont **closes** par la chaîne #50 et **son périmètre d'amendement était sous-dimensionné** — il n'amendait qu'A-9 et A-16 du contrat #7, alors que **A-19, la table de classes du §8.2, les exigences 2, 5, 6, 15 du §8.4 et l'interdit 7** restataient la même règle obsolète. La liste réelle est écrite, avec une **leçon de méthode** opposable. Les lignes sont **marquées closes, pas supprimées** : les supprimer effacerait la leçon. (e) **D-31 — l'emplacement 4 du §3.2 est retiré**, la liste fermée passe de six à **cinq** (1, 2, 3, 6, 7) : il ne pouvait se déclencher **nulle part par construction**, le `h2` du nom du massif gagnant **toujours** l'arbitrage du §3.3. **Solde la dette ouverte par A-15 du contrat #7**, que la v2.4 avait bornée par une note constatant la vacance. **Rien ne change à l'écran.** Le §16 est amendé en conséquence. (f) **§18, nouveau — « à traiter à la prochaine révision »** : cinq manques **du document**, chiffrés et argumentés — le repère sur l'option sélectionnée du portail (acte formel sur liste fermée), le survol qui efface l'étiquette du bouton primaire (**1,15:1** prescrit contre **12,66:1** livré), l'**absence d'échelle typographique de portail** (deux chaînes ont improvisé le **même** contournement sans se voir), le **focus SVG en rectangle englobant** (V-50.1, 94 × 55 px sur Regagnas à z9), et la **dette de duplication du chrome de portail**. **Aucune n'est corrigée ici** : deux exigeraient des jetons, et le §12 est gelé — une recette tournait pendant l'écriture de cette révision. | Clôture du lot Épic 5 ; direction du propriétaire ; escalades des contrats #14 (§14 point 2, Q-2, A-19), #15 (`ARBITRAGE-CSS`) et #50 (A-50.5, V-50.1) |

**Ce que la v1.0 avait raison de faire, et qui est conservé sans changement** : le pari du fond de carte
monochrome (§1), la signature « le repère » (§3), les deux familles typographiques et le budget de 2
fichiers (§5), l'échelle d'espacement et le plafond de rayon à 2 px (§6), le mouvement minimal (§9.4 et
§9.5), la voix de micro-rédaction (§11). **La révision porte sur la sémantique des statuts, pas sur le
langage visuel.** C'est la preuve que le système était correctement paramétré : le passage de 5 crans
inventés à 2 états réels n'a coûté aucune refonte de forme.

---

## 1. Intention de design — en cinq lignes

1. Le site ressemble à un **panneau de départ de sentier repeint chaque soir** : peinture mate sur support
   minéral, angles vifs, aucune matière, aucune profondeur simulée.
2. La carte est **le héros absolu** : elle est le seul endroit du site où de la couleur saturée apparaît,
   parce que les seules couleurs saturées autorisées sont les **deux** de la légende préfectorale —
   un vert, un rouge, rien entre les deux.
3. Tout le reste — chrome, texte, portail — est **calcaire et bleu mistral** : deux familles de tons froids,
   minérales, qui ne peuvent jamais être confondues avec un état d'accès.
4. La hiérarchie ne vient ni de l'ombre ni du contour arrondi : elle vient de **l'échelle, de la casse et
   d'un unique repère peint**, la signature du site (§3).
5. Ce que ça doit faire ressentir : *une information officielle relayée par quelqu'un de sérieux* — sobriété
   de service public, mais avec la brutalité graphique d'une signalétique de terrain, pas d'un intranet.

**Le pari central, celui qui tient tout** : le fond de carte auto-hébergé est **monochrome calcaire**.
Aucune route bleue, aucun bois vert, aucun bâti rose. Résultat : les massifs colorés sont les seules taches
de couleur de l'écran. C'est à la fois le geste le plus spectaculaire (une capture d'écran illisible chez
les concurrents devient évidente ici) et le plus fonctionnel (le contraste des statuts n'est plus pollué).
Tout le système en découle.

**Ce que la légende binaire change, et c'est un gain** : avec **deux** aplats seulement, la carte devient
lisible **à quatre mètres**. Ce n'est plus une carte à déchiffrer, c'est une réponse. La complexité
libérée par la disparition de 3 crans inventés est réinvestie **dans la lisibilité de loin** — aplats
opaques, liseré épais, hachure grossière, un chiffre géant — et **nulle part ailleurs**. Aucun ornement
n'a été ajouté par cette révision.

> **[v2.3]** Cette phrase citait un cinquième réinvestissement, retiré par **D-27**. Ce qui est perdu est
> nommé là-bas : la lecture de la **forme de la journée** d'un coup d'œil. Elle reste portée par le chiffre
> du jour et, en toutes lettres, par la liste du jour.

---

## 2. Ancrage dans le sujet — d'où viennent les tons

| Anchor | Ce qu'on en tire | Ce qu'on refuse d'en tirer |
|---|---|---|
| **Calcaire** (Calanques, Sainte-Victoire) | Les surfaces : un blanc-gris **froid, légèrement vert**, pas un crème chaud | Le crème beige « papier ancien » (tell IA §7) |
| **Mistral** (le ciel lavé après le vent) | Le chrome : bleu profond, froid, un peu grisé — la seule couleur d'interface | Le bleu « corporate SaaS » saturé et lumineux |
| **Pin d'Alep** | Le vert-gris rabattu du fond de carte végétal, à très faible chroma | Un vert d'interface — il entrerait en collision avec « Accès au massif autorisé » |
| **Garrigue** | Le gris-vert des filets, des textes tertiaires, des courbes | Un olive décoratif |
| **Charbon** (le bois brûlé) | L'encre — **et le liseré qui porte à lui seul la conformité AA des statuts** (§10.2). Un noir tiré vers le vert, jamais un `#000` | Le noir pur + accent acide (tell IA §7) |
| **Balisage peint** (blaze GR/PR) | **La signature** (§3) et l'idée de la marque repeinte par-dessus l'ancienne | Le rouge/blanc littéral du GR : le rouge est celui de la légende |
| **Panneaux DFCI** | La discipline typographique : capitales condensées *(le premier geste est levé — D-26)*, sérigraphie, rayon nul | Le pastiche de panneau (bordure double, coin coupé, « effet plaque ») |
| **Barrière DFCI** | Le vocabulaire de **hachures** obligatoire pour l'encodage non chromatique (§10.3), et le **jalon planté** des ZAPEF (§8.1) | La hachure comme décor |

**Terracotta et ocre sont bannis du système**, alors qu'ils seraient le réflexe « Provence ». Deux raisons :
c'est le tell IA nommé au §7 du brief, et surtout la terre cuite (teinte ≈ 18°) tombe **dans la bande
réservée au rouge officiel** — un aplat décoratif terracotta à côté d'un massif interdit créerait une
ambiguïté de sens. La contrainte fonctionnelle et la contrainte esthétique disent la même chose.

### 2.1 Règle de non-collision chromatique — **re-dérivée des deux teintes réelles**

La v1.0 réservait la bande 15°–160°, dérivée d'une échelle jaune → orange → rouge qui **n'existe pas dans
les Bouches-du-Rhône**. Mesures des deux teintes officielles réelles :

| Teinte officielle | Hex | Teinte (H) | Saturation (S) | Luminosité (L) |
|---|---|---|---|---|
| Accès au massif autorisé | `#22B14C` | **138°** | 68 % | 41 % |
| Accès au massif interdit | `#E63A3C` | **359°** | 77 % | 56 % |

> **Règle re-dérivée.** Trois bandes de teinte sont interdites à la palette du site **au-delà de 12 % de
> saturation** :
> - **95°–175°** — *réservée* : c'est la famille du vert officiel `#22B14C` ;
> - **330°–25°** (par 0°) — *réservée* : c'est la famille du rouge officiel `#E63A3C` ;
> - **26°–94°** — *interdite par implication* : jaune, ambre, or, orange clair.
>
> **La palette du site vit donc dans 176°–329°** (cyans, bleus, violets) **ou sous 12 % de saturation.**

**Pourquoi la troisième bande, qui n'appartient à personne, est quand même interdite.** Elle ne l'est pas
au titre de la légende : elle l'est parce qu'un jaune saturé posé **entre** un vert et un rouge est lu
universellement comme un **cran intermédiaire**. Or le dispositif du 13 n'en comporte aucun : il n'y a
qu'autorisé et interdit. Un ambre décoratif inventerait visuellement un troisième état — c'est-à-dire
exactement l'invention que le §4.2 du brief interdit, mais commise par la couleur au lieu du texte.
La v1.0 interdisait cette bande par confusion avec la légende ; la v2.0 l'interdit pour une raison plus
forte et plus juste.

Conséquences directes, à respecter sans exception :
- pas de bouton vert « valider », pas de message d'erreur rouge, pas d'alerte orange ni ambre ;
- le succès, l'erreur et la péremption sont portés par le **chrome mistral + un libellé explicite + une
  hachure**, jamais par une couleur sémantique (§9.2) ;
- l'indicateur « Météo des forêts » (§4.3 du brief) n'utilise **aucune couleur** : il utilise une échelle de
  carrés en charbon (§8.6). C'est la traduction visuelle de l'exigence « jamais fusionné avec le statut »
  — et elle devient **plus critique** avec deux états qu'avec cinq : une échelle météo colorée à côté
  d'une carte binaire serait immédiatement lue comme la vraie granularité du risque.

**Audit de conformité de la palette à sa propre règle** (mesuré, pas supposé) :

| Token | Hex | H | S | Bande | Verdict |
|---|---|---|---|---|---|
| `--c-calcaire` | `#EDEEEC` | 90° | 5,6 % | implicite | conforme (< 12 %) |
| `--c-calcaire-ombre` | `#DEDFD9` | 70° | 8,6 % | implicite | conforme (< 12 %) |
| `--c-poussiere` | `#C3C5BC` | 73° | 7,2 % | implicite | conforme (< 12 %) |
| `--c-trace` | `#9EA197` | 78° | 5,1 % | implicite | conforme (< 12 %) |
| `--c-garrigue` | `#5F6B5A` | 102° | **8,6 %** | **réservée vert** | conforme (< 12 %) — le seul ton du site dans la bande verte, volontairement maintenu sous le seuil |
| `--c-charbon-doux` | `#4A4E48` | 100° | 4,0 % | réservée vert | conforme (< 12 %) |
| `--c-charbon` | `#1A1C19` | 100° | 5,7 % | réservée vert | conforme (< 12 %) |
| `--c-mistral-nuit` | `#0B2B3C` | 201° | 69 % | libre | conforme |
| `--c-mistral` | `#17567A` | 202° | 68 % | libre | conforme |
| `--c-mistral-clair` | `#8FC3DD` | 200° | 51 % | libre | conforme |
| `--c-carte-vegetation` | `#D6DBD3` | 97° | **10,0 %** | **réservée vert** | conforme (< 12 %) — c'est la seule surface verte de la carte, et elle doit le rester |
| `--c-carte-eau` | `#CBD5D8` | 194° | 14,3 % | libre | conforme (hors bande, seuil non applicable) |

**Conséquence retirée de cet audit** : le token `--c-pin-alep` (`#22392C`, H 146°, **S 25 %**) est
**supprimé de `tokens.css`**. Il violait la règle s'il était peint tel quel. Il reste un **ingrédient de
mélange documenté** (§4.2), dont le seul produit — `--c-carte-vegetation` à 10 % de saturation — est,
lui, un token. On ne laisse pas traîner dans la feuille de style une valeur qu'aucune surface n'a le droit
de porter.

---

## 3. La signature : **le repère**

> **Une phrase** : toute information de statut est précédée d'un *repère* — une barre peinte de 8 px doublée
> d'une trace décalée de 3 px vers la droite et 4 px vers le bas, comme une balise de sentier repeinte
> par-dessus celle de la saison précédente.

C'est le sujet même du site rendu visible : **une marque qu'on repeint tous les soirs**, l'ancienne encore
visible dessous. L'indicateur de fraîcheur n'est plus une mention en petits caractères, c'est la forme
de base du système.

**Inchangé par la révision 2.0.** Le repère ne dépendait d'aucun nombre de niveaux : il prend la couleur de
l'état, quelle que soit la cardinalité de la légende. C'est précisément le test qu'une signature doit passer.

### 3.1 Construction CSS (référence normative)

```css
/* Le repère — élément de signature. Une seule implémentation, réutilisée partout. */
.repere {
  position: relative;
  padding-left: var(--esp-l);          /* 24px : 8 (barre) + 3 (décalage) + 13 (respiration) */
}
.repere::before,                        /* la trace : l'ancienne peinture */
.repere::after {                        /* le repère : la peinture du jour */
  content: "";
  position: absolute;
  left: 0;
  top: 0.14em;                          /* aligné sur la hauteur de capitale, pas sur la boîte */
  width: 8px;
  height: 0.86em;
  min-height: 20px;
}
.repere::before {
  transform: translate(3px, 4px);
  background: var(--c-trace);
}
.repere::after {
  background: var(--repere-couleur, var(--c-mistral-nuit));
}

/* Variante longue : bord gauche d'un panneau ou d'un bandeau */
.repere--bloc::before,
.repere--bloc::after { height: 100%; top: 0; min-height: 0; }

/* Variante inversée sur chrome sombre */
.sur-sombre .repere::before { background: var(--c-mistral); }
.sur-sombre .repere::after  { background: var(--c-calcaire); }
```

> **[v2.5] « Une seule implémentation » connaît aujourd'hui une exception, et elle est enregistrée.**
> `layout.css` est **inchargeable dans `wp-admin`** : l'écran de publication (#14, **D-4**) **reproduit ce
> bloc, scopé**, dans la feuille de l'extension, faute de quoi les emplacements **6 et 7** du §3.2 —
> tous deux des emplacements de portail — ne seraient pas rendus. **Ce n'est pas une seconde façon de
> dessiner le repère** : c'est la même géométrie, les mêmes jetons, recopiés. La règle « une seule
> implémentation » reste entière **en intention** et la dette est nommée : §17 lignes **13** et **21**,
> résorption proposée au **§18, recommandation 5**. **Conséquence opérationnelle immédiate : toute
> modification de ce bloc touche trois fichiers**, pas un.

`--repere-couleur` est la **première** des deux seules custom properties que les composants ont le droit de
redéfinir localement (la seconde est le groupe `--statut-lisere` / `--statut-*-encre` sous `.sur-sombre`,
§12) : elle prend la couleur officielle de l'état quand le repère précède une information de statut.

### 3.2 Où il apparaît — liste fermée

1. Devant le **chiffre du jour** dans l'ardoise (version `--bloc`, pleine hauteur, à gauche du slab).
2. **[v2.3, amendé]** Devant **chaque `h2` en portée de la famille d'affichage** — bande d'information du
   jour, légende, titres de statut (couleur `--c-mistral-nuit`). **Les `h2` du chrome, des pages
   éditoriales et du portail ne portent pas de repère.**
3. Devant **chaque puce de statut** dans la légende et dans la liste du jour (couleur = état officiel).
4. **[v2.5 — RETIRÉ par D-31]** Sur le **bord gauche du panneau massif**. Cet emplacement n'existe plus.
   Le panneau massif porte le repère **sur le `h2` de son nom** (emplacement 2), et là seulement.
5. **[v2.4 — RETIRÉ par D-28]** Sur le **massif sélectionné dans la carte**. Cet emplacement n'existe plus.
   Le massif sélectionné porte le **cerne** (§9.2), qui n'est pas le repère et ne s'en réclame pas.
6. Sur le **bord gauche du bandeau d'alerte** (péremption, source indisponible, hors-saison).
7. Sur le **bord gauche de la barre d'action** du portail (« Publier les statuts »).

**Décision de révision** : les **jalons ZAPEF** (§8.1) n'entrent **pas** dans cette liste. Un décalage de
3–4 px sur un marqueur de 18 px détruirait sa silhouette et diluerait la signature dans un objet trop petit
pour la porter. La discipline l'emporte sur la cohérence de façade.

> **[v2.5] Troisième amendement formel — second retrait, et la dette la plus ancienne de cette liste est
> soldée.** L'**emplacement 4** est retiré. **La liste compte cinq emplacements** : 1, 2, 3, 6, 7.
> **Les numéros 4 et 5 sont barrés, jamais réutilisés, et 6 et 7 ne bougent toujours pas** — tout renvoi
> existant reste juste, y compris ceux des contrats gelés (#14 D-4 renvoie aux « emplacements 6 et 7 »).
>
> **Avant** : « Sur le **bord gauche du panneau massif** (version `--bloc`, couleur = état du massif
> sélectionné). » **Après** : plus rien.
>
> **Motif : cet emplacement ne pouvait plus se déclencher nulle part, et ce n'était pas une circonstance,
> c'était une construction.** Le §8.4 ligne 1 donne au `h2` du nom du massif son repère — la v2.3 l'a
> **réaffirmé** en y maintenant la famille d'affichage et le repère après D-26. Le §3.3 interdit **plus
> d'un repère par bloc visuel** et tranche lui-même : « le plus proche de l'information de statut gagne ».
> Le `h2` est plus proche que le bord du panneau ; il gagne **toujours**, dans le seul panneau massif qui
> existe. Un emplacement qui perd systématiquement son propre arbitrage n'est pas un emplacement vacant,
> c'est **une prescription que personne ne doit appliquer** — et le document a déjà écrit, en D-27, qu'une
> telle ligne est « un piège, pas une archive ».
>
> **Ce qui est soldé.** L'arbitrage **A-15 du contrat `docs/contracts/issue-7.md`** demandait de « retirer
> l'emplacement 4 de la liste fermée, **ou** l'y maintenir en excluant explicitement le panneau de carte ».
> La v2.4 avait choisi une **troisième voie qui n'était offerte ni par A-15 ni par la nature d'une liste
> fermée** : une note parenthétique constatant la vacance. Une note constate ; elle ne borne pas. La
> seconde branche d'A-15 est écartée pour la raison ci-dessus : exclure le panneau de carte reviendrait à
> maintenir un emplacement qui ne s'applique **à rien**. **Rien ne change à l'écran** — le code livré ne
> posait déjà pas ce repère (#7, A-15) —, et c'est bien pourquoi ce retrait est un acte d'écriture.

> **[v2.4] Second amendement formel de la liste fermée — un retrait, et il s'écrit comme tel.**
> La v2.3 avait **restreint** un emplacement en écrivant qu'un amendement « n'en ajoute ni n'en retire
> aucun ». Celui-ci en **retire un**. C'est un acte d'une autre portée, et il ne se glisse pas dans une
> renumérotation : **l'emplacement 5 est barré, jamais réutilisé, et les numéros 6 et 7 ne bougent pas** —
> tout renvoi existant à « l'emplacement 6 » ou « 7 » reste juste.
>
> **Avant** : « Sur le massif sélectionné dans la carte : contour `--c-calcaire` 4 px + contour
> `--c-charbon` 4 px décalé de (3 px, 4 px), rendu par duplication du tracé dans un pane Leaflet dédié. »
> **Après** : plus rien. **La liste compte six emplacements.**
>
> **Motif, mesuré et non supposé** (D-28). Trois raisons, dont la première suffit.
> **(a) La géométrie réelle réfute la règle.** Le repère est *une barre peinte de 8 px doublée d'une trace
> décalée*. Transposé à un polygone, il devient la **duplication du tracé entier**, décalée de (3 px, 4 px)
> et tracée à 4 px — c'est-à-dire un objet dont la lisibilité dépend entièrement de la taille apparente de
> la forme. Sur **Regagnas, boîte englobante 94 × 55 px au zoom 9**, faite de languettes de quelques pixels
> de large, les deux contours de 4 px se recouvrent d'un filament au suivant : le halo calcaire **remplit
> la boîte englobante**, la duplication charbon l'encadre, et le résultat lu à l'écran est **un rectangle
> blanc bordé de noir**. Ni le massif, ni sa couleur, ni son motif, ni le repère.
> **(b) Ce document avait déjà écrit la règle qui l'interdit.** Le §3.3 refuse le repère sur « **tout objet
> répété de moins de 20 px** ». Un massif filamenteux au zoom 9 **est** un assemblage d'objets de moins de
> 20 px ; il tombait sous cette interdiction sans que personne ne l'y range, parce que la géométrie n'était
> pas connue quand l'emplacement 5 a été écrit. **La règle n'a pas changé ; le fait l'a rejointe.**
> **(c) La signature ne perd rien, et c'est vérifiable.** Sélectionner un massif **ouvre le panneau**, dont
> le `h2` — le nom du massif — porte le repère (§8.4, contrat #7 A-15). La signature est donc **présente à
> l'écran au moment exact de la sélection**, sur un titre, à l'échelle pour laquelle elle est dessinée.
> Ce qui est retiré n'est pas la signature : c'est **une seconde implémentation de la signature**, à une
> échelle où elle ne fonctionne pas — exactement ce que le §3.1 n'admet qu'une fois.

> **[v2.3] Amendement formel de la liste fermée — acte consigné comme tel.** Cette liste est **déclarée
> fermée** ; l'amender n'est donc pas une retouche de rédaction, c'est un acte, et il s'écrit.
> **Emplacement n° 2, avant** : « Devant **chaque `h2`** du site (couleur `--c-mistral-nuit`). »
> **Emplacement n° 2, après** : le texte ci-dessus.
>
> **La liste reste fermée et compte toujours sept emplacements.** Cet amendement **restreint** un
> emplacement ; il n'en ajoute ni n'en retire aucun. **Motif** : le repère est la signature de
> l'**information de statut**. Posé devant un `h2` éditorial composé en famille de texte, il ne signale
> plus rien et **dilue la signature** — c'est exactement le raisonnement par lequel cette même section a
> déjà refusé les jalons ZAPEF, et par lequel le §3.3 refuse les `h3`. La frontière qui décide « en portée
> / hors portée » est la **règle de portée typographique** du §5.1, et elle seule : aucune autre lecture
> n'est admise en revue.

### 3.3 Où il ne doit jamais apparaître

- Dans le corps de texte, dans les listes à puces éditoriales, dans les notes de bas de page.
- Sur les `h3`, `h4` (la hiérarchie basse se fait à la taille — **[v2.3]** et à elle seule : depuis D-26,
  la casse ne distingue plus aucun niveau de titre).
- Sur les boutons, les champs de formulaire, les liens.
- Dans le pied de page, sur les logos, en filet décoratif horizontal.
- **Sur les jalons ZAPEF** (§8.1) — trop petits pour porter un décalage de 3–4 px sans perdre leur
  silhouette. **[v2.3]** Cette ligne visait aussi les 25 marques retirées par D-27 ; elle vaut désormais
  pour **tout objet répété de moins de 20 px**, quel qu'il soit, et cette formulation la rend générale au
  lieu de la lier à un composant disparu.
- **[v2.4] Sur la géométrie de la carte — polygones de massif, tracés, contours, cernes.** Aucune
  duplication décalée, aucune ombre décalée, aucun second tracé, à aucun palier de zoom (D-28). La carte
  est la seule surface du site dont **l'échelle des objets n'est pas décidée par le design** : elle est
  décidée par la géographie et par le zoom. Un motif de signature dont la lisibilité dépend d'une taille
  apparente qu'on ne maîtrise pas n'est pas une signature, c'est un pari.
- **Plus d'une fois par bloc visuel** : deux repères adjacents cassent la métaphore (on ne repeint pas deux
  fois la même balise). Si deux candidats coexistent, le plus proche de l'information de statut gagne.
- **Jamais animé.** La peinture ne bouge pas.

### 3.4 Dégradation

- **Sans JS** : intégralement présent — c'est du CSS pur sur du HTML rendu par PHP. Sur la carte remplacée
  par l'image statique, le repère reste sur les titres, la légende et la liste.
- **À 360 px** : inchangé (8 px + 3 px = 11 px de gouttière, `padding-left` réduit à `--esp-m` = 16 px).
- **Sans CSS** : rien à dégrader, c'est décoratif — les pseudo-éléments ne portent aucune information.
- **`forced-colors: active`** : `background: CanvasText` pour `::after`, `background: GrayText` pour `::before`.
- **Impression** : conservé en noir 100 % (`::after`) et gris 45 % (`::before`), c'est ce qui donne à la
  page imprimée sa signature.

---

## 4. Palette

### 4.1 Statuts officiels — **non modifiable, reproduit la légende préfectorale**

> **ÉTABLI le 11 août 2026.** Source : `docs/decisions/source-prefecture.md` §4, qui relève la légende
> officielle du 13 de **trois manières concordantes** (le fichier `fr.json` que la préfecture charge et
> applique, la fonction de traduction propre au département 13, et le nombre d'entrées de légende servies
> dans le HTML). Les **5 niveaux gradués substituts de la v1.0 sont supprimés** : ils ne correspondaient à
> aucun dispositif réel. L'échelle à six crans de vigilance qui existe dans le code partagé de la
> plateforme appartient à **d'autres départements** et ne s'exécute jamais sur la page du 13.
>
> **Rien de ce tableau n'est un choix de design. Rien n'y est modifiable, « harmonisable » ou arrondi.**

#### 4.1.a Les deux états d'accès au massif — surfaces (polygones)

| Clé | Libellé officiel *verbatim* | Couleur officielle | Sévérité | Motif obligatoire | Liseré obligatoire |
|---|---|---|---|---|---|
| `autorise` | `Accès au massif autorisé` | **`#22B14C`** | `10` | `aucun` (aplat nu) | `--c-charbon` 2 px *(carte : §9.2.a)* |
| `interdit` | `Accès au massif interdit` | **`#E63A3C`** | `20` | `hachure_croisee` | `--c-charbon` 2 px *(carte : §9.2.a)* |

Les deux hex sont **relevés au pixel** sur les pastilles de légende publiées par la préfecture
(`couleur_vert.png`, `couleur_rouge.png`). Le rendu de la carte officielle emploie par ailleurs les
couleurs CSS nommées `green` / `red` en `fillOpacity: 0.5` : **nous reproduisons les hex de la légende**,
qui sont la référence publiée, et non l'approximation du rendu.

#### 4.1.b La dimension ZAPEF — points (marqueurs), **indépendante de l'état du massif**

| Clé | Libellé officiel *verbatim* | Couleur | Sévérité | Motif |
|---|---|---|---|---|
| `autorise` | `Accès à la ZAPEF* autorisé` | `#22B14C` | `10` | `aucun` |
| `interdit` | `Accès à la ZAPEF* interdite` | `#E63A3C` | `20` | `barre` |

Note de bas de légende, *verbatim*, apostrophe typographique U+2019 comprise :
`*ZAPEF : Zones d’Accueil du Public en Forêt`

> **Les incohérences de la source sont reproduites telles quelles** : `autorisé` au masculin face à
> `interdite` au féminin ; apostrophe typographique `’` (U+2019) dans la note ZAPEF alors que les autres
> chaînes emploient une apostrophe droite `'` (voir `Niveau d'Accès`). Corriger la préfecture serait
> cesser de reproduire sa légende. Toute « correction orthographique » constatée en revue est un défaut.

**Ce que cela impose au rendu, et c'est structurant :** la carte porte **deux objets de nature différente**
— des **surfaces** (massifs, 2 états) et des **points** (ZAPEF, 2 états) — **qui ne s'accordent pas
toujours**. Un massif peut être `interdit` alors que ses ZAPEF restent `autorisé`. Ce n'est ni une erreur
ni un cas limite : c'est le comportement nominal du dispositif au `level` brut 3. Le design doit rendre
cette divergence **lisible et non contradictoire** (§8.1, jalon planté ≠ pastille de surface).

#### 4.1.c Les deux — en réalité trois — états **hors niveau**

Ce ne sont pas des niveaux, ce sont des **absences d'information**. Ils n'ont ni libellé officiel ni
couleur officielle : ils sont **à nous**, et la §11.3 fixe leurs phrases.

| Clé | Quand | Aplat | Motif | Encre du motif |
|---|---|---|---|---|
| `indisponible` | Aucune donnée pour ce jour **ou** la source a publié `level` 0 (« aucune donnée ») | `--c-calcaire-ombre` | `hachure_descendante` | `--c-charbon-doux` |
| `hors_saison` | Hors du 1er juin – 30 septembre **inclus**, et aucune donnée | `--c-calcaire-ombre` | `aucun` | — |
| `non_encore_publie` | Jour futur demandé, rien de publié (le 404 de la source **est** le signal) | `--c-calcaire-ombre` | `pointille` | `--c-charbon-doux` |

> **`level` 0 n'est jamais « autorisé par défaut ».** La carte officielle peint la ZAPEF en vert dès
> `level >= 0`, donc y compris quand elle n'a aucune donnée. **Nous ne reproduisons pas ce comportement** :
> nous reproduisons la légende, pas les défauts de rendu de la source. À `level` 0, le massif **et** ses
> jalons ZAPEF passent en `indisponible`. C'est l'application directe de la règle de sécurité produit du
> `CLAUDE.md` : ne jamais présenter comme une information ce qui est une absence d'information.

#### 4.1.d Règles inviolables attachées à ce tableau

1. **`fill-opacity: 1`** sur tous les polygones de massif. Aucune transparence : les ratios mesurés au §10
   ne tiennent que sur aplat opaque. C'est aussi ce qui donne à la carte son aspect « formes peintes »,
   et ce qui la rend lisible de loin. La source officielle peint à 50 % ; nous non, et c'est délibéré.
2. **Liseré `--c-charbon` sur tout polygone de massif et tout jalon ZAPEF, sans exception.**
   Ce liseré n'est **pas décoratif** : il est le seul élément qui atteigne 3:1 (WCAG 1.4.11) sur toutes les
   surfaces, y compris **entre un massif vert et un massif rouge voisins, qui ne contrastent qu'à 1,48:1**
   (§10.2). Sans lui, on ne voit pas où s'arrête un massif autorisé et où commence un massif interdit.
   Un polygone sans liseré est un défaut **bloquant**, pas une variante esthétique.
   **[v2.4] Son épaisseur est de 2 px partout, sauf sur la carte, où elle suit le palier de zoom** —
   1,5 px / 2 px / 3 px (§9.2.a), plancher **mesuré** à 1,5 px (§10.2.a). Hors de la carte — pastilles,
   jalons, légende, liste du jour, panneau, portail, impression — **rien ne varie : 2 px**. Ce qui varie,
   c'est le rapport entre l'épaisseur du trait et la taille apparente de la forme, et il n'existe que sur
   une carte.
3. **Aucun texte n'est jamais posé sur un aplat de statut. Nulle part.** Ni sur la carte, ni dans la
   légende, ni dans la liste, ni dans le portail, ni à l'impression. Mesuré : `--c-charbon` sur `#E63A3C`
   plafonne à **4,11:1**, sous le seuil AA de 4,5:1 pour du texte normal ; le blanc pur n'y atteint que
   4,17:1. Les libellés vivent **à côté** de l'aplat, en `--c-charbon` sur `--c-calcaire` (14,74:1).
   Les jetons `--statut-*-encre` sont des **encres de motif**, jamais des encres de texte.
4. **Le motif est obligatoire partout où la couleur apparaît** : carte, légende, liste du jour, panneau,
   écran gestionnaire, impression. Une pastille sans motif est un défaut bloquant. **[v2.3]** L'énumération
   perd un emplacement (D-27) ; la règle, elle, ne connaît **aucune** exception d'emplacement.
5. **Il n'y a que deux états, donc le motif n'est plus une échelle de densité mais une opposition
   binaire** : `autorisé` = aplat nu, `interdit` = hachure croisée. La sévérité (`10` / `20`) est
   **comparable, jamais une identité et jamais un rang** ; elle ne pilote aucune densité graduée. Toute
   trace de « la densité croît avec la sévérité » (règle 5 de la v1.0) est **supprimée** : avec deux
   états, une gradation n'a plus de référent et suggérerait des crans intermédiaires inexistants.
6. **La couleur ne traverse jamais la frontière extension → thème.** L'extension émet des **noms de
   jetons** (`--statut-autorise`, `--statut-interdit`, `--statut-zapef-*`, `--statut-indisponible`…) ;
   `tokens.css` (§12) porte le pigment. Une valeur hexadécimale de statut écrite ailleurs que dans
   `tokens.css` est un défaut bloquant (contrat #3, interdit 6 et 14).
7. **Les jetons de statut sont sémantiques, jamais numérotés.** `--statut-1` … `--statut-5` sont
   **bannis à perpétuité**. Motif : réutiliser un jeton numéroté après un changement de légende
   repeindrait silencieusement des massifs interdits dans la mauvaise couleur — `--statut-2` valait un
   **jaune** en v1.0. Un jeton sémantique manquant ne produit **aucune** couleur : l'échec est bruyant et
   visible à la première intégration. Sur une donnée de sécurité, l'échec bruyant est toujours le bon choix.
8. **[v2.1] Aucune couche d'étiquettes cartographiques n'est jamais rendue au-dessus d'un aplat de
   statut.** Les toponymes du fond de carte vivent **sous** la couche des polygones de statut ; un nom de
   lieu intérieur à un massif est **occulté**, et c'est voulu. **Corollaire, de même force** : tout élément
   de chrome de carte flottant — attribution OSM, contrôles de zoom, bascule EFFIS, sélecteur de date —
   repose sur un **aplat opaque `--c-calcaire`**, jamais sur la toile nue.
   *Pourquoi c'est une règle et non une préférence* : mesuré, `--c-carte-encre` `#4A4E48` plafonne à
   **2,03:1 sur `#E63A3C`** et tombe à **3,02:1 sur `#22B14C`** (§10.7). **Aucune encre ne passe sur le
   rouge officiel** — c'est le même mur que la règle 3, et sur la carte il n'existe qu'un seul mécanisme
   pour l'appliquer : **l'ordre des couches**. Ni halo, ni contour, ni assombrissement de l'aplat (interdit
   par le §9.2) ne rattrapent 2,03:1. Le coût est nul : en raster, les étiquettes sont cuites dans la
   tuile, donc déjà sous le calque SVG ; en vectoriel, c'est un ordre de couches explicite. (D-24)

#### 4.1.e Ce qui reste `OUVERT` — à ne jamais combler par déduction

Les 8 questions bloquantes de la v1.0 sont désormais répondues, **sauf deux**. État à jour :

| # v1.0 | Question | État |
|---|---|---|
| 1 | Combien de niveaux ? | **RÉPONDU — deux**, plus la dimension ZAPEF |
| 2 | Libellés officiels mot pour mot | **RÉPONDU** — §4.1.a et §4.1.b, verbatim |
| 3 | Codes couleur exacts | **RÉPONDU** — `#22B14C` et `#E63A3C`, relevés au pixel |
| 4 | **Consigne officielle par niveau** | **`OUVERT`** — voir ci-dessous |
| 5 | **Distinction piéton / circulation / stationnement / travaux** | **`OUVERT`** — les travaux relèvent d'un dispositif et d'une carte séparés ; circulation et stationnement sont absents de la source |
| 6 | Libellé « demain non publié » | **RÉSOLU AUTREMENT** — le 404 est le signal ; notre phrase est fixée §11.3, celle de la source n'est pas recopiée |
| 7 | Dates du dispositif | **RÉPONDU** — 1er juin au 30 septembre **inclus** |
| 8 | Autorisation de reproduction et mention de source | **`OUVERT` et bloquant avant mise en production** — aucune mention légale, aucune CGU, aucune licence publiée |

> **`OUVERT` — les consignes.** La légende officielle **ne porte aucune consigne** : ni horaire d'accès,
> ni interdiction de travaux, ni mention de circulation ou de stationnement. L'arrêté préfectoral qui les
> contiendrait est un **PDF numérisé sans couche de texte** : il n'a pas pu être lu, et il ne sera pas
> deviné. Le §5.2 du brief promet pourtant une « consigne » dans le panneau massif.
> **Traitement retenu : l'emplacement existe, il se tait proprement quand il est vide, et il accueillera
> une transcription fournie par le propriétaire sans aucune refonte** (§8.4). C'est le seul traitement
> qui honore à la fois le §5.2 et l'interdiction d'inventer du §4.2.

> **`OUVERT` — les niveaux bruts.** Le flux porte un `level` 0–4 (1–2 → autorisé, 3–4 → interdit ; les
> ZAPEF ne ferment qu'à 4). **Aucun libellé officiel ne distingue 1 de 2, ni 3 de 4.** Il est donc
> **interdit d'en rendre un**, sous quelque forme que ce soit : pas de nuance de teinte, pas de densité de
> hachure, pas de mention « niveau élevé », pas d'infobulle. Le `level` brut est persisté par l'extension
> et **n'atteint jamais l'écran**.

> **`OUVERT` — la géométrie des ZAPEF.** La dimension ZAPEF est **établie** (libellés, états, règle de
> fermeture), mais **aucun contrat ne fournit à ce jour la position des points ZAPEF ni leur rattachement
> à un massif**. Tant que cette donnée n'existe pas, les jalons **ne sont pas rendus sur la carte** : la
> dimension ZAPEF vit alors uniquement dans le panneau massif et dans la liste du jour (§8.1, dégradation).
> Le site n'affiche pas un marqueur dont il ne connaît pas l'emplacement.

### 4.2 Palette du site

Encres et surfaces — **ratios mesurés (WCAG 2.x, sRGB)**. Preuve complète au §10.

| Token | Nom | Valeur | Usage | Contraste |
|---|---|---|---|---|
| `--c-calcaire` | Calcaire | `#EDEEEC` | Surface principale de page | réf. |
| `--c-calcaire-ombre` | Calcaire à l'ombre | `#DEDFD9` | Surfaces secondaires, lignes alternées, terre du fond de carte, **aplat des trois états hors niveau** | 1,15:1 vs calcaire (surfaces uniquement) |
| `--c-poussiere` | Poussière | `#C3C5BC` | Filets 1 px non informatifs, séparateurs | 1,50:1 vs calcaire — **jamais de texte, jamais de bordure porteuse de sens** |
| `--c-trace` | Trace | `#9EA197` | La peinture ancienne : `::before` du repère, ombres décalées | 2,26:1 vs calcaire — **décoratif exclusivement**. Retiré des motifs de statut en v2.0 (1,96:1 sur calcaire-ombre, insuffisant — §10.3) |
| `--c-garrigue` | Garrigue | `#5F6B5A` | Texte tertiaire, bordures de champs, filets de carte | **4,83:1** vs calcaire conforme · 4,19:1 vs calcaire-ombre ÉCHEC (grand texte ≥ 24 px uniquement) |
| `--c-charbon-doux` | Charbon doux | `#4A4E48` | Texte secondaire, méta, **encre des motifs hors niveau** | **7,29:1** vs calcaire conforme · **6,33:1** vs calcaire-ombre conforme |
| `--c-charbon` | Charbon | `#1A1C19` | Texte principal, **liseré des statuts**, encre des motifs de statut | **14,74:1** vs calcaire conforme · **12,79:1** vs calcaire-ombre conforme |
| `--c-mistral-nuit` | Mistral de nuit | `#0B2B3C` | Chrome : ardoise, en-tête, pied, barre d'action, bandeau d'alerte | **12,66:1** vs calcaire conforme · calcaire dessus **12,66:1** conforme |
| `--c-mistral` | Mistral | `#17567A` | Liens, boutons primaires, focus | **6,81:1** vs calcaire conforme · 5,91:1 vs calcaire-ombre conforme |
| `--c-mistral-clair` | Mistral clair | `#8FC3DD` | Texte et liens **sur chrome sombre**, halo de focus | **7,73:1** sur mistral-nuit conforme · 1,64:1 sur calcaire ÉCHEC — **interdit sur fond clair** |

**Ingrédient de mélange, absent de `tokens.css`** : *Pin d'Alep* `#22392C` (H 146°, S 25 %). Il sert à
composer `--c-carte-vegetation` (10 % sur `--c-calcaire-ombre`). Peint tel quel, il violerait la règle
§2.1 — d'où son retrait de la feuille de jetons en v2.0. L'ancre reste, le token part.

Tons de carte dérivés (fond OSM auto-hébergé, restylé monochrome) :

| Token | Valeur | Rôle sur le fond de carte |
|---|---|---|
| `--c-carte-fond` | `#E6E7E1` | Mer, hors-département, fond général |
| `--c-carte-terre` | `#DEDFD9` | Terre |
| `--c-carte-vegetation` | `#D6DBD3` | Bois et végétation (calcaire + 10 % pin d'Alep) |
| `--c-carte-eau` | `#CBD5D8` | Eau — **désaturée**, ne doit jamais lire comme « bleu carte » |
| `--c-carte-trait` | `#B4B7AC` | Routes, limites administratives — **jamais porteur d'une limite qui compte** (§10.7 : 1,64:1 sur le fond, 1,52:1 sur la terre) |
| `--c-carte-encre` | `#4A4E48` | Toponymes — **rendus exclusivement sous les aplats de statut** (§4.1.d règle 8) |

> Note d'implémentation : si le fond retenu est **raster**, le rendu monochrome est produit **à la génération
> des tuiles côté serveur**, pas par un `filter: grayscale()` navigateur (le filtre casse les ratios mesurés
> et coûte en peinture sur mobile). Si le fond est **vectoriel (PMTiles)**, la feuille de style reprend
> littéralement les six tokens ci-dessus.

---

## 5. Typographie

**Inchangée par la révision 2.0.** Deux familles, deux fichiers, **budget §10 du brief tenu exactement**.

| Rôle | Famille | Licence | Fichier | Poids | Sous-ensemble |
|---|---|---|---|---|---|
| Titrage — *de caractère* | **Big Shoulders Display** | SIL Open Font License 1.1 | `big-shoulders-display-var.woff2` (variable, axe `wght`) | 500 → 800 | **`latin` seul** (D-20) — accents FR **capitales comprises**, vérifié sur la cmap réelle |
| Texte — *de labeur* | **Atkinson Hyperlegible Next** | SIL Open Font License 1.1 | `atkinson-hyperlegible-next-var.woff2` (variable, axe `wght`) | 400 → 700 | **`latin` seul** (D-20) |

**Total : 2 fichiers `woff2`, auto-hébergés.** Aucun service tiers, aucun CDN. Les deux `@font-face`
vivent dans `assets/fonts/fonts.css` et **nulle part ailleurs** (D-21), avec
**`font-display: optional`** et **preload obligatoire des deux fichiers**, **sans aucun descripteur de
métriques** — ni `size-adjust`, ni `ascent-override`, ni `descent-override` (D-22).

> **[v2.1] Correction — le `size-adjust` promis par la v2.0 est retiré.** La v2.0 annonçait
> « `font-display: swap` et `size-adjust` calibré pour supprimer le saut de mise en page ». Mesuré,
> cette promesse n'est pas tenable, pour deux raisons distinctes et suffisantes chacune. **(a)** Posé sur
> la face *web*, `size-adjust` ne supprime aucun saut : il met la police à l'échelle en permanence. La
> technique qui supprime réellement le saut exige une **seconde face `@font-face` de repli, aliasée et
> dotée de descripteurs**, par famille — soit deux déclarations de plus, que le **budget de 2 fichiers
> interdit**. **(b)** Aucune valeur unique n'est de toute façon dérivable : `system-ui` désigne quatre
> polices différentes selon l'OS, et `Arial Narrow` est absent d'Android et de la plupart des Linux.
> `optional` est la seule option qui garantisse **structurellement** le « pas de sauts perceptibles » du
> §10 du brief. Coût assumé, écrit sans détour : sur connexion lente, le **tout premier** affichage se
> fait en police système ; la police s'applique dès la vue suivante.

**Pourquoi celles-là :**
- *Big Shoulders Display* est une typographie de **signalétique civique** (dessinée pour un système
  d'orientation urbaine) : ultra-condensée, industrielle, faite pour être lue de loin sur un panneau.
  Elle est le prolongement typographique du panneau DFCI, et elle est nettement moins vue que les
  condensées par défaut (Oswald, Anton, Roboto Condensed).
- *Atkinson Hyperlegible Next* est dessinée par le Braille Institute **pour la basse vision** : formes de
  caractères volontairement différenciées (`I` / `l` / `1`, `O` / `0`, `rn` / `m`). Sur un projet où
  l'accessibilité est bloquante et où l'on répond à un appel d'offres, c'est un argument défendable en
  mémoire technique, pas un choix esthétique. Le contraste de largeur avec la condensée est violent et
  mémorable — c'est exactement l'effet recherché.

**Vérifications à faire au build (`dev-front-cms`) :**
- **[v2.1] CLOS, affirmativement.** Le **fichier variable** d'Atkinson Hyperlegible Next **existe**, sous
  **OFL 1.1 sans Reserved Font Name**, et il est auto-hébergeable : version 2.001, axe unique
  `wght 200–800`. **Le repli `Public Sans` est retiré** — il n'a plus d'objet. La règle qui l'accompagnait
  reste entière : **ne jamais dépasser 2 fichiers.**
- **[v2.1] CLOS.** Big Shoulders Display 2.002, axe `wght 100–900`, contient bien **É È À Ç Ô Û Î** en
  capitales. Relevé complet ci-dessous.
- Le sous-ensemble de la police de texte contient **`’` (U+2019)** et **`*`** : la note ZAPEF
  officielle en dépend, et un glyphe manquant afficherait un rectangle dans une chaîne reproduite verbatim.
  **Vérifié** (voir ci-dessous).
- Piles de repli système : `--police-titre` → `"Big Shoulders Display", "Arial Narrow", sans-serif` ;
  `--police-texte` → `"Atkinson Hyperlegible Next", system-ui, sans-serif`.
  **[v2.1] `Arial Narrow` est absent d'Android, de ChromeOS et de la plupart des Linux** : sur ces
  plateformes, le repli de titrage est une `sans-serif` **non condensée**, sensiblement plus large.
  La composition de l'ardoise et des `h1` en `--fs-700` / `--fs-800` **doit être contrôlée polices
  désactivées**, à 360 px et à 200 % de zoom, par les chaînes d'intégration. C'est une conséquence
  directe de D-22 : avec `optional`, cette vue est réellement servie à certains visiteurs.

**[v2.1] Provenance et vérification des glyphes — enregistrées.** URL source, version amont, empreinte
**sha256**, date de récupération et relevés de vérification sont consignés dans
`wp-content/themes/massifs/assets/fonts/PROVENANCE.md`, à côté des deux fichiers de licence OFL 1.1
reproduits verbatim. **L'empreinte n'est pas recopiée ici** : deux copies d'un hachage sont deux choses
qui divergeront. Le fichier fait foi.

Résultat de la vérification, mené sur les binaires réellement embarqués : le sous-ensemble `latin` seul
contient **la totalité du bloc U+00C0–U+00FF** (donc tous les accents français, capitales comprises),
**U+00A0**, **U+2019**, **`*`**, les guillemets `« »`, les tirets `–` `—`, les points de suspension `…`,
le degré `°` et les ligatures `œ Œ æ Æ`, ainsi que les chiffres. **`latin-ext` ne contient que
`U+0100` et au-delà** : il est sans emploi pour le français, et le prendre coûterait **4 fichiers contre
un budget dur de 2**, pour zéro glyphe utile (D-20). Attention également : la **valeur par défaut de
l'axe `wght` de Big Shoulders Display est `100`** — un titre dont le poids n'est pas explicitement posé
s'affiche en filet ; `--poids-titre` / `--poids-affiche` ne sont donc jamais facultatifs.

**[v2.1] La flèche `→` (U+2192) de §7.2 est hors du sous-ensemble `latin` et absente des deux polices.**
Elle est donc rendue en **SVG en ligne, jamais en caractère** (D-25) — un caractère afficherait un
rectangle vide. C'est l'application du §16 en vigueur : « les rares symboles sont du SVG en ligne ».

### 5.1 Échelle

Base `1rem = 16px`. Corps à 17 px (l'œil d'Atkinson est large, 17 px donne la mesure juste).
Les niveaux 500 à 800 sont **fluides** (`clamp`) : pas de media query typographique.

| Token | Valeur | Rôle | Famille | Interligne | Approche |
|---|---|---|---|---|---|
| `--fs-100` | `0.8125rem` (13 px) | Attributions, mentions de licence, note ZAPEF | texte | 1,45 | 0 |
| `--fs-200` | `0.9375rem` (15 px) | Méta, fraîcheur, libellés de tableau | texte | 1,5 | 0 |
| `--fs-250` | `0.8125rem` (13 px) | **Étiquette** : capitales, `--ls-etiquette` | titre 700 | 1,2 | 0,08em |
| `--fs-300` | `1.0625rem` (17 px) | **Corps** | texte | 1,6 | 0 |
| `--fs-400` | `1.1875rem` (19 px) | Chapô, **libellé d'état dans le panneau**, consigne | texte | 1,55 | 0 |
| `--fs-500` | `clamp(1.375rem, 1.2rem + 0.9vw, 1.75rem)` | `h3` | titre 600 | 1,15 | 0,01em |
| `--fs-600` | `clamp(1.75rem, 1.4rem + 1.8vw, 2.5rem)` | `h2` | titre 700 | 1,08 | 0,01em |
| `--fs-700` | `clamp(2.25rem, 1.6rem + 3.2vw, 3.75rem)` | `h1` | titre 700 | 1,05 | 0,005em |
| `--fs-800` | `clamp(3.5rem, 2rem + 7.5vw, 8rem)` | **Le chiffre du jour** | titre 800, **largeur réservée** (§8.2 — `tabular-nums` inopérant) | 0,92 | −0,01em |

**Règle de hiérarchie — [v2.3], application normative de D-26** : la famille de titrage n'a **que deux
poids en service (700 et 800)**. La hiérarchie vient de la taille, pas du poids ni de la couleur.
**Les capitales sont réservées aux étiquettes `--fs-250`** ; `h1`, `h2` et `h3` sont composés en **casse
normale**. Interdit : titre en `--c-mistral`, titre en italique, titre souligné, **titre coloré par
l'état**, **`h1` ou `h2` rendu en capitales**.

> **Ce qui change ici, et ce qui ne change pas.** La v2.0 et la v2.1 écrivaient « toujours en capitales
> au-dessus de `--fs-500` ». Cette prescription tombe, **par D-26 et pour son motif : le déplacement de la
> cible de réception vers un décideur communal.** Ce n'est **pas** une décision d'accessibilité — le §14.3
> entrée 5 avait déjà relevé et borné ce risque-là, et rien de neuf n'est apparu de ce côté. La ligne
> `--fs-250` du tableau ci-dessus est **inchangée** : l'étiquette reste en capitales, avec
> `--ls-etiquette`, et c'est elle qui porte les chaînes officielles reproduites verbatim (§11.4).

> **Règle de portée typographique.** La famille d'affichage `--police-titre` (Big Shoulders Display) est
> **confinée à trois zones, et à elles seules** :
>
> 1. **la bande d'information du jour** — l'ardoise : le chiffre du jour, son dénominateur, le `h1` ;
> 2. **la légende de la carte** — son titre et ses étiquettes ;
> 3. **les titres de statut** — le titre de la liste du jour, les en-têtes de sa colonne d'état, le nom
>    du massif en tête du panneau massif, et les libellés d'état officiels.
>
> **Partout ailleurs, la famille de texte `--police-texte` (Atkinson Hyperlegible Next) est seule
> employée** :
>
> - le **chrome** — barre haute, pied de page, liens d'évitement, bandeau de non-officialité ;
> - les **pages éditoriales** — La démarche, Accessibilité, Mentions légales : leurs `h1`, `h2` et `h3`
>   sont en famille de texte, en **casse normale**, et **sans repère** ;
> - le **portail** — en-tête, tableau, boutons, barre d'action, historique.
>
> **Deux bornes qui ne se déduisent pas, et qu'il faut donc lire.**
>
> **(a) Les étiquettes `--fs-250` restent en famille d'affichage partout où elles paraissent, y compris
> dans le portail.** Ce sont des titres de statut au sens de la règle, et ce sont elles qui portent les
> chaînes officielles reproduites verbatim (§11.4). « Le portail en famille de texte » vise son **chrome**,
> jamais ses **étiquettes de statut**. Sans cette borne, la paire segmentée du §7.2 perdrait la famille de
> ses libellés officiels et la règle contredirait le §5.1 sur le rôle même de `--fs-250`.
>
> **(b) Le défaut du sélecteur nu `h1, h2, h3` reste la famille d'affichage.** Cette règle est
> **normative, pas portée par la cascade**. Trois raisons, dans cet ordre : `layout.css` n'a pas le droit
> de cibler les titres de la légende et de la liste (invariant I-1 du contrat #22), qui sont pourtant les
> deux seuls `h2` du site et sont tous deux **en** portée ; ces deux parties émettent un niveau de titre
> **variable** (`niveau_titre ∈ {2..6}`), donc un sélecteur qui parie sur `h2` est fragile par
> construction ; et une mécanique par zone leur ferait perdre leur famille **en silence** le jour où elles
> paraîtraient sur une seconde page. Une page éditoriale future retire la famille d'affichage **dans sa
> propre feuille**. On choisit ainsi l'échec **visible en revue** plutôt que l'échec silencieux, comme le
> §4.1.d règle 7 le fait déjà pour les jetons de statut.

Cette règle est la **frontière opposable** citée par le §3.2 (repère), le §7.2 (portail) et le §7.3 (pages
éditoriales). Elle est écrite littéralement, et non résumée, pour une raison précise : une frontière
paraphrasée est re-litigée à chaque revue, et le résultat se lit « incohérent » là où il devrait se lire
« discipliné ».

**[v2.3] Deux plafonds de consommation — valeurs normatives.** `--fs-700` et `--fs-800` restent
**intouchés** dans `tokens.css` (§12, sha256 épinglé) : la bride est posée **en consommation**, jamais dans
le jeton.

| Jeton | Emploi | Plafond de consommation | D'où vient la valeur |
|---|---|---|---|
| `--fs-700` | `h1` | **`3rem`** | **milieu exact** de `2.25rem`–`3.75rem` |
| `--fs-800` | le chiffre du jour | **`5.75rem`** | **milieu exact** de `3.5rem`–`8rem` |

- **Les deux valeurs sont dérivées, pas choisies.** « Recomposer dans la moitié basse du `clamp` » désigne
  littéralement la **borne médiane** ; il n'y a rien à arbitrer, et donc rien à re-arbitrer en revue.
- **Le plafond est en `rem`, jamais en `px`.** Un plafond en `rem` **recule** quand l'utilisateur grossit
  son texte : il ne peut donc pas plafonner la réponse au zoom (WCAG 1.4.4). Un plafond en `px` la
  plafonnerait — ce serait un défaut bloquant, pas une préférence.
- **Le terme médian `rem + vw` du `clamp` reste intact**, et le `clamp` n'est jamais réécrit localement :
  la défense du §14.3 entrée 2 est honorée, pas contournée.
- **`--fs-600` n'est pas bridé** : il plafonne déjà à `2.5rem` et n'est pas une affiche.
- **Effet mesuré** : la bride est **inerte à 360 px** et n'entre en action qu'à partir de ≈ 700 px (`h1`)
  et ≈ 800 px (chiffre). Ce qu'elle change est un registre, pas une taille de mobile : **le chiffre du jour
  redevient une donnée dans une bande d'information, il cesse d'être une affiche.** C'est l'objet même du
  recadrage.

> **[v2.5] Manque constaté : cette échelle n'a pas de branche « portail ».** Elle est calibrée pour le web
> public ; la règle de portée ci-dessus range le chrome du portail en famille de texte **sans rien dire de
> ses tailles**. Conséquence observée : les chaînes **#14** et **#15** ont écrit **le même**
> `min(var(--fs-700), 3rem)` sur leur `h1` de portail **sans se voir**, en empruntant le plafond du `h1`
> public ; et **rien ne plafonne `--fs-600`**, employé pour les `h2` de portail, qui monte donc à
> **2,5rem (40 px)** sur un écran d'outil. Deux improvisations convergentes signalent une règle manquante.
> **Non corrigé dans cette révision** — proposition chiffrée au **§18, recommandation 3** (option
> recommandée : **zéro jeton**, une table de rôles et un plafond `--fs-600` → **`2.125rem`**, milieu exact
> du `clamp`, par la même dérivation que les deux plafonds ci-dessus).

**Mesure de ligne** : `--mesure: 68ch` sur le corps éditorial, `--mesure-etroite: 46ch` dans le panneau massif.

**Comportement à 360 px — [v2.3], corrigé.** À 360 px (`1vw` = 3,6 px), `--fs-800` vaut **59 px**,
`--fs-700` **37,12 px** et `--fs-600` **28,88 px**. Le chiffre du jour n'affiche que **le nombre** (« 12 »),
la phrase complète passant en `--fs-400` en dessous. Aucun titre ne descend jamais sous **28 px** : la
condensée devient illisible avant de devenir petite. À 200 % de zoom, tous les `clamp` restent bornés par
leur minimum en `rem`, donc le texte grossit bien (pas de piège `vw` pur).

> **Ce qui a été corrigé et pourquoi c'est écrit.** La v2.0 annonçait « 56 px / 36 px / 28 px » : ce sont
> les **planchers** des `clamp`, atteints **sous ≈ 320 px** de largeur — 320 px pour `--fs-800`, 325 px
> pour `--fs-700`, 311 px pour `--fs-600` — et non les valeurs servies à 360 px.
> **Une correction de mesure n'est pas une réécriture de décision** — le plancher de 28 px reste exact, et
> l'affirmation « rien ne change à 360 px » sous l'effet des deux plafonds ci-dessus reste vraie dans les
> deux lectures.

---

## 6. Espacement, rythme, rayons, bordures, élévation

**Inchangé par la révision 2.0.**

### 6.1 Espacement — échelle base 4

| Token | Valeur | Emploi |
|---|---|---|
| `--esp-3xs` | `2px` | Décalage d'état actif, micro-ajustements |
| `--esp-2xs` | `4px` | Écart puce ↔ libellé, gouttière la plus serrée du système |
| `--esp-xs` | `8px` | Padding interne des puces, gouttière de tableau serré |
| `--esp-s` | `12px` | Écart entre éléments d'un même groupe |
| `--esp-m` | `16px` | **Gouttière de page à 360 px**, padding des cellules |
| `--esp-l` | `24px` | Retrait du repère, padding du panneau, gouttière ≥ 600 px |
| `--esp-xl` | `32px` | Écart entre blocs d'une même section |
| `--esp-2xl` | `48px` | Padding vertical d'une section (mobile) |
| `--esp-3xl` | `64px` | Padding vertical d'une section (desktop) |
| `--esp-4xl` | `96px` | Respiration de l'ardoise, écart avant le pied de page |

**Règle de rythme** : le rythme vertical entre sections est `clamp(48px, 6vw, 96px)`, exposé en
`--esp-section`. Aucune valeur d'espacement hors échelle. Aucune marge négative sauf pour le
plein-cadre (`--sortie-cadre`).

**[v2.3] Le rythme des bandes est asymétrique — et le sens de l'asymétrie se démontre.** Un filet est un
`border-block-start` : l'espace **au-dessus** de lui est le `padding-block-end` de la bande **précédente**,
l'espace **en dessous** est son propre `padding-block-start`. « Resserré au-dessus du filet, généreux en
dessous » s'écrit donc `padding-block: <généreux> <resserré>`, c'est-à-dire **start > end** — et **non
l'inverse**. C'est l'erreur naturelle : elle est nommée ici pour être évitée, pas pour être commentée.

| Bande | `padding-block` (start / end) | Pourquoi |
|---|---|---|
| Bande de section, cas général | `--esp-section` / `--esp-2xl` | Le contenu respire sous le filet qui l'ouvre, et se referme court sur le filet suivant |
| Bande d'ardoise, ≥ 900 px | `--esp-4xl` / `--esp-3xl` | Même geste, amplifié : c'est la bande d'entrée |
| Bandes fines (non-officialité) | **symétrique** (`--esp-m`) | Sur une bande d'une ligne, l'asymétrie n'est plus un rythme, c'est du bruit |

Toutes ces valeurs restent dans l'échelle **fermée** ci-dessus. **À 360 px, `--esp-section` vaut 48 px** :
le rythme y redevient symétrique 48/48, et **c'est voulu** — l'asymétrie est un geste de composition large,
pas un geste de mobile.

**La règle ne s'applique pas au filet 2 px intra-bande du bloc de légende.** Ce filet ne sépare pas deux
bandes : il vit **à l'intérieur** d'une bande, porté par le composant, et l'espace sous lui appartient à
`composants.css`, gelé. Compenser ce filet depuis la feuille de mise en page couplerait les deux, ce qui
est un arbitrage que ce document n'a pas rendu.

### 6.2 Rayons — la contrainte la plus visible

| Token | Valeur | Emploi |
|---|---|---|
| `--r-0` | `0` | **Par défaut, partout** : sections, carte, panneaux, tableaux, boutons, pastilles, jalons |
| `--r-1` | `2px` | Champs de formulaire, boutons — *uniquement pour éviter l'aliasing des coins* |

> **Aucun rayon supérieur à 2 px n'existe dans ce système.** Pas de carte arrondie, pas de pilule, pas
> d'avatar rond, **pas de pastille de statut ronde**. C'est la peinture sur pierre, pas le composant d'un
> kit UI. Un `border-radius: 8px` repéré en revue est un défaut.
>
> **Précision v2.0** : les pastilles de statut passent à `--r-0` (elles étaient à `--r-1` en v1.0). Motif
> mesuré : sur une pastille de 16 px de haut, un rayon de 2 px mange visiblement le liseré dans les
> angles, et c'est **le liseré qui porte la conformité**. On ne rogne pas un élément porteur d'accessibilité
> pour un adoucissement décoratif.

### 6.3 Bordures

| Token | Valeur | Emploi |
|---|---|---|
| `--bord-fin` | `1px solid var(--c-poussiere)` | Séparateurs de lignes de tableau, filets non informatifs |
| `--bord-champ` | `2px solid var(--c-garrigue)` | Champs de formulaire au repos (4,83:1 → limite ≥ 3:1 conforme) |
| `--bord-moyen` | `2px solid var(--c-charbon)` | Boutons secondaires, **liseré des polygones, pastilles et jalons**, **[v2.3] filet de tête de bande** (bloc de légende), **bas de la bande carte**, **bandeau de non-officialité** |
| `--bord-fort` | `4px solid var(--c-charbon)` | **[v2.3] Tête de la bande carte** — l'entrée du héros —, panneau massif, encart de consigne, bas du bandeau d'alerte |
| `--bord-selection` | `4px` | **[v2.4] Une épaisseur seule, jamais une abréviation** : `border-width` de l'état **sélectionné** dans le chrome — bouton de jour de la carte, paire segmentée du portail (§7.2, §9.2). La couleur vient du composant. **Il ne compte pas dans la quantité de `--bord-fort`** ci-dessous : ce n'est pas le même jeton, ce n'est pas le même rôle, et un état d'interaction n'est pas un filet de composition. **Sans rapport avec les épaisseurs de la carte**, qui varient (§9.2.a) |

**[v2.3] Répartition des filets — une seule occurrence forte, et elle est placée.** La v2.0 prescrivait
trois `--bord-fort` sur l'accueil (haut **et** bas de la carte, bandeau de non-officialité). Ce n'est pas
tenable : le filet le plus fort du site ne signale plus rien s'il paraît trois fois par page.

| Filet | Avant | Après | Raison |
|---|---|---|---|
| Tête de la bande carte | `--bord-fort` | **`--bord-fort`** | L'**unique** 4 px du chrome nominal, posé à l'entrée du héros (§1 : « la carte est le héros absolu ») |
| Bas de la bande carte | `--bord-fort` | **`--bord-moyen`** | Entrée forte, sortie discrète. La carte **reste encadrée** sans doubler le 4 px |
| Bandeau de non-officialité | `--bord-fort` | **`--bord-moyen`** | Le §5.6 du brief exige sa **présence**, jamais une épaisseur ; son slab et son filet 2 px en `--c-charbon` (12,79:1 d'encre) le détachent déjà |
| Tête de bande du bloc de légende | `--bord-moyen` | inchangé | `composants.css`, gelé |
| Intra-cellule de la liste | `--bord-fin` | inchangé | `composants.css`, gelé |
| Bas du bandeau d'alerte | `--bord-fort` | inchangé | État **exceptionnel**, et `composants.css` est gelé |

> **Règle de quantité, reformulée — parce qu'une règle qui ment un jour sur trois n'est pas une règle.**
> `--bord-fort` paraît **une fois dans le chrome nominal de la page** ; le **bandeau d'alerte**, qui est un
> **état exceptionnel**, porte le sien. La formulation nomme l'exception au lieu de prétendre l'ignorer :
> le `--bord-fort` de `.bandeau-alerte` vit dans `composants.css`, gelé, et il est donc hors d'atteinte.

**Conséquence datée, acceptée en connaissance de cause.** La bande carte est **vide** tant que la chaîne
« carte » n'a pas livré, et la règle de mise en page qui porte le filet de tête ne s'applique qu'à une
bande non vide. **Entre l'issue #23 et la chaîne « carte », le chrome nominal ne rend donc aucun filet de
4 px.** Aucune règle n'est violée dans l'intervalle : le slab, le filet 2 px, le filet 1 px et le repère
tiennent la composition. C'est enregistré au §17, divergence 8, pour qu'aucune revue ne le compte comme un
défaut.

### 6.4 Élévation — aucune ombre floue

| Token | Valeur | Emploi |
|---|---|---|
| `--ombre-0` | `none` | **Défaut de tous les éléments** |
| `--ombre-decalee` | `3px 4px 0 var(--c-trace)` | Panneau massif, bloc de légende |
| `--ombre-decalee-sombre` | `3px 4px 0 var(--c-mistral)` | Les mêmes, posés sur chrome sombre |

**`blur-radius` est toujours `0`.** Le décalage `(3px, 4px)` est exactement celui du repère : l'élévation
n'est pas une seconde idée, c'est la signature appliquée à une surface au lieu d'une barre.
**Deux types de composants au maximum** peuvent porter une ombre : le panneau massif et le bloc de légende.
Les boutons n'en portent pas. Les pastilles et les jalons n'en portent pas.

---

## 7. Mise en page

### 7.1 Accueil — la carte est le héros

Composition en **bandes horizontales pleine largeur** (strates calcaires), de haut en bas :

```
┌────────────────────────────────────────────────────────────┐
│ BARRE  mistral-nuit · 48px · nom du site · 4 liens · évitement│
├────────────────────────────────────────────────────────────┤
│ ▌                                                          │
│ ▌ L'ARDOISE   mistral-nuit, pleine largeur, ~34vh          │
│ ▌  ┌────────┐  Aujourd'hui, 12 massifs sur 25              │
│ ▌  │   12   │  sont d'accès autorisé.              (fs-700)│
│ ▌  │ /25    │  Statuts du mardi 11 août 2026, publiés la   │
│ ▌  └────────┘  veille à 17 h par la préfecture             │
│ ▲ le repère, version bloc, pleine hauteur                  │
├────────────────────────────────────────────────────────────┤
│ NON-OFFICIALITÉ calcaire-ombre · bord-moyen en haut · fs-200│
├════════════════════════════════════════════════════════════┤
│                                                            │
│           LA CARTE — plein cadre, bord à bord              │
│           min(72vh, 640px) · fond calcaire monochrome      │
│           2 aplats seulement : la lecture est immédiate    │
│                                                            │
├────────────────────────────────────────────────────────────┤
│ LÉGENDE DE LA CARTE   bande horizontale, 2 + 2 entrées     │
│   ▬ Accès au massif autorisé   ▬▨ Accès au massif interdit │
│   ▮ Accès à la ZAPEF* autorisé ▮ Accès à la ZAPEF* interdite│
│   *ZAPEF : Zones d’Accueil du Public en Forêt   (fs-100)   │
│   + bascule « Afficher les zones parcourues par le feu »   │
├────────────────────────────────────────────────────────────┤
│ ▌ LA LISTE DU JOUR   (h2 + repère) — ancre #liste          │
│   colonnes : Massif · Niveau d'Accès · ZAPEF · Fraîcheur   │
├────────────────────────────────────────────────────────────┤
│ ▌ DANGER MÉTÉO DU JOUR  module distinct, sans couleur      │
├────────────────────────────────────────────────────────────┤
│ ▌ ZONES PARCOURUES PAR LE FEU  (texte + limites EFFIS)     │
├────────────────────────────────────────────────────────────┤
│ PIED  mistral-nuit · attributions · licences · zéro cookie │
└────────────────────────────────────────────────────────────┘
```

> **[v2.3] Comment lire ce croquis.** Les intitulés de bande écrits en capitales — `BARRE`, `L'ARDOISE`,
> `LÉGENDE DE LA CARTE`, `LA LISTE DU JOUR`, `PIED` — **nomment des zones du schéma ; ils ne prescrivent
> aucune casse**. La seule ligne qui figurait un rendu de titre, le `h1` de l'ardoise, est **repassée en
> casse normale** et son annotation `caps` retirée (D-26). Le croquis a aussi perdu la rangée de marques
> qu'il montrait sous l'ardoise (D-27), et son filet de bas de carte est passé en `--bord-moyen` (§6.3).

**Ce qui fait la capture d'écran** : l'empilement ardoise sombre → carte monochrome où **deux couleurs
seulement** se répondent → bande de légende de quatre entrées. Trois bandes, aucun bruit, un chiffre net.
Rien d'autre n'a le droit d'attirer l'œil.

**Points non négociables de cette composition :**
- La carte **touche les deux bords** de la fenêtre à toutes les tailles. Elle n'est jamais dans un conteneur
  centré à coins arrondis. C'est la différence physique entre « une carte sur un site » et « un site qui est
  une carte ».
- Le bandeau de non-officialité (§5.6 du brief) est **entre l'ardoise et la carte**, pas en pied de page :
  il est dans le chemin du regard, mais dans une bande neutre — obligatoire sans être criard.
- **La liste du jour n'est pas un repli.** Elle a son `h2`, son repère, la pleine largeur, la même typographie
  de titrage que la carte, et elle est annoncée par le lien d'évitement « Aller à la liste des statuts ».
  Visuellement, c'est *le second héros*. On doit pouvoir lire le site en ne regardant qu'elle. Son en-tête
  de colonne d'état reprend **verbatim** l'intitulé officiel `Niveau d'Accès` (apostrophe droite).
- Le titre de la bande de légende est **verbatim** `Légende de la carte`.
- La légende compte **quatre entrées et une note**, jamais davantage : deux pour les massifs, deux pour les
  ZAPEF, la note `*ZAPEF : …`. Les états hors niveau **ne figurent pas dans la légende officielle** ; ils
  apparaissent dans une seconde ligne, séparée par un filet `--bord-fin` et introduite par l'étiquette
  « SUR CE SITE » — pour qu'on ne puisse jamais croire que la préfecture publie « information non disponible ».
- Le module météo est **visuellement étranger au reste** : bordure fine, aucune couleur, échelle de carrés.
  L'écart de traitement est la traduction de « deux notions jamais fusionnées » (§4.3 du brief).

**Panneau massif** : à partir de 900 px, colonne de droite `380px` collée (`position: sticky`) à côté de la
carte, avec le repère sur son bord gauche. En dessous de 900 px, feuille du bas (`bottom sheet`) occupant
au maximum 66 % de la hauteur, avec poignée de fermeture 44 px et fermeture par Échap. Jamais une popup
Leaflet par défaut, jamais une infobulle au survol.

**Points de rupture** (mobile-first, en `rem`) :

| Token | Valeur | Ce qui change |
|---|---|---|
| base | 360 px | Une colonne, gouttière `--esp-m`, légende en 2 colonnes *(voir §17, divergence 2 : le code livré la rend sur **une** colonne)*, tableau en cartes empilées |
| `--bp-s` | `37.5rem` (600 px) | Légende en ligne, tableau en vraies colonnes, gouttière `--esp-l` |
| `--bp-m` | `56.25rem` (900 px) | Panneau massif à droite de la carte, ardoise en deux colonnes |
| `--bp-l` | `80rem` (1280 px) | Contenu bridé à `--largeur-max: 1200px` ; **la carte reste plein cadre** |

À 360 px : aucun défilement horizontal, cibles ≥ 44 px, aucun élément en `position: fixed` autre que la
feuille du bas et la barre d'action du portail.

### 7.2 Portail gestionnaire

Même système, chrome plus dense — c'est un outil, pas une vitrine, mais il doit être aussi soigné (§6 du brief).

> **[v2.3] Portée typographique du portail** (§5.1). Le **chrome** du portail — en-tête, tableau, boutons,
> barre d'action, historique — est **entièrement en famille de texte**, en casse normale. **Les étiquettes
> de statut `--fs-250` restent en famille d'affichage**, capitales et `--ls-etiquette` comprises : ce sont
> des titres de statut au sens de la règle, et ce sont elles qui portent les libellés officiels reproduits
> verbatim (§11.4). C'est la borne (a) de la règle de portée, et elle vaut **ici en particulier** : sans
> elle, la paire segmentée ci-dessous perdrait la famille de ses libellés officiels.

- **En-tête** `--c-mistral-nuit`, 56 px : « MASSIFS · Mise à jour des statuts », date de la session, déconnexion.
- **Écran unique** : un tableau, une ligne par massif. Colonnes : massif · état d'aujourd'hui (lecture seule,
  pastille + libellé) · **`Niveau d'Accès` pour demain** · dernière modification (auteur + heure).
- **Le choix se fait entre deux options, pas cinq** : le groupe radio devient une **paire segmentée**
  `Accès au massif autorisé` / `Accès au massif interdit`, chacune ≥ 44 px de haut, pastille + motif +
  libellé **verbatim** posé à côté de l'aplat (jamais dessus), liseré `--c-charbon` 2 px, état sélectionné
  = liseré `--bord-selection` (**4 px**, §12) en `--c-mistral-nuit` + repère à gauche. **[v2.4]** Ces
  4 px-là ne varient jamais : une paire segmentée ne change pas d'échelle, contrairement à un polygone de
  carte (§9.2.a). Navigation clavier par flèches (rôle `radiogroup`),
  `Tab` passe à la ligne suivante.
  **[v2.5] Le repère de cette option est en contradiction ouverte** avec le §3.3 (« jamais sur les champs
  de formulaire ») et avec la liste **fermée** du §3.2, qui ne comporte pas cet emplacement. Le §7.2
  l'emporte **à titre conservatoire** (contrat #14, **A-19**), la divergence est enregistrée au **§17,
  ligne 20**, et l'arbitrage définitif — amender la liste fermée ou retirer cette prescription — est
  **versé au §18, recommandation 1**. Un ajout à une liste fermée n'est pas un arbitrage d'agent.
- **Conséquence directe de la légende binaire** : deux cibles au lieu de cinq divisent par deux et demi le
  temps de saisie d'une ligne. L'objectif « mise à jour complète en moins d'une minute » (§6 du brief)
  pour les 25 massifs devient atteignable **sans raccourci de masse**. Un bouton « tout autoriser / tout
  interdire » reste néanmoins offert au-dessus du tableau, car les journées où les 25 massifs partagent
  le même état sont le cas nominal observé.
- **Barre d'action collée en bas**, `--c-mistral-nuit`, repère sur son bord gauche : compteur « 7 statuts
  modifiés » + bouton unique **« Publier les statuts »**. Aucune étape intermédiaire, aucune modale de
  confirmation *avant*, une confirmation *après* (annoncée en `aria-live="polite"`).
- **Historique** : même tableau, filtres en ligne, export CSV. Les valeurs ancienne/nouvelle sont montrées
  par deux pastilles séparées par une **flèche rendue en SVG en ligne**, jamais par une couleur de diff.
  La flèche est **décorative** (`aria-hidden="true"`, `focusable="false"`, `fill="currentColor"`) et
  **doublée d'un texte « remplacé par » en `screen-reader-text`**, qui est ce qui porte le sens. Elle
  n'apparaît pas sur une première publication, qui n'a qu'une pastille.

> **[v2.5] Contradiction interne levée — la flèche s'écrit en SVG, jamais en caractère.** Jusqu'ici ce
> paragraphe imposait « une flèche typographique `→` », pendant que le §5 et **D-25** l'interdisaient
> nommément et que le §16 en faisait un **défaut bloquant**. Les deux textes se contredisaient depuis la
> v2.1, et la contradiction a été rencontrée **en production** : le contrat `docs/contracts/issue-15.md` a
> été gelé sur la rédaction de ce §7.2, `dev-ux-cms` a **refusé de l'appliquer**, et le contrat a dû être
> re-gelé en cours de chaîne (`CORRECTIF-1`).
>
> **C'est D-25 qui l'emporte, et pour un fait, pas pour une préférence** : U+2192 est **hors du
> sous-ensemble `latin`** et **absent des deux polices auto-hébergées** (§5). Écrit en caractère, il
> afficherait un **rectangle vide** — ou serait emprunté à une police système, donc hors du design system.
> Le mot « typographique » de la rédaction d'origine voulait dire **sobre et non décorative**, jamais
> *caractère Unicode* ; il est retiré pour qu'on ne puisse plus le lire ainsi. **Le §5, D-25 et le §16 ne
> changent pas d'un mot** : ce paragraphe les rejoint.
- Aucun bouton désactivé : si une action est impossible, elle reste focusable et explique pourquoi (§9.2).

### 7.3 Pages éditoriales (La démarche, Accessibilité, Mentions légales)

- Une seule colonne, `--mesure` 68ch, alignée à gauche de la grille (pas centrée : la page garde son bord
  gauche commun avec l'ardoise et les titres).
- **[v2.3]** `h2` en **famille de texte** (`--police-texte`), en **casse normale**, **sans repère**,
  `--esp-section` avant chacun. C'est l'application de la **règle de portée** du §5.1 — les pages
  éditoriales sont hors portée de la famille d'affichage — et de l'**amendement du §3.2** : le repère est
  la signature de l'information de statut ; devant un titre éditorial, il ne signale plus rien.
- Les citations et encarts sont des **slabs `--c-calcaire-ombre` avec `--bord-fort` en haut**, jamais des
  cartes ombrées ni des filets fins verticaux. **C'est ce même encart qui accueillera la consigne** quand
  elle sera fournie (§8.4) : le composant existe déjà, il n'y a rien à dessiner le jour venu.
- Les tableaux de sources/licences reprennent exactement le tableau de la liste du jour.
- Aucun visuel décoratif. Les seules images du site sont : l'image statique du département (repli sans JS)
  et, éventuellement, des photographies personnelles créditées sur « La démarche » — jamais en fond, jamais
  en bandeau héroïque, jamais derrière du texte.

#### [v2.3] Le pied de page — une convention de **menu**, jamais du balisage

Le web public français attend trois entrées au pied : **Mentions légales**, **Accessibilité**,
**La démarche**. Ce document les prescrit — mais comme des **éléments de menu**, et c'est une décision, pas
une commodité.

- Ce sont **trois entrées de menu** que l'administrateur affecte à l'emplacement `pied` **le jour où les
  pages existent**. **Aucune ligne de thème ne change ce jour-là** : le gabarit rend déjà l'emplacement, et
  il se tait tant qu'aucun menu n'y est affecté. La politique de navigation reste chez le propriétaire du
  site, pas dans le code.
- **Aucun lien codé en dur.** Les trois pages n'existent pas : un lien écrit aujourd'hui produirait une
  **404 dans le chrome de chaque page du site**. Un slug inventé et un libellé inventé sont, de la même
  façon, des inventions interdites par le §4.2 du brief.
- **Aucun taux ni qualificatif de conformité RGAA, nulle part.** Aucun audit n'a été mené ; or « non
  conforme », « partiellement conforme » et « totalement conforme » sont eux-mêmes des **résultats
  d'audit**. En écrire un — fût-ce le plus pessimiste, par prudence apparente — serait affirmer un fait non
  établi. Et une valeur figée dans un gabarit devient **fausse en silence** au premier audit suivant :
  c'est exactement la classe d'erreur que le §16 interdit déjà pour le chiffre du jour. Ligne de revue
  ajoutée au §16.

> **`OUVERT` — la phrase « zéro cookie » du croquis §7.1.** Le croquis fait figurer « zéro cookie » au
> pied. **Cette phrase n'a aucune chaîne normative au §11.3**, qui est la liste **fermée** des phrases que
> le site a le droit de rédiger. Deux issues, et deux seulement : ou bien elle est **fournie mot pour mot**
> par le propriétaire du projet et entre au §11.3, ou bien elle reste **traitée sur « La démarche »**, où
> le §9 du brief place le choix de conception « zéro cookie assumé et affiché ». **Tant que ce n'est pas
> tranché, aucun gabarit n'en écrit une variante.** On ne rédige pas une phrase de pied par déduction.

---

## 8. Composants clés — spécification visuelle

### 8.1 Pastille de massif et jalon ZAPEF — **deux silhouettes, parce que ce sont deux dimensions**

C'est l'objet le plus répété du site, et la révision 2.0 y fait son changement structurel : il n'y a plus
une échelle de pastilles graduées, il y a **deux familles d'objets de forme différente**.

```
MASSIF  (une surface)              ZAPEF  (un point)
┌──────────────┐                        ┌────────┐
│▌▬▬▬▬▬  ACCÈS…│  pastille              │▮       │  jalon 18×18
└──────────────┘  rectangle 26×16       └───┬────┘  + hampe 2×8
                                            ┴       le point est au pied
```

| | Pastille de massif | Jalon ZAPEF |
|---|---|---|
| Silhouette | **rectangle large 26 × 16 px** — une surface | **carré 18 × 18 px planté sur une hampe de 2 × 8 px** — un point |
| Liseré | `--c-charbon` 2 px | `--c-charbon` 2 px, hampe comprise |
| Motif `autorise` | aucun (aplat nu) | aucun (aplat nu) |
| Motif `interdit` | `hachure_croisee`, trait 2,5 px, pas 10 px | `barre` : une seule oblique 3 px d'angle à angle |
| Rayon | `--r-0` | `--r-0` |

**Pourquoi deux silhouettes et non deux couleurs.** Le cas nominal du `level` brut 3 affiche un **jalon
vert sur un massif rouge**. Si les deux objets partageaient la même forme, cet affichage se lirait comme
une contradiction ou comme un bug. Larges/plates pour les surfaces, hautes/plantées pour les points :
**la forme dit de quoi on parle, la couleur dit dans quel état c'est.** C'est aussi ce qui rend la
divergence lisible sans texte, donc lisible de loin.
Mesuré : vert sur rouge ne contraste qu'à **1,48:1** — le liseré charbon (6,10:1 sur le vert, 4,11:1 sur
le rouge) est **la seule chose** qui détache le jalon de l'aplat qu'il surplombe (§10.2).

**Pourquoi la barre unique pour le jalon interdit et non la hachure croisée.** À 18 px, une hachure croisée
de pas 10 px ne montre que deux ou trois croisements et se lit comme du bruit ou du crénelage. La barre
unique reste identifiable jusqu'à 14 px. Le motif change parce que **l'échelle change**, pas parce que le
sens change : c'est la même opposition binaire aplat nu / aplat barré.

Règles communes :
- Hauteur de cible **≥ 44 px** quand l'objet est cliquable, taille nominale quand il est informatif —
  la cible s'obtient par du padding transparent, jamais en grossissant la pastille.
- Le libellé est en `--fs-250` (capitales, `--ls-etiquette`), en `--c-charbon` **sur le fond de page**,
  posé **à côté** de l'aplat. **Jamais dessus** (§4.1.d règle 3).
- Le motif est **toujours** présent sur l'état `interdit`, dans tous les contextes, y compris l'impression.

Motifs — CSS de référence (aucune image, budget §10 du brief) :

```css
/* ── Massifs : deux états, une opposition binaire ─────────────── */
.pastille { inline-size: 26px; block-size: 16px; border: 2px solid var(--statut-lisere);
            border-radius: var(--r-0); }

.pastille--autorise  { background-color: var(--statut-autorise); }   /* aplat nu, aucun motif */

.pastille--interdit  { background-color: var(--statut-interdit);
  background-image:
    repeating-linear-gradient(45deg,  var(--statut-interdit-encre) 0 2.5px, transparent 2.5px 10px),
    repeating-linear-gradient(-45deg, var(--statut-interdit-encre) 0 2.5px, transparent 2.5px 10px); }

/* ── États hors niveau : ce sont des absences, pas des niveaux ── */
.pastille--indisponible { background-color: var(--statut-indisponible);
  background-image: repeating-linear-gradient(-45deg,
    var(--statut-indisponible-encre) 0 2px, transparent 2px 9px); }

.pastille--hors-saison  { background-color: var(--statut-hors-saison); }  /* aucun motif */

.pastille--non-publie   { background-color: var(--statut-non-publie);
  background-image: radial-gradient(var(--statut-non-publie-encre) 1.2px, transparent 1.4px);
  background-size: 6px 6px; }

/* ── ZAPEF : carré planté ─────────────────────────────────────── */
.jalon { inline-size: 18px; block-size: 18px; border: 2px solid var(--statut-lisere);
         border-radius: var(--r-0); position: relative; }
.jalon::after { content: ""; position: absolute; inset-block-start: 100%;
  inset-inline-start: calc(50% - 1px); inline-size: 2px; block-size: 8px;
  background: var(--statut-lisere); }

.jalon--autorise { background-color: var(--statut-zapef-autorise); }     /* aplat nu */

.jalon--interdit { background-color: var(--statut-zapef-interdit);
  background-image: linear-gradient(45deg, transparent calc(50% - 1.5px),
    var(--statut-zapef-interdit-encre) calc(50% - 1.5px) calc(50% + 1.5px),
    transparent calc(50% + 1.5px)); }
```

**Sur la carte**, les mêmes motifs sont déclarés en `<pattern patternUnits="userSpaceOnUse">` dans le
`defs` du calque SVG de Leaflet. **La densité du motif doit rester constante à l'écran quel que soit le
zoom** : recalculer la taille du pattern sur `zoomend`, ou utiliser un pane non transformé. Un motif qui
s'étire au zoom cesse d'être un encodage fiable — et sur une légende binaire, le motif est la moitié de
l'information.

**Dégradation ZAPEF** : tant que la géométrie des points ZAPEF n'est pas fournie par un contrat (§4.1.e,
`OUVERT`), **aucun jalon n'est rendu sur la carte**. La dimension ZAPEF reste alors visible dans le
panneau massif, dans la liste du jour et dans la légende. Le jalon décrit ici est **prêt**, pas
« à venir » : le jour où les points existent, il n'y a rien à concevoir.

### 8.2 L'ardoise — **le chiffre du jour**

- Fond `--c-mistral-nuit`, texte `--c-calcaire` (12,66:1 conforme), méta en `--c-mistral-clair` (7,73:1).
- Chiffre en `--fs-800`. **[v2.1] Correction d'une promesse fausse.** La v2.0 obtenait la stabilité du
  chiffre par `font-variant-numeric: tabular-nums`. **Sur la police de titrage, cette déclaration est un
  no-op silencieux** : Big Shoulders Display **n'expose pas la fonction OpenType `tnum`**, et ses chiffres
  sont fortement proportionnels — au poids 800, le `1` avance de 511 unités contre 961 pour le `5`, soit
  un écart de 450/2000 em, **≈ 29 px à `--fs-800`**. Un passage de 9 à 12, ou de 11 à 25, déplace donc
  réellement ce qui suit. **Règle qui remplace la promesse : la largeur en ligne du chiffre est réservée**,
  de sorte que la variation d'avance des chiffres ne provoque **aucun reflux du texte environnant**. La
  technique de mise en page (largeur fixe, grille, `ch` de réserve…) appartient aux chaînes d'intégration ;
  ce qui est normatif ici, c'est le résultat : **rien ne bouge autour du chiffre.**
  *(La police de labeur, elle, expose bien `tnum` : `tabular-nums` reste légitime dans les tableaux.)*
- Le dénominateur (« /25 ») est en `--fs-500`, aligné sur la ligne de base basse du chiffre.
- **[v2.3]** Le chiffre est **bridé en consommation à `5.75rem`** (§5.1) — jeton `--fs-800` inchangé,
  plafond en `rem`, inerte à 360 px et agissant à partir de ≈ 800 px de large. Au-delà de ce plafond, le
  chiffre cessait d'être **une donnée dans une bande d'information** pour devenir **une affiche**.
- Repère version `--bloc` sur toute la hauteur du slab, à gauche : `::after` `--c-calcaire`, `::before`
  `--c-mistral`.

> **[v2.3] La rangée de 25 marques que cette section prescrivait est retirée — D-27.** Le paragraphe qui
> la décrivait et ses six contraintes sortent d'ici. La décision, ce qu'elle coûte et ce qui la remplace sont
> écrits en **D-27** (§15) ; l'archive de la passe 2 bis (§14.2) et **D-19** ne sont **ni réécrites ni
> annotées** — une décision levée n'est pas une décision effacée. L'ardoise garde le chiffre du jour, son
> dénominateur, sa ligne de fraîcheur et son repère `--bloc` : **rien d'autre n'y entre.**

**Si l'information du jour est indisponible** : le chiffre disparaît, remplacé par le mot
« **Indisponible** » en `--fs-700`, l'ardoise prend la hachure `\` `--c-mistral` en surimpression à 12 %,
et le lien « Ouvrir la carte officielle de la préfecture » passe en bouton primaire. On ne montre
**jamais** un chiffre de la veille.

> **[v2.3] Deux précisions sur ce mot, et sur lui seul** — le reste du comportement d'indisponibilité
> (disparition du chiffre, hachure, lien passé en bouton primaire) est **inchangé et reste prescrit**.
>
> **(a) Il s'écrit en casse normale, « Indisponible ».** Il est composé en `--fs-700`, c'est-à-dire **au
> corps du `h1`, dans la même bande et à la place du chiffre du jour** : capitalisé à côté d'un `h1`
> désormais en casse normale, il produirait exactement l'incohérence que cette révision existe pour
> supprimer. Le §5.1 réserve les capitales aux **étiquettes `--fs-250`**, et ce mot n'en est pas une —
> le laisser capitalisé ouvrirait une **troisième catégorie non écrite**, que chaque revue re-litigerait.
> Enfin c'est **l'état de dégradation le plus visible du site** : un mot d'absence d'information crié en
> capitales est précisément le registre dont D-26 s'écarte.
>
> **(b) Cette prescription décrit ce qui est autorisé, pas ce qui est livré** — au même titre que celle du
> §8.5. **Le thème ne rend pas ce mot** : le `h1` porte à sa place la chaîne du §11.3, « Information du
> jour non disponible. Consultez la carte officielle de la préfecture. », et le chiffre n'est émis que
> dans le bras `disponible`. La raison est bonne et elle est écrite dans le code (chaîne #5, arbitrage
> A-5) : rendre ce mot poserait un **second bloc `--fs-700` adjacent au `h1`**, qui dit déjà exactement
> cela. Enregistré au **§17, divergence 9**.

### 8.3 Bandeau d'alerte (péremption, source indisponible, hors-saison)

Fond `--c-mistral-nuit`, texte `--c-calcaire`, repère `--bloc` à gauche, `--bord-fort` en bas, hachure
`--c-mistral` à 45° en fond à faible opacité. Le premier mot du texte porte l'information
(« Donnée périmée. », « Source indisponible. », « Dispositif estival inactif. ») : le sens ne repose ni
sur la couleur ni sur une icône. La bannière de péremption **s'ajoute** aux statuts affichés, elle ne les
masque jamais.

### 8.4 Panneau massif — et **l'emplacement de la consigne**

Ordre vertical **fixe**, du haut vers le bas. Cet ordre ne varie pas selon l'état : c'est ce qui rend le
panneau prévisible au clavier et au lecteur d'écran.

| # | Bloc | Toujours présent ? |
|---|---|---|
| 1 | Nom du massif — `h2` + repère, **famille d'affichage, casse normale** *([v2.3] D-26)* | oui |
| 2 | **État du massif** — pastille + libellé officiel verbatim en `--fs-400` | oui, si `etat === 'disponible'` |
| 3 | **État des ZAPEF** — jalon + libellé officiel verbatim + note `*ZAPEF : …` en `--fs-100` | oui, si le massif porte des ZAPEF et `etat === 'disponible'` |
| 4 | **Emplacement de la consigne** | **conditionnel — voir ci-dessous** |
| 5 | Fraîcheur et source (§11.3) | oui |
| 6 | Lien « Ouvrir la carte officielle de la préfecture » | oui |

> **[v2.3] Pourquoi ce `h2`-ci garde tout ce que D-26 ne lui retire pas.** Le nom du massif est un **titre
> de statut** au sens de la règle de portée (§5.1) : il reste donc en **famille d'affichage** et **garde
> son repère** (§3.2, emplacement n° 2, qui vise les `h2` **en portée**). Seules les **capitales** tombent.
> Un `h2` peut donc perdre ses capitales sans perdre sa famille ni son repère : les trois questions sont
> distinctes, et les confondre ferait passer ce panneau en chrome éditorial.

**Comment l'emplacement de la consigne se comporte aujourd'hui, où aucune consigne n'est publiée.**

L'extension expose `consignes_publiees === false` et une `consigne` **vide** (jamais `null`, jamais
inventée). Dans cet état :

- **Aucun intitulé « Consigne » n'est rendu.** Pas de titre orphelin.
- **Aucun gabarit vide, aucun tiret, aucun « — », aucun « non renseigné », aucun squelette.** Un
  emplacement vide qui se signale est pire qu'un emplacement absent : il donne à croire qu'une donnée
  manque alors que le fait est que la préfecture n'en publie pas.
- **Aucune hauteur réservée.** Le panneau est une simple pile en `gap: var(--esp-l)` ; un bloc absent ne
  laisse aucun trou, et rien ne se déplace le jour où il apparaît.
- À la place, **une seule phrase factuelle** en `--fs-200` / `--c-charbon-doux`, sans intitulé, sans
  excuse et sans point d'exclamation :
  > « Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en vigueur fait foi : [lien]. »

  Elle ne dit pas « information manquante » : elle dit ce qui est vrai et où aller. Voix active, pas
  d'apologie (§11.1 règle 3).

**Comment elle se remplira, sans aucune refonte.** Quand le propriétaire fournira une transcription de
l'arrêté, l'extension basculera `consignes_publiees` à `true` et renseignera `consigne`. Le bloc 4 rend
alors **un encart déjà défini au §7.3** — slab `--c-calcaire-ombre`, `--bord-fort` en haut, `--r-0` —
précédé de l'étiquette `CONSIGNE` en `--fs-250` capitales, texte en `--fs-400`, mesure `--mesure-etroite`.
**Aucun nouveau composant, aucun nouveau token, aucun nouveau motif ne sera nécessaire** : l'encart, la
bordure, l'étiquette et l'échelle typographique existent déjà et sont utilisés ailleurs. C'est la
définition opérationnelle de « ça tombe dedans sans rien redessiner ».

**Interdits attachés à cet emplacement :**
- Le thème ne **compose jamais** une consigne, ne la déduit jamais de l'état, ne la traduit jamais depuis
  un entier. Elle vient de l'extension ou elle n'existe pas.
- Tant que `consignes_publiees === false`, **aucun texte ne peut occuper l'emplacement 4** en dehors de la
  phrase factuelle ci-dessus.
- Une consigne ne peut **jamais** contredire ni nuancer le libellé officiel de l'état : elle le complète,
  elle ne le réinterprète pas.

### 8.5 Bloc de légende — reproduction fidèle

- Titre `Légende de la carte`, verbatim, en `h2` + repère.
- Quatre entrées, dans cet ordre : massif autorisé, massif interdit, ZAPEF autorisé, ZAPEF interdite.
  Libellés **verbatim**, y compris `autorisé` masculin / `interdite` féminin.
- Note `*ZAPEF : Zones d’Accueil du Public en Forêt` en `--fs-100`, apostrophe U+2019, rattachée
  typographiquement aux deux entrées ZAPEF (pas en pied de bloc isolé).
- **Une seconde ligne, séparée par `--bord-fin` et introduite par l'étiquette `SUR CE SITE`**, présente
  les états hors niveau (`information non disponible`, `dispositif estival inactif`). Cette séparation est
  **obligatoire** : elle empêche d'attribuer à la préfecture des états qui sont les nôtres.
- **[v2.3]** Le bloc **est autorisé à porter `--ombre-decalee`** — il reste, avec le panneau massif, l'un
  des deux seuls composants du site à qui le §6.4 l'accorde. **Mais il ne la porte pas dans le code
  livré** : sa mise en volume est faite par le **filet 2 px en tête de bande** et par le repère de son
  `h2`. Cette phrase disait « le bloc porte » ; elle décrivait un rendu qui n'existe pas. Le jeton n'est ni
  supprimé ni retiré du §6.4 — voir §12.1 (jetons orphelins) et §17, divergence 1.
- **La légende n'est jamais masquée derrière un bouton, un accordéon ou un survol.** Elle est visible en
  permanence, sans interaction, y compris sans JavaScript.

### 8.6 Module « Danger météo du jour » — sans aucune couleur

Échelle à cinq crans rendue par des carrés de 12 px en `--c-charbon` (pleins = niveau atteint, vides =
liseré 1,5 px `--c-garrigue`), suivie du libellé officiel Météo-France en toutes lettres et de la phrase
d'explication : « Le danger météo décrit les conditions du jour ; il ne détermine pas l'accès au massif,
qui relève de l'arrêté préfectoral. » Aucune pastille colorée, aucune icône de flamme, aucune proximité
visuelle avec les statuts.

> **Cette règle devient plus critique en v2.0, pas moins.** Le danger météo a cinq crans ; l'accès au
> massif en a deux. Colorer l'échelle météo installerait à l'écran une gradation à cinq niveaux qui serait
> immédiatement prise pour la vraie granularité du dispositif — exactement le contresens que §4.3 du
> brief cherche à empêcher. L'absence de couleur n'est pas une austérité : c'est ce qui protège la
> lecture binaire de la carte.

---

## 9. États d'interaction et mouvement

### 9.1 Anneau de focus — spécification unique

```css
:root {
  --focus-trait:  var(--c-mistral-nuit);    /* sur surfaces claires */
  --focus-trait-inverse: var(--c-calcaire); /* sur chrome sombre */
  --focus-halo:   var(--c-mistral-clair);
}
:where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
  outline: 3px solid var(--focus-trait);
  outline-offset: 2px;
  box-shadow: 0 0 0 6px var(--focus-halo);
}
.sur-sombre :focus-visible { outline-color: var(--focus-trait-inverse);
                             box-shadow: 0 0 0 6px var(--c-mistral); }
```

- Jamais `outline: none` sans remplacement. `:focus-visible` uniquement (pas de halo à la souris),
  **sauf** sur la feuille du bas et le panneau massif, où le focus programmatique doit rester visible.
- **Sur la carte**, un massif ou un jalon focusé reçoit un **double contour** : `--c-calcaire` 3 px **et**
  `--c-charbon` 3 px. **[v2.4] Les deux moitiés sont concentriques, jamais décalées** — le décalage
  appartenait au repère, retiré de la carte par D-28. Sur un polygone, ces deux moitiés sont **le cerne et
  son séparateur** (§9.2.a), qui les rendent déjà : le focus et la sélection coïncidant toujours (contrat
  #7, A-9), **aucun troisième tracé n'est ajouté**, et l'anneau générique de `layout.css` reste posé
  par-dessus (A-16). Ce n'est pas un raffinement : sur l'aplat vert officiel, un anneau calcaire
  seul ne fait que **2,42:1** ; la moitié charbon monte à **6,10:1**. Sur le fond de carte clair, c'est
  l'inverse qui menace (calcaire vs `--c-carte-fond` = 1,07:1) et c'est encore la moitié charbon qui
  sauve (13,79:1). **Le double contour garantit qu'au moins une de ses deux moitiés atteint 3:1 sur
  chacune des surfaces du système** — preuve complète au §10.5.
- **[v2.5] Limite connue, non spécifiée : le focus sur un `<path>` SVG.** Sur une forme SVG, Chrome dessine
  l'`outline` autour de la **boîte englobante** et **ne rend pas** le `box-shadow` du halo : sur Regagnas à
  z9, l'anneau paraît comme un **rectangle de 94 × 55 px**, plus fort que le cerne vu à 1,5 px. C'est
  **conforme** à ce qui est écrit ici et au contrat #7 A-16 — l'anneau est **conservé**, le retirer
  exigerait un `outline: none` dont le seul remplaçant serait un tracé créé par le JS, et WCAG 2.4.7
  tomberait si la duplication échouait. **Ce document ne spécifie aucun traitement de focus propre au
  SVG** : le manque est enregistré (contrat #50, **V-50.1**) et la marche à suivre est au **§18,
  recommandation 4**. Tant qu'il n'est pas comblé, **l'anneau générique reste la règle** ; l'améliorer sans
  mesure serait rouvrir la carte sans regarder l'écran, ce que le §16 interdit.

### 9.2 Survol, actif, désactivé

| État | Traitement | Règle |
|---|---|---|
| Repos | — | Les liens de contenu sont **soulignés en permanence** (`text-underline-offset: 0.18em`, épaisseur 1,5 px) |
| Survol | Fond `--c-calcaire-ombre` (boutons, lignes) — **[v2.5] règle valable sur surface claire seulement** : appliquée telle quelle à une surface de chrome, elle efface l'étiquette (bouton primaire du portail, **1,15:1**). Écart livré et enregistré au **§17 ligne 24** ; borne à écrire au **§18, recommandation 2** — ; soulignement porté à 3 px (liens) ; **[v2.4]** sur la carte, liseré du massif porté à `--carte-survol`, soit **une fois et demie le liseré du palier courant** (§9.2.a) — **jamais un changement de teinte** | **Aucune information n'apparaît au survol.** Un contenu qui n'existe qu'au survol est un défaut bloquant (§5.2 du brief) |
| Actif | `transform: translate(1px, 1px)` ; `--ombre-decalee` réduite à `2px 3px 0` | Le geste « la peinture s'enfonce » |
| Sélectionné — **hors carte** | Liseré porté à `--bord-selection` (**4 px**) + repère à gauche — **[v2.5]** ce repère-là est en contradiction ouverte avec le §3.2 et le §3.3 (§17 ligne 20, §18 recommandation 1) | Jamais un simple changement de couleur de fond, **jamais un éclaircissement de l'aplat officiel** |
| Sélectionné — **sur la carte** | **[v2.4] Le cerne** : un anneau posé **entièrement hors du polygone**, jamais dessus. Aucun repère (D-28), aucune duplication décalée, aucune épaisseur ajoutée au liseré du massif lui-même. Épaisseurs par palier : §9.2.a | **L'aplat de statut et son motif ne sont jamais recouverts, à aucun palier.** Un traitement de sélection qui mange l'aplat est un défaut **bloquant** |
| Désactivé | **N'existe pas.** L'action reste focusable et explique la raison (« Publication impossible : aucun statut modifié. ») | Évite l'exception de contraste et le cul-de-sac clavier |

> **Règle absolue héritée du §4.1** : aucun état d'interaction ne modifie la **teinte** d'un aplat de
> statut. Ni survol, ni focus, ni sélection, ni désactivation, ni opacité. Un vert éclairci au survol
> serait une couleur officielle altérée. Les états d'interaction agissent sur le **liseré** et sur le
> **cerne**, jamais sur le pigment.

> **[v2.4] Règle nouvelle, de même force, et qui manquait : aucun état d'interaction ne recouvre un aplat
> de statut.** La règle ci-dessus interdisait de *changer* le pigment ; elle n'interdisait pas de le
> *cacher*, et c'est par ce trou que la v2.3 a fait peindre un massif entier en calcaire. Un trait posé sur
> un tracé SVG est **centré** : il consomme la moitié de son épaisseur **à l'intérieur** de la forme. À 4 px
> sur une languette de 3 px de large, la moitié intérieure suffit à tout recouvrir. **Cacher un aplat
> officiel et l'altérer produisent exactement la même perte d'information** ; les deux sont désormais
> interdits par écrit.

### 9.2.a **[v2.4]** Le cerne — le traitement « sélectionné » de la carte

> **Une phrase** : le massif sélectionné est **cerné par l'extérieur** — un anneau charbon, doublé d'un
> séparateur calcaire dès qu'il y a la place, dessiné **sous** le polygone de sorte que l'aplat opaque du
> statut recouvre lui-même la moitié intérieure du trait.

**Le mécanisme, qui est aussi la preuve.** Le cerne est la **duplication du tracé courant**, rendue dans un
pane Leaflet placé **sous** celui des massifs. Un trait SVG étant centré, sa moitié intérieure tombe sous
le polygone — et le polygone est peint en `fill-opacity: 1` (§4.1.d règle 1). **La moitié intérieure est
donc invisible par construction, pas par convention** : il n'y a aucune règle à respecter, aucun réglage à
maintenir, aucune façon de se tromper. Seule la moitié extérieure paraît. C'est le même raisonnement que
D-24 : sur une carte, quand la couleur ne peut pas trancher, **c'est l'ordre des couches qui tranche**.

De la forme vers l'extérieur, un massif sélectionné donne donc :

```
 [ aplat de statut + motif ]  ← jamais touché, à aucun palier
 [ son liseré charbon ]       ← moitié extérieure seulement
 [ séparateur calcaire ]      ← absent au palier « département »
 [ cerne charbon ]            ← ce qui dit « celui-ci »
 [ fond de carte ]
```

**Pourquoi un séparateur clair, et pourquoi il ne porte rien.** Sans lui, le cerne et le liseré du massif
sont la même encre et fusionnent en une bavure noire : on voit un trait épais, on ne voit pas un anneau.
Le séparateur les décolle. Il est encadré de charbon **des deux côtés**, à **14,74:1** (§10.2.b) — c'est ce
qui l'autorise à ne rien porter lui-même : mesuré, `--c-calcaire` ne fait que **1,07:1** sur le fond de
carte et **2,42:1** sur le vert officiel. **Le séparateur est un intervalle, jamais un trait porteur.**
Toute la conformité du cerne est portée par sa part **charbon**, comme celle du liseré (D-13).

**Pourquoi il disparaît au palier « département », et c'est la décision centrale de cette révision.**
Au zoom 9, les massifs filamenteux n'ont pas d'espace entre leurs languettes : chaque pixel d'encre posé
**à l'extérieur** d'un filament est un pixel posé **dans le vide entre deux filaments**, et deux halos
voisins se rejoignent. Le défaut constaté à l'écran n'est pas venu d'une mauvaise couleur, il est venu
d'une **quantité de peinture claire** posée à une échelle qui ne pouvait pas l'absorber. D'où la règle,
qui tient en une ligne et qui est vérifiable en revue :

> **Au palier « département », aucune peinture claire n'est posée sur la carte.**

À ce palier, la sélection se lit comme **un bord repeint plus lourd** — pas comme un anneau. C'est le
palier où elle est la moins spectaculaire, et c'est **assumé** : c'est aussi celui où le panneau massif est
ouvert, porte le nom du massif, son repère et son état en toutes lettres, et où la région live a annoncé la
sélection. Le cerne n'est jamais le seul canal.

#### L'échelle d'épaisseurs par palier de zoom

La carte est cadrée sur l'emprise du référentiel (**z9**, vue département) et **plafonnée à z11** ; la
pyramide de tuiles monte à z12, que la carte ne suit pas (contrat #7, F-11). Trois paliers, trois classes
sur la **racine** de la carte, et **rien d'autre** :

| Palier | Zoom | Classe sur la racine | `--carte-lisere` | `--carte-survol` | `--carte-cerne` (charbon) | `--carte-cerne-clair` (séparateur) |
|---|---|---|---|---|---|---|
| **département** | **z ≤ 9** | `.carte--echelle-departement` | **1,5 px** | 2,5 px | **4,5 px** | **0** |
| **massif** | **z 10** | `.carte--echelle-massif` | **2 px** | 3 px | **9 px** | **5 px** |
| **abords** | **z ≥ 11** | `.carte--echelle-abords` | **3 px** | 4,5 px | **13 px** | **7 px** |

**Ces valeurs sont des `stroke-width`, pas des largeurs vues.** Un trait centré ne montre que sa moitié
extérieure quand il est sous le polygone. Ce que l'œil reçoit, par palier :

| Palier | Liseré vu (extérieur) | Séparateur vu | Cerne vu | Encre totale hors de la forme |
|---|---|---|---|---|
| **département** | 0,75 px | — | 1,5 px | **2,25 px** |
| **massif** | 1 px | 1,5 px | 2 px | **4,5 px** |
| **abords** | 1,5 px | 2 px | 3 px | **6,5 px** |

*Lecture du calcul, pour qu'il soit refaisable.* Trois traits **centrés sur le même tracé**, empilés du
plus large au plus étroit : le **cerne** charbon dessous, le **séparateur** calcaire par-dessus lui, puis
le **polygone** — son aplat opaque et son propre liseré — qui recouvre tout ce qui tombe en deçà de
`--carte-lisere` ÷ 2. D'où :

- **liseré vu** = `--carte-lisere` ÷ 2 ;
- **séparateur vu** = (`--carte-cerne-clair` − `--carte-lisere`) ÷ 2, nul quand `--carte-cerne-clair: 0` ;
- **cerne vu** = (`--carte-cerne` − **le plus large des deux traits qui le couvrent**) ÷ 2, soit
  (9 − 5) ÷ 2 = 2 px au palier massif, (13 − 7) ÷ 2 = 3 px au palier abords, et (4,5 − 1,5) ÷ 2 = 1,5 px au
  palier département, où c'est le liseré qui le couvre ;
- **encre totale hors de la forme** = `--carte-cerne` ÷ 2. C'est la seule valeur à surveiller en revue :
  elle est le rayon du halo, et c'est lui qui fusionne d'un filament au suivant quand il est trop gros.

Les valeurs de `stroke-width` sont donc **grandes et normales** : les trois quarts de leur épaisseur sont
recouverts par construction. **On ne les lit jamais comme des largeurs vues** — c'est la ligne de lecture
que la v2.3 avait manquée en écrivant « liseré 4 px » pour un trait dont 2 px tombaient dans l'aplat.

**Comparaison avec ce que la v2.3 produisait**, sur le même massif au même zoom. Son contour calcaire de
4 px était **centré et posé au-dessus** du polygone : **2 px hors de la forme et 2 px dedans**. Sa
duplication charbon, également 4 px, était **décalée de (3 px, 4 px)** : son encre atteignait donc **jusqu'à
6 px** du tracé d'un côté, et **recouvrait la forme** de l'autre. Sur une languette de 3 px de large, les
2 px intérieurs du calcaire suffisaient à **effacer l'aplat entier** — et c'est bien ce qu'on voit à
l'écran. **La v2.4 pose 2,25 px hors de la forme et zéro dessus.**

**Trois règles de tenue, opposables en revue :**

1. **Le survol vaut une fois et demie le liseré du même palier**, arrondi au demi-pixel supérieur
   (1,5 → 2,5 · 2 → 3 · 3 → 4,5). Ce n'est pas une suite de trois nombres choisis : c'est un rapport, et il
   se recalcule. Le survol reste **centré** sur le tracé — il est le seul état qui consomme de l'aplat, il
   est **transitoire**, il ne concerne **que le pointeur** (`@media (hover: hover)`), et il **ne porte
   aucune information** (§9.2). C'est pourquoi il a le droit de coûter, et pourquoi il a été réduit : la
   v2.3 le portait à 4 px, soit le **double** du liseré ; une fois et demie suffit à le percevoir.
2. **Le liseré ne descend jamais sous 1,5 px, et 1,5 px n'existe qu'au palier département.** Ce plancher
   est **mesuré** (§10.2.a), pas conventionnel.
3. **Le palier `massif` ne porte aucune règle CSS** : ses valeurs **sont** les valeurs nominales de `:root`.
   Le milieu de l'échelle est le défaut du système ; les deux paliers extrêmes sont les seules exceptions
   écrites. Une carte à laquelle aucune classe de palier n'a été posée — JS partiellement exécuté, classe
   oubliée — se rend donc **au palier massif**, qui est conforme partout. **L'échec par défaut est un état
   valide**, jamais un trait absent.

#### Comment cela se pose, et ce que le JS n'a pas le droit de faire

**Le JS de la carte ne pose aucun style.** Seuls `classList.add/remove/toggle` et l'attribut `hidden` lui
sont permis (contrat #7, interdit 24). Une épaisseur fonction du zoom se pose donc **par une classe de
palier sur la racine, et par des règles CSS** — il n'existe aucune autre voie, et aucune n'est à inventer :

- au montage, puis sur `zoomend`, le JS **remplace** la classe de palier sur la racine `.carte`, en lisant
  `carte.getZoom()` et **rien d'autre**. Trois classes, une table fermée, aucune valeur numérique de
  présentation écrite en JS — la borne du palier est un entier de zoom, pas une épaisseur ;
- `zoomend` est **déjà** écouté (garde de densité de motif, contrat #7 A-13) : aucun écouteur nouveau,
  aucun coût de peinture ajouté ;
- toutes les épaisseurs vivent au **§12**, dans `tokens.css`, et nulle part ailleurs. Un littéral
  d'épaisseur écrit dans `carte.css` est un défaut (§16).

**Ce que le cerne exige du DOM**, et qui existe déjà : deux couches GeoJSON non interactives portant le
tracé courant, rendues dans un pane placé **sous** celui des massifs — le cerne charbon d'abord, le
séparateur calcaire ensuite. C'est **le pane que la v2.3 utilisait pour la trace décalée** : il change de
nom et de rôle, il ne s'ajoute pas. L'échelle interne de Leaflet est inchangée (cerne 400, massifs 410).

**Ce que le cerne ne fait pas**, et qui doit rester vrai :

- il ne remplace pas l'anneau de focus générique du §9.1, qui reste posé sur le polygone focusé (contrat #7,
  A-16) — le supprimer exigerait un `outline: none` dont le seul remplaçant serait un tracé créé par le JS ;
- il **survit à la fermeture du panneau** : après Échap, le curseur garde son indicateur visible (WCAG 2.4.7) ;
- il ne s'anime pas, ne clignote pas, ne pulse pas, ne se dégrade pas en halo flou. **Aucune ombre, aucun
  `blur`, aucune opacité** (§16).

#### Dégradation

- **Sans JS** : sans objet — il n'y a pas de carte, donc pas de sélection. Le repli statique et la liste du
  jour portent l'information (§5.5 du brief).
- **À 360 px** : inchangé. Les paliers sont des paliers de **zoom cartographique**, pas de largeur d'écran ;
  ils ne croisent aucun point de rupture.
- **`forced-colors: active`** : le cerne passe en `CanvasText`, le séparateur en `Canvas` — l'intervalle
  reste un intervalle. La sémantique est celle du §3.4, la teinte n'y survit pas et n'a pas à y survivre.
- **Impression** : sans objet, `print.css` masque la bande carte.

### 9.3 Clavier et pointeur

- **Cibles ≥ 44 × 44 px** partout (`--cible-min: 2.75rem`), y compris la paire segmentée du portail, la
  bascule de couche EFFIS, la poignée de la feuille du bas et les contrôles de zoom de Leaflet
  (les contrôles par défaut font 30 px : ils sont **redimensionnés**, pas laissés tels quels).
  Les jalons ZAPEF, physiquement petits, reçoivent une zone de frappe transparente de 44 px.
- **Échap ferme** le panneau massif, la feuille du bas, le sélecteur de date, et rend le focus à l'élément
  déclencheur. Aucun piège clavier.
- Ordre de tabulation : évitement → en-tête → ardoise →
  **carte (un seul arrêt, puis flèches pour parcourir les massifs)** → légende → liste → sections → pied.
- Liens d'évitement « Aller au contenu » et « Aller à la liste des statuts » : cachés hors focus, visibles
  au focus en haut à gauche, fond `--c-mistral-nuit`, texte `--c-calcaire`.

### 9.4 Mouvement — durées et courbes

| Token | Valeur | Emploi |
|---|---|---|
| `--duree-court` | `120ms` | Changement de fond (survol, sélection dans la paire segmentée) |
| `--duree-moyen` | `200ms` | Ouverture/fermeture du panneau massif, zoom Leaflet |
| `--duree-long` | `320ms` | Feuille du bas mobile (translation verticale) |
| `--ease-net` | `cubic-bezier(0.2, 0, 0, 1)` | Entrées : démarrage franc, arrêt net |
| `--ease-retrait` | `cubic-bezier(0.4, 0, 1, 1)` | Sorties |

**Il n'existe que trois animations sur ce site** : le panneau (translation 12 px + opacité), les changements
d'état des puces (fond), le zoom de la carte. Rien d'autre ne bouge. **Les jalons ne bougent pas. Le
repère ne bouge jamais.**

Interdits explicites : parallaxe, apparition au défilement, compteur qui s'incrémente, souffle de vent
animé (la tentation « mistral » est refusée : la métaphore vaut pour la palette, pas pour le mouvement),
squelettes pulsants, spinners, marqueur ZAPEF pulsant ou rebondissant. Un chargement se signale par une
barre de progression de 2 px en `--c-mistral` en haut de la zone concernée, et par un texte `aria-live`.

### 9.5 `prefers-reduced-motion`

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```
Côté carte : quand `prefers-reduced-motion` vaut `reduce`, Leaflet est initialisé avec
`zoomAnimation: false`, `fadeAnimation: false`, `markerZoomAnimation: false` — la préférence doit
traverser la frontière CSS/JS, sinon elle n'est respectée qu'à moitié.

---

## 10. Preuve d'accessibilité (passe 3)

Toutes les valeurs ci-dessous sont **calculées** selon WCAG 2.x en sRGB (luminance relative, seuil
`0.03928`, exposant `2.4`), pas estimées. Elles sont vraies **parce que `fill-opacity` vaut 1** : sous
transparence, aucune ne tient.

### 10.1 Où la teinte officielle échoue — dit sans détour

Seuil applicable aux aplats et aux limites de forme : **3:1** (WCAG 1.4.11, non-texte).

| Paire | Ratio | Verdict |
|---|---|---|
| `#22B14C` (autorisé) vs `--c-calcaire` `#EDEEEC` | **2,42:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-fond` `#E6E7E1` | **2,26:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-terre` `#DEDFD9` | **2,10:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-vegetation` `#D6DBD3` | **2,00:1** | **ÉCHEC** |
| `#22B14C` vs `--c-carte-eau` `#CBD5D8` | **1,88:1** | **ÉCHEC** |
| `#E63A3C` (interdit) vs `--c-calcaire` | 3,58:1 | conforme |
| `#E63A3C` vs `--c-carte-fond` | 3,35:1 | conforme |
| `#E63A3C` vs `--c-carte-terre` | 3,11:1 | conforme (limite) |
| `#E63A3C` vs `--c-carte-vegetation` | **2,97:1** | **ÉCHEC** (de justesse) |
| `#E63A3C` vs `--c-carte-eau` | **2,79:1** | **ÉCHEC** |
| **`#22B14C` vs `#E63A3C`** — deux massifs voisins d'états opposés | **1,48:1** | **ÉCHEC — le plus grave de tous** |
| `--statut-indisponible` `#DEDFD9` vs `--c-carte-fond` `#E6E7E1` | **1,08:1** | **ÉCHEC** |
| `#22B14C` vs `--c-mistral-nuit` (**aplat de statut sur chrome sombre**) | 5,24:1 | conforme |
| `#E63A3C` vs `--c-mistral-nuit` | 3,53:1 | conforme |

**Constat, écrit noir sur blanc : la teinte officielle seule ne satisfait pas l'exigence AA du §8 du
brief.** Le vert échoue sur toutes les surfaces claires du système ; le rouge échoue sur la végétation et
sur l'eau ; et surtout, **deux massifs voisins d'états opposés ne se distinguent qu'à 1,48:1**, c'est-à-dire
pas du tout. C'est le cas le plus dangereux du site : sans traitement, on ne verrait pas où finit un massif
autorisé et où commence un massif interdit.

Le §4.2 du brief impose néanmoins de **reproduire la légende officielle**. Ces deux exigences ne sont pas
en conflit, parce que **ce n'est pas la teinte qui doit porter la conformité**.

### 10.2 Ce qui porte la conformité, mesuré : le liseré charbon

`--statut-lisere` = `--c-charbon` `#1A1C19`, sur **tout** polygone, **toute** pastille, **tout** jalon,
**sans exception**. Épaisseur **2 px** partout, **sauf sur la carte** où elle suit le palier de zoom
(§9.2.a) : les ratios de ce tableau valent pour **2 px et au-delà** ; le cas de **1,5 px** est mesuré
séparément au §10.2.a, parce qu'il ne se déduit pas de celui-ci.

| Le liseré contre… | Ratio | Verdict (seuil 3:1) |
|---|---|---|
| `#22B14C` (aplat autorisé) | **6,10:1** | conforme |
| `#E63A3C` (aplat interdit) | **4,11:1** | conforme |
| `--statut-indisponible` `#DEDFD9` | **12,80:1** | conforme |
| `--c-calcaire` `#EDEEEC` | **14,74:1** | conforme |
| `--c-carte-fond` `#E6E7E1` | **13,79:1** | conforme |
| `--c-carte-terre` `#DEDFD9` | **12,80:1** | conforme |
| `--c-carte-vegetation` `#D6DBD3` | **12,20:1** | conforme |
| `--c-carte-eau` `#CBD5D8` | **11,48:1** | conforme |
| **Minimum sur l'ensemble du système** | **4,11:1** | **conforme, avec 37 % de marge** |

**Le pire cas du §10.1 est résolu par ce seul mécanisme** : entre un massif vert et un massif rouge, le
liseré interpose une bande à 6,10:1 d'un côté et 4,11:1 de l'autre. La limite de forme est perceptible
partout, indépendamment de la teinte, indépendamment du daltonisme, et indépendamment du fond de carte.

Sur chrome sombre (`.sur-sombre` : ardoise, bandeau, barre d'action), le liseré bascule en
`--c-calcaire` : **12,66:1 contre `--c-mistral-nuit`**. La forme reste détachée du fond dans les deux
familles de contexte.

> **[v2.3] Ces deux mesures ne sont pas devenues sans objet.** Elles ont été écrites pour une rangée de
> marques retirée depuis (D-27), mais elles couvrent **tout aplat de statut posé sur chrome sombre** — et
> il en reste un, prévu et spécifié : la **barre d'action du portail** (§7.2), qui est un chrome
> `--c-mistral-nuit` portant des pastilles. **Le mot a disparu de ces sections ; la mesure y reste, parce
> qu'elle reste nécessaire.**

C'est pourquoi le liseré est déclaré **porteur d'accessibilité** et non décoratif : le supprimer,
**l'amincir sous son plancher mesuré (§10.2.a)**, l'arrondir au point de le manger dans les angles (§6.2)
ou le teinter est un défaut **bloquant**.

> **[v2.4] Amendement de la formule « jamais sous 2 px ».** Elle disait le vrai et le disait mal : elle
> énonçait **une valeur** là où la mesure produit **un seuil**. Elle est remplacée par : **« jamais sous
> 1,5 px, et 1,5 px au seul palier département de la carte ; 2 px partout ailleurs, sans exception. »**
> L'ancienne formule est **conservée telle quelle pour tout ce qui n'est pas un polygone de carte** —
> pastille, jalon, légende, liste, panneau, portail, impression : ces objets ont une taille fixe à l'écran,
> le rapport trait/forme y est constant, et aucun problème d'échelle ne s'y pose. **Le seul objet qui
> change d'échelle sous l'utilisateur est un polygone de carte.**

#### 10.2.a **[v2.4]** Le plancher de 1,5 px — dérivé, pas choisi

La question n'est pas « à partir de quelle épaisseur un trait est-il visible », c'est **« à partir de
quelle épaisseur un trait garantit-il son ratio »**. Un trait SVG n'est pas aligné sur la grille de pixels :
son tracé tombe où la projection le met. Au pire alignement sous-pixel, l'anticrénelage répartit l'encre
sur deux rangées et **aucune n'est pleine** — le contraste effectif est celui du charbon **mélangé à
l'aplat**, pas celui du charbon.

Couverture maximale garantie d'une rangée de pixels, pour un trait centré d'épaisseur *w*, au pire
alignement : *w* ≤ 1 → *w*/2 · *w* = 1,25 → 0,625 · *w* = 1,5 → 0,75 · ***w* ≥ 2 → 1,00**.
C'est la seule raison pour laquelle 2 px a toujours été le bon chiffre : **à 2 px, il existe toujours au
moins une rangée pleinement couverte, quel que soit l'alignement.** En dessous, il n'y en a plus, et il
faut mesurer le mélange.

Charbon `#1A1C19` mélangé à l'aplat officiel selon la couverture, contre l'aplat lui-même — seuil **3:1** :

| Épaisseur | Couverture garantie | vs `#E63A3C` (interdit) | vs `#22B14C` (autorisé) | Verdict |
|---|---|---|---|---|
| **≥ 2 px** | 100 % | **4,11:1** | **6,10:1** | conforme — marge 37 % |
| **1,5 px** | **75 %** | **3,18:1** | **4,07:1** | **conforme — marge 6 %** |
| 1,42 px | 71 % | **3,00:1** | — | **le plancher exact** |
| 1,25 px | 62,5 % | **2,66:1** | — | **ÉCHEC** |
| 1 px | 50 % | **2,18:1** | **2,49:1** | **ÉCHEC** |

**Le plancher tombe à 1,42 px. L'échelle du système travaille au demi-pixel. Donc 1,5 px, et rien entre les
deux.** Aucun jugement d'œil n'entre dans ce chiffre ; il se recalcule et il se conteste par la mesure.

Le liseré à 1,5 px contre les autres surfaces du système, à 75 % de couverture :

| Le liseré à 1,5 px contre… | Ratio | Verdict (seuil 3:1) |
|---|---|---|
| `--c-calcaire` `#EDEEEC` | **7,37:1** | conforme |
| `--c-carte-fond` `#E6E7E1` | **6,89:1** | conforme |
| `--c-carte-terre` / `--statut-indisponible` `#DEDFD9` | **6,40:1** | conforme |
| `--c-carte-vegetation` `#D6DBD3` | **6,10:1** | conforme |
| `--c-carte-eau` `#CBD5D8` | **5,74:1** | conforme |
| `#22B14C` (aplat autorisé) | **4,07:1** | conforme |
| `#E63A3C` (aplat interdit) | **3,18:1** | conforme |
| **Minimum au palier département** | **3,18:1** | **conforme, avec 6 % de marge** |

**Le pire cas du §10.1 reste résolu**, y compris au palier le plus mince : entre un massif vert et un
massif rouge voisins, le liseré interpose 4,07:1 d'un côté et 3,18:1 de l'autre, là où les deux aplats ne
se distinguent qu'à 1,48:1. **La séparation tient.**

**Le coût est écrit, il n'est pas minimisé.** La marge sur le pire cas passe de **37 % à 6 %**. C'est peu,
et c'est pourquoi 1,5 px est **borné à un seul palier**, est **le plancher absolu du système**, et ne peut
être atteint par aucun autre objet. Deux gardes en découlent, toutes deux au §16 : aucune valeur
d'épaisseur de trait de carte hors des jetons du §12 ; et le palier département est le seul endroit du
document où un ratio de statut passe sous 3,5:1 — s'il devait être encore réduit, c'est la mesure qui le
refuserait, pas le goût.

#### 10.2.b **[v2.4]** Le cerne et son séparateur

Le **cerne** est du `--c-charbon`, vu à **1,5 px au plus mince** (palier département) et posé avec un
`stroke-width` de **4,5 px au plus mince** — donc **toujours au-dessus du seuil de couverture pleine de
2 px** du §10.2.a. Il reprend par conséquent **intégralement** les ratios du §10.2, minimum **4,11:1**
contre le rouge officiel, et **aucun palier ne le met en dessous**. C'est voulu : le cerne est la partie du
dispositif de sélection qui **porte**, et il ne travaille jamais dans la marge où travaille le liseré.

Le **séparateur** `--c-calcaire` est mesuré, et le résultat est la raison pour laquelle il ne porte rien :

| Le séparateur calcaire contre… | Ratio | Lecture |
|---|---|---|
| `--c-charbon` — le cerne, à l'extérieur | **14,74:1** | c'est **ce qui le rend visible** |
| `--c-charbon` — le liseré du massif, à l'intérieur | **14,74:1** | idem, de l'autre côté |
| `--c-carte-fond` `#E6E7E1` | **1,07:1** | **invisible** sur le fond de carte |
| `--c-carte-vegetation` `#D6DBD3` | **1,21:1** | **invisible** |
| `--c-carte-eau` `#CBD5D8` | **1,29:1** | **invisible** |
| `#22B14C` (massif voisin autorisé) | **2,42:1** | **sous le seuil** |
| `#E63A3C` (massif voisin interdit) | 3,58:1 | conforme, mais **sans emploi** |

**Conclusion, à tenir en revue** : le séparateur est **encadré de charbon des deux côtés, à 14,74:1**, et
c'est la seule raison de sa présence. Il n'est **jamais** le trait extérieur du cerne, il ne borde
**jamais** directement le fond de carte ni un aplat voisin, et **aucune conformité ne repose sur lui**.
Un cerne rendu séparateur à l'extérieur — l'ordre de la v2.3 — serait un anneau qui **disparaît sur le fond
de carte à 1,07:1** : c'est littéralement le défaut constaté à l'écran, et l'inversion de l'ordre est ce
qui le corrige.

### 10.3 Ce qui porte l'indépendance à la couleur : le motif obligatoire

Une personne qui ne distingue pas le rouge du vert (deutéranopie, protanopie — environ 8 % des hommes)
verrait deux gris très proches. Le motif est donc le **second** canal, indépendant, et il est obligatoire.

| Motif | Encre | Sur | Ratio | Verdict |
|---|---|---|---|---|
| `hachure_croisee` (massif interdit) | `--c-charbon` | `#E63A3C` | **4,11:1** | conforme |
| `barre` (jalon ZAPEF interdit) | `--c-charbon` | `#E63A3C` | **4,11:1** | conforme |
| `hachure_croisee` sur chrome sombre | `--c-calcaire` | `#E63A3C` | **3,58:1** | conforme |
| `hachure_descendante` (indisponible) | `--c-charbon-doux` | `#DEDFD9` | **6,33:1** | conforme |
| `pointille` (non encore publié) | `--c-charbon-doux` | `#DEDFD9` | **6,33:1** | conforme |
| *(rejeté)* hachure en `--c-trace` `#9EA197` | `--c-trace` | `#DEDFD9` | **1,96:1** | **ÉCHEC — corrigé en v2.0** |

> **Correction issue de cette passe 3.** La v1.0 dessinait la hachure « indisponible » en `--c-trace`,
> par cohérence métaphorique avec la peinture ancienne du repère. Mesure faite : **1,96:1**, motif
> quasi invisible. `--c-trace` est **retiré de tous les motifs de statut** et confiné au décor (`::before`
> du repère, ombres décalées). La métaphore ne l'emporte pas sur une mesure.

**L'état `autorisé` n'a délibérément aucun motif.** Ce n'est pas un oubli : c'est l'opposition
« nu / marqué » qui encode l'information, exactement comme un panneau vierge s'oppose à un panneau barré.
Vouloir un motif « léger » sur l'autorisé affaiblirait l'écart. Mesuré, l'alternative ne tenait pas non
plus : `--c-calcaire` sur `#22B14C` ne fait que **2,42:1**, et `--c-charbon` sur `#22B14C` produirait un
vert visuellement assombri, donc une teinte officielle altérée.

**Troisième canal, toujours présent : le libellé.** Chaque pastille est accompagnée du libellé officiel
verbatim. Aucun statut n'est **jamais** encodé par la seule couleur, ni par couleur + motif sans texte.
Trois canaux indépendants : teinte, motif, mot.

### 10.4 Texte — pourquoi aucun mot ne se pose sur un aplat de statut

| Paire | Ratio | AA texte normal (4,5:1) |
|---|---|---|
| `--c-charbon` sur `#22B14C` | 6,10:1 | conforme — **mais interdit quand même** |
| `--c-charbon` sur `#E63A3C` | **4,11:1** | **ÉCHEC** |
| `#FFFFFF` sur `#E63A3C` | **4,17:1** | **ÉCHEC** |
| `--c-calcaire` sur `#E63A3C` | **3,58:1** | **ÉCHEC** |
| `--c-calcaire` sur `#22B14C` | **2,42:1** | **ÉCHEC** |
| **`--c-charbon` sur `--c-calcaire`** — la solution retenue | **14,74:1** | **conforme, très large** |

Aucune encre ne franchit 4,5:1 sur le rouge officiel. La règle « aucun texte sur un aplat de statut »
(§4.1.d règle 3) n'est donc pas une préférence graphique : c'est **la seule position tenable**, et elle
est uniforme pour les deux états afin qu'aucune exception ne s'installe. Les libellés vivent à côté de
l'aplat, sur `--c-calcaire`, à 14,74:1.

Corollaire : les jetons `--statut-*-encre` sont des **encres de motif**. Les employer comme `color` est un
défaut bloquant.

### 10.5 Anneau de focus — visible sur **chaque** surface de la palette

Seuil 3:1 contre la surface adjacente. Le double contour carte est réputé conforme si **au moins une** de
ses deux moitiés atteint 3:1.

| Surface | `--c-mistral-nuit` | `--c-calcaire` | `--c-charbon` | Verdict |
|---|---|---|---|---|
| `--c-calcaire` `#EDEEEC` | **12,66:1** | — | 14,74:1 | conforme |
| `--c-calcaire-ombre` `#DEDFD9` | **10,99:1** | — | 12,80:1 | conforme |
| `--c-carte-fond` `#E6E7E1` | 11,85:1 | 1,07:1 | **13,79:1** | conforme par le charbon |
| `--c-carte-vegetation` `#D6DBD3` | 10,48:1 | 1,21:1 | **12,20:1** | conforme par le charbon |
| `--c-carte-eau` `#CBD5D8` | 9,86:1 | 1,29:1 | **11,48:1** | conforme par le charbon |
| **`#22B14C`** (aplat autorisé) | 5,24:1 | 2,42:1 | **6,10:1** | conforme par le charbon |
| **`#E63A3C`** (aplat interdit) | 3,53:1 | 3,58:1 | **4,11:1** | conforme par les deux |
| `--c-mistral-nuit` `#0B2B3C` (chrome) | — | **12,66:1** | 1,16:1 | conforme par le calcaire |
| `--c-mistral` `#17567A` (bouton) | — | **6,81:1** | 2,16:1 | conforme par le calcaire |

> **[v2.1] Correction de la passe 3 ter.** Les 9 lignes ci-dessus ont été **recalculées une à une**, de
> façon indépendante. Six cellules bougent, toutes dans la colonne `--c-mistral-nuit` sauf une :
> `--c-calcaire-ombre` 10,93 → **10,99** · `--c-carte-fond` 11,79 → **11,85** · `--c-carte-vegetation`
> 10,43 → **10,48** · `--c-carte-eau` 9,82 → **9,86** · `#22B14C` 5,26 → **5,24** (alignement sur §10.1,
> qui était juste) ; et, seule erreur véritable, `--c-mistral` contre `--c-charbon` **1,35 → 2,16**, une
> erreur **conservatrice** qui sous-estimait le ratio. **Aucun verdict ne bascule** : la ligne
> `--c-mistral` reste « conforme par le calcaire » à 6,81:1, et 2,16:1 demeure sous le seuil de 3:1.
> Les cellules `--c-calcaire` 12,66 et `#E63A3C` 3,53 sont exactes ; **toute la colonne `--c-charbon`
> est exacte**. Recontrôle complet du §10 : **17 des 17 affirmations porteuses se reproduisent à
> l'identique**, dont le **pire cas à 4,11:1** et le **vert contre rouge à 1,48:1**. Le mécanisme de
> preuve est sain ; ce qui a été corrigé, ce sont des arrondis dans des cellules non porteuses.

**Aucune surface du système ne laisse le focus invisible.** Sur les surfaces claires et sur les deux aplats
officiels, c'est la moitié charbon qui porte ; sur le chrome sombre, la moitié calcaire. Le halo
`--c-mistral-clair` est un confort visuel, **jamais** le porteur du contraste — un halo seul serait
insuffisant sur plusieurs surfaces et ne doit jamais être livré sans son trait.

### 10.6 Indépendance à la couleur — règles fermes

1. **Aucun statut n'est jamais porté par la seule couleur.** Trois canaux obligatoires et simultanés :
   teinte + motif + libellé officiel en toutes lettres.
2. **Toute pastille est accompagnée de son libellé — [v2.3] sans aucune exception.** La seule exception que
   ce document ait jamais admise portait sur un objet retiré par **D-27** ; elle tombe avec lui. Une marque
   de statut sans libellé n'existe plus nulle part dans le système.
3. **La forme distingue les dimensions** : rectangle large = massif (surface), carré planté = ZAPEF
   (point). Un daltonien lit la dimension sans couleur ; un voyant lit l'état sans texte ; un lecteur
   d'écran lit les deux.
4. **Les trois états hors niveau ne sont jamais présentés comme des niveaux** : bloc de légende séparé,
   étiquette `SUR CE SITE`, phrases du §11.3.
5. **`forced-colors: active`** : les aplats passent en `Canvas`, les liserés et motifs en `CanvasText`, et
   **le libellé reste le porteur de sens** — c'est le seul mode où la teinte disparaît entièrement, et le
   site doit y rester intégralement compréhensible. À vérifier explicitement en revue.
6. **Zoom 200 % et 360 px** : aucun défilement horizontal, aucune pastille sous 12 px de haut, aucun
   libellé tronqué ni remplacé par une abréviation non explicitée.

### 10.7 [v2.1] Paires non mesurées jusqu'ici

Trois paires que la palette autorise et que les passes 3 et 3 bis n'avaient pas chiffrées. Les mesurer
ne change aucune valeur de jeton : cela **ferme trois angles morts** et transforme deux tolérances
implicites en règles écrites.

**1. Les traits du fond de carte — sous 3:1, accepté et argumenté.**

| Paire | Ratio | Seuil 1.4.11 |
|---|---|---|
| `--c-carte-trait` `#B4B7AC` vs `--c-carte-fond` `#E6E7E1` | **1,64:1** | échec |
| `--c-carte-trait` vs `--c-carte-terre` `#DEDFD9` | **1,52:1** | échec |

**Accepté**, et sans exception de complaisance : le filaire du fond de carte — routes, limites
administratives — **ne porte aucune information de statut**, et le §1.4.11 de WCAG ne s'applique qu'aux
éléments porteurs de sens. Un fond de carte contrasté serait d'ailleurs contraire au pari central du §1 :
il concurrencerait les aplats. **Corollaire ferme, qui est la contrepartie de cette acceptation** :
`--c-carte-trait` **ne doit jamais porter une limite qui compte**. Les limites de massif sont, et restent,
le **liseré `--c-charbon` 2 px** du §10.2. Un jour où quelqu'un aura l'idée de dessiner une frontière de
massif « en discret », c'est cette ligne-ci qui l'en empêche.

**2. Les toponymes contre les aplats officiels — pas une exception à accorder, un ordre à imposer.**

| Paire | Ratio | AA texte normal |
|---|---|---|
| `--c-carte-encre` `#4A4E48` sur `#22B14C` | **3,02:1** | **ÉCHEC** |
| `--c-carte-encre` sur `#E63A3C` | **2,03:1** | **ÉCHEC, sévère** |

C'est le même mur que le §10.4 : **aucune encre ne passe sur le rouge officiel**, et l'encre de carte y
plafonne encore plus bas que le charbon (2,03:1 contre 4,11:1). Il n'y a donc **rien à négocier au niveau
de la couleur** : la seule réponse disponible sur une carte est **l'ordre des couches**. D'où la règle
inviolable **§4.1.d n°8** : étiquettes cartographiques **sous** les aplats de statut, chrome de carte
flottant sur un **aplat opaque `--c-calcaire`**. Un toponyme intérieur à un massif est occulté ; c'est le
comportement voulu, pas une perte à compenser par un halo — un halo sur 2,03:1 ne rattrape rien.

**3. `--c-garrigue` sur `--c-calcaire-ombre` — 4,19:1, échec AA en texte normal.**

La valeur était déjà mesurée au §4.2, mais **aucune ligne de revue ne la faisait respecter**. Elle en a
une désormais (§16). Conséquence pratique : `--c-garrigue` **ne peut pas** servir de texte courant sur une
ligne alternée, dans un encart ou dans un slab `--c-calcaire-ombre`. Il reste conforme en **grand texte
≥ 24 px** et en **bordure** (`--bord-champ`, seuil 3:1). Sur `--c-calcaire`, il tient 4,83:1 et reste le
ton du texte tertiaire.

### 10.8 [v2.1] Exigence reportée aux chaînes d'intégration — `forced-colors: active`

Constat honnête, écrit plutôt que masqué : le §3.4 et le §10.6 règle 5 **exigent** un comportement en
couleurs forcées, mais le §12 **ne fournit ni jeton ni bloc** pour l'obtenir — et les motifs de statut
sont des `background-image` (`repeating-linear-gradient`), dont la survie sous couleurs forcées dépend de
l'implémentation. Or le motif porte **la moitié** de l'information (§10.3).

**Décision : aucun ajout au §12** (arbitrage A-4 de `docs/contracts/issue-4.md`). Ce n'est pas un problème
de jetons, c'est un problème de **règles de rendu** ; y répondre par un jeton spéculatif aurait modifié le
bloc normatif pendant que deux chaînes le lisent, sans bénéfice certain.

**Ce qui est exigé des chaînes d'intégration, et vérifiable en revue :** sous `forced-colors: active`, les
aplats passent en `Canvas`, les liserés et les motifs en `CanvasText` ; si un motif en dégradé n'y survit
pas, il est **remplacé par un mécanisme qui survit** (bordure, trait `currentColor`, `forced-color-adjust`
maîtrisé) — jamais supprimé. Et dans tous les cas, **le libellé officiel reste le porteur de sens**, ce
qui garantit que même un motif perdu ne rend aucun statut ambigu. À contrôler explicitement (§16).

---

## 11. Micro-rédaction

### 11.1 Règles de voix

1. **Voix active, sujet explicite.** « La préfecture publie les statuts vers 17 h », pas « les statuts sont publiés ».
2. **Le libellé nomme l'action**, jamais son mécanisme : « Publier les statuts », « Afficher les zones
   parcourues par le feu », « Fermer le panneau ». Interdits : « Valider », « OK », « Soumettre », « En savoir plus ».
3. **Les erreurs disent quoi faire, sans s'excuser.** « Choisissez un niveau pour chaque massif modifié. »
   Interdits : « Oups », « Désolé », « Une erreur est survenue ».
4. **Aucune promesse d'officialité.** Le site « relaie », « reprend », « d'après » — il ne « garantit » jamais.
5. **Aucun superlatif, aucune exclamation, aucun emoji, aucune icône seule** porteuse de sens.
6. **Dates et heures en français long** : « mardi 11 août 2026 », « 17 h 00 » (espace insécable, pas de `:`).
   Le thème ne compose **jamais** une date lui-même : il consomme `massifs_horodatage()`.
7. **Chiffres écrits en chiffres** dès qu'ils sont des données (« 12 massifs sur 25 »).
8. **[v2.0] Les libellés officiels sont reproduits mot pour mot, jamais paraphrasés, jamais corrigés,
   jamais abrégés.** Ni « Autorisé » seul, ni « Massif ouvert », ni « Accès autorisé » sans « au massif ».
   Les incohérences de la source sont conservées (§11.4). Une paraphrase d'un libellé officiel est un
   défaut bloquant, au même titre qu'une couleur inventée.

### 11.2 Vocabulaire fixe — un terme, un sens, partout

| Terme retenu | Sens | Ne jamais dire |
|---|---|---|
| **massif** | Le périmètre forestier du référentiel DDTM. Une **surface** | zone, secteur, espace, forêt |
| **niveau** | **[v2.0]** L'un des **deux** états d'accès publiés par la préfecture : autorisé ou interdit. Le terme est conservé parce que l'en-tête officiel est `Niveau d'Accès` | couleur, code, alerte, cran, palier |
| **ZAPEF** | Zone d'Accueil du Public en Forêt. Un **point**, une dimension **distincte** du niveau du massif | aire d'accueil, site, parking, aménagement |
| **statut** | L'enregistrement « ce massif, ce jour, ce niveau » | état, situation |
| **consigne** | Ce que le niveau impose au promeneur. **Non publiée à ce jour** (§4.1.e) | recommandation, conseil |
| **fraîcheur** | L'âge de la donnée affichée | actualité, mise à jour (en tant que nom) |
| **dispositif** | Le régime préfectoral estival, du 1er juin au 30 septembre inclus | système, plan, saison |
| **jour de validité** | Le jour auquel le statut s'applique | date, jour J |
| **carte officielle** | La carte de la préfecture | site officiel, source officielle |
| **zone parcourue par le feu** | Le polygone EFFIS | incendie, feu actif, zone brûlée |
| **danger météo** | L'indicateur Météo-France, à cinq crans, **sans rapport avec l'accès** | risque, alerte météo |
| **gestionnaire** | Le rôle qui met à jour les statuts | éditeur, modérateur, admin |
| **publier** | L'action d'enregistrer et de diffuser les statuts | valider, envoyer, sauvegarder |

**[v2.0] Termes explicitement bannis, hérités de l'hypothèse à 5 crans** : « niveau 1 » … « niveau 5 »,
« vigilance jaune / orange / noire », « risque sévère », « risque exceptionnel », « accès réglementé »,
« accès autorisé avec vigilance ». Aucun n'existe dans le dispositif des Bouches-du-Rhône. Le préfixe
« Vigilance » n'apparaît que dans le bulletin PDF (« Vigilance vert - … ») et **n'est pas notre référence** :
l'écran reproduit la formulation de la carte.

### 11.3 Chaînes fixes rédigées par le site (à reprendre mot pour mot)

> **[v2.5] Portée de cette liste — tranchée par le propriétaire du projet, à ne plus rouvrir.**
> **Le §11.3 borne le rendu PUBLIC, pas le portail.** C'est une **liste fermée** pour tout ce que voit le
> visiteur : sur une page publique, aucune phrase hors de cette liste et hors du §11.4 n'est rédigée, parce
> qu'une phrase inventée y passerait pour officielle. **Le portail gestionnaire n'est pas borné par elle** :
> il est interne, il ne parle qu'à un gestionnaire authentifié, et sa micro-rédaction relève du **§7 du
> brief** et des **règles de voix du §11.1**, qui s'y appliquent intégralement — voix active, libellé qui
> nomme l'action, erreur qui dit quoi faire sans s'excuser, vocabulaire fixe du §11.2.
>
> **Ce qui a rendu l'arbitrage nécessaire** : l'écran de publication (#14) écrit une vingtaine de chaînes
> de portail, et sa question Q-2 demandait si elles violaient une liste fermée. Elles ne la violent pas.
> **Le §7.2 le corroborait déjà en rédigeant lui-même « Publier les statuts » et « 7 statuts modifiés »** —
> deux chaînes de portail absentes de cette liste depuis la v1.0, qu'aucune revue n'a jamais comptées comme
> des défauts. Une liste qui se contredirait ainsi dans son propre document ne serait pas fermée, elle
> serait fausse.
>
> **Ce que la borne n'autorise pas pour autant, et qui reste opposable en revue** : (a) une chaîne de
> portail **reste** soumise au §11.1 et au §11.2 — « Valider » ou « Oups » y sont des défauts comme
> ailleurs ; (b) un **libellé officiel** affiché dans le portail reste reproduit **verbatim** (§11.4),
> sans exception ; (c) une chaîne de portail **regroupée en un seul fichier** est la condition de sa
> relecture — #14 la tient dans `messages.php` ; (d) toute chaîne qui **paraît sur une page publique**,
> quelle que soit la chaîne d'agents qui l'écrit, retombe sous cette liste fermée.

- Non-officialité (§5.6 du brief, obligatoire) : « Site d'information indépendant. Seules les publications
  de la préfecture des Bouches-du-Rhône font foi : [lien carte officielle]. »
- Fraîcheur : « Statuts du {jour de validité}, publiés la veille à {heure} par la préfecture — relevés sur
  ce site le {date} à {heure}. »
- Indisponible : « Information du jour non disponible. Consultez la carte officielle de la préfecture. »
- Hors saison : « Dispositif estival inactif. Reprise le {date}. »
- Non encore publié : « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. »
- Consigne absente (§8.4) : « Cette carte ne publie pas de consigne détaillée. L'arrêté préfectoral en
  vigueur fait foi : [lien]. »
- EFFIS : « Périmètres estimés par satellite (feux d'environ 30 ha et plus). Zone déjà parcourue par le
  feu, ce n'est pas un périmètre officiel d'interdiction. »
- Attribution des statuts (§9 du brief, verbatim, **jamais rédigée à la main par le thème** — elle vient de
  l'extension) : « D'après les publications de la préfecture des Bouches-du-Rhône ».

### 11.4 Chaînes **officielles** reproduites verbatim — ne jamais éditer

Ces huit chaînes appartiennent à la préfecture. Elles sont fournies par l'extension et rendues telles
quelles. **Toute modification, y compris orthographique, est un défaut bloquant.**

| Emploi | Chaîne exacte | Piège |
|---|---|---|
| État de massif | `Accès au massif autorisé` | — |
| État de massif | `Accès au massif interdit` | — |
| État de ZAPEF | `Accès à la ZAPEF* autorisé` | **`autorisé` au masculin** — c'est la source |
| État de ZAPEF | `Accès à la ZAPEF* interdite` | **`interdite` au féminin** — c'est la source |
| Note de légende | `*ZAPEF : Zones d’Accueil du Public en Forêt` | apostrophe **typographique U+2019** |
| Titre de légende | `Légende de la carte` | — |
| En-tête de colonne | `Niveau d'Accès` | apostrophe **droite U+0027**, majuscule à `Accès` |

> **Les deux apostrophes divergent volontairement.** `Zones d’Accueil` porte U+2019 ; `Niveau d'Accès`
> porte U+0027. C'est ce que publie la source. Une « uniformisation typographique » — réflexe naturel
> d'un intégrateur consciencieux — casserait la reproduction fidèle exigée par le §4.2 du brief.
> Ces chaînes doivent survivre à toute passe de nettoyage, de linter et de correcteur orthographique.
> Le sous-ensemble de police doit contenir `’` et `*` (§5).

---

## 12. Jetons CSS — contenu exact de `assets/css/tokens.css`

À recopier **tel quel**. Aucun autre fichier ne définit de custom property ; aucune valeur littérale de
couleur, d'espacement ou de durée n'apparaît ailleurs dans le CSS. Ce bloc est la **charge utile
normative** attendue par la chaîne front.

```css
/* MASSIFS — jetons du design system. Voir design-system/MASTER.md (v2.4).
   Ne pas ajouter de valeur hors échelle. Ne pas redéfinir hors :root, sauf
   les TROIS exceptions documentées : --repere-couleur (§3.1), le groupe
   liseré/encre sous .sur-sombre, et les épaisseurs de trait de la carte sous
   les classes de palier de zoom (§9.2.a) — les deux dernières en fin de
   fichier. */

:root {
  /* ── Surfaces et encres ─────────────────────────────────── */
  --c-calcaire:        #EDEEEC;
  --c-calcaire-ombre:  #DEDFD9;
  --c-poussiere:       #C3C5BC;
  --c-trace:           #9EA197;   /* décor uniquement — jamais un motif de statut */
  --c-garrigue:        #5F6B5A;
  --c-charbon-doux:    #4A4E48;
  --c-charbon:         #1A1C19;
  --c-mistral-nuit:    #0B2B3C;
  --c-mistral:         #17567A;
  --c-mistral-clair:   #8FC3DD;   /* interdit sur fond clair : 1,64:1 */

  /* ── Fond de carte monochrome ───────────────────────────── */
  --c-carte-fond:       #E6E7E1;
  --c-carte-terre:      #DEDFD9;
  --c-carte-vegetation: #D6DBD3;
  --c-carte-eau:        #CBD5D8;
  --c-carte-trait:      #B4B7AC;
  --c-carte-encre:      #4A4E48;

  /* ══ STATUTS OFFICIELS — REPRODUITS, NON MODIFIABLES ══════════════════
     Source : docs/decisions/source-prefecture.md §4.2 et §4.3.
     Deux états d'accès au massif + une dimension ZAPEF indépendante.
     Relevé au pixel sur les pastilles de légende publiées par la préfecture.
     NE JAMAIS harmoniser, éclaircir, désaturer ni « adapter » ces valeurs.
     NE JAMAIS créer de jeton numéroté (--statut-1…) : un jeton numéroté
     réutilisé après un changement de légende repeint des massifs interdits
     dans la mauvaise couleur, en silence. Un jeton sémantique manquant ne
     produit aucune couleur : l'échec est bruyant, donc sûr.               */

  --statut-autorise:        #22B14C;   /* Accès au massif autorisé  */
  --statut-interdit:        #E63A3C;   /* Accès au massif interdit  */
  --statut-zapef-autorise:  #22B14C;   /* Accès à la ZAPEF* autorisé */
  --statut-zapef-interdit:  #E63A3C;   /* Accès à la ZAPEF* interdite */

  /* Encres des MOTIFS posés sur l'aplat — jamais des encres de texte :
     aucun texte n'est posé sur un aplat de statut (§10.4). */
  --statut-autorise-encre:        var(--c-charbon);
  --statut-interdit-encre:        var(--c-charbon);
  --statut-zapef-autorise-encre:  var(--c-charbon);
  --statut-zapef-interdit-encre:  var(--c-charbon);

  /* États HORS NIVEAU — des absences d'information, pas des niveaux.
     Ceux-ci nous appartiennent : aucune couleur officielle en jeu. */
  --statut-indisponible:        var(--c-calcaire-ombre);
  --statut-indisponible-encre:  var(--c-charbon-doux);  /* 6,33:1 */
  --statut-hors-saison:         var(--c-calcaire-ombre);
  --statut-hors-saison-encre:   var(--c-charbon-doux);
  --statut-non-publie:          var(--c-calcaire-ombre);
  --statut-non-publie-encre:    var(--c-charbon-doux);

  /* Le liseré porte la conformité AA, pas la teinte (§10.2).
     Minimum mesuré sur tout le système : 4,11:1 (charbon sur #E63A3C).
     [v2.4] --statut-lisere-epaisseur est l'épaisseur HORS CARTE : pastille,
     jalon, légende, liste, panneau, portail, impression. Ces objets ont une
     taille fixe à l'écran. Sur la carte, l'épaisseur suit le palier de zoom :
     voir le bloc « Épaisseurs de trait de la carte » ci-dessous. */
  --statut-lisere:            var(--c-charbon);
  --statut-lisere-epaisseur:  2px;
  --statut-motif-trait:       2.5px;
  --statut-motif-pas:         10px;

  /* ══ ÉPAISSEURS DE TRAIT DE LA CARTE — variables par palier de zoom ═══════
     §9.2.a. Un trait SVG est CENTRÉ : la moitié de son épaisseur tombe dans
     l'aplat. Une épaisseur constante ne peut donc pas servir à la fois le
     département vu en entier (z9) et un massif vu de près (z11).
     Les valeurs ci-dessous sont celles du palier « massif » (z10) : c'est le
     MILIEU de l'échelle, et c'est délibérément le défaut — une carte sans
     classe de palier se rend dans un état conforme, jamais sans trait.
     Les deux paliers extrêmes sont en fin de fichier (exception n° 3).
     PLANCHER MESURÉ, ne jamais descendre en dessous : 1.5px (§10.2.a).       */

  --carte-lisere:       2px;   /* liseré de statut du polygone, centré        */
  --carte-survol:       3px;   /* survol : 1,5 × le liseré du même palier     */
  --carte-cerne:        9px;   /* cerne charbon, SOUS le polygone (§9.2.a)    */
  --carte-cerne-clair:  5px;   /* séparateur calcaire, SOUS le polygone       */

  /* Liseré de l'état « sélectionné » dans le CHROME — bouton de jour de la
     carte, paire segmentée du portail (§7.2, §9.2). Sans rapport avec la
     carte : ces objets ne changent pas d'échelle. Ce n'est pas --bord-fort,
     qui est une abréviation « 4px solid … » plafonnée à une occurrence par
     page (§6.3, §16) et inutilisable comme simple épaisseur. */
  --bord-selection: 4px;

  /* ── Typographie ────────────────────────────────────────── */
  --police-titre: "Big Shoulders Display", "Arial Narrow", sans-serif;
  --police-texte: "Atkinson Hyperlegible Next", system-ui, sans-serif;

  --fs-100: 0.8125rem;
  --fs-200: 0.9375rem;
  --fs-250: 0.8125rem;
  --fs-300: 1.0625rem;
  --fs-400: 1.1875rem;
  --fs-500: clamp(1.375rem, 1.2rem + 0.9vw, 1.75rem);
  --fs-600: clamp(1.75rem,  1.4rem + 1.8vw, 2.5rem);
  --fs-700: clamp(2.25rem,  1.6rem + 3.2vw, 3.75rem);
  --fs-800: clamp(3.5rem,   2rem   + 7.5vw, 8rem);

  --lh-affiche: 0.92;
  --lh-titre:   1.08;
  --lh-sous:    1.15;
  --lh-dense:   1.35;
  --lh-corps:   1.6;

  --ls-affiche:   -0.01em;
  --ls-titre:      0.01em;
  --ls-etiquette:  0.08em;

  --poids-titre: 700;
  --poids-affiche: 800;
  --poids-texte: 400;
  --poids-texte-fort: 700;

  --mesure: 68ch;
  --mesure-etroite: 46ch;

  /* ── Espacement ─────────────────────────────────────────── */
  --esp-3xs: 2px;  --esp-2xs: 4px;  --esp-xs: 8px;  --esp-s: 12px;
  --esp-m: 16px;   --esp-l: 24px;   --esp-xl: 32px; --esp-2xl: 48px;
  --esp-3xl: 64px; --esp-4xl: 96px;
  --esp-section: clamp(48px, 6vw, 96px);
  --gouttiere: var(--esp-m);
  --largeur-max: 1200px;

  /* ── Rayons, bordures, élévation ────────────────────────── */
  --r-0: 0;
  --r-1: 2px;      /* champs et boutons seulement — jamais une pastille */
  --bord-fin:   1px solid var(--c-poussiere);
  --bord-champ: 2px solid var(--c-garrigue);
  --bord-moyen: 2px solid var(--c-charbon);
  --bord-fort:  4px solid var(--c-charbon);
  --ombre-0: none;
  --ombre-decalee: 3px 4px 0 var(--c-trace);
  --ombre-decalee-sombre: 3px 4px 0 var(--c-mistral);

  /* ── Signature ──────────────────────────────────────────── */
  --repere-largeur: 8px;
  --repere-decalage-x: 3px;
  --repere-decalage-y: 4px;
  --repere-couleur: var(--c-mistral-nuit);

  /* ── Pastilles, jalons, frise ───────────────────────────── */
  --pastille-l: 26px;   --pastille-h: 16px;
  --jalon-cote: 18px;   --jalon-hampe: 8px;
  --frise-l: 14px;      --frise-h: 10px;

  /* ── Focus ──────────────────────────────────────────────── */
  --focus-trait: var(--c-mistral-nuit);
  --focus-trait-inverse: var(--c-calcaire);
  --focus-halo: var(--c-mistral-clair);
  --focus-epaisseur: 3px;
  --focus-ecart: 2px;
  --focus-halo-epaisseur: 6px;

  /* ── Cibles ─────────────────────────────────────────────── */
  --cible-min: 2.75rem;   /* 44px */

  /* ── Mouvement ──────────────────────────────────────────── */
  --duree-court: 120ms;
  --duree-moyen: 200ms;
  --duree-long:  320ms;
  --ease-net:     cubic-bezier(0.2, 0, 0, 1);
  --ease-retrait: cubic-bezier(0.4, 0, 1, 1);

  /* ── Points de rupture (documentaires : @media n'accepte pas les vars) ── */
  --bp-s: 37.5rem;   /* 600px  */
  --bp-m: 56.25rem;  /* 900px  */
  --bp-l: 80rem;     /* 1280px */

  /* ── Plans (Leaflet occupe 200–1000) ────────────────────── */
  --z-carte: 0;
  --z-panneau: 1100;
  --z-barre-action: 1200;
  --z-bandeau: 1300;
  --z-evitement: 1400;
}

/* Exception documentée n° 2 : sur chrome sombre, le liseré et les encres de
   motif basculent en calcaire. Les TEINTES officielles ne changent jamais.
   Mesuré : liseré calcaire sur mistral-nuit 12,66:1 ; hachure calcaire sur
   #E63A3C 3,58:1. Voir §10.2 et §10.3. */
.sur-sombre {
  --statut-lisere:                var(--c-calcaire);
  --statut-autorise-encre:        var(--c-calcaire);
  --statut-interdit-encre:        var(--c-calcaire);
  --statut-zapef-autorise-encre:  var(--c-calcaire);
  --statut-zapef-interdit-encre:  var(--c-calcaire);
}

/* [v2.4] Exception documentée n° 3 : les épaisseurs de trait de la carte
   suivent le PALIER DE ZOOM (§9.2.a). Le JS de la carte n'a pas le droit
   d'écrire un style — seulement classList — donc une épaisseur fonction du
   zoom se pose forcément par une classe de palier sur la racine et par des
   règles CSS. Le JS lit carte.getZoom() et pose l'une de ces classes ; toutes
   les valeurs sont ICI, aucune en JS, aucune en littéral dans carte.css.
   Le palier « massif » (z10) ne porte AUCUNE règle : ses valeurs sont celles
   de :root. Ne pas en écrire une vide — l'absence est la décision. */

.carte--echelle-departement {   /* z ≤ 9 — le département entier */
  --carte-lisere:       1.5px;  /* PLANCHER MESURÉ : 3,18:1 sur #E63A3C */
  --carte-survol:       2.5px;
  --carte-cerne:        4.5px;
  --carte-cerne-clair:  0;      /* aucune peinture claire à ce palier */
}

.carte--echelle-abords {        /* z ≥ 11 — plafond de la carte */
  --carte-lisere:       3px;
  --carte-survol:       4.5px;
  --carte-cerne:        13px;
  --carte-cerne-clair:  7px;
}

@media (min-width: 37.5rem) { :root { --gouttiere: var(--esp-l); } }

@media (prefers-reduced-motion: reduce) {
  :root { --duree-court: 0.01ms; --duree-moyen: 0.01ms; --duree-long: 0.01ms; }
}
```

> Convention de nommage : **les noms de jetons sont en ASCII pur**, sans accent ni caractère spécial
> (`--statut-autorise`, `--esp-…`, `--duree-…`). Les accents ne vivent que dans la documentation.

### 12.1 Ce qui a disparu de `tokens.css` en v2.0 — et pourquoi on ne le réintroduit pas

| Jeton supprimé | Raison |
|---|---|
| `--statut-1` … `--statut-5` et `--statut-N-encre` | Numérotation bannie (§4.1.d règle 7). `--statut-2` valait un **jaune** : réutilisé pour un état « interdit », il aurait peint des massifs fermés en jaune sans qu'aucun test ne le voie |
| `--statut-lisere-n5` | Le niveau 5 n'existe pas. Le liseré est unique et bascule par contexte, pas par niveau |
| `--c-pin-alep` | H 146°, S 25 % : viole la règle §2.1 s'il est peint. Reste un ingrédient de mélange documenté, dont le produit `--c-carte-vegetation` est, lui, un jeton |

#### [v2.3] Jetons **déclarés et consommés par personne** — ils restent dans `tokens.css`

Rubrique distincte de la précédente, et il faut lire la différence : ci-dessus, des jetons **supprimés** ;
ici, des jetons **conservés que plus aucune règle ne consomme**.

| Jeton | Ce qui le laisse sans consommateur | Statut |
|---|---|---|
| `--ombre-decalee` | Direction du propriétaire : le panneau massif et le bloc de légende ne portent pas d'ombre dans le code livré ; le **filet 2 px en tête de bande** et le repère font la mise en volume (§8.5, §17 divergence 1) | **déclaré, orphelin** |
| `--ombre-decalee-sombre` | Même décision, variante sur chrome sombre | **déclaré, orphelin** |
| `--frise-l` | **D-27** — l'objet qu'il dimensionnait est retiré | **déclaré, orphelin** |
| `--frise-h` | **D-27** — idem | **déclaré, orphelin** |

> **[v2.4] Ce que la v2.4 fait à cette règle : rien. Elle n'en supprime aucun.** Les quatre jetons
> orphelins ci-dessus **le restent** ; `--ombre-decalee` et `--ombre-decalee-sombre` en particulier ne sont
> **pas** ressuscités par le cerne, qui n'est pas une ombre et n'en emprunte ni la forme ni le décalage.
> La v2.4 **ajoute** cinq jetons et **n'en renomme aucun**. Lire la note de compte ci-dessous : la règle
> « on ne supprime pas » n'a jamais dit « on n'ajoute jamais », et la différence est écrite plus bas.

> **Règle générale du projet, énoncée ici une fois pour toutes : on ne supprime pas un jeton — on cesse de
> le consommer.** Deux raisons, et la première suffit. **(a)** Le **sha256 de `tokens.css` est épinglé** et
> son **compte de propriétés est un invariant du contrat #4**. Compte **vérifié au shell sur le fichier
> réel** : **111 déclarations de custom properties dans `:root`** — c'est l'invariant —, plus **5 sous
> `.sur-sombre`** et **4 dans les deux `@media`**, soit **120 dans le fichier entier**. Retirer une
> déclaration casse les deux, et transforme une décision de design en incident d'intégration pour des
> chaînes qui n'ont rien demandé. *(L'empreinte n'est pas recopiée ici : ce document ne duplique jamais un
> hachage — §5, même règle que pour les polices. Le fichier et le contrat #4 font foi.)* **(b)** Un jeton orphelin ne coûte rien — quelques octets, aucun rendu, aucune cascade ; un
> jeton supprimé puis re-nécessaire revient sous un autre nom, ou sous la même valeur écrite en dur
> ailleurs, ce qui est précisément l'accident que le §4.1.d règle 6 interdit.
>
> **Ce que cette règle n'autorise pas** : conserver une **prescription** qui décrit un rendu inexistant.
> Le jeton reste ; la phrase qui affirmait au présent qu'un composant le porte est corrigée (§8.5). Le
> §6.4 garde le jeton, sa valeur et sa raison d'être — il redeviendra consommable sans rien redessiner.
>
> **Cas signalé et volontairement non modifié** : le **§9.2** prescrit encore, à l'état « Actif »,
> « `--ombre-decalee` réduite à `2px 3px 0` ». Cet état concerne des composants **qui n'existent pas
> encore** ; la phrase n'est donc fausse sur rien de livré, et la réécrire aujourd'hui reviendrait à
> arbitrer par avance le jour où une ombre reviendra. Elle est signalée, pas touchée.

#### [v2.4] Le §12 est ouvert — cinq jetons ajoutés, un invariant remplacé, et pourquoi c'était la seule voie

Depuis la v2.0, ce document n'avait **pas rouvert** le §12. Deux révisions s'en sont explicitement
abstenues, et **elles avaient raison** : la v2.1, la v2.2 et la v2.3 auraient payé une préférence
esthétique par un incident d'intégration chez des chaînes sœurs (§17, divergences 3 et 6 : deux jetons
**non créés** pour cette raison exacte). **Ce cas-ci est d'une autre nature, et la différence doit être
écrite, sinon la prochaine révision citera celle-ci pour ouvrir le §12 par confort.**

| | Divergences 3 et 6 (jetons refusés) | v2.4 (jetons créés) |
|---|---|---|
| Ce qui manquait | Une échelle `pt` non vérifiée sur épreuve ; un interligne 1,2 | Une épaisseur de trait par palier de zoom |
| Ce qu'on perdait sans | Une intention de composition | **Un aplat de statut recouvert à l'écran, et un massif illisible** |
| Existe-t-il une autre voie ? | Oui : consommer un jeton voisin, ou ne rien faire | **Non.** Le JS ne peut pas écrire de style ; la valeur doit vivre en CSS et varier par classe |
| Nature du défaut | Écart cosmétique | **Perte d'information cartographique, constatée en Chrome** |

**Les cinq jetons** : `--carte-lisere`, `--carte-survol`, `--carte-cerne`, `--carte-cerne-clair`,
`--bord-selection`. **Aucune valeur de couleur n'est touchée. Aucun jeton n'est supprimé ni renommé.
Aucune section du §12 n'est réécrite** — les cinq déclarations et les deux classes de palier sont des
**ajouts**, insérés à leur rubrique.

**L'invariant de compte change, et il change en étant déclaré.** Il valait **111 déclarations dans
`:root`** et **120 dans le fichier** (contrat #4). Il vaut désormais :

| | Avant (v2.0 → v2.3) | Après (v2.4) |
|---|---|---|
| `:root` | 111 | **116** |
| `.sur-sombre` | 5 | 5 |
| `.carte--echelle-departement` | — | **4** |
| `.carte--echelle-abords` | — | **4** |
| Les deux `@media` | 4 | 4 |
| **Fichier entier** | **120** | **133** |

Le **sha256 épinglé de `tokens.css` tombe avec lui** : c'est la conséquence mécanique et **acceptée** de
cette révision, pas un effet de bord découvert après coup. *(L'empreinte n'est pas recopiée ici : ce
document ne duplique jamais un hachage — même règle qu'au §5 pour les polices. Le fichier et le contrat
font foi.)* **Le contrat #4 doit être amendé par sa chaîne, jamais par ce document** : la liste complète de
ce que la v2.4 ouvre en aval est au **§17.1**. Un compte modifié en silence serait exactement ce que la
règle « on ne supprime pas un jeton » cherchait à éviter — l'incident d'intégration non annoncé. **Ici il
est annoncé, chiffré et daté.**

**Correspondance avec le contrat de l'issue #3 — [v2.1] corrigée.** L'extension émet **quatorze** noms de
jetons, par les clés `jeton_css` / `jeton_encre_css` de `legende.config.php` : `--statut-autorise`,
`--statut-interdit`, `--statut-zapef-autorise`, `--statut-zapef-interdit`, `--statut-indisponible`,
`--statut-hors-saison`, `--statut-non-publie`, et les sept `-encre` correspondants. Tous existent dans le
bloc précédent.

**`--statut-lisere` n'en fait pas partie** — la v2.0 le rangeait par erreur parmi eux. Il n'est émis par
**personne** côté extension : avec `--statut-lisere-epaisseur`, `--statut-motif-trait` et
`--statut-motif-pas`, il forme les **quatre jetons de statut que le thème possède seul** (18 déclarés dans
`tokens.css` = 14 émis + 4 propres au thème). La distinction n'est pas comptable : `--statut-lisere` est
précisément celui qui **porte la conformité AA** (§10.2), et le chercher côté extension le jour d'une
panne ferait perdre du temps là où il ne faut pas en perdre.

**Trois jetons sont ajoutés par ce document et doivent être reflétés dans `legende.config.php`** pour la
clé `etats_hors_niveau` : `--statut-hors-saison`, `--statut-non-publie`, et les `-encre` associés. Ce ne
sont pas des données officielles — ce sont nos états, et leur nommage m'appartient. Motifs attendus côté
configuration : `indisponible` → `hachure_descendante`, `hors_saison` → `aucun`,
`non_encore_publie` → `pointille`. Pour la dimension ZAPEF : `autorise` → `aucun`, `interdit` → `barre`.

---

## 13. Impression

La page imprimée est un **livrable en soi** (§5.3 du brief : « imprimable proprement ») : c'est la feuille
qu'on affiche au gîte ou à la mairie. **La légende binaire la rend nettement meilleure** : deux états
s'impriment sans ambiguïté en noir et blanc, là où cinq crans gris auraient été indiscernables.

```
@page { margin: 12mm; }
```

- Fonds convertis : `--c-calcaire` → blanc, `--c-mistral-nuit` → blanc avec `--bord-fort` en haut et texte noir.
- **La carte interactive n'est pas imprimée** (`display: none`) : elle est remplacée par l'image statique du
  département si elle est disponible, sinon par rien. **La liste du jour est imprimée intégralement**, en
  tableau à filets 0,5 pt, `page-break-inside: avoid` sur chaque ligne, en-tête de tableau répété
  (`thead { display: table-header-group; }`), colonne d'état intitulée `Niveau d'Accès`.
- **Les pastilles s'impriment en noir et blanc, et restent lisibles sans couleur** :
  - `autorisé` → aplat **blanc**, liseré 1,5 pt noir, aucun motif ;
  - `interdit` → hachure croisée noire, liseré 1,5 pt noir ;
  - `indisponible` → hachure descendante grise 45 %, liseré 1,5 pt noir.
  Le libellé officiel accompagne toujours la pastille. La couleur n'est donc **jamais** nécessaire à la
  compréhension d'une page imprimée en niveaux de gris.
  `print-color-adjust: exact` uniquement sur les pastilles et les jalons, pour préserver les motifs.
- **Toujours imprimés** : le titre, le jour de validité, la ligne de fraîcheur, le bandeau de non-officialité,
  la légende complète (quatre entrées + note ZAPEF), les attributions (§9 du brief).
- Les liens de contenu voient leur URL dépliée (`a[href^="http"]::after { content: " (" attr(href) ")"; }`),
  sauf dans les menus et le pied, masqués à l'impression.
- Le repère s'imprime : `::after` noir, `::before` gris 45 %. C'est la signature de la feuille papier.
- Corps à 10,5 pt / 1,45 ; `h1` 20 pt ; `h2` 14 pt ; le chiffre du jour à 34 pt.

> **[v2.3] Quatre écarts entre cette section et la feuille d'impression livrée sont enregistrés au §17** —
> échelle `pt` non écrite (divergence 3), « gris 45 % » rendu par `--statut-indisponible-encre`
> (divergence 4), repère non forcé à l'impression (divergence 5), filet de 4 px en tête de première page
> (divergence 7). Chacun est **décidé et argumenté** ; aucun n'est un défaut, et `print.css` est **gelé**.
> Cette section n'est pas réécrite pour autant : elle reste l'intention, le §17 dit où le rendu s'en écarte
> et pourquoi.

---

## 14. Autocritique

### 14.1 Passe 2 — v1.0, conservée intégralement

Méthode : chaque décision de la passe 1 a été soumise à quatre questions — *l'aurais-je produite pour
n'importe quel site carto ? tombe-t-elle dans un tell « design IA » ? l'audace est-elle unique et tenue ?
la palette vient-elle du sujet ou d'un nuancier ?*

| Décision | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Fond de carte monochrome calcaire** | Générique ? | **Non.** C'est l'inverse du réflexe carto (fond OSM standard coloré, polygones translucides par-dessus). | Conservé, **promu au rang de pari central** (§1). |
| **Règle de non-collision chromatique** | Générique ? | Non — aucun design system générique ne s'interdit le vert « succès » et le rouge « erreur ». | Conservée. Ni bouton vert ni erreur rouge. |
| **Palette crème + serif + terracotta** | Tell IA §7 ? | **Oui, refusé.** C'était le réflexe « Provence ». | **Refait** : calcaire tiré vers le froid/vert (`#EDEEEC`, pas `#F5F0E6`), terracotta bannie, aucun serif. |
| **Noir + accent acide** | Tell IA §7 ? | **Oui, évité.** | Encre `#1A1C19` (charbon, jamais `#000`), accent bleu froid rabattu. |
| **Look journal à filets fins** | Tell IA §7 ? | **Oui, évité.** | Les filets porteurs de sens font 2 px et 4 px ; le 1 px est cantonné aux séparateurs. |
| **Cartes arrondies sur fond gris** | Kit UI générique ? | **Oui, refusé.** | `border-radius` plafonné à **2 px** (§6.2). Aucun composant « card ». |
| **Le repère (signature)** | Une seule audace ? | Oui — métaphore du produit, pas ornement. | Conservé, **liste fermée de 7 emplacements** + liste d'interdits. |
| **Ombres décalées non floues** | Deuxième idée non reliée ? | **Risque réel — corrigé.** | Décalage **exactement** celui du repère `(3px, 4px)`, couleur `--c-trace`. Limité à 2 composants. |
| **Ombres décalées** | Tell « néo-brutalisme » ? | **Risque réel.** | **Différencié volontairement** : décalage 3–4 px, jamais noir, palette minérale désaturée, typo civique. Choix délibéré, justifié. |
| **Big Shoulders Display** | Typo « par défaut » ? | Non : les réflexes sont Oswald, Anton, Roboto Condensed. | Conservée + vérification des capitales accentuées. |
| **Atkinson Hyperlegible Next** | Choix esthétique gratuit ? | Non : accessibilité bloquante, typo pour la basse vision. | Conservée + repli documenté (Public Sans variable). |
| **Bleu mistral en chrome** | Bleu « corporate » ? | **Risque.** | **Rabattu et refroidi**, employé en aplats pleine largeur, jamais en petites touches. |
| **Équivalent textuel** | Traité en repli ? | **Oui au premier jet — corrigé.** | **Refait** : `h2`, repère, pleine largeur. Second héros, pas note de bas de page. |
| **Animation « mistral »** | Idée dispersée ? | **Oui, supprimée.** | Le mistral vit dans la palette, pas dans le mouvement. |
| **Module météo** | Fusionné avec le statut ? | Risque de confusion identifié. | **Sans aucune couleur**, visuellement étranger au reste. |
| **Couleurs de statut** | Inventées ? | **Non — et c'était bloquant.** | Substituts marqués `À CONFIRMER` + 8 questions précises. **C'est cette discipline qui a rendu la v2.0 possible sans refonte.** |

### 14.2 Passe 2 bis — v2.0, après l'établissement de la légende réelle

Nouvelle passe complète sur ce que la révision introduit ou modifie. Mêmes quatre questions, plus une
cinquième, propre à cette révision : *est-ce que je profite d'une simplification pour ajouter de la
décoration ?*

| Décision v2.0 | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Reproduire `#22B14C` / `#E63A3C` sans les retoucher** | Un designer ne devrait-il pas « corriger » ces teintes criardes ? | **Non — et la tentation était forte.** Ce vert et ce rouge sont typiques d'un nuancier logiciel des années 2000, pas d'un choix chromatique. Les désaturer aurait « embelli » la carte. | **Reproduits à l'identique.** §4.2 du brief. Ce ne sont pas mes couleurs, et le seul endroit du site où je n'ai pas le droit de dessiner est précisément celui qui compte. La palette du site, elle, est entièrement mienne — et c'est son écart minéral avec ces deux teintes qui les fait exister. |
| **Deux états au lieu de cinq** | Est-ce un appauvrissement visuel ? | **Non — c'est le contraire.** Cinq aplats sur une carte donnent une mosaïque à déchiffrer ; deux donnent une réponse lisible à quatre mètres. | Complexité libérée **réinvestie dans la lisibilité de loin** : aplats opaques, liseré 2 px, hachure grossie de 2 px/pas 6 px à 2,5 px/pas 10 px, frise de 25 marques. **Zéro ornement ajouté.** |
| **La frise des 25 marques** | Est-ce une deuxième audace, une décoration ? | **Risque réel, examiné sérieusement.** | **Conservée, mais bornée** : elle n'introduit **aucune forme nouvelle** (c'est la pastille du §8.1, réduite), elle est **de la donnée**, pas du décor, et elle est cantonnée à **un seul emplacement**, `aria-hidden`, immobile, et absente si la journée n'est pas connue. Si un doute subsiste en revue, elle est le premier élément à retirer — et le système tient sans elle. |
| **Deux silhouettes : rectangle massif / carré planté ZAPEF** | Idée dispersée ? Un troisième langage de forme ? | **Non.** C'est une contrainte de domaine, pas une envie : le `level` 3 affiche un jalon vert sur un massif rouge. | Même vocabulaire (rectangle, angle vif, liseré 2 px, `--r-0`) ; seule la **proportion** distingue surface et point. Aucun pictogramme, aucune icône, aucun jeu de formes libre. |
| **Le repère non étendu aux jalons ZAPEF** | Incohérence ? | **Non, discipline.** | La liste des 7 emplacements **n'est pas allongée**. Un décalage de 3–4 px sur un objet de 18 px détruirait la silhouette. La cohérence de façade aurait dilué la signature. |
| **Re-dérivation de la règle chromatique** | Ai-je juste retouché des chiffres ? | **Non — la bande jaune est ré-argumentée.** L'ancienne raison (« elle appartient à la préfecture ») était devenue fausse. | Nouvelle raison, **plus forte** : un ambre saturé entre un vert et un rouge invente visuellement un cran intermédiaire qui n'existe pas dans le 13. Plus un **audit chiffré** des 12 tokens contre la règle, qui a fait **supprimer `--c-pin-alep`**. Une règle qu'on n'audite pas n'est pas une règle. |
| **Motif : opposition binaire au lieu d'une densité graduée** | Le motif n'est-il plus qu'une béquille a11y ? | **Non.** Sur deux états, la densité n'a plus de référent : « plus dense = plus grave » n'a de sens qu'avec trois crans ou plus. | Opposition **nu / barré**, empruntée à la signalétique de terrain (panneau vierge vs panneau barré). Et c'est aussi le **signal de loin** : le rouge hachuré lit plus sombre que le vert nu, avant même qu'on distingue les teintes. |
| **Hachure « indisponible » en `--c-trace`** | Métaphore ou mesure ? | **Échec mesuré : 1,96:1.** La cohérence poétique avec la peinture ancienne du repère masquait un motif invisible. | **Corrigé** en `--c-charbon-doux` (**6,33:1**), et `--c-trace` **interdit** dans tout motif de statut. La mesure l'emporte sur la métaphore. |
| **Pastilles à `--r-1` (2 px)** | Détail sans importance ? | **Non — mesuré comme nuisible.** Sur 16 px de haut, 2 px de rayon rongent le liseré dans les angles. | **Passées à `--r-0`.** On ne rogne pas l'élément qui porte la conformité pour un adoucissement décoratif. |
| **Jetons sémantiques `--statut-autorise` / `--statut-interdit`** | Question de style de nommage ? | **Non, question de sécurité.** | Numérotation **bannie**. `--statut-2` était jaune : réutilisé pour « interdit », il aurait peint des massifs fermés en jaune sans qu'aucun test ne le voie. Un jeton absent ne peint rien — échec bruyant, donc sûr. |
| **Publier les échecs de contraste au lieu de les contourner** | Est-ce que j'affaiblis le livrable en écrivant « ÉCHEC » douze fois ? | **Non — c'est le livrable.** Un tableau tout vert aurait signifié que je n'ai pas mesuré. | §10.1 énonce chaque échec, §10.2 et §10.3 montrent ce qui porte l'exigence à la place. Le pire cas (**vert vs rouge à 1,48:1**) n'aurait jamais été trouvé sans cette passe : c'est lui qui rend le liseré **bloquant** au lieu de « recommandé ». |
| **Emplacement de consigne vide** | Un trou dans le design ? | **Non — un fait, rendu honnêtement.** | Aucun intitulé orphelin, aucun tiret, aucun squelette, aucune hauteur réservée ; une phrase factuelle et un lien. Le remplissage futur réutilise un encart **déjà existant** (§7.3) : rien à redessiner. |
| **Reproduire `autorisé`/`interdite` et les deux apostrophes** | Négligence ? | **Non, fidélité.** | Les incohérences sont **documentées comme obligatoires** (§11.4) et protégées contre les passes de nettoyage automatique — c'est précisément le genre de détail qu'un linter « améliore » tout seul. |

**Verdict global de la passe 2 bis.** La révision n'a introduit **aucune audace nouvelle**. La signature
reste unique et tenue à sept emplacements ; le pari du fond monochrome est renforcé, pas concurrencé ;
la seule addition visuelle — la frise — est une répétition d'un objet existant, portant de la donnée,
bornée à un emplacement et supprimable sans dommage. Trois décisions de la v1.0 ont été **infirmées par
la mesure** (hachure `--c-trace`, rayon des pastilles, bande de teinte réservée) et corrigées ; une
quatrième (les jetons numérotés) a été bannie pour une raison de sécurité, pas d'esthétique.

**Ce que je referais si le temps le permettait** : rien de nouveau. Le jeu de pictogrammes de massif
écarté en v1.0 le reste, et la légende binaire renforce ce refus — avec deux états, une iconographie
supplémentaire n'aurait plus rien à encoder.

### 14.3 [v2.1] Passe 2 ter — motifs de mise en page et d'interaction

**Pourquoi cette passe existe.** Les passes 2 et 2 bis sont sérieuses, mais elles n'interrogent que la
**couleur, la forme, la signature et le nommage des jetons**. Elles n'ont examiné **aucun motif de mise en
page ni d'interaction** : ni la feuille du bas, ni la typographie fluide, ni le grand chiffre, ni la barre
d'action collée, ni le parti des capitales. Le §7 du brief demande explicitement cet examen : c'était une
ligne de DoD non tenue, et la tenir *après coup* vaut mieux que de la déclarer tenue.

Mêmes quatre questions que les passes précédentes, plus une cinquième propre à celle-ci :
***ce motif vient-il du sujet, ou d'une bibliothèque de motifs d'application ?***

**1. La feuille du bas façon Material (§7.1).** Le risque est réel et il faut le nommer : le *bottom
sheet* est un **composant signature de Material Design**, et personne ne le confond avec autre chose.
**Verdict : conservé.** Le §5.2 du brief demande littéralement un « panneau en bas d'écran », et le motif
est **déjà dé-materialisé par des règles en vigueur** : `--r-0` interdit les coins supérieurs arrondis,
`--ombre-0` / `--ombre-decalee` interdisent l'élévation floue, le §9.4 interdit la physique à ressort.
**Ce qui manquait et qui est écrit ici** : la poignée de 44 px **n'est pas une pilule** — le §6.2 les
bannit — mais un **bouton rectangulaire portant le libellé « Fermer le panneau »** ; et tout
*swipe-to-dismiss* **double toujours un bouton**, jamais l'inverse. Un composant qui ne se ferme qu'au
geste est inatteignable au clavier et au lecteur d'écran : ce serait un défaut bloquant, pas une
commodité tactile.

**2. La typographie fluide `clamp()` + `vw` (§5.1).** Risque double : c'est **le réflexe par défaut de
tous les design systems des années 2020**, et le `vw` dans la taille de texte est un **piège WCAG 1.4.4**
connu (le texte cesse de répondre au zoom texte). **Verdict : conservé**, mais **la défense écrite en
v2.0 était la mauvaise**. « Les `clamp` restent bornés par leur minimum en `rem` » ne prouve rien : un
plancher en `rem` n'empêche pas un terme médian insensible à la préférence utilisateur. **La vraie
défense, celle qui tient** : le terme médian est **toujours `rem + vw`, jamais `vw` seul** — il grandit
donc avec la taille de police racine et **répond au zoom texte**, la part `vw` n'ajoutant qu'une réponse à
la largeur du cadre.
**Observation à consigner, parce qu'elle a l'air d'une incohérence et n'en est pas** : l'échelle
d'espacement est **entièrement en `px`** alors que `--cible-min` est en **`rem`**. Cette asymétrie est
délibérée et bonne : **les cibles suivent la préférence de l'utilisateur** (une cible de 44 px doit
grandir avec son texte), **les gouttières restent physiques** — si elles grandissaient au zoom, un écran
de 360 px à 200 % verrait ses marges exploser et **imposerait un défilement horizontal**, c'est-à-dire
exactement l'échec que le §10.6 règle 6 interdit. Rien n'était écrit : un intégrateur consciencieux
« harmoniserait » l'échelle en `rem` et casserait le 360 px à 200 %.

**3. Le chiffre du jour géant en `--fs-800` (§8.2).** Risque : le **« big number » de tableau de bord
SaaS**, motif d'application s'il en est. **Verdict : conservé**, et différencié sur quatre points déjà
vrais dans ce document — (a) il vit dans une **bande sombre pleine largeur**, pas dans une carte ; (b) il
ne porte **ni sparkline, ni flèche de tendance, ni pourcentage, ni couleur de tendance**, le §2.1
interdisant toute couleur sémantique et la passe 2 bis ayant déjà refusé la jauge et l'anneau ; (c) il est
composé dans la **famille de titrage** en `--poids-affiche`, la même voix que le `h1`, et non dans une
police « métrique » séparée ; (d) il n'est **jamais animé** (§9.4 : aucun compteur qui s'incrémente).
**Risque résiduel à écrire** : c'est une **statistique dérivée**. La règle 6 du §11.1 interdit déjà au
thème de composer une date lui-même ; **la même interdiction couvre le chiffre et son dénominateur**. « 12
sur 25 » est une valeur de l'extension, pas un `count()` de gabarit — sans quoi une liste partielle
produirait un chiffre faux et confiant. Ligne de revue ajoutée au §16.

**4. La barre d'action collée du portail (§7.2).** Risque : motif d'UI applicative, doublé d'un danger
d'accessibilité documenté — elle **recouvre le contenu**, et à 200 % de zoom une barre fixe peut manger
**plus de 30 % de la hauteur utile**. **Verdict : conservé, pour le portail uniquement** : c'est elle qui
rend atteignable la « mise à jour complète en moins d'une minute » (§6 du brief) pour les 25 massifs, en
supprimant l'aller-retour vers un bouton de bas de page. **Deux bornes que le document ne portait pas** : (a) la **dernière ligne du
tableau réserve un `padding-block-end` égal à la hauteur de la barre**, pour qu'aucune ligne ne soit
jamais inatteignable sous elle ; (b) **en dessous d'une hauteur de fenêtre réduite — ou à 200 % de zoom —
la barre revient dans le flux statique**, en fin de tableau, plutôt que de recouvrir des lignes. Une barre
collée qui reste collée quand la hauteur ne le permet plus est un défaut, pas une fidélité au dessin.

**5. Le parti des capitales condensées lui-même.** C'est le risque le plus aigu de cette passe, et il
n'est pas esthétique : **les capitales dégradent la vitesse de lecture**, suppriment la reconnaissance de
la silhouette du mot, et **certains lecteurs d'écran épellent** les chaînes courtes tout en capitales.
**Verdict : conservé** — c'est le langage du panneau DFCI, il est le sujet et non un effet — mais **borné
par trois règles, dont deux n'étaient pas écrites** :
(a) les capitales sont produites par **`text-transform: uppercase`** sur une source **normalement
casée** ; **jamais** saisies en capitales dans le HTML. Ainsi le lecteur d'écran, le `Ctrl+F` et le
copier-coller reçoivent la casse véritable.
(b) **[v2.3, réduite]** capitales **uniquement sur les étiquettes `--fs-250`** — le §5.1 le dit ; jamais
sur un `h1`, jamais sur un `h2`, jamais sur du texte courant, jamais sur un paragraphe. *La borne écrite
en v2.1 comportait une seconde moitié, « au-dessus de `--fs-500` » : **D-26 la supprime**, et l'issue #23
l'applique. Ce qui subsiste ici est la moitié que D-26 a expressément maintenue.*
(c) **un conflit non vu jusqu'ici, tranché ici.** `Légende de la carte` est **à la fois** une chaîne
officielle *verbatim* (§11.4, où toute modification est un défaut bloquant) **et** un `h2` (§8.5), donc
capitalisé par le §5.1. Ces deux règles ont l'air de se contredire. **Résolution : `text-transform` est un
rendu, pas une édition.** Le texte du DOM reste `Légende de la carte`, mot pour mot, apostrophes et casse
d'origine comprises ; seul l'affichage est en capitales. Le §11.4 est donc honoré. Sans cette phrase,
`review-cms` signalerait un faux défaut — ou, bien pire, un intégrateur « corrigerait » en tapant
`LÉGENDE DE LA CARTE` dans le gabarit et **casserait le §11.4 pour de bon**.

**[v2.3]** L'exemple de cette entrée est caduc (« Légende de la carte » n'est plus capitalisé, D-26) ; la
règle qu'il établit reste en vigueur et vise désormais « Niveau d'Accès » et les quatre libellés officiels.

**[v2.3] Sur le verdict de cette entrée.** « Conservé » n'est plus le verdict en vigueur : **D-26 lève
cette défense** — elle ne l'invalide pas, elle était juste sur la cible qu'elle connaissait — et l'issue
#23 en tire les conséquences normatives. Le texte ci-dessus **n'est pas réécrit** : c'est la trace d'un
raisonnement mené de bonne foi, et l'effacer rendrait D-26 illisible. Voir §14.4.

**6. La décision `prefers-color-scheme` manquante.** Ce n'était pas un motif douteux : c'était un
**silence**. Le document n'a jamais dit s'il existe un mode sombre, et un silence de 1580 lignes se remplit
tout seul par le premier agent qui passe. **Verdict — D-23 : pas de mode sombre**, consigné, pas laissé
ouvert. Trois raisons : (a) un thème sombre exigerait une **seconde preuve §10 complète**, car `#22B14C`
et `#E63A3C` **ne sont pas re-tonalisables** et toute la conformité repose sur des ratios calculés contre
la palette claire ; (b) le §2.1 borne la palette du site, et une palette sombre inventée en aval tomberait
hors de ces bornes ; (c) **un panneau de sentier peint n'a pas de mode sombre** — le registre du site est
un objet physique éclairé par le jour. **Conséquence pratique à écrire** : `tokens.css` ne porte **aucun**
bloc `prefers-color-scheme`, **et** `html { color-scheme: light; }` doit être déclaré par les chaînes
d'intégration. Sans cette déclaration, sous un OS en thème sombre, Chrome assombrit de lui-même les
contrôles natifs et les barres de défilement, ce qui **invalide les hypothèses de `--bord-champ`** et les
ratios du portail.

**Verdict global de la passe 2 ter.** Cette passe **n'infirme aucune décision de mise en page**. Elle
ajoute des **bornes** à quatre motifs empruntés qui avaient été retenus sans être argumentés (feuille du
bas, typographie fluide, chiffre géant, barre collée), elle **tranche un conflit non vu** entre le §5.1 et
le §11.4, et elle **ferme une question restée ouverte pendant 1580 lignes**. Aucun élément visuel nouveau
n'est introduit, aucun jeton n'est ajouté : ce qui manquait n'était pas du dessin, c'était de l'écrit.

### 14.4 [v2.3] Passe 2 quater — quand la cible de réception change, pas le goût

**Pourquoi cette passe existe, et en quoi elle diffère des trois autres.** Le §7 du brief impose un
**processus d'autocritique** ; l'issue #23 en est une application formelle, et le document doit le dire au
lieu de laisser croire que ces changements sont des corrections de détail. Les passes 2, 2 bis et 2 ter
posaient toutes la même question — *ce choix est-il générique, est-ce un tell « design IA », l'audace
est-elle unique, la palette vient-elle du sujet ?* — et **infirmaient des choix qui étaient faux**. Celle-ci
est d'une autre nature : **elle renverse un choix qui était juste**, et qui avait été **explicitement
examiné puis défendu** (§14.3 entrée 5, verdict « conservé »). Le motif n'est pas que le raisonnement était
mauvais ; c'est que **le destinataire du rendu a été écrit après lui**.

| Décision réexaminée | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Les capitales sur `h1` / `h2`** | Le choix était-il faux ? | **Non — il était juste sur la cible qu'il connaissait.** Ce qui a changé est le §7 du brief : le rendu s'adresse à un **décideur communal**. Pour lui, un titrage intégralement capitalisé lit comme de la signalétique ou de la campagne, pas comme un service | **Renversé** (D-26) et **appliqué ici** : §5.1, §7.3, §8.4, §14.3 entrée 5 (b), §16. Les capitales restent sur les **étiquettes `--fs-250`**, qui portent les chaînes officielles |
| **Le motif du renversement** | Puis-je l'attribuer à l'accessibilité, puisque le gain existe ? | **Non, et c'est le point le plus important de cette passe.** Le §14.3 entrée 5 avait déjà relevé et **borné** le risque de lecture : rien de neuf n'est apparu de ce côté. Le gain est un **effet secondaire non recherché** | **Écrit tel quel** partout où le renoncement est justifié. Attribuer à l'accessibilité un arbitrage de **registre** rendrait D-26 illisible pour qui le relira — et affaiblirait, par contagion, les vraies décisions d'accessibilité de ce document, qui sont toutes **mesurées** |
| **La rangée de 25 marques** | La passe 2 bis avait-elle vu juste ? | **Oui, et c'est ce qui rend son retrait indolore.** Elle écrivait déjà : « si un doute subsiste en revue, elle est le premier élément à retirer — et le système tient sans elle » | **Retirée** (D-27). Ce qui est perdu est **nommé**, pas minimisé : la forme de la journée d'un coup d'œil. Une autocritique qui prépare sa propre sortie est une autocritique qui a fonctionné |
| **Trois filets de 4 px sur une page qui en prescrit « un »** | Détail de mise en page ? | **Non : une règle qui ment un jour sur trois n'est pas une règle**, et celle-là mentait depuis la v1.0 sans que trois passes la voient | **Redistribués** (§6.3) : une occurrence dans le chrome nominal, à l'entrée du héros ; l'exception du bandeau d'alerte est **nommée** au lieu d'être ignorée |
| **Le chiffre du jour à 8rem** | Le « big number » de tableau de bord, déjà défendu en passe 2 ter ? | **Défense maintenue, échelle bridée.** Les quatre différenciations du §14.3 entrée 3 restent vraies ; ce n'était pas le motif qui posait problème, c'était son amplitude | **Plafond de consommation** `5.75rem` (§5.1). Le chiffre redevient **une donnée dans une bande d'information**. Aucun jeton modifié |

**Le risque propre à cette passe, nommé.** Retirer des capitales, réduire un chiffre et supprimer une
rangée de marques va toujours dans le même sens : **vers le sage**. Trois pas de plus dans cette direction
et le site ressemblerait à n'importe quel site cartographique — c'est-à-dire au défaut que le §7 du brief
interdit. **Contre-mesure, vérifiée point par point** : la signature (§3) est **intacte** et reste tenue à
sept emplacements ; le pari du fond monochrome (§1) est **intact** ; le rayon nul, l'absence d'ombre floue,
les aplats opaques, le liseré charbon et la famille **condensée** de titrage sont **intacts** ; l'ancrage
DFCI du §2 perd **un** geste sur trois et conserve les deux autres. **Ce qui a été retiré est de
l'amplitude, jamais de l'identité** — et c'est la ligne à tenir si une révision ultérieure était tentée de
continuer dans ce sens.

**Verdict global de la passe 2 quater.** Aucune audace nouvelle, aucun élément visuel nouveau, aucun jeton
nouveau. Une décision de registre renversée pour un motif de registre, une décision de composition retirée
comme sa propre archive l'avait prévu, deux amplitudes bridées, une règle rendue vraie. Ce qui manquait
n'était toujours pas du dessin : c'était de l'écrit — et, cette fois, de l'écrit **opposable en revue**.

### 14.5 [v2.4] Passe 2 quinquies — la première fois que le rendu contredit le document

**Ce qui distingue cette passe des quatre précédentes.** Les passes 2 et 2 bis interrogeaient des choix
**sur le papier**. La passe 2 ter interrogeait des **motifs de mise en page**. La passe 2 quater renversait
un choix juste parce que la **cible de réception** avait bougé. Celle-ci est la première où **le document
avait raison en intention et tort en fait** : la règle « liseré 4 px + repère » était défendable, elle a
été appliquée fidèlement, et **elle a produit à l'écran l'inverse de ce qu'elle décrivait**. Le déclencheur
n'est ni une relecture, ni une direction, ni une mesure de contraste : c'est **un massif regardé dans un
navigateur**.

| Décision réexaminée | Question posée | Verdict | Ce qui a été fait |
|---|---|---|---|
| **Le repère sur le massif sélectionné** (§3.2, empl. 5) | L'aurais-je écrit pour n'importe quel site carto ? | **Non — et c'est précisément le problème.** Aucun site cartographique ne poserait une duplication décalée sur un polygone : c'était une idée **propre à ce site**, tenue jusque dans un endroit où elle ne pouvait pas fonctionner. L'audace unique était devenue une **obligation d'appliquer l'audace partout**, ce qui n'est pas la même chose | **Retiré** (D-28). La liste fermée du §3.2 passe de sept à **six** emplacements. La signature reste sur le `h2` du panneau, qui s'ouvre **au moment même** de la sélection |
| **Le traitement « sélectionné » lui-même** | Le remplaçant est-il un effet générique de carto web ? | **À surveiller, et surveillé.** Le réflexe du domaine, ce serait un **glow**, une **ombre portée diffuse**, une **pulsation** ou un aplat éclairci. Tous les quatre sont interdits, nommément, et l'étaient déjà | **Le cerne** : un anneau à bords vifs, sans flou, sans opacité, sans mouvement, **posé sous la forme** pour ne rien recouvrir. C'est du **trait plat sur trait plat** — le même vocabulaire que le liseré, la pastille et le jalon. Aucun vocabulaire nouveau n'entre dans le système |
| **Trois paliers de zoom** | Est-ce une troisième idée sans rapport, dispersée à côté des deux autres ? | **Non — c'est la même idée que D-24, appliquée une seconde fois.** D-24 : sur une carte, quand la couleur ne peut pas trancher, **c'est l'ordre des couches qui tranche**. Ici : quand l'épaisseur ne peut pas être constante, **c'est l'échelle qui tranche**. Dans les deux cas, la carte est traitée comme **le seul objet du site dont le design ne décide pas la taille** | **Écrit comme une règle générale** (§9.2.a), pas comme un correctif de zoom 9. Trois paliers, un rapport pour le survol, un plancher mesuré, aucune valeur devinée |
| **Descendre le liseré à 1,5 px** | Est-ce que j'affaiblis l'élément qui porte à lui seul la conformité (D-13) ? | **Oui, de 37 % de marge à 6 %, et c'est écrit en toutes lettres.** La question n'était pas « puis-je le rendre plus fin » mais « **jusqu'où la mesure me laisse aller** ». Elle répond **1,42 px**. Toute autre valeur aurait été un jugement d'œil sur l'objet du site où le jugement d'œil est le moins admissible | **Plancher dérivé, borné à un seul palier** (§10.2.a), et **deux gardes** au §16. Le tableau publie aussi les épaisseurs **qui échouent** — 1,25 px et 1 px —, parce qu'un plancher sans son échec voisin n'est pas un plancher, c'est une préférence |
| **Ouvrir le §12, gelé depuis la v2.0** | Trois révisions s'en sont abstenues. Suis-je en train de m'accorder ce qu'elles se sont refusé ? | **Non, et la différence est tabulée** (§12.1). Ce qu'elles refusaient de payer, c'était une **intention de composition**. Ce qui est payé ici, c'est **un aplat officiel recouvert à l'écran** | **Ouvert, chiffré, déclaré.** Cinq jetons, aucune suppression, aucun renommage, aucune couleur touchée. L'invariant de compte est **remplacé par écrit** (111 → 116) et l'amendement du contrat #4 est **délégué à sa chaîne** (§17.1), jamais commis d'ici |

**Le tell le plus dangereux de cette passe, nommé.** Ce n'est ni le crème-et-serif ni le noir-et-accent-acide :
c'est **le halo de sélection**. Sur une carte web, « l'élément choisi » se dit presque toujours par un
rayonnement clair — c'est le geste par défaut de tous les kits cartographiques, et c'est **exactement ce
que la v2.3 produisait sans l'avoir voulu**, puisque son contour calcaire de 4 px, sur un massif petit, ne
se lisait plus que comme une **tache claire**. La v2.4 s'en éloigne par une contrainte, pas par un goût :
**au palier département, aucune peinture claire n'est posée sur la carte.** Une règle qui se vérifie d'un
coup d'œil sur une capture d'écran vaut mieux qu'une intention qui se vérifie en relisant un paragraphe.

**Le risque symétrique, également nommé.** Cette passe **retire** un emplacement de signature — la
précédente en avait retiré un composant entier. Deux révisions de suite qui allègent, et la pente est
celle que la passe 2 quater avait déjà signalée : *vers le sage, donc vers le générique*. **Contre-mesure,
vérifiée point par point** : le pari du fond monochrome est intact ; le rayon nul, l'absence d'ombre floue,
les aplats opaques et la famille condensée sont intacts ; le repère est intact **dans sa forme et dans sa
construction CSS** — il perd un emplacement où il ne se voyait pas, il n'en perd aucun où il se voit. Et
cette révision **ajoute** un objet au système, le cerne, là où les deux précédentes n'en retiraient. **Le
solde n'est pas un appauvrissement : c'est un déplacement du geste vers l'échelle où il fonctionne.**

**Verdict global de la passe 2 quinquies.** Un emplacement de signature retiré parce que la géographie l'a
réfuté, un traitement d'état refait pour cesser de recouvrir l'information qu'il désigne, une échelle
d'épaisseurs là où il n'y avait qu'un nombre, un plancher dérivé d'une mesure et non d'un avis, un fichier
gelé ouvert en déclarant ce qu'il en coûte. **Ce que cette passe apprend au document, et qu'aucune des
quatre autres n'aurait pu lui apprendre : une prescription visuelle qui n'a jamais été regardée à l'écran
n'est pas une décision, c'est une hypothèse.** Le §16 reçoit la ligne correspondante.

---

## 15. Journal des décisions (extrait pour le §11 du brief)

| # | Décision | Raison retenue | Alternative écartée |
|---|---|---|---|
| D-01 | Fond de carte monochrome calcaire, restylé côté serveur | Les statuts deviennent la seule couleur de l'écran : lisibilité + impact | Fond OSM standard + polygones translucides |
| D-02 | `fill-opacity: 1` sur les massifs | Les ratios mesurés ne tiennent pas sous transparence | Aplats à 50 % (ce que fait la source officielle) |
| D-03 | Aucune couleur sémantique hors légende | Empêche toute confusion entre chrome et état d'accès | Vert « succès » / rouge « erreur » conventionnels |
| D-04 | Rayon plafonné à 2 px | Registre « signalétique peinte », anti-kit UI | Cartes arrondies 8–12 px |
| D-05 | Ombres décalées non floues, dérivées du repère | Une seule audace tenue partout | Ombres douces `0 2px 8px rgba(0,0,0,.1)` |
| D-06 | 2 fichiers de police variables | Budget §10 tenu, hiérarchie par la taille | 4 statiques (dépassement) |
| D-07 | Atkinson Hyperlegible Next pour le texte | Accessibilité bloquante, argument opposable en mémoire technique | Inter / Open Sans |
| D-08 | Motif obligatoire partout où la couleur apparaît | Lisible en niveaux de gris et en vision dichromatique | Couleur seule + libellé |
| D-09 | *(v1.0)* Légende officielle en `À CONFIRMER` + 8 questions | Interdiction d'inventer (§4.2) ; système paramétré pour l'échange des valeurs | Déduire les couleurs d'une capture approximative |
| D-10 | Liste du jour traitée en second héros | L'équivalent textuel ne doit pas se lire comme un repli | Liste discrète sous la carte |
| **D-11** | **Légende réelle : 2 états d'accès + dimension ZAPEF ; les 5 crans substituts sont supprimés** | Établie par trois relevés concordants. Le §4.2 impose de reproduire *exactement* la légende officielle. L'échelle à six crans du code partagé appartient à d'autres départements | Conserver 5 crans « pour la richesse visuelle » ; ou dériver des libellés depuis le `level` brut 0–4, dont **aucun libellé n'est publié** |
| **D-12** | **Teintes officielles `#22B14C` / `#E63A3C` reproduites sans retouche** | §4.2 du brief. La conformité est portée ailleurs (D-13) | Désaturer, assombrir ou « harmoniser » avec la palette minérale — ç'aurait été plus joli et faux |
| **D-13** | **La conformité AA des statuts est portée par le liseré charbon 2 px et le motif, jamais par la teinte** | Mesuré : la teinte seule échoue 8 fois sur 13 paires, dont **vert vs rouge à 1,48:1**. Le liseré tient **4,11:1 au pire cas** sur tout le système | Choisir des teintes conformes (= inventer la légende) ; ou déclarer l'exception « couleur de marque » (= abandonner le §8) |
| **D-14** | **Jetons de statut sémantiques ; numérotation bannie à perpétuité** | Un jeton numéroté réutilisé après changement de légende repeint des massifs interdits dans la mauvaise couleur, **en silence**. Un jeton absent ne peint rien : échec bruyant | `--statut-1` … `--statut-5` (v1.0), qui faisaient de « interdit » un jaune |
| **D-15** | **ZAPEF rendues par une silhouette distincte (carré planté), pas par une couleur distincte** | Au `level` 3, un jalon vert se superpose à un massif rouge : sans écart de forme, l'affichage se lit comme une contradiction. La forme dit la dimension, la couleur dit l'état | Marqueur rond classique ; ou fusion ZAPEF/massif en un seul indicateur (perte d'une dimension officielle) |
| **D-16** | **Motif binaire nu/barré, et suppression de la densité graduée** | Sur deux états, « plus dense = plus grave » n'a plus de référent et suggérerait des crans intermédiaires inexistants | Conserver une gradation de densité « au cas où » |
| **D-17** | **Encre des motifs hors niveau passée de `--c-trace` à `--c-charbon-doux`** | Mesure de la passe 3 : 1,96:1 contre 6,33:1. Le motif était invisible | Garder `--c-trace` pour la cohérence métaphorique avec le repère |
| **D-18** | **Emplacement de consigne présent, silencieux quand vide, sans hauteur réservée** | Le §5.2 du brief promet une consigne ; la préfecture n'en publie aucune et l'arrêté est illisible. Un emplacement qui se signale ferait croire à une donnée manquante | Afficher « — » ou « non renseigné » ; ou rédiger nous-mêmes une consigne plausible (interdit par le §4.2) |
| **D-19** | **Ajout de la frise des 25 marques dans l'ardoise, bornée à un emplacement et `aria-hidden`** | La légende binaire rend la forme de la journée lisible d'un coup d'œil, à quatre mètres. Réinvestit la complexité libérée dans la lisibilité, pas dans le décor | Ne rien ajouter (défendable) ; ou une jauge / un graphique en anneau (kit UI, deuxième audace) |
| **D-20** | **[v2.1] Sous-ensemble `latin` seul pour les deux familles** | Vérifié sur la cmap réelle des binaires embarqués : `latin` contient tout le bloc U+00C0–U+00FF, U+00A0, U+2019, `*`, guillemets, tirets, `°`, `œ`/`æ` et les chiffres. `latin-ext` ne contient que `U+0100` et au-delà — **sans emploi pour le français** (brief §2 : français uniquement). Prendre les deux ferait **4 fichiers contre un budget dur de 2**, pour zéro glyphe utile | `latin + latin-ext`, ce qu'écrivait le §5 de la v2.0 — cela cassait le budget du §10 du brief |
| **D-21** | **[v2.1] Les `@font-face` vivent dans `assets/fonts/fonts.css`**, ni dans `tokens.css`, ni dans `style.css` | Le répertoire des polices devient **autosuffisant** : octets, licences, provenance et déclarations au même endroit ; les `url("./…")` relatives sont incassables ; `@font-face` étant insensible à la cascade, ce fichier n'entre en concurrence avec aucune feuille des chaînes #5/#6 quel que soit l'ordre d'enqueue. Et surtout : **le bloc normatif §12 reste intact** pendant que deux chaînes le lisent | `@font-face` en tête de `tokens.css` — inclination initiale, écartée : elle aurait modifié le §12 au pire moment |
| **D-22** | **[v2.1] `font-display: optional` + preload obligatoire des deux fichiers ; aucun descripteur de métriques** | Seule option qui garantisse **structurellement** le « pas de sauts perceptibles » du §10 du brief sans inventer un `size-adjust` indérivable. **Coût assumé et écrit** : un visiteur de première visite sur connexion lente voit la police système sur la première vue ; l'identité du site (ardoise sombre, aplats, liseré charbon, repère, rayon nul, fond monochrome) survit intégralement à cette vue, et la police s'applique dès la suivante | `swap` — saut garanti sur l'élément LCP et sur les points de retour à la ligne via `68ch` ; `fallback` — saute encore dans la fenêtre nominale ; face de repli aliasée à descripteurs — exige un **3ᵉ fichier** ou une valeur inventée, `system-ui` désignant quatre polices selon l'OS et `Arial Narrow` étant absent d'Android et de la plupart des Linux |
| **D-23** | **[v2.1] Pas de mode sombre ; `color-scheme: light` déclaré par les chaînes d'intégration** | Toute la preuve du §10 est calculée contre la palette claire, et les deux teintes officielles **ne sont pas re-tonalisables** : un thème sombre exigerait une seconde preuve complète. Le §2.1 borne la palette du site ; une palette sombre inventée en aval en sortirait. Et un panneau de sentier peint n'a pas de mode sombre. Sans `color-scheme: light`, un OS en thème sombre fait assombrir les contrôles natifs par le navigateur et invalide les hypothèses de `--bord-champ` | Un bloc `prefers-color-scheme: dark` dans `tokens.css` ; ou laisser la question ouverte, c'est-à-dire la laisser trancher par le premier agent qui passe |
| **D-24** | **[v2.1] Ordre des couches : étiquettes du fond de carte sous les aplats de statut ; chrome de carte flottant sur aplat opaque `--c-calcaire`** | Mesuré : `--c-carte-encre` plafonne à **2,03:1 sur `#E63A3C`** et 3,02:1 sur `#22B14C` (§10.7). Aucune encre ne passe sur le rouge officiel — **l'ordre des couches est le seul mécanisme disponible** pour appliquer la règle 3 du §4.1.d sur une carte. Coût nul : en raster les étiquettes sont cuites dans la tuile, en vectoriel c'est un ordre explicite | Un pane « étiquettes au-dessus » ; un halo ou un contour sur les toponymes — sur 2,03:1, un halo ne rattrape rien |
| **D-25** | **[v2.1] La flèche `→` (U+2192) est rendue en SVG en ligne, jamais en caractère** | Mesuré : U+2192 est **hors du sous-ensemble `latin` et absent des deux polices**. Écrit en caractère, il afficherait un rectangle vide dans l'historique du portail (§7.2). Cohérent avec le §16 en vigueur : « les rares symboles sont du SVG en ligne » | Taper `→` dans le gabarit ; ou élargir le sous-ensemble pour un seul glyphe — ce qui rouvrirait D-20 |
| **D-26** | **[v2.2] Renoncement aux capitales condensées comme parti de titrage.** Borne dure : capitales admises **uniquement sur les étiquettes**, **interdites sur `h1` et `h2`**. Ce qui est perdu est nommé : le §2, ligne « Panneaux DFCI », tire de la signalétique **trois gestes** — capitales condensées, sérigraphie, rayon nul — et **le premier est abandonné**. Le §14.3 entrée 5 le **défendait explicitement** : « **Verdict : conservé** — c'est le langage du panneau DFCI, il est le sujet et non un effet ». Cette défense est **levée, elle n'est pas invalidée** : elle était juste sur la cible qu'elle connaissait. Restent la sérigraphie, le rayon nul, et la condensée elle-même, qui **demeure la famille de titrage** | **Le déplacement de la cible de réception, et lui seul.** Le rendu vise un **décideur communal** — l'élu ou le directeur général des services qui évalue une offre (§7 du brief, `CLAUDE.md` n°4). Pour ce destinataire, un `h1`/`h2` intégralement en capitales condensées lit comme de la signalétique ou de la campagne, pas comme un service. Le §1 l. 41-42 énonçait déjà la tension — « sobriété de service public, mais avec la brutalité graphique d'une signalétique de terrain » : l'arbitrage bascule vers le premier pôle **pour la typographie seule**, la couleur, les formes, le rayon nul, le repère et le fond monochrome restant au second. **Cette décision n'est pas motivée par l'accessibilité.** Le §14.3 entrée 5 avait déjà relevé le risque — « les capitales dégradent la vitesse de lecture », « certains lecteurs d'écran épellent » les chaînes courtes — et l'avait **borné**, donc tenu pour couvert : rien de neuf n'est apparu de ce côté. Le gain de lisibilité est un **effet secondaire, non recherché**, et **ne doit jamais être cité comme la raison** — ce serait attribuer à l'accessibilité un arbitrage de registre, et rendre D-26 illisible pour qui le relira. **Référence avant.** L'application normative — §5.1, §7.3, §14.3 entrée 5 (b), §16 — appartient à l'issue #23. Jusqu'à son exécution, ces sections restent en vigueur et cette contradiction est **connue et assumée** | Maintenir le parti de la v2.0/v2.1, capitales sur tout le titrage — conclusion de bonne foi du §14.3 entrée 5, prise sur une cible de réception qui n'était alors écrite nulle part ; ou abandonner la condensée elle-même, ce qui ferait tomber les deux autres gestes du §2 avec elle et viderait l'ancrage DFCI de sa substance typographique |
| **D-27** | **[v2.3] Retrait de la frise des 25 marques de l'ardoise — abandon définitif, non différé.** La frise n'existait **nulle part dans le code** : aucun gabarit ne l'émettait, aucune règle CSS ne la stylisait. Elle n'était prescrite que par **ce document**, en **17 points d'ancrage** — §1, §3.3, §4.1.d, §6.1, §7.1 (croquis, texte, tableau des points de rupture), §8.2, §9.3, §9.4, §10.6, §13, §16. Ces prescriptions sont retirées. **Ce qui est perdu est nommé, pas minimisé** : la lecture de la **forme de la journée d'un coup d'œil, à quatre mètres** (§1, §14.2). Elle reste portée par le **chiffre du jour** et, en toutes lettres, par la **liste du jour** — qui portait déjà l'intégralité de cette information, la frise étant `aria-hidden` (§10.6 règle 2, dont l'unique exception tombe ici). **Ce qui n'est pas touché** : **D-19 n'est ni réécrite ni annotée**, et l'archive §14.2 non plus. Le document fournit lui-même le patron, en D-26 : « cette défense est **levée**, elle n'est pas invalidée ». **Ce qui reste dans `tokens.css`** : `--frise-l` et `--frise-h` **demeurent déclarés et orphelins** (§12.1) — sha256 épinglé et **111 déclarations dans `:root`** (120 dans le fichier entier), invariant du contrat #4 **vérifié**. **On ne supprime pas un jeton : on cesse de le consommer.** Même traitement que `--ombre-decalee`. **Conséquence à consigner, sans quoi elle se perdrait** : l'arbitrage 13 du contrat #22 (`print.css`, protection du liseré sous `.sur-sombre`) était justifié par « bloquant **le jour de la frise** ». Cette règle devient **définitivement latente — et non morte** : la **barre d'action du portail** (§7.2) est un chrome sombre portant des pastilles. Un refacto futur qui la supprimerait comme du code mort commettrait une régression d'accessibilité silencieuse | **Direction du propriétaire, dans le même recadrage que D-26** — et **D-19 avait elle-même prévu ce retrait comme sortie nominale** : la passe 2 bis écrit en toutes lettres « si un doute subsiste en revue, elle est le premier élément à retirer — et le système tient sans elle ». Ce n'est donc pas une infirmation : c'est l'exercice d'une sortie que la décision d'origine avait ménagée, et c'est la preuve que la borne posée en §14.2 servait à quelque chose. **Abandonnée et non différée**, parce qu'« en attente » maintiendrait **17 prescriptions vivantes** dans un document normatif et **inviterait une chaîne future à la construire** de bonne foi : un document qui décrit un composant que personne ne doit fabriquer est un piège, pas une archive | La **différer** — les 17 prescriptions restent, et la première chaîne « carte » ou « portail » qui lit le §8.2 la fabrique ; la **supprimer de `tokens.css`** avec ses deux jetons — casse le sha256 épinglé et le compte de 111 propriétés, c'est-à-dire une décision de design payée par un incident d'intégration chez des chaînes sœurs ; la **remplacer** par un autre indicateur de forme (jauge, anneau, barre de proportion) — deuxième audace, déjà refusée par D-19 elle-même |

| **D-28** | **[v2.4] Le repère est retiré de la carte — l'emplacement 5 du §3.2 est supprimé, la liste fermée passe de sept à six.** Le traitement « sélectionné » de la carte devient **le cerne** : un anneau posé **entièrement hors du polygone**, rendu dans un pane placé **sous** celui des massifs, de sorte que l'aplat opaque du statut recouvre lui-même la moitié intérieure du trait. **L'aplat de statut et son motif ne sont recouverts à aucun palier.** Le numéro 5 est **barré, jamais réutilisé** ; 6 et 7 ne bougent pas | **Un défaut reproduit à l'écran, pas une préférence.** Chrome, stack Docker locale, zooms 9 et 10. Sur **Regagnas — boîte englobante 94 × 55 px au zoom 9**, un enchevêtrement de languettes de quelques pixels —, les deux contours de 4 px se recouvraient d'un filament au suivant : le halo calcaire **remplissait la boîte englobante**, la duplication charbon l'encadrait, et l'écran montrait **un rectangle blanc bordé de noir**. Ni massif, ni couleur, ni motif, ni repère. **Le document contenait déjà la règle qui l'interdisait** : le §3.3 refuse le repère sur « tout objet répété de moins de 20 px » — un massif filamenteux au zoom 9 **en est un**, ce que personne ne pouvait écrire avant que la géométrie ne soit rendue. **Rien n'est perdu de la signature** : la sélection **ouvre le panneau**, dont le `h2` porte le repère à l'échelle pour laquelle il est dessiné (§8.4, contrat #7 A-15). Ce qui est retiré, c'est **une seconde implémentation de la signature** — que le §3.1 n'admet qu'une fois | **Conditionner le repère à une taille minimale de massif** : exigerait que le JS mesure la boîte englobante et pose une classe — techniquement permis, mais cela **fait dépendre la signature d'un seuil arbitraire** et produit deux rendus de sélection dans la même carte, dont l'un apparaît et disparaît au zoom. Une signature intermittente n'est pas une signature. **Réduire le décalage à (1 px, 2 px)** : le repère est *une barre doublée d'une trace* ; à 1 px, la trace n'est plus une peinture ancienne, c'est un défaut d'impression. **Conserver le repère en le posant hors du polygone** : la duplication décalée pose de l'encre **des deux côtés** du tracé par construction — l'ordre des couches ne peut pas la sauver |
| **D-29** | **[v2.4] Les épaisseurs de trait de la carte suivent le palier de zoom** — trois paliers nommés `département` (z ≤ 9), `massif` (z 10), `abords` (z ≥ 11), portés par une **classe sur la racine** et par des règles CSS, avec des valeurs chiffrées pour le liseré, le survol et le cerne (§9.2.a). **Plancher du liseré : 1,5 px, mesuré** (§10.2.a). Cinq jetons entrent au §12 ; l'invariant de compte du contrat #4 est **remplacé par écrit** (111 → 116 dans `:root`) | **Un trait SVG est centré : il consomme la moitié de son épaisseur dans l'aplat.** Une épaisseur constante ne peut donc pas servir à la fois le département vu en entier et un massif vu de près. Mesuré à l'écran : au zoom 9, le liseré de 2 px était **plus large que les languettes qu'il cerne** — la carte lisait globalement noir et la couleur de statut disparaissait **sous son propre contour** ; au zoom 11, le même 2 px devenait un filet ténu. **Le plancher est dérivé, pas choisi** : au pire alignement sous-pixel, un trait de 1,5 px garantit 75 % de couverture, soit **3,18:1 sur le rouge officiel** ; à 1,42 px il vaut exactement 3,00:1, à 1,25 px **2,66:1**, à 1 px **2,18:1**. **La séparation vert/rouge à 1,48:1 reste tenue à tous les paliers.** Le mécanisme est **imposé par le code** : le JS de la carte n'a pas le droit d'écrire un style, donc l'épaisseur variable ne peut se poser que par une classe et des règles — aucune invention | **Garder 2 px partout** : c'est le défaut constaté, et il coûte l'information à z9. **Faire varier l'épaisseur en JS** : interdit — le JS ne pose que des classes (contrat #7, interdit 24). **Exprimer l'épaisseur en unités cartographiques** (`vector-effect`, unités de projection) : le trait grossirait *avec* le massif au lieu de suivre l'écran, ce qui **inverse** le problème au lieu de le résoudre. **Descendre le liseré sous 1,5 px pour gagner encore** : refusé **par la mesure**, pas par le goût — 2,66:1 à 1,25 px est un échec AA sur l'aplat interdit. **Créer un palier par niveau de zoom** (z8…z12) : cinq jeux de valeurs pour trois comportements réels, et un document que personne ne vérifie en revue |

| **D-30** | **[v2.5] Portée du §11.3 : la liste fermée borne le rendu PUBLIC, pas le portail gestionnaire.** Les chaînes du portail relèvent du §7 du brief et des règles de voix du §11.1 ; les libellés officiels y restent verbatim (§11.4) sans exception, et toute chaîne qui **paraît sur une page publique** retombe sous la liste fermée, quelle que soit la chaîne d'agents qui l'écrit | **Arbitrage du propriétaire du projet**, sur la question Q-2 du contrat #14. Le §11.3 protège **ce que voit le visiteur**, là où une phrase inventée pourrait passer pour officielle ; le portail est **interne** et ne s'adresse qu'à un gestionnaire authentifié. **Le §7.2 le corroborait déjà en rédigeant lui-même « Publier les statuts » et « 7 statuts modifiés »** — deux chaînes de portail hors liste depuis la v1.0, qu'aucune revue n'a jamais comptées comme des défauts : une liste qui se contredit ainsi n'est pas fermée, elle est fausse. Sans cet écrit, **chaque revue rouvre la question** — l'écran de publication en a écrit une vingtaine | **Étendre la liste fermée au portail** : il faudrait y faire entrer une vingtaine de chaînes d'outil, qui n'ont **aucun risque d'être prises pour officielles**, et rouvrir MASTER à chaque libellé de bouton — un document de design deviendrait un fichier de traduction. **Ne rien écrire** : c'est l'état qui a produit Q-2, et il se reproduirait à chaque écran d'administration |
| **D-31** | **[v2.5] Retrait de l'emplacement 4 du §3.2 — le repère au bord gauche du panneau massif.** La liste fermée passe de six à **cinq** : 1, 2, 3, 6, 7. Les numéros **4 et 5 sont barrés, jamais réutilisés**. **Rien ne change à l'écran** : le code livré ne posait déjà pas ce repère | **L'emplacement ne pouvait se déclencher nulle part, par construction et non par circonstance.** Le §8.4 ligne 1 donne son repère au `h2` du nom du massif — la v2.3 l'a **réaffirmé** après D-26 — et le §3.3 interdit plus d'un repère par bloc en tranchant lui-même : « le plus proche de l'information de statut gagne ». Le `h2` gagne **toujours**, dans le seul panneau massif qui existe. **Solde la dette ouverte par A-15 du contrat #7**, que la v2.4 avait bornée par une note constatant la vacance : une note constate, elle ne borne pas. Une prescription que personne ne doit appliquer est « un piège, pas une archive » (D-27) | **La maintenir en excluant explicitement le panneau de carte** — seconde branche offerte par A-15 : elle conserverait un emplacement qui ne s'applique **à rien**, et déplacerait la même dette d'une révision à l'autre. **Retirer le repère du `h2` pour le rendre au bord du panneau** : ce serait retirer la signature du **titre de statut** pour la poser sur du chrome, exactement l'inverse de l'amendement de l'emplacement 2 en v2.3 |

**Sur la flèche `→` : aucune décision nouvelle.** La mise en cohérence du §7.2 en v2.5 est **l'application
de D-25**, qui n'a jamais changé et qui était mesurée. Ce n'est pas un arbitrage entre deux options : c'est
un texte du document qui rejoint un fait établi par un autre. Aucune ligne de journal n'est donc ouverte
pour elle — en ouvrir une laisserait croire que la question a été rouverte.

---

## 16. Interdits — liste de contrôle de revue

Tout élément ci-dessous constaté par `review-cms` est un **défaut bloquant**.

**Fabrication**
- Constructeur de pages, thème tiers ou par défaut, kit UI, framework CSS générique (Bootstrap, Tailwind…).
- Toute requête navigateur vers un domaine tiers : police, icône, script, tuile, image.
- Police servie depuis un service de polices, même « via » un plugin tiers. Aucun CDN, aucun asset distant.
- Plus de 2 fichiers de police. Icônes en police d'icônes (les rares symboles sont du SVG en ligne).
- **[v2.1]** Bloc `prefers-color-scheme`, ou palette sombre alternative, sous quelque forme que ce soit (D-23).
- **[v2.1]** Fichier de police servi hors de `assets/fonts/`, ou `@font-face` déclaré ailleurs que dans
  `assets/fonts/fonts.css` (D-21).
- **[v2.1]** `→` — ou tout autre symbole hors du sous-ensemble `latin` — écrit en caractère plutôt qu'en
  SVG en ligne (D-25).

**Formes**
- `border-radius` > 2 px. Pilules, avatars ronds, boutons arrondis, **pastille de statut arrondie**.
- Ombre floue (`blur-radius` ≠ 0), dégradé décoratif, verre dépoli, néomorphisme.
- Ombre portée sur autre chose que le panneau massif et le bloc de légende.
- **[v2.5, amendé]** Repère hors des **5** emplacements du §3.2 — **1, 2, 3, 6, 7** —, deux repères dans le
  même bloc, repère sur un jalon — et **repère, duplication décalée ou ombre décalée sur la géométrie de la
  carte**, à quelque palier de zoom que ce soit (D-28). Les emplacements **4** (D-31) et **5** (D-28) sont
  **supprimés**, jamais réattribués. **Une seule exception, bornée et temporaire** : le repère de l'option
  sélectionnée de la paire segmentée du portail, que le §7.2 prescrit et que la liste ne comporte pas —
  contradiction interne enregistrée au **§17, ligne 20**, à trancher (**§18, recommandation 1**). Elle ne
  se compte **pas** comme un défaut tant qu'elle n'est pas tranchée ; **aucun autre emplacement nouveau
  n'est couvert par cette exception**.
- **[v2.3]** Repère devant un `h2` **hors portée** de la famille d'affichage — chrome, page éditoriale,
  portail (§3.2 amendé, §5.1 règle de portée).
- **[v2.1]** Poignée de la feuille du bas rendue en **pilule** ; **coins supérieurs arrondis** sur la
  feuille ; fermeture par **glissement sans bouton équivalent** (§14.3 entrée 1).
- **[v2.3]** **`h1` ou `h2` rendu en capitales** — par `text-transform` ou de quelque autre façon (D-26).
  Les capitales sont réservées aux **étiquettes `--fs-250`**.
- **[v2.3]** Famille **d'affichage** employée **hors** des trois zones en portée, ou famille **de texte**
  employée **dans** l'une d'elles (règle de portée, §5.1). Cas le plus probable en revue : un `h2` de page
  éditoriale ou de portail composé en `--police-titre`.
- Carte enfermée dans un conteneur centré à coins arrondis.
- **[v2.3]** Plus d'**une** occurrence de `--bord-fort` dans le **chrome nominal** d'une page — le bandeau
  d'alerte, état exceptionnel, porte le sien et ne compte pas (§6.3).

**Couleur et sens**
- Couleur officielle modifiée, réinterprétée, désaturée, éclaircie au survol ou « harmonisée ».
- Toute couleur du site dans les bandes 95°–175°, 330°–25° ou 26°–94° au-delà de 12 % de saturation (§2.1).
- **Jeton de statut numéroté** (`--statut-1`, `--statut-n2`, `--statut-niveau-3`…), sous quelque forme que ce soit.
- Valeur hexadécimale de statut écrite ailleurs que dans `tokens.css`.
- Polygone, pastille ou jalon **sans liseré**, ou avec un liseré aminci sous son plancher. **[v2.4]**
  Hors carte : **2 px**, sans exception. Sur la carte : **1,5 / 2 / 3 px** selon le palier de zoom
  (§9.2.a). **1,5 px est le plancher absolu du système** et n'existe **qu'au palier département** ; un
  liseré de carte à 1,25 px ou moins est un **échec AA mesuré**, pas une variante (§10.2.a).
- État `interdit` **sans motif** ; état `autorisé` **avec** un motif.
- Statut encodé par la couleur seule, sans motif **et** sans libellé.
- Texte posé sur un aplat de statut, où que ce soit, y compris à l'impression.
- `--statut-*-encre` employé comme `color` de texte ; `--c-trace` employé dans un motif de statut.
- Danger météo présenté avec des couleurs, ou visuellement mêlé aux statuts.
- ZAPEF et massif rendus avec la même silhouette, ou fusionnés en un seul indicateur.
- Un statut périmé présenté comme courant ; un chiffre de la veille conservé en l'absence de donnée ;
  `level` 0 rendu comme « autorisé ».
- **[v2.1]** `--c-garrigue` employé comme **texte courant sur `--c-calcaire-ombre`** — lignes alternées,
  encarts, slabs : **mesuré 4,19:1, échec AA**. Réservé au texte ≥ 24 px et aux bordures (§10.7).
- **[v2.1]** Couche d'étiquettes cartographiques rendue **au-dessus** des aplats de statut ; chrome de
  carte (attribution, zoom, bascule EFFIS, sélecteur de date) posé sur la toile nue sans aplat opaque (D-24).
- **[v2.1]** Motif de statut qui disparaît sous **`forced-colors: active`** sans mécanisme de
  remplacement, ou statut dont le sens n'est plus porté que par la teinte dans ce mode (§10.8).

**Contenu officiel**
- Libellé officiel paraphrasé, abrégé, tronqué ou « corrigé » — y compris `autorisé`/`interdite` uniformisés.
- Apostrophe typographique U+2019 de la note ZAPEF remplacée par une apostrophe droite, ou l'inverse pour
  `Niveau d'Accès`.
- Libellé inventé pour distinguer les `level` bruts 1 et 2, ou 3 et 4 — aucun n'est publié.
- Vocabulaire hérité des 5 crans : « niveau 3 », « vigilance orange », « accès réglementé », « risque sévère ».
- Consigne rédigée, déduite ou plausible ; intitulé « Consigne » affiché sans consigne ; « — » ou
  « non renseigné » dans l'emplacement de consigne.
- États hors niveau (`information non disponible`, `dispositif estival inactif`) présentés dans la légende
  officielle sans la séparation `SUR CE SITE`.
- Jalon ZAPEF rendu sur la carte sans géométrie établie (§4.1.e).
- **[v2.1]** Libellé officiel **saisi en capitales dans le HTML**. Les capitales sont un rendu
  `text-transform`, **jamais** une édition de la chaîne (§11.4, §14.3 entrée 5).

**Interaction**
- `outline: none` sans remplacement ; focus invisible sur une surface quelconque de la palette.
- Information révélée uniquement au survol ; infobulle porteuse de sens.
- Panneau que Échap ne ferme pas ; piège clavier ; cible < 44 px.
- Bouton désactivé sans explication accessible.
- Animation d'apparition au défilement, parallaxe, compteur animé, spinner, marqueur pulsant.
- Mouvement subsistant sous `prefers-reduced-motion: reduce`, y compris côté Leaflet.
- Motif de statut qui s'étire ou se densifie au zoom de la carte.
- **[v2.4] Tout traitement d'état qui recouvre un aplat de statut sur la carte** — contour de sélection
  tracé **au-dessus** du polygone, halo clair, aplat éclairci, calque de surbrillance. La sélection est
  **le cerne**, posé **sous** la forme (§9.2.a). Un massif sélectionné dont on ne voit plus la couleur ou
  le motif est un défaut **bloquant**, au même titre qu'un polygone sans liseré.
- **[v2.4] Peinture claire posée sur la carte au palier « département »** (z ≤ 9) : `--carte-cerne-clair`
  y vaut **0**, et aucune autre encre claire n'y est admise. C'est la règle qui empêche le retour du
  rectangle blanc constaté en v2.3.
- **[v2.4] Épaisseur de trait de carte écrite en littéral** dans `carte.css`, ou **écrite par le JS** sous
  quelque forme que ce soit (`element.style`, `setProperty`, `setAttribute('stroke-width')`, option
  Leaflet `weight`). Le JS pose **une classe de palier** et rien d'autre ; les valeurs vivent au §12.
- **[v2.4] Cerne animé, flouté, en opacité, pulsant** ; cerne dont le **séparateur calcaire** borde le fond
  de carte ou un massif voisin au lieu d'être encadré de charbon des deux côtés (§10.2.b, 1,07:1).
- **[v2.4] Prescription visuelle nouvelle portant sur la carte qui n'a pas été regardée à l'écran**, aux
  zooms 9 et 11, sur **un massif petit et filamenteux** autant que sur un grand massif compact. C'est la
  leçon de la passe 2 quinquies (§14.5), et elle est ici pour être opposable : une règle de carte non
  vérifiée sur un rendu est une **hypothèse**, et une hypothèse ne s'écrit pas au présent dans ce document.
- **[v2.1]** Barre d'action collée **recouvrant la dernière ligne** du tableau du portail, ou restant
  collée quand la hauteur utile ne le permet plus (fenêtre basse, zoom 200 %) — elle doit alors revenir
  dans le flux, en fin de tableau (§14.3 entrée 4).

**Contenu éditorial**
- « Valider », « OK », « Soumettre », « En savoir plus », « Oups », « Désolé ».
- Emoji, exclamation, superlatif dans l'interface.
- Terme hors du vocabulaire fixe du §11.2.
- Bandeau de non-officialité absent d'une page affichant un statut.
- Attribution préfecture, OSM, DDTM, Météo-France ou EFFIS manquante.
- Bandeau de consentement aux cookies (il n'y a rien à consentir — §9 du brief).
- **[v2.1]** Le thème calculant lui-même **le chiffre du jour, son dénominateur, un décompte ou une
  date** : ces valeurs viennent de l'extension (§11.1 règle 6, §14.3 entrée 3).
- **[v2.3]** **Taux ou qualificatif de conformité RGAA écrit dans un gabarit** — « non conforme »,
  « partiellement conforme », « totalement conforme », « x % des critères » : ce sont des **résultats
  d'audit**, et aucun audit n'a été mené (§4.2 du brief). Même classe d'erreur que le chiffre du jour
  calculé par le thème : une valeur figée dans un gabarit devient **fausse en silence** au premier audit
  suivant. Le jour venu, elle vient du contenu, jamais du code (§7.3).
- **[v2.3]** Lien de pied **codé en dur** vers « Mentions légales », « Accessibilité » ou « La démarche »,
  ou **slug/libellé inventé** : ce sont des **entrées de menu** affectées à l'emplacement `pied` (§7.3).
- **[v2.3]** Phrase « zéro cookie » rédigée dans un gabarit : elle n'a **aucune chaîne normative** au
  §11.3, qui est une liste **fermée** (§7.3, `OUVERT`).

---

## 17. [v2.3] Divergences enregistrées entre le document et le code livré

**À quoi sert cette section, et pourquoi elle est placée juste après le §16.** Le §16 est la liste de ce
qui est un **défaut**. Celle-ci est la liste de ce qui **n'en est pas un** : des écarts entre ce document et
un code **déjà commité**, chacun **décidé, argumenté et opposable**. La règle de lecture en tête de document
dit qu'« une divergence constatée en revue est un défaut, pas une variante » — elle reste vraie, et c'est
précisément pourquoi les exceptions doivent être **écrites ici** plutôt que découvertes en revue.

**Six de ces neuf divergences sont héritées.** Le contrat `docs/contracts/issue-22.md` se termine par six
écarts « à enregistrer par la chaîne #21, propriétaire du document » — et la chaîne #21 **s'est close sans
les écrire**, ce fichier n'étant pas dans son empreinte. Le contrat #22 prédisait littéralement la
conséquence : « sans enregistrement, `review-cms` signalera des **faux défauts** ». L'issue #23 est la
première chaîne à posséder ce document après #22 : elle les enregistre.

| # | Divergence | Origine | Pourquoi c'est la bonne décision |
|---|---|---|---|
| **1** | `--ombre-decalee` et `--ombre-decalee-sombre` sont **déclarés et consommés par personne**, alors que le §6.4 les accorde et que le §8.5 les prescrivait au panneau massif et au bloc de légende | #22, arbitrage 8 | **Direction du propriétaire.** La mise en volume est faite par le **filet 2 px en tête de bande** et par le repère du `h2`. Le §8.5 est corrigé pour cesser de décrire un rendu inexistant ; **le jeton et sa description au §6.4 restent** (§12.1) |
| **2** | La légende est rendue sur **une seule colonne** sous `--bp-s`, quand le croquis §7.1 et le tableau des points de rupture en annoncent deux | #22, arbitrage 9 | Les libellés officiels ont été établis **après** le croquis : `Accès à la ZAPEF* interdite` sur ≈ 140 px empile trois lignes. **Le §10.6 règle 6 — aucun libellé tronqué, aucun défilement horizontal — l'emporte sur un croquis.** Un croquis est une intention de composition, pas une mesure |
| **3** | L'**échelle typographique d'impression en `pt`** du §13 (10,5 / 20 / 14 / 34) n'est **écrite nulle part** dans le CSS | #22, arbitrage 16 | **Aucun jeton `pt` n'existe au §12**, et une valeur brute hors jeton est interdite. Créer quatre jetons aurait modifié le §12 — gelé, sha256 épinglé — pour une échelle non vérifiée sur épreuve papier. **Non créés**, et la question reste ouverte pour la chaîne qui imprimera réellement |
| **4** | Le « **gris 45 %** » du §13 est rendu par `--statut-indisponible-encre` (`--c-charbon-doux`, **6,33:1**), jamais par `--c-trace` (**1,96:1**) | #22, arbitrage 15 | `--c-trace` est **interdit dans tout motif de statut** depuis D-17, sur mesure. Le littéral « 45 % » du §13 est une **intention de valeur**, pas un jeton ; le rendu obtenu est **plus sombre** que 45 %, ce qui ne peut qu'aider sur papier. **La mesure l'emporte sur le littéral** |
| **5** | Le **repère n'est pas forcé à l'impression**, alors que le §13 écrit qu'il s'imprime | #22, arbitrage 14 | Le §13 se contredit avec le §3.4, et le redessiner en `border` pour l'impression serait une **seconde implémentation de l'élément signature** — interdit par le §3.1, qui n'en admet **qu'une**. Divergence assumée : la trancher est une décision de design à écrire, pas un correctif de feuille d'impression |
| **6** | L'**interligne 1,2** que le §5.1 assigne à `--fs-250` n'est pas appliqué : l'étiquette hérite `--lh-corps` | #22, arbitrage 18 | **Aucun jeton ne porte 1,2** au §12 (`--lh-sous` vaut 1,15, `--lh-dense` 1,35 : ce seraient des substitutions silencieuses), et 1,15 à 13 px sur un paragraphe serait une régression de lisibilité. **Jeton non créé** — le §12 n'est pas ouvert pour ça |
| **7** | À l'impression, la **première page commence par un filet de 4 px** : `print.css` pose `--bord-fort` en tête de `.sur-sombre`, et `header.php` porte `sur-sombre` sur la barre haute | #23, arbitrage A-12 | **Conforme à la lettre du §13** (« `--c-mistral-nuit` → blanc avec `--bord-fort` en haut »). `print.css` est **gelé** (chaîne #22) : l'ouvrir depuis une chaîne sœur pour une préférence esthétique casserait la disjonction des empreintes pour un gain nul. **Divergence assumée : la modifier serait une décision de design à écrire, jamais une correction CSS silencieuse** |
| **8** | **Aucun filet de 4 px n'est rendu dans le chrome nominal** entre l'issue #23 et la chaîne « carte » | #23, arbitrage A-3 | Le filet de tête est porté par la **bande carte**, qui est **vide** tant que la carte n'est pas livrée, et la règle ne s'applique pas à une bande vide. **Aucune règle n'est violée dans l'intervalle** : le slab, le filet 2 px, le filet 1 px et le repère tiennent la composition, et le bandeau de non-officialité reste détaché (§6.3) |
| **9** | Le §8.2 prescrit qu'en cas d'indisponibilité le chiffre du jour soit **remplacé par le mot « Indisponible » en `--fs-700`** ; **le thème ne rend pas ce mot.** Le `h1` porte à sa place la chaîne du §11.3 — « Information du jour non disponible. Consultez la carte officielle de la préfecture. » | #5, arbitrage A-5 (`front-page.php`, raison écrite dans le code) | **La raison est bonne** : rendre ce mot poserait un **second bloc `--fs-700` adjacent au `h1`**, qui dit déjà exactement cela — deux affiches pour une seule information, dans la bande même que cette révision recadre. La **règle de sécurité produit reste tenue par la structure** : le chiffre n'est émis que dans le bras `disponible`, donc aucune valeur de la veille ne peut survivre à l'état d'indisponibilité. **Le §8.2 est assoupli comme le §8.5 l'a été** : il décrit ce qui est **autorisé**, pas ce qui est **livré**. Le reste du comportement — disparition du chiffre, hachure `--c-mistral` à 12 %, lien passé en bouton primaire — **reste prescrit et n'est pas en cause** |

> **[v2.5] Quinze lignes ajoutées — la table passe de neuf à vingt-quatre, et le §17 change d'échelle.**
> Les lignes **10 à 24** enregistrent les écarts de trois chaînes **livrées et commitées** au lot Épic 5 :
> l'écran de publication (#14, `ea74b4d`), l'historique (#15, `56ea7bd`) et la carte (#50, `609eaef`).
> Elles sont écrites ici pour **exactement la même raison que les six héritées de #22** : le contrat #14
> §14 point 2 le demande nommément — « sans enregistrement au §17 de MASTER, `review-cms` les comptera
> comme de faux défauts, c'est exactement ce qui est arrivé entre les chaînes #22 et #21 ».
> **La numérotation `D-n` propre à chaque contrat est conservée en clair** dans la colonne « Origine » :
> deux contrats différents ont chacun leur `D-1`, et renuméroter effacerait la seule clé qui permet de
> retrouver la justification d'origine. **Ces numéros de contrat n'ont aucun rapport avec les décisions
> `D-01`…`D-29` du §15**, qui sont celles de ce document — le piège est signalé une fois, ici.

| # | Divergence | Origine | Pourquoi c'est la bonne décision |
|---|---|---|---|
| **10** | L'écran de publication est bâti en **`<fieldset>`/`<legend>` par massif**, pas en `<table>` comme l'écrit le §7.2 (« un tableau, une ligne par massif ») | #14, **D-1** (`ea74b4d`) | Le **nom accessible du groupe est garanti** par `legend`, là où l'association en-tête ↔ cellule d'un tableau de saisie n'était pas mesurée. Et le repli à 360 px se fait **sans machinerie** de tableau responsive. §8 du brief est bloquant ; un croquis ne l'est pas |
| **11** | Pas de `role="radiogroup"` explicite : `fieldset` + radios natives | #14, **D-2** | Le **comportement** que le §7.2 prescrit — navigation par flèches, `Tab` qui sort du groupe — est **natif**. Poser le rôle explicite remplacerait `group` et fragiliserait le nommage par `legend` : on perdrait un acquis pour redire ce que le navigateur fait déjà |
| **12** | Barre d'action en `position: sticky`, pas `fixed` comme le suggère le §7.1 (« collée en bas ») | #14, **D-3** | Ne masque pas le dernier massif, ne piège pas le focus, ne casse pas à 200 %, et évite un **littéral de hauteur sans source**. C'est la lecture stricte de l'interdit §16 « barre d'action recouvrant la dernière ligne du tableau » (§14.3 entrée 4) : `sticky` la satisfait par construction |
| **13** | Le bloc `.repere` du **§3.1** est **reproduit, scopé**, dans la feuille de l'extension, alors que le §3.1 dit « une seule implémentation, réutilisée partout » | #14, **D-4** | `layout.css` est **inchargeable dans `wp-admin`** ; ne pas rendre le repère violerait les **emplacements 6 et 7** du §3.2, qui sont des emplacements de portail. Entre deux règles du même document, celle qui porte l'information l'emporte sur celle qui porte la fabrication. **Dette nommée, pas subie** : voir §18, recommandation 4 |
| **14** | Paire segmentée **sur une colonne** sous `--bp-s`, quand le §7.2 la décrit côte à côte | #14, **D-5** | Précédent **mesuré** : contrat #22 arbitrage 9, déjà enregistré en ligne 2 de cette table. Le §10.6 règle 6 — aucun libellé tronqué, aucun défilement horizontal — l'emporte sur un croquis |
| **15** | La paire segmentée reste empilée **jusqu'à `--bp-l`**, pas seulement sous `--bp-s` | #14, **D-5 bis** (révision 1 du contrat) | **Mesuré** : dans la piste `Niveau d'Accès` (2/5,5 de la largeur utile), chaque option consomme **74 px de chrome avant le premier caractère** ; à 900 px il reste **≈ 62 px** de libellé et `Accès au massif autorisé` s'empile sur **quatre** lignes. D-5 n'interdisait rien au-dessus de `--bp-s` : c'est une **extension mesurée**, pas un revirement |
| **16** | « Publication impossible : aucun statut modifié. » est rendue **après** soumission, alors que le §9.2 la donne comme l'explication d'une action au repos | #14, **D-6** | **Sans JavaScript, l'état « rien de modifié » n'est pas connu avant l'aller-retour.** L'afficher au chargement serait **faux** — et le §9.2 exige que l'action reste focusable et explique la raison, ce qu'elle fait. La contrainte 3 du `CLAUDE.md` décide ici, pas la mise en page |
| **17** | Pas d'en-tête de portail séparé de **56 px** avec date de session et déconnexion (§7.2) : une bande interne à notre conteneur | #14, **D-7** | `wp-admin` **porte déjà ce chrome**. Le redoubler serait du chrome sur du chrome, et le `h1` s'en tient à « Mise à jour des statuts » sans redire « MASSIFS · », que l'écran d'administration affiche à côté (#14, A-11) |
| **18** | L'**extension enfile quatre feuilles du thème**. Aucune section de ce document n'autorisait ce couplage | #14, **D-8** — précédent nouveau | Ratifié, et **borné à une liste fermée de quatre fichiers**. C'est le seul moyen de tenir les jetons du §12 et la géométrie du §8.1 **identiques** entre le public et le portail. Un couplage **borné et écrit** vaut mieux qu'une seconde palette qui dériverait en silence |
| **19** | L'anneau de focus du portail s'écrit en **`:is()`**, pas en `:where()` comme le §9.1 | #14, **D-9** (révision 1) | `:where()` vaut **zéro** de spécificité : dans `wp-admin` il perd contre `a:focus` et `input[type="radio"]:focus` du cœur (0,1,1) et **l'anneau ne s'afficherait pas**. `:is()` monte à 0,3,0 **sans `!important`, sans sélecteur d'ID**. **Valeurs, jetons et géométrie inchangés** : c'est le sélecteur qui change, jamais le dessin — donc la preuve du §10.5 tient entière |
| **20** | Le **repère est posé sur l'option sélectionnée** de la paire segmentée du portail : le §7.2 l'impose, le §3.3 interdit le repère « sur les champs de formulaire », et la liste fermée du §3.2 ne comporte pas cet emplacement | #14, **A-19** — **contradiction interne à ce document** | **Le §7.2 l'emporte à titre conservatoire** : plus spécifique, plus récent, et portant sur ce composant précis. La chaîne a eu raison de ne pas trancher elle-même une liste que ce document déclare fermée. **Ce n'est pas un défaut de code** : le code applique une prescription écrite. **Mais la contradiction reste ouverte dans le document** et appelle un acte formel — amender la liste fermée, ou retirer la prescription du §7.2. **Versée au §18, recommandation 1** ; jusqu'à cet arbitrage, la ligne de revue du §16 « repère hors des 6 emplacements » **ne s'applique pas à ce composant** |
| **21** | Les règles de **pastille de statut** de `composants.css` sont **recopiées et scopées** dans la feuille de l'historique : **deux copies** de la géométrie normative du §8.1 coexistent (#14 et #15) | #15, **`ARBITRAGE-CSS`** (`56ea7bd`) | Le §12 interdit de **redéfinir un jeton** ; il n'interdit pas de **réécrire une règle de classe**, et **aucune empreinte du lot ne possédait de feuille de chrome de portail partagée**. Enfiler `composants.css` en administration y importerait `layout.css` et son `box-sizing` global. **Le coût est nommé, pas découvert : risque de dérive entre les deux copies.** Il est borné par une preuve — les pastilles portent les mêmes jetons et les mêmes `data-motif` — et par la recommandation 4 du §18 |
| **22** | Le cadrage réel de la carte est **fractionnaire** — z 9,5 en desktop, z 8,75 à 360 px — alors que le §9.2.a écrit « cadrée sur l'emprise du référentiel (**z9**, vue département) » | #50, **D1**/**D2** (`609eaef`) | **Le « z9 » du §9.2.a est descriptif, jamais normatif** : il notait ce que produisait `fitBounds` en pas entier. `zoomSnap: 0.25` supprime **~184 px de bandes latérales vides**, et le fond monochrome **sans toponyme** ne floute rien à l'échelle fractionnaire. **La table des paliers reste exacte** : elle est lue par `Math.floor( getZoom() )`, partition **totale et sans trou sur les réels**, équivalente au tableau sur les entiers. **`floor`, jamais `round`** — à z 9,5, `round` basculerait au palier massif et poserait de la **peinture claire** à peine au-dessus de z9, précisément ce que la v2.4 existe pour empêcher |
| **23** | Sur une **frontière partagée avec un massif voisin contigu**, la moitié extérieure du cerne passe **sous l'aplat du voisin** et disparaît : le schéma du §9.2.a suppose « fond de carte » à l'extérieur | #50, **D7** | **Structurellement inévitable** sous la règle « aucun état d'interaction ne recouvre un aplat de statut » : le cerne vit au pane 400, sous les massifs au pane 410 ; l'y remonter le ferait peindre **sur** l'aplat du massif sélectionné lui-même. Entre « le cerne s'interrompt le long d'une frontière » et « un aplat officiel est recouvert », le second est un défaut **bloquant** et le premier ne l'est pas. **Le cerne n'est jamais le seul canal** (§9.2.a) : panneau ouvert, nom, repère, état en toutes lettres, région live |
| **24** | Le **bouton primaire du portail** n'éclaircit pas vers `--c-calcaire-ombre` au survol comme l'écrit le §9.2 : il **assombrit** vers `--c-mistral-nuit` | #14 (`ea74b4d`), écart documenté dans le code | **Mesuré, et le document a tort ici** : le bouton primaire est `--c-calcaire` sur `--c-mistral` (**6,81:1**) ; un fond porté à `--c-calcaire-ombre` ferait tomber son étiquette à **1,15:1** — elle **disparaîtrait**. L'assombrissement vers `--c-mistral-nuit` la porte à **12,66:1**, reste **dans la famille mistral**, ne touche **aucun** aplat de statut et n'introduit aucune teinte sémantique (§2.1). **Le défaut est dans ce document, pas dans le code** : le §9.2 énonce une règle de survol dérivée des surfaces claires et ne l'a jamais bornée. **Versée au §18, recommandation 2** |

**Ces vingt-quatre ne sont pas des défauts à corriger.** Aucune ne demande une modification de code, et la plupart
portent sur des fichiers **gelés** (`composants.css`, `print.css`, `tokens.css`). Elles sont écrites pour une seule raison : qu'une revue de
lot ne les compte pas **deux fois** — une fois comme écart au document, une fois comme défaut de code.

**Règle de tenue de cette section.** Une divergence en sort par une **décision écrite** (§15), jamais par
une correction silencieuse. Si le code rejoint le document, la ligne est retirée en le déclarant au journal
de révision ; si le document rejoint le code, la section concernée est amendée et la ligne disparaît de la
même façon. **Une divergence qu'on cesse simplement de mentionner redevient un faux défaut au lot suivant.**

### 17.1 [v2.4] Ce que la révision v2.4 ouvre en aval — **à corriger, pas à enregistrer**

**Cette sous-section est l'inverse de la précédente**, et c'est pourquoi elle en est séparée au lieu d'y
ajouter des lignes. Le §17 liste des écarts **qui ne sont pas des défauts**. Celle-ci liste des écarts
**qui en sont** depuis que ce document a changé : la v2.4 a déplacé la règle, le code livré applique
encore l'ancienne, et il est **conforme à ce qu'on lui avait demandé**. Rien de ce qui suit n'est un
reproche à une chaîne ; tout ce qui suit doit être **fait par la chaîne qui possède le fichier**.

**Le périmètre d'écriture de cette révision est `design-system/MASTER.md` et lui seul.** Aucun fichier de
`wp-content/`, aucun contrat de `docs/contracts/`, aucune issue n'a été touché.

| # | Ce qui doit changer | Fichier / contrat | Qui | Nature |
|---|---|---|---|---|
| **1** | **Clause A-9 du contrat `docs/contracts/issue-7.md`** — « un seul contour rendu, à 4 px (L-12) » et « double contour de 4 px pour la sélection, §3.2 emplacement 5 » : **reprend l'ancienne règle du §9.2 et doit être amendée.** L'emplacement 5 n'existe plus (D-28), la sélection est **le cerne** (§9.2.a). La partie d'A-9 qui reste **vraie et doit survivre à l'amendement** : « le contour suit le focus, pas le panneau — après Échap, il reste » | `docs/contracts/issue-7.md` | chaîne propriétaire du contrat #7 | **Amendement de contrat gelé.** Ce document ne l'amende pas lui-même : un contrat gelé se rouvre par sa chaîne |
| **2** | **Clause A-16 du même contrat** — l'anneau de focus générique **reste** posé sur le polygone. Rien à changer sur le fond ; la clause **renvoie à A-9** et sa lecture doit suivre l'amendement | `docs/contracts/issue-7.md` | idem | Mise en cohérence de renvoi |
| **3** | **`carte.css`** — `.carte__contour` / `.carte__contour-trace` à 4 px avec le pane décalé, `stroke-width: 4px` en littéral au survol et sur `--courant`, et le littéral **L-12** de l'en-tête : tout cela applique la v2.3. À refaire selon §9.2.a, en **jetons** ; **L-12 disparaît de la liste des littéraux sans jeton** — les cinq jetons du §12 le remplacent, ainsi que `--bord-selection` pour la bordure du bouton de jour | `wp-content/themes/massifs/assets/css/carte.css` | chaîne « carte » à venir | Correction |
| **4** | **`carte.js`** — poser la **classe de palier** sur la racine au montage puis sur `zoomend` (écouteur **déjà** présent, garde de densité A-13) ; renommer le pane du repère en pane du cerne et y rendre **les deux** couches de contour, **sous** le pane des massifs. Toujours **`classList` seul** : aucune valeur numérique de présentation en JS, la borne de palier est un **entier de zoom** | `wp-content/themes/massifs/assets/js/carte/carte.js` | idem | Correction |
| **5** | **`tokens.css`** — cinq jetons ajoutés dans `:root`, deux classes de palier en fin de fichier (§12). **Le sha256 épinglé et l'invariant « 111 propriétés » du contrat #4 tombent** et deviennent **116 / 133** | `wp-content/themes/massifs/assets/css/tokens.css` et `docs/contracts/issue-4.md` | chaîne propriétaire du contrat #4 | **Invariant de contrat à remplacer**, déclaré ici, jamais modifié d'ici |
| **6** | **Assertion de recette** : reproduire la vue z9 sur **Regagnas** — massif petit et filamenteux — et vérifier que l'**aplat de statut et son motif restent visibles** sur le massif sélectionné, que le halo ne fusionne pas d'un filament au suivant, et qu'aucune peinture claire n'apparaît à ce palier. À faire **dans le navigateur**, pas dans une suite de tests : c'est ce qui a manqué à la v2.3 | recette de la chaîne « carte » | idem | **Preuve, pas correction** |

**Ce qui n'est pas dans cette liste, et qui doit le rester** : la clause **A-9 du contrat
`docs/contracts/issue-9.md`** — celle-ci porte sur les **toponymes** et n'a **aucun rapport** avec la
sélection. L'homonymie de numéro entre deux contrats est un piège de relecture ; il est signalé une fois
ici pour qu'aucune chaîne ne rouvre le mauvais fichier.

> **[v2.5] Les six lignes sont closes — et le périmètre d'amendement était plus large que ce qu'elles
> disaient.** La chaîne **#50** (`docs/contracts/issue-50.md`, commit **`609eaef`**) a exécuté les six
> lignes le 15 août 2026. **Elles sont marquées closes, pas supprimées** : la règle de tenue du §17
> demande qu'une ligne disparaisse quand le code rejoint le document, mais l'appliquer ici effacerait
> **la leçon**, qui est que cette sous-section était **sous-dimensionnée**. On garde donc la trace, une
> fois, et pour ce motif écrit.
>
> **Ce que les lignes 1 et 2 avaient manqué.** Elles n'amendaient que les clauses **A-9** et **A-16** du
> contrat #7. Or **quatre autres endroits du même contrat restataient la même règle obsolète**, et les
> laisser en l'état aurait fait lire l'implémentation conforme comme une infraction — c'est exactement le
> motif de l'amendement, appliqué à moitié. La chaîne #50 a **élargi le périmètre de son côté**
> (arbitrage **A-50.5**, signalé à `lead-design-cms`) ; **la liste réelle des amendements est celle-ci** :
>
> | Clause du contrat #7 | Ce qui portait l'ancienne règle | État au 15 août 2026 |
> |---|---|---|
> | **A-9** | « un seul contour rendu, à 4 px (L-12) », duplication décalée, renvoi à l'emplacement 5 | **Amendée.** Survit : « le contour suit le **focus**, pas le panneau » |
> | **A-16** | renvoi à A-9 | **Renvoi mis en cohérence.** Le fond ne change pas : l'anneau générique **reste** sur le polygone |
> | **A-19** | `z-index` explicite sur les deux panes, justifié par « la signature du §3.2 emplacement 5 » | **Amendée — et la règle devient plus critique** : ce n'est plus la signature qui en dépend, c'est **la conformité**. Panes 400 < 410 inversés, le cerne se peindrait **sur** l'aplat de statut |
> | **§8.2**, table des classes | `.carte__contour` / `.carte__contour-trace`, pane `--repere` | **Amendée** : `.carte__cerne` / `.carte__cerne-separateur`, pane `--cerne`, plus les trois classes de palier sur la racine |
> | **§8.4**, exigences **2, 5, 6, 15** | liseré « 2 px », survol « de 2 à 4 px », contour de sélection | **Amendées** : `--carte-lisere` par palier ; survol = **1,5 ×** le liseré du palier, un **rapport** et non trois nombres |
> | **Interdit 7** (« aucun zoom en dur ») | contredisait « la borne du palier est un entier de zoom » (§9.2.a) | **Portée précisée** : l'interdit protège le **cadrage**, pas un seuil de présentation (#50, **A-50.3**). MASTER l'emporte ; l'amendement **précise, il n'affaiblit pas** |
>
> **Ligne 5 close également** : les cinq jetons sont dans `tokens.css` et l'invariant du contrat #4 est
> remplacé — **116 propriétés dans `:root`, 133 dans le fichier**. La chaîne #50 signale (**A-50.6**) que
> ces valeurs sont **aussi épinglées hors de son empreinte** — `tests/rendu/recette-rendu.mjs` et les
> contrats #11, #21, #23 : leur reprise appartient à l'orchestrateur, jamais à ce document.
> **Ligne 6 close par une preuve, pas par du code** : la recette de #50 exige la vue Regagnas à z 9,5 dans
> **Chrome**, aplat et motif entiers, **aucun pixel calcaire** (`--carte-cerne-clair` calculé = `0`), halo
> charbon ~2,25 px qui ne fusionne pas d'un filament au suivant.
>
> **Leçon de méthode, opposable à la prochaine révision.** Quand une révision déplace une règle, elle doit
> lister **tous** les endroits qui la restatent — clauses d'arbitrage **et** tables de classes **et**
> listes d'exigences **et** interdits — et pas seulement celui qui l'énonce le plus visiblement. Une règle
> obsolète oubliée dans une exigence numérotée est **indiscernable** d'une règle en vigueur pour la chaîne
> qui la lira.

**Une ligne sort de cette sous-section quand le code rejoint le document** — et alors elle ne migre pas au
§17 : elle disparaît. Le §17 est fait pour les écarts **assumés durablement** ; celui-ci pour les écarts
**ouverts par une révision et destinés à se fermer**. Confondre les deux transformerait une dette en
exception permanente.

---

## 18. [v2.5] À traiter à la prochaine révision — **manques du document, pas défauts du code**

**Ce qu'est cette section, et pourquoi elle existe.** Le §17 enregistre des écarts **tranchés**. Le §17.1
liste des corrections **à faire dans du code**. Celle-ci est la troisième catégorie, et la seule qui manquait :
des **trous dans ce document** — des endroits où une chaîne d'intégration a dû décider seule parce que
MASTER ne disait rien, ou disait quelque chose de faux. **Aucune ne se corrige dans un fichier de
`wp-content/`** ; toutes se corrigent ici, à la révision suivante.

> **Pourquoi rien n'est corrigé dans cette passe, et pourquoi ce n'est pas une dérobade.** Le §12 et
> **toute déclaration de propriété personnalisée** sont **gelés** pour cette révision : `tokens.css` en est
> la transcription **octet pour octet**, son **sha256** est épinglé et la recette du lot vérifie **116
> propriétés dans `:root`, 133 dans le fichier** — une passe de recette tournait **pendant** l'écriture de
> cette révision. **Un seul jeton ajouté, retiré ou renommé rendrait la recette rouge pour une raison
> étrangère au code testé.** La contrainte est **temporaire et assumée par le propriétaire**. Les
> recommandations ci-dessous sont donc **chiffrées et argumentées jusqu'au point de décision**, et
> s'arrêtent là. **Une recommandation chiffrée qui attend une révision vaut mieux qu'un jeton posé dans une
> fenêtre où personne ne peut le vérifier** — c'est la même discipline que le §17 divergences 3 et 6, où
> deux jetons ont déjà été refusés pour ce motif.

**Règle de tenue.** Chaque ligne indique **ce qui manque**, **ce que ça a coûté**, **ce qui est proposé** et
**le coût en jetons**. Une ligne sort d'ici par une **section amendée** et une **décision au §15**, jamais
par un simple oubli.

### 18.1 · Recommandation 1 — trancher le repère sur l'option sélectionnée du portail

**Coût en jetons : zéro. Coût réel : un acte formel sur une liste fermée, qui n'appartient pas à une passe
d'enregistrement.**

**Ce qui manque.** Le **§7.2** prescrit un repère sur l'option sélectionnée de la paire segmentée ; le
**§3.3** interdit le repère « sur les boutons, les champs de formulaire » ; la liste **fermée** du §3.2 ne
comporte pas cet emplacement. Trois textes, deux réponses. La chaîne #14 a arbitré en faveur du §7.2
(**A-19**) — plus spécifique, plus récent, portant sur ce composant précis — et a eu **raison de ne pas
amender elle-même** une liste que ce document déclare fermée. Enregistré au **§17, ligne 20**.

**Ce que ça coûte aujourd'hui** : la ligne de revue du §16 « repère hors des emplacements du §3.2 » vise un
composant livré et conforme à une prescription écrite. Elle est **neutralisée par écrit**, mais une
neutralisation par exception est exactement ce qu'une liste fermée existe pour éviter.

**Les deux sorties, et elles seules.**

- **(a) Amender la liste fermée** — premier **ajout** depuis sa création : un huitième numéro, **8**, « sur
  l'option sélectionnée d'une paire segmentée de statut ». **Argument pour** : ce n'est pas un champ de
  formulaire quelconque, c'est une **option de statut** portant un **libellé officiel verbatim** en
  famille d'affichage (§5.1, borne (a)) — le repère y signale ce qu'il signale partout ailleurs. **Argument
  contre** : le §3.3 devrait être rouvert au même moment, et la formule « jamais sur les champs de
  formulaire » devenir « jamais sur les champs de formulaire, **hors option de statut sélectionnée** ».
- **(b) Retirer la prescription du §7.2.** La sélection y est déjà portée par **trois** marqueurs non
  chromatiques simultanés — point du radio natif, liseré porté de 2 px à `--bord-selection` (4 px), et le
  libellé officiel toujours présent à côté de l'aplat. **Le repère est le quatrième, et il est le seul
  redondant.** Coût : une modification de CSS dans une empreinte d'extension, donc **une ligne au §17.1**,
  pas une correction gratuite.

**Recommandation motivée : (a).** Le §3.2 emplacement 3 place déjà le repère devant **chaque puce de statut**
de la légende et de la liste du jour ; une option de statut sélectionnée est le même objet, dans un écran
d'outil. Retenir (b) créerait une **asymétrie** entre public et portail sur la signature du site, alors que
la borne (a) du §5.1 vient précisément d'établir l'inverse pour la typographie des mêmes libellés.
**Le propriétaire tranche : un ajout à une liste fermée n'est pas un arbitrage d'agent.**

### 18.2 · Recommandation 2 — borner la règle de survol du §9.2 aux surfaces claires

**Coût en jetons : zéro. Défaut du document, mesuré.**

**Ce qui manque.** Le §9.2 écrit, sans borne : « Survol → fond `--c-calcaire-ombre` (boutons, lignes) ».
Appliquée **à la lettre** au bouton primaire du portail, la règle **efface son étiquette** :

| Élément | Repos | Survol prescrit par le §9.2 | Contraste au survol | Verdict |
|---|---|---|---|---|
| Bouton primaire (`--c-calcaire` sur `--c-mistral`) | **6,81:1** conforme | fond → `--c-calcaire-ombre` | **1,15:1** | **échec — l'étiquette disparaît** |
| Le même, contournement livré | 6,81:1 | fond → `--c-mistral-nuit` | **12,66:1** | conforme |

**Ce que ça a coûté** : `dev-ux-cms` a dû contourner et **documenter l'écart dans le code**. Le
contournement est bon — il reste dans la **famille mistral**, ne touche aucun aplat de statut, n'introduit
aucune teinte sémantique (§2.1) et **améliore** le contraste. Enregistré au **§17, ligne 24**.

**Ce qui est proposé, à écrire tel quel au §9.2.** Une règle en deux branches, **dérivée et non choisie** :

> Le survol **déplace la surface d'un cran dans sa propre famille, dans le sens qui éloigne du texte**.
> Sur une surface **claire** (`--c-calcaire`), le cran est `--c-calcaire-ombre` — le texte est sombre,
> assombrir le fond le rapprocherait. Sur une surface **de chrome** (`--c-mistral`), le cran est
> `--c-mistral-nuit` — le texte est clair, éclaircir le fond le ferait disparaître.

**Aucun jeton n'est nécessaire** : les quatre valeurs existent déjà au §12. **Ce n'est pourtant pas une
correction de rédaction** — c'est une règle d'interaction nouvelle, applicable à tout le site, et elle doit
être vérifiée sur chaque surface du §10.5 avant d'être écrite au présent. C'est une révision, pas une passe
d'enregistrement.

### 18.3 · Recommandation 3 — une échelle typographique propre au portail

**Coût en jetons : zéro pour l'option A recommandée ; deux jetons pour l'option B.**

**Ce qui manque.** Le §5.1 donne **une seule** échelle, calibrée pour le **web public**, et la règle de
portée du §5.1 range le chrome du portail « en famille de texte » **sans rien dire de ses tailles**. Le
portail n'est pourtant pas une page publique : il vit dans `wp-admin`, dont la base est **13 px**, sur des
écrans larges, et il n'a **aucune affiche**.

**Ce que ça a coûté** : les chaînes #14 et #15 ont dû improviser, chacune de son côté, et elles ont abouti
au même contournement **sans se voir** — `font-size: min(var(--fs-700), 3rem)` sur le `h1` de portail, dans
`ecran-publication.css` **et** dans `historique.css`. Elles ont emprunté le **plafond de consommation** que
le §5.1 réserve au `h1` **public**. Deux improvisations convergentes sont une **règle manquante**, pas une
coïncidence — et rien n'a plafonné `--fs-600`, employé pour les `h2` de portail : il monte à **2,5rem
(40 px)** sur un écran d'outil, pour un titre en famille de **texte**.

**Option A — recommandée, zéro jeton : une table de rôles et deux plafonds de consommation, au §5.1.**

| Rôle de portail | Jeton | Plafond de consommation | D'où vient la valeur |
|---|---|---|---|
| `h1` d'écran | `--fs-700` | **`3rem`** | plafond public **déjà** écrit au §5.1 — il est simplement **étendu au portail**, ce que les deux chaînes ont fait spontanément |
| `h2` de section | `--fs-600` | **`2.125rem`** (34 px) | **milieu exact** de `1.75rem`–`2.5rem`, par la **même règle de dérivation** que `--fs-700` → `3rem` et `--fs-800` → `5.75rem`. Dérivé, pas choisi |
| Corps, cellules, libellés d'option | `--fs-300` | — | déjà en service, aucun changement |
| Méta, en-têtes de colonne, pagination | `--fs-200` | — | déjà en service |
| Étiquette de statut | `--fs-250` | — | **famille d'affichage**, borne (a) du §5.1 — ne change pas |

**Pourquoi A plutôt qu'une échelle dédiée** : le portail partage les jetons du public par **D-8/§17 ligne
18** (l'extension enfile quatre feuilles du thème) ; lui donner sa propre échelle **doublerait** la surface
à prouver au §10 pour un écran qui n'a que **cinq rôles typographiques**. Et un plafond de consommation
**ne touche pas le jeton** : c'est le mécanisme que la v2.3 a déjà validé, en `rem` et jamais en `px`, donc
sans plafonner la réponse au zoom (WCAG 1.4.4).

**Option B — deux jetons, non recommandée** : `--fs-portail-titre` et `--fs-portail-section`, valeurs fixes
`1.75rem` / `1.375rem`, sans `clamp` (un écran d'outil n'a pas besoin de fluidité). **Coût : +2 propriétés
dans `:root`**, donc **118 / 135**, donc **sha256, invariant du contrat #4 et recette du lot à reprendre** —
pour un gain que l'option A obtient sans toucher un octet de `tokens.css`.

### 18.4 · Recommandation 4 — un traitement de focus propre au SVG (V-50.1)

**Coût en jetons : zéro pour la piste recommandée ; à confirmer par mesure avant écriture.**

**Ce qui manque.** MASTER ne spécifie **aucun** traitement de focus propre au SVG. Le §9.1 pose un anneau
générique par `outline` + `box-shadow` ; sur un `<path>`, Chrome dessine l'`outline` autour de la **boîte
englobante** et **ne rend pas** le `box-shadow`. Sur **Regagnas à z9**, l'anneau paraît donc comme un
**rectangle de 94 × 55 px** posé sur une forme filamenteuse — visuellement **plus fort que le cerne**, qui
n'est vu qu'à **1,5 px** à ce palier.

**Ce n'est pas le défaut de l'issue #50** — `:focus-visible` ne s'arme pas au clic souris, et le cadre noir
observé alors était le contour charbon décalé, supprimé depuis. C'est **conforme à A-16** et **volontairement
non corrigé** par la chaîne #50 : retirer l'anneau imposerait un `outline: none` dont le seul remplaçant
serait **un tracé créé par le JS** — si la duplication échoue, le focus devient invisible et **WCAG 2.4.7
tombe**. Le raisonnement est bon ; c'est **la spécification qui manque**.

**Ce qu'il faudra trancher, et l'ordre dans lequel le mesurer** :

1. **Un rectangle englobant est-il un indicateur de focus acceptable sur une forme filamenteuse ?** WCAG
   2.4.7 exige un focus **visible**, pas un focus **ajusté à la forme** : la réponse est probablement oui,
   et alors **il n'y a rien à écrire d'autre que cette phrase**, ce qui fermerait la question à coût nul.
2. Si la réponse est non : la seule voie sans JS est un **troisième tracé CSS** sur le polygone focusé —
   c'est-à-dire une **quatrième couche** après cerne, séparateur et liseré, à chiffrer par palier comme
   l'a fait le §9.2.a, et à mesurer contre le rappel du §9.1 (calcaire **2,42:1** sur le vert officiel,
   charbon **6,10:1** ; sur le fond de carte, calcaire **1,07:1**, charbon **13,79:1**). **Coût probable :
   deux jetons d'épaisseur**, plus deux valeurs dans chacun des deux blocs de palier — **et donc la
   recette à reprendre**. C'est précisément pourquoi la question n'est pas ouverte dans cette passe.
3. **À vérifier dans le navigateur avant toute écriture**, aux zooms 9 et 11, sur un massif filamenteux
   **et** sur un massif compact. C'est la règle du §16 issue de la passe 2 quinquies, et elle s'applique
   d'abord à ce document.

### 18.5 · Recommandation 5 — la dette de duplication du chrome de portail

**Coût en jetons : zéro. Coût réel : une issue d'intégration, hors de ce document.**

**Ce qui manque** : aucune **feuille de chrome de portail partagée** n'existe. Conséquence enregistrée deux
fois au §17 — lignes **13** et **21** : le bloc `.repere` du §3.1 est **reproduit** dans la feuille de #14, et
les règles de pastille de `composants.css` sont **recopiées et scopées** dans celle de #15. **Deux copies
d'une géométrie normative** (§8.1) et **deux implémentations de l'élément signature** (§3.1, qui n'en admet
**qu'une**) coexistent aujourd'hui.

**Ce que ça coûte** : rien à l'écran aujourd'hui — les copies sont fidèles et portent les mêmes jetons.
**Le risque est la dérive** : le jour où le §8.1 ou le §3.1 change, trois fichiers doivent changer, et rien
dans le code ne le rappelle. C'est exactement le motif pour lequel le §3.1 dit « une seule implémentation ».

**Ce qui est proposé** : une issue d'intégration extrayant `.repere` et les marques de statut dans une
feuille **consommable par le thème et par `wp-admin`** — ce qui **lèverait la ligne 13** et **la ligne 21**
d'un même geste. **Ce document ne la crée pas et ne la porte pas** : il en enregistre le besoin et le
motif. Tant qu'elle n'existe pas, **toute modification du §3.1 ou du §8.1 doit être annoncée comme touchant
trois fichiers**, et cette phrase-ci est l'endroit où on s'en souviendra.






