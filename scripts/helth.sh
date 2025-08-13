# devサーバ([::1]:5173)への<script>が混ざってないか確認
curl -s https://mobile.ceri.link/login \
 | tr -d '\n' | grep -q '\[::1\]:5173' \
 && { echo "NG: Vite dev が混入しています"; exit 1; } \
 || echo "OK: 本番ビルドのみ"
