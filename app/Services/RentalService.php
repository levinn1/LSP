<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RentalService
{
    // gunakan rentalcalculator utk hitung tanggal dan biaya sewa
    public function __construct(private RentalCalculator $calc) {}

    // cek apakah sudah ada penyewaan di tanggal yang dipilih
    public function isAvailable(int $garageId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreRentalId = null): bool
    {
        return !Rental::where('garage_id', $garageId)
            ->when($ignoreRentalId, fn ($q) => $q->where('id', '!=', $ignoreRentalId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    // ambil garasi sesuai dengan jenis kendaraan yang ada di tanggal yang dipilih
    public function availableGarages(string $vehicleType, CarbonImmutable $start, CarbonImmutable $end)
    {
        $types = RentalCalculator::COMPATIBILITY[$vehicleType] ?? [];

        return Garage::whereIn('type', $types)
            ->whereDoesntHave('rentals', fn ($q) => $q
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start))
            ->orderBy('code')
            ->get();
    }

    // cek data penyewa dan kendaraan, menghitung biaya, dan buat transaksi sewa
    public function create(User $user, Vehicle $vehicle, Garage $garage, string $type, ?int $days,
                           CarbonImmutable $start, ?float $batteryRemaining, string $paymentProof): Rental
    {
        if ($user->role !== 'penyewa' || $user->verification_status !== 'accepted') {
            throw new InvalidArgumentException('Hanya penyewa terverifikasi yang dapat menyewa.');
        }
        if (!$this->calc->isCompatible($vehicle->type, $garage->type)) {
            throw new InvalidArgumentException('Tipe kendaraan tidak sesuai dengan tipe garasi.');
        }
        $this->calc->assertStartDateValid($start, CarbonImmutable::today());

        $effective = $this->calc->effectiveDays($type, $start, $days);
        $end = $this->calc->endDate($start, $effective);
        $cost = $this->calc->rentalCost($type, $effective, $garage->daily_price);

        // kalau pilih mobil listrik minta isi data baterai
        $kwh = null;
        if ($garage->type === 'mobil_listrik') {
            if ($batteryRemaining === null || $vehicle->battery_capacity_kwh === null) {
                throw new InvalidArgumentException('Data baterai wajib diisi untuk Garasi Mobil Listrik.');
            }
            $kwh = $this->calc->kwhToCharge((float) $vehicle->battery_capacity_kwh, $batteryRemaining);
            $cost += $this->calc->chargingCost($kwh, $garage->price_per_kwh);
        }

        // simpan transaksi sewa dan kunci garasi agar tidak dipesan bersamaan
        return DB::transaction(function () use ($user, $vehicle, $garage, $type, $effective, $start, $end, $batteryRemaining, $kwh, $cost, $paymentProof) {
            Garage::whereKey($garage->id)->lockForUpdate()->first();

            if (!$this->isAvailable($garage->id, $start, $end)) {
                throw new InvalidArgumentException('Garasi sudah disewa pada rentang tanggal tersebut.');
            }

            return Rental::create([
                'user_id' => $user->id, 'garage_id' => $garage->id, 'vehicle_id' => $vehicle->id,
                'rental_type' => $type, 'days' => $effective,
                'start_date' => $start, 'end_date' => $end,
                'battery_remaining_kwh' => $batteryRemaining, 'kwh_to_charge' => $kwh,
                'total_cost' => $cost, 'payment_proof' => $paymentProof,
            ]);
        });
    }

    // validasi admin, garasi yang tersedia, biaya tambahan,  lalu perbarui data perpanjangan sewa
    public function extend(User $admin, Rental $rental, CarbonImmutable $newEnd, string $paymentProof): RentalExtension
    {
        if ($admin->role !== 'admin') {
            throw new InvalidArgumentException('Hanya admin yang dapat memperpanjang sewa.');
        }
        $oldEnd = CarbonImmutable::parse($rental->end_date);
        $cost = $this->calc->extensionCost($oldEnd, $newEnd, $rental->garage->daily_price);

        return DB::transaction(function () use ($admin, $rental, $oldEnd, $newEnd, $cost, $paymentProof) {
            Garage::whereKey($rental->garage_id)->lockForUpdate()->first();

            if (!$this->isAvailable($rental->garage_id, $oldEnd->addDay(), $newEnd, $rental->id)) {
                throw new InvalidArgumentException('Garasi sudah dipesan pada periode perpanjangan.');
            }

            $rental->update(['end_date' => $newEnd, 'total_cost' => $rental->total_cost + $cost]);

            return RentalExtension::create([
                'rental_id' => $rental->id, 'admin_id' => $admin->id,
                'old_end_date' => $oldEnd, 'new_end_date' => $newEnd,
                'additional_cost' => $cost, 'payment_proof' => $paymentProof,
            ]);
        });
    }
}
