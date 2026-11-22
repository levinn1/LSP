<?php

namespace Tests\Feature;

use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatDataUji;
use Tests\TestCase;

class RentalHttpTest extends TestCase
{
    use RefreshDatabase, MembuatDataUji;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');   // file bukti bayar tidak disimpan di database
    }

    // data sewa yang valid
    private function dataSewa(int $garasiId): array
    {
        return [
            'vehicle_type' => 'motor', 'plate' => 'DD1234XX', 'brand' => 'Honda', 'model' => 'Beat',
            'rental_type' => 'harian', 'days' => 2, 'start_date' => CarbonImmutable::today()->toDateString(),
            'garage_id' => $garasiId, 'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ];
    }

    // cek endpoint untuk tunjukkan tanggal akhir dan garasi yang benar
    public function test_endpoint_garasi_tersedia_mengembalikan_tanggal_selesai_dan_garasi_sesuai(): void
    {
        $this->buatGarasi('sepeda');
        $this->buatGarasi('mobil');
        $mulai = CarbonImmutable::today();

        $this->actingAs($this->buatUser())
            ->getJson(route('rentals.available', [
                'vehicle_type' => 'motor', 'rental_type' => 'harian', 'days' => 3, 'start_date' => $mulai->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('days', 3)
            ->assertJsonPath('end_date', $mulai->addDays(2)->toDateString())
            ->assertJsonCount(1, 'garages')
            ->assertJsonPath('garages.0.type', 'sepeda');
    }

    // cek kalau sewa harian sama dengan jumlah hari bulan mulai
    public function test_endpoint_menolak_sewa_harian_sama_dengan_jumlah_hari_bulan(): void
    {
        $this->actingAs($this->buatUser())
            ->getJson(route('rentals.available', [
                'vehicle_type' => 'motor', 'rental_type' => 'harian', 'days' => 28, 'start_date' => '2027-02-01',
            ]))
            ->assertStatus(422);
    }

    // cek kalau sewa harian n-1 tanggal yang ada di bulan itu
    public function test_endpoint_menerima_sewa_harian_tepat_batas_bulan(): void
    {
        $this->buatGarasi('sepeda');

        $this->actingAs($this->buatUser())
            ->getJson(route('rentals.available', [
                'vehicle_type' => 'motor', 'rental_type' => 'harian', 'days' => 27, 'start_date' => '2027-02-01',
            ]))
            ->assertOk()
            ->assertJsonPath('end_date', '2027-02-27');
    }

    // cek apakah data dan bukti bayar disimpan kalau sewa berhasil dilakukan
    public function test_sewa_berhasil_menyimpan_data_dan_bukti_bayar(): void
    {
        $garasi = $this->buatGarasi('sepeda', 10000);

        $this->actingAs($this->buatUser())
            ->post(route('rentals.store'), $this->dataSewa($garasi->id))
            ->assertRedirect(route('rentals.index'));

        $this->assertDatabaseCount('rentals', 1);
        $this->assertDatabaseCount('vehicles', 1);
        $sewa = Rental::first();
        $this->assertSame(20000, (int) $sewa->total_cost);
        Storage::disk('public')->assertExists($sewa->payment_proof);
    }

    // cek kalau sudah ada yang sewa garasi tidak bisa disewa orang lain
    public function test_sewa_ditolak_tidak_meninggalkan_kendaraan_dan_file(): void
    {
        $garasi = $this->buatGarasi('sepeda');
        $pemilik = $this->buatUser();
        $this->buatSewa($pemilik, $garasi, $this->buatKendaraan($pemilik, 'motor'), CarbonImmutable::today(), 3);

        $this->actingAs($this->buatUser())
            ->post(route('rentals.store'), $this->dataSewa($garasi->id))
            ->assertSessionHasErrors('rental');

        $this->assertDatabaseCount('rentals', 1);     // hanya sewa milik pemilik awal
        $this->assertDatabaseCount('vehicles', 1);    // kendaraan penyewa kedua dibatalkan
        $this->assertSame([], Storage::disk('public')->allFiles('proofs'));
    }
}
