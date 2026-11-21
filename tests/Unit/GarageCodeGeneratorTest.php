<?php

namespace Tests\Unit;

use App\Services\GarageCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit test GarageCodeGenerator: format kode (prefix + 3 digit), incremental, dan batas 999.
 * Jalankan: php artisan test --testsuite=Unit
 */
class GarageCodeGeneratorTest extends TestCase
{
    /** Skenario: buat 3 garasi sepeda dari awal. Harapan: S001, S002, S003. */
    public function test_kode_incremental_dari_awal(): void
    {
        $this->assertSame(['S001', 'S002', 'S003'], (new GarageCodeGenerator())->generate('sepeda', 3));
    }

    /** Skenario: nomor terakhir mobil = 9, buat 2 lagi. Harapan: C010, C011. */
    public function test_kode_melanjutkan_nomor_terakhir(): void
    {
        $this->assertSame(['C010', 'C011'], (new GarageCodeGenerator())->generate('mobil', 2, 9));
    }

    /** Skenario: buat 1 garasi mobil listrik. Harapan: E001. */
    public function test_prefix_mobil_listrik(): void
    {
        $this->assertSame(['E001'], (new GarageCodeGenerator())->generate('mobil_listrik', 1));
    }

    /** Skenario: buat 1000 garasi dalam satu batch. Harapan: ditolak (exception). */
    public function test_jumlah_di_atas_999_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('mobil', 1000);
    }

    /** Skenario: nomor terakhir 997, buat 5 lagi (total 1002). Harapan: ditolak (exception). */
    public function test_melewati_999_total_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('mobil', 5, 997);
    }
}
