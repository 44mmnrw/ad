<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained('counterparties')->comment('Отправитель или получатель на этой точке');
            $table->enum('type', ['loading', 'unloading'])->comment('Погрузка / выгрузка');
            $table->string('address')->comment('Адрес точки');
            $table->datetime('planned_at')->nullable()->comment('Плановое время прибытия');
            $table->unsignedSmallInteger('sequence')->default(0)->comment('Порядок точек в маршруте');
            $table->text('cargo_description')->nullable()->comment('Что грузим / выгружаем на этой точке');
            $table->decimal('cargo_weight', 8, 2)->nullable()->comment('Вес на точке, тонн');
            $table->decimal('cargo_volume', 8, 2)->nullable()->comment('Объём на точке, м³');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stops');
    }
};
