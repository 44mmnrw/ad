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
            if (! Schema::hasColumn('order_stops', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('customers')
                    ->restrictOnDelete()
                    ->comment('Заказчик/контрагент на точке маршрута в роли отправителя или получателя');
            }
        });

        if (Schema::hasColumn('order_stops', 'counterparty_id')) {
            DB::table('order_stops')
                ->select('id', 'counterparty_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $stop): void {
                    if (! is_numeric($stop->counterparty_id ?? null)) {
                        return;
                    }

                    $counterpartyId = (int) $stop->counterparty_id;

                    $customerId = DB::table('customers')
                        ->where('counterparty_id', $counterpartyId)
                        ->value('id');

                    if (! is_numeric($customerId)) {
                        $now = now();

                        DB::table('customers')->insert([
                            'counterparty_id' => $counterpartyId,
                            'code' => 'CUST-CP-'.str_pad((string) $counterpartyId, 6, '0', STR_PAD_LEFT),
                            'is_active' => true,
                            'notes' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $customerId = DB::table('customers')
                            ->where('counterparty_id', $counterpartyId)
                            ->value('id');
                    }

                    if (! is_numeric($customerId)) {
                        return;
                    }

                    DB::table('order_stops')
                        ->where('id', (int) $stop->id)
                        ->update([
                            'customer_id' => (int) $customerId,
                            'updated_at' => now(),
                        ]);
                });

            Schema::table('order_stops', function (Blueprint $table) {
                $table->dropForeign(['counterparty_id']);
                $table->dropColumn('counterparty_id');
            });
        }

        DB::table('order_stops')
            ->whereNull('customer_id')
            ->delete();

        Schema::table('order_stops', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_stops', function (Blueprint $table) {
            if (! Schema::hasColumn('order_stops', 'counterparty_id')) {
                $table->foreignId('counterparty_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('counterparties')
                    ->restrictOnDelete()
                    ->comment('Отправитель или получатель на этой точке');
            }
        });

        DB::table('order_stops')
            ->select('id', 'customer_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $stop): void {
                if (! is_numeric($stop->customer_id ?? null)) {
                    return;
                }

                $counterpartyId = DB::table('customers')
                    ->where('id', (int) $stop->customer_id)
                    ->value('counterparty_id');

                if (! is_numeric($counterpartyId)) {
                    return;
                }

                DB::table('order_stops')
                    ->where('id', (int) $stop->id)
                    ->update([
                        'counterparty_id' => (int) $counterpartyId,
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('order_stops', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('order_stops', function (Blueprint $table) {
            $table->foreignId('counterparty_id')
                ->nullable(false)
                ->change();
        });
    }
};