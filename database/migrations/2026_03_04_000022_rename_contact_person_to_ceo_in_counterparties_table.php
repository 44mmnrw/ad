<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table): void {
            if (Schema::hasColumn('counterparties', 'contact_person') && ! Schema::hasColumn('counterparties', 'ceo')) {
                $table->renameColumn('contact_person', 'ceo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table): void {
            if (Schema::hasColumn('counterparties', 'ceo') && ! Schema::hasColumn('counterparties', 'contact_person')) {
                $table->renameColumn('ceo', 'contact_person');
            }
        });
    }
};
