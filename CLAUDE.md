# CLAUDE.md — Instructions Claude Code

Tu travailles sur la plateforme UMG de centralisation des étudiants inscrits et de traitement des demandes de bourse nationale.

## Source de vérité

Lire d'abord :

- `AGENTS.md`
- `docs/CDC.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/ROLES_PERMISSIONS.md`
- `docs/WORKFLOWS.md`

## Règle principale

**Ne jamais transformer l'application en système d'inscription universitaire.** L'inscription se fait dans les établissements, hors plateforme.

## Méthode de travail

1. Examiner le code existant avant de créer.
2. Identifier impacts DB/backend/permissions/UI/tests.
3. Faire un plan court pour une tâche multi-couches.
4. Réutiliser les patterns existants.
5. Implémenter sans sur-architecture.
6. Ajouter validation et autorisation serveur.
7. Ajouter/mettre à jour les tests Pest.
8. Exécuter les tests ciblés.
9. Vérifier TypeScript si le frontend change.
10. Résumer changements, fichiers, commandes et reste éventuel.

## Backend

Contrôleurs minces, Form Requests, Policies, Actions/Services, Enums, Jobs pour traitements longs, transactions DB pour opérations critiques.

## Frontend

Inertia + React + TypeScript + shadcn/ui. Pages lisibles, composants réutilisables, pas de `any` sans justification, gestion loading/empty/error.

## Interdictions

- pas de module d'inscription/réinscription ;
- pas de modification directe de l'identité par l'étudiant ;
- pas d'accès inter-établissements non autorisé ;
- pas de contournement des Policies ;
- pas d'audit désactivé ;
- pas de secrets dans le dépôt ;
- pas d'API séparée sans besoin validé.
