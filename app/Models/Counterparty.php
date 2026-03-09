<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counterparty extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'legal_address_data' => 'array',
        'legal_address_invalid' => 'boolean',
        'legal_geo_lat' => 'decimal:7',
        'legal_geo_lon' => 'decimal:7',
    ];

    public function typeRef(): BelongsTo
    {
        return $this->belongsTo(CounterpartyType::class, 'type');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CounterpartyContact::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'owner_id')
            ->where('owner_type', 'counterparty');
    }
}
