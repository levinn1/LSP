<?php

namespace Tests\Unit;

use App\Services\GarageCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;


class GarageCodeGeneratorBoundaryTest extends TestCase
{
    // cek kalau berhasil bisa buat 999 kode  
    public function test_tepat_999_per_batch_diterima(): void
    {
        $kode = (new GarageCodeGenerator())->generate('sepeda', 999);

        $this->assertCount(999, $kode);
        $this->assertSame('S001', $kode[0]);
        $this->assertSame('S999', $kode[998]);
    }

    // cek kalau tidak bisa buat kode diatas 999
    public function test_setelah_nomor_999_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('mobil', 1, 999);
    }

    // tes kalau kode tidak bisa 0
    public function test_jumlah_nol_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GarageCodeGenerator())->generate('sepeda', 0);
    }
}
