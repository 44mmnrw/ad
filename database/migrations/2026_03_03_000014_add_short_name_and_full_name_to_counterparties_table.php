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
            $table->string('short_name')->nullable()->after('name')->comment('Короткое наименование');
            $table->string('full_name')->nullable()->after('short_name')->comment('Полное наименование');
        });

        DB::table('counterparties')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($counterparties): void {
                foreach ($counterparties as $counterparty) {
                    $name = trim((string) ($counterparty->name ?? ''));

                    DB::table('counterparties')
                        ->where('id', $counterparty->id)
                        ->update([
                            'short_name' => $name !== '' ? $name : null,
                            'full_name' => $name !== '' ? $name : null,
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'full_name',
            ]);
        });
    }
};
