# Architecture technique

## Style
Monolithe Laravel + Inertia + React.

```text
Browser -> Laravel Routes -> Controllers -> Requests/Policies -> Actions/Services -> Eloquent -> MySQL
                                         \-> Inertia -> React/TypeScript/shadcn
```

## Backend
`app/Actions`, `app/Enums`, `app/Http/Controllers`, `app/Http/Requests`, `app/Imports`, `app/Jobs`, `app/Models`, `app/Policies`, `app/Services`.

## Frontend
`resources/js/components`, `components/ui`, `layouts`, `pages`, `hooks`, `lib`, `types`.

## Règles
- routes nommées cohérentes ;
- sécurité backend obligatoire ;
- pagination serveur ;
- eager loading maîtrisé ;
- indexes DB ;
- jobs pour imports/exports longs ;
- aucune API publique séparée requise en V1.
