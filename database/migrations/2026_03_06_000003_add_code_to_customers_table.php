<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('counterparty_id');
            $table->unique('code');
        });

        DB::table('customers')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($customers): void {
                foreach ($customers as $customer) {
                    $code = 'CUST-'.str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT);

                    DB::table('customers')
                        ->where('id', (int) $customer->id)
                        ->update([
                            'code' => $code,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_code_unique');
            $table->dropColumn('code');
        });
    }
};
