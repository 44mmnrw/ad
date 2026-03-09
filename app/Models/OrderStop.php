<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'counterparty_id',
        'type',
        'city',
        'address',
        'lat',
        'lng',
        'planned_at',
        'sequence',
        'cargo_description',
        'cargo_weight',
        'cargo_volume',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'planned_at' => 'datetime',
        'cargo_weight' => 'float',
        'cargo_volume' => 'float',
    ];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }
}
