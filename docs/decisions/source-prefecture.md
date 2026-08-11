# Décision — Source des statuts préfecture et mode de récupération

**Issue** #1 · **Épic 1 — Fondations des données** · **Domaines** `statuts`, `infra`
**Date du relevé** : 11 août 2026 · **Auteur** : chaîne `lead-issue-cms` #1
**Statut** : décision arrêtée · relevé à **re-vérifier au 1er juin de chaque saison** (voir §9)

> Ce document répond au §4.2 du brief (« Première chose à faire du projet : investiguer cette source »).
> Il est la source de vérité factuelle sur la source préfectorale pour toutes les chaînes aval,
> en particulier la chaîne #3 (modèle statuts / légende / fraîcheur) et la chaîne #2 (référentiel).
>
> **Règle de lecture** : tout ce qui est écrit ici est *observé*, avec son URL et son extrait verbatim.
> Ce qui n'a pas pu être observé est listé au §9 comme **question ouverte au propriétaire du projet**.
> Le §4.2 du brief interdit formellement d'inventer un fait de domaine. Aucun agent aval ne doit
> compléter ce document par déduction.

---

## 1. Question posée

La carte officielle `risque-prevention-incendie.fr/13` expose-t-elle un flux exploitable pour les statuts
quotidiens des massifs ? Le brief prévoit trois issues, toutes acceptables :

1. flux exploitable et stable → récupération automatisée quotidienne ;
2. flux fragile → récupération automatisée avec garde-fous (validation stricte, refus des valeurs
   aberrantes) + repli manuel ;
3. pas de flux → mode manuel assumé via le portail.

**Réponse : un flux existe, il est réel et directement exploitable, mais il n'est pas contractuel.
Le mode retenu est le n° 2.** Justification au §6.

---

## 2. Méthode

Relevé effectué le 11 août 2026 entre 15 h 50 et 16 h 10 (heure de Paris) par requêtes `curl` directes
depuis le poste de développement, et par lecture du code source JavaScript publié par le site officiel.
Aucune donnée n'a été déduite : chaque affirmation ci-dessous est adossée à une URL et à un extrait.

Le site est une **application JavaScript** : le HTML rendu par le serveur ne contient aucun statut, mais
un espace réservé portant le texte `Chargement des données en cours...`. Les statuts sont chargés côté
client. Le relevé a donc porté sur les fichiers JavaScript de la page, puis sur les points d'accès qu'ils
appellent.

Fichiers lus (source officielle) :

| URL | Rôle |
|---|---|
| `https://www.risque-prevention-incendie.fr/13/` | page de la carte |
| `https://www.risque-prevention-incendie.fr/static/13/js/massifs_prev.js` | style et infobulles des massifs |
| `https://www.risque-prevention-incendie.fr/static/js/maps_prev.js` | chargement des données, URL des flux |
| `https://www.risque-prevention-incendie.fr/static/js/translation.js` | mécanique d'internationalisation |
| `https://www.risque-prevention-incendie.fr/static/13/translation/fr.json` | **libellés officiels du 13** |
| `https://www.risque-prevention-incendie.fr/static/13/import_data/20260811.pdf` | bulletin officiel du jour |

---

## 3. Le flux de données

### 3.1 Point d'accès

```
https://www.risque-prevention-incendie.fr/static/13/import_data/{AAAAMMJJ}.json
```

Construit dans `maps_prev.js` :

```js
var url_data = '../static/' + id + '/import_data/';   // ligne 729, id = 13
data = await $.getJSON(url_data + date_format + '.json?nocache=' + new Date().getTime());  // ligne 293
```

Un bulletin PDF officiel est publié au même emplacement avec l'extension `.pdf`.

### 3.2 Sondages effectués

| Date demandée | HTTP | Type | Taille | `Last-Modified` |
|---|---|---|---|---|
| `20260811` (aujourd'hui) | **200** | `application/json` | 1762 o | `Mon, 10 Aug 2026 15:00:05 GMT` |
| `20260812` (demain) | **404** | — | — | — |
| `20260810` (hier) | 200 | `application/json` | 1762 o | — |
| `20260808` | 200 | | | |
| `20260805` | 200 | | | |
| `20260801` | 200 | | | |
| `20260715` | 200 | | | |
| `20260601` | **200** | | | |
| `20260531` | **404** | — | — | — |
| `20250815` (saison précédente) | **200** | | | |
| `20251001` | **404** | — | — | — |

En-têtes constants : `Server: nginx`, `Cache-Control: no-cache, no-store, must-revalidate`.

**Trois faits structurants tirés de ce tableau :**

1. **Le 404 est le signal normal de « pas encore publié »**, pas une panne. À 15 h 57, le fichier du
   lendemain n'existe pas encore ; il apparaîtra après la publication du soir.
2. **La borne saisonnière est mécaniquement observable** : `20260601` existe, `20260531` non ;
   `20250815` existe, `20251001` non. Le fichier n'existe que du 1er juin au 30 septembre.
3. **L'historique reste servi** : les dates passées, y compris de la saison précédente, répondent 200.

### 3.3 Forme exacte de la charge utile

Contenu verbatim de `20260811.json` (abrégé au milieu, les 27 entrées ont ici la même valeur) :

```json
{
    "massifs": {
        "131": [2, 0],
        "1324": [2, 0],
        "132": [2, 0],
        ...
        "1326": [2, 0]
    },
    "zm": {
        "131": 2, "132": 2, "133": 2, "134": 2, "135": 2,
        "136": 2, "137": 2, "138": 2, "139": 2
    }
}
```

- **`massifs`** — objet dont la clé est l'identifiant du massif (chaîne). La valeur est un tableau de
  **exactement 2 entiers**.
- Identifiants observés : **exactement 27**, contigus de `131` à `1327` (soit `13` suivi de 1 à 27).
- **`zm`** — 9 entrées, `131` à `139`. Un entier simple, pas un tableau. Granularité différente
  (« zones météo »). **Sémantique non publiée** → question ouverte §9.

Signification des deux éléments du tableau, d'après `maps_prev.js` lignes 109-110 :

```js
feature.properties.level     = data.massifs[massifId][0]; // Récupérer le level
feature.properties.procedure = data.massifs[massifId][1]; // Récupérer la procédure
```

- index 0 = **`level`** — valeurs observées sur les dates sondées : `0`, `1`, `2`, `3`.
  La valeur `4` **n'a jamais été observée** au cours de la saison 2026.
- index 1 = **`procedure`** — valeurs observées : `0`, `1`.
  Le 5 août 2026, `procedure` valait `1` pour **exactement** les 7 massifs qui étaient au `level` 3,
  et `0` pour tous les autres. Corrélation constatée sur un seul jour ; **sémantique non publiée**
  → question ouverte §9.

### 3.4 Sémantique du `level`, telle que le site officiel l'applique

Relevée dans `massifs_prev.js` (c'est le code qui produit le rendu de la carte officielle, donc la
meilleure preuve disponible en l'absence de documentation) :

```js
var tabCorresCouleursMassifs = { 0: "white", 1: "green", 2: "green", 3: "red", 4: "red" };
```

et, pour les infobulles :

```js
if (feature.properties.level === 0) { /* aucun statut affiché */ }
else if (level === 1 || level === 2) { "Accès Autorisé / Access authorized" }
else if (level === 3 || level === 4) { "Accès Interdit / Access prohibited" }
```

Pour les ZAPEF, dans `maps_prev.js` ligne 310 :

```js
flag_icon = (level >= 0 && level <= 3) ? flag_icon_green : flag_icon_red;
```

**Table de correspondance officielle constatée :**

| `level` | Couleur du massif | Accès au massif | Accès à la ZAPEF |
|---|---|---|---|
| `0` | blanc | *aucune donnée* | ouverte |
| `1` | vert | autorisé | ouverte |
| `2` | vert | autorisé | ouverte |
| `3` | rouge | interdit | **ouverte** |
| `4` | rouge | interdit | **fermée** |

Le `level` 4 est donc le **seul** qui ferme aussi les ZAPEF. C'est la seule différence de conséquence
entre 3 et 4 ; entre 1 et 2, aucune différence de conséquence n'a pu être observée.

Un cas particulier codé en dur existe pour une ZAPEF précise (`maps_prev.js` ligne 314) :
`if (level == 4 && feature.properties.id == "2016-001")` → « Fermés sauf parking n°1 ». Signalé pour
mémoire ; hors périmètre du site.

---

## 4. Légende officielle relevée

> Cette section répond aux **8 questions bloquantes** de `design-system/MASTER.md` §4.1.
> Elle est destinée à la chaîne #3, qui paramètre le domaine à partir d'elle.
> **Ce qui n'est pas établi ici ne doit pas être inventé** : voir §9.

### 4.0 Source primaire

Les libellés ci-dessous proviennent du fichier de traduction officiel du département 13 :

```
https://www.risque-prevention-incendie.fr/static/13/translation/fr.json
```

C'est le fichier que la page officielle charge et applique pour composer sa propre légende
(`translation.js`, fonction dédiée au 13, lignes 227-249). Ce n'est donc pas une paraphrase ni une
source secondaire : **c'est la chaîne de caractères que la préfecture affiche**.

Corroboré par le bulletin PDF officiel du jour
(`.../static/13/import_data/20260811.pdf`), dont le texte extrait est reproduit au §4.9.

### 4.1 Q1 — Combien de niveaux ?

**ÉTABLI. La légende publique du 13 compte DEUX états d'accès au massif, pas quatre ni cinq.**

Preuve directe et vérifiable de trois manières concordantes :

1. `fr.json` ne contient que deux libellés d'accès au massif : `low_risk` et `severe_risk`.
2. La fonction de traduction du 13 dans `translation.js` (lignes 227-228) n'alimente que
   `legendItems[0]` et `legendItems[1]` :
   ```js
   legendItems[0].innerHTML = '<img alt="legende_vert" ...> ' + translations['low_risk'];
   legendItems[1].innerHTML = '<img alt="legende_rouge" ...> ' + translations['severe_risk'];
   ```
3. La page `/13/` ne contient que **2** éléments `list-group-item` (comptés dans le HTML brut).

**Attention — piège écarté.** Le fichier `translation.js` est **partagé par tous les départements** de
la plateforme. Il contient aussi une fonction à **six** crans de vigilance
(`risque_faible`, `risque_leger`, `risque_modere`, `risque_severe`, `risque_tres_severe`,
`risque_exceptionnel`) et les images `couleur_jaune.png`, `couleur_orange.png`, `couleur_noir.png`
existent bien sur le serveur (HTTP 200). **Mais ce bloc est gardé par `if (legendItems.length >= 6)`
et ne s'exécute jamais sur la page du 13**, qui n'a que 2 entrées de légende. Cette échelle à six crans
appartient à d'autres départements (Alpes-Maritimes, Var, Gard…), pas aux Bouches-du-Rhône.

**Conséquence directe pour le design system** : les 5 niveaux gradués du tableau §4.1 de
`design-system/MASTER.md` **ne correspondent pas au dispositif du 13**. Voir la question ouverte §9-Q1,
qui est un arbitrage de produit, pas une décision d'agent.

### 4.2 Q2 — Libellés officiels, mot pour mot

**ÉTABLI.** Verbatim depuis `fr.json` :

| Clé | Libellé officiel, mot pour mot |
|---|---|
| `low_risk` | `Accès au massif autorisé` |
| `severe_risk` | `Accès au massif interdit` |
| `zapef_open` | `Accès à la ZAPEF* autorisé` |
| `zapef_closed` | `Accès à la ZAPEF* interdite` |
| `zapef` | `*ZAPEF : Zones d’Accueil du Public en Forêt` |
| `tablevel` | `Niveau d'Accès` |
| `legend_title` | `Légende de la carte` |

Note d'orthographe à reproduire fidèlement : `autorisé` est au masculin pour la ZAPEF alors que
`interdite` est au féminin — c'est bien ce que publie la source. La note ZAPEF utilise une apostrophe
typographique `’` (U+2019), les autres chaînes une apostrophe droite `'`.

Le bulletin PDF emploie une formulation légèrement différente, avec le préfixe « Vigilance » :
`Vigilance vert - Accès au massif autorisé` et `Vigilance rouge - Accès au massif interdit` (§4.9).
**Les deux formulations coexistent dans la source officielle** ; celle de la carte (`fr.json`) est
retenue comme référence pour l'écran, celle du PDF est signalée pour mémoire.

### 4.3 Q3 — Codes couleur exacts

**ÉTABLI par relevé au pixel** sur les images de légende publiées par le site
(`/static/images/couleur_*.png`, 167 × 72 px, couleur dominante mesurée sur l'image entière) :

| Image | Hex relevé | RVB | Part de l'image |
|---|---|---|---|
| `couleur_vert.png` | **`#22B14C`** | 34, 177, 76 | 95 % |
| `couleur_rouge.png` | **`#E63A3C`** | 230, 58, 60 | 92 % |
| `couleur_jaune.png` | `#F8E919` | 248, 233, 25 | 92 % |
| `couleur_orange.png` | `#F9BD6E` | 249, 189, 110 | 92 % |
| `couleur_noir.png` | `#000000` | 0, 0, 0 | 99 % |

**Seuls le vert et le rouge sont utilisés par le 13.** Les trois autres sont fournis pour information :
ils appartiennent à l'échelle à six crans des autres départements (§4.1). Ils sont relevés ici pour
éviter qu'une chaîne aval aille les redemander.

Sur la carte elle-même, les polygones ne sont pas peints avec ces hex mais avec les **couleurs CSS
nommées** `green` et `red`, en `fillOpacity: 0.5`, contour `black` de 0,8 px (`massifs_prev.js`,
`styleMassifs`). Les deux encodages coexistent dans la source : hex dans les pastilles de légende,
couleurs nommées sur la carte.

> **Avertissement d'accessibilité, à traiter par la chaîne #3 / `dev-ux-cms` :** ces couleurs sont
> celles de la source, et le §4.2 du brief impose de reproduire la légende officielle. Mais `#E63A3C`
> et `#22B14C` n'ont pas été vérifiés au regard de l'exigence AA bloquante du §8. Le design system
> impose déjà un liseré charbon 2 px et un motif obligatoire (`MASTER.md` §4.1, règles 2 et 4) : ce sont
> ces deux mécanismes, et non la teinte, qui doivent porter la conformité. **Reproduire la teinte,
> garantir le contraste par le liseré et le motif.**

### 4.4 Q4 — Consignes officielles par niveau

**NON ÉTABLI.** La légende de la carte officielle ne porte **aucune consigne** : elle se limite à
« autorisé » / « interdit ». Aucun horaire d'accès, aucune mention de travaux, aucune mention de
circulation ou de stationnement n'apparaît dans `fr.json`, dans le HTML de la page, ni dans le
bulletin PDF du jour.

L'arrêté préfectoral en vigueur, qui contiendrait ces consignes, a été téléchargé :

```
https://www.bouches-du-rhone.gouv.fr/contenu/telechargement/59418/421235/file/20250422_AP-acces_massifs_RAA-13-025-04-022-00011.pdf
```

HTTP 200, 11,8 Mo. **Ce PDF est un document numérisé sans couche de texte** : l'extraction
(`pdftotext`) rend 12 caractères. Il n'a donc pas pu être lu automatiquement, et je me refuse à en
deviner le contenu.

Seul élément approchant, relevé sur la page de la préfecture, et qui concerne **les travaux, pas
l'accès des promeneurs** :

> « Chaque jour en fonction du risque évalué par Météo France, les travaux peuvent être autorisés sous
> conditions ou restreints ou interdits en cas de risque très sévère ou extrême. »

→ **Question ouverte §9-Q4.**

### 4.5 Q5 — Distinction piéton / circulation / travaux

**PARTIELLEMENT ÉTABLI.** La source distingue clairement **deux objets** dans sa légende :

1. **le massif** — `Accès au massif autorisé` / `interdit` ;
2. **la ZAPEF** — `Accès à la ZAPEF* autorisé` / `interdite`, définie comme
   `Zones d’Accueil du Public en Forêt`. Ce sont des points (marqueurs), pas des surfaces.
   Elles restent ouvertes jusqu'au `level` 3 inclus et ne ferment qu'au `level` 4.

**Les travaux relèvent d'un dispositif séparé, sur une carte séparée**, explicitement désignée par la
page du 13 :

> « La réalisation des travaux par les particuliers et entreprises peut être à l'origine d'un départ de
> feu dans les espaces exposés au risque incendie et est réglementé par la carte suivant : »
> → `https://opendfci.fr/13/index.php/view/map?repository=openmassifs&project=open_massifs`

(instance Lizmap, projet « Risque feu de forêt pour les travaux réalisés par les entreprises et les
particuliers », exposant des services WMS/WFS/WMTS — **hors périmètre de cette issue**.)

La distinction **circulation / stationnement** n'apparaît nulle part dans la source consultée.
→ **Question ouverte §9-Q4.**

### 4.6 Q6 — Libellé quand le statut du lendemain n'est pas publié

**ÉTABLI.** Deux mécanismes concordants :

1. Le fichier du lendemain **n'existe pas** (HTTP 404) tant qu'il n'est pas publié — vérifié sur
   `20260812.json` à 15 h 57.
2. Le libellé affiché par la carte officielle dans ce cas, verbatim depuis `fr.json` :

   ```
   "popuph1": "Actuellement aucune donnée disponible pour le :  "
   ```

   suivi de la date. Déclenché par `loadFail(date)` dans `maps_prev.js` (ligne 466), lui-même appelé
   quand la récupération JSON échoue.

Le message de journal technique correspondant est
`MASSIFS : Les données JSON pour la date donnée ne sont pas encore disponibles`.

> **Ce libellé officiel n'est pas celui de notre site.** Le brief (§5.3) et `MASTER.md` §11.3 fixent
> notre propre formulation : « Information du jour non disponible. Consultez la carte officielle de la
> préfecture. » Le libellé officiel est relevé ici pour information et pour cohérence de vocabulaire,
> pas pour être recopié.

### 4.7 Q7 — Dates exactes du dispositif

**ÉTABLI**, par trois sources concordantes :

1. Titre officiel de la carte, verbatim depuis `fr.json` :
   > « Carte d'accès aux massifs forestiers des Bouches-du-Rhône pour la saison feux de forêt
   > **(du 1er juin au 30 septembre)** »
2. Texte de la page, verbatim :
   > « Pour votre sécurité et la préservation des forêts, l'accès aux massifs forestiers est réglementé
   > **du 1er Juin au 30 Septembre inclus.** »
3. Comportement mécanique du flux : `20260601` → 200 · `20260531` → 404 · `20250815` → 200 ·
   `20251001` → 404.

**Le libellé employé hors période n'est pas établi** : hors saison, la source ne publie simplement pas
de fichier. Notre propre formulation est fixée par `MASTER.md` §11.3 (« Dispositif estival inactif.
Reprise le {date}. »). → reste ouvert §9-Q5 pour la date de reprise à afficher.

### 4.8 Q8 — Autorisation de reproduction et mention de source

**NON ÉTABLI — et c'est un point bloquant avant mise en ligne.**

Ce qui a été vérifié :

- `https://www.risque-prevention-incendie.fr/robots.txt` → **HTTP 404**. Aucune directive
  d'exploration publiée : ni autorisation, ni interdiction.
- Aucune page de mentions légales ni de conditions générales d'utilisation trouvée sur le domaine
  (`/mentions`, `/mentions-legales`, `/13/mentions-legales`, `/infos` → tous 404).
- En-tête `Cache-Control: no-cache, no-store, must-revalidate` sur toutes les réponses. C'est une
  directive de cache navigateur, **pas** une interdiction de réutilisation, mais elle indique que
  l'exploitant ne prévoit pas la conservation de ses réponses.
- Aucune licence (Etalab, Licence Ouverte…) n'est mentionnée nulle part sur le domaine.

Mentions de source relevées dans le bulletin PDF officiel, verbatim :

> « Données Météo France. Réalisation Entente-PôNT. Source : SCAN 1000 ® © IGN - 10/08/2026 17:00:12 »

Le brief (§9) fixe déjà **notre** mention pour les statuts :
« D'après les publications de la préfecture des Bouches-du-Rhône ».

→ **Question ouverte §9-Q6**, bloquante avant production.

### 4.9 Bulletin PDF officiel — texte intégral extrait

Source : `https://www.risque-prevention-incendie.fr/static/13/import_data/20260811.pdf`

```
INFORMATION JOURNALIERE POUR L'ACCES AUX MASSIFS
DANS LES BOUCHES DU RHÔNE
                     mardi 11 août 2026

                     (mise à jour : 10/08 à 17h00)
Vigilance vert - Accès au massif autorisé
Vigilance rouge - Accès au massif interdit
Accès à la ZAPEF* autorisé
Accès à la ZAPEF* interdite
*ZAPEF : Zones d’Accueil du Public en Forêt

Données Météo France. Réalisation Entente-PôNT. Source : SCAN 1000 ® © IGN - 10/08/2026 17:00:12

Liste des massifs : Alpilles, Arbois, Calanques, Cap Canaille, Castillon, Chaîne des Côtes,
Chambremont, Collines de Gardanne, Concors, Cote Bleue, Etoile, Garlaban, Grand Caunet, Lançon,
Les Roques, Montagnette, Montaiguet, Pont de Rhaud, Quatre Termes, Regagnas, Rougadou,
Sainte-Baume, Sainte-Victoire, Sulauze, Trevaresse
```

### 4.10 Heure de publication — contradiction résolue

Trois valeurs différentes coexistent dans les sources officielles :

| Source | Valeur annoncée |
|---|---|
| Page de la préfecture (`bouches-du-rhone.gouv.fr`) | « vers 18 heures pour le lendemain » |
| HTML brut de la carte (espace réservé, avant JavaScript) | « (mise à jour : à 18h) » |
| `fr.json`, clé `maj2` — **appliquée par le JS, donc réellement affichée** | ` à 17h` |
| Bulletin PDF du jour | « (mise à jour : 10/08 à 17h00) » |
| En-tête HTTP `Last-Modified` du JSON | `Mon, 10 Aug 2026 15:00:05 GMT` = **17 h 00 Paris** |

**Conclusion : la publication réelle a lieu vers 17 h 00 (heure de Paris), la veille du jour de
validité.** Le « 18 h » du HTML est un espace réservé que le JavaScript remplace par « 17h » ; le
« vers 18 heures » de la page préfectorale est une approximation rédactionnelle.

**Conséquence retenue** : la fenêtre de récupération commence à **16 h** et se ferme à **23 h**, avec
une tentative horaire. Démarrer à 16 h coûte au pire deux 404 anodins et couvre une publication en
avance ; se fermer à 23 h laisse sept tentatives avant l'alerte.

### 4.11 Écart 27 identifiants / 25 noms — à trancher par la chaîne #2

Le flux JSON porte **27** identifiants de massif (`131` à `1327`). Le tableau de la page officielle et
la liste du bulletin PDF ne nomment que **25** massifs. Deux identifiants n'ont donc pas de nom publié
sur les supports consultés.

Aucune hypothèse n'est formulée ici. Le garde-fou référentiel du connecteur utilise l'ensemble des
**27 identifiants observés**, car c'est ce que la source émet réellement. → **Question ouverte §9-Q3.**

---

## 5. Ce qui n'est PAS documenté par la source

À consigner explicitement, parce que c'est ce qui fait du flux un flux *fragile* :

- Aucune documentation d'API, aucun contrat, aucune version.
- Aucune licence de réutilisation publiée.
- Aucun `robots.txt`, aucune mention légale, aucune CGU.
- Aucun libellé officiel pour les valeurs `level` 1, 2, 3, 4 prises individuellement — seulement leur
  regroupement en deux états d'accès.
- Sémantique du champ `procedure` inconnue.
- Sémantique et granularité du bloc `zm` inconnues.
- Le `level` 4 n'a jamais été observé en 2026 ; on ignore s'il est encore en vigueur.
- Aucun engagement sur la stabilité de l'URL, de la forme, ni de l'heure de publication.

---

## 6. Mode retenu

### **Option 2 du §4.2 — flux fragile, récupération automatisée sous garde-fous stricts, repli manuel permanent.**

**Pourquoi pas l'option 1 (flux stable).** Le flux est *stable de forme* — 27 identifiants contigus,
deux entiers par massif, historique servi, réponse de 1762 octets constante — ce qui justifie
pleinement l'automatisation. Mais rien de ce qui fait un flux « stable » au sens contractuel n'est
présent : pas de documentation, pas de version, pas de licence, pas de CGU, pas de `robots.txt`, des
libellés de niveaux introuvables, deux champs de sémantique inconnue, un niveau jamais observé, et un
protocole où **404 signifie « pas encore publié »**. Traiter cela comme un contrat serait une erreur
d'appréciation qui se paierait le jour d'un changement silencieux.

**Pourquoi pas l'option 3 (manuel assumé).** Le flux existe, il est trivial à consommer, et le §4.2
demande d'abord d'investiguer précisément pour ne pas retomber sur le manuel par défaut. Renoncer à
une source machine disponible serait un choix par confort.

**Le mode est un réglage d'exécution, pas une architecture.** Option
`massifs_prefecture_reglages['mode']` ∈ `automatique` | `automatique_surveille` (**défaut retenu**) |
`manuel`, basculable sans redéploiement. Justification directe tirée du brief : le §4.2 exige que
*chaque* statut porte sa source (récupération officielle / saisie manuelle) et son auteur — c'est la
preuve écrite que les deux écrivains coexistent en permanence, et non que l'un remplace l'autre. Le
portail (§6) doit de toute façon pouvoir corriger un statut du jour.

**Conséquence si le §9-Q6 (autorisation) revient négatif** : la branche automatisée est désactivée par
le seul réglage `mode = manuel`, sans refonte. C'est précisément ce que cette architecture achète.

---

## 7. Garde-fous retenus

Le détail d'implémentation vit dans le code et son README. Les **décisions** sont ici.

### 7.1 Six couches de validation, aucune ne code en dur une échelle de niveaux

1. **Transport** — 200 exigé ; taille dans `[200, 65536]` (observé : 1762) ; **le premier caractère non
   blanc du corps doit être `{`** — c'est la parade au « HTTP 200 portant une page d'erreur HTML ».
2. **Forme** — décodage strict ; `massifs` objet non vide ; chaque clé `/^\d{3,4}$/` ; chaque valeur =
   tableau de **exactement 2 entiers** (une chaîne numérique ou un flottant est un changement de type,
   donc un signal, donc un rejet). `zm` facultatif et **ne bloque jamais**. Une clé racine inattendue
   est **journalisée, pas rejetée** : sur un flux non contractuel, une clé nouvelle est une
   information, pas une panne.
3. **Référentiel** — comparaison d'ensembles **dans les deux sens** contre les 27 identifiants
   observés : identifiant inconnu, massif manquant, ou cardinal différent → **rejet du lot entier** et
   alerte immédiate. Jamais d'acceptation partielle ni silencieuse. Si l'ensemble de référence est
   vide, on **échoue fermé**.
4. **Sémantique** — `level` et `procedure` doivent appartenir à des listes blanches **stockées en
   option** (`[0,1,2,3,4]` et `[0,1]`), jamais en constantes de code, avec passerelle vers la chaîne #3
   si elle expose un validateur. **Raison impérative** : `MASTER.md` §4.1 est marqué `À CONFIRMER` ;
   figer une énumération dans le code reviendrait à graver une invention que le §4.2 interdit.
5. **Temporel** — la date de validité **est** la date du nom de fichier demandé. Jamais l'heure de
   récupération, jamais `Last-Modified`, jamais recalculée, jamais prolongée. La cible doit être
   aujourd'hui ou demain en Europe/Paris.
6. **Atomicité** — l'unité de persistance est une option unique écrite en un seul appel après succès de
   toutes les couches. Le tout-ou-rien est **structurel**, pas défensif : aucune écriture partielle
   n'est représentable.

### 7.2 Trois « non-règles » explicitement écartées

Ces trois cas ont été proposés comme aberrations à rejeter. **Les rejeter serait dangereux**, et la
raison est inscrite en commentaire dans le validateur pour empêcher leur réintroduction :

- **Une charge où tous les massifs sont au niveau le plus sévère n'est pas une aberration.** C'est
  exactement la journée de canicule où l'information compte le plus ; la rejeter afficherait
  « information non disponible » ce jour-là.
- **Une charge identique à celle de la veille n'est pas une aberration.** C'est le cas nominal en juin
  (vérifié : les 27 massifs au même niveau les 8, 11 août 2026 et 15 août 2025).
- **Un saut d'amplitude de niveau n'est pas une aberration.** Le niveau peut légitimement passer de 1 à
  3 d'un jour à l'autre.

Le seul cas réellement suspect est une réponse **strictement identique, empreinte comprise, servie pour
une autre date de validité** : la source n'a alors pas encore publié. On retente, on n'écrit pas, on ne
rejette pas, on ne compte pas d'échec.

### 7.3 La validation ne dépend pas du mode

Le validateur ne consulte jamais le mode retenu. Il est conçu pour garder **aussi** le chemin
d'écriture manuel du portail : une saisie humaine à 19 h peut produire une date de validité fausse ou
un massif oublié aussi facilement qu'un flux cassé. Même porte, même serrure.

### 7.4 Planification

Événement **horaire** dont le rappel s'auto-filtre, plutôt qu'un événement quotidien à heure fixe.
Trois raisons vérifiables : `wp_schedule_event` stocke un horodatage UTC, donc une heure de Paris fixe
dérive d'une heure au changement d'heure, et la saison est intégralement en heure d'été ; WP-Cron est
déclenché par le trafic, donc un créneau unique et étroit peut être purement et simplement manqué sur
un site peu visité ; la nouvelle tentative exigée par le §4.5 devient gratuite, sans boucle bloquante
dans une requête visiteur.

Sept portillons s'exécutent **avant le moindre octet réseau** : coupe-circuit, mode manuel, dates en
attente, saison évaluée **sur la date cible** (le 31 mai à 18 h la cible est le 1er juin, donc en
saison : c'est ce qui rend le premier jour de saison récupérable), instantané déjà obtenu, garde
anti-rafale de 15 minutes. Coût réel : environ 8 requêtes par jour en saison, **zéro hors saison**.

### 7.5 Alertes (§4.5)

- **Alerte de fenêtre** — une seule, à la première exécution à ou après 23 h, si aucun instantané pour
  le lendemain. Verrou d'unicité par date : jamais une alerte par tentative.
- **Alerte de rejet** — immédiate, une par date, quand un 200 échoue à la validation. C'est une classe
  d'événement différente : la forme de la source a changé, un humain doit regarder le jour même.
- Un **404 avant publication n'est pas un échec** et n'incrémente pas le compteur.

---

## 8. Contrat pour la chaîne #3 et les chaînes aval

### 8.1 Câblage — une seule ligne, à ajouter par la chaîne qui possède le bootstrap

`wp-content/plugins/massifs-core/massifs-core.php` ne charge rien aujourd'hui, et **ce fichier
n'appartient pas à cette chaîne**. Tant que la ligne ci-dessous n'est pas ajoutée, le connecteur est du
**code inerte** : il n'enregistre aucun hook, n'écrit aucune option, n'émet aucune requête.

```php
require_once MASSIFS_CORE_DIR . 'includes/ingest/prefecture/bootstrap.php';
```

Variante explicite, équivalente et idempotente (le double appel est sans effet) :

```php
\Massifs\Ingest\Prefecture\Bootstrap::register();
```

**Point d'entrée nommé : `\Massifs\Ingest\Prefecture\Bootstrap::register()`.**

Contraintes pour le chargeur de #3 :
- l'autoloader éventuel **ne doit pas** mapper `Massifs\Ingest\Prefecture\*` — nommage WPCS
  `class-*.php`, délibérément non PSR-4, ce sous-arbre se charge lui-même ;
- constantes facultatives attendues (repli prévu si absentes) : `MASSIFS_CORE_DIR`, `MASSIFS_CORE_FILE`.

### 8.2 Surface publique — la seule classe que #3 nomme

```php
\Massifs\Ingest\Prefecture\Connector::snapshot_for( string $date_iso ): ?array
\Massifs\Ingest\Prefecture\Connector::has_snapshot_for( string $date_iso ): bool
\Massifs\Ingest\Prefecture\Connector::state(): array
\Massifs\Ingest\Prefecture\Connector::attribution(): array
\Massifs\Ingest\Prefecture\Connector::validate_payload( array $massifs, string $date_iso ): true|WP_Error
\Massifs\Ingest\Prefecture\Connector::run_now( string $date_iso ): true|WP_Error
\Massifs\Ingest\Prefecture\Connector::mode(): string
```

`$date_iso` est **toujours** au format `YYYY-MM-DD` et **toujours obligatoire**.

### 8.3 La couture d'intégration — une seule action

```php
do_action( 'massifs_prefecture_snapshot_enregistre', array $snapshot );
```

C'est **#3 qui s'abonne** et qui projette l'instantané dans son modèle de statuts. Le connecteur
n'écrit jamais dans le modèle de #3, n'invalide aucun cache de page, ne touche à aucune option de #3.

### 8.4 Clause absolue à inscrire dans le modèle de #3, mot pour mot

> **L'API de lecture est indexée exclusivement par date. Aucune fonction « dernier statut connu » sans
> argument de date ne doit exister.**

C'est la traduction technique de la règle du §4.2 « ne jamais présenter un statut périmé comme
courant ». Tant qu'une fonction sans date existe, quelqu'un finira par l'appeler. C'est pourquoi
`Connector` n'expose délibérément **aucun** accesseur « dernier instantané ».

Les quatre garanties que l'ingestion apporte, et que le rendu ne peut pas rattraper si elles manquent :
la date de validité est toujours explicite et issue de la source ; elle n'est jamais prolongée ni
recalculée ; l'écriture est atomique ; **l'échec est enregistré positivement**, afin que le rendu
distingue « pas de donnée pour ce jour », « donnée de plus de 24 h » et « source injoignable ».

### 8.5 Ce que le thème ne doit JAMAIS faire

- Appeler quoi que ce soit de `Massifs\Ingest\Prefecture\*`. Le thème parle à #3, jamais à l'ingestion.
- Afficher `brut`, `niveau_source` ou `procedure_source`.
- Traduire lui-même un entier en libellé, couleur ou consigne.
- Rédiger à la main la chaîne d'attribution ou l'URL de la carte officielle : elles viennent de
  `Connector::attribution()`, relayées par #3.

### 8.6 Ce que #3 et #2 doivent exposer, si et quand ils existent

Détecté par `function_exists`, le connecteur leur cède alors la main :

- `massifs_niveaux_source_autorises(): array`
- `massifs_procedures_source_autorisees(): array`
- `massifs_referentiel_codes_source(): array` — codes source `'131'`…`'1327'`

**#2 doit exposer, pour chaque massif du référentiel, son code source préfecture.** Sans cette
correspondance, le garde-fou référentiel fonctionne sur l'ensemble semé mais reste déconnecté du
référentiel réel. Le rapprochement par nom est explicitement exclu : c'est un fait de domaine, pas une
heuristique de chaînes.

### 8.7 Attribution imposée par cette source

- Texte (§9 du brief, verbatim) : **« D'après les publications de la préfecture des Bouches-du-Rhône »**
- Carte officielle : `https://www.risque-prevention-incendie.fr/13`
- Bulletin PDF du jour : `https://www.risque-prevention-incendie.fr/static/13/import_data/{AAAAMMJJ}.pdf`

Le PDF est **lié, jamais récupéré ni re-servi** dans cette issue.

---

## 9. Questions ouvertes au propriétaire du projet

Aucune ne peut être devinée par un agent. Le §4.2 du brief l'interdit formellement.

**Q1 — Légende binaire contre échelle à 5 crans (bloquant pour #3 et pour le design).**
La légende publique du 13 est **binaire** pour l'accès aux massifs (autorisé / interdit), plus une
dimension ZAPEF, alors que `design-system/MASTER.md` §4.1 travaille sur 5 crans gradués. Le §4.2 du
brief impose de « reproduire exactement la légende officielle ». Reproduit-on la légende binaire réelle
— ce qui invalide 5 crans de design, le tableau de motifs et une partie du §8.1 de `MASTER.md` — ou
conserve-t-on une granularité plus fine en s'appuyant sur les valeurs `level` 1-4 du flux, dont les
libellés officiels ne sont pas publiés ? **C'est un arbitrage de produit, pas une décision d'agent.**

**Q2 — Sémantique des champs `procedure` et `zm`.**
`procedure` (0/1) valait 1 pour exactement les 7 massifs en `level` 3 le 5 août 2026. Hypothèse non
vérifiable. Que faut-il en faire : l'afficher, l'ignorer, le conserver sans l'afficher ?
`zm` (9 « zones météo ») recoupe-t-il la « Météo des forêts » de Météo-France (§4.3 du brief) ? Si oui,
le §4.3 exige que les deux notions ne soient **jamais** fusionnées visuellement — arbitrage nécessaire
avant la chaîne `meteo`.

**Q3 — Écart 27 identifiants / 25 noms.**
Le flux porte 27 identifiants ; la carte et le bulletin n'en nomment que 25. Quels sont les deux
identifiants sans nom publié, et doivent-ils apparaître sur notre site ? À croiser avec le référentiel
DDTM de la chaîne #2.

**Q4 — Consignes officielles par niveau (Q4 et Q5 de `MASTER.md` §4.1).**
Non établies : la légende officielle ne porte aucune consigne, et l'arrêté préfectoral est un PDF
numérisé sans couche de texte, donc illisible automatiquement. Faut-il une lecture humaine de cet
arrêté (fourniture d'un extrait ou d'une transcription), ou le site se limite-t-il aux deux états
d'accès sans consigne ? Le §5.2 du brief prévoit pourtant une « consigne » dans le panneau massif.

**Q5 — Libellé et date de reprise hors période.**
Le dispositif court du 1er juin au 30 septembre inclus (établi, §4.7). Hors période, la source ne
publie rien et n'affiche aucun libellé propre. Notre formulation est fixée (`MASTER.md` §11.3) mais la
date de reprise à afficher doit être confirmée chaque année : est-ce toujours le 1er juin ?

**Q6 — Autorisation de réutilisation (bloquant avant mise en production, pas avant développement).**
Aucune mention légale, aucune CGU, aucune licence, `robots.txt` en 404, `Cache-Control: no-store`.
Faut-il solliciter une autorisation explicite de la préfecture ou de l'exploitant (Entente-PôNT) avant
la mise en ligne, ou assume-t-on la réutilisation avec la seule mention de source du §9 du brief ?
Le §1 du brief précise que ce site sert de référence dans une réponse à appel d'offres public : se
présenter à un acheteur public avec une récupération contraire aux conditions d'un site préfectoral
serait un contresens commercial autant que juridique.

**Q7 — Destinataire des alertes du §4.5.**
`admin_email` suffit-il, ou faut-il une adresse dédiée ?

**Q8 — Contraste des couleurs officielles.**
`#22B14C` et `#E63A3C` sont les couleurs officielles à reproduire (§4.2 du brief), mais leur conformité
AA (§8, bloquant) n'est pas acquise. Le design system impose déjà liseré et motif obligatoires, qui
portent la conformité indépendamment de la teinte. Confirmation demandée que cette approche — teinte
officielle + liseré + motif — est bien celle retenue.

---

## 10. Re-vérification obligatoire

Ce relevé date du **11 août 2026** et décrit un dispositif **saisonnier** qui peut changer d'une année
sur l'autre. À re-vérifier **avant chaque 1er juin** :

- l'URL du flux et sa forme ;
- l'ensemble des identifiants de massif ;
- les libellés de `fr.json` ;
- les valeurs de `level` réellement émises (le `level` 4 n'a jamais été observé en 2026) ;
- l'heure de publication ;
- l'apparition éventuelle de mentions légales ou d'une licence.

Le connecteur est conçu pour **échouer bruyamment** — rejet et alerte immédiate — plutôt que
silencieusement, si l'une de ces caractéristiques change en cours de saison.
