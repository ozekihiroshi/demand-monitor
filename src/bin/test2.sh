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

  # Feature(API) だけ素早く確認
docker compose exec -w /var/www/html app php -d memory_limit=512M ./phpunit.phar \
  --bootstrap tests/bootstrap.php \
  tests/Feature/Api/GraphApiContractTest.php \
  --colors=always --stop-on-failure

# Policy 境界（モデル名を自環境に合わせてから）
docker compose exec -w /var/www/html app php -d memory_limit=512M ./phpunit.phar \
  --bootstrap tests/bootstrap.php \
  tests/Unit/Policy/FacilityScopePolicyTest.php \
  --colors=always --stop-on-failure

# まとめ（phpunit → smoke）
docker compose exec -w /var/www/html app bash -lc 'bin/test.sh'