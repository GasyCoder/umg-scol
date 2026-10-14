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
- Portail étudiant : `student_links.review` (revue des demandes de rattachement manuel).

## Matrice initiale

| Rôle                         | Permissions attribuées                                                                                                                                     |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `superadmin`                 | Les 34 permissions du catalogue, explicitement attribuées                                                                                                  |
| `developer`                  | Les mêmes 34 permissions pour le support privilégié, sans bypass d'autorisation ni d'audit                                                                 |
| `central_scholarship_office` | `students.view`, `students.viewAny`, `students.export`, `student_records.view`, `corrections.view`, `student_links.review` et les sept permissions bourses |
| `establishment`              | Les six permissions étudiants, les trois déclarations annuelles, les quatre imports, `corrections.view`, `corrections.review`, `student_links.review`      |
| `student`                    | `students.view`, `student_records.view`, `corrections.create`, `corrections.view`                                                                          |

`students.viewAny` autorise une liste dans le périmètre du rôle : établissement propre pour `establishment`, global pour la Scolarité Centrale. Les permissions de consultation individuelle ne donnent jamais accès aux données d'autrui par elles-mêmes. Les Policies et filtres effectifs seront implémentés avec les modèles métier ; aucune isolation fictive n'est créée dans cette fondation.

`RolesAndPermissionsSeeder` est transactionnel et idempotent. Il remet les attributions des cinq rôles en conformité avec cette matrice et invalide le cache Spatie. Aucun `Gate::before` ne donne implicitement des permissions futures. Une nouvelle permission doit être intégrée explicitement à la matrice.

Les attributions et retraits de rôles/permissions via les méthodes Spatie sont audités avec sujet, acteur authentifié et identifiants concernés. Les écritures directes dans les tables pivots contournent ces événements : les futures opérations d'administration devront passer par les méthodes Spatie dans une transaction et être testées.

## Demandes de correction

Matrice existante, sans nouvelle permission :

- `student` (`corrections.create`, `corrections.view`) : crée, consulte et annule ses propres demandes via `/api/student/corrections`, uniquement pour la fiche liée à son compte.
- `central_scholarship_office` (`corrections.view`) : consultation globale de `/corrections`, **lecture seule**.
- `establishment` (`corrections.view`, `corrections.review`) : consulte et décide les demandes des étudiants ayant une déclaration `active` dans son établissement sur l'année courante active (même périmètre que les rattachements, scope partagé `Student::currentlyDeclaredBy`).
- `superadmin`, `developer` : consultation et décision globales.

Décider exige en plus le droit de modifier l'identité (`StudentPolicy::update`) : comme pour la saisie manuelle, un établissement ne peut pas approuver une correction sur une identité également déclarée par un autre établissement ; la demande reste visible en lecture et relève du superadmin. La Policy est revérifiée sous verrou dans l'Action.

## Bourses

Matrice existante, sans nouvelle permission :

- `campaigns.manage` (superadmin, developer) : créer, modifier et changer le statut des campagnes. Le CDC confie les campagnes au superadmin ; la Scolarité Centrale ne reçoit pas cette permission.
- Consultation des campagnes : `campaigns.manage` ou `scholarships.view`, en lecture seule pour la Scolarité Centrale.
- `scholarships.view` : liste et détail des dossiers. Chaque action exige en plus sa permission : `scholarships.receive` (réception), `scholarships.review` (début de vérification, checklist), `scholarships.validate`, `scholarships.request_correction`, `scholarships.reject`, `scholarships.export`.
- `central_scholarship_office`, `superadmin` et `developer` ont ces permissions, avec un **accès global** : le traitement des dossiers papier est central. `establishment` et `student` n'ont aucune permission bourse et reçoivent 403 sur toutes ces routes. La Policy recontrôle la permission de la transition demandée, quelle que soit l'URL utilisée.

## Isolation

Pour un rôle établissement, chaque Policy doit aussi comparer le `establishment_id`. Ne jamais faire ce contrôle uniquement dans React.

## Portails

- `scol.umg.test:8000` : `establishment`, `central_scholarship_office`, `superadmin`, `developer`.
- `bourse.umg.test:8000` : `student` uniquement, via l'API Laravel `/api/student/*`.

La séparation des domaines ne remplace pas les contrôles serveur. L'API étudiante doit limiter chaque requête à la fiche liée au compte authentifié. Une adresse email vérifiée ou un compte Google ne donne pas, à lui seul, accès à une fiche étudiante.

Le rôle étudiant ne reçoit aucune permission de modification directe des données officielles. Il peut soumettre une demande de correction, traitée par l'établissement, et confirmer ses propres informations affichées (sans nouvelle permission). L'état de confirmation n'est visible que par lui via l'API et, sur `scol`, par le personnel qui peut consulter la fiche (`StudentPolicy::view`). Le compte `student` n'utilise aucune permission Spatie sur `bourse` : l'accès est déterminé par le garde `student`, le rôle `student` seul, l'email vérifié, le marqueur de session du portail et `users.student_id`. Un compte portant un autre rôle (même en plus de `student`) est refusé sur l'API étudiante ; un compte `student` est refusé sur toutes les routes de `scol`, y compris Fortify et passkeys.

Revue des rattachements (`/student-links`) : permission `student_links.review` + compte interne avec contexte valide. `establishment` ne voit et ne traite que les demandes dont l'étudiant a une déclaration `active` dans son établissement sur l'année courante active ; `central_scholarship_office`, `superadmin` et `developer` ont une revue globale. La Policy recontrôle ce périmètre sur l'ID de la demande et l'Action le revérifie sous verrou.
