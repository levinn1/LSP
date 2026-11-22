<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// sepeda kode S, mobil C, mobil_listrik E
class Garage extends Model
{
    // kolom buat atau update data garasi
    protected $fillable = ['code', 'type', 'daily_price', 'price_per_kwh'];

    // semua transaksi sewa pada garasi ini
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    // status "used" kalau masa sewa berlangsung
    // status sesuai start_date dan end_date
    // kalau masa sewa selesai status jadi "available"
    public function isUsedOn(\DateTimeInterface $date): bool
    {
        return $this->rentals()
            // ada garasi yang disewa di tanggal ini
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
