<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>Series: {{ $code }} ({{ $bucket }})</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <style>
    body{font-family:system-ui;margin:16px}
    #wrap{max-width:1000px;margin:auto}
    #series{width:100%;height:360px}
  </style>
</head>
<body>
<div id="wrap">
  <h2>Meter {{ $code }} — Series ({{ $bucket }})</h2>
  <div id="series"></div>
</div>

<script>
// きれいなY軸（分位+ナイススケール）
function computeScale(values, maxTicks = 6, qLo = 0.02, qHi = 0.98) {
  const ys = values.filter(Number.isFinite).slice().sort((a,b)=>a-b);
  if (!ys.length) return { min: 0, max: 1, step: 1 };
  const q = p => ys[Math.round(Math.min(ys.length-1, Math.max(0, p*(ys.length-1))))];
  let lo = q(qLo), hi = q(qHi);
  let range = hi - lo;
  if (!isFinite(range) || range === 0) { lo -= 0.5; hi += 0.5; range = hi - lo; }
  lo -= range * 0.10; hi += range * 0.10; // 10% 余白

  const nice = niceScale(lo, hi, maxTicks);
  return { min: nice.niceMin, max: nice.niceMax, step: nice.tickSpacing };
}
function niceScale(min, max, maxTicks) {
  const range = niceNum(max - min, false);
  const tick = niceNum(range / (maxTicks - 1), true);
  const niceMin = Math.floor(min / tick) * tick;
  const niceMax = Math.ceil (max / tick) * tick;
  return { niceMin, niceMax, tickSpacing: tick };
}
function niceNum(range, round) {
  const exp = Math.floor(Math.log10(Math.max(range, 1e-9)));
  const frac = range / Math.pow(10, exp);
  let nf;
  if (round) nf = frac < 1.5 ? 1 : frac < 3 ? 2 : frac < 7 ? 5 : 10;
  else       nf = frac <= 1 ? 1 : frac <= 2 ? 2 : frac <= 5 ? 5 : 10;
  return nf * Math.pow(10, exp);
}

const code   = @json($code);
const bucket = @json($bucket);

fetch(`/api/v1/meters/${encodeURIComponent(code)}/series?bucket=${encodeURIComponent(bucket)}`)
  .then(r => r.json())
  .then(data => {
    const decimals = bucket === '30m' ? 2 : 0;
    const pts = (data.series?.instant ?? [])
      .map(p => [Date.parse(p[0]), Number(p[1])])
      .filter(p => Number.isFinite(p[0]) && Number.isFinite(p[1]));
    if (!pts.length) throw new Error('no data');

    const ys = pts.map(p => p[1]);
    const { min:yMin, max:yMax, step } = computeScale(ys, 6);

    Highcharts.setOptions({ time: { useUTC: false } }); // ローカル時間（JST表示）
    Highcharts.chart('series', {
      chart: { type: 'line', animation: false },
      title: { text: null },
      credits: { enabled: false },
      xAxis: { type: 'datetime' },
      yAxis: {
        title: { text: null },
        min: yMin, max: yMax,
        tickInterval: step,
        labels: { formatter() { return this.value.toFixed(decimals); } }
      },
      tooltip: {
        xDateFormat: '%Y-%m-%d %H:%M',
        pointFormat: `<b>{point.y:.${decimals}f}</b>`
      },
      legend: { enabled: false },
      series: [{
        name: `${data.meter_name ?? code} (${bucket})`,
        data: pts,
        marker: { enabled: false },
        turboThreshold: 0
      }]
    });
  })
  .catch(err => alert('Load error: ' + err.message));
</script>
</body>
</html>
