@extends('layouts.app')
@section('title', 'Sewa Baru')
@section('content')
<div class="card">
    <h2>Sewa Garasi Baru</h2>
    {{-- Form sewa baru. enctype="multipart/form-data" wajib agar file bukti pembayaran ikut terkirim. --}}
    <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid">
            <div><label>Tipe Kendaraan</label>
                <select name="vehicle_type" id="vehicle_type">
                    @foreach(['sepeda'=>'Sepeda','motor'=>'Sepeda Motor','mobil'=>'Mobil','mobil_listrik'=>'Mobil Listrik'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('vehicle_type')===$k)>{{ $v }}</option>
                    @endforeach
                </select></div>
            {{-- Baris yang disembunyikan/ditampilkan oleh JavaScript (toggleFields): plat nomor tidak dipakai untuk sepeda. --}}
            <div id="plate_row"><label>Nomor Plat</label><input name="plate" value="{{ old('plate') }}"></div>
            <div><label>Merek</label><input name="brand" value="{{ old('brand') }}" required></div>
            <div><label>Tipe/Model</label><input name="model" value="{{ old('model') }}" required></div>
        </div>
        {{-- Data baterai: hanya tampil untuk Mobil Listrik. Batas max 500 kWh sesuai validasi server. --}}
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
            {{-- Jumlah hari hanya untuk sewa harian (maks. 30). Sewa bulanan = 1 bulan kalender penuh. --}}
            <div id="days_row"><label>Jumlah Hari (maks. 30)</label><input type="number" name="days" id="days" min="1" max="30" value="{{ old('days', 1) }}"></div>
            <div><label>Tanggal Mulai</label><input type="date" name="start_date" id="start_date" min="{{ $today }}" value="{{ old('start_date', $today) }}"></div>
        </div>
        <label>Pilih Garasi</label>
        {{-- Dropdown garasi diisi JavaScript (loadGarages) sesuai tipe kendaraan, tipe sewa, dan tanggal. --}}
        <select name="garage_id" id="garage_id" required><option value="">Memuat...</option></select>
        {{-- Estimasi biaya ditampilkan sebelum disimpan; total final dihitung server (termasuk biaya listrik). --}}
        <p id="estimate" style="font-weight:600"></p>
        <label>Bukti Pembayaran (gambar)</label><input type="file" name="payment_proof" accept="image/*" required>
        <button class="btn">Simpan Sewa</button>
    </form>
</div>

<!-- JavaScript halaman: memuat garasi tersedia lewat AJAX dan menghitung estimasi biaya. -->
<script>
// Singkatan untuk document.getElementById.
const $ = id => document.getElementById(id);
// Menyimpan daftar garasi terakhir dari server agar estimasi biaya bisa dihitung tanpa request ulang.
let garages = [];

// Tampilkan/sembunyikan input sesuai pilihan: plat (bukan sepeda), baterai (mobil listrik), jumlah hari (harian).
function toggleFields() {
    $('plate_row').style.display = $('vehicle_type').value === 'sepeda' ? 'none' : '';
    $('ev_rows').style.display = $('vehicle_type').value === 'mobil_listrik' ? '' : 'none';
    $('days_row').style.display = $('rental_type').value === 'harian' ? '' : 'none';
}

// Minta daftar garasi yang cocok ke server (endpoint rentals.available) lalu isi dropdown garasi.
async function loadGarages() {
    const p = new URLSearchParams({
        vehicle_type: $('vehicle_type').value, rental_type: $('rental_type').value,
        days: $('days').value, start_date: $('start_date').value,
    });
    // Request GET dengan header Accept JSON agar Laravel membalas JSON (422 bila input belum valid).
    const res = await fetch('{{ route('rentals.available') }}?' + p, {headers: {'Accept': 'application/json'}});
    const sel = $('garage_id');
    // Input belum valid (mis. jumlah hari kosong): kosongkan pilihan garasi.
    if (!res.ok) { garages = []; sel.innerHTML = '<option value="">Input belum valid</option>'; $('estimate').textContent = ''; return; }
    garages = await res.json();
    // Isi opsi dropdown: "kode - harga/hari", atau pesan bila tidak ada garasi tersedia.
    sel.innerHTML = garages.length
        ? garages.map(g => `<option value="${g.id}">${g.code} - Rp ${g.daily_price.toLocaleString('id-ID')}/hari</option>`).join('')
        : '<option value="">Tidak ada garasi tersedia</option>';
    estimate();
}

// Estimasi biaya (bulanan = 25 x tarif harian, harian = hari x tarif). Biaya listrik dihitung server saat simpan.
function estimate() {
    const g = garages.find(x => x.id == $('garage_id').value);
    if (!g) { $('estimate').textContent = ''; return; }
    const cost = $('rental_type').value === 'bulanan' ? 25 * g.daily_price : $('days').value * g.daily_price;
    $('estimate').textContent = 'Estimasi biaya sewa: Rp ' + cost.toLocaleString('id-ID')
        + (g.type === 'mobil_listrik' ? ' (belum termasuk biaya listrik; total final dihitung saat disimpan)' : '');
}

// Setiap kali salah satu input utama berubah: perbarui tampilan form dan muat ulang garasi tersedia.
['vehicle_type', 'rental_type', 'days', 'start_date'].forEach(id => $(id).addEventListener('change', () => { toggleFields(); loadGarages(); }));
// Ganti pilihan garasi => hitung ulang estimasi.
$('garage_id').addEventListener('change', estimate);
// Inisialisasi saat halaman pertama kali dibuka.
toggleFields(); loadGarages();
</script>
@endsection
