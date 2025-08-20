{{-- resources/views/company/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', $company->name.' ダッシュボード')
@section('content')
  <h2>{{ $company->name }}</h2>
  <ul>
    @foreach($facilities as $f)
      <li><a href="{{ route('facility.dashboard', $f) }}">{{ $f->name }}</a></li>
    @endforeach
  </ul>
  @section('crumb') <a href="{{ route('company.dashboard', $company) }}">{{ $company->name }}</a> @endsection
@endsection

