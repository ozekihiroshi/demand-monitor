docker compose exec -w /var/www/html app php artisan view:clear

 docker compose exec -w /var/www/html app php artisan test --filter=TimeseriesApiTest