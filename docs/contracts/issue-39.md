# Contrat d'interface — Issue #39 — Ajouter l'entrée de légende manquante pour l'état `non_encore_publie`

> **Portée du document.** L'issue #39 est **interne au thème** : elle ne touche ni l'extension, ni le
> CSS, ni aucun gabarit de partie. Il n'y a donc **pas de frontière front↔back à geler** et aucun
> `leaddev-back-cms` n'a été sollicité. Ce contrat existe pour une seule raison : l'issue **amende une
> convention partagée** (`massifs_partie()`), et deux contrats antérieurs opposent encore l'ancienne
> règle comme un mur. Laisser cet amendement uniquement dans un docblock ferait perdre du temps à la
> prochaine chaîne qui lira `docs/contracts/`.

---

## 1. Ce que l'issue corrige

`templates/parts/carte.php` peint un motif pointillé dédié pour l'état `non_encore_publie`
(`#carte-motif-non-publie`). Avant 17 h, avec le sélecteur de jour sur « demain », c'est le cas
**nominal** de toute consultation. Or la légende de la page ne rendait que deux entrées « Sur ce site »
(`indisponible`, `hors_saison`) : un motif visible que la légende ne savait pas décoder.

- **§12 du brief** — accessibilité AA bloquante : aucune information portée par la seule couleur ou le
  seul motif sans légende accessible correspondante **sur la page**.
- **§8.5 de `design-system/MASTER.md`** — la légende de la page reproduit fidèlement tous les états que
  la carte rend.

Défaut déjà qualifié par l'**arbitrage A-8 du contrat #7** et reporté au §13 comme issue de suivi.
Cette issue **est** ce suivi.

---

## 2. Amendement de convention — le cœur de ce contrat

### 2.1 L'ancienne règle, et pourquoi elle tombe

`functions.php` déclarait la convention d'appel « **figée avec la chaîne #6 : aucun `$args` n'est
passé** ». L'arbitrage **A-0 du contrat #7** refusait explicitement de la changer :

> « Ce qui n'est PAS accordé : modifier `massifs_partie()` pour qu'elle transmette des `$args`. »

**Ce refus était un refus d'empreinte, pas un refus de fond.** La chaîne #7 ne possédait pas
`functions.php`. L'issue #39 le possède, et A-8 avait explicitement reporté le correctif ici.

### 2.2 La nouvelle règle

```php
massifs_partie( string $slug, array $args = array() ): bool
```

`$args` est transmis à `get_template_part( 'templates/parts/' . $slug, null, $args )`.

**Ce qui ne change pas, et reste la règle par défaut :** les parties sont **auto-portantes** et
appellent elles-mêmes l'API publique de l'extension. `$args` ne porte que des paramètres de
**présentation dont la page appelante est propriétaire** — quelles entrées de légende demander, l'ancre,
le niveau de titre. **Jamais de donnée métier.** Le thème n'en fait transiter aucune par là.

### 2.3 Contrats antérieurs rendus périmés par cet amendement

| Document | Emplacement | Affirmation désormais fausse |
|---|---|---|
| `docs/contracts/issue-7.md` | A-0 (§ portée accordée) et A-8 | « modifier `massifs_partie()` […] n'est PAS accordé » — vrai pour la chaîne #7, périmé depuis #39 |
| `docs/contracts/issue-10.md` | l. 166 | « `massifs_partie( 'meteo' )` ne transmet aucun `$args` (convention gelée #6) » |
| `docs/contracts/issue-11.md` | l. 677 | « les deux voies sont fermées : `massifs_partie()` ne transmet **aucun `$args`** — le mur d'A-8 du contrat #7 » |

Ces trois fichiers **n'ont pas été édités** : ils sont hors de l'empreinte de #39, et un contrat gelé se
lit avec sa date. La correction est enregistrée ici et dans le docblock de `massifs_partie()`.

---

## 3. Préconditions vérifiées avant écriture

Trois conditions pouvaient faire échouer le correctif **en silence** — l'entrée ne se serait simplement
pas affichée. Toutes vérifiées vertes avant toute modification.

| # | Condition | Vérification | Résultat |
|---|---|---|---|
| 1 | `legende.php` lit bien `$args` | l. 43 : `$arguments = isset( $args ) && is_array( $args ) ? $args : array();` | ✅ atteignable |
| 2 | L'extension expose la clé | `legende.config.php` l. 219-238 : `non_encore_publie` présent dans `etats_hors_niveau`, motif `pointille` | ✅ garde l. 157-160 franchie |
| 3 | Le CSS existe | `composants.css` l. 136-138 (aplat + `radial-gradient`), l. 208 (casse), l. 864-878 (impression) ; `tokens.css` l. 57-58 | ✅ aucun CSS à écrire |

**Conséquence directe : ni l'extension ni le CSS n'ont été touchés.** `plugins/massifs-core/**` (chaîne
#45) et `assets/css/carte.css` (chaîne #37) sont restés intacts, et aucun `dev-ux-cms` n'a été lancé.

---

## 4. Sémantique de `get_template_part()` — la preuve que les 7 autres appelants ne bougent pas

Lue dans le cœur WordPress du conteneur en fonctionnement, pas inférée :

```php
function get_template_part( $slug, $name = null, $args = array() ) { … }
```

1. **Le défaut de `$args` dans le cœur est déjà `array()`.** Transmettre `array()` est donc
   **strictement identique** à ne rien transmettre. C'est une identité au niveau du cœur, pas un
   raisonnement sur sept sites d'appel.
2. `$name = (string) $name;` puis `if ( '' !== $name )` — `null` en second argument n'ajoute **aucun**
   candidat `{slug}-{name}.php`. Le `null` est requis et correct.
3. **Contrat de retour :** `return false` **uniquement** si `locate_template()` échoue ; sinon la
   fonction se termine sans `return` et vaut `null`.
4. `load_template()` ne fait **pas** d'`extract()` sur `$args` (son seul `extract()` porte sur
   `$wp_query->query_vars`, avec `EXTR_SKIP`). `$args` atteint la partie comme variable de portée, et y
   est **déjà** défini à `array()` aujourd'hui.

### 4.1 Interdit permanent — `false !== $charge`

```php
$charge = get_template_part( 'templates/parts/' . $slug, null, $args );

if ( false !== $charge ) {
	return true;
}
```

**Ce test ne doit jamais être « simplifié »** en `if ( $charge )`, `if ( ! empty( $charge ) )` ou tout
autre test de véracité. Par le point 3 ci-dessus, la valeur de **succès** est `null`, qui est *falsy* :
un test de véracité inverserait silencieusement le contrat de retour d'une fonction que **huit** sites
d'appel utilisent. C'est l'édition la plus dangereuse possible dans ce fichier.

---

## 5. Sites d'appel de `massifs_partie()`

**Huit**, tous dans `front-page.php`. L'audit antérieur qui en annonçait six était périmé ; les deux
occurrences de `carte.php` sont des **commentaires**.

| Ligne | Slug | `$args` |
|---|---|---|
| 492 | `bandeau-peremption` | — |
| 496 | `bandeau-non-officialite` | — |
| 509 | `carte` | — |
| 509 | `carte-secours` | — |
| ~530 | `legende` | **`etats_sur_ce_site`** ← seul appel modifié |
| 531 | `liste-statuts` | — |
| 549 | `meteo` | — |
| 553 | `panneau-feu` | — |

`carte.php` l. 18 documente « `massifs_partie( 'carte' )`, sans `$args` » : **toujours vrai**, cet appel
n'a pas changé. Aucune raison de toucher ce fichier (empreinte de la chaîne #37).

---

## 6. Chaîne fournie — aucune invention

L'unique chaîne rendue est produite par `legende.php` l. 174, **non par ce correctif** :

> « Les statuts de demain ne sont pas encore publiés. La préfecture publie vers 17 h. »

Elle reproduit **verbatim** `design-system/MASTER.md` §11.3 l. 1893. Aucun libellé, aucune couleur,
aucune consigne n'a été rédigée par cette chaîne. Aucune question de domaine n'est restée ouverte.

---

## 7. Interdits

- Le thème n'appelle jamais une source externe ni une fonction d'ingestion. **Non concerné** : aucune
  origine tierce n'est ajoutée, aucune requête réseau n'est introduite.
- Le thème ne calcule jamais une règle métier. **Tenu** : les états, leurs libellés, leurs motifs et
  leurs jetons viennent tous de l'extension ; la page ne fait que **choisir lesquels afficher**.
- L'extension n'émet jamais de HTML de présentation publique. **Non concerné** : l'extension n'est pas
  touchée.
- `$args` ne transporte jamais de donnée métier — seulement des paramètres de présentation.

---

## 8. Arbitrages

**A-1 · Amender une convention gelée, ou contourner ?**
La convention « aucun `$args` » était figée par la chaîne #6 et défendue par A-0 du contrat #7.
**Décision : amender.** Le refus d'A-0 était motivé par l'empreinte de la chaîne #7, pas par le fond, et
A-8 a explicitement reporté le correctif à cette issue. Aucune autre voie n'existait dans l'empreinte :
`legende.php` est interdit par l'issue elle-même, et il n'existe aucun filtre sur `massifs_legende()`
(constat du contrat #11 l. 677). L'amendement est enregistré explicitement — jamais silencieusement —
dans le docblock **et** ici.

**A-2 · `etats_sur_ce_site` est-il de la présentation ou de la donnée métier ?**
Enjeu réel : la frontière thème/extension est stricte. **Décision : présentation.** Arbitré au niveau
lot et rendu contraignant.

Le thème ne **compose** aucune donnée de domaine — il **sélectionne** lesquels des états fournis par
l'extension afficher. Les états, leurs libellés, leurs motifs, leurs jetons CSS et leurs phrases
naissent tous côté extension (`legende.config.php`), et `legende.php` l. 157-160 **écarte déjà** tout
état demandé que l'extension n'expose pas : l'extension reste l'autorité sur ce qui existe, et le thème
ne peut pas faire apparaître un état par la seule volonté de son site d'appel.

**Le point décisif est ailleurs : ce correctif ne crée pas la condition de frontière, il en déplace une
qui existait déjà.** La liste `array( 'indisponible', 'hors_saison' )` était **déjà codée en dur dans le
thème**, enfouie dans le défaut interne de `legende.php` l. 82-84. La porter au site d'appel dans
`front-page.php` rend la même liste codée en dur **plus visible, pas moins**. Rien ne traverse la
frontière qui ne la traversait pas avant ce commit.

**Résidu nommé plutôt que dissimulé** : le thème code bien en dur des identifiants d'états de domaine
sous forme de chaînes. Si cela devait être jugé une violation de frontière, **elle est préexistante et
largement antérieure à #39**. Elle n'est ni corrigée ni aggravée ici. Consignée comme condition
préexistante observée au passage ; l'arbitrage d'une éventuelle issue dédiée revient au niveau lot.
`refacto-cms` n'est **pas** autorisé à la « résoudre » : ce serait un changement d'architecture non
cadré embarqué dans un correctif de cinq lignes.

**A-3 · Corriger les commentaires devenus faux, ou les laisser ?**
Deux blocs de `front-page.php` affirmaient « aucun `$args` n'est passé ». **Décision : corriger les
deux**, y compris celui de `meteo`/`panneau-feu` (l. ~546) qui ne concerne pas l'appel modifié : sa
formulation attribuait l'absence d'`$args` à une **incapacité** de `massifs_partie()`, ce qui est
désormais faux. La raison honnête est que les valeurs par défaut de ces deux parties suffisent.

**A-4 · Preuve du non-effet sur les sept autres appelants.**
**Décision : preuve mécanique, pas inspection.** La page servie est **stable à l'octet** (deux captures
identiques, 46 256 octets, md5 `78d560b1331d9754fe58d24f63bc9b13`). Un `diff` avant/après de la page
servie a donc été posé en **condition de livraison** : la seule différence admise était le `<li>`
ajouté. Résultat : exactement six lignes ajoutées, aucun autre delta.

---

## 9. Dette signalée, hors empreinte — non corrigée

**`tests/rendu/recette-rendu.mjs` l. 1786-1790 code le défaut comme prémisse.** Le commentaire affirme
que `pastille--non-publie` « n'est JAMAIS atteignable depuis l'accueil servi », et les assertions qui
suivent (l. 1808-1811, puis l. 1818-1829 pour la casse §14.3) mesurent un `<span>` que le test
**crée et retire lui-même**. Cette prémisse devient **fausse** avec #39.

La recette continue de passer — elle mesure sa propre sonde —, mais **un test qui passe pour une raison
devenue fausse est exactement ce qu'une suite verte dissimule**. Le fichier est doublement hors
empreinte (il appartient au niveau lot, et il est modifié-non-commité depuis un lot antérieur) : **rien
n'y a été touché.** Remonté au niveau lot pour `test-integration-cms`.

Amélioration possible : viser la vraie entrée de légende plutôt qu'une sonde synthétique. Ce serait
**strictement plus fort** — une sonde ne peut pas détecter que le gabarit a cessé d'émettre la marque
avant le libellé, alors que `composants.css` l. 208 est un sélecteur adjacent qui en dépend.

---

## 10. Élément à charge pour la revue

`composants.css` l. 207 cite déjà **`legende.php l. 241-244`** comme site vivant de la règle de casse à
sélecteur adjacent — c'est-à-dire la boucle « Sur ce site » de la légende. **La feuille de style avait
été écrite pour une entrée que le site d'appel ne demandait pas.** Cette chaîne **achève un design
prévu ; elle n'en invente pas un.**
