# Décision — Projet vitrine, conséquence sur l'ambiguïté et sur le gel de contrat

**Domaines** `infra`
**Date de la décision** : 31 août 2026 · **Auteur** : propriétaire du projet, tranchée aux issues #19 et #79
**Statut** : décision arrêtée

> Ce document porte deux décisions distinctes mais liées par une même cause : la nature vitrine du
> projet. Il est opposable à toutes les chaînes aval.

---

## 1. MASSIFS est un projet vitrine, sans commanditaire réel

Il n'y a **aucun commanditaire réel** — ni préfecture, ni commune — à qui poser une question de fait
concernant le terrain (horaires de republication d'une source, usage réel d'un portail, etc.). Le projet
sert à démontrer un savoir-faire technique.

### Conséquence sur la règle d'ambiguïté

La règle en tête du brief (« question au propriétaire du projet, jamais d'invention silencieuse ») reste
entière pour tout ce qui touche au **domaine métier livré** : libellés officiels, seuils réglementaires,
couleurs, périmètres — rien de tout cela ne se déduit, cela se demande, comme le fait déjà
`docs/decisions/source-prefecture.md`.

Mais **une question de fait réel qui n'a pas de réponse accessible ne bloque plus une issue**. Quand
aucune enquête ne peut produire la réponse (ex. : la préfecture republie-t-elle un même jour en cours de
journée ? — cf. issue #19), la chaîne :

1. choisit l'hypothèse **techniquement la plus défendable** au regard des contraintes non négociables et
   de la règle de sécurité produit (ne jamais présenter un statut périmé comme courant) ;
2. **écrit la décision et son motif** dans l'issue, de façon vérifiable ;
3. avance — sans attendre une réponse qui ne viendra pas.

Le blocage reste réservé aux cas où **aucune hypothèse ne permettrait de livrer** (ex. : une valeur qui,
si mal choisie, ferait violer une des 4 contraintes non négociables ou l'accessibilité AA, sans repli
possible).

Précédent : issue #19 (« Rejouer la récupération d'une date déjà instantanée »), débloquée par
l'hypothèse « une republication en cours de journée est possible », retenue parce qu'elle est la seule
des deux dont l'erreur est rattrapable.

## 2. Le gel de contrat est facultatif pour une issue mono-face

`CLAUDE.md` décrit le gel du contrat (`docs/contracts/issue-<n>.md`) comme une étape de la chaîne. Cette
étape a un objet précis : réconcilier, avant tout développement, l'interface que `leaddev-back-cms` et
`leaddev-front-cms` proposent chacun de son côté sans se voir. Elle n'a de sens que s'il existe une
interface à réconcilier.

**Décision : le gel de contrat est**

- **obligatoire** dès qu'une issue a **deux faces** — thème et extension doivent s'accorder sur une clé,
  une chaîne ou une forme de données ;
- **facultatif** pour une issue **mono-face** — infra, outillage, documentation, `.gitignore`,
  `.gitattributes`, CI — c'est-à-dire une issue ne touchant qu'**un seul** domaine fonctionnel, sans
  empreinte fichiers à la fois côté thème **et** côté extension. Sans deux parties qui s'ignorent en
  parallèle, un contrat gelé deviendrait une vérification circulaire : le document ne ferait que répéter
  le fichier qu'il est censé contraindre.

Une chaîne qui se dispense du contrat sur ce fondement **consigne son raisonnement** dans l'en-tête du
fichier livré et dans le corps du commit — comme #33 (`.gitignore`) et #35 (`.gitattributes`) l'ont fait.

**Pas de contrat rétroactif** pour #33 ni #35 : leur raisonnement est déjà écrit et vérifiable (78 lignes
d'en-tête dans `.gitattributes`, 3 lignes de convention dans `.gitignore`, corps de commit détaillés).

Tranché à l'issue #79.

## 3. Portée

Ces deux décisions sont des règles de méthode, pas des exemptions aux 4 contraintes non négociables, à
l'accessibilité AA bloquante ni à la règle de sécurité produit. Elles s'appliquent à toute chaîne future.
