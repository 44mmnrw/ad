<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounterpartyType extends Model
{
    use HasFactory;

    protected $table = 'counterparties_type';

    protected $guarded = [];

    public function counterparties(): HasMany
    {
        return $this->hasMany(Counterparty::class, 'type');
    }
}
