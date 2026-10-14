# Rôles et permissions

## Rôles
### establishment
Accès uniquement à son établissement : étudiants, déclarations annuelles, imports, corrections et exports autorisés.

### central_scholarship_office
Consultation globale, réception/vérification/validation/correction/rejet des dossiers, statistiques et exports.

### student
Consultation de ses propres données, confirmation et demande de correction.

### superadmin
Administration fonctionnelle globale.

### developer
Support technique privilégié ; jamais invisible dans les audits.

## Permissions proposées
`students.view`, `students.create`, `students.update`, `students.deactivate`, `students.export`, `student_records.view`, `student_records.create`, `student_records.update`, `imports.view`, `imports.create`, `imports.commit`, `corrections.create`, `corrections.view`, `corrections.review`, `scholarships.view`, `scholarships.receive`, `scholarships.review`, `scholarships.validate`, `scholarships.request_correction`, `scholarships.reject`, `scholarships.export`, `establishments.manage`, `academic_years.manage`, `levels.manage`, `programs.manage`, `campaigns.manage`, `users.manage`, `roles.manage`, `permissions.manage`, `audits.view`.

## Isolation
Pour un rôle établissement, chaque Policy doit aussi comparer le `establishment_id`. Ne jamais faire ce contrôle uniquement dans React.
