<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->comment('Назначенный водитель');
            $table->foreignId('vehicle_id')->constrained()->comment('Автомобиль');
            $table->foreignId('trailer_id')->nullable()->constrained('trailers')->nullOnDelete()->comment('Прицеп (если есть)');
            $table->string('token', 64)->unique()->comment('Уникальный токен для ссылки водителя');
            $table->enum('status', ['assigned', 'loading', 'in_transit', 'unloading', 'completed'])
                  ->default('assigned')
                  ->comment('Текущий статус рейса');
            $table->datetime('planned_departure')->nullable()->comment('Плановое время выезда');
            $table->datetime('planned_arrival')->nullable()->comment('Плановое время прибытия');
            $table->datetime('actual_departure')->nullable()->comment('Фактическое время выезда');
            $table->datetime('actual_arrival')->nullable()->comment('Фактическое время прибытия');
            $table->text('driver_notes')->nullable()->comment('Заметки водителя');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
