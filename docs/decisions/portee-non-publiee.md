# Décision — Projet non destiné à la publication, sources externes bouchonnées

**Domaines** `infra`, `meteo`, `effis`, `statuts`, `securite`
**Date de la décision** : 14 août 2026 · **Auteur** : propriétaire du projet, via `/lead-CMS`
**Statut** : décision arrêtée · **révocable** — voir §5

> Ce document fixe la portée de déploiement du projet. Il est opposable à toutes les chaînes aval,
> en particulier celles de l'Épic 4 (#10 météo, #11 EFFIS, #12 fraîcheur) et de l'Épic 6 (#16).
>
> **Règle de lecture** : ce document réduit le périmètre de *déploiement*, jamais celui de la
> *qualité*. Aucune contrainte non négociable du brief n'est levée. Une chaîne qui invoquerait ce
> document pour justifier un raccourci sur l'accessibilité, l'absence de requête tierce, le
> fonctionnement sans JavaScript ou l'honnêteté des données est en faute.

---

## 1. La décision

Le site **ne sera pas publié**. Pas de mise en ligne, aucun utilisateur réel attendu.

Deux conséquences directes :

1. **Aucun compte externe n'est à créer, aucune clé d'API n'est à obtenir.** Les sources externes
   sont consommées via des **connecteurs simulés**.
2. Les prérequis qui n'existent que pour un site publié sont **mis en sommeil, pas supprimés** :
   nom de domaine, hébergement, sauvegarde hors hébergeur, autorisation de réutilisation du flux
   préfectoral, licence de notre agrégat de données. Ils sont consignés à l'issue **#40** et se
   rallument tels quels si la décision change.

## 2. Ce que « connecteur simulé » veut dire, et ne veut pas dire

La source est fausse. **Tout le reste est réel.**

| Élément | État |
|---|---|
| Logique métier, normalisation, validation | réel |
| Mise en cache, fraîcheur, péremption | réel |
| Rendu serveur, affichage, accessibilité | réel |
| Tests d'intégration | réels, et ils doivent couvrir les états dégradés |
| **Source des données** | **bouchonnée** |

**La frontière d'ingestion ne change pas.** Le connecteur simulé occupe exactement la place
qu'occuperait le connecteur réel, avec la même signature et le même contrat de sortie. Basculer
vers la vraie source doit rester **un changement de connecteur, pas une réécriture**.

## 3. Ce que cette décision ne lève pas

- **Contrainte n° 2 — zéro requête navigateur vers un domaine tiers.** Inchangée. Même simulée,
  une source est consommée **côté serveur**. Un connecteur bouchonné ne justifie jamais un appel
  depuis le navigateur.
- **§4.2 du brief — ne jamais présenter un statut périmé comme courant.** Inchangée, et c'est le
  point de vigilance principal de cette décision. Un connecteur simulé ne doit pas servir d'alibi
  pour afficher une valeur inventée comme si elle était relevée. Les états « information non
  disponible », « hors saison » et « non encore publié » restent **atteignables et testés**.
- **Accessibilité AA bloquante**, **zéro cookie côté public**, **rendu atelier**. Inchangées.
- **Interdiction d'inventer un fait de domaine.** Un libellé officiel, un seuil, une couleur
  réglementaire ne se déduisent pas d'un bouchon. Ils se demandent au propriétaire du projet, comme
  le prescrit la règle en tête du brief.

## 4. Sources concernées

| Source | Compte requis | Traitement retenu | Établi par |
|---|---|---|---|
| Météo-France « Météo des forêts » | oui, gratuit (§4.3 du brief) | **connecteur simulé** — pas d'inscription | cette décision |
| EFFIS / Copernicus | à vérifier, a priori non | connecteur simulé, cohérence avec #10 | cette décision |
| Préfecture des Bouches-du-Rhône | non — flux public sans authentification | inchangé, mode `automatique_surveille` déjà en place | [`source-prefecture.md`](source-prefecture.md) §6 |
| Tuiles OpenStreetMap | non | déjà auto-hébergées, aucun changement | issue #9, livrée |

Le cas de la préfecture n'est **pas** modifié par cette décision : le flux est réel, public et déjà
consommé. Seule la question de l'**autorisation de réutilisation** (§9-Q6 de `source-prefecture.md`)
passe en sommeil, puisqu'elle ne se pose qu'à la publication.

## 5. Comment cette décision se révoque

Si une publication est décidée, il faut, **avant toute mise en ligne** :

1. rouvrir l'issue **#40** et traiter ses entrées mises en sommeil ;
2. trancher la **question Q1 du contrat #8** (`docs/contracts/issue-8.md`) — licence de notre
   agrégat sur un point d'accès anonyme ouvert à toute origine ;
3. obtenir l'**autorisation de réutilisation** du flux préfectoral (§9-Q6 de `source-prefecture.md`),
   ce flux ne portant ni licence ni CGU ;
4. remplacer les connecteurs simulés par les connecteurs réels — ce qui, si le §2 a été respecté,
   ne doit toucher que la couche connecteur.

Ce document est alors amendé, jamais supprimé : il explique pourquoi le code a la forme qu'il a.
