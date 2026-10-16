# Modèle de données proposé

## Tables

- `users`
- `establishments`
- `academic_years`
- `levels`
- `domains`
- `mentions`
- `programs`
- `students`
- `student_records`
- `scholarship_campaigns`
- `scholarship_applications`
- `student_correction_requests`
- `student_information_confirmations`
- `imports`
- `import_rows`
- `activity_log`

## students

Identité permanente, alignée sur le fichier modèle des établissements : `last_name`, `first_names`, `sex`, `birth_date`, `mother_name`, `cin`, `cin_issued_at`, `cin_issued_place`, `nationality`, `phone`, `email`, `address`, `bac_series`, `bac_year`, `is_active`. Pas de matricule, de numéro BAC, de lieu de naissance ni de mention BAC (absents du modèle, supprimés par la migration `2026_10_17_100000_align_students_with_establishment_template`).

## student_records

Déclaration annuelle d'un étudiant déjà inscrit : `student_id`, `academic_year_id`, `establishment_id`, `level_id`, `program_id`, `repeat_code` (N, R, T+), `scholarship_requested_at`, `scholarship_request_reference`, `status`, `declared_by`, `declared_at`.

Indexes conseillés : `(academic_year_id, establishment_id)`, `(student_id, academic_year_id)`, `(establishment_id, level_id)`.

## scholarship_applications

Implémentée : voir « Bourses implémentées » ci-dessous.

## imports/import_rows

Conserver métadonnées d'import, compteurs, données brutes/normalisées, erreurs, warnings et éventuel étudiant correspondant.

## Principes

FK, transactions, historique, indexes, pas de duplication inutile, contraintes uniques seulement après confirmation de la qualité réelle des données historiques.

## Tables de fondation implémentées

Les migrations techniques sont complétées par `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (Spatie Permission) et `activity_log` (Spatie Activitylog). Les identifiants de modèles utilisent des relations polymorphes ; les pivots des rôles et permissions possèdent leurs contraintes et index fournis par le package.

`activity_log` conserve sujet, acteur, événement, journal, changements d'attributs autorisés et métadonnées minimales. La fondation identité n'ajoutait aucun rattachement métier à `users`. La phase Référentiels ajoute désormais `establishment_id` nullable ; le portail étudiant ajoute `student_id` (voir ci-dessous). Les tables étudiantes, du portail, des corrections, de confirmation et de bourse sont implémentées (sections ci-dessous).

## Référentiels institutionnels implémentés

- `establishments` : code obligatoire unique (30 caractères, présenté comme « sigle »), nom, type optionnel (Faculté, Institut, École ou texte libre « Autre », 100 caractères), `sector` optionnel (`public`, `private` = colonne « Institution » du modèle), contacts optionnels, `logo_path` optionnel (migration `2026_10_16_110000_add_logo_to_establishments`), statut actif, timestamps. Codes normalisés en majuscules et sans espaces périphériques. Les contacts restent visibles aux administrateurs autorisés mais leurs valeurs ne sont pas dupliquées dans l'audit.
- `academic_years` : nom unique (30 caractères), dates optionnelles, `scholarship_max_bac_year` optionnel (année limite du BAC pour la règle de bourse), `is_current`, `is_active`, timestamps. `current_marker` est une colonne virtuelle technique valant 1 pour l'année courante et NULL sinon ; son index unique impose au plus une année courante, y compris hors interface. Cette colonne est masquée dans la sérialisation.
- `levels` : code obligatoire unique (30 caractères), nom, ordre entier positif ou nul, statut actif et timestamps. Affichage stable par `sort_order`, puis code et id ; les niveaux ne sont pas codés en dur.
- `domains` : domaines LMD communs à tous les établissements (ALSH…) : code unique, nom, ordre, statut actif ; gérés avec la permission `levels.manage`, sans corbeille (désactivation seulement).
- `programs` : FK restrictive `establishment_id`, code optionnel (30 caractères), nom, `training_type` optionnel (`academic`, `professional` = « Type de formation » du modèle), statut actif, timestamps. Unicité du code par établissement ; plusieurs codes NULL sont admis. Aucune unicité globale du nom, aucune relation imposée à un niveau.
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

`students` contient `last_name` obligatoire (150), `first_names` nullable (150), `sex` (`M`/`F`), date de naissance, `mother_name` (255, « Nom et prénoms de la mère »), CIN (100, date et lieu de délivrance), `nationality` (50), téléphone (30), email (255), adresse (text), série BAC (50), année BAC (unsigned small integer), `is_active` et timestamps. Les colonnes sont nullable en base pour les fiches antérieures ; la validation (saisie, import, correction) exige nom, sexe, date de naissance et nationalité. Nationalité et série BAC suivent les listes du modèle (`StudentReferenceLists`).

`cin`, `phone` et `email` sont indexés sans contrainte UNIQUE SQL (unicité applicative, voir ci-dessous). `last_name`, `is_active` et le triplet nom/prénoms/naissance sont également indexés. Les tailles sont compatibles avec les limites d'index MySQL utf8mb4. Aucun `user_id` n'est ajouté : le rattachement d'un compte étudiant reste une phase ultérieure.

La normalisation s'applique dans les Form Requests et le modèle : trim des textes, email en minuscules, retrait des espaces et points de la CIN, téléphone sous forme nationale `0XXXXXXXXX` (séparateurs retirés, préfixe +261/261 remplacé, zéro initial perdu par une cellule numérique restauré pour 9 chiffres), sexe `M`/`F` (Masculin, Féminin, H… acceptés), nationalité et série BAC à l'orthographe de la liste, code de redoublement réduit au code (« N (Nouveau) » → `N`), espaces répétés réduits dans les noms et la référence de demande. Les noms officiels ne sont ni mis en majuscules ni translittérés. Les textes vides deviennent NULL.

### Anti-doublons manuels

La saisie manuelle refuse une CIN standard (12 chiffres), un téléphone ou un email déjà portés par une autre fiche (« Unicité à respecter » du modèle), ainsi qu'une égalité nom/prénoms/date de naissance. Une CIN non standard (récépissé, numéro partiel) est acceptée, signalée « CIN non standard » sur la fiche, et n'identifie personne : elle peut être partagée. La saisie ne fusionne aucune identité, ne révèle aucune fiche étrangère et renvoie un message générique pour la CIN et l'identité.

`CreateDeclaredStudent` maintient un verrou de cache partagé `students:manual-identity-write` pendant la transaction identité + première déclaration ; `SaveStudentIdentity` utilise le même verrou pour l'édition. Les deux opérations recontrôlent l'autorisation côté Action. Le cache database existant fournit ce verrou en local ; les déploiements multi-instances doivent utiliser un store partagé supportant les locks. Les écritures sont courtes et sérialisées, avec attente de dix secondes et durée du verrou de soixante secondes. Les imports utilisent désormais des verrous DB ciblés partagés avec la saisie manuelle (voir section Imports), sans appeler cette Action sous le verrou de cache. Les changements d'identifiants ne sont pas garantis uniques pour des écritures SQL directes, volontairement.

### Déclaration annuelle et historique

`student_records` porte les FK restrictives `student_id`, `academic_year_id`, `establishment_id`, `level_id`, `declared_by` et le couple optionnel `program_id`/`establishment_id`. Cette dernière FK composite référence l'index unique `(id, establishment_id)` de `programs` : même une écriture SQL falsifiée ne peut associer un parcours à un autre établissement. Un parcours déjà utilisé ne peut plus changer d'établissement dans le formulaire de référentiels. Un compte ayant signé une déclaration est conservé : son formulaire de suppression signale une erreur de validation.

`StudentRecordStatus` comporte `active` et `inactive` : la déclaration est maintenue valide ou conservée inactive dans la plateforme. Ce ne sont jamais des statuts d'inscription universitaire. `declared_by` et `declared_at` sont imposés côté serveur lors de la création et restent inchangés lors des modifications.

Deux unicités sont retenues :

- `(student_id, academic_year_id, establishment_id)` empêche une deuxième fiche du même établissement pour une même année, même inactive ; modifier/réactiver la fiche existante est l'opération attendue.
- `(student_id, active_academic_year_id)` utilise une colonne virtuelle nullable, égale à l'année pour une déclaration active et NULL sinon. Elle impose au plus une déclaration active par étudiant/année, tout en permettant plusieurs anciennes déclarations inactives dans des établissements différents.

Le transfert n'est pas un workflow automatique : un administrateur autorisé peut, après désactivation explicite de la déclaration précédente, déclarer l'étudiant dans un autre établissement sans effacer l'historique. Le nouvel établissement n'obtient jamais un accès arbitraire à une identité étrangère pour effectuer ce rattachement. L'année et l'établissement d'une fiche existante sont immuables dans les mutations du module ; niveau et parcours sont modifiables. Une nouvelle année crée une nouvelle fiche.

`SaveStudentRecord` verrouille l'identité, recontrôle les permissions/périmètres, vérifie les référentiels et conflits annuels, puis persiste dans une transaction avec reprise limitée des deadlocks. Une création/activation exige des référentiels actifs et une identité active. Une modification historique peut conserver des référentiels devenus inactifs mais ne peut pas choisir de nouveaux référentiels inactifs. Une désactivation ne supprime rien. L'identité `is_active` et le statut annuel restent deux colonnes distinctes, mais désactiver l'étudiant (`SetStudentStatus`) désactive aussi ses déclarations actives de l'année courante, marquées `inactive_with_identity` ; le réactiver restaure exactement ces déclarations (sauf si une autre déclaration de l'année est devenue active entre-temps). Les années passées ne sont pas modifiées. Un changement manuel du statut d'une déclaration efface la marque. Aucune nouvelle déclaration active ni réactivation annuelle n'est permise tant que l'identité reste inactive.

Index annuels : `(academic_year_id, establishment_id, status)`, `(establishment_id, level_id)`, FK et index des deux unicités. Les FK protègent l'historique contre les suppressions SQL liées ; les modèles refusent les suppressions Eloquent standard. Aucune route DELETE étudiante n'est exposée.

### Isolation, lecture et performance

Les Policies combinent permissions, rôle interne autorisé, contexte d'établissement actif et périmètre. Un compte ayant le rôle `student` est refusé explicitement, même s'il reçoit manuellement une permission. Aucun bypass superadmin/developer. La matrice existante n'est pas modifiée et la Scolarité Centrale reste en lecture seule.

Les établissements consultent uniquement les identités ayant une déclaration chez eux, y compris inactive pour conserver leur historique. Sur une fiche partagée, seuls leurs enregistrements annuels sont renvoyés. Pour protéger l'identité permanente commune, un établissement ne peut pas modifier/désactiver une identité déjà déclarée ailleurs (même historiquement) ; cette correction administrative nécessite un acteur global autorisé. Il conserve la gestion de ses propres déclarations.

La liste est une vue de déclarations annuelles : un étudiant peut apparaître sur plusieurs lignes correspondant à plusieurs années, avec pagination SQL de 20 lignes. Elle sélectionne uniquement nom, prénoms, sexe, date de naissance et statut d'identité dans la relation Student. CIN, contacts et BAC restent hors liste. Les relations utiles sont chargées en eager loading ; un test sur 25 déclarations borne les requêtes vers students indépendamment du nombre de lignes.

Recherche SQL normalisée : sous-chaîne pour nom/prénoms, préfixe pour CIN/téléphone/email afin d'exploiter les index lorsque le plan le permet. `%`, `_` et `!` sont échappés explicitement. Les filtres année/établissement/niveau/parcours/statut sont combinés côté SQL ; un filtre établissement falsifié est refusé en 403 pour un compte établissement. Les recherches partielles de noms peuvent scanner plus de lignes : aucune performance chiffrée sur plusieurs dizaines de milliers d'identités n'est prétendue sans données de charge.

Les contraintes critiques ont été vérifiées sur MySQL : FK, unicités annuelles, cohérence parcours/établissement, identifiants NULL non uniques, historique inactif et rollback avec audit. Les tests Pest utilisent toujours SQLite en mémoire ; les collations peuvent différer, notamment pour les noms et identifiants non normalisés en casse.

## Imports Excel/CSV implémentés

Laravel Excel **4.0.4** et PhpSpreadsheet **5.10.0**, compatibles Laravel 13/PHP 8.4. Aucun changement des autres versions verrouillées.

- `student_imports` : FK restrictives établissement/année/auteur ; nom original, chemin UUID privé, disque ; `StudentImportStatus` ; headers/mapping JSON ; `excel_calendar` (1900/1904) ; compteurs ; dates uploaded/analyzed/committed/failed et marqueur `purged_at`. Index `(establishment_id, created_at)` et `(status, created_at)`.
- `student_import_rows` : FK import avec cascade réservée à la gestion technique, numéro physique de ligne, raw/normalized JSON, `StudentImportRowStatus`, erreurs/avertissements structurés `{field, code, message}`, FK restrictives vers identité/déclaration reconnues, résolution `imported`/`skipped`. Unicité `(student_import_id, row_number)` et index `(student_import_id, status, id)`. Aucune route DELETE.
- `student_identity_locks` : clé SHA-256 primaire, sans valeur personnelle brute. Verrous transactionnels persistants sur CIN standard, téléphone, email et identité exacte avec naissance ; clés triées pour limiter les deadlocks. Ne pas purger ces clés pendant l'exploitation : une suppression concurrente détruirait la coordination. Les clés sont des pseudonymes techniques à protéger dans les sauvegardes.

`created_rows` compte les nouvelles identités ; `updated_rows` les identités dont des champs vides ont été complétés ; `declared_rows` les nouvelles déclarations, y compris pour une identité réutilisée sans changement ; `skipped_rows = total_rows - declared_rows`. Les compteurs valid/warning/rejected/duplicate sont recalculés après confirmation si la base a changé.

Analyse : collecte groupée des candidats pour chaque bloc, union des correspondances CIN standard et nom/prénoms/naissance. Plusieurs candidats impliquent une revue, jamais une fusion. Téléphone et email ne rapprochent pas deux fiches : portés par une autre identité, ils rejettent la ligne (unicité). Une référence de demande de bourse déjà attribuée à un autre étudiant dans l'établissement et l'année rejette aussi la ligne. Dans le fichier, une même identité répétée est un doublon ; une autre identité réutilisant CIN standard, téléphone, email ou référence est rejetée. Une seule identité visible chez l'établissement, sans historique étranger, est réutilisable ; sinon message générique sans ID ou valeurs officielles étrangers. Les valeurs non vides différentes déclenchent un warning sur **tous** les champs, contacts compris. Des valeurs DB vides peuvent être complétées, après validation et contrôle de cohérence des dates combinées.

Même déclaration annuelle et mêmes niveau, parcours, code de redoublement et demande de bourse : duplicate sans modification, même si inactive. Valeurs différentes : warning. Déclaration active étrangère : warning, sans transfert. Warnings/rejected/duplicate ne sont jamais écrits dans les tables métier.

Confirmation : transaction unique avec verrou de l'import, verrous partagés des référentiels et verrous ciblés d'identité, puis nouvelle analyse de chaque ligne valid et verrouillage des candidats/déclarations. Les écritures Eloquent conservent l'audit. Reprise limitée des deadlocks ; échec critique rollback identité/déclaration/staging/audit, puis statut failed avec motif générique sans données personnelles. Un retry completed est un no-op sans second audit. La saisie manuelle conserve son verrou court de cache et acquiert les mêmes verrous DB avant les contrôles de doublons. Les écritures SQL directes ne bénéficient pas de ce protocole.

SQLite conserve les tests automatisés rapides ; MySQL est vérifié séparément avec `php tests/MySql/StudentImportChecks.php` sur la connexion locale. Toutes les données de contrôle sont rollbackées. Les collations MySQL peuvent rapprocher casse/accents différemment de SQLite : le matching final et les clés utilisent un repli ASCII/minuscule, les conflits de valeurs restent stricts. Les dates SQLite peuvent être stockées avec minuit, les recherches incluent les deux formes date et date/minuit.

## Portail étudiant implémenté

Migration `2026_10_14_180000_create_student_portal_foundation` :

- `users.student_id` : FK nullable vers `students`, `restrictOnDelete`, **unique** (une fiche = un compte au plus). Les comptes internes restent à `NULL`. Colonne cachée dans la sérialisation.
- `users.normalized_email` : colonne générée virtuelle `LOWER(email)`, unique : deux comptes ne peuvent différer que par la casse de l'email.
- `email_otp_challenges` : UUID, email, `email_key` HMAC unique (un défi actif par adresse), `code_hash` (bcrypt d'un HMAC du code), `session_hash` (lien au navigateur), `attempts`, `expires_at` indexé, `consumed_at`. Le code en clair n'est jamais stocké. Purge horaire par `students:purge-otp`.
- `portal_email_locks` : clés HMAC servant de verrous de ligne (sérialisation des créations de compte par email / sujet Google).
- `social_accounts` : `provider`, `provider_user_id`, `provider_email`, uniques `(provider, provider_user_id)` et `(user_id, provider)`. Aucun jeton OAuth stocké.
- `student_link_requests` : `user_id`, `student_id`, `status` (`pending`, `approved`, `rejected`, `cancelled`), `requested_at`, `reviewed_at`, `reviewed_by`, `rejection_reason` (motif interne). Unique `(user_id, student_id)` et colonne virtuelle `pending_user_id` unique : une seule demande pending par compte. Les données saisies par l'étudiant (identifiant, naissance) ne sont pas persistées.

Toutes les FK sont restrictives ; aucune suppression en cascade. Le rollback supprime les tables puis les colonnes de `users`, sans toucher aux autres données.

## Demandes de correction implémentées

Migration `2026_10_14_190000_create_student_correction_requests`, table `student_correction_requests` :

- `student_id` (FK restrictive, toujours issu de `users.student_id` du demandeur), `user_id` (FK, compte demandeur), `field` (enum `StudentCorrectionField`, liste blanche), `status` (`pending`, `approved`, `rejected`, `cancelled`).
- `proposed_value` : texte **chiffré** (cast `encrypted`, clé `APP_KEY`), normalisé comme la saisie administrative. Nécessaire pour appliquer la décision ; jamais journalisé.
- `current_fingerprint` : HMAC-SHA256 (clé `APP_KEY`) de la valeur officielle observée lors de la demande. **La valeur actuelle n'est pas copiée** : la revue l'affiche depuis la fiche, et l'empreinte sert au contrôle de cohérence optimiste. Une rotation d'`APP_KEY` rend les demandes pending en conflit (échec sûr) et le chiffrement exige la conservation des anciennes clés (`APP_PREVIOUS_KEYS`).
- `student_message` (500), `rejection_reason` (500, visible par l'étudiant), `reviewed_by` (FK), `reviewed_at`, timestamps.
- `pending_marker` : colonne virtuelle (1 si pending, sinon NULL) et unique `student_corrections_pending_field_unique (student_id, field, pending_marker)` : une seule demande pending par étudiant et champ, les demandes décidées restant illimitées.
- Index `(status, created_at)` pour la file de revue et `(user_id, created_at)` pour « Mes demandes ».

Aucune suppression en cascade ; le rollback supprime uniquement cette table. Vérifications MySQL dédiées (contrainte unique, FK, attente de verrou entre deux connexions, rollback) : `php tests/MySql/StudentCorrectionChecks.php`, **uniquement sur une base jetable dont le nom contient `check`** (le script écrit des données réelles).

## Confirmations étudiantes implémentées

Migration `2026_10_14_210000_create_student_information_confirmations`, table `student_information_confirmations` : historique des déclarations « mes informations affichées sont correctes ».

- `student_id`, `user_id` (compte qui confirme), `student_record_id` et `academic_year_id` (déclaration courante confirmée), tous dérivés côté serveur ; FK restrictives, aucune cascade.
- `sequence` : rang dans l'historique de l'étudiant, unique `(student_id, sequence)`. Deux confirmations concurrentes ne peuvent pas occuper le même rang.
- `information_fingerprint` : HMAC-SHA256 (clé `APP_KEY`) de la version confirmée, **jamais une copie des valeurs**. Aucun nom, contact, naissance, BAC ni CIN dans cette table. Masqué à la sérialisation.
- `confirmed_at`, timestamps. Aucune colonne d'état : « confirmée », « obsolète » ou « jamais confirmée » est **déduit** à chaque lecture en comparant la dernière confirmation (rang le plus élevé) à l'empreinte de la situation actuelle. Aucune Action Student ne touche cette table.

Contenu canonique de l'empreinte (`StudentInformationFingerprint`, version `v2` depuis l'alignement sur le modèle établissement : toutes les confirmations `v1` apparaissent obsolètes) : ordre de clés fixe, valeurs préfixées par leur longueur, NULL distinct de la chaîne vide, dates `Y-m-d` ; jamais de JSON.

- Student : `last_name`, `first_names`, `sex`, `birth_date`, `mother_name`, `nationality`, `phone`, `email`, `address`, `bac_series`, `bac_year` (exactement les champs affichés sur `bourse`). Les données CIN, jamais affichées à l'étudiant, sont exclues.
- Déclaration courante : `id`, `status`, `repeat_code` ; année, établissement, niveau, parcours : **identifiant et nom affiché** ; nom affiché de la mention. Renommer un parcours ou un niveau rend donc la confirmation obsolète : l'étudiant n'a pas vu ce libellé.
- Les déclarations des autres années, affichées pour l'historique, ne font pas partie de la version confirmée.

Rotation d'`APP_KEY` : toutes les empreintes deviennent non comparables, donc toutes les confirmations apparaissent obsolètes (échec sûr, même convention que les corrections) ; l'étudiant confirme à nouveau. Vérifications MySQL : `php tests/MySql/StudentConfirmationChecks.php`, **uniquement sur une base jetable dont le nom contient `check`**.

## Bourses implémentées

Migration `2026_10_14_200000_create_scholarship_tables`. Toutes les FK sont restrictives, sans cascade ; le rollback supprime uniquement ces quatre tables.

- `scholarship_campaigns` : `academic_year_id`, `name` (unique par année), `starts_at`/`ends_at` indicatives, `status` (indexé), `notes`, `created_by`, `last_application_sequence` (compteur de numérotation, incrémenté sous verrou de la ligne campagne).
- `scholarship_applications` : le dossier papier reçu. `application_number` unique ; `scholarship_campaign_id`, `sequence` (unique par campagne) ; `student_id` et `student_record_id` (déclaration annuelle retenue à la réception) ; `ddb_number` et `ddb_key` ; `status` ; `received_by`/`received_at` ; `review_started_by`/`review_started_at` (**dernier début de vérification**) ; `decided_by`/`decided_at` (**décision définitive uniquement** : validation ou rejet) ; `decision_reason` (motif de la demande de correction en cours ou du rejet) ; `internal_notes`. Aucune donnée personnelle du formulaire papier n'est recopiée : la comparaison se fait avec `students`/`student_records`.
    - Unique `(campaign, student)` : un dossier par étudiant et campagne.
    - Unique `(campaign, ddb_key)` : un numéro DDB fourni est unique dans sa campagne ; plusieurs dossiers sans DDB restent possibles (NULL).
    - Index `(campaign, status, received_at)`, `(status, received_at)`, `received_at`, `ddb_key`, plus les index des FK `student_id` et `student_record_id`.
- `scholarship_application_checks` : un contrôle par `(application, key)` unique ; `status` (`pending`, `ok`, `issue`, `not_applicable`), `note`, `checked_by`/`checked_at`. Les clés sont copiées depuis `config/scholarships.php` à la réception : modifier la liste ne change pas les dossiers déjà reçus.
- `scholarship_application_status_histories` : historique métier `from_status` (NULL à la réception) → `to_status`, `actor_id`, `reason`, `created_at`. ActivityLog reste l'audit technique.

**Numéro de dossier** : format technique `BRS{id campagne}-{séquence sur 6 chiffres}`, par exemple `BRS3-000042` (préfixe configurable). Ce n'est pas un format officiel. Il est attribué par le serveur, stable et unique (contrainte SQL). La séquence n'est consommée que si la réception est validée dans la même transaction.

**Numéro DDB** : sa structure réglementaire n'est pas connue. Il est conservé tel que saisi, en chaîne, après suppression des espaces de début et de fin et regroupement des espaces internes. La clé de doublon `ddb_key` retire les espaces et passe en majuscules, sans toucher aux autres caractères. Il n'y a pas d'unicité globale, seulement par campagne.

Vérifications MySQL : `php tests/MySql/ScholarshipChecks.php`, **uniquement sur une base jetable dont le nom contient `check`**.

## Mentions implémentées

Migration `2026_10_15_100000_create_mentions`. Organisation LMD : établissement → mention → parcours ; le niveau reste porté par la déclaration annuelle.

- `mentions` : `establishment_id` (FK restrictive), `code` facultatif unique par établissement, `name`, `is_active`, timestamps. Unique `(id, establishment_id)` pour la FK composite.
- `programs.mention_id` : facultatif (les parcours existants restent « Sans mention » jusqu'à leur rattachement), FK composite `(mention_id, establishment_id) → mentions(id, establishment_id)` : un parcours et sa mention appartiennent toujours au même établissement, garanti en base. Aucune cascade ; le rollback retire la colonne puis la table.
- La mention d'un étudiant est déduite de son parcours (`student_records.program_id → programs.mention_id`) : aucune colonne mention dans les déclarations ni dans l'import.

## Identifiants publics (UUID)

Migration `2026_10_15_110000_add_uuid_to_students` : colonne `students.uuid` (UUID v7, unique, non nulle), générée par le modèle à la création (saisie manuelle et import passent par Eloquent) et remplie pour les fiches existantes par la migration. Elle sert de clé de route (`/students/{uuid}`, modification, statut, nouvelle déclaration) ; l'`id` entier reste la clé primaire et la cible de toutes les FK. Une URL avec l'identifiant numérique ou un UUID invalide répond 404.

Migration `2026_10_15_120000_add_uuid_to_routed_tables` : même colonne `uuid` (UUID v7, unique, non nulle, remplie par lots pour les lignes existantes) sur `establishments`, `academic_years`, `levels`, `mentions`, `programs`, `student_records`, `student_imports`, `student_correction_requests`, `student_link_requests`, `scholarship_campaigns`, `scholarship_applications` et `activity_log`. Les modèles utilisent le trait `App\Models\Concerns\HasPublicUuid` (génération à la création, clé de route `uuid`) ; le journal d'audit passe par `App\Models\Activity` (`activitylog.activity_model`). Toute URL interne identifiant un enregistrement (référentiels, déclarations, imports, corrections, rattachements, campagnes, dossiers, audit) et l'API étudiante `POST /api/student/corrections/{uuid}/cancel` utilisent l'UUID ; les `id` entiers restent clés primaires, cibles des FK et valeurs des formulaires (sélecteurs, filtres). Une insertion SQL directe dans ces tables doit fournir `uuid`. Le rollback supprime index unique puis colonne, table par table.

## Corbeille administrative et contraintes de restauration

Migrations `2026_10_15_130000_add_trash_columns` et `130100_make_trash_restoration_constraints_safe` : `deleted_at` (indexé), `deleted_by` (FK nullable vers users, nullOnDelete), `trash_batch` (UUID indexé) sur établissements, années, niveaux, mentions, parcours, campagnes, imports, étudiants, déclarations et dossiers. `Trashable` applique le scope SoftDeletes et refuse delete/forceDelete/restore hors `TrashManager`. Les UUID publics et les ID/FK existants restent inchangés.

La suppression d'un étudiant déplace ses déclarations visibles dans le même lot. La restauration ne restaure que les déclarations supprimées avec lui, jamais celles déjà en corbeille auparavant. Un référentiel ou une campagne utilisé, même par un enfant en corbeille, n'est pas supprimable ; désactiver/archiver reste l'action standard. Un étudiant/une déclaration portant un dossier visible doit d'abord faire traiter ce dossier. Imports analyzing/committing protégés.

Les slots d'unicité des lignes visibles sont désormais conditionnés par `deleted_at IS NULL` : `academic_years.current_marker`, `student_records.active_academic_year_id` et nouveau `student_records.live_marker` dans l'unicité étudiant/année/établissement. Un index indépendant sur `student_records.student_id` assure le support de sa FK lorsque les index composites sont reconstruits (MySQL 1553 sinon). Les codes/noms uniques des référentiels restent réservés en corbeille. Restauration : contrôle des identifiants étudiants, année courante, déclaration annuelle et parents disponibles, puis contraintes SQL comme dernier rempart ; refus transactionnel en cas de conflit.

Purge : uniquement après mise en corbeille et contrôle des dépendances. Une identité liée à un compte, une correction, un rattachement, une confirmation, un import ou un dossier ne peut pas être purgée. Ses déclarations doivent être en corbeille et sans dépendance. Un dossier déjà traité n'est pas purgeable ; seul un dossier pending sans contrôle réalisé ni historique de traitement supplémentaire peut l'être, avec ses lignes de checklist/historique de réception. Un import purgé supprime seulement son staging et son fichier, jamais les Student/StudentRecord produits. Les audits ne sont pas supprimés.

Migration `130200_create_pending_file_deletions` : table technique privée disk/path uniques, attempts, classe d'erreur éventuelle et dates. Une purge planifie la suppression de fichier dans sa transaction ; la tentative réelle commence après commit. Un échec stockage reste réessayable par `trash:cleanup-files` (horaire), sans contenu personnel dans les logs. La corbeille affiche le nombre de fichiers en attente. La rétention de staging à 30 jours s'applique aussi aux imports en corbeille ; restaurer un import expiré ne recrée pas le fichier ni ses données purgées.

Le rollback du schéma vers les anciennes unicités nécessite de résoudre préalablement les conflits permis dans la corbeille (années courantes/déclarations supprimées). Aucun rollback destructif n'a été exécuté sur les données locales. Contrôles MySQL reproductibles : `php tests/MySql/TrashPermissionsChecks.php`, transaction externe et rollback intégral des données temporaires.

Migration `2026_10_16_100000_add_internal_user_management` : `users.uuid` (UUID v7 public, unique, rempli pour l'existant), `users.is_active` (défaut vrai) et table `user_denied_permissions` (`user_id`, `permission_id`, `created_at`, clé primaire composite, suppression en cascade) pour les droits du rôle retirés à un compte.

Migration `2026_10_16_110000_add_logo_to_establishments` : `establishments.logo_path` nullable, chemin sur le disque privé `local` (`establishment-logos/{uuid7}.{ext}`).

Migration `2026_10_16_120000_create_platform_settings` : table clé/valeur `platform_settings` (`key` clé primaire, `value` texte nullable, timestamps) pour l'identité de la plateforme : `institution_name`, `scol_name`, `bourse_name`, `support_email`, `logo_path`, `favicon_path` (fichiers sur le disque privé `local`, dossier `branding/`).

## Alignement sur le fichier modèle des établissements

Migration `2026_10_17_100000_align_students_with_establishment_template`. Le fichier Excel transmis par les établissements (une ligne par étudiant, 26 colonnes) est la source de vérité des champs étudiants.

| Colonne du modèle                                            | Stockage                                                                                                                                                                                           |
| ------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| NOM, PRENOMS, SEXE, DATE DE NAISSANCE, Nom et prénom de mère | `students.last_name`, `first_names`, `sex`, `birth_date`, `mother_name`                                                                                                                            |
| CIN, Date de délivrance, Lieu de délivrance                  | `students.cin`, `cin_issued_at`, `cin_issued_place`                                                                                                                                                |
| NATIONALITÉ, ANNÉE D'OBTENTION DU BACC, SÉRIE DU BACC        | `students.nationality`, `bac_year`, `bac_series`                                                                                                                                                   |
| ADRESSE EXACTE, NUMERO TELEPHONE, ADRESSE MAIL               | `students.address`, `phone`, `email` (téléphone et email uniques)                                                                                                                                  |
| CODE DE REDOUBLEMENT                                         | `student_records.repeat_code` (`N`, `R`, `T+`)                                                                                                                                                     |
| DATE DE DÉPÔT / RÉFÉRENCE DEMANDE DE BOURSE                  | `student_records.scholarship_requested_at`, `scholarship_request_reference` (unique par établissement et année, index `student_records_request_reference_unique` limité aux lignes hors corbeille) |
| BOURSIER, TAUX DE BOURSE                                     | non stockés : formules du modèle recalculées par le serveur (`ScholarshipEligibility`)                                                                                                             |
| Niveau, Mention, Parcours                                    | `student_records.level_id`, `program_id` (mention déduite du parcours)                                                                                                                             |
| Domaine                                                      | `mentions.domain_id` → `domains`                                                                                                                                                                   |
| Type de formation                                            | `programs.training_type`                                                                                                                                                                           |
| Institution                                                  | `establishments.sector`                                                                                                                                                                            |
| École / Institut                                             | établissement de l'import (contrôle de concordance)                                                                                                                                                |

Règle de bourse indicative (`ScholarshipEligibility`, reprise des formules du modèle) : éligible si nationalité Malagasy, année du BAC ≤ `academic_years.scholarship_max_bac_year`, référence de demande présente et code `N` (taux 1) ou `R` (taux 1/4) ; `T+` n'est pas boursier. Sans année limite définie, le résultat est « indéterminé ». La règle est affichée sur la fiche interne ; elle ne remplace pas la vérification du dossier papier par la Scolarité Centrale.

Suppression des colonnes `student_number`, `birth_place`, `bac_registration_number`, `bac_mention` : leurs valeurs sont perdues, ainsi que les demandes de correction portant sur ces champs (supprimées par la migration, faute de champ cible). Le `down()` recrée les colonnes vides.

## Notifications du personnel

Migration `2026_10_17_170000_create_notifications_table` : table Laravel `notifications` (UUID primaire, type, destinataire polymorphe, données JSON encodées en texte, `read_at`, timestamps), index destinataire + état de lecture ; `users.notification_preferences` JSON nullable (`off` = types désactivés, aucun par défaut). Données stockées : type, titre, message bref avec le nom, UUID étudiant et UUID du sujet. Aucune valeur corrigée, CIN, contact, empreinte ou note étudiante. La lecture expose uniquement les champs de présentation, après contrôle des droits et du périmètre courant. Notifications et action métier appartiennent à la même transaction.
