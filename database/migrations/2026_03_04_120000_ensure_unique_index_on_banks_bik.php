<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'banks')
            ->where('index_name', 'banks_bik_unique')
            ->exists();

        if ($indexExists) {
            return;
        }

        $duplicates = DB::table('banks')
            ->select('bik', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull('bik')
            ->where('bik', '!=', '')
            ->groupBy('bik')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('duplicate_count')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $examples = $duplicates
                ->map(fn ($row) => $row->bik.' ('.$row->duplicate_count.')')
                ->implode(', ');

            throw new \RuntimeException('Найдены дубли БИК в таблице banks. Перед добавлением unique-индекса устраните дубли: '.$examples);
        }

        Schema::table('banks', function (Blueprint $table): void {
            $table->unique('bik', 'banks_bik_unique');
        });
    }

    public function down(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'banks')
            ->where('index_name', 'banks_bik_unique')
            ->exists();

        if (! $indexExists) {
            return;
        }

        Schema::table('banks', function (Blueprint $table): void {
            $table->dropUnique('banks_bik_unique');
        });
    }
};
