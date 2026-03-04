<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparty_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')->constrained('counterparties')->cascadeOnDelete();
            $table->string('full_name')->comment('ФИО контактного лица');
            $table->string('email')->nullable()->comment('Email контакта');
            $table->string('phone_mobile', 20)->nullable()->comment('Мобильный телефон');
            $table->string('phone_city', 20)->nullable()->comment('Городской телефон');
            $table->string('phone_extension', 10)->nullable()->comment('Добавочный номер');
            $table->boolean('is_primary')->default(false)->comment('Основной контакт');
            $table->boolean('is_active')->default(true)->comment('Активный контакт');
            $table->text('notes')->nullable()->comment('Примечания');
            $table->timestamps();

            $table->index(['counterparty_id', 'is_primary'], 'counterparty_contacts_primary_idx');
            $table->index('email', 'counterparty_contacts_email_idx');
            $table->index('phone_mobile', 'counterparty_contacts_mobile_idx');
        });

        DB::table('counterparties')
            ->select(['id', 'contact_person', 'email', 'phone'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insertRows = [];

                foreach ($rows as $row) {
                    $fullName = trim((string) ($row->contact_person ?? ''));
                    $email = trim((string) ($row->email ?? ''));
                    $phone = trim((string) ($row->phone ?? ''));

                    if ($fullName === '' && $email === '' && $phone === '') {
                        continue;
                    }

                    $insertRows[] = [
                        'counterparty_id' => $row->id,
                        'full_name' => $fullName !== '' ? $fullName : 'Контакт не указан',
                        'email' => $email !== '' ? $email : null,
                        'phone_mobile' => $phone !== '' ? $phone : null,
                        'phone_city' => null,
                        'phone_extension' => null,
                        'is_primary' => true,
                        'is_active' => true,
                        'notes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($insertRows !== []) {
                    DB::table('counterparty_contacts')->insert($insertRows);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_contacts');
    }
};
