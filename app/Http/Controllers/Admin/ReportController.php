<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // filter riwayat sewa utk admin.
    public function index(Request $request)
    {
        $f = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
            'garage_type' => ['nullable', 'in:sepeda,mobil,mobil_listrik'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        // eager loading untuk hindari query berulang
        $rentals = Rental::with(['garage', 'user', 'vehicle'])
            ->when($f['year'] ?? null, fn ($q, $v) => $q->whereYear('start_date', $v))
            ->when($f['month'] ?? null, fn ($q, $v) => $q->whereMonth('start_date', $v))
            ->when($f['day'] ?? null, fn ($q, $v) => $q->whereDay('start_date', $v))
            ->when($f['garage_type'] ?? null, fn ($q, $v) => $q->whereHas('garage', fn ($g) => $g->where('type', $v)))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->orderBy('start_date')->paginate(20)->withQueryString();

        return view('admin.reports.index', [
            'rentals' => $rentals, 'total' => $rentals->getCollection()->sum('total_cost'),
            'users' => User::where('role', 'penyewa')->orderBy('name')->get(), 'filters' => $f,
            'years' => $this->availableYears(),
        ]);
    }

    // daftar tahun yang ada garasi disewa
    private function availableYears(): array
    {
        return Rental::query()->distinct()->pluck('start_date')
            ->map(fn ($d) => (int) $d->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()->sortDesc()->values()->all();
    }
}
