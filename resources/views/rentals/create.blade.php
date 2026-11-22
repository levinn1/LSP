{{-- // page untuk penyewa saat mau sewa garasi --}}
@extends('layouts.app')
@section('title', 'Sewa Baru')
@section('content')
<div class="card">
    <h2>Sewa Garasi Baru</h2>
    <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid">
            <div><label>Tipe Kendaraan</label>
                <select name="vehicle_type" id="vehicle_type">
                    @foreach(['sepeda'=>'Sepeda','motor'=>'Sepeda Motor','mobil'=>'Mobil','mobil_listrik'=>'Mobil Listrik'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('vehicle_type')===$k)>{{ $v }}</option>
                    @endforeach
                </select></div>
            <div id="plate_row"><label>Nomor Plat</label><input name="plate" value="{{ old('plate') }}"></div>
            <div><label>Merek</label><input name="brand" value="{{ old('brand') }}" required></div>
            <div><label>Tipe/Model</label><input name="model" value="{{ old('model') }}" required></div>
        </div>
        <div class="grid" id="ev_rows">
            <div><label>Kapasitas Baterai Penuh (kWh)</label><input type="number" step="0.01" min="0.01" max="500" name="battery_capacity_kwh" value="{{ old('battery_capacity_kwh') }}"></div>
            <div><label>Sisa Baterai Saat Ini (kWh)</label><input type="number" step="0.01" min="0" max="500" name="battery_remaining_kwh" value="{{ old('battery_remaining_kwh') }}"></div>
        </div>
        <div class="grid"> 
            <div><label>Tipe Sewa</label>
                <select name="rental_type" id="rental_type">
                    <option value="harian" @selected(old('rental_type')==='harian')>Harian</option>
                    <option value="bulanan" @selected(old('rental_type')==='bulanan')>Bulanan</option>
                </select></div>
            <div id="days_row"><label>Jumlah Hari (maks. 30)</label><input type="number" name="days" id="days" min="1" max="30" value="{{ old('days', 1) }}"></div>
            <div><label>Tanggal Mulai</label><input type="date" name="start_date" id="start_date" min="{{ $today }}" value="{{ old('start_date', $today) }}"></div>
        </div>
        <label>Pilih Garasi</label>
        <select name="garage_id" id="garage_id" required><option value="">Memuat...</option></select>
        <p id="estimate" style="font-weight:600"></p>
        <label>Bukti Pembayaran (gambar)</label><input type="file" name="payment_proof" accept="image/*" required>
        <button class="btn">Simpan Sewa</button>
    </form>
</div>

<script>
// helper untuk ambil elemen form berdasarkan ID
const $ = id => document.getElementById(id);
let garages = [];

// input ditunjukkan atau tidak sesuai kendaraan dan jenis sewa
function toggleFields() {
    $('plate_row').style.display = $('vehicle_type').value === 'sepeda' ? 'none' : '';
    $('ev_rows').style.display = $('vehicle_type').value === 'mobil_listrik' ? '' : 'none';
    $('days_row').style.display = $('rental_type').value === 'harian' ? '' : 'none';
}

// fetch daftar garasi sesuai dengan pilihan penyewa
async function loadGarages() {
    const p = new URLSearchParams({
        vehicle_type: $('vehicle_type').value, rental_type: $('rental_type').value,
        days: $('days').value, start_date: $('start_date').value,
    });
    const res = await fetch('{{ route('rentals.available') }}?' + p, {headers: {'Accept': 'application/json'}});
    const sel = $('garage_id');
    if (!res.ok) { garages = []; sel.innerHTML = '<option value="">Input belum valid</option>'; $('estimate').textContent = ''; return; }
    garages = await res.json();
    sel.innerHTML = garages.length
        ? garages.map(g => `<option value="${g.id}">${g.code} - Rp ${g.daily_price.toLocaleString('id-ID')}/hari</option>`).join('')
        : '<option value="">Tidak ada garasi tersedia</option>';
    estimate();
}

// hitung biaya sewa sesuai dengan pilihan penyewa
function estimate() {
    const g = garages.find(x => x.id == $('garage_id').value);
    if (!g) { $('estimate').textContent = ''; return; }
    const cost = $('rental_type').value === 'bulanan' ? 25 * g.daily_price : $('days').value * g.daily_price;
    $('estimate').textContent = 'Estimasi biaya sewa: Rp ' + cost.toLocaleString('id-ID')
        + (g.type === 'mobil_listrik' ? ' (belum termasuk biaya listrik; total final dihitung saat disimpan)' : '');
}

['vehicle_type', 'rental_type', 'days', 'start_date'].forEach(id => $(id).addEventListener('change', () => { toggleFields(); loadGarages(); }));
$('garage_id').addEventListener('change', estimate);
toggleFields(); loadGarages();
</script>
@endsection
