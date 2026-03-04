<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Наименование банка');
            $table->string('short_name')->nullable()->comment('Краткое наименование банка');
            $table->string('bik', 9)->nullable()->unique()->comment('БИК банка');
            $table->string('correspondent_account', 20)->nullable()->comment('Корреспондентский счёт банка');
            $table->timestamps();
        });

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

        $resolveBankId = static function (?string $bankName, ?string $bik, ?string $corrAccount): ?int {
            $bankName = trim((string) $bankName);
            $bik = trim((string) $bik);
            $corrAccount = trim((string) $corrAccount);

            if ($bankName === '' && $bik === '' && $corrAccount === '') {
                return null;
            }

            if ($bik !== '') {
                $existingId = DB::table('banks')->where('bik', $bik)->value('id');
                if ($existingId) {
                    return (int) $existingId;
                }
            }

            if ($bankName !== '' || $corrAccount !== '') {
                $query = DB::table('banks');

                if ($bankName !== '') {
                    $query->where('name', $bankName);
                }

                if ($corrAccount !== '') {
                    $query->where('correspondent_account', $corrAccount);
                }

                $existingId = $query->value('id');
                if ($existingId) {
                    return (int) $existingId;
                }
            }

            return (int) DB::table('banks')->insertGetId([
                'name' => $bankName !== '' ? $bankName : 'Банк без названия',
                'short_name' => null,
                'bik' => $bik !== '' ? $bik : null,
                'correspondent_account' => $corrAccount !== '' ? $corrAccount : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        DB::table('counterparties')
            ->select(['id', 'bank_name', 'bank_account', 'bik', 'correspondent_account'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($resolveBankId): void {
                foreach ($rows as $row) {
                    $bankName = trim((string) ($row->bank_name ?? ''));
                    $bankAccount = trim((string) ($row->bank_account ?? ''));
                    $bik = trim((string) ($row->bik ?? ''));
                    $corr = trim((string) ($row->correspondent_account ?? ''));

                    if ($bankName === '' && $bankAccount === '' && $bik === '' && $corr === '') {
                        continue;
                    }

                    $bankId = $resolveBankId($bankName, $bik, $corr);

                    DB::table('counterparty_bank_accounts')->insert([
                        'counterparty_id' => $row->id,
                        'bank_id' => $bankId,
                        'account_number' => $bankAccount !== '' ? $bankAccount : null,
                        'is_primary' => true,
                        'is_active' => true,
                        'comment' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');

        DB::table('company_profiles')
            ->select(['id', 'bank_name', 'bank_account', 'bik', 'correspondent_account'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($resolveBankId): void {
                foreach ($rows as $row) {
                    $bankName = trim((string) ($row->bank_name ?? ''));
                    $bankAccount = trim((string) ($row->bank_account ?? ''));
                    $bik = trim((string) ($row->bik ?? ''));
                    $corr = trim((string) ($row->correspondent_account ?? ''));

                    if ($bankName === '' && $bankAccount === '' && $bik === '' && $corr === '') {
                        continue;
                    }

                    $bankId = $resolveBankId($bankName, $bik, $corr);

                    DB::table('company_profile_bank_accounts')->insert([
                        'company_profile_id' => $row->id,
                        'bank_id' => $bankId,
                        'account_number' => $bankAccount !== '' ? $bankAccount : null,
                        'is_primary' => true,
                        'is_active' => true,
                        'comment' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');

        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account', 'bik', 'correspondent_account']);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account', 'bik', 'correspondent_account']);
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->comment('Название банка');
            $table->string('bank_account', 20)->nullable()->comment('Расчётный счёт');
            $table->string('bik', 9)->nullable()->comment('БИК банка');
            $table->string('correspondent_account', 20)->nullable()->comment('Корреспондентский счёт');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->comment('Название банка');
            $table->string('bank_account', 20)->nullable()->comment('Расчётный счёт');
            $table->string('bik', 9)->nullable()->comment('БИК');
            $table->string('correspondent_account', 20)->nullable()->comment('Корреспондентский счёт');
        });

        $counterpartyPrimaryAccounts = DB::table('counterparty_bank_accounts as cba')
            ->leftJoin('banks as b', 'b.id', '=', 'cba.bank_id')
            ->select([
                'cba.counterparty_id',
                'cba.account_number',
                'b.name as bank_name',
                'b.bik',
                'b.correspondent_account',
                'cba.is_primary',
                'cba.id',
            ])
            ->orderByDesc('cba.is_primary')
            ->orderBy('cba.id')
            ->get()
            ->groupBy('counterparty_id');

        foreach ($counterpartyPrimaryAccounts as $counterpartyId => $accounts) {
            $account = $accounts->first();
            if (! $account) {
                continue;
            }

            DB::table('counterparties')
                ->where('id', $counterpartyId)
                ->update([
                    'bank_name' => $account->bank_name,
                    'bank_account' => $account->account_number,
                    'bik' => $account->bik,
                    'correspondent_account' => $account->correspondent_account,
                ]);
        }

        $companyPrimaryAccounts = DB::table('company_profile_bank_accounts as cba')
            ->leftJoin('banks as b', 'b.id', '=', 'cba.bank_id')
            ->select([
                'cba.company_profile_id',
                'cba.account_number',
                'b.name as bank_name',
                'b.bik',
                'b.correspondent_account',
                'cba.is_primary',
                'cba.id',
            ])
            ->orderByDesc('cba.is_primary')
            ->orderBy('cba.id')
            ->get()
            ->groupBy('company_profile_id');

        foreach ($companyPrimaryAccounts as $companyProfileId => $accounts) {
            $account = $accounts->first();
            if (! $account) {
                continue;
            }

            DB::table('company_profiles')
                ->where('id', $companyProfileId)
                ->update([
                    'bank_name' => $account->bank_name,
                    'bank_account' => $account->account_number,
                    'bik' => $account->bik,
                    'correspondent_account' => $account->correspondent_account,
                ]);
        }

        Schema::dropIfExists('company_profile_bank_accounts');
        Schema::dropIfExists('counterparty_bank_accounts');
        Schema::dropIfExists('banks');
    }
};
