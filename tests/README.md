# Recette d'intégration — MASSIFS

**Pas de tests unitaires dans ce dépôt.** Un scénario ne teste jamais une fonction isolée : il joue une
histoire complète dans un WordPress réellement amorcé, à l'intérieur de la stack Docker du dépôt, et
n'affirme que des faits **observables** — ce que la base contient, ce que le domaine rend au thème, ce
que le serveur répond en HTTP. Si un contrôle n'exerce pas le front et le back ensemble à travers une
frontière réelle, il n'a pas sa place ici.

**Aucune source externe n'est jamais appelée.** La préfecture, Météo-France et EFFIS sont bouchonnés à
la frontière d'ingestion (`pre_http_request`). Quand un scénario a besoin d'un vrai aller-retour HTTP,
il vise notre propre serveur (`http://wordpress/`), à l'intérieur de la stack.

---

## Lancer

```bash
bash docker/up.sh          # la stack doit tourner

bash tests/run.sh          # tous les scénarios
bash tests/run.sh 13       # un seul, par numéro
bash tests/run.sh jointure # ou par mot-clé

bash tests/verifier-http.sh   # origines tierces, gardes 403, budget de transfert
bash tests/module-absent.sh   # tolérance du chargeur à un module frère absent

docker compose down        # ne rien laisser tourner
```

`tests/run.sh` rend un code de sortie non nul dès qu'une assertion échoue, et affiche le total.

## Comment c'est fait

| Chemin | Rôle |
|---|---|
| `tests/bootstrap.php` | assertions, purge d'état, fabriques de charges utiles, bouchons réseau |
| `tests/scenarios/*.php` | un scénario par fichier, exécuté par `wp eval-file` dans le conteneur d'outillage |
| `tests/outils/` | fichiers appelés par les scripts shell, hors de la boucle des scénarios |
| `tests/run.sh`, `tests/verifier-http.sh`, `tests/module-absent.sh` | orchestration depuis l'hôte |

**Chaque scénario est autonome** : il commence et finit par `t_reset()`, et doit passer lancé seul.
Aucun ne dépend de l'ordre.

**Le connecteur est désarmé sur la stack de développement** (`WP_ENVIRONMENT_TYPE=local`), pour qu'une
machine de développement ne puisse pas bombarder le serveur de la préfecture. Un scénario qui a besoin
du chemin d'ingestion appelle `t_armer_connecteur()`, qui redéfinit le modèle d'URL vers notre propre
serveur : le connecteur se réarme et la source réelle devient inatteignable par construction. Un
scénario qui doit être armé **dès l'amorçage** (planification du cron sur `init`) porte le suffixe
`.arme.php` ; `run.sh` lui pose la constante avant le chargement de WordPress.

## Les scénarios

| Fichier | Ce qu'il éprouve | Ligne du §12 |
|---|---|---|
| `01-amorcage` | surface contractuelle des trois chaînes, table, légende officielle, attributions | chaîne des données |
| `02-jointure-statut-massif` | une publication préfectorale traverse tout et devient un statut lisible par le thème | chaîne des données |
| `03-garde-referentiel-ingestion` | le garde-fou référentiel rejette un lot amputé, inconnu ou de cardinal différent | données aberrantes |
| `04-statut-jamais-perime` | **règle absolue §4.2** : aucune donnée, donnée de la veille, `level` 0, hors saison, jour futur, péremption | statut périmé |
| `05-non-publie-404` | un 404 est « pas encore publié » : aucun échec compté, aucune alerte | chaîne des données |
| `06-panne-reseau` | source injoignable : dernière valeur conservée, échec compté, fraîcheur honnête | échec réseau |
| `07-charge-aberrante` | 11 charges rejetées, valeur précédente intacte, alerte émise ; et ce qui n'est PAS une aberration | données aberrantes |
| `08-connecteur-desarme` | le coupe-circuit de la stack de développement est réel, pas décoratif | — |
| `09-migration-nullabilite` | base déjà installée : `niveau_cle` devient nullable, la ligne héritée survit | chaîne des données |
| `10-alter-idempotent` | l'`ALTER` de nullabilité est émis une fois, jamais deux | chaîne des données |
| `11-contrat-ecriture-projection` | une ligne irrécupérable n'écrit rien ; `1326`/`1327` écartés ; relevé réussi seulement si tout est écrit | chaîne des données |
| `12-geometrie-et-rest` | géométrie servie depuis notre origine, intègre, sous budget ; aucune route REST ; aucun asset enfilé | zéro requête tierce, budgets |
| **`13-jours-consecutifs-identiques`** | **régression permanente — voir ci-dessous** | statut périmé |
| `14-install-fraiche` | installation vierge : table créée, aucune erreur PHP, tout « indisponible » honnêtement | chaîne des données |
| `20-cron-complet.arme` | enregistrement, planification horaire, filtre d'URL de bout en bout, hors-saison sans octet réseau, retrait à la désactivation | chaîne des données |

### `13-jours-consecutifs-identiques` — à ne jamais supprimer

Le corps servi par la préfecture **ne contient aucune date**. Deux journées où les 27 massifs portent les
mêmes valeurs produisent donc un corps octet pour octet identique — c'est le cas **nominal** en juin et
pendant tout épisode stable. Un garde-fou fondé sur le hachage a classé le second jour « doublon »,
n'a rien enregistré, et aurait affiché « information non disponible » pendant toute la durée d'un
épisode stable, c'est-à-dire exactement quand la donnée est bonne.

Ce défaut a échappé à la recette **parce que cette suite affirmait le mauvais comportement**. La règle
en vigueur est sans exception :

> Le 404 est le seul signal de non-publication. Un 200 sur `{date}.json` **est** la publication de cette
> date. Le hachage ne peut que journaliser, ou éviter une réécriture pour la **même** date.

## Écrire un scénario

1. Une histoire, pas une micro-assertion : « la préfecture publie, le visiteur voit » est un scénario.
2. On n'affirme que de l'observable. Jamais « telle méthode privée a été appelée ».
3. On gèle ce qui doit l'être — saison, fraîcheur — par les filtres publics prévus à cet effet, jamais
   en attendant que l'horloge coopère.
4. **On n'affaiblit jamais une assertion pour faire passer un scénario**, et on ne supprime jamais un
   scénario rouge. Un rouge est soit un défaut du code — qui se rapporte —, soit une attente fausse —
   qui se corrige en disant pourquoi elle était fausse.

## Ce que cette suite ne couvre pas

Elle ne remplace ni un contrôle humain au lecteur d'écran, ni un vrai téléphone à 360 px, ni une
restauration de sauvegarde, ni HTTPS en production. Tant qu'aucun gabarit de thème n'affiche les
statuts, tout ce qui se prouve sur une page rendue — utilisabilité sans JavaScript, parcours clavier,
contrastes, bandeau de non-officialité affiché — reste hors d'atteinte, et n'est déclaré nulle part
comme couvert.
