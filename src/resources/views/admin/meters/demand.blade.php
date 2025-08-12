@extends('layouts.admin')
@section('title', $code.' / demand')
@section('content')
<h3>{{ $code }} — 当日リアルタイム（実績＆予測）</h3>
<div id="alert" class="banner"></div>
<div id="chart" style="height:520px"></div>
@endsection

@push('scripts')
<script>
(async function loadAndDraw(){
  const code = @json($code);
  const rate = @json($rate);
  const th   = @json($threshold);
  const res  = await fetch(`/api/v1/meters/${code}/demand?rate=${rate}&threshold=${th}`);
  const j    = await res.json();

  const last = j.predict_last;
  const exceed = last ? (last[1] >= th) : false;

  const alert = document.getElementById('alert');
  alert.className = 'banner ' + (exceed ? 'ng' : 'ok');
  alert.textContent = exceed
    ? `警報: 最終予測 ${last[1].toFixed(1)} kW が閾値 ${th} kW を超過`
    : `正常: 予測は閾値 ${th} kW 未満`;

  Highcharts.chart('chart', {
    title:{ text:`${j.meter} / ${j.window.start} ～ ${j.window.end}` },
    xAxis:{ type:'datetime', min: Date.parse(j.window.start), max: Date.parse(j.window.end) },
    yAxis:{
      title:{ text:'デマンド (kW)' }, min:0,
      plotLines:[{ value: th, color:'red', width:2, zIndex:5, label:{ text:`閾値 ${th} kW`, align:'left', style:{color:'#666'} } }]
    },
    tooltip:{ shared:true, xDateFormat:'%H:%M' },
    series:[
      { name:'現時点実績（積算）', color:'#2E8B57', data: j.series.accumulation.map(p=>[Date.parse(p[0]),p[1]]), lineWidth:2 },
      { name:'瞬間デマンド',       color:'#888',   data: j.series.instant.map(p=>[Date.parse(p[0]),p[1]]), lineWidth:1, marker:{enabled:false}, visible:false },
      { name:'デマンド予測',        color:'#C0392B', data: j.series.predict.map(p=>[Date.parse(p[0]),p[1]]), dashStyle:'ShortDot', lineWidth:2 }
    ]
  });

  setTimeout(loadAndDraw, 30000);
})();
</script>
@endpush