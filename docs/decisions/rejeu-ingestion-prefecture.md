# Décision — Rejouer une date déjà instantanée : re-contrôle, rejeu de projection, états de projection

**Domaines** `statuts`
**Date de la décision** : 2 septembre 2026 · **Auteur** : chaîne de l'issue #19
**Statut** : décision arrêtée · **Amendée le 2 septembre 2026** (§3 bis et §4 bis, passe corrective de
revue de lot ; §4 bis *limites* et §4 ter, passe documentaire de fin de lot)

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
une **table d'états de projection** dont un seul contresens rendrait le connecteur bouclant (§3). Ces
deux choses doivent survivre à la lecture du diff.

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
aucune option d'une autre chaîne. Il écoute, c'est tout — et depuis le §4 bis, il **lit** aussi le
domaine par sa fonction de lecture publique, avant de décider s'il rejoue. Toujours aucune écriture.

**Conséquence documentaire non traitée ici** : l'en-tête de `ProjecteurPrefecture` porte encore, à ses
lignes 23-24, l'affirmation « cette classe est donc la SEULE frontière entre les deux modules, et elle
est à sens unique ». Elle est **fausse depuis ce lot**. Le fichier appartient au domaine, hors de
l'empreinte de cette issue : la correction est ouverte en suivi.

Le second argument `$motif` (`publication` | `republication` | `rejeu`) est ajouté à
`massifs_prefecture_snapshot_enregistre`. `ProjecteurPrefecture::projeter` est branché avec
`accepted_args = 1` : l'abonné existant n'en reçoit qu'un et n'est pas affecté.

## 3. La table des états de projection

L'état de projection vit sur l'instantané, dans une clé `projection`. **L'absence totale de la clé se lit
`inconnue`** : un instantané écrit avant cette issue se relit tel quel, sans migration ni bump de schéma
(`SnapshotRepository::all()` conserve les clés inconnues).

| `resultat` | Rejeu autorisé ? | Re-contrôle réseau autorisé ? | Motif |
|---|---|---|---|
| `inconnue` | **non** | oui | Rien ne dit que la projection a échoué. Rejouer ré-émettrait une publication probablement déjà projetée et doublerait l'historique. |
| `complet` | **non** | oui | Il n'y a rien à réparer. |
| `partiel` | **oui**, borné | oui | Une partie du lot manque en base ; l'écrire est le seul remède. |
| `rejete` | **oui**, borné | oui | Le lot entier a été refusé ; la cause peut être passagère. |
| `sans_projecteur` | **seulement si un abonné est de nouveau présent** | oui | Voir §3 bis. |

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
projecteur cassé n'est pas un projecteur absent.** Le confondre reviendrait à consigner
`sans_projecteur` sur la foi d'une réponse mal formée, et donc à suspendre le rattrapage de la date à la
seule présence d'un abonné (§3 bis) au moment précis où le domaine signale qu'il va mal — le garde-fou
anti-boucle retourné contre le but qu'il sert.

`sans_projecteur` n'ouvre pas le rejeu de lui-même, mais il n'empêche jamais le **re-contrôle réseau**.
Les deux n'ont pas le même motif : le rejeu répond à une projection en échec, le re-contrôle à une
republication possible de la source. Le domaine peut être absent sans que cela justifie de cesser de
surveiller la source.

## 3 bis. `sans_projecteur` n'est pas terminal : il attend le retour d'un abonné

**Ce qui était décidé.** `sans_projecteur` interdisait tout rejeu, définitivement. Le motif était juste :
réémettre une action que personne n'écoute ne répare rien, et le faire à chacune des 96 passes du jour
est une boucle pure.

**Ce qui manquait.** Cet état n'a aucun chemin de récupération **dans la journée**. Si le domaine est
désarmé au moment de l'unique publication puis réparé une heure plus tard, la date reste sans statut
**jusqu'à minuit** alors que la donnée est en cache, à une émission d'action de la base. C'est le défaut
2 de cette issue, simplement décalé dans le temps — le site annonce « information non disponible » sur
une donnée qu'il détient.

**Ce qui est décidé.** Un instantané en `sans_projecteur` redevient rejouable **lorsque, et seulement
lorsque, `has_action( 'massifs_prefecture_snapshot_enregistre' )` est vrai** — c'est-à-dire quand
quelqu'un est de nouveau abonné à l'action.

La sonde est exacte pour ce qu'elle décide, et gratuite :

- si le domaine est **réellement absent**, elle est fausse et **aucun rejeu n'a lieu** : la boucle que le
  garde-fou d'origine interdisait reste impossible ;
- si un abonné est **revenu**, l'émission a une chance d'aboutir, et c'est exactement le cas que le
  garde-fou n'avait aucune raison de condamner ;
- le rejeu reste plafonné par `REJEUX_MAX_PAR_JOUR`, compteur inchangé.

**Sa limite, nommée.** La sonde compte **tout abonné**, pas seulement un projecteur : un observateur
passif branché sur l'action (journalisation, métrique) suffit à la rendre vraie. Le coût de cette
imprécision est borné à `REJEUX_MAX_PAR_JOUR` émissions sans effet par date et par jour, sans un octet
réseau et sans une ligne d'historique — puisque personne n'écrit. Une sonde plus fine (« un abonné qui
conclut un bilan ») n'existe pas avant d'avoir émis, et l'émission est précisément ce qu'on cherche à
décider.

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

## 4 bis. Un rejeu ne révoque jamais une décision humaine plus récente

C'est l'arbitrage de la passe corrective, et il n'était pas instruit : le §4 ci-dessus décide **quand**
on ré-émet, jamais **ce qu'une ré-émission écrase**.

**Le fait de base.** Le statut courant d'un couple (massif, jour) est résolu par
`Depot::selectionner_jour()` — un `INNER JOIN ( SELECT MAX(id) … GROUP BY massif_code )`, c'est-à-dire
**la dernière écriture gagne, sans aucune préséance de source**. Le portail
(`admin/ecran-publication/service-publication.php`) et le projecteur (`ProjecteurPrefecture`) passent
tous deux par `massifs_enregistrer_statuts()`, écrivent dans la même table, pour le même
`jour_validite`. Le modèle est append-only : une correction est une ligne de plus, jamais un
écrasement — mais c'est bien la **dernière** ligne qui est présentée.

**La séquence, atteignable telle quelle.**

1. **07 h** — projection `partiel` : la base a refusé une écriture.
2. **09 h** — le gestionnaire corrige un massif depuis l'écran de publication. « Corriger le statut du
   jour » est une fonction annoncée du portail (§6 du brief).
3. **10 h** — passe planifiée, le rejeu est autorisé, **le corps de 07 h est ré-émis** : les 25 lignes
   préfectorales sont ré-insérées et **la correction de 09 h cesse d'être courante**. Aucune alerte,
   aucune trace lisible autre que 25 lignes d'historique de plus.

**Pourquoi c'est inacceptable.** Ce n'est pas « la préfecture prime avec une donnée plus fraîche » —
cela se défendrait. C'est **une copie périmée qui redevient courante par effet de bord d'un mécanisme de
reprise** : la donnée rejouée n'apporte aucune information nouvelle. On tombe donc sur la règle absolue
du projet — *ne jamais présenter un statut périmé comme courant* — et sur la promesse du §6. C'est un
effet **né de cette issue** : avant elle, le garde `has()` interdisait toute ré-émission.

**Ce qui est décidé.** `Runner::rejeu_du()` s'abstient dès qu'il existe, pour ce `jour_validite`, une
écriture de source `saisie_manuelle` **postérieure au `recupere_le` de l'instantané**. Un rejeu est une
**reprise technique** ; il n'a aucune raison de révoquer une décision humaine plus récente.

**Le re-contrôle n'est pas concerné, et c'est délibéré.** Un corps réellement nouveau n'est pas une
copie périmée : c'est une publication que la préfecture vient de faire, elle est plus fraîche que la
saisie, et la préséance de la source officielle s'y défend. Un re-contrôle écrase donc une saisie
manuelle antérieure, comme avant.

**Comment le garde lit le domaine, sans jamais y écrire.** Par la fonction de lecture publique
`massifs_statuts_du_jour()`, dont chaque entrée porte `source` et `enregistre_le`. Pas par
`massifs_journal_statuts()`, réservée au portail et qui déclencherait un `_doing_it_wrong` en cron. Les
codes de massif sont dérivés de l'instantané par `massifs_code_depuis_source()`, comme le fait le
projecteur : l'identité appartient au référentiel.

**Le piège des formats, traité explicitement.** `recupere_le` est écrit par le validateur en ISO 8601
UTC (`gmdate( 'c' )`, décalage explicite). `enregistre_le` est rendu par le domaine en ISO 8601 UTC lui
aussi, mais converti depuis un **format de stockage sans fuseau**. Les deux passent par le même parseur
explicite, `Horloge::instant_depuis_chaine()` : un `strtotime()` appliquerait le fuseau du serveur à la
forme sans décalage et fausserait la comparaison d'une heure entière en été.

**Échec fermé.** Horodatage illisible ou absent, date de validité incohérente, provenance humaine
renommée par le domaine : **on s'abstient de rejouer**. Sur une donnée de sécurité, ne rien faire est le
bon défaut. Le domaine **absent**, en revanche, n'est pas un doute : sans domaine, aucune saisie manuelle
n'est possible, et transformer son absence en refus retirerait au connecteur sa seule reprise.

**Les limites connues, nommées plutôt que tues.**

- Une saisie manuelle **antérieure** au `recupere_le` de l'instantané reste écrasable par un rejeu. Elle
  est déjà écrasable par le re-contrôle, pour la même raison, et distinguer les deux exigerait une
  préséance de source que le modèle ne porte pas.
- Le garde protège la décision humaine **la plus récente pour ce jour**, pas la lisibilité de
  l'historique : une reprise légitime ajoute toujours ses lignes.
- Le garde ne **répare** rien : il s'abstient, et laisse l'état de projection tel quel. Une projection
  restée `partiel` derrière une saisie manuelle le reste, et se voit sur l'écran d'exploitation.
- **La frontière d'une seconde n'est pas protégée.** La comparaison de `class-runner.php:375` est
  **stricte** (`$enregistre_le > $recupere_le`) et les deux horodatages sont **tronqués à la seconde** :
  `gmdate( 'c' )` d'un côté, format de stockage MySQL de l'autre, et `Horloge::instant_depuis_chaine()`
  ignore les fractions. Une saisie manuelle enregistrée **dans la même seconde** que la récupération de
  l'instantané est donc lue comme antérieure, et le rejeu passe. La fenêtre réelle est d'**une seconde**,
  entre deux actes qui ne se déclenchent pas ensemble : elle est quasi théorique. Elle est nommée parce
  que c'est le **seul endroit où « doute → abstention » n'est pas appliqué à sa propre frontière**. Elle
  n'est pas corrigée : passer à `>=` est un changement de comportement exécutable, et une seconde ne
  justifie pas de rouvrir un code validé en revue et en recette. Le jour où ce fichier se rouvre pour
  une autre raison, c'est la première ligne à reprendre.

**Une dépendance cachée, nommée plutôt que découverte dans deux ans.** Deux chemins du garde **laissent
passer** le rejeu au lieu de s'abstenir :

- `class-runner.php:320` — le domaine est absent (`function_exists()` / `class_exists()` en échec) ;
- `class-runner.php:357` — aucun code de rangement n'est dérivable du corps, donc « un rejeu n'écrirait
  rien, il n'y a aucune décision humaine à protéger ».

Ces deux « rien à protéger » ne sont vrais **que parce qu'un autre module refuse d'écrire dans ces
états** : `ProjecteurPrefecture` rejette le lot quand l'instantané ne porte aucun massif
(`ProjecteurPrefecture.php:87-91`) et quand le référentiel n'expose pas `massifs_code_depuis_source()`
(`:100-106`). L'invariant est donc **« le projecteur n'écrit rien de ce que le garde n'a pas pu
regarder »**, et il vit dans un fichier d'un **autre module** — il n'est nommé ni au garde, ni dans le
projecteur.

**Ce qui casse s'il disparaît** : rendre le projecteur plus tolérant — ranger un statut sous
l'identifiant de la source faute de code, accepter un lot vide — transforme silencieusement ces deux
« laisser passer » en trous du garde. Un rejeu écrirait alors des lignes que le garde n'a jamais
examinées, et pourrait faire cesser d'être courante une saisie manuelle qu'il n'a pas pu voir. Les deux
fichiers appartiennent à des modules dont les évolutions ne se relisent pas l'une l'autre : c'est le
genre de couplage qui se paie deux ans plus tard.

**Une seule porte, et c'est structurel.** La politique de rejeu est interrogée depuis **trois** chemins —
la sélection des dates à traiter, le rejeu direct, et le chemin « corps inchangé » d'une récupération
réussie. Elle vit donc dans **une seule fonction**, `Runner::rejeu_du()`, que les trois appellent. Une
règle posée dans un seul de ces chemins serait contournable par les deux autres : c'est exactement le
patron de duplication dénoncé au §1, un cran plus bas.

## 4 ter. L'emprunt de CLASSE au domaine, exception nommée à la clause d'`api.php`

Le §4 bis consigne l'emprunt **fonctionnel** du connecteur au domaine (`massifs_statuts_du_jour()`,
`massifs_sources_statut()`, `massifs_code_depuis_source()`) : c'est exactement l'usage que les fonctions
publiques prévoient. Il ne consignait pas le second emprunt, qui n'est pas de même nature.

**Le fait.** `class-runner.php:44` porte `use Massifs\Domain\Fraicheur\Horloge;`. Or
`domain/statuts/api.php:4-5` énonce l'inverse : « Seules ces fonctions sont publiques : aucun
consommateur n'instancie ni n'appelle une classe `Massifs\` ». La clause souffre donc **une exception,
et elle est nommée ici** au lieu de ne vivre que dans un commentaire d'implémentation.

**Pourquoi cette exception est préférable au second parseur.** `domain/fraicheur/api.php` n'expose
**aucune fonction publique qui parse un instant arbitraire en valeur comparable** :
`massifs_horodatage()` met en forme pour l'affichage, `massifs_fraicheur()` répond sur le jour courant.
Le garde du §4 bis, lui, compare deux horodatages d'origines différentes, dont l'un vient d'un format de
stockage **sans fuseau**. Écrire un parseur local dans le connecteur aurait produit **deux lectures
divergentes du même instant** — d'une heure entière en été — sur la comparaison même qui décide si une
décision humaine est protégée. Sur une donnée de sécurité, un seul parseur. L'emprunt est en **lecture
pure** : `Horloge` ne lit ni n'écrit aucune table.

**Ce qu'il faudrait pour lever l'exception.** Exposer côté `domain/fraicheur` une fonction publique de
**parsage** d'instant rendant une valeur comparable, puis remplacer l'emprunt de classe par son appel,
gardé par `function_exists()` comme les trois autres. `domain/fraicheur/api.php` est **hors de
l'empreinte de l'issue #19** : le suivi appartient à l'orchestrateur, au même titre que la correction de
l'en-tête de `ProjecteurPrefecture` ouverte au §2.

**Le connecteur préfecture n'est pas seul dans ce cas.** `includes/ingest/meteo/class-releve.php:69-74`
atteint `Massifs\Domain\Fraicheur\RegistreReleves` en nom pleinement qualifié, et l'**instancie**.
Lever la clause suppose donc de traiter les deux ensemble. Hors empreinte également.

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
