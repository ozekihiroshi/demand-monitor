<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>1分デマンド: {{ $code }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
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

  const chart = Highcharts.chart('chart', {
    title: { text: 'Loading...' },
    credits: { enabled:false },
    xAxis: { type:'datetime' },
    yAxis: { title:{ text:'デマンド (kW)' }, min:0, tickAmount:6, startOnTick:true, endOnTick:true },
    legend: { enabled:true },
    tooltip: { shared:false, xDateFormat: '%m/%d %H:%M' },
    series: []
  });

  function setActive() {
    btnOv.classList.toggle('active', view==='overlay');
    btnTl.classList.toggle('active', view==='timeline');
  }

  function clearDeltaTimer() { if (deltaTimer) { clearInterval(deltaTimer); deltaTimer = null; } }

  async function loadFull() {
    setActive(); clearDeltaTimer();

    const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/series`, location.origin);
    url.searchParams.set('bucket','1m');
    url.searchParams.set('days',days);
    url.searchParams.set('view',view);

    const j = await fetchJson(url);
    chart.setTitle({ text: `${j.meter} / 1M / ${j.view==='timeline'?'7日連続':'日別オーバーレイ'}` }, false);

    while (chart.series.length) chart.series[0].remove(false);

    if (j.view === 'timeline') {
      // 単一系列
      const s = j.series[0];
      const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
      chart.addSeries({ name: s.label, data, color: s.color, lineWidth: 1.1, marker:{enabled:false} }, false);

      // 差分開始（タイムライン）
      if (data.length) {
        deltaCursorMs = data[data.length-1][0];
        startDelta();
      }
      subEl.textContent = '7日間を連続で表示（末尾だけ差分追記）';
    } else {
      // 日別オーバーレイ
      for (const s of j.series) {
        const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
        chart.addSeries({ name: s.label, data, color: s.color, visible: !!s.visible, lineWidth: 1, marker:{enabled:false} }, false);
      }
      chart.redraw();

      // 差分開始（当日系列のみ置換＋追記）
      const todayLabel = new Date().toISOString().slice(0,10);
      const idx = chart.series.findIndex(sr => sr.name === todayLabel);
      if (idx >= 0) {
        const s = chart.series[idx];
        if (s.data.length) {
          deltaCursorMs = s.data[s.data.length-1].x;
          startDelta();
        }
      }
      subEl.textContent = '各日を重ねて傾向を比較（当日だけ差分更新）';
    }

    chart.redraw();
  }

  function startDelta() {
    clearDeltaTimer();
    deltaTimer = setInterval(async () => {
      try {
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
        if (!append.length) return;

        if (j.view === 'timeline') {
          const s = chart.series[0]; if (!s) return;
          const kept = s.data.filter(p => p.x < sinceMs).map(p => [p.x, p.y]);
          s.setData(kept.concat(append), false);
        } else {
          const todayLabel = j.target; // 'YYYY-MM-DD'
          const idx = chart.series.findIndex(sr => sr.name === todayLabel);
          if (idx < 0) return;
          const s = chart.series[idx];
          const kept = s.data.filter(p => p.x < sinceMs).map(p => [p.x, p.y]);
          s.setData(kept.concat(append), false);
        }
        chart.redraw();
        deltaCursorMs = cursorMs;
      } catch (e) {
        console.warn('delta poll failed:', e);
      }
    }, 30000); // 30秒
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

