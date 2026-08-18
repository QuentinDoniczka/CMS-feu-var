# Module `ingest/tuiles` — fond de carte auto-hébergé

Contrats de référence : [`docs/contracts/issue-9.md`](../../../../../docs/contracts/issue-9.md),
**avenants du 14 août (§13) et du 17 août 2026 (§14) compris**, et
[`docs/contracts/issue-71.md`](../../../../../docs/contracts/issue-71.md) — emprise déclarée et
toponymes cuits.

**Le fond cuit au build.** Pyramide raster bornée z5–z12, un seul pipeline, deux artefacts. Aucun
runtime de récupération, aucun cron, aucun fetcher : le fond de carte ne change jamais, la génération
appartient au **build hors ligne**, et l'hôte mutualisé ne fait que servir des octets statiques.

**Corollaire opposable : la surface d'écriture du fond de carte en production est nulle.**

---

## 1. Ce que le module expose

Trois fonctions, et trois seulement, définies dans `compat.php`. Toutes **totales** — aucune exception,
aucun `WP_Error`, aucun `null`, toutes les clés toujours présentes — et toutes rendant du **brut non
échappé** : c'est le thème qui échappe, une fois, à la sortie.

| Fonction | Rend |
|---|---|
| `massifs_fond_de_carte()` | `disponible`, `type`, `format` (**classe de média**, `raster`), `format_tuile` (**extension**, `png`), `url_modele`, `zoom_min`, `zoom_max`, `taille_tuile`, `nombre`, `bbox`, `mode`, `version`, `sha256`, `octets`, `attribution`, `attribution_url` |
| `massifs_fond_de_carte_statique()` | `disponible`, `largeur`, `hauteur`, `porte_les_statuts`, `contours_massifs`, `version`, `sha256`, `octets` |
| `massifs_attribution_fond_de_carte()` | `phrase`, `lien_licence`, `faits{}` |

`massifs_fond_de_carte_etat()` et `massifs_fond_de_carte_disponible()` **n'existent pas** : elles
n'auraient aucun consommateur, `disponible` et `mode` étant déjà des clés, et « une seconde manière de
poser la même question est une divergence en attente » (§1.4).

Trois pièges, tous à l'exécution seulement :

- **`url_modele` ne passe JAMAIS par `esc_url()`.** `esc_url()` supprime `{` et `}`, hors de sa liste
  blanche, et produit `…/zxy.png`. Panne silencieuse. `esc_attr()` ou `wp_json_encode()`.
- **`zoom_max` est la borne de la PYRAMIDE, pas une autorisation de zoom.** La carte reste plafonnée au
  `zoom_max` du référentiel (= 11). Le douzième niveau existe pour la netteté sur écran dense ; réglé en
  `maxZoom`, il afficherait un fond **sans polygones**.
- **`bbox` est l'emprise DÉCLARÉE de la pyramide** (§5), en coordonnées entières de tuile à z12. Il se
  trouve qu'elle reste un sur-ensemble strict de `massifs_emprise()['bbox']` — mais c'est désormais une
  **conséquence contrôlée**, et non plus une définition : elle ne se recalcule plus depuis la géométrie.
  Elle borne la couche ; elle ne cadre pas la vue initiale.

Il n'y a **délibérément pas de clé `url`** sur l'image statique : l'artefact vit dans le thème, qui
résout son propre chemin d'asset. L'extension publie des **faits**, jamais un chemin de thème
(arbitrage A-3).

## 2. Ce que le module ne fait pas

Aucun hook, aucun filtre, aucune table, aucune option, aucun transient, aucun cron, aucune route REST,
aucun écran, aucun rôle, aucune capability, aucune sortie. Le charger ne fait rien d'observable ; ne pas
le charger n'est pas fatal.

**PHP n'ouvre jamais une tuile ni l'image statique** : ni `file_get_contents`, ni `getimagesize`, ni
`filesize`, ni `hash_file`, ni `file_exists`. `disponible` atteste la présence des **métadonnées**, jamais
celle des octets. Une tuile manquante se dégrade en trou visuel, jamais en erreur PHP — et 295 amorçages
WordPress pour servir des octets immuables contrediraient les 2,5 s du §10 du brief.

**Aucun `wp_remote_*`, aucun `curl`, aucun `file_get_contents` sur une URL** dans tout
`includes/ingest/tuiles/**` (invariant I-9.8). Le seul fichier du dépôt qui touche le réseau est
`build/recuperer.mjs`, joué à la main, jamais un prérequis de `npm run construire`.

## 3. Validation stricte, tout ou rien

Une clé manquante ou d'un type inattendu fait rejeter **le fichier de métadonnées entier**, jamais la
seule clé. Un fond partiellement décrit produirait une couche montée sur des bornes fausses, c'est-à-dire
une carte qui affirme quelque chose de faux sur la géographie — pire qu'une carte absente.

Le repli n'expose **aucun code de raison**, et c'est un choix : le §1.4 du contrat a écarté
`massifs_fond_de_carte_etat()`, seul consommateur possible d'un tel code, qui serait donc du code mort.
Le diagnostic appartient à `npm run verifier`, qui nomme précisément ce qui cloche ; l'exécution, elle,
n'a qu'une décision à prendre — monter la couche, ou ne pas la monter.

## 4. Fichiers

| Fichier | Rôle |
|---|---|
| `module.php` | Amorce inerte. Nom imposé par le chargeur de l'extension. |
| `etats.php` | Constantes et valeurs de repli. Feuille de l'arbre de dépendances. |
| `metadonnees.php` | Chargement, validation stricte, mémoïsation par `static`. |
| `fond.php` | `fond()` et `statique()`. |
| `attribution.php` | `attribution()`. |
| `compat.php` | Surface `massifs_*()`, chaque fonction gardée par `function_exists()`. |
| `.htaccess` | Ce sous-arbre porte du code, jamais un octet servi au navigateur. |
| `build/` | Le pipeline. Voir §5. |

Artefacts produits, hors de ce répertoire :

| Artefact | Chemin | Servi ? |
|---|---|---|
| Métadonnées | `data/tuiles/fond-13.php` | non (lu par PHP en interne) |
| En-têtes de cache | `data/tuiles/.htaccess` | — |
| Pyramide | `data/tuiles/<version>/{z}/{x}/{y}.png` | oui |
| Image statique | `themes/massifs/assets/img/carte-statique.png` | oui, **par le thème** |

## 5. Le pipeline

```
npm ci                # une fois
npm run recuperer     # RÉSEAU. À la main, jamais en intégration continue.
npm run construire    # TOUJOURS hors ligne. Consomme l'archive commitée.
npm run verifier      # Recette. Ne réécrit rien.
```

`recuperer` interroge **Overpass API** sur la bbox du référentiel, convertit en GeoJSON, découpe au
département, simplifie, extrait les toponymes `place=city|town|village` avec leurs trois attributs, et
écrit `build/source/osm-13.json` (~4 Mo) avec son manifeste — **les deux sont commités**, et c'est ce
qui rend `construire` reproductible hors ligne (§11 du brief, arbitrage A-8).

Une charge Overpass tronquée par timeout rend un JSON **syntaxiquement valide mais amputé** : aucun
`JSON.parse` ne l'attrape. `recuperer` la rattrape par des **dénombrements** par couche, un contrôle de
recouvrement de la bbox départementale, et des bornes de taille d'archive. Sortie ≠ 0, et **l'archive en
place n'est jamais écrasée par un échec**.

`toponymes` est la **première couche dont les attributs portent l'information**, et un dénombrement seul
ne peut pas y suffire : une charge où chaque `name` serait arrivé vide produirait **moins d'étiquettes au
lieu d'un échec**. Quatre gardes s'y ajoutent donc, toutes en sortie ≠ 0 — **complétude** (`rejetés /
retournés ≤ 20 %`), **coordonnées finies**, **plancher après découpe** (une découpe qui vide la couche
signifie que le polygone `terre` est faux), et **`place=city` ∈ [1, 10]** : Marseille et Aix sont
structurellement présentes, et zéro `city` signale une charge tronquée. Le risque assumé est qu'un
retaggage OSM fasse rougir cette dernière à tort — c'est le bon sens de la défaillance : **bruyante,
datée, réparable par une décision humaine écrite.**

`construire` **relit les six `--c-carte-*` et `--c-charbon` dans `themes/massifs/assets/css/tokens.css`**
et **sort en code ≠ 0** si l'un est absent, renommé ou divergent, en nommant le jeton, la valeur lue et la
valeur attendue (invariant I-9.7). C'est ce qui rend D-01 opposable et empêche un `filter: grayscale()` de
revenir par la fenêtre : **le monochrome est cuit à la génération**.

### L'emprise est déclarée, et un débordement arrête le build

`EMPRISE_DECLAREE = { zoom: 12, x0: 2100, x1: 2114, y0: 1490, y1: 1503 }`. Les zooms z5 à z11 en
dérivent par **décalage entier** — `x0 >> (12 − z)` —, ce qui rend l'emboîtement des huit niveaux
*prouvé* plutôt que calculé : `floor( floor(a·2¹²) / 2^(12−z) ) === floor( a·2^z )`, aucun flottant,
aucun décalage d'une colonne quand un bord tombe pile sur une frontière de tuile. `bboxDeGrille()` rend
alors exactement l'emprise déclarée : **déclaré === publié**, à l'octet.

Les quatre entiers viennent d'une marge de **0,05°** appliquée aux quatre bords de
`massifs_emprise()['bbox']`, **une fois, le 17 août 2026, puis gelée**. Elle n'est jamais réévaluée au
build : la réévaluer rétablirait le couplage que #71 supprime. Marges résiduelles obtenues : ouest
0,0861° · est 0,0754° · nord 0,0588° · sud 0,0884°. Seul le bord sud franchit une frontière de tuile,
d'où z12 15 × 14 = **210** et **295** tuiles au total.

**Le non-débordement est contrôlé deux fois, de deux manières indépendantes**, et les deux sortent en
code ≠ 0 :

- `construire` balaye **chaque sommet** de chaque contour, **avant toute rasterisation**, et nomme le
  massif, le bord, la valeur, la borne et le débordement ;
- `verifier` rejoue ce balayage **et** contrôle, au niveau de la tuile, que `grille( emprise, 12 )` est
  contenue dans `EMPRISE_DECLAREE` sur ses quatre bords.

Le message d'erreur prescrit de **décider** une nouvelle emprise, jamais de la recalculer. L'ancien
contrôle était une **tautologie** — il testait un sur-ensemble sur une bbox dérivée de la même géométrie
et ne pouvait jamais rougir —, ce qui est très exactement ce qui a laissé `ded0f2f` invalider 280 tuiles
en silence.

**Changer l'emprise est une décision humaine, écrite** : elle re-cuit les 295 tuiles, déplace la bbox
publiée et bouge les dimensions de l'image statique.

Aucune coordonnée, aucun compte de tuiles n'est codé en dur ailleurs : les grilles se dérivent de
l'emprise déclarée, et l'emprise du référentiel reste lue dans `data/massifs-13.php`.

**Émission atomique** : tout est écrit en bloc, après tous les contrôles, et la version précédente n'est
supprimée qu'après succès complet. Un build à moitié appliqué laisserait des tuiles neuves et des
métadonnées anciennes, donc **une URL qui ment**.

**La version est dans le chemin**, dérivée du contenu (8 premiers hexadécimaux du sha256 du manifeste),
jamais en query : c'est ce qui mérite `immutable`.

### Mode dégradé

Sans archive OSM lisible, `npm run construire` :

- **produit quand même l'image statique**, depuis `data/massifs-13.geometrie.json` seule, que nous
  possédons hors ligne — c'est la ligne de DoD §5.5 qui ne dépend d'aucun accès réseau (invariant I-9.9) ;
- **n'émet aucune pyramide** : 295 aplats uniformes seraient une carte qui affirme quelque chose de faux
  sur la géographie ;
- **laisse intacte** la pyramide déjà en place — les métadonnées déclarent le fond indisponible, donc
  personne n'en publie l'URL, et détruire des artefacts commités parce qu'une machine n'a pas l'archive
  serait un défaut hostile ;
- écrit `mode => 'degrade'`, avertit **en tête et en pied** de sortie, et **sort en code 0**.

`npm run verifier` **sort alors en code ≠ 0**. Un artefact dégradé est constructible en local sans réseau,
**jamais commitable en silence**.

### Recette

```
PHP_BIN="docker compose run --rm -T wpcli php" \
MASSIFS_PHP_RACINE=/var/www/html/wp-content/plugins/massifs-core \
npm run verifier
```

**Sans PHP atteignable, la recette échoue — elle ne saute pas ses contrôles** (interdit 4 du contrat #20).
Elle relit les métadonnées **par PHP**, puis **charge le module et mesure la surface publique elle-même** :
contrôler le fichier de métadonnées ne suffirait pas, ce que la chaîne #7 consomme, ce sont les **clés du
contrat**, et un renommage de clé casserait la carte sans toucher un octet d'artefact.

## 6. Ce que le build ne garantit PAS

**La reproductibilité binaire inter-plateformes n'est pas revendiquée.** `resvg` et `sharp` sont des
binaires natifs, et rien ne promet que deux machines produisent les mêmes octets de PNG.
`build/reference.json` garantit la **détection de dérive**, et rien de plus. Ne jamais présenter cette
garantie pour celle du contrat #2, où la géométrie, elle, est reproductible à l'octet.

Ce qui **est** déterministe et écrit à la main plutôt qu'emprunté : la quantification sur palette fermée
et l'encodage PNG-8 (`png8.mjs`). Trois exigences le demandaient et aucune bibliothèque ne les offrait
ensemble — absence **structurelle** de chunk `tEXt`/`iTXt`/`XMP` (I-9.2), palette **fermée et
recalculable** par la recette, et encodage déterministe.

**Le JEU de toponymes est déterministe ; les OCTETS ne le sont pas davantage qu'avant.** Le classement
repose sur des entiers et une règle fermée — classe, puis population décroissante, puis nom par `<` sur
la chaîne brute, **jamais `localeCompare`** — si bien que deux machines choisissent les mêmes noms, aux
mêmes ancres. Ne pas lire ce déterminisme-là comme une promesse de reproductibilité binaire : `resvg`
reste un binaire natif.

**Le mode dégradé couvre désormais aussi les toponymes.** Une archive sans couche `toponymes`
exploitable — une archive antérieure à #71, par exemple — bascule le build en `degrade` : aucune
pyramide n'est émise et `npm run verifier` sort en échec. **I-9.9 est intact** : l'image statique est
produite quand même, hors ligne, depuis la seule géométrie, sans étiquettes. Seul le *contenu* de
« complet » change, jamais la garantie.

## 7. Réglages mesurés, non choisis

> **Aucun poids absolu n'est écrit ici.** Le §7 en portait un — *142 464 o* — et il s'est périmé deux
> fois en quatre jours (`ded0f2f`, `fd2c10f`), puis une troisième avec l'emprise déclarée de #71. Un
> nombre qui se périme dans une prose que rien ne vérifie est pire qu'une absence de nombre. **Le poids
> courant de l'artefact vit dans `build/reference.json`, clé `statique.octets`, et dans aucune prose.**
> Ce qui figure ci-dessous, ce sont des **écarts entre couches**, qui sont stables — datés « mesures du
> build initial du 13 août 2026, ordre de grandeur, non normatives ».

| Réglage | Valeur | Pourquoi cette valeur |
|---|---|---|
| Paliers d'anticrénelage | **0**, soit 7 couleurs | Mitigation (1) du §2. Écarts mesurés : 3 paliers → 1 palier **−47 974 o**, 1 palier → 0 palier **−61 652 o**. Voir la note « 1 palier » ci-dessous. |
| Couches de l'image statique | **terre + eau** | Mitigation (2) du §2, appliquée après (1) qui ne suffisait pas. Écarts : retrait de `routes` **−13 115 o**, puis de `vegetation` **−22 245 o**. L'ordre suit le §4.2 de `MASTER.md` (`--c-carte-trait` n'est « jamais porteur d'une limite qui compte ») et nomme lui-même ce qui porte l'orientation : « la forme du littoral, l'Étang de Berre et les 25 contours ». **`terre` et `eau` viennent toutes deux d'OSM** : l'attribution posée sous l'image reste vraie. |
| Largeur de l'image statique | **1600 px** | Contrat §2. La mitigation (3), 1280 px, **n'a pas été appliquée**. |
| Épaisseur des contours | **2 px** | Une largeur entière rend un trait d'épaisseur constante une fois quantifié ; 1,5 alternerait entre 1 et 2 px le long du tracé, ce qui se lirait comme une différence entre massifs. |
| Voirie extraite | `motorway`, `trunk`, `primary` | `secondary` ajoute 12 000 voies et triple l'encre sans rien apporter à l'orientation à z ≤ 12. |
| Emprise de la pyramide | **déclarée**, `{ zoom: 12, x0: 2100, x1: 2114, y0: 1490, y1: 1503 }` | Voir le §5. Marge de 0,05° appliquée **une fois**, le 17 août 2026, puis gelée. **295 tuiles**, image statique de rapport **15/14**. |
| Corps des toponymes, pyramide | **19 px** | Deux planchers indépendants, le plus haut l'emporte. **Optique** : `carte.js` pose `zoomSnap: 0.25`, donc Leaflet charge `Math.round(zoom)` et met le reste à l'échelle en CSS, d'un facteur garanti de 0,7071 ; pour tenir `--fs-100` = 13 px il faut 13 / 0,7071 = 18,4. **Typographique** : à 13 px une hampe d'Atkinson peut se quantifier entièrement en `--c-carte-trait` (4,17:1) et l'étiquette perd son cœur à 6,82:1. La pyramide n'a aucun plafond d'octets. |
| Corps des toponymes, statique | **28 px**, plancher **25 px** | Plancher **dérivé** : 1600 px sur les 186 mm utiles de l'A4 (§13 de `MASTER.md`) = 218,5 ppp ; 8 pt = 8/72 in × 218,5 = 24,3 px, arrondi **vers le haut**. 28 px le franchit de 12 %. Voir le §8, `OUVERT`. |
| Halos | **1,5 px** (pyramide), **2 px** (statique) | D-28 avait rejeté un halo de 4 px pour cause de **remplissage de la boîte englobante**. Les rapports ici sont 1,5/19 = **0,079** et 2/28 = **0,071** : un ordre de grandeur sous ce mode de défaillance. C'est le rapport qui compte, pas le pixel. |
| Densité de toponymes | **25 par Mpx** | Appliquée à l'aire, **en pixels de toile, de `massifs_emprise()['bbox']`** — jamais de la toile entière : la toile z9 fait 0,393 Mpx contre 0,158 Mpx pour l'emprise, et l'erreur livrerait 2,5 fois trop d'étiquettes. Donne z9 **4**, z10 **16**, z11 **63**, z12 = z11. |
| Zoom perçu de la statique | **10** | Le jeu de la statique est celui de ce zoom. Valait 9, **révisé le 18 août 2026 sur mesure** : à z9 les trois autres candidats — Aix-en-Provence, Martigues, Aubagne — sont rejetés **aux cinq ancres**, contre Sainte-Victoire, la Côte Bleue et le Garlaban, et la feuille ne portait plus que « Marseille ». Une image de repli portant un seul nom n'est l'équivalent de la carte que formellement. **C'est le seul degré de liberté** : si la feuille paraît trop nue ou trop chargée, on bouge cet entier et on re-mesure — jamais on ne choisit des noms à la main. |
| Seuils de texture à 360 px | plage **89**, moyenne **159** | **Mesurés le 18 août 2026** sur les **six** étiquettes de la statique, puis gelés au **minimum mesuré moins 20 %** : plage minimale 112,4 (*Istres*) → 89, moyenne minimale 199,7 (*Marignane*) → 159. Étendue mesurée : plage 112,4 → 144,6, moyenne 199,7 → 207,0. La prédiction de vraisemblance du contrat donnait ≈ 69 et ≈ 207 ; une plage de 20 aurait signalé un défaut de pipeline. **Le seuil suit la mesure, jamais l'inverse** — et c'est le build qui l'a imposé, en refusant d'émettre sous un seuil hérité d'un échantillon d'une seule étiquette. |
| Bornes Overpass des toponymes | **[100, 597]** | Procédure en deux passes : première passe sous une enveloppe large, `n = 199` éléments retournés dont 2 `place=city`, puis resserrement à `n/2` et `3n`. La **seconde** archive est celle qui est commitée. |

**Note « 1 palier ».** Avant #71, 1 palier d'anticrénelage était hors d'atteinte, et de peu : les deux
mesures à deux couches donnaient un rapport de **1,2886**, appliqué à une statique de 120 768 o, soit
155 641 o contre un plafond de 153 600 — un dépassement de **1,3 %**. `PALIERS = 0` était donc un
**réglage tenu, pas une dette**. **Depuis #71 ce calcul a changé** : l'emprise déclarée a fait maigrir la
statique, et le même rapport de 1,2886 appliqué à son poids courant — `build/reference.json`, clé
`statique.octets`, et aucune prose — retombe **sous le plafond**. 1 palier
redeviendrait finançable en octets — il n'est pas activé pour autant, la palette étant **fermée à 7** par
l'interdit 9 du §7 du contrat #71. **La justification a changé de nature** : ce n'est plus le plafond
d'octets qui tient `PALIERS = 0`, c'est la fermeture de la palette. C'est une décision de design, à
rouvrir par une révision de contrat.

**La rampe accidentelle à cinq niveaux.** `PALIERS = 0` ne veut pas dire « pas d'anticrénelage » :
`resvg` lisse toujours le bord d'un tracé, et le quantificateur au plus proche voisin rabat ce dégradé
sur la palette fermée. Entre l'encre et le fond il en sort **cinq niveaux** — `--c-carte-encre` jusqu'à
~30 % vers le fond, `--c-carte-trait` jusqu'à ~80 %, `--c-carte-vegetation` jusqu'à ~91 %,
`--c-carte-terre` jusqu'à ~96 %, `--c-carte-fond` au-delà. Elle est gratuite et elle est réelle.
**Réserve à lire avec** : la frange d'un glyphe est peinte en `--c-carte-trait`, qui vaut **4,17:1** et
**n'est pas le texte** ; le 6,82:1 repose sur le **cœur** du glyphe. Le contrôle **C-g** de la recette
mesure ce cœur — rapport `encre / (encre + trait)` ≥ 35 %, mesuré entre **47 % et 78 %** — et c'est ce
qui rend la conformité AA assertée plutôt qu'espérée.

**Typographie — Atkinson est prescrite, pas subie.** La Règle de portée typographique de `MASTER.md`
(l. 612-644) énumère **trois** zones pour la famille d'affichage — ardoise, légende de la carte, titres
de statut — et pose l. 620 que « partout ailleurs, la famille de texte est seule employée ». Un toponyme
cuit n'appartient à aucune des trois. La contrainte technique **converge** avec la prescription :
`getVariation()` échoue sur woff2 en fontkit 2.0.4, donc seule l'instance par défaut est atteignable — et
celle d'Atkinson **est** Regular/400, à l'intérieur de la plage 400→700 du §5. Big Shoulders est
inutilisable pour la raison exactement symétrique : son instance par défaut est **Thin/100**
(`MASTER.md` l. 574-576, `PROVENANCE.md` l. 50). **Aucune divergence à enregistrer.**

**Aucun `<text>` n'est jamais émis.** Les toponymes sont des **contours de glyphes** posés en `<path>` :
aucune police n'est présente au moment de la rasterisation, donc aucune substitution système n'est
possible. `resvg` reçoit de surcroît **`loadSystemFonts: false`**, ce qui rend cette propriété
*structurelle* et non incidente. La recette balaye `build/**`, commentaires retirés, à la recherche de
`<text`, `font-family`, `getVariation` et `localeCompare` — ce dernier parce qu'il dépend de l'ICU
embarqué dans Node et rendrait **l'empreinte de version dépendante de la machine**.

**Le nom cuit est `tags.name` verbatim.** Ni `name:fr`, ni `int_name`, ni repli, ni abréviation, ni
troncature, ni changement de casse, ni traduction, ni translittération, ni suffixe de désambiguïsation.
Une étiquette dont la boîte sortirait de la toile est **rejetée, jamais rognée** : rogner un nom est une
troncature, donc une invention.

## 8. Ce qui n'est pas tranché

- **`OUVERT` — la mention de la source de l'extrait.** Le §9 du brief impose la phrase et le lien,
  « + mention de la source de l'extrait **le cas échéant** ». La condition n'est pas levée. `phrase` porte
  la chaîne du §9 **seule et verbatim** ; `faits.canal` porte le fait brut, citable sur « La démarche » le
  jour venu ; **aucune formulation supplémentaire n'est rédigée.** À confirmer avant mise en production.
- ~~**`OUVERT` — les toponymes.**~~ **CLOS le 18 août 2026 par #71.** L'arbitrage A-9 du contrat #9 est
  **renversé** : les toponymes sont cuits dans **les deux** artefacts, pyramide z9–z12 et image statique.
  Ses deux motifs sont tombés — le placement d'étiquettes n'est pas « un moteur à lui seul », la
  toile-par-zoom existant déjà, et `fontkit` lit le `woff2` directement. `--c-carte-encre` a désormais
  **un** consommateur. Règle de sélection, réglages et invariants :
  [`docs/contracts/issue-71.md`](../../../../../docs/contracts/issue-71.md).
- **`OUVERT` — la taille d'une étiquette de carte n'est prescrite nulle part.** `MASTER.md` est muet : le
  §5.1 s'arrête à `--fs-100` = 13 px à l'écran, et la plus petite valeur d'impression du §13 est
  **10,5 pt** (= 31,9 px cuits). Le plancher de 8 pt retenu ici est une **convention cartographique**,
  légitime mais non écrite dans le document. C'est un **trou du design system**, à confirmer par
  `lead-design-cms` (couture C-12). Conséquence à dire sans détour : si l'étiquette de carte est alignée
  sur le corps du §13, les toponymes ne sont probablement pas finançables dans l'image statique à
  1600 px, et ils doivent en sortir.
- **`OUVERT` — le plancher d'impression n'a aujourd'hui aucun chemin d'existence.** `print.css`
  l. 98-103 (**gelé**) masque `.bande--carte` **en entier** : l'image statique **ne s'imprime pas**.
  Fonder la taille cuite sur une lisibilité papier que rien ne produit est fonder une décision sur une
  intention. Ce n'est pas rédhibitoire — le §13 est normatif et la couture **C-1** est censée se
  refermer — **mais cela est écrit ici plutôt que supposé**.
