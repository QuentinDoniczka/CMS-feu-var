# Contrat d'interface — Issue #13 — Rôles du portail et authentification renforcée

**Gelé le** 14 août 2026 par `lead-issue-cms` (chaîne #13) · **Statut** : contraignant.

Épique 5 « Portail de mise à jour », lot de trois chaînes parallèles en arbre de travail partagé,
mono-branche, sans isolation : **#13** (ce document), **#14** (écran de publication), **#15**
(historique + export CSV).

Ce contrat est **le seul document contre lequel #14 et #15 construisent leurs contrôles d'accès.**
Elles ne voient pas le code de #13 et ne doivent rien en supposer d'autre que ce qui est écrit ici.

> **Note de provenance — incident de lot, 14 août 2026.** Une seconde chaîne #13, lancée par erreur en
> parallèle de celle-ci, a écrasé ce fichier à 23:19. Sa version imposait des capacités administrateur
> **dynamiques et jamais persistées** — ce qui contredit frontalement le code réellement livré, dont
> `roles/Installation.php` fait un `add_cap` **persisté** dans `wp_user_roles`. Le présent fichier est le
> contrat d'origine, **restauré**, et c'est bien celui que l'implémentation suit : les fichiers livrés
> citent nommément ses arbitrages (`Installation.php` → « arbitrage A-5 » et « interdit 7 »,
> `Suspension.php` → « arbitrage A-3 »). Toute version antérieure de ce document parlant de capacités
> dynamiques est caduque.

> **Particularité de cette issue** : elle est entièrement côté extension. Aucun fichier de thème n'est
> touché, aucune interface publique n'est produite, aucune chaîne visible par un visiteur n'est émise.
> Les sections « Chaînes fournies par le serveur » et « États spéciaux » du gabarit sont donc **sans
> objet**, et le disent explicitement plutôt que d'être omises.

---

## Empreinte d'écriture — exhaustive

```
wp-content/plugins/massifs-core/includes/security/roles/**
wp-content/plugins/massifs-core/includes/security/auth/**
docs/contracts/issue-13.md
```

**Rien d'autre.** En particulier `massifs-core.php` n'est **pas** modifié : `massifs_core_charger_modules()`
découvre automatiquement `includes/<couche>/<module>/module.php`, et charge la couche `security`
**en premier**. Les deux amorces sont donc chargées sans qu'aucune ligne du bootstrap ne bouge.

> **Conséquence opérationnelle à ne jamais perdre de vue** : le chargeur ne découvre **que**
> `<couche>/<module>/module.php` (ou à défaut `bootstrap.php`). Un répertoire de module **sans amorce est
> du code mort** — ses classes ne sont jamais chargées et aucun de ses hooks n'est enregistré.
> `roles/module.php` **et** `auth/module.php` sont donc l'un et l'autre obligatoires.

Hors empreinte et jamais touchés : `includes/admin/**` (dont `ecran-publication/**` et `historique/**`),
`includes/rest/portail/**`, `includes/rest/public/**`, `includes/domain/**`, `includes/ingest/**`,
tout le thème, `wp-config.php`, `docker/provision/**`.

**Ordre de chargement, fait vérifié** : `scandir` parcourt `security/` alphabétiquement —
`alertes`, puis **`auth`, puis `roles`**. `auth/module.php` est donc chargé **avant** `roles/module.php`.
Aucune des deux amorces ne lit l'autre au chargement ; toute lecture croisée se fait dans un callback
de hook, sous `class_exists`.

---

## Vocabulaire gelé — imposé au niveau du lot

| Élément | Valeur exacte |
|---|---|
| Slug du rôle gestionnaire | `massifs_gestionnaire` |
| Nom affiché du rôle | `Gestionnaire des massifs` |
| Capacité « publier / corriger les statuts » | `massifs_publier_statuts` |
| Capacité « consulter l'historique et l'exporter » | `massifs_consulter_historique` |
| Capacité « gérer les comptes gestionnaires » | `massifs_gerer_gestionnaires` |
| Préfixe reconnu par les gardes | `massifs_` |

### Matrice des capacités — **lisez cette ligne avant d'écrire un contrôle d'accès**

| Rôle | `read` | `massifs_publier_statuts` | `massifs_consulter_historique` | `massifs_gerer_gestionnaires` |
|---|---|---|---|---|
| `massifs_gestionnaire` | oui | **oui** | **oui** | non |
| `administrator` (cœur) | oui | **oui** | **oui** | **oui** |

Trois conséquences contractuelles :

1. **Il n'existe aucun rôle « administrateur » sur mesure.** Le rôle `administrator` du cœur reçoit les
   capacités `massifs_*` par `add_cap`, **persistées** dans `wp_user_roles`. Cloner `administrator` a été
   explicitement rejeté : son jeu de capacités grandit à chaque version majeure de WordPress, et un clone
   figé pourrit en silence.
2. **L'administrateur porte aussi `massifs_publier_statuts` et `massifs_consulter_historique`.**
   Un administrateur incapable de publier serait absurde.
3. **`read` est obligatoire sur le rôle gestionnaire** et n'est jamais retiré, même à un compte suspendu :
   un utilisateur sans `read` est éjecté de `wp-admin` par le cœur, ce qui tuerait le portail.

---

## Fonctions de lecture exposées par l'extension

Déclarées dans `includes/security/roles/api.php`, toutes sous `if ( ! function_exists() )`, toutes
disponibles à partir de `plugins_loaded`. **Elles retournent des chaînes non échappées : l'appelant
échappe au rendu.**

```php
massifs_role_gestionnaire(): string                    // 'massifs_gestionnaire'
massifs_capacite_publier(): string                     // 'massifs_publier_statuts'
massifs_capacite_historique(): string                  // 'massifs_consulter_historique'
massifs_capacite_gerer(): string                       // 'massifs_gerer_gestionnaires'
massifs_capacites_massifs(): array                     // list<string>, les 3 capacités

massifs_peut_publier( ?int $user_id = null ): bool             // null = utilisateur courant
massifs_peut_consulter_historique( ?int $user_id = null ): bool
massifs_peut_gerer_gestionnaires( ?int $user_id = null ): bool

massifs_compte_est_suspendu( int $user_id ): bool
```

Deux fonctions existent **spécifiquement pour la chaîne #15** (filtre « auteur » de l'historique et
rendu des lignes) :

```php
massifs_gestionnaires( bool $inclure_suspendus = true ): array
// list< array{
//   id:           int,
//   identifiant:  string,
//   nom_affiche:  string,
//   email:        string,
//   suspendu:     bool,
//   suspendu_le:  string|null   // ISO 8601 UTC, ou null si jamais suspendu
// } >
// Trié par `nom_affiche`.

massifs_nom_auteur( int $user_id ): string
// Nom d'affichage pour une ligne d'historique.
// Renvoie 'Auteur inconnu' si l'identifiant ne résout pas.
// Ne renvoie JAMAIS une chaîne vide. Non échappé.
```

`massifs_nom_auteur()` existe parce que `wp_massifs_statuts.auteur_id` est un `bigint` **sans clé
étrangère** : un identifiant peut ne plus résoudre, et l'historique doit rester lisible sans que #15
invente un libellé de repli.

### Classes exposées (espace de noms)

```php
Massifs\Security\Roles\Capacites::ROLE_GESTIONNAIRE
Massifs\Security\Roles\Capacites::PUBLIER
Massifs\Security\Roles\Capacites::HISTORIQUE
Massifs\Security\Roles\Capacites::GERER
Massifs\Security\Roles\Capacites::toutes(): array
Massifs\Security\Roles\Suspension::est_suspendu( int $user_id ): bool

Massifs\Security\Roles\Comptes::creer( array $donnees ): int|WP_Error
Massifs\Security\Roles\Comptes::suspendre( int $user_id ): true|WP_Error
Massifs\Security\Roles\Comptes::retablir( int $user_id ): true|WP_Error
Massifs\Security\Roles\Comptes::reinitialiser( int $user_id, bool $reinitialiser_2fa = false ): true|WP_Error

Massifs\Security\Auth\Sessions::detruire( int $user_id ): void
Massifs\Security\Auth\Sessions::detruire_autres( int $user_id ): void
Massifs\Security\Auth\Motdepasse::valider( string $mdp, ?WP_User $u = null ): true|WP_Error
Massifs\Security\Auth\GardeRest::peut_publier( WP_REST_Request $r ): true|WP_Error
Massifs\Security\Auth\GardeRest::peut_consulter_historique( WP_REST_Request $r ): true|WP_Error
Massifs\Security\Auth\GardeRest::peut_gerer_gestionnaires( WP_REST_Request $r ): true|WP_Error
```

---

## Routes REST

**#13 n'enregistre aucune route.** Elle fournit deux choses aux chaînes qui en enregistrent : des
`permission_callback` partagés, et un garde global fail-closed.

### `permission_callback` partagés — à utiliser tels quels

Forme procédurale, utilisable depuis un module sans espace de noms (`includes/rest/public/` est dans ce
cas : `public` est un mot réservé de PHP) :

```php
'permission_callback' => 'massifs_rest_peut_publier',
'permission_callback' => 'massifs_rest_peut_consulter_historique',
'permission_callback' => 'massifs_rest_peut_gerer_gestionnaires',
```

Retour : `true`, ou

```
WP_Error( 'massifs_droits_insuffisants', <message français>, array( 'status' => 401|403 ) )
   401  utilisateur non authentifié
   403  authentifié mais capacité absente, compte suspendu, ou 2FA requise et non enrôlée
```

### Règle exacte du garde global

Filtre `rest_request_before_callbacks`, priorité 10, 3 arguments.

```
SI  $reponse est déjà un WP_Error                       -> laisser passer intact
SI  ltrim(route,'/') ne commence pas par 'massifs'      -> laisser passer
SI  méthode ∈ { GET, HEAD, OPTIONS }                    -> LAISSER PASSER  (§5.4 lecture publique)
SINON exiger :
      is_user_logged_in()
  ET  current_user_can( au moins une de massifs_capacites_massifs() )
  ET  NON ( 2FA requise pour ce compte ET non enrôlée )
SINON WP_Error( 'massifs_ecriture_non_autorisee' | 'massifs_2fa_requise', 401|403 )
```

Quatre points qui vous concernent directement :

- **Le filtre par méthode est porteur, pas cosmétique.** `massifs/v1` est un espace de noms **partagé** :
  `includes/rest/public/route-statuts.php` y déclare `GET /massifs/v1/statuts` avec
  `'permission_callback' => '__return_true'`, imposé par le §5.4 du brief (données ouvertes) et
  consommé par la carte. Filtrer par espace de noms seul casserait l'open data et la carte.
- **Le préfixe testé est `massifs`, pas `massifs/v1`.** Si #14 ou #15 choisit `massifs-portail/v1`,
  le garde mord quand même.
- **Vos routes en écriture sont refusées aux anonymes même si vous oubliez votre `permission_callback`.**
  Ce n'est pas une permission d'oublier — voir l'interdit 4.
- **Vos routes en LECTURE ne sont pas couvertes par ce filet.** L'historique est une lecture sensible :
  #15 **doit** poser `massifs_rest_peut_consulter_historique` sur ses routes `GET`.

Un compte suspendu est refusé **automatiquement** et sans code dédié : le résolveur `user_has_cap` lui a
déjà retiré ses capacités `massifs_*`.

Le nonce des requêtes REST authentifiées par cookie reste géré par le cœur (`rest_cookie_check_errors`,
en-tête `X-WP-Nonce`). **Ne le réimplémentez pas.**

---

## États spéciaux

**Sans objet.** Cette issue ne produit aucune donnée de statut et n'émet aucun état de la table
canonique (`information_indisponible`, `hors_saison`, `donnee_perimee`, `couche_effis_indisponible`).
Elle ne les lit pas davantage.

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` | Hors périmètre de #13 | — |
| `hors_saison` | Hors périmètre de #13 | — |
| `donnee_perimee` | Hors périmètre de #13 | — |
| `couche_effis_indisponible` | Hors périmètre de #13 | — |

Les seuls états introduits par #13 sont des **états de compte**, internes au portail et jamais visibles
d'un visiteur : `actif` / `suspendu`, et `2FA enrôlée` / `2FA requise non enrôlée`.

---

## Chaînes fournies par le serveur

**Aucune chaîne publique.** Toutes les chaînes de #13 sont des messages d'administration ou
d'authentification, en français, jamais vus par un visiteur du site public. Elles ne reproduisent
aucun libellé officiel de la préfecture — **#13 n'a aucune surface de fait de domaine**, et n'invente
donc aucun libellé de niveau, aucune consigne, aucune couleur.

Les seules chaînes contractuelles, parce qu'un test de recette les vérifie :

| Contexte | Chaîne | Raison d'être contractuelle |
|---|---|---|
| Échec de connexion, identifiant inexistant **ou** mot de passe faux | `Identifiant ou mot de passe incorrect.` | Doit être **rigoureusement identique** dans les deux cas — sinon le formulaire énumère les comptes |
| Compte suspendu | `Ce compte est suspendu. Contactez un administrateur.` | Distincte, et émise **seulement après** validation des identifiants |
| Verrouillage anti-force-brute | message indiquant **le délai d'attente** | L'utilisateur légitime doit savoir combien de temps attendre |

---

## Interdits

Adressés d'abord aux chaînes #14 et #15, qui construisent contre ce document.

1. **Ne jamais tester `in_array( 'massifs_gestionnaire', $user->roles, true )`.** Testez la **capacité**.
   L'administrateur porte `massifs_publier_statuts` et `massifs_consulter_historique` sans porter le rôle :
   un test par rôle exclurait l'administrateur de son propre portail.
2. **Ne jamais enregistrer une entrée de menu exigeant une capacité hors `massifs_*`** — surtout pas
   `manage_options` ni `edit_posts`. Une telle entrée serait **invisible au gestionnaire** et la ligne de
   DoD « mise à jour complète en moins d'une minute » tomberait sans la moindre erreur PHP.
   Corollaire : **il n'existe aucune liste d'autorisation de menu, par choix** (voir arbitrage A-6).
   Ne cherchez pas à vous y inscrire, il n'y a rien où s'inscrire.
3. **Ne jamais utiliser `rest_authentication_errors`**, ni aucun filtre global d'authentification REST,
   pour rejeter les anonymes. Ce filtre court-circuite `WP_REST_Server::dispatch` pour **toute** l'API :
   il renverrait 401 sur `GET /massifs/v1/statuts`, cassant le §5.4 et la carte. C'est le réflexe naturel
   et il est faux.
4. **Ne jamais déclarer une route REST sans `permission_callback` réel** en comptant sur le garde global.
   Le garde est un filet, pas la documentation de votre route — et il ne couvre pas vos routes en lecture.
5. **Ne jamais écrire les user metas `massifs_compte_suspendu*` ni `massifs_totp_*`.** Passez par
   `Roles\Comptes::suspendre()` / `retablir()` : elles détruisent les sessions et journalisent, ce qu'un
   `update_user_meta` nu ne fait pas.
6. **Ne jamais appeler `wp_delete_user()` sur un compte gestionnaire.** Bloqué par `map_meta_cap`, et pour
   une raison de domaine : `wp_massifs_statuts.auteur_id` est un `bigint` **sans clé étrangère**, la
   réattribution native de WordPress ne couvre que les types de contenu, jamais une table sur mesure.
   Supprimer un compte orphelinerait l'historique que le §4.2 exige de conserver intégralement.
   Le §6 énumère **créer / suspendre / réinitialiser** — jamais supprimer.
7. **Ne jamais recréer, retirer ni modifier le rôle `massifs_gestionnaire`**, ni appeler `add_cap` /
   `remove_cap` sur `administrator`. `Roles\Installation` en est propriétaire exclusif.
8. **Ne jamais afficher, journaliser ni exporter** un secret TOTP, un code de secours, une clé d'écluse
   ou une adresse IP. **L'export CSV de l'historique (#15) ne contient aucune de ces valeurs.**
9. **N'écrivez aucun contrôle d'expiration de session.** Sortie unique par `Auth\Sessions`.
10. Rappels de frontière inchangés : le thème n'appelle jamais une source externe ni une fonction
    d'ingestion, ne calcule jamais une règle métier, et l'extension n'émet **aucun HTML de présentation
    publique** — les écrans de `wp-admin` étant explicitement le domaine de l'extension.

---

## Arbitrages

Aucun désaccord entre deux plans à réconcilier : l'issue est entièrement côté extension, un seul
leaddev a donc été lancé (voir arbitrage A-0). Les arbitrages ci-dessous sont les décisions que j'ai
tranchées entre les options du brainstorm et les propositions du plan.

| # | Sujet | Décision | Raison |
|---|---|---|---|
| **A-0** | Un seul leaddev, pas de gel croisé | `leaddev-front-cms` non lancé ; `dev-front-cms` et `dev-ux-cms` non lancés ; `dev-integration-cms` non applicable | L'empreinte est à 100 % dans l'extension. Aucun fichier de thème, aucun CSS, aucune couture front↔back. Lancer une chaîne front aurait produit un plan sans objet |
| **A-1** | Rôle administrateur | **Aucun rôle sur mesure.** `administrator` du cœur reçoit les capacités `massifs_*` par `add_cap`, **persistées** | Le jeu de capacités de `administrator` grandit à chaque version majeure de WordPress ; un clone figé devient un administrateur dégradé sans que personne le remarque avant l'écran blanc. Le §6 dit « Administrateur : **tout**, y compris… » — « tout » = le rôle du cœur, le « y compris » est une capacité **en plus**, pas un rôle en moins |
| **A-2** | L'administrateur porte-t-il aussi `publier` et `historique` ? | **Oui, les trois** | #14 et #15 gardent leurs écrans sur la capacité. Un administrateur incapable de publier serait absurde. Point contractuel majeur : c'est ce qui rend l'interdit 1 nécessaire |
| **A-3** | Mécanisme de suspension | **User meta**, refus dans `authenticate`, `destroy_all()`, **plus** un résolveur `user_has_cap` qui retire les capacités `massifs_*` | Quatre mécanismes pesés. La suppression est exclue par le §4.2 (orphelinage de `auteur_id`). `user_status` est morte dans le cœur depuis la fusion MU. Le décapage de capacités perd l'état d'origine. Le résolveur `user_has_cap` est l'emprunt qui rend la suspension **incontournable** par un cookie survivant ou un chemin d'écriture oublié par une chaîne sœur — c'est lui qui transforme une suspension déclarative en suspension réelle |
| **A-4** | Résidu assumé de la suspension | La méta ne survit pas à une désactivation de l'extension. **Accepté et écrit, pas tu** | Extension désactivée = plus d'écran de publication, plus de routes portail, plus de capacités `massifs_*`. Un gestionnaire suspendu atterrirait sur un tableau de bord vide avec `read` et rien d'autre |
| **A-5** | Persistance des capacités | **Persistées**, via le mécanisme `massifs_core_signature_schema` / `massifs_core_installation` déjà en service | Ce mécanisme existant est idempotent et **rejoué quand la signature change** : il annule le seul vrai défaut des capacités persistées, la dérive. L'alternative (tout résoudre dynamiquement par `user_has_cap`) laisse `get_role()->capabilities` vide, ce qui envoie sur une fausse piste quiconque débogue « le gestionnaire ne peut pas publier ». Le projet privilégie le plat au malin. `installer()` **réconcilie** : il ajoute les capacités déclarées absentes et **retire les `massifs_*` non déclarées**, pour qu'une capacité renommée ne traîne pas |
| **A-6** | Réduction du menu d'administration | **Rien à écrire.** Aucune passe `admin_menu`, aucune liste d'autorisation, aucun slug en dur | C'était le plus gros risque de couplage de l'issue, et la bonne réponse est de ne rien faire. WordPress construit le menu **à partir des capacités** : avec `read` + les trois `massifs_*`, Articles, Pages, Médias, Commentaires, Apparence, Extensions, Utilisateurs, Outils, Réglages et Mises à jour disparaissent seuls. Il ne reste que Tableau de bord et Profil, **tous deux nécessaires** (cible de redirection après connexion ; changement de mot de passe et enrôlement 2FA). Résidu réel : aucun. Une liste d'autorisation aurait été **fail-closed sur un contrat que #14 et #15 écrivent à l'aveugle** — une sœur qui oublie de s'inscrire voit son écran devenir silencieusement invisible au gestionnaire. Inacceptable en lot parallèle |
| **A-7** | Deuxième facteur | **TOTP maison, saisie manuelle du secret base32. Pas de QR, pas de bibliothèque, pas d'extension `two-factor`** | `hash_hmac` est dans PHP ; l'encodage base32 et la vérification tiennent en ~50 lignes. Écrire un encodeur QR en PHP pur ferait 600 à 900 lignes pour une valeur nulle. Une extension tierce contredirait l'argumentaire même du site (§1 : prouver qu'on sait construire un portail sécurisé **sur mesure**) et livrerait, sur la page de connexion que le §8 impose de valider, une interface que nous n'auditons pas. **Et la saisie manuelle est meilleure en accessibilité** : un lecteur d'écran lit un secret en texte, il ne lit pas un QR code. L'URI `otpauth://` est affichée à côté, copiable |
| **A-8** | Codes de secours et rampe d'enrôlement | **Non négociables, implémentés AVANT l'application de la 2FA** | Sans eux, l'issue livre un mécanisme d'**auto-enfermement** : 2FA imposée aux administrateurs + téléphone perdu = production définitivement inaccessible. Un administrateur requis mais non enrôlé est **redirigé** vers l'enrôlement, **jamais refusé**. 10 codes à usage unique, hachés par `wp_hash_password`, affichés une seule fois |
| **A-9** | Périmètre de la 2FA | **Imposée à `administrator`, disponible (opt-in) au gestionnaire** | Lecture du §6 « double authentification **disponible** et **active pour les administrateurs** », confirmée **indépendamment** par le brainstorm et par le leaddev. Le §6 interdit de l'imposer au gestionnaire : le compte de démonstration a ses **identifiants publiés** et une promesse de « scénario complet en moins de 2 minutes » qu'une 2FA rendrait impossible |
| **A-10** | Emplacement de l'écran d'enrôlement | **La page de profil (`profile.php`), pas une page de menu** | Évacue trois problèmes d'un coup : aucune entrée de menu, donc **zéro collision** avec les menus que #14 et #15 écrivent à l'aveugle ; `profile.php` est toujours atteignable avec `read` ; l'enrôlement optionnel du gestionnaire y trouve sa place naturelle. Aucun `add_menu_page`, aucun `add_submenu_page`, aucun slug en dur |
| **A-11** | Stockage de l'anti-force-brute | **Séparé en deux, selon la conséquence d'une éviction** : compteurs d'échecs en **transients** (un par clé, TTL = fenêtre) ; **verrous actifs en registre borné en option** (`autoload = false`, 100 entrées, ordre d'insertion) | C'est l'arbitrage le plus fin du plan, et il refuse de recopier `Verrou.php` sans le vérifier. `Verrou.php` a choisi l'option parce qu'un transient est évincible sous pression du cache objet. Ici les prémisses s'inversent **partiellement** : la conséquence d'une éviction est bien pire (un compteur évincé **désactive silencieusement** la protection), mais le chemin d'écriture l'est aussi — l'écluse écrit **à chaque échec**, depuis potentiellement des centaines d'IP, ce qui ferait d'un registre partagé unique un **read-modify-write non atomique** où deux échecs concurrents s'écrasent, plus une amplification d'écriture sur `wp_options`. La ligne de partage est posée là où la conséquence change de nature : perdre un **incrément** est immatériel (l'attaquant franchit le seuil une fraction de seconde plus tard), perdre un **verrou** ne l'est pas |
| **A-12** | Clés de comptage | **IP hachée** (`hash_hmac` + `wp_salt('auth')`) en clé primaire ; **couple (identifiant × IP hachée)** en clé secondaire. **Jamais d'IP en clair** | Le §9 n'admet que le traitement des comptes internes. Le hachage garde le compteur et abandonne la capacité forensique, dont nous n'avons pas besoin : ce n'est pas un SIEM. `REMOTE_ADDR` seul, validé ; `X-Forwarded-For` **jamais** honoré par défaut — un en-tête falsifiable rendrait l'écluse triviale à contourner |
| **A-13** | Jamais de verrouillage par identifiant seul | **Interdit, motif écrit en majuscules dans le code** | Les identifiants du compte de démonstration sont **publiés** (§6). Un compteur par identifiant permettrait à n'importe quel visiteur de désactiver la démonstration en dix requêtes ratées — un sabotage à un clic de l'argumentaire commercial du site |
| **A-14** | Ordre des filtres `authenticate` | `Ecluse::barrer` **1**, cœur 20, `AccesCompte` 30, `Ecluse::constater` 40, `MessageConnexion` 45, `Deuxfacteurs` 50, **`Ecluse::reaffirmer` 100** | Contractuel. Le refus de compte suspendu n'agit **que si `$utilisateur instanceof WP_User`**, sinon la suspension deviendrait un oracle d'existence de compte. **Voir A-14 bis : la rédaction initiale de cet arbitrage était fausse.** |
| **A-14 bis** | **Comment le verrou est réellement opposé** — correctif du 16 août 2026, sur défaut bloquant remonté par `test-integration-cms` | **Deux mécanismes, pas un.** (1) En priorité 1, `barrer()` **désarme les trois rappels de mot de passe du cœur** (`wp_authenticate_username_password`, `wp_authenticate_email_password`, `wp_authenticate_application_password`, tous en priorité 20) **uniquement dans la branche verrouillée**, puis les **réarme** ; (2) en **priorité 100** — après `wp_authenticate_spam_check` du cœur, qui est en 99 — `reaffirmer()` **renvoie le refus quoi qu'il arrive**, y compris face à un `WP_User` valide. En complément, `constater()` (40) **ne purge jamais les compteurs tant qu'un verrou est actif** | **La rédaction initiale d'A-14 affirmait deux choses fausses** : « rejette sans jamais vérifier le mot de passe » et « aucun oracle ». Rendre un `WP_Error` en priorité 1 **ne bloquait rien** : `wp_authenticate_username_password()` ne se court-circuite que sur un `WP_User` (« `if ( $user instanceof WP_User ) { return $user; }` »), **un `WP_Error` entrant est purement ignoré**, et le cœur enchaînait sur `get_user_by()` + `wp_check_password()` en rendant le compte si le mot de passe était bon. Le verrou était écrit en base et **jamais opposé** : la ligne de DoD §12 « force brute bloquée » était fausse. Le désarmement (1) est une mesure de **coût** — c'est lui, et lui seul, qui tient la promesse « aucun hachage calculé sous verrou ». La réaffirmation (2) est la mesure d'**accès** : elle ne dépend du comportement d'aucun autre rappel, donc elle tient même si une extension réenregistre un rappel de mot de passe ou si l'ordre des priorités bouge. Le garde-fou sur `constater()` ferme le piège corollaire : sans lui, un mot de passe **correct** présenté pendant un verrou faisait produire un `WP_User` par le cœur, que `constater()` interprétait comme un succès et qui **purgeait le verrou** — quiconque connaissait le mot de passe pouvait ainsi effacer le verrou. **Vérifié dans la stack qui tourne, pas par lecture** : verrou levé au 5ᵉ échec et **tenu au 6ᵉ** ; bon mot de passe pendant verrou → refusé en `massifs_trop_de_tentatives`, `user_id = 0`, **aucune session** ; verrou **survivant** (895 s) ; hors verrou, connexion normale et rappels du cœur **réarmés en priorité 20**. **A-13 intact** : ni les clés, ni les seuils, ni les fenêtres, ni le HMAC n'ont été touchés |
| **A-15** | Expiration d'inactivité | **Volontairement absente**, et dit plutôt que sous-entendu | Les cookies WordPress sont à expiration **absolue**, pas glissante. Une vraie inactivité exigerait un `last_seen` contrôlé à chaque requête d'administration, soit une écriture en base par vue de page — coûteux sur mutualisé, pour un faible apport face à un plafond absolu de 4 h sur une tâche d'une minute par jour. « Sessions expirantes » (§6) est honnêtement tenu par l'expiration absolue courte. Le crochet `massifs_auth_inactivite_max` est déclaré, **implémentation non écrite** |
| **A-16** | Destruction des sessions | Point de passage unique `Auth\Sessions::detruire()`, appelé **explicitement** sur suspension, réinitialisation administrateur, changement de rôle, changement de secret TOTP | C'est **la** ligne qui porte la DoD. Une suspension qui laisse vivre la session en cours n'est pas une suspension : le compte continuerait de publier des statuts pendant des heures. Ne jamais se reposer sur le comportement implicite du cœur, qui varie selon le chemin d'appel. Distinction retenue : un changement de mot de passe **à l'initiative de l'utilisateur** appelle `detruire_autres()` — le déconnecter de son propre navigateur en pleine action serait hostile |
| **A-17** | Anti-rejeu TOTP | `massifs_totp_dernier_pas` : un code n'est accepté que si son pas est **strictement supérieur** au dernier mémorisé | Exigence RFC 6238 §5.2, et c'est exactement la différence entre une vraie 2FA et un jeton rejouable pendant 90 secondes. Sans ce champ, un code intercepté vaut trois tentatives. Verrouillé par le scénario de recette 13 |
| **A-18** | Fermetures ajoutées hors énoncé | **Mots de passe d'application et XML-RPC fermés** ; **message d'erreur de connexion uniformisé** | Sans elles, deux lignes de la DoD seraient **fausses**. Les mots de passe d'application et XML-RPC authentifient en Basic auth et **ne traversent jamais `wp-login.php`** : cocher « 2FA active pour les admins » en les laissant ouverts est un mensonge. Et le cœur distingue `invalid_username` de `incorrect_password`, ce qui offre l'énumération des comptes sur le formulaire et divise par deux la valeur du verrouillage. Les deux sont dans `security/auth/**`, donc dans l'empreinte. **Conséquence pour #14 et #15** : toute écriture REST se fait en **cookie + `X-WP-Nonce`**, jamais en mot de passe d'application |
| **A-19** | Suppression d'un gestionnaire | **Bloquée** par `map_meta_cap`, pas seulement « non proposée » | Voir interdit 6. Échappatoire documentée : filtre `massifs_autoriser_suppression_gestionnaire`, défaut `false` |
| **A-20** | Écran de gestion des comptes | **Aucun écran dédié.** Greffes par filtres sur l'écran *Utilisateurs* du cœur | `CLAUDE.md` placerait un tel écran dans `includes/admin/comptes/`, chemin **hors de mon empreinte et non attribué dans ce lot**. Les greffes (`manage_users_columns`, `user_row_actions`, `bulk_actions-users`) sont des **filtres enregistrables depuis mes propres fichiers** : elles donnent ~90 % de la valeur de démonstration pour ~10 % du coût et **zéro octet écrit hors empreinte**. `user-new.php` du cœur crée déjà un utilisateur avec le rôle « Gestionnaire des massifs » — il n'y a rien à écrire pour l'action « créer ». Le §6 exige que les trois actions **existent**, pas qu'elles aient leur propre écran |
| **A-21** | Journal des évènements de compte | Registre borné en option, **statut explicite : repli** ; action `massifs_compte_evenement` émise | La ligne « les trois actions sont journalisées » ne doit dépendre d'**aucune** chaîne sœur. Si #15 construit une vraie table d'audit, elle s'abonne à l'action et le registre devient redondant sans gêner |
| **A-22** | Nom de la classe anti-force-brute | **`Ecluse`**, jamais `Verrou` | `Massifs\Security\Alertes\Verrou` existe déjà. La collision de vocabulaire aurait coûté cher en relecture, alors même que les deux espaces de noms la rendent légale |
| **A-23** | Secret TOTP : chiffré, jamais haché | **Chiffrement réversible au repos** (`aes-256-gcm`, clé dérivée de `wp_salt('secure_auth')`, IV aléatoire, format `v1:base64(iv\|tag\|chiffré)`). **Les codes de secours, eux, sont hachés** (`wp_hash_password`) | Distinction non négociable et souvent ratée : la vérification TOTP **recalcule** un HMAC à partir du secret, elle a donc besoin du secret **en clair au moment du contrôle**. Un secret haché rendrait la 2FA structurellement inopérante. Un code de secours, lui, est seulement **comparé** : il doit être haché. Périmètre de protection énoncé sans exagération — cela protège d'une divulgation par **injection SQL seule**, pas d'une compromission complète du système de fichiers, puisque le sel vit dans `wp-config.php`. **Faire tourner `SECURE_AUTH_SALT` invalide tous les secrets** ; les codes de secours sont alors le chemin de récupération |

### Valeurs proposées, en attente de validation du propriétaire

**Ce ne sont pas des faits du brief.** Le §6 dit « mots de passe forts imposés », « sessions expirantes »,
« tentatives limitées » sans donner une seule valeur. Les chiffres ci-dessous sont un **jugement
d'ingénierie**, tous filtrables, et signalés comme tels dans le rapport au lot. Ils ne sont pas propagés
comme des faits.

| Paramètre | Valeur retenue | Filtre |
|---|---|---|
| Session administrateur | 4 h | `massifs_auth_duree_session` |
| Session gestionnaire | 8 h | `massifs_auth_duree_session` |
| « Se souvenir de moi » | plafonné à 12 h (case **conservée**) | `massifs_auth_duree_session` |
| Longueur minimale de mot de passe | 12 caractères, **aucune règle de composition** | `massifs_auth_longueur_mot_de_passe` (plancher dur 8) |
| Écluse — IP | 10 essais / 15 min → verrou 15 min | `massifs_auth_ecluse_seuils` |
| Écluse — palier IP | 20 essais / 1 h → verrou 1 h | `massifs_auth_ecluse_seuils` |
| Écluse — couple identifiant × IP | 5 essais / 15 min → verrou 15 min | `massifs_auth_ecluse_seuils` |
| Temporisation progressive | `min($echecs, 5)` s, à partir du 2ᵉ échec | `massifs_auth_ecluse_temporisation` |

Le défaut du cœur (48 h, 14 jours avec « se souvenir de moi ») est sans rapport avec une tâche
quotidienne d'une minute. **Aucune règle de composition** de mot de passe : doctrine
longueur-plutôt-que-complexité (NIST SP 800-63B) — les règles de composition produisent `Motdepasse1!`
et rien d'autre.

---

## Clés de user meta et options

**Lecture autorisée pour les chaînes sœurs, écriture strictement réservée à #13** (interdit 5).

| Clé de user meta | Type | Écrite par |
|---|---|---|
| `massifs_compte_suspendu` | `'1'` ou absente | `Roles\Suspension` |
| `massifs_compte_suspendu_le` | ISO 8601 UTC | `Roles\Suspension` |
| `massifs_compte_suspendu_par` | int | `Roles\Suspension` |
| `massifs_totp_secret` | chaîne **chiffrée** `v1:…` | `Auth\SecretUtilisateur` |
| `massifs_totp_actif` | `'1'` ou absente | `Auth\SecretUtilisateur` |
| `massifs_totp_active_le` | ISO 8601 UTC | `Auth\SecretUtilisateur` |
| `massifs_totp_dernier_pas` | int | `Auth\SecretUtilisateur` |
| `massifs_totp_codes_secours` | `list<string>` **hachés** | `Auth\SecretUtilisateur` |
| `massifs_totp_codes_secours_genere_le` | ISO 8601 UTC | `Auth\SecretUtilisateur` |

**Secret ≠ actif** : un secret généré mais jamais confirmé ne verrouille personne. Seule
`massifs_totp_actif` déclenche la demande de second facteur.

| Option | `autoload` | Contenu |
|---|---|---|
| `massifs_ecluse_verrous` | `false` | registre borné (100) des verrous actifs, clés HMAC |
| `massifs_journal_comptes` | `false` | registre borné (200) des évènements de compte |
| `wp_user_roles` | *cœur* | modifiée par `Roles\Installation` **uniquement** |

Transients : `massifs_ecluse_c_{hash}` (compteurs), `massifs_2fa_{sha1(jeton)}` (étape 2, TTL 300 s),
`massifs_2fa_prov_{user_id}` (secret provisoire d'enrôlement, TTL 900 s). Pour les deux derniers, le
transient est le **bon** choix et pour la raison inverse de l'écluse : une éviction y fait **échouer
fermé** (l'utilisateur ressaisit son mot de passe).

---

## Action émise — point d'accroche pour #15

```php
do_action( 'massifs_compte_evenement', array(
  'type'            => string,   // compte_cree | compte_suspendu | compte_retabli | compte_reinitialise
  'cible_id'        => int,
  'cible_login'     => string,
  'acteur_id'       => int,
  'instant_iso_utc' => string,   // ISO 8601 UTC
  'details'         => array,
) );
```

#15 **peut** s'y abonner pour alimenter son audit. Elle n'y est pas obligée : #13 journalise déjà de son
côté (A-21).

---

## Hors périmètre de #13 — à attribuer, sinon la DoD garde des trous

Ces points appartiennent au §9 du brief mais **ne sont réalisables dans aucune empreinte de ce lot**.
Ils sont listés ici pour qu'aucune revue n'ait à les redécouvrir, et remontés au lot.

| Sujet | Pourquoi pas #13 | Où ça doit aller |
|---|---|---|
| `DISALLOW_FILE_EDIT` | Constante de `wp-config.php`, hors de **toute** empreinte du lot | Issue #16 `securite` |
| En-têtes de sécurité stricts (CSP, HSTS…) | `.htaccess` / configuration serveur | Issue `infra` |
| Blocage de `xmlrpc.php` au niveau serveur | #13 le neutralise **fonctionnellement** (`xmlrpc_enabled`, `xmlrpc_methods`), mais le fichier répond toujours | Issue `infra` |
| **Avatars Gravatar dans `wp-admin`** | Le tableau de bord peut émettre des URL `secure.gravatar.com`, **une requête navigateur vers un domaine tiers**, que le §12 interdit « sur tout le site ». Le correctif (`show_avatars`, filtre `get_avatar_url`) est hors empreinte | À attribuer — **et à trancher : la preuve de recette du §12 couvre-t-elle `wp-admin` ?** |
| Énumération d'utilisateurs publique (`?author=1`, `/wp/v2/users`, sitemap auteurs) | Surface publique, hors `security/auth/**`. **Exception traitée par #13** : le message d'erreur de connexion, qui est de l'authentification | Issue #16 `securite` |
| **`docker/provision/provision.sh` créerait un rôle `gestionnaire`** au lieu de `massifs_gestionnaire`, et y rattacherait le compte de démonstration | Hors empreinte de #13. **Si exact, le compte de démo du §6 n'a aucune capacité de portail** et la démonstration publique ne démontre rien | À traiter au niveau lot |
| Scénario 21 de `recette-rendu.mjs` (attend un 200 sur `/wp-admin/users.php` en session admin) | **L'interstitiel 2FA le fera échouer** tant que l'administrateur de recette n'est pas enrôlé, ou tant que `MASSIFS_DESACTIVER_2FA` n'est pas posée dans l'environnement de recette. Comportement **voulu**, pas une régression | À arbitrer au niveau lot ; #13 ne touche pas la recette |
| HTTPS, sauvegardes, mises à jour automatiques | Exploitation | Infra |
| Re-provisionnement du secret TOTP de l'administrateur de la démo à chaque remise à zéro | **#13 crée la contrainte**, l'issue démo la porte : sans cela la démo se verrouille toute seule | Issue démo |
| Le compte de démonstration peut-il changer son propre mot de passe ? | Entre deux remises à zéro, un visiteur pourrait le changer et rendre la démo inaccessible. #13 ne code **aucun cas particulier de la démo** et n'inscrit **aucun identifiant de démo en dur** | Issue démo |
