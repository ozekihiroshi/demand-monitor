<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $code }} / demand</title>
<script src="https://code.highcharts.com/highcharts.js"></script>
<style>
.banner{padding:8px 12px;margin:8px 0;border-radius:6px;}
.ok{background:#eef7ee;color:#244b24}
.ng{background:#fdecea;color:#611a15}
</style>
</head><body>
<h3>{{ $code }} — 当日リアルタイム（実績＆予測）</h3>
<div id="alert" class="banner"></div>
<div id="chart" style="height:520px"></div>

<script>
async function loadAndDraw(){
  const code = @json($code);
  const url  = `/api/v1/meters/${code}/demand`;

  const res = await fetch(url);
  const j   = await res.json();

  const th  = j.threshold ?? 800;
  const last = j.predict_last;
  const exceed = j.will_exceed_threshold === true;

const inst = j.series?.instant ?? [];
const acc  = j.series?.accumulation ?? [];
const pred = j.series?.predict ?? [];

const getMax = a => a.reduce((m,p)=> Math.max(m, p[1]), 0);
const dataMax = Math.max(getMax(inst), getMax(acc), getMax(pred));

const yMax = Math.max(th, dataMax) * 1.05; // 5%ヘッドルーム

  const alert = document.getElementById('alert');
  if(exceed){
    alert.className='banner ng';
    alert.textContent = `警報: この30分の最終予測 ${(last && last[1] != null)? last[1].toFixed(1) : '-'} kW が閾値 ${th} kW を超過します`;
  } else {
    alert.className='banner ok';
    alert.textContent = `正常: 予測は閾値 ${th} kW 未満です`;
  }
  Highcharts.setOptions({
    time: { timezoneOffset: -9 * 60 } // 分指定。JST(UTC+9) は -540
  });
  Highcharts.chart('chart', {
    chart:{ zoomType:'x' },
    title:{ text:`${j.meter} / ${j.window.start}～${j.window.end}` },
    xAxis:{
      type:'datetime',
      min: Date.parse(j.window.start),
      max: Date.parse(j.window.end)
    },
     yAxis: {
    title: { text: 'デマンド (kW)' },
    min: 0,
    max: yMax,
    plotLines: [{
      value: th,
      color: 'red',
      width: 2,
      zIndex: 3,
      label: { text: `閾値 ${th} kW`, align: 'left', style: { color: '#666' } }
    }]
  },
    tooltip:{ shared:true, xDateFormat:'%H:%M' },
    series:[
      // ① 現時点実績（積算） ← 緑の太線
      { name:'現時点実績（積算）', color:'#2E8B57', data: j.series.accumulation.map(p=>[Date.parse(p[0]),p[1]]), lineWidth:2 },

      // ② 瞬間デマンド（任意、見たければ有効化）
      { name:'瞬間デマンド', color:'#888888', data: j.series.instant.map(p=>[Date.parse(p[0]),p[1]]), lineWidth:1 ,marker:{ enabled:false },
    visible:false},

      // ③ 予測（点線・赤）
      { name:'デマンド予測',  color:'#C0392B', data: j.series.predict.map(p=>[Date.parse(p[0]),p[1]]), dashStyle:'ShortDot', lineWidth:2 }
    ]
  });
  setTimeout(loadAndDraw, 30000);
}
loadAndDraw();
</script>
</body></html>
