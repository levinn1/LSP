<?php

namespace Tests\Unit;

use App\Services\RentalCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RentalCalculatorTest extends TestCase
{
    private RentalCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new RentalCalculator();
    }

    public function test_kompatibilitas_kendaraan_garasi(): void
    {
        $this->assertTrue($this->calc->isCompatible('motor', 'sepeda'));
        $this->assertFalse($this->calc->isCompatible('motor', 'mobil'));
        $this->assertTrue($this->calc->isCompatible('mobil_listrik', 'mobil'));
        $this->assertTrue($this->calc->isCompatible('mobil_listrik', 'mobil_listrik'));
        $this->assertFalse($this->calc->isCompatible('mobil', 'mobil_listrik'));
    }

    public function test_tanggal_mundur_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->assertStartDateValid(CarbonImmutable::parse('2026-11-20'), CarbonImmutable::parse('2026-11-21'));
    }

    public function test_harian_maksimal_30_hari(): void
    {
        $start = CarbonImmutable::parse('2026-11-21');
        $this->assertSame(30, $this->calc->effectiveDays('harian', $start, 30));
        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', $start, 31);
    }

    public function test_bulanan_mengikuti_jumlah_hari_bulan_mulai(): void
    {
        $feb = CarbonImmutable::parse('2026-02-15');
        $days = $this->calc->effectiveDays('bulanan', $feb, null);
        $this->assertSame(28, $days);
        $this->assertSame('2026-03-14', $this->calc->endDate($feb, $days)->toDateString());

        $leap = CarbonImmutable::parse('2028-02-01');
        $this->assertSame(29, $this->calc->effectiveDays('bulanan', $leap, null));
    }

    public function test_tarif_harian_dan_bulanan(): void
    {
        $this->assertSame(30000, $this->calc->rentalCost('harian', 3, 10000));
        $this->assertSame(250000, $this->calc->rentalCost('bulanan', 31, 10000));
    }

    public function test_daya_diisi_dan_biaya_listrik(): void
    {
        $kwh = $this->calc->kwhToCharge(60.0, 22.5);
        $this->assertSame(37.5, $kwh);
        $this->assertSame(56250, $this->calc->chargingCost($kwh, 1500));
    }

    public function test_sisa_baterai_melebihi_kapasitas_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->kwhToCharge(60.0, 70.0);
    }

    public function test_biaya_perpanjangan_harian(): void
    {
        $old = CarbonImmutable::parse('2026-11-25');
        $new = CarbonImmutable::parse('2026-11-30');
        $this->assertSame(50000, $this->calc->extensionCost($old, $new, 10000)); // 5 hari
    }

    public function test_biaya_perpanjangan_satu_bulan_penuh(): void
    {
        $old = CarbonImmutable::parse('2026-11-30');
        $new = CarbonImmutable::parse('2026-12-31'); // 1-31 Des = 1 bulan penuh
        $this->assertSame(250000, $this->calc->extensionCost($old, $new, 10000));
    }

    public function test_biaya_perpanjangan_bulan_plus_sisa_hari(): void
    {
        $old = CarbonImmutable::parse('2026-11-30');
        $new = CarbonImmutable::parse('2027-01-05'); // Des penuh + 5 hari
        $this->assertSame(300000, $this->calc->extensionCost($old, $new, 10000));
    }
}
