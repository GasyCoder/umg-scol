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

`activity_log` conserve sujet, acteur, événement, journal, changements d'attributs autorisés et métadonnées minimales. La fondation identité n'ajoutait aucun rattachement métier à `users`. La phase Référentiels ajoute désormais `establishment_id` nullable ; `student_id` reste absent. Les tables étudiantes et de bourse restent proposées et non implémentées.

## Référentiels institutionnels implémentés

- `establishments` : code obligatoire unique (30 caractères), nom, type et contacts optionnels, statut actif, timestamps. Codes normalisés en majuscules et sans espaces périphériques. Les contacts restent visibles aux administrateurs autorisés mais leurs valeurs ne sont pas dupliquées dans l'audit.
- `academic_years` : nom unique (30 caractères), dates optionnelles, `is_current`, `is_active`, timestamps. `current_marker` est une colonne virtuelle technique valant 1 pour l'année courante et NULL sinon ; son index unique impose au plus une année courante, y compris hors interface. Cette colonne est masquée dans la sérialisation.
- `levels` : code obligatoire unique (30 caractères), nom, ordre entier positif ou nul, statut actif et timestamps. Affichage stable par `sort_order`, puis code et id ; les niveaux ne sont pas codés en dur.
- `programs` : FK restrictive `establishment_id`, code optionnel (30 caractères), nom, statut actif, timestamps. Unicité du code par établissement ; plusieurs codes NULL sont admis. Aucune unicité globale du nom, aucune relation imposée à un niveau.
- `users.establishment_id` : FK nullable et restrictive. Relations `User belongsTo Establishment`, `Establishment hasMany Users`, `Establishment hasMany Programs`, `Program belongsTo Establishment`.

Les statuts et les couples établissement/statut possèdent les index nécessaires aux listes. La suppression via Eloquent des quatre référentiels est refusée ; les routes et interfaces standard proposent uniquement activation/désactivation. Les FK empêchent la suppression SQL d'un établissement encore lié à un parcours ou utilisateur.

`SaveAcademicYear` verrouille les années dans un ordre stable et effectue la transition dans une transaction avec reprise limitée en cas de deadlock. Les anciennes années perdent seulement `is_current` ; elles conservent leur statut actif et leur historique. Une année inactive ne peut pas être définie comme courante. Désactiver l'année courante retire aussi `is_current`. Les modifications individuelles restent auditées et l'audit est annulé avec la transaction en cas d'échec.

`AssignUserEstablishment` rattache un utilisateur dans une transaction, conserve les autres rôles et refuse un rattachement nul/inactif pour le rôle `establishment`. La FK reste nullable pour les autres rôles. Le middleware des routes métier internes refuse un compte établissement sans rattachement actif ; les pages de compte et la déconnexion restent accessibles. Aucun CRUD utilisateur n'est ajouté ; le futur gestionnaire de comptes devra employer cette validation et l'autorisation `users.manage`. Le profil personnel ne permet pas de modifier ce rattachement.

Les Policies utilisent les quatre permissions de gestion existantes. Si un compte établissement reçoit ultérieurement `programs.manage`, listes, formulaires et mutations restent limités à son établissement. La matrice initiale n'est pas modifiée.

Les listes utilisent une pagination serveur de 15 lignes ; les parcours chargent leur établissement en eager loading. Les opérations de référentiel sont auditées dans `references` avec champs structurels autorisés ; une modification limitée aux contacts conserve un événement sans leurs valeurs.

MySQL reste la référence : unicités, FK restrictives, index généré et rollback des décisions/audits ont été vérifiés sur la base locale avec des données temporaires annulées. Les tests Pest courants utilisent SQLite en mémoire (FK activées). Les collations SQLite et MySQL ne sont pas identiques pour les comparaisons Unicode ; les codes sont normalisés en ASCII, et l'unicité des noms d'année dépend de la collation MySQL configurée.
