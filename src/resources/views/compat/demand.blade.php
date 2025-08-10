<!doctype html><html lang="ja"><head><meta charset="utf-8">
<title>デマンド管理図</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}</style>
</head><body>
<h1>デマンド管理図（プレースホルダ）</h1>
<p>Meter: {{ optional($meter)->code ?? 'N/A' }} / demand_ip: {{ request('demand_ip') }}</p>
<p>※ 後でPNG/HTMLグラフに差し替えます。</p>
</body></html>

