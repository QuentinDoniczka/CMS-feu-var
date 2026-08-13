# Contrat d'interface — Issue #20 — Import reproductible, artefact de recette hors de l'accès public

**Gelé le** 13 août 2026 par `lead-issue-cms` (chaîne #20) · **Statut** : contraignant.

Cette issue est une **dette technique** du lot 1 (épique « Fondations des données », issues #1 #2 #3).
Elle ne crée aucune fonctionnalité, n'expose aucune donnée nouvelle et ne change **aucune interface**.
Son objet est de rendre vrai, par construction, ce que le contrat `issue-2.md` affirmait déjà par la prose.

**Ce document n'est pas un contrat parallèle** : la frontière thème ↔ extension reste intégralement
définie par [`issue-2.md`](issue-2.md), que cette chaîne **amende**. On trouve ici les arbitrages de la
chaîne #20 et leurs raisons ; les clauses contraignantes vivent dans `issue-2.md`, aux arbitrages **B-11**,
**B-14**, **B-17**, **B-18** et aux interdits **4** et **14**.

---

## Ce qui ne change pas — et qu'il ne faut donc pas supposer changé

Aucune fonction `massifs_*()`. Aucune route REST (il n'y en a toujours aucune). Aucune clé de
`massifs_geometrie()`, `massifs_emprise()`, ni de la ligne de massif. **`zoom_max` reste `11`.** Les
25 `code` gelés, leurs libellés, la correspondance préfectorale (B-16), les valeurs dégradées, les états
spéciaux, les interdits 1-3, 5-13 et 15-19 : inchangés. `emprise.bbox` et `emprise.centre` sont
**identiques à la décimale** après régénération, ce qui a été vérifié et non supposé.

**Le thème n'a rien à changer.** Seules les *valeurs* de `geometrie.version`, `geometrie.sha256` et
`geometrie.octets` bougent — `c2f3854f` → `744fba53`, 278 728 → 278 894 o. Le jeton de cache-busting
existe exactement pour absorber cela, et l'interdit 11 interdit déjà d'écrire un `?v=` en dur.

## Fonctions de lecture exposées par l'extension

**Aucune ajoutée, aucune retirée, aucune signature modifiée.** Voir `issue-2.md`.

## Routes REST

**Aucune.** La géométrie reste un fichier statique servi par le serveur web, sans PHP dans le chemin.

## États spéciaux

| État | Émis par le serveur | Rendu par le thème |
|---|---|---|
| `geometrie_indisponible` | Inchangé — voir `issue-2.md` | Inchangé |
| `information_indisponible` · `hors_saison` · `donnee_perimee` · `couche_effis_indisponible` | Hors périmètre | — |

**Aucun état spécial n'est créé ni modifié par #20.**

## Chaînes fournies par le serveur

**Aucune.** Cette issue ne touche à aucune chaîne d'interface.

## Interdits — ajoutés ou renforcés par #20

1. **Ne jamais éditer à la main un artefact généré**, et cela inclut désormais les **preuves** :
   `build/massifs-13.fidelite.json` et `build/reference.json` sont émis par `npm run importer`
   (interdit 14 étendu). Le défaut qui motive la règle est constaté, pas théorique.
2. **Ne jamais desserrer un seuil de `SEUILS` pour faire passer un import.** Un import qui échoue sur
   son budget ou sur son écart maximal est une information, pas un obstacle.
3. **Ne jamais présenter une mesure gzip du build pour une mesure de transfert**, ni l'inverse. Deux
   méthodes, deux nombres : 73 737 o (`zlib.gzipSync`) et 74 023 o (transféré, mesuré en HTTP).
4. **Ne jamais déclarer un contrôle de recette passé s'il n'a pas été joué.** Sans PHP atteignable, la
   recette **échoue** ; elle ne saute pas silencieusement ses 22 contrôles de métadonnées.
5. **`data/` = servi au navigateur ; `build/` = jamais servi.** Tout artefact qui n'est pas destiné au
   navigateur va dans `build/`. Invariant structurel, opposable aux chaînes suivantes.

## Arbitrages

| # | Désaccord ou point ouvert | Décision retenue | Raison |
|---|---|---|---|
| C-1 | **Reproductibilité : reproduire les octets du passé, ou garantir ceux de l'avenir ?** L'issue citait 16 272 / 278 728 o / 94,55 m au contrat contre 16 282 / 278 894 o / 93,62 m à la re-mesure | **Garantir l'avenir, et réaligner le contrat sur la mesure** — jamais l'inverse | La piste « reproduire le passé » supposait de retrouver la source pleine précision que l'ancien `fidelite.json` déclare avoir mesurée (`dataset/scout/massifs_forestiers_13_wgs84.geojson`, 3 664 738 o). **Recherchée dans tout le dépôt et dans tout l'historique git : elle n'existe nulle part et n'a jamais été commitée.** Les anciens chiffres ne sont donc reproductibles par personne — les inscrire au contrat, c'était opposer une garantie invérifiable. **Deux effets distincts, séparés et non confondus** (détail en B-14) : la géométrie **a** changé (278 728 → 278 894 o, 16 272 → 16 282 sommets), et la source archivée étant rigoureusement identique (`sha256 d0316cbc…`), l'outillage non verrouillé en est la seule variable — c'est cela que le verrou ferme. En revanche **94,55 → 94,31 m n'est pas une dérive** : c'est la **même géométrie au même octet**, mesurée contre une autre référence (source pleine précision disparue vs archive à 5 décimales). Vérifié mécaniquement, pas déduit : re-mesurer la géométrie de `HEAD` avec le code d'aujourd'hui redonne 94,31 m, donc le code de mesure n'est pas la variable. Voir B-17 |
| C-2 | **La panne §11 réelle n'était pas celle que l'issue décrivait** | **Traitée dans la même passe** : `.gitattributes` `-text` sur les artefacts empreintés | Constat vérifié, absent de l'issue comme de la revue qui l'a produite : **aucun `.gitattributes` n'existait dans le dépôt**, `core.autocrlf=true`, et `git ls-files --eol` donnait `attr/` (aucun attribut) sur une géométrie qui contient 26 LF. **Un clone Windows la convertissait en CRLF : +26 octets, `sha256` différent** — donc `massifs_geometrie()` annonçant une empreinte et un poids faux, `verifier.mjs` en échec, et `tests/scenarios/12-geometrie-et-rest.php` en échec sur « octets annoncés = octets servis ». Un développeur qui découvrait le projet sous Windows obtenait un dépôt cassé : c'est très exactement la promesse §11 non tenue, et un verrou npm seul ne l'aurait jamais fermée |
| C-3 | **Un verrou suffit-il, ou faut-il une empreinte de référence ?** | **Les deux** : `package-lock.json` **et** `build/reference.json` comparé par la recette | Le verrou ne couvre ni la version de Node, ni le CRLF, ni la version d'outil affirmée en dur, ni les causes qu'on n'a pas anticipées. `reference.json` les rattrape toutes d'un coup, pour une vingtaine de lignes : c'est le meilleur rapport valeur/coût de l'issue. **Émis par l'import, jamais tenu à la main** — le tenir à la main serait reproduire mot pour mot le défaut que cette issue ferme |
| C-4 | **`genere_le` empêche l'identité binaire** : injecté par `new Date()`, il rend `massifs-13.php` et `fidelite.json` différents à chaque import | **Assumé et l'exigence reformulée**, pas rendu déterministe : *la géométrie publiée est reproductible à l'octet ; les métadonnées portent un horodatage d'import assumé.* Une variable `MASSIFS_GENERE_LE` prouve l'identité du quadruplet à la demande | Dériver `genere_le` de la révision source ferait **énoncer une fausse date de génération** dans le champ qui prétend dire quand le fichier a été généré, exposé par `massifs_referentiel_etat()['genere_le']`. Dans un projet dont la règle cardinale est de ne jamais présenter une date pour une autre (§4.2), acheter une propriété de build au prix d'une date fausse est un mauvais échange. La géométrie, seule dont l'empreinte est contractuelle et sert au cache-busting, est bien reproductible à l'octet — **prouvé, deux passes, quatre empreintes identiques** |
| C-5 | **Artefact de recette public : servi ou protégé ?** | **Protégé par relocation dans `build/`**, pas par une règle nouvelle ; **doublé** d'un durcissement Apache sur les sous-arbres non publics | Le fichier ne porte ni secret ni donnée personnelle et pèse 16 429 o que personne ne demande : ni le §10 ni la contrainte #2 ne sont en jeu. Le vrai argument est ailleurs — **un fichier servi publiquement et déclaré « hors contrat » est une invitation à en dépendre**. La relocation rend l'interdit 4 vrai **par construction** ; elle ne dépend d'aucune configuration serveur nouvelle ; elle suit sur o2switch parce qu'elle est livrée par l'extension ; et elle restaure un invariant lisible. Le durcissement Apache la double parce qu'un `.htaccess` est inerte si `AllowOverride` est désactivé. Voir B-18 pour ce qui est **délibérément épargné**, et pourquoi la règle vise des sous-arbres plutôt qu'une liste noire de noms |
| C-6 | **Tolérance de simplification : la compression étant confirmée, faut-il descendre à `interval=20 m` ?** | **Non. `interval=90 m` inchangé**, et les raisons consignées une fois pour toutes | La prémisse qui bloquait la piste est bien tombée (B-11), mais elle n'était pas la seule raison, et les trois qui restent sont indépendantes de la compression : **(i)** l'interdit 12 plafonne la couche massifs à z11, donc la finesse sous-pixel à z12 serait **strictement invisible** ; **(ii)** 809 966 o bruts re-mesurés, soit 2,7× le budget brut, et ~2,9× de sommets à décompresser, parser et rasteriser sur mobile contre les 2,5 s du §10 — un coût réel pour un gain nul ; **(iii)** la carte n'est pas écrite, il n'y a aucun consommateur à servir. Rouvrir un arbitrage mesuré et gelé sans besoin en face serait du périmètre qui s'étend tout seul. **Relever `zoom_max` à 12 touche le front** et sort de l'empreinte de #20 : c'est à la chaîne carte de l'instruire, **après** avoir tranché §10 « brut ou transféré ? » |
| C-7 | **`source.capture_scale = "1:25 000"`** figurait dans l'artefact maintenu à la main, et n'est dérivable ni du pipeline ni de la source archivée | **Retiré**, et remonté comme **question ouverte** au propriétaire du projet | C'est une affirmation sur l'échelle de saisie du jeu DDTM, attestée nulle part — ni dans le brief, ni dans le dépôt, ni dans la source. B-10 a déjà tranché la classe du problème : **absent vaut mieux que faux**. Retirer une affirmation non sourcée n'est pas inventer ; la conserver dans une **preuve de recette** aurait été exactement le reproche que cette issue adresse à ce fichier. Enjeu réel et assumé : un écart de 90 m se juge relativement à l'échelle de saisie, donc l'argument de fidélité §4.1 est un peu moins étayé qu'il ne le prétendait. **Récupérable** dans l'historique git (commit `259580c`) si la DDTM la documente |
| C-8 | **`source.raw_bytes = 3 664 738`** décrivait la source pleine précision absente du dépôt | **Abandonné.** Le bloc `source` décrit l'archive seule, 3 022 441 o mesurés. Aucune clé `original_raw_bytes` inventée | Un chiffre que personne ne peut recalculer n'est pas une preuve. Le conserver sous un nom nouveau n'aurait fait que déplacer le mensonge dans une clé plus longue |
| C-9 | **Le majeur de Node : échec dur ou avertissement** dans la recette ? | **Avertissement**, toujours imprimé, et rappelé dans le bloc de tout autre échec | Un échec sur Node 26 alors que les octets concordent est un **faux positif**, et un faux positif répété entraîne à régénérer `reference.json` par réflexe — ce qui détruit exactement le dispositif qu'on vient de construire. Node reste consigné comme **contexte de diagnostic**, jamais comme critère. Les faits binaires (empreintes, octets, sommets, version de mapshaper), eux, échouent durement |
| C-10 | **Aucun binaire PHP sur l'hôte**, alors que la recette en dépend pour 22 contrôles | **La recette est rendue jouable sans PHP hôte** (`PHP_BIN` accepte des arguments, `MASSIFS_PHP_RACINE` réécrit le préfixe de chemin pour un PHP conteneurisé) — et **elle échoue toujours** si PHP est injoignable | Une recette qui ne tourne que sur une machine ayant par chance un PHP installé n'est pas la procédure d'installation reproductible que le §11 promet : le développeur qui découvre le projet **est** le cas d'usage. Ce qui n'était pas négociable : PHP injoignable reste un **échec**, jamais 22 contrôles silencieusement sautés |

## Mesures — ce qui a été joué, et ce qui ne l'a pas été

**Joué et vert** : `npm run verifier` en mode PHP conteneurisé (PHP 8.3.33), **aucun contrôle non joué** ;
`php -l` sur 7 fichiers, 0 erreur ; `node --check` sur les deux `.mjs` ; matrice HTTP complète sur la
stack reconstruite ; `bash tests/verifier-http.sh` (lu, **non modifié**), 12/12 gardes ; identité binaire
du quadruplet sur deux passes ; trois chemins d'échec de la recette éprouvés pour de vrai (`reference.json`
absent, version mapshaper falsifiée, CRLF injecté), artefacts restaurés à l'octet après chacun.

**Non joué, et déclaré comme tel** : la **preuve de clone à froid** — elle exige que `.gitattributes` soit
déjà dans un commit. Substitut joué : `git check-attr -a` → `text: unset` et `git ls-files --eol` →
`attr/-text` sur les fichiers empreintés. La preuve complète est à jouer **après** le commit de cette
chaîne ; les commandes exactes figurent dans le rapport de chaîne. **Aucune preuve de clone n'est
revendiquée ici.** `interval=60 m → 379 940 o` n'a pas été re-mesuré sous verrouillage et reste indicatif.

## Questions ouvertes — à ne pas trancher en silence

1. **`capture_scale` du jeu DDTM** (C-7). Fait de domaine, non attesté, retiré. À confirmer par le
   propriétaire du projet ou par la fiche du jeu de données, et alors seulement réintroduit **sourcé**.
2. **§10, « géométries < 300 Ko » : octets bruts ou octets transférés ?** Le brief ne le dit pas.
   B-11 maintient la lecture stricte (bruts), qui est déjà satisfaite et ne peut pas être fausse. La
   réponse change la marge disponible et rouvrirait C-6. **Adressée à la chaîne qui construira la carte** ;
   à trancher avant toute demande de relèvement de `zoom_max`.
