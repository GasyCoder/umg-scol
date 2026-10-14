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
