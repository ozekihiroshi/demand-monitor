<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield("title","管理")</title>
  @vite(["resources/js/app.js"])
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
  <script>
Highcharts.setOptions({
  chart: {
    backgroundColor: 'transparent',
    style: { fontFamily: '"Inter","Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",system-ui' }
  },
  colors: ['#0ea5e9','#10b981','#ef4444','#f59e0b','#8b5cf6','#14b8a6'],
  title: { style: { fontWeight: 600, color: '#111827' } },
  xAxis: {
    lineColor: '#e5e7eb', tickColor: '#e5e7eb',
    labels: { style: { color: '#4b5563' } }
  },
  yAxis: {
    gridLineColor: '#e5e7eb',
    labels: { style: { color: '#4b5563' } }
  },
  legend: { itemStyle: { color: '#374151', fontWeight: 500 } },
  tooltip: {
    borderWidth: 0,
    backgroundColor: 'rgba(17,24,39,.92)',
    style: { color: '#fff' }
  },
  plotOptions: {
    series: {
      lineWidth: 2,
      marker: { enabled: false },
      states: { hover: { lineWidthPlus: 0 } },
      animation: { duration: 300 }
    },
    areaspline: { fillOpacity: 0.18 }
  },
  accessibility: { enabled: false }
});
</script>

</head>
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
