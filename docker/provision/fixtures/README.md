# Fixtures de provisionnement

Ce dossier est prêt à recevoir les données de démonstration nécessaires aux tests
d'intégration, mais ne contient encore rien : c'est aux chaînes fonctionnelles
(`referentiel`, `statuts`, `meteo`, `effis`) de le remplir.

## Comment c'est câblé

`docker/provision/provision.sh` cherche un fichier `seed.php` dans ce dossier et,
s'il existe, l'exécute avec `wp eval-file` (contexte WordPress complet chargé,
donc les fonctions de l'extension `massifs-core` sont disponibles). S'il n'existe
pas, l'étape est ignorée sans erreur — le provisionnement reste idempotent.

## Ce que `seed.php` devra créer

D'après `docs/BRIEF.md` et le rôle attendu du service `wpcli` :

- les **périmètres des massifs** (référentiel importé une fois, §4.1 du brief) ;
- un **jeu de statuts quotidiens** couvrant les scénarios que les tests
  d'intégration doivent pouvoir déclencher :
  - **nominal** — statut du jour valide et présent pour tous les massifs ;
  - **pas de statut pour aujourd'hui** — pour vérifier l'état « information non
    disponible, consultez la carte officielle » (§4.2, règle de sécurité) ;
  - **hors saison** — dispositif estival inactif (§4.5) ;
  - **EFFIS indisponible** — la couche zones de feu doit disparaître proprement
    avec la mention « donnée momentanément indisponible » (§4.4).

`seed.php` peut `require` d'autres fichiers de ce dossier si besoin (ex.
`seed-massifs.php`, `seed-statuts.php`) — `provision.sh` n'appelle que
`seed.php`, à lui d'orchestrer le reste. Le script doit être idempotent (vérifier
avant de créer, ou nettoyer/recréer proprement) car le provisionnement peut être
rejoué sur une stack existante.
