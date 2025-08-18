{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6 space-y-8">
  <h1 class="text-2xl font-semibold mb-2">Admin Dashboard</h1>

  {{-- カード3枚 --}}
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="rounded-xl border p-4">
      <div class="text-sm text-gray-500">Companies</div>
      <div class="text-3xl font-bold">{{ number_format($companies) }}</div>
    </div>
    <div class="rounded-xl border p-4">
      <div class="text-sm text-gray-500">Facilities</div>
      <div class="text-3xl font-bold">{{ number_format($facilities) }}</div>
    </div>
    <div class="rounded-xl border p-4">
      <div class="text-sm text-gray-500">Meters</div>
      <div class="text-3xl font-bold">{{ number_format($meters) }}</div>
    </div>
  </div>

  {{-- 最近のメーター更新 --}}
  <div class="rounded-xl border">
    <div class="p-4 border-b font-medium">最近のメーター更新</div>
    <div class="p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-left text-gray-500">
          <th class="py-2 pr-6">ID</th>
          <th class="py-2 pr-6">Code</th>
          <th class="py-2 pr-6">Name</th>
          <th class="py-2 pr-6">Updated</th>
        </tr></thead>
        <tbody>
        @forelse($recentMeterUpdates as $m)
          <tr class="border-t">
            <td class="py-2 pr-6">{{ $m->id }}</td>
            <td class="py-2 pr-6">{{ $m->code ?? '-' }}</td>
            <td class="py-2 pr-6">{{ $m->name ?? '-' }}</td>
            <td class="py-2 pr-6">{{ optional($m->updated_at)->format('Y-m-d H:i') }}</td>
          </tr>
        @empty
          <tr><td class="py-4 text-gray-500" colspan="4">データなし</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- 最近のエラー（failed_jobs がある場合のみ） --}}
  @if($recentErrors->count())
  <div class="rounded-xl border">
    <div class="p-4 border-b font-medium">最近のエラー（failed_jobs）</div>
    <div class="p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-left text-gray-500">
          <th class="py-2 pr-6">ID</th>
          <th class="py-2 pr-6">Connection</th>
          <th class="py-2 pr-6">Queue</th>
          <th class="py-2 pr-6">Failed At</th>
        </tr></thead>
        <tbody>
        @foreach($recentErrors as $e)
          <tr class="border-t">
            <td class="py-2 pr-6">{{ $e->id }}</td>
            <td class="py-2 pr-6">{{ $e->connection }}</td>
            <td class="py-2 pr-6">{{ $e->queue }}</td>
            <td class="py-2 pr-6">{{ \Carbon\Carbon::parse($e->failed_at)->format('Y-m-d H:i') }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

</div>
@endsection

