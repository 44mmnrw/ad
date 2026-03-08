<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')
                ->unique()
                ->constrained('counterparties')
                ->cascadeOnDelete()
                ->comment('Контрагент-юрлицо, представляющий заказчика');
            $table->boolean('is_active')->default(true)->index()->comment('Активен ли заказчик');
            $table->text('notes')->nullable()->comment('Примечания');
            $table->timestamps();
        });

        $now = now();

        DB::table('counterparties')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($counterparties) use ($now): void {
                foreach ($counterparties as $counterparty) {
                    DB::table('customers')->updateOrInsert(
                        ['counterparty_id' => (int) $counterparty->id],
                        [
                            'is_active' => true,
                            'notes' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });

        $orders = DB::table('orders')
            ->select('id', 'customer_id')
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $oldCounterpartyId = (int) $order->customer_id;

            $customerId = DB::table('customers')
                ->where('counterparty_id', $oldCounterpartyId)
                ->value('id');

            if (! is_numeric($customerId)) {
                DB::table('customers')->insert([
                    'counterparty_id' => $oldCounterpartyId,
                    'is_active' => true,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $customerId = DB::table('customers')
                    ->where('counterparty_id', $oldCounterpartyId)
                    ->value('id');
            }

            DB::table('orders')
                ->where('id', (int) $order->id)
                ->update(['customer_id' => (int) $customerId]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $orders = DB::table('orders')
            ->select('id', 'customer_id')
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $counterpartyId = DB::table('customers')
                ->where('id', (int) $order->customer_id)
                ->value('counterparty_id');

            if (! is_numeric($counterpartyId)) {
                continue;
            }

            DB::table('orders')
                ->where('id', (int) $order->id)
                ->update(['customer_id' => (int) $counterpartyId]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')
                ->references('id')
                ->on('counterparties')
                ->restrictOnDelete();
        });

        Schema::dropIfExists('customers');
    }
};
