<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>Demand: {{ $code }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; margin:16px}
    #wrap{max-width:1100px;margin:auto}
    #chart{width:100%;height:520px}
    .banner{margin:8px 0;padding:10px 12px;border-radius:8px;font-weight:600}
    .banner.ok{background:#e8f5e9;color:#1b5e20}
    .banner.ng{background:#ffebee;color:#b71c1c}
    .kpis{display:flex;gap:12px;flex-wrap:wrap;margin:10px 0}
    .kpi{padding:8px 10px;border-radius:8px;background:#f6f7f9}
    .kpi b{font-size:1.05rem}
  </style>
</head>
<body>
<div id="wrap">
  <h3>{{ $code }} — 当日リアルタイム（30分枠：積算＆予測）</h3>
  <div id="alert" class="banner" style="display:none"></div>
  <div class="kpis" id="kpis" style="display:none">
    <div class="kpi">最新観測 <b id="kpi-last-ts">--:--:--</b>（遅延 <b id="kpi-lag">--s</b>）</div>
    <div class="kpi">枠終端まで <b id="kpi-remaining">--:--</b></div>
    <div class="kpi">最終予測 <b id="kpi-predict">-- kW</b></div>
    <div class="kpi">閾値 <b id="kpi-th">-- kW</b> / 余裕 <b id="kpi-margin">-- kW</b></div>
  </div>
  <div id="chart"></div>
</div>

<script>
(async function draw(){
  Highcharts.setOptions({ time: { useUTC: false } });

  const code = @json($code);
  const thresholdFromBlade = @json($threshold ?? null);

  const api = new URL(`/api/v1/meters/${encodeURIComponent(code)}/demand`, location.origin);
  if (thresholdFromBlade !== null) api.searchParams.set('threshold', thresholdFromBlade);

  const res = await fetch(api.toString(), { cache: 'no-store' });
  const j   = await res.json();

  // サーバ時刻（レスポンスに now があれば優先）
  const nowServerMs = j.now ? Date.parse(j.now) : Date.now();

  // 30分枠はAPIの window を優先
  let slotStart = j.window?.start ? Date.parse(j.window.start) : floorToHalfHour(nowServerMs);
  let slotEnd   = j.window?.end   ? Date.parse(j.window.end)   : (slotStart + 30*60*1000);

  const threshold = (j.threshold ?? thresholdFromBlade ?? null);

  // 瞬時（枠内）: [ms,kW]
  const inst = (j.series?.instant ?? [])
    .map(p => [Date.parse(p[0]), Number(p[1])])
    .filter(([t,y]) => Number.isFinite(t) && Number.isFinite(y))
    .filter(([t]) => t >= slotStart && t <= slotEnd)
    .sort((a,b)=>a[0]-b[0]);

  if (!inst.length) return banner('データがありません');

  // ===== 連続積分：ゼロ開始・右肩上がり（30分平均kW換算）=====
  const actual = [[slotStart, 0]];
  let prevT = slotStart;
  let prevKW = inst[0][1];
  let cumKWh = 0;

  for (const [t, kw] of inst) {
    const dtSec = Math.max(0, (t - prevT)/1000);
    cumKWh += prevKW * dtSec / 3600;     // kWh
    actual.push([t, +(2*cumKWh).toFixed(1)]);
    prevT = t; prevKW = kw;
  }

  const lastPoint = actual[actual.length - 1];
  const lastTs = lastPoint[0];
  const demandNow = lastPoint[1];

  // ===== 実績→予測を“赤で連続” =====
  const remainSec = Math.max(0, Math.round((slotEnd - lastTs)/1000));
  const demandEnd = demandNow + (prevKW * remainSec)/1800; // 2*(kW*sec/3600)
  const predict = remainSec > 0
    ? [[ lastTs, demandNow ], [ slotEnd, +demandEnd.toFixed(1) ]]
    : [];
  const predict_last = predict.length ? predict[predict.length-1] : lastPoint;

  // ===== バナー =====
  if (threshold !== null && predict_last) {
    const exceed = predict_last[1] >= Number(threshold);
    banner(
      exceed
        ? `警報: 最終予測 ${predict_last[1].toFixed(1)} kW が閾値 ${threshold} kW を超過`
        : `正常: 予測は閾値 ${threshold} kW 未満`,
      exceed ? 'ng' : 'ok'
    );
  } else {
    banner('データ取得', null, true);
  }

  // ===== KPI 表示（復活）=====
  const lagSec = Math.max(0, Math.round((nowServerMs - lastTs)/1000));
  const remainMs = Math.max(0, slotEnd - nowServerMs);
  const remainMm = Math.floor(remainMs / 60000);
  const remainSs = Math.floor((remainMs % 60000) / 1000);
  const margin = (threshold != null && predict_last)
    ? +(Number(threshold) - predict_last[1]).toFixed(1)
    : null;

  const kpisEl = document.getElementById('kpis');
  kpisEl.style.display = 'flex';
  qs('#kpi-last-ts').textContent = timefmt(lastTs);
  qs('#kpi-lag').textContent = `${lagSec}s`;
  qs('#kpi-remaining').textContent = `${String(remainMm).padStart(2,'0')}:${String(remainSs).padStart(2,'0')}`;
  qs('#kpi-predict').textContent = predict_last ? `${predict_last[1].toFixed(1)} kW` : '-- kW';
  qs('#kpi-th').textContent = threshold != null ? `${Number(threshold)} kW` : '-- kW';
  qs('#kpi-margin').textContent = margin != null ? `${margin >= 0 ? '+' : ''}${margin} kW` : '-- kW';

  // ===== 描画 =====
  Highcharts.chart('chart', {
    title:{ text:`${j.meter ?? code} / ${fmt(slotStart)} ～ ${fmt(slotEnd)}` },
    credits:{ enabled:false },
    xAxis:{ type:'datetime', min: slotStart, max: slotEnd, tickInterval: 5*60*1000 },
    yAxis:{
      title:{ text:'デマンド (kW)' },
      min:0, startOnTick:true, endOnTick:true, tickAmount:6,
      plotLines: threshold===null ? [] : [{
        value:Number(threshold), color:'red', width:2, zIndex:5,
        label:{ text:`閾値 ${Number(threshold)} kW`, align:'left', style:{color:'#666'} }
      }]
    },
    tooltip:{ shared:true, xDateFormat:'%H:%M' },
    legend:{ enabled:true },
    series:[
      { // 瞬間デマンド（デフォルト表示・最背面）
        name:'瞬間デマンド',
        type:'line',
        color:'#999',
        data: inst,
        lineWidth: 1,
        marker:{ enabled:false },
        visible: true,
        zIndex: 1
      },
      { // 現時点実績（積算）
        name:'現時点実績（積算）',
        type:'line',
        color:'#2E8B57',
        data: actual,
        lineWidth: 2,
        marker:{ enabled:false },
        zIndex: 3
      },
      { // 予測（実績末端から連続）
        name:'デマンド予測（終端まで）',
        type:'line',
        color:'#C0392B',
        data: predict,
        lineWidth: 2,
        dashStyle:'ShortDot',
        marker:{ enabled:false },
        zIndex: 4
      }
    ]
  });

  // 15秒ごとに再取得
  setTimeout(draw, 15000);

  // ===== util =====
  function banner(text, cls, subtle=false){
    const el = document.getElementById('alert');
    el.style.display='block';
    el.className='banner' + (cls ? ' '+cls : '');
    el.style.opacity = subtle ? .85 : 1;
    el.textContent = text;
  }
  function floorToHalfHour(ms){
    const d = new Date(ms); d.setSeconds(0,0);
    d.setMinutes(d.getMinutes()<30 ? 0 : 30); return d.getTime();
  }
  function fmt(ms){
    const d = new Date(ms); const p=n=>String(n).padStart(2,'0');
    return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
  }
  function timefmt(ms){
    const d = new Date(ms); const p=n=>String(n).padStart(2,'0');
    return `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  }
  function qs(sel){ return document.querySelector(sel); }
})();
</script>
</body>
</html>
