<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataUji;
use Tests\TestCase;


class ReportTest extends TestCase
{
    use RefreshDatabase, MembuatDataUji;

    // cek filter tahun
    public function test_filter_tahun(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $this->buatSewa($user, $garasi, $kendaraan, CarbonImmutable::parse('2025-06-10'), 1, 5000);
        $this->buatSewa($user, $garasi, $kendaraan, CarbonImmutable::parse('2026-06-10'), 1, 7000);

        $this->actingAs($this->buatUser('admin'))
            ->get(route('admin.reports', ['year' => 2026]))
            ->assertOk()
            ->assertViewHas('total', 7000);
    }

    // cek filter tipe garasi
    public function test_filter_tipe_garasi(): void
    {
        $user = $this->buatUser();
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $this->buatSewa($user, $this->buatGarasi('sepeda'), $kendaraan, CarbonImmutable::today(), 1, 1000);
        $this->buatSewa($user, $this->buatGarasi('mobil'), $kendaraan, CarbonImmutable::today(), 1, 2000);

        $this->actingAs($this->buatUser('admin'))
            ->get(route('admin.reports', ['garage_type' => 'mobil']))
            ->assertOk()
            ->assertViewHas('total', 2000);
    }

    // cek filter penyewa
    public function test_filter_penyewa(): void
    {
        $a = $this->buatUser();
        $b = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $this->buatSewa($a, $garasi, $this->buatKendaraan($a, 'motor'), CarbonImmutable::today(), 1, 3000);
        $this->buatSewa($b, $garasi, $this->buatKendaraan($b, 'motor'), CarbonImmutable::today(), 1, 4000);

        $this->actingAs($this->buatUser('admin'))
            ->get(route('admin.reports', ['user_id' => $a->id]))
            ->assertOk()
            ->assertViewHas('total', 3000);
    }

    // cek apakah filter tahun sudah sesuai dengan data
    public function test_dropdown_tahun_berisi_tahun_yang_ada_di_data(): void
    {
        $user = $this->buatUser();
        $garasi = $this->buatGarasi('sepeda');
        $kendaraan = $this->buatKendaraan($user, 'motor');
        $this->buatSewa($user, $garasi, $kendaraan, CarbonImmutable::parse('2025-06-10'));
        $this->buatSewa($user, $garasi, $kendaraan, CarbonImmutable::parse('2026-06-10'));

        $this->actingAs($this->buatUser('admin'))
            ->get(route('admin.reports'))
            ->assertViewHas('years', fn (array $tahun) => in_array(2025, $tahun, true)
                && in_array(2026, $tahun, true)
                && in_array((int) CarbonImmutable::today()->format('Y'), $tahun, true));
    }
}
