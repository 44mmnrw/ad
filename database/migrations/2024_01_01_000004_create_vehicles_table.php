<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->comment('Марка');
            $table->string('model')->comment('Модель');
            $table->string('reg_number', 15)->unique()->comment('Гос. номер');
            $table->string('vin', 17)->nullable()->unique();
            $table->smallInteger('year')->nullable()->comment('Год выпуска');
            $table->decimal('carrying_capacity', 8, 2)->nullable()->comment('Грузоподъёмность, тонн');
            $table->decimal('body_volume', 8, 2)->nullable()->comment('Объём кузова, м³');
            $table->enum('body_type', ['tent', 'ref', 'board', 'isoterm', 'container', 'other'])
                  ->default('tent')
                  ->comment('Тип кузова: тент, реф, борт, изотерм, контейнер');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
