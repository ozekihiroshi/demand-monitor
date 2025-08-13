#!/usr/bin/env bash
set -euo pipefail

docker compose exec -w /var/www/html app bash -lc 'rm -f public/hot'
npm ci && npm run build
docker compose exec -w /var/www/html app php artisan optimize:clear
docker compose restart app

# dev混入チェック
curl -s https://mobile.ceri.link/login | tr -d '\n' | grep -q '\[::1\]:5173' \
  && { echo "NG: dev混入"; exit 1; } || echo "OK: 本番ビルド"
