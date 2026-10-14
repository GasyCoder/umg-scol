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

`activity_log` conserve sujet, acteur, événement, journal, changements d'attributs autorisés et métadonnées minimales. La fondation identité n'ajoutait aucun rattachement métier à `users`. La phase Référentiels ajoute désormais `establishment_id` nullable ; `student_id` reste absent. Les tables étudiantes sont désormais implémentées dans la phase suivante ; les tables de bourse restent proposées et non implémentées.

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

## Étudiants et déclarations annuelles implémentés

`Student` est une identité permanente. Aucune inscription universitaire, admission ou réinscription n'est créée par le module. L'ajout manuel atteste une situation déjà déclarée par l'établissement hors plateforme.

### Identité et index

`students` contient `student_number` nullable (100), `last_name` obligatoire (150), `first_names` nullable (150), naissance (date et lieu), CIN (100, date et lieu de délivrance), téléphone (30), email (255), adresse (text), numéro BAC (100), série (50), année (unsigned small integer), mention (100), `is_active` et timestamps.

Les identifiants `student_number`, `cin`, `bac_registration_number`, `phone` et `email` sont indexés sans contrainte UNIQUE. `last_name`, `is_active` et le triplet nom/prénoms/naissance sont également indexés. Les tailles sont compatibles avec les limites d'index MySQL utf8mb4. Aucun `user_id` n'est ajouté : le rattachement d'un compte étudiant reste une phase ultérieure.

La normalisation s'applique dans les Form Requests et le modèle : trim des textes, email en minuscules, retrait des espaces CIN, retrait des séparateurs de présentation du téléphone (espaces, points, parenthèses, tirets), conservation du zéro initial et du préfixe +261. Les matricules et numéros BAC conservent casse et ponctuation ; seuls les espaces répétés sont réduits. Les noms officiels ne sont ni mis en majuscules ni translittérés. Les textes vides deviennent NULL.

### Anti-doublons manuels

La saisie manuelle refuse un même matricule, CIN ou numéro BAC non vide dans la base entière, ainsi qu'une égalité nom/prénoms/date de naissance lorsque la naissance est fournie. Elle ne fusionne aucune identité, ne révèle aucune fiche étrangère et renvoie un message générique invitant à vérifier ou contacter la Scolarité Centrale. Le téléphone et l'email peuvent être partagés ; ils ne suffisent pas à conclure à un doublon.

`CreateDeclaredStudent` maintient un verrou de cache partagé `students:manual-identity-write` pendant la transaction identité + première déclaration ; `SaveStudentIdentity` utilise le même verrou pour l'édition. Les deux opérations recontrôlent l'autorisation côté Action. Le cache database existant fournit ce verrou en local ; les déploiements multi-instances doivent utiliser un store partagé supportant les locks. Les écritures sont courtes et sérialisées, avec attente de dix secondes et durée du verrou de soixante secondes. Ce mécanisme n'est pas une contrainte globale sur les futures données historiques ou imports : ceux-ci devront reprendre les contrôles de normalisation et doublons avec leur propre stratégie validée. Les changements d'identifiants ne sont pas garantis uniques pour des écritures SQL directes, volontairement.

### Déclaration annuelle et historique

`student_records` porte les FK restrictives `student_id`, `academic_year_id`, `establishment_id`, `level_id`, `declared_by` et le couple optionnel `program_id`/`establishment_id`. Cette dernière FK composite référence l'index unique `(id, establishment_id)` de `programs` : même une écriture SQL falsifiée ne peut associer un parcours à un autre établissement. Un parcours déjà utilisé ne peut plus changer d'établissement dans le formulaire de référentiels. Un compte ayant signé une déclaration est conservé : son formulaire de suppression signale une erreur de validation.

`StudentRecordStatus` comporte `active` et `inactive` : la déclaration est maintenue valide ou conservée inactive dans la plateforme. Ce ne sont jamais des statuts d'inscription universitaire. `declared_by` et `declared_at` sont imposés côté serveur lors de la création et restent inchangés lors des modifications.

Deux unicités sont retenues :

- `(student_id, academic_year_id, establishment_id)` empêche une deuxième fiche du même établissement pour une même année, même inactive ; modifier/réactiver la fiche existante est l'opération attendue.
- `(student_id, active_academic_year_id)` utilise une colonne virtuelle nullable, égale à l'année pour une déclaration active et NULL sinon. Elle impose au plus une déclaration active par étudiant/année, tout en permettant plusieurs anciennes déclarations inactives dans des établissements différents.

Le transfert n'est pas un workflow automatique : un administrateur autorisé peut, après désactivation explicite de la déclaration précédente, déclarer l'étudiant dans un autre établissement sans effacer l'historique. Le nouvel établissement n'obtient jamais un accès arbitraire à une identité étrangère pour effectuer ce rattachement. L'année et l'établissement d'une fiche existante sont immuables dans les mutations du module ; niveau et parcours sont modifiables. Une nouvelle année crée une nouvelle fiche.

`SaveStudentRecord` verrouille l'identité, recontrôle les permissions/périmètres, vérifie les référentiels et conflits annuels, puis persiste dans une transaction avec reprise limitée des deadlocks. Une création/activation exige des référentiels actifs et une identité active. Une modification historique peut conserver des référentiels devenus inactifs mais ne peut pas choisir de nouveaux référentiels inactifs. Une désactivation ne supprime rien. L'identité `is_active` et le statut annuel sont distincts : désactiver l'identité ne réécrit pas les déclarations existantes ; aucune nouvelle déclaration active ni réactivation annuelle n'est permise tant que l'identité reste inactive.

Index annuels : `(academic_year_id, establishment_id, status)`, `(establishment_id, level_id)`, FK et index des deux unicités. Les FK protègent l'historique contre les suppressions SQL liées ; les modèles refusent les suppressions Eloquent standard. Aucune route DELETE étudiante n'est exposée.

### Isolation, lecture et performance

Les Policies combinent permissions, rôle interne autorisé, contexte d'établissement actif et périmètre. Un compte ayant le rôle `student` est refusé explicitement, même s'il reçoit manuellement une permission. Aucun bypass superadmin/developer. La matrice existante n'est pas modifiée et la Scolarité Centrale reste en lecture seule.

Les établissements consultent uniquement les identités ayant une déclaration chez eux, y compris inactive pour conserver leur historique. Sur une fiche partagée, seuls leurs enregistrements annuels sont renvoyés. Pour protéger l'identité permanente commune, un établissement ne peut pas modifier/désactiver une identité déjà déclarée ailleurs (même historiquement) ; cette correction administrative nécessite un acteur global autorisé. Il conserve la gestion de ses propres déclarations.

La liste est une vue de déclarations annuelles : un étudiant peut apparaître sur plusieurs lignes correspondant à plusieurs années, avec pagination SQL de 20 lignes. Elle sélectionne uniquement matricule, nom, prénoms et statut d'identité dans la relation Student. CIN, naissance, contacts et BAC restent hors liste. Les relations utiles sont chargées en eager loading ; un test sur 25 déclarations borne les requêtes vers students indépendamment du nombre de lignes.

Recherche SQL normalisée : sous-chaîne pour nom/prénoms, préfixe pour matricule/CIN/BAC/téléphone/email afin d'exploiter les index lorsque le plan le permet. `%`, `_` et `!` sont échappés explicitement. Les filtres année/établissement/niveau/parcours/statut sont combinés côté SQL ; un filtre établissement falsifié est refusé en 403 pour un compte établissement. Les recherches partielles de noms peuvent scanner plus de lignes : aucune performance chiffrée sur plusieurs dizaines de milliers d'identités n'est prétendue sans données de charge.

Les contraintes critiques ont été vérifiées sur MySQL : FK, unicités annuelles, cohérence parcours/établissement, identifiants NULL non uniques, historique inactif et rollback avec audit. Les tests Pest utilisent toujours SQLite en mémoire ; les collations peuvent différer, notamment pour les noms et identifiants non normalisés en casse.
