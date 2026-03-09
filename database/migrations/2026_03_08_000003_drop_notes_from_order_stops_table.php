<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_stops', 'notes')) {
            return;
        }

        Schema::table('order_stops', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_stops', 'notes')) {
            return;
        }

        Schema::table('order_stops', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('cargo_volume');
        });
    }
};