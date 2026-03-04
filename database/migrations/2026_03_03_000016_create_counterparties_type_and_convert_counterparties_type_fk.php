<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties_type', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Наименование типа контрагента');
            $table->timestamps();
        });

        DB::table('counterparties_type')->insert([
            [
                'name' => 'ООО',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ИП',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Физ. лицов',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Самозанятый',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('counterparties', function (Blueprint $table) {
            $table->unsignedBigInteger('type_new')->nullable()->after('id')->comment('Тип контрагента (FK)');
        });

        $oooId = DB::table('counterparties_type')->where('name', 'ООО')->value('id');
        $fizId = DB::table('counterparties_type')->where('name', 'Физ. лицов')->value('id');

        DB::table('counterparties')->where('type', 'legal')->update(['type_new' => $oooId]);
        DB::table('counterparties')->where('type', 'individual')->update(['type_new' => $fizId]);
        DB::table('counterparties')->whereNull('type_new')->update(['type_new' => $oooId]);

        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('counterparties', function (Blueprint $table) {
            $table->renameColumn('type_new', 'type');
        });

        Schema::table('counterparties', function (Blueprint $table) {
            $table->foreign('type')->references('id')->on('counterparties_type')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropForeign(['type']);
        });

        Schema::table('counterparties', function (Blueprint $table) {
            $table->unsignedBigInteger('type_old')->nullable()->after('id');
        });

        $oooId = DB::table('counterparties_type')->where('name', 'ООО')->value('id');
        $ipId = DB::table('counterparties_type')->where('name', 'ИП')->value('id');
        $fizId = DB::table('counterparties_type')->where('name', 'Физ. лицов')->value('id');
        $samozId = DB::table('counterparties_type')->where('name', 'Самозанятый')->value('id');

        DB::table('counterparties')->where('type', $oooId)->update(['type_old' => 1]);
        DB::table('counterparties')->where('type', $ipId)->update(['type_old' => 1]);
        DB::table('counterparties')->where('type', $fizId)->update(['type_old' => 2]);
        DB::table('counterparties')->where('type', $samozId)->update(['type_old' => 2]);

        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('counterparties', function (Blueprint $table) {
            $table->enum('type', ['legal', 'individual'])->default('legal')->after('id')->comment('юр. лицо / физ. лицо');
        });

        DB::table('counterparties')->where('type_old', 1)->update(['type' => 'legal']);
        DB::table('counterparties')->where('type_old', 2)->update(['type' => 'individual']);
        DB::table('counterparties')->whereNull('type')->update(['type' => 'legal']);

        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn('type_old');
        });

        Schema::dropIfExists('counterparties_type');
    }
};
