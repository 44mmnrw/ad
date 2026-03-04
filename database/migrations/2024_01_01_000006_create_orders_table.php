<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique()->comment('Номер заявки');
            $table->foreignId('customer_id')->constrained('counterparties')->comment('Заказчик (кто платит)');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()->comment('Ответственный менеджер');
            $table->text('cargo_description')->nullable()->comment('Описание груза');
            $table->decimal('cargo_weight', 8, 2)->nullable()->comment('Общий вес, тонн');
            $table->decimal('cargo_volume', 8, 2)->nullable()->comment('Общий объём, м³');
            $table->string('cargo_type')->nullable()->comment('Тип груза (хрупкий, опасный и т.д.)');
            $table->integer('distance_km')->nullable()->comment('Расстояние маршрута, км');
            $table->decimal('price', 12, 2)->nullable()->comment('Стоимость перевозки');
            $table->enum('status', ['new', 'assigned', 'in_progress', 'completed', 'cancelled'])
                  ->default('new')
                  ->comment('Статус заявки');
            $table->text('notes')->nullable()->comment('Примечания менеджера');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
