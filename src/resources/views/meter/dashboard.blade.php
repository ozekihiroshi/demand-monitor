@extends('layouts.app')
@section('title', 'メータ '.$meter->code)

@section('content')
  @include('partials.breadcrumbs', ['crumbs' => [
    ['label'=>$facility->company->name, 'url'=>route('company.dashboard', $facility->company)],
    ['label'=>$facility->name,          'url'=>route('facility.dashboard', $facility)],
    ['label'=>'メータ '.$meter->code],
  ]])

  <h3>メータ {{ $meter->code }}</h3>
  <ul>
    <li><a href="{{ route('meter.demand',  $meter->code) }}">当日リアルタイム</a></li>
    <li><a href="{{ route('meter.minute',  $meter->code) }}">1分（オーバーレイ/連続）</a></li>
    <li><a href="{{ route('meter.series',  $meter->code) }}">30分オーバーレイ</a></li>
  </ul>
@endsection