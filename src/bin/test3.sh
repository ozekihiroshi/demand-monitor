#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

php -d memory_limit=512M ./phpunit.phar --bootstrap tests/bootstrap.php --colors=always
echo
echo "== smoke =="
bin/smoke.sh

