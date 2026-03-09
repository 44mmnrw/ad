<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table): void {
            $table->text('legal_address')->nullable()->comment('Юридический адрес')->change();
            $table->text('actual_address')->nullable()->comment('Фактический адрес')->change();

            $table->string('legal_postal_code', 10)->nullable()->after('actual_address')->comment('Индекс юридического адреса');
            $table->string('legal_region', 150)->nullable()->after('legal_postal_code')->comment('Регион юридического адреса');
            $table->string('legal_city', 150)->nullable()->after('legal_region')->comment('Город юридического адреса');
            $table->string('legal_settlement', 150)->nullable()->after('legal_city')->comment('Населённый пункт юридического адреса');
            $table->string('legal_street', 150)->nullable()->after('legal_settlement')->comment('Улица юридического адреса');
            $table->string('legal_house', 50)->nullable()->after('legal_street')->comment('Дом юридического адреса');
            $table->string('legal_block', 50)->nullable()->after('legal_house')->comment('Корпус/строение юридического адреса');
            $table->string('legal_flat', 50)->nullable()->after('legal_block')->comment('Квартира/офис юридического адреса');
            $table->string('legal_fias_id', 50)->nullable()->after('legal_flat')->comment('FIAS ID юридического адреса');
            $table->string('legal_kladr_id', 20)->nullable()->after('legal_fias_id')->comment('КЛАДР ID юридического адреса');
            $table->decimal('legal_geo_lat', 10, 7)->nullable()->after('legal_kladr_id')->comment('Широта юридического адреса');
            $table->decimal('legal_geo_lon', 10, 7)->nullable()->after('legal_geo_lat')->comment('Долгота юридического адреса');
            $table->unsignedTinyInteger('legal_qc')->nullable()->after('legal_geo_lon')->comment('Код качества юридического адреса');
            $table->unsignedTinyInteger('legal_qc_geo')->nullable()->after('legal_qc')->comment('Точность координат юридического адреса');
            $table->boolean('legal_address_invalid')->nullable()->after('legal_qc_geo')->comment('Признак недостоверности юридического адреса');
            $table->json('legal_address_data')->nullable()->after('legal_address_invalid')->comment('Полный структурированный payload юридического адреса из DaData');
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_postal_code',
                'legal_region',
                'legal_city',
                'legal_settlement',
                'legal_street',
                'legal_house',
                'legal_block',
                'legal_flat',
                'legal_fias_id',
                'legal_kladr_id',
                'legal_geo_lat',
                'legal_geo_lon',
                'legal_qc',
                'legal_qc_geo',
                'legal_address_invalid',
                'legal_address_data',
            ]);

            $table->string('legal_address')->nullable()->comment('Юридический адрес')->change();
            $table->string('actual_address')->nullable()->comment('Фактический адрес')->change();
        });
    }
};
