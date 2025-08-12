<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $code }} / {{ $bucket }}</title>
<script src="https://code.highcharts.com/highcharts.js"></script>
</head><body>
<h3>{{ $code }} — {{ $bucket }} オーバーレイ</h3>
<div id="chart" style="height:480px"></div>
<script>
(async function(){
  const code   = @json($code);
  const bucket = @json($bucket);
  const url    = `/api/v1/meters/${code}/series?bucket=${bucket}&days=8`;

  const res = await fetch(url);
  const json = await res.json();

  Highcharts.chart('chart', {
    chart: { zoomType:'x' },
    title: { text: `${json.meter} (${bucket})` },
    subtitle: { text: `TZ: ${json.tz}` },
    xAxis: { type:'datetime' },
    yAxis: {
      title: { text: 'デマンド (kW)' },
      min: 0,
      plotLines: [{
        value: json.threshold ?? 800, color: 'red', width: 1,
        label: { text: `閾値: ${json.threshold ?? 800} kW`, align: 'left', style:{ color:'#666' } }
      }]
    },
    tooltip: { shared: true, xDateFormat: '%H:%M' },
    series: json.series.map(s => ({
      name: s.label,
      data: s.data.map(p => [new Date(p[0]).getTime(), p[1]]),
      color: s.color, visible: !!s.visible,
      lineWidth: 1
    }))
  });
})();
</script>
</body></html>