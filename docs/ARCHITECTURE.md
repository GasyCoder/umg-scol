# Architecture technique

## Style

Un seul backend Laravel 13 / PHP 8.4+ et une base MySQL, avec deux portails.

## Portails cibles

| Portail  | Domaine local          | Utilisateurs                                                             | Communication                                                        |
| -------- | ---------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| Interne  | `scol.umg.test:8000`   | `central_scholarship_office`, `establishment`, `superadmin`, `developer` | Laravel + Inertia + React + TypeScript                               |
| Étudiant | `bourse.umg.test:8000` | `student` uniquement                                                     | Interface étudiante utilisant une API dédiée du même backend Laravel |

Les futurs endpoints étudiants seront regroupés sous `/api/student/*`. Il ne s'agit pas d'un second backend ni d'un service indépendant. L'interface étudiante et les endpoints ne sont pas encore implémentés.

L'authentification étudiante prévue est email + OTP ou Google OAuth, suivie d'un rattachement sécurisé à une fiche `Student` existante. Vérifier un email ou un compte Google ne suffit pas à prouver l'appartenance à cette fiche. La fréquence du contrôle OTP et le mécanisme de rattachement restent à préciser avant leur implémentation.

La création du compte donne accès au portail ; elle ne constitue jamais une inscription universitaire. L'établissement déclare un étudiant déjà régulièrement inscrit hors plateforme. L'étudiant consulte ses données et soumet une demande de correction, sans modifier directement les informations administratives.

```text
Portail interne -> Routes web Laravel -> Inertia -> React/TypeScript/shadcn
Portail étudiant -> /api/student/* Laravel (prévu)
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
- À terme, résoudre `scol.umg.test` et `bourse.umg.test` vers `127.0.0.1` via le fichier hosts. Ces domaines décrivent l'architecture cible ; le routage par domaine n'est pas encore activé.
