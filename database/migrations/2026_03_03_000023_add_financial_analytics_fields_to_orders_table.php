<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('customer_amount_without_vat', 12, 2)
                ->nullable()
                ->after('price')
                ->comment('Стоимость входящей заявки без НДС');
            $table->decimal('customer_vat_rate', 5, 2)
                ->nullable()
                ->after('customer_amount_without_vat')
                ->comment('Ставка НДС входящей заявки, %');
            $table->decimal('customer_vat_amount', 12, 2)
                ->nullable()
                ->after('customer_vat_rate')
                ->comment('Сумма НДС входящей заявки');
            $table->decimal('customer_amount_with_vat', 12, 2)
                ->nullable()
                ->after('customer_vat_amount')
                ->comment('Стоимость входящей заявки с НДС');

            $table->index(['status', 'created_at'], 'orders_status_created_at_idx');
            $table->index(['customer_id', 'created_at'], 'orders_customer_created_at_idx');
            $table->index(['manager_id', 'status'], 'orders_manager_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_idx');
            $table->dropIndex('orders_customer_created_at_idx');
            $table->dropIndex('orders_manager_status_idx');

            $table->dropColumn([
                'customer_amount_without_vat',
                'customer_vat_rate',
                'customer_vat_amount',
                'customer_amount_with_vat',
            ]);
        });
    }
};
