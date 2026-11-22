{{-- // halaman admin untuk laporan penyewaan --}}
@extends('layouts.app')
@section('title', 'Laporan Penyewaan')
@section('content')
<div class="card">
    <h2>Laporan Penyewaan Garasi</h2>
    <form method="GET" action="{{ route('admin.reports') }}">
        <div class="grid">
            <div><label>Tahun</label>
                <select name="year"><option value="">Semua</option>
                    @foreach($years as $y)<option value="{{ $y }}" @selected(($filters['year'] ?? null) == $y)>{{ $y }}</option>@endforeach
                </select></div>
            <div><label>Bulan</label>
                <select name="month"><option value="">Semua</option>
                    @for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected(($filters['month'] ?? null) == $m)>{{ $m }}</option>@endfor
                </select></div>
            <div><label>Tanggal (hari)</label><input type="number" name="day" min="1" max="31" value="{{ $filters['day'] ?? '' }}"></div>
            <div><label>Tipe Garasi</label>
                <select name="garage_type"><option value="">Semua</option>
                    @foreach(['sepeda','mobil','mobil_listrik'] as $t)<option value="{{ $t }}" @selected(($filters['garage_type'] ?? null) === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach
                </select></div>
            <div><label>Penyewa</label>
                <select name="user_id"><option value="">Semua</option>
                    @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>@endforeach
                </select></div>
        </div>
        <button class="btn">Tampilkan</button>
        <a class="btn red" href="{{ route('admin.reports') }}">Reset</a>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>Mulai</th><th>Selesai</th><th>Garasi</th><th>Penyewa</th><th>Kendaraan</th><th>Tipe</th><th>Total</th></tr>
        @forelse($rentals as $r)
            <tr>
                <td>{{ $r->start_date->format('d/m/Y') }}</td><td>{{ $r->end_date->format('d/m/Y') }}</td>
                <td>{{ $r->garage->code }}</td><td>{{ $r->user->name }}</td>
                <td>{{ $r->vehicle->brand }} {{ $r->vehicle->model }}</td><td>{{ ucfirst($r->rental_type) }}</td>
                <td>Rp {{ number_format($r->total_cost, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Tidak ada data.</td></tr>
        @endforelse
    </table>
    <p><strong>Total {{ $rentals->total() }} transaksi (semua halaman): Rp {{ number_format($total, 0, ',', '.') }}</strong></p>
    {{ $rentals->links() }}
</div>
@endsection
