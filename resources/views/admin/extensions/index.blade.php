{{-- // tampilan dashboard admin --}}
@extends('layouts.app')
@section('title', 'Perpanjang Sewa')
@section('content')
<div class="card">
    <h2>Garasi yang Sedang Disewa Hari Ini</h2>
    @forelse($rentals as $r)
        <form method="POST" action="{{ route('admin.extensions.store', $r) }}" enctype="multipart/form-data" class="card" style="background:#f9fafb">
            @csrf
            <strong>{{ $r->garage->code }}</strong> - {{ $r->user->name }} ({{ $r->vehicle->brand }} {{ $r->vehicle->model }})<br>
            Selesai saat ini: {{ $r->end_date->format('d/m/Y') }} | Tarif harian: Rp {{ number_format($r->garage->daily_price, 0, ',', '.') }}
            <div class="grid">
                <div><label>Tanggal Selesai Baru</label><input type="date" name="new_end_date" min="{{ $r->end_date->copy()->addDay()->toDateString() }}" required></div>
                <div><label>Bukti Pembayaran (gambar)</label><input type="file" name="payment_proof" accept="image/*" required></div>
            </div>
            <button class="btn">Perpanjang</button>
        </form>
    @empty
        <p>Tidak ada garasi yang sedang disewa hari ini.</p>
    @endforelse
</div>
@endsection
