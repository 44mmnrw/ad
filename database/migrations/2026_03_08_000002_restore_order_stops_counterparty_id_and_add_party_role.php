<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_stops', function (Blueprint $table) {
            if (! Schema::hasColumn('order_stops', 'counterparty_id')) {
                $table->foreignId('counterparty_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('counterparties')
                    ->restrictOnDelete()
                    ->comment('Контрагент на точке маршрута');
            }

            if (! Schema::hasColumn('order_stops', 'party_role')) {
                $table->enum('party_role', ['sender', 'receiver'])
                    ->nullable()
                    ->after('counterparty_id')
                    ->comment('Роль контрагента на точке: грузоотправитель или грузополучатель');
            }
        });

        DB::table('order_stops')
            ->select('id', 'customer_id', 'type')
            ->orderBy('id')
            ->get()
            ->each(function (object $stop): void {
                $counterpartyId = null;

                if (is_numeric($stop->customer_id ?? null)) {
                    $counterpartyId = DB::table('customers')
                        ->where('id', (int) $stop->customer_id)
                        ->value('counterparty_id');
                }

                DB::table('order_stops')
                    ->where('id', (int) $stop->id)
                    ->update([
                        'counterparty_id' => is_numeric($counterpartyId) ? (int) $counterpartyId : null,
                        'party_role' => ($stop->type ?? 'unloading') === 'loading' ? 'sender' : 'receiver',
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('order_stops', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        DB::table('order_stops')
            ->whereNull('counterparty_id')
            ->delete();

        Schema::table('order_stops', function (Blueprint $table) {
            $table->foreignId('counterparty_id')->nullable(false)->change();
            $table->enum('party_role', ['sender', 'receiver'])->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_stops', function (Blueprint $table) {
            if (! Schema::hasColumn('order_stops', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('customers')
                    ->restrictOnDelete()
                    ->comment('Заказчик/контрагент на точке маршрута');
            }
        });

        DB::table('order_stops')
            ->select('id', 'counterparty_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $stop): void {
                $customerId = null;

                if (is_numeric($stop->counterparty_id ?? null)) {
                    $customerId = DB::table('customers')
                        ->where('counterparty_id', (int) $stop->counterparty_id)
                        ->value('id');
                }

                DB::table('order_stops')
                    ->where('id', (int) $stop->id)
                    ->update([
                        'customer_id' => is_numeric($customerId) ? (int) $customerId : null,
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('order_stops', function (Blueprint $table) {
            $table->dropForeign(['counterparty_id']);
            $table->dropColumn('counterparty_id');
            $table->dropColumn('party_role');
        });

        DB::table('order_stops')
            ->whereNull('customer_id')
            ->delete();

        Schema::table('order_stops', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};