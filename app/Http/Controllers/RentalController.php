<?php

namespace App\Http\Controllers;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\RentalCalculator;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class RentalController extends Controller
{
    public function __construct(private RentalService $service, private RentalCalculator $calc) {}

    // tampilkan riwayat sewa
    public function index(Request $request)
    {
        $rentals = Rental::with(['garage', 'vehicle'])
            ->where('user_id', $request->user()->id)->latest()->paginate(10);
        return view('rentals.index', compact('rentals'));
    }

    // tampilkan page buat sewa baru
    public function create()
    {
        return view('rentals.create', ['today' => CarbonImmutable::today()->toDateString()]);
    }

    // tampilkan daftar garasi yang tersedia sesuai pilihan
    public function availableGarages(Request $request)
    {
        $v = $request->validate([
            'vehicle_type' => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'rental_type'  => ['required', 'in:harian,bulanan'],
            'days'         => ['nullable', 'integer'],
            'start_date'   => ['required', 'date'],
        ]);

        // hitung jumlah hari efektif dan tanggal akhir (dihitung server agar satu sumber kebenaran)
        try {
            $start = CarbonImmutable::parse($v['start_date']);
            $days = $this->calc->effectiveDays($v['rental_type'], $start, $v['days'] ?? null);
            $end = $this->calc->endDate($start, $days);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // balasan: tanggal selesai (untuk ditampilkan ke penyewa) + daftar garasi yang tersedia
        return response()->json([
            'days'     => $days,
            'end_date' => $end->toDateString(),
            'garages'  => $this->service->availableGarages($v['vehicle_type'], $start, $end)
                ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'type' => $g->type, 'daily_price' => $g->daily_price, 'price_per_kwh' => $g->price_per_kwh])
                ->values(),
        ]);
    }

    // validasi data sebelum buat penyewaan
    public function store(Request $request)
    {
        $v = $request->validate([
            'vehicle_type'         => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'plate'                => ['required_unless:vehicle_type,sepeda', 'nullable', 'string', 'max:12'],
            'brand'                => ['required', 'string', 'max:100'],
            'model'                => ['required', 'string', 'max:100'],
            'battery_capacity_kwh' => ['required_if:vehicle_type,mobil_listrik', 'nullable', 'numeric', 'gt:0', 'max:500'],
            'battery_remaining_kwh'=> ['required_if:vehicle_type,mobil_listrik', 'nullable', 'numeric', 'gte:0', 'lte:battery_capacity_kwh'],
            'rental_type'          => ['required', 'in:harian,bulanan'],
            // max:30 hanya batas kasar; batas sebenarnya (jumlah hari bulan mulai - 1) dicek di RentalCalculator::effectiveDays
            'days'                 => ['required_if:rental_type,harian', 'nullable', 'integer', 'min:1', 'max:30'],
            'start_date'           => ['required', 'date'],
            'garage_id'            => ['required', 'exists:garages,id'],
            'payment_proof'        => ['required', 'image', 'max:4096'],
        ]);

        // simpan bukti pembayaran lebih dulu; file dihapus lagi bila sewa ditolak
        $proofPath = $request->file('payment_proof')->store('proofs', 'public');

        try {
            // transaksi: data kendaraan ikut dibatalkan otomatis jika pembuatan sewa gagal
            $rental = DB::transaction(function () use ($request, $v, $proofPath) {
                $vehicle = Vehicle::create([
                    'user_id' => $request->user()->id, 'type' => $v['vehicle_type'],
                    'plate' => $v['plate'] ?? null, 'brand' => $v['brand'], 'model' => $v['model'],
                    'battery_capacity_kwh' => $v['battery_capacity_kwh'] ?? null,
                ]);

                return $this->service->create(
                    $request->user(), $vehicle, Garage::findOrFail($v['garage_id']),
                    $v['rental_type'], isset($v['days']) ? (int) $v['days'] : null,
                    CarbonImmutable::parse($v['start_date']),
                    isset($v['battery_remaining_kwh']) ? (float) $v['battery_remaining_kwh'] : null,
                    $proofPath,
                );
            });
        } catch (InvalidArgumentException $e) {
            // sewa ditolak aturan bisnis: hapus file bukti bayar yang sudah terlanjur tersimpan
            Storage::disk('public')->delete($proofPath);
            return back()->withErrors(['rental' => $e->getMessage()])->withInput();
        }

        return redirect()->route('rentals.index')
            ->with('status', 'Sewa berhasil. Total biaya: Rp ' . number_format($rental->total_cost, 0, ',', '.'));
    }
}
