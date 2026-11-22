{{-- // tampilan admin saat buat garasi --}}
@extends('layouts.app')
@section('title', 'Buat Garasi')
@section('content')
<div class="card" style="max-width:480px">
    <h2>Buat Garasi</h2>
    <form method="POST" action="{{ route('admin.garages.store') }}">
        @csrf
        <label>Tipe Garasi</label>
        <select name="type" id="type">
            <option value="sepeda">Garasi Sepeda (S)</option>
            <option value="mobil">Garasi Mobil (C)</option>
            <option value="mobil_listrik">Garasi Mobil Listrik (E)</option>
        </select>
        <label>Jumlah Garasi (maks. 999)</label><input type="number" name="count" min="1" max="999" value="{{ old('count', 1) }}" required>
        <label>Harga Sewa Harian (Rp)</label><input type="number" name="daily_price" min="1" value="{{ old('daily_price') }}" required>
        <div id="kwh_row" style="display:none"><label>Harga Listrik per kWh (Rp)</label><input type="number" name="price_per_kwh" min="1" value="{{ old('price_per_kwh') }}"></div>
        <button class="btn">Buat</button>
    </form>
</div>
<script>
// tampilkan tarif listrik kalau dipilih mobil listrik
const t = document.getElementById('type');
const toggle = () => document.getElementById('kwh_row').style.display = t.value === 'mobil_listrik' ? '' : 'none';
t.addEventListener('change', toggle); toggle();
</script>
@endsection
