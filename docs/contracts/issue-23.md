# Contrat d'interface — Issue #23 — Appliquer le recadrage typographique « direction A » et la règle de portée

**Gelé le 12 août 2026 par `lead-issue-cms`.** Liant à partir de ce point pour les trois agents de la
chaîne : `dev-ux-cms` (`layout.css`), `dev-front-cms` (`front-page.php`, `templates/footer.php`),
`lead-design-cms` (`design-system/MASTER.md`).

> **Ce contrat n'a pas la forme habituelle.** L'issue #23 ne touche **aucune** fonction de lecture,
> **aucune** route REST, **aucun** état de statut : elle ne franchit pas la frontière thème ↔ extension.
> Sa valeur est ailleurs — dans les **arbitrages**, les **valeurs normatives** que le document doit
> porter pour que le CSS ait le droit de les écrire, et les **interdits**. C'est la forme du contrat #21,
> qui est le précédent applicable.

---

## Empreinte fichiers — stricte

**Écriture autorisée, et rien d'autre :**

| Fichier | Agent | Ampleur réelle |
|---|---|---|
| `wp-content/themes/massifs/assets/css/layout.css` | `dev-ux-cms` | 8 déclarations, 1 sélecteur, les commentaires afférents |
| `wp-content/themes/massifs/front-page.php` | `dev-front-cms` | **un caractère** (l. 94) |
| `wp-content/themes/massifs/templates/footer.php` | `dev-front-cms` | **non modifié** — voir A-6 |
| `design-system/MASTER.md` | `lead-design-cms` | révision **v2.3** |
| `docs/contracts/issue-23.md` | `lead-issue-cms` | ce fichier |

**Interdits absolus** — arbre de travail partagé, mono-branche, aucune isolation. Un fichier écrit hors de
cette liste est une perte irrécupérable pour une chaîne sœur :
`assets/css/tokens.css` (gelé, sha256 épinglé, 111 propriétés, contrat #4) · `assets/css/composants.css`
et `assets/css/print.css` (chaîne #22, commit `6bb8b4d`) · `templates/parts/**` · `templates/header.php` ·
`functions.php` · `docs/BRIEF.md` · `CLAUDE.md` · `assets/fonts/**` · `docs/contracts/issue-2..22.md`.

---

## Fonctions de lecture exposées par l'extension

**Sans objet.** Aucune fonction `massifs_*` n'est créée, modifiée, ni même appelée différemment. Les
appels existants de `front-page.php` (`massifs_codes`, `massifs_jour_courant`, `massifs_synthese_du_jour`,
`massifs_fraicheur`, `massifs_horodatage`, `massifs_attribution_statuts`, `massifs_journaliser`,
`massifs_partie`) et de `templates/footer.php` (`massifs_menu`, `massifs_attribution`,
`massifs_attribution_statuts`) sont **inchangés, garde comprise**.

## Routes REST

**Sans objet.** Aucune route créée, modifiée ni consommée.

## États spéciaux

**Sans objet au sens habituel** — aucun état n'est émis, rendu ni interprété différemment par cette issue.
Un seul point de contact, à ne pas casser :

| État | Émis par le serveur | Rendu par le thème | Ce que #23 y change |
| `information_indisponible` | `etat_global === 'indisponible'` | bras `indisponible` de `front-page.php`, `h1` porteur d'un lien | **la couleur du lien du `h1`** passe de `--c-mistral-clair` (7,73:1) à `--c-calcaire` (12,66:1) — A-8 |
| `hors_saison` | `etat_global === 'hors_saison'` | bras `hors_saison` | rien |
| `donnee_perimee` | `massifs_fraicheur()['perimee']` | `.ardoise__peremption`, phrase **ajoutée** | rien |
| `non_encore_publie` | `etat_global === 'non_encore_publie'` | bras `non_encore_publie` | **la seule espace insécable** de la l. 94 — A-9 |
| `couche_effis_indisponible` | — | — | sans objet, la couche n'existe pas |

**La règle de sécurité produit reste tenue par la structure et n'est pas touchée** : le chiffre du jour
n'est écrit que dans le bras `disponible` de `front-page.php`. Aucune modification de cette issue ne peut
faire apparaître un statut périmé comme courant.

## Chaînes fournies par le serveur

Inchangées, et **aucune n'est rédigée par cette issue**. Les libellés de niveau, les consignes, les
attributions et la phrase de fraîcheur restent la propriété du serveur. La seule chaîne touchée est
`Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h.` — chaîne du §11.3
rédigée par le site, dont **un seul caractère d'espacement** change de codet (A-9). **Aucun mot n'est
réécrit.**

---

## LA RÈGLE DE PORTÉE — texte normatif, à reproduire tel quel dans `MASTER.md` §5.1

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

### Amendement formel de la liste fermée du §3.2

Le §3.2 (« Où il apparaît — liste fermée ») compte **sept emplacements** et le document déclare cette
liste **fermée**. L'amender est donc un **acte formel**, et il est consigné comme tel :

> **Emplacement n° 2, avant :** « Devant **chaque `h2`** du site (couleur `--c-mistral-nuit`). »
>
> **Emplacement n° 2, après :** « Devant **chaque `h2` en portée de la famille d'affichage** — bande
> d'information du jour, légende, titres de statut (couleur `--c-mistral-nuit`). **Les `h2` du chrome, des
> pages éditoriales et du portail ne portent pas de repère.** »

**La liste reste fermée et compte toujours sept emplacements.** Cet amendement **restreint** un
emplacement, il n'en ajoute ni n'en retire aucun. Motif : le repère est la signature de l'information de
statut ; posé devant un `h2` éditorial en famille de texte, il ne signale plus rien et dilue la signature —
exactement le raisonnement par lequel le §3.2 avait déjà refusé les jalons ZAPEF, et le §3.3 les `h3`.

---

## Arbitrages

Chaque désaccord tranché, la décision retenue, sa raison. **Aucun n'est rouvrable par un agent aval.**

### A-1 — Le renoncement aux capitales s'applique **dans le même commit**, code et document ensemble

`leaddev-front-cms` proposait de **laisser** `text-transform: uppercase` sur `h1`/`h2` dans `layout.css`,
au motif que l'application de D-26 est normative et appartient au document.

**Refusé.** La tâche 1 de l'issue dit littéralement « Retirer `text-transform: uppercase` des règles
`h1`/`h2` dans `layout.css` », et la v2.2 a déclaré **deux fois** que la contradiction transitoire serait
fermée par #23. Séparer les deux moitiés produirait soit un document en avance sur son code, soit
l'inverse — c'est-à-dire une **quatrième** version de la contradiction que cette issue existe pour
éteindre. Les deux moitiés partent dans **le même commit** ; c'est précisément le rôle du gel de contrat.

**Décision : `dev-ux-cms` retire les deux `text-transform: uppercase` (`layout.css` l. 89 et l. 95), et
`lead-design-cms` ferme §5.1, §7.3, §8.4, §14.3 entrée 5 (b) et §16 dans la même passe.**

### A-2 — Bride typographique : `min()`, plafond en `rem`, milieux exacts

`--fs-700` et `--fs-800` restent **intouchés** dans `tokens.css`. La recomposition « dans la moitié basse
du `clamp` » se fait **en consommation** :

- `h1` → `min(var(--fs-700), 3rem)` — `3rem` est le **milieu exact** de 2.25rem–3.75rem ;
- `.ardoise__chiffre-valeur` → `min(var(--fs-800), 5.75rem)` — **milieu exact** de 3.5rem–8rem.

Les deux valeurs sont **dérivées, pas choisies** : « la moitié basse » désigne littéralement la borne
médiane. Elles sont écrites d'abord dans `MASTER.md` §5.1, puis reproduites dans `layout.css` sous le
précédent **A-13** (« on ne crée pas de jeton, on reproduit la référence »), déjà employé trois fois dans
ce fichier pour les mesures du repère.

**Le plafond est en `rem`, jamais en `px`** : un plafond en `rem` **recule** quand l'utilisateur grossit
son texte, il ne peut donc pas plafonner la réponse au zoom (WCAG 1.4.4). Un plafond en `px` la
plafonnerait — ce serait un défaut bloquant. Le terme médian `rem + vw` du `clamp` reste **intact** : la
défense écrite au §14.3 entrée 2 est honorée, pas contournée.

`--fs-600` (`h2`) **n'est pas bridé** : il plafonne déjà à 2.5rem et n'est pas une affiche.

**Conséquence mesurée, à confirmer par le dev** : la bride est **inerte à 360 px** (37,12 px et 59 px
inchangés) et n'entre en action qu'à partir de ≈ 700 px (h1) et ≈ 800 px (chiffre).

### A-3 — Filets : le 4 px unique va à l'**entrée du héros**

L'issue impose « 4 px une fois par page ». `MASTER.md` §6.3 en prescrit aujourd'hui trois sur l'accueil
(haut **et** bas de la carte, bandeau de non-officialité).

**Décision :**

| Filet | Avant | Après |
|---|---|---|
| Haut de la bande carte | `--bord-fort` | **`--bord-fort`** — l'unique 4 px du chrome nominal |
| Bas de la bande carte | `--bord-fort` | **`--bord-moyen`** — entrée forte, sortie discrète |
| Bandeau de non-officialité | `--bord-fort` | **`--bord-moyen`** |
| Tête de bande de la légende | `--bord-moyen` | inchangé — `composants.css`, **gelé** |
| Intra-cellule de la liste | `--bord-fin` | inchangé — `composants.css`, **gelé** |
| Bas du bandeau d'alerte | `--bord-fort` | inchangé — `composants.css`, **gelé** |

La carte **reste encadrée** (croquis §7.1 tenu en esprit) sans doubler le 4 px, et le filet le plus fort du
site marque l'entrée du héros — cohérent avec le §1 (« la carte est le héros absolu »).

**Reformulation de la règle de quantité, parce qu'une règle qui ment un jour sur trois n'est pas une
règle** : « **une occurrence dans le chrome nominal de la page** ; le bandeau d'alerte, état exceptionnel,
porte le sien ». Le `--bord-fort` de `.bandeau-alerte` vit dans `composants.css`, gelé : il est hors
d'atteinte, et la règle doit le dire plutôt que de prétendre l'ignorer.

**§6.3 et le croquis §7.1 sont amendés en conséquence** — sans quoi ces deux lignes de CSS seraient de
**vrais** défauts de revue, pas des faux positifs.

**Conséquence datée, acceptée en connaissance de cause** : `.bande--carte:has(*)` ne matche pas
aujourd'hui (la bande carte est vide). Entre #23 et la chaîne « carte », **le chrome nominal ne rend aucun
filet de 4 px**. Aucune règle n'est violée dans l'intervalle : le slab, le 2 px, le 1 px et le repère
restent. Le bandeau de non-officialité reste détaché par son slab et son filet 2 px en `--c-charbon`
(12,79:1 d'encre) ; le §5.6 du brief exige sa **présence**, jamais une épaisseur.

### A-4 — La clause « `border-left` sur les enfants de grille » n'a **aucun site d'application**

`layout.css` ne contient **aucun** `display: grid` (deux `display: flex` seulement). Les seules grilles du
thème vivent dans `composants.css`, gelé. **C'est constaté, pas oublié.** Interdit d'inventer une grille
pour satisfaire la clause.

**Et la clause « jamais par des `<div>` positionnés en absolu » vise les filets, jamais `.repere`.** Le
seul objet absolument positionné du thème est l'élément de signature (§3.1). Le réécrire en bordures
serait une **seconde implémentation de la signature**, contre `MASTER.md` §3.1 et l'arbitrage 14 du
contrat #22. **Formellement interdit.**

### A-5 — Rythme vertical asymétrique : `start > end`, et pourquoi c'est ce sens-là

Un filet est un `border-block-start` : l'espace **au-dessus** de lui est le `padding-block-end` de la bande
précédente, l'espace **en dessous** son propre `padding-block-start`. « Resserré au-dessus, généreux en
dessous » se traduit donc par `padding-block: <généreux> <resserré>`, et **non l'inverse** — c'est
l'erreur naturelle, elle est nommée ici pour être évitée.

- `.bande__contenu` : `padding-block: var(--esp-section) var(--esp-2xl)`
- `.bande--ardoise .bande__contenu` (≥ 900 px) : `padding-block: var(--esp-4xl) var(--esp-3xl)`
- `.bande--non-officialite > .bande__contenu` : **reste symétrique** en `--esp-m` — bande fine,
  l'asymétrie y serait du bruit.

Toutes les valeurs restent dans l'échelle **fermée** du §6.1. À 360 px, `--esp-section` vaut 48 px : le
rythme y redevient symétrique 48/48, ce qui est **voulu** — l'asymétrie est un geste de composition large,
pas de mobile.

**La règle ne s'applique pas au filet 2 px de la légende** : c'est un filet **intra-bande**, porté par
`.legende` dans `composants.css` (gelé), et l'espace sous lui (`--esp-l`) est hors d'atteinte. Interdit
d'ajouter un override `.bande--legende > .bande__contenu` pour compenser : ce serait un couplage de
`layout.css` à une bande de composant, et un arbitrage non tranché.

### A-6 — Le pied n'est pas modifié, et c'est la bonne réponse

`templates/footer.php` fait **déjà** ce que la contrainte impose : il rend l'**emplacement** de menu `pied`
(silencieux tant qu'aucun menu n'est affecté) et deux attributions **fournies par le serveur**, gardées et
échappées. Relu ligne à ligne : aucun défaut local, structure valide, un seul landmark `contentinfo`.

Les pages « Mentions légales », « Accessibilité » et « La démarche » **n'existent pas**. Un lien codé en
dur produirait une 404 dans le chrome de **chaque** page du site.

**Décision : `templates/footer.php` n'est pas modifié.** La convention de pied du web public s'écrit dans
`MASTER.md` §7.3 comme une **convention de menu administrable** — l'administrateur affecte les trois
entrées le jour où les pages existent, et elles apparaissent sans qu'une ligne de thème change. C'est le
comportement WordPress natif, et il garde la politique de navigation chez le propriétaire du site plutôt
que dans le code.

**Interdits attachés, opposables :** aucun slug inventé, aucun libellé de menu inventé, **aucun taux ni
qualificatif de conformité RGAA** (voir Q-1), **aucune phrase « zéro cookie »** (elle figure au croquis
§7.1 mais n'a aucune chaîne normative au §11.3, qui est la liste **fermée** des phrases que le site a le
droit de rédiger).

### A-7 — Frise : **abandonnée**, pas différée ; jetons **orphelins**, pas supprimés

La frise n'existe **nulle part** dans le code : aucun gabarit ne l'émet, aucune règle ne la stylise. La
tâche 6 est donc vide côté code — **aucune suppression n'est fabriquée**.

Côté document elle est entière : 17 points d'ancrage dans `MASTER.md`. **Décision : retrait définitif**,
consigné en **D-27**. Pas « différée » : laisser la frise en attente maintiendrait 17 prescriptions vivantes
et inviterait une chaîne future à la construire.

`--frise-l` et `--frise-h` **restent déclarés** dans `tokens.css` et **restent au §12**, exactement comme
`--ombre-decalee` : le sha256 est épinglé et le compte de **111 propriétés** est un invariant du contrat
#4. Les retirer casserait les deux. Ils deviennent **déclarés et consommés par personne**, et ce statut est
enregistré en **§12.1**, qui existe déjà pour ça — jamais dans le bloc de code du §12, qui est fermé.

**Conséquence à consigner, sinon un refacto futur la détruira** : l'arbitrage 13 du contrat #22
(`print.css`, protection du liseré sous `.sur-sombre`) était justifié par « bloquant **le jour de la
frise** ». La frise étant abandonnée, cette règle devient **définitivement latente** — elle n'est pas morte
pour autant, la barre d'action du portail (§7.2) étant un chrome sombre portant des pastilles. À écrire.

### A-8 — `.ardoise__titre a` : rendue **opérante**, pas retirée

La règle `.ardoise__titre a { color: inherit }` (l. 226) est **inopérante** : à spécificité égale (0-1-1)
elle perd au *source order* face à `.sur-sombre a` (l. 341). Le lien du `h1` rend `--c-mistral-clair`
(7,73:1) au lieu du `--c-calcaire` hérité (12,66:1). Les deux passent AA — ce n'est pas bloquant — mais la
règle **ment sur son intention**.

**Décision : la rendre opérante.** Son intention documentée est juste et meilleure : §5.1 interdit « titre
en `--c-mistral` » et « titre souligné », WCAG 1.4.1 interdit de distinguer un lien par la seule couleur —
d'où couleur héritée + soulignement comme unique indice, non chromatique.

**Sélecteur retenu : `.ardoise__titre a:any-link`** (0-2-1). Il gagne sur `.sur-sombre a` **dans les deux
contextes**, sans ancêtre et sans réordonnancement du fichier.

**Écartés, et pourquoi :** `.sur-sombre .ardoise__titre a` (0-2-1) conditionnerait l'intention au chrome
sombre — le jour où une ardoise paraîtrait hors `.sur-sombre`, le lien retomberait sur
`a { color: var(--c-mistral) }`, c'est-à-dire exactement le « titre en `--c-mistral` » **interdit** par
§5.1 ; le repli serait faux par construction. Réordonner le fichier ne corrigerait rien de durable :
`composants.css` est enfilée **après** `layout.css`, toute règle 0-1-1 future y reprendrait l'avantage.
**Abaisser `.sur-sombre a` en `:where()` est formellement interdit** : le contrat #22 fait reposer la
couleur de `.bandeau-alerte__lien` sur cette règle.

Ratio résultant : **12,66:1** contre 7,73:1. Amélioration, aucune régression. Le soulignement subsiste.

### A-9 — `front-page.php:94` : un caractère, et un contrôle sur les octets

La chaîne du bras `non_encore_publie` porte `publie vers 17 h.` avec une espace **U+0020** entre `17` et
`h`. `MASTER.md` §11.1 règle 6 impose l'insécable, le reste du site l'emploie déjà, et c'est le `h1` de
l'ardoise — l'occurrence la plus visible du site.

**Décision : le seul U+0020 entre `17` et `h` devient U+00A0.** Aucun mot n'est réécrit, aucune autre
espace de la phrase n'est touchée (la règle 6 ne vise que la liaison nombre ↔ unité), aucun autre bras du
`match`, aucun autre fichier.

**Contrôle obligatoire sur les octets, après écriture ET après tout passage d'outil** — un formateur ou un
correcteur peut retransformer l'insécable en silence :

```
sed -n '94p' wp-content/themes/massifs/front-page.php | od -An -tx1 | tr -d ' \n' | grep -c 'c2a0'
```
Attendu : `1`. Plus `git diff --numstat -- wp-content/themes/massifs/front-page.php` → exactement `1 1`.
Un diff plus large signale un reformatage parasite, qui est un défaut.

**Vérifié : c'est la seule occurrence** dans les deux fichiers PHP de l'empreinte. L'heure de la ligne 180
est **fournie par le serveur** avec ses insécables (`massifs_horodatage()['heure']`) : le gabarit ne doit
rien y ajouter.

### A-10 — Les six divergences orphelines du contrat #22 sont **toutes** enregistrées

Le contrat #22 se termine par six divergences avec `MASTER.md`, à enregistrer par la chaîne #21 — qui
**est close sans les avoir écrites**, le document n'étant pas dans son empreinte. Le contrat #22 prédit
littéralement la conséquence : « sans enregistrement, `review-cms` signalera des **faux défauts** ».

#23 est la **seule** chaîne qui possède `MASTER.md` après #22. **Décision : les six sont enregistrées**,
dans une sous-section dédiée. Le coût est de quelques lignes ; l'alternative est de laisser un contrat gelé
pointer vers une chaîne close.

1. `--ombre-decalee` / `--ombre-decalee-sombre` **déclarés et consommés par personne** (contre §6.4 et
   §8.5, qui les prescrivent sur le panneau massif et le bloc de légende) ;
2. **légende sur une seule colonne sous `--bp-s`** — divergence assumée avec le croquis §7.1 : en
   condensée capitale sur ≈ 140 px, `Accès à la ZAPEF* interdite` empilerait trois lignes ; le §10.6
   règle 6 l'emporte sur un croquis ;
3. **échelle `pt` d'impression non écrite** — le §13 fixe un corps en `pt` mais aucun jeton `pt` n'existe
   et les valeurs brutes sont interdites ;
4. le **« gris 45 % » du §13** est rendu par `--statut-indisponible-encre` (6,33:1) et **jamais** par
   `--c-trace` (1,96:1, refusé en v2.0 par la mesure) ;
5. le **repère n'est pas forcé à l'impression** — le §13 se contredit avec le §3.4, et le redessiner en
   `border` serait une seconde implémentation de l'élément signature, contre le §3.1 ;
6. **interligne 1,2 pour `--fs-250`** — jeton manquant, non créé.

**Aucune de ces six n'est un défaut à corriger dans du code.** Ce sont des écarts entre le document et un
code déjà commité, à enregistrer pour que la revue de lot ne les compte pas deux fois.

### A-11 — Erratum du contrat #6, **signalé et non corrigé**

Le contrat #6 attribue `display: table-header-group` au `<tr>`. **C'est faux** : cette valeur ne s'applique
qu'au `<thead>`. La chaîne #22 l'a implémenté **correctement** (`thead`), et `MASTER.md` §13 écrit lui
aussi `thead { display: table-header-group; }` — le document et le code sont justes ; **seul le contrat
gelé porte l'erreur**.

**Décision : signalé ici, non corrigé.** `docs/contracts/issue-6.md` est un contrat **gelé et hors
empreinte**. Le réécrire depuis une chaîne postérieure poserait un précédent bien pire que l'erreur
elle-même : un contrat gelé qu'une chaîne suivante peut amender n'est plus un contrat. **À porter par le
lot au propriétaire du projet.**

### A-12 — Le filet 4 px en tête de `.sur-sombre` à l'impression : **divergence assumée**

`print.css` (chaîne #22, gelé) fait commencer la première page imprimée par un filet de 4 px, parce que
`header.php` porte `sur-sombre` sur `.barre`. C'est **conforme à la lettre du §13** (« `--c-mistral-nuit`
→ blanc avec `--bord-fort` en haut »), et l'arbitrage 23 du contrat #22 l'a argumenté.

**Décision : on n'y touche pas.** Ouvrir un fichier gelé d'une chaîne sœur pour une préférence esthétique
casserait la disjonction des empreintes pour un gain nul. Consigné comme **divergence assumée** dans
`MASTER.md`, avec la mention que la modifier serait une décision de design à écrire, jamais une correction
CSS silencieuse.

### A-13 — Correction factuelle du §5.1 « Comportement à 360 px »

Le §5.1 écrit « à 360 px, `--fs-800` vaut 56 px, `--fs-700` 36 px, `--fs-600` 28 px ». Ce sont les
**planchers des `clamp`**, atteints à ≤ 325 px et non à 360 px, où les valeurs réelles sont **59 px**,
**37,12 px** et **28,88 px**.

**Décision : corrigé**, puisque le §5.1 est ouvert de toute façon, et **déclaré** dans la ligne v2.3 du
journal de révision — une correction de mesure n'est pas une réécriture de décision, et l'affirmation de
l'issue (« rien ne change à 360 px ») reste vraie dans les deux lectures. Le plancher de 28 px sous lequel
« aucun titre ne descend jamais » reste exact.

---

## Ce que `MASTER.md` doit porter — dépendance dure

`layout.css` s'interdit lui-même (en-tête, l. 10-19) toute valeur qui n'est pas la reproduction d'une
**référence normative de `MASTER.md`**. Si le document n'est pas amendé dans **ce commit**, le CSS porte
des valeurs non autorisées et la revue remonte des défauts **réels**.

| # | Section | Ce qui doit y être écrit |
|---|---|---|
| N-1 | **§5.1** | Plafond de consommation de `--fs-700` : **`3rem`**, milieu exact de 2.25rem–3.75rem, en `rem`, posé en consommation, jeton inchangé |
| N-2 | **§5.1** | Plafond de consommation de `--fs-800` : **`5.75rem`**, milieu exact de 3.5rem–8rem, mêmes règles |
| N-3 | **§6.3** + croquis **§7.1** | Répartition des filets d'A-3, et la reformulation de la règle de quantité |
| N-4 | **§6.1** | Règle de rythme asymétrique d'A-5 (`start > end`, et pourquoi ce sens) |
| N-5 | **§5.1, §7.3, §8.4, §14.3 entrée 5 (b), §16, §3.2** | Application normative de **D-26** + **la règle de portée** reproduite verbatim de ce contrat + l'amendement formel du §3.2 |
| N-6 | **§12.1** | `--ombre-decalee`, `--ombre-decalee-sombre`, `--frise-l`, `--frise-h` : **déclarés et consommés par personne**. Le §12 lui-même n'est pas touché |
| N-7 | **§15** | **D-27** — retrait de la frise. **D-19 n'est ni réécrite ni annotée** |
| N-8 | **journal de révision** | Ligne **v2.3** déclarant tout ce qui précède, et déclarant que **le §12 n'est pas touché** |

### Ce qui, dans `MASTER.md`, **ne doit surtout pas être touché**

- **§5.1 ligne `--fs-250`** — « **Étiquette : capitales** ». C'est la borne qui **survit** à D-26.
- **§14.3 entrée 5 (a)** — « les capitales sont produites par `text-transform` sur une source normalement
  casée ; **jamais** saisies en capitales dans le HTML ». Elle devient **plus** porteuse, pas moins : les
  quatre `text-transform` survivants portent tous des **chaînes officielles verbatim**. Conserver mot pour
  mot.
- **§14.3 entrée 5 (c)** — la résolution du conflit `Légende de la carte`. **La règle reste vraie ; seul
  son exemple devient caduc.** Ne pas la réécrire : ajouter une note `[v2.3]` disant que l'exemple est
  caduc et que la règle vise désormais `Niveau d'Accès` et les quatre libellés officiels.
- **§16 « Libellé officiel saisi en capitales dans le HTML »** — **survit sans discussion**. Les étiquettes
  `--fs-250` restent capitalisées et portent les chaînes officielles ; le retirer ouvrirait la porte à
  l'édition en dur de `Niveau d'Accès`, donc à la violation durable du §11.4.
- **§12 et son bloc de code** — transcription octet pour octet de `tokens.css`, sha256 épinglé. **Fermé.**
- **§14.2 et §15 (archives)** — jamais réécrites. Le retrait de la frise s'enregistre par une **décision
  nouvelle** (D-27), pas par une rature de D-19. Le document fournit lui-même le patron, en D-26 : « cette
  défense est **levée**, elle n'est pas invalidée ».
- **§10.1 et §10.2** — le mot « frise » y apparaît dans des **mesures de contraste** (`#22B14C` vs
  `--c-mistral-nuit` = 5,24:1 ; bascule du liseré sous `.sur-sombre`). Ces mesures restent **vraies et
  nécessaires** : elles couvriront la barre d'action du portail. **Retirer le mot, jamais la mesure.**

---

## Interdits — opposables à tout agent de cette chaîne

1. **Ne jamais modifier `tokens.css`** — ni pour créer un jeton de plafond, ni pour amender un `clamp`, ni
   pour retirer `--frise-l` / `--frise-h` / `--ombre-decalee`. sha256 épinglé, 111 propriétés, contrat #4.
2. **Jamais de plafond typographique en `px`.** Toujours en `rem` (WCAG 1.4.4, A-2).
3. **Ne jamais toucher le terme médian `rem + vw`** d'un `clamp`, ni le réécrire en `clamp()` local :
   cela duplique le jeton en consommation et fait diverger les deux fichiers à la première révision.
4. **Invariant I-1** : aucun sélecteur `.liste-statuts*`, `.legende*`, `.statut*`, `.pastille*`, `.jalon*`,
   `.bandeau-*` dans `layout.css`. Aucune exception, y compris « juste pour le rythme ».
5. **Ne jamais réécrire `.repere` en bordures** (A-4).
6. **N'inventer aucune grille** pour satisfaire la clause `border-left` (A-4).
7. **Ne pas « corriger »** `print.css` ni `composants.css` : hors empreinte, divergences assumées et
   consignées (A-12, A-3).
8. **Invariant I-8** : ne jamais réindenter `templates/parts/liste-statuts.php` — la règle
   `:empty { display: none }` de la chaîne #22 ne tient que parce que PHP avale le saut de ligne après
   `?>`. Hors empreinte de toute façon.
9. **`templates/footer.php` n'est pas modifié** : aucun slug, aucun libellé, aucun taux RGAA, aucune
   phrase « zéro cookie » (A-6).
10. **Aucune autre chaîne PHP retypographiée d'office** : seule la l. 94 de `front-page.php` change, et
    d'un seul caractère (A-9).
11. **Aucun `url()`, aucun `@import`, aucun domaine tiers, aucune animation, aucune transition** — les
    quatre contraintes non négociables de `CLAUDE.md` priment sur toute considération de rendu.
12. **Le thème ne calcule aucune règle métier** : le chiffre, le dénominateur, la saison, la péremption, la
    fraîcheur et le formatage des heures viennent de l'extension.
13. **Ne jamais inventer un fait de domaine.** Un taux de conformité, un libellé officiel, un horaire, une
    couleur : inconnu = question bloquante = on s'arrête et on rapporte.
14. **Aucun contrat gelé n'est rouvert** — pas même pour y corriger une erreur avérée (A-11).

---

## Limites connues de ce contrat — remontées par `refacto-cms`, tranchées ici

**L-1 — La règle de portée a un trou aux niveaux de titre 4 à 6, et il est aujourd'hui inerte.**

La borne (b) de la règle de portée retient le sélecteur nu `h1, h2, h3` en argumentant que les deux
titres en portée émettent un **niveau variable** (`niveau_titre ∈ {2..6}`) et qu'une mécanique par zone
les ferait tomber en silence. **Le sélecteur retenu a exactement le même trou** : un appelant qui passerait
`niveau_titre = 4`, `5` ou `6` ferait retomber `.legende__titre` et `.liste-statuts__titre` — qui sont
**en** portée — sur `--police-texte`, sans bruit. `composants.css` (gelé) ne pose aucune `font-family` sur
ces deux classes, donc rien ne rattraperait la chute.

**Décision : constaté, non corrigé, et écrit ici plutôt que découvert en revue.** Trois raisons :
le défaut est **inerte aujourd'hui** (`front-page.php` ne passe aucun `$args`, le défaut `h2` s'applique,
et c'est documenté dans le gabarit) ; étendre le sélecteur à `h4, h5, h6` **changerait le rendu** de tout
titre de rang bas du site, alors que `MASTER.md` §5.1 ne fournit **aucune taille** pour ces niveaux — ce
serait une invention ; et la corriger rouvrirait la borne (b) d'un contrat gelé dans la passe de nettoyage
qui suit son gel, ce qui est exactement l'ordre inverse du bon.

**Ce que ça implique pour la chaîne qui, la première, passera un `niveau_titre` supérieur à 3** : c'est
elle qui devra porter la question au design system, et `MASTER.md` §5.1 devra alors fournir soit une taille
pour ces niveaux, soit l'interdiction explicite de les employer pour un titre en portée. **Aucune chaîne ne
doit « réparer » ce trou en silence.** Le même trou existe, préexistant et hors périmètre de #23, sur la
remise à zéro des marges (`:where(h1, h2, h3, h4, p, …)` ne couvre ni `h5` ni `h6`).

**L-2 — Collision de numérotation d'arbitrage entre contrats, à l'intérieur d'un même fichier.**

`layout.css` référence « A-5 » pour désigner **deux décisions différentes** : l'arbitrage A-5 du contrat
**#23** (rythme vertical asymétrique) et l'arbitrage A-5 du contrat **#5** (« le mot INDISPONIBLE n'est
pas rendu »). Même situation latente pour « A-13 », que les contrats #5 et #23 emploient tous les deux.
Les contrats numérotent leurs arbitrages indépendamment ; rien n'oblige aujourd'hui à citer la chaîne
d'origine.

**Décision : signalé, non corrigé dans ce lot.** Corriger imposerait de reflouer plusieurs blocs de
commentaire pour un gain de traçabilité qui **ne supprime pas la classe de problème** — elle reviendra au
contrat suivant. C'est une **décision de protocole**, pas un défaut local, et elle appartient au
propriétaire du projet.

**La forme désambiguïsante existe déjà dans le code** et devrait devenir la convention :
`layout.css` l. 37 écrit `Arbitrage A-22 (décision D-23 de la chaîne #4)`. Proposition à trancher hors
lot : **toute citation d'arbitrage porte sa chaîne d'origine** (`A-5 de la chaîne #23`), sans exception,
y compris quand le contrat cité est celui de la chaîne courante.

---

## Questions bloquantes

**Q-1 — Le taux de conformité RGAA, et le qualificatif qui l'accompagne.** Aucun audit n'a été mené. Les
trois qualificatifs conventionnels du web public français — « non conforme », « partiellement conforme »,
« totalement conforme » — sont eux-mêmes des **résultats d'audit** : en choisir un, y compris le plus
pessimiste, serait une affirmation factuelle non établie, interdite par le §4.2 du brief.
**Traitement retenu, sans attendre de réponse** : aucun taux, aucun qualificatif, et une contrainte écrite
dans `MASTER.md` interdisant d'en taper un dans un gabarit le jour venu — c'est la même classe d'erreur
que le §16 interdit déjà pour le chiffre du jour (une valeur figée dans un gabarit devient fausse
silencieusement au premier audit suivant, exactement comme un statut périmé).

**Q-2 — Les libellés exacts des trois entrées de pied**, si le propriétaire veut qu'elles apparaissent
autrement que par le menu. Sans objet aujourd'hui (A-6), bloquant le jour où on renoncerait au menu.

**Q-3 — La phrase « zéro cookie » du croquis §7.1.** Aucune chaîne normative n'existe au §11.3. Si elle
doit figurer au pied, elle doit être **fournie mot pour mot**. Sinon elle reste sur « La démarche », comme
le veut le §9 du brief.

**Q-4 — Éditeur, directeur de publication, hébergeur, `[DOMAINE]`.** Non fournis. Sans objet tant qu'aucune
page « Mentions légales » n'est écrite ; bloquant pour la chaîne qui l'écrira.

Aucune de ces quatre n'empêche l'exécution de #23.
