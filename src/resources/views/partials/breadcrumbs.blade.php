{{-- resources/views/partials/breadcrumbs.blade.php --}}
@php
  /** @var array<int,array{label:string,url?:string|null}> $crumbs */
  $items = ($crumbs ?? []);
  $showHome = $showHome ?? false;

  if ($showHome) {
    $homeUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
    array_unshift($items, ['label' => 'Home', 'url' => $homeUrl]);
  }

  $last = max(count($items) - 1, 0);
@endphp

<nav aria-label="breadcrumbs" class="text-sm mb-3">
  @foreach($items as $i => $c)
    @if(!empty($c['url']) && $i < $last)
      <a href="{{ $c['url'] }}">{{ $c['label'] }}</a>
      <span aria-hidden="true"> &raquo; </span>
    @else
      <span>{{ $c['label'] }}</span>
    @endif
  @endforeach
</nav>
