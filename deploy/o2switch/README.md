# Déploiement sur o2switch (hébergement mutualisé)

Pas de Supervisor ni de processus permanent sur un mutualisé : les traitements en arrière-plan
démarrent **tout seuls**.

- **Worker à la demande** : dès qu'une tâche est mise en file (email OTP, gros import) et qu'aucun
  worker n'est actif, l'application lance un `queue:work` en arrière-plan. Il vide sa file, puis
  s'arrête (`App\Support\Queue\QueueWorkers`).
- **Filet de sécurité** : la tâche cron `schedule:run`, installée par le script, relance chaque
  minute un worker pour toute tâche en attente et exécute les purges planifiées.
- Deux files : `default` (emails) et `imports` (analyse et confirmation des imports). Un import
  ne retarde jamais un code OTP.

## Première installation

1. cPanel : créer la base MySQL et son utilisateur, choisir **PHP 8.4** (Sélecteur de version PHP)
   et faire pointer le domaine ou sous-domaine vers le dossier `public/` de l'application.
2. SSH : cloner le dépôt, puis `cp .env.example .env` et renseigner le `.env`
   (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, base de données, mail,
   `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=660`). Lancer ensuite
   `php artisan key:generate`.
3. `bash deploy/o2switch/deploy.sh`

## À chaque mise à jour

```bash
bash deploy/o2switch/deploy.sh
```

Le script fait tout, et peut être relancé sans risque :

- maintenance ;
- `git pull` ;
- dépendances Composer ;
- construction de l'interface si `npm` est disponible ;
- migrations, rôles et référentiel UMG ;
- caches ;
- installation de la tâche cron si elle manque ;
- `queue:restart`, pour que les workers reprennent le nouveau code.

Variables possibles :

- `PHP_BIN=/chemin/php` : PHP 8.4 en ligne de commande, si `php` n'est pas le bon. Le script
  l'écrit dans le `.env` (`PHP_CLI_BINARY`) pour que le site lance les workers avec lui.
- `SKIP_BUILD=1` : interface construite en local et envoyée dans `public/build`.

Journaux des workers : `storage/logs/worker-default.log` et `storage/logs/worker-imports.log`.

## Déploiement automatique (CI/CD)

Chaque push sur `master` lance `.github/workflows/ci-cd.yml` :

1. **Tests** : formatage et lint, TypeScript, Pint, PHPStan, Pest (MySQL 8).
2. **Déploiement**, seulement si les tests passent :
    - construction dans GitHub : `composer install --no-dev`, `npm run build` (le serveur ne lance ni npm ni Composer) ;
    - autorisation SSH temporaire de la machine GitHub (liste blanche o2switch, via l'API cPanel), retirée à la fin ;
    - maintenance, envoi par `rsync` dans `~/umg-scol` (sans `.env`, `storage/`, `tests/`, voir `rsync-exclude.txt`) ;
    - `post-deploy.sh` : migrations, rôles et référentiel, caches, `queue:restart`, remise en ligne.

L'ajout et le retrait de l'autorisation SSH vérifient aussi le statut renvoyé par l'API cPanel.
Le nettoyage retire les règles entrante et sortante créées pour l'adresse de la machine GitHub ;
un échec de nettoyage fait échouer le workflow et reste visible dans ses journaux.

Secrets du dépôt GitHub (Settings › Secrets and variables › Actions) : `DEPLOY_HOST`, `DEPLOY_USER`,
`DEPLOY_PATH`, `DEPLOY_SSH_KEY` (clé privée dédiée, sa clé publique est dans `~/.ssh/authorized_keys`
du serveur), `DEPLOY_KNOWN_HOSTS`, `CPANEL_HOST` et `CPANEL_TOKEN` (jeton d'API cPanel pour la liste
blanche SSH). Les mots de passe de la base et du SMTP restent uniquement dans le `.env` du serveur.

Les sous-domaines `scol.mahajanga-univ.mg` (`public_html/scolarite`) et `bourse.mahajanga-univ.mg`
(`public_html/bourse-student`) sont des liens vers `~/umg-scol/public` : une seule application sert
les deux portails selon le domaine.

## Chargement JavaScript en HTTP/2

Les préchargements JavaScript sont désactivés dans Laravel (`AppServiceProvider`) et dans Vite
(`build.modulePreload: false`). Sur PowerBoost, les chargements Chromium avec `modulepreload`
ont reproduit des transferts interrompus, `ERR_HTTP2_PROTOCOL_ERROR` et un tableau de bord vide ;
le même écran fonctionne avec les imports natifs, en conservant HTTP/2.
Les scripts, leurs imports, les styles et les polices restent chargés normalement.

Le build regroupe les modules partagés entre plusieurs entrées dans un fichier commun,
au lieu de multiplier les petits fichiers d'icônes et de composants. Les pages restent chargées
à la demande. Les tests Pest contrôlent le graphe du build : chaque portail initial charge
au maximum cinq fichiers JavaScript, imports compris.

Après une modification du build, vérifier dans un navigateur avec un cache vide : ouverture du
tableau de bord authentifié, navigation Inertia et portail étudiant. Un contrôle HTTP isolé de
chaque fichier ne suffit pas à reproduire ce problème de chargement.
