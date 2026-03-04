<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('price');

            $table->renameColumn('customer_amount_without_vat', 'amount_without_vat');
            $table->renameColumn('customer_vat_rate', 'vat_rate');
            $table->renameColumn('customer_vat_amount', 'vat_amount');
            $table->renameColumn('customer_amount_with_vat', 'amount_with_vat');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('amount_without_vat', 'customer_amount_without_vat');
            $table->renameColumn('vat_rate', 'customer_vat_rate');
            $table->renameColumn('vat_amount', 'customer_vat_amount');
            $table->renameColumn('amount_with_vat', 'customer_amount_with_vat');

            $table->decimal('price', 12, 2)
                ->nullable()
                ->after('distance_km')
                ->comment('Стоимость перевозки');
        });
    }
};
