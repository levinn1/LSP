<?php

namespace Tests\Unit;

use App\Services\RentalCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit test RentalCalculator (white box): kompatibilitas kendaraan, validasi tanggal,
 * perhitungan hari/tarif, biaya listrik, dan biaya perpanjangan.
 * Tidak memakai database sehingga cepat dan deterministik.
 * Jalankan: php artisan test --testsuite=Unit
 */
class RentalCalculatorTest extends TestCase
{
    private RentalCalculator $calc;

    // Dijalankan sebelum setiap test: objek kalkulator baru agar antar-test tidak saling memengaruhi.
    protected function setUp(): void
    {
        $this->calc = new RentalCalculator();
    }

    /** Skenario: aturan tipe kendaraan -> tipe garasi. Harapan: motor hanya sepeda; mobil listrik boleh mobil & mobil listrik. */
    public function test_kompatibilitas_kendaraan_garasi(): void
    {
        $this->assertTrue($this->calc->isCompatible('motor', 'sepeda'));
        $this->assertFalse($this->calc->isCompatible('motor', 'mobil'));
        $this->assertTrue($this->calc->isCompatible('mobil_listrik', 'mobil'));
        $this->assertTrue($this->calc->isCompatible('mobil_listrik', 'mobil_listrik'));
        $this->assertFalse($this->calc->isCompatible('mobil', 'mobil_listrik'));
    }

    /** Skenario: tanggal mulai 20 Nov saat hari ini 21 Nov. Harapan: ditolak (exception). */
    public function test_tanggal_mundur_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->assertStartDateValid(CarbonImmutable::parse('2026-11-20'), CarbonImmutable::parse('2026-11-21'));
    }

    /** Skenario: sewa harian 30 hari (batas) dan 31 hari. Harapan: 30 diterima, 31 ditolak. */
    public function test_harian_maksimal_30_hari(): void
    {
        $start = CarbonImmutable::parse('2026-11-21');
        $this->assertSame(30, $this->calc->effectiveDays('harian', $start, 30));
        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', $start, 31);
    }

    /** Skenario: sewa bulanan Feb 2026 (28 hari), Feb 2028 (kabisat, 29 hari). Harapan: hari efektif mengikuti jumlah hari bulan; mulai 15 Feb selesai 14 Mar. */
    public function test_bulanan_mengikuti_jumlah_hari_bulan_mulai(): void
    {
        $feb = CarbonImmutable::parse('2026-02-15');
        $days = $this->calc->effectiveDays('bulanan', $feb, null);
        $this->assertSame(28, $days);
        $this->assertSame('2026-03-14', $this->calc->endDate($feb, $days)->toDateString());

        $leap = CarbonImmutable::parse('2028-02-01');
        $this->assertSame(29, $this->calc->effectiveDays('bulanan', $leap, null));
    }

    /** Skenario: tarif harian Rp10.000. Harapan: 3 hari = Rp30.000; bulanan = 25 x harian = Rp250.000. */
    public function test_tarif_harian_dan_bulanan(): void
    {
        $this->assertSame(30000, $this->calc->rentalCost('harian', 3, 10000));
        $this->assertSame(250000, $this->calc->rentalCost('bulanan', 31, 10000));
    }

    /** Skenario: kapasitas 60 kWh, sisa 22,5 kWh, Rp1.500/kWh. Harapan: isi 37,5 kWh, biaya Rp56.250. */
    public function test_daya_diisi_dan_biaya_listrik(): void
    {
        $kwh = $this->calc->kwhToCharge(60.0, 22.5);
        $this->assertSame(37.5, $kwh);
        $this->assertSame(56250, $this->calc->chargingCost($kwh, 1500));
    }

    /** Skenario: sisa baterai 70 kWh melebihi kapasitas 60 kWh. Harapan: ditolak (exception). */
    public function test_sisa_baterai_melebihi_kapasitas_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->kwhToCharge(60.0, 70.0);
    }

    /** Skenario: perpanjang 26-30 Nov (5 hari), tarif Rp10.000. Harapan: Rp50.000. */
    public function test_biaya_perpanjangan_harian(): void
    {
        $old = CarbonImmutable::parse('2026-11-25');
        $new = CarbonImmutable::parse('2026-11-30');
        $this->assertSame(50000, $this->calc->extensionCost($old, $new, 10000)); // 5 hari
    }

    /** Skenario: perpanjang tepat 1 Des-31 Des (1 bulan kalender penuh). Harapan: 25 x Rp10.000 = Rp250.000. */
    public function test_biaya_perpanjangan_satu_bulan_penuh(): void
    {
        $old = CarbonImmutable::parse('2026-11-30');
        $new = CarbonImmutable::parse('2026-12-31'); // 1-31 Des = 1 bulan penuh
        $this->assertSame(250000, $this->calc->extensionCost($old, $new, 10000));
    }

    /** Skenario: perpanjang Des penuh + 5 hari Januari. Harapan: Rp250.000 + 5 x Rp10.000 = Rp300.000. */
    public function test_biaya_perpanjangan_bulan_plus_sisa_hari(): void
    {
        $old = CarbonImmutable::parse('2026-11-30');
        $new = CarbonImmutable::parse('2027-01-05'); // Des penuh + 5 hari
        $this->assertSame(300000, $this->calc->extensionCost($old, $new, 10000));
    }
}
