# Administration courante — MASSIFS

**Livrable du §11 du brief** : « une documentation d'administration courte : mettre à jour les statuts,
gérer un compte gestionnaire, comprendre les alertes. »

Courte, donc. Pour le détail d'un écran, l'écran lui-même fait foi.

---

## 1. Mettre à jour les statuts — le geste quotidien

**Qui** : un compte de rôle **gestionnaire**, ou un administrateur.
**Où** : menu **Massifs → Publier les statuts**.

1. L'écran liste les massifs, avec le statut actuel de chacun.
2. Choisir le jour : **aujourd'hui** (correction) ou **demain** (le cas normal — la préfecture publie
   le statut du lendemain la veille au soir ; l'heure exacte n'est pas écrite ici, le brief §4.2 et le
   design system ne disent pas la même, et l'écran affiche celle que le domaine sert).
3. Choisir un niveau par massif. Au clavier : `Tab` d'un massif au suivant, flèches pour le niveau.
4. **Publier les statuts** — un seul bouton, en bas.
5. Un récapitulatif confirme ce qui a été enregistré et combien de statuts ont changé.

Le site public reflète la publication en moins d'une minute.

**Ce qu'il ne faut pas faire** : republier « pour être sûr ». Chaque publication est journalisée ; une
republication à l'identique ajoute une ligne d'historique sans rien changer, et brouille la lecture du
journal.

**Si un massif n'a pas de statut pour le jour demandé**, le site affiche « information non disponible »
et renvoie à la carte officielle. C'est le comportement voulu : il ne faut **jamais** saisir une valeur
plausible pour combler un trou. Un statut inventé est plus dangereux qu'une absence de statut.

---

## 2. Consulter l'historique

**Où** : menu **Massifs → Historique**.

Toute écriture y figure : qui, quoi, quand, ancienne et nouvelle valeur. Filtres par massif, période,
auteur et source. Export CSV depuis le même écran.

C'est la vue à ouvrir en premier quand quelqu'un demande « pourquoi ce massif affichait-il ça hier ».

---

## 3. Gérer un compte gestionnaire

**Qui** : administrateur uniquement.

Les trois actions existent, et ce sont celles promises au client :

Tout se passe sur l'écran **Utilisateurs** du cœur — il n'y a **pas** d'écran dédié, et c'est un choix :
la liste des utilisateurs porte une colonne **Portail**, des actions au survol de chaque ligne, et les
mêmes actions en **actions groupées**. Rien de tout cela n'est sur la fiche d'un utilisateur.

| Action | Où | Effet |
|---|---|---|
| **Créer** | Utilisateurs → Ajouter, rôle **Gestionnaire des massifs** | accès aux seuls écrans de statuts et d'historique |
| **Suspendre** | Utilisateurs → action de ligne, ou action groupée | le compte existe, ses sessions vivantes sont révoquées, il ne peut plus se connecter |
| **Rétablir** | Utilisateurs → action de ligne, ou action groupée | rend au compte suspendu ses droits, sans le recréer |
| **Réinitialiser le mot de passe** | Utilisateurs → action de ligne, ou action groupée | lien de réinitialisation envoyé |

La colonne **Portail** dit l'état de chaque compte : c'est là qu'on lit qu'un compte est suspendu, et
pas ailleurs. Un compte suspendu reste visible dans la liste — c'est voulu, un compte invisible se
recrée par erreur.

Le rôle **gestionnaire** ne voit ni les contenus, ni les réglages, ni les extensions, ni les
utilisateurs. C'est délibéré, et c'est éprouvé par les tests d'intégration : douze écrans du cœur lui
sont refusés même par URL directe.

**La double authentification est active pour les administrateurs.** Ne pas la désactiver pour se
dépanner : c'est une exigence du §6, et une ligne de la recette.

**Suspendre plutôt que supprimer.** Supprimer un compte casse la lisibilité de l'historique, qui
référence son auteur.

---

## 4. Comprendre les alertes

Les alertes arrivent par courriel à l'administrateur du site.

| Message | Ce qu'il veut dire | Quoi faire |
|---|---|---|
| **Échec de récupération** d'une source | Toutes les tentatives de la journée ont échoué | Vérifier que la source publie. Le site continue sur la dernière donnée connue, avec son indicateur de fraîcheur |
| **Donnée périmée** | Plus de 24 h sans donnée fraîche, en période d'activité | Une bannière est déjà affichée aux visiteurs. Saisir les statuts **manuellement** via le portail si la source reste muette |
| **Charge aberrante refusée** | Une source a répondu, mais avec des valeurs hors des bornes admises | Rien à faire dans l'urgence : le refus est le comportement correct. La donnée douteuse n'a **pas** été enregistrée |
| **Couche des zones de feu indisponible** | EFFIS ne répond pas | La couche disparaît proprement du site. Aucune action |

**Le principe à retenir** : aucune de ces alertes ne provoque l'affichage d'une donnée fausse. Le pire
qui arrive est que le site dise « information non disponible » et renvoie à la carte officielle. Une
alerte est une invitation à saisir manuellement, jamais une urgence à contourner.

---

## 5. Hors saison

Hors de la période du dispositif préfectoral, le site affiche « dispositif estival inactif » et n'attend
aucune saisie. Rien à faire.

Les bornes ne sont **pas** écrites ici, et surtout pas déduites de mémoire : elles viennent du domaine,
qui les sert avec un drapeau disant si la préfecture les a **confirmées** pour l'année en cours. Les
recopier dans une documentation les figerait, et une borne figée devient fausse en silence l'année où
elle bouge.

---

## 6. Les trois pages éditoriales

« La démarche », « Accessibilité » et « Mentions légales » sont des **pages WordPress ordinaires** : on
les modifie dans l'éditeur, comme n'importe quelle page.

Deux règles de rédaction, opposables :

1. **Blocs plats uniquement** — paragraphe, titre, liste, tableau. Un bloc conteneur (groupe, colonnes,
   bannière) enveloppe ses enfants dans une `<div>` et leur fait perdre la largeur de lecture et le
   rythme vertical **en silence** : la page reste correcte à la relecture et fausse à l'écran.
2. **Jamais de `h1` dans le corps** — le titre de la page est déjà le `h1`. La hiérarchie du contenu
   commence au `h2`.

Et un interdit : **jamais de taux ni de qualificatif de conformité RGAA** sur la page Accessibilité.
Aucun audit n'a été mené, et ces qualificatifs sont eux-mêmes des résultats d'audit.

La source versionnée de ces trois pages est dans [`contenu/`](contenu/), et
[`importer-pages.sh`](importer-pages.sh) la repousse en base. **Si vous modifiez une page dans
l'éditeur, reportez la modification dans le fichier source** — sinon le prochain import l'écrasera.
