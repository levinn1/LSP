<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class GarageController extends Controller
{
    // kode ID untuk tiap tipe garasi
    private const CODE_PREFIX = ['sepeda' => 'S', 'mobil' => 'C', 'mobil_listrik' => 'E'];

    public function __construct(private GarageCodeGenerator $generator) {}

    // tampilkan daftar garasi
    public function index() { return view('admin.garages.index', ['garages' => Garage::orderBy('code')->paginate(25)]); }
    // tampilkan create garasi
    public function create() { return view('admin.garages.create'); }
    // tampilkan edit garasi
    public function edit(Garage $garage) { return view('admin.garages.edit', ['garage' => $garage]); }

    // validasi data dan buat garasi berurutan berdasarkan kode
    public function store(Request $request)
    {
        $v = $request->validate([
            'type'          => ['required', 'in:sepeda,mobil,mobil_listrik'],
            'count'         => ['required', 'integer', 'min:1', 'max:999'],
            'daily_price'   => ['required', 'integer', 'min:1'],
            'price_per_kwh' => ['required_if:type,mobil_listrik', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            DB::transaction(function () use ($v) {
                // Kunci data spya nomor kode tidak dibaca bersamaan dengan transaksi lain
                $last = Garage::where('type', $v['type'])->lockForUpdate()->get()
                    ->map(fn ($g) => (int) substr($g->code, 1))->max() ?? 0;

                foreach ($this->generator->generate($v['type'], (int) $v['count'], $last) as $code) {
                    Garage::create(['code' => $code, 'type' => $v['type'], 'daily_price' => $v['daily_price'],
                        'price_per_kwh' => $v['type'] === 'mobil_listrik' ? $v['price_per_kwh'] : null]);
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['count' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.garages.index')->with('status', $v['count'] . ' garasi berhasil dibuat.');
    }

    // ubah data garasi
    public function update(Request $request, Garage $garage)
    {
        $v = $request->validate([
            'code'          => ['required', 'regex:/^[SCE][0-9]{3}$/', Rule::unique('garages', 'code')->ignore($garage->id)],
            'type'          => ['required', 'in:sepeda,mobil,mobil_listrik'],
            'daily_price'   => ['required', 'integer', 'min:1'],
            'price_per_kwh' => ['required_if:type,mobil_listrik', 'nullable', 'integer', 'min:1'],
        ]);

        if ($v['code'][0] !== self::CODE_PREFIX[$v['type']]) {
            return back()->withErrors(['code' => 'Kode untuk tipe ini harus diawali huruf ' . self::CODE_PREFIX[$v['type']] . '.'])->withInput();
        }

        if ($v['type'] !== $garage->type && $garage->rentals()->exists()) {
            return back()->withErrors(['type' => 'Tipe garasi tidak dapat diubah karena garasi sudah memiliki riwayat sewa.'])->withInput();
        }

        $garage->update([
            'code'          => $v['code'],
            'type'          => $v['type'],
            'daily_price'   => $v['daily_price'],
            'price_per_kwh' => $v['type'] === 'mobil_listrik' ? $v['price_per_kwh'] : null,
        ]);

        return redirect()->route('admin.garages.index')->with('status', 'Garasi ' . $garage->code . ' berhasil diperbarui.');
    }

    // hapus garasi
    // cmn bsa hapus garasi kalau sedang tidak disewa atau belum pernah disewa
    public function destroy(Garage $garage)
    {
        if ($garage->rentals()->exists()) {
            return back()->withErrors(['delete' => 'Garasi ' . $garage->code . ' tidak dapat dihapus karena sudah memiliki riwayat sewa.']);
        }

        $garage->delete();

        return redirect()->route('admin.garages.index')->with('status', 'Garasi ' . $garage->code . ' berhasil dihapus.');
    }
}
