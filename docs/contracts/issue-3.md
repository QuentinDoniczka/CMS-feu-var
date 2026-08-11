# Contrat d'interface — Issue #3 — Modéliser les statuts quotidiens, la légende officielle et la fraîcheur des données

**Gelé le** 11 août 2026 par `lead-issue-cms` (chaîne #3) · **Révision 2** · **Statut** : contraignant.

> **Révision 2 — la légende n'est plus inconnue.** La chaîne #1 a livré
> `docs/decisions/source-prefecture.md`, qui établit la légende officielle du 13 par trois relevés
> concordants. Elle est **binaire** (accès autorisé / interdit), pas graduée sur 5 crans comme le
> supposait `MASTER.md` §4.1. Les sections touchées portent la mention **[R2]**. Les substituts
> `À CONFIRMER` de la révision 1 sont **caducs** pour les libellés, les couleurs, les bornes de saison
> et l'heure de publication ; ils restent ouverts pour les consignes.

Ce contrat est la frontière entre l'extension `massifs-core` (qui possède la donnée et les chaînes) et
tout consommateur — thème, portail, future couche REST, chaînes sœurs #1 (ingestion préfecture) et #2
(référentiel des massifs). Une divergence constatée en revue est un défaut, pas une variante.

**Périmètre de l'issue #3** : le socle du domaine uniquement. Aucune route REST, aucun écran de portail,
aucun cron, aucun rôle, aucun HTML.

---

## Fonctions de lecture exposées par l'extension

Toutes préfixées `massifs_`, toutes retournant des **tableaux associatifs** (jamais des objets : le
consommateur ne se couple à aucune classe, la future route REST sérialise sans adaptateur, et un tableau
copié par valeur ne peut pas muter le domaine).

**Aucune fonction de lecture ne retourne `null` ni `false`. Jamais.**

### `massifs_statuts_du_jour( array $codes_massifs, ?string $jour = null ): array`

`$jour` au format `YYYY-MM-DD`, jour civil `Europe/Paris` ; `null` → `massifs_jour_courant()`.
Format de date invalide → `InvalidArgumentException` (erreur de programmation de l'appelant ; une
coercition silencieuse vers « aujourd'hui » masquerait un bug §4.2).

Retour : tableau **clé = `massif_code`**, dans **l'ordre de fourniture des codes**, **exactement une
entrée par code fourni** — y compris pour un code inconnu du référentiel ou malformé, qui reçoit une
entrée en `indisponible`. Liste de codes vide → `array()` sans requête SQL.

| Clé | Type | Renseignée quand |
|---|---|---|
| `massif_code` | `string` | toujours |
| `etat` | `string` — `disponible` \| `non_encore_publie` \| `indisponible` \| `hors_saison` | toujours |
| `jour_validite` | `string` `YYYY-MM-DD` | **toujours — c'est le jour DEMANDÉ**, jamais celui d'une ligne d'un autre jour |
| `niveau` | `array` \| `null` | **non `null` uniquement si `etat === 'disponible'`** |
| `zapef` | `array` \| `null` | **[R2]** idem — seconde dimension publiée (voir ci-dessous) |
| `source` | `string` \| `null` | idem — `recuperation_officielle` \| `saisie_manuelle` |
| `auteur_id` | `int` \| `null` | idem, et `null` si `source === 'recuperation_officielle'` |
| `publie_prefecture_le` | `string` ISO 8601 UTC \| `null` | idem, `null` si la source ne l'a pas fourni |
| `enregistre_le` | `string` ISO 8601 UTC \| `null` | idem |
| `statut_id` | `int` \| `null` | idem |

**[R2] Le `level` brut de la source n'est jamais exposé au thème.** `niveau_source_brut` (0-4) et
`procedure_source` (0-1) sont **persistés** — c'est la vérité de la source, la perdre est irréversible —
mais n'apparaissent dans **aucune** forme de retour publique. Décision doc §8.5 : « le thème ne doit
jamais afficher `brut`, `niveau_source` ou `procedure_source` », et ne doit jamais traduire lui-même un
entier en libellé.

Sous-tableau `niveau` — forme identique à une entrée de `massifs_legende()['niveaux']` :

| Clé | Type | Valeur |
|---|---|---|
| `cle` | `string` | **[R2]** Clé **texte stable** : `autorise` \| `interdit`. Jamais un entier de position |
| `libelle` | `string` | **[R2]** Libellé officiel **verbatim** : `Accès au massif autorisé` \| `Accès au massif interdit` |
| `consigne` | `string` | **Chaîne vide** — la légende officielle ne porte **aucune** consigne (décision doc §4.4, Q4 ouverte). Jamais `null`, jamais inventée |
| `severite` | `int` | **[R2]** `10` (autorisé) \| `20` (interdit) — croissante. Comparable ; jamais une identité, jamais un rang |
| `motif` | `string` | Clé de motif. Valeurs actuelles : `aucun` (autorisé) \| `hachure_croisee` (interdit) |
| `jeton_css` | `string` | **[R2]** `--statut-autorise` \| `--statut-interdit`. Aucun hex ne traverse la frontière |
| `jeton_encre_css` | `string` | **[R2]** `--statut-autorise-encre` \| `--statut-interdit-encre` |
| `rang` | `int` | Position 1-based — **affichage seulement**, jamais persisté, jamais comparé entre deux dates |
| `total` | `int` | Nombre de niveaux de la légende courante (**2** en R2) |

**[R2] Sous-tableau `zapef`** — seconde dimension officiellement publiée (Zones d'Accueil du Public en
Forêt : des **points**, pas des surfaces). Mêmes clés que `niveau` :

| `cle` | `libelle` (verbatim officiel) | `severite` | `jeton_css` |
|---|---|---|---|
| `autorise` | `Accès à la ZAPEF* autorisé` | `10` | `--statut-zapef-autorise` |
| `interdit` | `Accès à la ZAPEF* interdite` | `20` | `--statut-zapef-interdit` |

**Reproduire les libellés tels quels, fautes comprises** : `autorisé` est au masculin et `interdite` au
féminin dans la source ; c'est ce que publie la préfecture. La note de bas de légende
`*ZAPEF : Zones d’Accueil du Public en Forêt` emploie une apostrophe **typographique U+2019**, les
autres chaînes une apostrophe droite. Elle est exposée en `massifs_legende()['zapef_note']`.

### `massifs_statut_du_jour( string $code_massif, ?string $jour = null ): array`
Confort : une seule entrée, forme ci-dessus. Ne retourne jamais `null`.

### `massifs_synthese_du_jour( array $codes_massifs, ?string $jour = null ): array`
Existe pour que le thème **ne recalcule jamais** la sémantique « accès autorisé = niveau le moins sévère ».

`jour_validite` `string` · `etat_global` `string` (même vocabulaire que `etat` ; `disponible` dès qu'au
moins un massif a une donnée pour ce jour) · `partiel` `bool` · `total` `int` · `disponibles` `int` ·
`sans_donnee` `int` · `par_niveau` `array<string,int>` (**toutes** les clés de la légende présentes, à `0`
si nulle) · `niveau_le_moins_severe` `string|null` · `niveau_le_plus_severe` `string|null`.

### `massifs_legende(): array` · `massifs_legende_est_confirmee(): bool`

`confirmee` `bool` (**[R2] `true`** — libellés et couleurs établis par trois relevés concordants) ·
`consignes_publiees` `bool` (**[R2] `false`** — la légende officielle ne porte aucune consigne) ·
`revision` `string` · `source` `string` · `source_officielle_url` `string` ·
`niveaux` `list<array>` **ordonnée par sévérité croissante** (forme ci-dessus) ·
`zapef` `list<array>` **[R2]** · `zapef_note` `string` **[R2]** ·
`publication_heure` `string` **[R2]** (`'17:00'`, Europe/Paris, la veille du jour de validité) ·
`etats_hors_niveau` `array<string,array>` de clés `indisponible`, `hors_saison`, `non_encore_publie`,
chacune `['cle','motif','jeton_css']` — **structure seulement, aucune phrase**.

Rien dans la forme de retour ne dépend d'un nombre de niveaux figé : le passage de 5 substituts à 2
niveaux réels s'est fait **sans toucher au domaine**, uniquement dans `legende.config.php`.
**Aucun filtre WordPress n'altère la légende** : un filtre laisserait fabriquer une légende non officielle.

**[R2] `consignes_publiees === false`** : le thème n'affiche **aucun** intitulé « Consigne » tant que ce
drapeau est faux. Une consigne vide n'est pas une donnée manquante à combler, c'est un fait : le
dispositif du 13 n'en publie pas.

### [R2] Correspondance `level` brut → niveau affiché — la seule table à modifier

`massifs_niveaux_source_autorises(): array` · `massifs_procedures_source_autorisees(): array`

Exposées pour la couche 4 de validation de la chaîne #1 (décision doc §7.1 et §8.6) : la liste blanche
des `level` et `procedure` acceptables vit **dans notre configuration versionnée**, jamais en constante
dans le code de l'ingestion. Retour : `[0,1,2,3,4]` et `[0,1]`.

| `level` brut | `niveau_cle` | `zapef_cle` | État résolu |
|---|---|---|---|
| `0` | `null` | `null` | **`indisponible`** — « aucune donnée », jamais « autorisé par défaut » |
| `1` | `autorise` | `autorise` | `disponible` |
| `2` | `autorise` | `autorise` | `disponible` |
| `3` | `interdit` | `autorise` | `disponible` |
| `4` | `interdit` | `interdit` | `disponible` |

Cette table est **une donnée de `legende.config.php`**, pas du code. Si le propriétaire arbitre en faveur
d'une granularité plus fine (décision doc §9-Q1), seule cette table et la liste `niveaux` changent : le
`level` brut restant persisté, les lignes passées se re-projettent sans perte.

### `massifs_fraicheur( ?string $jour = null ): array`

`jour_validite` `string` · `dernier_releve_le` ISO 8601 UTC \| `null` · `dernier_releve_source` `string` ·
`age_secondes` `int|null` · `seuil_secondes` `int` (`86400`) · `perimee` `bool` · `publie_prefecture_le`
ISO 8601 UTC \| `null` · `dispositif_actif` `bool` · `evalue_le` ISO 8601 UTC.

`perimee === true` **uniquement si** `dispositif_actif === true` **et** (`dernier_releve_le === null`
**ou** `age_secondes > seuil_secondes`).

### `massifs_saison( ?string $jour = null ): array`
`jour` · `active` `bool` (calendrier seul) · `debut` · `fin` · `prochaine_ouverture` (**toujours une date,
jamais `null`**) · `confirmee` `bool` (`false` — question 7 de MASTER.md §4.1 ouverte).

### `massifs_jour_courant(): string` · `massifs_jour_suivant(): string`
`YYYY-MM-DD`, jour civil `Europe/Paris`. **Seule source légitime du jour.**

### [R2] `massifs_attribution_statuts(): array`
Relaie `\Massifs\Ingest\Prefecture\Connector::attribution()` **si la classe existe**, sinon retombe sur
les valeurs fixées par le §9 du brief et la décision doc §8.7 — qui ne sont pas une invention mais des
chaînes imposées :
`texte` = `D'après les publications de la préfecture des Bouches-du-Rhône` ·
`carte_officielle_url` = `https://www.risque-prevention-incendie.fr/13` ·
`bulletin_url_modele` = `https://www.risque-prevention-incendie.fr/static/13/import_data/{AAAAMMJJ}.pdf`.

Le thème **ne rédige jamais** cette chaîne ni cette URL à la main (décision doc §8.5). Le bulletin PDF
est **lié, jamais récupéré ni re-servi**.

### [R2] Clause absolue, à inscrire mot pour mot dans le code (décision doc §8.4)

> **L'API de lecture est indexée exclusivement par date. Aucune fonction « dernier statut connu » sans
> argument de date ne doit exister.**

Elle est déjà satisfaite : `$jour` optionnel est **immédiatement résolu** en date explicite, `Depot` lie
`jour_validite = %s`, et aucune méthode « dernier instantané » n'existe. La clause est reproduite en
commentaire dans `includes/domain/statuts/api.php` pour empêcher sa réintroduction.

### [R2] Couture d'intégration avec la chaîne #1 (décision doc §8.3)

C'est **la chaîne #3 qui s'abonne** à `massifs_prefecture_snapshot_enregistre` et projette l'instantané
dans le modèle de statuts. Le connecteur n'écrit jamais dans notre modèle. L'abonné **valide strictement
la forme reçue** et, si elle ne correspond pas, **n'écrit rien** et journalise — une projection
approximative écrirait une donnée fausse, ce que le §4.2 interdit.

### `massifs_horodatage( string $instant_iso_utc ): array`
`['iso', 'attr_datetime', 'date_longue' => 'mardi 11 août 2026', 'heure' => '19 h 04', 'date_courte']`,
converti en `Europe/Paris`, noms de mois et de jours **français en dur**. Chaîne invalide →
`InvalidArgumentException`.

## Fonctions d'écriture exposées (chaîne #1 et futur portail)

```php
massifs_enregistrer_statut( array $statut ): array
massifs_enregistrer_statuts( array $statuts ): array
massifs_enregistrer_releve_reussi( string $source_cle, ?string $instant_iso_utc = null ): array
```

- **Aucune fonction d'update, aucune fonction de delete n'existe nulle part.** `$wpdb` n'apparaît que dans
  `Depot.php`, et son vocabulaire se limite à `insert`, `prepare`, `get_results`, `get_row`, `insert_id`.
  Ni `UPDATE`, ni `DELETE`, ni `REPLACE`, ni `TRUNCATE`, en méthode comme en SQL littéral.
- Ces fonctions **ne vérifient aucune capability** : l'authentification et l'autorisation appartiennent à
  l'appelant (route REST avec un vrai `permission_callback`, ou écran admin avec `current_user_can` +
  nonce). À écrire en toutes lettres dans le docbloc de chaque fonction.
- Retour d'écriture : `enregistre` `bool` · `id` `int|null` · `erreurs` `list<string>` de **clés stables**
  (`massif_code_invalide`, `jour_validite_invalide`, `jour_validite_hors_horizon`, `niveau_inconnu`,
  `source_invalide`, `auteur_requis`, `auteur_interdit`, `publie_prefecture_le_invalide`,
  `echec_insertion`) — des clés, jamais des phrases : la rédaction appartient à l'appelant.
- Aucune exception pour une donnée invalide : une source externe qui envoie n'importe quoi ne doit pas
  tuer un cron.
- `enregistre_le` est posé par le domaine, **jamais** fourni par l'appelant.
- `massifs_enregistrer_releve_reussi()` n'est appelée **qu'après un relevé réussi et validé**. Un échec
  n'écrit rien, sinon la fraîcheur mentirait — exactement ce que le §4.5 interdit.

## Routes REST

**Aucune dans cette issue.** Les formes de retour ci-dessus sont conçues pour être `wp_json_encode`ables
telles quelles par une future route `GET /massifs/v1/statuts`, sans adaptateur ni renommage de clé. Aucun
consommateur ne doit planifier d'appel REST contre l'issue #3.

## Hooks exposés aux chaînes sœurs

| Nom | Type | Signature | Pour qui |
|---|---|---|---|
| `massifs_core_amorcage` | action | — | Tous les modules chargés, autoloader armé |
| `massifs_core_signature_schema` | filtre | `array $signatures` | Un module déclare `'<module>' => '<version>'` pour forcer le rejeu de l'installation |
| `massifs_core_installation` | action | `string $signature_precedente` | Un module crée/migre sa table. **Handler idempotent obligatoire** |
| `massifs_statut_enregistre` | action | `int $id, array $statut` | Journalisation |
| `massifs_statuts_publies` | action | `array $codes, string $jour` | **Invalidation du cache de page** (§10 du brief) |

**Aucun filtre n'altère la légende, un statut, la fraîcheur ou la saison.**

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `information_indisponible` (`etat === 'indisponible'`) | **[R2] Deux causes, un seul état** : (a) aucune ligne pour ce jour, en saison, jour ≤ aujourd'hui ; (b) **une ligne existe mais la source a publié `level` 0**, c'est-à-dire « aucune donnée » | MASTER §11.3 : « Information du jour non disponible. Consultez la carte officielle de la préfecture. » + lien. L'ardoise affiche « INDISPONIBLE », **jamais un chiffre de la veille**. Le thème ne distingue pas (a) de (b) : les deux sont une absence d'information |
| `hors_saison` (`etat === 'hors_saison'`) | Dispositif inactif ce jour-là **et** aucune donnée | MASTER §11.3 : « Dispositif estival inactif. Reprise le {date}. » avec `massifs_saison()['prochaine_ouverture']` |
| `non_encore_publie` (`etat === 'non_encore_publie'`) | Jour demandé futur, rien de publié | Le sélecteur « demain » reste accessible et annonce l'absence de publication. **Libellé officiel = question 6 de MASTER §4.1, OUVERT** |
| `donnee_perimee` | **Pas un `etat`** : `massifs_fraicheur()['perimee'] === true` | Bannière MASTER §8.3, **superposée** aux statuts qui restent affichés. Ne masque jamais une donnée valide |
| `couche_effis_indisponible` | **Hors périmètre de l'issue #3** | Aucune donnée fournie par ce contrat |

**Obligation faite au consommateur** : filtrer sur `etat` avec un `match()` **sans `default`**. Un
`if/else` avec branche « sinon » est un défaut : il rendrait silencieux l'ajout d'un cinquième état.

## Chaînes fournies par le serveur

Le serveur possède la **donnée** et les **libellés officiels reproduits** ; le thème possède la
**rédaction éditoriale**. Frontière exacte :

| Fourni par l'extension | Composé par le thème |
|---|---|
| `niveau['libelle']` — libellé de niveau (substitut `À CONFIRMER`) | Les 5 chaînes fixes de MASTER §11.3 (non-officialité, fraîcheur, indisponible, hors saison, EFFIS) |
| `niveau['consigne']` — consigne officielle (**vide** tant que non confirmée) | Le gabarit de la phrase de fraîcheur, rempli avec les valeurs du serveur |
| `massifs_horodatage()` — `date_longue`, `heure`, `date_courte` en `Europe/Paris` | Tout HTML, tout échappement |
| `legende['source']`, `legende['source_officielle_url']` | Les attributions rédigées du §9 du brief |
| Toutes les clés d'erreur d'écriture | La phrase d'erreur affichée |

**Le thème ne compose jamais une date lui-même** : il consomme `massifs_horodatage()`. Motif : la stack est
en UTC (`provision.sh` ne pose pas `timezone_string`), `wp_date()`/`date_i18n()`/`current_time()`
produiraient l'heure UTC et dépendraient du pack de langue, et MASTER §11.1 règle 6 impose un format
strict (« mardi 11 août 2026 », « 19 h 04 » avec espace insécable, jamais de `:`).

## Interdits

**Pour le thème et tout consommateur**

1. Interroger `$wpdb` ou la table `wp_massifs_statuts`.
2. Instancier ou appeler une classe `Massifs\` (`Depot`, `Statuts`, `Legende`, `Horloge`…). **Seules les
   fonctions `massifs_*` sont publiques.**
3. Calculer « aujourd'hui » ou « demain » lui-même (`date()`, `current_time()`, `strtotime('today')`).
4. Formater une date sans passer par `massifs_horodatage()`.
5. Fabriquer un libellé de niveau, une consigne, un ordre de sévérité ou une couleur.
6. Écrire une valeur hexadécimale de statut ailleurs que dans `assets/css/tokens.css`.
7. Afficher un niveau quand `etat !== 'disponible'`.
8. Rejouer un statut de la veille, le mémoriser en session/localStorage, ou afficher un chiffre d'ardoise
   quand `etat_global !== 'disponible'`.
9. Traiter `perimee` comme un masque : la bannière s'ajoute aux statuts, elle ne les remplace pas.
10. Appeler une fonction d'ingestion ou contacter une source externe depuis le navigateur.
11. Utiliser un `if/else` avec branche « sinon » sur `etat`.
12. Compter sur `consigne` non vide tant que `massifs_legende_est_confirmee() === false` — afficher alors
    « Légende en cours de vérification » (MASTER §4.1).

**Pour l'extension**

13. L'extension n'émet **aucun HTML de présentation publique** et **aucune phrase destinée au visiteur**.
    Seule exception assumée : `libelle` et `consigne` des niveaux, qui sont de la donnée officielle
    reproduite, pas de la rédaction.
14. L'extension n'émet **aucune valeur hexadécimale** : uniquement des **noms de jetons CSS**.
15. Le domaine `statuts` ne connaît, n'appelle et ne valide jamais `Massifs\Domain\Massifs` (chaîne #2) :
    `massif_code` est une chaîne opaque validée sur sa **forme** (`/^[a-z0-9_-]{1,64}$/`), jamais sur son
    existence.
16. `Statuts` ne référence jamais `Fraicheur` ni `RegistreReleves` : les deux règles de fraîcheur ne se
    rencontrent nulle part dans le code.
17. Aucun `require` nommé en dur d'un fichier appartenant à une chaîne sœur dans `massifs-core.php`.

## Arbitrages

| # | Désaccord ou point ouvert | Décision retenue | Raison |
|---|---|---|---|
| A-1 | Nom des jetons CSS : `--statut-n1` (mon brief) vs `--statut-1` (MASTER §12) | **`--statut-1` … `--statut-5`, `--statut-N-encre`, `--statut-indisponible`** | MASTER §12 est normatif et fige le contenu exact de `tokens.css`. Un nom de jeton qui n'existe pas dans `tokens.css` ne produit aucune couleur. `--statut-n1` était un exemple illustratif de mon brief, pas une décision. **Contrainte ajoutée** : le nom du jeton est une **donnée de configuration par niveau**, jamais calculé depuis une position — sinon un passage de 5 à 4 niveaux casserait le mapping en silence |
| A-2 | Où vivent les couleurs de la légende ? | **Les hex ne sont PAS dans l'extension.** La config PHP déclare le nom du jeton CSS ; `tokens.css` porte le pigment | MASTER §12 : « aucun autre fichier ne définit de custom property ». Une source pour la sémantique (l'extension), une pour le pigment (le design system), **zéro duplication de hex** donc zéro dérive possible. Conséquence assumée : la tâche « couleurs » de l'issue #3 est satisfaite par le **mapping**, pas par les valeurs — celles-ci restent la propriété du design system |
| A-3 | `massifs_horodatage()` : le serveur formate-t-il les dates, ou le thème ? | **Le serveur. Fonction retenue et rendue obligatoire** | Règle « le serveur possède la donnée et les chaînes ». Un formatage côté thème passerait par `wp_date()`/`date_i18n()`, qui rendent l'heure **UTC** dans cette stack et dépendent du pack de langue — et MASTER §11.1 règle 6 impose un format strict. Le formatage d'une date est ici une règle métier (fuseau), pas de la présentation |
| A-4 | Ordre de résolution : donnée d'abord ou saison d'abord ? | **Donnée d'abord.** `HorsSaison` n'est émis que si le calendrier dit inactif **ET** qu'aucune ligne valide n'existe | Si la préfecture prolonge le dispositif au-delà du 30 septembre, le site affiche le vrai statut au lieu de mentir. **La donnée bat le calendrier ; l'absence de donnée n'invente jamais de statut.** Les deux branches ne se recouvrent jamais, l'ordre est donc sûr |
| A-5 | Seuil de 24 h : une règle ou deux ? | **Deux règles disjointes.** §4.2 = absolue, sans seuil, sur `jour_validite` ≠ jour demandé. §4.5 = seuil 24 h sur l'**instant du dernier relevé réussi**, et déclenche **uniquement** la bannière | Les fusionner produirait soit des faux positifs, soit un trou de sécurité. `massifs_statuts_du_jour()` ne consulte jamais `Fraicheur` : `perimee` ne peut donc pas masquer une donnée valide |
| A-6 | Fuseau horaire | **`Europe/Paris` figé en constante du domaine**, indépendamment du réglage WordPress | `provision.sh` ne pose pas `timezone_string` : l'option vaut UTC et « aujourd'hui » basculerait à 2 h du matin heure de Paris — bug §4.2 livré clé en main. Un administrateur ne doit pas pouvoir décaler le jour de validité depuis Réglages → Général |
| A-7 | Colonne `portee` (piéton / circulation / travaux) | **Pas de colonne `portee`** | La question 5 de MASTER §4.1 est ouverte ; l'ajouter serait inventer une dimension de domaine non confirmée. La table étant en insertion pure et sans données de production, l'ajout ultérieur est purement additif. **Seul point ouvert qui ferait bouger le schéma** |
| A-8 | Table d'audit séparée pour le §6 ? | **Non.** La table des statuts est simultanément l'historique intégral et le journal d'écriture ; « ancienne → nouvelle valeur » se dérive de la ligne précédente du même couple (massif, jour) | Deux tables signifieraient deux vérités possibles |
| A-9 | Chargement des modules des chaînes sœurs | **Autoloader `Massifs\` → `includes/` + découverte d'un seul chemin prédit `<couche>/<module>/module.php`**, sur une liste de couches fixe et ordonnée | Un `glob` récursif chargerait un fichier à moitié écrit par une chaîne sœur ; un `ParseError` de fichier inclus **n'est pas rattrapable par `try/catch`** → écran blanc sur tout le site, pour les trois chaînes. Composer est rejeté (surface tierce, aucune étape `composer install` dans la stack) |
| A-10 | `register_activation_hook` suffit-il ? | **Non.** Option `massifs_schema_version` comparée sur `plugins_loaded` + `do_action('massifs_core_installation')` idempotent | La stack Docker a **déjà activé** le plugin : le hook d'activation ne se redéclenchera jamais et la table ne serait jamais créée. Le filtre `massifs_core_signature_schema` permet aux chaînes sœurs de déclencher l'installation de **leur** table sans modifier `massifs-core.php`, dont je suis seul propriétaire |
| A-11 | Clé de niveau : entier de position ou texte ? | **Clé texte stable** (`n1`…), persistée telle quelle. `rang` existe pour l'affichage uniquement | Si le dispositif passe de 4 à 5 niveaux, un entier de position ferait changer de sens **toutes les lignes passées en silence** — corruption rétroactive et irréparable du §4.2 |
| A-12 | Q0 du brainstorm : re-scoper la tâche 2 de l'issue ? | **Déjà tranché en amont**, donc pas une question bloquante | Mon mandat prescrit explicitement la légende **entièrement paramétrée** avec marquage `À CONFIRMER`. « Reproduire la légende » signifie ici « construire la paramétrisation et la peupler de substituts marqués », pas « inventer des valeurs officielles » |

### Arbitrages de la révision 2 — après le relevé officiel de la chaîne #1

| # | Point | Décision retenue | Raison |
|---|---|---|---|
| A-13 | Légende binaire réelle vs 5 crans de `MASTER.md` §4.1 | **La légende réelle gagne : 2 niveaux d'accès + une dimension ZAPEF.** Les 5 crans substituts sont supprimés de la configuration | Le §4.2 du brief impose de reproduire **exactement** la légende officielle. Les libellés distinguant `level` 1 de 2, ou 3 de 4, **ne sont pas publiés** : les afficher serait une invention. La granularité d'affichage reste une **donnée de configuration** : si le propriétaire arbitre en faveur d'une granularité plus fine (décision doc §9-Q1), la table de correspondance change, le domaine non |
| A-14 | Que persiste-t-on : le `level` brut, le niveau affiché, ou les deux ? | **Les deux.** Colonnes `niveau_source_brut` (0-4) et `procedure_source` (0-1) persistées et **jamais exposées** ; `niveau_cle` et `zapef_cle` résolus à l'écriture | Le `level` brut est la vérité de la source : le perdre est **irréversible**, et c'est lui qui rendra possible une re-projection si la granularité change. Le niveau affiché est résolu à l'écriture pour qu'une saisie manuelle (qui n'a pas de `level`) et une récupération officielle produisent la même forme en lecture |
| A-15 | `level` 0 : ligne absente, ou ligne présente sans niveau ? | **Ligne présente, `niveau_cle` et `zapef_cle` à `NULL`, état résolu `indisponible`.** `niveau_cle` devient **nullable** | « La source a publié qu'elle n'a pas d'information » et « la source n'a rien publié » sont deux faits différents qu'il faut pouvoir distinguer en base — mais **un seul et même état pour le visiteur**, parce que dans les deux cas nous n'avons pas l'information. `level` 0 n'est **jamais** « autorisé par défaut » |
| A-16 | ZAPEF à `level` 0 : la source affiche un marqueur vert (« ouverte ») | **Nous affichons `indisponible`, pas « ouverte »** | Le JS officiel peint la ZAPEF en vert dès `level >= 0`, donc y compris quand il n'a aucune donnée. Reproduire ce comportement afficherait comme une information ce qui est une absence d'information — violation directe du §4.2. Nous reproduisons la **légende** (les libellés, les teintes), pas les **défauts de rendu** de la source |
| A-17 | Noms des jetons CSS après le passage à 2 niveaux | **`--statut-autorise` / `--statut-interdit`** (et `--statut-zapef-*`), et **non** `--statut-1` / `--statut-2` | `tokens.css` fait aujourd'hui de `--statut-2` un **jaune**. Réutiliser `--statut-2` pour « interdit » peindrait les massifs interdits en jaune jusqu'à ce que quelqu'un s'en aperçoive : un échec **silencieux et dangereux**. Un jeton inexistant ne produit **aucune** couleur — un échec **bruyant**, que la chaîne front voit immédiatement. Sur une donnée de sécurité, l'échec bruyant est toujours le bon choix. Amende A-1 |
| A-18 | Contraste AA de `#22B14C` et `#E63A3C` | **Reproduire la teinte officielle ; la conformité AA est portée par le liseré charbon 2 px et le motif obligatoire** de `MASTER.md` §4.1 | Pré-arbitré par l'orchestrateur, non rouvert. C'est la seule lecture qui satisfait simultanément le §4.2 (reproduire la légende) et le §8 (AA bloquante). Ces hex **n'entrent pas dans l'extension** : ils sont consignés ici pour la chaîne front, qui les portera dans `tokens.css` |
| A-19 | Chargement du sous-arbre de la chaîne #1 | **La découverte par convention essaie `module.php` puis, à défaut, `bootstrap.php`** — premier trouvé, un seul chargé | La chaîne #1 a nommé son amorce `bootstrap.php`, pas `module.php` : son sous-arbre n'était chargé par personne. Généraliser la convention à deux noms candidats corrige le câblage **sans nommer `ingest/prefecture` nulle part** et sans `require` en dur — l'interdit n° 17 reste tenu, et l'absence du répertoire reste sans effet |
| A-20 | L'autoloader doit-il mapper `Massifs\Ingest\Prefecture\*` ? | **Non — exclusion explicite en tête d'autoloader** | Demandé par la décision doc §8.1 : ce sous-arbre utilise le nommage WPCS `class-*.php`, délibérément non PSR-4, et se charge lui-même. L'exclusion **nomme** un espace de noms sœur mais ne peut provoquer aucun fatal error s'il est absent — c'est un `return`, pas un `require` |
| A-21 | Constantes attendues par la chaîne #1 | **`MASSIFS_CORE_DIR` et `MASSIFS_CORE_FILE` définies en alias** de `MASSIFS_CORE_CHEMIN` et `MASSIFS_CORE_FICHIER` | Elles sont documentées « facultatives, repli prévu si absentes ». Les définir coûte deux lignes et supprime un risque d'intégration. Les noms français restent les canoniques du projet |
| A-22 | Saison et heure de publication | **`saison.confirme => true`** (1er juin – 30 septembre **inclus**) · `publication_heure = '17:00'` Europe/Paris | Établis par trois relevés concordants (titre officiel, texte de page, comportement du flux : `20260601` → 200, `20260531` → 404). L'heure de 17 h corrige le « 18 h » de deux pages officielles, contredit par `fr.json`, le bulletin PDF et l'en-tête `Last-Modified`. Reste à re-vérifier **chaque 1er juin** (décision doc §10) |

## Valeurs `À CONFIRMER` — bloquantes avant mise en ligne, non bloquantes pour cette issue

**[R2] Six des dix points de la révision 1 sont désormais ÉTABLIS.** État à jour :

| # | Valeur | Statut R2 | Où elle vit |
|---|---|---|---|
| 1 | Nombre de niveaux | **ÉTABLI — 2** (accès autorisé / interdit) + dimension ZAPEF | `legende.config.php` |
| 2 | Libellés officiels exacts | **ÉTABLI**, verbatim depuis le `fr.json` officiel | `legende.config.php` |
| 3 | Codes couleur exacts | **ÉTABLI** — vert `#22B14C`, rouge `#E63A3C` | **Hors extension** — `tokens.css`, chaîne front |
| 7 | Dates du dispositif | **ÉTABLI** — 1er juin au 30 septembre **inclus** | `legende.config.php`, `saison.confirme => true` |
| — | Heure de publication | **ÉTABLI — 17 h 00 Paris**, la veille | `legende.config.php` |
| — | Correspondance `level` → niveau | **ÉTABLIE** par le code de rendu officiel | `legende.config.php` |
| 4 | **Consignes officielles** | **TOUJOURS OUVERT** — la légende officielle n'en publie aucune, et l'arrêté préfectoral est un PDF numérisé sans couche de texte (illisible). `consigne` = **chaîne vide**, `consignes_publiees => false` | Décision doc §9-Q4 |
| 5 | Circulation / stationnement / travaux | **TOUJOURS OUVERT** — les travaux relèvent d'un dispositif et d'une carte séparés ; circulation/stationnement absents de la source. Toujours **pas de colonne `portee`** (A-7) | Décision doc §9-Q4 |
| 6 | Libellé « demain non encore publié » | **RÉSOLU AUTREMENT** — le 404 est le signal ; notre formulation est fixée par MASTER §11.3, celle de la source n'est **pas** recopiée | Décision doc §4.6 |
| 8 | Autorisation de réutilisation | **TOUJOURS OUVERT et bloquant avant mise en production** — aucune mention légale, aucune CGU, aucune licence, `robots.txt` en 404 | Décision doc §9-Q6 |
| 9 | Horizon d'écriture `MASSIFS_STATUT_HORIZON_JOURS = 2` | Garde-fou conservé, cohérent avec une publication J+1 à 17 h | `api.php` |
| 10 | Créneaux horaires programmés | Non observés ; le modèle append-only absorbe une republication en cours de journée | — |
| 11 | Sémantique de `procedure` et de `zm` | **OUVERT** — `procedure` est **persisté sans être exposé** ; `zm` n'est pas consommé par cette issue | Décision doc §9-Q2 |
| 12 | 27 identifiants du flux vs 25 noms publiés | **OUVERT** — sans effet ici : `massif_code` est opaque et jamais validé contre un référentiel | Décision doc §9-Q3 |

**Source unique de la légende** : `wp-content/plugins/massifs-core/includes/domain/statuts/legende.config.php`
pour la sémantique (nombre de niveaux, clés, libellés officiels, consignes, sévérité, motifs, noms de
jetons, dimension ZAPEF, table de correspondance `level` → niveau, listes blanches `level`/`procedure`,
bornes de saison, heure de publication, drapeaux de confirmation) et
`wp-content/themes/massifs/assets/css/tokens.css` pour le pigment. Deux fichiers, deux préoccupations
disjointes, **aucune valeur dupliquée entre les deux**.

## Dépendances hors empreinte — signalées, non traitées par cette chaîne

| # | Élément | Attendu | Destinataire |
|---|---|---|---|
| 1 | `docker/provision/provision.sh` | Ajouter `wp option update timezone_string 'Europe/Paris'` (idempotent). N'affecte pas le domaine, qui fige déjà le fuseau — évite seulement que l'admin affiche des heures UTC au gestionnaire | `docker-cms` |
| 2 | **[R2]** `themes/massifs/assets/css/tokens.css` | Doit définir `--statut-autorise` = **`#22B14C`**, `--statut-interdit` = **`#E63A3C`** (teintes officielles relevées au pixel), leurs `-encre`, `--statut-zapef-autorise`, `--statut-zapef-interdit`, `--statut-indisponible`, `--statut-lisere`. Les jetons `--statut-1`…`--statut-5` de MASTER §12 sont **caducs**. Conformité AA portée par le liseré charbon 2 px + le motif obligatoire, **pas** par la teinte (A-18) | chaîne front |
| 2b | **[R2]** `design-system/MASTER.md` §4.1, §8.1, §12 | **Révision requise** : le tableau à 5 niveaux gradués, ses motifs, ses ratios de contraste et les jetons `--statut-1`…`--statut-5` ne correspondent pas au dispositif réel du 13, qui est binaire. Les 8 questions bloquantes du §4.1 sont majoritairement répondues par `docs/decisions/source-prefecture.md`. **Hors de mon empreinte** | `lead-design-cms` |
| 3 | `includes/domain/massifs/` (chaîne #2) | Fournit la liste des `massif_code`. **Contrainte dure** : les codes doivent matcher `/^[a-z0-9_-]{1,64}$/` | chaîne #2 |
| 4 | `includes/ingest/prefecture/` (chaîne #1) | Appelle `massifs_enregistrer_statuts()` puis `massifs_enregistrer_releve_reussi('prefecture')`, **uniquement après validation de la charge utile**. N'écrit jamais dans la table directement | chaîne #1 |
| 5 | `.phpcs.xml` | Les fichiers de classe sont en PascalCase (imposé par l'autoloader), pas en `class-*.php` | `refacto-cms` |
| 6 | Cache de page / REST | Se brancher sur `massifs_statuts_publies` pour l'invalidation (§10 du brief) | chaîne perf / REST |
