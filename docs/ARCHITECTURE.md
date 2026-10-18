# Architecture technique

## Style

Un seul backend Laravel 13 / PHP 8.4+ et une base MySQL, avec deux portails.

## Portails cibles

| Portail  | Domaine local          | Utilisateurs                                                             | Communication                                                        |
| -------- | ---------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| Interne  | `scol.umg.test:8000`   | `central_scholarship_office`, `establishment`, `superadmin`, `developer` | Laravel + Inertia + React + TypeScript                               |
| Étudiant | `bourse.umg.test:8000` | `student` uniquement                                                     | Interface étudiante utilisant une API dédiée du même backend Laravel |

Les endpoints étudiants sont regroupés sous `/api/student/*` (`routes/student-portal.php`). Il ne s'agit pas d'un second backend ni d'un service indépendant. L'interface étudiante est une SPA React dédiée (`resources/js/bourse/`, vue `bourse.blade.php`) servie uniquement sur le domaine `bourse`.

- Routage : `Route::domain()` sur `SCOL_DOMAIN` et `BOURSE_DOMAIN` (`config/portals.php`). Tout autre hôte répond 404 (`PortalContext`).
- Session : Sanctum en mode session/cookie, garde `student` distinct du garde `web`. Chaque portail a son propre cookie de session host-only, HttpOnly et SameSite=Lax. Aucun jeton Bearer n'est accepté sur `bourse`.
- Le garde `web` utilise le provider `internal` : un compte `student` ne peut ni se connecter par Fortify ni réinitialiser un mot de passe. Les comptes internes ou à rôles mixtes sont refusés sur les endpoints étudiants.

L'authentification étudiante se fait par email + OTP (code à 6 chiffres haché, 10 minutes, 5 tentatives, lié à la session, jamais envoyé par le mailer `log`) ou par Google OAuth (email Google vérifié). Elle crée un compte numérique vérifié, qui ne donne accès à aucune fiche. L'accès à la fiche `Student` exige ensuite un rattachement :

- automatique dès que la date de naissance et un renseignement de la fiche (CIN, téléphone, email, ou nom + nom de la mère) désignent une seule fiche active non rattachée ; pas de revue manuelle (ADR-073) ;
- sinon, réponse générique, sans demande au personnel ; si l'email officiel de la fiche diffère de celui du compte, une alerte lui est envoyée, et le personnel peut détacher un compte depuis la fiche étudiant.

Une fiche ne peut être rattachée qu'à un seul compte (contrainte unique en SQL). Les réponses du portail ne renvoient jamais l'identifiant interne ni la CIN.

Une fois rattaché, l'étudiant peut signaler une erreur (`GET/POST /api/student/corrections`, `POST /api/student/corrections/{id}/cancel`) ; le personnel traite les demandes dans `/corrections` sur `scol`. Il peut aussi confirmer que ses informations affichées sont correctes (`GET/POST /api/student/confirmation`, l'état figure aussi dans `/api/student/me`). Voir `WORKFLOWS.md` et `SECURITY.md`.

La création du compte donne accès au portail ; elle ne constitue jamais une inscription universitaire. L'établissement déclare un étudiant déjà régulièrement inscrit hors plateforme. L'étudiant consulte ses données et soumet une demande de correction, sans modifier directement les informations administratives.

```text
Portail interne -> Routes web Laravel -> Inertia -> React/TypeScript/shadcn
Portail étudiant -> /api/student/* Laravel
                         \-> Controllers -> Requests/Policies -> Actions/Services -> Eloquent -> MySQL
```

## Backend

`app/Actions`, `app/Enums`, `app/Http/Controllers`, `app/Http/Requests`, `app/Imports`, `app/Jobs`, `app/Models`, `app/Policies`, `app/Services`.

Pilotage : `app/Support/Reporting` (`ReportScope` pour le périmètre et l'année, `InstitutionalMetrics` pour les agrégats SQL partagés, `InstitutionalDashboard` et `InstitutionalReports` pour la composition), `app/Support/Audit/AuditPresenter` (sortie unique et assainie des journaux), `app/Support/CsvExport`. Contrôleurs minces sous `App\Http\Controllers\Reporting`. Les agrégats sont calculés en SQL (`COUNT`, `SUM(CASE…)`, `GROUP BY`) ; seul l'état des confirmations étudiantes exige de recalculer l'empreinte HMAC, par lots de 500 étudiants ayant déjà confirmé. Pas de cache : le tableau de bord exécute un nombre borné de requêtes (environ 30 pour un superadmin, indépendant du volume, vérifié par test) ; un cache court par rôle, établissement et année serait à envisager seulement si les mesures sur le volume réel le justifient.

## Frontend

`resources/js/components`, `components/ui`, `layouts`, `pages`, `hooks`, `lib`, `types`.

Thème : tokens shadcn/ui définis dans `resources/css/app.css` (palette institutionnelle bleu roi / marine / or, tokens de statut, modes clair et sombre), polices Public Sans (texte) et Plus Jakarta Sans (titres) servies par Bunny Fonts via `laravel-vite-plugin`. Composants et règles de page : `docs/UI_DESIGN.md` (ADR-062). Le dossier `template-model/` (DashWind) reste une référence locale non versionnée.

## Règles

- routes nommées cohérentes ;
- sécurité backend obligatoire ;
- pagination serveur ;
- eager loading maîtrisé ;
- indexes DB ;
- jobs pour imports/exports longs ;
- API étudiante dédiée au sein du monolithe, sans backend séparé ;
- autorisation serveur par rôle, établissement et fiche étudiante, indépendamment du domaine ;
- sessions des deux portails isolées ; ne pas partager un cookie sur `.umg.test` ;
- choix du mécanisme d'authentification API, configuration CSRF/CORS et limitation des tentatives à définir avant l'ouverture du portail étudiant.

## Socle local reproductible

- PHP 8.4 minimum ; Node 22 compatible avec les dépendances, version fixée dans `.nvmrc` (minimum 22.18.0 pour Vite Plus sur la branche 22).
- `.env.example` documente MySQL ; créer la base et l'utilisateur dédiés puis renseigner les secrets dans `.env` avant `composer setup` ou `php artisan migrate`.
- Sessions, cache et queue utilisent leurs tables MySQL techniques. Ne pas exécuter de migrations destructives pour changer de moteur.
- Le fichier local `database/database.sqlite` est résiduel et conservé. Les tests fonctionnels utilisent volontairement SQLite en mémoire ; ils ne constituent pas une validation complète des particularités MySQL.
- La CI utilise PHP 8.4 et un service MySQL 8.0 pour le setup. Les identifiants CI sont réservés à ce service éphémère.
- Résoudre `scol.umg.test` et `bourse.umg.test` vers `127.0.0.1` via le fichier hosts : le routage par domaine est actif et tout autre hôte (dont `127.0.0.1`) répond 404. Renseigner `APP_URL`, `SCOL_DOMAIN` et `BOURSE_DOMAIN` dans `.env`.
- L'OTP étudiant exige un vrai transport email (SMTP local type Mailpit sur le port 1025 en développement) ; le mailer `log` est refusé pour ne pas écrire de code secret dans les journaux. Google OAuth reste désactivé tant que `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URI` ne sont pas tous renseignés ; l'OTP fonctionne seul.
- `students:purge-otp` supprime les défis OTP expirés toutes les heures : le scheduler doit être actif.
- Traitements en arrière-plan (file `database`), en développement comme en production :
    - file `default` : emails OTP et tâches courtes ; file `imports` (`STUDENT_IMPORT_QUEUE`) : analyse et confirmation des imports de plus de 1 000 lignes (jobs de 600 s au plus, une seule tentative, `DB_QUEUE_RETRY_AFTER=660` à garder au-dessus). Un import long ne retarde donc jamais un code OTP.
    - Développement : `composer run dev` (ou `php artisan dev`) lance le serveur, Vite, les logs, un `queue:listen` par file (le code est rechargé à chaque job) et `schedule:work` (`AppServiceProvider::configureDevProcesses`).
    - Automatique partout (`App\Support\Queue\QueueWorkers`) : un job mis en file sans worker vivant de sa file (signe de vie à chaque boucle) lance un `queue:work --stop-when-empty` détaché, qui s'arrête une fois la file vide ; le planificateur relance chaque minute un worker pour toute file en attente. `QUEUE_AUTO_WORKER=false` le désactive ; `PHP_CLI_BINARY` donne le PHP en ligne de commande au site.
    - Mutualisé o2switch : `bash deploy/o2switch/deploy.sh` (installe aussi la tâche cron `schedule:run`) ; voir `deploy/o2switch/README.md`.
    - Serveur dédié ou VPS : Supervisor avec `deploy/supervisor/umg-scol.conf` (2 workers `default`, 1 worker `imports`, le planificateur ; ou une entrée cron `schedule:run`). Après chaque déploiement : `php artisan queue:restart`, sinon les workers gardent l'ancien code en mémoire.
        - Si rien ne démarre (processus interdits et cron absent), la page le signale après 90 s.
- URL front générées par Wayfinder : relatives (`/login`, `/students`…), car les routes `scol` ne sont pas liées à leur domaine pendant `wayfinder:generate` (`PortalRouteDomain`). Sinon Wayfinder écrirait `//scol.umg.test/login` sans le port et enverrait formulaires et liens vers un autre origin. Le routage par hôte reste inchangé à l'exécution. Générer les assets (`npm run build`, qui lance `wayfinder:generate`) **avant** tout `php artisan route:cache` : avec des routes en cache, le domaine réapparaîtrait dans les URL générées.
- SMTP local pour l'OTP : Mailpit (par exemple `docker run -d --name umg-mailpit -p 127.0.0.1:1025:1025 -p 127.0.0.1:8025:8025 axllent/mailpit`), `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` ; boîte de réception sur `http://127.0.0.1:8025`.

## Chargement des pages

Les placeholders utilisent le package officiel `@skeletonlabs/skeleton` **5.0.1** (Skeleton Labs), compatible avec Tailwind CSS v4. Le composant React partagé `components/ui/skeleton.tsx` applique sa classe `placeholder` et l’animation Tailwind préconisée. Seule la feuille `src/utilities/placeholders.css` du package est importée, par chemin relatif depuis `app.css`, pour conserver les styles globaux et les composants shadcn/ui UMG. Ce chemin interne justifie la version exacte : le revérifier lors d’une mise à jour. Les tokens de couleur et d’arrondi sont adaptés localement à `[data-slot='skeleton']`, y compris en mode sombre ; `min-h-0` permet aux petites lignes de respecter la hauteur de la page. Aucun package React supplémentaire n’est nécessaire pour ces placeholders CSS. Voir la [documentation officielle](https://www.skeleton.dev/docs/react/tailwind-components/placeholders).

Le portail interne affiche des squelettes de navigation dans le contenu, en conservant le menu et l’en-tête. `PageLoadingProvider` écoute une seule fois les visites Inertia ; après 180 ms, `pageSkeletonFor` choisit la structure de destination : cartes ou tableau des étudiants, fiches, formulaires, import avec étapes, réception, statistiques, paramètres ou authentification. Aucun délai minimal ne prolonge une réponse rapide. Les formulaires restent montés et masqués pendant le chargement : leurs valeurs sont conservées en cas d’annulation ou d’échec.

Les mutations, préchargements, téléchargements et rechargements partiels/asynchrones (notamment les notifications) ne déclenchent pas de squelette global. Les événements de fin sont associés à leur visite pour qu’une ancienne requête ne ferme pas le chargement d’une nouvelle. L’historique et les erreurs rétablissent le contenu. Les placeholders sont décoratifs, non interactifs, annoncés par un seul statut accessible ; l’animation respecte la réduction des mouvements.

Le portail étudiant utilise un squelette de carte de connexion pendant la lecture de session, puis un squelette de fiche pendant la récupération du profil lié ; une erreur propose de réessayer. Les chargements initiaux Inertia déjà rendus par le serveur ne reçoivent aucun délai artificiel. Vérifications de classification et exclusions : `npm run test:ui`.
