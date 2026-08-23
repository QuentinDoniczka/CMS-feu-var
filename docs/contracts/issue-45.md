# Contrat d'interface — Issue #45 — Importer le référentiel communal (IGN ADMIN EXPRESS)

**Gelé le** 18 août 2026 par `lead-issue-cms` (chaîne #45) · **Statut** : contraignant.
**Ré-gelé le 18 août 2026**, après implémentation et avant commit : **§4.3** (règle unique + départage),
**§5** (le seam public devient géométrique), **§11.7.d** (quatrième contournement refusé). Motif :
l'échec de gel décrit au §4.3 — deux formulations divergentes transportées sans être réconciliées, et une
API par point incapable d'exprimer la règle. Trouvé en vérifiant le code livré, pas en relayant un
rapport d'agent.

**Réaligné le 21 août 2026** par la chaîne de reprise, avant commit, **sur le code livré et sans changer
aucune décision** : le seam s'appelle `massifs_commune_de_la_zone()` et non `massifs_commune_du_point()`
(nom d'avant le ré-gel du §5, resté dans le §3, le §3.3 et le §13.4), et le §5 comptait « 16 à 17 » là où
il gèle trois fonctions. Deux erreurs de **transcription**, trouvées par `refacto-cms` en comparant le
contrat au code — c'est-à-dire par le contrôle que le §12 réclame. Consigné parce qu'un contrat qui
nomme une fonction inexistante envoie un relecteur chercher un seam qui n'a jamais été écrit.
**Troisième reste du même gel, corrigé en même temps** : le §7 décrivait `RAISON_COMMUNES_HORS_COUVERTURE`
comme un « **point** hors emprise ». Le code teste bien l'**emprise entière de la zone** contre la
couverture de l'artefact. Encore le vocabulaire de l'API par point, encore le code qui a raison contre le
contrat. Trouvé par `dev-integration-cms`.

**Quatrième réalignement, 23 août 2026, sur le §2 — et c'est le plus dangereux des quatre.** Le §2
donnait la couche comme `ADMINEXPRESS-COG-CARTO.<millésime>:commune`, **en la classant « mesurée »**
au §12. **Aucune couche datée `ADMINEXPRESS-COG-CARTO.<AAAA>` n'existe** : la variante CARTO n'est
servie que derrière l'alias `.LATEST`, et une interrogation datée répond **HTTP 400**
(`recuperer-communes.mjs` l. 20-24, README du domaine l. 446-447). La forme datée qui existe appartient
à la famille **`ADMINEXPRESS-COG`**, sans `-CARTO`, et elle ne sert **qu'à résoudre l'alias par mesure** —
elle n'est la source d'aucune géométrie livrée. Le §2 décrivait donc une couche interrogeable qui ne
l'est pas, sous l'autorité d'une mention « requête réelle ». **Un fait faux estampillé « mesuré » est
pire qu'un fait absent** : il ferme l'enquête au lieu de l'ouvrir, et le prochain relecteur serait allé
interroger une couche inexistante en concluant à une panne du service. Le §2.1 disait déjà juste ; c'est
le tableau du §2 qui le contredisait. Trouvé par `review-cms`.

**Provenance de ce contrat — à lire avant de s'en servir.** Les deux `leaddev` de cette chaîne
(`leaddev-back-45`, `leaddev-front-45`) **n'avaient rendu aucun plan au moment du gel** : lancés, ils ont
accusé réception des consignes, puis n'ont pas répondu dans la fenêtre, y compris à un ping de liveness
explicite. `leaddev-back-45` a **refait surface après le gel**, toujours en cours de travail et sans
blocage — ils étaient donc **silencieux, non morts**, et la même chose s'est vérifiée sur les chaînes #37
et #39. La distinction est consignée ici parce qu'elle a failli être écrite à l'envers : « ne répond pas
dans la fenêtre » n'autorise pas à conclure « arrêté ».

**Aucune clause de ce contrat n'est issue d'un agent de planification.** Tout ce qui suit est établi par
lecture directe du code par le lead d'issue, par les cinq réponses du propriétaire du projet et par les
arbitrages de l'orchestrateur de lot. Un plan arrivé après le gel sert de **vérification a posteriori**,
jamais de source : il peut confirmer ou contredire une clause, il n'en devient pas l'auteur. Le §12
énumère ce qui est mesuré, ce qui est décidé et ce qui reste à établir par les agents de développement.

---

## 1. Ce que fait cette issue, en une phrase

Elle **retire une lacune documentée** : le référentiel communal, cité par son absence dans deux contrats
gelés, deux README, trois scénarios de recette et un commentaire de code. Elle ne se contente pas
d'importer un jeu de données.

Deux besoins, aujourd'hui non tenus faute de donnée :

- **§5.2 du brief** — « la commune la plus proche » du panneau des zones de feu ;
- **transverse** — « les communes concernées » du panneau massif et du JSON public.

---

## 2. Source retenue, et sa provenance MESURÉE

| Fait | Valeur | Comment il a été établi |
|---|---|---|
| Service | `data.geopf.fr` WFS 2.0.0, `OUTPUTFORMAT=application/json` | Requête réelle, 18 août 2026 |
| Couche interrogée | `ADMINEXPRESS-COG-CARTO.LATEST:commune` — **un alias, la seule forme servie** | idem |
| Famille témoin datée | `ADMINEXPRESS-COG.<AAAA>:commune` — sert **uniquement** à résoudre l'alias par mesure | idem |
| Filtre | `CQL_FILTER=code_insee_du_departement='13'` (+ départements limitrophes, §4.2) | idem |
| Projection | `SRSNAME=EPSG:4326` | idem |
| Entités (13 seul) | **119** | Compté sur la réponse |
| Octets (13 seul, pleine précision) | **993 664** | `size_download` |
| Durée | **0,32 s** | `time_total` |
| Types de géométrie | `MultiPolygon` uniquement | Compté sur la réponse |
| Licence | Licence Ouverte / Etalab 2.0 | Licence de publication d'ADMIN EXPRESS |

**Attributs disponibles et retenus** : `code_insee`, `nom_officiel`, `code_insee_du_departement`,
`superficie_cadastrale`. Attributs présents mais **non consommés** : `cleabs`,
`nom_officiel_en_majuscules`, `statut`, `population`, `date_du_recensement`, `organisme_recenseur`,
`code_insee_du_canton`, `code_insee_de_l_arrondissement`, `code_insee_de_la_region`, `code_siren`,
`codes_siren_des_epci`, `code_postal`.

> **`population` et `date_du_recensement` sont explicitement écartés.** Rien dans le brief ne les demande,
> et les embarquer dans un artefact servi au public serait publier une donnée dont nous n'avons pas
> l'usage. L'artefact ne porte que ce que le rendu consomme.

### 2.1 Le millésime ne s'écrit JAMAIS `LATEST` — clause dure

La couche est publiée derrière l'alias mouvant `ADMINEXPRESS-COG-CARTO.LATEST`. **Épingler l'alias
produirait un millésime qui dérive en silence**, c'est-à-dire exactement la faute que l'arbitrage 10 de
l'orchestrateur demande d'écrire noir sur blanc : une fusion ou un renommage de commune afficherait un nom
périmé comme courant, sans qu'aucun artefact ne le signale.

**Obligation** : le script d'acquisition **résout `LATEST` vers son millésime daté** (lecture des
`GetCapabilities`) et consigne **le millésime résolu** — jamais l'alias — dans `build/reference.json`,
dans `data/massifs-13.php` et dans la phrase d'attribution. Un artefact portant la chaîne `LATEST` est un
artefact refusé en recette.

---

## 3. Architecture gelée : deux artefacts, deux mécanismes, résolution figée à l'ingestion

Le panneau des zones de feu est **rendu par le serveur** (contrainte #3 : le site fonctionne sans
JavaScript). Une résolution « à la lecture » ouvrirait donc la géométrie communale **à chaque rendu de la
page d'accueil**. La résolution appartient au **cron EFFIS**, jamais au chemin de rendu.

Les budgets du §10 du brief **ne mordent pas ici** : ils bornent les octets **transférés**, et rien de
ceci n'atteint le navigateur. Ce qui mord, c'est le chemin de rendu et la mémoire PHP en mutualisé.

| | Communes **par massif** | Polygones communaux |
|---|---|---|
| Calcul | **au build**, exact | — |
| Stockage | baké dans `data/massifs-13.php` | artefact JSON simplifié sous `includes/domain/massifs/` |
| Coût à l'exécution | **nul** | ouverture **paresseuse**, chemin cron uniquement |
| Lecteur | `massifs_massif($code)['communes']` (accesseur existant) | `massifs_commune_de_la_zone()` |

### 3.1 L'artefact de lookup ne vit PAS sous `data/`

`docker/wordpress/plugins-guard.conf` refuse
`^/var/www/html/wp-content/(plugins|themes)/[^/]+/(.*/)?(includes|build|node_modules)/` à toute
profondeur, et **épargne délibérément** `plugins/massifs-core/data/` parce que ce répertoire sert la
géométrie au navigateur. Placer ~1 Mo de géométrie communale sous `data/` **publierait à une URL publique
une donnée qu'aucun client ne demande**. L'artefact vit donc sous `includes/domain/massifs/`, où le garde
le refuse au navigateur — ce qui est le comportement voulu, pas un effet de bord.

### 3.2 Cet artefact-ci s'ouvre en PHP — et ce n'est pas une entorse à `geometrie.php`

`includes/domain/massifs/geometrie.php` l. 4-10 pose que **PHP n'ouvre JAMAIS**
`data/massifs-13.geometrie.json` : ni `file_get_contents`, ni `json_decode`, ni `filesize`, ni
`hash_file`, ni `filemtime`. Cette règle vise **l'artefact servi au navigateur**, dont la taille,
l'empreinte et le jeton de version viennent du build.

L'artefact communal est un **fichier différent, strictement serveur, jamais servi, lu uniquement sur le
chemin cron**. La règle de `geometrie.php` reste entière et n'est pas amendée. **Ne pas lire les deux
comme un seul cas** : un relecteur qui verrait un `file_get_contents` sans cette clause conclurait à une
violation.

### 3.3 Le module domaine reste INERTE

`includes/domain/massifs/module.php` l. 9-13 déclare le module sans hook, sans table, sans option, sans
transient, sans cron, sans route REST, sans écran, sans sortie. **`massifs_commune_de_la_zone()` est une
fonction de lecture pure** ; l'**appel** se fait depuis `includes/ingest/effis/`. Aucun hook n'est ajouté
au domaine.

---

## 4. Règles de domaine — décidées par le propriétaire du projet, appliquées à la lettre

### 4.1 « Commune concernée » par un massif — seuil relatif

**≥ 1 % de la surface du massif.** Tri par **surface décroissante**.

> **Limitation connue et acceptée par le propriétaire, à consigner** : sur un très grand massif, 1 %
> reste une surface importante, et une petite commune réellement concernée peut passer sous le seuil.

### 4.2 L'intersection se calcule sur la source, JAMAIS sur la géométrie publiée

Le calcul par massif se fait sur **`build/source/massifs-13.full.geojson`**, jamais sur
`data/massifs-13.geometrie.json`. Cette dernière a **perdu ses îlots de moins de 25 ha**
(`SIMPLIFICATION.ilots_min_m2 = 250000`) et subi **90 m de Douglas-Peucker**. Intersecter dessus
ajouterait et retirerait des communes **pour des raisons de rendu** — une commune apparaîtrait ou
disparaîtrait parce qu'un îlot a été retiré pour la lisibilité de la carte.

**Zone tampon** : les massifs limitrophes débordent des Bouches-du-Rhône (la Sainte-Baume est à cheval
13/83). L'acquisition couvre donc **le 13 et ses départements limitrophes** (83 Var, 84 Vaucluse, 30
Gard), faute de quoi une commune varoise réellement concernée serait silencieusement absente.

### 4.3 Commune d'une zone de feu — règle unique, et son départage

> **RÉ-GELÉ le 18 août 2026, après implémentation.** La première rédaction de ce §4.3 portait **deux
> formulations que le gel a transportées sans voir qu'elles diffèrent** : l'étiquette du thème dit « la
> commune **la plus proche** », la réponse 2 du propriétaire dit « **la plus grande part** ». Ce ne sont
> pas la même notion. Pire, le §5 n'exposait qu'une API **par point**, structurellement incapable
> d'exprimer « la plus grande part d'un polygone ». Le développeur ne pouvait satisfaire les deux et a
> comblé l'écart par le **centre de la bbox** de la zone. **C'est l'étape de gel qui a échoué, pas le
> développeur.** Le défaut a été trouvé en vérifiant les affirmations de l'agent plutôt qu'en les
> relayant.

**Règle, en une phrase** :

> **La commune la plus proche de la GÉOMÉTRIE de la zone. Si plusieurs communes sont à distance zéro —
> c'est-à-dire que la zone les chevauche — départage par la part de surface de la zone que chacune porte,
> la plus grande l'emporte. Au-delà du plafond de 5 km, silence.**

Ce n'est pas un compromis entre deux règles : c'est **une règle et son départage**. Elle satisfait
l'étiquette au singulier du thème, elle applique la réponse 2 du propriétaire **exactement là où la
réponse 2 s'applique** (le cas du chevauchement), et elle traite le cas « en mer » que l'étiquette seule
ne couvrait pas. Aucune reprise du thème n'est nécessaire.

**La distance se mesure depuis la géométrie de la zone, jamais depuis un point qui la résume.** Voir
§11.7, quatrième contournement refusé.

### 4.4 Plafond de distance — 5 km

Au-delà, **le serveur n'émet rien** plutôt qu'un nom trompeur. `panneau-feu.php` omet déjà proprement la
paire quand la valeur est vide : le silence ne demande aucune modification du thème.

### 4.5 « Hors couverture » est un état ATTEIGNABLE, pas décoratif

`includes/ingest/effis/class-validator.php::couche_emprise()` filtre les entités EFFIS par
**INTERSECTION** de bbox (`intersecte()`), pas par inclusion. Une zone dont la bbox **effleure** le
rectangle départemental est conservée **avec sa géométrie entière** : son point représentatif peut tomber
dans le Var, le Vaucluse, le Gard, ou **en mer**.

**Conséquence normative** : le chemin « aucune commune » est une **issue normale du filtre que nous
livrons déjà**, pas un coin dégradé rare. Les deux branches — nom trouvé, et silence — doivent être
prouvées en recette.

### 4.6 Les deux artefacts échouent INDÉPENDAMMENT

Si l'artefact de lookup est **absent ou corrompu**, les communes par massif **restent servies** : elles
sont bakées dans `data/massifs-13.php` et ne dépendent d'aucun fichier de géométrie. Cette propriété est
délibérée et doit être **prouvée**, pas seulement affirmée.

---

## 5. Fonctions de lecture exposées par l'extension

> **RÉ-GELÉ le 18 août 2026, en même temps que le §4.3.** La première rédaction n'exposait qu'une API
> **par point**. Une signature `( float $lon, float $lat )` est **structurellement incapable** d'exprimer
> « la plus grande part de la zone » : elle ne reçoit pas la zone. C'est la moitié structurelle du défaut
> décrit au §4.3 — et c'est aussi pourquoi le seam public doit désormais être **de forme géométrique**.
> Un relecteur qui infère la règle depuis la signature doit y lire la bonne règle, sinon le même défaut
> reviendra au prochain passage.

**Trois fonctions publiques nouvelles** — une de lecture, sa commodité, et l'attribution. Le total du
module passe de **16 à 19**.

> **Corrigé le 21 août 2026, avant commit, contre le code livré.** Cette ligne annonçait « de 16 à 17 »
> tout en gelant trois fonctions dans les trois blocs qui suivent — l'attribution n'était pas comptée et
> la commodité l'était à moitié. `compat.php` en porte **19**, le README du module en annonce **19** :
> c'est le contrat qui comptait faux, pas le code. **Aucune décision ne change** ; seul le décompte est
> réaligné sur ce que le contrat lui-même gèle plus bas.

```php
massifs_commune_de_la_zone( array $geometrie ): array
```

`$geometrie` est la géométrie GeoJSON de la zone, `Polygon` **ou** `MultiPolygon`, telle qu'elle voyage
déjà sous la clé `geometrie` de l'enregistrement de zone. **L'appelant a donc déjà ce qu'il faut** : rien
de nouveau ne traverse la frontière.

Retour **total** — toutes les clés toujours présentes, jamais de `isset()` chez le consommateur :

| Clé | Type | Valeur quand `trouvee` est `false` |
|---|---|---|
| `trouvee` | `bool` | `false` |
| `insee` | `string` | `''` |
| `nom` | `string` | `''` |
| `departement` | `string` | `''` |
| `distance_m` | `?int` | `null` |
| `etat` | `string` | une constante du §7 |

- `distance_m` vaut `0` quand la zone **chevauche** la commune, sinon la distance en mètres entre la
  géométrie de la zone et le bord communal le plus proche, **plafonnée à 5 000** (§4.4).
- **Départage** : à distance `0`, si plusieurs communes sont chevauchées, celle qui porte **la plus grande
  part de surface de la zone** l'emporte (§4.3).
- **Départage du départage** : à part **strictement égale**, le plus petit `code_insee` tranche.
  **Ajouté pour la REPRODUCTIBILITÉ, pas pour la correction — ce n'est pas une règle de domaine.** Le cas
  est de mesure nulle, mais sans départage énoncé la sortie cesse d'être reproductible d'une exécution à
  l'autre, et les contrôles de recette de ce projet comparent les artefacts **octet par octet** : une
  comparaison sur une sortie non déterministe ne veut plus rien dire. Le critère est arbitraire, stable et
  documenté — c'est tout ce qu'un départage de mesure nulle doit être. **Ne pas le relire comme une
  préférence du propriétaire du projet entre deux communes.**
- `nom` est le `nom_officiel` de l'archive, **verbatim**, UTF-8 brut, **jamais échappé dans le module** —
  l'échappement appartient au point de sortie.

```php
massifs_commune_de_la_zone_nom( array $geometrie ): string
```

Commodité pour l'appelant EFFIS : renvoie `nom` si `trouvee`, sinon `''`. **C'est cette forme qui est
appelée** en remplacement du `''` codé en dur dans `class-validator.php::projeter()`.

**Une fonction par point peut exister en interne** si l'implémentation en a besoin (elle est le cas
dégénéré d'un anneau à un sommet), mais elle **n'est pas le seam public** et l'ingestion ne l'appelle
jamais.

```php
massifs_attribution_communes(): array
```

Même forme que `massifs_attribution()` — `phrase`, `phrase_courte`, `lien_source`, `lien_licence`,
`faits`. **TOTALE** : toujours peuplée, y compris référentiel absent.

**`massifs_attribution_communes()` est SÉPARÉE de `massifs_attribution()`**, qui porte la DDTM. Les deux
ne fusionnent jamais : ce sont deux producteurs, deux licences, deux millésimes.

### 5.1 Aucune fonction « communes par massif » n'est ajoutée

La tâche « Exposer une fonction de lecture des communes concernées par massif » de l'issue est **mal
formulée et ne doit pas être implémentée telle quelle**. `massifs_massif($code)['communes']` existe déjà,
est déjà validé par `referentiel.php::normaliser_ligne()`, et est déjà relayé au JSON public. **Le travail
est de REMPLIR, pas d'exposer.** Une fonction dédiée dupliquerait un accesseur existant et `refacto-cms`
la retirerait. Écart consigné, assumé, et à rapporter.

---

## 6. Routes REST

**Aucune route nouvelle. Aucune signature de route modifiée.**

`includes/rest/public/charge-statuts.php` **n'est pas modifié** :

- l. 233-241 et 253 relaient déjà `$ligne_referentiel['communes']` **verbatim** vers `massifs[].communes` ;
- l. 120-121 et 141 construisent `referentiel.communes_statut` depuis `massifs_lacunes()['communes']['statut']`.

**Remplir le référentiel rend le JSON public vivant sans qu'une ligne de PHP change.** C'est le fait le
plus important du périmètre.

---

## 7. États spéciaux

| Constante | Sens | Émise par le serveur | Rendue par le thème |
|---|---|---|---|
| `ETAT_COMMUNES_OK` | commune résolue | oui | le nom |
| `ETAT_COMMUNES_INCONNUES` | conservée ; **cesse d'être permanente, devient atteignable** | oui | rien (paire omise) |
| `RAISON_COMMUNES_HORS_COUVERTURE` | **emprise de la zone** hors de la couverture de l'artefact, ou commune la plus proche au-delà de 5 km (§4.4, §4.5) | oui | rien (paire omise) |
| `RAISON_COMMUNES_ARTEFACT_ABSENT` | fichier de lookup absent | oui | rien (paire omise) |
| `RAISON_COMMUNES_ARTEFACT_INVALIDE` | fichier illisible ou malformé | oui | rien (paire omise) |

Plus la constante de plafond, valeur **5 000** mètres.

**`referentiel.communes_statut`** : `'calculee'` en nominal, `'inconnue'` en replié.
`STATUT_COMMUNES_DEFAUT` **reste `'inconnue'`** — c'est la seule valeur qui ne puisse jamais être relue
comme « aucune commune concernée ».

> **Pourquoi `'calculee'` et non `'disponible'`** — choix explicite du propriétaire : la valeur dit à un
> réutilisateur du JSON que la liste **résulte de notre propre calcul** et n'est pas une publication
> officielle de la DDTM.

---

## 8. Chaînes fournies par le serveur

Le serveur possède **la donnée et les chaînes**. Le thème les rend ; il n'en compose aucune.

- `nom_officiel` de chaque commune — verbatim de l'archive IGN.
- La phrase d'attribution IGN complète, **avec son millésime résolu** (§2.1).
- `phrase_courte`, `lien_source`, `lien_licence`.

**Le thème ne compose, ne découpe, ne reformule jamais une phrase de licence.** La Licence Ouverte 2.0
impose une formulation exacte ; trois consommateurs qui l'assembleraient chacun produiraient trois
variantes, dont deux non conformes.

---

## 9. Attribution — elle atterrit dans CE lot, pas plus tard

L'obligation d'attribution de la Licence Ouverte 2.0 se déclenche à la **diffusion**. `massifs[].communes`
part dans le JSON public **au moment même** où le fichier est rempli, sans qu'aucune autre chaîne
intervienne.

**Donc : ou bien la mention IGN atterrit dans le même commit, ou bien le remplissage n'est pas commité.**

Deux gabarits rendent aujourd'hui **les mêmes cinq attributions**, et les deux passent à six :

- `templates/page-mentions-legales.php` — `<dl>` sous `<h2 id="sources">`, via le helper local
  `massifs_mentions_source( string $terme, string $phrase, string $lien_licence = '' ): void` ;
- `templates/page-la-demarche.php` — mêmes cinq lectures, même garde `$massifs_a_une_source`, même `<h2>`.

Dans les deux fichiers, les commentaires disant « **les cinq** attributions » deviennent **six**. Un
commentaire qui contredit le code qu'il décrit est précisément ce que `refacto-cms` signale.

**`templates/footer.php` n'est PAS touché.** Sa doctrine écrite (l. 13-15) est que « créditer une source
dont aucune donnée n'est affichée est une affirmation fausse » et que « chaque attribution arrive avec sa
couche ». Le pied ne rend pas de communes : il ne crédite pas l'IGN. C'est une décision, pas un oubli.

---

## 10. Reproductibilité — écart assumé au précédent des massifs

Le précédent de ce projet est fort : `build/source/massifs-13.full.geojson` est commité **en entier**
(3 022 441 octets), son sha256 est consigné dans `data/massifs-13.php` sous `source.archive`, et
`build/.gitattributes` le marque `-text` parce que ses octets sont empreintés.

**Cette issue s'en écarte, délibérément.** Est commité **l'extrait dérivé, découpé à la bbox**, accompagné
du **MD5 national**, de **la bbox de découpe** et du **millésime résolu**. La récupération réseau est un
script **séparé et documenté** ; **l'import reste rejouable HORS LIGNE depuis le seul extrait commité**.

| | Précédent massifs | Cette issue |
|---|---|---|
| Propriété conservée | import rejouable depuis le seul dépôt | **idem** |
| Propriété abandonnée | — | provenance à l'octet du fichier national entier |

Committer ADMIN EXPRESS national n'est pas viable ; la propriété qui compte — **un import rejouable depuis
le dépôt seul** — est préservée. L'écart est nommé ici pour qu'il ne passe pas inaperçu.

### 10.1 `build/reference.json` doit bouger dans le MÊME commit

Son propre `a_propos` l'impose : `npm run verifier` compare les artefacts à ces valeurs, et **toute
modification d'outillage doit régénérer artefacts et référence ensemble**. Il épingle aujourd'hui
`mapshaper 0.6.102`, `node_major 24`, et les empreintes source/géométrie. Toute dépendance nouvelle est
**build-only**, jamais exécutée à l'exécution du site, même statut que mapshaper.

---

## 11. Interdits

1. Le thème n'appelle jamais une source externe, ni une fonction d'ingestion.
2. Le thème ne calcule jamais une règle métier — ni seuil de 1 %, ni plafond de 5 km, ni part majoritaire.
3. Le thème ne compose jamais une phrase d'attribution ni un nom de commune.
4. L'extension n'émet aucun HTML de présentation publique.
5. **Aucune requête navigateur** vers l'IGN, ni vers aucun tiers. L'acquisition est **hors ligne, au
   build**. (Contrainte #2 — non négociable.)
6. **Aucun code INSEE dans `massifs[].communes`.** Ce tableau porte des **noms**. Un code INSEE y serait
   expédié tel quel dans le JSON public et **affiché au visiteur**.
7. Les **quatre** contournements refusés ne reparaissent sous aucune forme :
   a. **massif le plus proche** — substituer une notion à une autre ;
   b. **attribut `commune` du WFS EFFIS** — schéma jamais interrogé ;
   c. **points chefs-lieux** — Arles couvre 759 km², un feu au nord du territoire d'Arles est plus proche
      du chef-lieu de Saint-Martin-de-Crau, ce qui afficherait **un nom faux comme un fait**, dans le
      panneau même qui annonce « estimation satellite » ;
   d. **tout point qui résume la zone** — centre de bbox, centroïde, premier sommet. *Ajouté le 18 août
      2026, après l'avoir trouvé dans le code livré.* Une zone EFFIS de 30 ha et plus est couramment en
      croissant, en L, ou en plusieurs parties : **le centre de sa bbox peut tomber hors de la zone**,
      dans une commune que le feu n'a jamais touchée, ou en mer. **Le plafond de 5 km ne rattrape pas
      cela** : un centre situé à 2 km du foyer réel, à l'intérieur d'une commune voisine, renvoie le nom
      de cette voisine **avec assurance et sous le plafond**. C'est le raisonnement exact qui a fait
      refuser les chefs-lieux (c), et il se transporte presque mot pour mot. **La distance se mesure
      depuis la géométrie de la zone, jamais depuis un point qui la résume.**
8. **`LATEST` ne s'écrit dans aucun artefact** (§2.1).
9. **Rien sous `tests/rendu/`.** Ce répertoire porte des restes non commités d'un lot précédent
   (`recette-rendu.mjs` modifié, `fraicheur.php` non suivi). `tests/scenarios/` est un répertoire
   différent et n'est pas visé par cet interdit.
10. Aucune montée de version de schéma. `SCHEMA` reste `2` : `referentiel.php::charger()` ignore les clés
    racines inconnues et `normaliser_ligne()` valide déjà `communes` et `communes_source`.

---

## 12. Ce qui est mesuré, ce qui est décidé, ce qui reste à établir

**Mesuré** (requêtes et lectures réelles, 18 août 2026) : les chiffres du §2 ; l'unicité de `code_insee` ;
**Marseille 13055 présente exactement une fois** ; UTF-8 intact ; `nom_officiel` non vide sur les 119 ;
l'absence totale de donnée IGN dans le dépôt ; le filtrage par intersection de bbox d'EFFIS ; le relais
verbatim de `communes` par `charge-statuts.php` ; l'omission propre de la paire par `panneau-feu.php` ;
les quatre artefacts écrits par `importer.mjs` ; le contenu de `reference.json` ; la doctrine de
`geometrie.php`, de `module.php` et de `footer.php` ; le refus de `plugins-guard.conf`.

**Décidé** : §4.1 à §4.4 par le propriétaire du projet ; §2.1, §5.1, §7, §9, §10 par l'orchestrateur de
lot ; §3.1, §3.2, §3.3, §4.5, §4.6 par le lead d'issue à partir des lectures ci-dessus.

**À établir par `dev-back-cms`, et non gelé ici** : le format exact de l'artefact de lookup et son
tolérance de simplification ; la méthode de jointure spatiale sous mapshaper ; l'implémentation du
point-en-polygone et de la distance point-segment ; la consommation mémoire réelle, **à mesurer et non à
postuler** ; le nombre exact de communes après ajout de la zone tampon.

---

## 13. Recette — contrôles nommés, obligatoires

À router vers `test-integration-cms` au niveau du lot. **Aucun n'est écrit sous `tests/rendu/`.**

1. **Unicité de `code_insee`** sur l'artefact produit.
2. **Marseille 13055 présente exactement une fois** — COG CARTO peut porter des arrondissements
   municipaux ; le contrôle doit rougir s'ils apparaissent.
3. **`nom_officiel` UTF-8 préservé brut** — `Lançon-Provence`, `Belcodène`, `Saint-Pierre-de-Mézoargues`
   traversent sans mutation ni double échappement.
4. **Panne indépendante** : artefact de lookup supprimé ⇒ `massifs[].communes` **toujours servi**, et
   `massifs_commune_de_la_zone()` renvoie `trouvee=false` avec `RAISON_COMMUNES_ARTEFACT_ABSENT`.
5. **Hors couverture** : point en mer et point hors 5 km ⇒ silence, et **aucune paire « Commune la plus
   proche »** dans le HTML.
6. **Aucun `LATEST`** dans les artefacts produits.
7. **Aucune origine tierce** : le HTML et les assets rendus ne contiennent aucune URL `geopf.fr` ni `ign.fr`.

### 13.1 Scénarios existants à mettre à jour — jamais à supprimer

| Fichier | Ligne | Sort |
|---|---|---|
| `tests/scenarios/40-zones-ingestion-nominale.php` | 184 | **Bascule.** `t_egal( '', … )` doit devenir une assertion sur le nom résolu. |
| `tests/scenarios/47-zones-jonction-plugin-gabarit.php` | 255 | **Bascule.** Doit prouver **les deux branches** : nom rendu quand résolu, paire omise quand vide. |
| `tests/scenarios/46-zones-route-publique.php` | 163 | **Ne bascule probablement pas** : `array_keys` + `sort` assertent la **présence** de la clé, pas sa vacuité. À **vérifier**, pas à modifier spéculativement. |

**Une assertion se met à jour, elle ne se supprime pas.** Un test qui cesse d'asserter est pire qu'un test
rouge. La règle « paire omise, ni tiret, ni "non renseigné" » **survit** : elle cesse d'être permanente et
devient **atteignable** par le plafond de 5 km et le hors-couverture (§4.5).

---

## 14. Amendements aux contrats gelés

Le précédent de la maison est double : la chaîne qui amende porte le texte motivé **dans son propre
contrat** (#23 §3.2, #28 arbitrage 7, #30 A-9), et le contrat amendé reçoit un **renvoi daté** (#2, amendé
par la chaîne #20). Les deux sont appliqués.

**Ces deux amendements ne corrigent pas une erreur : ils actent qu'une règle exacte au moment où elle a
été écrite est désormais dépassée.** C'est un régime différent de celui d'une affirmation fausse en place
— d'où le renvoi daté plutôt que la réécriture.

### 14.1 Contrat #8, arbitrage A-11

A-11 gelait `communes: []` exposé, plus `referentiel.communes_statut: "inconnue"`, en notant que
« l'enrichissement par IGN ADMIN EXPRESS relève d'une issue `referentiel` distincte ». **Cette issue est
celle-là.**

**Amendement** : `communes` reste exposé et devient **peuplé** ; `communes_statut` vaut `'calculee'` en
nominal et **conserve `'inconnue'` en replié**. Le raisonnement d'A-11 est **intact** : une liste vide
seule se lirait « aucune commune concernée », et le drapeau reste la seule valeur qui ne puisse pas être
relue ainsi. **Aucune clé n'est ajoutée ni retirée ; aucun consommateur n'a d'adaptation à faire.**

### 14.2 Contrat #11, arbitrage A-8

A-8 gelait « l'emplacement existe et se tait » pour `commune_la_plus_proche`, sur la base de
`includes/domain/massifs/README.md` l. 405-408.

**Amendement** : la clé est désormais **peuplée quand une commune est résolue à moins de 5 km**, et
**vide sinon**. Les **deux substitutions refusées par A-8 restent refusées** (massif le plus proche ;
attribut `commune` du WFS EFFIS), et une troisième s'y ajoute (points chefs-lieux, §11.7). La règle
d'omission propre **survit intacte** ; elle cesse seulement d'être le seul comportement possible.

### 14.3 Documentation qui devient fausse au commit

| Fichier | Ce qui devient faux |
|---|---|
| `includes/rest/public/README.md` l. 108 | « **Vide aujourd'hui** » à propos d'un tableau désormais peuplé |
| `includes/rest/public/README.md` l. 143 | `inconnue` documenté comme le sens du drapeau, sans mentionner `calculee` |
| `includes/domain/massifs/README.md` l. 404-408 | « `communes` est donc **toujours vide** » ; `statut` vaut `inconnue` ; l'import IGN est « une issue à part entière » |

> **`« Absent vaut mieux que faux. »`**, qui clôt le bloc « Lacunes assumées » du README domaine, est un
> **principe, pas un constat d'état**. Il **survit entier** et justifie même le plafond de 5 km et le
> silence hors couverture. Seules les trois affirmations d'état autour de lui cessent d'être vraies.

---

## 15. Empreinte fichiers — et ce qu'elle dit de l'issue

Empreinte **déclarée** par l'issue : **une** ligne — `includes/domain/massifs/`.
Empreinte **réelle** : **13 chemins** (14 si le README du domaine est compté à part du sous-arbre qui le
contient).

| # | Chemin | Cause |
|---|---|---|
| 1 | `includes/domain/massifs/` (dont `build/`, `README.md`) | **Anticipé par l'issue** |
| 2 | `data/massifs-13.php` | **Défaut de l'issue** — sortie de `importer.mjs` |
| 3 | `data/massifs-13.geometrie.json` | **Défaut de l'issue** — sortie de `importer.mjs` |
| 4 | `includes/ingest/effis/class-validator.php` | Réponse 3 du propriétaire + §5.1 |
| 5 | `includes/rest/public/README.md` | Devient faux au commit (§14.3) |
| 6 | `templates/page-mentions-legales.php` | LOv2 se déclenche à la diffusion (§9) |
| 7 | `templates/page-la-demarche.php` | Idem — découvert par lecture, non par l'issue |
| 8 | `tests/scenarios/40-zones-ingestion-nominale.php` | Encode la règle retirée |
| 9 | `tests/scenarios/47-zones-jonction-plugin-gabarit.php` | Idem |
| 10 | `tests/scenarios/46-zones-route-publique.php` | **Conditionnel** — seulement si vérification montre qu'il bascule |
| 11 | `docs/contracts/issue-45.md` | Ce contrat |
| 12 | `docs/contracts/issue-8.md` | Renvoi daté (§14.1) |
| 13 | `docs/contracts/issue-11.md` | Renvoi daté (§14.2) |

**Trois causes, à ne pas confondre :**

- **Anticipé : 1.**
- **Défaut de l'issue telle qu'écrite : 3** (§2, §3 et `build/`). L'empreinte déclarée **excluait les
  sorties de son propre module** : `importer.mjs` a toujours écrit ces deux artefacts. Aucun import
  n'aurait pu être réalisé à l'intérieur de l'empreinte déclarée. C'est un fait sur l'issue, pas sur le
  travail.
- **Le reste : le processus qui fonctionne.** Ces chemins n'étaient **pas connaissables depuis la liste de
  tâches** ; il fallait lire le graphe des consommateurs — ce à quoi servent précisément les étapes
  brainstorm et leaddev.

> **L'issue a sous-estimé sa portée parce qu'elle était formulée « importer un jeu de données » alors que
> le travail réel est « retirer une lacune documentée ».** Une lacune citée par son nom dans deux contrats
> gelés, deux README, trois scénarios de recette et un commentaire de code a une empreinte égale à **son
> graphe de citations**, pas au répertoire où atterrit le fichier.
>
> **Cadrer par qui cite la lacune, pas par où va le fichier.**

---

## 16. Suites à ouvrir — hors empreinte de cette issue

1. **Panneau massif : aucun emplacement « communes concernées » n'existe dans le thème.** La donnée part
   dans le JSON public et n'est **rendue nulle part** dans le panneau. Il est **explicitement interdit**
   d'en créer un ici. Issue `carte` / `a11y` à ouvrir.
2. **« La démarche » doit annoncer `communes_statut: 'calculee'`** et la méthode de calcul (seuil de 1 %,
   plafond de 5 km). La prose éditoriale est hors empreinte.
3. **Aucune API d'attribution en forme de liste n'existe.** Chaque source est un emplacement codé en dur,
   dans **trois** fichiers (pied, mentions légales, la démarche). Une septième source rencontrera la même
   friction, dans trois fichiers encore.
4. **`tests/rendu/` porte des restes non commités** d'un lot précédent — à traiter au niveau du lot.
