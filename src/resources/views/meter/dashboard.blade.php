{{-- resources/views/meter/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', 'メータ '.$meter->code)

@section('content')
  @php
    $crumbs = [
      ['label' => $facility->company?->name ?? '会社', 'url' => $facility->company ? route('company.dashboard', $facility->company) : null],
      ['label' => $facility->name, 'url' => route('facility.dashboard', $facility)],
      ['label' => 'メータ '.$meter->code],
    ];
  @endphp
  @include('partials.breadcrumbs', compact('crumbs'))

  <h2>メータ {{ $meter->code }}</h2>
  <ul>
    <li><a href="{{ route('meter.demand', $meter->code) }}">当日リアルタイム</a></li>
    <li><a href="{{ route('meter.minute', $meter->code) }}">1分（オーバーレイ/連続）</a></li>
    <li><a href="{{ route('meter.series', $meter->code) }}">30分オーバーレイ</a></li>
  </ul>
@endsection
