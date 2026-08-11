---
name: update-structure
description: Scanne et met a jour l'arborescence du projet dans CLAUDE.md
---

Scanne la structure du projet et met a jour la section structure dans `CLAUDE.md` (entre les marqueurs `<!-- STRUCTURE:START -->` et `<!-- STRUCTURE:END -->`).

## Regles de scan

### Dossiers a EXCLURE completement
- `.git/`, `.claude/`, `.vs/`, `.idea/`
- `node_modules/`, `packages/`
- `bin/`, `obj/`, `TestResults/`
- `__pycache__/`

### Fichiers a EXCLURE
- `*.csproj`, `*.sln`, `*.user`
- `*.lock.json`
- `.DS_Store`, `Thumbs.db`
- `*.md` (documentation a la racine)
- `Properties/`, `launchSettings.json`
- `Dockerfile`, `WeatherForecast.cs`

### Ce qui est inclus
- Fichiers source : `*.cs`
- Config essentiels : `appsettings.json`, `appsettings.Development.json`
- Docker compose : `compose.yaml`, `docker-compose*.yml`

### Simplifications
- Migrations EF Core : afficher `Migrations/ (X files)` sans lister les fichiers
- Pas d'annotations `[API Layer]` etc. — juste l'arbre brut
- Pas de date de generation

## Format de sortie

Arborescence minimaliste, uniquement les 4 couches et leurs fichiers source :

```
back_Csharp_advance/
├── API/
│   ├── Controllers/
│   │   └── AuthController.cs
│   ├── Middleware/
│   │   └── ExceptionMiddleware.cs
│   └── Program.cs
├── Application/
│   └── Commands/
│       └── Register/
│           ├── RegisterCommand.cs
│           └── RegisterCommandHandler.cs
├── Domain/
│   (empty)
├── Infrastructure/
│   └── Data/
│       └── AppDbContext.cs
└── compose.yaml
```

## Instructions

1. Scanner le projet depuis la racine
2. Construire l'arborescence selon les regles ci-dessus
3. Lire `CLAUDE.md`
4. Remplacer le contenu entre `<!-- STRUCTURE:START -->` et `<!-- STRUCTURE:END -->` (marqueurs inclus) par le nouvel arbre, en conservant les marqueurs
5. Confirmer avec le nombre de fichiers scannes

$ARGUMENTS
