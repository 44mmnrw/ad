<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('id')->comment('Фамилия');
            $table->string('first_name')->nullable()->after('last_name')->comment('Имя');
            $table->string('middle_name')->nullable()->after('first_name')->comment('Отчество');
        });

        DB::table('drivers')
            ->select(['id', 'full_name'])
            ->orderBy('id')
            ->chunkById(100, function ($drivers): void {
                foreach ($drivers as $driver) {
                    $fullName = trim((string) ($driver->full_name ?? ''));
                    if ($fullName === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/u', $fullName, 3) ?: [];

                    DB::table('drivers')
                        ->where('id', $driver->id)
                        ->update([
                            'last_name' => $parts[0] ?? null,
                            'first_name' => $parts[1] ?? null,
                            'middle_name' => $parts[2] ?? null,
                        ]);
                }
            }, 'id');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'license_number',
                'license_categories',
                'license_expiry',
                'passport_series',
                'passport_number',
                'is_active',
                'notes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('id')->comment('ФИО водителя');
            $table->string('license_number', 20)->nullable()->after('phone')->comment('Номер водительского удостоверения');
            $table->string('license_categories', 20)->nullable()->after('license_number')->comment('Категории: B, C, CE, D...');
            $table->date('license_expiry')->nullable()->after('license_categories')->comment('Срок действия удостоверения');
            $table->string('passport_series', 4)->nullable()->after('license_expiry');
            $table->string('passport_number', 6)->nullable()->after('passport_series');
            $table->boolean('is_active')->default(true)->after('passport_number');
            $table->text('notes')->nullable()->after('is_active');
        });

        DB::table('drivers')
            ->select(['id', 'last_name', 'first_name', 'middle_name'])
            ->orderBy('id')
            ->chunkById(100, function ($drivers): void {
                foreach ($drivers as $driver) {
                    $fullName = trim(implode(' ', array_filter([
                        $driver->last_name,
                        $driver->first_name,
                        $driver->middle_name,
                    ])));

                    DB::table('drivers')
                        ->where('id', $driver->id)
                        ->update([
                            'full_name' => $fullName !== '' ? $fullName : null,
                        ]);
                }
            }, 'id');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'first_name',
                'middle_name',
            ]);
        });
    }
};
