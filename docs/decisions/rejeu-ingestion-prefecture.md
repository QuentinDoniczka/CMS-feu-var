# Décision — Rejouer une date déjà instantanée : re-contrôle, rejeu de projection, états terminaux

**Domaines** `statuts`
**Date de la décision** : 2 septembre 2026 · **Auteur** : chaîne de l'issue #19
**Statut** : décision arrêtée

> Ce document complète [`docs/decisions/source-prefecture.md`](source-prefecture.md), dont le §7.2 fixe la
> règle « le hachage ne provoque jamais de rejet », et le README du connecteur
> (`wp-content/plugins/massifs-core/includes/ingest/prefecture/README.md`), qui porte le détail
> d'implémentation. Il est opposable aux chaînes aval.

---

## 0. Pourquoi une décision écrite, sans contrat gelé

L'issue #19 est **mono-face** : son empreinte fichiers est entièrement dans l'extension
(`includes/ingest/prefecture/`), plus un scénario de recette et ce document. Aucune ligne de thème
n'est touchée, aucune clé de rendu n'est négociée, aucun développeur front ne travaille contre elle en
parallèle. `CLAUDE.md` et [`projet-vitrine.md` §2](projet-vitrine.md) rendent le gel de
`docs/contracts/issue-19.md` **facultatif** dans ce cas précis : sans deux parties qui s'ignorent, un
contrat gelé ne ferait que répéter le fichier qu'il est censé contraindre.

Une décision est néanmoins écrite, parce que l'issue ne se contente pas de corriger un défaut : elle
**renverse une propriété que deux fichiers du dépôt affirmaient explicitement** (§2), et elle introduit
une **table d'états terminaux** dont un seul contresens rendrait le connecteur bouclant (§3). Ces deux
choses doivent survivre à la lecture du diff.

## 1. Les deux défauts

**Défaut 1 — une date déjà couverte n'était plus jamais relue.** `Runner::dates_a_traiter()` et
`SourceCalendar::pending_dates()` écartaient tous deux les dates déjà instantanées
(`if ( SnapshotRepository::has( $date_ymd ) ) { continue; }`). La politique était **dupliquée en deux
endroits**, ce qui rendait le défaut structurellement invisible : assouplir l'un des deux gardes ne
changeait rien, l'autre écartait la date de toute façon. Une republication en cours de journée n'était
relue par personne.

**Défaut 2, le plus grave — une projection en échec ne laissait aucune trace, et rien ne la relançait.**
L'instantané était enregistré, l'action émise, et si le domaine refusait le lot ou n'en écrivait qu'une
partie, le connecteur n'en savait rien. Le site annonçait « information non disponible » alors que la
donnée était en cache, à un appel de fonction de la base. Aucune passe ultérieure ne réessayait, puisque
la date était couverte (défaut 1).

**Hypothèse de fait retenue** — « la préfecture peut republier un même jour en cours de journée ». Elle
n'est pas vérifiable : il n'existe aucun commanditaire à qui la poser (cf.
[`projet-vitrine.md` §1](projet-vitrine.md), dont l'issue #19 est le précédent nommé). Elle est retenue
parce qu'elle est **la seule des deux dont l'erreur est rattrapable** : si la préfecture ne republie
jamais, le coût est une poignée de requêtes 200 identiques par jour, sans effet observable ; si elle
republie et que nous ne relisons pas, le site affiche une valeur périmée comme courante — ce que la
règle de sécurité produit interdit.

## 2. Le renversement de frontière

L'en-tête de `ProjecteurPrefecture` (« Cette classe est donc la SEULE frontière entre les deux modules,
et elle est à sens unique ») et la section « Actions » du README du connecteur (« C'est au domaine de
s'y abonner », sous-entendu : et à personne d'autre) décrivaient tous deux la frontière
ingestion → domaine comme **unidirectionnelle**.

**Elle ne peut pas le rester, et c'est ce qui est décidé ici.** Le connecteur s'abonne désormais à
`massifs_projection_prefecture`, l'action que le domaine émet à chaque projection, succès compris. Sans
cet abonnement, le défaut 2 n'est pas corrigeable : « l'instantané est enregistré » et « les statuts sont
écrits » sont deux faits distincts, et seul le domaine connaît le second.

Ce qui borne ce renversement, et le rend acceptable :

- le récepteur (`ProjectionListener`) est **purement passif** — il n'émet rien, n'appelle rien, ne lit
  aucun statut, n'écrit dans aucune table du domaine ;
- il est **strictement défensif** — un bilan non tabulaire, un `jour` illisible ou une date inconnue du
  dépôt n'écrivent rien et ne cassent rien. La forme du bilan appartient au domaine et n'est figée par
  aucun contrat ;
- **la décision de rejeu appartient au `Runner`, et à lui seul.** Le récepteur consigne un fait, il ne
  déclenche rien.

Le connecteur ne projette toujours rien, n'invalide toujours aucun cache de page, ne touche toujours à
aucune option d'une autre chaîne. Il écoute, c'est tout.

Le second argument `$motif` (`publication` | `republication` | `rejeu`) est ajouté à
`massifs_prefecture_snapshot_enregistre`. `ProjecteurPrefecture::projeter` est branché avec
`accepted_args = 1` : l'abonné existant n'en reçoit qu'un et n'est pas affecté.

## 3. La table des états terminaux

L'état de projection vit sur l'instantané, dans une clé `projection`. **L'absence totale de la clé se lit
`inconnue`** : un instantané écrit avant cette issue se relit tel quel, sans migration ni bump de schéma
(`SnapshotRepository::all()` conserve les clés inconnues).

| `resultat` | Rejeu autorisé ? | Re-contrôle réseau autorisé ? | Motif |
|---|---|---|---|
| `inconnue` | **non** | oui | Rien ne dit que la projection a échoué. Rejouer ré-émettrait une publication probablement déjà projetée et doublerait l'historique. |
| `complet` | **non** | oui | Il n'y a rien à réparer. |
| `partiel` | **oui**, borné | oui | Une partie du lot manque en base ; l'écrire est le seul remède. |
| `rejete` | **oui**, borné | oui | Le lot entier a été refusé ; la cause peut être passagère. |
| `sans_projecteur` | **JAMAIS** | oui | **ÉTAT TERMINAL.** |

**`sans_projecteur` est le piège principal de cette mécanique.** Il ne signifie pas « la projection a
échoué » mais « personne n'a conclu de projection » : le domaine `statuts` est absent de l'arbre ou
désarmé, et l'action est émise dans le vide. Le confondre avec `rejete` ferait réémettre à chaque passe
une action que personne n'écoute, jusqu'à la borne quotidienne, chaque jour, pour rien. Le mécanisme qui
les distingue est un **drapeau en mémoire, pour la requête courante** : le `Runner` le réarme avant
d'émettre, le récepteur le pose dès qu'un abonné s'exprime, le `Runner` le relit juste après. Drapeau
absent = `sans_projecteur`.

Le drapeau signifie « le domaine a **répondu** », jamais « la réponse est **exploitable** ». Il est donc
posé en **première instruction** du récepteur, avant tout contrôle de forme : un bilan non tabulaire, un
`resultat` inconnu ou un `jour` illisible n'écrivent rien, mais comptent comme une réponse. **Un
projecteur cassé n'est pas un projecteur absent.** Le confondre reviendrait à poser un état terminal sur
la foi d'une réponse mal formée, et donc à condamner la date au moment précis où le domaine signale qu'il
va mal — le garde-fou anti-boucle retourné contre le but qu'il sert.

`sans_projecteur` interdit le **rejeu**, pas le **re-contrôle réseau**. Les deux n'ont pas le même
motif : le rejeu répond à une projection en échec, le re-contrôle à une republication possible de la
source. Le domaine peut être absent sans que cela justifie de cesser de surveiller la source.

## 4. On ne rejoue que si la projection a échoué **ou** si le corps a changé

C'est la règle qui protège l'historique, et elle est plus stricte que « le corps a-t-il changé ? ».

`Depot::inserer()` **ne déduplique pas** : chaque ré-émission acceptée par le domaine ajoute une ligne
par massif, soit 25 lignes. L'écran Historique est un livrable produit (§6 du brief) : le noyer sous 96
lots identiques par jour le rendrait illisible, et le journal d'écriture ne dirait plus rien de ce qui
s'est réellement passé.

Donc :

- **corps différent** → ré-émission (c'est une republication, elle doit être livrée) ;
- **corps inchangé + projection `partiel` ou `rejete`** → ré-émission, dans la limite de
  `REJEUX_MAX_PAR_JOUR` ;
- **corps inchangé + projection `complet`** → **jamais**. C'est la garantie éprouvée par
  `tests/scenarios/13-jours-consecutifs-identiques.php` (lignes 110-115) et par
  `tests/scenarios/57-rejeu-republication-et-projection.php` (§3).

Corollaire imposé par le §7.2 de [`source-prefecture.md`](source-prefecture.md), et non rediscutable :
**le hachage ne provoque jamais de rejet.** Il n'évite qu'une réécriture inutile et journalise. Le
court-circuit « corps identique » **écrit désormais une entrée de journal** au lieu de sortir en silence
— sans quoi le seul chemin nominal d'une date déjà couverte serait devenu invisible à l'exploitation le
jour même où cette date est redevenue candidate d'une passe à l'autre.

## 5. Les bornes, et leur dérivation

La cadence qui dimensionne tout est celle **réellement recommandée en production** : `DISABLE_WP_CRON`
plus une tâche système au quart d'heure (README du connecteur, §Hébergement), soit **96 passes par
jour**, dont **28 dans la fenêtre 16 h → 23 h**, avec au plus **2 dates par passe**. Ce n'est pas la
récurrence `hourly` du planificateur WordPress, qui n'est qu'un repli quand aucune tâche système
n'existe.

| Constante | Valeur | Dérivation |
|---|---|---|
| `RECONTROLE_SECONDES` | 3 h | 12 passes. Une republication est captée en moins de trois heures, sans recharger une date couverte à chacune des 96 passes — ce qui coûterait 192 requêtes par jour. |
| `RECONTROLES_MAX_PAR_JOUR` | 4 | 4 × 3 h = 12 h, la plage entière où une republication a un sens. Coût plafond ajouté : 4 × 2 dates = **8 requêtes par jour**. |
| `REJEUX_MAX_PAR_JOUR` | 3 | Traverse une cause passagère (panne de base de quelques minutes), borne une cause permanente (référentiel absent) à trois passages au lieu de 96. Zéro octet réseau, mais au plus 3 lots d'historique. |

**Budget.** Le garde d'origine évitait légitimement environ 8 requêtes inutiles par jour en saison. La
nouvelle politique en ajoute au plus 8. Total : environ 16 au lieu de 8 — **même ordre de grandeur**,
ce qui était la contrainte. Zéro hors saison, inchangé. Chaque requête évitée sert la contrainte n°2 du
projet.

Les deux compteurs quotidiens portent le jour auquel ils appartiennent et **se réarment d'eux-mêmes au
changement de jour**, sans tâche de purge : une cause permanente réparée mérite un nouvel essai le
lendemain.

**Un rejeu ne coûte aucun octet réseau et prime donc toujours sur une requête sortante** dans une passe
planifiée. Recharger un fichier ne répare pas une projection qui a échoué.

## 6. Deux défauts latents corrigés au passage, parce qu'ils sont des prérequis

**La mémoire du garde anti-rafale était dérivée du journal FIFO.** À 96 passes par jour et 2 dates par
passe, un journal de 20 entrées couvre une dizaine de minutes : la dernière tentative pour une date en
sortait avant que le garde de 15 minutes ait fini de la protéger. Tant que les dates couvertes étaient
écartées d'office, le défaut restait théorique ; il devient réel dès que ces dates redeviennent
candidates. `last_attempt_for()` lit désormais une **carte dédiée par date**, écrite avant tout octet
réseau et élaguée à 3 jours. `JOURNAL_MAX` **n'est pas relevé** — le découplage rend ce plafond non
pertinent pour ce frein ; il ne gouverne plus que la lisibilité de l'écran d'exploitation. Le balayage du
journal subsiste en **repli**, pour un état écrit avant l'introduction de la carte.

**`record_marker()` dédoublonnait contre la seule dernière entrée du journal.** Une passe hors saison
marque aujourd'hui *puis* demain : `hors_saison(J)` et `hors_saison(J+1)` alternent et ne se
dédoublonnent donc jamais — 2 entrées par passe, 192 par jour, un journal de 20 entrées qui ne montre
plus que quatre mois de hors-saison. Le dédoublonnage se fait désormais **par date cible**, sur tout le
journal.

## 7. Ce qui n'est pas amendé

**`docs/contracts/issue-3.md:392` n'est pas amendé.** Sa phrase — « le modèle append-only absorbe une
republication en cours de journée » — porte sur le **modèle de statuts**, et elle est exacte : le modèle
l'absorbe parfaitement, comme le prouvent le cas 6 de
`tests/scenarios/11-contrat-ecriture-projection.php` et la fin de
`tests/scenarios/13-jours-consecutifs-identiques.php`. Ce qui manquait n'était pas la capacité du
modèle, c'était le **connecteur qui ne lui en livrait jamais une**. On aligne le code sur le contrat,
pas l'inverse.

**`SnapshotRepository::SCHEMA` et `Validator::SCHEMA` ne sont pas touchées.** Ajouter une clé à
l'instantané est rétro-compatible sans migration, puisque `all()` conserve les clés inconnues. Les
incrémenter n'aurait aucun effet — elles ne sont lues nulle part — et ferait croire à une migration qui
n'a pas lieu.

**`surveiller_fenetre()` est inchangée.** Elle répond à « la source a-t-elle publié quelque chose pour
demain ? », question à laquelle `SnapshotRepository::has()` est la bonne réponse, avant comme après.
L'assouplissement porte sur la sélection des dates à travailler, pas sur ce constat. Aucune alerte
parasite n'est possible : un rechargement n'efface jamais l'instantané en place (un rejet laisse le
précédent intact, un corps inchangé n'est pas réécrit), donc `has()` ne peut pas repasser à faux.

Une projection en échec **ne déclenche pas de courriel**. C'est une autre classe d'incident — la donnée
existe, elle n'est pas écrite — traitée par le rejeu, et déjà journalisée par le domaine. Ouvrir une
troisième classe d'alerte est une autre issue.

Tranché à l'issue #19.
