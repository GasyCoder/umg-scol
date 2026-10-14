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

## Portails prévus

- `scol.umg.test:8000` : `establishment`, `central_scholarship_office`, `superadmin`, `developer`.
- `bourse.umg.test:8000` : `student` uniquement, via l'API Laravel `/api/student/*`.

La séparation des domaines ne remplace pas les contrôles serveur. L'API étudiante doit limiter chaque requête à la fiche liée au compte authentifié. Une adresse email vérifiée ou un compte Google ne donne pas, à lui seul, accès à une fiche étudiante.

Le rôle étudiant ne reçoit aucune permission de modification directe des données officielles. Il peut soumettre une demande de correction, traitée par l'établissement. Le mécanisme de rattachement et les contrôles de portail restent à implémenter lors d'une phase ultérieure.
