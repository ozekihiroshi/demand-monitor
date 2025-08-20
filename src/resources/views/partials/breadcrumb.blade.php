{{-- resources/views/partials/breadcrumb.blade.php --}}
<nav class="text-sm mb-3">
  <a href="/">Home</a>
  @foreach(($crumbs ?? []) as $c)
    › @if(!empty($c['url'])) <a href="{{ $c['url'] }}">{{ $c['label'] }}</a>
      @else <span>{{ $c['label'] }}</span>
      @endif
  @endforeach
</nav>
