<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Logika murni (tanpa database) sehingga mudah diuji unit.
 */
class RentalCalculator
{
    public const MONTHLY_MULTIPLIER = 25;
    public const MAX_DAILY_DAYS = 30;

    /** Peta tipe kendaraan => tipe garasi yang boleh disewa. */
    public const COMPATIBILITY = [
        'sepeda'        => ['sepeda'],
        'motor'         => ['sepeda'],
        'mobil'         => ['mobil'],
        'mobil_listrik' => ['mobil', 'mobil_listrik'],
    ];

    public function isCompatible(string $vehicleType, string $garageType): bool
    {
        return in_array($garageType, self::COMPATIBILITY[$vehicleType] ?? [], true);
    }

    /** Tanggal mulai tidak boleh mundur dari hari ini. */
    public function assertStartDateValid(CarbonImmutable $start, CarbonImmutable $today): void
    {
        if ($start->startOfDay()->lt($today->startOfDay())) {
            throw new InvalidArgumentException('Tanggal sewa tidak boleh mundur.');
        }
    }

    /** Jumlah hari efektif: harian = input (1..30), bulanan = jumlah hari bulan mulai. */
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

    /** Tanggal selesai inklusif: mulai + hari - 1. */
    public function endDate(CarbonImmutable $start, int $effectiveDays): CarbonImmutable
    {
        return $start->addDays($effectiveDays - 1);
    }

    public function rentalCost(string $type, int $effectiveDays, int $dailyPrice): int
    {
        return $type === 'bulanan'
            ? self::MONTHLY_MULTIPLIER * $dailyPrice
            : $effectiveDays * $dailyPrice;
    }

    public function kwhToCharge(float $capacity, float $remaining): float
    {
        if ($remaining < 0 || $remaining > $capacity) {
            throw new InvalidArgumentException('Sisa baterai harus antara 0 dan kapasitas penuh.');
        }
        return round($capacity - $remaining, 2);
    }

    public function chargingCost(float $kwh, int $pricePerKwh): int
    {
        return (int) round($kwh * $pricePerKwh);
    }

    /**
     * Biaya perpanjangan: setiap 1 bulan kalender penuh (dihitung dari hari setelah
     * tanggal selesai lama) ditarif bulanan, sisanya ditarif harian.
     */
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
