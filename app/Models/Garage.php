<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garage extends Model
{
    protected $fillable = ['code', 'type', 'daily_price', 'price_per_kwh'];

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** Status "used" bila tanggal berada dalam rentang sewa, bukan sekadar sudah dipesan. */
    public function isUsedOn(\DateTimeInterface $date): bool
    {
        return $this->rentals()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
