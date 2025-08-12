@extends('layouts.admin')
@section('title', $code.' / series')
@section('content')
<h3>{{ $code }} — 日次オーバーレイ</h3>
<div style="margin-bottom:8px">
  <a href="{{ route('admin.meters.series', $code) }}?bucket=30m">30分</a> |
  <a href="{{ route('admin.meters.series', $code) }}?bucket=1m">1分</a>
</div>
<div id="chart" style="height:520px"></div>
@endsection

@push('scripts')
<script>
(async function(){
  const code   = @json($code);
  const rate   = @json($rate);
  const goal   = @json($goal);
  const bucket = new URLSearchParams(location.search).get('bucket') || '30m';

  const res = await fetch(`/api/v1/meters/${code}/series?bucket=${bucket}&days=8&rate=${rate}&goal=${goal}`);
  const j   = await res.json();

  Highcharts.chart('chart', {
    title:{ text:`${j.meter} / ${bucket} / JST` },
    xAxis:{ type:'datetime' },
    yAxis:{
      title:{ text:'デマンド (kW)' }, min:0,
      plotLines:[{ value: j.goal_kw, color:'red', width:2, zIndex:5, label:{ text:`閾値 ${j.goal_kw} kW`, align:'left', style:{color:'#666'} } }]
    },
    tooltip:{ xDateFormat:'%m/%d %H:%M' },
    series: j.series.map(s => ({
      name: s.label,
      data: s.data.map(p=>[Date.parse(p[0]),p[1]]),
      color: s.color, visible: s.visible, lineWidth:1.5, marker:{enabled:false}
    }))
  });
})();
</script>
@endpush