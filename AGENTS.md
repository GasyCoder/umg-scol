# AGENTS.md — UMG Bourse

## Mission

Application web interne de l'Université de Mahajanga destinée à centraliser les listes des étudiants déjà régulièrement inscrits dans les établissements et à aider la Scolarité Centrale à vérifier et traiter les demandes papier de bourse nationale.

**Le système ne gère pas l'inscription universitaire.**

Flux de référence : inscription hors système → établissement ajoute/importe l'étudiant → Scolarité Centrale consulte → étudiant dépose le dossier papier → secrétaire vérifie → valide / demande correction / rejette.

## Stack obligatoire

- Laravel 13 / PHP 8.4+
- MySQL
- Inertia.js
- React + TypeScript
- Tailwind CSS v4
- shadcn/ui
- Pest

Packages privilégiés : `spatie/laravel-permission`, `spatie/laravel-activitylog`, `maatwebsite/excel`.

## Architecture

- Un seul backend Laravel ; portail interne `scol.umg.test:8000` avec Inertia et portail étudiant `bourse.umg.test:8000` avec l'API `/api/student/*`. Aucun deuxième backend.
- Contrôleurs minces, Form Requests, Policies/Gates, Actions/Services pour la logique métier.
- Enums PHP pour les statuts.
- Transactions DB pour imports et décisions sensibles.
- Autorisation serveur obligatoire ; le frontend ne remplace jamais les Policies.

## Règles métier

- `students` = identité permanente.
- `student_records` = déclaration annuelle d'un étudiant déjà inscrit (année, établissement, niveau, parcours).
- Un établissement ne voit/modifie que ses propres étudiants.
- L'étudiant peut vérifier ses informations et signaler une correction, mais ne modifie pas directement ses données officielles.
- La Scolarité Centrale peut consulter tous les établissements et traiter les dossiers papier.
- Statuts bourse minimum : `pending`, `in_review`, `validated`, `needs_correction`, `rejected`.
- Le rôle `developer` peut être absent des listes opérationnelles mais jamais des audits.

## Imports

Toujours : upload → parsing → validation → doublons/anomalies → prévisualisation → confirmation → import transactionnel → résumé.
Ne jamais importer silencieusement une ligne invalide.

## Sécurité

- Moindre privilège.
- Pas de secrets dans Git.
- Tracer créations/modifications/imports/exports/décisions sensibles.
- Protéger CIN, téléphone, email, adresse et autres données personnelles.
- Éviter les suppressions physiques des données administratives importantes.

## UI/UX

Interface institutionnelle, sobre, rapide et responsive. Préférer DataTable, filtres, recherche, badges de statut, dialogues de confirmation. Éviter les interfaces "AI-looking" et les effets décoratifs inutiles.

## Avant de terminer une tâche

1. Lire le CDC et les docs concernés.
2. Vérifier permissions/validation/audit.
3. Ajouter ou mettre à jour les tests Pest.
4. Lancer les tests ciblés et les vérifications TypeScript si nécessaire.
5. Résumer les fichiers modifiés et commandes exécutées.
