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

- automatique uniquement si l'identifiant (matricule ou numéro d'inscription au bac) correspond exactement, avec la même date de naissance, un candidat unique et actif, et l'email officiel de la fiche identique à l'email vérifié du compte ;
- sinon, une `StudentLinkRequest` est créée et doit être approuvée par le personnel (`/student-links`, permission `student_links.review`). Un établissement ne voit que les demandes dont l'étudiant a une déclaration active sur l'année courante ; la Scolarité Centrale a une revue globale.

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

## Frontend

`resources/js/components`, `components/ui`, `layouts`, `pages`, `hooks`, `lib`, `types`.

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
- URL front générées par Wayfinder : relatives (`/login`, `/students`…), car les routes `scol` ne sont pas liées à leur domaine pendant `wayfinder:generate` (`PortalRouteDomain`). Sinon Wayfinder écrirait `//scol.umg.test/login` sans le port et enverrait formulaires et liens vers un autre origin. Le routage par hôte reste inchangé à l'exécution. Générer les assets (`npm run build`, qui lance `wayfinder:generate`) **avant** tout `php artisan route:cache` : avec des routes en cache, le domaine réapparaîtrait dans les URL générées.
- SMTP local pour l'OTP : Mailpit (par exemple `docker run -d --name umg-mailpit -p 127.0.0.1:1025:1025 -p 127.0.0.1:8025:8025 axllent/mailpit`), `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` ; boîte de réception sur `http://127.0.0.1:8025`.
