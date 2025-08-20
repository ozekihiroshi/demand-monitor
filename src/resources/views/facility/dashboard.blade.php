{{-- resources/views/facility/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', ($facility->name ?? '施設').' ダッシュボード')

@section('content')
  @php
    $crumbs = [
      ['label'=>$facility->company?->name ?? '会社', 'url'=>$facility->company ? route('company.dashboard',$facility->company) : null],
      ['label'=>$facility->name ?? '施設'],
    ];
  @endphp
  @include('partials.breadcrumbs', compact('crumbs'))

  <h2>{{ $facility->name ?? '施設' }} ダッシュボード</h2>

  <h4 class="mt-4">代表メータ</h4>
  @php $code = $code ?? ($facility->main_meter_code ?? optional($facility->meters->first())->code); @endphp
  @if ($code)
    <ul>
      <li><a href="{{ route('meter.demand', $code) }}">当日リアルタイム</a></li>
      <li><a href="{{ route('meter.minute', $code) }}">1分（オーバーレイ/連続）</a></li>
      <li><a href="{{ route('meter.series', $code) }}">30分オーバーレイ</a></li>
    </ul>
  @else
    <p>代表メータ未設定（メータ一覧から選択してください）。</p>
  @endif

  <h4 class="mt-6">メータ一覧</h4>
  <ul>
    @forelse($facility->meters as $m)
  <li>
    @if ($m)
      <a href="{{ route('facility.meters.show', ['facility' => $facility, 'meter' => $m]) }}">
        メータ {{ $m->code }}
      </a>
      — <a href="{{ route('meter.demand', $m->code) }}">当日</a>
      — <a href="{{ route('meter.minute', $m->code) }}">1分</a>
      — <a href="{{ route('meter.series', $m->code) }}">30分</a>
    @else
      <span>（不正なメータ）</span>
    @endif
  </li>
    @empty
  <li>メータ未登録</li>
    @endforelse
  </ul>
@endsection
