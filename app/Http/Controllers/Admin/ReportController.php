<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Controller admin: laporan penyewaan dengan filter tahun, bulan, hari, tipe garasi, dan penyewa.
 */
class ReportController extends Controller
{
    /**
     * Filter opsional: year, month, day, garage_type, user_id (berdasarkan tanggal mulai sewa).
     * Filter yang dikosongkan diabaikan, sehingga semua kombinasi filter dapat dipakai bersamaan.
     */
    public function index(Request $request)
    {
        $f = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
            'garage_type' => ['nullable', 'in:sepeda,mobil,mobil_listrik'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        // when(): kondisi hanya ditambahkan ke query bila nilai filternya diisi.
        $rentals = Rental::with(['garage', 'user', 'vehicle'])
            ->when($f['year'] ?? null, fn ($q, $v) => $q->whereYear('start_date', $v))
            ->when($f['month'] ?? null, fn ($q, $v) => $q->whereMonth('start_date', $v))
            ->when($f['day'] ?? null, fn ($q, $v) => $q->whereDay('start_date', $v))
            ->when($f['garage_type'] ?? null, fn ($q, $v) => $q->whereHas('garage', fn ($g) => $g->where('type', $v)))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            // withQueryString(): filter tetap terbawa saat pindah halaman (pagination).
            ->orderBy('start_date')->paginate(20)->withQueryString();

        return view('admin.reports.index', [
            // Data untuk view: hasil sewa, total biaya halaman ini, daftar penyewa (dropdown), filter aktif, daftar tahun.
            'rentals' => $rentals, 'total' => $rentals->getCollection()->sum('total_cost'),
            'users' => User::where('role', 'penyewa')->orderBy('name')->get(), 'filters' => $f,
            'years' => $this->availableYears(),
        ]);
    }

    /** Daftar tahun untuk dropdown: tahun yang ada di data sewa, ditambah tahun berjalan (urut terbaru). */
    private function availableYears(): array
    {
        return Rental::query()->distinct()->pluck('start_date')
            ->map(fn ($d) => (int) $d->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()->sortDesc()->values()->all();
    }
}
