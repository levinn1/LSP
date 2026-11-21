<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    protected $fillable = ['user_id', 'type', 'plate', 'brand', 'model', 'battery_capacity_kwh'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
