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

## Catalogue implémenté

Le catalogue et la matrice initiale sont définis dans `config/access.php`, pour le guard `web`.

- Étudiants : `students.view`, `students.viewAny`, `students.create`, `students.update`, `students.deactivate`, `students.export`.
- Déclarations annuelles : `student_records.view`, `student_records.create`, `student_records.update`.
- Imports : `imports.view`, `imports.create`, `imports.commit`, `imports.export_errors`.
- Corrections : `corrections.create`, `corrections.view`, `corrections.review`.
- Bourses : `scholarships.view`, `scholarships.receive`, `scholarships.review`, `scholarships.validate`, `scholarships.request_correction`, `scholarships.reject`, `scholarships.export`.
- Administration : `establishments.manage`, `academic_years.manage`, `levels.manage`, `programs.manage`, `campaigns.manage`, `users.manage`, `roles.manage`, `permissions.manage`.
- Audit : `audits.view`, `audits.export`.

## Matrice initiale

| Rôle                         | Permissions attribuées                                                                                                             |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `superadmin`                 | Les 33 permissions du catalogue, explicitement attribuées                                                                          |
| `developer`                  | Les mêmes 33 permissions pour le support privilégié, sans bypass d'autorisation ni d'audit                                         |
| `central_scholarship_office` | `students.view`, `students.viewAny`, `students.export`, `student_records.view`, `corrections.view` et les sept permissions bourses |
| `establishment`              | Les six permissions étudiants, les trois déclarations annuelles, les quatre imports, `corrections.view`, `corrections.review`      |
| `student`                    | `students.view`, `student_records.view`, `corrections.create`, `corrections.view`                                                  |

`students.viewAny` autorise une liste dans le périmètre du rôle : établissement propre pour `establishment`, global pour la Scolarité Centrale. Les permissions de consultation individuelle ne donnent jamais accès aux données d'autrui par elles-mêmes. Les Policies et filtres effectifs seront implémentés avec les modèles métier ; aucune isolation fictive n'est créée dans cette fondation.

`RolesAndPermissionsSeeder` est transactionnel et idempotent. Il remet les attributions des cinq rôles en conformité avec cette matrice et invalide le cache Spatie. Aucun `Gate::before` ne donne implicitement des permissions futures. Une nouvelle permission doit être intégrée explicitement à la matrice.

Les attributions et retraits de rôles/permissions via les méthodes Spatie sont audités avec sujet, acteur authentifié et identifiants concernés. Les écritures directes dans les tables pivots contournent ces événements : les futures opérations d'administration devront passer par les méthodes Spatie dans une transaction et être testées.

## Isolation

Pour un rôle établissement, chaque Policy doit aussi comparer le `establishment_id`. Ne jamais faire ce contrôle uniquement dans React.

## Portails prévus

- `scol.umg.test:8000` : `establishment`, `central_scholarship_office`, `superadmin`, `developer`.
- `bourse.umg.test:8000` : `student` uniquement, via l'API Laravel `/api/student/*`.

La séparation des domaines ne remplace pas les contrôles serveur. L'API étudiante doit limiter chaque requête à la fiche liée au compte authentifié. Une adresse email vérifiée ou un compte Google ne donne pas, à lui seul, accès à une fiche étudiante.

Le rôle étudiant ne reçoit aucune permission de modification directe des données officielles. Il peut soumettre une demande de correction, traitée par l'établissement. Le mécanisme de rattachement et les contrôles de portail restent à implémenter lors d'une phase ultérieure.
