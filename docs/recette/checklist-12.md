# Checklist du §12 — état au 16 août 2026

**Issue #18.** Cette checklist est remplie **pour ce que l'issue #18 a pu établir**, et pas au-delà.
Les lignes qui relèvent d'autres chaînes portent leur état connu, avec la source qui l'établit.

> **Convention, tenue sans exception.**
> `tenue` = **vérifié par une mesure ou un test**, avec sa preuve référencée.
> `partielle` = établi pour une partie du périmètre seulement, la part manquante est nommée.
> `non vérifiée` = **personne ne l'a mesuré**. Ce n'est pas « probablement bon ».
> `en sommeil` = sans objet tant que le site n'est pas publié.
> Aucune ligne n'est cochée sur une conviction.

---

| # | Ligne de DoD | État | Preuve / motif |
|---|---|---|---|
| 1 | Aucune requête du navigateur vers un domaine tiers, preuve jointe | **tenue** | [`preuves-a11y-et-perf.md`](preuves-a11y-et-perf.md) §3 — 5 pages, avec et sans JS, 0 origine tierce. Relevés bruts dans [`releves/`](releves/) |
| 2a | Utilisable JavaScript désactivé | **tenue** pour les 5 pages relevées | §4.3 — texte rendu identique avec et sans JS sur les trois pages éditoriales |
| 2b | Parcours clavier complet | **non vérifiée** dans cette passe | Demande le contrôle humain ; couvert partiellement par le harnais de lot |
| 2c | Vérifications d'accessibilité sans erreur bloquante | **tenue** | §2 — 0 violation `serious`/`critical` sur accueil, les 3 pages éditoriales et la connexion. Les 2 violations de `wp-login.php` sont `moderate` et viennent du cœur WordPress |
| 2d | Contrôle lecteur d'écran documenté | **non vérifiée — non exécuté** | Aucun agent ne peut l'exécuter. Procédure et gabarit vide : [`controle-lecteur-ecran.md`](controle-lecteur-ecran.md) |
| 3 | Mobile réel 360 px — carte, panneaux, liste, écran gestionnaire | **partielle** | Débordement horizontal **nul** sur les 5 pages relevées (§7 et [`captures/`](captures/)). L'écran gestionnaire n'est pas dans cette passe ; il est couvert par le scénario `portail` du harnais de lot. « Mobile réel » reste un émulateur ici |
| 4 | Chaîne des données testée | **hors périmètre de #18** | Couverte par les scénarios 01 à 54 du harnais (`tests/scenarios/`) |
| 5 | Portail — mise à jour < 1 min, journal exact, force brute, 2FA | **hors périmètre de #18** | Couverte par l'Épic 5 et le scénario `portail` |
| 6 | Démo publique | **en sommeil** | Site non publié — [`portee-non-publiee.md`](../decisions/portee-non-publiee.md) |
| 7a | HTTPS actif | **en sommeil** | Aucun hébergement. La stack locale est en HTTP, et `Strict-Transport-Security` y est **correctement absent** (conditionné à `is_ssl()`) |
| 7b | Sauvegarde et restauration testées | **hors périmètre de #18** | Chaîne #16 |
| 7c | Budgets de performance tenus | **tenue** | §4 — HTML+CSS+JS **103 Ko** contre un budget de 250 Ko ; **2** fichiers de police contre un maximum de 2 |
| 8a | Attributions et licences toutes présentes | **tenue** | Les **cinq** figurent sur les mentions légales — DDTM, OpenStreetMap, préfecture, Météo-France, EFFIS. Vérifié au rendu, voir `captures/mentions-mobile-360.png` |
| 8b | Mentions légales | **tenue** | Éditeur, directeur de la publication et contact fournis par le propriétaire du projet ; hébergeur **en sommeil**, dit comme tel |
| 8c | Pages « La démarche » et « Accessibilité » rédigées | **tenue, sous condition d'import** | Rédigées et importées ; elles répondent 200 et sont mesurées. **La copie vit dans [`contenu/`](contenu/) et n'est en base qu'après [`importer-pages.sh`](importer-pages.sh)** — une réinitialisation de la stack les perd tant que le provisionnement durable n'existe pas |
| 9 | Livrables du §11 complets | **partielle** | [`livrables-11.md`](livrables-11.md) — production et démo **en sommeil** ; tout le reste est livré ou référencé |

---

## Ce que cette checklist ne dit pas

- Elle n'établit **rien** sur les lignes 4, 5 et 7b : ce sont d'autres chaînes, et #18 n'a pas rejoué
  leurs tests. Leur état ici est **rapporté**, pas vérifié.
- Les mesures de la ligne 3 sont prises dans un **navigateur émulé** à 360 px, pas sur un téléphone.
  L'absence de défilement horizontal est un fait mesuré ; « utilisable au doigt » ne l'est pas.
- Les relevés valent **pour l'état de l'arbre au 16 août 2026**, travail non commité des issues #16
  et #18 inclus. Ils sont à rejouer après le lot.
