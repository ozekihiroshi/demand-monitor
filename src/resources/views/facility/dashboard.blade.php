{{-- resources/views/facility/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', $facility->name.' ダッシュボード')

@section('content')
  @include('partials.breadcrumbs', ['crumbs' => [
    ['label'=>$facility->company->name, 'url'=>route('company.dashboard', $facility->company)],
    ['label'=>$facility->name],
  ]])

  <h3>{{ $facility->name }} ダッシュボード</h3>

  <h4 class="mt-4">代表メータ</h4>
  <ul>
    <li><a href="{{ route('meter.demand', $code) }}">当日リアルタイム</a></li>
    <li><a href="{{ route('meter.minute', $code) }}">1分（オーバーレイ/連続）</a></li>
    <li><a href="{{ route('meter.series', $code) }}">30分オーバーレイ</a></li>
  </ul>

  <h4 class="mt-6">メータ一覧</h4>
  <ul>
    @foreach($facility->meters as $m)
      <li>
        <a href="{{ route('facility.meters.show', [$facility, $m]) }}">メータ {{ $m->code }}</a>
        — <a href="{{ route('meter.demand', $m->code) }}">当日</a>
        — <a href="{{ route('meter.minute', $m->code) }}">1分</a>
        — <a href="{{ route('meter.series', $m->code) }}">30分</a>
      </li>
    @endforeach
  </ul>
@endsection
