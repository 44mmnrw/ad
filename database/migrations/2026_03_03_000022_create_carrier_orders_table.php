<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete()
                ->comment('Входящая заявка клиента, из которой сформирована исходящая');
            $table->foreignId('carrier_id')
                ->constrained('carriers')
                ->restrictOnDelete()
                ->comment('Назначенный перевозчик');

            $table->string('number')->unique()->comment('Номер исходящей заявки перевозчику');
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'cancelled', 'in_progress', 'completed'])
                ->default('draft')
                ->comment('Статус исходящей заявки');

            $table->decimal('amount_without_vat', 12, 2)->nullable()->comment('Стоимость перевозчику без НДС');
            $table->decimal('vat_rate', 5, 2)->nullable()->comment('Ставка НДС, % (например 20.00)');
            $table->decimal('vat_amount', 12, 2)->nullable()->comment('Сумма НДС для перевозчика');
            $table->decimal('amount_with_vat', 12, 2)->nullable()->comment('Стоимость перевозчику с НДС');

            $table->timestamp('sent_at')->nullable()->comment('Когда отправлено перевозчику');
            $table->timestamp('responded_at')->nullable()->comment('Когда получен ответ перевозчика');
            $table->text('rejection_reason')->nullable()->comment('Причина отказа перевозчика');
            $table->json('payload_snapshot')->nullable()->comment('Снимок параметров заявки на момент отправки');
            $table->text('notes')->nullable()->comment('Внутренние примечания');

            $table->timestamps();

            $table->index(['order_id', 'status'], 'carrier_orders_order_status_idx');
            $table->index(['carrier_id', 'status'], 'carrier_orders_carrier_status_idx');
            $table->index('sent_at', 'carrier_orders_sent_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_orders');
    }
};
