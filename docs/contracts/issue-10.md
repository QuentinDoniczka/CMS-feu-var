# Contrat d'interface — Issue #10 — Intégrer l'indicateur de danger météo des forêts (Météo-France)

**Gelé le** 14 août 2026 · **Par** `lead-issue-cms` (chaîne #10) · **Statut** contraignant

> Ce document est le point de réconciliation des deux plans, qui ont été produits **en aveugle** l'un de
> l'autre. Là où ils divergeaient, il tranche ; la décision et son motif sont écrits au §7. Rien de ce
> document ne se renégocie en cours d'implémentation : une divergence constatée est un défaut, pas une
> variante.
>
> **Approche retenue (option D du brainstorm)** : la couture simulé/réel passe par le **point d'entrée
> HTTP** redirigé par constante, décalque du connecteur préfecture — la simulation traverse le vrai
> tuyau. Elle est doublée d'une **garde de vocabulaire** qui interdit d'afficher un cran de danger tant
> que les libellés officiels ne sont pas sourcés.

---

## 1. Fonctions de lecture exposées par l'extension

Une fonction, une seule. Le thème ne nomme **aucune** classe de l'extension.

```php
massifs_meteo_du_jour( ?string $jour = null ): array
```

`$jour` au format `YYYY-MM-DD` ; `null` vaut « aujourd'hui ».

**La fonction est TOTALE.** Elle ne lève jamais d'exception, ne rend jamais `null`, `false` ni
`WP_Error`, et **toutes ses clés sont toujours présentes**. Le thème n'écrit jamais `isset()` ni `??`
sur une clé de ce contrat. Un `$jour` malformé rend `etat = 'indisponible'` et `jour = ''` — **il ne
lève pas**.

*Motif de la totalité, opposable* : une exception ferait tomber toute la page d'accueil pour un module
secondaire de bas de page, alors que les statuts sont la raison d'être de cette page. Divergence assumée
avec `massifs_saison()` et `massifs_fraicheur()`, qui, elles, portent l'information principale.

Implémentation namespacée sous `Massifs\Ingest\Meteo\` ; **seule la surface `massifs_*()` est publique**,
gardée par `function_exists()` — patron de `includes/ingest/tuiles/compat.php`, gelé par le contrat #9 §1.

### 1.1 Forme exacte du retour, clé par clé

```
[
  'jour'        => string,       // 'YYYY-MM-DD' — TOUJOURS le jour DEMANDÉ ; '' si malformé
  'etat'        => string,       // liste FERMÉE de 3 valeurs — cf. §3
  'niveau'      => array|null,   // objet SI ET SEULEMENT SI etat === 'disponible', sinon null LITTÉRAL
                                 //   [ 'cle' => string, 'libelle' => string ]
  'echelle'     => [
      'crans'     => int,        // CARDINALITÉ de l'échelle. 0 aujourd'hui. Lue de la config, jamais littérale
      'atteint'   => int,        // rang atteint, 0 <= atteint <= crans. 0 hors de 'disponible'
      'confirmee' => bool,       // false aujourd'hui
      'phrase'    => string,     // « n crans sur N » — rédigée par le SERVEUR. '' hors de 'disponible'
  ],
  'zone'        => [
      'cle'         => string,   // '13'
      'libelle'     => string,   // 'Bouches-du-Rhône'
      'granularite' => string,   // liste FERMÉE : 'departement' | 'zone_meteo' | 'massif'
  ],
  'releve_le'   => string|null,  // ISO 8601 UTC — dernier relevé RÉUSSI de la source 'meteo'
  'publie_le'   => string|null,  // ISO 8601 UTC — publication déclarée par la source POUR CE JOUR
  'distinction' => string,       // MASTER.md §8.6, VERBATIM, TOUJOURS non vide, dans TOUS les états
  'attribution' => [
      'texte'        => string,  // §9 du brief, VERBATIM
      'lien_licence' => string,  // '' aujourd'hui
      'lien_source'  => string,  // '' aujourd'hui — aucune URL vérifiée, rien d'inventé
  ],
]
```

### 1.2 Valeurs servies aujourd'hui — sans exception

`etat = 'indisponible'` (ou `'non_encore_publie'` selon le jour demandé), `niveau = null`,
`echelle = [ 'crans' => 0, 'atteint' => 0, 'confirmee' => false, 'phrase' => '' ]`.

**Quelle que soit la charge reçue et mise en cache.** La garde est dans notre code, pas dans le bouchon :
un bouchon bavard qui injecterait un libellé et une cardinalité ne peut pas la contourner.

### 1.3 Invariants de cohérence — responsabilité du serveur

Quand `etat === 'disponible'`, et seulement alors :
`niveau !== null` · `niveau.libelle !== ''` · `1 <= echelle.crans <= 12` · `1 <= echelle.atteint <=
echelle.crans` · `echelle.confirmee === true` · `echelle.phrase !== ''`.

Le thème vérifie **quand même** (§4, gardes 5 et 6) et **refuse de dessiner** plutôt que d'écrêter : une
valeur fausse ne devient jamais une valeur plausible. Mais la responsabilité est serveur.

### 1.4 Aucune seconde fonction

Ni `massifs_danger_meteo()`, ni `massifs_attribution_meteo()`, ni `massifs_meteo_disponible()`, ni
`massifs_meteo_niveau()`. `etat`, `niveau` et `attribution` sont déjà des clés — « une seconde manière de
poser la même question est une divergence en attente » (contrat #9 §1.4).

### 1.5 Façade interne de l'extension

`Massifs\Ingest\Meteo\Connector` est la **seule** classe que le reste de l'extension a le droit de nommer.

```php
Connector::snapshot_for( string $date_iso ): ?array
Connector::has_snapshot_for( string $date_iso ): bool
Connector::state(): array
Connector::attribution(): array
Connector::run_now( string $date_iso ): true|\WP_Error
Connector::mode(): string
```

**Il n'existe AUCUN `latest()`, ni aucun accesseur « dernier instantané ».** Même justification, mot pour
mot, que `prefecture/class-connector.php` : un `latest()` serait immédiatement employé pour afficher un
indicateur, et le jour où la récupération échoue il servirait celui de la veille comme s'il était
courant. Toute lecture **exige** une date ; l'absence de réponse pour cette date **est** une réponse.
Le scénario 35 **vérifie** cette absence par `method_exists()`, elle n'est pas seulement écrite.

---

## 2. Routes REST

**Aucune.** Le contrat #8 §1 reste intact — aucune méthode ajoutée à l'espace `massifs/v1`.

Quatre raisons, cumulatives : la bande est rendue côté serveur (contrainte #3) ; ajouter une route
toucherait `includes/rest/**`, **hors empreinte** ; la carte n'a jamais besoin du danger météo, et une
route consommée par la carte serait le premier pas vers la fusion que le §4.3 interdit ; aujourd'hui la
seule réponse honnête serait une absence.

**Aucun chemin d'écriture n'existe dans ce module** : pas de route, pas de formulaire, pas d'écran, donc
aucun nonce et aucune capability à vérifier. Seule action déclenchable : `Connector::run_now()`, qui
porte la même ceinture que le préfecture — auto-refus si un utilisateur est connecté sans
`manage_options`, refus si le coupe-circuit est actif, refus hors plage aujourd'hui/demain **avant tout
octet réseau**.

---

## 3. États spéciaux — vocabulaire FERMÉ à trois valeurs

| État | Émis par le serveur quand | Rendu par le thème |
|---|---|---|
| `disponible` | Instantané validé pour ce jour **ET** `Vocabulaire::est_confirme()` **ET** un cran correspond au `niveau_source` | Échelle de `echelle.crans` carrés dont `echelle.atteint` pleins, puis `niveau.libelle` en toutes lettres, puis `echelle.phrase`, puis `distinction`, puis `attribution.texte`. **Aucune couleur.** **Inatteignable aujourd'hui.** |
| `indisponible` | Aucun instantané pour ce jour ; ou vocabulaire non confirmé ; ou `niveau_source` sans correspondance ; ou `$jour` malformé ; ou hors saison | Phrase d'indisponibilité (§5) + `distinction`. **Jamais un tiret, jamais un zéro, jamais une échelle vide.** **Aucune attribution.** Cas nominal de recette. |
| `non_encore_publie` | Jour demandé = demain, aucun instantané | Phrase « pas encore publié » (§5) + `distinction`. **Jamais présenté comme un niveau bas.** Aucune attribution. |

**`hors_saison` N'EXISTE PAS pour la météo, et ne doit pas être créé.** Voir l'arbitrage A-3 (§7).

**`donnee_perimee` n'existe pas non plus** : un instantané n'est servi que pour son propre jour de
validité, il n'y a donc aucun état intermédiaire entre « courant » et « absent ». Voir A-6.

**`couche_effis_indisponible`** : hors périmètre de cette issue (chaîne #11).

### 3.1 Trois règles opposables

1. `niveau` vaut **`null` littéral** hors de `etat === 'disponible'`. **Jamais `[]`, jamais
   `[ 'cle' => '' ]`.**
2. `echelle.crans === 0` ⇒ le thème **ne dessine aucune échelle**. Il ne dessine jamais cinq carrés vides
   « en attendant » : ce serait afficher exactement la cardinalité inventée que ce module existe pour
   empêcher.
3. Le thème filtre `etat` par un `match()` **sans `default`**, sous `try/catch ( \UnhandledMatchError )`
   repliant sur `indisponible` — idiome imposé par I-8 du contrat #8, arbitrage E du contrat #6.
   **Le thème implémente les TROIS bras**, pas deux.

---

## 4. Le gabarit — `templates/parts/meteo.php`

### 4.1 Convention d'appel

```php
get_template_part( 'templates/parts/meteo', null, $args );
```

**`massifs_partie( 'meteo' )` ne transmet aucun `$args`** (convention gelée #6). Les valeurs par défaut
de la partie doivent donc suffire à elles seules ; `$args` n'est qu'une afforance de recette et de
réemploi futur.

| Clé | Type | Défaut | Traitement |
|---|---|---|---|
| `ancre` | `string` | `meteo` | `sanitize_key()`, préfixe de **tous** les `id` |
| `niveau_titre` | `int` | `2`, retenu dans `2..6` | Jamais 1 : le `h1` appartient à l'appelant |
| `jour` | `string` | `null` | Contrôle de **forme** seul (`/^\d{4}-\d{2}-\d{2}$/`), jamais un calcul de date |
| `meteo` | `array` | `massifs_meteo_du_jour( $jour )` | Injection de recette. **Clé unique** : elle porte le retour entier, attribution comprise |

Toute clé absente, vide ou de mauvais type vaut absente ; toute clé non listée est ignorée.

### 4.2 Gardes, dans l'ordre

1. **Garde d'extension** — `massifs_meteo_du_jour` absente et `meteo` non injecté ⇒ **zéro octet**. Pas
   de section, pas de titre orphelin, pas d'ancre morte.
2. **Garde défensive** — l'appel est enveloppé d'un `try/catch`. Le contrat §1 rend cette garde
   théoriquement inatteignable ; elle reste, et son repli est une **absence**, jamais une donnée.
3. **Garde de vocabulaire** — `match ( $etat )` à **trois bras**, sans `default`, sous
   `catch ( \UnhandledMatchError )` repliant sur `indisponible` avec journalisation.
4. **Garde de sens (AA, structurante)** — **jamais de carrés sans libellé.** `niveau.libelle` vide ⇒
   l'échelle n'est pas dessinée et la partie bascule sur le rendu `indisponible`, quelle que soit la
   valeur de `echelle`.
5. **Garde de cardinalité** — `crans` retenu dans `1..12`, `atteint` dans `0..crans`. Hors bornes ⇒
   **aucune échelle dessinée**, `_doing_it_wrong()`, les phrases demeurent. **Aucun écrêtage silencieux.**
   La borne 12 est un garde-fou de rendu, **pas un fait de domaine** : elle n'affirme rien sur la
   cardinalité réelle et n'est jamais affichée.

**Le chiffre 5 n'apparaît nulle part** : ni littéral, ni borne de boucle, ni table de libellés, ni classe
CSS numérotée, ni commentaire. La boucle est `for ( $i = 0; $i < $crans; $i++ )` où `$crans` vient du
serveur.

> **[Précision du 14 août 2026, après refacto — portée de la règle, non modifiée]** Cette règle vise la
> **cardinalité de l'échelle de danger**, et elle seule. Le `stroke-width="1.5"` que le §4.4 impose sur
> les carrés vides est une **épaisseur de trait en pixels** : il n'affirme rien sur le nombre de crans,
> et le retirer violerait le §4.4. Un `grep '5'` mécanique le touche ; ce n'est pas une occurrence au
> sens de cette règle. **C'est la seule exception, et elle est fermée.** Côté extension, le contrôle
> reste absolu : **zéro** occurrence, y compris en littéral isolé.

### 4.3 Ordre du balisage — imposé par MASTER.md §8.6

`<section id="{ancre}" aria-labelledby="{ancre}-titre">` → `h{n}` **Danger météo du jour** → *branche
disponible* : échelle SVG, `niveau.libelle`, `echelle.phrase` → *branche indisponible / non encore
publié* : phrase d'état → **toujours** : `distinction` → *branche disponible seulement* :
`attribution.texte`.

- **Titre en casse normale.** Un `h2` en capitales est un défaut §16 (D-26).
- **Pas de `tabindex`, pas d'`aria-live`, pas de `role="status"`** : rien ne se met à jour, l'affirmer
  serait faux.
- **Pas de ligne de date** : `jour` est une clé du contrat que **le gabarit ne lit pas**.
- **Aucune balise externe** : pas d'`<img>`, `<iframe>`, `<link>`, `<script>`, ni le moindre `href`.

### 4.4 L'échelle de carrés

- **SVG en ligne**, jamais un caractère (précédent D-25 ; `▪`/`□` sont hors du sous-ensemble `latin`).
- `aria-hidden="true"` **et** `focusable="false"`.
- `fill="currentColor"` sur les pleins ; `fill="none" stroke="currentColor" stroke-width="1.5"` sur les
  vides. **Aucune valeur de couleur dans le gabarit** — la couleur est héritée.
- **Géométrie portée par le SVG lui-même** (`width`, `height`, `viewBox`, `x` en attributs) : le module
  est lisible **sans une ligne de CSS**. Pas d'attribut `style`.
- Carré 12 px, gouttière 4 px ⇒ `W = crans * 16 - 4`. À la borne défensive de 12, `W = 188 px`, sous
  360 − 2×`--esp-m` = 328 px : **aucun débordement horizontal possible à 360 px**, prouvé par
  l'arithmétique et non par une media query.
- **Les carrés ne portent jamais l'information** : le sens est porté par `niveau.libelle` et
  `echelle.phrase`, deux textes visibles. Ni la géométrie seule, ni la couleur seule.

### 4.5 Sans JavaScript, échappement, impression

- **Zéro octet de JS**, aucun `wp_enqueue_script`, aucun `data-*` de comportement, aucun `<noscript>`.
  Le module est dans le HTML initial au même titre que la liste des statuts.
- Toute chaîne serveur en contenu ⇒ `esc_html()`. **Jamais** `wp_kses_post()`, **jamais** `echo` brut :
  les valeurs sont d'**origine tierce**. Tout attribut ⇒ `esc_attr()`, `id`/`class`/`viewBox`/`x`
  compris. Les valeurs numériques du SVG sont calculées depuis des entiers validés, jamais interpolées
  depuis une chaîne serveur. **Aucune chaîne d'origine tierce ne devient un `id`, une `class` ou un
  `href`.** Aucun `esc_url()` : le module n'émet aucune URL.
- **Impression** : texte de flux + un SVG en `currentColor`, donc de l'encre et non un fond. Aucune règle
  d'impression nécessaire. **360 px et 200 %** : trois paragraphes redistribuables et un SVG de 188 px au
  pire.

### 4.6 Crochets de classe livrés (consommés plus tard par `dev-ux-cms`)

`.meteo` · `.meteo__titre` · `.meteo__echelle` · `.meteo__carres` · `.meteo__carre` ·
`.meteo__carre--plein` · `.meteo__carre--vide` · `.meteo__libelle` · `.meteo__crans` ·
`.meteo__indisponible` · `.meteo__distinction` · `.meteo__attribution`.

**Aucune classe de la famille `statut` / `pastille` / `jalon` / `bandeau-alerte`. Aucune classe
`repere`.**

---

## 5. Chaînes — qui les émet, sous quelle clé

| Chaîne | Émetteur | Clé | Statut |
|---|---|---|---|
| `Données Météo-France — Licence Etalab 2.0` | **serveur** | `attribution.texte` | §9 du brief, **verbatim**, tiret cadratin U+2014. Rendue entière, jamais découpée ni reformulée. **Rendue seulement si `etat === 'disponible'`** (A-4). |
| `Le danger météo décrit les conditions du jour ; il ne détermine pas l'accès au massif, qui relève de l'arrêté préfectoral.` | **serveur** | `distinction` | MASTER.md §8.6, **verbatim, ne pas réécrire**. Émise et rendue **dans TOUS les états**, y compris `indisponible` : le propos est vrai indépendamment de la donnée, et c'est précisément quand l'indicateur manque qu'un lecteur risque de le rabattre sur le statut d'accès. |
| Libellé officiel du cran | **serveur** | `niveau.libelle` | Verbatim Météo-France. **N'existe pas encore** (Q2). |
| « n crans sur N » | **serveur** | `echelle.phrase` | Rédigée par le serveur (A-2). `''` hors de `disponible`. |
| URL de licence Etalab, URL de la source | **serveur** | `attribution.lien_licence`, `lien_source` | **`''` aujourd'hui.** Le thème **ne rend aucun lien dans ce module** ; ces clés existent pour la vérité serveur, pas pour l'affichage. |
| Titre de section `Danger météo du jour` | **THÈME** | — | Chrome de section, même statut que `La liste du jour` et `Légende de la carte`, écrits dans leurs gabarits respectifs. |
| Phrase `indisponible` | **THÈME** | — | **`Danger météo du jour non disponible.`** (A-1) |
| Phrase `non_encore_publie` | **THÈME** | — | **`Le danger météo de demain n'est pas encore publié.`** (A-1) |

Les deux phrases d'état sont **gelées ici mot pour mot** afin qu'aucun dev aval ne les rédige. Leur
enregistrement au §11.3 de `MASTER.md` — liste **fermée** — est une couture remontée au lot (S4).

---

## 6. Interdits

### Le thème n'a jamais le droit de

1. Appeler `Massifs\Ingest\Meteo\Connector`, ni aucune classe de ce namespace, ni `Vocabulaire`, ni une
   fonction d'ingestion. **Seule porte : `massifs_meteo_du_jour()`.**
2. Appeler une source externe, sous quelque forme que ce soit.
3. **Écrire le chiffre 5** — ni en dur, ni en borne de boucle, ni en classe CSS, ni en commentaire.
4. Dessiner une échelle quand `echelle.crans === 0`, ou afficher un tiret / un zéro / un « n. d. » à la
   place d'un niveau.
5. Composer, traduire, abréger ou reformuler `niveau.libelle`, `distinction`, `echelle.phrase` ou
   `attribution.texte`.
6. Afficher `niveau` quand `etat !== 'disponible'`, ou écrire `isset()` / `??` sur une clé du contrat.
7. Calculer une règle métier : saison, péremption, fraîcheur, formatage de niveau, « aujourd'hui » ou
   « demain » (`massifs_jour_courant()` / `massifs_jour_suivant()`), mise en forme d'un instant
   (`massifs_horodatage()`).
8. **Colorer** l'indicateur, lui donner une icône de flamme ou un emoji, employer un jeton `--statut-*`,
   une valeur hexadécimale, ou le poser à proximité visuelle des statuts.
9. Poser un `repere` sur son `h2`, ou réutiliser une classe de la famille `statut` / `pastille` / `jalon`.
10. Rendre l'attribution Météo-France quand aucune donnée météo n'est affichée.
11. Interpréter `indisponible` comme « pas de danger ».
12. Poser un cookie, dépendre de JavaScript, émettre un `<script>`, une `<img>`, un `<link>` ou un `href`.

### L'extension n'a jamais le droit de

1. Émettre du HTML de présentation publique.
2. Servir `disponible` un danger dont la date de validité diffère du jour demandé.
3. Offrir un `latest()` ou tout accesseur « dernier instantané ».
4. Ouvrir la garde de vocabulaire autrement qu'en remplissant la table de crans depuis une source écrite.
5. Émettre un octet réseau depuis un autre fichier que `class-fetcher.php`.
6. Inventer une URL par défaut pour la source (voir §8, coupe-circuit).
7. Ajouter une route à `massifs/v1`, une table, un rôle, une capability, un crochet d'activation.
8. Se brancher sur le cron d'une chaîne sœur.

---

## 7. Arbitrages — chaque désaccord entre les deux plans, la décision, sa raison

### A-1 — Qui émet les phrases d'état ? → **le thème**

`leaddev-front-cms` (F-6) demandait une clé serveur `phrase_indisponible` et rendait **zéro octet** sans
elle. `leaddev-back-cms` (A-1) a tranché l'inverse, **contre la lettre de son propre briefing**, en
citant un artefact **gelé** — `includes/domain/statuts/legende.config.php` l. 196-201 : « *aucune phrase
destinée au visiteur ne vient du serveur. Les libellés de ces trois états appartiennent au thème
(MASTER.md §11.3)* » — pour exactement cette classe d'états.

**Décision : le back a raison.** Ma règle par défaut (« le serveur possède les données et les chaînes »)
cède devant un précédent gelé qui couvre le cas identique. Faire diverger la météo installerait deux
conventions contradictoires pour la même chose.

**La ligne de partage, écrite pour qu'elle ne se rediscute pas** : une chaîne qui **décrit la donnée de
la source** (attribution, libellé officiel, position sur l'échelle) vient du **serveur** ; une chaîne qui
**décrit l'état de notre site** (absence, non-publication) appartient au **thème** et relève du §11.3.

Conséquence heureuse : la question bloquante Q1 du plan front se dissout — le cas nominal de recette ne
rend plus zéro octet. Les deux phrases sont gelées verbatim au §5.

### A-2 — `echelle.phrase` (« n crans sur N ») → **serveur**

Le front la voulait serveur et **refusait** un `sprintf` côté thème (Q3) ; le back ne l'offrait pas.

**Décision : serveur, sous `echelle.phrase`.** Elle décrit la position de la donnée **sur l'échelle de la
source** — donc du côté « données » de la ligne A-1 — et son libellé même dépend d'une cardinalité que
seul le serveur sait confirmée. Le motif 3 du front (« tant que `crans` vaut 0, aucune phrase n'est
composable ») disparaît de lui-même : à `crans === 0` on est en `indisponible` et aucune échelle n'est
dessinée.

### A-3 — `hors_saison` pour la météo → **supprimé**

Le back prévoyait un état public `hors_saison`, adossé à `massifs_saison()`. Le front avait explicitement
refusé de le demander : « *le danger météo n'a pas de saison préfectorale, et l'affirmer serait un fait de
domaine que je ne peux pas produire.* »

**Décision : le front a raison, l'état public disparaît.** La saison du 1er juin au 30 septembre est un
fait du **dispositif préfectoral**. Affirmer au visiteur que Météo-France ne publie pas hors de cette
période serait inventer un fait de domaine sur une source tierce — précisément ce que cette chaîne
existe pour empêcher. Le back le reconnaît d'ailleurs lui-même en classant la question en Q5 (« hypothèse
intérimaire »).

**Ce qui subsiste, et seulement cela : une porte opérationnelle.** Hors saison, le module n'émet ni octet
réseau ni alerte, et rend `indisponible`. S'abstenir d'appeler n'affirme rien ; c'est asymétrique et sans
risque, puisque la seule conséquence possible est le repli honnête. Si `massifs_saison()` est absente, la
porte ne s'applique pas et le module procède normalement.

Le vocabulaire fermé compte donc **trois** valeurs. Le front n'en avait planifié que **deux** : il doit
implémenter le troisième bras (`non_encore_publie`). C'est exactement le genre de dérive que ce gel existe
pour attraper.

### A-4 — Attribution rendue en état non disponible → **non**, et la lecture du §16 est confirmée

Les deux plans convergeaient déjà (front F-9, back). Le front demandait toutefois confirmation (Q2), le
§16 de `MASTER.md` listant « attribution Météo-France manquante » comme défaut bloquant.

**Décision, opposable en revue** : « manquante » se lit « **manquante là où la donnée est affichée** ».
`templates/footer.php` l. 13-15 est explicite et gelé : « *créditer une source dont aucune donnée n'est
affichée est une affirmation fausse* » ; invariant I-9.4 du contrat #9, même règle. Aujourd'hui, donc :
**la bande météo ne porte aucune attribution.** La chaîne, elle, voyage toujours dans le retour et est
gelée dès maintenant.

### A-5 — Collision de nommage sur la forme du retour → arbitrée clé par clé

Les deux plans ont nommé différemment les mêmes choses — le mode d'échec classique du travail en
parallèle. Table de conversion, **contraignante** :

| Plan front | Plan back | **Gelé** | Motif |
|---|---|---|---|
| `massifs_danger_meteo()` | `massifs_meteo_du_jour()` | **`massifs_meteo_du_jour()`** | Convention `massifs_<domaine>_*()` du projet ; le front inversait l'ordre des mots |
| `massifs_attribution_meteo()` | *(clé)* | **clé `attribution`** | Contrat #9 §1.4 : une seconde manière de poser la même question est une divergence en attente |
| `echelle.total` | `echelle.crans` | **`echelle.crans`** | Nommage back |
| `echelle.atteint` | `niveau.rang` | **`echelle.atteint`** | Un seul nombre, un seul endroit. `rang` reste **interne** à `vocabulaire.config.php` et ne traverse pas la frontière : deux noms pour la même valeur dans deux blocs différents sont une dérive en attente. Le thème lit les deux nombres de géométrie au même endroit, ce que sa boucle demande |
| `libelle` (racine) | `niveau.libelle` | **`niveau.libelle`** | Le bloc entier est annulable, ce qui rend « pas de niveau » non représentable par une chaîne vide |
| `phrase_distinction` | `distinction` | **`distinction`** | Nommage back |
| `jour_validite` | `jour` | **`jour`** | Le jour **demandé**, toujours. Le gabarit ne le lit pas |
| `phrase_echelle` | — | **`echelle.phrase`** | Voir A-2 ; rangée avec les deux nombres qu'elle décrit |
| `$args['danger']` + `$args['attribution']` | — | **`$args['meteo']`, clé unique** | Une seule fonction ⇒ une seule injection, attribution comprise |

### A-6 — Totalité contre exception → **totale**

Le front (F-1) autorisait `\InvalidArgumentException` sur un jour malformé ; le back la proscrivait.
**Décision : totale**, motif au §1. La garde `try/catch` du gabarit reste en ceinture et bretelles, mais
elle est contractuellement inatteignable et ne doit jamais être le chemin qui produit zéro octet.

### A-7 — Répertoire des bouchons → **`data/meteo/bouchons/`**, extension d'empreinte que j'autorise

Les deux candidats du briefing sont **morts, et le back l'a vérifié** :
`includes/ingest/meteo/bouchons/` est refusé en **403** par `docker/wordpress/plugins-guard.conf` bloc 2,
à n'importe quelle profondeur — un bouchon déposé là ne peut donc jamais traverser un aller-retour HTTP,
c'est-à-dire exactement ce que l'approche retenue exige ; `docker/tiles/data/stubs/meteo/` est hors
empreinte, servi par un nginx qui répond 404 hors `/tiles/`, et déclaré sans emploi et à retirer par le
contrat #9 C-7.

`plugins-guard.conf` l. 45-47 et le contrat #30 §3.6 **nomment `plugins/massifs-core/data/` comme
délibérément servi et nommément réservé aux « caches météo / EFFIS / tuiles »**.

**Décision : j'autorise l'écriture de DEUX fichiers hors empreinte littérale** —
`wp-content/plugins/massifs-core/data/meteo/bouchons/README.md` et `.gitignore` (ignorant `*.json`).
**Rien d'autre.** Motif : le répertoire n'existe pas, aucune chaîne sœur ne le touche, sa création ne
peut écraser aucun travail en cours — le risque contre lequel la règle d'empreinte protège est
structurellement absent — et sans lui la troisième case de la checklist (« mettre en cache et servir
depuis notre domaine », prouvé par un vrai aller-retour HTTP) n'est pas livrable. Précédent : contrat #9
§10. **Aucun fichier daté n'est commité** : un `20260814.json` serait périmé le lendemain.
Convention miroir `data/effis/` proposée à la chaîne #11, à arbitrer au lot.

### A-8 — Aucun point de jonction côté extension

Le briefing des deux leaddev supposait une ligne de `require_once` à écrire hors empreinte. **Elle
n'existe pas.** `massifs-core.php` l. 122-165 (`massifs_core_charger_modules()`) découvre les modules
**par convention** : pour chaque couche, il `scandir` et charge `<couche>/<module>/module.php` ou, à
défaut, `<couche>/<module>/bootstrap.php`.

**Conséquence dure et inversée, qui devient une clause contraignante** : le sous-arbre n'est pas inerte
par absence de câblage — il est chargé **dès que `bootstrap.php` existe**. `bootstrap.php` s'écrit donc
**EN DERNIER** (§9). Un `ParseError` dans un fichier inclus **n'est pas rattrapable** : ce serait un écran
blanc pour les **trois** chaînes du lot, qui partagent l'arbre de travail.
**Aucune modification de `massifs-core.php` n'est requise ni permise.**

### A-9 — Fraîcheur : quelle classe réutiliser

Le briefing affirmait que `massifs_fraicheur()` n'était pas générique. **Faux** :
`Fraicheur::__construct` accepte déjà `string $source_cle` en troisième paramètre. Il n'y a **rien à
demander à la chaîne #12**.

**Décision : le module n'utilise pas `Fraicheur` pour autant.** Sa valeur ajoutée est `perimee`, calculée
sur un seuil de 86 400 s qui est **une règle des statuts** (§4.5 attache la bannière de péremption aux
statuts) et n'existe pour la météo dans aucune source ; l'employer obligerait de surcroît à traîner
`Saison`, `Legende` et `Horloge`.

Le couplage se réduit à **un seul fichier documenté**, `class-releve.php` : écriture via
`massifs_enregistrer_releve_reussi( 'meteo', $instant )` — **uniquement après un instantané validé et
enregistré**, jamais sur un 404, un rejet ou un échec réseau, sinon la fraîcheur ment ; lecture via
`RegistreReleves::dernier_releve( 'meteo' )`.

**L'honnêteté vient d'ailleurs, et plus fort** : un instantané n'est courant que **pour son propre jour
de validité**. Aucun seuil, aucune bannière, aucune valeur de la veille servie comme courante.
`releve_le` voyage comme **fait**, jamais comme autorisation.

### A-10 — Plages de scénarios : collision frontale, arbitrée

Les deux plans réclamaient **30 à 34**. Partage :

- **`leaddev-back-cms` / `dev-back-cms` : 30 à 35.**
- **`leaddev-front-cms` / `dev-front-cms` : 36 à 39.**

### A-11 — `dev-ux-cms` n'est pas lancé

`assets/css/**` est **entièrement hors de l'empreinte de la chaîne**. `dev-ux-cms` n'aurait aucun fichier
qu'il ait le droit d'écrire. Le bloc de style est une couture remontée au lot (J-2). Le gabarit doit être
**lisible et conforme AA sans lui** — seulement plus nu.

---

## 8. Ingestion — clauses contraignantes

- **Couture simulé/réel : `MASSIFS_METEO_JSON_URL_TEMPLATE`.** `{date}` est le **seul** jeton reconnu,
  substitué **après** validation stricte contre `/^\d{8}$/`, l'URL finale passant par `esc_url_raw()`.
- **Coupe-circuit plus strict que le préfecture, à dessein** : `Settings::is_disabled()` rend vrai si
  `MASSIFS_METEO_DISABLE` est vraie, **ou si `MASSIFS_METEO_JSON_URL_TEMPLATE` n'est pas définie — dans
  TOUS les environnements, production comprise.** La constante n'a **aucune valeur par défaut** : le
  point d'entrée réel de l'API n'est pas connu et ne se déduit pas. Une URL par défaut inventée serait le
  pire des deux mondes — un appel sortant vers une adresse fausse, en production. Sans constante, le
  module ne peut **structurellement** pas émettre un octet.
- **Format du bouchon : le nôtre, déclaré comme tel, versionné (`schema: 1`).** Il n'est **jamais
  présenté comme une imitation du format réel**, qui est inconnu (Q1). Le jour où le format réel est
  connu, **seule la couche `forme` du validateur change** — transport, référentiel, sémantique, temporel,
  cache, fraîcheur, planification et alertes ne bougent pas. C'est la définition opérationnelle de
  « un changement de connecteur, pas une réécriture ».
- **Planification** : crochet `massifs_meteo_recuperation`, récurrence **native `hourly`**, aucun filtre
  `cron_schedules`, posé sur `init`, auto-réparateur, retiré à la désactivation. **Aucun couplage au cron
  de la chaîne #12.** **Pas de fenêtre de publication** : l'heure de publication de « Météo des forêts »
  n'est établie nulle part, et en inventer une déclencherait des alertes sur une heure fausse.
- **Réseau** : `class-fetcher.php` est le **seul** fichier du module autorisé à émettre un octet.
  `wp_remote_get` uniquement, temporisation bornée 1–30 s, `sslverify` **ré-imposé à `true` après** le
  filtre `massifs_meteo_http_args` — qui est aussi le point d'accroche du futur en-tête
  d'authentification. **Aucune boucle de reprise, aucun `sleep()`** : la récurrence horaire EST la
  politique de reprise. **Deux dates au plus par exécution.**
- **Validation en cinq couches** (transport, forme, référentiel, sémantique, temporel), chaque rejet
  portant sa `couche`. **La couche sémantique ne consulte JAMAIS `Vocabulaire`** : une charge dont le
  niveau n'a pas de libellé reste **valide et mise en cache**, et c'est la couche de **lecture** qui
  refuse de la servir. C'est ce qui permet d'exercer réellement cache, fraîcheur et alertes aujourd'hui.
- **Ce qui n'est PAS une aberration**, à ne jamais réintroduire comme motif de rejet : un niveau au
  maximum de l'échelle ; une valeur identique à celle de la veille ; un saut d'amplitude quelconque. Le
  hachage ne provoque **jamais** de rejet. Le seul signal de non-publication est le **404**, qui
  n'incrémente pas le compteur d'échecs et ne déclenche aucune alerte.
- **La garde de vocabulaire** (`vocabulaire.config.php` + `Vocabulaire::est_confirme()`) n'est ouverte que
  si `confirme === true` **ET** la table de crans est non vide, chaque cran portant `cle`, `libelle` et
  `rang` valides, rangs distincts et contigus depuis 1, **ET** la correspondance source→cran est non vide
  et ne pointe que sur des crans existants. Le filtre `massifs_meteo_vocabulaire` est **ré-validé après
  application** : un filtre n'ouvre jamais la garde à lui seul. **Aucune constante d'ouverture n'est
  offerte** — elle permettrait d'ouvrir sans fournir un seul libellé.

---

## 9. Ordre d'implémentation — contraignant

**Back** : garde de vocabulaire → réglages et calendrier → dépôts → validateur → ingestion (fetcher,
notifier, runner, schedule) → relevé → lecture et `api.php` → façade → README →
**`bootstrap.php` EN DERNIER** (A-8) → scénarios 30–35.

**Front** : coquille et gardes → branche `indisponible` (cas nominal, module déjà livrable à ce point) →
branches `non_encore_publie` et `disponible` → scénarios 36–39.

---

## 10. Coutures hors empreinte — signalées, non exécutées

| # | Couture | Porteur |
|---|---|---|
| **J-1** | `front-page.php` — insertion de `<div class="bande bande--meteo"><div class="bande__contenu"><?php massifs_partie( 'meteo' ); ?></div></div>` après le `</div>` de `bande--liste` (l. 409) et avant le commentaire l. 411-419, qui perd son paragraphe météo. **Aucun argument.** Sans elle, la partie est du code mort. | Lot |
| **J-2** | `assets/css/composants.css` — bloc `.meteo`. **Règle impérative** : `.meteo__titre { font-family: var(--police-texte); }`, la borne (b) du §5.1 faisant de la famille d'affichage le défaut du sélecteur nu `h2`, ce qui serait un défaut §16 pour un titre hors portée. Plus : `.meteo` en `--bord-fin` sans ombre ; `.meteo__carre--vide { stroke: var(--c-garrigue) }` ; `.meteo__attribution` en `--fs-100`, `.meteo__distinction` en `--fs-300`, `.meteo__libelle` en `--fs-400`. Et `.bande--meteo` dans `layout.css`. | `dev-ux-cms`, lot |
| **J-3** | `MASTER.md` §11.3 — enregistrement des deux phrases d'état du §5. §8.6 et §11.2 affirment « cinq crans » **sans source** et devraient porter un `À CONFIRMER` ; le §8.6 ne dit rien de la famille typographique du module ni du traitement de sa boîte. | `lead-design-cms` |
| **J-4** | `tests/bootstrap.php` — `t_reset()` devrait purger les trois options météo. Contourné par des `delete_option()` en ligne dans chaque scénario. | Chaîne #12 ou lot |
| **J-5** | `wp-config.php` de la stack — recette `MASSIFS_METEO_JSON_URL_TEMPLATE`. | `docker-cms`, fin de lot |
| **J-6** | `tests/README.md` — table des scénarios 30–39 ; la phrase « faute d'exister : […] l'indicateur Météo-France » devient fausse. | `test-integration-cms` |
| **J-7** | **Défaut relevé en passant, hors périmètre** : `includes/ingest/prefecture/README.md` l. 234-240 documente une recette de bouchons (`http://tiles/stubs/prefecture/{date}.json`) que `docker/tiles/nginx.conf` ne peut pas servir (`location / { return 404; }`). S'ajoute à C-7 du contrat #9. | Lot |

---

## 11. Questions bloquantes — jamais comblées par déduction

| # | Question | Nature |
|---|---|---|
| **Q1** | **Format réel de la réponse de l'API « Météo des forêts »** : point d'entrée, authentification, forme JSON, nom et type du champ de niveau. | Bloquante avant bascule vers le connecteur réel, **pas avant commit**. Le format du bouchon est le nôtre, déclaré et versionné. |
| **Q2** | **Libellés officiels des crans et cardinalité de l'échelle.** `MASTER.md` §8.6 et §11.2 affirment « cinq crans » ; ce chiffre **n'est sourcé nulle part dans le dépôt**, et la v1.0 du même document portait déjà 5 niveaux **inventés** pour les statuts, détruits en v2.0 par une décision sourcée. La seule échelle graduée réellement relevée dans le dépôt en compte **six** et appartient à d'autres départements. | **Bloquante avant tout affichage d'un niveau.** Se comble par une source écrite du propriétaire, jamais par un bouchon. La table reste vide, l'indicateur reste `indisponible`. |
| **Q3** | **Découpage géographique** — l'arbitrage §9-Q2 de `docs/decisions/source-prefecture.md` (bloc `zm`, 9 « zones météo », sémantique non publiée), que ce document déclare lui-même « nécessaire **avant la chaîne meteo** », **n'a pas eu lieu**. Hypothèse intérimaire : une valeur départementale. | Non bloquante pour #10 : le bloc `zone` porte déjà sa `granularite` dans une liste fermée, il n'y a qu'une valeur à changer. À trancher avant toute publication par zone. |
| **Q4** | **URL canonique de la Licence Ouverte / Etalab 2.0**, et Météo-France exige-t-elle une mention plus spécifique que la chaîne du §9 ? | Bloquante avant mise en production. En attendant, les deux liens restent `''`. |
| **Q5** | **Le danger météo suit-il un calendrier saisonnier propre ?** Résolu pour l'affichage par A-3 (aucune affirmation publique) ; reste ouvert pour l'exploitation. | Non bloquante. |
