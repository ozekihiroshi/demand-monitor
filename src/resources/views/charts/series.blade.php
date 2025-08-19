<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>Series: {{ $code }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;margin:16px}
    #wrap{max-width:1100px;margin:auto}
    #chart{width:100%;height:560px}
    .toolbar{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn{padding:6px 10px;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#333;cursor:pointer}
    .btn.active{background:#f0f3f8}
    .meta{color:#666}
  </style>
</head>
<body>
<div id="wrap">
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
  let   bucket = @json($bucket ?? '30m');
  const days   = @json($days ?? 8);
  const goal   = @json($goal ?? null);

  const btn30 = document.getElementById('btn-30m');
  const btn1  = document.getElementById('btn-1m');
  const subEl = document.getElementById('subtitle');

  let deltaTimer = null;
  let deltaCursorMs = null; // 1分×1日で使う（分頭のms）

  const chart = Highcharts.chart('chart', {
    title: { text: 'Loading...' },
    credits: { enabled:false },
    xAxis: { type:'datetime' },
    yAxis: {
      title:{ text:'デマンド (kW)' },
      min:0, startOnTick:true, endOnTick:true, tickAmount:6, plotLines:[]
    },
    legend: { enabled:true },
    tooltip: { shared:false, xDateFormat: '%m/%d %H:%M' },
    series: []
  });

  function setActive() {
    btn30.classList.toggle('active', bucket==='30m');
    btn1.classList.toggle('active',  bucket==='1m');
  }

  function clearDeltaTimer() {
    if (deltaTimer) { clearInterval(deltaTimer); deltaTimer = null; }
  }

  async function loadFull() {
    setActive();
    clearDeltaTimer();

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

    while (chart.series.length) chart.series[0].remove(false);

    for (const s of j.series) {
      const data = s.data.map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
      chart.addSeries({
        name: s.label, data, color: s.color,
        visible: !!s.visible, lineWidth: 1.5, marker: { enabled:false }
      }, false);
    }
    chart.redraw();

    subEl.textContent = (bucket==='30m')
      ? '台形法＋境界補間で30分平均（半枠終端にプロット）'
      : '各1分の平均kW（分頭にプロット）';

    // 1分×1日は増分ポーリング
    if (bucket==='1m' && days===1) {
      const s = chart.series[0];
      if (s && s.data.length) {
        deltaCursorMs = s.data[s.data.length - 1].x; // 末尾の分頭
        startDelta();
      }
    }
  }

  function startDelta() {
    clearDeltaTimer();
    deltaTimer = setInterval(async () => {
      try {
        if (bucket!=='1m' || days!==1 || deltaCursorMs===null) return;
        const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/series`, location.origin);
        url.searchParams.set('bucket', '1m');
        url.searchParams.set('days', 1);
        url.searchParams.set('since', Math.floor(deltaCursorMs/1000));
        const j = await fetchJson(url);
        if (j.mode !== 'delta') return;

        const sinceMs  = Date.parse(j.since);
        const cursorMs = Date.parse(j.cursor);
        const append   = (j.append ?? []).map(p => [Date.parse(p[0]), p[1]===null? null : Number(p[1])]);
        if (!append.length) return;

        // 置換開始点（since以降）を一旦削ってから連結
        const s = chart.series[0]; if (!s) return;
        const kept = s.data.filter(p => p.x < sinceMs).map(p => [p.x, p.y]);
        s.setData(kept.concat(append), false);
        chart.redraw();

        deltaCursorMs = cursorMs;
      } catch(e) {
        console.warn('delta poll failed:', e);
      }
    }, 30000); // 30秒おき（任意で調整）
  }

  async function fetchJson(url){
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
