---
name: refacto
description: Liste les problemes de refactoring du plus critique au moins critique
---

Analyse le code et liste TOUS les problemes du plus critique au moins critique.

## Criteres a analyser

### Violations de couches (Clean Architecture)
- Domain qui reference Application, Infrastructure ou API
- Business logic dans les Controllers (doit etre dans Application/Domain)
- Validation dans API (doit etre FluentValidation dans Application)
- Acces direct a DbContext en dehors d'Infrastructure
- Entities exposees dans les reponses API (doit utiliser des DTOs)
- Infrastructure referencee depuis Application (sauf via interfaces)

### Async/Await et Threading
- `.Result`, `.Wait()`, `.GetAwaiter().GetResult()` — toujours utiliser `await`
- `new Thread()` ou `Thread.Start()` — toujours utiliser `Task` avec `await`
- `SaveChanges()` au lieu de `SaveChangesAsync()`
- `File.ReadAllText` au lieu de `File.ReadAllTextAsync`
- Methode async qui n'await rien
- `async void` (sauf event handlers) — doit etre `async Task`

### Injection de dependances
- Service Locator pattern (`IServiceProvider.GetService<T>()` dans le business code)
- `new` sur des services au lieu d'injection par constructeur
- Constructeur avec 5+ dependances — signe de SRP violation, splitter la classe
- Mauvais lifetime (Singleton qui depend d'un Scoped, etc.)

### Reinvention de la roue
Identifie le code qui refait manuellement ce que .NET/ASP.NET fait deja nativement :
- Middleware custom qui reimplemente `UseExceptionHandler`
- Conteneur DI custom — .NET a le DI built-in
- Logging custom — `ILogger<T>` existe deja
- Configuration custom — `IConfiguration` / `IOptions<T>` existe deja
- Serialisation custom — `System.Text.Json` existe deja
- Validation custom — FluentValidation est le standard du projet
- Mapping custom — AutoMapper ou mappings manuels standardises

Priorite :
- HAUTE : Code complexe (10+ lignes) qui remplace une feature native .NET
- MOYENNE : Code moyen (5-10 lignes) evitable avec un package/systeme existant
- BASSE : Micro-optimisation inutile ou abstraction prematuree

Question cle : "Est-ce que .NET/ASP.NET ne fait pas deja ca ?"

### Extraction de classes
Identifie les portions de code qui peuvent etre extraites dans une classe separee :
- Methodes qui forment un groupe logique coherent
- Code duplique qui merite sa propre classe
- Responsabilites distinctes melangees dans une meme classe (violation Single Responsibility)

Priorite extraction :
- HAUTE : 80+ lignes extractibles ou responsabilite clairement separee
- MOYENNE : 40-80 lignes ou groupe de 3+ methodes liees
- BASSE : < 40 lignes mais ameliorerait la lisibilite

Rester KISS : on extrait seulement si ca simplifie reellement le code, pas pour le plaisir d'abstraire.

### Principes SOLID et architecture
- **Single Responsibility** : Une classe fait trop de choses differentes
- **Open/Closed** : Code qui necessite modification pour extension (vs strategy/DI)
- **Liskov** : Heritage mal utilise — preferer composition
- **Interface Segregation** : Interfaces trop larges — splitter
- **Dependency Inversion** : Dependances hardcodees vs injection/interfaces
- Couplage fort entre couches qui devraient etre independantes

### Code mort et dette technique
- Variables/methodes non utilisees
- Code commente laisse en place
- TODO/FIXME/HACK anciens non resolus
- Imports/using inutilises

## Format de sortie

IMPORTANT : Format texte simple uniquement. PAS de tableau markdown, PAS de colonnes, PAS de syntaxe `|`. Juste des listes numerotees.

### CRITIQUE
1. `Fichier.cs:ligne` - Description du probleme
2. ...

### HAUTE
1. `Fichier.cs:ligne` - Description du probleme
2. ...

### MOYENNE
1. `Fichier.cs:ligne` - Description du probleme
2. ...

### BASSE
1. `Fichier.cs:ligne` - Description du probleme
2. ...

Regles :
- Si une section est vide, ne pas l'afficher
- Ne jamais utiliser de tableau markdown
- Garder le format simple : numero, fichier, description
- Toujours proposer une solution concrete pour chaque probleme

$ARGUMENTS
