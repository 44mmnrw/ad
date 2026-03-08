<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Services\YandexMaps\GeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $orders = array_values($this->orders());

        if ($statusFilter !== 'all') {
            $orders = array_values(array_filter(
                $orders,
                static fn (array $order): bool => ($order['status_code'] ?? null) === $statusFilter
            ));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $orders = array_values(array_filter($orders, static function (array $order) use ($needle): bool {
                $haystack = implode(' ', [
                    (string) ($order['number'] ?? ''),
                    (string) ($order['route']['from']['city'] ?? ''),
                    (string) ($order['route']['to']['city'] ?? ''),
                    (string) ($order['driver']['name'] ?? ''),
                    (string) ($order['driver']['car'] ?? ''),
                ]);

                return str_contains(mb_strtolower($haystack), $needle);
            }));
        }

        return view('orders.index', [
            'orders' => $orders,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function show(int $order): View
    {
        $dbOrder = $this->loadOrderFromDatabase($order);
        $currentOrder = $dbOrder ?? ($this->orders()[$order] ?? $this->orders()[1]);
        $yandexJsApiKey = $this->resolveYandexMapsSetting([
            'js_api_key',
            'js_http_geocoder_api_key',
        ]);

        return view('orders.show', [
            'order' => $currentOrder,
            'routeMapPoints' => $this->buildRouteMapPoints($currentOrder),
            'yandexJsApiKey' => $yandexJsApiKey,
        ]);
    }

    public function create(): View
    {
        return view('orders.form-page', [
            'order' => null,
            'counterparties' => $this->counterpartyOptions(),
            'pageTitle' => 'Новая заявка',
            'backRoute' => route('orders.index'),
            'backLabel' => '← Вернуться к списку',
            'metaTitle' => 'Создать заявку',
        ]);
    }

    public function edit(int $order): View
    {
        $dbOrder = $this->loadOrderFromDatabase($order);

        if ($dbOrder !== null) {
            $currentOrder = $dbOrder;
            $backRoute = route('orders.index');
            $backLabel = '← Вернуться к списку';
        } else {
            $currentOrder = $this->orders()[$order] ?? $this->orders()[1];
            $backRoute = route('orders.show', $currentOrder['id']);
            $backLabel = '← Назад к заявке';
        }

        return view('orders.form-page', [
            'order' => $currentOrder,
            'counterparties' => $this->counterpartyOptions(),
            'pageTitle' => 'Редактирование заявки '.$currentOrder['number'],
            'backRoute' => $backRoute,
            'backLabel' => $backLabel,
            'metaTitle' => 'Редактирование заявки',
        ]);
    }

    public function store(Request $request, GeocodeService $geocodeService): RedirectResponse
    {
        $validated = $this->validateOrderPayload($request);

        $orderPartyContext = $this->resolveOrderPartyContext();

        if ($orderPartyContext === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'from_city' => 'Невозможно сохранить заявку: отсутствуют заказчики/контрагенты. Создайте хотя бы одного заказчика.',
                ]);
        }

        $customerId = (int) $orderPartyContext['customer_id'];
        $customerCounterpartyId = (int) $orderPartyContext['counterparty_id'];

        $orderId = DB::transaction(function () use ($validated, $customerId, $customerCounterpartyId, $geocodeService): int {
            $now = now();

            $orderId = (int) DB::table('orders')->insertGetId([
                'number' => $this->generateOrderNumber(),
                'customer_id' => $customerId,
                'manager_id' => Auth::id(),
                'cargo_description' => null,
                'cargo_weight' => null,
                'cargo_volume' => null,
                'cargo_type' => null,
                'distance_km' => $this->parseDistanceKm($validated['distance'] ?? null),
                'status' => 'new',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncOrderStops($orderId, $customerCounterpartyId, $validated, $geocodeService);

            return $orderId;
        });

        return redirect()
            ->route('orders.edit', $orderId)
            ->with('status', 'Заявка сохранена. Точки маршрута записаны в order_stops.');
    }

    public function update(Request $request, int $order, GeocodeService $geocodeService): RedirectResponse
    {
        $validated = $this->validateOrderPayload($request);

        $orderPartyContext = $this->resolveOrderPartyContext();

        if ($orderPartyContext === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'from_city' => 'Невозможно сохранить заявку: отсутствуют заказчики/контрагенты. Создайте хотя бы одного заказчика.',
                ]);
        }

        $customerId = (int) $orderPartyContext['customer_id'];
        $customerCounterpartyId = (int) $orderPartyContext['counterparty_id'];

        $orderExists = DB::table('orders')->where('id', $order)->exists();

        if (! $orderExists) {
            return $this->store($request, $geocodeService);
        }

        DB::transaction(function () use ($order, $validated, $customerId, $customerCounterpartyId, $geocodeService): void {
            DB::table('orders')
                ->where('id', $order)
                ->update([
                    'customer_id' => $customerId,
                    'distance_km' => $this->parseDistanceKm($validated['distance'] ?? null),
                    'manager_id' => Auth::id(),
                    'updated_at' => now(),
                ]);

            $this->syncOrderStops($order, $customerCounterpartyId, $validated, $geocodeService);
        });

        return redirect()
            ->route('orders.edit', $order)
            ->with('status', 'Изменения заявки сохранены. Точки маршрута обновлены в order_stops.');
    }

    public function geocodeRoute(Request $request, GeocodeService $geocodeService): JsonResponse
    {
        $validated = $request->validate([
            'from_city' => ['required', 'string', 'max:120'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'to_city' => ['required', 'string', 'max:120'],
            'to_address' => ['nullable', 'string', 'max:255'],
        ]);

        $fromQuery = $this->buildGeocodeQuery($validated['from_city'], $validated['from_address'] ?? null);
        $toQuery = $this->buildGeocodeQuery($validated['to_city'], $validated['to_address'] ?? null);

        try {
            $fromPoint = $geocodeService->geocode($fromQuery);
            $toPoint = $geocodeService->geocode($toQuery);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'from' => $fromPoint,
            'to' => $toPoint,
        ]);
    }

    public function suggestRouteCities(Request $request, GeocodeService $geocodeService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        try {
            $suggestions = $geocodeService->suggestCities($validated['query']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    public function suggestRouteAddresses(Request $request, GeocodeService $geocodeService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $suggestions = $geocodeService->suggestAddresses(
                $validated['query'],
                $validated['city'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Demo-данные до подключения БД.
     *
     * @return array<int, array<string, mixed>>
     */
    private function orders(): array
    {
        return [
            1 => [
                'id' => 1,
                'number' => 'АД-20240315-001',
                'created_at' => '15.03.2024',
                'status' => 'В пути',
                'status_code' => 'in_transit',
                'distance' => '480 км',
                'cost' => 68500,
                'route' => [
                    'from' => ['city' => 'Москва', 'address' => 'ул. Ленина, 12', 'lat' => 55.7558, 'lng' => 37.6176],
                    'to' => ['city' => 'Санкт-Петербург', 'address' => 'пр. Невский, 45', 'lat' => 59.9343, 'lng' => 30.3351],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '10:30', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '12:45', 'state' => 'active'],
                    ['title' => 'Разгрузка', 'time' => '—', 'state' => 'pending'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/abc123xyz',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Иванов Иван Иванович',
                    'car' => 'Газель, А123БВ 77',
                    'phone' => '+7 (999) 123-45-67',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'Петров Сергей Николаевич',
                    'phone' => '+7 (911) 000-11-22',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'Сидорова Анна Петровна',
                    'phone' => '+7 (921) 333-44-55',
                ],
            ],
            2 => [
                'id' => 2,
                'number' => 'АД-20240315-002',
                'created_at' => '15.03.2024',
                'status' => 'Загрузка',
                'status_code' => 'loading',
                'distance' => '820 км',
                'cost' => 94300,
                'route' => [
                    'from' => ['city' => 'Казань', 'address' => 'ул. Баумана, 10', 'lat' => 55.7961, 'lng' => 49.1064],
                    'to' => ['city' => 'Москва', 'address' => 'Ленинградский проспект, 37', 'lat' => 55.7905, 'lng' => 37.5448],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '09:20', 'state' => 'active'],
                    ['title' => 'В пути', 'time' => '—', 'state' => 'pending'],
                    ['title' => 'Разгрузка', 'time' => '—', 'state' => 'pending'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/ktx620plm',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Смирнов Петр Васильевич',
                    'car' => 'MAN TGX, В456ГД 16',
                    'phone' => '+7 (977) 456-78-90',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'ГК Волга-Трейд',
                    'phone' => '+7 (843) 220-11-11',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'ООО Логистик Центр',
                    'phone' => '+7 (495) 123-12-12',
                ],
            ],
            3 => [
                'id' => 3,
                'number' => 'АД-20240314-015',
                'created_at' => '14.03.2024',
                'status' => 'Завершено',
                'status_code' => 'completed',
                'distance' => '1420 км',
                'cost' => 157900,
                'route' => [
                    'from' => ['city' => 'Новосибирск', 'address' => 'ул. Фрунзе, 5', 'lat' => 55.0084, 'lng' => 82.9357],
                    'to' => ['city' => 'Екатеринбург', 'address' => 'ул. Малышева, 88', 'lat' => 56.8389, 'lng' => 60.6057],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '11:00', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '20:45', 'state' => 'done'],
                    ['title' => 'Разгрузка', 'time' => '09:15', 'state' => 'done'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/nsk-ekb-015',
                'driver_link_expires' => 'Срок действия ссылки истёк',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Козлов Дмитрий Андреевич',
                    'car' => 'Volvo FH, С789ЕЖ 54',
                    'phone' => '+7 (923) 700-55-44',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'СибТрансСервис',
                    'phone' => '+7 (383) 312-12-12',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'УралСклад',
                    'phone' => '+7 (343) 410-10-10',
                ],
            ],
            4 => [
                'id' => 4,
                'number' => 'АД-20240315-003',
                'created_at' => '15.03.2024',
                'status' => 'Разгрузка',
                'status_code' => 'unloading',
                'distance' => '285 км',
                'cost' => 41200,
                'route' => [
                    'from' => ['city' => 'Краснодар', 'address' => 'ул. Северная, 201', 'lat' => 45.0355, 'lng' => 38.9753],
                    'to' => ['city' => 'Ростов-на-Дону', 'address' => 'Будённовский пр., 98', 'lat' => 47.2357, 'lng' => 39.7015],
                ],
                'progress' => [
                    ['title' => 'Загрузка', 'time' => '07:50', 'state' => 'done'],
                    ['title' => 'В пути', 'time' => '10:35', 'state' => 'done'],
                    ['title' => 'Разгрузка', 'time' => '13:10', 'state' => 'active'],
                ],
                'driver_link' => 'https://avtodostavka.ru/d/krd-rnd-003',
                'driver_link_expires' => 'Ссылка активна до: 16.03.2024, 23:59',
                'driver' => [
                    'title' => 'Водитель',
                    'emoji' => '🚗',
                    'name' => 'Соколов Андрей Николаевич',
                    'car' => 'Mercedes Actros, Т012МН 23',
                    'phone' => '+7 (918) 321-00-77',
                ],
                'sender' => [
                    'title' => 'Грузоотправитель',
                    'emoji' => '📦',
                    'name' => 'КубаньАгро',
                    'phone' => '+7 (861) 222-32-32',
                ],
                'receiver' => [
                    'title' => 'Грузополучатель',
                    'emoji' => '📬',
                    'name' => 'РостовЛогистик',
                    'phone' => '+7 (863) 303-03-03',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return list<array{role: string, label: string, lat: float, lng: float}>
     */
    private function buildRouteMapPoints(array $order): array
    {
        $from = is_array($order['route']['from'] ?? null) ? $order['route']['from'] : null;
        $to = is_array($order['route']['to'] ?? null) ? $order['route']['to'] : null;
        $intermediate = is_array($order['route']['intermediate'] ?? null) ? $order['route']['intermediate'] : [];

        $points = [];

        $appendPoint = static function (mixed $source, string $role, string $fallbackLabel) use (&$points): void {
            if (! is_array($source)) {
                return;
            }

            $lat = $source['lat'] ?? null;
            $lng = $source['lng'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return;
            }

            $city = trim((string) ($source['city'] ?? ''));
            $address = trim((string) ($source['address'] ?? ''));
            $label = $city !== '' ? $city : $fallbackLabel;

            if ($address !== '') {
                $label .= ', '.$address;
            }

            $points[] = [
                'role' => $role,
                'label' => $label,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        };

        $appendPoint($from, 'from', 'Отправление');

        foreach ($intermediate as $index => $stop) {
            $type = is_array($stop) && ($stop['type'] ?? null) === 'loading' ? 'Загрузка' : 'Выгрузка';
            $appendPoint($stop, 'intermediate', $type.' #'.($index + 1));
        }

        $appendPoint($to, 'to', 'Назначение');

        return $points;
    }

    private function buildGeocodeQuery(string $city, ?string $address): string
    {
        $city = trim($city);
        $address = trim((string) $address);

        if ($address === '') {
            return $city;
        }

        return $city.', '.$address;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrderPayload(Request $request): array
    {
        return $request->validate([
            'from_city' => ['required', 'string', 'max:120'],
            'from_address' => ['required', 'string', 'max:255'],
            'to_city' => ['required', 'string', 'max:120'],
            'to_address' => ['required', 'string', 'max:255'],
            'intermediate_cities' => ['nullable', 'array'],
            'intermediate_cities.*' => ['nullable', 'string', 'max:120'],
            'intermediate_addresses' => ['nullable', 'array'],
            'intermediate_addresses.*' => ['nullable', 'string', 'max:255'],
            'intermediate_types' => ['nullable', 'array'],
            'intermediate_types.*' => ['nullable', 'in:loading,unloading'],
            'intermediate_counterparty_ids' => ['nullable', 'array'],
            'intermediate_counterparty_ids.*' => ['nullable', 'integer', 'exists:counterparties,id'],
            'intermediate_lats' => ['nullable', 'array'],
            'intermediate_lats.*' => ['nullable', 'numeric'],
            'intermediate_lngs' => ['nullable', 'array'],
            'intermediate_lngs.*' => ['nullable', 'numeric'],
            'distance' => ['nullable', 'string', 'max:20'],
            'created_at' => ['nullable', 'string', 'max:30'],
            'from_lat' => ['nullable', 'numeric'],
            'from_lng' => ['nullable', 'numeric'],
            'to_lat' => ['nullable', 'numeric'],
            'to_lng' => ['nullable', 'numeric'],
        ]);
    }

    /**
     * @return array{customer_id: int, counterparty_id: int}|null
     */
    private function resolveOrderPartyContext(): ?array
    {
        if (Schema::hasTable('customers')) {
            $customer = DB::table('customers')
                ->select('id', 'counterparty_id')
                ->orderBy('id')
                ->first();

            if ($customer !== null && is_numeric($customer->id) && is_numeric($customer->counterparty_id)) {
                return [
                    'customer_id' => (int) $customer->id,
                    'counterparty_id' => (int) $customer->counterparty_id,
                ];
            }
        }

        $counterpartyId = DB::table('counterparties')->orderBy('id')->value('id');

        if (! is_numeric($counterpartyId)) {
            return null;
        }

        $counterpartyId = (int) $counterpartyId;

        if (Schema::hasTable('customers')) {
            $customerId = DB::table('customers')
                ->where('counterparty_id', $counterpartyId)
                ->value('id');

            if (! is_numeric($customerId)) {
                $now = now();

                DB::table('customers')->insert([
                    'counterparty_id' => $counterpartyId,
                    'code' => 'CUST-CP-'.str_pad((string) $counterpartyId, 6, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $customerId = DB::table('customers')
                    ->where('counterparty_id', $counterpartyId)
                    ->value('id');
            }

            if (! is_numeric($customerId)) {
                return null;
            }

            return [
                'customer_id' => (int) $customerId,
                'counterparty_id' => $counterpartyId,
            ];
        }

        return [
            'customer_id' => $counterpartyId,
            'counterparty_id' => $counterpartyId,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function syncOrderStops(int $orderId, int $defaultCounterpartyId, array $validated, GeocodeService $geocodeService): void
    {
        DB::table('order_stops')->where('order_id', $orderId)->delete();

        $fromCoordinates = $this->resolveStopCoordinates(
            city: (string) ($validated['from_city'] ?? ''),
            address: (string) ($validated['from_address'] ?? ''),
            lat: $validated['from_lat'] ?? null,
            lng: $validated['from_lng'] ?? null,
            geocodeService: $geocodeService,
        );

        $toCoordinates = $this->resolveStopCoordinates(
            city: (string) ($validated['to_city'] ?? ''),
            address: (string) ($validated['to_address'] ?? ''),
            lat: $validated['to_lat'] ?? null,
            lng: $validated['to_lng'] ?? null,
            geocodeService: $geocodeService,
        );

        $intermediateStops = $this->normalizeIntermediateStops($validated, $defaultCounterpartyId);

        $intermediateStops = array_map(function (array $stop) use ($geocodeService): array {
            $coordinates = $this->resolveStopCoordinates(
                city: $stop['city'],
                address: $stop['address'],
                lat: $stop['lat'] ?? null,
                lng: $stop['lng'] ?? null,
                geocodeService: $geocodeService,
            );

            $stop['lat'] = $coordinates['lat'];
            $stop['lng'] = $coordinates['lng'];

            return $stop;
        }, $intermediateStops);

        $now = now();
        $rows = [];
        $sequence = 1;

        $rows[] = $this->makeStopRow(
            orderId: $orderId,
            counterpartyId: $defaultCounterpartyId,
            type: 'loading',
            sequence: $sequence++,
            city: (string) ($validated['from_city'] ?? ''),
            address: (string) ($validated['from_address'] ?? ''),
            lat: $fromCoordinates['lat'],
            lng: $fromCoordinates['lng'],
            role: 'from',
            now: $now,
        );

        foreach ($intermediateStops as $stop) {
            $rows[] = $this->makeStopRow(
                orderId: $orderId,
                counterpartyId: $stop['counterparty_id'],
                type: $stop['type'],
                sequence: $sequence++,
                city: $stop['city'],
                address: $stop['address'],
                lat: $stop['lat'] ?? null,
                lng: $stop['lng'] ?? null,
                role: 'intermediate',
                now: $now,
            );
        }

        $rows[] = $this->makeStopRow(
            orderId: $orderId,
            counterpartyId: $defaultCounterpartyId,
            type: 'unloading',
            sequence: $sequence,
            city: (string) ($validated['to_city'] ?? ''),
            address: (string) ($validated['to_address'] ?? ''),
            lat: $toCoordinates['lat'],
            lng: $toCoordinates['lng'],
            role: 'to',
            now: $now,
        );

        DB::table('order_stops')->insert($rows);
    }

    private function composeStopAddress(string $city, string $address): string
    {
        $city = trim($city);
        $address = trim($address);

        if ($city === '') {
            return $address;
        }

        if ($address === '') {
            return $city;
        }

        return $city.', '.$address;
    }

    private function parseDistanceKm(mixed $distance): ?int
    {
        $raw = trim((string) $distance);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return (int) $digits;
    }

    private function generateOrderNumber(): string
    {
        return 'АД-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadOrderFromDatabase(int $orderId): ?array
    {
        $order = DB::table('orders')->where('id', $orderId)->first();

        if ($order === null) {
            return null;
        }

        $stops = DB::table('order_stops')
            ->where('order_id', $orderId)
            ->orderBy('sequence')
            ->get();

        $fromStop = $stops->first();
        $toStop = $stops->last() ?? $stops->first();

        $intermediateStopsCollection = $stops->count() > 2
            ? $stops->slice(1, $stops->count() - 2)
            : collect();

        $fromMeta = $fromStop !== null ? $this->decodeStopNotes($fromStop->notes ?? null) : [];
        $toMeta = $toStop !== null ? $this->decodeStopNotes($toStop->notes ?? null) : [];

        $intermediateStops = $intermediateStopsCollection
            ->map(function ($stop): array {
                $meta = $this->decodeStopNotes($stop->notes ?? null);

                return [
                    'type' => in_array((string) ($stop->type ?? 'unloading'), ['loading', 'unloading'], true) ? (string) $stop->type : 'unloading',
                    'counterparty_id' => is_numeric($stop->counterparty_id ?? null) ? (int) $stop->counterparty_id : null,
                    'city' => $this->resolveStopCity($stop, $meta),
                    'address' => $this->resolveStopAddress($stop, $meta),
                    'lat' => $this->resolveStopCoordinate($stop->lat ?? null, $meta['lat'] ?? null),
                    'lng' => $this->resolveStopCoordinate($stop->lng ?? null, $meta['lng'] ?? null),
                ];
            })
            ->values()
            ->all();

        $createdAt = $order->created_at ? date('d.m.Y', strtotime((string) $order->created_at)) : now()->format('d.m.Y');

        $statusCode = (string) ($order->status ?? 'new');

        return [
            'id' => (int) $order->id,
            'number' => (string) ($order->number ?? 'АД-—'),
            'created_at' => $createdAt,
            'status' => $this->statusLabel($statusCode),
            'status_code' => $statusCode,
            'distance' => $order->distance_km !== null ? ((int) $order->distance_km).' км' : '',
            'cost' => 0,
            'autodostavka_cost' => 0,
            'route' => [
                'from' => [
                    'counterparty_id' => is_numeric($fromStop->counterparty_id ?? null) ? (int) $fromStop->counterparty_id : null,
                    'city' => $fromStop !== null ? $this->resolveStopCity($fromStop, $fromMeta) : '',
                    'address' => $fromStop !== null ? $this->resolveStopAddress($fromStop, $fromMeta) : '',
                    'lat' => $fromStop !== null ? $this->resolveStopCoordinate($fromStop->lat ?? null, $fromMeta['lat'] ?? null) : null,
                    'lng' => $fromStop !== null ? $this->resolveStopCoordinate($fromStop->lng ?? null, $fromMeta['lng'] ?? null) : null,
                ],
                'to' => [
                    'counterparty_id' => is_numeric($toStop->counterparty_id ?? null) ? (int) $toStop->counterparty_id : null,
                    'city' => $toStop !== null ? $this->resolveStopCity($toStop, $toMeta) : '',
                    'address' => $toStop !== null ? $this->resolveStopAddress($toStop, $toMeta) : '',
                    'lat' => $toStop !== null ? $this->resolveStopCoordinate($toStop->lat ?? null, $toMeta['lat'] ?? null) : null,
                    'lng' => $toStop !== null ? $this->resolveStopCoordinate($toStop->lng ?? null, $toMeta['lng'] ?? null) : null,
                ],
                'intermediate' => $intermediateStops,
            ],
            'progress' => [
                ['title' => 'Загрузка', 'time' => '—', 'state' => 'pending'],
                ['title' => 'В пути', 'time' => '—', 'state' => 'pending'],
                ['title' => 'Разгрузка', 'time' => '—', 'state' => 'pending'],
            ],
            'driver_link' => '—',
            'driver_link_expires' => 'Ссылка для водителя не сформирована',
            'driver' => [
                'name' => null,
                'car' => '—',
                'phone' => '—',
            ],
            'sender' => [
                'name' => $this->counterpartyNameById(is_numeric($fromStop->counterparty_id ?? null) ? (int) $fromStop->counterparty_id : null),
                'phone' => '—',
            ],
            'receiver' => [
                'name' => $this->counterpartyNameById(is_numeric($toStop->counterparty_id ?? null) ? (int) $toStop->counterparty_id : null),
                'phone' => '—',
            ],
            'carrier' => [
                'name' => '—',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStopNotes(?string $notes): array
    {
        if ($notes === null || trim($notes) === '') {
            return [];
        }

        $decoded = json_decode($notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function resolveStopCity(object $stop, array $meta): string
    {
        $cityFromColumn = trim((string) ($stop->city ?? ''));

        if ($cityFromColumn !== '') {
            return $cityFromColumn;
        }

        $city = $meta['city'] ?? null;

        if (is_string($city) && trim($city) !== '') {
            return trim($city);
        }

        $addressString = trim((string) ($stop->address ?? ''));

        if ($addressString === '') {
            return '';
        }

        return trim((string) explode(',', $addressString)[0]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function resolveStopAddress(object $stop, array $meta): string
    {
        $addressFromColumn = trim((string) ($stop->address ?? ''));

        if ($addressFromColumn !== '') {
            return $addressFromColumn;
        }

        $addressValue = $meta['address'] ?? null;

        if (is_string($addressValue)) {
            return trim($addressValue);
        }

        return $addressFromColumn;
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return list<array{type: string, counterparty_id: int, city: string, address: string, lat: float|null, lng: float|null}>
     */
    private function normalizeIntermediateStops(array $validated, int $defaultCounterpartyId): array
    {
        $types = is_array($validated['intermediate_types'] ?? null)
            ? $validated['intermediate_types']
            : [];
        $counterpartyIds = is_array($validated['intermediate_counterparty_ids'] ?? null)
            ? $validated['intermediate_counterparty_ids']
            : [];
        $lats = is_array($validated['intermediate_lats'] ?? null)
            ? $validated['intermediate_lats']
            : [];
        $lngs = is_array($validated['intermediate_lngs'] ?? null)
            ? $validated['intermediate_lngs']
            : [];
        $cities = is_array($validated['intermediate_cities'] ?? null)
            ? $validated['intermediate_cities']
            : [];
        $addresses = is_array($validated['intermediate_addresses'] ?? null)
            ? $validated['intermediate_addresses']
            : [];

        $max = max(count($cities), count($addresses));
        $result = [];

        for ($index = 0; $index < $max; $index++) {
            $typeRaw = (string) ($types[$index] ?? 'unloading');
            $type = in_array($typeRaw, ['loading', 'unloading'], true) ? $typeRaw : 'unloading';
            $counterpartyId = $this->resolveStopCounterpartyId($counterpartyIds[$index] ?? null, $defaultCounterpartyId);
            $city = trim((string) ($cities[$index] ?? ''));
            $address = trim((string) ($addresses[$index] ?? ''));

            if ($city === '' && $address === '') {
                continue;
            }

            $result[] = [
                'type' => $type,
                'counterparty_id' => $counterpartyId,
                'city' => $city,
                'address' => $address,
                'lat' => $this->normalizeCoordinate($lats[$index] ?? null),
                'lng' => $this->normalizeCoordinate($lngs[$index] ?? null),
            ];
        }

        return $result;
    }

    private function makeStopRow(
        int $orderId,
        int $counterpartyId,
        string $type,
        int $sequence,
        string $city,
        string $address,
        mixed $lat,
        mixed $lng,
        string $role,
        mixed $now,
    ): array {
        $notes = json_encode([
            'role' => $role,
            'city' => $city,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'order_id' => $orderId,
            'counterparty_id' => $counterpartyId,
            'type' => $type,
            'city' => $city !== '' ? $city : null,
            'address' => $address !== '' ? $address : null,
            'lat' => $this->normalizeCoordinate($lat),
            'lng' => $this->normalizeCoordinate($lng),
            'planned_at' => $now,
            'sequence' => $sequence,
            'cargo_description' => null,
            'cargo_weight' => null,
            'cargo_volume' => null,
            'notes' => $notes,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function resolveStopCoordinates(string $city, string $address, mixed $lat, mixed $lng, GeocodeService $geocodeService): array
    {
        if (is_numeric($lat) && is_numeric($lng)) {
            return [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        $query = $this->buildGeocodeQuery($city, $address);

        if (trim($query) === '') {
            return [
                'lat' => null,
                'lng' => null,
            ];
        }

        try {
            $point = $geocodeService->geocode($query);

            return [
                'lat' => is_numeric($point['lat'] ?? null) ? (float) $point['lat'] : null,
                'lng' => is_numeric($point['lng'] ?? null) ? (float) $point['lng'] : null,
            ];
        } catch (RuntimeException) {
            return [
                'lat' => null,
                'lng' => null,
            ];
        }
    }

    private function normalizeCoordinate(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function resolveStopCoordinate(mixed $columnValue, mixed $legacyValue): ?float
    {
        $columnCoordinate = $this->normalizeCoordinate($columnValue);

        if ($columnCoordinate !== null) {
            return $columnCoordinate;
        }

        return $this->normalizeCoordinate($legacyValue);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Новая',
            'assigned' => 'Назначена',
            'in_progress' => 'В работе',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
            default => 'Новая',
        };
    }

    private function counterpartyNameById(?int $counterpartyId): string
    {
        if ($counterpartyId === null) {
            return '—';
        }

        $row = DB::table('counterparties')
            ->select('short_name', 'full_name')
            ->where('id', $counterpartyId)
            ->first();

        if ($row === null) {
            return '—';
        }

        $short = trim((string) ($row->short_name ?? ''));
        $full = trim((string) ($row->full_name ?? ''));

        if ($short !== '') {
            return $short;
        }

        if ($full !== '') {
            return $full;
        }

        return '—';
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function counterpartyOptions(): array
    {
        $rows = DB::table('counterparties')
            ->select('id', 'short_name', 'full_name', 'inn')
            ->orderBy('short_name')
            ->orderBy('full_name')
            ->get();

        return $rows->map(static function ($row): array {
            $short = trim((string) ($row->short_name ?? ''));
            $full = trim((string) ($row->full_name ?? ''));
            $inn = trim((string) ($row->inn ?? ''));

            $baseLabel = $short !== '' ? $short : ($full !== '' ? $full : ('Контрагент #'.(int) $row->id));
            $label = $inn !== '' ? $baseLabel.' (ИНН '.$inn.')' : $baseLabel;

            return [
                'id' => (int) $row->id,
                'label' => $label,
            ];
        })->values()->all();
    }

    private function resolveStopCounterpartyId(mixed $submittedId, int $defaultCounterpartyId): int
    {
        if (! is_numeric($submittedId)) {
            return $defaultCounterpartyId;
        }

        $id = (int) $submittedId;
        $exists = DB::table('counterparties')->where('id', $id)->exists();

        return $exists ? $id : $defaultCounterpartyId;
    }

    private function resolveYandexMapsSetting(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) IntegrationSetting::getValue('yandex_maps', $key, config("services.yandex_maps.{$key}")));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
