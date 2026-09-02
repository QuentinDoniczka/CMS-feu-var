# Décision — La disjonction des empreintes ne protège pas l'exécution

**Domaines** `infra`
**Date de la décision** : 2 septembre 2026 · **Auteur** : `/lead-CMS`, lot #19/#69/#70
**Statut** : décision arrêtée

> Ce document corrige une affirmation des conventions du projet. Il ne change aucune règle de qualité :
> il nomme une protection que nous croyions avoir et que nous n'avons pas.

---

## 1. Ce que les conventions affirmaient

`CLAUDE.md` énonce, au titre du mono-branche :

> La **disjonction des empreintes fichiers** est la seule protection contre l'écrasement mutuel.

C'est vrai pour les **fichiers**. Ce lot a établi que c'est faux pour l'**exécution**.

## 2. Le fait, mesuré

`docker-compose.yml` monte l'extension et le thème en **bind-mount `rw`** depuis l'arbre de travail
partagé :

```
.../CMS-feu-var/wp-content/plugins/massifs-core → /var/www/html/wp-content/plugins/massifs-core (rw=true)
.../CMS-feu-var/wp-content/themes/massifs       → /var/www/html/wp-content/themes/massifs       (rw=true)
```

Conséquence : **le code non committé d'une chaîne s'exécute dans les tests de toutes les autres**, à
chaque requête HTTP et à chaque `wp eval-file`. Un fichier non suivi qui apparaît entre deux requêtes
change le comportement du site sans qu'aucune ligne de la chaîne qui teste n'ait bougé.

Trois mesures indépendantes, sur des fichiers de test **strictement inchangés** entre les exécutions :

| Chaîne | Observation |
|---|---|
| #70 | quatre exécutions du même fichier : 7 rouges, puis 3, puis 0, puis 4 — sur des cas différents |
| #70 | `ETAT` rapporte `etat=disponible` à l'`arrange`, la page rend `indisponible` à l'`assert` |
| #19 | une sonde relève le compte de `wp_massifs_statuts` passer 25 → 16 → 25 et `MAX(id)` bouger, alors que rien de la chaîne ne tournait |

Une seconde cause, distincte et plus banale, a joué en parallèle : l'**état de la base** est partagé lui
aussi, et un scénario qui débranche un crochet sans le rebrancher laisse la stack faussée pour les
suivants (constaté sur `ProjecteurPrefecture::projeter`, corrigé depuis).

## 3. Ce qui ne referme pas le trou

- **Une base par chaîne** ne suffit pas : ce n'est pas seulement la donnée qui change sous les pieds
  d'une chaîne, c'est **le code qui la produit**.
- **Une empreinte fichiers disjointe** ne suffit pas : elle est respectée dans les trois cas ci-dessus.
- **Une purge par cas** ne suffit pas : elle protège de l'état hérité, pas d'une écriture concurrente
  survenant entre l'`arrange` et l'`assert`.

## 4. La décision

**L'orchestrateur séquence l'exécution ; les chaînes ne s'en protègent pas toutes seules.**

1. **Une seule chaîne à la fois exerce la stack.** Le parallélisme reste acquis pour l'analyse, la
   planification et l'écriture ; il s'arrête à la porte du conteneur. Une chaîne qui a besoin de la stack
   le signale et attend son tour.
2. **Une chaîne qui teste par HTTP attend que les chaînes qui écrivent du code de production aient
   commité.** C'est le signal de déblocage : « ma dernière écriture dans `wp-content/**` est faite et
   commitée », vérifié par l'orchestrateur avec `git status --short wp-content/`, jamais sur parole.
3. **Un rouge isolé se rejoue avant d'être cru**, et **un vert non reproductible n'est pas un vert**.
   Deux exécutions concordantes en fenêtre calme, sinon le résultat ne conclut rien.
4. **Chaque cas purge et pose sa propre précondition**, et ne compte jamais en absolu sur une table
   partagée — des deltas, ou un comptage scopé au jour qu'il possède.

## 5. Ce que cette décision ne dispense pas de faire

Elle ne justifie **jamais** d'assouplir, de désarmer ou de retirer une assertion pour obtenir du vert.
Une chaîne qui neutralise un contrôle au motif que « la stack est instable » fabrique précisément le
faux vert que les issues #32, #69 et #71 existent pour supprimer. Le remède est le séquencement, pas
l'affaiblissement de la recette.

## 6. Ce qui reste ouvert

Le séquencement est une discipline d'orchestration, pas une garantie mécanique : rien n'empêche
techniquement deux conteneurs de tourner ensemble. Une isolation réelle demanderait un projet Compose
par chaîne, avec sa base et sa copie du code — ce qui coûte un arbre de travail par chaîne, donc
contredit le mono-branche. **Le coût n'a pas été jugé justifié** pour un projet vitrine à trois chaînes ;
la discipline suffit tant que l'orchestrateur la tient.

L'orchestrateur qui lance lui-même deux agents sur la stack en parallèle enfreint cette décision — c'est
arrivé une fois pendant ce lot, sur un test et une review lancés ensemble, et le rouge parasite qui en a
résulté a coûté une vérification supplémentaire.
