<?php

namespace Tests\Concerns;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Pembantu untuk feature test: membuat data uji (user, garasi, kendaraan, sewa) dengan cepat.
 * Setiap pemanggilan menghasilkan data unik (email, NIK, kode garasi) lewat penghitung urutan.
 */
trait MembuatDataUji
{
    private static int $urutan = 0;

    /** Buat akun. Default: penyewa yang sudah diterima admin. */
    protected function buatUser(string $role = 'penyewa', string $status = 'accepted'): User
    {
        $n = ++self::$urutan;

        return User::create([
            'name' => "Pengguna $n",
            'email' => "pengguna$n@contoh.test",
            'password' => 'rahasia123',
            'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT),
            'phone' => '0812000' . $n,
            'role' => $role,
            'verification_status' => $status,
        ]);
    }

    /** Buat garasi dengan kode unik sesuai tipe (S###, C###, E###). */
    protected function buatGarasi(string $type = 'mobil', int $harian = 10000, ?int $hargaKwh = null): Garage
    {
        $prefix = ['sepeda' => 'S', 'mobil' => 'C', 'mobil_listrik' => 'E'][$type];
        $n = ++self::$urutan;

        return Garage::create([
            'code' => $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'type' => $type,
            'daily_price' => $harian,
            'price_per_kwh' => $type === 'mobil_listrik' ? ($hargaKwh ?? 1500) : null,
        ]);
    }

    /** Buat kendaraan milik penyewa. Plat kosong untuk sepeda. */
    protected function buatKendaraan(User $pemilik, string $type = 'mobil', ?float $kapasitasKwh = null): Vehicle
    {
        return Vehicle::create([
            'user_id' => $pemilik->id,
            'type' => $type,
            'plate' => $type === 'sepeda' ? null : 'DD 1234 XX',
            'brand' => 'Merek',
            'model' => 'Tipe',
            'battery_capacity_kwh' => $kapasitasKwh,
        ]);
    }

    /** Buat data sewa langsung ke database (melewati service) untuk menyiapkan kondisi uji. */
    protected function buatSewa(User $user, \App\Models\Garage $garasi, Vehicle $kendaraan, CarbonImmutable $mulai, int $hari = 1, int $total = 1000): Rental
    {
        return Rental::create([
            'user_id' => $user->id,
            'garage_id' => $garasi->id,
            'vehicle_id' => $kendaraan->id,
            'rental_type' => 'harian',
            'days' => $hari,
            'start_date' => $mulai,
            'end_date' => $mulai->addDays($hari - 1),
            'total_cost' => $total,
            'payment_proof' => 'proofs/uji.jpg',
        ]);
    }
}
