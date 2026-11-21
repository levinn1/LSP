<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Garage: satu unit garasi yang disewakan.
 *
 * Tabel: garages. Tipe: sepeda (kode S###), mobil (C###), mobil_listrik (E###).
 * Satu garasi dapat memiliki banyak sewa (rentals) pada rentang tanggal berbeda.
 */
class Garage extends Model
{
    // Kolom yang boleh diisi lewat mass assignment (Garage::create / update).
    protected $fillable = ['code', 'type', 'daily_price', 'price_per_kwh'];

    /** Relasi one-to-many: semua transaksi sewa pada garasi ini. */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Status "used" bila tanggal berada dalam rentang sewa, bukan sekadar sudah dipesan.
     * Status tidak disimpan di kolom; dihitung dari rentang start_date s.d. end_date
     * sehingga otomatis kembali "available" setelah masa sewa selesai.
     */
    public function isUsedOn(\DateTimeInterface $date): bool
    {
        return $this->rentals()
            // Ada sewa yang mulai sebelum/pada tanggal ini DAN selesai pada/setelah tanggal ini.
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
