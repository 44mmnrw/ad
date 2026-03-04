<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->enum('owner_type', ['counterparty', 'company_profile'])->comment('Тип владельца счёта');
            $table->unsignedBigInteger('owner_id')->comment('ID владельца счёта');
            $table->string('account_number', 20)->nullable()->comment('Номер расчётного счёта');
            $table->boolean('is_primary')->default(false)->comment('Основной счёт');
            $table->boolean('is_active')->default(true)->comment('Активный счёт');
            $table->text('comment')->nullable()->comment('Примечание');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'bank_accounts_owner_idx');
            $table->index(['owner_type', 'owner_id', 'is_primary'], 'bank_accounts_primary_idx');
            $table->index('bank_id', 'bank_accounts_bank_idx');
            $table->unique(['owner_type', 'owner_id', 'account_number'], 'bank_accounts_owner_account_unique');
        });

        DB::table('counterparty_bank_accounts')
            ->select(['counterparty_id', 'bank_id', 'account_number', 'is_primary', 'is_active', 'comment', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insertRows = [];

                foreach ($rows as $row) {
                    $insertRows[] = [
                        'bank_id' => $row->bank_id,
                        'owner_type' => 'counterparty',
                        'owner_id' => $row->counterparty_id,
                        'account_number' => $row->account_number,
                        'is_primary' => (bool) $row->is_primary,
                        'is_active' => (bool) $row->is_active,
                        'comment' => $row->comment,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($insertRows !== []) {
                    DB::table('bank_accounts')->insert($insertRows);
                }
            }, 'id');

        DB::table('company_profile_bank_accounts')
            ->select(['company_profile_id', 'bank_id', 'account_number', 'is_primary', 'is_active', 'comment', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insertRows = [];

                foreach ($rows as $row) {
                    $insertRows[] = [
                        'bank_id' => $row->bank_id,
                        'owner_type' => 'company_profile',
                        'owner_id' => $row->company_profile_id,
                        'account_number' => $row->account_number,
                        'is_primary' => (bool) $row->is_primary,
                        'is_active' => (bool) $row->is_active,
                        'comment' => $row->comment,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($insertRows !== []) {
                    DB::table('bank_accounts')->insert($insertRows);
                }
            }, 'id');

        Schema::dropIfExists('company_profile_bank_accounts');
        Schema::dropIfExists('counterparty_bank_accounts');
    }

    public function down(): void
    {
        Schema::create('counterparty_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')->constrained('counterparties')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('account_number', 20)->nullable()->comment('Расчётный счёт контрагента');
            $table->boolean('is_primary')->default(false)->comment('Основной счёт контрагента');
            $table->boolean('is_active')->default(true)->comment('Активный счёт');
            $table->text('comment')->nullable()->comment('Примечание');
            $table->timestamps();

            $table->index(['counterparty_id', 'is_primary'], 'cp_bank_accounts_primary_idx');
            $table->index('bank_id', 'cp_bank_accounts_bank_idx');
        });

        Schema::create('company_profile_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('account_number', 20)->nullable()->comment('Расчётный счёт компании');
            $table->boolean('is_primary')->default(false)->comment('Основной счёт компании');
            $table->boolean('is_active')->default(true)->comment('Активный счёт');
            $table->text('comment')->nullable()->comment('Примечание');
            $table->timestamps();

            $table->index(['company_profile_id', 'is_primary'], 'company_bank_accounts_primary_idx');
            $table->index('bank_id', 'company_bank_accounts_bank_idx');
        });

        DB::table('bank_accounts')
            ->where('owner_type', 'counterparty')
            ->select(['owner_id', 'bank_id', 'account_number', 'is_primary', 'is_active', 'comment', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insertRows = [];

                foreach ($rows as $row) {
                    $insertRows[] = [
                        'counterparty_id' => $row->owner_id,
                        'bank_id' => $row->bank_id,
                        'account_number' => $row->account_number,
                        'is_primary' => (bool) $row->is_primary,
                        'is_active' => (bool) $row->is_active,
                        'comment' => $row->comment,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($insertRows !== []) {
                    DB::table('counterparty_bank_accounts')->insert($insertRows);
                }
            }, 'id');

        DB::table('bank_accounts')
            ->where('owner_type', 'company_profile')
            ->select(['owner_id', 'bank_id', 'account_number', 'is_primary', 'is_active', 'comment', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insertRows = [];

                foreach ($rows as $row) {
                    $insertRows[] = [
                        'company_profile_id' => $row->owner_id,
                        'bank_id' => $row->bank_id,
                        'account_number' => $row->account_number,
                        'is_primary' => (bool) $row->is_primary,
                        'is_active' => (bool) $row->is_active,
                        'comment' => $row->comment,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if ($insertRows !== []) {
                    DB::table('company_profile_bank_accounts')->insert($insertRows);
                }
            }, 'id');

        Schema::dropIfExists('bank_accounts');
    }
};
