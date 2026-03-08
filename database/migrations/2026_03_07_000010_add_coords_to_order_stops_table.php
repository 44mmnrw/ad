<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_stops', function (Blueprint $table) {
            if (! Schema::hasColumn('order_stops', 'city')) {
                $table->string('city', 128)->nullable()->after('address');
            }

            if (! Schema::hasColumn('order_stops', 'lat')) {
                $table->decimal('lat', 10, 6)->nullable()->after('city');
            }

            if (! Schema::hasColumn('order_stops', 'lng')) {
                $table->decimal('lng', 10, 6)->nullable()->after('lat');
            }
        });

        DB::table('order_stops')
            ->select('id', 'address', 'notes', 'city', 'lat', 'lng')
            ->orderBy('id')
            ->get()
            ->each(function (object $stop): void {
                $notes = json_decode((string) ($stop->notes ?? ''), true);
                $meta = is_array($notes) ? $notes : [];

                $city = trim((string) ($meta['city'] ?? ''));
                $address = trim((string) ($meta['address'] ?? ''));
                $fullAddress = trim((string) ($stop->address ?? ''));

                if ($city === '' && $fullAddress !== '') {
                    $city = trim((string) explode(',', $fullAddress)[0]);
                }

                if ($address === '') {
                    if ($city !== '' && str_starts_with(mb_strtolower($fullAddress), mb_strtolower($city))) {
                        $parts = explode(',', $fullAddress, 2);
                        $address = trim((string) ($parts[1] ?? ''));
                    }

                    if ($address === '') {
                        $address = $fullAddress;
                    }
                }

                $lat = is_numeric($meta['lat'] ?? null) ? (float) $meta['lat'] : null;
                $lng = is_numeric($meta['lng'] ?? null) ? (float) $meta['lng'] : null;

                DB::table('order_stops')
                    ->where('id', $stop->id)
                    ->update([
                        'address' => $address !== '' ? $address : $fullAddress,
                        'city' => $city !== '' ? $city : null,
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('order_stops', function (Blueprint $table) {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('order_stops', 'city') ? 'city' : null,
                Schema::hasColumn('order_stops', 'lat') ? 'lat' : null,
                Schema::hasColumn('order_stops', 'lng') ? 'lng' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
