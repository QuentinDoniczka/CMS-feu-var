# Bouchons du connecteur météo

Ce répertoire sert les charges simulées de l'indicateur de danger météo des
forêts, **par notre propre serveur**, pour que le connecteur y fasse un **vrai
aller-retour HTTP** au lieu d'un court-circuit en mémoire.

> `docs/decisions/portee-non-publiee.md` : la source est fausse, **tout le reste
> est réel**. La frontière d'ingestion ne change pas, et basculer vers la vraie
> source doit rester **un changement de connecteur, pas une réécriture**.

---

## Pourquoi ici, et pas ailleurs

Deux autres emplacements ont été envisagés puis écartés, sur vérification :

- **sous `includes/`** — le garde-fou du serveur web refuse ce sous-arbre en
  **403 à n'importe quelle profondeur**, quelle que soit l'extension du fichier.
  Un bouchon déposé là ne pourrait jamais traverser un aller-retour HTTP,
  c'est-à-dire exactement ce que l'approche exige ;
- **dans le service de tuiles de la stack** — son serveur répond 404 hors de son
  propre préfixe, et ce répertoire de bouchons est par ailleurs déclaré sans
  emploi et à retirer.

`plugins/massifs-core/data/` est, lui, **délibérément servi** et nommément
réservé aux caches météo, EFFIS et tuiles. C'est le seul emplacement qui
satisfait la contrainte.

**Même origine que la page**, donc aucune requête vers un domaine tiers : la
contrainte n° 2 du projet tient, bouchon compris.

---

## Rien de daté n'est commité

`.gitignore` ignore `*.json`. Un `20260814.json` serait périmé le lendemain — la
couche temporelle du validateur ne récupère qu'aujourd'hui et demain — et un
bouchon versionné finirait par être lu comme une source, c'est-à-dire comme un
fait. Les bouchons se fabriquent à la demande et se suppriment après usage.

---

## Format — le nôtre, déclaré et versionné

Ce format **n'imite pas** celui de l'API réelle, qui est **inconnu**. Il est
déclaré comme nôtre par sa clé `schema`, et il porte son numéro de version.

```json
{
  "schema": 1,
  "zone": "13",
  "jour": "2026-08-14",
  "publie_le": "2026-08-13T16:00:00+00:00",
  "niveau_source": 2
}
```

| Clé | Type | Contrôlée par |
|---|---|---|
| `schema` | entier, `1` | couche `forme` — un autre numéro est rejeté, jamais deviné |
| `zone` | chaîne | couche `référentiel` — doit être la zone configurée |
| `jour` | `YYYY-MM-DD` | couche `temporel` — doit être **le jour demandé**, sinon rejet |
| `niveau_source` | **entier** | couche `forme` (type strict) puis `sémantique` (nature) |
| `publie_le` | chaîne d'instant, facultative | couche `temporel` |

Deux pièges à connaître :

1. `niveau_source` doit être un **entier JSON**, pas une chaîne numérique ni un
   flottant : un changement de type est un signal, donc un rejet, jamais une
   conversion silencieuse ;
2. `jour` doit correspondre au jour **demandé**. Une charge datée d'un autre
   jour est rejetée et **non recalée** — c'est ce recalage-là qui ferait glisser
   une donnée d'un jour sur l'autre.

Le nom du fichier porte la date au format `Ymd` : `{date}.json`.

---

## Aucun bouchon n'ouvre l'affichage

Un bouchon **ne peut pas** faire apparaître un niveau de danger sur le site,
même en portant un libellé et une cardinalité. La garde de vocabulaire vit dans
notre code (`includes/ingest/meteo/vocabulaire.config.php`), et les libellés
officiels des crans ne sont **sourcés nulle part**. Une charge parfaitement
valide est mise en cache, exerce la fraîcheur et les alertes, et reste rendue
`indisponible`.

C'est délibéré, et c'est le cas nominal de recette : **un libellé officiel ne se
déduit pas d'un bouchon.**

---

## Mode d'emploi

1. Déposer un fichier `{date}.json` ici, au format ci-dessus, la date en `Ymd`.
2. Dans le `wp-config.php` de la stack, pointer le connecteur sur ce répertoire :

```php
define(
    'MASSIFS_METEO_JSON_URL_TEMPLATE',
    '<origine de la stack>/wp-content/plugins/massifs-core/data/meteo/bouchons/{date}.json'
);
```

Définir cette constante est ce qui **arme** le connecteur : sans elle, il est
désarmé dans tous les environnements, production comprise, et ne peut
structurellement pas émettre un octet.

Pour couper totalement, quelle que soit la configuration :

```php
define( 'MASSIFS_METEO_DISABLE', true );
```

Un fichier **absent** produit un 404, c'est-à-dire exactement le scénario « pas
encore publié » : aucun échec compté, aucune alerte.

### Un piège de la stack de développement, mesuré et non supposé

Depuis l'intérieur du réseau de la stack, le site s'atteint par un nom d'hôte de
service, alors que son URL canonique porte l'hôte **et le port** de la machine de
développement. Un fichier **absent** sous `wp-content/` n'est alors pas servi en
404 par le serveur web : la réécriture de permaliens le confie à WordPress, qui
répond un **301 canonique** vers son propre hôte:port — injoignable de
l'intérieur. Le connecteur verrait une panne réseau là où la source dit « pas
encore publié », c'est-à-dire l'inverse de ce qu'il faut observer.

Le correctif est d'une ligne, et il passe par le filtre public prévu pour les
en-têtes sortants (`massifs_meteo_http_args`) : présenter l'en-tête `Host`
canonique du site. L'aller-retour reste entièrement réel — même serveur, même
tuyau, même code de statut. C'est ce que fait le scénario de recette
`31-meteo-bouchon-http-reel`, et il **constate le 404 sur le fil** avant d'en
tirer la moindre conclusion.

Ce piège appartient à la stack, pas au connecteur : contre une source réelle
servant sa propre origine, il n'existe pas.
