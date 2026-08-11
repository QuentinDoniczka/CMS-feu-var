# BRIEF PRODUIT — « Accès du jour aux massifs forestiers » (nom de code : MASSIFS)

> Ce document décrit le **QUOI** : ce que le site doit être, faire et prouver.
> Le **COMMENT** (architecture, découpage en issues, outillage, ordonnancement) est entièrement à la main de l'équipe d'agents (brainstorm / lead dev).
> Les technologies citées sont des **propositions**, sauf les quelques contraintes de fond du §3, qui font partie du quoi.
> Toute ambiguïté rencontrée = question au propriétaire du projet, jamais d'invention silencieuse.

---

## 1. Pourquoi ce site existe

Ce site est un **projet indépendant réel et public**, qui servira aussi de **référence vérifiable** dans une réponse à appel d'offres (site communal avec carte interactive de zones à statut variable, portail de mise à jour sécurisé, forte exigence d'accessibilité).

Il doit donc **prouver par l'exemple** cette mécanique :

1. des **zones géographiques prédéfinies** (les massifs forestiers d'un département) ;
2. un **statut qui change quotidiennement** (accès autorisé / réglementé / interdit) ;
3. une **carte interactive** sur fond OpenStreetMap servi depuis notre propre hébergement ;
4. un **équivalent textuel accessible** de la carte ;
5. un **portail sécurisé à rôles restreints** pour mettre à jour les statuts, avec compte de démonstration public ;
6. le tout en **WordPress + thème et extension sur mesure**, mobile-first, rapide, HTTPS, hébergé en France.

**Ce que le site ne doit surtout pas être** : un rendu « prémâché » (constructeur de pages, thème acheté ou par défaut, kit UI générique, esthétique « template IA »). Il doit ressembler à un travail d'atelier conçu pour ce sujet précis (§7).

**Utilité réelle** : en période estivale (≈ 1er juin – 30 septembre), les préfectures du Sud publient chaque soir le niveau d'accès du lendemain à chaque massif forestier. L'information officielle existe mais est éclatée ; le site la rend lisible en une page, avec un équivalent accessible. Le site reste un relais d'information **indicatif** : le bandeau de non-officialité est obligatoire (§5).

---

## 2. Périmètre

- **Département couvert : Bouches-du-Rhône (13)** (périmètres des massifs disponibles en open data, voir §4). L'ajout ultérieur d'autres départements doit rester possible, mais n'est pas à réaliser.
- **Langue : français uniquement.**
- **Aucune donnée personnelle collectée** : pas de formulaire de contact, pas de commentaire, pas de compte visiteur, pas de newsletter, **zéro cookie côté public**. Choix de conception assumé et affiché.
- Nom de domaine : `[DOMAINE]` (fourni par le propriétaire du projet). Environnement de démonstration public sur `demo.[DOMAINE]`.
- Hébergement en **France** (cible pressentie : o2switch, mutualisé).

---

## 3. Contraintes de fond (font partie du QUOI)

Ces points ne sont pas des choix techniques discutables : ils sont **l'objet même de la démonstration**.

- **WordPress**, avec **thème sur mesure** et **extension dédiée** développés pour ce projet. Interdits : constructeurs de pages, thèmes tiers ou par défaut, frameworks CSS génériques.
- **Fond de carte OpenStreetMap servi depuis notre propre hébergement** : aucune requête du navigateur vers un domaine tiers, sur tout le site (tuiles, polices, scripts, images comprises).
- **Site utilisable JavaScript désactivé** : l'information des statuts est présente dans le HTML rendu côté serveur ; la carte est un enrichissement, pas un prérequis.
- **Rendu « atelier », jamais « template »** (§7).

Tout le reste (bibliothèque cartographique, format des fonds de carte, outillage, organisation du code…) est **libre** — des pistes peuvent être proposées (Leaflet, tuiles vectorielles auto-hébergées type PMTiles, proxy-cache raster…), le lead dev tranche.

---

## 4. Les données

**Principe général** : toutes les sources externes (préfecture, Météo-France, EFFIS, fonds de carte) sont consommées **côté serveur** par des tâches planifiées, mises en cache localement, puis servies aux visiteurs depuis notre propre domaine. Le navigateur ne contacte jamais un domaine tiers ; les clés API restent sur le serveur ; en cas de panne d'une source, le site continue de fonctionner sur la dernière donnée en cache avec son indicateur de fraîcheur (§4.5).

### 4.1 Périmètres des massifs (référentiel)
- Source : jeu de données « Massifs forestiers dans les Bouches-du-Rhône » (data.gouv.fr / DataSud, producteur DDTM 13), **Licence Ouverte / Etalab 2.0** — réutilisation libre avec citation de la source et de la date de mise à jour.
- Exigence : les périmètres affichés sont visuellement fidèles à la carte officielle ; ils sont importés une fois puis maintenus, pas dessinés à la main.

### 4.2 Statut quotidien officiel (le cœur du site)
- Fait de domaine : la préfecture publie chaque soir (vers 18–19 h) le statut du lendemain, massif par massif, sur la carte officielle risque-prevention-incendie.fr/13.
- **Première chose à faire du projet : investiguer cette source** (existe-t-il un flux exploitable ?). Trois issues possibles, toutes acceptables :
  - flux exploitable et stable → récupération automatisée quotidienne ;
  - flux fragile → récupération automatisée avec garde-fous (validation stricte, refus des valeurs aberrantes) + repli manuel ;
  - pas de flux → **mode manuel assumé** : saisie quotidienne via le portail (§6) — ce qui reste une démonstration parfaite dudit portail — avec lien direct vers la carte officielle.
- La légende (niveaux, libellés, couleurs, consignes) **reproduit exactement la légende officielle** du dispositif. Ne rien inventer ; en cas de doute, question avec captures.
- Chaque statut porte : sa date de validité, sa source (récupération officielle / saisie manuelle), son auteur le cas échéant. **L'historique intégral est conservé** et consultable (§6).
- **Règle de sécurité absolue** : ne jamais présenter un statut périmé comme courant. Sans donnée valide pour le jour → état « information non disponible, consultez la carte officielle » (lien), sur la carte ET dans la liste.

### 4.3 Météo des forêts (indicateur complémentaire)
- Source : API officielle « Météo des forêts » de Météo-France (portail des API, compte gratuit, licence Etalab 2.0). Niveau de danger météo quotidien.
- Exigence : affiché comme indicateur **distinct** du statut réglementaire d'accès — deux notions différentes, jamais fusionnées visuellement ni sémantiquement ; la différence est expliquée en une phrase sur la page.

### 4.4 Zones parcourues par le feu (EFFIS / Copernicus)
- Objectif : afficher, en plus des massifs, de **vraies zones surfaciques de feu** (polygones).
- Source : services OGC d'EFFIS — European Forest Fire Information System (Copernicus Emergency Management Service) ; couches de type « Burnt Areas 7 Days » (et en option le cumul de saison). Récupération **côté serveur**, filtrée sur le département, servie depuis notre hébergement (la règle « aucune requête tierce depuis le navigateur » s'applique).
- Limites à afficher honnêtement dans la légende : périmètres **estimés par satellite** (feux d'environ 30 ha et plus, mise à jour de l'ordre de deux fois par jour) ; c'est la **zone déjà parcourue par le feu**, pas un périmètre officiel d'interdiction ou d'évacuation — le « on ne peut pas y aller » réglementaire reste porté par le statut des massifs.
- Attribution obligatoire : « © Union européenne, Copernicus Emergency Management Service / EFFIS ».
- Si la source est indisponible, la couche disparaît proprement avec mention « donnée momentanément indisponible », sans casser le reste.

### 4.5 Fraîcheur et honnêteté des données (transverse)
- Récupérations automatiques quotidiennes après l'heure de publication officielle, avec nouvelles tentatives en cas d'échec, puis **alerte email** à l'administrateur si tout échoue.
- Indicateur de fraîcheur partout où un statut apparaît : « Statuts du [date], publiés la veille par la préfecture — mis à jour sur ce site le [date/heure] ».
- Bannière automatique si les données ont plus de 24 h en période d'activité du dispositif.
- Hors période estivale : mode « dispositif inactif » propre et expliqué (pas de page cassée ni de fausse donnée).

---

## 5. Fonctionnalités publiques

### 5.1 Pages
1. **Accueil = la carte du jour** (l'information principale en 0 clic) : statut global en une phrase (« Aujourd'hui, X massifs sur Y sont d'accès autorisé »), carte interactive, équivalent textuel, indicateur de fraîcheur, légende.
2. **La démarche** : pourquoi ce site, comment il fonctionne, sources et limites, qui le fait. C'est la page qui donne sa crédibilité de « vrai projet ».
3. **Accessibilité** : démarche suivie, résultats des vérifications, moyen de signaler un problème par email.
4. **Mentions légales** : éditeur, directeur de publication, hébergeur, crédits, licences des données (§9).

### 5.2 Carte interactive
- Vue initiale : le département entier. Massifs colorés selon le niveau du jour (légende officielle), avec motif/hachure en plus de la couleur pour le niveau le plus restrictif — l'information ne repose jamais sur la couleur seule.
- Sélection d'un massif → panneau lisible (pas une popup minuscule) : nom, statut du jour en toutes lettres, consigne, date de validité, source, communes concernées.
- Sélection d'une zone de feu → panneau : surface estimée, dates de première et dernière observation, commune la plus proche, rappel « estimation satellite, pas un périmètre officiel ».
- Couches : massifs et statuts toujours visibles (le cœur du site) ; zones de feu (§4.4) activable ; chaque couche a son entrée de légende et son reflet dans l'équivalent textuel.
- Sélecteur de date : aujourd'hui / demain (quand le statut du lendemain est publié).
- Utilisable au doigt sur mobile 360 px : cibles ≥ 44 px, panneau en bas d'écran, aucun survol requis.

### 5.3 Équivalent textuel (non négociable)
- Liste/tableau des massifs et statuts **présents dans le HTML initial** (rendu serveur), sous la carte, avec lien d'évitement « aller à la liste des statuts » en haut de page.
- Lisible au lecteur d'écran, imprimable proprement.
- États vides explicites : « Aucune restriction en cours », « Dispositif estival inactif (reprise le 1er juin) », « Information du jour non disponible — consultez la carte officielle [lien] ».
- Les zones de feu détectées y figurent aussi (« zones parcourues par le feu : … », commune la plus proche, date de dernière observation).

### 5.4 Données ouvertes du site
- Un point d'accès public en lecture seule fournit les statuts du jour au format JSON, documenté sur la page « La démarche ». La carte s'en sert aussi.

### 5.5 Sans JavaScript
- La carte est remplacée par une image statique du département renvoyant à la liste textuelle. Tout le contenu informationnel reste disponible.

### 5.6 Bandeau de non-officialité (obligatoire sur toute page affichant un statut)
> « Site d'information indépendant. Seules les publications de la préfecture des Bouches-du-Rhône font foi : [lien carte officielle]. »

---

## 6. Portail de mise à jour (la vitrine du futur portail sécurisé)

À soigner autant que le front : c'est la partie qui démontre la mécanique demandée dans l'appel d'offres.

- **Rôle « gestionnaire » à droits restreints** : consulter et mettre à jour les statuts, voir l'historique — rien d'autre (pas d'accès aux contenus, réglages, extensions, utilisateurs). Menu réduit à l'essentiel.
- **Administrateur** : tout, y compris créer, suspendre et réinitialiser un compte gestionnaire (les trois actions doivent exister : ce sont celles promises au futur client).
- **Écran de mise à jour unique** : liste des massifs, statut actuel, choix du niveau pour demain (et correction du jour), bouton unique « Publier les statuts ». Objectif : **mise à jour complète en moins d'une minute**, au clavier comme à la souris, utilisable sur téléphone. Publication → confirmation, journalisation, propagation sur le site en moins d'une minute.
- **Historique** : vue chronologique filtrable (massif, période, auteur, source), export CSV. Toute écriture est journalisée (qui, quoi, quand, ancienne/nouvelle valeur).
- **Sécurité du portail (résultats attendus)** : HTTPS partout ; tentatives de connexion limitées avec verrouillage temporaire ; mots de passe forts imposés ; double authentification disponible et active pour les administrateurs ; sessions expirantes ; toute écriture vérifie l'identité et les droits ; aucune écriture possible sans authentification via l'API.
- **Démonstration publique** : sur `demo.[DOMAINE]`, instance séparée avec données fictives, non indexée, **compte de démonstration (rôle gestionnaire) dont les identifiants sont affichés** sur une page « Tester le portail » du site principal, **remise à zéro automatique et régulière** (bannière permanente l'indiquant). Le visiteur — donc l'élu qui évalue l'offre — peut se connecter, changer un statut, et voir la carte de démo se mettre à jour. Scénario complet réalisable en moins de 2 minutes.

---

## 7. Design — direction « atelier », pas « template »

- **Processus attendu** : un plan de design d'abord (palette nommée, deux familles typographiques libres et auto-hébergées — une de caractère pour les titres, une de labeur pour le texte —, concept de mise en page, **un élément signature unique**), autocritique ensuite (tout choix qui ressemble à ce qu'on produirait pour n'importe quel site carto est refait), intégration enfin. Le plan de design est un livrable (§11).
- **Ancrage dans le sujet** : la garrigue, le calcaire, le pin, le mistral, la signalétique de sentier (balisage peint, panneaux DFCI). La palette et l'iconographie viennent de là, pas d'un nuancier générique. Éviter les tells « design IA » (crème + serif + terracotta ; noir + accent acide ; look journal à filets fins) sauf choix délibéré et justifié.
- **La carte est le héros** : elle ouvre la page ; le reste est discipliné et silencieux autour. Une seule audace, tenue partout.
- **Micro-rédaction** : voix active, chaque libellé dit exactement ce qui se passe (« Publier les statuts », pas « Valider ») ; les erreurs expliquent quoi faire, sans s'excuser ; vocabulaire constant d'un bout à l'autre.
- **Plancher de qualité silencieux** : responsive jusqu'à 360 px, focus visible partout, préférences de réduction de mouvement respectées, impression propre.
- Ressources visuelles à licence irréprochable uniquement (photos personnelles, banques libres créditées, ou créations pour le projet).

---

## 8. Accessibilité (exigences bloquantes)

- Structure de titres logique, une seule h1 par page, langue déclarée, titres de page uniques.
- Liens d'évitement (« aller au contenu », « aller à la liste des statuts »), parcours clavier complet, focus visible, aucun piège clavier (les panneaux se ferment à Échap).
- Contrastes AA minimum, y compris sur les couleurs de statut (carte et légende).
- Information jamais portée par la couleur seule (motifs + libellés).
- Alternatives pertinentes pour les images ; l'information de la carte est intégralement portée par la liste textuelle ; formulaires du portail : étiquettes explicites, erreurs reliées aux champs, confirmation de publication annoncée aux lecteurs d'écran.
- Zoom texte 200 % sans perte, pas de défilement horizontal à 320 px.
- **Vérifications automatisées d'accessibilité sans erreur bloquante** sur les pages clés (accueil, démarche, accessibilité, connexion, écran gestionnaire) + un contrôle humain final au lecteur d'écran, documenté sur la page Accessibilité.

---

## 9. Sécurité, vie privée, licences

- **Durcissement (résultats attendus)** : édition de code impossible depuis l'administration ; surface d'extensions tierces réduite à zéro si possible, sinon justifiée ; énumération d'utilisateurs bloquée ; en-têtes de sécurité stricts (rendus possibles par l'absence totale de ressources tierces).
- **Sauvegardes** : quotidiennes (base + fichiers), rotation 30 jours, copie hors hébergeur, restauration testée et documentée.
- **Mises à jour de sécurité** appliquées automatiquement pour les mineures ; procédure documentée pour les majeures.
- **Vie privée exemplaire et affichée** : zéro cookie côté public, aucun traceur, aucune ressource tierce ; pas de bandeau de consentement parce qu'il n'y a rien à consentir — l'expliquer fièrement sur « La démarche ». Statistiques : rien, ou analyse de logs serveur anonymisés. Seul traitement : les comptes internes (mentionné dans les mentions légales).
- **Attributions obligatoires** (pied de carte + mentions légales) :
  - fond de carte : « © les contributeurs d'OpenStreetMap » (lien openstreetmap.org/copyright) + mention de la source de l'extrait le cas échéant ;
  - périmètres : « Source : DDTM des Bouches-du-Rhône, via data.gouv.fr — Licence Ouverte 2.0, données du [date] » ;
  - statuts : « D'après les publications de la préfecture des Bouches-du-Rhône » ;
  - météo : « Données Météo-France — Licence Etalab 2.0 » ;
  - zones de feu : « © Union européenne, Copernicus Emergency Management Service / EFFIS ».
- Thème et extension sous licence GPL (cohérence WordPress).

---

## 10. Performance (budgets)

- Accueil : HTML + CSS + JS < 250 Ko transférés hors fond de carte et géométries ; géométries < 300 Ko ; deux fichiers de police maximum.
- Sur mobile simulé en connexion moyenne : chargement principal < 2,5 s, pas de sauts de mise en page perceptibles, score accessibilité maximal aux outils d'audit courants.
- Pages publiques servies en cache, invalidé à chaque publication de statuts ; ressources cartographiques en cache long.

---

## 11. Livrables

- Le **site en production** sur `[DOMAINE]` (HTTPS) et l'**environnement de démonstration** sur `demo.[DOMAINE]`.
- Le **code source complet** (thème + extension) dans un dépôt, avec instructions d'installation permettant à un développeur qui découvre de monter le projet rapidement.
- Le **plan de design** (tokens, typographies, élément signature) et un **journal des décisions** (choix retenus et leurs raisons — réutilisable dans le mémoire technique).
- Une **documentation d'administration** courte : mettre à jour les statuts, gérer un compte gestionnaire, comprendre les alertes.
- Les **preuves de recette** : export réseau démontrant l'absence de requêtes tierces, captures desktop + mobile, résultat des vérifications d'accessibilité, checklist §12 remplie.

---

## 12. Definition of Done

- [ ] Aucune requête **du navigateur** vers un domaine tiers sur tout le site (preuve jointe) — les sources externes (préfecture, Météo-France, EFFIS) sont appelées côté serveur puis servies depuis notre domaine
- [ ] Utilisable JavaScript désactivé ; parcours clavier complet ; vérifications d'accessibilité sans erreur bloquante ; contrôle lecteur d'écran documenté
- [ ] Mobile réel 360 px : carte, panneaux, liste, écran gestionnaire
- [ ] Chaîne des données testée : périmètres fidèles, récupération quotidienne (succès, échec réseau, données aberrantes, hors-saison), couche EFFIS (nominal + indisponibilité), bannière de péremption, bandeau de non-officialité présent
- [ ] Portail : mise à jour complète < 1 min, propagation < 1 min, journal exact, force brute bloquée, double authentification active pour les admins
- [ ] Démo publique : connexion avec le compte affiché, modification visible sur la carte de démo, remise à zéro vérifiée
- [ ] HTTPS actif, sauvegarde et restauration testées, budgets de performance tenus
- [ ] Attributions et licences toutes présentes ; mentions légales ; pages « La démarche » et « Accessibilité » rédigées
- [ ] Livrables du §11 complets

---

## 13. Hors périmètre

Multilingue ; application mobile ; notifications aux visiteurs ; commentaires ; comptes visiteurs ; newsletter ; SEO avancé (se limiter aux bases : titres, métadonnées, sitemap, aperçus de partage) ; outillage RGPD (bandeau cookies, registre…) — sans objet puisque aucune donnée personnelle n'est collectée ; couche de points « détections satellites » NASA FIRMS (uniquement si tout le reste est terminé : ce sont des points, pas des zones, avec avertissement pédagogique et attribution « NASA FIRMS »).
