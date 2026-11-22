<?php

namespace Tests\Feature;

use App\Models\Garage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataUji;
use Tests\TestCase;

class GarageManagementTest extends TestCase
{
    use RefreshDatabase, MembuatDataUji;

    // cek kalau penyewa tidak bisa buka page admin
    public function test_penyewa_tidak_boleh_mengakses_halaman_admin(): void
    {
        $this->actingAs($this->buatUser())
            ->get(route('admin.garages.index'))
            ->assertForbidden();
    }

    // cek apakah pembuatan garasi sudah punya kode berurutan
    public function test_buat_garasi_massal_kode_berurutan(): void
    {
        $this->actingAs($this->buatUser('admin'));

        $this->post(route('admin.garages.store'), ['type' => 'sepeda', 'count' => 3, 'daily_price' => 10000])
            ->assertRedirect(route('admin.garages.index'));
        $this->post(route('admin.garages.store'), ['type' => 'sepeda', 'count' => 2, 'daily_price' => 10000]);

        $this->assertSame(['S001', 'S002', 'S003', 'S004', 'S005'], Garage::orderBy('code')->pluck('code')->all());
    }

    // cek kalau garasi mobil listrik ada harga per kwh
    public function test_garasi_mobil_listrik_wajib_harga_kwh(): void
    {
        $this->actingAs($this->buatUser('admin'))
            ->post(route('admin.garages.store'), ['type' => 'mobil_listrik', 'count' => 1, 'daily_price' => 20000])
            ->assertSessionHasErrors('price_per_kwh');

        $this->assertDatabaseCount('garages', 0);
    }

    // cek jumlah garasi tidak boleh lebih dari 999
    public function test_buat_garasi_lebih_dari_999_ditolak(): void
    {
        $this->actingAs($this->buatUser('admin'))
            ->post(route('admin.garages.store'), ['type' => 'mobil', 'count' => 1000, 'daily_price' => 10000])
            ->assertSessionHasErrors('count');
    }

    
    // cek kalau admin bisa ubah harga garasi
    public function test_admin_dapat_mengubah_harga_garasi(): void
    {
        $garasi = $this->buatGarasi('mobil', 10000);

        $this->actingAs($this->buatUser('admin'))
            ->put(route('admin.garages.update', $garasi), ['code' => $garasi->code, 'type' => 'mobil', 'daily_price' => 15000])
            ->assertRedirect(route('admin.garages.index'));

        $this->assertDatabaseHas('garages', ['id' => $garasi->id, 'daily_price' => 15000]);
    }

    // cek apakah kode garasi sudah sesuai dengan tipenya
    public function test_kode_harus_sesuai_tipe(): void
    {
        $garasi = $this->buatGarasi('sepeda');

        $this->actingAs($this->buatUser('admin'))
            ->put(route('admin.garages.update', $garasi), ['code' => 'C777', 'type' => 'sepeda', 'daily_price' => 10000])
            ->assertSessionHasErrors('code');
    }

    // cek kalau tidak boleh ada kode garasi yang kembar
    public function test_kode_tidak_boleh_kembar(): void
    {
        $a = $this->buatGarasi('mobil');
        $b = $this->buatGarasi('mobil');

        $this->actingAs($this->buatUser('admin'))
            ->put(route('admin.garages.update', $b), ['code' => $a->code, 'type' => 'mobil', 'daily_price' => 10000])
            ->assertSessionHasErrors('code');
    }

    // cek kalau garasi sudah pernah disewa tidak bisa diedit tipenya
    public function test_tipe_tidak_dapat_diubah_bila_sudah_ada_sewa(): void
    {
        $garasi = $this->buatGarasi('mobil');
        $user = $this->buatUser();
        $this->buatSewa($user, $garasi, $this->buatKendaraan($user, 'mobil'), CarbonImmutable::today());

        $this->actingAs($this->buatUser('admin'))
            ->put(route('admin.garages.update', $garasi), ['code' => 'S777', 'type' => 'sepeda', 'daily_price' => 10000])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseHas('garages', ['id' => $garasi->id, 'type' => 'mobil']);
    }

    // cek kalau garasi yang belum pernah disewa bisa dihapus
    public function test_garasi_tanpa_riwayat_sewa_dapat_dihapus(): void
    {
        $garasi = $this->buatGarasi('sepeda');

        $this->actingAs($this->buatUser('admin'))
            ->delete(route('admin.garages.destroy', $garasi))
            ->assertRedirect(route('admin.garages.index'));

        $this->assertDatabaseMissing('garages', ['id' => $garasi->id]);
    }

    // cek kalau garasi yang belum pernah disewa bisa dihapus
    public function test_garasi_dengan_riwayat_sewa_tidak_dapat_dihapus(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $user = $this->buatUser();
        $this->buatSewa($user, $garasi, $this->buatKendaraan($user, 'motor'), CarbonImmutable::today());

        $this->actingAs($this->buatUser('admin'))
            ->delete(route('admin.garages.destroy', $garasi))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('garages', ['id' => $garasi->id]);
    }
}
