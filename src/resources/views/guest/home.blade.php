<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>電気の見える化</title>
  <style>
    body{font-family:system-ui;margin:32px;max-width:920px}
    header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    a.button,button{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #ddd;text-decoration:none}
    .cta{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
    .muted{color:#666}
  </style>
</head>
<body>
  <header>
    <h1 class="muted">電気の見える化</h1>
    <div>
      @auth
        <a class="button" href="{{ route('dashboard') }}">ダッシュボードへ</a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
          @csrf
          <button type="submit" class="button">ログアウト</button>
        </form>
      @else
        <a class="button cta" href="{{ route('login') }}">ログイン</a>
      @endauth
    </div>
  </header>

  <p>各施設の電力データをリアルタイムに可視化します。権限に応じて会社・施設・メータにアクセスできます。</p>

  <hr>

  <h3>主な機能</h3>
  <ul>
    <li>当日リアルタイム（実績＋予測）</li>
    <li>1分データ（オーバーレイ／連続表示）</li>
    <li>30分オーバーレイ</li>
  </ul>

  {{-- 任意：公開チャートのサンプルリンクを置くなら（不要なら削除）
  <h3>公開サンプル</h3>
  <ul>
    <li><a href="/meters/d100318/demand">当日リアルタイム（サンプル）</a></li>
  </ul>
  --}}
</body>
</html>
