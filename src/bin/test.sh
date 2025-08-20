docker compose exec -w /var/www/html app composer dump-autoload
docker compose exec -w /var/www/html app php artisan optimize:clear
docker compose exec -w /var/www/html app php -d memory_limit=512M ./phpunit.phar \
  --bootstrap tests/bootstrap.php \
  tests/Feature/Admin/MeterUiSmokeTest.php \
  --filter 'operator_cannot_open_create_but_can_open_edit_for_own_group' \
  --colors=always --stop-on-failure
docker compose exec -w /var/www/html app php -d memory_limit=512M ./phpunit.phar \
  --bootstrap tests/bootstrap.php \
  tests/Feature \
  --colors=always

