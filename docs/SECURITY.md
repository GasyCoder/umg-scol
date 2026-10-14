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
