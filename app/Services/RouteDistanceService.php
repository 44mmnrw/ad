<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class RouteDistanceService
{
    private const INTEGRATION = 'yandex_maps';

    /**
     * @return array{distance_km: int, source: string}
     */
    public function calculateRouteDistance(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $roadDistanceKm = $this->calculateRoadDistanceKilometers($fromLat, $fromLng, $toLat, $toLng);

        if ($roadDistanceKm !== null) {
            return [
                'distance_km' => $roadDistanceKm,
                'source' => 'yandex-routing',
            ];
        }

        return [
            'distance_km' => $this->calculateKilometers($fromLat, $fromLng, $toLat, $toLng),
            'source' => 'geodesic',
        ];
    }

    public function calculateKilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        $earthRadiusKm = 6371.0;

        $fromLatRad = deg2rad($fromLat);
        $toLatRad = deg2rad($toLat);
        $deltaLatRad = deg2rad($toLat - $fromLat);
        $deltaLngRad = deg2rad($toLng - $fromLng);

        $a = sin($deltaLatRad / 2) ** 2
            + cos($fromLatRad) * cos($toLatRad) * sin($deltaLngRad / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return (int) round($earthRadiusKm * $c);
    }

    private function calculateRoadDistanceKilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): ?int
    {
        $apiKey = $this->resolveIntegrationValue('router_api_key');

        if ($apiKey === '') {
            return null;
        }

        $url = (string) config('services.yandex_maps.router_url');
        $timeout = max($this->resolveTimeout(), 1);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, [
                    'apikey' => $apiKey,
                    'lang' => 'ru_RU',
                    'mode' => 'driving',
                    'waypoints' => sprintf('%.6F,%.6F|%.6F,%.6F', $fromLng, $fromLat, $toLng, $toLat),
                ])
                ->throw();
        } catch (RequestException) {
            return null;
        }

        $distanceMeters = $this->extractDistanceMeters($response->json());

        if (! is_numeric($distanceMeters) || (float) $distanceMeters < 0) {
            return null;
        }

        return (int) round(((float) $distanceMeters) / 1000);
    }

    private function resolveTimeout(): int
    {
        $fallback = (int) config('services.yandex_maps.timeout', 10);

        try {
            $rawTimeout = IntegrationSetting::getValue(self::INTEGRATION, 'timeout', $fallback);
        } catch (Throwable) {
            $rawTimeout = $fallback;
        }

        if (is_int($rawTimeout) || is_float($rawTimeout) || (is_string($rawTimeout) && is_numeric($rawTimeout))) {
            return (int) $rawTimeout;
        }

        return $fallback;
    }

    private function resolveIntegrationValue(string $settingKey): string
    {
        $fallback = trim((string) config("services.yandex_maps.{$settingKey}", ''));

        try {
            return trim((string) IntegrationSetting::getValue(self::INTEGRATION, $settingKey, $fallback));
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function extractDistanceMeters(mixed $payload): ?float
    {
        $directCandidates = [
            data_get($payload, 'route.legs.0.summary.lengthInMeters'),
            data_get($payload, 'route.legs.0.summary.length.value'),
            data_get($payload, 'route.legs.0.distance.value'),
            data_get($payload, 'route.distance.value'),
            data_get($payload, 'routes.0.distance.value'),
            data_get($payload, 'routes.0.distance'),
            data_get($payload, 'routes.0.legs.0.distance.value'),
            data_get($payload, 'features.0.properties.RouteMetaData.Distance.value'),
            data_get($payload, 'features.0.properties.summary.distance.value'),
        ];

        foreach ($directCandidates as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        $legCollections = [
            data_get($payload, 'route.legs', []),
            data_get($payload, 'routes.0.legs', []),
        ];

        foreach ($legCollections as $legs) {
            if (! is_array($legs) || $legs === []) {
                continue;
            }

            $sum = 0.0;
            $found = false;

            foreach ($legs as $leg) {
                $legDistance = data_get($leg, 'distance.value', data_get($leg, 'summary.lengthInMeters', data_get($leg, 'summary.length.value')));

                if (! is_numeric($legDistance)) {
                    continue;
                }

                $sum += (float) $legDistance;
                $found = true;
            }

            if ($found) {
                return $sum;
            }
        }

        return null;
    }
}