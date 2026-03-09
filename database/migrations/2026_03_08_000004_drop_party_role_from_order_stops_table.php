<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_stops', 'party_role')) {
            return;
        }

        Schema::table('order_stops', function (Blueprint $table) {
            $table->dropColumn('party_role');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_stops', 'party_role')) {
            return;
        }

        Schema::table('order_stops', function (Blueprint $table) {
            $table->enum('party_role', ['sender', 'receiver'])
                ->nullable()
                ->after('counterparty_id')
                ->comment('Роль контрагента на точке: грузоотправитель или грузополучатель');
        });
    }
};