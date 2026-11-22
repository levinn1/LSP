<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RentalCalculator
{
    public const MONTHLY_MULTIPLIER = 25;
    public const MAX_DAILY_DAYS = 30;

    // tipe garasi yang boleh disewa berdasarkan kendaraan
    public const COMPATIBILITY = [
        'sepeda'        => ['sepeda'],
        'motor'         => ['sepeda'],
        'mobil'         => ['mobil'],
        'mobil_listrik' => ['mobil', 'mobil_listrik'],
    ];

    // cek apakah garasi yang dipilih sesuai dengan kendaraan
    public function isCompatible(string $vehicleType, string $garageType): bool
    {
        return in_array($garageType, self::COMPATIBILITY[$vehicleType] ?? [], true);
    }

    // tolak kalau tanggal mulai sebelum tanggal hari ini
    public function assertStartDateValid(CarbonImmutable $start, CarbonImmutable $today): void
    {
        if ($start->startOfDay()->lt($today->startOfDay())) {
            throw new InvalidArgumentException('Tanggal sewa tidak boleh mundur.');
        }
    }

    // hitung jumlah hari sewa sesuai jenis sewa
    public function effectiveDays(string $type, CarbonImmutable $start, ?int $days): int
    {
        if ($type === 'bulanan') {
            return $start->daysInMonth;
        }
        if ($days === null || $days < 1 || $days > self::MAX_DAILY_DAYS) {
            throw new InvalidArgumentException('Sewa harian harus 1 sampai ' . self::MAX_DAILY_DAYS . ' hari.');
        }
        return $days;
    }

    // tentukan tanggal akhir sewa berdasarkan tanggal mulai dan jumlah hari sewa
    public function endDate(CarbonImmutable $start, int $effectiveDays): CarbonImmutable
    {
        return $start->addDays($effectiveDays - 1);
    }

    // hitung biaya sewa
    public function rentalCost(string $type, int $effectiveDays, int $dailyPrice): int
    {
        return $type === 'bulanan'
            ? self::MONTHLY_MULTIPLIER * $dailyPrice
            : $effectiveDays * $dailyPrice;
    }

    // hitung listrik yang harus diisi
    public function kwhToCharge(float $capacity, float $remaining): float
    {
        if ($remaining < 0 || $remaining > $capacity) {
            throw new InvalidArgumentException('Sisa baterai harus antara 0 dan kapasitas penuh.');
        }
        return round($capacity - $remaining, 2);
    }

    // hitung biaya listrik sesuai kwh.
    public function chargingCost(float $kwh, int $pricePerKwh): int
    {
        return (int) round($kwh * $pricePerKwh);
    }

    // hitung biaya sewa perpanjangan
    public function extensionCost(CarbonImmutable $oldEnd, CarbonImmutable $newEnd, int $dailyPrice): int
    {
        if ($newEnd->lte($oldEnd)) {
            throw new InvalidArgumentException('Tanggal selesai baru harus setelah tanggal selesai lama.');
        }

        $cursor = $oldEnd->addDay();
        $cost = 0;

        while ($cursor->addDays($cursor->daysInMonth - 1)->lte($newEnd)) {
            $cost += self::MONTHLY_MULTIPLIER * $dailyPrice;
            $cursor = $cursor->addDays($cursor->daysInMonth);
        }

        $remainingDays = $cursor->lte($newEnd) ? (int) $cursor->diffInDays($newEnd, true) + 1 : 0;
        return $cost + $remainingDays * $dailyPrice;
    }
}
