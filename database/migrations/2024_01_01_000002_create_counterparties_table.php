<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['legal', 'individual'])->default('legal')->comment('юр. лицо / физ. лицо');
            $table->string('name')->comment('Название организации или ФИО');
            $table->string('inn', 12)->nullable()->comment('ИНН');
            $table->string('kpp', 9)->nullable()->comment('КПП (для юр. лиц)');
            $table->string('ogrn', 15)->nullable()->comment('ОГРН / ОГРНИП');
            $table->string('legal_address')->nullable()->comment('Юридический адрес');
            $table->string('actual_address')->nullable()->comment('Фактический адрес');
            $table->string('contact_person')->nullable()->comment('Контактное лицо');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable()->comment('Название банка');
            $table->string('bank_account', 20)->nullable()->comment('Расчётный счёт');
            $table->string('bik', 9)->nullable()->comment('БИК банка');
            $table->string('correspondent_account', 20)->nullable()->comment('Корреспондентский счёт');
            $table->text('notes')->nullable()->comment('Примечания');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
