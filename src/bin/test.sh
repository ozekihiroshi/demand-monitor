#!/usr/bin/env bash
set -euo pipefail
PHPUNIT=""
if [ -x ./vendor/bin/phpunit ]; then
  PHPUNIT="./vendor/bin/phpunit"
elif [ -f ./phpunit.phar ]; then
  PHPUNIT="php -d memory_limit=512M ./phpunit.phar"
else
  curl -fsSL -o phpunit.phar https://phar.phpunit.de/phpunit-10.phar
  chmod +x phpunit.phar
  PHPUNIT="php -d memory_limit=512M ./phpunit.phar"
fi
$PHPUNIT --bootstrap tests/bootstrap.php --colors=always "$@"
