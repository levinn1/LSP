<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Controller admin untuk master garasi: daftar, buat massal, ubah, dan hapus.
 */
class GarageController extends Controller
{
    /** Huruf awal kode ID yang wajib dipakai untuk setiap tipe garasi. */
    private const CODE_PREFIX = ['sepeda' => 'S', 'mobil' => 'C', 'mobil_listrik' => 'E'];

    // GarageCodeGenerator disuntikkan otomatis (dependency injection).
    public function __construct(private GarageCodeGenerator $generator) {}

    // Halaman daftar (25 per halaman), form buat, dan form edit.
    public function index() { return view('admin.garages.index', ['garages' => Garage::orderBy('code')->paginate(25)]); }
    public function create() { return view('admin.garages.create'); }
    public function edit(Garage $garage) { return view('admin.garages.edit', ['garage' => $garage]); }

    /** Membuat garasi secara massal: kode dibuat otomatis dan melanjutkan nomor terakhir tipe tersebut. */
    public function store(Request $request)
    {
        $v = $request->validate([
            'type'          => ['required', 'in:sepeda,mobil,mobil_listrik'],
            'count'         => ['required', 'integer', 'min:1', 'max:999'],
            'daily_price'   => ['required', 'integer', 'min:1'],
            // Harga per kWh wajib hanya untuk Garasi Mobil Listrik.
            'price_per_kwh' => ['required_if:type,mobil_listrik', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            // Transaksi: jika satu garasi gagal dibuat, seluruh batch dibatalkan (tidak ada data setengah jadi).
            DB::transaction(function () use ($v) {
                // Cari nomor terbesar dari kode yang sudah ada untuk tipe ini (mis. C009 => 9); 0 jika belum ada.
                $last = Garage::where('type', $v['type'])->lockForUpdate()->get()
                    ->map(fn ($g) => (int) substr($g->code, 1))->max() ?? 0;

                // Buat satu baris garasi untuk setiap kode baru; harga kWh dikosongkan bila bukan mobil listrik.
                foreach ($this->generator->generate($v['type'], (int) $v['count'], $last) as $code) {
                    Garage::create(['code' => $code, 'type' => $v['type'], 'daily_price' => $v['daily_price'],
                        'price_per_kwh' => $v['type'] === 'mobil_listrik' ? $v['price_per_kwh'] : null]);
                }
            });
        } catch (InvalidArgumentException $e) {
            // Mis. total kode melebihi 999: tampilkan pesan di kolom jumlah.
            return back()->withErrors(['count' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.garages.index')->with('status', $v['count'] . ' garasi berhasil dibuat.');
    }

    /**
     * Mengubah seluruh data satu garasi (kode, tipe, harga harian, harga per kWh).
     * Route Model Binding: {garage} pada URL otomatis diubah menjadi objek Garage.
     */
    public function update(Request $request, Garage $garage)
    {
        $v = $request->validate([
            // Kode harus berformat S###/C###/E### dan unik (garasi ini sendiri dikecualikan dari pengecekan unik).
            'code'          => ['required', 'regex:/^[SCE][0-9]{3}$/', Rule::unique('garages', 'code')->ignore($garage->id)],
            'type'          => ['required', 'in:sepeda,mobil,mobil_listrik'],
            'daily_price'   => ['required', 'integer', 'min:1'],
            'price_per_kwh' => ['required_if:type,mobil_listrik', 'nullable', 'integer', 'min:1'],
        ]);

        // Huruf awal kode harus sesuai tipe (S = sepeda, C = mobil, E = mobil listrik).
        if ($v['code'][0] !== self::CODE_PREFIX[$v['type']]) {
            return back()->withErrors(['code' => 'Kode untuk tipe ini harus diawali huruf ' . self::CODE_PREFIX[$v['type']] . '.'])->withInput();
        }

        // Tipe tidak boleh diganti bila garasi sudah punya riwayat sewa (kendaraan bisa jadi tidak cocok lagi).
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

    /** Menghapus garasi; ditolak bila sudah punya riwayat sewa agar data laporan tetap utuh. */
    public function destroy(Garage $garage)
    {
        if ($garage->rentals()->exists()) {
            return back()->withErrors(['delete' => 'Garasi ' . $garage->code . ' tidak dapat dihapus karena sudah memiliki riwayat sewa.']);
        }

        $garage->delete();

        return redirect()->route('admin.garages.index')->with('status', 'Garasi ' . $garage->code . ' berhasil dihapus.');
    }
}
