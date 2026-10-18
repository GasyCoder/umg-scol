# Conventions de développement

## PHP/Laravel

PSR-12, Pint, types de retour, Enums, Form Requests, Policies, relations Eloquent, transactions, éviter N+1.

## Naming

Code/DB en anglais, UI en français. Exemples : `Student`, `StudentRecord`, `ScholarshipApplication`.

## React/TypeScript

TypeScript obligatoire, props typées, éviter `any`, composants réutilisables, shadcn/ui avant primitives custom, états loading/empty/error.

## Tests

Couvrir au minimum : isolation établissement, accès Scolarité Centrale, étudiant limité à ses données, import invalide non persisté, rejet avec motif, audit d'une action sensible.
