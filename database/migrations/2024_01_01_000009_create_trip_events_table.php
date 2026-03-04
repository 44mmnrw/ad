<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_stop_id')->nullable()->constrained('order_stops')->nullOnDelete()->comment('К какой точке маршрута относится событие');
            $table->enum('event_type', [
                'loading_started',
                'loading_done',
                'departed',
                'arrived',
                'unloading_started',
                'unloading_done',
            ])->comment('Тип события');
            $table->datetime('occurred_at')->comment('Время события (фиксирует водитель)');
            $table->decimal('latitude', 10, 7)->nullable()->comment('Геолокация — широта');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Геолокация — долгота');
            $table->string('photo_path')->nullable()->comment('Фото документов или груза');
            $table->text('notes')->nullable()->comment('Заметка водителя к событию');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_events');
    }
};
