@extends('layouts.app')
@section('title', 'Edit Garasi')
@section('content')
<div class="card" style="max-width:480px">
    <h2>Edit Garasi {{ $garage->code }}</h2>
    {{-- Form edit garasi. HTML hanya mengenal GET/POST, jadi @method('PUT') memalsukan metode PUT untuk rute update. --}}
    <form method="POST" action="{{ route('admin.garages.update', $garage) }}">
        @csrf @method('PUT')
        <label>Kode Garasi (S### / C### / E###)</label>
        <input type="text" name="code" maxlength="4" value="{{ old('code', $garage->code) }}" required>
        <label>Tipe Garasi</label>
        <select name="type" id="type">
            @foreach(['sepeda' => 'Garasi Sepeda (S)', 'mobil' => 'Garasi Mobil (C)', 'mobil_listrik' => 'Garasi Mobil Listrik (E)'] as $val => $label)
                <option value="{{ $val }}" @selected(old('type', $garage->type) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <label>Harga Sewa Harian (Rp)</label>
        <input type="number" name="daily_price" min="1" value="{{ old('daily_price', $garage->daily_price) }}" required>
        <div id="kwh_row" style="display:none">
            <label>Harga Listrik per kWh (Rp)</label>
            <input type="number" name="price_per_kwh" min="1" value="{{ old('price_per_kwh', $garage->price_per_kwh) }}">
        </div>
        <button class="btn">Simpan</button>
        <a class="btn red" href="{{ route('admin.garages.index') }}">Batal</a>
    </form>

    {{-- Form hapus dipisah (di luar form edit) karena HTML tidak membolehkan form di dalam form. confirm() meminta persetujuan dulu. --}}
    <hr style="margin:20px 0;border:0;border-top:1px solid #e5e7eb">

    <form method="POST" action="{{ route('admin.garages.destroy', $garage) }}"
          onsubmit="return confirm('Hapus garasi {{ $garage->code }}? Tindakan ini tidak dapat dibatalkan.')">
        @csrf @method('DELETE')
        <button class="btn red" style="margin-top:0">Hapus Garasi</button>
    </form>
</div>
<!-- JavaScript: tampilkan input harga kWh hanya bila tipe = Garasi Mobil Listrik. -->
<script>
const t = document.getElementById('type');
const toggle = () => document.getElementById('kwh_row').style.display = t.value === 'mobil_listrik' ? '' : 'none';
t.addEventListener('change', toggle); toggle();
</script>
@endsection
