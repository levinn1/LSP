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

/**
 * Service use case penyewaan: validasi aturan bisnis, cek bentrok jadwal, lalu simpan.
 *
 * Lapisan ini menjembatani controller (HTTP) dan model (database). Perhitungan biaya/tanggal
 * didelegasikan ke RentalCalculator agar logika murni terpisah dari akses database.
 */
class RentalService
{
    // RentalCalculator disuntikkan otomatis oleh service container Laravel (dependency injection).
    public function __construct(private RentalCalculator $calc) {}

    /**
     * Cek apakah garasi bebas pada rentang $start s.d. $end.
     * Bentrok bila ada sewa lain yang rentangnya beririsan: mulai <= $end DAN selesai >= $start.
     * $ignoreRentalId dipakai saat perpanjangan agar sewa itu sendiri tidak dihitung bentrok.
     */
    public function isAvailable(int $garageId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreRentalId = null): bool
    {
        return !Rental::where('garage_id', $garageId)
            ->when($ignoreRentalId, fn ($q) => $q->where('id', '!=', $ignoreRentalId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    /**
     * Garasi yang cocok dengan tipe kendaraan dan kosong pada rentang tanggal.
     * Dipakai untuk mengisi dropdown "Pilih Garasi" di form sewa.
     */
    public function availableGarages(string $vehicleType, CarbonImmutable $start, CarbonImmutable $end)
    {
        // Tipe garasi yang boleh dipakai kendaraan ini (mis. mobil listrik => mobil & mobil_listrik).
        $types = RentalCalculator::COMPATIBILITY[$vehicleType] ?? [];

        return Garage::whereIn('type', $types)
            // Buang garasi yang sudah punya sewa beririsan dengan rentang yang diminta.
            ->whereDoesntHave('rentals', fn ($q) => $q
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start))
            ->orderBy('code')
            ->get();
    }

    /**
     * Use case "Sewa Baru": validasi, hitung biaya, lalu simpan sewa.
     *
     * @throws InvalidArgumentException bila aturan bisnis dilanggar (akun belum diverifikasi,
     *         kendaraan tidak cocok, tanggal mundur, data baterai kurang, garasi bentrok)
     */
    public function create(User $user, Vehicle $vehicle, Garage $garage, string $type, ?int $days,
                           CarbonImmutable $start, ?float $batteryRemaining, string $paymentProof): Rental
    {
        // Aturan 1: hanya penyewa yang sudah diterima admin yang boleh menyewa.
        if ($user->role !== 'penyewa' || $user->verification_status !== 'accepted') {
            throw new InvalidArgumentException('Hanya penyewa terverifikasi yang dapat menyewa.');
        }
        // Aturan 2: tipe kendaraan harus cocok dengan tipe garasi.
        if (!$this->calc->isCompatible($vehicle->type, $garage->type)) {
            throw new InvalidArgumentException('Tipe kendaraan tidak sesuai dengan tipe garasi.');
        }
        // Aturan 3: tanggal mulai tidak boleh mundur dari hari ini.
        $this->calc->assertStartDateValid($start, CarbonImmutable::today());

        // Hitung jumlah hari efektif, tanggal selesai (otomatis), dan biaya sewa dasar.
        $effective = $this->calc->effectiveDays($type, $start, $days);
        $end = $this->calc->endDate($start, $effective);
        $cost = $this->calc->rentalCost($type, $effective, $garage->daily_price);

        // Biaya listrik hanya berlaku untuk mobil listrik di Garasi Mobil Listrik (wajib isi daya sampai penuh).
        $kwh = null;
        if ($garage->type === 'mobil_listrik') {
            if ($batteryRemaining === null || $vehicle->battery_capacity_kwh === null) {
                throw new InvalidArgumentException('Data baterai wajib diisi untuk Garasi Mobil Listrik.');
            }
            $kwh = $this->calc->kwhToCharge((float) $vehicle->battery_capacity_kwh, $batteryRemaining);
            $cost += $this->calc->chargingCost($kwh, $garage->price_per_kwh);
        }

        // Transaksi database: kunci baris garasi, cek bentrok sekali lagi, baru simpan.
        // Ini mencegah dua penyewa memesan garasi yang sama pada waktu bersamaan (race condition).
        return DB::transaction(function () use ($user, $vehicle, $garage, $type, $effective, $start, $end, $batteryRemaining, $kwh, $cost, $paymentProof) {
            // lockForUpdate: transaksi lain yang menyewa garasi yang sama menunggu sampai transaksi ini selesai.
            Garage::whereKey($garage->id)->lockForUpdate()->first();

            // Cek ulang ketersediaan di dalam kunci (data bisa berubah sejak form dimuat).
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

    /**
     * Use case "Perpanjang Sewa" (khusus admin): ubah tanggal selesai dan tambah total biaya.
     * Biaya tambahan dihitung RentalCalculator::extensionCost() dan dicatat di rental_extensions.
     *
     * @throws InvalidArgumentException bila bukan admin atau garasi sudah dipesan pada periode perpanjangan
     */
    public function extend(User $admin, Rental $rental, CarbonImmutable $newEnd, string $paymentProof): RentalExtension
    {
        // Hanya admin yang boleh memperpanjang (penyewa tidak bisa memperpanjang sendiri).
        if ($admin->role !== 'admin') {
            throw new InvalidArgumentException('Hanya admin yang dapat memperpanjang sewa.');
        }
        // Biaya tambahan dihitung dari tarif harian garasi dan selisih tanggal selesai lama -> baru.
        $oldEnd = CarbonImmutable::parse($rental->end_date);
        $cost = $this->calc->extensionCost($oldEnd, $newEnd, $rental->garage->daily_price);

        // Transaksi: periode perpanjangan harus bebas dari sewa lain, lalu update sewa + catat perpanjangan.
        return DB::transaction(function () use ($admin, $rental, $oldEnd, $newEnd, $cost, $paymentProof) {
            Garage::whereKey($rental->garage_id)->lockForUpdate()->first();

            // Periode yang dicek: sehari setelah tanggal selesai lama s.d. tanggal selesai baru (sewa ini diabaikan).
            if (!$this->isAvailable($rental->garage_id, $oldEnd->addDay(), $newEnd, $rental->id)) {
                throw new InvalidArgumentException('Garasi sudah dipesan pada periode perpanjangan.');
            }

            // Sewa asli diperbarui: tanggal selesai baru dan total biaya ditambah biaya perpanjangan.
            $rental->update(['end_date' => $newEnd, 'total_cost' => $rental->total_cost + $cost]);

            // Catat riwayat perpanjangan (siapa admin-nya, tanggal lama/baru, biaya, bukti bayar).
            return RentalExtension::create([
                'rental_id' => $rental->id, 'admin_id' => $admin->id,
                'old_end_date' => $oldEnd, 'new_end_date' => $newEnd,
                'additional_cost' => $cost, 'payment_proof' => $paymentProof,
            ]);
        });
    }
}
