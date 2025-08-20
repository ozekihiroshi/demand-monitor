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
  @if (!empty($meter) && $meter->relationLoaded('facility') && $meter->facility && $meter->facility->relationLoaded('company'))
  @include('partials.breadcrumbs', ['crumbs' => [
    ['label'=>$meter->facility->company->name, 'url'=>route('company.dashboard', $meter->facility->company)],
    ['label'=>$meter->facility->name,          'url'=>route('facility.dashboard', $meter->facility)],
    ['label'=>'メータ '.$meter->code,          'url'=>route('facility.meters.show', [$meter->facility, $meter])],
    ['label'=>'当日リアルタイム'],
  ]])
  @endif
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
(() => {
  Highcharts.setOptions({ time: { useUTC: false } });
  const code = @json($code);
  const thresholdFromBlade = @json($threshold ?? null);

  let chart = Highcharts.chart('chart', {
    title:{ text:'Loading...' },
    credits:{ enabled:false },
    xAxis:{ type:'datetime' },
    yAxis:{ title:{ text:'デマンド (kW)' }, min:0, startOnTick:true, endOnTick:true, tickAmount:6, plotLines:[] },
    tooltip:{ shared:true, xDateFormat:'%H:%M' },
    legend:{ enabled:true },
    series:[
      { name:'瞬間デマンド', type:'line', color:'#999', data:[], lineWidth:1, marker:{enabled:false}, zIndex:1, visible:true },
      { name:'現時点実績（積算）', type:'line', color:'#2E8B57', data:[], lineWidth:2, marker:{enabled:false}, zIndex:3 },
      { name:'デマンド予測（終端まで）', type:'line', color:'#C0392B', data:[], lineWidth:2, dashStyle:'ShortDot', marker:{enabled:false}, zIndex:4 }
    ]
  });

  async function refresh(){
    try {
      const url = new URL(`/api/v1/meters/${encodeURIComponent(code)}/demand`, location.origin);
      if (thresholdFromBlade !== null) url.searchParams.set('threshold', thresholdFromBlade);

      const j = await fetchJson(url);

      const xMin = Date.parse(j.window.start);
      const xMax = Date.parse(j.window.end);
      chart.setTitle({ text:`${j.meter ?? code} / ${fmt(xMin)} ～ ${fmt(xMax)}` }, false);
      chart.xAxis[0].setExtremes(xMin, xMax, false);

      const th = j.threshold ?? thresholdFromBlade ?? null;
      chart.yAxis[0].update({
        plotLines: th===null ? [] : [{
          value:Number(th), color:'red', width:2, zIndex:5,
          label:{ text:`閾値 ${Number(th)} kW`, align:'left', style:{color:'#666'} }
        }]
      }, false);

      const inst  = (j.series.instant ?? []).map(p => [Date.parse(p[0]), Number(p[1])]);
      const accum = (j.series.accumulation ?? []).map(p => [Date.parse(p[0]), Number(p[1])]);
      const pred  = (j.series.predict ?? []).map(p => [Date.parse(p[0]), Number(p[1])]);

      chart.series[0].setData(inst, false);
      chart.series[1].setData(accum, false);
      chart.series[2].setData(pred, false);
      chart.redraw();

      const nowMs  = j.now ? Date.parse(j.now) : Date.now();
      const lastTs = inst.length ? inst[inst.length-1][0] : xMin;
      const lagSec = Math.max(0, Math.round((nowMs - lastTs)/1000));
      const remainMs = Math.max(0, xMax - nowMs);
      const mm = Math.floor(remainMs/60000), ss = Math.floor((remainMs%60000)/1000);

      const predLast = j.predict_last ? [Date.parse(j.predict_last[0]), Number(j.predict_last[1])] : null;

      const bannerEl = document.getElementById('alert');
      const kpisEl = document.getElementById('kpis'); kpisEl.style.display='flex';
      qs('#kpi-last-ts').textContent = timefmt(lastTs);
      qs('#kpi-lag').textContent = `${lagSec}s`;
      qs('#kpi-remaining').textContent = `${String(mm).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
      qs('#kpi-predict').textContent = predLast ? `${predLast[1].toFixed(1)} kW` : '-- kW';
      qs('#kpi-th').textContent = th != null ? `${Number(th)} kW` : '-- kW';
      const margin = (th != null && predLast) ? +(Number(th) - predLast[1]).toFixed(1) : null;
      qs('#kpi-margin').textContent = margin != null ? `${margin >= 0 ? '+' : ''}${margin} kW` : '-- kW';

      if (th != null && predLast) {
        const exceed = predLast[1] >= Number(th);
        bannerEl.style.display = 'block';
        bannerEl.className = 'banner ' + (exceed ? 'ng' : 'ok');
        bannerEl.textContent = exceed
          ? `警報: 最終予測 ${predLast[1].toFixed(1)} kW が閾値 ${Number(th)} kW を超過`
          : `正常: 予測は閾値 ${Number(th)} kW 未満`;
      } else {
        bannerEl.style.display = 'block';
        bannerEl.className = 'banner';
        bannerEl.textContent = 'データ取得';
      }
    } catch (e) {
      // ★ 失敗時の見える化
      const bannerEl = document.getElementById('alert');
      bannerEl.style.display = 'block';
      bannerEl.className = 'banner';
      bannerEl.textContent = 'データ取得に失敗しました';
      console.error('refresh() failed:', e);
    }
  }

  async function fetchJson(url){
    const res = await fetch(url.toString(), { cache:'no-store' });
    if (!res.ok) { throw new Error(`${res.status} ${res.statusText}`); }
    return res.json();
  }
  function fmt(ms){ const d=new Date(ms),p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`; }
  function timefmt(ms){ const d=new Date(ms),p=n=>String(n).padStart(2,'0'); return `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`; }
  function qs(s){ return document.querySelector(s); }

  refresh();               // 起動時
  setInterval(refresh, 15000); // 15秒おき
})();
</script>

</body>
</html>
