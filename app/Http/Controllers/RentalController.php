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

class RentalController extends Controller
{
    public function __construct(private RentalService $service, private RentalCalculator $calc) {}

    public function index(Request $request)
    {
        $rentals = Rental::with(['garage', 'vehicle'])
            ->where('user_id', $request->user()->id)->latest()->paginate(10);
        return view('rentals.index', compact('rentals'));
    }

    public function create()
    {
        return view('rentals.create', ['today' => CarbonImmutable::today()->toDateString()]);
    }

    /** Daftar garasi tersedia (dipanggil via query string agar form dapat memuat ulang pilihan). */
    public function availableGarages(Request $request)
    {
        $v = $request->validate([
            'vehicle_type' => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'rental_type'  => ['required', 'in:harian,bulanan'],
            'days'         => ['nullable', 'integer'],
            'start_date'   => ['required', 'date'],
        ]);

        try {
            $start = CarbonImmutable::parse($v['start_date']);
            $end = $this->calc->endDate($start, $this->calc->effectiveDays($v['rental_type'], $start, $v['days'] ?? null));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->service->availableGarages($v['vehicle_type'], $start, $end)
            ->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'type' => $g->type, 'daily_price' => $g->daily_price]));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'vehicle_type'         => ['required', 'in:sepeda,motor,mobil,mobil_listrik'],
            'plate'                => ['required_unless:vehicle_type,sepeda', 'nullable', 'string', 'max:12'],
            'brand'                => ['required', 'string', 'max:100'],
            'model'                => ['required', 'string', 'max:100'],
            'battery_capacity_kwh' => ['required_if:vehicle_type,mobil_listrik', 'nullable', 'numeric', 'gt:0'],
            'battery_remaining_kwh'=> ['nullable', 'numeric', 'gte:0'],
            'rental_type'          => ['required', 'in:harian,bulanan'],
            'days'                 => ['required_if:rental_type,harian', 'nullable', 'integer', 'min:1', 'max:30'],
            'start_date'           => ['required', 'date'],
            'garage_id'            => ['required', 'exists:garages,id'],
            'payment_proof'        => ['required', 'image', 'max:4096'],
        ]);

        try {
            $vehicle = Vehicle::create([
                'user_id' => $request->user()->id, 'type' => $v['vehicle_type'],
                'plate' => $v['plate'] ?? null, 'brand' => $v['brand'], 'model' => $v['model'],
                'battery_capacity_kwh' => $v['battery_capacity_kwh'] ?? null,
            ]);

            $rental = $this->service->create(
                $request->user(), $vehicle, Garage::findOrFail($v['garage_id']),
                $v['rental_type'], isset($v['days']) ? (int) $v['days'] : null,
                CarbonImmutable::parse($v['start_date']),
                isset($v['battery_remaining_kwh']) ? (float) $v['battery_remaining_kwh'] : null,
                $request->file('payment_proof')->store('proofs', 'public'),
            );
        } catch (InvalidArgumentException $e) {
            isset($vehicle) && $vehicle->delete();
            return back()->withErrors(['rental' => $e->getMessage()])->withInput();
        }

        return redirect()->route('rentals.index')
            ->with('status', 'Sewa berhasil. Total biaya: Rp ' . number_format($rental->total_cost, 0, ',', '.'));
    }
}
