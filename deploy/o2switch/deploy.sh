#!/usr/bin/env bash
# Deployment of the UMG platform on an o2switch shared host (cPanel + SSH), without Supervisor.
# Usage, over SSH, from the application directory:   bash deploy/o2switch/deploy.sh
# Re-runnable: each step only does what is missing. Options (environment variables):
#   PHP_BIN=/path/to/php   CLI PHP 8.4 (default: the "php" of the SSH session)
#   COMPOSER_BIN=composer  SKIP_BUILD=1 (assets already built and uploaded in public/build)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_DIR"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
step() { printf '\n==> %s\n' "$1"; }

step "PHP : $PHP_BIN"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' \
  || { echo "PHP 8.4 requis : choisissez-le dans cPanel (Sélecteur de version PHP) ou indiquez PHP_BIN=..."; exit 1; }
[ -f .env ] || { echo "Fichier .env absent : copiez .env.example en .env et renseignez-le (APP_KEY, base de données, mail)."; exit 1; }

# The web server (php-fpm) must know the CLI binary to start the background workers by itself.
if ! grep -q '^PHP_CLI_BINARY=' .env; then
  printf '\nPHP_CLI_BINARY=%s\n' "$PHP_BIN" >> .env
  echo "PHP_CLI_BINARY ajouté au .env"
fi
grep -q '^QUEUE_CONNECTION=database' .env || echo "Attention : QUEUE_CONNECTION=database est attendu dans le .env."

step "Mode maintenance"
"$PHP_BIN" artisan down --retry=30 || true
trap '"$PHP_BIN" artisan up || true' EXIT

if [ -d .git ]; then
  step "Code (git pull)"
  git pull --ff-only
fi

step "Dépendances PHP"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ "${SKIP_BUILD:-0}" != "1" ]; then
  if command -v npm > /dev/null 2>&1; then
    step "Interface (npm run build)"
    npm ci --no-audit --no-fund && npm run build
  elif [ ! -f public/build/manifest.json ]; then
    echo "npm absent et public/build manquant : construisez l'interface en local (npm run build), envoyez public/build, puis relancez avec SKIP_BUILD=1."
    exit 1
  fi
fi

step "Base de données et référentiels"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class=RolesAndPermissionsSeeder --force
"$PHP_BIN" artisan db:seed --class=UmgReferenceSeeder --force

step "Caches et liens"
"$PHP_BIN" artisan storage:link 2> /dev/null || true
"$PHP_BIN" artisan optimize

step "Tâche planifiée (cron, chaque minute)"
# The scheduler runs the purges and restarts a worker for any waiting job (safety net of the automatic workers).
CRON_LINE="* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
if crontab -l 2> /dev/null | grep -Fq "$APP_DIR && $PHP_BIN artisan schedule:run"; then
  echo "Déjà installée."
else
  (crontab -l 2> /dev/null | grep -v "$APP_DIR && .*artisan schedule:run" || true; echo "$CRON_LINE") | crontab -
  echo "Installée : $CRON_LINE"
fi

step "Workers"
# Running workers finish their job, then stop; the next job starts a worker with the new code.
"$PHP_BIN" artisan queue:restart

step "Terminé"
