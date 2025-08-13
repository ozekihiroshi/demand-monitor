# 0) 念のため dev用 hot を消す（サーバ側）
docker compose exec -w /var/www/html app bash -lc 'rm -f public/hot'

# 1) フロントビルド（ホスト側）
npm ci
npm run build

# 2) Laravel キャッシュ掃除 & php-fpm 再起動（サーバ側）
docker compose exec -w /var/www/html app php artisan optimize:clear
docker compose restart app

# 3) アセット配信確認（サーバ側）
APP_JS=$(docker compose exec -T -w /var/www/html app php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true); echo $m["resources/js/app.js"]["file"];')
curl -f "https://mobile.ceri.link/build/${APP_JS}" >/dev/null && echo "OK: asset 配信中"
