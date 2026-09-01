# MASSIFS — Accès du jour aux massifs forestiers (Bouches-du-Rhône)

Site WordPress public, **thème sur mesure + extension dédiée**, qui affiche le statut d'accès quotidien
des massifs forestiers du 13 sur une carte interactive, avec équivalent textuel accessible et portail
sécurisé de mise à jour.

**Source de vérité produit : [`docs/BRIEF.md`](docs/BRIEF.md).** Tout agent lit le brief avant d'agir.
Le brief décrit le QUOI ; le COMMENT appartient à `brainstorm-massifs` et aux deux leaddev.

---

## Les 4 contraintes non négociables

Elles conditionnent chaque décision technique. Un agent qui en viole une doit s'arrêter et le signaler.

| # | Contrainte | Conséquence concrète |
|---|-----------|----------------------|
| 1 | **WordPress, thème sur mesure + extension dédiée** | Aucun page builder, aucun thème tiers/par défaut, aucun framework CSS générique (Bootstrap, Tailwind…). CSS écrit à la main. |
| 2 | **Zéro requête navigateur vers un domaine tiers** | Tuiles OSM, polices, JS carto, images : tout auto-hébergé. Sources externes (préfecture, Météo-France, EFFIS) consommées **côté serveur** via cron, mises en cache, re-servies depuis notre domaine. |
| 3 | **Site utilisable JavaScript désactivé** | Les statuts sont dans le HTML rendu par PHP. La carte est un enrichissement progressif, jamais un prérequis. |
| 4 | **Rendu « atelier », jamais « template »** | Le design suit `design-system/MASTER.md` produit par `lead-design-massifs` : palette nommée ancrée garrigue/calcaire/pin/DFCI, 2 familles typo auto-hébergées, un élément signature. **Registre borné par la négative** : jamais ludique, jamais « produit grand public », jamais registre marketing ou landing page — le rendu vise un **décideur communal** (l'élu ou le directeur général des services qui évalue une offre), ce qui n'autorise ni thème acheté, ni kit UI, ni esthétique « template institutionnel » ; l'ancrage garrigue/calcaire/pin/DFCI du §7 du brief reste entier. |

Deux règles transverses de même niveau :

- **Accessibilité AA bloquante** (§8 du brief) — contrastes, clavier, focus visible, Échap ferme les panneaux, jamais d'info portée par la couleur seule, 200 % de zoom, 360 px sans scroll horizontal.
- **Zéro cookie et zéro donnée personnelle côté public** (§2, §9) — pas de commentaire, pas de formulaire, pas de traceur, donc pas de bandeau de consentement.

Et une règle de sécurité produit : **ne jamais présenter un statut périmé comme courant.** Sans donnée
valide pour le jour → « information non disponible, consultez la carte officielle » (lien), sur la carte
ET dans la liste.

---

## Architecture cible

```
wp-content/
├── themes/massifs/          # thème sur mesure — présentation uniquement
│   ├── templates/           # rendu serveur des statuts (fonctionne sans JS)
│   ├── assets/css/          # CSS écrit à la main, tokens du design system
│   ├── assets/js/           # enrichissement progressif (carte)
│   ├── assets/fonts/        # 2 fichiers max, auto-hébergés
│   └── assets/vendor/       # Leaflet & co, vendorisés, jamais de CDN
└── plugins/massifs-core/    # extension dédiée — domaine + données + portail
    ├── includes/domain/     # massifs, niveaux, statuts, fraîcheur, saison
    ├── includes/ingest/     # préfecture, Météo-France, EFFIS, tuiles
    ├── includes/rest/       # endpoints publics (lecture) et portail (écriture)
    ├── includes/admin/      # écran gestionnaire, historique, comptes
    └── includes/security/   # rôles/capabilities, nonces, throttling, 2FA
```

**Frontière stricte** : le thème n'interroge jamais une source externe et ne contient aucune règle
métier. L'extension ne produit aucun HTML public de présentation. Elle expose des données au thème via
des fonctions de lecture et l'API REST.

---

## Domaines fonctionnels (labels des issues GitHub)

`referentiel` · `statuts` · `carte` · `meteo` · `effis` · `portail` · `securite` · `a11y` · `design` · `perf` · `infra` · `contenu`

---

## Chaîne d'agents

Commande orchestratrice : **`/lead-CMS`**. Elle ne code jamais et n'exécute aucune chaîne elle-même :
elle constitue un lot de 3 issues, lance **3 chaînes complètes en parallèle**, puis valide le lot.

```
                            /lead-CMS
                                │
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
  lead-issue-cms          lead-issue-cms          lead-issue-cms
     issue #A                issue #B                issue #C
        │                       │                       │
  brainstorm-cms             (idem)                  (idem)
  leaddev-back-cms ∥ leaddev-front-cms
  gel du contrat  →  docs/contracts/issue-<n>.md  (obligatoire si deux faces, cf. bullet ci-dessous)
  dev-back-cms ∥ dev-front-cms ∥ dev-ux-cms
  refacto-cms
  dev-integration-cms        (si thème ET extension touchés)
  git-cms commit
        │                       │                       │
        └───────────────────────┼───────────────────────┘
                                ▼
                  niveau lot, une seule fois :
        test-integration-cms → review-cms → docker-cms
                → git-cms push → github-boards
```

- Chaque `lead-issue-cms` porte **une seule issue** de bout en bout, dans son propre contexte — c'est ce qui préserve la qualité par rapport à un orchestrateur unique qui ferait tout.
- `lead-design-cms` tourne **une seule fois** au bootstrap (et sur révision explicite) — il produit `design-system/MASTER.md`, préalable à tout travail visuel.
- `docker-cms` tourne au bootstrap (création de la stack) et en fin de lot (vérification).
- **Gel du contrat facultatif en mono-face** : `docs/contracts/issue-<n>.md` est obligatoire dès qu’une issue a deux faces (thème et extension doivent s’accorder sur une clé, une chaîne ou une forme de données). Il est facultatif pour une issue mono-face (infra, outillage, documentation, `.gitignore`, `.gitattributes`, CI) — la chaîne consigne alors son raisonnement dans l’en-tête du fichier livré et dans le corps du commit. Voir `docs/decisions/projet-vitrine.md` (tranché à l’issue #79).
- **Lots de 3 issues maximum** (contrainte de tokens). Parallèle uniquement si les empreintes fichiers des 3 issues sont disjointes ; sinon séquentiel. Arbre de travail unique, aucune isolation.
- **Pas de tests unitaires.** Un seul agent de test, une fois par lot, en intégration front+back dans Docker.

---

## Conventions

- **Langue** : français pour l'interface, le contenu, les commits et les échanges avec l'utilisateur ; anglais dans les prompts d'agents.
- **Git** : **mono-branche**. Tout est commité et poussé directement sur `main` — pas de branche feature, pas de PR, pas de worktree. Remote : `git@github.com:QuentinDoniczka/CMS-feu-var.git`.
  Conséquence : les chaînes parallèles partagent le même arbre de travail. La **disjonction des empreintes fichiers** est la seule protection contre l'écrasement mutuel — une chaîne n'écrit jamais hors de son empreinte, et les commits mi-lot passent par `commit-scoped` avec une liste de fichiers explicite.
- **Fins de ligne** : `.gitattributes` impose LF sur `*.sh`, `*.conf` et `.htaccess`, mais **git n'applique un attribut qu'au moment où il écrit un fichier** — une copie de travail clonée avant ce correctif reste en CRLF, et `git status` la voit propre. Un script shell en CRLF meurt sur une erreur de syntaxe et rend toute la recette inexécutable. Dérive à vérifier par `git ls-files --eol | grep 'w/crlf' | grep 'eol=lf'` ; `git add --renormalize` ne la corrige pas (il réécrit l'index, déjà en LF, et rend la main en succès silencieux). Voir `docs/decisions/fins-de-ligne-copie-de-travail.md` (tranché à l'issue #78).
- **Commits** : Conventional Commits en français, scope = domaine fonctionnel, référence d'issue en fin de sujet (`feat(carte): afficher les massifs du jour (closes #12)`).
- **PHP** : WordPress Coding Standards, préfixe `massifs_` / namespace `Massifs\`, `declare(strict_types=1)`, échappement systématique en sortie (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), assainissement systématique en entrée, `$wpdb->prepare` pour toute requête.
- **Licence** : GPL v2 or later pour le thème et l'extension.
- **Ambiguïté** : question au propriétaire du projet, jamais d'invention silencieuse (règle en tête du brief) — sauf pour une question de fait réel sans réponse accessible (MASSIFS est un projet vitrine, sans commanditaire réel) : la chaîne retient alors l'hypothèse techniquement la plus défendable, en écrit le motif dans l'issue, et avance. Voir `docs/decisions/projet-vitrine.md` (tranché à l'issue #19).
