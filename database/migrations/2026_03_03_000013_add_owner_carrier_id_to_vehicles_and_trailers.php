<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('owner_carrier_id')
                ->nullable()
                ->after('id')
                ->constrained('carriers')
                ->nullOnDelete()
                ->comment('Перевозчик-владелец ТС');
        });

        Schema::table('trailers', function (Blueprint $table) {
            $table->foreignId('owner_carrier_id')
                ->nullable()
                ->after('id')
                ->constrained('carriers')
                ->nullOnDelete()
                ->comment('Перевозчик-владелец прицепа');
        });
    }

    public function down(): void
    {
        Schema::table('trailers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_carrier_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_carrier_id');
        });
    }
};
