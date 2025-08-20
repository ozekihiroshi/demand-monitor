<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>日次オーバーレイ: {{ $code }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <script src="https://code.highcharts.com/modules/boost.js"></script>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;margin:16px}
    #wrap{max-width:1200px;margin:auto}
    #chart{width:100%;height:560px}
    .toolbar{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn{padding:6px 10px;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#333;cursor:pointer}
    .btn.active{background:#f0f3f8}
    .meta{color:#666}
  </style>
</head>
<body>
<div id="wrap">
  @if (!empty($meter) && $meter->relationLoaded('facility') && $meter->facility && $meter->facility->relationLoaded('company'))
  @include('partials.breadcrumbs', ['crumbs' => [
    ['label'=>$meter->facility->company->name, 'url'=>route('company.dashboard', $meter->facility->company)],
    ['label'=>$meter->facility->name,          'url'=>route('facility.dashboard', $meter->facility)],
    ['label'=>'メータ '.$meter->code,          'url'=>route('facility.meters.show', [$meter->facility, $meter])],
    ['label'=>'当日リアルタイム'],
  ]])
  @endif

  <h3>{{ $code }} — 日次オーバーレイ</h3>
  <div class="toolbar">
    <span class="btn" id="btn-30m">30分</span>
    <span class="btn" id="btn-1m">1分</span>
    <span class="meta" id="subtitle"></span>
  </div>
  <div id="chart"></div>
</div>

<script>
(() => {
  Highcharts.setOptions({ time: { useUTC: false } });

  const code   = @json($code);
  let   bucket = @json($bucket ?? '30m'); // '30m' | '1m'
  const days   = @json($days ?? 8);
  const goal   = @json($goal ?? null);

  const btn30  = document.getElementById('btn-30m');
  const btn1   = document.getElementById('btn-1m');
  const subEl  = document.getElementById('subtitle');

  const chart = Highcharts.chart('chart', {
    title: { text: 'Loading...' },
    credits: { enabled:false },
    xAxis: { type:'datetime' },
    yAxis: {
      title:{ text:'デマンド (kW)' },
      min:0, tickAmount:6, startOnTick:true, endOnTick:true, plotLines:[]
    },
    legend: { enabled:true },
    tooltip: { shared:false, xDateFormat:'%m/%d %H:%M' },

    // 改善②：大量点向けのブースト設定
    boost: { useGPUTranslations: true, seriesThreshold: 1 },
    plotOptions: {
      series: {
        turboThreshold: 20000,
        boostThreshold: 2000,
        lineWidth: 1.5,
        marker: { enabled:false },
        events: {
          show: function(){ fitYAxisPadding(); },
          hide: function(){ fitYAxisPadding(); }
        }
      }
    },
    series: []
  });

  function setActive() {
    btn30.classList.toggle('active', bucket==='30m');
    btn1.classList.toggle('active',  bucket==='1m');
  }

  async function loadFull() {
    setActive();

    const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/series`, location.origin);
    url.searchParams.set('bucket', bucket);
    url.searchParams.set('days', days);
    if (goal != null) url.searchParams.set('goal', goal);

    const j = await fetchJson(url);

    chart.setTitle({ text: `${j.meter} / ${j.bucket.toUpperCase()} / JST` }, false);
    chart.yAxis[0].update({
      plotLines: (j.goal_kw==null) ? [] : [{
        value:Number(j.goal_kw), color:'red', width:2, zIndex:5,
        label:{ text:`閾値 ${Number(j.goal_kw)} kW`, align:'left', style:{color:'#666'} }
      }]
    }, false);

    // 既存シリーズをクリアして再構築
    while (chart.series.length) chart.series[0].remove(false);
    for (const s of j.series) {
      const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
      chart.addSeries({
        name: s.label, data, color: s.color, visible: !!s.visible
      }, false);
    }

    chart.redraw();
    fitYAxisPadding();

    subEl.textContent = (bucket==='30m')
      ? '台形法＋境界補間で30分平均（半枠終端にプロット）'
      : '各1分の平均kW（分頭にプロット）';
  }

  // 改善③：表示中シリーズ最大値に合わせて上限を少し持ち上げる
  function fitYAxisPadding(pad = 10) {
    const vals = chart.series
      .filter(s => s.visible)
      .flatMap(s => s.yData)
      .filter(v => v != null);
    if (!vals.length) return;
    const max = Math.max(...vals);
    if (!isFinite(max)) return;
    const yMax = Math.ceil((max + pad) / 10) * 10;
    chart.yAxis[0].update({ max: yMax }, false);
    chart.redraw();
  }

  async function fetchJson(url) {
    const res = await fetch(url.toString(), { cache:'no-store' });
    if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
    return res.json();
  }

  btn30.onclick = () => { bucket='30m'; loadFull(); };
  btn1.onclick  = () => { bucket='1m';  loadFull(); };

  loadFull();
})();
</script>
</body>
</html>
