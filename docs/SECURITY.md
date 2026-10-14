# Sécurité

- Autorisation serveur et moindre privilège.
- Isolation stricte par établissement.
- Validation des fichiers importés.
- Protection CIN/date naissance/téléphone/email/adresse.
- Exports soumis aux permissions et au périmètre.
- Aucun secret dans Git.
- Ne pas loguer mots de passe/tokens/secrets.
- En production : `APP_DEBUG=false`, HTTPS, sauvegardes DB, rotation des logs, permissions fichiers correctes.

## Rôle developer

Tracer connexions, modifications, exports, changements de configuration/permissions. Ne jamais créer un superuser invisible.

## Fondation identité et audit

- Spatie Permission utilise le guard `web` et des attributions explicites ; `developer` est un rôle ordinaire identifiable, sans exception dans l'audit.
- Spatie Activitylog est activé dans la configuration, avec écritures synchrones. Aucun rôle ne désactive la journalisation.
- Les connexions réussies et déconnexions sont tracées dans le journal `authentication`, avec l'identifiant du compte et le guard, sans email ni credentials.
- Les événements de création, modification et suppression de `User` sont tracés dans le journal `identity`. Seuls `name` et `email_verified_at` peuvent figurer dans les changements d'attributs ; email, mot de passe, remember token, secrets 2FA et credentials passkeys sont exclus.
- Une modification limitée à un secret conserve un événement d'audit sans sa valeur. Les attributions et retraits de rôles/permissions sont tracés dans le journal `access`, avec les identifiants concernés. En CLI, l'acteur peut être nul (opération système) ; un acteur authentifié, y compris developer, est conservé.
- Les futurs journaux étudiants, imports, décisions et exports devront fournir explicitement sujet, acteur, événement et métadonnées minimales. Ne jamais passer une requête entière ou des attributs sensibles à `withProperties()` : l'exclusion des attributs de modèles ne nettoie pas automatiquement les métadonnées manuelles.
- Aucune interface d'audit ni purge automatique n'est créée. La commande de nettoyage Spatie n'est pas planifiée ; la politique de conservation doit être validée avant toute purge.

## Provisionnement du premier superadmin

1. Renseigner `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` dans l'environnement sécurisé local ou de production (jamais dans Git).
2. Exécuter `php artisan migrate`, puis `php artisan db:seed` ; en production, utiliser les options `--force` de façon explicite après vérification de l'environnement cible.
3. Le seeder crée les rôles/permissions, puis crée ou retrouve le compte par email et lui attribue `superadmin` dans une transaction. Un nouveau compte exige un nom et un mot de passe de 12 caractères minimum contenant majuscules, minuscules, chiffres et symboles. La vérification d'email habituelle reste requise.
4. Pour un compte existant, seul `SUPERADMIN_EMAIL` est nécessaire : ses données, son mot de passe et ses autres rôles sont conservés. Cette commande constitue un octroi explicite de privilèges et doit être exécutée uniquement par un opérateur autorisé.
5. Toutes les variables absentes : aucun compte de démonstration n'est créé. Une configuration partielle invalide échoue sans afficher les valeurs secrètes. Relancer ne réinitialise jamais le mot de passe ni ne duplique le compte.
6. Après provisionnement, retirer les valeurs de bootstrap de l'environnement sécurisé et reconstruire le cache de configuration si utilisé. Ne pas exporter `config/provisioning.php` résolu, qui peut contenir le secret pendant le bootstrap.

## Inscription publique

L'inscription Fortify du starter kit est désactivée globalement tant que les domaines ne sont pas routés : GET et POST `/register` ne sont plus exposés, y compris sur `scol`. Les liens de création de compte ont été retirés de l'accueil et de la connexion. Les tests historiques d'inscription restent présents mais sont ignorés lorsque la fonctionnalité Fortify est désactivée ; des tests dédiés vérifient désormais le refus effectif de création.

La création de compte étudiant sur `bourse` (`/api/student/*`) est indépendante de cette inscription administrative et n'utilise pas Fortify, qui reste désactivé pour l'inscription. Voir « Portail étudiant ».

## Données étudiantes et audit minimal

Les routes internes Student/StudentRecord refusent les comptes student avant leur futur rattachement sécurisé. Les Policies et les Actions recontrôlent permission et établissement ; modifier une URL ou envoyer un établissement/parcours étranger ne donne aucun droit supplémentaire. La Scolarité Centrale consulte sans éditer. La navigation dépend de l'autorisation serveur partagée, et non d'un rôle supposé côté React.

L'audit `students` conserve événement, identifiant du sujet, acteur, liste des noms de champs modifiés et changements du seul indicateur `is_active`. Il n'enregistre ni nom/prénoms, matricule, CIN, téléphone, email, adresse, date/lieu de naissance ni valeurs BAC, même en old/new. Le hook `beforeActivityLogged` du package Activitylog 5 ajoute les noms de champs sans leurs valeurs. L'audit `student_records` conserve uniquement les identifiants administratifs, statut et attribution/date de déclaration. Toutes les écritures du workflow sont synchrones et leur audit est annulé avec le rollback transactionnel. Le developer reste identifiable comme acteur.

Les contacts et identifiants personnels ne sont pas renvoyés dans la liste principale mais restent accessibles dans les fiches autorisées. Les recherches personnelles figurent dans les URL de filtres : elles peuvent être présentes dans l'historique navigateur ou les access logs de l'infrastructure. La production doit limiter l'accès à ces logs et éviter de capturer les paramètres sensibles dans la télémétrie. Aucun journal manuel ne reçoit le payload d'une requête.

Les données officielles sont stockées en base avec contrôle d'accès ; cette phase ne met pas en place un chiffrement applicatif de tous les champs recherchables. Le durcissement d'infrastructure (TLS, sauvegardes protégées, compte DB au moindre privilège, accès restreint et rétention) reste applicable.

## Sécurité des imports étudiants

Les fichiers sont stockés sur le disque local dédié `student_imports`, sous `storage/app/private/student-imports`, visibilité private, serve false, nom UUID. Aucun téléchargement du fichier original n'est exposé. Extension et MIME validés côté Form Request ; XLSX/CSV uniquement, limite configurable de 10 Mo. Le contrôle préalable refuse plusieurs feuilles, trop de lignes/colonnes et une expansion ZIP supérieure à 100 Mo. Aucune évaluation de formule ; les valeurs commençant par `=` sont rejetées. CSV d'erreurs sans données personnelles, cellules protégées contre l'injection de formules. Messages d'échec techniques génériques.

Le staging JSON contient des données personnelles et exige les mêmes protections que students : accès DB/sauvegardes restreint, chiffrement de l'infrastructure et HTTPS en production. Il ne figure jamais dans l'audit ou les props de liste. Audit uploaded/analyzed/committed avec acteur, IDs administratifs et compteurs uniquement. Le nom original du fichier n'est pas audité, car il peut contenir un nom de personne. Les audits d'identité/déclaration existants restent actifs dans la transaction.

Conservation V1 : **30 jours depuis l'upload**, configurable dans `config/student_imports.php`. `php artisan students:purge-imports`, planifié quotidiennement, supprime le fichier et vide raw/normalized/errors/warnings/headers/mapping, anonymise le nom original et conserve IDs/compteurs/métadonnées de résultat. Les imports uploaded/ready expirés deviennent cancelled ; les imports analyzing/committing ne sont pas purgés. Le cron Laravel (`schedule:run`) doit être installé en déploiement. Une suppression fichier réussie suivie d'un échec DB est réessayable à la prochaine purge ; l'historique ne permet pas de réanalyser un import annulé. Les données officiellement importées et les audits administratifs ne sont pas purgés par cette commande. La conservation des sauvegardes doit suivre la politique institutionnelle.

Verrou d'import + verrous DB ciblés partagés avec la saisie manuelle, nouvelle validation à la confirmation, unicités/FK annuelles MySQL et rollback global protègent les retries/conflits. Aucun verrou global de cache n'est tenu par le pipeline d'import. L'établissement ne reçoit ni ID ni détail personnel d'une identité étrangère détectée. Les refus d'import doivent toujours être corrigés par revue autorisée ; aucune baisse de sécurité pour importer une ligne warning.

## Portail étudiant

**Séparation des portails.** Les routes internes (Inertia, Fortify, passkeys) sont déclarées sur `SCOL_DOMAIN`, les routes étudiantes sur `BOURSE_DOMAIN` ; tout autre hôte répond 404. Le hostname n'est pas la seule barrière : `EnsureInternalPortal` refuse tout compte non interne sur `scol`, le provider `internal` refuse la connexion par mot de passe, la réinitialisation et les passkeys aux comptes `student`, et `EnsureStudentPortal` exige le garde `student`, le seul rôle `student`, un email vérifié et le marqueur de session posé à la connexion étudiante. Une session interne n'authentifie jamais l'API étudiante (gardes et cookies distincts).

**Sessions et cookies.** `PortalContext` impose par requête un cookie de session propre au portail : `{PORTAL_COOKIE_PREFIX}-scol-session` et `{PORTAL_COOKIE_PREFIX}-bourse-session`, host-only (`SESSION_DOMAIN` ignoré), HttpOnly, SameSite=Lax. `Secure` suit `SESSION_SECURE_COOKIE`, à mettre à `true` en production HTTPS. La session est régénérée à la connexion et invalidée (avec nouveau jeton CSRF) à la déconnexion. Les requêtes d'écriture passent par le CSRF Laravel et un contrôle d'`Origin` égal à l'hôte courant (port compris) ; un en-tête `Authorization` est refusé sur `bourse` et Sanctum ne lit aucun jeton Bearer (aucun jeton API émis). Sanctum n'est utilisé qu'en mode session ; `stateful` est vide car le groupe `web` démarre déjà la session, aucun domaine ni port n'y est donc configuré. Réponses `bourse` en `Cache-Control: private, no-store`. Derrière un proxy TLS, configurer les proxies de confiance pour que schéma et hôte soient corrects.

**OTP email.** Code à 6 chiffres (`random_int`), stocké en bcrypt d'un HMAC, jamais journalisé ni placé dans une URL (POST JSON uniquement), valable 10 minutes, usage unique, 5 essais par défi, lié à la session du navigateur demandeur. Une nouvelle demande remplace le défi précédent. Limites : 20/min par IP et, par adresse, 3/min, 10/h et 20/jour pour les demandes ; 20/min par IP et 5/min par défi pour la vérification. Réponse identique que l'adresse ait un compte ou non. L'envoi est synchrone (aucun code sérialisé dans la file) et refusé (503) si le transport est `log`, `failover` ou inconnu. `students:purge-otp` (horaire) supprime les défis expirés ou consommés : le scheduler (`schedule:run`) doit tourner en production.

**Google OAuth (optionnel).** Activé seulement si `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URI` sont renseignés ; sinon le bouton est masqué, la redirection répond 503 et l'OTP reste disponible. Socialite en mode stateful (paramètre `state` en session). Exige `email_verified === true` et un sujet Google numérique. L'association se fait par `(provider, provider_user_id)`, jamais par simple égalité d'email : si un compte existe déjà pour cet email, Google n'y est associé que depuis une session étudiante déjà ouverte sur ce compte (via OTP). Aucun jeton Google stocké, le nom Google n'est pas repris, aucun rôle autre que `student` n'est attribué.

**Rattachement et données.** `/api/student/me` lit uniquement `user.student_id` du compte authentifié, ignore tout paramètre client et ne renvoie ni ID technique, ni CIN, ni `declared_by`. Échec de rattachement : même réponse générique (identité absente, ambiguë, inactive, déjà liée ou email différent) ; identifiant et date de naissance soumis ne sont pas persistés. Rate limit du rattachement : 10/min par IP, 3/min et 20/jour par compte. Le journal `student_portal` contient acteur, événement et IDs administratifs (`student_id`, `request_id`) uniquement : ni code, ni email, ni naissance, ni identifiant soumis, ni CIN, ni jeton.

## Demandes de correction

- Aucune route étudiante n'écrit dans `students` ou `student_records`. La fiche n'est modifiée qu'à l'approbation par le personnel, via `SaveStudentIdentity::persist` : même autorisation, mêmes verrous d'identité et mêmes contrôles de doublons (matricule, CIN, numéro BAC, nom + prénoms + naissance) et de cohérence (délivrance CIN ≥ naissance) que la saisie manuelle.
- `student_id` vient toujours du compte authentifié ; un identifiant de demande d'un autre étudiant répond 404 (lookup restreint au demandeur). Liste blanche de champs (enum) et règles de validation partagées avec la saisie administrative.
- Cohérence optimiste : empreinte HMAC de la valeur observée ; si la fiche a changé avant la revue, l'approbation est refusée et le personnel est invité à réexaminer. Aucune valeur actuelle n'est dupliquée en clair ; la valeur proposée est chiffrée au repos.
- Approbation : verrou applicatif court partagé avec la saisie manuelle, puis une transaction unique (verrou de la demande, contrôle du statut, verrou de la fiche, Policy revérifiée, empreinte, validation, écriture du seul champ, statut, relecteur, audit). Annulation étudiante et décisions concurrentes se sérialisent sur le verrou de la demande.
- Exposition : l'API étudiante ne renvoie ni relecteur, ni empreinte, ni IDs internes autres que l'ID de demande, et masque le numéro CIN proposé (3 derniers caractères). Les données CIN actuelles ne sont jamais renvoyées au portail étudiant : l'étudiant propose une nouvelle valeur sans voir la valeur actuelle ; un refus « valeur identique » ne concerne que sa propre fiche. La liste interne n'affiche que nom, prénoms, matricule, champ, date, statut et établissement ; la page de revue montre la valeur actuelle et la valeur proposée du seul champ concerné.
- Le motif de refus est visible par l'étudiant (V1 : un seul champ, l'interface le signale) : il ne doit contenir aucune information interne.
- **`Student.email` ≠ `User.email`** : corriger l'email de contact officiel ne modifie jamais l'email de connexion vérifié du compte. Une éventuelle synchronisation sera un workflow distinct avec vérification du nouvel email.
- Audit `student_corrections` : événements `correction_requested`, `correction_cancelled`, `correction_approved`, `correction_rejected` avec acteur, `field`, `student_id`, `correction_id` uniquement ; l'audit `students` existant n'enregistre que le nom du champ modifié. Aucune valeur ancienne ou nouvelle, ni motif, ni message n'est journalisé.
- Limites : 10 demandes/min et 30/jour par compte ; revue limitée à 30/min.
