<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];

    public static function getValue(string $integration, string $key, mixed $default = null): mixed
    {
        return static::query()
            ->where('integration', $integration)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public static function setValue(string $integration, string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            [
                'integration' => $integration,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }
}
