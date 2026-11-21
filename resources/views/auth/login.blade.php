@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<div class="card" style="max-width:400px;margin:auto">
    <h2>Masuk</h2>
    {{-- Form login. @csrf wajib untuk melindungi dari serangan CSRF; old() mempertahankan input email jika gagal. --}}
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>
        <label>Password</label><input type="password" name="password" required>
        <button class="btn">Masuk</button>
    </form>
    <p>Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
</div>
@endsection
