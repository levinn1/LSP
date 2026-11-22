<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

 // end_date dan total_cost diperbarui saat admin memperpanjang sewa.

class Rental extends Model
{
    // Kolom diisi saat sewa.
    protected $fillable = [
        'user_id', 'garage_id', 'vehicle_id', 'rental_type', 'days', 'start_date', 'end_date',
        'battery_remaining_kwh', 'kwh_to_charge', 'total_cost', 'payment_proof',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    // many-to-one penyewa, garasi, dan kendaraan.
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function garage(): BelongsTo { return $this->belongsTo(Garage::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    // riwayat perpanjangan sewa.
    public function extensions(): HasMany { return $this->hasMany(RentalExtension::class); }
}
