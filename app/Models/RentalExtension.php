<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExtension extends Model
{
    protected $fillable = ['rental_id', 'admin_id', 'old_end_date', 'new_end_date', 'additional_cost', 'payment_proof'];

    protected $casts = ['old_end_date' => 'date', 'new_end_date' => 'date'];

    public function rental(): BelongsTo { return $this->belongsTo(Rental::class); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
}
