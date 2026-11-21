<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    protected $fillable = [
        'user_id', 'garage_id', 'vehicle_id', 'rental_type', 'days', 'start_date', 'end_date',
        'battery_remaining_kwh', 'kwh_to_charge', 'total_cost', 'payment_proof',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function garage(): BelongsTo { return $this->belongsTo(Garage::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function extensions(): HasMany { return $this->hasMany(RentalExtension::class); }
}
