<?php

namespace App\Http\Controllers;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\RentalCalculator;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Controller penyewa: riwayat sewa, form sewa baru, dan penyimpanan sewa.
 * Controller dibuat tipis; aturan bisnis ada di RentalService dan RentalCalculator.
 */
class RentalController extends Controller
{
    public function __construct(private RentalService $service, private RentalCalculator $calc) {}

    /** Riwayat sewa milik penyewa yang sedang login (10 per halaman, terbaru dulu). */
    public function index(Request $request)
    {
        // with(): eager loading agar tidak terjadi query berulang (masalah N+1).
        $rentals = Rental::with(['garage', 'vehicle'])
            ->where('user_id', $request->user()->id)->latest()->paginate(10);
        return view('rentals.index', compact('rentals'));
    }

    /** Menampilkan form sewa baru; tanggal mulai default hari ini. */
    public function create()
    {
        return view('rentals.create', ['today' => CarbonImmutable::today()->toDateString()]);
    }

    /**
     * Endpoint JSON: daftar garasi yang tersedia untuk tipe kendaraan & rentang tanggal tertentu.
     * Dipanggil lewat JavaScript (fetch) setiap kali input pada form sewa berubah.
     */
    public function availableGarages(Request $request)
    {
        $v = $request->validate([
            'vehicle_type' => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'rental_type'  => ['required', 'in:harian,bulanan'],
            'days'         => ['nullable', 'integer'],
            'start_date'   => ['required', 'date'],
        ]);

        try {
            // Hitung tanggal selesai dari jumlah hari; input tidak valid dibalas HTTP 422 berisi pesan error.
            $start = CarbonImmutable::parse($v['start_date']);
            $end = $this->calc->endDate($start, $this->calc->effectiveDays($v['rental_type'], $start, $v['days'] ?? null));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Hanya kirim kolom yang dibutuhkan JavaScript.
        return response()->json($this->service->availableGarages($v['vehicle_type'], $start, $end)
            ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'type' => $g->type, 'daily_price' => $g->daily_price, 'price_per_kwh' => $g->price_per_kwh]));
    }

    /** Menyimpan sewa baru: validasi form, simpan kendaraan, lalu buat sewa lewat RentalService. */
    public function store(Request $request)
    {
        // Aturan validasi: plat wajib kecuali sepeda; baterai wajib untuk mobil listrik
        // (kapasitas maks. 500 kWh, sisa <= kapasitas); hari 1..30 untuk sewa harian;
        // bukti pembayaran harus berupa gambar (maks. 4 MB).
        $v = $request->validate([
            'vehicle_type'         => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'plate'                => ['required_unless:vehicle_type,sepeda', 'nullable', 'string', 'max:12'],
            'brand'                => ['required', 'string', 'max:100'],
            'model'                => ['required', 'string', 'max:100'],
            'battery_capacity_kwh' => ['required_if:vehicle_type,mobil_listrik', 'nullable', 'numeric', 'gt:0', 'max:500'],
            'battery_remaining_kwh'=> ['required_if:vehicle_type,mobil_listrik', 'nullable', 'numeric', 'gte:0', 'lte:battery_capacity_kwh'],
            'rental_type'          => ['required', 'in:harian,bulanan'],
            'days'                 => ['required_if:rental_type,harian', 'nullable', 'integer', 'min:1', 'max:30'],
            'start_date'           => ['required', 'date'],
            'garage_id'            => ['required', 'exists:garages,id'],
            'payment_proof'        => ['required', 'image', 'max:4096'],
        ]);

        try {
            // Simpan data kendaraan lebih dulu (tiap sewa membuat data kendaraan baru).
            $vehicle = Vehicle::create([
                'user_id' => $request->user()->id, 'type' => $v['vehicle_type'],
                'plate' => $v['plate'] ?? null, 'brand' => $v['brand'], 'model' => $v['model'],
                'battery_capacity_kwh' => $v['battery_capacity_kwh'] ?? null,
            ]);

            // Buat sewa; bukti pembayaran disimpan ke storage/app/public/proofs (diakses lewat storage:link).
            $rental = $this->service->create(
                $request->user(), $vehicle, Garage::findOrFail($v['garage_id']),
                $v['rental_type'], isset($v['days']) ? (int) $v['days'] : null,
                CarbonImmutable::parse($v['start_date']),
                isset($v['battery_remaining_kwh']) ? (float) $v['battery_remaining_kwh'] : null,
                $request->file('payment_proof')->store('proofs', 'public'),
            );
        } catch (InvalidArgumentException $e) {
            // Pelanggaran aturan bisnis: batalkan data kendaraan yang sudah terlanjur dibuat, tampilkan pesan error.
            isset($vehicle) && $vehicle->delete();
            return back()->withErrors(['rental' => $e->getMessage()])->withInput();
        }

        return redirect()->route('rentals.index')
            ->with('status', 'Sewa berhasil. Total biaya: Rp ' . number_format($rental->total_cost, 0, ',', '.'));
    }
}
