<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield("title","管理")</title>
  <!-- 必要なら Vite を後で復活させる: @vite(["resources/css/app.css","resources/js/app.js"]) -->
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f5f7fb}
    .wrap{max-width:1100px;margin:24px auto;padding:0 12px}
    .topbar{background:#111827;color:#fff;padding:10px 12px;border-radius:10px}
    .topbar a{color:#fff;text-decoration:none;margin-right:14px}
    .card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:16px;margin-top:16px}
  </style>
  @stack("head")
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <script src="https://code.highcharts.com/modules/accessibility.js"></script>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <a href="{{ route("admin.meters.index") }}">メーター一覧</a>
    </div>
    <main class="card">
      @yield("content")
    </main>
  </div>
  @stack("scripts")
</body>
</html>
