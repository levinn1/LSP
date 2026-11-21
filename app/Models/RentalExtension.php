<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model RentalExtension: catatan satu kali perpanjangan sewa oleh admin.
 *
 * Menyimpan tanggal selesai lama/baru, biaya tambahan, dan bukti pembayaran.
 */
class RentalExtension extends Model
{
    // Kolom yang boleh diisi massal.
    protected $fillable = ['rental_id', 'admin_id', 'old_end_date', 'new_end_date', 'additional_cost', 'payment_proof'];

    // Kolom tanggal otomatis menjadi objek Carbon.
    protected $casts = ['old_end_date' => 'date', 'new_end_date' => 'date'];

    // Relasi ke sewa yang diperpanjang dan ke admin yang memprosesnya (tabel users).
    public function rental(): BelongsTo { return $this->belongsTo(Rental::class); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
}
