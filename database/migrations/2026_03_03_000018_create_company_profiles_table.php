<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('short_name')->comment('Краткое наименование');
            $table->string('full_name')->comment('Полное наименование');
            $table->string('inn', 12)->nullable()->comment('ИНН');
            $table->string('kpp', 9)->nullable()->comment('КПП');
            $table->string('ogrn', 15)->nullable()->comment('ОГРН/ОГРНИП');
            $table->string('legal_address')->nullable()->comment('Юридический адрес');
            $table->string('actual_address')->nullable()->comment('Фактический адрес');
            $table->string('bank_name')->nullable()->comment('Название банка');
            $table->string('bank_account', 20)->nullable()->comment('Расчётный счёт');
            $table->string('bik', 9)->nullable()->comment('БИК');
            $table->string('correspondent_account', 20)->nullable()->comment('Корреспондентский счёт');
            $table->string('phone', 20)->nullable()->comment('Телефон');
            $table->string('email')->nullable()->comment('Email');
            $table->boolean('is_active')->default(true)->index()->comment('Активный профиль компании');
            $table->text('notes')->nullable()->comment('Примечания');
            $table->timestamps();

            $table->index(['inn', 'kpp'], 'company_profiles_inn_kpp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
