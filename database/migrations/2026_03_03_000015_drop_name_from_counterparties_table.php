<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->string('name')->nullable()->after('type')->comment('Название организации или ФИО');
        });

        DB::table('counterparties')
            ->select(['id', 'short_name', 'full_name'])
            ->orderBy('id')
            ->chunkById(100, function ($counterparties): void {
                foreach ($counterparties as $counterparty) {
                    $name = trim((string) ($counterparty->short_name ?: $counterparty->full_name ?: ''));

                    DB::table('counterparties')
                        ->where('id', $counterparty->id)
                        ->update([
                            'name' => $name !== '' ? $name : null,
                        ]);
                }
            }, 'id');
    }
};
