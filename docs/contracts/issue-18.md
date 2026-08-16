# Contrat d'interface — Issue #18 — Pages de contenu obligatoires, accessibilité et performance

**Domaines** `a11y` · `perf` · `contenu` · **Épic 6** (jalon #6)
**Gelé le** 16 août 2026 · **Auteur** `lead-issue-cms` de l'issue #18
**Statut** : opposable à toute chaîne aval de cette issue.

> Ce contrat n'a **pas** deux moitiés à réconcilier : l'issue #18 ne touche que le thème et `docs/`.
> `leaddev-back-cms` n'a donc pas été lancé — aucune chaîne aval n'a le droit d'écrire dans
> l'extension, un plan serveur n'aurait rien produit de légitime. Ce que ce document gèle, ce sont les
> **arbitrages** : la frontière du §11.3, la place de la prose, les fonctions de lecture consommées, et
> les interdits. Voir §7.

---

## 0. Empreinte fichiers — la seule protection de cette chaîne

Projet **mono-branche**, arbre de travail partagé, aucune isolation. La chaîne sœur de l'issue **#16**
écrit au même moment dans `wp-content/plugins/massifs-core/includes/security/**`. Un fichier hors
empreinte appartient à une autre chaîne ; l'écraser est irrécupérable.

**Écriture autorisée — rien d'autre, jamais :**

| Fichier | Rôle |
|---|---|
| `wp-content/themes/massifs/templates/page-la-demarche.php` | Gabarit — structure et attributions |
| `wp-content/themes/massifs/templates/page-accessibilite.php` | Gabarit — structure et moyen de signalement |
| `wp-content/themes/massifs/templates/page-mentions-legales.php` | Gabarit — structure, identité, table des sources |
| `wp-content/themes/massifs/includes/seo-meta.php` | Métadonnées de `<head>` |
| `docs/recette/**` | Preuves de recette, copie rédactionnelle, procédure d'import, livrables §11 |
| `docs/contracts/issue-18.md` | Ce document |

**Interdits d'écriture, à contourner par la conception, jamais par l'écriture :**
`functions.php` · `assets/css/**` · `templates/header.php` · `templates/footer.php` · `page.php` ·
toute l'extension · `tests/**` · `docker/**` · `docs/decisions/**` · les autres `docs/contracts/*`.

---

## 1. Fonctions de lecture exposées par l'extension — consommées par les gabarits

Aucune n'est créée ni modifiée par cette issue. Toutes sont appelées **sous garde
`function_exists()`** : les trois pages doivent rendre un HTML valide même extension désactivée, comme
le fait déjà `page.php`.

| Fonction | Retour consommé, clé par clé | Consommateur |
|---|---|---|
| `massifs_attribution()` | `phrase:string` · `phrase_courte:string` · `lien_source:string` · `lien_licence:string` · `faits:array<string,string>` (dont `producteur`, `jeu_de_donnees`, `donnees_du_libelle`, `licence_nom`, `licence_version`, `licence_identifiant`, `base_reglementaire`) | Démarche (`faits`), Mentions légales (`phrase`, `lien_licence`) |
| `massifs_attribution_statuts()` | `texte:string` | Mentions légales |
| `massifs_attribution_fond_de_carte()` | `phrase:string` · `lien_licence:string` · `faits:array<string,string>` | Démarche (`faits`), Mentions légales |
| `massifs_attribution_zones_parcourues_par_le_feu()` | `phrase:string` · `faits:array<string,string>` (dont `producteur`, `service`, `methode`, `fenetre_jours`, `surface_minimale_ha`, `frequence_par_jour`, `connecteur`) | Démarche (`faits`), Mentions légales |
| `massifs_meteo_du_jour()` | **uniquement** `attribution:array{texte:string,lien_licence:string,lien_source:string}` | Mentions légales, Démarche |

**Valeurs relevées sur la stack le 16 août 2026** — pour vérification, **jamais à recopier en dur** :

```
DDTM     Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du 14 février 2023
statuts  D'après les publications de la préfecture des Bouches-du-Rhône
OSM      © les contributeurs d'OpenStreetMap
EFFIS    © Union européenne, Copernicus Emergency Management Service / EFFIS
météo    Données Météo-France — Licence Etalab 2.0        (lien_licence : CHAÎNE VIDE)
```

### 1.1 Règles d'emploi — opposables

1. **Interdit de découpe.** `phrase` et `texte` se rendent **entiers**. Le thème ne coupe pas,
   n'abrège pas, ne reformule pas, n'insère pas de lien au milieu, et n'invente aucun libellé de lien.
   La forme admise est `<a href="{lien_licence}">{phrase}</a>` ou la phrase nue. C'est écrit dans
   `includes/ingest/tuiles/attribution.php` et `includes/domain/massifs/attribution.php` : la phrase
   est une **donnée**, pas de la rédaction de thème.
2. **Échappement en sortie systématique** : `esc_html()` sur les textes, `esc_url()` sur les URL. Les
   retours sont **bruts et non échappés** par contrat.
3. **`lien_licence` absent ou vide ⇒ aucun `<a>`.** Deux cas distincts, et le premier est un piège :
   la météo rend une **chaîne vide** (un `href=""` pointerait sur la page courante), tandis que
   `massifs_attribution_zones_parcourues_par_le_feu()` **n'a pas du tout la clé** — y accéder est un
   warning PHP 8, pas une chaîne vide. Le tableau du §1 fait foi sur les clés existantes ; ne jamais
   déduire une clé de la présence d'une autre. Dans les deux cas la phrase se rend **nue**.
4. **Toute valeur vide ⇒ rien ne s'affiche**, et cela vaut pour **chaque clé**, pas seulement pour
   `phrase` et `texte`. `faits.couche` d'EFFIS vaut la chaîne vide **par conception**, et `couche` des
   périmètres peut l'être. Qui énumère un bloc `faits` sans rejouer la garde clé par clé produit un
   `<dt>` suivi d'un `<dd>` vide — une étiquette qui affirme une donnée absente. Même règle que
   l'emplacement de consigne du §8.4 de `MASTER.md` : ni tiret, ni « non renseigné ».
5. **`massifs_attribution_statuts()` retourne aussi `carte_officielle_url` et `bulletin_url_modele`.
   Interdit d'y toucher sur ces trois pages.** Ces clés existent et appellent : un lien « carte
   officielle » posé là serait une **chaîne de statut** du §11.3 sur une page qui n'affiche aucun
   statut, exactement ce qu'interdit le corollaire du §3. Seule `texte` est lue.
5. **`massifs_meteo_du_jour()` : seule la clé `attribution` est lue.** C'est une lecture **datée**
   (`jour`, `releve_le`, `niveau`, `etat`…) employée pour obtenir une valeur **statique**. La fonction
   est **totale** — jamais d'exception, jamais de `null`, `attribution` toujours peuplée, y compris en
   état `indisponible`. **Aucune autre clé ne doit être lue, et surtout aucune date du retour ne doit
   transparaître dans la page** : une page de mentions légales n'affiche pas l'état du jour.

---

## 2. Routes REST

**Aucune route n'est créée, modifiée ou enregistrée par cette issue.**

`GET /wp-json/massifs/v1/statuts` est **documentée** par la page « La démarche » (§5.4 du brief), elle
n'est pas appelée par elle. La source de rédaction est
`wp-content/plugins/massifs-core/includes/rest/public/README.md`, écrit pour cet usage et servi en
`403` par le serveur web — donc jamais publié tel quel.

**Interdit** : réenregistrer un fournisseur de plan de site, en particulier `users` ou `authors`. La
chaîne #16 le retire inconditionnellement au titre du §9 (fermeture de l'énumération) ; ce
comportement l'emporte. Le plan de site est de toute façon **en sommeil** sur cette issue.

---

## 3. États spéciaux

Les trois pages **n'affichent aucun statut**. Elles héritent donc de la garantie de `page.php` :
faute de statut rendu, la règle §4.2 du brief (« jamais un statut périmé présenté comme courant ») y
est tenue **par construction**.

| État | Émis par le serveur | Rendu par le thème |
| `information_indisponible` | oui, ailleurs | **jamais sur ces trois pages** — aucune n'affiche de statut |
| `hors_saison` | oui, ailleurs | **jamais sur ces trois pages** |
| `donnee_perimee` | oui, ailleurs | **jamais sur ces trois pages** |
| `couche_effis_indisponible` | oui, ailleurs | **jamais sur ces trois pages** ; « La démarche » décrit la *limite* de la couche, pas son *état du jour* |
| attribution vide (`phrase === ''`) | oui | rien n'est rendu — ni intitulé, ni tiret, ni « non renseigné » |
| `lien_licence` vide | oui (météo) | phrase rendue **nue**, sans `<a>` |
| extension désactivée | — | page valide, sections d'attribution absentes, aucun avertissement PHP |

**Corollaire opposable** : le bandeau de non-officialité du §5.6 n'a **pas** à figurer sur ces trois
pages, puisqu'il est obligatoire « sur toute page affichant un statut » et qu'aucune n'en affiche. Ne
pas l'ajouter par excès de zèle : ce serait une phrase de statut sur une page sans statut.

---

## 4. Chaînes fournies par le serveur — et la frontière qui les protège

**Le serveur possède les données et les chaînes. Le thème les place et les échappe.**

Fournies par l'extension, jamais composées par le thème : les cinq attributions, tout libellé de
niveau, toute consigne, toute phrase de fraîcheur, toute date, tout décompte.

Fournies par le **propriétaire du projet** le 16 août 2026, à reprendre **verbatim** :

| Fait | Valeur | Emploi |
|---|---|---|
| Éditeur | `OmbruStudio` | Mentions légales |
| Directeur de publication | `Quentin Doniczka` | Mentions légales |
| Contact | `doniczka.quentin67@gmail.com` | Mentions légales **et** signalement a11y |
| Hébergeur | **en sommeil** — aucun hébergement réel | Mentions légales, dit comme tel |

**Le contact est déclaré une seule fois**, dans une constante ou une variable unique clairement
commentée comme remplaçable par une adresse dédiée. **Interdit de le recopier dans trois gabarits.**
« En sommeil » se dit en toutes lettres, avec renvoi à `docs/decisions/portee-non-publiee.md` — on
n'invente pas un hébergeur, et on ne laisse pas non plus un trou muet.

### 4.1 La frontière du §11.3 — l'arbitrage central de cette issue

`MASTER.md` §11.3 déclare une **liste fermée** des phrases que le site a le droit de rédiger sur une
page publique ; **D-30** la confirme et son point (d) y fait retomber *toute* chaîne paraissant sur une
page publique. Le **§16** en tire deux interdits nommés : la phrase « zéro cookie » **rédigée dans un
gabarit**, et tout taux ou qualificatif de conformité RGAA **écrit dans un gabarit**.

Lu au pied de la lettre, cela interdirait l'issue #18, que le brief §5.1 **exige**.

**Le §16 tranche lui-même la forme de la sortie** : un qualificatif de conformité, « le jour venu,
vient **du contenu, jamais du code** ». D'où la règle gelée ici :

> **Les trois gabarits portent la structure, les attributions servies par le serveur et les faits
> d'identité fournis par le propriétaire. Ils ne portent aucune prose éditoriale.**
> **La prose est du contenu**, saisie en base, rendue par `the_content()`.

Conséquence assumée et **à ne pas dissimuler** : tant que le contenu n'est pas importé, les pages
existent comme gabarits mais **ne sont pas rédigées au sens de la DoD**. La copie est donc livrée
**complète et définitive** sous `docs/recette/`, accompagnée d'une **procédure d'import idempotente et
exécutable**, et le rapport de chaîne le dit noir sur blanc.

*Arbitrage validé par l'orchestrateur avant gel. Précédent suivi : contrat #24, F-1 — la chaîne #23 a
livré les chaînes de la 404 « non ratifiées » et a demandé leur entrée au §11.3 plutôt que de trancher
seule. Même traitement ici : la copie éditoriale est livrée **non ratifiée**, et son entrée au §11.3
est demandée à `lead-design-cms`, propriétaire de `MASTER.md`.*

---

## 5. Gabarits — mécanique gelée

### 5.1 Découverte

Les trois fichiers sont des **gabarits de page WordPress**, en-tête `Template Name:` en tête de
fichier.

**Vérifié en exécutant WP-CLI dans la stack** : `WP_Theme::get_files('php', 1)` retourne
`404.php`, `front-page.php`, `functions.php`, `index.php`, `page.php`, `templates/footer.php`,
`templates/header.php` — donc `templates/*.php` **est** balayé à la profondeur 1, et
`templates/parts/*` ne l'est pas. Un `Template Name:` posé dans `templates/page-*.php` sera bien
proposé par `get_page_templates()`, qui retourne aujourd'hui un tableau vide.

**Ce que cela coûte, dit franchement** : un gabarit ne s'applique qu'à une **page existante en base**
à laquelle il a été affecté. `docker/provision/provision.sh` ne crée **aucune page et aucun menu**, et
il est hors empreinte. C'est la procédure d'import de `docs/recette/` qui crée les trois pages, leur
affecte le gabarit, et affecte les trois entrées à l'emplacement de menu `pied`.

### 5.2 Squelette imposé

```
<?php  // Template Name: …
declare(strict_types=1);  guard ABSPATH

require_once du fichier includes/seo-meta.php + appel d'enregistrement de la description
        ← IMPÉRATIVEMENT AVANT l'inclusion de l'en-tête : c'est templates/header.php
          qui appelle wp_head(). Après lui, il est trop tard, le <head> est fermé.

get_template_part( 'templates/header' );

if ( have_posts() ) : the_post();
  <section class="bande bande--editorial">
    <div class="bande__contenu">
      the_title( '<h1>', '</h1>' );   ← l'unique h1 de la page
      the_content();                  ← ENFANT DIRECT de .bande__contenu
      … blocs d'attribution / d'identité, également enfants directs …
    </div>
  </section>
else : massifs_journaliser( … ); endif;

get_template_part( 'templates/footer' );
```

**Trois contraintes de structure, chacune avec sa raison :**

1. `the_content()` doit rester **enfant direct** de `.bande__contenu`. `layout.css` y accroche le
   rythme vertical (l. 134), l'espacement des titres (l. 138) et la mesure de **68ch** (l. 143). Une
   `<div>`, un `<article>` ou un `entry-content` fait perdre les trois **en silence**. Idem pour les
   blocs d'attribution.
2. `the_title()` et `the_content()` sont rendus **sans `esc_html()`** — ce n'est pas un oubli. Le
   filtre `the_title` applique `wptexturize()`, qui **produit** des entités ; les ré-encoder
   afficherait « Mentions l&#8217;égales ». Ne pas « corriger ».
3. `if` et non `while`, un seul `the_post()` : le nombre de `h1` émis reste une constante du code
   source, pas une fonction de `$wp_query->post_count`. Même raison que `page.php`.

### 5.3 `includes/seo-meta.php`

Le répertoire `includes/` du thème **n'existe pas encore** et **rien ne le charge** : `functions.php`
ne fait aucun `require` et est hors empreinte. Le fichier est donc **chargé par les trois gabarits
eux-mêmes**, avant l'inclusion de l'en-tête, ce qui laisse `wp_head()` encore à venir.

Périmètre, une fois retirés le plan de site et les aperçus de partage (**en sommeil**) et le SEO
avancé (**hors périmètre**, §13) :

- une `<meta name="description">` par page, **fournie par l'appelant**, jamais déduite ni tronquée
  depuis le contenu ;
- rien d'autre. Les titres uniques sont déjà tenus par `wp_get_document_title()` dans
  `templates/header.php`.

**Interdits attachés** : aucun `add_theme_support('title-tag')` — `templates/header.php` imprime déjà
`<title>` et en ajouterait un second ; aucun fournisseur de plan de site, en particulier `users` ou
`authors` ; aucune balise `og:` ou `twitter:` ; aucun enregistrement en double si le fichier est
inclus trois fois — garde `function_exists()` et garde d'enregistrement.

---

## 6. Interdits — opposables en revue

**Frontière thème / extension**
- Le thème n'appelle jamais une source externe ni une fonction d'ingestion.
- Le thème ne calcule jamais une règle métier — saison, péremption, formatage de niveau, date,
  décompte. Aucune des trois pages ne compose de date : `massifs_horodatage()` existe pour ça, et ces
  pages n'en ont de toute façon pas besoin.
- L'extension n'émet aucun HTML de présentation publique. Rien n'est demandé à l'extension ici.

**Contenu officiel et rédaction**
- Aucune attribution rédigée à la main, découpée, abrégée ou reformulée (§1.1).
- **Aucun taux ni qualificatif de conformité RGAA**, nulle part — ni « non conforme », ni
  « partiellement conforme », ni « x % des critères ». Aucun audit n'a été mené, et ces qualificatifs
  sont eux-mêmes des résultats d'audit. Ni dans le gabarit, ni dans la copie.
- **Aucune phrase « zéro cookie » écrite dans un gabarit.** Le sujet est traité **dans le contenu** de
  « La démarche », où le §9 du brief le place.
- Aucun lien de pied codé en dur vers les trois pages, aucun slug ni libellé inventé : ce sont des
  **entrées de menu** affectées à l'emplacement `pied` (§7.3).
- Aucun libellé officiel, aucun libellé de niveau, aucune consigne sur ces pages.
- Aucun terme hors du vocabulaire fixe du §11.2 ; aucun emoji, exclamation, superlatif ; jamais
  « Valider », « OK », « En savoir plus », « Oups », « Désolé ».
- Aucune identité inventée, aucun hébergeur inventé, aucun libellé qui *ressemble* à une vraie
  identité. Les valeurs du §4 sont les seules admises.
- Aucune licence déclarée pour **notre agrégat JSON** : la question **Q1 du contrat #8** est ouverte
  et n'appartient pas à cette chaîne. « La démarche » documente le **format** de la route, jamais sa
  licence.

**Accessibilité et structure**
- Un seul `h1` par page, celui de `the_title()`. **Aucun `h1` dans le corps** — la hiérarchie du
  contenu commence à `h2` (contrat #24, F-6).
- Blocs **plats** uniquement dans le contenu : un `wp:group`, `wp:columns` ou `wp:cover` enveloppe ses
  enfants dans une `<div>` et leur fait perdre les 68ch et le rythme vertical **en silence**.
  Rappel : `wp-block-library` est retiré du front (`functions.php` l. 297-311).
- Aucune ancre en double, aucun lien `#…` non résolu, **jamais l'ancre `contenu-principal`** (elle
  appartient à `<main>`).
- **Aucun contenu protégé par mot de passe** : le formulaire du cœur pose un cookie `wp-postpass_`,
  contre le « zéro cookie côté public » des §2 et §9.
- Aucun `outline: none`, aucune cible < 44 px, aucune information portée par la couleur seule.

**Fabrication**
- **Aucune requête vers un domaine tiers** — police, script, image, feuille. Les trois pages
  n'enfilent **rien** : elles héritent des feuilles déjà en place et n'ajoutent pas un octet.
- Aucun `<style>` ni `<script>` en ligne : depuis #16 le site sert `script-src 'self'` et
  `style-src 'self'`, et un style en ligne serait **bloqué par la CSP**.
- Aucune écriture dans `assets/css/**` : `dev-ux-cms` n'est **pas** lancé sur cette issue, ce
  répertoire est hors empreinte.

---

## 7. Arbitrages

| # | Désaccord ou tension | Décision | Raison |
|---|---|---|---|
| **A-1** | Le §11.3 est une liste fermée qui, lu littéralement, interdit de rédiger trois pages publiques que le brief §5.1 exige | **Gabarit = structure + chaînes du serveur ; prose = contenu en base.** Copie livrée complète sous `docs/recette/` + import idempotent | Le §16 tranche lui-même : un qualificatif de conformité « vient du contenu, jamais du code ». Précédent #24 F-1 : livrer **non ratifié** et demander l'entrée au §11.3, plutôt que trancher seul. Validé par l'orchestrateur |
| **A-2** | `templates/page-*.php` n'est pas résolu par la hiérarchie WordPress, qui ne lit `page-{slug}.php` qu'à la racine du thème | **Gabarits `Template Name:`**, découverts au balayage de profondeur 1 | Vérifié en exécutant WP-CLI dans la stack, pas déduit. Coût nommé : une page en base doit exister et porter le gabarit — c'est l'import qui le fait |
| **A-3** | `includes/seo-meta.php` serait du code mort : rien ne le charge et `functions.php` est hors empreinte | **Chargé par les trois gabarits, avant l'inclusion de l'en-tête** | C'est le seul point où `wp_head()` n'a pas encore été appelé. Aucune modification hors empreinte, aucun fichier inerte livré |
| **A-4** | Le pied rend déjà DDTM + préfecture sur **toutes** les pages ; le §9 exige les **cinq** en mentions légales, page qui n'affiche aucune donnée | **Les cinq sont rendues en mentions légales**, comme **table de sources et licences** — présentation distincte du crédit de pied | Le §9 et le §16 l'exigent. La duplication de deux phrases est **pré-existante et déjà enregistrée** (contrat #24, F-3) ; la résoudre demande `templates/footer.php`, hors empreinte → report |
| **A-5** | L'attribution météo n'a pas de fonction dédiée ; elle vit dans une lecture datée | **`massifs_meteo_du_jour()['attribution']`, cette clé et rien d'autre**, aucune date du retour ne transparaît | La fonction est **totale** : attribution toujours peuplée, même en état `indisponible`. Demander une fonction dédiée toucherait l'extension, hors empreinte. Dette signalée à l'orchestrateur, qui l'a actée |
| **A-6** | `lien_licence` de la météo est **vide** dans la version livrée | **Phrase rendue nue, aucun `<a>`** | Un `href=""` pointe sur la page courante — un lien qui ment est pire qu'une absence de lien |
| **A-7** | `faits` n'est lu par aucun gabarit existant | **« La démarche » les consomme**, y compris `faits.connecteur` d'EFFIS | Les trois modules d'attribution réservent explicitement `faits` à cette page, en commentaire de code. `connecteur` rend la **portée simulée auditable** — le dire est une exigence d'honnêteté du §2 de `portee-non-publiee.md`, pas une option |
| **A-8** | Le §7.3 veut un `h2` éditorial en famille de **texte**, sans repère ; le code rend les `h1`/`h2` hors accueil en famille **d'affichage** | **Divergence subie, non corrigée, et signalée** | C'est la divergence **déjà enregistrée** au contrat #24, A-8/F-2. La corriger demande `assets/css/editorial.css` **et** un handle dans `functions.php` : deux fichiers hors empreinte. **Vérifié à l'écran**, pas déduit. Mes trois pages la portent sur trois pages de plus → report |
| **A-9** | Les preuves a11y ne peuvent pas être relevées : la CSP de #16 (`script-src 'self'`) bloque l'injection d'axe-core | **`bypassCSP` côté pilote de test uniquement, sur la passe axe ; passe réseau sans aucune dérogation** | La dérogation appartient au pilote, pas au site : elle ne retire pas l'en-tête et ne fausse donc ni la preuve « zéro requête tierce » ni la mesure de la CSP. **Consigné dans `docs/recette/`** : un relevé qui tait ses conditions vaut moins que pas de relevé |
| **A-10** | Le contrôle humain au lecteur d'écran ne peut pas être exécuté par un agent | **Livré comme procédure exécutable + gabarit de preuve à remplir, jamais comme résultat** | Consigner une vérification non exécutée serait la même faute que présenter un statut périmé comme courant |
| **A-11** | Les livrables §11 (installation, journal des décisions, administration) débordent de `docs/recette/**` | **Placés sous `docs/recette/`**, en **renvoyant** à l'existant plutôt qu'en le dupliquant | `docs/decisions/` et `docker/README.md` sont hors empreinte et déjà écrits ; `MASTER.md` §15 porte déjà un journal des décisions. Dupliquer créerait deux vérités. Conflit d'empreinte signalé en rapport |
| **A-12** | `faits.connecteur` d'EFFIS vaut le jeton brut `simule` — ni un mot français, ni une phrase | **Publié verbatim, mais rendu en `<code>`** comme un identifiant technique | A-7 impose de le publier — taire la portée simulée serait mentir sur ce que la page prétend documenter. Le reformuler en « simulé » violerait l'interdit de composition. Le `<code>` dit au lecteur qu'il voit un jeton, pas une faute. **Dette** : un libellé lisible relève de l'extension, hors empreinte |
| **A-13** | `faits.extrait_le` du fond de carte est une date ISO `2026-08-13` **sans équivalent long**, là où les périmètres ont `donnees_du_libelle` | **Cette date n'est pas affichée** | Les deux issues sont fautives : la rendre brute viole le §11.1 règle 6 de `MASTER.md` (le thème ne compose ni n'affiche une date non mise en forme par le serveur), la formater viole le §6 de ce contrat. Ne rien afficher est la seule sortie propre. **Dette** : une clé `extrait_le_libelle` côté extension, hors empreinte |
| **A-14** | Les trois `<meta name="description">` sont **rédigées dans un gabarit**, ce que A-1 interdit à la prose éditoriale | **Assumé, et livré non ratifié au titre de Q-2** | Une description doit être émise dans `<head>`, avant `wp_head()` ; la faire venir du contenu demanderait `functions.php`, hors empreinte. L'incohérence est réelle : elle est **nommée ici plutôt que laissée à la revue** |
| **A-15** | Le §7.3 veut que la table des sources reprenne le tableau de la liste du jour | **Liste de définitions (`<dl>`) à la place** | Les classes `liste-statuts__*` portent une mise en page mobile en cartes, une règle `:empty` qui dépend des sauts de ligne du PHP, et du contenu généré par `data-etiquette` : les réemployer serait fragile et faux de sens. Les rendre correctement demanderait du CSS, hors empreinte. Une `<dl>` tient à 360 px sans une ligne de style — **et le zéro défilement horizontal est bloquant** (§8). Vérifié : 0 px de débordement |

---

## 8. Questions ouvertes — remontées, jamais comblées

| # | Question | Statut |
|---|---|---|
| **Q-1** | **Licence de notre agrégat JSON** (question Q1 du contrat #8, toujours ouverte). « La démarche » documente le format de la route sans pouvoir en déclarer la licence | **Non bloquante avant le commit**, bloquante avant toute mise en production. Ne jamais combler par déduction |
| **Q-2** | **Entrée au §11.3 de la copie éditoriale** des trois pages, livrée **non ratifiée** | À `lead-design-cms`, propriétaire de `MASTER.md`. Précédent : #24 F-1 |
| **Q-3** | **Phrase « zéro cookie » au pied** — `OUVERT` depuis `MASTER.md` §7.3 | Sans objet ici : le sujet est traité **dans le contenu** de « La démarche », pas au pied |
| **Q-4** | **Provisionnement durable** des trois pages et du menu `pied` | Hors empreinte (`docker/provision`). L'orchestrateur a acté une issue de suivi, voisine de #44 |

---

## 9. Ce que cette issue **ne** prouve **pas**

Écrit ici pour ne pas être déduit à tort par une revue :

- Elle ne prouve pas la ligne de DoD « pages rédigées » **tant que l'import n'a pas tourné** : les
  gabarits existent, le contenu est livré, la base ne les porte pas encore.
- Elle n'exécute **ni** `test-integration-cms`, **ni** `review-cms`, **ni** `docker-cms`, **ni**
  `git-cms push` — niveau lot.
- Le contrôle au lecteur d'écran est **documenté et outillé**, jamais **exécuté**.
- Les relevés a11y et perf valent **pour l'état de l'arbre à leur horodatage**, précisé dans chaque
  fichier de preuve. Un relevé n'est jamais présenté comme courant s'il ne l'est pas.
