<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidOwnerTypes = DB::table('bank_accounts')
            ->where('owner_type', '!=', 'counterparty')
            ->count();

        if ($invalidOwnerTypes > 0) {
            throw new \RuntimeException('Нельзя добавить FK: обнаружены bank_accounts с owner_type != counterparty.');
        }

        $orphanOwners = DB::table('bank_accounts as ba')
            ->leftJoin('counterparties as c', 'c.id', '=', 'ba.owner_id')
            ->whereNull('c.id')
            ->count();

        if ($orphanOwners > 0) {
            throw new \RuntimeException('Нельзя добавить FK: обнаружены bank_accounts с owner_id без записи в counterparties.');
        }

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->foreign('owner_id', 'bank_accounts_owner_counterparty_fk')
                ->references('id')
                ->on('counterparties')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropForeign('bank_accounts_owner_counterparty_fk');
        });
    }
};
