@extends('layouts.app')
@section('title', 'Daftar Garasi')
@section('content')
<div class="card">
    <h2>Daftar Garasi</h2>
    <a class="btn" href="{{ route('admin.garages.create') }}">+ Buat Garasi</a>
    {{-- Daftar garasi. Status "used/available" dihitung dari rentang sewa hari ini (Garage::isUsedOn), bukan kolom tersimpan. --}}
    <table>
        <tr><th>Kode</th><th>Tipe</th><th>Harga Harian</th><th>Harga/kWh</th><th>Status Hari Ini</th><th>Aksi</th></tr>
        @forelse($garages as $g)
            <tr>
                <td>{{ $g->code }}</td><td>{{ str_replace('_', ' ', $g->type) }}</td>
                <td>Rp {{ number_format($g->daily_price, 0, ',', '.') }}</td>
                <td>{{ $g->price_per_kwh ? 'Rp ' . number_format($g->price_per_kwh, 0, ',', '.') : '-' }}</td>
                <td>{{ $g->isUsedOn(now()) ? 'used' : 'available' }}</td>
               <td>
                    {{-- Tombol Edit menuju form edit; hapus tersedia di halaman edit. --}}
                    <a class="btn" style="margin:0;padding:4px 10px" href="{{ route('admin.garages.edit', $g) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">Belum ada garasi.</td></tr>
        @endforelse
    </table>
    {{ $garages->links() }}
</div>
@endsection
