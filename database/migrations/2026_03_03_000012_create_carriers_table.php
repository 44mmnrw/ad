<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')
                ->unique()
                ->constrained('counterparties')
                ->cascadeOnDelete()
                ->comment('Контрагент-юрлицо, представляющий перевозчика');
            $table->string('code')->unique()->comment('Внутренний код перевозчика');
            $table->boolean('is_active')->default(true)->index()->comment('Активен ли перевозчик');
            $table->text('notes')->nullable()->comment('Примечания');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};
