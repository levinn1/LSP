<?php

namespace Tests\Feature;

use App\Models\RentalExtension;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\MembuatDataUji;
use Tests\TestCase;

class RentalServiceTest extends TestCase
{
    use RefreshDatabase, MembuatDataUji;

    private RentalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RentalService::class);
    }

    private function hariIni(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    // cek kalau akun belum diverif tidak bisa menyewa
    public function test_penyewa_belum_diverifikasi_ditolak(): void
    {
        $user = $this->buatUser('penyewa', 'pending');
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($user, $kendaraan, $garasi, 'harian', 1, $this->hariIni(), null, 'proofs/a.jpg');
    }

    // cek kalau tipe kendaraan tidak sesuai dengan garasi akan gagal
    public function test_kendaraan_tidak_cocok_dengan_garasi_ditolak(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'mobil');

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($user, $kendaraan, $garasi, 'harian', 1, $this->hariIni(), null, 'proofs/a.jpg');
    }

   // cek tanggal mulai sewa tidak boleh tanggal sudah lewat
    public function test_tanggal_mulai_mundur_ditolak(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($user, $kendaraan, $garasi, 'harian', 1, $this->hariIni()->subDay(), null, 'proofs/a.jpg');
    }

    // cek biaya harian dengan tanggal selesai
    public function test_sewa_harian_menghitung_total_dan_tanggal_selesai(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda', 10000);
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $mulai = $this->hariIni();

        $sewa = $this->service->create($user, $kendaraan, $garasi, 'harian', 3, $mulai, null, 'proofs/a.jpg');

        $this->assertSame(30000, (int) $sewa->total_cost);
        $this->assertSame(3, (int) $sewa->days);
        $this->assertSame($mulai->addDays(2)->toDateString(), $sewa->end_date->toDateString());
        $this->assertDatabaseHas('rentals', ['id' => $sewa->id, 'total_cost' => 30000]);
    }

    // cek sewa harian 1 hari sebelum hari yang ada di bulan diterima
    public function test_sewa_harian_tepat_batas_n_min_1_diterima(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $mulai = $this->hariIni();

        $sewa = $this->service->create($user, $kendaraan, $garasi, 'harian', $mulai->daysInMonth - 1, $mulai, null, 'proofs/a.jpg');

        $this->assertSame($mulai->daysInMonth - 1, (int) $sewa->days);
    }

    // cek sewa harian itu sama dengan hari yang ada di bulan akan tolak
    public function test_sewa_harian_sebanyak_n_hari_ditolak(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $mulai = $this->hariIni();

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($user, $kendaraan, $garasi, 'harian', $mulai->daysInMonth, $mulai, null, 'proofs/a.jpg');
    }

    // cek harga sewa bulanan
    public function test_sewa_bulanan_tarif_25_kali_harian(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda', 10000);
        $kendaraan = $this->buatKendaraan($user, 'sepeda');
        $mulai = $this->hariIni();

        $sewa = $this->service->create($user, $kendaraan, $garasi, 'bulanan', null, $mulai, null, 'proofs/a.jpg');

        $this->assertSame(250000, (int) $sewa->total_cost);
        $this->assertSame($mulai->daysInMonth, (int) $sewa->days);
        $this->assertSame($mulai->addDays($mulai->daysInMonth - 1)->toDateString(), $sewa->end_date->toDateString());
    }

   // cek apakah biaya sewa garasi listrik sudah sama biaya isi listrik
    public function test_mobil_listrik_di_garasi_listrik_total_termasuk_biaya_listrik(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('mobil_listrik', 20000, 1500);
        $kendaraan = $this->buatKendaraan($user, 'mobil_listrik', 60.0);

        $sewa = $this->service->create($user, $kendaraan, $garasi, 'harian', 3, $this->hariIni(), 22.5, 'proofs/a.jpg');

        $this->assertSame(116250, (int) $sewa->total_cost);
        $this->assertEquals(37.5, (float) $sewa->kwh_to_charge);
    }

    // cek apakah mobil listrik di garasi biasa bayar tidak membayar listrik
    public function test_mobil_listrik_di_garasi_mobil_biasa_tanpa_biaya_listrik(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('mobil', 20000);
        $kendaraan = $this->buatKendaraan($user, 'mobil_listrik', 60.0);

        $sewa = $this->service->create($user, $kendaraan, $garasi, 'harian', 2, $this->hariIni(), 22.5, 'proofs/a.jpg');

        $this->assertSame(40000, (int) $sewa->total_cost);
        $this->assertNull($sewa->kwh_to_charge);
    }

    // cek sisa baterai harus diisi untuk garasi listrik
    public function test_mobil_listrik_di_garasi_listrik_tanpa_sisa_baterai_ditolak(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('mobil_listrik');
        $kendaraan = $this->buatKendaraan($user, 'mobil_listrik', 60.0);

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($user, $kendaraan, $garasi, 'harian', 1, $this->hariIni(), null, 'proofs/a.jpg');
    }

    // cek garasi yang sudah disewa tidak bisa disewa oleh orang lain di tanggal yang bertabrakan
    public function test_garasi_bentrok_jadwal_ditolak(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $a = $this->buatUser();
        $b = $this->buatUser();
        $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), $this->hariIni(), 3);

        $this->expectException(InvalidArgumentException::class);
        $this->service->create($b, $this->buatKendaraan($b, 'motor'), $garasi, 'harian', 1, $this->hariIni()->addDays(2), null, 'proofs/b.jpg');
    }

    // cek kalau garasi bisa disewa lagi setelah tanggal sewa sudah lewat
    public function test_garasi_dapat_disewa_sehari_setelah_sewa_lain_selesai(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $a = $this->buatUser();
        $b = $this->buatUser();
        $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), $this->hariIni(), 3);

        $this->service->create($b, $this->buatKendaraan($b, 'motor'), $garasi, 'harian', 1, $this->hariIni()->addDays(3), null, 'proofs/b.jpg');

        $this->assertDatabaseCount('rentals', 2);
    }

    // cek garasi tersedia sesuai tipe kendaraan
    public function test_daftar_garasi_tersedia_sesuai_tipe_kendaraan(): void
    {
        $this->buatGarasi('sepeda');
        $this->buatGarasi('mobil');
        $this->buatGarasi('mobil_listrik');
        $hariIni = $this->hariIni();

        $untukMotor = $this->service->availableGarages('motor', $hariIni, $hariIni)->pluck('type')->all();
        $untukMobil = $this->service->availableGarages('mobil', $hariIni, $hariIni)->pluck('type')->all();
        $untukListrik = $this->service->availableGarages('mobil_listrik', $hariIni, $hariIni)->pluck('type')->sort()->values()->all();

        $this->assertSame(['sepeda'], $untukMotor);
        $this->assertSame(['mobil'], $untukMobil);
        $this->assertSame(['mobil', 'mobil_listrik'], $untukListrik);
    }

    // cek garasi yang sedang disewa tidak tersedia
    public function test_garasi_yang_sedang_disewa_tidak_tersedia(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $a = $this->buatUser();
        $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), $this->hariIni(), 1);
        $hariIni = $this->hariIni();
        $lusa = $hariIni->addDays(5);

        $this->assertCount(0, $this->service->availableGarages('motor', $hariIni, $hariIni));
        $this->assertCount(1, $this->service->availableGarages('motor', $lusa, $lusa));
    }

    // cek apakah status garasi sudah sesuai dengan jadwal diseawakannya
    public function test_status_used_mengikuti_rentang_sewa(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $a = $this->buatUser();
        $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), $this->hariIni(), 2);

        $this->assertTrue($garasi->isUsedOn($this->hariIni()));
        $this->assertTrue($garasi->isUsedOn($this->hariIni()->addDay()));
        $this->assertFalse($garasi->isUsedOn($this->hariIni()->subDay()));
        $this->assertFalse($garasi->isUsedOn($this->hariIni()->addDays(2)));
    }

    // cek apakah admin bisa perpanjang sewa
    public function test_admin_dapat_memperpanjang_sewa(): void
    {
        $admin = $this->buatUser('admin');
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda', 10000);
        $mulai = $this->hariIni();
        $sewa = $this->service->create($user, $this->buatKendaraan($user, 'motor'), $garasi, 'harian', 3, $mulai, null, 'proofs/a.jpg');

        $perpanjangan = $this->service->extend($admin, $sewa, $mulai->addDays(4), 'proofs/ext.jpg');

        $this->assertInstanceOf(RentalExtension::class, $perpanjangan);
        $this->assertSame(20000, (int) $perpanjangan->additional_cost);
        $sewa->refresh();
        $this->assertSame($mulai->addDays(4)->toDateString(), $sewa->end_date->toDateString());
        $this->assertSame(50000, (int) $sewa->total_cost);
        $this->assertDatabaseCount('rental_extensions', 1);
    }

    // cek kalau perpanjangan sewa bertabrakan dengan penyewa yang lain
    public function test_perpanjangan_yang_bentrok_ditolak(): void
    {
        $admin = $this->buatUser('admin');
        $a = $this->buatUser();
        $b = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $mulai = $this->hariIni();
        $sewaA = $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), $mulai, 3);
        $this->buatSewa($b, $garasi, $this->buatKendaraan($b, 'motor'), $mulai->addDays(3), 2);

        try {
            $this->service->extend($admin, $sewaA, $mulai->addDays(4), 'proofs/ext.jpg');
            $this->fail('Perpanjangan yang bentrok seharusnya ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($mulai->addDays(2)->toDateString(), $sewaA->fresh()->end_date->toDateString());
            $this->assertDatabaseCount('rental_extensions', 0);
        }
    }
}
