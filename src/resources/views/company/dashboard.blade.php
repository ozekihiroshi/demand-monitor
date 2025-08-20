{{-- resources/views/company/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', $company->name.' ダッシュボード')

@section('content')
  @php
    $crumbs = [
      ['label' => $company->name],
    ];
  @endphp
  @include('partials.breadcrumbs', compact('crumbs'))

  <h2>{{ $company->name }}</h2>

  <h4 class="mt-4">施設一覧</h4>
  <ul>
    @forelse($facilities as $f)
      <li>
        <a href="{{ route('facility.dashboard', $f) }}">{{ $f->name }}</a>
      </li>
    @empty
      <li>施設未登録</li>
    @endforelse
  </ul>
@endsection
