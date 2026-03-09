<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Services\DaData\FindPartyService;
use App\Services\RouteDistanceService;
use App\Services\YandexMaps\GeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $orders = $this->loadOrdersFromDatabase();

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

    public function show(string $order): View
    {
        $orderId = $this->resolveOrderRouteParameter($order);
        $dbOrder = $this->loadOrderFromDatabase($orderId);

        abort_if($dbOrder === null, 404);

        $currentOrder = $dbOrder;
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

    public function edit(string $order): View
    {
        $orderId = $this->resolveOrderRouteParameter($order);
        $dbOrder = $this->loadOrderFromDatabase($orderId);

        abort_if($dbOrder === null, 404);

        $currentOrder = $dbOrder;
        $backRoute = route('orders.index');
        $backLabel = '← Вернуться к списку';

        return view('orders.form-page', [
            'order' => $currentOrder,
            'counterparties' => $this->counterpartyOptions(),
            'pageTitle' => 'Редактирование заявки '.$currentOrder['number'],
            'backRoute' => $backRoute,
            'backLabel' => $backLabel,
            'metaTitle' => 'Редактирование заявки',
        ]);
    }

    public function store(Request $request, GeocodeService $geocodeService, RouteDistanceService $routeDistanceService): RedirectResponse
    {
        $validated = $this->validateOrderPayload($request);

        $orderPartyContext = $this->resolveOrderPartyContext($validated);

        if ($orderPartyContext === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'customer_name' => 'Заполните обязательные данные заказчика перед сохранением заявки.',
                ]);
        }

        $customerId = (int) $orderPartyContext['customer_id'];
        $customerCounterpartyId = (int) $orderPartyContext['counterparty_id'];
        $orderId = DB::transaction(function () use ($validated, $customerId, $customerCounterpartyId, $geocodeService, $routeDistanceService): int {
            $now = now();

            $orderId = (int) DB::table('orders')->insertGetId([
                'number' => $this->generateOrderNumber(),
                'customer_id' => $customerId,
                'manager_id' => Auth::id(),
                'cargo_description' => null,
                'cargo_weight' => null,
                'cargo_volume' => null,
                'cargo_type' => null,
                'distance_km' => null,
                'status' => 'new',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $routeCoordinates = $this->syncOrderStops($orderId, $customerCounterpartyId, $validated, $geocodeService);

            $distanceResult = $this->resolveRouteDistanceResult($validated, $routeCoordinates, $routeDistanceService);

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'distance_km' => $distanceResult['distance_km'],
                    'updated_at' => $now,
                ]);

            $this->syncCarrierAssignment(
                $orderId,
                is_numeric($validated['carrier_counterparty_id'] ?? null) ? (int) $validated['carrier_counterparty_id'] : null,
                $now,
            );

            return $orderId;
        });

        return redirect()
            ->route('orders.edit', $orderId)
            ->with('status', 'Заявка сохранена. Точки маршрута записаны в order_stops.');
    }

    public function update(Request $request, string $order, GeocodeService $geocodeService, RouteDistanceService $routeDistanceService): RedirectResponse
    {
        $orderId = $this->resolveOrderRouteParameter($order);
        $validated = $this->validateOrderPayload($request);

        $orderPartyContext = $this->resolveOrderPartyContext($validated);

        if ($orderPartyContext === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'customer_name' => 'Заполните обязательные данные заказчика перед сохранением заявки.',
                ]);
        }

        $customerId = (int) $orderPartyContext['customer_id'];
        $customerCounterpartyId = (int) $orderPartyContext['counterparty_id'];
        $orderExists = DB::table('orders')->where('id', $orderId)->exists();

        if (! $orderExists) {
            return $this->store($request, $geocodeService, $routeDistanceService);
        }

        DB::transaction(function () use ($orderId, $validated, $customerId, $customerCounterpartyId, $geocodeService, $routeDistanceService): void {
            $routeCoordinates = $this->syncOrderStops($orderId, $customerCounterpartyId, $validated, $geocodeService);

            $distanceResult = $this->resolveRouteDistanceResult($validated, $routeCoordinates, $routeDistanceService);
            $now = now();

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'customer_id' => $customerId,
                    'distance_km' => $distanceResult['distance_km'],
                    'manager_id' => Auth::id(),
                    'updated_at' => $now,
                ]);

            $this->syncCarrierAssignment(
                $orderId,
                is_numeric($validated['carrier_counterparty_id'] ?? null) ? (int) $validated['carrier_counterparty_id'] : null,
                $now,
            );
        });

        return redirect()
            ->route('orders.edit', $orderId)
            ->with('status', 'Изменения заявки сохранены. Точки маршрута обновлены в order_stops.');
    }

    public function geocodeRoute(Request $request, GeocodeService $geocodeService, RouteDistanceService $routeDistanceService): JsonResponse
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

        $distanceResult = $routeDistanceService->calculateRouteDistance(
            (float) $fromPoint['lat'],
            (float) $fromPoint['lng'],
            (float) $toPoint['lat'],
            (float) $toPoint['lng'],
        );

        return response()->json([
            'from' => $fromPoint,
            'to' => $toPoint,
            'distance_km' => $distanceResult['distance_km'],
            'distance_source' => $distanceResult['source'],
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

    public function searchParticipantCounterparties(Request $request, FindPartyService $findPartyService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'in:sender,receiver,carrier,customer'],
        ]);

        $query = trim((string) $validated['query']);

        if (mb_strlen($query) < 2) {
            return response()->json([
                'suggestions' => [],
            ]);
        }

        $localSuggestions = $this->searchLocalParticipantCounterparties($query);
        $localInns = collect($localSuggestions)
            ->pluck('inn')
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value) => trim($value))
            ->values()
            ->all();

        $suggestions = collect($localSuggestions)
            ->map(static fn (array $counterparty): array => [
                'source' => 'local',
                'counterparty' => $counterparty,
            ]);
        $dadataError = null;

        $digits = preg_replace('/\D+/', '', $query) ?? '';

        if (mb_strlen($query) >= 3 || in_array(strlen($digits), [10, 12, 13, 15], true)) {
            try {
                $result = $findPartyService->suggestByQuery($query, ['count' => 5]);
                $dadataSuggestions = collect((array) ($result['suggestions'] ?? []))
                    ->map(fn ($suggestion) => $this->mapDaDataCounterpartySuggestion($suggestion))
                    ->filter(static function (?array $suggestion) use ($localInns): bool {
                        if ($suggestion === null) {
                            return false;
                        }

                        $inn = trim((string) ($suggestion['counterparty']['inn'] ?? ''));

                        return $inn === '' || ! in_array($inn, $localInns, true);
                    })
                    ->values();

                $suggestions = $suggestions->concat($dadataSuggestions);
            } catch (Throwable $exception) {
                $dadataError = $exception->getMessage();
            }
        }

        return response()->json([
            'suggestions' => $suggestions->values()->all(),
            'dadata_error' => $dadataError,
        ]);
    }

    public function autofillCustomerByInn(Request $request, FindPartyService $findPartyService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:40'],
        ]);

        $query = preg_replace('/\D+/', '', (string) $validated['query']) ?? '';

        if (! in_array(strlen($query), [10, 12, 13, 15], true)) {
            return response()->json([
                'message' => 'Укажите корректный ИНН или ОГРН (10, 12, 13 или 15 цифр).',
            ], 422);
        }

        try {
            $result = $findPartyService->findByQuery($query, ['count' => 1]);
            $suggestion = data_get($result, 'suggestions.0');

            if (! is_array($suggestion)) {
                return response()->json([
                    'message' => 'По указанному ИНН/ОГРН данные не найдены.',
                ], 404);
            }

            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];
            $shortName = trim((string) (
                data_get($data, 'name.short_with_opf')
                ?? data_get($data, 'name.short')
                ?? ''
            ));
            $fullName = trim((string) (
                data_get($data, 'name.full_with_opf')
                ?? data_get($data, 'name.full')
                ?? ''
            ));
            $name = $shortName !== '' ? $shortName : ($fullName !== '' ? $fullName : trim((string) ($suggestion['value'] ?? '')));
            $contactName = trim((string) (
                data_get($data, 'management.name')
                ?? data_get($data, 'fio.name')
                ?? ''
            ));
            $phone = trim((string) (
                data_get($data, 'phones.0.value')
                ?? data_get($data, 'phones.0.data.value')
                ?? ''
            ));
            $email = trim((string) (
                data_get($data, 'emails.0.value')
                ?? data_get($data, 'emails.0.data.value')
                ?? ''
            ));

            return response()->json([
                'message' => 'Данные заказчика загружены из DaData.',
                'data' => array_merge([
                    'name' => $name,
                    'short_name' => $shortName !== '' ? $shortName : null,
                    'full_name' => $fullName !== '' ? $fullName : null,
                    'contact_name' => $contactName !== '' ? $contactName : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'email' => $email !== '' ? $email : null,
                    'inn' => (string) ($data['inn'] ?? $query),
                    'kpp' => trim((string) ($data['kpp'] ?? '')),
                    'ogrn' => trim((string) (($data['ogrn'] ?? $data['ogrnip'] ?? ''))),
                ], $this->extractLegalAddressAttributesFromDaDataParty($data)),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Не удалось получить данные заказчика из DaData: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function resolveParticipantCounterparty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:local,dadata'],
            'role' => ['nullable', 'in:sender,receiver,carrier,customer'],
            'id' => ['nullable', 'integer'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:12'],
            'kpp' => ['nullable', 'string', 'max:9'],
            'ogrn' => ['nullable', 'string', 'max:15'],
            'phone' => ['nullable', 'string', 'max:20'],
            'legal_address' => ['nullable', 'string', 'max:1000'],
            'actual_address' => ['nullable', 'string', 'max:1000'],
            'legal_postal_code' => ['nullable', 'string', 'max:10'],
            'legal_region' => ['nullable', 'string', 'max:150'],
            'legal_city' => ['nullable', 'string', 'max:150'],
            'legal_settlement' => ['nullable', 'string', 'max:150'],
            'legal_street' => ['nullable', 'string', 'max:150'],
            'legal_house' => ['nullable', 'string', 'max:50'],
            'legal_block' => ['nullable', 'string', 'max:50'],
            'legal_flat' => ['nullable', 'string', 'max:50'],
            'legal_fias_id' => ['nullable', 'string', 'max:50'],
            'legal_kladr_id' => ['nullable', 'string', 'max:20'],
            'legal_geo_lat' => ['nullable', 'numeric'],
            'legal_geo_lon' => ['nullable', 'numeric'],
            'legal_qc' => ['nullable', 'integer', 'min:0', 'max:255'],
            'legal_qc_geo' => ['nullable', 'integer', 'min:0', 'max:255'],
            'legal_address_invalid' => ['nullable', 'boolean'],
            'legal_address_data' => ['nullable', 'string'],
            'type_kind' => ['nullable', 'in:legal,entrepreneur,person,self_employed'],
        ]);

        if ($validated['source'] === 'local') {
            $counterpartyId = is_numeric($validated['id'] ?? null) ? (int) $validated['id'] : null;

            if ($counterpartyId === null || ! DB::table('counterparties')->where('id', $counterpartyId)->exists()) {
                return response()->json([
                    'message' => 'Выбранный контрагент не найден в базе.',
                ], 404);
            }

            return response()->json([
                'counterparty' => $this->counterpartyLookupItemById($counterpartyId),
            ]);
        }

        $counterpartyId = $this->upsertCounterpartyFromParticipantPayload($validated);

        return response()->json([
            'counterparty' => $this->counterpartyLookupItemById($counterpartyId),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadOrdersFromDatabase(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return DB::table('orders')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($orderId) => is_numeric($orderId) ? $this->loadOrderFromDatabase((int) $orderId) : null)
            ->filter(static fn (?array $order): bool => $order !== null)
            ->values()
            ->all();
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'customer_contact_id' => ['nullable', 'integer', 'exists:counterparty_contacts,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_inn' => ['nullable', 'string', 'max:12'],
            'customer_legal_address' => ['nullable', 'string', 'max:1000'],
            'customer_legal_postal_code' => ['nullable', 'string', 'max:10'],
            'customer_legal_region' => ['nullable', 'string', 'max:150'],
            'customer_legal_city' => ['nullable', 'string', 'max:150'],
            'customer_legal_settlement' => ['nullable', 'string', 'max:150'],
            'customer_legal_street' => ['nullable', 'string', 'max:150'],
            'customer_legal_house' => ['nullable', 'string', 'max:50'],
            'customer_legal_block' => ['nullable', 'string', 'max:50'],
            'customer_legal_flat' => ['nullable', 'string', 'max:50'],
            'customer_legal_fias_id' => ['nullable', 'string', 'max:50'],
            'customer_legal_kladr_id' => ['nullable', 'string', 'max:20'],
            'customer_legal_geo_lat' => ['nullable', 'numeric'],
            'customer_legal_geo_lon' => ['nullable', 'numeric'],
            'customer_legal_qc' => ['nullable', 'integer', 'min:0', 'max:255'],
            'customer_legal_qc_geo' => ['nullable', 'integer', 'min:0', 'max:255'],
            'customer_legal_address_invalid' => ['nullable', 'boolean'],
            'customer_legal_address_data' => ['nullable', 'string'],
            'from_city' => ['required', 'string', 'max:120'],
            'from_address' => ['required', 'string', 'max:255'],
            'from_counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'carrier_counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'to_city' => ['required', 'string', 'max:120'],
            'to_address' => ['required', 'string', 'max:255'],
            'to_counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
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
    private function resolveOrderPartyContext(array $validated): ?array
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('counterparties')) {
            return null;
        }

        $customerName = trim((string) ($validated['customer_name'] ?? ''));
        $customerContactName = trim((string) ($validated['customer_contact_name'] ?? ''));
        $customerPhone = trim((string) ($validated['customer_phone'] ?? ''));
        $customerEmail = trim((string) ($validated['customer_email'] ?? ''));
        $customerInn = trim((string) ($validated['customer_inn'] ?? ''));
        $customerId = is_numeric($validated['customer_id'] ?? null) ? (int) $validated['customer_id'] : null;
        $counterpartyId = is_numeric($validated['customer_counterparty_id'] ?? null) ? (int) $validated['customer_counterparty_id'] : null;
        $contactId = is_numeric($validated['customer_contact_id'] ?? null) ? (int) $validated['customer_contact_id'] : null;

        if ($customerName === '' || $customerPhone === '') {
            return null;
        }

        $existingCounterparty = null;

        if ($counterpartyId !== null) {
            $existingCounterparty = DB::table('counterparties')
                ->select('id', 'type', 'ceo')
                ->where('id', $counterpartyId)
                ->first();
        }

        if ($existingCounterparty === null && $customerId !== null) {
            $existingCounterparty = DB::table('customers as c')
                ->join('counterparties as cp', 'cp.id', '=', 'c.counterparty_id')
                ->select('cp.id', 'cp.type', 'cp.ceo')
                ->where('c.id', $customerId)
                ->first();
        }

        if ($existingCounterparty === null && $customerInn !== '') {
            $existingCounterparty = DB::table('counterparties')
                ->select('id', 'type', 'ceo')
                ->where('inn', $customerInn)
                ->orderBy('id')
                ->first();
        }

        if ($existingCounterparty === null) {
            $existingCounterparty = DB::table('counterparties')
                ->select('id', 'type', 'ceo')
                ->where(function ($builder) use ($customerName): void {
                    $builder->where('short_name', $customerName)
                        ->orWhere('full_name', $customerName);
                })
                ->orderBy('id')
                ->first();
        }

        $now = now();
        $resolvedCounterpartyId = is_numeric($existingCounterparty->id ?? null) ? (int) $existingCounterparty->id : null;
        $counterpartyPayload = [
            'type' => is_numeric($existingCounterparty->type ?? null)
                ? (int) $existingCounterparty->type
                : $this->resolveCounterpartyTypeIdByKind('legal'),
            'short_name' => $customerName,
            'full_name' => $customerName,
            'inn' => $customerInn !== '' ? $customerInn : null,
            'ceo' => $customerContactName !== ''
                ? $customerContactName
                : (trim((string) ($existingCounterparty->ceo ?? '')) !== '' ? trim((string) $existingCounterparty->ceo) : $customerName),
            'phone' => $customerPhone,
            'email' => $customerEmail !== '' ? $customerEmail : null,
            'updated_at' => $now,
        ];

        $counterpartyPayload = array_merge(
            $counterpartyPayload,
            $this->extractPrefixedLegalAddressAttributes($validated, 'customer_', $resolvedCounterpartyId === null)
        );

        if ($resolvedCounterpartyId !== null) {
            DB::table('counterparties')
                ->where('id', $resolvedCounterpartyId)
                ->update($counterpartyPayload);
        } else {
            $resolvedCounterpartyId = (int) DB::table('counterparties')->insertGetId($counterpartyPayload + [
                'created_at' => $now,
            ]);
        }

        $resolvedCustomerId = null;

        if ($customerId !== null && DB::table('customers')->where('id', $customerId)->exists()) {
            $resolvedCustomerId = $customerId;
        } else {
            $existingCustomerId = DB::table('customers')
                ->where('counterparty_id', $resolvedCounterpartyId)
                ->value('id');

            if (is_numeric($existingCustomerId)) {
                $resolvedCustomerId = (int) $existingCustomerId;
            }
        }

        if ($resolvedCustomerId !== null) {
            DB::table('customers')
                ->where('id', $resolvedCustomerId)
                ->update([
                    'counterparty_id' => $resolvedCounterpartyId,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        } else {
            $resolvedCustomerId = (int) DB::table('customers')->insertGetId([
                'counterparty_id' => $resolvedCounterpartyId,
                'is_active' => true,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('counterparty_contacts')) {
            DB::table('counterparty_contacts')
                ->where('counterparty_id', $resolvedCounterpartyId)
                ->update([
                    'is_primary' => false,
                    'updated_at' => $now,
                ]);

            $existingContact = null;

            if ($contactId !== null) {
                $existingContact = DB::table('counterparty_contacts')
                    ->select('id')
                    ->where('id', $contactId)
                    ->where('counterparty_id', $resolvedCounterpartyId)
                    ->first();
            }

            if ($existingContact === null) {
                $existingContact = DB::table('counterparty_contacts')
                    ->select('id')
                    ->where('counterparty_id', $resolvedCounterpartyId)
                    ->orderByDesc('is_primary')
                    ->orderBy('id')
                    ->first();
            }

            $contactPayload = [
                'full_name' => $customerContactName !== '' ? $customerContactName : 'Контакт не указан',
                'email' => $customerEmail !== '' ? $customerEmail : null,
                'phone_mobile' => $customerPhone,
                'phone_city' => null,
                'phone_extension' => null,
                'is_primary' => true,
                'is_active' => true,
                'notes' => null,
                'updated_at' => $now,
            ];

            if (is_numeric($existingContact->id ?? null)) {
                DB::table('counterparty_contacts')
                    ->where('id', (int) $existingContact->id)
                    ->update($contactPayload);
            } else {
                DB::table('counterparty_contacts')->insert($contactPayload + [
                    'counterparty_id' => $resolvedCounterpartyId,
                    'created_at' => $now,
                ]);
            }
        }

        return [
            'customer_id' => $resolvedCustomerId,
            'counterparty_id' => $resolvedCounterpartyId,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function syncOrderStops(int $orderId, int $defaultCounterpartyId, array $validated, GeocodeService $geocodeService): array
    {
        DB::table('order_stops')->where('order_id', $orderId)->delete();

        $fromCounterpartyId = $this->resolveStopCounterpartyId(
            $validated['from_counterparty_id'] ?? null,
            $defaultCounterpartyId,
        );

        $toCounterpartyId = $this->resolveStopCounterpartyId(
            $validated['to_counterparty_id'] ?? null,
            $defaultCounterpartyId,
        );

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
            counterpartyId: $fromCounterpartyId,
            type: 'loading',
            sequence: $sequence++,
            city: (string) ($validated['from_city'] ?? ''),
            address: (string) ($validated['from_address'] ?? ''),
            lat: $fromCoordinates['lat'],
            lng: $fromCoordinates['lng'],
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
                now: $now,
            );
        }

        $rows[] = $this->makeStopRow(
            orderId: $orderId,
            counterpartyId: $toCounterpartyId,
            type: 'unloading',
            sequence: $sequence,
            city: (string) ($validated['to_city'] ?? ''),
            address: (string) ($validated['to_address'] ?? ''),
            lat: $toCoordinates['lat'],
            lng: $toCoordinates['lng'],
            now: $now,
        );

        DB::table('order_stops')->insert($rows);

        return [
            'from' => $fromCoordinates,
            'to' => $toCoordinates,
        ];
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

    /**
     * @param array<string, mixed> $validated
     * @param array{from: array{lat: float|null, lng: float|null}, to: array{lat: float|null, lng: float|null}} $routeCoordinates
     *
     * @return array{distance_km: int|null, source: string}
     */
    private function resolveRouteDistanceResult(array $validated, array $routeCoordinates, RouteDistanceService $routeDistanceService): array
    {
        $fromLat = $routeCoordinates['from']['lat'] ?? null;
        $fromLng = $routeCoordinates['from']['lng'] ?? null;
        $toLat = $routeCoordinates['to']['lat'] ?? null;
        $toLng = $routeCoordinates['to']['lng'] ?? null;

        if (is_numeric($fromLat) && is_numeric($fromLng) && is_numeric($toLat) && is_numeric($toLng)) {
            return $routeDistanceService->calculateRouteDistance(
                (float) $fromLat,
                (float) $fromLng,
                (float) $toLat,
                (float) $toLng,
            );
        }

        return [
            'distance_km' => $this->parseDistanceKm($validated['distance'] ?? null),
            'source' => 'manual-fallback',
        ];
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

        $intermediateStops = $intermediateStopsCollection
            ->map(function ($stop): array {
                return [
                    'type' => in_array((string) ($stop->type ?? 'unloading'), ['loading', 'unloading'], true) ? (string) $stop->type : 'unloading',
                    'counterparty_id' => is_numeric($stop->counterparty_id ?? null) ? (int) $stop->counterparty_id : null,
                    'city' => $this->resolveStopCity($stop),
                    'address' => $this->resolveStopAddress($stop),
                    'lat' => $this->normalizeCoordinate($stop->lat ?? null),
                    'lng' => $this->normalizeCoordinate($stop->lng ?? null),
                ];
            })
            ->values()
            ->all();

        $createdAt = $order->created_at ? date('d.m.Y', strtotime((string) $order->created_at)) : now()->format('d.m.Y');

        $statusCode = (string) ($order->status ?? 'new');
        $fromCounterpartyDetails = $this->counterpartyDetailsById(is_numeric($fromStop->counterparty_id ?? null) ? (int) $fromStop->counterparty_id : null);
        $toCounterpartyDetails = $this->counterpartyDetailsById(is_numeric($toStop->counterparty_id ?? null) ? (int) $toStop->counterparty_id : null);
        $driverData = $this->resolveOrderDriverData((int) $order->id);
        $carrierData = $this->resolveOrderCarrierData((int) $order->id);
        $customerData = $this->resolveOrderCustomerData(is_numeric($order->customer_id ?? null) ? (int) $order->customer_id : null);

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
                    'city' => $fromStop !== null ? $this->resolveStopCity($fromStop) : '',
                    'address' => $fromStop !== null ? $this->resolveStopAddress($fromStop) : '',
                    'lat' => $fromStop !== null ? $this->normalizeCoordinate($fromStop->lat ?? null) : null,
                    'lng' => $fromStop !== null ? $this->normalizeCoordinate($fromStop->lng ?? null) : null,
                ],
                'to' => [
                    'counterparty_id' => is_numeric($toStop->counterparty_id ?? null) ? (int) $toStop->counterparty_id : null,
                    'city' => $toStop !== null ? $this->resolveStopCity($toStop) : '',
                    'address' => $toStop !== null ? $this->resolveStopAddress($toStop) : '',
                    'lat' => $toStop !== null ? $this->normalizeCoordinate($toStop->lat ?? null) : null,
                    'lng' => $toStop !== null ? $this->normalizeCoordinate($toStop->lng ?? null) : null,
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
            'driver' => $driverData,
            'customer' => $customerData,
            'sender' => $fromCounterpartyDetails,
            'receiver' => $toCounterpartyDetails,
            'carrier' => $carrierData,
        ];
    }

    /**
     * @return array{id: int|null, counterparty_id: int|null, contact_id: int|null, name: string, contact_name: string, phone: string, email: string, inn: string}
     */
    private function resolveOrderCustomerData(?int $customerId): array
    {
        $fallback = [
            'id' => null,
            'counterparty_id' => null,
            'contact_id' => null,
            'name' => '',
            'contact_name' => '',
            'phone' => '',
            'email' => '',
            'inn' => '',
        ];

        if ($customerId === null || ! Schema::hasTable('customers') || ! Schema::hasTable('counterparties')) {
            return $fallback;
        }

        $row = DB::table('customers as c')
            ->join('counterparties as cp', 'cp.id', '=', 'c.counterparty_id')
            ->select(
                'c.id as customer_id',
                'cp.id as counterparty_id',
                'cp.short_name',
                'cp.full_name',
                'cp.inn',
                'cp.phone',
                'cp.email',
                'cp.ceo',
                'cp.legal_address',
                'cp.legal_postal_code',
                'cp.legal_region',
                'cp.legal_city',
                'cp.legal_settlement',
                'cp.legal_street',
                'cp.legal_house',
                'cp.legal_block',
                'cp.legal_flat',
                'cp.legal_fias_id',
                'cp.legal_kladr_id',
                'cp.legal_geo_lat',
                'cp.legal_geo_lon',
                'cp.legal_qc',
                'cp.legal_qc_geo',
                'cp.legal_address_invalid',
                'cp.legal_address_data'
            )
            ->where('c.id', $customerId)
            ->first();

        if ($row === null) {
            return $fallback;
        }

        $contact = null;

        if (Schema::hasTable('counterparty_contacts')) {
            $contact = DB::table('counterparty_contacts')
                ->select('id', 'full_name', 'email', 'phone_mobile')
                ->where('counterparty_id', (int) $row->counterparty_id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();
        }

        $contactName = trim((string) ($contact->full_name ?? ''));
        if ($contactName === 'Контакт не указан') {
            $contactName = '';
        }

        $contactPhone = trim((string) ($contact->phone_mobile ?? ''));
        $contactEmail = trim((string) ($contact->email ?? ''));

        return [
            'id' => (int) $row->customer_id,
            'counterparty_id' => (int) $row->counterparty_id,
            'contact_id' => is_numeric($contact->id ?? null) ? (int) $contact->id : null,
            'name' => $this->resolveCounterpartyDisplayName($row),
            'contact_name' => $contactName !== '' ? $contactName : trim((string) ($row->ceo ?? '')),
            'phone' => $contactPhone !== '' ? $contactPhone : trim((string) ($row->phone ?? '')),
            'email' => $contactEmail !== '' ? $contactEmail : trim((string) ($row->email ?? '')),
            'inn' => trim((string) ($row->inn ?? '')),
            'legal_address' => trim((string) ($row->legal_address ?? '')),
            'legal_postal_code' => trim((string) ($row->legal_postal_code ?? '')),
            'legal_region' => trim((string) ($row->legal_region ?? '')),
            'legal_city' => trim((string) ($row->legal_city ?? '')),
            'legal_settlement' => trim((string) ($row->legal_settlement ?? '')),
            'legal_street' => trim((string) ($row->legal_street ?? '')),
            'legal_house' => trim((string) ($row->legal_house ?? '')),
            'legal_block' => trim((string) ($row->legal_block ?? '')),
            'legal_flat' => trim((string) ($row->legal_flat ?? '')),
            'legal_fias_id' => trim((string) ($row->legal_fias_id ?? '')),
            'legal_kladr_id' => trim((string) ($row->legal_kladr_id ?? '')),
            'legal_geo_lat' => $this->normalizeCoordinate($row->legal_geo_lat ?? null),
            'legal_geo_lon' => $this->normalizeCoordinate($row->legal_geo_lon ?? null),
            'legal_qc' => is_numeric($row->legal_qc ?? null) ? (int) $row->legal_qc : null,
            'legal_qc_geo' => is_numeric($row->legal_qc_geo ?? null) ? (int) $row->legal_qc_geo : null,
            'legal_address_invalid' => $row->legal_address_invalid === null ? null : (bool) $row->legal_address_invalid,
            'legal_address_data' => $this->decodeJsonArray($row->legal_address_data ?? null),
        ];
    }

    private function resolveStopCity(object $stop): string
    {
        $cityFromColumn = trim((string) ($stop->city ?? ''));

        if ($cityFromColumn !== '') {
            return $cityFromColumn;
        }

        $addressString = trim((string) ($stop->address ?? ''));

        if ($addressString === '') {
            return '';
        }

        return trim((string) explode(',', $addressString)[0]);
    }

    private function resolveStopAddress(object $stop): string
    {
        $addressFromColumn = trim((string) ($stop->address ?? ''));

        if ($addressFromColumn !== '') {
            return $addressFromColumn;
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
        mixed $now,
    ): array {
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
     * @return array{name: string, phone: string}
     */
    private function counterpartyDetailsById(?int $counterpartyId): array
    {
        if ($counterpartyId === null) {
            return [
                'name' => '—',
                'phone' => '—',
            ];
        }

        $row = DB::table('counterparties')
            ->select('short_name', 'full_name', 'phone')
            ->where('id', $counterpartyId)
            ->first();

        if ($row === null) {
            return [
                'name' => '—',
                'phone' => '—',
            ];
        }

        $phone = trim((string) ($row->phone ?? ''));

        return [
            'name' => $this->resolveCounterpartyDisplayName($row),
            'phone' => $phone !== '' ? $phone : '—',
        ];
    }

    /**
     * @return array{name: string, phone: string, car: string, plate: string}
     */
    private function resolveOrderDriverData(int $orderId): array
    {
        $fallback = [
            'name' => null,
            'car' => '—',
            'plate' => '—',
            'phone' => '—',
        ];

        if (! Schema::hasTable('trips') || ! Schema::hasTable('drivers') || ! Schema::hasTable('vehicles')) {
            return $fallback;
        }

        $row = DB::table('trips as t')
            ->leftJoin('drivers as d', 'd.id', '=', 't.driver_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 't.vehicle_id')
            ->select(
                'd.last_name',
                'd.first_name',
                'd.middle_name',
                'd.phone',
                'v.brand',
                'v.model',
                'v.reg_number'
            )
            ->where('t.order_id', $orderId)
            ->orderByDesc('t.id')
            ->first();

        if ($row === null) {
            return $fallback;
        }

        $driverName = trim(implode(' ', array_filter([
            trim((string) ($row->last_name ?? '')),
            trim((string) ($row->first_name ?? '')),
            trim((string) ($row->middle_name ?? '')),
        ])));

        $car = trim(implode(' ', array_filter([
            trim((string) ($row->brand ?? '')),
            trim((string) ($row->model ?? '')),
        ])));

        $phone = trim((string) ($row->phone ?? ''));
        $plate = trim((string) ($row->reg_number ?? ''));

        return [
            'name' => $driverName !== '' ? $driverName : null,
            'car' => $car !== '' ? $car : '—',
            'plate' => $plate !== '' ? $plate : '—',
            'phone' => $phone !== '' ? $phone : '—',
        ];
    }

    /**
     * @return array{name: string, phone: string}
     */
    private function resolveOrderCarrierData(int $orderId): array
    {
        $fallback = [
            'counterparty_id' => null,
            'name' => '—',
            'phone' => '—',
        ];

        if (! Schema::hasTable('carrier_orders') || ! Schema::hasTable('carriers')) {
            return $fallback;
        }

        $row = DB::table('carrier_orders as co')
            ->join('carriers as c', 'c.id', '=', 'co.carrier_id')
            ->join('counterparties as cp', 'cp.id', '=', 'c.counterparty_id')
            ->select('cp.id as counterparty_id', 'cp.short_name', 'cp.full_name', 'cp.phone')
            ->where('co.order_id', $orderId)
            ->orderByDesc('co.id')
            ->first();

        if ($row === null) {
            return $fallback;
        }

        $phone = trim((string) ($row->phone ?? ''));

        return [
            'counterparty_id' => is_numeric($row->counterparty_id ?? null) ? (int) $row->counterparty_id : null,
            'name' => $this->resolveCounterpartyDisplayName($row),
            'phone' => $phone !== '' ? $phone : '—',
        ];
    }

    private function resolveCounterpartyDisplayName(object $row): string
    {
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
     * @return list<array{id: int, name: string, label: string, phone: string, inn: string, short_name: string, full_name: string, legal_address: string, actual_address: string, type_kind: string, source: string}>
     */
    private function searchLocalParticipantCounterparties(string $query): array
    {
        $rows = DB::table('counterparties as cp')
            ->leftJoin('customers as c', 'c.counterparty_id', '=', 'cp.id')
            ->leftJoin('counterparties_type as cpt', 'cpt.id', '=', 'cp.type')
            ->select('cp.id', 'c.id as customer_id', 'cp.short_name', 'cp.full_name', 'cp.inn', 'cp.kpp', 'cp.ogrn', 'cp.phone', 'cp.email', 'cp.ceo', 'cp.legal_address', 'cp.actual_address', 'cp.legal_postal_code', 'cp.legal_region', 'cp.legal_city', 'cp.legal_settlement', 'cp.legal_street', 'cp.legal_house', 'cp.legal_block', 'cp.legal_flat', 'cp.legal_fias_id', 'cp.legal_kladr_id', 'cp.legal_geo_lat', 'cp.legal_geo_lon', 'cp.legal_qc', 'cp.legal_qc_geo', 'cp.legal_address_invalid', 'cp.legal_address_data', 'cpt.name as type_name')
            ->where(function ($builder) use ($query): void {
                $builder->where('cp.short_name', 'like', "%{$query}%")
                    ->orWhere('cp.full_name', 'like', "%{$query}%")
                    ->orWhere('cp.inn', 'like', "%{$query}%")
                    ->orWhere('cp.ogrn', 'like', "%{$query}%")
                    ->orWhere('cp.phone', 'like', "%{$query}%");
            })
            ->orderBy('cp.short_name')
            ->orderBy('cp.full_name')
            ->limit(8)
            ->get();

        return $rows->map(function ($row): array {
            $name = $this->resolveCounterpartyDisplayName($row);
            $inn = trim((string) ($row->inn ?? ''));
            $phone = trim((string) ($row->phone ?? ''));

            return [
                'id' => (int) $row->id,
                'customer_id' => is_numeric($row->customer_id ?? null) ? (int) $row->customer_id : null,
                'name' => $name,
                'label' => $inn !== '' ? $name.' (ИНН '.$inn.')' : $name,
                'phone' => $phone !== '' ? $phone : '—',
                'inn' => $inn,
                'kpp' => trim((string) ($row->kpp ?? '')),
                'ogrn' => trim((string) ($row->ogrn ?? '')),
                'email' => trim((string) ($row->email ?? '')),
                'contact_name' => trim((string) ($row->ceo ?? '')),
                'short_name' => trim((string) ($row->short_name ?? '')),
                'full_name' => trim((string) ($row->full_name ?? '')),
                'legal_address' => trim((string) ($row->legal_address ?? '')),
                'actual_address' => trim((string) ($row->actual_address ?? '')),
                'legal_postal_code' => trim((string) ($row->legal_postal_code ?? '')),
                'legal_region' => trim((string) ($row->legal_region ?? '')),
                'legal_city' => trim((string) ($row->legal_city ?? '')),
                'legal_settlement' => trim((string) ($row->legal_settlement ?? '')),
                'legal_street' => trim((string) ($row->legal_street ?? '')),
                'legal_house' => trim((string) ($row->legal_house ?? '')),
                'legal_block' => trim((string) ($row->legal_block ?? '')),
                'legal_flat' => trim((string) ($row->legal_flat ?? '')),
                'legal_fias_id' => trim((string) ($row->legal_fias_id ?? '')),
                'legal_kladr_id' => trim((string) ($row->legal_kladr_id ?? '')),
                'legal_geo_lat' => $this->normalizeCoordinate($row->legal_geo_lat ?? null),
                'legal_geo_lon' => $this->normalizeCoordinate($row->legal_geo_lon ?? null),
                'legal_qc' => is_numeric($row->legal_qc ?? null) ? (int) $row->legal_qc : null,
                'legal_qc_geo' => is_numeric($row->legal_qc_geo ?? null) ? (int) $row->legal_qc_geo : null,
                'legal_address_invalid' => $row->legal_address_invalid === null ? null : (bool) $row->legal_address_invalid,
                'legal_address_data' => $this->decodeJsonArray($row->legal_address_data ?? null),
                'type_kind' => $this->resolveCounterpartyTypeKindByName((string) ($row->type_name ?? '')),
                'source' => 'local',
            ];
        })->values()->all();
    }

    private function mapDaDataCounterpartySuggestion(mixed $suggestion): ?array
    {
        if (! is_array($suggestion)) {
            return null;
        }

        $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];
        $partyType = mb_strtoupper((string) ($data['type'] ?? 'LEGAL'));
        $typeKind = $partyType === 'INDIVIDUAL' ? 'entrepreneur' : 'legal';
        $shortName = trim((string) (data_get($data, 'name.short_with_opf') ?? data_get($data, 'name.short') ?? ''));
        $fullName = trim((string) (data_get($data, 'name.full_with_opf') ?? data_get($data, 'name.full') ?? ''));
        $name = $shortName !== '' ? $shortName : ($fullName !== '' ? $fullName : trim((string) ($suggestion['value'] ?? 'Контрагент без названия')));
        $phone = trim((string) (data_get($data, 'phones.0.value') ?? data_get($data, 'phones.0.data.value') ?? ''));
        $inn = trim((string) ($data['inn'] ?? ''));
        $addressAttributes = $this->extractLegalAddressAttributesFromDaDataParty($data);

        return [
            'source' => 'dadata',
            'counterparty' => array_merge([
                'id' => null,
                'customer_id' => null,
                'name' => $name,
                'label' => $inn !== '' ? $name.' (DaData, ИНН '.$inn.')' : $name.' (DaData)',
                'phone' => $phone !== '' ? $phone : '—',
                'inn' => $inn,
                'short_name' => $shortName,
                'full_name' => $fullName,
                'kpp' => trim((string) ($data['kpp'] ?? '')),
                'ogrn' => trim((string) (($data['ogrn'] ?? $data['ogrnip'] ?? ''))),
                'email' => '',
                'contact_name' => '',
                'actual_address' => '',
                'type_kind' => $typeKind,
                'source' => 'dadata',
            ], $addressAttributes),
        ];
    }

    private function counterpartyLookupItemById(int $counterpartyId): array
    {
        $row = DB::table('counterparties as cp')
            ->leftJoin('customers as c', 'c.counterparty_id', '=', 'cp.id')
            ->leftJoin('counterparties_type as cpt', 'cpt.id', '=', 'cp.type')
            ->select('cp.id', 'c.id as customer_id', 'cp.short_name', 'cp.full_name', 'cp.inn', 'cp.kpp', 'cp.ogrn', 'cp.phone', 'cp.email', 'cp.ceo', 'cp.legal_address', 'cp.actual_address', 'cp.legal_postal_code', 'cp.legal_region', 'cp.legal_city', 'cp.legal_settlement', 'cp.legal_street', 'cp.legal_house', 'cp.legal_block', 'cp.legal_flat', 'cp.legal_fias_id', 'cp.legal_kladr_id', 'cp.legal_geo_lat', 'cp.legal_geo_lon', 'cp.legal_qc', 'cp.legal_qc_geo', 'cp.legal_address_invalid', 'cp.legal_address_data', 'cpt.name as type_name')
            ->where('cp.id', $counterpartyId)
            ->first();

        if ($row === null) {
            return [
                'id' => $counterpartyId,
                'customer_id' => null,
                'name' => '—',
                'label' => '—',
                'phone' => '—',
                'inn' => '',
                'kpp' => '',
                'ogrn' => '',
                'email' => '',
                'contact_name' => '',
                'short_name' => '',
                'full_name' => '',
                'legal_address' => '',
                'actual_address' => '',
                'type_kind' => 'legal',
                'source' => 'local',
            ];
        }

        $name = $this->resolveCounterpartyDisplayName($row);
        $inn = trim((string) ($row->inn ?? ''));
        $phone = trim((string) ($row->phone ?? ''));

        return [
            'id' => (int) $row->id,
            'customer_id' => is_numeric($row->customer_id ?? null) ? (int) $row->customer_id : null,
            'name' => $name,
            'label' => $inn !== '' ? $name.' (ИНН '.$inn.')' : $name,
            'phone' => $phone !== '' ? $phone : '—',
            'inn' => $inn,
            'kpp' => trim((string) ($row->kpp ?? '')),
            'ogrn' => trim((string) ($row->ogrn ?? '')),
            'email' => trim((string) ($row->email ?? '')),
            'contact_name' => trim((string) ($row->ceo ?? '')),
            'short_name' => trim((string) ($row->short_name ?? '')),
            'full_name' => trim((string) ($row->full_name ?? '')),
            'legal_address' => trim((string) ($row->legal_address ?? '')),
            'actual_address' => trim((string) ($row->actual_address ?? '')),
            'legal_postal_code' => trim((string) ($row->legal_postal_code ?? '')),
            'legal_region' => trim((string) ($row->legal_region ?? '')),
            'legal_city' => trim((string) ($row->legal_city ?? '')),
            'legal_settlement' => trim((string) ($row->legal_settlement ?? '')),
            'legal_street' => trim((string) ($row->legal_street ?? '')),
            'legal_house' => trim((string) ($row->legal_house ?? '')),
            'legal_block' => trim((string) ($row->legal_block ?? '')),
            'legal_flat' => trim((string) ($row->legal_flat ?? '')),
            'legal_fias_id' => trim((string) ($row->legal_fias_id ?? '')),
            'legal_kladr_id' => trim((string) ($row->legal_kladr_id ?? '')),
            'legal_geo_lat' => $this->normalizeCoordinate($row->legal_geo_lat ?? null),
            'legal_geo_lon' => $this->normalizeCoordinate($row->legal_geo_lon ?? null),
            'legal_qc' => is_numeric($row->legal_qc ?? null) ? (int) $row->legal_qc : null,
            'legal_qc_geo' => is_numeric($row->legal_qc_geo ?? null) ? (int) $row->legal_qc_geo : null,
            'legal_address_invalid' => $row->legal_address_invalid === null ? null : (bool) $row->legal_address_invalid,
            'legal_address_data' => $this->decodeJsonArray($row->legal_address_data ?? null),
            'type_kind' => $this->resolveCounterpartyTypeKindByName((string) ($row->type_name ?? '')),
            'source' => 'local',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertCounterpartyFromParticipantPayload(array $payload): int
    {
        $inn = trim((string) ($payload['inn'] ?? ''));
        $kpp = trim((string) ($payload['kpp'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $shortName = trim((string) ($payload['short_name'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $legalAddress = trim((string) ($payload['legal_address'] ?? ''));
        $actualAddress = trim((string) ($payload['actual_address'] ?? ''));
        $ogrn = trim((string) ($payload['ogrn'] ?? ''));
        $typeId = $this->resolveCounterpartyTypeIdByKind((string) ($payload['type_kind'] ?? 'legal'));

        $existingQuery = DB::table('counterparties');

        if ($inn !== '') {
            $existingQuery->where('inn', $inn);

            if ($kpp !== '') {
                $existingQuery->orderByRaw('CASE WHEN kpp = ? THEN 0 ELSE 1 END', [$kpp]);
            }
        } elseif ($shortName !== '' || $fullName !== '') {
            $existingQuery->where(function ($builder) use ($shortName, $fullName): void {
                if ($shortName !== '') {
                    $builder->orWhere('short_name', $shortName);
                }

                if ($fullName !== '') {
                    $builder->orWhere('full_name', $fullName);
                }
            });

            if ($phone !== '') {
                $existingQuery->orderByRaw('CASE WHEN phone = ? THEN 0 ELSE 1 END', [$phone]);
            }
        }

        $existing = $existingQuery->first();
        $now = now();
        $fallbackName = $shortName !== '' ? $shortName : ($fullName !== '' ? $fullName : 'Контрагент из DaData');

        $data = [
            'type' => $typeId,
            'short_name' => $shortName !== '' ? $shortName : null,
            'full_name' => $fullName !== '' ? $fullName : $fallbackName,
            'inn' => $inn !== '' ? $inn : null,
            'kpp' => $kpp !== '' ? $kpp : null,
            'ogrn' => $ogrn !== '' ? $ogrn : null,
            'legal_address' => $legalAddress !== '' ? $legalAddress : null,
            'actual_address' => $actualAddress !== '' ? $actualAddress : null,
            'ceo' => $fullName !== '' ? $fullName : ($shortName !== '' ? $shortName : $fallbackName),
            'phone' => $phone !== '' ? $phone : '—',
            'email' => null,
            'notes' => null,
            'updated_at' => $now,
        ];

        $data = array_merge($data, $this->extractPrefixedLegalAddressAttributes($payload, '', $existing === null));

        if ($existing !== null && is_numeric($existing->id ?? null)) {
            DB::table('counterparties')
                ->where('id', (int) $existing->id)
                ->update($data);

            return (int) $existing->id;
        }

        return (int) DB::table('counterparties')->insertGetId($data + [
            'created_at' => $now,
        ]);
    }

    private function resolveCounterpartyTypeIdByKind(string $kind): int
    {
        $typeName = match ($kind) {
            'entrepreneur' => 'ИП',
            'person' => 'Физ. лицов',
            'self_employed' => 'Самозанятый',
            default => 'ООО',
        };

        $typeId = DB::table('counterparties_type')->where('name', $typeName)->value('id');

        if (! is_numeric($typeId)) {
            $fallbackId = DB::table('counterparties_type')->orderBy('id')->value('id');

            if (is_numeric($fallbackId)) {
                return (int) $fallbackId;
            }

            throw new RuntimeException('В системе не настроены типы контрагентов.');
        }

        return (int) $typeId;
    }

    private function resolveCounterpartyTypeKindByName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));

        return match (true) {
            str_contains($normalized, 'самозан') => 'self_employed',
            str_contains($normalized, 'физ') => 'person',
            str_contains($normalized, 'ип'), str_contains($normalized, 'предприним') => 'entrepreneur',
            default => 'legal',
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function extractLegalAddressAttributesFromDaDataParty(array $data): array
    {
        $addressData = is_array(data_get($data, 'address.data')) ? data_get($data, 'address.data') : [];
        $fullAddress = trim((string) (data_get($data, 'address.unrestricted_value') ?? data_get($data, 'address.value') ?? ''));
        $addressInvalidity = data_get($data, 'address.invalidity');
        $hasAddress = $fullAddress !== '' || $addressData !== [];

        return [
            'legal_address' => $fullAddress !== '' ? $fullAddress : null,
            'legal_postal_code' => $this->nullableString(data_get($addressData, 'postal_code')),
            'legal_region' => $this->nullableString(data_get($addressData, 'region_with_type')) ?? $this->nullableString(data_get($addressData, 'region')),
            'legal_city' => $this->nullableString(data_get($addressData, 'city_with_type')) ?? $this->nullableString(data_get($addressData, 'city')),
            'legal_settlement' => $this->nullableString(data_get($addressData, 'settlement_with_type')) ?? $this->nullableString(data_get($addressData, 'settlement')),
            'legal_street' => $this->nullableString(data_get($addressData, 'street_with_type')) ?? $this->nullableString(data_get($addressData, 'street')),
            'legal_house' => $this->nullableString(data_get($addressData, 'house')),
            'legal_block' => $this->nullableString(data_get($addressData, 'block')),
            'legal_flat' => $this->nullableString(data_get($addressData, 'flat')),
            'legal_fias_id' => $this->nullableString(data_get($addressData, 'fias_id')),
            'legal_kladr_id' => $this->nullableString(data_get($addressData, 'kladr_id')),
            'legal_geo_lat' => $this->nullableFloat(data_get($addressData, 'geo_lat')),
            'legal_geo_lon' => $this->nullableFloat(data_get($addressData, 'geo_lon')),
            'legal_qc' => $this->nullableInt(data_get($addressData, 'qc')),
            'legal_qc_geo' => $this->nullableInt(data_get($addressData, 'qc_geo')),
            'legal_address_invalid' => $hasAddress ? $addressInvalidity !== null : null,
            'legal_address_data' => $addressData !== [] ? $addressData : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractPrefixedLegalAddressAttributes(array $payload, string $prefix = '', bool $includeNulls = false): array
    {
        $attributes = [
            'legal_address' => $this->nullableString($payload[$prefix.'legal_address'] ?? null),
            'legal_postal_code' => $this->nullableString($payload[$prefix.'legal_postal_code'] ?? null),
            'legal_region' => $this->nullableString($payload[$prefix.'legal_region'] ?? null),
            'legal_city' => $this->nullableString($payload[$prefix.'legal_city'] ?? null),
            'legal_settlement' => $this->nullableString($payload[$prefix.'legal_settlement'] ?? null),
            'legal_street' => $this->nullableString($payload[$prefix.'legal_street'] ?? null),
            'legal_house' => $this->nullableString($payload[$prefix.'legal_house'] ?? null),
            'legal_block' => $this->nullableString($payload[$prefix.'legal_block'] ?? null),
            'legal_flat' => $this->nullableString($payload[$prefix.'legal_flat'] ?? null),
            'legal_fias_id' => $this->nullableString($payload[$prefix.'legal_fias_id'] ?? null),
            'legal_kladr_id' => $this->nullableString($payload[$prefix.'legal_kladr_id'] ?? null),
            'legal_geo_lat' => $this->nullableFloat($payload[$prefix.'legal_geo_lat'] ?? null),
            'legal_geo_lon' => $this->nullableFloat($payload[$prefix.'legal_geo_lon'] ?? null),
            'legal_qc' => $this->nullableInt($payload[$prefix.'legal_qc'] ?? null),
            'legal_qc_geo' => $this->nullableInt($payload[$prefix.'legal_qc_geo'] ?? null),
            'legal_address_invalid' => $this->nullableBoolean($payload[$prefix.'legal_address_invalid'] ?? null),
            'legal_address_data' => $this->decodeJsonArray($payload[$prefix.'legal_address_data'] ?? null),
        ];

        if ($includeNulls) {
            return $attributes;
        }

        return array_filter($attributes, static fn (mixed $value): bool => $value !== null);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function syncCarrierAssignment(int $orderId, ?int $counterpartyId, mixed $now): void
    {
        if (! Schema::hasTable('carriers') || ! Schema::hasTable('carrier_orders')) {
            return;
        }

        if ($counterpartyId === null) {
            DB::table('carrier_orders')->where('order_id', $orderId)->delete();

            return;
        }

        $carrierId = DB::table('carriers')
            ->where('counterparty_id', $counterpartyId)
            ->value('id');

        if (! is_numeric($carrierId)) {
            $carrierId = DB::table('carriers')->insertGetId([
                'counterparty_id' => $counterpartyId,
                'code' => $this->generateCarrierCode($counterpartyId),
                'is_active' => true,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existingCarrierOrderId = DB::table('carrier_orders')
            ->where('order_id', $orderId)
            ->value('id');

        if (is_numeric($existingCarrierOrderId)) {
            DB::table('carrier_orders')
                ->where('id', (int) $existingCarrierOrderId)
                ->update([
                    'carrier_id' => (int) $carrierId,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('carrier_orders')->insert([
            'order_id' => $orderId,
            'carrier_id' => (int) $carrierId,
            'number' => $this->generateCarrierOrderNumber($orderId),
            'status' => 'draft',
            'amount_without_vat' => null,
            'vat_rate' => null,
            'vat_amount' => null,
            'amount_with_vat' => null,
            'sent_at' => null,
            'responded_at' => null,
            'rejection_reason' => null,
            'payload_snapshot' => null,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function generateCarrierCode(int $counterpartyId): string
    {
        return 'CAR-CP-'.str_pad((string) $counterpartyId, 6, '0', STR_PAD_LEFT);
    }

    private function generateCarrierOrderNumber(int $orderId): string
    {
        return 'ПЕР-'.now()->format('Ymd').'-'.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{id: int, label: string, name: string, phone: string, inn: string}>
     */
    private function counterpartyOptions(): array
    {
        $rows = DB::table('counterparties')
            ->select('id', 'short_name', 'full_name', 'inn', 'phone')
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
                'name' => $baseLabel,
                'phone' => trim((string) ($row->phone ?? '')),
                'inn' => $inn,
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
            $value = trim((string) IntegrationSetting::getValue('yandex_maps', $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveOrderRouteParameter(string $order): int
    {
        abort_unless(ctype_digit($order), 404);

        $orderId = (int) $order;

        abort_if($orderId < 1, 404);

        return $orderId;
    }
}
