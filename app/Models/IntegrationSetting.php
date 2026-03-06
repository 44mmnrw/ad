<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        try {
            return static::query()
                ->where('integration', $integration)
                ->where('key', $key)
                ->value('value') ?? $default;
        } catch (DecryptException $e) {
            Log::warning('Failed to decrypt integration setting value.', [
                'integration' => $integration,
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            return $default;
        }
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
