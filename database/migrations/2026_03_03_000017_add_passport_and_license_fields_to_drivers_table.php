<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('passport_series', 4)->nullable()->after('phone')->comment('Серия паспорта');
            $table->string('passport_number', 6)->nullable()->after('passport_series')->comment('Номер паспорта');
            $table->date('passport_issued_at')->nullable()->after('passport_number')->comment('Дата выдачи паспорта');
            $table->string('passport_issued_by')->nullable()->after('passport_issued_at')->comment('Кем выдан паспорт');

            $table->string('license_number', 20)->nullable()->after('passport_issued_by')->comment('Номер водительского удостоверения');
            $table->string('license_categories', 20)->nullable()->after('license_number')->comment('Категории прав (B, C, CE, D...)');
            $table->date('license_issued_at')->nullable()->after('license_categories')->comment('Дата выдачи водительского удостоверения');
            $table->date('license_expiry')->nullable()->after('license_issued_at')->comment('Срок действия водительского удостоверения');

            $table->index('license_number', 'drivers_license_number_idx');
            $table->index('passport_number', 'drivers_passport_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('drivers_license_number_idx');
            $table->dropIndex('drivers_passport_number_idx');

            $table->dropColumn([
                'passport_series',
                'passport_number',
                'passport_issued_at',
                'passport_issued_by',
                'license_number',
                'license_categories',
                'license_issued_at',
                'license_expiry',
            ]);
        });
    }
};
