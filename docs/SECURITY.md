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

La future inscription étudiante sur `bourse` et `/api/student/*` sera indépendante de cette inscription administrative. OTP, Google OAuth et rattachement à une fiche `Student` ne sont pas implémentés ici. Il ne faudra pas réactiver globalement l'inscription Fortify pour ouvrir ce portail.
