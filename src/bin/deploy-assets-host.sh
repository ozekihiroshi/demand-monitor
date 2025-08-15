#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-../docker-compose.yml}"
SERVICE="${SERVICE:-app}"
WORKDIR="${WORKDIR:-/var/www/html}"

echo "==> Host build (in $(pwd))"
# ※ node_modules は消さない方が速い。初回だけ npm ci を実行
# rm -rf node_modules
# npm ci
rm -rf public/build
npm run build
test -f public/build/manifest.json

echo "==> Clear Laravel caches in container"
docker compose -f "$COMPOSE_FILE" exec -T -w "$WORKDIR" "$SERVICE" php artisan optimize:clear

echo "==> Sanity checks"
# bind mount なのでコンテナからも同じ manifest が見えるはず
docker compose -f "$COMPOSE_FILE" exec -T -w "$WORKDIR" "$SERVICE" ls -l public/build/manifest.json || true
