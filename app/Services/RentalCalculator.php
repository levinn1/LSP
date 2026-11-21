<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Kalkulator aturan bisnis penyewaan: kompatibilitas kendaraan, tanggal, dan biaya.
 *
 * Logika murni (tanpa database) sehingga mudah diuji unit.
 */
class RentalCalculator
{
    // Tarif bulanan = 25 x tarif harian (aturan bisnis).
    public const MONTHLY_MULTIPLIER = 25;
    // Sewa harian maksimal 30 hari (sesuai soal).
    public const MAX_DAILY_DAYS = 30;

    /** Peta tipe kendaraan => tipe garasi yang boleh disewa. */
    public const COMPATIBILITY = [
        'sepeda'        => ['sepeda'],
        'motor'         => ['sepeda'],
        'mobil'         => ['mobil'],
        'mobil_listrik' => ['mobil', 'mobil_listrik'],
    ];

    /** Apakah kendaraan bertipe $vehicleType boleh memakai garasi bertipe $garageType? */
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

    /**
     * Jumlah hari efektif: harian = input (1..30), bulanan = jumlah hari bulan mulai.
     * Contoh: bulanan mulai 15 Feb 2026 => 28 hari (Februari 2026 punya 28 hari).
     *
     * @throws InvalidArgumentException bila sewa harian tidak 1..30 hari
     */
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

    /**
     * Tanggal selesai inklusif: mulai + hari - 1.
     * Contoh: mulai 21 Nov, 3 hari => selesai 23 Nov (hari mulai ikut dihitung).
     */
    public function endDate(CarbonImmutable $start, int $effectiveDays): CarbonImmutable
    {
        return $start->addDays($effectiveDays - 1);
    }

    /** Biaya sewa dasar: bulanan = 25 x tarif harian, harian = jumlah hari x tarif harian. */
    public function rentalCost(string $type, int $effectiveDays, int $dailyPrice): int
    {
        return $type === 'bulanan'
            ? self::MONTHLY_MULTIPLIER * $dailyPrice
            : $effectiveDays * $dailyPrice;
    }

    /**
     * Daya yang harus diisi (kWh) = kapasitas penuh - sisa baterai.
     *
     * @throws InvalidArgumentException bila sisa negatif atau melebihi kapasitas
     */
    public function kwhToCharge(float $capacity, float $remaining): float
    {
        if ($remaining < 0 || $remaining > $capacity) {
            throw new InvalidArgumentException('Sisa baterai harus antara 0 dan kapasitas penuh.');
        }
        return round($capacity - $remaining, 2);
    }

    /** Biaya listrik = kWh yang diisi x harga per kWh, dibulatkan ke rupiah terdekat. */
    public function chargingCost(float $kwh, int $pricePerKwh): int
    {
        return (int) round($kwh * $pricePerKwh);
    }

    /**
     * Biaya perpanjangan: setiap 1 bulan kalender penuh (dihitung dari hari setelah
     * tanggal selesai lama) ditarif bulanan, sisanya ditarif harian.
     *
     * @throws InvalidArgumentException bila tanggal selesai baru tidak setelah yang lama
     */
    public function extensionCost(CarbonImmutable $oldEnd, CarbonImmutable $newEnd, int $dailyPrice): int
    {
        if ($newEnd->lte($oldEnd)) {
            throw new InvalidArgumentException('Tanggal selesai baru harus setelah tanggal selesai lama.');
        }

        // Perpanjangan mulai dihitung dari sehari setelah tanggal selesai lama.
        $cursor = $oldEnd->addDay();
        $cost = 0;

        // Selama satu bulan kalender penuh (dari $cursor) masih muat sebelum tanggal selesai baru: tarif bulanan,
        // lalu geser $cursor ke awal bulan berikutnya.
        while ($cursor->addDays($cursor->daysInMonth - 1)->lte($newEnd)) {
            $cost += self::MONTHLY_MULTIPLIER * $dailyPrice;
            $cursor = $cursor->addDays($cursor->daysInMonth);
        }

        // Hari yang tersisa (kurang dari 1 bulan penuh) ditarif harian, hari pertama dan terakhir ikut dihitung (+1).
        $remainingDays = $cursor->lte($newEnd) ? (int) $cursor->diffInDays($newEnd, true) + 1 : 0;
        return $cost + $remainingDays * $dailyPrice;
    }
}
