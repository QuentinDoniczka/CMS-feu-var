# Module `sauvegardes`

Moteur de sauvegarde et de restauration de l'extension MASSIFS. **Il ne parle qu'à WP-CLI.**
Aucune route REST, aucun écran d'administration, aucun bouton, aucune page de réglages — c'est une
décision, pas une omission (arbitrage A-11 du contrat `docs/contracts/issue-16.md`). Une restauration
à un clic depuis `wp-admin` est une arme braquée sur le pied du site, et un cadeau à un compte compromis.

---

## 1. Le compromis sur l'emplacement des archives — daté, et à lever en production

> **Ceci est un compromis, pas une bonne pratique. Ne le recopiez pas ailleurs.**

Le répertoire d'archives par défaut est
`wp-content/plugins/massifs-core/includes/security/sauvegardes/archives/`.

Cet emplacement a deux défauts, connus, assumés, et écrits ici pour qu'ils ne soient pas redécouverts :

1. **Une archive contient `wp_users` et `wp_usermeta`** — donc des **hachages de mots de passe** et des
   **secrets TOTP** (arbitrage A-5). Elle est incluse parce qu'une restauration sans comptes n'est pas
   une restauration (§6 du brief). Le contenu est légitime ; **son emplacement sous la racine web ne
   l'est pas.**
2. **Le répertoire vit dans un arbre qu'un redéploiement écrase.** Une mise à jour de l'extension par
   copie de répertoire peut emporter les archives.

Quatre protections sont posées, et elles se cumulent :

| # | Protection | Statut mesuré |
|---|---|---|
| 1 | `<DirectoryMatch>` de `docker/wordpress/plugins-guard.conf`, qui refuse `**/includes/**` à toute profondeur | **en place** ; c'est la protection effective aujourd'hui |
| 2 | `archives/.htaccess` — `Require all denied` | **non mesuré** : `AllowOverride` n'a pas été vérifié sur l'image `wordpress:php8.3-apache` |
| 3 | `archives/index.php` — `<?php // Silence.` | efficace seulement contre l'affichage d'index de répertoire |
| 4 | `archives/.gitignore` — rien n'entre dans git sauf les fichiers de protection | **en place** |

À quoi s'ajoute le **nom d'archive non devinable** : suffixe aléatoire de 8 hexadécimaux, précisément
pour qu'on ne puisse pas énumérer les 365 noms d'une année.

**LA PREMIÈRE CHOSE À FAIRE EN PRODUCTION** est de sortir ce répertoire de la racine web, sans toucher
une ligne de code, via le filtre prévu pour cela :

```php
// mu-plugin, ou wp-config.php via un fichier chargé tôt
add_filter(
    'massifs_sauvegardes_repertoire',
    static function (): string {
        return '/home/compte/sauvegardes-massifs'; // hors de public_html/
    }
);
```

Le même filtre rend possible le déménagement du moteur vers `docker/` sans réécriture (couture S-7).
En ponctuel, `wp massifs sauvegarde creer --repertoire=/backups --porcelain` fait la même chose pour
une exécution.

---

## 2. Procédure de restauration

> **Une restauration écrase la base.** Le filet automatique est votre seule marche arrière : ne le
> désactivez pas avec `--sans-filet` sans savoir pourquoi.

### 2.1. Avant de toucher à quoi que ce soit

```bash
wp massifs sauvegarde lister
```

Lisez la colonne `complet`. **Une archive `complet = non` ne doit pas être restaurée** sans avoir
d'abord regardé ce qui lui manque :

```bash
wp massifs sauvegarde inspecter massifs-sauvegarde-20260816-031500-a1b2c3d4.zip
```

Dans le manifeste, regardez en particulier :

- `tables[*].lignes_attendues` contre `tables[*].lignes_emises` — une divergence est ce qui a mis
  `complet` à `false` ;
- `tables[*].pagination` — la valeur `offset` signale une table dumpée **sans clé de pagination
  utilisable**, donc exposée au saut de lignes sous écriture concurrente ;
- `tables[*].exclusions` et `lignes_exclues_ignorees` — la liste **nommée** de ce qui n'est pas dans
  l'archive ;
- `fichiers.ignores` — liens symboliques refusés, fichiers trop volumineux, fichiers illisibles.

### 2.2. Restaurer

```bash
wp massifs sauvegarde restaurer massifs-sauvegarde-20260816-031500-a1b2c3d4.zip
```

La commande imprime **toujours**, avant tout geste : l'environnement, `DB_NAME`, `DB_HOST` et
`site_url()`. **Lisez ces quatre lignes.** C'est la seule protection réelle contre « je croyais être
sur la préproduction ».

Hors `local` et `development`, la commande **refuse** (code de retour 3) tant que vous ne passez pas
les deux options ensemble :

```bash
wp massifs sauvegarde restaurer <archive> \
    --je-sais-ce-que-je-fais \
    --nom-base=le_nom_exact_de_la_base \
    --oui
```

Le nom de base est comparé **strictement** à `DB_NAME`. Le geste de le taper à la main est le contrôle,
pas la comparaison.

Sans terminal interactif et sans `--oui`, la commande **refuse** (code 3) : elle ne fait jamais
l'hypothèse qu'un `EOF` vaut un accord.

### 2.3. Ce qui se passe, dans l'ordre

1. Gardes : WP-CLI, environnement, `--nom-base`, confirmation.
2. Lecture du manifeste ; refus si `complet:false` sans `--forcer`.
3. **Création d'une archive « avant restauration »** (genre `filet`), sauf `--sans-filet`. Si le filet
   échoue, **la restauration est abandonnée** : on ne détruit pas une base sans marche arrière.
4. Extraction de `base.sql` dans un répertoire temporaire, puis rejeu instruction par instruction.
   Toute erreur SQL **arrête** le rejeu.
5. Extraction et réécriture des fichiers dans les racines de **la configuration courante** — l'archive
   ne porte aucun chemin absolu, c'est ce qui la rend restaurable sur un autre hôte.
6. Vidage des caches.

### 2.4. Revenir en arrière

L'archive de filet porte `filet` dans son nom. Elle n'apparaît pas dans `lister` par défaut :

```bash
wp massifs sauvegarde lister --garder-filets
wp massifs sauvegarde restaurer massifs-filet-20260816-031500-a1b2c3d4.zip --oui
```

### 2.5. Codes de retour

| Sous-commande | 0 | 1 | 2 | 3 |
|---|---|---|---|---|
| `creer` | archive complète | `complet:false` | échec | — |
| `lister` | ok | — | échec | — |
| `inspecter` | ok | manifeste illisible | échec | — |
| `restaurer` | ok | archive incomplète | échec | refusée par une garde |
| `verifier` | aller-retour fidèle | **aller-retour infidèle** | échec | refusée par une garde |
| `purger` | ok | — | échec | — |

---

## 3. Vérifier que le moteur ne ment pas

```bash
wp massifs sauvegarde verifier
```

> **Cette commande écrase la base cible.** Elle crée une table de fixtures, prend une archive, **altère
> la base**, restaure, compare, puis re-dumpe et compare encore. Elle est soumise aux mêmes gardes que
> `restaurer` : `local`/`development`, ou `--je-sais-ce-que-je-fais` avec `--nom-base`.

Elle imprime, à chaque exécution :

- les **trois** normalisations de la projection de comparaison — et il n'y en a jamais une quatrième ;
- la **liste nommée** des lignes exclues du dump, donc exclues de la comparaison ;
- chaque assertion en `OK` / `ÉCHEC`, dont, **nommée séparément**, « `NULL` est resté `NULL` et n'est
  pas devenu la chaîne vide » ;
- les deux empreintes, et, en cas de divergence, **le nom des tables qui divergent**.

Un `ÉCHEC` ici ne veut pas dire « la commande a raté ». Il veut dire **les archives déjà produites par
ce moteur sont fausses**. C'est le seul message du module qui crie.

---

## 4. Ce que la sauvegarde ne couvre PAS

Le périmètre est **base-centré**. Ce qui est irremplaçable est sauvegardé ; ce que git ou une image
Docker reproduisent ne l'est pas. Sauvegarder trente fois ce que git tient déjà n'est pas de la
prudence, c'est du bruit.

**Inclus** : toutes les tables du préfixe (dont `wp_users` et `wp_usermeta`), `wp-content/uploads/`,
`wp-content/plugins/massifs-core/data/`.

**Non couvert, et reproductible :**

| Non sauvegardé | Comment le retrouver |
|---|---|
| Cœur WordPress | image Docker / réinstallation |
| Thème `massifs` | git |
| Extension `massifs-core` | git |
| Pyramide de tuiles `data/tuiles/**` | **commitée dans git** et régénérable par le build — double raison |
| `uploads/massifs-tiles/**` | régénérable |
| Caches, `**/*.tmp` | par nature jetables |
| Le répertoire d'archives lui-même | exclusion **en dur, non filtrable** — sans elle, chaque sauvegarde contiendrait les précédentes et doublerait de taille |

**Transients (`_transient_%`, `_site_transient_%`) : exclus, et ce n'est pas de la propreté.** Restaurer
un transient vieux de trois semaines réinjecterait un état périmé sous les règles de fraîcheur du §4.5 —
exactement le « statut périmé présenté comme courant » que le brief interdit en règle absolue.
Corollaire à connaître : **une ligne exclue ne revient pas** à la restauration, puisque celle-ci
commence par `DROP TABLE`.

---

## 5. Ce qui n'est pas tenu, et ne prétend pas l'être

| Promesse | État réel |
|---|---|
| **Sauvegardes quotidiennes** | **NON TENUE par ce module.** `DISABLE_WP_CRON` vaut `true` sur les deux services : aucune planification interne ne s'exécute. L'évènement `massifs_sauvegarde_quotidienne` est **branché mais jamais planifié** par défaut. La périodicité exige un **déclencheur hôte** — cron système ou tâche d'hébergeur — appelant `wp massifs sauvegarde creer` (couture S-8). |
| **Copie hors hébergeur** | **EN SOMMEIL** par décision du propriétaire (`portee-non-publiee.md` §1.2, issue #40). Non tenue par cette issue, et ce n'est pas un manquement. Une archive qui ne quitte jamais la machine ne protège pas contre la perte de la machine : **c'est le trou principal du dispositif actuel**, et il est délibéré. |
| **Restauration testée** | **Outillée, pas exécutée par cette chaîne.** `verifier` existe et son vert est reproductible ; c'est `test-integration-cms`, au niveau du lot, qui l'exécute. |
| **Multisite** | **Hors périmètre.** Le moteur filtre les tables sur `$wpdb->prefix`, ce qui, en multisite, ne prend que le site principal et ignore `wp_2_*`, `wp_3_*`… `wp_blogs`, `wp_site` et `wp_sitemeta` portent le préfixe de base et seraient dumpées, ce qui donnerait une archive **cohérente en apparence et amputée**. Ne pas utiliser ce module sur un multisite. |

---

## 6. Rotation

**Par nombre : 30 archives, défaut opposable** (arbitrage A-10). Sans cron quotidien, « 30 jours » et
« 30 archives » divergent, et seul le compte a un sens observable ici. La rétention par âge existe,
filtrable, **désactivée** par défaut. Les filets ont leur propre quota (5).

La rotation ne s'exécute **qu'après** un `rename()` réussi : purger avant reviendrait à détruire une
archive valide pour faire place à une archive qui n'existe pas encore.

```bash
wp massifs sauvegarde purger --simuler
wp massifs sauvegarde purger --garder=10
```

---

## 7. Les quatorze filtres

| Filtre | Défaut |
|---|---|
| `massifs_sauvegardes_repertoire` | `…/includes/security/sauvegardes/archives` — **couture de sortie n° 1** |
| `massifs_sauvegardes_retention_nombre` | `30` |
| `massifs_sauvegardes_retention_jours` | `0` (désactivée) |
| `massifs_sauvegardes_retention_filets` | `5` |
| `massifs_sauvegardes_tables_exclues` | `array()` |
| `massifs_sauvegardes_lignes_exclues` | `array( 'options' => array( '_transient_%', '_site_transient_%' ) )` |
| `massifs_sauvegardes_racines_fichiers` | `uploads` et `data` |
| `massifs_sauvegardes_exclusions_fichiers` | `data/tuiles/*`, `uploads/massifs-tiles/*`, `*/cache/*`, `*.tmp` |
| `massifs_sauvegardes_taille_max_fichier` | `67108864` |
| `massifs_sauvegardes_lignes_par_page` | `500` |
| `massifs_sauvegardes_octets_par_insert` | `1048576` |
| `massifs_sauvegardes_planification_active` | `false` |
| `massifs_sauvegardes_heure_planifiee` | `'03:15'` |
| `massifs_sauvegardes_destinataire_alerte` | `get_option( 'admin_email' )` |

Tous sont résolus **en un seul endroit**, `Reglages.php`. Un filtre au type inattendu retombe sur son
défaut sans faire échouer la sauvegarde : refuser de sauvegarder parce qu'un mu-plugin est mal écrit,
c'est choisir « pas de sauvegarde » plutôt que « sauvegarde avec les défauts ».

**`massifs_sauvegardes_lignes_exclues` distingue le vide voulu du vide accidentel**, et la distinction
compte : elle porte la garantie n° 11 du contrat.

| Ce que rend le filtre | Ce qui est retenu | Pourquoi |
|---|---|---|
| `array()` **explicitement vide** | `array()` — **aucune exclusion** | Intention claire d'un exploitant. C'est son seul levier pour tout désactiver en connaissance de cause. |
| Tableau **non vide** dont plus rien ne survit à la normalisation | **le défaut** | Filtre malformé, intention inconnue. Le prendre pour « aucune exclusion » réintégrerait `_transient_%` et `_site_transient_%` au dump, **en silence** — donc réinjecterait un état périmé sous les règles de fraîcheur du §4.5 à la restauration. |
| Autre chose qu'un tableau | **le défaut** | Idem. |

`massifs_sauvegardes_racines_fichiers` et `massifs_sauvegardes_exclusions_fichiers` retombent, eux, sur
leur défaut **dans les deux cas de vide** : un périmètre fichiers vidé par accident ne doit pas passer
pour un périmètre vidé exprès. Qui veut une archive sans fichiers dispose de `--sans-fichiers`, qui le
dit explicitement et l'inscrit au manifeste.

**Actions émises** : `massifs_sauvegarde_terminee( array $rapport )`,
`massifs_sauvegarde_echouee( WP_Error $erreur )`, `massifs_sauvegarde_evenement( array $entree )`, plus
le crochet cron `massifs_sauvegarde_quotidienne`.

**Fonctions de lecture** (totales, jamais `null`, jamais `WP_Error`) :
`massifs_sauvegardes_derniere()`, `massifs_sauvegardes_lister()`, `massifs_sauvegardes_repertoire()`.

---

## 8. Domicile de ce document

Provisoire. Le domicile durable des procédures d'exploitation est `docs/` (couture S-5), pris par une
chaîne sœur. Ce README y sera déplacé, pas réécrit.
