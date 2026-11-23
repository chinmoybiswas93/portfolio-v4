#!/usr/bin/env bash
# Lighthouse CI against a production-mode local server with seeded content (docs/TESTING.md §6).
# Uses its own SQLite database and a temporary .env; the original .env is restored afterwards.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then cp .env .env.lighthouse-backup; fi
restore() {
    if [ -f .env.lighthouse-backup ]; then mv .env.lighthouse-backup .env; else rm -f .env; fi
    php artisan optimize:clear -q || true
}
trap restore EXIT

cp .env.example .env
php artisan key:generate -q
cat >> .env <<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=http://127.0.0.1:8090
DB_CONNECTION=sqlite
DB_DATABASE=$PWD/database/lighthouse.sqlite
QUEUE_CONNECTION=sync
CACHE_STORE=file
SESSION_DRIVER=file
ENV

rm -f database/lighthouse.sqlite && touch database/lighthouse.sqlite
php artisan migrate --force -q
php artisan db:seed --class=DemoSeeder --force -q
php artisan storage:link -q 2>/dev/null || true
php artisan optimize -q

rm -rf .lighthouseci
npx lhci collect
npx lhci upload
npx lhci assert
