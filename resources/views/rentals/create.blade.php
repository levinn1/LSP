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
            <div id="days_row"><label>Jumlah Hari (maks. <span id="max_days">30</span>)</label><input type="number" name="days" id="days" min="1" max="30" value="{{ old('days', 1) }}"></div>
            <div><label>Tanggal Mulai</label><input type="date" name="start_date" id="start_date" min="{{ $today }}" value="{{ old('start_date', $today) }}"></div>
        </div>
        <label>Pilih Garasi</label>
        <select name="garage_id" id="garage_id" required><option value="">Memuat...</option></select>
        <p id="end_info" style="font-weight:600;margin-bottom:4px"></p>
        <p id="estimate" style="font-weight:600"></p>
        <label>Bukti Pembayaran (gambar)</label><input type="file" name="payment_proof" accept="image/*" required>
        <button class="btn">Simpan Sewa</button>
    </form>
</div>

<script>
// helper untuk ambil elemen form berdasarkan ID
const $ = id => document.getElementById(id);
// ambil angka dari input berdasarkan atribut name
const num = name => parseFloat(document.querySelector(`[name="${name}"]`).value);
// format angka menjadi rupiah
const rupiah = n => 'Rp ' + Number(n).toLocaleString('id-ID');
// ubah tanggal Y-m-d (dari server) menjadi d/m/Y untuk ditampilkan
const formatDate = s => s.split('-').reverse().join('/');

const typeLabel = {sepeda: 'Garasi Sepeda', mobil: 'Garasi Mobil', mobil_listrik: 'Garasi Mobil Listrik'};
let garages = [];     // daftar garasi tersedia dari server
let endDate = null;   // tanggal selesai sewa hasil hitung server
let requestId = 0;

// batas sewa harian
function updateMaxDays() {
    const [y, m] = $('start_date').value.split('-').map(Number);
    if (!y || !m) return;
    const max = new Date(y, m, 0).getDate() - 1;
    $('days').max = max;
    $('max_days').textContent = max;
}

// input ditunjukkan atau tidak sesuai kendaraan dan jenis sewa
function toggleFields() {
    updateMaxDays();
    $('plate_row').style.display = $('vehicle_type').value === 'sepeda' ? 'none' : '';
    $('ev_rows').style.display = $('vehicle_type').value === 'mobil_listrik' ? '' : 'none';
    $('days_row').style.display = $('rental_type').value === 'harian' ? '' : 'none';
}

// fetch daftar garasi dan tanggal selesai sesuai dengan pilihan penyewa
async function loadGarages() {
    const myRequest = ++requestId;
    const p = new URLSearchParams({
        vehicle_type: $('vehicle_type').value, rental_type: $('rental_type').value,
        days: $('days').value, start_date: $('start_date').value,
    });
    const res = await fetch('{{ route('rentals.available') }}?' + p, {headers: {'Accept': 'application/json'}});
    if (myRequest !== requestId) return; 
    const sel = $('garage_id');

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        if (myRequest !== requestId) return;
        garages = []; endDate = null;
        sel.innerHTML = '<option value="">Input belum valid</option>';
        estimate();
        $('end_info').textContent = err.error || err.message || '';
        return;
    }

    const data = await res.json();
    if (myRequest !== requestId) return;
    garages = data.garages;
    endDate = data.end_date;

    if (!garages.length) {
        sel.innerHTML = '<option value="">Tidak ada garasi tersedia</option>';
        estimate();
        return;
    }

    // kelompokkan per tipe garasi agar jelas pilihan mana yang sesuai dengan kendaraan
    const groups = {};
    garages.forEach(g => (groups[g.type] = groups[g.type] || []).push(g));
    sel.innerHTML = Object.entries(groups).map(([type, list]) =>
        `<optgroup label="${typeLabel[type]}">` + list.map(g =>
            `<option value="${g.id}">${g.code} - ${rupiah(g.daily_price)}/hari`
            + (g.type === 'mobil_listrik' ? ` + listrik ${rupiah(g.price_per_kwh)}/kWh` : '')
            + `</option>`).join('') + `</optgroup>`
    ).join('');
    estimate();
}

// tampilkan tanggal selesai dan total biaya
function estimate() {
    $('end_info').textContent = endDate ? 'Tanggal selesai sewa: ' + formatDate(endDate) : '';

    const g = garages.find(x => x.id == $('garage_id').value);
    if (!g) { $('estimate').textContent = ''; return; }

    const rental = $('rental_type').value === 'bulanan' ? 25 * g.daily_price : $('days').value * g.daily_price;

    if (g.type !== 'mobil_listrik') {
        $('estimate').textContent = 'Total biaya: ' + rupiah(rental);
        return;
    }

    // mobil listrik di garasi listrik wajib isi daya sampai penuh
    const cap = num('battery_capacity_kwh'), rem = num('battery_remaining_kwh');
    if (isNaN(cap) || isNaN(rem) || rem < 0 || rem > cap) {
        $('estimate').textContent = 'Biaya sewa: ' + rupiah(rental)
            + '. Isi kapasitas dan sisa baterai dengan benar untuk menghitung biaya listrik.';
        return;
    }
    const kwh = Math.round((cap - rem) * 100) / 100;
    const charge = Math.round(kwh * g.price_per_kwh);
    $('estimate').textContent = 'Total biaya: ' + rupiah(rental + charge)
        + ` (sewa ${rupiah(rental)} + listrik ${rupiah(charge)} untuk ${kwh} kWh)`;
}

['vehicle_type', 'rental_type', 'days', 'start_date'].forEach(id => $(id).addEventListener('change', () => { toggleFields(); loadGarages(); }));
$('garage_id').addEventListener('change', estimate);
// biaya listrik ikut berubah saat penyewa mengetik data baterai
['battery_capacity_kwh', 'battery_remaining_kwh'].forEach(n => document.querySelector(`[name="${n}"]`).addEventListener('input', estimate));
toggleFields(); loadGarages();
</script>
@endsection
