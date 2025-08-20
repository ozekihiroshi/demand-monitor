<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>1分デマンド: {{ $code }}</title>
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

  <h3>{{ $code }} — 1分デマンド</h3>
  <div class="toolbar">
    <span class="btn" id="btn-overlay">日別オーバーレイ</span>
    <span class="btn" id="btn-timeline">7日連続</span>
    <span class="meta" id="subtitle"></span>
  </div>
  <div id="chart"></div>
</div>
<script>
(() => {
  Highcharts.setOptions({ time: { useUTC: false } });

  const code   = @json($code);
  let   view   = @json($view ?? 'overlay'); // 'overlay' | 'timeline'
  const days   = @json($days ?? 7);

  const btnOv  = document.getElementById('btn-overlay');
  const btnTl  = document.getElementById('btn-timeline');
  const subEl  = document.getElementById('subtitle');

  let deltaTimer = null;
  let deltaCursorMs = null; // 末尾分頭のms（差分の since に使う）
  let deltaFail = 0;
  let currentDayLabel = null; // overlay時の当日ラベル

  const chart = Highcharts.chart('chart', {
    title: { text: 'Loading...' },
    credits: { enabled:false },
    xAxis: { type:'datetime' },
    yAxis: {
      title:{ text:'デマンド (kW)' },
      min:0, tickAmount:6, startOnTick:true, endOnTick:true
    },
    legend: { enabled:true },
    tooltip: { shared:false, xDateFormat: '%m/%d %H:%M' },

    // A-2: Boost（大量点向け）
    boost: { useGPUTranslations: true, seriesThreshold: 1 },
    plotOptions: {
      series: {
        turboThreshold: 20000,
        boostThreshold: 2000,
        events: {
          show: function(){ fitYAxisPadding(); },
          hide: function(){ fitYAxisPadding(); }
        }
      }
    },
    series: []
  });

  function setActive() {
    btnOv.classList.toggle('active', view==='overlay');
    btnTl.classList.toggle('active', view==='timeline');
  }

  function clearDeltaTimer() {
    if (deltaTimer) { clearInterval(deltaTimer); deltaTimer = null; }
  }

  async function loadFull() {
    setActive();
    clearDeltaTimer();

    const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/series`, location.origin);
    url.searchParams.set('bucket','1m');
    url.searchParams.set('days',days);
    url.searchParams.set('view',view);

    const j = await fetchJson(url);
    chart.setTitle({ text: `${j.meter} / 1M / ${j.view==='timeline'?'7日連続':'日別オーバーレイ'}` }, false);

    // 既存シリーズをクリア
    while (chart.series.length) chart.series[0].remove(false);

    if (j.view === 'timeline') {
      // 単一系列
      const s = j.series[0];
      const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
      chart.addSeries({
        name: s.label, data, color: s.color, lineWidth: 1.1, marker:{enabled:false}
      }, false);

      // 差分開始（タイムライン）
      if (data.length) {
        deltaCursorMs = data[data.length-1][0];
        startDeltaSynced();
      }
      subEl.textContent = '7日間を連続で表示（末尾だけ差分追記）';
    } else {
      // 日別オーバーレイ
      for (const s of j.series) {
        const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
        chart.addSeries({
          name: s.label, data, color: s.color, visible: !!s.visible, lineWidth: 1, marker:{enabled:false}
        }, false);
      }
      chart.redraw();
      fitYAxisPadding();

      // 差分開始（当日系列のみ置換＋追記）
      currentDayLabel = j.series[j.series.length - 1]?.label || null; // 今日のラベル（JST）
      if (currentDayLabel) {
        const idx = chart.series.findIndex(sr => sr.name === currentDayLabel);
        if (idx >= 0) {
          const s = chart.series[idx];
          if (s.data.length) {
            deltaCursorMs = s.data[s.data.length-1].x;
            startDeltaSynced();
          }
        }
      }
      subEl.textContent = '各日を重ねて傾向を比較（当日だけ差分更新）';
    }

    chart.redraw();
    fitYAxisPadding();
  }

  // A-1: 分頭同期してから差分開始
  function startDeltaSynced() {
    clearDeltaTimer();
    const now = new Date();
    const msToNextMinute = 60000 - (now.getSeconds()*1000 + now.getMilliseconds()) + 3000; // +3s余裕
    setTimeout(() => { startDelta(); }, msToNextMinute);
  }

  function startDelta() {
    clearDeltaTimer();
    deltaTimer = setInterval(safeDelta, 30000); // 30秒おき
  }

  // A-4: 差分失敗時フォールバック
  async function safeDelta() {
    try {
      await doDeltaOnce();
      deltaFail = 0;
    } catch (e) {
      console.warn('delta poll failed:', e);
      if (++deltaFail >= 3) {
        deltaFail = 0;
        await loadFull(); // 一度フルに戻す
      }
    }
  }

  async function doDeltaOnce() {
    if (deltaCursorMs == null) return;

    const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/series`, location.origin);
    url.searchParams.set('bucket','1m');
    url.searchParams.set('days', days);
    url.searchParams.set('view', view);
    url.searchParams.set('since', Math.floor(deltaCursorMs/1000));

    const j = await fetchJson(url);
    if (j.mode !== 'delta') return;

    const sinceMs  = Date.parse(j.since);
    const cursorMs = Date.parse(j.cursor);
    const append   = (j.append ?? []).map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
    if (!append.length) { deltaCursorMs = cursorMs; return; }

    if (j.view === 'timeline') {
      const s = chart.series[0]; if (!s) return;
      const kept = s.data.filter(p => p.x < sinceMs).map(p => [p.x, p.y]);
      s.setData(kept.concat(append), false);
    } else {
      const targetLabel = j.target || currentDayLabel;
      const idx = chart.series.findIndex(sr => sr.name === targetLabel);
      if (idx < 0) return;
      const s = chart.series[idx];
      const kept = s.data.filter(p => p.x < sinceMs).map(p => [p.x, p.y]);
      s.setData(kept.concat(append), false);
    }

    chart.redraw();
    fitYAxisPadding();

    deltaCursorMs = cursorMs;
  }

  // A-3: Y軸オートフィット（見やすい上限）
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
  }

  async function fetchJson(url) {
    const res = await fetch(url.toString(), { cache:'no-store' });
    if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
    return res.json();
  }

  btnOv.onclick = () => { view='overlay'; loadFull(); };
  btnTl.onclick = () => { view='timeline'; loadFull(); };

  loadFull();
})();
</script>
</body>
</html>
