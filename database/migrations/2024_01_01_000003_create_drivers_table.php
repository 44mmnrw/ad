<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->comment('ФИО водителя');
            $table->string('phone', 20);
            $table->string('license_number', 20)->unique()->comment('Номер водительского удостоверения');
            $table->string('license_categories', 20)->comment('Категории: B, C, CE, D...');
            $table->date('license_expiry')->comment('Срок действия удостоверения');
            $table->string('passport_series', 4)->nullable();
            $table->string('passport_number', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
