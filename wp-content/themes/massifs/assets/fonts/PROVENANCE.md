# Provenance des fontes auto-hébergées — thème `massifs`

Ce répertoire est **autosuffisant** : les deux `.woff2`, leurs licences, leurs déclarations
`@font-face` (`./fonts.css`) et le présent relevé y vivent ensemble. Aucun fichier de police n'est
servi ailleurs, et jamais depuis un CDN — contrainte n° 2 du projet, **zéro requête navigateur vers
un domaine tiers**. Le téléchargement documenté ci-dessous a eu lieu **au build**, une fois ; rien
dans les fichiers livrés ne pointe vers `fonts.gstatic.com`.

**Budget dur : 2 fichiers de police, saturé exactement.** Toute troisième face est un défaut.

Décisions applicables : **D-20** (sous-ensemble `latin` seul), **D-21** (`@font-face` uniquement
dans `./fonts.css`), **D-22** (`font-display: optional` + preload obligatoire), **A-3** (aucun
descripteur de métriques), **D-25** (la flèche `→` est du SVG en ligne), **A-6** (`tabular-nums`
inopérant sur la face de titrage).

---

## Big Shoulders Display

| Champ | Valeur |
|---|---|
| Famille | Big Shoulders Display |
| Version amont | **2.002** (`name` ID 5 : `Version 2.002` ; `head.fontRevision` 2.00199890…) |
| Rôle | Titrage — MASTER.md §5 |
| URL CSS2 interrogée | `https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500..800&display=swap` |
| User-Agent employé | `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36` |
| URL gstatic résolue (verbatim) | `https://fonts.gstatic.com/s/bigshouldersdisplay/v24/fC1_PZJEZG-e9gHhdI4-NBbfd2ys3SjJCx1czNDu.woff2` |
| Fichier local | `big-shoulders-display-var.woff2` |
| Taille | **35 436 octets** |
| sha256 | `698f9960927e7b5a0c38e5bf388cd0a6e42d855062fe70366cf5057664e39401` |
| Date de récupération | 12 août 2026 |
| Licence | SIL Open Font License 1.1 — `./LICENSE-big-shoulders-display.txt` (récupérée le 12 août 2026 depuis `https://raw.githubusercontent.com/google/fonts/main/ofl/bigshouldersdisplay/OFL.txt`, 4 396 o, verbatim) |

**Mention de droit d'auteur à préserver** (première ligne de l'OFL, reproduite verbatim comme
l'article 1 de l'OFL 1.1 l'exige) :

```
Copyright 2019 The Big Shoulders Project Authors (https://github.com/xotypeco/big_shoulders)
```

**Reserved Font Name : aucun.** La ligne de copyright ne porte pas la clause
« with Reserved Font Name » ; la seule occurrence de l'expression dans le fichier de licence est la
définition générique de l'article « Definitions ». Le renommage n'est donc pas contraint.

### Relevés de vérification

| Mesure | Valeur relevée |
|---|---|
| `unitsPerEm` | 2000 |
| axes `fvar` | `wght` 100 → 900, **défaut 100** |
| `fsSelection` bit 7 (`USE_TYPO_METRICS`) | **vrai** (`fsSelection` = 0x00C0) |
| `hhea` asc / desc / gap | 1968 / −426 / 0 |
| `OS/2` sTypo asc / desc / gap | 1968 / −426 / 0 |
| `OS/2` usWin asc / desc | 2614 / 730 |
| `sCapHeight` | 1600 (0,800 em) |
| `sxHeight` | 1200 (0,600 em) |
| features `GSUB` | `ccmp dnom frac liga locl numr` |
| features `GPOS` | `kern mark mkmk` |
| `tnum` | **ABSENT** |
| U+2192 `→` | **ABSENT** |
| bloc U+00C0–U+00FF | **complet, 64/64** |
| U+00A0, U+2019 `’`, `*`, `«` `»`, `–`, `—`, `…`, `°`, `œ`, `Œ`, `æ`, `Æ` | **tous présents** |

**Verdict : sous-ensemble `latin` seul, conforme D-20.** `unicode-range` du bloc retenu :
`U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329,
U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD`.

---

## Atkinson Hyperlegible Next

| Champ | Valeur |
|---|---|
| Famille | Atkinson Hyperlegible Next |
| Version amont | **2.001** (`name` ID 5 : `Version 2.001` ; `head.fontRevision` 2.00100708…) |
| Rôle | Labeur — MASTER.md §5 |
| URL CSS2 interrogée | `https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400..700&display=swap` |
| User-Agent employé | `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36` |
| URL gstatic résolue (verbatim) | `https://fonts.gstatic.com/s/atkinsonhyperlegiblenext/v7/NaPNcYPdHfdVxJw0IfIP0lvYFqijb-UxCtm5_wdGseiJn3o.woff2` |
| Fichier local | `atkinson-hyperlegible-next-var.woff2` |
| Taille | **33 996 octets** |
| sha256 | `18b2a1a39a2fa298b0ba5390aca68462669826c90925656f1c1f6796e0e1bbaf` |
| Date de récupération | 12 août 2026 |
| Licence | SIL Open Font License 1.1 — `./LICENSE-atkinson-hyperlegible-next.txt` (récupérée le 12 août 2026 depuis `https://raw.githubusercontent.com/google/fonts/main/ofl/atkinsonhyperlegiblenext/OFL.txt`, 4 431 o, verbatim) |

**Mention de droit d'auteur à préserver** (première ligne de l'OFL, verbatim) :

```
Copyright 2020-2024 The Atkinson Hyperlegible Next Project Authors (https://github.com/googlefonts/atkinson-hyperlegible-next)
```

**Reserved Font Name : aucun.** Même constat que ci-dessus.

### Relevés de vérification

| Mesure | Valeur relevée |
|---|---|
| `unitsPerEm` | 1000 |
| axes `fvar` | `wght` 200 → 800, défaut 400 |
| `fsSelection` bit 7 (`USE_TYPO_METRICS`) | **vrai** (`fsSelection` = 0x00C0) |
| `hhea` asc / desc / gap | 984 / −316 / 0 |
| `OS/2` sTypo asc / desc / gap | 984 / −316 / 0 |
| `OS/2` usWin asc / desc | 996 / 411 |
| `sCapHeight` | 668 (0,668 em) |
| `sxHeight` | 496 (0,496 em) |
| features `GSUB` | `ccmp frac locl pnum tnum` |
| features `GPOS` | `kern mark mkmk` |
| `tnum` | **présent** |
| U+2192 `→` | **ABSENT** |
| bloc U+00C0–U+00FF | **complet, 64/64** |
| U+00A0, U+2019 `’`, `*`, `«` `»`, `–`, `—`, `…`, `°`, `œ`, `Œ`, `æ`, `Æ` | **tous présents** |

**Verdict : sous-ensemble `latin` seul, conforme D-20.** Même `unicode-range` que la face de
titrage.

---

## Constats pour les chaînes d'intégration

### 1. `tabular-nums` est un no-op silencieux sur la face de titrage (A-6)

Big Shoulders Display **n'expose pas la feature `tnum`** et ses chiffres sont **fortement
proportionnels**. Relevé sur l'instance `wght 800`, en unités d'em (`unitsPerEm` = 2000) :

| chiffre | 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 |
|---|---|---|---|---|---|---|---|---|---|---|
| chasse | 947 | **511** | 914 | 941 | 959 | **961** | 931 | 893 | 933 | 931 |

Amplitude **450 / 2000 em = 0,225 em**, soit environ **29 px à `--fs-800`** dans sa borne haute
(8 rem = 128 px). `font-variant-numeric: tabular-nums` **ne corrige rien** : sans feature `tnum`, la
déclaration est ignorée sans erreur. Le chiffre du jour de MASTER.md §8.2 doit donc voir sa
**largeur réservée par la mise en page** ; il ne peut pas être stabilisé par la typographie.

Atkinson Hyperlegible Next, elle, expose bien `tnum` (relevé `wght 700` sans la feature : amplitude
440 → 659, soit 0,219 em ; la feature la ramène à zéro).

### 2. `→` n'existe dans aucune des deux polices (D-25)

U+2192 est absent des deux `cmap`. Un caractère `→` posé en HTML afficherait un rectangle de
substitution, ou serait rendu par une police système imprévisible. La flèche de MASTER.md §7.2 est
du **SVG en ligne**, jamais un caractère.

### 3. `font-display: optional` exige le preload (D-22)

Sans `<link rel="preload" as="font" type="font/woff2" crossorigin>` sur les **deux** fichiers dans
`wp_head`, `optional` fait tomber le premier affichage en police système et D-22 perd tout son
intérêt. `crossorigin` est obligatoire **même en même origine** : l'omettre provoque un double
téléchargement.

### 4. Aucun descripteur de métriques n'est fourni, délibérément (A-3)

Ni `size-adjust`, ni `ascent-override`, `descent-override`, `line-gap-override`. La composition doit
donc être contrôlée **polices désactivées** (repli non condensé : `Arial Narrow` puis `sans-serif`
au titrage, `system-ui` au texte), à 360 px et à 200 % de zoom.

### 5. Servir les fichiers correctement

`Content-Type: font/woff2` · `Cache-Control: public, max-age=31536000, immutable` ·
**ne pas recompresser les `.woff2`** : ils sont déjà compressés en Brotli en interne, une couche
gzip/Brotli supplémentaire les alourdit.

---

## Procédure de renouvellement

À rejouer intégralement à chaque mise à jour amont — **ne jamais remplacer un `.woff2` sans
remettre à jour ce fichier**.

1. **Interroger l'API CSS2 avec un User-Agent Chrome de bureau.** Sans lui, Google renvoie du
   **TTF**, pas du woff2 :

   ```sh
   UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
   curl -sS -A "$UA" "https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500..800&display=swap"
   curl -sS -A "$UA" "https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400..700&display=swap"
   ```

2. **Extraire le bloc par le marqueur de commentaire `/* latin */`**, jamais par un index de
   position. Découper la réponse sur le commentaire `/* latin */` et prendre la **première**
   `url(https://fonts.gstatic.com/…woff2)` qui le suit. **L'ordre des sous-ensembles dans la réponse
   n'est pas contractuel** : il a déjà changé entre familles (Big Shoulders sort `vietnamese`,
   `latin-ext`, `latin` ; Atkinson sort `latin-ext`, `latin`). Un index positionnel livrerait
   silencieusement le mauvais sous-ensemble.

3. **Contrôle bloquant avant téléchargement** : l'`unicode-range` du bloc retenu doit **commencer
   par `U+0000-00FF`**. Si ce n'est pas le cas, c'est `latin-ext` (`U+0100-02BA…`) ou un autre
   sous-ensemble — **arrêter et signaler**, ne pas télécharger.

4. **Contrôle bloquant après téléchargement** : comparer taille et sha256 aux valeurs consignées
   ci-dessus. Une taille de l'ordre de plusieurs centaines de kilo-octets signale une réponse TTF.
   Un écart de hachage à taille identique signale une reconstruction amont : **signaler, ne pas
   accepter en silence**.

5. **Rejouer les relevés de vérification** des deux tableaux (fontTools) et, en particulier,
   reconfirmer : bloc U+00C0–U+00FF complet, `’` et `*` présents (la note ZAPEF officielle en
   dépend), `tnum` toujours absent de Big Shoulders, `→` toujours absent des deux. Consigner tout
   écart ici avant de livrer.

6. **Ne jamais ajouter `latin-ext`.** Vérifié sur la `cmap` réelle : tous les glyphes français
   utiles sont dans `latin` ; `latin-ext` ne commence qu'à `U+0100`. Le prendre porterait le compte
   à **4 fichiers contre un budget dur de 2**, pour zéro glyphe utile (D-20).

7. **Mettre à jour les sha256 courts** des commentaires d'en-tête de `./fonts.css`.
