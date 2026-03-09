<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasActualAddress = Schema::hasColumn('counterparties', 'actual_address');
        $hasCeo = Schema::hasColumn('counterparties', 'ceo');
        $hasManagerName = Schema::hasColumn('counterparties', 'manager_name');
        $hasManagerPost = Schema::hasColumn('counterparties', 'manager_post');

        Schema::table('counterparties', function (Blueprint $table) use ($hasActualAddress, $hasCeo, $hasManagerName, $hasManagerPost): void {
            if ($hasActualAddress) {
                $table->dropColumn('actual_address');
            }

            if (! $hasManagerName) {
                $table->string('manager_name')->nullable()->after('legal_address')->comment('ФИО руководителя/ответственного лица');
            }

            if (! $hasManagerPost) {
                $table->string('manager_post')->nullable()->after('manager_name')->comment('Должность руководителя/ответственного лица');
            }

            if ($hasCeo) {
                $table->dropColumn('ceo');
            }
        });
    }

    public function down(): void
    {
        $hasActualAddress = Schema::hasColumn('counterparties', 'actual_address');
        $hasCeo = Schema::hasColumn('counterparties', 'ceo');
        $hasManagerName = Schema::hasColumn('counterparties', 'manager_name');
        $hasManagerPost = Schema::hasColumn('counterparties', 'manager_post');

        Schema::table('counterparties', function (Blueprint $table) use ($hasActualAddress, $hasCeo, $hasManagerName, $hasManagerPost): void {
            if (! $hasActualAddress) {
                $table->text('actual_address')->nullable()->after('legal_address')->comment('Фактический адрес');
            }

            if (! $hasCeo) {
                $table->string('ceo')->nullable()->after('legal_address')->comment('Контактное лицо');
            }

            $columnsToDrop = [];

            if ($hasManagerPost) {
                $columnsToDrop[] = 'manager_post';
            }

            if ($hasManagerName) {
                $columnsToDrop[] = 'manager_name';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};