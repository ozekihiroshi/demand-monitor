@extends('layouts.admin')
@section('title','計器一覧')
@section('content')
<table border="1" cellspacing="0" cellpadding="6">
  <thead><tr><th>コード</th><th>名称</th><th>rate</th><th>しきい値</th><th>操作</th></tr></thead>
  <tbody>
  @foreach($meters as $m)
    <tr>
      <td>{{ $m->code }}</td>
      <td>{{ $m->name }}</td>
      <td>{{ $m->rate }}</td>
      <td>{{ $m->shikiichi }}</td>
      <td>
        <a href="{{ route('admin.meters.demand',$m->code) }}">当日リアルタイム</a> /
        <a href="{{ route('admin.meters.series',$m->code) }}">オーバーレイ</a>
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
@endsection

