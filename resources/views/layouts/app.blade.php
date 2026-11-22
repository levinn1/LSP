<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Penyewaan Garasi')</title>
    <style>
        body{font-family:system-ui,sans-serif;margin:0;background:#f4f5f7;color:#222}
        nav{background:#1f2937;padding:12px 24px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
        nav a,nav button{color:#fff;text-decoration:none;background:none;border:0;font:inherit;cursor:pointer}
        nav .sp{flex:1}
        main{max-width:960px;margin:24px auto;padding:0 16px}
        .card{background:#fff;border-radius:8px;padding:20px;box-shadow:0 1px 3px #0002;margin-bottom:16px}
        label{display:block;margin:10px 0 4px;font-weight:600}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box}
        button.btn,a.btn{display:inline-block;background:#2563eb;color:#fff;padding:8px 16px;border:0;border-radius:6px;cursor:pointer;text-decoration:none;margin-top:12px}
        button.red,a.red{background:#dc2626}
        table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        .ok{background:#dcfce7;padding:10px;border-radius:6px;margin-bottom:12px}
        .err{background:#fee2e2;padding:10px;border-radius:6px;margin-bottom:12px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
    </style>
</head>
<body>
<nav>
    <strong style="color:#fff">Penyewaan Garasi</strong>
    @auth
        @if(auth()->user()->role === 'admin')
            <a href="{{ route('admin.garages.index') }}">Garasi</a>
            <a href="{{ route('admin.verifications.index') }}">Verifikasi</a>
            <a href="{{ route('admin.extensions.index') }}">Perpanjangan</a>
            <a href="{{ route('admin.reports') }}">Laporan</a>
        @else
            <a href="{{ route('rentals.index') }}">Riwayat</a>
            <a href="{{ route('rentals.create') }}">Sewa Garasi</a>
        @endif
        <span class="sp"></span>
        <span style="color:#fff">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button>Keluar</button></form>
    @endauth
</nav>
<main>
    @if(session('status'))<div class="ok">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
</main>
</body>
</html>
