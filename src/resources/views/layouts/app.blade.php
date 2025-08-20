<!doctype html>
<html lang="{{ str_replace("_","-",app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield("title", config("app.name","Laravel"))</title>
  @vite(["resources/css/app.css","resources/js/app.js"])
</head>
<body>
  <header style="padding:10px;border-bottom:1px solid #eee;display:flex;gap:12px;align-items:center;">
    <a href="/">Home</a>
    @auth
      <span style="opacity:.7;margin-left:8px;">{{ auth()->user()->email }}</span>
      <form method="POST" action="{{ route("logout") }}" style="margin-left:auto;">
        @csrf
        <button type="submit">Log out</button>
      </form>
    @endauth
  </header>
  @includeWhen(View::hasSection('crumb'), 'partials.breadcrumb')
  <main style="padding:16px;">
    @yield("content")
  </main>
</body>
</html>
