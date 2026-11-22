<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ExtensionController extends Controller
{
    // mengatur proses perpanjangan sewa
    public function __construct(private RentalService $service) {}

    // garasi yang sedang disewa hari ini
    // tampilan penyewaan yg ada saat ini
    public function index()
    {
        $today = CarbonImmutable::today();
        $rentals = Rental::with(['garage', 'user', 'vehicle'])
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->get();
        return view('admin.extensions.index', compact('rentals'));
    }

    // terima tanggal akhir baru dan bukti pembayaran
    public function store(Request $request, Rental $rental)
    {
        $v = $request->validate([
            'new_end_date'  => ['required', 'date', 'after:' . $rental->end_date->toDateString()],
            'payment_proof' => ['required', 'image', 'max:4096'],
        ]);

        // simpan bukti pembayaran lebih dulu; file dihapus lagi bila perpanjangan ditolak
        $proofPath = $request->file('payment_proof')->store('proofs', 'public');

        // jalankan validasi dan hitung biaya
        try {
            $this->service->extend($request->user(), $rental, CarbonImmutable::parse($v['new_end_date']), $proofPath);
        } catch (InvalidArgumentException $e) {
            Storage::disk('public')->delete($proofPath);
            return back()->withErrors(['extend' => $e->getMessage()]);
        }

        return back()->with('status', 'Perpanjangan berhasil disimpan.');
    }
}
