@php
    $isEdit = !empty($order);
    $orderNumber = $order['number'] ?? 'АД-20240315-001';
    $routeFromCity = $order['route']['from']['city'] ?? '';
    $routeFromAddress = $order['route']['from']['address'] ?? '';
    $routeToCity = $order['route']['to']['city'] ?? '';
    $routeToAddress = $order['route']['to']['address'] ?? '';
    $distanceRaw = $order['distance'] ?? '';
    $distanceValue = trim((string) preg_replace('/\D+/', '', (string) $distanceRaw));
    $dateValue = $order['created_at'] ?? now()->format('d.m.Y');

    $carrierName = $order['carrier']['name'] ?? null;
    $carrierPhone = $order['carrier']['phone'] ?? null;
    $carrierCounterpartyId = isset($order['carrier']['counterparty_id']) ? (int) $order['carrier']['counterparty_id'] : null;
    $driverName = $order['driver']['name'] ?? null;
    $driverPhone = $order['driver']['phone'] ?? null;
    $driverTransport = $order['driver']['car'] ?? null;
    $driverPlate = $order['driver']['plate'] ?? null;
    $senderName = $order['sender']['name'] ?? null;
    $senderPhone = $order['sender']['phone'] ?? null;
    $receiverName = $order['receiver']['name'] ?? null;
    $receiverPhone = $order['receiver']['phone'] ?? null;
    $fromLat = $order['route']['from']['lat'] ?? null;
    $fromLng = $order['route']['from']['lng'] ?? null;
    $toLat = $order['route']['to']['lat'] ?? null;
    $toLng = $order['route']['to']['lng'] ?? null;
    $fromCounterpartyId = isset($order['route']['from']['counterparty_id']) ? (int) $order['route']['from']['counterparty_id'] : null;
    $toCounterpartyId = isset($order['route']['to']['counterparty_id']) ? (int) $order['route']['to']['counterparty_id'] : null;
    $counterpartyOptions = is_iterable($counterparties ?? null) ? $counterparties : [];
    $intermediateStops = collect($order['route']['intermediate'] ?? [])
        ->filter(static fn ($stop) => is_array($stop))
        ->map(static fn (array $stop) => [
            'type' => in_array(($stop['type'] ?? 'unloading'), ['loading', 'unloading'], true) ? (string) $stop['type'] : 'unloading',
            'counterparty_id' => isset($stop['counterparty_id']) ? (int) $stop['counterparty_id'] : null,
            'city' => (string) ($stop['city'] ?? ''),
            'address' => (string) ($stop['address'] ?? ''),
            'lat' => is_numeric($stop['lat'] ?? null) ? (float) $stop['lat'] : null,
            'lng' => is_numeric($stop['lng'] ?? null) ? (float) $stop['lng'] : null,
        ])
        ->values()
        ->all();

    if (! filled($driverPlate) && filled($driverTransport) && str_contains((string) $driverTransport, ',')) {
        [$driverTransportParsed, $driverPlateParsed] = array_map(
            static fn ($value) => trim((string) $value),
            explode(',', (string) $driverTransport, 2)
        );

        $driverTransport = $driverTransportParsed;
        $driverPlate = $driverPlateParsed;
    }

    $hasParticipantValue = static fn ($value) => filled($value)
        && trim((string) $value) !== '—'
        && trim((string) $value) !== 'Не указан';

    $carrierFilled = $hasParticipantValue($carrierName) || $hasParticipantValue($carrierPhone);
    $driverFilled = $hasParticipantValue($driverName) || $hasParticipantValue($driverPhone) || $hasParticipantValue($driverTransport) || $hasParticipantValue($driverPlate);
    $senderFilled = $hasParticipantValue($senderName) || $hasParticipantValue($senderPhone);
    $receiverFilled = $hasParticipantValue($receiverName) || $hasParticipantValue($receiverPhone);

    $participantsFilled = collect([
        $carrierFilled ? '1' : null,
        $driverFilled ? '1' : null,
        $senderFilled ? '1' : null,
        $receiverFilled ? '1' : null,
    ])->filter()->count();

    $participantSearchRoute = route('orders.participants.counterparties.search');
    $participantResolveRoute = route('orders.participants.counterparties.resolve');
    $customerAutofillRoute = route('orders.customer.dadata.autofill');
    $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
    $customerId = old('customer_id', $customer['id'] ?? '');
    $customerCounterpartyId = old('customer_counterparty_id', $customer['counterparty_id'] ?? '');
    $customerContactId = old('customer_contact_id', $customer['contact_id'] ?? '');
    $customerName = old('customer_name', $customer['name'] ?? '');
    $customerContactName = old('customer_contact_name', $customer['contact_name'] ?? '');
    $customerPhone = old('customer_phone', $customer['phone'] ?? '');
    $customerEmail = old('customer_email', $customer['email'] ?? '');
    $customerInn = old('customer_inn', $customer['inn'] ?? '');
    $customerLegalAddress = old('customer_legal_address', $customer['legal_address'] ?? '');
    $customerLegalPostalCode = old('customer_legal_postal_code', $customer['legal_postal_code'] ?? '');
    $customerLegalRegion = old('customer_legal_region', $customer['legal_region'] ?? '');
    $customerLegalCity = old('customer_legal_city', $customer['legal_city'] ?? '');
    $customerLegalSettlement = old('customer_legal_settlement', $customer['legal_settlement'] ?? '');
    $customerLegalStreet = old('customer_legal_street', $customer['legal_street'] ?? '');
    $customerLegalHouse = old('customer_legal_house', $customer['legal_house'] ?? '');
    $customerLegalBlock = old('customer_legal_block', $customer['legal_block'] ?? '');
    $customerLegalFlat = old('customer_legal_flat', $customer['legal_flat'] ?? '');
    $customerLegalFiasId = old('customer_legal_fias_id', $customer['legal_fias_id'] ?? '');
    $customerLegalKladrId = old('customer_legal_kladr_id', $customer['legal_kladr_id'] ?? '');
    $customerLegalGeoLat = old('customer_legal_geo_lat', $customer['legal_geo_lat'] ?? '');
    $customerLegalGeoLon = old('customer_legal_geo_lon', $customer['legal_geo_lon'] ?? '');
    $customerLegalQc = old('customer_legal_qc', $customer['legal_qc'] ?? '');
    $customerLegalQcGeo = old('customer_legal_qc_geo', $customer['legal_qc_geo'] ?? '');
    $customerLegalAddressInvalid = old('customer_legal_address_invalid', array_key_exists('legal_address_invalid', $customer) ? ($customer['legal_address_invalid'] === null ? '' : ($customer['legal_address_invalid'] ? '1' : '0')) : '');
    $customerLegalAddressData = old('customer_legal_address_data', is_array($customer['legal_address_data'] ?? null)
        ? json_encode($customer['legal_address_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : ($customer['legal_address_data'] ?? ''));
@endphp

<form
    class="order-edit"
    action="{{ $isEdit ? route('orders.update', $order['id'] ?? 1) : route('orders.store') }}"
    method="post"
    data-participant-search-url="{{ $participantSearchRoute }}"
    data-participant-resolve-url="{{ $participantResolveRoute }}"
    data-customer-autofill-url="{{ $customerAutofillRoute }}"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <div class="order-edit__layout">
        <div class="order-edit__main">
            <section class="order-edit-card order-edit-head">
                <div class="order-edit-head__icon" aria-hidden="true">
                    <svg><use href="/icons/sprite.svg#icon-orders"></use></svg>
                </div>
                <div class="order-edit-head__content">
                    <h1 class="ad-h1">{{ $isEdit ? 'Редактирование заявки ' . $orderNumber : 'Новая заявка' }}</h1>
                    <p>{{ ($routeFromCity ?: 'Город отправления') . ' → ' . ($routeToCity ?: 'Город назначения') }}</p>
                </div>
                <span class="order-edit-head__badge">#{{ $orderNumber }}</span>
            </section>

            <section class="order-edit-card order-edit-form-card">
                <nav class="order-edit-tabs" aria-label="Разделы заявки">
                    <button type="button" class="ad-btn is-active" data-order-tab="customer" aria-controls="order-tab-panel-customer" aria-selected="true">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                        <span>Заказчик</span>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="route" aria-controls="order-tab-panel-route" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-trips"></use></svg>
                        <span>Маршрут</span>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="participants" aria-controls="order-tab-panel-participants" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        <span>Участники</span>
                        <em data-participants-progress>{{ $participantsFilled }}/4</em>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="cargo" aria-controls="order-tab-panel-cargo" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                        <span>Груз</span>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="cost" aria-controls="order-tab-panel-cost" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-rubl"></use></svg>
                        <span>Стоимость</span>
                    </button>
                </nav>

                <div class="order-edit-tab-panels">
                    <div id="order-tab-panel-customer" class="order-edit-tab-panel" data-order-tab-panel="customer">
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Информация о заказчике</h2>

                                <input type="hidden" name="customer_id" value="{{ $customerId }}">
                                <input type="hidden" name="customer_counterparty_id" value="{{ $customerCounterpartyId }}">
                                <input type="hidden" name="customer_contact_id" value="{{ $customerContactId }}">
                                <input type="hidden" name="customer_legal_address" value="{{ $customerLegalAddress }}">
                                <input type="hidden" name="customer_legal_postal_code" value="{{ $customerLegalPostalCode }}">
                                <input type="hidden" name="customer_legal_region" value="{{ $customerLegalRegion }}">
                                <input type="hidden" name="customer_legal_city" value="{{ $customerLegalCity }}">
                                <input type="hidden" name="customer_legal_settlement" value="{{ $customerLegalSettlement }}">
                                <input type="hidden" name="customer_legal_street" value="{{ $customerLegalStreet }}">
                                <input type="hidden" name="customer_legal_house" value="{{ $customerLegalHouse }}">
                                <input type="hidden" name="customer_legal_block" value="{{ $customerLegalBlock }}">
                                <input type="hidden" name="customer_legal_flat" value="{{ $customerLegalFlat }}">
                                <input type="hidden" name="customer_legal_fias_id" value="{{ $customerLegalFiasId }}">
                                <input type="hidden" name="customer_legal_kladr_id" value="{{ $customerLegalKladrId }}">
                                <input type="hidden" name="customer_legal_geo_lat" value="{{ $customerLegalGeoLat }}">
                                <input type="hidden" name="customer_legal_geo_lon" value="{{ $customerLegalGeoLon }}">
                                <input type="hidden" name="customer_legal_qc" value="{{ $customerLegalQc }}">
                                <input type="hidden" name="customer_legal_qc_geo" value="{{ $customerLegalQcGeo }}">
                                <input type="hidden" name="customer_legal_address_invalid" value="{{ $customerLegalAddressInvalid }}">
                                <input type="hidden" name="customer_legal_address_data" value="{{ $customerLegalAddressData }}">

                                <div class="order-edit-grid">
                                    <label class="order-participant-lookup order-customer-lookup" data-customer-lookup>
                                        <span>Название организации / ФИО <em>*</em></span>
                                        <div class="order-participant-lookup__control">
                                            <div class="order-edit-input order-edit-input--icon">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                                <input type="text" name="customer_name" value="{{ $customerName }}" placeholder='ООО &quot;Логистические решения&quot;' data-customer-search-input autocomplete="organization" required>
                                            </div>
                                            <button type="button" class="ad-btn" data-customer-autofill>Заполнить по DaData</button>
                                            <button type="button" class="ad-btn" data-customer-clear>Очистить</button>
                                        </div>
                                        <p class="order-participant-lookup__status" data-customer-search-status hidden></p>
                                        <div class="order-participant-lookup__results" data-customer-search-results hidden></div>
                                    </label>

                                    <div class="order-edit-grid order-edit-grid--two">
                                        <label>
                                            <span>Контактное лицо</span>
                                            <div class="order-edit-input order-edit-input--icon">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                                <input type="text" name="customer_contact_name" value="{{ $customerContactName }}" placeholder="Смирнов Алексей Петрович" autocomplete="name">
                                            </div>
                                        </label>
                                        <label>
                                            <span>Телефон <em>*</em></span>
                                            <div class="order-edit-input order-edit-input--icon">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                                <input type="text" name="customer_phone" value="{{ $customerPhone }}" placeholder="+7 (495) 123-45-67" autocomplete="tel" required>
                                            </div>
                                        </label>
                                        <label>
                                            <span>Email</span>
                                            <div class="order-edit-input order-edit-input--icon">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-auth-mail"></use></svg>
                                                <input type="email" name="customer_email" value="{{ $customerEmail }}" placeholder="info@company.ru" autocomplete="email">
                                            </div>
                                        </label>
                                        <label>
                                            <span>ИНН</span>
                                            <div class="order-edit-input order-edit-input--icon">
                                                <span class="order-edit-input__prefix order-edit-input__prefix--hash" aria-hidden="true">#</span>
                                                <input type="text" name="customer_inn" value="{{ $customerInn }}" placeholder="7712345678" inputmode="numeric" autocomplete="off">
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <section class="order-edit-note-card" aria-label="Подсказка по заказчику">
                                <div class="order-edit-note-card__icon" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                </div>
                                <div class="order-edit-note-card__body">
                                    <p class="order-edit-note-card__title">Информация о заказчике</p>
                                    <p class="order-edit-note-card__text">Укажите организацию или физическое лицо, которое является инициатором перевозки и выступает заказчиком услуги. Эти данные будут использованы в документах.</p>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-route" class="order-edit-tab-panel" data-order-tab-panel="route" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Откуда</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Грузоотправитель</span>
                                        <div class="order-edit-input">
                                            <select name="from_counterparty_id" data-custom-select data-sync-participant-role="sender">
                                                <option value="">По умолчанию: Заказчик</option>
                                                @foreach ($counterpartyOptions as $cp)
                                                    <option
                                                        value="{{ $cp['id'] }}"
                                                        data-counterparty-name="{{ $cp['name'] }}"
                                                        data-counterparty-phone="{{ $cp['phone'] ?: '—' }}"
                                                        data-counterparty-inn="{{ $cp['inn'] }}"
                                                        @selected((string) ($fromCounterpartyId ?? '') === (string) $cp['id'])
                                                    >
                                                        {{ $cp['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </label>
                                    <div></div>
                                    <label>
                                        <span>Город <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                            <input type="text" name="from_city" value="{{ $routeFromCity }}" placeholder="Москва" data-route-input="from_city" data-route-city-input autocomplete="off" list="order-city-suggest-from">
                                        </div>
                                        <datalist id="order-city-suggest-from"></datalist>
                                    </label>
                                    <label>
                                        <span>Адрес загрузки <em>*</em></span>
                                        <div class="order-edit-input">
                                            <input type="text" name="from_address" value="{{ $routeFromAddress }}" placeholder="ул. Ленина, 12" data-route-input="from_address" data-route-address-input autocomplete="off" list="order-address-suggest-from">
                                        </div>
                                        <datalist id="order-address-suggest-from"></datalist>
                                        <p class="order-route-point" data-route-point="from">{{ is_numeric($fromLat) && is_numeric($fromLng) ? 'Точка: '.number_format((float) $fromLat, 6, '.', '').', '.number_format((float) $fromLng, 6, '.', '') : 'Точка не определена' }}</p>
                                    </label>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Куда</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Грузополучатель</span>
                                        <div class="order-edit-input">
                                            <select name="to_counterparty_id" data-custom-select data-sync-participant-role="receiver">
                                                <option value="">По умолчанию: Заказчик</option>
                                                @foreach ($counterpartyOptions as $cp)
                                                    <option
                                                        value="{{ $cp['id'] }}"
                                                        data-counterparty-name="{{ $cp['name'] }}"
                                                        data-counterparty-phone="{{ $cp['phone'] ?: '—' }}"
                                                        data-counterparty-inn="{{ $cp['inn'] }}"
                                                        @selected((string) ($toCounterpartyId ?? '') === (string) $cp['id'])
                                                    >
                                                        {{ $cp['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </label>
                                    <div></div>
                                    <label>
                                        <span>Город <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                            <input type="text" name="to_city" value="{{ $routeToCity }}" placeholder="Санкт-Петербург" data-route-input="to_city" data-route-city-input autocomplete="off" list="order-city-suggest-to">
                                        </div>
                                        <datalist id="order-city-suggest-to"></datalist>
                                    </label>
                                    <label>
                                        <span>Адрес загрузки <em>*</em></span>
                                        <div class="order-edit-input">
                                            <input type="text" name="to_address" value="{{ $routeToAddress }}" placeholder="пр. Невский, 45" data-route-input="to_address" data-route-address-input autocomplete="off" list="order-address-suggest-to">
                                        </div>
                                        <datalist id="order-address-suggest-to"></datalist>
                                        <p class="order-route-point" data-route-point="to">{{ is_numeric($toLat) && is_numeric($toLng) ? 'Точка: '.number_format((float) $toLat, 6, '.', '').', '.number_format((float) $toLng, 6, '.', '') : 'Точка не определена' }}</p>
                                    </label>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Промежуточные пункты</h2>

                                <div class="order-route-stops" data-route-intermediate-list>
                                    @forelse ($intermediateStops as $stop)
                                        <div class="order-route-stop">
                                            <div class="order-edit-grid order-edit-grid--two">
                                                <label>
                                                    <span>Тип пункта</span>
                                                    <div class="order-edit-input">
                                                        <select name="intermediate_types[]" data-custom-select>
                                                            <option value="loading" @selected(($stop['type'] ?? 'unloading') === 'loading')>Загрузка</option>
                                                            <option value="unloading" @selected(($stop['type'] ?? 'unloading') === 'unloading')>Выгрузка</option>
                                                        </select>
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Контрагент (опционально)</span>
                                                    <div class="order-edit-input">
                                                        <select name="intermediate_counterparty_ids[]" data-custom-select>
                                                            <option value="">По умолчанию: Заказчик</option>
                                                            @foreach ($counterpartyOptions as $cp)
                                                                <option value="{{ $cp['id'] }}" @selected((string) ($stop['counterparty_id'] ?? '') === (string) $cp['id'])>
                                                                    {{ $cp['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Город</span>
                                                    <div class="order-edit-input order-edit-input--icon">
                                                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                                        <input
                                                            type="text"
                                                            name="intermediate_cities[]"
                                                            value="{{ $stop['city'] }}"
                                                            placeholder="Тверь"
                                                            autocomplete="off"
                                                            list="order-city-suggest-waypoints"
                                                            data-route-city-input
                                                        >
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Адрес</span>
                                                    <div class="order-edit-input">
                                                        <input
                                                            type="text"
                                                            name="intermediate_addresses[]"
                                                            value="{{ $stop['address'] }}"
                                                            placeholder="ул. Центральная, 1"
                                                            autocomplete="off"
                                                            list="order-address-suggest-waypoints"
                                                            data-route-address-input
                                                        >
                                                    </div>
                                                </label>
                                                <input type="hidden" name="intermediate_lats[]" value="{{ is_numeric($stop['lat'] ?? null) ? number_format((float) $stop['lat'], 6, '.', '') : '' }}">
                                                <input type="hidden" name="intermediate_lngs[]" value="{{ is_numeric($stop['lng'] ?? null) ? number_format((float) $stop['lng'], 6, '.', '') : '' }}">
                                            </div>
                                            <button type="button" class="ad-btn order-route-stop__remove" data-route-intermediate-remove>
                                                Удалить пункт
                                            </button>
                                        </div>
                                    @empty
                                        <p class="order-route-stops__empty" data-route-intermediate-empty>
                                            Промежуточные пункты не добавлены.
                                        </p>
                                    @endforelse
                                </div>

                                <datalist id="order-city-suggest-waypoints"></datalist>
                                <datalist id="order-address-suggest-waypoints"></datalist>

                                <template data-route-intermediate-template>
                                    <div class="order-route-stop">
                                        <div class="order-edit-grid order-edit-grid--two">
                                            <label>
                                                <span>Тип пункта</span>
                                                <div class="order-edit-input">
                                                    <select name="intermediate_types[]" data-custom-select>
                                                        <option value="loading">Загрузка</option>
                                                        <option value="unloading" selected>Выгрузка</option>
                                                    </select>
                                                </div>
                                            </label>
                                            <label>
                                                <span>Контрагент (опционально)</span>
                                                <div class="order-edit-input">
                                                    <select name="intermediate_counterparty_ids[]" data-custom-select>
                                                        <option value="" selected>По умолчанию: Заказчик</option>
                                                        @foreach ($counterpartyOptions as $cp)
                                                            <option value="{{ $cp['id'] }}">{{ $cp['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </label>
                                            <label>
                                                <span>Город</span>
                                                <div class="order-edit-input order-edit-input--icon">
                                                    <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                                    <input
                                                        type="text"
                                                        name="intermediate_cities[]"
                                                        placeholder="Тверь"
                                                        autocomplete="off"
                                                        list="order-city-suggest-waypoints"
                                                        data-route-city-input
                                                    >
                                                </div>
                                            </label>
                                            <label>
                                                <span>Адрес</span>
                                                <div class="order-edit-input">
                                                    <input
                                                        type="text"
                                                        name="intermediate_addresses[]"
                                                        placeholder="ул. Центральная, 1"
                                                        autocomplete="off"
                                                        list="order-address-suggest-waypoints"
                                                        data-route-address-input
                                                    >
                                                </div>
                                            </label>
                                            <input type="hidden" name="intermediate_lats[]" value="">
                                            <input type="hidden" name="intermediate_lngs[]" value="">
                                        </div>
                                        <button type="button" class="ad-btn order-route-stop__remove" data-route-intermediate-remove>
                                            Удалить пункт
                                        </button>
                                    </div>
                                </template>

                                <div class="order-route-stops__actions">
                                    <button type="button" class="ad-btn" data-route-intermediate-add>
                                        Добавить промежуточный пункт
                                    </button>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Параметры</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Расстояние (км) <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-driver-arrow"></use></svg>
                                            <input type="text" name="distance" value="{{ $distanceValue }}" placeholder="Рассчитывается автоматически" readonly data-route-distance-input>
                                        </div>
                                    </label>
                                    <label>
                                        <span>Дата <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-date"></use></svg>
                                            <input type="text" name="created_at" value="{{ $dateValue }}" placeholder="15.03.2026">
                                        </div>
                                    </label>
                                </div>

                                <div class="order-edit-route-tools">
                                    <button type="button" class="ad-btn" data-route-geocode-button>
                                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-driver-arrow"></use></svg>
                                        <span>Определить точки маршрута</span>
                                    </button>
                                    <p class="order-edit-route-status" data-route-status role="status" aria-live="polite"></p>
                                </div>

                                <input type="hidden" name="from_lat" value="{{ is_numeric($fromLat) ? number_format((float) $fromLat, 6, '.', '') : '' }}" data-route-coord="from_lat">
                                <input type="hidden" name="from_lng" value="{{ is_numeric($fromLng) ? number_format((float) $fromLng, 6, '.', '') : '' }}" data-route-coord="from_lng">
                                <input type="hidden" name="to_lat" value="{{ is_numeric($toLat) ? number_format((float) $toLat, 6, '.', '') : '' }}" data-route-coord="to_lat">
                                <input type="hidden" name="to_lng" value="{{ is_numeric($toLng) ? number_format((float) $toLng, 6, '.', '') : '' }}" data-route-coord="to_lng">
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-participants" class="order-edit-tab-panel" data-order-tab-panel="participants" hidden>
                        <div class="order-edit-participants-tab">
                            <h2 class="order-edit-participants-tab__title">Стороны перевозки</h2>

                            <section class="order-participant-panel order-participant-panel--carrier is-open" data-participant-panel data-participant-role="carrier" data-participant-filled="{{ $carrierFilled ? '1' : '0' }}">
                                <button type="button" class="order-participant-panel__toggle" data-participant-toggle aria-expanded="true" aria-controls="participant-panel-carrier-body">
                                    <span class="order-participant-panel__icon order-participant-panel__icon--carrier" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                    </span>
                                    <span class="order-participant-panel__summary">
                                        <span class="order-participant-panel__name">Грузоперевозчик</span>
                                        <span class="order-participant-panel__meta" data-participant-summary="meta">{{ $carrierName ?: 'Транспортная компания, выполняющая перевозку' }}</span>
                                    </span>
                                    <span class="order-participant-panel__status {{ $carrierFilled ? 'order-participant-panel__status--filled order-participant-panel__status--carrier' : 'order-participant-panel__status--empty' }}" data-participant-badge>{{ $carrierFilled ? 'Заполнен' : 'Не указан' }}</span>
                                    <span class="order-participant-panel__arrow" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                    </span>
                                </button>
                                <div id="participant-panel-carrier-body" class="order-participant-panel__body" data-participant-body>
                                    <div class="order-participant-lookup" data-participant-lookup data-target-mode="hidden" data-target-input-name="carrier_counterparty_id">
                                        <input type="hidden" name="carrier_counterparty_id" value="{{ $carrierCounterpartyId ?: '' }}" data-participant-hidden-input>
                                        <label class="order-participant-lookup__field">
                                            <span>Подбор перевозчика</span>
                                            <div class="order-participant-lookup__control">
                                                <input type="text" value="{{ $carrierName && $carrierName !== '—' ? $carrierName : '' }}" placeholder="Начните вводить название, ИНН или телефон" data-participant-search-input>
                                                <button type="button" class="ad-btn" data-participant-clear>Очистить</button>
                                            </div>
                                        </label>
                                        <p class="order-participant-lookup__status" data-participant-search-status hidden></p>
                                        <div class="order-participant-lookup__results" data-participant-search-results hidden></div>
                                    </div>
                                    <div class="order-participant-panel__fields order-participant-panel__fields--two">
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Название / ФИО</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                                <span data-participant-field="name">{{ $carrierName ?: 'Не указан' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Телефон</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                                <span data-participant-field="phone">{{ $carrierPhone ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="order-participant-panel order-participant-panel--driver is-open" data-participant-panel data-participant-role="driver" data-participant-filled="{{ $driverFilled ? '1' : '0' }}">
                                <button type="button" class="order-participant-panel__toggle" data-participant-toggle aria-expanded="true" aria-controls="participant-panel-driver-body">
                                    <span class="order-participant-panel__icon order-participant-panel__icon--driver" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                    </span>
                                    <span class="order-participant-panel__summary">
                                        <span class="order-participant-panel__name">Водитель</span>
                                        <span class="order-participant-panel__meta" data-participant-summary="meta">{{ $driverName ?: 'Не указан' }}</span>
                                    </span>
                                    <span class="order-participant-panel__status {{ $driverFilled ? 'order-participant-panel__status--filled order-participant-panel__status--driver' : 'order-participant-panel__status--empty' }}" data-participant-badge>{{ $driverFilled ? 'Заполнен' : 'Не указан' }}</span>
                                    <span class="order-participant-panel__arrow" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                    </span>
                                </button>
                                <div id="participant-panel-driver-body" class="order-participant-panel__body" data-participant-body>
                                    <div class="order-participant-panel__fields order-participant-panel__fields--two">
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">ФИО</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                                <span>{{ $driverName ?: 'Не указан' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Телефон</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                                <span>{{ $driverPhone ?: '—' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Транспорт</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-truck"></use></svg>
                                                <span>{{ $driverTransport ?: '—' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Гос. номер</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-orders"></use></svg>
                                                <span>{{ $driverPlate ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="order-participant-panel order-participant-panel--sender is-open" data-participant-panel data-participant-role="sender" data-participant-filled="{{ $senderFilled ? '1' : '0' }}">
                                <button type="button" class="order-participant-panel__toggle" data-participant-toggle aria-expanded="true" aria-controls="participant-panel-sender-body">
                                    <span class="order-participant-panel__icon order-participant-panel__icon--sender" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                                    </span>
                                    <span class="order-participant-panel__summary">
                                        <span class="order-participant-panel__name">Грузоотправитель</span>
                                        <span class="order-participant-panel__meta" data-participant-summary="meta">{{ $senderName ?: 'Не указан' }}</span>
                                    </span>
                                    <span class="order-participant-panel__status {{ $senderFilled ? 'order-participant-panel__status--filled order-participant-panel__status--sender' : 'order-participant-panel__status--empty' }}" data-participant-badge>{{ $senderFilled ? 'Заполнен' : 'Не указан' }}</span>
                                    <span class="order-participant-panel__arrow" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                    </span>
                                </button>
                                <div id="participant-panel-sender-body" class="order-participant-panel__body" data-participant-body>
                                    <div class="order-participant-lookup" data-participant-lookup data-target-mode="select" data-target-select-name="from_counterparty_id">
                                        <label class="order-participant-lookup__field">
                                            <span>Подбор грузоотправителя</span>
                                            <div class="order-participant-lookup__control">
                                                <input type="text" value="{{ $senderName && $senderName !== '—' ? $senderName : '' }}" placeholder="Начните вводить название, ИНН или телефон" data-participant-search-input>
                                                <button type="button" class="ad-btn" data-participant-clear>Очистить</button>
                                            </div>
                                        </label>
                                        <p class="order-participant-lookup__status" data-participant-search-status hidden></p>
                                        <div class="order-participant-lookup__results" data-participant-search-results hidden></div>
                                    </div>
                                    <div class="order-participant-panel__fields order-participant-panel__fields--two">
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">ФИО / Организация</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                                <span data-participant-field="name">{{ $senderName ?: 'Не указан' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Телефон</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                                <span data-participant-field="phone">{{ $senderPhone ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="order-participant-panel order-participant-panel--receiver is-open" data-participant-panel data-participant-role="receiver" data-participant-filled="{{ $receiverFilled ? '1' : '0' }}">
                                <button type="button" class="order-participant-panel__toggle" data-participant-toggle aria-expanded="true" aria-controls="participant-panel-receiver-body">
                                    <span class="order-participant-panel__icon order-participant-panel__icon--receiver" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-packagecheck"></use></svg>
                                    </span>
                                    <span class="order-participant-panel__summary">
                                        <span class="order-participant-panel__name">Грузополучатель</span>
                                        <span class="order-participant-panel__meta" data-participant-summary="meta">{{ $receiverName ?: 'Не указан' }}</span>
                                    </span>
                                    <span class="order-participant-panel__status {{ $receiverFilled ? 'order-participant-panel__status--filled order-participant-panel__status--receiver' : 'order-participant-panel__status--empty' }}" data-participant-badge>{{ $receiverFilled ? 'Заполнен' : 'Не указан' }}</span>
                                    <span class="order-participant-panel__arrow" aria-hidden="true">
                                        <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                    </span>
                                </button>
                                <div id="participant-panel-receiver-body" class="order-participant-panel__body" data-participant-body>
                                    <div class="order-participant-lookup" data-participant-lookup data-target-mode="select" data-target-select-name="to_counterparty_id">
                                        <label class="order-participant-lookup__field">
                                            <span>Подбор грузополучателя</span>
                                            <div class="order-participant-lookup__control">
                                                <input type="text" value="{{ $receiverName && $receiverName !== '—' ? $receiverName : '' }}" placeholder="Начните вводить название, ИНН или телефон" data-participant-search-input>
                                                <button type="button" class="ad-btn" data-participant-clear>Очистить</button>
                                            </div>
                                        </label>
                                        <p class="order-participant-lookup__status" data-participant-search-status hidden></p>
                                        <div class="order-participant-lookup__results" data-participant-search-results hidden></div>
                                    </div>
                                    <div class="order-participant-panel__fields order-participant-panel__fields--two">
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">ФИО / Организация</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                                <span data-participant-field="name">{{ $receiverName ?: 'Не указан' }}</span>
                                            </div>
                                        </div>
                                        <div class="order-participant-panel__field">
                                            <span class="order-participant-panel__label">Телефон</span>
                                            <div class="order-participant-panel__value">
                                                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                                <span data-participant-field="phone">{{ $receiverPhone ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-cargo" class="order-edit-tab-panel" data-order-tab-panel="cargo" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Груз</h2>
                                <div class="order-edit-tab-note">Раздел груза будет заполнен на следующем шаге.</div>
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-cost" class="order-edit-tab-panel" data-order-tab-panel="cost" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Стоимость</h2>
                                <div class="order-edit-tab-note">Раздел стоимости будет заполнен на следующем шаге.</div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="order-edit__side" aria-label="Панель заявки">
            <section class="order-edit-card order-edit-side-card">
                <h3>Действия</h3>
                <div class="order-edit-actions">
                    <button type="submit" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-save"></use></svg>
                        <span>Сохранить изменения</span>
                    </button>
                    <a class="ad-btn" href="{{ $backRoute }}">Отмена</a>
                    <button type="button" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-trash"></use></svg>
                        <span>Удалить заявку</span>
                    </button>
                </div>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Участники</h3>
                <ul class="order-edit-participants">
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>Грузоперевозчик</span>
                        <span class="order-edit-participants__state {{ $carrierFilled ? 'is-ok' : '' }}" data-participant-side-state="carrier">{{ $carrierFilled ? '✓' : '—' }}</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>Водитель</span>
                        <span class="order-edit-participants__state {{ $driverFilled ? 'is-ok' : '' }}" data-participant-side-state="driver">{{ $driverFilled ? '✓' : '—' }}</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-package"></use></svg>Грузоотправитель</span>
                        <span class="order-edit-participants__state {{ $senderFilled ? 'is-ok' : '' }}" data-participant-side-state="sender">{{ $senderFilled ? '✓' : '—' }}</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-packagecheck"></use></svg>Грузополучатель</span>
                        <span class="order-edit-participants__state {{ $receiverFilled ? 'is-ok' : '' }}" data-participant-side-state="receiver">{{ $receiverFilled ? '✓' : '—' }}</span>
                    </li>
                </ul>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Сводка</h3>
                <ul class="order-edit-summary">
                    <li><span>Маршрут</span><em class="is-green">Заполнен</em></li>
                    <li><span>Участники</span><em class="is-blue" data-participants-progress>{{ $participantsFilled }} / 4</em></li>
                    <li><span>Груз</span><em class="is-gray">Не указан</em></li>
                </ul>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Информация</h3>
                <div class="order-edit-info">
                    <div>
                        <span class="order-edit-info__label">Номер заявки</span>
                        <span class="order-edit-info__value">{{ $orderNumber }}</span>
                    </div>
                    <div>
                        <span class="order-edit-info__label">Дата создания</span>
                        <span class="order-edit-info__value">{{ $order['created_at'] ?? '15.03.2024' }}</span>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</form>
