# Copie éditoriale des trois pages — issue #18

**Écrit le** 16 août 2026 · **État de l'arbre** : `d0887aa` (plus les fichiers non suivis de l'issue #16
et de l'issue #18) · **Statut : livrée complète, NON RATIFIÉE.**

---

## Pourquoi cette copie est ici et pas dans un gabarit

`MASTER.md` §11.3 est une **liste fermée** des phrases que le site a le droit de rédiger sur une page
publique. Le §16 en tire deux interdits nommés : la phrase « zéro cookie » **rédigée dans un gabarit**,
et tout qualificatif de conformité **écrit dans un gabarit**. Le §16 tranche aussi la forme de la sortie :
ce type de rédaction, « le jour venu, vient **du contenu, jamais du code** ».

D'où l'arbitrage **A-1** du contrat `docs/contracts/issue-18.md` : les trois gabarits portent la
structure, les chaînes servies par le serveur et les faits d'identité ; **la prose est du contenu**,
saisie en base et rendue par `the_content()`. Ces trois fichiers **sont** cette prose.

**Non ratifiée** veut dire ce que ça dit : aucune de ces phrases n'est encore entrée au §11.3. Leur
entrée est demandée à `lead-design-cms`, propriétaire de `MASTER.md` (question **Q-2** du contrat).
Précédent suivi : contrat #24, F-1 — la chaîne #23 a livré les chaînes de la 404 « non ratifiées »
plutôt que de trancher seule.

---

## Les trois pages

| Fichier | Titre (`post_title`) | Slug (`post_name`) | Gabarit (`_wp_page_template`) |
|---|---|---|---|
| `la-demarche.html` | La démarche | `la-demarche` | `templates/page-la-demarche.php` |
| `accessibilite.html` | Accessibilité | `accessibilite` | `templates/page-accessibilite.php` |
| `mentions-legales.html` | Mentions légales | `mentions-legales` | `templates/page-mentions-legales.php` |

Les titres viennent du **§5.1 du brief**, mot pour mot. Les slugs en sont la translittération sans
accent ; ils sont fixés **ici et dans `../importer-pages.sh`**, et nulle part ailleurs — aucun gabarit,
aucun lien de pied ne les connaît (contrat §6 : « aucun slug ni libellé inventé », les trois entrées
sont des **entrées de menu** affectées à l'emplacement `pied`).

**Ordre retenu dans le menu de pied** : La démarche, Accessibilité, Mentions légales — l'ordre du §5.1
du brief. `MASTER.md` §7.3 énumère les trois entrées dans l'autre sens ; c'est une énumération en prose,
pas une prescription d'ordre. L'ordre d'insertion du script fait foi et se change sans toucher au code.

---

## Les trois `<meta name="description">`

Elles sont **fournies par l'appelant**, c'est-à-dire par le gabarit lui-même (contrat §5.3) : elles ne
sont **pas** stockées en base et l'import ne les écrit pas. Elles sont proposées ici parce qu'elles sont
de la rédaction, et que la rédaction est mon travail — mais **le gabarit est la source de vérité** ; si
`templates/page-*.php` en porte une autre, c'est la sienne qui est servie, et c'est ce fichier-ci qu'il
faut corriger.

| Page | Description proposée |
|---|---|
| La démarche | `Pourquoi ce site relaie l'accès du jour aux massifs forestiers des Bouches-du-Rhône, comment il fonctionne, ses sources, ses limites et son format ouvert.` |
| Accessibilité | `La démarche d'accessibilité suivie sur ce site, les vérifications déjà menées, celles qui restent à mener, et comment signaler un obstacle.` |
| Mentions légales | `Éditeur, directeur de publication, hébergement, données personnelles, sources et licences des données relayées par ce site.` |

Aucune ne dépasse 160 caractères, aucune ne reprend une chaîne officielle, aucune n'annonce un statut.

---

## Règles tenues dans la copie — opposables en revue

**Structure**

- **Aucun `h1` dans le corps.** Le `h1` est le titre de la page, rendu par le gabarit. La hiérarchie du
  contenu commence à `h2` (contrat #24, F-6).
- **Blocs plats uniquement** : `wp:paragraph`, `wp:heading`, `wp:list` (avec ses `wp:list-item`).
  Aucun `wp:group`, `wp:columns`, `wp:cover` — un bloc conteneur enveloppe ses enfants dans une `<div>`
  et leur fait perdre **en silence** la mesure de 68ch et le rythme vertical, que `layout.css` accroche
  aux **enfants directs** de `.bande__contenu` (l. 134, 138, 143).
- **Aucun `wp:table`.** Décision assumée, pas un oubli : `wp-block-library` est retiré du front
  (`functions.php` l. 297-311) et **aucune feuille du thème ne style un tableau de contenu**. Un
  `<figure class="wp-block-table">` sortirait donc sans bordure ni séparation de cellules, et
  franchirait la largeur à 360 px — deux régressions visibles pour un gain nul. `MASTER.md` §7.3 réserve
  de toute façon le tableau de sources et de licences au **gabarit**, qui le rend depuis les fonctions
  de lecture. Ce que la copie aurait mis en tableau est écrit en paragraphes titrés.
- **Ancres de titre uniques, aucun lien `#…`, aucun lien interne.** Chaque `h2`/`h3` porte une ancre —
  la convention canonique du bloc `core/heading`, `class="wp-block-heading" id="…"`. Les quatorze
  ancres des trois pages sont **uniques par page** et **aucune ne vaut `contenu-principal`** : celle-là
  appartient au `<main>` de l'en-tête, et la doubler casserait le lien d'évitement. Aucun `href="#…"`
  n'existe dans la copie, donc aucun lien mort. Les renvois d'une page à l'autre se font **par leur
  nom**, en toutes lettres, jamais par un `href` — un slug écrit en dur dans la base survivrait à un
  changement de slug et pointerait sur une 404.
- **Aucun contenu protégé par mot de passe** : le formulaire du cœur pose un cookie `wp-postpass_`,
  contre le zéro-cookie public des §2 et §9 du brief.

**Rédaction**

- Voix active, sujet explicite. Aucun superlatif, aucune exclamation, aucun emoji. Jamais « Valider »,
  « OK », « En savoir plus », « Oups », « Désolé ».
- **Aucune promesse d'officialité** : le site « relaie », « reprend », « d'après ». Il ne « garantit »
  jamais, et ne se déclare jamais officiel.
- **Vocabulaire fixe du §11.2 tenu** : massif, niveau, ZAPEF, statut, consigne, fraîcheur, dispositif,
  jour de validité, carte officielle, zone parcourue par le feu, danger météo, gestionnaire, publier.
  Aucune occurrence de zone/secteur/forêt pour massif, de « zone brûlée », d'« accès réglementé », de
  « vigilance orange », de « niveau 3 » ni de « risque sévère » — aucun de ces termes n'existe dans le
  dispositif des Bouches-du-Rhône, qui a exactement **deux** états d'accès.
- **Aucun libellé officiel n'est reproduit dans la copie.** Les libellés de niveau et les consignes
  appartiennent au §11.4 et à l'extension ; les écrire ici les figerait dans la base, où une révision de
  la légende ne les atteindrait pas. La copie parle des états d'accès **sans les nommer**.
- **Aucune date, aucun décompte, aucune heure de publication composés par la copie**, sauf la date des
  vérifications d'accessibilité — qui est la date d'un fait daté, pas une donnée du domaine, et qui est
  écrite en français long. En particulier : ni les bornes du dispositif (elles sont publiées par la
  préfecture et portent un drapeau « confirmée »), ni l'heure de publication du soir (le brief §4.2 dit
  18-19 h, `MASTER.md` §11.3 dit « vers 17 h » — cette divergence ne m'appartient pas et la copie ne la
  tranche pas), ni le nombre de massifs.
- **Aucune adresse externe écrite en dur**, en particulier pas celle de la carte officielle ni celle du
  bulletin : elles voyagent dans les réponses du serveur et sont liées là où il les fournit
  (`README` du point d'accès public, §8, règle 7). La copie nomme la carte officielle, elle ne la lie
  pas.
- **Aucun taux ni qualificatif de conformité** : ni « non conforme », ni « partiellement conforme », ni
  « x % des critères ». Aucun audit n'a été mené ; ces qualificatifs sont eux-mêmes des résultats
  d'audit. La page « Accessibilité » écrit à la place **ce qui a été mesuré** et **ce qui ne l'a pas
  été**.
- **Aucune licence déclarée pour notre agrégat.** La question est ouverte (contrat #8, Q1 ; contrat #18,
  Q-1). Les deux pages qui en parlent documentent le **format** et disent que la licence n'est pas
  arrêtée. C'est un fait, pas un oubli — et surtout pas une invention par déduction.

**Ce que la copie ne redit pas, parce que le gabarit le rend**

- Les cinq attributions (périmètres, statuts, fond de carte, zones parcourues par le feu, danger météo)
  et leurs `faits` : elles viennent des fonctions de lecture de l'extension, se rendent **entières** et
  ne se reformulent pas (contrat §1.1). La copie écrit **l'encadrement** de ces blocs, jamais une
  phrase d'attribution.
- L'éditeur, le directeur de publication, l'adresse de contact et l'état de l'hébergement : fournis par
  le propriétaire du projet, déclarés **une seule fois** dans le gabarit (contrat §4). La copie les
  désigne (« l'adresse de contact indiquée sur cette page ») et ne les recopie pas.
- Le bandeau de non-officialité du §5.6 : il est obligatoire « sur toute page affichant un statut », et
  aucune de ces trois pages n'en affiche. La copie ne le reproduit pas — ce serait une phrase de statut
  sur une page sans statut (contrat §3, corollaire). Elle dit la non-officialité **avec ses propres
  mots**, une fois par page où c'est utile.

---

## Là où j'ai senti la pression d'inventer, et ce que j'ai fait à la place

| Tentation | Ce que j'ai écrit |
|---|---|
| Donner l'heure de publication du soir | Rien. Le brief et `MASTER.md` divergent (18-19 h contre « vers 17 h »). « chaque soir » suffit et n'invente rien |
| Donner les bornes du dispositif (1<sup>er</sup> juin – 30 septembre) | Rien. La réponse du serveur porte un drapeau `confirmee` sur ces bornes ; les figer en prose reviendrait à les présenter comme confirmées |
| Donner le nombre de massifs | Rien. C'est une donnée du référentiel, servie par le serveur |
| Nommer les deux états d'accès | Rien. Ce sont des libellés officiels du §11.4, propriété de la préfecture, servis par l'extension |
| Écrire l'adresse de la carte officielle | Rien. Elle est liée là où le serveur la fournit |
| Écrire l'adresse de contact dans la page « Accessibilité » | Un renvoi (« l'adresse de contact figure sur cette page »). Le contact est déclaré **une seule fois**, dans le gabarit (contrat §4) — c'est aussi ce qui permettra de le remplacer par une adresse dédiée en un seul endroit |
| Écrire « conforme », « partiellement conforme » ou un taux | Ce qui a été mesuré, daté, et la liste de ce qui ne l'a pas été |
| Déclarer une licence pour notre agrégat | Que la question n'est pas tranchée et qu'aucune licence n'est déclarée |
| Nommer un hébergeur | Rien. Le gabarit dit « en sommeil » en toutes lettres, avec renvoi à `docs/decisions/portee-non-publiee.md` |

---

## Les seuls faits techniques affirmés par la copie, et leur vérification

La copie évite d'affirmer des faits de domaine. Elle en affirme trois de nature technique, tous
vérifiés dans l'arbre le 16 août 2026 — aucun n'est déduit :

| Affirmation (mentions légales) | Vérification |
|---|---|
| Les deux polices sont sous SIL Open Font License 1.1 | `assets/fonts/LICENSE-atkinson-hyperlegible-next.txt` et `LICENSE-big-shoulders-display.txt` portent le texte OFL 1.1 ; `fonts.css` le redit en tête de chaque famille |
| La bibliothèque cartographique est sous licence BSD à deux clauses | `assets/vendor/leaflet/LICENSE` : « BSD 2-Clause License », Leaflet 1.9.4 |
| Le thème et l'extension sont sous GPL v2 ou ultérieure | En-tête de licence des fichiers du thème et de l'extension, et `CLAUDE.md` |

Les deux polices sont bien **deux fichiers** et non davantage (budget §10 du brief saturé exactement) :
`assets/fonts/` ne contient que `atkinson-hyperlegible-next-var.woff2` et
`big-shoulders-display-var.woff2`.

---

## Vérifier la copie après import

```bash
# La page rend-elle bien un seul h1, et le corps commence-t-il à h2 ?
curl -s http://localhost:3002/la-demarche/ | grep -o '<h[1-3][^>]*>' 

# Aucune origine externe en sous-ressource (les <a> vers la carte officielle
# et vers openstreetmap.org sont des LIENS, pas des requêtes) :
curl -s http://localhost:3002/la-demarche/ \
  | grep -oE '(src|href)="https?://[^"]+"' | grep -v 'localhost:3002'
```

La marche à suivre complète, et ce que chaque commande prouve, sont dans `../releves/README.md`.
