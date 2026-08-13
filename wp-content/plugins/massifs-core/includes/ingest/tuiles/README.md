# Module `ingest/tuiles` — fond de carte auto-hébergé

Contrat de référence : [`docs/contracts/issue-9.md`](../../../../../docs/contracts/issue-9.md),
**avenant du 14 août 2026 compris (§13)**.

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
- **`bbox` est l'emprise de la pyramide**, alignée sur la grille de tuiles, donc un sur-ensemble strict de
  `massifs_emprise()['bbox']`. Elle borne la couche ; elle ne cadre pas la vue initiale.

Il n'y a **délibérément pas de clé `url`** sur l'image statique : l'artefact vit dans le thème, qui
résout son propre chemin d'asset. L'extension publie des **faits**, jamais un chemin de thème
(arbitrage A-3).

## 2. Ce que le module ne fait pas

Aucun hook, aucun filtre, aucune table, aucune option, aucun transient, aucun cron, aucune route REST,
aucun écran, aucun rôle, aucune capability, aucune sortie. Le charger ne fait rien d'observable ; ne pas
le charger n'est pas fatal.

**PHP n'ouvre jamais une tuile ni l'image statique** : ni `file_get_contents`, ni `getimagesize`, ni
`filesize`, ni `hash_file`, ni `file_exists`. `disponible` atteste la présence des **métadonnées**, jamais
celle des octets. Une tuile manquante se dégrade en trou visuel, jamais en erreur PHP — et 280 amorçages
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
département, simplifie, et écrit `build/source/osm-13.json` (~4 Mo) avec son manifeste — **les deux sont
commités**, et c'est ce qui rend `construire` reproductible hors ligne (§11 du brief, arbitrage A-8).

Une charge Overpass tronquée par timeout rend un JSON **syntaxiquement valide mais amputé** : aucun
`JSON.parse` ne l'attrape. `recuperer` la rattrape par des **dénombrements** par couche, un contrôle de
recouvrement de la bbox départementale, et des bornes de taille d'archive. Sortie ≠ 0, et **l'archive en
place n'est jamais écrasée par un échec**.

`construire` **relit les six `--c-carte-*` et `--c-charbon` dans `themes/massifs/assets/css/tokens.css`**
et **sort en code ≠ 0** si l'un est absent, renommé ou divergent, en nommant le jeton, la valeur lue et la
valeur attendue (invariant I-9.7). C'est ce qui rend D-01 opposable et empêche un `filter: grayscale()` de
revenir par la fenêtre : **le monochrome est cuit à la génération**.

Aucune coordonnée, aucun compte de tuiles n'est codé en dur nulle part : la grille se recalcule depuis
`massifs_emprise()['bbox']`, lue dans `data/massifs-13.php`.

**Émission atomique** : tout est écrit en bloc, après tous les contrôles, et la version précédente n'est
supprimée qu'après succès complet. Un build à moitié appliqué laisserait des tuiles neuves et des
métadonnées anciennes, donc **une URL qui ment**.

**La version est dans le chemin**, dérivée du contenu (8 premiers hexadécimaux du sha256 du manifeste),
jamais en query : c'est ce qui mérite `immutable`.

### Mode dégradé

Sans archive OSM lisible, `npm run construire` :

- **produit quand même l'image statique**, depuis `data/massifs-13.geometrie.json` seule, que nous
  possédons hors ligne — c'est la ligne de DoD §5.5 qui ne dépend d'aucun accès réseau (invariant I-9.9) ;
- **n'émet aucune pyramide** : 280 aplats uniformes seraient une carte qui affirme quelque chose de faux
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

## 7. Réglages mesurés, non choisis

| Réglage | Valeur | Pourquoi cette valeur |
|---|---|---|
| Paliers d'anticrénelage | **0**, soit 7 couleurs | Mesuré : 3 paliers → 287 450 o, 1 palier → 239 476 o, 0 palier → 177 824 o, pour un plafond de 153 600 o. Mitigation (1) du §2. |
| Couches de l'image statique | **terre + eau** | Mitigation (2) du §2, appliquée après (1) qui ne suffisait pas. Retrait des routes (164 709 o) puis de la végétation (142 464 o). L'ordre suit le §4.2 de `MASTER.md` (`--c-carte-trait` n'est « jamais porteur d'une limite qui compte ») et l'arbitrage A-9, qui nomme lui-même ce qui porte l'orientation : « la forme du littoral, l'Étang de Berre et les 25 contours ». **`terre` et `eau` viennent toutes deux d'OSM** : l'attribution posée sous l'image reste vraie. |
| Largeur de l'image statique | **1600 px** | Contrat §2. La mitigation (3), 1280 px, **n'a pas été appliquée** : la (2) a suffi. |
| Épaisseur des contours | **2 px** | Sans anticrénelage, une largeur entière rend un trait d'épaisseur constante ; 1,5 alternerait entre 1 et 2 px le long du tracé, ce qui se lirait comme une différence entre massifs. |
| Voirie extraite | `motorway`, `trunk`, `primary` | `secondary` ajoute 12 000 voies et triple l'encre sans rien apporter à l'orientation à z ≤ 12. |

## 8. Ce qui n'est pas tranché

- **`OUVERT` — la mention de la source de l'extrait.** Le §9 du brief impose la phrase et le lien,
  « + mention de la source de l'extrait **le cas échéant** ». La condition n'est pas levée. `phrase` porte
  la chaîne du §9 **seule et verbatim** ; `faits.canal` porte le fait brut, citable sur « La démarche » le
  jour venu ; **aucune formulation supplémentaire n'est rédigée.** À confirmer avant mise en production.
- **`OUVERT` — les toponymes.** Aucun nom de lieu n'est cuit, ni dans la pyramide, ni dans l'image
  statique. `--c-carte-encre` n'a donc aucun consommateur en v1 : absence délibérée, pas défaut. Une v2 les
  ajouterait par un simple changement de `version`, absorbé par la version-dans-le-chemin.
