<?php

namespace Tests\Unit;

use App\Services\RentalCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;


class RentalCalculatorBoundaryTest extends TestCase
{
    private RentalCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new RentalCalculator();
    }

    // cek kecocokan sepeda dengan tipe garasi
    public function test_kompatibilitas_sepeda(): void
    {
        $this->assertTrue($this->calc->isCompatible('sepeda', 'sepeda'));
        $this->assertFalse($this->calc->isCompatible('sepeda', 'mobil'));
    }

    // cek kalau mobil tidak bisa gunakan garasi sepeda
    public function test_mobil_tidak_boleh_ke_garasi_sepeda(): void
    {
        $this->assertFalse($this->calc->isCompatible('mobil', 'sepeda'));
    }

    // cek tipe kendaraan yang tidak dikenal akan ditolak
    public function test_tipe_kendaraan_tidak_dikenal_ditolak(): void
    {
        $this->assertFalse($this->calc->isCompatible('truk', 'mobil'));
    }

    // cek tanggal mulai hari ini dapat digunakan
    public function test_tanggal_mulai_hari_ini_diterima(): void
    {
        $hariIni = CarbonImmutable::parse('2026-11-21');
        $this->calc->assertStartDateValid($hariIni, $hariIni);
        $this->addToAssertionCount(1);  
    }

    // cek tanggal mulai di masa depan bisa digunakan
    public function test_tanggal_mulai_masa_depan_diterima(): void
    {
        $this->calc->assertStartDateValid(CarbonImmutable::parse('2026-12-25'), CarbonImmutable::parse('2026-11-21'));
        $this->addToAssertionCount(1);
    }

    // cek sewa harian dengan jumlah nol hari ditolak
    public function test_harian_nol_hari_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', CarbonImmutable::parse('2026-11-21'), 0);
    }

    // cek sewa harian tanpa jumlah hari ditolak
    public function test_harian_tanpa_jumlah_hari_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', CarbonImmutable::parse('2026-11-21'), null);
    }

    // cek perhitungan durasi sewa bulanan di bulan 30 dan 31 hari
    public function test_bulanan_bulan_30_dan_31_hari(): void
    {
        $april = CarbonImmutable::parse('2026-04-10');
        $this->assertSame(30, $this->calc->effectiveDays('bulanan', $april, null));
        $this->assertSame('2026-05-09', $this->calc->endDate($april, 30)->toDateString());

        $desember = CarbonImmutable::parse('2026-12-01');
        $this->assertSame(31, $this->calc->effectiveDays('bulanan', $desember, null));
        $this->assertSame('2026-12-31', $this->calc->endDate($desember, 31)->toDateString());
    }

    // cek baterai kosong harus isi sampai full
    public function test_baterai_kosong_isi_kapasitas_penuh(): void
    {
        $this->assertSame(60.0, $this->calc->kwhToCharge(60.0, 0.0));
    }

    // cek baterai full tidak perlu isi listrik
    public function test_baterai_sudah_penuh_tidak_perlu_isi(): void
    {
        $kwh = $this->calc->kwhToCharge(60.0, 60.0);
        $this->assertSame(0.0, $kwh);
        $this->assertSame(0, $this->calc->chargingCost($kwh, 1500));
    }

    // cek sisa baterai negatif ditolak
    public function test_sisa_baterai_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->kwhToCharge(60.0, -1.0);
    }

    // cek perpanjangan dengan tanggal akhir yang sama ditolak
    public function test_perpanjangan_tanggal_sama_ditolak(): void
    {
        $tgl = CarbonImmutable::parse('2026-11-25');
        $this->expectException(InvalidArgumentException::class);
        $this->calc->extensionCost($tgl, $tgl, 10000);
    }

    // cek perpanjangan dengan tanggal akhir lebih awal ditolak
    public function test_perpanjangan_tanggal_mundur_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->extensionCost(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-20'), 10000);
    }

    // cek perhitungan biaya untuk perpanjangan satu hari
    public function test_perpanjangan_satu_hari(): void
    {
        $this->assertSame(10000, $this->calc->extensionCost(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-26'), 10000));
    }

    // cek batas maksimal sewa harian sesuai jumlah hari dalam bulan
    public function test_batas_harian_mengikuti_panjang_bulan(): void
    {
        $this->assertSame(27, $this->calc->maxDailyDays(CarbonImmutable::parse('2026-02-10')));  
        $this->assertSame(28, $this->calc->maxDailyDays(CarbonImmutable::parse('2028-02-10')));  
        $this->assertSame(29, $this->calc->maxDailyDays(CarbonImmutable::parse('2026-04-10')));   
        $this->assertSame(30, $this->calc->maxDailyDays(CarbonImmutable::parse('2026-12-01')));   
    }

    // cek sewa harian pas dengan batas maksimal bulan februari diterima
    public function test_harian_tepat_batas_februari_diterima(): void
    {
        $this->assertSame(27, $this->calc->effectiveDays('harian', CarbonImmutable::parse('2026-02-10'), 27));
    }

    // cek sewa harian yang melebihi batas maksimal bulan februari ditolak
    public function test_harian_sama_dengan_jumlah_hari_bulan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', CarbonImmutable::parse('2026-02-10'), 28);
    }

    // cek sewa harian pada bulan desember maksimal 30 hari
    public function test_harian_bulan_31_hari_maksimal_30(): void
    {
        $desember = CarbonImmutable::parse('2026-12-01');
        $this->assertSame(30, $this->calc->effectiveDays('harian', $desember, 30));

        $this->expectException(InvalidArgumentException::class);
        $this->calc->effectiveDays('harian', $desember, 31);
    }
}
