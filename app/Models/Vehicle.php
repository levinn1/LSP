<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Vehicle: data kendaraan penyewa (sepeda, motor, mobil, mobil listrik).
 *
 * plate null untuk sepeda; battery_capacity_kwh hanya terisi untuk mobil listrik.
 */
class Vehicle extends Model
{
    // Kolom yang boleh diisi massal.
    protected $fillable = ['user_id', 'type', 'plate', 'brand', 'model', 'battery_capacity_kwh'];

    /** Relasi many-to-one: pemilik kendaraan. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
