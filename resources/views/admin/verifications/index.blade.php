{{-- // halaman verif akun pendaftar untuk admin --}}
@extends('layouts.app')
@section('title', 'Verifikasi Pendaftar')
@section('content')
<div class="card">
    <h2>Verifikasi Pendaftar Baru</h2>
    <table>
        <tr><th>NIK</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Aksi</th></tr>
        @forelse($users as $u)
            <tr>
                <td>{{ $u->nik }}</td><td>{{ $u->name }}</td><td>{{ $u->phone }}</td><td>{{ $u->email }}</td>
                <td style="display:flex;gap:6px">
                    <form method="POST" action="{{ route('admin.verifications.accept', $u) }}">@csrf<button class="btn">Terima</button></form>
                    <form method="POST" action="{{ route('admin.verifications.reject', $u) }}">@csrf<button class="btn red">Tolak</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Tidak ada pendaftar yang menunggu.</td></tr>
        @endforelse
    </table>
</div>
@endsection
