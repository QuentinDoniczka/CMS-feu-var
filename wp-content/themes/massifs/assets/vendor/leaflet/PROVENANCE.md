# Provenance — Leaflet 1.9.4 vendorisé

Contrainte non négociable n° 2 du projet : **zéro requête navigateur vers un domaine tiers**. La
bibliothèque cartographique est donc servie depuis notre domaine, et ce fichier consigne d'où elle vient
et en quoi ce qui est servi diffère de ce qui a été récupéré.

- **Bibliothèque** : Leaflet
- **Version** : 1.9.4
- **Licence** : BSD 2-Clause — texte intégral dans `LICENSE`, à côté de ce fichier
- **Date de récupération** : 13 août 2026
- **Contrat d'interface** : `docs/contracts/issue-7.md` §10

---

## 1. Fichiers, amont et servi

| Fichier | URL amont | sha256 **amont** | sha256 **servi** | Octets servis |
|---|---|---|---|---|
| `leaflet.js` | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js` | `db49d009c841f5ca34a888c96511ae936fd9f5533e90d8b2c4d57596f4e5641a` | `dc71f8a6880bc3ca1bd9fa8dc5f1af48c702dc510b0a78240a07c5feed7ce935` | 147 517 |
| `leaflet.css` | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css` | `a7837102824184820dfa198d1ebcd109ff6d0ff9a2672a074b9a1b4d147d04c6` | `337bfca5cabd03b39815b2700febe2b3b7edf55921c59cd49f88ecb328212303` | 14 145 |
| `LICENSE` | `https://unpkg.com/leaflet@1.9.4/LICENSE` | `53e8dc25862014e4324741ca18fbe3611e11d42ef69f59f86ea8c5389647d4cb` | `bbc959aaef7488b38942e7f05cfd1244351f8bce54261951ea17dd7cf60f894b` | 1 369 |

**Ces deux lignes ont longtemps porté « identique » et les octets de l'amont (14 806 et 1 395).
C'était faux, et depuis l'origine.** Ces deux fichiers arrivent de l'amont en CRLF ; leurs CR ont quitté
le blob au moment du commit, sous `core.autocrlf=true`. Le contenu **versionné** n'a donc jamais été
identique à l'amont — 661 octets d'écart pour `leaflet.css`, 26 pour `LICENSE`, soit exactement un CR par
ligne. L'affirmation n'était vraie que par accident, sur une copie de travail Windows où git restituait
les CR au checkout ; elle était fausse sur tout clone Linux. La renormalisation de l'issue #80 a rendu la
copie de travail déterministe, donc l'écart visible partout. Voir
[`docs/decisions/generalisation-des-fins-de-ligne.md`](../../../../../../docs/decisions/generalisation-des-fins-de-ligne.md) §6.2.

**Ne pas « réparer » cet écart en re-vendorisant l'amont** : cela réintroduirait des CR dans des fichiers
servis, et l'identité amont n'est de toute façon plus récupérable — les CR ont quitté l'historique.
Vérifier contre la colonne « servi », jamais contre la colonne « amont ».

**Deux empreintes et non une, pour `leaflet.js`.** Le fichier servi diffère de l'amont : une empreinte
unique serait invérifiable, puisqu'elle ne correspondrait à aucun des deux états du fichier. Les deux sont
donc écrites, et l'écart est décrit au §2.

**Contre-vérification d'origine.** Les trois fichiers ont été récupérés sur `unpkg.com` puis re-téléchargés
sur `cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/` : `leaflet.js` et `leaflet.css` y donnent **le même
sha256**. Le sha512 de `leaflet.js` amont vaut
`BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==`, qui est
l'empreinte d'intégrité (SRI) publiée pour cette version.

---

## 2. Écart entre l'amont et le servi — une seule ligne, et pourquoi

`leaflet.js` amont se termine par :

```
…window.L=t});
//# sourceMappingURL=leaflet.js.map
```

**La dernière ligne est retirée**, et rien d'autre. Le fichier servi se termine par `});` suivi d'un saut
de ligne. Écart mesuré : **36 octets retirés, 1 octet ajouté** (le saut de ligne final), soit
147 552 → 147 517 octets. **Aucun autre octet du fichier n'est touché** : ni minification, ni reformatage,
ni correctif, ni ajout d'en-tête.

**Motif.** Nous ne vendorisons **aucun `.map`**. Laissée en place, cette ligne fait partir une requête vers
`leaflet.js.map`, fichier absent, dès qu'un visiteur ouvre les outils de développement : une 404 sur notre
domaine, et une assertion de recette (« zéro requête inattendue ») rendue illisible par du bruit.

**`leaflet.css` n'est jamais édité.** Toute modification de son contenu est un défaut. Seule exception,
subie et non choisie : la conversion de ses fins de ligne en LF à l'entrée dans git (§1). Le fichier servi
fait donc autorité sur l'amont, et c'est son empreinte « servi » qu'il faut vérifier.

---

## 3. Aucune image vendorisée — la preuve est par les API non appelées

`leaflet.css` porte trois références d'image, laissées telles quelles :

| Ligne | Référence | Règle qui la porte | Instanciée par le thème ? |
|---|---|---|---|
| `url(images/layers.png)` | icône du sélecteur de couches | `.leaflet-control-layers-toggle` | non |
| `url(images/layers-2x.png)` | idem, écrans à haute densité | `.leaflet-retina .leaflet-control-layers-toggle` | non |
| `url(images/marker-icon.png)` | icône de marqueur par défaut | `.leaflet-default-icon-path` | non |

Une `url()` de feuille de style ne déclenche une requête que si un élément correspondant au sélecteur
existe dans le document. **Aucun de ces trois sélecteurs ne peut apparaître** : `assets/js/carte/carte.js`
n'instancie **ni `L.Control.Layers`, ni `L.Marker`, ni `L.Icon`, ni `L.Icon.Default`, ni `L.Marker.Default`**
— les seules API Leaflet employées sont `L.map`, `L.svg`, `L.geoJSON`, `L.tileLayer` et
`L.latLngBounds`. Le contrôle de couches n'est jamais construit, aucun marqueur n'est jamais posé, et
`attributionControl: false` retire le seul contrôle qu'une carte Leaflet crée d'office.

C'est la raison pour laquelle **le CSS n'est pas fourché pour trois règles inatteignables** : fourcher un
CSS vendorisé crée une dette de maintenance permanente et une divergence à chaque montée de version, pour
supprimer des règles que le navigateur n'atteint jamais. La preuve est structurelle (les API ne sont pas
appelées) et elle est **vérifiée en recette** par l'assertion « zéro requête vers
`/assets/vendor/leaflet/images/` » (`docs/contracts/issue-7.md` §12, assertion 1).

`url(#default#VML)` — quatrième `url()` du fichier — est une référence de comportement Internet Explorer 8,
interne au document. Elle ne déclenche aucune requête réseau, sur aucun navigateur.

---

## 4. Structure du répertoire — à plat, quatre fichiers

```
assets/vendor/leaflet/
├── leaflet.js       — 1.9.4 dist minifié, ligne sourceMappingURL retirée
├── leaflet.css      — 1.9.4 dist, contenu inchangé, fins de ligne en LF (§1)
├── LICENSE          — BSD 2-Clause, contenu inchangé, fins de ligne en LF (§1)
└── PROVENANCE.md    — ce fichier
```

**Aucun répertoire `dist/`, `build/`, `includes/` ou `node_modules/` sous `vendor/`**, et aucun `.map`
(`docs/contracts/issue-30.md` §3.7, legué à cette chaîne). La protection est **structurelle** : elle ne
dépend pas de `docker/plugins-guard.conf`, qui vit dans `docker/` et n'existe pas en production o2switch.

---

## 5. Mise à jour de version

1. Récupérer `dist/leaflet.js`, `dist/leaflet.css` et `LICENSE` de la nouvelle version, depuis `unpkg.com`,
   et contre-vérifier le sha256 sur une seconde origine.
2. Retirer la dernière ligne `//# sourceMappingURL=` de `leaflet.js`, et **rien d'autre**.
3. Réécrire le tableau du §1 : **six empreintes**, une « amont » et une « servi » pour chacun des trois
   fichiers, plus la nouvelle date.
   **L'empreinte « servi » se calcule après `git add` et checkout, jamais sur le fichier téléchargé.**
   `.gitattributes` porte `* text=auto eol=lf` : `leaflet.css` et `LICENSE`, qui arrivent de l'amont en
   CRLF, perdront leurs CR à l'entrée dans git. Hacher le téléchargement et l'inscrire en colonne
   « servi » recréerait très exactement l'incohérence corrigée au §1 — c'est la manière dont elle est née.
   `leaflet.js`, lui, arrive en LF et n'est pas concerné.
4. Revérifier le §3 : si la nouvelle version instancie une image par une API que nous appelons, la preuve
   par les API non appelées tombe et il faut alors traiter le cas explicitement.
5. Rejouer les assertions de recette 1, 2 et 6 du §12 de `docs/contracts/issue-7.md`.
