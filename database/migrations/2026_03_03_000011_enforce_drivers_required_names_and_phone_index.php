<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('drivers')->whereNull('last_name')->update(['last_name' => '']);
        DB::table('drivers')->whereNull('first_name')->update(['first_name' => '']);
        DB::table('drivers')->whereNull('phone')->update(['phone' => '']);

        DB::statement("ALTER TABLE `drivers` MODIFY `last_name` VARCHAR(255) NOT NULL COMMENT 'Фамилия'");
        DB::statement("ALTER TABLE `drivers` MODIFY `first_name` VARCHAR(255) NOT NULL COMMENT 'Имя'");
        DB::statement("ALTER TABLE `drivers` MODIFY `middle_name` VARCHAR(255) NULL COMMENT 'Отчество'");
        DB::statement("ALTER TABLE `drivers` MODIFY `phone` VARCHAR(20) NOT NULL");

        Schema::table('drivers', function (Blueprint $table) {
            $table->index('phone', 'drivers_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('drivers_phone_idx');
        });

        DB::statement("ALTER TABLE `drivers` MODIFY `last_name` VARCHAR(255) NULL COMMENT 'Фамилия'");
        DB::statement("ALTER TABLE `drivers` MODIFY `first_name` VARCHAR(255) NULL COMMENT 'Имя'");
        DB::statement("ALTER TABLE `drivers` MODIFY `middle_name` VARCHAR(255) NULL COMMENT 'Отчество'");
        DB::statement("ALTER TABLE `drivers` MODIFY `phone` VARCHAR(20) NOT NULL");
    }
};
