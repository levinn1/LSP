{{-- // page register akun --}}
@extends('layouts.app')
@section('title', 'Registrasi')
@section('content')
<div class="card" style="max-width:480px;margin:auto">
    <h2>Registrasi Penyewa</h2>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label>NIK (16 digit)</label><input name="nik" value="{{ old('nik') }}" maxlength="16" required>
        <label>Nama Lengkap</label><input name="name" value="{{ old('name') }}" required>
        <label>Nomor Telepon</label><input name="phone" value="{{ old('phone') }}" required>
        <label>Email (username)</label><input type="email" name="email" value="{{ old('email') }}" required>
        <label>Password</label><input type="password" name="password" required>
        <label>Konfirmasi Password</label><input type="password" name="password_confirmation" required>
        <button class="btn">Daftar</button>
    </form>
    <p>Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
</div>
@endsection
