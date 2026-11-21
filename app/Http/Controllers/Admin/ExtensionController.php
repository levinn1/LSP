<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExtensionController extends Controller
{
    public function __construct(private RentalService $service) {}

    /** Garasi yang sedang disewa hari ini. */
    public function index()
    {
        $today = CarbonImmutable::today();
        $rentals = Rental::with(['garage', 'user', 'vehicle'])
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->get();
        return view('admin.extensions.index', compact('rentals'));
    }

    public function store(Request $request, Rental $rental)
    {
        $v = $request->validate([
            'new_end_date'  => ['required', 'date', 'after:' . $rental->end_date->toDateString()],
            'payment_proof' => ['required', 'image', 'max:4096'],
        ]);

        try {
            $this->service->extend($request->user(), $rental, CarbonImmutable::parse($v['new_end_date']),
                $request->file('payment_proof')->store('proofs', 'public'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['extend' => $e->getMessage()]);
        }

        return back()->with('status', 'Perpanjangan berhasil disimpan.');
    }
}
