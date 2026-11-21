@extends('layouts.app')
@section('title', 'Riwayat Sewa')
@section('content')
<div class="card">
    <h2>Riwayat Sewa</h2>
    <a class="btn" href="{{ route('rentals.create') }}">+ Sewa Baru</a>
    {{-- Tabel riwayat sewa milik penyewa yang login. $rentals berasal dari RentalController::index(). --}}
    <table>
        <tr><th>Garasi</th><th>Kendaraan</th><th>Tipe</th><th>Mulai</th><th>Selesai</th><th>Total</th><th>Status</th></tr>
        @forelse($rentals as $r)
            <tr>
                <td>{{ $r->garage->code }}</td>
                <td>{{ $r->vehicle->brand }} {{ $r->vehicle->model }} {{ $r->vehicle->plate }}</td>
                <td>{{ ucfirst($r->rental_type) }}</td>
                <td>{{ $r->start_date->format('d/m/Y') }}</td>
                <td>{{ $r->end_date->format('d/m/Y') }}</td>
                <td>Rp {{ number_format($r->total_cost, 0, ',', '.') }}</td>
                {{-- Status dihitung dari tanggal: sebelum mulai = dipesan, sedang berjalan = used, lewat tanggal selesai = selesai. --}}
                <td>{{ $r->start_date->isFuture() ? 'dipesan' : ($r->end_date->endOfDay()->isPast() ? 'selesai' : 'used') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Belum ada sewa.</td></tr>
        @endforelse
    </table>
    {{-- Tautan pagination. --}}
    {{ $rentals->links() }}
</div>
@endsection
