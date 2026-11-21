<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Rental: transaksi sewa satu garasi oleh satu penyewa dengan satu kendaraan.
 *
 * end_date dan total_cost ikut diperbarui saat admin memperpanjang sewa.
 */
class Rental extends Model
{
    // Kolom yang boleh diisi massal.
    protected $fillable = [
        'user_id', 'garage_id', 'vehicle_id', 'rental_type', 'days', 'start_date', 'end_date',
        'battery_remaining_kwh', 'kwh_to_charge', 'total_cost', 'payment_proof',
    ];

    // Ubah string tanggal dari database menjadi objek Carbon agar bisa di-format/dibandingkan.
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    // Relasi balik (many-to-one): penyewa, garasi, dan kendaraan yang dipakai.
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function garage(): BelongsTo { return $this->belongsTo(Garage::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    // Relasi one-to-many: riwayat perpanjangan sewa ini.
    public function extensions(): HasMany { return $this->hasMany(RentalExtension::class); }
}
