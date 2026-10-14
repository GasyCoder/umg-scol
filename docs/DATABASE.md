# Modèle de données proposé

## Tables

- `users`
- `establishments`
- `academic_years`
- `levels`
- `programs`
- `students`
- `student_records`
- `scholarship_campaigns`
- `scholarship_applications`
- `student_correction_requests`
- `imports`
- `import_rows`
- `activity_log`

## students

Identité permanente : `student_number`, `last_name`, `first_names`, `birth_date`, `birth_place`, `cin`, `cin_issued_at`, `cin_issued_place`, `phone`, `email`, `address`, `bac_registration_number`, `bac_series`, `bac_year`, `bac_mention`, `is_active`.

## student_records

Déclaration annuelle d'un étudiant déjà inscrit : `student_id`, `academic_year_id`, `establishment_id`, `level_id`, `program_id`, `status`, `declared_by`, `declared_at`.

Indexes conseillés : `(academic_year_id, establishment_id)`, `(student_id, academic_year_id)`, `(establishment_id, level_id)`.

## scholarship_applications

`application_number`, `scholarship_campaign_id`, `student_id`, `student_record_id`, `status`, dates/acteurs de réception, revue et décision, `reason`, `observation`.

## imports/import_rows

Conserver métadonnées d'import, compteurs, données brutes/normalisées, erreurs, warnings et éventuel étudiant correspondant.

## Principes

FK, transactions, historique, indexes, pas de duplication inutile, contraintes uniques seulement après confirmation de la qualité réelle des données historiques.

## Tables de fondation implémentées

Les migrations techniques sont complétées par `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (Spatie Permission) et `activity_log` (Spatie Activitylog). Les identifiants de modèles utilisent des relations polymorphes ; les pivots des rôles et permissions possèdent leurs contraintes et index fournis par le package.

`activity_log` conserve sujet, acteur, événement, journal, changements d'attributs autorisés et métadonnées minimales. Aucun rattachement `establishment_id` ou `student_id` n'est ajouté à `users` dans cette étape. Les autres tables métier restent proposées et non implémentées.
