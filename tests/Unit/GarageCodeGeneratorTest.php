<?php

namespace Tests\Unit;

use App\Services\GarageCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GarageCodeGeneratorTest extends TestCase
{
    // buat kode sesuai kode yang dibuat sebelumnya dan dimulai dari 001
    public function test_kode_incremental_dari_awal(): void
    {
        $this->assertSame(['S001', 'S002', 'S003'], (new GarageCodeGenerator())->generate('sepeda', 3));
    }

    // pastikan generator buat kode sesuai dengan kode sebelum
    public function test_kode_melanjutkan_nomor_terakhir(): void
    {
        $this->assertSame(['C010', 'C011'], (new GarageCodeGenerator())->generate('mobil', 2, 9));
    }

    // pastikan tipe mobil listrik pakai kode E
    public function test_prefix_mobil_listrik(): void
    {
        $this->assertSame(['E001'], (new GarageCodeGenerator())->generate('mobil_listrik', 1));
    }

    // pastikan garasi yang dibuat tidak lebih dari 999
    public function test_jumlah_di_atas_999_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('mobil', 1000);
    }

    // pastikan setiap tipe garasi tidak lebih dari 999
    public function test_melewati_999_total_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('mobil', 5, 997);
    }
}
