<?php

namespace Tests\Unit;

use App\Services\RouteDistanceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteDistanceServiceTest extends TestCase
{
    public function test_it_returns_zero_for_identical_points(): void
    {
        $service = new RouteDistanceService();

        $distance = $service->calculateKilometers(55.7558, 37.6176, 55.7558, 37.6176);

        $this->assertSame(0, $distance);
    }

    public function test_it_calculates_expected_direct_distance_between_cities(): void
    {
        $service = new RouteDistanceService();

        $moscowToSpb = $service->calculateKilometers(55.7558, 37.6176, 59.9343, 30.3351);
        $spbToMoscow = $service->calculateKilometers(59.9343, 30.3351, 55.7558, 37.6176);

        $this->assertSame($moscowToSpb, $spbToMoscow);
        $this->assertGreaterThan(600, $moscowToSpb);
        $this->assertLessThan(700, $moscowToSpb);
    }

    public function test_it_prefers_router_api_distance_when_available(): void
    {
        config()->set('services.yandex_maps.router_api_key', 'test-router-key');
        config()->set('services.yandex_maps.router_url', 'https://api.routing.yandex.net/v2/route');

        Http::fake([
            'https://api.routing.yandex.net/v2/route*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'RouteMetaData' => [
                                'Distance' => [
                                    'value' => 712345,
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new RouteDistanceService();

        $result = $service->calculateRouteDistance(55.7558, 37.6176, 59.9343, 30.3351);

        $this->assertSame(712, $result['distance_km']);
        $this->assertSame('yandex-routing', $result['source']);
    }
}