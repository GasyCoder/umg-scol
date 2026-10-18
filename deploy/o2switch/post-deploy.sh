#!/usr/bin/env bash
# Run on the server by the CI/CD deployment, right after the release is uploaded (see .github/workflows/ci-cd.yml).
# Vendor and public/build come built from the CI: no Composer, no npm here. Re-runnable.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

[ -f .env ] || { echo "Fichier .env absent sur le serveur : créez-le avant de déployer."; exit 1; }

# storage/ is not uploaded: its tree must exist for caches, sessions, logs and imports.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

"$PHP_BIN" artisan package:discover --ansi
"$PHP_BIN" artisan migrate --force
# Idempotent: creates what is missing, never rewrites the permission matrix set on screen.
"$PHP_BIN" artisan db:seed --class=RolesAndPermissionsSeeder --force
"$PHP_BIN" artisan db:seed --class=UmgReferenceSeeder --force
"$PHP_BIN" artisan storage:link 2> /dev/null || true
"$PHP_BIN" artisan optimize
# Running workers finish their job, then stop; the next job starts a worker with the new code.
"$PHP_BIN" artisan queue:restart
"$PHP_BIN" artisan up
echo "Déploiement terminé."
