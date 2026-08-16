# Contrôle humain au lecteur d'écran — procédure et gabarit de preuve

**Issue #18** · exigence du **§8 du brief** : « un contrôle humain final au lecteur d'écran, documenté
sur la page Accessibilité ».

> **Ce document n'est pas un résultat. C'est une procédure et un formulaire vide.**
> Aucun agent ne peut exécuter ce contrôle : il demande une personne, un lecteur d'écran réel, et un
> jugement sur ce qui est *annoncé*, pas sur ce qui est *présent dans le DOM*. Les outils automatisés
> ne s'en approchent pas — `axe-core` prouve que le nœud existe et n'est pas ignoré ; il ne dit rien de
> ce qu'une synthèse vocale en fait.
>
> **Tant que le §4 de ce document est vide, la ligne de DoD correspondante n'est pas tenue**, et la page
> « Accessibilité » doit continuer de dire que ce contrôle n'a pas eu lieu. Elle le dit aujourd'hui.

---

## 1. Ce qu'on cherche, et ce qu'on ne cherche pas

On ne cherche **pas** à repasser ce qu'axe-core a déjà couvert. On cherche les défauts qu'aucun outil
ne voit :

- un intitulé techniquement correct mais **inutilisable à l'oreille** (« lien », « bouton », « cliquez ici ») ;
- un ordre de lecture qui a du sens à l'œil et **pas au fil du flux** ;
- une information de statut **portée par la mise en page** plutôt que par le texte ;
- un changement d'état **non annoncé** (panneau ouvert, statut publié, couche activée) ;
- une **verbosité** telle que l'information utile se perd — un tableau annoncé cellule par cellule sur
  vingt-cinq lignes est conforme et inexploitable ;
- un titre **vide** ou dupliqué qui casse la navigation par titres.

---

## 2. Configuration minimale

Au moins **deux** paires parmi celles-ci, dont une sur mobile :

| Lecteur d'écran | Navigateur | Système |
|---|---|---|
| NVDA | Firefox | Windows |
| VoiceOver | Safari | macOS |
| VoiceOver | Safari | iOS |
| TalkBack | Chrome | Android |

Le site est servi sur `http://localhost:3002/` après `bash docker/up.sh`, puis
`docs/recette/importer-pages.sh` pour que les trois pages de contenu existent.

---

## 3. Le parcours — cinq pages clés du §8

Pour chaque page : parcourir **au clavier seul**, puis **par la liste des titres**, puis **par la liste
des liens**.

### 3.1 Accueil
1. Les liens d'évitement sont-ils les **premiers** éléments atteints, et mènent-ils où ils annoncent ?
2. La phrase de synthèse du jour est-elle lue **avant** la carte ?
3. La liste du jour donne-t-elle, pour chaque massif, **son nom et son statut en toutes lettres** — sans
   dépendre d'une couleur, d'un motif ni d'une position ?
4. L'indicateur de fraîcheur est-il lu, et **comprend-on de quel jour** on parle ?
5. Le bandeau de non-officialité est-il atteint ?
6. La carte est-elle **correctement ignorée ou correctement décrite** — jamais un amas de nœuds muets ?
7. À la sélection d'un massif, l'ouverture du panneau est-elle **annoncée**, et Échap rend-il le focus
   à l'élément d'origine ?

### 3.2 La démarche
1. La navigation par titres donne-t-elle un **plan utilisable** de la page ?
2. La liste des sources est-elle lue comme des **paires terme/valeur** intelligibles ?
3. Le jeton technique `simule` du connecteur est-il **compréhensible à l'oreille**, ou faut-il un
   libellé lisible côté extension ? *(dette connue — voir le contrat #18)*

### 3.3 Accessibilité
1. La distinction entre « ce qui a été vérifié » et « ce qui ne l'a pas été » **survit-elle à l'écoute** ?
2. L'adresse de signalement est-elle **atteignable et lisible** ? Elle est encodée en entités par
   `antispambot()` : vérifier qu'elle est **épelée correctement** et non lue comme une suite d'entités.

### 3.4 Connexion
1. Les champs ont-ils une **étiquette annoncée** ?
2. Une erreur de connexion est-elle **reliée au champ** et annoncée ?
3. Le verrouillage anti-force-brute est-il **annoncé**, et dit-il quoi faire ?

### 3.5 Écran gestionnaire
1. Le tableau des massifs est-il navigable **cellule par cellule avec ses en-têtes** ?
2. Le choix du niveau est-il annoncé **avec le nom du massif**, et pas seulement « autorisé / interdit » ?
3. La publication est-elle **confirmée à voix haute** ? Le compte de statuts modifiés est-il lu ?
4. Combien de gestes réels pour publier 25 massifs au clavier ? *(l'objectif du §6 est « moins d'une minute »)*

---

## 4. Gabarit de preuve — **à remplir par la personne qui exécute le contrôle**

> Ne rien pré-remplir. Une ligne non remplie signifie « non exécuté », et c'est une information exacte.

**Date :** …
**Personne :** …
**Configurations utilisées :** …
**Version du site :** commit … · pages importées le …

| Page | Configuration | Verdict | Obstacles constatés |
|---|---|---|---|
| Accueil | | | |
| La démarche | | | |
| Accessibilité | | | |
| Connexion | | | |
| Écran gestionnaire | | | |

**Verdict** : `franchi` · `franchi avec réserve` · `bloquant`.
Un « bloquant » est un obstacle qui **empêche d'obtenir l'information ou d'accomplir la tâche** — pas
une gêne.

### Obstacles à corriger

| # | Page | Description | Gravité | Suite donnée |
|---|---|---|---|---|
| | | | | |

### Ce que ce contrôle n'a pas couvert

*(à remplir aussi — un contrôle dont on ignore les angles morts se lit comme exhaustif)*

---

## 5. Après le contrôle

1. Reporter les **résultats** — et rien de plus — dans `docs/recette/contenu/accessibilite.html`,
   section « Ce qui a été vérifié », puis rejouer `docs/recette/importer-pages.sh`.
2. Déplacer la ligne correspondante hors de « Ce qui n'a pas encore été vérifié ».
3. **Ne jamais écrire un taux ni un qualificatif de conformité RGAA** — « non conforme »,
   « partiellement conforme », « totalement conforme », « x % des critères ». Ce sont des **résultats
   d'audit**, et un contrôle au lecteur d'écran n'est pas un audit. Cet interdit est opposable
   (`MASTER.md` §16).
4. Ouvrir une issue par obstacle `bloquant`.
