<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GarageController extends Controller
{
    public function __construct(private GarageCodeGenerator $generator) {}

    public function index() { return view('admin.garages.index', ['garages' => Garage::orderBy('code')->paginate(25)]); }
    public function create() { return view('admin.garages.create'); }

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
}
